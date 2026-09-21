<?php
/**
 * Frontend controller
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */
namespace WPSL\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Templates\Filters;
use WPSL\Core\UI\Theme_Styles;
use WPSL\Frontend\Core\Service_Loader;

class Controller {

    /**
     * Shortcode attributes for the [wpsl] shortcode
     *
     * @since 2.0.0
     * @var array
     */
    private $shortcode_atts;

    /**
     * Store data for map rendering
     *
     * @since 3.0.0
     * @var array
     */
    private $store_map_data = [];
    
    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;
    
    /**
     * Translations service
     * 
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * Container object
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Template filters service
     * 
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Filters
     */
    private $template_filters;

    /**
     * Theme styles service
     * 
     * @since 3.0.0
     * @var \WPSL\Core\UI\Theme_Styles
     */
    private $theme_styles;

    /**
     * Assets manager service
     * 
     * @since 3.0.0
     * @var \WPSL\Core\Assets\Manager
     */
    private $assets_manager;

    /**
     * Whether this request resolves the frontend services up-front.
     *
     * Null until the Service_Loader has been asked.
     *
     * @since 3.0.0
     * @var   bool|null
     */
    private $needs_services = null;

    /**
     * Cached front-end service loader.
     *
     * @since 3.0.0
     * @var   \WPSL\Frontend\Core\Service_Loader|null
     */
    private $service_loader = null;

    /**
     * Whether the GDPR artwork rule for the per-map overlays was rendered.
     *
     * @since 3.0.0
     * @var   bool
     */
    private $map_gdpr_artwork_added = false;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container          $container        Service container instance
     * @param \WPSL\Core\Settings\Manager   $settings         Settings manager instance
     * @param \WPSL\Core\I18n\Translations  $i18n             Translations instance
     * @param \WPSL\Core\Templates\Filters  $template_filters Template filters instance
     * @param \WPSL\Core\UI\Theme_Styles    $theme_styles     Theme styles instance
     */
    public function __construct( \WPSL\Core\Container $container, WpslSettings $settings, Translations $i18n, Filters $template_filters, Theme_Styles $theme_styles ) {
        $this->container = $container;
        $this->settings = $settings;
        $this->i18n = $i18n;
        $this->template_filters = $template_filters;
        $this->theme_styles = $theme_styles;
            
        add_action( 'init',                          [ $this, 'init_shortcodes' ] );
        add_action( 'init',                          [ $this, 'maybe_load_borlabs' ] );
        add_action( 'init',                          [ $this, 'maybe_load_weglot' ] );
        add_action( 'wp_ajax_osm_directions',        [ $this, 'osm_directions' ] );
        add_action( 'wp_ajax_nopriv_osm_directions', [ $this, 'osm_directions' ] );
        add_action( 'wp_ajax_stadia_directions',     [ $this, 'stadia_directions' ] );
        add_action( 'wp_ajax_nopriv_stadia_directions', [ $this, 'stadia_directions' ] );
        add_action( 'wp',                            [ $this, 'detect_shortcode_attributes' ], 5 );
        add_action( 'wp_enqueue_scripts',            [ $this, 'add_frontend_styles' ] );
        add_action( 'wp_enqueue_scripts',            [ $this, 'maybe_deregister_other_gmaps' ], 100 );
        add_action( 'wp_footer',                     [ $this, 'add_frontend_scripts' ] );
    }

