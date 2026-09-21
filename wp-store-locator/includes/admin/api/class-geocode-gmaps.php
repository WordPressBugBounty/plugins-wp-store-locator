<?php
/**
 * Handle geocode request to the Google Geocode API v4.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 * @see    https://developers.google.com/maps/documentation/geocoding/geocoding-v4-overview
 */

namespace WPSL\Admin\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Admin\Core\Notices;
use WPSL\Core\Settings\Manager as WpslSettings;

class Geocode_Gmaps extends Geocode {

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Settings\Manager
     */
    public $settings;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Admin\Core\Notices    $notices  Notices manager instance
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( Notices $notices, WpslSettings $settings ) {
        $this->notices  = $notices;
        $this->settings = $settings;
    }

    /**
     * Make the call to the Google Geocode API.
     *
     * @since  3.0.0
     * @see    https://developers.google.com/maps/documentation/geocoding/requests-geocoding
     * @param  string         $address  The address to geocode
     * @param  array          $args     Optional arguments
     * @return array|WP_Error $response The API response
     */
    public function call_api( $address, $args = [] ) {
        $defaults = [
            'key_type' => 'server_key',
            'callback' => 'wpslCallback'
        ];

        $args = array_merge( $defaults, $args );
        
        // Get API key from params
        $api_params = wpsl_get_gmap_api_params( $args );
        
        $url = 'https://geocode.googleapis.com/v4/geocode/address/' . rawurlencode( $address ) . '?' . ltrim( $api_params, '&' );
        $url = apply_filters( 'wpsl_gmaps_api_request_url', $url, $address, $args );
        
        $response = wp_remote_get( $url, [ 'timeout' => 15 ] );

        // If we don't get a valid response, log the error and the $url.
        if ( is_wp_error( $response ) || $response['response']['code'] !== 200 ) {
            $this->log_api_errors( $response, $url );
        }

        return $response;
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
        if ( isset( $response['results'][0] ) && isset( $response['results'][0]['location']['latitude'] ) ) {
            return true;
        }

        return false;
    }

