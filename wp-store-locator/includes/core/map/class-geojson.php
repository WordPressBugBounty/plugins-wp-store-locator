<?php
/**
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Map;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GeoJSON {

    /**
     * Should the results be returned in GeoJSON format?
     *
     * For now, only the Mapbox JS code supports this.
     *
     * @return bool
     */
    public function is_supported() {
        $settings = wpsl_get_service( 'wpsl_settings' );
        $geojson = $settings->get( 'api', 'active_map_service' ) == 'mapbox' ? true : false;

        return apply_filters( 'wpsl_geojson', $geojson );
    }

    /**
     * Convert the search results to geoJSON.
     *
     * @since  3.0.0
     * @see    https://docs.mapbox.com/help/glossary/geojson/
     * @param  array $store_data
     * @return array $geojson
     */
    public function create( $store_data ) {
        $online     = [];
        $geojson = [
            'type'     => 'FeatureCollection',
            'features' => []
        ];

        foreach ( $store_data as $store ) {

            // Reset the properties for every store.
            $properties = [];

            /**
             * Online-only stores have no coordinates, so they can't be a map
             * feature. Keep them in a separate list so the frontend can still
             * render them in the results, instead of dropping them entirely.
             */
            if ( empty( $store['lat'] ) || empty( $store['lng'] ) ) {
                $online[] = $store;
                continue;
            }

            $feature = [
                'type' => 'Feature',
                'properties' => '',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [ $store['lng'], $store['lat'] ]
                ],
            ];

            unset( $store['lng'] );
            unset( $store['lat'] );

            $properties['icon'] = 'store';

            foreach ( $store as $k => $details ) {

                /**
                 * If a custom marker is provided, then set
                 * the 'icon' value in the geojson.
                 *
                 * This value is used as the unique ID for
                 * 'loadImage' and 'addLayer' in the Mapbox JS code.
                 */
                if ( $k == 'alternateMarkerUrl' && $details ) {
                    $properties['icon'] = basename( wp_parse_url( $details, PHP_URL_PATH ) );
                }

                $properties[$k] = $details;
            }

            $feature['properties'] = $properties;

            array_push( $geojson['features'], apply_filters( 'wpsl_geojson_feature', $feature ) );
        }

        if ( ! empty( $online ) ) {
            $geojson['online'] = $online;
        }

        return $geojson;
    }
}