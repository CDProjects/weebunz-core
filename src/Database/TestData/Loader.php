<?php
namespace Weebunz\Database\TestData;

/**
 * Class Loader
 *
 * Programmatically loads all test-data SQL files in this directory.
 */
class Loader {
    /**
     * Execute every .sql file in this folder.
     *
     * @param \wpdb $wpdb The WPDB instance.
     */
    public static function load( \wpdb $wpdb ) {
        // Grab every .sql file in this directory
        foreach ( glob( __DIR__ . '/*.sql' ) as $file ) {
            $sql = file_get_contents( $file );
            if ( ! empty( $sql ) ) {
                $wpdb->query( $sql );
                if ( $wpdb->last_error ) {
                    error_log( sprintf(
                        'Weebunz TestData Loader error in %s: %s',
                        basename( $file ),
                        $wpdb->last_error
                    ) );
                }
            }
        }
    }
}
