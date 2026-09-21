<?php
/**
 * Geocoding and routing API helpers.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get a specific geocode implementation regardless of the active map service.
 *
 * @since  3.0.0
 * @param  string $map_service The map service to get the geocode implementation for.
 * @return object The geocode implementation for the specified map service.
 */
function wpsl_get_geocode_implementation( $map_service = '' ) {
    if ( empty( $map_service ) ) {
        return wpsl_get_service( 'geocode_implementation' );
    }
    
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' );
    
    switch ( $map_service ) {
        case 'mapbox':
            return new \WPSL\Admin\API\Geocode_Mapbox( $wpsl_settings );
        case 'osm':
            return new \WPSL\Admin\API\Geocode_Osm( $wpsl_settings );
        case 'stadia':
            return new \WPSL\Admin\API\Geocode_Stadia( $wpsl_settings );
        case 'gmaps':
        default:
            $notices = wpsl_get_service( 'notices' );
            return new \WPSL\Admin\API\Geocode_Gmaps( $notices, $wpsl_settings );
    }
}

/**
 * Make Geocode API calls to Google Maps / Nominatim / Mapbox.
 *
 * @since  2.1.1
 * @note   Mapbox calls will default to the 'mapbox.places-permanent' ( paid ) endpoint,
 *         but this can be changed
 * @param  string $address     The address to geocode.
 * @param  string $map_service Optionally specify the Map provider to make the API call to.
 * @param  array  $args        Optional arguments.
 * @return array  $response    Either a WP_Error or the response from the Geocode API.
 */
function wpsl_call_geocode_api( $address, $map_service = 'gmaps', $args = [] ) {
    $response          = '';
    $allowed_providers = wpsl_get_map_services();
    $wpsl_settings     = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    if ( array_key_exists( $map_service, $allowed_providers ) ) {

        /**
         * If Mapbox is selected, but the server geocoder is set to Nominatim on the
         * WPSL settings page, then we set the map service to 'osm' to make sure the
         * API requests are made to Nominatim and not Mapbox.
         *
         * Except when we check if the Mapbox API key is valid,
         * then we have to stick with Mapbox.
         */
        if ( $map_service == 'mapbox' && $wpsl_settings['mapbox_geocoder'] == 'nominatim' && ! isset( $args['force_mapbox_geocoder'] ) ) {
            $map_service = 'osm';
            
            // Also update the force_map_service parameter if it's set to 'mapbox'
            if ( isset( $args['force_map_service'] ) && $args['force_map_service'] == 'mapbox' ) {
                $args['force_map_service'] = 'osm';
            }
        }

        // Get the appropriate geocode implementation
        if ( isset( $args['force_map_service'] ) && ! empty( $args['force_map_service'] ) ) {
            $geocode_implementation = wpsl_get_geocode_implementation( $args['force_map_service'] );
        } else {
            $geocode_implementation = wpsl_get_geocode_implementation();
        }

        // Call the API using the implementation
        $response = $geocode_implementation->call_api( $address, $args );
    }

    return $response;
}

/**
 * Get the latlng for the provided address.
 *
 * This is used to geocode the address set as the start point on
 * the settings page in case the autocomplete fails
 * ( only happens when there is a JS error on the page ),
 * or to get the latlng when the 'start_location' attr is set
 * on the wpsl shortcode.
 *
 * @since  2.2.0
 * @param  string     $address     The address to geocode.
 * @param  string     $map_service Optionally specify the Map provider to make the API call to.
 * @return array|void $latlng      The returned latlng or nothing if there was an error.
 */
