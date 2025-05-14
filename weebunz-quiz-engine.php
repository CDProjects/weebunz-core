<?php
/**
 * Plugin Name:     WeeBunz Quiz Engine
 * Plugin URI:      https://weebunz.com
 * Description:     A high-performance quiz engine optimized for concurrent users.
 * Version:         1.1.0
 * Author:          WeeBunz Team
 * Text Domain:     weebunz-quiz-engine
 * Domain Path:     /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // abort if called directly
}

// 1) Define the plugin directory for convenience
define( 'WEEBUNZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEEBUNZ_VERSION', '1.1.0' );

// 2) Load Composer’s PSR-4 autoloader
$autoloader = WEEBUNZ_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
} else {
    trigger_error( 'Weebunz Quiz Engine: Autoloader not found.', E_USER_ERROR );
    return;
}

// 3) Register deactivation hook via your PSR-4 Deactivator
if ( class_exists( \Weebunz\Setup\Deactivator::class ) ) {
    register_deactivation_hook( __FILE__, [ \Weebunz\Setup\Deactivator::class, 'deactivate' ] );
}

// 4) DEBUGGING PSR-4 + CLASS LOADING

// Load the raw PSR-4 map that Composer produced
$psr4 = require WEEBUNZ_PLUGIN_DIR . 'vendor/composer/autoload_psr4.php';

// What mapping do we have for the "Weebunz\" prefix?
$mapping = isset( $psr4['Weebunz\\\\'] ) ? $psr4['Weebunz\\\\'] : null;
error_log( 'Weebunz PSR-4 mapping: ' . var_export( $mapping, true ) );

// Where PSR-4 says the files live (should point to ".../src")
if ( is_array( $mapping ) && isset( $mapping[0] ) ) {
    $expected_src = $mapping[0];
    error_log( 'Expecting Core class at: ' . $expected_src . '/Core/WeeBunz.php' );

    // Does that file actually exist on disk?
    if ( file_exists( $expected_src . '/Core/WeeBunz.php' ) ) {
        error_log( 'Core file FOUND on disk.' );
    } else {
        error_log( 'Core file NOT found where PSR-4 says it should be.' );
    }
} else {
    error_log( 'No PSR-4 entry for "Weebunz\\" namespace.' );
}

// Finally, check class_exists
if ( class_exists( \Weebunz\Core\WeeBunz::class ) ) {
    error_log( 'Class \\Weebunz\\Core\\WeeBunz FOUND by autoloader!' );
    \Weebunz\Core\WeeBunz::get_instance()->run();
} else {
    error_log( 'Class \\Weebunz\\Core\\WeeBunz NOT FOUND by autoloader.' );
    trigger_error( 'Weebunz Quiz Engine: Core class not found.', E_USER_ERROR );
}
