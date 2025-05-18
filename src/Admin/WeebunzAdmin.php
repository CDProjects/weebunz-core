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

class WeebunzAdmin {

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
        
        // Register AJAX test handler
        add_action( 'wp_ajax_weebunz_test_api', [ $this, 'handle_ajax_test_api' ] );
    }

    /**
     * Enqueue admin CSS.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'assets/css/weebunz-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    /**
     * Enqueue admin JS (and conditionally load React on our quiz-test page).
     */
    public function enqueue_scripts() {
        $is_quiz_test_page = isset( $_GET['page'] ) && $_GET['page'] === 'weebunz-quiz-engine-quiz-test';
        
        // Base admin script
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'assets/js/weebunz-admin.js',
            [ 'jquery' ],
            $this->version,
            false
        );

        if ( $is_quiz_test_page ) {
            // React + ReactDOM for the quiz test harness
            wp_enqueue_script( 'react',     'https://unpkg.com/react@17/umd/react.development.js', [], '17.0.2', false );
            wp_enqueue_script( 'react-dom', 'https://unpkg.com/react-dom@17/umd/react-dom.development.js', [ 'react' ], '17.0.2', false );

            // Our quiz components bundle
            wp_enqueue_script(
                $this->plugin_name . '-admin-quiz-components',
                plugin_dir_url( __FILE__ ) . 'assets/js/quiz-components.admin.js',
                [ 'jquery', 'react', 'react-dom' ],
                $this->version,
                true
            );

            // Quiz test page logic
            wp_enqueue_script(
                $this->plugin_name . '-admin-quiz-test',
                plugin_dir_url( __FILE__ ) . 'assets/js/quiz-test.admin.js',
                [ 'jquery', $this->plugin_name . '-admin-quiz-components' ],
                $this->version,
                true
            );

            // API-test helper
            wp_enqueue_script(
                $this->plugin_name . '-admin-api-test',
                plugin_dir_url( __FILE__ ) . 'assets/js/api-test.js',
                [ 'jquery' ],
                $this->version,
                true
            );

            // Localize data for JS
            wp_localize_script(
                $this->plugin_name . '-admin-quiz-test',
                'weebunzTest',
                [
                    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                    'nonce'       => wp_create_nonce( 'wp_rest' ),
                    'apiEndpoint' => rest_url( 'weebunz/v1' ),
                    'debug'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
                    'demoStats'   => [
                        'maxConcurrent'  => 500,
                        'targetPlatform' => 'WordPress + React',
                        'scalingCapacity'=> 'Optimized for low-latency on shared hosting',
                    ],
                ]
            );

            // Console debug in footer if WP_DEBUG
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                add_action( 'admin_footer', function() {
                    ?>
                    <script>
                        console.log('WeeBunz API Debug Info:');
                        console.log('REST API URL: <?php echo esc_js( rest_url('weebunz/v1') ); ?>');
                        console.log('REST API Status: ' + (typeof window.wp !== 'undefined' ? 'Available' : 'Not Available'));
                        console.log('weebunzTest Localization:', window.weebunzTest);
                    </script>
                    <?php
                } );
            }
        }

        // Localize core admin script
        wp_localize_script(
            $this->plugin_name,
            'weebunz_quiz_admin',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'weebunz_quiz_nonce' ),
            ]
        );
    }

    /**
     * AJAX handler for our API test button.
     */
    public function handle_ajax_test_api() {
        check_ajax_referer( 'wp_rest', 'security' );

        wp_send_json_success( [
            'status'    => 'success',
            'message'   => 'API connection successful',
            'timestamp' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // Your existing register_settings code here
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
            [ 'Quizzes',      'weebunz-quiz-engine-quizzes',      'display_quizzes_page'      ],
            [ 'Questions',    'weebunz-quiz-engine-questions',    'display_questions_page'    ],
            [ 'Results',      'weebunz-quiz-engine-results',      'display_results_page'      ],
            [ 'Raffles',      'weebunz-quiz-engine-raffle',       'display_raffle_page'       ],
            [ 'Members',      'weebunz-quiz-engine-members',      'display_members_page'      ],
            [ 'Quiz Test',    'weebunz-quiz-engine-quiz-test',    'display_quiz_test_page'    ],
            [ 'Settings',     'weebunz-quiz-engine-settings',     'display_settings_page'     ],
            [ 'Performance',  'weebunz-quiz-engine-performance',  'display_performance_page'  ],
            [ 'Tools',        'weebunz-quiz-engine-tools',        'display_tools_page'        ],
            [ 'Load Testing', 'weebunz-quiz-engine-load-testing', 'display_load_testing_page' ],
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

    // === Existing display methods ===

    public function display_dashboard_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-dashboard.php';
    }

    public function display_quizzes_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/quiz-management-page.php';
    }

    public function display_questions_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/questions-page.php';
    }

    public function display_results_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/results-page.php';
    }

    public function display_raffle_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/manage-raffles-page.php';
    }

    public function display_members_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/members-page.php';
    }

    /**
     * Renders the Quiz Test page partial.
     */
    public function display_quiz_test_page() {
        // __DIR__ is .../weebunz-core/src/Admin
        $partial = __DIR__ . '/Partials/test-page.php';

        if ( file_exists( $partial ) ) {
            include_once $partial;
        } else {
            error_log( "WeebunzAdmin: partial not found at $partial" );
            echo '<div class="notice notice-error"><p>Quiz Test page not found.</p></div>';
        }
    }

    public function display_settings_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    public function display_performance_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-performance.php';
    }

    public function display_tools_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-admin-tools.php';
    }

    public function display_load_testing_page() {
        include_once WEEBUNZ_PLUGIN_DIR . 'admin/partials/weebunz-load-testing.php';
    }

}
