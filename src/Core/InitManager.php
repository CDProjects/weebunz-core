<?php
// wp-content/plugins/weebunz-core/includes/class-init-manager.php

namespace Weebunz\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Init_Manager {
    private $required_directories = [
        'weebunz',
        'weebunz/temp',
        'weebunz/exports'
    ];

    /**
     * Initialize required system components
     */
    public function initialize() {
        $this->create_upload_directories();
        $this->set_permissions();
    }

    /**
     * Create required upload directories
     */
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        foreach ($this->required_directories as $dir) {
            $full_path = $base_dir . '/' . $dir;
            
            if (!file_exists($full_path)) {
                wp_mkdir_p($full_path);
            }
        }
    }

    /**
     * Set correct permissions on directories
     */
    private function set_permissions() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        foreach ($this->required_directories as $dir) {
            $full_path = $base_dir . '/' . $dir;
            
            if (file_exists($full_path)) {
                chmod($full_path, 0755);
                
                // Create .htaccess for security
                if ($dir === 'weebunz/temp') {
                    $this->secure_temp_directory($full_path);
                }
            }
        }
    }

    /**
     * Add security measures to temp directory
     */
    private function secure_temp_directory($dir) {
        $htaccess = $dir . '/.htaccess';
        
        if (!file_exists($htaccess)) {
            $content = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccess, $content);
        }
    }
}