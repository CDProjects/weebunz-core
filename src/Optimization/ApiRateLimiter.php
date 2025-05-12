<?php
/**
 * API Rate Limiter for WeeBunz Quiz Engine
 *
 * Provides rate limiting for API endpoints to prevent abuse and ensure stability
 * Optimized for Kinsta hosting environment with Redis support
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/src/Optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Rate Limiter
 * 
 * Provides rate limiting for API endpoints to prevent abuse and ensure stability
 * Optimized for Kinsta hosting environment with Redis support
 */
class ApiRateLimiter {
    private static $instance = null;
    private $cache_manager;
    private $prefix = 'rate_limit_';
    private $default_limit = 60; // 60 requests per minute
    private $default_window = 60; // 1 minute window
    private $limits = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->cache_manager = Redis_Cache_Manager::get_instance();
        $this->init_limits();
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
     * Initialize rate limits for different endpoints
     */
    private function init_limits() {
        // Default limits for different endpoint types
        $this->limits = [
            'quiz/start' => [
                'limit' => 10,   // 10 requests per minute
                'window' => 60   // 1 minute window
            ],
            'quiz/question' => [
                'limit' => 120,  // 120 requests per minute (2 per second)
                'window' => 60   // 1 minute window
            ],
            'quiz/answer' => [
                'limit' => 120,  // 120 requests per minute (2 per second)
                'window' => 60   // 1 minute window
            ],
            'quiz/complete' => [
                'limit' => 10,   // 10 requests per minute
                'window' => 60   // 1 minute window
            ],
            'payment' => [
                'limit' => 20,   // 20 requests per minute
                'window' => 60   // 1 minute window
            ],
            'user' => [
                'limit' => 30,   // 30 requests per minute
                'window' => 60   // 1 minute window
            ],
            'admin' => [
                'limit' => 300,  // 300 requests per minute (5 per second)
                'window' => 60   // 1 minute window
            ]
        ];

        // Allow filtering of rate limits
        $this->limits = apply_filters('weebunz_rate_limits', $this->limits);
    }

    /**
     * Check if request is within rate limits
     * 
     * @param string $endpoint The API endpoint being accessed
     * @param string $identifier The unique identifier for the requester (IP, user ID, etc.)
     * @return bool|array True if within limits, or array with limit info if exceeded
     */
    public function check_rate_limit($endpoint, $identifier = null) {
        // Get client IP if identifier not provided
        if ($identifier === null) {
            $identifier = $this->get_client_ip();
        }

        // Find the appropriate limit for this endpoint
        $limit_key = $this->get_limit_key_for_endpoint($endpoint);
        $limit = $this->limits[$limit_key]['limit'] ?? $this->default_limit;
        $window = $this->limits[$limit_key]['window'] ?? $this->default_window;

        // Generate cache key
        $cache_key = $this->prefix . $limit_key . '_' . md5($identifier);

        // Get current count
        $current = $this->get_request_count($cache_key);
        
        // Increment count
        $count = $this->increment_request_count($cache_key, $window);
        
        // Check if limit exceeded
        if ($count > $limit) {
            // Calculate reset time
            $reset_time = time() + $window - ($current['timestamp'] % $window);
            
            $this->log_warning('Rate limit exceeded', [
                'endpoint' => $endpoint,
                'identifier' => $this->anonymize_identifier($identifier),
                'limit' => $limit,
                'count' => $count,
                'reset' => $reset_time
            ]);
            
            return [
                'limited' => true,
                'limit' => $limit,
                'remaining' => 0,
                'reset' => $reset_time,
                'retry_after' => $reset_time - time()
            ];
        }
        
        // Return limit info
        return [
            'limited' => false,
            'limit' => $limit,
            'remaining' => $limit - $count,
            'reset' => time() + $window - (time() % $window)
        ];
    }

    /**
     * Get the appropriate limit key for an endpoint
     */
    private function get_limit_key_for_endpoint($endpoint) {
        // Check for exact match
        if (isset($this->limits[$endpoint])) {
            return $endpoint;
        }
        
        // Check for partial match
        foreach (array_keys($this->limits) as $key) {
            if (strpos($endpoint, $key) === 0) {
                return $key;
            }
        }
        
        // Default to 'default'
        return 'default';
    }

