<?php
/**
 * Map services, types, tile layers and Google Maps bootstrap.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Collect the query parameters ( languageCode, regionCode, key ) for a request to
 * the Google Geocoding API v4.
 *
 * @since  1.0.0
 * @see    https://developers.google.com/maps/documentation/geocoding/geocoding-v4-overview
 * @param  array  $args Configuration options for the API parameters
 * @return string $api_params The API parameters string with a leading ampersand
 */
function wpsl_get_gmap_api_params( $args ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();
    $i18n          = wpsl_get_service( 'i18n' );
    $api           = isset( $wpsl_settings['api'] ) ? $wpsl_settings['api'] : [];

    $params = [];

    if ( is_string( $i18n->active_plugin ) ) {
        $language = $i18n->check_multilingual_code();
    } else {
        $language = isset( $api['gmaps_language'] ) ? $api['gmaps_language'] : '';
    }

    if ( ! empty( $language ) ) {
        $params['languageCode'] = $language;
    }

    /**
     * Region bias. The v4 free-text geocode endpoint biases (not restricts)
     * with 'regionCode' and has no equivalent of the classic 'components'
     * country filter, so the configured country is a soft bias only. This
     * avoids geocoding failures on store addresses outside the selected
     * country. Skipped while an API key is being validated.
     */
    if ( get_option( 'wpsl_key_validation_in_progess' ) ) {
        delete_option( 'wpsl_key_validation_in_progess' );
    } else {
        $region_code = '';

        if ( isset( $api['region_restriction_type'] ) && $api['region_restriction_type'] === 'restrict' ) {
            // Only a single restricted country can be used as a region bias.
            $countries = ( isset( $api['multiple_regions'] ) && is_array( $api['multiple_regions'] ) ) ? array_values( $api['multiple_regions'] ) : [];

            if ( count( $countries ) === 1 ) {
                $region_code = $countries[0];
            }
        } else if ( ! empty( $api['gmaps_region'] ) ) {
            $region_code = $api['gmaps_region'];
        }

        if ( ! empty( $region_code ) ) {
            $params['regionCode'] = $region_code;
        }
    }

    /**
     * API key. A key passed in $args takes priority ( used when validating a new key ).
     */
    if ( isset( $args['api_key'] ) && $args['api_key'] ) {
        $key = sanitize_text_field( $args['api_key'] );
    } else {
        $key = isset( $api['gmaps_server_key'] ) ? $api['gmaps_server_key'] : '';
    }

    if ( ! empty( $key ) ) {
        $params['key'] = $key;
    }

    $api_params = '';

    foreach ( $params as $name => $value ) {
        $api_params .= $name . '=' . rawurlencode( $value ) . '&';
    }

    // Remove trailing ampersand if it exists
    $api_params = rtrim( $api_params, '&' );

    // Always return with a leading ampersand since this will be appended to a URL that already has parameters
    if ( ! empty( $api_params ) ) {
        $api_params = '&' . $api_params;
    }

    return apply_filters( 'wpsl_gmap_api_params', $api_params );
}

/**
 * Get the available map types.
 *
 * @since  2.0.0
 * @return array $map_types The available map types
 */
function wpsl_get_map_types() {
    $map_types = [
        'roadmap'   => __( 'Roadmap', 'wp-store-locator' ),
        'satellite' => __( 'Satellite', 'wp-store-locator' ),
        'hybrid'    => __( 'Hybrid', 'wp-store-locator' ),
        'terrain'   => __( 'Terrain', 'wp-store-locator' )
    ];

    return $map_types;
}

/**
 * Make sure the provided map type is valid.
 *
 * If the map type is invalid the default is used ( roadmap ).
 *
 * @since  2.0.0
 * @param  string $map_type The provided map type
 * @return string $map_type A valid map type
 */
if ( ! function_exists( 'wpsl_valid_map_type' ) ) {
    function wpsl_valid_map_type( $map_type ) {
        $allowed_map_types = wpsl_get_map_types();

        if ( ! array_key_exists( $map_type, $allowed_map_types ) ) {
            $map_type = wpsl_settings( 'map', 'type' );
        }

        return $map_type;
    }
}

/**
 * Make sure the provided zoom level is valid.
 *
 * If the zoom level is invalid the default is used ( 3 ).
 *
 * @since  2.0.0
 * @param  string $zoom_level The provided zoom level
 * @return string $zoom_level A valid zoom level
 */
if ( ! function_exists( 'wpsl_valid_zoom_level' ) ) {
    function wpsl_valid_zoom_level( $zoom_level ) {
        $zoom_level = absint( $zoom_level );

        if ( ( $zoom_level < 1 ) || ( $zoom_level > 21 ) ) {
            $zoom_level = wpsl_settings( 'map', 'zoom_level' );
        }

        return $zoom_level;
    }
}

