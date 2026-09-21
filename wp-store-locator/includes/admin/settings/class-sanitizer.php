<?php
/**
 * Handle the settings sanitization.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Hours\Service as Hours;

use WPSL\Admin\Settings\Validate_Keys;
use WPSL\Admin\Settings\Manager as AdminSettings;

/**
 * Sanitize the settings.
 *
 * @since 3.0.0
 */
class Sanitizer {

    /**
    * Settings object.
    *
    * @since 3.0.0
    * @var \WPSL\Core\Settings\Manager
    */
   private $settings;

   /**
    * Admin Settings object.
    *
    * @since 3.0.0
    * @var \WPSL\Admin\Settings\Manager
    */
   private $admin_settings;

    /**
     * Hours object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Hours\Service
     */
    private $hours;

    /**
     * Validate_Keys object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\Validate_Keys
     */
    private $validate_keys;

    /**
     * Constructor.
     * 
     * @since 3.0.0
     * @param WpslSettings     $settings       Core settings manager
     * @param Hours            $hours          Hours service
     * @param Validate_Keys    $validate_keys  API key validator
     * @param AdminSettings    $admin_settings Admin settings handler
     */
    public function __construct( WpslSettings $settings, Hours $hours, Validate_Keys $validate_keys, AdminSettings $admin_settings ) {
        $this->settings       = $settings;
        $this->hours          = $hours;
        $this->validate_keys  = $validate_keys;
        $this->admin_settings = $admin_settings;
    }

    /**
     * Sanitize template-specific dimensions.
     * 
     * @since  3.0.0
     * @param  array  $input    The full input array
     * @param  string $template Template name (horizontal, vertical, default)
     * @param  array  $fields   Field definitions with default values
     * @return array  Sanitized dimension values
     */
    private function sanitize_template_dimensions( $input, $template, $fields ) {
        $output = [];
        
        if ( ! isset( $input['dimensions'][$template] ) ) {
            return $output;
        }
        
        foreach ( $fields as $field => $default ) {
            if ( strpos( $field, '_mode' ) !== false ) {
                // Mode field (default/custom)
                $output[$field] = isset( $input['dimensions'][$template][$field] ) && 
                                in_array( $input['dimensions'][$template][$field], [ 'default', 'custom' ] )
                    ? sanitize_text_field( $input['dimensions'][$template][$field] )
                    : 'custom';
            } else {
                // Numeric field
                $output[$field] = isset( $input['dimensions'][$template][$field] ) && 
                                absint( $input['dimensions'][$template][$field] )
                    ? absint( $input['dimensions'][$template][$field] )
                    : $default;
            }
        }
        
        return $output;
    }

    /**
     * Sanitize country codes from comma-separated string.
     * 
     * @since  3.0.0
     * @param  string $input_string Comma-separated country codes
     * @return mixed  Array of valid country codes or empty string
     */
    private function sanitize_country_codes( $input_string ) {
        if ( empty( $input_string ) ) {
            return '';
        }
        
        $countries = array_map( 'trim', explode( ',', $input_string ) );
        $valid = [];
        
        foreach ( $countries as $country ) {
            if ( ctype_alpha( $country ) && strlen( $country ) == 2 ) {
                $valid[] = strtoupper( sanitize_text_field( $country ) );
            }
        }
        
        return ! empty( $valid ) ? $valid : '';
    }

    /**
     * Sanitize region array (2-letter country/region codes).
     * 
     * @since  3.0.0
     * @param  array $input_array Array of region codes
     * @return mixed Array of valid region codes or empty string
     */
    private function sanitize_region_array( $input_array ) {
        if ( ! is_array( $input_array ) || empty( $input_array ) ) {
            return '';
        }
        
        $valid = [];
        
        foreach ( $input_array as $region ) {
            if ( ctype_alpha( $region ) && strlen( $region ) == 2 ) {
                $valid[] = sanitize_text_field( $region );
            }
        }
        
        return ! empty( $valid ) ? $valid : '';
    }

    /**
     * Sanitize the API settings.
     * 
     * @since  3.0.0
     * @param  array $input
     * @return array $output
     */
    public function api( $input ) {
        $output = [];
        $map_types = wpsl_get_map_services();

        // Only form saves reach this method, so this is a person choosing.
        \WPSL\Admin\Home\Home::mark_map_service_chosen();

        if ( array_key_exists( $input['active_map_service'], $map_types ) ) {
            $output['active_map_service'] = sanitize_text_field( $input['active_map_service'] );
        } else {
            $output['active_map_service'] = 'osm';
        }

        if ( in_array( $input['mapbox_geocoder'], [ 'mapbox', 'nominatim' ] ) ) {
            $output['mapbox_geocoder'] = sanitize_text_field( $input['mapbox_geocoder'] );
        } else {
            $output['mapbox_geocoder'] = 'mapbox';
        }

        // Always just sanitize the browser key (no validation needed)
        if ( isset( $input['gmaps_browser_key'] ) ) {
            $output['gmaps_browser_key'] = sanitize_text_field( $input['gmaps_browser_key'] );
        }

        // Deal with the different API keys.
        // Keys are always validated when present (so the wpsl_valid_*_key options
        // stay accurate), but a notice is only shown for the active map provider.
        // Validate Google Maps server key if Google Maps is active OR if a key is provided
        if ( $input['active_map_service'] === 'gmaps' || ! empty( trim( $input['gmaps_server_key'] ) ) ) {
            $notify = ( $input['active_map_service'] === 'gmaps' );
            $output['gmaps_server_key'] = $this->validate_keys->check( 'gmaps', 'server', $input['gmaps_server_key'], $notify );
        } else {
            $output['gmaps_server_key'] = sanitize_text_field( $input['gmaps_server_key'] );
        }

        // Validate Mapbox key if Mapbox is active OR if a key is provided
        if ( $input['active_map_service'] === 'mapbox' || ! empty( trim( $input['mapbox_key'] ) ) ) {
            $notify = ( $input['active_map_service'] === 'mapbox' );
            $output['mapbox_key'] = $this->validate_keys->check( 'mapbox', 'mapbox', $input['mapbox_key'], $notify );
        } else {
            $output['mapbox_key'] = sanitize_text_field( $input['mapbox_key'] );
        }

        // Validate Openrouteservice key if OSM or Stadia is active OR if a key is provided
        if ( in_array( $input['active_map_service'], [ 'osm', 'stadia' ], true ) || ! empty( trim( $input['openrouteservice_key'] ) ) ) {
            $notify = in_array( $input['active_map_service'], [ 'osm', 'stadia' ], true );
            $output['openrouteservice_key'] = $this->validate_keys->check( 'openrouteservice', 'openrouteservice', $input['openrouteservice_key'], $notify );
        } else {
            $output['openrouteservice_key'] = sanitize_text_field( $input['openrouteservice_key'] );
        }

        // Validate Stadia key if Stadia is active OR if a key is provided
        if ( $input['active_map_service'] === 'stadia' || ! empty( trim( $input['stadia_key'] ) ) ) {
            $notify = ( $input['active_map_service'] === 'stadia' );
            $output['stadia_key'] = $this->validate_keys->check( 'stadia', 'stadia', $input['stadia_key'], $notify );
        } else {
            $output['stadia_key'] = sanitize_text_field( $input['stadia_key'] );
        }
        
        $output['gmaps_language']  = sanitize_text_field( $input['gmaps_language'] );
        $output['mapbox_language'] = sanitize_text_field( $input['mapbox_language'] );

        $output['multiple_regions'] = isset( $input['multiple_regions'] ) ? $this->sanitize_region_array( $input['multiple_regions'] ) : '';

        $output['gmaps_region']              = wp_filter_nohtml_kses( $input['gmaps_region'] );

        // How the Google Maps region is applied: soft 'bias' or hard 'restrict'.
        $output['region_restriction_type'] = ( isset( $input['region_restriction_type'] ) && $input['region_restriction_type'] === 'restrict' ) ? 'restrict' : 'bias';

        $output['openrouteservice_language'] = sanitize_text_field( $input['openrouteservice_language'] );
        $output['osm_language']              = sanitize_text_field( $input['osm_language'] );
        $output['stadia_language']           = sanitize_text_field( $input['stadia_language'] );
        $output['stadia_eu_endpoints']       = isset( $input['stadia_eu_endpoints'] ) ? 1 : '';

        // Check if we need to delete transients based on API settings changes
        $this->admin_settings->set_delete_transient_option( 'api', $output );

        return $output;
    }