    /**
     * Get current request count
     */
    private function get_request_count($cache_key) {
        $default = [
            'count' => 0,
            'timestamp' => time()
        ];
        
        return $this->cache_manager->get($cache_key, $default);
    }

    /**
     * Increment request count
     */
    private function increment_request_count($cache_key, $window) {
        $current = $this->get_request_count($cache_key);
        $time = time();
        
        // If window has expired, reset count
        if ($time - $current['timestamp'] >= $window) {
            $data = [
                'count' => 1,
                'timestamp' => $time
            ];
            $this->cache_manager->set($cache_key, $data, $window * 2);
            return 1;
        }
        
        // Increment count
        $data = [
            'count' => $current['count'] + 1,
            'timestamp' => $current['timestamp']
        ];
        $this->cache_manager->set($cache_key, $data, $window * 2);
        
        return $data['count'];
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        // Check for proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Common proxy header
            'HTTP_CLIENT_IP',        // Another common proxy header
            'REMOTE_ADDR'            // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // For X-Forwarded-For, use the first IP in the list
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $_SERVER[$header]);
                    return trim($ips[0]);
                }
                return $_SERVER[$header];
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Anonymize identifier for logging (privacy protection)
     */
    private function anonymize_identifier($identifier) {
        // If it's an IP address, mask the last octet
        if (filter_var($identifier, FILTER_VALIDATE_IP)) {
            return preg_replace('/(\d+\.\d+\.\d+\.)\d+/', '$1xxx', $identifier);
        }
        
        // Otherwise hash it
        return substr(md5($identifier), 0, 8) . '...';
    }

    /**
     * Apply rate limit headers to response
     */
    public function apply_headers($result, $endpoint, $identifier = null) {
        if (!is_array($result)) {
            return;
        }
        
        // Add rate limit headers
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);
        
        // If limited, add retry headers
        if ($result['limited']) {
            header('Retry-After: ' . $result['retry_after']);
            header('HTTP/1.1 429 Too Many Requests');
            
            // Return error response for API
            wp_send_json([
                'code' => 'rate_limit_exceeded',
                'message' => 'Rate limit exceeded. Please try again later.',
                'data' => [
                    'status' => 429,
                    'retry_after' => $result['retry_after']
                ]
            ], 429);
            exit;
        }
    }

    /**
     * Register rate limiting for REST API
     */
    public function register_rest_api_limiting() {
        add_filter('rest_pre_dispatch', [$this, 'check_rest_api_rate_limit'], 10, 3);
    }

    /**
     * Check rate limit for REST API requests
     */
    public function check_rest_api_rate_limit($result, $server, $request) {
        // Skip rate limiting for authenticated admin users
        if (current_user_can('manage_options')) {
            return $result;
        }
        
        // Get endpoint from request
        $route = $request->get_route();
        
        // Only rate limit our plugin endpoints
        if (strpos($route, '/weebunz/') !== 0) {
            return $result;
        }
        
        // Get identifier (user ID or IP)
        $user_id = get_current_user_id();
        $identifier = $user_id ? 'user_' . $user_id : $this->get_client_ip();
        
        // Check rate limit
        $limit_result = $this->check_rate_limit($route, $identifier);
        
        // Apply headers
        $this->apply_headers($limit_result, $route, $identifier);
        
        // If limited, return error response
        if ($limit_result['limited']) {
            return new \WP_Error(
                'rate_limit_exceeded',
                'Rate limit exceeded. Please try again later.',
                [
                    'status' => 429,
                    'retry_after' => $limit_result['retry_after']
                ]
            );
        }
        
        return $result;
    }

    /**
     * Log warning message
     */
    private function log_warning($message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Rate Limiter] WARNING: ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log error message
     */
    private function log_error($message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Rate Limiter] ERROR: ' . $message . ' ' . json_encode($context));
        }
    }
}
