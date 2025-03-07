// File: wp-content/plugins/weebunz-core/public/js/quiz-components.js
const { React, ReactDOM } = window;
const { useState, useEffect } = React;

// QuizTimer Component
const QuizTimer = ({ duration, onTimeout, className = "" }) => {
    const [timeLeft, setTimeLeft] = useState(duration);
    
    useEffect(() => {
        if (timeLeft <= 0) {
            onTimeout();
            return;
        }
        
        const timer = setInterval(() => {
            setTimeLeft(prev => Math.max(0, prev - 1));
        }, 1000);
        
        return () => clearInterval(timer);
    }, [timeLeft, onTimeout]);
    
    // Time warning thresholds
    const warningClass = timeLeft < duration * 0.25 ? "danger" : 
                        timeLeft < duration * 0.5 ? "warning" : "";
    
    return React.createElement("div", { className: `quiz-timer ${className}` },
        React.createElement("div", { 
            className: `time-display ${warningClass}` 
        }, `Time remaining: ${timeLeft}s`),
        React.createElement("div", { className: "progress-bar" },
            React.createElement("div", {
                className: `progress ${warningClass}`,
                style: { width: `${(timeLeft / duration) * 100}%` }
            })
        )
    );
};

// QuizQuestion Component
const QuizQuestion = ({ question, answers, onAnswer, selectedAnswer, className = "" }) => {
    return React.createElement("div", { className: `quiz-question ${className}` },
        React.createElement("h3", { className: "question-text" }, question),
        React.createElement("div", { className: "answers-grid" },
            answers.map((answer) => 
                React.createElement("button", {
                    key: answer.id,
                    onClick: () => onAnswer(answer.id),
                    disabled: selectedAnswer !== null,
                    className: `answer-button ${selectedAnswer === answer.id ? 'selected' : ''}`
                }, answer.answer_text)
            )
        )
    );
};

