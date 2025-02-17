const path = require('path');

module.exports = {
  entry: {
    'quiz-player': './public/components/quiz/QuizPlayer.jsx'
  },
  output: {
    path: path.resolve(__dirname, 'public/dist'),
    filename: '[name].js',
    library: ['WeebunzQuiz', '[name]'],
    libraryTarget: 'umd'
  },
  externals: {
    'react': 'React',
    'react-dom': 'ReactDOM'
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              '@babel/preset-env',
              '@babel/preset-react'
            ],
            plugins: [
              '@babel/plugin-transform-runtime'
            ]
          }
        }
      }
    ]
  },
  resolve: {
    extensions: ['.js', '.jsx'],
    alias: {
      '@/components/ui': path.resolve(__dirname, 'public/components/ui')
    }
  },
  mode: process.env.NODE_ENV === 'production' ? 'production' : 'development'
};