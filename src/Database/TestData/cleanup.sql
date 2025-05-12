-- Save as: wp-content/plugins/weebunz-core/includes/database/test-data/cleanup.sql

-- Clean existing test data
DELETE FROM `{prefix}question_answers`;
DELETE FROM `{prefix}questions_pool`;
DELETE FROM `{prefix}quiz_tag_relations`;
DELETE FROM `{prefix}quiz_categories`;
DELETE FROM `{prefix}quiz_tags`;
DELETE FROM `{prefix}winner_questions_pool`;
DELETE FROM `{prefix}platinum_memberships`;

-- Reset auto-increment values
ALTER TABLE `{prefix}question_answers` AUTO_INCREMENT = 1;
ALTER TABLE `{prefix}questions_pool` AUTO_INCREMENT = 1;
ALTER TABLE `{prefix}quiz_categories` AUTO_INCREMENT = 1;
ALTER TABLE `{prefix}quiz_tags` AUTO_INCREMENT = 1;
ALTER TABLE `{prefix}winner_questions_pool` AUTO_INCREMENT = 1;