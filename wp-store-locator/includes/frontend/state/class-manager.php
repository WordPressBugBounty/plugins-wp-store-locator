<?php
/**
 * Frontend Page State Manager
 * 
 * We use this to track the number of active maps, the used shortcodes, 
 * the scripts we need to load and if we're using a static map.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Manager {

    /**
     * Global singleton instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private static $instance = null;

    /**
     * Map counter for multiple maps on a page
     *
     * @since 3.0.0
     * @var int
     */
    private $map_count = 0;

    /**
     * Store data for map rendering
     *
     * @since 3.0.0
     * @var array
     */
    private $store_map_data = [];

    /**
     * Scripts to load on the frontend
     *
     * @since 3.0.0
     * @var array
     */
    private $load_scripts = [];

    /**
     * JavaScript shortcode attributes for the current map instance
     *
     * @since 3.0.0
     * @var array
     */
    private $js_shortcode_atts = [];

    /**
     * Get singleton instance
     *
     * @since 3.0.0
     * @return Manager
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Prevent cloning
     *
     * @since 3.0.0
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     *
     * @since 3.0.0
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Get the current map count.
     *
     * @since  3.0.0
     * @return int The current map count
     */
    public function get_map_count() {
        return $this->map_count;
    }

    /**
     * Increment the map count and return the new value.
     *
     * @since  3.0.0
     * @return int The new map count after incrementing
     */
    public function increment_map_count() {
        return $this->map_count++;
    }

    /**
     * Set the map count to a specific value.
     *
     * @since  3.0.0
     * @param  int $count The value to set the map count to
     * @return void
     */
    public function set_map_count( $count ) {
        $this->map_count = absint( $count );
    }

    /**
     * Set store map data for a specific map count.
     *
     * @since  3.0.0
     * @param  int   $map_count The map count index
     * @param  array $data      The store data to set
     * @return void
     */
    public function set_store_map_data( $map_count, $data ) {
        $this->store_map_data[ $map_count ] = $data;
    }

    /**
     * Get store map data for a specific map count.
     *
     * @since  3.0.0
     * @param  int $map_count The map count index
     * @return array|null The store data or null if not found
     */
    public function get_store_map_data( $map_count ) {
        return isset( $this->store_map_data[ $map_count ] ) ? $this->store_map_data[ $map_count ] : null;
    }

    /**
     * Get all store map data.
     *
     * @since  3.0.0
     * @return array All store map data
     */
    public function get_all_store_map_data() {
        return $this->store_map_data;
    }

    /**
     * Add a script to the load_scripts array.
     *
     * @since  3.0.0
     * @param  string $script The script handle to add
     * @return void
     */
    public function add_script( $script ) {
        if ( ! in_array( $script, $this->load_scripts ) ) {
            $this->load_scripts[] = $script;
        }
    }

    /**
     * Get all scripts that need to be loaded.
     *
     * @since  3.0.0
     * @return array Array of script handles
     */
    public function get_load_scripts() {
        return $this->load_scripts;
    }

    /**
     * Set JavaScript shortcode attributes for the current map instance.
     *
     * @since  3.0.0
     * @param  array $atts The JavaScript shortcode attributes to set
     * @return void
     */
    public function set_js_shortcode_atts( $atts ) {
        $this->js_shortcode_atts = $atts;
    }

    /**
     * Get JavaScript shortcode attributes for the current map instance.
     *
     * @since 3.0.0
     * @return array The JavaScript shortcode attributes
     */
    public function get_js_shortcode_atts() {
        return $this->js_shortcode_atts;
    }

    /**
     * Get a specific JavaScript shortcode attribute value.
     *
     * @since  3.0.0
     * @param  string $key     The attribute key (supports dot notation for nested values)
     * @param  mixed  $default Default value if key doesn't exist
     * @return mixed  The attribute value or default
     */
    public function get_js_shortcode_att( $key, $default = null ) {
        $keys = explode( '.', $key );
        $value = $this->js_shortcode_atts;

        foreach ( $keys as $k ) {
            if ( ! is_array( $value ) || ! isset( $value[ $k ] ) ) {
                return $default;
            }

            $value = $value[ $k ];
        }

        return $value;
    }

    /**
     * Set a specific JavaScript shortcode attribute value.
     *
     * @since  3.0.0
     * @param  string $key   The attribute key (supports dot notation for nested values)
     * @param  mixed  $value The value to set
     * @return void
     */
    public function set_js_shortcode_att( $key, $value ) {
        $keys = explode( '.', $key );
        $current = &$this->js_shortcode_atts;

        foreach ( $keys as $k ) {
            if ( ! is_array( $current ) ) {
                $current = [];
            }

            if ( ! isset( $current[ $k ] ) ) {
                $current[ $k ] = [];
            }

            $current = &$current[ $k ];
        }

        $current = $value;
    }

    /**
     * Check if a JavaScript shortcode attribute exists.
     *
     * @since  3.0.0
     * @param  string $key The attribute key (supports dot notation for nested values)
     * @return bool True if the attribute exists, false otherwise
     */
    public function has_js_shortcode_att( $key ) {
        $keys = explode( '.', $key );
        $value = $this->js_shortcode_atts;

        foreach ( $keys as $k ) {
            if ( ! is_array( $value ) || ! isset( $value[ $k ] ) ) {
                return false;
            }
            $value = $value[ $k ];
        }

        return true;
    }

    /**
     * Reset all state data (useful for testing or cleanup).
     *
     * @since  3.0.0
     * @return void
     */
    public function reset() {
        $this->map_count = 0;
        $this->store_map_data = [];
        $this->load_scripts = [];
        $this->js_shortcode_atts = [];
    }
}