<?php
namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Geolocation policy check
 *
 * A Permissions-Policy header with geolocation=() blocks every location
 * request, which only the admin can fix. The Home page fetches the locator
 * page in the admin's browser ( CDN headers included ) and stores them here.
 *
 * @since 3.1.0
 */
class Geolocation_Policy {

    /**
     * Holds the last check: [ 'blocked', 'header', 'directive', 'url', 'checked' ].
     *
     * @since 3.1.0
     * @var string
     */
    const OPTION = 'wpsl_geolocation_policy';

    /**
     * The troubleshooting article the alert links to.
     *
     * @since 3.1.0
     * @var string
     */
    const DOCS_URL = 'https://wpstorelocator.co/document/geolocation-blocked-permissions-policy/';

    /**
     * The longest header value that is stored.
     *
     * @since 3.1.0
     * @var int
     */
    const MAX_HEADER = 2000;

    /**
     * The page to check: the first published page with the store locator,
     * otherwise the front page.
     *
     * Only [wpsl] and its block: [wpsl_map] and the single-store shortcodes
     * never ask for the visitor's location.
     *
     * @since  3.1.0
     * @return string
     */
    public static function get_check_url() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No core API answers "which page uses this shortcode"; runs once per Home page load.
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ( 'page', 'post' ) AND ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) ORDER BY post_type = 'page' DESC, ID ASC LIMIT 1",
            '%' . $wpdb->esc_like( '[wpsl]' ) . '%',
            '%' . $wpdb->esc_like( '[wpsl ' ) . '%',
            '%' . $wpdb->esc_like( 'wp:wpsl/store-locator' ) . '%'
        ) );

        $url = $id ? get_permalink( (int) $id ) : '';

        return $url ? $url : home_url( '/' );
    }

    /**
     * Whether the headers of a page stop it from using geolocation.
     *
     * Permissions-Policy wins over the older Feature-Policy when both
     * name geolocation. One that doesn't mention it leaves the default
     * in place, which allows the page itself.
     *
     * @since  3.1.0
     * @param  string $permissions_policy The Permissions-Policy header, or an empty string
     * @param  string $feature_policy     The Feature-Policy header, or an empty string
     * @param  string $url                The URL the headers came from
     * @return array [ 'blocked' => bool, 'header' => string, 'directive' => string ]
     */
    public static function evaluate( $permissions_policy, $feature_policy, $url ) {
        $origin = self::origin( $url );

        /*
         * Multiple header lines arrive joined by commas; the last
         * geolocation entry counts. Case-sensitive, like the browser's parser.
         */
        if ( preg_match_all( '/(?:^|,)\s*geolocation\s*=\s*(\([^)]*\)|[^,;\s]*)/', (string) $permissions_policy, $matches ) ) {
            $allowlist = trim( end( $matches[1] ) );
            $items     = preg_split( '/\s+/', trim( $allowlist, '() ' ), -1, PREG_SPLIT_NO_EMPTY );
            $allowed   = false;

            foreach ( $items as $item ) {
                if ( '*' === $item || 'self' === $item || self::origin( trim( $item, '"' ) ) === $origin ) {
                    $allowed = true;
                    break;
                }
            }

            return [
                'blocked'   => ! $allowed,
                'header'    => 'Permissions-Policy',
                'directive' => 'geolocation=' . $allowlist,
            ];
        }

        if ( preg_match_all( '/(?:^|[;,])\s*geolocation(?=[\s;,]|$)([^;,]*)/i', (string) $feature_policy, $matches ) ) {
            $allowlist = trim( end( $matches[1] ) );
            $items     = preg_split( '/\s+/', $allowlist, -1, PREG_SPLIT_NO_EMPTY );
            $allowed   = false;

            foreach ( $items as $item ) {
                if ( '*' === $item || "'self'" === strtolower( $item ) || self::origin( $item ) === $origin ) {
                    $allowed = true;
                    break;
                }
            }

            return [
                'blocked'   => ! $allowed,
                'header'    => 'Feature-Policy',
                'directive' => trim( 'geolocation ' . $allowlist ),
            ];
        }

        return [
            'blocked'   => false,
            'header'    => '',
            'directive' => '',
        ];
    }

    /**
     * Store the result of a check.
     *
     * @since  3.1.0
     * @param  string $permissions_policy The Permissions-Policy header
     * @param  string $feature_policy     The Feature-Policy header
     * @param  string $url                The URL that was fetched
     * @return array The stored result
     */
    public static function save( $permissions_policy, $feature_policy, $url ) {
        $result = self::evaluate(
            substr( (string) $permissions_policy, 0, self::MAX_HEADER ),
            substr( (string) $feature_policy, 0, self::MAX_HEADER ),
            $url
        );

        $result['url']     = esc_url_raw( $url );
        $result['checked'] = time();

        update_option( self::OPTION, $result, false );

        return $result;
    }

    /**
     * Forget the last result, when the page could not be fetched: an alert
     * that can't be confirmed again shouldn't stay.
     *
     * @since  3.1.0
     * @return void
     */
    public static function clear() {
        delete_option( self::OPTION );
    }

    /**
     * The last stored result.
     *
     * @since  3.1.0
     * @return array The result, or an empty array when there is none
     */
    public static function get_result() {
        $result = get_option( self::OPTION, [] );

        return is_array( $result ) ? $result : [];
    }

    /**
     * Whether auto-locate is on, the only setting that asks for the
     * visitor's location ( the "Use my current location" button depends on
     * it too ).
     *
     * @since  3.1.0
     * @return bool
     */
    public static function is_needed() {
        return (bool) wpsl_get_service( 'wpsl_settings' )->get( 'search', 'auto_locate' );
    }

    /**
     * Scheme, host and port of a URL, lowercased, without a default port.
     *
     * @since  3.1.0
     * @param  string $url
     * @return string The origin, or an empty string for anything that isn't an http(s) URL
     */
    private static function origin( $url ) {
        $parts = wp_parse_url( (string) $url );

        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }

        $scheme = strtolower( $parts['scheme'] );

        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return '';
        }

        $port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;

        if ( ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ) {
            $port = 0;
        }

        return $scheme . '://' . strtolower( $parts['host'] ) . ( $port ? ':' . $port : '' );
    }
}