/**
 * Get the max auto zoom levels for the map.
 *
 * @since  2.0.0
 * @return array $max_zoom_levels The array holding the min - max zoom levels
 */
if ( ! function_exists( 'wpsl_get_max_zoom_levels' ) ) {
    function wpsl_get_max_zoom_levels() {
        $max_zoom_levels = [];
        $zoom_level = [
            'min' => 10,
            'max' => 21
        ];

        $i = $zoom_level['min'];

        while ( $i <= $zoom_level['max'] ) {
            $max_zoom_levels[$i] = $i;
            $i++;
        }

        return $max_zoom_levels;
    }
}

/**
 * Deregister other Google Maps scripts.
 *
 * If your theme or another plugin loads the Google Maps library separately from the WP Store Locator, 
 * it can interfere with the autocomplete function and API key validation on the settings page. 
 * 
 * This may also cause the store locator to malfunction on both the frontend and backend.
 *
 * @since  2.2.4
 */
function wpsl_deregister_other_gmaps() {
    global $wp_scripts;
    
    foreach ( $wp_scripts->registered as $index => $script ) {
        if ( ( strpos( $script->src, 'maps.google.com' ) !== false || strpos( $script->src, 'maps.googleapis.com' ) !== false ) && $script->handle !== 'wpsl-js' ) {
            wp_deregister_script( $script->handle );
        }
    }
}

/**
 * Get the list of available map services
 *
 * @since  3.0.0
 * @return array $map_services List of map services
 */
if ( ! function_exists( 'wpsl_get_map_services' ) ) {
    function wpsl_get_map_services() {
        $map_services = apply_filters( 'wpsl_map_services', [
            'osm'    => __( 'OpenStreetMap (Leaflet)', 'wp-store-locator' ),
            'stadia' => __( 'Stadia Maps', 'wp-store-locator' ),
            'mapbox' => __( 'Mapbox', 'wp-store-locator' ),
            'gmaps'  => __( 'Google Maps', 'wp-store-locator' )
        ]);

        return $map_services;
    }
}

/**
 * Get the validated active map service.
 *
 * @since  3.0.0
 * @return string $map_service The active map service, e.g. 'osm' or 'gmaps'
 */
if ( ! function_exists( 'wpsl_get_active_map_service' ) ) {
    function wpsl_get_active_map_service() {
        $map_service  = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'active_map_service' );
        $map_services = wpsl_get_map_services();

        if ( ! is_string( $map_service ) || ! isset( $map_services[ $map_service ] ) ) {
            $map_service = 'osm';
        }

        /**
         * Filter the active map service.
         *
         * @since 3.0.0
         * @param string $map_service The validated active map service
         */
        return apply_filters( 'wpsl_active_map_service', $map_service );
    }
}

/**
 * Check whether a GDPR consent handler can actually be used.
 *
 * 'none' and 'wpsl' are handled by this plugin, so they are always available.
 * The rest hand the decision to another plugin, and only work while that
 * plugin is active.
 *
 * @since   3.0.0
 * @param   string $handler The handler to check - none, wpsl, borlabs or complianz
 * @return  bool True when the handler is usable
 */
if ( ! function_exists( 'wpsl_is_gdpr_handler_available' ) ) {
    function wpsl_is_gdpr_handler_available( $handler ) {
        switch ( $handler ) {
            case 'complianz':
                return class_exists( 'COMPLIANZ' );
            case 'borlabs':
                return function_exists( 'BorlabsCookieHelper' );
        }

        return true;
    }
}

/**
 * Get the GDPR consent handler that is actually usable.
 *
 * The handlers that defer to another plugin are only usable while that plugin
 * is there to answer. Deactivate it and the setting stays behind, so the
 * frontend keeps waiting for a consent signal that can never arrive: the map
 * is never built, the search bar stays hidden behind the checkpoint class,
 * and neither the console nor the log says why. Fall back to loading the map
 * normally, which is the one option that cannot leave a dead locator.
 *
 * @since   3.0.0
 * @return  string The handler in effect - none, wpsl, borlabs or complianz
 */
if ( ! function_exists( 'wpsl_get_gdpr_handler' ) ) {
    function wpsl_get_gdpr_handler() {
        $handler = wpsl_get_service( 'wpsl_settings' )->get( 'gdpr', 'handler' );

        if ( ! wpsl_is_gdpr_handler_available( $handler ) ) {
            $handler = 'none';
        }

        /**
         * Filter the active GDPR handler.
         *
         * @since 3.0.0
         * @param string $handler The validated handler
         */
        return apply_filters( 'wpsl_gdpr_handler', $handler );
    }
}

/**
 * Google Maps Bootstrap loader.
 *
 * @see     https://developers.google.com/maps/documentation/javascript/load-maps-js-api
 *          https://developers.google.com/maps/documentation/javascript/load-maps-js-api#optional_parameters
 * @since   3.0.0
 * @param   string $args Pass 'params_only' to return just the API params ( GDPR loading ). Default ''.
 * @return  string The Google Maps parameters
 */