    /**
     * Check if Borlabs Cookie is active and load the integration if needed.
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_load_borlabs() {
        if ( function_exists( 'BorlabsCookieHelper' ) ) {
            require_once( WPSL_PLUGIN_DIR . 'includes/core/integrations/class-borlabs-cookie.php' );

            $borlabs = new \WPSL\Core\Integrations\Borlabs_Cookie();
            $borlabs->maybe_enable_bct();
        }
    }

    /**
     * Check if Weglot is active and load the integration if needed.
     *
     * Runs on admin-ajax requests too: the store search response is JSON
     * that Weglot buffers, and the integration's weglot_add_json_keys
     * filter has to be registered before Weglot translates it.
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_load_weglot() {
        if ( defined( 'WEGLOT_VERSION' ) ) {
            require_once( WPSL_PLUGIN_DIR . 'includes/core/integrations/class-weglot.php' );

            new \WPSL\Core\Integrations\Weglot( $this->container );
        }
    }

    /**
     * Whether this request resolves the frontend services up-front.
     *
     * Cached: both initializers ask, and the answer runs a filter.
     *
     * @since  3.0.0
     * @return bool
     */
    private function needs_services() {
        if ( null === $this->needs_services ) {
            $this->needs_services = ! is_admin() ? true : ( new Service_Loader() )->needs_frontend_services();
        }

        return $this->needs_services;
    }

    /**
     * The front-end service loader, built at most once per request.
     *
     * @since  3.0.0
     * @return \WPSL\Frontend\Core\Service_Loader
     */
    private function service_loader() {
        if ( null === $this->service_loader ) {
            $this->service_loader = new Service_Loader();
        }

        return $this->service_loader;
    }

    /**
     * Initialize shortcodes.
     *
     * Skipped on admin requests that render no locator markup. Nothing there
     * reads the service without resolving it itself, and [wpsl] has no work
     * to do on a screen it cannot appear on.
     *
     * @since 3.0.0
     * @return void
     */
    public function init_shortcodes() {
        if ( ! $this->needs_services() ) {
            return;
        }

        $this->container->get( 'shortcodes' );

        /*
         * [wpsl_hours] is registered by the hours service constructor, and on a
         * page that holds only that shortcode nothing else resolves it - it
         * arrives as a store_data dependency, which only the assets manager
         * builds, and only for the shortcodes the asset detector recognizes.
         * Without this the tag stays unregistered and renders as plain text.
         */
        $this->container->get( 'hours' );

        /*
         * Resolved on init because the manager's constructor registers the
         * 'the_content' filter that appends [wpsl_map] to a store page. Block
         * themes fire that filter from get_the_block_template_html(), which
         * template-canvas.php calls before wp_head(), so waiting for the
         * wp_enqueue_scripts resolve would register it too late to run.
         */
        $this->container->get( 'templates_manager' );
    }

    /**
     * Make the request to the OpenStreet directions API.
     *
     * @since  3.0.0
     * @see https://github.com/GIScience/openrouteservice/blob/main/docs/api-reference/error-codes.md for a full list of possible error codes.
     * @return void
     */
    public function osm_directions() {
        $wpsl_settings = $this->settings->get_group( 'api' );

        if ( ! $wpsl_settings['openrouteservice_key'] || ! get_option( 'wpsl_valid_openrouteservice_key' ) ) {
            wp_send_json_error(  esc_html__( 'You need to provide an valid API key for the Openrouteservice on the WPSL settings page before the directions can be rendered on the map itself.', 'wp-store-locator' ), 401 );
        }

        /**
         * This endpoint is public and can't use a nonce ( cached pages would
         * serve expired nonces and break the directions ). Rate limit it per
         * IP instead so a single visitor can't drain the site's billed
         * Openrouteservice request quota.
         */
        if ( \WPSL\Core\Utils\Rate_Limiter::is_limited( 'directions', apply_filters( 'wpsl_directions_rate_limit', 30 ) ) ) {
            wp_send_json_error( esc_html__( 'Too many direction requests. Please wait a moment and try again.', 'wp-store-locator' ), 429 );
        }

        $routes = '';
        $start  = isset( $_GET['start'] ) ? wpsl_sanitize_coords( sanitize_text_field( wp_unslash( $_GET['start'] ) ) ) : '';
        $end    = isset( $_GET['end'] ) ? wpsl_sanitize_coords( sanitize_text_field( wp_unslash( $_GET['end'] ) ) ) : '';

        if ( $start && $end ) {
            $response = wpsl_call_openrouteservice_api( [
                'start' => $start,
                'end'   => $end
            ] );

            if ( is_wp_error( $response ) ) {
                $routes = wpsl_format_wp_error_status( $response );

                wp_send_json_error( $routes, 400 );
                exit();
            } else if ( isset( $response['response'] ) ) {
                $routes = json_decode( $response['body'], true );

                if ( $response['response']['code'] !== 200 ) {

                    // The error body shape varies ( missing, a string, or an
                    // array ), so read it defensively to avoid PHP warnings /
                    // illegal-offset errors on an unexpected response.
                    $error_data = isset( $routes['error'] ) ? $routes['error'] : '';

                    if ( $response['response']['code'] == 404 ) {
                        $error = ''; // Use wpslLabels.xxxx to grab the correct error text in the JS code.
                    } else if ( is_string( $error_data ) && $error_data !== '' && $response['response']['code'] == 403 ) { // Not allowed
                        $error = $error_data . '. ' . esc_html__( 'Please make sure to provide a valid Openrouteservice API key on the WPSL settings page.', 'wp-store-locator' );
                    } else if ( $response['response']['code'] == 400 && is_array( $error_data ) && isset( $error_data['code'] ) && $error_data['code'] == 2004 ) {
                        $error = esc_html__( 'The requested route distance exceeds the maximum allowed by Openrouteservice.', 'wp-store-locator' );
                    } else if ( is_array( $error_data ) && isset( $error_data['message'] ) ) {
                        $error = $error_data['message'];
                    } else {
                        $error = esc_html__( 'The Openrouteservice API didn\'t return valid data, please try again later.', 'wp-store-locator' );
                    }

                    wp_send_json_error( $error, $response['response']['code'] );
                    exit();
                }
            }
        }

        wp_send_json( $routes );
        exit();
    }

