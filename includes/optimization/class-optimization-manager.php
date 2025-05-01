<?php
/**
 * Main integration file for WeeBunz Quiz Engine optimizations
 *
 * This file loads and initializes all optimization components
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optimization Manager
 * 
 * Initializes and manages all optimization components
 */
class Optimization_Manager {
    private static $instance = null;
    private $components = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->init();
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
     * Initialize optimization components
     */
    private function init() {
        // Load and initialize Redis Cache Manager
        require_once plugin_dir_path(__FILE__) . 'class-redis-cache-manager.php';
        $this->components['cache'] = Redis_Cache_Manager::get_instance();
        
        // Load and initialize Enhanced Session Handler
        require_once plugin_dir_path(__FILE__) . 'class-enhanced-session-handler.php';
        $this->components['session'] = new Enhanced_Session_Handler();
        
        // Load and initialize Database Optimizer
        require_once plugin_dir_path(__FILE__) . 'class-database-optimizer.php';
        $this->components['database'] = Database_Optimizer::get_instance();
        
        // Load and initialize API Rate Limiter
        require_once plugin_dir_path(__FILE__) . 'class-api-rate-limiter.php';
        $this->components['rate_limiter'] = API_Rate_Limiter::get_instance();
        
        // Load and initialize Error Handler
        require_once plugin_dir_path(__FILE__) . 'class-error-handler.php';
        $this->components['error_handler'] = Error_Handler::get_instance();
        
        // Load and initialize Performance Monitor
        require_once plugin_dir_path(__FILE__) . 'class-performance-monitor.php';
        $this->components['performance'] = Performance_Monitor::get_instance();
        
        // Load and initialize Kinsta Config
        require_once plugin_dir_path(__FILE__) . 'class-kinsta-config.php';
        $this->components['kinsta'] = Kinsta_Config::get_instance();
        
        // Register hooks
        add_action('init', [$this, 'register_hooks']);
        
        // Optimize database on plugin activation
        register_activation_hook(WEEBUNZ_QUIZ_PLUGIN_FILE, [$this, 'optimize_on_activation']);
    }

    /**
     * Register hooks for optimization components
     */
    public function register_hooks() {
        // Initialize Kinsta configuration
        if (isset($this->components['kinsta'])) {
            $this->components['kinsta']->init();
        }
        
        // Register API rate limiting
        if (isset($this->components['rate_limiter'])) {
            $this->components['rate_limiter']->register_rest_api_limiting();
        }
    }

    /**
     * Optimize database on plugin activation
     */
    public function optimize_on_activation() {
        if (isset($this->components['database'])) {
            $this->components['database']->optimize();
        }
    }

    /**
     * Get component instance
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }

    /**
     * Get optimization status
     */
    public function get_status() {
        $status = [];
        
        // Cache status
        if (isset($this->components['cache'])) {
            $status['cache'] = [
                'available' => $this->components['cache']->is_available(),
                'type' => $this->components['cache']->is_available() ? 'Redis' : 'Transients'
            ];
        }
        
        // Database optimization status
        if (isset($this->components['database'])) {
            $status['database'] = [
                'optimized' => $this->components['database']->is_optimized()
            ];
        }
        
        // Kinsta environment status
        if (isset($this->components['kinsta'])) {
            $status['kinsta'] = [
                'is_kinsta' => $this->components['kinsta']->is_kinsta_environment(),
                'redis_available' => $this->components['kinsta']->is_redis_available(),
                'object_cache_enabled' => $this->components['kinsta']->is_object_cache_enabled()
            ];
        }
        
        return $status;
    }
}
