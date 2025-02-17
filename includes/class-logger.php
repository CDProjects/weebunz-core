<?php
// Location: /wp-content/plugins/weebunz-core/includes/class-logger.php

namespace Weebunz;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    const ERROR = 'ERROR';
    const WARNING = 'WARNING';
    const INFO = 'INFO';
    const DEBUG = 'DEBUG';

    private static $initialized = false;

    /**
     * Initialize logger
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Ensure log directory exists
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/weebunz/logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Create .htaccess to protect logs
        $htaccess = $log_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
        }
    }

    /**
     * Log a message
     */
    public static function log($message, $level = self::INFO, $context = []) {
        if (!self::$initialized) {
            self::init();
        }

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $timestamp = current_time('mysql');
        $context_str = !empty($context) ? ' | ' . json_encode($context) : '';
        $log_message = "[{$timestamp}] WeeBunz {$level}: {$message}{$context_str}";
        
        error_log($log_message);

        // Also write to our custom log file
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/weebunz/logs/weebunz-' . date('Y-m-d') . '.log';
        file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Log an error
     */
    public static function error($message, $context = []) {
        self::log($message, self::ERROR, $context);
    }

    /**
     * Log a warning
     */
    public static function warning($message, $context = []) {
        self::log($message, self::WARNING, $context);
    }

    /**
     * Log debug information
     */
    public static function debug($message, $context = []) {
        self::log($message, self::DEBUG, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = []) {
        self::log($message, self::INFO, $context);
    }

    /**
     * Log an exception with stack trace
     */
    public static function exception(\Exception $e, $context = []) {
        $context['file'] = $e->getFile();
        $context['line'] = $e->getLine();
        $context['trace'] = $e->getTraceAsString();
        
        self::error($e->getMessage(), $context);
    }
}