<?php
/**
 * Handle geocode requests to the Mapbox Geocode API v6.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 * @see    https://docs.mapbox.com/api/search/geocoding/
 */

namespace WPSL\Admin\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Geocode_Mapbox extends Geocode {

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
     * @param  \WPSL\Core\Settings\Manager $settings The settings manager
     * @return void
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Make a request to the Mapbox Geocode API.
     *
     * @since  3.0.0
     * @param  string         $address  The address to geocode
     * @param  array          $args     Optional arguments
     * @return array|WP_Error $response The API response
     */
    public function call_api( $address, $args = [] ) {
        $wpsl_settings = $this->settings->get_group( 'api' );

        $params = 'q=' . urlencode( $address ) . '&';

        if ( $wpsl_settings['mapbox_language'] ) {
            $params .= 'language=' . $wpsl_settings['mapbox_language'] . '&';
        }

        if ( $wpsl_settings['multiple_regions'] ) {
            $params .= 'country=' . implode( ',', $wpsl_settings['multiple_regions'] ) . '&';
        }

        /**
         * Handle the permanent parameter - convert to string 'true' or 'false' for the API
         * 
         * Using Permanent storage with the Geocoding API requires that
         * you have a valid credit card on file or an active enterprise contract.
         * 
         * @see https://docs.mapbox.com/api/search/geocoding/#storing-geocoding-results
         */
        if ( isset( $args['permanent'] ) && $args['permanent'] ) {
            $params .= 'permanent=true&';
        }
        
        // Include the API key
        $access_token = ( isset( $args['access_token'] ) && $args['access_token'] ) ? $args['access_token'] : $wpsl_settings['mapbox_key'];

        $params .= 'access_token=' . $access_token . '&limit=1';
        
        // Remove trailing ampersand
        $params = rtrim( $params, '&' );

        $url = 'https://api.mapbox.com/search/geocode/v6/forward?' . apply_filters( 'wpsl_mapbox_api_request_params', $params, $args );

        /**
         * Send this site's URL as the Referer so Mapbox matches
         * a public token's URL restriction against our own domain
         */
        $request_args = [
            'timeout' => 15,
            'headers' => [
                'Referer' => home_url( '/' ),
            ],
        ];

        $response = wp_remote_get( $url, $request_args );

        // If we don't get a valid response, log the error and the $url.
        if ( is_wp_error( $response ) || $response['response']['code'] !== 200 ) {
            $this->log_api_errors( $response, $url );
        }

        return $response;
    }

