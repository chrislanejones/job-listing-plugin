<?php
namespace JobListingPlugin;

class Job_Listing_Admin {
    private static $instance = null;
    private $plugin;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin = Job_Listing_Plugin::get_instance();
    }

    public function init() {
        // Admin menu and page setup
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Admin AJAX actions
        add_action('wp_ajax_job_listing_save_setup', [$this, 'save_setup_ajax']);
        add_action('wp_ajax_job_listing_refresh_jobs', [$this, 'refresh_jobs_ajax']);
    }

    public function add_admin_menu() {
        add_options_page(
            'Job Listing Settings',
            'Job Listing',
            'manage_options',
            'job-listing-settings',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        // Get last fetch info
        $last_fetch_info = $this->plugin->get_last_fetch_info();

        // Get WordPress timezone
        $timezone = wp_timezone();
        $timezone_string = $timezone->getName();

        ?>
        <div class="wrap">
            <h1>Job Listing Settings</h1>
            <div class="job-listing-setup">
                <form id="job-listing-setup-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="organization-id">Organization ID</label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="organization-id" 
                                    name="organization_id" 
                                    value="<?php echo esc_attr($last_fetch_info['organization_id'] ?? ''); ?>" 
                                    class="regular-text"
                                    placeholder="Enter Ashby Organization ID"
                                >
                                <p class="description">
                                    Enter your Ashby organization ID. You can find this in your Ashby job board URL.
                                    For example, if your job board URL is "jobs.ashbyhq.com/your-company", your organization ID is "your-company".
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div class="schedule-info-wrapper">
                        <h2>API Fetch Schedule</h2>
                        <p>Jobs are fetched five times daily from the Ashby API.</p>

                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th>Last Fetch</th>
                                    <th>Next Scheduled Fetch</th>
                                    <th>Frequency</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="last-fetch-time">
                                        <?php 
                                        if ($last_fetch_info['last_fetch']) {
                                            $date = new \DateTime($last_fetch_info['last_fetch'], $timezone);
                                            echo $date->format('Y-m-d g:i A');
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($last_fetch_info['scheduled_times'])) {
                                            foreach ($last_fetch_info['scheduled_times'] as $index => $time) {
                                                $date = new \DateTime($time, $timezone);
                                                echo $date->format('g:i A');
                                                if ($index < count($last_fetch_info['scheduled_times']) - 1) {
                                                    echo ', ';
                                                }
                                            }
                                        } else {
                                            echo 'Not scheduled';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        Five times daily<br>
                                        <small>(Every 4.8 hours)</small>
                                    </td>
                                    <td>
                                        <button type="button" id="refresh-jobs" class="button button-secondary">
                                            Fetch Jobs Now
                                        </button>
                                        <span id="refresh-status"></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="timezone-info">
                            <p>
                                <strong>Current Server Time Zone:</strong> <?php echo esc_html($timezone_string); ?><br>
                                <strong>Current Server Time:</strong> <?php echo current_time('Y-m-d g:i A'); ?>
                            </p>
                        </div>

                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                        </p>
                    </div>
                    <div class="database-info-wrapper">
    <h2>Database Information</h2>
    <?php $this->render_database_info(); ?>
</div>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_database_info() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'job_listings';
        $job_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $last_updated = $wpdb->get_var("SELECT MAX(date_updated) FROM $table_name");
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Total Jobs in Database</th>
                    <th>Database Table Name</th>
                    <th>Last Updated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo intval($job_count); ?></td>
                    <td><?php echo esc_html($table_name); ?></td>
                    <td>
                        <?php 
                        if ($last_updated) {
                            $date = new \DateTime($last_updated, wp_timezone());
                            echo $date->format('Y-m-d g:i A');
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name) {
                            echo '<span class="status-success">Active</span>';
                        } else {
                            echo '<span class="status-error">Table Missing</span>';
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our plugin page
        if ('settings_page_job-listing-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'job-listing-admin',
            JLP_PLUGIN_URL . 'css/admin.css',
            [],
            JLP_VERSION
        );

        wp_enqueue_script(
            'job-listing-admin',
            JLP_PLUGIN_URL . 'js/admin.js',
            [],
            JLP_VERSION,
            true
        );

        wp_localize_script('job-listing-admin', 'jobListingAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('job_listing_admin_nonce')
        ]);
    }

    public function save_setup_ajax() {
        // Verify nonce
        check_ajax_referer('job_listing_admin_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        // Sanitize and validate inputs
        $organization_id = sanitize_text_field($_POST['organization_id'] ?? '');
        $schedule_times = array_map('sanitize_text_field', $_POST['schedule_times'] ?? []);

        // Remove empty schedule times
        $schedule_times = array_filter($schedule_times);

        // Save setup
        $result = $this->plugin->save_setup($organization_id, $schedule_times);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success($result);
    }

    public function refresh_jobs_ajax() {
        // Verify nonce
        check_ajax_referer('job_listing_admin_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }

        // Trigger job refresh
        $result = $this->plugin->refresh_jobs_data();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success($result);
    }
}