<?php
/**
 * Handle the WPSL add-on license and updates in combination with
 * Easy Digital Downloads.
 *
 * @author Tijmen Smit
 * @since  2.1.0
 */

namespace WPSL\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class License_Manager {
    
    /**
     * Singleton instance
     *
     * @var \WPSL\Admin\Tools\License_Manager
     */
    private static $instance = null;
    
    public $item_name;
    public $item_shortname;
    public $item_id;
    public $version;
    public $author;
    public $file;
    public $plugin_base;
    public $api_url = 'https://wpstorelocator.co/';
        
    /**
     * Class constructor
     *
     * @since 2.1.0
     * @param string      $item_name     Product name
     * @param string      $version       Plugin version
     * @param string      $author        Plugin author name
     * @param string      $file          Plugin file path
     * @param int|bool    $item_id       Optional EDD product ID. Preferred over item_name for API requests when provided.
     */
    public function __construct( $item_name = null, $version= null, $author = null, $file = null, $item_id = false ) {
        if ( ! empty( $item_name ) ) {
            $this->item_name      = $item_name;
            $this->item_shortname = $this->create_shortname();
        }

        $this->item_id = $item_id;

        if ( ! empty( $version ) && ! empty( $author ) && ! empty( $file ) ) {
            $this->version     = $version;
            $this->author      = $author;
            $this->file        = $file;
            $this->plugin_base = plugin_basename( $this->file );

            $this->includes();
            $this->init();
        }
    }

