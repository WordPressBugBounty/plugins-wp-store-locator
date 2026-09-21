<?php
/**
 * Handle the map settings.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Maps;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Settings {

    /**
     * Return API request restrictions.
     *
     * @since   3.0.0
     * @return  array $api_settings
     */
    public function get_api_settings() {
        $wpsl_settings = $this->settings->get_group( 'api' );
        $map_services = wpsl_get_map_services();
        $api_settings = [
            'provider' => wpsl_get_active_map_service()
        ];

        if ( $api_settings['provider'] !== 'gmaps' ) {
            $api_settings['language'] = $wpsl_settings[ $api_settings['provider'] . '_language' ];
        }

        if ( $api_settings['provider'] === 'mapbox' ) {
            $api_settings['key'] = $wpsl_settings['mapbox_key'];
        }

        if ( $api_settings['provider'] === 'osm' ) {
            $api_settings['tileLayer'] = wpsl_get_osm_tile_layer();
        }

        // Stadia uses Leaflet (like OSM) with Stadia tile layers
        if ( $api_settings['provider'] === 'stadia' ) {
            $api_settings['tileLayer'] = wpsl_get_stadia_tile_layer();
            $api_settings['key']       = $wpsl_settings['stadia_key'];
        }

        if ( $api_settings['provider'] === 'gmaps' ) {
            $api_settings['versions'] = $wpsl_settings['versions'][$api_settings['provider']];
        }

        if ( wpsl_has_multi_region_restrictions() && $wpsl_settings['multiple_regions'] ) {
            $api_settings['regions'] = implode( ',', $wpsl_settings['multiple_regions'] );
        }

        // API restrictions for geocode / autocomplete requests.
        foreach ( $map_services as $available_service => $name ) {
            $restrictions = wpsl_api_response_restrictions( $available_service );

            if ( $api_settings['provider'] == $available_service && $restrictions ) {
                $api_settings['filters'] = implode( ',', $restrictions );
            }
        }

        return apply_filters( 'wpsl_api_settings', $api_settings );
    }

    /**
     * Get the infobox settings.
     *
     * @since 2.0.0
     * @deprecated 3.0.0 The loaded JS file is no longer maintained, and hasn't been updated for 6+ years.
     * @see https://github.com/googlemaps/v3-utility-library/tree/master/archive/infobox
     * @param  array $settings The plugin settings used on the front-end in js
     * @return void
     */
    public function get_infobox_settings( $settings ) {
    }
}