<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/dashboard-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get real statistics
$active_raffles = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}raffle_events 
    WHERE status = 'active'
");

$total_members = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}platinum_memberships 
    WHERE status = 'active'
");

$active_quizzes = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}active_quizzes 
    WHERE status = 'active'
");

$total_entries = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->prefix}raffle_entries
");

$upcoming_draws = $wpdb->get_results("
    SELECT title, event_date 
    FROM {$wpdb->prefix}raffle_events 
    WHERE status = 'scheduled' 
    AND event_date > NOW() 
    ORDER BY event_date ASC 
    LIMIT 5
");

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>Welcome to WeeBunz Management</h2>
        <p>From here you can manage all aspects of your quiz and raffle system.</p>
    </div>

    <div class="weebunz-dashboard-grid">
        <!-- Statistics Cards -->
        <div class="card stat-card">
            <h3>Active Raffles</h3>
            <div class="stat-number"><?php echo esc_html($active_raffles); ?></div>
            <a href="<?php echo admin_url('admin.php?page=weebunz-manage-raffles'); ?>" class="button button-secondary">View All</a>
        </div>

        <div class="card stat-card">
            <h3>Total Members</h3>
            <div class="stat-number"><?php echo esc_html($total_members); ?></div>
            <a href="<?php echo admin_url('admin.php?page=weebunz-members'); ?>" class="button button-secondary">Manage Members</a>
        </div>

        <div class="card stat-card">
            <h3>Active Quizzes</h3>
            <div class="stat-number"><?php echo esc_html($active_quizzes); ?></div>
            <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes'); ?>" class="button button-secondary">Manage Quizzes</a>
        </div>

        <div class="card stat-card">
            <h3>Total Entries</h3>
            <div class="stat-number"><?php echo esc_html($total_entries); ?></div>
        </div>
    </div>

    <!-- Upcoming Draws -->
    <div class="card">
        <h3>Upcoming Draws</h3>
        <?php if ($upcoming_draws): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Raffle Title</th>
                        <th>Draw Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_draws as $draw): ?>
                        <tr>
                            <td><?php echo esc_html($draw->title); ?></td>
                            <td><?php echo esc_html(date('F j, Y g:i A', strtotime($draw->event_date))); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=weebunz-manage-raffles&action=edit&id=' . $draw->id); ?>" 
                                   class="button button-small">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No upcoming draws scheduled.</p>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h3>Quick Actions</h3>
        <div class="quick-actions">
            <a href="<?php echo admin_url('admin.php?page=weebunz-create-raffle'); ?>" class="button button-primary">Create New Raffle</a>
            <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes&action=new'); ?>" class="button button-primary">Add New Quiz</a>
            <a href="<?php echo admin_url('admin.php?page=weebunz-members&action=new'); ?>" class="button button-primary">Add Member</a>
        </div>
    </div>
</div>

<style>
.weebunz-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    text-align: center;
    padding: 20px;
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin: 15px 0;
    color: #2271b1;
}

.quick-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.quick-actions .button {
    flex: 1;
    text-align: center;
}

table {
    margin-top: 15px;
}
</style>