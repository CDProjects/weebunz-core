<?php
/**
 * Kinsta Hosting Configuration for WeeBunz Quiz Engine
 *
 * This file provides specific configurations and optimizations for Kinsta hosting environment
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kinsta Configuration Manager
 * 
 * Handles Kinsta-specific configurations and optimizations
 */
class Kinsta_Config {
    private static $instance = null;
    private $is_kinsta = false;
    private $redis_enabled = false;
    private $object_cache_enabled = false;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->detect_kinsta_environment();
    }

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detect Kinsta hosting environment
     */
    private function detect_kinsta_environment() {
        // Check for Kinsta environment variables
        $this->is_kinsta = (
            defined('KINSTA_CACHE_PATH') || 
            (getenv('KINSTA_SERVICE_NAME') !== false) || 
            (isset($_SERVER['KINSTA_SERVICE_NAME']))
        );

        // Check for Redis availability
        $this->redis_enabled = (
            $this->is_kinsta && 
            class_exists('Redis') && 
            (defined('WP_REDIS_HOST') || getenv('WP_REDIS_HOST') !== false)
        );

        // Check for object cache
        $this->object_cache_enabled = (
            $this->is_kinsta && 
            function_exists('wp_cache_add') && 
            defined('WP_CACHE') && 
            WP_CACHE
        );
    }

    /**
     * Initialize Kinsta-specific configurations
     */
    public function init() {
        if (!$this->is_kinsta) {
            return;
        }

        // Set Redis configuration constants if not already defined
        if ($this->redis_enabled) {
            if (!defined('REDIS_HOST')) {
                define('REDIS_HOST', defined('WP_REDIS_HOST') ? WP_REDIS_HOST : getenv('WP_REDIS_HOST'));
            }
            
            if (!defined('REDIS_PORT')) {
                define('REDIS_PORT', defined('WP_REDIS_PORT') ? WP_REDIS_PORT : getenv('WP_REDIS_PORT'));
            }
            
            if (!defined('REDIS_PASSWORD')) {
                define('REDIS_PASSWORD', defined('WP_REDIS_PASSWORD') ? WP_REDIS_PASSWORD : getenv('WP_REDIS_PASSWORD'));
            }
        }

        // Register hooks
        add_action('init', [$this, 'register_kinsta_hooks']);
    }

    /**
     * Register Kinsta-specific hooks
     */
    public function register_kinsta_hooks() {
        // Add cache purging hooks
        add_action('weebunz_quiz_completed', [$this, 'purge_cache_on_quiz_completion']);
        add_action('weebunz_raffle_updated', [$this, 'purge_cache_on_raffle_update']);
        
        // Add performance optimization hooks
        add_filter('weebunz_performance_settings', [$this, 'optimize_performance_settings']);
        
        // Add CDN integration hooks
        add_filter('weebunz_asset_url', [$this, 'maybe_use_cdn_url']);
    }

    /**
     * Purge Kinsta cache on quiz completion
     */
    public function purge_cache_on_quiz_completion($quiz_id) {
        if (!$this->is_kinsta || !function_exists('kinsta_cache_purge')) {
            return;
        }
        
        // Purge specific URLs
        $urls = [
            home_url('/quiz/' . $quiz_id),
            home_url('/quiz-results/'),
            home_url('/leaderboard/')
        ];
        
        foreach ($urls as $url) {
            kinsta_cache_purge($url);
        }
    }

    /**
     * Purge Kinsta cache on raffle update
     */
    public function purge_cache_on_raffle_update($raffle_id) {
        if (!$this->is_kinsta || !function_exists('kinsta_cache_purge')) {
            return;
        }
        
        // Purge specific URLs
        $urls = [
            home_url('/raffle/' . $raffle_id),
            home_url('/raffles/'),
            home_url('/entry-lists/')
        ];
        
        foreach ($urls as $url) {
            kinsta_cache_purge($url);
        }
    }

    /**
     * Optimize performance settings for Kinsta
     */
    public function optimize_performance_settings($settings) {
        if (!$this->is_kinsta) {
            return $settings;
        }
        
        // Optimize settings for Kinsta
        $settings['use_redis_cache'] = $this->redis_enabled;
        $settings['use_object_cache'] = $this->object_cache_enabled;
        $settings['optimize_database'] = true;
        $settings['use_cdn'] = true;
        
        return $settings;
    }

    /**
     * Use CDN URL for assets if available
     */
    public function maybe_use_cdn_url($url) {
        if (!$this->is_kinsta) {
            return $url;
        }
        
        // Check if Kinsta CDN is configured
        $cdn_url = getenv('KINSTA_CDN_URL');
        if (!$cdn_url) {
            return $url;
        }
        
        // Replace site URL with CDN URL for static assets
        $site_url = site_url();
        $cdn_url = rtrim($cdn_url, '/');
        
        // Only apply to static assets
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'css', 'js', 'woff', 'woff2', 'ttf', 'eot'];
        $extension = pathinfo($url, PATHINFO_EXTENSION);
        
        if (in_array($extension, $extensions)) {
            return str_replace($site_url, $cdn_url, $url);
        }
        
        return $url;
    }

    /**
     * Check if running on Kinsta
     */
    public function is_kinsta_environment() {
        return $this->is_kinsta;
    }

    /**
     * Check if Redis is available
     */
    public function is_redis_available() {
        return $this->redis_enabled;
    }

    /**
     * Check if object cache is enabled
     */
    public function is_object_cache_enabled() {
        return $this->object_cache_enabled;
    }

    /**
     * Get Kinsta environment information
     */
    public function get_environment_info() {
        if (!$this->is_kinsta) {
            return [
                'is_kinsta' => false
            ];
        }
        
        return [
            'is_kinsta' => true,
            'redis_enabled' => $this->redis_enabled,
            'object_cache_enabled' => $this->object_cache_enabled,
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->get_mysql_version(),
            'service_name' => getenv('KINSTA_SERVICE_NAME') ?: 'unknown',
            'environment_type' => $this->get_environment_type()
        ];
    }

    /**
     * Get MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        return $wpdb->get_var('SELECT VERSION()');
    }

    /**
     * Get environment type (production, staging, etc.)
     */
    private function get_environment_type() {
        if (defined('KINSTA_ENVIRONMENT_TYPE')) {
            return KINSTA_ENVIRONMENT_TYPE;
        }
        
        if (getenv('KINSTA_ENVIRONMENT_TYPE')) {
            return getenv('KINSTA_ENVIRONMENT_TYPE');
        }
        
        return 'unknown';
    }
}
