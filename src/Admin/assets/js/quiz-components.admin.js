// File: wp-content/plugins/weebunz-core/src/Admin/assets/js/quiz-components.admin.js

(function() {
  "use strict";

  // Make sure React and ReactDOM are available globally
  const React = window.React || {};
  const ReactDOM = window.ReactDOM || {};
  
  // Only attempt to destructure if React is available
  const useState = React.useState;
  const useEffect = React.useEffect;

  // Simple test component using React.createElement instead of JSX
  const QuizPlayer = function(props) {
    // Use React hooks if available, otherwise use simple object
    const [state, setState] = useState ? 
      useState({
        loaded: false,
        error: null
      }) : 
      { loaded: false, error: null };
    
    // Use effect hook if available
    if (useEffect) {
      useEffect(function() {
        console.log("QuizPlayer component mounted");
        setState({ loaded: true, error: null });
      }, []);
    }

    // Check for error state
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
        'Status: ' + (state.loaded ? 'Loaded' : 'Loading')
      ),
      // Add button for testing interaction
      React.createElement('button', {
        className: 'button button-primary mt-4',
        onClick: function() {
          console.log("Test button clicked");
          if (props.onAnswer) {
            props.onAnswer({ is_correct: true });
          }
        }
      }, 'Test Answer (Correct)')
    );
  };

  // Export to global scope - make sure this is properly namespaced
  window.WeebunzQuiz = {
    QuizPlayer: QuizPlayer
  };

  // Log to console for debugging
  console.log('WeebunzQuiz components loaded:', window.WeebunzQuiz);
})();
