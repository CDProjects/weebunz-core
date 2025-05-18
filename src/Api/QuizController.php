<?php
// File: /wp-content/plugins/weebunz-core/includes/controllers/class-quiz-controller.php

namespace Weebunz\Api;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Quiz\QuizManager;
use Weebunz\Quiz\QuizValidator;
use Weebunz\Logger;

class QuizController {
    private $namespace = 'weebunz/v1';
    private $quiz_manager;
    private $quiz_validator;

    public function __construct() {
        $this->quiz_manager = new QuizManager(get_current_user_id());
        $this->quiz_validator = new QuizValidator(get_current_user_id());
    }

    /**
     * Register REST API endpoints
     */
    public function register_routes() {
        Logger::info('Registering quiz routes');
        
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
        
        register_rest_route($this->namespace, '/quiz/session/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_session'],
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

            // For debugging/test mode, bypass strict validation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::debug('Bypassing strict validation in test mode', [
                    'quiz_id' => $quiz_id,
                    'quiz_type' => $this->quiz_manager->get_quiz_type_name($quiz_id)
                ]);
            } else {
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
            error_log('Quiz question request received');
            $session_id = $request->get_header('X-Quiz-Session');
            
            error_log('Quiz question request with session ID: ' . ($session_id ?? 'MISSING'));
            Logger::debug('Getting question', ['session_id' => $session_id]);

            if (!$session_id) {
                error_log('Quiz question request missing session ID');
                return new \WP_Error(
                    'missing_session',
                    'Quiz session ID is required',
                    ['status' => 400]
                );
            }

            try {
                $question = $this->quiz_manager->get_next_question($session_id);
                
                if ($question === null) {
                    error_log('Quiz completed for session: ' . $session_id);
                    Logger::debug('No more questions, quiz completed');
                    return rest_ensure_response([
                        'success' => true,
                        'completed' => true
                    ]);
                }
                
                error_log('Question fetched for session ' . $session_id . ': ' . json_encode(['id' => $question['id']]));
                
                // Structure the question data to match the frontend expectations
                return rest_ensure_response([
                    'success' => true,
                    'question' => $question
                ]);
            } catch (\Exception $e) {
                error_log('Error getting question for session ' . $session_id . ': ' . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            error_log('Exception in get_question: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            Logger::error('Failed to get question', [
                'session_id' => $session_id ?? 'none',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new \WP_Error(
                'question_fetch_failed',
                'Failed to fetch question: ' . $e->getMessage(),
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
            
            error_log('Answer submission received: ' . json_encode($params));
            
            if (!$session_id) {
                error_log('Missing session ID for answer submission');
                return new \WP_Error(
                    'missing_session',
                    'Quiz session ID is required',
                    ['status' => 400]
                );
            }

            // Validate required parameters
            if (!isset($params['question_id']) || !isset($params['answer_id']) || !isset($params['time_taken'])) {
                error_log('Missing required parameters for answer submission: ' . json_encode($params));
                return new \WP_Error(
                    'missing_parameters',
                    'Missing required parameters: question_id, answer_id, time_taken',
                    ['status' => 400]
                );
            }

            // For debugging purposes, bypass validation in test mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::debug('Bypassing answer validation in test mode', [
                    'session_id' => $session_id,
                    'question_id' => $params['question_id'],
                    'answer_id' => $params['answer_id']
                ]);
                
                $result = $this->quiz_manager->submit_answer(
                    $session_id,
                    $params['question_id'],
                    $params['answer_id'],
                    $params['time_taken']
                );
                
                return rest_ensure_response([
                    'success' => true,
                    'result' => $result
                ]);
            }

            // Normal validation for production
            $validation = $this->quiz_validator->validate_answer_submission(
                $session_id,
                $params['question_id'],
                $params['answer_id'],
                $params['time_taken']
            );

            if (!$validation['valid']) {
                error_log('Answer validation failed: ' . $validation['error']);
                return new \WP_Error(
                    'answer_validation_failed',
                    $validation['error'],
                    ['status' => 400]
                );
            }

            $result = $this->quiz_manager->submit_answer(
                $session_id,
                $params['question_id'],
                $params['answer_id'],
                $params['time_taken']
            );
            
            error_log('Answer submission successful: ' . json_encode($result));

            return rest_ensure_response([
                'success' => true,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            error_log('Answer submission exception: ' . $e->getMessage());
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

            // For debugging purposes, bypass validation in test mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                Logger::debug('Bypassing completion validation in test mode', [
                    'session_id' => $session_id
                ]);
                
                $results = $this->quiz_manager->complete_quiz($session_id);
                
                return rest_ensure_response([
                    'success' => true,
                    'results' => $results
                ]);
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
     * Clear quiz session
     */
    public function clear_session($request) {
        try {
            $session_id = $request->get_param('session_id');
            
            if (!$session_id) {
                return new \WP_Error(
                    'missing_session',
                    'Session ID is required',
                    ['status' => 400]
                );
            }
            
            // Delete the transient
            delete_transient('weebunz_quiz_session_' . $session_id);
            
            // Update the session status in database
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'quiz_sessions',
                [
                    'status' => 'expired',
                    'ended_at' => current_time('mysql')
                ],
                ['session_id' => $session_id]
            );
            
            return rest_ensure_response([
                'success' => true,
                'message' => 'Session cleared successfully'
            ]);
        } catch (\Exception $e) {
            Logger::error('Session clear failed', [
                'session_id' => $session_id ?? 'none',
                'error' => $e->getMessage()
            ]);
            
            return new \WP_Error(
                'session_clear_failed',
                'Failed to clear session',
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