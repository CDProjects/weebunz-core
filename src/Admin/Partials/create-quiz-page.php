<?php
// Save as: wp-content/plugins/weebunz-core/admin/partials/create-quiz-page.php

// Security check
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get quiz types
$quiz_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}quiz_types ORDER BY name");

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('weebunz_messages'); ?>

    <div class="card">
        <form method="post" action="" class="weebunz-form">
            <?php wp_nonce_field('create_quiz', 'weebunz_quiz_nonce'); ?>

            <div class="form-field">
                <label for="title"><strong>Quiz Title</strong></label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       class="regular-text" 
                       required>
                <p class="description">Enter a descriptive title for the quiz</p>
            </div>

            <div class="form-field">
                <label for="quiz_type"><strong>Quiz Type</strong></label>
                <select id="quiz_type" name="quiz_type" required>
                    <option value="">Select Quiz Type</option>
                    <?php foreach ($quiz_types as $type): ?>
                        <option value="<?php echo esc_attr($type->id); ?>"
                                data-difficulty="<?php echo esc_attr($type->difficulty_level); ?>"
                                data-question-count="<?php echo esc_attr($type->question_count); ?>"
                                data-time-limit="<?php echo esc_attr($type->time_limit); ?>">
                            <?php echo esc_html($type->name); ?> 
                            (<?php echo esc_html(ucfirst($type->difficulty_level)); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="quiz-type-details" class="hidden mt-2">
                    <p><strong>Questions:</strong> <span id="question-count">-</span></p>
                    <p><strong>Time Limit:</strong> <span id="time-limit">-</span> seconds per question</p>
                </div>
            </div>

            <div class="form-field">
                <label><strong>Question Categories</strong></label>
                <div id="category-selection">
                    <!-- Categories will be dynamically loaded based on quiz type -->
                    <p class="description">Select quiz type first to see available categories</p>
                </div>
            </div>

            <div class="form-field">
                <label for="start_date"><strong>Start Date</strong></label>
                <input type="datetime-local" 
                       id="start_date" 
                       name="start_date" 
                       required>
                <p class="description">When should this quiz become available?</p>
            </div>

            <div class="form-field">
                <label for="end_date"><strong>End Date</strong></label>
                <input type="datetime-local" 
                       id="end_date" 
                       name="end_date" 
                       required>
                <p class="description">When should this quiz expire?</p>
            </div>

            <div class="form-field">
                <label for="discount_percentage"><strong>Discount Percentage</strong></label>
                <input type="number" 
                       id="discount_percentage" 
                       name="discount_percentage" 
                       min="0" 
                       max="100" 
                       step="1">
                <p class="description">Optional: Apply a discount to this quiz's entry fee</p>
            </div>

            <div id="question-pool">
                <!-- Question pool will be dynamically loaded here -->
            </div>

            <p class="submit">
                <input type="submit" 
                       name="create_quiz" 
                       class="button button-primary" 
                       value="Create Quiz">
                <a href="<?php echo admin_url('admin.php?page=weebunz-quizzes'); ?>" 
                   class="button button-secondary">
                    Cancel
                </a>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle quiz type selection
    $('#quiz_type').on('change', function() {
        var $selected = $(this).find('option:selected');
        var $details = $('#quiz-type-details');
        
        if ($selected.val()) {
            $('#question-count').text($selected.data('question-count'));
            $('#time-limit').text($selected.data('time-limit'));
            $details.removeClass('hidden');
            
            // Load categories for this quiz type
            loadCategories($selected.val());
            
            // Load question pool
            loadQuestionPool($selected.val(), $selected.data('difficulty'));
        } else {
            $details.addClass('hidden');
            $('#category-selection').html('<p class="description">Select quiz type first to see available categories</p>');
            $('#question-pool').empty();
        }
    });

    function loadCategories(quizTypeId) {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_quiz_categories',
                quiz_type: quizTypeId,
                nonce: $('#weebunz_quiz_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#category-selection').html(response.data.html);
                }
            }
        });
    }

    function loadQuestionPool(quizTypeId, difficulty) {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_question_pool',
                quiz_type: quizTypeId,
                difficulty: difficulty,
                nonce: $('#weebunz_quiz_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    $('#question-pool').html(response.data.html);
                }
            }
        });
    }

    // Form validation
    $('form').on('submit', function(e) {
        var startDate = new Date($('#start_date').val());
        var endDate = new Date($('#end_date').val());
        
        if (endDate <= startDate) {
            e.preventDefault();
            alert('End date must be after start date');
            return false;
        }
    });
});
</script>