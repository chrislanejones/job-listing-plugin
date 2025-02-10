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
            // Get the organization ID from options
            $org_id = isset($this->options['organization_id']) ? trim($this->options['organization_id']) : '';
            
            if (empty($org_id)) {
                return new WP_Error(
                    'missing_org_id',
                    'Ashby organization ID is required',
                    ['status' => 400]
                );
            }

            // Make request to jobs page
            $api_url = "https://jobs.ashbyhq.com/{$org_id}";
            $response = wp_remote_get($api_url);

            if (is_wp_error($response)) {
                return new WP_Error(
                    'api_error',
                    'API request failed: ' . $response->get_error_message(),
                    ['status' => 500]
                );
            }

            $html = wp_remote_retrieve_body($response);

            // Extract the window.__appData JSON
            if (preg_match('/window\.__appData\s*=\s*({.*?});/s', $html, $matches)) {
                $appData = json_decode($matches[1], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new WP_Error(
                        'json_error',
                        'Failed to parse JSON data: ' . json_last_error_msg(),
                        ['status' => 500]
                    );
                }

                // Extract job postings from the jobBoard data
                if (isset($appData['jobBoard']['jobPostings']) && is_array($appData['jobBoard']['jobPostings'])) {
                    $formatted_jobs = array_map(function($posting) use ($org_id) {
                        return [
                            'title' => $posting['title'],
                            'department' => $posting['departmentName'],
                            'location' => $posting['locationName'],
                            'compensation' => $posting['compensationTierSummary'] ?? '',
                            'employmentType' => $posting['employmentType'] ?? '',
                            'applicationUrl' => "https://jobs.ashbyhq.com/{$org_id}/application?jobId=" . $posting['jobId'],
                            'publishedDate' => $posting['publishedDate'] ?? '',
                            'workplaceType' => $posting['workplaceType'] ?? ''
                        ];
                    }, $appData['jobBoard']['jobPostings']);

                    return new WP_REST_Response([
                        'jobs' => $formatted_jobs,
                        'organization' => [
                            'name' => $appData['organization']['name'] ?? '',
                            'description' => strip_tags($appData['organization']['theme']['jobBoardTopDescriptionHtml'] ?? ''),
                            'values' => strip_tags($appData['organization']['theme']['jobBoardBottomDescriptionHtml'] ?? '')
                        ]
                    ], 200);
                }
            }

            // If we couldn't find the data, return an error
            return new WP_Error(
                'parsing_error',
                'Could not find job data in the response',
                ['status' => 500]
            );

        } catch (Exception $e) {
            return new WP_Error(
                'api_error',
                'Unexpected error: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}
