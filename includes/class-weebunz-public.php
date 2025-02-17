<?php
// File: /wp-content/plugins/weebunz-core/includes/class-weebunz-public.php

namespace Weebunz;

if (!defined('ABSPATH')) {
    exit;
}

use Weebunz\Logger;

class WeeBunz_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'public/css/weebunz-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'public/js/weebunz-public.js',
            array('jquery'),
            $this->version,
            true
        );

        if (has_shortcode(get_post()->post_content ?? '', 'weebunz_quiz')) {
            wp_enqueue_script(
                'weebunz-quiz-player',
                plugin_dir_url(dirname(__FILE__)) . 'public/dist/quiz-player.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script('weebunz-quiz-player', 'weebunzSettings', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('weebunz_quiz'),
                'apiEndpoint' => rest_url('weebunz/v1')
            ));
        }
    }

    public function register_shortcodes() {
        add_shortcode('weebunz_quiz', array($this, 'quiz_shortcode'));
    }

    public function quiz_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => '',
            'theme' => 'default'
        ), $atts);

        ob_start();
        ?>
        <div id="weebunz-quiz-container" 
             class="weebunz-quiz-wrapper theme-<?php echo esc_attr($atts['theme']); ?>"
             data-quiz-type="<?php echo esc_attr($atts['type']); ?>">
        </div>
        <?php
        return ob_get_clean();
    }

    public function init() {
        try {
            $this->register_shortcodes();
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        } catch (\Exception $e) {
            Logger::error('Failed to initialize public class: ' . $e->getMessage());
        }
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}