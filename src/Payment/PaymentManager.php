<?php
namespace Weebunz\Payment;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class Payment_Manager {
    private $wpdb;
    private $user_id;
    private $weekly_spend_limit;

    public function __construct($user_id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_id = $user_id;
        $this->weekly_spend_limit = get_option('weebunz_weekly_spend_limit', 35);

        Logger::debug('Payment_Manager initialized', [
            'user_id' => $this->user_id,
            'weekly_spend_limit' => $this->weekly_spend_limit
        ]);
    }

    /**
     * Process payment for quiz or membership
     */
    public function process_payment($amount, $type, $reference_id = null) {
        try {
            Logger::info('Processing payment', [
                'amount' => $amount,
                'type' => $type,
                'reference_id' => $reference_id
            ]);

            if (!$this->user_id) {
                throw new \Exception('User ID is required for payment processing');
            }

            // Check spending limit for quiz payments
            if ($type === 'quiz' && !$this->check_spending_limit($amount)) {
                Logger::warning('Payment rejected - Weekly spend limit would be exceeded', [
                    'user_id' => $this->user_id,
                    'amount' => $amount,
                    'current_spent' => $this->get_weekly_spent()
                ]);
                return [
                    'success' => false,
                    'message' => 'Weekly spending limit would be exceeded'
                ];
            }

            // Start transaction
            $this->wpdb->query('START TRANSACTION');

            // Record payment in spending log
            $spending_result = $this->wpdb->insert(
                $this->wpdb->prefix . 'spending_log',
                [
                    'user_id' => $this->user_id,
                    'amount' => $amount,
                    'type' => $type,
                    'reference_id' => $reference_id,
                    'created_at' => current_time('mysql')
                ]
            );

            if ($spending_result === false) {
                throw new \Exception('Failed to record payment in spending log');
            }

            $this->wpdb->query('COMMIT');

            Logger::info('Payment processed successfully', [
                'user_id' => $this->user_id,
                'amount' => $amount,
                'type' => $type
            ]);

            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => $this->wpdb->insert_id
            ];

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            Logger::exception($e, [
                'context' => 'process_payment',
                'user_id' => $this->user_id,
                'amount' => $amount,
                'type' => $type
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Process refund
     */
    public function process_refund($transaction_id, $reason) {
        try {
            Logger::info('Processing refund', [
                'transaction_id' => $transaction_id,
                'reason' => $reason
            ]);

            $transaction = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}spending_log WHERE id = %d",
                $transaction_id
            ));

            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            $this->wpdb->query('START TRANSACTION');

            // Record refund
            $refund_result = $this->wpdb->insert(
                $this->wpdb->prefix . 'spending_log',
                [
                    'user_id' => $transaction->user_id,
                    'amount' => -$transaction->amount, // Negative amount for refund
                    'type' => $transaction->type . '_refund',
                    'reference_id' => $transaction_id,
                    'description' => $reason,
                    'created_at' => current_time('mysql')
                ]
            );

            if ($refund_result === false) {
                throw new \Exception('Failed to record refund');
            }

            $this->wpdb->query('COMMIT');

            Logger::info('Refund processed successfully', [
                'transaction_id' => $transaction_id,
                'amount' => $transaction->amount,
                'user_id' => $transaction->user_id
            ]);

            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $this->wpdb->insert_id
            ];

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            Logger::exception($e, [
                'context' => 'process_refund',
                'transaction_id' => $transaction_id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if amount would exceed weekly spending limit
     */
    private function check_spending_limit($amount) {
        try {
            $current_spent = $this->get_weekly_spent();
            $would_exceed = ($current_spent + $amount) > $this->weekly_spend_limit;

            Logger::debug('Checking spending limit', [
                'user_id' => $this->user_id,
                'current_spent' => $current_spent,
                'amount' => $amount,
                'would_exceed' => $would_exceed
            ]);

            return !$would_exceed;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'check_spending_limit',
                'user_id' => $this->user_id,
                'amount' => $amount
            ]);
            return false;
        }
    }

    /**
     * Get total spent this week
     */
    private function get_weekly_spent() {
        try {
            $week_start = date('Y-m-d', strtotime('monday this week'));
            
            $total = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) 
                FROM {$this->wpdb->prefix}spending_log 
                WHERE user_id = %d 
                AND type = 'quiz'
                AND created_at >= %s",
                $this->user_id,
                $week_start
            ));

            Logger::debug('Retrieved weekly spending', [
                'user_id' => $this->user_id,
                'total' => $total,
                'week_start' => $week_start
            ]);

            return floatval($total);

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'get_weekly_spent',
                'user_id' => $this->user_id
            ]);
            return 0;
        }
    }

    /**
     * Get payment history for user
     */
    public function get_payment_history($limit = 10, $offset = 0) {
        try {
            Logger::debug('Retrieving payment history', [
                'user_id' => $this->user_id,
                'limit' => $limit,
                'offset' => $offset
            ]);

            $history = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}spending_log 
                WHERE user_id = %d 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                $this->user_id,
                $limit,
                $offset
            ));

            Logger::debug('Payment history retrieved', [
                'user_id' => $this->user_id,
                'count' => count($history)
            ]);

            return $history;

        } catch (\Exception $e) {
            Logger::exception($e, [
                'context' => 'get_payment_history',
                'user_id' => $this->user_id
            ]);
            return [];
        }
    }
}