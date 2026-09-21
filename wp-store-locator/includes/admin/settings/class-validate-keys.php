<?php
/**
 * Validate API keys.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Admin\API\Geocode;
use WPSL\Core\Settings\Manager as WpslSettings;
    
class Validate_Keys {

    /**
     * The geocode instance.
     *
     * @var \WPSL\Admin\API\Geocode
     */
    private $geocode;

    /**
     * The settings instance.
     *
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Admin\API\Geocode      $geocode   Geocode API instance
     * @param \WPSL\Core\Settings\Manager  $settings  Settings handler instance
     */
    public function __construct( Geocode $geocode, WpslSettings $settings ) {
        $this->geocode  = $geocode;
        $this->settings = $settings;

        add_action( 'wp_ajax_wpsl_validate_key', [ $this, 'validate' ] );
        add_action( 'wp_ajax_wpsl_update_validation_status', [ $this, 'update_validation_status' ] );
    }

    /**
     * Handle the AJAX call from the settings 
     * page to validate the different API keys.
     *
     * @since  3.0.0
     * @return void
     */
    public function validate() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsl_validate_key' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-store-locator' ) ] );
            exit;
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) || ! is_admin() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) ] );
            exit;
        }

        $allowed_methods = [ 'server', 'mapbox', 'openrouteservice', 'stadia' ];
        $wpsl_api        = ( isset( $_POST['wpsl_api'] ) && is_array( $_POST['wpsl_api'] ) ) ? map_deep( wp_unslash( $_POST['wpsl_api'] ), 'sanitize_text_field' ) : [];

        foreach ( $wpsl_api as $name => $value ) {
            $name = sanitize_key( $name );

            if ( in_array( $name, $allowed_methods, true ) ) {
                call_user_func( [ $this, $name ], $value );
            } else if ( has_action( 'wpsl_' . $name . '_key' ) ) {
                do_action( 'wpsl_' . $name . '_key', $value );
            }
        }
    }

    /**
     * Check if the provided API key is valid and sanitize it.
     *
     * @since  3.0.0
     * @param  string  $map_service The map service (gmaps, mapbox)
     * @param  string  $key_type    The key type ( server, mapbox)
     * @param  string  $value       The API key to validate
     * @param  boolean $notify      Whether to show a settings error notice on failure
     * @return string               The sanitized API key
     */
    public function check( $map_service, $key_type, $value, $notify = true ) {
        $sanitized_value = sanitize_text_field( $value );

        if ( $key_type === 'server' ) {
            $setting_key = 'gmaps_server';
        } else {
            $setting_key = $key_type;
        }

        $this->update_and_validate( $setting_key, $key_type, $value, $notify );

        return $sanitized_value;
    }

    /**
     * Update and validate the API key.
     *
     * @since  3.0.0
     * @param  string  $setting_key The setting key
     * @param  string  $key_type    The key type
     * @param  string  $value       The key value
     * @param  boolean $notify      Whether to show a settings error notice on failure
     * @return void
     */
    public function update_and_validate( $setting_key, $key_type, $value, $notify = true ) {
        $key_changed = $this->has_changed( $setting_key, $value );
        $option_name = 'wpsl_valid_' . $setting_key . '_key';
        $is_valid = true;

        // Check if the key has changed
        if ( ! $key_changed ) {
            $is_valid = get_option( $option_name, 0 );
        }

        // Skip validation if the key is empty - just mark as invalid but don't test API
        if ( empty( trim( $value ) ) ) {
            update_option( $option_name, 0, 'no' );

            return;
        }

        // Only test the response if the key changed or was previously invalid
        if ( $key_changed || ! $is_valid ) {
            $this->test_response( $key_type, $value, $notify );
        }
    }

    /**
     * Check if the provided API key has changed compared to what's stored in the database.
     *
     * @since  3.0.0
     * @param  string $key_type  The type of key (browser, server, mapbox, openrouteservice)
     * @param  string $key_value The API key value to check
     * @return boolean True if the key has changed, false otherwise
     */
    public function has_changed( $key_type, $key_value ) {
        $api_settings = $this->settings->get_group( 'api' );
        
        return ( ! isset( $api_settings[ $key_type . '_key' ] ) || $key_value !== $api_settings[ $key_type . '_key' ] );
    }
    
    /**
     * Test the API response for a specific key type
     *
     * @since  3.0.0
     * @param  string  $key_type  The type of key to test (browser, server, mapbox, openrouteservice)
     * @param  string  $key_value The API key value to test
     * @param  boolean $notify    Whether to show a settings error notice on failure
     * @return void
     */
    public function test_response( $key_type, $key_value, $notify = true ) {
        if ( method_exists( $this, $key_type ) ) {
            call_user_func( [ $this, $key_type ], $key_value, $notify );
        } else if ( has_action( 'wpsl_' . $key_type . '_key' ) ) {
            do_action( 'wpsl_' . $key_type . '_key', $key_value, $notify );
        }
    }

    /**
     * Format a plain "Error code: x\nReason: y" status string into HTML,
     * bolding the labels and joining the lines with a single line break.
     *
     * @since  3.0.0
     * @param  string $response The plain status string returned by a key check
     * @return string           The escaped, formatted HTML
     */
    private function format_key_error( $response ) {
        $html = [];

        foreach ( explode( "\n", $response ) as $line ) {
            $pos = strpos( $line, ': ' );

            if ( $pos !== false ) {
                $label  = substr( $line, 0, $pos + 1 );
                $value  = substr( $line, $pos + 2 );
                $html[] = '<strong>' . esc_html( $label ) . '</strong> ' . esc_html( $value );
            } else {
                $html[] = esc_html( $line );
            }
        }

        return implode( '<br>', $html );
    }

    /**
     * Queue a custom-rendered API key error notice for the settings page..
     *
     * @since  3.0.0
     * @param  string $key_class The notice key class (e.g. 'mapbox-key', 'server-key')
     * @param  string $msg       The notice title (plain text)
     * @param  string $details   The notice body HTML (code/reason paragraph + hint)
     * @param  string $type      The notice type: 'error' (default) or 'info'
     * @return void
     */
    private function queue_key_error_notice( $key_class, $msg, $details, $type = 'error' ) {
        $notices = get_transient( 'wpsl_key_error_notices' );

        if ( ! is_array( $notices ) ) {
            $notices = [];
        }

        // Prevent duplicate notices for the same key.
        if ( isset( $notices[ $key_class ] ) ) {
            return;
        }

        $notices[ $key_class ] = [
            'msg'     => $msg,
            'details' => $details,
            'type'    => ( $type === 'info' ) ? 'info' : 'error',
        ];

        set_transient( 'wpsl_key_error_notices', $notices, MINUTE_IN_SECONDS );
    }

    /**
     * Render any queued API key error notices, matching the JS notice markup.
     *
     * Called from the settings page template, right after settings_errors().
     *
     * @since  3.0.0
     * @return void
     */
    public function render_key_error_notices() {
        $notices = get_transient( 'wpsl_key_error_notices' );

        if ( empty( $notices ) || ! is_array( $notices ) ) {
            return;
        }

        delete_transient( 'wpsl_key_error_notices' );

        foreach ( $notices as $key_class => $notice ) {
            // 'error' keeps the legacy red notice; 'info' uses WP's blue info notice.
            $type        = ( isset( $notice['type'] ) && $notice['type'] === 'info' ) ? 'info' : 'error';
            $notice_class = ( $type === 'info' ) ? 'notice-info' : 'error';

            printf(
                '<div class="wpsl-%1$s %2$s notice is-dismissible apikeys-error"><p><strong>%3$s</strong></p>%4$s</div>',
                esc_attr( $key_class ),
                esc_attr( $notice_class ),
                esc_html( $notice['msg'] ),
                wp_kses_post( $notice['details'] )
            );
        }
    }

    /**
     * Whether this validation run is allowed to answer the request itself.
     *
     * The branches end in wp_send_json() + exit(), which is needed for the
     * "Validate API keys" button but fatal for callers like
     * wpsl_validate_migrated_api_keys() that only want validity recorded:
     * the migration can run inside an AJAX request, and exiting there
     * killed it mid-way. $notify is how a caller says "stay silent", so
     * it governs the exit too.
     *
     * @since  3.0.0
     * @param  bool $notify Whether the caller wants this run to report itself.
     * @return bool
     */
    private function may_answer_request( $notify ) {
        return $notify && defined( 'DOING_AJAX' ) && DOING_AJAX;
    }

    /**
     * Validate the provided Mapbox API key.
     *
     * @since  3.0.0
     * @param  string  $key_value The API key value to test
     * @param  boolean $notify    Whether to show a settings error notice on failure
     * @return void
     */
    private function mapbox( $key_value, $notify = true ) {
        $this->geocode->set_geocode_implementation( 'mapbox' );

        $response = wpsl_check_mapbox_key_status( $key_value );

        if ( $response !== 'OK' ) {
            update_option( 'wpsl_valid_mapbox_key', 0, 'no' );

            $msg     = esc_html__( 'The Mapbox API has returned the following error for the used API key.', 'wp-store-locator' );
            $details = '<p>' . $this->format_key_error( $response ) . '</p>' . wpsl_get_mapbox_error_hint( $response );

            // When validated over AJAX, return msg + details so the JS notice
            // renders the code/reason
            if ( $this->may_answer_request( $notify ) ) {
                $key_status = [
                    'valid'   => 0,
                    'msg'     => $msg,
                    'details' => $details
                ];

                wp_send_json( $key_status );

                exit();
            }

            // Only show the notice when Mapbox is the active map provider.
            if ( ! $notify ) {
                return;
            }

            $this->queue_key_error_notice( 'mapbox-key', $msg, $details );
        } else {
            update_option( 'wpsl_valid_mapbox_key', 1, 'no' );
        }
    }

    /**
     * Check if the provided server key 
     * for the Google Maps API is valid.
     *
     * @since  2.2.10
     * @param  string  $key_value The API key value to test
     * @param  boolean $notify    Whether to show a settings error notice on failure
     * @return void If the validation failed and AJAX is used, then json
     */
    private function server( $key_value, $notify = true ) {
        add_option( 'wpsl_key_validation_in_progess', true );

        $this->geocode->set_geocode_implementation( 'gmaps' );
        
        // Test the server key by making a request to the Geocode API.
        $response = wpsl_check_gmaps_key_status( $key_value );

        // If the state is not OK, then there's a problem with the key.
        if ( $response !== 'OK' ) {
            update_option( 'wpsl_valid_gmaps_server_key', 0, 'no' );

            if ( $this->may_answer_request( $notify ) ) {
                $key_status = [
                    'valid'   => 0,
                    'msg'     => esc_html__( 'The Google Geocode API returned the following error for the server key.', 'wp-store-locator' ),
                    'details' => '<p>' . $this->format_key_error( $response ) . '</p>' . wpsl_get_gmaps_error_hint( $response )
                ];

                wp_send_json( $key_status );

                exit();
            } else {
                // Only show a notice (and console error) when Google Maps is
                // the active map provider. The validity option is still updated above.
                if ( ! $notify ) {
                    delete_transient( 'wpsl_gmaps_key_error_details' );

                    return;
                }

                // Render a custom notice (instead of add_settings_error, which would
                // bold the whole message) so the code/reason and hint match the JS notice.
                $this->queue_key_error_notice(
                    'server-key',
                    esc_html__( 'The Google Geocode API returned the following error for the server key.', 'wp-store-locator' ),
                    '<p>' . $this->format_key_error( $response ) . '</p>' . wpsl_get_gmaps_error_hint( $response )
                );
            }
        } else {
            update_option( 'wpsl_valid_gmaps_server_key', 1, 'no' );

            if ( $this->may_answer_request( $notify ) ) {
                $key_status = [
                    'valid' => 1,
                    'msg'   => esc_html__( 'No problems found with the server key.', 'wp-store-locator' )
                ];
                
                wp_send_json( $key_status );
                
                exit();
            }
        }
    }

    /**
     * Validate the provided Stadia Maps API key.
     *
     * @since  3.0.0
     * @param  string  $key_value The API key value to test
     * @param  boolean $notify    Whether to show a settings error notice on failure
     * @return void
     */
    private function stadia( $key_value, $notify = true ) {
        $response = wpsl_check_stadia_key_status( $key_value );

        if ( $response !== 'OK' ) {
            update_option( 'wpsl_valid_stadia_key', 0, 'no' );

            delete_option( 'wpsl_stadia_reverse_access' );

            $msg     = esc_html__( 'The Stadia Maps API has returned the following error for the used API key.', 'wp-store-locator' );
            $details = '<p>' . $this->format_key_error( $response ) . '</p>' . wpsl_get_stadia_error_hint( $response );

            if ( $this->may_answer_request( $notify ) ) {
                $key_status = [
                    'valid'   => 0,
                    'msg'     => $msg,
                    'details' => $details
                ];

                wp_send_json( $key_status );

                exit();
            }

            if ( ! $notify ) {
                return;
            }

            $this->queue_key_error_notice( 'stadia-key', $msg, $details );
        } else {
            update_option( 'wpsl_valid_stadia_key', 1, 'no' );

            /*
             * Record whether this key may reverse geocode, so the onboarding
             * map knows before it renders whether dragging the start marker
             * can produce a location name.
             */
            update_option( 'wpsl_stadia_reverse_access', wpsl_check_stadia_reverse_access( $key_value ) ? 1 : 0, 'no' );

            if ( $this->may_answer_request( $notify ) ) {
                wp_send_json( $this->stadia_key_status( $key_value ) );

                exit();
            }
        }
    }

    /**
     * The AJAX payload for a Stadia key that passed validation.
     *
     * The key check only proves the key works against a tile, which says
     * nothing about geocoding access. Stadia sells the reverse endpoint
     * separately, so a key can validate here, run searches, and still 403 on
     * every reverse request, leaving the search field silently empty when the
     * auto-locate option is on.
     *
     * @since  3.0.0
     * @param  string $key_value The API key that was validated
     * @return array  The key status payload for the settings screen
     */
    private function stadia_key_status( $key_value ) {
        $key_status = [
            'valid' => 1,
            'msg'   => esc_html__( 'No problems found with the Stadia Maps API key.', 'wp-store-locator' )
        ];

        // Read the answer stadia() just recorded rather than asking again.
        if ( get_option( 'wpsl_stadia_reverse_access', 1 ) ) {
            return $key_status;
        }

        $key_status['warning'] = 1;
        $key_status['msg']     = esc_html__( 'The Stadia Maps API key is valid, but it has no access to the reverse geocoding endpoint.', 'wp-store-locator' );

        $key_status['details']  = '<p>' . esc_html__( 'Searching works normally. The "Attempt to auto-locate the user" option will still find and search on the visitor location, but cannot fill their address into the search field.', 'wp-store-locator' ) . '</p>';

        // The way out, same wording and destination as the frontend notice.
        $key_status['details'] .= '<p><a target="_blank" rel="noopener noreferrer" href="' . esc_url( wpsl_stadia_account_url() ) . '">' . esc_html__( 'Please upgrade your account', 'wp-store-locator' ) . '</a></p>';

        return $key_status;
    }

    /**
     * Handle the AJAX call to validate the provided
     * directions key for the OpenStreetMaps Directions API.
     *
     * @since  3.0.0
     * @param  string  $key_value The API key value to test
     * @param  boolean $notify    Whether to show a settings error notice on failure
     * @return void
     */
    private function openrouteservice( $key_value, $notify = true ) {
        $response = wpsl_check_openrouteservice_key_status( $key_value );

        if ( ! is_wp_error( $response ) ) {
            if ( $response['response']['code'] != 200 ) {
                update_option( 'wpsl_valid_openrouteservice_key', 0, 'no' );

                $body = json_decode( $response['body'], true );

                if ( is_array( $body['error'] ) ) {
                    $error_message = $body['error']['message'] . ' (' . $body['error']['code'] . ')';
                } else {
                    $error_message = $body['error'];
                }

                // Plain two-line status, matching the other key error formats.
                /* translators: 1: HTTP status code, 2: error reason from the API */
                $status = sprintf( __( 'Error code: %1$s', 'wp-store-locator' ), $response['response']['code'] ) . "\n" . sprintf( __( 'Reason: %1$s', 'wp-store-locator' ), $error_message );

                /* translators: 1: opening link tag to the Openrouteservice account page, 2: closing link tag */
                $hint = '<p>' . sprintf( esc_html__( 'Double-check the key in your %1$sOpenrouteservice account%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://account.heigit.org/login">', '</a>' ) . '</p>';

                // The notice display layers escape 'msg' and render 'details'
                // as HTML, so the title stays plain text and the code/reason
                // and account link go in details.
                $msg     = esc_html__( 'The Openrouteservice API has returned the following error for the used API key.', 'wp-store-locator' );
                $details = '<p>' . $this->format_key_error( $status ) . '</p>' . $hint;

                if ( $this->may_answer_request( $notify ) ) {
                    $key_status = [
                        'valid'   => 0,
                        'msg'     => $msg,
                        'details' => $details
                    ];

                    wp_send_json( $key_status );

                    exit();
                }  else if ( $notify ) {
                    $this->queue_key_error_notice( 'openrouteservice-key', $msg, $details );
                }
            } else {
                update_option( 'wpsl_valid_openrouteservice_key', 1, 'no' );

                if ( $this->may_answer_request( $notify ) ) {
                    $key_status = [
                        'valid' => 1,
                        'msg'   => esc_html__( 'No problems found with the Openrouteservice API key.', 'wp-store-locator' )
                    ];

                    wp_send_json( $key_status );

                    exit();
                }
            }
        } else {
            // Network error, DNS failure, timeout, etc. — the other branch
            // above only handles a completed HTTP response, so this always
            // has to end in a JSON reply / notice instead of falling through
            // to WordPress' default "0" admin-ajax response.
            update_option( 'wpsl_valid_openrouteservice_key', 0, 'no' );

            $status  = wpsl_format_wp_error_status( $response );
            $msg     = esc_html__( 'The Openrouteservice API has returned the following error for the used API key.', 'wp-store-locator' );
            $details = '<p>' . $this->format_key_error( $status ) . '</p>' . wpsl_get_timeout_error_hint( $status );

            if ( $this->may_answer_request( $notify ) ) {
                $key_status = [
                    'valid'   => 0,
                    'msg'     => $msg,
                    'details' => $details
                ];

                wp_send_json( $key_status );

                exit();
            }

            if ( $notify ) {
                $this->queue_key_error_notice( 'openrouteservice-key', $msg, $details );
            }
        }
    }

    /**
     * Update the validation status for a specific API key type.
     * This is called via AJAX from the frontend.
     *
     * @since  3.0.0
     * @return void
     */
    public function update_validation_status() {
        // Check nonce for security
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsl_update_validation_status' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'wp-store-locator' ) ] );
            exit;
        }

        // Check if user has required capabilities
        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) ] );
            exit;
        }

        // Get the key type and status
        $key_type = isset( $_POST['key_type'] ) ? sanitize_text_field( wp_unslash( $_POST['key_type'] ) ) : '';
        $status   = isset( $_POST['status'] ) ? (int) $_POST['status'] : 0;

        // Validate key type
        $valid_key_types = [ 'mapbox', 'openrouteservice', 'stadia', 'gmaps_server', 'gmaps_browser' ];

        if ( ! in_array( $key_type, $valid_key_types, true ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Invalid key type.', 'wp-store-locator' ) ] );
            exit;
        }

        // Validate status (0 or 1)
        $status = ( $status === 1 ) ? 1 : 0;

        // Update the option in the database
        $option_name = 'wpsl_valid_' . $key_type . '_key';

        $updated = update_option( $option_name, $status, 'no' );

        wp_send_json_success();

        exit;
    }
    
    /**
     * Check one key and report the verdict, without answering the request.
     *
     * @since  3.0.0
     * @param  string $key_type  server, mapbox or stadia
     * @param  string $key_value The key to test
     * @return array|null [ 'valid', 'msg', 'details', 'warning' ], or null for a type this cannot test
     */
    public function key_status( $key_type, $key_value ) {
        $key_value = trim( (string) $key_value );

        switch ( $key_type ) {
            case 'server':
                $option   = 'wpsl_valid_gmaps_server_key';
                $fail_msg = esc_html__( 'The Google Geocode API returned the following error for the server key.', 'wp-store-locator' );
                $ok_msg   = esc_html__( 'No problems found with the server key.', 'wp-store-locator' );
                $missing  = esc_html__( 'The server key is missing.', 'wp-store-locator' );
                break;
            case 'mapbox':
                $option   = 'wpsl_valid_mapbox_key';
                $fail_msg = esc_html__( 'The Mapbox API has returned the following error for the used API key.', 'wp-store-locator' );
                $ok_msg   = esc_html__( 'No problems found with the Mapbox API key.', 'wp-store-locator' );
                $missing  = esc_html__( 'The Mapbox API key is missing.', 'wp-store-locator' );
                break;
            case 'stadia':
                $option   = 'wpsl_valid_stadia_key';
                $fail_msg = esc_html__( 'The Stadia Maps API has returned the following error for the used API key.', 'wp-store-locator' );
                $ok_msg   = esc_html__( 'No problems found with the Stadia Maps API key.', 'wp-store-locator' );
                $missing  = esc_html__( 'The Stadia Maps API key is missing.', 'wp-store-locator' );
                break;
            default:
                return null;
        }

        // The status helpers fall back to the saved key when handed an empty
        // one, which would test yesterday's key rather than the empty field.
        if ( '' === $key_value ) {
            update_option( $option, 0, 'no' );

            if ( 'stadia' === $key_type ) {
                delete_option( 'wpsl_stadia_reverse_access' );
            }

            return [ 'valid' => 0, 'msg' => $missing, 'details' => '' ];
        }

        switch ( $key_type ) {
            case 'server':
                $response = wpsl_check_gmaps_key_status( $key_value );
                $hint     = 'OK' === $response ? '' : wpsl_get_gmaps_error_hint( $response );
                break;
            case 'mapbox':
                $response = wpsl_check_mapbox_key_status( $key_value );
                $hint     = 'OK' === $response ? '' : wpsl_get_mapbox_error_hint( $response );
                break;
            default:
                $response = wpsl_check_stadia_key_status( $key_value );
                $hint     = 'OK' === $response ? '' : wpsl_get_stadia_error_hint( $response );
                break;
        }

        if ( 'OK' !== $response ) {
            update_option( $option, 0, 'no' );

            if ( 'stadia' === $key_type ) {
                delete_option( 'wpsl_stadia_reverse_access' );
            }

            return [
                'valid'   => 0,
                'msg'     => $fail_msg,
                'details' => '<p>' . $this->format_key_error( $response ) . '</p>' . $hint,
            ];
        }

        update_option( $option, 1, 'no' );

        if ( 'stadia' === $key_type ) {
            // Same bookkeeping as stadia(): whether this key may reverse geocode.
            update_option( 'wpsl_stadia_reverse_access', wpsl_check_stadia_reverse_access( $key_value ) ? 1 : 0, 'no' );

            return $this->stadia_key_status( $key_value );
        }

        return [ 'valid' => 1, 'msg' => $ok_msg, 'details' => '' ];
    }
}