function wpsl_gmaps_bootstrap( $args = '' ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );
    $i18n = wpsl_get_service( 'i18n' );

    $api_params = '';
    $param_keys = [ 'language', 'region', 'browser_key' ];

    foreach ( $param_keys as $param_key ) {

        /**
         * If a multilingual plugin is active, then we grab
         * the active language code. Otherwise get the language
         * value from the settings var.
         */
        if ( $param_key == 'language' && ( is_string( $i18n->active_plugin ) ) ) {
            $param_val = $i18n->check_multilingual_code();
        } else if ( $param_key == 'region' && isset( $wpsl_settings['region_restriction_type'] ) && $wpsl_settings['region_restriction_type'] === 'restrict' ) {
            // In hard-restrict mode we don't bias the whole API towards a single region.
            $param_val = '';
        } else {
            $param_val = $wpsl_settings[ 'gmaps_' . $param_key ];
        }

        if ( ! empty( $param_val ) ) {
            if ( $param_key == 'browser_key' ) {
                $param_key = 'key';
            }

            $api_params .= "\t" . $param_key . ': ' . "'" . esc_js( $param_val )  . "'" . ',' . "\n";
        }
    }

    $api_params = $api_params . "\t" . "v: 'quarterly'". "\n";

    $script = '(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?(window.wpslGmapsConflict=1,console.warn(p+" only loads once. Ignoring:",g)):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({' . "\n";
    $script .= apply_filters( 'wpsl_gmaps_bootloader_params', $api_params );
    $script .= '});';

    // 'params_only' use to load Google Maps with GDPR
    if ( isset( $args ) && $args == 'params_only' ) {
        $response = $api_params;
    } else {
        $response = $script;
    }

    /**
     * Google's authReferrerPolicy: 'origin' reduces referrer data sent when
     * authorizing Maps requests, but only works if the API key restriction in
     * Cloud Console is domain-only (no path). A path-restricted key would
     * break, so it's not set by default - add it via the
     * wpsl_gmaps_bootloader_params filter above if your key is origin-only.
     *
     * @see https://developers.google.com/maps/documentation/javascript/load-maps-js-api#optional_parameters
     */

    return $response;
}

/**
 * Publish any callbacks registered via the wpsl_gmaps_callbacks filter
 * as a window variable for the JS GDPR flow to consume after consent.
 *
 * Hooked to wp_footer at priority 25 when the GDPR handler is 'wpsl'.
 * The JS in wpsl-gdpr.js reads window.wpslGmapsCallbacks after Maps loads.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_gmaps_publish_callbacks() {
    $callbacks = apply_filters( 'wpsl_gmaps_callbacks', [] );

    if ( empty( $callbacks ) ) {
        return;
    }

    wp_print_inline_script_tag( 'window.wpslGmapsCallbacks = ' . wp_json_encode( array_values( $callbacks ) ) . ';' );
}

/**
 * Fire any callbacks registered via the wpsl_gmaps_callbacks filter.
 *
 * Hooked to wp_footer at priority 25, AFTER the bootstrap script and all
 * shortcodes have been processed, so add-ons can register their JS callback
 * function names by filtering 'wpsl_gmaps_callbacks'.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_gmaps_fire_callbacks() {
    $callbacks = apply_filters( 'wpsl_gmaps_callbacks', [] );

    if ( empty( $callbacks ) ) {
        return;
    }

    $calls = '';

    foreach ( $callbacks as $fn ) {
        $calls .= 'if (typeof ' . $fn . ' === "function") ' . $fn . '(); ';
    }

    wp_print_inline_script_tag( 'google.maps.importLibrary("places").then(() => { ' . trim( $calls ) . ' });', [ 'type' => 'module' ] );
}

/**
 * Collect the names of the required ( Google Maps )
 * libraries used in various JS scripts.
 *
 * @since  3.0.0
 * @see    https://developers.google.com/maps/documentation/javascript/libraries#javascript
 * @param  array $shortcode_type The detected shortcode types. Default [].
 * @return array $libraries
 */
function wpsl_gmaps_libraries( $shortcode_type = [] ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();

    // Always required
    $libraries = [
        'core', 'maps', 'geocoding', 'marker'
    ];

    // Used based on the selected settings
    $optional_setting_libraries = [
        'search' => [ 'autocomplete' => 'places' ],
        'ux'     => [ 'marker_streetview' => 'streetView' ]
    ];

    if ( in_array( 'store_locator', $shortcode_type ) ) {
        foreach ( $optional_setting_libraries as $group => $settings ) {
            foreach ( $settings as $setting => $library ) {
                if ( isset( $wpsl_settings[$group][$setting] ) && $wpsl_settings[$group][$setting] ) {
                    $libraries[] = $library;
                }
            }
        }
    }

    return apply_filters( 'wpsl_default_api_libraries', $libraries );
}

