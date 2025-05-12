<?php
/**
 * Enhanced Error Handler for WeeBunz Quiz Engine
 *
 * Provides improved error handling, logging, and recovery mechanisms
 * Optimized for production environment with detailed diagnostics
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/src/Optimization
 */

namespace Weebunz\Optimization;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Error Handler
 * 
 * Provides improved error handling, logging, and recovery mechanisms
 * Optimized for production environment with detailed diagnostics
 */
class ErrorHandler {
    private static $instance = null;
    private $error_log_file;
    private $is_initialized = false;
    private $error_counts = [];
    private $error_thresholds = [
        E_ERROR => 1,
        E_WARNING => 10,
        E_NOTICE => 50
    ];
    private $notification_sent = false;
    private $recovery_attempts = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->error_log_file = WP_CONTENT_DIR . '/weebunz-errors.log';
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
     * Initialize error handler
     */
    private function init() {
        if ($this->is_initialized) {
            return;
        }

        // Register error handlers
        set_error_handler([$this, 'handle_error']);
        set_exception_handler([$this, 'handle_exception']);
        register_shutdown_function([$this, 'handle_shutdown']);

        // Initialize error counts
        $this->error_counts = [
            E_ERROR => 0,
            E_WARNING => 0,
            E_NOTICE => 0,
            E_DEPRECATED => 0,
            E_USER_ERROR => 0,
            E_USER_WARNING => 0,
            E_USER_NOTICE => 0,
            E_USER_DEPRECATED => 0,
            E_RECOVERABLE_ERROR => 0
        ];

        $this->is_initialized = true;
        $this->log_info('Error handler initialized');
    }

