<?php
namespace JobListingPlugin;

class Job_Listing_Plugin {
    private static $instance = null;
    private $options;
    private $db_table;
    
    const SCHEDULE_HOOK = 'job_listing_api_fetch';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        global $wpdb;
        $this->db_table = $wpdb->prefix . 'job_listings';
    }

    /**
     * Get all scheduled events for a specific hook
     * 
     * @param string $hook The hook name to look for
     * @return array Timestamps of scheduled events
     */
    private function get_scheduled_events($hook) {
        $crons = _get_cron_array();
        $events = [];
        
        if (!empty($crons)) {
            foreach ($crons as $timestamp => $cron) {
                if (isset($cron[$hook])) {
                    $events[] = $timestamp;
                }
            }
        }
        
        return $events;
    }

    public function init() {
        $this->options = get_option('job_listing_settings');
        
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_route']);
        
        // Schedule API fetch hook
        add_action(self::SCHEDULE_HOOK, [$this, 'fetch_and_store_jobs']);
    }

    public function register_widgets() {
        // Ensure Elementor is loaded
        if (!did_action('elementor/loaded')) {
            return;
        }

        // Include the widget file
        require_once(JLP_PLUGIN_DIR . 'includes/widgets/class-job-listing-widget.php');

        // Register the widget
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(
            new \JobListingPlugin\Job_Listing_Widget()
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'job-listing-style',
            JLP_PLUGIN_URL . 'assets/css/job-listing.css',
            [],
            JLP_VERSION
        );

        wp_enqueue_script(
            'job-listing-script',
            JLP_PLUGIN_URL . 'assets/js/job-listing.js',
            [], // No jQuery dependency
            JLP_VERSION,
            true
        );

        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            [],
            '5.15.4'
        );

        wp_localize_script('job-listing-script', 'jobListingData', [
            'ajaxUrl' => rest_url('job-listing/v1/list'),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    public function register_rest_route() {
        register_rest_route('job-listing/v1', '/list', [
            'methods' => 'GET',
            'callback' => [$this, 'get_jobs_list'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('job-listing/v1', '/refresh', [
            'methods' => 'GET',
            'callback' => [$this, 'refresh_jobs_data'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
        
        // New endpoint for initializing schedule
        register_rest_route('job-listing/v1', '/initialize-schedule', [
            'methods' => 'GET',
            'callback' => [$this, 'initialize_schedule'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    public function initialize_schedule() {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                'Sorry, you are not allowed to do that.',
                ['status' => 401]
            );
        }
        
        $this->activate_scheduler($this->options['schedule_times'] ?? []);
        
        // Also trigger an immediate data fetch
        $result = $this->fetch_and_store_jobs();
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Schedule initialized successfully.',
            'next_scheduled' => wp_next_scheduled(self::SCHEDULE_HOOK) 
                ? date('Y-m-d H:i:s', wp_next_scheduled(self::SCHEDULE_HOOK)) 
                : null
        ], 200);
    }
    
    public function refresh_jobs_data() {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                'Sorry, you are not allowed to do that.',
                ['status' => 401]
            );
        }
        
        $result = $this->fetch_and_store_jobs();
        
        if ($result === false) {
            return new \WP_Error(
                'api_error',
                'Failed to fetch or store jobs',
                ['status' => 500]
            );
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => sprintf(
                'Successfully refreshed jobs data. Added: %d, Updated: %d, Removed: %d',
                $result['added'],
                $result['updated'],
                $result['removed']
            ),
            'data' => $result
        ], 200);
    }

    public function get_jobs_list() {
        try {
            $jobs = $this->get_jobs_from_db();
            
            if (empty($jobs)) {
                // Try to fetch from API if no jobs in DB
                $api_result = $this->fetch_and_store_jobs();
                if ($api_result !== false) {
                    $jobs = $this->get_jobs_from_db();
                }
                
                if (empty($jobs)) {
                    return new \WP_Error(
                        'no_jobs',
                        'No jobs found',
                        ['status' => 404]
                    );
                }
            }
            
            return new \WP_REST_Response([
                'jobs' => $jobs
            ], 200);

        } catch (\Exception $e) {
            return new \WP_Error(
                'db_error',
                'Unexpected error: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function activate_scheduler($schedule_times) {
        // Clear any existing schedules
        $this->deactivate_scheduler();
        
        if (empty($schedule_times) || !is_array($schedule_times) || count($schedule_times) < 1) {
            return false;
        }
        
        // Get current time
        $current_time = current_time('timestamp');
        $current_day_start = strtotime('today', $current_time);
        
        foreach ($schedule_times as $time) {
            // Parse hour and minute from time string (format: HH:MM)
            list($hour, $minute) = explode(':', $time);
            
            // Create timestamp for this schedule time today
            $schedule_time = $current_day_start + ($hour * HOUR_IN_SECONDS) + ($minute * MINUTE_IN_SECONDS);
            
            // If this time has already passed today, schedule for tomorrow
            if ($schedule_time <= $current_time) {
                $schedule_time += DAY_IN_SECONDS;
            }
            
            // Schedule the event to occur at this specific time daily
            wp_schedule_event($schedule_time, 'daily', self::SCHEDULE_HOOK);
        }
        
        return true;
    }

    public function deactivate_scheduler() {
        // Get all scheduled times for our hook
        $timestamps = $this->get_scheduled_events(self::SCHEDULE_HOOK);
        
        // Clear each scheduled event
        if (!empty($timestamps)) {
            foreach ($timestamps as $timestamp) {
                wp_unschedule_event($timestamp, self::SCHEDULE_HOOK);
            }
        }
    }

    public function fetch_and_store_jobs() {
        $org_id = isset($this->options['organization_id']) ? trim($this->options['organization_id']) : '';
        
        if (empty($org_id)) {
            error_log('Job Listing Plugin: No organization ID set for API fetch');
            return false;
        }
        
        $api_url = "https://api.ashbyhq.com/posting-api/job-board/{$org_id}";
        
        $args = [
            'method' => 'GET',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('Job Listing Plugin: API fetch error - ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Job Listing Plugin: API returned status ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Job Listing Plugin: Invalid JSON response');
            return false;
        }
        
        if (!isset($data['jobs']) || !is_array($data['jobs'])) {
            error_log('Job Listing Plugin: No jobs found in API response');
            return false;
        }
        
        // Store the last fetch timestamp and source data hash
        $fetch_time = current_time('mysql');
        $data_hash = md5($body);
        update_option('job_listing_last_fetch', [
            'time' => $fetch_time,
            'hash' => $data_hash
        ]);
        
        return $this->store_jobs_in_db($data['jobs'], $data_hash);
    }
    
    private function store_jobs_in_db($jobs, $fetch_hash) {
        global $wpdb;
        
        // Get existing job IDs
        $existing_job_ids = $wpdb->get_col("SELECT job_id FROM {$this->db_table}");
        
        $jobs_updated = 0;
        $jobs_added = 0;
        
        foreach ($jobs as $job) {
            $job_data = [
                'job_id' => $job['id'] ?? '',
                'title' => $job['title'] ?? '',
                'department' => $job['department'] ?? '',
                'team' => $job['team'] ?? '',
                'employment_type' => $job['employmentType'] ?? '',
                'location' => $job['location'] ?? '',
                'is_remote' => $job['isRemote'] ? 1 : 0,
                'application_url' => $job['jobUrl'] ?? '',
                'last_fetch_hash' => $fetch_hash
            ];
            
            // Skip if no job ID
            if (empty($job_data['job_id'])) {
                continue;
            }
            
            // Check if job exists
            if (in_array($job_data['job_id'], $existing_job_ids)) {
                // Update existing job
                $result = $wpdb->update(
                    $this->db_table,
                    $job_data,
                    ['job_id' => $job_data['job_id']]
                );
                
                if ($result !== false) {
                    $jobs_updated++;
                }
            } else {
                // Insert new job
                $result = $wpdb->insert(
                    $this->db_table,
                    $job_data
                );
                
                if ($result) {
                    $jobs_added++;
                }
            }
        }
        
        // Remove jobs no longer in API response
        $current_job_ids = array_map(function($job) {
            return $job['id'] ?? '';
        }, $jobs);
        
        $current_job_ids = array_filter($current_job_ids);
        
        if (!empty($current_job_ids)) {
            $jobs_to_remove = array_diff($existing_job_ids, $current_job_ids);
            
            if (!empty($jobs_to_remove)) {
                $jobs_removed = $this->remove_jobs_from_db($jobs_to_remove);
            }
        }
        
        return [
            'added' => $jobs_added,
            'updated' => $jobs_updated,
            'removed' => $jobs_removed ?? 0
        ];
    }
    
    private function remove_jobs_from_db($job_ids) {
        global $wpdb;
        
        if (empty($job_ids)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($job_ids), '%s'));
        $query = $wpdb->prepare(
            "DELETE FROM {$this->db_table} WHERE job_id IN ($placeholders)",
            $job_ids
        );
        
        $wpdb->query($query);
        return $wpdb->rows_affected;
    }

    public function get_jobs_from_db() {
        global $wpdb;
        
        $jobs = $wpdb->get_results(
            "SELECT 
                job_id as id,
                title,
                department,
                team,
                employment_type as employmentType,
                location,
                is_remote as isRemote,
                application_url as applicationUrl
            FROM {$this->db_table}
            ORDER BY department ASC, title ASC",
            ARRAY_A
        );
        
        // Convert is_remote from 0/1 to boolean
        foreach ($jobs as &$job) {
            $job['isRemote'] = (bool)$job['isRemote'];
        }
        
        return $jobs;
    }

    public function get_last_fetch_info() {
        $last_fetch = get_option('job_listing_last_fetch', null);
        
        // Get all scheduled times for our hook
        $scheduled_times = [];
        $timestamps = $this->get_scheduled_events(self::SCHEDULE_HOOK);
        
        if (!empty($timestamps)) {
            foreach ($timestamps as $timestamp) {
                $scheduled_times[] = date('Y-m-d H:i:s', $timestamp);
            }
            sort($scheduled_times);
        }
        
        return [
            'last_fetch' => $last_fetch ? $last_fetch['time'] : null,
            'scheduled_times' => $scheduled_times,
            'setup_complete' => !empty($this->options['setup_complete']),
            'organization_id' => $this->options['organization_id'] ?? ''
        ];
    }
    
    // New method to save setup and initialize scheduling
    public function save_setup($organization_id, $schedule_times) {
        if (empty($organization_id) || empty($schedule_times) || count($schedule_times) < 1) {
            return new \WP_Error(
                'invalid_setup',
                'Organization ID and at least one schedule time are required',
                ['status' => 400]
            );
        }
        
        // Update options
        $this->options['organization_id'] = sanitize_text_field($organization_id);
        $this->options['schedule_times'] = array_map('sanitize_text_field', $schedule_times);
        $this->options['setup_complete'] = true;
        
        update_option('job_listing_settings', $this->options);
        
        // Activate scheduler with new times
        $scheduling_result = $this->activate_scheduler($schedule_times);
        
        if (!$scheduling_result) {
            return new \WP_Error(
                'scheduling_error',
                'Failed to schedule jobs with provided times',
                ['status' => 500]
            );
        }
        
        // Fetch initial data
        $fetch_result = $this->fetch_and_store_jobs();
        
        return [
            'success' => true,
            'organization_id' => $organization_id,
            'schedule_times' => $schedule_times,
            'fetch_result' => $fetch_result,
            'scheduled_events' => $this->get_scheduled_events(self::SCHEDULE_HOOK)
        ];
    }

    public function create_db_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->db_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id varchar(64) NOT NULL,
            title text NOT NULL,
            department varchar(255),
            team varchar(255),
            employment_type varchar(100),
            location varchar(255),
            is_remote tinyint(1) DEFAULT 0,
            application_url text,
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_fetch_hash varchar(32),
            PRIMARY KEY  (id),
            UNIQUE KEY job_id (job_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
