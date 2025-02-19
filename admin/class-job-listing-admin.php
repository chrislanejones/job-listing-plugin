<?php
class Job_Listing_Admin {
    private $options;
    private $plugin;
    private $active_tab = 'settings';

    public function init() {
        $this->options = get_option('job_listing_settings');
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
        
        wp_localize_script('job-listing-admin', 'jobListingAdmin', [
            'refreshEndpoint' => rest_url('job-listing/v1/refresh'),
            'initializeEndpoint' => rest_url('job-listing/v1/initialize-schedule'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('job_listing_admin'),
            'setupComplete' => !empty($this->options['setup_complete'])
        ]);
    }

    public function add_admin_menu() {
        add_options_page(
            'Job Listing Settings',
            'Job Listing',
            'manage_options',
            'job-listing-settings',
            [$this, 'render_settings_page']
        );
    }

    public function init_settings() {
        register_setting('job_listing_settings', 'job_listing_settings');

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
                    <a href="?page=job-listing-settings&tab=settings" class="nav-tab <?php echo $this->active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                    <a href="?page=job-listing-settings&tab=schedule" class="nav-tab <?php echo $this->active_tab == 'schedule' ? 'nav-tab-active' : ''; ?>">Schedule</a>
                    <a href="?page=job-listing-settings&tab=database" class="nav-tab <?php echo $this->active_tab == 'database' ? 'nav-tab-active' : ''; ?>">Database</a>
                <?php endif; ?>
                <a href="?page=job-listing-settings&tab=setup" class="nav-tab <?php echo $this->active_tab == 'setup' ? 'nav-tab-active' : ''; ?>">
                    <?php echo empty($this->options['setup_complete']) ? 'Initial Setup' : 'Modify Setup'; ?>
                </a>
            </h2>
            
            <div class="tab-content">
                <?php
                if ($this->active_tab == 'setup') {
                    $this->render_setup_tab();
                } elseif ($this->active_tab == 'settings' && !empty($this->options['setup_complete'])) {
                    $this->render_settings_tab();
                } elseif ($this->active_tab == 'schedule' && !empty($this->options['setup_complete'])) {
                    $this->render_schedule_tab();
                } elseif ($this->active_tab == 'database' && !empty($this->options['setup_complete'])) {
                    $this->render_database_tab();
                } else {
                    $this->render_setup_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_setup_tab() {
        $fetch_info = $this->plugin->get_last_fetch_info();
        $organization_id = $fetch_info['organization_id'] ?? '';
        $setup_complete = $fetch_info['setup_complete'] ?? false;
        $scheduled_times = isset($this->options['schedule_times']) ? $this->options['schedule_times'] : [];
        ?>
        <div class="setup-wizard-container">
            <h2><?php echo $setup_complete ? 'Modify Setup' : 'Initial Setup Wizard'; ?></h2>
            
            <p>Please provide your Ashby organization ID and select when you would like the plugin to fetch job data.</p>
            
            <div class="job-listing-setup-form">
                <div class="form-group">
                    <label for="organization_id">Organization ID</label>
                    <input type="text" id="organization_id" name="organization_id" class="regular-text" 
                           value="<?php echo esc_attr($organization_id); ?>" 
                           placeholder="your-company" required>
                    <p class="description">
                        Enter your Ashby organization ID. You can find this in your Ashby job board URL.
                        For example, if your job board URL is "jobs.ashbyhq.com/your-company", your organization ID is "your-company".
                    </p>
                </div>
                
                <div class="form-group schedule-times-group">
                    <label>Schedule Times</label>
                    <p class="description">Choose up to three times when the plugin should fetch job data daily. All times are in 24-hour format.</p>
                    
                    <div class="schedule-times-container">
                        <div class="schedule-time-wrapper">
                            <select name="schedule_time_1" id="schedule_time_1" required>
                                <option value="">Select Time</option>
                                <?php echo $this->get_time_options($scheduled_times[0] ?? '08:00'); ?>
                            </select>
                        </div>
                        
                        <div class="schedule-time-wrapper">
                            <select name="schedule_time_2" id="schedule_time_2">
                                <option value="">Select Time (Optional)</option>
                                <?php echo $this->get_time_options($scheduled_times[1] ?? '14:00'); ?>
                            </select>
                        </div>
                        
                        <div class="schedule-time-wrapper">
                            <select name="schedule_time_3" id="schedule_time_3">
                                <option value="">Select Time (Optional)</option>
                                <?php echo $this->get_time_options($scheduled_times[2] ?? '20:00'); ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="submit-container">
                    <div id="setup-response" class="setup-response"></div>
                    <button type="button" id="save-setup-button" class="button button-primary">
                        <?php echo $setup_complete ? 'Update Setup' : 'Complete Setup'; ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_time_options($selected = '') {
        $options = '';
        
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $time_value = sprintf('%02d:%02d', $hour, $minute);
                $time_display = date('g:i A', strtotime($time_value));
                
                $options .= '<option value="' . $time_value . '"' . 
                            ($time_value === $selected ? ' selected' : '') . 
                            '>' . $time_display . '</option>';
            }
        }
        
        return $options;
    }
    
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
    
    private function render_schedule_tab() {
        $fetch_info = $this->plugin->get_last_fetch_info();
        ?>
        <div class="schedule-info-wrapper">
            <h2>Scheduled Job Data Fetches</h2>
            
            <p>The plugin automatically fetches job data from the Ashby API at the following times:</p>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Last Fetch</th>
                        <th>Schedule Times (Daily)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td id="last-fetch-time">
                            <?php echo $fetch_info['last_fetch'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fetch_info['last_fetch'])) : 'Never'; ?>
                        </td>
                        <td>
                            <?php if (!empty($fetch_info['scheduled_times'])): ?>
                                <ul class="schedule-times-list">
                                    <?php foreach ($fetch_info['scheduled_times'] as $time): ?>
                                        <li><?php echo date_i18n('g:i A', strtotime($time)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No scheduled times. Please complete setup.</p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" id="refresh-jobs-button" class="button">
                                Fetch Jobs Now
                            </button>
                            <span id="refresh-status"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p class="schedule-note"><em>Note: These times are based on the server time zone. Current server time: <?php echo date_i18n('g:i A'); ?></em></p>
        </div>
        <?php
    }
    
    private function render_database_tab() {
        ?>
        <div class="job-listing-database-info">
            <h2>Database Information</h2>
            <?php $this->render_database_info(); ?>
        </div>
        <?php
    }
    
    private function render_database_info() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'job_listings';
        $job_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Total Jobs in Database</th>
                    <th>Table Name</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo intval($job_count); ?></td>
                    <td><?php echo esc_html($table_name); ?></td>
                    <td>
                        <?php 
                        $last_updated = $wpdb->get_var("SELECT MAX(date_updated) FROM $table_name");
                        echo $last_updated ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_updated)) : 'Never';
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    public function ajax_save_setup() {
        check_ajax_referer('job_listing_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }
        
        $organization_id = sanitize_text_field($_POST['organization_id'] ?? '');
        $schedule_times = [];
        
        // Collect valid schedule times
        for ($i = 1; $i <= 3; $i++) {
            $time_key = "schedule_time_{$i}";
            if (!empty($_POST[$time_key])) {
                $time_value = sanitize_text_field($_POST[$time_key]);
                // Validate time format (HH:MM)
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
        
        // Save setup and initialize scheduling
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
    }
}