<?php
// File: /wp-content/plugins/weebunz-core/includes/quiz/class-quiz-session-handler.php

namespace Weebunz\Quiz;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class Quiz_Session_Handler {
    private $wpdb;
    private $user_id;
    private $cache_group = 'weebunz_quiz_sessions';
    private $session_expiry = 3600; // 1 hour

    public function __construct($user_id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_id = $user_id;
    }

    /**
     * Create new quiz session
     */
    public function create_session($quiz_id, $user_id = null) {
    try {
        Logger::info('Creating new quiz session', [
            'quiz_id' => $quiz_id,
            'user_id' => $user_id ?? $this->user_id
        ]);

        $session_id = wp_generate_uuid4();
        
        $session_data = [
            'session_id' => $session_id,
            'quiz_id' => $quiz_id,
            'user_id' => $user_id ?? $this->user_id,
            'current_question' => 0,
            'answers' => [],
            'start_time' => time(),
            'expires_at' => time() + $this->session_expiry,
            'last_activity' => time()
        ];

        // Store in transient
        // Ensure session data is stored properly
if (!set_transient('weebunz_quiz_session_' . $session_id, $session_data, $this->session_expiry)) {
    error_log('WeeBunz ERROR: Failed to store session in transient: ' . json_encode($session_data));
    throw new \Exception('Failed to create quiz session.');
}


        // Store in database
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'quiz_sessions',
            [
                'session_id' => $session_id,
                'quiz_type_id' => $quiz_id,
                'user_id' => $user_id ?? $this->user_id,
                'session_data' => maybe_serialize($session_data),
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', time() + $this->session_expiry)
            ]
        );

        if ($result === false) {
            throw new \Exception('Failed to store session in database');
        }

        // Store in cache
        wp_cache_set(
            $session_id, 
            $session_data, 
            $this->cache_group, 
            $this->session_expiry
        );

        Logger::debug('Session created successfully', [
            'session_id' => $session_id
        ]);

        return $session_data;

    } catch (\Exception $e) {
        Logger::error('Failed to create quiz session', [
            'quiz_id' => $quiz_id,
            'user_id' => $user_id,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

    /**
 * Get session data
 */
public function get_session_data($session_id) {
    Logger::debug('Getting session data', ['session_id' => $session_id]);

    if (empty($session_id)) {
        throw new \Exception('Session ID is required');
    }

    // Try cache first
    $session_data = wp_cache_get($session_id, $this->cache_group);
    
    if ($session_data === false) {
        // Try transient
        $session_data = get_transient('weebunz_quiz_session_' . $session_id);

        if (!$session_data) {
            Logger::debug('Session not found in cache or transient, checking database');
            
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
                Logger::error('Session not found or expired', ['session_id' => $session_id]);
                throw new \Exception('Session expired or not found');
            }

            $session_data = maybe_unserialize($session->session_data);
            
            // Restore to cache and transient
            wp_cache_set(
                $session_id, 
                $session_data, 
                $this->cache_group, 
                $this->session_expiry
            );

            set_transient(
                'weebunz_quiz_session_' . $session_id,
                $session_data,
                $this->session_expiry
            );
        }
    }

    // Validate session data
    if (!is_array($session_data) || !isset($session_data['session_id'])) {
        Logger::error('Invalid session data', ['session_id' => $session_id]);
        throw new \Exception('Invalid session data');
    }

    // Update last activity
    $this->update_session($session_id, ['last_activity' => time()]);

    return $session_data;
}

    /**
     * Update session data
     */
    public function update_session($session_id, $data) {
        try {
            $session_data = $this->get_session($session_id);
            if (!$session_data) {
                return false;
            }

            // Update session data
            $session_data = array_merge($session_data, $data);

            // Update cache
            wp_cache_set(
                $session_id, 
                $session_data, 
                $this->cache_group, 
                $this->session_expiry
            );

            // Update database
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'session_data' => maybe_serialize($session_data),
                    'updated_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );

            if ($result === false) {
                throw new \Exception('Failed to update session in database');
            }

            return $session_data;

        } catch (\Exception $e) {
            Logger::error('Failed to update quiz session', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Complete a quiz session
     */
    public function complete_session($session_id) {
        try {
            // Remove from cache
            wp_cache_delete($session_id, $this->cache_group);

            // Update database
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'status' => 'completed',
                    'ended_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );

            if ($result === false) {
                throw new \Exception('Failed to complete session in database');
            }

            Logger::info('Quiz session completed', ['session_id' => $session_id]);
            return true;

        } catch (\Exception $e) {
            Logger::error('Failed to complete quiz session', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanup_expired_sessions() {
        try {
            $result = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->wpdb->prefix}quiz_sessions 
                SET status = 'expired', 
                    ended_at = %s 
                WHERE status = 'active' 
                AND expires_at < NOW()",
                current_time('mysql')
            ));

            Logger::info('Cleaned up expired sessions', ['count' => $result]);
            return true;

        } catch (\Exception $e) {
            Logger::error('Failed to cleanup expired sessions', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}