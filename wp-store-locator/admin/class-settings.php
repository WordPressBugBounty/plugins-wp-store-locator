<?php
/**
 * Handle the plugin settings.
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WPSL_Settings' ) ) {
    
	class WPSL_Settings {
                        
        public function __construct() {
            
            $this->manually_clear_transient();

            add_action( 'wp_ajax_validate_server_key',        array( $this, 'ajax_validate_server_key' ) );
            add_action( 'admin_init',                         array( $this, 'register_settings' ) );
            add_action( 'admin_init',                         array( $this, 'maybe_flush_rewrite_and_transient' ) );
        }

        /**
         * Determine if we need to clear the autoload transient.
         * 
         * User can do this manually from the 'Tools' section on the settings page. 
         * 
         * @since 2.0.0
         * @return void
         */
        public function manually_clear_transient() {
            
            global $wpsl_admin;
            
            if ( isset( $_GET['action'] ) && $_GET['action'] == 'clear_wpsl_transients' && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'clear_transients' ) ) {
                $wpsl_admin->delete_autoload_transient();
                
                $msg = __( 'WP Store Locator Transients Cleared', 'wp-store-locator' );
                $wpsl_admin->notices->save( 'update', $msg );
                
                /* 
                 * Make sure the &action=clear_wpsl_transients param is removed from the url.
                 * 
                 * Otherwise if the user later clicks the 'Save Changes' button, 
                 * and the &action=clear_wpsl_transients param is still there it 
                 * will show two notices 'WP Store Locator Transients Cleared' and 'Settings Saved'.
                 */
                wp_safe_redirect( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) );
                exit;
            }
        }

        /**
         * Register the settings.
         * 
         * @since 2.0.0
         * @return void
         */
        public function register_settings() {
            register_setting( 'wpsl_settings', 'wpsl_settings', array( $this, 'sanitize_settings' ) );
        }       
            
        /**
         * Sanitize the submitted plugin settings.
         * 
         * @since 1.0.0
         * @return array $output The setting values
         */
		public function sanitize_settings() {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification is handled by WordPress register_setting()
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- All input is validated and sanitized appropriately throughout this method

            global $wpsl_settings, $wpsl_admin;

            $ux_absints = array(
                'height',
                'infowindow_width',
                'search_width',
                'label_width'
            );
            
            $marker_effects = array(
                'bounce',
                'info_window',
                'ignore'
            );
            
            $ux_checkboxes = array(
                'new_window',
                'reset_map',
                'listing_below_no_scroll',
                'direction_redirect',
                'more_info',
                'store_url',
                'phone_url',
                'marker_streetview',
                'marker_zoom_to',
                'mouse_focus',
                'reset_map',
                'show_contact_details',
                'clickable_contact_details',
                'hide_country',
                'hide_distance'
            );

            /*
             * If the provided server key is different then the existing value,
             * then we test if it's valid by making a call to the Geocode API.
             */
            $api_server_key = isset( $_POST['wpsl_api']['server_key'] ) ? sanitize_text_field( $_POST['wpsl_api']['server_key'] ) : '';

            if ( $api_server_key && $wpsl_settings['api_server_key'] != $api_server_key || !get_option( 'wpsl_valid_server_key' ) ) {
                $this->validate_server_key( $api_server_key );
            }

			$output['api_server_key']        = $api_server_key;
            $output['api_browser_key']       = isset( $_POST['wpsl_api']['browser_key'] ) ? sanitize_text_field( $_POST['wpsl_api']['browser_key'] ) : '';
			$output['api_language']          = isset( $_POST['wpsl_api']['language'] ) ? wp_filter_nohtml_kses( $_POST['wpsl_api']['language'] ) : '';
			$output['api_region']            = isset( $_POST['wpsl_api']['region'] ) ? wp_filter_nohtml_kses( $_POST['wpsl_api']['region'] ) : '';
            $output['api_geocode_component'] = isset( $_POST['wpsl_api']['geocode_component'] ) ? 1 : 0;

            $output['autocomplete'] = isset( $_POST['wpsl_search']['autocomplete'] ) ? 1 : 0;

            // Check the search filter.
            $output['results_dropdown']     = isset( $_POST['wpsl_search']['results_dropdown'] ) ? 1 : 0;
            $output['radius_dropdown']      = isset( $_POST['wpsl_search']['radius_dropdown'] ) ? 1 : 0;
            $output['category_filter']      = isset( $_POST['wpsl_search']['category_filter'] ) ? 1 : 0;
            $output['category_filter_type'] = ( isset( $_POST['wpsl_search']['category_filter_type'] ) && $_POST['wpsl_search']['category_filter_type'] == 'dropdown' ) ? 'dropdown' : 'checkboxes';
            
            $output['distance_unit'] = ( isset( $_POST['wpsl_search']['distance_unit'] ) && $_POST['wpsl_search']['distance_unit'] == 'km' ) ? 'km' : 'mi';
			
			// Check for a valid max results value, otherwise we use the default.
			if ( !empty( $_POST['wpsl_search']['max_results'] ) ) {
				$output['max_results'] = sanitize_text_field( $_POST['wpsl_search']['max_results'] );
			} else {
				$this->settings_error( 'max_results' );
				$output['max_results'] = wpsl_get_default_setting( 'max_results' );
			}
			
			// See if a search radius value exist, otherwise we use the default.
			if ( !empty( $_POST['wpsl_search']['radius'] ) ) {
				$output['search_radius'] = sanitize_text_field( $_POST['wpsl_search']['radius'] );
			} else {
				$this->settings_error( 'search_radius' );
				$output['search_radius'] = wpsl_get_default_setting( 'search_radius' );
			}

            $output['force_postalcode'] = isset( $_POST['wpsl_search']['force_postalcode'] ) ? 1 : 0;

			// Check if we have a valid zoom level, it has to be between 1 or 12. If not set it to the default of 3.
			$output['zoom_level'] = isset( $_POST['wpsl_map']['zoom_level'] ) ? wpsl_valid_zoom_level( $_POST['wpsl_map']['zoom_level'] ) : wpsl_get_default_setting( 'zoom_level' );	
            
            // Check for a valid max auto zoom level.
            $max_zoom_levels = wpsl_get_max_zoom_levels();
            
            if ( isset( $_POST['wpsl_map']['max_auto_zoom'] ) && in_array( absint( $_POST['wpsl_map']['max_auto_zoom'] ), $max_zoom_levels ) ) {
                $output['auto_zoom_level'] = $_POST['wpsl_map']['max_auto_zoom'];
            } else {
                $output['auto_zoom_level'] = wpsl_get_default_setting( 'auto_zoom_level' );
            }

            if ( isset( $_POST['wpsl_map']['start_name'] ) ) {
                $output['start_name'] = sanitize_text_field( $_POST['wpsl_map']['start_name'] );
            } else {
                $output['start_name'] = '';
            }

			// If no location name is then we also empty the latlng values from the hidden input field.
			if ( empty( $output['start_name'] ) ) {
				$this->settings_error( 'start_point' );
                $output['start_latlng'] = '';
			} else {

                /*
                 * If the start latlng is empty, but a start location name is provided, 
                 * then make a request to the Geocode API to get it.
                 * 
                 * This can only happen if there is a JS error in the admin area that breaks the
                 * Google Maps Autocomplete. So this code is only used as fallback to make sure
                 * the provided start location is always geocoded.
                 */
                if ( $wpsl_settings['start_name'] != $_POST['wpsl_map']['start_name']
                  && $wpsl_settings['start_latlng'] == $_POST['wpsl_map']['start_latlng']
                  || empty( $_POST['wpsl_map']['start_latlng'] ) ) {
                    $start_latlng = wpsl_get_address_latlng( $_POST['wpsl_map']['start_name'] );
                } else {
                    $start_latlng = sanitize_text_field( $_POST['wpsl_map']['start_latlng'] );
                }
                
				$output['start_latlng'] = $start_latlng;
			}

			// Do we need to run the fitBounds function make the markers fit in the viewport?
            $output['run_fitbounds'] = isset( $_POST['wpsl_map']['run_fitbounds'] ) ? 1 : 0;

			// Check if we have a valid map type.
			$output['map_type']    = isset( $_POST['wpsl_map']['type'] ) ? wpsl_valid_map_type( $_POST['wpsl_map']['type'] ) : wpsl_get_default_setting( 'map_type' );
            $output['auto_locate'] = isset( $_POST['wpsl_map']['auto_locate'] ) ? 1 : 0; 
            $output['autoload']    = isset( $_POST['wpsl_map']['autoload'] ) ? 1 : 0; 

            // Make sure the auto load limit is either empty or an int.
            if ( empty( $_POST['wpsl_map']['autoload_limit'] ) ) {
                $output['autoload_limit'] = '';
            } else {
                $output['autoload_limit'] = absint( $_POST['wpsl_map']['autoload_limit'] );
            }
     
			$output['streetview'] 		= isset( $_POST['wpsl_map']['streetview'] ) ? 1 : 0;
            $output['type_control']     = isset( $_POST['wpsl_map']['type_control'] ) ? 1 : 0;
            $output['scrollwheel']      = isset( $_POST['wpsl_map']['scrollwheel'] ) ? 1 : 0;	
            $output['zoom_controls']    = isset( $_POST['wpsl_map']['zoom_controls'] ) ? 1 : 0;	
            $output['fullscreen']       = isset( $_POST['wpsl_map']['fullscreen'] ) ? 1 : 0;
			$output['control_position'] = ( isset( $_POST['wpsl_map']['control_position'] ) && $_POST['wpsl_map']['control_position'] == 'left' ) ? 'left' : 'right';	
            
            $output['map_style'] = isset( $_POST['wpsl_map']['map_style'] ) ? json_encode( wp_strip_all_tags( trim( $_POST['wpsl_map']['map_style'] ) ) ) : '';
                    
            // Make sure we have a valid template ID.
            if ( isset( $_POST['wpsl_ux']['template_id'] ) && ( $_POST['wpsl_ux']['template_id'] ) ) {
				$output['template_id'] = sanitize_text_field( $_POST['wpsl_ux']['template_id'] );
			} else {
				$output['template_id'] = wpsl_get_default_setting( 'template_id' );
			}
            
            $output['marker_clusters'] = isset( $_POST['wpsl_map']['marker_clusters'] ) ? 1 : 0;	
                        
            // Check for a valid cluster zoom value.
            if ( isset( $_POST['wpsl_map']['cluster_zoom'] ) && in_array( $_POST['wpsl_map']['cluster_zoom'], $this->get_default_cluster_option( 'cluster_zoom' ) ) ) {
                $output['cluster_zoom'] = $_POST['wpsl_map']['cluster_zoom'];
            } else {
                $output['cluster_zoom'] = wpsl_get_default_setting( 'cluster_zoom' );
            }
            
            // Check for a valid cluster size value.
            if ( isset( $_POST['wpsl_map']['cluster_size'] ) && in_array( $_POST['wpsl_map']['cluster_size'], $this->get_default_cluster_option( 'cluster_size' ) ) ) {
                $output['cluster_size'] = $_POST['wpsl_map']['cluster_size'];
            } else {
                $output['cluster_size'] = wpsl_get_default_setting( 'cluster_size' );
            }
                        
            /* 
             * Make sure all the ux related fields that should contain an int, actually are an int.
             * Otherwise we use the default value. 
             */
            foreach ( $ux_absints as $ux_key ) {
                if ( isset( $_POST['wpsl_ux'][$ux_key] ) && absint( $_POST['wpsl_ux'][$ux_key] ) ) {
                    $output[$ux_key] = $_POST['wpsl_ux'][$ux_key];
                } else {
                    $output[$ux_key] = wpsl_get_default_setting( $ux_key );
                }
            }
            
            // Check if the ux checkboxes are checked.
            foreach ( $ux_checkboxes as $ux_key ) {
                $output[$ux_key] = isset( $_POST['wpsl_ux'][$ux_key] ) ? 1 : 0; 
            }
            
            // Check if we have a valid marker effect.
            if ( isset( $_POST['wpsl_ux']['marker_effect'] ) && in_array( $_POST['wpsl_ux']['marker_effect'], $marker_effects ) ) {
                $output['marker_effect'] = $_POST['wpsl_ux']['marker_effect'];
            } else {
				$output['marker_effect'] = wpsl_get_default_setting( 'marker_effect' );
			}
            
            // Check if we have a valid address format.  
            if ( isset( $_POST['wpsl_ux']['address_format'] ) && array_key_exists( $_POST['wpsl_ux']['address_format'], wpsl_get_address_formats() ) ) {
                $output['address_format'] = $_POST['wpsl_ux']['address_format'];
            } else {
				$output['address_format'] = wpsl_get_default_setting( 'address_format' );
			}
            
            $output['more_info_location'] = ( isset( $_POST['wpsl_ux']['more_info_location'] ) && $_POST['wpsl_ux']['more_info_location'] == 'store listings' ) ? 'store listings' : 'info window';	
            $output['infowindow_style']   = isset( $_POST['wpsl_ux']['infowindow_style'] ) ? 'default' : 'infobox';
            $output['start_marker']       = isset( $_POST['wpsl_map']['start_marker'] ) ? wp_filter_nohtml_kses( $_POST['wpsl_map']['start_marker'] ) : '';
            $output['store_marker']       = isset( $_POST['wpsl_map']['store_marker'] ) ? wp_filter_nohtml_kses( $_POST['wpsl_map']['store_marker'] ) : '';
			$output['editor_country']     = isset( $_POST['wpsl_editor']['default_country'] ) ? sanitize_text_field( $_POST['wpsl_editor']['default_country'] ) : '';
            $output['editor_map_type']    = isset( $_POST['wpsl_editor']['map_type'] ) ? wpsl_valid_map_type( $_POST['wpsl_editor']['map_type'] ) : wpsl_get_default_setting( 'editor_map_type' );
            $output['hide_hours']         = isset( $_POST['wpsl_editor']['hide_hours'] ) ? 1 : 0; 
            
            if ( isset( $_POST['wpsl_editor']['hour_input'] ) ) {
				$output['editor_hour_input'] = ( $_POST['wpsl_editor']['hour_input'] == 'textarea' ) ? 'textarea' : 'dropdown';	
			} else {
				$output['editor_hour_input'] = 'dropdown';
			}
            
            $output['editor_hour_format'] = ( isset( $_POST['wpsl_editor']['hour_format'] ) && $_POST['wpsl_editor']['hour_format'] == 12 ) ? 12 : 24;
            
            // The default opening hours.
            if ( isset( $_POST['wpsl_editor']['textarea'] ) ) {
                $output['editor_hours']['textarea'] = wp_kses_post( trim( stripslashes( $_POST['wpsl_editor']['textarea'] ) ) );
            }
            
            $output['editor_hours']['dropdown'] = $wpsl_admin->metaboxes->format_opening_hours();
            array_walk_recursive( $output['editor_hours']['dropdown'], 'wpsl_sanitize_multi_array' );  
            
            // Permalink and taxonomy slug.
            $output['permalinks'] = isset( $_POST['wpsl_permalinks']['active'] ) ? 1 : 0;
            $output['permalink_remove_front'] = isset( $_POST['wpsl_permalinks']['remove_front'] ) ? 1 : 0;
            
            if ( !empty( $_POST['wpsl_permalinks']['slug'] ) ) {
				$output['permalink_slug'] = sanitize_text_field( $_POST['wpsl_permalinks']['slug'] );
			} else {
				$output['permalink_slug'] = wpsl_get_default_setting( 'permalink_slug' );
			}
            
            if ( !empty( $_POST['wpsl_permalinks']['category_slug'] ) ) {
				$output['category_slug'] = sanitize_text_field( $_POST['wpsl_permalinks']['category_slug'] );
			} else {
				$output['category_slug'] = wpsl_get_default_setting( 'category_slug' );
			}
                                    
			$required_labels = wpsl_labels();
            
			// Sanitize the labels.
			foreach ( $required_labels as $label ) {
                $output[$label.'_label'] = isset( $_POST['wpsl_label'][$label] ) ? sanitize_text_field( $_POST['wpsl_label'][$label] ) : '';
			}

            $output['show_credits']     = isset( $_POST['wpsl_credits'] ) ? 1 : 0;
            $output['debug']            = isset( $_POST['wpsl_tools']['debug'] ) ? 1 : 0;
            $output['deregister_gmaps'] = isset( $_POST['wpsl_tools']['deregister_gmaps'] ) ? 1 : 0;
            $output['delay_loading']    = isset( $_POST['wpsl_tools']['delay_loading'] ) ? 1 : 0;

            // Check if we need to flush the permalinks.
            $this->set_flush_rewrite_option( $output );           
  
            // Check if there is a reason to delete the autoload transient.
            if ( $wpsl_settings['autoload'] ) {
                $this->set_delete_transient_option( $output );
            }

            // See which autocomplete API is used.
            if ( isset( $_POST['wpsl_search']['autocomplete_api_version'] ) && in_array( $_POST['wpsl_search']['autocomplete_api_version'], array( 'legacy', 'latest' ) ) ) {
                $output['api_versions']['autocomplete'] = sanitize_text_field( $_POST['wpsl_search']['autocomplete_api_version'] );
            } else {
                $output['api_versions']['autocomplete'] = 'latest';
            }
                        
			return $output;
		}

        /**
         * Handle the AJAX call to validate the provided
         * server key for the Google Maps API.
         *
         * @since 2.2.10
         * @return void
         */
        public function ajax_validate_server_key() {

            if ( ( current_user_can( 'manage_wpsl_settings' ) ) && is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX  ) {
                
                // Verify nonce
                if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'wpsl_validate_server_key' ) ) {
                    wp_send_json_error( array( 'msg' => __( 'Security check failed.', 'wp-store-locator' ) ) );
                    return;
                }
                
                $server_key = isset( $_GET['server_key'] ) ? sanitize_text_field( wp_unslash( $_GET['server_key'] ) ) : '';

                if ( $server_key ) {
                    $this->validate_server_key( $server_key );
                }
            }
        }

        /**
         * Check if the provided server key for
         * the Google Maps API is valid.
         *
         * @since 2.2.10
         * @param string $server_key The server key to validate
         * @return json|void If the validation failed and AJAX is used, then json
         */
        public function validate_server_key( $server_key ) {

            global $wpsl_admin;

            // Test the server key by making a request to the Geocode API.
            $address  = 'Manhattan, NY 10036, USA';
            $url      = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $address ) .'&key=' . $server_key;
            $response = wp_remote_get( $url );

            if ( !is_wp_error( $response ) ) {
                $response = json_decode( $response['body'], true );

                // If the state is not OK, then there's a problem with the key.
                if ( $response['status'] !== 'OK' ) {
                    $geocode_errors = $wpsl_admin->geocode->check_geocode_error_msg( $response, true );

                    /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: error message */
                    $error_msg = sprintf( __( 'There\'s a problem with the provided %1$sserver key%2$s. %3$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/create-google-api-keys/#server-key">', '</a>', $geocode_errors );

                    update_option( 'wpsl_valid_server_key', 0 );

                    // If the server key input field has 'wpsl-validate-me' class on it, then it's validated with AJAX in the background.
                    if ( defined('DOING_AJAX' ) && DOING_AJAX ) {
                        $key_status = array(
                            'valid' => 0,
                            'msg'   => $error_msg
                        );

                        wp_send_json( $key_status );

                        exit();
                    } else {
                        add_settings_error( 'setting-errors', esc_attr( 'server-key' ), $error_msg, 'error' );
                    }
                } else {
                    update_option( 'wpsl_valid_server_key', 1 );

                    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                        $key_status = array(
                            'valid' => 1,
                            'msg'   => __( 'No problems found with the server key.', 'wp-store-locator' )
                        );

                        wp_send_json( $key_status );

                        exit();
                    }
                }
            }
        }

        /**
         * Check if we need set the option that will be used to determine 
         * if we need to flush the permalinks once the setting page reloads.
         * 
         * @since 2.0.0
         * @param array $new_settings The submitted plugin settings
         * @return void
         */
        public function set_flush_rewrite_option( $new_settings ) {
            
            global $wpsl_settings;
            
            // The settings fields to check.
            $fields = array( 'permalinks', 'permalink_slug', 'permalink_remove_front', 'category_slug' );

            foreach ( $fields as $k => $field ) {
                if ( $wpsl_settings[$field] != $new_settings[$field] ) {
                update_option( 'wpsl_flush_rewrite', 1 );

                    break;
                }
            }
        }
        
        /**
         * Check if we need set the option that is used to determine 
         * if we need to delete the autoload transient once the settings page reloads.
         * 
         * @since 2.0.0
         * @param array $new_settings The submitted plugin settings
         * @return void
         */
        public function set_delete_transient_option( $new_settings ) {

            global $wpsl_settings;

            // The options we need to check for changes.
            $options = array(
                'start_name',
                'debug',
                'autoload', 
                'autoload_limit', 
                'more_info', 
                'more_info_location', 
                'hide_hours',
                'hide_distance',
                'hide_country',
                'show_contact_details'
            );

            foreach ( $options as $option_name ) {
                if ( $wpsl_settings[$option_name] != $new_settings[$option_name] ) {
                    update_option( 'wpsl_delete_transient', 1 );
                    break;
                }
            }
        }

        /**
         * Check if the permalinks settings changed.
         * 
         * @since 2.0.0
         * @return void
         */
        public function maybe_flush_rewrite_and_transient() {

            global $wpsl_admin;
            
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking which admin page we're on, not processing form data
            if ( isset( $_GET['page'] ) && ( sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl_settings' ) ) {
                $flush_rewrite    = get_option( 'wpsl_flush_rewrite' );
                $delete_transient = get_option( 'wpsl_delete_transient' );
                
                if ( $flush_rewrite ) {
                    flush_rewrite_rules();
                    update_option( 'wpsl_flush_rewrite', 0 );
                }
                
                if ( $delete_transient ) {
                    update_option( 'wpsl_delete_transient', 0 );
                }
                
                if ( $flush_rewrite || $delete_transient ) {
                    $wpsl_admin->delete_autoload_transient();     
                }
            }
        }

        /**
         * Handle the different validation errors for the plugin settings.
         * 
         * @since 1.0.0
         * @param string $error_type Contains the type of validation error that occured
         * @return void
         */
		private function settings_error( $error_type ) {
            
			switch ( $error_type ) {
				case 'max_results':
					$error_msg = __( 'The max results field cannot be empty, the default value has been restored.', 'wp-store-locator' );	
					break;
				case 'search_radius':
					$error_msg = __( 'The search radius field cannot be empty, the default value has been restored.', 'wp-store-locator' );	
					break;	
                case 'start_point':
					/* translators: %s: line break */
					$error_msg = sprintf( __( 'Please provide the name of a city or country that can be used as a starting point under "Map Settings". %s This will only be used if auto-locating the user fails, or the option itself is disabled.', 'wp-store-locator' ), '<br><br>' );
					break;
			}
			
			add_settings_error( 'setting-errors', esc_attr( 'settings_fail' ), $error_msg, 'error' );
		}
        
        /**
         * Options for the language and region list.
         *
         * @since 1.0.0
         * @param  string      $list        The request list type
         * @return string|void $option_list The html for the selected list, or nothing if the $list contains invalud values
         */
		public function get_api_option_list( $list ) {
            
            global $wpsl_settings;
            
			switch ( $list ) {
				case 'language':	
					$api_option_list = array ( 	
						__('Select your language', 'wp-store-locator')    => '',
						__('English', 'wp-store-locator')                 => 'en',
						__('Arabic', 'wp-store-locator')                  => 'ar',
						__('Basque', 'wp-store-locator')                  => 'eu',
						__('Bulgarian', 'wp-store-locator')               => 'bg',
						__('Bengali', 'wp-store-locator')                 => 'bn',
						__('Catalan', 'wp-store-locator')                 => 'ca',
						__('Czech', 'wp-store-locator')                   => 'cs',
						__('Danish', 'wp-store-locator')                  => 'da',
						__('German', 'wp-store-locator')                  => 'de',
						__('Greek', 'wp-store-locator')                   => 'el',
						__('English (Australian)', 'wp-store-locator')    => 'en-AU',
						__('English (Great Britain)', 'wp-store-locator') => 'en-GB',
						__('Spanish', 'wp-store-locator')                 => 'es',
						__('Farsi', 'wp-store-locator')                   => 'fa',
						__('Finnish', 'wp-store-locator')                 => 'fi',
						__('Filipino', 'wp-store-locator')                => 'fil',
						__('French', 'wp-store-locator')                  => 'fr',
						__('Galician', 'wp-store-locator')                => 'gl',
						__('Gujarati', 'wp-store-locator')                => 'gu',
						__('Hindi', 'wp-store-locator')                   => 'hi',
						__('Croatian', 'wp-store-locator')                => 'hr',
						__('Hungarian', 'wp-store-locator')               => 'hu',
						__('Indonesian', 'wp-store-locator')              => 'id',
						__('Italian', 'wp-store-locator')                 => 'it',
						__('Hebrew', 'wp-store-locator')                  => 'iw',
						__('Japanese', 'wp-store-locator')                => 'ja',
						__('Kannada', 'wp-store-locator')                 => 'kn',
						__('Korean', 'wp-store-locator')                  => 'ko',
						__('Lithuanian', 'wp-store-locator')              => 'lt',
						__('Latvian', 'wp-store-locator')                 => 'lv',
						__('Malayalam', 'wp-store-locator')               => 'ml',
						__('Marathi', 'wp-store-locator')                 => 'mr',
						__('Dutch', 'wp-store-locator')                   => 'nl',
						__('Norwegian', 'wp-store-locator')               => 'no',
						__('Norwegian Nynorsk', 'wp-store-locator')       => 'nn',
						__('Polish', 'wp-store-locator')                  => 'pl',
						__('Portuguese', 'wp-store-locator')              => 'pt',
						__('Portuguese (Brazil)', 'wp-store-locator')     => 'pt-BR',
						__('Portuguese (Portugal)', 'wp-store-locator')   => 'pt-PT',
						__('Romanian', 'wp-store-locator')                => 'ro',
						__('Russian', 'wp-store-locator')                 => 'ru',
						__('Slovak', 'wp-store-locator')                  => 'sk',
						__('Slovenian', 'wp-store-locator')               => 'sl',
						__('Serbian', 'wp-store-locator')                 => 'sr',
						__('Swedish', 'wp-store-locator')                 => 'sv',
						__('Tagalog', 'wp-store-locator')                 => 'tl',
						__('Tamil', 'wp-store-locator')                   => 'ta',
						__('Telugu', 'wp-store-locator')                  => 'te',
						__('Thai', 'wp-store-locator')                    => 'th',
						__('Turkish', 'wp-store-locator')                 => 'tr',
						__('Ukrainian', 'wp-store-locator')               => 'uk',
						__('Vietnamese', 'wp-store-locator')              => 'vi',
						__('Chinese (Simplified)', 'wp-store-locator')    => 'zh-CN',
						__('Chinese (Traditional)' ,'wp-store-locator')   => 'zh-TW'
				);	
					break;			
				case 'region':
                    $api_option_list = array (
                        __('Select your region', 'wp-store-locator')               => '',
                        __('Afghanistan', 'wp-store-locator')                      => 'af',
                        __('Albania', 'wp-store-locator')                          => 'al',
                        __('Algeria', 'wp-store-locator')                          => 'dz',
                        __('American Samoa', 'wp-store-locator')                   => 'as',
                        __('Andorra', 'wp-store-locator')                          => 'ad',
                        __('Angola', 'wp-store-locator')                           => 'ao',
                        __('Anguilla', 'wp-store-locator')                         => 'ai',
                        __('Antarctica', 'wp-store-locator')                       => 'aq',
                        __('Antigua and Barbuda', 'wp-store-locator')              => 'ag',
                        __('Argentina', 'wp-store-locator')                        => 'ar',
                        __('Armenia', 'wp-store-locator')                          => 'am',
                        __('Aruba', 'wp-store-locator')                            => 'aw',
                        __('Ascension Island', 'wp-store-locator')                 => 'ac',
                        __('Australia', 'wp-store-locator')                        => 'au',
                        __('Austria', 'wp-store-locator')                          => 'at',
                        __('Azerbaijan', 'wp-store-locator')                       => 'az',
                        __('Bahamas', 'wp-store-locator')                          => 'bs',
                        __('Bahrain', 'wp-store-locator')                          => 'bh',
                        __('Bangladesh', 'wp-store-locator')                       => 'bd',
                        __('Barbados', 'wp-store-locator')                         => 'bb',
                        __('Belarus', 'wp-store-locator')                          => 'by',
                        __('Belgium', 'wp-store-locator')                          => 'be',
                        __('Belize', 'wp-store-locator')                           => 'bz',
                        __('Benin', 'wp-store-locator')                            => 'bj',
                        __('Bermuda', 'wp-store-locator')                          => 'bm',
                        __('Bhutan', 'wp-store-locator')                           => 'bt',
                        __('Bolivia', 'wp-store-locator')                          => 'bo',
                        __('Bosnia and Herzegovina', 'wp-store-locator')           => 'ba',
                        __('Botswana', 'wp-store-locator')                         => 'bw',
                        __('Bouvet Island', 'wp-store-locator')                    => 'bv',
                        __('Brazil', 'wp-store-locator')                           => 'br',
                        __('British Indian Ocean Territory', 'wp-store-locator')   => 'io',
                        __('British Virgin Islands', 'wp-store-locator')           => 'vg',
                        __('Brunei', 'wp-store-locator')                           => 'bn',
                        __('Bulgaria', 'wp-store-locator')                         => 'bg',
                        __('Burkina Faso', 'wp-store-locator')                     => 'bf',
                        __('Burundi', 'wp-store-locator')                          => 'bi',
                        __('Cambodia', 'wp-store-locator')                         => 'kh',
                        __('Cameroon', 'wp-store-locator')                         => 'cm',
                        __('Canada', 'wp-store-locator')                           => 'ca',
                        __('Canary Islands', 'wp-store-locator')                   => 'ic',
                        __('Cape Verde', 'wp-store-locator')                       => 'cv',
                        __('Caribbean Netherlands', 'wp-store-locator')            => 'bq',
                        __('Cayman Islands', 'wp-store-locator')                   => 'ky',
                        __('Central African Republic', 'wp-store-locator')         => 'cf',
                        __('Ceuta and Melilla', 'wp-store-locator')                => 'ea',
                        __('Chad', 'wp-store-locator')                             => 'td',
                        __('Chile', 'wp-store-locator')                            => 'cl',
                        __('China', 'wp-store-locator')                            => 'cn',
                        __('Christmas Island', 'wp-store-locator')                 => 'cx',
                        __('Clipperton Island', 'wp-store-locator')                => 'cp',
                        __('Cocos (Keeling) Islands', 'wp-store-locator')          => 'cc',
                        __('Colombia', 'wp-store-locator')                         => 'co',
                        __('Comoros', 'wp-store-locator')                          => 'km',
                        __('Congo (DRC)', 'wp-store-locator')                       => 'cd',
                        __('Congo (Republic)', 'wp-store-locator')                 => 'cg',
                        __('Cook Islands', 'wp-store-locator')                     => 'ck',
                        __('Costa Rica', 'wp-store-locator')                       => 'cr',
                        __('Croatia', 'wp-store-locator')                          => 'hr',
                        __('Cuba', 'wp-store-locator')                             => 'cu',
                        __('Curaçao', 'wp-store-locator')                          => 'cw',
                        __('Cyprus', 'wp-store-locator')                           => 'cy',
                        __('Czech Republic', 'wp-store-locator')                   => 'cz',
                        __('Côte d\'Ivoire', 'wp-store-locator')                   => 'ci',
                        __('Denmark', 'wp-store-locator')                          => 'dk',
                        __('Djibouti', 'wp-store-locator')                         => 'dj',
                        __('Democratic Republic of the Congo', 'wp-store-locator') => 'cd',
                        __('Dominica', 'wp-store-locator')                         => 'dm',
                        __('Dominican Republic', 'wp-store-locator')               => 'do',
                        __('Ecuador', 'wp-store-locator')                          => 'ec',
                        __('Egypt', 'wp-store-locator')                            => 'eg',
                        __('El Salvador', 'wp-store-locator')                      => 'sv',
                        __('Equatorial Guinea', 'wp-store-locator')                => 'gq',
                        __('Eritrea', 'wp-store-locator')                          => 'er',
                        __('Estonia', 'wp-store-locator')                          => 'ee',
                        __('Ethiopia', 'wp-store-locator')                         => 'et',
                        __('Falkland Islands(Islas Malvinas)', 'wp-store-locator') => 'fk',
                        __('Faroe Islands', 'wp-store-locator')                    => 'fo',
                        __('Fiji', 'wp-store-locator')                             => 'fj',
                        __('Finland', 'wp-store-locator')                          => 'fi',
                        __('France', 'wp-store-locator')                           => 'fr',
                        __('French Guiana', 'wp-store-locator')                    => 'gf',
                        __('French Polynesia', 'wp-store-locator')                 => 'pf',
                        __('French Southern Territories', 'wp-store-locator')      => 'tf',
                        __('Gabon', 'wp-store-locator')                            => 'ga',
                        __('Gambia', 'wp-store-locator')                           => 'gm',
                        __('Georgia', 'wp-store-locator')                          => 'ge',
                        __('Germany', 'wp-store-locator')                          => 'de',
                        __('Ghana', 'wp-store-locator')                            => 'gh',
                        __('Gibraltar', 'wp-store-locator')                        => 'gi',
                        __('Greece', 'wp-store-locator')                           => 'gr',
                        __('Greenland', 'wp-store-locator')                        => 'gl',
                        __('Grenada', 'wp-store-locator')                          => 'gd',
                        __('Guam', 'wp-store-locator')                             => 'gu',
                        __('Guadeloupe', 'wp-store-locator')                       => 'gp',
                        __('Guam', 'wp-store-locator')                             => 'gu',
                        __('Guatemala', 'wp-store-locator')                        => 'gt',
                        __('Guernsey', 'wp-store-locator')                         => 'gg',
                        __('Guinea', 'wp-store-locator')                           => 'gn',
                        __('Guinea-Bissau', 'wp-store-locator')                    => 'gw',
                        __('Guyana', 'wp-store-locator')                           => 'gy',
                        __('Haiti', 'wp-store-locator')                            => 'ht',
                        __('Heard and McDonald Islands', 'wp-store-locator')       => 'hm',
                        __('Honduras', 'wp-store-locator')                         => 'hn',
                        __('Hong Kong', 'wp-store-locator')                        => 'hk',
                        __('Hungary', 'wp-store-locator')                          => 'hu',
                        __('Iceland', 'wp-store-locator')                          => 'is',
                        __('India', 'wp-store-locator')                            => 'in',
                        __('Indonesia', 'wp-store-locator')                        => 'id',
                        __('Iran', 'wp-store-locator')                             => 'ir',
                        __('Iraq', 'wp-store-locator')                             => 'iq',
                        __('Ireland', 'wp-store-locator')                          => 'ie',
                        __('Isle of Man', 'wp-store-locator')                      => 'im',
                        __('Israel', 'wp-store-locator')                           => 'il',
                        __('Italy', 'wp-store-locator')                            => 'it',
                        __('Jamaica', 'wp-store-locator')                          => 'jm',
                        __('Japan', 'wp-store-locator')                            => 'jp',
                        __('Jersey', 'wp-store-locator')                           => 'je',
                        __('Jordan', 'wp-store-locator')                           => 'jo',
                        __('Kazakhstan', 'wp-store-locator')                       => 'kz',
                        __('Kenya', 'wp-store-locator')                            => 'ke',
                        __('Kiribati', 'wp-store-locator')                         => 'ki',
                        __('Kosovo', 'wp-store-locator')                           => 'xk',
                        __('Kuwait', 'wp-store-locator')                           => 'kw',
                        __('Kyrgyzstan', 'wp-store-locator')                       => 'kg',
                        __('Laos', 'wp-store-locator')                             => 'la',
                        __('Latvia', 'wp-store-locator')                           => 'lv',
                        __('Lebanon', 'wp-store-locator')                          => 'lb',
                        __('Lesotho', 'wp-store-locator')                          => 'ls',
                        __('Liberia', 'wp-store-locator')                          => 'lr',
                        __('Libya', 'wp-store-locator')                            => 'ly',
                        __('Liechtenstein', 'wp-store-locator')                    => 'li',
                        __('Lithuania', 'wp-store-locator')                        => 'lt',
                        __('Luxembourg', 'wp-store-locator')                       => 'lu',
                        __('Macau', 'wp-store-locator')                            => 'mo',
                        __('Macedonia (FYROM)', 'wp-store-locator')                => 'mk',
                        __('Madagascar', 'wp-store-locator')                       => 'mg',
                        __('Malawi', 'wp-store-locator')                           => 'mw',
                        __('Malaysia ', 'wp-store-locator')                        => 'my',
                        __('Maldives ', 'wp-store-locator')                        => 'mv',
                        __('Mali', 'wp-store-locator')                             => 'ml',
                        __('Malta', 'wp-store-locator')                            => 'mt',
                        __('Marshall Islands', 'wp-store-locator')                 => 'mh',
                        __('Martinique', 'wp-store-locator')                       => 'mq',
                        __('Mauritania', 'wp-store-locator')                       => 'mr',
                        __('Mauritius', 'wp-store-locator')                        => 'mu',
                        __('Mayotte', 'wp-store-locator')                          => 'yt',
                        __('Mexico', 'wp-store-locator')                           => 'mx',
                        __('Micronesia', 'wp-store-locator')                       => 'fm',
                        __('Moldova', 'wp-store-locator')                          => 'md',
                        __('Monaco' ,'wp-store-locator')                           => 'mc',
                        __('Mongolia', 'wp-store-locator')                         => 'mn',
                        __('Montenegro', 'wp-store-locator')                       => 'me',
                        __('Montserrat', 'wp-store-locator')                       => 'ms',
                        __('Morocco', 'wp-store-locator')                          => 'ma',
                        __('Mozambique', 'wp-store-locator')                       => 'mz',
                        __('Myanmar (Burma)', 'wp-store-locator')                  => 'mm',
                        __('Namibia', 'wp-store-locator')                          => 'na',
                        __('Nauru', 'wp-store-locator')                            => 'nr',
                        __('Nepal', 'wp-store-locator')                            => 'np',
                        __('Netherlands', 'wp-store-locator')                      => 'nl',
                        __('Netherlands Antilles', 'wp-store-locator')             => 'an',
                        __('New Caledonia', 'wp-store-locator')                    => 'nc',
                        __('New Zealand', 'wp-store-locator')                      => 'nz',
                        __('Nicaragua', 'wp-store-locator')                        => 'ni',
                        __('Niger', 'wp-store-locator')                            => 'ne',
                        __('Nigeria', 'wp-store-locator')                          => 'ng',
                        __('Niue', 'wp-store-locator')                             => 'nu',
                        __('Norfolk Island', 'wp-store-locator')                   => 'nf',
                        __('North Korea', 'wp-store-locator')                      => 'kp',
                        __('Northern Mariana Islands', 'wp-store-locator')         => 'mp',
                        __('Norway', 'wp-store-locator')                           => 'no',
                        __('Oman', 'wp-store-locator')                             => 'om',
                        __('Pakistan', 'wp-store-locator')                         => 'pk',
                        __('Palau', 'wp-store-locator')                            => 'pw',
                        __('Palestine', 'wp-store-locator')                        => 'ps',
                        __('Panama' ,'wp-store-locator')                           => 'pa',
                        __('Papua New Guinea', 'wp-store-locator')                 => 'pg',
                        __('Paraguay' ,'wp-store-locator')                         => 'py',
                        __('Peru', 'wp-store-locator')                             => 'pe',
                        __('Philippines', 'wp-store-locator')                      => 'ph',
                        __('Pitcairn Islands', 'wp-store-locator')                 => 'pn',
                        __('Poland', 'wp-store-locator')                           => 'pl',
                        __('Portugal', 'wp-store-locator')                         => 'pt',
                        __('Puerto Rico', 'wp-store-locator')                      => 'pr',
                        __('Qatar', 'wp-store-locator')                            => 'qa',
                        __('Reunion', 'wp-store-locator')                          => 're',
                        __('Romania', 'wp-store-locator')                          => 'ro',
                        __('Russia', 'wp-store-locator')                           => 'ru',
                        __('Rwanda', 'wp-store-locator')                           => 'rw',
                        __('Saint Helena', 'wp-store-locator')                     => 'sh',
                        __('Saint Kitts and Nevis', 'wp-store-locator')            => 'kn',
                        __('Saint Vincent and the Grenadines', 'wp-store-locator') => 'vc',
                        __('Saint Lucia', 'wp-store-locator')                      => 'lc',
                        __('Samoa', 'wp-store-locator')                            => 'ws',
                        __('San Marino', 'wp-store-locator')                       => 'sm',
                        __('São Tomé and Príncipe', 'wp-store-locator')            => 'st',
                        __('Saudi Arabia', 'wp-store-locator')                     => 'sa',
                        __('Senegal', 'wp-store-locator')                          => 'sn',
                        __('Serbia', 'wp-store-locator')                           => 'rs',
                        __('Seychelles', 'wp-store-locator')                       => 'sc',
                        __('Sierra Leone', 'wp-store-locator')                     => 'sl',
                        __('Singapore', 'wp-store-locator')                        => 'sg',
                        __('Sint Maarten', 'wp-store-locator')                     => 'sx',
                        __('Slovakia', 'wp-store-locator')                         => 'sk',
                        __('Slovenia', 'wp-store-locator')                         => 'si',
                        __('Solomon Islands', 'wp-store-locator')                  => 'sb',
                        __('Somalia', 'wp-store-locator')                          => 'so',
                        __('South Africa', 'wp-store-locator')                     => 'za',
                        __('South Georgia and South Sandwich Islands', 'wp-store-locator') => 'gs',
                        __('South Korea', 'wp-store-locator')                      => 'kr',
                        __('South Sudan', 'wp-store-locator')                      => 'ss',
                        __('Spain', 'wp-store-locator')                            => 'es',
                        __('Sri Lanka', 'wp-store-locator')                        => 'lk',
                        __('Sudan', 'wp-store-locator')                            => 'sd',
                        __('Swaziland', 'wp-store-locator')                        => 'sz',
                        __('Sweden', 'wp-store-locator')                           => 'se',
                        __('Switzerland', 'wp-store-locator')                      => 'ch',
                        __('Syria', 'wp-store-locator')                            => 'sy',
                        __('São Tomé & Príncipe', 'wp-store-locator')              => 'st',
                        __('Taiwan', 'wp-store-locator')                           => 'tw',
                        __('Tajikistan', 'wp-store-locator')                       => 'tj',
                        __('Tanzania', 'wp-store-locator')                         => 'tz',
                        __('Thailand', 'wp-store-locator')                         => 'th',
                        __('Timor-Leste', 'wp-store-locator')                      => 'tl',
                        __('Tokelau' ,'wp-store-locator')                          => 'tk',
                        __('Togo', 'wp-store-locator')                             => 'tg',
                        __('Tokelau' ,'wp-store-locator')                          => 'tk',
                        __('Tonga', 'wp-store-locator')                            => 'to',
                        __('Trinidad and Tobago', 'wp-store-locator')              => 'tt',
                        __('Tristan da Cunha', 'wp-store-locator')                 => 'ta',
                        __('Tunisia', 'wp-store-locator')                          => 'tn',
                        __('Turkey', 'wp-store-locator')                           => 'tr',
                        __('Turkmenistan', 'wp-store-locator')                     => 'tm',
                        __('Turks and Caicos Islands', 'wp-store-locator')         => 'tc',
                        __('Tuvalu', 'wp-store-locator')                           => 'tv',
                        __('Uganda', 'wp-store-locator')                           => 'ug',
                        __('Ukraine', 'wp-store-locator')                          => 'ua',
                        __('United Arab Emirates', 'wp-store-locator')             => 'ae',
                        __('United Kingdom', 'wp-store-locator')                   => 'gb',
                        __('United States', 'wp-store-locator')                    => 'us',
                        __('Uruguay', 'wp-store-locator')                          => 'uy',
                        __('Uzbekistan', 'wp-store-locator')                       => 'uz',
                        __('Vanuatu', 'wp-store-locator')                          => 'vu',
                        __('Vatican City', 'wp-store-locator')                     => 'va',
                        __('Venezuela', 'wp-store-locator')                        => 've',
                        __('Vietnam', 'wp-store-locator')                          => 'vn',
                        __('Wallis Futuna', 'wp-store-locator')                    => 'wf',
                        __('Western Sahara', 'wp-store-locator')                   => 'eh',
                        __('Yemen', 'wp-store-locator')                            => 'ye',
                        __('Zambia' ,'wp-store-locator')                           => 'zm',
                        __('Zimbabwe', 'wp-store-locator')                         => 'zw',
                        __('Åland Islands', 'wp-store-locator')                    => 'ax'
                    );
			}
			
			// Make sure we have an array with a value.
			if ( !empty( $api_option_list ) && ( is_array( $api_option_list ) ) ) {
                $option_list = '';
				$i = 0;
				
				foreach ( $api_option_list as $api_option_key => $api_option_value ) {  
				
					// If no option value exist, set the first one as selected.
					if ( ( $i == 0 ) && ( empty( $wpsl_settings['api_'.$list] ) ) ) {
						$selected = 'selected="selected"';
					} else {
						$selected = ( $wpsl_settings['api_'.$list] == $api_option_value ) ? 'selected="selected"' : '';
					}
					
					$option_list .= '<option value="' . esc_attr( $api_option_value ) . '" ' . $selected . '>' . esc_html( $api_option_key ) . '</option>';
					$i++;
				}
												
				return $option_list;				
			}
		}
        
        /**
         * Create the dropdown to select the zoom level.
         *
         * @since 1.0.0
         * @return string $dropdown The html for the zoom level list
         */
		public function show_zoom_levels() {
            
            global $wpsl_settings;
                        
			$dropdown = '<select id="wpsl-zoom-level" name="wpsl_map[zoom_level]" autocomplete="off">';
			
			for ( $i = 1; $i < 13; $i++ ) {
				$selected = ( $wpsl_settings['zoom_level'] == $i ) ? 'selected="selected"' : '';
				
				switch ( $i ) {
					case 1:
						$zoom_desc = ' - ' . __( 'World view', 'wp-store-locator' );
						break;
					case 3:
						$zoom_desc = ' - ' . __( 'Default', 'wp-store-locator' );
						break;
					case 12:
						$zoom_desc = ' - ' . __( 'Roadmap', 'wp-store-locator' );
						break;	
					default:
						$zoom_desc = '';		
				}
		
				$dropdown .= "<option value='$i' $selected>". $i . esc_html( $zoom_desc ) . "</option>";	
			}
				
			$dropdown .= "</select>";
				
			return $dropdown;
		}
        
        /**
         * Create the html output for the marker list that is shown on the settings page.
         * 
         * There are two markers lists, one were the user can set the marker for the start point 
         * and one were a marker can be set for the store. We also check if the marker img is identical
         * to the name in the option field. If so we set it to checked.
         *
         * @since 1.0.0
         * @param  string $marker_img  The filename of the marker
         * @param  string $location    Either contains "start" or "store"
         * @return string $marker_list A list of all the available markers
         */
        public function create_marker_html( $marker_img, $location ) {

            global $wpsl_settings;

            $marker_path = ( defined( 'WPSL_MARKER_URI' ) ) ? WPSL_MARKER_URI : WPSL_URL . 'img/markers/';
            $marker_list = '';

            if ( $wpsl_settings[$location.'_marker'] == $marker_img ) {
                $checked   = 'checked="checked"';
                $css_class = 'class="wpsl-active-marker"';
            } else {
                $checked   = '';
                $css_class = '';
            }
            
            $marker_list .= '<li ' . wp_kses_post( $css_class ) . '>';
            $marker_list .= '<img src="' . esc_url( $marker_path . $marker_img ) . '" />';
            $marker_list .= '<input ' . esc_attr( $checked ) . ' type="radio" name="wpsl_map[' . esc_attr( $location ) . '_marker]"  value="' . esc_attr( $marker_img ) . '" />';
            $marker_list .= '</li>';

            return $marker_list;
        }
        
        /**
         * Get the default values for the marker clusters dropdown options.
         *
         * @since 1.2.20
         * @param  string $type           The cluster option type
         * @return string $cluster_values The default cluster options
         */
		public function get_default_cluster_option( $type ) {
            
            $cluster_values = array(
                'cluster_zoom' => array(
                    '7',
                    '8',
                    '9',
                    '10',
                    '11',
                    '12',
                    '13'
                ),
                'cluster_size' => array(
                    '40',
                    '50',
                    '60',
                    '70',
                    '80'
                ), 
            );
            
            return $cluster_values[$type];
        }
        
        /**
         * Create a dropdown for the marker cluster options.
         *
         * @since 1.2.20
         * @param  string $type     The cluster option type
         * @return string $dropdown The html for the distance option list
         */
		public function show_cluster_options( $type ) {
            
            global $wpsl_settings;
            
			$cluster_options = array(
                'cluster_zoom' => array(
                    'id'      => 'wpsl-marker-zoom',
                    'name'    => 'cluster_zoom',
                    'options' => $this->get_default_cluster_option( $type )
                 ),
                'cluster_size' => array(
                    'id'      => 'wpsl-marker-cluster-size',
                    'name'    => 'cluster_size',
                    'options' => $this->get_default_cluster_option( $type )
                ),
			);
            
			$dropdown = '<select id="' . esc_attr( $cluster_options[$type]['id'] ) . '" name="wpsl_map[' . esc_attr( $cluster_options[$type]['name'] ) . ']" autocomplete="off">';
			
            $i = 0;
			foreach ( $cluster_options[$type]['options'] as $item => $value ) {
				$selected = ( $wpsl_settings[$type] == $value ) ? 'selected="selected"' : '';
                
                if ( $i == 0 ) {
                    $dropdown .= "<option value='0' $selected>" . __( 'Default', 'wp-store-locator' ) . "</option>";
                } else {
                    $dropdown .= "<option value=". absint( $value ) . " $selected>" . absint( $value ) . "</option>";
                }
                    
                $i++;
			}
			
			$dropdown .= "</select>";
			
			return $dropdown;			
		}
        
        /**
         * Show the options of the start and store markers.
         *
         * @since 1.0.0
         * @return string $marker_list The complete list of available and selected markers
         */
        public function show_marker_options() {

            $marker_list      = '';
            $marker_images    = $this->get_available_markers();
            $marker_locations = array( 
                'start', 
                'store' 
            );

            foreach ( $marker_locations as $location ) {
                if ( $location == 'start' ) {
                    $marker_list .= __( 'Start location marker', 'wp-store-locator' ) . ':';
                } else  {
                    $marker_list .= __( 'Store location marker', 'wp-store-locator' ) . ':'; 
                }

                if ( !empty( $marker_images ) ) {
                    $marker_list .= '<ul class="wpsl-marker-list">';

                    foreach ( $marker_images as $marker_img ) {
                        $marker_list .= $this->create_marker_html( $marker_img, $location );
                    }

                    $marker_list .= '</ul>';
                }
            }

            return $marker_list;
        }

        /**
         * Load the markers that are used on the map.
         *
         * @since 1.0.0
         * @return array $marker_images A list of all the available markers.
         */
        public function get_available_markers() {
            
            $marker_images = array();
            $dir           = apply_filters( 'wpsl_admin_marker_dir', WPSL_PLUGIN_DIR . 'img/markers/' );
            
            if ( is_dir( $dir ) ) {
                if ( $dh = opendir( $dir ) ) {
                    while ( false !== ( $file = readdir( $dh ) ) ) {
                        if ( $file == '.' || $file == '..' || ( strpos( $file, '2x' ) !== false ) ) continue;
                        $marker_images[] = $file;
                    }

                    closedir( $dh );
                }
            }
            
            return $marker_images;
        }
        
        /**
         * Show a list of available templates.
         *
         * @since 1.2.20
         * @return string $dropdown The html for the template option list
         */
        public function show_template_options() {
            
            global $wpsl_settings;
            
			$dropdown = '<select id="wpsl-store-template" name="wpsl_ux[template_id]" autocomplete="off">';

            foreach ( wpsl_get_templates() as $template ) {
                $template_id = ( isset( $template['id'] ) ) ? $template['id'] : '';
                
				$selected = ( $wpsl_settings['template_id'] == $template_id ) ? ' selected="selected"' : '';
				$dropdown .= "<option value='" . esc_attr( $template_id ) . "' $selected>" . esc_html( $template['name'] ) . "</option>";
            }
			
			$dropdown .= '</select>';
			
			return $dropdown;            
        }
        
        /**
         * Create dropdown lists.
         * 
         * @since 2.0.0
         * @param  string $type     The type of dropdown
         * @return string $dropdown The html output for the dropdown
         */
        public function create_dropdown( $type ) {
            
            global $wpsl_settings;
            
			$dropdown_lists = apply_filters( 'wpsl_setting_dropdowns', array(
                'hour_input' => array(
                    'values' => array(
                        'textarea' => __( 'Textarea', 'wp-store-locator' ), 
                        'dropdown' => __( 'Dropdowns (recommended)', 'wp-store-locator' )
                     ),
                    'id'       => 'wpsl-editor-hour-input',
                    'name'     => 'wpsl_editor[hour_input]',
                    'selected' => $wpsl_settings['editor_hour_input']
                ),
                'marker_effects' => array(
                    'values' => array(
                        'bounce'      => __( 'Bounces up and down', 'wp-store-locator' ),
                        'info_window' => __( 'Will open the info window', 'wp-store-locator' ),
                        'ignore'      => __( 'Does not respond', 'wp-store-locator' )
                    ),
                    'id'       => 'wpsl-marker-effect',
                    'name'     => 'wpsl_ux[marker_effect]',
                    'selected' => $wpsl_settings['marker_effect']
                ),
                'more_info' => array(
                    'values' => array(
                        'store listings' => __( 'In the store listings', 'wp-store-locator' ),
                        'info window'    => __( 'In the info window on the map', 'wp-store-locator' )
                    ),
                    'id'       => 'wpsl-more-info-list',
                    'name'     => 'wpsl_ux[more_info_location]',
                    'selected' => $wpsl_settings['more_info_location']
                ),
                'map_types' => array(
                    'values'   => wpsl_get_map_types(),
                    'id'       => 'wpsl-map-type',
                    'name'     => 'wpsl_map[type]',
                    'selected' => $wpsl_settings['map_type']
                ),
                'editor_map_types' => array(
                    'values'   => wpsl_get_map_types(),
                    'id'       => 'wpsl-editor-map-type',
                    'name'     => 'wpsl_editor[map_type]',
                    'selected' => $wpsl_settings['editor_map_type']
                ),
                'max_zoom_level' => array(
                    'values'   => wpsl_get_max_zoom_levels(),
                    'id'       => 'wpsl-max-auto-zoom',
                    'name'     => 'wpsl_map[max_auto_zoom]',
                    'selected' => $wpsl_settings['auto_zoom_level']
                ),
                'address_format' => array(
                    'values'   => wpsl_get_address_formats(),
                    'id'       => 'wpsl-address-format',
                    'name'     => 'wpsl_ux[address_format]',
                    'selected' => $wpsl_settings['address_format']
                ),
                'filter_types' => array(
                    'values' => array(
                        'dropdown'   => __( 'Dropdown', 'wp-store-locator' ), 
                        'checkboxes' => __( 'Checkboxes', 'wp-store-locator' )
                     ),
                    'id'       => 'wpsl-cat-filter-types',
                    'name'     => 'wpsl_search[category_filter_type]',
                    'selected' => $wpsl_settings['category_filter_type']
                ),
                'autocomplete_api_versions' => array(
                    'values' => array(
                        'legacy' => __( 'Places Autocomplete Service (legacy)', 'wp-store-locator' ),
                        'latest'    => __( 'Autocomplete Data API (new)', 'wp-store-locator' )
                    ),
                    'id'       => 'wpsl-autocomplete-api-versions',
                    'name'     => 'wpsl_search[autocomplete_api_version]',
                    'selected' => $wpsl_settings['api_versions']['autocomplete']
                ),
            ) );
                        
			$dropdown = '<select id="' . esc_attr( $dropdown_lists[$type]['id'] ) . '" name="' . esc_attr( $dropdown_lists[$type]['name'] ) . '" autocomplete="off">';
			
			foreach ( $dropdown_lists[$type]['values'] as $key => $value ) {
				$selected = ( $key == $dropdown_lists[$type]['selected'] ) ? 'selected="selected"' : '';
				$dropdown .= "<option value='" . esc_attr( $key ) . "' $selected>" . esc_html( $value ) . "</option>";
			}
			
			$dropdown .= '</select>';
			
			return $dropdown;			
		}
        
        /**
         * Create a dropdown for the 12/24 opening hours format.
         * 
         * @since 2.0.0
         * @param  string $hour_format The hour format that should be set to selected
         * @return string $dropdown    The html for the dropdown
         */
        public function show_opening_hours_format( $hour_format = '' ) {
            
            global $wpsl_settings;
            
			$items = array( 
                '12' => __( '12 Hours', 'wp-store-locator' ),
                '24' => __( '24 Hours', 'wp-store-locator' )
            );
            
            if ( ! absint( $hour_format ) ) {
                $hour_format = $wpsl_settings['editor_hour_format'];
            } 
            
			$dropdown = '<select id="wpsl-editor-hour-format" name="wpsl_editor[hour_format]" autocomplete="off">';
			
			foreach ( $items as $key => $value ) {
				$selected = ( $hour_format == $key ) ? 'selected="selected"' : '';
				$dropdown .= "<option value='" . esc_attr( $key ) . "' $selected>" . esc_html( $value ) . "</option>";
			}
			
			$dropdown .= '</select>';
			
			return $dropdown;			
		}

        /**
         * Get the map style code
         *
         * @since  2.2.241
         * @return string $map_style The code to style the map
         */
        public function get_map_style() {

            global $wpsl_settings;

            $map_style = '';

            if ( isset( $wpsl_settings['map_style'] ) ) {
                $map_style = json_decode( $wpsl_settings['map_style'] );

                if ( $map_style !== null ) {
                    $map_style = wp_strip_all_tags( stripslashes( $map_style ) );
                }
            }

            return $map_style;
        }
    }
}