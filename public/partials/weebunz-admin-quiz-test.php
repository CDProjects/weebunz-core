<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/test-page.php

if (!defined('ABSPATH')) {
    exit;
}

require_once WEEBUNZ_PLUGIN_DIR . 'includes/test/class-weebunz-tester.php';

$tester = new \Weebunz\Test\WeeBunz_Tester();

// Run tests if requested
if (isset($_POST['run_tests']) && check_admin_referer('weebunz_run_tests')) {
    $results = $tester->run_all_tests();
    echo $tester->get_html_report();
}
?>

<div class="wrap">
    <h1>WeeBunz Core Tests</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('weebunz_run_tests'); ?>
        <p class="submit">
            <input type="submit" name="run_tests" class="button button-primary" value="Run Tests">
        </p>
    </form>

    <div class="card">
        <h2>How to Test</h2>
        <ol>
            <li>Make sure WP_DEBUG is enabled in wp-config.php</li>
            <li>Click "Run Tests" to verify:
                <ul>
                    <li>Database structure</li>
                    <li>Test data insertion</li>
                    <li>File system setup</li>
                </ul>
            </li>
            <li>Check error logs for any warnings or notices</li>
            <li>Verify all tests pass (green checkmarks)</li>
        </ol>
    </div>

    <div class="card">
        <h2>Manual Testing Steps</h2>
        <ol>
            <li>Deactivate the plugin</li>
            <li>Reactivate the plugin</li>
            <li>Run the tests above</li>
            <li>Check the WordPress users list for test users</li>
            <li>Verify upload directories in wp-content/uploads/weebunz/</li>
        </ol>
    </div>
</div>