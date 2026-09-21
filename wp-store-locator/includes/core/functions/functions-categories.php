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