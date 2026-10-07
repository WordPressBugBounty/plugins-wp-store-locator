<?php
/**
 * Store category and taxonomy helpers.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Find the term ids for the provided term slugs or ids.
 *
 * @since  2.2.10
 * @param  string $cat_list Comma separated list of term slugs or term ids
 * @return array  $term_ids The term ids
 */
function wpsl_get_term_ids( $cat_list ) {
    $term_ids = [];
    $cats     = array_map( 'sanitize_text_field', explode( ',', $cat_list ) );

    foreach ( $cats as $key => $cat ) {
        $term_data = is_numeric( $cat )
            ? get_term( absint( $cat ), 'wpsl_store_category' )
            : get_term_by( 'slug', $cat, 'wpsl_store_category' );

        if ( ! is_wp_error( $term_data ) && isset( $term_data->term_id ) && $term_data->term_id ) {
            $term_ids[] = $term_data->term_id;
        }
    }

    return $term_ids;
}

/**
 * Normalize the category filter request parameter.
 *
 * @since  3.0.0
 * @param  string|array $filter The raw filter parameter
 * @return string       $filter Comma separated list of term ids
 */
function wpsl_normalize_category_filter( $filter ) {
    if ( is_array( $filter ) ) {
        $filter = implode( ',', array_filter( $filter, 'is_scalar' ) );
    }

    return sanitize_text_field( $filter );
}

/**
 * Get a list of unique store categories.
 *
 * @since   3.0.0
 * @return  array $unique_categories List of unique categories ( term_id => name )
 */
function wpsl_get_unique_categories() {
    // Check if we have cached results first
    static $cached_categories = null;
    
    if ( $cached_categories !== null ) {
        return $cached_categories;
    }
    
    // Check for transient cache
    $transient_key = 'wpsl_unique_categories';
    $cached_categories = get_transient( $transient_key );
    
    if ( $cached_categories !== false ) {
        return $cached_categories;
    }

    $unique_categories = [];

    // Get all terms from the wpsl_store_category taxonomy that are actually used
    $terms = get_terms( [
        'taxonomy'   => 'wpsl_store_category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ] );

    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $term ) {
            $unique_categories[ $term->term_id ] = $term->name;
        }
    }
    
    // Cache the results for 1 hour
    set_transient( $transient_key, $unique_categories, HOUR_IN_SECONDS );
    $cached_categories = $unique_categories;

    return $unique_categories;
}

/**
 * Clear the cached unique categories data.
 * Should be called when categories are created, updated, or deleted.
 *
 * @since 3.0.0
 */
function wpsl_clear_unique_categories_cache() {
    delete_transient( 'wpsl_unique_categories' );
}

/**
 * The colors offered as presets in the category color picker.
 *
 * They take the place of the lightness swatches below the color wheel.
 *
 * @since  3.1.0
 * @return array Hex colors.
 */
function wpsl_category_color_presets() {
    return apply_filters( 'wpsl_category_color_presets', [
        '#d63638',
        '#e26f1e',
        '#dba617',
        '#00a32a',
        '#1a9c8c',
        '#2271b1',
        '#3858e9',
        '#7e57c2',
        '#c2378e',
        '#50575e',
    ] );
}

/**
 * The shape of the colored dot in front of a category name.
 *
 * @since  3.1.0
 * @return string 'circle', or 'square' for a square with rounded corners.
 */
function wpsl_category_dot_shape() {
    $shape = wpsl_get_service( 'wpsl_settings' )->get( 'appearance', 'categories.shape' );

    return ( 'square' === $shape ) ? 'square' : 'circle';
}

/**
 * The color a category has been given.
 *
 * @since  3.1.0
 * @param  int $term_id Term id.
 * @return string The hex color, or '' when the category has none.
 */
function wpsl_category_color( $term_id ) {
    static $colors = [];

    $term_id = (int) $term_id;

    if ( ! isset( $colors[ $term_id ] ) ) {
        $color = sanitize_hex_color( (string) get_term_meta( $term_id, 'wpsl_category_color', true ) );

        $colors[ $term_id ] = $color ? $color : '';
    }

    return apply_filters( 'wpsl_category_color', $colors[ $term_id ], $term_id );
}

/**
 * The colored dot shown in front of a category name.
 *
 * Decorative: the name is always next to it, so it is hidden from
 * assistive technology.
 *
 * @since  3.1.0
 * @param  int $term_id Term id.
 * @return string The dot markup, or '' when the category has no color.
 */
function wpsl_category_dot( $term_id ) {
    return wpsl_category_dot_markup( wpsl_category_color( $term_id ) );
}

/**
 * The markup of the colored dot, for a given color.
 *
 * @since  3.1.0
 * @param  string $color A hex color.
 * @return string The dot markup, or '' without a valid color.
 */
function wpsl_category_dot_markup( $color ) {
    $color = sanitize_hex_color( (string) $color );

    if ( ! $color ) {
        return '';
    }

    return '<span class="wpsl-category-dot" style="background-color:' . esc_attr( $color ) . '" aria-hidden="true"></span>';
}

/**
 * The categories shown in the preview on the Appearance page.
 *
 * @since  3.1.0
 * @return array Objects with a term_id, name and color.
 */
function wpsl_example_categories() {
    return [
        (object) [ 'term_id' => 1, 'name' => __( 'Headquarters', 'wp-store-locator' ), 'color' => '#2271b1' ],
        (object) [ 'term_id' => 2, 'name' => __( 'Office', 'wp-store-locator' ), 'color' => '#d63638' ],
    ];
}

/**
 * The category names of a store, as shown in the search results.
 *
 * A category with a color carries it as --wpsl-cat-color, which the
 * stylesheet uses for the dot and the tint. One without a color gets the
 * neutral style and no dot.
 *
 * @since  3.1.0
 * @param  int $store_id The store id.
 * @return string The markup, or '' when the store has no categories.
 */
function wpsl_store_categories_html( $store_id ) {
    $terms = get_the_terms( $store_id, 'wpsl_store_category' );

    if ( ! $terms || is_wp_error( $terms ) ) {
        return '';
    }

    $terms = apply_filters( 'wpsl_store_categories', $terms, $store_id );

    if ( ! $terms ) {
        return '';
    }

    $output = '<div class="wpsl-categories">';

    foreach ( $terms as $term ) {
        $color = wpsl_category_color( $term->term_id );
        $style = $color ? ' style="--wpsl-cat-color:' . esc_attr( $color ) . '"' : '';
        $dot   = $color ? '<span class="wpsl-category-dot" aria-hidden="true"></span>' : '';

        $output .= '<span class="wpsl-category"' . $style . '>' . $dot . esc_html( $term->name ) . '</span>';
    }

    $output .= '</div>';

    return $output;
}