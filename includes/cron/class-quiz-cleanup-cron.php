<?php
// File: /wp-content/plugins/weebunz-core/includes/cron/class-quiz-cleanup-cron.php

namespace Weebunz\Cron;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Quiz\Quiz_Session_Handler;
use Weebunz\Logger;

class Quiz_Cleanup_Cron {
    private $session_hook = 'weebunz_cleanup_expired_sessions';
    private $daily_hook = 'weebunz_daily_cleanup';
    private $weekly_hook = 'weebunz_weekly_cleanup';
    private $monthly_hook = 'weebunz_monthly_cleanup';
    private $session_handler;

    public function __construct() {
        $this->session_handler = new Quiz_Session_Handler();
        $this->init();
    }

    /**
     * Initialize cron jobs
     */
    public function init() {
        // Session cleanup - every 15 minutes
        if (!wp_next_scheduled($this->session_hook)) {
            wp_schedule_event(time(), 'fifteen_minutes', $this->session_hook);
            Logger::info('Quiz session cleanup scheduled');
        }

        // Daily cleanup
        if (!wp_next_scheduled($this->daily_hook)) {
            wp_schedule_event(time(), 'daily', $this->daily_hook);
            Logger::info('Daily cleanup scheduled');
        }

        // Weekly cleanup
        if (!wp_next_scheduled($this->weekly_hook)) {
            wp_schedule_event(time(), 'weekly', $this->weekly_hook);
            Logger::info('Weekly cleanup scheduled');
        }

        // Monthly cleanup
        if (!wp_next_scheduled($this->monthly_hook)) {
            wp_schedule_event(time(), 'monthly', $this->monthly_hook);
            Logger::info('Monthly cleanup scheduled');
        }

        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Add cleanup actions
        add_action($this->session_hook, [$this, 'cleanup_sessions']);
        add_action($this->daily_hook, [$this, 'daily_cleanup']);
        add_action($this->weekly_hook, [$this, 'weekly_cleanup']);
        add_action($this->monthly_hook, [$this, 'monthly_cleanup']);
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['fifteen_minutes'] = [
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 minutes', 'weebunz-core')
        ];

        $schedules['monthly'] = [
            'interval' => 2635200, // 30.5 days in seconds
            'display' => __('Monthly', 'weebunz-core')
        ];

        return $schedules;
    }

    /**
     * Clean up expired sessions
     */
    public function cleanup_sessions() {
        try {
            Logger::info('Starting session cleanup');
            global $wpdb;

            // Clean up expired sessions
            $this->session_handler->cleanup_expired_sessions();

            // Clean up abandoned quiz attempts
            $wpdb->query(
                "UPDATE {$wpdb->prefix}quiz_attempts 
                SET status = 'abandoned' 
                WHERE status = 'in_progress' 
                AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );

            Logger::info('Session cleanup completed');
        } catch (\Exception $e) {
            Logger::error('Session cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Daily cleanup tasks
     */
    public function daily_cleanup() {
        try {
            Logger::info('Starting daily cleanup');
            global $wpdb;

            // Clean temporary files
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/weebunz/temp';
            if (is_dir($temp_dir)) {
                array_map('unlink', glob("$temp_dir/*.*"));
            }

            // Clean up failed quiz attempts older than 24 hours
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}quiz_attempts 
                WHERE status = 'abandoned' 
                AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );

            Logger::info('Daily cleanup completed');
        } catch (\Exception $e) {
            Logger::error('Daily cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Weekly cleanup tasks
     */
    public function weekly_cleanup() {
        try {
            Logger::info('Starting weekly cleanup');
            global $wpdb;

            // Clean up old quiz attempts
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}quiz_attempts 
                WHERE status = 'completed' 
                AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );

            // Clean up old raffle entries for completed raffles
            $wpdb->query(
                "DELETE re FROM {$wpdb->prefix}raffle_entries re
                INNER JOIN {$wpdb->prefix}raffle_events r ON re.raffle_id = r.id
                WHERE r.status = 'completed' 
                AND r.event_date < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );

            Logger::info('Weekly cleanup completed');
        } catch (\Exception $e) {
            Logger::error('Weekly cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Monthly cleanup tasks
     */
    public function monthly_cleanup() {
        try {
            Logger::info('Starting monthly cleanup');
            global $wpdb;

            // Optimize database tables
            $tables = [
                'quiz_attempts',
                'quiz_sessions',
                'raffle_entries',
                'user_answers'
            ];

            foreach ($tables as $table) {
                $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}{$table}");
            }

            Logger::info('Monthly cleanup completed');
        } catch (\Exception $e) {
            Logger::error('Monthly cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Deactivate all cron jobs
     */
    public function deactivate() {
        $hooks = [
            $this->session_hook,
            $this->daily_hook,
            $this->weekly_hook,
            $this->monthly_hook
        ];

        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        Logger::info('All cleanup cron jobs unscheduled');
    }
}