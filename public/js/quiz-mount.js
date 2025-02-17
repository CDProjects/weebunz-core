// File: wp-content/plugins/weebunz-core/public/js/quiz-mount.js

(function() {
    const container = document.getElementById('weebunz-quiz-container');
    if (container) {
        const quizId = container.dataset.quizId || 1; // Default to quiz ID 1 if not specified
        ReactDOM.render(
            React.createElement(window.WeebunzQuiz.QuizPlayer, { quizId }),
            container
        );
    }
})();