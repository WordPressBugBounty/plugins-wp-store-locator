<?php
/**
 * Handle the frontend store data.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Hours\Service as Hours;

class Data {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;
    
    /**
     * Translations instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;
    
    /**
     * Hours instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Hours\Service
     */
    private $hours;
    
    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager  $settings Settings manager instance
     * @param \WPSL\Core\I18n\Translations $i18n     Translations manager instance
     * @param \WPSL\Core\Hours\Service     $hours    Hours manager instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n, Hours $hours ) {
        $this->settings = $settings;
        $this->i18n = $i18n;
        $this->hours = $hours;
    }

    /**
     * Get the post metadata for the selected stores.
     *
     * @since  2.0.0
     * @param  object $stores
     * @return array  $all_stores The stores that fall within the selected range with the post metadata.
     */
    public function get_meta_data( $stores ) {
        $wpsl_settings = $this->settings->get_all();

        $all_stores = [];

        // Get the list of store fields that we need to filter out of the post metadata.
        $meta_field_map = $this->frontend_meta_fields();

        // Check if any category has an image set (cached for performance)
        $has_category_images = $this->has_any_category_images();

        /**
         * Batch-prime the post, meta and term caches for the whole result
         * set: the search is a raw $wpdb query, so the get_post() /
         * get_the_title() calls below would otherwise be an N+1.
         *
         * WPML ids resolve first, so the loop's ids get primed, and
         * untranslatable locations are dropped before the loop.
         */
        $is_wpml_compatible = $this->i18n->is_wpml_compatible();
        $prime_ids          = [];
        $seen_ids           = [];

        foreach ( $stores as $store_key => $store ) {
            if ( $is_wpml_compatible ) {
                $store->ID = $this->i18n->maybe_get_wpml_id( $store->ID );

                // Translations of one store resolve to the same ID, so a
                // duplicate here is the same store in another language.
                // Callers that skip Search::dedupe_translated_stores()
                // must not render it twice.
                if ( ! $store->ID || isset( $seen_ids[ $store->ID ] ) ) {
                    unset( $stores[ $store_key ] );
                    continue;
                }

                $seen_ids[ $store->ID ] = true;
            }

            $prime_ids[] = $store->ID;
        }

        if ( $prime_ids ) {
            _prime_post_caches( $prime_ids, true, true );

            $thumb_ids = [];

            foreach ( $prime_ids as $prime_id ) {
                $thumb_id = get_post_thumbnail_id( $prime_id );

                if ( $thumb_id ) {
                    $thumb_ids[] = $thumb_id;
                }
            }

            if ( $thumb_ids ) {
                _prime_post_caches( $thumb_ids, false, true );
            }
        }

        foreach ( $stores as $store_key => $store ) {
            /*
             * Fresh row per store. Several keys below are written only when
             * the store has something to write (per-location marker, active
             * marker, distance). Without this reset the previous store's
             * value lingers when the guard skips, so a store with a marker
             * would leak it to every markerless store after it.
             */
            $store_meta = [];

            // Get the post metadata for each store that was within the range of the search radius.
            $custom_fields = get_post_custom( $store->ID );

            $store_meta['id']    = (int) $store->ID;
            $store_meta['store'] = esc_html( get_the_title( $store->ID ) );

            foreach ( $meta_field_map as $meta_key => $meta_value ) {
                if ( isset( $custom_fields[$meta_key][0] ) ) {
                    if ( ( isset( $meta_value['type'] ) ) && ( ! empty( $meta_value['type'] ) ) ) {
                        $meta_type = $meta_value['type'];
                    } else {
                        $meta_type = '';
                    }

                    /**
                     * If we need to hide the opening hours,
                     * and the current meta type is set to hours we skip it.
                     */
                    if ( $wpsl_settings['editor']['hide_hours'] && $meta_type == 'hours' ) {
                        continue;
                    }

                    /**
                     * Make sure the data is safe to use on the
                     * frontend and in the format we expect it to be.
                     */
                    switch ( $meta_type ) {
                        case 'numeric':
                            $meta_data = ( is_numeric( $custom_fields[$meta_key][0] ) ) ? $custom_fields[$meta_key][0] : 0 ;
                            break;
                        case 'email':
                            $meta_data = esc_attr( $custom_fields[$meta_key][0] );
                            break;
                        case 'phone':
                            // Rendered inside href="tel:..." by formatPhoneNumber() through the raw <%= %> tag, so escape for the attribute context.
                            $meta_data = esc_attr( sanitize_text_field( stripslashes( $custom_fields[$meta_key][0] ) ) );
                            break;
                        case 'url':
                            $meta_data = esc_url( $custom_fields[$meta_key][0] );
                            break;
                        case 'hours':
                            $formatted_hours = $this->format_hours_meta( $store->ID );
                            $meta_data       = $formatted_hours['hours'];

                            // The plain status (no link wrapper) is used by the popup template.
                            $store_meta['hours_status'] = $formatted_hours['status'];
                            break;
                        case 'wp_editor':
                        case 'textarea':
                            $meta_data = wp_kses_post( wpautop( $custom_fields[$meta_key][0] ) );
                            break;
                        case 'text':
                        default:
                            $meta_data = sanitize_text_field( stripslashes( $custom_fields[$meta_key][0] ) );
                            break;
                            
                    }

                    $store_meta[ $meta_value['name'] ] = $meta_data;

                    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                        if ( $meta_type == 'hours' ) {
                            $store_meta['hours_raw'] = $custom_fields[$meta_key][0];
                        }
                    }
                } else {
                    $store_meta[ $meta_value['name'] ] = '';
                }
            }

            /**
             * Include the post content if the "More info" option is enabled on the settings page,
             * or if $include_post_content is set to true through the 'wpsl_include_post_content' filter.
             */
            if ( $wpsl_settings['ux']['description'] || apply_filters( 'wpsl_include_post_content', false ) ) {
                $page_object = get_post( $store->ID );

                // Check if we need to strip the shortcode from the post content.
                if ( apply_filters( 'wpsl_strip_content_shortcode', true ) ) {
                    $post_content = strip_shortcodes( $page_object->post_content );
                } else {
                    $post_content = $page_object->post_content;
                }

                $store_meta['description'] = apply_filters( 'the_content', $post_content );
            }

            if ( $wpsl_settings['local_pages']['permalinks'] ) {
                $store_meta['permalink'] = get_permalink( $store->ID );
            }

            /**
             * Include the location status metadata ( temporarily / permanently
             * closed ) and, when a status is shown, remove the hours itself.
             */
            $store_meta['location_status'] = $this->get_location_status_msg( $store->ID );

            if ( $store_meta['location_status'] ) {
                $store_meta['hours'] = '';
            }

            $store_meta['thumb'] = $this->get_store_thumb( $store->ID, $store_meta['store'] );

            if ( $has_category_images ) {
                $category_markers = $this->get_category_image( $store->ID );

                $store_meta['categoryMarkerUrl'] = ! empty( $category_markers['normal'] ) ? $category_markers['normal'] : '';
                $store_meta['categoryMarkerUrlActive'] = ! empty( $category_markers['active'] ) ? $category_markers['active'] : '';
            }

            $location_marker = get_post_meta( $store->ID, 'wpsl_location_marker', true );

            if ( $location_marker ) {
                $location_marker_src = wpsl_marker_src( $location_marker );

                if ( $location_marker_src ) {
                    $store_meta['locationMarkerUrl'] = $location_marker_src;
                }
            }

            $location_marker_active = get_post_meta( $store->ID, 'wpsl_location_marker_active', true );

            if ( $location_marker_active ) {
                $location_marker_active_src = wpsl_marker_src( $location_marker_active );

                if ( $location_marker_active_src ) {
                    $store_meta['locationMarkerUrlActive'] = $location_marker_active_src;
                }
            }

            /**
             * Gate the distance key on is_numeric, not truthiness: a store on
             * the exact searched coordinates is 0 away (a real distance, not
             * missing), while an absent key means "never calculated" (name
             * search, online-only locations) - which the listing template
             * branches on.
             */
            if ( ! $wpsl_settings['ux']['hide_distance'] && isset( $store->distance ) && is_numeric( $store->distance ) ) {
                $store_meta['distance'] = number_format( (float) $store->distance, 1, '.', '' );
            }

            /**
             * If the user selected the 'open now' checkbox, then make sure
             * that locations that are currently closed are exlcuded.
             */
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public filter parameter, not a form submission
            if ( isset( $store_meta['hours'] ) && $store_meta['hours'] == 'closed' && isset( $_GET['open_only'] ) ) {
                continue;
            }

            $all_stores[] = apply_filters( 'wpsl_store_meta', $store_meta, $store->ID );
        }

        $all_stores = $this->sort_search_results( $all_stores );

        return $all_stores;
    }

    /**
     * Format a store's opening hours for the search results.
     *
     * @since  3.0.0
     * @param  int   $store_id The store ID
     * @return array           'hours' holds the markup shown in the results,
     *                         'status' the plain open / closed status used by
     *                         the marker popup. Both are always strings.
     */
    public function format_hours_meta( $store_id ) {
        $empty = [
            'hours'  => '',
            'status' => ''
        ];

        $hours = get_post_meta( $store_id, 'wpsl_hours' );

        if ( ! $hours ) {
            return $empty;
        }

        $hours = $this->hours->prepare_output( $hours[0], [ 'store_id' => $store_id ] );

        /**
         * Locations upgraded from 1.x keep hours as a free-form textarea, and
         * prepare_output() returns ready-made HTML instead of the
         * status/table array the dropdown format returns. Without this, 1.x
         * sites silently showed no hours. No open/closed status can be derived
         * from free text, so it stays empty.
         */
        if ( is_string( $hours ) ) {
            return [
                'hours'  => $hours,
                'status' => ''
            ];
        }

        // prepare_output() returns null when every day is set to closed.
        if ( ! is_array( $hours ) || ! isset( $hours['status'], $hours['table'] ) ) {
            return $empty;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public filter parameter, not a form submission
        if ( $hours['status'] == 'closed' && isset( $_GET['open_only'] ) ) {
            $formatted = $hours['status'];
        } else {
            $formatted = $hours['table'];
        }

        return [
            'hours'  => $formatted,
            'status' => isset( $hours['status_plain'] ) ? $hours['status_plain'] : ''
        ];
    }

    /**
     * Collect the extra fields for the landing page marker popup.
     *
     * Single store page map markers only carry name and address by default.
     * This returns contact details, post content and/or opening hours status,
     * but only for data types with 'landing_page_marker_popup' selected on the
     * settings page. Keys mirror the cpt_info_window Underscore template vars.
     *
     * @since  3.0.0
     * @param  int   $store_id The store ID
     * @return array           The extra marker popup fields
     */
    public function get_marker_popup_data( $store_id ) {
        $ux     = $this->settings->get_group( 'ux' );
        $extras = [];

        // Contact details. Always set the keys so the template's <% if ( phone ) %> checks are safe.
        if ( in_array( 'landing_page_marker_popup', $ux['contact_details'], true ) ) {
            $extras['phone'] = esc_attr( sanitize_text_field( get_post_meta( $store_id, 'wpsl_phone', true ) ) );
            $extras['fax']   = esc_attr( sanitize_text_field( get_post_meta( $store_id, 'wpsl_fax', true ) ) );
            $extras['email'] = sanitize_email( get_post_meta( $store_id, 'wpsl_email', true ) );
            $extras['url']   = esc_url_raw( get_post_meta( $store_id, 'wpsl_url', true ) );
        }

        // Post content / description.
        if ( in_array( 'landing_page_marker_popup', $ux['description'], true ) ) {
            $page_object = get_post( $store_id );

            if ( $page_object ) {
                if ( apply_filters( 'wpsl_strip_content_shortcode', true ) ) {
                    $post_content = strip_shortcodes( $page_object->post_content );
                } else {
                    $post_content = $page_object->post_content;
                }

                $extras['description'] = apply_filters( 'the_content', $post_content );
            }
        }

        // Opening hours status (short open / closed status), matching the regular marker popup.
        if ( in_array( 'landing_page_marker_popup', $ux['hours'], true ) && $this->settings->get( 'ux', 'show_hour_status' ) && ! $this->settings->get( 'editor', 'hide_hours' ) ) {
            $hours = get_post_meta( $store_id, 'wpsl_hours' );

            if ( $hours ) {
                $hours = $this->hours->prepare_output( $hours[0], [ 'store_id' => $store_id ] );

                if ( is_array( $hours ) && isset( $hours['status_plain'] ) ) {
                    $extras['hours_status'] = $hours['status_plain'];
                }
            }
        }

        return apply_filters( 'wpsl_marker_popup_data', $extras, $store_id );
    }

    /**
     * Sort the search results by the
     * selected settings page value.
     *
     * @since  3.0.0
     * @param  array $stores The collected search results
     * @param  array $args   The sorting arguments ( order, by, flags )
     * @return array $stores The sorted search results
     */
    public function sort_search_results( $stores, $args = [] ) {
        $order_options = [
            'asc'  => SORT_ASC,
            'desc' => SORT_DESC
        ];

        $flags = [
            'regular' => SORT_REGULAR,
            'numeric' => SORT_NUMERIC,
            'string'  => SORT_STRING,
            'case'    => SORT_NATURAL|SORT_FLAG_CASE
        ];

        $order_by = ( isset( $args['orderby'] ) ) ? $args['orderby'] : $this->settings->get( 'search', 'orderby' );

        if ( isset( $args['order'] ) ) {
            $order_key = strtolower( $args['order'] );
            $order = isset( $order_options[ $order_key ] ) ? $order_options[ $order_key ] : SORT_ASC;
        } else {
            $order_key = strtolower( $this->settings->get( 'search', 'order' ) );
            $order = isset( $order_options[ $order_key ] ) ? $order_options[ $order_key ] : SORT_ASC;
        }

        if ( isset( $args['flag'] ) && isset( $flags[ $args['flag'] ] ) ) {
            $flag = $flags[ $args['flag'] ];
        } else if ( $this->settings->get( 'search', 'orderby' ) == 'store' ) {
            $flag = $flags['case'];
        } else {
            $flag = $flags['regular'];
        }

        $sort_args = apply_filters( 'wpsl_multisort_order_args', [
            'order' => $order,
            'flag'  => $flag
        ] );

        // Make sure the selected sort value exists in the data
        $will_sort = isset( $stores[0][$order_by] );

        if ( $will_sort ) {
            $has_value   = [];
            $custom_sort = [];

            foreach ( $stores as $key => $row ) {
                $value = $row[$order_by];

                /**
                 * Locations missing the sort field (e.g. no zip) should sort
                 * after populated ones regardless of direction. Otherwise an
                 * empty string sorts "smaller" and bubbles to the top of an
                 * ascending sort. A numeric 0 (e.g. distance) is real, so
                 * only '' and null count as missing.
                 */
                $has_value[$key]   = ( $value === '' || $value === null ) ? 0 : 1;
                $custom_sort[$key] = $value;
            }

            array_multisort(
                $has_value, SORT_DESC, SORT_NUMERIC,
                $custom_sort, $sort_args['order'], $sort_args['flag'],
                $stores
            );
        }

        return $stores;
    }

    /**
     * See which location status message is set.
     *
     * @since  3.0.0
     * @param  int    $store_id
     * @return string $location_status
     */
    function get_location_status_msg( $store_id ) {
        $status = get_post_meta( $store_id, 'wpsl_location_status', true );
        $status_options = wpsl_get_location_status_options();

        if ( isset( $status_options[$status] ) ) {
            $status_txt = $status_options[$status];
        } else {
            $status_txt = '';
        }

        if ( $status !== 'open' && $status_txt ) {
            $location_status = $status_txt;

            if ( $status == 'temporarily_closed' ) {
                $reopen = get_post_meta( $store_id, 'wpsl_reopens', true );

                if ( is_numeric( $reopen ) ) {
                    if ( $reopen <= time() ) {
                        // Not through the container: its location_status service is admin-only.
                        \WPSL\Admin\Utils\Location_Status::reopen_location( $store_id );
                        $location_status = '';
                    } else {
                        /* translators: 1: status text (e.g., "Closed"), 2: line break, 3: reopening date */
                        $location_status = sprintf( esc_html__( '%1$s %2$s Reopens %3$s', 'wp-store-locator' ), $status_txt, '<br>', date_i18n( get_option( 'date_format' ), $reopen ) );
                    }
                }
            }
        } else {
            $location_status = '';
        }

        return $location_status;
    }

    /**
     * The store meta fields included in the json output.
     *
     * `wpsl_` is the db name; `name` is the json key. `type` decides
     * sanitization (text -> sanitize_text_field, email -> esc_attr, ...),
     * defaulting to sanitize_text_field when unset.
     *
     * @since  2.0.0
     * @return array $store_fields The names of the meta fields used by the store
     */
    public function frontend_meta_fields() {
        $store_fields = [
            'wpsl_address' => [
                'name' => 'address'
            ],
            'wpsl_address2' => [
                'name' => 'address2'
            ],
            'wpsl_city' => [
                'name' => 'city'
            ],
            'wpsl_state' => [
                'name' => 'state'
            ],
            'wpsl_zip' => [
                'name' => 'zip'
            ],
            'wpsl_country' => [
                'name' => 'country'
            ],
            'wpsl_lat' => [
                'name' => 'lat',
                'type' => 'numeric'
            ],
            'wpsl_lng' => [
                'name' => 'lng',
                'type' => 'numeric'
            ],
            'wpsl_hours' => [
                'name' => 'hours',
                'type' => 'hours'
            ],
            'wpsl_phone' => [
                'name' => 'phone',
                'type' => 'phone'
            ],
            'wpsl_fax' => [
                'name' => 'fax',
                'type' => 'phone'
            ],
            'wpsl_email' => [
                'name' => 'email',
                'type' => 'email'
            ],
            'wpsl_url' => [
                'name' => 'url',
                'type' => 'url'
            ]
        ];

        // Add custom fields from the Fields Manager so they're included in the template data.
        $fields_manager = wpsl_get_service( 'store_fields' );
        $custom_fields  = $fields_manager->get_fields( [ 'only_custom' => true ] );

        if ( ! empty( $custom_fields ) ) {
            foreach ( $custom_fields as $group_name => $group_fields ) {
                foreach ( $group_fields as $field_name => $field_data ) {
                    $store_fields[ 'wpsl_' . $field_name ] = [
                        'name' => $field_name,
                        'type' => isset( $field_data['type'] ) ? $field_data['type'] : 'text',
                    ];
                }
            }
        }

        return apply_filters( 'wpsl_frontend_meta_fields', $store_fields );
    }

    /**
     * Get the store thumbnail.
     *
     * @since  2.0.0
     * @param  string      $post_id    The post id of the store
     * @param  string      $store_name The name of the store
     * @return void|string $thumb      The html img tag
     */
    public function get_store_thumb( $post_id, $store_name ) {
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            $thumb = [
                'id'  => $thumb_id,
                'src' => set_url_scheme( get_the_post_thumbnail_url( $post_id, apply_filters( 'wpsl_thumb_attr', $this->get_store_thumb_size() ) ) ),
                'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true)
            ];
        } else {
            $attr = [
                'class' => 'wpsl-store-thumb',
                'alt'   => $store_name
            ];

            $thumb = get_the_post_thumbnail( $post_id, $this->get_store_thumb_size(), apply_filters( 'wpsl_thumb_attr', $attr ) );
        }

        return $thumb;
    }

    /**
     * Get the store thumbnail size.
     *
     * @since  2.0.0
     * @return array $size The thumb format
     */
    public function get_store_thumb_size() {
        return apply_filters( 'wpsl_thumb_size', [ 45, 45 ] );
    }

    /**
     * Check if any category has an image set.
     * 
     * Uses transient caching to avoid repeated database queries.
     *
     * @since  3.0.0
     * @return bool True if at least one category has an image, false otherwise
     */
    public function has_any_category_images() {
        $cached = get_transient( 'wpsl_has_category_images' );

        if ( false !== $cached ) {
            return ( bool ) $cached;
        }

        $terms_with_images = get_terms( [
            'taxonomy'   => 'wpsl_store_category', 
            'hide_empty' => false, // We want to check all categories, even empty ones
            'number'     => 1,     // Stop looking after finding just one match
            'fields'     => 'ids', // Only fetch the ID to keep memory usage minimal
            'meta_query' => $this->category_marker_meta_query(),
        ] );

        // If it's not an error and the array isn't empty, we have at least one image
        $result = ( ! is_wp_error( $terms_with_images ) && ! empty( $terms_with_images ) );

        // Cache for 12 hours (or until category is saved/deleted which clears cache)
        set_transient( 'wpsl_has_category_images', $result ? 1 : 0, 12 * HOUR_IN_SECONDS );

        return $result;
    }

    /**
     * Get the category images for a store (normal and active states).
     
     * @since  3.0.0
     * @param  int $store_id The store id
     * @return array Array with 'normal' and 'active' image URLs
     */
    public function get_category_image( $store_id ) {
        $category_markers = [
            'normal' => '',
            'active' => ''
        ];
        
        $terms = get_the_terms( $store_id, 'wpsl_store_category' );

        if ( $terms && ! is_wp_error( $terms ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public filter parameter, sanitized below
            if ( isset( $_GET['filter'] ) && $_GET['filter'] ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Sanitized in wpsl_normalize_category_filter
                $filter_ids = explode( ',', wpsl_normalize_category_filter( wp_unslash( $_GET['filter'] ) ) );

                foreach ( $terms as $term ) {
                    if ( in_array( $term->term_id, $filter_ids ) ) {
                        $category_markers = $this->get_term_markers( $term->term_id );

                        if ( $category_markers['normal'] ) {
                            break;
                        }
                    }
                }
            } else {
                $category_markers = $this->get_term_markers( $terms[0]->term_id );
            }
        }

        return $category_markers;
    }

    /**
     * "Has this term any marker at all", as a meta query.
     *
     * @since  3.0.0
     * @return array A meta_query matching a term with any marker meta set.
     */
    private function category_marker_meta_query() {
        $query = [ 'relation' => 'OR' ];

        foreach ( [ 'store', 'active' ] as $type ) {
            $query[] = [
                'key'     => wpsl_category_marker_key( $type ),
                'value'   => '',
                'compare' => '!=',
            ];
        }

        return $query;
    }

    /**
     * One category's pair of markers, resolved to images.
     *
     * @since  3.0.0
     * @param  int $term_id Term id.
     * @return array Array with 'normal' and 'active' image URLs.
     */
    private function get_term_markers( $term_id ) {
        $markers = [
            'normal' => wpsl_category_marker_src( $term_id, 'store' ),
            'active' => wpsl_category_marker_src( $term_id, 'active' ),
        ];

        // A category given only an active marker uses it for both states.
        if ( ! $markers['normal'] && $markers['active'] ) {
            $markers['normal'] = $markers['active'];
        }

        return $markers;
    }
}