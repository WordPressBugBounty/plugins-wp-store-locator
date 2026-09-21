<?php
/**
 * Handle geocode requests to the Stadia Maps Geocoding API.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 * @see    https://docs.stadiamaps.com/geocoding-search/search/
 */

namespace WPSL\Admin\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as Settings;

class Geocode_Stadia extends Geocode {

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    public $settings;

    /**
     * Constructor
     *
     * @since  3.0.0
     * @param  Settings $settings The settings manager
     * @return void
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Make the call to the Stadia Maps Geocoding Search API.
     *
     * Stadia indexes some postcodes without their internal space, so a spaced
     * postcode can return a low-quality fallback match while the unspaced form
     * is exact. When the first request produces nothing usable and the address
     * is a postcode, the request is repeated once without the whitespace.
     * The retry is only kept when usable, and is never itself retried.
     *
     * @since  3.0.0
     * @see    https://docs.stadiamaps.com/geocoding-search/search/
     * @param  string         $address  The address to geocode
     * @param  array          $args     Optional arguments
     * @return array|WP_Error $response The API response
     */
    public function call_api( $address, $args = [] ) {
        $response = $this->request( $address, $args );

        // Only retry when the API itself answered fine but the result is poor.
        // A transport error or a non-200 is not something a reworded address
        // can fix, and retrying would just spend a second request.
        if ( $this->is_successful_request( $response ) && ! $this->has_usable_result( $response ) ) {
            $retry_address = $this->get_postcode_retry_address( $address );

            if ( $retry_address ) {
                $retry = $this->request( $retry_address, $args );

                if ( $this->is_successful_request( $retry ) && $this->has_usable_result( $retry ) ) {
                    return $retry;
                }
            }
        }

        return $response;
    }

    /**
     * Run a single geocode request for the passed address.
     *
     * @since  3.0.0
     * @param  string         $address The address to geocode
     * @param  array          $args    Optional arguments
     * @return array|WP_Error $response The API response
     */
    private function request( $address, $args = [] ) {
        $wpsl_settings = $this->settings->get_group( 'api' );
        $api_key       = isset( $wpsl_settings['stadia_key'] ) ? $wpsl_settings['stadia_key'] : '';
        $language      = $this->get_api_language( 'stadia' );

        $params = [
            'text'    => $address,
            'size'    => 1,
            'api_key' => $api_key,
        ];

        if ( $language ) {
            $params['lang'] = $language;
        }

        /**
         * Restrict the geocoding results to the selected countries, if any.
         *
         * @see https://docs.stadiamaps.com/geocoding-search/search/#filter-by-country
         */
        $countrycodes = $this->get_restriction_countrycodes( $wpsl_settings );

        if ( $countrycodes ) {
            $params['boundary.country'] = $countrycodes;
        }

        $url = wpsl_stadia_api_base_url() . '/geocoding/v2/search?' . http_build_query( apply_filters( 'wpsl_stadia_api_request_params', $params, $args ) );

        $remote_args = apply_filters( 'wpsl_stadia_remote_args', [
            'user-agent' => 'WP Store Locator/' . WPSL_VERSION_NUM,
            'headers'    => [
                'Accept-Language' => $language,
            ],
        ] );

        $response = wp_remote_get( $url, $remote_args );

        if ( is_wp_error( $response ) || $response['response']['code'] !== 200 ) {
            $this->log_api_errors( $response, $url );
        }

        return $response;
    }

    /**
     * Check whether a request reached the API and came back with a 200.
     *
     * @since  3.0.0
     * @param  array|WP_Error $response The raw wp_remote_get() response
     * @return bool
     */
    private function is_successful_request( $response ) {
        if ( is_wp_error( $response ) ) {
            return false;
        }

        return (int) wp_remote_retrieve_response_code( $response ) === 200;
    }

    /**
     * Check whether a raw API response holds a result worth using.
     *
     * @since  3.0.0
     * @param  array|WP_Error $response The raw wp_remote_get() response
     * @return bool
     */
    private function has_usable_result( $response ) {
        if ( ! $this->is_successful_request( $response ) ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $data['features'][0]['geometry']['coordinates'] ) ) {
            return false;
        }

