import React from 'react';
import { Progress } from '@/components/ui/progress';

const QuizProgress = ({ currentQuestion, totalQuestions, correctAnswers }) => {
  const progress = (currentQuestion / totalQuestions) * 100;

  return (
    <div className="space-y-2">
      <div className="flex justify-between items-center text-sm">
        <span>Question {currentQuestion} of {totalQuestions}</span>
        <span className="text-green-600">
          {correctAnswers} correct ({Math.round((correctAnswers / currentQuestion) * 100) || 0}%)
        </span>
      </div>
      <Progress value={progress} className="h-2" />
    </div>
  );
};

export default QuizProgress;