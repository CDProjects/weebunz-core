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

// 4) Bootstrap the plugin
if ( class_exists( \Weebunz\Core\WeeBunz::class ) ) {
    \Weebunz\Core\WeeBunz::get_instance()->run();
} else {
    trigger_error( 'Weebunz Quiz Engine: Core class not found.', E_USER_ERROR );
}
