<?php
namespace WPSL\Core\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core Service Provider
 *
 * Registers all core services with the container.
 *
 * @since 3.0.0
 */
class Core_Service_Provider implements Service_Provider {

    /**
     * Register core services
     *
     * @param \WPSL\Core\Container $container
     * @return void
     */
    public function register( $container ) {
        $container->register( 'wpsl_settings', function() {
            return new \WPSL\Core\Settings\Manager();
        }, true );
        
        $container->register( 'i18n', function( $container ) {
            return new \WPSL\Core\I18n\Translations(
                $container->get( 'wpsl_settings' )
            );
        }, true );

        /*
         * Auto-resolved services type-hint both, and are resolved by class name.
         * Without the binding each gets a private Manager ( a settings cache
         * that never sees a save ) and hooks a second Translations onto 'init'.
         */
        $container->bind_class( '\WPSL\Core\Settings\Manager', 'wpsl_settings' );
        $container->bind_class( '\WPSL\Core\I18n\Translations', 'i18n' );

        $container->register_with_auto_resolution( 'location_utils', '\WPSL\Core\Utils\Location_Utils', true );
        $container->register_with_auto_resolution( 'system_utils', '\WPSL\Core\Utils\System_Utils', true );
        
        $container->register( 'template_filters', function( $container ) {
            return new \WPSL\Core\Templates\Filters(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' ),
                $container->get( 'frontend_state' ),
                $container,
                $container->get( 'location_utils' )
            );
        }, true );
        
        $container->register_with_auto_resolution( 'store_fields', '\WPSL\Core\Store\Fields_Manager', true );
        $container->register_with_auto_resolution( 'hours', '\WPSL\Core\Hours\Service', true );

        $container->register_shared( 'custom_markers', function() {
            return new \WPSL\Core\Markers\Custom_Markers();
        });

        $container->register_shared( 'shapes_repository', function() {
            return new \WPSL\Core\Shapes\Repository();
        });

        /*
         * Hand back the singleton rather than a second object. upgrade.php and
         * the CPT conversion reach for Notices::instance() directly, and every
         * Notices constructor hooks all_admin_notices, so a container-owned
         * instance alongside the singleton rendered every notice twice.
         */
        $container->register_shared( 'notices', function() {
            return \WPSL\Admin\Core\Notices::instance();
        });

        $container->register( 'frontend_state', function( $container ) {
            return \WPSL\Frontend\State\Manager::get_instance();
        }, true );
        
        $container->register_shared( 'geocode_implementation', function( $container ) {
            $active_map_service = wpsl_get_active_map_service();

            switch ( $active_map_service ) {
                case 'mapbox':
                    return new \WPSL\Admin\API\Geocode_Mapbox( $container->get( 'wpsl_settings' ) );
                case 'osm':
                    return new \WPSL\Admin\API\Geocode_Osm( $container->get( 'wpsl_settings' ) );
                case 'stadia':
                    return new \WPSL\Admin\API\Geocode_Stadia( $container->get( 'wpsl_settings' ) );
                case 'gmaps':
                default:
                    return new \WPSL\Admin\API\Geocode_Gmaps( $container->get( 'notices' ), $container->get( 'wpsl_settings' ) );
            }
        });
    }
}