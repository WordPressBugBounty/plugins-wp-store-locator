<?php
/**
 * Admin settings manager.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

use WPSL\Admin\Settings\Settings_Service;

class Manager {

    /**
     * Settings manager instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    protected $settings;

    /**
     * Sanitizer instance or callback
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\Sanitizer|callable
     */
    protected $sanitizer;

    /**
     * Settings service
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\Settings_Service
     */
    protected $settings_service;

    /**
     * Constructor
     *
     * @since 3.0.0         
     * @param WpslSettings     $settings         Core settings manager
     * @param Settings_Service $settings_service Settings service
     * @param Sanitizer|callable $sanitizer      Settings sanitizer or callable that returns sanitizer
     */
    public function __construct( WpslSettings $settings, Settings_Service $settings_service, $sanitizer = null ) {
        $this->sanitizer = $sanitizer;
        $this->settings = $settings;
        $this->settings_service = $settings_service;
        
        // Change from admin_init to a slightly later hook to ensure sanitizer is set
        add_action( 'admin_menu', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'maybe_flush_rewrite_and_transient' ] );
        add_filter( 'wp_redirect', [ $this, 'preserve_active_section' ], 10, 2 );
    }

    /**
     * Register the settings.
     * 
     * @since  2.0.0
     * @return void
     */
    public function register_settings() {
        // Don't register settings during WPSL AJAX requests or onboarding
        if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) && strpos( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ), 'wpsl_' ) === 0 ) || 
                ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl-onboarding' ) ) {
            return;
        }

        if ( ! $this->sanitizer ) {
            return;
        }

        $sanitizer = call_user_func( $this->sanitizer );
        $sections = $this->get_sections();

        // Register each section as a separate setting with the same option group
        foreach ( $sections as $section => $fields ) {
            register_setting(
                'wpsl_settings',
                'wpsl_' . $section,
                [
                    'sanitize_callback' => function( $input ) use ( $sanitizer, $section ) {
                        /*
                         * WP runs this on every update_option(), not just form saves.
                         * Sanitizers treat input as raw POST ( isset() on a checkbox
                         * key means "checked" ), so programmatic writes ( defaults,
                         * migrations, Manager::set() ) must skip it or every disabled
                         * toggle would flip on.
                         */
                        if ( $this->settings->doing_programmatic_write() ) {
                            return $input;
                        }

                        if ( method_exists( $sanitizer, $section ) ) {
                            return call_user_func( [ $sanitizer, $section ], $input );
                        } else {
                            return apply_filters( 'wpsl_sanitize_' . $section, $input, $section );
                        }
                    }
                ]
            );
        }
    }

    /**
     * Check if the permalinks settings changed.
     * 
     * @since  2.0.0
     * @return void
     */
    public function maybe_flush_rewrite_and_transient() {
        if ( isset( $_GET['page'] ) && ( sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl_settings' ) ) {
            $flush_rewrite    = get_option( 'wpsl_flush_rewrite' );
            $delete_transient = get_option( 'wpsl_delete_transient' );
            
            if ( $flush_rewrite ) {
                flush_rewrite_rules();
                update_option( 'wpsl_flush_rewrite', 0, 'no' );
            }
            
            if ( $delete_transient ) {
                update_option( 'wpsl_delete_transient', 0, 'no' );
            }

            if ( $flush_rewrite || $delete_transient ) {
                wpsl_get_service( 'system_utils' )->flush_autoload_transients();
            }
        }
    }

    /**
     * Get the settings sections.
     * 
     * @since  3.0.0
     * @return array The settings sections
     */
    public function get_sections() {
        return $this->settings_service->get_sections();
    }

    /**
     * Set the flush rewrite option if necessary
     *
     * @param array $new_settings The new settings being saved
     * @return void
     */
    public function set_flush_rewrite_option( $new_settings ) {
        // Fields that require rewrite rules to be flushed when changed
        $fields = [ 'permalinks', 'permalink_slug', 'permalink_remove_front', 'category_slug' ];
        
        // Get current local_pages settings
        $current_settings = $this->settings->get_group( 'local_pages' );
        
        foreach ( $fields as $field ) {
            if ( isset( $current_settings[$field] ) && isset( $new_settings[$field] ) && 
                $current_settings[$field] != $new_settings[$field] ) {

                update_option( 'wpsl_flush_rewrite', 1, 'no' );

                break;
            }
        }
    }
    
    /**
     * Check if we need to delete the transient data
     *
     * @since  3.0.0
     * @param  string $group The settings group name
     * @param  array  $new_settings The new settings for this group
     * @param  array  $fields_to_check Optional array of specific fields to check, if empty checks all fields
     * @return void
     */
    public function set_delete_transient_option( $group, $new_settings, $fields_to_check = [] ) {
        // Define options that trigger transient deletion when changed
        $transient_trigger_fields = [
            'api'       => ['active_map_service'],
            'map'       => ['start_name', 'autoload', 'autoload_limit'],
            'search'    => ['orderby', 'order', 'distance_unit'],
            'ux'        => ['contact_details', 'hours', 'description', 'hide_distance', 'hide_country', 'show_contact_details', 'hide_closed_hours'],
            'editor'    => ['hide_hours'],
            'tools'     => ['debug'],
            'local_pages' => ['permalinks', 'permalink_slug', 'permalink_remove_front', 'category_slug'],
        ];
        
        // If group doesn't have any fields that trigger transient deletion, return early
        if ( ! isset( $transient_trigger_fields[$group] ) ) {
            return;
        }
        
        // Get current settings for this group
        $current_settings = $this->settings->get_group( $group );
        
        // Determine which fields to check
        $fields = ! empty( $fields_to_check ) ? $fields_to_check : $transient_trigger_fields[$group];
        
        // Check each field for changes
        foreach ( $fields as $field ) {
            if ( isset( $current_settings[$field] ) && isset( $new_settings[$field] ) &&
                $current_settings[$field] != $new_settings[$field] ) {

                update_option( 'wpsl_delete_transient', 1, 'no' );

                return;
            }
        }
        
        // Special check for mapbox service change
        if ( $group === 'api' && isset( $new_settings['active_map_service'] ) && $new_settings['active_map_service'] == 'mapbox' && 
            isset( $current_settings['active_map_service'] ) && $current_settings['active_map_service'] !== 'mapbox' ) {
            
            update_option( 'wpsl_delete_transient', 1, 'no' );
        }
    }

    /**
     * Handle the different validation errors for the plugin settings.
     * 
     * @since  3.0.0
     * @param  string $error_type Contains the type of validation error that occured
     * @return void
     */
    public function validation_error( $error_type ) {
        $this->settings_service->validation_error( $error_type );
    }

    /**
     * Return the path and name of the php file
     * required for the different sections on
     * the settings page.
     *
     * @since  3.0.0
     * @param  string $name
     * @return array  $file
     */
    public function get_template_args( $name ) {
        $file = [
            'name' => $name,
            'path' => WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/sections/'
        ];

        return apply_filters( 'wpsl_setting_section_args', $file );
    }

    /**
     * Create CSS rules based on the
     * $wpsl_settings['theme_colors']
     * values and the active map service.
     *
     * @since  3.0.0
     * @return string $css
     */
    public function create_style_editor_css() {
        $wpsl_settings = $this->settings->get_group( 'appearance' );

        $selectors = [
            'header' => '#wpsl-style-editor .wpsl-search',
            'header_input' => '.wpsl-styled-example #wpsl-search-input',
            'header_dropdown' => '#wpsl-search-wrap select',
            'header_button' => '#wpsl-search-btn',
            'listing' => '#wpsl-stores',
        ];

        $properties_map = [
            'header' => [
                'background' => 'search_background',
                'color' => 'search_text',
            ],
            'header_input' => [
                'background' => 'search_input_background',
                'color' => 'search_input_text',
            ],
            'header_dropdown' => [
                'background' => 'search_dropdown_background',
                'color' => 'search_dropdown_text',
            ],
            'header_button' => [
                'background' => 'search_button_background',
                'color' => 'search_button_text',
            ],
            'listing' => [
                'background' => 'listing_background',
                'color' => 'listing_text',
                'a' => 'listing_link',
            ],
        ];

        $css_rules = [];

        // Build the CSS rules for header / listing style.
        foreach ( $selectors as $key => $selector ) {
            $properties = '';

            foreach ( $properties_map[$key] as $property_value => $property_key ) {
                $color_code = sanitize_hex_color( $wpsl_settings['theme_colors'][$property_key] );

                if ( $color_code ) {
                    $properties .= $property_value . ': ' . $color_code . '; ';
                }
            }

            if ( $properties ) {
                $css_rules[$key] = trim( $properties );
            }
        }

        if ( sanitize_hex_color( $wpsl_settings['theme_colors']['listing_link'] ) ) {
            $css_rules['listing_link'] = 'color: ' . $wpsl_settings['theme_colors']['listing_link'];
        }

        /**
         * Deal with the infowindow styles
         * for the different map providers.
         */
        $infowindow_fields = [ 'background', 'text', 'link' ];

        foreach ( $infowindow_fields as $key ) {
            $infowindow_css_styles[$key] = sanitize_hex_color( $wpsl_settings['theme_colors']['infowindow_' . $key . ''] );
        }

        $infowindow_css = [];

        switch ( $this->settings->get( 'api', 'active_map_service' ) ) {
            case 'gmaps':
                if ( $infowindow_css_styles['background'] ) {
                    $infowindow_css['background'] = '.gm-style-iw-d,' . "\r\n";
                    $infowindow_css['background'] .= '.gm-style .gm-style-iw-c,' . "\r\n";
                    $infowindow_css['background'] .= '.gm-style .gm-style-iw-t::after,' . "\r\n";
                    $infowindow_css['background'] .= '.gm-style .gm-style-iw-tc::after,' . "\r\n";
                    $infowindow_css['background'] .= '.gm-style .gm-style-iw-d::-webkit-scrollbar-track,' . "\r\n";
                    $infowindow_css['background'] .= '.gm-style .gm-style-iw-d::-webkit-scrollbar-track-piece { background: ' . esc_attr( $infowindow_css_styles['background'] ) . '; }' . "\r\n";
                }

                if ( $infowindow_css_styles['text'] ) {
                    $infowindow_css['text'] = '.gm-style-iw-d { color: ' . esc_attr( $infowindow_css_styles['text'] ) . '; }' . "\r\n";
                }

                if ( $infowindow_css_styles['link'] ) {
                    $infowindow_css['link'] = '.gm-style-iw-d a { color: ' . esc_attr( $infowindow_css_styles['link'] ) . '; }' . "\r\n";
                }

                break;
            case 'mapbox':
                if ( $infowindow_css_styles['background'] ) {
                    $infowindow_css['background'] = '.mapboxgl-popup-content { background: ' . esc_attr( $infowindow_css_styles['background'] ) . '; }' . "\r\n";
                    $infowindow_css['background'] .= '.mapboxgl-popup-anchor-bottom .mapboxgl-popup-tip { border-top-color: ' . esc_attr( $infowindow_css_styles['background'] ) . '; }' . "\r\n";
                }

                if ( $infowindow_css_styles['text'] ) {
                    $infowindow_css['text'] = '.mapboxgl-popup-content { color: ' . esc_attr( $infowindow_css_styles['text'] ) . '; }' . "\r\n";
                }

                if ( $infowindow_css_styles['link'] ) {
                    $infowindow_css['link'] = '.mapboxgl-popup-content a { color: ' . esc_attr( $infowindow_css_styles['link'] ) . '; }' . "\r\n";
                }

                break;
            case 'osm':
                if ( $infowindow_css_styles['background'] ) {
                    $infowindow_css['background'] = '.leaflet-popup-content-wrapper, .leaflet-popup-tip { background: ' . esc_attr( $infowindow_css_styles['background'] ) . '; }' . "\r\n";
                }

                if ( $infowindow_css_styles['text'] ) {
                    $infowindow_css['text'] = '.leaflet-popup-content { color: ' . esc_attr( $infowindow_css_styles['text'] ) . '; }' . "\r\n";
                }

                if ( $infowindow_css_styles['link'] ) {
                    $infowindow_css['link'] = '.leaflet-popup-content a { color: ' . esc_attr( $infowindow_css_styles['link'] ) . '; }' . "\r\n";
                }

                break;
        }

        foreach ( $infowindow_css as $property => $value ) {
            echo "\r\n" .'<style id="wpsl-dynamic-popup-' . esc_attr( $property ) . '">' . "\r\n";
            echo $infowindow_css[ $property ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
            echo '</style>';
        }

        return $css_rules;
    }

    /**
     * Check if a field should be disabled based on the check type.
     *
     * @since  3.0.0
     * @param  string $check_type The type of check to perform
     * @return string Returns 'disabled' if the check conditions are met, empty string otherwise
     */
    public function maybe_disable_state( $check_type ) {
        switch ( $check_type ) {
            case 'gmap_styles': 
                $map_style = $this->settings->get_group( 'appearance' );
                
                $selected_style = $map_style['map_style']['gmaps']['selected'];
                
                return empty( $map_style['map_style']['gmaps'][$selected_style] ) ? 'disabled' : '';
            default:
                return '';
        }
    }

    /**
     * Preserve the active section in the redirect URL after settings are saved.
     * 
     * This ensures that when WordPress redirects after saving settings,
     * the user is returned to the same section they were viewing.
     *
     * @since 3.0.0
     * @param string $location The redirect URL
     * @param int    $status   The HTTP status code
     * @return string Modified redirect URL with section parameter
     */
    public function preserve_active_section( $location, $status ) {
        // Only modify redirects for WPSL settings page
        if ( ! isset( $_POST['option_page'] ) || sanitize_text_field( wp_unslash( $_POST['option_page'] ) ) !== 'wpsl_settings' ) {
            return $location;
        }

        // Get the active section from the hidden input field
        if ( isset( $_POST['wpsl_active_section'] ) ) {
            $active_section = sanitize_text_field( wp_unslash( $_POST['wpsl_active_section'] ) );
            
            // Add the section parameter to the redirect URL
            $location = add_query_arg( 'section', $active_section, $location );
        }

        return $location;
    }
}