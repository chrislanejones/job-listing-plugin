<?php
class Job_Listing_Plugin {
    private static $instance = null;
    private $options;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        $this->options = get_option('job_listing_settings');
        
        add_action('elementor/widgets/widgets_registered', [$this, 'register_widgets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_route']);
    }

    public function register_widgets() {
        require_once(JLP_PLUGIN_DIR . 'includes/widgets/class-job-listing-widget.php');
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new Job_Listing_Widget());
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
            ['jquery'],
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
    }

    public function get_jobs_list() {
        try {
            $org_id = isset($this->options['organization_id']) ? trim($this->options['organization_id']) : '';
            
            if (empty($org_id)) {
                return new WP_Error(
                    'missing_org_id',
                    'Organization ID is required',
                    ['status' => 400]
                );
            }

            $api_url = "https://api.ashbyhq.com/posting-api/job-board/{$org_id}";
            
            $args = [
                'method' => 'GET',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ];

            $response = wp_remote_get($api_url, $args);

            if (is_wp_error($response)) {
                return new WP_Error(
                    'api_error',
                    'API request failed: ' . $response->get_error_message(),
                    ['status' => 500]
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return new WP_Error(
                    'api_error',
                    'API request failed with status ' . $response_code,
                    ['status' => $response_code]
                );
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error(
                    'json_error',
                    'Invalid JSON response',
                    ['status' => 500]
                );
            }

            // Format jobs with only the specified fields
            $formatted_jobs = array_map(function($posting) {
                return [
                    'title' => $posting['title'] ?? '',
                    'department' => $posting['department'] ?? '',
                    'team' => $posting['team'] ?? '',
                    'employmentType' => $posting['employmentType'] ?? '',
                    'location' => $posting['location'] ?? '',
                    'isRemote' => $posting['isRemote'] ?? false,
                    'applicationUrl' => $posting['jobUrl'] ?? ''
                ];
            }, $data['jobs'] ?? []);

            return new WP_REST_Response([
                'jobs' => $formatted_jobs
            ], 200);

        } catch (Exception $e) {
            return new WP_Error(
                'api_error',
                'Unexpected error: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}