    /**
     * Handle the AJAX request for Stadia Maps directions.
     *
     * Uses the Stadia Maps Routing API (Valhalla) to calculate
     * the route between the start and end coordinates.
     *
     * @since  3.0.0
     * @see    https://docs.stadiamaps.com/routing/standard-routing/
     * @return void
     */
    public function stadia_directions() {
        $wpsl_settings = $this->settings->get_group( 'api' );

        if ( ! $wpsl_settings['stadia_key'] || ! get_option( 'wpsl_valid_stadia_key' ) ) {
            wp_send_json_error( esc_html__( 'You need to provide a valid API key for Stadia Maps on the WPSL settings page before the directions can be rendered on the map itself.', 'wp-store-locator' ), 401 );
        }

        /**
         * This endpoint is public and can't use a nonce ( cached pages would
         * serve expired nonces and break the directions ). Rate limit it per
         * IP instead so a single visitor can't drain the site's billed
         * Stadia Maps request quota.
         */
        if ( \WPSL\Core\Utils\Rate_Limiter::is_limited( 'directions', apply_filters( 'wpsl_directions_rate_limit', 30 ) ) ) {
            wp_send_json_error( esc_html__( 'Too many direction requests. Please wait a moment and try again.', 'wp-store-locator' ), 429 );
        }

        $routes = '';
        $start  = isset( $_GET['start'] ) ? wpsl_sanitize_coords( sanitize_text_field( wp_unslash( $_GET['start'] ) ) ) : '';
        $end    = isset( $_GET['end'] ) ? wpsl_sanitize_coords( sanitize_text_field( wp_unslash( $_GET['end'] ) ) ) : '';

        if ( $start && $end ) {
            $response = wpsl_call_stadia_routing_api( [
                'start' => $start,
                'end'   => $end
            ] );

            if ( is_wp_error( $response ) ) {
                $routes = wpsl_format_wp_error_status( $response );

                wp_send_json_error( $routes, 400 );
                exit();
            } else if ( isset( $response['response'] ) ) {
                $routes = json_decode( $response['body'], true );

                if ( $response['response']['code'] !== 200 ) {
                    $error = esc_html__( 'The Stadia Maps Routing API didn\'t return valid data, please try again later.', 'wp-store-locator' );

                    if ( isset( $routes['error'] ) && is_string( $routes['error'] ) ) {
                        $error = $routes['error'];
                    } else if ( isset( $routes['error']['message'] ) ) {
                        $error = $routes['error']['message'];
                    }

                    wp_send_json_error( $error, $response['response']['code'] );
                    exit();
                }
            }
        }

        wp_send_json( $routes );
        exit();
    }

