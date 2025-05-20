<?php
// File: /wp-content/plugins/weebunz-core/includes/quiz/class-quiz-manager.php

namespace Weebunz\Quiz;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Util\Logger;

class QuizManager {
    private $wpdb;
    private $user_id;
    private $session_expiry = 900; // 15 minutes
    private $cache_group   = 'weebunz_quiz_sessions';

    public function __construct($user_id = null) {
        global $wpdb;
        $this->wpdb    = $wpdb;
        $this->user_id = $user_id;
    }

    /**
     * Get available quizzes
     */
    public function get_available_quizzes() {
        try {
            Logger::debug('Fetching available quizzes', [
                'user_id' => $this->user_id
            ]);

            // explicitly list all quiz_type columns + question_count
            $sql = "
                SELECT
                    qt.id,
                    qt.name,
                    qt.difficulty_level,
                    qt.time_limit,
                    qt.entry_cost,
                    qt.max_entries,
                    qt.answers_per_entry,
                    COUNT(qp.id) AS question_count
                FROM {$this->wpdb->prefix}quiz_types qt
                LEFT JOIN {$this->wpdb->prefix}questions_pool qp 
                    ON qp.difficulty_level = qt.difficulty_level
                GROUP BY qt.id
                ORDER BY qt.entry_cost ASC
            ";
            $quizzes = $this->wpdb->get_results($sql);

            Logger::debug('Available quizzes fetched', [
                'count' => count($quizzes)
            ]);

            return $quizzes;
        } catch (\Exception $e) {
            Logger::error('Failed to fetch quizzes', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Start new quiz session
     */
    public function start_quiz($quiz_id) {
        try {
            Logger::info('Starting quiz', [
                'quiz_id' => $quiz_id,
                'user_id' => $this->user_id
            ]);

            // fetch quiz_type + question_count in one query
            $sql = "
                SELECT
                    qt.id,
                    qt.name,
                    qt.difficulty_level,
                    qt.time_limit,
                    qt.entry_cost,
                    qt.max_entries,
                    qt.answers_per_entry,
                    COUNT(qp.id) AS question_count
                FROM {$this->wpdb->prefix}quiz_types qt
                LEFT JOIN {$this->wpdb->prefix}questions_pool qp
                    ON qp.difficulty_level = qt.difficulty_level
                WHERE qt.id = %d
                GROUP BY qt.id
            ";
            $quiz = $this->wpdb->get_row($this->wpdb->prepare($sql, $quiz_id));

            if (!$quiz) {
                throw new \Exception('Quiz type not found');
            }

            $num_questions = (int) $quiz->question_count;

            // Get random questions
            $questions = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT
                    qp.*,
                    GROUP_CONCAT(qa.id)          AS answer_ids,
                    GROUP_CONCAT(qa.answer_text) AS answer_texts,
                    GROUP_CONCAT(qa.is_correct)  AS correct_flags
                FROM {$this->wpdb->prefix}questions_pool qp
                JOIN {$this->wpdb->prefix}question_answers qa
                    ON qa.question_id = qp.id
                WHERE qp.difficulty_level = %s
                GROUP BY qp.id
                ORDER BY RAND()
                LIMIT %d
            ", $quiz->difficulty_level, $num_questions));

            if (count($questions) < $num_questions) {
                throw new \Exception('Insufficient questions available');
            }

            // Create session
            $session_id   = wp_generate_uuid4();
            $session_data = [
                'quiz_type_id'     => $quiz_id,
                'user_id'          => $this->user_id,
                'questions'        => $questions,
                'current_question' => 0,
                'answers'          => [],
                'start_time'       => time(),
                'expires_at'       => time() + $this->session_expiry,
            ];

            set_transient(
                'weebunz_quiz_session_' . $session_id,
                $session_data,
                $this->session_expiry
            );

            $this->wpdb->insert(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'session_id'   => $session_id,
                    'quiz_type_id' => $quiz_id,
                    'user_id'      => $this->user_id,
                    'session_data' => maybe_serialize($session_data),
                    'status'       => 'active',
                    'created_at'   => current_time('mysql'),
                    'expires_at'   => date('Y-m-d H:i:s', time() + $this->session_expiry),
                ]
            );
            if ($this->wpdb->last_error) {
                Logger::error('Database error starting quiz', [
                    'error'   => $this->wpdb->last_error,
                    'quiz_id' => $quiz_id
                ]);
                throw new \Exception('Failed to store quiz session');
            }

            Logger::info('Quiz session started', [
                'session_id'     => $session_id,
                'question_count' => $num_questions
            ]);

            return [
                'session_id'      => $session_id,
                'total_questions' => $num_questions,
                'time_limit'      => $quiz->time_limit,
                'entry_reward'    => $quiz->max_entries
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to start quiz', [
                'quiz_id' => $quiz_id,
                'error'   => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get session data
     */
    private function get_session_data($session_id) {
        try {
            $session_data = get_transient('weebunz_quiz_session_' . $session_id);
            if (!$session_data) {
                $session = $this->wpdb->get_row($this->wpdb->prepare("
                    SELECT session_data
                    FROM {$this->wpdb->prefix}quiz_sessions
                    WHERE session_id = %s
                    AND status = 'active'
                    AND expires_at > NOW()
                ", $session_id));

                if ($session) {
                    $session_data = maybe_unserialize($session->session_data);
                    set_transient(
                        'weebunz_quiz_session_' . $session_id,
                        $session_data,
                        $this->session_expiry
                    );
                    $this->wpdb->update(
                        $this->wpdb->prefix . 'quiz_sessions',
                        [
                            'expires_at' => date('Y-m-d H:i:s', time() + $this->session_expiry),
                            'updated_at' => current_time('mysql')
                        ],
                        ['session_id' => $session_id]
                    );
                    Logger::debug('Session refreshed', [
                        'session_id' => $session_id,
                        'new_expiry' => date('Y-m-d H:i:s', time() + $this->session_expiry)
                    ]);
                }
            }

            if (!$session_data) {
                Logger::error('Session not found or expired', [
                    'session_id' => $session_id
                ]);
                throw new \Exception('Session expired or not found');
            }

            return $session_data;
        } catch (\Exception $e) {
            Logger::error('Failed to get session data', [
                'session_id' => $session_id,
                'error'      => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update session data
     */
    private function update_session_data($session_id, $session_data) {
        set_transient(
            'weebunz_quiz_session_' . $session_id,
            $session_data,
            $this->session_expiry
        );
        return $this->wpdb->update(
            $this->wpdb->prefix . 'quiz_sessions',
            [
                'session_data' => maybe_serialize($session_data),
                'updated_at'   => current_time('mysql')
            ],
            ['session_id' => $session_id]
        );
    }

    /**
     * Get next question
     */
    public function get_next_question($session_id) {
        try {
            $session_data = $this->get_session_data($session_id);
            if ($session_data['current_question'] >= count($session_data['questions'])) {
                return null; // Quiz completed
            }
            $question = $session_data['questions'][$session_data['current_question']];
            $answer_ids    = explode(',', $question->answer_ids);
            $answer_texts  = explode(',', $question->answer_texts);
            $correct_flags = explode(',', $question->correct_flags);
            $answers = [];
            foreach ($answer_ids as $i => $id) {
                $answers[] = [
                    'id'          => $id,
                    'answer_text' => $answer_texts[$i]
                ];
            }
            shuffle($answers);
            return [
                'id'              => $question->id,
                'question_text'   => $question->question_text,
                'answers'         => $answers,
                'time_limit'      => $question->time_limit,
                'question_number' => $session_data['current_question'] + 1,
                'total_questions' => count($session_data['questions'])
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get next question', [
                'session_id' => $session_id,
                'error'      => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Submit answer
     */
    public function submit_answer($session_id, $question_id, $answer_id, $time_taken) {
        try {
            $session_data = $this->get_session_data($session_id);
            $is_correct = (bool) $this->wpdb->get_var($this->wpdb->prepare("
                SELECT is_correct
                FROM {$this->wpdb->prefix}question_answers
                WHERE id = %d AND question_id = %d
            ", $answer_id, $question_id));
            $session_data['answers'][] = [
                'question_id' => $question_id,
                'answer_id'   => $answer_id,
                'time_taken'  => $time_taken,
                'is_correct'  => $is_correct
            ];
            $session_data['current_question']++;
            $this->update_session_data($session_id, $session_data);
            return [
                'is_correct'    => $is_correct,
                'quiz_completed'=> $session_data['current_question'] >= count($session_data['questions'])
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to submit answer', [
                'session_id' => $session_id,
                'error'      => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Complete quiz
     */
    public function complete_quiz($session_id) {
        try {
            $session_data    = $this->get_session_data($session_id);
            $correct_answers = count(array_filter($session_data['answers'], fn($a) => $a['is_correct']));
            $quiz            = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT * FROM {$this->wpdb->prefix}quiz_types
                WHERE id = %d
            ", $session_data['quiz_type_id']));
            $entries_earned  = floor($correct_answers / $quiz->answers_per_entry);
            $entries_earned  = min($entries_earned, $quiz->max_entries);
            $this->wpdb->insert(
                $this->wpdb->prefix . 'quiz_attempts',
                [
                    'user_id'        => $this->user_id,
                    'quiz_type_id'   => $session_data['quiz_type_id'],
                    'score'          => $correct_answers,
                    'entries_earned' => $entries_earned,
                    'status'         => 'completed',
                    'start_time'     => date('Y-m-d H:i:s', $session_data['start_time']),
                    'end_time'       => current_time('mysql'),
                    'created_at'     => current_time('mysql')
                ]
            );
            delete_transient('weebunz_quiz_session_' . $session_id);
            $this->wpdb->update(
                $this->wpdb->prefix . 'quiz_sessions',
                [
                    'status'   => 'completed',
                    'ended_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );
            return [
                'correct_answers' => $correct_answers,
                'total_questions' => count($session_data['questions']),
                'entries_earned'  => $entries_earned
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to complete quiz', [
                'session_id' => $session_id,
                'error'      => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Debug the session data
     */
    private function debug_session_data($session_id, $context = '') {
        try {
            $transient_data = get_transient('weebunz_quiz_session_' . $session_id);
            $db_session     = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}quiz_sessions WHERE session_id = %s",
                $session_id
            ));
            Logger::debug('Session debug info: ' . $context, [
                'session_id'      => $session_id,
                'transient_exists'=> !empty($transient_data),
                'db_exists'       => !empty($db_session),
                'db_status'       => $db_session ? $db_session->status     : 'N/A',
                'db_expires'      => $db_session ? $db_session->expires_at : 'N/A'
            ]);
        } catch (\Exception $e) {
            Logger::error('Error debugging session', [
                'session_id' => $session_id,
                'error'      => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the name of a quiz type by ID
     */
    public function get_quiz_type_name($quiz_id) {
        try {
            Logger::debug('Getting quiz type name', ['quiz_id' => $quiz_id]);
            $quiz_type = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT name FROM {$this->wpdb->prefix}quiz_types WHERE id = %d",
                $quiz_id
            ));
            if (!$quiz_type) {
                Logger::warning('Quiz type not found', ['quiz_id' => $quiz_id]);
                return null;
            }
            return $quiz_type;
        } catch (\Exception $e) {
            Logger::error('Failed to get quiz type name', [
                'quiz_id' => $quiz_id,
                'error'   => $e->getMessage()
            ]);
            return null;
        }
    }
}
