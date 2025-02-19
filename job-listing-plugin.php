<?php
/*
Plugin Name: Job Listing Plugin
Plugin URI:
Description: A comprehensive job listing plugin with Elementor integration
Version: 1.3.0
Author: Chris Lane Jones
*/

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('JLP_VERSION', '1.2.0');
define('JLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JLP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load core plugin class
require_once JLP_PLUGIN_DIR . 'includes/class-job-listing-plugin.php';

// Load admin functionality
if (is_admin()) {
  require_once JLP_PLUGIN_DIR . 'admin/class-job-listing-admin.php';
}

// Initialize the plugin
function jlp_init() {
  // Check if Elementor is installed and activated
  if (!did_action('elementor/loaded')) {
    add_action('admin_notices', function () {
      ?>
      <div class="notice notice-warning">
        <p>
          <?php
          echo sprintf(
            'Job Listing Plugin requires Elementor to be installed and activated. Please %s install Elementor %s',
            '<a href="' .
              esc_url(
                admin_url('plugin-install.php?s=Elementor&tab=search&type=term')
              ) .
              '">',
            '</a>'
          );
          ?>
        </p>
      </div>
      <?php
    });
    return;
  }

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
  // Check PHP version
  if (version_compare(PHP_VERSION, '7.4', '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    add_action('admin_notices', function () {
      ?>
      <div class="notice notice-error">
        <p>Job Listing Plugin requires PHP 7.4 or higher.</p>
      </div>
      <?php
    });
    return;
  }

  // Add default options
  $default_options = [
    'organization_id' => '',
    'refresh_frequency' => 'thrice_daily',
  ];
  add_option('job_listing_settings', $default_options);

  // Create database table
  $plugin = Job_Listing_Plugin::get_instance();
  $plugin->create_db_table();

  // Schedule the cron job
  $plugin->activate_scheduler();

  // Initial data fetch
  $plugin->fetch_and_store_jobs();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'jlp_deactivate');
function jlp_deactivate() {
  // Clear scheduled events
  $plugin = Job_Listing_Plugin::get_instance();
  $plugin->deactivate_scheduler();
}

register_uninstall_hook(__FILE__, 'jlp_uninstall_plugin');
