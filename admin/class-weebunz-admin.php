<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */
class WeeBunz_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/weebunz-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/weebunz-admin.js', array('jquery'), $this->version, false);
        
        // Localize the script with data for AJAX calls
        wp_localize_script($this->plugin_name, 'weebunz_quiz_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('weebunz_quiz_nonce'),
        ));
    }

    /**
     * Add menu items to the admin dashboard
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('WeeBunz Quiz Engine', 'weebunz-quiz-engine'),
            __('WeeBunz', 'weebunz-quiz-engine'), // Changed label for brevity
            'manage_options',
            'weebunz-quiz-engine',
            array($this, 'display_dashboard_page'),
            'dashicons-welcome-learn-more',
            30
        );
        
        // Dashboard submenu (optional, can point to main page)
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Dashboard', 'weebunz-quiz-engine'),
            __('Dashboard', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine', // Use the main slug
            array($this, 'display_dashboard_page')
        );
        
        // Quizzes submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Quizzes', 'weebunz-quiz-engine'),
            __('Quizzes', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-quizzes',
            array($this, 'display_quizzes_page')
        );
        
        // Questions submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Questions', 'weebunz-quiz-engine'),
            __('Questions', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-questions',
            array($this, 'display_questions_page')
        );
        
        // Results submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Results', 'weebunz-quiz-engine'),
            __('Results', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-results',
            array($this, 'display_results_page')
        );
        
        // Raffle Entries submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Raffles', 'weebunz-quiz-engine'), // Changed label
            __('Raffles', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-raffle',
            array($this, 'display_raffle_page')
        );

        // Members submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Members', 'weebunz-quiz-engine'),
            __('Members', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-members',
            array($this, 'display_members_page') // Added this page
        );

        // Quiz Test submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Quiz Test', 'weebunz-quiz-engine'),
            __('Quiz Test', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-quiz-test',
            array($this, 'display_quiz_test_page') // Added this page
        );
        
        // Settings submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Settings', 'weebunz-quiz-engine'),
            __('Settings', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-settings',
            array($this, 'display_settings_page')
        );
        
        // Performance submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Performance', 'weebunz-quiz-engine'),
            __('Performance', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-performance',
            array($this, 'display_performance_page')
        );

        // Tools submenu
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Tools', 'weebunz-quiz-engine'),
            __('Tools', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-tools',
            array($this, 'display_tools_page') // Added this page
        );

        // Load Testing submenu (under Tools)
        add_submenu_page(
            'weebunz-quiz-engine-tools', // Parent slug is now Tools
            __('Load Testing', 'weebunz-quiz-engine'),
            __('Load Testing', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-load-testing',
            array($this, 'display_load_testing_page') // Added this page
        );
    }

    /**
     * Register settings for the plugin
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // General Settings
        register_setting('weebunz_quiz_general', 'weebunz_quiz_time_limit');
        register_setting('weebunz_quiz_general', 'weebunz_quiz_points_per_question');
        register_setting('weebunz_quiz_general', 'weebunz_quiz_enable_raffle_entries');
        register_setting('weebunz_quiz_general', 'weebunz_quiz_entries_per_correct_answer');
        
        // Performance Settings
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_enable_redis_cache');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_redis_host');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_redis_port');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_redis_auth');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_redis_db');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_session_expiry');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_rate_limit_enabled');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_rate_limit_requests');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_rate_limit_window');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_concurrent_users_limit');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_db_connection_pool_size');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_enable_background_processing');
        register_setting('weebunz_quiz_performance', 'weebunz_quiz_log_level');
    }

    /**
     * Display the dashboard page
     *
     * @since    1.0.0
     */
    public function display_dashboard_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-dashboard.php';
    }

    /**
     * Display the quizzes page
     *
     * @since    1.0.0
     */
    public function display_quizzes_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-quizzes.php';
    }

    /**
     * Display the questions page
     *
     * @since    1.0.0
     */
    public function display_questions_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-questions.php';
    }

    /**
     * Display the results page
     *
     * @since    1.0.0
     */
    public function display_results_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-results.php';
    }

    /**
     * Display the raffle page
     *
     * @since    1.0.0
     */
    public function display_raffle_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-raffle.php';
    }

    /**
     * Display the members page
     *
     * @since    1.0.0
     */
    public function display_members_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-members.php';
    }

    /**
     * Display the quiz test page
     *
     * @since    1.0.0
     */
    public function display_quiz_test_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-quiz-test.php';
    }

    /**
     * Display the settings page
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-settings.php';
    }

    /**
     * Display the performance page
     *
     * @since    1.0.0
     */
    public function display_performance_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-performance.php';
    }

    /**
     * Display the tools page
     *
     * @since    1.0.0
     */
    public function display_tools_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-tools.php';
    }

    /**
     * Display the load testing page
     *
     * @since    1.0.0
     */
    public function display_load_testing_page() {
        include_once WEEBUNZ_QUIZ_PLUGIN_DIR . 'admin/partials/weebunz-load-testing.php';
    }
}