    /**
     * Sanitize the search settings.
     *
     * @since  3.0.0
     * @param  array $input  The submitted search values
     * @return array $output The sanitized values
     */
    public function search( $input ) {
        $output = [];

        if ( isset( $input['search_method'] ) && in_array( $input['search_method'], [ 'geocode', 'name' ] ) ) {
            $output['search_method'] = sanitize_key( $input['search_method'] );
        } else {
            $output['search_method'] = 'geocode';
        }

        $output['input_placeholder'] = sanitize_text_field( $input['input_placeholder'] );
        $output['auto_locate'] = isset( $input['auto_locate'] ) ? 1 : 0;

        if ( in_array( $input['auto_locate_format'], [ 'zip', 'city', 'formatted_address' ] ) ) {
            $output['auto_locate_format'] = sanitize_text_field( $input['auto_locate_format'] );
        } else {
            $output['auto_locate_format'] = 'zip';
        }
        
        if ( in_array( $input['auto_locate_trigger'], [ 'pageload', 'user_request' ] ) ) {
            $output['auto_locate_trigger'] = sanitize_text_field( $input['auto_locate_trigger'] );
        } else {
            $output['auto_locate_trigger'] = 'pageload';
        }

        // Check the search filter.
        $output['autocomplete'] = isset( $input['autocomplete'] ) ? 1 : 0; //gmaps only

        // See which Google Maps autocomplete API is used.
        if ( isset( $input['gmaps'] ) && isset( $input['gmaps']['autocomplete_api_version'] ) && in_array( $input['gmaps']['autocomplete_api_version'], [ 'legacy', 'latest' ] ) ) {
            $output['api_versions']['gmaps']['autocomplete'] = sanitize_text_field( $input['gmaps']['autocomplete_api_version'] );
        } else {
            $output['api_versions']['gmaps']['autocomplete'] = 'latest';
        }

        $output['autosubmit_autocomplete'] = isset( $input['autosubmit_autocomplete'] ) ? 1 : 0;
        $output['input_only']              = isset( $input['input_only'] ) ? 1 : 0;
        $output['force_postalcode']        = isset( $input['force_postalcode'] ) ? 1 : 0;

        $output['distance_unit'] = ( $input['distance_unit'] == 'km' ) ? 'km' : 'mi';

        // Check for a valid max results value, otherwise we use the default.
        if ( ! empty( $input['max_results'] ) && strpos( $input['max_results'] , '[' ) !== false ) {
            $output['max_results'] = sanitize_text_field( $input['max_results'] );
        } else {
            $this->validation_error( empty( $input['max_results'] ) ? 'max_results_empty' : 'max_results' );

            $output['max_results'] = $this->settings->get_default( 'search', 'max_results' );
        }

        // See if a search radius value exist, otherwise we use the default.
        if ( ! empty( $input['search_radius'] ) && strpos( $input['search_radius'] , '[' ) !== false ) {
            $output['search_radius'] = sanitize_text_field( $input['search_radius'] );
        } else {
            $this->validation_error( empty( $input['search_radius'] ) ? 'search_radius_empty' : 'search_radius' );

            $output['search_radius'] = $this->settings->get_default( 'search', 'search_radius' );
        }

        if ( array_key_exists( $input['orderby'], wpsl_get_search_order_options() ) ) {
            $output['orderby'] = sanitize_text_field( $input['orderby'] );
        } else {
            $output['orderby'] = 'distance';
        }

        if ( in_array( $input['order'], [ 'asc', 'desc' ] ) ) {
            $output['order'] = sanitize_text_field( $input['order'] );
        } else {
            $output['order'] = 'asc';
        }

        $output['enforce_borders']       = isset( $input['enforce_borders'] ) ? 1 : 0;
        $output['full_search']           = isset( $input['full_search'] ) ? 1 : 0;
        $output['number_results']        = isset( $input['number_results'] ) ? 1 : 0;
        $output['find_nearest_location'] = isset( $input['find_nearest_location'] ) ? 1 : 0;

        // Filter settings (moved from appearance)
        $output['radius_dropdown']         = isset( $input['radius_dropdown'] ) ? 1 : 0;
        $output['results_dropdown']        = isset( $input['results_dropdown'] ) ? 1 : 0;
        $output['category_filter']         = isset( $input['category_filter'] ) ? 1 : 0;
        $output['all_categories_required'] = isset( $input['all_categories_required'] ) ? 1 : 0;
        $output['category_filter_only']    = isset( $input['category_filter_only'] ) ? 1 : 0;

        // Validate category filter type
        if ( in_array( $input['category_filter_type'], [ 'dropdown', 'checkboxes' ] ) ) {
            $output['category_filter_type'] = sanitize_text_field( $input['category_filter_type'] );
        } else {
            $output['category_filter_type'] = 'dropdown';
        }

        // Validate category default (should be a term ID or empty)
        if ( isset( $input['category_default'] ) && ! empty( $input['category_default'] ) ) {
            $output['category_default'] = absint( $input['category_default'] );
        } else {
            $output['category_default'] = '';
        }

        // The autoload cache stores the already sorted results, so flush it when the sort order changes.
        $this->admin_settings->set_delete_transient_option( 'search', $output );

        return $output;
    }

