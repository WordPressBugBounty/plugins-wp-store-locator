<?php
/**
 * Handle geocode requests to the Nominatim API.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 * @see    https://nominatim.org/release-docs/develop/api/Search/
 */

namespace WPSL\Admin\API;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as Settings;
use WPSL\Core\Utils\Request_Throttle;

class Geocode_Osm extends Geocode {

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    public $settings;

    /**
     * Spaces the requests to Nominatim for the whole site.
     *
     * @since 3.0.0
     * @var Request_Throttle
     */
    private $throttle;

    /**
     * Constructor
     *
     * @since  3.0.0
     * @param  Settings              $settings The settings manager
     * @param  Request_Throttle|null $throttle Optional throttle, one is built from the filters when omitted.
     * @return void
     */
    public function __construct( Settings $settings, $throttle = null ) {
        $this->settings = $settings;

        if ( ! $throttle ) {

            /**
             * Nominatim allows one request per second for the whole
             * application; the public endpoint's per-IP limit doesn't cover
             * that. wpsl_nominatim_throttle_interval sets the seconds between
             * requests ( 0 disables it, e.g. self-hosted Nominatim );
             * wpsl_nominatim_throttle_max_wait sets how long a request waits
             * for its slot ( see default_max_wait() ).
             *
             * @see https://operations.osmfoundation.org/policies/nominatim/
             */
            $throttle = new Request_Throttle(
                'nominatim',
                apply_filters( 'wpsl_nominatim_throttle_interval', 1 ),
                apply_filters( 'wpsl_nominatim_throttle_max_wait', self::default_max_wait() )
            );
        }

        $this->throttle = $throttle;
    }

    /**
     * How long a request waits for the site-wide Nominatim slot by default.
     *
     * @since  3.0.0
     * @return int Seconds.
     */
    public static function default_max_wait() {
        $visitor = wp_doing_ajax() && ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() );

