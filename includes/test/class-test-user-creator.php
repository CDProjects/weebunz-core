<?php
// Save as: wp-content/plugins/weebunz-core/includes/test/class-test-user-creator.php

namespace Weebunz\Test;

if (!defined('ABSPATH')) {
    exit;
}

class Test_User_Creator {
    private $test_users = [
        [
            'user_login' => 'testuser1',
            'user_pass'  => 'testpass123',
            'user_email' => 'testuser1@example.com',
            'role'       => 'subscriber',
            'display_name' => 'Test User 1'
        ],
        [
            'user_login' => 'testuser2',
            'user_pass'  => 'testpass123',
            'user_email' => 'testuser2@example.com',
            'role'       => 'subscriber',
            'display_name' => 'Test User 2'
        ]
    ];

    /**
     * Create test users
     * @return array User IDs
     */
    public function create_users() {
        $user_ids = [];

        // First remove any existing test users
        $this->cleanup_test_users();

        foreach ($this->test_users as $user_data) {
            // Create user data array
            $userdata = array(
                'user_login'    => $user_data['user_login'],
                'user_pass'     => $user_data['user_pass'],
                'user_email'    => $user_data['user_email'],
                'role'          => $user_data['role'],
                'display_name'  => $user_data['display_name'],
                'show_admin_bar_front' => false
            );

            // Insert the user
            $user_id = wp_insert_user($userdata);

            if (!is_wp_error($user_id)) {
                $user_ids[] = $user_id;
            }
        }

        return $user_ids;
    }

    /**
     * Clean up existing test users
     */
    private function cleanup_test_users() {
        $test_emails = array_column($this->test_users, 'user_email');
        
        foreach ($test_emails as $email) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user($existing_user->ID, true);
            }
        }
    }
}