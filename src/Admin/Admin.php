<?php
namespace Weebunz\Admin; // Updated namespace

// Ensure this class can find the Logger if it's in a different namespace
use Weebunz\Util\Logger;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */
class Admin { // Changed class name to match filename for PSR-4

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
            wp_enqueue_style(
                $this->plugin_name . '-admin',
                \WEEBUNZ_PLUGIN_URL . 'admin/css/weebunz-admin.css',
                [],               // no dependencies
                $this->version,   // plugin version constant
                'all'             // media
            );
        }
        
    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name . "-admin", WEEBUNZ_PLUGIN_URL . "admin/js/weebunz-admin.js", array("jquery"), $this->version, false);
        wp_enqueue_script($this->plugin_name . "-admin-quiz-components", WEEBUNZ_PLUGIN_URL . "admin/js/quiz-components.admin.js", array("jquery"), $this->version, false);
        wp_enqueue_script($this->plugin_name . '-admin-quiz-test', WEEBUNZ_PLUGIN_URL . 'admin/js/quiz-test.admin.js', ['jquery'], $this->version, true);
        wp_localize_script($this->plugin_name . "-admin", "weebunz_admin_params", array(
            "ajax_url" => admin_url("admin-ajax.php"),
            "nonce" => wp_create_nonce("weebunz_admin_ajax_nonce"),
            "text_domain" => "weebunz-quiz-engine",
            "error_generic" => esc_html__("An error occurred. Please try again.", "weebunz-quiz-engine") // Generic error message
        ));
    }

    /**
     * Add menu items to the admin dashboard
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            __("WeeBunz Quiz Engine", "weebunz-quiz-engine"),
            __("WeeBunz", "weebunz-quiz-engine"), 
            "manage_options",
            "weebunz-quiz-engine",
            array($this, "display_dashboard_page"),
            "dashicons-welcome-learn-more",
            30
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Dashboard", "weebunz-quiz-engine"),
            __("Dashboard", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine",
            array($this, "display_dashboard_page")
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Quizzes", "weebunz-quiz-engine"),
            __("Quizzes", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-quizzes",
            array($this, "display_quizzes_page")
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Questions", "weebunz-quiz-engine"),
            __("Questions", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-questions",
            array($this, "display_questions_page")
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Results", "weebunz-quiz-engine"),
            __("Results", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-results",
            array($this, "display_results_page")
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Raffles", "weebunz-quiz-engine"),
            __("Raffles", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-raffle",
            array($this, "display_raffle_page")
        );

        add_submenu_page(
            "weebunz-quiz-engine",
            __("Members", "weebunz-quiz-engine"),
            __("Members", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-members",
            array($this, "display_members_page")
        );

        add_submenu_page(
            "weebunz-quiz-engine",
            __("Quiz Test", "weebunz-quiz-engine"),
            __("Quiz Test", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-quiz-test",
            array($this, "display_quiz_test_page")
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Settings", "weebunz-quiz-engine"),
            __("Settings", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-settings",
            array($this, "display_settings_page")
        );
        
        add_submenu_page(
            "weebunz-quiz-engine",
            __("Performance", "weebunz-quiz-engine"),
            __("Performance", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-performance",
            array($this, "display_performance_page")
        );

        add_submenu_page(
            "weebunz-quiz-engine",
            __("Tools", "weebunz-quiz-engine"),
            __("Tools", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-tools",
            array($this, "display_tools_page")
        );

        add_submenu_page(
            "weebunz-quiz-engine-tools",
            __("Load Testing", "weebunz-quiz-engine"),
            __("Load Testing", "weebunz-quiz-engine"),
            "manage_options",
            "weebunz-quiz-engine-load-testing",
            array($this, "display_load_testing_page")
        );
    }

    /**
     * Register settings for the plugin
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // General Settings Section
        add_settings_section(
            "weebunz_quiz_engine_general_section",
            __("General Quiz Settings", "weebunz-quiz-engine"),
            null, 
            "weebunz-quiz-engine-settings" // Changed
        );

        register_setting("weebunz_quiz_engine_general_options_group", "weebunz_quiz_engine_time_limit", array("sanitize_callback" => "absint"));
        register_setting("weebunz_quiz_engine_general_options_group", "weebunz_quiz_engine_session_expiry", array("sanitize_callback" => "absint"));

        add_settings_field(
            "weebunz_quiz_engine_time_limit",
            __("Quiz Time Limit (seconds)", "weebunz-quiz-engine"),
            array($this, "render_time_limit_field"),
            "weebunz-quiz-engine-settings", // Changed
            "weebunz_quiz_engine_general_section"
        );
        add_settings_field(
            "weebunz_quiz_engine_session_expiry",
            __("Quiz Session Expiry (seconds)", "weebunz-quiz-engine"),
            array($this, "render_session_expiry_field"),
            "weebunz-quiz-engine-settings", // Changed
            "weebunz_quiz_engine_general_section"
        );

        // Performance Settings Section
        add_settings_section(
            "weebunz_quiz_engine_performance_section",
            __("Performance Settings", "weebunz-quiz-engine"),
            null,
            "weebunz-quiz-engine-performance" // Corrected page slug
        );
        register_setting("weebunz_quiz_engine_performance_options_group", "weebunz_quiz_engine_enable_redis_cache", array("sanitize_callback" => "rest_sanitize_boolean"));
        add_settings_field(
            "weebunz_quiz_engine_enable_redis_cache",
            __("Enable Redis Cache", "weebunz-quiz-engine"),
            array($this, "render_enable_redis_cache_field"),
            "weebunz-quiz-engine-performance", // Corrected page slug
            "weebunz_quiz_engine_performance_section"
        );
    }
    
    public function render_time_limit_field() {
        $option = get_option("weebunz_quiz_engine_time_limit", 300);
        echo ": <input type=\"number\" id=\"weebunz_quiz_engine_time_limit\" name=\"weebunz_quiz_engine_time_limit\" value=\"" . esc_attr($option) . "\" />";
    }

    public function render_session_expiry_field() {
        $option = get_option("weebunz_quiz_engine_session_expiry", 3600);
        echo ": <input type=\"number\" id=\"weebunz_quiz_engine_session_expiry\" name=\"weebunz_quiz_engine_session_expiry\" value=\"" . esc_attr($option) . "\" />";
    }

    public function render_enable_redis_cache_field() {
        $option = get_option("weebunz_quiz_engine_enable_redis_cache", false);
        echo ": <input type=\"checkbox\" id=\"weebunz_quiz_engine_enable_redis_cache\" name=\"weebunz_quiz_engine_enable_redis_cache\" value=\"1\" " . checked(1, $option, false) . " />";
    }

    // Callback functions for displaying admin pages (partials)
    private function display_admin_partial($partial_name) {
        $file_path = WEEBUNZ_PLUGIN_DIR . 'src/Admin/Partials/' . $partial_name . '.php';
        if (file_exists($file_path)) {
            include_once $file_path;
        } else {
            echo "<div class=\"wrap\"><p>" . esc_html__(ucfirst(str_replace("-", " ", $partial_name)) . " partial not found.", "weebunz-quiz-engine") . "</p></div>";
        }
    }

    public function display_dashboard_page()    { $this->display_admin_partial( 'weebunz-admin-dashboard' ); }
    public function display_quizzes_page()      { $this->display_admin_partial( 'quiz-management-page' ); }
    public function display_questions_page()    { $this->display_admin_partial( 'questions-page' ); }
    public function display_results_page()      { $this->display_admin_partial( 'results-page' ); }
    public function display_raffle_page()       { $this->display_admin_partial( 'manage-raffles-page' ); }
    public function display_members_page()      { $this->display_admin_partial( 'members-page' ); }
    public function display_quiz_test_page()    { $this->display_admin_partial( 'quiz-test-page' ); }
    public function display_settings_page()     { $this->display_admin_partial( 'settings-page' ); }
    public function display_performance_page()  { $this->display_admin_partial( 'maintenance-page' ); }
    public function display_tools_page()        { $this->display_admin_partial( 'weebunz-admin-tools' ); }
    public function display_load_testing_page() { $this->display_admin_partial( 'weebunz-load-testing' ); }

    
    /**
     * Example AJAX handler for an admin action.
     * Hooked in Core/Loader.php: add_action("wp_ajax_weebunz_admin_some_action", array($admin_instance, "handle_ajax_admin_action"));
     */
    public function handle_ajax_admin_action() {
        // 1. Verify nonce
        check_ajax_referer("weebunz_admin_ajax_nonce", "security"); // Matches nonce in enqueue_scripts

        // 2. Check user capabilities
        if (!current_user_can("manage_options")) {
            wp_send_json_error(
                array("message" => esc_html__("You do not have sufficient permissions to perform this action.", "weebunz-quiz-engine")),
                403 // Forbidden
            );
            return; // Or wp_die();
        }

        // 3. Sanitize input data (example)
        $some_data = isset($_POST["some_data"]) ? sanitize_text_field($_POST["some_data"]) : null;

        if (empty($some_data)) {
            wp_send_json_error(array("message" => esc_html__("Required data is missing.", "weebunz-quiz-engine")));
            return;
        }

        // ... (Process the data, interact with database using $wpdb->prepare(), etc.) ...
        // Example: Logger::log("Admin AJAX action performed with data: " . $some_data);

        // 4. Send JSON response
        wp_send_json_success(array(
            "message" => esc_html__("Action completed successfully! Data: ", "weebunz-quiz-engine") . esc_html($some_data)
        ));
    }
}

