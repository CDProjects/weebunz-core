<?php
/**
 * Core library for the WeeBunz Quiz Engine.
 * Defines classes, loader, installer, etc. — but does not auto-run anything.
 */

// Prevent direct access
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Prevent multiple loading
if ( defined( 'WEEBUNZ_CORE_VERSION' ) ) {
    return;
}

// Core constants
define( 'WEEBUNZ_CORE_VERSION', '1.0.0' );
define( 'WEEBUNZ_CORE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WEEBUNZ_CORE_URL',     plugin_dir_url( __FILE__ ) );

// Autoload (if using Composer)
if ( file_exists( WEEBUNZ_CORE_DIR . 'vendor/autoload.php' ) ) {
    require_once WEEBUNZ_CORE_DIR . 'vendor/autoload.php';
}

// Core class files
require_once WEEBUNZ_CORE_DIR . 'includes/class-logger.php';
require_once WEEBUNZ_CORE_DIR . 'includes/class-weebunz-loader.php';
require_once WEEBUNZ_CORE_DIR . 'includes/class-weebunz.php';
require_once WEEBUNZ_CORE_DIR . 'includes/class-weebunz-public.php';
require_once WEEBUNZ_CORE_DIR . 'admin/class-weebunz-admin.php';
require_once WEEBUNZ_CORE_DIR . 'includes/class-weebunz-installer.php';

/**
 * Helper to fetch the singleton core instance.
 *
 * @return \Weebunz\WeeBunz
 */
function weebunz_core() {
    return \Weebunz\WeeBunz::get_instance();
}