        return $visitor ? 1 : 5;
    }

    /**
     * Make the call to the Nominatim API.
     *
     * @since  3.0.0
     * @see    https://nominatim.org/release-docs/develop/api/Search/
     * @param  string         $address  The urlencoded address to geocode
     * @param  array          $args     Optional arguments
     * @return array|WP_Error $response The API response
     */
    public function call_api( $address, $args = [] ) {
        $wpsl_settings = $this->settings->get_group( 'api' );

        /**
         * Nominatim geocodes for OSM, but also for Mapbox when its "Server
         * geocoder" option is set to Nominatim. In that case the Mapbox
         * language and country restrictions should apply to the request, not the
         * unused OSM settings, so the language is picked based on the active
         * map service.
         */
        $language = $this->get_api_language( $this->get_language_provider( $wpsl_settings ) );

        /**
         * The start location from the settings page has no street=/city=/country=
         * parameters and could be a street or city, so use q= instead.
         *
         * rawurlencode() prevents a free-form address from injecting Nominatim
         * parameters via "&"/"=". Structured queries (postalcode=, street=&city=...)
         * already contain "=" and are pre-encoded, so they skip this branch.
         */
        if ( strpos( $address,'=' ) === false ) {
            $address = 'q=' . rawurlencode( $address );
        }

        $params = $address . '&format=json&addressdetails=1&limit=1';

        /**
         * Restrict the geocoding results to the selected countries, if any.
         * Nominatim only returns matches within these countries, so an address
         * from a different country returns no results and is reported as such.
         *
         * @see https://nominatim.org/release-docs/develop/api/Search/#result-restriction
         */
        $countrycodes = $this->get_restriction_countrycodes( $wpsl_settings );

        if ( $countrycodes ) {
            $params .= '&countrycodes=' . $countrycodes;
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . apply_filters( 'wpsl_osm_api_request_params', $params, $args );

        /**
         * Required for the API to work. Otherwise you get a 403 error.
         *
         * The site URL identifies the install. With only the plugin name every
         * site sends the same User-Agent, so if Nominatim blocks one site that
         * misbehaves, it blocks all of them.
         */
        $args = apply_filters( 'wpsl_nominatim_remote_args', [
            'user-agent' => 'WP Store Locator/' . WPSL_VERSION_NUM . ' (' . home_url( '/' ) . ')',
            'headers' => [
                'Accept-Language' => $language
            ]
        ] );

        if ( ! $this->throttle->acquire() ) {
            if ( $this->throttle->has_db_error() ) {
                return new \WP_Error( 'wpsl_nominatim_throttle_failed', __( 'The request to the OpenStreetMap geocoder could not be scheduled because of a database error.', 'wp-store-locator' ) );
            }

            return new \WP_Error( 'wpsl_nominatim_busy', __( 'The OpenStreetMap geocoder is busy, too many addresses were looked up at the same time.', 'wp-store-locator' ) );
        }

        try {
            $response = wp_remote_get( $url, $args );
        } finally {
            $this->throttle->release();
        }

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
        if ( isset( $response[0] ) && isset( $response[0]['lat'] ) && $response[0]['lat'] ) {
            return true;
        }

        return false;
    }

    /**
     * If the returned data doesn't include the coordinates,
     * then we show this message.
     *
     * The Google Geocode API has different status messages
     * like ZERO_RESULTS and so, but can not find any
     * documentation on this for OpenStreetMaps.
     *
     * @since  3.0.0
     * @param  array  $response The API data
     * @return string $response The returned message including the error code and message from the API request
     */
    public function get_status_message( $response ) {
        if ( isset( $response['response'] ) && is_array( $response['response'] ) ) {
            /* translators: 1: error code, 2: error message */
            $response['message'] = sprintf( esc_html__( 'Something went wrong connecting to the OpenStreetMaps API. The server returned status code %1$s %2$s. Please try again later.', 'wp-store-locator' ), $response['response']['code'],  $response['response']['message'] );
        } else if ( empty( $response ) || ( is_array( $response ) && count( $response ) === 0 ) ) {

            /**
             * When a country restriction is active the most likely cause of an
             * empty result is that the address lies outside the allowed
             * countries ( Nominatim filtered it out via countrycodes ), so show
             * the same friendly restriction notice the other providers use.
             */
            $wpsl_settings = $this->settings->get_group( 'api' );

            if ( $this->get_restriction_countrycodes( $wpsl_settings ) ) {
                return $this->get_restriction_notice();
            }

            $response = [];
            $response['message'] = esc_html__( 'The OpenStreetMaps API returned no results. Make sure the address is spelled correctly, and try to provide as many locations details as possible.', 'wp-store-locator' );
        }

        return $response;
    }

    /**
     * Determine which map service's language setting should be used.
     *
     * Nominatim is the geocoder for the OpenStreetMap service, and for Mapbox
     * when its "Server geocoder" option is set to Nominatim. In the latter case
     * the Mapbox language setting is the one shown / configured in the UI, so
     * that's the one we honor.
     *
     * @since  3.0.0
     * @param  array  $wpsl_settings The 'api' settings group.
     * @return string The provider key used to look up the language setting.
     */
    private function get_language_provider( $wpsl_settings ) {
        return ( isset( $wpsl_settings['active_map_service'] ) && $wpsl_settings['active_map_service'] === 'mapbox' ) ? 'mapbox' : 'osm';
    }

    /**
     * What besides the searched text decides Nominatim's answer: the country
     * restriction and the language, read exactly as call_api() reads them.
     *
     * The Nominatim cache keys its entries on this, so an answer given under
     * one configuration is never served under another.
     *
     * @since  3.0.0
     * @return string E.g. "be,fr|nl". The countries are sorted, their order doesn't change the answer.
     */
    public function restriction_identity() {
        $wpsl_settings = $this->settings->get_group( 'api' );

        $countries = array_filter( explode( ',', $this->get_restriction_countrycodes( $wpsl_settings ) ) );
        sort( $countries );

        return implode( ',', $countries ) . '|' . $this->get_api_language( $this->get_language_provider( $wpsl_settings ) );
    }

    /**
     * Build the comma separated list of ISO country codes the geocoding
     * results should be restricted to.
     *
     * The country restriction ( multiple_regions ) is shared by all map
     * services, so it applies to Nominatim regardless of whether it's used for
     * the OpenStreetMap or the Mapbox service.
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
     * Filter out the coordinates, country name and postcode from
     * the data returned by the Nominatim API.
     *
     * @since  3.0.0
     * @param  array $response      API data
     * @return array $location_data The coordinates, country and zip values
     */
    public function filter_location_data( $response ) {
        $latlng = [
            'lat' => $response[0]['lat'],
            'lng' => $response[0]['lon']
        ];

        $address = isset( $response[0]['address'] ) ? $response[0]['address'] : [];

        $location_data = [
            'latlng' => $this->format_latlng( $latlng )
        ];

        /**
         * Only an address level match carries the full set. A city or country
         * match leaves parts out, and an absent key must not become an empty
         * value that later overwrites what the user typed.
         */
        $fields = [
            'country'     => 'country',
            'country_iso' => 'country_code',
            'zip'         => 'postcode'
        ];

        foreach ( $fields as $key => $source ) {
            if ( ! empty( $address[$source] ) ) {
                $location_data[$key] = $address[$source];
            }
        }

        return $location_data;
    }

    /**
     * Create the address for the Nominatim Geocode API
     *
     * @see https://nominatim.org/release-docs/develop/api/Search/#parameters
     * @since  3.0.0
     * @param  array  $store_data      The provided store data
     * @return string $geocode_address The address we are sending to the Geocode API
     */
    public function create_osm_address( $store_data ) {
        /**
         * Map the location fields to the expected
         * data structure for the API request.
         *
         * @note Zip is disabled for now because of inconsistent results,
         * but can be enabled with the filters if necessary.
         *
         * @todo If zip is enabled, retry with spaces stripped when the first
         * request fails. 3012 KG ( street in Rotterdam ) fails, but 3013KG works.
         */
        $address_parts = apply_filters( 'wpsl_osm_address_parts', [
            'address' => 'street',
            'city'    => 'city',
            'state'   => 'state',
            'country' => 'country'
        ] );

        $address = [];

        foreach ( $address_parts as $k => $address_part ) {
            if ( isset( $store_data[$k] ) && $store_data[$k] ) {
                $address[] = $address_parts[$k] . '=' . urlencode( trim( $store_data[$k] ) );
            }
        }

        $geocode_address = implode( '&', $address );

        return $geocode_address;
    }
}