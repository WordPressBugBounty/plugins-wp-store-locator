<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_version = get_option( 'wpsl_version' );

// Without a version, only a 3.x site ( no 2.x wpsl_settings ) runs the check, which restores the version.
if ( $current_version || false === get_option( 'wpsl_settings' ) ) {
    add_action( 'admin_init', 'wpsl_check_upgrade' );
}

// Only check CPT conversion state if it's actually in progress
if ( $current_version && get_option( 'wpsl_convert_cpt' ) == 'in_progress' ) {
    add_action( 'admin_init', 'wpsl_cpt_update_state' );
}

// One-time consolidation of the Google Maps country restriction options.
add_action( 'admin_init', 'wpsl_migrate_gmaps_country_restrictions' );

/**
 * Merge the legacy Google Maps country restriction options into the unified
 * 'multiple_regions' setting that is now shared by all map services.
 *
 * The old gmaps_region ( when its geocode_component toggle was on ),
 * country_restrictions ( search ) and gmaps_autocomplete_restrictions ( search )
 * are combined, de-duplicated and stored as api.multiple_regions. Runs once and
 * never overwrites an existing selection.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_migrate_gmaps_country_restrictions() {
    if ( get_option( 'wpsl_gmaps_country_restrictions_migrated' ) ) {
        return;
    }

    $api    = get_option( 'wpsl_api' );
    $search = get_option( 'wpsl_search' );

    if ( ! is_array( $api ) ) {
        // The grouped settings don't exist yet ( e.g. the v2 -> v3 migration has
        // not run ). Retry on a later request rather than marking this as done,
        // so a v2 user's restriction is never skipped.
        return;
    }

    $existing = ( isset( $api['multiple_regions'] ) && is_array( $api['multiple_regions'] ) ) ? $api['multiple_regions'] : [];

    // Only seed when the new field is still empty, so a manual selection is never clobbered.
    if ( empty( $existing ) ) {
        $countries = [];

        // Single-country geocode restriction ( only when the toggle was enabled ).
        if ( ! empty( $api['geocode_component'] ) && ! empty( $api['gmaps_region'] ) ) {
            $countries[] = $api['gmaps_region'];
        }

        // Comma separated "restrict the search results" field.
        if ( is_array( $search ) && ! empty( $search['country_restrictions'] ) ) {
            $countries = array_merge( $countries, explode( ',', $search['country_restrictions'] ) );
        }

        // Autocomplete regions multiselect.
        if ( is_array( $search ) && ! empty( $search['gmaps_autocomplete_restrictions'] ) && is_array( $search['gmaps_autocomplete_restrictions'] ) ) {
            $countries = array_merge( $countries, $search['gmaps_autocomplete_restrictions'] );
        }

        // Normalise: lowercase, trim, drop blanks, de-duplicate.
        $countries = array_filter( array_unique( array_map( function( $code ) {
            return strtolower( trim( (string) $code ) );
        }, $countries ) ) );

        if ( ! empty( $countries ) ) {
            $api['multiple_regions'] = array_values( $countries );
        }
    }

    unset( $api['geocode_component'] );
    wpsl_get_service( 'wpsl_settings' )->update( 'api', $api );

    if ( is_array( $search ) ) {
        unset( $search['country_restrictions'], $search['gmaps_autocomplete_restrictions'] );
        wpsl_get_service( 'wpsl_settings' )->update( 'search', $search );
    }

    update_option( 'wpsl_gmaps_country_restrictions_migrated', 1, 'no' );
}

// One-time default for the Google Maps region handling toggle.
add_action( 'admin_init', 'wpsl_migrate_region_restriction_type' );

/**
 * Seed 'region_restriction_type' for existing installs: default to 'restrict'
 * if a country restriction is already configured (multiple_regions), otherwise
 * 'bias'. Runs after wpsl_migrate_gmaps_country_restrictions() on the same
 * hook, so multiple_regions is already populated.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_migrate_region_restriction_type() {
    if ( get_option( 'wpsl_region_restriction_type_migrated' ) ) {
        return;
    }

    $api = get_option( 'wpsl_api' );

    if ( ! is_array( $api ) ) {
        // Grouped settings not created yet; retry on a later request.
        return;
    }

    if ( ! isset( $api['region_restriction_type'] ) ) {
        $has_countries = ! empty( $api['multiple_regions'] ) && is_array( $api['multiple_regions'] );

        $api['region_restriction_type'] = $has_countries ? 'restrict' : 'bias';

        // Written through the settings handler so the form sanitize callback
        // skips this settings-shaped array ( see wpsl_migrate_gmaps_country_restrictions ).
        wpsl_get_service( 'wpsl_settings' )->update( 'api', $api );
    }

    update_option( 'wpsl_region_restriction_type_migrated', 1, 'no' );
}

add_action( 'admin_init', 'wpsl_migrate_option_autoload' );

/**
 * Stop autoloading the wpsl_* settings groups that aren't read every request.
 *
 * Most groups are only read when a locator renders, or by the v2 back-compat
 * shim, so they do not belong in the alloptions payload of every request.
 * wpsl_api, wpsl_local_pages and wpsl_version are excluded: those are read on
 * every request, so they are autoloaded on purpose.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_migrate_option_autoload() {
    if ( get_option( 'wpsl_option_autoload_migrated' ) ) {
        return;
    }

    // api / local_pages are read on every request and wpsl_version with them,
    // so they are deliberately absent here - see Settings\Manager.
    $groups = [ 'search', 'map', 'ux', 'markers', 'editor', 'appearance', 'labels', 'gdpr', 'tools' ];

    foreach ( $groups as $group ) {
        $option_name = 'wpsl_' . $group;
        $value       = get_option( $option_name );

        if ( false === $value ) {
            continue;
        }

        if ( function_exists( 'wp_set_option_autoload' ) ) {
            // WordPress 6.4+.
            wp_set_option_autoload( $option_name, false );
        } else {
            // Older WordPress: re-create the option with autoload disabled.
            delete_option( $option_name );
            add_option( $option_name, $value, '', 'no' );
        }
    }

    update_option( 'wpsl_option_autoload_migrated', 1, 'no' );
}

add_action( 'admin_init', 'wpsl_migrate_settings_autoload' );

/**
 * Autoload the settings options that every request reads.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_migrate_settings_autoload() {
    if ( get_option( 'wpsl_settings_autoload_fixed' ) ) {
        return;
    }

    // WordPress 6.4+. Older installs keep what they have, which only costs
    // them the per-option queries they already pay.
    if ( function_exists( 'wp_set_option_autoload' ) ) {

        foreach ( [ 'wpsl_api', 'wpsl_local_pages', 'wpsl_version' ] as $option ) {
            wp_set_option_autoload( $option, true );
        }

        update_option( 'wpsl_settings_autoload_fixed', 1, 'no' );
    }
}

/**
 * If the db doesn't hold the current version, run the upgrade procedure
 *
 * @since  1.2.0
 * @return void
 */