    /**
     * Sanitize the map settings.
     * 
     * @since  3.0.0
     * @param  array $input  The submitted map values
     * @return array $output The sanitized values
     */
    public function map( $input ) {
        $output = [];

        if ( isset( $input['start_name'] ) ) {
            $output['start_name'] = sanitize_text_field( $input['start_name'] );
        } else {
            $output['start_name'] = '';
        }

        // If no location name is provided, empty the latlng values
        if ( empty( $output['start_name'] ) ) {
            $this->validation_error( 'start_point' );

            $output['start_latlng'] = '';
        } else {
            /**
             * Always ensure we have coordinates when a start name is provided.
             * This handles cases where:
             * 1. No coordinates are provided (empty latlng)
             * 2. The start name has changed but coordinates haven't been updated
             * 3. JS errors prevented autocomplete from working
             */
            if ( empty( $input['start_latlng'] ) || 
                 ( $this->settings->get( 'map', 'start_name' ) != $input['start_name'] && 
                   $this->settings->get( 'map', 'start_latlng' ) == $input['start_latlng'] ) ) {
                
                $start_latlng = wpsl_get_address_latlng( $input['start_name'], $this->settings->get( 'api', 'active_map_service' ) );
            } else {
                $start_latlng = sanitize_text_field( $input['start_latlng'] );
            }
            
            $output['start_latlng'] = $start_latlng;
        }

        $output['autoload'] = isset( $input['autoload'] ) ? 1 : 0;
        $output['autoload_start_latlng'] = isset( $input['autoload_start_latlng'] ) ? 1 : 0;

        // Make sure the autoload limit is either empty or an int.
        if ( empty( $input['autoload_limit'] ) ) {
            $output['autoload_limit'] = '';
        } else {
            $output['autoload_limit'] = absint( $input['autoload_limit'] );
        }

        // Do we need to run the fitBounds function make the markers fit in the viewport?
        $output['run_fitbounds'] = isset( $input['run_fitbounds'] ) ? 1 : 0;

        /**
         * Verify that the zoom level is within the valid range of 1 to 12. 
         * If it is outside this range, set it to the default value of 3.
         */
        $output['zoom_level'] = wpsl_valid_zoom_level( $input['zoom_level'] );	
        
        // Check for a valid max auto zoom level.
        $max_zoom_levels = wpsl_get_max_zoom_levels();
        
        if ( isset( $input['max_auto_zoom'] ) && in_array( absint( $input['max_auto_zoom'] ), $max_zoom_levels ) ) {
            $output['auto_zoom_level'] = absint( $input['max_auto_zoom'] );
        } else {
            $output['auto_zoom_level'] = $this->settings->get_default( 'map', 'auto_zoom_level' );
        }

        // Google Maps only.
        $output['streetview'] 		= isset( $input['streetview'] ) ? 1 : 0;
        $output['type_control']     = isset( $input['type_control'] ) ? 1 : 0;
        $output['scrollwheel']      = isset( $input['scrollwheel'] ) ? 1 : 0;	
        $output['control_position'] = ( $input['control_position'] == 'left' ) ? 'left' : 'right';
        $output['type']             = wpsl_valid_map_type( $input['type'] );

        $output['show_credits'] = isset( $input['show_credits'] ) ? 1 : 0;

        // Check if we need to delete transients based on map settings changes
        $this->admin_settings->set_delete_transient_option( 'map', $output );

        return $output;
    }

    /**
     * Sanitize UX settings.
     * 
     * @since  3.0.0
     * @param  array $input  The submitted UX values
     * @return array $output The sanitized values
     */
    public function ux( $input ) {
        $output = [];

        $marker_effects = [
            'bounce',
            'info_window',
            'ignore'
        ];
        
        $ux_checkboxes = [
            'new_window',
            'reset_map',
            'listing_below_no_scroll',
            'direction_redirect',
            //'more_info',
            'store_url',
            'phone_url',
            'marker_streetview',
            'marker_zoom_to',
            'mouse_focus',
            'show_contact_details',
            'clickable_contact_details',
            'hide_country',
            'hide_distance',
            'hide_closed_hours',
            //'show_post_content',
            //'show_hours',
            'show_hour_status',
            'expand_hours',
            'address_event'
        ];
        
        // Check if the ux checkboxes are checked.
        foreach ( $ux_checkboxes as $ux_key ) {
            $output[$ux_key] = isset( $input[$ux_key] ) ? 1 : 0; 
        }

        // Check the locations for the multiselect options.
        $multiselect_locations = wpsl_get_multiselect_ux_locations();

        $multiselect_options = [
            'contact_details',
            'hours',
            'description'
        ];

        foreach ( $multiselect_options as $multiselect_option ) {
            if ( isset( $input[$multiselect_option] ) ) {
                foreach ( $input[$multiselect_option] as $multiselect_location ) {
                    if ( array_key_exists( $multiselect_location, $multiselect_locations ) ) {
                        $output[$multiselect_option][] = sanitize_text_field( $multiselect_location );
                    }
                }
            } else {
                $output[$multiselect_option] = [];
            }
        }

        // Check if we have a valid marker effect.
        if ( in_array( $input['marker_effect'], $marker_effects ) ) {
            $output['marker_effect'] = $input['marker_effect'];
        } else {
            $output['marker_effect'] = $this->settings->get_default( 'ux', 'marker_effect' );
        }
        
        // Check if we have a valid address format.  
        if ( array_key_exists( $input['address_format'], wpsl_get_address_formats() ) ) {
            $output['address_format'] = $input['address_format'];
        } else {
            $output['address_format'] = $this->settings->get_default( 'ux', 'address_format' );
        }
        
        // Check if we need to delete transients based on UX settings changes
        $this->admin_settings->set_delete_transient_option( 'ux', $output );

        return $output;
    }

