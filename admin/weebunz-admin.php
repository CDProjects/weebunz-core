<?php
namespace Weebunz;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The admin‐side functionality of the plugin.
 */
class Admin {

    /** @var string */
    private $plugin_name;

    /** @var string */
    private $version;

    public function __construct( string $plugin_name, string $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    public function enqueue_styles(): void {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( dirname( __FILE__ ) ) . 'css/weebunz-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts(): void {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( dirname( __FILE__ ) ) . 'js/weebunz-admin.js',
            [ 'jquery' ],
            $this->version,
            true
        );
        wp_localize_script(
            $this->plugin_name,
            'weebunz_quiz_admin',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'weebunz_quiz_nonce' ),
            ]
        );
    }

    public function add_admin_menu(): void {
        add_menu_page(
            __( 'WeeBunz Quiz Engine', 'weebunz-core' ),
            __( 'WeeBunz',          'weebunz-core' ),
            'manage_options',
            'weebunz-quiz-engine',
            [ $this, 'display_dashboard_page' ],
            'dashicons-welcome-learn-more',
            30
        );

        // … all other add_submenu_page() calls go here, unchanged …
    }

    public function register_settings(): void {
        // … register_setting() calls …
    }

    public function display_dashboard_page(): void {
        include WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-dashboard.php';
    }

    public function display_quizzes_page(): void {
        include WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-quizzes.php';
    }

    // … and all other display_* methods …
}
