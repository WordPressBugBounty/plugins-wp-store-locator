<?php
/**
 * Handles configuration for the plugin.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Manager {

    /**
     * Cache for options to prevent multiple database requests.
     *
     * @var array
     */
    private $cache = [];

    /**
     * Whether the non-autoloaded groups were already primed this request.
     *
     * @since 3.0.0
     * @var   bool
     */
    private $primed = false;

    /**
     * Flag to indicate if all options have been loaded.
     *
     * @var bool
     */
    private $all_options_loaded = false;

    /**
     * Set while the plugin writes settings itself ( defaults, set() ).
     * The form sanitize callback skips processing when this is true,
     * because it treats input as raw POST data ( a checkbox key being
     * present means "on" ), which would corrupt already-sanitized arrays.
     *
     * @var bool
     */
    private $programmatic_write = false;

    /**
     * Option groups handled by this class.
     *
     * @var array
     */
    protected $groups = [
        'api',
        'search',
        'map',
        'ux',
        'markers',
        'editor',
        'appearance',
        'local_pages',
        'labels',
        'gdpr',
        'tools',
    ];

    /**
     * Groups read on every request, whether or not a locator renders.
     *
     * Post type registration (local_pages) and map service resolution (api) run
     * on every page load, so they use WordPress' alloptions query. The rest
     * are only read on a locator render or through the v2 shim (see
     * prime_groups()), and wpsl_appearance alone is ~5 KB, growing with custom
     * sections.
     *
     * @since 3.0.0
     * @var   array
     */
    protected $autoloaded_groups = [ 'api', 'local_pages' ];

    /**
     * Whether a group's option is autoloaded.
     *
     * @since  3.0.0
     * @param  string $group The option group name, without the wpsl_ prefix
     * @return bool
     */
    public function is_autoloaded_group( $group ) {
        return in_array( $group, $this->autoloaded_groups, true );
    }

    /**
     * Every settings group this class owns.
     *
     * @since  3.0.0
     * @return string[] Group names, without the wpsl_ prefix
     */
    public function get_groups() {
        return $this->groups;
    }

    /**
     * Get settings for a specific group.
     *
     * @since  3.0.0
     * @param  string $group The option group to retrieve
     * @return array  The settings for the group or empty array if group doesn't exist
     */
    public function get_group( $group ) {
        if ( ! in_array( $group, $this->groups, true ) ) {
            return [];
        }

        // Check if the group is already cached
        if ( isset( $this->cache[$group] ) ) {
            return $this->cache[$group];
        }

        $this->prime_groups();

        $defaults = $this->defaults( $group );

        $option_name = 'wpsl_' . $group;

        $options = get_option( $option_name, [] );

        if ( ! $options && 'local_pages' === $group ) {
            $options = $this->adopt_legacy_local_seo();
        }

        // Use array_replace_recursive for deep merge to handle nested arrays properly
        $settings = array_replace_recursive( $defaults, $options );

        // Automatically disable the radius dropdown and nearest location fallback in previews/templates when Name Search is active
        if ( $group === 'search' && isset( $settings['search_method'] ) && $settings['search_method'] === 'name' ) {
            $settings['radius_dropdown'] = 0;
            $settings['find_nearest_location'] = 0;
        }

        $this->cache[$group] = $settings;

        return $this->cache[$group];
    }

    /**
     * Move a pre-rename wpsl_local_seo option onto wpsl_local_pages.
     *
     * @since  3.0.0
     * @return array The adopted settings, or an empty array when there is
     *               nothing to adopt.
     */
    private function adopt_legacy_local_seo() {
        $legacy = get_option( 'wpsl_local_seo' );

        if ( ! is_array( $legacy ) || ! $legacy ) {
            return [];
        }

        update_option( 'wpsl_local_pages', $legacy, $this->is_autoloaded_group( 'local_pages' ) );
        delete_option( 'wpsl_local_seo' );

        return $legacy;
    }

    /**
     * Fetch the settings groups in one query, once per request.
     *
     * @since  3.0.0
     * @return void
     */
    private function prime_groups() {
        if ( $this->primed ) {
            return;
        }

        $this->primed = true;

        if ( ! function_exists( 'wp_prime_option_caches' ) ) {
            return;
        }

        $names = [];

        foreach ( $this->groups as $group ) {
            $names[] = 'wpsl_' . $group;
        }

        /*
         * Not a settings group, but Manager::defaults() reads it through
         * wpsl_default_opening_hours() on every request. Free when it is
         * autoloaded - wp_prime_option_caches() skips what is already cached -
         * and it covers the window before the autoload migration has run.
         */
        $names[] = 'wpsl_version';

        wp_prime_option_caches( $names );
    }

    /**
     * Get all settings.
     *
     * @since  3.0.0
     * @return array All plugin options from all groups
     */
    public function get_all() {
        // Return cached version if available
        if ( $this->all_options_loaded ) {
            return $this->cache;
        }

        $all_options = [];
        
        foreach ( $this->groups as $group ) {
            $all_options[$group] = $this->get_group( $group );
        }
        
        // Cache the complete set of options
        $this->cache = $all_options;
        $this->all_options_loaded = true;
        
        return $all_options;
    }

    /**
     * Update an option group.
     *
     * Used by migrations and the Field Manager to write already-sanitized
     * settings directly, bypassing the form sanitize callback through
     * programmatic_write. Autoload follows $autoloaded_groups. The cache is
     * invalidated rather than overwritten because get_group() merges saved
     * values over defaults, so caching raw $values would drop omitted keys.
     *
     * @since 3.0.0
     * @param string $group  The option group name.
     * @param array  $values The settings values to write.
     * @return bool|false True on success, false on failure or invalid group.
     */
    public function update( $group, $values ) {
        if ( ! in_array( $group, $this->groups, true ) ) {
            return false;
        }

        $this->programmatic_write = true;
        $result = update_option( 'wpsl_' . $group, $values, $this->is_autoloaded_group( $group ) );
        $this->programmatic_write = false;

        /*
         * update_option() returns false both on failure and when the stored
         * value already equals $values, so confirm against the database to
         * avoid migrate() reporting a failed migration for groups that already
         * hold the values being written.
         */
        if ( ! $result && get_option( 'wpsl_' . $group ) === $values ) {
            $result = true;
        }

        if ( $result ) {
            $this->clear_cache( $group );
        }

        return $result;
    }

    /**
     * Get a specific setting value
     * 
     * @since  3.0.0
     * @param  string       $section The settings section
     * @param  string|array $key     The setting key to retrieve (can use dot notation for nested values or array path)
     * @param  mixed        $default Default value if key not found
     * @return mixed The setting value or default
     */
    public function get( $section, $key, $default = null ) {
        $settings = $this->get_group( $section );
        
        if ( empty( $settings ) ) {
            return $default;
        }
        
        // Normalize key to array form
        if ( ! is_array( $key ) ) {
            $key = explode( '.', $key );
        }
        
        $current = $settings;

        foreach ( $key as $k ) {
            if ( ! isset( $current[$k] ) ) {
                return $default;
            }
            
            $current = $current[$k];
        }
        
        return $current;
    }
    
    /**
     * Get a default setting value from the configuration (ignores saved values)
     * 
     * @since  3.0.0
     * @param  string       $section The settings section
     * @param  string|array $key     The setting key to retrieve (can use array path)
     * @param  mixed        $default Default value if key not found in defaults
     * @return mixed The default setting value or fallback default
     */
    public function get_default( $section, $key, $default = null ) {
        $defaults = $this->defaults( $section );
        
        if ( empty( $defaults ) ) {
            return $default;
        }
        
        // Handle array path
        if ( is_array( $key ) ) {
            $current = $defaults;
            
            foreach ( $key as $k ) {
                if ( ! isset( $current[$k] ) ) {
                    return $default;
                }
                
                $current = $current[$k];
            }
            
            return $current;
        }
        
        // Simple key
        return isset( $defaults[$key] ) ? $defaults[$key] : $default;
    }

    /**
     * Set a specific setting value
     * 
     * @since  3.0.0
     * @param  string $section The settings section
     * @param  string $key     The setting key to update
     * @param  mixed  $value   The new value to set
     * @return bool   True if the setting was updated, false otherwise
     */
    public function set( $section, $key, $value ) {
        if ( ! in_array( $section, $this->groups, true ) ) {
            return false;
        }

        $settings = $this->get_group( $section );

        // Apply appropriate sanitization based on the setting type
        $settings[$key] = $this->sanitize_setting( $section, $key, $value );

        return $this->update( $section, $settings );
    }

    /**
     * Clear the cache for a specific group or all groups.
     *
     * @since 3.0.0
     * @param string|null $group Optional group to clear, or null to clear all
     */
    public function clear_cache( $group = null ) {
        if ( $group === null ) {
            $this->cache = [];
            $this->all_options_loaded = false;
        } elseif ( isset( $this->cache[$group] ) ) {
            unset( $this->cache[$group] );
            
            $this->all_options_loaded = false;
        }
    }
    
    /**
     * Sanitize a setting value based on its type
     * 
     * @since  3.0.0
     * @param  string $section The settings section
     * @param  string $key     The setting key
     * @param  mixed  $value   The value to sanitize
     * @return mixed  The sanitized value
     */
    private function sanitize_setting( $section, $key, $value ) {
        // Special case handling for specific settings
        if ( $section === 'api' ) {
            if ( in_array( $key, [ 'active_map_service', 'gmaps_browser_key', 'gmaps_server_key', 'openrouteservice_key', 'mapbox_access_token' ] ) ) {
                return sanitize_text_field( $value );
            }
        } elseif ( $section === 'map' ) {
            if ( in_array( $key, [ 'start_name', 'start_latlng' ] ) ) {
                return sanitize_text_field( $value );
            }
        }
        
        // Default sanitization based on value type
        if ( is_string( $value ) ) {
            return sanitize_text_field( $value );
        } elseif ( is_numeric( $value ) ) {
            return is_int( $value ) ? (int) $value : (float) $value;
        } elseif ( is_bool( $value ) ) {
            return (bool) $value;
        } elseif ( is_array( $value ) ) {
            return array_map( 'sanitize_text_field', $value );
        }
        
        return $value;
    }

    /**
     * Set all default options.
     * 
     * @since 3.0.0
     */
    public function set_defaults() {
        foreach ( $this->groups as $group ) {
            $option_name = 'wpsl_' . $group;

            if ( get_option( $option_name ) === false ) {
                $this->programmatic_write = true;
                update_option( $option_name, $this->defaults( $group ), $this->is_autoloaded_group( $group ) );
                $this->programmatic_write = false;
            }
        }
    }

    /**
     * Whether this handler is currently writing a settings-shaped array
     * itself. Used by the form sanitize callback to let such writes pass
     * through untouched instead of interpreting them as raw POST data.
     *
     * @since  3.0.0
     * @return bool
     */
    public function doing_programmatic_write() {
        return $this->programmatic_write;
    }

    /**
     * Return default values for a group.
     * 
     * @since 3.0.0
     * @param string $group The settings group
     * @return array The default values for the group
     */
    public function defaults( $group ) {
        switch ( $group ) {
            case 'api':
                return [
                    'active_map_service'        => 'osm',
                    'map_services'              => wpsl_get_map_services(),
                    'gmaps_browser_key'         => '',
                    'gmaps_server_key'          => '',
                    'gmaps_language'            => 'en',
                    'gmaps_region'              => '',
                    'region_restriction_type'   => 'bias', // 'bias' ( soft, Google only ) or 'restrict' ( hard )
                    'openrouteservice_key'      => '',
                    'openrouteservice_language' => '',
                    'osm_language'              => '',
                    'mapbox_key'                => '',
                    'mapbox_language'           => '',
                    'mapbox_geocoder'           => 'nominatim',
                    'stadia_key'                => '',
                    'stadia_language'           => '',
                    'stadia_eu_endpoints'       => '',
                    'versions'                  => [ 
                        'gmaps' => [ 
                            'autocomplete' => 'latest' 
                        ]
                    ],
                    'multiple_regions' => '',
                ];
            case 'search':
                return [
                    'search_method'                   => 'geocode',
                    'input_placeholder'               => '',
                    'auto_locate'                     => true,
                    'auto_locate_format'              => 'zip',
                    'auto_locate_trigger'             => 'pageload',
                    'autocomplete'                    => false,
                    'api_versions'                    => [
                        'gmaps' => [
                            'autocomplete' => 'latest'
                        ]
                    ],
                    'autosubmit_autocomplete'         => false,
                    'input_only'                      => false,
                    'force_postalcode'                => false,
                    'distance_unit'                   => 'km',
                    'max_results'                     => '[25],50,75,100',
                    'search_radius'                   => '10,25,[50],100,200,500',
                    'orderby'                         => 'distance',
                    'order'                           => 'ASC',
                    'enforce_borders'                 => false,
                    'full_search'                     => false,
                    'number_results'                  => false,
                    'find_nearest_location'           => false,
                    'radius_dropdown'                 => true,
                    'results_dropdown'                => true,
                    'category_filter'                 => false,
                    'category_default'                => '',
                    'all_categories_required'         => false,
                    'category_filter_only'            => false,
                    'category_filter_type'            => 'dropdown',
                ];
            case 'map':
                return [
                    'start_name'            => '',
                    'start_latlng'          => '',
                    'autoload'              => true,
                    'autoload_start_latlng' => '',
                    'autoload_limit'        => 50,
                    'run_fitbounds'         => true,
                    'zoom_level'            => 3,
                    'auto_zoom_level'       => 15,
                    'streetview'            => false,
                    'type_control'          => false,
                    'scrollwheel'           => true,
                    'control_position'      => 'left',
                    'type'              => 'roadmap',
                    'show_credits'          => false,
                ];
            case 'ux':
                return [
                    'new_window'                => false,
                    'reset_map'                 => false,
                    'listing_below_no_scroll'   => false,
                    'direction_redirect'        => false,
                    // 'more_info'                 => false,
                    'store_url'                 => false,
                    'phone_url'                 => false,
                    'marker_streetview'         => false,
                    'marker_zoom_to'            => false,
                    'keyboard_focus_min_zoom'   => 7,
                    'mouse_focus'               => false,
                    'show_contact_details'      => false,
                    'clickable_contact_details' => false,
                    'hide_distance'             => false,
                    'hide_country'              => false,
                    // 'show_post_content'         => false,
                    // 'show_hours'                => false,
                    'hide_closed_hours'         => false,
                    'show_hour_status'          => false,
                    'expand_hours'              => false,
                    'address_event'             => false,
                    'marker_effect'             => 'bounce',
                    'address_format'            => 'city_state_zip',
                    //'more_info_location'        => 'info window',
                    // Show contact details and hours inline below the address by
                    // default ( Google-Maps-style ). Post content is off by default.
                    'contact_details'           => [ 'search_results' ],
                    'hours'                     => [ 'search_results' ],
                    'description'               => [],
                ];
            case 'markers':
                return [
                    'start_marker'             => 'red.svg',
                    'store_marker'             => 'blue.svg',
                    'active_marker'            => 'dark-blue.svg',
                    'start_marker_on_top'      => false,
                    'labels'                   => 'none',
                    'marker_clusters'          => false,
                    'cluster_style'            => 'default',
                    'cluster_marker_shape'     => 'default',
                    'cluster_zoom'             => false,
                    'cluster_size'             => false,
                    'cluster_exclude_start'    => false,
                    'cluster_low_density_color'  => '#0000ff',
                    'cluster_high_density_color' => '#ff0000',
                    'cluster_label_color'        => '#ffffff',
                ];
            case 'editor':
                return [
                    'country'            => '',
                    'enable_online_only' => false,
                    'map_type'           => 'roadmap',
                    'hide_hours'         => false,
                    'hours'              => wpsl_default_opening_hours(),
                    'hour_input'         => 'dropdown',
                    'hour_format'        => 12,
                    'field_manager'      => [
                        'groups' => [],
                        'fields' => []
                    ],
                ];
            case 'appearance':
                return [
                    'template_id' => 'default',
                    'active_custom_sections' => [],
                    'dimensions' => [
                        'sl_height'      => 450,
                        'map_height'     => 350,
                        'results_height' => 350,
                        'search_width'   => 179,
                        'label_width'    => 95,
                    ],
                    'theme_colors' => [
                        'header_container_background'               => '',
                        'header_container_text'                     => '',
                        'header_input_background'                   => '',
                        'header_input_background_hover'             => '',
                        'header_input_text'                         => '',
                        'header_input_text_hover'                   => '',
                        'header_input_border'                       => '',
                        'header_input_border_hover'                 => '',
                        'header_reset_background'                   => '',
                        'header_reset_background_hover'             => '',
                        'header_reset_icon'                         => '',
                        'header_reset_icon_hover'                   => '',
                        'header_reset_border'                       => '',
                        'header_reset_border_hover'                 => '',
                        'header_dropdown_background'                => '',
                        'header_dropdown_background_hover'          => '',
                        'header_dropdown_text'                      => '',
                        'header_dropdown_text_hover'                => '',
                        'header_dropdown_border'                    => '',
                        'header_dropdown_border_hover'              => '',
                        'header_dropdown_background_expanded'       => '',
                        'header_dropdown_item_text'                 => '',
                        'header_dropdown_item_background_hover'     => '',
                        'header_dropdown_item_text_hover'           => '',
                        'header_dropdown_item_background_selected'  => '',
                        'header_dropdown_item_text_selected'        => '',
                        'header_dropdown_reset_background'          => '',
                        'header_dropdown_reset_background_hover'    => '',
                        'header_dropdown_reset_border'              => '',
                        'header_dropdown_reset_border_hover'        => '',
                        'header_dropdown_reset_icon'                => '',
                        'header_dropdown_reset_icon_hover'          => '',
                        'listing_results_background'                => '',
                        'listing_results_text'                      => '',
                        'listing_results_link'                      => '',
                        'listing_results_link_hover'                => '',
                        'listing_icons_color'                       => '',
                        'popup_content_background'                  => '',
                        'popup_content_text'                        => '',
                        'popup_content_link'                        => '',
                        'popup_content_link_hover'                  => '',
                        'popup_content_close'                       => '',
                        'popup_content_close_hover'                 => '',
                        'popup_icons_color'                         => '',
                    ],
                    'button_styles' => [
                        'more_details'   => 'primary',
                        'directions'     => 'secondary',
                        'zoom_here'      => 'secondary',
                        'share_location' => 'primary',
                        'no_thanks'      => 'secondary',
                        'streetview'     => 'secondary',
                    ],
                    'map_style' => [
                        'osm'   => [
                            'tile_source'      => 'default',
                            'overwrite_styles' => 0,
                            'selected'         => 'default',
                            'selected_style'   => '',
                        ],
                        'gmaps' => [
                            'selected'    => 'cloud_based',
                            'json'        => '',
                            'cloud_based' => ''
                        ],
                        'mapbox' => [
                            'selected'   => 'standard',
                            'url'        => 'mapbox://styles/mapbox/standard',
                            'custom_url' => ''
                        ],
                        'openfreemap' => [
                            'selected'   => 'liberty',
                            'custom_url' => ''
                        ],
                        'stadia' => [
                            'selected_style' => 'alidade_smooth',
                            'style_source'   => 'stadia'
                        ],
                        'maplibre' => [
                            'custom_url' => '',
                            'enabled'    => 1
                        ]
                    ],
                    'preloader_color'        => 'black',
                    'preloader_custom_color' => '',
                    'result_columns'       => 1,
                    'filter_layout'        => 'horizontal',
                    'custom_focus_outline' => false,
                    'focus_outline'        => '',
                    'enable_icons'    => false,
                    'icons' => [
                        'enabled' => false,
                        'address' => 'marker',
                        'phone'   => 'phone',
                        'email'   => 'email',
                    ],
                    'cta' => [
                        'enabled'        => false,
                        'details'        => false,
                        'details_target' => 'website',
                    ],
                ];
            case 'local_pages':
                return [
                    'permalinks'             => false,
                    'permalink_remove_front' => false,
                    'permalink_slug'         => esc_html__( 'stores', 'wp-store-locator' ),
                    'category_slug'          => esc_html__( 'store-category', 'wp-store-locator' ),
                ];
            case 'labels':
                return [
                    'start_label'              => esc_html__( 'Start location', 'wp-store-locator' ),
                    'search_label'             => esc_html__( 'Your location', 'wp-store-locator' ),
                    'search_name_label'        => esc_html__( 'Store name', 'wp-store-locator' ),
                    'search_btn_label'         => esc_html__( 'Search', 'wp-store-locator' ),
                    'preloader_label'          => esc_html__( 'Searching...', 'wp-store-locator' ),
                    'radius_label'             => esc_html__( 'Search radius', 'wp-store-locator' ),
                    'no_results_label'         => esc_html__( 'No results found.', 'wp-store-locator' ),
                    'results_label'            => esc_html__( 'Results', 'wp-store-locator' ),
                    'more_label'               => esc_html__( 'More info', 'wp-store-locator' ),
                    'directions_label'         => esc_html__( 'Directions', 'wp-store-locator' ),
                    'loading_directions_label' => esc_html__( 'Loading directions', 'wp-store-locator' ),
                    'no_directions_label'      => esc_html__( 'No route found between the origin and destination.', 'wp-store-locator' ),
                    'back_label'               => esc_html__( 'Back', 'wp-store-locator' ),
                    'street_view_label'        => esc_html__( 'Street view', 'wp-store-locator' ),
                    'zoom_here_label'          => esc_html__( 'Zoom here', 'wp-store-locator' ),
                    'error_label'              => esc_html__( 'Something went wrong, please try again', 'wp-store-locator' ),
                    'limit_label'              => esc_html__( 'API usage limit reached', 'wp-store-locator' ),
                    'phone_label'              => esc_html__( 'Phone', 'wp-store-locator' ),
                    'fax_label'                => esc_html__( 'Fax', 'wp-store-locator' ),
                    'email_label'              => esc_html__( 'Email', 'wp-store-locator' ),
                    'url_label'                => esc_html__( 'Url', 'wp-store-locator' ),
                    'more_details_label'       => esc_html__( 'More details', 'wp-store-locator' ),
                    'hours_label'              => esc_html__( 'Hours', 'wp-store-locator' ),
                    'category_label'           => esc_html__( 'Category', 'wp-store-locator' ),
                    'category_default_label'   => esc_html__( 'Any', 'wp-store-locator' ),
                    'categories_label'         => esc_html__( 'Categories', 'wp-store-locator' ),
                    'filters_label'            => esc_html__( 'Filters', 'wp-store-locator' ),
                    'show_filters_label'       => esc_html__( 'Show filters', 'wp-store-locator' ),
                    'apply_label'              => esc_html__( 'Apply', 'wp-store-locator' ),
                    'skip_to_results_label'    => esc_html__( 'Skip map, jump to search results', 'wp-store-locator' ),
                    'routes_api_disabled_label' => esc_html__( 'The directions can only be rendered if the Routes API is enabled.', 'wp-store-locator' ),
                    'routes_api_enabled_link_label' => esc_html__( 'enabled', 'wp-store-locator' ),
                    'number_results_label'     => esc_html__( '{number} stores near you', 'wp-store-locator' ),
                    'number_results_single_label' => esc_html__( '{number} store near you', 'wp-store-locator' ),
                    'geolocation_dialog_label' => esc_html__( 'Would you like to share your location to find nearby stores?', 'wp-store-locator' ),
                    'geolocation_accept_label' => esc_html__( 'Share Location', 'wp-store-locator' ),
                    'geolocation_decline_label' => esc_html__( 'No Thanks', 'wp-store-locator' ),
                    'geolocation_locating_label' => esc_html__( 'Determining your location…', 'wp-store-locator' ),
                    'visibility'                => [
                        'search'      => true,
                        'search_name' => true,
                        'radius'      => true,
                        'results'     => true,
                        'category'    => true,
                    ],
                ];
            case 'gdpr':
                return [
                    'handler'     => 'none',
                    'description' => __( 'This content is hosted by a third party. By loading the external content you accept the privacy policy of [map_provider]. [policy_url]Learn more[/policy_url]', 'wp-store-locator' ),
                    'gmaps' => [
                        'privacy_policy_url' => 'https://policies.google.com/privacy'
                    ],
                    'mapbox' => [
                        'privacy_policy_url' => 'https://www.mapbox.com/legal/privacy'
                    ],
                    'osm' => [
                        'privacy_policy_url' => 'https://osmfoundation.org/wiki/Privacy_Policy'
                    ],
                    'stadia' => [
                        'privacy_policy_url' => 'https://stadiamaps.com/privacy/privacy-policy/'
                    ],
                ];
            case 'tools':
                return [
                    'debug' => false,
                    'deregister_gmaps' => false,
                    'disable_v3_css' => false
                ];
            default:
                return [];
        }
    }

    /**
     * Convert the legacy 2.x "More info" options to the 3.x detail model.
     *
     * The 2.x plugin had a single 'more_info' checkbox plus a
     * 'more_info_location' dropdown ( 'info window' / 'store listings' ).
     * This maps that onto the 3.x per-detail location arrays:
     *
     *  - more_info on  + store listings -> shown behind the "More info" link.
     *  - more_info on  + info window    -> shown in the marker pop-up.
     *  - more_info off                  -> contact details stay inline when
     *                                      they were enabled in 2.x.
     *
     * @since  3.0.0
     * @param  array $old_settings The 2.x settings.
     * @return array               The ux keys for the new detail model.
     */
    private function migrate_more_info( $old_settings ) {
        $more_info     = ! empty( $old_settings['more_info'] );
        $more_info_loc = isset( $old_settings['more_info_location'] ) ? $old_settings['more_info_location'] : 'info window';

        if ( $more_info && $more_info_loc === 'store listings' ) {
            return [
                'contact_details'      => [ 'more_info' ],
                'hours'                => [ 'more_info' ],
                'description'          => [ 'more_info' ],
            ];
        }

        if ( $more_info && $more_info_loc === 'info window' ) {
            return [
                'contact_details'      => [ 'marker_popup' ],
                'hours'                => [ 'marker_popup' ],
                'description'          => [ 'marker_popup' ],
            ];
        }

        // "More info" was off: only keep the inline contact details if they were enabled.
        return [
            'contact_details'      => ! empty( $old_settings['show_contact_details'] ) ? [ 'search_results' ] : [],
            'hours'                => [],
            'description'          => [],
        ];
    }

    /**
     * Migrate the 2.x height setting into the per-template dimensions.
     *
     * 2.x had one height field that it applied to the map and the results
     * together, except on the below_map template with a non-scrolling listing,
     * where only the map used it - 3.x handles that case from
     * listing_below_no_scroll, so both heights are migrated either way.
     *
     * @since  3.0.0
     * @param  array $old_settings The 2.x settings.
     * @return array               The dimensions keys for the 2.x height.
     */
    private function migrate_dimension_heights( $old_settings ) {
        $height = isset( $old_settings['height'] ) ? absint( $old_settings['height'] ) : 0;

        if ( ! $height ) {
            return [];
        }

        if ( $old_settings['template_id'] === 'below_map' ) {
            return [
                'horizontal' => [
                    'map_height_mode'     => 'custom',
                    'map_height'          => $height,
                    'results_height_mode' => 'custom',
                    'results_height'      => $height,
                ],
            ];
        }

        return [
            'default' => [
                'map_height_mode' => 'custom',
                'map_height'      => $height,
            ],
        ];
    }

    /**
     * Migrate the 2.x permalink slugs.
     *
     * Only a slug the 2.x install actually stored is returned, so anything
     * else keeps the 3.x default once the groups are merged. These are the
     * post type and taxonomy rewrite slugs - migrating an empty value would
     * change every store URL on the site.
     *
     * @since  3.0.0
     * @param  array $old_settings The 2.x settings.
     * @return array               The local_pages slugs that have a 2.x value.
     */
    private function migrate_permalink_slugs( $old_settings ) {
        $slugs = [];

        foreach ( [ 'permalink_slug', 'category_slug' ] as $key ) {
            if ( ! empty( $old_settings[ $key ] ) ) {
                $slugs[ $key ] = $old_settings[ $key ];
            }
        }

        return $slugs;
    }

    /**
     * Migrate a 2.x marker value, falling back to the 3.x default.
     *
     * @since  3.0.0
     * @param  mixed  $value The 2.x marker value.
     * @param  string $key   The markers group key, "start_marker" or "store_marker".
     * @return string        The resolved marker filename, or the 3.x default.
     */
    private function migrate_marker( $value, $key ) {
        $resolved = wpsl_sanitize_marker_value( $value );

        return $resolved ? $resolved : $this->get_default( 'markers', $key );
    }

    /**
     * Migrate the 2.x options to the 3.x format.
     *
     * @todo for now we will keep the original wpsl_settings option
     * as a backup, will add code in future 3.x to remove it.
     *
     * @since 3.0.0
     */
    public function migrate() {
        $old_settings = get_option( 'wpsl_settings' );
        
        // Safety check: ensure old settings exist and are valid
        if ( ! $old_settings || ! is_array( $old_settings ) ) {
            error_log( 'WPSL: Migration failed - wpsl_settings option is missing or invalid' );
            return false;
        }

        /**
         * The cluster marker only exists for 2.3.x installs. If it's
         * set, validate it against the 3.x preset library so a preset that no
         * longer exists falls back to the default circles.
         */
        $old_cluster_shape = $old_settings['cluster_marker_shape'] ?? 'default';

        if ( ! in_array( $old_cluster_shape, array_merge( [ 'default' ], array_keys( wpsl_get_cluster_marker_shapes() ) ), true ) ) {
            $old_cluster_shape = 'default';
        }

        /*
         * The api_gmaps_* fallbacks cover installs upgraded by a 3.0 beta that
         * renamed these keys, which left the migration unable to find them.
         */
        $browser_key = $old_settings['api_browser_key'] ?? $old_settings['api_gmaps_browser_key'] ?? '';
        $server_key  = $old_settings['api_server_key'] ?? $old_settings['api_gmaps_server_key'] ?? '';

        // Only set from 2.2.250 onwards, so pre-2.2.250 installs fall back to the 3.x default.
        $api_versions = $old_settings['api_versions'] ?? $this->defaults( 'api' )['versions']['gmaps'];

        /*
         * The 2.x label texts live in the same flat option under identical key
         * names. Only the keys 2.x actually stored are copied, so labels that
         * are new in 3.x ( and the visibility map ) keep their defaults when
         * the groups are merged below.
         */
        $old_labels = [];

        foreach ( array_keys( $this->defaults( 'labels' ) ) as $label_key ) {
            if ( isset( $old_settings[ $label_key ] ) ) {
                $old_labels[ $label_key ] = $old_settings[ $label_key ];
            }
        }

        $new_settings = [
            'api' => [
                'active_map_service' => 'gmaps',
                'gmaps_browser_key' => $browser_key,
                'gmaps_server_key'  => $server_key,
                'gmaps_language'    => $old_settings['api_language'],
                'gmaps_region'      => $old_settings['api_region'],
                'geocode_component' => $old_settings['api_geocode_component'],
                'versions'          => [
                    'gmaps' => $api_versions
                ],
            ],
            'search' => [
                'auto_locate'              => $old_settings['auto_locate'],
                /*
                 * The settings UI, the search template and the frontend assets
                 * all read the version from this group; the api group copy
                 * above only feeds the legacy add-on compat layer.
                 */
                'api_versions'             => [
                    'gmaps' => $api_versions
                ],
                'distance_unit'            => $old_settings['distance_unit'],
                'max_results'              => $old_settings['max_results'],
                'search_radius'            => $old_settings['search_radius'],
                'force_postalcode'         => $old_settings['force_postalcode'],
                'autocomplete'             => $old_settings['autocomplete'],
                'radius_dropdown'          => $old_settings['radius_dropdown'],
                'results_dropdown'         => $old_settings['results_dropdown'],
                'category_filter'          => $old_settings['category_filter'],
                'category_filter_type'     => $old_settings['category_filter_type'],
                'all_categories_required'  => isset( $old_settings['all_categories_required'] ) ? $old_settings['all_categories_required'] : false,
                'category_filter_only'     => isset( $old_settings['category_filter_only'] ) ? $old_settings['category_filter_only'] : false,
                'category_default'         => isset( $old_settings['category_default'] ) ? $old_settings['category_default'] : '',
            ],
            'map' => [
                'start_name'            => $old_settings['start_name'],
                'start_latlng'          => $old_settings['start_latlng'],
                'autoload'              => $old_settings['autoload'],
                'autoload_limit'        => $old_settings['autoload_limit'],
                'run_fitbounds'         => isset( $old_settings['run_fitbounds'] ) ? $old_settings['run_fitbounds'] : 0,
                'zoom_level'            => $old_settings['zoom_level'],
                'auto_zoom_level'       => $old_settings['auto_zoom_level'],
                'max_auto_zoom'         => isset( $old_settings['max_auto_zoom'] ) ? $old_settings['max_auto_zoom'] : 15,
                'streetview'            => $old_settings['streetview'],
                'type_control'          => $old_settings['type_control'],
                'scrollwheel'           => $old_settings['scrollwheel'],
                'control_position'      => $old_settings['control_position'],
                'type'                  => $old_settings['map_type'],
                'show_credits'          => $old_settings['show_credits'],
            ],
            'ux' => [
                'new_window'                => $old_settings['new_window'],
                'reset_map'                 => $old_settings['reset_map'],
                'listing_below_no_scroll'   => $old_settings['listing_below_no_scroll'],
                'direction_redirect'        => $old_settings['direction_redirect'],
                'marker_effect'             => $old_settings['marker_effect'],
                'address_format'            => $old_settings['address_format'],
                'hide_distance'             => $old_settings['hide_distance'],
                'hide_country'              => $old_settings['hide_country'],
                'show_contact_details'      => $old_settings['show_contact_details'],
                'clickable_contact_details' => $old_settings['clickable_contact_details'],
                'store_url'                 => $old_settings['store_url'],
                'phone_url'                 => $old_settings['phone_url'],
                'marker_streetview'         => $old_settings['marker_streetview'],
                'marker_zoom_to'            => $old_settings['marker_zoom_to'],
                'mouse_focus'               => $old_settings['mouse_focus'],
            ] + $this->migrate_more_info( $old_settings ),
            'markers' => [
                'start_marker'         => $this->migrate_marker( isset( $old_settings['start_marker'] ) ? $old_settings['start_marker'] : '', 'start_marker' ),
                'store_marker'         => $this->migrate_marker( isset( $old_settings['store_marker'] ) ? $old_settings['store_marker'] : '', 'store_marker' ),
                'marker_clusters'      => $old_settings['marker_clusters'],
                'cluster_zoom'         => empty( $old_settings['cluster_zoom'] ) ? false : $old_settings['cluster_zoom'],
                'cluster_size'         => empty( $old_settings['cluster_size'] ) ? false : $old_settings['cluster_size'],
                'cluster_marker_shape' => $old_cluster_shape,
            ],
            'editor' => [
                'country'     => $old_settings['editor_country'],
                'map_type'    => $old_settings['editor_map_type'],
                'hours'       => $old_settings['editor_hours'],
                'hour_input'  => $old_settings['editor_hour_input'],
                'hour_format' => $old_settings['editor_hour_format'],
                'hide_hours'  => $old_settings['hide_hours'],
            ],
            'appearance' => [
                'template_id' => ( $old_settings['template_id'] === 'below_map' ) ? 'horizontal' : $old_settings['template_id'],
                'dimensions' => [
                    'search_width' => $old_settings['search_width'],
                    'label_width'  => $old_settings['label_width'],
                ] + $this->migrate_dimension_heights( $old_settings ),
                'map_style' => [
                    'gmaps' => [
                        'json' => $old_settings['map_style'],
                    ]
                ],
            ],
            'local_pages' => [
                'permalinks'             => $old_settings['permalinks'],
                'permalink_remove_front' => $old_settings['permalink_remove_front'],
            ] + $this->migrate_permalink_slugs( $old_settings ),
            'labels' => $old_labels,
            'tools' => [
                'debug' => $old_settings['debug'],
                'deregister_gmaps' => isset( $old_settings['deregister_gmaps'] ) ? $old_settings['deregister_gmaps'] : false,
            ],
        ];

        // Combine the default 3.x values with the migrated options from 2.x
        $merged_settings = [];
        
        foreach ( $this->groups as $group ) {
            // Skip groups that don't have migrated settings
            if ( ! isset( $new_settings[ $group ] ) ) {
                $merged_settings[ $group ] = $this->defaults( $group );

                continue;
            }
            
            $defaults = $this->defaults( $group );
            
            $merged_settings[ $group ] = array_replace_recursive( $defaults, $new_settings[ $group ] );
        }

        // Save all the merged settings to the database
        $success = true;
        $failed_groups = [];
        
        foreach ( $merged_settings as $group => $settings ) {
            $update_success = $this->update( $group, $settings );
            
            if ( ! $update_success ) {
                $success = false;
                $failed_groups[] = $group;
            }
        }
        
        // Log failures
        if ( ! $success ) {
            error_log( 'WPSL: Migration failed for groups: ' . implode( ', ', $failed_groups ) );
        }
        
        return $success;
    }
}