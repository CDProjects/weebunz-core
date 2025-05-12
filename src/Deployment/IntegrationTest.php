<?php
/**
 * Integration test script for WeeBunz Quiz Engine
 *
 * This file provides a test script to verify the quiz engine functionality
 * and test concurrent user handling
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/deployment
 */

namespace Weebunz\Deployment;

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
}

require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-load.php';

// Include the Load Testing Tool
require_once plugin_dir_path(__FILE__) . 'class-load-testing-tool.php';

/**
 * Run integration tests
 */
function run_integration_tests() {
    echo "Starting WeeBunz Quiz Engine Integration Tests\n";
    echo "=============================================\n\n";
    
    // Test database connection
    test_database_connection();
    
    // Test Redis connection if available
    test_redis_connection();
    
    // Test quiz functionality
    test_quiz_functionality();
    
    // Test concurrent user handling
    test_concurrent_users();
    
    echo "\nAll tests completed.\n";
}

/**
 * Test database connection
 */
function test_database_connection() {
    global $wpdb;
    
    echo "Testing database connection... ";
    
    try {
        $result = $wpdb->get_var("SELECT 1");
        
        if ($result === '1') {
            echo "SUCCESS\n";
            
            // Check for required tables
            $tables = [
                $wpdb->prefix . 'quiz_sessions',
                $wpdb->prefix . 'questions_pool',
                $wpdb->prefix . 'question_answers',
                $wpdb->prefix . 'active_quizzes'
            ];
            
            echo "Checking required tables:\n";
            
            foreach ($tables as $table) {
                echo "  - $table: ";
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
                echo $table_exists ? "EXISTS\n" : "MISSING\n";
                
                if (!$table_exists) {
                    echo "    WARNING: Required table $table is missing. Please run the database setup.\n";
                }
            }
        } else {
            echo "FAILED\n";
            echo "  Error: Unexpected result from database test query.\n";
        }
    } catch (\Exception $e) {
        echo "FAILED\n";
        echo "  Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Test Redis connection
 */
function test_redis_connection() {
    echo "Testing Redis connection... ";
    
    if (!class_exists('Redis')) {
        echo "SKIPPED (Redis extension not installed)\n\n";
        return;
    }
    
    try {
        $redis = new \Redis();
        
        // Get Redis host and port from constants or environment
        $host = defined('REDIS_HOST') ? REDIS_HOST : (defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1');
        $port = defined('REDIS_PORT') ? REDIS_PORT : (defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379);
        
        if ($redis->connect($host, $port, 2)) {
            echo "SUCCESS\n";
            
            // Test Redis functionality
            $test_key = 'weebunz_test_' . time();
            $test_value = 'test_value_' . time();
            
            $redis->set($test_key, $test_value, 60);
            $retrieved_value = $redis->get($test_key);
            
            echo "  - Redis set/get test: ";
            echo ($retrieved_value === $test_value) ? "SUCCESS\n" : "FAILED\n";
            
            // Clean up
            $redis->del($test_key);
        } else {
            echo "FAILED\n";
            echo "  Error: Could not connect to Redis server at $host:$port\n";
        }
    } catch (\Exception $e) {
        echo "FAILED\n";
        echo "  Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Test quiz functionality
 */
function test_quiz_functionality() {
    global $wpdb;
    
    echo "Testing quiz functionality...\n";
    
    // Check for active quizzes
    $active_quizzes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}active_quizzes WHERE status = 'active'");
    
    echo "  - Active quizzes found: " . count($active_quizzes) . "\n";
    
    if (empty($active_quizzes)) {
        echo "    WARNING: No active quizzes found. Please create at least one active quiz.\n";
        return;
    }
    
    // Test quiz session creation
    $quiz = $active_quizzes[0];
    echo "  - Testing quiz session creation for quiz ID {$quiz->id}... ";
    
    try {
        // This would normally be handled by the API, but we'll simulate it here
        $session_id = wp_generate_uuid4();
        $user_id = get_current_user_id();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'quiz_sessions',
            [
                'session_id' => $session_id,
                'quiz_id' => $quiz->id,
                'user_id' => $user_id,
                'session_data' => serialize([
                    'quiz_id' => $quiz->id,
                    'user_id' => $user_id,
                    'current_question' => 0,
                    'answers' => []
                ]),
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600)
            ]
        );
        
        if ($result) {
            echo "SUCCESS\n";
            
            // Get questions for the quiz
            $questions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}questions_pool LIMIT 10");
            
            echo "  - Questions available: " . count($questions) . "\n";
            
            if (!empty($questions)) {
                $question = $questions[0];
                
                // Get answers for the question
                $answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}question_answers WHERE question_id = %d",
                    $question->id
                ));
                
                echo "  - Answers available for question {$question->id}: " . count($answers) . "\n";
                
                if (!empty($answers)) {
                    // Test answer submission
                    echo "  - Testing answer submission... ";
                    
                    // Find the correct answer
                    $correct_answer = null;
                    foreach ($answers as $answer) {
                        if ($answer->is_correct) {
                            $correct_answer = $answer;
                            break;
                        }
                    }
                    
                    if ($correct_answer) {
                        // Update session data
                        $session_data = [
                            'quiz_id' => $quiz->id,
                            'user_id' => $user_id,
                            'current_question' => 1,
                            'answers' => [
                                [
                                    'question_id' => $question->id,
                                    'answer_id' => $correct_answer->id,
                                    'is_correct' => true,
                                    'time_taken' => 5
                                ]
                            ]
                        ];
                        
                        $result = $wpdb->update(
                            $wpdb->prefix . 'quiz_sessions',
                            [
                                'session_data' => serialize($session_data),
                                'updated_at' => current_time('mysql')
                            ],
                            ['session_id' => $session_id]
                        );
                        
                        if ($result !== false) {
                            echo "SUCCESS\n";
                        } else {
                            echo "FAILED\n";
                            echo "    Error: " . $wpdb->last_error . "\n";
                        }
                    } else {
                        echo "SKIPPED (No correct answer found)\n";
                    }
                }
            }
            
            // Clean up
            $wpdb->delete($wpdb->prefix . 'quiz_sessions', ['session_id' => $session_id]);
        } else {
            echo "FAILED\n";
            echo "    Error: " . $wpdb->last_error . "\n";
        }
    } catch (\Exception $e) {
        echo "FAILED\n";
        echo "    Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

/**
 * Test concurrent users
 */
function test_concurrent_users() {
    echo "Testing concurrent user handling...\n";
    
    // Get the load testing tool
    $load_testing_tool = Load_Testing_Tool::get_instance();
    
    // Start a small load test
    $concurrent_users = 10;
    $test_duration = 10;
    
    echo "  - Starting load test with $concurrent_users concurrent users for $test_duration seconds...\n";
    
    $result = $load_testing_tool->start_test($concurrent_users, $test_duration);
    
    if ($result['success']) {
        echo "    Test started successfully (Test ID: {$result['test_id']})\n";
        
        // Wait for test to complete
        echo "    Waiting for test to complete...\n";
        
        $start_time = time();
        $status = null;
        
        while (time() - $start_time < $test_duration + 5) {
            $status = $load_testing_tool->get_test_status($result['test_id']);
            
            if ($status['status'] === 'completed') {
                break;
            }
            
            sleep(1);
        }
        
        if ($status && $status['status'] === 'completed') {
            echo "    Test completed successfully.\n";
            
            // Display results
            $results = $status['results'];
            
            echo "    Results:\n";
            echo "      - Requests: {$results['requests']}\n";
            echo "      - Successful requests: {$results['successful_requests']}\n";
            echo "      - Failed requests: {$results['failed_requests']}\n";
            
            if (isset($results['avg_response_time'])) {
                echo "      - Average response time: " . round($results['avg_response_time'], 3) . " seconds\n";
            }
            
            // Calculate success rate
            if ($results['requests'] > 0) {
                $success_rate = ($results['successful_requests'] / $results['requests']) * 100;
                echo "      - Success rate: " . round($success_rate, 2) . "%\n";
                
                if ($success_rate >= 95) {
                    echo "    PASSED: Success rate is above 95%\n";
                } else {
                    echo "    WARNING: Success rate is below 95%\n";
                }
            }
        } else {
            echo "    Test did not complete within expected time.\n";
        }
    } else {
        echo "    Failed to start load test: {$result['message']}\n";
    }
    
    echo "\n";
}

// Run the tests if this file is executed directly
if (isset($argv) && basename($argv[0]) === basename(__FILE__)) {
    run_integration_tests();
}
