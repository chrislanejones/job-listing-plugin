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
            $client_url = isset($this->options['client_url']) 
                ? $this->options['client_url'] 
                : 'https://api.ashbyhq.com/jobBoard.list';
            
            $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';

            $headers = ['accept' => 'application/json'];
            if (!empty($api_key)) {
                $headers['Authorization'] = 'Bearer ' . $api_key;
            }

            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $client_url, [
                'headers' => $headers,
            ]);

            $body = json_decode($response->getBody(), true);
            return new WP_REST_Response($body, 200);

        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage(), ['status' => 500]);
        }
    }
}