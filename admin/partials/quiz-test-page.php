<?php
// File: wp-content/plugins/weebunz-core/admin/partials/quiz-test-page.php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Verify WP_DEBUG is enabled
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    wp_die('This test page is only available when WP_DEBUG is enabled.');
}

global $wpdb;

// Enqueue required scripts
wp_enqueue_script('jquery');
wp_enqueue_script(
    'weebunz-quiz-test',
    WEEBUNZ_PLUGIN_URL . 'admin/js/quiz-test.js',
    array('jquery'),
    WEEBUNZ_VERSION,
    true
);

// Update the script localization:
wp_localize_script('weebunz-quiz-test', 'weebunzTest', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wp_rest'),
    'apiEndpoint' => rest_url('weebunz/v1'),
    'debug' => WP_DEBUG
));

// Get quiz types with question counts
$quiz_types = $wpdb->get_results("
    SELECT qt.*, COUNT(qp.id) as question_count 
    FROM {$wpdb->prefix}quiz_types qt
    LEFT JOIN {$wpdb->prefix}questions_pool qp ON qp.difficulty_level = qt.difficulty_level
    GROUP BY qt.id
    ORDER BY qt.name
");
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="notice notice-info">
        <p>This page provides a testing interface for the quiz engine. All operations here bypass payment requirements and provide detailed debugging information.</p>
    </div>

    <div class="quiz-test-container">
        <!-- Test Configuration -->
        <div class="card quiz-test-config">
            <h2>Quiz Configuration</h2>
            
            <div class="quiz-type-selection">
                <label for="quiz_type"><strong>Quiz Type:</strong></label>
                <select id="quiz_type" class="regular-text">
                    <option value="">Select a quiz type...</option>
                    <?php foreach ($quiz_types as $type): ?>
                        <option value="<?php echo esc_attr($type->id); ?>" 
                                data-difficulty="<?php echo esc_attr($type->difficulty_level); ?>"
                                data-questions="<?php echo esc_attr($type->question_count); ?>"
                                data-time="<?php echo esc_attr($type->time_limit); ?>">
                            <?php 
                            echo esc_html(sprintf(
                                '%s (%s) - %d questions available',
                                $type->name,
                                ucfirst($type->difficulty_level),
                                $type->question_count
                            )); 
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="quiz-type-details" class="hidden mt-3">
                <p><strong>Questions:</strong> <span id="question-count">-</span></p>
                <p><strong>Time Limit:</strong> <span id="time-limit">-</span> seconds per question</p>
            </div>

            <div class="test-options mt-3">
                <label><input type="checkbox" id="debug-mode" checked> Enable Detailed Logging</label>
                <label><input type="checkbox" id="simulate-lag"> Simulate Network Lag</label>
                <label><input type="checkbox" id="force-errors"> Force Random Errors</label>
                <label><input type="checkbox" id="bypass-timer"> Bypass Question Timer</label>
            </div>

            <div class="quiz-actions mt-3">
                <button id="start-quiz" class="button button-primary" disabled="disabled">Start Quiz</button>
                <button id="reset-quiz" class="button" disabled>Reset Quiz</button>
                <button id="test-api" class="button">Test API Connection</button>
            </div>
        </div>

        <!-- Quiz Interface -->
        <div id="quiz-interface" class="card quiz-interface hidden">
            <h2>Quiz Player</h2>
            <div id="quiz-mount-point"></div>
            <div id="quiz-display"></div>
            <div class="timer-display"></div>
        </div>

        <!-- Test Controls -->
        <div class="card quiz-test-controls hidden">
            <h2>Test Controls</h2>
            
            <div class="control-buttons">
                <button id="skip-question" class="button">Skip Question</button>
                <button id="force-timeout" class="button">Force Timeout</button>
                <button id="simulate-disconnect" class="button">Simulate Disconnect</button>
                <button id="clear-session" class="button">Clear Session</button>
            </div>

            <div class="session-info mt-3">
                <h3>Session Information</h3>
                <pre id="session-data">No active session</pre>
            </div>
        </div>

        <!-- Debug Output -->
        <div class="card quiz-debug">
            <h2>
                Debug Output 
                <button id="clear-log" class="button button-small">Clear Log</button>
                <button id="export-log" class="button button-small">Export Log</button>
            </h2>
            <div id="debug-log" class="debug-log"></div>
        </div>
    </div>
    <script>
        jQuery(document).ready(function($) {
            console.log('Document ready handler executing');
    
            // Test the select element exists
            const $quizType = $('#quiz_type');
            console.log('Quiz type select found:', $quizType.length > 0);
    
            // Add a direct event handler
            $quizType.on('change', function() {
                console.log('Direct change event triggered');
                console.log('Selected value:', $(this).val());
                console.log('Selected option data:', {
                    difficulty: $(this).find(':selected').data('difficulty'),
                    questions: $(this).find(':selected').data('questions'),
                    time: $(this).find(':selected').data('time')
                });
            });
        });
    </script>
</div>

<style>
.quiz-test-container {
    max-width: 1200px;
}

.quiz-test-container .card {
    margin-top: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.quiz-interface {
    background: #f8f9fa;
    min-height: 400px;
    position: relative;
}

.test-options label {
    display: block;
    margin-bottom: 8px;
}

.control-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.debug-log {
    background: #1e1e1e;
    color: #00ff00;
    font-family: monospace;
    padding: 15px;
    border-radius: 4px;
    height: 300px;
    overflow-y: auto;
}

.debug-log .error { color: #ff4444; }
.debug-log .warning { color: #ffbb33; }
.debug-log .success { color: #00C851; }
.debug-log .info { color: #33b5e5; }

.mt-3 { margin-top: 15px; }

.quiz-type-selection {
    max-width: 600px;
}

.session-info pre {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
    max-height: 200px;
}

.question-container {
    padding: 20px;
    background: white;
    border-radius: 4px;
    margin: 20px 0;
}

.timer-display {
    font-size: 1.2em;
    font-weight: bold;
    text-align: center;
    margin: 10px 0;
}

.answer-feedback {
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
    text-align: center;
}

.answer-feedback.correct {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.answer-feedback.incorrect {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.hidden {
    display: none;
}

.loading {
    opacity: 0.5;
    pointer-events: none;
}

.loading:after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 30px;
    height: 30px;
    margin: -15px 0 0 -15px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>