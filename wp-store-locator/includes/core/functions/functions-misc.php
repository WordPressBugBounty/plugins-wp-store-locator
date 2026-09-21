<?php
/**
 * Service locator and other shared helpers.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get a service from the WPSL container
 *
 * @since  3.0.0
 * @param  string $service_id The service identifier
 * @return mixed The service instance
 */
function wpsl_get_service( $service_id ) {
    return wpsl_container()->get( $service_id );
}

/**
 * Drop the cached autoload store search results.
 *
 * Results are cached for a day with everything baked in: by the time a store
 * reaches the transient its categoryMarkerUrl / locationMarkerUrl are complete
 * SVG data URIs. So any change underneath (marker artwork, a category, the
 * settings the rows were built from) must flush it, or the map keeps drawing
 * yesterday through a hard reload and a private window alike.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_flush_store_cache() {
    $system_utils = wpsl_get_service( 'system_utils' );

    if ( $system_utils ) {
        $system_utils->flush_autoload_transients();
    }
}

/**
 * Get the available cluster marker shapes.
 *
 * Each shape holds the label shown in the admin dropdown, the SVG template and
 * the size in pixels the SVG is scaled to on the map. The templates support the
 * ${color}, ${labelColor}, ${labelSize} and ${count} placeholders, replaced
 * with the actual values in JS when the cluster marker is rendered.
 *
 * Extra shapes added through the wpsl_cluster_marker_shapes filter show up in
 * the admin dropdown automatically. Google Maps only: the other map services
 * use their own cluster styling.
 *
 * @since  3.0.0
 * @return array $shapes The cluster marker shapes
 */
function wpsl_get_cluster_marker_shapes() {
    $shapes = [
        'square' => [
            'label' => __( 'Square', 'wp-store-locator' ),
            'svg'   => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><rect x="25" y="25" width="190" height="190" rx="34" opacity=".25" /><rect x="45" y="45" width="150" height="150" rx="26" opacity=".85" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
            'size'  => 55,
        ],
        'hexagon' => [
            'label' => __( 'Hexagon', 'wp-store-locator' ),
            'svg'   => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><polygon points="120,10 25,65 25,175 120,230 215,175 215,65" opacity=".25" /><polygon points="120,30 42,75 42,165 120,210 198,165 198,75" opacity=".85" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
            'size'  => 55,
        ],
        'diamond' => [
            'label' => __( 'Diamond', 'wp-store-locator' ),
            'svg'   => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><polygon points="120,10 230,120 120,230 10,120" opacity=".25" /><polygon points="120,35 205,120 120,205 35,120" opacity=".85" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
            'size'  => 60,
        ],
        'donut' => [
            'label' => __( 'Donut', 'wp-store-locator' ),
            'svg'   => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" r="65" opacity=".9" /><circle cx="120" cy="120" r="90" fill="none" stroke="${color}" stroke-width="14" opacity=".45" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
            'size'  => 55,
        ],
        'pulse' => [
            'label' => __( 'Pulse', 'wp-store-locator' ),
            'svg'   => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" opacity=".15" r="118" /><circle cx="120" cy="120" opacity=".3" r="95" /><circle cx="120" cy="120" opacity=".85" r="65" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
            'size'  => 65,
        ],
    ];

    return apply_filters( 'wpsl_cluster_marker_shapes', $shapes );
}

/**
 * Check if the provided cluster marker template contains valid SVG code.
 *
 * Validate SVG templates supplied through the wpsl_cluster_marker_shapes /
 * wpsl_cluster_marker_templates filters before they reach the map; broken SVG
 * falls back to the default cluster style. The ${} placeholders are valid XML
 * attribute/text content, so they don't affect the check.
 *
 * @since  3.0.0
 * @param  mixed $svg The SVG template code
 * @return bool Whether or not the template is valid SVG code
 */
