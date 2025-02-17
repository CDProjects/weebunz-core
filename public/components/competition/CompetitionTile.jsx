import React from 'react';
import { Timer, Trophy, Users } from 'lucide-react';

const CompetitionTile = ({ 
  title,
  prizeDescription,
  endDate,
  totalEntries,
  maxEntries,
  imageUrl,
  status,
  onClick
}) => {
  const progressPercentage = Math.min((totalEntries / maxEntries) * 100, 100);
  const timeRemaining = new Date(endDate) - new Date();
  const daysRemaining = Math.ceil(timeRemaining / (1000 * 60 * 60 * 24));

  return (
    <div 
      onClick={onClick}
      className="bg-white rounded-lg shadow-lg overflow-hidden cursor-pointer transform transition hover:scale-105"
    >
      {/* Prize Image */}
      <div className="relative h-48 bg-gray-200">
        <img
          src={imageUrl || "/api/placeholder/400/320"}
          alt={title}
          className="w-full h-full object-cover"
        />
        <div className="absolute top-2 right-2">
          <span className="bg-blue-600 text-white px-3 py-1 rounded-full text-sm">
            {status}
          </span>
        </div>
      </div>

      {/* Content */}
      <div className="p-4">
        <h3 className="text-lg font-bold mb-2 line-clamp-2">{title}</h3>
        <p className="text-gray-600 text-sm mb-4 line-clamp-2">{prizeDescription}</p>

        {/* Stats */}
        <div className="space-y-3">
          {/* Time Remaining */}
          <div className="flex items-center text-sm">
            <Timer className="w-4 h-4 mr-2 text-blue-600" />
            <span>
              {daysRemaining} days remaining
            </span>
          </div>

          {/* Entries */}
          <div className="flex items-center text-sm">
            <Users className="w-4 h-4 mr-2 text-blue-600" />
            <span>
              {totalEntries} / {maxEntries} entries
            </span>
          </div>

          {/* Progress Bar */}
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div 
              className="bg-blue-600 rounded-full h-2 transition-all duration-300"
              style={{ width: `${progressPercentage}%` }}
            />
          </div>
        </div>

        {/* Action Button */}
        <button className="w-full mt-4 bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition-colors">
          Enter Competition
        </button>
      </div>
    </div>
  );
};

export default CompetitionTile;