function wpsl_check_upgrade() {
    $current_version = get_option( 'wpsl_version' );

    if ( version_compare( $current_version, WPSL_VERSION_NUM, '==' ) )
        return;

    $wpsl_settings = get_option( 'wpsl_settings' );

    // If wpsl_settings doesn't exist, there's nothing to upgrade
    if ( ! $wpsl_settings ) {
        // No version and no 2.x settings is a 3.x site whose version row was lost (older Reset data deleted it).
        if ( false === $current_version || version_compare( $current_version, '3.0', '>=' ) ) {
            update_option( 'wpsl_version', WPSL_VERSION_NUM, true );
        }

        return;
    }

    if ( version_compare( $current_version, '1.1', '<' ) ) {
        if ( is_array( $wpsl_settings ) ) {
            if ( empty( $wpsl_settings['reset_map'] ) ) {
                $wpsl_settings['reset_map'] = 0;
            }

            if ( empty( $wpsl_settings['auto_load'] ) ) {
                $wpsl_settings['auto_load'] = 1;
            }	

            if ( empty( $wpsl_settings['new_window'] ) ) {
                $wpsl_settings['new_window'] = 0;
            }  

            update_option( 'wpsl_settings', $wpsl_settings, 'no' );
        } 
    }

    if ( version_compare( $current_version, '1.2', '<' ) ) {
        if ( is_array( $wpsl_settings ) ) {
            if ( empty( $wpsl_settings['store_below'] ) ) {
                $wpsl_settings['store_below'] = 0;
            }	

            if ( empty( $wpsl_settings['direction_redirect'] ) ) {
                $wpsl_settings['direction_redirect'] = 0;
            }    

            update_option( 'wpsl_settings', $wpsl_settings, 'no' );
        } 
    }

    if ( version_compare( $current_version, '1.2.11', '<' ) ) {
        if ( is_array( $wpsl_settings ) ) {
            if ( empty( $wpsl_settings['more_info'] ) ) {
                $wpsl_settings['more_info'] = 0;
            }

            if ( empty( $wpsl_settings['more_label'] ) ) {
                $wpsl_settings['more_label'] = esc_html__( 'More info', 'wp-store-locator' );
            }

            if ( empty( $wpsl_settings['mouse_focus'] ) ) {
                $wpsl_settings['mouse_focus'] = 0;
            }	

            update_option( 'wpsl_settings', $wpsl_settings, 'no' );
        } 
    }

    if ( version_compare( $current_version, '1.2.12', '<' ) ) {
        if ( is_array( $wpsl_settings ) ) {
            if ( empty( $wpsl_settings['more_info_location'] ) ) {
                $wpsl_settings['more_info_location'] = esc_html__( 'info window', 'wp-store-locator' );
            }

            if ( empty( $wpsl_settings['back_label'] ) ) {
                $wpsl_settings['back_label'] = esc_html__( 'Back', 'wp-store-locator' );
            }

            if ( empty( $wpsl_settings['reset_label'] ) ) {
                $wpsl_settings['reset_label'] = esc_html__( 'Reset', 'wp-store-locator' );
            }                  

            if ( empty( $wpsl_settings['store_below_scroll'] ) ) {
                $wpsl_settings['store_below_scroll'] = 0;
            }  

            update_option( 'wpsl_settings', $wpsl_settings, 'no' );
        } 
    }   

    if ( version_compare( $current_version, '1.2.20', '<' ) ) {

        global $wpdb;
        
        $wpsl_table = $wpdb->prefix . 'wpsl_stores';

        // Check if table exists before attempting alterations
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check, caching not applicable
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpsl_table ) ) == $wpsl_table ) {
            
            // Check if 'street' column exists before renaming to 'address'
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check, caching not applicable
            $street_column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM " . esc_sql( $wpsl_table ) . " LIKE %s", 'street' ) );
            
            if ( ! empty( $street_column ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time schema upgrade
                $wpdb->query( "ALTER TABLE " . esc_sql( $wpsl_table ) . " CHANGE street address VARCHAR(255)" );
            }

            // Check if 'address2' column exists before adding it
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check, caching not applicable
            $address2_column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM " . esc_sql( $wpsl_table ) . " LIKE %s", 'address2' ) );
            
            if ( empty( $address2_column ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time schema upgrade
                $wpdb->query( "ALTER TABLE " . esc_sql( $wpsl_table ) . " ADD address2 VARCHAR(255) NULL AFTER address" );
            }
        }

        if ( is_array( $wpsl_settings ) ) {
            if ( empty( $wpsl_settings['store_url'] ) ) {
                $wpsl_settings['store_url'] = 0;
            }

            if ( empty( $wpsl_settings['phone_url'] ) ) {
                $wpsl_settings['phone_url'] = 0;
            }

            if ( empty( $wpsl_settings['marker_clusters'] ) ) {
                $wpsl_settings['marker_clusters'] = 0;
            }

            if ( empty( $wpsl_settings['cluster_zoom'] ) ) {
                $wpsl_settings['cluster_zoom'] = 0;
            }

            if ( empty( $wpsl_settings['cluster_size'] ) ) {
                $wpsl_settings['cluster_size'] = 0;
            }

            if ( empty( $wpsl_settings['template_id'] ) ) {
                $wpsl_settings['template_id'] = ( $wpsl_settings['store_below'] ) ? 1 : 0;
                unset( $wpsl_settings['store_below'] );
            }

            if ( empty( $wpsl_settings['marker_streetview'] ) ) {
                $wpsl_settings['marker_streetview'] = 0;
            }

            if ( empty( $wpsl_settings['marker_zoom_to'] ) ) {
                $wpsl_settings['marker_zoom_to'] = 0;
            }

            if ( !isset( $wpsl_settings['editor_country'] ) ) {
                $wpsl_settings['editor_country'] = '';
            }

            if ( empty( $wpsl_settings['street_view_label'] ) ) {
                $wpsl_settings['street_view_label'] = esc_html__( 'Street view', 'wp-store-locator' );
            }

            if ( empty( $wpsl_settings['zoom_here_label'] ) ) {
                $wpsl_settings['zoom_here_label'] = esc_html__( 'Zoom here', 'wp-store-locator' );
            }

            if ( empty( $wpsl_settings['no_directions_label'] ) ) {
                $wpsl_settings['no_directions_label'] = esc_html__( 'No route could be found between the origin and destination', 'wp-store-locator' );
            }

            update_option( 'wpsl_settings', $wpsl_settings, 'no' );
        }
    }

    if ( version_compare( $current_version, '2.0', '<' ) ) {
        
        global $wpdb;
        
        $wpsl_table = $wpdb->prefix . 'wpsl_stores';
        
        if ( is_array( $wpsl_settings ) ) {
            if ( empty( $wpsl_settings['radius_dropdown'] ) ) {
                $wpsl_settings['radius_dropdown'] = 1;
            }
            
            if ( empty( $wpsl_settings['permalinks'] ) ) {
                $wpsl_settings['permalinks'] = 0;
            }

            if ( empty( $wpsl_settings['permalink_slug'] ) ) {
                $wpsl_settings['permalink_slug'] = esc_html__( 'stores', 'wp-store-locator' );
            }
            
            if ( empty( $wpsl_settings['category_slug'] ) ) {
                $wpsl_settings['category_slug'] = esc_html__( 'store-category', 'wp-store-locator' );
            }
           
            if ( empty( $wpsl_settings['editor_hours'] ) ) {
                $wpsl_settings['editor_hours'] = wpsl_default_opening_hours();
            }
            
            if ( empty( $wpsl_settings['editor_hour_format'] ) ) {
                $wpsl_settings['editor_hour_format'] = 12;
            }
            
            if ( empty( $wpsl_settings['editor_map_type'] ) ) {
                $wpsl_settings['editor_map_type'] = 'roadmap';
            }
            
            if ( empty( $wpsl_settings['infowindow_style'] ) ) {
                $wpsl_settings['infowindow_style'] = 'default';
            }
            
            if ( empty( $wpsl_settings['email_label'] ) ) {
                $wpsl_settings['email_label'] = esc_html__( 'Email', 'wp-store-locator' );
            }
            
            if ( empty( $wpsl_settings['url_label'] ) ) {
                $wpsl_settings['url_label'] = esc_html__( 'Url', 'wp-store-locator' );
            }
            
            if ( empty( $wpsl_settings['category_label'] ) ) {
                $wpsl_settings['category_label'] = esc_html__( 'Category filter', 'wp-store-locator' );
            }
            
            if ( empty( $wpsl_settings['show_credits'] ) ) {
                $wpsl_settings['show_credits'] = 0;
            }
            
            if ( empty( $wpsl_settings['autoload_limit'] ) ) {
                $wpsl_settings['autoload_limit'] = 50;
            }
            
            if ( empty( $wpsl_settings['scrollwheel'] ) ) {
                $wpsl_settings['scrollwheel'] = 1;
            }
            
            if ( empty( $wpsl_settings['type_control'] ) ) {
                $wpsl_settings['type_control'] = 0;
            }

            if ( empty( $wpsl_settings['hide_hours'] ) ) {
                $wpsl_settings['hide_hours'] = 0;
            }
            
            // Either correct the existing map style format from the 2.0 beta or set it to empty.
            if ( isset( $wpsl_settings['map_style'] ) && is_array( $wpsl_settings['map_style'] ) && isset( $wpsl_settings['map_style']['id'] ) ) {
                switch( $wpsl_settings['map_style']['id'] ) {
                    case 'custom':
                        $map_style = $wpsl_settings['map_style']['custom_json'];
                        break;
                    case 'default':
                        $map_style = '';
                        break;
                    default:
                        $map_style = $wpsl_settings['map_style']['theme_json'];
                        break;
                }

                $wpsl_settings['map_style'] = $map_style;
            } else {
                $wpsl_settings['map_style'] = '';
            }
                        
            if ( empty( $wpsl_settings['autoload'] ) ) {
                $wpsl_settings['autoload'] = $wpsl_settings['auto_load'];
                unset( $wpsl_settings['auto_load'] );
            }
            
            if ( empty( $wpsl_settings['address_format'] ) ) {
                $wpsl_settings['address_format'] = 'city_state_zip';
            }
            
            if ( empty( $wpsl_settings['auto_zoom_level'] ) ) {
                $wpsl_settings['auto_zoom_level'] = 15;
            }
            
            if ( empty( $wpsl_settings['hide_distance'] ) ) {
                $wpsl_settings['hide_distance'] = 0;
            }
            
            if ( empty( $wpsl_settings['debug'] ) ) {
                $wpsl_settings['debug'] = 0;
            }
            
            if ( empty( $wpsl_settings['category_dropdown'] ) ) {
                $wpsl_settings['category_dropdown'] = 0;
            }
           
            /* 
             * Replace marker_bounce with marker_effect to better reflect what the option contains.
             * 
             * If a user hovers over the result list then either the corresponding marker will bounce,
             * the info window will open, or nothing will happen. 
             * 
             * The default behaviour is that the marker will bounce.
             */            
            if ( empty( $wpsl_settings['marker_effect'] ) ) {
                $wpsl_settings['marker_effect'] = ( $wpsl_settings['marker_bounce'] ) ? 'bounce' : 'ignore';
                unset( $wpsl_settings['marker_bounce'] );
            }
                        
            /* 
             * The default input for the opening hours is set to textarea for current users, 
             * for new users it will be set to dropdown ( easier to format in a table output and to use with schema.org in the future ).  
             */
            if ( empty( $wpsl_settings['editor_hour_input'] ) ) {
                $wpsl_settings['editor_hour_input'] = 'textarea';
            }
            
            // Rename store_below_scroll to listing_below_no_scroll, it better reflects what it does.
            if ( empty( $wpsl_settings['listing_below_no_scroll'] ) && isset( $wpsl_settings['store_below_scroll'] ) ) {
                $wpsl_settings['listing_below_no_scroll'] = $wpsl_settings['store_below_scroll'];
                unset( $wpsl_settings['store_below_scroll'] );
            }
            
            // Change the template ids from number based to name based.
            if ( is_numeric( $wpsl_settings['template_id'] ) ) {
                $wpsl_settings['template_id'] = ( ! $wpsl_settings['template_id'] ) ? 'default' : 'below_map';
            }

            $replace_data = [
                'max_results'   => $wpsl_settings['max_results'],
                'search_radius' => $wpsl_settings['search_radius']
            ];

            /* 
             * Replace the () with [], this fixes an issue with the mod_security module that is installed on some servers. 
             * It triggerd a 'Possible SQL injection attack' warning probably because of the int,(int) format of the data.
             */
            foreach ( $replace_data as $index => $option_value ) {
                $wpsl_settings[$index] = str_replace( [ '(', ')' ], [ '[', ']' ], $option_value );
            }
            
            // The reset button now uses an icon instead of text, so no need for the label anymore.
            unset( $wpsl_settings['reset_label'] );

            update_option( 'wpsl_settings', $wpsl_settings, 'no' );
            
            /* 
             * Users upgrading from 1.x will be given the choice between the textarea or 
             * dropdowns for the opening hours. 
             * 
             * New users don't get that choice, they will only get the dropdowns. 
             * 
             * The wpsl_legacy_support option is used to determine if we need to show both options.
             */
            update_option( 'wpsl_legacy_support', 1, 'no' );
                           
            // Add the WPSL roles and caps.
            wpsl_add_roles();
            wpsl_add_caps();

            // If there is a wpsl_stores table, then we need to convert all the locations to the 'wpsl_stores' custom post type.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check, caching not applicable
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpsl_table ) ) && version_compare( $current_version, '1.9', '<' ) ) { 
                if ( wpsl_remaining_cpt_count() ) {
                    update_option( 'wpsl_convert_cpt', 'in_progress', 'no' );
                }
            }
        }
    }
    
    /*
     * Both map options are no longer supported in 3.22 of the Google Maps API.
     * See: https://developers.google.com/maps/articles/v322-controls-diff
     */
    if ( version_compare( $current_version, '2.0.3', '<' ) ) {
        unset( $wpsl_settings['control_style'] );
        unset( $wpsl_settings['pan_controls'] );

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.1.0', '<' ) ) {
        if ( !isset( $wpsl_settings['api_geocode_component'] ) ) {
            $wpsl_settings['api_geocode_component'] = 0;
        }

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }
    
    if ( version_compare( $current_version, '2.2', '<' ) ) {
        $wpsl_settings['autocomplete'] = 0;
        $wpsl_settings['category_default_label'] = esc_html__( 'Any', 'wp-store-locator' );

        // Rename the 'zoom_name' and 'zoom_latlng' to 'start_name' and 'start_latlng'.
        if ( isset( $wpsl_settings['zoom_name'] ) ) {
            $wpsl_settings['start_name'] = $wpsl_settings['zoom_name'];
            unset( $wpsl_settings['zoom_name'] );
        }

        if ( isset( $wpsl_settings['zoom_latlng'] ) ) {
            $wpsl_settings['start_latlng'] = $wpsl_settings['zoom_latlng'];
            unset( $wpsl_settings['zoom_latlng'] );
        }

        if ( isset( $wpsl_settings['category_dropdown'] ) ) {
            $wpsl_settings['category_filter'] = $wpsl_settings['category_dropdown'];
            unset( $wpsl_settings['category_dropdown'] );
        }
        
        /*
         * We now have separate browser and server key fields, and assume the existing key is a server key.
         *
         * The names have to stay 'api_server_key' / 'api_browser_key': that is what 2.x itself wrote,
         * and what migrate() reads out of wpsl_settings further down the chain. Renaming them here
         * silently dropped the API key of everyone upgrading from 1.x.
         */
        if ( isset( $wpsl_settings['api_key'] ) ) {
            $wpsl_settings['api_server_key'] = $wpsl_settings['api_key'];
            unset( $wpsl_settings['api_key'] );
        }

        $wpsl_settings['api_browser_key']      = '';
        $wpsl_settings['category_filter_type'] = 'dropdown';
        $wpsl_settings['hide_country']         = 0;
        $wpsl_settings['show_contact_details'] = 0;
        
        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }
    
    if ( version_compare( $current_version, '2.2.4', '<' ) ) {
        $wpsl_settings['deregister_gmaps'] = 0;

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.2.9', '<' ) ) {
        $wpsl_settings['run_fitbounds'] = 1;

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.2.13', '<' ) ) {
        $wpsl_settings['clickable_contact_details'] = 0;

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.2.20', '<' ) ) {
        $wpsl_settings['force_postalcode'] = 0;
        $wpsl_settings['permalink_remove_front'] = 0;

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.2.22', '<' ) ) {
        $wpsl_settings['delay_loading'] = 0;

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.2.250', '<' ) ) {
        $wpsl_settings['api_versions']['autocomplete'] = 'legacy';

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.2.260', '<' ) ) {
        $wpsl_settings['zoom_controls'] = 0;
        $wpsl_settings['fullscreen']    = 0;

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    if ( version_compare( $current_version, '2.3.2', '<' ) ) {
        $wpsl_settings['cluster_renderer_style'] = 'default';

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    /*
     * Runs before the 3.0 migration block below: the migration reads
     * cluster_marker_shape straight out of the wpsl_settings option.
     */
    if ( version_compare( $current_version, '2.3.22', '<' ) ) {
        $wpsl_settings['cluster_marker_shape'] = 'default';

        update_option( 'wpsl_settings', $wpsl_settings, 'no' );
    }

    /*
     * Before 3.0.0, wpsl_custom_markers was autoloaded, pulling the whole
     * marker payload into alloptions on every request. New saves pass the
     * flag; this repairs installs whose option predates that.
     */
    if ( version_compare( $current_version, '3.0.0', '<' ) && function_exists( 'wp_set_option_autoload' ) ) {
        wp_set_option_autoload( 'wpsl_custom_markers', false );
    }

    /*
     * wpsl_v3_migration_source() decides whether the v2 to v3 migration runs
     * and which version to migrate from. It also recovers 3.0 beta installs
     * that skipped the migration because the guard here still read '2.3.22',
     * so a version comparison alone can't reach them.
     */
    $migrate_from = wpsl_v3_migration_source( $current_version );

    if ( false !== $migrate_from ) {
        update_option( 'wpsl_updated_from', $migrate_from, 'no' );

        // Set migration state to trigger settings migration
        update_option( 'wpsl_v3_migration_complete', 'in_progress', 'no' );

        wpsl_migrate_license_data();

        wpsl_run_settings_migration( $migrate_from );

        // Create the Nominatim ( OpenStreetMaps ) Geocode cache table.
        require_once( WPSL_PLUGIN_DIR . 'includes/core/map/class-nominatim-geocode-cache.php' );

        $nominatim_cache = new \WPSL\Core\Map\Nominatim_Geocode_Cache();
        $nominatim_cache->create_table();

        wpsl_create_theme_table();

        /**
         * Remove the v2.x autoload transients ( wpsl_autoload_* ).
         *
         * They were created without an expiration date, so they would
         * otherwise remain in the options table as autoloaded rows
         * forever. The v3 code uses different transient names and
         * never reads them again.
         */
        wpsl_get_service( 'system_utils' )->flush_autoload_transients();
    }

    /*
     * Queue "What's New" for everyone arriving from pre-3.0. Guarded on '3.0'
     * rather than '2.3.22' so the last 2.x release is included. A stored
     * option, not a transient: the redirect must survive until the user opens
     * an admin page, and it should show exactly once.
     */
    if ( version_compare( $current_version, '3.0', '<' ) ) {
        update_option( 'wpsl_whats_new_redirect', 1, 'no' );
    }

    update_option( 'wpsl_version', WPSL_VERSION_NUM, true );
}

/*
 * Priority 20 so wpsl_check_upgrade() ( priority 10 ) has already run and set
 * the flag, which lets the redirect happen on the same request as the upgrade.
 */
add_action( 'admin_init', 'wpsl_whats_new_redirect', 20 );

/**
 * Send users who just upgraded from 2.x to the "What's New" screen, once.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_whats_new_redirect() {

    if ( ! get_option( 'wpsl_whats_new_redirect' ) ) {
        return;
    }

    /*
     * Only ever hijack a plain admin page view. Background requests have no
     * browser to send anywhere, and redirecting a form submit would throw the
     * submitted data away ( admin_init also runs on options.php POSTs ).
     */
    if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }

    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'GET' !== $request_method || is_network_admin() ) {
        return;
    }

    /*
     * Leave the flag in place when the current user can't open the page, so the
     * site owner still gets to see it on their next visit.
     */
    if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
        return;
    }

    /*
     * The update screens run admin_init while WordPress is busy installing
     * plugins, and a bulk update lands back on plugins.php afterwards. Waiting
     * for the next page view leaves that flow intact.
     */
    global $pagenow;

    if ( in_array( $pagenow, [ 'update.php', 'update-core.php', 'plugins.php' ], true ) ) {
        return;
    }

    delete_option( 'wpsl_whats_new_redirect' );

    wp_safe_redirect( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_whats-new' ) );
    exit;
}

