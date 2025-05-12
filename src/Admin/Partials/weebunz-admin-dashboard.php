<?php
/**
 * Provide a admin area view for the plugin dashboard
 *
 * @link       https://weebunz.com
 * @since      1.0.0
 *
 * @package    WeeBunz_Quiz_Engine
 * @subpackage WeeBunz_Quiz_Engine/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="weebunz-dashboard">
        <div class="weebunz-dashboard-header">
            <h2><?php _e('WeeBunz Quiz Engine Dashboard', 'weebunz-quiz-engine'); ?></h2>
            <p><?php _e('Welcome to the WeeBunz Quiz Engine. This dashboard provides an overview of your quiz system.', 'weebunz-quiz-engine'); ?></p>
        </div>
        
        <div class="weebunz-dashboard-stats">
            <div class="weebunz-stat-box">
                <h3><?php _e('Total Quizzes', 'weebunz-quiz-engine'); ?></h3>
                <div class="weebunz-stat-number">
                    <?php 
                    global $wpdb;
                    $quiz_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}weebunz_quizzes");
                    echo esc_html($quiz_count ? $quiz_count : '0'); 
                    ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-quizzes'); ?>" class="button"><?php _e('Manage Quizzes', 'weebunz-quiz-engine'); ?></a>
            </div>
            
            <div class="weebunz-stat-box">
                <h3><?php _e('Total Questions', 'weebunz-quiz-engine'); ?></h3>
                <div class="weebunz-stat-number">
                    <?php 
                    $question_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}weebunz_questions");
                    echo esc_html($question_count ? $question_count : '0'); 
                    ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-questions'); ?>" class="button"><?php _e('Manage Questions', 'weebunz-quiz-engine'); ?></a>
            </div>
            
            <div class="weebunz-stat-box">
                <h3><?php _e('Quiz Sessions', 'weebunz-quiz-engine'); ?></h3>
                <div class="weebunz-stat-number">
                    <?php 
                    $session_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}weebunz_quiz_sessions");
                    echo esc_html($session_count ? $session_count : '0'); 
                    ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-results'); ?>" class="button"><?php _e('View Results', 'weebunz-quiz-engine'); ?></a>
            </div>
            
            <div class="weebunz-stat-box">
                <h3><?php _e('Raffle Entries', 'weebunz-quiz-engine'); ?></h3>
                <div class="weebunz-stat-number">
                    <?php 
                    $entry_count = $wpdb->get_var("SELECT SUM(entry_count) FROM {$wpdb->prefix}weebunz_raffle_entries");
                    echo esc_html($entry_count ? $entry_count : '0'); 
                    ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-raffle'); ?>" class="button"><?php _e('Manage Raffle', 'weebunz-quiz-engine'); ?></a>
            </div>
        </div>
        
        <div class="weebunz-dashboard-performance">
            <h3><?php _e('System Performance', 'weebunz-quiz-engine'); ?></h3>
            
            <?php
            // Check Redis connection if enabled
            $redis_enabled = get_option('weebunz_quiz_enable_redis_cache') === 'yes';
            $redis_status = 'disabled';
            $redis_class = 'weebunz-status-disabled';
            
            if ($redis_enabled && class_exists('Redis')) {
                try {
                    $redis = new Redis();
                    $connected = $redis->connect(
                        get_option('weebunz_quiz_redis_host', '127.0.0.1'),
                        get_option('weebunz_quiz_redis_port', 6379),
                        1 // 1 second timeout
                    );
                    
                    if ($connected) {
                        $auth = get_option('weebunz_quiz_redis_auth', '');
                        if (!empty($auth)) {
                            $redis->auth($auth);
                        }
                        
                        $redis_status = 'connected';
                        $redis_class = 'weebunz-status-good';
                        $redis->close();
                    } else {
                        $redis_status = 'connection failed';
                        $redis_class = 'weebunz-status-error';
                    }
                } catch (Exception $e) {
                    $redis_status = 'error: ' . $e->getMessage();
                    $redis_class = 'weebunz-status-error';
                }
            }
            ?>
            
            <div class="weebunz-performance-metrics">
                <div class="weebunz-metric">
                    <span class="weebunz-metric-label"><?php _e('Redis Cache:', 'weebunz-quiz-engine'); ?></span>
                    <span class="weebunz-metric-value <?php echo esc_attr($redis_class); ?>"><?php echo esc_html($redis_status); ?></span>
                </div>
                
                <div class="weebunz-metric">
                    <span class="weebunz-metric-label"><?php _e('Rate Limiting:', 'weebunz-quiz-engine'); ?></span>
                    <span class="weebunz-metric-value <?php echo get_option('weebunz_quiz_rate_limit_enabled') === 'yes' ? 'weebunz-status-good' : 'weebunz-status-disabled'; ?>">
                        <?php echo get_option('weebunz_quiz_rate_limit_enabled') === 'yes' ? esc_html__('enabled', 'weebunz-quiz-engine') : esc_html__('disabled', 'weebunz-quiz-engine'); ?>
                    </span>
                </div>
                
                <div class="weebunz-metric">
                    <span class="weebunz-metric-label"><?php _e('Background Processing:', 'weebunz-quiz-engine'); ?></span>
                    <span class="weebunz-metric-value <?php echo get_option('weebunz_quiz_enable_background_processing') === 'yes' ? 'weebunz-status-good' : 'weebunz-status-disabled'; ?>">
                        <?php echo get_option('weebunz_quiz_enable_background_processing') === 'yes' ? esc_html__('enabled', 'weebunz-quiz-engine') : esc_html__('disabled', 'weebunz-quiz-engine'); ?>
                    </span>
                </div>
                
                <div class="weebunz-metric">
                    <span class="weebunz-metric-label"><?php _e('Concurrent User Limit:', 'weebunz-quiz-engine'); ?></span>
                    <span class="weebunz-metric-value">
                        <?php echo esc_html(get_option('weebunz_quiz_concurrent_users_limit', 1000)); ?>
                    </span>
                </div>
            </div>
            
            <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-performance'); ?>" class="button"><?php _e('View Performance Details', 'weebunz-quiz-engine'); ?></a>
        </div>
        
        <div class="weebunz-dashboard-actions">
            <h3><?php _e('Quick Actions', 'weebunz-quiz-engine'); ?></h3>
            <div class="weebunz-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-quizzes&action=new'); ?>" class="button button-primary"><?php _e('Create New Quiz', 'weebunz-quiz-engine'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=weebunz-quiz-engine-settings'); ?>" class="button"><?php _e('Configure Settings', 'weebunz-quiz-engine'); ?></a>
                <a href="#" class="button weebunz-clear-cache-button"><?php _e('Clear Cache', 'weebunz-quiz-engine'); ?></a>
            </div>
        </div>
    </div>
</div>
