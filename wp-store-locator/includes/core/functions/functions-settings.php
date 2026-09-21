<?php
/**
 * Settings accessors and defaults.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get a setting from the WPSL settings.
 *
 * @since  3.0.0
 * @param  string $group The setting group
 * @param  string $key The setting key
 * @return mixed The setting value
 */
function wpsl_settings( $group, $key ) {
    return wpsl_get_service( 'wpsl_settings' )->get( $group, $key );
}

/**
 * The labels and the values that can be set through the settings page.
 *
 * @since  2.0.0
 * @return array $labels The label names from the settings page.
 */
function wpsl_labels() {
    $labels = [
        'search',
        'search_name',
        'radius',
        'no_results',
        'search_btn',
        'preloader',
        'results',
        'category',
        'category_default',
        'filters',
        'show_filters',
        'apply',
        'more',
        'phone',
        'fax',
        'email',
        'url',
        'more_details',
        'hours',
        'start',
        'directions',
        'loading_directions',
        'no_directions',
        'back',
        'street_view',
        'zoom_here',
        'error',
        'number_results',
        'number_results_single',
    ];

    return $labels;
}

/**
 * Get a label by its settings name, translated and escaped.
 *
 * Backs the {{wpsl_label( 'phone_label' )}} template tag in the section
 * templates. Returns the label from the settings page after any active
 * multilingual plugin translated it, or an empty string for unknown names.
 *
 * @since  3.0.0
 * @param  string $name The name of value from the WPSL settings page ( label section )
 * @return string The escaped label, or an empty string
 */
function wpsl_label( $name ) {
    return wpsl_get_service( 'i18n' )->get_label( $name );
}

/**
 * Return the used distance unit.
 *
 * @since  2.2.8
 * @return string Either km or mi
 */
function wpsl_get_distance_unit() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'search' );

    $distance_unit = $wpsl_settings['distance_unit'];

    // Check if shortcode has a distance_unit attribute
    $container = wpsl_container();
    
    if ( $container->has( 'shortcodes' ) ) {
        $shortcode_atts = $container->get( 'shortcodes' )->atts;
        
        if ( ! empty( $shortcode_atts['distance_unit'] ) && in_array( $shortcode_atts['distance_unit'], [ 'km', 'mi' ], true ) ) {
            $distance_unit = $shortcode_atts['distance_unit'];
        }
    }

    return apply_filters( 'wpsl_distance_unit', $distance_unit );
}

/**
 * Get a list of the used meta fields.
 *
 * Used by add-ons and the REST-API.
 *
 * @since  2.2.14
 * @param  array $exclude Argument to grab the locations field. See the $defaults structure.
 * @return array $fields
 */
function wpsl_get_location_fields( $exclude = [] ) {
    $meta_fields = wpsl_get_service( 'store_fields' )->get_fields();

    $fields   = [];
    $defaults = [ 'country_iso' ];

    $exclude = wp_parse_args( $exclude, $defaults );

    foreach ( $meta_fields as $k => $field_section ) {
        foreach ( $field_section as $field_name => $field_value ) {
            if ( in_array( $field_name, $exclude ) ) {
                continue;
            }

            $fields[] = $field_name;
        }
    }

    return $fields;
}

/**
 * Get a selection of the post details
 * that are included in the REST API response.
 *
 * @since  3.0.0
 * @param  array  $data The existing response data
 * @param  object $item Post details
 * @return array  $publishing_details The collected publishing details
 */
function wpsl_get_publishing_details( $data, $item ) {
    $publishing_details = apply_filters( 'wpsl_publishing_details', [
        'author'       => (int) $item->post_author,
        'date'         => $item->post_date,
        'description'  => $item->post_content,
        'excerpt'      => $item->post_excerpt,
        'date_gmt'     => $item->post_date_gmt,
        'modified'     => $item->post_modified,
        'modified_gmt' => $item->post_modified_gmt,
        'status'       => $item->post_status,
    ] );

    return array_merge( $data, $publishing_details );
}

