<?php
/**
 * Plugin Name:     WeeBunz Quiz Engine
 * Plugin URI:      https://weebunz.com
 * Description:     A high-performance quiz engine optimized for concurrency.
 * Version:         1.0.0
 * Author:          WeeBunz Team
 * Text Domain:     weebunz-quiz-engine
 * Domain Path:     /languages
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// 1) Load the core library
require_once __DIR__ . '/weebunz-core.php';

// 2) Activation / Deactivation
function activate_weebunz_quiz_engine() {
    // Core installation (creates tables, folders, etc.)
    \Weebunz\Installer::install();

    // Engine-specific activation
    require_once WEEBUNZ_CORE_DIR . 'includes/class-weebunz-activator.php';
    WeeBunz_Activator::activate();

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'activate_weebunz_quiz_engine' );

function deactivate_weebunz_quiz_engine() {
    // Engine-specific deactivation
    require_once WEEBUNZ_CORE_DIR . 'includes/class-weebunz-deactivator.php';
    WeeBunz_Deactivator::deactivate();

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'deactivate_weebunz_quiz_engine' );

// 3) Internationalize & Boot
add_action( 'plugins_loaded', function() {
    // Load text domain
    load_plugin_textdomain(
        'weebunz-quiz-engine',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages/'
    );

    // Now that WP is ready, run the core (register menus, hooks, etc.)
    weebunz_core()->run();

}, 20 );
