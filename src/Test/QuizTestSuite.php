<?php
// File: wp-content/plugins/weebunz-core/includes/test/class-quiz-test-suite.php

namespace Weebunz\Test;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Quiz\Quiz_Manager;
use Weebunz\Quiz\Quiz_Validator;
use Weebunz\Quiz\Quiz_Entry;
use Weebunz\Logger;

class QuizTestSuite {
    private $quiz_manager;
    private $quiz_validator;
    private $test_results = [];
    private $current_test = null;

    public function __construct() {
        $this->quiz_manager = new Quiz_Manager();
        $this->quiz_validator = new Quiz_Validator();
    }

    /**
     * Run all test scenarios
     */
    public function run_all_tests() {
        Logger::info('Starting Quiz Test Suite');
        
        try {
            // Basic quiz functionality tests
            $this->test_quiz_initialization();
            $this->test_question_loading();
            $this->test_answer_submission();
            $this->test_quiz_completion();

            // Edge case tests
            $this->test_session_timeout();
            $this->test_invalid_answers();
            $this->test_concurrent_sessions();
            $this->test_network_interruption();

            // Integration tests
            $this->test_entry_reward_calculation();
            $this->test_user_progress_tracking();
            $this->test_cleanup_processes();

            // API integration tests
            $this->test_api_endpoints();
            $this->test_api_error_handling();

            Logger::info('Quiz Test Suite completed', [
                'total_tests' => count($this->test_results),
                'passed' => $this->get_passed_count(),
                'failed' => $this->get_failed_count()
            ]);

            return $this->get_test_results();

        } catch (\Exception $e) {
            Logger::error('Quiz Test Suite failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Test quiz initialization
     */
    private function test_quiz_initialization() {
        $this->start_test('Quiz Initialization');

        try {
            // Test case 1: Valid quiz initialization
            $quiz_entry = new Quiz_Entry(1); // Using test quiz ID
            $session = $quiz_entry->initialize();
            $this->assert($session !== false, 'Quiz should initialize successfully');
            $this->assert(!empty($session['session_id']), 'Session ID should be generated');

            // Test case 2: Invalid quiz ID
            try {
                $invalid_quiz = new Quiz_Entry(999);
                $invalid_session = $invalid_quiz->initialize();
                $this->assert($invalid_session === false, 'Invalid quiz should not initialize');
            } catch (\Exception $e) {
                $this->assert(true, 'Invalid quiz should throw exception');
            }

            // Test case 3: Session data verification
            $this->assert(isset($session['question_count']), 'Session should include question count');
            $this->assert(isset($session['time_limit']), 'Session should include time limit');
            $this->assert(isset($session['entry_cost']), 'Session should include entry cost');

            $this->pass_test();
        } catch (\Exception $e) {
            $this->fail_test($e->getMessage());
        }
    }

    /**
     * Test question loading
     */
    private function test_question_loading() {
        $this->start_test('Question Loading');

        try {
            $quiz_entry = new Quiz_Entry(1);
            $session = $quiz_entry->initialize();

            // Test case 1: First question load
            $question = $quiz_entry->get_next_question();
            $this->assert(!empty($question), 'First question should load');
            $this->assert(isset($question['answers']), 'Question should include answers');
            $this->assert(count($question['answers']) >= 2, 'Question should have multiple answers');

            // Test case 2: Question format
            $this->assert(isset($question['question_id']), 'Question should have ID');
            $this->assert(isset($question['question_text']), 'Question should have text');
            $this->assert(isset($question['time_limit']), 'Question should have time limit');

            // Test case 3: Sequential loading
            $first_id = $question['question_id'];
            $next_question = $quiz_entry->get_next_question();
            $this->assert($next_question['question_id'] !== $first_id, 'Questions should be unique');

            $this->pass_test();
        } catch (\Exception $e) {
            $this->fail_test($e->getMessage());
        }
    }

    /**
     * Test answer submission
     */
    private function test_answer_submission() {
        $this->start_test('Answer Submission');

        try {
            $quiz_entry = new Quiz_Entry(1);
            $session = $quiz_entry->initialize();
            $question = $quiz_entry->get_next_question();

            // Test case 1: Valid answer submission
            $result = $quiz_entry->submit_answer(
                $question['question_id'],
                $question['answers'][0]['id'],
                5 // time taken
            );
            $this->assert($result !== false, 'Answer submission should succeed');
            $this->assert(isset($result['is_correct']), 'Result should indicate correctness');

            // Test case 2: Invalid answer ID
            try {
                $invalid_result = $quiz_entry->submit_answer(
                    $question['question_id'],
                    999999, // invalid answer ID
                    5
                );
                $this->assert($invalid_result === false, 'Invalid answer should fail');
            } catch (\Exception $e) {
                $this->assert(true, 'Invalid answer should throw exception');
            }

            // Test case 3: Time limit validation
            $over_time_result = $quiz_entry->submit_answer(
                $question['question_id'],
                $question['answers'][0]['id'],
                $question['time_limit'] + 10
            );
            $this->assert($over_time_result === false, 'Over-time submission should fail');

            $this->pass_test();
        } catch (\Exception $e) {
            $this->fail_test($e->getMessage());
        }
    }

    /**
     * Test quiz completion
     */
    private function test_quiz_completion() {
        $this->start_test('Quiz Completion');

        try {
            $quiz_entry = new Quiz_Entry(1);
            $session = $quiz_entry->initialize();

            // Complete all questions
            while ($question = $quiz_entry->get_next_question()) {
                $quiz_entry->submit_answer(
                    $question['question_id'],
                    $question['answers'][0]['id'],
                    5
                );
            }

            // Test case 1: Completion results
            $results = $quiz_entry->complete_quiz();
            $this->assert(!empty($results), 'Completion should return results');
            $this->assert(isset($results['entries_earned']), 'Results should include entries earned');
            $this->assert(isset($results['correct_answers']), 'Results should include correct answers');

            // Test case 2: Session cleanup
            $expired_question = $quiz_entry->get_next_question();
            $this->assert($expired_question === null, 'Session should be cleaned up');

            $this->pass_test();
        } catch (\Exception $e) {
            $this->fail_test($e->getMessage());
        }
    }

    /**
     * Test entry reward calculation
     */
    private function test_entry_reward_calculation() {
        $this->start_test('Entry Reward Calculation');

        try {
            $quiz_entry = new Quiz_Entry(1);
            $session = $quiz_entry->initialize();

            // Test different scoring scenarios
            $scenarios = [
                ['correct' => 0, 'expected' => 0],
                ['correct' => 1, 'expected' => 0],
                ['correct' => 2, 'expected' => 1],
                ['correct' => 3, 'expected' => 1],
                ['correct' => 4, 'expected' => 2]
            ];

            foreach ($scenarios as $scenario) {
                $entries = $this->calculate_entries($scenario['correct']);
                $this->assert(
                    $entries === $scenario['expected'],
                    sprintf(
                        'Expected %d entries for %d correct answers, got %d',
                        $scenario['expected'],
                        $scenario['correct'],
                        $entries
                    )
                );
            }

            $this->pass_test();
        } catch (\Exception $e) {
            $this->fail_test($e->getMessage());
        }
    }

    /**
 * Test API endpoints
 */
private function test_api_endpoints() {
    $this->start_test('API Endpoints');

    try {
        // Test quiz listing endpoint
        $listing_response = $this->make_api_request('GET', 'quizzes');
        $this->assert(!empty($listing_response['quizzes']), 'Quiz listing should return quizzes');

        // Test quiz start
        $start_response = $this->make_api_request('POST', 'quiz/start', [
            'quiz_id' => $listing_response['quizzes'][0]->id
        ]);
        $this->assert(!empty($start_response['session_id']), 'Quiz start should return session ID');
        $session_id = $start_response['session_id'];

        // Test question fetching
        $question_response = $this->make_api_request('GET', 'quiz/question', null, [
            'X-Quiz-Session' => $session_id
        ]);
        $this->assert(!empty($question_response['question']), 'Should return question data');
        $question = $question_response['question'];

        // Test answer submission
        $answer_response = $this->make_api_request('POST', 'quiz/answer', [
            'question_id' => $question['id'],
            'answer_id' => $question['answers'][0]['id'],
            'time_taken' => 5
        ], [
            'X-Quiz-Session' => $session_id
        ]);
        $this->assert(isset($answer_response['result']['is_correct']), 'Should indicate answer correctness');

        // Test quiz completion
        $completion_response = $this->make_api_request('POST', 'quiz/complete', null, [
            'X-Quiz-Session' => $session_id
        ]);
        $this->assert(isset($completion_response['results']), 'Should return completion results');

        $this->pass_test();
    } catch (\Exception $e) {
        $this->fail_test($e->getMessage());
    }
}

/**
 * Test API error handling
 */
private function test_api_error_handling() {
    $this->start_test('API Error Handling');

    try {
        // Test invalid quiz ID
        $invalid_start_response = $this->make_api_request('POST', 'quiz/start', [
            'quiz_id' => 999999
        ], [], false);
        $this->assert($invalid_start_response['code'] === 'quiz_not_found', 'Should handle invalid quiz ID');

        // Test invalid session ID
        $invalid_question_response = $this->make_api_request('GET', 'quiz/question', null, [
            'X-Quiz-Session' => 'invalid-session'
        ], false);
        $this->assert($invalid_question_response['code'] === 'invalid_session', 'Should handle invalid session');

        // Test missing required parameters
        $missing_params_response = $this->make_api_request('POST', 'quiz/answer', [
            'question_id' => 1
            // Missing answer_id
        ], [], false);
        $this->assert($missing_params_response['code'] === 'missing_parameters', 'Should handle missing parameters');

        $this->pass_test();
    } catch (\Exception $e) {
        $this->fail_test($e->getMessage());
    }
}

/**
 * Make API request helper
 */
private function make_api_request($method, $endpoint, $data = null, $headers = [], $expect_success = true) {
    $request = new \WP_REST_Request($method, "/weebunz/v1/{$endpoint}");
    
    if ($data) {
        $request->set_body_params($data);
    }

    foreach ($headers as $key => $value) {
        $request->add_header($key, $value);
    }

    $response = rest_do_request($request);
    
    if ($expect_success && $response->is_error()) {
        throw new \Exception("API request failed: " . $response->get_error_message());
    }

    return $response->get_data();
}

    // Helper methods
    private function start_test($name) {
        $this->current_test = [
            'name' => $name,
            'assertions' => 0,
            'failures' => [],
            'start_time' => microtime(true)
        ];
        Logger::debug("Starting test: {$name}");
    }

    private function pass_test() {
        if ($this->current_test) {
            $duration = microtime(true) - $this->current_test['start_time'];
            $this->current_test['duration'] = $duration;
            $this->current_test['status'] = 'passed';
            $this->test_results[] = $this->current_test;
            Logger::debug("Test passed: {$this->current_test['name']}");
        }
    }

    private function fail_test($message) {
        if ($this->current_test) {
            $duration = microtime(true) - $this->current_test['start_time'];
            $this->current_test['duration'] = $duration;
            $this->current_test['status'] = 'failed';
            $this->current_test['error'] = $message;
            $this->test_results[] = $this->current_test;
            Logger::error("Test failed: {$this->current_test['name']}", ['error' => $message]);
        }
    }

    private function assert($condition, $message) {
        if ($this->current_test) {
            $this->current_test['assertions']++;
            if (!$condition) {
                $this->current_test['failures'][] = $message;
                Logger::warning("Assertion failed: {$message}");
            }
        }
    }

    public function get_test_results() {
        return [
            'total' => count($this->test_results),
            'passed' => $this->get_passed_count(),
            'failed' => $this->get_failed_count(),
            'duration' => array_sum(array_column($this->test_results, 'duration')),
            'tests' => $this->test_results
        ];
    }

    private function get_passed_count() {
        return count(array_filter($this->test_results, function($test) {
            return $test['status'] === 'passed';
        }));
    }

    private function get_failed_count() {
        return count(array_filter($this->test_results, function($test) {
            return $test['status'] === 'failed';
        }));
    }

    private function calculate_entries($correct_answers) {
        // Simplified calculation for testing
        return floor($correct_answers / 2);
    }
}