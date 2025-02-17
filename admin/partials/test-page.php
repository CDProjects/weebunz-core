<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/test-page.php

// Security check and error reporting
if (!defined('ABSPATH')) {
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get the correct plugin root directory
    $plugin_root = dirname(dirname(dirname(__FILE__))); 
    $test_dir = $plugin_root . '/includes/test';
    $test_file = $test_dir . '/class-weebunz-tester.php';

    // Debug information
    $debug_info = array(
        'Plugin Dir' => WEEBUNZ_PLUGIN_DIR,
        'Plugin Root' => $plugin_root,
        'Test Dir' => $test_dir,
        'Full Path' => $test_file,
        'File Exists' => file_exists($test_file) ? 'Yes' : 'No',
        'Is Readable' => is_readable($test_file) ? 'Yes' : 'No',
        'File Permissions' => file_exists($test_file) ? substr(sprintf('%o', fileperms($test_file)), -4) : 'N/A',
        'Current File' => __FILE__,
        'Includes Dir Exists' => is_dir($plugin_root . '/includes') ? 'Yes' : 'No',
        'Test Dir Exists' => is_dir($test_dir) ? 'Yes' : 'No'
    );

    // Add directory contents to debug info
    if (is_dir($plugin_root . '/includes')) {
        $debug_info['Parent Dir Contents'] = print_r(scandir($plugin_root . '/includes'), true);
    }
    if (is_dir($test_dir)) {
        $debug_info['Test Dir Contents'] = print_r(scandir($test_dir), true);
    }

    // Display debug information
    echo '<div class="wrap">';
    echo '<h1>WeeBunz Core Tests</h1>';
    echo '<div class="notice notice-info">';
    echo '<h3>Debug Information</h3>';
    echo '<pre>';
    foreach ($debug_info as $key => $value) {
        echo esc_html($key . ': ' . $value) . "\n";
    }
    echo '</pre>';
    echo '</div>';

    // Try to include the test file
    if (!file_exists($test_file)) {
        throw new Exception('Test file not found at: ' . $test_file);
    }

    if (!is_readable($test_file)) {
        throw new Exception('Test file is not readable at: ' . $test_file);
    }

    echo '<div class="notice notice-info"><p>Loading test file...</p></div>';
    require_once $test_file;
    echo '<div class="notice notice-success"><p>Test file loaded successfully</p></div>';

    // Verify class exists
    if (!class_exists('\Weebunz\Test\WeeBunz_Tester')) {
        throw new Exception('WeeBunz_Tester class not found after loading file. Available classes: ' . print_r(get_declared_classes(), true));
    }
    echo '<div class="notice notice-success"><p>WeeBunz_Tester class exists</p></div>';

    // Create tester instance
    echo '<div class="notice notice-info"><p>Creating tester instance...</p></div>';
    $tester = new \Weebunz\Test\WeeBunz_Tester();
    echo '<div class="notice notice-success"><p>Tester instance created successfully</p></div>';

    // Run tests if requested
    if (isset($_POST['run_tests']) && check_admin_referer('weebunz_run_tests')) {
        echo '<div class="notice notice-info"><p>Running tests...</p></div>';
        $results = $tester->run_all_tests();
        echo '<div class="notice notice-success"><p>Tests completed</p></div>';
        echo $tester->get_html_report();
    }

    // Form for running tests
    ?>
    <form method="post" action="">
        <?php wp_nonce_field('weebunz_run_tests'); ?>
        <p class="submit">
            <input type="submit" 
                   name="run_tests" 
                   class="button button-primary" 
                   value="Run Tests">
        </p>
    </form>
    <?php

} catch (Throwable $e) {
    // Display any errors (using Throwable to catch both Exceptions and Errors)
    echo '<div class="notice notice-error">';
    echo '<p>Error: ' . esc_html($e->getMessage()) . '</p>';
    echo '<p>File: ' . esc_html($e->getFile()) . ' Line: ' . esc_html($e->getLine()) . '</p>';
    echo '<pre>' . esc_html($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}

// Additional error handler for PHP notices/warnings
function weebunz_error_handler($errno, $errstr, $errfile, $errline) {
    echo '<div class="notice notice-warning">';
    echo '<p>PHP Notice (' . $errno . '): ' . $errstr . '</p>';
    echo '<p>File: ' . $errfile . ' Line: ' . $errline . '</p>';
    echo '</div>';
    return true;
}

set_error_handler('weebunz_error_handler');