    /**
     * Check the response code returned by the Mapbox Geocode API.
     *
     * @since  3.0.0
     * @param  array|WP_Error $api_response Data returned from the Mapbox API.
     * @param  string         $address      The address sent to the API.
     * @return array          $response     Either the API data or an error message.
     */
    public function check_mapbox_response_code( $api_response, $address ) {
        if ( is_wp_error( $api_response ) ) {
            /* translators: %s: error message from the API */
            $response['message'] = sprintf( esc_html__( 'Something went wrong connecting to the Mapbox Geocode API: %s. Please try again later.', 'wp-store-locator' ), $api_response->get_error_message() );

            return $response;
        }

        $http_code = (int) wp_remote_retrieve_response_code( $api_response );
        $body      = wp_remote_retrieve_body( $api_response );
        $data      = json_decode( $body, true );

        // A successful request, return the data ( or a no-results message ).
        if ( $http_code === 200 ) {
            $response = is_array( $data ) ? $data : [];

            if ( isset( $response['features'] ) && empty( $response['features'] ) ) {

                /**
                 * Mapbox restricts server-side via the 'country' parameter, so
                 * an empty result while a restriction is active most likely means
                 * the address falls outside the allowed countries. Show the same
                 * friendly notice the other providers use in that case.
                 */
                $api = $this->settings->get_group( 'api' );

                if ( ! empty( $api['multiple_regions'] ) ) {
                    return array_merge( $response, $this->get_restriction_notice() );
                }

                $response['message'] = esc_html__( 'The Mapbox API has returned no results. Make sure the address is spelled correctly, and try to provide as many locations details as possible.', 'wp-store-locator' );
            } elseif ( isset( $response['features'][0]['properties']['feature_type'] ) ) {
                $feature_type = $response['features'][0]['properties']['feature_type'];

                /**
                 * Filter the allowed feature types for geocoding results.
                 *
                 * By default only 'address' is accepted. If the API returns a less
                 * precise result (e.g. 'country', 'region', 'place'), a warning is shown.
                 *
                 * @since 3.0.0
                 * @param array $allowed_types Array of allowed feature_type values.
                 */
                $allowed_types = apply_filters( 'wpsl_mapbox_allowed_feature_types', [ 'address' ] );

                if ( ! in_array( $feature_type, $allowed_types, true ) ) {
                    /**
                     * The API still returned usable coordinates, just not for an
                     * exact address ( e.g. a "country" or "place" ). We keep the
                     * coordinates and flag this as a non-blocking warning so the
                     * store can still be saved, while prompting the user to check
                     * the address details.
                     */
                    $response['warning'] = sprintf(
                        /* translators: %s: the feature type returned by the API (e.g. "country", "place") */
                        esc_html__( 'The address could not be geocoded precisely. The API returned a result of type "%s" instead of "address", so the returned coordinates might be unreliable. Please check the address details.', 'wp-store-locator' ),
                        esc_html( $feature_type )
                    );
                } elseif ( $this->is_low_confidence_match( $response['features'][0] ) ) {

                    /**
                     * An address result, but a poorly matched one. Mapbox grades
                     * address matches through match_code, where a confidence of
                     * 'low' means the house number, the region, or more than two
                     * other components had to be corrected, so the coordinates
                     * can belong to a different address than the one entered.
                     *
                     * Warned about rather than rejected, matching how a
                     * non-address feature_type is handled directly above: the
                     * coordinates are usable enough to save, and the store owner
                     * is the one who can tell whether the address is right.
                     *
                     * @see https://docs.mapbox.com/api/search/geocoding/
                     */
                    $response['warning'] = esc_html__( 'The address could not be geocoded precisely. The API had to correct several parts of the address to find a match, so the returned coordinates might belong to a different address. Please check the address details.', 'wp-store-locator' );
                }
            }

            return $response;
        }

        // An error response ( e.g. 401 Not Authorized, 403, 422, 429 ).
        $api_message = isset( $data['message'] ) ? $data['message'] : wp_remote_retrieve_response_message( $api_response );
        $status      = trim( $http_code . ' ' . $api_message );

        /* translators: %s: the API status code and message ( e.g. "401 Not Authorized - No Token" ) */
        $response['message'] = sprintf( esc_html__( 'There\'s a problem with the Mapbox Geocode API. It returned: %s. Please try again later.', 'wp-store-locator' ), esc_html( $status ) );

        // Structured notice for the editor, matching the settings page key error:
        // a bold title, the code/reason line, then an actionable hint.
        $response['notice_html'] = wpsl_build_key_error_notice(
            __( 'The Mapbox API has returned the following error for the used API key.', 'wp-store-locator' ),
            $http_code,
            $api_message,
            wpsl_get_mapbox_error_hint( $status )
        );

        // Pass the raw Mapbox API response along so the editor can log it to the console.
        $raw_response = $data ? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $body;
        if ( $raw_response ) {
            $response['details'] = $raw_response;
        }

        return $response;
    }

    /**
     * Check whether Mapbox had to correct the address to find a match.
     *
     * Mapbox grades address matches through match_code, where a confidence of
     * 'low' means the house number, the region, or more than two other
     * components were corrected to produce the result.
     *
     * match_code is only present on address-type features, so a 'place' or
     * 'postcode' result carries none. An absent match_code therefore means
     * "not graded", not "bad", and is never reported as low confidence.
     *
     * @since  3.0.0
     * @see    https://docs.mapbox.com/api/search/geocoding/
     * @param  array $feature A single GeoJSON feature
     * @return bool
     */
    public function is_low_confidence_match( $feature ) {
        $confidence = isset( $feature['properties']['match_code']['confidence'] )
            ? $feature['properties']['match_code']['confidence']
            : '';

        $is_low = ( 'low' === $confidence );

        return (bool) apply_filters( 'wpsl_mapbox_low_confidence_match', $is_low, $feature );
    }

    /**
     * Check if the expected fields exist
     * in the returned API data.
     *
     * @since  3.0.0
     * @param  $response The API data
     * @return bool
     */
    public function is_valid_response( $response ) {
        if ( isset( $response['features'] ) && isset( $response['features'][0] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Filter out the coordinates and country name
     * from the data returned by the Mapbox API.
     *
     * @since  3.0.0
     * @param  array $response      API data
     * @return array $location_data The coordinates and country values
     */
    public function filter_location_data( $response ) {
        $feature = $response['features'][0];

        $latlng = [
            'lat' => $feature['geometry']['coordinates'][1],
            'lng' => $feature['geometry']['coordinates'][0]
        ];

        $location_data = [
            'latlng' => $this->format_latlng( $latlng )
        ];

        $country = isset( $feature['properties']['context']['country'] ) ? $feature['properties']['context']['country'] : [];

        if ( isset( $country['name'] ) ) {
            $location_data['country'] = $country['name'];
        }

        if ( isset( $country['country_code'] ) ) {
            $location_data['country_iso'] = $country['country_code'];
        }

        // Only an address level match includes the postcode context entry.
        if ( ! empty( $feature['properties']['context']['postcode']['name'] ) ) {
            $location_data['zip'] = $feature['properties']['context']['postcode']['name'];
        }

        return $location_data;
    }
}