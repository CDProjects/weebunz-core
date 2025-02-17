-- Original working version from before plugin deletion
-- Location: wp-content/plugins/weebunz-core/includes/database/schema.sql

-- Quiz Types (Wee buns, Deadly, Gift)
CREATE TABLE IF NOT EXISTS `{prefix}quiz_sessions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` varchar(36) NOT NULL,
    `quiz_type_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED,
    `session_data` longtext NOT NULL,
    `status` enum('active', 'completed', 'expired') NOT NULL DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` datetime NOT NULL,
    `ended_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_id` (`session_id`),
    KEY `quiz_type_id` (`quiz_type_id`),
    KEY `user_id` (`user_id`),
    KEY `status_expires` (`status`, `expires_at`),
    CONSTRAINT `fk_session_quiz_type` FOREIGN KEY (`quiz_type_id`) 
        REFERENCES `{prefix}quiz_types` (`id`),
    CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) 
        REFERENCES `{prefix}users` (`ID`) ON DELETE SET NULL
) {charset_collate};

-- Quiz Tags
CREATE TABLE IF NOT EXISTS `{prefix}quiz_tags` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `slug` varchar(50) NOT NULL,
    `type` enum('status', 'promotion', 'custom') NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) {charset_collate};

-- Active Quizzes
CREATE TABLE IF NOT EXISTS `{prefix}active_quizzes` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `quiz_type_id` bigint(20) UNSIGNED NOT NULL,
    `title` varchar(255) NOT NULL,
    `quiz_code` varchar(8) NOT NULL,
    `status` enum('draft', 'active', 'finished') NOT NULL DEFAULT 'draft',
    `discount_percentage` decimal(5,2) DEFAULT NULL,
    `start_date` datetime NOT NULL,
    `end_date` datetime NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `quiz_code` (`quiz_code`),
    KEY `quiz_type_id` (`quiz_type_id`),
    CONSTRAINT `fk_active_quiz_type` FOREIGN KEY (`quiz_type_id`) 
        REFERENCES `{prefix}quiz_types` (`id`)
) {charset_collate};

-- Quiz-Tag Relations
CREATE TABLE IF NOT EXISTS `{prefix}quiz_tag_relations` (
    `quiz_id` bigint(20) UNSIGNED NOT NULL,
    `tag_id` bigint(20) UNSIGNED NOT NULL,
    PRIMARY KEY (`quiz_id`, `tag_id`),
    CONSTRAINT `fk_relation_quiz` FOREIGN KEY (`quiz_id`) 
        REFERENCES `{prefix}active_quizzes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_relation_tag` FOREIGN KEY (`tag_id`) 
        REFERENCES `{prefix}quiz_tags` (`id`) ON DELETE CASCADE
) {charset_collate};

-- Questions Pool
CREATE TABLE IF NOT EXISTS `{prefix}questions_pool` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_text` text NOT NULL,
    `question_type` enum('multiple_choice', 'true_false', 'skill_based') NOT NULL,
    `category` varchar(100) NOT NULL,
    `difficulty_level` enum('easy', 'medium', 'hard') NOT NULL,
    `time_limit` int(11) NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category` (`category`),
    KEY `difficulty_level` (`difficulty_level`)
) {charset_collate};

-- Winner Questions Pool
CREATE TABLE IF NOT EXISTS `{prefix}winner_questions_pool` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_text` text NOT NULL,
    `correct_answer` text NOT NULL,
    `difficulty_level` enum('easy', 'medium', 'hard') NOT NULL,
    `used_count` int(11) NOT NULL DEFAULT 0,
    `last_used` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) {charset_collate};

-- Raffle Events
CREATE TABLE IF NOT EXISTS `{prefix}raffle_events` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `prize_description` text NOT NULL,
    `is_live_event` tinyint(1) NOT NULL DEFAULT 0,
    `event_date` datetime NOT NULL,
    `status` enum('scheduled', 'active', 'completed') NOT NULL DEFAULT 'scheduled',
    `entry_limit` int(11) DEFAULT 200,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) {charset_collate};

-- Raffle Entries
CREATE TABLE IF NOT EXISTS `{prefix}raffle_entries` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `raffle_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED,
    `entry_number` bigint(20) UNSIGNED NOT NULL,
    `phone_number` varchar(20) NOT NULL,
    `source_type` enum('quiz', 'platinum', 'mega_quiz') NOT NULL,
    `source_id` bigint(20) UNSIGNED NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `raffle_entry` (`raffle_id`, `entry_number`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_entry_raffle` FOREIGN KEY (`raffle_id`) 
        REFERENCES `{prefix}raffle_events` (`id`),
    CONSTRAINT `fk_entry_user` FOREIGN KEY (`user_id`) 
        REFERENCES `{prefix}users` (`ID`) ON DELETE SET NULL
) {charset_collate};

-- Raffle Draws
CREATE TABLE IF NOT EXISTS `{prefix}raffle_draws` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `raffle_id` bigint(20) UNSIGNED NOT NULL,
    `entry_id` bigint(20) UNSIGNED NOT NULL,
    `winner_question_id` bigint(20) UNSIGNED NOT NULL,
    `phone_answer_time` int(11),
    `question_answer_time` int(11),
    `answered_correctly` tinyint(1),
    `draw_time` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `raffle_id` (`raffle_id`),
    KEY `entry_id` (`entry_id`),
    KEY `winner_question_id` (`winner_question_id`),
    CONSTRAINT `fk_draw_raffle` FOREIGN KEY (`raffle_id`) 
        REFERENCES `{prefix}raffle_events` (`id`),
    CONSTRAINT `fk_draw_entry` FOREIGN KEY (`entry_id`) 
        REFERENCES `{prefix}raffle_entries` (`id`),
    CONSTRAINT `fk_draw_question` FOREIGN KEY (`winner_question_id`) 
        REFERENCES `{prefix}winner_questions_pool` (`id`)
) {charset_collate};

-- Platinum Member Management
CREATE TABLE IF NOT EXISTS `{prefix}platinum_memberships` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `plan_duration` enum('monthly', 'quarterly', 'biannual', 'annual') NOT NULL,
    `start_date` datetime NOT NULL,
    `end_date` datetime NOT NULL,
    `status` enum('active', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
    `free_quizzes_remaining` int(11) NOT NULL DEFAULT 3,
    `accumulated_entries` int(11) NOT NULL DEFAULT 0,
    `monthly_points` int(11) NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_platinum_user` FOREIGN KEY (`user_id`) 
        REFERENCES `{prefix}users` (`ID`) ON DELETE CASCADE
) {charset_collate};