    /**
     * Sanitize the markers settings.
     * 
     * @since  3.0.0
     * @param  array $input The submitted marker values
     * @return array $output The sanitized values
     */
    public function markers( $input ) {
        $output = [];

        // The default markers. Resolve each submitted name against the files that
        // actually exist ( SVG preferred, PNG fallback ) and drop anything with an
        // unexpected extension so only known marker filenames are ever stored.
        $output['start_marker']  = $this->sanitize_marker( $input, 'start', 'red' );
        $output['store_marker']  = $this->sanitize_marker( $input, 'store', 'blue' );
        $output['active_marker'] = $this->sanitize_marker( $input, 'active', 'dark-blue' );

        $output['start_marker_on_top'] = isset( $input['start_marker_on_top'] ) ? 1 : 0;

        // Runtime marker labels: none / numbers / letters.
        $output['labels'] = \WPSL\Core\Markers\Marker_Label::sanitize_mode( isset( $input['labels'] ) ? $input['labels'] : '' );

        // cluster marker only settings
        $output['marker_clusters'] = isset( $input['clusters'] ) ? 1 : 0;

        if ( isset( $input['cluster_style'] ) && in_array( $input['cluster_style'], [ 'default', 'interpolation' ] ) ) {
            $output['cluster_style'] = $input['cluster_style'];
        } else {
            $output['cluster_style'] = 'default';
        }

        // Only accept marker shapes that exist in the shape library ( includes filter-added shapes ).
        $valid_shapes = array_merge( [ 'default' ], array_keys( wpsl_get_cluster_marker_shapes() ) );

        if ( isset( $input['cluster_marker_shape'] ) && in_array( $input['cluster_marker_shape'], $valid_shapes, true ) ) {
            $output['cluster_marker_shape'] = $input['cluster_marker_shape'];
        } else {
            $output['cluster_marker_shape'] = 'default';
        }

        $output['cluster_zoom'] = filter_var( $input['cluster_zoom'], FILTER_VALIDATE_INT ) !== false ? $input['cluster_zoom'] : '';
        $output['cluster_size'] = filter_var( $input['cluster_size'], FILTER_VALIDATE_INT ) !== false ? $input['cluster_size'] : '';

        $output['cluster_exclude_start'] = isset( $input['cluster_exclude_start'] ) ? 1 : 0;

        // Cluster marker colors
        $output['cluster_low_density_color']  = isset( $input['cluster_low_density_color'] ) ? sanitize_hex_color( $input['cluster_low_density_color'] ) : '#0000ff';
        $output['cluster_high_density_color'] = isset( $input['cluster_high_density_color'] ) ? sanitize_hex_color( $input['cluster_high_density_color'] ) : '#ff0000';
        $output['cluster_label_color']        = isset( $input['cluster_label_color'] ) ? sanitize_hex_color( $input['cluster_label_color'] ) : '#ffffff';

        return $output;
    }

    /**
     * Sanitize a single submitted marker filename.
     *
     * Keeps only the basename, rejects any extension that isn't an allowed
     * marker image type, and resolves the result against the files on disk so
     * SVG is preferred with a PNG fallback. Falls back to the default color when
     * the submitted marker can't be resolved to an existing file.
     *
     * A marker created in the Marker Manager is submitted as "custom:{id}"
     * rather than a filename and is kept as-is when the id still exists.
     *
     * @since  3.0.0
     * @param  array  $input   The submitted marker values.
     * @param  string $key     The input key to read ( start / store / active ).
     * @param  string $default The default marker color to fall back to.
     * @return string          The resolved marker filename, or a "custom:{id}" value.
     */
    private function sanitize_marker( $input, $key, $default ) {

        /**
         * A missing radio means nothing was checked (stored value was empty or
         * unknown, e.g. migrated v2 data). Store the default so a save of the
         * markers tab self-heals the setting.
         */
        if ( ! isset( $input[ $key ] ) || '' === $input[ $key ] ) {
            return wpsl_resolve_marker_filename( $default );
        }

        /**
         * Custom markers are checked before sanitize_file_name() would strip
         * the colon. A deleted marker id falls through to the default, same as
         * an unknown filename.
         */
        $custom_id = wpsl_custom_marker_id( $input[ $key ] );

        if ( $custom_id && wpsl_custom_marker_data_uri( $custom_id ) ) {
            return 'custom:' . $custom_id;
        }

        $submitted = sanitize_file_name( wp_filter_nohtml_kses( $input[ $key ] ) );
        $base      = pathinfo( $submitted, PATHINFO_FILENAME );
        $ext       = strtolower( pathinfo( $submitted, PATHINFO_EXTENSION ) );

        // Reject anything that isn't one of the allowed marker image types.
        if ( ! in_array( $ext, wpsl_marker_extensions(), true ) ) {
            $base = $default;
        }

        $resolved = wpsl_resolve_marker_filename( $base );
        $files    = wpsl_marker_files();

        // Fall back to the default when the resolved file doesn't exist on disk.
        if ( ! isset( $files[ $resolved ] ) ) {
            $resolved = wpsl_resolve_marker_filename( $default );
        }

        return $resolved;
    }

