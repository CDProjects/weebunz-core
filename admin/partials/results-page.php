<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/results-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get quiz attempts with pagination
$page = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Try to get attempts if table exists
$attempts = [];
$total_attempts = 0;

try {
    // Check if table exists first
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}quiz_attempts'");
    
    if ($table_exists) {
        $attempts = $wpdb->get_results($wpdb->prepare("
            SELECT qa.*, 
                   u.display_name as user_name,
                   qt.name as quiz_name,
                   COUNT(ua.id) as answer_count
            FROM {$wpdb->prefix}quiz_attempts qa
            LEFT JOIN {$wpdb->users} u ON qa.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}active_quizzes q ON qa.quiz_id = q.id
            LEFT JOIN {$wpdb->prefix}quiz_types qt ON q.quiz_type_id = qt.id
            LEFT JOIN {$wpdb->prefix}user_answers ua ON qa.id = ua.attempt_id
            GROUP BY qa.id
            ORDER BY qa.start_time DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        $total_attempts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}quiz_attempts");
    }
} catch (Exception $e) {
    // Do nothing, we'll handle the empty state below
}

$total_pages = ceil($total_attempts / $per_page);

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php 
    // Show notice if tables don't exist
    if (!$table_exists) {
        echo '<div class="notice notice-warning"><p>The quiz attempts table does not exist yet. Please run the database setup or activate the plugin properly.</p></div>';
    }
    
    settings_errors('weebunz_messages'); 
    ?>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="status_filter" id="status_filter">
                <option value="">All Statuses</option>
                <option value="completed">Completed</option>
                <option value="in_progress">In Progress</option>
                <option value="abandoned">Abandoned</option>
            </select>
            <input type="submit" class="button" value="Filter">
        </div>

        <!-- Search Box -->
        <div class="tablenav-pages">
            <div class="search-box">
                <input type="search" id="results-search" name="s" placeholder="Search by username...">
                <input type="submit" class="button" value="Search">
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-user">User</th>
                <th scope="col" class="column-quiz">Quiz</th>
                <th scope="col" class="column-score">Score</th>
                <th scope="col" class="column-entries">Entries</th>
                <th scope="col" class="column-date">Date</th>
                <th scope="col" class="column-status">Status</th>
                <th scope="col" class="column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($attempts)): ?>
                <?php foreach ($attempts as $attempt): ?>
                    <tr>
                        <td class="column-user">
                            <?php echo $attempt->user_id ? esc_html($attempt->user_name) : 'Guest'; ?>
                        </td>
                        <td class="column-quiz">
                            <?php echo esc_html($attempt->quiz_name); ?>
                        </td>
                        <td class="column-score">
                            <?php 
                            if ($attempt->status === 'completed') {
                                echo esc_html($attempt->score);
                                if (isset($attempt->answer_count) && $attempt->answer_count > 0) {
                                    echo ' / ' . esc_html($attempt->answer_count);
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="column-entries">
                            <?php echo $attempt->status === 'completed' ? esc_html($attempt->entries_earned) : '-'; ?>
                        </td>
                        <td class="column-date">
                            <?php echo esc_html(date('F j, Y g:i A', strtotime($attempt->start_time))); ?>
                        </td>
                        <td class="column-status">
                            <span class="status-badge status-<?php echo esc_attr($attempt->status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $attempt->status))); ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-results&action=view&id=' . $attempt->id); ?>">
                                        View Details
                                    </a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <?php if ($table_exists): ?>
                            No quiz attempts found.
                        <?php else: ?>
                            Quiz attempts table not found. Please run the database setup.
                        <?php endif; ?>
                    </td>
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
/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-completed {
    background-color: #e7f5e7;
    color: #00a32a;
}

.status-in_progress {
    background-color: #fff8e5;
    color: #996800;
}

.status-abandoned {
    background-color: #f0f0f1;
    color: #646970;
}

/* Table Columns */
.column-user { width: 15%; }
.column-quiz { width: 20%; }
.column-score { width: 10%; }
.column-entries { width: 10%; }
.column-date { width: 20%; }
.column-status { width: 10%; }
.column-actions { width: 15%; }
</style>

<script>
jQuery(document).ready(function($) {
    // Handle filters
    $('#status_filter').on('change', function() {
        var status = $(this).val();
        var url = '<?php echo admin_url('admin.php?page=weebunz-quiz-engine-results'); ?>';
        
        if (status) url += '&status=' + status;
        
        window.location.href = url;
    });

    // Handle search
    $('#results-search').keypress(function(e) {
        if (e.which == 13) { // Enter key
            var searchTerm = $(this).val();
            var url = '<?php echo admin_url('admin.php?page=weebunz-quiz-engine-results'); ?>&s=' + encodeURIComponent(searchTerm);
            window.location.href = url;
        }
    });
});
</script>