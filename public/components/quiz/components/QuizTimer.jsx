import React, { useEffect, useState } from 'react';
import { Timer } from 'lucide-react';
import { Progress } from '@/components/ui/progress';

const QuizTimer = ({ duration, onTimeout }) => {
  const [timeLeft, setTimeLeft] = useState(duration);
  const progress = (timeLeft / duration) * 100;

  useEffect(() => {
    if (!timeLeft) {
      onTimeout();
      return;
    }

    const timer = setInterval(() => {
      setTimeLeft(prev => Math.max(0, prev - 1));
    }, 1000);

    return () => clearInterval(timer);
  }, [timeLeft, onTimeout]);

  useEffect(() => {
    setTimeLeft(duration);
  }, [duration]);

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <div className="flex items-center">
          <Timer className="w-4 h-4 mr-2" />
          <span className={timeLeft < 5 ? 'text-red-600 font-medium' : ''}>
            {timeLeft}s remaining
          </span>
        </div>
      </div>
      <Progress value={progress} className="h-2" />
    </div>
  );
};

export default QuizTimer;