    /**
     * Sanitize editor settings.
     * 
     * @since  3.0.0
     * @param  array $input  The submitted editor values
     * @return array $output The sanitized values
     */
    public function editor( $input ) {
        $output = [];

        $output['country']         = isset( $input['default_country'] ) ? sanitize_text_field( $input['default_country'] ) : '';
        $output['enable_online_only'] = isset( $input['enable_online_only'] ) ? 1 : 0;
        $output['map_type']        = wpsl_valid_map_type( $input['map_type'] );
        $output['hide_hours']      = isset( $input['hide_hours'] ) ? 1 : 0;

        if ( isset( $input['hour_input'] ) ) {
            $output['hour_input'] = ( $input['hour_input'] == 'textarea' ) ? 'textarea' : 'dropdown';	
        } else {
            $output['hour_input'] = 'dropdown';
        }

        $output['hour_format'] = ( $input['hour_format'] == 12 ) ? 12 : 24;
        
        // The default opening hours.
        if ( isset( $input['hours']['textarea'] ) ) {
            $output['hours']['textarea'] = sanitize_textarea_field( $input['hours']['textarea'] );
        }

        // Format and validate the opening hours
        $output['hours']['dropdown'] = $this->hours->validate();
        
        $field_manager = [
            'groups' => [],
            'fields' => []
        ];

        if ( isset( $input['field_manager']['groups'] ) ) {

            /*
             * Current group names, read before the option is written, for the
             * duplicate check below.
             */
            $stored        = $this->settings->get_group( 'editor' );
            $stored_groups = $stored['field_manager']['groups'] ?? [];

            if ( ! is_array( $stored_groups ) ) {
                $stored_groups = [];
            }

            foreach ( $input['field_manager']['groups'] as $group_id => $group_name ) {
                $group_id = sanitize_text_field( $group_id );
                $group_name = mb_substr( sanitize_text_field( $group_name ), 0, 50 );

                if ( '' === trim( $group_name ) ) {
                    $this->validation_error( 'group_name_empty' );
                    continue;
                }

                /*
                 * get_custom_field_names() keys by group name, so a duplicate
                 * means one group's fields overwrite another's. Undo just the
                 * rename rather than drop the group and its fields.
                 */
                if ( in_array( $group_name, $field_manager['groups'], true ) ) {
                    $this->validation_error( 'group_name_duplicate' );

                    $group_name = isset( $stored_groups[ $group_id ] ) ? $stored_groups[ $group_id ] : '';

                    // Nothing to fall back to ( a group added in this same save ).
                    if ( '' === trim( $group_name ) || in_array( $group_name, $field_manager['groups'], true ) ) {
                        continue;
                    }
                }

                $field_manager['groups'][$group_id] = $group_name;

                if ( ! isset( $input['field_manager']['fields'][$group_id] ) ) {
                    $field_manager['fields'][ sanitize_text_field( $group_id ) ] = []; // group exists, but is empty.
                } else {
                    foreach ( $input['field_manager']['fields'][$group_id] as $field_id => $field_name ) {
                        $field_id = sanitize_text_field( $field_id );
                        $field = $input['field_manager']['fields'][$group_id][$field_id];

                        /**
                         * Only save the data when we have a label value.
                         *
                         * If no name value exists, then we create one
                         * based on the label data.
                         */
                        if ( isset( $field['label'] ) && $field['label'] ) {
                            if ( isset( $field['name'] ) && $field['name'] ) {
                                $field_name = $field['name'];
                            } else {
                                $field_name = $field['label'];
                            }

                            $field_data = [];

                            // Validate the field type against allowed types.
                            $allowed_types = [ 'text', 'textarea', 'email', 'tel', 'url', 'checkbox', 'dropdown' ];
                            $field_type = isset( $field['type'] ) ? sanitize_text_field( $field['type'] ) : 'text';

                            if ( ! in_array( $field_type, $allowed_types, true ) ) {
                                $field_type = 'text';
                            }

                            switch ( $field_type ) {
                                case 'dropdown':
                                    if ( isset( $field['options'] ) ) {
                                        $field_data['options'] = sanitize_textarea_field( $field['options'] );
                                        unset( $field['options'] );
                                    }

                                    break;
                                case 'url':
                                    if ( isset( $field['default'] ) ) {
                                        $field_data['default'] = esc_url_raw( $field['default'] );
                                        unset( $field['default'] );
                                    }

                                    break;
                                case 'email':
                                    if ( isset( $field['default'] ) ) {
                                        $field_data['default'] = sanitize_email( $field['default'] );
                                        unset( $field['default'] );
                                    }

                                    break;
                            }

                            $sanitized_fields = array_map( 'sanitize_text_field', $field );

                            // Limit the field label to 50 characters.
                            if ( isset( $sanitized_fields['label'] ) ) {
                                $sanitized_fields['label'] = mb_substr( $sanitized_fields['label'], 0, 50 );
                            }

                            $field_data = array_merge( $sanitized_fields, $field_data );

                            // Remove any illegal characters
                            $field_data['name'] = strtolower( wpsl_alphanum_no_space( $field_name ) );

                            // Field names are used as JS variable names in Underscore.js templates,
                            // so they can't start with a digit
                            if ( preg_match( '/^[0-9]/', $field_data['name'] ) ) {
                                $field_data['name'] = 'field_' . $field_data['name'];
                            }

                            $field_manager['fields'][$group_id][$field_id] = $field_data;
                        }
                    }
                }
            }
        }

        $output['field_manager'] = $field_manager;

        // Check if we need to delete transients based on editor settings changes
        $this->admin_settings->set_delete_transient_option( 'editor', $output );

        return $output;
    }

