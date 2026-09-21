<?php
namespace WPSL\Core\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Template Service Provider
 *
 * Registers all template-related services with the container.
 *
 * @since 3.0.0
 */
class Template_Service_Provider implements Service_Provider {

    /**
     * Register template services
     *
     * @param \WPSL\Core\Container $container
     * @return void
     */
    public function register( $container ) {
        $container->register( 'template_loader', function() {
            return new \WPSL\Core\Templates\Loader();
        }, true );

        $container->register( 'template_sections', function( $container ) {
            return new \WPSL\Core\Templates\Sections(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' )
            );
        }, true );

        // Register panel_filters for v3 panel-style templates
        $container->register( 'panel_filters', function( $container ) {
            return new \WPSL\Core\Templates\Panel_Filters(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'frontend_state' ),
                $container,
                $container->get( 'location_utils' )
            );
        }, true );

        $container->register_with_auto_resolution( 'theme_styles', '\WPSL\Core\UI\Theme_Styles', true );
    }
}