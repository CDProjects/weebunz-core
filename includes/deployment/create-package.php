<?php
/**
 * Deployment Package Creator for WeeBunz Quiz Engine
 *
 * This file creates a deployment package for Kinsta hosting
 *
 * @package    Weebunz_Quiz_Engine
 * @subpackage Weebunz_Quiz_Engine/includes/deployment
 */

namespace Weebunz\Deployment;

if (!defined('ABSPATH')) {
    exit;
}

// Include the Kinsta Deployment Manager
require_once plugin_dir_path(__FILE__) . 'class-kinsta-deployment-manager.php';

/**
 * Create deployment package
 */
function create_deployment_package() {
    // Get deployment manager
    $deployment_manager = Kinsta_Deployment_Manager::get_instance();
    
    // Check environment
    $requirements = $deployment_manager->check_environment();
    $all_requirements_met = !in_array(false, $requirements);
    
    if (!$all_requirements_met) {
        echo "Warning: Not all deployment requirements are met.\n";
        echo "Please check the following requirements:\n";
        
        foreach ($requirements as $key => $met) {
            if (!$met) {
                echo "- $key: Not met\n";
            }
        }
        
        echo "\n";
    }
    
    // Create temporary directory for package
    $temp_dir = sys_get_temp_dir() . '/weebunz-deployment-' . time();
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    // Create deployment package
    $package_file = $deployment_manager->create_deployment_package($temp_dir);
    
    if ($package_file && file_exists($package_file)) {
        echo "Deployment package created successfully: $package_file\n";
        
        // Copy to destination
        $destination = dirname(plugin_dir_path(dirname(__FILE__))) . '/deployment';
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $dest_file = $destination . '/weebunz-quiz-engine.zip';
        copy($package_file, $dest_file);
        
        echo "Package copied to: $dest_file\n";
        
        // Clean up
        unlink($package_file);
        rmdir($temp_dir);
        
        return $dest_file;
    } else {
        echo "Failed to create deployment package.\n";
        return false;
    }
}

// Create deployment package if this file is executed directly
if (isset($argv) && basename($argv[0]) === basename(__FILE__)) {
    create_deployment_package();
}
