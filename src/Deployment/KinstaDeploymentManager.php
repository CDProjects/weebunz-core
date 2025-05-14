<?php
/**
 * Kinsta Deployment Configuration for WeeBunz Quiz Engine
 *
 * This file provides deployment instructions and configuration for Kinsta hosting
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/deployment
 */

namespace Weebunz\Deployment;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kinsta Deployment Manager
 * 
 * Handles deployment configuration for Kinsta hosting
 */
class KinstaDeploymentManager {
    private static $instance = null;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Initialize deployment settings
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
     * Check if environment is ready for deployment
     */
    public function check_environment() {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '7.4', '>='),
            'mysql_version' => $this->check_mysql_version('5.7'),
            'redis_available' => class_exists('Redis'),
            'object_cache_enabled' => function_exists('wp_cache_add') && defined('WP_CACHE') && WP_CACHE,
            'memory_limit' => $this->check_memory_limit('128M')
        ];
        
        return $requirements;
    }
    
    /**
     * Check MySQL version
     */
    private function check_mysql_version($required_version) {
        global $wpdb;
        $mysql_version = $wpdb->get_var('SELECT VERSION()');
        return version_compare($mysql_version, $required_version, '>=');
    }
    
    /**
     * Check memory limit
     */
    private function check_memory_limit($required_limit) {
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_to_bytes($memory_limit);
        $required_limit_bytes = $this->convert_to_bytes($required_limit);
        
        return $memory_limit_bytes >= $required_limit_bytes;
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($memory_value) {
        $unit = strtoupper(substr($memory_value, -1));
        $value = intval(substr($memory_value, 0, -1));
        
        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Get deployment instructions
     */
    public function get_deployment_instructions() {
        return [
            'title' => 'Kinsta Deployment Instructions',
            'steps' => [
                [
                    'title' => 'Prepare WordPress Environment',
                    'instructions' => [
                        'Log in to your Kinsta MyKinsta dashboard',
                        'Create a new WordPress site or use an existing one',
                        'Make sure PHP version is set to 7.4 or higher',
                        'Enable Redis cache from the Tools section'
                    ]
                ],
                [
                    'title' => 'Upload Plugin',
                    'instructions' => [
                        'Access your site via SFTP using the credentials provided by Kinsta',
                        'Upload the entire weebunz-quiz-engine folder to wp-content/plugins/',
                        'Alternatively, zip the plugin folder and upload via WordPress admin'
                    ]
                ],
                [
                    'title' => 'Configure Object Cache',
                    'instructions' => [
                        'Upload the object-cache.php file to wp-content/',
                        'This file is configured to work with Kinsta\'s Redis implementation'
                    ]
                ],
                [
                    'title' => 'Database Setup',
                    'instructions' => [
                        'Activate the plugin through the WordPress admin',
                        'The plugin will automatically create the necessary database tables',
                        'To load sample data, go to WeeBunz > Settings > Tools and click "Load Sample Data"'
                    ]
                ],
                [
                    'title' => 'Performance Optimization',
                    'instructions' => [
                        'In MyKinsta dashboard, enable page caching',
                        'Configure CDN if available',
                        'Set up a Redis cache monitor to track cache usage'
                    ]
                ],
                [
                    'title' => 'Testing',
                    'instructions' => [
                        'Test the quiz functionality with a few users',
                        'Use the built-in load testing tool to simulate concurrent users',
                        'Monitor performance through the WeeBunz > Performance dashboard'
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Create deployment package
     */
    public function create_deployment_package($destination_path) {
        // Get plugin directory
        $plugin_dir = plugin_dir_path(dirname(dirname(__FILE__)));
        $plugin_dir_name = basename($plugin_dir);
        
        // Create zip file
        $zip_file = $destination_path . '/weebunz-quiz-engine.zip';
        
        if (file_exists($zip_file)) {
            unlink($zip_file);
        }
        
        $zip = new \ZipArchive();
        if ($zip->open($zip_file, \ZipArchive::CREATE) !== true) {
            return false;
        }
        
        // Add plugin files to zip
        $this->add_directory_to_zip($zip, $plugin_dir, $plugin_dir_name);
        
        // Add object-cache.php file
        $object_cache_file = dirname(dirname(dirname(__FILE__))) . '/object-cache.php';
        if (file_exists($object_cache_file)) {
            $zip->addFile($object_cache_file, 'object-cache.php');
        }
        
        // Add deployment instructions
        $instructions = $this->get_deployment_instructions();
        $instructions_file = $destination_path . '/deployment-instructions.txt';
        file_put_contents($instructions_file, $this->format_instructions($instructions));
        $zip->addFile($instructions_file, 'deployment-instructions.txt');
        
        $zip->close();
        
        // Clean up
        if (file_exists($instructions_file)) {
            unlink($instructions_file);
        }
        
        return $zip_file;
    }
    
    /**
     * Add directory to zip
     */
    private function add_directory_to_zip($zip, $dir, $zip_dir) {
        $dir = rtrim($dir, '/\\') . '/';
        
        // Create recursive directory iterator
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($dir));
                
                // Add current file to archive
                $zip->addFile($file_path, $zip_dir . '/' . $relative_path);
            }
        }
    }
    
    /**
     * Format instructions as text
     */
    private function format_instructions($instructions) {
        $text = $instructions['title'] . "\n";
        $text .= str_repeat('=', strlen($instructions['title'])) . "\n\n";
        
        foreach ($instructions['steps'] as $index => $step) {
            $text .= ($index + 1) . '. ' . $step['title'] . "\n";
            $text .= str_repeat('-', strlen($step['title']) + 3) . "\n";
            
            foreach ($step['instructions'] as $i => $instruction) {
                $text .= '   ' . ($i + 1) . '. ' . $instruction . "\n";
            }
            
            $text .= "\n";
        }
        
        return $text;
    }
}
