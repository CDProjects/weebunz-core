/**
 * WeeBunz Quiz Engine Public JavaScript
 * Handles quiz interaction, timer, and submission
 */

jQuery(document).ready(function($) {
    // Quiz container
    const $quizContainer = $('.weebunz-quiz-container');
    if (!$quizContainer.length) return;
    
    // Get quiz data
    const quizId = $quizContainer.data('quiz-id');
    const sessionId = $quizContainer.data('session-id');
    const timeLimit = $quizContainer.data('time-limit');
    
    // Quiz elements
    const $questionContainer = $('.weebunz-question-container');
    const $timerProgress = $('.weebunz-timer-progress');
    const $nextButton = $('.weebunz-next-question');
    const $submitButton = $('.weebunz-submit-quiz');
    const $currentQuestionNum = $('.weebunz-current-question');
    const $totalQuestions = $('.weebunz-total-questions');
    const $messagesContainer = $('.weebunz-quiz-messages');
    
    // Quiz state
    let currentQuestionIndex = 0;
    let selectedAnswerId = null;
    let userAnswers = [];
    let timerInterval = null;
    let timeRemaining = timeLimit;
    let quizCompleted = false;
    
    // Initialize quiz
    initQuiz();
    
    /**
     * Initialize the quiz
     */
    function initQuiz() {
        // Set total questions
        $totalQuestions.text(weebunzQuizData.questions.length);
        
        // Set up answer selection
        setupAnswerSelection();
        
        // Set up navigation buttons
        setupNavigation();
        
        // Start timer
        startTimer();
    }
    
    /**
     * Set up answer selection
     */
    function setupAnswerSelection() {
        $('.weebunz-answer-input').on('click', function() {
            // Remove selected class from all answers
            $('.weebunz-answer-input').removeClass('selected');
            
            // Add selected class to clicked answer
            $(this).addClass('selected');
            
            // Store selected answer ID
            selectedAnswerId = $(this).data('answer-id');
        });
    }
    
    /**
     * Set up navigation buttons
     */
    function setupNavigation() {
        // Next button click
        $nextButton.on('click', function() {
            if (selectedAnswerId === null) {
                showMessage('Please select an answer before proceeding.', 'error');
                return;
            }
            
            // Save user's answer
            saveAnswer();
            
            // Move to next question or submit quiz
            if (currentQuestionIndex < weebunzQuizData.questions.length - 1) {
                currentQuestionIndex++;
                loadQuestion(currentQuestionIndex);
            } else {
                // Show submit button instead of next button
                $nextButton.hide();
                $submitButton.show();
            }
        });
        
        // Submit button click
        $submitButton.on('click', function() {
            if (selectedAnswerId === null) {
                showMessage('Please select an answer before submitting.', 'error');
                return;
            }
            
            // Save final answer
            saveAnswer();
            
            // Submit quiz
            submitQuiz();
        });
    }
    
    /**
     * Save the current answer
     */
    function saveAnswer() {
        const currentQuestion = weebunzQuizData.questions[currentQuestionIndex];
        
        userAnswers.push({
            questionId: currentQuestion.id,
            answerId: selectedAnswerId
        });
        
        // Reset selected answer
        selectedAnswerId = null;
    }
    
    /**
     * Load a question by index
     */
    function loadQuestion(index) {
        const question = weebunzQuizData.questions[index];
        
        // Update question text
        $('.weebunz-question-input').val(question.question_text);
        
        // Update question ID
        $questionContainer.data('question-id', question.id);
        
        // Update answers
        const $answersContainer = $('.weebunz-answers-container');
        $answersContainer.empty();
        
        question.answers.forEach((answer, i) => {
            const optionLetter = String.fromCharCode(65 + i); // A, B, C, etc.
            
            const $answerRow = $(`
                <div class="weebunz-answer-row">
                    <div class="weebunz-logo-container left">
                        <img src="${weebunzQuizData.pluginUrl}public/assets/images/logo-placeholder.png" alt="EOR" class="weebunz-logo">
                    </div>
                    
                    <div class="weebunz-answer-option">
                        <input type="text" readonly value="Option ${optionLetter}" class="weebunz-answer-label">
                        <input type="text" readonly value="${answer.text}" class="weebunz-answer-input" data-answer-id="${answer.id}">
                    </div>
                    
                    <div class="weebunz-logo-container right">
                        <img src="${weebunzQuizData.pluginUrl}public/assets/images/logo-placeholder.png" alt="EOR" class="weebunz-logo">
                    </div>
                </div>
            `);
            
            $answersContainer.append($answerRow);
        });
        
        // Update question number
        $currentQuestionNum.text(index + 1);
        
        // Reset selected answer
        $('.weebunz-answer-input').removeClass('selected');
        
        // Re-setup answer selection
        setupAnswerSelection();
        
        // Clear any messages
        $messagesContainer.empty().hide();
    }
    
    /**
     * Start the quiz timer
     */
    function startTimer() {
        // Set initial time
        timeRemaining = timeLimit;
        
        // Update timer every second
        timerInterval = setInterval(function() {
            timeRemaining--;
            
            // Update timer progress bar
            const percentRemaining = (timeRemaining / timeLimit) * 100;
            $timerProgress.css('width', percentRemaining + '%');
            
            // Add warning class when time is running low
            if (timeRemaining <= 10) {
                $('.weebunz-timer-container').addClass('weebunz-timer-warning');
            }
            
            // Time's up
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                
                // If quiz not completed, submit it
                if (!quizCompleted) {
                    // Save current answer if selected
                    if (selectedAnswerId !== null) {
                        saveAnswer();
                    }
                    
                    submitQuiz();
                }
            }
        }, 1000);
    }
    
    /**
     * Submit the quiz
     */
    function submitQuiz() {
        // Mark quiz as completed to prevent double submission
        quizCompleted = true;
        
        // Stop timer
        clearInterval(timerInterval);
        
        // Disable buttons
        $nextButton.prop('disabled', true);
        $submitButton.prop('disabled', true);
        
        // Show loading message
        showMessage('Submitting your quiz...', 'info');
        
        // Submit answers via AJAX
        $.ajax({
            url: weebunz_quiz.ajax_url,
            type: 'POST',
            data: {
                action: 'weebunz_submit_quiz',
                nonce: weebunz_quiz.nonce,
                quiz_id: quizId,
                session_id: sessionId,
                answers: JSON.stringify(userAnswers),
                time_taken: timeLimit - timeRemaining
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showMessage('Quiz submitted successfully! Redirecting to results...', 'success');
                    
                    // Redirect to results page after a short delay
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 2000);
                } else {
                    // Show error message
                    showMessage('Error: ' + response.data.message, 'error');
                    
                    // Re-enable submit button
                    $submitButton.prop('disabled', false);
                }
            },
            error: function() {
                // Show error message
                showMessage('An error occurred while submitting your quiz. Please try again.', 'error');
                
                // Re-enable submit button
                $submitButton.prop('disabled', false);
            }
        });
    }
    
    /**
     * Show a message to the user
     */
    function showMessage(message, type) {
        $messagesContainer.removeClass('error success info')
            .addClass(type)
            .text(message)
            .show();
    }
});
