import React, { useState, useEffect } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Trophy, Timer, Users, AlertCircle } from 'lucide-react';
import QuizPlayer from './QuizPlayer';

const QuizPage = () => {
  const [state, setState] = useState({
    quizzes: [],
    selectedQuiz: null,
    loading: true,
    error: null
  });

  // Fetch available quizzes on mount
  useEffect(() => {
    fetchQuizzes();
  }, []);

  const fetchQuizzes = async () => {
    try {
      setState(prev => ({ ...prev, loading: true, error: null }));

      const response = await fetch('/wp-json/weebunz/v1/quizzes', {
        headers: {
          'X-WP-Nonce': window.weebunzSettings.nonce
        }
      });

      if (!response.ok) throw new Error('Failed to fetch quizzes');
      
      const data = await response.json();
      setState(prev => ({
        ...prev,
        quizzes: data.quizzes,
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

  const handleQuizSelect = (quiz) => {
    setState(prev => ({ ...prev, selectedQuiz: quiz }));
  };

  const handleQuizComplete = (results) => {
    // Could show a modal or additional info here
    console.log('Quiz completed:', results);
  };

  const handleBackToQuizzes = () => {
    setState(prev => ({ ...prev, selectedQuiz: null }));
    fetchQuizzes(); // Refresh the quiz list
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

  if (state.selectedQuiz) {
    return (
      <div className="container mx-auto p-4 max-w-4xl">
        <div className="mb-4">
          <Button
            onClick={handleBackToQuizzes}
            variant="outline"
            className="mb-4"
          >
            ← Back to Quizzes
          </Button>
        </div>

        <QuizPlayer 
          quizId={state.selectedQuiz.id} 
          onComplete={handleQuizComplete}
        />
      </div>
    );
  }

  return (
    <div className="container mx-auto p-4">
      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Available Quizzes</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-gray-600 mb-4">
            Select a quiz to start playing. Each quiz has different difficulty levels and entry rewards.
          </p>
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {state.quizzes.map((quiz) => (
          <Card 
            key={quiz.id}
            className="hover:shadow-lg transition-shadow duration-200"
          >
            <CardHeader>
              <CardTitle className="flex justify-between items-center">
                <span>{quiz.name}</span>
                <span className={`text-sm px-2 py-1 rounded ${
                  quiz.difficulty_level === 'easy' 
                    ? 'bg-green-100 text-green-800'
                    : quiz.difficulty_level === 'medium'
                    ? 'bg-yellow-100 text-yellow-800'
                    : 'bg-red-100 text-red-800'
                }`}>
                  {quiz.difficulty_level.charAt(0).toUpperCase() + quiz.difficulty_level.slice(1)}
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                <div className="text-sm space-y-2">
                  <div className="flex items-center">
                    <Timer className="w-4 h-4 mr-2 text-gray-500" />
                    <span>{quiz.time_limit} seconds per question</span>
                  </div>
                  <div className="flex items-center">
                    <Trophy className="w-4 h-4 mr-2 text-gray-500" />
                    <span>Up to {quiz.max_entries} entries</span>
                  </div>
                  <div className="flex items-center">
                    <Users className="w-4 h-4 mr-2 text-gray-500" />
                    <span>{quiz.question_count} questions</span>
                  </div>
                </div>

                <div className="text-center bg-gray-50 p-3 rounded-lg">
                  <div className="text-sm text-gray-600">Entry Cost</div>
                  <div className="text-2xl font-bold text-blue-600">
                    €{quiz.entry_cost.toFixed(2)}
                  </div>
                </div>

                <Button
                  onClick={() => handleQuizSelect(quiz)}
                  className="w-full"
                >
                  Start Quiz
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {state.quizzes.length === 0 && (
        <div className="text-center text-gray-600 py-12">
          No quizzes currently available. Please check back later.
        </div>
      )}
    </div>
  );
};

export default QuizPage;