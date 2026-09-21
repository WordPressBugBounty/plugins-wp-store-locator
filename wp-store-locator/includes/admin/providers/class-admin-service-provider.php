<?php
namespace WPSL\Admin\Providers;

use WPSL\Core\Container;
use WPSL\Core\Providers\Service_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Service Provider
 *
 * Registers all admin-specific services with the container.
 *
 * @since 3.0.0
 */
class Admin_Service_Provider implements Service_Provider {
    
    /**
     * Register admin services
     *
     * @param \WPSL\Core\Container $container
     * @return void
     */
    public function register( $container ) {
        // Register the admin controller
        $container->register_shared( 'admin', function( $container ) {
            return new \WPSL\Admin\Controller( 
                $container,
                $container->get( 'wpsl_settings' ),
                $container->get( 'theme_styles' )
            );
        });
        
        $container->register_shared( 'system', function() {
            return new \WPSL\Admin\Utils\System();
        });
        
        $container->register( 'display_page', function() {
            return new \WPSL\Admin\Core\Display_Page();
        });
        
        // Get the active map service
        $active_map_service = wpsl_get_active_map_service();

        // Register the main geocode service
        $container->register_shared( 'geocode', function( $container ) use ( $active_map_service ) {
            $geocode = new \WPSL\Admin\API\Geocode(
                $container->get( 'notices' ),
                $container->get( 'wpsl_settings' )
            );
            
            // Set the map service and implementation
            $geocode->set_geocode_implementation( $active_map_service );
            
            return $geocode;
        });
                
        // Asset management
        $container->register_with_auto_resolution( 'asset_resources', '\WPSL\Admin\Assets\Resources', false );
        $container->register_with_auto_resolution( 'asset_manager', '\WPSL\Admin\Assets\Manager', true );
        
        $container->register_with_auto_resolution( 'location_status', '\WPSL\Admin\Utils\Location_Status', true );
        
        $container->register_shared( 'api', function( $container ) {
            return new \WPSL\Core\API\Service(
                $container->get( 'location_status' ),
                $container->get( 'geocode' ),
                $container->get( 'location_utils' ),
                $container->get( 'store_fields' ),
                $container->get( 'hours' ),
                $container->get( 'store_data' )
            );
        });
        
        // Metaboxes
        $container->register_shared( 'metaboxes', function( $container ) {
            return new \WPSL\Admin\Core\Metaboxes(
                $container->get( 'geocode' ),
                $container->get( 'admin' ),
                $container->get( 'notices' ),
                $container->get( 'system' ),
                $container->get( 'wpsl_settings' ),
                $container->get( 'api' ),
                $container->get( 'admin_ui' ),
                $container->get( 'location_utils' )
            );
        });

        // API key validation
        $container->register_shared( 'validate_keys', function( $container ) {
            return new \WPSL\Admin\Settings\Validate_Keys(
                $container->get( 'geocode' ),
                $container->get( 'wpsl_settings' )
            );
        });
        
        /*
         * The Manager is also constructed on the post-activation redirect
         * request (Controller::init()), which never renders the onboarding UI,
         * so the container is passed in and render-time dependencies are
         * resolved lazily.
         */
        $container->register_shared( 'onboarding', function( $container ) {
            return new \WPSL\Admin\Onboarding\Manager(
                $container,
                $container->get( 'wpsl_settings' )
            );
        });
        
        // Settings classes
        $container->register( 'api_settings', function( $container ) {
            return new \WPSL\Admin\Settings\API_Settings(
                $container->get( 'wpsl_settings' )
            );
        });
        
        $container->register_shared( 'appearance', function( $container ) {
            return new \WPSL\Admin\Appearance\Manager(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' )
            );
        });
        
        $container->register_shared( 'field_manager', function( $container ) {
            return new \WPSL\Admin\Settings\Field_Manager(
                $container->get( 'wpsl_settings' )
            );
        });
        
        $container->register( 'map_settings', function( $container ) {
            return new \WPSL\Admin\Settings\Map_Settings(
                $container->get( 'wpsl_settings' )
            );
        });
        
        $container->register( 'marker_manager', function( $container ) {
            return new \WPSL\Admin\Settings\Marker_Manager(
                $container->get( 'wpsl_settings' )
            );
        });

        $container->register_shared( 'custom_marker_ajax', function( $container ) {
            return new \WPSL\Admin\Settings\Custom_Marker_Ajax(
                $container->get( 'custom_markers' )
            );
        });

        $container->register_shared( 'map_shapes_admin', function( $container ) {
            return new \WPSL\Admin\Map_Shapes\Manager(
                $container->get( 'shapes_repository' ),
                new \WPSL\Core\Shapes\Sanitizer()
            );
        });

        $container->register_shared( 'section_editor', function( $container ) {
            return new \WPSL\Admin\Settings\Section_Editor(
                $container->get( 'template_sections' ),
                $container->get( 'wpsl_settings' )
            );
        });

        /*
         * The analyzer lives in Core\Templates but only runs while authoring a
         * custom section, so it's registered here rather than alongside its
         * dependencies. is_admin() covers admin-ajax, where the Section_Editor
         * handlers resolve it.
         */
        $container->register_shared( 'section_analyzer', function( $container ) {
            return new \WPSL\Core\Templates\Section_Analyzer(
                $container->get( 'wpsl_settings' ),
                $container->get( 'template_sections' ),
                $container->get( 'i18n' )
            );
        });
        
        $container->register( 'shortcode_generator', function( $container ) {
            return new \WPSL\Admin\Utils\Shortcode_Generator(
                $container->get( 'wpsl_settings' ),
                $container->get( 'marker_manager' ),
                $container->get( 'admin_ui' )
            );
        });
        
        $container->register_shared( 'cache_manager', function( $container ) {
            $api = $container->get( 'api' );
            $nominatim_cache = null;
            
            // Only get nominatim_cache if it exists (for Mapbox or OpenStreetMaps)
            if ( $container->has( 'nominatim_cache' ) ) {
                $nominatim_cache = $container->get( 'nominatim_cache' );
            }
            
            return new \WPSL\Admin\Settings\Cache_Manager(
                $api,
                $nominatim_cache
            );
        });
        
        // Register the settings service
        $container->register_shared( 'settings_service', function( $container ) {
            return new \WPSL\Admin\Settings\Settings_Service(
                $container->get( 'wpsl_settings' )
            );
        });
        
        // Register admin_settings
        $container->register_shared( 'admin_settings', function( $container ) {
            return new \WPSL\Admin\Settings\Manager(
                $container->get( 'wpsl_settings' ),
                $container->get( 'settings_service' ),
                function() use ( $container ) {
                    return $container->get( 'sanitizer' );
                }
            );
        });

        $container->register( 'sanitizer', function( $container ) {
            return new \WPSL\Admin\Settings\Sanitizer(
                $container->get( 'wpsl_settings' ),
                $container->get( 'hours' ),
                $container->get( 'validate_keys' ),
                $container->get( 'admin_settings' )
            );
        });
        
        $container->register_shared( 'admin_ui', function( $container ) {
            return new \WPSL\Admin\Settings\UI(
                $container->get( 'wpsl_settings' ),
                $container->get( 'i18n' )
            );
        });
        
        // Tools classes
        $container->register_shared( 'license_manager', function() {
            return \WPSL\Admin\Tools\License_Manager::instance();
        });
        
        // Data management tool
        $container->register_shared( 'data_management', function( $container ) {
            return new \WPSL\Admin\Tools\Data_Management(
                $container->get( 'wpsl_settings' )
            );
        });

        // Settings export / import.
        $container->register_shared( 'settings_transfer', function( $container ) {
            return new \WPSL\Admin\Tools\Settings_Transfer(
                $container->get( 'wpsl_settings' ),
                $container->get( 'custom_markers' ),
                $container->get( 'shapes_repository' ),
                $container->get( 'template_sections' )
            );
        });

        // Converts 1.x opening hours to the dropdown format.
        $container->register_shared( 'hours_converter', function( $container ) {
            return new \WPSL\Admin\Tools\Hours_Converter(
                $container->get( 'wpsl_settings' )
            );
        });

        // Finds and re-geocodes locations without usable coordinates.
        $container->register_shared( 'geocode_locations', function( $container ) {
            return new \WPSL\Admin\Tools\Geocode_Locations(
                $container->get( 'geocode' )
            );
        });

        // Shortens the admin menu and the Settings page for small sites.
        $container->register_shared( 'simple_mode', function( $container ) {
            return new \WPSL\Admin\Home\Simple_Mode(
                $container->get( 'wpsl_settings' ),
                $container->get( 'custom_markers' ),
                $container->get( 'shapes_repository' )
            );
        });

        // The Home page's setup checklist, counters and quick links.
        $container->register_shared( 'home', function( $container ) {
            return new \WPSL\Admin\Home\Home(
                $container->get( 'wpsl_settings' ),
                $container->get( 'simple_mode' ),
                $container->get( 'custom_markers' )
            );
        });

        /**
         * Conditionally loaded tools.
         *
         * The status report is also needed during the Home page feedback
         * popup's submission, which attaches it to bug reports ( mirrors
         * the Service_Loader's tools context and its wpsl_home_feedback
         * AJAX map entry ).
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- routing only, the AJAX handlers verify their nonces
        $wpsl_page        = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $wpsl_ajax_action = ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
        $wpsl_report_ajax = ( 'wpsl_home_feedback' === $wpsl_ajax_action );

        if ( 'wpsl_tools' === $wpsl_page || $wpsl_report_ajax ) {
            $container->register_shared( 'status_report', function( $container ) {
                return new \WPSL\Admin\Tools\Status_Report(
                    $container->get( 'wpsl_settings' ),
                    $container->get( 'post_types' )
                );
            });
        }
    }
}