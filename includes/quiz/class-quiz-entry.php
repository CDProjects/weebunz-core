<?php
// File: /wp-content/plugins/weebunz-core/includes/quiz/class-quiz-entry.php

namespace Weebunz\Quiz;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class Quiz_Entry {
    private $wpdb;
    private $quiz_id;
    private $user_id;
    private $session_id;
    private $quiz_data;
    private $current_question;
    private $start_time;
    private $answers = [];

    public function __construct($quiz_id, $user_id = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->quiz_id = $quiz_id;
        $this->user_id = $user_id;
        $this->session_id = wp_generate_uuid4();
    }

    public function initialize() {
        try {
            // Get quiz data
            $this->quiz_data = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT q.*, qt.* 
                FROM {$this->wpdb->prefix}active_quizzes q
                JOIN {$this->wpdb->prefix}quiz_types qt ON q.quiz_type_id = qt.id
                WHERE q.id = %d AND q.status = 'active'",
                $this->quiz_id
            ));

            if (!$this->quiz_data) {
                throw new \Exception('Quiz not found or not active');
            }

            // Get questions
            $questions = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT qp.* 
                FROM {$this->wpdb->prefix}quiz_questions qq
                JOIN {$this->wpdb->prefix}questions_pool qp ON qq.question_id = qp.id
                WHERE qq.quiz_id = %d
                ORDER BY RAND()
                LIMIT %d",
                $this->quiz_id,
                $this->quiz_data->question_count
            ));

            if (count($questions) < $this->quiz_data->question_count) {
                throw new \Exception('Insufficient questions available');
            }

            // Store in session
            $session_data = [
                'quiz_id' => $this->quiz_id,
                'questions' => $questions,
                'current_index' => 0,
                'start_time' => time(),
                'answers' => []
            ];

            wp_cache_set($this->session_id, $session_data, 'weebunz_quiz_sessions', 900);

            return [
                'session_id' => $this->session_id,
                'question_count' => count($questions),
                'time_limit' => $this->quiz_data->time_limit,
                'entry_cost' => $this->quiz_data->entry_cost,
                'max_entries' => $this->quiz_data->max_entries
            ];

        } catch (\Exception $e) {
            Logger::error('Quiz initialization failed', [
                'quiz_id' => $this->quiz_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function get_next_question() {
        try {
            $session_data = wp_cache_get($this->session_id, 'weebunz_quiz_sessions');
            if (!$session_data) {
                throw new \Exception('Quiz session expired');
            }

            if ($session_data['current_index'] >= count($session_data['questions'])) {
                return null; // Quiz completed
            }

            $question = $session_data['questions'][$session_data['current_index']];
            
            // Get answers
            $answers = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT id, answer_text 
                FROM {$this->wpdb->prefix}question_answers
                WHERE question_id = %d
                ORDER BY RAND()",
                $question->id
            ));

            return [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'answers' => $answers,
                'time_limit' => $question->time_limit,
                'question_number' => $session_data['current_index'] + 1,
                'total_questions' => count($session_data['questions'])
            ];

        } catch (\Exception $e) {
            Logger::error('Error fetching next question', [
                'session_id' => $this->session_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function submit_answer($question_id, $answer_id, $time_taken) {
        try {
            $session_data = wp_cache_get($this->session_id, 'weebunz_quiz_sessions');
            if (!$session_data) {
                throw new \Exception('Quiz session expired');
            }

            // Verify answer
            $is_correct = (bool) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT is_correct 
                FROM {$this->wpdb->prefix}question_answers
                WHERE id = %d AND question_id = %d",
                $answer_id,
                $question_id
            ));

            // Store answer
            $session_data['answers'][] = [
                'question_id' => $question_id,
                'answer_id' => $answer_id,
                'time_taken' => $time_taken,
                'is_correct' => $is_correct
            ];

            // Move to next question
            $session_data['current_index']++;
            
            // Update session
            wp_cache_set($this->session_id, $session_data, 'weebunz_quiz_sessions', 900);

            return [
                'is_correct' => $is_correct,
                'quiz_completed' => $session_data['current_index'] >= count($session_data['questions']),
                'next_question' => null // Will be fetched separately if needed
            ];

        } catch (\Exception $e) {
            Logger::error('Error submitting answer', [
                'session_id' => $this->session_id,
                'question_id' => $question_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function complete_quiz() {
        try {
            $session_data = wp_cache_get($this->session_id, 'weebunz_quiz_sessions');
            if (!$session_data) {
                throw new \Exception('Quiz session expired');
            }

            // Calculate results
            $correct_answers = count(array_filter($session_data['answers'], 
                fn($a) => $a['is_correct']
            ));

            $total_time = array_sum(array_column($session_data['answers'], 'time_taken'));
            
            // Calculate entries earned
            $entries_earned = floor($correct_answers / $this->quiz_data->answers_per_entry);
            $entries_earned = min($entries_earned, $this->quiz_data->max_entries);

            // Record attempt
            $attempt_data = [
                'user_id' => $this->user_id,
                'quiz_id' => $this->quiz_id,
                'score' => $correct_answers,
                'time_taken' => $total_time,
                'entries_earned' => $entries_earned,
                'created_at' => current_time('mysql')
            ];

            $this->wpdb->insert($this->wpdb->prefix . 'quiz_attempts', $attempt_data);
            $attempt_id = $this->wpdb->insert_id;

            // Record individual answers
            foreach ($session_data['answers'] as $answer) {
                $this->wpdb->insert(
                    $this->wpdb->prefix . 'user_answers',
                    [
                        'attempt_id' => $attempt_id,
                        'question_id' => $answer['question_id'],
                        'answer_id' => $answer['answer_id'],
                        'time_taken' => $answer['time_taken'],
                        'is_correct' => $answer['is_correct'],
                        'created_at' => current_time('mysql')
                    ]
                );
            }

            // Clean up session
            wp_cache_delete($this->session_id, 'weebunz_quiz_sessions');

            return [
                'attempt_id' => $attempt_id,
                'correct_answers' => $correct_answers,
                'total_questions' => count($session_data['questions']),
                'total_time' => $total_time,
                'entries_earned' => $entries_earned
            ];

        } catch (\Exception $e) {
            Logger::error('Error completing quiz', [
                'session_id' => $this->session_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}