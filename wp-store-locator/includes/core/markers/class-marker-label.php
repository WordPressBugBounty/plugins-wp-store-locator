<?php
/**
 * Marker label text.
 *
 * The text a marker can carry in place of an icon: a letter, a number, 
 * a character from any script.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Markers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Marker_Label {

    /**
     * The widest label allowed, as the sum of its glyphs' widths.
     *
     * @since 3.0.0
     * @var   int
     */
    const MAX_WEIGHT = 3;

    /**
     * The widest label the Studio's Text field accepts: two narrow glyphs,
     * or one wide one. MAX_WEIGHT stays 3 so a runtime label ( a result
     * number past 99 ) still has a size to draw at.
     *
     * @since 3.0.0
     * @var   int
     */
    const STUDIO_MAX_WEIGHT = 2;

    /**
     * The runtime label modes: how a result's position in the list is
     * written on its marker. The single authority for the settings
     * sanitizer, the shortcode attribute and the block attribute.
     *
     * @since 3.0.0
     * @var   string[]
     */
    const MODES = [ 'none', 'numbers', 'letters' ];

    /**
     * Resolve a submitted label mode to one of MODES.
     *
     * @since  3.0.0
     * @param  mixed       $mode
     * @param  string|null $fallback Returned for anything not recognised. The
     *                               shortcode passes null, so a typo leaves the
     *                               setting in charge instead of turning labels off.
     * @return string|null
     */
    public static function sanitize_mode( $mode, $fallback = 'none' ) {
        if ( ! is_string( $mode ) ) {
            return $fallback;
        }

        $mode = strtolower( trim( $mode ) );

        // What a shortcode author reaches for to turn it off.
        if ( in_array( $mode, [ 'off', 'false', '0', 'no' ], true ) ) {
            return 'none';
        }

        return in_array( $mode, self::MODES, true ) ? $mode : $fallback;
    }

    /**
     * The font size per total weight, in units of the 24x24 icon box.
     *
     * @since 3.0.0
     * @var   string[]
     */
    const FONT_SIZE = [
        1 => '15',
        2 => '12',
        3 => '9.5',
    ];

    /**
     * Characters that take two columns: Chinese / Japanese / Korean
     * characters, their punctuation, full-width forms and emoji. Written
     * as explicit ranges rather than a Unicode property class.
     *
     * @since 3.0.0
     * @var   string
     */
    const WIDE = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\x{3000}-\x{303F}\x{FF01}-\x{FF60}\x{FFE0}-\x{FFE6}\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}]/u';

    /**
     * Whitespace trimmed off both ends.
     *
     * @since 3.0.0
     * @var   string
     */
    const TRIM = '/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u';

    /**
     * Invisible characters stripped from a label before it is measured:
     * control characters ( invalid XML in a text node, so they would break the
     * whole marker image ) and zero-width spacing characters ( they draw
     * nothing yet count as a column, letting an empty-looking label through ).
     * Extenders and variation selectors are deliberately not included: they
     * modify the glyph before them, so stripping would corrupt it, not hide it.
     *
     * Same character class as wpslMarkerLabel's STRIP; keep the two in sync.
     *
     * @since 3.0.0
     * @var   string
     */
    const STRIP = '/[\p{Cc}\x{200B}\x{2060}]/u';

    /**
     * An SVG loaded as an image cannot fetch a web font, so the label sets
     * the system stack and lets the browser fall back per script.
     *
     * @since 3.0.0
     * @var   string
     */
    const FONT_FAMILY = 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif';

    /**
     * How far below the box centre the text baseline sits, as a fraction of
     * the font size: half a cap height.
     *
     * @since 3.0.0
     * @var   int
     */
    const BASELINE_PERCENT = 35;

    /**
     * Code points that never start a unit: they attach to the one before.
     *
     * @since 3.0.0
     * @var   string
     */
    const EXTENDER = '/^[\p{M}\x{200C}\x{200D}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}\x{1F3FB}-\x{1F3FF}\x{E0020}-\x{E007F}]$/u';

    /**
     * A regional indicator symbol: two in a row make one flag.
     *
     * @since 3.0.0
     * @var   string
     */
    const REGIONAL = '/^[\x{1F1E6}-\x{1F1FF}]$/u';

    /**
     * Split text into grapheme clusters -- what a user sees as one
     * character, even when it is several code points underneath ( "é" as
     * e + accent, or a flag emoji as two letter code points ).
     *
     * Not PHP's \X or JS's Intl.Segmenter: they follow whichever Unicode
     * version their engine ships, and disagree on some clusters. One
     * explicit rule, kept identical in PHP and JS, instead: a unit is a
     * base character plus any extenders after it, plus whatever follows a
     * zero-width joiner; a flag pair is one unit.
     *
     * @since  3.0.0
     * @param  mixed $text
     * @return string[]
     */
    public static function graphemes( $text ) {
        if ( ! is_string( $text ) || '' === $text ) {
            return [];
        }

        $points = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );

        if ( false === $points ) {
            return [];
        }

        $units    = [];
        $current  = '';
        $previous = '';
        $regional = 0;

        foreach ( $points as $point ) {
            $joins = ( '' !== $current ) && (
                preg_match( self::EXTENDER, $point )
                || "\u{200D}" === $previous
                || ( 1 === $regional && preg_match( self::REGIONAL, $point ) )
            );

            if ( ! $joins ) {
                if ( '' !== $current ) {
                    $units[] = $current;
                }

                $current  = '';
                $regional = 0;
            }

            $current .= $point;

            if ( preg_match( self::REGIONAL, $point ) ) {
                $regional++;
            }

            $previous = $point;
        }

        if ( '' !== $current ) {
            $units[] = $current;
        }

        return $units;
    }

    /**
     * How many columns one grapheme takes.
     *
     * @since  3.0.0
     * @param  string $grapheme
     * @return int 1 or 2.
     */
    public static function weight_of( $grapheme ) {
        return preg_match( self::WIDE, $grapheme ) ? 2 : 1;
    }

    /**
     * How many columns a label takes.
     *
     * @since  3.0.0
     * @param  mixed $text
     * @return int
     */
    public static function weight( $text ) {
        $weight = 0;

        foreach ( self::graphemes( $text ) as $grapheme ) {
            $weight += self::weight_of( $grapheme );
        }

        return $weight;
    }

    /**
     * Trim a label and cut it down to a maximum weight.
     *
     * @since  3.0.0
     * @param  mixed $text
     * @param  int   $max  The heaviest label to keep. Defaults to MAX_WEIGHT.
     * @return string
     */
    public static function sanitize( $text, $max = self::MAX_WEIGHT ) {
        if ( ! is_string( $text ) ) {
            return '';
        }

        $text = preg_replace( self::STRIP, '', $text );
        $text = preg_replace( self::TRIM, '', $text );

        if ( null === $text || '' === $text ) {
            return '';
        }

        $max    = max( 1, min( self::MAX_WEIGHT, (int) $max ) );
        $out    = '';
        $weight = 0;

        foreach ( self::graphemes( $text ) as $grapheme ) {
            $w = self::weight_of( $grapheme );

            if ( $weight + $w > $max ) {
                break;
            }

            $out    .= $grapheme;
            $weight += $w;
        }

        return $out;
    }

    /**
     * The font size a label is drawn at, in icon box units ( the box is 24
     * wide ). Heavier labels draw smaller so three columns still fit.
     *
     * @since  3.0.0
     * @param  string $text  A label already sanitized.
     * @param  mixed  $scale The shape's text scale.
     * @return float
     */
    public static function font_size( $text, $scale = 1 ) {
        $weight = self::weight( $text );
        $size   = isset( self::FONT_SIZE[ $weight ] ) ? self::FONT_SIZE[ $weight ] : self::FONT_SIZE[ self::MAX_WEIGHT ];

        /*
         * The shape's text scale ( Custom_Markers::TEXT_SCALE ). Anything
         * that is not a positive number means 1.
         */
        $scale = is_numeric( $scale ) && (float) $scale > 0 ? (float) $scale : 1;

        return round( (float) $size * $scale, 2 );
    }

    /**
     * The <text> element for a label, centred in the 24x24 icon box.
     *
     * The caller wraps it in the same transformed group an icon gets, so
     * ICON_SCALE and icon_size apply unchanged. It paints itself
     * ( fill + stroke="none" ) so it looks right whatever the group carries.
     *
     * @since  3.0.0
     * @param  mixed  $text  Raw label text, sanitized here.
     * @param  string $color The text color.
     * @return string SVG markup, or '' for an empty label.
     */
    public static function markup( $text, $color, $scale = 1 ) {
        $text = self::sanitize( $text );

        if ( '' === $text ) {
            return '';
        }

        $size = self::font_size( $text, $scale );

        // Half a cap height below the centre: see BASELINE_PERCENT.
        $dy = round( $size * self::BASELINE_PERCENT ) / 100;

        return '<text x="12" y="12" text-anchor="middle" dy="' . esc_attr( $dy ) . '"'
            . ' font-family="' . esc_attr( self::FONT_FAMILY ) . '" font-weight="700" font-size="' . esc_attr( $size ) . '"'
            . ' fill="' . esc_attr( $color ) . '" stroke="none">' . esc_html( $text ) . '</text>';
    }
}