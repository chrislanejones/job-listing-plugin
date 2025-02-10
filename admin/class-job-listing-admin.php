<?php
class Job_Listing_Admin {
    private $options;

    public function init() {
        $this->options = get_option('job_listing_settings');
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
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
            'job_listing_main_section',
            'API Settings',
            null,
            'job-listing-settings'
        );

        add_settings_field(
            'client_url',
            'API URL',
            [$this, 'render_client_url_field'],
            'job-listing-settings',
            'job_listing_main_section'
        );

        add_settings_field(
            'api_key',
            'API Key',
            [$this, 'render_api_key_field'],
            'job-listing-settings',
            'job_listing_main_section'
        );
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
        </div>
        <?php
    }

    public function render_client_url_field() {
        $value = isset($this->options['client_url']) ? $this->options['client_url'] : '';
        ?>
        <input type="text" 
               name="job_listing_settings[client_url]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="https://api.ashbyhq.com/jobBoard.list"
        />
        <p class="description">Enter your API endpoint URL</p>
        <?php
    }

    public function render_api_key_field() {
        $value = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        ?>
        <input type="password" 
               name="job_listing_settings[api_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
        />
        <p class="description">Enter your API key if required</p>
        <?php
    }
}