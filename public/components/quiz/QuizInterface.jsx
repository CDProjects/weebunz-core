import React, { useState, useEffect } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';

const QuizInterface = ({
  quizType = "Deadly",
  question,
  options,
  timeLimit,
  onAnswerSubmit
}) => {
  const [timeLeft, setTimeLeft] = useState(timeLimit);
  const [selectedAnswer, setSelectedAnswer] = useState(null);

  // Timer effect
  useEffect(() => {
    if (timeLeft <= 0) return;
    
    const timer = setInterval(() => {
      setTimeLeft(prev => Math.max(0, prev - 1));
    }, 1000);

    return () => clearInterval(timer);
  }, [timeLeft]);

  // Reset timer when new question comes in
  useEffect(() => {
    setTimeLeft(timeLimit);
    setSelectedAnswer(null);
  }, [question, timeLimit]);

  const handleOptionSelect = (optionIndex) => {
    setSelectedAnswer(optionIndex);
    if (onAnswerSubmit) {
      onAnswerSubmit(optionIndex);
    }
  };

  return (
    <div className="w-full max-w-2xl mx-auto">
      <Card className="bg-gray-900 text-white shadow-xl">
        <CardContent className="p-6 space-y-6">
          {/* Quiz Type Header */}
          <div className="flex justify-between items-center">
            <h2 className="text-xl font-bold text-amber-500">{quizType}</h2>
            <div className="flex space-x-4">
              {/* Placeholder Gold Pot Icons */}
              <img 
                src="/api/placeholder/32/32" 
                alt="EOR gold pot left" 
                className="w-8 h-8"
              />
              <img 
                src="/api/placeholder/32/32" 
                alt="EOR gold pot right" 
                className="w-8 h-8"
              />
            </div>
          </div>

          {/* Question */}
          <div className="bg-white text-gray-900 rounded-lg p-4 min-h-[60px]">
            <p className="text-lg font-medium">{question}</p>
          </div>

          {/* Answer Options */}
          <div className="space-y-3">
            {options.map((option, index) => (
              <button
                key={index}
                onClick={() => handleOptionSelect(index)}
                disabled={selectedAnswer !== null}
                className={`w-full bg-white text-gray-900 rounded-lg p-4 text-left 
                  transition-colors duration-200
                  ${selectedAnswer === index ? 'bg-amber-100' : 'hover:bg-gray-100'}
                  ${selectedAnswer !== null && selectedAnswer !== index ? 'opacity-50' : ''}
                `}
              >
                {`Option ${String.fromCharCode(65 + index)}: ${option}`}
              </button>
            ))}
          </div>

          {/* Timer */}
          <div className="space-y-2">
            <div className="bg-white rounded-lg p-2">
              <Progress 
                value={(timeLeft / timeLimit) * 100}
                className="h-2 bg-gray-200"
                indicatorClassName="bg-amber-500"
              />
            </div>
            <div className="text-center text-amber-500">
              {timeLeft} seconds remaining
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default QuizInterface;