/**
 * Explain the single-key to browser + server key split to 1.x upgraders.
 *
 * Up to 2.2 the plugin stored one Google Maps API key. From 2.2 on that became a
 * separate browser key ( Maps JavaScript API ) and server key ( Geocoding API ),
 * and the upgrade can only assume the existing key is the server one. The browser
 * key field is therefore left empty and flagged as required, with nothing on screen
 * explaining why, so the map silently stops loading. Point that out instead.
 *
 * @since  3.0.0
 * @param  string $previous_version The version being upgraded from.
 * @return void
 */
function wpsl_notify_split_api_key( $previous_version ) {

    // Installs on 2.2 or later already had both fields.
    if ( version_compare( $previous_version, '2.2', '>=' ) ) {
        return;
    }

    $api = get_option( 'wpsl_api' );

    /*
     * Only relevant when a key actually carried over. Without one the user has to
     * fill in both fields anyway, which is just the normal first-run experience.
     */
    if ( ! is_array( $api ) || empty( $api['gmaps_server_key'] ) || ! empty( $api['gmaps_browser_key'] ) ) {
        return;
    }

    $api_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' );

    $notice  = '<p>' . esc_html__( 'WP Store Locator used to store a single Google Maps API key. It now uses two: a server key for the Geocoding API, and a browser key for the Maps JavaScript API.', 'wp-store-locator' ) . '</p>';
    $notice .= '<p>' . sprintf(
        /* translators: 1: opening link tag to the API section, 2: closing link tag */
        esc_html__( 'Your existing key has been saved as the server key. The map will not load until you also add a browser key in the %1$sAPI section%2$s.', 'wp-store-locator' ),
        '<a class="wpsl-trigger-nav" data-item="api" data-focus="wpsl_api[gmaps_browser_key]" href="' . esc_url( $api_url ) . '">',
        '</a>'
    ) . '</p>';
    $notice .= '<p><a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key">' . esc_html__( 'How to create a browser key', 'wp-store-locator' ) . '</a></p>';

    \WPSL\Admin\Core\Notices::instance()->save( 'warning', $notice, true );
}