/**
 * List of classic Mapbox styles.
 *
 * @since   3.0.0
 * @return  array $styles The classic Mapbox styles.
 */
if ( ! function_exists( 'wpsl_mapbox_classic_styles' ) ) {
    function wpsl_mapbox_classic_styles() {
        $styles = [
            'standard'           => 'mapbox://styles/mapbox/standard',
            'standard-satellite' => 'mapbox://styles/mapbox/standard-satellite',
            'streets'            => 'mapbox://styles/mapbox/streets-v12',
            'outdoors'           => 'mapbox://styles/mapbox/outdoors-v12',
            'light'              => 'mapbox://styles/mapbox/light-v11',
            'dark'               => 'mapbox://styles/mapbox/dark-v11',
            'satellite'          => 'mapbox://styles/mapbox/satellite-v9',
            'satellite-streets'  => 'mapbox://styles/mapbox/satellite-streets-v12',
            'navigation-day'     => 'mapbox://styles/mapbox/navigation-day-v1',
            'navigation-night'   => 'mapbox://styles/mapbox/navigation-night-v1'
        ];

        return $styles;
    }
}

/**
 * List of Stadia Maps raster styles.
 *
 * @since   3.0.0
 * @return  array $styles The Stadia Maps styles.
 * @see     https://docs.stadiamaps.com/themes/
 */
function wpsl_stadia_styles() {
    $styles = [
        'alidade_bright'         => esc_html__( 'Alidade Bright (Beta)', 'wp-store-locator' ),
        'osm_bright'             => esc_html__( 'OSM Bright', 'wp-store-locator' ),
        'outdoors'               => esc_html__( 'Outdoors', 'wp-store-locator' ),
        'alidade_smooth'         => esc_html__( 'Alidade Smooth', 'wp-store-locator' ),
        'alidade_smooth_dark'    => esc_html__( 'Alidade Smooth Dark', 'wp-store-locator' ),
        'stamen_terrain'         => esc_html__( 'Stamen Terrain', 'wp-store-locator' ),
        'stamen_toner'           => esc_html__( 'Stamen Toner', 'wp-store-locator' ),
        'stamen_toner_lite'      => esc_html__( 'Stamen Toner Lite', 'wp-store-locator' ),
        'stamen_toner_dark'      => esc_html__( 'Stamen Toner Dark', 'wp-store-locator' ),
        'stamen_toner_blacklite' => esc_html__( 'Stamen Toner Blacklite', 'wp-store-locator' ),
    ];

    return $styles;
}

/**
 * OpenFreeMap vector styles (no API key required).
 *
 * @since  3.0.0
 * @return array $styles style_key => MapLibre style JSON URL
 */
function wpsl_openfreemap_styles() {
    $styles = [
        'liberty'  => 'https://tiles.openfreemap.org/styles/liberty',
        'bright'   => 'https://tiles.openfreemap.org/styles/bright',
        'positron' => 'https://tiles.openfreemap.org/styles/positron',
        'dark'     => 'https://tiles.openfreemap.org/styles/dark',
        'fiord'    => 'https://tiles.openfreemap.org/styles/fiord',
    ];

    return apply_filters( 'wpsl_openfreemap_styles', $styles );
}

/**
 * Get the active Mapbox style.
 *
 * @since  3.0.0
 * @return string $mapbox_style The URL to the active Mapbox style
 */
function wpsl_active_mapbox_style() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );
    $mapbox_style = '';
    
    // Check if mapbox settings exist before accessing them
    if ( isset( $wpsl_settings['map_style'] ) && isset( $wpsl_settings['map_style']['mapbox'] ) ) {
        $mapbox_style = $wpsl_settings['map_style']['mapbox'];
        
        if ( $mapbox_style['selected'] == 'custom' ) {
            $mapbox_style = isset( $mapbox_style['custom_url'] ) ? $mapbox_style['custom_url'] : '';
        } else {
            $mapbox_style = isset( $mapbox_style['url'] ) ? $mapbox_style['url'] : '';
        }
    }

    return $mapbox_style;
}

/**
 * Get the default OSM tile layer configuration.
 *
 * @since  3.0.0
 * @return array Default OSM tile layer configuration
 */
function wpsl_get_default_osm_tile_layer() {
    $tile_layer = [
        'type'        => 'raster',
        'urlTemplate' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'options' => [
            'attribution' => '&copy; OpenStreetMap',
        ],
    ];

    return $tile_layer;
}

/**
 * Build the tile layer configuration for a Stadia Maps raster style.
 *
 * @since  3.0.0
 * @param  string     $selected_style The Stadia style key, e.g. 'alidade_smooth'
 * @return array|null $tile_layer     The tile layer configuration, or null when
 *                                    the style is invalid or no valid Stadia key exists
 */
