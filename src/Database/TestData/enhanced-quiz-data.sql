-- Enhanced test data for WeeBunz Quiz Engine
-- This file contains additional sample quizzes and test data for demonstration

-- Quiz Types
INSERT INTO `{prefix}quiz_types` (`name`, `slug`, `description`, `entry_fee`, `question_count`, `time_limit_per_question`) VALUES
('Deadly', 'deadly', 'Challenging quiz with higher stakes and rewards', 2.00, 10, 15),
('Wee Buns', 'wee-buns', 'Easy quiz with lower stakes and entry fee', 1.00, 5, 20),
('Gift', 'gift', 'Free quiz for promotional purposes', 0.00, 3, 30);

-- Additional Quiz Categories
INSERT INTO `{prefix}quiz_categories` (`name`, `slug`, `description`) VALUES
('Technology', 'technology', 'Questions about computers, gadgets, and tech innovations'),
('Food & Drink', 'food-drink', 'Culinary questions and beverage knowledge'),
('Music', 'music', 'Questions about songs, artists, and musical history'),
('Literature', 'literature', 'Books, authors, and literary works');

-- Active Quizzes
INSERT INTO `{prefix}active_quizzes` (`quiz_type_id`, `title`, `quiz_code`, `status`, `discount_percentage`, `start_date`, `end_date`) VALUES
(1, 'Deadly Challenge: General Knowledge', 'DEAD001', 'active', NULL, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
(1, 'Deadly Challenge: Science & Tech', 'DEAD002', 'active', NULL, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
(2, 'Wee Buns: Easy Geography', 'WEEB001', 'active', NULL, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY)),
(3, 'Gift Quiz: Entertainment Special', 'GIFT001', 'active', NULL, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY));

-- Quiz Tags
INSERT INTO `{prefix}quiz_tags` (`name`, `slug`, `type`) VALUES
('Featured', 'featured', 'status'),
('New', 'new', 'status'),
('Popular', 'popular', 'status'),
('Limited Time', 'limited-time', 'promotion'),
('Double Entries', 'double-entries', 'promotion');

-- Quiz-Tag Relations
INSERT INTO `{prefix}quiz_tag_relations` (`quiz_id`, `tag_id`) VALUES
(1, 1), -- Deadly Challenge: General Knowledge - Featured
(1, 3), -- Deadly Challenge: General Knowledge - Popular
(2, 2), -- Deadly Challenge: Science & Tech - New
(3, 5), -- Wee Buns: Easy Geography - Double Entries
(4, 4); -- Gift Quiz: Entertainment Special - Limited Time

-- Additional Questions Pool
INSERT INTO `{prefix}questions_pool` (`question_text`, `question_type`, `category`, `difficulty_level`, `time_limit`) VALUES
-- Technology Questions
('What company makes the iPhone?', 'multiple_choice', 'Technology', 'easy', 10),
('What does CPU stand for?', 'multiple_choice', 'Technology', 'easy', 10),
('Which programming language is known for its use in web development with its frameworks like React and Angular?', 'multiple_choice', 'Technology', 'medium', 15),
('What technology is used to record cryptocurrency transactions?', 'multiple_choice', 'Technology', 'medium', 15),
('What year was the first iPhone released?', 'multiple_choice', 'Technology', 'hard', 20),

-- Food & Drink Questions
('What is the main ingredient in guacamole?', 'multiple_choice', 'Food & Drink', 'easy', 10),
('Which country is known for inventing pizza?', 'multiple_choice', 'Food & Drink', 'easy', 10),
('What is the main ingredient in traditional Japanese miso soup?', 'multiple_choice', 'Food & Drink', 'medium', 15),
('Which spirit is used to make a mojito cocktail?', 'multiple_choice', 'Food & Drink', 'medium', 15),
('What is the name of the fungus that gives blue cheese its distinctive flavor and appearance?', 'multiple_choice', 'Food & Drink', 'hard', 20),

