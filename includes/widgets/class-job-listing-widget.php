<?php
class Job_Listing_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'job_listing';
    }

    public function get_title() {
        return 'Job Listing';
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Content Settings',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'jobs_per_page',
            [
                'label' => 'Jobs per Page',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 100,
                'default' => 10,
            ]
        );

        $this->add_control(
            'show_department',
            [
                'label' => 'Show Department',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_location',
            [
                'label' => 'Show Location',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => 'Style Settings',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'label' => 'Job Title Typography',
                'selector' => '{{WRAPPER}} .job-item h3',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => 'Job Title Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .job-item h3' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => 'Button Color',
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .job-apply-button' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <div class="job-listing-container" 
             data-jobs-per-page="<?php echo esc_attr($settings['jobs_per_page']); ?>"
             data-show-department="<?php echo esc_attr($settings['show_department']); ?>"
             data-show-location="<?php echo esc_attr($settings['show_location']); ?>">
            <div class="jobs-list"></div>
            <div class="loading">Loading jobs...</div>
        </div>
        <?php
    }
}
