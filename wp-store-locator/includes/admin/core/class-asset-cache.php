<?php
namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Outdated cached scripts check
 *
 * A CDN that ignores the ?ver= query string ( CloudFront's default cache
 * policy ) keeps serving the previous wpsl.min.js after an update, and the
 * store locator breaks until its cache expires. Only the admin can fix that.
 *
 * The check runs in the admin's browser, see wpsl-asset-cache-check.js, so
 * the requests take the same route through the CDN as a visitor's. A request
 * from PHP to the site's own URL can skip the CDN entirely. Two requests:
 *
 * - wpsl.min.js?ver=<version>, the URL visitors load. Without the installed
 *   build id in it, the CDN serves outdated scripts right now.
 * - wpsl.min.js?ver=<random>, a URL nobody requested before. Answered from a
 *   cache, the CDN ignores the version, and the next update breaks again.
 *
 * The Home page runs it when it is due, the Tools section on request. Both
 * report here, and the result becomes an alert, see Alerts.
 *
 * @since 3.1.1
 */
class Asset_Cache {

    /**
     * Holds the last check: [ 'build', 'stale', 'ignores_version', 'header', 'checked' ].
     *
     * @since 3.1.1
     * @var string
     */
    const OPTION = 'wpsl_asset_cache_check';

    /**
     * The AJAX action the check reports to, and its nonce.
     *
     * @since 3.1.1
     * @var string
     */
    const ACTION = 'wpsl_asset_cache_check';
    const NONCE  = 'wpsl-asset-cache-check';

    /**
     * The troubleshooting article the alerts link to.
     *
     * @since 3.1.1
     * @var string
     */
    const DOCS_URL = 'https://wpstorelocator.co/document/clear-cdn-cache-after-update/';

    /**
     * How long a result without a problem stands before the Home page checks again.
     *
     * @since 3.1.1
     * @var int
     */
    const RECHECK = DAY_IN_SECONDS;

    /**
     * The longest cache header value that is stored.
     *
     * @since 3.1.1
     * @var int
     */
    const MAX_HEADER = 200;

    /**
     * What the script needs to run the check.
     *
     * Empty when there is nothing to check: without a build id ( a build
     * from before 3.1.1 ), or with SCRIPT_DEBUG, when visitors load the
     * files in assets/src and not the bundle.
     *
     * The script makes the never requested URL itself, a new one on every
     * run: a second run with the same URL would find the copy the first one
     * left in the CDN.
     *
     * @since  3.1.1
     * @return array [ 'build', 'url' ], or an empty array
     */
    public static function get_check_config() {
        $build = wpsl_get_build_id();

        if ( ! $build || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
            return [];
        }

        return [
            'build' => $build,
            'url'   => add_query_arg( 'ver', rawurlencode( WPSL_VERSION_NUM ), WPSL_URL . 'assets/dist/frontend/js/wpsl.min.js' ),
        ];
    }

    /**
     * Whether the Home page should run the check.
     *
     * After an update ( the stored result is for another build ), while a
     * problem is reported that hasn't been dismissed, so the alert goes once
     * it is fixed, and otherwise once a day.
     *
     * @since  3.1.1
     * @return bool
     */
    public static function is_due() {
        // A result for another build is dropped by get_result(), so an update makes it due.
        $result = self::get_result();

        if ( ! $result ) {
            return true;
        }

        if ( ! empty( $result['stale'] ) ) {
            return true;
        }

        // Once the warning is dismissed, the daily check is enough.
        $alerts    = get_option( 'wpsl_alerts', [] );
        $dismissed = is_array( $alerts ) && ! empty( $alerts['cdn_version']['dismissed'] );

        if ( ! empty( $result['ignores_version'] ) && ! $dismissed ) {
            return true;
        }

        return empty( $result['checked'] ) || ( time() - (int) $result['checked'] ) > self::RECHECK;
    }