function wpsl_build_stadia_tile_layer( $selected_style ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();
    $stadia_styles = wpsl_stadia_styles();

    if ( ! isset( $stadia_styles[ $selected_style ] ) ) {
        return null;
    }

    if ( ! $wpsl_settings['api']['stadia_key'] || get_option( 'wpsl_valid_stadia_key' ) != '1' ) {
        return null;
    }

    return [
        'type'        => 'raster',
        'urlTemplate' => wpsl_stadia_api_base_url( 'tiles' ) . '/tiles/{style}/{z}/{x}/{y}{r}.png?api_key={api_key}',
        'options' => [
            'attribution' => '&copy; <a href="https://stadiamaps.com/" target="_blank">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
            'style'       => $selected_style,
            'api_key'     => $wpsl_settings['api']['stadia_key'],
            'maxZoom'     => 'stamen_watercolor' === $selected_style ? 16 : 20,
        ],
    ];
}

/**
 * Build the tile layer configuration for an OpenFreeMap vector style URL.
 *
 * @since  3.0.0
 * @param  string     $style_url  The MapLibre style JSON URL
 * @return array|null $tile_layer The tile layer configuration, or null for an empty URL
 */
function wpsl_build_openfreemap_tile_layer( $style_url ) {
    if ( ! $style_url ) {
        return null;
    }

    return [
        'type'    => 'vector',
        'style'   => $style_url,
        'options' => [
            'attribution' => '<a href="https://openfreemap.org">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'maxZoom'     => 20,
        ],
        'fallback' => wpsl_get_default_osm_tile_layer(),
    ];
}

/**
 * Build the raster tile layer configuration for a Mapbox style URL.
 *
 * @since  3.0.0
 * @param  string     $style_url  The style URL in the 'mapbox://styles/...' format
 * @return array|null $tile_layer The tile layer configuration, or null when the
 *                                URL is invalid or no valid Mapbox key exists
 */
function wpsl_build_mapbox_raster_tile_layer( $style_url ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();

    if ( ! $wpsl_settings['api']['mapbox_key'] || get_option( 'wpsl_valid_mapbox_key' ) != '1' ) {
        return null;
    }

    $map_id_parts = explode( 'mapbox://styles/', ( string ) $style_url );

    if ( ! isset( $map_id_parts[1] ) || empty( $map_id_parts[1] ) ) {
        return null;
    }

    return [
        'type'        => 'raster',
        'urlTemplate' => 'https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}',
        'options' => [
            'attribution' => ' Mapbox  OpenStreetMap',
            'tileSize'    => 512,
            'maxZoom'     => 18,
            'zoomOffset'  => -1,
            'id'          => $map_id_parts[1],
            'accessToken' => $wpsl_settings['api']['mapbox_key']
        ],
    ];
}

/**
 * Get the saved custom MapLibre style JSON URL.
 *
 * @since  3.0.0
 * @param  array  $map_style The appearance map_style group.
 * @return string $style_url The style URL, or an empty string.
 */
function wpsl_get_maplibre_style_url( $map_style ) {
    if ( isset( $map_style['maplibre']['enabled'] ) && ! $map_style['maplibre']['enabled'] ) {
        return '';
    }

    if ( ! empty( $map_style['maplibre']['custom_url'] ) ) {
        return $map_style['maplibre']['custom_url'];
    }

    if ( ! empty( $map_style['openfreemap']['custom_url'] ) ) {
        return $map_style['openfreemap']['custom_url'];
    }

    return '';
}

/**
 * Build the tile layer configuration for a custom MapLibre style JSON URL.
 *
 * A {api_key} placeholder in the URL is expanded from the validated Stadia
 * key, so a Stadia hosted vector style works without the key appearing in
 * the saved settings. When the placeholder cannot be filled the layer is
 * rejected ( null ) instead of shipping a URL that is guaranteed to 404.
 *
 * @since  3.0.0
 * @param  string     $style_url  The MapLibre style JSON URL
 * @param  array|null $fallback   The raster fallback config. Default the OSM raster layer.
 * @return array|null $tile_layer The tile layer configuration, or null when
 *                                the URL is empty or a placeholder can't be filled
 */
function wpsl_build_maplibre_tile_layer( $style_url, $fallback = null ) {
    if ( ! $style_url ) {
        return null;
    }

    if ( false !== strpos( $style_url, '{api_key}' ) ) {
        $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();

        if ( ! $wpsl_settings['api']['stadia_key'] || get_option( 'wpsl_valid_stadia_key' ) != '1' ) {
            return null;
        }

        $style_url = str_replace( '{api_key}', $wpsl_settings['api']['stadia_key'], $style_url );
    }

    return [
        'type'    => 'vector',
        'style'   => $style_url,
        'options' => [
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'maxZoom'     => 20,
        ],
        'fallback' => $fallback ? $fallback : wpsl_get_default_osm_tile_layer(),
    ];
}

