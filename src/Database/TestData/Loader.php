<?php
namespace Weebunz\Database\TestData;

use wpdb;

class Loader {
    /**
     * Load all test-data SQL scripts in src/Database/TestData/
     *
     * @param wpdb $wpdb The global wpdb instance.
     */
    public static function load( wpdb $wpdb ) {
        // Find every .sql file in this directory
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
