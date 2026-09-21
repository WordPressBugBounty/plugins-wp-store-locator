<?php
/**
 * Convert the free-form 1.x opening hours into the dropdown format.
 *
 * 1.x stored a single free-text textarea per location; 2.x replaced it
 * with day => periods dropdowns, but never converted old values, so
 * locations upgraded from 1.x kept rendering their raw textarea text.
 *
 * This turns the common shapes of that free text into the array format.
 * It's deliberately all-or-nothing since the input is unstructured: a
 * schedule not fully understood is refused rather than partially
 * converted, since a half-converted schedule silently loses hours.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Hours;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Legacy_Converter {

    /**
     * The week days in output order, keyed by the array key used for storage.
     *
     * @since 3.0.0
     * @var array
     */
    private $week_days = [
        'monday'    => 'monday',
        'tuesday'   => 'tuesday',
        'wednesday' => 'wednesday',
        'thursday'  => 'thursday',
        'friday'    => 'friday',
        'saturday'  => 'saturday',
        'sunday'    => 'sunday'
    ];

    /**
     * Why the last parse() gave up. One of the REASON_* constants.
     *
     * @since 3.0.0
     * @var string
     */
    private $reason = '';

    /**
     * The value holds no opening times at all, just a note.
     *
     * @since 3.0.0
     * @var string
     */
    const REASON_PROSE = 'prose';

    /**
     * A time was written without a meridiem or minutes ( "9 - 5" ), so there is
     * no way to tell 9 AM from 9 PM.
     *
     * @since 3.0.0
     * @var string
     */
    const REASON_AMBIGUOUS = 'ambiguous';

    /**
     * Opening times were found, but so was text the dropdowns can't carry.
     *
     * @since 3.0.0
     * @var string
     */
    const REASON_EXTRA = 'extra';

    /**
     * A time that isn't on the clock, e.g. "25:00" or "9:75".
     *
     * @since 3.0.0
     * @var string
     */
    const REASON_TIME = 'time';

    /**
     * Convert a 1.x opening hours string to the dropdown format.
     *
     * @since  3.0.0
     * @param  mixed       $hours  The stored wpsl_hours value.
     * @param  string      $format The wanted output format, '12' or '24'. Defaults
     *                             to whichever format the input itself uses.
     * @return array|false         The day => periods array, or false when the
     *                             value isn't free-form text this can fully parse.
     *                             Call reason() to find out why.
     */
    public function parse( $hours, $format = '' ) {
        $this->reason = self::REASON_PROSE;

        // Anything already in the dropdown format is left alone.
        if ( ! is_string( $hours ) || ! trim( $hours ) ) {
            return false;
        }

        $text = $this->normalize( $hours );

        if ( ! $format ) {
            $format = preg_match( '/\d\s*(am|pm)\b/i', $text ) ? '12' : '24';
        }

        $parsed = [];
        $notes  = [];

        /*
         * Segments are separated by a line break, or by a full stop followed by
         * whitespace / the end of the string ( "monday: 9-5. tuesday: closed." ).
         * Full stops used as a time separator were turned into colons above, so
         * splitting here can't cut a time in half.
         */
        $segments = preg_split( '/[\r\n]+|\.(?=\s|$)/', $text );

        foreach ( $segments as $segment ) {
            $segment = trim( $segment, " \t,;" );

            if ( '' === $segment ) {
                continue;
            }

            $day = $this->match_day( $segment, $remainder );

            if ( ! $day ) {

                /*
                 * A line that doesn't start with a day name is a note rather
                 * than a schedule ( "Ring ahead, hours vary" ). The dropdown
                 * format has a 'special' field for exactly that, so the note is
                 * carried over there instead of the whole location being
                 * refused because of it.
                 */
                $notes[] = $segment;
                continue;
            }

            $periods = $this->match_periods( $remainder, $format );

            if ( false === $periods ) {
                return false;
            }

            /*
             * A day can be listed more than once ( a separate line per period ),
             * so merge instead of letting the last line win. An earlier period
             * list is never replaced by a later "closed".
             */
            if ( $periods ) {
                $parsed[ $day ] = array_merge( isset( $parsed[ $day ] ) ? $parsed[ $day ] : [], $periods );
            } elseif ( ! isset( $parsed[ $day ] ) ) {
                $parsed[ $day ] = [];
            }
        }

        /*
         * Nothing but notes. Leave the location alone: a value that holds only
         * a 'special' entry renders as nothing at all, because
         * Hours\Service::not_always_closed() only counts real week days - so
         * converting would hide text that is on the page today.
         */
        if ( ! $parsed ) {
            $this->reason = self::REASON_PROSE;

            return false;
        }

        // Always hand the days back in week order, whatever order they were typed in.
        $ordered = [];

        foreach ( $this->week_days as $day ) {
            if ( isset( $parsed[ $day ] ) ) {
                $ordered[ $day ] = $parsed[ $day ];
            }
        }

        if ( $notes ) {
            $ordered['special'] = implode( "\n", $notes );
        }

        $this->reason = '';

        return $ordered;
    }

    /**
     * Why the last parse() refused the value.
     *
     * @since  3.0.0
     * @return string One of the REASON_* constants, or an empty string when the
     *                last parse succeeded.
     */
    public function reason() {
        return $this->reason;
    }

    /**
     * Flatten the input into something the segment / period matching can work on.
     *
     * @since  3.0.0
     * @param  string $hours The raw 1.x value
     * @return string        The normalized text
     */
    private function normalize( $hours ) {
        // Line breaks that were stored as markup are real line breaks here.
        $text = preg_replace( '#<br\s*/?>#i', "\n", $hours );
        $text = wp_strip_all_tags( $text );

        // Non breaking spaces behave like ordinary ones.
        $text = str_replace( [ '&nbsp;', "\xc2\xa0" ], ' ', $text );

        // Every flavour of dash means "until".
        $text = str_replace( [ "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\x92", "\xe2\x80\x90", "\xe2\x88\x92" ], '-', $text );

        /*
         * "9.00" is a time, "closed." ends a segment. Turn the first into a
         * colon so the segment split below can treat every remaining full stop
         * as a separator.
         */
        $text = preg_replace( '/(?<=\d)\.(?=\d{2}(?!\d))/', ':', $text );

        return trim( $text );
    }

    /**
     * Pull the leading day name off a segment.
     *
     * @since  3.0.0
     * @param  string $segment   The segment, e.g. "Mon 9:00 AM - 5:00 PM"
     * @param  string $remainder Set to whatever follows the day name.
     * @return string|false      The week day key, or false when the segment
     *                           doesn't start with an unambiguous day.
     */
    private function match_day( $segment, &$remainder ) {
        $remainder = '';

        if ( ! preg_match( '/^(\p{L}{2,})\.?\s*[:\-]?\s*(.*)$/us', $segment, $match ) ) {
            return false;
        }

        $token = strtolower( $match[1] );

        /**
         * Day names to recognise, as name => week day key. Names are matched on
         * their start, so "mon", "monda" and "monday" all resolve. Sites that
         * entered their 1.x hours in another language can add their own here.
         *
         * @since 3.0.0
         * @param array $names The recognised day names
         */
        $names = apply_filters( 'wpsl_legacy_hours_day_names', array_merge( $this->week_days, [
            // Abbreviations that aren't a prefix of the full name.
            'weds'  => 'wednesday',
            'tues'  => 'tuesday',
            'thurs' => 'thursday',
            'thur'  => 'thursday'
        ] ) );

        $matched = [];

        foreach ( $names as $name => $day ) {
            if ( 0 === strpos( $name, $token ) || 0 === strpos( $token, $name ) ) {
                $matched[ $day ] = $day;
            }
        }

        // "s" could be saturday or sunday - refuse rather than guess.
        if ( 1 !== count( $matched ) ) {
            return false;
        }

        $remainder = trim( $match[2] );

        return reset( $matched );
    }

    /**
     * Turn the part of a segment after the day name into a list of periods.
     *
     * @since  3.0.0
     * @param  string     $remainder The text after the day name
     * @param  string     $format    '12' or '24'
     * @return array|false           The periods ( an empty array means closed ),
     *                               or false when the text isn't fully understood.
     */
    private function match_periods( $remainder, $format ) {
        $bare = trim( strtolower( $remainder ), " \t.,;:!" );

        /**
         * Words that mean the location is closed that day.
         *
         * @since 3.0.0
         * @param array $words The recognised words
         */
        $closed_words = apply_filters( 'wpsl_legacy_hours_closed_words', [
            'closed', 'close', 'gesloten', 'geschlossen', 'ferme', 'fermé', 'cerrado', 'chiuso'
        ] );

        // A day with nothing after it, or just "closed", is a closed day.
        if ( '' === $bare || in_array( $bare, $closed_words, true ) ) {
            return [];
        }

        $periods = [];
        $pattern = '/(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)\s*(?:-|to|till|until|t\/m)\s*(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)/i';

        $leftover = preg_replace_callback( $pattern, function( $match ) use ( &$periods, $format ) {
            $start = $this->format_time( $match[1], $format );
            $end   = $this->format_time( $match[2], $format );

            if ( false === $start || false === $end ) {
                // format_time() has already recorded whether it was ambiguous or impossible.
                $periods = false;

                return '';
            }

            if ( is_array( $periods ) ) {
                $periods[] = $start . ',' . $end;
            }

            return '';
        }, $remainder );

        if ( false === $periods ) {
            return false;
        }

        // A day name followed by something that holds no times at all.
        if ( ! $periods ) {
            $this->reason = self::REASON_PROSE;

            return false;
        }

        /*
         * Whatever is left once the periods are removed may only be separators.
         * Anything else ( "by appointment", a second phone number, a note about
         * public holidays ) means the segment holds information the dropdowns
         * can't carry, so the whole value is left as it is.
         */
        $leftover = preg_replace( '/\b(?:and|en|und|plus)\b/i', '', $leftover );
        $leftover = preg_replace( '#[\s,;&/\.\-]+#', '', $leftover );

        if ( '' !== $leftover ) {
            $this->reason = self::REASON_EXTRA;

            return false;
        }

        return $periods;
    }

    /**
     * Validate a single clock time and render it in the wanted format.
     *
     * @since  3.0.0
     * @param  string       $time   The time as it was typed, e.g. "9:00 AM"
     * @param  string       $format '12' or '24'
     * @return string|false         The formatted time, or false when it isn't
     *                              a time that can be resolved without guessing.
     */
    private function format_time( $time, $format ) {
        $time = trim( preg_replace( '/\s+/', ' ', $time ) );

        if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i', $time, $match ) ) {
            $this->reason = self::REASON_TIME;

            return false;
        }

        $hour     = (int) $match[1];
        $minute   = isset( $match[2] ) && '' !== $match[2] ? (int) $match[2] : 0;
        $meridiem = isset( $match[3] ) ? strtolower( $match[3] ) : '';

        if ( $minute > 59 ) {
            $this->reason = self::REASON_TIME;

            return false;
        }

        if ( $meridiem ) {
            if ( $hour < 1 || $hour > 12 ) {
                $this->reason = self::REASON_TIME;

                return false;
            }

            if ( 'am' === $meridiem ) {
                $hour = ( 12 === $hour ) ? 0 : $hour;
            } else {
                $hour = ( 12 === $hour ) ? 12 : $hour + 12;
            }
        } else {

            /*
             * "9 - 5" carries no clue whether that is 9 AM or 9 PM, so it is
             * refused. A written out "9:00" is read as 24 hour time, which is
             * the only way it can be meant.
             */
            if ( ! isset( $match[2] ) || '' === $match[2] ) {
                $this->reason = self::REASON_AMBIGUOUS;

                return false;
            }

            if ( $hour > 24 || ( 24 === $hour && 0 !== $minute ) ) {
                $this->reason = self::REASON_TIME;

                return false;
            }

            $hour = ( 24 === $hour ) ? 0 : $hour;
        }

        if ( '12' === (string) $format ) {
            $suffix = ( $hour < 12 ) ? 'AM' : 'PM';
            $hour12 = $hour % 12;

            return ( 0 === $hour12 ? 12 : $hour12 ) . ':' . sprintf( '%02d', $minute ) . ' ' . $suffix;
        }

        return sprintf( '%02d:%02d', $hour, $minute );
    }
}