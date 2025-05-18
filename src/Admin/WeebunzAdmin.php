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
        add_action('wp_ajax_weebunz_test_api', [$this, 'handle_ajax_test_api']);
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'assets/css/weebunz-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        // Detect if we're on the quiz test page
        $is_quiz_test_page = isset($_GET['page']) && $_GET['page'] === 'weebunz-quiz-engine-quiz-test';
        
        // Base admin script
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'assets/js/weebunz-admin.js',
            [ 'jquery' ],
            $this->version,
            false
        );
        
        // Only load React and quiz testing scripts on the quiz test page
        if ($is_quiz_test_page) {
            // Load React and ReactDOM from CDN for testing
            wp_enqueue_script(
                'react',
                'https://unpkg.com/react@17/umd/react.development.js',
                array(),
                '17.0.2',
                false
            );
            
            wp_enqueue_script(
                'react-dom',
                'https://unpkg.com/react-dom@17/umd/react-dom.development.js',
                array('react'),
                '17.0.2',
                false
            );
            
            // Load the quiz components - IMPORTANT: load after React
            wp_enqueue_script(
                $this->plugin_name . "-admin-quiz-components", 
                plugin_dir_url( __FILE__ ) . "assets/js/quiz-components.admin.js", 
                array("jquery", "react", "react-dom"), 
                $this->version, 
                true  // Load in footer to ensure React is loaded first
            );
            
            // Load the quiz test script
            wp_enqueue_script(
                $this->plugin_name . '-admin-quiz-test', 
                plugin_dir_url( __FILE__ ) . 'assets/js/quiz-test.admin.js', 
                array('jquery', $this->plugin_name . "-admin-quiz-components"), 
                $this->version, 
                true  // Load in footer
            );
            
            // Load the API test script
            wp_enqueue_script(
                $this->plugin_name . '-admin-api-test', 
                plugin_dir_url( __FILE__ ) . 'assets/js/api-test.js', 
                array('jquery'), 
                $this->version, 
                true  // Load in footer
            );
            
            // Add localization for the quiz test script
            wp_localize_script(
                $this->plugin_name . '-admin-quiz-test', 
                'weebunzTest', 
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'apiEndpoint' => rest_url('weebunz/v1'),
                    'debug' => defined('WP_DEBUG') && WP_DEBUG,
                    'demoStats' => array(
                        'maxConcurrent' => 500,
                        'targetPlatform' => 'WordPress + React',
                        'scalingCapacity' => 'Optimized for low-latency on shared hosting'
                    )
                )
            );
            
            // Debug output to help diagnose API issues
            if (defined('WP_DEBUG') && WP_DEBUG) {
                add_action('admin_footer', function() {
                    echo "<script>
                        console.log('WeeBunz API Debug Info:');
                        console.log('REST API URL: " . esc_url(rest_url('weebunz/v1')) . "');
                        console.log('REST API Status: " . (function_exists('rest_get_server') ? 'Available' : 'Not Available') . "');
                        console.log('WP REST API Enabled: " . (get_option('permalink_structure') ? 'Yes' : 'No - Please enable pretty permalinks') . "');
                        console.log('weebunzTest Localization:', window.weebunzTest);
                    </script>";
                });
            }
        }
        
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
     * Test API AJAX handler
     */
    public function handle_ajax_test_api() {
        check_ajax_referer('wp_rest', 'security');
        
        wp_send_json_success([
            'status' => 'success',
            'message' => 'API connection successful',
            'timestamp' => current_time('mysql')
        ]);
    }

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
            [ 'Quizzes',       'weebunz-quiz-engine-quizzes',      'display_quizzes_page'      ],
            [ 'Questions',     'weebunz-quiz-engine-questions',    'display_questions_page'    ],
            [ 'Results',       'weebunz-quiz-engine-results',      'display_results_page'      ],
            [ 'Raffles',       'weebunz-quiz-engine-raffle',       'display_raffle_page'       ],
            [ 'Members',       'weebunz-quiz-engine-members',      'display_members_page'      ],
            [ 'Quiz Test',     'weebunz-quiz-engine-quiz-test',    'display_quiz_test_page'    ],
            [ 'Settings',      'weebunz-quiz-engine-settings',     'display_settings_page'     ],
            [ 'Performance',   'weebunz-quiz-engine-performance',  'display_performance_page'  ],
            [ 'Tools',         'weebunz-quiz-engine-tools',        'display_tools_page'        ],
            [ 'Load Testing',  'weebunz-quiz-engine-load-testing', 'display_load_testing_page' ],
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

    // Your existing display methods here
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
