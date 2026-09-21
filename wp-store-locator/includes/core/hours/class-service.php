<?php
/**
 * Handle anything opening hours related.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Hours;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Service {

    /**
     * The hour format set on the settings page,
     * but changing won't convert exsting location
     * data with the old format.
     *
     * @since 3.0.0
     */
    public $hours_format;

    /**
     * Holds the days of the week.
     *
     * @since 3.0.0
     * @var array
     */
    public $week_days;

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * The method to use for rendering hours (table or flexbox)
     *
     * @since 3.0.0
     * @var string
     */
    private $hours_render_method;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;

        // Delay loading weekdays until init hook when translations are available
        add_action( 'init', [ $this, 'setup_weekdays' ] );

        add_shortcode( 'wpsl_hours', [ $this, 'show_opening_hours' ] );
    }

    /**
     * Setup weekdays after translations are loaded
     * 
     * @since  3.0.0
     * @return void
     */
    public function setup_weekdays() {
        $this->week_days = wpsl_get_weekdays();
    }

    /**
     * Get the opening hours in the correct format.
     *
     * Either convert the hour values that are set through
     * a dropdown to a table or flexbox, or wrap the textarea input in a <p>.
     *
     * Note: The opening hours can only be set in the textarea format by users who upgraded from 1.x.
     *
     * @since  2.0.0
     * @param  array|string $hours The opening hours
     * @param  array        $atts  Shortcode attributes
     * @return string       $hours The formatted opening hours
     * @note New 3.x installs use the flexbox layout. Sites upgraded from a pre-3.0
     *       version keep the table layout so existing styling isn't broken. The
     *       wpsl_force_hours_table filter forces the table layout regardless.
     */
    public function prepare_output( $hours, $atts = [] ) {
        /**
         * If the opening hours are selected from the dropdown,
         * we generate a table to display them. Otherwise, we show
         * the data entered in the textarea.
         */
        if ( is_array( $hours ) ) {
            if ( ! $this->hours_render_method ) {
                $this->determine_hours_render_method();
            }

            $hours = $this->{$this->hours_render_method}( $hours, $atts );
        } else {
            $hours = wp_kses_post( wpautop( $hours ) );
        }

        return $hours;
    }

    /**
     * Determine which method to use for rendering hours.
     *
     * New installs use the flexbox layout. Sites upgraded from a pre-3.0
     * version keep the classic table layout so their existing styling
     * isn't broken. The wpsl_force_hours_table filter forces the table
     * layout regardless.
     *
     * @since 3.0.0
     * @return void
     */
    private function determine_hours_render_method() {
        $force_table = apply_filters( 'wpsl_force_hours_table', false );

        $upgraded_from = wpsl_upgraded_from();
        $is_pre_v3     = ( '' !== $upgraded_from && (int) $upgraded_from < 3 );

        if ( $force_table || $is_pre_v3 ) {
            $this->hours_render_method = 'create_table';
        } else {
            $this->hours_render_method = 'create_flexbox';
        }
    }

    /**
     * Return the used CSS class.
     *
     * @since  3.0.0
     * @param  array  $atts The passed shortcode attributes
     * @param  string $type The render method ( 'table' or 'flexbox' ). Default 'table'.
     * @return string The CSS rules
     */
    public function get_css_classes( $atts, $type = 'table' ) {
        $classes = [ 'wpsl-opening-hours' ];

        if ( $type === 'flexbox' ) {
            $classes[] = 'wpsl-flex-opening-hours';
        } else {
            // Marks the table as the intended legacy layout so the
            // table:not(.wpsl-legacy-hours) rule in the CSS keeps it visible.
            $classes[] = 'wpsl-legacy-hours';
        }

        return ' class="' . implode( ' ', $classes ) . '"';
    }

    /**
     * Create a flexbox holding the opening hours.
     *
     * @since  3.0.0
     * @param  array $hours    The opening hours
     * @param  array $atts     Shortcode attributes
     * @return array $response The opening hours structured in a flexbox and possible the current openings state ( open / closed ).
     */
    public function create_flexbox( $hours, $atts = [] ) {
        global $post;

        $aria_label      = '';
        $hour_flexbox    = '';
        $status_html     = '';
        $status_plain    = '';
        $visibility      = '';
        $current_status  = '';
        
        // Ensure week_days is initialized before using array_merge
        if ( ! $this->week_days ) {
            $this->setup_weekdays();
        }
        
        $opening_days = array_merge( $this->week_days, [ 'special' => 'Special' ] );

        /**
         * Merge instead of all-or-nothing: callers may pass only some keys
         * ( e.g. just store_id ), and every missing key must still get its
         * settings-based default.
         */
        $atts = wp_parse_args( $atts, [
            'hide_closed'    => (bool) $this->settings->get( 'ux', 'hide_closed_hours' ),
            'current_status' => (bool) $this->settings->get( 'ux', 'show_hour_status' ),
            'expand_status'  => (bool) $this->settings->get( 'ux', 'expand_hours' ),
            'store_id'       => 0,
        ] );

        $timezone      = wpsl_store_timezone( $atts['store_id'] );
        $datetime_info = $this->get_current_datetime( $hours, $timezone );
        $current_day   = $datetime_info['today'];
        /**
         * Make sure that we have actual opening hours,
         * and not every day is empty.
         */
        if ( $this->not_always_closed( $hours ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public query parameter for filtering, no state change
            $open_only = isset( $_GET['open_only'] ) ? sanitize_key( $_GET['open_only'] ) : false;

            if ( $atts['current_status'] || $open_only ) {
                $this->warn_on_missing_store_id( $atts, __METHOD__ );

                $current_status = $this->current_status( $hours, $timezone );
            }

            if ( ! $open_only ) {

                // Check if we need to show the current open / closed status.
                if ( $atts['current_status'] ) {
                    if ( $atts['expand_status'] ) {
                        // wpsl.js handles the expanding hours on [wpsl] and single store
                        // pages. Used on its own, [wpsl_hours] has to bring that handler itself.
                        if ( isset( $post->post_type ) && $post->post_type !== 'wpsl_stores' && has_shortcode( $post->post_content, 'wpsl_hours' ) ) {
                            $this->enqueue_inline_js();
                        }

                        $status     = '<a href="#" aria-expanded="false" class="wpsl-flex-hours">' . $current_status['message'] . '</a>';
                        $visibility = 'display:none';
                    } else {
                        $status = $current_status['message'];
                    }

                    $status_html = '<p class="wpsl-opening-hours-status wpsl-flex-hours">' . $status . '</p>' . "\r\n";
                    $aria_label = 'aria-label="' . esc_attr( $this->aria_label( $hours ) ) . '" ';
                }

                if ( ! $atts['current_status'] && $this->settings->get( 'ux', 'hours' ) || ( $this->settings->get( 'ux', 'hours' ) && $this->settings->get( 'ux', 'expand_hours' ) ) && ! $visibility || $this->settings->get( 'ux', 'more_info' ) ) {
                    $visibility = 'display:flex';
                }

                // The settings above shape the search results; [wpsl_hours] is there to show the hours.
                if ( ! $visibility && ! empty( $atts['always_list'] ) ) {
                    $visibility = 'display:flex';
                }

                if ( $visibility ) {
                    $hour_flexbox .= '<dl' . $this->get_css_classes( $atts, 'flexbox' ) . ' '. $aria_label . 'style="' . $visibility . '">' . "\r\n";

                    foreach ( $opening_days as $index => $day ) {
                        $i = 0;

                        if ( isset( $hours[$index] ) && is_array( $hours[$index] ) ) {
                            $hours[$index] = $this->readable_periods( $hours[$index] );
                            $hour_count    = count( $hours[$index] );
                        } else {
                            $hour_count = 0;
                        }

                        // If we need to hide days that are set to closed then skip them.
                        if ( $atts['hide_closed'] && ! $hour_count ) {
                            continue;
                        }

                        if ( isset( $hours[$index] ) && $index != 'special' ) {

                            // Make sure the hours of the current day are highlighted with css.
                            if ( $index == $current_day ) {
                                $current_day_class = 'wpsl-current-day';
                            } else {
                                $current_day_class = '';
                            }

                            $hour_flexbox .= "\t\t" . '<div class="wpsl-opening-hours-row ' . $current_day_class . '">' . "\r\n";
                            $hour_flexbox .= "\t\t\t" . '<dt>' . apply_filters( 'wpsl_hours_day', esc_html( $day ) ) . '</dt>' . "\r\n";

                            // If we have opening hours we show them, otherwise just show 'Closed'.
                            if ( $hour_count > 0 ) {
                                $multiple_ranges_class = $hour_count > 1 ? 'class="wpsl-multiple-time-ranges"' : '';

                                $hour_flexbox .= "\t\t\t" . '<dd ' . $multiple_ranges_class . ' >';

                                // $datetime_info was resolved once at the top, in the store's timezone.
                                $is_today = ( $datetime_info['today'] === $index );

                                while ( $i < $hour_count ) {
                                    $hour = explode( ',', $hours[$index][$i] );

                                    // Skip malformed ranges that don't have both a start and end time.
                                    if ( count( $hour ) < 2 ) {
                                        $i++;
                                        continue;
                                    }

                                    // Check if this time range is currently active
                                    $range_class = 'wpsl-time-range';

                                    if ( $is_today && $this->is_time_range_active( $hour[0], $hour[1], $datetime_info['format'], $datetime_info['now'] ) ) {
                                        $range_class .= ' wpsl-current-time-range';
                                    }
                                    
                                    $hour_flexbox .= '<span class="' . esc_attr( $range_class ) . '"><time datetime="' . esc_attr( $this->convert_to_24h_format( $hour[0] ) ) . '">' . esc_html( $hour[0] ) . '</time> - <time datetime="' . esc_attr( $this->convert_to_24h_format( $hour[1] ) ) . '">' . esc_html( $hour[1] ) . '</time></span>';

                                    $i++;
                                }

                                $hour_flexbox .= '</dd>' . "\r\n";
                            } else {
                                $msg = esc_html__( 'Closed', 'wp-store-locator' );
                                $hour_flexbox .= "\t\t\t" . '<dd>' . esc_html( $msg ) . '</dd>' . "\r\n";
                            }

                            $hour_flexbox .= "\t\t" . '</div>' . "\r\n";
                        }
                    }

                    $hour_flexbox .= '</dl>';

                    if ( isset( $hours['special'] ) && $hours['special'] ) {
                        $hour_flexbox .= '<p class="wpsl-special-opening-hours">' . nl2br( esc_html( $hours['special'] ) ) . '</p>' . "\r\n";
                    }
                }
            }

            if ( isset( $current_status['message'] ) && $current_status['message'] ) {
                $status_plain = '<p class="wpsl-opening-hours-status">' . $current_status['message'] . '</p>' . "\r\n";
            }

            $response = [
                'table'        => $status_html . $hour_flexbox,
                'hours_table'  => $hour_flexbox,
                'status_html'  => $status_html,
                'status_plain' => $status_plain,
                'status'       => isset( $current_status['status'] ) ? $current_status['status'] : ''
            ];

            return apply_filters( 'wpsl_hours_flexbox', $response );
        }
    }

    /**
     * Create a table holding the opening hours.
     *
     * @since 2.0.0
     * @param array  $hours The opening hours
     * @param array  $atts  Shortcode attributes
     * @return array $response The opening hours structured in a table and possible the current openings state ( open / closed ).
     */
    public function create_table( $hours, $atts = [] ) {
        global $post;

        $aria_label     = '';
        $hour_table     = '';
        $status_html    = '';
        $status_plain   = '';
        $visibility     = '';
        $current_status = '';
        
        // Ensure week_days is initialized before using array_merge
        if ( ! $this->week_days ) {
            $this->setup_weekdays();
        }
        
        $opening_days = array_merge( $this->week_days, [ 'special' => 'Special' ] );

        $atts = wp_parse_args( $atts, [
            'hide_closed'    => (bool) $this->settings->get( 'ux', 'hide_closed_hours' ),
            'expand_status'  => (bool) $this->settings->get( 'ux', 'expand_hours' ),
            'current_status' => (bool) $this->settings->get( 'ux', 'show_hour_status' ),
            'store_id'       => 0,
        ] );

        $timezone = wpsl_store_timezone( $atts['store_id'] );

        /**
         * Make sure that we have actual opening hours,
         * and not every day is empty.
         */
        if ( $this->not_always_closed( $hours ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public query parameter for filtering, no state change
            $open_only = isset( $_GET['open_only'] ) ? sanitize_key( $_GET['open_only'] ) : false;

            if ( $atts['current_status'] || $open_only ) {
                $this->warn_on_missing_store_id( $atts, __METHOD__ );

                $current_status = $this->current_status( $hours, $timezone );
            }

            if ( ! $open_only ) {

                // Check if we need to show the current open / closed status.
                if ( $atts['current_status'] ) {
                    if ( $atts['expand_status'] ) {

                        // wpsl.js handles the expanding hours on [wpsl] and single store
                        // pages. Used on its own, [wpsl_hours] has to bring that handler itself.
                        if ( isset( $post->post_type ) && $post->post_type !== 'wpsl_stores' && has_shortcode( $post->post_content, 'wpsl_hours' ) ) {
                            $this->enqueue_inline_js();
                        }

                        $status     = '<a href="#" aria-expanded="false" class="">' . $current_status['message'] . '</a>';
                        $visibility = 'display:none';
                    } else {
                        $status = $current_status['message'];
                    }

                    $status_html = '<p class="wpsl-opening-hours-status">' . $status . '</p>' . "\r\n";
                    $aria_label = 'aria-label="' . esc_attr( $this->aria_label( $hours ) ) . '" ';
                }

                if ( ! $atts['current_status'] && $this->settings->get( 'ux','hours' ) || ( $this->settings->get( 'ux','hours' ) && $this->settings->get( 'ux','expand_hours' ) ) && ! $visibility || $this->settings->get( 'ux','more_info' ) ) {
                    $visibility = 'display:table';
                }

                // The settings above shape the search results; [wpsl_hours] is there to show the hours.
                if ( ! $visibility && ! empty( $atts['always_list'] ) ) {
                    $visibility = 'display:table';
                }

                if ( $visibility ) {
                    $hour_table .= '<table role="presentation" ' . $this->get_css_classes( $atts ) . ' '. $aria_label . 'style="' . $visibility . '">' . "\r\n";

                    foreach ( $opening_days as $index => $day ) {
                        $i = 0;

                        if ( isset( $hours[$index] ) && is_array( $hours[$index] ) ) {
                            $hours[$index] = $this->readable_periods( $hours[$index] );
                            $hour_count    = count( $hours[$index] );
                        } else {
                            $hour_count = 0;
                        }

                        // If we need to hide days that are set to closed then skip them.
                        if ( $atts['hide_closed'] && ! $hour_count ) {
                            continue;
                        }

                        if ( isset( $hours[$index] ) && $index != 'special' ) {
                            $hour_table .= "\t\t" . '<tr>' . "\r\n";
                            $hour_table .= "\t\t\t" . '<td>' . apply_filters( 'wpsl_hours_day', esc_html( $day ) ) . '</td>' . "\r\n";

                            // If we have opening hours we show them, otherwise just show 'Closed'.
                            if ( $hour_count > 0 ) {
                                $hour_table .= "\t\t\t" . '<td>';

                                while ( $i < $hour_count ) {
                                    $hour = explode( ',', $hours[$index][$i] );

                                    // Skip malformed ranges that don't have both a start and end time.
                                    if ( count( $hour ) < 2 ) {
                                        $i++;
                                        continue;
                                    }

                                    $start_time_24h = $this->convert_to_24h_format( $hour[0] );
                                    $end_time_24h = $this->convert_to_24h_format( $hour[1] );
                                    $hour_table .= '<span class="wpsl-time-range"><time datetime="' . esc_attr( $start_time_24h ) . '">' . esc_html( $hour[0] ) . '</time> – <time datetime="' . esc_attr( $end_time_24h ) . '">' . esc_html( $hour[1] ) . '</time></span>';

                                    $i++;
                                }

                                $hour_table .= '</td>' . "\r\n";
                            } else {
                                if ( is_array( $hours[$index] ) && ! $hours[$index] ) {
                                    $msg = esc_html__( 'Closed', 'wp-store-locator' );
                                } else {
                                    $msg = $hours[$index] ;
                                }

                                $hour_table .= "\t\t\t" . '<td>' . esc_html( $msg ) . '</td>' . "\r\n";
                            }

                            $hour_table .= "\t\t" . '</tr>' . "\r\n";
                        }
                    }

                    $hour_table .= '</table>';

                    if ( isset( $hours['special'] ) && $hours['special'] ) {
                        $hour_table .= '<p class="wpsl-special-opening-hours">' . nl2br( esc_html( $hours['special'] ) ) . '</p>' . "\r\n";
                    }
                }
            }

            if ( isset( $current_status['message'] ) && $current_status['message'] ) {
                $status_plain = '<p class="wpsl-opening-hours-status">' . $current_status['message'] . '</p>' . "\r\n";
            }

            $response = [
                'table'        => $status_html . $hour_table,
                'hours_table'  => $hour_table,
                'status_html'  => $status_html,
                'status_plain' => $status_plain,
                'status'       => isset( $current_status['status'] ) ? $current_status['status'] : ''
            ];

            return apply_filters( 'wpsl_hours_table', $response );
        }
    }

    /**
     * Check if we have an opening day that has valid time values,
     * if not they are all set to closed.
     *
     * @since  2.0.0
     * @param  array   $opening_hours The opening hours
     * @return boolean True if a day is found with valid opening hours
     */
    public function not_always_closed( $opening_hours ) {
        foreach ( $opening_hours as $day => $hours ) {
            // Skip special hours field
            if ( $day === 'special' ) {
                continue;
            }

            // Valid opening hours are stored as arrays with time ranges (e.g., ['9:00 AM,5:00 PM'])
            // Text values like "Closed" or "monday: closed." are not valid opening hours
            if ( is_array( $hours ) && ! empty( $hours ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add the inline JS code to make the hours expand.
     *
     * We do it this way because users can use the wpsl_hours
     * shortcode anywhere, and the wpsl.js is only loaded on the
     * page where the [wpsl] shortcode is used and wpsl_stores cpt pages.
     *
     * @since  3.0.0
     * @return  void
     */
    public function enqueue_inline_js() {
        /*
         * A footer handle of its own: the shortcode renders in the content,
         * after jQuery has already been printed in the head, and inline code
         * added to a printed script is never output.
         */
        if ( wp_script_is( 'wpsl-hours-toggle', 'enqueued' ) ) {
            return;
        }

        wp_register_script( 'wpsl-hours-toggle', false, [ 'jquery' ], WPSL_VERSION_NUM, true );
        wp_enqueue_script( 'wpsl-hours-toggle' );

        /*
         * The same toggle as bindExpandingHours() in wpsl-event-handlers.js,
         * and like that one it unbinds before binding: with both on a page,
         * whichever runs last is the only handler, so a click never toggles twice.
         */
        wp_add_inline_script(
            'wpsl-hours-toggle',
            'jQuery( function( $ ) { $( document ).off( "click", ".wpsl-opening-hours-status a" ).on( "click", ".wpsl-opening-hours-status a", function() { var $link = $( this ); $link.parents( "p" ).next( "table, dl.wpsl-flex-opening-hours" ).toggle(); $link.attr( "aria-expanded", $link.attr( "aria-expanded" ) === "false" ? "true" : "false" ).toggleClass( "wpsl-active-details" ); return false; } ); } );'
        );
    }

    /**
     * Report a status being calculated without knowing which location it is for.
     *
     * The status is only meaningful in the location's own timezone ( read
     * by wpsl_store_timezone() from its post meta ), so a store_id is
     * required. Without one the site timezone is used, which silently
     * reports the wrong status for locations in another zone.
     *
     * @since  3.0.0
     * @param  array  $atts   The resolved attributes
     * @param  string $method The calling method
     * @return void
     */
    private function warn_on_missing_store_id( $atts, $method ) {
        if ( ! empty( $atts['store_id'] ) ) {
            return;
        }

        _doing_it_wrong(
            esc_html( $method ),
            esc_html__( 'The open / closed status is calculated in the timezone of the location itself, so a store_id attribute is required. Without one the site timezone is used, which silently reports the wrong status for any location in another timezone.', 'wp-store-locator' ),
            '3.0.0'
        );
    }

    /**
     * Get current DateTime with timezone and format detection
     *
     * @since  3.0.0
     * @param  array              $hours    The opening hours for format detection
     * @param  \DateTimeZone|null $timezone The timezone to calculate "now" in.
     *                                      Defaults to the site timezone.
     * @return array Array containing DateTime objects and format
     */
    private function get_current_datetime( $hours = [], $timezone = null ) {
        /**
         * We do this because the user can change the hour format
         * on the WPSL settings page, but still have locations in
         * the previous used format.
         */
        if ( preg_match( '/ AM| PM/i', json_encode( $hours ) ) ) {
            $format = 'g:i A';
        } else {
            $format = 'H:i';
        }

        if ( ! $timezone instanceof \DateTimeZone ) {
            $timezone = wp_timezone();
        }

        /**
         * $now carries the real calendar date (not just a time-of-day), so that
         * overnight periods (e.g. "5:00 PM,2:00 AM") can be compared against
         * start/end times that are anchored to an explicit date -- see
         * parse_period(). Without a real date attached, "now" and any dated
         * start/end times can't be compared consistently across midnight.
         */
        $date = new \DateTime( 'now', $timezone );
        $now  = \DateTime::createFromFormat( 'Y-m-d ' . $format, $date->format( 'Y-m-d' ) . ' ' . $date->format( $format ) );

        /**
         * format( 'l' ) always returns the English day name, matching the
         * English array keys the hours are stored under. wp_date( 'l' )
         * returned a translated name, which never matched on non-English
         * sites.
         */
        $today = strtolower( $date->format( 'l' ) );

        return [
            'date'   => $date,
            'now'    => $now,
            'today'  => $today,
            'format' => $format
        ];
    }

    /**
     * Check if current time falls within a specific time range
     *
     * @since  3.0.0
     * @param  string $start_time Start time (e.g., "9:00 AM")
     * @param  string $end_time   End time (e.g., "5:00 PM")
     * @param  string $format     Time format
     * @param  \DateTime $now     Current time
     * @return bool
     */
    private function is_time_range_active( $start_time, $end_time, $format, $now ) {
        $startTime = \DateTime::createFromFormat( $format, $start_time );
        $endTime   = \DateTime::createFromFormat( $format, $end_time );

        // Open from the opening minute, closed from the closing minute.
        return ( $startTime <= $now ) && ( $now < $endTime );
    }

    /**
     * Parse a "start,end" opening-hour period into DateTime objects anchored
     * to a given calendar date. Overnight periods (e.g. "5:00 PM,2:00 AM")
     * have an end time that isn't after the start time once both are placed
     * on the same date -- in that case the end date is rolled forward a day,
     * so comparisons against a real-dated "now" (see get_current_datetime())
     * correctly span midnight.
     *
     * @since  3.0.0
     * @param  string $period   The period, e.g. "9:00 AM,5:00 PM"
     * @param  string $format   Time format ('g:i A' or 'H:i')
     * @param  string $date_ymd The date ( 'Y-m-d' ) the start time falls on
     * @return array|null       [ 'start' => \DateTime, 'end' => \DateTime ], or null if malformed
     */
    private function parse_period( $period, $format, $date_ymd ) {
        $sections = explode( ',', $period );

        if ( count( $sections ) < 2 ) {
            return null;
        }

        $start = $this->parse_time( trim( $sections[0] ), $format, $date_ymd );
        $end   = $this->parse_time( trim( $sections[1] ), $format, $date_ymd );

        if ( ! $start || ! $end ) {
            return null;
        }

        if ( $end <= $start ) {
            $end->modify( '+1 day' );
        }

        return [ 'start' => $start, 'end' => $end ];
    }

    /**
     * A day's periods without the ones the status can't read either, so the
     * hours table never shows a day as open that the status calls closed.
     *
     * @since  3.0.0
     * @param  array $periods The day's periods, e.g. [ "9:00 AM,5:00 PM" ]
     * @return array The readable periods, reindexed
     */
    private function readable_periods( $periods ) {
        return array_values( array_filter( (array) $periods, function( $period ) {
            return is_string( $period ) && null !== $this->parse_period( $period, 'H:i', '2000-01-01' );
        } ) );
    }

    /**
     * Parse a single opening / closing time onto a given calendar date.
     *
     * A location can hold both hour formats at once ( changing the format
     * on the settings page doesn't convert existing data ), so the format
     * detected for the location can't parse every day. Fall back to the
     * other one rather than returning nothing; createFromFormat() is
     * strict, so trying both is safe.
     *
     * @since  3.0.0
     * @param  string $time     A single time, e.g. "9:00 AM" or "09:00"
     * @param  string $format   The format detected for the location ('g:i A' or 'H:i')
     * @param  string $date_ymd The date ( 'Y-m-d' ) to anchor the time to
     * @return \DateTime|false  The parsed time, or false when neither format fits
     */
    private function parse_time( $time, $format, $date_ymd ) {
        $formats = ( 'H:i' === $format ) ? [ 'H:i', 'g:i A' ] : [ 'g:i A', 'H:i' ];

        /*
         * 24:00 is a common way to write a midnight close. createFromFormat()
         * does read it as 00:00 the next day, but only with a warning, and
         * warnings are refused below. So it is read as that here.
         */
        $midnight = ( '24:00' === $time );

        if ( $midnight ) {
            $time = '00:00';
        }

        foreach ( $formats as $candidate ) {
            $parsed = \DateTime::createFromFormat( 'Y-m-d ' . $candidate, $date_ymd . ' ' . $time );

            /*
             * createFromFormat() accepts out-of-range values and rolls them
             * over with only a warning: 25:00 becomes 01:00 the next day and
             * 09:60 becomes 10:00. A typo in imported hours must not turn
             * into an opening period, so a warning counts as a failure.
             */
            $errors = \DateTime::getLastErrors();

            if ( ! $parsed || ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
                continue;
            }

            if ( $midnight ) {
                $parsed->modify( '+1 day' );
            }

            return $parsed;
        }

        return false;
    }

    /**
     * See if the location is currently open or closed
     *
     * @since  3.0.0
     * @param  array              $hours    The opening hours
     * @param  \DateTimeZone|null $timezone Timezone to evaluate the status in; null = site timezone.
     * @return array $status
     */
    public function current_status( $hours, $timezone = null ) {
        if ( ! $this->week_days ) {
            $this->setup_weekdays();
        }

        $datetime_info = $this->get_current_datetime( $hours, $timezone );
        $now    = $datetime_info['now'];
        $today  = $datetime_info['today'];
        $format = $datetime_info['format'];

        $today_ymd     = $datetime_info['date']->format( 'Y-m-d' );
        $yesterday_key = strtolower( ( clone $datetime_info['date'] )->modify( '-1 day' )->format( 'l' ) );
        $yesterday_ymd = ( clone $datetime_info['date'] )->modify( '-1 day' )->format( 'Y-m-d' );

        // Whether one of today's own periods still has to start.
        $upcoming_today = false;

        foreach ( $hours as $day => $daily_hours ) {
            if ( $today == $day ) {
                $status = [
                    'status'  => 'closed',
                    'message' => ''
                ];

                /**
                 * Overnight hours (e.g. "5:00 PM,2:00 AM") close after midnight,
                 * on what is by then a new calendar day. Before evaluating
                 * today's own periods, check whether yesterday's last period is
                 * still ongoing.
                 */
                $carry_over = null;

                if ( isset( $hours[ $yesterday_key ] ) && is_array( $hours[ $yesterday_key ] ) && ! empty( $hours[ $yesterday_key ] ) ) {
                    $yesterday_periods  = array_values( $hours[ $yesterday_key ] );
                    $last_yesterday_period = end( $yesterday_periods );
                    $parsed_carry_over  = $this->parse_period( $last_yesterday_period, $format, $yesterday_ymd );

                    if ( $parsed_carry_over && $now >= $parsed_carry_over['start'] && $now < $parsed_carry_over['end'] ) {
                        $carry_over = $parsed_carry_over;
                    }
                }

                if ( $carry_over ) {
                    $endTime  = $carry_over['end'];
                    $interval = $now->diff( $endTime );

                    $status['status'] = 'open';

                    if ( $interval->format('%h%' ) == 0 ) {
                        $closes_in = $interval->format('%i minutes' );
                        $closes_in_attr = 'PT' . $this->interval_minutes( $interval ) . 'M';

                        if ( is_array( $daily_hours ) && ! empty( $daily_hours ) ) {
                            $reopen = explode( ',', $daily_hours[0] );

                            /* translators: 1: opening span tag, 2: time element with minutes, 3: closing span tag, 4: opening span tag for next opening, 5: the reopening time in a time element, 6: closing span tag */
                            $status['message'] = sprintf( esc_html__( '%1$sCloses in %2$s %3$s %4$sReopens %5$s%6$s', 'wp-store-locator' ), '<span class="wpsl-closing-soon">', '<time datetime="' . $closes_in_attr . '">' . $closes_in . '</time>', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time"><time datetime="' . esc_attr( $this->convert_to_24h_format( $reopen[0] ) ) . '">' . esc_html( $reopen[0] ) . '</time></span>', '</span>' );
                        } else {
                            /* translators: 1: opening span tag, 2: time element with minutes, 3: closing span tag, 4: opening span tag for next opening, 5: opening span tag for the time, 6: next opening day and time, 7: closing span tags */
                            $status['message'] = sprintf( esc_html__( '%1$sCloses in %2$s %3$s %4$s Opens %5$s%6$s%7$s', 'wp-store-locator' ), '<span class="wpsl-closing-soon">', '<time datetime="' . $closes_in_attr . '">' . $closes_in . '</time>', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', $this->open_next( $today, $hours ), '</span></span>' );
                        }
                    } else {
                        $yesterday_sections = explode( ',', $last_yesterday_period );

                        /* translators: 1: opening span tag, 2: closing span tag, 3: time element with closing time */
                        $status['message'] = sprintf( esc_html__( '%1$sOpen%2$s Closes %3$s', 'wp-store-locator' ), '<span class="wpsl-currently-open">', '</span>', '<time datetime="' . esc_attr( $this->convert_to_24h_format( $yesterday_sections[1] ) ) . '">' . esc_html( $yesterday_sections[1] ) . '</time>' );
                    }
                } elseif ( is_array( $daily_hours ) && ! empty( $daily_hours ) ) {
                    $index = 0;

                    foreach ( $daily_hours as $hour ) {
                        $sections  = explode( ',', $hour );

                        // Skip malformed ranges that don't have both a start and end time.
                        if ( count( $sections ) < 2 ) {
                            $index++;
                            continue;
                        }

                        $parsed_period = $this->parse_period( $hour, $format, $today_ymd );

                        // Neither hour format could read this period; treat it like the malformed case above.
                        if ( ! $parsed_period ) {
                            $index++;
                            continue;
                        }

                        $startTime     = $parsed_period['start'];
                        $endTime       = $parsed_period['end'];

                        // Inclusive opening, exclusive closing, as status_at() has it.
                        if ( ( $startTime <= $now ) && ( $now < $endTime ) ) {
                            $status['status'] = 'open';
                            $interval = $now->diff( $endTime );

                            if ( $interval->format('%h%' ) == 0 ) {
                                $closes_in = $interval->format('%i minutes' );
                                $closes_in_attr = 'PT' . $this->interval_minutes( $interval ) . 'M';

                                if ( isset( $daily_hours[ $index + 1 ] ) ) {
                                    $reopen = explode( ',', $daily_hours[ $index + 1 ] );

                                    /* translators: 1: opening span tag, 2: time element with minutes, 3: closing span tag, 4: opening span tag for next opening, 5: the reopening time in a time element, 6: closing span tag */
                                    $status['message'] = sprintf( esc_html__( '%1$sCloses in %2$s %3$s %4$sReopens %5$s%6$s', 'wp-store-locator' ), '<span class="wpsl-closing-soon">', '<time datetime="' . $closes_in_attr . '">' . $closes_in . '</time>', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time"><time datetime="' . esc_attr( $this->convert_to_24h_format( $reopen[0] ) ) . '">' . esc_html( $reopen[0] ) . '</time></span>', '</span>' );
                                } else {
                                    /* translators: 1: opening span tag, 2: time element with minutes, 3: closing span tag, 4: opening span tag for next opening, 5: opening span tag for the time, 6: next opening day and time, 7: closing span tags */
                                    $status['message'] = sprintf( esc_html__( '%1$sCloses in %2$s %3$s %4$s Opens %5$s%6$s%7$s', 'wp-store-locator' ), '<span class="wpsl-closing-soon">', '<time datetime="' . $closes_in_attr . '">' . $closes_in . '</time>', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', $this->open_next( $day, $hours ), '</span></span>' );
                                }

                            } else {
                                $hour = explode( ',', $daily_hours[$index] );
                                /* translators: 1: opening span tag, 2: closing span tag, 3: time element with closing time */
                                $status['message'] = sprintf( esc_html__( '%1$sOpen%2$s Closes %3$s', 'wp-store-locator' ), '<span class="wpsl-currently-open">', '</span>', '<time datetime="' . esc_attr( $this->convert_to_24h_format( $hour[1] ) ) . '">' . esc_html( $hour[1] ) . '</time>' );
                            }

                            break;
                        } else {
                            $interval = $now->diff( $startTime );

                            if ( ! $interval->invert ) {
                                $upcoming_today = true;
                            }

                            if ( ( $interval->format('%h%' ) == 0 ) && ! $interval->invert ) {
                                $opens_in = $interval->format('%i minutes' );
                                $opens_in_attr = 'PT' . $this->interval_minutes( $interval ) . 'M';

                                /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: time element with minutes, 5: closing span tag */
                                $status['message'] = sprintf( esc_html__( '%1$sClosed%2$s %3$sOpens in %4$s%5$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time"><time datetime="' . $opens_in_attr . '">' . esc_html( $opens_in ) . '</time></span>', '</span>' );

                                break;
                            } else {

                                // See if the opening time is actually today.
                                if ( ! $interval->invert ) {
                                    /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: opening time span, 5: time element, 6: closing span tags */
                                    $status['message'] = sprintf( esc_html__( '%1$sClosed%2$s %3$sOpens %4$s%5$s%6$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', ' <time>' . esc_html( $sections[0] ) . '</time>', '</span></span>' );
                                } else {
                                    /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: opening time span, 5: next opening time, 6: closing span tags */
                                    $status['message'] = sprintf( esc_html__( '%1$sClosed%2$s %3$sOpens %4$s%5$s%6$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', $this->open_next( $day, $hours ), '</span></span>' );
                                }
                            }
                        }

                        $index++;
                    }

                    /**
                     * Every period today was skipped as unreadable, so the loop
                     * above never set a message and the status paragraph would
                     * render empty. A day with no usable hours is the same thing
                     * to a visitor as a day with none at all, so say so and point
                     * at the next opening.
                     */
                    if ( '' === $status['message'] ) {
                        /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: opening time span, 5: next opening time and closing span tags */
                        $status['message'] = sprintf( esc_html__( '%1$sClosed%2$s %3$sOpens %4$s%5$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', $this->open_next( $today, $hours ), '</span></span>' );
                    }
                } else {
                    /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: opening time span, 5: next opening time and closing span tags */
                    $status['message'] = sprintf( esc_html__( '%1$sClosed%2$s %3$sOpens %4$s%5$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', $this->open_next( $today, $hours ), '</span></span>' );
                }
            }
        }

        if ( ! isset( $status ) ) {
                $status = [
                'status'  => 'closed',
                /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: opening time span, 5: next opening time and closing span tags */
                'message' => sprintf( esc_html__( '%1$sClosed.%2$s %3$sOpens %4$s%5$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time">', $this->open_next( $today, $hours ), '</span></span>' )
            ];
        }

        /**
         * Opening carry-over: the countdown above only sees periods stored
         * under today's day key, so an opening shortly after midnight (
         * tomorrow's first period ) never got one. Mirror of the closing
         * carry-over at the top of the loop.
         */
        if ( 'closed' === $status['status'] && ! $upcoming_today ) {
            $opens_in_minutes = $this->tomorrow_opening_countdown( $hours, $datetime_info );

            if ( null !== $opens_in_minutes ) {
                $opens_in      = $opens_in_minutes . ' minutes';
                $opens_in_attr = 'PT' . $opens_in_minutes . 'M';

                /* translators: 1: opening span tag, 2: closing span tag, 3: opening span tag for next opening, 4: time element with minutes, 5: closing span tag */
                $status['message'] = sprintf( esc_html__( '%1$sClosed%2$s %3$sOpens in %4$s%5$s', 'wp-store-locator' ), '<span class="wpsl-currently-closed">', '</span>', '<span class="wpsl-opens-next">', '<span class="wpsl-opens-time"><time datetime="' . $opens_in_attr . '">' . esc_html( $opens_in ) . '</time></span>', '</span>' );
            }
        }

        return $status;
    }

    /**
     * Whether a store is open at a given moment.
     *
     * current_status() renders the live badge, so it only knows "now" and
     * returns markup. This answers for any moment and returns data. It checks,
     * in order:
     *
     * 1. The location status: permanently closed, or temporarily closed with
     *    no reopen date or one still ahead.
     * 2. Dated special hours, supplied through wpsl_special_hours_for_date.
     * 3. The weekly hours.
     *
     * Steps 2 and 3 also look at the previous day, whose last period can run
     * past midnight.
     *
     * It never writes: a reopen date that has passed is only read as open.
     * Updating the stored status is Location_Status's job, not a question's.
     *
     * @since  3.0.0
     * @param  int                            $store_id The store ID.
     * @param  \DateTimeInterface|string|null $when     The moment to check. A string is read in the
     *                                                  store's timezone, a DateTime is converted to
     *                                                  it. Null means now.
     * @return array|\WP_Error {
     *     @type bool|null   $open     Whether the store is open. Null when its hours can't be read.
     *     @type string      $reason   permanently_closed, temporarily_closed, special_hours,
     *                                 weekly_hours or unverified.
     *     @type array|null  $period   The period it's open in, as [ 'open' => 'H:i', 'close' => 'H:i' ].
     *     @type string|null $reopens  The reopen date ( Y-m-d ) of a temporarily closed store.
     *     @type string      $when     The moment checked, ISO 8601 in the store's timezone.
     *     @type string      $timezone The store's timezone.
     * }
     */
    public function status_at( $store_id, $when = null ) {
        $store_id = (int) $store_id;

        if ( ! $store_id || 'wpsl_stores' !== get_post_type( $store_id ) ) {
            return new \WP_Error( 'wpsl_invalid_store', __( 'Invalid store ID.', 'wp-store-locator' ) );
        }

        $timezone = wpsl_store_timezone( $store_id );

        if ( $when instanceof \DateTimeInterface ) {
            $when = new \DateTimeImmutable( '@' . $when->getTimestamp() );
        } else {
            try {
                $when = new \DateTimeImmutable( null === $when ? 'now' : (string) $when, $timezone );
            } catch ( \Exception $e ) {
                return new \WP_Error( 'wpsl_invalid_time', __( 'Invalid date or time.', 'wp-store-locator' ) );
            }
        }

        $when = $when->setTimezone( $timezone );

        $result = [
            'open'     => null,
            'reason'   => 'unverified',
            'period'   => null,
            'reopens'  => null,
            'when'     => $when->format( 'c' ),
            'timezone' => $timezone->getName(),
        ];

        $location_status = get_post_meta( $store_id, 'wpsl_location_status', true );

        if ( 'permanently_closed' === $location_status ) {
            $result['open']   = false;
            $result['reason'] = 'permanently_closed';

            return $result;
        }

        if ( 'temporarily_closed' === $location_status ) {
            $reopens = get_post_meta( $store_id, 'wpsl_reopens', true );

            /**
             * Location_Status::process() stores the reopen date with
             * strtotime(), which WordPress runs in UTC, so the timestamp is
             * midnight UTC of the picked date ( the metabox reads it back with
             * gmdate() for the same reason ). Compare calendar dates in the
             * store's own timezone, so it reopens at its own midnight.
             */
            $reopen_ymd = ( ! empty( $reopens ) && is_numeric( $reopens ) ) ? gmdate( 'Y-m-d', (int) $reopens ) : null;

            if ( null === $reopen_ymd || $when->format( 'Y-m-d' ) < $reopen_ymd ) {
                $result['open']    = false;
                $result['reason']  = 'temporarily_closed';
                $result['reopens'] = $reopen_ymd;

                return $result;
            }
        }

        /**
         * The weekly hours can only be read when all seven days are there.
         * The legacy textarea format is free text, and imports have left
         * stores with a single-day array such as [ 'monday' => [] ], which
         * says nothing about the other six days. A day saved as a string
         * ( "Closed" ) is read as closed, as current_status() does.
         */
        $weekly = get_post_meta( $store_id, 'wpsl_hours', true );

        /**
         * Filters the weekly hours status_at() checks.
         *
         * For an add-on that changes wpsl_hours as it's read, e.g. to show
         * today's special hours in the live status, to hand back the stored
         * week. Dated exceptions belong in wpsl_special_hours_for_date.
         *
         * @since 3.0.0
         * @param mixed $weekly   The wpsl_hours meta as read.
         * @param int   $store_id The store ID.
         */
        $weekly = apply_filters( 'wpsl_status_at_weekly_hours', $weekly, $store_id );

        if ( ! is_array( $weekly ) || array_diff( [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ], array_keys( $weekly ) ) ) {
            $weekly = null;
        }

        $today     = $this->periods_on( $store_id, $when, $weekly );
        $yesterday = $this->periods_on( $store_id, $when->modify( '-1 day' ), $weekly );

        // Minute precision, built the same way parse_period() builds its times.
        $now = \DateTime::createFromFormat( 'Y-m-d H:i', $when->format( 'Y-m-d H:i' ) );

        foreach ( [ $today, $yesterday ] as $day ) {
            if ( null === $day ) {
                continue;
            }

            foreach ( $day['periods'] as $period ) {
                if ( $period['start'] <= $now && $now < $period['end'] ) {
                    $result['open']   = true;
                    $result['reason'] = $day['source'];
                    $result['period'] = [
                        'open'  => $period['start']->format( 'H:i' ),
                        'close' => $period['end']->format( 'H:i' ),
                    ];

                    return $result;
                }
            }
        }

        if ( null !== $today ) {
            $result['open']   = false;
            $result['reason'] = $today['source'];
        }

        return $result;
    }

    /**
     * The opening periods of a store on one calendar date: its special hours
     * for that date when there are any, otherwise its weekly hours.
     *
     * @since  3.0.0
     * @param  int                $store_id The store ID.
     * @param  \DateTimeImmutable $date     The date, in the store's timezone.
     * @param  array|null         $weekly   The weekly hours, or null when they can't be read.
     * @return array|null [ 'source' => 'special_hours'|'weekly_hours', 'periods' => [ [ 'start' => \DateTime, 'end' => \DateTime ] ] ],
     *                    or null when neither special nor weekly hours are available.
     */
    private function periods_on( $store_id, \DateTimeImmutable $date, $weekly ) {
        $ymd = $date->format( 'Y-m-d' );

        /**
         * Dated exceptions to the weekly hours, such as holidays.
         *
         * Core's own special hours field is free text, so core supplies none.
         * An add-on that stores dated exceptions returns the periods for
         * $date: "open,close" strings in the same format as the weekly hours,
         * an empty array when the store is closed all day, or null to use the
         * weekly hours.
         *
         * @since 3.0.0
         * @param array|null $periods  Null.
         * @param int        $store_id The store ID.
         * @param string     $date     The date ( Y-m-d ), in the store's timezone.
         */
        $special = apply_filters( 'wpsl_special_hours_for_date', null, $store_id, $ymd );

        if ( is_array( $special ) ) {
            $source = 'special_hours';
            $ranges = $special;
        } elseif ( null !== $weekly ) {
            $day    = strtolower( $date->format( 'l' ) );
            $source = 'weekly_hours';
            $ranges = ( isset( $weekly[ $day ] ) && is_array( $weekly[ $day ] ) ) ? $weekly[ $day ] : [];
        } else {
            return null;
        }

        $periods = [];

        foreach ( $ranges as $range ) {
            $parsed = $this->parse_period( (string) $range, 'H:i', $ymd );

            if ( $parsed ) {
                $periods[] = $parsed;
            }
        }

        return [ 'source' => $source, 'periods' => $periods ];
    }

    /**
     * If tomorrow's first opening is less than an hour away, return the
     * minutes-until-opening text for it, otherwise an empty string.
     *
     * @since  3.0.0
     * @param  array  $hours         The opening hours
     * @param  array  $datetime_info The resolved date / time info ( see get_current_datetime() )
     * @return int|null Whole minutes until tomorrow's first opening, or null when there's no imminent opening
     */
    private function tomorrow_opening_countdown( $hours, $datetime_info ) {
        $tomorrow_key = strtolower( ( clone $datetime_info['date'] )->modify( '+1 day' )->format( 'l' ) );
        $tomorrow_ymd = ( clone $datetime_info['date'] )->modify( '+1 day' )->format( 'Y-m-d' );

        if ( ! isset( $hours[ $tomorrow_key ] ) || ! is_array( $hours[ $tomorrow_key ] ) || empty( $hours[ $tomorrow_key ] ) ) {
            return null;
        }

        $periods = array_values( $hours[ $tomorrow_key ] );
        $parsed  = $this->parse_period( $periods[0], $datetime_info['format'], $tomorrow_ymd );

        if ( ! $parsed ) {
            return null;
        }

        $interval = $datetime_info['now']->diff( $parsed['start'] );

        // Unlike a same-day diff, this one can span a full day, so the day
        // count has to be checked next to the hour component.
        if ( $interval->invert || $interval->days > 0 || '0' !== $interval->format( '%h' ) ) {
            return null;
        }

        return $this->interval_minutes( $interval );
    }

    /**
     * Find the first day the location is open
     * again starting from the current day.
     *
     * @since  3.0.0
     * @param  string $offset The current day
     * @param  array  $hours  The opening hours
     * @return string The first day the location is open with the openings time
     */
    public function open_next( $offset, $hours ) {
        $next   = '';
        $passed = '';
        // Pure day-name arithmetic; gmdate() keeps it locale- and timezone-independent.
        $offset = strtolower( gmdate( 'l', strtotime( $offset . ' +1 day' ) ) );

        /**
         * Find nearest available opening hours
         * based on the provided offset ( tomorrow ).
         */
        foreach ( $hours as $day => $daily_hours ) {
            if ( ! $passed ) {
                $passed = $offset == $day;
            }

            if ( $passed && ! empty( $daily_hours ) && is_array( $daily_hours ) ) {
                $next = $this->get_next_hours( $hours, $day );

                break;
            }
        }

        /**
         * If no opening hours where found during the remaning
         * week days, then start again from the begining of week.
         */
        if ( ! $next ) {
            foreach ( $hours as $day => $daily_hours ) {
                if ( ! empty( $daily_hours ) && is_array( $daily_hours ) ) {
                    $next = $this->get_next_hours( $hours, $day );

                    break;
                }
            }
        }

        return $next;
    }

    /**
     * Get the hours from the next opening day.
     *
     * @since  3.0.0
     * @param  array  $hours      The opening hours
     * @param  string $day        The next day the location opens
     * @return string $next_hours
     */
    function get_next_hours( $hours, $day ) {
        $open = explode( ',', $hours[$day][0] );
        
        // Allow filtering to use abbreviated day names (3 letters) instead of full names
        $use_abbreviated_days = apply_filters( 'wpsl_use_short_day_names', true );
        
        if ( $use_abbreviated_days ) {
            $day_name = substr( $this->week_days[$day], 0, 3 );
        } else {
            $day_name = $this->week_days[$day];
        }
        
        $next_hours = $day_name . ' ' . '<time datetime="' . esc_attr( $this->convert_to_24h_format( $open[0] ) ) . '">' . esc_html( $open[0] ) . '</time>';

        return apply_filters( 'wpsl_next_hours', $next_hours );
    }

    /**
     * Handle the [wpsl_hours] shortcode.
     *
     * @since  2.0.0
     * @param  array       $atts   Shortcode attributes
     * @return void|string $output The opening hours
     */
    public function show_opening_hours( $atts ) {
        global $post;

        $wpsl_editor_settings = $this->settings->get_group( 'editor' );
        $wpsl_ux_settings     = $this->settings->get_group( 'ux' );

        $output = '';

        // If the hours are set to hidden on the settings page, then don't continue.
        if ( $wpsl_editor_settings['hide_hours'] ) {
            return;
        }

        /*
         * Covers pages the asset detector can't see (theme templates, page
         * builders, widgets); prints in the footer when it lands this late.
         * After the hide_hours guard, so a site rendering no hours doesn't
         * load their stylesheets.
         */
        wpsl_enqueue_frontend_assets();

        $atts = wpsl_bool_check( shortcode_atts( apply_filters( 'wpsl_hour_shortcode_defaults', [
            'id'             => '',
            'hide_closed'    => (bool) $wpsl_ux_settings['hide_closed_hours'],
            'current_status' => (bool) $this->settings->get( 'ux', 'show_hour_status' ),
            'expand_status'  => (bool) $this->settings->get( 'ux', 'show_hour_status' )
                                && (bool) $this->settings->get( 'ux', 'expand_hours' )
        ] ), $atts ) );

        if ( get_post_type() == 'wpsl_stores' ) {
            if ( empty( $atts['id'] ) ) {
                if ( isset( $post->ID ) ) {
                    $atts['id'] = $post->ID;
                } else {
                    return;
                }
            }
        } else if ( empty( $atts['id'] ) ) {
            if ( is_user_logged_in() ) {
                /* translators: 1: opening link tag, 2: closing link tag */
                $output .= '<p>' . sprintf( esc_html__( 'If you use the [wpsl_hours] shortcode outside a store page, then you need to set the %1$sID attribute%2$s.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/shortcodes/#opening-hours">', '</a>' ) . '</p>';
            }

            return $output;
        }

        // The status must be calculated in this store's timezone.
        $atts['store_id'] = $atts['id'];

        // Set here, not as a shortcode attribute: this output always includes the hours.
        $atts['always_list'] = true;

        $opening_hours = get_post_meta( $atts['id'], 'wpsl_hours' );

        if ( $opening_hours ) {
            $hours = $this->prepare_output( $opening_hours[0], $atts );

            if ( is_array( $hours ) && ! empty( $hours['table'] ) ) {
                $output .= $hours['table'];
            } elseif ( is_string( $hours ) && $hours ) {
                // Legacy 1.x textarea format is returned as a ready-made string.
                $output .= $hours;
            }
        }

        return $output;
    }

    /**
     * Create the value for the aria-label
     * attribute on the hours table.
     *
     * @since  3.0.0
     * @param  array  $hours The opening hours
     * @return string $label The aria label txt
     */
    public function aria_label( $hours ) {
        $label = '';

        foreach ( $hours as $day => $daily_hours ) {
            $parts = '';
            $sections = [];

            if ( is_array( $daily_hours ) ) {
                $daily_hours = $this->readable_periods( $daily_hours );
            }

            if ( is_array( $daily_hours ) && ! empty( $daily_hours ) ) {

                foreach ( $daily_hours as $hour ) {
                    $sections[] = str_replace( ',', esc_html__( ' to ', 'wp-store-locator' ), $hour );
                }

                $parts .= implode( ', ', $sections );
            } else {
                if ( is_array( $daily_hours ) && empty( $daily_hours ) ) {
                    $daily_hours = esc_html__( 'Closed', 'wp-store-locator' );
                }

                $parts .= $daily_hours;
            }

            /**
             * If the $day value isn't a normal week day,
             * then it has to be the 'Special' day.
             */
            if ( array_key_exists( $day, $this->week_days ) ) {
                $day = $this->week_days[$day];
            } else {
                $day = ucfirst( $day );
            }

            $label .= $day . ', ' . $parts . '; ';
        }

        return trim( $label );
    }

    /**
     * If the hours are set through a dropdown ( 2.x and later ),
     * then check if the set values are valid date formats.
     *
     * @since  3.0.0
     * @param  array $args location data
     * @return array $args location data
     */
    public function maybe_validate_hours( $args ) {
        $wpsl_settings = $this->settings->get_group( 'editor' );

        if ( isset( $args['hours'] ) && ! empty( $args['hours'] ) ) {
            if ( is_array( $args['hours'] ) ) {
                $args['hours'] = $this->validate( $args['hours'] );
            } else if ( is_string( $args['hours'] ) && ! $this->hours_came_from_the_textarea() ) {

                /*
                 * The textarea keeps the opening hours as the text that was typed;
                 * that is the whole point of the 1.x input. Converting that text to
                 * the dropdown array here handed an array to the 'textarea' branch
                 * of Api\Service::set_metadata(), where stripslashes() turned it
                 * into an empty string ( PHP < 8 ) and wiped the location's hours on
                 * every save.
                 *
                 * The REST API and the CSV import have no textarea, so a string
                 * arriving from those is still meant to become the dropdown format.
                 */
                $args['hours'] = $this->convert_hours_to_array( $args['hours'] );
            }
        } else {

            /**
             * If no openings hour are provided, then we
             * use the defaults from the settings page.
             *
             * Only users who upgraded from WPSL 1.x
             * can have the textarea option.
             */
            if ( $wpsl_settings['hour_input'] == 'dropdown' ) {
                $args['hours'] = $wpsl_settings['hours']['dropdown'];
            } else {
                $args['hours'] = $wpsl_settings['hours']['textarea'];
            }
        }

        return $args;
    }

    /**
     * Whether the opening hours being saved were typed into the store editor's
     * textarea rather than posted by the API or the CSV import.
     *
     * The two inputs are told apart by what they submit: the dropdowns post
     * wpsl[hours] as an array of per-day fields, the textarea posts it as a
     * single string.
     *
     * @since  3.0.0
     * @return bool
     */
    private function hours_came_from_the_textarea() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the caller ( Metaboxes::save_post ).
        return isset( $_POST['wpsl']['hours'] ) && is_string( $_POST['wpsl']['hours'] );
    }

    /**
     * Convert the opening hours from a string into an array.
     *
     * @param  string       $opening_hours            The opening hours in a string
     * @return string|array $formatted_openings_hours Empty or the formatted openings hours.
     */
    public function convert_hours_to_array( $opening_hours ) {
        $formatted_openings_hours = [];
        $opening_sections         = explode( '.', $opening_hours );

        if ( stripos( $opening_hours, 'am' ) !== false || stripos( $opening_hours, 'pm' ) !== false ) {
            $format = '12';
        } else {
            $format = '24';
        }

        foreach ( $opening_sections as $opening_section ) {
            $hour_parts      = [];
            $opening_section = explode( ':', $opening_section, 2 );

            if ( count( $opening_section ) == 2 ) {
                if ( trim( $opening_section[1] ) != 'closed' ) {
                    $preg_params = [
                        'pattern' => [
                            '!\s+!',
                            '/\s*-\s*/'
                        ],
                        'replacement' => [
                            ' ',
                            ','
                        ]
                    ];

                    /**
                     * Replace multiple spaces with a single space, make sure there are no
                     * spaces before and after the -, replace it with a , and remove any start / end spaces.
                     */
                    $hour_sections = preg_replace( $preg_params['pattern'], $preg_params['replacement'], trim( $opening_section[1] ) );

                    /**
                     * Check if the hours are formated in 12 / 24hr format, and try to split them accordingly.
                     *
                     * The 24hrs format is split at the space between the hours ( 10:00–17:00 19:00-21:00 ).
                     * The 12hrs format needs to split at the space after the hour ( 9:00 AM-5:00 PM ).
                     */
                    if ( $format == '12' ) {
                        $chunks     = array_chunk( explode( ' ', $hour_sections ), 3 );
                        $hour_parts = array_map( [ $this, 'join_hour_chunks' ], $chunks );
                    } else {
                        $hour_parts = explode( ' ', $hour_sections );
                    }
                }

                $formatted_openings_hours[ trim( strtolower( $opening_section[0] ) ) ] = $hour_parts;
            }
        }

        return $formatted_openings_hours;
    }

    /**
     * Join the hour parts.
     *
     * @param  array $chunks One set of hours split by three spaces.
     * @return string The hour part joined together like 9:00 AM,5:00 PM.
     */
    public function join_hour_chunks( $chunks ) {
        return implode( ' ', $chunks );
    }

    /**
     * Loop through the opening hours submited through the admin editor
     * and make sure the passed hours are valid.
     *
     * @param  string $store_hours   The store hours
     * @return array  $opening_hours The formatted opening hours
     */
    public function validate( $store_hours = '' ) {
        $opening_hours = [];
        $week_days     = wpsl_get_weekdays();

        /*
         * Fall back to the opening hours from the editor page or the add/edit
         * store page. Only when the caller passed none: the settings page saves
         * its defaults through this branch, but a REST / WP-CLI / importer save
         * has to keep the hours it handed in.
         */
        if ( empty( $store_hours ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified in calling function, sanitization happens in validate_format()
            if ( isset( $_POST['wpsl_editor']['hours'] ) ) {
                $store_hours = $_POST['wpsl_editor']['hours'];
            } else if ( isset( $_POST['wpsl']['hours'] ) ) {
                $store_hours = $_POST['wpsl']['hours'];
            }
        }

        foreach ( $week_days as $day => $value ) {
            $i       = 0;
            $periods = [];

            if ( isset( $store_hours[$day . '_open'] ) && $store_hours[$day . '_open'] ) {
                foreach ( $store_hours[$day . '_open'] as $opening_hour ) {
                    $hours     = $this->validate_format( $store_hours[$day.'_open'][$i] ) . ',' . $this->validate_format( $store_hours[$day.'_closed'][$i] );
                    $periods[] = $hours;
                    $i++;
                }
            } else if ( isset( $store_hours[$day] ) && $store_hours[$day] ) {
                $periods = sanitize_text_field( $store_hours[$day] );
            }

            // Don't include days where no hours / data was entered.
            if ( $periods ) {
                $opening_hours[$day] = $periods;
            }
        }

        if ( isset( $store_hours['special'] ) && $store_hours['special'] ) {
            $opening_hours['special'] = sanitize_textarea_field( $store_hours['special'] );
        }

        return $opening_hours;
    }

    /**
     * Validate the 12 or 24 hr time format.
     *
     * @param  string $hour The opening hour part
     * @return string $hour The validated opening hour part
     */
    public function validate_format( $hour ) {
        $wpsl_settings = $this->settings->get_group( 'editor' );

        /**
         * Check if we're validating from the store editor page where the user
         * can change the hour format, otherwise use the global setting.
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling function
        if ( isset( $_POST['wpsl_editor']['hour_format'] ) ) {
            $hour_format = ( $_POST['wpsl_editor']['hour_format'] == 12 ) ? 12 : 24;
        } else {
            $hour_format = $wpsl_settings['hour_format'];
        }

        if ( $hour_format == 12 ) {
            $format = 'g:i A';
        } else {
            $format = 'H:i';
        }

        /**
         * If the passed value doesn't match the recreated hour,
         * then set it to 00:00 to make sure the dropdowns can render correctly.
         */
        $time = strtotime( $hour );

        if ( $time === false ) {
            $hour = gmdate( $format, strtotime( '00:00' ) );
        } else {
            $hour = gmdate( $format, $time );
        }

        return $hour;
    }

    /**
     * Convert time to 24-hour format for datetime attributes.
     *
     * @param  string $time The time string to convert
     * @return string       The time in 24-hour format (HH:MM)
     */
    private function convert_to_24h_format( $time ) {
        $timestamp = strtotime( $time );
        
        if ( $timestamp === false ) {
            return '00:00';
        }
        
        return gmdate( 'H:i', $timestamp );
    }

    /**
     * Total whole minutes in an interval.
     *
     * Used for the ISO 8601 duration in the datetime attribute of the
     * countdown <time> elements, e.g. 30 minutes becomes PT30M.
     *
     * @since  3.0.0
     * @param  \DateInterval $interval An interval produced by DateTime::diff().
     * @return int Whole minutes, never negative.
     */
    private function interval_minutes( $interval ) {

        // %a is only meaningful on a diff() interval; anything else casts to 0.
        $days = (int) $interval->format( '%a' );

        return max( 0, ( $days * 24 * 60 ) + ( (int) $interval->format( '%h' ) * 60 ) + (int) $interval->format( '%i' ) );
    }
}