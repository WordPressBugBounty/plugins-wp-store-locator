<?php
/**
 * Handle caching for Nomination geocode requests.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Map;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nominatim_Geocode_Cache {

    /**
     * The name of the table that holds the Nominatim geocode cache
     *
     * @since 3.0.0
     */
    public $cache_table;

    /**
     * Whether to cache Nominatim API responses
     *
     * @since 3.0.0
     * @var bool
     */
    private $enable_cache;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;

        $this->cache_table = $wpdb->prefix . 'wpsl_nominatim_cache';

        /**
         * Filter whether to enable Nominatim geocode caching
         *
         * @since 3.0.0
         * @param bool $enable_cache Whether to cache API responses. Default true.
         */
        $this->enable_cache = apply_filters( 'wpsl_enable_nominatim_cache', true );

        add_action( 'wp_ajax_nominatim_search',        [ $this, 'search' ] );
        add_action( 'wp_ajax_nopriv_nominatim_search', [ $this, 'search' ] );
    }

    /**
     * Search the Nominatim geocode cache for
     * geolocation data based on the searched location.
     *
     * @since 3.0.0
     */
    public function search() {
        $response = [];
        $location = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';

        /**
         * Optional structured query type. When the "Force zip code only search"
         * option is enabled the frontend sends type=postcode, so we query
         * Nominatim's structured "postalcode=" field instead of the free-form
         * "q=" field, which resolves bare postcodes far more reliably.
         *
         * @see https://nominatim.org/release-docs/develop/api/Search/#structured-query
         */
        $type = isset( $_REQUEST['type'] ) ? sanitize_key( wp_unslash( $_REQUEST['type'] ) ) : '';

        // Only the postcode structured query is supported for now.
        if ( $type !== 'postcode' ) {
            $type = '';
        }

        /**
         * The same searched value means something different as a free-form
         * query than as a structured postcode query, so the cache key has to
         * include the type to keep the two from polluting each other.
         */
        $cache_key = $type ? $type . ':' . $location : $location;

        /**
         * The geocoder restricts Nominatim to the selected countries and asks
         * for a language, and both change the answer. They are part of the
         * key, so switching either never serves an answer ( or a remembered
         * "nothing found" ) given under the old configuration. Hashed to a
         * fixed length: the query column is a VARCHAR(256).
         */
        $cache_key = 'r' . substr( md5( $this->restriction_identity() ), 0, 8 ) . ':' . $cache_key;

        /**
         * The query column in the cache table is a VARCHAR(256), longer
         * queries can never produce a cache hit. Real searched locations
         * never get this long, so don't waste an API request on it.
         */
        if ( strlen( $cache_key ) > 256 ) {
            wp_send_json( [
                'success'    => false,
                'userNotice' => 'technicalProblem'
            ] );
        }

        /**
         * This endpoint is public and can't use a nonce ( cached pages would
         * serve expired nonces and break the search ). Rate limit it instead
         * to prevent a single visitor from generating an unlimited number of
         * Nominatim API requests, which violates their usage policy.
         */
        if ( $this->is_rate_limited() ) {
            wp_send_json( [
                'success'    => false,
                'userNotice' => 'technicalProblem'
            ] );
        }

        if ( $location ) {
            // Check cache only if caching is enabled
            if ( $this->enable_cache ) {
                $result = $this->get_cache_entry( $cache_key );

                // Decode the JSON string from database to PHP array
                $response = json_decode( stripslashes( $result ?? '' ), true );
            }

            /**
             * If no cached entry exists, make a new request to
             * the Nominatim API, cache the response, and return it.
             */
            if ( ! $response ) {

                // Nominatim recently had nothing for this query, it won't have now.
                if ( $this->enable_cache && get_transient( $this->empty_result_key( $cache_key ) ) ) {
                    wp_send_json( [] );
                }

                /**
                 * Every site has one Nominatim request per second to hand
                 * out ( see Request_Throttle ), and only a cache miss spends
                 * it. Under the general limit above a few visitors sending
                 * made-up queries could keep that slot taken, and everyone
                 * else's search would fail. A miss is rare for a real
                 * visitor, so it gets its own, tighter limit.
                 *
                 * @since 3.0.0
                 * @param int $max_per_minute Max uncached geocode requests per visitor per minute. 0 disables the limit.
                 */
                if ( \WPSL\Core\Utils\Rate_Limiter::is_limited( 'nominatim_miss', apply_filters( 'wpsl_nominatim_miss_rate_limit', 10 ) ) ) {
                    wp_send_json( [
                        'success'    => false,
                        'userNotice' => 'technicalProblem'
                    ] );
                }

                $geocode_query = self::build_geocode_query( $location, $type );

                $api_response = wpsl_call_geocode_api( $geocode_query, 'osm', [] );

                if ( ! is_wp_error( $api_response ) && $api_response['response']['message'] == 'OK' ) {
                    $response = json_decode( $api_response['body'], true );

                    // Validate the response structure before caching (only cache if enabled)
                    if ( $this->enable_cache && $this->is_valid_nominatim_response( $response ) ) {
                        $this->update( $cache_key, $response );
                    } elseif ( $this->enable_cache && [] === $response ) {
                        $this->remember_empty_result( $cache_key );
                    }
                } else {
                    $response = [ 
                        'success'    => false, 
                        'userNotice' => 'technicalProblem'
                    ];
                }   
            }
        }

        wp_send_json( $response );
        exit();
    }

    /**
     * Build the Nominatim query string for a searched location.
     *
     * The value is untrusted visitor input, so it MUST be rawurlencode()'d -
     * without it, "London&countrycodes=ru&limit=50" would inject extra
     * parameters. Encoding turns "&"/"=" into %26/%3D so the value stays one
     * search term.
     *
     * Postcode searches use "postalcode=", everything else uses "q=". Both
     * carry an "=", which Geocode_Osm::call_api() treats as a pass-through
     * signal so it doesn't wrap the string again.
     *
     * @since  3.0.0
     * @param  string $location The raw searched location.
     * @param  string $type     The query type ( 'postcode' for a structured
     *                          postcode search, empty for a free-form search ).
     * @return string           The encoded, ready-to-use Nominatim query string.
     */
    public static function build_geocode_query( $location, $type ) {
        $field = ( $type === 'postcode' ) ? 'postalcode=' : 'q=';

        return $field . rawurlencode( $location );
    }

    /**
     * Remember for a while that Nominatim had no result for a query.
     *
     * The cache table only takes real results, an address that is unknown
     * today may be mapped next month. But without any memory a made-up query
     * is a guaranteed cache miss, and repeating it spends the site's one
     * Nominatim request per second every time.
     *
     * @since  3.0.0
     * @param  string $cache_key The cache key of the searched location.
     * @return void
     */
    private function remember_empty_result( $cache_key ) {

        /**
         * Filter how long an empty Nominatim result is remembered.
         *
         * @since 3.0.0
         * @param int $ttl Seconds. 0 or less turns it off.
         */
        $ttl = (int) apply_filters( 'wpsl_nominatim_empty_result_ttl', HOUR_IN_SECONDS );

        if ( $ttl > 0 ) {
            set_transient( $this->empty_result_key( $cache_key ), 1, $ttl );
        }
    }

    /**
     * The transient that marks a query as having no result.
     *
     * The country restriction is part of it: a place Nominatim can't find
     * inside one country may well exist in the one the admin switches to.
     *
     * @since  3.0.0
     * @param  string $cache_key The cache key of the searched location.
     * @return string
     */
    private function empty_result_key( $cache_key ) {
        return 'wpsl_nominatim_empty_' . md5( $cache_key );
    }

    /**
     * The country restriction and language the geocoder sends with the
     * request, read by the geocoder itself so the two can't drift apart.
     *
     * @since  3.0.0
     * @return string
     */
    private function restriction_identity() {
        return ( new \WPSL\Admin\API\Geocode_Osm( wpsl_get_service( 'wpsl_settings' ) ) )->restriction_identity();
    }

    /**
     * Check if the current visitor exceeded the geocode request limit.
     *
     * Cache hits count towards the limit as well, but the limit is high
     * enough that regular visitors searching for locations never reach it.
     *
     * The wpsl_nominatim_rate_limit filter controls the max number of
     * geocode requests a single visitor can make per minute. Return 0 to
     * disable the rate limit.
     *
     * @since  3.0.0
     * @return bool True if the visitor made too many requests
     */
    private function is_rate_limited() {
        $max_requests = apply_filters( 'wpsl_nominatim_rate_limit', 30 );

        return \WPSL\Core\Utils\Rate_Limiter::is_limited( 'nominatim', $max_requests );
    }

    /**
     * Get a cache entry from the database.
     *
     * @since  3.0.0
     * @param  string $query The search query
     * @return string|null The cached response or null if not found
     */
    private function get_cache_entry( $query ) {
        global $wpdb;

        if ( empty( $query ) ) {
            return null;
        }

        // The country restriction and language are part of the key, see search().
        $sql = "SELECT response FROM {$this->cache_table} WHERE query = %s LIMIT 1";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared with $wpdb->prepare() with placeholders, this custom table is itself the cache
        return $wpdb->get_var( $wpdb->prepare( $sql, $query ) );
    }

    /**
     * Validate that the response matches expected Nominatim structure.
     *
     * @since  3.0.0
     * @param  mixed $response The decoded JSON response
     * @return bool True if valid, false otherwise
     */
    private function is_valid_nominatim_response( $response ) {
        // Empty responses stay out of the cache table, see remember_empty_result() for those
        if ( empty( $response ) ) {
            return false;
        }
        
        // Nominatim returns an array of results - validate structure
        if ( ! is_array( $response ) || ! isset( $response[0] ) || ! is_array( $response[0] ) ) {
            return false;
        }
        
        $first_result = $response[0];
        
        // Required fields that Nominatim always returns
        $required_fields = apply_filters( 'wpsl_nominatim_required_response_fields', [
            'lat',
            'lon',
            'display_name'
        ] );
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $first_result[ $field ] ) ) {
                return false;
            }
        }
        
        // Validate lat/lon are numeric and within valid ranges
        $lat = $first_result['lat'];
        $lon = $first_result['lon'];
        
        if ( ! is_numeric( $lat ) || ! is_numeric( $lon ) ) {
            return false;
        }
        
        $lat = (float) $lat;
        $lon = (float) $lon;
        
        if ( $lat < -90 || $lat > 90 ) {
            return false;
        }
        
        if ( $lon < -180 || $lon > 180 ) {
            return false;
        }
        
        // Validate display_name is not empty
        if ( empty( $first_result['display_name'] ) ) {
            return false;
        }
        
        return true;
    }

    /**
     * Update the Nominatim geocode cache db.
     *
     * @since  3.0.0
     * @param  string $location The search location/query
     * @param  array  $response The Nominatim API response
     * @return void
     */
    private function update( $location, $response ) {
        global $wpdb;

        // Encode the response array to JSON for storage
        // Response is already validated by is_valid_nominatim_response() before calling update()
        $response_json = wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        // Extract country code from first result if available
        $country_code = '';

        if ( ! empty( $response[0]['address']['country_code'] ) ) {
            $country_code = strtolower( $response[0]['address']['country_code'] );
        }

        $placeholder = apply_filters( 'wpsl_nominatim_update_geocode_cache_placeholder', [ $location, $country_code, $response_json ] );

        list( $location, $country_code, $response_json ) = $placeholder;

        $this->maybe_evict_oldest_entries();

        /**
         * Remove any existing entries for this query first. The query column
         * doesn't have a unique index, so without this the table would
         * accumulate duplicate rows for the same searched location.
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This custom table is itself the cache
        $wpdb->delete( $this->cache_table, [ 'query' => $location ] );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- This custom table is itself the cache
        $result = $wpdb->insert( $this->cache_table, [
            'query'        => $location,
            'country_code' => $country_code,
            'response'     => $response_json
        ] );

        // Check for errors (silently fail in production)
        if ( $result === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for development
            error_log( 'Nominatim cache update failed: ' . $wpdb->last_error );
        }
    }

    /**
     * Keep the cache table within a row limit by removing the oldest
     * entries once the max is reached.
     *
     * The wpsl_nominatim_cache_max_rows filter controls the max number of
     * rows kept in the cache table. Return 0 to disable the limit.
     *
     * @since  3.0.0
     * @return void
     */
    private function maybe_evict_oldest_entries() {
        global $wpdb;

        $max_rows = apply_filters( 'wpsl_nominatim_cache_max_rows', 10000 );

        if ( ! $max_rows ) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a safe class property
        $row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->cache_table}" );

        if ( $row_count >= $max_rows ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a safe class property, SQL is prepared
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->cache_table} ORDER BY id ASC LIMIT %d", ( $row_count - $max_rows ) + 1 ) );
        }
    }

    /**
     * Flush the Nominatim geocode cache.
     *
     * @since 3.0.0
     */
    public function flush() {
        global $wpdb;

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error();
        }

        if ( ! isset( $_REQUEST['wpsl_nonce'] ) || ! isset( $_REQUEST['id'] ) ) {
            wp_send_json_error();
        }

        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['wpsl_nonce'] ) );
        $id = sanitize_key( $_REQUEST['id'] );

        if ( wp_verify_nonce( $nonce, $id ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is a safe class property, one-time existence check
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->cache_table ) ) === $this->cache_table ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a safe class property
                $wpdb->query( "TRUNCATE TABLE {$this->cache_table}" );
            }

            // The markers for queries without a result, see remember_empty_result().
            // With a persistent object cache they aren't in this table and run out on their own.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No core function exists to bulk delete transients by prefix
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like( '_transient_wpsl_nominatim_empty_' ) . '%',
                    $wpdb->esc_like( '_transient_timeout_wpsl_nominatim_empty_' ) . '%'
                )
            );

            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Create the Nominatim ( OpenStreetMaps ) Geocode cache table.
     *
     * @since   3.0.0
     * @return  void
     */
    public function create_table() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time table existence check, table name is a safe class property
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $this->cache_table ) ) != $this->cache_table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time table creation for geocode cache
            $sql = "CREATE TABLE {$this->cache_table} (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        query VARCHAR(256),
                        country_code CHAR(2),
                        response TEXT,
                        PRIMARY KEY (id),
                        KEY query (query)
                        ) $collate;";

            dbDelta( $sql );
        }
    }
}