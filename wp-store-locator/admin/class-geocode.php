<?php
/**
 * Legacy compatibility for the removed v2.x WPSL_Geocode class.
 *
 * CSV Manager 1.x instantiates `new WPSL_Geocode()` and require_once's this
 * exact path, so removing it fatals every wp-admin page. Methods are placeholders;
 * \WPSL\Core\Legacy_Addons loads this on demand and hides the CSV Manager menu.
 *
 * DO NOT DELETE or move under includes/ — the path is hardcoded in CSV Manager.
 *
 * @package WP_Store_Locator
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSL_Geocode' ) ) {

    /**
     * Inert stand-in for the removed v2.x geocoder. Do not add behaviour here.
     */
    class WPSL_Geocode {

        /**
         * @param  array $store_data Ignored.
         * @return array Empty response.
         */
        public function get_latlng( $store_data = [] ) {
            return [];
        }

        /**
         * @param  array $location Ignored.
         * @return array Empty lat/lng pair.
         */
        public function format_latlng( $location = [] ) {
            return [ 'lat' => '', 'lng' => '' ];
        }

        /**
         * @param  array $geocode_response Ignored.
         * @return string Empty string.
         */
        public function filter_country_name( $geocode_response = [] ) {
            return '';
        }

        /**
         * @param  array $geocode_response Ignored.
         * @param  bool  $echo            Ignored.
         * @return string Empty string.
         */
        public function check_geocode_error_msg( $geocode_response = [], $echo = true ) {
            return '';
        }
    }
}