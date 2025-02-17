<?php
// Original working version from before plugin deletion
// Location: wp-content/plugins/weebunz-core/admin/class-quiz-ajax.php

namespace Weebunz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Quiz_Ajax {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Register AJAX actions
        add_action('wp_ajax_get_quiz_categories', array($this, 'get_quiz_categories'));
        add_action('wp_ajax_get_question_pool', array($this, 'get_question_pool'));
        add_action('wp_ajax_save_quiz', array($this, 'save_quiz'));
    }

    /**
     * Get categories for a quiz type
     */
    public function get_quiz_categories() {
        check_ajax_referer('weebunz_quiz_nonce', 'nonce');

        $quiz_type_id = isset($_GET['quiz_type']) ? intval($_GET['quiz_type']) : 0;
        
        if (!$quiz_type_id) {
            wp_send_json_error(['message' => 'Invalid quiz type']);
        }

        $categories = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DISTINCT category 
            FROM {$this->wpdb->prefix}questions_pool 
            WHERE difficulty_level = (
                SELECT difficulty_level 
                FROM {$this->wpdb->prefix}quiz_types 
                WHERE id = %d
            )",
            $quiz_type_id
        ));

        ob_start();
        ?>
        <div class="category-checkboxes">
            <?php foreach ($categories as $category): ?>
                <label class="category-label">
                    <input type="checkbox" 
                           name="categories[]" 
                           value="<?php echo esc_attr($category->category); ?>">
                    <?php echo esc_html($category->category); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get question pool for a quiz type
     */
    public function get_question_pool() {
        check_ajax_referer('weebunz_quiz_nonce', 'nonce');

        $quiz_type_id = isset($_GET['quiz_type']) ? intval($_GET['quiz_type']) : 0;
        $difficulty = isset($_GET['difficulty']) ? sanitize_text_field($_GET['difficulty']) : '';

        if (!$quiz_type_id || !$difficulty) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        $questions = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}questions_pool 
            WHERE difficulty_level = %s 
            ORDER BY RAND() 
            LIMIT 50",
            $difficulty
        ));

        ob_start();
        ?>
        <div class="question-pool-section">
            <h3>Question Pool</h3>
            <p class="description">Select questions to include in this quiz</p>
            
            <div class="question-list">
                <?php foreach ($questions as $question): ?>
                    <div class="question-item">
                        <label>
                            <input type="checkbox" 
                                   name="questions[]" 
                                   value="<?php echo esc_attr($question->id); ?>">
                            <span class="question-text">
                                <?php echo esc_html($question->question_text); ?>
                            </span>
                            <span class="question-category">
                                <?php echo esc_html($question->category); ?>
                            </span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Save new quiz
     */
    public function save_quiz() {
        check_ajax_referer('weebunz_quiz_nonce', 'nonce');

        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $quiz_type_id = isset($_POST['quiz_type']) ? intval($_POST['quiz_type']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $discount = isset($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : 0;
        $questions = isset($_POST['questions']) ? array_map('intval', $_POST['questions']) : [];

        // Validation
        if (!$title || !$quiz_type_id || !$start_date || !$end_date || empty($questions)) {
            wp_send_json_error(['message' => 'Missing required fields']);
        }

        try {
            $this->wpdb->query('START TRANSACTION');

            // Insert quiz
            $quiz_data = [
                'quiz_type_id' => $quiz_type_id,
                'title' => $title,
                'status' => 'draft',
                'discount_percentage' => $discount,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'created_at' => current_time('mysql')
            ];

            $this->wpdb->insert(
                $this->wpdb->prefix . 'active_quizzes',
                $quiz_data
            );

            $quiz_id = $this->wpdb->insert_id;

            // Insert quiz questions
            foreach ($questions as $question_id) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'quiz_questions',
                    [
                        'quiz_id' => $quiz_id,
                        'question_id' => $question_id,
                        'created_at' => current_time('mysql')
                    ]
                );
            }

            $this->wpdb->query('COMMIT');

            wp_send_json_success([
                'message' => 'Quiz created successfully',
                'redirect' => admin_url('admin.php?page=weebunz-quizzes&quiz=' . $quiz_id)
            ]);

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            wp_send_json_error([
                'message' => 'Error creating quiz: ' . $e->getMessage()
            ]);
        }
    }
}