/**
 * Get the tile layer for OpenStreetMap.
 *
 * @since  3.0.0
 * @return array $tile_layer
 */
function wpsl_get_osm_tile_layer() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();

    $map_style = $wpsl_settings['appearance']['map_style'];

    // Determine tile source: new tile_source takes precedence, legacy overwrite_styles maps to 'mapbox'.
    $tile_source = 'default';
    if ( isset( $map_style['osm']['tile_source'] ) && in_array( $map_style['osm']['tile_source'], [ 'default', 'mapbox', 'stadia', 'openfreemap', 'maplibre' ], true ) ) {
        $tile_source = $map_style['osm']['tile_source'];
    } elseif ( isset( $map_style['osm']['overwrite_styles'] ) && $map_style['osm']['overwrite_styles'] == 1 ) {
        $tile_source = 'mapbox';
    }

    $tile_layer = null;

    // Stadia Maps raster tiles.
    if ( 'stadia' === $tile_source ) {
        $selected_style = isset( $map_style['stadia']['selected_style'] ) ? $map_style['stadia']['selected_style'] : 'alidade_smooth';
        $tile_layer     = wpsl_build_stadia_tile_layer( $selected_style );
    }

    // Mapbox vector tiles via Leaflet
    if ( 'mapbox' === $tile_source && ! isset( $tile_layer ) ) {
        if ( isset( $map_style['mapbox']['selected'] ) && $map_style['mapbox']['selected'] ) {
            if ( $map_style['mapbox']['selected'] == 'custom' && $map_style['mapbox']['custom_url'] ) {
                $map_id = $map_style['mapbox']['custom_url'];
            } else {
                $map_id = $map_style['mapbox']['url'];
            }

            $tile_layer = wpsl_build_mapbox_raster_tile_layer( $map_id );
        }
    }

    // OpenFreeMap vector styles via MapLibre (no key required).
    if ( 'openfreemap' === $tile_source && ! isset( $tile_layer ) ) {
        $selected = isset( $map_style['openfreemap']['selected'] ) ? $map_style['openfreemap']['selected'] : 'liberty';

        if ( 'custom' === $selected ) {
            $style_url = isset( $map_style['openfreemap']['custom_url'] ) ? $map_style['openfreemap']['custom_url'] : '';
        } else {
            $presets   = wpsl_openfreemap_styles();
            $style_url = isset( $presets[ $selected ] ) ? $presets[ $selected ] : '';
        }

        $tile_layer = wpsl_build_openfreemap_tile_layer( $style_url );
    }

    // A custom MapLibre style JSON URL via MapLibre.
    if ( 'maplibre' === $tile_source && ! isset( $tile_layer ) ) {
        $tile_layer = wpsl_build_maplibre_tile_layer( wpsl_get_maplibre_style_url( $map_style ) );
    }

    if ( ! isset( $tile_layer ) ) {
        $tile_layer = wpsl_get_default_osm_tile_layer();
    }

    return apply_filters( 'wpsl_osm_tile_layer', $tile_layer );
}

/**
 * Get the tile layer configuration for Stadia Maps as the main map service.
 *
 * @since  3.0.0
 * @return array $tile_layer The tile layer configuration
 */
function wpsl_get_stadia_tile_layer() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_all();
    $map_style     = $wpsl_settings['appearance']['map_style'];

    // Get selected Stadia style from appearance settings
    $stadia_styles  = wpsl_stadia_styles();
    $selected_style = isset( $map_style['stadia']['selected_style'] ) ? $map_style['stadia']['selected_style'] : 'alidade_smooth';

    // Fallback to OSM settings if Stadia-specific style not set
    if ( ! isset( $stadia_styles[ $selected_style ] ) && isset( $map_style['osm']['selected_style'] ) ) {
        $selected_style = $map_style['osm']['selected_style'];
    }

    // Ensure valid style
    if ( ! isset( $stadia_styles[ $selected_style ] ) ) {
        $selected_style = 'alidade_smooth';
    }

    $tile_layer = wpsl_build_stadia_tile_layer( $selected_style );

    /**
     * A custom MapLibre style JSON URL instead of the preset raster tiles.
     * The preset raster layer doubles as the client-side fallback so a
     * failing style still renders the provider's own tiles.
     */
    if ( isset( $map_style['stadia']['style_source'] ) && 'maplibre' === $map_style['stadia']['style_source'] ) {
        $vector_layer = wpsl_build_maplibre_tile_layer(
            wpsl_get_maplibre_style_url( $map_style ),
            $tile_layer ? $tile_layer : wpsl_get_default_osm_tile_layer()
        );

        if ( $vector_layer ) {
            $tile_layer = $vector_layer;
        }
    }

    if ( ! $tile_layer ) {
        // Fallback to default OSM tiles if no valid Stadia key
        $tile_layer = wpsl_get_default_osm_tile_layer();
    }

    return apply_filters( 'wpsl_stadia_tile_layer', $tile_layer );
}

