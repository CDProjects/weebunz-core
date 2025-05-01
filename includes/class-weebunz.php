<?php
namespace Weebunz;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WeeBunz {

    /** @var WeeBunz */
    private static $instance = null;

    /** @var Loader */
    private $loader;

    /** @var Admin */
    private $admin;

    /** @var WeeBunz_Public */
    private $public;

    /**
     * Constructor. Loads dependencies and registers hooks.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Returns the singleton instance of this class.
     *
     * @return WeeBunz
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load all required classes.
     */
    private function load_dependencies() {
        require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-loader.php';
        require_once WEEBUNZ_PLUGIN_DIR . 'admin/class-weebunz-admin.php';
        require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-public.php';

        $this->loader = new Loader();
        $this->admin  = new Admin( WEEBUNZ_PLUGIN_NAME, WEEBUNZ_VERSION );
        $this->public = new WeeBunz_Public( WEEBUNZ_PLUGIN_NAME, WEEBUNZ_VERSION );
    }

    /**
     * Register all admin-side hooks.
     */
    private function define_admin_hooks() {
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu',            $this->admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init',            $this->admin, 'register_settings' );
    }

    /**
     * Register all public-facing hooks.
     */
    private function define_public_hooks() {
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );
        $this->loader->add_action( 'init',               $this->public, 'init' );
    }

    /**
     * Run the loader to execute all hooks.
     */
    public function run() {
        $this->loader->run();
    }
}
