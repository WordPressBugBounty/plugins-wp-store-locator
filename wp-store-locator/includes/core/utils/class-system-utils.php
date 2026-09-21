<?php
/**
 * System utility functions.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class System_Utils {

    /**
     * See if HTTPS is used.
     *
     * @since  3.0.0
     * @return bool
     */
    public function ssl_active() {
        return ( is_ssl() && 'https' === substr( get_home_url(), 0, 5 ) );
    }

    /**
     * Check if we're in a WordPress admin context.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_admin_context() {
        return is_admin() && ! wp_doing_ajax();
    }

    /**
     * Check if we're in an AJAX request.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_ajax_request() {
        return wp_doing_ajax();
    }

    /**
     * Get the current WordPress version.
     *
     * @since  3.0.0
     * @return string
     */
    public function get_wp_version() {
        global $wp_version;
        return $wp_version;
    }

    /**
     * Get the current cache version number.
     *
     * @since  3.0.0
     * @return int
     */
    public function get_cache_version() {
        return (int) get_option( 'wpsl_cache_version', 1 );
    }

    /**
     * Generate a versioned cache key.
     *
     * @since  3.0.0
     * @param  string $identifier The base name for the cache, an md5 hash of the request details.
     * @return string
     */
    public function get_cache_key( $identifier ) {
        $version = $this->get_cache_version();

        // Transient names have a max length of 172 characters in the DB, longer names are truncated.
        return 'wpsl_auto_v' . $version . '_' . $identifier;
    }

    /**
     * Whether another autoload transient may be stored.
     *
     * The cache key is built from public request input ( start coordinates,
     * restrictions, category filter ), so without a cap an anonymous visitor
     * could fill the options table with day-long copies of the full result
     * set. Past the cap results are still served, only no longer cached.
     *
     * Only the current cache version is counted. With an external object
     * cache the transients don't live in the options table; those caches
     * evict on their own, so the cap is not enforced there.
     *
     * @since  3.0.0
     * @return bool
     */
    public function can_add_autoload_transient() {
        global $wpdb;

        /**
         * Filter the maximum number of autoload result sets kept in the options table.
         *
         * @since 3.0.0
         * @param int $max_entries Return 0 to remove the cap.
         */
        $max_entries = (int) apply_filters( 'wpsl_autoload_cache_max_entries', 200 );

        if ( $max_entries <= 0 || wp_using_ext_object_cache() ) {
            return true;
        }

        /*
         * Only entries that are still alive count. An expired transient
         * stays in the table until the daily clean-up or the next read of
         * that key, and a busy geolocated site turns entries over far faster
         * than that: counting the expired ones filled the cap within minutes
         * and switched caching off for everyone until the cron ran.
         *
         * A transient's expiry sits in a second row, _transient_timeout_ plus
         * the same key; a value row without one never expires.
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting transients by prefix, no core function exists for it.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} AS entry
              LEFT JOIN {$wpdb->options} AS expiry
                     ON expiry.option_name = CONCAT( '_transient_timeout_', SUBSTRING( entry.option_name, %d ) )
                  WHERE entry.option_name LIKE %s
                    AND ( expiry.option_value IS NULL OR expiry.option_value > %d )",
                strlen( '_transient_' ) + 1,
                $wpdb->esc_like( '_transient_wpsl_auto_v' . $this->get_cache_version() . '_' ) . '%',
                time()
            )
        );

        return $count < $max_entries;
    }

    /**
     * Globally flush the wpsl autoload and geocode transients.
     *
     * The transients are removed from the options table directly, this
     * also covers entries from previous cache versions that were created
     * without an expiration date and would otherwise remain in the
     * options table forever.
     *
     * The cache version is incremented as well so external object caches
     * ( Redis / Memcached ), which don't store transients in the options
     * table, no longer serve the old entries.
     *
     * @since  3.0.0
     * @return bool
     */
    public function flush_autoload_transients() {
        global $wpdb;

        /**
         * Both the current versioned names ( wpsl_auto_v ) and the legacy
         * v2.x names ( wpsl_autoload_ ) are removed. The patterns deliberately
         * exclude the wpsl_autocomplete_ transients used by the assets manager.
         *
         * The wpsl_*_latlng transients that hold the geocoded coordinates for
         * a store address are included as well. Only Google sets an expiration
         * date on those, for every other map service nothing else removes them.
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No core function exists to bulk delete transients by prefix.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                      WHERE option_name LIKE %s
                         OR option_name LIKE %s
                         OR option_name LIKE %s
                         OR option_name LIKE %s
                         OR option_name LIKE %s
                         OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_wpsl_auto_v' ) . '%',
                $wpdb->esc_like( '_transient_timeout_wpsl_auto_v' ) . '%',
                $wpdb->esc_like( '_transient_wpsl_autoload_' ) . '%',
                $wpdb->esc_like( '_transient_timeout_wpsl_autoload_' ) . '%',
                $wpdb->esc_like( '_transient_wpsl_' ) . '%' . $wpdb->esc_like( '_latlng' ),
                $wpdb->esc_like( '_transient_timeout_wpsl_' ) . '%' . $wpdb->esc_like( '_latlng' )
            )
        );

        $current_version = $this->get_cache_version();

        return update_option( 'wpsl_cache_version', $current_version + 1 );
    }
}