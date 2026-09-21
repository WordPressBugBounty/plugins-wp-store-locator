<?php
/**
 * Convert 1.x opening hours to the dropdown format.
 *
 * 1.x stored hours as free-form text in a single textarea. 2.x replaced that
 * with a day => periods array but never converted old values, so those
 * locations have no open/closed status, no "open now" filter, and no structured
 * data.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Hours\Legacy_Converter;

class Hours_Converter {

    /**
     * How many locations are handled per AJAX request.
     *
     * @since 3.0.0
     * @var int
     */
    const BATCH_SIZE = 50;

    /**
     * The meta key the original 1.x text is kept under, so a conversion can
     * always be traced back to what the location started with.
     *
     * @since 3.0.0
     * @var string
     */
    const BACKUP_META_KEY = '_wpsl_hours_1x';

    /**
     * Reported when a location's opening hours went missing between listing it
     * and opening its row.
     *
     * @since 3.0.0
     * @var string
     */
    const REASON_MISSING = 'missing';

    /**
     * The settings instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * The parser turning the 1.x text into the dropdown format.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Hours\Legacy_Converter
     */
    private $converter;

    /**
     * Cached answer for has_legacy_hours(), null until first asked.
     *
     * @since 3.0.0
     * @var bool|null
     */
    private $has_legacy_hours = null;

    /**
     * Constructor.
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager $settings The settings handler instance.
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings  = $settings;
        $this->converter = new Legacy_Converter();

        add_action( 'wp_ajax_wpsl_convert_hours', [ $this, 'handle_request' ] );
    }

    /**
     * Whether there is anything for this tool to do.
     *
     * Cached: the settings page asks twice per request (the Tools row and
     * the dialog template), and the answer can't change in between.
     *
     * @since  3.0.0
     * @return bool True if at least one location still holds 1.x opening hours.
     */
    public function has_legacy_hours() {
        if ( null === $this->has_legacy_hours ) {
            $this->has_legacy_hours = (bool) $this->collect_legacy_hours( 1 );
        }

        return $this->has_legacy_hours;
    }

    /**
     * Handle the AJAX call from the Tools tab.
     *
     * @since  3.0.0
     * @return void
     */
    public function handle_request() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsl-convert-hours' ) ) {
            wp_send_json_error( [ 'message' => __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) ] );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'wp-store-locator' ) ] );
        }

        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'list';

        switch ( $mode ) {
            case 'convert':
                wp_send_json_success( $this->convert_batch() );
                break;
            case 'detail':
                $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

                wp_send_json_success( $this->detail( $id ) );
                break;
            default:
                wp_send_json_success( $this->list_locations() );
        }
    }

    /**
     * List every location still holding 1.x opening hours.
     *
     * @since  3.0.0
     * @return array The counts and one row per location
     */
    public function list_locations() {
        $rows      = $this->collect_legacy_hours();
        $addresses = $this->collect_addresses( wp_list_pluck( $rows, 'id' ) );
        $format    = $this->hour_format();

        $result = [
            'total'       => count( $rows ),
            'convertible' => 0,
            'skipped'     => 0,
            'locations'   => []
        ];

        foreach ( $rows as $index => $row ) {
            $parsed      = $this->converter->parse( $row['hours'], $format );
            $convertible = ( false !== $parsed );

            if ( $convertible ) {
                $result['convertible']++;
            } else {
                $result['skipped']++;
            }

            $result['locations'][] = [
                'number'      => $index + 1,
                'id'          => $row['id'],
                'store'       => get_the_title( $row['id'] ),
                'address'     => isset( $addresses[ $row['id'] ] ) ? $addresses[ $row['id'] ] : '',
                'convertible' => $convertible,
                'reason'      => $convertible ? '' : $this->converter->reason()
            ];
        }

        return $result;
    }

    /**
     * The before / after schedule for a single location.
     *
     * @since  3.0.0
     * @param  int   $store_id The store ID
     * @return array           The current text and what it would become
     */
    public function detail( $store_id ) {
        $hours = get_post_meta( $store_id, 'wpsl_hours', true );

        if ( ! is_string( $hours ) || '' === $hours ) {
            return [
                'before' => '',
                'after'  => '',
                'reason' => self::REASON_MISSING
            ];
        }

        $parsed = $this->converter->parse( $hours, $this->hour_format() );

        return [
            'before' => $hours,
            'after'  => ( false === $parsed ) ? '' : $this->describe( $parsed ),
            'reason' => ( false === $parsed ) ? $this->converter->reason() : ''
        ];
    }

    /**
     * Collect a one line address per location, in a single query.
     *
     * @since  3.0.0
     * @param  array $ids The store IDs
     * @return array      Store ID => address line
     */
    private function collect_addresses( $ids ) {
        global $wpdb;

        if ( ! $ids ) {
            return [];
        }

        $ids = array_map( 'absint', $ids );

        $in = implode( ',', $ids );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off admin tool; the id list is built from absint() above.
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE post_id IN ( {$in} )
             AND meta_key IN ( 'wpsl_address', 'wpsl_city' )"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $parts = [];

        foreach ( $rows as $row ) {
            $parts[ (int) $row->post_id ][ $row->meta_key ] = $row->meta_value;
        }

        $addresses = [];

        foreach ( $parts as $id => $meta ) {
            $line = array_filter( [
                isset( $meta['wpsl_address'] ) ? $meta['wpsl_address'] : '',
                isset( $meta['wpsl_city'] ) ? $meta['wpsl_city'] : ''
            ] );

            $addresses[ $id ] = implode( ', ', $line );
        }

        return $addresses;
    }

    /**
     * Convert one batch of locations.
     *
     * @since  3.0.0
     * @return array The progress of this batch
     */
    public function convert_batch() {
        $skipped_before = isset( $_POST['skipped'] ) ? absint( wp_unslash( $_POST['skipped'] ) ) : 0;

        /*
         * Locations that can't be converted stay in the result set, so ask for
         * a batch beyond the ones already known to be unreadable. Without the
         * offset the same unconvertible locations would come back forever.
         */
        $rows = $this->collect_legacy_hours( self::BATCH_SIZE + $skipped_before );
        $rows = array_slice( $rows, $skipped_before );

        $format    = $this->hour_format();
        $converted = 0;
        $skipped   = $skipped_before;

        foreach ( $rows as $row ) {
            $parsed = $this->converter->parse( $row['hours'], $format );

            if ( false === $parsed ) {
                $skipped++;
                continue;
            }

            // Keep the original text around.
            if ( ! metadata_exists( 'post', $row['id'], self::BACKUP_META_KEY ) ) {
                update_post_meta( $row['id'], self::BACKUP_META_KEY, $row['hours'] );
            }

            update_post_meta( $row['id'], 'wpsl_hours', $parsed );

            $converted++;
        }

        $remaining = count( $this->collect_legacy_hours() ) - $skipped;
        $done      = $remaining < 1;

        $switched = false;

        /*
         * The editor input type is a single global setting, so it can only
         * switch to dropdowns once no location holds text. Switching early
         * would show those locations default hours and overwrite their text
         * on the next save.
         */
        if ( $done && ! $skipped ) {
            $switched = $this->switch_to_dropdown();
        }

        return [
            'converted'  => $converted,
            'skipped'    => $skipped,
            'remaining'  => max( 0, $remaining ),
            'done'       => $done,
            'switched'   => $switched
        ];
    }

    /**
     * Collect the locations that still hold 1.x opening hours.
     *
     * @since  3.0.0
     * @param  int   $limit Stop after this many locations, 0 for all of them.
     * @return array        Rows of [ 'id' => int, 'hours' => string ]
     */
    private function collect_legacy_hours( $limit = 0 ) {
        global $wpdb;

        $sql = "SELECT pm.post_id, pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = 'wpsl_hours'
                AND pm.meta_value != ''
                AND pm.meta_value NOT LIKE 'a:%'
                AND p.post_type = 'wpsl_stores'
                AND p.post_status NOT IN ( 'auto-draft', 'trash' )
                ORDER BY pm.post_id ASC";

        if ( $limit ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- absint() leaves nothing to inject.
            $sql .= ' LIMIT ' . absint( $limit );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- One-off admin tool, deliberately uncached; the only variable is the absint() above.
        $rows = $wpdb->get_results( $sql );

        $legacy = [];

        foreach ( $rows as $row ) {
            if ( is_serialized( $row->meta_value ) ) {
                continue;
            }

            $legacy[] = [
                'id'    => (int) $row->post_id,
                'hours' => $row->meta_value
            ];
        }

        return $legacy;
    }

    /**
     * The hour format the converted values should use.
     *
     * @since  3.0.0
     * @return string '12' or '24'
     */
    private function hour_format() {
        return ( 24 == $this->settings->get( 'editor', 'hour_format' ) ) ? '24' : '12';
    }

    /**
     * Move the store editor over to the opening hour dropdowns.
     *
     * @since  3.0.0
     * @return bool True if the setting was changed
     */
    private function switch_to_dropdown() {
        $editor = $this->settings->get_group( 'editor' );

        if ( 'dropdown' === $editor['hour_input'] ) {
            return false;
        }

        $editor['hour_input'] = 'dropdown';

        return (bool) $this->settings->update( 'editor', $editor );
    }

    /**
     * Render a parsed week as readable text for the preview.
     *
     * @since  3.0.0
     * @param  array  $parsed The day => periods array
     * @return string         One day per line
     */
    private function describe( $parsed ) {
        $week_days = wpsl_get_weekdays();
        $lines     = [];

        foreach ( $parsed as $day => $periods ) {
            $label = isset( $week_days[ $day ] ) ? $week_days[ $day ] : ucfirst( $day );

            /*
             * The 'special' entry holds a note rather than a list of periods,
             * and a week day can hold free text too ( the per-day text input in
             * the store editor ). Print those as they are.
             */
            if ( is_string( $periods ) ) {
                $lines[] = $label . ': ' . $periods;
                continue;
            }

            if ( ! $periods ) {
                $lines[] = $label . ': ' . __( 'Closed', 'wp-store-locator' );
                continue;
            }

            $readable = [];

            foreach ( $periods as $period ) {
                $readable[] = str_replace( ',', ' - ', $period );
            }

            $lines[] = $label . ': ' . implode( ', ', $readable );
        }

        return implode( "\n", $lines );
    }
}