    /**
     * Sanitize the appearance settings.
     * 
     * @since  3.0.0
     * @param  array $input  The submitted appearance values
     * @return array $output The sanitized values
     */
    public function appearance( $input ) {
        $output = [];

        // Make sure we have a valid template ID.
        if ( isset( $input['template_id'] ) && ( $input['template_id'] ) ) {
            $output['template_id'] = sanitize_text_field( $input['template_id'] );
        } else {
            $output['template_id'] = $this->settings->get_default( 'appearance', 'template_id' );
        }

        $output['overwrite_theme_styles'] = isset( $input['overwrite_theme_styles'] ) ? 1 : 0;

        // Custom theme color / styles
        if ( isset( $input['theme_colors'] ) ) {
            $theme_colors = array_map( 'sanitize_text_field', $input['theme_colors'] );

            foreach ( $theme_colors as $color_key => $color_code ) {

                // Handle angle values separately (they're not hex colors)
                if ( strpos( $color_key, '_angle' ) !== false ) {
                    $output['theme_colors'][$color_key] = absint( $color_code );
                } else {
                    $output['theme_colors'][$color_key] = sanitize_hex_color( $color_code );
                }
            }
        }

        // Button colors (separate from theme_colors)
        if ( isset( $input['button_colors'] ) ) {
            $button_colors = array_map( 'sanitize_text_field', $input['button_colors'] );

            foreach ( $button_colors as $color_key => $color_code ) {
                // Handle angle values separately (they're not hex colors)
                if ( strpos( $color_key, '_angle' ) !== false ) {
                    $output['button_colors'][$color_key] = absint( $color_code );
                } else {
                    $output['button_colors'][$color_key] = sanitize_hex_color( $color_code );
                }
            }
        }

        // Map styles - the shared MapLibre style JSON URL ( osm / stadia ).
        $maplibre_url = null;

        if ( isset( $input['map']['style']['maplibre'] ) ) {
            $maplibre_input = $input['map']['style']['maplibre'];
            $maplibre_url   = isset( $maplibre_input['custom_url'] ) ? esc_url_raw( trim( $maplibre_input['custom_url'] ) ) : '';

            // Only https URLs are allowed for custom styles.
            if ( 0 !== strpos( $maplibre_url, 'https://' ) ) {
                $maplibre_url = '';
            }

            $output['map_style']['maplibre'] = [
                'custom_url' => $maplibre_url,
                // An unchecked checkbox is absent from the POST payload.
                'enabled'    => ! empty( $maplibre_input['enabled'] ) ? 1 : 0,
            ];
        }

        // Map styles - OSM
        if ( isset( $input['map']['style']['osm'] ) ) {
            $osm_style   = $input['map']['style']['osm'];
            $tile_source = isset( $osm_style['tile_source'] ) ? sanitize_text_field( $osm_style['tile_source'] ) : 'default';

            if ( ! in_array( $tile_source, [ 'default', 'mapbox', 'stadia', 'openfreemap', 'maplibre' ], true ) ) {
                $tile_source = 'default';
            }

            // A maplibre tile source with no valid URL cannot resolve; revert.
            if ( 'maplibre' === $tile_source && ! $maplibre_url ) {
                $tile_source = 'default';
            }

            $output['map_style']['osm']['tile_source'] = $tile_source;

            // Keep legacy fields in sync so older code paths still work.
            $output['map_style']['osm']['overwrite_styles'] = ( 'mapbox' === $tile_source ) ? 1 : 0;
            $output['map_style']['osm']['selected']         = ( 'mapbox' === $tile_source ) ? 'mapbox' : 'default';

            if ( 'stadia' === $tile_source ) {
                $styles         = wpsl_stadia_styles();
                $selected_style = isset( $osm_style['selected_style'] ) ? sanitize_text_field( $osm_style['selected_style'] ) : 'alidade_smooth';

                if ( array_key_exists( $selected_style, $styles ) ) {
                    $output['map_style']['stadia']['selected_style'] = $selected_style;
                } else {
                    $output['map_style']['stadia']['selected_style'] = 'alidade_smooth';
                }
            } else {
                $output['map_style']['osm']['selected_style'] = '';
            }

            if ( 'openfreemap' === $tile_source ) {
                $of_input   = isset( $input['map']['style']['openfreemap'] ) ? $input['map']['style']['openfreemap'] : [];
                $presets    = wpsl_openfreemap_styles();
                $selected   = isset( $of_input['selected'] ) ? sanitize_text_field( $of_input['selected'] ) : 'liberty';
                $custom_url = isset( $of_input['custom_url'] ) ? esc_url_raw( trim( $of_input['custom_url'] ) ) : '';

                // Only https URLs are allowed for custom styles.
                if ( 0 !== strpos( $custom_url, 'https://' ) ) {
                    $custom_url = '';
                }

                if ( 'custom' === $selected ) {
                    // A custom selection with no valid URL is meaningless; revert.
                    if ( '' === $custom_url ) {
                        $selected = 'liberty';
                    }
                } elseif ( ! array_key_exists( $selected, $presets ) ) {
                    $selected = 'liberty';
                }

                $output['map_style']['openfreemap'] = [
                    'selected'   => $selected,
                    'custom_url' => $custom_url,
                ];
            }
        }

        // Map styles - Stadia (when Stadia is the active map service)
        if ( isset( $input['map']['style']['stadia'] ) ) {
            $stadia_style   = $input['map']['style']['stadia'];
            $styles         = wpsl_stadia_styles();
            $selected_style = isset( $stadia_style['selected_style'] ) ? sanitize_text_field( $stadia_style['selected_style'] ) : 'alidade_smooth';

            if ( array_key_exists( $selected_style, $styles ) ) {
                $output['map_style']['stadia']['selected_style'] = $selected_style;
            } else {
                $output['map_style']['stadia']['selected_style'] = 'alidade_smooth';
            }

            // Style source: the preset raster styles, or the custom MapLibre style URL.
            $style_source = isset( $stadia_style['style_source'] ) ? sanitize_text_field( $stadia_style['style_source'] ) : 'stadia';

            if ( ! in_array( $style_source, [ 'stadia', 'maplibre' ], true ) ) {
                $style_source = 'stadia';
            }

            // A maplibre style source with no valid URL cannot resolve; revert.
            if ( 'maplibre' === $style_source && ! $maplibre_url ) {
                $style_source = 'stadia';
            }

            $output['map_style']['stadia']['style_source'] = $style_source;
        }

        // Google Maps style section
        if ( isset( $input['map']['style']['gmaps'] ) || isset( $input['map']['gmaps_selected_style'] ) ) {
            $gmaps_style = isset( $input['map']['style']['gmaps'] ) ? $input['map']['style']['gmaps'] : [];

            // Get existing values from database to preserve hidden fields
            $existing_map_style = $this->settings->get( 'appearance', 'map_style' );
            $existing_gmaps = isset( $existing_map_style['gmaps'] ) ? $existing_map_style['gmaps'] : [];

            // Use submitted dropdown value for selected, fallback to existing
            if ( isset( $input['map']['gmaps_selected_style'] ) && in_array( $input['map']['gmaps_selected_style'], [ 'cloud_based', 'json' ], true ) ) {
                $selected = sanitize_text_field( $input['map']['gmaps_selected_style'] );
            } else {
                $selected = isset( $existing_gmaps['selected'] ) ? $existing_gmaps['selected'] : 'cloud_based';
            }

            // For cloud_based: use submitted value if present, otherwise preserve existing
            if ( isset( $gmaps_style['cloud_based'] ) ) {
                $cloud_based = sanitize_text_field( $gmaps_style['cloud_based'] );
            } else {
                $cloud_based = isset( $existing_gmaps['cloud_based'] ) ? $existing_gmaps['cloud_based'] : '';
            }

            // For json: use submitted value if present, otherwise preserve existing
            if ( isset( $gmaps_style['json'] ) ) {
                $json_style = trim( $gmaps_style['json'] ) ? json_encode( wp_strip_all_tags( trim( $gmaps_style['json'] ) ) ) : '';
            } else {
                $json_style = isset( $existing_gmaps['json'] ) ? $existing_gmaps['json'] : '';
            }

            $output['map_style']['gmaps']['selected']    = $selected;
            $output['map_style']['gmaps']['cloud_based'] = $cloud_based;
            $output['map_style']['gmaps']['json']        = $json_style;
        }

        // Mapbox style section
        if ( isset( $input['map']['style']['mapbox'] ) ) {
            $mapbox_style = $input['map']['style']['mapbox'];
            
            if ( isset( $mapbox_style['selected'] ) && $mapbox_style['selected'] == 'custom' && isset( $mapbox_style['custom_url'] ) && $this->is_mapbox_style_url( $mapbox_style['custom_url'] ) ) {
                $output['map_style']['mapbox'] = [
                    'selected'   => 'custom',
                    'url'        => '',
                    'custom_url' => sanitize_text_field( $mapbox_style['custom_url'] )
                ];
            } else {
                $styles = wpsl_mapbox_classic_styles();
                $selected = isset( $mapbox_style['selected'] ) ? sanitize_text_field( $mapbox_style['selected'] ) : '';

                /**
                 * Make sure we have a valid Mapbox style.
                 * If not, use the default one.
                 */
                if ( $selected && array_key_exists( $selected, $styles ) ) {
                    $output['map_style']['mapbox'] = [
                        'selected'   => $selected,
                        'url'        => $styles[ $selected ],
                        'custom_url' => ( isset( $mapbox_style['custom_url'] ) && $this->is_mapbox_style_url( $mapbox_style['custom_url'] ) ) ? sanitize_text_field( $mapbox_style['custom_url'] ) : ''
                    ];
                } else {
                    // Auto-select first style (streets) if OSM is set to use Mapbox tiles.
                    $is_mapbox_tile_source = ( isset( $output['map_style']['osm']['tile_source'] ) && 'mapbox' === $output['map_style']['osm']['tile_source'] ) || ( isset( $output['map_style']['osm']['overwrite_styles'] ) && $output['map_style']['osm']['overwrite_styles'] );
                    $default_style = $is_mapbox_tile_source ? 'streets' : 'standard';
                    $default_url = $is_mapbox_tile_source ? 'mapbox://styles/mapbox/streets-v12' : 'mapbox://styles/mapbox/standard';
                    
                    $output['map_style']['mapbox'] = [
                        'selected'   => $default_style,
                        'url'        => $default_url,
                        'custom_url' => ( isset( $mapbox_style['custom_url'] ) && $this->is_mapbox_style_url( $mapbox_style['custom_url'] ) ) ? sanitize_text_field( $mapbox_style['custom_url'] ) : ''
                    ];
                }
            }
        }

        /**
         * The map style tab only posts the active provider's fields, so the
         * sanitized map_style is merged over the stored one rather than
         * replacing it (which would wipe other providers' config on every save).
         */
        if ( isset( $output['map_style'] ) ) {
            $existing_map_style = $this->settings->get( 'appearance', 'map_style' );

            if ( is_array( $existing_map_style ) ) {
                $output['map_style'] = array_replace_recursive( $existing_map_style, $output['map_style'] );
            }
        }

        // Preloader color: 'black', 'white' or 'custom' ( custom stores a hex color ).
        $preloader_color = isset( $input['search']['preloader_color'] ) ? sanitize_text_field( $input['search']['preloader_color'] ) : 'black';
        $output['preloader_color'] = in_array( $preloader_color, [ 'black', 'white', 'custom' ], true ) ? $preloader_color : 'black';

        $preloader_custom_color = isset( $input['search']['preloader_custom_color'] ) ? sanitize_hex_color( $input['search']['preloader_custom_color'] ) : '';
        $output['preloader_custom_color'] = $preloader_custom_color ? $preloader_custom_color : '';

        // Drop the retired boolean so the back-compat branch in wpsl_get_preloader_color() stops firing.
        unset( $output['white_preloader'] );

        // Ensure result_columns is a number and only allows values 1, 2, or 3
        $result_columns = isset( $input['search']['result_columns'] ) ? absint( $input['search']['result_columns'] ) : 1;
        $output['result_columns'] = in_array( $result_columns, [ 1, 2, 3 ] ) ? $result_columns : 1;

        // How the result filters are laid out (vertical template only):
        // horizontal row, stacked full-width, or nested under a single button.
        $filter_layout = isset( $input['search']['filter_layout'] ) ? sanitize_text_field( $input['search']['filter_layout'] ) : 'horizontal';
        $output['filter_layout'] = in_array( $filter_layout, [ 'horizontal', 'stacked', 'nested' ], true ) ? $filter_layout : 'horizontal';

        // Icon settings in nested array
        $output['icons'] = [];
        $output['icons']['enabled'] = isset( $input['icons']['enabled'] ) ? 1 : 0;
        
        $address_icon = isset( $input['icons']['address'] ) ? sanitize_text_field( $input['icons']['address'] ) : '';
        $output['icons']['address'] = in_array( $address_icon, [ 'marker', 'house', 'building', 'building-outline' ], true ) ? $address_icon : 'marker';

        $phone_icon = isset( $input['icons']['phone'] ) ? sanitize_text_field( $input['icons']['phone'] ) : '';
        $output['icons']['phone'] = in_array( $phone_icon, [ 'phone', 'mobile-phone' ], true ) ? $phone_icon : 'phone';

        $email_icon = isset( $input['icons']['email'] ) ? sanitize_text_field( $input['icons']['email'] ) : '';
        $output['icons']['email'] = in_array( $email_icon, [ 'email', 'email-outline' ], true ) ? $email_icon : 'email';
        
        // CTA settings - nested structure like icons
        $output['cta']['enabled'] = isset( $input['cta']['enabled'] ) ? 1 : 0;
        $output['cta']['details'] = isset( $input['cta']['details'] ) ? 1 : 0;

        if ( isset( $input['cta']['details_target'] ) && in_array( $input['cta']['details_target'], [ 'website', 'landing_page' ] ) ) {
            $output['cta']['details_target'] = sanitize_text_field( $input['cta']['details_target'] );
        } else {
            $output['cta']['details_target'] = 'website';
        }

        // Button styles - sanitize button style selections (primary/secondary)
        if ( isset( $input['button_styles'] ) && is_array( $input['button_styles'] ) ) {
            $valid_actions = [ 'more_details', 'directions', 'zoom_here', 'share_location', 'no_thanks', 'streetview' ];
            $valid_styles = [ 'primary', 'secondary' ];
            
            foreach ( $input['button_styles'] as $action => $style ) {
                if ( in_array( $action, $valid_actions, true ) && in_array( $style, $valid_styles, true ) ) {
                    $output['button_styles'][$action] = sanitize_text_field( $style );
                }
            }
        }

        // Sanitize accessibility settings
        $output['custom_focus_outline'] = isset( $input['accessibility']['custom_focus_outline'] ) ? 1 : 0;
        $output['focus_outline'] = isset( $input['accessibility']['focus_outline'] ) ? sanitize_hex_color( $input['accessibility']['focus_outline'] ) : '';

        // Sanitize dimensions - template-namespaced structure
        $output['dimensions']['horizontal'] = $this->sanitize_template_dimensions( $input, 'horizontal', [
            'map_height_mode'     => null,
            'map_height'          => 350,
            'results_height_mode' => null,
            'results_height'      => 350
        ] );
        
        $output['dimensions']['vertical'] = $this->sanitize_template_dimensions( $input, 'vertical', [
            'sl_height_mode' => null,
            'sl_height'      => 450
        ] );
        
        $output['dimensions']['default'] = $this->sanitize_template_dimensions( $input, 'default', [
            'map_height_mode' => null,
            'map_height'      => 350
        ] );
        
        // Shared dimensions (not template-specific)
        $output['dimensions']['search_width_mode'] = isset( $input['dimensions']['search_width_mode'] ) && in_array( $input['dimensions']['search_width_mode'], [ 'default', 'custom' ] ) 
            ? sanitize_text_field( $input['dimensions']['search_width_mode'] ) 
            : 'custom';

        $output['dimensions']['search_width'] = isset( $input['dimensions']['search_width'] ) && absint( $input['dimensions']['search_width'] )
            ? absint( $input['dimensions']['search_width'] )
            : 179;

        // Sanitize font sizes
        $output['font_sizes']['overwrite_defaults'] = isset( $input['font_sizes']['overwrite_defaults'] ) ? 1 : 0;
        
        // Sanitize font size values (numeric only, stored without unit)
        $font_size_fields = [ 'base', 'location_name', 'cta_buttons' ];
        
        foreach ( $font_size_fields as $field ) {
            if ( isset( $input['font_sizes'][$field] ) ) {
                $value = absint( $input['font_sizes'][$field] );
                
                // Enforce min (12) and max (20) limits
                if ( $value >= 12 && $value <= 20 ) {
                    $output['font_sizes'][$field] = $value;
                } else {
                    $output['font_sizes'][$field] = 14;
                }
            } else {
                $output['font_sizes'][$field] = 14;
            }
        }

        return $output;
    }

