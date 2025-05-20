<?php
namespace Weebunz\Database;

if (!defined("ABSPATH")) {
    exit;
}

// Corrected use statement for Logger, assuming it's in Weebunz\Util namespace
use Weebunz\Util\Logger;

class DBManager { // Renamed class to match PSR-4 and filename
    private $wpdb;
    private $charset_collate;
    private $updates_dir;
    private $update_version_option = "weebunz_db_update_version";
    private $current_db_version = "1.1.1"; // Example, should be defined

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        // Assuming database updates are still in includes/database/updates for now
        // This might need to change if these are also moved into src/
        $this->updates_dir = WEEBUNZ_PLUGIN_DIR . "includes/database/updates/"; 
        // Logger::debug("DBManager initialized", ["charset" => $this->charset_collate]);
    }

    public function initialize() {
        try {
            // Logger::info("Starting database initialization");
            
            $this->create_tables();
            $this->process_updates();
            $this->initialize_quiz_types();
            $this->initialize_quiz_tags();
            $this->initialize_basic_settings();

            if (defined("WP_DEBUG") && WP_DEBUG) {
                $this->initialize_test_data();
            }

            // Logger::info("Database initialization completed successfully");
            return true;

        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "database_initialization"]);
            error_log("WeeBunz Quiz Engine DB Init Error: " . $e->getMessage()); // Fallback logging
            return false;
        }
    }

    
    public function create_tables() {
            // Logger::info("Creating base tables");
            require_once(ABSPATH . "wp-admin/includes/upgrade.php");
    
            // Corrected path (assuming 'Database' is directly under plugin root)
            $schema_file = WEEBUNZ_PLUGIN_DIR . "Database/schema.sql"; 

            // --- DEBUGGING START ---
            error_log("DBManager: Attempting to load schema from: " . $schema_file);
            if (!file_exists($schema_file)) {
                error_log("DBManager: CRITICAL - Schema file NOT FOUND at: " . $schema_file);
                // Optional: wp_die("Schema file not found at: " . esc_html($schema_file)); // Uncomment for hard stop during testing
                return; // or throw new \Exception(...)
            } else {
                error_log("DBManager: Schema file FOUND at: " . $schema_file);
            }
            // --- DEBUGGING END ---

            $schema_content = file_get_contents($schema_file);
            if (!$schema_content) {
                // Logger::error("Failed to read schema file", ["path" => $schema_file]);
                error_log("DBManager: CRITICAL - Failed to read schema file or file is empty: " . $schema_file); // Added error log
                throw new \Exception("Failed to read schema file or file is empty");
            }

            // --- DEBUGGING START ---
            error_log("DBManager: Content of schema file loaded (first 500 chars): " . substr($schema_content, 0, 500));
            // --- DEBUGGING END ---

            $schema_content = str_replace(
                array("{prefix}", "{charset_collate}"),
                array($this->wpdb->prefix, $this->charset_collate),
                $schema_content
            );

            $statements = array_filter(
                array_map("trim", explode(";", $schema_content)),
                "strlen"
            );

            foreach ($statements as $sql) {
                if (stripos($sql, "CREATE TABLE") !== false) {
                    // Logger::debug("Executing SQL for table creation", ["sql_start" => substr($sql, 0, 50)]);
                    dbDelta($sql); // dbDelta expects full CREATE TABLE statements

                    // --- DEBUGGING START ---
                    if (!empty($this->wpdb->last_error)) {
                        error_log("DBManager: dbDelta error for SQL (CREATE TABLE context): " . substr($sql, 0, 200) . " | Error: " . $this->wpdb->last_error);
                    } else {
                        error_log("DBManager: dbDelta processed SQL for (CREATE TABLE context): " . substr($sql, 0, 100));
                    }
                    // --- DEBUGGING END ---
                }
            }
            // Logger::info("Base tables creation process completed");
        }
    
    private function process_updates() {
        try {
            $current_version = get_option($this->update_version_option, "1.0.0");
            
            if (version_compare($current_version, $this->current_db_version, "<")) {
                // Logger::info("Processing database updates", [
                //     "from_version" => $current_version,
                //     "to_version" => $this->current_db_version
                // ]);
                
                $updates = $this->get_pending_updates($current_version);
                
                foreach ($updates as $update_file) {
                    if (!$this->run_update($update_file)) {
                        throw new \Exception("Failed to run update: {$update_file}");
                    }
                }
                
                update_option($this->update_version_option, $this->current_db_version);
                // Logger::info("All updates completed successfully");
            }
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "process_updates"]);
            error_log("WeeBunz Quiz Engine DB Update Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function get_pending_updates($current_version) {
        $updates = [];
        if (!is_dir($this->updates_dir)) {
            // Logger::warning("Updates directory not found, creating...", ["path" => $this->updates_dir]);
            wp_mkdir_p($this->updates_dir);
            return $updates;
        }

        $files = scandir($this->updates_dir);
        if ($files === false) {
            // Logger::error("Could not scan updates directory", ["path" => $this->updates_dir]);
            throw new \Exception("Could not scan updates directory");
        }

        foreach ($files as $file) {
            if ($file === "." || $file === "..") continue;
            $version = substr($file, 0, strpos($file, "-"));
            if (version_compare($version, $current_version, ">")) {
                $updates[] = $file;
            }
        }
        usort($updates, function($a, $b) {
            $version_a = substr($a, 0, strpos($a, "-"));
            $version_b = substr($b, 0, strpos($b, "-"));
            return version_compare($version_a, $version_b);
        });
        // Logger::debug("Found pending updates", ["count" => count($updates), "files" => $updates]);
        return $updates;
    }

    private function run_update($update_file) {
        // Logger::info("Running update", ["file" => $update_file]);
        $update_path = $this->updates_dir . $update_file;
        if (!file_exists($update_path)) {
            // Logger::error("Update file not found", ["path" => $update_path]);
            return false;
        }
        try {
            $sql_content = file_get_contents($update_path);
            if ($sql_content === false) {
                throw new \Exception("Could not read update file: {$update_file}");
            }
            $sql_content = str_replace(
                array("{prefix}", "{charset_collate}"),
                array($this->wpdb->prefix, $this->charset_collate),
                $sql_content
            );
            $statements = array_filter(array_map("trim", explode(";", $sql_content)), "strlen");

            $this->wpdb->query("START TRANSACTION");
            foreach ($statements as $statement) {
                $result = $this->wpdb->query($statement);
                if ($result === false && !preg_match("/Can't DROP|Unknown table|Duplicate|doesn't exist/i", $this->wpdb->last_error)) {
                    throw new \Exception("Error executing SQL: " . $this->wpdb->last_error . " IN STATEMENT: " . $statement);
                }
            }
            $this->wpdb->query("COMMIT");
            // Logger::info("Update completed successfully", ["file" => $update_file]);
            return true;
        } catch (\Exception $e) {
            $this->wpdb->query("ROLLBACK");
            // Logger::exception($e, ["context" => "run_update", "file" => $update_file]);
            error_log("WeeBunz Quiz Engine Update Run Error: " . $e->getMessage());
            throw $e;
        }
    }

    private function initialize_quiz_types() {
        // Logger::info("Initializing quiz types");
        $quiz_types = [
            ["name" => "Gift", "slug" => "gift", "entry_cost" => 2.50, "question_count" => 2, "max_entries" => 1, "answers_per_entry" => 2, "difficulty_level" => "easy", "time_limit" => 10, "is_member_only" => 0],
            ["name" => "Wee Buns", "slug" => "wee_buns", "entry_cost" => 5.00, "question_count" => 6, "max_entries" => 3, "answers_per_entry" => 2, "difficulty_level" => "medium", "time_limit" => 15, "is_member_only" => 0],
            ["name" => "Deadly", "slug" => "deadly", "entry_cost" => 3.00, "question_count" => 10, "max_entries" => 5, "answers_per_entry" => 2, "difficulty_level" => "hard", "time_limit" => 20, "is_member_only" => 0]
        ];
        $table = $this->wpdb->prefix . "quiz_types";
        foreach ($quiz_types as $type) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $type["slug"]));
            if (!$exists) {
                $this->wpdb->insert($table, $type);
            }
        }
    }

    private function initialize_quiz_tags() {
        // Logger::info("Initializing quiz tags");
        $tags = [
            ["name" => "Finishing Soon", "slug" => "finishing-soon", "type" => "status"],
            ["name" => "Discounted", "slug" => "discounted", "type" => "promotion"],
            ["name" => "New", "slug" => "new", "type" => "status"],
            ["name" => "Featured", "slug" => "featured", "type" => "promotion"]
        ];
        $table = $this->wpdb->prefix . "quiz_tags";
        foreach ($tags as $tag) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = %s", $tag["slug"]));
            if (!$exists) {
                $this->wpdb->insert($table, $tag);
            }
        }
    }

    private function initialize_basic_settings() {
        // Logger::info("Initializing basic settings");
        $settings = [
            "weebunz_weekly_spend_limit" => 50,
            "weebunz_platinum_monthly" => 25.50,
            "weebunz_platinum_quarterly" => 69.50,
            "weebunz_platinum_biannual" => 139.50,
            "weebunz_platinum_annual" => 289.50,
            "weebunz_mega_quiz_entry_fee" => 4.00,
            "weebunz_phone_answer_timeout" => 30,
            "weebunz_winner_question_timeout" => 30
        ];
        foreach ($settings as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    public function initialize_test_data() {
        try {
            // Logger::info("Initializing test data");
            // Assuming Data_Verifier is also moved to Weebunz\Database namespace
            // and its file is DataVerifier.php in src/Database/
            require_once WEEBUNZ_PLUGIN_DIR . "src/Database/DataVerifier.php"; 
            $verifier = new DataVerifier(); // Updated class name if it follows PSR-4
            $result = $verifier->insert_missing_data();
            
            // if ($result) {
            //     Logger::info("Test data initialized successfully");
            // } else {
            //     Logger::error("Failed to initialize test data");
            // }
            return $result;
        } catch (\Exception $e) {
            // Logger::exception($e, ["context" => "test_data_initialization"]);
            error_log("WeeBunz Quiz Engine Test Data Error: " . $e->getMessage());
            return false;
        }
    }
}

