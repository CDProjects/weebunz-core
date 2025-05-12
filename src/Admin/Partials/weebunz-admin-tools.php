<?php
/**
 * Admin Tools Page for WeeBunz Quiz Engine
 *
 * This file provides the UI for the Tools page, including sample data loading
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/admin/partials
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
// Include the Sample Data Loader
require_once WEEBUNZ_PLUGIN_DIR . 'includes/database/class-sample-data-loader.php';
$sample_data_loader = new \Weebunz\Database\Sample_Data_Loader();
// Handle form submission for loading sample data
if (isset($_POST['weebunz_load_sample_data'])) {
    check_admin_referer('weebunz_tools_nonce');
   
    $result = $sample_data_loader->load_sample_data();
   
    if ($result) {
        echo '<div class="notice notice-success"><p>Sample data loaded successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Failed to load sample data. Check error logs.</p></div>';
    }
}
// Handle form submission for cleaning up sample data
if (isset($_POST['weebunz_cleanup_sample_data'])) {
    check_admin_referer('weebunz_tools_nonce');
   
    $result = $sample_data_loader->cleanup_sample_data();
   
    if ($result) {
        echo '<div class="notice notice-success"><p>Sample data cleaned up successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Failed to clean up sample data. Check error logs.</p></div>';
    }
}
$is_sample_data_loaded = $sample_data_loader->is_sample_data_loaded();
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
   
    <div class="card">
        <h2>Sample Data Management</h2>
        <p>Use these tools to load or remove sample quiz data for testing and demonstration purposes.</p>
       
        <form method="post" action="">
            <?php wp_nonce_field('weebunz_tools_nonce'); ?>
           
            <?php if ($is_sample_data_loaded): ?>
                <p>Sample data is currently loaded.</p>
                <p class="submit">
                    <input type="submit" name="weebunz_cleanup_sample_data" id="weebunz_cleanup_sample_data" class="button button-secondary" value="Clean Up Sample Data">
                </p>
            <?php else: ?>
                <p>Sample data is not currently loaded.</p>
                <p class="submit">
                    <input type="submit" name="weebunz_load_sample_data" id="weebunz_load_sample_data" class="button button-primary" value="Load Sample Data">
                </p>
            <?php endif; ?>
        </form>
    </div>
    <div class="card">
        <h2>Other Tools</h2>
        <p>Additional tools and utilities for the WeeBunz Quiz Engine.</p>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-load-testing'); ?>">Load Testing Tool</a></li>
            <!-- Add links to other tools here -->
        </ul>
    </div>
</div>