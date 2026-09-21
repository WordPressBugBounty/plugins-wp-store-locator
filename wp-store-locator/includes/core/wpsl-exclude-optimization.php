<?php
/**
 * Try to automatically prevent JS files required by WPSL from
 * being combined/minified by caching/optimization plugins
 * that are known to trigger JS errors.
 * 
 * The follow plugins require special attention.
 * - SiteGround Optimizer
 * - Autoptimize
 * - LiteSpeed Cache
 * - WP Rocket
 *
 * @since 2.2.240
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get common settings used by the optimization exclusion functions
 *
 * @since  3.0.0
 * @return array Array containing API and map settings and cluster markers status
 */
function wpsl_get_optimization_settings() {
    return [
        'settings' => array_merge(
            wpsl_get_service( 'wpsl_settings' )->get_group( 'api' ),
            wpsl_get_service( 'wpsl_settings' )->get_group( 'map' ),
            wpsl_get_service( 'wpsl_settings' )->get_group( 'markers' )
        ),
        'cluster_markers_active' => wpsl_get_service( 'assets_resources' )->cluster_markers_active()
    ];
}

/**
 * JS scripts we need to exclude from being
 * optimized when SiteGround Optimizer is active.
 *
 * @since  2.2.240
 * @see    https://wordpress.org/plugins/sg-cachepress/
 * @param  array $exclude_list
 * @return array $exclude_list
 */
function wpsl_sgo_optimize_js_excludes( $exclude_list ) {
    $optimization_settings = wpsl_get_optimization_settings();

    $exclude_list[] = 'wpsl';
    $exclude_list[] = 'underscore';
    $exclude_list[] = 'jquery-ui-core';
    $exclude_list[] = 'jquery-ui-tabs';

    // Add map service specific exclusions (frontend-only scripts)
    switch ( $optimization_settings['settings']['active_map_service'] ) {
        case 'osm':
        case 'stadia': // Stadia renders through Leaflet and uses the same scripts.
            $exclude_list[] = 'wpsl-leaflet-encoded';
            $exclude_list[] = 'wpsl-leaflet'; // Exclude Leaflet core by script handle
            $exclude_list   = array_merge( $exclude_list, wpsl_get_library_exclude_paths( [ 'leaflet_js' ] ) ); // Also exclude by URL

            // Only enqueued for vector map styles, harmless to list otherwise.
            $exclude_list[] = 'wpsl-maplibre-gl';
            $exclude_list[] = 'wpsl-maplibre-gl-leaflet';

            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list[] = 'wpsl-leaflet-markercluster';
            }

            break;
        case 'gmaps':
            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list[] = 'wpsl-cluster';
                $exclude_list[] = 'wpsl-d3-color';
                $exclude_list[] = 'wpsl-d3-interpolate';
            }

            break;
        case 'mapbox':
            $exclude_list[] = 'wpsl-mapbox-gl';
            $exclude_list[] = 'wpsl-mapbox-geocoder';
            $exclude_list[] = 'mapbox-gl-geocoder'; // Kept for URL based matching.
            break;
    }

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'sgo_javascript_combine_exclude', 'wpsl_sgo_optimize_js_excludes' );
add_filter( 'sgo_js_minify_exclude', 'wpsl_sgo_optimize_js_excludes' );
add_filter( 'sgo_js_defer_exclude', 'wpsl_sgo_optimize_js_excludes' );

// Exclude external CDN scripts from being combined
add_filter( 'sgo_javascript_combine_excluded_external_paths', 'wpsl_sgo_exclude_external_scripts' );

function wpsl_sgo_exclude_external_scripts( $exclude_list ) {
    // Libraries only land here when wpsl_library_asset pointed them at another host.
    foreach ( wpsl_get_library_exclude_paths( [ 'leaflet_js', 'maplibre_gl_js', 'maplibre_gl_leaflet_js' ] ) as $path ) {
        if ( strpos( $path, '/' ) !== 0 ) {
            $exclude_list[] = $path;
        }
    }

    $exclude_list[] = 'api.mapbox.com';

    return $exclude_list;
}

/**
 * JS scripts we need to exclude from being
 * optimized when Autoptimize is active.
 *
 * @since  2.2.240
 * @see    https://wordpress.org/plugins/autoptimize/
 * @param  string $exclude_list
 * @return string $exclude_list
 */
