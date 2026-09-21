<?php
/**
 * Geocode store locations
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

namespace WPSL\Admin\API;

defined( 'ABSPATH' ) || exit;

use WPSL\Admin\Core\Notices;
use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Utils\Location_Utils;

/**
 * Geocode class.
 *
 * @since 2.0.0
 */
class Geocode {

    /**
     * Store fields the geocoder can resolve on its own, and that the submitted
     * form doesn't carry unless the editor JS filled them in first.
     *
     * @since 3.0.0
     */
    const GEOCODED_FIELDS = [ 'country', 'country_iso', 'zip' ];

    /**
     * Transient prefix used to hand those fields to the save that follows.
     *
     * @since 3.0.0
     */
    const STASH_PREFIX = 'wpsl_geocoded_';

    /**
     * The active map service
     *
     * @since 3.0.0
     */
    public $map_service;

    /**
     * The geocode implementation for the active map provider.
     *
     * The type of object varies depending on which map provider is active
     * (e.g., Google Maps, Mapbox, OpenStreetMap, etc.).
     *
     * @since 3.0.0
     * @var mixed
     */
    public $geocode_request;

    /**
     * Is Gutenberg active?
     *
     * @since 3.0.0
     */
    public $is_block_editor = false;

    /**
     * Notices object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Core\Notices
     */
    public $notices;

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    public $settings;