/**
 * Loop over all the plugins and look for wpsl add-ons.
 *
 * If they exist, move the license key to a single option field and
 * store the rest in a transient ( expiry date, status ) that we use
 * to check every week if something has changed with the license.
 *
 * @since 3.0.0
 */
function wpsl_migrate_license_data() {

    // Only run once - check if already migrated
    if ( get_option( 'wpsl_license_data_migrated' ) ) {
        return;
    }
    
    // Ensure License_Manager class is loaded
    if ( ! class_exists( '\WPSL\Admin\Tools\License_Manager' ) ) {
        require_once( WPSL_PLUGIN_DIR . 'includes/admin/tools/class-license-manager.php' );
    }

    $plugins = get_plugins();

    foreach ( $plugins as $k => $plugin ) {
        if ( strpos( $k, 'wp-store-locator-' ) !== false ) {
            $addon_name = array_map('trim', explode('-', $plugin['Name'] ) );

            if ( count( $addon_name ) > 1 ) {
                if ( $addon_name[1] == 'Widget' ) {
                    $addon_name[1] = 'Search ' . $addon_name[1]; // The price to pay for not naming it correctly in the add-on itself...
                }

                $add_on = $addon_name[1];
            } else {
                $add_on = $addon_name[0];
            }

            $license = new \WPSL\Admin\Tools\License_Manager( $add_on );

            $short_name = $license->item_shortname;

            // Move the license key to a new option field.
            if ( $license_data = get_option( $short_name . '_license_data' ) ) {
                update_option( $short_name . '_license_key', $license_data['key'], false );
                delete_option( $short_name . '_license_data' );

                /**
                 * Save the old data again to disable the autoload option.
                 *
                 * We won't delete this data for now in case users want to downgraden from 3.x
                 * if they run into issues, but it will be removed at a later stage.
                 */
                update_option( $short_name . '_license_data', $license_data,'no' );
            }
        }
    }
    
    // Mark license migration as complete
    update_option( 'wpsl_license_data_migrated', true, 'no' );
}

