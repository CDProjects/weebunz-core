<?php
/**
 * Enhanced Quiz Session Handler for WeeBunz Quiz Engine
 *
 * Provides improved session handling for quiz sessions with Redis support
 * Optimized for concurrent users and Kinsta hosting
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/src/Optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Quiz Session Handler
 * 
 * Provides improved session handling for quiz sessions with Redis support
 * Optimized for concurrent users and Kinsta hosting
 */
class EnhancedSessionHandler {
    private $wpdb;
    private $user_id;
    private $cache_manager;
    private $session_expiry = 3600; // 1 hour
    private $lock_timeout = 10; // 10 seconds
    private $session_prefix = 'quiz_session_';
    private $lock_prefix = 'quiz_session_lock_';

    /**
     * Constructor
     */
    public function __construct($user_id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_id = $user_id ?: get_current_user_id();
        $this->cache_manager = Redis_Cache_Manager::get_instance();
        
        // Register shutdown function to release locks
        register_shutdown_function([$this, 'release_all_locks']);
    }

    /**
     * Create new quiz session with optimized storage
     */
    public function create_session($quiz_id, $user_id = null) {
        try {
            $user_id = $user_id ?: $this->user_id;
            
            $this->log_info('Creating new quiz session', [
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            ]);

            $session_id = wp_generate_uuid4();
            
            $session_data = [
                'session_id' => $session_id,
                'quiz_id' => $quiz_id,
                'user_id' => $user_id,
                'current_question' => 0,
                'answers' => [],
                'start_time' => time(),
                'expires_at' => time() + $this->session_expiry,
                'last_activity' => time()
            ];

            // Store in cache first
            $cache_key = $this->session_prefix . $session_id;
            $this->cache_manager->set($cache_key, $session_data, $this->session_expiry);

            // Store in database for persistence
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'session_id' => $session_id,
                    'quiz_type_id' => $quiz_id,
                    'user_id' => $user_id,
                    'session_data' => maybe_serialize($session_data),
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'expires_at' => date('Y-m-d H:i:s', time() + $this->session_expiry)
                ]
            );

            if ($result === false) {
                throw new \Exception('Failed to store session in database');
            }

            $this->log_debug('Session created successfully', [
                'session_id' => $session_id
            ]);