/**
 * Resolve the [wpsl_map] map_style attribute to a tile layer configuration
 * for the Leaflet based map services ( osm / stadia ).
 *
 * Accepts the style names shown on the appearance page. Both dashes and
 * underscores are accepted ( stamen-terrain / stamen_terrain ). The value
 * 'custom' resolves to the custom style URL from the appearance settings,
 * 'default' to the standard OpenStreetMap raster tiles.
 *
 * A style name is matched against OpenFreeMap first ( no API key required ),
 * then Stadia Maps and finally the Mapbox styles, where the latter two only
 * resolve when a validated API key for that provider exists.
 *
 * @since  3.0.0
 * @param  string     $style      The map_style attribute value
 * @return array|null $tile_layer The tile layer configuration, or null when
 *                                the style can't be resolved
 */
function wpsl_get_shortcode_tile_layer( $style ) {
    $style = strtolower( trim( $style ) );

    if ( 'default' === $style ) {
        return wpsl_get_default_osm_tile_layer();
    }

    $map_style = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );
    $map_style = isset( $map_style['map_style'] ) ? $map_style['map_style'] : [];

    // Use the custom style URL from the tile source selected on the appearance page.
    if ( 'custom' === $style ) {
        $tile_source = isset( $map_style['osm']['tile_source'] ) ? $map_style['osm']['tile_source'] : '';

        // The shared MapLibre style URL wins over the legacy per-provider ones.
        if ( 'mapbox' !== $tile_source && ! empty( $map_style['maplibre']['custom_url'] ) ) {
            return wpsl_build_maplibre_tile_layer( $map_style['maplibre']['custom_url'] );
        }

        if ( 'mapbox' !== $tile_source && ! empty( $map_style['openfreemap']['custom_url'] ) ) {
            return wpsl_build_openfreemap_tile_layer( $map_style['openfreemap']['custom_url'] );
        }

        if ( ! empty( $map_style['mapbox']['custom_url'] ) ) {
            return wpsl_build_mapbox_raster_tile_layer( $map_style['mapbox']['custom_url'] );
        }

        return null;
    }

    $underscored = str_replace( '-', '_', $style );
    $dashed      = str_replace( '_', '-', $style );

    $openfreemap_styles = wpsl_openfreemap_styles();

    if ( isset( $openfreemap_styles[ $underscored ] ) ) {
        return wpsl_build_openfreemap_tile_layer( $openfreemap_styles[ $underscored ] );
    }

    if ( array_key_exists( $underscored, wpsl_stadia_styles() ) ) {
        return wpsl_build_stadia_tile_layer( $underscored );
    }

    /**
     * The Mapbox 'standard' styles are vector only and not supported by the
     * Leaflet raster path, the appearance page hides them for osm / stadia too.
     */
    $mapbox_styles = wpsl_mapbox_classic_styles();

    if ( isset( $mapbox_styles[ $dashed ] ) && ! in_array( $dashed, [ 'standard', 'standard-satellite' ], true ) ) {
        return wpsl_build_mapbox_raster_tile_layer( $mapbox_styles[ $dashed ] );
    }

    return null;
}

/**
 * Resolve the [wpsl_map] map_style attribute to a Mapbox style URL when
 * Mapbox is the active map service.
 *
 * Accepts the style names shown on the appearance page ( streets, dark,
 * satellite-streets, ... ) and 'custom' for the custom style URL from the
 * appearance settings.
 *
 * @since  3.0.0
 * @param  string $style     The map_style attribute value
 * @return string $style_url The Mapbox style URL, or an empty string when
 *                           the style can't be resolved
 */
function wpsl_get_shortcode_mapbox_style( $style ) {
    $style = strtolower( trim( $style ) );

    if ( 'custom' === $style ) {
        $map_style = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );

        return ! empty( $map_style['map_style']['mapbox']['custom_url'] ) ? $map_style['map_style']['mapbox']['custom_url'] : '';
    }

    $mapbox_styles = wpsl_mapbox_classic_styles();
    $dashed        = str_replace( '_', '-', $style );

    return isset( $mapbox_styles[ $dashed ] ) ? $mapbox_styles[ $dashed ] : '';
}

/**
 * The map_style values a provider may be offered, with display labels.
 *
 * The single source of truth behind the map block's style dropdown: every
 * entry is a value the shortcode resolvers above actually resolve for that
 * provider, and nothing is offered that would hand the user a broken map.
 * 
 * Stadia and Mapbox tile sources are gated on their API keys, 'custom' on a
 * custom URL actually being saved ( in the same order the Leaflet resolver
 * reads them ), and the vector-only Mapbox 'standard' pair stays off the
 * Leaflet providers, which cannot draw it.
 *
 * @since  3.0.0
 * @param  string $provider The map provider ( gmaps / mapbox / osm / stadia ).
 * @return array  $options  Labels keyed by map_style value; [] for an unknown provider.
 */
