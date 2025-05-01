<?php
namespace Weebunz;
if ( ! defined( 'ABSPATH' ) ) exit;

class WeeBunz {
    private static $instance = null;
    private $loader;
    private $admin;
    private $public;

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
        require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-loader.php';
        require_once WEEBUNZ_PLUGIN_DIR . 'admin/class-weebunz-admin.php';
        require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-public.php';

        $this->loader = new Loader();
        $this->admin  = new Admin( 'weebunz-core', WEEBUNZ_VERSION );
        $this->public = new WeeBunz_Public( 'weebunz-core', WEEBUNZ_VERSION );
    }

    private function define_admin_hooks() {
        // enqueue assets
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );

        // **FIX**: register menu & settings
        $this->loader->add_action( 'admin_menu', $this->admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
    }

    private function define_public_hooks() {
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );
        $this->loader->add_action( 'init',           $this->public, 'init' );
    }

    public function run() {
        $this->loader->run();
    }
}