-- Music Questions
('Who is known as the "King of Pop"?', 'multiple_choice', 'Music', 'easy', 10),
('Which band performed the hit song "Bohemian Rhapsody"?', 'multiple_choice', 'Music', 'easy', 10),
('Which instrument has 88 keys?', 'multiple_choice', 'Music', 'medium', 15),
('Which Finnish symphonic metal band features vocalist Tarja Turunen as its original lead singer?', 'multiple_choice', 'Music', 'hard', 20),
('Who composed the "Four Seasons"?', 'multiple_choice', 'Music', 'hard', 20);

-- Get the question IDs for the new questions
SET @t1 = LAST_INSERT_ID();
SET @t2 = @t1 + 1;
SET @t3 = @t1 + 2;
SET @t4 = @t1 + 3;
SET @t5 = @t1 + 4;
SET @f1 = @t1 + 5;
SET @f2 = @t1 + 6;
SET @f3 = @t1 + 7;
SET @f4 = @t1 + 8;
SET @f5 = @t1 + 9;
SET @m1 = @t1 + 10;
SET @m2 = @t1 + 11;
SET @m3 = @t1 + 12;
SET @m4 = @t1 + 13;
SET @m5 = @t1 + 14;

-- Question Answers for Technology Questions
INSERT INTO `{prefix}question_answers` (`question_id`, `answer_text`, `is_correct`) VALUES
(@t1, 'Apple', 1), (@t1, 'Samsung', 0), (@t1, 'Google', 0), (@t1, 'Microsoft', 0),
(@t2, 'Central Processing Unit', 1), (@t2, 'Computer Personal Unit', 0), (@t2, 'Central Process Utility', 0), (@t2, 'Core Processing Unit', 0),
(@t3, 'JavaScript', 1), (@t3, 'Python', 0), (@t3, 'Java', 0), (@t3, 'C++', 0),
(@t4, 'Blockchain', 1), (@t4, 'Cloud Computing', 0), (@t4, 'Artificial Intelligence', 0), (@t4, 'Virtual Reality', 0),
(@t5, '2007', 1), (@t5, '2005', 0), (@t5, '2010', 0), (@t5, '2001', 0);

-- Question Answers for Food & Drink Questions
INSERT INTO `{prefix}question_answers` (`question_id`, `answer_text`, `is_correct`) VALUES
(@f1, 'Avocado', 1), (@f1, 'Tomato', 0), (@f1, 'Onion', 0), (@f1, 'Lime', 0),
(@f2, 'Italy', 1), (@f2, 'Greece', 0), (@f2, 'France', 0), (@f2, 'Spain', 0),
(@f3, 'Fermented soybean paste', 1), (@f3, 'Rice', 0), (@f3, 'Fish', 0), (@f3, 'Seaweed', 0),
(@f4, 'Rum', 1), (@f4, 'Vodka', 0), (@f4, 'Gin', 0), (@f4, 'Tequila', 0),
(@f5, 'Penicillium', 1), (@f5, 'Aspergillus', 0), (@f5, 'Saccharomyces', 0), (@f5, 'Lactobacillus', 0);

-- Question Answers for Music Questions
INSERT INTO `{prefix}question_answers` (`question_id`, `answer_text`, `is_correct`) VALUES
(@m1, 'Michael Jackson', 1), (@m1, 'Elvis Presley', 0), (@m1, 'Prince', 0), (@m1, 'Freddie Mercury', 0),
(@m2, 'Queen', 1), (@m2, 'The Beatles', 0), (@m2, 'Led Zeppelin', 0), (@m2, 'Pink Floyd', 0),
(@m3, 'Piano', 1), (@m3, 'Guitar', 0), (@m3, 'Violin', 0), (@m3, 'Harp', 0),
(@m4, 'Nightwish', 1), (@m4, 'Apocalyptica', 0), (@m4, 'HIM', 0), (@m4, 'The Rasmus', 0),
(@m5, 'Antonio Vivaldi', 1), (@m5, 'Johann Sebastian Bach', 0), (@m5, 'Wolfgang Amadeus Mozart', 0), (@m5, 'Ludwig van Beethoven', 0);

