<?php
/**
 * Handle the different location statuses ( open / permantely / temporary closed ).
 *
 * When it's set to temporarily closed and a reopen date is also provided,
 * then we schedule an event to automatically reopen it.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Location_Status {

    /**
     * Listen for the scheduled reopen.
     *
     * The event fires from WP-Cron, which isn't an admin request, so this is
     * registered on every request from init_services() rather than by the
     * admin-only location_status service. That service used to add the
     * listener from its constructor, which never ran under cron, so a
     * scheduled reopen did nothing.
     *
     * @since  3.0.0
     * @return void
     */
    public static function register_hooks() {
        add_action( 'wpsl_reopen_location', [ __CLASS__, 'reopen_location' ] );
    }

    /**
     * Reopen a location without going through the container, where the
     * location_status service only exists on admin requests.
     *
     * @since  3.0.0
     * @param  int $post_id
     * @return void
     */
    public static function reopen_location( $post_id ) {
        ( new self() )->reopen( $post_id );
    }

    /**
     * Holds the unixtime stamp when the store reopens,
     * or nothing if it's open / permantely closed.
     *
     * @since 3.0.0
     */
    public $reopens;

    /**
     * Update the status of the current location.
     *
     * Either open / temporarily closed / permanently closed
     *
     * The metabox always posts a status, but the API / import path may send a
     * partial set of fields. Without a status there's nothing to update, so we
     * leave the stored one alone. An explicit empty value still clears it.
     *
     * @since 3.0.0
     * @param array $args
     * @param int   $post_id
     */
    public function process( $args, $post_id ) {
        if ( ! isset( $args['location_status'] ) ) {
            return;
        }

        if ( $args['location_status'] == 'temporarily_closed' && ! empty( $args['reopens'] ) ) {
            $this->reopens = strtotime( $args['reopens'] );
        } else {
            $this->reopens = '';
        }

        $exclude_closed = ( $args['location_status'] == 'permanently_closed' && ! empty( $args['exclude_closed'] ) ) ? 1 : 0;

        update_post_meta( $post_id, 'wpsl_location_status', sanitize_text_field( $args['location_status'] ) );
        update_post_meta( $post_id, 'wpsl_reopens', $this->reopens );
        update_post_meta( $post_id, 'wpsl_exclude_closed', $exclude_closed );

        /**
         * If a date is provided, and it's set to temporarily
         * closed, then schedule the automatic reopening.
         *
         * Otherwise check if we need to remove any existing reopening events.
         */
        if ( $args['location_status'] == 'temporarily_closed' && is_numeric( $this->reopens ) ) {
            $this->schedule_reopen( $post_id );
        } else {
            $this->maybe_clear_scheduler( $post_id );
        }
    }

    /**
     * Schedule the automatic reopening of a location.
     *
     * @since 3.0.0
     * @param int   $post_id
     */
    private function schedule_reopen( $post_id ) {
        $this->maybe_clear_scheduler( $post_id );

        if ( $this->reopens <= time() ) {
            $this->reopen( $post_id );
        } else {
            wp_schedule_single_event( $this->reopens, 'wpsl_reopen_location', [ $post_id ] );
        }
    }

    /**
     * If a scheduled event exists of the
     * passed location ID, then we remove it.
     *
     * @since 3.0.0
     * @param int   $post_id
     */
    private function maybe_clear_scheduler( $post_id ) {
        $next = wp_next_scheduled( 'wpsl_reopen_location', [ $post_id ] );

        if ( $next ) {
            wp_clear_scheduled_hook( 'wpsl_reopen_location', [ $post_id ] );
        }
    }

    /**
     * Set the metadata for the location to open.
     *
     * @since 3.0.0
     * @param int   $post_id
     */
    public function reopen( $post_id ) {
        update_post_meta( $post_id, 'wpsl_location_status', 'open' );
        update_post_meta( $post_id, 'wpsl_reopens', '' );
        update_post_meta( $post_id, 'wpsl_exclude_closed', 0 );

        // Use shared utility method for flushing transients
        $system_utils = wpsl_get_service( 'system_utils' );
        $system_utils->flush_autoload_transients();
    }
}