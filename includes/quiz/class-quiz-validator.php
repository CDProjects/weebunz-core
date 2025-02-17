<?php
namespace Weebunz\Quiz;

use Weebunz\Logger;
use Weebunz\Quiz\Quiz_Session_Handler;

if (!defined('ABSPATH')) {
    exit;
}

require_once 'class-quiz-session-handler.php';

class Quiz_Validator {
    private $wpdb;
    private $user_id;
    private $spending_limit;

    public function __construct($user_id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->user_id = $user_id;
        $this->spending_limit = get_option('weebunz_weekly_spend_limit', 50);
    }

    public function validate_quiz_start($quiz_id) {
    try {
        Logger::debug('Starting quiz validation', [
            'quiz_id' => $quiz_id,
            'user_id' => $this->user_id
        ]);

        // First check if quiz type exists
        $quiz_type = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}quiz_types 
            WHERE id = %d",
            $quiz_id
        ));

        if (!$quiz_type) {
            throw new \Exception('Quiz type not found');
        }

        // Verify sufficient questions exist for this quiz type
        $question_count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}questions_pool 
            WHERE difficulty_level = %s",
            $quiz_type->difficulty_level
        ));

        if ($question_count < $quiz_type->question_count) {
            throw new \Exception('Insufficient questions available for this quiz type');
        }

        // Verify user can take quiz
        if ($this->user_id) {
            // Check weekly spending limit
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $spent = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) 
                FROM {$this->wpdb->prefix}spending_log 
                WHERE user_id = %d 
                AND created_at >= %s",
                $this->user_id,
                $week_start
            ));

            if (($spent + $quiz_type->entry_cost) > $this->spending_limit) {
                throw new \Exception('Weekly spending limit would be exceeded');
            }
        }

        Logger::debug('Quiz validation successful', [
            'quiz_id' => $quiz_id,
            'quiz_type' => $quiz_type->name
        ]);

        return [
            'valid' => true,
            'quiz' => $quiz_type
        ];

    } catch (\Exception $e) {
        Logger::error('Quiz validation failed', [
            'quiz_id' => $quiz_id,
            'user_id' => $this->user_id,
            'error' => $e->getMessage()
        ]);

        return [
            'valid' => false,
            'error' => $e->getMessage()
        ];
    }
}

    public function validate_answer_submission($session_id, $question_id, $answer_id, $time_taken) {
            try {
                // Retrieve session data from the session handler
                if (!class_exists('Weebunz\Quiz\Quiz_Session_Handler')) {
                    throw new \Exception('Quiz session handler class not found.');
                    }

                    $session_handler = new Quiz_Session_Handler();
                    $session_data = $session_handler->get_session_data($session_id);

                if (!$session_data) {
                    Logger::error('Session data is null for session ID: ' . $session_id);
                    throw new \Exception('Quiz session not found or expired.');
                    }

                // Verify question belongs to current quiz
                $current_question = $session_data['questions'][$session_data['current_index']] ?? null;
                if (!$current_question || $current_question->id != $question_id) {
                    throw new \Exception('Invalid question submission');
                }

                // Skip time validation for skipped questions
                if ($answer_id !== null) {
                    // Verify time taken is within limits (add 2 second grace period)
                    if ($time_taken > $current_question->time_limit + 2) {
                        throw new \Exception('Answer submitted after time limit');
                    }

                    // Verify answer belongs to question
                    $answer_exists = $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT COUNT(*) FROM {$this->wpdb->prefix}question_answers
                        WHERE id = %d AND question_id = %d",
                        $answer_id,
                        $question_id
                    ));

                    if (!$answer_exists) {
                        throw new \Exception('Invalid answer submission');
                    }
                }

                return [
                    'valid' => true,
                    'session_data' => $session_data
                ];

            } catch (\Exception $e) {
                Logger::error('Answer validation failed', [
                    'session_id' => $session_id,
                    'question_id' => $question_id,
                    'error' => $e->getMessage()
                ]);

                return [
                    'valid' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

    public function validate_quiz_completion($session_id) {
        try {
            // Get session data
            $session_data = wp_cache_get($session_id, 'weebunz_quiz_sessions');
            if (!$session_data) {
                throw new \Exception('Quiz session expired');
            }

            // Verify all questions were answered
            if (count($session_data['answers']) !== count($session_data['questions'])) {
                throw new \Exception('Not all questions were answered');
            }

            // Verify answers are for correct questions
            foreach ($session_data['answers'] as $index => $answer) {
                if ($answer['question_id'] !== $session_data['questions'][$index]->id) {
                    throw new \Exception('Answer sequence mismatch');
                }
            }

            return [
                'valid' => true,
                'session_data' => $session_data
            ];

        } catch (\Exception $e) {
            Logger::error('Quiz completion validation failed', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}