// Main QuizPlayer Component
const QuizPlayer = ({ debug = false, onAnswer, onComplete, onError, session = null }) => {
    const [state, setState] = useState({
        loaded: false,
        currentQuestion: null,
        timeLeft: 0,
        questionNumber: 0,
        totalQuestions: 0,
        selectedAnswer: null,
        error: null,
        completed: false,
        results: null
    });

    // Initialize when component mounts
    useEffect(() => {
        setState(prevState => ({ ...prevState, loaded: true }));
        
        // If we have a session, fetch the first question
        if (session) {
            fetchQuestion();
        }
    }, [session]);

    // Fetch current question
    const fetchQuestion = async () => {
        try {
            if (debug) console.log('Fetching question with session:', session);
            
            const response = await fetch('/wp-json/weebunz/v1/quiz/question', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.weebunzTest?.nonce,
                    'X-Quiz-Session': session
                }
            });
            
            const data = await response.json();
            
            if (debug) console.log('Question response:', data);
            
            if (data.success) {
                if (data.completed) {
                    setState(prevState => ({
                        ...prevState,
                        completed: true,
                        currentQuestion: null
                    }));
                    return;
                }
                
                setState(prevState => ({
                    ...prevState,
                    currentQuestion: data.question,
                    timeLeft: data.question.time_limit,
                    questionNumber: data.question.question_number,
                    totalQuestions: data.question.total_questions,
                    answers: data.question.answers,
                    selectedAnswer: null
                }));
            } else {
                setState(prevState => ({
                    ...prevState,
                    error: data.message || 'Failed to fetch question'
                }));
                if (onError) onError(data.message);
            }
        } catch (error) {
            console.error('Error fetching question:', error);
            setState(prevState => ({
                ...prevState,
                error: error.message
            }));
            if (onError) onError(error.message);
        }
    };

    // Handle answer selection
    const handleAnswer = async (answerId) => {
        if (state.selectedAnswer) return; // Prevent multiple submissions
        
        setState(prevState => ({
            ...prevState,
            selectedAnswer: answerId
        }));
        
        try {
            const timeTaken = state.currentQuestion.time_limit - state.timeLeft;
            
            if (debug) console.log('Submitting answer:', answerId, 'Time taken:', timeTaken);
            
            const response = await fetch('/wp-json/weebunz/v1/quiz/answer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.weebunzTest?.nonce,
                    'X-Quiz-Session': session
                },
                body: JSON.stringify({
                    question_id: state.currentQuestion.id,
                    answer_id: answerId,
                    time_taken: timeTaken
                })
            });
            
            const data = await response.json();
            
            if (debug) console.log('Answer response:', data);
            
            if (data.success) {
                if (onAnswer) onAnswer(data.result);
                
                // Show feedback briefly before moving on
                setTimeout(() => {
                    if (data.result.quiz_completed) {
                        completeQuiz();
                    } else {
                        fetchQuestion();
                    }
                }, 1500);
            } else {
                setState(prevState => ({
                    ...prevState,
                    error: data.message || 'Failed to submit answer'
                }));
                if (onError) onError(data.message);
            }
        } catch (error) {
            console.error('Error submitting answer:', error);
            setState(prevState => ({
                ...prevState,
                error: error.message
            }));
            if (onError) onError(error.message);
        }
    };

    // Handle timeout
    const handleTimeout = () => {
        setState(prevState => ({
            ...prevState,
            selectedAnswer: 'timeout'
        }));
        
        setTimeout(() => {
            fetchQuestion();
        }, 1500);
    };

    // Complete quiz
    const completeQuiz = async () => {
        try {
            if (debug) console.log('Completing quiz with session:', session);
            
            const response = await fetch('/wp-json/weebunz/v1/quiz/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.weebunzTest?.nonce,
                    'X-Quiz-Session': session
                }
            });
            
            const data = await response.json();
            
            if (debug) console.log('Complete quiz response:', data);
            
            if (data.success) {
                setState(prevState => ({
                    ...prevState,
                    completed: true,
                    results: data.results,
                    currentQuestion: null
                }));
                
                if (onComplete) onComplete(data.results);
            } else {
                setState(prevState => ({
                    ...prevState,
                    error: data.message || 'Failed to complete quiz'
                }));
                if (onError) onError(data.message);
            }
        } catch (error) {
            console.error('Error completing quiz:', error);
            setState(prevState => ({
                ...prevState,
                error: error.message
            }));
            if (onError) onError(error.message);
        }
    };

    // Render error state
    if (state.error) {
        return React.createElement("div", { className: "quiz-error" },
            React.createElement("h3", null, "Error"),
            React.createElement("p", null, state.error),
            debug && React.createElement("pre", null, JSON.stringify(state, null, 2))
        );
    }

    // Render loading state
    if (!state.loaded || (!state.currentQuestion && !state.completed)) {
        return React.createElement("div", { className: "quiz-loading" },
            React.createElement("p", null, "Loading quiz...")
        );
    }

    // Render completed state
    if (state.completed) {
        return React.createElement("div", { className: "quiz-completed" },
            React.createElement("h3", null, "Quiz Completed!"),
            state.results && React.createElement("div", { className: "quiz-results" },
                React.createElement("p", { className: "score" },
                    `Score: ${state.results.correct_answers} / ${state.results.total_questions}`
                ),
                React.createElement("p", { className: "entries" },
                    `Entries Earned: ${state.results.entries_earned}`
                )
            )
        );
    }

    // Render current question
    return React.createElement("div", { className: "quiz-container" },
        // Question progress
        React.createElement("div", { className: "question-progress" },
            `Question ${state.questionNumber} of ${state.totalQuestions}`
        ),
        
        // Question and timer
        React.createElement("div", { className: "question-content" },
            // Question text
            React.createElement("h3", { className: "question-text" }, 
                state.currentQuestion.question_text
            ),
            
            // Timer
            React.createElement("div", { className: "timer-display" },
                `Time Left: ${state.timeLeft}s`
            )
        ),
        
        // Answer options
        React.createElement("div", { className: "answer-options" },
            state.currentQuestion.answers.map(answer => 
                React.createElement("button", {
                    key: answer.id,
                    className: `answer-option ${state.selectedAnswer === answer.id ? 'selected' : ''}`,
                    onClick: () => handleAnswer(answer.id),
                    disabled: state.selectedAnswer !== null
                }, answer.answer_text)
            )
        ),
        
        // Debug info
        debug && React.createElement("pre", { className: "debug-info" }, 
            JSON.stringify(state, null, 2)
        )
    );
};

// Export components to global scope
window.WeebunzQuiz = {
    QuizPlayer,
    QuizTimer,
    QuizQuestion
};