<?php
namespace Weebunz;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @since 1.0.0
 */
class Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @var array
     */
    protected $actions = [];

    /**
     * The array of filters registered with WordPress.
     *
     * @var array
     */
    protected $filters = [];

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress action.
     * @param object $component     Instance on which the callback exists.
     * @param string $callback      Method name to call.
     * @param int    $priority      Priority (default 10).
     * @param int    $accepted_args Number of args (default 1).
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress filter.
     * @param object $component     Instance on which the callback exists.
     * @param string $callback      Method name to call.
     * @param int    $priority      Priority (default 10).
     * @param int    $accepted_args Number of args (default 1).
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    /**
     * Register all filters and actions with WordPress.
     */
    public function run() {
        foreach ( $this->filters as $f ) {
            add_filter( $f['hook'], [ $f['component'], $f['callback'] ], $f['priority'], $f['accepted_args'] );
        }
        foreach ( $this->actions as $a ) {
            add_action( $a['hook'], [ $a['component'], $a['callback'] ], $a['priority'], $a['accepted_args'] );
        }
    }
}