function wpsl_map_style_options( $provider ) {
    $options  = [];
    $settings = wpsl_get_service( 'wpsl_settings' );

    $map_style = $settings->get_group( 'appearance' );
    $map_style = isset( $map_style['map_style'] ) ? $map_style['map_style'] : [];

    // A slug as a readable name: dashes and underscores both read as word breaks.
    $label_from_slug = function( $slug ) {
        return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
    };

    if ( 'gmaps' === $provider ) {
        // Google has no named styles; the one option clears the global JSON style.
        $options['default'] = __( 'Default Google style', 'wp-store-locator' );
    } elseif ( 'mapbox' === $provider ) {
        foreach ( array_keys( wpsl_mapbox_classic_styles() ) as $slug ) {
            $options[ $slug ] = $label_from_slug( $slug );
        }

        if ( ! empty( $map_style['mapbox']['custom_url'] ) ) {
            $options['custom'] = __( 'Custom style (from settings)', 'wp-store-locator' );
        }
    } elseif ( 'osm' === $provider || 'stadia' === $provider ) {
        $options['default'] = __( 'OpenStreetMap default tiles', 'wp-store-locator' );

        foreach ( array_keys( wpsl_openfreemap_styles() ) as $slug ) {
            /* translators: %s: the style name. */
            $options[ $slug ] = sprintf( __( 'OpenFreeMap: %s', 'wp-store-locator' ), $label_from_slug( $slug ) );
        }

        /*
         * A slug two sources share belongs to the FIRST source in resolver
         * order - wpsl_get_shortcode_tile_layer() tries OpenFreeMap, then
         * Stadia, then the Mapbox rasters, returning on the first hit. So
         * 'dark' is OpenFreeMap's and 'outdoors' is Stadia's however many
         * keys are saved. Later sources must not overwrite the label: their
         * same-named styles are unreachable through the shortcode, and a
         * "Mapbox: Dark" entry would promise a style the resolver never picks.
         */
        if ( $settings->get( 'api', 'stadia_key' ) ) {
            foreach ( wpsl_stadia_styles() as $slug => $name ) {
                if ( isset( $options[ $slug ] ) ) {
                    continue;
                }

                /* translators: %s: the style name. */
                $options[ $slug ] = sprintf( __( 'Stadia: %s', 'wp-store-locator' ), $name );
            }
        }

        if ( $settings->get( 'api', 'mapbox_key' ) ) {
            foreach ( array_keys( wpsl_mapbox_classic_styles() ) as $slug ) {
                // Vector only -- the Leaflet raster path cannot draw them, and
                // wpsl_get_shortcode_tile_layer() refuses them for the same reason.
                if ( isset( $options[ $slug ] ) || in_array( $slug, [ 'standard', 'standard-satellite' ], true ) ) {
                    continue;
                }

                /* translators: %s: the style name. */
                $options[ $slug ] = sprintf( __( 'Mapbox: %s', 'wp-store-locator' ), $label_from_slug( $slug ) );
            }
        }

        /*
         * Offered exactly when the resolver's 'custom' branch would resolve:
         * a MapLibre or OpenFreeMap ( legacy ) custom URL counts unless the
         * tile source is mapbox, a Mapbox custom URL counts always.
         */
        $tile_source   = isset( $map_style['osm']['tile_source'] ) ? $map_style['osm']['tile_source'] : '';
        $has_gl_custom = 'mapbox' !== $tile_source
            && ( ! empty( $map_style['maplibre']['custom_url'] ) || ! empty( $map_style['openfreemap']['custom_url'] ) );

        if ( $has_gl_custom || ! empty( $map_style['mapbox']['custom_url'] ) ) {
            $options['custom'] = __( 'Custom style (from settings)', 'wp-store-locator' );
        }
    }

    return apply_filters( 'wpsl_map_style_options', $options, $provider );
}

/**
 * Return the coordinates for the hq of the selected map provider.
 * 
 * Used for the map center when no start_latlng is set.
 *
 * Stadia draws OpenStreetMap data, so it shares the OSM coordinates, and
 * any other provider falls back to them too: the front end needs a start
 * point, a null breaks the map setup.
 *
 * @since  3.0.0
 * @param  string $map_provider The active map provider
 * @return array  The coordinates for the hq of the selected map provider
 */
function wpsl_get_map_hq_coordinates( $map_provider ) {
    $hq_coordinates = [
        'gmaps'  => [ 37.4232067, -122.0836052 ],
        'mapbox' => [ 38.9048605, -77.0365996 ],
        'osm'    => [ 52.2352976, 0.1539601 ],
    ];

    $hq_coordinates['stadia'] = $hq_coordinates['osm'];

    return isset( $hq_coordinates[ $map_provider ] ) ? $hq_coordinates[ $map_provider ] : $hq_coordinates['osm'];
}