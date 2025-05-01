<?php
/**
 * Database Optimization for WeeBunz Quiz Engine
 *
 * Adds indexes and optimizes tables for better performance with concurrent users
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Optimizer for WeeBunz
 * 
 * Handles database optimization for better performance with concurrent users
 */
class Database_Optimizer {
    private $wpdb;
    private static $instance = null;
    private $is_optimized = false;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
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
     * Check if database is optimized
     */
    public function is_optimized() {
        if ($this->is_optimized) {
            return true;
        }
        
        // Check if optimization has been performed by checking for a specific index
        $result = $this->wpdb->get_results(
            "SHOW INDEX FROM {$this->wpdb->prefix}quiz_sessions WHERE Key_name = 'idx_session_status_expires'"
        );
        
        $this->is_optimized = !empty($result);
        return $this->is_optimized;
    }

    /**
     * Run database optimization
     */
    public function optimize() {
        if ($this->is_optimized()) {
            $this->log_info('Database already optimized, skipping');
            return true;
        }

        try {
            $this->log_info('Starting database optimization');
            
            // Get SQL from file and replace prefix placeholder
            $sql = $this->get_optimization_sql();
            $sql = str_replace('{prefix}', $this->wpdb->prefix, $sql);
            
            // Split SQL into individual statements
            $statements = $this->split_sql($sql);
            
            // Execute each statement
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) {
                    continue;
                }
                
                $result = $this->wpdb->query($statement);
                if ($result === false) {
                    $this->log_error('Failed to execute SQL: ' . $statement . ' - Error: ' . $this->wpdb->last_error);
                }
            }
            
            $this->is_optimized = true;
            $this->log_info('Database optimization completed successfully');
            
            // Store optimization flag
            update_option('weebunz_db_optimized', time());
            
            return true;
            
        } catch (\Exception $e) {
            $this->log_error('Database optimization failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get optimization SQL
     */
    private function get_optimization_sql() {
        return "
-- Add indexes to quiz_sessions table for better session management
ALTER TABLE `{prefix}quiz_sessions` 
ADD INDEX `idx_session_status_expires` (`status`, `expires_at`),
ADD INDEX `idx_session_user_status` (`user_id`, `status`);

-- Add indexes to spending_log table for better weekly spending calculations
ALTER TABLE `{prefix}spending_log` 
ADD INDEX `idx_spending_user_date` (`user_id`, `created_at`),
ADD INDEX `idx_spending_type_date` (`type`, `created_at`);

-- Add indexes to quiz_attempts table for better user history queries
ALTER TABLE `{prefix}quiz_attempts` 
ADD INDEX `idx_attempts_user_quiz` (`user_id`, `quiz_id`),
ADD INDEX `idx_attempts_status_date` (`status`, `created_at`);

-- Add indexes to raffle_entries table for better entry counting
ALTER TABLE `{prefix}raffle_entries` 
ADD INDEX `idx_entries_raffle_user` (`raffle_id`, `user_id`),
ADD INDEX `idx_entries_source` (`source_type`, `source_id`);

-- Add indexes to user_answers table for better performance
ALTER TABLE `{prefix}user_answers` 
ADD INDEX `idx_user_answers_attempt_question` (`attempt_id`, `question_id`);

-- Add indexes to questions_pool table for better question selection
ALTER TABLE `{prefix}questions_pool` 
ADD INDEX `idx_questions_difficulty_category` (`difficulty_level`, `category`);

-- Add indexes to question_answers table for better answer retrieval
ALTER TABLE `{prefix}question_answers` 
ADD INDEX `idx_answers_correct` (`question_id`, `is_correct`);

-- Add indexes to platinum_memberships table for better membership management
ALTER TABLE `{prefix}platinum_memberships` 
ADD INDEX `idx_platinum_status_date` (`status`, `end_date`);

-- Optimize tables for better performance
OPTIMIZE TABLE `{prefix}quiz_sessions`;
OPTIMIZE TABLE `{prefix}quiz_attempts`;
OPTIMIZE TABLE `{prefix}user_answers`;
OPTIMIZE TABLE `{prefix}spending_log`;
OPTIMIZE TABLE `{prefix}raffle_entries`;
OPTIMIZE TABLE `{prefix}questions_pool`;
OPTIMIZE TABLE `{prefix}question_answers`;
OPTIMIZE TABLE `{prefix}platinum_memberships`;
";
    }

    /**
     * Split SQL into individual statements
     */
    private function split_sql($sql) {
        $sql = str_replace("\r", '', $sql);
        $statements = [];
        $current = '';
        
        foreach (explode("\n", $sql) as $line) {
            // Skip comments and empty lines
            if (empty($line) || substr($line, 0, 2) == '--') {
                continue;
            }
            
            $current .= $line . "\n";
            
            // If line ends with semicolon, it's the end of a statement
            if (substr(trim($line), -1) == ';') {
                $statements[] = $current;
                $current = '';
            }
        }
        
        // Add any remaining statement
        if (!empty($current)) {
            $statements[] = $current;
        }
        
        return $statements;
    }

    /**
     * Log error message
     */
    private function log_error($message) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz DB Optimizer] ERROR: ' . $message);
        }
    }

    /**
     * Log info message
     */
    private function log_info($message) {
        if (function_exists('error_log') && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WeeBunz DB Optimizer] INFO: ' . $message);
        }
    }
}