/**
 * Check if we need to migrate the v2 settings to v3.
 * 
 * We no longer store everything together in the wpsl_settings 
 * group, but each option group is stored in its own option.
 * 
 * @since 3.0.0
 */
function wpsl_migrate_settings() {
    $migration_state = get_option( 'wpsl_v3_migration_complete' );

    if ( $migration_state == 'in_progress' ) {
        $settings_handler = wpsl_get_service( 'wpsl_settings' );

        return $settings_handler->migrate();
    }

    return true;
}

/**
 * Decide whether the v2 -> v3 settings migration should run, and which
 * version the data is migrated from.
 *
 * @since  3.0.0
 * @param  string $current_version The version number the site is upgrading from.
 * @return string|false The version to migrate from, or false when no migration should run.
 */
function wpsl_v3_migration_source( $current_version ) {
    $migration_state = get_option( 'wpsl_v3_migration_complete' );

    if ( $migration_state === 'complete' ) {
        return false;
    }

    if ( version_compare( $current_version, '3.0', '<' ) ) {
        return $current_version;
    }

    if ( false === $migration_state ) {
        return '2.3.22';
    }

    return false;
}

/**
 * Run the settings-migration step and apply its outcome ( notices, follow-up
 * key validation ). Shared between the automatic 2.x -> 3.0 upgrade in
 * wpsl_check_upgrade() and the manual "Retry migration" tool
 * ( wpsl_retry_migration() ) for when that first attempt failed.
 *
 * wpsl_check_upgrade() always bumps wpsl_version to WPSL_VERSION_NUM before
 * returning, success or failure, so a failed attempt is never retried
 * automatically on a later page load — only this shared path re-runs it.
 *
 * @since  3.0.0
 * @param  string $previous_version The version being migrated from.
 * @return bool True if the migration succeeded.
 */
