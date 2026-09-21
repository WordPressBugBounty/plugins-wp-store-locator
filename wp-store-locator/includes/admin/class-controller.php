<?php
/**
 * Admin controller
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin; 

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Container;
use WPSL\Core\UI\Theme_Styles;
use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Utils\Location_Utils;

use WPSL\Admin\API\Geocode;
use WPSL\Admin\Core\Display_Page;
use WPSL\Admin\Core\Service_Loader;

class Controller {

    /**
     * Geocode object.
     *
     * @since 2.0.0
     * @var   \WPSL\Admin\API\Geocode
     */
    private $geocode;

    /**
     * Display Page object.
     *
     * @since 3.0.0
     * @var   \WPSL\Admin\Core\Display_Page
     */
    private $display_page;

    /**
     * Onboarding object.
     *
     * @since 3.0.0
     * @var   \WPSL\Admin\Onboarding\Manager
     */
    private $onboarding;

    /**
     * Container object.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Container
     */
    private $container;

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Theme Styles object.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\UI\Theme_Styles
     */
    private $theme_styles;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container       $container    Service container instance
     * @param \WPSL\Core\Settings\Manager $settings     Settings manager instance
     * @param \WPSL\Core\UI\Theme_Styles $theme_styles Theme styles instance
     */
    public function __construct( $container, WpslSettings $settings, Theme_Styles $theme_styles ) {
        $this->container = $container;
        $this->settings  = $settings;
        $this->theme_styles = $theme_styles;

        $this->includes();
        
        add_action( 'init',                                 [ $this, 'init' ] );
        add_action( 'admin_menu',                           [ $this, 'create_admin_menu' ], 20 );
        add_action( 'admin_enqueue_scripts',                [ $this, 'menu_icon_style' ] );
        add_action( 'admin_init',                           [ $this, 'process_actions' ] );
        add_action( 'current_screen',                       [ $this, 'fix_whats_new_page_title' ] );
        add_action( 'current_screen',                       [ $this, 'defer_notices_for_own_pages' ] );

        add_action( 'wp_loaded',                            [ $this, 'disable_setting_notices' ] );
        add_action( 'wp_ajax_wpsl_validate_save_post',      [ $this, 'validate_save_post' ] );

        add_filter( 'submenu_file',                         [ $this, 'highlight_current_submenu' ] );
        add_filter( 'plugin_row_meta',                      [ $this, 'add_plugin_meta_row' ], 10, 2 );
        add_filter( 'plugin_action_links_' . WPSL_BASENAME, [ $this, 'add_action_links' ], 10, 2 );
        add_filter( 'admin_footer_text',                    [ $this, 'admin_footer_text' ], 1 );
    }

    /**
     * Include the required files.
     *
     * @since 2.0.0
     * @return void
     */
    private function includes() {
        require_once( WPSL_PLUGIN_DIR . 'includes/admin/functions.php' );
        require_once( WPSL_PLUGIN_DIR . 'includes/admin/utils/helpers.php' );
    }

    /**
     * Init the classes.
     *
     * The always-global services are resolved unconditionally; everything
     * screen-specific goes through the Service_Loader, which maps the
     * request to the services whose hooks it actually needs.
     *
     * @since  3.0.0
     * @return void
     */
    public function init() {
        // Initialize settings first to prevent circular dependencies
        $settings = $this->container->get( 'wpsl_settings' );

        // Force load all settings to ensure they're cached
        $settings->get_all();

        $this->display_page = $this->container->get( 'display_page' );

        /*
         * Always global: the menu is built on every admin request, and the
         * body class has to be on every WPSL screen, not just the Home page.
         */
        $this->container->get( 'simple_mode' );

        $this->container->get( 'system' );

        /*
         * admin-ajax.php sets WP_ADMIN, so this runs on nopriv requests like
         * the store search too. Every alert hook needs a logged in admin.
         */
        if ( is_user_logged_in() ) {
            $this->container->get( 'plugin_alerts' );
        }

        $this->container->get( 'notices' );

        // Include the upgrade.php file for CPT conversion AJAX handlers
        require_once( WPSL_PLUGIN_DIR . 'includes/admin/upgrade.php' );

        /**
         * The onboarding redirect only has a window right after activation:
         * install.php sets a transient ( 5 minutes ), and the first admin
         * request afterwards ( usually plugins.php ) must redirect from
         * whatever page it lands on. Outside that window the service is
         * only needed on its own page, where the Service_Loader resolves it.
         */
        if ( get_transient( 'wpsl_onboarding_redirect' ) ) {
            $this->onboarding = $this->container->get( 'onboarding' );
        }

        $loader   = new Service_Loader();
        $contexts = $loader->get_contexts();

        foreach ( $loader->get_required_files( $contexts ) as $file ) {
            require_once( WPSL_PLUGIN_DIR . $file );
        }

        /**
         * The has() guard covers services with conditional registrations
         * ( status_report is only registered on the requests that need it ).
         */
        foreach ( $loader->get_services( $contexts ) as $service_id ) {

            if ( $this->container->has( $service_id ) ) {
                $this->container->get( $service_id );
            }
        }
    }

    /**
     * Resolve the geocode service on first use.
     *
     * validate_save_post() is hooked on every admin request, but the geocode
     * service is only built on store screens, so the AJAX handler resolves
     * it itself instead of relying on init() having done so.
     *
     * @since  3.0.0
     * @return \WPSL\Admin\API\Geocode
     */
    private function get_geocode() {
        if ( ! $this->geocode ) {
            $this->geocode = $this->container->get( 'geocode' );
        }

        return $this->geocode;
    }

    /**
     * Add the admin menu pages.
     *
     * @since 1.0.0
     * @return void
     */
    public function create_admin_menu() {
        // Make sure display_page is initialized
        if ( ! $this->display_page) {
            $this->display_page = $this->container->get( 'display_page' );
        }

        $sub_menus = apply_filters( 'wpsl_sub_menu_items', [
                [
                    'page_title' => esc_html__( 'Home', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Home', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_home',
                    'function'   => [ $this->display_page, 'create' ],
                    'position'   => 0,
                ],
                [
                    'page_title' => esc_html__( 'Settings', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Settings', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_settings',
                    'function'   => [ $this->display_page, 'create' ]
                ],
                [
                    'page_title' => esc_html__( 'Appearance', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Appearance', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_appearance',
                    'function'   => [ $this->display_page, 'create_appearance_page' ]
                ],
                [
                    'page_title' => esc_html__( 'Map Shapes', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Map Shapes', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_map_shapes',
                    'function'   => [ $this->display_page, 'create_map_shapes_page' ]
                ],
                [
                    'page_title' => esc_html__( 'Marker Studio', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Marker Studio', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_marker_studio',
                    'function'   => [ $this->display_page, 'create_marker_studio_page' ]
                ],
                [
                    'page_title' => esc_html__( 'Add-Ons', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Add-Ons', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_addons',
                    'function'   => [ $this->display_page, 'create' ]
                ],
                [
                    'page_title' => esc_html__( 'Tools', 'wp-store-locator' ),
                    'menu_title' => esc_html__( 'Tools', 'wp-store-locator' ),
                    'caps'       => 'manage_wpsl_settings',
                    'menu_slug'  => 'wpsl_tools',
                    'function'   => [ $this->display_page, 'create' ]
                ]
            ]
        );

        /*
         * Simple mode, applied after the filter so an add-on's own entries
         * are never touched. The pages stay registered as routes: hiding is
         * about the menu, and a bookmarked URL keeps working.
         */
        $simple_mode = $this->container->get( 'simple_mode' );
        $hideable    = [
            'wpsl_marker_studio' => 'marker_studio',
            'wpsl_map_shapes'    => 'map_shapes',
        ];

        if ( count( $sub_menus ) ) {
            foreach ( $sub_menus as $sub_menu ) {
                $position = isset( $sub_menu['position'] ) ? $sub_menu['position'] : null;

                add_submenu_page( 'edit.php?post_type=wpsl_stores', $sub_menu['page_title'], $sub_menu['menu_title'], $sub_menu['caps'], $sub_menu['menu_slug'], $sub_menu['function'], $position );
            }
        }

        // Page titles of routes kept out of the menu, see set_unlisted_page_title().
        $unlisted = [];

        // Registered above so the URL stays routable, then taken out of the menu only.
        foreach ( $sub_menus as $sub_menu ) {
            $slug = isset( $sub_menu['menu_slug'] ) ? $sub_menu['menu_slug'] : '';

            if ( isset( $hideable[ $slug ] ) && $simple_mode->is_hidden( $hideable[ $slug ] ) ) {
                remove_submenu_page( 'edit.php?post_type=wpsl_stores', $slug );
                $unlisted[ $slug ] = $sub_menu['page_title'];
            }
        }

        /**
         * The "What's New" screen is only shown to users once after the 2.x ->
         * 3.0 update, so it's registered as a routable page but removed from
         * the Store Locator menu.
         */
        add_submenu_page(
            'edit.php?post_type=wpsl_stores',
            esc_html__( "What's New", 'wp-store-locator' ),
            esc_html__( "What's New", 'wp-store-locator' ),
            'manage_wpsl_settings',
            'wpsl_whats-new',
            [ $this->display_page, 'create' ]
        );
        remove_submenu_page( 'edit.php?post_type=wpsl_stores', 'wpsl_whats-new' );
        $unlisted['wpsl_whats-new'] = __( "What's New", 'wp-store-locator' );

        $this->set_unlisted_page_title( $unlisted );
    }

    /**
     * Give a page that is registered but not in the menu its title.
     *
     * WordPress looks the title up in the menu, so for these it finds none and
     * admin-header.php hands null to strip_tags().
     *
     * @since  3.0.0
     * @param  array $unlisted Page titles keyed by menu slug.
     * @return void
     */
    private function set_unlisted_page_title( $unlisted ) {
        global $plugin_page, $title;

        if ( is_string( $plugin_page ) && isset( $unlisted[ $plugin_page ] ) && empty( $title ) ) {
            $title = $unlisted[ $plugin_page ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- read by admin-header.php
        }
    }

    /**
     * Tell WordPress which submenu item the Home page belongs to.
     *
     * @since  3.0.0
     * @param  string|null $submenu_file The file already chosen, if any.
     * @return string|null
     */
    public function highlight_current_submenu( $submenu_file ) {
        if ( ! empty( $submenu_file ) ) {
            return $submenu_file;
        }

        $plugin_page = isset( $GLOBALS['plugin_page'] ) ? $GLOBALS['plugin_page'] : '';

        if ( ! is_string( $plugin_page ) || strpos( $plugin_page, 'wpsl' ) !== 0 ) {
            return $submenu_file;
        }

        return $plugin_page;
    }

    /**
     * Paint the map pin on the Store Locator admin menu item.
     *
     * @since  3.0.0
     * @return void
     */
    public function menu_icon_style() {
        $icon = wpsl_get_pin_icon_url();

        $mask = 'url( "' . $icon . '" ) no-repeat center / 20px';

        $css = '
            #adminmenu #menu-posts-wpsl_stores .wp-menu-image:before {
                content: "";
                display: block;
                width: 20px;
                height: 20px;
                margin: 0 auto;
                background-color: currentColor;
                -webkit-mask: ' . $mask . ';
                mask: ' . $mask . ';
            }
        ';

        wp_add_inline_style( 'admin-menu', $css );
    }

    /**
     * Restore the page title for the hidden "What's New" screen.
     *
     * @since  3.0.0
     * @return void
     */
    public function fix_whats_new_page_title() {
        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wpsl_whats-new' ) {
            $GLOBALS['title'] = esc_html__( "What's New", 'wp-store-locator' );
        }
    }

    /**
     * Defer admin notices on our own settings-style pages so they render
     * inside Display_Page::admin_header(), below the WPSL logo header,
     * instead of in WordPress' default position above it.
     *
     * @since  3.0.0
     * @return void
     */
    public function defer_notices_for_own_pages() {
        if ( ! wpsl_admin_page_renders_own_notices() ) {
            return;
        }

        $this->container->get( 'notices' )->defer();
    }

    /**
     * Make sure the correct action is called.
     *
     * @since 3.0.0
     */
    public function process_actions() {
        if ( ! isset( $_REQUEST['wpsl-action'] ) || ! current_user_can( 'manage_wpsl_settings' ) ) {
            return;
        }

        $action = sanitize_key( $_REQUEST['wpsl-action'] );

        /**
         * Filter the request actions that may be dispatched as a wpsl_{action} hook.
         *
         * The dispatcher itself carries no nonce ( every handler verifies its
         * own ), so it must not be able to fire arbitrary wpsl_* hooks such as
         * wpsl_init or wpsl_reopen_location from a crafted link.
         *
         * @since 3.0.0
         * @param array $actions Allowed action names, without the wpsl_ prefix.
         */
        $allowed_actions = apply_filters( 'wpsl_request_actions', [ 'import_settings', 'export_settings', 'download_status_report' ] );

        if ( ! in_array( $action, $allowed_actions, true ) ) {
            return;
        }

        do_action( 'wpsl_' . $action, $_REQUEST );
    }

    /**
     * Validate the save post action.
     *
     * @since 3.0.0
     */
    public function validate_save_post() {
        if ( ! isset( $_REQUEST['wpsl_validate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['wpsl_validate_nonce'] ) ), 'validate' ) ) {
            return wp_send_json_error();
        }

        $location     = isset( $_REQUEST['location'] ) ? wp_unslash( $_REQUEST['location'] ) : [];
        $run_geocoder = true;

        if ( ! isset( $location['id'] ) || ! is_numeric( $location['id'] ) ) {
            return wp_send_json_error( [ 'message' => 'Invalid location' ] );
        }

        if ( ! current_user_can( 'edit_store', $location['id'] ) ) {
            return wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
        }

        if ( get_post_type( $location['id'] ) !== 'wpsl_stores' ) {
            return wp_send_json_error( [ 'message' => 'Invalid post type' ] );
        }

        /**
         * If the user supplied both coordinates manually, make sure they are
         * within a valid range before continuing. Empty values are allowed,
         * since the address is geocoded to obtain them.
         */
        if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
            $location['lat'] = Location_Utils::sanitize_coordinate( $location['lat'], 'lat' );
            $location['lng'] = Location_Utils::sanitize_coordinate( $location['lng'], 'lng' );

            if ( ! Location_Utils::validate_latlng( $location['lat'], $location['lng'] ) ) {
                return wp_send_json_error( [ 'message' => esc_html__( 'The provided coordinates are out of range. Latitude must be between -90 and 90, and longitude between -180 and 180.', 'wp-store-locator' ) ] );
            }
        }

        // See if the address details have changed and we need to get new coordinates
        if ( get_post_meta( $location['id'], 'wpsl_address', true ) ) {
            $run_geocoder       = $this->get_geocode()->address_changed( $location['id'], $location );
            $location['recode'] = $run_geocoder;
        }

        // Check if we need to geocode the details.
        if ( $run_geocoder ) {
            $response = $this->get_geocode()->check_data( $location['id'], $location );
            
            // Ensure we have a valid response array
            if ( ! is_array( $response ) ) {
                $response = [ 'message' => __( 'Geocoding failed. Please check the address and try again.', 'wp-store-locator' ) ];
            }
            
            // If there's a message (warning/error), treat as failure so the editor shows it.
            // Otherwise, success depends on whether we got valid coordinates.
            $response['success'] = isset( $response['latlng'] ) && ! isset( $response['message'] );
        } else {
            $response = [ 'success' => true ];
        }

        /**
         * Return a fresh nonce so the editor can keep validating
         * saves on long-lived sessions where the original nonce
         * generated on page load may have expired.
         */
        $response['validate_nonce'] = wp_create_nonce( 'validate' );

        wp_send_json( $response );

        exit();
    }

    /**
     * Disable notices about the plugin settings.
     *
     * @todo move to class-notices?
     * @since 2.2.3
     * @return void
     */
    public function disable_setting_notices() {
        global $current_user;

        if ( isset( $_GET['wpsl-notice'] ) && isset( $_GET['_wpsl_notice_nonce'] ) ) {

            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpsl_notice_nonce'] ) ), 'wpsl_notices_nonce' ) ) {
                wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) );
            }

            $notice = sanitize_text_field( wp_unslash( $_GET['wpsl-notice'] ) );

            add_user_meta( $current_user->ID, 'wpsl_disable_' . $notice . '_warning', 'true', true );
        }
    }

    /**
     * Add link to the plugin action row.
     *
     * @since  2.0.0
     * @param  array  $links The existing action links
     * @param  string $file  The file path of the current plugin
     * @return array  $links The modified links
     */
    public function add_action_links( $links, $file ) {
        if ( strpos( $file, 'wp-store-locator.php' ) !== false ) {
            $settings_link = '<a href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) . '" title="View WP Store Locator Settings">' . esc_html__( 'Settings', 'wp-store-locator' ) . '</a>';
            array_unshift( $links, $settings_link );
        }

        return $links;
    }

    /**
     * Add links to the plugin meta row.
     *
     * @since  2.1.1
     * @param  array  $links The existing meta links
     * @param  string $file  The file path of the current plugin
     * @return array  $links The modified meta links
     */
    public function add_plugin_meta_row( $links, $file ) {
        if ( strpos( $file, 'wp-store-locator.php' ) !== false ) {
            $new_links = [
                '<a href="https://wpstorelocator.co/documentation/" title="View Documentation">'. esc_html__( 'Documentation', 'wp-store-locator' ).'</a>',
                '<a href="https://wpstorelocator.co/add-ons/" title="View Add-Ons">'. esc_html__( 'Add-Ons', 'wp-store-locator' ).'</a>'
            ];

            $links = array_merge( $links, $new_links );
        }

        return $links;
    }

    /**
     * Change the footer text on the settings page.
     *
     * @since  2.0.0
     * @param  string $text The current footer text
     * @return string $text Either the original or modified footer text
     */
    public function admin_footer_text( $text ) {
        $current_screen = get_current_screen();

        // Only modify the footer text if we are on the settings page of the wp store locator.
        if ( isset( $current_screen->id ) && $current_screen->id == 'wpsl_stores_page_wpsl_settings' ) {
            /* translators: 1: opening link and strong tags, 2: closing strong and link tags */
            $text = sprintf( esc_html__( 'If you like this plugin please leave us a %1$s5 star%2$s rating.', 'wp-store-locator' ), '<a href="https://wordpress.org/support/view/plugin-reviews/wp-store-locator?filter=5#postform" target="_blank"><strong>', '</strong></a>' );
        }

        return $text;
    }

    /**
     * Check if we need to show warnings after
     * the user installed the plugin.
     *
     * @since      1.0.0
     * @deprecated 3.0.0
     * @return     void
     */
    public function setting_warnings() {
        _deprecated_function( __FUNCTION__, '3.0.0' );
    }

    /**
    * Show the admin warnings
    *
    * @since      1.2.0
    * @deprecated 3.0.0
    * @return     void
    */
    public function show_warning() {
        _deprecated_function( __FUNCTION__, '3.0.0' );
    }

    /**
     * The text messages used in wpsl-admin.js.
     *
     * @since      1.2.20
     * @deprecated 3.0.0 Use Script_Data::instance()->get_l10n() instead.
     * @return     void
     */
    public static function admin_js_l10n() {
        _deprecated_function( __FUNCTION__, '3.0.0', 'Script_Data::instance()->get_l10n()' );
    }

    /**
     * Plugin settings that are used in the wpsl-admin.js.
     *
     * @since      2.0.0
     * @deprecated 3.0.0 Use Script_Data::instance()->get_settings() instead.
     * @return     void
     */
    public static function js_settings() {
        _deprecated_function( __FUNCTION__, '3.0.0', 'Script_Data::instance()->get_settings()' );
    }

    /**
     * Map the v2 WPSL_Admin public properties onto the v3 container services.
     *
     * The $wpsl_admin global still exists in 3.0 ( see wp-store-locator.php ),
     * but it now holds this controller instead of the old WPSL_Admin object.
     * Add-ons written against 2.x read $wpsl_admin->notices, ->metaboxes,
     * ->geocode and ->settings_page. None of those are public properties here,
     * so without this map reading them either warns ( undefined ) or throws
     * ( private ), and the method call that follows fatals on null.
     *
     * @since 3.0.0
     * @var   array
     */
    private static $v2_properties = [
        'notices'       => 'notices',
        'metaboxes'     => 'metaboxes',
        'geocode'       => 'geocode',
        'settings_page' => 'admin_settings',
    ];

    /**
     * Map the v2 WPSL_Admin methods that add-ons call onto their v3 service.
     *
     * The value is a [ service, method ] pair. Only methods that 2.x add-ons
     * actually call are listed - the rest of the old class was internal.
     *
     * @since 3.0.0
     * @var   array
     */
    private static $v2_methods = [
        'delete_autoload_transient' => [ 'system_utils', 'flush_autoload_transients' ],
    ];

    /**
     * Resolve a v2 property read to its v3 service.
     *
     * @since  3.0.0
     * @param  string $name The requested property name
     * @return mixed        The service instance, or null when unknown
     */
    public function __get( $name ) {
        $service = $this->get_v2_service_id( $name );

        if ( $service ) {
            return $this->container->get( $service );
        }

        $this->v2_compat_warning( sprintf( 'Undefined property $wpsl_admin->%s.', $name ) );

        return null;
    }

    /**
     * Report the mapped v2 properties as set.
     *
     * Without this isset( $wpsl_admin->notices ) returns false, since __get()
     * is not consulted by isset() / empty().
     *
     * @since  3.0.0
     * @param  string $name The requested property name
     * @return bool
     */
    public function __isset( $name ) {
        return (bool) $this->get_v2_service_id( $name );
    }

    /**
     * Forward a v2 method call to its v3 service.
     *
     * @since  3.0.0
     * @param  string $name      The called method name
     * @param  array  $arguments The passed arguments
     * @return mixed             The return value of the mapped method, or null
     */
    public function __call( $name, $arguments ) {
        if ( isset( self::$v2_methods[ $name ] ) && $this->container ) {
            list( $service, $method ) = self::$v2_methods[ $name ];

            if ( $this->container->has( $service ) ) {
                return call_user_func_array( [ $this->container->get( $service ), $method ], $arguments );
            }
        }

        $this->v2_compat_warning( sprintf( 'Undefined method $wpsl_admin->%s().', $name ) );

        return null;
    }

    /**
     * Look up the v3 service id for a v2 property name.
     *
     * @since  3.0.0
     * @param  string $name The v2 property name
     * @return string       The service id, or an empty string when unavailable
     */
    private function get_v2_service_id( $name ) {
        if ( ! isset( self::$v2_properties[ $name ] ) || ! $this->container ) {
            return '';
        }

        $service = self::$v2_properties[ $name ];

        return $this->container->has( $service ) ? $service : '';
    }

    /**
     * Flag an unmapped v2 call without taking the page down.
     *
     * Returning null keeps a legacy add-on degrading instead of fataling, while
     * _doing_it_wrong() reports the call on WP_DEBUG installs.
     *
     * @since  3.0.0
     * @param  string $message The notice to log
     * @return void
     */
    private function v2_compat_warning( $message ) {
        if ( function_exists( '_doing_it_wrong' ) ) {
            _doing_it_wrong( 'wpsl_admin', esc_html( $message ), '3.0.0' );
        }
    }
}