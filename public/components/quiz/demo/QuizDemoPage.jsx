import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Timer, Trophy } from 'lucide-react';
import { Progress } from '@/components/ui/progress';

const QuizDemoPage = () => {
  const [availableQuizzes, setAvailableQuizzes] = useState([]);
  const [selectedQuiz, setSelectedQuiz] = useState(null);
  const [currentQuestion, setCurrentQuestion] = useState(null);
  const [sessionId, setSessionId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [quizState, setQuizState] = useState({
    totalQuestions: 0,
    currentQuestionNumber: 0,
    correctAnswers: 0,
    timeLimit: 0,
    completed: false,
    results: null
  });

  // Fetch available quizzes on mount
  useEffect(() => {
    fetchQuizzes();
  }, []);

  const fetchQuizzes = async () => {
    try {
      const response = await fetch('/wp-json/weebunz/v1/quizzes', {
        headers: {
          'X-WP-Nonce': window.weebunzSettings.nonce
        }
      });
      
      if (!response.ok) throw new Error('Failed to fetch quizzes');
      
      const data = await response.json();
      setAvailableQuizzes(data.quizzes);
      setLoading(false);
    } catch (err) {
      setError('Failed to load quizzes: ' + err.message);
      setLoading(false);
    }
  };

  const startQuiz = async (quizId) => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch('/wp-json/weebunz/v1/quiz/start', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.weebunzSettings.nonce
        },
        body: JSON.stringify({ quiz_id: quizId })
      });

      if (!response.ok) throw new Error('Failed to start quiz');
      
      const data = await response.json();
      setSessionId(data.session_id);
      setQuizState(prev => ({
        ...prev,
        totalQuestions: data.quiz_info.total_questions,
        timeLimit: data.quiz_info.time_limit,
        currentQuestionNumber: 1
      }));

      // Fetch first question
      await fetchNextQuestion(data.session_id);
      setLoading(false);
    } catch (err) {
      setError('Failed to start quiz: ' + err.message);
      setLoading(false);
    }
  };

  const fetchNextQuestion = async (sid = sessionId) => {
    try {
      const response = await fetch('/wp-json/weebunz/v1/quiz/question', {
        headers: {
          'X-Quiz-Session': sid,
          'X-WP-Nonce': window.weebunzSettings.nonce
        }
      });

      if (!response.ok) throw new Error('Failed to fetch question');
      
      const data = await response.json();
      
      if (data.completed) {
        await handleQuizComplete();
        return;
      }

      setCurrentQuestion(data.question);
    } catch (err) {
      setError('Failed to fetch question: ' + err.message);
    }
  };

  const submitAnswer = async (questionId, answerId, timeTaken) => {
    try {
      const response = await fetch('/wp-json/weebunz/v1/quiz/answer', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Quiz-Session': sessionId,
          'X-WP-Nonce': window.weebunzSettings.nonce
        },
        body: JSON.stringify({
          question_id: questionId,
          answer_id: answerId,
          time_taken: timeTaken
        })
      });

      if (!response.ok) throw new Error('Failed to submit answer');
      
      const data = await response.json();
      
      if (data.result.is_correct) {
        setQuizState(prev => ({
          ...prev,
          correctAnswers: prev.correctAnswers + 1
        }));
      }

      // Wait a moment to show the result
      setTimeout(() => {
        setQuizState(prev => ({
          ...prev,
          currentQuestionNumber: prev.currentQuestionNumber + 1
        }));
        fetchNextQuestion();
      }, 1500);

    } catch (err) {
      setError('Failed to submit answer: ' + err.message);
    }
  };

  const handleQuizComplete = async () => {
    try {
      const response = await fetch('/wp-json/weebunz/v1/quiz/complete', {
        method: 'POST',
        headers: {
          'X-Quiz-Session': sessionId,
          'X-WP-Nonce': window.weebunzSettings.nonce
        }
      });

      if (!response.ok) throw new Error('Failed to complete quiz');
      
      const data = await response.json();
      setQuizState(prev => ({
        ...prev,
        completed: true,
        results: data.results
      }));

    } catch (err) {
      setError('Failed to complete quiz: ' + err.message);
    }
  };

  const resetQuiz = () => {
    setSelectedQuiz(null);
    setCurrentQuestion(null);
    setSessionId(null);
    setQuizState({
      totalQuestions: 0,
      currentQuestionNumber: 0,
      correctAnswers: 0,
      timeLimit: 0,
      completed: false,
      results: null
    });
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  if (quizState.completed && quizState.results) {
    return (
      <Card className="w-full max-w-2xl mx-auto">
        <CardHeader>
          <CardTitle className="flex items-center">
            <Trophy className="mr-2 h-5 w-5 text-yellow-500" />
            Quiz Results
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-6">
            <div className="text-2xl font-bold text-center">
              {quizState.correctAnswers} out of {quizState.totalQuestions} Correct!
            </div>
            
            <div className="grid grid-cols-2 gap-4 text-center">
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-lg font-medium">Entries Earned</div>
                <div className="text-2xl text-green-600">
                  {quizState.results.entries_earned}
                </div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-lg font-medium">Total Score</div>
                <div className="text-2xl text-blue-600">
                  {Math.round((quizState.correctAnswers / quizState.totalQuestions) * 100)}%
                </div>
              </div>
            </div>

            <Button 
              onClick={resetQuiz}
              className="w-full"
            >
              Try Another Quiz
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  if (currentQuestion) {
    return (
      <Card className="w-full max-w-2xl mx-auto">
        <CardHeader>
          <CardTitle>Question {quizState.currentQuestionNumber}</CardTitle>
          <div className="space-y-2">
            <div className="flex justify-between text-sm">
              <span>{quizState.currentQuestionNumber} of {quizState.totalQuestions}</span>
              <span>Score: {quizState.correctAnswers} correct</span>
            </div>
            <Progress 
              value={(quizState.currentQuestionNumber / quizState.totalQuestions) * 100} 
              className="h-2"
            />
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="text-lg">{currentQuestion.question_text}</div>
          
          <div className="space-y-3">
            {currentQuestion.answers.map((answer) => (
              <Button
                key={answer.id}
                onClick={() => submitAnswer(currentQuestion.id, answer.id, 5)}
                variant="outline"
                className="w-full justify-start text-left h-auto py-3"
              >
                {answer.answer_text}
              </Button>
            ))}
          </div>

          <div className="flex items-center justify-between text-sm">
            <div className="flex items-center">
              <Timer className="w-4 h-4 mr-1" />
              <span>{quizState.timeLimit}s per question</span>
            </div>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="container mx-auto p-6 max-w-4xl">
      <Card>
        <CardHeader>
          <CardTitle>Select a Quiz</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {availableQuizzes.map((quiz) => (
              <Button
                key={quiz.id}
                onClick={() => {
                  setSelectedQuiz(quiz);
                  startQuiz(quiz.id);
                }}
                variant="outline"
                className="h-auto py-4 flex flex-col items-start space-y-2"
              >
                <span className="font-bold">{quiz.name}</span>
                <span className="text-sm text-gray-500">
                  {quiz.difficulty_level} • {quiz.time_limit}s per question
                </span>
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default QuizDemoPage;