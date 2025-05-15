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

if ( ! defined( 'WEEBUNZ_PLUGIN_URL' ) ) {
    define(
        'WEEBUNZ_PLUGIN_URL',
        plugin_dir_url( __FILE__ )
    );
}

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

// 4) DEBUGGING PSR-4 + CLASS LOADING (corrected)

// Load the raw PSR-4 map that Composer produced
$psr4 = require WEEBUNZ_PLUGIN_DIR . 'vendor/composer/autoload_psr4.php';

// 4a) Show all registered PSR-4 prefixes
$prefixes = array_keys( $psr4 );
error_log( 'Registered PSR-4 prefixes: ' . implode( ', ', $prefixes ) );

// 4b) Now look specifically for our namespace
$ns = 'Weebunz\\';
if ( isset( $psr4[ $ns ] ) ) {
    $mapping = $psr4[ $ns ];
    error_log( "Found mapping for \"$ns\": " . var_export( $mapping, true ) );

    // Check the expected file location
    $expected = $mapping[0] . '/Core/WeeBunz.php';
    error_log( "Expecting Core class at: $expected" );
    error_log( file_exists( $expected )
        ? 'Core file FOUND on disk.'
        : 'Core file NOT found where PSR-4 says it should be.'
    );
} else {
    error_log( "No PSR-4 entry for namespace \"$ns\"." );
}

// 4c) Finally, check class_exists
if ( class_exists( \Weebunz\Core\WeeBunz::class ) ) {
    error_log( 'Class \\Weebunz\\Core\\WeeBunz FOUND by autoloader!' );
    \Weebunz\Core\WeeBunz::get_instance()->run();
} else {
    error_log( 'Class \\Weebunz\\Core\\WeeBunz NOT FOUND by autoloader.' );
    trigger_error( 'Weebunz Quiz Engine: Core class not found.', E_USER_ERROR );
}
