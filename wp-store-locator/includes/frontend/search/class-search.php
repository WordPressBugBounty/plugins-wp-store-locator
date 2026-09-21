<?php
/**
 * Handle the search functionality.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Search;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Templates\Filters;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Utils\Location_Utils;

use WPSL\Frontend\Store\Data as StoreData;

class Search {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Template filters instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Filters
     */
    private $template_filters;

    /**
     * Translations instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * Location utils instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Utils\Location_Utils
     */
    private $location_utils;

    /**
     * Store data instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Store\Data
     */
    private $store_data;

    /**
     * Property to store shortcode attributes
     *
     * @since 3.0.0
     * @var array
     */
    private $sl_shortcode_atts = [];

    /**
     * Static AJAX handler that delegates to the instance method.
     *
     * Hooked from wp-store-locator.php as a class-string callable, so this
     * file only autoloads once a store_search request actually arrives.
     *
     * @since  3.0.0
     * @return void
     */
    public static function handle_ajax_search() {
        $search = wpsl_get_service( 'search' );
        $search->ajax_store_search();
    }

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager     $settings         Settings manager instance
     * @param \WPSL\Core\I18n\Translations    $i18n             Translations instance
     * @param \WPSL\Core\Templates\Filters    $template_filters Template filters instance
     * @param \WPSL\Core\Utils\Location_Utils $location_utils   Location utils instance
     * @param \WPSL\Frontend\Store\Data       $store_data       Store data instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n, Filters $template_filters, Location_Utils $location_utils, StoreData $store_data ) {
        $this->settings = $settings;
        $this->template_filters = $template_filters;
        $this->i18n = $i18n;
        $this->location_utils = $location_utils;
        $this->store_data = $store_data;
    }

    /**
     * Handle the Ajax search on the frontend.
     *
     * @since  1.0.0
     * @return json A list of store locations that are located within the selected search radius
     */
    public function ajax_store_search() {
        /**
         * The search runs without a nonce ( the page it sits on can be
         * full-page cached ), so it is throttled per visitor instead. The
         * limit is generous on purpose: a search fires on every filter
         * change, and visitors behind one shared address ( office NAT,
         * mobile carrier ) share a counter.
         *
         * @since 3.0.0
         * @param int $max_per_minute Max search requests per visitor per minute. 0 disables the limit.
         */
        if ( \WPSL\Core\Utils\Rate_Limiter::is_limited( 'search', apply_filters( 'wpsl_search_rate_limit', 120 ) ) ) {
            // Plain text, two lines: the front end escapes it and breaks the line like the no-results message.
            wp_send_json_error( __( 'Too many search requests.', 'wp-store-locator' ) . "\n" . __( 'Please wait a moment and try again.', 'wp-store-locator' ), 429 );
        }

        $wpsl_settings = $this->settings->get_all();

        /**
         * Check if autoloading the locations on page load is enabled.
         *
         * If so then we save the store data in a transient to prevent a long loading time
         * in case a large amount of locations need to be displayed.
         */
        $autoload = isset( $_REQUEST['autoload'] ) ? sanitize_key( $_REQUEST['autoload'] ) : false;
        $skip_cache = isset( $_REQUEST['skip_cache'] ) ? sanitize_key( $_REQUEST['skip_cache'] ) : false;
        
        if ( $wpsl_settings['map']['autoload'] && $autoload && ! $wpsl_settings['tools']['debug'] && ! $skip_cache && $this->cacheable_autoload_results() ) {
            $transient_name = $this->create_transient_name();

            // Don't cache requests that contain invalid user input.
            if ( false === $transient_name ) {
                $store_data = $this->find_nearby_locations();
            } else {
                $cache_key = wpsl_get_service( 'system_utils' )->get_cache_key( $transient_name );

                if ( false === ( $store_data = get_transient( $cache_key ) ) ) {
                    $store_data = $this->find_nearby_locations();

                    /**
                     * Every distinct start location and restriction gets its
                     * own transient, and the request is public, so the number
                     * of entries is capped. Past the cap the results are still
                     * served, just not cached.
                     */
                    if ( $store_data && wpsl_get_service( 'system_utils' )->can_add_autoload_transient() ) {
                        /**
                         * Filter how long the autoload search results are cached.
                         *
                         * Transients with an expiration aren't autoloaded by WordPress,
                         * and expired ones are garbage collected by core. This prevents
                         * the options table from filling up with stale cache entries.
                         *
                         * @since 3.0.0
                         * @param int $expiration The cache duration in seconds.
                         */
                        $expiration = apply_filters( 'wpsl_autoload_cache_expiration', DAY_IN_SECONDS );

                        set_transient( $cache_key, $store_data, $expiration );
                    }
                } else {
                    // Cache hit: serve cached results.
                }
            }
        } else {
            $types = isset( $_GET['types'] ) ? sanitize_key( $_GET['types'] ) : '';

            if ( $types && ! in_array( $types, [ 'nearest', 'location' ] ) ) {
                $custom_search = wpsl_get_service( 'search_types' );
                $custom_search->init();
            } else {
                $store_data = $this->find_nearby_locations();
            }
        }

        do_action( 'wpsl_store_search' );

        if ( isset( $store_data ) && $store_data ) {
            // Maybe convert the results to geoJSON format ( Mapbox only )
            if ( wpsl_container()->has( 'geojson' ) ) {
                $geojson = wpsl_get_service( 'geojson' );
                $store_data = $geojson->create( $store_data );
            }

            wp_send_json( $store_data );

            exit();
        }
    }

    /**
     * Find store locations within the provided search radius.
     *
     * Calculates the distance between the searched location's lat/lng and
     * each store's lat/lng in the database.
     *
     * @since  2.0.0
     * @param  array      $args    The arguments to use in the SQL query, only used by add-ons
     * @return void|array $results The list of stores that fall within the selected range.
     */
    public function find_nearby_locations( $args = [] ) {
        global $wpdb;

        $placeholder_values = [];
        $wpsl_settings      = $this->settings->get_all();
        $sql_parts          = new \stdClass();

        // The placeholder values for the prepared statement in the SQL query.
        if ( empty( $args ) ) {
            /**
             * map_deep() is used instead of array_map() so that array
             * parameters like restrictions[city] keep their structure.
             * Passing an array to sanitize_text_field() directly would
             * return an empty string and break the location restrictions.
             */
            $args = map_deep( wp_unslash( $_GET ), 'sanitize_text_field' );
        }

        /**
         * Set the correct earth radius in either km or miles.
         * We need this to calculate the distance between two coordinates.
         */
        if ( isset( $args['distance_unit'] ) ) {
            $distance_unit = $args['distance_unit'];
        } else {
            $distance_unit = wpsl_get_distance_unit();
        }

        $placeholder_values[] = ( $distance_unit == 'km' ) ? 6371 : 3959;

        /**
         * Make sure the request contains valid coordinates to search from.
         * Without them the distance calculation is meaningless, so return
         * the no results data instead of running the SQL query.
         */
        $latlng = Location_Utils::validate_latlng(
            isset( $args['lat'] ) ? $args['lat'] : '',
            isset( $args['lng'] ) ? $args['lng'] : ''
        );

        if ( ! $latlng ) {
            return $this->process_results( [] );
        }

        array_push( $placeholder_values, $latlng['lat'], $latlng['lng'], $latlng['lat'] );

        // Check if we need to filter the results by category.
        if ( isset( $args['filter'] ) && $args['filter'] ) {
            $term_ids = array_map( 'absint', explode( ',', wpsl_normalize_category_filter( $args['filter'] ) ) );

            /**
             * See if we only need to return locations
             * that are assigned to all categories or not.
             */
            if ( $wpsl_settings['search']['all_categories_required'] ) {
                $post_ids = $this->location_utils->get_post_ids_by_terms( $term_ids );

                /**
                 * If no location is assigned to all the selected categories,
                 * then there is nothing to search for. Return the no results
                 * data instead of running the SQL query with an empty,
                 * invalid IN () clause.
                 */
                if ( empty( $post_ids ) ) {
                    return $this->process_results( [] );
                }

                $sql_parts->category_filter = "AND posts.ID IN (" . implode( ',', $post_ids ) . ")";
            } else {
                $sql_parts->category_filter = "INNER JOIN $wpdb->term_relationships AS term_rel ON posts.ID = term_rel.object_id
                                               INNER JOIN $wpdb->term_taxonomy AS term_tax ON term_rel.term_taxonomy_id = term_tax.term_taxonomy_id
                                                      AND term_tax.taxonomy = 'wpsl_store_category'
                                                      AND term_tax.term_id IN (" . implode( ',', $term_ids ) . ")";
            }
        } else {
            $sql_parts->category_filter = '';
        }

        $closed_filter = $this->build_closed_filter_sql();
        $sql_parts->closed_filter_join  = $closed_filter['join'];
        $sql_parts->closed_filter_match = $closed_filter['match'];

        /**
         * A store existing in multiple languages puts 
         * one row per language in the radius.
         */
        $sql_parts->group_by = 'GROUP BY posts.ID';

        /*
         * With WPML one store is a row per language, and the duplicates are only
         * collapsed after the query - so a LIMIT of 10 rows can end up as 4
         * stores. Fetching language-count times as many rows leaves enough for
         * dedupe_translated_stores() to cut back to the intended page. Rows it
         * drops for having no translation in the current language are not
         * covered, but only exist when wpsl_return_original_wpml_id is false.
         */
        $overfetch_factor = $this->i18n->is_wpml_compatible() ? $this->i18n->get_language_count() : 1;
        $php_limit        = null;

        // The restrictions are always passed as an array, ignore other formats.
        if ( isset( $args['restrictions'] ) && ! is_array( $args['restrictions'] ) ) {
            unset( $args['restrictions'] );
        }

        /**
         * Check if we need to restrict the results to one or more countries.
         *
         * A [wpsl country="..."] shortcode sends its own country restriction
         * ( config.search.restrictions.country ), which is a deliberate override
         * and must win over the settings-page country. Only fall back to the
         * settings restriction when neither an enforce-borders restriction nor a
         * shortcode country was passed; otherwise the settings country would
         * clobber the shortcode's and filter out the intended results.
         */
        if ( empty( $args['restrictions']['borders'] ) && empty( $args['restrictions']['country'] ) ) {
            $country_restriction = $this->get_country_restrictions( $wpsl_settings );

            if ( $country_restriction ) {
                $args['restrictions']['country'] = $country_restriction;
            }
        }

        /**
         * See if we need to restrict the returned
         * results to a specific country, city or state.
         */
        if ( isset( $args['restrictions'] ) ) {
            $restriction_meta_fields = apply_filters( 'wpsl_restriction_meta_fields', [
                'city'    => 'wpsl_city',
                'state'   => 'wpsl_state',
                'country' => 'wpsl_country',
                'iso'     => 'wpsl_country_iso'
            ] );

            // Structure the passed restrictions so we can use them in the SQL query.
            $restriction_sql = $this->build_restriction_sql( $this->prepare_sql_restrictions( $args['restrictions'] ), $restriction_meta_fields );

            $sql_parts->restriction_join  = $restriction_sql['join'];
            $sql_parts->restriction_match = $restriction_sql['match'];
            $placeholder_values           = array_merge( $placeholder_values, $restriction_sql['values'] );
        } else {
            $sql_parts->restriction_join  = '';
            $sql_parts->restriction_match = '';
        }

        /**
         * If autoload is enabled we need to check if there is
         * a limit to the amount of locations we need to show.
         *
         * Otherwise include the radius and max results limit in the sql query.
         *
         * Only when the site has autoload on. The flag comes from the public
         * request, and this branch drops the radius and the result limit:
         * honoured regardless, one autoload=1 parameter returned every store
         * on a site that never loads them all. The front end only sends it
         * when the setting is on, so nothing legitimate changes.
         */
        if ( ! empty( $args['autoload'] ) && ! empty( $wpsl_settings['map']['autoload'] ) ) {
            $limit = '';

            if ( $wpsl_settings['map']['autoload_limit'] ) {
                $autoload_limit = absint( $wpsl_settings['map']['autoload_limit'] );

                $limit = 'LIMIT %d';
                $placeholder_values[] = $autoload_limit * $overfetch_factor;

                if ( $overfetch_factor > 1 ) {
                    $php_limit = [
                        'offset' => 0,
                        'count'  => $autoload_limit
                    ];
                }
            }

            $sql_parts->sort = 'ORDER BY distance ' . $limit;
        } else if ( isset( $args['type'] ) && $args['type'] == 'nearest' ) {
            $sql_parts->sort = 'ORDER BY distance ASC LIMIT 1';
        } else {
            $search_radius = $this->template_filters->check_store_filter( $args, 'search_radius' );

            $placeholder_values[] = $search_radius;

            // See if we need it offset the results, or just limit it to x.
            $limits = $this->set_sql_limits( $args, $placeholder_values, $overfetch_factor );
            $placeholder_values = $limits['placeholder'];

            $sql_parts->sort = 'HAVING distance < %d ORDER BY distance ' . $limits['sql'];

            if ( $overfetch_factor > 1 ) {
                $php_limit = [
                    'offset' => $limits['offset'],
                    'count'  => $limits['count']
                ];
            }
        }

        $placeholder_values = apply_filters( 'wpsl_sql_placeholder_values', $placeholder_values );

        /**
         * The SQL that checks which store locations fall within the selected
         * radius based on lat and lng values.
         *
         * Both joins are pinned to the lowest meta_id for their key, because
         * WordPress allows more than one row per meta key and an importer can
         * create a second set. Unpinned, the joins pair a latitude from one set
         * with a longitude from another, and those coordinates can land inside
         * the radius while the real location sits far outside it. The lowest
         * meta_id is what get_post_meta() returns, so the distance uses the
         * coordinates the response reports.
         *
         * The pin is an anti-join rather than a correlated MIN(), which re-runs
         * per row and measured ~4.5x slower on a 2.5k store dataset.
         *
         * acos() is clamped to [-1, 1]: on a store at the searched point the
         * argument is mathematically 1, but rounding can push it past, and
         * acos() then returns NULL - dropping the very store searched on.
         */
        $sql = "SELECT post_lat.meta_value AS lat,
                        post_lng.meta_value AS lng,
                        posts.ID, 
                        ( %d * acos( LEAST( 1, GREATEST( -1, cos( radians( %s ) ) * cos( radians( post_lat.meta_value ) ) * cos( radians( post_lng.meta_value ) - radians( %s ) ) + sin( radians( %s ) ) * sin( radians( post_lat.meta_value ) ) ) ) ) )
                     AS distance
                   FROM {$wpdb->posts} AS posts
             INNER JOIN {$wpdb->postmeta} AS post_lat ON post_lat.post_id = posts.ID AND post_lat.meta_key = 'wpsl_lat'
                    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS pick_lat WHERE pick_lat.post_id = posts.ID AND pick_lat.meta_key = 'wpsl_lat' AND pick_lat.meta_id < post_lat.meta_id )
             INNER JOIN {$wpdb->postmeta} AS post_lng ON post_lng.post_id = posts.ID AND post_lng.meta_key = 'wpsl_lng'
                    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS pick_lng WHERE pick_lng.post_id = posts.ID AND pick_lng.meta_key = 'wpsl_lng' AND pick_lng.meta_id < post_lng.meta_id )
                {$sql_parts->restriction_join}
                {$sql_parts->category_filter}
                {$sql_parts->closed_filter_join}
                  WHERE posts.post_type = 'wpsl_stores'
                    AND posts.post_status = 'publish'
                    {$sql_parts->restriction_match}
                    {$sql_parts->closed_filter_match}
                    {$sql_parts->group_by} {$sql_parts->sort}";

        $sql = apply_filters( 'wpsl_sql', $sql );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required for Haversine distance math. Results cannot be cached due to infinite dynamic coordinate inputs.
        $stores = $wpdb->get_results( $wpdb->prepare( $sql, $placeholder_values ) );

        // Collapse translated stores to one row each, and re-apply the limit.
        if ( $this->i18n->is_wpml_compatible() && $stores ) {
            $stores = $this->dedupe_translated_stores( $stores, $php_limit );
        }

        // Grab the metadata for the returned location id's.
        $results = $this->process_results( $stores );

        return $results;
    }

    /**
     * Remove the extra rows a multilingual plugin adds to the search results.
     *
     * Translations of a store share the same ID, so duplicate IDs identify
     * translations — the first ( nearest, results are distance-ordered )
     * row of each group is kept. Unrelated stores sharing a lat/lng survive.
     *
     * Offset/limit are applied here, not in SQL: the query over-fetched to
     * allow for collapsing siblings, so slicing after dedupe guarantees a
     * full result page.
     *
     * @since  3.0.0
     * @param  array      $stores The distance-ordered rows from the search query.
     * @param  array|null $limit  Optional 'offset' / 'count' to slice the deduped rows.
     * @return array      $unique One row per store.
     */
    public function dedupe_translated_stores( $stores, $limit = null ) {
        $unique = [];
        $seen   = [];

        foreach ( $stores as $store ) {
            $translated_id = $this->i18n->maybe_get_wpml_id( $store->ID );

            /**
             * Rows without a translation for the current language would be
             * dropped by get_meta_data() anyway; drop them before the limit
             * is applied so they can't occupy a result slot. Duplicates are
             * translations of a store that's already in the list.
             */
            if ( ! $translated_id || isset( $seen[ $translated_id ] ) ) {
                continue;
            }

            $seen[ $translated_id ] = true;
            $unique[] = $store;
        }

        if ( $limit ) {
            $unique = array_slice( $unique, $limit['offset'], $limit['count'] );
        }

        return $unique;
    }

    /**
     * Build the SQL used to keep permanently closed locations that are
     * flagged to be excluded ( wpsl_exclude_closed ) out of the results.
     *
     * @since 3.0.0
     * @return array{join:string,match:string}
     */
    public function build_closed_filter_sql() {
        global $wpdb;

        return [
            'join'  => "LEFT JOIN {$wpdb->postmeta} AS post_location_status ON post_location_status.post_id = posts.ID AND post_location_status.meta_key = 'wpsl_location_status'
                        LEFT JOIN {$wpdb->postmeta} AS post_exclude_closed ON post_exclude_closed.post_id = posts.ID AND post_exclude_closed.meta_key = 'wpsl_exclude_closed'",
            'match' => "AND NOT ( post_location_status.meta_value = 'permanently_closed' AND COALESCE( post_exclude_closed.meta_value, '0' ) = '1' )",
        ];
    }

    /**
     * Grab the metadata for location ID's
     * and send the results back to the AJAX call.
     *
     * @since  3.0.0
     * @param  $stores                The store ID's from the search results.
     * @return mixed|void $store_data The search results including the full meta data.
     */
    public function process_results( $stores ) {
        if ( $stores ) {
            $store_data = apply_filters( 'wpsl_store_data', $this->store_data->get_meta_data( $stores ) );
        } else {
            $store_data = apply_filters( 'wpsl_no_results_sql', '' );
        }

        /**
         * If it's possible online only locations
         * exists, then we search for them and
         * return them.
         */
        if ( $this->settings->get( 'editor', 'enable_online_only' ) && $this->location_utils->online_only_exists() && apply_filters( 'wpsl_ignore_empty_stores', true ) ) {
            $store_data = $this->include_online_locations( $store_data );
        }

        return $store_data;
    }

    /**
     * Make sure the sql limit value is valid.
     *
     * The returned 'offset' / 'count' always hold the intended result page,
     * regardless of what the SQL LIMIT itself fetches.
     *
     * @since  3.0.0
     * @param  array $args               The arguments used in the SQL query
     * @param  array $placeholder_values The placeholder values collected so far
     * @param  int   $overfetch_factor   Fetch this many times the requested rows,
     *                                   used when translation duplicates are
     *                                   removed after the query.
     * @return array $limits
     */
    public function set_sql_limits( $args, $placeholder_values, $overfetch_factor = 1 ) {
        // absint() the offset so a negative page can't produce invalid SQL ( LIMIT -5, .. ).
        $offset = ( isset( $args['page'] ) && isset( $args['max_results'] ) ) ? absint( $args['page'] ) : 0;
        $count  = $this->template_filters->check_store_filter( $args, 'max_results' );

        $limits = [
            'sql'         => 'LIMIT 0, %d',
            'placeholder' => $placeholder_values,
            'offset'      => $offset,
            'count'       => absint( $count )
        ];

        if ( $overfetch_factor > 1 ) {
            /**
             * The query must start at row 0: translation duplicates are still
             * in the result set, so an SQL offset would skip an unpredictable
             * number of real stores. The offset is applied after the dedupe.
             */
            $limits['placeholder'][] = ( $offset + absint( $count ) ) * $overfetch_factor;
        } else if ( $offset ) {
            $limits['sql'] = 'LIMIT %d, %d';
            array_push( $limits['placeholder'], $offset, $count );
        } else {
            $limits['placeholder'][] = $count;
        }

        return $limits;
    }

    /**
     * Get the country restrictions based on the active map service.
     *
     * All map services ( Google Maps, OSM, Mapbox ) use the API setting
     * 'multiple_regions'. For Google Maps a single selected country is also enforced
     * at the geocode level; multiple countries are only filtered here ( server-side ).
     *
     * @since  3.0.0
     * @param  array  $wpsl_settings All plugin settings.
     * @return string                Comma-separated country codes, or empty string if none.
     */
    private function get_country_restrictions( $wpsl_settings ) {
        // For Google Maps the country restriction only applies in hard-restrict mode;
        // OSM and Mapbox always restrict to the selected countries.
        if ( $wpsl_settings['api']['active_map_service'] === 'gmaps'
            && ( ! isset( $wpsl_settings['api']['region_restriction_type'] ) || $wpsl_settings['api']['region_restriction_type'] !== 'restrict' )
        ) {
            return '';
        }

        if ( ! empty( $wpsl_settings['api']['multiple_regions'] ) && is_array( $wpsl_settings['api']['multiple_regions'] ) ) {
            return implode( ',', $wpsl_settings['api']['multiple_regions'] );
        }

        return '';
    }

    /**
     * The JOINs, the WHERE condition and its placeholder values for the
     * location restrictions.
     *
     * @since  3.0.0
     * @param  array $restrictions Field => values, as prepare_sql_restrictions() returns them.
     * @param  array $meta_fields  Field => meta key, see wpsl_restriction_meta_fields.
     * @return array [ 'join' => string, 'match' => string, 'values' => array ]
     */
    public function build_restriction_sql( $restrictions, $meta_fields ) {
        global $wpdb;

        $fields = [];

        foreach ( $restrictions as $restriction => $values ) {
            if ( array_key_exists( $restriction, $meta_fields ) && $values ) {
                $fields[ $restriction ] = array_values( (array) $values );
            }
        }

        /*
         * The alternatives for one field are ORed ( Paris or Lyon ), and the
         * fields are ANDed ( ... within France ). A country name and an ISO
         * code are two ways to name the same country, so they share one
         * group. The conditions used to be chained flat, and AND binds
         * tighter than OR: "Paris or Lyon, within France" came out as
         * "Paris OR ( Lyon AND France )" and let Paris, Texas through.
         */
        $groups = [];

        foreach ( $fields as $field => $values ) {
            $group = ( 'iso' === $field ) ? 'country' : $field;

            foreach ( $values as $value ) {
                $groups[ $group ]['conditions'][] = 'post_' . esc_sql( $field ) . '.meta_value LIKE %s';
                $groups[ $group ]['values'][]     = '%' . $wpdb->esc_like( $value ) . '%';
            }
        }

        /*
         * With both the name and the code in play either one may match, so
         * neither meta row may be required: a store without an ISO code row
         * still has to be found by its country name. Every other field keeps
         * its INNER JOIN.
         */
        $either_country = isset( $fields['country'], $fields['iso'] );
        $join           = '';

        foreach ( array_keys( $fields ) as $field ) {
            $join_type = ( $either_country && in_array( $field, [ 'country', 'iso' ], true ) ) ? 'LEFT JOIN' : 'INNER JOIN';
            $alias     = 'post_' . esc_sql( $field );

            $join .= "$join_type $wpdb->postmeta AS $alias ON $alias.post_id = posts.ID AND $alias.meta_key = '" . esc_sql( $meta_fields[ $field ] ) . "' \r\n";
        }

        $parts  = [];
        $values = [];

        foreach ( $groups as $group ) {
            $parts[] = '( ' . implode( ' OR ', $group['conditions'] ) . ' )';
            $values  = array_merge( $values, $group['values'] );
        }

        return [
            'join'   => $join,
            // One outer group, so no OR can escape into the rest of the WHERE clause.
            'match'  => $parts ? 'AND ( ' . implode( ' AND ', $parts ) . ' )' : '',
            'values' => $values,
        ];
    }

    /**
     * Structure the passed location restrictions for the SQL query.
     *
     * @since  3.0.0
     * @param  array $restrictions The city / state / country / iso code restrictions for the SQL query.
     * @return array $restrictions Restructed location data for the SQL query.
     */
    public function prepare_sql_restrictions( $restrictions ) {
        $fields = [];

        foreach ( $restrictions as $field_name => $values ) {

            // Ignore nested arrays, restriction values are always strings.
            if ( ! is_string( $values ) ) {
                continue;
            }

            $values = array_map( 'trim', explode( ',', $values ) );

            /**
             * Assign the field names that we will use in the SQl query to the
             * array key. Only initialize a key that doesn't exist yet, because
             * 'borders' and 'country' both write into the shared 'country' /
             * 'iso' keys. Resetting unconditionally would wipe values an
             * earlier iteration already collected ( e.g. the 'Netherlands' name
             * from a borders restriction when a country restriction follows ).
             */
            if ( ! isset( $fields[$field_name] ) ) {
                $fields[$field_name] = [];
            }

            foreach ( $values as $value ) {
                if ( in_array( $field_name, [ 'country', 'borders' ] ) ) {

                    // Only reason it can be 2 chars and letters is if it's an iso code.
                    if ( strlen( $value ) == 2 && ctype_alpha( $value ) ) {
                        $fields['iso'][] = $value;
                    } else {
                        $fields['country'][] = $value;
                    }
                } else if ( $value ) {
                    $fields[$field_name][] = $value;
                }
            }
        }

        $restrictions = $fields;

        unset( $restrictions['borders'] );

        /**
         * Need to force the order of the array to make sure
         * the AND / OR ( AND country='x' OR iso = 'y' )
         * is applied correctly in the SQL query.
         */
        if ( array_key_first( $restrictions ) == 'iso' ) {
            $restrictions = array_reverse( $restrictions );
        }

        return $restrictions;
    }

    /**
     * Make sure to include online locations.
     *
     * @since 3.0.0
     * @param array $stores The collected search results.
     */
    public function include_online_locations( $stores ) {
        $online = $this->location_utils->online_only();

        if ( is_array( $stores ) ) {
            $stores = array_merge( $online, $stores );
        } else {
            $stores = $online;
        }

        return $stores;
    }

    /**
     * Whether the autoload search results can be cached.
     *
     * The open / closed status is rendered into the response, so with the
     * status enabled the payload is time-sensitive: a transient created in
     * the morning would keep reporting "Open, Closes 5:00 PM" long after
     * closing time. Without the status the payload only changes when the
     * locations themselves change.
     *
     * @since  3.0.0
     * @return bool
     */
    public function cacheable_autoload_results() {
        // With the hours globally hidden no status reaches the payload.
        if ( $this->settings->get( 'editor', 'hide_hours' ) ) {
            return true;
        }

        return ! $this->settings->get( 'ux', 'show_hour_status' );
    }

    /**
     * Create the name used in the wpsl autoload transient.
     *
     * The returned name is hashed so user input can't influence the length
     * of the option name, and false is returned for requests that contain
     * invalid user input so they are never cached.
     *
     * @since  2.1.1
     * @return string|false $transient_name The transient name, or false if the request shouldn't be cached.
     */
    public function create_transient_name() {
        $wpsl_settings = $this->settings->get_group( 'map' );
        $name_section  = [];

        /**
         * An open-only request is a snapshot of "who is open right now", so it
         * must never be cached. The open_only param isn't part of the cache
         * key, so caching it would serve the filtered payload ( closed
         * locations removed ) to every visitor until the transient expires.
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public filter parameter, not a form submission
        if ( isset( $_GET['open_only'] ) ) {
            return false;
        }

        // Include the set autoload limit.
        if ( $wpsl_settings['autoload'] && $wpsl_settings['autoload_limit'] ) {
            $name_section[] = absint( $wpsl_settings['autoload_limit'] );
        }

        /**
         * Check if we need to include the cat id(s) in the transient name.
         *
         * This can only happen if the user used the
         * 'category' attr on the wpsl shortcode.
         */
        if ( isset( $_GET['filter'] ) ) {
            $filter = wpsl_normalize_category_filter( wp_unslash( $_GET['filter'] ) );

            if ( $filter ) {

                // Only cache the request if the filter holds comma separated term ids.
                if ( ! preg_match( '/^[0-9]+(,[0-9]+)*$/', $filter ) ) {
                    return false;
                }

                // Keep the filter unchanged so '1,2' and '12' don't collapse to the same cache key.
                $name_section[] = $filter;
            }
        }

        // Include both the lat and lng from the start location.
        foreach ( [ 'lat' => 90, 'lng' => 180 ] as $coord => $max ) {
            if ( isset( $_GET[ $coord ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_GET[ $coord ] ) );

                if ( $value !== '' ) {

                    // Only cache the request if it contains a valid coordinate.
                    if ( ! is_numeric( $value ) || $value > $max || $value < -$max ) {
                        return false;
                    }

                    /**
                     * Key on the coordinate rounded to three decimals ( about
                     * 110 m ). The front end sends the settings start point,
                     * but the request is public: without rounding, every
                     * trailing-digit variant would get its own day-long
                     * transient and use up the cap.
                     */
                    $name_section[] = $coord . ':' . number_format( (float) $value, 3, '.', '' );
                }
            }
        }

        /**
         * Every restriction the query reads, under its field name: borders
         * included ( the enforce-borders button sends it ), and any field an
         * add-on adds through wpsl_restriction_meta_fields. Joining only the
         * city / state / country values let a bordered and an unbordered
         * search, or city=Paris and state=Paris, share one cache entry.
         * Sorted, so the same restrictions in another order hit the same
         * entry; empty ones are skipped, the query ignores them too.
         */
        if ( isset( $_GET['restrictions'] ) && is_array( $_GET['restrictions'] ) ) {
            $restrictions = [];

            foreach ( wp_unslash( $_GET['restrictions'] ) as $field => $value ) {

                // prepare_sql_restrictions() only reads string values.
                if ( ! is_string( $value ) ) {
                    continue;
                }

                $value = sanitize_text_field( $value );

                if ( '' !== $value ) {
                    $restrictions[ sanitize_key( $field ) ] = $value;
                }
            }

            if ( $restrictions ) {
                ksort( $restrictions );

                $name_section[] = wp_json_encode( $restrictions );
            }
        }

        /**
         * If a multilingual plugin is active then we have to make sure each
         * language has his own unique transient. We do this by including the
         * lang code in the transient name.
         *
         * Otherwise if the language is for example set to German on page load,
         * and the user switches to Spanish, then he would get the incorrect
         * permalink structure ( /de/.. instead or /es/.. ) and translated
         * store details.
         */
        $lang_code = $this->i18n->check_multilingual_code();

        if ( $lang_code ) {
            $name_section[] = $lang_code;
        }

        $transient_name = implode( '_', $name_section );

        // Include the distance unit in the transient name to prevent caching collisions between km/mi.
        if ( isset( $_GET['distance_unit'] ) && in_array( $_GET['distance_unit'], [ 'km', 'mi' ], true ) ) {
            $transient_name = $transient_name . '_' . sanitize_key( $_GET['distance_unit'] );
        } else {
            $transient_name = $transient_name . '_' . wpsl_get_distance_unit();
        }

        /**
         * Hash the name to keep the option name well below the 191 character
         * limit. Longer names would silently be truncated by WordPress,
         * which can result in cache collisions.
         */
        $hashed_name = md5( $transient_name );

        return $hashed_name;
    }
}