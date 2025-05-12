<?php
// Location: /wp-content/plugins/weebunz-core/src/Util/Logger.php

namespace Weebunz\Util; // Updated namespace

if (!defined("ABSPATH")) {
    exit;
}

class Logger {
    const ERROR = "ERROR";
    const WARNING = "WARNING";
    const INFO = "INFO";
    const DEBUG = "DEBUG";

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
        $log_dir = $upload_dir["basedir"] . "/weebunz/logs";
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Create .htaccess to protect logs
        $htaccess = $log_dir . "/.htaccess";
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

        // Only log if WP_DEBUG is true, or if the level is ERROR or WARNING
        if (! (defined("WP_DEBUG") && WP_DEBUG) && ($level !== self::ERROR && $level !== self::WARNING) ) {
            return;
        }

        $timestamp = current_time("mysql");
        $context_str = !empty($context) ? " | " . wp_json_encode($context) : ""; // Use wp_json_encode
        $log_message = "[{$timestamp}] WeeBunz {$level}: {$message}{$context_str}";
        
        error_log($log_message); // Standard PHP error log

        // Also write to our custom log file if directory is writable
        $upload_dir = wp_upload_dir();
        if (isset($upload_dir["basedir"])) {
            $log_file = $upload_dir["basedir"] . "/weebunz/logs/weebunz-" . date("Y-m-d") . ".log";
            if (is_writable(dirname($log_file))) {
                 file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
            }
        }
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
    public static function exception(\Throwable $e, $context = []) { // Type hint Throwable for PHP 7+
        $context["file"] = $e->getFile();
        $context["line"] = $e->getLine();
        $context["trace"] = $e->getTraceAsString();
        
        self::error($e->getMessage(), $context);
    }
}
