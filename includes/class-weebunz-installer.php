<?php
namespace Weebunz;
if ( ! defined( 'ABSPATH' ) ) exit;

class Installer {
    public static function install() {
        global $wpdb;
        $prefix          = $wpdb->prefix;
        $charset_collate = $wpdb->get_charset_collate();

        // Get the path to the schema file
        $schema_file = WEEBUNZ_PLUGIN_DIR . 'includes/database/schema.sql';

        if ( ! file_exists( $schema_file ) ) {
            Logger::error('Database schema file not found: ' . $schema_file);
            // Optionally throw an exception or return false
            return false; 
        }

        // Read the schema file content
        $sql_content = file_get_contents( $schema_file );
        if ( $sql_content === false ) {
            Logger::error('Failed to read database schema file: ' . $schema_file);
            return false;
        }

        // Replace placeholders
        $sql_content = str_replace( '{prefix}', $prefix, $sql_content );
        $sql_content = str_replace( '{charset_collate}', $charset_collate, $sql_content );

        // Execute the SQL using dbDelta
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_content );

        // Check for errors after dbDelta
        if ( ! empty( $wpdb->last_error ) ) {
            Logger::error( 'Database Error during dbDelta: ' . $wpdb->last_error );
            // Consider throwing an exception or returning false if critical
            // return false;
        }

        // version var for future migrations
        update_option( 'weebunz_db_version', WEEBUNZ_VERSION );

        Logger::info('Database tables created/updated successfully.');
        return true;
    }
}

