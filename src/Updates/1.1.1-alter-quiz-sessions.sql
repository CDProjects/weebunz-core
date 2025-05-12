SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '{prefix}quiz_sessions'
    AND COLUMN_NAME = 'quiz_type_id'
);

SET @add_column_sql = IF(@column_exists = 0,
    'ALTER TABLE `{prefix}quiz_sessions` 
     ADD COLUMN `quiz_type_id` bigint(20) UNSIGNED DEFAULT NULL',
    'SELECT 1');

PREPARE stmt FROM @add_column_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = '{prefix}quiz_sessions' 
    AND COLUMN_NAME = 'quiz_id' 
    AND REFERENCED_TABLE_NAME = '{prefix}active_quizzes'
);

SET @drop_fk_sql = IF(@constraint_name IS NOT NULL,
    CONCAT('ALTER TABLE `{prefix}quiz_sessions` DROP FOREIGN KEY ', @constraint_name),
    'SELECT 1');

PREPARE stmt FROM @drop_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @copy_data_sql = IF(@column_exists = 0,
    'UPDATE `{prefix}quiz_sessions` SET `quiz_type_id` = `quiz_id`',
    'SELECT 1');

PREPARE stmt FROM @copy_data_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @quiz_id_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '{prefix}quiz_sessions'
    AND COLUMN_NAME = 'quiz_id'
);

SET @drop_column_sql = IF(@quiz_id_exists > 0,
    'ALTER TABLE `{prefix}quiz_sessions` DROP COLUMN `quiz_id`',
    'SELECT 1');

PREPARE stmt FROM @drop_column_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `{prefix}quiz_sessions` 
MODIFY COLUMN `quiz_type_id` bigint(20) UNSIGNED NOT NULL;

SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = '{prefix}quiz_sessions'
    AND COLUMN_NAME = 'quiz_type_id'
    AND REFERENCED_TABLE_NAME = '{prefix}quiz_types'
);

SET @add_fk_sql = IF(@fk_exists = 0,
    'ALTER TABLE `{prefix}quiz_sessions` 
     ADD CONSTRAINT `fk_session_quiz_type` 
     FOREIGN KEY (`quiz_type_id`) REFERENCES `{prefix}quiz_types` (`id`)',
    'SELECT 1');

PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;