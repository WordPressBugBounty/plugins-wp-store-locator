<?php
/**
 * The approximate location of a visitor, without a permission prompt.
 *
 * Read from the geo headers a CDN or host adds to the request: no third-party
 * request, no database lookup, but city-level at best. The browser's
 * Geolocation API stays the way to get an exact position.
 *
 * @author Tijmen Smit
 * @since  3.1.0
 */

namespace WPSL\Core\Map;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Visitor_Location {

    /**
     * Whether the approximate location can be used at all.
     *
     * Off in 3.1.0: built but not released. While false, the "approximate"
     * trigger isn't accepted anywhere, and a declined or failed prompt goes
     * straight to the start location.
     *
     *     add_action( 'wp_ajax_wpsl_visitor_location',        [ '\WPSL\Core\Map\Visitor_Location', 'handle_ajax_request' ] );
     *     add_action( 'wp_ajax_nopriv_wpsl_visitor_location', [ '\WPSL\Core\Map\Visitor_Location', 'handle_ajax_request' ] );
     *     add_action( 'admin_init',                           [ '\WPSL\Core\Map\Visitor_Location', 'add_privacy_policy_content' ] );
     *
     * @since 3.1.0
     */
    const ENABLED = false;

    /**
     * Check if the approximate location can be used.
     *
     * @since  3.1.0
     * @return bool
     */
    public static function is_enabled() {
        return self::ENABLED;
    }

    /**
     * Handle the pageload auto-locate request for the approximate location.
     *
     * The location never reaches the page — a page cache would serve it to
     * every other visitor.
     *
     * @since  3.1.0
     * @return void
     */
    public static function handle_ajax_request() {
        /*
         * wp_send_json() ends with nocache_headers(), which replaces any
         * header set here. Older WordPress versions leave private and
         * no-store out of it.
         */
        add_filter( 'nocache_headers', function( $headers ) {
            $headers['Cache-Control'] = 'private, no-store, max-age=0';

            return $headers;
        } );

        wp_send_json( self::detect() );

        exit();
    }

    /**
     * Get the approximate location of the current visitor.
     *
     * @since  3.1.0
     * @return array {
     *     @type string     $country    Two letter ISO code, or an empty string.
     *     @type string     $region     Region / state code, or an empty string.
     *     @type string     $city       City name, or an empty string.
     *     @type string     $postalCode Postal code, or an empty string.
     *     @type float|null $lat        Latitude, or null when unknown.
     *     @type float|null $lng        Longitude, or null when unknown.
     *     @type string     $source     Where the location came from, or an empty string.
     * }
     */
    public static function detect() {
        $location = self::get_empty_location();

        /**
         * Filter whether the approximate location of the visitor may be used.
         *
         * A location based on the IP address is personal data. Return false
         * to not look it up, for example until the visitor gave consent.
         *
         * @since 3.1.0
         * @param bool $enabled Whether the location may be used. Default true.
         */
        if ( ! apply_filters( 'wpsl_approximate_location_enabled', true ) ) {
            return $location;
        }

        /*
         * Cloudflare sends the country alone unless the site owner enabled
         * the visitor location headers, so a later source that does know the
         * coordinates is worth more than the first one that answered.
         */
        foreach ( self::get_header_locations() as $header_location ) {
            if ( $header_location['lat'] !== null ) {
                $location = $header_location;

                break;
            }

            if ( $location['source'] === '' ) {
                $location = $header_location;
            }
        }

        /**
         * Filter the approximate location of the visitor.
         *
         * Use it to look up the location on hosts that don't send geo
         * headers, for example in a local IP database.
         *
         * @since 3.1.0
         * @param array $location The country, region, city, postalCode, lat, lng and source.
         */
        $location = apply_filters( 'wpsl_visitor_location', $location );

        return self::normalize( $location );
    }

    /**
     * Describe what is known about the location of the current request.
     *
     * Shown on the settings page: the request of the admin passes the same
     * CDN and host as the requests of the visitors, so it tells whether the
     * approximate location is going to do anything on this site.
     *
     * @since  3.1.0
     * @return array {
     *     @type string $level   none, country ( without coordinates ) or city ( with coordinates ).
     *     @type string $message The description, not escaped.
     * }
     */
    public static function get_status() {
        $location = self::detect();

        if ( $location['lat'] === null ) {
            if ( $location['country'] === '' ) {
                return [
                    'level'   => 'none',
                    'message' => __( 'No location came with this request. A CDN or host has to add it, without it visitors see the start location.', 'wp-store-locator' ),
                ];
            }

            /* translators: 1: two letter country code, 2: the name of the CDN or host */
            $message = sprintf( __( 'Only the country ( %1$s ) came with this request, reported by %2$s. Without coordinates visitors see the start location.', 'wp-store-locator' ), $location['country'], self::get_source_name( $location['source'] ) );

            if ( $location['source'] === 'cloudflare' ) {
                $message .= ' ' . __( 'Enable the "Add visitor location headers" managed transform in Cloudflare to receive them.', 'wp-store-locator' );
            }

            return [
                'level'   => 'country',
                'message' => $message,
            ];
        }

        $place = implode( ', ', array_filter( [ $location['city'], $location['country'] ] ) );

        if ( $place === '' ) {
            $place = $location['lat'] . ',' . $location['lng'];
        }

        return [
            'level'   => 'city',
            /* translators: 1: the detected location, for example "Lisbon, PT", 2: the name of the CDN or host */
            'message' => sprintf( __( 'This request came from %1$s, reported by %2$s. Visitors can be shown the locations near their own city.', 'wp-store-locator' ), $place, self::get_source_name( $location['source'] ) ),
        ];
    }

