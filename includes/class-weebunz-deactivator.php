<?php
/**
 * WeeBunz Quiz Engine Deactivator
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 */
class WeeBunz_Deactivator {

    /**
     * Clean up any necessary items during deactivation
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any transients and cached data
        self::clear_cache();
    }
    
    /**
     * Clear any cached data
     */
    private static function clear_cache() {
        // Delete transients
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_weebunz_quiz_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_timeout_weebunz_quiz_%'");
        
        // If Redis is enabled, clear Redis cache
        if (class_exists('Redis') && get_option('weebunz_quiz_enable_redis_cache') === 'yes') {
            try {
                $redis = new Redis();
                $redis->connect(
                    get_option('weebunz_quiz_redis_host', '127.0.0.1'),
                    get_option('weebunz_quiz_redis_port', 6379)
                );
                
                $auth = get_option('weebunz_quiz_redis_auth', '');
                if (!empty($auth)) {
                    $redis->auth($auth);
                }
                
                $db = get_option('weebunz_quiz_redis_db', 0);
                $redis->select($db);
                
                // Delete all keys with the weebunz_quiz prefix
                $keys = $redis->keys('weebunz_quiz_*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                
                $redis->close();
            } catch (Exception $e) {
                // Log error but continue
                error_log('WeeBunz Quiz Engine: Error clearing Redis cache: ' . $e->getMessage());
            }
        }
    }
}
