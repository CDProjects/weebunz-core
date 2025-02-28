<?php
// Location: wp-content/plugins/weebunz-core/includes/database/class-db-manager.php

namespace Weebunz\Database;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class DB_Manager {
    private $wpdb;
    private $charset_collate;
    private $updates_dir;
    private $update_version_option = 'weebunz_db_update_version';
    private $current_db_version = '1.1.1';

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->updates_dir = WEEBUNZ_PLUGIN_DIR . 'includes/database/updates/';
        Logger::debug('DB_Manager initialized', ['charset' => $this->charset_collate]);
    }

    public function initialize() {
        try {
            Logger::info('Starting database initialization');
            
            // Create base tables
            $this->create_tables();

            // Run any pending updates
            $this->process_updates();

            // Initialize core data
            $this->initialize_quiz_types();
            $this->initialize_quiz_tags();
            $this->initialize_basic_settings();

            // Initialize test data if in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->initialize_test_data();
            }

            Logger::info('Database initialization completed successfully');
            return true;

        } catch (\Exception $e) {
            Logger::exception($e, ['context' => 'database_initialization']);
            return false;
        }
    }

    
public function create_tables() {
    Logger::info('Creating base tables');
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    global $wpdb;

    // List of tables in correct order
    $tables = [
        'wp_quiz_types',
        'wp_quiz_tags',
        'wp_active_quizzes',
        'wp_questions_pool',
        'wp_question_answers',
        'wp_quiz_sessions',
        'wp_quiz_attempts',
        'wp_quiz_tag_relations',
        'wp_user_answers'
    ];

    // Load SQL schema
    $schema_file = WEEBUNZ_PLUGIN_DIR . 'includes/database/schema.sql';
    if (!file_exists($schema_file)) {
        Logger::error('Schema file not found', ['path' => $schema_file]);
        throw new \Exception('Schema file not found: ' . $schema_file);
    }

    $schema_content = file_get_contents($schema_file);
    if (!$schema_content) {
        Logger::error('Failed to read schema file', ['path' => $schema_file]);
        throw new \Exception('Failed to read schema file');
    }

    // Execute SQL for each table in the correct order
    foreach ($tables as $table) {
        if (strpos($schema_content, "CREATE TABLE IF NOT EXISTS `$table`") !== false) {
            $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` " . $schema_content);
            Logger::debug('Table created', ['table' => $table]);
        }
    }
}

        
        $schema_content = file_get_contents($schema_file);
        if ($schema_content === false) {
            Logger::error('Could not read schema file', ['path' => $schema_file]);
            throw new \Exception('Could not read schema file');
        }

        $schema_content = str_replace(
            ['{prefix}', '{charset_collate}'],
            [$this->wpdb->prefix, $this->charset_collate],
            $schema_content
        );

        $statements = array_filter(
            array_map('trim', explode(';', $schema_content)),
            'strlen'
        );

        foreach ($statements as $sql) {
            $result = dbDelta($sql);
            if (!empty($result)) {
                Logger::debug('Table operation result', ['result' => $result]);
            }
        }

        Logger::info('Base tables created successfully');
    }

    private function process_updates() {
        try {
            $current_version = get_option($this->update_version_option, '1.0.0');
            
            if (version_compare($current_version, $this->current_db_version, '<')) {
                Logger::info('Processing database updates', [
                    'from_version' => $current_version,
                    'to_version' => $this->current_db_version
                ]);
                
                $updates = $this->get_pending_updates($current_version);
                
                foreach ($updates as $update_file) {
                    if (!$this->run_update($update_file)) {
                        throw new \Exception("Failed to run update: {$update_file}");
                    }
                }
                
                update_option($this->update_version_option, $this->current_db_version);
                Logger::info('All updates completed successfully');
            }
        } catch (\Exception $e) {
            Logger::exception($e, ['context' => 'process_updates']);
            throw $e;
        }
    }

    private function get_pending_updates($current_version) {
        $updates = [];
        
        if (!is_dir($this->updates_dir)) {
            Logger::warning('Updates directory not found, creating...', [
                'path' => $this->updates_dir
            ]);
            wp_mkdir_p($this->updates_dir);
            return $updates;
        }

        $files = scandir($this->updates_dir);
        if ($files === false) {
            Logger::error('Could not scan updates directory', ['path' => $this->updates_dir]);
            throw new \Exception('Could not scan updates directory');
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            // Extract version from filename (e.g., "1.1.0-spending-log.sql")
            $version = substr($file, 0, strpos($file, '-'));
            if (version_compare($version, $current_version, '>')) {
                $updates[] = $file;
            }
        }

        // Sort updates by version number
        usort($updates, function($a, $b) {
            $version_a = substr($a, 0, strpos($a, '-'));
            $version_b = substr($b, 0, strpos($b, '-'));
            return version_compare($version_a, $version_b);
        });

        Logger::debug('Found pending updates', ['count' => count($updates), 'files' => $updates]);
        return $updates;
    }

        private function run_update($update_file) {
            Logger::info('Running update', ['file' => $update_file]);
    
            $update_path = $this->updates_dir . $update_file;
            if (!file_exists($update_path)) {
                Logger::error('Update file not found', ['path' => $update_path]);
                return false;
            }

            try {
                $sql = file_get_contents($update_path);
                if ($sql === false) {
                    throw new \Exception("Could not read update file: {$update_file}");
                }

                $sql = str_replace(
                    ['{prefix}', '{charset_collate}'],
                    [$this->wpdb->prefix, $this->charset_collate],
                    $sql
                );

                $delimiter = ';';
                $offset = 0;
                $statements = [];
        
                while (($pos = strpos($sql, $delimiter, $offset)) !== false) {
                    $length = $pos - $offset;
                    $statement = trim(substr($sql, $offset, $length));
                    if (!empty($statement)) {
                        $statements[] = $statement;
                    }
                    $offset = $pos + strlen($delimiter);
                }
        
                $final_statement = trim(substr($sql, $offset));
                if (!empty($final_statement)) {
                    $statements[] = $final_statement;
                }

                Logger::debug('Processing SQL statements', [
                    'file' => $update_file,
                    'count' => count($statements)
                ]);

                $this->wpdb->query('START TRANSACTION');

                foreach ($statements as $statement) {
                    $result = $this->wpdb->query($statement);
                    // Only treat as error if query failed and isn't about dropping non-existent items
                    if ($result === false && !preg_match("/Can't DROP|Unknown table|Duplicate|doesn't exist/i", $this->wpdb->last_error)) {
                        throw new \Exception("Error executing SQL: " . $this->wpdb->last_error);
                    }
                    Logger::debug('SQL statement executed', [
                        'result' => $result !== false ? 'success' : 'warning',
                        'error' => $result === false ? $this->wpdb->last_error : null
                    ]);
                }

                $this->wpdb->query('COMMIT');
                Logger::info('Update completed successfully', ['file' => $update_file]);
                return true;

            } catch (\Exception $e) {
                $this->wpdb->query('ROLLBACK');
                Logger::exception($e, ['context' => 'run_update', 'file' => $update_file]);
                throw $e;
            }
        }

    private function initialize_quiz_types() {
        Logger::info('Initializing quiz types');
        
        $quiz_types = [
            [
                'name' => 'Gift',
                'slug' => 'gift',
                'entry_cost' => 2.50,
                'question_count' => 2,
                'max_entries' => 1,
                'answers_per_entry' => 2,
                'difficulty_level' => 'easy',
                'time_limit' => 10,
                'is_member_only' => 0
            ],
            [
                'name' => 'Wee Buns',
                'slug' => 'wee_buns',
                'entry_cost' => 5.00,
                'question_count' => 6,
                'max_entries' => 3,
                'answers_per_entry' => 2,
                'difficulty_level' => 'medium',
                'time_limit' => 15,
                'is_member_only' => 0
            ],
            [
                'name' => 'Deadly',
                'slug' => 'deadly',
                'entry_cost' => 3.00,
                'question_count' => 10,
                'max_entries' => 5,
                'answers_per_entry' => 2,
                'difficulty_level' => 'hard',
                'time_limit' => 20,
                'is_member_only' => 0
            ]
        ];

        $table = $this->wpdb->prefix . 'quiz_types';
        foreach ($quiz_types as $type) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE slug = %s",
                $type['slug']
            ));

            if (!$exists) {
                $result = $this->wpdb->insert($table, $type);
                if ($result === false) {
                    Logger::error('Failed to insert quiz type', ['type' => $type]);
                } else {
                    Logger::debug('Added quiz type', ['name' => $type['name']]);
                }
            }
        }
    }

    private function initialize_quiz_tags() {
        Logger::info('Initializing quiz tags');
        
        $tags = [
            ['name' => 'Finishing Soon', 'slug' => 'finishing-soon', 'type' => 'status'],
            ['name' => 'Discounted', 'slug' => 'discounted', 'type' => 'promotion'],
            ['name' => 'New', 'slug' => 'new', 'type' => 'status'],
            ['name' => 'Featured', 'slug' => 'featured', 'type' => 'promotion']
        ];

        $table = $this->wpdb->prefix . 'quiz_tags';
        foreach ($tags as $tag) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE slug = %s",
                $tag['slug']
            ));

            if (!$exists) {
                $result = $this->wpdb->insert($table, $tag);
                if ($result === false) {
                    Logger::error('Failed to insert quiz tag', ['tag' => $tag]);
                } else {
                    Logger::debug('Added quiz tag', ['name' => $tag['name']]);
                }
            }
        }
    }

    private function initialize_basic_settings() {
        Logger::info('Initializing basic settings');
        
        $settings = [
            'weebunz_weekly_spend_limit' => 50,
            'weebunz_platinum_monthly' => 25.50,
            'weebunz_platinum_quarterly' => 69.50,
            'weebunz_platinum_biannual' => 139.50,
            'weebunz_platinum_annual' => 289.50,
            'weebunz_mega_quiz_entry_fee' => 4.00,
            'weebunz_phone_answer_timeout' => 30,
            'weebunz_winner_question_timeout' => 30
        ];

        foreach ($settings as $key => $value) {
            if (get_option($key) === false) {
                $result = add_option($key, $value);
                if ($result === false) {
                    Logger::error('Failed to add setting', ['key' => $key, 'value' => $value]);
                } else {
                    Logger::debug('Added setting', ['key' => $key, 'value' => $value]);
                }
            }
        }
    }

    public function initialize_test_data() {
        try {
            Logger::info('Initializing test data');
            
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/database/class-data-verifier.php';
            $verifier = new Data_Verifier();
            $result = $verifier->insert_missing_data();
            
            if ($result) {
                Logger::info('Test data initialized successfully');
            } else {
                Logger::error('Failed to initialize test data');
            }
            
            return $result;
        } catch (\Exception $e) {
            Logger::exception($e, ['context' => 'test_data_initialization']);
            return false;
        }
    }
}