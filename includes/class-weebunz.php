<?php
namespace Weebunz;

use Weebunz\Loader;
use Weebunz\Weebunz_Admin;
use Weebunz\Weebunz_Public;
use Weebunz\Installer;
use Weebunz\Logger;

/**
 * Core singleton.
 */
class WeeBunz {
    /** @var Loader */
    protected $loader;

    /**
     * Constructor (private—use get_instance()).
     */
    private function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Require all of the class files we need.
     */
    private function load_dependencies() {
        // ALWAYS fully-qualify the constant so PHP knows to look in the global namespace
        require_once \WEEBUNZ_PLUGIN_DIR . 'includes/class-logger.php';
        require_once \WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-loader.php';
        require_once \WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-installer.php';
        require_once \WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-public.php';
        require_once \WEEBUNZ_PLUGIN_DIR . 'admin/class-weebunz-admin.php';

        $this->loader = new Loader();
    }

    /**
     * Register all of the admin-side hooks.
     */
    private function define_admin_hooks() {
        $admin = new WeeBunz_Admin( $this->loader );
        $admin->run();
    }

    /**
     * Register all of the public-side hooks.
     */
    private function define_public_hooks() {
        $public = new WeeBunz_Public( $this->loader );
        $public->run();
    }

    /**
     * Kick everything off.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Get the singleton.
     *
     * @return WeeBunz
     */
    public static function get_instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }
}