function wpsl_is_valid_cluster_svg( $svg ) {
    if ( ! is_string( $svg ) || trim( $svg ) === '' ) {
        return false;
    }

    $use_errors = libxml_use_internal_errors( true );
    $xml        = simplexml_load_string( trim( $svg ) );

    libxml_clear_errors();
    libxml_use_internal_errors( $use_errors );

    return $xml !== false && strtolower( $xml->getName() ) === 'svg';
}

/**
 * Get the url to the admin-ajax.php
 *
 * @since  2.2.3
 * @return string $ajax_url URL to the admin-ajax.php possibly with the WPML lang param included.
 */
function wpsl_get_ajax_url() {
    $param = '';

    $i18n = wpsl_get_service( 'i18n' );

    if ( is_string( $i18n->active_plugin ) ) {
        $param = '?lang=' . $i18n->check_multilingual_code();
    }

    $ajax_url = admin_url( 'admin-ajax.php' . $param );

    return apply_filters( 'wpsl_ajax_url', $ajax_url );
}

/**
 * Change the sorting of the search results.
 *
 * @since  3.0.0
 * @param  array $results The location data
 * @param  array $args    The sorting arguments ( order, orderby, flags )
 * @return array $results The sorted search results
 */
function wpsl_sort_results( $results, $args ) {
    return wpsl_get_service( 'store_data' )->sort_search_results( $results, $args );
}

/**
 * Get the version number for external scripts used in the plugin.
 *
 * Centralizes version management for third-party scripts to ensure
 * consistency across different parts of the plugin.
 *
 * @since  3.0.0
 * @param  string $script The script identifier to get the version for
 * @return string The version number for the requested script
 */
function wpsl_get_script_version( $script ) {
    $versions = apply_filters( 'wpsl_script_versions', [
        'mapbox_gl_js'               => '3.21.0',
        'mapbox_gl_geocoder'         => '5.1.0',
        'gmaps'                      => 'quarterly',

        // Vendored under assets/dist/vendor/, see wpsl_get_library_asset().
        'leaflet'                    => '1.9.4',
        'maplibre_gl'                => '4.7.1',
        'maplibre_gl_leaflet'        => '0.0.22',

        // Vendored under assets/vendor/geoman/ and assets/vendor/terra-draw/,
        'geoman'                     => '2.20.0',
        'terra_draw'                 => '1.32.3',
        'terra_draw_gmaps_adapter'   => '1.6.1',
        'terra_draw_mapbox_adapter'  => '1.4.0',
        'terra_draw_leaflet_adapter' => '1.3.0',
    ] );
    
    return isset( $versions[$script] ) ? $versions[$script] : '';
}

/**
 * The URL, version and Subresource Integrity hash for a third-party library file.
 *
 * Leaflet, MapLibre GL, the MapLibre Leaflet bridge and the jQuery UI theme
 * ship minified in assets/dist/vendor/ and load from there, whatever
 * SCRIPT_DEBUG is set to, as the WordPress.org plugin guidelines require. The
 * first line of each file links to its unminified source.
 *
 * A site that would rather load one from elsewhere can swap it through the
 * wpsl_library_asset filter, which receives this array and the asset name and
 * may set its own 'url' and 'integrity'. The older single-URL filters
 * ( wpsl_leaflet_js, wpsl_maplibre_gl_css, ... ) still run first, so existing
 * code using them keeps working.
 *
 * @since  3.0.0
 * @param  string $asset leaflet_js, leaflet_css, maplibre_gl_js, maplibre_gl_css,
 *                       maplibre_gl_leaflet_js or jquery_ui_theme_css.
 * @return array{url: string, version: string, integrity: string}
 */
