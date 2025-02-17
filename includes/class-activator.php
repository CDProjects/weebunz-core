<?php
// Save as: wp-content/plugins/weebunz-core/includes/class-activator.php

namespace Weebunz;

if (!defined('ABSPATH')) {
    exit;
}

// Explicitly require the DB_Manager class
require_once WEEBUNZ_PLUGIN_DIR . 'includes/database/class-db-manager.php';

class Activator {
    private static $current_version = '1.1.0';
    private static $db_version_option = 'weebunz_db_version';
    private static $plugin_version_option = 'weebunz_version';
    private static $is_dev_mode;

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::$is_dev_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        try {
            self::initialize_plugin();
            self::update_database();
            self::initialize_test_data();
            self::update_version_options();
        } catch (\Exception $e) {
            error_log('WeeBunz Activation Error: ' . $e->getMessage());
            wp_die('Error activating WeeBunz plugin: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Initialize plugin requirements
     */
    private static function initialize_plugin() {
        // Create necessary directories
        $upload_dir = wp_upload_dir();
        $weebunz_dir = $upload_dir['basedir'] . '/weebunz';
        
        if (!file_exists($weebunz_dir)) {
            wp_mkdir_p($weebunz_dir);
            wp_mkdir_p($weebunz_dir . '/temp');
            wp_mkdir_p($weebunz_dir . '/exports');
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
            }
        }
    }

    /**
     * Update database schema and content
     */
    private static function update_database() {
        try {
            $db_manager = new Database\DB_Manager();
            $db_manager->create_tables();
        } catch (\Exception $e) {
            throw new \Exception('Database initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Initialize test data
     */
    private static function initialize_test_data() {
        if (!self::$is_dev_mode) {
            return;
        }

        try {
            global $wpdb;
            
            // Run cleanup first
            $cleanup_file = WEEBUNZ_PLUGIN_DIR . 'includes/database/test-data/cleanup.sql';
            if (file_exists($cleanup_file)) {
                $cleanup_sql = file_get_contents($cleanup_file);
                $cleanup_sql = str_replace('{prefix}', $wpdb->prefix, $cleanup_sql);
                
                $statements = array_filter(
                    array_map('trim', explode(';', $cleanup_sql)),
                    'strlen'
                );

                foreach ($statements as $statement) {
                    $wpdb->query($statement);
                }
            }

            // Create test users
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/test/class-test-user-creator.php';
            $user_creator = new Test\Test_User_Creator();
            $user_ids = $user_creator->create_users();

            // Insert test data
            $test_data_file = WEEBUNZ_PLUGIN_DIR . 'includes/database/test-data/quiz-data.sql';
            
            if (!file_exists($test_data_file)) {
                throw new \Exception('Test data file not found');
            }

            $sql = file_get_contents($test_data_file);
            $sql = str_replace('{prefix}', $wpdb->prefix, $sql);

            // Set user variables for the SQL
            if (!empty($user_ids[0])) {
                $wpdb->query($wpdb->prepare("SET @user1_id = %d", $user_ids[0]));
            }
            if (!empty($user_ids[1])) {
                $wpdb->query($wpdb->prepare("SET @user2_id = %d", $user_ids[1]));
            }

            $wpdb->query('START TRANSACTION');

            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                'strlen'
            );

            foreach ($statements as $statement) {
                $result = $wpdb->query($statement);
                if ($result === false) {
                    throw new \Exception("Error executing SQL: $statement");
                }
            }

            $wpdb->query('COMMIT');
            
        } catch (\Exception $e) {
            if (isset($wpdb)) {
                $wpdb->query('ROLLBACK');
            }
            error_log('WeeBunz Test Data Error: ' . $e->getMessage());
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw $e;
            }
        }
    }

    /**
     * Update version information
     */
    private static function update_version_options() {
        update_option(self::$db_version_option, self::$current_version);
        update_option(self::$plugin_version_option, self::$current_version);
    }

    /**
     * Plugin deactivation handler
     */
    public static function deactivate() {
        // Clean up temporary data
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/weebunz/temp';
        
        if (file_exists($temp_dir)) {
            self::remove_directory($temp_dir);
            wp_mkdir_p($temp_dir);
        }
    }

    /**
     * Plugin uninstall handler
     */
    public static function uninstall() {
        // This method intentionally left empty
        // Uninstall functionality is handled by uninstall.php
    }

    /**
     * Helper method to remove a directory and its contents
     */
    private static function remove_directory($dir) {
        if (!file_exists($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::remove_directory($path) : unlink($path);
        }
        return rmdir($dir);
    }
}