<?php
/**
 * Geocode store locations
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WPSL_Geocode' ) ) {
        
	class WPSL_Geocode {
                
        /** 
         * Check if we need to run a geocode request or use the current location data.
         * 
         * The latlng value is only present if the user provided it himself, or used the preview
         * on the map. Otherwise the latlng will be missing and we need to geocode the supplied address.
         * 
         * @since 2.0.0
         * @param  integer $post_id    Store post ID
         * @param  array   $store_data The store data
         * @return void
         */
		public function check_geocode_data( $post_id, $store_data ) {
            
            $location_data = array();
            
            // Check if the latlng data is valid.
            $latlng = $this->validate_latlng( $store_data['lat'], $store_data['lng'] );

            // If we don't have a valid latlng value, we geocode the supplied address to get one.
            if ( !$latlng ) {
                $response = $this->geocode_location( $post_id, $store_data );
                
                if ( empty( $response ) ) {
                    return;
                }
                
                $location_data['country_iso'] = $response['country_iso'];
                $location_data['latlng']      = $response['latlng'];
            } else {
                $location_data['latlng']      = $latlng;
            }

            // Restrict the latlng to a max of 6 decimals.
            $location_data['latlng'] = $this->format_latlng( $location_data['latlng'] );

            $location_data['lat'] = $location_data['latlng']['lat'];
            $location_data['lng'] = $location_data['latlng']['lng'];
            
            $this->save_store_location( $post_id, $location_data );
        }
        
        /** 
         * Geocode the store location.
         * 
         * @since 1.0.0
         * @param  integer $post_id    Store post ID
         * @param  array   $store_data The submitted store data ( address, city, country etc )
         * @return void
         */
		public function geocode_location( $post_id, $store_data ) {
                        
			$geocode_response = $this->get_latlng( $store_data );

            if ( isset( $geocode_response['status'] ) ) {
                switch ( $geocode_response['status'] ) {
                    case 'OK':
                        $country = $this->filter_country_name( $geocode_response );

                        $location_data = array(
                            'country_iso' => $country['short_name'],
                            'latlng'      => $this->format_latlng( $geocode_response['results'][0]['geometry']['location'] )
                        );

                        return $location_data;
                    case 'ZERO_RESULTS':
                        $msg = __( 'The Google Geocoding API returned no results for the supplied address. Please change the address and try again.', 'wp-store-locator' );
                        break;
                    case 'OVER_QUERY_LIMIT':
                        /* translators: %1$s: opening link tag, %2$s: closing link tag */
                        $msg = sprintf( __( 'You have reached the daily allowed geocoding limit, you can read more %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://developers.google.com/maps/documentation/geocoding/#Limits">', '</a>' );
                        break;
                    case 'REQUEST_DENIED':
                        /* translators: %s: error details */
                        $msg = sprintf( __( 'The Google Geocoding API returned REQUEST_DENIED. %s', 'wp-store-locator' ), $this->check_geocode_error_msg( $geocode_response ) );
                        break;
                    default:
                        $msg = __( 'The Google Geocoding API failed to return valid data, please try again later.', 'wp-store-locator' );
                        break;
                }
            } else {
                $msg = $geocode_response;
            }
            
            // Handle the geocode code errors messages.
            if ( !empty( $msg ) ) {
                $this->geocode_failed( $msg, $post_id );
            }
		}
        
        /**
         * Check if the response from the Geocode API contains an error message.
         * 
         * @since 2.1.0
         * @param  array  $geocode_response The response from the Geocode API.
         * @return string $error_msg        The error message, or empty if none exists.  
         */
        public function check_geocode_error_msg( $geocode_response, $inc_breaks = true ) {
            
            $breaks = ( $inc_breaks ) ? '<br><br>' : '';
            
            if ( isset( $geocode_response['error_message'] ) && $geocode_response['error_message'] ) {

                // If the problem is IP based, then show a different error msg.
                if ( strpos( $geocode_response['error_message'], 'IP' ) !== false  ) {
                    /* translators: %1$s: line break, %2$s: error message, %3$s: line break, %4$s: opening referrer link tag, %5$s: closing link tag, %6$s: opening console link tag, %7$s: closing link tag */
                    $error_msg = sprintf( __( '%1$sError message: %2$s. %3$s Make sure the IP address mentioned in the error matches with the IP set as the %4$sreferrer%5$s for the server API key in the %6$sGoogle API Console%7$s.', 'wp-store-locator' ), $breaks, $this->clickable_error_links( $geocode_response['error_message'] ), $breaks, '<a href="https://wpstorelocator.co/document/create-google-api-keys/#server-key-referrer">', '</a>', '<a href="https://console.developers.google.com">', '</a>' );
                } else {
                    /* translators: %1$s: line break, %2$s: error message, %3$s: line break, %4$s: opening troubleshooting link tag, %5$s: closing link tag, %6$s: opening support link tag, %7$s: closing link tag */
                    $error_msg = sprintf( __( '%1$sError message: %2$s %3$s Check if your issue is covered in the %4$stroubleshooting%5$s section, if not, then please open a %6$ssupport ticket%7$s.', 'wp-store-locator' ),  $breaks, $this->clickable_error_links( $geocode_response['error_message'] ), $breaks, '<a href="https://wpstorelocator.co/document/create-google-api-keys/#troubleshooting">', '</a>', '<a href="https://wpstorelocator.co/support/">', '</a>' );
                }
            } else {
                $error_msg = '';
            }
            
            return $error_msg;
        }
        
        /** 
         * Make the API call to Google to geocode the address.
         * 
         * @since 1.0.0
         * @param  array        $store_data   The store data
         * @return array|string $geo_response The response from the Google Geocode API, or the wp_remote_get error message.
         */
        public function get_latlng( $store_data ) {

            $address  = $this->create_geocode_address( $store_data );
            $response = wpsl_call_geocode_api( $address );

            if ( is_wp_error( $response ) ) {
                /* translators: %1$s: error message, %2$s: line break */
                $geo_response = sprintf( __( 'Something went wrong connecting to the Google Geocode API: %1$s %2$s Please try again later.', 'wp-store-locator' ), $response->get_error_message(), '<br><br>' );
            } else if ( $response['response']['code'] == 500 ) {
                /* translators: %1$s: error code, %2$s: error message, %3$s: line break */
                $geo_response = sprintf( __( 'The Google Geocode API reported the following problem: error %1$s %2$s %3$s Please try again later.', 'wp-store-locator' ), $response['response']['code'], $response['response']['message'], '<br><br>' );
            } else if ( $response['response']['code'] == 400 ) {
                $data_issue = '';

                // Check on which page the 400 error was triggered, and based on that adjust the msg.
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking which admin page we're on, not processing form data
                if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl_csv' ) {
                    /* translators: %1$s: opening link tag, %2$s: closing link tag */
                    $data_issue = sprintf( __( 'You can fix this by making sure the CSV file uses %1$sUTF-8 encoding%2$s.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/csv-manager/#utf8">', '</a>' );
                } else if ( !$address ) {
                    $data_issue = __( 'You need to provide the details for either the address, city, state or country before the API can return coordinates.', 'wp-store-locator' ); // this is only possible if the required fields are disabled with custom code.
                }

                /* translators: %1$s: error code, %2$s: error message, %3$s: line break, %4$s: additional data issue message */
                $geo_response = sprintf( __( 'The Google Geocode API reported the following problem: error %1$s %2$s %3$s %4$s', 'wp-store-locator' ), $response['response']['code'], $response['response']['message'], '<br><br>', $data_issue );
            } else if ( $response['response']['code'] != 200 ) {
                /* translators: %1$s: error code, %2$s: error message, %3$s: line break, %4$s: opening support link tag, %5$s: closing link tag */
                $geo_response = sprintf( __( 'The Google Geocode API reported the following problem: error %1$s %2$s %3$s Please contact %4$ssupport%5$s if the problem persists.', 'wp-store-locator' ), $response['response']['code'], $response['response']['message'], '<br><br>', '<a href="https://wpstorelocator.co/support/">', '</a>' );
            } else {
                $geo_response = json_decode( $response['body'], true );
            }

            return $geo_response;
        }
        
        /** 
         * Create the address we need to Geocode.
         * 
         * @since 2.1.0
         * @param  array  $store_data      The provided store data
         * @return string $geocode_address The address we are sending to the Geocode API separated by ,
         */
        public function create_geocode_address( $store_data ) {
            
            $address       = array();
            $address_parts = array( 'address', 'city', 'state', 'zip', 'country' );
            
            foreach ( $address_parts as $address_part ) {
                if ( isset( $store_data[$address_part] ) && $store_data[$address_part] ) {
                    $address[] = trim( $store_data[$address_part] );
                }
            }

            $geocode_address = implode( ',', $address );

            return $geocode_address;
        }
        
        /** 
         * If there is a problem with the geocoding then we save the notice and change the post status to pending.
         * 
         * @since 2.0.0
         * @param  string  $msg     The geocode error message
         * @param  integer $post_id Store post ID
         * @return void
         */
        public function geocode_failed( $msg, $post_id ) {
            
            global $wpsl_admin;
            
            $wpsl_admin->notices->save( 'error', $msg );
            $wpsl_admin->metaboxes->set_post_pending( $post_id );
        }

        /** 
         * Save the store location data.
         * 
         * @since 2.0.0
         * @param  integer $post_id       Store post ID
         * @param  array   $location_data The country code and latlng
         * @return void
         */
		public function save_store_location( $post_id, $location_data ) {
            
            if ( isset( $location_data['country_iso'] ) && ( !empty( $location_data['country_iso'] ) ) ) {
                update_post_meta( $post_id, 'wpsl_country_iso', $location_data['country_iso'] );
            }
            
            update_post_meta( $post_id, 'wpsl_lat', $location_data['latlng']['lat'] );
            update_post_meta( $post_id, 'wpsl_lng', $location_data['latlng']['lng'] );
        }
        
        /** 
         * Make sure the latlng value has a max of 6 decimals.
         * 
         * @since 2.0.0
         * @param  array $latlng The latlng data
         * @return array $latlng The formatted latlng
         */
		public function format_latlng( $latlng ) {
            
            foreach ( $latlng as $key => $value ) {
                if ( strlen( substr( strrchr( $value, '.' ), 1 ) ) > 6 ) {
                    $latlng[$key] = round( $value, 6 );
                }
            }
            
            return $latlng;
        }

        /**
         * Filter out the two letter country code from the Geocode API response.
         *
         * @since 1.0.0
         * @param  array $response The full API geocode response
         * @return array $country  The country ISO code and full name
         */
        public function filter_country_name( $response ) {

            $length = count( $response['results'][0]['address_components'] );

            $country = array();

            // Loop over the address components until we find the country, political part.
            for ( $i = 0; $i < $length; $i++ ) {
                $address_component = $response['results'][0]['address_components'][$i]['types'];

                if ( $address_component[0] == 'country' && $address_component[1] == 'political' ) {
                    $country = $response['results'][0]['address_components'][$i];

                    break;
                }
            }

            return $country;
        }
        
        /** 
         * Validate the latlng values.
         * 
         * @since 1.0.0
         * @param  string        $lat    The latitude value
         * @param  string        $lng    The longitude value
         * @return boolean|array $latlng The validated latlng values or false if it fails
         */
		public function validate_latlng( $lat, $lng ) {
            
            if ( !is_numeric( $lat ) || ( $lat > 90 ) || ( $lat < -90 ) ) {
                return false;
            }

            if ( !is_numeric( $lng ) || ( $lng > 180 ) || ( $lng < -180 ) ) {
                return false;
            }

            $latlng = array( 
                'lat' => $lat,
                'lng' => $lng
            );

            return $latlng;
		}

		/**
         * Error messages returned by the Google Maps API
         * don't always contain clickable links.
         *
         * They now just look like this http://g.co/dev/maps-no-account
         * and are not clickable. To change this we wrap an href around it.
         *
         * @since 2.2.22
         * @return void
         */
		public function clickable_error_links( $msg ) {

            // Make sure the URLS aren't clickable yet. They aren't at the moment, but maybe Google changes this in the future.
            if ( strpos( $msg,'href' ) === false ) {
                preg_match_all( '#\bhttp(s?)?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $msg, $match );

                foreach ( $match[0] as $k => $url ) {
                    $msg = str_replace( $url, '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>', $msg );
                }
            }

            return $msg;
        }
    }
}