function wpsl_get_library_asset( $asset ) {
    $libraries = [
        'leaflet_js'             => [ 'wpsl_leaflet_js', 'leaflet/leaflet.min.js', 'leaflet' ],
        'leaflet_css'            => [ 'wpsl_leaflet_css', 'leaflet/leaflet.min.css', 'leaflet' ],
        'maplibre_gl_js'         => [ 'wpsl_maplibre_gl_js', 'maplibre-gl/maplibre-gl.min.js', 'maplibre_gl' ],
        'maplibre_gl_css'        => [ 'wpsl_maplibre_gl_css', 'maplibre-gl/maplibre-gl.min.css', 'maplibre_gl' ],
        'maplibre_gl_leaflet_js' => [ 'wpsl_maplibre_gl_leaflet_js', 'maplibre-gl-leaflet/leaflet-maplibre-gl.min.js', 'maplibre_gl_leaflet' ],
        'jquery_ui_theme_css'    => [ 'wpsl_jquery_ui_theme_css', 'jquery-ui/smoothness/jquery-ui.min.css', '' ],
    ];

    if ( ! isset( $libraries[ $asset ] ) ) {
        return [ 'url' => '', 'version' => '', 'integrity' => '' ];
    }

    list( $legacy_filter, $file, $version_key ) = $libraries[ $asset ];

    $library = [
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- every name in $libraries above is wpsl_ prefixed.
        'url'       => apply_filters( $legacy_filter, WPSL_URL . 'assets/dist/vendor/' . $file ),
        'version'   => $version_key ? wpsl_get_script_version( $version_key ) : '1.13.2',
        'integrity' => '',
    ];

    $library = apply_filters( 'wpsl_library_asset', $library, $asset );

    return wp_parse_args( (array) $library, [ 'url' => '', 'version' => '', 'integrity' => '' ] );
}

/**
 * Register a third-party library file, with its SRI hash when it has one.
 *
 * A key ending in _css registers a style, anything else a script ( in the footer ).
 *
 * @since  3.0.0
 * @param  string $handle The script or style handle.
 * @param  string $asset  The library key, see wpsl_get_library_asset().
 * @param  array  $deps   The handles it depends on.
 * @return void
 */
function wpsl_register_library( $handle, $asset, $deps = [] ) {
    static $hashes = [ 'script' => [], 'style' => [] ];
    static $hooked = false;

    $library = wpsl_get_library_asset( $asset );
    $type    = ( '_css' === substr( $asset, -4 ) ) ? 'style' : 'script';

    if ( 'style' === $type ) {
        wp_register_style( $handle, $library['url'], $deps, $library['version'] );
    } else {
        wp_register_script( $handle, $library['url'], $deps, $library['version'], true );
    }

    if ( ! $library['integrity'] ) {
        return;
    }

    $hashes[ $type ][ $handle ] = $library['integrity'];

    if ( $hooked ) {
        return;
    }

    $hooked = true;

    add_filter( 'script_loader_tag', function( $tag, $tag_handle ) use ( &$hashes ) {
        if ( isset( $hashes['script'][ $tag_handle ] ) && strpos( $tag, 'integrity=' ) === false ) {
            $tag = str_replace( '<script ', '<script integrity="' . esc_attr( $hashes['script'][ $tag_handle ] ) . '" crossorigin="anonymous" ', $tag );
        }

        return $tag;
    }, 10, 2 );

    add_filter( 'style_loader_tag', function( $html, $tag_handle ) use ( &$hashes ) {
        if ( isset( $hashes['style'][ $tag_handle ] ) && strpos( $html, 'integrity=' ) === false ) {
            $html = str_replace( "rel='stylesheet'", "rel='stylesheet' integrity='" . esc_attr( $hashes['style'][ $tag_handle ] ) . "' crossorigin='anonymous'", $html );
        }

        return $html;
    }, 10, 2 );
}

/**
 * Register and enqueue a third-party library file.
 *
 * @since  3.0.0
 * @param  string $handle The script or style handle.
 * @param  string $asset  The library key, see wpsl_get_library_asset().
 * @param  array  $deps   The handles it depends on.
 * @return void
 */
