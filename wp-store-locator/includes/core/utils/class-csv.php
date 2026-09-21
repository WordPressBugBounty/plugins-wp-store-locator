<?php
/**
 * CSV output helpers.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CSV {

    /**
     * Neutralize CSV formula injection: values starting with =, +, -, @,
     * tab, or CR are prefixed with a single quote so spreadsheets treat
     * them as literal text ( OWASP recommendation ). Non-strings untouched.
     *
     * @since  3.0.0
     * @param  mixed $value The cell value.
     * @return mixed        The escaped value ( non-strings are returned untouched ).
     */
    public static function escape_field( $value ) {
        if ( ! is_string( $value ) || '' === $value ) {
            return $value;
        }

        if ( preg_match( '/^[=+\-@\t\r]/', $value ) ) {
            return "'" . $value;
        }

        return $value;
    }
}