// File: wp-content/plugins/weebunz-core/admin/js/quiz-test.js

(function($) {
    'use strict';

    class QuizTestController {
        constructor() {
            console.log('QuizTestController constructor called');
            console.log('weebunzTest config:', window.weebunzTest);
            this.sessionId = null;
            this.currentQuestion = null;
            this.debugMode = true;
            this.simulateLag = false;
            this.forceErrors = false;
            this.loading = false;
            this.timer = null;
            this.quizState = {
                questionNumber: 0,
                totalQuestions: 0,
                correctAnswers: 0,
                timeLeft: 0,
                completed: false
            };
            this.answerSubmitted = false;
    
            $(document).ready(() => {
                console.log('Document ready in QuizTestController');
                this.bindEvents();
                this.initializeInterface();
            
                // Debug checks
                console.log('Quiz type select exists:', $('#quiz_type').length > 0);
                console.log('Start button exists:', $('#start-quiz').length > 0);
                console.log('Start button disabled:', $('#start-quiz').prop('disabled'));
            }); // Properly close the document ready function
        } // Properly close the constructor
    
        async testApiConnection() {
            try {
                console.log('Testing API connection...');
                
                if (!window.weebunzTest || !window.weebunzTest.apiEndpoint) {
                    console.error('API configuration missing');
                    this.log('API configuration missing', 'error');
                    return;
                }
                
                const url = `${window.weebunzTest.apiEndpoint}/test`;
                console.log(`Making test request to: ${url}`);
                
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': window.weebunzTest.nonce
                    },
                    credentials: 'same-origin'
                });
                
                console.log(`Test response status: ${response.status}`);
                const responseText = await response.text();
                console.log('Test response text:', responseText);
                
                try {
                    const data = JSON.parse(responseText);
                    console.log('Parsed test response:', data);
                    this.log('API test successful', 'success');
                } catch (e) {
                    console.error('Failed to parse test response:', e);
                    this.log(`Failed to parse test response: ${e.message}`, 'error');
                }
            } catch (error) {
                console.error('API test failed:', error);
                this.log(`API test failed: ${error.message}`, 'error');
            }
        }

        bindEvents() {
            console.log('Binding events...');

            $('#test-api').on('click', (e) => {
                e.preventDefault();
                console.log('Test API button clicked');
                this.testApiConnection();
            });
            
            // Quiz type selection with direct handler
            $('#quiz_type').on('change', (e) => {
                console.log('Direct change event on quiz type');
                this.handleQuizTypeChange(e);
            });

            // Start/Reset quiz
            $('#start-quiz').on('click', (e) => {
                e.preventDefault(); // Add this
                console.log('Start Quiz button clicked');
                if (typeof this.handleStartQuiz === 'function') {
                    console.log('handleStartQuiz is a function, calling it...');
                    this.handleStartQuiz();
                } else {
                    console.error('handleStartQuiz is not a function:', this.handleStartQuiz);
                }
            });

            // Test controls
            $('#skip-question').on('click', () => this.skipQuestion());
            $('#force-timeout').on('click', () => this.forceTimeout());
            $('#simulate-disconnect').on('click', () => this.simulateDisconnect());
            $('#clear-session').on('click', () => this.clearSession());

            // Debug controls
            $('#clear-log').on('click', () => this.clearLog());
            $('#export-log').on('click', () => this.exportLog());

            // Test option toggles
            $('#debug-mode').on('change', (e) => {
                this.debugMode = e.target.checked;
                this.log('Debug mode ' + (this.debugMode ? 'enabled' : 'disabled'));
            });
            $('#simulate-lag').on('change', (e) => {
                this.simulateLag = e.target.checked;
                this.log('Network lag simulation ' + (this.simulateLag ? 'enabled' : 'disabled'));
            });
            $('#force-errors').on('change', (e) => {
                this.forceErrors = e.target.checked;
                this.log('Random errors ' + (this.forceErrors ? 'enabled' : 'disabled'));
            });
            $('#bypass-timer').on('change', (e) => {
                this.bypassTimer = e.target.checked;
                this.log('Timer bypass ' + (this.bypassTimer ? 'enabled' : 'disabled'));
            });
        }

        initializeInterface() {
            this.log('Quiz test interface initialized', 'info');
        }

        handleQuizTypeChange(e) {
            console.log('Quiz type change event triggered');
            this.log('Quiz type change triggered', 'debug');
            const $selected = $(e.target).find('option:selected');
            console.log('Selected option:', $selected);
    
            if ($selected.val()) {
                const quizInfo = {
                    difficulty: $selected.data('difficulty'),
                    questionCount: $selected.data('questions'),
                    timeLimit: $selected.data('time')
                };
        
                console.log('About to enable Start Quiz button');
                const $startButton = $('#start-quiz');
                console.log('Start button found:', $startButton.length > 0);
                console.log('Button disabled state before:', $startButton.prop('disabled'));
        
                $startButton.prop('disabled', false);
        
                console.log('Button disabled state after:', $startButton.prop('disabled'));
                this.updateQuizInfo(quizInfo);
                this.log(`Selected quiz type: ${$selected.text()}`);
            } else {
                $('#start-quiz').prop('disabled', true);
                $('#quiz-type-details').addClass('hidden');
            }
        }

        updateQuizInfo(info) {
            $('#quiz-type-details').removeClass('hidden')
                .find('#question-count').text(info.questionCount);
            $('#time-limit').text(info.timeLimit);
        }

        async handleStartQuiz() {
            try {
                console.log('handleStartQuiz called');
                
                // Prevent multiple clicks
                if (this.loading) {
                    console.log('Already loading, ignoring click');
                    return;
                }

                if (this.sessionId) {
                    console.log('Session already exists, resetting');
                    await this.handleResetQuiz();
                    return;
                }

                const quizTypeId = $('#quiz_type').val();
                console.log(`Selected quiz type ID: ${quizTypeId}`);
                
                if (!quizTypeId) {
                    console.log('No quiz type selected');
                    this.log('No quiz type selected', 'error');
                    return;
                }

                this.setLoading(true);
                this.log('Starting quiz...', 'info');

                // Disable start button immediately
                $('#start-quiz').prop('disabled', true);
                console.log('Start button disabled for request');

                try {
                    console.log('Making quiz/start request');
                    const response = await this.makeRequest('POST', 'quiz/start', {
                        quiz_id: quizTypeId
                    });
                    console.log('Quiz start response received:', response);

                    if (response.success) {
                        console.log(`Session ID received: ${response.session_id}`);
                        this.sessionId = response.session_id;
                        
                        console.log('Setting quiz state from response:', response.quiz_info);
                        this.quizState.totalQuestions = response.quiz_info.total_questions;
                        this.quizState.timeLimit = response.quiz_info.time_limit;
                        
                        this.updateSessionInfo(response);
                        this.log('Quiz started successfully', 'success');
                        
                        console.log('Updating UI elements');
                        $('#quiz-interface').removeClass('hidden');
                        $('#start-quiz').text('End Quiz').prop('disabled', false);
                        $('#reset-quiz').prop('disabled', false);
                        $('.quiz-test-controls').removeClass('hidden');

                        console.log('About to fetch first question');
                        await this.fetchNextQuestion();
                        console.log('First question fetched successfully');
                    } else {
                        console.error('Quiz start failed:', response);
                        this.log(`Quiz start failed: ${response.message || 'Unknown error'}`, 'error');
                    }
                } catch (error) {
                    console.error('Error in handleStartQuiz:', error);
                    this.log(`Error in handleStartQuiz: ${error.message}`, 'error');
                    this.handleError(error);
                    $('#start-quiz').prop('disabled', false);
                }
            } catch (error) {
                console.error('Outer error in handleStartQuiz:', error);
                this.log(`Outer error in handleStartQuiz: ${error.message}`, 'error');
                this.handleError(error);
                $('#start-quiz').prop('disabled', false);
            } finally {
                console.log('handleStartQuiz completed');
                this.setLoading(false);
            }
        }

        async handleResetQuiz() {
            try {
                console.log('handleResetQuiz called');
                this.setLoading(true);
        
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
        
                if (this.sessionId) {
                    await this.clearSession();
                }
        
                // Reset all state
                this.sessionId = null;
                this.currentQuestion = null;
                this.answerSubmitted = false;
                this.quizState = {
                    questionNumber: 0,
                    totalQuestions: 0,
                    correctAnswers: 0,
                    timeLeft: 0,
                    completed: false
                };

                // Reset UI elements
                $('#quiz-interface').addClass('hidden');
                $('#start-quiz').text('Start Quiz');
                $('#reset-quiz').prop('disabled', true);
                $('.quiz-test-controls').addClass('hidden');
                $('#quiz-display').empty();
                $('.timer-display').empty();
                $('.answer-feedback').remove();
        
                // Re-enable controls
                $('#quiz_type').prop('disabled', false);
        
                this.updateSessionInfo(null);
                this.log('Quiz reset completed', 'info');
                console.log('Quiz reset completed');
        
            } catch (error) {
                console.error('Error in handleResetQuiz:', error);
                this.handleError(error);
            } finally {
                this.setLoading(false);
            }
        }

        async fetchNextQuestion() {
            try {
                console.log('fetchNextQuestion called');
                console.log(`Current session ID: ${this.sessionId}`);
                
                this.log('Fetching next question...', 'debug');
                
                console.log('Making quiz/question request');
                const response = await this.makeRequest('GET', 'quiz/question');
                console.log('Question response received:', response);
                
                this.log('Question response received', 'debug');
                this.log(JSON.stringify(response), 'debug');
                
                if (response.completed) {
                    console.log('Quiz completed, handling completion');
                    await this.handleQuizComplete();
                    return;
                }

                if (!response.question) {
                    console.error('No question data in response');
                    throw new Error('No question data in response');
                }

                console.log('Question data:', response.question);
                this.currentQuestion = response.question;
                this.displayQuestion(response.question);
                this.startTimer(response.question.time_limit);
                
                this.log('Question displayed successfully', 'debug');
            } catch (error) {
                console.error('Error fetching question:', error);
                this.log('Error fetching question: ' + error.message, 'error');
                this.handleError(error);
            }
        }

        displayQuestion(question) {
            console.log('displayQuestion called with:', question);
            const $display = $('#quiz-display');
            $display.empty();

            if (!question || !question.answers) {
                console.error('Invalid question data:', question);
                this.log('Invalid question data', 'error');
                return;
            }

            this.answerSubmitted = false; // Reset flag for new question
            this.log('Displaying question', {
                id: question.id,
                text: question.question_text,
                answers_count: question.answers.length
            });

            console.log('Building question HTML');
            const html = `
                <div class="question-container">
                    <h3>Question ${question.question_number} of ${question.total_questions}</h3>
                    <p class="question-text">${question.question_text}</p>
                    <div class="answer-options">
                        ${question.answers.map((answer, index) => `
                            <button class="answer-option button button-secondary w-full mb-2 text-left py-3 px-4" 
                                    data-answer-id="${answer.id}">
                                ${String.fromCharCode(65 + index)}. ${answer.answer_text}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;

            $display.html(html);
            console.log('Question HTML added to display');

            // Remove any existing click handlers first
            $('.answer-option').off('click');
            
            // Add new click handlers
            $('.answer-option').on('click', async (e) => {
                e.preventDefault();
                const $button = $(e.currentTarget);
                
                console.log('Answer button clicked:', $button.data('answer-id'));
                
                if (this.answerSubmitted) {
                    console.log('Answer already submitted, ignoring click');
                    this.log('Answer already submitted, ignoring click');
                    return;
                }

                this.log('Answer button clicked', {
                    answerId: $button.data('answer-id'),
                    questionId: this.currentQuestion.id
                });

                // Disable all buttons immediately
                $('.answer-option').prop('disabled', true);
                this.answerSubmitted = true;

                // Add visual feedback for selected answer
                $button.addClass('selected');
                
                console.log('Submitting answer');
                await this.handleAnswerSubmit($button.data('answer-id'));
            });
            
            console.log('Answer click handlers attached');
        }

        async handleAnswerSubmit(answerId) {
            console.log('handleAnswerSubmit called with answerId:', answerId);
            
            if (!this.currentQuestion || !this.sessionId || this.answerSubmitted) {
                console.error('Cannot submit answer - invalid state', {
                    currentQuestion: !!this.currentQuestion,
                    sessionId: !!this.sessionId,
                    answerSubmitted: this.answerSubmitted
                });
                this.log('Cannot submit answer - invalid state', 'error');
                return;
            }

            try {
                this.answerSubmitted = true;
                this.setLoading(true);

                // Stop the timer
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }

                // Disable all buttons immediately
                $('.answer-option').prop('disabled', true);

                const timeTaken = this.currentQuestion.time_limit - (this.quizState.timeLeft || 0);
        
                this.log('Submitting answer', {
                    question_id: this.currentQuestion.id,
                    answer_id: answerId,
                    time_taken: timeTaken,
                    session_id: this.sessionId
                });
                
                console.log('Making answer submission request with data:', {
                    session_id: this.sessionId,
                    question_id: this.currentQuestion.id,
                    answer_id: answerId,
                    time_taken: timeTaken
                });

                const response = await this.makeRequest('POST', 'quiz/answer', {
                    question_id: this.currentQuestion.id,
                    answer_id: answerId,
                    time_taken: timeTaken
                });
                
                console.log('Answer submission response:', response);

                if (response.success) {
                    console.log('Answer submission successful, processing result');
                    if (response.result.is_correct) {
                        this.quizState.correctAnswers++;
                        console.log('Answer was correct, updated score:', this.quizState.correctAnswers);
                    }
            
                    await this.showAnswerFeedback(response.result.is_correct);
            
                    if (response.result.quiz_completed) {
                        console.log('Quiz completed according to result, handling completion');
                        await this.handleQuizComplete();
                    } else {
                        console.log('Fetching next question after answer');
                        await this.fetchNextQuestion();
                    }
                } else {
                    console.error('Answer submission response not successful:', response);
                }

            } catch (error) {
                console.error('Failed to submit answer:', error);
                this.log(`Failed to submit answer: ${error.message}`, 'error');
                // Re-enable buttons on error
                $('.answer-option').prop('disabled', false);
                this.answerSubmitted = false;
            } finally {
                this.setLoading(false);
            }
        }

        async showAnswerFeedback(isCorrect) {
            console.log('Showing answer feedback, correct:', isCorrect);
            $('.answer-feedback').remove();
    
            const $feedback = $('<div>')
                .addClass(`answer-feedback ${isCorrect ? 'correct' : 'incorrect'}`)
                .text(isCorrect ? 'Correct!' : 'Incorrect')
                .insertAfter('#quiz-display');

            return new Promise(resolve => {
                setTimeout(() => {
                    $feedback.remove();
                    resolve();
                }, 1500);
            });
        }

        startTimer(timeLimit) {
            console.log('Starting timer with limit:', timeLimit);
            if (this.timer) {
                clearInterval(this.timer);
            }

            this.quizState.timeLeft = this.bypassTimer ? 999 : timeLimit;
            this.updateTimerDisplay();

            this.timer = setInterval(() => {
                this.quizState.timeLeft--;
                this.updateTimerDisplay();

                if (this.quizState.timeLeft <= 0) {
                    clearInterval(this.timer);
                    this.handleTimeout();
                }
            }, 1000);
        }

        updateTimerDisplay() {
            $('.timer-display').text(`Time remaining: ${this.quizState.timeLeft}s`)
                .toggleClass('text-danger', this.quizState.timeLeft <= 5);
        }

        async handleTimeout() {
            console.log('Question timed out');
            this.log('Question timed out', 'warning');
    
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
    
            // Disable all buttons and remove click handlers
            $('.answer-option').prop('disabled', true).off('click');
    
            await this.showAnswerFeedback(false, 'Time Out!');
            await this.fetchNextQuestion();
        }

        async handleQuizComplete() {
            try {
                console.log('handleQuizComplete called');
                console.log('Making quiz/complete request');
                const response = await this.makeRequest('POST', 'quiz/complete');
                console.log('Quiz completion response:', response);
                
                if (response.success) {
                    console.log('Quiz completed successfully, displaying results');
                    this.displayResults(response.results);
                    this.quizState.completed = true;
                    this.log('Quiz completed successfully', 'success');
                } else {
                    console.error('Quiz completion response not successful:', response);
                }
            } catch (error) {
                console.error('Error completing quiz:', error);
                this.handleError(error);
            }
        }

        displayResults(results) {
            console.log('Displaying quiz results:', results);
            const $display = $('#quiz-display');
            $display.empty();
            $('.timer-display').empty();

            const html = `
                <div class="results-container text-center">
                    <h2>Quiz Complete!</h2>
                    <div class="results-summary">
                        <p class="score">Score: ${this.quizState.correctAnswers} / ${this.quizState.totalQuestions}</p>
                        <p class="percentage">
                            ${Math.round((this.quizState.correctAnswers / this.quizState.totalQuestions) * 100)}%
                        </p>
                        <p class="entries">Entries Earned: ${results.entries_earned}</p>
                    </div>
                </div>
            `;

            $display.html(html);
            console.log('Results displayed');
        }

        async skipQuestion() {
            console.log('skipQuestion called');
            this.log('Skipping question', 'warning');
            if (this.currentQuestion) {
                await this.handleAnswerSubmit(null);
            }
        }

        async forceTimeout() {
            console.log('forceTimeout called');
            this.log('Forcing timeout', 'warning');
            if (this.timer) {
                clearInterval(this.timer);
                this.quizState.timeLeft = 0;
                this.updateTimerDisplay();
                await this.handleTimeout();
            }
        }

        async simulateDisconnect() {
            console.log('simulateDisconnect called');
            this.log('Simulating network disconnect', 'warning');
            this.simulateLag = true;
            setTimeout(() => {
                this.simulateLag = false;
                this.log('Network connection restored', 'info');
            }, 5000);
        }

        async clearSession() {
            console.log('clearSession called, sessionId:', this.sessionId);
            if (this.sessionId) {
                try {
                    console.log('Attempting to clear session');
                    await this.makeRequest('POST', 'quiz/session/clear', {
                        session_id: this.sessionId
                    });
                    
                    this.sessionId = null;
                    this.updateSessionInfo(null);
                    this.log('Session cleared', 'warning');
                    console.log('Session cleared successfully');
                } catch (error) {
                    console.error('Error clearing session:', error);
                    this.handleError(error);
                }
            }
        }

        setLoading(isLoading) {
            console.log('setLoading called with:', isLoading);
            this.loading = isLoading;
            $('#quiz-interface')[isLoading ? 'addClass' : 'removeClass']('loading');
            $('#start-quiz').prop('disabled', isLoading);

            // Add debug logging
            this.log(`Loading state: ${isLoading ? 'enabled' : 'disabled'}`, 'debug');
        }

        async makeRequest(method, endpoint, data = null) {
            try {
                console.log(`makeRequest called: ${method} ${endpoint}`);
                
                if (!window.weebunzTest || !window.weebunzTest.apiEndpoint) {
                    console.log('API configuration missing');
                    throw new Error('API configuration missing');
                }

                const url = `${window.weebunzTest.apiEndpoint}/${endpoint}`;
                console.log(`Making ${method} request to ${url}`);
                this.log(`Making ${method} request to ${endpoint}`, 'debug');
                if (data) {
                    console.log('Request data:', data);
                    this.log(`Request data: ${JSON.stringify(data)}`, 'debug');
                }

                const config = {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.weebunzTest.nonce
                    },
                    credentials: 'same-origin'
                };

                if (this.sessionId) {
                    config.headers['X-Quiz-Session'] = this.sessionId;
                    console.log(`Including session ID in headers: ${this.sessionId}`);
                    this.log(`Including session ID: ${this.sessionId}`, 'debug');
                }

                if (data) {
                    config.body = JSON.stringify(data);
                }

                if (this.simulateLag) {
                    const delay = Math.random() * 2000;
                    await new Promise(resolve => setTimeout(resolve, delay));
                }

                console.log('Fetch request config:', config);
                const response = await fetch(url, config);
                console.log(`Response status: ${response.status}`);
                this.log(`Response status: ${response.status}`, 'debug');
                
                // Get the raw response text first
                const responseText = await response.text();
                console.log('Raw response text:', responseText);
                this.log(`Raw response: ${responseText}`, 'debug');
                
                // Then try to parse it as JSON
                let responseData;
                try {
                    responseData = JSON.parse(responseText);
                    console.log('Parsed response data:', responseData);
                    this.log(`Parsed response data: ${JSON.stringify(responseData)}`, 'debug');
                } catch (e) {
                    console.error('JSON parse error:', e);
                    this.log(`Failed to parse JSON response: ${e.message}`, 'error');
                    throw new Error(`Invalid JSON response: ${responseText}`);
                }

                if (!response.ok) {
                    const error = new Error(
                        responseData?.message || 
                        responseData?.error || 
                        `Request failed with status ${response.status}`
                    );
                    error.response = responseData;
                    throw error;
                }

                return responseData;

            } catch (error) {
                console.error('Request error:', error);
                this.log(`Request error: ${error.message}`, 'error');
                throw error;
            }
        }

        updateSessionInfo(data) {
            console.log('updateSessionInfo called with:', data);
            const sessionInfo = $('#session-data');
            if (data) {
                sessionInfo.html(JSON.stringify(data, null, 2));
            } else {
                sessionInfo.html('No active session');
            }
        }

        clearLog() {
            console.log('clearLog called');
            $('#debug-log').empty();
            this.log('Debug log cleared', 'info');
        }

        exportLog() {
            console.log('exportLog called');
            const logContent = Array.from($('#debug-log').children())
                .map(el => el.textContent)
                .reverse()
                .join('\n');

            const blob = new Blob([logContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `quiz-test-log-${new Date().toISOString()}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
            
            this.log('Debug log exported', 'info');
        }

        log(message, type = 'info') {
            if (!this.debugMode && type === 'debug') {
                return;
            }

            const debugLog = $('#debug-log');
            const timestamp = new Date().toISOString();
            const formattedMessage = `[${timestamp}] ${message}`;
            
            const logEntry = $('<div>')
                .addClass(type)
                .text(formattedMessage);
            
            debugLog.prepend(logEntry);

            if (debugLog.children().length > 100) {
                debugLog.children().last().remove();
            }

            if (this.debugMode) {
                console.log(`[Quiz Test] ${message}`);
            }
        }

        handleError(error) {
            console.error('handleError called with:', error);
            this.log(`Error: ${error.message}`, 'error');
            console.error('[Quiz Test]', error);
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        window.WeebunzQuizTest = new QuizTestController();
    });

    // Monitor state changes
    $(document).on('quiz:state:change', (event, newState) => {
        if (window.WeebunzQuizTest) {
            window.WeebunzQuizTest.log(`Quiz state changed: ${JSON.stringify(newState)}`, 'debug');
        }
    });

    // Monitor API calls
    $(document).on('quiz:api:call', (event, details) => {
        if (window.WeebunzQuizTest) {
            window.WeebunzQuizTest.log(`API call: ${details.endpoint}`, 'debug');
        }
    });

    // Monitor errors
    $(document).on('quiz:error', (event, error) => {
        if (window.WeebunzQuizTest) {
            window.WeebunzQuizTest.log(`Error occurred: ${error.message}`, 'error');
        }
    });

    // Performance monitoring
    const performanceMonitor = {
        startTime: null,
        questionTimes: [],

        start() {
            this.startTime = performance.now();
            this.questionTimes = [];
        },

        recordQuestion() {
            if (this.startTime) {
                this.questionTimes.push(performance.now() - this.startTime);
            }
        },

        getStats() {
            if (!this.questionTimes.length) return null;

            const total = this.questionTimes.reduce((a, b) => a + b, 0);
            return {
                averageTime: total / this.questionTimes.length,
                totalTime: total,
                questionCount: this.questionTimes.length
            };
        },

        reset() {
            this.startTime = null;
            this.questionTimes = [];
        }
    };

    // Add performance monitoring to QuizTestController
    Object.defineProperty(QuizTestController.prototype, 'performance', {
        get: function() {
            return performanceMonitor;
        }
    });

    // Global error handler
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        if (window.WeebunzQuizTest) {
            window.WeebunzQuizTest.log(`Global error: ${msg}`, 'error');
        }
        console.error('Global error:', msg, url, lineNo, columnNo, error);
        return false;
    };

    // Global promise rejection handler
    window.onunhandledrejection = function(event) {
        if (window.WeebunzQuizTest) {
            window.WeebunzQuizTest.log(`Unhandled promise rejection: ${event.reason}`, 'error');
        }
        console.error('Unhandled promise rejection:', event.reason);
    };

    // Initialize when document is ready
    $(document).ready(() => {
        console.log('Initializing WeeBunz Quiz Test...');
        try {
            window.WeebunzQuizTest = new QuizTestController();
            console.log('WeeBunz Quiz Test initialized successfully');
        } catch (error) {
            console.error('Failed to initialize WeeBunz Quiz Test:', error);
        }
    });

})(jQuery);