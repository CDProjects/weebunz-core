import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Timer, Trophy, AlertCircle } from 'lucide-react';
import { Progress } from '@/components/ui/progress';

const QuizPlayer = ({ quizId, onComplete }) => {
  const [state, setState] = useState({
    sessionId: null,
    currentQuestion: null,
    questionNumber: 0,
    totalQuestions: 0,
    correctAnswers: 0,
    timeLimit: 0,
    selectedAnswer: null,
    lastResult: null,
    completed: false,
    results: null,
    loading: true,
    error: null
  });

  // Start quiz session on mount
  useEffect(() => {
    startQuiz();
  }, [quizId]);

  // Start quiz session
  const startQuiz = async () => {
    try {
      setState(prev => ({ ...prev, loading: true, error: null }));
      
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
      
      setState(prev => ({
        ...prev,
        sessionId: data.session_id,
        totalQuestions: data.quiz_info.total_questions,
        timeLimit: data.quiz_info.time_limit,
        loading: false
      }));

      // Fetch first question
      fetchNextQuestion(data.session_id);

    } catch (err) {
      setState(prev => ({
        ...prev,
        loading: false,
        error: err.message
      }));
    }
  };

  // Fetch next question
  const fetchNextQuestion = async (sessionId = state.sessionId) => {
    try {
      setState(prev => ({ ...prev, loading: true, error: null }));
      
      const response = await fetch('/wp-json/weebunz/v1/quiz/question', {
        headers: {
          'X-Quiz-Session': sessionId,
          'X-WP-Nonce': window.weebunzSettings.nonce
        }
      });

      if (!response.ok) throw new Error('Failed to fetch question');
      
      const data = await response.json();
      
      if (data.completed) {
        handleQuizComplete();
        return;
      }

      setState(prev => ({
        ...prev,
        currentQuestion: data.question,
        questionNumber: prev.questionNumber + 1,
        selectedAnswer: null,
        lastResult: null,
        loading: false
      }));

    } catch (err) {
      setState(prev => ({
        ...prev,
        loading: false,
        error: err.message
      }));
    }
  };

  // Handle answer selection
  const handleAnswerSelect = async (answerId) => {
    try {
      setState(prev => ({ 
        ...prev, 
        selectedAnswer: answerId,
        loading: true,
        error: null 
      }));

      const response = await fetch('/wp-json/weebunz/v1/quiz/answer', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Quiz-Session': state.sessionId,
          'X-WP-Nonce': window.weebunzSettings.nonce
        },
        body: JSON.stringify({
          question_id: state.currentQuestion.id,
          answer_id: answerId,
          time_taken: state.timeLimit - state.timeLeft
        })
      });

      if (!response.ok) throw new Error('Failed to submit answer');
      
      const data = await response.json();

      setState(prev => ({
        ...prev,
        lastResult: data.result,
        correctAnswers: data.result.is_correct ? prev.correctAnswers + 1 : prev.correctAnswers,
        loading: false
      }));

      // Show result briefly before moving on
      setTimeout(() => {
        if (data.result.quiz_completed) {
          handleQuizComplete();
        } else {
          fetchNextQuestion();
        }
      }, 1500);

    } catch (err) {
      setState(prev => ({
        ...prev,
        loading: false,
        error: err.message
      }));
    }
  };

  // Handle quiz completion
  const handleQuizComplete = async () => {
    try {
      const response = await fetch('/wp-json/weebunz/v1/quiz/complete', {
        method: 'POST',
        headers: {
          'X-Quiz-Session': state.sessionId,
          'X-WP-Nonce': window.weebunzSettings.nonce
        }
      });

      if (!response.ok) throw new Error('Failed to complete quiz');
      
      const data = await response.json();

      setState(prev => ({
        ...prev,
        completed: true,
        results: data.results,
        loading: false
      }));

      if (onComplete) {
        onComplete(data.results);
      }

    } catch (err) {
      setState(prev => ({
        ...prev,
        loading: false,
        error: err.message
      }));
    }
  };

  if (state.loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600" />
      </div>
    );
  }

  if (state.error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>{state.error}</AlertDescription>
      </Alert>
    );
  }

  if (state.completed) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center">
            <Trophy className="mr-2 h-5 w-5 text-yellow-500" />
            Quiz Results
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="space-y-6">
            <div className="text-2xl font-bold text-center">
              {state.correctAnswers} out of {state.totalQuestions} Correct!
            </div>
            
            <div className="grid grid-cols-2 gap-4 text-center">
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-lg font-medium">Entries Earned</div>
                <div className="text-2xl text-green-600">
                  {state.results.entries_earned}
                </div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-lg font-medium">Score</div>
                <div className="text-2xl text-blue-600">
                  {Math.round((state.correctAnswers / state.totalQuestions) * 100)}%
                </div>
              </div>
            </div>

            <Button 
              onClick={() => window.location.reload()}
              className="w-full"
            >
              Play Again
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card className="w-full max-w-2xl mx-auto">
      <CardHeader>
        <CardTitle>Question {state.questionNumber}</CardTitle>
        <div className="space-y-2">
          <div className="flex justify-between text-sm">
            <span>{state.questionNumber} of {state.totalQuestions}</span>
            <span>Score: {state.correctAnswers} correct</span>
          </div>
          <Progress 
            value={(state.questionNumber / state.totalQuestions) * 100} 
            className="h-2"
          />
        </div>
      </CardHeader>
      <CardContent className="space-y-6">
        {state.currentQuestion && (
          <>
            <div className="text-lg font-medium">
              {state.currentQuestion.question_text}
            </div>
            
            <div className="space-y-3">
              {state.currentQuestion.answers.map((answer) => (
                <Button
                  key={answer.id}
                  onClick={() => handleAnswerSelect(answer.id)}
                  disabled={state.selectedAnswer !== null}
                  variant={state.selectedAnswer === answer.id ? 'secondary' : 'outline'}
                  className={`w-full justify-start text-left h-auto py-3 ${
                    state.selectedAnswer === answer.id && state.lastResult?.is_correct 
                      ? 'bg-green-50 border-green-500'
                      : state.selectedAnswer === answer.id && state.lastResult?.is_correct === false
                      ? 'bg-red-50 border-red-500'
                      : ''
                  }`}
                >
                  {answer.answer_text}
                </Button>
              ))}
            </div>

            <div className="flex items-center justify-between text-sm">
              <div className="flex items-center">
                <Timer className="w-4 h-4 mr-1" />
                <span>{state.timeLimit}s per question</span>
              </div>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
};

export default QuizPlayer;