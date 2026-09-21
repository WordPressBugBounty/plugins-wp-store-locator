<?php
namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Admin\Tools\Geocode_Locations;

/**
 * Alerts
 *
 * Manages various types of alerts for users including plugin conflicts,
 * API key issues and caching plugin notifications.
 *
 * @since 3.0.0
 */
class Alerts {

    /**
     * Where the add-on downloads live, for anyone the updater cannot reach.
     *
     * @var string
     */
    const ACCOUNT_URL = 'https://wpstorelocator.co/account/';

    /**
     * Transient holding the last add-on version scan.
     *
     * @var string
     */
    const OUTDATED_TRANSIENT = 'wpsl_outdated_addons';

    /**
     * The key the coordinate alert is filed under.
     *
     * @var string
     */
    const COORDINATE_ALERT = 'wpsl-coordinates';

    /**
     * The key the Routes API alert is filed under.
     *
     * @since 3.0.1
     * @var string
     */
    const ROUTES_API_ALERT = 'wpsl-routes-api';

    /**
     * The Routes API page in the Google Cloud console.
     *
     * The library URL rather than a project-specific one: the console asks
     * which project to enable it for, and the plugin has no way to know
     * which project holds the keys.
     *
     * @since 3.0.1
     * @var string
     */
    const ROUTES_API_URL = 'https://console.cloud.google.com/apis/library/routes.googleapis.com';

    /**
     * List of conflicting plugins
     *
     * @since 3.0.0
     * @var array
     */
    private $conflicting_plugins = [];

    /**
     * Class constructor
     *
     * @since 3.0.0
     */
    public function __construct() {
        $this->conflicting_plugins = $this->get_conflicting_plugins();

        add_action( 'activated_plugin',           [ $this, 'check_activated_plugin' ], 10, 1 );
        add_action( 'deactivated_plugin',         [ $this, 'handle_deactivated_plugin' ], 10, 1 );
        add_action( 'admin_init',                 [ $this, 'check_existing_plugins' ] );
        add_action( 'wp_ajax_wpsl_dismiss_alert', [ $this, 'dismiss_alert' ] );

        /*
         * The add-on scan reads the header of every installed plugin, and the
         * admin bar counts alerts on every admin page, so the result is cached
         * until something can plausibly have changed it.
         */
        add_action( 'activated_plugin',          [ $this, 'flush_outdated_addons' ] );
        add_action( 'deactivated_plugin',        [ $this, 'flush_outdated_addons' ] );
        add_action( 'upgrader_process_complete', [ $this, 'flush_outdated_addons' ] );
    }

