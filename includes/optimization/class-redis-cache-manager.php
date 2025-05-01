<?php
/**
 * Redis Cache Manager for WeeBunz Quiz Engine
 *
 * Provides a centralized caching mechanism using Redis
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
 * Redis Cache Manager for WeeBunz
 * 
 * Provides a centralized caching mechanism using Redis
 * Optimized for Kinsta hosting environment
 */
class Redis_Cache_Manager {
    private static $instance = null;
    private $redis = null;
    private $prefix = 'weebunz_';
    private $default_expiry = 3600; // 1 hour
    private $is_connected = false;

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
     * Initialize Redis connection
     */
    private function init() {
        try {
            // Check if Redis is available (Kinsta provides this)
            if (!class_exists('Redis')) {
                $this->log_warning('Redis extension not available, falling back to transients');
                return;
            }

            // Get Redis configuration from constants or environment
            $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
            $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
            $timeout = 2; // 2 second timeout
            $retry_interval = 100; // 100ms
            $read_timeout = 1; // 1 second read timeout

            // Create Redis instance
            $this->redis = new \Redis();
            
            // Connect with retry logic
            $retry_count = 3;
            $connected = false;
            
            while ($retry_count > 0 && !$connected) {
                try {
                    $connected = $this->redis->connect($host, $port, $timeout, null, $retry_interval, $read_timeout);
                } catch (\Exception $e) {
                    $retry_count--;
                    if ($retry_count <= 0) {
                        throw $e;
                    }
                    usleep(100000); // 100ms sleep before retry
                }
            }

            // Check if connection was successful
            if (!$connected) {
                throw new \Exception('Failed to connect to Redis after multiple attempts');
            }

            // Set authentication if provided
            if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
                $this->redis->auth(REDIS_PASSWORD);
            }

            // Select database if specified
            if (defined('REDIS_DATABASE') && REDIS_DATABASE) {
                $this->redis->select(REDIS_DATABASE);
            }

            $this->is_connected = true;
            $this->log_info('Redis cache initialized successfully');

        } catch (\Exception $e) {
            $this->log_error('Redis initialization failed: ' . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * Check if Redis is available
     */
    public function is_available() {
        return $this->is_connected && $this->redis !== null;
    }

    /**
     * Get item from cache
     */
    public function get($key, $default = null) {
        $cache_key = $this->prefix . $key;
        
        try {
            if (!$this->is_available()) {
                return get_transient($key) ?: $default;
            }

            $value = $this->redis->get($cache_key);
            if ($value === false) {
                return $default;
            }

            $decoded = json_decode($value, true);
            return ($decoded !== null) ? $decoded : $value;

        } catch (\Exception $e) {
            $this->log_error('Redis get failed for key ' . $key . ': ' . $e->getMessage());
            return get_transient($key) ?: $default;
        }
    }

    /**
     * Set item in cache
     */
    public function set($key, $value, $expiry = null) {
        $cache_key = $this->prefix . $key;
        $expiry = $expiry ?: $this->default_expiry;
        
        try {
            // Always set transient as fallback
            set_transient($key, $value, $expiry);
            
            if (!$this->is_available()) {
                return true;
            }

            // Serialize arrays and objects
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            return $this->redis->setex($cache_key, $expiry, $value);

        } catch (\Exception $e) {
            $this->log_error('Redis set failed for key ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete item from cache
     */
    public function delete($key) {
        $cache_key = $this->prefix . $key;
        
        try {
            // Always delete transient as fallback
            delete_transient($key);
            
            if (!$this->is_available()) {
                return true;
            }

            return $this->redis->del($cache_key) > 0;

        } catch (\Exception $e) {
            $this->log_error('Redis delete failed for key ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Flush all cache items with prefix
     */
    public function flush() {
        try {
            if (!$this->is_available()) {
                return false;
            }

            // Get all keys with prefix
            $keys = $this->redis->keys($this->prefix . '*');
            
            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }
            
            return true;

        } catch (\Exception $e) {
            $this->log_error('Redis flush failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment a counter in cache
     */
    public function increment($key, $by = 1, $expiry = null) {
        $cache_key = $this->prefix . $key;
        $expiry = $expiry ?: $this->default_expiry;
        
        try {
            if (!$this->is_available()) {
                $value = (int)get_transient($key) + $by;
                set_transient($key, $value, $expiry);
                return $value;
            }

            // Check if key exists
            if (!$this->redis->exists($cache_key)) {
                $this->redis->setex($cache_key, $expiry, $by);
                return $by;
            }

            $value = $this->redis->incrBy($cache_key, $by);
            
            // Refresh expiry
            $this->redis->expire($cache_key, $expiry);
            
            return $value;

        } catch (\Exception $e) {
            $this->log_error('Redis increment failed for key ' . $key . ': ' . $e->getMessage());
            
            // Fallback to transient
            $value = (int)get_transient($key) + $by;
            set_transient($key, $value, $expiry);
            return $value;
        }
    }

    /**
     * Get multiple items from cache
     */
    public function get_multiple(array $keys, $default = null) {
        try {
            if (!$this->is_available()) {
                $result = [];
                foreach ($keys as $key) {
                    $result[$key] = get_transient($key) ?: $default;
                }
                return $result;
            }

            $cache_keys = [];
            foreach ($keys as $key) {
                $cache_keys[] = $this->prefix . $key;
            }

            $values = $this->redis->mGet($cache_keys);
            
            $result = [];
            foreach ($keys as $i => $key) {
                $value = $values[$i];
                if ($value === false) {
                    $result[$key] = $default;
                    continue;
                }
                
                $decoded = json_decode($value, true);
                $result[$key] = ($decoded !== null) ? $decoded : $value;
            }
            
            return $result;

        } catch (\Exception $e) {
            $this->log_error('Redis get_multiple failed: ' . $e->getMessage());
            
            // Fallback to transients
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = get_transient($key) ?: $default;
            }
            return $result;
        }
    }

    /**
     * Set multiple items in cache
     */
    public function set_multiple(array $items, $expiry = null) {
        $expiry = $expiry ?: $this->default_expiry;
        
        try {
            // Always set transients as fallback
            foreach ($items as $key => $value) {
                set_transient($key, $value, $expiry);
            }
            
            if (!$this->is_available()) {
                return true;
            }

            $cache_items = [];
            foreach ($items as $key => $value) {
                // Serialize arrays and objects
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $cache_items[$this->prefix . $key] = $value;
            }

            // Use pipeline for better performance
            $pipe = $this->redis->pipeline();
            foreach ($cache_items as $key => $value) {
                $pipe->setex($key, $expiry, $value);
            }
            $pipe->exec();
            
            return true;

        } catch (\Exception $e) {
            $this->log_error('Redis set_multiple failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete multiple items from cache
     */
    public function delete_multiple(array $keys) {
        try {
            // Always delete transients as fallback
            foreach ($keys as $key) {
                delete_transient($key);
            }
            
            if (!$this->is_available()) {
                return true;
            }

            $cache_keys = [];
            foreach ($keys as $key) {
                $cache_keys[] = $this->prefix . $key;
            }

            return $this->redis->del($cache_keys) > 0;

        } catch (\Exception $e) {
            $this->log_error('Redis delete_multiple failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add item to cache only if it doesn't exist
     */
    public function add($key, $value, $expiry = null) {
        $cache_key = $this->prefix . $key;
        $expiry = $expiry ?: $this->default_expiry;
        
        try {
            if (!$this->is_available()) {
                if (get_transient($key) !== false) {
                    return false;
                }
                return set_transient($key, $value, $expiry);
            }

            // Serialize arrays and objects
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            // Use NX option to only set if key doesn't exist
            return $this->redis->set($cache_key, $value, ['nx', 'ex' => $expiry]);

        } catch (\Exception $e) {
            $this->log_error('Redis add failed for key ' . $key . ': ' . $e->getMessage());
            
            // Fallback to transient
            if (get_transient($key) !== false) {
                return false;
            }
            return set_transient($key, $value, $expiry);
        }
    }

    /**
     * Check if key exists in cache
     */
    public function has($key) {
        $cache_key = $this->prefix . $key;
        
        try {
            if (!$this->is_available()) {
                return get_transient($key) !== false;
            }

            return $this->redis->exists($cache_key);

        } catch (\Exception $e) {
            $this->log_error('Redis has failed for key ' . $key . ': ' . $e->getMessage());
            return get_transient($key) !== false;
        }
    }

    /**
     * Get cache statistics
     */
    public function get_stats() {
        try {
            if (!$this->is_available()) {
                return [
                    'status' => 'unavailable',
                    'using_fallback' => true
                ];
            }

            $info = $this->redis->info();
            return [
                'status' => 'connected',
                'version' => $info['redis_version'],
                'memory_used' => $info['used_memory_human'],
                'uptime' => $info['uptime_in_seconds'],
                'connected_clients' => $info['connected_clients'],
                'total_keys' => $info['db0'] ? explode('=', explode(',', $info['db0'])[0])[1] : 0
            ];

        } catch (\Exception $e) {
            $this->log_error('Redis stats failed: ' . $e->getMessage());
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log error message
     */
    private function log_error($message) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Redis Cache] ERROR: ' . $message);
        }
    }

    /**
     * Log warning message
     */
    private function log_warning($message) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Redis Cache] WARNING: ' . $message);
        }
    }

    /**
     * Log info message
     */
    private function log_info($message) {
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WeeBunz Redis Cache] INFO: ' . $message);
        }
    }
}
