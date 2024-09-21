<?php
/**
 * WPSL Exit Survey
 *
 * @author Tijmen Smit
 * @since  2.2.240
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WPSL_Exit_Survey' ) ) {

    class WPSL_Exit_Survey {

        protected $server = 'https://feedback.wpstorelocator.co/api';

        function __construct() {

            global $pagenow;

            if ( 'plugins.php' != $pagenow || ! $this->is_live_site() ) {
                return;
            }

            add_action( 'admin_enqueue_scripts',       array( $this, 'enqueue_scripts' ) );
            add_action( 'admin_footer',                array( $this, 'load_survey' ) );
            add_action( 'deactivate_' . WPSL_BASENAME, array( $this, 'deactivate' ) );
        }

        /**
         * Runs when the WP Store Locator
         * plugin is deactivated.
         *
         * @return void
         */
        public function deactivate() {

            if ( empty( $_REQUEST['wpsl_nonce'] ) || ! wp_verify_nonce( $_REQUEST['wpsl_nonce'], 'wpsl_survey_nonce' ) ) {
                return;
            }

            $reason   = ( isset( $_REQUEST['wpsl_deactivation_reason'] ) ) ? $_REQUEST['wpsl_deactivation_reason'] : '';
            $feedback = ( isset( $_REQUEST['wpsl_deactivation_feedback'] ) ) ? $_REQUEST['wpsl_deactivation_feedback'] : '';

            if ( $reason ) {
                $args = array(
                    'reason'   => $reason,
                    'feedback' => $feedback
                );

                $response = wp_remote_post( $this->server, array(
                        'method'      => 'POST',
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'body'        => $args,
                        'user-agent'  => 'WPSL/' . WPSL_VERSION_NUM . ''
                    )
                );
            }
        }

        /**
         * Load the exit survey template.
         */
        public function load_survey() {
            require_once 'templates/exit-survey.php';
        }

        /**
         * Check if the current URL is for a live site (not local, not staging).
         *
         * Based on rocket_is_live_site() from /wp-rocket/inc/functions/api.php
         *
         * @author Remy Perona
         * @return bool True if live, false otherwise.
         */
        public function is_live_site() {

            $host = wp_parse_url( home_url(), PHP_URL_HOST );

            if ( ! $host ) {
                return false;
            }

            // Check for local development sites.
            $local_tlds = array(
                '127.0.0.1',
                'localhost',
                '.local',
                '.test',
                '.docksal',
                '.docksal.site',
                '.dev.cc',
                '.lndo.site',
            );

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
            $staging = array(
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
            );

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
         * @since  2.2.240
         * @return void
         */
        public function enqueue_scripts() {

            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

            wp_enqueue_script( 'mircomodal', plugins_url( '/js/micromodal.min.js', __FILE__ ), '', '0.4.10', true ); //@see https://micromodal.vercel.app/
            wp_enqueue_script( 'wpsl-exit-survey', plugins_url( '/js/wpsl-exit-survey'. $min .'.js', __FILE__ ), array( 'jquery', 'mircomodal' ), WPSL_VERSION_NUM, true );
            wp_enqueue_style( 'wpsl-exit-survey', plugins_url( '/css/micromodal'. $min .'.css', __FILE__ ), '', WPSL_VERSION_NUM );
            wp_enqueue_style( 'buttons' );
        }
    }

    new WPSL_Exit_Survey();
}