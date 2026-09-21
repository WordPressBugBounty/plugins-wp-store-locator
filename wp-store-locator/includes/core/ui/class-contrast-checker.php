<?php
/**
 * WCAG contrast ratio calculator.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\UI;

defined( 'ABSPATH' ) || exit;

class Contrast_Checker {

    /**
     * Convert a hex color string to an RGB array.
     *
     * Accepts both CSS spellings: the theme style defaults are written as
     * '#fff' and '#000', and those values reach this method straight from the
     * color fields.
     *
     * @since  3.0.0
     * @param  string     $hex  Hex color with or without leading '#', 3 or 6 digits.
     * @return array|null       ['r' => int, 'g' => int, 'b' => int], or null on failure.
     */
    public function hex_to_rgb( string $hex ): ?array {
        $hex = ltrim( $hex, '#' );

        if ( ! ctype_xdigit( $hex ) ) {
            return null;
        }

        // Shorthand: every digit stands for itself doubled, so 'f' is 'ff'.
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( strlen( $hex ) !== 6 ) {
            return null;
        }

        return [
            'r' => hexdec( substr( $hex, 0, 2 ) ),
            'g' => hexdec( substr( $hex, 2, 2 ) ),
            'b' => hexdec( substr( $hex, 4, 2 ) ),
        ];
    }

    /**
     * Calculate relative luminance per WCAG 2.1 §1.4.3.
     *
     * @since  3.0.0
     * @param  array $rgb  ['r' => int, 'g' => int, 'b' => int]
     * @return float       Relative luminance in [0, 1].
     */
    public function get_luminance( array $rgb ): float {
        $channels = [];

        foreach ( [ 'r', 'g', 'b' ] as $channel ) {
            $v = $rgb[ $channel ] / 255;
            $channels[] = $v <= 0.04045
                ? $v / 12.92
                : pow( ( $v + 0.055 ) / 1.055, 2.4 );
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Calculate the WCAG contrast ratio between two hex colors.
     *
     * @since  3.0.0
     * @param  string $hex1  First hex color.
     * @param  string $hex2  Second hex color.
     * @return float         Contrast ratio in [1, 21]. Returns 1.0 if either color is invalid.
     */
    public function get_contrast_ratio( string $hex1, string $hex2 ): float {
        $rgb1 = $this->hex_to_rgb( $hex1 );
        $rgb2 = $this->hex_to_rgb( $hex2 );

        if ( null === $rgb1 || null === $rgb2 ) {
            return 1.0;
        }

        $l1 = $this->get_luminance( $rgb1 );
        $l2 = $this->get_luminance( $rgb2 );

        $brightest = max( $l1, $l2 );
        $darkest   = min( $l1, $l2 );

        return ( $brightest + 0.05 ) / ( $darkest + 0.05 );
    }

    /**
     * Map a contrast ratio to a WCAG rating string.
     *
     * Ratings:
     *   'aa'       — ≥ 4.5:1  passes WCAG AA for normal text
     *   'aa-large' — ≥ 3.0:1  passes WCAG AA for large text only
     *   'fail'     — < 3.0:1  fails all WCAG AA criteria
     *
     * @since  3.0.0
     * @param  float  $ratio
     * @return string
     */
    public function get_rating( float $ratio ): string {
        if ( $ratio >= 4.5 ) {
            return 'aa';
        }

        if ( $ratio >= 3.0 ) {
            return 'aa-large';
        }

        return 'fail';
    }
}