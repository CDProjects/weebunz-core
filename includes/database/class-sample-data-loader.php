<?php
/**
 * Sample Quiz Data Loader for WeeBunz Quiz Engine
 *
 * This file loads sample quiz data for demonstration purposes
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/database
 */

namespace Weebunz\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sample Data Loader
 * 
 * Loads sample quiz data for demonstration purposes
 */
class Sample_Data_Loader {
    private $wpdb;
    private $db_manager;
    private $prefix;
    private $test_data_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->test_data_dir = plugin_dir_path(__FILE__) . 'test-data/';
        
        // Get DB Manager instance
        require_once plugin_dir_path(__FILE__) . 'class-db-manager.php';
        $this->db_manager = DB_Manager::get_instance();
    }
    
    /**
     * Load sample data
     */
    public function load_sample_data() {
        // Check if sample data is already loaded
        if ($this->is_sample_data_loaded()) {
            return true;
        }
        
        // Make sure tables exist
        $this->db_manager->create_tables();
        
        // Load basic quiz data
        $this->load_sql_file('quiz-data.sql');
        
        // Load enhanced quiz data
        $this->load_sql_file('enhanced-quiz-data.sql');
        
        // Mark sample data as loaded
        update_option('weebunz_sample_data_loaded', time());
        
        return true;
    }
    
    /**
     * Check if sample data is already loaded
     */
    public function is_sample_data_loaded() {
        return get_option('weebunz_sample_data_loaded', false) !== false;
    }
    
    /**
     * Clean up sample data
     */
    public function cleanup_sample_data() {
        // Load cleanup SQL
        $this->load_sql_file('cleanup.sql');
        
        // Remove the flag
        delete_option('weebunz_sample_data_loaded');
        
        return true;
    }
    
    /**
     * Load SQL file
     */
    private function load_sql_file($file) {
        $file_path = $this->test_data_dir . $file;
        
        if (!file_exists($file_path)) {
            error_log("WeeBunz: SQL file not found: $file_path");
            return false;
        }
        
        $sql = file_get_contents($file_path);
        
        // Replace prefix placeholder
        $sql = str_replace('{prefix}', $this->prefix, $sql);
        
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
                error_log("WeeBunz: SQL Error: " . $this->wpdb->last_error);
                error_log("WeeBunz: Failed SQL: " . $statement);
            }
        }
        
        return true;
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
}