function wpsl_get_address_latlng( $address, $map_service = 'gmaps' ) {
    $latlng   = '';
    $response = wpsl_call_geocode_api( $address, $map_service );

    if ( ! is_wp_error( $response ) && $response['response']['message'] == 'OK' ) {
        $response = json_decode( $response['body'], true );

        switch ( $map_service ) {
            case 'gmaps':
                if ( isset( $response['results'][0]['location']['latitude'] ) ) {
                    $latlng = $response['results'][0]['location']['latitude'] . ',' . $response['results'][0]['location']['longitude'];
                }

                break;
            case 'osm':
                if ( $response && count( $response ) && isset( $response[0]['lat'] ) ) {
                    $latlng = $response[0]['lat'] . ',' . $response[0]['lon'];
                }

                break;
            case 'mapbox':
                if ( isset( $response['features'] ) && count( $response['features'] ) ) {
                    $latlng = $response['features'][0]['geometry']['coordinates'][1] . ',' . $response['features'][0]['geometry']['coordinates'][0] ;
                }

                break;
            case 'stadia':
                if ( isset( $response['features'] ) && count( $response['features'] ) ) {
                    $latlng = $response['features'][0]['geometry']['coordinates'][1] . ',' . $response['features'][0]['geometry']['coordinates'][0];
                }

                break;
        }
    }

    return $latlng;
}

/**
 * Check if there's a transient that holds
 * the coordinates for the passed address.
 *
 * If not, then we geocode the address and
 * set the returned value in the transient.
 *
 * @since  2.2.11
 * @param  string $address The location to geocode
 * @return string $latlng  The coordinates of the geocoded location
 */
function wpsl_check_latlng_transient( $address ) {
    $name_section   = explode( ',', $address );
    $transient_name = 'wpsl_' . sanitize_key( $name_section[0] ) . '_latlng';

    if ( false === ( $latlng = get_transient( $transient_name ) ) ) {
        $map_service = wpsl_get_active_map_service();
        $latlng      = wpsl_get_address_latlng( $address, $map_service );

        if ( $latlng ) {

            /**
             * Keep the latlng data for 30 days, as allowed by the terms from Google
             * https://cloud.google.com/maps-platform/terms/maps-service-terms.
             */
            if ( $map_service == 'gmaps' ) {
                $expires = 30 * DAY_IN_SECONDS;
            } else {
                $expires = 0;
            }

            set_transient( $transient_name, $latlng, $expires );
        }
    }

    return $latlng;
}

/**
 * Return the name of the active geocoding service.
 *
 * When Mapbox is used as the mapping service there are two different
 * sources we can use for the server side geocoding requests.
 *
 * - Nominatim (free)
 * - Mapbox (paid)
 *
 * We need to make sure we use the correct one.
 *
 * @since  3.0.0
 * @return string
 */
function wpsl_get_geocoding_service() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    $map_service = wpsl_get_active_map_service();

    if ( $map_service == 'mapbox' && $wpsl_settings['mapbox_geocoder'] == 'nominatim' ) {
        $map_service = 'osm';
    }

    return $map_service;
}

/**
 * Sanitize the coordinates
 *
 * @since  3.0.0
 * @param  string $coords The coordinates
 * @return string $coords The sanitized coordinates
 */
function wpsl_sanitize_coords( $coords ) {
    return filter_var( $coords, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND );
}

/**
 * Grab the directions data from the OpenRouteService.
 *
 * @since  3.0.0
 * @see    https://openrouteservice.org/dev/#/api-docs/v2/directions/{profile}/get
 * @param  array $args The arguments
 * @return array $response
 */
function wpsl_call_openrouteservice_api( $args ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();
    $travel_mode   = wpsl_get_directions_travel_mode( 'osm' );
    $path          = 'https://api.openrouteservice.org/v2/directions/' . $travel_mode . '';

    $language = $wpsl_settings['api']['openrouteservice_language'] ?: 'en';

    if ( isset( $args['api_key'] ) ) {
        $api_key = $args['api_key'];
    } else {
        $api_key = $wpsl_settings['api']['openrouteservice_key'];
    }

    $start = array_map( 'floatval', explode( ',', $args['start'] ) );
    $end   = array_map( 'floatval', explode( ',', $args['end'] ) );

    $params = wp_json_encode( [
        'coordinates' => [ $start, $end ],
        'language'    => $language,

        /**
         * Always request km — formatDirectionsDistance() in the JS assumes
         * the Openrouteservice values are km and converts them to the
         * configured display unit itself.
         */
        'units'       => 'km',
    ] );

    $response = wp_remote_post( $path, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => $api_key
            ],
            'body' => $params,
        ]
    );

    return $response;
}

