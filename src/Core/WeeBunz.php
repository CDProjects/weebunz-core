<?php
namespace Weebunz\Core; // Updated namespace

if ( ! defined( "ABSPATH" ) ) exit;

// Add these imports to use your existing API controllers
use Weebunz\Api\QuizController;
use Weebunz\Api\AuthController;

class WeeBunz {
    private static $instance = null;
    private $loader;
    private $admin_handler; // Renamed for clarity
    private $public_handler; // Renamed for clarity
    private $quiz_controller; // Add this for the API
    private $auth_controller; // Add this for the API

    private function __construct() {
        $this->load_dependencies();
        $this->init_api_controllers(); // Add this method call
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
        // Assuming Admin.php contains class WeebunzAdmin within namespace Weebunz\Admin
        $this->admin_handler  = new \Weebunz\Admin\WeebunzAdmin( "weebunz-quiz-engine", WEEBUNZ_VERSION ); 
        // Assuming Public.php contains class PublicHooks within namespace Weebunz\Public
        $this->public_handler = new \Weebunz\Public\PublicHooks( "weebunz-quiz-engine", WEEBUNZ_VERSION ); 
    }

    /**
     * Initialize API controllers
     */
    private function init_api_controllers() {
        // Check if controllers exist and initialize them
        if (class_exists('\\Weebunz\\Api\\QuizController')) {
            $this->quiz_controller = new QuizController();
            $this->loader->add_action('rest_api_init', $this->quiz_controller, 'register_routes');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WeeBunz Quiz Controller initialized');
            }
        } else if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WeeBunz Quiz Controller class not found');
        }
        
        if (class_exists('\\Weebunz\\Api\\AuthController')) {
            $this->auth_controller = new AuthController();
            $this->loader->add_action('rest_api_init', $this->auth_controller, 'register_routes');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WeeBunz Auth Controller initialized');
            }
        } else if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WeeBunz Auth Controller class not found');
        }
    }

    private function define_admin_hooks() {
        // enqueue assets
        $this->loader->add_action( "admin_enqueue_scripts", $this->admin_handler, "enqueue_styles" );
        $this->loader->add_action( "admin_enqueue_scripts", $this->admin_handler, "enqueue_scripts" );

        // register menu & settings
        $this->loader->add_action( "admin_menu", $this->admin_handler, "add_admin_menu" );
        $this->loader->add_action( "admin_init", $this->admin_handler, "register_settings" );
    }

    private function define_public_hooks() {
        $this->loader->add_action( "wp_enqueue_scripts", $this->public_handler, "enqueue_styles" );
        $this->loader->add_action( "wp_enqueue_scripts", $this->public_handler, "enqueue_scripts" );
        $this->loader->add_action( 'init', $this->public_handler, 'register_shortcodes' ); // Renamed for clarity
    }

    public function run() {
        $this->loader->run();
    }
}
