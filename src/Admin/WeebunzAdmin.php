<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 */
namespace Weebunz\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // abort if called directly
}

class WeeBunzAdmin {

    private $plugin_name;
    private $version;

    /**
     * Store the plugin name and version.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
    }

    /**
     * Register all of the hooks related to the admin area.
     */
    public function run() {
        // Enqueue assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Register settings
        add_action( 'admin_init',          [ $this, 'register_settings' ] );

        // Build the menu
        add_action( 'admin_menu',          [ $this, 'add_admin_menu'     ] );
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'css/weebunz-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'js/weebunz-admin.js',
            [ 'jquery' ],
            $this->version,
            false
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

    public function register_settings() {
        // … your existing register_setting() calls …
    }

    /**
     * Build the top-level & submenus.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'WeeBunz Quiz Engine', 'weebunz-quiz-engine' ),
            __( 'WeeBunz',             'weebunz-quiz-engine' ),
            'manage_options',
            'weebunz-quiz-engine',
            [ $this, 'display_dashboard_page' ],
            'dashicons-welcome-learn-more',
            30
        );

        $subs = [
            [ 'Quizzes',       'weebunz-quiz-engine-quizzes',      'display_quizzes_page'      ],
            [ 'Questions',     'weebunz-quiz-engine-questions',    'display_questions_page'    ],
            [ 'Results',       'weebunz-quiz-engine-results',      'display_results_page'      ],
            [ 'Raffles',       'weebunz-quiz-engine-raffle',       'display_raffle_page'       ],
            [ 'Members',       'weebunz-quiz-engine-members',      'display_members_page'      ],
            [ 'Quiz Test',     'weebunz-quiz-engine-quiz-test',    'display_quiz_test_page'    ],
            [ 'Settings',      'weebunz-quiz-engine-settings',     'display_settings_page'     ],
            [ 'Performance',   'weebunz-quiz-engine-performance',  'display_performance_page'  ],
            [ 'Tools',         'weebunz-quiz-engine-tools',        'display_tools_page'        ],
            [ 'Load Testing',  'weebunz-quiz-engine-load-testing','display_load_testing_page' ],
        ];

        foreach ( $subs as list( $title, $slug, $callback ) ) {
            add_submenu_page(
                'weebunz-quiz-engine',
                __( $title, 'weebunz-quiz-engine' ),
                __( $title, 'weebunz-quiz-engine' ),
                'manage_options',
                $slug,
                [ $this, $callback ]
            );
        }
    }

    // — your existing display_*() methods unchanged —
    public function display_dashboard_page()    { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-dashboard.php'; }
    public function display_quizzes_page()      { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/quiz-management-page.php'; }
    public function display_questions_page()    { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/questions-page.php'; }
    public function display_results_page()      { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/results-page.php'; }
    public function display_raffle_page()       { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/manage-raffles-page.php'; }
    public function display_members_page()      { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/members-page.php'; }
    public function display_quiz_test_page()    { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/test-page.php'; }
    public function display_settings_page()     { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/settings-page.php'; }
    public function display_performance_page()  { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-performance.php'; }
    public function display_tools_page()        { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-tools.php'; }
    public function display_load_testing_page() { include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-load-testing.php'; }
}
