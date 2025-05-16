<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/questions-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get questions with pagination
$page = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Try to get questions if table exists
$questions = [];
$total_questions = 0;

try {
    // Check if table exists first
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}questions_pool'");
    
    if ($table_exists) {
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT qp.*, COUNT(qa.id) as answer_count
            FROM {$wpdb->prefix}questions_pool qp
            LEFT JOIN {$wpdb->prefix}question_answers qa ON qp.id = qa.question_id
            GROUP BY qp.id
            ORDER BY qp.id DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        $total_questions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}questions_pool");
    }
} catch (Exception $e) {
    // Do nothing, we'll handle the empty state below
}

$total_pages = ceil($total_questions / $per_page);

// Get categories for filter
$categories = [];
try {
    if ($table_exists) {
        $categories = $wpdb->get_results(
            "SELECT DISTINCT category FROM {$wpdb->prefix}questions_pool WHERE category IS NOT NULL"
        );
    }
} catch (Exception $e) {
    // Do nothing
}

// Base admin URL for this page
$base_admin = admin_url('admin.php');

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    <a href="<?php echo esc_url(add_query_arg([
            'page'   => 'weebunz-quiz-engine-questions',
            'action' => 'new',
        ], $base_admin)); ?>" class="page-title-action">Add New Question</a>
    
    <hr class="wp-header-end">

    <?php 
    // Show notice if tables don't exist
    if (!$table_exists) {
        echo '<div class="notice notice-warning"><p>The questions table does not exist yet. Please run the database setup or activate the plugin properly.</p></div>';
    }
    
    settings_errors('weebunz_messages'); 
    ?>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="category_filter" id="category_filter">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->category); ?>">
                        <?php echo esc_html($cat->category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty_filter" id="difficulty_filter">
                <option value="">All Difficulties</option>
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
            </select>
            <input type="submit" class="button" value="Filter">
        </div>

        <!-- Search Box -->
        <div class="tablenav-pages">
            <div class="search-box">
                <input type="search" id="question-search" name="s" placeholder="Search questions...">
                <input type="submit" class="button" value="Search">
            </div>
        </div>
    </div>

    <!-- Questions Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-question">Question</th>
                <th scope="col" class="column-category">Category</th>
                <th scope="col" class="column-difficulty">Difficulty</th>
                <th scope="col" class="column-answers"># of Answers</th>
                <th scope="col" class="column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($questions)): ?>
                <?php foreach ($questions as $question): ?>
                    <tr>
                        <td class="column-question">
                            <strong><?php echo esc_html($question->question_text); ?></strong>
                        </td>
                        <td class="column-category">
                            <span class="question-category"><?php echo esc_html($question->category); ?></span>
                        </td>
                        <td class="column-difficulty">
                            <span class="difficulty-badge difficulty-<?php echo esc_attr($question->difficulty_level); ?>">
                                <?php echo esc_html(ucfirst($question->difficulty_level)); ?>
                            </span>
                        </td>
                        <td class="column-answers">
                            <?php echo esc_html($question->answer_count); ?>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <?php $edit_url = esc_url(add_query_arg([
                                    'page'   => 'weebunz-quiz-engine-questions',
                                    'action' => 'edit',
                                    'id'     => $question->id,
                                ], $base_admin)); ?>
                                <span class="edit">
                                    <a href="<?php echo $edit_url; ?>">Edit</a> |
                                </span>
                                <?php $ans_url = esc_url(add_query_arg([
                                    'page'   => 'weebunz-quiz-engine-questions',
                                    'action' => 'answers',
                                    'id'     => $question->id,
                                ], $base_admin)); ?>
                                <span class="view-answers">
                                    <a href="<?php echo $ans_url; ?>">View Answers</a> |
                                </span>
                                <span class="delete">
                                    <a href="#" class="delete-question" data-id="<?php echo $question->id; ?>">Delete</a>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">
                        <?php if ($table_exists): ?>
                            No questions found. <a href="<?php echo esc_url(add_query_arg([
                                'page'   => 'weebunz-quiz-engine-questions',
                                'action' => 'new',
                            ], $base_admin)); ?>">Add your first question</a>.
                        <?php else: ?>
                            Questions table not found. Please run the database setup.
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
/* Category Badge */
.question-category {
    display: inline-block;
    padding: 3px 8px;
    background-color: #f0f7ff;
    color: #2271b1;
    border-radius: 4px;
    font-size: 12px;
}

/* Difficulty Badges */
.difficulty-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.difficulty-easy {
    background-color: #e7f5e7;
    color: #00a32a;
}

.difficulty-medium {
    background-color: #fff8e5;
    color: #996800;
}

.difficulty-hard {
    background-color: #fcf0f1;
    color: #d63638;
}

/* Table Columns */
.column-question { width: 40%; }
.column-category { width: 15%; }
.column-difficulty { width: 15%; }
.column-answers { width: 10%; }
.column-actions { width: 20%; }
</style>

<script>
jQuery(document).ready(function($) {
    // Handle filters
    $('#category_filter, #difficulty_filter').on('change', function() {
        var category = encodeURIComponent($('#category_filter').val());
        var difficulty = $('#difficulty_filter').val();
        var url = '<?php echo esc_js(add_query_arg([], $base_admin) . "&page=weebunz-quiz-engine-questions"); ?>';
        if (category) url += '&category=' + category;
        if (difficulty) url += '&difficulty=' + difficulty;
        window.location.href = url;
    });

    // Handle search
    $('#question-search').keypress(function(e) {
        if (e.which == 13) {
            var searchTerm = encodeURIComponent($(this).val());
            var url = '<?php echo esc_js(add_query_arg([], $base_admin) . "&page=weebunz-quiz-engine-questions"); ?>&s=' + searchTerm;
            window.location.href = url;
        }
    });

    // Handle delete action
    $('.delete-question').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this question? All associated answers will also be deleted.')) {
            var questionId = $(this).data('id');
            // Add AJAX delete functionality here
        }
    });
});
</script>
