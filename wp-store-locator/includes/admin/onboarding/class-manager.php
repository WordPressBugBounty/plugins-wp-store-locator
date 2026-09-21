<?php
/**
 * Create the onboarding page that helps users
 * configure the store locator.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Onboarding;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Container;
use WPSL\Core\Settings\Manager as WpslSettings;

class Manager {

    /**
     * Holds the current step.
     *
     * @var string
     */
    private $step = '';

    /**
     * All the onboarding steps.
     *
     * @var array
     */
    private $steps = [];

    /**
     * The selected map service provider
     *
     * @var string
     * @since 3.0.0
     */
    public $map_service = '';

    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Asset resources instance, resolved on first use
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Assets\Resources
     */
    private $resources;

    /**
     * Validate keys instance, resolved on first use
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\Validate_Keys
     */
    private $validate_keys;

    /**
     * Translations service, resolved on first use
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * All available step keys in order
     *
     * @var array
     * @since 3.0.0
     */
    private $step_keys = [
        'map-service',
        'api-keys',
        'start-location',
        'finishing-up'
    ];

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container          $container  Service container instance
     * @param \WPSL\Core\Settings\Manager   $settings   Settings manager instance
     */
    public function __construct( Container $container, WpslSettings $settings ) {
        // Always register the redirect hook for first-time setup
        add_action( 'admin_init', [ $this, 'redirect' ] );

        $this->container = $container;
        $this->settings = $settings;

        // Only register UI hooks when on the onboarding page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking admin page parameter, not form submission
        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl-onboarding' ) {
            add_action( 'admin_menu',        [ $this, 'admin_menu' ] );
            add_action( 'current_screen',    [ $this, 'create_template' ] );
            add_filter( 'script_loader_tag', [ $this, 'add_type_attribute' ], 10, 3 );

            $this->map_service = $this->get_map_service();
        }
    }

    /**
     * Resolve the asset resources service on first use.
     *
     * @since  3.0.0
     * @return \WPSL\Admin\Assets\Resources
     */
    private function resources() {
        if ( ! $this->resources ) {
            $this->resources = $this->container->get( 'asset_resources' );
        }

        return $this->resources;
    }

    /**
     * Resolve the key validation service on first use.
     *
     * @since  3.0.0
     * @return \WPSL\Admin\Settings\Validate_Keys
     */
    private function validate_keys() {
        if ( ! $this->validate_keys ) {
            $this->validate_keys = $this->container->get( 'validate_keys' );
        }

        return $this->validate_keys;
    }

    /**
     * Resolve the translations service on first use.
     *
     * @since  3.0.0
     * @return \WPSL\Core\I18n\Translations
     */
    private function i18n() {
        if ( ! $this->i18n ) {
            $this->i18n = $this->container->get( 'i18n' );
        }

        return $this->i18n;
    }

    /**
     * Add the onboarding page to the admin menu
     *
     * @since  3.0.0
     * @return void
     */
    public function admin_menu() {
        add_dashboard_page( 
            esc_html__( 'WP Store Locator - Onboarding', 'wp-store-locator' ), 
            esc_html__( 'Store Locator Setup', 'wp-store-locator' ), 
            'manage_wpsl_settings', 
            'wpsl-onboarding', 
            [ $this, 'create_template' ]
        );
    }

    /**
     * Output the onboarding template.
     *
     * @since 3.0.0
     * @param \WP_Screen $current_screen The current screen object
     */
    public function create_template( $current_screen = null ) {
        if ( ! is_object( $current_screen ) || $current_screen->id !== 'dashboard_page_wpsl-onboarding' ) {
            return;
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return;
        }

        // Build the step bar from the single source of truth ($step_keys),
        // so the order and slug list stay identical between methods.
        $labels = $this->step_labels();

        $this->steps = [];
        foreach ( $this->step_keys as $step_key ) {
            $this->steps[ $step_key ] = [
                'name' => isset( $labels[ $step_key ] ) ? $labels[ $step_key ] : $step_key,
            ];
        }

        $this->step = $this->get_current_step();

        // Add wpsl-wp-7-plus class for WP 7.0+ ( mirrors the admin_body_class filter, which doesn't run on this custom page ).
        global $wp_version;
        $wp_7_plus_class = version_compare( $wp_version, '7.0', '>=' ) ? ' wpsl-wp-7-plus' : '';

        $this->admin_enqueue_scripts();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_progress() method
        if ( ! empty( $_POST['save'] ) ) {
            $this->save_progress();
        }

        $this->maybe_handle_skip();

        ob_start();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
    <head>
        <meta name="viewport" content="width=device-width"/>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title><?php esc_html_e( 'WP Store Locator - Onboarding', 'wp-store-locator' ); ?></title>
        <?php wp_print_styles( 'wpsl-shared' ); ?>
        <?php wp_print_styles( 'wpsl-onboarding' ); ?>
        <?php wp_print_styles( 'dashicons' ); ?>
        <?php wp_print_styles( 'common' ); ?>
        <?php wp_print_styles( 'buttons' ); ?>
        <?php wp_print_styles( 'forms' ); ?>
    </head>
    <body class="wpsl-onboarding <?php echo 'wpsl-' . esc_attr( $this->step ) . esc_attr( $wp_7_plus_class ); ?> wp-core-ui">
        <?php
        $this->onboarding_steps();

        $this->content();
        ?>

        <div id="wpsl-onboarding-footer">
            <p><a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Return to dashboard', 'wp-store-locator' ) ?></a></p>
        </div>

        <?php
            wp_print_scripts( 'common' );
            
            // Add error handler for module loading in debug mode
            if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
                ?>
                <script>
                window.addEventListener('error', function(e) {
                    if (e.filename && e.filename.includes('.js')) {
                        console.error('[Module Error]', e.message, e.filename, e.lineno, e.colno);
                    }
                });
                </script>
                <?php
            }
            
            wp_print_scripts( 'wpsl-onboarding' );

            if ( $this->step === 'api-keys' ) {
                wp_print_scripts( 'wpsl-key-visibility' );
            }

            if ( $this->step == 'start-location' ) {
                switch ( $this->map_service ) {
                    case 'mapbox':
                        wp_print_styles( 'wpsl-mapbox' );
                        wp_print_scripts( 'wpsl-mapbox' );
                        break;
                    case 'gmaps':
                        echo '<script>' . wpsl_gmaps_bootstrap() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Script content is escaped with esc_js, wrapping the tag would break the JS
                        break;
                    case 'stadia':
                    case 'osm':
                        // Both render through Leaflet ( registered as 'wpsl-osm' ).
                        wp_print_styles( 'wpsl-osm' );
                        wp_print_scripts( 'wpsl-osm' );
                        break;
                }
            }
        ?>
    </body>
