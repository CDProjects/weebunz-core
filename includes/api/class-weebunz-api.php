<?php
/**
 * Minimal stub for the Quiz Engine’s API.
 *
 * This file should be placed at:
 * wp-content/plugins/weebunz-core/includes/api/class-weebunz-api.php
 */

class WeeBunz_API {
    /**
     * Register API hooks (REST, AJAX) here. Currently a no-op.
     */
    public static function define_api_hooks() {
        // Example:
        // add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Placeholder for registering REST routes.
     */
    public static function register_routes() {
        // Example:
        // register_rest_route( 'weebunz/v1', '/quizzes', [
        //     'methods'             => WP_REST_Server::READABLE,
        //     'callback'            => [ __CLASS__, 'get_quizzes' ],
        //     'permission_callback' => '__return_true',
        // ] );
    }
}