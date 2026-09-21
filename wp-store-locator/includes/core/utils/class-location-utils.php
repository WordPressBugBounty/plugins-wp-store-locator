<?php
/**
 * location utils.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Location_Utils {

   /**
     * Return the post ids from locations that
     * are assigned to all the passed term ids.
     *
     * @since  3.0.0
     * @param  array $term_ids
     * @return array $location_ids
     */
    public function get_post_ids_by_terms( $term_ids = [] ) {
        $location_ids = [];
        $tax_query    = [];

        if ( empty( $term_ids ) ) {
            return $location_ids;
        }

        foreach ( $term_ids as $id ) {
            $tax_query[] = [
                'taxonomy' => 'wpsl_store_category',
                'field'    => 'term_id',
                'terms'    => (int) $id,
            ];
        }

        $args = [
            'post_type' => 'wpsl_stores',
            'post_status'            => 'publish',
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'posts_per_page'         => -1,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'tax_query'              => array_merge( [ 'relation' => 'AND' ], $tax_query ),
        ];

        $posts = new \WP_Query( $args );

        if ( is_array( $posts->posts ) && ! empty( $posts->posts ) ) {
            foreach ( $posts->posts as $post_id ) {
                $location_ids[] = absint( $post_id );
            }
        }

        return $location_ids;
    }

    /**
     * Check if any online only stores exist.
     *
     * @since  3.0.0
     * @return array $stores
     */
    public function online_only_exists() {
        $online = $this->find_online_ids( [ 'limit' => 1 ] );

        return $online;
    }

    /**
     * Collect data for the online only stores.
     *
     * @since  3.0.0
     * @param  array $args             Set the limit and offset for the query
     * @return array $online_locations Online only locations
     */
    public function online_only( $args = [] ) {
        $online_locations = [];
        $ids = $this->find_online_ids( $args );

        if ( $ids && function_exists( 'wpsl_get_service' ) ) {

            /*
             * The id query populates neither the post nor the meta cache, so
             * without this every location below costs a get_post() and a
             * get_post_meta() query of its own. Terms are primed too, the
             * response includes the store categories.
             */
            _prime_post_caches( array_map( function( $location ) {
                return (int) $location->ID;
            }, $ids ), true, true );

            $api_service = wpsl_get_service( 'api' );

            foreach ( $ids as $k => $location ) {
                $online_locations[] = $this->escape_for_template( $api_service->get( $location->ID ) );
            }
        }

        return $online_locations;
    }

    /**
     * Escape an online location for the store listing templates.
     *
     * Api\Service::format_response() returns raw post and meta values (it
     * also serves API consumers that escape at their own layer). The listing
     * templates interpolate with <%= %>, which doesn't escape, so anything
     * reaching them must be escaped first, the same way
     * Frontend\Store\Data::get_meta_data() escapes a physical store. Without
     * this an online store's title, url, email, phone or fax reaches the DOM
     * exactly as it sits in the database.
     *
     * The description is left alone: physical stores render it as HTML through
     * the_content, so escaping it here would make the two paths disagree.
     *
     * @since  3.0.0
     * @param  array $location The raw location data
     * @return array $location The location data, safe to interpolate
     */
    private function escape_for_template( $location ) {
        if ( ! is_array( $location ) ) {
            return $location;
        }

        if ( isset( $location['store'] ) ) {
            $location['store'] = esc_html( $location['store'] );
        }

        if ( isset( $location['url'] ) ) {
            $location['url'] = esc_url( $location['url'] );
        }

        if ( isset( $location['email'] ) ) {
            $location['email'] = esc_attr( $location['email'] );
        }

        if ( isset( $location['phone'] ) ) {
            $location['phone'] = esc_attr( sanitize_text_field( stripslashes( $location['phone'] ) ) );
        }

        if ( isset( $location['fax'] ) ) {
            $location['fax'] = esc_attr( sanitize_text_field( stripslashes( $location['fax'] ) ) );
        }

        return $location;
    }

    /**
     * Return post ID's for the online stores.
     *
     * @since  3.0.0
     * @param  array $args   Optionally the limit and offset for the query
     * @return array $online ID's of online stores
     */
    public function find_online_ids( $args = [] ) {
        $query_args = [
            'post_type'      => 'wpsl_stores',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'wpsl_online',
                    'value'   => '1',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => isset( $args['limit'] ) ? absint( $args['limit'] ) : -1,
            'offset'         => isset( $args['offset'] ) ? absint( $args['offset'] ) : 0,
            'no_found_rows'  => true,
        ];

        $online_ids = get_posts( $query_args );

        // If the rest of your plugin strictly expects objects (e.g., $item->ID), map it like this:
        $online = array_map( function( $id ) {
            $obj = new \stdClass();
            $obj->ID = (string) $id;
            return $obj;
        }, $online_ids );

        return $online;
    }

    /**
     * Make sure the location that belongs to
     * the passed ID has coordinates set.
     *
     * @since  3.0.0
     * @param  int  $post_id
     * @return bool
     */
    public function coordinates_set( $post_id ) {
        $set    = true;
        $fields = [ 'lat', 'lng' ];

        foreach ( $fields as $field ) {
            if ( ! get_post_meta( $post_id, 'wpsl_' . $field, true ) ) {
                $set = false;

                break;
            }
        }

        return $set;
    }

    /**
     * Map the fields from the passed arguments to
     * those used with wp_insert_post / wp_update_post.
     *
     * @since  3.0.0
     * @see    https://codex.wordpress.org/Function_Reference/wp_insert_post#Parameters
     * @return array $field_map
     */
    public function get_post_fields_map() {
        $field_map = apply_filters( 'wpsl_api_post_args_fields', [
            'wpsl_id'     => 'ID',
            'store'       => 'post_title',
            'status'      => 'post_status',
            'permalink'   => 'post_name',
            'description' => 'post_content',
            'excerpt'     => 'post_excerpt',
            'author'      => 'post_author',
            'date'        => 'post_date'
        ] );

        return $field_map;
    }

    /**
     * Map the passed fields to the one used
     * with wp_insert_post / wp_update_post
     *
     * @since  3.0.0
     * @param  array $args
     * @return array $args
     */
    public function set_post_args( $args ) {
        $fields_map = $this->get_post_fields_map();
        $post_args  = [];

        foreach ( $fields_map as $wpsl_key => $post_key ) {
            if ( isset( $args[$wpsl_key] ) && $args[$wpsl_key] ) {
                $post_args[$post_key] = $args[$wpsl_key];
                unset( $args[$wpsl_key] );
            }
        }

        $args = array_merge( $args, $post_args );

        $args['post_type'] = 'wpsl_stores';

        if ( ! isset( $args['post_status'] ) ) {
            $args['post_status'] = 'publish';
        }

        return apply_filters( 'wpsl_api_default_post_args', $args );
    }

    /**
     * Return all locations that don't
     * have any coordinates set in the
     * meta fields.
     *
     * @since   3.0.0
     * @return  array $post_ids The ID's from locations that don't have coordinates set.
     */
    public function get_uncoded() {

        $args = [
            'post_type'   => 'wpsl_stores',
            'post_status' => [ 'publish', 'draft', 'pending', 'future', 'private' ],
            'numberposts' => -1,
            'fields'      => 'ids',
            'meta_query'  => [
                'relation' => 'OR',
                [ 'key' => 'wpsl_lat', 'value' => '', 'compare' => '=' ],
                [ 'key' => 'wpsl_lat', 'compare' => 'NOT EXISTS' ],
            ]
        ];

        $post_ids = get_posts( $args );

        /*
         * The meta query matches when ANY wpsl_lat row is empty, and a store
         * can carry more than one (an importer using add_post_meta() writes a
         * second set). The rest of the plugin reads the first row, so a store
         * with real coordinates there isn't waiting to be geocoded. Priming
         * the meta cache keeps the re-check to one extra query.
         */
        if ( $post_ids ) {
            update_meta_cache( 'post', $post_ids );

            $post_ids = array_values( array_filter( $post_ids, function ( $post_id ) {
                return '' === (string) get_post_meta( $post_id, 'wpsl_lat', true );
            } ) );
        }

        return $post_ids;
    }

    /**
     * Get a list of unique meta values
     * based on the passed key.
     *
     * @since  3.0.0
     * @param  string $key WPSL meta key
     * @return array  $result
     */
    function get_unique_meta_values( $key ) {
        global $wpdb;

        $cache_key   = 'wpsl_unique_meta_' . md5( $key );
        $cache_group = 'wpsl';
        $result      = wp_cache_get( $cache_key, $cache_group );

        if ( false === $result ) {
            $sql = "SELECT DISTINCT pm.meta_value
                        FROM {$wpdb->postmeta} pm
                    LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                        WHERE pm.meta_key = %s
                        AND TRIM( pm.meta_value ) > ''
                        AND p.post_type = 'wpsl_stores'
                        AND p.post_status NOT IN ( 'trash', 'auto-draft' )
                    ORDER BY pm.meta_value ASC";

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- No native WP function for DISTINCT meta values. SQL is prepared below.
            $result = $wpdb->get_col( $wpdb->prepare( $sql, $key ) );

            // Cache for 12 hours
            wp_cache_set( $cache_key, $result, $cache_group, 12 * HOUR_IN_SECONDS );
        }

        return $result;
    }

    /**
     * See if there are locations with identical coordinates.
     *
     * @since 3.0.0
     * @todo unused?
     * @todo check what happens if there are 3 locations with the same coordinates, show additional column linking to duplicate ids?
     * @return array $duplicate_ids Ids and coordinates of locations that have a duplicate
     */
    public function get_duplicate_latlng_ids() {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT GROUP_CONCAT( posts.ID ) AS ID, post_lat.meta_value AS lat, post_lng.meta_value AS lng
                  FROM $wpdb->posts AS posts
            INNER JOIN $wpdb->postmeta AS post_lat ON post_lat.post_id = posts.ID AND post_lat.meta_key = %s
                   AND NOT EXISTS ( SELECT 1 FROM $wpdb->postmeta AS pick_lat WHERE pick_lat.post_id = posts.ID AND pick_lat.meta_key = %s AND pick_lat.meta_id < post_lat.meta_id )
            INNER JOIN $wpdb->postmeta AS post_lng ON post_lng.post_id = posts.ID AND post_lng.meta_key = %s
                   AND NOT EXISTS ( SELECT 1 FROM $wpdb->postmeta AS pick_lng WHERE pick_lng.post_id = posts.ID AND pick_lng.meta_key = %s AND pick_lng.meta_id < post_lng.meta_id )
                 WHERE posts.post_type = %s
                   AND posts.post_status = %s
              GROUP BY post_lat.meta_value HAVING COUNT( * ) > 1",
            'wpsl_lat',
            'wpsl_lat',
            'wpsl_lng',
            'wpsl_lng',
            'wpsl_stores',
            'publish'
        );
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required for GROUP_CONCAT and HAVING COUNT aggregation. Caching omitted as this is a real-time admin utility check. SQL prepared above.
        $duplicate_ids = $wpdb->get_results( $sql );

        return $duplicate_ids;
    }

    /**
     * Clean a single latitude or longitude value before it is stored.
     *
     * Everything that writes coordinates goes through here.
     *
     * @since  3.0.0
     * @param  mixed  $value The submitted coordinate
     * @param  string $axis  Which half of the pair this is, 'lat' or 'lng'.
     *                       Decides the allowed range.
     * @return string The coordinate, or an empty string when it is unusable
     */
    public static function sanitize_coordinate( $value, $axis = 'lat' ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = trim( (string) $value, " \t\n\r\0\x0B'\"`" );

        if ( 1 === substr_count( $value, ',' ) && false === strpos( $value, '.' ) ) {
            $value = str_replace( ',', '.', $value );
        }

        if ( ! is_numeric( $value ) ) {
            return '';
        }

        $limit = ( 'lng' === $axis ) ? 180 : 90;
        $value = (float) $value;

        if ( ( $value > $limit ) || ( $value < -$limit ) ) {
            return '';
        }

        // Cap the precision at 6 decimals ( ~0.11 m ), same as validate_latlng().
        return (string) round( $value, 6 );
    }

    /**
     * Validate the latlng values.
     * 
     * @since  3.0.0
     * @param  string        $lat    The latitude value
     * @param  string        $lng    The longitude value
     * @return boolean|array $latlng The validated latlng values or false if it fails
     */
    public static function validate_latlng( $lat, $lng ) {
        if ( ! is_numeric( $lat ) || ( $lat > 90 ) || ( $lat < -90 ) ) {
            return false;
        }

        if ( ! is_numeric( $lng ) || ( $lng > 180 ) || ( $lng < -180 ) ) {
            return false;
        }

        // cap the coordinates at 6 decimals ( ~0.11 m precision ).
        $latlng = self::format_latlng( [
            'lat' => $lat,
            'lng' => $lng
        ] );

        return $latlng;
    }

    /**
     * Make sure the latlng value has a max of 6 decimals.
     * 
     * @since  3.0.0
     * @param  array $latlng The latlng data
     * @return array $latlng The formatted latlng
     */
    public static function format_latlng( $latlng ) {
        foreach ( $latlng as $key => $value ) {
            if ( strlen( substr( strrchr( $value, '.' ), 1 ) ) > 6 ) {
                $latlng[$key] = round( $value, 6 );
            }
        }
        
        return $latlng;
    }
}