            return $session_data;

        } catch (\Exception $e) {
            $this->log_error('Failed to create quiz session', [
                'quiz_id' => $quiz_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get session data with locking for concurrent access
     */
    public function get_session_data($session_id, $acquire_lock = true) {
        $this->log_debug('Getting session data', ['session_id' => $session_id]);

        if (empty($session_id)) {
            throw new \Exception('Session ID is required');
        }

        // Try to acquire lock if needed
        if ($acquire_lock && !$this->acquire_lock($session_id)) {
            throw new \Exception('Session is currently in use by another request');
        }

        try {
            // Try cache first
            $cache_key = $this->session_prefix . $session_id;
            $session_data = $this->cache_manager->get($cache_key);
            
            if (!$session_data) {
                $this->log_debug('Session not found in cache, checking database');
                
                // Try database
                $session = $this->wpdb->get_row($this->wpdb->prepare(
                    "SELECT session_data 
                    FROM {$this->wpdb->prefix}quiz_sessions 
                    WHERE session_id = %s 
                    AND status = 'active' 
                    AND expires_at > NOW()",
                    $session_id
                ));

                if (!$session) {
                    if ($acquire_lock) {
                        $this->release_lock($session_id);
                    }
                    $this->log_error('Session not found or expired', ['session_id' => $session_id]);
                    throw new \Exception('Session expired or not found');
                }

                $session_data = maybe_unserialize($session->session_data);
                
                // Restore to cache
                $this->cache_manager->set($cache_key, $session_data, $this->session_expiry);
            }

            // Validate session data
            if (!is_array($session_data) || !isset($session_data['session_id'])) {
                if ($acquire_lock) {
                    $this->release_lock($session_id);
                }
                $this->log_error('Invalid session data', ['session_id' => $session_id]);
                throw new \Exception('Invalid session data');
            }

            // Update last activity
            if ($acquire_lock) {
                $session_data['last_activity'] = time();
                $this->update_session($session_id, $session_data, false);
            }

            return $session_data;
            
        } catch (\Exception $e) {
            if ($acquire_lock) {
                $this->release_lock($session_id);
            }
            throw $e;
        }
    }

    /**
     * Update session data with optimized storage
     */
    public function update_session($session_id, $data, $acquire_lock = true) {
        try {
            // Acquire lock if needed
            if ($acquire_lock && !$this->acquire_lock($session_id)) {
                throw new \Exception('Session is currently in use by another request');
            }

            // Get current session data
            $current_data = $this->get_session_data($session_id, false);
            if (!$current_data) {
                if ($acquire_lock) {
                    $this->release_lock($session_id);
                }
                return false;
            }

            // Update session data
            $session_data = is_array($data) ? array_merge($current_data, $data) : $data;
            
            // Ensure last_activity is updated
            if (!isset($session_data['last_activity']) || $session_data['last_activity'] < time() - 60) {
                $session_data['last_activity'] = time();
            }

            // Update cache
            $cache_key = $this->session_prefix . $session_id;
            $this->cache_manager->set($cache_key, $session_data, $this->session_expiry);

            // Update database asynchronously for better performance
            $this->schedule_db_update($session_id, $session_data);

            if ($acquire_lock) {
                $this->release_lock($session_id);
            }
            
            return $session_data;

        } catch (\Exception $e) {
            if ($acquire_lock) {
                $this->release_lock($session_id);
            }
            
            $this->log_error('Failed to update quiz session', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Schedule database update for better performance
     */
    private function schedule_db_update($session_id, $session_data) {
        // Use WordPress action scheduler if available
        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action(time(), 'weebunz_update_session_db', [
                'session_id' => $session_id,
                'session_data' => $session_data
            ]);
            return;
        }
        
        // Fallback to immediate update
        $this->update_session_db($session_id, $session_data);
    }

    /**
     * Update session in database
     */
    public function update_session_db($session_id, $session_data) {
        try {
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'session_data' => maybe_serialize($session_data),
                    'updated_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );

            if ($result === false) {
                $this->log_error('Failed to update session in database', [
                    'session_id' => $session_id
                ]);
            }
            
            return $result !== false;
            
        } catch (\Exception $e) {
            $this->log_error('Exception updating session in database', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Complete a quiz session with optimized cleanup
     */
    public function complete_session($session_id) {
        try {
            // Acquire lock
            if (!$this->acquire_lock($session_id)) {
                throw new \Exception('Session is currently in use by another request');
            }

            // Remove from cache
            $cache_key = $this->session_prefix . $session_id;
            $this->cache_manager->delete($cache_key);

            // Update database
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'status' => 'completed',
                    'ended_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );

            $this->release_lock($session_id);

            if ($result === false) {
                throw new \Exception('Failed to complete session in database');
            }

            $this->log_info('Quiz session completed', ['session_id' => $session_id]);
            return true;

        } catch (\Exception $e) {
            $this->release_lock($session_id);
            
            $this->log_error('Failed to complete quiz session', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clean up expired sessions with optimized query
     */
    public function cleanup_expired_sessions() {
        try {
            // Use transaction for consistency
            $this->wpdb->query('START TRANSACTION');
            
            // Get expired session IDs
            $expired_sessions = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT session_id FROM {$this->wpdb->prefix}quiz_sessions 
                WHERE status = 'active' AND expires_at < %s
                LIMIT 1000",
                current_time('mysql')
            ));
            
            if (!empty($expired_sessions)) {
                // Update status in database
                $this->wpdb->query($this->wpdb->prepare(
                    "UPDATE {$this->wpdb->prefix}quiz_sessions 
                    SET status = 'expired', ended_at = %s 
                    WHERE session_id IN (" . implode(',', array_fill(0, count($expired_sessions), '%s')) . ")",
                    array_merge([current_time('mysql')], $expired_sessions)
                ));
                
                // Remove from cache
                foreach ($expired_sessions as $session_id) {
                    $cache_key = $this->session_prefix . $session_id;
                    $this->cache_manager->delete($cache_key);
                }
            }
            
            $this->wpdb->query('COMMIT');
            
            $this->log_info('Cleaned up expired sessions', ['count' => count($expired_sessions)]);
            return count($expired_sessions);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            
            $this->log_error('Failed to cleanup expired sessions', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Acquire lock for session to prevent concurrent modifications
     */
    private function acquire_lock($session_id) {
        $lock_key = $this->lock_prefix . $session_id;
        
        // Try to acquire lock with Redis if available
        if ($this->cache_manager->is_available()) {
            // Use Redis NX option to only set if key doesn't exist
            $acquired = $this->cache_manager->add($lock_key, getmypid(), $this->lock_timeout);
            
            if ($acquired) {
                // Register this lock for cleanup
                $this->register_acquired_lock($session_id);
            }
            
            return $acquired;
        }
        
        // Fallback to transient-based locking
        $transient_key = 'weebunz_lock_' . $session_id;
        if (get_transient($transient_key)) {
            return false;
        }
        
        $acquired = set_transient($transient_key, getmypid(), $this->lock_timeout);
        
        if ($acquired) {
            $this->register_acquired_lock($session_id);
        }
        
        return $acquired;
    }

    /**
     * Release lock for session
     */
    public function release_lock($session_id) {
        $lock_key = $this->lock_prefix . $session_id;
        
        // Use Redis if available
        if ($this->cache_manager->is_available()) {
            $this->cache_manager->delete($lock_key);
        } else {
            delete_transient('weebunz_lock_' . $session_id);
        }
        
        // Unregister this lock
        $this->unregister_acquired_lock($session_id);
        
        return true;
    }

    /**
     * Register acquired lock for cleanup
     */
    private function register_acquired_lock($session_id) {
        static $acquired_locks = [];
        $acquired_locks[] = $session_id;
    }

    /**
     * Unregister acquired lock
     */
    private function unregister_acquired_lock($session_id) {
        static $acquired_locks = [];
        $acquired_locks = array_diff($acquired_locks, [$session_id]);
    }

    /**
     * Release all acquired locks on shutdown
     */
    public function release_all_locks() {
        static $acquired_locks = [];
        foreach ($acquired_locks as $session_id) {
            $this->release_lock($session_id);
        }
    }

    /**
     * Get active session count for monitoring
     */
    public function get_active_session_count() {
        try {
            return $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}quiz_sessions 
                WHERE status = 'active' AND expires_at > NOW()"
            );
        } catch (\Exception $e) {
            $this->log_error('Failed to get active session count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get user's active sessions
     */
    public function get_user_active_sessions($user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        
        try {
            $sessions = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT session_id, quiz_type_id, created_at 
                FROM {$this->wpdb->prefix}quiz_sessions 
                WHERE user_id = %d AND status = 'active' AND expires_at > NOW()",
                $user_id
            ));
            
            return $sessions ?: [];
            
        } catch (\Exception $e) {
            $this->log_error('Failed to get user active sessions', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Log error message
     */
    private function log_error($message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Session Handler] ERROR: ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log warning message
     */
    private function log_warning($message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Session Handler] WARNING: ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log info message
     */
    private function log_info($message, $context = []) {
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WeeBunz Session Handler] INFO: ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log debug message
     */
    private function log_debug($message, $context = []) {
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WeeBunz Session Handler] DEBUG: ' . $message . ' ' . json_encode($context));
        }
    }
}