function wpsl_autoptimize_optimize_js_excludes( $exclude_list ) {
    $optimization_settings = wpsl_get_optimization_settings();
    $js_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    if ( $exclude_list ) {
        $exclude_list .= ',';
    }

    $exclude_list .= 'jquery'. $min .'.js,jquery-migrate'. $min .'.js,underscore'. $min .'.js,' . $js_base . 'frontend/js/wpsl' . $min . '.js,wpsl-js-extra';

    // Exclude files based on the active map provider (frontend-only scripts)
    switch ( $optimization_settings['settings']['active_map_service'] ) {
        case 'gmaps':
            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list .= ',markerclusterer/google/index.min.js,d3/d3-color.min.js,d3/d3-interpolate.min.js';
            }

            break;
        case 'osm':
            $exclude_list .= ',assets/vendor/leaflet/leaflet-encoded'. $min .'.js,leaflet.js,leaflet.css';

            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list .= ',assets/vendor/markerclusterer/osm/leaflet-markercluster'. $min .'.js,leaflet-markercluster.min.css';
            }

            // Also exclude Leaflet by its full path, local or CDN.
            $exclude_list .= ',' . implode( ',', wpsl_get_library_exclude_paths( [ 'leaflet_js', 'leaflet_css' ] ) );

            break;
        case 'mapbox':
            $exclude_list .= ',mapbox-gl-geocoder.min.js';
            break;
    }

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'autoptimize_filter_js_exclude', 'wpsl_autoptimize_optimize_js_excludes' );

/**
 * CSS files we need to exclude from being
 * optimized when Autoptimize is active.
 *
 * @since  3.0.0
 * @see    https://wordpress.org/plugins/autoptimize/
 * @param  string $exclude_list
 * @return string $exclude_list
 */
function wpsl_autoptimize_optimize_css_excludes( $exclude_list ) {
    $optimization_settings = wpsl_get_optimization_settings();
    
    if ( $exclude_list ) {
        $exclude_list .= ',';
    }
    
    // Exclude files based on the active map provider
    switch ( $optimization_settings['settings']['active_map_service'] ) {
        case 'osm':
            $exclude_list .= 'leaflet.css,leaflet-markercluster.min.css,wpsl-leaflet-markercluster.css';
            
            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list .= ',leaflet-markercluster.min.css,wpsl-leaflet-markercluster.css';
            }
            
            // Also exclude Leaflet by its full path, local or CDN.
            $exclude_list .= ',' . implode( ',', wpsl_get_library_exclude_paths( [ 'leaflet_css' ] ) );
            break;
    }
    
    return apply_filters( 'wpsl_exclude_css', $exclude_list );
}

add_filter( 'autoptimize_filter_css_exclude', 'wpsl_autoptimize_optimize_css_excludes' );

/**
 * JS scripts we need to exclude from being
 * optimized when LiteSpeed Cache is active.
 *
 * @since  2.2.240
 * @see    https://wordpress.org/plugins/litespeed-cache/
 * @param  array $exclude_list
 * @return array $exclude_list
 */
function wpsl_litespeed_optimize_js_excludes( $exclude_list ) {
    $optimization_settings = wpsl_get_optimization_settings();
    $js_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    $exclude_list[] = 'jquery'. $min .'.js';
    $exclude_list[] = 'jquery-migrate'. $min .'.js';
    $exclude_list[] = 'underscore.min.js';
    $exclude_list[] = $js_base . 'frontend/js/wpsl' . $min .'.js';
    $exclude_list[] = 'wpsl-js-extra';

    // Exclude files based on the active map provider (frontend-only scripts)
    switch ( $optimization_settings['settings']['active_map_service'] ) {
        case 'gmaps':
            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list[] = 'assets/vendor/markerclusterer/google/index.min.js';
                $exclude_list[] = 'assets/vendor/d3/d3-color.min.js';
                $exclude_list[] = 'assets/vendor/d3/d3-interpolate.min.js';
            }

            $exclude_list[] = 'https://maps.google.com/maps/api/js';
            break;
        case 'osm':
            $exclude_list[] = 'assets/vendor/leaflet/leaflet-encoded'. $min .'.js';

            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list[] = 'assets/vendor/markerclusterer/osm/leaflet-markercluster'. $min .'.js';
            }

            // Also exclude Leaflet itself to ensure correct loading order
            $exclude_list = array_merge( $exclude_list, wpsl_get_library_exclude_paths( [ 'leaflet_js', 'leaflet_css' ] ) );
            
            break;
        case 'mapbox':
            $exclude_list[] = 'mapbox-gl-geocoder.min.js';
            $exclude_list[] = 'api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder';
            break;
    }

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'litespeed_optm_js_defer_exc',    'wpsl_litespeed_optimize_js_excludes' );
add_filter( 'litespeed_optimize_js_excludes', 'wpsl_litespeed_optimize_js_excludes' );
add_filter( 'litespeed_optm_gm_js_exc',       'wpsl_litespeed_optimize_js_excludes' ); //see https://docs.litespeedtech.com/lscache/lscwp/pageopt/#guest-mode-js-excludes

/**
 * Prevent LiteSpeed Cache from combining or delaying wp-hooks,
 * which would make wp.hooks undefined when wpsl.js runs as a module.
 * 
 * Applies when JS combine or defer/delay is enabled (aggressive preset and above).
 *
 * @since 3.0.0
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @return string The modified script tag.
 */
