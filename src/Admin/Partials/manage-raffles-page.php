<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/manage-raffles-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get raffles with pagination
$page = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$raffles = $wpdb->get_results($wpdb->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM {$wpdb->prefix}raffle_entries WHERE raffle_id = r.id) as entry_count
    FROM {$wpdb->prefix}raffle_events r
    ORDER BY r.event_date DESC
    LIMIT %d OFFSET %d
", $per_page, $offset));

$total_raffles = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}raffle_events");
$total_pages = ceil($total_raffles / $per_page);

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=weebunz-create-raffle'); ?>" class="page-title-action">Add New</a>
    
    <hr class="wp-header-end">

    <?php settings_errors('weebunz_messages'); ?>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="status_filter" id="status_filter">
                <option value="">All Statuses</option>
                <option value="scheduled">Scheduled</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
            </select>
            <input type="submit" class="button" value="Filter">
        </div>
    </div>

    <!-- Raffles Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Event Date</th>
                <th>Status</th>
                <th>Entries</th>
                <th>Live Event</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($raffles): ?>
                <?php foreach ($raffles as $raffle): ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=weebunz-manage-raffles&action=edit&id=' . $raffle->id); ?>">
                                    <?php echo esc_html($raffle->title); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html(date('F j, Y g:i A', strtotime($raffle->event_date))); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($raffle->status); ?>">
                                <?php echo esc_html(ucfirst($raffle->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($raffle->entry_count); ?> / <?php echo esc_html($raffle->entry_limit); ?>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo min(100, ($raffle->entry_count / $raffle->entry_limit) * 100); ?>%"></div>
                            </div>
                        </td>
                        <td><?php echo $raffle->is_live_event ? 'Yes' : 'No'; ?></td>
                        <td>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-manage-raffles&action=edit&id=' . $raffle->id); ?>">
                                        Edit
                                    </a> |
                                </span>
                                <span class="entries">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-manage-raffles&action=entries&id=' . $raffle->id); ?>">
                                        View Entries
                                    </a> |
                                </span>
                                <?php if ($raffle->status === 'scheduled'): ?>
                                    <span class="delete">
                                        <a href="#" class="delete-raffle" data-id="<?php echo $raffle->id; ?>">
                                            Delete
                                        </a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No raffles found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $page
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.status-scheduled { color: #2271b1; }
.status-active { color: #00a32a; }
.status-completed { color: #646970; }

.progress-bar {
    width: 100%;
    background-color: #f0f0f1;
    height: 8px;
    border-radius: 4px;
    margin-top: 5px;
}

.progress-bar .progress {
    background-color: #2271b1;
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle status filter
    $('#status_filter').on('change', function() {
        var status = $(this).val();
        if (status) {
            window.location.href = '<?php echo admin_url('admin.php?page=weebunz-manage-raffles'); ?>&status=' + status;
        }
    });

    // Handle delete action
    $('.delete-raffle').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this raffle?')) {
            var raffleId = $(this).data('id');
            // Add delete functionality here
        }
    });
});
</script>