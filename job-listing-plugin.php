<?php
/*
Plugin Name: Job Listing Plugin
Plugin URI: 
Description: A comprehensive job listing plugin with Elementor integration
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JLP_VERSION', '1.0.0');
define('JLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JLP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require Composer autoloader
require_once(JLP_PLUGIN_DIR . 'vendor/autoload.php');

// Load core plugin class
require_once(JLP_PLUGIN_DIR . 'includes/class-job-listing-plugin.php');

// Load admin functionality
if (is_admin()) {
    require_once(JLP_PLUGIN_DIR . 'admin/class-job-listing-admin.php');
}

// Initialize the plugin
function jlp_init() {
    $plugin = Job_Listing_Plugin::get_instance();
    $plugin->init();

    if (is_admin()) {
        $admin = new Job_Listing_Admin();
        $admin->init();
    }
}
add_action('plugins_loaded', 'jlp_init');

// Activation hook
register_activation_hook(__FILE__, 'jlp_activate');
function jlp_activate() {
    // Add default options
    $default_options = [
        'client_url' => 'https://api.ashbyhq.com/jobBoard.list',
        'api_key' => ''
    ];
    add_option('job_listing_settings', $default_options);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'jlp_deactivate');
function jlp_deactivate() {
    // Cleanup if needed
}