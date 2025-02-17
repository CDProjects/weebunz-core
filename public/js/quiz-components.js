// File: wp-content/plugins/weebunz-core/public/js/quiz-components.js

const { React, ReactDOM } = window;
const { useState, useEffect } = React;

// QuizTimer Component
const QuizTimer = ({ duration, onTimeout }) => {
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

    return (
        <div className="quiz-timer">
            <div className="time-display">
                Time remaining: {timeLeft}s
            </div>
            <div className="progress-bar">
                <div 
                    className="progress" 
                    style={{ width: `${(timeLeft / duration) * 100}%` }}
                />
            </div>
        </div>
    );
};

// QuizQuestion Component
const QuizQuestion = ({ question, onAnswer }) => {
    return (
        <div className="quiz-question">
            <h3 className="text-xl mb-4">{question.text}</h3>
            <div className="answers-grid">
                {question.options.map((option, index) => (
                    <button
                        key={index}
                        onClick={() => onAnswer(index)}
                        className="answer-button"
                    >
                        {option}
                    </button>
                ))}
            </div>
        </div>
    );
};

// Main QuizPlayer Component
const QuizPlayer = ({ quizId }) => {
    const [state, setState] = useState({
        loading: true,
        error: null,
        currentQuestion: null,
        sessionId: null,
        score: 0
    });

    useEffect(() => {
        startQuiz();
    }, []);

    const startQuiz = async () => {
        try {
            const response = await fetch(`${weebunzSettings.apiEndpoint}/quiz/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': weebunzSettings.nonce
                },
                body: JSON.stringify({ quiz_id: quizId })
            });

            if (!response.ok) throw new Error('Failed to start quiz');

            const data = await response.json();
            setState(prev => ({
                ...prev,
                sessionId: data.session_id,
                loading: false
            }));

            fetchNextQuestion(data.session_id);
        } catch (error) {
            setState(prev => ({
                ...prev,
                error: error.message,
                loading: false
            }));
        }
    };

    // Add other methods (fetchNextQuestion, handleAnswer, etc.)

    if (state.loading) {
        return <div>Loading quiz...</div>;
    }

    if (state.error) {
        return <div>Error: {state.error}</div>;
    }

    return (
        <div className="quiz-player">
            {state.currentQuestion && (
                <>
                    <QuizTimer 
                        duration={state.currentQuestion.timeLimit} 
                        onTimeout={handleTimeout} 
                    />
                    <QuizQuestion 
                        question={state.currentQuestion}
                        onAnswer={handleAnswer}
                    />
                </>
            )}
        </div>
    );
};

// Export components to global scope
window.WeebunzQuiz = {
    QuizPlayer,
    QuizTimer,
    QuizQuestion
};