/**
 * Get the allowed search options
 *
 * @since  3.0.0
 * @return array $search_options The allowed search options
 */
function wpsl_get_search_order_options() {
    $search_options = apply_filters( 'wpsl_search_order_options', [
        'distance' => __( 'Distance (default)', 'wp-store-locator' ),
        'store'    => __( 'Store Name', 'wp-store-locator' ),
        'address'  => __( 'Address', 'wp-store-locator' ),
        'id'       => __( 'ID', 'wp-store-locator' ),
        'city'     => __( 'City', 'wp-store-locator' ),
        'zip'      => __( 'Zip code', 'wp-store-locator' ),
        'state'    => __( 'State', 'wp-store-locator' ),
        'country'  => __( 'Country', 'wp-store-locator' ),
    ]);

    return $search_options;
}

/**
 * Get the max range and default value for a
 * filter type ( max radius or max results ).
 *
 * @since  3.0.0
 * @param  string $type  Either 'max_results' or 'radius' (settings key name)
 * @return array  $range The max and default values
 */
function wpsl_get_filter_range( $type ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'search' );

    $range       = [];
    $list_values = explode( ',', $wpsl_settings[$type] );

    foreach ( $list_values as $k => $list_value ) {

        // The default radius has a [] wrapped around it, so we check for that and filter out the [].
        if ( strpos( $list_value, '[' ) !== false ) {
            $range['default'] = filter_var( $list_value, FILTER_SANITIZE_NUMBER_INT );
            break;
        }
    }

    $range['max'] = end( $list_values );

    return $range;
}

/**
 * Get a list of the available search types
 * ( location, name etc ).
 *
 * Used both in WPSl itself and the
 * search widget.
 *
 * @since   3.0.0
 * @return  array $search_types
 */
function wpsl_available_search_types() {
    $search_types = apply_filters( 'wpsl_available_search_types', [
        'name'     => __( 'Name', 'wp-store-locator' ),
        'location' => __( 'Location', 'wp-store-locator' )
    ] );

    return $search_types;
}

/**
 * Return the available location status options.
 *
 * @since   3.0.0
 * @return  array $status_options
 */
function wpsl_get_location_status_options() {
    $status_options = [
        'open'               => __( 'Open', 'wp-store-locator' ),
        'temporarily_closed' => __( 'Temporarily closed', 'wp-store-locator' ),
        'permanently_closed' => __( 'Permanently closed', 'wp-store-locator' ),
    ];

    return $status_options;
}

/**
 * Create a custom meta filter dropdown or checkbox list.
 *
 * @since  3.0.0
 * @param  array  $args {
 *     Array of arguments for creating the meta filter.
 *
 *     @type string $meta_key  Required. The custom field meta key to filter by.
 *     @type string $type      Required. Filter type: 'dropdown' or 'checkbox'.
 *     @type string $label     Optional. Label text (only for dropdown type).
 *     @type string $selected  Optional. Pre-selected value.
 *     @type int    $columns   Optional. Number of columns (only for checkbox type, default: 3).
 * }
 * @return string The HTML markup for the filter.
 *
 * @example echo wpsl_create_meta_filter( [ 'meta_key' => 'wpsl_brand', 'type' => 'dropdown' ] );
 */
function wpsl_create_meta_filter( $args ) {
    $filters = wpsl_get_service( 'frontend_search_filters' );

    return $filters->create_meta_filter( $args );
}

