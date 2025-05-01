<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 */
class WeeBunz_Public {

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
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/weebunz-public.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/weebunz-public.js', array('jquery'), $this->version, false);
        
        // Localize the script with data for AJAX calls
        wp_localize_script($this->plugin_name, 'weebunz_quiz', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('weebunz_quiz_nonce'),
            'quiz_time_limit' => get_option('weebunz_quiz_time_limit', 60),
            'timer_warning_threshold' => 10, // Show warning when 10 seconds remain
        ));
    }

    /**
     * Register shortcodes
     *
     * @since    1.0.0
     */
    public function register_shortcodes() {
        add_shortcode('weebunz_quiz', array($this, 'quiz_shortcode'));
        add_shortcode('weebunz_quiz_list', array($this, 'quiz_list_shortcode'));
        add_shortcode('weebunz_user_results', array($this, 'user_results_shortcode'));
        add_shortcode('weebunz_raffle_entries', array($this, 'raffle_entries_shortcode'));
    }

    /**
     * Shortcode to display a quiz
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string             HTML output.
     */
    public function quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts, 'weebunz_quiz');
        
        $quiz_id = intval($atts['id']);
        
        if ($quiz_id <= 0) {
            return '<p>' . __('Invalid quiz ID.', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to take this quiz.', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Get quiz data
        global $wpdb;
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}weebunz_quizzes WHERE id = %d AND status = 'published'",
            $quiz_id
        ));
        
        if (!$quiz) {
            return '<p>' . __('Quiz not found or not published.', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Check if user has an active session for this quiz
        $user_id = get_current_user_id();
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}weebunz_quiz_sessions 
            WHERE user_id = %d AND quiz_id = %d AND status = 'in_progress'
            ORDER BY id DESC LIMIT 1",
            $user_id,
            $quiz_id
        ));
        
        // If no active session, create one
        if (!$session) {
            $wpdb->insert(
                $wpdb->prefix . 'weebunz_quiz_sessions',
                array(
                    'user_id' => $user_id,
                    'quiz_id' => $quiz_id,
                    'started_at' => current_time('mysql'),
                    'status' => 'in_progress',
                )
            );
            
            $session_id = $wpdb->insert_id;
        } else {
            $session_id = $session->id;
        }
        
        // Get questions for this quiz
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}weebunz_questions 
            WHERE quiz_id = %d 
            ORDER BY order_num ASC, id ASC",
            $quiz_id
        ));
        
        if (empty($questions)) {
            return '<p>' . __('No questions found for this quiz.', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Start output buffer
        ob_start();
        
        // Include the quiz template
        include WEEBUNZ_PLUGIN_DIR . 'public/partials/weebunz-quiz-display.php';
        
        return ob_get_clean();
    }

    /**
     * Shortcode to display a list of available quizzes
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string             HTML output.
     */
    public function quiz_list_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
            'difficulty' => '',
        ), $atts, 'weebunz_quiz_list');
        
        $limit = intval($atts['limit']);
        $difficulty = sanitize_text_field($atts['difficulty']);
        
        // Get quizzes
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}weebunz_quizzes WHERE status = 'published'";
        
        if (!empty($difficulty)) {
            $query .= $wpdb->prepare(" AND difficulty = %s", $difficulty);
        }
        
        $query .= " ORDER BY id DESC LIMIT %d";
        $quizzes = $wpdb->get_results($wpdb->prepare($query, $limit));
        
        if (empty($quizzes)) {
            return '<p>' . __('No quizzes available.', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Start output buffer
        ob_start();
        
        // Include the quiz list template
        include WEEBUNZ_PLUGIN_DIR . 'public/partials/weebunz-quiz-list.php';
        
        return ob_get_clean();
    }

    /**
     * Shortcode to display user's quiz results
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string             HTML output.
     */
    public function user_results_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 10,
        ), $atts, 'weebunz_user_results');
        
        $limit = intval($atts['limit']);
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your results.', 'weebunz-quiz-engine') . '</p>';
        }
        
        $user_id = get_current_user_id();
        
        // Get user's completed quiz sessions
        global $wpdb;
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, q.title as quiz_title 
            FROM {$wpdb->prefix}weebunz_quiz_sessions s
            JOIN {$wpdb->prefix}weebunz_quizzes q ON s.quiz_id = q.id
            WHERE s.user_id = %d AND s.status = 'completed'
            ORDER BY s.completed_at DESC
            LIMIT %d",
            $user_id,
            $limit
        ));
        
        if (empty($sessions)) {
            return '<p>' . __('You have not completed any quizzes yet.', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Start output buffer
        ob_start();
        
        // Include the results template
        include WEEBUNZ_PLUGIN_DIR . 'public/partials/weebunz-user-results.php';
        
        return ob_get_clean();
    }

    /**
     * Shortcode to display user's raffle entries
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string             HTML output.
     */
    public function raffle_entries_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts, 'weebunz_raffle_entries');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your raffle entries.', 'weebunz-quiz-engine') . '</p>';
        }
        
        $user_id = get_current_user_id();
        
        // Get user's raffle entries
        global $wpdb;
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, q.title as quiz_title 
            FROM {$wpdb->prefix}weebunz_raffle_entries r
            JOIN {$wpdb->prefix}weebunz_quizzes q ON r.quiz_id = q.id
            WHERE r.user_id = %d
            ORDER BY r.created_at DESC",
            $user_id
        ));
        
        // Get total entries
        $total_entries = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(entry_count) 
            FROM {$wpdb->prefix}weebunz_raffle_entries 
            WHERE user_id = %d",
            $user_id
        ));
        
        if (empty($entries)) {
            return '<p>' . __('You have no raffle entries yet. Complete quizzes to earn entries!', 'weebunz-quiz-engine') . '</p>';
        }
        
        // Start output buffer
        ob_start();
        
        // Include the raffle entries template
        include WEEBUNZ_PLUGIN_DIR . 'public/partials/weebunz-raffle-entries.php';
        
        return ob_get_clean();
    }
}