function wpsl_run_settings_migration( $previous_version ) {
    $migration_success = wpsl_migrate_settings();

    if ( $migration_success ) {
        update_option( 'wpsl_v3_migration_complete', 'complete', 'no' );

        wpsl_validate_migrated_api_keys();

        // Tell users with custom v2 templates that legacy template mode was enabled.
        $custom_templates = wpsl_get_custom_templates();

        if ( $custom_templates ) {
            $template_list = '';

            foreach ( $custom_templates as $custom_template ) {
                $template_list .= '<li>' . esc_html( $custom_template['name'] ) . '</li>';
            }

            $notice  = '<p>' . esc_html__( 'We detected one or more custom templates created for WP Store Locator 2:', 'wp-store-locator' ) . '</p>';
            $notice .= '<ul class="ul-disc">' . $template_list . '</ul>';
            $notice .= '<p>' . esc_html__( 'They are detected automatically and keep working, so there is nothing to configure. Update them to the v3 structure when you can, to stay compatible with current and future features.', 'wp-store-locator' ) . '</p>';
            $notice .= '<p><a target="_blank" href="https://wpstorelocator.co/document/load-custom-store-locator-template/#legacy-mode">' . esc_html__( 'Read more', 'wp-store-locator' ) . '</a></p>';

            \WPSL\Admin\Core\Notices::instance()->save( 'update', $notice, true );
        }

        wpsl_notify_split_api_key( $previous_version );
    } else {
        $notice_message = sprintf(
            /* translators: 1: opening link tag to the tools section, 2: closing link tag */
            esc_html__( 'WP Store Locator settings migration failed. Please check the error log or contact support, or %1$sretry the migration%2$s.', 'wp-store-locator' ),
            '<a class="wpsl-trigger-nav" data-item="tools" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-tools' ) ) . '">',
            '</a>'
        );

        \WPSL\Admin\Core\Notices::instance()->save( 'error', $notice_message );
    }

    return $migration_success;
}

/**
 * AJAX callback for the "Retry migration" tool ( Settings -> Tools ),
 * shown only while a migration is stuck at 'in_progress'. Re-runs
 * wpsl_run_settings_migration() using the originally recorded source
 * version, since the automatic upgrade path never retries on its own -
 * see the note on that function.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_retry_migration() {
    if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
        wp_send_json_error();
    }

    if ( ! isset( $_REQUEST['wpsl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['wpsl_nonce'] ) ), 'wpsl-retry-migration' ) ) {
        wp_send_json_error();
    }

    if ( get_option( 'wpsl_v3_migration_complete' ) !== 'in_progress' ) {
        wp_send_json_error( [ 'message' => __( 'There is no failed migration to retry.', 'wp-store-locator' ) ] );
    }

    $previous_version = get_option( 'wpsl_updated_from' );

    if ( wpsl_run_settings_migration( $previous_version ) ) {
        wp_send_json_success( [ 'message' => __( 'Migration completed successfully. Reload the page to see your restored settings.', 'wp-store-locator' ) ] );
    }

    error_log( 'WPSL: Migration retry failed for source version ' . $previous_version ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- deliberate diagnostic log, see class-manager.php::migrate()

    wp_send_json_error( [ 'message' => __( 'Migration failed again. Check the PHP error log for details.', 'wp-store-locator' ) ] );
}
add_action( 'wp_ajax_wpsl_retry_migration', 'wpsl_retry_migration' );

/**
 * Validate the migrated Google Maps API keys.
 *
 * v3 gates the red error border on a key input behind a persisted
 * wpsl_valid_*_key option, set only by "Validate API keys" or a settings save.
 * v2 had no such concept, so a migrated key is unvalidated rather than invalid
 * - but the settings page cannot tell those apart, and showed a site's
 * untouched, still-working keys as broken the first time it loaded.
 *
 * The server key is tested with a real Geocode request, as "Validate API keys"
 * already does. The browser key cannot be, since that needs an actual browser
 * running the Maps JavaScript API, so it is marked valid instead: 2.x was
 * already loading maps with it.
 *
 * Mapbox and Stadia keys never existed pre-3.0, so nothing to validate there.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_validate_migrated_api_keys() {
    $api = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    if ( ! isset( $api['active_map_service'] ) || $api['active_map_service'] !== 'gmaps' ) {
        return;
    }

    if ( ! empty( $api['gmaps_server_key'] ) ) {
        wpsl_get_service( 'validate_keys' )->check( 'gmaps', 'server', $api['gmaps_server_key'], false );
    }

    if ( ! empty( $api['gmaps_browser_key'] ) ) {
        update_option( 'wpsl_valid_gmaps_browser_key', 1, 'no' );
    }
}

/**
 * Check if we need to show the notice that tells users that the store locations
 * need to be converted to custom post types before the update from 1.x to 2.x is complete.
 * 
 * @since 2.0
 * @return void
 */
