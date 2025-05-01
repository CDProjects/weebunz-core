<?php
/**
 * Performance Monitor for WeeBunz Quiz Engine
 *
 * Provides real-time performance monitoring and metrics collection
 * Optimized for Kinsta hosting environment
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Monitor
 * 
 * Provides real-time performance monitoring and metrics collection
 * Optimized for Kinsta hosting environment
 */
class Performance_Monitor {
    private static $instance = null;
    private $cache_manager;
    private $start_time;
    private $metrics = [];
    private $timers = [];
    private $slow_threshold = 1.0; // 1 second threshold for slow operations
    private $memory_threshold = 10 * 1024 * 1024; // 10MB threshold for high memory usage
    private $prefix = 'perf_';
    private $enabled = true;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->cache_manager = Redis_Cache_Manager::get_instance();
        $this->start_time = microtime(true);
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
     * Initialize performance monitor
     */
    private function init() {
        // Check if monitoring is enabled
        $this->enabled = apply_filters('weebunz_performance_monitoring_enabled', true);
        
        if (!$this->enabled) {
            return;
        }

        // Initialize metrics
        $this->metrics = [
            'request_count' => 0,
            'slow_requests' => 0,
            'errors' => 0,
            'avg_response_time' => 0,
            'peak_memory' => 0
        ];

        // Register shutdown function to record metrics
        register_shutdown_function([$this, 'record_request_metrics']);
        
        // Add action hooks for monitoring
        add_action('rest_api_init', [$this, 'start_api_timer'], 1);
        add_action('rest_api_init', [$this, 'register_performance_endpoints']);
        add_filter('rest_pre_echo_response', [$this, 'end_api_timer'], 999, 3);
        
        $this->log_debug('Performance monitor initialized');
    }

