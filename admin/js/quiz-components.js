// File: wp-content/plugins/weebunz-core/admin/js/quiz-components.js

const { React, ReactDOM } = window;
const { useState, useEffect } = React;

const QuizPlayer = ({ debug = false, onAnswer, onComplete, onError }) => {
    const [state, setState] = useState({
        loaded: false,
        error: null
    });

    useEffect(() => {
        setState({ loaded: true, error: null });
    }, []);

    if (state.error) {
        return React.createElement('div', { className: 'error-state' },
            'Error: ' + state.error
        );
    }

    return React.createElement('div', { className: 'quiz-player-test' },
        React.createElement('h3', { className: 'text-lg font-bold mb-4' }, 
            'Quiz Player Test Component'
        ),
        React.createElement('div', { className: 'status-display' },
            `Status: ${state.loaded ? 'Loaded' : 'Loading'}`
        )
    );
};

// Export to global scope
window.WeebunzQuiz = {
    QuizPlayer
};