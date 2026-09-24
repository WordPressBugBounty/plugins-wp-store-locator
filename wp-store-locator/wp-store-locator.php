<?php
/*
Plugin Name: WP Store Locator
Description: WordPress store locator for OpenStreetMap, Stadia Maps, Mapbox and Google Maps, with unlimited locations, custom fields and custom markers. Free on OpenStreetMap, no API key needed.
Author: Tijmen Smit
Author URI: https://wpstorelocator.co/
Version: 3.0.1
Tested up to: 7.1
Requires at least: 5.7
Requires PHP: 7.4
Text Domain: wp-store-locator
Domain Path: /languages/
License: GPL v3

WP Store Locator
Copyright (C) 2013-2026 Tijmen Smit - tijmen@wpstorelocator.co

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_Store_locator' ) ) {

    final class WP_Store_locator {
        
        /**
         * Holds the singleton instance
         *
         * @var WP_Store_locator
         * @since 3.0.0
         */
        private static $instance = null;

        /**
         * WPSL Container object.
         *
         * @var   \WPSL\Core\Container
         * @since 3.0.0
         */
        public $container;

        /**
         * Post types instance
         *
         * @var \WPSL\Core\Post_Types\Register
         * @since 3.0.0
         */
        public $post_types;

        /**
         * Class constructor
         *
         * @since 2.0.0
         * @return void
         */
        private function __construct() {
            $this->define_constants();
            $this->register_autoloader();
            
            $this->includes();
            
            $this->init_global_container();
            
            add_action( 'wp_ajax_store_search',        [ '\WPSL\Frontend\Search\Search', 'handle_ajax_search' ] );
            add_action( 'wp_ajax_nopriv_store_search', [ '\WPSL\Frontend\Search\Search', 'handle_ajax_search' ] );
            
            // Initialize hooks
            $this->init_hooks();
        }

        /**
         * Get the singleton instance
         *
         * @since 3.0.0
         * @return WP_Store_locator The singleton instance
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            
            return self::$instance;
        }

        /**
         * Define WPSL Constants.
         *
         * @since  2.0.0
         * @return void
         */
        private function define_constants() {
            if ( ! defined( 'WPSL_VERSION_NUM' ) )
                define( 'WPSL_VERSION_NUM', '3.0.1' );

            if ( ! defined( 'WPSL_URL' ) )
                define( 'WPSL_URL', plugin_dir_url( __FILE__ ) );

            if ( ! defined( 'WPSL_BASENAME' ) )
                define( 'WPSL_BASENAME', plugin_basename( __FILE__ ) );

            if ( ! defined( 'WPSL_PLUGIN_DIR' ) )
                define( 'WPSL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
                
            if ( ! defined( 'WPSL_PLUGIN_FILE' ) )
                define( 'WPSL_PLUGIN_FILE', __FILE__ );
        }

        /**
         * Register the autoloader.
         *
         * @since  3.0.0
         * @return void
         */
        private function register_autoloader() {
            spl_autoload_register( function( $class ) {
                $base_dir = WPSL_PLUGIN_DIR;
                
                $prefix = 'WPSL\\';
                $len = strlen( $prefix );
                
                // Does the class use the namespace prefix?
                if ( strncmp( $prefix, $class, $len ) !== 0 ) {
                    return;
                }
                
                $relative_class = substr( $class, $len );
                
                // Skip loading certain classes based on settings
                if ( $relative_class === 'Core\\Map\\GeoJSON' ) {

                    // Only check if container is fully initialized with settings
                    if ( isset( $this->container ) && 
                         $this->container instanceof \WPSL\Core\Container &&
                         $this->container->has( 'wpsl_settings' ) ) {
                        
                        try {
                            $settings = $this->container->get( 'wpsl_settings' );
                            $map_service = $settings->get( 'api', 'active_map_service' );
                            
                            if ( $map_service !== 'mapbox' ) {
                                return; // Skip loading GeoJSON for non-Mapbox providers
                            }
                        } catch ( \Exception $e ) {
                            // Container not ready yet, allow class to load
                            // It will be conditionally used later
                        }
                    }
                }
                
                // Get the last part of the class name (after the last \)
                $class_parts = explode( '\\', $relative_class );
                $class_name = end( $class_parts );
                
                // Replace the class name with class-{lowercase-name}.php
                $class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
                array_pop( $class_parts );
                
                // Build the file path
                $file_path = empty( $class_parts ) ? '' : implode( '/', $class_parts ) . '/';
                
                // Convert underscores to hyphens in the file path
                $file_path = str_replace( '_', '-', $file_path );
                
                // All classes are now in the includes directory
                $file = $base_dir . 'includes/' . strtolower( str_replace( '\\', '/', $file_path ) ) . $class_file;

                if ( file_exists( $file ) ) {
                    require $file;
                }
            });
        }

        /**
         * Include the required files.
         *
         * @since  2.0.0
         * @return void
         */
        private function includes() {
            require_once( WPSL_PLUGIN_DIR . 'includes/core/wpsl-functions.php' );
            require_once( WPSL_PLUGIN_DIR . 'includes/core/wpsl-svg-icons.php' );
            require_once( WPSL_PLUGIN_DIR . 'includes/core/wpsl-exclude-optimization.php' );

            if ( is_admin() || defined( 'WP_CLI' ) && WP_CLI ) {
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/utils/roles.php' );
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/functions.php' );
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/utils/helpers.php' );

                // Not namespaced, so the autoloader doesn't pick it up.
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/utils/class-wpsl-progress-bar.php' );
            }
        }
        
        /**
         * Initialize the global container early so it's available for AJAX
         * handlers. Core and Template providers register here; the frontend
         * and admin providers register later via init_services() on the
         * 'wpsl_init' action (fired from WordPress' 'init' hook).
         *
         * @since 3.0.0
         * @return void
         */
        private function init_global_container() {
            $GLOBALS['wpsl_container'] = new \WPSL\Core\Container();
            
            $this->container = $GLOBALS['wpsl_container'];
            
            $this->register_essential_services();
        }
        
        /**
         * Register essential services needed for AJAX handlers
         * This runs immediately after container initialization
         *
         * @since 3.0.0
         * @return void
         */
        private function register_essential_services() {

            // Register the container itself as a service
            $this->container->register( 'container', function( $container ) {
                return $container;
            }, true );

            // Without this an auto-resolved constructor asking for a Container
            // would silently receive a fresh, empty one.
            $this->container->bind_class( '\WPSL\Core\Container', 'container' );
            
            // Register core service providers needed for AJAX
            $this->container->register_provider( new \WPSL\Core\Providers\Core_Service_Provider() );
            $this->container->register_provider( new \WPSL\Core\Providers\Template_Service_Provider() );
        }

        /**
         * Initialize hooks
         *
         * @since 3.0.0
         * @return void
         */
        private function init_hooks() {
            add_action( 'init',      [ $this, 'init' ], 0 );
            add_action( 'wpsl_init', [ $this, 'init_services' ] );
        }

        /**
         * Initialize WP Store Locator when WordPress initializes.
         *
         * @since  3.0.0
         * @return void
         */
        public function init() {
            $this->load_plugin_textdomain();
            
            do_action( 'wpsl_init' );
        }

        /**
         * Load plugin textdomain.
         *
         * @since  3.0.0
         * @return void
         */
        public function load_plugin_textdomain() {
            $locale = apply_filters( 'plugin_locale', determine_locale(), 'wp-store-locator' );
            
            // Load the language file from the /wp-content/languages/wp-store-locator folder, custom + update proof translations
            load_textdomain( 'wp-store-locator', WP_LANG_DIR . '/wp-store-locator/wp-store-locator-' . $locale . '.mo' );
            
            // Load the language file from the plugin's languages directory
            load_plugin_textdomain( 'wp-store-locator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }

        /**
         * Initialize services
         * 
         * @since  3.0.0
         * @return void
         */
        public function init_services() {
            $this->container->register_provider( new \WPSL\Core\Providers\Map_Service_Provider() );

            /*
             * A scheduled reopen fires from WP-Cron, which is not an admin
             * request, so the listener is added on every request instead of
             * by the admin-only location_status service.
             */
            \WPSL\Admin\Utils\Location_Status::register_hooks();

            if ( $this->container->has( 'nominatim_cache' ) ) {
                $this->container->get( 'nominatim_cache' );
            }

            // Register post types in container first
            $this->container->register( 'post_types', function( $container ) {
                return new \WPSL\Core\Post_Types\Register( 
                    $container->get( 'wpsl_settings' ) 
                );
            }, true );
            
            // Then get from container and register with WordPress
            $this->post_types = $this->container->get( 'post_types' );
            $this->register_post_types();

            // Register taxonomy image handler in container
            $this->container->register( 'taxonomy_image', function( $container ) {
                return new \WPSL\Core\Post_Types\Taxonomy_Image();
            }, true );
            
            /*
             * Five of the eight hooks are admin-only UI, but the term-write
             * hooks fire on REST, WP-CLI, or wp_insert_term() too, so only a
             * front-end page view can safely skip instantiation.
             */
            if ( ! wpsl_is_frontend_page_view() ) {
                $this->container->get( 'taxonomy_image' );
            }

            if ( is_user_logged_in() ) {
                $this->container->register_shared( 'admin_bar_menu', function() {
                    return new \WPSL\Admin\Core\Admin_Bar_Menu();
                } );

                $this->container->register_shared( 'plugin_alerts', function() {
                    return new \WPSL\Admin\Core\Alerts();
                } );

                $this->container->get( 'admin_bar_menu' );
            }

            if ( is_admin() ) {
                $this->container->register_provider( new \WPSL\Admin\Providers\Admin_Service_Provider() );
    
                /**
                 * Backward compat: 2.x add-ons read $wpsl_admin->notices,
                 * ->metaboxes, ->geocode and ->settings_page. The controller
                 * maps those onto container services via __get()/__call().
                 */
                $GLOBALS['wpsl_admin'] = $this->container->get( 'admin' );
            }

            $this->container->register_provider( new \WPSL\Frontend\Providers\Frontend_Service_Provider() );
            $this->container->get( 'frontend' );

            /*
             * Unconditional as well: store coordinates change from the
             * editor, Quick Edit, REST, imports, WP-CLI and front-end
             * submission forms, and the cached coordinate audit that the
             * store list and the alerts read has to follow every one of them.
             * The admin service that owns the audit only loads on the store
             * list screen, so its hooks are registered from here.
             */
            \WPSL\Admin\Tools\Geocode_Locations::register_sync_hooks();

            // The scheduled reopen of a temporarily closed store runs under WP-Cron, not admin.
            \WPSL\Admin\Utils\Location_Status::register_hooks();

            /*
             * Unconditional and outside is_admin(): the block editor needs the
             * blocks to offer them, front-end page views need the render
             * callbacks, and REST needs them to save posts containing them.
             * Admin_Service_Provider loads in none of those last two contexts.
             */
            $this->container->register_shared( 'blocks', function() {
                return new \WPSL\Admin\Blocks\Manager();
            } );
            $this->container->get( 'blocks' )->register();

            // Backward compatibility: expose $wpsl_settings as a flat v2-style global so that
            // existing filter callbacks written for v2.x work without any code changes.
            $GLOBALS['wpsl_settings'] = wpsl_build_v2_settings();
        }

        /**
         * Install the plugin data.
         *
         * @since 2.0.0
         * @param bool $network_wide Whether the plugin is being activated network-wide
         * @return void
         */
        public function install( $network_wide ) {
            require_once( WPSL_PLUGIN_DIR . 'includes/install.php' );

            wpsl_install( $network_wide );
        }

        /**
         * Register post types early in the init hook
         * 
         * @since 3.0.0
         * @return void
         */
        public function register_post_types() {
            $this->post_types->register_post_types();
            $this->post_types->register_taxonomies();
        }

        /**
         * Setup the plugin settings.
         *
         * @since      2.0.0
         * @deprecated 3.0.0
         * @return     void
         */
        public function plugin_settings() {

            _deprecated_function( __FUNCTION__, '3.0.0' );
        }

        /**
         * Prevent cloning of the singleton instance.
         *
         * @since 3.0.0
         * @access protected
         * @return void
         */
        public function __clone() {
            _doing_it_wrong( __FUNCTION__, 'Cloning of this singleton is not allowed.', '3.0.0' );
        }

        /**
         * Prevent unserializing of the singleton instance.
         *
         * @since 3.0.0
         * @access protected
         * @return void
         */
        public function __wakeup() {
            _doing_it_wrong( __FUNCTION__, 'Unserialization of this singleton is not allowed.', '3.0.0' );
        }
    }

    /**
     * Returns the main instance of WPSL.
     *
     * @since  3.0.0
     * @return WP_Store_locator
     */
    function WPSL() {
        return WP_Store_locator::instance();
    }

    /**
     * Get the WP Store Locator container
     * 
     * @since 3.0.0
     * @return \WPSL\Core\Container
     * @throws \RuntimeException If container not initialized
     */
    function wpsl_container() {
        
        if ( ! isset( $GLOBALS['wpsl_container'] ) ) {
            throw new \RuntimeException( 
                'WPSL Container not initialized. Ensure plugins_loaded hook has fired.' 
            );
        }

        return $GLOBALS['wpsl_container'];
    }

    register_activation_hook( __FILE__, function( $network_wide ) {
        if ( ! class_exists( 'WP_Store_locator' ) ) {
            return;
        }

        WPSL()->install( $network_wide );
    });

    add_action( 'plugins_loaded', function() { WPSL(); }, 5 );

    /*
     * WordPress doesn't run activation hooks for subsites created after a
     * network activation, and only drops its own tables when a subsite is
     * deleted. Priority 900 runs after core finished creating the site tables.
     */
    add_action( 'wp_initialize_site', function( $new_site ) {
        require_once WPSL_PLUGIN_DIR . 'includes/install.php';

        wpsl_install_new_site( $new_site );
    }, 900 );

    add_filter( 'wpmu_drop_tables', function( $tables, $site ) {
        require_once WPSL_PLUGIN_DIR . 'includes/install.php';

        return wpsl_drop_site_tables( $tables, $site );
    }, 10, 2 );

    /**
     * Neutralize any active pre-2.0 add-on so it cannot fatal the site.
     *
     * Runs after core boots ( priority 5 ) and before the add-ons initialize
     * ( priority 10 ), so it can define the legacy geocode class and hook the
     * widget/menu suppressors before the add-on code needs them.
     *
     * @see \WPSL\Core\Legacy_Addons
     */
    add_action( 'plugins_loaded', function() {
        new \WPSL\Core\Legacy_Addons();
    }, 6 );

    /**
     * Load the Complianz integration.
     */
    add_action( 'plugins_loaded', function() {
        if ( ! class_exists( 'COMPLIANZ' ) ) {
            return;
        }

        require_once WPSL_PLUGIN_DIR . 'includes/core/integrations/class-complianz.php';

        new \WPSL\Core\Integrations\Complianz();
    }, 6 );
}