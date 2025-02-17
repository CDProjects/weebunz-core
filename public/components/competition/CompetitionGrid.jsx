import React, { useState, useEffect } from 'react';
import CompetitionTile from './CompetitionTile';

const CompetitionGrid = () => {
  const [competitions, setCompetitions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchCompetitions = async () => {
      try {
        const response = await fetch('/wp-json/weebunz/v1/competitions');
        if (!response.ok) throw new Error('Failed to fetch competitions');
        const data = await response.json();
        setCompetitions(data);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchCompetitions();
  }, []);

  const handleCompetitionClick = (competitionId) => {
    window.location.href = `/competition/${competitionId}`;
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
      <div className="text-center text-red-600 p-4">
        Error: {error}
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {competitions.map((competition) => (
          <CompetitionTile
            key={competition.id}
            {...competition}
            onClick={() => handleCompetitionClick(competition.id)}
          />
        ))}
      </div>

      {competitions.length === 0 && (
        <div className="text-center text-gray-600 py-12">
          No active competitions found
        </div>
      )}
    </div>
  );
};

export default CompetitionGrid;