    /**
     * Create the category filter.
     *
     * @since 2.0.0
     * @return string|void $category The HTML for the category dropdown, or nothing if no terms exist.
     */
    public function create_category_filter() {
        return $this->template_filters->category_list();
    }

    /**
     * Create a dropdown list holding the
     * search radius or max search results options.
     *
     * @since  1.0.0
     * @param  string $list_type     The name of the list we need to load data for
     * @return string $dropdown_list A list with the available options for the dropdown list
     */
    public function get_dropdown_list( $list_type ) {
        return $this->template_filters->dropdown_list( $list_type );
    }

    /**
     * Load the required css styles.
     *
     * @since 2.0.0
     * @return void
     */
    public function add_frontend_styles() {
        if ( ! $this->service_loader()->needs_frontend_assets() ) {
            return;
        }

        $this->container->get( 'assets_manager' )->ensure_styles();
    }

    /**
     * Remove the Google Maps scripts loaded by other plugins or the theme.
     *
     * Runs late on wp_enqueue_scripts: late enough to see everything other
     * plugins registered, early enough to still be before the header scripts
     * are printed. The call in the assets manager runs on wp_footer, by which
     * point anything another plugin put in the header has already loaded, so
     * that one alone leaves the conflict in place.
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_deregister_other_gmaps() {
        if ( ! $this->service_loader()->needs_frontend_assets() ) {
            return;
        }

        if ( wpsl_get_active_map_service() !== 'gmaps' ) {
            return;
        }

        if ( ! wpsl_get_service( 'wpsl_settings' )->get( 'tools', 'deregister_gmaps' ) ) {
            return;
        }

        wpsl_deregister_other_gmaps();
    }

    /**
     * Load the required JS scripts.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_frontend_scripts() {
        if ( ! wpsl_get_service( 'frontend_state' )->get_load_scripts() ) {
            return;
        }

        $this->container->get( 'assets_manager' )->enqueue_scripts();
    }

    /**
     * Get the used travel direction mode.
     *
     * @since  2.2.8
     * @param  string $map_provider Either OSM or Gmaps
     * @return string $travel_mode  The used travel mode for the travel direcions
     * @deprecated 3.0.0 Use wpsl_get_directions_travel_mode() instead
     */
    public function get_directions_travel_mode( $map_provider = '' ) {
        return wpsl_get_directions_travel_mode( $map_provider );
    }

    /**
     * Check if GDPR checkpoint should be shown and return the HTML markup.
     *
     * @since  3.0.0
     * @return string The HTML markup for the GDPR checkpoint.
     */
    public function maybe_show_gdpr_checkpoint() {
        $gdpr_text = $this->get_gdpr_checkpoint_text();

        if ( ! $gdpr_text ) {
            return '';
        }

        $checkpoint = '<div id="wpsl-gdpr-checkpoint">';
        $checkpoint .= '<div class="wpsl-gdpr-content">';
        $checkpoint .= '<div class="wpsl-gdpr-text">';
        $checkpoint .= '<p>' . $gdpr_text . '</p>';
        $checkpoint .= '<div class="wpsl-gdpr-actions"><button id="wpsl-gdpr-approved">' . __( 'Load Map', 'wp-store-locator' ) . '</button><input type="checkbox" id="wpsl-gdpr-permanent-approved" name="wpsl-gdpr-permanent-approved"><label for="wpsl-gdpr-permanent-approved">' . __( 'Don\'t ask again', 'wp-store-locator' ) . '</label></div>';
        $checkpoint .= '</div>';
        $checkpoint .= '</div>';
        $checkpoint .= '</div>';
        $checkpoint .= $this->get_gdpr_fast_path_script( 'wpsl-wrap' );

        return $checkpoint;
    }