-- Additional Winner Questions Pool
INSERT INTO `{prefix}winner_questions_pool` (`question_text`, `correct_answer`, `difficulty_level`) VALUES
('What is the capital of Australia?', 'Canberra', 'medium'),
('Who wrote the Harry Potter series?', 'J.K. Rowling', 'easy'),
('What is the largest planet in our solar system?', 'Jupiter', 'medium'),
('What is the chemical symbol for water?', 'H2O', 'easy'),
('Who painted "Starry Night"?', 'Vincent van Gogh', 'medium'),
('What is the tallest mountain in the world?', 'Mount Everest', 'easy');

-- Raffle Events
INSERT INTO `{prefix}raffle_events` (`title`, `prize_description`, `is_live_event`, `event_date`, `status`, `entry_limit`) VALUES
('Weekly Cash Prize Draw', 'Win €500 cash prize!', 1, DATE_ADD(NOW(), INTERVAL 7 DAY), 'scheduled', 200),
('Monthly Tech Giveaway', 'Win the latest iPhone!', 1, DATE_ADD(NOW(), INTERVAL 30 DAY), 'scheduled', 300),
('Holiday Special Raffle', 'Win a luxury vacation package!', 1, DATE_ADD(NOW(), INTERVAL 60 DAY), 'scheduled', 500);

-- Sample Users (if needed and not already in WordPress)
-- Note: In a real WordPress environment, these would be in wp_users table
-- This is just for reference if needed for test data

-- Sample Quiz Attempts (for demonstration)
-- These would be populated when users take quizzes
INSERT INTO `{prefix}quiz_attempts` (`user_id`, `quiz_id`, `start_time`, `end_time`, `score`, `entries_earned`, `status`) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 8, 2, 'completed'),
(1, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 6, 1, 'completed'),
(2, 1, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 10, 3, 'completed'),
(3, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 5, 1, 'completed'),
(4, 4, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR), 3, 1, 'completed');

-- Sample Raffle Entries (based on quiz attempts)
INSERT INTO `{prefix}raffle_entries` (`raffle_id`, `user_id`, `entry_number`, `phone_number`, `source_type`, `source_id`) VALUES
(1, 1, 1001, '+353871234567', 'quiz', 1),
(1, 1, 1002, '+353871234567', 'quiz', 1),
(1, 2, 1003, '+353872345678', 'quiz', 1),
(1, 2, 1004, '+353872345678', 'quiz', 1),
(1, 2, 1005, '+353872345678', 'quiz', 1),
(1, 3, 1006, '+353873456789', 'quiz', 3),
(2, 1, 2001, '+353871234567', 'quiz', 2),
(2, 4, 2002, '+353874567890', 'quiz', 4);

-- Sample Platinum Memberships
INSERT INTO `{prefix}platinum_memberships` (`user_id`, `plan_duration`, `start_date`, `end_date`, `status`, `free_quizzes_remaining`) VALUES
(1, 'monthly', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_ADD(NOW(), INTERVAL 15 DAY), 'active', 2),
(2, 'annual', DATE_SUB(NOW(), INTERVAL 2 MONTH), DATE_ADD(NOW(), INTERVAL 10 MONTH), 'active', 3),
(3, 'quarterly', DATE_SUB(NOW(), INTERVAL 1 MONTH), DATE_ADD(NOW(), INTERVAL 2 MONTH), 'active', 1);

-- Sample Spending Log
INSERT INTO `{prefix}spending_log` (`user_id`, `amount`, `type`, `reference_id`, `description`) VALUES
(1, 2.00, 'quiz', 1, 'Deadly Challenge: General Knowledge'),
(1, 2.00, 'quiz', 2, 'Deadly Challenge: Science & Tech'),
(1, 9.99, 'membership', 1, 'Monthly Platinum Membership'),
(2, 2.00, 'quiz', 1, 'Deadly Challenge: General Knowledge'),
(2, 99.99, 'membership', 2, 'Annual Platinum Membership'),
(3, 1.00, 'quiz', 3, 'Wee Buns: Easy Geography'),
(3, 24.99, 'membership', 3, 'Quarterly Platinum Membership'),
(4, 0.00, 'quiz', 4, 'Gift Quiz: Entertainment Special');
