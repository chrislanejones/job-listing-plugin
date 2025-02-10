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
            'Ashby API Settings',
            [$this, 'render_section_info'],
            'job-listing-settings'
        );

        add_settings_field(
            'organization_id',
            'Organization ID',
            [$this, 'render_organization_id_field'],
            'job-listing-settings',
            'job_listing_main_section'
        );
    }

    public function render_section_info() {
        echo '<p>Enter your Ashby organization ID to connect to your job board.</p>';
        echo '<p>You can find your organization ID in your Ashby job board URL. For example, if your job board URL is "jobs.ashbyhq.com/your-company", your organization ID is "your-company".</p>';
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