    /**
     * Check if the GDPR checkpoint should be shown for a basic map and
     * return the per-map overlay markup.
     *
     * The [wpsl_map] shortcode can exist multiple times on one page, so
     * unlike the locator checkpoint the overlay and its controls are
     * class-based, and the "don't ask again" checkbox id is derived from
     * the map container id. The overlay is rendered inside the container,
     * which the shortcode flags with the wpsl-gdpr-checkpoint class.
     *
     * @since  3.0.0
     * @param  string $map_id The basic map container id ( wpsl-base-{service}_{n} ).
     * @return string The HTML markup for the map's GDPR checkpoint.
     */
    public function maybe_show_map_gdpr_checkpoint( $map_id ) {
        $gdpr_text = $this->get_gdpr_checkpoint_text();

        if ( ! $gdpr_text ) {
            return '';
        }

        $checkbox_id = esc_attr( $map_id . '-gdpr-permanent' );

        $checkpoint = $this->get_map_gdpr_artwork_css();
        $checkpoint .= '<div class="wpsl-gdpr-checkpoint-overlay">';
        $checkpoint .= '<div class="wpsl-gdpr-content">';
        $checkpoint .= '<div class="wpsl-gdpr-text">';
        $checkpoint .= '<p>' . $gdpr_text . '</p>';
        $checkpoint .= '<div class="wpsl-gdpr-actions"><button type="button" class="wpsl-gdpr-approve">' . __( 'Load Map', 'wp-store-locator' ) . '</button><input type="checkbox" class="wpsl-gdpr-permanent" id="' . $checkbox_id . '" name="' . $checkbox_id . '"><label for="' . $checkbox_id . '">' . __( 'Don\'t ask again', 'wp-store-locator' ) . '</label></div>';
        $checkpoint .= '</div>';
        $checkpoint .= '</div>';
        $checkpoint .= '</div>';
        $checkpoint .= $this->get_gdpr_fast_path_script( $map_id );

        return $checkpoint;
    }

    /**
     * The GDPR artwork rule for the per-map overlays.
     *
     * The locator checkpoint gets this rule from get_custom_css(), which only
     * the [wpsl] templates render, so a [wpsl_map]-only page has to bring it
     * along itself. Emitted with the first overlay on the page, not per map.
     *
     * @since  3.0.0
     * @return string The style tag, or an empty string after the first call.
     */
    private function get_map_gdpr_artwork_css() {
        if ( $this->map_gdpr_artwork_added ) {
            return '';
        }

        $this->map_gdpr_artwork_added = true;

        $map_service     = wpsl_get_active_map_service();
        $gdpr_background = apply_filters( 'wpsl_gdpr_background', WPSL_URL . 'assets/img/frontend/gdpr/' . esc_attr( $map_service ) . '.gif' );

        return '<style>.wpsl-gdpr-checkpoint-overlay .wpsl-gdpr-content { background: url( \'' . esc_url( $gdpr_background ) . '\' ) center top no-repeat; background-size: cover; }</style>' . "\r\n";
    }

    /**
     * Build the consent text shared by the locator and per-map checkpoints.
     *
     * @since  3.0.0
     * @return string The kses'd consent text, or an empty string when no checkpoint is due.
     */
    private function get_gdpr_checkpoint_text() {
        if ( wpsl_get_gdpr_handler() != 'wpsl' ) {
            return '';
        }

        $wpsl_settings = $this->settings->get_all();
        $map_service   = wpsl_get_active_map_service();

        $privacy_url = $wpsl_settings['gdpr'][ $map_service ]['privacy_policy_url'];
        $description = $wpsl_settings['gdpr']['description'];

        if ( ! $description || ! $privacy_url ) {
            return '';
        }

        $gdpr_text = wpsl_convert_bbcode_to_string( $description, $privacy_url );
        $gdpr_text = nl2br( $gdpr_text, false );

        return wp_kses( $gdpr_text, [
            'a' => [
                'href'   => [],
                'target' => [],
                'rel'    => []
            ],
            'br' => []
        ] );
    }

