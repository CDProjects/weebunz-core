<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/members-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get members with pagination
$page = isset($_GET['paged']) ? abs((int)$_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$members = $wpdb->get_results($wpdb->prepare("
    SELECT m.*, 
           u.user_email,
           u.display_name,
           (SELECT COUNT(*) FROM {$wpdb->prefix}raffle_entries WHERE user_id = m.user_id) as total_entries
    FROM {$wpdb->prefix}platinum_memberships m
    JOIN {$wpdb->users} u ON m.user_id = u.ID
    ORDER BY m.created_at DESC
    LIMIT %d OFFSET %d
", $per_page, $offset));

$total_members = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}platinum_memberships");
$total_pages = ceil($total_members / $per_page);

?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=weebunz-members&action=new'); ?>" class="page-title-action">Add New Member</a>
    
    <hr class="wp-header-end">

    <?php settings_errors('weebunz_messages'); ?>

    <!-- Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="plan_filter" id="plan_filter">
                <option value="">All Plans</option>
                <option value="monthly">Monthly (€25.50)</option>
                <option value="quarterly">Quarterly (€69.50)</option>
                <option value="biannual">6 Months (€139.50)</option>
                <option value="annual">Annual (€289.50)</option>
            </select>
            <select name="status_filter" id="status_filter">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="cancelled">Cancelled</option>
                <option value="expired">Expired</option>
            </select>
            <input type="submit" class="button" value="Filter">
        </div>

        <!-- Search Box -->
        <div class="tablenav-pages">
            <div class="search-box">
                <input type="search" id="member-search" name="s" placeholder="Search members...">
                <input type="submit" class="button" value="Search">
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-member">Member Details</th>
                <th scope="col" class="column-plan">Membership Plan</th>
                <th scope="col" class="column-entries">Entries & Points</th>
                <th scope="col" class="column-quizzes">Quiz Access</th>
                <th scope="col" class="column-dates">Membership Dates</th>
                <th scope="col" class="column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($members): ?>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td class="column-member">
                            <strong><?php echo esc_html($member->display_name); ?></strong>
                            <br>
                            <span class="member-email"><?php echo esc_html($member->user_email); ?></span>
                            <br>
                            <span class="status-badge status-<?php echo esc_attr($member->status); ?>">
                                <?php echo esc_html(ucfirst($member->status)); ?>
                            </span>
                        </td>
                        <td class="column-plan">
                            <span class="plan-badge">
                                <?php 
                                $plan_labels = [
                                    'monthly' => 'Monthly - €25.50',
                                    'quarterly' => 'Quarterly - €69.50',
                                    'biannual' => '6 Months - €139.50',
                                    'annual' => 'Annual - €289.50'
                                ];
                                echo esc_html($plan_labels[$member->plan_duration] ?? '');
                                ?>
                            </span>
                        </td>
                        <td class="column-entries">
                            <strong>Available Entries:</strong> <?php echo esc_html($member->accumulated_entries); ?>
                            <br>
                            <strong>Total Points:</strong> <?php echo esc_html($member->monthly_points); ?>
                            <br>
                            <strong>Total Entries Used:</strong> <?php echo esc_html($member->total_entries); ?>
                        </td>
                        <td class="column-quizzes">
                            <strong>Free Quizzes:</strong> <?php echo esc_html($member->free_quizzes_remaining); ?> remaining
                            <?php if ($member->free_quizzes_remaining < 3): ?>
                                <br>
                                <button class="button button-small reset-quizzes" data-id="<?php echo $member->id; ?>">
                                    Reset Free Quizzes
                                </button>
                            <?php endif; ?>
                        </td>
                        <td class="column-dates">
                            <strong>Started:</strong> <?php echo esc_html(date('M j, Y', strtotime($member->start_date))); ?>
                            <br>
                            <strong>Expires:</strong> <?php echo esc_html(date('M j, Y', strtotime($member->end_date))); ?>
                            <?php if ($member->status === 'active'): ?>
                                <br>
                                <span class="days-remaining">
                                    <?php 
                                    $days_remaining = ceil((strtotime($member->end_date) - time()) / (60 * 60 * 24));
                                    echo esc_html($days_remaining . ' days remaining');
                                    ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-members&action=edit&id=' . $member->id); ?>">
                                        Edit Details
                                    </a>
                                </span> |
                                <span class="view-entries">
                                    <a href="<?php echo admin_url('admin.php?page=weebunz-members&action=entries&id=' . $member->id); ?>">
                                        View Entries
                                    </a>
                                </span>
                                <?php if ($member->status === 'active'): ?>
                                    |
                                    <span class="cancel">
                                        <a href="#" class="cancel-membership" data-id="<?php echo $member->id; ?>">
                                            Cancel Membership
                                        </a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No members found.</td>
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
/* Member Status Badges */
.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-active {
    background-color: #e7f5e7;
    color: #00a32a;
}

.status-cancelled {
    background-color: #fcf0f1;
    color: #d63638;
}

.status-expired {
    background-color: #f0f0f1;
    color: #646970;
}

/* Plan Badge */
.plan-badge {
    display: inline-block;
    padding: 4px 10px;
    background-color: #f0f7ff;
    color: #2271b1;
    border-radius: 4px;
    font-size: 13px;
}

/* Member Email */
.member-email {
    color: #646970;
    font-size: 13px;
}

/* Days Remaining */
.days-remaining {
    font-size: 12px;
    color: #2271b1;
}

/* Table Columns */
.column-member { width: 20%; }
.column-plan { width: 15%; }
.column-entries { width: 20%; }
.column-quizzes { width: 15%; }
.column-dates { width: 15%; }
.column-actions { width: 15%; }

/* Search Box */
.search-box {
    float: right;
    margin-bottom: 8px;
}

/* Action Buttons */
.row-actions {
    font-size: 12px;
}

.button-small {
    font-size: 11px;
    height: 25px;
    line-height: 23px;
    padding: 0 8px;
    margin-top: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle filters
    $('#plan_filter, #status_filter').on('change', function() {
        var plan = $('#plan_filter').val();
        var status = $('#status_filter').val();
        var url = '<?php echo admin_url('admin.php?page=weebunz-members'); ?>';
        
        if (plan) url += '&plan=' + plan;
        if (status) url += '&status=' + status;
        
        window.location.href = url;
    });

    // Handle member search
    var searchTimer;
    $('#member-search').on('input', function() {
        clearTimeout(searchTimer);
        var searchTerm = $(this).val();
        
        searchTimer = setTimeout(function() {
            if (searchTerm.length > 2 || searchTerm.length === 0) {
                var url = '<?php echo admin_url('admin.php?page=weebunz-members'); ?>&s=' + encodeURIComponent(searchTerm);
                window.location.href = url;
            }
        }, 500);
    });

    // Handle membership cancellation
    $('.cancel-membership').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to cancel this membership? This action cannot be undone.')) {
            var memberId = $(this).data('id');
            // Add cancellation AJAX call here
        }
    });

    // Handle free quiz reset
    $('.reset-quizzes').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to reset the free quizzes for this member?')) {
            var memberId = $(this).data('id');
            // Add reset AJAX call here
        }
    });
});
</script>