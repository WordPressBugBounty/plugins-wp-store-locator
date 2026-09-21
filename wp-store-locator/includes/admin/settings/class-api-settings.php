<?php
/**
 * API settings.
 * 
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
    
/**
 * Handle the API settings
 *
 * @since 3.0.0
 */
class API_Settings {
    
    /**
     * Settings manager instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;
    
    /**
     * Constructor.
     *
     * @since 3.0.0
     * @param WpslSettings $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;
    }
    
    /**
     * Options for the language and region list.
     *
     * @since  1.0.0
     * @param  string      $list        The request list type
     * @return string|void $option_list The html for the selected list, or nothing if the $list contains invalud values
     */
    public function get_option_list( $list ) {
        
        switch ( $list ) {
            case 'openrouteservice_language':
                $api_option_list = wpsl_language_restrictions( 'openrouteservice_language' );
                break;
            case 'mapbox_language':
                $api_option_list = wpsl_language_restrictions( 'mapbox_language' );
                break;
            case 'gmaps_language':
            case 'osm_language':
            case 'stadia_language':
                $api_option_list = wpsl_language_restrictions( 'all_languages' );
                break;			
            case 'gmaps_region':
                $api_option_list = wpsl_get_regions();
                break;
            case 'mapbox_geocoder':
                $api_option_list = [
                    esc_html__( 'Nominatim / OpenStreetMap (free)', 'wp-store-locator' ) => 'nominatim',
                    esc_html__( 'Mapbox (paid)', 'wp-store-locator' )  => 'mapbox',
                ];

                break;
        }
        
        // Make sure we have an array with a value.
        if ( ! empty( $api_option_list ) && ( is_array( $api_option_list ) ) ) {
            $option_list = '';
            $i = 0;
            
            foreach ( $api_option_list as $api_option_key => $api_option_value ) {
                
                // Get the correct setting key without the redundant prefix
                $setting_key = $list;
                $api_settings = $this->settings->get_group( 'api' );
                $api_setting = isset( $api_settings[$setting_key] ) ? $api_settings[$setting_key] : null;
            
                // If no option value exist, set the first one as selected.
                if ( $i == 0 && ( ! isset( $api_setting ) || empty( $api_setting ) ) ) {
                    $selected = ' selected="selected"';
                } else {
                    if ( isset( $api_setting ) && is_array( $api_setting ) && in_array( $api_option_value, $api_setting ) ) {
                        $selected = ' selected="selected"';
                    } else {
                        $selected = ( isset( $api_setting ) && $api_setting == $api_option_value ) ? ' selected="selected"' : '';
                    }
                }
                
                $disabled = ( $api_option_value === '-' ) ? ' disabled' : '';
                
                $option_list .= '<option value="' . esc_attr( $api_option_value ) . '"' . $selected . '' . $disabled . '>' . esc_html( $api_option_key ) . '</option>' . "\n";
                $i++;
            }
                                            
            return $option_list;				
        }
    }
}