</html>
        <?php

        exit();
    }

    /**
     * Add type="module" attribute to specific script tags.
     *
     * @since  3.0.0
     * @param  string $tag    The <script> tag for the enqueued script.
     * @param  string $handle The script's registered handle.
     * @param  string $src    The script's source URL.
     * @return string The modified <script> tag.
     */
    public function add_type_attribute( $tag, $handle, $src ) {
        // Only add type="module" for source files in development mode
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
            $module_scripts = [ 'wpsl-onboarding' ];
            
            if ( in_array( $handle, $module_scripts ) ) {
                $tag = '<script type="module" src="' . esc_url( $src ) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Rewrites the tag of an already enqueued script inside a script_loader_tag filter
            }
        }
        
        return $tag;
    }

    /**
     * Get the current onboarding step
     *
     * @since  3.0.0
     * @return string The current onboarding step
     */
    public function get_current_step() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading current step from URL parameter, not form submission
        return isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( $this->step_keys );
    }

    /**
     * The translated display label for each step, keyed by step slug.
     *
     * @since  3.0.0
     * @return array Map of step slug => label.
     */
    private function step_labels() {
        return [
            'map-service'    => esc_html__( 'Map Service', 'wp-store-locator' ),
            'api-keys'       => esc_html__( 'API Keys', 'wp-store-locator' ),
            'start-location' => esc_html__( 'Start Location', 'wp-store-locator' ),
            'finishing-up'   => esc_html__( 'Finishing Up', 'wp-store-locator' ),
        ];
    }

    /**
     * Show the onboarding steps
     *
     * @since  3.0.0
     * @return void
     */
    public function onboarding_steps() {
        ?>
        <div class="wpsl-onboarding-steps">
            <?php
            foreach ( $this->steps as $step_key => $step ) {
                if ( $step_key === $this->step || $this->step_done( $step_key ) ) {
                    ?>
                    <div <?php echo $this->get_step_class( $step_key ); ?>><a href="<?php echo esc_url( add_query_arg( [ 'step' => $step_key, ], admin_url( '?page=wpsl-onboarding' ) ) ); ?>"><?php echo esc_html( $step['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></a></div>
                    <?php
                } else {
                    ?>
                    <div <?php echo $this->get_step_class( $step_key ); ?>><?php echo esc_html( $step['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></div>
                    <?php
                }

                if ( $step_key !== 'finishing-up' ) {
                    ?>
                    <span class="wpsl-divider"></span>
                    <?php
                }
                ?>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * When the user runs the plugin for the first time
     * redirect them to the onboarding page.
     *
     * @since  3.0.0
     * @return void
     */
    public function redirect() {
        if ( get_transient( 'wpsl_onboarding_redirect' ) && current_user_can( 'manage_wpsl_settings' ) ) {
            delete_transient( 'wpsl_onboarding_redirect' );

            wp_safe_redirect( admin_url( 'index.php?page=wpsl-onboarding' ) );
            exit();
        }
    }   

    /**
     * Register the required JS / CSS files.
     *
     * @since  3.0.0
     * @return void
     */
    public function admin_enqueue_scripts() {
        $js_settings = $this->resources()->get_settings( $this->map_service );

        // Load from dist folder for production, source folder for development
        $css_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $css_ext = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.css' : '.min.css';
        
        wp_register_style( 'wpsl-shared', WPSL_URL . $css_base . 'admin/css/shared' . $css_ext, [], WPSL_VERSION_NUM );
        wp_register_style( 'wpsl-onboarding', WPSL_URL . $css_base . 'admin/css/onboarding' . $css_ext, [], WPSL_VERSION_NUM );
        wp_register_style( 'dashicons', includes_url( 'css/dashicons.css' ), [], get_bloginfo( 'version' ) );
        wp_register_style( 'common', admin_url( 'css/common.css' ), [], get_bloginfo( 'version' ) );
        wp_register_style( 'buttons', admin_url( 'css/buttons.css' ), [], get_bloginfo( 'version' ) );
        wp_register_style( 'forms', admin_url( 'css/forms.css' ), [], get_bloginfo( 'version' ) );

        if ( in_array( $this->step, [ 'start-location', 'api-keys' ] ) ) {

            // Check if API keys are valid, fallback to OSM if not
            if ( $this->step === 'start-location' ) {
                $service_before = $this->map_service;
                $this->check_api_key_validation();

                // If validation downgraded the provider to OSM (a key-required
                // service without a valid key), rebuild the JS settings so the
                // provider, key and tile layer all reflect the OSM fallback
                // instead of leaving the original provider's stale config.
                if ( $this->map_service !== $service_before ) {
                    $js_settings = $this->resources()->get_settings( $this->map_service );
                }
            }

            switch ( $this->map_service ) {
                case 'mapbox':
                    wp_register_script( 'wpsl-mapbox', 'https://api.mapbox.com/mapbox-gl-js/v' . wpsl_get_script_version( 'mapbox_gl_js' ) . '/mapbox-gl.js', [], wpsl_get_script_version( 'mapbox_gl_js' ), true );
                    wp_register_style( 'wpsl-mapbox', 'https://api.mapbox.com/mapbox-gl-js/v' . wpsl_get_script_version( 'mapbox_gl_js' ) . '/mapbox-gl.css', [], wpsl_get_script_version( 'mapbox_gl_js' ) );

                    $js_settings['api']['provider'] = 'mapbox';
                    break;
                case 'gmaps':
                    $js_settings['api']['provider'] = 'gmaps';
                    $js_settings['libraries'] = wpsl_gmaps_libraries();
                    break;
                case 'stadia':
                    // Stadia renders through Leaflet, exactly like OSM.
                    wpsl_register_library( 'wpsl-osm', 'leaflet_js' );
                    wpsl_register_library( 'wpsl-osm', 'leaflet_css' );

                    $js_settings['api']['provider'] = 'stadia';
                    break;
                case 'osm':
                    wpsl_register_library( 'wpsl-osm', 'leaflet_js' );
                    wpsl_register_library( 'wpsl-osm', 'leaflet_css' );

                    $js_settings['api']['provider'] = 'osm';
                    break;
            }

            /*
             * Dragging the start marker is only useful when the drop can be
             * reverse geocoded into a name: start_name is a required setting,
             * and the sanitizer clears start_latlng along with it, so a drag
             * that cannot be named leaves nothing behind to save.
             */
            $js_settings['api']['reverseGeocoding'] = wpsl_map_service_can_reverse_geocode( $this->map_service ) ? 1 : 0;
        }

        $toggle_min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        wp_register_script( 'wpsl-key-visibility', WPSL_URL . 'assets/src/admin/js/wpsl-key-visibility' . $toggle_min . '.js', [], WPSL_VERSION_NUM, true );

        // Load from dist folder for production, source folder for development
        $onboarding_path = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/admin/js/wpsl-onboarding.js' : 'assets/dist/admin/js/wpsl-onboarding.min.js';
        
        wp_register_script( 'wpsl-onboarding', WPSL_URL . $onboarding_path, [ 'jquery' ], WPSL_VERSION_NUM, false );
        
        wp_localize_script( 'wpsl-onboarding', 'wpslSettings', $js_settings );
        wp_localize_script( 'wpsl-onboarding', 'wpslApiErrors', wpsl_api_error_messages() );
        wp_localize_script( 'wpsl-onboarding', 'wpslOnboardingL10n', $this->onboarding_js_l10n() );
        wp_localize_script( 'wpsl-onboarding', 'wpslL10n', $this->admin_l10n() );
        wp_localize_script( 'wpsl-onboarding', 'wpslSecurity', [
            'validateKeyNonce' => wp_create_nonce( 'wpsl_validate_key' ),
        ] );
    }

    /**
     * Get the selected map provider value.
     *
     * @since  3.0.0
     * @return string $map_service;
     */
    public function get_map_service() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only: the value only decides which provider UI/scripts to load for this request. It is sanitized with sanitize_key() and validated against the wpsl_get_map_services() allowlist below; no state is changed here, so no nonce is required. The actual save is nonce-verified in save_progress().
        $selected_service = isset( $_POST['wpsl_api']['active_map_service'] ) ? sanitize_key( wp_unslash( $_POST['wpsl_api']['active_map_service'] ) ) : '';
        
        if ( ! empty( $selected_service ) && array_key_exists( $selected_service, wpsl_get_map_services() ) ) {
            $map_service = $selected_service;
        } else {
            $map_service = $this->settings->get( 'api', 'active_map_service' );
        }
        
        return $map_service;
    }

    /**
     * Load the content template based on the active section.
     *
     * @since  3.0.0
     * @return void
     */
    public function content() {
        // Validate against the single source of truth and fall back to the first step.
        if ( ! in_array( $this->step, $this->step_keys, true ) ) {
            $this->step = current( $this->step_keys );
        }
        
        // Get settings to pass to the template
        $settings = $this->settings->get_all();
        
        // Include the template file
        include( WPSL_PLUGIN_DIR . 'includes/admin/onboarding/templates/' . $this->step . '.php' );
    }

    /**
     * Save the onboarding progress.
     *
     * @since  3.0.0
     * @return void
     */
    public function save_progress() {
        $key = ! empty( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : false;

        if ( ! $key || ! isset( $_POST['onboarding_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['onboarding_nonce'] ) ), 'wpsl_onboarding_' . $key ) ) {
            exit();
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            exit();
        }

        $map_service = $this->get_map_service();

        switch ( $key ) {
            case 'map-service':
                $this->settings->set( 'api', 'active_map_service', $map_service );

                // Reset any fallback recorded from a previous pass; it is
                // re-evaluated when the API Keys step is reached.
                delete_option( 'wpsl_onboarding_key_fallback' );
                break;
            case 'api-keys':
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above, individual fields sanitized below
                $api_keys = isset( $_POST['wpsl_api'] ) ? wp_unslash( $_POST['wpsl_api'] ) : [];

                // Sanitize and save API keys based on selected map service.
                // The key values must be persisted to the settings (not just
                // validated), because the start-location step reads the token
                // from the settings to initialize the provider map.
                switch ( $map_service ) {
                    case 'gmaps':
                        if ( isset( $api_keys['gmaps_browser_key'] ) ) {
                            $browser_key = sanitize_text_field( $api_keys['gmaps_browser_key'] );
                            $this->settings->set( 'api', 'gmaps_browser_key', $browser_key );
                            $this->validate_keys()->update_and_validate( 'gmaps_browser', 'browser', $browser_key );
                        }

                        if ( isset( $api_keys['gmaps_server_key'] ) ) {
                            $server_key = sanitize_text_field( $api_keys['gmaps_server_key'] );
                            $this->settings->set( 'api', 'gmaps_server_key', $server_key );
                            $this->validate_keys()->update_and_validate( 'gmaps_server', 'server', $server_key );
                        }

                        break;
                    case 'osm':
                        if ( isset( $api_keys['openrouteservice_key'] ) ) {
                            $ors_key = sanitize_text_field( $api_keys['openrouteservice_key'] );
                            $this->settings->set( 'api', 'openrouteservice_key', $ors_key );
                            $this->validate_keys()->update_and_validate( 'openrouteservice', 'openrouteservice', $ors_key );
                        }

                        break;
                    case 'mapbox':
                        if ( isset( $api_keys['mapbox_key'] ) ) {
                            $mapbox_key = sanitize_text_field( $api_keys['mapbox_key'] );
                            $this->settings->set( 'api', 'mapbox_key', $mapbox_key );
                            $this->validate_keys()->update_and_validate( 'mapbox', 'mapbox', $mapbox_key );
                        }

                        break;
                    case 'stadia':
                        if ( isset( $api_keys['stadia_key'] ) ) {
                            $stadia_key = sanitize_text_field( $api_keys['stadia_key'] );
                            $this->settings->set( 'api', 'stadia_key', $stadia_key );
                            $this->validate_keys()->update_and_validate( 'stadia', 'stadia', $stadia_key );
                        }

                        break;
                }

                $this->maybe_fallback_to_osm( $map_service );

                break;
            case 'start-location':
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above, individual fields sanitized below
                $start_location = isset( $_POST['wpsl_map'] ) ? wp_unslash( $_POST['wpsl_map'] ) : [];

                if ( isset( $start_location['start_name'] ) ) {
                    $this->settings->set( 'map', 'start_name', sanitize_text_field( $start_location['start_name'] ) );
                }

                if ( isset( $start_location['start_latlng'] ) ) {
                    $this->settings->set( 'map', 'start_latlng', sanitize_text_field( $start_location['start_latlng'] ) );
                }

                update_option( 'wpsl_onboarding_finished', true );
                break;
        }

        $this->step = $key;

        if ( ! empty( $_POST['save'] ) ) {
            $next_step = $this->nav_step();
            $redirect_url = add_query_arg( 'step', $next_step, admin_url( 'index.php?page=wpsl-onboarding' ) );
        } else {
            $redirect_url = add_query_arg( 'step', $key, admin_url( 'index.php?page=wpsl-onboarding' ) );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Check if the current step is done or not.
     *
     * @since  3.0.0
     * @param  string $step_key
     * @return boolean
     */
    public function step_done( $step_key ) {
        $current_index = array_search( $this->step, $this->step_keys );
        $check_index   = array_search( $step_key, $this->step_keys );

        if ( $current_index === false || $check_index === false ) {
            return false;
        }

        return $current_index > $check_index;
    }

    /**
     * Get the correct CSS class for the steps.
     *
     * @since  3.0.0
     * @param  string $step_key
     * @return string $css_class
     */
    public function get_step_class( $step_key ) {
        $css_class = 'class="wpsl-step"';

        if ( $step_key === $this->step ) {
            $css_class = 'class="wpsl-step wpsl-onboarding-active"';
        } elseif ( $this->step_done( $step_key ) ) {
            $css_class = 'class="wpsl-step wpsl-onboarding-done"';
        }

        return $css_class;
    }

    /**
     * Return text that is used in the wpsl-onboarding.js
     *
     * @since  3.0.0
     * @return array $onboarding_js_l10n
     */
    public function onboarding_js_l10n() {
        $onboarding_js_l10n = [
            'noResults' => $this->i18n()->get_no_results_message(),
            'noStartLocation' => esc_html__( 'Please enter a start location.', 'wp-store-locator' ),
            'startLocation' => esc_html__( 'Start Location', 'wp-store-locator' ),
            'searching' => esc_html__( 'Searching...', 'wp-store-locator' )
        ];

        return $onboarding_js_l10n;
    }

    /**
     * Text used during the onboarding in the wpsl-admin.js.
     *
     * @since  3.0.0
     * @return array $onboarding_js_l10n
     */
    public function admin_l10n() {
        $onboarding_js_l10n = [
            'browserKeySuccess' => esc_html__( 'No problems found with the browser key.', 'wp-store-locator' ),
            'mapboxKeySuccess'  => esc_html__( 'No problems found with the Mapbox API key.', 'wp-store-locator' ),
        ];

        return $onboarding_js_l10n;
    }

    /**
     * Render the HTML for the
     * continue / skip button.
     *
     * @since  3.0.0
     * @return void
     */
    private function action_buttons() {
        ?>
        <p class="submit">
            <?php
            // No Previous on the first step, and none on the closing one - setup
            // is done there, so stepping back into it is not a route we offer.
            if ( $this->step !== 'map-service' && $this->step !== 'finishing-up' ) { ?>
                <a class="wpsl-onboarding-previous" href="<?php echo esc_url( add_query_arg( 'step', $this->nav_step( 'prev' ) ) ); ?>"><?php esc_html_e( 'Previous', 'wp-store-locator' ); ?></a>
            <?php } ?>

            <?php if ( $this->step === 'map-service' ) { ?>
                <span class="wpsl-onboarding-footnote"><?php
                    /* translators: 1: opening link tag, 2: closing link tag */
                    echo sprintf( esc_html__( '* required for the %1$sCSV Manager%2$s add-on.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/add-ons/csv-manager/">', '</a>' ); ?></span>
            <?php } ?>

            <span class="wpsl-onboarding-actions">
                <?php if ( $this->step !== 'map-service' && $this->step !== 'finishing-up' ) { ?>
                    <a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'step', $this->nav_step() ), 'wpsl_onboarding_skip', 'wpsl_skip_nonce' ) ); ?>"><?php esc_html_e( 'Skip this step', 'wp-store-locator' ); ?></a>
                <?php } ?>
                <?php if ( $this->step !== 'finishing-up' ) { ?>
                    <input type="submit" name="save" value="<?php esc_html_e( 'Save &amp; Continue', 'wp-store-locator' ); ?>" class="button button-primary" />
                <?php } ?>
                <?php if ( $this->step == 'finishing-up' ) { ?>
                    <a href="https://wpstorelocator.co/support/" class="button button-secondary wpsl-onboarding-help"><?php esc_html_e( 'Need help? Contact us!' , 'wp-store-locator' ); ?></a>
                <?php } ?>
            </span>
        </p>
        <?php
    }

    /**
     * Get the next section based on the value of the current section.
     *
     * @since  3.0.0
     * @param  string $type
     * @return string $next_section
     */
    public function nav_step( $type = '' ) {
        $next_section = '';
        
        // Use the class property for step keys to avoid hardcoding
        $sections_map = array_flip( $this->step_keys );

        if ( $type == 'prev' ) {
            if ( isset( $sections_map[$this->step] ) && $sections_map[$this->step] > 0 ) {
                $prev_index = $sections_map[$this->step] - 1;
                $next_section = $this->step_keys[$prev_index];
            }
        } else {
            if ( isset( $sections_map[$this->step] ) && $sections_map[$this->step] < count( $this->step_keys ) - 1 ) {
                $next_index = $sections_map[$this->step] + 1;
                $next_section = $this->step_keys[$next_index];
            }
        }

        return $next_section;
    }

    /**
     * The hidden fields keeping track of the
     * onboarding progress and active map service.
     *
     * @since  3.0.0
     * @return void
     */
    private function hidden_fields() {
        ?>
        <input type="hidden" name="onboarding_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl_onboarding_' . $this->step ) ); ?>" />
        <input type="hidden" name="step" value="<?php echo esc_attr( $this->step ); ?>" />
        <input type="hidden" name="wpsl_api[active_map_service]" id="wpsl-selected-map-service" value="<?php echo esc_attr( $this->map_service ); ?>">
        <?php
    }

    /**
     * Render the map service boxes.
     *
     * @since  3.0.0
     * @return void
     */
    private function render_map_service_boxes() {
        $map_providers = [
            'osm' => [
                /* translators: 1: opening span tag, 2: closing span tag */
                'title' => sprintf( esc_html__( 'OpenStreetMap %1$sFree%2$s', 'wp-store-locator' ), '<span>', '</span>' ),
                'img' => WPSL_URL . 'assets/img/admin/onboarding/osm-placeholder.jpg',
                'img_alt' => esc_html__( 'OpenStreetMaps placeholder', 'wp-store-locator' ),
                'features' => [
                    /* translators: 1: opening link tag, 2: closing link tag */
                    esc_html__( 'Free to use, no API key or credit card required.', 'wp-store-locator' ) . ' <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">' . sprintf( esc_html__( 'Note: Displaying map directions requires a free %1$sOpenRouteService%2$s API key.', 'wp-store-locator' ), '<a target="_blank" href="https://openrouteservice.org/">', '</a>' ) . '</span></span>',
                    esc_html__( 'Autocomplete not supported', 'wp-store-locator' ),
                    esc_html__( 'Bulk geocoding requests not supported', 'wp-store-locator' ) . ' *',
                ],
            ],
            'stadia' => [
                /* translators: 1: opening span tag, 2: closing span tag */
                'title' => sprintf( esc_html__( 'Stadia Maps %1$sPaid plan%2$s', 'wp-store-locator' ), '<span>', '</span>' ),
                'img' => WPSL_URL . 'assets/img/admin/onboarding/stadia-placeholder.jpg',
                'img_alt' => esc_html__( 'Stadia Maps placeholder', 'wp-store-locator' ),
                'features' => [
                    /* translators: 1: opening strong tag, 2: closing strong tag, 3: opening link tag to the Stadia Maps pricing page, 4: closing link tag */
                    esc_html__( 'API key required', 'wp-store-locator' ) . ' <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">' . sprintf( esc_html__( 'Note: the free plan %1$sdoes not allow commercial use%2$s, see the %3$spricing page%4$s for the paid plans.', 'wp-store-locator' ), '<strong>', '</strong>', '<a target="_blank" href="https://stadiamaps.com/pricing/">', '</a>' ) . '</span></span>',
                    /* translators: 1: opening link tag, 2: closing link tag, 3: opening link tag, 4: closing link tag */
                    sprintf( esc_html__( 'More affordable than %1$sGoogle Maps%2$s or %3$sMapbox%4$s for high usage', 'wp-store-locator' ), '<a target="_blank" href="https://stadiamaps.com/switch-to-stadia/from-google/">', '</a>', '<a target="_blank" href="https://stadiamaps.com/switch-to-stadia/from-mapbox/">', '</a>' ),
                    /* translators: 1: opening link tag, 2: closing link tag */
                    esc_html__( 'Autocomplete supported', 'wp-store-locator' ),
                    /* translators: 1: opening link tag, 2: closing link tag */
                    esc_html__( 'Bulk geocoding requests supported', 'wp-store-locator' ) . ' * <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">' . sprintf( esc_html__( 'Note: Bulk geocoding and persistent data storage require the %1$sStandard plan%2$s or higher.', 'wp-store-locator' ), '<a target="_blank" href="https://stadiamaps.com/pricing/">', '</a>' ) . '</span></span>',
                    /* translators: 1: opening link tag, 2: closing link tag */
                    sprintf( esc_html__( 'EU endpoints %1$savailable%2$s', 'wp-store-locator' ), '<a target="_blank" href="https://docs.stadiamaps.com/eu-gdpr-endpoints/">', '</a>' ),
                ],
            ],
            'mapbox' => [
                /* translators: 1: opening span tag, 2: closing span tag */
                'title' => sprintf( esc_html__( 'Mapbox %1$sFree Quota%2$s', 'wp-store-locator' ), '<span>', '</span>' ),
                'img' => WPSL_URL . 'assets/img/admin/onboarding/mapbox-placeholder.jpg',
                'img_alt' => esc_html__( 'Mapbox placeholder', 'wp-store-locator' ),
                'features' => [
                    esc_html__( 'API key required', 'wp-store-locator' ),
                    /* translators: 1: opening link tag, 2: closing link tag */
                    sprintf( esc_html__( '50.000 free map loads, see details and %1$spricing%2$s.' , 'wp-store-locator' ), '<a target="_blank" href="https://www.mapbox.com/pricing">', '</a>' ),
                    esc_html__( 'Autocomplete supported', 'wp-store-locator' ),
                    esc_html__( 'Bulk geocoding requests supported' , 'wp-store-locator' ) . ' *',
                ],
            ],
            'gmaps' => [
                /* translators: 1: opening span tag, 2: closing span tag */
                'title' => sprintf( esc_html__( 'Google Maps %1$sFree Quota%2$s', 'wp-store-locator' ), '<span>', '</span>' ),
                'img' => WPSL_URL . 'assets/img/admin/onboarding/gmaps-placeholder.jpg',
                'img_alt' => esc_html__( 'Google Maps placeholder', 'wp-store-locator' ),
                'features' => [
                    esc_html__( 'API keys always required', 'wp-store-locator' ),
                    /* translators: 1: opening link tag, 2: closing link tag */
                    sprintf( esc_html__( 'Google provides %1$sfree monthly usage caps%2$s for each API.', 'wp-store-locator' ), '<a target="_blank" href="https://mapsplatform.google.com/pricing/#pay-as-you-go">', '</a>' ) . ' <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">' . sprintf( esc_html__( 'An active billing account is required to activate their map services. You can manage this in the %1$sGoogle Cloud Console%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://docs.cloud.google.com/billing/docs/how-to/manage-billing-account">', '</a>' ) . '</span></span>',
                    esc_html__( 'Autocomplete supported', 'wp-store-locator' ),
                    esc_html__( 'Bulk geocoding requests supported' , 'wp-store-locator' ) . ' *',
                ],
            ],
        ];
    
        foreach ( $map_providers as $key => $provider ) {
            $selected_class = ( $this->map_service === $key ) ? ' wpsl-selected-provider' : '';
            ?>
            <div class="wpsl-flex-box wpsl-onboarding-<?php echo esc_attr( $key ); ?>-provider<?php echo esc_attr( $selected_class ); ?>">
                <h2><?php echo wp_kses( $provider['title'], [ 'span' => [] ] ); ?></h2>
                <img data-name="<?php echo esc_attr( $key ); ?>" alt="<?php echo esc_attr( $provider['img_alt'] ); ?>" src="<?php echo esc_url( $provider['img'] ); ?>" />
                <ul>
                    <?php foreach ( $provider['features'] as $feature ) : ?>
                        <li><?php echo wp_kses( $feature, [ 'a' => [ 'href' => [], 'target' => [] ], 'span' => [ 'class' => [] ], 'strong' => [] ] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }

    /**
     * Render the API key fields for the selected map service
     *
     * @since  3.0.0
     * @return void
     */
    public function render_api_key_fields() {
        $api_settings = $this->settings->get_group( 'api' );
        $map_service = $this->get_map_service();
        
        switch ( $map_service ) {
            case 'gmaps':
                ?>
                <div class="wpsl-api-gmaps">
                    <p><?php
                        /* translators: 1: opening link tag, 2: closing link tag */
                        echo sprintf( esc_html__( 'How to create the required API keys for Google Maps is explained in %1$sthis guide%2$s. If you don\'t want to create them now, then make sure to do so before using the store locator.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/">', '</a>' ); ?></p>

                    <div class="wpsl-onboarding-key-wrap">
                        <p>
                            <label for="wpsl-api-browser-key"><?php esc_html_e( 'Browser key', 'wp-store-locator' ); ?></label>
                            <span class="wpsl-key-field">
                                <input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $api_settings['gmaps_browser_key'] ); ?>" name="wpsl_api[gmaps_browser_key]" class="wpsl-key-input" id="wpsl-api-browser-key">
                                <?php echo wpsl_key_visibility_toggle( 'wpsl-api-browser-key', $api_settings['gmaps_browser_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?>
                            </span>
                        </p>
                        <p>
                            <label for="wpsl-api-server-key"><?php esc_html_e( 'Server key', 'wp-store-locator' ); ?></label>
                            <span class="wpsl-key-field">
                                <input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $api_settings['gmaps_server_key'] ); ?>" name="wpsl_api[gmaps_server_key]" class="wpsl-key-input" id="wpsl-api-server-key">
                                <?php echo wpsl_key_visibility_toggle( 'wpsl-api-server-key', $api_settings['gmaps_server_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?>
                            </span>
                        </p>
                        <p class="wpsl-has-preloader">
                            <input type="submit" value="Validate API Keys" class="wpsl-verify-keys button <?php if ( ! $api_settings['gmaps_browser_key'] && ! $api_settings['gmaps_server_key'] ) { echo 'disabled'; } ?>" id="wpsl-verify-gmaps-keys">
                        </p>
                    </div>
                </div>
                <?php

                break;
            case 'mapbox':
                ?>
                <div class="wpsl-api-mapbox">
                    <p><?php
                        /* translators: 1: opening link tag, 2: closing link tag */
                        echo sprintf( esc_html__( 'How to create the required API key for Mapbox is explained %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-mapbox-api-key/">', '</a>' ); ?></p>

                    <div class="wpsl-onboarding-key-wrap">
                        <p class="wpsl-has-preloader">
                            <label for="wpsl-api-mapbox-key"><?php esc_html_e( 'API key', 'wp-store-locator' ); ?></label>
                            <span class="wpsl-key-field">
                                <input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $api_settings['mapbox_key'] ); ?>" name="wpsl_api[mapbox_key]" class="wpsl-key-input" id="wpsl-api-mapbox-key">
                                <?php echo wpsl_key_visibility_toggle( 'wpsl-api-mapbox-key', $api_settings['mapbox_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?>
                            </span>
                            <input type="submit" value="Validate API Key" class="wpsl-verify-keys button <?php if ( ! $api_settings['mapbox_key'] ) { echo 'disabled'; } ?>" id="wpsl-verify-mapbox-keys">
                        </p>
                    </div>
                </div>
                <?php

                break;
            case 'stadia':
                ?>
                <div class="wpsl-api-stadia">
                    <p><?php
                        /* translators: 1: opening link tag, 2: closing link tag */
                        echo sprintf( esc_html__( 'A valid API key is required before you can use Stadia Maps. You can create one %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://client.stadiamaps.com/signup/">', '</a>' ); ?></p>

                    <div class="wpsl-onboarding-key-wrap">
                        <p class="wpsl-has-preloader">
                            <label for="wpsl-api-stadia-key"><?php esc_html_e( 'API key', 'wp-store-locator' ); ?></label>
                            <span class="wpsl-key-field">
                                <input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $api_settings['stadia_key'] ); ?>" name="wpsl_api[stadia_key]" class="wpsl-key-input" id="wpsl-api-stadia-key">
                                <?php echo wpsl_key_visibility_toggle( 'wpsl-api-stadia-key', $api_settings['stadia_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?>
                            </span>
                            <input type="submit" value="Validate API Key" class="wpsl-verify-keys button <?php if ( ! $api_settings['stadia_key'] ) { echo 'disabled'; } ?>" id="wpsl-verify-stadia-keys">
                        </p>
                    </div>
                </div>
                <?php

                break;
            default: // OSM
                ?>
                <div class="wpsl-api-osm">
                    <p><?php
                        /* translators: 1: opening link tag, 2: closing link tag */
                        echo sprintf( esc_html__( 'OpenStreetMaps itself doesn\'t require an API key. But if you want to show the directions from the customers location to a store in the store locator itself, then this requires an %1$sAPI key from Openrouteservice%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://openrouteservice.org/dev/#/signup/">', '</a>' ); ?></p>
                    <p><?php
                        /* translators: 1: opening link tag, 2: closing link tag */
                        echo sprintf( esc_html__( 'If no API key is provided, the user is redirected to %1$sOpenStreetMap%2$s for the directions.', 'wp-store-locator' ), '<a target="_blank" href="https://www.openstreetmap.org/directions">', '</a>' ); ?></p>
                    <div class="wpsl-onboarding-key-wrap">
                        <p class="wpsl-has-preloader">
                            <label for="wpsl-api-openrouteservice-key"><?php esc_html_e( 'Openrouteservice key', 'wp-store-locator' ); ?></label>
                            <span class="wpsl-key-field">
                                <input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $api_settings['openrouteservice_key'] ); ?>" name="wpsl_api[openrouteservice_key]" class="wpsl-key-input" id="wpsl-api-openrouteservice-key">
                                <?php echo wpsl_key_visibility_toggle( 'wpsl-api-openrouteservice-key', $api_settings['openrouteservice_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?>
                            </span>
                            <input type="submit" value="Validate API Keys" class="wpsl-verify-keys button <?php if ( ! $api_settings['openrouteservice_key'] ) { echo 'disabled'; } ?>" id="wpsl-verify-osm-keys">
                        </p>
                    </div>
                </div>
                <?php

                break;
        }
    }

    /**
     * Check if the selected map service has valid API keys.
     * If not, fallback to OpenStreetMap.
     *
     * @since  3.0.0
     * @return void
     */
    private function check_api_key_validation() {
        $is_valid = false;
        
        switch ( $this->map_service ) {
            case 'gmaps':
                $is_valid = (bool) get_option( 'wpsl_valid_gmaps_browser_key', 0 );
                break;
            case 'mapbox':
                $is_valid = (bool) get_option( 'wpsl_valid_mapbox_key', 0 );
                break;
            case 'stadia':
                $is_valid = (bool) get_option( 'wpsl_valid_stadia_key', 0 );
                break;
            case 'osm':
                $is_valid = true;
                break;
        }

        // If the API key validation failed, fallback to OSM
        if ( ! $is_valid ) {
            $this->map_service = 'osm';
        }
    }

    /**
     * Check whether a map service is missing a valid API key.
     *
     * Google Maps, Mapbox and Stadia Maps require a valid API key to load the
     * map and to geocode locations. OpenStreetMap has no such requirement, so
     * it never triggers the warning.
     *
     * @since  3.0.0
     * @param  string $map_service The service to check. Defaults to the active service.
     * @return bool True when the service requires an API key that is missing or invalid.
     */
    public function map_service_missing_api_key( $map_service = null ) {
        if ( null === $map_service ) {
            $map_service = $this->map_service;
        }

        switch ( $map_service ) {
            case 'gmaps':
                return ! get_option( 'wpsl_valid_gmaps_browser_key', 0 ) || ! get_option( 'wpsl_valid_gmaps_server_key', 0 );
            case 'mapbox':
                return ! get_option( 'wpsl_valid_mapbox_key', 0 );
            case 'stadia':
                return ! get_option( 'wpsl_valid_stadia_key', 0 );
            default:
                return false;
        }
    }

    /**
     * The documentation URL explaining how to create an API key for a service.
     *
     * @since  3.0.0
     * @param  string $map_service The service to link documentation for.
     * @return string
     */
    public function api_key_doc_url( $map_service ) {
        switch ( $map_service ) {
            case 'gmaps':
                return 'https://wpstorelocator.co/document/create-google-api-keys/';
            case 'mapbox':
                return 'https://wpstorelocator.co/document/create-mapbox-api-key/';
            case 'stadia':
                return 'https://docs.stadiamaps.com/authentication/';
            default:
                return '';
        }
    }

    /**
     * Resolve which premium service (if any) the user was switched away from
     * because it lacked a valid API key.
     *
     * The onboarding flow falls back to OpenStreetMap whenever a Google Maps,
     * Mapbox or Stadia Maps key is missing or invalid. This returns the service
     * that was fallen back from, so the finishing-up step can inform the user.
     *
     * Reporting only - the fallback itself is performed by the nonce-verified
     * save and skip paths, never while rendering a step.
     *
     * @since  3.0.0
     * @return string The original service slug ('gmaps', 'mapbox' or 'stadia'), or '' when no fallback applies.
     */
    public function get_key_fallback_service() {
        $fallback_service = get_option( 'wpsl_onboarding_key_fallback', '' );

        return in_array( $fallback_service, [ 'gmaps', 'mapbox', 'stadia' ], true ) ? $fallback_service : '';
    }

    /**
     * Switch to OpenStreetMap when the chosen provider has no valid API key.
     *
     * Without a valid key that provider can't load a map or geocode anything,
     * so the original choice is recorded and the closing step reports it.
     *
     * @since  3.0.0
     * @param  string $map_service The service the user picked.
     * @return void
     */
    private function maybe_fallback_to_osm( $map_service ) {
        if ( ! in_array( $map_service, [ 'gmaps', 'mapbox', 'stadia' ], true ) ) {
            return;
        }

        if ( $this->map_service_missing_api_key( $map_service ) ) {
            update_option( 'wpsl_onboarding_key_fallback', $map_service, 'no' );

            $this->settings->set( 'api', 'active_map_service', 'osm' );
            $this->map_service = 'osm';
        } else {
            delete_option( 'wpsl_onboarding_key_fallback' );
        }
    }

    /**
     * Apply the OpenStreetMap fallback when a step is skipped.
     *
     * @since  3.0.0
     * @return void
     */
    private function maybe_handle_skip() {
        if ( ! isset( $_GET['wpsl_skip_nonce'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_GET['wpsl_skip_nonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'wpsl_onboarding_skip' ) ) {
            return;
        }

        $this->maybe_fallback_to_osm( $this->map_service );
    }
}