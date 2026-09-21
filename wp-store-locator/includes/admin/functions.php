<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The 3.0 release post, with the What's New campaign parameters.
 *
 * @since  3.0.0
 * @param  string $fragment Optional fragment to append ( e.g. '#add-on' ).
 * @return string
 */
function wpsl_release_post_url( $fragment = '' ) {
    $url = 'https://wpstorelocator.co/version-3-0-released/?utm_source=wpsl-whats-new&utm_medium=admin&utm_campaign=v3-release';

    return $url . $fragment;
}

/**
 * Whether this admin page prints WPSL notices inside its own header.
 *
 * Display_Page::admin_header() renders #wpsl-notice-wrap under the WPSL logo
 * bar and flushes notices there, so anything that would otherwise print at
 * WordPress' default spot ( above that bar ) has to hold back here and let the
 * header emit it instead.
 *
 * Excludes wpsl_appearance and wpsl_marker_studio: both render with
 * skip_header, so admin_header() never runs and a held-back notice would be
 * lost rather than merely misplaced.
 *
 * @since  3.0.0
 * @return bool
 */
function wpsl_admin_page_renders_own_notices() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
    if ( ! isset( $_GET['post_type'] ) || sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) !== 'wpsl_stores' ) {
        return false;
    }

    if ( ! isset( $_GET['page'] ) ) {
        return false;
    }

    $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    return ! in_array( $page, [ 'wpsl_appearance', 'wpsl_marker_studio' ], true );
}

/**
 * Whether this admin page keeps WPSL notices off it altogether.
 *
 * What's New is a release announcement someone opens on purpose, so add-on
 * warnings and migration notices are suppressed here. Nothing is dropped: the
 * stored notices simply aren't flushed, so they still print on the next WPSL
 * screen that shows them.
 *
 * @since  3.0.0
 * @return bool
 */
function wpsl_admin_page_hides_notices() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
    if ( ! isset( $_GET['post_type'] ) || sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) !== 'wpsl_stores' ) {
        return false;
    }

    if ( ! isset( $_GET['page'] ) ) {
        return false;
    }

    return 'wpsl_whats-new' === sanitize_text_field( wp_unslash( $_GET['page'] ) );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/**
 * Get screen IDs used by WPSL.
 *
 * @since 3.0.0
 * @return array
 */
function wpsl_get_screen_ids() {
    $wpsl_screen_id = 'wpsl_stores';
    $screen_ids     = [
        'edit-' . $wpsl_screen_id,
        $wpsl_screen_id,
        'toplevel_page_wpsl_settings',
        'wpsl_stores_page_wpsl_settings',
        'wpsl_stores_page_wpsl_appearance',
        'wpsl_stores_page_wpsl_marker_studio',
        'wpsl_stores_page_wpsl_map_shapes',
        'wpsl_stores_page_wpsl_tools',
        'wpsl_stores_page_wpsl_onboarding',
        'wpsl_stores_page_wpsl_import',
        'wpsl_stores_page_wpsl_export',
        'edit-wpsl_store_category',
    ];

    return apply_filters( 'wpsl_screen_ids', $screen_ids );
}

/**
 * Check if any store locations exist.
 *
 * @since 3.0.0
 * @return array Array with 'exist' boolean and 'count' integer
 */
function wpsl_locations_exist() {
    $location_count = wp_count_posts( 'wpsl_stores' );
    $total_locations = 0;
    
    if ( $location_count ) {
        $total_locations = $location_count->publish + $location_count->draft + $location_count->private + $location_count->pending;
    }
    
    return [
        'exist' => $total_locations > 0,
        'count' => $total_locations
    ];
}

/**
 * Check if any store categories exist.
 *
 * @since 3.0.0
 * @return array Array with 'exist' boolean and 'count' integer
 */
function wpsl_categories_exist() {
    $categories = get_terms( [
        'taxonomy' => 'wpsl_store_category',
        'hide_empty' => false,
        'count' => true
    ] );
    
    $categories_exist = false;
    $category_count = 0;
    
    if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
        $categories_exist = true;
        $category_count = count( $categories );
    }
    
    return [
        'exist' => $categories_exist,
        'count' => $category_count
    ];
}

/**
 * Check if any custom markers exist in the Marker Studio library.
 *
 * @since 3.0.0
 * @return array Array with 'exist' boolean and 'count' integer
 */
function wpsl_custom_markers_exist() {
    $custom_markers = new \WPSL\Core\Markers\Custom_Markers();
    $marker_count   = count( $custom_markers->get_markers() );

    return [
        'exist' => $marker_count > 0,
        'count' => $marker_count
    ];
}

/**
 * Check if any map shapes exist.
 *
 * @since 3.0.0
 * @return array Array with 'exist' boolean and 'count' integer
 */
function wpsl_map_shapes_exist() {
    $shapes      = new \WPSL\Core\Shapes\Repository();
    $shape_count = count( $shapes->get_collection()['features'] );

    return [
        'exist' => $shape_count > 0,
        'count' => $shape_count
    ];
}