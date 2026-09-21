<?php
/**
 * String, sanitization and array helpers.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Callback for array_walk_recursive, sanitize items in a multidimensional array.
 *
 * @since 2.0.0
 * @param string  $item The value
 * @param integer $key  The key
 */
function wpsl_sanitize_multi_array( &$item, $key ) {
    $item = sanitize_text_field( $item );
}

/**
 * Check whether the array is multidimensional.
 *
 * @since  2.0.0
 * @param  array    $array The array to check
 * @return boolean
 */
function wpsl_is_multi_array( $array ) {
    return \WPSL\Core\Utils\Formatting::is_multi_array( $array );
}

/**
 * Make sure the shortcode attributes are booleans
 * when they are expected to be.
 *
 * @since  2.0.4
 * @param  array $atts Shortcode attributes
 * @return array $atts Shortcode attributes
 */
function wpsl_bool_check( $atts ) {
    return \WPSL\Core\Utils\Formatting::bool_check( $atts );
}

/**
 * Create a string with random characters.
 *
 * @since  2.2.4
 * @param  int    $length       Used length
 * @return string $random_chars Random characters
 */
function wpsl_random_chars( $length = 5 ) {
    return \WPSL\Core\Utils\Formatting::random_chars( $length );
}

/**
 * Make sure the phone number only contains
 * digits and the + or - characters.
 *
 * @since  3.0.0
 * @param  string $phone The phone number
 * @return string $phone The sanitized phone number
 */
function wpsl_sanitize_phone( $phone ) {
    return \WPSL\Core\Utils\Formatting::sanitize_phone( $phone );
}

/**
 * Neutralize a single CSV cell against spreadsheet formula injection.
 *
 * Thin wrapper around {@see \WPSL\Core\Utils\CSV::escape_field()} so the guard can
 * be used with array_map() and other callable contexts.
 *
 * @since  3.0.0
 * @param  mixed $value The cell value.
 * @return mixed        The escaped value ( non-strings are returned untouched ).
 */
function wpsl_escape_csv_field( $value ) {
    return \WPSL\Core\Utils\CSV::escape_field( $value );
}

/**
 * Convert the [policy_url]text[/policy_url] into a clickable 
 * url and replace [map_provider] with the name of the
 * active map provider.
 *
 * This is only used for text in the WPSL GDPR checkpoint.
 *
 * @since  3.0.0
 * @param  string $input The text to filter
 * @param  string $url   The URL
 * @return string        Text with a clickable url and the full name of the active map provider
 */
function wpsl_convert_bbcode_to_string( $input, $url ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    if ( strpos( $input, '[map_provider]' ) !== false ) {
        $map_services = wpsl_get_map_services();

        $input = str_replace('[map_provider]', $map_services[ $wpsl_settings['active_map_service'] ], $input );
    }

    $pattern = '/\[policy_url\](.+?)\[\/policy_url\]/';
    $replacement = '<a target="_blank" href="' . $url . '">$1</a>';

    return preg_replace( $pattern, $replacement, $input );
}

/**
 * Convert a string to camelCase.
 *
 * @since  3.0.0
 * @param  string $string
 * @return string $string
 */
function wpsl_camel_case( $string ) {
    return \WPSL\Core\Utils\Formatting::camel_case( $string );
}

/**
 * Replace spaces with hypens, and make sure only
 * a-zA-Z0-9_- characters are part of the string.
 *
 * @since  3.0.0
 * @param  string $input
 * @return string $input
 */
function wpsl_alphanum_no_space( $input ) {
    return \WPSL\Core\Utils\Formatting::alphanum_no_space( $input );
}

/**
 * Change underscore-separated string to readable format.
 * Example: 'search_results' becomes 'Search results'
 *
 * @since  3.0.0
 * @param  string $string
 * @return string
 */
function wpsl_underscore_to_readable( $string ) {
    return \WPSL\Core\Utils\Formatting::underscore_to_readable( $string );
}

/**
 * Check if an array is multidimensional.
 *
 * @since  3.0.0
 * @param  array $array
 * @return bool
 */
function wpsl_is_multidimensional( $array ) {
    return \WPSL\Core\Utils\Formatting::is_multidimensional( $array );
}