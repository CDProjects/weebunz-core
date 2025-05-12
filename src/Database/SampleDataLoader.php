<?php
/**
 * Sample Quiz Data Loader for WeeBunz Quiz Engine
 *
 * This file loads sample quiz data for demonstration purposes
 */

namespace Weebunz\Database;

if (!defined("ABSPATH")) {
    exit;
}

use Weebunz\Util\Logger; // Assuming Logger class is available
use Weebunz\Database\DBManager;

/**
 * SampleDataLoader
 * 
 * Loads sample quiz data for demonstration purposes
 */
class SampleDataLoader {
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
        // Corrected path for test-data directory, assuming it remains in includes/database/
        $this->test_data_dir = WEEBUNZ_PLUGIN_DIR . "includes/database/test-data/";
        
        // Instantiate DBManager directly, assuming autoloading or prior include
        $this->db_manager = new DBManager();
    }
    
    /**
     * Load sample data
     */
    public function load_sample_data() {
        if ($this->is_sample_data_loaded()) {
            Logger::info("Sample data already loaded.");
            return true;
        }
        
        Logger::info("Attempting to load sample data.");
        $this->db_manager->create_tables(); // Ensure tables exist
        
        $this->load_sql_file("quiz-data.sql");
        $this->load_sql_file("enhanced-quiz-data.sql");
        
        update_option("weebunz_sample_data_loaded", time());
        Logger::info("Sample data loading process completed.");
        return true;
    }
    
    /**
     * Check if sample data is already loaded
     */
    public function is_sample_data_loaded() {
        return get_option("weebunz_sample_data_loaded", false) !== false;
    }
    
    /**
     * Clean up sample data
     */
    public function cleanup_sample_data() {
        Logger::info("Attempting to clean up sample data.");
        // Ensure cleanup.sql exists and is safe to run
        // $this->load_sql_file("cleanup.sql"); 
        // For safety, cleanup logic should be more explicit, e.g., truncating specific tables
        // For now, will just delete the option
        delete_option("weebunz_sample_data_loaded");
        Logger::info("Sample data cleanup process completed (option deleted).");
        return true;
    }
    
    /**
     * Load SQL file
     */
    private function load_sql_file($file) {
        $file_path = $this->test_data_dir . $file;
        
        if (!file_exists($file_path)) {
            Logger::error("WeeBunz: SQL file not found for sample data.", ["path" => $file_path]);
            return false;
        }
        
        $sql = file_get_contents($file_path);
        if ($sql === false) {
            Logger::error("WeeBunz: Could not read SQL file for sample data.", ["path" => $file_path]);
            return false;
        }
        
        $sql = str_replace("{prefix}", $this->prefix, $sql);
        
        $statements = $this->split_sql($sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            // Note: $wpdb->query() is used here. For DDL/DML from trusted SQL files, this is common.
            // If these files could ever contain user-derived data, $wpdb->prepare() would be essential for parts of it.
            $result = $this->wpdb->query($statement);
            if ($result === false) {
                Logger::error("WeeBunz: SQL Error during sample data load.", [
                    "error" => $this->wpdb->last_error,
                    "sql" => substr($statement, 0, 200) // Log a snippet of the failed SQL
                ]);
            }
        }
        Logger::info("Successfully processed SQL file.", ["file" => $file]);
        return true;
    }
    
    /**
     * Split SQL into individual statements
     */
    private function split_sql($sql) {
        $sql = str_replace("\r", "", $sql);
        $statements = [];
        $current_statement = "";
        
        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $trimmed_line = trim($line);
            if (empty($trimmed_line) || strpos($trimmed_line, "--") === 0) { // Skip comments and empty lines
                continue;
            }
            
            $current_statement .= $line . "\n";
            
            if (substr($trimmed_line, -1) === ";") { // End of a statement
                $statements[] = trim($current_statement);
                $current_statement = "";
            }
        }
        
        if (!empty(trim($current_statement))) { // Add any remaining statement
            $statements[] = trim($current_statement);
        }
        
        return $statements;
    }
}

