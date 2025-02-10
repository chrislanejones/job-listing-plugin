<?php
/*
Plugin Name: Job Listing Plugin
Plugin URI: 
Description: A comprehensive job listing plugin with Elementor integration
Version: 1.0
Author: CLJ
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JLP_VERSION', '1.0.0');
define('JLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JLP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check for Composer autoloader
if (!file_exists(JLP_PLUGIN_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p>Job Listing Plugin requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory or download the full release version.</p>
        </div>
        <?php
    });
    return;
}

// Require Composer autoloader
require_once(JLP_PLUGIN_DIR . 'vendor/autoload.php');

// Check if Elementor is installed and activated
if (!did_action('elementor/loaded')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-warning">
            <p><?php echo sprintf('Job Listing Plugin requires Elementor to be installed and activated. Please %s install Elementor %s', '<a href="' . esc_url(admin_url('plugin-install.php?s=Elementor&tab=search&type=term')) . '">', '</a>'); ?></p>
        </div>
        <?php
    });
    return;
}

// Load core plugin class
if (file_exists(JLP_PLUGIN_DIR . 'includes/class-job-listing-plugin.php')) {
    require_once(JLP_PLUGIN_DIR . 'includes/class-job-listing-plugin.php');
} else {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p>Job Listing Plugin core file is missing. Please reinstall the plugin.</p>
        </div>
        <?php
    });
    return;
}

// Load admin functionality
if (is_admin() && file_exists(JLP_PLUGIN_DIR . 'admin/class-job-listing-admin.php')) {
    require_once(JLP_PLUGIN_DIR . 'admin/class-job-listing-admin.php');
}

// Initialize the plugin
function jlp_init() {
    if (class_exists('Job_Listing_Plugin')) {
        $plugin = Job_Listing_Plugin::get_instance();
        $plugin->init();

        if (is_admin() && class_exists('Job_Listing_Admin')) {
            $admin = new Job_Listing_Admin();
            $admin->init();
        }
    }
}
add_action('plugins_loaded', 'jlp_init');

// Activation hook
register_activation_hook(__FILE__, 'jlp_activate');
function jlp_activate() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Job Listing Plugin requires PHP 7.4 or higher.');
    }

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