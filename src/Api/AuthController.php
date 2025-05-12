<?php
namespace Weebunz\Api;

if (!defined('ABSPATH')) {
    exit;
}

class AuthController {
    private $namespace = 'weebunz/v1';

    public function register_routes() {
        register_rest_route($this->namespace, '/register', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_registration'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_login'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handle_registration($request) {
        $params = $request->get_json_params();
        
        if (empty($params['email']) || empty($params['password']) || empty($params['name'])) {
            return new \WP_Error('missing_fields', 'Required fields are missing', ['status' => 400]);
        }

        if (!is_email($params['email'])) {
            return new \WP_Error('invalid_email', 'Invalid email address', ['status' => 400]);
        }

        if (email_exists($params['email'])) {
            return new \WP_Error('email_exists', 'Email already registered', ['status' => 400]);
        }

        $user_data = [
            'user_login' => $params['email'],
            'user_email' => $params['email'],
            'user_pass' => $params['password'],
            'display_name' => $params['name'],
            'role' => 'subscriber'
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return new \WP_Error('registration_failed', $user_id->get_error_message(), ['status' => 500]);
        }

        // Set initial user meta
        update_user_meta($user_id, '_weebunz_verification', [
            'verified' => false,
            'method' => '',
            'timestamp' => current_time('mysql')
        ]);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        return [
            'success' => true,
            'message' => 'Registration successful'
        ];
    }

    public function handle_login($request) {
        $params = $request->get_json_params();
        
        if (empty($params['email']) || empty($params['password'])) {
            return new \WP_Error('missing_fields', 'Required fields are missing', ['status' => 400]);
        }

        $user = get_user_by('email', $params['email']);
        
        if (!$user || !wp_check_password($params['password'], $user->user_pass, $user->ID)) {
            return new \WP_Error('invalid_credentials', 'Invalid credentials', ['status' => 401]);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        return [
            'success' => true,
            'message' => 'Login successful'
        ];
    }
}