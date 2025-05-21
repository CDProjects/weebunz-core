<?php
// File: src/Setup/Activator.php

namespace Weebunz\Setup; // This class is in Weebunz\Setup namespace

if (!defined('ABSPATH')) {
    exit;
}

// NO require_once statements for DBManager or TestUserCreator
// They will be autoloaded by Composer if they are in src/ and correctly namespaced.

class Activator {
    // Match this to WEEBUNZ_VERSION in main plugin file for consistency in version checks
    private static $current_version = '1.1.0';
    private static $db_version_option = 'weebunz_db_version';
    // This is the option key that the main plugin file's admin_init hook should check
    private static $plugin_version_option = 'weebunz_version';
    private static $is_dev_mode;

    /**
     * Activate the plugin
     */
    public static function activate() {
        error_log('Activator: activate() method CALLED.');
        self::$is_dev_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        try {
            error_log('Activator: Calling initialize_plugin().');
            self::initialize_plugin();

            error_log('Activator: Calling update_database().');
            self::update_database(); // This calls DBManager->create_tables() or initialize()

            error_log('Activator: Calling initialize_test_data().');
            self::initialize_test_data();

            error_log('Activator: Calling update_version_options().');
            self::update_version_options();

            error_log('Activator: activate() method COMPLETED successfully.');
        } catch (\Exception $e) {
            // Log the full exception details
            error_log('Activator: EXCEPTION in activate(): ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
            
            // Avoid wp_die in activation hooks for a better user experience.
            // If you must, make it conditional and only for development.
            if (self::$is_dev_mode && (isset($_GET['action']) && $_GET['action'] === 'activate')) {
                // Commenting out wp_die to rely on logs. Uncomment if you absolutely need a hard stop on manual activation.
                // wp_die('Error activating WeeBunz plugin (see debug.log for details): ' . esc_html($e->getMessage()));
            }
        }
    }

    /**
     * Initialize plugin requirements
     */
    private static function initialize_plugin() {
        error_log('Activator: initialize_plugin() started.');
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $weebunz_dir = $upload_dir['basedir'] . '/weebunz';
        
        if (!file_exists($weebunz_dir)) {
            wp_mkdir_p($weebunz_dir); // Ensure this directory is writable by the web server
            wp_mkdir_p($weebunz_dir . '/temp');
            wp_mkdir_p($weebunz_dir . '/exports');
            error_log('Activator: Created weebunz upload directories.');
        } else {
            error_log('Activator: Weebunz upload directories already exist.');
        }

        // Initialize default options
        $default_options = [
            'weebunz_spend_limit' => 35,
            'weebunz_quiz_settings' => [
                'easy_timer' => 10,
                'medium_timer' => 15,
                'hard_timer' => 20
            ]
        ];

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
                error_log('Activator: Added default option: ' . $option);
            }
        }
        error_log('Activator: initialize_plugin() completed.');
    }

    /**
     * Update database schema and content
     */
    private static function update_database() {
        error_log('Activator: update_database() started.');
        try {
            // Use fully qualified namespace. Composer autoloader will find src/Database/DBManager.php
            // Make sure DBManager.php contains "namespace Weebunz\Database;" and "class DBManager"
            $db_manager = new \Weebunz\Database\DBManager();
            
            // Determine if DBManager has an initialize() method that calls create_tables()
            // or if should call create_tables() directly.
            // Based on DBManager, it seems initialize() is the main entry point.
            if (method_exists($db_manager, 'initialize')) {
                error_log('Activator: Calling $db_manager->initialize()');
                $db_manager->initialize();
            } elseif (method_exists($db_manager, 'create_tables')) {
                error_log('Activator: Calling $db_manager->create_tables()');
                $db_manager->create_tables();
            } else {
                error_log('Activator: CRITICAL - DBManager does not have initialize() or create_tables() method.');
                throw new \Exception('DBManager is missing a database setup method.');
            }
            error_log('Activator: update_database() completed successfully.');
        } catch (\Exception $e) {
            // Log the full exception details
            error_log('Activator: EXCEPTION in update_database(): ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
            // Re-throw to be caught by the main try-catch in activate()
            throw new \Exception('Database setup failed in update_database(): ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Initialize test data
     */
    private static function initialize_test_data() {
        error_log('Activator: initialize_test_data() started.');
        if (!self::$is_dev_mode) {
            error_log('Activator: Not in dev mode, skipping test data initialization.');
            return;
        }

        try {
            global $wpdb;
            
            // SQL files are data, not classes, so their paths are direct.
            // Consider moving these SQL files into src/Database/data/ for better organization if desired.
            $cleanup_file = WEEBUNZ_PLUGIN_DIR . 'src/Database/TestData/cleanup.sql';
            if (file_exists($cleanup_file)) {
                error_log('Activator: Running test data cleanup SQL: ' . $cleanup_file);
                // ... (your cleanup logic)
            } else {
                error_log('Activator: Test data cleanup SQL file not found: ' . $cleanup_file);
            }

            // Use fully qualified namespace. Composer autoloader will find src/Test/TestUserCreator.php
            // Make sure TestUserCreator.php contains "namespace Weebunz\Test;" and "class TestUserCreator"
            // Also, ensure the filename matches the class name: TestUserCreator.php
            error_log('Activator: Attempting to instantiate \Weebunz\Test\TestUserCreator');
            $user_creator = new \Weebunz\Test\TestUserCreator();
            $user_ids = $user_creator->create_users();
            error_log('Activator: Test users created. IDs: ' . implode(', ', $user_ids ?: []));

            $test_data_file = WEEBUNZ_PLUGIN_DIR . 'src/Database/TestData/quiz-data.sql';
            if (file_exists($test_data_file)) {
                error_log('Activator: Running main test data SQL: ' . $test_data_file);
                // ... (your test data insertion logic) ...
            } else {
                error_log('Activator: Main test data SQL file not found: ' . $test_data_file);
                throw new \Exception('Test data file not found: ' . $test_data_file);
            }
            
            error_log('Activator: initialize_test_data() completed successfully.');
        } catch (\Exception $e) {
            if (isset($wpdb) && $wpdb->dbh) { // Check if $wpdb->dbh is set before trying to rollback
                $wpdb->query('ROLLBACK');
                error_log('Activator: Rolled back transaction in initialize_test_data due to exception.');
            }
            // Log the full exception details
            error_log('Activator: EXCEPTION in initialize_test_data(): ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
            
            if (self::$is_dev_mode) { // Only re-throw in dev mode to potentially be caught by activate()
                throw new \Exception('Test data initialization failed: ' . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Update version information
     */
    private static function update_version_options() {
        error_log('Activator: update_version_options() started.');
        // Ensure WEEBUNZ_VERSION is defined and used for consistency.
        $version_to_store = defined('WEEBUNZ_VERSION') ? WEEBUNZ_VERSION : self::$current_version;

        update_option(self::$db_version_option, $version_to_store);
        update_option(self::$plugin_version_option, $version_to_store);
        error_log('Activator: update_version_options() completed. Stored version (' . self::$plugin_version_option . '): ' . $version_to_store);
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate() {
        error_log('Activator: deactivate() method CALLED.');
        // Clean up temporary data
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/weebunz/temp';
        
        if (file_exists($temp_dir)) {
            self::remove_directory($temp_dir);
            wp_mkdir_p($temp_dir); // Recreate empty temp dir
            error_log('Activator: Temporary directory cleaned and recreated.');
        } else {
            error_log('Activator: Temporary directory not found during deactivation.');
        }
    }

    /**
     * Plugin uninstall handler
     * NOTE: This is typically registered in `uninstall.php` in the plugin root,
     * not via `register_uninstall_hook` to avoid loading the whole plugin.
     * If using `register_uninstall_hook(__FILE__, [self::class, 'uninstall'])`,
     * this method will be called.
     */
    public static function uninstall() {
        error_log('Activator: uninstall() method CALLED.');
        // Add actual uninstallation logic here:
        // - Delete options (e.g., delete_option(self::$plugin_version_option);)
        // - Delete custom tables (e.g., $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}quiz_types");)
        // - Remove directories created by the plugin.
        // - Be very careful with data deletion!
    }

    /**
     * Helper method to remove a directory and its contents
     */
    private static function remove_directory($dir) {
        if (!file_exists($dir) || !is_dir($dir)) { // Added is_dir check
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file; // Use DIRECTORY_SEPARATOR
            is_dir($path) ? self::remove_directory($path) : unlink($path);
        }
        return rmdir($dir);
    }
}