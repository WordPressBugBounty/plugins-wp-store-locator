<?php
/**
 * Handle the map functionality.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Maps;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Map\GeoJSON;
use WPSL\Core\Container;
use WPSL\Frontend\State\Manager as StateManager;

class Manager {

    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Translations instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * Frontend state manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container         $container Container instance
     * @param \WPSL\Core\Settings\Manager  $settings  Settings manager instance
     * @param \WPSL\Core\I18n\Translations $i18n      Translations manager instance
     * @param \WPSL\Frontend\State\Manager $state     Frontend state manager instance
     */
    public function __construct( Container $container, WpslSettings $settings, Translations $i18n, StateManager $state ) {
        $this->container = $container;
        $this->settings = $settings;
        $this->i18n = $i18n;
        $this->state = $state;
    }

    /**
     * Get the Frontend Controller instance
     *
     * @since  3.0.0
     * @return \WPSL\Frontend\Controller
     */
    private function get_frontend() {
        return $this->container->get( 'frontend' );
    }

    /**
     * Get the Shortcodes instance
     *
     * @since  3.0.0
     * @return \WPSL\Frontend\Shortcodes\Shortcodes
     */
    private function get_shortcodes() {
        return $this->container->get( 'shortcodes' );
    }

    /**
     * Get the HTML for the map controls.
     *
     * The '&#xe800;' and '&#xe801;' code is for the icon font from fontello.com
     *
     * @since  2.0.0
     * @return string The HTML for the map controls
     */
    public function get_controls() {
        $wpsl_settings = $this->settings->get_all();

        $classes = [];

        if ( $wpsl_settings['ux']['reset_map'] ) {
            $reset_button = '<button class="wpsl-icon-reset" aria-label="' . esc_html__( 'Reset the map', 'wp-store-locator' ) . '" tabindex="0"><span aria-hidden="true" title="' . esc_html__( 'Reset the map', 'wp-store-locator' ) . '">&#xe807;</span></button>';
        } else {
            $reset_button = '';
        }

        // If SSL isn't used, then don't reader the auto_locate option.
        if ( $wpsl_settings['search']['auto_locate'] && wpsl_get_service( 'system_utils' )->ssl_active() ) {
            $geolocation_button = '<button class="wpsl-icon-direction" aria-label="' . esc_html__( 'Use my current location', 'wp-store-locator' ) . '" tabindex="0"><span aria-hidden="true" title="' . esc_html__( 'Use my current location', 'wp-store-locator' ) . '">&#xe808;</span></button>';
        } else {
            $geolocation_button = '';
        }

        if ( $wpsl_settings['ux']['reset_map'] && ! $wpsl_settings['search']['auto_locate'] ) {
            $classes[] = 'wpsl-hide-reset';
        }

        // If the street view option is enabled, then we need to adjust the right margin for the map control div.
        if ( $wpsl_settings['map']['streetview'] && $wpsl_settings['api']['active_map_service'] === 'gmaps' ) {
            $classes[] = 'wpsl-street-view-exists';
        }

        if ( ! empty( $classes ) ) {
            $class = 'class="' . join( ' ', $classes ) . '"';
        } else {
            $class = '';
        }

        $map_controls = '';

        if ( $reset_button || $geolocation_button ) {
            $map_controls = '<div id="wpsl-map-controls" ' . $class . '>' . $reset_button . $geolocation_button . '</div>';
        }

        return apply_filters( 'wpsl_map_controls', $map_controls );
    }

    /**
     * Get the map settings.
     *
     * @since  3.0.0
     * @return array
     */
    function get_map_settings() {
        $wpsl_settings = $this->settings->get_all();

        /**
         * Include different settings based on whether
         * Google Maps / OpenStreetMaps / Mapbox is used.
         */
        if ( $wpsl_settings['api']['active_map_service'] == 'gmaps' ) {
            $map_settings = [
                'type'                => $wpsl_settings['map']['map_type'],
                'typeControl'         => $wpsl_settings['map']['type_control'],
                'scrollWheel'         => $wpsl_settings['map']['scrollwheel'],
                'streetView'          => $wpsl_settings['map']['streetview'],
                'gestureHandling'     => apply_filters( 'wpsl_gesture_handling', 'auto' ),
                'streetViewAvailable' => false,
            ];
        }

        $map_settings['controlPosition'] = $wpsl_settings['map']['control_position'];
        $map_settings['controls']        = $this->get_controls();
        // A [wpsl] city / state / country restriction geocodes its own start
        // location in Shortcodes::check_sl_shortcode_atts(), which overrides
        // this value through the atts['js'] merge.
        $map_settings['startLatLng']     = $wpsl_settings['map']['start_latlng'];
        $map_settings['zoomLevel']       = $wpsl_settings['map']['zoom_level'];
        $map_settings['autoZoomLevel']   = $wpsl_settings['map']['auto_zoom_level'];
        $map_settings['tabAnchor']       = $this->get_map_tab_anchor();
        $map_settings['tabAnchorReturn'] = apply_filters( 'wpsl_map_tab_anchor_return', false );

        if ( $wpsl_settings['api']['active_map_service'] == 'mapbox' ) {
            $map_settings['style'] = wpsl_active_mapbox_style();
        }

        if ( $wpsl_settings['api']['active_map_service'] == 'gmaps' ) {
            $selected_style = $wpsl_settings['appearance']['map_style']['gmaps']['selected'];

            if ( $selected_style == 'json' ) {
                $map_settings['style'] = wp_strip_all_tags( stripslashes( json_decode( $wpsl_settings['appearance']['map_style']['gmaps']['json'] ) ) );
            } else {
                /**
                 * Cloud-based styling is selected. Advanced Markers require a Map ID,
                 * so when none is configured we fall back to Google's DEMO_MAP_ID.
                 * Without this the map is created without a Map ID while the markers
                 * code still uses Advanced Markers, which breaks them and triggers
                 * the "map is initialized without a valid Map ID" warning.
                 */
                $cloud_based_id        = $wpsl_settings['appearance']['map_style']['gmaps']['cloud_based'];
                $map_settings['mapId'] = ! empty( $cloud_based_id ) ? $cloud_based_id : 'DEMO_MAP_ID';
            }
        }

        return $map_settings;
    }

    /**
     * Get the map tab anchors.
     *
     * If the wpsl/wpsl_map shortcode is used in one or more tabs,
     * then a JS fix ( the fixGreyTabMap function ) needs to run
     * to make sure the map doesn't turn grey.
     *
     * For the fix to work need to know the used anchor(s).
     *
     * @since  2.2.10
     * @return string|array $map_tab_anchor One or more anchors used to show the map(s)
     */
    public function get_map_tab_anchor() {
        return apply_filters( 'wpsl_map_tab_anchor', 'wpsl-map-tab' );
    }
}