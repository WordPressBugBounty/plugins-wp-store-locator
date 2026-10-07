<?php
/**
 * WPSL Exit Survey
 *
 * @author Tijmen Smit
 * @since  2.2.240
 */

namespace WPSL\Admin\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Exit_Survey {

    /**
     * API server URL for feedback submission
     * 
     * @var string
     */
    protected $server = 'https://feedback.wpstorelocator.co/api';

    /**
     * Constructor
     *
     * @since 2.2.240
     */
    public function __construct() {
        global $pagenow;

        if ( 'plugins.php' != $pagenow ) {
            return;
        }

        if ( ! apply_filters( 'wpsl_exit_survey_is_live_site', $this->is_live_site() ) ) {
            return;
        }

        add_action( 'deactivate_' . WPSL_BASENAME, [ $this, 'deactivate' ] );
        add_action( 'admin_enqueue_scripts',       [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_footer',                [ $this, 'load_survey' ] );
    }

    /**
     * Runs when the WP Store Locator
     * plugin is deactivated.
     */
    public function deactivate() {
        if ( empty( $_REQUEST['wpsl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['wpsl_nonce'] ) ), 'wpsl_survey_nonce' ) ) {
            return;
        }

        $reason   = isset( $_REQUEST['wpsl_deactivation_reason'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpsl_deactivation_reason'] ) ) : '';
        $feedback = isset( $_REQUEST['wpsl_deactivation_feedback'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['wpsl_deactivation_feedback'] ) ) : '';

        if ( $reason ) {
            $args = [
                'reason'   => $reason,
                'feedback' => $feedback
            ];

            // Unknown slugs are dropped, the free text name only travels with 'other'.
            if ( 'better_plugin' === $reason ) {
                $competitor = isset( $_REQUEST['wpsl_competitor'] ) ? sanitize_key( wp_unslash( $_REQUEST['wpsl_competitor'] ) ) : '';

                if ( 'other' === $competitor ) {
                    $args['competitor'] = $competitor;

                    $competitor_other = isset( $_REQUEST['wpsl_competitor_other'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpsl_competitor_other'] ) ) : '';

                    if ( $competitor_other ) {
                        $args['competitor_other'] = $competitor_other;
                    }
                } else if ( isset( self::get_competitors()[ $competitor ] ) ) {
                    $args['competitor'] = $competitor;
                }
            }

            if ( 'not_working' === $reason ) {
                $sub_reason = isset( $_REQUEST['wpsl_not_working'] ) ? sanitize_key( wp_unslash( $_REQUEST['wpsl_not_working'] ) ) : '';

                // Unknown slugs are dropped.
                if ( isset( self::get_not_working_reasons()[ $sub_reason ] ) ) {
                    $args['sub_reason'] = $sub_reason;
                }

                // Only the opt-in travels in the request, the details are collected here.
                if ( ! empty( $_REQUEST['wpsl_debug'] ) ) {
                    $args['debug'] = $this->get_debug_data();
                }
            }

            $response = wp_remote_post( $this->server, [
                    'method'      => 'POST',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'body'        => $args,
                    'user-agent'  => 'WPSL/' . WPSL_VERSION_NUM . ''
                ]
            );
        }
    }

    /**
     * The plugins offered in the dropdown under 'I found a better plugin'.
     *
     * @since  3.0.0
     * @return array
     */
    public static function get_competitors() {
        return [
            'agile_store_locator' => 'Agile Store Locator',
            'elfsight'            => 'Elfsight',
            'mappress'            => 'MapPress',
            'storemapper'         => 'Storemapper',
            'storerocket'         => 'StoreRocket',
            'wp_go_maps'          => 'WP Go Maps',
            'wp_map_block'        => 'WP Map Block',
            'wp_maps'             => 'WP Maps',
        ];
    }

    /**
     * The options in the dropdown under 'I couldn't get the plugin to work'.
     *
     * @since  3.1.0
     * @return array The reason slug and its label.
     */
    public static function get_not_working_reasons() {
        return [
            'map_error'      => __( 'The map doesn\'t load or shows an error', 'wp-store-locator' ),
            'save_location'  => __( 'I can\'t add or save a location', 'wp-store-locator' ),
            'wrong_location' => __( 'Locations show up in the wrong place', 'wp-store-locator' ),
            'search_results' => __( 'Search returns no or wrong results', 'wp-store-locator' ),
            'blank_page'     => __( 'The store locator page is blank', 'wp-store-locator' ),
            'conflict'       => __( 'It broke my theme\'s layout or conflicts with another plugin', 'wp-store-locator' ),
            'site_error'     => __( 'It caused a site error or crash', 'wp-store-locator' ),
            'api_key'        => __( 'Setting up the Google API key was too complicated', 'wp-store-locator' ),
            'other'          => __( 'Something else', 'wp-store-locator' ),
        ];
    }

    /**
     * The longest status report the survey sends.
     *
     * @since 3.1.0
     */
    const REPORT_MAX = 12000;

    /**
     * Collect the technical details a visitor opted in to share.
     *
     * The status report from the Tools page, without the 
     * site address and the start location.
     *
     * @since  3.1.0
     * @return array The details, which are sent with the survey answer.
     */
    public function get_debug_data() {
        $data = [];

        // Only registered on a deactivation that opted in, see the admin service provider.
        if ( function_exists( 'wpsl_container' ) && wpsl_container()->has( 'status_report' ) ) {
            $report = wpsl_get_service( 'status_report' )->get_report( null, true );

            if ( strlen( $report ) > self::REPORT_MAX ) {
                $report = substr( $report, 0, self::REPORT_MAX ) . "\n\n[ truncated ]";
            }

            $data['report'] = $report;
        }

        /**
         * Filter the technical details sent with the exit survey, when the visitor opted in.
         *
         * @since 3.1.0
         * @param array $data The details, the status report under 'report'.
         */
        return apply_filters( 'wpsl_exit_survey_debug_data', $data );
    }

    /**
     * Load the exit survey template.
     */
    public function load_survey() {
        require_once( WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/exit-survey.php' );
    }

    /**
     * Check if the current URL is for a live site (not local, not staging).
     *
     * Based on rocket_is_live_site() from /wp-rocket/inc/functions/api.php
     *
     * @author Remy Perona
     * @return bool True if live, false otherwise.
     */
    private function is_live_site() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( ! $host ) {
            return false;
        }

        // Check for local development sites.
        $local_tlds = [
            '127.0.0.1',
            'localhost',
            '.local',
            '.test',
            '.docksal',
            '.docksal.site',
            '.dev.cc',
            '.lndo.site',
        ];

        foreach ( $local_tlds as $local_tld ) {
            if ( $host === $local_tld ) {
                return false;
            }

            // Check the TLD.
            if ( substr( $host, -strlen( $local_tld ) ) === $local_tld ) {
                return false;
            }
        }

        // Check for staging sites.
        $staging = [
            '.wpengine.com',
            '.wpenginepowered.com',
            '.pantheonsite.io',
            '.flywheelsites.com',
            '.flywheelstaging.com',
            '.kinsta.com',
            '.kinsta.cloud',
            '.cloudwaysapps.com',
            '.azurewebsites.net',
            '.wpserveur.net',
            '-liquidwebsites.com',
            '.myftpupload.com',
            '.dream.press',
            '.sg-host.com',
            '.platformsh.site',
            '.wpstage.net',
            '.bigscoots-staging.com',
            '.wpsc.site',
            '.runcloud.link',
            '.onrocket.site',
            '.singlestaging.com',
            '.myraidbox.de',
        ];

        foreach ( $staging as $partial_host ) {
            if ( strpos( $host, $partial_host ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enqueue the scripts required for the exit survey.
     *
     * Public because it's registered as an 'admin_enqueue_scripts' callback.
     *
     * @since  2.2.240
     * @return void
     */
    public function enqueue_scripts() {
        $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        // The minified stylesheet only lives in the dist folder, see the assets manager.
        $css_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';

        wp_enqueue_script( 'mircomodal', plugins_url( 'assets/src/admin/js/micromodal.min.js', WPSL_PLUGIN_FILE ), '', '0.4.10', true ); //@see https://micromodal.vercel.app/
        wp_enqueue_script( 'wpsl-exit-survey', plugins_url( 'assets/src/admin/js/wpsl-exit-survey'. $min .'.js', WPSL_PLUGIN_FILE ), [ 'jquery', 'mircomodal' ], WPSL_VERSION_NUM, true );
        wp_enqueue_style( 'wpsl-exit-survey', plugins_url( $css_base . 'admin/css/micromodal'. $min .'.css', WPSL_PLUGIN_FILE ), '', WPSL_VERSION_NUM );
        wp_enqueue_style( 'buttons' );
    }
}

// Initialize the class
new Exit_Survey();