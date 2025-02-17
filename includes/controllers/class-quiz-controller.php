<?php
namespace Weebunz\API;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Quiz\Quiz_Manager;
use Weebunz\Quiz\Quiz_Entry;
use Weebunz\Quiz\Quiz_Validator;
use Weebunz\Logger;

class Quiz_Controller {
    private $namespace = 'weebunz/v1';
    private $quiz_manager;
    private $quiz_validator;

    public function __construct() {
        $this->quiz_manager = new Quiz_Manager(get_current_user_id());
        $this->quiz_validator = new Quiz_Validator(get_current_user_id());
    }

    public function register_routes() {
        // Get available quizzes
        register_rest_route($this->namespace, '/quizzes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_available_quizzes'],
            'permission_callback' => '__return_true'
        ]);

        // Start quiz
        register_rest_route($this->namespace, '/quiz/start', [
            'methods' => 'POST',
            'callback' => [$this, 'start_quiz'],
            'permission_callback' => '__return_true',
            'args' => [
                'quiz_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        // Get next question
        register_rest_route($this->namespace, '/quiz/question', [
            'methods' => 'GET',
            'callback' => [$this, 'get_question'],
            'permission_callback' => '__return_true'
        ]);

        // Submit answer
        register_rest_route($this->namespace, '/quiz/answer', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_answer'],
            'permission_callback' => '__return_true'
        ]);

        // Complete quiz
        register_rest_route($this->namespace, '/quiz/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_quiz'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function get_available_quizzes(\WP_REST_Request $request) {
        try {
            $quizzes = $this->quiz_manager->get_available_quizzes();
            
            return rest_ensure_response([
                'success' => true,
                'quizzes' => $quizzes
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch quizzes', ['error' => $e->getMessage()]);
            return new \WP_Error(
                'quiz_fetch_failed',
                'Failed to fetch available quizzes',
                ['status' => 500]
            );
        }
    }

    public function start_quiz(\WP_REST_Request $request) {
        try {
            $quiz_id = $request->get_param('quiz_id');
            $session = $this->quiz_manager->start_quiz($quiz_id);
            
            if (!$session) {
                throw new \Exception('Failed to initialize quiz session');
            }

            return rest_ensure_response([
                'success' => true,
                'session_id' => $session['session_id'],
                'quiz_info' => [
                    'total_questions' => $session['question_count'],
                    'time_limit' => $session['time_limit'],
                    'entry_reward' => $session['max_entries']
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to start quiz', [
                'quiz_id' => $quiz_id,
                'error' => $e->getMessage()
            ]);
            return new \WP_Error(
                'quiz_start_failed',
                'Failed to start quiz: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_question(\WP_REST_Request $request) {
        try {
            $session_id = $request->get_header('X-Quiz-Session');
            if (!$session_id) {
                throw new \Exception('Quiz session ID is required');
            }

            $question = $this->quiz_manager->get_next_question($session_id);
            
            if (!$question) {
                return rest_ensure_response([
                    'success' => true,
                    'completed' => true
                ]);
            }

            return rest_ensure_response([
                'success' => true,
                'question' => $question
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to fetch question', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return new \WP_Error(
                'question_fetch_failed',
                'Failed to fetch question: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function submit_answer(\WP_REST_Request $request) {
        try {
            $session_id = $request->get_header('X-Quiz-Session');
            if (!$session_id) {
                throw new \Exception('Quiz session ID is required');
            }

            $params = $request->get_json_params();
            if (!isset($params['question_id']) || !isset($params['answer_id'])) {
                throw new \Exception('Question ID and answer ID are required');
            }

            $result = $this->quiz_manager->submit_answer(
                $session_id,
                $params['question_id'],
                $params['answer_id'],
                $params['time_taken'] ?? null
            );

            return rest_ensure_response([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to submit answer', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return new \WP_Error(
                'answer_submit_failed',
                'Failed to submit answer: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function complete_quiz(\WP_REST_Request $request) {
        try {
            $session_id = $request->get_header('X-Quiz-Session');
            if (!$session_id) {
                throw new \Exception('Quiz session ID is required');
            }

            $results = $this->quiz_manager->complete_quiz($session_id);

            return rest_ensure_response([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to complete quiz', [
                'session_id' => $session_id,
                'error' => $e->getMessage()
            ]);
            return new \WP_Error(
                'quiz_completion_failed',
                'Failed to complete quiz: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}