    /**
     * Check if we got a valid response from
     * the Google Geocode API. If not, then see
     * what the returned error code is.
     * 
     * @param  array|WP_Error $api_response Data returned from the Google Maps API
     * @param  string         $address      The address data send to the API
     * @return mixed|string   $response     Either the returned API data or an error message
     */
    protected function check_gmaps_response_code( $api_response, $address ) {
        if ( is_wp_error( $api_response ) ) {
            /* translators: %s: error message from the API */
            $response['message'] = sprintf( esc_html__( 'Something went wrong connecting to the Google Geocode API: %s. Please try again later.', 'wp-store-locator' ), $api_response->get_error_message() );
        } else {
            $http_code = (int) wp_remote_retrieve_response_code( $api_response );
            $body = wp_remote_retrieve_body( $api_response );
            $data = json_decode( $body, true );
            
            // HTTP status codes indicate errors
            if ( $http_code !== 200 ) {
                $api_message = isset( $data['error']['message'] ) ? $data['error']['message'] : '';
                $api_status = isset( $data['error']['status'] ) ? $data['error']['status'] : '';
                
                switch ( $http_code ) {
                    case 429:
                        $status = 'OVER_QUERY_LIMIT';
                        $response['message'] = $api_message ? esc_html( $api_message ) : esc_html__( 'You have reached the daily allowed geocoding limit.', 'wp-store-locator' );
                        $response['label'] = esc_html__( 'Read more', 'wp-store-locator' );
                        $response['url'] = 'https://developers.google.com/maps/documentation/geocoding/#Limits';
                        break;
                    case 403:
                        $status = 'REQUEST_DENIED';
                        $response['message'] = esc_html__( 'The address could not be converted into coordinates because Google Maps rejected the request. This usually means the API key is missing, restricted, or the Geocoding API is not enabled for it.', 'wp-store-locator' );
                        $response['label']   = esc_html__( 'How to set up your API key', 'wp-store-locator' );
                        $response['url']     = 'https://wpstorelocator.co/document/create-google-api-keys/';

                        // Pass the raw Google API response along so the editor can
                        // log it to the browser console for debugging.
                        $raw_response = $data ? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $body;
                        if ( $raw_response ) {
                            $response['details'] = $raw_response;
                        }
                        break;
                    case 400:
                        $status = 'INVALID_REQUEST';
                        $data_issue = '';
                        
                        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl_csv' ) {
                            $response['label'] = 'UTF-8 encoding.';
                            $response['url']   = 'https://wpstorelocator.co/document/csv-manager/#utf8';
                            $data_issue = esc_html__( 'You can fix this by making sure the CSV file uses', 'wp-store-locator' );
                        } else if ( ! $address ) {
                            $data_issue = esc_html__( 'You need to provide the details for either the address, city, state or country before the API can return coordinates.', 'wp-store-locator' );
                        }
                        
                        /* translators: 1: error code, 2: error message, 3: additional data issue description */
                        $response['message'] = sprintf( esc_html__( 'The Google Geocode API reported the following problem: error %1$s %2$s. %3$s', 'wp-store-locator' ), $http_code, $api_message ? esc_html( $api_message ) : $api_response['response']['message'], $data_issue );
                        break; 
                    case 500:
                        $status = 'UNKNOWN_ERROR';
                        /* translators: 1: error code, 2: error message */
                        $response['message'] = sprintf( esc_html__( 'The Google Geocode API reported the following problem: error %1$s %2$s. Please try again later.', 'wp-store-locator' ), $http_code, $api_response['response']['message'] );
                        break;
                    default:
                        $status = 'UNKNOWN_ERROR';
                        $response = [
                            /* translators: 1: error code, 2: error message */
                            'message' => sprintf( esc_html__( 'The Google Geocode API reported the following problem: error %1$s %2$s. If the problem persists contact', 'wp-store-locator' ), $http_code, $api_response['response']['message'] ),
                            'label'   => esc_html__( 'support', 'wp-store-locator' ),
                            'url'     => 'https://wpstorelocator.co/support/',
                        ];
                }
                
                // Store the status for backward compatibility
                if ( ! isset( $response['status'] ) ) {
                    $response['status'] = $status;
                }

                // Store error details
                $response['error_detail'] = [
                    'http_code' => $http_code,
                    'api_status' => $api_status,
                ];

                // Structured notice for the editor, matching the settings page key
                // error: a bold title, the code/reason line, then the explanation
                // (and any documentation link) as the hint.
                $reason = $api_message ? $api_message : ( $api_status ? $api_status : wp_remote_retrieve_response_message( $api_response ) );

                // The IP/HTTP-referrer restriction advice (same as the settings page).
                $gmaps_hint = wpsl_get_gmaps_error_hint( $reason );

                // When that specific advice applies (a misconfigured key), it covers
                // the problem, so skip the generic explanation — matching the
                // settings page. Otherwise keep the per-case explanation (e.g. the
                // daily-limit or invalid-address messages), which is the main info.
                $hint = $gmaps_hint ? $gmaps_hint : '<p>' . $response['message'] . '</p>';

                if ( isset( $response['label'], $response['url'] ) ) {
                    $hint .= '<p><a target="_blank" rel="noopener" href="' . esc_url( $response['url'] ) . '">' . esc_html( $response['label'] ) . '</a></p>';
                }

                $response['notice_html'] = wpsl_build_key_error_notice(
                    __( 'The Google Geocode API returned the following error.', 'wp-store-locator' ),
                    $http_code,
                    $reason,
                    $hint
                );
            } else {
                // HTTP 200: Check for empty results (ZERO_RESULTS)
                $response = $data;
                
                if ( empty( $response['results'] ) ) {
                    $response['status'] = 'ZERO_RESULTS';
                    $response['message'] = esc_html__( 'The Google Geocoding API returned no results for the supplied address. Please change the address and try again.', 'wp-store-locator' );
                } else if ( ! $this->is_precise_result( $response ) ) {
                    /**
                     * The API still returned usable coordinates, but not for a
                     * precise address ( e.g. it resolved to a country, region or
                     * locality ).
                     */
                    $result_types = isset( $response['results'][0]['types'] ) ? $response['results'][0]['types'] : [];
                    $result_type  = ! empty( $result_types ) ? reset( $result_types ) : 'unknown';

                    $response['warning'] = sprintf(
                        /* translators: %s: the result type returned by the API (e.g. "country", "locality") */
                        esc_html__( 'The address could not be geocoded precisely. The API returned a result of type "%s" instead of a precise address, so the returned coordinates might be unreliable. Please check the address details.', 'wp-store-locator' ),
                        esc_html( $result_type )
                    );
                } else if ( isset( $response['results'][0]['partial_match'] ) && ! defined( 'WPSL_CSV_IMPORT' ) ) {
                    $partial_msg['message'] = esc_html__( 'The response from the Geocode API contains the partial match field, which means the returned coordinates may not be 100% accurate. You can check the preview map in the right sidebar if the marker is in the expected location. If this is not the case, then you can adjust it by dragging it to the correct location. You can also try to simplify the address. So if it contains things like 2th floor or the building name, then remove them. Also make sure the state field contains valid data before saving the location again ', 'wp-store-locator' );
                    $partial_msg['label']   = esc_html__( 'Read more', 'wp-store-locator' );
                    $partial_msg['url']     = 'https://developers.google.com/maps/documentation/geocoding/requests-geocoding#results';

                    if ( ! defined( 'REST_REQUEST' ) ) {
                        $this->notices->save( 'info', $partial_msg );
                    }
                    
                    $response = array_merge( $response, $partial_msg );
                }
            }
        }