    /**
     * Return a list of plugins that may require additional 
     * configuration steps after activation (e.g., caching or GDPR plugins)
     *
     * @since  3.0.0
     * @return array List of conflicting plugins with their details
     */
    private function get_conflicting_plugins() {
        $plugins = [
            'agile-store-locator/agile-store-locator.php' => [
                'name'        => 'Agile Store Locator',
                'description' => __( 'The Agile Store Locator plugin is active. If you experience problems with the WP Store Locator map, go to <a href="#" class="wpsl-trigger-nav" data-item="tools">Tools</a> and enable compatibility mode.', 'wp-store-locator' )
            ],
            'wp-google-maps/wpGoogleMaps.php' => [
                'name'        => 'WP Go Maps',
                'description' => __( 'WP Go Maps is active. If you experience problems with the WP Store Locator map, go to <a href="#" class="wpsl-trigger-nav" data-item="tools">Tools</a> and enable compatibility mode.', 'wp-store-locator' )
            ],
            'wp-google-map-plugin/wp-google-map-plugin.php' => [
                'name'        => 'WP Maps',
                'description' => __( 'WP Maps is active. If you experience problems with the WP Store Locator map, go to <a href="#" class="wpsl-trigger-nav" data-item="tools">Tools</a> and enable compatibility mode.', 'wp-store-locator' )
            ],
            'borlabs-cookie/borlabs-cookie.php' => [
                'name'        => 'Borlabs Cookie',
                /* translators: %s: link to the GDPR documentation, with the text "additional configuration" */
                'description' => sprintf( __( 'Borlabs Cookie requires %s to work with WP Store Locator.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/the-general-data-protection-regulation/" target="_blank" rel="noopener noreferrer">' . __( 'additional configuration', 'wp-store-locator' ) . '</a>' )
            ],
            'complianz-gdpr/complianz-gpdr.php' => [
                'name'        => 'Complianz',
                'description' => __( 'Complianz is active. To let it decide when the map may load, go to <a href="#" class="wpsl-trigger-nav" data-item="gdpr">GDPR</a> and set the consent handler to Complianz.', 'wp-store-locator' )
            ],
        ];

        return apply_filters( 'wpsl_conflicting_plugins', $plugins );
    }

    /**
     * Give the section links in an alert a real destination.
     *
     * @since  3.0.0
     * @param  string $html An alert description.
     * @return string The description, with every section link resolved.
     */
    public static function link_settings_sections( $html ) {
        $settings_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' );

        return preg_replace_callback(
            '/href="#" class="wpsl-trigger-nav" data-item="([a-z_]+)"/',
            function( $match ) use ( $settings_url ) {
                return 'href="' . esc_url( $settings_url . '#wpsl-' . $match[1] ) . '" class="wpsl-trigger-nav" data-item="' . $match[1] . '"';
            },
            (string) $html
        );
    }

    /**
     * Warn when the consent handler points at a plugin that is not there.
     *
     * @since  3.0.0
     * @return array The alert, or an empty array when the handler is usable
     */
    public function get_handler_alert() {
        $handler = wpsl_get_service( 'wpsl_settings' )->get( 'gdpr', 'handler' );

        $handlers = [
            'complianz' => [
                'label'  => 'Complianz',
                'active' => wpsl_is_gdpr_handler_available( 'complianz' ),
            ],
            'borlabs' => [
                'label'  => 'Borlabs Cookie',
                'active' => wpsl_is_gdpr_handler_available( 'borlabs' ),
            ],
        ];

        if ( ! isset( $handlers[ $handler ] ) || $handlers[ $handler ]['active'] ) {
            return [];
        }

        $label = $handlers[ $handler ]['label'];

        return [
            'wpsl-gdpr-handler' => [
                'name'        => $label,
                'dismissible' => false,
                'description' => sprintf(
                    /* translators: 1: consent plugin name, 2: link to the GDPR settings tab */
                    __( 'The consent handler is set to %1$s, but the %1$s plugin is not active, so the map now loads without asking for consent. Either activate %1$s again, or go to %2$s and switch the handler to WP Store Locator to use its own checkpoint - or to None if the map may load straight away.', 'wp-store-locator' ),
                    esc_html( $label ),
                    '<a href="#" class="wpsl-trigger-nav" data-item="gdpr">' . esc_html__( 'GDPR', 'wp-store-locator' ) . '</a>'
                ),
            ],
        ];
    }

    /**
     * Report locations that no search can return.
     *
     * The only alert about the data rather than the configuration: a store
     * without usable coordinates is silently absent from every result.
     *
     * @since  3.0.0
     * @return array The alert, or an empty array when there is nothing to report
     */
    public function get_coordinate_alert() {
        if ( ! class_exists( 'WPSL\Admin\Tools\Geocode_Locations' ) ) {
            return [];
        }

        $count = Geocode_Locations::count_problems( is_admin() );

        if ( ! $count ) {
            return [];
        }

        $stored    = get_option( 'wpsl_alerts', [] );
        $dismissed = ( is_array( $stored ) && isset( $stored['coordinates']['dismissed_count'] ) )
            ? (int) $stored['coordinates']['dismissed_count']
            : 0;

        if ( $dismissed && $count <= $dismissed ) {
            return [];
        }

        $url = add_query_arg(
            [
                'post_type'                                            => 'wpsl_stores',
                Geocode_Locations::VIEW_ARG => 'problem',
            ],
            admin_url( 'edit.php' )
        );

        return [
            self::COORDINATE_ALERT => [
                'name'        => __( 'Coordinates', 'wp-store-locator' ),
                'dismissible' => true,
                'description' => sprintf(
                    /* translators: %d: number of locations without usable coordinates */
                    _n(
                        '%d location has no usable coordinates, so it is never returned by a search.',
                        '%d locations have no usable coordinates, so they are never returned by a search.',
                        $count,
                        'wp-store-locator'
                    ),
                    $count
                ) . ' ' . sprintf(
                    /* translators: 1: opening link tag to the filtered store list, 2: closing link tag */
                    __( '%1$sReview and geocode them%2$s.', 'wp-store-locator' ),
                    '<a href="' . esc_url( $url ) . '">',
                    '</a>'
                ),
            ],
        ];
    }

    /**
     * Tell sites that upgraded from 2.x to enable the Google Routes API.
     *
     * Version 3 gets directions from the Routes API instead of the old
     * Directions service, and it must be enabled separately in Google
     * Cloud. Until it is, every directions request fails.
     *
     * @since  3.0.1
     * @return array The alert, or an empty array when it does not apply
     */
    public function get_routes_api_alert() {
        $updated_from = get_option( 'wpsl_updated_from', '' );

        // Only a site that came from a release without the Routes API.
        if ( ! $updated_from || version_compare( $updated_from, '3.0', '>=' ) ) {
            return [];
        }

        $settings = wpsl_get_service( 'wpsl_settings' );

        if ( $settings->get( 'api', 'active_map_service' ) !== 'gmaps' ) {
            return [];
        }

        // Directions that open on maps.google.com never touch the Routes API.
        if ( $settings->get( 'ux', 'direction_redirect' ) ) {
            return [];
        }

        $stored = get_option( 'wpsl_alerts', [] );

        if ( is_array( $stored ) && ! empty( $stored['routes_api']['dismissed'] ) ) {
            return [];
        }

        return [
            self::ROUTES_API_ALERT => [
                'name'        => __( 'Routes API', 'wp-store-locator' ),
                'dismissible' => true,
                'description' => sprintf(
                    /* translators: 1: opening link tag to the Routes API page in the Google Cloud console, 2: closing link tag */
                    __( 'Directions now go through the Google Routes API, which your Google Cloud project may not have enabled yet. %1$sEnable the Routes API%2$s, otherwise the Directions button on the map keeps failing.', 'wp-store-locator' ),
                    '<a href="' . esc_url( self::ROUTES_API_URL ) . '" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                ),
                'details'     => $this->get_routes_api_details(),
            ],
        ];
    }

    /**
     * The long version of the Routes API alert: why it changed, what breaks
     * without it, and the steps to fix it.
     *
     * @since  3.0.1
     * @return string HTML, limited to the tags the alerts list allows
     */
    private function get_routes_api_details() {
        $console_link     = '<a href="' . esc_url( self::ROUTES_API_URL ) . '" target="_blank" rel="noopener noreferrer">';
        $credentials_link = '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">';

        $html  = '<p><strong>' . esc_html__( 'Why this changed', 'wp-store-locator' ) . '</strong><br>';
        $html .= sprintf(
            /* translators: 1: opening link tag to the Directions API documentation, 2: closing link tag */
            esc_html__( 'Version 2 requested directions through the Directions service of the Maps JavaScript API, which runs on the legacy %1$sDirections API%2$s. Google no longer offers that API to new projects and is retiring it, so version 3 switched to its replacement, the Routes API. It is a separate product in Google Cloud: enabling the Maps JavaScript API does not enable it, and an existing browser key does not get access to it on its own.', 'wp-store-locator' ),
            '<a href="https://developers.google.com/maps/documentation/directions" target="_blank" rel="noopener noreferrer">',
            '</a>'
        ) . '</p>';

        $html .= '<p><strong>' . esc_html__( 'What happens until it is enabled', 'wp-store-locator' ) . '</strong><br>';
        $html .= esc_html__( 'The map, the search and the store list keep working. Clicking Directions shows a notice that the Routes API has to be enabled instead of the route.', 'wp-store-locator' ) . '</p>';

        $html .= '<p><strong>' . esc_html__( 'What to do', 'wp-store-locator' ) . '</strong></p>';
        $html .= '<ol>';
        $html .= '<li>' . sprintf(
            /* translators: 1: opening link tag to the Routes API page in the Google Cloud console, 2: closing link tag */
            esc_html__( 'Open the %1$sRoutes API%2$s page in the Google Cloud console and select the project that holds your Google Maps API keys.', 'wp-store-locator' ),
            $console_link,
            '</a>'
        ) . '</li>';
        $html .= '<li>' . esc_html__( 'Click Enable. The Routes API is billed through the same billing account as the Maps JavaScript API, so no new account is needed.', 'wp-store-locator' ) . '</li>';
        $html .= '<li>' . sprintf(
            /* translators: 1: opening link tag to the Credentials page in the Google Cloud console, 2: closing link tag */
            esc_html__( 'If the browser key has API restrictions, open %1$sCredentials%2$s, edit the key and add the Routes API to the allowed APIs.', 'wp-store-locator' ),
            $credentials_link,
            '</a>'
        ) . '</li>';
        $html .= '<li>' . esc_html__( 'Request directions for a store on your site to confirm the route renders, then dismiss this alert.', 'wp-store-locator' ) . '</li>';
        $html .= '</ol>';

        return $html;
    }

    /**
     * Report active add-ons that work, but are behind their latest release.
     *
     * Nothing here turns anything off: these add-ons keep every feature.
     * Anything below the compatibility floor is left to Legacy_Addons,
     * which reports it with a much stronger message.
     *
     * @since  3.0.0
     * @return array Alerts keyed by the add-on's plugin file
     */
    public function get_outdated_addon_alerts() {
        $dismissed = $this->get_dismissed_addons();
        $alerts    = [];

        foreach ( $this->get_outdated_addons() as $addon ) {
            if (
                isset( $dismissed[ $addon['file'] ]['dismissed_version'] )
                && version_compare( $addon['latest'], $dismissed[ $addon['file'] ]['dismissed_version'], '<=' )
            ) {
                continue;
            }

            $alerts[ $addon['file'] ] = [
                'name'        => $addon['name'],
                'dismissible' => true,
                'description' => sprintf(
                    /* translators: 1: add-on name, 2: installed version, 3: latest available version */
                    __( 'You are running %1$s %2$s. The latest version is %3$s.', 'wp-store-locator' ),
                    esc_html( $addon['name'] ),
                    esc_html( $addon['version'] ),
                    esc_html( $addon['latest'] )
                ) . ' ' . sprintf(
                    /* translators: 1: opening link tag to the Plugins screen, 2: closing link tag, 3: opening link tag to the wpstorelocator.co account page, 4: closing link tag */
                    __( '%1$sNo update showing%2$s? Download it from your %3$saccount page%4$s.', 'wp-store-locator' ),
                    '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">',
                    '</a>',
                    '<a target="_blank" rel="noopener noreferrer" href="' . esc_url( self::ACCOUNT_URL ) . '">',
                    '</a>'
                ),
            ];
        }

        return $alerts;
    }

    /**
     * The add-on version scan, cached.
     *
     * @since  3.0.0
     * @return array Entries as wpsl_get_outdated_addons() returns them
     */
    private function get_outdated_addons() {
        $cached = get_transient( self::OUTDATED_TRANSIENT );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $outdated = \wpsl_get_outdated_addons();

        set_transient( self::OUTDATED_TRANSIENT, $outdated, DAY_IN_SECONDS );

        return $outdated;
    }

    /**
     * Drop the cached add-on version scan.
     *
     * Hooked to plugin activation, deactivation and the end of an update, so
     * the alert clears as soon as the add-on it names is updated.
     *
     * @since  3.0.0
     * @return void
     */
    public function flush_outdated_addons() {
        delete_transient( self::OUTDATED_TRANSIENT );
    }

    /**
     * The stored outdated-add-on dismissals.
     *
     * @since  3.0.0
     * @return array Plugin file => [ 'dismissed_version' => string ]
     */
    private function get_dismissed_addons() {
        $stored = get_option( 'wpsl_alerts', [] );

        if ( ! is_array( $stored ) || ! isset( $stored['outdated_addons'] ) || ! is_array( $stored['outdated_addons'] ) ) {
            return [];
        }

        return $stored['outdated_addons'];
    }

    /**
     * Look up an outdated add-on by its plugin file.
     *
     * @since  3.0.0
     * @param  string $plugin_file The add-on's plugin file
     * @return array  The matching entry, or an empty array
     */
    private function outdated_addon_for( $plugin_file ) {
        foreach ( $this->get_outdated_addons() as $addon ) {
            if ( $addon['file'] === $plugin_file ) {
                return $addon;
            }
        }

        return [];
    }

    /**
     * Check if a newly activated plugin conflicts with WPSL
     *
     * @since  3.0.0
     * @param  string $plugin Plugin basename
     * @return void
     */
    public function check_activated_plugin( $plugin ) {
        if ( isset( $this->conflicting_plugins[ $plugin ] ) ) {
            $this->add_alert( $plugin );
        }
    }

    /**
     * Remove the alert when a conflicting plugin is deactivated
     *
     * @since  3.0.0
     * @param  string $plugin Plugin basename
     * @return void
     */
    public function handle_deactivated_plugin( $plugin ) {
        if ( ! isset( $this->conflicting_plugins[ $plugin ] ) ) {
            return;
        }

        $alerts = get_option( 'wpsl_alerts', [] );

        if ( isset( $alerts['plugin_conflicts'][ $plugin ] ) ) {
            unset( $alerts['plugin_conflicts'][ $plugin ] );
            update_option( 'wpsl_alerts', $alerts, false );
        }
    }

    /**
     * Check for existing active plugins that conflict with WPSL
     * Runs once when WPSL settings page is first loaded
     *
     * @since  3.0.0
     * @return void
     */
    public function check_existing_plugins() {
        $checked = get_option( 'wpsl_plugins_checked', false );
        
        if ( $checked ) {
            return;
        }

        $active_plugins = get_option( 'active_plugins', [] );
        
        foreach ( $active_plugins as $plugin ) {
            if ( isset( $this->conflicting_plugins[ $plugin ] ) ) {
                $this->add_alert( $plugin );
            }
        }
        
        update_option( 'wpsl_plugins_checked', true, false );
    }

    /**
     * Add a plugin conflict alert
     *
     * @since  3.0.0
     * @param  string $plugin_basename Plugin basename
     * @return void
     */
    private function add_alert( $plugin_basename ) {
        $alerts = get_option( 'wpsl_alerts', [] );
        
        // Ensure alerts is an array
        if ( ! is_array( $alerts ) ) {
            $alerts = [];
        }
        
        if ( ! isset( $alerts['plugin_conflicts'] ) || ! is_array( $alerts['plugin_conflicts'] ) ) {
            $alerts['plugin_conflicts'] = [];
        }
        
        $alerts['plugin_conflicts'][ $plugin_basename ] = [
            'dismissed' => false
        ];

        update_option( 'wpsl_alerts', $alerts, false );
    }

    /**
     * Get all active (non-dismissed) alerts
     *
     * The active plugin list is the source of truth: an alert is shown for every
     * conflicting plugin that is currently active, unless the user has explicitly
     * dismissed it (dismissed = true in wpsl_alerts). A missing or empty
     * wpsl_alerts option is treated the same as "no dismissals".
     *
     * @since  3.0.0
     * @return array Active alerts with details
     */
    public function get_active_alerts() {
        /**
         * Whether WPSL reports alerts at all. Returning false empties the
         * admin bar badge, the settings count and the Alerts section, and
         * skips all scanning.
         *
         * @since 3.0.0
         * @param bool $show_alerts Defaults to true.
         */
        if ( ! apply_filters( 'wpsl_show_alerts', true ) ) {
            return [];
        }

        $stored     = get_option( 'wpsl_alerts', [] );
        $conflicts  = isset( $stored['plugin_conflicts'] ) && is_array( $stored['plugin_conflicts'] )
            ? $stored['plugin_conflicts']
            : [];

        $active_plugins = get_option( 'active_plugins', [] );
        $active_alerts  = [];

        foreach ( $this->conflicting_plugins as $plugin_basename => $plugin_data ) {
            if ( ! in_array( $plugin_basename, $active_plugins, true ) ) {
                continue;
            }

            $dismissed = isset( $conflicts[ $plugin_basename ]['dismissed'] )
                ? $conflicts[ $plugin_basename ]['dismissed']
                : false;

            if ( ! $dismissed ) {
                $active_alerts[ $plugin_basename ] = $plugin_data;
            }
        }

        return array_merge(
            $active_alerts,
            $this->get_handler_alert(),
            $this->get_routes_api_alert(),
            $this->get_coordinate_alert(),
            $this->get_outdated_addon_alerts()
        );
    }

    /**
     * Get the count of active alerts
     *
     * @since  3.0.0
     * @return int Number of active alerts
     */
    public function get_alert_count() {
        return count( $this->get_active_alerts() );
    }

    /**
     * Dismiss an alert via AJAX
     *
     * @since  3.0.0
     * @return void
     */
    public function dismiss_alert() {
        check_ajax_referer( 'wpsl_dismiss_alert', 'nonce' );
        
        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-store-locator' ) ] );
        }
        
        $plugin_basename = isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '';
        
        if ( empty( $plugin_basename ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid plugin.', 'wp-store-locator' ) ] );
        }
        
        /*
         * Recorded against the count it was dismissed at, so fixing nothing
         * keeps it away while breaking more locations brings it back. Handled
         * before the lookups below, which only know about plugin files.
         */
        if ( self::COORDINATE_ALERT === $plugin_basename ) {
            $alerts = get_option( 'wpsl_alerts', [] );

            if ( ! is_array( $alerts ) ) {
                $alerts = [];
            }

            $alerts['coordinates'] = [
                'dismissed_count' => Geocode_Locations::count_problems(),
            ];

            update_option( 'wpsl_alerts', $alerts, false );

            wp_send_json_success( [
                'message' => __( 'Alert dismissed.', 'wp-store-locator' ),
                'count'   => $this->get_alert_count(),
            ] );
        }

        // A plain opt-out: once the API is enabled there is nothing to re-check.
        if ( self::ROUTES_API_ALERT === $plugin_basename ) {
            $alerts = get_option( 'wpsl_alerts', [] );

            if ( ! is_array( $alerts ) ) {
                $alerts = [];
            }

            $alerts['routes_api'] = [ 'dismissed' => true ];

            update_option( 'wpsl_alerts', $alerts, false );

            wp_send_json_success( [
                'message' => __( 'Alert dismissed.', 'wp-store-locator' ),
                'count'   => $this->get_alert_count(),
            ] );
        }

        $outdated_addon = $this->outdated_addon_for( $plugin_basename );

        if ( ! isset( $this->conflicting_plugins[ $plugin_basename ] ) && empty( $outdated_addon ) ) {
            wp_send_json_error( [ 'message' => __( 'Alert not found.', 'wp-store-locator' ) ] );
        }

        $alerts = get_option( 'wpsl_alerts', [] );

        if ( ! is_array( $alerts ) ) {
            $alerts = [];
        }

        if ( ! empty( $outdated_addon ) ) {
            if ( ! isset( $alerts['outdated_addons'] ) || ! is_array( $alerts['outdated_addons'] ) ) {
                $alerts['outdated_addons'] = [];
            }

            /*
             * Recorded against the release it was dismissed for, so putting
             * this away is not a permanent opt-out: the next release alerts
             * again.
             */
            $alerts['outdated_addons'][ $plugin_basename ] = [ 'dismissed_version' => $outdated_addon['latest'] ];
        } else {
            if ( ! isset( $alerts['plugin_conflicts'] ) || ! is_array( $alerts['plugin_conflicts'] ) ) {
                $alerts['plugin_conflicts'] = [];
            }

            $alerts['plugin_conflicts'][ $plugin_basename ] = [ 'dismissed' => true ];
        }

        update_option( 'wpsl_alerts', $alerts, false );

        wp_send_json_success( [
            'message' => __( 'Alert dismissed.', 'wp-store-locator' ),
            'count'   => $this->get_alert_count()
        ] );
    }
}