    /**
     * Class constructor
     *
     * @since 2.0.0
     * @param \WPSL\Admin\Core\Notices    $notices  Notices manager instance
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( Notices $notices, WpslSettings $settings ) {
        $this->notices  = $notices;
        $this->settings = $settings;

        add_action( 'current_screen', [ $this, 'is_block_editor' ] );
    }

    /**
     * Check if Gutenberg is active.
     *
     * @since  3.0.0
     * @return void
     */
    public function is_block_editor() {
        $current_screen = get_current_screen();

        if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() && did_action( 'admin_init' ) ) {
            $this->is_block_editor = true;
        }
    }

    /**
     * Set the geocode implementation and map service
     * 
     * @since  3.0.0
     * @param  string $map_service The map service to use
     * @return void
     */
    public function set_geocode_implementation( $map_service = '' ) {
        if ( empty( $map_service ) ) {
            $map_service = $this->settings->get( 'api', 'active_map_service' );
        }
        
        $this->geocode_request = wpsl_get_geocode_implementation( $map_service );
        $this->map_service = $map_service;
    }

    /**
     * See if the submitted locations details
     * are different from the existing meta values.
     *
     * If so, then this mean we need to trigger
     * a new geocode request.
     *
     * @since  3.0.0
     * @param  int  $post_id     ID of the current post
     * @param  array $store_data The store details
     * @return bool
     */
    public function address_changed( $post_id, $store_data ) {
        $changed = false;

        /**
         * Only the address fields count. The lat/lng values are deliberately
         * excluded — a coordinate-only change means the user set them manually
         * ( typed in, or dragged the marker on the preview map ), and flagging
         * that as changed would re-geocode the unchanged address and overwrite
         * the coordinates the user just set.
         */
        $fields = [ 'address', 'city', 'state', 'zip', 'country' ];

        foreach ( $fields as $field ) {
            $meta = get_post_meta( $post_id, 'wpsl_' . $field, true );
            $new  = $store_data[$field] ?? '';

            if ( $new !== $meta ) {
                $changed = true;
                break;
            }
        }

        return $changed;
    }
            
    /** 
     * Check if we need to run a geocode request or use the
     * current location data.
     * 
     * The latlng value is only present if the user provided it himself,
     * or used the preview on the map. Otherwise the latlng will be
     * missing and we need to geocode the supplied address.
     * 
     * @since  2.0.0
     * @param  integer    $post_id    Store post ID
     * @param  array      $store_data The store data
     * @return array|null $location_data The stored location data, or null when geocoding failed
     */
    public function check_data( $post_id, $store_data ) {
        $location_data = [];

        if ( isset( $store_data['recode'] ) && $store_data['recode'] ) {
            $store_data['lat'] = '';
            $store_data['lng'] = '';
        }

        $latlng = Location_Utils::validate_latlng(
            Location_Utils::sanitize_coordinate( $store_data['lat'] ?? '', 'lat' ),
            Location_Utils::sanitize_coordinate( $store_data['lng'] ?? '', 'lng' )
        );

        // If we don't have a valid latlng value, we geocode the supplied address to get one.
        if ( ! $latlng ) {
            /**
             * The block editor validates the address through an admin-ajax
             * request ( validate_save_post ) before saving. In that context the
             * error is returned to the editor and shown there, so we treat it as
             * an API call to avoid also persisting a separate admin notice that
             * would otherwise linger on the store overview page.
             */
            $api_call = defined( 'DOING_AJAX' ) && DOING_AJAX;
            $response = $this->geocode_location( $store_data, $post_id, $api_call );

            if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || isset( $response['message'] ) ) {
                if ( $post_id && ! isset( $response['message'] ) ) {
                    $this->stash_location_fields( $post_id, $response );
                }

                return $response;
            }

            if ( empty( $response ) ) {
                return ( defined( 'DOING_AJAX' ) && DOING_AJAX )
                    ? [ 'message' => __( 'Geocoding failed. Please check the address and try again.', 'wp-store-locator' ) ]
                    : null;
            }

            if ( isset( $response['country_iso'] ) ) {
                $location_data['country_iso'] = $response['country_iso'];
            }

            /**
             * Hand the geocoded country name and postcode to set_metadata(),
             * but only for the fields the user left empty — what someone typed
             * always wins over what the API thinks the address resolves to.
             */
            foreach ( [ 'country', 'zip' ] as $field ) {
                if ( ! empty( $response[$field] ) && empty( $store_data[$field] ) ) {
                    $location_data[$field] = $response[$field];
                }
            }

            /**
             * Show a non-blocking geocode warning ( e.g. an imprecise,
             * country-level match ) as an admin notice for the classic editor.
             * The block editor handles this client-side via the AJAX response,
             * which already returned earlier, so this only runs for classic saves.
             */
            if ( isset( $response['warning'] ) && ! $this->is_block_editor && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
                $this->notices->save( 'warning', $response['warning'] );
            }

            $location_data['latlng'] = $response['latlng'];
        } else {
            $location_data['latlng'] = $latlng;
        }

        // Restrict the latLng to a max of 6 decimals.
        $location_data['latlng'] = Location_Utils::format_latlng( $location_data['latlng'] );
        
        $this->set_metadata( $post_id, $location_data );

        return $location_data;
    }
    
    /** 
     * Geocode the store location.
     * 
     * @since  1.0.0
     * @param  array   $store_data The submitted store data ( address, city, country etc )
     * @param  integer $post_id    Store post ID
     * @param  boolean $api_call   If true, then no admin notices are created
     * @return array   $response
     */
    public function geocode_location( $store_data, $post_id = '', $api_call = false ) {
        $this->set_geocode_implementation( wpsl_get_geocoding_service() );

        // Make the API call
        $response = $this->get_latlng( $store_data );

        /**
         * Check if the expected location data exists,
         * or if the API returned errors.
         */
        if ( $this->geocode_request->is_valid_response( $response ) ) {

            /**
             * If Google Maps is used, then this is only possible if
             * the API returned coordinates and included the 'partial match'
             * field.
             */
            if ( isset( $response['message'] ) || isset( $response['warning'] ) ) {
                $custom_message = $this->filter_custom_message( $response );
            }

            $response = $this->geocode_request->filter_location_data( $response );

            if ( isset( $custom_message ) ) {
                $response = array_merge( $response, $custom_message );
            }

            /**
             * Enforce the country restriction uniformly for every provider.
             *
             * Not every geocoding API can hard-restrict to multiple countries
             * server-side ( e.g. Google Maps only biases with a single
             * regionCode ), so a valid match can still come back for an address
             * outside the selected countries. We reject those here so Google
             * Maps, Mapbox and OSM all behave the same way.
             */
            $restricted = $this->enforce_region_restriction( $response );

            if ( isset( $restricted['message'] ) ) {
                if ( ! $this->is_block_editor && $post_id ) {
                    $this->geocode_failed( $restricted, $post_id, $api_call );
                }

                return $restricted;
            }
        } else {
            if ( ! isset( $response['message'] ) && method_exists( $this->geocode_request, 'get_status_message' ) ) {
                $response = $this->geocode_request->get_status_message( $response );
            }

            if ( ! $this->is_block_editor && $post_id ) {
                $this->geocode_failed( $response, $post_id, $api_call );
            }
        }

        return $response;
    }

    /**
     * Park the address details the geocoder resolved for the save that follows.
     *
     * The block editor geocodes over AJAX before the post is saved, so by the
     * time save_post() runs the coordinates are already filled in and no second
     * geocode happens. The country name, ISO code and postcode aren't derived
     * from anything the form carries, so without this they only reach the
     * database if the editor JS managed to write them into the fields first.
     *
     * @since  3.0.0
     * @param  integer $post_id  Store post ID
     * @param  array   $response The filtered location data
     * @return void
     */
    protected function stash_location_fields( $post_id, $response ) {
        $fields = [];

        foreach ( self::GEOCODED_FIELDS as $field ) {
            if ( ! empty( $response[$field] ) ) {
                $fields[$field] = $response[$field];
            }
        }

        if ( $fields ) {
            set_transient( self::STASH_PREFIX . $post_id, $fields, 15 * MINUTE_IN_SECONDS );
        }
    }

    /**
     * Write the parked address details into the fields the save left empty.
     *
     * Runs after the submitted form values are stored, so anything the user
     * typed ( or the editor JS already filled in ) is what stays.
     *
     * @since  3.0.0
     * @param  integer $post_id Store post ID
     * @return void
     */
    public function apply_stashed_location_fields( $post_id ) {
        $fields = get_transient( self::STASH_PREFIX . $post_id );

        if ( ! is_array( $fields ) ) {
            return;
        }

        delete_transient( self::STASH_PREFIX . $post_id );

        foreach ( $fields as $field => $value ) {
            if ( ! in_array( $field, self::GEOCODED_FIELDS, true ) ) {
                continue;
            }

            if ( ! get_post_meta( $post_id, 'wpsl_' . $field, true ) ) {
                update_post_meta( $post_id, 'wpsl_' . $field, sanitize_text_field( $value ) );
            }
        }
    }

    /**
     * Filter the custom message returned by the Geocode API.
     *
     * @since  3.0.0
     * @param  array $response The response from the Geocode API
     * @return array $custom_message The custom message
     */
    protected function filter_custom_message( $response ) {
        $custom_message = [];
        $fields = [ 'message', 'label', 'url', 'warning' ];

        foreach ( $fields as $field ) {
            if ( isset( $response[$field] ) ) {
                $custom_message[$field] = $response[$field];
            }
        }

        return $custom_message;
    }
    
    /** 
     * Make the API call to geocode the address.
     * 
     * @param  array        $store_data  The store data
     * @return array|string $response    The response from the Google Geocode API, or the wp_remote_get error message.
     */
    public function get_latlng( $store_data ) {
        // When called directly the map service isn't set yet: use the
        // configured one, falling back to OSM ( no API key needed ).
        if ( ! is_object( $this->geocode_request ) ) {
            $configured = $this->settings->get( 'api', 'active_map_service' );

            $this->set_geocode_implementation( $configured ? $configured : 'osm' );
        }

        if ( method_exists( $this->geocode_request, 'create_' . $this->map_service . '_address' ) ) {
            $address = $this->geocode_request->{ 'create_' . $this->map_service . '_address' }( $store_data );
        } else {
            $address = $this->create_address( $store_data );
        }

        $response = $this->geocode_request->call_api( $address );

        if ( method_exists( $this->geocode_request, 'check_' . $this->map_service . '_response_code' ) ) {
            $response = $this->geocode_request->{'check_'. $this->map_service .'_response_code'}( $response, $address );
        } else {
            $response = $this->check_response_code( $response );
        }

        return $response;
    }

    /**
     * Create a comma separated string of the address details
     * that is send to the Geocode API to convert into coordinates.
     *
     * @since  3.0.0
     * @param  array  $store_data      The provided store data
     * @return string $geocode_address The address we are sending to the Geocode API
     */
    public function create_address( $store_data ) {
        $address       = [];
        $address_parts = apply_filters( 'wpsl_gmaps_address_parts', [
            'address',
            'city',
            'state',
            'zip',
            'country'
        ] );

        foreach ( $address_parts as $address_part ) {
            if ( isset( $store_data[$address_part] ) && $store_data[$address_part] ) {
                $address[] = trim( $store_data[$address_part] );
            }
        }

        $geocode_address = implode( ',', $address );

        return $geocode_address;
    }

    /**
     * See if the API request returned a valid response.
     *
     * If not return an error message
     *
     * @since  3.0.0
     * @param  array $api_response Data from the API call
     * @return mixed|string
     */
    protected function check_response_code( $api_response ) {
        $services = wpsl_get_map_services();
        $name = $services[ $this->map_service ];

        if ( is_wp_error( $api_response ) ) {
            /* translators: 1: map service name, 2: error message */
            $response['message'] = sprintf( esc_html__( 'Something went wrong connecting to the %1$s Geocode API: %2$s Please try again later.', 'wp-store-locator' ), $name, $api_response->get_error_message() );
        } else if ( $api_response['response']['code'] !== 200 ) {
            $decoded = json_decode( $api_response['body'], true );
            $code    = $api_response['response']['code'];
            $reason  = ( is_array( $decoded ) && isset( $decoded['message'] ) ) ? $decoded['message'] : wp_remote_retrieve_response_message( $api_response );

            $response = is_array( $decoded ) ? $decoded : [];
            $status   = $code . ' ' . $reason;

            /* translators: 1: map service name, 2: error status */
            $response['message'] = sprintf( esc_html__( 'There\'s a problem with the %1$s Geocode API. It returned: %2$s Please try again later.', 'wp-store-locator' ), $name, $status );

            // Structured notice for the editor, matching the settings page key error.
            $response['notice_html'] = wpsl_build_key_error_notice(
                /* translators: %s: the map service name */
                sprintf( __( 'The %s Geocode API returned the following error.', 'wp-store-locator' ), $name ),
                $code,
                $reason
            );
        } else {
            $response = json_decode( $api_response['body'], true );

            if ( $this->map_service == 'mapbox' && isset( $response['features'] ) && empty( $response['features'] ) ) {
                $response['message'] = esc_html__( 'The Mapbox API has returned no results. Make sure the address is spelled correctly, and try to provide as many locations details as possible.', 'wp-store-locator' );
            }
        }

        return $response;
    }

    /**
     * If there is a problem with the geocoding then we save the notice and change the post status to pending.
     * 
     * @since  2.0.0
     * @param  string  $msg      The geocode error message
     * @param  integer $post_id  Store post ID
     * @param  boolean $api_call If true, then no admin notices are created
     * @return void
     */
    protected function geocode_failed( $msg, $post_id, $api_call ) {
        if ( ! $api_call ) {
            $this->notices->save( 'error', $msg );
        }

        // Get the metaboxes service from the container
        $metaboxes = wpsl_get_service( 'metaboxes' );
        $metaboxes->set_post_pending( $post_id );
    }

    /** 
     * Set the coordinates and country ISO 
     * code returned by the Geocode API.
     * 
     * @since  2.0.0
     * @todo move to other class?
     * @param  integer $post_id       Store post ID
     * @param  array   $location_data The country code and latlng
     * @return void
     */
    public function set_metadata( $post_id, $location_data ) {
        if ( isset( $location_data['country_iso'] ) && ( ! empty( $location_data['country_iso'] ) ) ) {
            update_post_meta( $post_id, 'wpsl_country_iso', $location_data['country_iso'] );
        }

        /**
         * The country name and postcode are regular store fields, so they are
         * normally written from the submitted form. Without this a store saved
         * with only an address and a city keeps an empty Country / Zip Code
         * field even though the geocoder returned both.
         *
         * check_data() only passes these along when the user left the field
         * empty, so a value someone typed is never overwritten. An absent key
         * means the API didn't return that part, and nothing is touched.
         */
        foreach ( [ 'country', 'zip' ] as $field ) {
            if ( ! empty( $location_data[$field] ) ) {
                update_post_meta( $post_id, 'wpsl_' . $field, sanitize_text_field( $location_data[$field] ) );
            }
        }

        // Capped at 6 decimals like every other coordinate write; geocoders can return more.
        update_post_meta( $post_id, 'wpsl_lat', Location_Utils::sanitize_coordinate( $location_data['latlng']['lat'], 'lat' ) );
        update_post_meta( $post_id, 'wpsl_lng', Location_Utils::sanitize_coordinate( $location_data['latlng']['lng'], 'lng' ) );
    }
    
    /**
     * Filter out the country name
     *
     * @param  array $response The API response
     * @return array the country details
     */
    public function filter_country_name( $response ) {
        if ( method_exists( $this->geocode_request,'filter_country_name' ) ) {
            return $this->geocode_request->filter_country_name( $response );
        }
    }

    /**
     * Log API errors.
     *
     * @since  3.0.0
     * @param  array|\WP_Error $response The API response
     * @param  string          $url      The requested API URL
     * @return void
     */
    protected function log_api_errors( $response, $url ) {
        $error_message = 'WPSL Geocoding Error: ';

        if ( is_wp_error( $response ) ) {
            $error_message .= $response->get_error_message();
        } else if ( isset( $response['response']['code'] ) && isset( $response['response']['message'] ) ) {
            $error_message .= sprintf( '[%s] %s', $response['response']['code'], $response['response']['message'] );
        } else {
            $error_message .= 'Unknown error';
        }
        
        wpsl_debug_log( $error_message );

        // Log the full, unmodified API response body so the raw error returned
        // by the provider can be inspected. Guarded by WP_DEBUG to avoid writing
        // response payloads to the log on production sites.
        if ( ! is_wp_error( $response ) ) {
            $body = wp_remote_retrieve_body( $response );

            if ( $body ) {
                wpsl_debug_log( 'WPSL Geocoding Response: ' . $body );
            }
        }

        $parsed_url = wp_parse_url( $url );
        $redacted_url = '';

        if ( ! empty( $parsed_url['host'] ) ) {
            $redacted_url = $parsed_url['host'];

            if ( ! empty( $parsed_url['path'] ) ) {
                $redacted_url .= $parsed_url['path'];
            }
        }

        wpsl_debug_log( 'WPSL Geocoding URL: ' . ( $redacted_url ? $redacted_url : 'redacted' ) );
    }

    /**
     * Format the latitude and longitude coordinates.
     * Converts various formats to the standard array format ['lat' => x, 'lng' => y]
     *
     * @since  3.0.0
     * @param  array|string $location The location data (array or string)
     * @return array                  Formatted latlng array with 'lat' and 'lng' keys
     */
    protected function format_latlng( $location ) {
        // If it's already an array with lat and lng, restrict it to 6 decimals.
        if ( is_array( $location ) && isset( $location['lat'] ) && isset( $location['lng'] ) ) {
            return Location_Utils::format_latlng( $location );
        }

        // If it's a string like "lat,lng", convert to array and restrict decimals.
        if ( is_string( $location ) && strpos( $location, ',' ) !== false ) {
            $parts = explode( ',', $location );

            if ( count( $parts ) === 2 ) {
                return Location_Utils::format_latlng( [
                    'lat' => trim( $parts[0] ),
                    'lng' => trim( $parts[1] )
                ] );
            }
        }

        // Return empty array if format is not recognized
        return [ 'lat' => '', 'lng' => '' ];
    }

    /**
     * Get the language code for API requests based on the provider.
     *
     * @since  3.0.0
     * @param  string $provider The map provider name (e.g., 'osm', 'mapbox', 'gmaps')
     * @return string Language code (e.g., 'en', 'nl', 'de')
     */
    protected function get_api_language( $provider = '' ) {
        if ( empty( $provider ) ) {
            return '';
        }

        // Build the setting key based on the provider
        $language_key = $provider . '_language';
        
        // Check if there's a setting in WPSL settings
        $api_language = $this->settings->get( 'api', $language_key );
        
        if ( ! empty( $api_language ) ) {
            return $api_language;
        }
        
        // Fall back to WordPress locale
        $wp_locale = get_locale(); // Returns 'en_US', 'nl_NL', etc.
        $language = substr( $wp_locale, 0, 2 ); // Extract 'en', 'nl', etc.

        return apply_filters( 'wpsl_' . $provider . '_api_language', $language );
    }

    /**
     * Get the list of countries the geocoding results should be limited to.
     *
     * The country restriction ( multiple_regions ) is shared by all map
     * services. For Google Maps it only applies in the hard 'restrict' mode;
     * in 'bias' mode the selection is a soft preference and isn't enforced.
     * This mirrors the notice shown in the store editor.
     *
     * @since  3.0.0
     * @return array Lowercase ISO 3166-1 alpha-2 country codes, or an empty array.
     */
    protected function get_region_restriction() {
        $api = $this->settings->get_group( 'api' );

        // For Google Maps the restriction only applies in hard 'restrict' mode.
        if ( $this->map_service === 'gmaps' ) {
            $type = isset( $api['region_restriction_type'] ) ? $api['region_restriction_type'] : 'bias';

            if ( $type !== 'restrict' ) {
                return [];
            }
        }

        if ( empty( $api['multiple_regions'] ) || ! is_array( $api['multiple_regions'] ) ) {
            return [];
        }

        return array_filter( array_map( 'strtolower', array_map( 'sanitize_text_field', $api['multiple_regions'] ) ) );
    }

    /**
     * Reject a geocode result that falls outside the selected countries.
     *
     * @since  3.0.0
     * @param  array $response The filtered location data ( includes country_iso ).
     * @return array The original response when allowed, or an error message array
     *               with the friendly restriction notice when it's out of bounds.
     */
    protected function enforce_region_restriction( $response ) {
        $allowed = $this->get_region_restriction();

        if ( empty( $allowed ) ) {
            return $response;
        }

        $country_iso = isset( $response['country_iso'] ) ? strtolower( $response['country_iso'] ) : '';

        // Within an allowed country, nothing to do.
        if ( $country_iso && in_array( $country_iso, $allowed, true ) ) {
            return $response;
        }

        return $this->get_restriction_notice();
    }

    /**
     * Build the "no result within the selected countries" notice.
     *
     * Shared by every provider so the message is identical whether the address
     * was rejected after the fact ( Google Maps ) or returned no results
     * because the request itself was filtered server-side ( Mapbox, OSM ). The
     * settings link is passed as a separate url / label pair so it renders as a
     * clickable action in both the classic and block editor notices.
     *
     * @since  3.0.0
     * @return array The message plus the API settings link.
     */
    protected function get_restriction_notice() {
        $api       = $this->settings->get_group( 'api' );
        $countries = ( ! empty( $api['multiple_regions'] ) ) ? wpsl_map_country_names( $api['multiple_regions'] ) : '';

        return [
            'message' => sprintf(
                /* translators: %s: the list of countries the results are restricted to. */
                esc_html__( 'No matching location was found. The geocoding results are currently restricted to %s, so addresses outside these countries will not be found.', 'wp-store-locator' ),
                $countries
            ),
            'url'   => admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ),
            'label' => esc_html__( 'Review the country restrictions', 'wp-store-locator' ),
        ];
    }

    /**
     * Check if we need to run a geocode request or use the
     * current location data.
     *
     * @since      2.0.0
     * @deprecated 3.0.0 Use \WPSL\Admin\API\Geocode::check_data() instead.
     * @param  integer $post_id    Store post ID
     * @param  array   $store_data The store data
     * @return void
     */
    public function check_geocode_data( $post_id, $store_data ) {
        _deprecated_function( __METHOD__, '3.0.0', '\WPSL\Admin\API\Geocode::check_data' );
    }
}