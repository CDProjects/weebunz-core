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
// Define these constants as early as possible
if ( ! defined( 'WEEBUNZ_PLUGIN_DIR' ) ) {
    define( 'WEEBUNZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WEEBUNZ_VERSION' ) ) {
    define( 'WEEBUNZ_VERSION', '1.1.0' ); // Ensure this matches your actual plugin version
}
if ( ! defined( 'WEEBUNZ_PLUGIN_URL' ) ) {
    define( 'WEEBUNZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// 2) Load Composer’s PSR-4 autoloader
$autoloader = WEEBUNZ_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
} else {
    // It's critical to log this if it happens, as nothing else will work.
    error_log( 'Weebunz Quiz Engine: CRITICAL - Composer Autoloader not found at ' . $autoloader );
    // Using trigger_error is fine, but an explicit error_log is good too.
    trigger_error( 'Weebunz Quiz Engine: Autoloader not found.', E_USER_ERROR );
    return; // Stop further execution if autoloader is missing
}

// --- ACTIVATION HOOK REGISTRATION ---
// Ensure \Weebunz\Setup\Activator class exists and is autoloadable.
// This assumes your Activator.php is in a location like src/Setup/Activator.php
// and your composer.json maps the Weebunz\Setup namespace correctly.
if ( class_exists( \Weebunz\Setup\Activator::class ) ) {
    register_activation_hook( __FILE__, [ \Weebunz\Setup\Activator::class, 'activate' ] );
    // Log that the hook registration is attempted. This log will appear when the plugin file is parsed.
    error_log('WeeBunz Main: Activation hook registered for \Weebunz\Setup\Activator.');
} else {
    // This log will appear if the Activator class can't be found by the autoloader.
    error_log('WeeBunz Main: CRITICAL - \Weebunz\Setup\Activator class NOT FOUND. Activation hook NOT registered.');
}
// --- END ACTIVATION HOOK ---


// 3) Register deactivation hook via your PSR-4 Deactivator
if ( class_exists( \Weebunz\Setup\Deactivator::class ) ) {
    register_deactivation_hook( __FILE__, [ \Weebunz\Setup\Deactivator::class, 'deactivate' ] );
    error_log('WeeBunz Main: Deactivation hook registered for \Weebunz\Setup\Deactivator.');
} else {
    error_log('WeeBunz Main: \Weebunz\Setup\Deactivator class NOT FOUND. Deactivation hook NOT registered.');
}


// --- OPTIONAL: PLUGIN UPDATE / VERSION CHECK ---
// This function will run on every admin page load to check if an update (including DB schema changes) is needed.
if ( ! function_exists( 'weebunz_check_version_on_admin_init' ) ) {
    function weebunz_check_version_on_admin_init() {
        // Only run in the admin area and for users who can activate plugins
        if ( is_admin() && current_user_can( 'activate_plugins' ) ) {
            $installed_version = get_option('weebunz_version'); // This option is set by your Activator

            // WEEBUNZ_VERSION is defined at the top of this file
            if ( ! $installed_version || version_compare( $installed_version, WEEBUNZ_VERSION, '<' ) ) {
                error_log('WeeBunz Main (admin_init): Version mismatch (Installed: ' . $installed_version . ', Current: ' . WEEBUNZ_VERSION . ') or new install. Attempting to run Activator::activate().');
                if ( class_exists( \Weebunz\Setup\Activator::class ) ) {
                    // Run the full activation logic
                    \Weebunz\Setup\Activator::activate();
                    // The Activator should update the 'weebunz_version' option itself.
                } else {
                    error_log('WeeBunz Main (admin_init): CRITICAL - Version mismatch but \Weebunz\Setup\Activator class NOT FOUND for update.');
                }
            }
        }
    }
}
add_action( 'admin_init', 'weebunz_check_version_on_admin_init' );
// --- END PLUGIN UPDATE / VERSION CHECK ---


// 4) DEBUGGING PSR-4 + CLASS LOADING (corrected) - This part seems to be working fine based on your previous logs
// but keeping it won't hurt during debugging. These logs appear on every load.

// Load the raw PSR-4 map that Composer produced
$psr4 = require WEEBUNZ_PLUGIN_DIR . 'vendor/composer/autoload_psr4.php';

// 4a) Show all registered PSR-4 prefixes
$prefixes = array_keys( $psr4 );
// error_log( 'Registered PSR-4 prefixes: ' . implode( ', ', $prefixes ) ); // You can comment this out later to reduce log noise

// 4b) Now look specifically for our namespace
$ns = 'Weebunz\\';
if ( isset( $psr4[ $ns ] ) ) {
    $mapping = $psr4[ $ns ];
    // error_log( "Found mapping for \"$ns\": " . var_export( $mapping, true ) ); // You can comment this out

    // Check the expected file location
    // Assuming your main Weebunz classes (like Core) are in the 'src' directory mapped by PSR-4
    $expected_core_class_path = '';
    if (is_array($mapping) && !empty($mapping[0])) {
        $expected_core_class_path = $mapping[0] . 'Core/WeeBunz.php'; // Adjusted to use $mapping[0]
        // error_log( "Expecting Core class at: $expected_core_class_path" ); // You can comment this out
        // error_log( file_exists( $expected_core_class_path ) ? 'Core file FOUND on disk.' : 'Core file NOT found where PSR-4 says it should be.' ); // You can comment this out
    } else {
        // error_log("PSR-4 mapping for Weebunz\\ is not in the expected array format or is empty.");
    }
} else {
    // error_log( "No PSR-4 entry for namespace \"$ns\"." ); // You can comment this out
}

// 4c) Finally, check class_exists for the main plugin class and run it
if ( class_exists( \Weebunz\Core\WeeBunz::class ) ) {
    // error_log( 'Class \\Weebunz\\Core\\WeeBunz FOUND by autoloader! Initializing plugin.' ); // You can comment this out
    // Get instance and run the plugin
    \Weebunz\Core\WeeBunz::get_instance()->run();
} else {
    error_log( 'WeeBunz Main: CRITICAL - Class \\Weebunz\\Core\\WeeBunz NOT FOUND by autoloader. Plugin cannot run.' );
    // This is a fatal error for the plugin's operation.
    trigger_error( 'Weebunz Quiz Engine: Core class not found. Plugin will not function.', E_USER_ERROR );
}

?>