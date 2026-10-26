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
     * The reasons the survey offers.
     *
     * @since 3.1.1
     */
    const REASONS = [ 'not_working', 'too_complicated', 'better_plugin', 'missing_feature', 'temporary_deactivation', 'other' ];

    /**
     * The reasons that can ask to be contacted, and have the longer text box.
     *
     * @since 3.1.1
     */
    const CONTACT_REASONS = [ 'not_working', 'too_complicated' ];

    /**
     * The longest feedback text, and the longer limit for pasted error messages.
     *
     * @since 3.1.1
     */
    const FEEDBACK_MAX      = 1000;
    const FEEDBACK_MAX_LONG = 4000;

    /**
     * Constructor
     *
     * @since 2.2.240
     */
    public function __construct() {
        global $pagenow;

        if ( ! apply_filters( 'wpsl_exit_survey_is_live_site', $this->is_live_site() ) ) {
            return;
        }

        // The answer is posted here before the deactivation link is followed, see wpsl-exit-survey.js.
        if ( wp_doing_ajax() ) {
            add_action( 'wp_ajax_wpsl_exit_survey', [ $this, 'ajax_submit' ] );

            return;
        }

        if ( 'plugins.php' != $pagenow ) {
            return;
        }

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_footer',          [ $this, 'load_survey' ] );
    }

    /**
     * Send the survey answer to the feedback server.
     *
     * Posted from the survey before the plugin is deactivated. Until 3.1.1
     * the answer traveled in the deactivation URL, which would leave an email
     * address in the site's access logs and the browser history.
     *
     * @since  3.1.1
     * @return void
     */
    public function ajax_submit() {
        check_ajax_referer( 'wpsl_survey_nonce', 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( null, 403 );
        }

        $reason = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';

        if ( ! in_array( $reason, self::REASONS, true ) ) {
            wp_send_json_error( null, 400 );
        }

        $max      = in_array( $reason, self::CONTACT_REASONS, true ) ? self::FEEDBACK_MAX_LONG : self::FEEDBACK_MAX;
        $feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

        $args = [
            'reason'   => $reason,
            'feedback' => mb_substr( $feedback, 0, $max ),
        ];

        // Unknown slugs are dropped, the free text name only travels with 'other'.
        if ( 'better_plugin' === $reason ) {
            $competitor = isset( $_POST['competitor'] ) ? sanitize_key( wp_unslash( $_POST['competitor'] ) ) : '';

            if ( 'other' === $competitor ) {
                $args['competitor'] = $competitor;

                $competitor_other = isset( $_POST['competitor_other'] ) ? sanitize_text_field( wp_unslash( $_POST['competitor_other'] ) ) : '';

                if ( $competitor_other ) {
                    $args['competitor_other'] = mb_substr( $competitor_other, 0, 100 );
                }
            } else if ( isset( self::get_competitors()[ $competitor ] ) ) {
                $args['competitor'] = $competitor;
            }
        }

        if ( 'not_working' === $reason ) {
            $sub_reason = isset( $_POST['sub_reason'] ) ? sanitize_key( wp_unslash( $_POST['sub_reason'] ) ) : '';

            // Unknown slugs are dropped.
            if ( isset( self::get_not_working_reasons()[ $sub_reason ] ) ) {
                $args['sub_reason'] = $sub_reason;
            }

            // Only the opt-in travels in the request, the details are collected here.
            if ( ! empty( $_POST['debug'] ) ) {
                $args['debug'] = $this->get_debug_data();
            }
        }

        // Only sent when the user ticked "You can email me about this".
        if ( in_array( $reason, self::CONTACT_REASONS, true ) && ! empty( $_POST['email'] ) ) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ) );

            if ( is_email( $email ) ) {
                $args['email'] = $email;
            }
        }

        $response = wp_remote_post( $this->server, [
            'timeout'    => 5,
            'body'       => $args,
            'headers'    => [ 'Accept' => 'application/json' ],
            'user-agent' => 'WPSL/' . WPSL_VERSION_NUM,
        ] );

        $code = wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
            wp_send_json_error();
        }

        wp_send_json_success();
    }

    /**
     * The "You can email me about this" checkbox and its email field.
     *
     * The field holds the email of the logged-in user, which can be changed.
     * Nothing is sent unless the checkbox is ticked.
     *
     * @since  3.1.1
     * @param  string $id A unique part for the field ids, one per reason.
     * @return void
     */
    public static function contact_fields( $id ) {
        $user  = wp_get_current_user();
        $email = ( $user && $user->exists() ) ? $user->user_email : '';
        ?>
        <div class="wpsl-survey-contact">
            <label class="wpsl-survey-contact-toggle" for="wpsl-survey-contact-<?php echo esc_attr( $id ); ?>">
                <input type="checkbox" class="wpsl-survey-contact-check" id="wpsl-survey-contact-<?php echo esc_attr( $id ); ?>" value="1">
                <?php esc_html_e( 'You can email me about this', 'wp-store-locator' ); ?>
            </label>
            <div class="wpsl-survey-contact-email">
                <label class="screen-reader-text" for="wpsl-survey-email-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Email address', 'wp-store-locator' ); ?></label>
                <input type="email" class="wpsl-survey-email" id="wpsl-survey-email-<?php echo esc_attr( $id ); ?>" maxlength="254" value="<?php echo esc_attr( $email ); ?>" data-default="<?php echo esc_attr( $email ); ?>">
                <p class="wpsl-survey-hint"><?php esc_html_e( 'We only use it to reply about this feedback.', 'wp-store-locator' ); ?></p>
            </div>
        </div>
        <?php
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