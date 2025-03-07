// File: wp-content/plugins/weebunz-core/public/js/quiz-components.js

// Check if React and ReactDOM are available globally
const React = window.React || {};
const ReactDOM = window.ReactDOM || {};

// Simple quiz player component
const QuizPlayer = props => {
    const { session, debug = false, onAnswer, onComplete, onError } = props;
    
    // State
    const [state, setState] = React.useState({
        loading: true,
        error: null,
        currentQuestion: null,
        timeLeft: 0,
        questionNumber: 0,
        totalQuestions: 0,
        selectedAnswer: null,
        completed: false,
        results: null
    });
    
    // Effect to fetch first question when component mounts
    React.useEffect(() => {
        if (session) {
            fetchQuestion();
        } else if (debug) {
            console.log('No session provided to QuizPlayer');
        }
    }, [session]);
    
    // Timer effect
    React.useEffect(() => {
        let timer = null;
        
        if (state.timeLeft > 0 && state.currentQuestion) {
            timer = setInterval(() => {
                setState(prev => ({
                    ...prev,
                    timeLeft: prev.timeLeft - 1
                }));
            }, 1000);
        } else if (state.timeLeft === 0 && state.currentQuestion) {
            // Handle timeout
            setState(prev => ({...prev, selectedAnswer: 'timeout'}));
            setTimeout(fetchQuestion, 1500);
        }
        
        return () => {
            if (timer) clearInterval(timer);
        };
    }, [state.timeLeft, state.currentQuestion]);
    
    // Fetch question
    const fetchQuestion = async () => {
        try {
            if (debug) console.log('Fetching question with session:', session);
            
            setState(prev => ({...prev, loading: true, selectedAnswer: null}));
            
            const response = await fetch('/wp-json/weebunz/v1/quiz/question', {
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.weebunzTest?.nonce,
                    'X-Quiz-Session': session
                }
            });
            
            const data = await response.json();
            
            if (debug) console.log('Question data:', data);
            
            if (data.success) {
                if (data.completed) {
                    if (debug) console.log('Quiz completed');
                    completeQuiz();
                    return;
                }
                
                setState(prev => ({
                    ...prev,
                    loading: false,
                    currentQuestion: data.question,
                    timeLeft: parseInt(data.question.time_limit),
                    questionNumber: data.question.question_number,
                    totalQuestions: data.question.total_questions
                }));
            } else {
                setState(prev => ({
                    ...prev, 
                    loading: false,
                    error: data.message || 'Failed to fetch question'
                }));
                
                if (onError) onError(data.message || 'Failed to fetch question');
            }
        } catch (error) {
            console.error('Error fetching question:', error);
            setState(prev => ({
                ...prev, 
                loading: false,
                error: error.message
            }));
            
            if (onError) onError(error.message);
        }
    };
    
    // Handle answer selection
    const handleAnswer = async (answerId) => {
        try {
            if (state.selectedAnswer) return; // Prevent multiple submissions
            
            setState(prev => ({...prev, selectedAnswer: answerId}));
            
            const timeTaken = parseInt(state.currentQuestion.time_limit) - state.timeLeft;
            
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
                
                setTimeout(() => {
                    if (data.result.quiz_completed) {
                        completeQuiz();
                    } else {
                        fetchQuestion();
                    }
                }, 1500);
            } else {
                setState(prev => ({
                    ...prev,
                    error: data.message || 'Failed to submit answer'
                }));
                
                if (onError) onError(data.message || 'Failed to submit answer');
            }
        } catch (error) {
            console.error('Error submitting answer:', error);
            setState(prev => ({
                ...prev,
                error: error.message
            }));
            
            if (onError) onError(error.message);
        }
    };
    
    // Complete quiz
    const completeQuiz = async () => {
        try {
            if (debug) console.log('Completing quiz');
            
            const response = await fetch('/wp-json/weebunz/v1/quiz/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.weebunzTest?.nonce,
                    'X-Quiz-Session': session
                }
            });
            
            const data = await response.json();
            
            if (debug) console.log('Quiz completion response:', data);
            
            if (data.success) {
                setState(prev => ({
                    ...prev,
                    completed: true,
                    results: data.results,
                    currentQuestion: null,
                    loading: false
                }));
                
                if (onComplete) onComplete(data.results);
            } else {
                setState(prev => ({
                    ...prev,
                    error: data.message || 'Failed to complete quiz',
                    loading: false
                }));
                
                if (onError) onError(data.message || 'Failed to complete quiz');
            }
        } catch (error) {
            console.error('Error completing quiz:', error);
            setState(prev => ({
                ...prev,
                error: error.message,
                loading: false
            }));
            
            if (onError) onError(error.message);
        }
    };
    
    // Render error state
    if (state.error) {
        return React.createElement('div', { className: 'quiz-error' },
            React.createElement('h3', null, 'Error'),
            React.createElement('p', null, state.error)
        );
    }
    
    // Render loading state
    if (state.loading || (!state.currentQuestion && !state.completed)) {
        return React.createElement('div', { className: 'quiz-loading' },
            React.createElement('p', null, 'Loading quiz...')
        );
    }
    
    // Render completed state
    if (state.completed) {
        return React.createElement('div', { className: 'quiz-completed' },
            React.createElement('h3', null, 'Quiz Completed!'),
            state.results && React.createElement('div', { className: 'quiz-results' },
                React.createElement('p', { className: 'score' },
                    `Score: ${state.results.correct_answers} / ${state.results.total_questions}`
                ),
                React.createElement('p', { className: 'entries' },
                    `Entries Earned: ${state.results.entries_earned}`
                )
            )
        );
    }
    
    // Render question
    return React.createElement('div', { className: 'quiz-container' },
        // Question header
        React.createElement('div', { className: 'question-header' },
            React.createElement('div', { className: 'question-progress' },
                `Question ${state.questionNumber} of ${state.totalQuestions}`
            ),
            React.createElement('div', { className: 'timer-display' },
                `Time Left: ${state.timeLeft}s`
            )
        ),
        
        // Question
        React.createElement('h3', { className: 'question-text' },
            state.currentQuestion.question_text
        ),
        
        // Answers
        React.createElement('div', { className: 'answer-options' },
            state.currentQuestion.answers.map(answer => 
                React.createElement('button', {
                    key: answer.id,
                    className: `answer-option ${state.selectedAnswer === answer.id ? 'selected' : ''}`,
                    onClick: () => handleAnswer(answer.id),
                    disabled: state.selectedAnswer !== null
                }, answer.answer_text)
            )
        ),
        
        // Debug info
        debug && React.createElement('pre', { className: 'debug-info' },
            JSON.stringify(state, null, 2)
        )
    );
};

// Export to global namespace
window.WeebunzQuiz = {
    QuizPlayer
};