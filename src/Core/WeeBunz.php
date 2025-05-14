<?php
namespace Weebunz\Core; // Updated namespace

if ( ! defined( "ABSPATH" ) ) exit;

// These will be autoloaded by Composer
// use Weebunz\Core\Loader;
// use Weebunz\Admin\Admin;
// use Weebunz\Public\PublicPage; // Assuming Public.php contains class PublicPage or similar

class WeeBunz {
    private static $instance = null;
    private $loader;
    private $admin_handler; // Renamed for clarity
    private $public_handler; // Renamed for clarity

    private function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_dependencies() {
        // Autoloader handles these now, so require_once calls are removed.
        // Ensure your composer.json psr-4 autoload is set up correctly
        // and `composer dump-autoload` has been run.

        $this->loader = new \Weebunz\Core\Loader();
        // Assuming Admin.php contains class Admin within namespace Weebunz\Admin
        $this->admin_handler  = new \Weebunz\Admin\Admin( "weebunz-quiz-engine", WEEBUNZ_VERSION ); 
        // Assuming Public.php contains class Public within namespace Weebunz\Public
        $this->public_handler = new \Weebunz\Public\PublicHooks( "weebunz-quiz-engine", WEEBUNZ_VERSION ); 
    }

    private function define_admin_hooks() {
        // enqueue assets
        $this->loader->add_action( "admin_enqueue_scripts", $this->admin_handler, "enqueue_styles" );
        $this->loader->add_action( "admin_enqueue_scripts", $this->admin_handler, "enqueue_scripts" );

        // register menu & settings
        $this->loader->add_action( "admin_menu", $this->admin_handler, "add_admin_menu" );
        $this->loader->add_action( "admin_init", $this->admin_handler, "register_settings" );
        
        // Add other admin hooks as needed, e.g., for AJAX
        // $this->loader->add_action( "wp_ajax_weebunz_some_action", $this->admin_handler, "handle_ajax_some_action" );
    }

    private function define_public_hooks() {
        $this->loader->add_action( "wp_enqueue_scripts", $this->public_handler, "enqueue_styles" );
        $this->loader->add_action( "wp_enqueue_scripts", $this->public_handler, "enqueue_scripts" );
        $this->loader->add_action( "init", $this->public_handler, "init_shortcodes" ); // Renamed for clarity
        
        // Add other public hooks as needed, e.g., for REST API endpoints
        // $this->loader->add_action( "rest_api_init", $this->public_handler, "register_rest_routes" );
    }

    public function run() {
        $this->loader->run();
    }
}

