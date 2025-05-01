<?php
/**
 * Plugin Name: WeeBunz Quiz Engine
 * Plugin URI: https://weebunz.com
 * Description: A high-performance quiz engine optimized for Kinsta hosting with support for hundreds to thousands of concurrent users
 * Version: 1.0.0
 * Author: WeeBunz Team
 * Text Domain: weebunz-quiz-engine
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WEEBUNZ_QUIZ_VERSION', '1.0.0');
define('WEEBUNZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEEBUNZ_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WEEBUNZ_QUIZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_weebunz_quiz_engine() {
    require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-activator.php';
    WeeBunz_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_weebunz_quiz_engine() {
    require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-deactivator.php';
    WeeBunz_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_weebunz_quiz_engine');
register_deactivation_hook(__FILE__, 'deactivate_weebunz_quiz_engine');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-quiz-engine.php';

/**
 * Begins execution of the plugin.
 */
function run_weebunz_quiz_engine() {
    $plugin = new WeeBunz_Quiz_Engine();
    $plugin->run();
}
run_weebunz_quiz_engine();