function wpsl_enqueue_library( $handle, $asset, $deps = [] ) {
    wpsl_register_library( $handle, $asset, $deps );

    if ( '_css' === substr( $asset, -4 ) ) {
        wp_enqueue_style( $handle );
    } else {
        wp_enqueue_script( $handle );
    }
}

/**
 * The library URLs in the form caching plugins match their exclude lists against.
 *
 * A local file becomes its path ( /wp-content/plugins/... ), a remote one
 * keeps its host ( cdn.example.com/leaflet.js ), so the pattern matches
 * whichever source is actually in use.
 *
 * @since  3.0.0
 * @param  array $assets Library keys, see wpsl_get_library_asset().
 * @return array
 */
function wpsl_get_library_exclude_paths( $assets ) {
    $paths = [];

    foreach ( $assets as $asset ) {
        $url   = wpsl_get_library_asset( $asset )['url'];
        $parts = wp_parse_url( $url );
        $path  = isset( $parts['path'] ) ? $parts['path'] : '';

        $paths[] = ( strpos( $url, WPSL_URL ) !== 0 && isset( $parts['host'] ) ) ? $parts['host'] . $path : $path;
    }

    return $paths;
}

/**
 * Return the major version number
 * that the user updated from.
 *
 * Used to keep some things
 * backward compatible.
 *
 * @since   3.0.0
 * @return  mixed
 */
function wpsl_upgraded_from() {
    $updated_from = get_option( 'wpsl_updated_from', '' );

    return $updated_from ? strtok( $updated_from, '.' ) : '';
}

/**
 * Create a WPSL-styled admin page for add-ons.
 * 
 * This function allows add-ons to create admin pages with the same
 * header and navigation styling as the main WPSL plugin.
 * 
 * Note: This function only works with WPSL 3.0+. Add-ons should check
 * if this function exists before using it to maintain backward compatibility.
 *
 * @since 3.0.0
 * @param array $args {
 *     Configuration for the admin page.
 *     
 *     @type string   $page_slug          The page slug (e.g., 'wpsl_statistics')
 *     @type array    $tabs               Array of tabs with key => label pairs
 *     @type callable $content_callback   Callback function to render content for each tab
 *     @type string   $default_tab        Default tab to show (optional)
 *     @type array    $short_tab_names    Short names for responsive display (optional)
 *     @type callable $nav_extras_callback Callback to render extra HTML inside the nav wrap. Receives $current_tab as argument. (optional)
 *     @type string   $layout              Layout mode: 'wide' (full width) or 'default' (narrow, matching main WPSL settings width). Default 'wide'.
 *     @type array    $tab_classes          Optional CSS classes for individual tabs. Array of tab_key => class string pairs (e.g., 'dashboard' => 'icon-dashboard').
 * }
 * @return void
 */
function wpsl_create_admin_page( $args ) {
    if ( class_exists( '\WPSL\Admin\Core\Display_Page' ) ) {
        $display_page = new \WPSL\Admin\Core\Display_Page();
        $display_page->create_addon_page( $args );
    }
}
/**
 * Write a diagnostic message to the PHP error log, but only when WP_DEBUG is on.
 *
 * Keeps developer tracing out of production logs, where it would otherwise spam
 * the log on every request or operation. Genuine error conditions that should
 * always be recorded ( migration failures, DB errors ) use error_log() directly.
 *
 * @since  3.0.0
 * @param  string $message The message to log.
 * @return void
 */
function wpsl_debug_log( $message ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Gated on WP_DEBUG; diagnostic tracing only.
        error_log( $message );
    }
}

/**
 * Validate a timezone value coming from the store editor.
 *
 * Accepts the values offered by wp_timezone_choice(): named IANA
 * identifiers, or the "Manual Offsets" group ( e.g. UTC+9.5 ). Anything
 * else returns an empty string.
 *
 * @since  3.0.0
 * @param  string $timezone The raw timezone value.
 * @return string The validated value, or '' when invalid.
 */