-- MEGA Quiz Events
CREATE TABLE IF NOT EXISTS `{prefix}mega_quiz_events` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `event_date` datetime NOT NULL,
    `entry_fee` decimal(10,2) NOT NULL DEFAULT 4.00,
    `question_count` int(11) NOT NULL DEFAULT 20,
    `answers_per_entry` int(11) NOT NULL DEFAULT 2,
    `max_entries` int(11) NOT NULL DEFAULT 10,
    `status` enum('scheduled', 'active', 'completed') NOT NULL DEFAULT 'scheduled',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) {charset_collate};

-- Quiz Categories Table (for organizing questions)
CREATE TABLE IF NOT EXISTS `{prefix}quiz_categories` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `slug` varchar(100) NOT NULL,
    `description` text,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) {charset_collate};

-- Question Answers Table (for storing multiple choice options)
CREATE TABLE IF NOT EXISTS `{prefix}question_answers` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_id` bigint(20) UNSIGNED NOT NULL,
    `answer_text` text NOT NULL,
    `is_correct` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `question_id` (`question_id`),
    CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) 
        REFERENCES `{prefix}questions_pool` (`id`) ON DELETE CASCADE
) {charset_collate};

-- Quiz Attempts Table (for tracking user quiz attempts)
CREATE TABLE IF NOT EXISTS `{prefix}quiz_attempts` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED,
    `quiz_id` bigint(20) UNSIGNED NOT NULL,
    `start_time` datetime NOT NULL,
    `end_time` datetime,
    `score` int(11) NOT NULL DEFAULT 0,
    `entries_earned` int(11) NOT NULL DEFAULT 0,
    `status` enum('in_progress', 'completed', 'abandoned') NOT NULL DEFAULT 'in_progress',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `quiz_id` (`quiz_id`),
    CONSTRAINT `fk_attempt_user` FOREIGN KEY (`user_id`) 
        REFERENCES `{prefix}users` (`ID`) ON DELETE SET NULL,
    CONSTRAINT `fk_attempt_quiz` FOREIGN KEY (`quiz_id`) 
        REFERENCES `{prefix}active_quizzes` (`id`) ON DELETE CASCADE
) {charset_collate};

-- User Answers Table (for storing user responses)
CREATE TABLE IF NOT EXISTS `{prefix}user_answers` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_id` bigint(20) UNSIGNED NOT NULL,
    `question_id` bigint(20) UNSIGNED NOT NULL,
    `answer_id` bigint(20) UNSIGNED NOT NULL,
    `time_taken` int(11),
    `is_correct` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `attempt_id` (`attempt_id`),
    KEY `question_id` (`question_id`),
    KEY `answer_id` (`answer_id`),
    CONSTRAINT `fk_user_answer_attempt` FOREIGN KEY (`attempt_id`) 
        REFERENCES `{prefix}quiz_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_answer_question` FOREIGN KEY (`question_id`) 
        REFERENCES `{prefix}questions_pool` (`id`),
    CONSTRAINT `fk_user_answer_answer` FOREIGN KEY (`answer_id`) 
        REFERENCES `{prefix}question_answers` (`id`)
) {charset_collate};

-- Quiz Sessions Table (for managing active quiz sessions)
CREATE TABLE IF NOT EXISTS `{prefix}quiz_sessions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` varchar(36) NOT NULL,
    `quiz_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED,
    `session_data` longtext NOT NULL,
    `status` enum('active', 'completed', 'expired') NOT NULL DEFAULT 'active',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` datetime NOT NULL,
    `ended_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_id` (`session_id`),
    KEY `quiz_id` (`quiz_id`),
    KEY `user_id` (`user_id`),
    KEY `status_expires` (`status`, `expires_at`),
    CONSTRAINT `fk_session_quiz` FOREIGN KEY (`quiz_id`) 
        REFERENCES `{prefix}active_quizzes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) 
        REFERENCES `{prefix}users` (`ID`) ON DELETE SET NULL
) {charset_collate};

-- Spending Log Table
CREATE TABLE IF NOT EXISTS `{prefix}spending_log` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `type` enum('quiz', 'membership', 'mega_quiz') NOT NULL,
    `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
    `description` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `created_at` (`created_at`),
    KEY `type_reference` (`type`, `reference_id`),
    CONSTRAINT `fk_spending_user` FOREIGN KEY (`user_id`) 
        REFERENCES `{prefix}users` (`ID`) ON DELETE CASCADE
) {charset_collate};