    /**
     * Sanitize the permalinks settings.
     * 
     * @since  3.0.0
     * @param  array $input The submitted permalink values
     * @return array $output The sanitized values
     */
    public function local_pages( $input ) {
        $output = [];

        $output['permalinks']             = isset( $input['active'] ) ? 1 : 0;
        $output['permalink_remove_front'] = isset( $input['remove_front'] ) ? 1 : 0;

        if ( ! empty( $input['slug'] ) ) {
            $output['permalink_slug'] = sanitize_text_field( $input['slug'] );
        } else {
            $output['permalink_slug'] = $this->settings->get_default( 'local_pages', 'permalink_slug' );
        }
        
        if ( ! empty( $input['category_slug'] ) ) {
            $output['category_slug'] = sanitize_text_field( $input['category_slug'] );
        } else {
            $output['category_slug'] = $this->settings->get_default( 'local_pages', 'category_slug' );
        }

        // Check if we need to flush the rewrite rules based on the new settings
        $this->admin_settings->set_flush_rewrite_option( $output );

        return $output;
    }

    /**
     * Sanitize the labels.
     *
     * @since  3.0.0
     * @param  array $input The submitted label values
     * @return array $output The sanitized values
     */
    public function labels( $input ) {
        $output = [];
        $required_labels = wpsl_labels();

        foreach ( $required_labels as $label ) {
            $output[$label . '_label'] = isset( $input[$label] ) ? sanitize_text_field( $input[$label] ) : '';
        }

        $visibility_keys = [ 'search', 'search_name', 'radius', 'results', 'category' ];

        $output['visibility'] = [];

        foreach ( $visibility_keys as $key ) {
            $output['visibility'][ $key ] = isset( $input['visibility'][ $key ] );
        }

        return $output;
    }

