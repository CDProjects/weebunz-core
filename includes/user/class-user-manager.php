<?php
namespace Weebunz\User;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class User_Manager {
    private $wpdb;
    private $weekly_spend_limit;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->weekly_spend_limit = get_option('weebunz_weekly_spend_limit', 35);
        Logger::debug('User_Manager initialized', [
            'spend_limit' => $this->weekly_spend_limit
        ]);
    }

    /**
     * Verify user's account status
     */
    public function verify_user($user_id, $verification_data) {
        try {
            Logger::debug('Verifying user', [
                'user_id' => $user_id,
                'verification_type' => $verification_data['method'] ?? 'unknown'
            ]);

            $existing = get_user_meta($user_id, '_weebunz_verification', true);
            
            $verification = wp_parse_args($verification_data, [
                'verified' => false,
                'method' => '',
                'timestamp' => current_time('mysql'),
                'verification_data' => []
            ]);

            update_user_meta($user_id, '_weebunz_verification', $verification);
            
            Logger::info('User verification status updated', [
                'user_id' => $user_id,
                'verified' => $verification['verified'],
                'method' => $verification['method']
            ]);

            return [
                'success' => true,
                'message' => $verification['verified'] ? 'User verified successfully' : 'Verification status updated'
            ];

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'verify_user',
                'user_id' => $user_id
            ]);
            throw $e;
        }
    }

    /**
     * Check user's weekly spending limit
     */
    public function check_spending_limit($user_id) {
        try {
            Logger::debug('Checking spending limit', ['user_id' => $user_id]);

            $week_start = date('Y-m-d', strtotime('monday this week'));
            
            $total_spent = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) 
                FROM {$this->wpdb->prefix}spending_log 
                WHERE user_id = %d 
                AND created_at >= %s",
                $user_id,
                $week_start
            ));

            if ($total_spent === null) {
                throw new \Exception('Failed to retrieve spending data');
            }

            $result = [
                'limit' => $this->weekly_spend_limit,
                'spent' => floatval($total_spent),
                'remaining' => $this->weekly_spend_limit - floatval($total_spent),
                'can_spend' => floatval($total_spent) < $this->weekly_spend_limit
            ];

            Logger::debug('Spending limit checked', $result);
            return $result;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'check_spending_limit',
                'user_id' => $user_id
            ]);
            throw $e;
        }
    }

    /**
     * Get user's membership status
     */
    public function get_membership_status($user_id) {
        try {
            Logger::debug('Getting membership status', ['user_id' => $user_id]);

            $membership = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}platinum_memberships 
                WHERE user_id = %d 
                AND status = 'active' 
                AND NOW() BETWEEN start_date AND end_date",
                $user_id
            ));

            $result = [
                'is_platinum' => !empty($membership),
                'type' => !empty($membership) ? 'platinum' : 'regular',
                'expiry_date' => !empty($membership) ? $membership->end_date : null,
                'free_quizzes_remaining' => !empty($membership) ? $membership->free_quizzes_remaining : 0
            ];

            Logger::debug('Membership status retrieved', $result);
            return $result;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'get_membership_status',
                'user_id' => $user_id
            ]);
            throw $e;
        }
    }

    /**
     * Record user spending
     */
    public function record_spending($user_id, $amount, $type = 'quiz') {
        try {
            Logger::info('Recording user spending', [
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type
            ]);

            if (!$this->can_spend($user_id, $amount)) {
                Logger::warning('Spending limit would be exceeded', [
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'type' => $type
                ]);
                return [
                    'success' => false,
                    'message' => 'Weekly spending limit would be exceeded'
                ];
            }

            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'spending_log',
                [
                    'user_id' => $user_id,
                    'amount' => $amount,
                    'type' => $type,
                    'created_at' => current_time('mysql')
                ]
            );

            if ($result === false) {
                throw new \Exception('Failed to record spending');
            }

            Logger::info('Spending recorded successfully', [
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type
            ]);

            return [
                'success' => true,
                'message' => 'Spending recorded successfully'
            ];

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'record_spending',
                'user_id' => $user_id,
                'amount' => $amount,
                'type' => $type
            ]);
            throw $e;
        }
    }

    /**
     * Check if user can spend amount
     */
    private function can_spend($user_id, $amount) {
        try {
            $spending = $this->check_spending_limit($user_id);
            $can_spend = ($spending['spent'] + $amount) <= $spending['limit'];

            Logger::debug('Checking if user can spend', [
                'user_id' => $user_id,
                'amount' => $amount,
                'current_spent' => $spending['spent'],
                'limit' => $spending['limit'],
                'can_spend' => $can_spend
            ]);

            return $can_spend;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'can_spend',
                'user_id' => $user_id,
                'amount' => $amount
            ]);
            return false;
        }
    }
}