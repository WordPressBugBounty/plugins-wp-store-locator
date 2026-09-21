<?php
/**
 * Site-wide spacing between requests to a third-party API.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Request_Throttle {

    /**
     * Seconds a slot stays taken when the request holding it never gives it
     * back ( fatal error, killed PHP worker ). Longer than any request should
     * take, short enough that a crash doesn't block the API for long.
     *
     * @since 3.0.0
     */
    const LOCK_TIMEOUT = 30;

    /**
     * Longest single sleep while waiting for a slot, in seconds. A slot held
     * by a request in flight has no known end time, so it's polled.
     *
     * @since 3.0.0
     */
    const POLL_INTERVAL = 0.25;

    /**
     * The option that holds the time the slot is free again.
     *
     * @since 3.0.0
     * @var string
     */
    private $option_name;

    /**
     * Seconds between the end of one request and the start of the next.
     *
     * @since 3.0.0
     * @var float
     */
    private $interval;

    /**
     * Seconds to wait for a free slot before giving up.
     *
     * @since 3.0.0
     * @var float
     */
    private $max_wait;

    /**
     * Returns the current time in seconds.
     *
     * @since 3.0.0
     * @var callable
     */
    private $clock;

    /**
     * Sleeps for the given number of seconds.
     *
     * @since 3.0.0
     * @var callable
     */
    private $sleep;

    /**
     * The option value written when this instance took the slot, or null.
     *
     * @since 3.0.0
     * @var string|null
     */
    private $held_value = null;

    /**
     * Seconds the database clock is ahead of this server's, or null until
     * measured. See db_offset().
     *
     * @since 3.0.0
     * @var float|null
     */
    private $db_offset = null;

    /**
     * The options table the slot lives in, or null until resolved. See table().
     *
     * @since 3.0.0
     * @var string|null
     */
    private $table = null;

    /**
     * Whether acquire() gave up because the database refused the query,
     * rather than because the slot stayed taken.
     *
     * @since 3.0.0
     * @var bool
     */
    private $db_failed = false;

    /**
     * The throttles that hold a slot right now, by object id. See release_all().
     *
     * @since 3.0.0
     * @var Request_Throttle[]
     */
    private static $in_flight = [];

    /**
     * Whether release_all() is already set to run when the request ends.
     *
     * @since 3.0.0
     * @var bool
     */
    private static $shutdown_registered = false;

    /**
     * One slot for the whole site, so a busy site cannot exceed an API's
     * total request rate ( the per-IP Rate_Limiter gives every visitor
     * their own budget and cannot do that ).
     *
     * The slot is a row in the options table taken with INSERT IGNORE:
     * option_name has a unique index, so exactly one PHP worker wins
     * regardless of the object cache. get_option() is never used for it,
     * it could return a cached value.
     *
     * @since 3.0.0
     * @param string        $name     Short name for the throttled API, e.g. 'nominatim'.
     * @param float         $interval Seconds between two requests. 0 or less disables the throttle.
     * @param float         $max_wait Seconds to wait for a free slot before giving up.
     * @param callable|null $clock    Returns the current time in seconds. Defaults to the database's clock.
     * @param callable|null $sleep    Sleeps for the given seconds. Defaults to usleep().
     */
    public function __construct( $name, $interval, $max_wait, $clock = null, $sleep = null ) {
        $this->option_name = 'wpsl_throttle_' . preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $name ) );
        $this->interval    = max( 0, (float) $interval );
        $this->max_wait    = max( 0, (float) $max_wait );

        $this->clock = $clock ? $clock : function() {
            return microtime( true ) + $this->db_offset();
        };

        $this->sleep = $sleep ? $sleep : function( $seconds ) {
            usleep( (int) round( $seconds * 1000000 ) );
        };
    }

    /**
     * Take the slot, waiting up to max_wait seconds for it to come free.
     *
     * @since  3.0.0
     * @return bool True when the slot is ours and the request can go ahead.
     */
    public function acquire() {
        global $wpdb;

        if ( $this->interval <= 0 ) {
            return true;
        }

        $this->db_failed = false;

        $table    = $this->table();
        $deadline = $this->now() + $this->max_wait;

        while ( true ) {
            $now        = $this->now();
            $held_value = $this->format( $now + self::LOCK_TIMEOUT );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The row is a lock, it must bypass the object cache. The table name is built from the database prefix.
            $inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", $this->option_name, $held_value ) );

            if ( 1 === $inserted ) {
                $this->held_value = $held_value;

                self::$in_flight[ spl_object_id( $this ) ] = $this;

                if ( ! self::$shutdown_registered ) {
                    self::$shutdown_registered = true;

                    register_shutdown_function( [ __CLASS__, 'release_all' ] );
                }

                return true;
            }

            // Waiting is pointless when the database refuses the query.
            if ( false === $inserted ) {
                $this->db_failed = true;

                return false;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- See above
            $free_at = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$table} WHERE option_name = %s", $this->option_name ) );

            /*
             * Clear the slot when its time is up, whether it was released or
             * abandoned, and go again without sleeping. Only when this
             * request removed the row: if the DELETE found nothing, another
             * request got there first ( or the SELECT came from a replica
             * that lags ), and looping on would spin.
             */
            if ( null !== $free_at && (float) $free_at < $now ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- See above
                if ( $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE option_name = %s AND CAST( option_value AS DECIMAL(20,6) ) < %s", $this->option_name, $this->format( $now ) ) ) ) {
                    continue;
                }

                $free_at = $now + self::POLL_INTERVAL;
            }

            /*
             * The deadline is checked only after an expired slot had its
             * chance to be reclaimed. Checked first, a request with max_wait 0
             * could never clear the row, and every one of them failed until
             * some other request happened to.
             */
            $remaining = $deadline - $now;

            if ( $remaining <= 0 ) {
                return false;
            }

            ( $this->sleep )( min( max( (float) $free_at - $now, 0.01 ), self::POLL_INTERVAL, $remaining ) );
        }
    }

    /**
     * Whether the last acquire() failed on a database error instead of a
     * slot that stayed taken. The two need a different message: "busy"
     * sends the admin looking in the wrong place.
     *
     * @since  3.0.0
     * @return bool
     */
    public function has_db_error() {
        return $this->db_failed;
    }

    /**
     * The options table that holds the slot.
     *
     * On multisite that is the main site's, for every site in the network:
     * the sites share one server address, and that address is what the API
     * counts requests for.
     *
     * @since  3.0.0
     * @return string
     */
    private function table() {
        global $wpdb;

        if ( null === $this->table ) {
            $this->table = is_multisite() ? $wpdb->get_blog_prefix( get_main_site_id() ) . 'options' : $wpdb->options;
        }

        return $this->table;
    }

    /**
     * Give the slot back. The next request can start once the interval has passed.
     *
     * Only the value this instance wrote is replaced. If the request outlived
     * LOCK_TIMEOUT and another one took over the slot, that one keeps it.
     *
     * @since  3.0.0
     * @return void
     */
    public function release() {
        global $wpdb;

        if ( null === $this->held_value ) {
            return;
        }

        $table            = $this->table();
        $held_value       = $this->held_value;
        $this->held_value = null;

        unset( self::$in_flight[ spl_object_id( $this ) ] );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The row is a lock, it must bypass the object cache. The table name is built from the database prefix.
        $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET option_value = %s WHERE option_name = %s AND option_value = %s", $this->format( $this->now() + $this->interval ), $this->option_name, $held_value ) );
    }

    /**
     * Give back every slot this request still holds. Runs on shutdown.
     *
     * The caller's try / finally doesn't run on a fatal error or when
     * max_execution_time kills the request, shutdown functions do. Without
     * this the slot stays taken for the full LOCK_TIMEOUT, and nobody on the
     * site can geocode in the meantime.
     *
     * @since  3.0.0
     * @return void
     */
    public static function release_all() {
        foreach ( self::$in_flight as $throttle ) {
            try {
                $throttle->release();
            } catch ( \Throwable $e ) {
                // Nothing is left to report to. The lock's own expiry covers it.
                continue;
            }
        }
    }

    /**
     * @since  3.0.0
     * @return float
     */
    private function now() {
        return (float) ( $this->clock )();
    }

    /**
     * How far the database clock is ahead of this server's, in seconds.
     *
     * Read once per instance. A database that cannot answer ( no
     * connection, a fake ) leaves the offset at zero, which is the local
     * clock the throttle used before.
     *
     * @since  3.0.0
     * @return float
     */
    private function db_offset() {
        global $wpdb;

        if ( null === $this->db_offset ) {
            $this->db_offset = 0.0;

            if ( $wpdb && method_exists( $wpdb, 'get_var' ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading the database clock, there is nothing to cache
                $db_now = $wpdb->get_var( 'SELECT UNIX_TIMESTAMP( NOW( 6 ) )' );

                if ( is_numeric( $db_now ) ) {
                    $this->db_offset = (float) $db_now - microtime( true );
                }
            }
        }

        return $this->db_offset;
    }

    /**
     * Format a time for the option value. %F ignores the locale, so there's never a decimal comma.
     *
     * @since  3.0.0
     * @param  float  $time
     * @return string
     */
    private function format( $time ) {
        return sprintf( '%.6F', $time );
    }
}