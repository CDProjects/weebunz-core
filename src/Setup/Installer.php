<?php
namespace Weebunz\Setup; // Updated namespace

// Ensure this class can find the Logger if it's in a different namespace
use Weebunz\Util\Logger;

if ( ! defined( "ABSPATH" ) ) exit;

class Installer {
    public static function install() {
        global $wpdb;
        $prefix          = $wpdb->prefix;
        $charset_collate = $wpdb->get_charset_collate();

        // Get the path to the schema file - this should still be relative to the plugin root
        $schema_file = WEEBUNZ_PLUGIN_DIR . "includes/database/schema.sql";

        if ( ! file_exists( $schema_file ) ) {
            if (class_exists("Weebunz\Util\Logger")) {
                Logger::error("Database schema file not found: " . $schema_file);
            }
            return false; 
        }

        // Read the schema file content
        $sql_content = file_get_contents( $schema_file );
        if ( $sql_content === false ) {
            if (class_exists("Weebunz\Util\Logger")) {
                Logger::error("Failed to read database schema file: " . $schema_file);
            }
            return false;
        }

        // Replace placeholders
        $sql_content = str_replace( "{prefix}", $prefix, $sql_content );
        $sql_content = str_replace( "{charset_collate}", $charset_collate, $sql_content );

        // Execute the SQL using dbDelta
        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta( $sql_content );

        // Check for errors after dbDelta
        if ( ! empty( $wpdb->last_error ) ) {
            if (class_exists("Weebunz\Util\Logger")) {
                Logger::error( "Database Error during dbDelta: " . $wpdb->last_error );
            }
            // return false; // Decide if this should halt activation
        }

        // version var for future migrations
        update_option( "weebunz_db_version", WEEBUNZ_VERSION );

        if (class_exists("Weebunz\Util\Logger")) {
            Logger::info("Database tables created/updated successfully.");
        }
        return true;
    }
}

