<?php
/**
 * Provide a public-facing view for the quiz display
 *
 * This file is used to markup the public-facing aspects of the quiz.
 * It follows the UI mockup provided by the client with a dark theme,
 * gold accents, and placeholder logos on either side of the answer options.
 *
 * @since      1.0.0
 */

// Get quiz data from the shortcode function
$quiz_id = $quiz->id;
$quiz_title = $quiz->title;
$quiz_description = $quiz->description;
$time_limit = $quiz->time_limit ? $quiz->time_limit : get_option('weebunz_quiz_time_limit', 60);
$difficulty = $quiz->difficulty;

// Get the first question
$current_question = $questions[0];

// Get answers for the first question
$answers = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}weebunz_answers 
    WHERE question_id = %d 
    ORDER BY order_num ASC, id ASC",
    $current_question->id
));
?>

<div class="weebunz-quiz-container" data-quiz-id="<?php echo esc_attr($quiz_id); ?>" data-session-id="<?php echo esc_attr($session_id); ?>" data-time-limit="<?php echo esc_attr($time_limit); ?>">
    <div class="weebunz-quiz-header">
        <h2 class="weebunz-quiz-title"><?php echo esc_html($difficulty); ?></h2>
    </div>
    
    <div class="weebunz-quiz-content">
        <div class="weebunz-question-container" data-question-id="<?php echo esc_attr($current_question->id); ?>">
            <div class="weebunz-question-text">
                <input type="text" readonly value="<?php echo esc_attr($current_question->question_text); ?>" class="weebunz-question-input">
            </div>
            
            <div class="weebunz-answers-container">
                <?php foreach ($answers as $index => $answer) : 
                    $option_letter = chr(65 + $index); // A, B, C, etc.
                ?>
                <div class="weebunz-answer-row">
                    <div class="weebunz-logo-container left">
                        <img src="<?php echo WEEBUNZ_QUIZ_PLUGIN_URL; ?>public/assets/images/logo-placeholder.png" alt="EOR" class="weebunz-logo">
                    </div>
                    
                    <div class="weebunz-answer-option">
                        <input type="text" readonly value="Option <?php echo esc_attr($option_letter); ?>" class="weebunz-answer-label">
                        <input type="text" readonly value="<?php echo esc_attr($answer->answer_text); ?>" class="weebunz-answer-input" data-answer-id="<?php echo esc_attr($answer->id); ?>">
                    </div>
                    
                    <div class="weebunz-logo-container right">
                        <img src="<?php echo WEEBUNZ_QUIZ_PLUGIN_URL; ?>public/assets/images/logo-placeholder.png" alt="EOR" class="weebunz-logo">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="weebunz-timer-container">
            <input type="text" readonly value="Timer" class="weebunz-timer-label">
            <div class="weebunz-timer-bar">
                <div class="weebunz-timer-progress"></div>
            </div>
        </div>
    </div>
    
    <div class="weebunz-quiz-navigation">
        <button type="button" class="weebunz-next-question"><?php _e('Next Question', 'weebunz-quiz-engine'); ?></button>
        <button type="button" class="weebunz-submit-quiz" style="display: none;"><?php _e('Submit Quiz', 'weebunz-quiz-engine'); ?></button>
    </div>
    
    <div class="weebunz-quiz-progress">
        <span class="weebunz-current-question">1</span> / <span class="weebunz-total-questions"><?php echo count($questions); ?></span>
    </div>
    
    <div class="weebunz-quiz-messages"></div>
</div>

<script>
    // Store all questions and answers for client-side processing
    var weebunzQuizData = {
        quizId: <?php echo json_encode($quiz_id); ?>,
        sessionId: <?php echo json_encode($session_id); ?>,
        timeLimit: <?php echo json_encode($time_limit); ?>,
        questions: <?php 
            $questions_data = array();
            foreach ($questions as $q) {
                $q_answers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}weebunz_answers 
                    WHERE question_id = %d 
                    ORDER BY order_num ASC, id ASC",
                    $q->id
                ));
                
                $answers_data = array();
                foreach ($q_answers as $a) {
                    $answers_data[] = array(
                        'id' => $a->id,
                        'text' => $a->answer_text,
                        'isCorrect' => (bool)$a->is_correct
                    );
                }
                
                $questions_data[] = array(
                    'id' => $q->id,
                    'text' => $q->question_text,
                    'type' => $q->question_type,
                    'points' => $q->points,
                    'answers' => $answers_data
                );
            }
            echo json_encode($questions_data);
        ?>
    };
</script>
