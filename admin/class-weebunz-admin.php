<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */
class WeeBunz_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/weebunz-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/weebunz-admin.js',
            array('jquery'),
            $this->version,
            false
        );
        wp_localize_script(
            $this->plugin_name,
            'weebunz_quiz_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('weebunz_quiz_nonce'),
            )
        );
    }

    public function add_admin_menu() {
        // Top‐level menu
        add_menu_page(
            __('WeeBunz Quiz Engine', 'weebunz-quiz-engine'),
            __('WeeBunz', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine',
            array($this, 'display_dashboard_page'),
            'dashicons-welcome-learn-more',
            30
        );

        // — Removed duplicate Dashboard submenu here —

        // Quizzes
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Quizzes', 'weebunz-quiz-engine'),
            __('Quizzes', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-quizzes',
            array($this, 'display_quizzes_page')
        );

        // Questions
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Questions', 'weebunz-quiz-engine'),
            __('Questions', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-questions',
            array($this, 'display_questions_page')
        );

        // Results
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Results', 'weebunz-quiz-engine'),
            __('Results', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-results',
            array($this, 'display_results_page')
        );

        // Raffles
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Raffles', 'weebunz-quiz-engine'),
            __('Raffles', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-raffle',
            array($this, 'display_raffle_page')
        );

        // Members
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Members', 'weebunz-quiz-engine'),
            __('Members', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-members',
            array($this, 'display_members_page')
        );

        // Quiz Test
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Quiz Test', 'weebunz-quiz-engine'),
            __('Quiz Test', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-quiz-test',
            array($this, 'display_quiz_test_page')
        );

        // Settings
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Settings', 'weebunz-quiz-engine'),
            __('Settings', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-settings',
            array($this, 'display_settings_page')
        );

        // Performance
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Performance', 'weebunz-quiz-engine'),
            __('Performance', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-performance',
            array($this, 'display_performance_page')
        );

        // Tools
        add_submenu_page(
            'weebunz-quiz-engine',
            __('Tools', 'weebunz-quiz-engine'),
            __('Tools', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-tools',
            array($this, 'display_tools_page')
        );

        // Load Testing (under Tools)
        add_submenu_page(
            'weebunz-quiz-engine-tools',
            __('Load Testing', 'weebunz-quiz-engine'),
            __('Load Testing', 'weebunz-quiz-engine'),
            'manage_options',
            'weebunz-quiz-engine-load-testing',
            array($this, 'display_load_testing_page')
        );
    }

    public function register_settings() {
        // … your existing register_setting() calls …
    }

    public function display_dashboard_page()    { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-dashboard.php'; }
    public function display_quizzes_page()      { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/quiz-management-page.php'; }
    public function display_questions_page()    { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/questions-page.php'; }
    public function display_results_page()      { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/results-page.php'; }
    public function display_raffle_page()       { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/manage-raffles-page.php'; }
    public function display_members_page()      { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/members-page.php'; }
    public function display_quiz_test_page()    { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/test-page.php'; }
    public function display_settings_page()     { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/settings-page.php'; }
    public function display_performance_page()  { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-performance.php'; }
    public function display_tools_page()        { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-tools.php'; }
    public function display_load_testing_page() { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-load-testing.php'; }
}
