<?php
/**
 * Frontend service loader
 *
 * Decides whether a request needs the frontend service chain resolved
 * up-front: the shortcode handler and the search service, which pull in the
 * template loader, assets, map markers/manager, templates manager, store data
 * and hours service.
 *
 * Mirrors \WPSL\Admin\Core\Service_Loader; registrations stay unconditional
 * ( so all remain resolvable through wpsl_get_service() ), only the
 * constructors are gated.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Service_Loader {

    /**
     * admin-ajax / admin-post actions that need the chain resolved up-front.
     *
     * The plugin's own public endpoints are deliberately absent, each already
     * reaches for what it needs through the container:
     *
     * - store_search      Search::handle_ajax_search() resolves 'search'.
     * - osm_directions    Controller methods, they only read the api settings.
     * - stadia_directions idem.
     * - nominatim_search  owned by Nominatim_Geocode_Cache, which is resolved
     *                     unconditionally from init_services().
     *
     * Listed instead is the classic editor's shortcode preview, which runs
     * do_shortcode() over post content and so needs [wpsl] registered.
     *
     * @since 3.0.0
     * @var   array
     */
    public const BOOT_ACTIONS = [
        'parse-media-shortcode',
    ];

    /**
     * Cached needs_frontend_assets() answer.
     *
     * @since 3.0.0
     * @var   bool|null
     */
    private $needs_assets = null;

    /**
     * Whether the frontend services should be resolved on this request.
     *
     * Every non-admin request ( the front end, but also REST and cron, where
     * is_admin() is false ) resolves them exactly as before.
     *
     * @since  3.0.0
     * @return bool
     */
    public function needs_frontend_services() {
        if ( ! is_admin() ) {
            return true;
        }

        $action = $this->current_action();
        $needed = in_array( $action, self::BOOT_ACTIONS, true );

        /**
         * Filter whether the frontend services are resolved up-front.
         *
         * Lets an add-on that renders locator markup on an admin screen pull
         * the chain in, the way it loaded on every admin request before 3.0.
         *
         * @since 3.0.0
         * @param bool   $needed Whether the services are resolved up-front
         * @param string $action The current admin-ajax / admin-post action
         */
        return (bool) apply_filters( 'wpsl_load_frontend_services', $needed, $action );
    }

    /**
     * Whether this request renders locator markup, and so needs the
     * front-end stylesheets and the services behind them.
     *
     * Reads the queried post, so it is only meaningful from 'wp' onwards.
     * Cached because the style callback and the render path both ask.
     *
     * @since  3.0.0
     * @return bool
     */
    public function needs_frontend_assets() {
        if ( null === $this->needs_assets ) {
            $this->needs_assets = $this->detect_frontend_assets();
        }

        return $this->needs_assets;
    }

    /**
     * Work out whether the current request renders locator markup.
     *
     * Only singular requests can be judged from post content. Everything else
     * ( archives, home, 404, feeds ) falls to Assets\Manager::ensure_styles(),
     * which still enqueues the styles when a shortcode does run - just late
     * enough to print in the footer.
     *
     * @since  3.0.0
     * @return bool
     */
    private function detect_frontend_assets() {
        $needed = false;

        if ( ! is_admin() && is_singular() ) {
            if ( is_singular( 'wpsl_stores' ) ) {
                $needed = true;
            } else {
                $post = get_post();

                if ( $post && ! empty( $post->post_content ) ) {
                    foreach ( [ 'wpsl', 'wpsl_map', 'wpsl_address', 'wpsl_hours' ] as $tag ) {
                        if ( has_shortcode( $post->post_content, $tag ) ) {
                            $needed = true;
                            break;
                        }
                    }
                }
            }
        }

        /**
         * Filter whether this request needs the front-end assets.
         *
         * The detector only sees shortcodes in the queried post's content, so
         * a theme template, page builder or widget that renders a locator has
         * to say so here ( or call wpsl_enqueue_frontend_assets() ).
         *
         * @since 3.0.0
         * @param bool   $needed Whether the assets are needed
         * @param object $object The queried object, or null
         */
        return (bool) apply_filters( 'wpsl_needs_frontend_assets', $needed, get_queried_object() );
    }

    /**
     * The action this request carries, if any.
     *
     * @since  3.0.0
     * @return string The action name, or an empty string
     */
    private function current_action() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- routing only, the handlers verify nonces
        return isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
    }
}