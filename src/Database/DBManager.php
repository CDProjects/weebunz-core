<?php
// File: src/Database/DBManager.php

namespace Weebunz\Database;

if (!defined("ABSPATH")) {
    exit;
}

// No need to use Weebunz\Util\Logger unless you have a custom Logger class.
// WordPress's error_log() is sufficient for this level of debugging.
// If you do have a custom Logger: use Weebunz\Util\Logger;

class DBManager {
    private $wpdb;
    private $charset_collate;
    private $updates_dir;
    private $update_version_option = "weebunz_db_update_version";
    // Ensure this matches WEEBUNZ_VERSION in main plugin file for consistency
    private $current_db_version = "1.1.0"; 

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        
        // Path for DB update SQL files. Consider moving to src/Database/updates/
        $this->updates_dir = WEEBUNZ_PLUGIN_DIR . "includes/database/updates/"; 
        error_log("DBManager: __construct() called. Updates dir: " . $this->updates_dir);
    }

    public function initialize() {
        error_log("DBManager: initialize() called.");
        try {
            error_log("DBManager: Calling create_tables() from initialize().");
            $this->create_tables();

            error_log("DBManager: Calling process_updates() from initialize().");
            $this->process_updates();

            error_log("DBManager: Calling initialize_quiz_types() from initialize().");
            $this->initialize_quiz_types();

            error_log("DBManager: Calling initialize_quiz_tags() from initialize().");
            $this->initialize_quiz_tags();

            error_log("DBManager: Calling initialize_basic_settings() from initialize().");
            $this->initialize_basic_settings();

            if (defined("WP_DEBUG") && WP_DEBUG) {
                error_log("DBManager: WP_DEBUG is true, calling initialize_test_data() from initialize().");
                $this->initialize_test_data();
            }

            error_log("DBManager: initialize() completed successfully.");
            return true;

        } catch (\Exception $e) {
            error_log("DBManager: EXCEPTION in initialize(): " . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
            return false; // Indicate failure
        }
    }

    
    public function create_tables() {
        error_log("DBManager: create_tables() called.");
        require_once(ABSPATH . "wp-admin/includes/upgrade.php");

        // Path to schema.sql.
        // If DBManager.php is in src/Database/, and schema.sql is also in src/Database/:
        $schema_file = __DIR__ . "/schema.sql"; 
        // If schema.sql is in [plugin_root]/Database/schema.sql:
        // $schema_file = WEEBUNZ_PLUGIN_DIR . "Database/schema.sql";

        error_log("DBManager: Attempting to load schema from: " . $schema_file);
        if (!file_exists($schema_file)) {
            error_log("DBManager: CRITICAL - Schema file NOT FOUND at: " . $schema_file);
            throw new \Exception("Schema file not found at: " . $schema_file);
        } else {
            error_log("DBManager: Schema file FOUND at: " . $schema_file);
        }

        $schema_content = file_get_contents($schema_file);
        if (!$schema_content) {
            error_log("DBManager: CRITICAL - Failed to read schema file or file is empty: " . $schema_file);
            throw new \Exception("Failed to read schema file or file is empty: " . $schema_file);
        }

        error_log("DBManager: Content of schema file loaded (first 500 chars): " . substr($schema_content, 0, 500));

        $schema_content = str_replace(
            array("{prefix}", "{charset_collate}"),
            array($this->wpdb->prefix, $this->charset_collate),
            $schema_content
        );

        $statements = array_filter(
            array_map("trim", explode(";", $schema_content)),
            "strlen"
        );

        $dbDelta_had_errors = false;
        foreach ($statements as $sql) {
            if (stripos($sql, "CREATE TABLE") !== false) {
                error_log("DBManager: Preparing to run dbDelta for SQL (first 100 chars): " . substr($sql, 0, 100));
                // dbDelta can return an array of messages, successful or errors.
                $result_messages = dbDelta($sql); 

                if (!empty($this->wpdb->last_error)) {
                    error_log("DBManager: dbDelta error for SQL (CREATE TABLE context): " . substr($sql, 0, 200) . " | Error: " . $this->wpdb->last_error);
                    $dbDelta_had_errors = true;
                } else {
                    error_log("DBManager: dbDelta processed SQL (CREATE TABLE context): " . substr($sql, 0, 100) . " | Messages: " . implode(" | ", $result_messages ?: []));
                }
            }
        }
        if ($dbDelta_had_errors) {
            error_log("DBManager: One or more dbDelta errors occurred during table creation/update.");
        } else {
            error_log("DBManager: create_tables() statements processed.");
        }
    }
    
    private function process_updates() {
        error_log("DBManager: process_updates() called.");
        // ... (rest of the method, add logging within if needed) ...
        try {
            $current_plugin_db_version = get_option($this->update_version_option, "1.0.0"); // Version of DB schema on site
            
            // $this->current_db_version is the target DB version for *this* plugin code.
            if (version_compare($current_plugin_db_version, $this->current_db_version, "<")) {
                error_log("DBManager: Processing database updates from v{$current_plugin_db_version} to v{$this->current_db_version}");
                
                $updates = $this->get_pending_updates($current_plugin_db_version);
                if (empty($updates)) {
                    error_log("DBManager: No pending update files found.");
                }
                
                foreach ($updates as $update_file) {
                    if (!$this->run_update($update_file)) {
                        throw new \Exception("Failed to run update: {$update_file}");
                    }
                }
                
                update_option($this->update_version_option, $this->current_db_version);
                error_log("DBManager: Database updates completed. DB version option set to {$this->current_db_version}.");
            } else {
                error_log("DBManager: Database schema is up to date (v{$current_plugin_db_version}). No updates needed.");
            }
        } catch (\Exception $e) {
            error_log("DBManager: EXCEPTION in process_updates(): " . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            throw $e; // Re-throw to be caught by initialize()
        }
    }

    // ... (get_pending_updates, run_update methods - add internal logging if complex issues arise here) ...

    private function initialize_quiz_types() {
        error_log("DBManager: initialize_quiz_types() called.");
        // ... (rest of the method) ...
        // Add logging for inserts if needed:
        // if (!$exists) {
        //     $this->wpdb->insert($table, $type);
        //     error_log("DBManager: Inserted quiz type: " . $type['slug']);
        // }
    }

    private function initialize_quiz_tags() {
        error_log("DBManager: initialize_quiz_tags() called.");
        // ... (rest of the method) ...
    }

    private function initialize_basic_settings() {
        error_log("DBManager: initialize_basic_settings() called.");
        // ... (rest of the method) ...
    }

    public function initialize_test_data() {
        error_log("DBManager: initialize_test_data() called.");
        try {
            // REMOVE this require_once if DataVerifier is PSR-4 autoloaded from src/Database/
            
            // Use fully qualified namespace. Assumes DataVerifier.php is in src/Database/
            // and contains "namespace Weebunz\Database; class DataVerifier"
            error_log("DBManager: Attempting to instantiate \Weebunz\Database\DataVerifier");
            $verifier = new \Weebunz\Database\DataVerifier(); 
            $result = $verifier->insert_missing_data();
            
            if ($result) {
                error_log("DBManager: Test data initialized successfully via DataVerifier.");
            } else {
                error_log("DBManager: DataVerifier->insert_missing_data() returned false or error.");
            }
            return $result;
        } catch (\Exception $e) {
            error_log("DBManager: EXCEPTION in initialize_test_data(): " . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
            return false;
        }
    }
}