/**
 * Resolve the height settings for a template.
 *
 * Dimension heights are stored per template (dimensions[horizontal|vertical|default]),
 * each with a *_mode key switching between stock and custom. Both the admin
 * preview and frontend CSS resolve through here so the preview can't differ
 * from what visitors get. A 'default' mode returns the stock height rather
 * than nothing, matching the "Default mode sets the map height to 350px" hint.
 *
 * @since  3.0.0
 * @param  array  $dimensions The appearance dimensions.
 * @param  string $template   Template to resolve for: horizontal, vertical or
 *                            default. Custom templates pass vertical when they
 *                            have a panel and default when they do not.
 * @return array {
 *     Only the keys that apply to $template.
 *
 *     @type int      $map_height      Horizontal only.
 *     @type int      $results_height  Horizontal only.
 *     @type int      $sl_height       Vertical only.
 *     @type int|null $combined_height Default only. Map and results share one
 *                                     height there, as they did in 2.x. Null
 *                                     when no height was ever set, so an
 *                                     untouched install keeps whatever the
 *                                     stylesheet does rather than gaining a
 *                                     height rule.
 * }
 */
function wpsl_dimension_heights( $dimensions, $template ) {
    $dimensions = is_array( $dimensions ) ? $dimensions : [];

    if ( $template === 'horizontal' ) {
        return [
            'map_height'     => wpsl_resolve_dimension( $dimensions, 'horizontal', 'map_height', 350 ),
            'results_height' => wpsl_resolve_dimension( $dimensions, 'horizontal', 'results_height', 350 ),
        ];
    }

    if ( $template === 'vertical' ) {
        return [
            'sl_height' => wpsl_resolve_dimension( $dimensions, 'vertical', 'sl_height', 450 ),
        ];
    }

    if ( ! empty( $dimensions['default'] ) ) {
        return [
            'combined_height' => wpsl_resolve_dimension( $dimensions, 'default', 'map_height', 350 ),
        ];
    }

    /*
     * map_and_results_height is the pre-per-template 2.x migration key, still
     * read so early-3.0 upgrades keep their height. With neither stored, leave
     * it to the stylesheet - an install that never set one shouldn't gain a rule.
     */
    $legacy = isset( $dimensions['map_and_results_height'] ) ? absint( $dimensions['map_and_results_height'] ) : 0;

    return [
        'combined_height' => $legacy ? $legacy : null,
    ];
}

/**
 * Resolve one dimension against its *_mode key.
 *
 * @since  3.0.0
 * @param  array    $dimensions The appearance dimensions.
 * @param  string   $template   The template the value is stored under.
 * @param  string   $key        The dimension key.
 * @param  int|null $stock      The height to use for the stock mode, and when
 *                              nothing usable is stored.
 * @return int|null
 */
function wpsl_resolve_dimension( $dimensions, $template, $key, $stock ) {
    $values = isset( $dimensions[ $template ] ) && is_array( $dimensions[ $template ] ) ? $dimensions[ $template ] : [];
    $mode   = isset( $values[ $key . '_mode' ] ) ? $values[ $key . '_mode' ] : 'custom';

    if ( $mode === 'default' ) {
        return $stock;
    }

    $value = isset( $values[ $key ] ) ? absint( $values[ $key ] ) : 0;

    return $value ? $value : $stock;
}

/**
 * Get the current plugin settings.
 *
 * @since      1.0.0
 * @deprecated 3.0.0
 * @return     void
 */
function wpsl_get_settings() {
    _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'wpsl_settings' )->get_all()" );
}

/**
 * Get a single value from the default settings.
 *
 * @since      1.0.0
 * @deprecated 3.0.0
 * @param      string $group The value that should be restored
 * @return     void
 */
function wpsl_get_default_setting( $group ) {
    _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'wpsl_settings' )->defaults( \$group )" );
}

/**
 * Set the default plugin settings.
 *
 * @since      1.0.0
 * @deprecated 3.0.0
 * @return     void
 */
function wpsl_set_default_settings() {
    _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'wpsl_settings' )->set_defaults()" );
}

/**
 * Get the default plugin settings.
 *
 * @since      1.0.0
 * @deprecated 3.0.0
 * @return     void
 */
function wpsl_get_default_settings() {
    _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'wpsl_settings' )->defaults()" );
}