    /**
     * Handle PHP errors
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        // Don't handle errors if they're suppressed with @
        if (error_reporting() === 0) {
            return false;
        }

        // Increment error count
        if (isset($this->error_counts[$errno])) {
            $this->error_counts[$errno]++;
        }

        // Map error level to log level
        $level = $this->map_error_to_log_level($errno);

        // Log the error
        $this->log($level, $errstr, [
            'file' => $errfile,
            'line' => $errline,
            'type' => $this->get_error_type_name($errno)
        ]);

        // Check if we need to take action based on error thresholds
        $this->check_error_thresholds();

        // Let PHP handle the error as well
        return false;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handle_exception($exception) {
        // Log the exception
        $this->log_exception($exception);

        // Increment error count
        $this->error_counts[E_ERROR]++;

        // Check if we need to take action
        $this->check_error_thresholds();

        // Display friendly error message in production
        if (!WP_DEBUG) {
            $this->display_friendly_error();
            exit;
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Log the fatal error
            $this->log_critical('Fatal error occurred', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $this->get_error_type_name($error['type'])
            ]);

            // Attempt recovery for certain types of errors
            $this->attempt_recovery($error);

            // Display friendly error message in production
            if (!WP_DEBUG) {
                $this->display_friendly_error();
            }
        }
    }

    /**
     * Map PHP error level to PSR-3 log level
     */
    private function map_error_to_log_level($errno) {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return 'error';
            
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'warning';
            
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'notice';
            
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'debug';
            
            default:
                return 'info';
        }
    }

    /**
     * Get error type name
     */
    private function get_error_type_name($errno) {
        switch ($errno) {
            case E_ERROR: return 'E_ERROR';
            case E_WARNING: return 'E_WARNING';
            case E_PARSE: return 'E_PARSE';
            case E_NOTICE: return 'E_NOTICE';
            case E_CORE_ERROR: return 'E_CORE_ERROR';
            case E_CORE_WARNING: return 'E_CORE_WARNING';
            case E_COMPILE_ERROR: return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING: return 'E_COMPILE_WARNING';
            case E_USER_ERROR: return 'E_USER_ERROR';
            case E_USER_WARNING: return 'E_USER_WARNING';
            case E_USER_NOTICE: return 'E_USER_NOTICE';
            case E_STRICT: return 'E_STRICT';
            case E_RECOVERABLE_ERROR: return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED: return 'E_DEPRECATED';
            case E_USER_DEPRECATED: return 'E_USER_DEPRECATED';
            default: return 'UNKNOWN';
        }
    }

    /**
     * Check if error thresholds have been exceeded
     */
    private function check_error_thresholds() {
        foreach ($this->error_thresholds as $errno => $threshold) {
            if (isset($this->error_counts[$errno]) && $this->error_counts[$errno] >= $threshold) {
                $this->handle_threshold_exceeded($errno);
            }
        }
    }

    /**
     * Handle threshold exceeded
     */
    private function handle_threshold_exceeded($errno) {
        // Only send notification once per request
        if ($this->notification_sent) {
            return;
        }

        $this->log_alert('Error threshold exceeded', [
            'type' => $this->get_error_type_name($errno),
            'count' => $this->error_counts[$errno],
            'threshold' => $this->error_thresholds[$errno]
        ]);

        // Send notification to admin if in production
        if (!WP_DEBUG) {
            $this->send_admin_notification($errno);
        }

        $this->notification_sent = true;
    }

    /**
     * Send notification to admin
     */
    private function send_admin_notification($errno) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $subject = sprintf(
            '[%s] Error threshold exceeded: %s',
            get_bloginfo('name'),
            $this->get_error_type_name($errno)
        );

        $message = sprintf(
            "Error threshold exceeded on your website:\n\n" .
            "Error type: %s\n" .
            "Count: %d\n" .
            "Threshold: %d\n\n" .
            "Please check the error logs for more details.\n\n" .
            "Site URL: %s",
            $this->get_error_type_name($errno),
            $this->error_counts[$errno],
            $this->error_thresholds[$errno],
            get_site_url()
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Display friendly error message
     */
    private function display_friendly_error() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            wp_send_json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.'
            ], 500);
            exit;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            wp_send_json([
                'code' => 'server_error',
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => ['status' => 500]
            ], 500);
            exit;
        }

        // For regular requests
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Temporary Error</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; color: #333; line-height: 1.6; padding: 20px; max-width: 600px; margin: 0 auto; }
                h1 { color: #d63638; }
                .container { background-color: #f8f9fa; border-radius: 5px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .button { display: inline-block; background-color: #2271b1; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Temporary Error</h1>
                <p>We\'re sorry, but something went wrong while processing your request. Our team has been notified and is working to fix the issue.</p>
                <p>Please try again in a few moments.</p>
                <p><a href="' . esc_url(home_url()) . '" class="button">Return to Homepage</a></p>
            </div>
        </body>
        </html>';
    }

    /**
     * Attempt recovery for certain types of errors
     */
    private function attempt_recovery($error) {
        // Generate a unique key for this error
        $error_key = md5($error['file'] . $error['line'] . $error['message']);
        
        // Check if we've already tried to recover from this error
        if (isset($this->recovery_attempts[$error_key])) {
            return false;
        }
        
        // Mark this error as attempted recovery
        $this->recovery_attempts[$error_key] = true;
        
        // Attempt different recovery strategies based on error message
        if (strpos($error['message'], 'Allowed memory size') !== false) {
            // Memory limit error
            $this->log_info('Attempting recovery from memory limit error');
            
            // Try to increase memory limit
            if (function_exists('wp_raise_memory_limit')) {
                wp_raise_memory_limit();
                return true;
            }
        }
        
        if (strpos($error['message'], 'Maximum execution time') !== false) {
            // Timeout error
            $this->log_info('Attempting recovery from timeout error');
            
            // Try to increase timeout
            if (function_exists('set_time_limit')) {
                set_time_limit(300); // 5 minutes
                return true;
            }
        }
        
        if (strpos($error['message'], 'database') !== false || 
            strpos($error['message'], 'MySQL') !== false || 
            strpos($error['message'], 'mysqli') !== false) {
            // Database error
            $this->log_info('Attempting recovery from database error');
            
            global $wpdb;
            if ($wpdb) {
                // Try to reconnect
                $wpdb->db_connect();
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get error statistics
     */
    public function get_error_stats() {
        return [
            'counts' => $this->error_counts,
            'thresholds' => $this->error_thresholds
        ];
    }

    /**
     * Set custom error threshold
     */
    public function set_error_threshold($errno, $threshold) {
        $this->error_thresholds[$errno] = $threshold;
    }

    /**
     * Log message with level
     */
    private function log($level, $message, $context = []) {
        if (function_exists('error_log')) {
            error_log('[WeeBunz Error Handler] ' . strtoupper($level) . ': ' . $message . ' ' . json_encode($context));
        }
    }

    /**
     * Log exception
     */
    private function log_exception($exception) {
        $this->log('error', $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Log error message
     */
    private function log_error($message, $context = []) {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message
     */
    private function log_warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }

    /**
     * Log info message
     */
    private function log_info($message, $context = []) {
        $this->log('info', $message, $context);
    }

    /**
     * Log critical message
     */
    private function log_critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }

    /**
     * Log alert message
     */
    private function log_alert($message, $context = []) {
        $this->log('alert', $message, $context);
    }
}
