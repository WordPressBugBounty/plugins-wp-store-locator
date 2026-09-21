<?php
namespace WPSL\Frontend\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Providers\Service_Provider;

/**
 * Frontend Service Provider
 *
 * Registers all frontend-specific services with the container.
 *
 * @since 3.0.0
 */
class Frontend_Service_Provider implements Service_Provider {
    
    /**
     * Register frontend services
     *
     * @param \WPSL\Core\Container $container
     * @return void
     */
    public function register( $container ) {
        $container->register_shared( 'frontend', function( $container ) {
            return new \WPSL\Frontend\Controller(
                $container,
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'template_filters' ),
                $container->get( 'theme_styles' )
            );
        });
                        
        $container->register_with_auto_resolution( 'maps_settings', '\WPSL\Frontend\Maps\Settings', false );
        
        $container->register( 'maps_markers', function( $container ) {
            return new \WPSL\Frontend\Maps\Markers(
                $container->get( 'wpsl_settings' ),
                $container->get( 'frontend_state' ),
                $container
            );
        }, false );
        
        $container->register( 'maps_manager', function( $container ) {
            return new \WPSL\Frontend\Maps\Manager(
                $container,
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'frontend_state' )
            );
        });
        
        $container->register( 'assets_resources', function( $container ) {
            return new \WPSL\Frontend\Assets\Resources(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'template_sections' ),
                $container->get( 'maps_markers' ),
                $container->get( 'maps_manager' ),
                $container->get( 'template_filters' ),
                $container->get( 'frontend_state' ),
                $container
            );
        });
        
        $container->register( 'assets_manager', function( $container ) {
            return new \WPSL\Frontend\Assets\Manager(
                $container,
                $container->get( 'wpsl_settings' ),
                $container->get( 'assets_resources' ),
                $container->get( 'theme_styles' ),
                $container->get( 'templates_manager' ),
                $container->get( 'store_data' ),
                $container->get( 'frontend_state' )
            );
        }, true );

        $container->register( 'shortcodes', function( $container ) {
            return new \WPSL\Frontend\Shortcodes\Shortcodes(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'frontend_state' ),
                $container
            );
        }, true );

        $container->register( 'store_data', function( $container ) {
            return new \WPSL\Frontend\Store\Data(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'hours' ),
            );
        }, false );
        
        $container->register( 'search', function( $container ) {
            return new \WPSL\Frontend\Search\Search(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'template_filters' ),
                $container->get( 'location_utils' ),
                $container->get( 'store_data' )
            );
        }, false );
        
        $container->register( 'search_filters', function( $container ) {
            return new \WPSL\Frontend\Search\Filters(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'frontend_state' ),
                $container,
                $container->get( 'location_utils' )
            );
        }, false );
        
        $container->register( 'search_types', function( $container ) {
            return new \WPSL\Frontend\Search\Types(
                $container->get( 'wpsl_settings' ),
                $container->get( 'store_data' ),
                $container->get( 'location_utils' )
            );
        }, false );
                
        $container->register( 'templates_manager', function( $container ) {
            return new \WPSL\Frontend\Templates\Manager(
                $container,
                $container->get( 'wpsl_settings' ),
                $container->get( 'template_sections' ),
                $container->get( 'frontend_state' )
            );
        }, false );
    }
}