function wpsl_cpt_update_state() {
    $conversion_state = get_option( 'wpsl_convert_cpt' );
    $remaining = wpsl_remaining_cpt_count();
    
    // If stores need converting but the option isn't set, set it now
    if ( $remaining && $conversion_state != 'in_progress' ) {
        update_option( 'wpsl_convert_cpt', 'in_progress', 'no' );
        $conversion_state = 'in_progress';
    }
    
    if ( $conversion_state == 'in_progress' ) {
        if ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) {
            
            // Check if this notice already exists before saving
            /* translators: %1$s: opening span tag with remaining count, %2$s: opening link tag, %3$s: closing link tag */
            $notice_message = sprintf( esc_html__( 'Because you updated WP Store Locator from version 1.x, the %1$s current store locations need to be %2$sconverted%3$s to custom post types.', 'wp-store-locator' ), "<span class='wpsl-cpt-remaining'>" . $remaining . "</span>", "<a href='#' id='wpsl-cpt-dialog'>", "</a>" );
            
            $current_notices = get_option( 'wpsl_notices' );
            $notice_exists = false;
            
            if ( $current_notices && is_array( $current_notices ) ) {
                if ( ! wpsl_is_multi_array( $current_notices ) ) {
                    $current_notices = [ $current_notices ];
                }
                
                foreach ( $current_notices as $notice ) {
                    if ( isset( $notice['message'] ) && isset( $notice['type'] ) && 
                         $notice['type'] === 'error' && 
                         strpos( $notice['message'], 'wpsl-cpt-dialog' ) !== false ) {
                        $notice_exists = true;
                        break;
                    }
                }
            }
            
            if ( ! $notice_exists ) {
                \WPSL\Admin\Core\Notices::instance()->save( 'error', $notice_message );
                add_action( 'admin_footer',  'wpsl_cpt_dialog_html' );
            } else {
                // Notice already exists, just add the dialog HTML
                add_action( 'admin_footer',  'wpsl_cpt_dialog_html' );
            }
        }

        add_action( 'admin_enqueue_scripts',     'wpsl_convert_cpt_js' );	
        add_action( 'wp_ajax_convert_cpt',       'wpsl_convert_cpt' );
        add_action( 'wp_ajax_convert_cpt_count', 'wpsl_convert_cpt_count' );
    }
}

/**
 * Include the js file that handles the ajax request to 
 * start converting the 1.x store locations to custom post types.
 * 
 * @since 2.0
 * @return void
 */
function wpsl_convert_cpt_js() {
    wp_enqueue_script( 'jquery-ui-dialog' );
    wp_enqueue_style( 'wp-jquery-ui-dialog' );
    
    $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

    wp_enqueue_script( 'wpsl-queue', plugins_url( 'assets/src/admin/js/ajax-queue' . $min . '.js', WPSL_PLUGIN_FILE ), [ 'jquery' ], WPSL_VERSION_NUM, true );
    wp_enqueue_script( 'wpsl-cpt-js', plugins_url( 'assets/src/admin/js/wpsl-cpt-upgrade' . $min . '.js', WPSL_PLUGIN_FILE ), [ 'jquery', 'wpsl-queue' ], WPSL_VERSION_NUM, true );
    
    wp_localize_script( 'wpsl-cpt-js', 'wpsl_cpt_vars', [
            'count_nonce'  => wp_create_nonce( 'wpsl-cpt-count' ),
            'convert_nonce' => wp_create_nonce( 'wpsl-cpt-fix' )
        ]
    );
}

/**
 * The html for the lightbox
 * 
 * @since 2.0
 * @return void
 */
function wpsl_cpt_dialog_html() {

    ?>
    <div id="wpsl-cpt-lightbox" style="display:none;">
        <span class="tb-close-icon"></span>
        <p class="wpsl-cpt-remaining"><?php esc_html_e( 'Store locations to convert:', 'wp-store-locator' ); echo '<span></span>'; ?></p>
        <div class="wslp-cpt-fix-wrap">
            <input id="wpsl-start-cpt-conversion" class="button-primary" type="submit" value="<?php esc_html_e( 'Start Converting', 'wp-store-locator' ); ?>" >
            <img class="wpsl-preloader" alt="preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" />
        </div>
        <input type="hidden" name="wpsl-cpt-fix-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-cpt-fix' ) ); ?>" />
        <input type="hidden" name="wpsl-cpt-conversion-count" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-cpt-count' ) ); ?>" />
    </div>
    <div id="wpsl-cpt-overlay" style="display:none;"></div>
    <style>
        .wslp-cpt-fix-wrap {
            float:left;
            clear:both;
            width:100%;
            margin:0 0 15px 0;
        }

        #wpsl-cpt-lightbox .wpsl-cpt-remaining span {
            margin-left:5px;
        }

        #wpsl-start-cpt-conversion {
            float:left;
        }

        .wslp-cpt-fix-wrap .wpsl-preloader,
        .wslp-cpt-fix-wrap span {
            float:left;
            margin:8px 0 0 10px;    
        }

        .wslp-cpt-fix-wrap .wpsl-preloader {
            display: none;
        }
        
        #wpsl-cpt-lightbox {
            position:fixed;
            width:450px;
            left:50%;
            right:50%;
            top:3.8em;
            padding:15px;
            background:none repeat scroll 0 0 #fff;
            border-radius:3px;
            margin-left:-225px;
            z-index: 9999;
        }
        
        #wpsl-cpt-overlay {
            position:fixed;
            right:0;
            top:0;
            z-index:9998;
            background:none repeat scroll 0 0 #000;
            bottom:0;
            left:0;
            opacity:0.5;
        }
        
        .tb-close-icon {
            color: #666;
            text-align: center;
            line-height: 29px;
            width: 29px;
            height: 29px;
            position: absolute;
            top: 0;
            right: 0;
        }

        .tb-close-icon:before {
            content: '\f158';
            font: normal 20px/29px 'dashicons';
            speak: none;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .tb-close-icon:hover {
            color: #999 !important;
            cursor: pointer;
        }
    </style>
    <?php    
}