        return $response;
    }

    /**
     * Check whether the geocode result points to a precise address.
     *
     * The Google v4 API returns a `types` array on each result. A precise
     * match contains an address-level type; less precise matches resolve to a
     * country, region, locality, postal code, etc.
     *
     * @since  3.0.0
     * @param  array $response The API response.
     * @return bool  True if the top result is a precise address.
     */
    protected function is_precise_result( $response ) {
        $result_types = isset( $response['results'][0]['types'] ) ? $response['results'][0]['types'] : [];

        /**
         * Filter the result types considered a precise address match.
         *
         * @since 3.0.0
         * @param array $precise_types The Google result types treated as precise.
         */
        $precise_types = apply_filters( 'wpsl_gmaps_precise_result_types', [
            'street_address',
            'premise',
            'subpremise',
            'establishment',
            'point_of_interest',
        ] );

        return ! empty( array_intersect( $precise_types, $result_types ) );
    }

    /**
     * Filter out the coordinates and country name from
     * the data returned by the Google Geocode API.
     *
     * @since  3.0.0
     * @param  array $response      API data
     * @return array $location_data The coordinates and country values
     */
    public function filter_location_data( $response ) {
        $latlng = [
            'lat' => $response['results'][0]['location']['latitude'],
            'lng' => $response['results'][0]['location']['longitude']
        ];
        
        $location_data = [
            'latlng' => $this->format_latlng( $latlng )
        ];
        
        /**
         * The v4 API only includes the structured `postalAddress` field for
         * precise, address-level results; it's omitted for other matches
         * (country, region, etc.). 
         * 
         * When it's present we use its `regionCode` for the ISO code, but still read
         *  the country name from `addressComponents` since `regionCode` is just 
         * the code (e.g. "NL"). When it's absent we take both the name and ISO from `addressComponents`.
         */
        if ( isset( $response['results'][0]['postalAddress']['regionCode'] ) ) {
            $location_data['country_iso'] = $response['results'][0]['postalAddress']['regionCode'];

            $country = $this->filter_country_name( $response );
            if ( ! empty( $country ) ) {
                $location_data['country'] = $country['longText'];
            }
        } else {
            $country = $this->filter_country_name( $response );
            if ( ! empty( $country ) ) {
                $location_data['country'] = $country['longText'];
                $location_data['country_iso'] = $country['shortText'];
            }
        }

        /**
         * The postcode lives in postalAddress for precise matches, and only in
         * the address components for the rest, so fall back to those.
         */
        if ( ! empty( $response['results'][0]['postalAddress']['postalCode'] ) ) {
            $location_data['zip'] = $response['results'][0]['postalAddress']['postalCode'];
        } else {
            $postal_code = $this->filter_address_component( $response, 'postal_code' );

            if ( ! empty( $postal_code['longText'] ) ) {
                $location_data['zip'] = $postal_code['longText'];
            }
        }

        return $location_data;
    }

    /**
     * Grab the bounds latlng from the API v4 response.
     * 
     * @since  3.0.0
     * @param  array $response The API data
     * @return array $bounds   latlng bounds
     */
    protected function get_bounds( $response ) {
        if ( isset( $response['results'][0]['viewport'] ) ) {
            $bounds = [
                'sw' => $response['results'][0]['viewport']['low']['latitude'] . ',' . $response['results'][0]['viewport']['low']['longitude'],
                'ne' => $response['results'][0]['viewport']['high']['latitude'] . ',' . $response['results'][0]['viewport']['high']['longitude']
            ];
        } else {
            $bounds = [
                'sw' => '',
                'ne' => ''
            ];
        }

        return $bounds;
    }

    /**
     * Check the returned status code from the API call.
     *
     * @since  3.0.0
     * @param  array $response        The returned API data
     * @return array $status_response Either the original data, or an error message based on the set status message
     */
    protected function get_status_message( $response ) {
        $status_response = [];

        if ( isset( $response['status'] ) ) {
            switch ( $response['status'] ) {
                case 'ZERO_RESULTS':
                    $status_response['message'] = esc_html__( 'The Google Geocoding API returned no results for the supplied address. Please change the address and try again.', 'wp-store-locator' );
                    break;
                case 'OVER_QUERY_LIMIT':
                    $status_response = [
                        'message' => esc_html__( 'You have reached the daily allowed geocoding limit, you can read more', 'wp-store-locator' ),
                        'label'   => 'here',
                        'url'     => 'https://developers.google.com/maps/documentation/geocoding/#Limits',
                    ];
                    break;
                case 'REQUEST_DENIED':
                    /* translators: %s: error message from the API */
                    $status_response['message'] = sprintf( esc_html__( 'The Google Geocoding API returned REQUEST_DENIED. %s', 'wp-store-locator' ), $response['error_message'] );
                    break;
                default:
                    $status_response['message'] = esc_html__( 'The Google Geocoding API failed to return valid data, please try again later.', 'wp-store-locator' );
                    break;
            }
        } else {
            $status_response = $response;
        }

        return $status_response;
    }

    /**
     * Filter out the country name from the API response.
     * 
     * @since  3.0.0
     * @param  array $response The API response
     * @return array $country  The country component
     */
    public function filter_country_name( $response ) {
        return $this->filter_address_component( $response, 'country' );
    }

    /**
     * Return the address component matching the requested type.
     *
     * @since  3.0.0
     * @param  array  $response The API response
     * @param  string $type     The component type to look for ( country, postal_code, ... )
     * @return array  The matching component, or an empty array
     */
    public function filter_address_component( $response, $type ) {
        if ( isset( $response['results'][0]['addressComponents'] ) ) {
            foreach ( $response['results'][0]['addressComponents'] as $component ) {
                if ( in_array( $type, $component['types'], true ) ) {
                    return $component;
                }
            }
        }

        return [];
    }

    /**
     * Return an formatted error messages
     * 
     * @since  3.0.0
     * @param  array  $geocode_response API response
     * @return string $error_msg        Formatted and readable error message
     */
    public function get_error_message( $geocode_response ) {
        if ( isset( $geocode_response['error_message'] ) && $geocode_response['error_message'] ) {

            // If the problem is IP based, then show a different error msg.
            if ( strpos( $geocode_response['error_message'], 'IP' ) !== false  ) {
                /* translators: 1: line break, 2: error message, 3: line break, 4: opening link tag for referrer documentation, 5: closing link tag, 6: opening link tag for Google API Console, 7: closing link tag */
                $error_msg = sprintf( __( '%1$sError message: %2$s. %3$s Make sure the IP address mentioned in the error matches with the IP set as the %4$sreferrer%5$s for the server API key in the %6$sGoogle API Console%7$s.', 'wp-store-locator' ), '<br><br>', self::clickable_error_links( $geocode_response ), $breaks, '<a href="https://wpstorelocator.co/document/create-google-api-keys/#server-key-referrer">', '</a>', '<a href="https://console.developers.google.com">', '</a>' );
            } else {
                /* translators: 1: opening paragraph tag, 2: error message, 3: opening link tag for API keys documentation, 4: closing link tag, 5: opening link tag for troubleshooting documentation, 6: closing paragraph tag with closing link tag */
                $error_msg = sprintf( __( '%1$s %2$s %3$sConfigure API keys%4$s | %5$sTroubleshooting%6$s', 'wp-store-locator' ),'<p>', self::clickable_error_links( $geocode_response ), '<br><a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys">', '</a>', '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#troubleshooting">', '</a></p>' );
            }
        } else {
            $error_msg = '';
        }

        return $error_msg;
    }

    /**
     * Error messages returned by the Google Maps API
     * don't always contain clickable links.
     *
     * They now just look like this http://g.co/dev/maps-no-account
     * and are not clickable. To change this we wrap a href around it.
     *
     * @since  2.2.22
     * @param  array  $geocode_response The API response
     * @return string $msg              The clickable error message
     */
    public static function clickable_error_links( $geocode_response ) {
        $msg = $geocode_response['error_message'];

        if ( strpos( $geocode_response['error_message'],'href' ) === false ) {
            preg_match_all( '#\bhttp(s?)?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $geocode_response['error_message'], $match );

            foreach ( $match[0] as $k => $url ) {
                $msg = str_replace( $url, '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $msg );
            }
        }

        $last_dot = strrpos( $msg, '.' );

        if ( $last_dot ) {
            $msg = substr_replace( $msg, ' ( ' . $geocode_response['status'] . ' )', $last_dot, 0 );
        }

        return $msg;
    }
}