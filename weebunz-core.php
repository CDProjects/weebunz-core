<?php
/**
 * Plugin Name: Weebunz
 * Plugin URI: https://weebunz.fi
 * Description: Custom quiz and raffle system for Weebunz
 * Version: 1.0.0
 * Author: CD Projects
 * Text Domain: weebunz-core
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

// Check for REST API issues
add_action('admin_notices', function() {
    if (is_admin() && current_user_can('manage_options')) {
        // Check if REST API is disabled
        $rest_available = rest_get_url_prefix() !== false;
        if (!$rest_available) {
            echo '<div class="error"><p>REST API is disabled! WeeBunz plugin requires REST API to function.</p></div>';
        }

        // Check for permalink issues
        $permalink_structure = get_option('permalink_structure');
        if (empty($permalink_structure)) {
            echo '<div class="error"><p>You are using default permalinks which may cause REST API issues. Please switch to post name permalinks for WeeBunz plugin to work properly.</p></div>';
        }
    }
});

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Prevent multiple loading
if (defined('WEEBUNZ_VERSION')) {
    return;
}

// Define plugin constants
define('WEEBUNZ_VERSION', '1.0.0');
define('WEEBUNZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEEBUNZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WEEBUNZ_PLUGIN_NAME', 'weebunz-core');

// Store plugin instance globally
global $weebunz_plugin;

// Required dependencies
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-logger.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/database/class-db-manager.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/quiz/class-quiz-manager.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/quiz/class-quiz-validator.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/quiz/class-quiz-entry.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/controllers/class-quiz-controller.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-public.php';
require_once WEEBUNZ_PLUGIN_DIR . 'admin/class-admin.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz.php';

/**
 * Activation handler
 */
function activate_weebunz() {
    try {
        \Weebunz\Logger::init();
        \Weebunz\Logger::info('Starting plugin activation...');

        // Initialize DB Manager
        $db_manager = new \Weebunz\Database\DB_Manager();

        // Run full initialization
        $result = $db_manager->initialize();

        if (!$result) {
            throw new \Exception('Database initialization failed');
        }

        // Create required directories
        $upload_dir = wp_upload_dir();
        $weebunz_dir = $upload_dir['basedir'] . '/weebunz';
        wp_mkdir_p($weebunz_dir);
        wp_mkdir_p($weebunz_dir . '/temp');
        wp_mkdir_p($weebunz_dir . '/exports');

        // Ensure updates directory exists
        $updates_dir = WEEBUNZ_PLUGIN_DIR . 'includes/database/updates';
        wp_mkdir_p($updates_dir);

        flush_rewrite_rules();
        \Weebunz\Logger::info('Plugin activation completed successfully');

    } catch (\Exception $e) {
        \Weebunz\Logger::exception($e, ['context' => 'plugin_activation']);
        wp_die('Error activating WeeBunz plugin: ' . esc_html($e->getMessage()));
    }
}

/**
 * Deactivation handler
 */
function deactivate_weebunz() {
    try {
        \Weebunz\Logger::info('Starting plugin deactivation...');

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/weebunz/temp';
        if (is_dir($temp_dir)) {
            array_map('unlink', glob("$temp_dir/*.*"));
        }

        flush_rewrite_rules();
        \Weebunz\Logger::info('Plugin deactivation completed successfully');

    } catch (\Exception $e) {
        \Weebunz\Logger::exception($e, ['context' => 'plugin_deactivation']);
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'activate_weebunz');
register_deactivation_hook(__FILE__, 'deactivate_weebunz');

/**
 * Initialize text domain loading after init action
 */
function load_weebunz_textdomain() {
    load_plugin_textdomain(
        'weebunz-core',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}
add_action('init', 'load_weebunz_textdomain', 10);

/**
 * Register and initialize REST API routes
 */
function weebunz_register_quiz_routes() {
    $quiz_controller = new \Weebunz\Controllers\Quiz_Controller();

    error_log('Initializing Weebunz API Routes'); // Debugging Log

    add_action('rest_api_init', function () use ($quiz_controller) {
        $quiz_controller->register_routes();
        error_log('Weebunz Quiz Routes Registered'); // Debugging Log
    });
}
add_action('init', 'weebunz_register_quiz_routes');

/**
 * Initialize plugin
 */
function init_weebunz() {
    global $weebunz_plugin;

    if (!isset($weebunz_plugin)) {
        try {
            // Load required files first
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/quiz/class-quiz-manager.php';
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/quiz/class-quiz-validator.php';
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/quiz/class-quiz-entry.php';
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/controllers/class-quiz-controller.php';

            // Initialize main plugin using singleton
            $weebunz_plugin = \Weebunz\WeeBunz::get_instance();
            $weebunz_plugin->run();

            \Weebunz\Logger::info('WeeBunz plugin initialized successfully');

        } catch (\Exception $e) {
            \Weebunz\Logger::error('Failed to initialize plugin: ' . $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw $e;
            }
        }
    }
}

// Register initialization hook
add_action('plugins_loaded', 'init_weebunz', 20);

/**
 * Autoloader
 */
spl_autoload_register(function ($class) {
    $prefix = 'Weebunz\\';
    $base_dirs = [
        WEEBUNZ_PLUGIN_DIR . 'includes/',
        WEEBUNZ_PLUGIN_DIR . 'admin/'
    ];

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = str_replace('\\', '/', $relative_class) . '.php';

    foreach ($base_dirs as $base_dir) {
        $path = $base_dir . $file;
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
});

// Simple test route to confirm REST API is working
add_action('rest_api_init', function() {
    register_rest_route('weebunz/v1', '/test', [
        'methods' => 'GET',
        'callback' => function() {
            return rest_ensure_response(['success' => true, 'message' => 'API is working']);
        },
        'permission_callback' => '__return_true'
    ]);
});