/**
 * Handle the ajax call to start converting the
 * store locations to custom post types.
 * 
 * @since 2.0
 * @return void|string json on completion
 */
function wpsl_convert_cpt() {
    if ( !current_user_can( 'manage_options' ) )
        die( '-1' );
    check_ajax_referer( 'wpsl-cpt-fix' );

    // Start the cpt coversion.
    wpsl_cpt_conversion();

    exit();
}

/**
 * Get the amount of locations that still need to be converted.
 * 
 * @since 2.0
 * @return string json The amount of locations that still need to be converted
 */
function wpsl_convert_cpt_count() {
    if ( !current_user_can( 'manage_options' ) )
        die( '-1' );
    check_ajax_referer( 'wpsl-cpt-count' );
    
    $remaining_count = wpsl_remaining_cpt_count();
        
    $response['success'] = true;
    
    if ( $remaining_count ) {
        $response['count'] = $remaining_count;
    } else {
        /* translators: %1$s: line breaks, %2$s: opening link tag to All Stores page, %3$s: closing link tag */
        $response['url'] = sprintf( esc_html__( 'All the store locations are now converted to custom post types. %1$s You can view them on the %2$sAll Stores%3$s page.', 'wp-store-locator' ), '<br><br>', '<a href="' . admin_url( 'edit.php?post_type=wpsl_stores' ) . '">', '</a>' );
        
        delete_option( 'wpsl_convert_cpt' );
    }
    
    wp_send_json( $response );
    
    exit();
}

/**
 * Return the difference between the number of existing wpsl custom post types, 
 * and the number of records in the old wpsl_stores database.
 * 
 * @since 2.0
 * @return int|boolean $remaining The amount of locations that still need to be converted
 */
function wpsl_remaining_cpt_count() {
    
    global $wpdb;

    $table = $wpdb->prefix . 'wpsl_stores';
    
    // Check if the v1 table exists before querying it
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check, caching not applicable
    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    
    if ( ! $table_exists ) {
        return false;
    }
    
    $count = wp_count_posts( 'wpsl_stores' );
    
    if ( isset( $count->publish ) && isset( $count->draft ) ) {
        $cpt_count = $count->publish + $count->draft;
    } else {
        $cpt_count = 0;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade count from legacy table, caching not applicable
    $db_count   = $wpdb->get_var( "SELECT COUNT(wpsl_id) FROM " . esc_sql( $table ) );
    $difference = $db_count - $cpt_count;
    
    /* 
     * This prevents users who used the 2.0 beta, and later added 
     * more stores from seeing the upgrade notice again.
     */
    $remaining = ( $difference < 0 ) ? false : $difference;
                    
    return $remaining;
}

/**
 * Convert the existing locations to custom post types.
 * 
 * @since 2.0
 * @return void|boolean True if the conversion is completed
 */
function wpsl_cpt_conversion() {
    
    global $wpdb;
    
    // Try to disable the time limit to prevent timeouts.
    @set_time_limit( 0 );

    $meta_keys  = [ 'address', 'address2', 'city', 'state', 'zip', 'country', 'country_iso', 'lat', 'lng', 'phone', 'fax', 'url', 'email', 'hours' ];
    $offset     = wpsl_remaining_cpt_count();
    $wpsl_table = $wpdb->prefix . 'wpsl_stores';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time data migration from legacy table, caching not applicable
    $stores     = $wpdb->get_results( $wpdb->prepare( "(SELECT * FROM " . esc_sql( $wpsl_table ) . " ORDER BY wpsl_id DESC LIMIT %d) ORDER BY wpsl_id ASC", $offset ) );
    
    foreach ( $stores as $store ) {
        
        // Make sure we set the correct post status.
        if ( $store->active ) {
            $post_status = 'publish';
        } else {
            $post_status = 'draft';
        }
        
        $post =  [
            'post_type'    => 'wpsl_stores',
            'post_status'  => $post_status,
            'post_title'   => $store->store,
            'post_content' => $store->description              
        ];

        $post_id = wp_insert_post( $post );

        if ( $post_id ) {
            
            // Save the data from the wpsl_stores db table as post meta data,
            // sanitizing per field type to match how the editor stores it.
            foreach ( $meta_keys as $meta_key ) {
                if ( isset( $store->{$meta_key} ) && !empty( $store->{$meta_key} ) ) {
                    $value = $store->{$meta_key};

                    switch ( $meta_key ) {
                        case 'url':
                            $value = esc_url_raw( $value );
                            break;
                        case 'email':
                            $value = sanitize_email( $value );
                            break;
                        case 'phone':
                        case 'fax':
                            $value = wpsl_sanitize_phone( $value );
                            break;
                        case 'hours':
                            /*
                             * 1.x stored the opening hours as free-form text, one day per line,
                             * and the upgrade puts the editor in 'textarea' mode to keep showing
                             * them that way. sanitize_text_field() collapses newlines, which would
                             * flatten the whole schedule onto a single line.
                             */
                            $value = sanitize_textarea_field( $value );
                            break;
                        default:
                            $value = sanitize_text_field( $value );
                            break;
                    }

                    update_post_meta( $post_id, 'wpsl_' . $meta_key, $value );
                }
            }
            
            // If we have a thumb ID set the post thumbnail for the inserted post.
            if ( $store->thumb_id ) {
                set_post_thumbnail( $post_id, $store->thumb_id );
            }
        }
    }
}