    /**
     * Sanitize the GDPR settings.
     * 
     * @since  3.0.0
     * @param  array $input The GDPR settings
     * @return array $output The sanitized values
     */
    public function gdpr( $input ) {
        $output = $this->settings->get_group( 'gdpr' );

        if ( isset( $input['handler'] ) && in_array( $input['handler'], [ 'none', 'wpsl', 'borlabs', 'complianz' ] ) ) {
            $output['handler'] = sanitize_text_field( $input['handler'] );
        } elseif ( ! isset( $output['handler'] ) ) {
            $output['handler'] = 'none';
        }

        if ( isset( $input['description'] ) ) {
            $output['description'] = wp_kses_post( trim( stripslashes( $input['description'] ) ) );
        }
        
        return $output;
    }

    /**
     * Sanitize the tools settings.
     * 
     * @since  3.0.0
     * @param  array $input The submitted tools values
     * @return array $output The sanitized values
     */
    public function tools( $input ) {
        $output = [];

        $output['debug'] = isset( $input['debug'] ) ? 1 : 0;
        $output['deregister_gmaps'] = isset( $input['deregister_gmaps'] ) ? 1 : 0;
        $output['disable_v3_css'] = isset( $input['disable_v3_css'] ) ? 1 : 0;

        // Check if we need to delete transients based on tools settings changes
        $this->admin_settings->set_delete_transient_option( 'tools', $output );

        return $output;
    }

    /**
     * Check if a URL is a valid Mapbox style URL.
     * 
     * @since  3.0.0
     * @param  string $url The URL to check
     * @return bool True if valid Mapbox style URL, false otherwise
     */
    public function is_mapbox_style_url( $url ) {
        return ( substr( $url,0, 16 ) === 'mapbox://styles/' ) ? true : false;
    }

    /**
     * Handle validation errors - delegates to the admin settings manager.
     *
     * @since 3.0.0
     * @param string $error_type Contains the type of validation error that occurred
     * @return void
     */
    public function validation_error( $error_type ) {
        $this->admin_settings->validation_error( $error_type );
    }

    /**
     * Get the validate_keys instance for external validation
     * 
     * @since 3.0.0
     * @return Validate_Keys The validate_keys instance
     */
    public function get_validate_keys() {
        return $this->validate_keys;
    }
}