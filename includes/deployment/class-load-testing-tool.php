<?php
/**
 * Load Testing Tool for WeeBunz Quiz Engine
 *
 * This file provides a load testing tool to simulate concurrent users
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/deployment
 */

namespace Weebunz\Deployment;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load Testing Tool
 * 
 * Simulates concurrent users for testing
 */
class Load_Testing_Tool {
    private static $instance = null;
    private $results = [];
    private $test_running = false;
    private $concurrent_users = 0;
    private $test_duration = 0;
    private $start_time = 0;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Initialize load testing tool
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Start load test
     */
    public function start_test($concurrent_users = 100, $test_duration = 60) {
        if ($this->test_running) {
            return [
                'success' => false,
                'message' => 'A test is already running'
            ];
        }
        
        $this->concurrent_users = $concurrent_users;
        $this->test_duration = $test_duration;
        $this->test_running = true;
        $this->start_time = time();
        $this->results = [
            'start_time' => $this->start_time,
            'concurrent_users' => $concurrent_users,
            'test_duration' => $test_duration,
            'requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'response_times' => [],
            'errors' => [],
            'status' => 'running'
        ];
        
        // Schedule test execution
        wp_schedule_single_event(time(), 'weebunz_run_load_test', [$concurrent_users, $test_duration]);
        
        return [
            'success' => true,
            'message' => "Load test started with $concurrent_users concurrent users for $test_duration seconds",
            'test_id' => $this->start_time
        ];
    }
    
    /**
     * Run load test
     */
    public function run_test($concurrent_users, $test_duration) {
        // This would be called by the WordPress cron
        // In a real implementation, this would spawn multiple processes or use a job queue
        
        // Simulate concurrent users
        for ($i = 0; $i < $concurrent_users; $i++) {
            // Spawn a new process for each user
            $this->spawn_user_process($i);
        }
        
        // Wait for test duration
        sleep($test_duration);
        
        // Collect results
        $this->collect_results();
        
        $this->test_running = false;
        $this->results['status'] = 'completed';
        $this->results['end_time'] = time();
        $this->results['actual_duration'] = $this->results['end_time'] - $this->results['start_time'];
        
        // Calculate statistics
        if (count($this->results['response_times']) > 0) {
            $this->results['avg_response_time'] = array_sum($this->results['response_times']) / count($this->results['response_times']);
            $this->results['min_response_time'] = min($this->results['response_times']);
            $this->results['max_response_time'] = max($this->results['response_times']);
        }
        
        // Store results
        update_option('weebunz_load_test_results_' . $this->start_time, $this->results);
        
        return $this->results;
    }
    
    /**
     * Spawn user process
     */
    private function spawn_user_process($user_index) {
        // In a real implementation, this would create a separate process
        // For this demo, we'll simulate it
        
        // Get a random quiz
        global $wpdb;
        $quiz = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}active_quizzes WHERE status = 'active' ORDER BY RAND() LIMIT 1");
        
        if (!$quiz) {
            $this->results['errors'][] = "No active quiz found for user $user_index";
            $this->results['failed_requests']++;
            return;
        }
        
        // Simulate quiz session
        $start_time = microtime(true);
        
        try {
            // Simulate API calls
            $this->simulate_api_call('start_quiz', ['quiz_id' => $quiz->id]);
            $this->results['requests']++;
            
            // Get questions
            $questions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}questions_pool LIMIT 10"
            ));
            
            if (!$questions) {
                throw new \Exception("No questions found for quiz");
            }
            
            // Answer each question
            foreach ($questions as $question) {
                // Get answers
                $answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}question_answers WHERE question_id = %d",
                    $question->id
                ));
                
                if (!$answers) {
                    throw new \Exception("No answers found for question {$question->id}");
                }
                
                // Select a random answer
                $answer = $answers[array_rand($answers)];
                
                // Submit answer
                $this->simulate_api_call('submit_answer', [
                    'question_id' => $question->id,
                    'answer_id' => $answer->id
                ]);
                $this->results['requests']++;
                
                // Simulate thinking time
                usleep(rand(500000, 2000000)); // 0.5-2 seconds
            }
            
            // Complete quiz
            $this->simulate_api_call('complete_quiz', ['quiz_id' => $quiz->id]);
            $this->results['requests']++;
            $this->results['successful_requests']++;
            
            // Record response time
            $response_time = microtime(true) - $start_time;
            $this->results['response_times'][] = $response_time;
            
        } catch (\Exception $e) {
            $this->results['errors'][] = "User $user_index: " . $e->getMessage();
            $this->results['failed_requests']++;
        }
    }
    
    /**
     * Simulate API call
     */
    private function simulate_api_call($endpoint, $data) {
        // In a real implementation, this would make an actual API call
        // For this demo, we'll simulate it
        
        // Simulate network latency
        usleep(rand(50000, 200000)); // 50-200ms
        
        // Simulate server processing time
        usleep(rand(100000, 500000)); // 100-500ms
        
        // Simulate random errors (5% chance)
        if (rand(1, 100) <= 5) {
            throw new \Exception("Simulated error in $endpoint API call");
        }
        
        return [
            'success' => true,
            'data' => ['message' => "Simulated $endpoint response"]
        ];
    }
    
    /**
     * Collect results
     */
    private function collect_results() {
        // In a real implementation, this would collect results from all processes
        // For this demo, results are already collected in the spawn_user_process method
    }
    
    /**
     * Get test status
     */
    public function get_test_status($test_id) {
        if ($this->test_running && $this->start_time == $test_id) {
            $elapsed = time() - $this->start_time;
            $progress = min(100, round(($elapsed / $this->test_duration) * 100));
            
            return [
                'status' => 'running',
                'progress' => $progress,
                'elapsed' => $elapsed,
                'remaining' => max(0, $this->test_duration - $elapsed)
            ];
        }
        
        // Check for stored results
        $results = get_option('weebunz_load_test_results_' . $test_id);
        
        if ($results) {
            return [
                'status' => $results['status'],
                'results' => $results
            ];
        }
        
        return [
            'status' => 'not_found',
            'message' => 'Test not found'
        ];
    }
    
    /**
     * Get all test results
     */
    public function get_all_test_results() {
        global $wpdb;
        
        $results = [];
        $options = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} 
            WHERE option_name LIKE 'weebunz_load_test_results_%' 
            ORDER BY option_name DESC"
        );
        
        foreach ($options as $option) {
            $test_id = str_replace('weebunz_load_test_results_', '', $option->option_name);
            $results[$test_id] = maybe_unserialize($option->option_value);
        }
        
        return $results;
    }
    
    /**
     * Clear test results
     */
    public function clear_test_results($test_id = null) {
        if ($test_id) {
            delete_option('weebunz_load_test_results_' . $test_id);
            return true;
        }
        
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE 'weebunz_load_test_results_%'"
        );
        
        return true;
    }
}
