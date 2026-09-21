<?php
/**
 * API for standard WP Store Locator actions.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Store\Fields_Manager;
use WPSL\Core\Hours\Service as Hours_Service;
use WPSL\Core\Utils\Location_Utils;
use WPSL\Frontend\Store\Data as StoreData;

use WPSL\Admin\Utils\Location_Status;
use WPSL\Admin\API\Geocode;

class Service {

    /**
     * Location_Status object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Utils\Location_Status
     */
    private $location_status;

    /**
     * Geocode_Service object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\API\Geocode
     */
    private $geocode_service;

    /**
     * Location utils object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Utils\Location_Utils
     */
    private $location_utils;

    /**
     * Fields manager object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Store\Fields_Manager
     */
    private $fields_manager;
    
    /**
     * Hours service object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Hours\Service
     */
    private $hours_service;

    /**
     * Store data instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Store\Data
     */
    private $store_data;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Admin\Utils\Location_Status  $location_status  Location status instance
     * @param \WPSL\Admin\API\Geocode            $geocode_service  Geocode service instance
     * @param \WPSL\Core\Utils\Location_Utils    $location_utils   Location utils instance
     * @param \WPSL\Core\Store\Fields_Manager    $fields_manager   Fields manager instance
     * @param \WPSL\Core\Hours\Service           $hours_service    Hours service instance
     * @param \WPSL\Frontend\Store\Data          $store_data       Store data instance
     */
    public function __construct( Location_Status $location_status, Geocode $geocode_service, Location_Utils $location_utils, Fields_Manager $fields_manager, Hours_Service $hours_service, StoreData $store_data ) {
        $this->location_status = $location_status;
        $this->geocode_service = $geocode_service;
        $this->location_utils  = $location_utils;
        $this->fields_manager  = $fields_manager;
        $this->hours_service   = $hours_service;
        $this->store_data      = $store_data;
    }

    /**
     * Create a new location.
     *
     * @since 3.0.0
     * @param array $args
     */
    public function create( $args ) {
        /**
         * Filter the capability required to create a location through the API.
         * Return an empty value to skip the check ( e.g. a trusted programmatic
         * importer that manages its own authorization ).
         *
         * @since 3.0.0
         * @param string $capability The required capability. Default 'publish_stores'.
         */
        $capability = apply_filters( 'wpsl_api_create_capability', 'publish_stores' );

        if ( $capability && ! current_user_can( $capability ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers
            throw new \Exception( 'Insufficient permissions to create a location.' );
        }

        $methods = apply_filters( 'wpsl_api_create_methods', [
            'set_metadata',
            'set_object_terms',
            'check_featured_image'
        ] );

        // Map the passed args to the matching wp_insert_post args.
        $post_args = $this->location_utils->set_post_args( $args );

        /*
         * A caller that filtered the capability away manages its own
         * authorization ( the importer case ), so the payload is trusted
         * as-is. Everything else goes through the restrictions.
         */
        if ( $capability ) {
            $post_args = $this->restrict_post_args( $post_args );
        }

        $post_id = wp_insert_post( $post_args, true );

        if ( is_wp_error( $post_id ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers
            throw new \Exception( $post_id->get_error_code() . ', ' . $post_id->get_error_message() );
        }

        if ( $post_id ) {

            /**
             * Run the required methods that set
             * the metadata, terms and featured image.
             */
            foreach ( $methods as $method ) {
                call_user_func( [ $this, $method ], $post_id, $args );
            }

            $response = $this->geocode_service->geocode_location( $args, $post_id, true );

            if ( isset( $response['status'] ) && $response['status'] !== 'OK' ) {
                return json_encode( [ 'geocode_status' => $response['status'], 'error' => $response['error_message'] ] );
            } else if ( isset( $response['message'] ) ) {
                return json_encode( [ 'geocode_status' => $response['message'] ] );
            } else {
                $this->geocode_service->set_metadata( $post_id, $response );
            }

            return $post_id;
        }
    }

    /**
     * Return all the location related data.
     *
     * @since   3.0.0
     * @param   int   $post_id  The location id
     * @return  array $location The collected location data
     */
    public function get( $post_id ) {
        $item = get_post( $post_id );

        if ( ! $item ) {
            return null;
        }

        $location = $this->format_response( $item );

        return $location;
    }

    /**
     * Update a location.
     *
     * @since   3.0.0
     * @param   int   $post_id Post id
     * @param   array $args    Data we need to update.
     * @return  int $post_id
     */
    public function update( $post_id, $args ) {
        if ( get_post_type( $post_id ) == 'wpsl_stores' ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return new \WP_Error( 'wpsl_forbidden', 'Insufficient permissions to update this location.', [ 'status' => 403 ] );
            }

            $post_args = $this->restrict_post_args( $this->location_utils->set_post_args( $args ), $post_id );
            $post_id   = wp_update_post( $post_args, true, false );

            if ( is_wp_error( $post_id ) ) {
                if ( 'db_update_error' === $post_id->get_error_code() ) {
                    $post_id->add_data( [ 'status' => 500 ] );
                } else {
                    $post_id->add_data( [ 'status' => 400 ] );
                }
            } else {
                $this->set_metadata( $post_id, $post_args );
                $this->set_object_terms( $post_id, $post_args );
                $this->check_featured_image( $post_id, $post_args );
            }
        }

        return $post_id;
    }

    /**
     * Keep the post args within what the current user is allowed to do.
     *
     * The args come from the caller, so without this a payload could point the
     * write at a location the capability check never covered, hand the store to
     * another author, or publish it without the capability for it.
     *
     * @since  3.0.0
     * @param  array $post_args The mapped wp_insert_post / wp_update_post args
     * @param  int   $post_id   The authorized location id, 0 when creating
     * @return array $post_args The restricted args
     */
    private function restrict_post_args( $post_args, $post_id = 0 ) {
        // The authorized id is the only one that may be written.
        if ( $post_id ) {
            $post_args['ID'] = $post_id;
        } else {
            unset( $post_args['ID'] );
        }

        if ( isset( $post_args['post_author'] ) && ! current_user_can( 'edit_others_stores' ) ) {
            unset( $post_args['post_author'] );
        }

        if ( isset( $post_args['post_status'] ) && 'publish' === $post_args['post_status'] && ! current_user_can( 'publish_stores' ) ) {
            unset( $post_args['post_status'] );
        }

        return $post_args;
    }

    /**
     * Delete a location.
     *
     * @since  3.0.0
     * @param  int                $post_id Post id
     * @return WP_Post|false|null Post data on success, false or null on failure.
     */
    public function delete( $post_id ) {
        if ( get_post_type( $post_id ) !== 'wpsl_stores' ) {
            return;
        }

        if ( ! current_user_can( 'delete_post', $post_id ) ) {
            return false;
        }

        return wp_delete_post( $post_id, true );
    }

    /**
     * Set the metadata for a location.
     *
     * @since  3.0.0
     * @param  int   $post_id Post id
     * @param  array $args
     * @return void
     */
    public function set_metadata( $post_id, $args ) {
        if ( get_post_type( $post_id ) !== 'wpsl_stores' ) {
            return;
        }

        $fields    = $this->fields_manager->get_fields();
        $meta_data = $this->hours_service->maybe_validate_hours( $args );

        // Primed in one query, so the empty fields below only delete what exists.
        $stored_meta = get_post_meta( $post_id );

        foreach ( $fields as $tab => $meta_fields ) {
            foreach ( $meta_fields as $field_key => $field_data ) {

                /*
                 * Either update or delete the post meta. The empty check is
                 * strict, a loose one also treats the integer 0 as empty on
                 * PHP 7.x and deletes a legitimate value. isset() already
                 * covers null.
                 */
                if ( isset( $meta_data[ $field_key ] ) && '' !== $meta_data[ $field_key ] && false !== $meta_data[ $field_key ] ) {
                    if ( isset( $field_data['type'] ) && $field_data['type'] ) {
                        $field_type = $field_data['type'];
                    } elseif ( 'lat' === $field_key || 'lng' === $field_key ) {
                        $field_type = 'coordinate';
                    } else {
                        $field_type = '';
                    }

                    switch ( $field_type ) {
                        case 'thumbnail':
                            update_post_meta( $post_id, 'wpsl_' . $field_key, absint( $meta_data[ $field_key ] ) );
                            break;
                        case 'checkbox':
                            update_post_meta( $post_id, 'wpsl_' . $field_key, 1 );
                            break;
                        case 'tel':
                            update_post_meta( $post_id, 'wpsl_' . $field_key, wpsl_sanitize_phone( $meta_data[ $field_key ] ) );
                            break;
                        case 'email':
                            $sanitized_email = sanitize_email( $meta_data[ $field_key ] );

                            if ( $sanitized_email ) {
                                update_post_meta( $post_id, 'wpsl_' . $field_key, $sanitized_email );
                            } else {
                                delete_post_meta( $post_id, 'wpsl_' . $field_key );
                            }
                            break;
                        case 'url':
                            update_post_meta( $post_id, 'wpsl_' . $field_key, esc_url_raw( $meta_data[ $field_key ] ) );
                            break;
                        case 'wp_editor':
                        case 'textarea':
                            /*
                             * Opening hours reach this branch as an array when the
                             * site-wide input type is 'textarea' but the value is
                             * dropdown-format -- the normal state for a site upgraded
                             * from 1.x with some locations converted. stripslashes() on
                             * an array returns null on PHP 7.x and throws a TypeError on
                             * PHP 8, wiping the value -- so arrays go to the array
                             * writer, which passes the hours through and sanitizes
                             * anything else that arrives here as one.
                             */
                            if ( ! is_string( $meta_data[ $field_key ] ) ) {
                                $this->store_array_value( $post_id, $field_key, $meta_data[ $field_key ] );
                                break;
                            }

                            $raw_value = $this->convert_bb_code( trim( stripslashes( $meta_data[ $field_key ] ) ) );

                            update_post_meta( $post_id, 'wpsl_' . $field_key, wp_kses_post( $raw_value ) );
                            break;
                        case 'coordinate':
                            $coordinate = Location_Utils::sanitize_coordinate( $meta_data[ $field_key ], $field_key );

                            if ( '' !== $coordinate ) {
                                update_post_meta( $post_id, 'wpsl_' . $field_key, $coordinate );
                            } else {
                                delete_post_meta( $post_id, 'wpsl_' . $field_key );
                            }
                            break;
                        case 'timezone':
                            $timezone = wpsl_sanitize_timezone( $meta_data[ $field_key ] );

                            if ( $timezone ) {
                                update_post_meta( $post_id, 'wpsl_timezone', $timezone );
                            } else {
                                delete_post_meta( $post_id, 'wpsl_timezone' );
                            }
                            break;
                        default:
                            if ( is_array( $meta_data[ $field_key ] ) ) {
                                $this->store_array_value( $post_id, $field_key, $meta_data[ $field_key ] );
                            } else {
                                update_post_meta( $post_id, 'wpsl_' . $field_key, sanitize_text_field( $meta_data[$field_key] ) );
                            }
                            break;
                    }
                } else {
                    /**
                     * When a post is saved from within the admin area empty
                     * meta fields are deleted, but when data is saved through
                     * the RESET API this check can be skipped.
                     *
                     * This makes it possible to include data for a single
                     * field in the REST request without having the code
                     * deleting the rest of the meta data.
                     */
                    if ( isset( $args['meta_check'] ) && ! $args['meta_check'] ) {
                        continue;
                    }

                    // Nothing stored means nothing to delete, so skip the query.
                    if ( isset( $stored_meta[ 'wpsl_' . $field_key ] ) ) {
                        delete_post_meta( $post_id, 'wpsl_' . $field_key );
                    }
                }
            }
        }

        /**
         * Set the location status to either open / temporary
         * permanently closed / and include a possible reopen time.
         *
         * If a reopen time is provided, then we make sure
         * an event is schedulded to automatically set it to
         * open
         */
        $this->location_status->process( $args, $post_id );
    }

    /**
     * Store a field that arrived as an array.
     *
     * Only the opening hours are pre-validated ( by Hours\Service::validate(),
     * which also keeps the line breaks 'sanitize_text_field' and
     * 'wpsl_sanitize_multi_array' would strip from the 'special' hours ).
     * Anything else is sanitized here, whatever field type it was sent as.
     *
     * @since  3.0.0
     * @param  int    $post_id   The location id
     * @param  string $field_key The field key, without the wpsl_ prefix
     * @param  array  $value     The value to store
     * @return void
     */
    private function store_array_value( $post_id, $field_key, $value ) {
        if ( $field_key == 'hours' ) {
            update_post_meta( $post_id, 'wpsl_hours', $value );
        } else if ( wpsl_is_multi_array( $value ) ) {
            array_walk_recursive( $value, 'wpsl_sanitize_multi_array' );
            update_post_meta( $post_id, 'wpsl_' . $field_key, $value );
        } else {
            update_post_meta( $post_id, 'wpsl_' . $field_key, array_map( 'sanitize_text_field', $value ) );
        }
    }

    /**
     * Convert BB code to HTML for user-friendly formatting.
     *
     * The value is returned untouched if it can't contain
     * any BB code, so we skip the regex calls for the
     * majority of the values.
     *
     * @since  3.0.0
     * @param  string $value The raw field value
     * @return string The value with the supported BB code replaced by HTML
     */
    private function convert_bb_code( $value ) {
        if ( strpos( $value, '[' ) === false || strpos( $value, ']' ) === false ) {
            return $value;
        }

        $patterns = [
            '/\[strong\](.*?)\[\/strong\]/s'   => '<strong>$1</strong>',
            '/\[b\](.*?)\[\/b\]/s'             => '<strong>$1</strong>',
            '/\[em\](.*?)\[\/em\]/s'           => '<em>$1</em>',
            '/\[i\](.*?)\[\/i\]/s'             => '<em>$1</em>',
            '/\[br\]/s'                        => '<br>',
            '/\[url=(.*?)\](.*?)\[\/url\]/s'   => '<a href="$1">$2</a>',
            '/\[link=(.*?)\](.*?)\[\/link\]/s' => '<a href="$1">$2</a>',
        ];

        return preg_replace( array_keys( $patterns ), array_values( $patterns ), $value );
    }

    /**
     * Set the opening hours for a single location
     *
     * @since  3.0.0
     * @param string  $opening_hours The opening hours ( for example, monday: 09:00-15:00 17:00-21:00. tuesday: 10:00-13:00. wednesday: closed. thursday: 10:00-17:00 19:00-21:00. friday: 09:00-13:00 13:30-17:00 19:00-21:00. saturday: 09:00-13:00. sunday: closed. )
     * @param int     $post_id       The post ID
     */
    public function set_hours( $opening_hours, $post_id ) {
        $hours = $this->hours_service->convert_hours_to_array( $opening_hours );

        update_post_meta( $post_id, 'wpsl_hours', $hours );
    }

    /**
     * Get the opening hours for a single location
     *
     * @since 3.0.0
     * @param int  $post_id The post ID
     * @param bool $raw     If false, then the hours are wrapped in a table.
     */
    public function get_hours( $post_id, $raw = true ) {
        $hours = get_post_meta( $post_id, 'wpsl_hours', true );

        if ( ! $raw ) {
            $hours = $this->hours_service->prepare_output( $hours, [ 'store_id' => $post_id ] );
        }

        return $hours;
    }

    /**
     * Format the data from get_post and include
     * the releveant meta data before returning it.
     *
     * @since   3.0.0
     * @param   object $item Data from get_post
     * @return  array  $data The formatted location data
     */
    public function format_response( $item ) {
        $is_online = get_post_meta( $item->ID, 'wpsl_online', true );
        $post_data = [
            'id'    => (int) $item->ID,
            'store' => $item->post_title,
        ];

        // Grab the publishing / modified / post status details.
        $post_data = wpsl_get_publishing_details( $post_data, $item );

        /**
         * Online only requires different data
         * compared to a physical location.
         */
        if ( $is_online ) {
            $meta_fields = [ 'url', 'email', 'phone', 'fax', 'thumb', 'online' ];
        }

        if ( ! $is_online || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $meta_fields = wpsl_get_location_fields();
        }

        // Include the meta data
        $meta_data = [];

        foreach ( $meta_fields as $meta_key ) {
            $meta_data[$meta_key] = get_post_meta( $item->ID, 'wpsl_' . $meta_key , true );

            // A location without hours needs neither the table nor the raw copy.
            if ( $meta_key == 'hours' && ! empty( $meta_data['hours'] ) ) {
                $hours = $this->hours_service->prepare_output( $meta_data['hours'], [ 'store_id' => $item->ID ] );

                $serialized_hours = serialize( $meta_data['hours'] );

                if ( isset( $hours['status'] ) && $hours['status'] == 'closed' ) {
                    $meta_data['hours'] = $hours['status'];
                } else if ( isset( $hours['table'] ) ) {
                    $meta_data['hours'] = $hours['table'];
                }

                $meta_data['hours_raw'] = $serialized_hours;
            }
        }

        $data = array_merge( $post_data, $meta_data );

        $data['permalink'] = get_permalink( $item->ID );

        // See if we need to include terms data
        $terms = $this->get_terms( $item->ID, apply_filters( 'wpsl_api_terms_type', 'term_id' ) );

        if ( $terms ) {
            $data['terms'] = $terms;
        }

        // Include the featured image
        $data['thumb'] = $this->store_data->get_store_thumb( $item->ID, $item->post_title );

        return $data;
    }

    /**
     * Return the terms used for a single location
     *
     * @since   3.0.0
     * @param   int    $id        The location ID
     * @param   string $type      The type of data
     * @return  string $term_list Returns a comma seperated list of the term data ( defaults to ids )
     */
    public function get_terms( $id, $type = 'term_id' ) {
        $terms     = get_the_terms( $id, 'wpsl_store_category' );
        $term_list = '';

        if ( $terms ) {
            if ( ! is_wp_error( $terms ) ) {
                // Make sure the required data type exists
                if ( ! isset( $terms[0]->$type ) && $type != 'term_id' ) {
                    $type = 'term_id';
                }

                if ( count( $terms ) > 1 ) {
                    $location_terms = [];

                    foreach ( $terms as $term ) {
                        $location_terms[] = $term->$type;
                    }

                    $term_list = implode( ',', $location_terms );
                } else {
                    $term_list = $terms[0]->$type;
                }
            }
        }

        return $term_list;
    }

    /**
     * Geocode the passed address details.
     *
     * @since   3.0.0
     * @param   array $args     The location data ( street, zip, city, country details ).
     * @return  array $response Either the geocoded data or an error message.
     */
    public function geocode( $args ) {
        return $this->geocode_service->geocode_location( $args );
    }

    /**
     * Recode a location based on the passed location id.
     *
     * @since   3.0.0
     * @param   int          $id       The location ID
     * @return  array|string $response Either the geocode response, or error message.
     */
    public function recode( $id ) {
        $args          = [];
        $address_parts = [ 'address', 'city', 'state', 'zip' ];
        $item          = get_post_custom( $id );

        foreach ( $address_parts as $address_part ) {
            if ( isset( $item['wpsl_' . $address_part] ) ) {
                $args[$address_part] = $item['wpsl_' . $address_part][0];
            }
        }

        if ( $args ) {
            $response = $this->geocode_service->geocode_location( $args );

            if ( isset( $response['latlng'] ) ) {
                $this->geocode_service->set_metadata( $id, $response );
            }
        } else {
            /* translators: %d: location ID */
            $response = sprintf( esc_html__( 'The location with ID %d has no address data set.', 'wp-store-locator' ), $id );
        }

        return $response;
    }

    /**
     * Flush the transient cache that's used on the
     * front-end when the autoload option is enabled.
     *
     * Transient names are partly dynamic: wpsl_autoload_{count}_{lang},
     * e.g. wpsl_autoload_20_de for 20 stores in German. The language
     * code has to be included, or a multilingual visitor switching to
     * Spanish could still see the German store data.
     *
     * @since  3.0.0
     */
    public function flush_transient_cache() {
        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error();
        }

        if ( ! isset( $_REQUEST['wpsl_nonce'] ) ) {
            wp_send_json_error();
        }

        $nonce = sanitize_text_field( wp_unslash( $_REQUEST['wpsl_nonce'] ) );

        /*
         * Fixed action. Taking it from the request ( it used to ) means any
         * nonce the user holds for any action passes this check.
         */
        if ( wp_verify_nonce( $nonce, 'wpsl-flush-transient-cache' ) ) {
            wpsl_get_service( 'system_utils' )->flush_autoload_transients();

            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Assign the correct category and tags
     * ( tag support will come in the future ).
     *
     * @since 3.0.0
     * @param int   $post_id
     * @param array $args
     */
    public function set_object_terms( $post_id, $args ) {
        if ( isset( $args['category'] ) ) {
            if ( $args['category'] ) {
                $categories = explode( '|', $args['category'] );
            } else {
                $categories = NULL;
            }

            wp_set_object_terms( $post_id, $categories, 'wpsl_store_category' );
        }

        if ( isset( $args['tags'] ) ) {
            if ( $args['tags'] ) {
                $tags = explode( '|', $args['tags'] );
            } else {
                $tags = NULL;
            }

            wp_set_object_terms( $post_id, $tags, 'wpsl_store_tags' );
        }
    }

    /**
     * Set or remove a featured image.
     *
     * @since  3.0.0
     * @param  int   $post_id The current post id
     * @param  array $args    The location data
     * @return void
     */
    public function check_featured_image( $post_id, $args ) {
        /*
        * If we have an image, set it as the featured image.
        *
        * Otherwise check if we are updating an existing store location.
        *
        * If this is the case, and the 'image' field in the imported data is left empty,
        * but the current post id has an existing post thumbnail,
        * then we delete the old post thumbnail.
        */
        if ( isset( $args['image'] ) && $args['image'] ) {

           /**
            * If the image field contains an ID, then use it to set the post thumbnail.
            * Otherwise try to download the image before setting the post thumbnail.
            */
            if ( is_numeric( $args['image'] ) ) {
                // An id is only a featured image if it points at an actual image.
                if ( wp_attachment_is_image( $args['image'] ) ) {
                    set_post_thumbnail( $post_id, $args['image'] );
                }
            } else {
                $this->set_featured_image( $post_id, $args['image'] );
            }
        } else if ( has_post_thumbnail( $post_id ) ) {
            delete_post_thumbnail( $post_id );
        }
    }

    /**
     * Set a featured image for a store location.
     *
     * @since  3.0.0
     * @param  int    $post_id   The id of current location
     * @param  string $image_url The Url of the featured image
     * @return void
     */
    public function set_featured_image( $post_id, $image_url ) {
        // Need to require these files
        if ( ! function_exists( 'media_handle_upload' ) || ! function_exists( 'download_url' ) ) {
            require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
            require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
            require_once( ABSPATH . "wp-admin" . '/includes/media.php' );
        }

        /**
         * Reject URLs that resolve to the local host or a private / reserved IP
         * range so the image sideload can't be used as an SSRF probe.
         * wp_http_validate_url() also rejects non-HTTP(S) schemes and respects
         * the standard http_request_host_is_external / _is_allowed filters.
         */
        if ( ! wp_http_validate_url( $image_url ) ) {
            return;
        }

        $tmp = download_url( $image_url );

        if ( ! is_wp_error( $tmp ) ) {
            $file_array = [];
            $desc       = get_the_title( $post_id );

            // Set variables for storage and fix file filename for query strings.
            if ( ! preg_match( '/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $image_url, $matches ) ) {
                // No extension to name the file after, so there's nothing to sideload.
                wp_delete_file( $tmp );

                return;
            }

            $file_array['name']     = basename( $matches[0] );
            $file_array['tmp_name'] = $tmp;

            // do the validation and storage stuff.
            $attachment_id = media_handle_sideload( $file_array, $post_id, $desc );

            // If error storing permanently, unlink.
            if ( is_wp_error( $attachment_id ) ) {
                wp_delete_file( $file_array['tmp_name'] );
            } else {
                set_post_thumbnail( $post_id, $attachment_id );
            }
        }
    }
}