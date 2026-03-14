<?php
/**
 * Admin class
 *
 * @author Tijmen Smit
 * @since  1.0.0
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WPSL_Admin' ) ) {

    /**
     * Handle the backend of the store locator
     *
     * @since 1.0.0
     */
	class WPSL_Admin {

        /**
         * @since 2.0.0
         * @var WPSL_Metaboxes
         */
        public $metaboxes;

        private $setting_warning;

        /**
         * @since 2.0.0
         * @var WPSL_Geocode
         */
        public $geocode;

        /**
         * @since 2.0.0
         * @var WPSL_Notices
         */
        public $notices;

        /**
         * @since 2.0.0
         * @var WPSL_Settings
         */
        public $settings_page;

        /**
         * Class constructor
         */
		function __construct() {

            $this->includes();

            add_action( 'init',                                 array( $this, 'init' ) );
            add_action( 'admin_menu',                           array( $this, 'create_admin_menu' ) );
            add_action( 'admin_init',                           array( $this, 'setting_warnings' ) );
            add_action( 'delete_post',                          array( $this, 'maybe_delete_autoload_transient' ) );
            add_action( 'wp_trash_post',                        array( $this, 'maybe_delete_autoload_transient' ) );
            add_action( 'untrash_post',                         array( $this, 'maybe_delete_autoload_transient' ) );
            add_action( 'admin_enqueue_scripts',                array( $this, 'admin_scripts' ) );
            add_filter( 'plugin_row_meta',                      array( $this, 'add_plugin_meta_row' ), 10, 2 );
            add_filter( 'plugin_action_links_' . WPSL_BASENAME, array( $this, 'add_action_links' ), 10, 2 );
            add_filter( 'admin_footer_text',                    array( $this, 'admin_footer_text' ), 1 );
            add_action( 'wp_loaded',                            array( $this, 'disable_setting_notices' ) );

            add_action( 'wp_ajax_validate_server_key',          array( $this, 'ajax_validate_server_key' ) );
		}

        /**
         * @since 2.2.234
         * @return void
         */
		public function ajax_validate_server_key() {
            $this->settings_page->ajax_validate_server_key();
        }

        /**
         * Include the required files.
         *
         * @since 2.0.0
         * @return void
         */
        public function includes() {
            require_once( WPSL_PLUGIN_DIR . 'admin/class-shortcode-generator.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/class-notices.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/class-license-manager.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/class-metaboxes.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/class-geocode.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/class-settings.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/upgrade.php' );
            require_once( WPSL_PLUGIN_DIR . 'admin/data-export.php' );

            if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX  ) {
                require_once( WPSL_PLUGIN_DIR . 'admin/class-exit-survey.php' );
            }
		}

        /**
         * Init the classes.
         *
         * @since 2.0.0
         * @return void
         */
		public function init() {
            $this->notices       = new WPSL_Notices();
            $this->metaboxes     = new WPSL_Metaboxes();
            $this->geocode       = new WPSL_Geocode();
            $this->settings_page = new WPSL_Settings();
		}

        /**
         * Check if we need to show warnings after
         * the user installed the plugin.
         *
         * @since 1.0.0
         * @todo move to class-notices?
         * @return void
         */
		public function setting_warnings() {

            global $current_user, $wpsl_settings;

            $this->setting_warning = array();

            // The fields settings field to check for data.
            $warnings = array(
                'start_latlng'    => 'location',
                'api_browser_key' => 'key'
            );

            if ( ( current_user_can( 'install_plugins' ) ) && is_admin() ) {
                foreach ( $warnings as $setting_name => $warning ) {
                    if ( empty( $wpsl_settings[$setting_name] ) && !get_user_meta( $current_user->ID, 'wpsl_disable_' . $warning . '_warning' ) ) {
                        if ( $warning == 'key' ) {
                            /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: opening dismiss link tag, %4$s: closing link tag */
                            $this->setting_warning[$warning] = sprintf( __( 'You need to create %1$sAPI keys%2$s for Google Maps before you can use the store locator! %3$sDismiss%4$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/create-google-api-keys/">', '</a>', "<a href='" . esc_url( wp_nonce_url( add_query_arg( 'wpsl-notice', 'key' ), 'wpsl_notices_nonce', '_wpsl_notice_nonce' ) ) . "'>", "</a>" );
                        } else {
                            /* translators: %1$s: opening settings link tag, %2$s: closing link tag, %3$s: opening dismiss link tag, %4$s: closing link tag */
                            $this->setting_warning[$warning] = sprintf( __( 'Before adding the [wpsl] shortcode to a page, please don\'t forget to define a start point on the %1$ssettings%2$s page. %3$sDismiss%4$s', 'wp-store-locator' ), "<a href='" . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) . "'>", "</a>", "<a href='" . esc_url( wp_nonce_url( add_query_arg( 'wpsl-notice', 'location' ), 'wpsl_notices_nonce', '_wpsl_notice_nonce' ) ) . "'>", "</a>" );
                        }
                    }
                }

                if ( defined( 'WP_ROCKET_VERSION' ) && ! get_user_meta( $current_user->ID, 'wpsl_disable_wp_rocket_warning' ) ) {
                    /* translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: line break, %4$s: opening strong tag, %5$s: closing strong tag, %6$s: opening dismiss link tag, %7$s: closing link tag */
                    $this->setting_warning['wp_rocket'] = sprintf( __( '%1$sWP Store Locator:%2$s To prevent any conflicts the required JavaScript files are automatically excluded from WP Rocket. %3$s If the store locator map still breaks, then make sure to flush the cache by going to %4$sWP Rocket -> Clear and preload cache%5$s. %6$sDismiss%7$s', 'wp-store-locator' ), '<strong>', '</strong>', '<br><br>', '<strong>', '</strong>', "<a href='" . esc_url( wp_nonce_url( add_query_arg( 'wpsl-notice', 'wp_rocket' ), 'wpsl_notices_nonce', '_wpsl_notice_nonce' ) ) . "'>", "</a>" );
                }
                
                // Show WP Store Locator 3.0 beta notice
                $v3_beta_dismissed = get_user_meta( $current_user->ID, 'wpsl_disable_v3_beta_warning', true );
                
                if ( ! $v3_beta_dismissed ) {
                    /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: opening dismiss link tag, %4$s: closing link tag */
                    $this->setting_warning['v3_beta'] = sprintf( __( 'Interested in getting notified when the beta version for WP Store Locator 3.0 is released? %1$sClick here%2$s. %3$sDismiss%4$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/update-on-wp-store-locator-3-0/" target="_blank">', '</a>', "<a href='" . esc_url( wp_nonce_url( add_query_arg( 'wpsl-notice', 'v3_beta' ), 'wpsl_notices_nonce', '_wpsl_notice_nonce' ) ) . "'>", "</a>" );
                }

                if ( $this->setting_warning ) {
                    add_action( 'admin_notices', array( $this, 'show_warning' ) );
                }
            }
		}

       /**
        * Show the admin warnings
        *
        * @since 1.2.0
        * @return void
        */
        public function show_warning() {
            foreach ( $this->setting_warning as $k => $warning ) {
                echo '<div id="message" class="error"><p>' . wp_kses_post( $warning ) . '</p></div>';
            }
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
                
                $meta_key = 'wpsl_disable_' . $notice . '_warning';
                
                update_user_meta( $current_user->ID, $meta_key, 'true' );
                
                // Redirect to remove query parameters from URL
                wp_safe_redirect( remove_query_arg( array( 'wpsl-notice', '_wpsl_notice_nonce' ) ) );
                exit;
            }
        }

        /**
         * Add the admin menu pages.
         *
         * @since 1.0.0
         * @return void
         */
		public function create_admin_menu() {

            $sub_menus = apply_filters( 'wpsl_sub_menu_items', array(
                    array(
                        'page_title'  => __( 'Settings', 'wp-store-locator' ),
                        'menu_title'  => __( 'Settings', 'wp-store-locator' ),
                        'caps'        => 'manage_wpsl_settings',
                        'menu_slug'   => 'wpsl_settings',
                        'function'    => array( $this, 'load_template' )
                    ),
                    array(
                        'page_title'  => __( 'Add-Ons', 'wp-store-locator' ),
                        'menu_title'  => __( 'Add-Ons', 'wp-store-locator' ),
                        'caps'        => 'manage_wpsl_settings',
                        'menu_slug'   => 'wpsl_add_ons',
                        'function'    => array( $this, 'load_template' )
                    )
                )
            );

            if ( count( $sub_menus ) ) {
                foreach ( $sub_menus as $sub_menu ) {
                    add_submenu_page( 'edit.php?post_type=wpsl_stores', $sub_menu['page_title'], $sub_menu['menu_title'], $sub_menu['caps'], $sub_menu['menu_slug'], $sub_menu['function'] );
                }
            }
        }

        /**
         * Load the correct page template.
         *
         * @since 2.1.0
         * @return void
         */
        public function load_template() {

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking which admin page to load, not processing form data
            $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

            switch ( $page ) {
                case 'wpsl_settings':
                    require 'templates/map-settings.php';
                break;
                case 'wpsl_add_ons':
                    require 'templates/add-ons.php';
                break;
            }
        }

        /**
         * Check if we need to delete the autoload transient.
         *
         * This is called when a post it saved, deleted, trashed or untrashed.
         *
         * @since 2.0.0
         * @return void
         */
        public function maybe_delete_autoload_transient( $post_id ) {

            global $wpsl_settings;

            if ( isset( $wpsl_settings['autoload'] ) && $wpsl_settings['autoload'] && get_post_type( $post_id ) == 'wpsl_stores' ) {
				$this->delete_autoload_transient();
            }
        }

        /**
         * Delete the transients that are used on the front-end
         * if the autoload option is enabled.
         *
         * The transient names used by the store locator are partly dynamic.
         * They always start with wpsl_autoload_, followed by the number of
         * stores to load and ends with the language code.
         *
         * So you get wpsl_autoload_20_de if the language is set to German
         * and 20 stores are set to show on page load.
         *
         * The language code has to be included in case a multilingual plugin is used.
         * Otherwise it can happen the user switches to Spanish,
         * but ends up seeing the store data in the wrong language.
         *
         * @since 2.0.0
         * @return void
         */
        public function delete_autoload_transient() {

            global $wpdb;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct query to find and delete transients, caching not applicable
            $option_names = $wpdb->get_results( $wpdb->prepare( "SELECT option_name AS transient_name FROM " . esc_sql( $wpdb->options ) . " WHERE option_name LIKE %s", '\_transient\_wpsl\_autoload\_%' ) );

            if ( $option_names ) {
                foreach ( $option_names as $option_name ) {
                    $transient_name = str_replace( "_transient_", "", $option_name->transient_name );

                    delete_transient( $transient_name );
                }
            }
        }

        /**
         * Check if we can use a font for the plugin icon.
         *
         * This is supported by WP 3.8 or higher
         *
         * @since 1.0.0
         * @return void
         */
        private function check_icon_font_usage() {

            global $wp_version;

            if ( ( version_compare( $wp_version, '3.8', '>=' ) == TRUE ) ) {
                $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

                wp_enqueue_style( 'wpsl-admin-38', plugins_url( '/css/style-3.8'. $min .'.css', __FILE__ ), array(), WPSL_VERSION_NUM );
            }
        }

        /**
         * The text messages used in wpsl-admin.js.
         *
         * @since 1.2.20
         * @return array $admin_js_l10n The texts used in the wpsl-admin.js
         */
        public function admin_js_l10n() {

            global $wpsl_settings;

            $admin_js_l10n = array(
                'noAddress'         => __( 'Cannot determine the address at this location.', 'wp-store-locator' ),
                'geocodeFail'       => __( 'Geocode was not successful for the following reason', 'wp-store-locator' ),
                'securityFail'      => __( 'Security check failed, reload the page and try again.', 'wp-store-locator' ),
                'requiredFields'    => __( 'Please fill in all the required store details.', 'wp-store-locator' ),
                'missingGeoData'    => __( 'The map preview requires all the location details.', 'wp-store-locator' ),
                'closedDate'        => __( 'Closed', 'wp-store-locator' ),
                'styleError'        => __( 'The code for the map style is invalid.', 'wp-store-locator' ),
                'dismissNotice'     => __( 'Dismiss this notice.', 'wp-store-locator' ),
                /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: line break, %4$s: opening console link tag, %5$s: closing link tag, %6$s-%15$s: keyboard shortcuts markup, %16$s-%17$s: line breaks, %18$s: opening troubleshooting link tag, %19$s: closing link tag */
                'browserKeyError'   => sprintf( __( 'There\'s a problem with the provided %1$sbrowser key%2$s. %3$s You will have to open the %4$sbrowser console%5$s ( %6$sctrl%7$s %8$sshift%9$s %10$sk%11$s in Firefox, or %12$sctrl%13$s %14$sshift%15$s %16$sj%17$s in Chrome ) to see the error details returned by the Google Maps API. %18$s The error itself includes a link explaining the problem in more detail. %19$s Common API errors are also covered in the %20$stroubleshooting section%21$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">','</a>', '<br><br>', '<a target="_blank" href="https://codex.wordpress.org/Using_Your_Browser_to_Diagnose_JavaScript_Errors#Step_3:_Diagnosis">', '</a>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<br><br>', '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#api-errors">', '</a>' ),
                'browserKeySuccess' => __( 'No problems found with the browser key.', 'wp-store-locator' ),
                'serverKey'         => __( 'Server key', 'wp-store-locator' ),
                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                'serverKeyMissing'  => sprintf( __( 'No %1$sserver key%2$s found!', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#server-key">', '</a>' ),
                'browserKey'        => __( 'Browser key', 'wp-store-locator' ),
                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                'browserKeyMissing' => sprintf( __( 'No %1$sbrowser key%2$s found!', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">', '</a>' ),
                'restrictedZipCode' => __( 'and will only work for zip codes.', 'wp-store-locator' ),
                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                'noRestriction'     => sprintf( __( 'because no %1$smap region%2$s is selected the geocode API will search for matching results around the world. This may result in unexpected results.', 'wp-store-locator' ), '<a class="wpsl-region-href" href="#wpsl-tabs">', '</a>' ),
                /* translators: %1$s: opening billing link tag, %2$s: closing link tag, %3$s: opening account link tag, %4$s: closing link tag, %5$s: line break, %6$s: opening support link tag, %7$s: closing link tag */
                'loadingError'      => sprintf( __( 'Google Maps didn\'t load correctly. Make sure you have an active %1$sbilling%2$s %3$saccount%4$s for Google Maps. %5$s If the "For development purposes only" text keeps showing after creating a billing account, then you will have to contact %6$sGoogle Billing Support%7$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#billing">', '</a>', '<a href="http://g.co/dev/maps-no-account">', '</a>', '<br><br>', '<a target="_blank" href="https://cloud.google.com/support/billing/">', '</a>' ),
                /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: line break, %4$s: opening console link tag, %5$s: closing link tag, %6$s-%15$s: keyboard shortcuts markup, %16$s-%17$s: line breaks, %18$s: opening troubleshooting link tag, %19$s: closing link tag */
                'loadingFailed'     => sprintf( __( 'Google Maps failed to load correctly. This is likely due to a problem with the provided %1$sbrowser key%2$s. %3$s You will have to open the %4$sbrowser console%5$s ( %6$sctrl%7$s %8$sshift%9$s %10$sk%11$s in Firefox, or %12$sctrl%13$s %14$sshift%15$s %16$sj%17$s in Chrome ) to see the error details returned by the Google Maps API. %18$s The error itself includes a link explaining the problem in more detail. %19$s Common API errors are also covered in the %20$stroubleshooting section%21$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">','</a>', '<br><br>', '<a target="_blank" href="https://codex.wordpress.org/Using_Your_Browser_to_Diagnose_JavaScript_Errors#Step_3:_Diagnosis">', '</a>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<kbd>', '</kbd>', '<kbd>', '</kbd>','<kbd>', '</kbd>', '<br><br>', '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#api-errors">', '</a>' ),
                'close'             => __( 'Close', 'wp-store-locator' ),
            );

            /**
             * This text is only shown when the user checks the API response
             * for a provided address ( tools section ), and a map region is selected.
             */
            if ( $wpsl_settings['api_region'] ) {
                if ( $wpsl_settings['api_geocode_component'] ) {
                    $restriction_type = 'restricted';
                } else {
                    $restriction_type = 'biased';
                }

                /* translators: %s: restriction type (restricted or biased) */
                $admin_js_l10n['resultsWarning'] = sprintf( __( 'with the current settings the results are %s to', 'wp-store-locator' ), $restriction_type );
            }

            return $admin_js_l10n;
        }

        /**
         * Plugin settings that are used in the wpsl-admin.js.
         *
         * @since 2.0.0
         * @return array $settings_js The settings used in the wpsl-admin.js
         */
        public function js_settings() {

            global $wpsl_settings;

            $js_settings = array(
                'hourFormat'     => $wpsl_settings['editor_hour_format'],
                'defaultLatLng'  => $this->get_default_lat_lng(),
                'defaultZoom'    => 6,
                'mapType'        => $wpsl_settings['editor_map_type'],
                'requiredFields' => array( 'address', 'city', 'country' ),
                'ajaxurl'        => wpsl_get_ajax_url(),
                'url'            => WPSL_URL,
                'storeMarker'    => $wpsl_settings['store_marker'],
                'validateKeyNonce' => wp_create_nonce( 'wpsl_validate_server_key' )
            );

            // Make sure that the Geocode API testing tool correctly restricts the results if required.
            if ( $wpsl_settings['api_region'] && $wpsl_settings['api_geocode_component'] ) {
                $geocode_components = array();
                $geocode_components['country'] = strtoupper( $wpsl_settings['api_region'] );

                if ( $wpsl_settings['force_postalcode'] ) {
                    $geocode_components['postalCode'] = '';
                }

                $js_settings['geocodeComponents'] = $geocode_components;
            }

            return apply_filters( 'wpsl_admin_js_settings', $js_settings );
        }

        /**
         * Get the coordinates that are used to
         * show the map on the settings page.
         *
         * @since 2.2.5
         * @return string $startLatLng The start coordinates
         */
        public function get_default_lat_lng() {

            global $wpsl_settings;

            $startLatLng = $wpsl_settings['start_latlng'];

            // If no start coordinates exists, then set the default to Holland.
            if ( !$startLatLng ) {
                $startLatLng = '52.378153,4.899363';
            }

            return $startLatLng;
        }

        /**
         * Add the required admin script.
         *
         * @since 1.0.0
         * @return void
         */
		public function admin_scripts() {

            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

            // Always load the main js admin file to make sure the "dismiss" link in the location notice works.
            wp_enqueue_script( 'wpsl-admin-js', plugins_url( '/js/wpsl-admin'. $min .'.js', __FILE__ ), array( 'jquery' ), WPSL_VERSION_NUM, true );

            $this->maybe_show_pointer();
            $this->check_icon_font_usage();

            // Only enqueue the rest of the css/js files if we are on a page that belongs to the store locator.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking which admin page we're on, not processing form data
            if ( ( get_post_type() == 'wpsl_stores' ) || ( isset( $_GET['post_type'] ) && ( sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) == 'wpsl_stores' ) ) ) {

                // Make sure no other Google Map scripts can interfere with the one from the store locator.
                wpsl_deregister_other_gmaps();

                wp_enqueue_style( 'wp-jquery-ui-dialog' );
                wp_enqueue_style( 'wpsl-admin-css', plugins_url( '/css/style'. $min .'.css', __FILE__ ), array(), WPSL_VERSION_NUM );

                wp_enqueue_media();
                wp_enqueue_script( 'jquery-ui-dialog' );
                wp_enqueue_script( 'jquery-ui-tabs' );
                wp_enqueue_script( 'wpsl-gmap', ( 'https://maps.google.com/maps/api/js' . wpsl_get_gmap_api_params( 'browser_key' ) ), false, WPSL_VERSION_NUM, true );

                wp_enqueue_script( 'wpsl-queue', plugins_url( '/js/ajax-queue'. $min .'.js', __FILE__ ), array( 'jquery' ), WPSL_VERSION_NUM, true );
                wp_enqueue_script( 'wpsl-retina', plugins_url( '/js/retina'. $min .'.js', __FILE__ ), array( 'jquery' ), WPSL_VERSION_NUM, true );

                wp_localize_script( 'wpsl-admin-js', 'wpslL10n',     $this->admin_js_l10n() );
                wp_localize_script( 'wpsl-admin-js', 'wpslSettings', $this->js_settings() );
            }
        }

        /**
         * Check if we need to show the wpsl pointer.
         *
         * @since 2.0.0
         * @return void
         */
        public function maybe_show_pointer() {

            $disable_pointer = apply_filters( 'wpsl_disable_welcome_pointer', false );

            if ( $disable_pointer ) {
                return;
            }

            $dismissed_pointers = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

            // If the user hasn't dismissed the wpsl pointer, enqueue the script and style, and call the action hook.
            if ( !in_array( 'wpsl_signup_pointer', $dismissed_pointers ) ) {
                wp_enqueue_style( 'wp-pointer' );
                wp_enqueue_script( 'wp-pointer' );

                add_action( 'admin_print_footer_scripts', array( $this, 'welcome_pointer_script' ) );
            }
        }

        /**
         * Add the script for the welcome pointer.
         *
         * @since 2.0.0
         * @return void
         */
        public function welcome_pointer_script() {

            $pointer_content = '<h3>' . __( 'Welcome to WP Store Locator', 'wp-store-locator' ) . '</h3>';
            $pointer_content .= '<p>' . __( 'Sign up for the latest plugin updates and announcements.', 'wp-store-locator' ) . '</p>';
            $pointer_content .= '<div id="mc_embed_signup" class="wpsl-mc-wrap" style="padding:0 15px; margin-bottom:13px;"><form action="//wpstorelocator.us10.list-manage.com/subscribe/post?u=34e4c75c3dc990d14002e19f6&amp;id=4be03427d7" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate><div id="mc_embed_signup_scroll"><input type="email" value="" name="EMAIL" class="email" id="mce-EMAIL" placeholder="email address" required style="margin-right:5px;width:230px;"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button"><div style="position: absolute; left: -5000px;"><input type="text" name="b_34e4c75c3dc990d14002e19f6_4be03427d7" tabindex="-1" value=""></div></div></form></div>';
            ?>

            <script type="text/javascript">
			//<![CDATA[
			jQuery( document ).ready( function( $ ) {
                $( '#menu-posts-wpsl_stores' ).pointer({
                    content: <?php echo wp_json_encode( $pointer_content ); ?>,
                    position: {
                        edge: 'left',
                        align: 'center'
                    },
                    pointerWidth: 350,
                    close: function () {
                        $.post( ajaxurl, {
                            pointer: 'wpsl_signup_pointer',
                            action: 'dismiss-wp-pointer'
                        });
                    }
                }).pointer( 'open' );

                // If a user clicked the "subscribe" button trigger the close button for the pointer.
                $( ".wpsl-mc-wrap #mc-embedded-subscribe" ).on( "click", function() {
                    $( ".wp-pointer .close" ).trigger( "click" );
                });
            });
            //]]>
            </script>

            <?php
        }

        /**
         * Add link to the plugin action row.
         *
         * @since 2.0.0
         * @param  array  $links The existing action links
         * @param  string $file  The file path of the current plugin
         * @return array  $links The modified links
         */
        public function add_action_links( $links, $file ) {

            if ( strpos( $file, 'wp-store-locator.php' ) !== false ) {
                $settings_link = '<a href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) . '" title="View WP Store Locator Settings">' . __( 'Settings', 'wp-store-locator' ) . '</a>';
                array_unshift( $links, $settings_link );
            }

            return $links;
        }

        /**
         * Add links to the plugin meta row.
         *
         * @since 2.1.1
         * @param  array  $links The existing meta links
         * @param  string $file  The file path of the current plugin
         * @return array  $links The modified meta links
         */
        public function add_plugin_meta_row( $links, $file ) {

            if ( strpos( $file, 'wp-store-locator.php' ) !== false ) {
                $new_links = array(
                    '<a href="https://wpstorelocator.co/documentation/" title="View Documentation">'. __( 'Documentation', 'wp-store-locator' ).'</a>',
                    '<a href="https://wpstorelocator.co/add-ons/" title="View Add-Ons">'. __( 'Add-Ons', 'wp-store-locator' ).'</a>'
                );

                $links = array_merge( $links, $new_links );
            }

            return $links;
        }

        /**
         * Change the footer text on the settings page.
         *
         * @since 2.0.0
         * @param  string $text The current footer text
         * @return string $text Either the original or modified footer text
         */
        public function admin_footer_text( $text ) {

            $current_screen = get_current_screen();

            // Only modify the footer text if we are on the settings page of the wp store locator.
            if ( isset( $current_screen->id ) && $current_screen->id == 'wpsl_stores_page_wpsl_settings' ) {
                /* translators: %1$s: opening link and strong tag, %2$s: closing strong and link tag */
                $text = sprintf( __( 'If you like this plugin please leave us a %1$s5 star%2$s rating.', 'wp-store-locator' ), '<a href="https://wordpress.org/support/view/plugin-reviews/wp-store-locator?filter=5#postform" target="_blank"><strong>', '</strong></a>' );
            }

            return $text;
        }
    }

	$GLOBALS['wpsl_admin'] = new WPSL_Admin();
}