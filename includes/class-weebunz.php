<?php
// File: wp-content/plugins/weebunz-core/includes/class-weebunz.php

namespace Weebunz;

if (!defined('ABSPATH')) {
    exit;
}

class WeeBunz {
    private static $instance = null;
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $admin;
    protected $public;

    private function __construct() {
        $this->plugin_name = 'weebunz-core';
        $this->version = WEEBUNZ_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_dependencies() {
        try {
            require_once WEEBUNZ_PLUGIN_DIR . 'includes/class-weebunz-loader.php';
            
            // Initialize components
            $this->loader = new Loader();
            
            try {
                Logger::debug('Before Admin class instantiation');
                $this->admin = new Admin\Admin($this->get_plugin_name(), $this->get_version());
                Logger::debug('Admin class instantiated successfully');
                
                $this->public = new WeeBunz_Public($this->get_plugin_name(), $this->get_version());
                Logger::debug('Public class instantiated successfully');
                
            } catch (\Exception $e) {
                Logger::error('Class instantiation failed: ' . $e->getMessage());
                throw $e;
            }

        } catch (\Exception $e) {
            Logger::error('Failed to load dependencies: ' . $e->getMessage());
            throw $e;
        }
    }

    private function define_admin_hooks() {
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_scripts');
        $this->loader->add_action('init', $this->admin, 'init');
    }

    private function define_public_hooks() {
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $this->public, 'enqueue_scripts');
        $this->loader->add_action('init', $this->public, 'init');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }

    public function get_loader() {
        return $this->loader;
    }
}