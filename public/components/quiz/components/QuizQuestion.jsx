import React from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

const QuizQuestion = ({
  question,
  selectedAnswer,
  lastResult,
  onAnswerSelect,
  disabled
}) => {
  return (
    <div className="space-y-6">
      <div className="text-lg font-medium mb-4">
        {question.question_text}
      </div>

      <div className="space-y-3">
        {question.answers.map((answer) => (
          <Button
            key={answer.id}
            onClick={() => onAnswerSelect(answer.id)}
            disabled={disabled}
            variant="outline"
            className={cn(
              "w-full p-4 h-auto justify-start text-left",
              selectedAnswer === answer.id && lastResult?.is_correct && "border-green-500 bg-green-50",
              selectedAnswer === answer.id && !lastResult?.is_correct && "border-red-500 bg-red-50"
            )}
          >
            <div className="flex items-center w-full">
              <span className="flex-grow">{answer.answer_text}</span>
              {selectedAnswer === answer.id && lastResult && (
                lastResult.is_correct 
                  ? <CheckCircle className="w-5 h-5 text-green-500 ml-2" />
                  : <XCircle className="w-5 h-5 text-red-500 ml-2" />
              )}
            </div>
          </Button>
        ))}
      </div>
    </div>
  );
};

export default QuizQuestion;