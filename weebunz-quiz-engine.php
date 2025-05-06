<?php
/**
 * Plugin Name:     WeeBunz Quiz Engine
 * Plugin URI:      https://weebunz.com
 * Description:     A high-performance quiz engine optimized for Kinsta hosting with support for hundreds to thousands of concurrent users
 * Version:         1.0.0
 * Author:          WeeBunz Team
 * Text Domain:     weebunz-quiz-engine
 * Domain Path:     /languages
 */

// Abort if called directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Load the core library
require_once __DIR__ . '/weebunz-core.php';

// Quiz‐engine constants
if ( ! defined( 'WEEBUNZ_QUIZ_VERSION' ) ) {
    define( 'WEEBUNZ_QUIZ_VERSION', '1.0.0' );
}
if ( ! defined( 'WEEBUNZ_QUIZ_PLUGIN_BASENAME' ) ) {
    define( 'WEEBUNZ_QUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Activation & Deactivation
function activate_weebunz_quiz_engine() {
    require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-activator.php';
    WeeBunz_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_weebunz_quiz_engine' );

function deactivate_weebunz_quiz_engine() {
    require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-deactivator.php';
    WeeBunz_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_weebunz_quiz_engine' );

// Bootstrap the Quiz Engine
require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-quiz-engine.php';

function run_weebunz_quiz_engine() {
    $plugin = new WeeBunz_Quiz_Engine();
    $plugin->run();
}
run_weebunz_quiz_engine();
