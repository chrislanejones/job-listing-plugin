<?php
/*
Plugin Name: Job Listing Plugin
Plugin URI:
Description: A comprehensive job listing plugin with Elementor integration
Version: 1.3.1
Author: Chris Lane Jones
Author URI:
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: job-listing-plugin
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('JLP_VERSION', '1.3.1'); // Match with plugin version above
define('JLP_MINIMUM_PHP_VERSION', '7.4');
define('JLP_MINIMUM_WP_VERSION', '5.6');
define('JLP_PLUGIN_FILE', __FILE__);
define('JLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JLP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JLP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Plugin namespace prefix
    $prefix = 'JobListingPlugin\\';
    $base_dir = JLP_PLUGIN_DIR . 'includes/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
final class Job_Listing_Plugin_Init {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->check_requirements();
        $this->init_hooks();
    }

    private function check_requirements() {
        // Check PHP Version
        if (version_compare(PHP_VERSION, JLP_MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'php_version_notice']);
            return;
        }

        // Check WordPress Version
        if (version_compare(get_bloginfo('version'), JLP_MINIMUM_WP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'wp_version_notice']);
            return;
        }

        // Load plugin files
        $this->load_files();
    }

    private function load_files() {
        // Load core plugin class
        require_once JLP_PLUGIN_DIR . 'includes/class-job-listing-plugin.php';

        // Load admin functionality
        if (is_admin()) {
            require_once JLP_PLUGIN_DIR . 'admin/class-job-listing-admin.php';
        }
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init_plugin']);
        register_activation_hook(JLP_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(JLP_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(JLP_PLUGIN_FILE, ['Job_Listing_Plugin_Init', 'uninstall']);
    }

    public function init_plugin() {
        // Initialize text domain for internationalization
        load_plugin_textdomain(
            'job-listing-plugin',
            false,
            dirname(JLP_PLUGIN_BASENAME) . '/languages/'
        );

        // Check if Elementor is installed and activated
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'elementor_missing_notice']);
            return;
        }

        // Initialize plugin
        $plugin = Job_Listing_Plugin::get_instance();
        $plugin->init();

        if (is_admin()) {
            $admin = new Job_Listing_Admin();
            $admin->init();
        }
    }

    public function activate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, JLP_MINIMUM_PHP_VERSION, '<')) {
            deactivate_plugins(JLP_PLUGIN_BASENAME);
            wp_die(
                sprintf(
                    __('Job Listing Plugin requires PHP version %s or higher.', 'job-listing-plugin'),
                    JLP_MINIMUM_PHP_VERSION
                )
            );
        }

        // Add default options
        $default_options = [
            'organization_id' => '',
            'refresh_frequency' => 'thrice_daily',
            'setup_complete' => false
        ];
        add_option('job_listing_settings', $default_options);

        // Initialize plugin instance
        $plugin = Job_Listing_Plugin::get_instance();
        
        // Create database table
        $plugin->create_db_table();

        // Schedule the cron job
        $plugin->activate_scheduler();

        // Initial data fetch
        $plugin->fetch_and_store_jobs();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Clear scheduled events
        $plugin = Job_Listing_Plugin::get_instance();
        $plugin->deactivate_scheduler();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public static function uninstall() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Cleanup will be handled by uninstall.php
    }

    public function elementor_missing_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $screen = get_current_screen();
        if (isset($screen->parent_file) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id) {
            return;
        }

        $plugin = 'elementor/elementor.php';
        $installed_plugins = get_plugins();

        if (isset($installed_plugins[$plugin])) {
            $activation_url = wp_nonce_url('plugins.php?action=activate&plugin=' . $plugin, 'activate-plugin_' . $plugin);
            $message = sprintf(
                __('Job Listing Plugin requires Elementor to be activated. %1$sActivate Elementor%2$s', 'job-listing-plugin'),
                '<a href="' . $activation_url . '">',
                '</a>'
            );
        } else {
            $activation_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=elementor'), 'install-plugin_elementor');
            $message = sprintf(
                __('Job Listing Plugin requires Elementor to be installed and activated. %1$sInstall Elementor%2$s', 'job-listing-plugin'),
                '<a href="' . $activation_url . '">',
                '</a>'
            );
        }

        echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
    }

    public function php_version_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . 
             sprintf(
                 __('Job Listing Plugin requires PHP version %s or higher.', 'job-listing-plugin'),
                 JLP_MINIMUM_PHP_VERSION
             ) . 
             '</p></div>';
    }

    public function wp_version_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . 
             sprintf(
                 __('Job Listing Plugin requires WordPress version %s or higher.', 'job-listing-plugin'),
                 JLP_MINIMUM_WP_VERSION
             ) . 
             '</p></div>';
    }
}

// Initialize the plugin
function jlp_init() {
    return Job_Listing_Plugin_Init::instance();
}

// Start the plugin
jlp_init();
