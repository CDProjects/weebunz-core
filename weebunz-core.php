<?php
/**
 * Core library for the WeeBunz Quiz Engine.
 * This file no longer carries a Plugin Name header so WP won't register it as its own plugin.
 */

// Prevent direct access
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Prevent multiple loading
if ( defined( 'WEEBUNZ_VERSION' ) ) {
    return;
}

// Plugin constants
define( 'WEEBUNZ_VERSION',     '1.0.0' );
define( 'WEEBUNZ_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WEEBUNZ_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// Core dependencies
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-logger.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-loader.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz.php';
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-public.php';
require_once WEEBUNZ_PLUGIN_DIR . 'admin/class-weebunz-admin.php';

// Installer class
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-installer.php';

/**
 * Activation handler – runs Installer::install()
 */
function activate_weebunz() {
    // Initialize logger if available
    if ( class_exists( '\Weebunz\Logger' ) ) {
        \Weebunz\Logger::init();
        \Weebunz\Logger::info( 'Starting plugin activation...' );
    }

    // Run the installer
    $install_result = \Weebunz\Installer::install();

    if ( ! $install_result ) {
        if ( class_exists( '\Weebunz\Logger' ) ) {
            \Weebunz\Logger::error( 'Plugin activation failed during installation step.' );
        }
        return; // Stop activation if install failed
    }

    // Create upload directories
    $u = wp_upload_dir();
    wp_mkdir_p( $u['basedir'] . '/weebunz/temp' );
    wp_mkdir_p( $u['basedir'] . '/weebunz/exports' );

    // Flush rewrite rules
    flush_rewrite_rules();

    if ( class_exists( '\Weebunz\Logger' ) ) {
        \Weebunz\Logger::info( 'Plugin activation completed successfully' );
    }
}

/**
 * Deactivation handler
 */
function deactivate_weebunz() {
    if ( class_exists( '\Weebunz\Logger' ) ) {
        \Weebunz\Logger::info( 'Starting plugin deactivation...' );
    }
    flush_rewrite_rules();
    if ( class_exists( '\Weebunz\Logger' ) ) {
        \Weebunz\Logger::info( 'Plugin deactivation completed successfully' );
    }
}

// register_activation_hook(   __FILE__, 'activate_weebunz' );
// register_deactivation_hook( __FILE__, 'deactivate_weebunz' );

// Early textdomain load
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain(
        'weebunz-core',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );
}, 5 );

// Boot the core
// add_action( 'plugins_loaded', function() {
//     \Weebunz\WeeBunz::get_instance()->run();
// }, 20 );
