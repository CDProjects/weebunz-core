<?php
namespace Weebunz\Public;

use Weebunz\Util\Logger;

if ( ! defined( "ABSPATH" ) ) exit;

class Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name . "-public", WEEBUNZ_PLUGIN_URL . "public/css/weebunz-public.css", array(), $this->version, "all");
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name . "-public", WEEBUNZ_PLUGIN_URL . "public/js/weebunz-public.js", array("jquery"), $this->version, true); // Changed to true for footer loading
        wp_enqueue_script($this->plugin_name . "-public-quiz-components", WEEBUNZ_PLUGIN_URL . "public/js/quiz-components.public.js", array("jquery"), $this->version, true); // Changed to true

        wp_localize_script($this->plugin_name . "-public", "weebunz_public_params", array(
            "ajax_url" => admin_url("admin-ajax.php"),
            "rest_url" => esc_url_raw(rest_url("weebunz/v1/")), // Added namespace to rest_url
            "nonce" => wp_create_nonce("wp_rest"),
            "public_ajax_nonce" => wp_create_nonce("weebunz_public_ajax_nonce"),
            "quiz_time_limit" => get_option("weebunz_quiz_engine_time_limit", 60),
            "timer_warning_threshold" => 10, 
            "text_domain" => "weebunz-quiz-engine", // Consistent text domain
            "error_generic" => esc_html__("An error occurred. Please try again.", "weebunz-quiz-engine")
        ));
    }

    public function register_shortcodes() {
        add_shortcode("weebunz_quiz", array($this, "render_quiz_shortcode"));
        add_shortcode("weebunz_quiz_list", array($this, "render_quiz_list_shortcode"));
        add_shortcode("weebunz_user_results", array($this, "render_user_results_shortcode"));
        add_shortcode("weebunz_raffle_entries", array($this, "render_raffle_entries_shortcode"));
    }

    public function render_quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            "id" => 0,
        ), $atts, "weebunz_quiz");
        
        $quiz_id = intval($atts["id"]);
        
        if ($quiz_id <= 0) {
            return "<p>" . esc_html__("Invalid quiz ID.", "weebunz-quiz-engine") . "</p>";
        }
        
        if (!is_user_logged_in()) {
            return "<p>" . sprintf(
                wp_kses(
                    __("Please <a href=\"%s\">log in</a> to take this quiz.", "weebunz-quiz-engine"),
                    array( "a" => array( "href" => array() ) )
                ),
                esc_url(wp_login_url(get_permalink()))
            ) . "</p>";
        }
        
        global $wpdb;
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}active_quizzes WHERE id = %d AND status = %s",
            $quiz_id,
            "active"
        ));
        
        if (!$quiz) {
            return "<p>" . esc_html__("Quiz not found or not active.", "weebunz-quiz-engine") . "</p>";
        }
        
        $user_id = get_current_user_id();
        // Check for existing active session or create a new one
        $session_uuid = $this->get_or_create_quiz_session($user_id, $quiz_id);

        if (!$session_uuid) {
            return "<p>" . esc_html__("Could not start or resume quiz session.", "weebunz-quiz-engine") . "</p>";
        }
        
        // Fetch questions - consider randomizing and limiting within the session creation logic if needed
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT qp.* FROM {$wpdb->prefix}questions_pool qp ORDER BY RAND() LIMIT %d", // Example: limit to 20 questions
            20 
        ));
        
        if (empty($questions)) {
            return "<p>" . esc_html__("No questions found for this quiz.", "weebunz-quiz-engine") . "</p>";
        }
        
        ob_start();
        // Pass necessary data to the partial. Ensure data is escaped within the partial.
        include WEEBUNZ_PLUGIN_DIR . "public/partials/weebunz-quiz-display.php";
        return ob_get_clean();
    }

    private function get_or_create_quiz_session($user_id, $quiz_id) {
        global $wpdb;
        $session_table = $wpdb->prefix . "quiz_sessions";

        // Check for an existing active session
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT session_id FROM {$session_table} WHERE user_id = %d AND quiz_id = %d AND status = %s AND expires_at > %s",
            $user_id, $quiz_id, "active", current_time("mysql")
        ));

        if ($session) {
            return $session->session_id;
        }

        // Create a new session
        $session_uuid = wp_generate_uuid4();
        $session_expiry_seconds = get_option("weebunz_quiz_engine_session_expiry", 3600); // Default 1 hour
        $expires_at = date("Y-m-d H:i:s", time() + intval($session_expiry_seconds));

        $inserted = $wpdb->insert(
            $session_table,
            array(
                "user_id" => $user_id,
                "quiz_id" => $quiz_id,
                "session_id" => $session_uuid,
                "session_data" => wp_json_encode(array()),
                "created_at" => current_time("mysql"),
                "expires_at" => $expires_at,
                "status" => "active",
            ),
            array("%d", "%d", "%s", "%s", "%s", "%s", "%s")
        );
        return $inserted ? $session_uuid : null;
    }

    public function render_quiz_list_shortcode($atts) {
        $atts = shortcode_atts(array("limit" => 10), $atts, "weebunz_quiz_list");
        $limit = absint($atts["limit"]);
        
        global $wpdb;
        $quizzes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}active_quizzes WHERE status = %s ORDER BY id DESC LIMIT %d", 
            "active", 
            $limit
        ));
        
        if (empty($quizzes)) {
            return "<p>" . esc_html__("No quizzes available.", "weebunz-quiz-engine") . "</p>";
        }
        
        ob_start();
        include WEEBUNZ_PLUGIN_DIR . "public/partials/weebunz-quiz-list.php";
        return ob_get_clean();
    }

    public function render_user_results_shortcode($atts) {
        if (!is_user_logged_in()) {
             return "<p>" . sprintf(
                wp_kses(
                    __("Please <a href=\"%s\">log in</a> to view your results.", "weebunz-quiz-engine"),
                    array( "a" => array( "href" => array() ) )
                ),
                esc_url(wp_login_url(get_permalink()))
            ) . "</p>";
        }
        $atts = shortcode_atts(array("limit" => 10), $atts, "weebunz_user_results");
        $limit = absint($atts["limit"]);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, q.title as quiz_title FROM {$wpdb->prefix}quiz_sessions s JOIN {$wpdb->prefix}active_quizzes q ON s.quiz_id = q.id WHERE s.user_id = %d AND s.status = %s ORDER BY s.ended_at DESC LIMIT %d",
            $user_id, 
            "completed", 
            $limit
        ));
        
        if (empty($sessions)) {
            return "<p>" . esc_html__("You have not completed any quizzes yet.", "weebunz-quiz-engine") . "</p>";
        }
        
        ob_start();
        include WEEBUNZ_PLUGIN_DIR . "public/partials/weebunz-user-results.php";
        return ob_get_clean();
    }

    public function render_raffle_entries_shortcode($atts) {
        if (!is_user_logged_in()) {
            return "<p>" . sprintf(
                wp_kses(
                    __("Please <a href=\"%s\">log in</a> to view your raffle entries.", "weebunz-quiz-engine"),
                    array( "a" => array( "href" => array() ) )
                ),
                esc_url(wp_login_url(get_permalink()))
            ) . "</p>";
        }
        $user_id = get_current_user_id();
        
        global $wpdb;
        // Assuming a raffle_entries table and raffle_events table exist as per schema
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT re.*, ra.title as raffle_title FROM {$wpdb->prefix}raffle_entries re JOIN {$wpdb->prefix}raffle_events ra ON re.raffle_id = ra.id WHERE re.user_id = %d ORDER BY re.created_at DESC",
            $user_id
        ));
        
        $total_entries = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}raffle_entries WHERE user_id = %d", $user_id));
        
        if (empty($entries)) {
            return "<p>" . esc_html__("You have no raffle entries yet. Complete quizzes to earn entries!", "weebunz-quiz-engine") . "</p>";
        }
        
        ob_start();
        include WEEBUNZ_PLUGIN_DIR . "public/partials/weebunz-raffle-entries.php";
        return ob_get_clean();
    }

    public function init_shortcodes() {
        $this->register_shortcodes();
    }
    
    public function handle_ajax_public_action() {
        check_ajax_referer("weebunz_public_ajax_nonce", "security");

        $quiz_id = isset($_POST["quiz_id"]) ? absint($_POST["quiz_id"]) : 0;
        // Sanitize answers more thoroughly depending on their expected structure
        $answers = isset($_POST["answers"]) ? $this->sanitize_answers_array($_POST["answers"]) : array();

        if ($quiz_id === 0) {
            wp_send_json_error(array("message" => esc_html__("Invalid Quiz ID.", "weebunz-quiz-engine")));
            return;
        }
        if (!is_user_logged_in()) {
            wp_send_json_error(array("message" => esc_html__("You must be logged in.", "weebunz-quiz-engine")), 403);
            return;
        }

        // Further processing: save answers, calculate score, update session, etc.
        // This logic needs to be implemented based on how answers are structured and stored.
        // Example: global $wpdb; $user_id = get_current_user_id(); ...

        wp_send_json_success(array(
            "message" => sprintf(esc_html__("Answers submitted successfully for quiz ID: %d", "weebunz-quiz-engine"), $quiz_id)
        ));
    }

    private function sanitize_answers_array($answers) {
        if (!is_array($answers)) {
            return array();
        }
        $sanitized_answers = array();
        foreach ($answers as $question_id => $answer_value) {
            // Assuming question_id is numeric and answer_value is simple text or numeric
            $sanitized_question_id = absint($question_id);
            if (is_array($answer_value)) { // For multiple choice / checkboxes
                $sanitized_answer_value = array_map("sanitize_text_field", $answer_value);
            } else {
                $sanitized_answer_value = sanitize_text_field($answer_value);
            }
            $sanitized_answers[$sanitized_question_id] = $sanitized_answer_value;
        }
        return $sanitized_answers;
    }

    public function register_rest_routes() {
        register_rest_route("weebunz/v1", "/quiz/(?P<id>\\d+)", array(
            "methods" => WP_REST_Server::READABLE,
            "callback" => array($this, "get_quiz_data_rest"),
            "args" => array(
                "id" => array(
                    "validate_callback" => "is_numeric",
                    "sanitize_callback" => "absint",
                    "required" => true,
                    "description" => esc_html__("Unique identifier for the quiz.", "weebunz-quiz-engine")
                ),
            ),
            "permission_callback" => function(WP_REST_Request $request) {
                // Allow public access to quiz data, or add capability checks if needed
                return true; 
            },
        ));

        register_rest_route("weebunz/v1", "/submit-answers", array(
            "methods" => WP_REST_Server::CREATABLE,
            "callback" => array($this, "submit_quiz_answers_rest"),
            "args" => array(
                "quiz_id" => array(
                    "required" => true,
                    "validate_callback" => function($param) { return is_numeric($param) && $param > 0; },
                    "sanitize_callback" => "absint"
                ),
                "session_id" => array(
                    "required" => true,
                    "validate_callback" => function($param) { return is_string($param) && preg_match("/^[a-f\\d]{8}-([a-f\\d]{4}-){3}[a-f\\d]{12}$/i", $param); }, // Basic UUID format check
                    "sanitize_callback" => "sanitize_text_field"
                ),
                "answers" => array(
                    "required" => true,
                    "validate_callback" => array($this, "validate_answers_param"),
                    "sanitize_callback" => array($this, "sanitize_answers_param"),
                ),
            ),
            "permission_callback" => function(WP_REST_Request $request) {
                return is_user_logged_in(); // Only logged-in users can submit answers
            },
        ));
    }

    public function validate_answers_param($value, $request, $param) {
        if (!is_array($value)) {
            return new WP_Error("rest_invalid_param", esc_html__("Answers must be an array.", "weebunz-quiz-engine"), array("status" => 400));
        }
        foreach ($value as $question_id => $answer_value) {
            if (!is_numeric($question_id) || intval($question_id) <= 0) {
                return new WP_Error("rest_invalid_param", esc_html__("Invalid question ID in answers.", "weebunz-quiz-engine"), array("status" => 400));
            }
            // Allow string, numeric, or array of strings/numerics for answer_value
            if (!is_string($answer_value) && !is_numeric($answer_value) && !is_array($answer_value)) {
                 return new WP_Error("rest_invalid_param", sprintf(esc_html__("Invalid answer format for question ID %s.", "weebunz-quiz-engine"), esc_html($question_id)), array("status" => 400));
            }
            if (is_array($answer_value)) {
                foreach($answer_value as $sub_answer) {
                    if (!is_string($sub_answer) && !is_numeric($sub_answer)) {
                        return new WP_Error("rest_invalid_param", sprintf(esc_html__("Invalid sub-answer format for question ID %s.", "weebunz-quiz-engine"), esc_html($question_id)), array("status" => 400));
                    }
                }
            }
        }
        return true;
    }

    public function sanitize_answers_param($value, $request, $param) {
        return $this->sanitize_answers_array($value); // Reuse existing sanitization logic
    }

    public function get_quiz_data_rest(WP_REST_Request $request) {
        $quiz_id = $request->get_param("id");
        global $wpdb;
        $quiz = $wpdb->get_row($wpdb->prepare("SELECT id, title, description, time_limit FROM {$wpdb->prefix}active_quizzes WHERE id = %d AND status = %s", $quiz_id, "active"));

        if (!$quiz) {
            return new WP_Error("rest_quiz_not_found", esc_html__("Quiz not found.", "weebunz-quiz-engine"), array("status" => 404));
        }

        // Fetch questions (example, adjust as needed)
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT qp.id, qp.question_text, qp.question_type, qa.id as answer_id, qa.answer_text, qa.is_correct FROM {$wpdb->prefix}questions_pool qp LEFT JOIN {$wpdb->prefix}question_answers qa ON qp.id = qa.question_id WHERE qp.quiz_id = %d ORDER BY qp.id, qa.id", 
            $quiz_id
        ));
        
        $formatted_questions = array();
        if ($questions) {
            foreach($questions as $q_row) {
                if (!isset($formatted_questions[$q_row->id])) {
                    $formatted_questions[$q_row->id] = array(
                        "id" => $q_row->id,
                        "text" => $q_row->question_text,
                        "type" => $q_row->question_type,
                        "answers" => array()
                    );
                }
                if ($q_row->answer_id) {
                     $formatted_questions[$q_row->id]["answers"][] = array(
                        "id" => $q_row->answer_id,
                        "text" => $q_row->answer_text
                        // Do NOT expose is_correct here to the client before submission
                    );
                }
            }
        }

        $response_data = array(
            "id" => $quiz->id,
            "title" => $quiz->title,
            "description" => $quiz->description,
            "time_limit" => $quiz->time_limit,
            "questions" => array_values($formatted_questions) // Re-index array
        );
        return new WP_REST_Response($response_data, 200);
    }

    public function submit_quiz_answers_rest(WP_REST_Request $request) {
        $quiz_id = $request->get_param("quiz_id");
        $session_id = $request->get_param("session_id");
        $answers = $request->get_param("answers"); 
        $user_id = get_current_user_id();

        global $wpdb;
        // Validate session
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}quiz_sessions WHERE session_id = %s AND user_id = %d AND quiz_id = %d AND status = %s AND expires_at > %s",
            $session_id, $user_id, $quiz_id, "active", current_time("mysql")
        ));

        if (!$session) {
            return new WP_Error("rest_invalid_session", esc_html__("Invalid or expired quiz session.", "weebunz-quiz-engine"), array("status" => 403));
        }

        // --- Processing logic for answers ---
        // 1. Iterate through $answers (question_id => selected_answer_id(s))
        // 2. For each answer, fetch the correct answer(s) from {$wpdb->prefix}question_answers
        // 3. Compare and calculate score
        // 4. Store user answers in {$wpdb->prefix}user_answers
        // 5. Update {$wpdb->prefix}quiz_sessions: status to "completed", score, ended_at, session_data (with answers and score)
        // Example of storing an answer:
        // foreach ($answers as $question_id => $submitted_answer_id) {
        //    $wpdb->insert($wpdb->prefix . "user_answers", 
        //        array("session_db_id" => $session->id, "question_id" => $question_id, "answer_id" => $submitted_answer_id, "is_correct" => $is_correct_flag), 
        //        array("%d", "%d", "%d", "%d")
        //    );
        // }
        // $wpdb->update($wpdb->prefix . "quiz_sessions", 
        //    array("status" => "completed", "score" => $calculated_score, "ended_at" => current_time("mysql") ), 
        //    array("id" => $session->id), 
        //    array("%s", "%d", "%s"), 
        //    array("%d")
        // );
        // --- End processing logic placeholder ---

        return new WP_REST_Response(array(
            "message" => sprintf(esc_html__("Answers submitted for quiz %d.", "weebunz-quiz-engine"), $quiz_id),
            "status" => "success"
            // "score" => $calculated_score // etc.
        ), 200);
    }
}

