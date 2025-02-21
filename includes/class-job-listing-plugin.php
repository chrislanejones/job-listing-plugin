<?php
namespace JobListingPlugin;

class Job_Listing_Plugin {
    private static $instance = null;
    private $options;
    private $db_table;
    
    const SCHEDULE_HOOK = 'job_listing_api_fetch';
    const SCHEDULE_RECURRENCE = 'five_times_daily';

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

    public function init() {
        $this->options = get_option('job_listing_settings');
        
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_route']);
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action(self::SCHEDULE_HOOK, [$this, 'fetch_and_store_jobs']);
    }

    public function add_cron_schedules($schedules) {
        $schedules['five_times_daily'] = [
            'interval' => 17280, // 24 hours / 5 = 4.8 hours or 17280 seconds
            'display'  => __('Five times daily')
        ];
        return $schedules;
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

    // These methods are referenced but not implemented in the original code
    private function get_jobs_from_db() {
        // Implementation needed
    }

    private function fetch_and_store_jobs() {
        // Implementation needed
    }

    private function activate_scheduler($schedule_times) {
        // Implementation needed
    }
}