    /**
     * Get the name to show for a location source.
     *
     * @since  3.1.0
     * @param  string $source The source of the location
     * @return string         The name
     */
    private static function get_source_name( $source ) {
        $names = [
            'cloudflare' => 'Cloudflare',
            'cloudfront' => 'Amazon CloudFront',
            'vercel'     => 'Vercel',
            'akamai'     => 'Akamai',
            'geoip'      => __( 'the web server', 'wp-store-locator' ),
        ];

        if ( isset( $names[ $source ] ) ) {
            return $names[ $source ];
        }

        // A location from the wpsl_visitor_location filter.
        return $source !== '' ? $source : __( 'a custom lookup', 'wp-store-locator' );
    }

    /**
     * Add a suggested text to the privacy policy guide.
     *
     * @since  3.1.0
     * @return void
     */
    public static function add_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }

        $content = '<p class="privacy-policy-tutorial">' . esc_html__( 'This text applies when the store locator uses the approximate location of a visitor: with the "Auto-locate trigger" option set to the approximate location, or after a visitor declined to share their exact location.', 'wp-store-locator' ) . '</p>';

        $content .= '<strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'wp-store-locator' ) . '</strong> ';

        $content .= esc_html__( 'When you visit a page with our store locator, we use the country, region, city, postal code and coordinates that our hosting provider or content delivery network derives from your IP address. We use this approximate location to show you the locations near you, without asking for your exact location. We do not store it.', 'wp-store-locator' );

        wp_add_privacy_policy_content( 'WP Store Locator', wp_kses_post( wpautop( $content, false ) ) );
    }

    /**
     * Collect the locations the request headers describe, in the order the
     * sources are checked. Sources without a usable country are left out.
     *
     * @since  3.1.0
     * @return array[] The locations
     */
    private static function get_header_locations() {
        $locations = [];

        // The $_SERVER keys holding the country, region, city, postal code, latitude and longitude.
        $sources = [
            [ 'cloudflare', 'HTTP_CF_IPCOUNTRY', 'HTTP_CF_REGION_CODE', 'HTTP_CF_IPCITY', 'HTTP_CF_POSTAL_CODE', 'HTTP_CF_IPLATITUDE', 'HTTP_CF_IPLONGITUDE' ],
            [ 'cloudfront', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY_REGION', 'HTTP_CLOUDFRONT_VIEWER_CITY', 'HTTP_CLOUDFRONT_VIEWER_POSTAL_CODE', 'HTTP_CLOUDFRONT_VIEWER_LATITUDE', 'HTTP_CLOUDFRONT_VIEWER_LONGITUDE' ],
            [ 'vercel', 'HTTP_X_VERCEL_IP_COUNTRY', 'HTTP_X_VERCEL_IP_COUNTRY_REGION', 'HTTP_X_VERCEL_IP_CITY', 'HTTP_X_VERCEL_IP_POSTAL_CODE', 'HTTP_X_VERCEL_IP_LATITUDE', 'HTTP_X_VERCEL_IP_LONGITUDE' ],

            // Set by the web server itself ( mod_geoip, the nginx geoip module ), or passed on as headers by the host.
            [ 'geoip', 'GEOIP_COUNTRY_CODE', 'GEOIP_REGION', 'GEOIP_CITY', 'GEOIP_POSTAL_CODE', 'GEOIP_LATITUDE', 'GEOIP_LONGITUDE' ],
            [ 'geoip', 'HTTP_GEOIP_COUNTRY_CODE', 'HTTP_GEOIP_REGION', 'HTTP_GEOIP_CITY', 'HTTP_GEOIP_POSTAL_CODE', 'HTTP_GEOIP_LATITUDE', 'HTTP_GEOIP_LONGITUDE' ],
        ];

        foreach ( $sources as $source ) {
            $locations[] = self::build_location(
                $source[0],
                self::get_server_value( $source[1] ),
                self::get_server_value( $source[2] ),
                self::get_server_value( $source[3] ),
                self::get_server_value( $source[4] ),
                self::get_server_value( $source[5] ),
                self::get_server_value( $source[6] )
            );
        }

        $locations[] = self::get_akamai_location();

        return array_values( array_filter( $locations ) );
    }

    /**
     * Akamai sends everything in a single header:
     * georegion=263,country_code=NP,region_code=BA,city=KATHMANDU,lat=27.70,long=85.32,zip=44600-44601+44605
     *
     * @since  3.1.0
     * @return array|null The location, or null without a usable country
     */
    private static function get_akamai_location() {
        $edgescape = [];

        foreach ( explode( ',', self::get_server_value( 'HTTP_X_AKAMAI_EDGESCAPE' ) ) as $pair ) {
            $pair = explode( '=', $pair, 2 );

            if ( count( $pair ) === 2 ) {
                $edgescape[ trim( $pair[0] ) ] = trim( $pair[1] );
            }
        }

        $value = function( $key ) use ( $edgescape ) {
            return isset( $edgescape[ $key ] ) ? $edgescape[ $key ] : '';
        };

        // The zip is a list of ranges, the first value is as good as any other.
        $postal_code = preg_split( '/[-+]/', $value( 'zip' ) );

        return self::build_location(
            'akamai',
            $value( 'country_code' ),
            $value( 'region_code' ),
            ucwords( strtolower( $value( 'city' ) ) ), // The city is in capitals.
            $postal_code[0],
            $value( 'lat' ),
            $value( 'long' )
        );
    }

    /**
     * Create the location for a single source.
     *
     * @since  3.1.0
     * @param  string $source      The name of the source
     * @param  string $country     Two letter ISO code
     * @param  string $region      Region / state code
     * @param  string $city        City name
     * @param  string $postal_code Postal code
     * @param  string $lat         Latitude
     * @param  string $lng         Longitude
     * @return array|null          The location, or null without a usable country
     */
    private static function build_location( $source, $country, $region, $city, $postal_code, $lat, $lng ) {
        $country = strtoupper( $country );

        /*
         * Besides a country the sources also report an unknown location
         * ( XX, ZZ ), the Tor network ( T1 ), anonymous proxies ( A1 ) and
         * whole continents ( EU, AP ). The coordinates that come with those
         * say nothing about the visitor.
         */
        if ( strlen( $country ) !== 2 || ! ctype_alpha( $country ) || in_array( $country, [ 'XX', 'ZZ', 'EU', 'AP' ], true ) ) {
            return null;
        }

        $point = self::get_point( $lat, $lng );

        return [
            'country'    => $country,
            'region'     => $region,
            'city'       => $city,
            'postalCode' => $postal_code,
            'lat'        => $point ? $point['lat'] : null,
            'lng'        => $point ? $point['lng'] : null,
            'source'     => $source,
        ];
    }

    /**
     * Validate the coordinates.
     *
     * They are rounded to three decimals ( roughly 100 meters ), the data
     * is nowhere near accurate enough for the decimals that follow.
     *
     * @since  3.1.0
     * @param  mixed $lat Latitude
     * @param  mixed $lng Longitude
     * @return array|null The lat and lng as floats, or null if they are unusable
     */
    private static function get_point( $lat, $lng ) {
        if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        // 0,0 is what a failed lookup returns, not a place a visitor is in.
        if ( abs( $lat ) > 90 || abs( $lng ) > 180 || ( $lat === 0.0 && $lng === 0.0 ) ) {
            return null;
        }

        return [
            'lat' => round( $lat, 3 ),
            'lng' => round( $lng, 3 ),
        ];
    }

    /**
     * Make sure the location has the expected structure and values, after
     * it went through the wpsl_visitor_location filter.
     *
     * @since  3.1.0
     * @param  mixed $location The filtered location
     * @return array           The location
     */
    private static function normalize( $location ) {
        $empty = self::get_empty_location();

        if ( ! is_array( $location ) ) {
            return $empty;
        }

        $location = wp_parse_args( $location, $empty );
        $point    = self::get_point( $location['lat'], $location['lng'] );

        return [
            'country'    => strtoupper( sanitize_text_field( $location['country'] ) ),
            'region'     => sanitize_text_field( $location['region'] ),
            'city'       => sanitize_text_field( $location['city'] ),
            'postalCode' => sanitize_text_field( $location['postalCode'] ),
            'lat'        => $point ? $point['lat'] : null,
            'lng'        => $point ? $point['lng'] : null,
            'source'     => sanitize_key( $location['source'] ),
        ];
    }

    /**
     * Read a value the web server, the CDN or the host added to the request.
     *
     * Some sources percent-encode names ( S%C3%A3o%20Paulo ). The value is
     * decoded first: sanitize_text_field() removes percent-encoded
     * characters, which would turn that name into "SoPaulo".
     *
     * @since  3.1.0
     * @param  string $key The $_SERVER key
     * @return string      The value, or an empty string if it doesn't exist
     */
    private static function get_server_value( $key ) {
        if ( ! isset( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
            return '';
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized after decoding.
        return sanitize_text_field( rawurldecode( wp_unslash( $_SERVER[ $key ] ) ) );
    }

    /**
     * The location of a visitor nothing is known about.
     *
     * @since  3.1.0
     * @return array The location
     */
    private static function get_empty_location() {
        return [
            'country'    => '',
            'region'     => '',
            'city'       => '',
            'postalCode' => '',
            'lat'        => null,
            'lng'        => null,
            'source'     => '',
        ];
    }
}