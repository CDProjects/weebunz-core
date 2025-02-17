-- Location: wp-content/plugins/weebunz-core/includes/database/updates/1.1.0-spending-log.sql

-- Check if table exists
SET @table_exists = (
    SELECT COUNT(*)
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() 
    AND table_name = '{prefix}spending_log'
);

-- Only create if table doesn't exist
SET @create_sql = IF(@table_exists = 0,
    'CREATE TABLE `{prefix}spending_log` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) UNSIGNED NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `type` enum("quiz", "membership", "mega_quiz") NOT NULL,
        `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
        `description` varchar(255) DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `created_at` (`created_at`),
        KEY `type_reference` (`type`, `reference_id`),
        CONSTRAINT `fk_spending_user` 
            FOREIGN KEY (`user_id`) 
            REFERENCES `{prefix}users` (`ID`) 
            ON DELETE CASCADE
    ) {charset_collate}',
    'SELECT "Table {prefix}spending_log already exists" AS message'
);

-- Execute the create statement
PREPARE create_stmt FROM @create_sql;
EXECUTE create_stmt;
DEALLOCATE PREPARE create_stmt;

-- Add any needed indexes if they don't exist
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
    AND table_name = '{prefix}spending_log'
    AND index_name = 'weekly_spending'
);

-- Add weekly spending index if it doesn't exist
SET @index_sql = IF(@index_exists = 0,
    'ALTER TABLE `{prefix}spending_log` 
    ADD INDEX `weekly_spending` (`user_id`, `created_at`)',
    'SELECT "Weekly spending index already exists" AS message'
);

PREPARE index_stmt FROM @index_sql;
EXECUTE index_stmt;
DEALLOCATE PREPARE index_stmt;