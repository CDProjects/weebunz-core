<?php
namespace Weebunz\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Quiz\Quiz_Manager;
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

    /**
     * Register REST API endpoints
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/quiz/start', [
            'methods' => 'POST',
            'callback' => [$this, 'start_quiz'],
            'permission_callback' => [$this, 'check_quiz_permissions'],
            'args' => [
                'quiz_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/quiz/question', [
            'methods' => 'GET',
            'callback' => [$this, 'get_question'],
            'permission_callback' => [$this, 'check_quiz_permissions']
        ]);

        register_rest_route($this->namespace, '/quiz/answer', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_answer'],
            'permission_callback' => [$this, 'check_quiz_permissions']
        ]);

        register_rest_route($this->namespace, '/quiz/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_quiz'],
            'permission_callback' => [$this, 'check_quiz_permissions']
        ]);
    }

    /**
     * Start a new quiz session
     */
public function start_quiz($request) {
    try {
        $quiz_id = absint($request->get_param('quiz_id'));
        
        // Add detailed logging
        error_log('Quiz start request received for ID: ' . $quiz_id);
        
        if (empty($quiz_id)) {
            error_log('Quiz ID validation failed: empty or zero');
            return new \WP_Error(
                'quiz_validation_failed',
                'Quiz ID is required and must be a positive number',
                ['status' => 400]
            );
        }

        // Log the incoming request
        Logger::debug('Starting quiz', [
            'quiz_id' => $quiz_id,
            'user_id' => get_current_user_id()
        ]);

        $validation = $this->quiz_validator->validate_quiz_start($quiz_id);
        if (!$validation['valid']) {
            error_log('Quiz validation failed: ' . $validation['error']);
            Logger::warning('Quiz validation failed', [
                'quiz_id' => $quiz_id,
                'error' => $validation['error']
            ]);
            return new \WP_Error(
                'quiz_validation_failed',
                $validation['error'],
                ['status' => 400]
            );
        }

        $session = $this->quiz_manager->start_quiz($quiz_id);
        if (!$session) {
            error_log('Failed to create quiz session for ID: ' . $quiz_id);
            throw new \Exception('Failed to create quiz session');
        }

        // Log successful quiz start
        Logger::info('Quiz started successfully', [
            'quiz_id' => $quiz_id,
            'session_id' => $session['session_id']
        ]);
        
        // Add detailed logging of the response
        error_log('Sending quiz start response: ' . json_encode([
            'success' => true,
            'session_id' => $session['session_id'],
            'quiz_info' => [
                'total_questions' => $session['total_questions'],
                'time_limit' => $session['time_limit'],
                'entry_reward' => $session['entry_reward']
            ]
        ]));

        return rest_ensure_response([
            'success' => true,
            'session_id' => $session['session_id'],
            'quiz_info' => [
                'total_questions' => $session['total_questions'],
                'time_limit' => $session['time_limit'],
                'entry_reward' => $session['entry_reward']
            ]
        ]);

    } catch (\Exception $e) {
        error_log('Quiz start exception: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
        Logger::error('Quiz start failed', [
            'quiz_id' => $quiz_id ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return new \WP_Error(
            'quiz_start_failed',
            $e->getMessage(),
            ['status' => 500]
        );
    }
}

    /**
     * Get current question
     */
    public function get_question($request) {
        try {
            $session_id = $request->get_header('X-Quiz-Session');
            Logger::debug('Getting question', ['session_id' => $session_id]);
    
            if (!$session_id) {
                return new \WP_Error(
                    'missing_session',
                    'Quiz session ID is required',
                    ['status' => 400]
                );
            }

            $question = $this->quiz_manager->get_next_question($session_id);
    
            if (!$question) {
                Logger::debug('No more questions, quiz completed');
                return rest_ensure_response([
                    'success' => true,
                    'completed' => true
                ]);
            }

            // Structure the question data to match the frontend expectations
            return rest_ensure_response([
                'success' => true,
                'question' => $question
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get question', [
                'session_id' => $session_id ?? 'none',
                'error' => $e->getMessage()
            ]);
    
            return new \WP_Error(
                'question_fetch_failed',
                'Failed to fetch question',
                ['status' => 500]
            );
        }
    }

    /**
     * Submit answer for current question
     */
    public function submit_answer(\WP_REST_Request $request) {
        try {
            $session_id = $request->get_header('X-Quiz-Session');
            $params = $request->get_json_params();
            
            if (!$session_id) {
                return new \WP_Error(
                    'missing_session',
                    'Quiz session ID is required',
                    ['status' => 400]
                );
            }

            // Validate required parameters
            if (!isset($params['question_id']) || !isset($params['time_taken'])) {
                return new \WP_Error(
                    'missing_parameters',
                    'Missing required parameters',
                    ['status' => 400]
                );
            }

            // Allow null answer_id for skipped questions
            $answer_id = isset($params['answer_id']) ? $params['answer_id'] : null;

            $validation = $this->quiz_validator->validate_answer_submission(
                $session_id,
                $params['question_id'],
                $answer_id,
                $params['time_taken']
            );

            if (!$validation['valid']) {
                return new \WP_Error(
                    'answer_validation_failed',
                    $validation['error'],
                    ['status' => 400]
                );
            }

            $result = $this->quiz_manager->submit_answer(
                $session_id,
                $params['question_id'],
                $answer_id,
                $params['time_taken']
            );

            return rest_ensure_response([
                'success' => true,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Logger::error('Answer submission failed', [
                'session_id' => $session_id ?? 'none',
                'error' => $e->getMessage()
            ]);
            
            return new \WP_Error(
                'submission_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Complete quiz and calculate results
     */
    public function complete_quiz($request) {
        try {
            $session_id = $request->get_header('X-Quiz-Session');
            
            if (!$session_id) {
                return new \WP_Error(
                    'missing_session',
                    'Quiz session ID is required',
                    ['status' => 400]
                );
            }

            $validation = $this->quiz_validator->validate_quiz_completion($session_id);
            
            if (!$validation['valid']) {
                return new \WP_Error(
                    'completion_validation_failed',
                    $validation['error'],
                    ['status' => 400]
                );
            }

            $results = $this->quiz_manager->complete_quiz($session_id);

            return rest_ensure_response([
                'success' => true,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Logger::error('Quiz completion failed', [
                'session_id' => $session_id ?? 'none',
                'error' => $e->getMessage()
            ]);
            
            return new \WP_Error(
                'quiz_completion_failed',
                'Failed to complete quiz',
                ['status' => 500]
            );
        }
    }

    /**
     * Check permissions for quiz endpoints
     */
    public function check_quiz_permissions($request) {
        // Allow all requests in test mode if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // For production, check user capabilities
        if (!current_user_can('edit_posts')) {
            return new \WP_Error(
                'rest_forbidden',
                'You do not have permissions to access this endpoint.',
                ['status' => 403]
            );
        }

        return true;
    }
}