/**
 * Get the Stadia Maps API base URL based on the EU endpoints setting.
 *
 * @since  3.0.0
 * @param  string $type The endpoint type: 'api' or 'tiles'
 * @return string The base URL
 */
function wpsl_stadia_api_base_url( $type = 'api' ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();
    $use_eu        = ! empty( $wpsl_settings['api']['stadia_eu_endpoints'] );

    if ( $type === 'tiles' ) {
        return $use_eu ? 'https://tiles-eu.stadiamaps.com' : 'https://tiles.stadiamaps.com';
    }

    return $use_eu ? 'https://api-eu.stadiamaps.com' : 'https://api.stadiamaps.com';
}

/**
 * Make a request to the Stadia Maps Routing API (Valhalla).
 *
 * @since  3.0.0
 * @see    https://docs.stadiamaps.com/routing/standard-routing/
 * @param  array $args The arguments (start, end coordinates)
 * @return array $response The API response
 */
function wpsl_call_stadia_routing_api( $args ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();
    $travel_mode   = wpsl_get_directions_travel_mode( 'stadia' );
    $api_key       = $wpsl_settings['api']['stadia_key'];

    // Stadia Maps uses Valhalla costing models
    $costing_map = [
        'driving'  => 'auto',
        'walking'  => 'pedestrian',
        'cycling'  => 'bicycle',
    ];

    $costing = isset( $costing_map[ $travel_mode ] ) ? $costing_map[ $travel_mode ] : 'auto';

    $start = array_map( 'floatval', explode( ',', $args['start'] ) );
    $end   = array_map( 'floatval', explode( ',', $args['end'] ) );

    // Valhalla expects [lon, lat] order for coordinates
    $params = wp_json_encode( [
        'locations'   => [
            [ 'lat' => $start[0], 'lon' => $start[1] ],
            [ 'lat' => $end[0],   'lon' => $end[1] ],
        ],
        'costing'     => $costing,

        /**
         * Always request km — formatDirectionsDistance() in the JS assumes
         * the Valhalla values are km and converts them to the configured
         * display unit itself.
         */
        'units'       => 'km',
    ] );

    $url = wpsl_stadia_api_base_url() . '/route/v1?api_key=' . $api_key;

    $response = wp_remote_post( $url, [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => $params,
    ] );

    return $response;
}

/**
 * Get the used travel direction mode.
 *
 * @since  2.2.8
 * @param  string $map_provider Either OSM or Gmaps
 * @return string $travel_mode  The used travel mode for the travel direcions
 */
function wpsl_get_directions_travel_mode( $map_provider = '' ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    if ( ! $map_provider ) {
        $map_provider = $wpsl_settings['active_map_service'];
    }

    $modes = [
        'gmaps' => [
            'default' => 'driving',
            'allowed' => [
                'driving',
                'bicycling',
                'transit',
                'walking'
            ],
        ],
        'osm' => [
            'default' => 'driving-car',
            'allowed' => [
                'driving-car',
                'driving-hgv',
                'cycling-regular',
                'cycling-road',
                'cycling-mountain',
                'cycling-electric',
                'foot-walking',
                'foot-hiking',
                'wheelchair'
            ],
        ],
        'mapbox' => [
            'default' => 'driving',
            'allowed' => [
                'driving-traffic',
                'walking',
                'cycling'
            ],
        ],
        'stadia' => [
            'default' => 'driving',
            'allowed' => [
                'driving',
                'walking',
                'cycling'
            ],
        ],
    ];

    $default     = $modes[$map_provider]['default'];
    $travel_mode = apply_filters( 'wpsl_direction_travel_mode', $default );

    if ( ! in_array( $travel_mode, $modes[$map_provider]['allowed'] ) ) {
        $travel_mode = $default;
    }

    return $travel_mode;
}