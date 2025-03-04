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

/**
 * Create spending_log table if it doesn't exist
 */
function weebunz_create_spending_log_table() {
    global $wpdb;
    
    // Check if spending_log table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}spending_log'");
    
    if (!$table_exists) {
        error_log('Creating spending_log table');
        
        // Create spending_log table
        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}spending_log` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) UNSIGNED NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `type` enum('quiz', 'membership', 'mega_quiz') NOT NULL,
            `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `created_at` (`created_at`),
            KEY `weekly_spending` (`user_id`, `created_at`)
        ) {$wpdb->get_charset_collate()}";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        if ($wpdb->last_error) {
            error_log('Failed to create spending_log table: ' . $wpdb->last_error);
        } else {
            error_log('spending_log table created successfully');
        }
    }
}
add_action('admin_init', 'weebunz_create_spending_log_table');

/**
 * Debug helper function for question data
 */
function weebunz_debug_question_data() {
    global $wpdb;
    
    // Only show in admin and when debug is enabled
    if (!is_admin() || !defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    echo '<div class="notice notice-info is-dismissible">';
    echo '<h3>WeeBunz Question Data Debug</h3>';
    
    // Count questions by difficulty
    $question_counts = $wpdb->get_results("
        SELECT difficulty_level, COUNT(*) as count 
        FROM {$wpdb->prefix}questions_pool 
        GROUP BY difficulty_level
    ");
    
    echo '<h4>Questions by Difficulty</h4>';
    echo '<ul>';
    foreach ($question_counts as $count) {
        echo "<li>{$count->difficulty_level}: {$count->count}</li>";
    }
    echo '</ul>';
    
    // Count answers
    $answer_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}question_answers");
    echo "<p>Total answers: {$answer_count}</p>";
    
    // Check quiz types
    $quiz_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}quiz_types");
    echo '<h4>Quiz Types</h4>';
    echo '<ul>';
    foreach ($quiz_types as $type) {
        echo "<li>{$type->name}: requires {$type->question_count} questions of {$type->difficulty_level} difficulty</li>";
    }
    echo '</ul>';
    
    echo '</div>';
}
// Uncomment this line to show the debug information on admin pages
// add_action('admin_notices', 'weebunz_debug_question_data');

// Register initialization hook
add_action('plugins_loaded', 'init_weebunz', 20);

// Add this near the end of the file before the autoloader, after the simple test route
add_action('rest_api_init', function() {
    register_rest_route('weebunz/v1', '/quiz/session/clear', [
        'methods' => 'POST',
        'callback' => function($request) {
            $session_id = $request->get_param('session_id');
            if (!$session_id) {
                return new WP_REST_Response(['success' => false, 'error' => 'No session ID provided'], 400);
            }
            
            // Delete the transient
            delete_transient('weebunz_quiz_session_' . $session_id);
            
            // Update the session status in database if it exists
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'quiz_sessions',
                [
                    'status' => 'expired',
                    'ended_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );
            
            return new WP_REST_Response(['success' => true, 'message' => 'Session cleared']);
        },
        'permission_callback' => '__return_true'
    ]);
});

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