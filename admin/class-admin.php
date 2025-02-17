<?php
// File: wp-content/plugins/weebunz-core/admin/class-admin.php

namespace Weebunz\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class Admin {
    private $plugin_name;
    private $version;
    private $pages = [];

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->init_admin_pages();
    }

    private function init_admin_pages() {
        $this->pages = [
            'dashboard' => [
                'title' => 'WeeBunz Dashboard',
                'menu_title' => 'WeeBunz',
                'capability' => 'manage_options',
                'menu_slug' => 'weebunz-dashboard',
                'function' => 'display_dashboard_page',
                'icon' => 'dashicons-games',
                'position' => 30
            ],
            'quizzes' => [
                'title' => 'Quiz Management',
                'menu_title' => 'Quizzes',
                'capability' => 'manage_options',
                'menu_slug' => 'weebunz-quizzes',
                'function' => 'display_quiz_management_page',
                'parent_slug' => 'weebunz-dashboard'
            ],
            'raffles' => [
                'title' => 'Raffle Management',
                'menu_title' => 'Raffles',
                'capability' => 'manage_options',
                'menu_slug' => 'weebunz-manage-raffles',
                'function' => 'display_raffle_management_page',
                'parent_slug' => 'weebunz-dashboard'
            ],
            'members' => [
                'title' => 'Member Management',
                'menu_title' => 'Members',
                'capability' => 'manage_options',
                'menu_slug' => 'weebunz-members',
                'function' => 'display_members_page',
                'parent_slug' => 'weebunz-dashboard'
            ],
            'quiz_test' => [
                'title' => 'Quiz Test',
                'menu_title' => 'Quiz Test',
                'capability' => 'manage_options',
                'menu_slug' => 'weebunz-quiz-test',
                'function' => 'display_quiz_test_page',
                'parent_slug' => 'weebunz-dashboard'
            ]
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->pages['test'] = [
                'title' => 'WeeBunz Tests',
                'menu_title' => 'Tests',
                'capability' => 'manage_options',
                'menu_slug' => 'weebunz-tests',
                'function' => 'display_test_page',
                'parent_slug' => 'weebunz-dashboard'
            ];
        }
    }

    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name . '-admin',
            plugin_dir_url(__FILE__) . 'css/weebunz-admin.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            plugin_dir_url(__FILE__) . 'js/weebunz-admin.js',
            ['jquery'],
            $this->version,
            true
        );

        // Only load test scripts when on test page
        if (isset($_GET['page']) && $_GET['page'] === 'weebunz-tests') {
            wp_enqueue_script(
                $this->plugin_name . '-test',
                plugin_dir_url(__FILE__) . 'js/quiz-test.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script($this->plugin_name . '-test', 'weebunzTest', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('weebunz_test'),
                'apiEndpoint' => rest_url('weebunz/v1')
            ]);
        }
    }

    public function add_admin_menu() {
        try {
            foreach ($this->pages as $key => $page) {
                if (isset($page['parent_slug'])) {
                    add_submenu_page(
                        $page['parent_slug'],
                        $page['title'],
                        $page['menu_title'],
                        $page['capability'],
                        $page['menu_slug'],
                        [$this, $page['function']]
                    );
                } else {
                    add_menu_page(
                        $page['title'],
                        $page['menu_title'],
                        $page['capability'],
                        $page['menu_slug'],
                        [$this, $page['function']],
                        $page['icon'],
                        $page['position']
                    );
                }
            }
        } catch (\Exception $e) {
            Logger::error('Failed to add admin menu: ' . $e->getMessage());
        }
    }

    public function register_settings() {
        register_setting('weebunz_options', 'weebunz_weekly_spend_limit');
        register_setting('weebunz_options', 'weebunz_phone_answer_timeout');
        register_setting('weebunz_options', 'weebunz_winner_question_timeout');
    }

    // Page display methods
    public function display_dashboard_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/dashboard-page.php';
    }

    public function display_quiz_management_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/quiz-management-page.php';
    }

    public function display_raffle_management_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/manage-raffles-page.php';
    }

    public function display_members_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/members-page.php';
    }

    public function display_quiz_test_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/quiz-test-page.php';
    }

    public function display_test_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/test-page.php';
    }
}