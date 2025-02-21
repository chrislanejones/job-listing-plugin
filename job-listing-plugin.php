<?php
/**
 * Plugin Name: Job Listing Plugin
 * Plugin URI: 
 * Description: A comprehensive job listing plugin with Elementor integration
 * Version: 1.7.0
 * Author: Chris Lane Jones
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

namespace JobListingPlugin;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('JLP_VERSION', '1.7.0');
define('JLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JLP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JLP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('JLP_MINIMUM_PHP_VERSION', '7.4');

// Error handling function
function jlp_handle_error($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($message);
    }
    return false;
}

// Include required files with error handling
$required_files = [
    'includes/class-job-listing-plugin.php',
    'includes/class-job-listing-admin.php'
];

foreach ($required_files as $file) {
    if (!file_exists(JLP_PLUGIN_DIR . $file)) {
        jlp_handle_error("Required file not found: $file");
        return;
    }
    require_once JLP_PLUGIN_DIR . $file;
}

// Autoloader
spl_autoload_register(function ($class) {
    // Only autoload classes in our namespace
    $prefix = 'JobListingPlugin\\';
    $base_dir = JLP_PLUGIN_DIR . 'includes/';
    
    // Check if the class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Remove namespace prefix and convert namespace to file path
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . str_replace(['_', '\\'], ['-', '/'], strtolower($relative_class)) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
    }
});

// Main plugin initialization class
class Job_Listing_Plugin_Core {
    private static $instance = null;
    private $plugin = null;
    private $admin = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_plugin']);
        register_activation_hook(JLP_PLUGIN_BASENAME, [$this, 'activate']);
        register_deactivation_hook(JLP_PLUGIN_BASENAME, [$this, 'deactivate']);
    }

    public function load_plugin() {
        try {
            // Initialize plugin
            $this->plugin = Job_Listing_Plugin::get_instance();
            $this->plugin->init();

            // Initialize admin if in admin area
            if (is_admin()) {
                $this->admin = Job_Listing_Admin::get_instance();
                $this->admin->init();
            }
        } catch (\Exception $e) {
            jlp_handle_error('Job Listing Plugin Initialization Error: ' . $e->getMessage());
        }
    }

    public function activate($network_wide = false) {
        try {
            // Check PHP version
            if (version_compare(PHP_VERSION, JLP_MINIMUM_PHP_VERSION, '<')) {
                throw new \Exception(sprintf(
                    'Job Listing Plugin requires PHP version %s or higher.',
                    JLP_MINIMUM_PHP_VERSION
                ));
            }

            // Initialize plugin instance if not already done
            if (null === $this->plugin) {
                $this->plugin = Job_Listing_Plugin::get_instance();
            }

            // Create database table
            if (method_exists($this->plugin, 'create_db_table')) {
                $this->plugin->create_db_table();
            } else {
                throw new \Exception('Required method create_db_table not found');
            }

            // Setup default settings
            $settings = get_option('job_listing_settings', []);
            $schedule_times = isset($settings['schedule_times']) 
                ? $settings['schedule_times'] 
                : ['08:00', '16:00'];

            // Setup scheduler
            if (method_exists($this->plugin, 'activate_scheduler')) {
                $this->plugin->activate_scheduler($schedule_times);
            } else {
                throw new \Exception('Required method activate_scheduler not found');
            }

            // Initial data fetch
            if (method_exists($this->plugin, 'fetch_and_store_jobs')) {
                $this->plugin->fetch_and_store_jobs();
            }

            // Flush rewrite rules
            flush_rewrite_rules();

        } catch (\Exception $e) {
            jlp_handle_error('Activation Error: ' . $e->getMessage());
            wp_die(
                esc_html($e->getMessage()),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
    }

    public function deactivate($network_wide = false) {
        try {
            // Initialize plugin instance if not already done
            if (null === $this->plugin) {
                $this->plugin = Job_Listing_Plugin::get_instance();
            }

            // Deactivate scheduler
            if (method_exists($this->plugin, 'deactivate_scheduler')) {
                $this->plugin->deactivate_scheduler();
            }

            // Flush rewrite rules
            flush_rewrite_rules();

        } catch (\Exception $e) {
            jlp_handle_error('Deactivation Error: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
function jlp_init() {
    return Job_Listing_Plugin_Core::instance();
}

// Start the plugin
add_action('plugins_loaded', 'JobListingPlugin\\jlp_init', 10);