        return $this->is_usable_feature( $data['features'][0] );
    }

    /**
     * Check whether a feature actually matches what was searched for.
     *
     * Stadia returns something for nearly every query. When it cannot match the
     * input it sets match_type to 'fallback' and returns a loosely similar
     * record instead. Storing those as a store's coordinates puts the marker in
     * the wrong place, so they count as no result.
     *
     * Only 'fallback' is rejected. 'match' and 'interpolated' are both real
     * locations ( interpolated estimates a house number along a known street ),
     * and rejecting by name rather than allow-listing 'match' keeps any
     * match_type Stadia adds later usable by default.
     *
     * @since  3.0.0
     * @param  array $feature A single GeoJSON feature
     * @return bool
     */
    public function is_usable_feature( $feature ) {
        $parsed = $this->parse_stadia_feature( $feature );
        $usable = 'fallback' !== $parsed['match_type'];

        return (bool) apply_filters( 'wpsl_stadia_usable_geocode_result', $usable, $feature );
    }

    /**
     * Return the whitespace-stripped version of a postcode address, or
     * an empty string when the address should not be retried.
     *
     * Postcodes like 'SW1A 1AA' (UK) and 'K1A 0B1' (CA) are two short
     * alphanumeric groups holding both a digit and a letter. Anything longer,
     * punctuated, or purely alphabetic is a street or place name, where
     * removing the space would corrupt the query and waste a request.
     *
     * @since  3.0.0
     * @param  string $address The original geocode address
     * @return string The retry address, or '' to skip the retry
     */
    public function get_postcode_retry_address( $address ) {
        $trimmed = trim( (string) $address );
        $retry   = '';

        if ( preg_match( '/^[a-z0-9]{2,4}\s+[a-z0-9]{2,4}$/i', $trimmed )
            && preg_match( '/[0-9]/', $trimmed )
            && preg_match( '/[a-z]/i', $trimmed )
        ) {
            $retry = preg_replace( '/\s+/', '', $trimmed );
        } else {

            /**
             * A postcode inside a longer address, where the rest must survive
             * untouched.
             *
             * Four digits and two letters is the Dutch format, the one Stadia
             * indexes without its space. Deliberately narrow: collapsing any
             * two short groups would rewrite a US address like '1234 NW 5th
             * Ave' into '1234NW 5th Ave'. As a retry rather than up front, even
             * that stays safe since the retry is only kept when it beats the
             * original.
             */
            $retry = preg_replace( '/\b(\d{4})\s+([a-z]{2})\b/i', '$1$2', $trimmed );
        }

        // preg_replace() returns null when the subject cannot be processed.
        if ( ! is_string( $retry ) ) {
            $retry = '';
        }

        // Nothing gained when stripping the whitespace changes nothing.
        if ( $retry === $trimmed ) {
            $retry = '';
        }

        return (string) apply_filters( 'wpsl_stadia_postcode_retry_address', $retry, $trimmed );
    }

    /**
     * Check if the expected fields exist in the returned API data.
     *
     * The Stadia Maps Geocoding API returns a GeoJSON FeatureCollection.
     * A valid response has at least one feature with coordinates that actually
     * matches the query, see is_usable_feature().
     *
     * @since  3.0.0
     * @param  array $response The API data
     * @return bool
     */
    public function is_valid_response( $response ) {
        if ( isset( $response['features'] ) && count( $response['features'] ) > 0 ) {
            if ( isset( $response['features'][0]['geometry']['coordinates'] ) ) {
                return $this->is_usable_feature( $response['features'][0] );
            }
        }

        return false;
    }

    /**
     * If the returned data doesn't include the coordinates,
     * then we show this message.
     *
     * @since  3.0.0
     * @param  array  $response The API data
     * @return string $response The returned message including the error code and message from the API request
     */
    public function get_status_message( $response ) {
        if ( isset( $response['response'] ) && is_array( $response['response'] ) ) {
            /* translators: 1: error code, 2: error message */
            $response['message'] = sprintf( esc_html__( 'Something went wrong connecting to the Stadia Maps API. The server returned status code %1$s %2$s. Please try again later.', 'wp-store-locator' ), $response['response']['code'], $response['response']['message'] );
        } else if ( empty( $response ) || ( is_array( $response ) && count( $response ) === 0 ) ) {
            $wpsl_settings = $this->settings->get_group( 'api' );

            if ( $this->get_restriction_countrycodes( $wpsl_settings ) ) {
                return $this->get_restriction_notice();
            }

            $response = [];
            $response['message'] = esc_html__( 'The Stadia Maps API returned no results. Make sure the address is spelled correctly, and try to provide as many location details as possible.', 'wp-store-locator' );
        } else if ( isset( $response['features'] ) && ! $this->is_valid_response( $response ) ) {

            /**
             * Either no features at all, or a single 'fallback' feature that
             * is_valid_response() rejected. Both mean the address could not be
             * matched, and without this branch a rejected fallback would fall
             * through with no message at all and show an empty error notice.
             */
            $wpsl_settings = $this->settings->get_group( 'api' );

            if ( $this->get_restriction_countrycodes( $wpsl_settings ) ) {
                return $this->get_restriction_notice();
            }

            $response['message'] = esc_html__( 'The Stadia Maps API returned no results. Make sure the address is spelled correctly, and try to provide as many location details as possible.', 'wp-store-locator' );
        }

        return $response;
    }

    /**
     * Build the comma separated list of ISO country codes the geocoding
     * results should be restricted to.
     *
     * @since  3.0.0
     * @param  array  $wpsl_settings The 'api' settings group.
     * @return string The lowercase, comma separated country codes, or an empty string.
     */
    private function get_restriction_countrycodes( $wpsl_settings ) {
        if ( empty( $wpsl_settings['multiple_regions'] ) || ! is_array( $wpsl_settings['multiple_regions'] ) ) {
            return '';
        }

        $codes = array_filter( array_map( 'sanitize_text_field', $wpsl_settings['multiple_regions'] ) );

        return implode( ',', array_map( 'strtolower', $codes ) );
    }

    /**
     * Normalize a Stadia Maps Geocoding v2 GeoJSON feature.
     *
     * v2 nests the country name under context.whosonfirst.country.name and the
     * ISO code under context.iso_3166_a2, unlike the flat v1 (Pelias) shape.
     *
     * @since  3.0.0
     * @param  array $feature A single GeoJSON feature.
     * @return array {
     *     @type string $country     Country name, or '' when absent.
     *     @type string $country_iso Two-letter ISO country code, or '' when absent.
     *     @type string $zip         Postal code, or '' when absent.
     *     @type string $match_type  How well the result matches the query:
     *                               'match' and 'interpolated' are real
     *                               locations, 'fallback' means Stadia could
     *                               not match the input. '' when absent.
     * }
     */
    public function parse_stadia_feature( $feature ) {
        $props   = isset( $feature['properties'] ) ? $feature['properties'] : [];
        $context = isset( $props['context'] ) ? $props['context'] : [];
        $wof     = isset( $context['whosonfirst'] ) ? $context['whosonfirst'] : [];
        $address = isset( $props['address_components'] ) ? $props['address_components'] : [];

        $country     = isset( $wof['country']['name'] ) ? $wof['country']['name'] : '';
        $country_iso = isset( $context['iso_3166_a2'] ) ? $context['iso_3166_a2'] : '';
        $zip         = isset( $address['postal_code'] ) ? $address['postal_code'] : '';
        $match_type  = isset( $props['match_type'] ) ? $props['match_type'] : '';

        return [
            'country'     => $country,
            'country_iso' => $country_iso,
            'zip'         => $zip,
            'match_type'  => $match_type,
        ];
    }

    /**
     * Filter out the coordinates and country name from the data returned by the
     * Stadia Maps Geocoding v2 API.
     *
     * The API returns GeoJSON. Coordinates are in [lng, lat] order in the
     * geometry.coordinates array; country data comes from parse_stadia_feature.
     *
     * @since  3.0.0
     * @param  array $response      API data (GeoJSON FeatureCollection)
     * @return array $location_data The coordinates and country values
     */
    public function filter_location_data( $response ) {
        $feature = $response['features'][0];
        $coords  = $feature['geometry']['coordinates'];

        $latlng = [
            'lat' => $coords[1],
            'lng' => $coords[0],
        ];

        $parsed = $this->parse_stadia_feature( $feature );

        $location_data = [
            'country'     => $parsed['country'],
            'country_iso' => $parsed['country_iso'],
            'latlng'      => $this->format_latlng( $latlng ),
        ];

        // Absent for anything less precise than an address match.
        if ( $parsed['zip'] ) {
            $location_data['zip'] = $parsed['zip'];
        }

        return $location_data;
    }

    /**
     * Create the address string for the Stadia Maps Geocoding API.
     *
     * The Stadia Maps Search API uses a free-form 'text' parameter,
     * so we join the address parts with commas.
     *
     * @since  3.0.0
     * @param  array  $store_data      The provided store data
     * @return string $geocode_address The address we are sending to the Geocode API
     */
    public function create_stadia_address( $store_data ) {
        $address_parts = apply_filters( 'wpsl_stadia_address_parts', [
            'address',
            'city',
            'state',
            'zip',
            'country',
        ] );

        $address = [];

        foreach ( $address_parts as $part ) {
            if ( isset( $store_data[ $part ] ) && $store_data[ $part ] ) {
                $address[] = trim( $store_data[ $part ] );
            }
        }

        return implode( ', ', $address );
    }
}