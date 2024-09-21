<?php
/**
 * Try to automatically prevent JS files required by WPSL from
 * being combined / minified by caching / optimization plugins
 * that are known to trigger JS errors.
 *
 * It can otherwise result in a Uncaught (in promise) be
 * {message: ‘wpslCallback is not a function’} error.
 *
 * @since 2.2.240
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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

    $exclude_list[] = 'wpsl-js';
    $exclude_list[] = 'wpsl-cluster';
    $exclude_list[] = 'underscore';

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'sgo_javascript_combine_exclude', 'wpsl_sgo_optimize_js_excludes' );

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

    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    if ( $exclude_list ) {
        $exclude_list .= ',';
    }

    $exclude_list .= 'jquery'. $min .'.js,jquery-migrate'. $min .'.js,underscore'. $min .'.js,wpsl-gmap'. $min .'.js';

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'autoptimize_filter_js_exclude', 'wpsl_autoptimize_optimize_js_excludes' );

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

    global $wpsl_settings;

    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    $exclude_list[] = 'jquery'. $min .'.js';
    $exclude_list[] = 'jquery-migrate'. $min .'.js';
    $exclude_list[] = 'wpslSettings'; // wp_localize_script var
    $exclude_list[] = 'wpsl-gmap'. $min .'.js';

    if ( $wpsl_settings['marker_clusters'] ) {
        $exclude_list[] = 'markerclusterer'. $min .'.js';
    }

    $exclude_list[] = 'underscore'. $min .'.js';
    $exclude_list[] = 'https://maps.google.com/maps/api/js';

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'litespeed_optimize_js_excludes', 'wpsl_litespeed_optimize_js_excludes' );
add_filter( 'litespeed_optm_gm_js_exc',       'wpsl_litespeed_optimize_js_excludes' ); // see https://docs.litespeedtech.com/lscache/lscwp/pageopt/#guest-mode-js-excludes

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

    global $wpsl_settings;

    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    $exclude_list[] = '/wp-includes/js/jquery/jquery'. $min .'.js';
    $exclude_list[] = '/wp-includes/js/jquery/jquery-migrate'. $min .'.js';
    $exclude_list[] = '/wp-includes/js/underscore'. $min .'.js';
    $exclude_list[] = '/wp-content/plugins/wp-store-locator/js/wpsl-gmap'. $min .'.js';

    if ( $wpsl_settings['marker_clusters'] ) {
        $exclude_list[] = '/wp-content/plugins/wp-store-locator/js/markerclusterer'. $min .'.js';
    }

    return apply_filters( 'wpsl_exclude_js', $exclude_list );
}

add_filter( 'rocket_exclude_defer_js', 'wpsl_wp_rocket_optimize_js_excludes' );
add_filter( 'rocket_exclude_js', 	   'wpsl_wp_rocket_optimize_js_excludes' );