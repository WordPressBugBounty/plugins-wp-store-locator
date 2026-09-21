<?php
/**
 * Handle the different search types that are different
 * from the normal location ( coordinates based ) searches.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Search;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Utils\Location_Utils;
use WPSL\Frontend\Store\Data as StoreData;

class Types {

    /**
     * @since 3.0.0
     */
    public $search_args;
    
    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;
    
    /**
     * Store data instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Store\Data
     */
    private $store_data;

    /**
     * Location utils instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Utils\Location_Utils
     */
    private $location_utils;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager     $settings       Settings manager instance
     * @param \WPSL\Frontend\Store\Data       $store_data     Store data instance
     * @param \WPSL\Core\Utils\Location_Utils $location_utils Location utils instance
     */
    public function __construct( WpslSettings $settings, StoreData $store_data, Location_Utils $location_utils ) {
        $this->settings = $settings;
        $this->store_data = $store_data;
        $this->location_utils = $location_utils;
    }

    /**
     * Check which search code to run
     * based on the passed 'types' value
     *
     * @since  3.0.0
     * @return void
     */
    public function init() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public search parameter, no state change
        if ( isset( $_GET['types'] ) ) {
            $this->prepare_search();
        }
    }

    /**
     * Prepare the search.
     *
     * @since  3.0.0
     * @param  array $args
     * @return void
     */
    public function prepare_search( $args = [] ) {
        if ( empty( $args ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public search parameters, no state change
            $args = $_GET;
        }

        $this->search_args = apply_filters( 'wpsl_default_search_args', [
            'post_type'   => 'wpsl_stores',
            'post_status' => 'publish',
            'fields'      => 'ids',
            'numberposts' => $this->get_numberposts()
        ] );

        // Multiple comma-separated types are supported ( e.g. types=name,country );
        // each runs its own *_search_args handler and the arguments compose.
        // Covered by tests/unit/frontend/search/TypesTest.php.
        $types = explode( ',', $args['types'] );

        /**
         * Extend the search arguments based on the passed type.
         *
         * There's support for country, category, state or name searches, and
         * add-ons can hook a custom type through the wpsl_{type}_search_args
         * action.
         */
        foreach ( $types as $type ) {
            $name = $type . '_search_args';

            if ( method_exists( $this, $name ) ) {
                call_user_func( [ $this, $name ], $args );
            } else if ( has_action( 'wpsl_' . $name ) ) {
                do_action( 'wpsl_' . $name, $args, $this->search_args );
            }
        }

        // Make sure the results are filtered by a taxonomy.
        if ( isset( $args['filter'] ) && ! isset( $this->search_args['tax_query'] ) ) {
            $this->taxonomy_search_args( $args );
        }

        $this->run_search();
    }

    /**
     * Get the results limit.
     *
     * @since  3.0.0
     * @return string $numberposts
     */
    public function get_numberposts() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public search parameter, no state change
        $max_results = isset( $_GET['max_results'] ) ? absint( $_GET['max_results'] ) : 0;

        /*
         * The same ceiling the radius search applies ( Filters::check_allowed_filter_value() ):
         * the largest value in the max results list on the settings page. A
         * larger request falls back to the default, so one public request
         * can't ask for the whole store table.
         */
        $search_settings = $this->settings->get_group( 'search' );
        $ceiling         = isset( $search_settings['max_results'] ) ? max( array_map( 'absint', explode( ',', str_replace( [ '[', ']' ], '', (string) $search_settings['max_results'] ) ) ) ) : 0;

        if ( $max_results && $max_results <= $ceiling ) {
            $numberposts = $max_results;
        } else {
            $numberposts = $this->get_default_filter_value( 'max_results' );
        }

        return $numberposts;
    }

    /**
     * Get the default selected value for a dropdown.
     *
     * @since  1.0.0
     * @param  string $type     The request list type
     * @return string $response The default list value
     */
    public function get_default_filter_value( $type ) {
        $settings    = $this->settings->get_group( 'search' );
        $list_values = explode( ',', $settings[$type] );

        foreach ( $list_values as $k => $list_value ) {
            // The default radius has a [] wrapped around it, so we check for that and filter out the [].
            if ( strpos( $list_value, '[' ) !== false ) {
                $response = filter_var( $list_value, FILTER_SANITIZE_NUMBER_INT );
                break;
            }
        }

        return $response;
    }

    /**
     * Check if we need to include the arguments
     * to filter the data on the selected categories.
     * 
     * This can be called via types=taxonomy for category-only searches.
     *
     * @since  3.0.0
     * @param  array $args Search arguments
     * @return void
     */
    public function taxonomy_search_args( $args ) {
        if ( ! isset( $args['filter'] ) ) {
            return;
        }

        $filter_ids = array_map( 'absint', explode( ',', wpsl_normalize_category_filter( $args['filter'] ) ) );

        $this->search_args['tax_query'][] = [
            'taxonomy' => 'wpsl_store_category',
            'field'    => 'term_id',
            'terms'    => $filter_ids,
            'operator' => 'IN',
        ];
    }

    /**
     * Set the search argument.
     *
     * @since 3.0.0
     * @param  array $args
     * @return void
     */
    public function name_search_args( $args ) {
        $this->search_args['s'] = sanitize_text_field( $args['search'] );
    }

    /**
     * Make a search based on the provided country name / iso code.
     *
     * @since   3.0.0
     * @param   array $args The submitted AJAX data
     * @return  void
     */
    public function country_search_args( $args ) {
        $values = $this->get_location_values( $args, 'country' );

        // Some add-ons pass the iso code separately instead of via location[country].
        if ( ( ! is_array( $values ) || ! $values ) && isset( $args['country_iso'] ) && $args['country_iso'] ) {
            $values = [ sanitize_text_field( $args['country_iso'] ) ];
        }

        if ( ! is_array( $values ) || ! $values ) {
            return;
        }

        $search = [];

        /**
         * A selected value can be a full country name or a 2 letter ISO code.
         * The geocoded dropdown passes a single "Name,ISO" pair, while the
         * checkbox filter passes one or more country names. Matching each
         * value against both meta keys covers all of those formats and keeps
         * the original single country behaviour intact.
         */
        foreach ( $values as $value ) {
            if ( $value === '' ) {
                continue;
            }

            $search[] = [ 'key' => 'country',     'value' => $value ];
            $search[] = [ 'key' => 'country_iso', 'value' => $value ];
        }

        if ( ! $search ) {
            return;
        }

        $meta_args = [
            'relation' => 'OR',
            'search'   => $search,
        ];

        $meta_query = $this->create_meta_query( apply_filters( 'wpsl_country_meta_query_args', $meta_args ) );

        $this->add_meta_query( $meta_query );
    }

    /**
     * Take out the location data from the passed args.
     *
     * @since 3.0.0
     * @param array  $args The submitted AJAX data
     * @param string $type The location field to read ( e.g. 'country', 'state' )
     * @return array|string The sanitized values, or '' when the field is absent
     */
    public function get_location_values( $args, $type ) {
        $values = '';

        if ( isset( $args['location'] ) && array_key_exists( $type, $args['location'] ) ) {
            $values = array_map('sanitize_text_field', explode( ',', $args['location'][$type] ) );
        }

        return $values;
    }

    /**
     * Make a search based on the provided state / province name
     *
     * @since  3.0.0
     * @param  array $args The submitted AJAX data
     * @return void
     */
    public function state_search_args( $args ) {
        $meta_args = [];
        $values = $this->get_location_values( $args, 'state' );

        if ( is_array( $values ) && $values ) {
            $meta_args = [
                'relation' => 'OR',
                'search'   => [
                    [
                        'key'   => 'state',
                        'value' => $values[0]
                    ]
                ]
            ];

            /**
             * Check if we have multiple state values. This can happen if
             * both the long and short names are passed. ( 'California','CA' ).
             */
            if ( count( $values ) > 1 ) {
                array_shift( $values );

                foreach ( $values as $k => $value ) {
                    $meta_args['search'][] = [
                        'key'   => 'state',
                        'value' => $value
                    ];
                }
            }
        }

        $meta_query = $this->create_meta_query( apply_filters( 'wpsl_state_meta_query_args', $meta_args ) );

        $this->add_meta_query( $meta_query );
    }

    /**
     * Add a handler's meta query to the search, next to the ones already there.
     *
     * Each handler builds its own OR group ( Germany or DE ). Combining
     * types means every group must hold, so a second group joins the first
     * under an AND instead of replacing it: array_merge() used to overwrite
     * the whole meta_query, and the handler that ran last was the only
     * restriction left.
     *
     * @since 3.0.0
     * @param array $args What create_meta_query() returned.
     * @return void
     */
    private function add_meta_query( $args ) {
        $meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : [];

        unset( $args['meta_query'] );

        // Anything else wpsl_meta_query_filter added is merged as before.
        $this->search_args = array_merge( $this->search_args, $args );

        // A group without a single clause restricts nothing, and must not wipe one that does.
        if ( ! array_filter( $meta_query, 'is_array' ) ) {
            return;
        }

        if ( empty( $this->search_args['meta_query'] ) ) {
            $this->search_args['meta_query'] = $meta_query;
        } else {
            $this->search_args['meta_query'] = [
                'relation' => 'AND',
                $this->search_args['meta_query'],
                $meta_query,
            ];
        }
    }

    /**
     * Create the arguments for the meta query.
     *
     * @since  3.0.0
     * @param  array $meta_args The meta key to use in the $args param
     * @return array $args      The argument for the meta query
     */
    public function create_meta_query( $meta_args ) {
        $args = [
            'meta_query' => []
        ];

        if ( isset( $meta_args['relation'] ) ) {
            $args['meta_query']['relation'] = $meta_args['relation'];
        }

        if ( isset( $meta_args['search'] ) ) {
            foreach ( $meta_args['search'] as $meta_arg ) {
                $args['meta_query'][] = [
                    'key'     => 'wpsl_' . $meta_arg['key'],
                    'value'   => $meta_arg['value']
                ];
            }
        }

        return apply_filters( 'wpsl_meta_query_filter', $args );
    }

    /**
     * Run the search
     *
     * @since 3.0.0
     * @param array $args Arguments used to run the search
     */
    public function run_search( $args = [] ) {
        if ( empty( $args ) ) {
            $args = $this->search_args;
        }

        $location_ids = $this->get_location_ids( $args );

        // Grab the location metadata and return the search results.
        $results = $this->process_results( $location_ids );

        do_action( 'wpsl_store_search' );

        /**
         * Maybe convert the results to geoJSON format ( Mapbox only ).
         *
         * The standard search path does this too. Without it a category /
         * taxonomy search returns a plain array, which the Mapbox JS can't
         * batch-render, so only a single marker ends up on the map.
         */
        if ( $results && wpsl_container()->has( 'geojson' ) ) {
            $results = wpsl_get_service( 'geojson' )->create( $results );
        }

        wp_send_json( $results );

        exit();
    }

    /**
     * Retrieves the WPSL post ID's based on the passed $args,
     * and use them to grab the location metadata.
     *
     * @since  3.0.0
     * @param  array $args   The get_posts arguments
     * @return array $stores The ID's of the matching locations
     */
    public function get_location_ids( $args ) {
        $stores = [];
        $ids    = get_posts( apply_filters( 'wpsl_get_location_ids_args', $args ) );

        // We need to do this to make it work with get_store_meta_data()
        foreach ( $ids as $id ) {
            $stores[] = ( object ) [ 'ID' => $id ];
        }

        return $stores;
    }

    /**
     * Process the search results by getting metadata for each store.
     *
     * @since  3.0.0
     * @param  array $stores Array of store objects with ID property
     * @return array Processed store results with metadata
     */
    public function process_results( $stores ) {
        if ( empty( $stores ) ) {
            $results = [];
        } else {
            $results = apply_filters( 'wpsl_store_data', $this->store_data->get_meta_data( $stores ) );
        }

        /**
         * Optionally append the online-only locations, just like the standard
         * search path does. They have no coordinates, so the geoJSON conversion
         * keeps them in a separate 'online' list for the frontend to render.
         */
        if ( $this->settings->get( 'editor', 'enable_online_only' ) && $this->location_utils->online_only_exists() && apply_filters( 'wpsl_ignore_empty_stores', true ) ) {
            $results = $this->include_online_locations( $results );
        }

        return $results;
    }

    /**
     * Make sure to include online locations.
     *
     * @since 3.0.0
     * @param  array $stores The collected search results.
     * @return array $stores The results with the online-only locations merged in.
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
}