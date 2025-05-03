<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 */
class WeeBunz_Quiz_Engine {

    /**
     * The loader that's responsible for maintaining and registering all hooks
     * that power the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WeeBunz_Loader    $loader    Maintains and registers all hooks.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name  Uniquely identifies this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version  Plugin version.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = WEEBUNZ_QUIZ_VERSION;
        $this->plugin_name = 'weebunz-quiz-engine';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }

    /**
     * Load required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        $base = WEEBUNZ_PLUGIN_DIR;
        $files = [
            'includes/class-weebunz-loader.php',
            'includes/class-weebunz-i18n.php',
            'admin/class-weebunz-admin.php',
            'public/class-weebunz-public.php',
            // API stub (if exists)
            'includes/api/class-weebunz-api.php',
            // Optimization components
            'includes/optimization/class-weebunz-redis-cache.php',
            'includes/optimization/class-weebunz-session-handler.php',
            'includes/optimization/class-weebunz-db-manager.php',
            'includes/optimization/class-weebunz-rate-limiter.php',
            'includes/optimization/class-weebunz-error-handler.php',
            'includes/optimization/class-weebunz-background-processor.php',
            // Quiz managers
            'includes/quiz/class-weebunz-quiz-manager.php',
            'includes/quiz/class-weebunz-question-manager.php',
            'includes/quiz/class-weebunz-answer-manager.php',
            'includes/quiz/class-weebunz-session-manager.php',
            'includes/quiz/class-weebunz-raffle-manager.php',
        ];

        foreach ( $files as $file ) {
            $path = $base . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        // Instantiate the loader
        $this->loader = new WeeBunz_Loader();
    }

    /**
     * Set up internationalization.
     *
     * @since    1.0.0
     */
    private function set_locale() {
        $plugin_i18n = new WeeBunz_i18n();
        $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }

    /**
     * Register admin area hooks.
     *
     * @since    1.0.0
     */
    private function define_admin_hooks() {
        $plugin_admin = new WeeBunz_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'admin_menu',           $plugin_admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init',           $plugin_admin, 'register_settings' );
    }

    /**
     * Register public-facing hooks.
     *
     * @since    1.0.0
     */
    private function define_public_hooks() {
        $plugin_public = new WeeBunz_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
        $this->loader->add_action( 'init',              $plugin_public, 'register_shortcodes' );
    }

    /**
     * Register API hooks if the stub exists.
     *
     * @since    1.0.0
     */
    private function define_api_hooks() {
        if ( class_exists( 'WeeBunz_API' ) ) {
            $plugin_api = new WeeBunz_API( $this->get_plugin_name(), $this->get_version() );
            $this->loader->add_action( 'rest_api_init', $plugin_api, 'register_routes' );
        }
    }

    /**
     * Run the loader to execute all hooks.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /** Get plugin name. */
    public function get_plugin_name() { return $this->plugin_name; }

    /** Get loader instance. */
    public function get_loader() { return $this->loader; }

    /** Get plugin version. */
    public function get_version() { return $this->version; }
}