    /**
     * Start a timer for measuring operation duration
     */
    public function start_timer($name) {
        if (!$this->enabled) {
            return;
        }
        
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage()
        ];
    }

    /**
     * End a timer and get the duration
     */
    public function end_timer($name) {
        if (!$this->enabled || !isset($this->timers[$name])) {
            return 0;
        }
        
        $end_time = microtime(true);
        $memory_end = memory_get_usage();
        
        $duration = $end_time - $this->timers[$name]['start'];
        $memory_used = $memory_end - $this->timers[$name]['memory_start'];
        
        // Log slow operations
        if ($duration > $this->slow_threshold) {
            $this->log_warning('Slow operation detected', [
                'operation' => $name,
                'duration' => round($duration, 4) . 's',
                'threshold' => $this->slow_threshold . 's'
            ]);
        }
        
        // Log high memory usage
        if ($memory_used > $this->memory_threshold) {
            $this->log_warning('High memory usage detected', [
                'operation' => $name,
                'memory_used' => round($memory_used / 1024 / 1024, 2) . 'MB',
                'threshold' => round($this->memory_threshold / 1024 / 1024, 2) . 'MB'
            ]);
        }
        
        // Store metrics for this operation
        $this->store_operation_metrics($name, $duration, $memory_used);
        
        unset($this->timers[$name]);
        
        return $duration;
    }

    /**
     * Store metrics for an operation
     */
    private function store_operation_metrics($name, $duration, $memory_used) {
        if (!$this->enabled || !$this->cache_manager->is_available()) {
            return;
        }
        
        $key = $this->prefix . 'op_' . $name;
        $metrics = $this->cache_manager->get($key, [
            'count' => 0,
            'total_time' => 0,
            'max_time' => 0,
            'total_memory' => 0,
            'max_memory' => 0
        ]);
        
        // Update metrics
        $metrics['count']++;
        $metrics['total_time'] += $duration;
        $metrics['max_time'] = max($metrics['max_time'], $duration);
        $metrics['total_memory'] += $memory_used;
        $metrics['max_memory'] = max($metrics['max_memory'], $memory_used);
        
        // Store updated metrics
        $this->cache_manager->set($key, $metrics, 86400); // 24 hours
    }

    /**
     * Start timer for API requests
     */
    public function start_api_timer() {
        if (!$this->enabled) {
            return;
        }
        
        $this->start_timer('api_request');
    }

    /**
     * End timer for API requests
     */
    public function end_api_timer($response, $server, $request) {
        if (!$this->enabled) {
            return $response;
        }
        
        $route = $request->get_route();
        
        // Only measure WeeBunz endpoints
        if (strpos($route, '/weebunz/') === 0) {
            $duration = $this->end_timer('api_request');
            
            // Add performance header
            header('X-WeeBunz-Response-Time: ' . round($duration * 1000) . 'ms');
        }
        
        return $response;
    }

    /**
     * Record metrics for the current request
     */
    public function record_request_metrics() {
        if (!$this->enabled || !$this->cache_manager->is_available()) {
            return;
        }
        
        $end_time = microtime(true);
        $duration = $end_time - $this->start_time;
        $peak_memory = memory_get_peak_usage();
        
        // Get current metrics
        $key = $this->prefix . 'daily_' . date('Ymd');
        $metrics = $this->cache_manager->get($key, [
            'request_count' => 0,
            'total_time' => 0,
            'slow_requests' => 0,
            'errors' => 0,
            'peak_memory' => 0
        ]);
        
        // Update metrics
        $metrics['request_count']++;
        $metrics['total_time'] += $duration;
        
        if ($duration > $this->slow_threshold) {
            $metrics['slow_requests']++;
        }
        
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $metrics['errors']++;
        }
        
        $metrics['peak_memory'] = max($metrics['peak_memory'], $peak_memory);
        
        // Store updated metrics
        $this->cache_manager->set($key, $metrics, 86400); // 24 hours
    }

    /**
     * Register REST API endpoints for performance monitoring
     */
    public function register_performance_endpoints() {
        if (!$this->enabled) {
            return;
        }
        
        register_rest_route('weebunz/v1', '/admin/performance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_performance_metrics'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * Get performance metrics for admin dashboard
     */
    public function get_performance_metrics() {
        if (!$this->enabled || !$this->cache_manager->is_available()) {
            return new \WP_Error(
                'performance_monitoring_disabled',
                'Performance monitoring is disabled',
                ['status' => 404]
            );
        }
        
        // Get daily metrics
        $daily_key = $this->prefix . 'daily_' . date('Ymd');
        $daily_metrics = $this->cache_manager->get($daily_key, [
            'request_count' => 0,
            'total_time' => 0,
            'slow_requests' => 0,
            'errors' => 0,
            'peak_memory' => 0
        ]);
        
        // Calculate averages
        $avg_response_time = $daily_metrics['request_count'] > 0 
            ? $daily_metrics['total_time'] / $daily_metrics['request_count'] 
            : 0;
        
        // Get operation metrics
        $operation_metrics = [];
        $keys = $this->get_keys($this->prefix . 'op_*');
        
        foreach ($keys as $key) {
            $name = str_replace($this->prefix . 'op_', '', $key);
            $metrics = $this->cache_manager->get($key);
            
            if ($metrics && $metrics['count'] > 0) {
                $operation_metrics[$name] = [
                    'count' => $metrics['count'],
                    'avg_time' => $metrics['total_time'] / $metrics['count'],
                    'max_time' => $metrics['max_time'],
                    'avg_memory' => $metrics['total_memory'] / $metrics['count'],
                    'max_memory' => $metrics['max_memory']
                ];
            }
        }
        
        // Get system metrics
        $system_metrics = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false)['opcache_enabled'],
            'redis_available' => $this->cache_manager->is_available()
        ];
        
        // Return all metrics
        return [
            'daily' => [
                'request_count' => $daily_metrics['request_count'],
                'avg_response_time' => round($avg_response_time * 1000, 2) . 'ms',
                'slow_requests' => $daily_metrics['slow_requests'],
                'error_count' => $daily_metrics['errors'],
                'peak_memory' => round($daily_metrics['peak_memory'] / 1024 / 1024, 2) . 'MB'
            ],
            'operations' => $operation_metrics,
            'system' => $system_metrics
        ];
    }

    /**
     * Get keys matching a pattern
     */
    private function get_keys($pattern) {
        if (!$this->cache_manager->is_available()) {
            return [];
        }
        
        // This is a simplified implementation since our Redis_Cache_Manager doesn't have a keys method
        // In a real implementation, you would add a keys method to the Redis_Cache_Manager class
        return [];
    }

    /**
     * Set slow operation threshold
     */
    public function set_slow_threshold($seconds) {
        $this->slow_threshold = $seconds;
    }

    /**
     * Set memory usage threshold
     */
    public function set_memory_threshold($bytes) {
        $this->memory_threshold = $bytes;
    }

    /**
     * Enable or disable performance monitoring
     */
    public function set_enabled($enabled) {
        $this->enabled = $enabled;
    }

    /**
     * Log debug message
     */
    private function log_debug($message, $context = []) {
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WeeBunz Performance Monitor] DEBUG: ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log warning message
     */
    private function log_warning($message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Performance Monitor] WARNING: ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log error message
     */
    private function log_error($message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Performance Monitor] ERROR: ' . $message . ' ' . json_encode($context));
        }
    }
}
