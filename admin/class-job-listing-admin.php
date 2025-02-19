<?php
/**
 * Job Listing Admin Class
 *
 * Handles all admin functionality for the Job Listing plugin
 *
 * @package JobListingPlugin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Job_Listing_Admin {
    /**
     * Plugin options
     *
     * @var array
     */
    private $options;

    /**
     * Plugin instance
     *
     * @var Job_Listing_Plugin
     */
    private $plugin;

    /**
     * Active admin tab
     *
     * @var string
     */
    private $active_tab = 'settings';

    /**
     * Constructor
     */
    public function __construct() {
        $this->options = get_option('job_listing_settings', []);
    }

    /**
     * Initialize the admin functionality
     *
     * @return void
     */
    public function init() {
        // Clean up old transients and temporary data
        $this->cleanup_old_data();
        
        $this->options = get_option('job_listing_settings', []);
        $this->plugin = Job_Listing_Plugin::get_instance();
        
        // Set active tab
        if (isset($_GET['tab'])) {
            $this->active_tab = sanitize_key($_GET['tab']);
        } elseif (empty($this->options['setup_complete'])) {
            // If setup is not complete, default to setup tab
            $this->active_tab = 'setup';
        }
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_save_job_listing_setup', [$this, 'ajax_save_setup']);
    }

    /**
     * Clean up old plugin data
     *
     * @return void
     */
    private function cleanup_old_data() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM $wpdb->options 
            WHERE option_name LIKE '_transient_job_listing_%' 
            AND option_value < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY))"
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_job-listing-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'job-listing-admin-style',
            JLP_PLUGIN_URL . 'admin/css/admin.css',
            [],
            JLP_VERSION
        );
        
        wp_enqueue_script(
            'job-listing-admin',
            JLP_PLUGIN_URL . 'admin/js/admin.js',
            [],
            JLP_VERSION,
            true
        );
        
        // For time picker
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_localize_script('job-listing-admin', 'jobListingAdmin', array(
            'refreshEndpoint' => rest_url('job-listing/v1/refresh'),
            'initializeEndpoint' => rest_url('job-listing/v1/initialize-schedule'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('job_listing_admin'),
            'setupComplete' => !empty($this->options['setup_complete']),
            'i18n' => array(
                'saving' => __('Saving...', 'job-listing-plugin'),
                'updateSetup' => __('Update Setup', 'job-listing-plugin'),
                'completeSetup' => __('Complete Setup', 'job-listing-plugin'),
                'refreshing' => __('Refreshing...', 'job-listing-plugin'),
                'error' => __('Error', 'job-listing-plugin'),
                'success' => __('Success', 'job-listing-plugin')
            )
        ));
        
    }

    /**
     * Add admin menu items
     *
     * @return void
     */
    public function add_admin_menu() {
        add_options_page(
            'Job Listing Settings',
            'Job Listing',
            'manage_options',
            'job-listing-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Initialize plugin settings
     *
     * @return void
     */
    public function init_settings() {
        register_setting(
            'job_listing_settings',
            'job_listing_settings',
            [$this, 'validate_settings']
        );

        // Only register settings fields if setup is complete
        if (!empty($this->options['setup_complete'])) {
            add_settings_section(
                'job_listing_api_section',
                'API Settings',
                [$this, 'render_api_section_info'],
                'job-listing-settings'
            );

            add_settings_field(
                'organization_id',
                'Organization ID',
                [$this, 'render_organization_id_field'],
                'job-listing-settings',
                'job_listing_api_section'
            );
            
            add_settings_section(
                'job_listing_schedule_section',
                'Scheduled API Fetches',
                [$this, 'render_schedule_section_info'],
                'job-listing-settings'
            );
        }
    }

    /**
     * Render API section information
     *
     * @return void
     */
    public function render_api_section_info() {
        echo '<p>' . esc_html__('Configure your Ashby API settings below.', 'job-listing-plugin') . '</p>';
    }

    /**
     * Render organization ID field
     *
     * @return void
     */
    public function render_organization_id_field() {
        $organization_id = isset($this->options['organization_id']) ? $this->options['organization_id'] : '';
        ?>
        <input type="text" 
               id="organization_id" 
               name="job_listing_settings[organization_id]" 
               value="<?php echo esc_attr($organization_id); ?>" 
               class="regular-text"
        />
        <p class="description">
            <?php _e('Enter your Ashby organization ID. You can find this in your Ashby job board URL.', 'job-listing-plugin'); ?>
        </p>
        <?php
    }

    /**
     * Render schedule section information
     *
     * @return void
     */
    public function render_schedule_section_info() {
        echo '<p>' . esc_html__('Configure when the plugin should fetch job data from Ashby.', 'job-listing-plugin') . '</p>';
    }

    /**
     * Validate plugin settings
     *
     * @param array $input Input settings
     * @return array Validated settings
     */
    public function validate_settings($input) {
        $new_input = [];
        
        if (isset($input['organization_id'])) {
            $new_input['organization_id'] = sanitize_text_field($input['organization_id']);
        }
        
        if (isset($input['schedule_times']) && is_array($input['schedule_times'])) {
            $new_input['schedule_times'] = array_map(
                'sanitize_text_field',
                $this->validate_schedule_times($input['schedule_times'])
            );
        }
        
        return $new_input;
    }

    /**
     * Validate schedule times
     *
     * @param array $times Array of times
     * @return array Validated times
     */
    private function validate_schedule_times($times) {
        $validated_times = [];
        $seen_times = [];
        
        foreach ($times as $time) {
            if (!isset($seen_times[$time])) {
                $seen_times[$time] = true;
                if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
                    $validated_times[] = $time;
                }
            }
        }
        
        return $validated_times;
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Job Listing Settings</h1>
            
            <?php if (empty($this->options['setup_complete'])): ?>
                <div class="notice notice-warning">
                    <p><strong>Setup Required:</strong> Please complete the initial setup before using the plugin.</p>
                </div>
            <?php endif; ?>
            
            <h2 class="nav-tab-wrapper">
                <?php if (!empty($this->options['setup_complete'])): ?>
                    <a href="?page=job-listing-settings&tab=settings" 
                       class="nav-tab <?php echo $this->active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                        Settings
                    </a>
                    <a href="?page=job-listing-settings&tab=schedule" 
                       class="nav-tab <?php echo $this->active_tab == 'schedule' ? 'nav-tab-active' : ''; ?>">
                        Schedule
                    </a>
                    <a href="?page=job-listing-settings&tab=database" 
                       class="nav-tab <?php echo $this->active_tab == 'database' ? 'nav-tab-active' : ''; ?>">
                        Database
                    </a>
                <?php endif; ?>
                <a href="?page=job-listing-settings&tab=setup" 
                   class="nav-tab <?php echo $this->active_tab == 'setup' ? 'nav-tab-active' : ''; ?>">
                    <?php echo empty($this->options['setup_complete']) ? 'Initial Setup' : 'Modify Setup'; ?>
                </a>
            </h2>
            
            <div class="tab-content">
                <?php
                switch ($this->active_tab) {
                    case 'setup':
                        $this->render_setup_tab();
                        break;
                    case 'settings':
                        if (!empty($this->options['setup_complete'])) {
                            $this->render_settings_tab();
                        }
                        break;
                    case 'schedule':
                        if (!empty($this->options['setup_complete'])) {
                            $this->render_schedule_tab();
                        }
                        break;
                    case 'database':
                        if (!empty($this->options['setup_complete'])) {
                            $this->render_database_tab();
                        }
                        break;
                    default:
                        $this->render_setup_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the setup tab
     *
     * @return void
     */
    private function render_setup_tab() {
        try {
            $fetch_info = $this->plugin->get_last_fetch_info();
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error: ' . 
                esc_html($e->getMessage()) . '</p></div>';
            $fetch_info = [];
        }

        $organization_id = $fetch_info['organization_id'] ?? '';
        $setup_complete = $fetch_info['setup_complete'] ?? false;
        $scheduled_times = isset($this->options['schedule_times']) ? 
            $this->options['schedule_times'] : [];
        
        include JLP_PLUGIN_DIR . 'admin/templates/setup-tab.php';
    }

    /**
     * Get time options for select field
     *
     * @param string $selected Selected time
     * @return string HTML options
     */
    private function get_time_options($selected = '') {
        $options = '';
        
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $time_value = sprintf('%02d:%02d', $hour, $minute);
                $time_display = date('g:i A', strtotime($time_value));
                
                $options .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($time_value),
                    selected($time_value, $selected, false),
                    esc_html($time_display)
                );
            }
        }
        
        return $options;
    }

    /**
     * Handle AJAX setup save
     *
     * @return void
     */
    public function ajax_save_setup() {
        if (!defined('WPINC')) {
            die;
        }

        if (!check_ajax_referer('job_listing_admin', 'nonce', false)) {
            wp_send_json_error([
                'message' => 'Invalid security token'
            ], 403);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Permission denied'
            ], 403);
            return;
        }
        
        $organization_id = sanitize_text_field($_POST['organization_id'] ?? '');
        $schedule_times = [];
        
        // Collect valid schedule times
        for ($i = 1; $i <= 3; $i++) {
            $time_key = "schedule_time_{$i}";
            if (!empty($_POST[$time_key])) {
                $time_value = sanitize_text_field($_POST[$time_key]);
                if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time_value)) {
                    $schedule_times[] = $time_value;
                }
            }
        }
        
        if (empty($organization_id)) {
            wp_send_json_error(['message' => 'Organization ID is required']);
            return;
        }
        
        if (empty($schedule_times)) {
            wp_send_json_error(['message' => 'At least one schedule time is required']);
            return;
        }
        
        try {
            $result = $this->plugin->save_setup($organization_id, $schedule_times);
            
            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message()
                ]);
                return;
            }
            
            wp_send_json_success([
                'message' => 'Setup completed successfully!',
                'redirect' => admin_url('options-general.php?page=job-listing-settings&tab=schedule'),
                'data' => $result
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Render the settings tab
     *
     * @return void
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('job_listing_settings');
            do_settings_sections('job-listing-settings');
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Render the schedule tab
     *
     * @return void
     */
    private function render_schedule_tab() {
        try {
            $fetch_info = $this->plugin->get_last_fetch_info();
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error: ' . 
                esc_html($e->getMessage()) . '</p></div>';
            return;
        }
        
        include JLP_PLUGIN_DIR . 'admin/templates/schedule-tab.php';
    }

    /**
     * Render the database tab
     *
     * @return void
     */
    private function render_database_tab() {
        ?>
        <div class="job-listing-database-info">
            <h2>Database Information</h2>
            <?php $this->render_database_info(); ?>
        </div>
        <?php
    }

    /**
     * Render database information
     *
     * @return void
     */
    private function render_database_info() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'job_listings';
        
        // Check if table exists
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        
        if (!$table_exists) {
            echo '<div class="notice notice-error"><p>Job listings table does not exist!</p></div>';
            return;
        }
        
        $job_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($wpdb->last_error) {
            echo '<div class="notice notice-error"><p>Database error: ' . 
                esc_html($wpdb->last_error) . '</p></div>';
            return;
        }
        
        include JLP_PLUGIN_DIR . 'admin/templates/database-info.php';
    }
}

/**
 * Add uninstall warning notice
 *
 * @return void
 */
function jlp_add_uninstall_warning() {
    ?>
    <div class="notice notice-warning">
        <p>
            <?php _e('Warning: Uninstalling this plugin will permanently delete all job listings, 
            applications, and related data. This action cannot be undone.', 'job-listing-plugin'); ?>
        </p>
    </div>
    <?php
}
