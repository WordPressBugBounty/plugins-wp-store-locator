<?php
namespace WPSL\Core\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Map Service Provider
 *
 * Registers all map-related services with the container.
 * Conditionally registers services based on the active map provider.
 *
 * @since 3.0.0
 */
class Map_Service_Provider implements Service_Provider {

    /**
     * Register map services
     *
     * @param \WPSL\Core\Container $container
     * @return void
     */
    public function register( $container ) {
        $container->register_with_auto_resolution( 'map_service_manager', '\WPSL\Core\Map\Service_Manager', true );
        
        $active_map_service = wpsl_get_active_map_service();

        // Register GeoJSON service only if Mapbox is active
        if ( $active_map_service === 'mapbox' ) {
            $container->register_with_auto_resolution( 'geojson', '\WPSL\Core\Map\GeoJSON', true );
        }
        
        // Register Nominatim Geocode Cache only for OpenStreetMaps
        if ( $active_map_service === 'osm' ) {
            $container->register_with_auto_resolution( 'nominatim_cache', '\WPSL\Core\Map\Nominatim_Geocode_Cache', true );
        }
    }
}