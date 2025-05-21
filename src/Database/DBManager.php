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

    // In src/Database/DBManager.php

// ... (other methods like __construct, initialize, create_tables, process_updates are above this) ...

    private function initialize_quiz_types() {
        error_log("DBManager: initialize_quiz_types() called.");
        $table_name = $this->wpdb->prefix . "quiz_types";
        $inserted_count = 0;

        // Define the default quiz types to insert
        // Ensure all keys here match the column names in your wp_quiz_types table
        // and that the data types are compatible.
        $default_quiz_types = [
            [
                "name"               => "Gift", // From your QuizManager logs, this is quiz_id 1
                "slug"               => "gift",
                "description"        => "A simple gift quiz.",
                "difficulty_level"   => "easy",
                "time_limit"         => 10,
                "entry_cost"         => 2.50, // Ensure this matches schema: decimal(10,2)
                "max_entries"        => 1,    // Ensure this matches schema: int(11)
                "answers_per_entry"  => 2,    // Ensure this matches schema: int(11)
                "question_count"     => 2,    // Ensure this matches schema: int(11) or NULL
                "is_member_only"     => 0     // Ensure this matches schema: tinyint(1)
            ],
            [
                "name"               => "Wee Buns",
                "slug"               => "wee-buns", // Slugs should be unique
                "description"        => "A slightly more challenging quiz.",
                "difficulty_level"   => "medium",
                "time_limit"         => 15,
                "entry_cost"         => 5.00,
                "max_entries"        => 3,
                "answers_per_entry"  => 2,
                "question_count"     => 6,
                "is_member_only"     => 0
            ],
            [
                "name"               => "Deadly",
                "slug"               => "deadly", // Slugs should be unique
                "description"        => "A difficult quiz for experts.",
                "difficulty_level"   => "hard",
                "time_limit"         => 20,
                "entry_cost"         => 3.00,
                "max_entries"        => 5,
                "answers_per_entry"  => 2,
                "question_count"     => 10,
                "is_member_only"     => 0
            ]
        ];

        // Define data types for $wpdb->insert
        $data_formats = [
            '%s', // name
            '%s', // slug
            '%s', // description
            '%s', // difficulty_level
            '%d', // time_limit
            '%f', // entry_cost (float/decimal)
            '%d', // max_entries
            '%d', // answers_per_entry
            '%d', // question_count (or null if not provided, adjust format if null)
            '%d'  // is_member_only (tinyint stored as int)
        ];


        foreach ($default_quiz_types as $type_data) {
            // Check if a quiz type with this slug already exists
            $slug_exists_query = $this->wpdb->prepare("SELECT id FROM `{$table_name}` WHERE slug = %s", $type_data["slug"]);
            $existing_id = $this->wpdb->get_var($slug_exists_query);

            if ($this->wpdb->last_error) {
                 error_log("DBManager: Error checking for existing quiz type slug '{$type_data['slug']}': " . $this->wpdb->last_error);
                 continue; // Skip this type if there's an error checking
            }

            error_log("DBManager: Checking if quiz type slug '{$type_data['slug']}' exists. Found ID: " . ($existing_id ?: 'No'));

            if (!$existing_id) {
                error_log("DBManager: Attempting to insert quiz type: " . print_r($type_data, true));
                
                // Ensure all keys in $type_data exist as columns and formats match
                $insert_data = [];
                $current_formats = [];

                // Map data to ensure correct order for formats if not all columns are always present
                // For simplicity, assuming all defined columns in $default_quiz_types are always present
                // and match the order of $data_formats.
                // A more robust way would be to dynamically build format array based on keys in $type_data
                // or ensure $type_data always has all keys corresponding to $data_formats.

                // Simple check for required keys (name and slug are usually essential)
                if (empty($type_data['name']) || empty($type_data['slug'])) {
                    error_log("DBManager: Skipping insertion for quiz type due to missing name or slug: " . print_r($type_data, true));
                    continue;
                }
                
                // Directly use $type_data if it matches the column structure and $data_formats order
                $result = $this->wpdb->insert($table_name, $type_data, $data_formats);

                if ($result === false) {
                    error_log("DBManager: FAILED to insert quiz type '{$type_data['slug']}'. DB Error: " . $this->wpdb->last_error . " | Data: " . print_r($type_data, true));
                } else {
                    error_log("DBManager: Successfully inserted quiz type '{$type_data['slug']}'. Insert ID: " . $this->wpdb->insert_id);
                    $inserted_count++;
                }
            } else {
                error_log("DBManager: Quiz type slug '{$type_data['slug']}' (ID: {$existing_id}) already exists. Skipping insertion.");
            }
        }
        error_log("DBManager: initialize_quiz_types() completed. Inserted {$inserted_count} new types into {$table_name}.");
    }

    private function initialize_quiz_tags() {
        error_log("DBManager: initialize_quiz_tags() called.");
        $table_name = $this->wpdb->prefix . "quiz_tags";
        $inserted_count = 0;

        $default_tags = [
            ["name" => "Finishing Soon", "slug" => "finishing-soon", "type" => "status"],
            ["name" => "Discounted", "slug" => "discounted", "type" => "promotion"],
            ["name" => "New", "slug" => "new", "type" => "status"],
            ["name" => "Featured", "slug" => "featured", "type" => "promotion"]
        ];

        $data_formats = ['%s', '%s', '%s']; // name, slug, type

        foreach ($default_tags as $tag_data) {
            $slug_exists_query = $this->wpdb->prepare("SELECT id FROM `{$table_name}` WHERE slug = %s", $tag_data["slug"]);
            $existing_id = $this->wpdb->get_var($slug_exists_query);

            if ($this->wpdb->last_error) {
                 error_log("DBManager: Error checking for existing quiz tag slug '{$tag_data['slug']}': " . $this->wpdb->last_error);
                 continue;
            }
            error_log("DBManager: Checking if quiz tag slug '{$tag_data['slug']}' exists. Found ID: " . ($existing_id ?: 'No'));

            if (!$existing_id) {
                error_log("DBManager: Attempting to insert quiz tag: " . print_r($tag_data, true));
                $result = $this->wpdb->insert($table_name, $tag_data, $data_formats);

                if ($result === false) {
                    error_log("DBManager: FAILED to insert quiz tag '{$tag_data['slug']}'. DB Error: " . $this->wpdb->last_error);
                } else {
                    error_log("DBManager: Successfully inserted quiz tag '{$tag_data['slug']}'. Insert ID: " . $this->wpdb->insert_id);
                    $inserted_count++;
                }
            } else {
                error_log("DBManager: Quiz tag slug '{$tag_data['slug']}' (ID: {$existing_id}) already exists. Skipping insertion.");
            }
        }
        error_log("DBManager: initialize_quiz_tags() completed. Inserted {$inserted_count} new tags into {$table_name}.");
    }

    private function initialize_basic_settings() {
        error_log("DBManager: initialize_basic_settings() called.");
        $settings_inserted_count = 0;
        $default_settings = [
            "weebunz_weekly_spend_limit"       => 50,
            "weebunz_platinum_monthly_price"   => 25.50,  // Renamed for clarity if it's a price
            "weebunz_platinum_quarterly_price" => 69.50,
            "weebunz_platinum_biannual_price"  => 139.50,
            "weebunz_platinum_annual_price"    => 289.50,
            "weebunz_mega_quiz_entry_fee"      => 4.00,
            "weebunz_phone_answer_timeout_seconds" => 30, // Added units for clarity
            "weebunz_winner_question_timeout_seconds" => 30  // Added units for clarity
        ];

        foreach ($default_settings as $option_key => $option_value) {
            if (get_option($option_key) === false) { // Check if option does not exist
                if (add_option($option_key, $option_value)) {
                    error_log("DBManager: Added basic setting option '{$option_key}' with value '{$option_value}'.");
                    $settings_inserted_count++;
                } else {
                    error_log("DBManager: FAILED to add basic setting option '{$option_key}'.");
                }
            } else {
                error_log("DBManager: Basic setting option '{$option_key}' already exists. Skipping.");
            }
        }
        error_log("DBManager: initialize_basic_settings() completed. Added {$settings_inserted_count} new options.");
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