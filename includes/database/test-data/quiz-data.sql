-- Save as: wp-content/plugins/weebunz-core/includes/database/test-data/quiz-data.sql

-- Quiz Categories
INSERT INTO `{prefix}quiz_categories` (`name`, `slug`, `description`) VALUES
('General Knowledge', 'general-knowledge', 'Basic general knowledge questions'),
('Sports', 'sports', 'Questions about various sports'),
('History', 'history', 'Historical events and figures'),
('Science', 'science', 'Scientific concepts and discoveries'),
('Entertainment', 'entertainment', 'Movies, music, and pop culture'),
('Geography', 'geography', 'World geography and landmarks');

-- Questions Pool
INSERT INTO `{prefix}questions_pool` (`question_text`, `question_type`, `category`, `difficulty_level`, `time_limit`) VALUES
('What is the capital of Finland?', 'multiple_choice', 'Geography', 'easy', 10),
('Who won the FIFA World Cup in 2022?', 'multiple_choice', 'Sports', 'easy', 10),
('What is the chemical symbol for gold?', 'multiple_choice', 'Science', 'easy', 10),
('Which planet is known as the Red Planet?', 'multiple_choice', 'Science', 'medium', 15),
('In which year did Finland gain independence?', 'multiple_choice', 'History', 'medium', 15),
('Who painted the Mona Lisa?', 'multiple_choice', 'General Knowledge', 'medium', 15),
('What is the most abundant element in the Universe?', 'multiple_choice', 'Science', 'hard', 20),
('Which Finnish company was founded by Fredrik Idestam in 1865?', 'multiple_choice', 'History', 'hard', 20),
('Who wrote the Kalevala?', 'multiple_choice', 'Entertainment', 'hard', 20);

-- Get the question IDs
SET @q1 = LAST_INSERT_ID();
SET @q2 = @q1 + 1;
SET @q3 = @q1 + 2;
SET @q4 = @q1 + 3;
SET @q5 = @q1 + 4;
SET @q6 = @q1 + 5;
SET @q7 = @q1 + 6;
SET @q8 = @q1 + 7;
SET @q9 = @q1 + 8;

-- Question Answers
INSERT INTO `{prefix}question_answers` (`question_id`, `answer_text`, `is_correct`) VALUES
(@q1, 'Helsinki', 1), (@q1, 'Stockholm', 0), (@q1, 'Oslo', 0), (@q1, 'Copenhagen', 0),
(@q2, 'Argentina', 1), (@q2, 'France', 0), (@q2, 'Brazil', 0), (@q2, 'Germany', 0),
(@q3, 'Au', 1), (@q3, 'Ag', 0), (@q3, 'Fe', 0), (@q3, 'Cu', 0),
(@q4, 'Mars', 1), (@q4, 'Venus', 0), (@q4, 'Jupiter', 0), (@q4, 'Saturn', 0),
(@q5, '1917', 1), (@q5, '1919', 0), (@q5, '1905', 0), (@q5, '1920', 0),
(@q6, 'Leonardo da Vinci', 1), (@q6, 'Michelangelo', 0), (@q6, 'Raphael', 0), (@q6, 'Donatello', 0),
(@q7, 'Hydrogen', 1), (@q7, 'Helium', 0), (@q7, 'Oxygen', 0), (@q7, 'Carbon', 0),
(@q8, 'Nokia', 1), (@q8, 'Fiskars', 0), (@q8, 'Fazer', 0), (@q8, 'Valio', 0),
(@q9, 'Elias Lönnrot', 1), (@q9, 'Jean Sibelius', 0), (@q9, 'Aleksis Kivi', 0), (@q9, 'Mika Waltari', 0);

-- Winner Questions Pool
INSERT INTO `{prefix}winner_questions_pool` (`question_text`, `correct_answer`, `difficulty_level`) VALUES
('What is the national animal of Finland?', 'Brown Bear', 'easy'),
('What is the Finnish word for "Hello"?', 'Hei', 'easy'),
('Name one of the two official languages of Finland', 'Finnish or Swedish', 'medium'),
('What is the name of the Finnish national epic?', 'Kalevala', 'hard');