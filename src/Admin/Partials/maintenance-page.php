<?php
// Location: wp-content/plugins/weebunz-core/admin/partials/maintenance-page.php

if (!defined('ABSPATH')) {
    exit;
}

// Process update request
if (isset($_POST['run_updates']) && check_admin_referer('weebunz_maintenance')) {
    try {
        $db_manager = new \Weebunz\Database\DB_Manager();
        $result = $db_manager->process_updates();
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Updates processed successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error processing updates. Check error log for details.</p></div>';
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

// Get current database version
$current_version = get_option('weebunz_db_update_version', '1.0.0');
?>

<div class="wrap">
    <h1>WeeBunz Database Maintenance</h1>

    <div class="card">
        <h2>Database Status</h2>
        <p><strong>Current Version:</strong> <?php echo esc_html($current_version); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('weebunz_maintenance'); ?>
            <p>
                <input type="submit" 
                       name="run_updates" 
                       class="button button-primary" 
                       value="Check and Run Updates">
            </p>
        </form>
    </div>

    <div class="card">
        <h2>Latest Updates</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Update File</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $updates_dir = WEEBUNZ_PLUGIN_DIR . 'includes/database/updates/';
                if (is_dir($updates_dir)) {
                    $files = scandir($updates_dir);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        
                        $version = substr($file, 0, strpos($file, '-'));
                        $status = version_compare($version, $current_version, '<=') 
                            ? '<span style="color:green">✓ Applied</span>' 
                            : '<span style="color:orange">Pending</span>';
                        
                        echo '<tr>';
                        echo '<td>' . esc_html($version) . '</td>';
                        echo '<td>' . esc_html($file) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>