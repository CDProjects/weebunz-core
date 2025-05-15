<?php
namespace Weebunz\Setup;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * WeeBunz Quiz Engine Activator
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 */
class Deactivator {

    /**
     * Create necessary database tables and initialize plugin settings
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;
        
        // Create database tables with proper indexes for high performance
        self::create_tables();
        
        // Initialize default settings
        self::initialize_settings();
        
        // Set version in database
        update_option('weebunz_quiz_engine_version', WEEBUNZ_QUIZ_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quiz table
        $table_quizzes = $wpdb->prefix . 'weebunz_quizzes';
        $sql_quizzes = "CREATE TABLE $table_quizzes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            difficulty varchar(50),
            time_limit int(11),
            status varchar(20) DEFAULT 'draft',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_difficulty (difficulty),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Questions table
        $table_questions = $wpdb->prefix . 'weebunz_questions';
        $sql_questions = "CREATE TABLE $table_questions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) NOT NULL,
            question_text text NOT NULL,
            question_type varchar(50) DEFAULT 'multiple_choice',
            difficulty varchar(50),
            points int(11) DEFAULT 1,
            order_num int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_difficulty (difficulty),
            KEY idx_order (order_num)
        ) $charset_collate;";
        
        // Answers table
        $table_answers = $wpdb->prefix . 'weebunz_answers';
        $sql_answers = "CREATE TABLE $table_answers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            question_id bigint(20) NOT NULL,
            answer_text text NOT NULL,
            is_correct tinyint(1) DEFAULT 0,
            order_num int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_question_id (question_id),
            KEY idx_is_correct (is_correct),
            KEY idx_order (order_num)
        ) $charset_collate;";
        
        // Quiz sessions table
        $table_sessions = $wpdb->prefix . 'weebunz_quiz_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime,
            score int(11),
            status varchar(20) DEFAULT 'in_progress',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_status (status),
            KEY idx_started_at (started_at)
        ) $charset_collate;";
        
        // User answers table
        $table_user_answers = $wpdb->prefix . 'weebunz_user_answers';
        $sql_user_answers = "CREATE TABLE $table_user_answers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            question_id bigint(20) NOT NULL,
            answer_id bigint(20),
            answer_text text,
            is_correct tinyint(1) DEFAULT 0,
            points_earned int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_session_id (session_id),
            KEY idx_question_id (question_id),
            KEY idx_answer_id (answer_id),
            KEY idx_is_correct (is_correct)
        ) $charset_collate;";
        
        // Raffle entries table
        $table_raffle_entries = $wpdb->prefix . 'weebunz_raffle_entries';
        $sql_raffle_entries = "CREATE TABLE $table_raffle_entries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            quiz_id bigint(20) NOT NULL,
            session_id bigint(20) NOT NULL,
            entry_count int(11) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_user_id (user_id),
            KEY idx_quiz_id (quiz_id),
            KEY idx_session_id (session_id),
            KEY idx_entry_count (entry_count)
        ) $charset_collate;";
        
        // Include WordPress database upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        dbDelta($sql_quizzes);
        dbDelta($sql_questions);
        dbDelta($sql_answers);
        dbDelta($sql_sessions);
        dbDelta($sql_user_answers);
        dbDelta($sql_raffle_entries);
    }
    
    /**
     * Initialize default settings
     */
    private static function initialize_settings() {
        // Default settings
        $default_settings = array(
            'quiz_time_limit' => 60, // Default time limit in seconds
            'points_per_question' => 1,
            'enable_raffle_entries' => 'yes',
            'entries_per_correct_answer' => 1,
            'enable_redis_cache' => 'yes',
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'redis_auth' => '',
            'redis_db' => 0,
            'session_expiry' => 3600, // 1 hour
            'rate_limit_enabled' => 'yes',
            'rate_limit_requests' => 100,
            'rate_limit_window' => 60, // 1 minute
            'concurrent_users_limit' => 1000,
            'db_connection_pool_size' => 50,
            'enable_background_processing' => 'yes',
            'log_level' => 'error', // error, warning, info, debug
        );
        
        // Save default settings
        foreach ($default_settings as $key => $value) {
            update_option('weebunz_quiz_' . $key, $value);
        }
    }
}