function wpsl_sanitize_timezone( $timezone ) {
    $timezone = trim( (string) $timezone );

    if ( '' === $timezone ) {
        return '';
    }

    if ( in_array( $timezone, timezone_identifiers_list(), true ) ) {
        return $timezone;
    }

    // Manual offsets from wp_timezone_choice(), e.g. UTC+9.5 or UTC-12.
    if ( preg_match( '/^UTC[+-]\d{1,2}(\.\d{1,2})?$/', $timezone ) ) {
        return $timezone;
    }

    return '';
}

/**
 * Resolve the timezone the open / closed status should be calculated in
 * for a single store.
 *
 * Reads the wpsl_timezone post meta. When it's missing or invalid the
 * site-wide WordPress timezone is used, so stores without an explicit
 * timezone behave exactly as before.
 *
 * @since  3.0.0
 * @param  int $store_id The store post ID.
 * @return \DateTimeZone The timezone for the store.
 */
function wpsl_store_timezone( $store_id = 0 ) {
    if ( ! $store_id ) {
        return wp_timezone();
    }

    $meta = wpsl_sanitize_timezone( get_post_meta( $store_id, 'wpsl_timezone', true ) );

    if ( '' === $meta ) {
        return wp_timezone();
    }

    if ( in_array( $meta, timezone_identifiers_list(), true ) ) {
        return new DateTimeZone( $meta );
    }

    // Manual offset like UTC+9.5 -> +09:30 ( a valid DateTimeZone offset ).
    $offset  = (float) substr( $meta, 3 );
    $sign    = $offset < 0 ? '-' : '+';
    $hours   = abs( (int) $offset );
    $minutes = abs( ( $offset - (int) $offset ) * 60 );

    return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, $hours, $minutes ) );
}

/**
 * Whether a store is open at a given moment.
 *
 * Takes the location status ( permanently / temporarily closed, reopen date ),
 * dated special hours and the weekly hours into account, including a period
 * that runs past midnight. Nothing is written. See
 * \WPSL\Core\Hours\Service::status_at() for the returned array.
 *
 * Example: wpsl_get_store_status_at( 42, '2026-09-19 13:30' )['open']
 *
 * @since  3.0.0
 * @param  int                            $store_id The store post ID.
 * @param  \DateTimeInterface|string|null $when     The moment to check. A string is read in the
 *                                                  store's timezone. Null means now.
 * @return array|\WP_Error
 */
function wpsl_get_store_status_at( $store_id, $when = null ) {
    return wpsl_get_service( 'hours' )->status_at( $store_id, $when );
}

/**
 * Whether this request is an ordinary front-end page view.
 *
 * True only for a plain GET on the front end: not wp-admin, not admin-ajax,
 * not the REST API and not WP-CLI. Services whose hooks can only matter when
 * something is written or an admin screen is drawn use this to stay unloaded
 * on the requests that make up nearly all of a site's traffic.
 *
 * @since  3.0.0
 * @return bool
 */
function wpsl_is_frontend_page_view() {

    if ( is_admin() || wp_doing_ajax() ) {
        return false;
    }

    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return false;
    }

    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

    return 'GET' === $method;
}

/**
 * Is this a request that can rebuild the plugin update transient outside wp-admin?
 *
 * WordPress refreshes update_plugins from the wp_update_plugins cron event and
 * from WP-CLI, where is_admin() is false and admin_init never fires. Anything the
 * add-on updaters depend on has to load in those contexts too — a transient
 * rebuilt without them drops every add-on out of the update list, which also
 * means background auto-updates can never run.
 *
 * @since  3.0.0
 * @return bool
 */
function wpsl_doing_background_update_check() {
    if ( wp_doing_cron() ) {
        return true;
    }

    return defined( 'WP_CLI' ) && WP_CLI;
}