    /**
     * Store the result of a check.
     *
     * @since  3.1.1
     * @param  bool   $stale           Whether wpsl.min.js?ver=<version> lacked the installed build id
     * @param  bool   $ignores_version Whether the never requested URL came from a cache
     * @param  string $header          The response header that showed the cache hit
     * @return array  The stored result
     */
    public static function save( $stale, $ignores_version, $header ) {
        $result = [
            'build'           => wpsl_get_build_id(),
            'stale'           => (bool) $stale,
            'ignores_version' => (bool) $ignores_version,
            'header'          => substr( sanitize_text_field( (string) $header ), 0, self::MAX_HEADER ),
            'checked'         => time(),
        ];

        update_option( self::OPTION, $result, false );

        return $result;
    }

    /**
     * Forget the last result, when the script could not be fetched: an
     * alert that can't be confirmed again shouldn't stay.
     *
     * @since  3.1.1
     * @return void
     */
    public static function clear() {
        delete_option( self::OPTION );
    }

    /**
     * The last stored result, only when it is about the installed build.
     *
     * @since  3.1.1
     * @return array The result, or an empty array when there is none
     */
    public static function get_result() {
        $result = get_option( self::OPTION, [] );

        if ( ! is_array( $result ) || empty( $result['build'] ) || $result['build'] !== wpsl_get_build_id() ) {
            return [];
        }

        return $result;
    }

    /**
     * Store what the browser found, and answer with the alerts to show.
     *
     * The Home page puts the alerts' markup in its Alerts box, the Tools
     * section shows the message.
     *
     * @since  3.1.1
     * @return void
     */
    public static function ajax_save() {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) ] );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer() above.
        if ( ! empty( $_POST['failed'] ) ) {
            self::clear();

            $result = [];
        } else {
            $result = self::save(
                ! empty( $_POST['stale'] ),
                ! empty( $_POST['ignores_version'] ),
                isset( $_POST['header'] ) ? sanitize_text_field( wp_unslash( $_POST['header'] ) ) : ''
            );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $alerts = wpsl_container()->has( 'plugin_alerts' ) ? wpsl_get_service( 'plugin_alerts' ) : null;
        $html   = '';

        if ( $alerts ) {
            $wpsl_alert_items = array_merge( $alerts->get_stale_scripts_alert(), $alerts->get_cdn_version_alert() );

            if ( $wpsl_alert_items ) {
                ob_start();
                require WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/partials/alerts-list.php';
                $html = ob_get_clean();
            }
        }

        $active = $alerts ? $alerts->get_active_alerts() : [];

        wp_send_json_success( array_merge( self::get_message( $result ), [
            'keys'   => [ Alerts::STALE_SCRIPTS_ALERT, Alerts::CDN_VERSION_ALERT ],
            'html'   => $html,
            'alerts' => [
                'keys'  => array_map( 'strval', array_keys( $active ) ),
                'count' => count( $active ),
            ],
        ] ) );
    }

    /**
     * The message the Tools section shows for a result.
     *
     * @since  3.1.1
     * @param  array $result A stored result, or an empty array when the check failed
     * @return array [ 'type' => 'success' | 'warning' | 'error', 'message' => string ]
     */
    private static function get_message( array $result ) {
        if ( ! $result ) {
            return [
                'type'    => 'error',
                'message' => esc_html__( 'The check could not load the store locator script through the address of your site, so it could not tell whether a CDN serves outdated files. If a CDN plugin moves the scripts to another domain, use the check in the documentation instead.', 'wp-store-locator' ),
            ];
        }

        if ( $result['stale'] ) {
            return [
                'type'    => 'error',
                'message' => esc_html__( 'Your CDN or cache serves an outdated version of the store locator scripts. Purge it, see the alert on the Home page for the steps.', 'wp-store-locator' ),
            ];
        }

        if ( $result['ignores_version'] ) {
            return [
                'type'    => 'warning',
                'message' => esc_html__( 'The scripts are up to date, but your CDN ignores the version in script URLs. The next update will serve outdated scripts until the CDN cache expires, see the alert on the Home page.', 'wp-store-locator' ),
            ];
        }

        return [
            'type'    => 'success',
            'message' => esc_html__( 'No outdated cached files found. Visitors get the current store locator scripts, and the CDN treats the version in script URLs as part of the URL.', 'wp-store-locator' ),
        ];
    }
}
