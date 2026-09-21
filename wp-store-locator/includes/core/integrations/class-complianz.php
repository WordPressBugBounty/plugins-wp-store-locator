<?php
/**
 * WPSL / Complianz class
 *
 * Complianz blocks third party content by rewriting matching script tags in
 * the page buffer. That cannot reach the store locator: with a consent
 * handler active none of the provider libraries are in the HTML at all - PHP
 * publishes them as wpslSettings.api.assets and the frontend injects them
 * once consent is given. So the split is:
 *
 * - Complianz owns the placeholder and the consent state.
 * - The frontend ( gdpr.handleComplianz ) listens for that state and loads
 *   the provider itself.
 *
 * The URL patterns below are still registered as a safety net for setups
 * where a provider tag does end up in the markup.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Integrations;

defined( 'ABSPATH' ) || exit;

class Complianz {

    /**
     * Class constructor
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_filter( 'cmplz_integration_path',            [ $this, 'integration_path' ], 10, 2 );
        add_filter( 'cmplz_placeholder_markers',         [ $this, 'placeholder_markers' ] );
        add_filter( 'cmplz_placeholder_Store-Locator',   [ $this, 'placeholder_image' ] );
        add_filter( 'cmplz_detected_services',           [ $this, 'detected_services' ] );
    }

    /**
     * Point Complianz at our own rules instead of its bundled ones.
     *
     * Complianz applies this filter to the bundled file too, so replacing the
     * stale 2.x rules does not depend on a Complianz release.
     *
     * @since  3.0.0
     * @param  string $path   The integration file Complianz resolved.
     * @param  string $plugin The integration key.
     * @return string
     */
    public function integration_path( $path, $plugin ) {
        if ( 'wp-store-locator' === $plugin ) {
            return WPSL_PLUGIN_DIR . 'includes/core/integrations/complianz-script-tags.php';
        }

        return $path;
    }

    /**
     * Whether the store locator hands its consent handling to Complianz.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_active_handler() {
        return wpsl_get_service( 'wpsl_settings' )->get( 'gdpr', 'handler' ) === 'complianz';
    }

    /**
     * Put a placeholder on the map canvas.
     *
     * @since  3.0.0
     * @param  array $markers Existing placeholder markers.
     * @return array
     */
    public function placeholder_markers( $markers ) {
        if ( ! $this->is_active_handler() ) {
            return $markers;
        }

        $markers['Store Locator'][] = 'wpsl-canvas-' . wpsl_get_active_map_service();

        return $markers;
    }

    /**
     * Report the map artwork as the placeholder image.
     *
     * @since  3.0.0
     * @param  string $src The placeholder Complianz resolved.
     * @return string
     */
    public function placeholder_image( $src ) {
        if ( ! $this->is_active_handler() ) {
            return $src;
        }

        $placeholder = wpsl_get_service( 'assets_manager' )->get_complianz_placeholder( wpsl_get_active_map_service() );

        return $placeholder['url'];
    }

    /**
     * Report the map provider so Complianz can pre-select it and mention it
     * in its own notice about detected services.
     *
     * @since  3.0.0
     * @param  array $services The detected services.
     * @return array
     */
    public function detected_services( $services ) {
        if ( ! $this->is_active_handler() || wpsl_get_active_map_service() !== 'gmaps' ) {
            return $services;
        }

        if ( ! in_array( 'google-maps', $services, true ) ) {
            $services[] = 'google-maps';
        }

        return $services;
    }
}