    /**
     * Inline script that hides a checkpoint before first paint when the map
     * load was approved on an earlier visit.
     *
     * PHP cannot see the approval - it lives in localStorage - so every
     * element that ships with the wpsl-gdpr-checkpoint class needs this to
     * avoid flashing the consent overlay at returning visitors.
     *
     * @since  3.0.0
     * @param  string $element_id The element carrying the checkpoint class ( wpsl-wrap, or a basic map container id ).
     * @return string The script tag.
     */
    private function get_gdpr_fast_path_script( $element_id ) {
        $map_service = wpsl_get_active_map_service();

        // Use minified version in production, readable version when debugging
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
            return '<script>
                    ( function() {
                        if ( typeof window.localStorage !== "undefined" ) {
                            const storageKey = "wpsl-gdpr-' . esc_js( $map_service ) . '";

                            if ( localStorage.getItem( storageKey ) === "1" ) {
                                const wpslElement = document.getElementById( "' . esc_js( $element_id ) . '" );

                                if ( wpslElement ) {
                                    wpslElement.classList.remove( "wpsl-gdpr-checkpoint" );
                                    wpslElement.classList.add( "wpsl-gdpr-passed" );
                                }
                            }
                        }
                    })();
                </script>' . "\r\n";
        }

        return '<script>!function(){if("undefined"!=typeof window.localStorage&&"1"===localStorage.getItem("wpsl-gdpr-' . esc_js( $map_service ) . '")){const e=document.getElementById("' . esc_js( $element_id ) . '");e&&(e.classList.remove("wpsl-gdpr-checkpoint"),e.classList.add("wpsl-gdpr-passed"))}}();</script>' . "\r\n";
    }

    /**
     * Detect shortcode attributes early (before wp_enqueue_scripts).
     * This allows us to check if marker_clusters is enabled via shortcode
     * and conditionally enqueue cluster assets.
     *
     * @since  3.0.0
     * @return void
     */
    public function detect_shortcode_attributes() {
        global $post;

        // Only run on singular posts/pages
        if ( ! is_singular() || ! isset( $post->post_content ) ) {
            return;
        }

        // Check if the post content contains a wpsl shortcode
        if ( ! has_shortcode( $post->post_content, 'wpsl' ) && ! has_shortcode( $post->post_content, 'wpsl_map' ) ) {
            return;
        }

        // Parse the shortcodes to extract attributes
        $pattern = get_shortcode_regex( [ 'wpsl', 'wpsl_map' ] );

        if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches ) && array_key_exists( 2, $matches ) ) {
            $detected_atts   = [];
            $cluster_enabled = false;

            foreach ( $matches[2] as $key => $shortcode_tag ) {
                $atts = shortcode_parse_atts( $matches[3][$key] );

                if ( ! is_array( $atts ) ) {
                    continue;
                }

                // Keep the atts from the first [wpsl] shortcode, only one can exist on a page.
                if ( 'wpsl' === $shortcode_tag && empty( $detected_atts ) ) {
                    $detected_atts = $atts;
                }

                /**
                 * The [wpsl_map] shortcode can exist multiple times, so if any of
                 * them ( or the [wpsl] shortcode itself ) enables the marker
                 * clusters, then the cluster assets need to be loaded.
                 */
                foreach ( [ 'marker_clusters', 'marker_cluster' ] as $cluster_att ) {
                    if ( isset( $atts[ $cluster_att ] ) && filter_var( $atts[ $cluster_att ], FILTER_VALIDATE_BOOLEAN ) ) {
                        $cluster_enabled = true;
                    }
                }
            }

            if ( $cluster_enabled ) {
                $detected_atts['marker_clusters'] = 'true';
            }

            if ( $detected_atts ) {
                $shortcodes = $this->container->get( 'shortcodes' );
                $shortcodes->detected_atts = $detected_atts;
            }
        }
    }
}