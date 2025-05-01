<?php
/**
 * Admin interface for load testing tool
 *
 * This file provides an admin interface for the load testing tool
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get the load testing tool
require_once plugin_dir_path(dirname(dirname(__FILE__))) . 'includes/deployment/class-load-testing-tool.php';
$load_testing_tool = \Weebunz\Deployment\Load_Testing_Tool::get_instance();

// Handle form submission
if (isset($_POST['weebunz_start_load_test'])) {
    check_admin_referer('weebunz_load_test_nonce');
    
    $concurrent_users = intval($_POST['concurrent_users']);
    $test_duration = intval($_POST['test_duration']);
    
    $result = $load_testing_tool->start_test($concurrent_users, $test_duration);
    
    if ($result['success']) {
        echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
    }
}

// Get all test results
$test_results = $load_testing_tool->get_all_test_results();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>Load Testing Tool</h2>
        <p>Use this tool to simulate concurrent users and test the performance of your quiz system.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('weebunz_load_test_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="concurrent_users">Concurrent Users</label></th>
                    <td>
                        <input type="number" name="concurrent_users" id="concurrent_users" value="100" min="1" max="1000" step="1" class="regular-text">
                        <p class="description">Number of concurrent users to simulate</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="test_duration">Test Duration (seconds)</label></th>
                    <td>
                        <input type="number" name="test_duration" id="test_duration" value="60" min="10" max="300" step="1" class="regular-text">
                        <p class="description">Duration of the test in seconds</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="weebunz_start_load_test" id="weebunz_start_load_test" class="button button-primary" value="Start Load Test">
            </p>
        </form>
    </div>
    
    <?php if (!empty($test_results)) : ?>
    <div class="card">
        <h2>Test Results</h2>
        
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Test ID</th>
                    <th>Date</th>
                    <th>Users</th>
                    <th>Duration</th>
                    <th>Requests</th>
                    <th>Success Rate</th>
                    <th>Avg Response Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($test_results as $test_id => $result) : ?>
                <tr>
                    <td><?php echo esc_html($test_id); ?></td>
                    <td><?php echo esc_html(date('Y-m-d H:i:s', $result['start_time'])); ?></td>
                    <td><?php echo esc_html($result['concurrent_users']); ?></td>
                    <td><?php echo esc_html($result['test_duration']); ?> sec</td>
                    <td><?php echo esc_html($result['requests']); ?></td>
                    <td>
                        <?php 
                        if ($result['requests'] > 0) {
                            $success_rate = ($result['successful_requests'] / $result['requests']) * 100;
                            echo esc_html(round($success_rate, 2)) . '%';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if (isset($result['avg_response_time'])) {
                            echo esc_html(round($result['avg_response_time'], 3)) . ' sec';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html($result['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>Performance Recommendations</h2>
        
        <div class="weebunz-performance-recommendations">
            <h3>Current Configuration</h3>
            <ul>
                <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                <li><strong>MySQL Version:</strong> <?php global $wpdb; echo $wpdb->get_var('SELECT VERSION()'); ?></li>
                <li><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></li>
                <li><strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds</li>
                <li><strong>Redis Available:</strong> <?php echo class_exists('Redis') ? 'Yes' : 'No'; ?></li>
                <li><strong>Object Cache Enabled:</strong> <?php echo (function_exists('wp_cache_add') && defined('WP_CACHE') && WP_CACHE) ? 'Yes' : 'No'; ?></li>
            </ul>
            
            <h3>Recommendations for Kinsta Hosting</h3>
            <ol>
                <li>Enable Redis cache for improved performance with concurrent users</li>
                <li>Use a PHP version of 7.4 or higher for best performance</li>
                <li>Configure the object cache to use Redis</li>
                <li>Enable Kinsta CDN for static assets</li>
                <li>Set up database indexes as provided in the optimization scripts</li>
                <li>Monitor Redis cache usage through Kinsta's dashboard</li>
                <li>Consider increasing PHP memory limit if handling very large concurrent loads</li>
            </ol>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Add AJAX functionality to update test status in real-time
    // This would be implemented in a real production environment
});
</script>
