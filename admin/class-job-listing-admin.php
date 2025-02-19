<?php
class Job_Listing_Admin {
    private $options;
    private $plugin;

    public function init() {
        $this->options = get_option('job_listing_settings');
        $this->plugin = Job_Listing_Plugin::get_instance();
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
        
        wp_localize_script('job-listing-admin', 'jobListingAdmin', [
            'refreshEndpoint' => rest_url('job-listing/v1/refresh'),
            'nonce' => wp_create_nonce('wp_rest')
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

    public function render_api_section_info() {
        echo '<p>Enter your Ashby organization ID to connect to your job board.</p>';
        echo '<p>You can find your organization ID in your Ashby job board URL. For example, if your job board URL is "jobs.ashbyhq.com/your-company", your organization ID is "your-company".</p>';
    }
    
    public function render_schedule_section_info() {
        $fetch_info = $this->plugin->get_last_fetch_info();
        
        $cron_schedules = wp_get_schedules();
        $schedule_display = isset($cron_schedules[Job_Listing_Plugin::SCHEDULE_RECURRENCE]) 
            ? $cron_schedules[Job_Listing_Plugin::SCHEDULE_RECURRENCE]['display'] 
            : 'Three times daily';
        
        ?>
        <div class="schedule-info-wrapper">
            <p>The plugin automatically fetches job data from the Ashby API <?php echo $schedule_display; ?>.</p>
            
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
                            <?php echo $fetch_info['last_fetch'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fetch_info['last_fetch'])) : 'Never'; ?>
                        </td>
                        <td id="next-fetch-time">
                            <?php echo $fetch_info['next_scheduled'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($fetch_info['next_scheduled'])) : 'Not scheduled'; ?>
                        </td>
                        <td>
                            <?php echo $schedule_display; ?><br>
                            <small>(Every 8 hours)</small>
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
            
            <p><strong>Daily fetch times (server time):</strong></p>
            <ul>
                <?php
                $base_time = wp_next_scheduled(Job_Listing_Plugin::SCHEDULE_HOOK);
                if ($base_time) {
                    $interval = 8 * HOUR_IN_SECONDS;
                    $times = [];
                    $base_hour = date('H', $base_time);
                    $base_minute = date('i', $base_time);
                    
                    // Find the first fetch time of the day
                    $first_fetch = $base_time;
                    while (date('H', $first_fetch) > 0) {
                        $first_fetch -= $interval;
                    }
                    
                    for ($i = 0; $i < 3; $i++) {
                        $time = $first_fetch + ($i * $interval);
                        echo '<li>' . date_i18n('g:i a', $time) . '</li>';
                    }
                } else {
                    echo '<li>Schedule not set. Activate the plugin to set the schedule.</li>';
                }
                ?>
            </ul>
            <p><em>Note: These times are approximate and may vary slightly based on server load.</em></p>
        </div>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Job Listing Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('job_listing_settings');
                do_settings_sections('job-listing-settings');
                submit_button();
                ?>
            </form>
            
            <div class="job-listing-database-info">
                <h2>Database Information</h2>
                <?php $this->render_database_info(); ?>
            </div>
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

    public function render_organization_id_field() {
        $value = isset($this->options['organization_id']) ? $this->options['organization_id'] : '';
        ?>
        <input type="text" 
               name="job_listing_settings[organization_id]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="your-company"
        />
        <p class="description">Enter your Ashby organization ID (found in your job board URL)</p>
        <?php
    }
}