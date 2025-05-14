<?php
// Save as: wp-content/plugins/weebunz-core/includes/test/class-weebunz-tester.php

namespace Weebunz\Test;

if (!defined('ABSPATH')) {
    exit;
}

class WeebunzTester {
    private $wpdb;
    private $results = [];
    private $expected_tables = [
        'quiz_categories',
        'quiz_tags',
        'quiz_types',
        'questions_pool',
        'question_answers',
        'winner_questions_pool',
        'active_quizzes',
        'quiz_tag_relations',
        'raffle_events',
        'raffle_entries',
        'raffle_draws',
        'platinum_memberships',
        'mega_quiz_events'
    ];

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Run all tests
     */
    public function run_all_tests() {
        $this->test_database_structure();
        $this->test_test_data();
        $this->test_file_system();
        return $this->get_results();
    }

    /**
     * Test database structure
     */
    private function test_database_structure() {
        // Check tables exist
        foreach ($this->expected_tables as $table) {
            $table_name = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            
            $this->results["table_{$table}"] = [
                'test' => "Table {$table} exists",
                'status' => $exists === $table_name ? 'pass' : 'fail'
            ];
        }

        // Check foreign keys
        $this->check_foreign_key('question_answers', 'question_id', 'questions_pool', 'id');
        $this->check_foreign_key('quiz_tag_relations', 'quiz_id', 'active_quizzes', 'id');
        $this->check_foreign_key('quiz_tag_relations', 'tag_id', 'quiz_tags', 'id');
    }

    /**
     * Test test data insertion
     */
    private function test_test_data() {
        // Check test users
        $test_users = get_users([
            'search' => '*@example.com',
            'search_columns' => ['user_email']
        ]);

        $this->results['test_users'] = [
            'test' => 'Test users created',
            'status' => count($test_users) === 2 ? 'pass' : 'fail',
            'found' => count($test_users)
        ];

        // Check quiz data
        $counts = [
            'quiz_categories' => 6,
            'quiz_types' => 3,
            'questions_pool' => 9,
            'question_answers' => 36,
            'winner_questions_pool' => 4
        ];

        foreach ($counts as $table => $expected) {
            $actual = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}{$table}"
            );

            $this->results["data_{$table}"] = [
                'test' => "Data in {$table}",
                'status' => intval($actual) === $expected ? 'pass' : 'fail',
                'expected' => $expected,
                'actual' => intval($actual)
            ];
        }

        // Check relationships
        $orphaned_answers = $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->wpdb->prefix}question_answers qa
            LEFT JOIN {$this->wpdb->prefix}questions_pool qp ON qa.question_id = qp.id
            WHERE qp.id IS NULL
        ");

        $this->results['answer_relationships'] = [
            'test' => 'Question-Answer relationships',
            'status' => $orphaned_answers === '0' ? 'pass' : 'fail',
            'orphaned' => intval($orphaned_answers)
        ];
    }

    /**
     * Test file system setup
     */
    private function test_file_system() {
        $upload_dir = wp_upload_dir();
        $weebunz_dir = $upload_dir['basedir'] . '/weebunz';
        
        $directories = [
            'main' => $weebunz_dir,
            'temp' => $weebunz_dir . '/temp',
            'exports' => $weebunz_dir . '/exports'
        ];

        foreach ($directories as $key => $dir) {
            $exists = file_exists($dir);
            $writable = $exists && is_writable($dir);
            $permissions = $exists ? substr(sprintf('%o', fileperms($dir)), -4) : 'none';

            $this->results["dir_{$key}"] = [
                'test' => "Directory {$key}",
                'status' => ($exists && $writable) ? 'pass' : 'fail',
                'exists' => $exists,
                'writable' => $writable,
                'permissions' => $permissions
            ];
        }
    }

    /**
     * Check foreign key relationship
     */
    private function check_foreign_key($table, $column, $ref_table, $ref_column) {
        $orphaned = $this->wpdb->get_var("
            SELECT COUNT(*) FROM {$this->wpdb->prefix}{$table} t
            LEFT JOIN {$this->wpdb->prefix}{$ref_table} r ON t.{$column} = r.{$ref_column}
            WHERE r.{$ref_column} IS NULL
        ");

        $this->results["fk_{$table}_{$column}"] = [
            'test' => "Foreign key {$table}.{$column}",
            'status' => $orphaned === '0' ? 'pass' : 'fail',
            'orphaned' => intval($orphaned)
        ];
    }

    /**
     * Get formatted results
     */
    public function get_results() {
        $total = count($this->results);
        $passed = count(array_filter($this->results, function($r) { 
            return $r['status'] === 'pass'; 
        }));

        return [
            'summary' => [
                'total' => $total,
                'passed' => $passed,
                'failed' => $total - $passed
            ],
            'tests' => $this->results
        ];
    }

    /**
     * Get HTML report
     */
    public function get_html_report() {
        $results = $this->get_results();
        
        $html = '<div class="wrap">';
        $html .= '<h1>WeeBunz Core Test Results</h1>';
        
        // Summary
        $html .= sprintf(
            '<div class="notice %s"><p>Tests Run: %d, Passed: %d, Failed: %d</p></div>',
            $results['summary']['failed'] === 0 ? 'notice-success' : 'notice-error',
            $results['summary']['total'],
            $results['summary']['passed'],
            $results['summary']['failed']
        );

        // Detailed results
        $html .= '<table class="widefat">';
        $html .= '<thead><tr><th>Test</th><th>Status</th><th>Details</th></tr></thead><tbody>';
        
        foreach ($results['tests'] as $test) {
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($test['test']),
                $test['status'] === 'pass' 
                    ? '<span style="color:green">✓</span>' 
                    : '<span style="color:red">✗</span>',
                $this->format_test_details($test)
            );
        }
        
        $html .= '</tbody></table></div>';
        
        return $html;
    }

    /**
     * Format test details
     */
    private function format_test_details($test) {
        $details = [];
        foreach ($test as $key => $value) {
            if ($key !== 'test' && $key !== 'status') {
                $details[] = sprintf('%s: %s', $key, $value);
            }
        }
        return implode(', ', $details);
    }
}