function wpsl_prevent_wp_hooks_optimization( $tag, $handle ) {
    if ( 'wp-hooks' === $handle && wpsl_is_litespeed_js_optimization_enabled() ) {
        return str_replace( '<script ', '<script data-no-optimize="1" ', $tag );
    }

    return $tag;
}

add_filter( 'script_loader_tag', 'wpsl_prevent_wp_hooks_optimization', 10, 2 );

/**
 * Check if LiteSpeed Cache JS combine or defer/delay is active.
 *
 * @since 3.0.0
 * @return bool
 */
function wpsl_is_litespeed_js_optimization_enabled() {
    if ( ! is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
        return false;
    }

    $js_comb  = get_option( 'litespeed.optm-js_comb', false );
    $js_defer = get_option( 'litespeed.optm-js_defer', false );

    return $js_comb || $js_defer === 1 || $js_defer === 2;
}

/**
 * JS scripts we need to exclude from being
 * optimized when Breeze is active.
 *
 * @since  3.0.0
 * @see    https://wordpress.org/plugins/breeze/
 * @param  string $exclude_list
 * @return string $exclude_list
 */
function wpsl_breeze_optimize_js_excludes( $exclude_list ) {
    $optimization_settings = wpsl_get_optimization_settings();
    $js_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    if ( $exclude_list ) {
        $exclude_list .= ',';
    }

    // Exclude jQuery with full URLs - THIS IS THE KEY FIX!
    $site_url = site_url();
    $exclude_list .= $site_url . '/wp-includes/js/jquery/jquery' . $min . '.js,';
    $exclude_list .= $site_url . '/wp-includes/js/jquery/jquery-migrate' . $min . '.js,';
    $exclude_list .= $site_url . '/wp-includes/js/underscore' . $min . '.js';
    
    return $exclude_list;
}

// Add Breeze exclusion filters if Breeze is active
if ( is_plugin_active( 'breeze/breeze.php' ) ) {
    add_filter( 'breeze_filter_js_exclude', 'wpsl_breeze_optimize_js_excludes', 10, 1 );
}

/**
 * JS scripts we need to exclude from being
 * optimized when WP Rocket is active.
 *
 * @since  2.2.240
 * @see    https://wp-rocket.me/
 * @param  array $exclude_list
 * @return array $exclude_list
 */
function wpsl_wp_rocket_optimize_js_excludes( $exclude_list ) {
    $optimization_settings = wpsl_get_optimization_settings();
    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
    $js_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'src' : 'dist';

    $exclude_list[] = '/wp-includes/js/jquery/jquery'. $min .'.js';
    $exclude_list[] = '/wp-includes/js/jquery/jquery-migrate'. $min .'.js';
    $exclude_list[] = '/wp-includes/js/dist/hooks.js';
    $exclude_list[] = '/wp-content/plugins/wp-store-locator/assets/'. $js_base .'/frontend/js/wpsl'. $min .'.js';
    $exclude_list[] = '/wp-includes/js/underscore.min.js';

    // Exclude files based on the active map provider (frontend-only scripts)
    switch ( $optimization_settings['settings']['active_map_service'] ) {
        case 'gmaps':
            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list[] = '/wp-content/plugins/wp-store-locator/assets/vendor/markerclusterer/google/index.min.js';
                $exclude_list[] = '/wp-content/plugins/wp-store-locator/assets/vendor/d3/d3-color.min.js';
                $exclude_list[] = '/wp-content/plugins/wp-store-locator/assets/vendor/d3/d3-interpolate.min.js';
            }
            
            $exclude_list[] = 'https://maps.google.com/maps/api/js';
            break;
        case 'osm':
        case 'stadia': // Stadia renders through Leaflet and uses the same scripts.
            $exclude_list[] = '/wp-content/plugins/wp-store-locator/assets/vendor/leaflet/leaflet-encoded'. $min .'.js';
            $exclude_list   = array_merge( $exclude_list, wpsl_get_library_exclude_paths( [
                'leaflet_js',
                'maplibre_gl_js',         // Only enqueued for vector map styles.
                'maplibre_gl_leaflet_js',
            ] ) );

            if ( $optimization_settings['cluster_markers_active'] ) {
                $exclude_list[] = '/wp-content/plugins/wp-store-locator/assets/vendor/markerclusterer/osm/leaflet-markercluster'. $min .'.js';
            }
            break;
        case 'mapbox':
            $exclude_list[] = 'mapbox-gl-geocoder';
            $exclude_list[] = 'api.mapbox.com';
            break;
    }

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'rocket_exclude_defer_js', 'wpsl_wp_rocket_optimize_js_excludes' );
add_filter( 'rocket_exclude_js', 	   'wpsl_wp_rocket_optimize_js_excludes' );