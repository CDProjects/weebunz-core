<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/quiz-management-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get all quizzes with pagination
$page = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$quizzes = $wpdb->get_results($wpdb->prepare("
    SELECT q.*, 
           qt.name as type_name,
           qt.difficulty_level
    FROM {$wpdb->prefix}active_quizzes q
    LEFT JOIN {$wpdb->prefix}quiz_types qt ON q.quiz_type_id = qt.id
    ORDER BY q.created_at DESC
    LIMIT %d OFFSET %d
", $per_page, $offset));

$total_quizzes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}active_quizzes");
$total_pages = ceil($total_quizzes / $per_page);

// Get quiz types for filter
$quiz_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}quiz_types");

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes&action=new'); ?>" class="page-title-action">Add New Quiz</a>
    
    <hr class="wp-header-end">

    <?php settings_errors('weebunz_messages'); ?>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="type_filter" id="type_filter">
                <option value="">All Quiz Types</option>
                <?php foreach ($quiz_types as $type): ?>
                    <option value="<?php echo esc_attr($type->id); ?>">
                        <?php echo esc_html($type->name); ?> (<?php echo esc_html($type->difficulty_level); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status_filter" id="status_filter">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="finished">Finished</option>
            </select>
            <input type="submit" class="button" value="Filter">
        </div>
    </div>

    <!-- Quizzes Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Status</th>
                <th>Discount</th>
                <th>Date Range</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($quizzes): ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes&action=edit&id=' . $quiz->id); ?>">
                                    <?php echo esc_html($quiz->title); ?>
                                </a>
                            </strong>
                        </td>
                        <td>
                            <span class="quiz-type quiz-type-<?php echo esc_attr($quiz->difficulty_level); ?>">
                                <?php echo esc_html($quiz->type_name); ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-<?php echo esc_attr($quiz->status); ?>">
                                <?php echo esc_html(ucfirst($quiz->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($quiz->discount_percentage): ?>
                                <span class="discount-badge">
                                    <?php echo esc_html($quiz->discount_percentage); ?>% OFF
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            echo esc_html(date('M j, Y', strtotime($quiz->start_date))) . ' - ' . 
                                 esc_html(date('M j, Y', strtotime($quiz->end_date))); 
                            ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes&action=edit&id=' . $quiz->id); ?>">
                                        Edit
                                    </a> |
                                </span>
                                <span class="duplicate">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes&action=duplicate&id=' . $quiz->id); ?>">
                                        Duplicate
                                    </a> |
                                </span>
                                <?php if ($quiz->status === 'draft'): ?>
                                    <span class="delete">
                                        <a href="#" class="delete-quiz" data-id="<?php echo $quiz->id; ?>">
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
                    <td colspan="6">No quizzes found.</td>
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
.quiz-type {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.quiz-type-easy {
    background-color: #e7f5e7;
    color: #00a32a;
}

.quiz-type-medium {
    background-color: #fff8e5;
    color: #996800;
}

.quiz-type-hard {
    background-color: #fcf0f1;
    color: #d63638;
}

.status-draft { color: #646970; }
.status-active { color: #00a32a; }
.status-finished { color: #646970; }

.discount-badge {
    background-color: #d63638;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle filters
    $('#type_filter, #status_filter').on('change', function() {
        var type = $('#type_filter').val();
        var status = $('#status_filter').val();
        var url = '<?php echo admin_url('admin.php?page=weebunz-quizzes'); ?>';
        
        if (type) url += '&type=' + type;
        if (status) url += '&status=' + status;
        
        window.location.href = url;
    });

    // Handle delete action
    $('.delete-quiz').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) {
            var quizId = $(this).data('id');
            // Add delete functionality here
        }
    });
});
</script>