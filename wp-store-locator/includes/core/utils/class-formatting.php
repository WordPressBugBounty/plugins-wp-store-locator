<?php
/**
 * Pure string and array formatting helpers.
 *
 * The wpsl_* functions in functions-formatting.php delegate here so the logic
 * lives in a class that can be unit tested directly, while the global function
 * names stay unchanged for backwards compatibility.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Formatting {

    /**
     * Whether any element of the array is itself an array.
     *
     * @since  3.0.0
     * @param  array $array
     * @return bool
     */
    public static function is_multi_array( $array ) {
        foreach ( $array as $value ) {
            if ( is_array( $value ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Same as is_multi_array(), kept as a separate helper for the callers that
     * used the count()-based variant.
     *
     * @since  3.0.0
     * @param  array $array
     * @return bool
     */
    public static function is_multidimensional( $array ) {
        return count( array_filter( $array, 'is_array' ) ) > 0;
    }

    /**
     * Coerce string-y shortcode attribute values to real booleans.
     *
     * @since  3.0.0
     * @param  array $atts
     * @return array
     */
    public static function bool_check( $atts ) {
        foreach ( $atts as $key => $val ) {
            // Loose comparison kept from the original: shortcode attributes are
            // strings, and this matches the pre-extraction behavior exactly.
            if ( in_array( $val, [ 'true', '1', 'yes', 'on' ] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
                $atts[ $key ] = true;
            } elseif ( in_array( $val, [ 'false', '0', 'no', 'off' ] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
                $atts[ $key ] = false;
            }
        }

        return $atts;
    }

    /**
     * A string of random lowercase characters.
     *
     * @since  3.0.0
     * @param  int $length
     * @return string
     */
    public static function random_chars( $length = 5 ) {
        return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz' ), 0, $length );
    }

    /**
     * Keep only digits and + ( ) - space in a phone number.
     *
     * @since  3.0.0
     * @param  string $phone
     * @return string
     */
    public static function sanitize_phone( $phone ) {
        return preg_replace( '/[^\d+() -]/', '', $phone );
    }

    /**
     * Convert a string to camelCase.
     *
     * @since  3.0.0
     * @param  string $string
     * @return string
     */
    public static function camel_case( $string ) {
        return lcfirst( str_replace( [ '-', '_' ], '', ucwords( $string, '-_' ) ) );
    }

    /**
     * Replace spaces with hyphens and strip anything outside a-zA-Z0-9_-.
     *
     * @since  3.0.0
     * @param  string $input
     * @return string
     */
    public static function alphanum_no_space( $input ) {
        $input = str_replace( ' ', '-', $input );

        return preg_replace( '/[^a-zA-Z0-9_-]/', '', $input );
    }

    /**
     * 'search_results' becomes 'Search results'.
     *
     * @since  3.0.0
     * @param  string $string
     * @return string
     */
    public static function underscore_to_readable( $string ) {
        return ucfirst( strtolower( str_replace( '_', ' ', $string ) ) );
    }
}