    /**
     * Get the singleton instance
     *
     * @since  3.0.0
     * @return License_Manager
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Initialize the license manager.
     *
     * @since  3.0.0
     * @return void
     */
    public function init() {
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'auto_updater' ], 0 );
        } elseif ( \wpsl_doing_background_update_check() ) {
            add_action( 'init', [ $this, 'auto_updater' ], 0 );
        }

        add_action( 'admin_init',                                     [ $this, 'license_actions' ] );
        add_filter( 'wpsl_license_settings',                          [ $this, 'add_license_field' ], 1 );
        add_action( 'in_plugin_update_message-' . $this->plugin_base, [ $this, 'extend_plugin_update_msg' ], 10, 2 );
        add_action( 'admin_notices',                                  [ __CLASS__, 'suppress_edd_license_notices' ], 0 );
    }

    /**
     * Suppress the EDD "invalid or expired license keys" admin notice.
     *
     * WPSL manages its own license UI, so the generic EDD notice
     * is redundant and confusing for users.
     *
     * @since 3.0.0
     * @return void
     */
    public static function suppress_edd_license_notices() {
        static $done = false;

        if ( $done ) {
            return;
        }
        $done = true;

        global $wp_filter;

        if ( empty( $wp_filter['admin_notices'] ) ) {
            return;
        }

        foreach ( $wp_filter['admin_notices']->callbacks as $priority => $hooks ) {
            foreach ( $hooks as $key => $hook ) {
                if ( ! is_array( $hook['function'] ) || empty( $hook['function'][0] ) || ! is_object( $hook['function'][0] ) ) {
                    continue;
                }

                $class = get_class( $hook['function'][0] );

                if (
                    ( 'EDD_License' === $class || 'EDD\\Extensions\\Handler' === $class )
                    && 'notices' === $hook['function'][1]
                ) {
                    unset( $wp_filter['admin_notices']->callbacks[ $priority ][ $key ] );
                }
            }
        }
    }

    /**
     * Include the updater class
     *
     * The EDD SDK is bundled once here instead of in every add-on. Loading its
     * bootstrap registers the composer autoloader, so the SDK updater classes
     * resolve immediately for whichever add-on constructs a License_Manager.
     *
     * @since  2.1.0
     * @since  3.0.0 Load the bundled EDD SDK, add-ons no longer ship their own copy.
     * @access private
     * @return void
     */
    private function includes() {
        if ( file_exists( WPSL_PLUGIN_DIR . 'includes/admin/vendor/edd-sl-sdk/edd-sl-sdk.php' ) ) {
            require_once WPSL_PLUGIN_DIR . 'includes/admin/vendor/edd-sl-sdk/edd-sl-sdk.php';
        }

        // Only load the legacy updater if the SDK updater class is not available.
        if ( ! class_exists( 'EasyDigitalDownloads\Updater\Updaters\Plugin' ) ) {
            if ( ! class_exists( 'EDD_WPSL_SL_Plugin_Updater' ) ) {
                require_once WPSL_PLUGIN_DIR . 'includes/admin/EDD_SL_Plugin_Updater.php';
            }
        }
    }

    /**
     * Create a shortname for the license.
     *
     * @since  3.0.0
     * @return string The shortname
     */
    private function create_shortname() {
        return 'wpsl_' . preg_replace( '/[^a-zA-Z0-9_\s]/', '', str_replace( ' ', '_', strtolower( $this->item_name ) ) );
    }
    
    /**
     * Handle the add-on updates.
     * 
     * @since  2.1.0
     * @return void
     */
    public function auto_updater() {
        $license_key = $this->get_license_key();

        // Without a key there is nothing to authenticate the update request with.
        if ( empty( $license_key ) ) {
            return;
        }

        /*
         * Updates are not tied to the support period, so an expired license keeps
         * receiving them. 'unknown' means the license API was unreachable, which
         * shouldn't silently stop updates for a paying customer either.
         */
        if ( ! in_array( $this->get_license_option( 'status' ), [ 'valid', 'expired', 'unknown' ], true ) ) {
            return;
        }

        // Use the SDK updater when available, otherwise fall back to the legacy updater.
        if ( class_exists( 'EasyDigitalDownloads\Updater\Updaters\Plugin' ) ) {
            $args = [
                'file'    => $this->file,
                'version' => $this->version,
                'license' => $license_key,
            ];

            if ( ! empty( $this->item_id ) ) {
                $args['item_id'] = $this->item_id;
            } else {
                $args['item_name'] = $this->item_name;
            }

            new \EasyDigitalDownloads\Updater\Updaters\Plugin(
                $this->api_url,
                $args
            );
        } else {
            $args = [
                'version'   => $this->version,
                'license'   => $license_key,
                'author'    => $this->author,
                'item_name' => $this->item_name,
            ];

            if ( ! empty( $this->item_id ) ) {
                $args['item_id'] = $this->item_id;
            }

            new \EDD_WPSL_SL_Plugin_Updater(
                $this->api_url,
                $this->file,
                $args
            );
        }
    }
    
    /**
     * Check which license actions to take.
     * 
     * @since  2.1.0
     * @return void
     */
    public function license_actions() {
        if ( ! isset( $_POST['wpsl_licenses'] ) ) {
            return;
        }
        
        $licenses = isset( $_POST['wpsl_licenses'] ) ? wp_unslash( $_POST['wpsl_licenses'] ) : [];

        if ( ! isset( $licenses[ $this->item_shortname ] ) || empty( $licenses[ $this->item_shortname ] ) ) {
            return;
        }
        
        if ( ! check_admin_referer( $this->item_shortname . '_license-nonce', $this->item_shortname . '_license-nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return;
        }

        if ( isset( $_POST[ $this->item_shortname . '_license_key_deactivate' ] ) ) {
            $this->deactivate_license();
        } else {
            $this->activate_license();
        }
    }

    /**
     * Add the license field to the settings page.
     *
     * @since  2.1.0
     * @param  array $settings The existing settings.
     * @return array
     */
    public function add_license_field( $settings ) {
        $license_details[] = $this->get_license_details();

        return array_merge( $settings, $license_details );
    }

    /**
     * Try to activate the license key.
     * 
     * @since  2.1.0
     * @return void
     */
    private function activate_license() {
        // Stop if the current license is already active. 
        if ( $this->get_license_option( 'status' ) == 'valid' ) {
            return;
        }

        $licenses = isset( $_POST['wpsl_licenses'] ) ? wp_unslash( $_POST['wpsl_licenses'] ) : [];
        $license = isset( $licenses[ $this->item_shortname ] ) ? sanitize_text_field( $licenses[ $this->item_shortname ] ) : '';

        // data to send in our API request.
        $api_params = [
            'edd_action'  => 'activate_license',
            'license'     => $license,
            'url'         => home_url(),
            'environment' => wp_get_environment_type()
        ];

        $api_params += $this->get_item_identifier();

        // Get the license data from the API.
        $license_data = $this->call_license_api( $api_params );

        if ( $license_data ) {
            /*
             * An expired license comes back as a failed activation, but it still
             * entitles the site to updates — only support has run out. Store it
             * like any other working key instead of rejecting it.
             */
            $expired = $this->is_expired_response( $license_data );

            if ( ! empty( $license_data->success ) || $expired ) {
                update_option( $this->item_shortname . '_license_key', $license, false );

                $license_details = $this->set_license_transient( $license_data );

                $message = $this->item_name . ' ' . __( 'license activated.', 'wp-store-locator' );

                if ( 'expired' === $license_details['support'] ) {
                    $message .= ' ' . ( isset( $license_data->support_message )
                        ? $license_data->support_message
                        : __( 'Your support has expired. Renew it to regain access to support.', 'wp-store-locator' ) );
                }

                $this->set_license_notice( wp_kses_post( $message ), 'updated' );
            } else if ( ! empty( $license_data->error ) ) {
                $this->handle_activation_errors( $license_data );
            }
        }
    }

    /**
     * Deactivate the license key.
     * 
     * @since  2.1.0
     * @return void
     */    
    private function deactivate_license() {
        // Data to send to the API
        $api_params = [
            'edd_action' => 'deactivate_license',
            'license'    => $this->get_license_key(),
            'url'        => home_url()
        ];

        $api_params += $this->get_item_identifier();

        // Get the license data from the API.
        $license_data = $this->call_license_api( $api_params );
        
        if ( $license_data ) {
            $this->clear_stored_license();
        }

        wp_safe_redirect( esc_url_raw( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings&tab=licenses' ) ) );
        exit;
    }
    
    /**
     * Access the license API.
     *
     * @since  2.1.0
     * @param  array       $api_params   The used API parameters
     * @param  bool        $silent       Suppress the admin notice when the API request itself fails.
     *                                   Used for background checks where a connection error
     *                                   ( offline, DNS failure ) isn't actionable for the user.
     * @return object|null $license_data The decoded license data on success
     */
    private function call_license_api( $api_params, $silent = false ) {
        $response = wp_remote_post(
            $this->api_url,
            [
                'timeout'   => 15,
                'sslverify' => apply_filters( 'https_ssl_verify', true, $this->api_url ),
                'body'      => $api_params
            ]
        );

        // Make sure the response came back okay.
        if ( is_wp_error( $response ) ) {
            if ( ! $silent ) {
                $this->set_license_api_notice( $response );
            }
        } else {
            $license_data = json_decode( wp_remote_retrieve_body( $response ) );        

            return $license_data;
        }
    }    

    /**
     * Forget everything stored about this add-on's license.
     *
     * The legacy '_license_data' option is normally kept for a downgrade
     * path, but get_license_key() would promote it straight back after an
     * explicit deactivation, so it has to go too.
     *
     * @since 3.0.0
     * @return void
     */
    private function clear_stored_license() {
        delete_option( $this->item_shortname . '_license_key' );
        delete_option( $this->item_shortname . '_license_data' );
        delete_transient( $this->item_shortname . '_license_details' );
    }

    /**
     * Return the stored license key, healing pre-3.0 storage on the way.
     *
     * 2.x stored the key in '{shortname}_license_data'; 3.0 moved it to
     * '{shortname}_license_key'. The migration copies it once, but a key
     * written by 2.x afterward (rollback, or an early beta) is left stranded
     * and the add-on silently stops getting updates. This promotes the legacy
     * value the first time it's needed and writes it forward, so the fallback
     * costs one extra lookup, once.
     *
     * @since  3.0.0
     * @return string The license key, or an empty string.
     */
    private function get_license_key() {
        $license_key = get_option( $this->item_shortname . '_license_key' );

        if ( ! empty( $license_key ) ) {
            return $license_key;
        }

        $legacy = get_option( $this->item_shortname . '_license_data' );

        if ( empty( $legacy['key'] ) ) {
            return '';
        }

        update_option( $this->item_shortname . '_license_key', $legacy['key'], false );

        /*
         * Any cached status was checked without a key, so it reads 'invalid'
         * and auto_updater() would keep bailing on it for the rest of the
         * week. Drop it so the very next read re-checks with the key that
         * just arrived.
         */
        delete_transient( $this->item_shortname . '_license_details' );

        return $legacy['key'];
    }

    /**
     * Get a single license option.
     *
     * @since  2.1.0
     * @param  string      $option Name of the license option.
     * @return void|string         The value for the license option.
     */
    private function get_license_option( $option ) {
        $license_details = $this->get_license_details();

        if ( isset( $license_details[ $option ] ) ) {
            return $license_details[ $option ];
        }
    }

    /**
     * Set a notice holding license information.
     *
     * @since  2.1.0
     * @param  string $message The license message to display.
     * @param  string $type    Either updated or error.
     * @return void
     */
    private function set_license_notice( $message, $type ) {
        add_settings_error( $this->item_shortname . '-license', 'license-notice', $message, $type );
    }

    /**
     * Set a notice for a failed license API connection.
     *
     * All add-ons hit the same API endpoint, so a connection failure is
     * queued once ( instead of once per add-on ), and only on the licenses
     * tab where it's relevant to the user.
     *
     * @since 3.0.0
     * @param  \WP_Error $error The error returned by wp_remote_post().
     * @return void
     */
    private function set_license_api_notice( $error ) {
        if ( ! $this->is_licenses_page() ) {
            return;
        }

        foreach ( get_settings_errors( 'wpsl-license' ) as $notice ) {
            if ( 'license-api-error' === $notice['code'] ) {
                return;
            }
        }

        $message = $error->get_error_message() . '. ' . esc_html__( 'Please try again later.', 'wp-store-locator' );

        add_settings_error( 'wpsl-license', 'license-api-error', $message, 'error' );
    }

    /**
     * Check if the current request is for the licenses tab
     * on the settings page.
     *
     * @since 3.0.0
     * @return bool
     */
    private function is_licenses_page() {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

        return 'wpsl_settings' === $page && 'licenses' === $tab;
    }

    /**
     * Check the different license activation errors.
     * 
     * @since  2.1.0
     * @param  string $license_data The error data returned by the license API.
     * @return void
     */     
    private function handle_activation_errors( $license_data ) {
        switch ( $license_data->error ) {
            case 'expired' :
                /* translators: %s: expiration date */
                $message = sprintf( esc_html__( 'Your license key expired on %s.', 'wp-store-locator' ), date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) ) );
                break;
            case 'disabled' :
            case 'revoked' :
                $message = esc_html__( 'Your license key has been disabled.', 'wp-store-locator' );
                break;
            case 'missing' :
                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                $message = wp_kses_post( sprintf( __( 'Please provide a valid %1$slicense key%2$s. Required for add-on updates and support.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/account/#license-keys">', '</a>' ) );
                break;
            case 'invalid' :
            case 'site_inactive' :
                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                $message = wp_kses_post( sprintf( __( 'Your license is not active for this URL. %1$sFind your license keys%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/account/#license-keys">', '</a>' ) );
                break;
            case 'item_name_mismatch' :
                /* translators: %1$s: item name, %2$s: opening link tag, %3$s: closing link tag */
                $message = wp_kses_post( sprintf( __( 'This appears to be an invalid license key for %1$s. %2$sFind your license keys%3$s.', 'wp-store-locator' ), $this->item_name, '<a target="_blank" href="https://wpstorelocator.co/account/#license-keys">', '</a>' ) );
                break;
            case 'no_activations_left':
                $message = esc_html__( 'Your license key has reached its activation limit.', 'wp-store-locator' );
                break;
            default :
                /* translators: %1$s: item name, %2$s: error code */
                $message = sprintf( esc_html__( 'There was a problem activating the license key for the %1$s, please try again or contact support. Error code: %2$s', 'wp-store-locator' ), $this->item_name, $license_data->error );
                break;
        }

        $this->set_license_notice( $message, 'error' );
    }

    /**
     * Return the license details ( name, expiry date and status ).
     *
     * @since  3.0.0
     * @return array $license_details
     */
    private function get_license_details() {
        // Transients exists for 7 days before we check for changes in the license details.
        if ( false === ( $license_details = get_transient( $this->item_shortname . '_license_details' ) ) ) {
            $api_params = [
                'edd_action' => 'check_license',
                'license'    => $this->get_license_key(),
                'url'        => home_url(),
            ];

            $api_params += $this->get_item_identifier();

            /*
             * Get the license data from the API. This is a background check that
             * runs on admin_init, so a failed connection shouldn't queue a notice.
             */
            $license_data = $this->call_license_api( $api_params, true );

            if ( $license_data ) {
                $license_details = $this->set_license_transient( $license_data );
            } else {
                /*
                 * The API request failed ( offline, DNS failure, timeout ). Cache an
                 * unknown status for an hour so the blocking request isn't repeated
                 * on every admin page load.
                 */
                $license_details = [
                    'name'       => $this->item_name,
                    'short_name' => $this->item_shortname,
                    'expiration' => '',
                    'status'     => 'unknown',
                    'support'    => 'unknown',
                ];

                set_transient( $this->item_shortname . '_license_details', $license_details, HOUR_IN_SECONDS );
            }
        }

        return $license_details;
    }

    /**
     * Set a transient that holds the license details for 7 days.
     *
     * After this another API call is made to see if the license
     * status has changed ( expiry date, disabled etc ).
     *
     * @since  3.0.0
     * @param  object $license_data Data returned from license API request.
     * @return array  $license_details The stored license details.
     */
    private function set_license_transient( $license_data ) {
        $expired = $this->is_expired_response( $license_data );
        $status  = isset( $license_data->license ) ? $license_data->license : 'invalid';

        $license_details = [
            'name'       => $this->item_name,
            'short_name' => $this->item_shortname,
            'expiration' => isset( $license_data->expires ) ? $license_data->expires : '',
            'status'     => $expired ? 'valid' : $status,
            'support'    => $expired ? 'expired' : ( isset( $license_data->support_status ) ? $license_data->support_status : 'active' ),
        ];

        set_transient( $this->item_shortname . '_license_details', $license_details, WEEK_IN_SECONDS );

        return $license_details;
    }

    /**
     * Check whether an API response describes an expired license.
     *
     * @since 3.0.0
     * @param  object $license_data Data returned from a license API request.
     * @return bool
     */
    private function is_expired_response( $license_data ) {
        if ( isset( $license_data->license ) && 'expired' === $license_data->license ) {
            return true;
        }

        return isset( $license_data->error ) && 'expired' === $license_data->error;
    }

    /**
     * Return the item identifier for API requests.
     *
     * Prefers item_id when available, falls back to item_name.
     *
     * @since 3.0.0
     * @return array
     */
    private function get_item_identifier() {
        if ( ! empty( $this->item_id ) ) {
            return [ 'item_id' => $this->item_id ];
        }

        return [ 'item_name' => urlencode( $this->item_name ) ];
    }

    /**
     * If there's a problem with the license key,
     * then show a message explaining this to the user
     * that a valid key is required to install updates.
     *
     * An expired license is not a problem here — it still receives updates.
     *
     * @since  3.0.0
     * @param  array  $plugin_data Plugin metadata
     * @param  object $response
     * @return void
     */
    public function extend_plugin_update_msg( $plugin_data, $response ) {
        $license_key     = $this->get_license_key();
        $license_details = get_transient( $this->item_shortname . '_license_details' );
        $license_status  = isset( $license_details['status'] ) ? $license_details['status'] : '';

        if ( empty( $license_key ) || ! in_array( $license_status, [ 'valid', 'expired', 'unknown' ], true ) ) {
            echo '&nbsp;<strong><a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings&tab=licenses' ) ) . '">' . esc_html__( 'Enter a valid license key for updates.', 'wp-store-locator' ) . '</a></strong>';
        }
    }
}