<?php
/**
 * Map Service Manager
 *
 * Manages map-related services and functionality based on the active map provider.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Map;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Service_Manager {

    /**
     * The active map service provider
     *
     * @var string
     */
    private $active_provider;

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param WpslSettings $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;
        $this->active_provider = wpsl_get_active_map_service();
    }

    /**
     * Check if the current map provider supports GeoJSON
     *
     * @since  3.0.0
     * @return bool Whether GeoJSON is supported
     */
    public function supports_geojson() {
        return $this->active_provider === 'mapbox';
    }

    /**
     * Check if the current map provider uses Nominatim for geocoding
     *
     * @since  3.0.0
     * @return bool Whether Nominatim is used
     */
    public function uses_nominatim() {
        return in_array( $this->active_provider, ['mapbox', 'osm'] );
    }

    /**
     * Get the active map provider
     *
     * @since  3.0.0
     * @return string The active map provider
     */
    public function get_active_provider() {
        return $this->active_provider;
    }

    /**
     * Get provider-specific map options
     *
     * @since  3.0.0
     * @return array Map options for the active provider
     */
    public function get_map_options() {
        $options = [];
        
        switch ( $this->active_provider ) {
            case 'mapbox':
                $options['api_key'] = $this->settings->get( 'api', 'mapbox_key' );
                $options['map_type'] = $this->settings->get( 'appearance', 'map_style' );
                break;
            case 'osm':
                // OpenStreetMaps specific options
                break;
            case 'gmaps':
            default:
                $options['api_key'] = $this->settings->get( 'api', 'gmaps_browser_key' );
                $options['map_type'] = $this->settings->get( 'appearance', 'map_style' );
                break;
        }
        
        return apply_filters( 'wpsl_map_provider_options', $options, $this->active_provider );
    }
}