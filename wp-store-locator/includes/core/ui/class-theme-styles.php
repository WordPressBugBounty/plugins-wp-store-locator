<?php
/**
 * Generate custom CSS for themes that use custom style colors.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\UI;

defined( 'ABSPATH' ) || exit;

use WPSL\Core\Settings\Manager as Settings;

class Theme_Styles {

    /**
     * Map the different sections to
     * the correct jQuery selector.
     *
     * @since 3.0.0
     */
    private $selectors = [];

    /**
     * Map the different css properties to the
     * correct $wpsl_settings['theme_colors'] field.
     *
     * @since 3.0.0
     */
    private $properties_map = [];

    /**
     * The default theme styles.
     *
     * @since 3.0.0
     */
    public $defaults = [];

    /**
     * Holds the CSS output
     *
     * @since 3.0.0
     */
    private $css_styles;

    /**
     * Settings manager instance
     *
     * @var \WPSL\Core\Settings\Manager
     * @since 3.0.0
     */
    private $settings;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;

        $prefix = is_admin() ? '#wpsl-appearance-preview #wpsl-wrap ' : '#wpsl-wrap ';

        $this->selectors = [
            'header' => $prefix . '.wpsl-search',
            'header_label' => $prefix . '.wpsl-search label',
            'header_input' => $prefix . '.wpsl-search input[type=text]', // Not using an ID because Mapbox autocomplete input has not ID.
            'header_dropdown_select' => $prefix . '#wpsl-search-wrap select',
            'header_dropdown' => $prefix . '.wpsl-dropdown',
            'header_dropdown_selected_item' => $prefix . '.wpsl-dropdown .wpsl-selected-item',
            'header_dropdown_list' => $prefix . '.wpsl-dropdown ul',
            'header_dropdown_item' => $prefix . '.wpsl-dropdown li',
            'header_dropdown_button' => $prefix . '#wpsl-result-filters button',
            'header_filter_container' => $prefix . '#wpsl-filter-header',
            'header_filter_options' => $prefix . '#wpsl-filter-options',
            'header_filter_item' => $prefix . '#wpsl-filter-options .wpsl-filter li, ' . $prefix . '#wpsl-filter-options .wpsl-filter label',
            'header_filter_dropdown_item' => $prefix . '#wpsl-filter-options .wpsl-dropdown li',
            'header_filter_item_selected' => $prefix . '#wpsl-filter-options li[aria-selected="true"]',
            'header_filter_reset_button' => $prefix . '#wpsl-clear-filter',
            'header_filter_reset_button_icon' => $prefix . '#wpsl-clear-filter svg',
            'header_search_button' => $prefix . '#wpsl-search-btn',
            'header_reset_button' => $prefix . '#wpsl-clear-search-input',
            'header_reset_button_icon' => $prefix . '#wpsl-clear-search-input svg',
            'listing' => $prefix . '#wpsl-result-list',
            'listing_link' => $prefix . '#wpsl-stores a',
        ];

        $this->properties_map = [
            'header' => [
                'background' => 'header_container_background',
            ],
            'header_label' => [
                'color' => 'header_container_text',
            ],
            'header_input' => [
                'background' => 'header_input_background',
                'background_hover' => 'header_input_background_hover',
                'color' => 'header_input_text',
                'color_hover' => 'header_input_text_hover',
                'border' => 'header_input_border',
                'border_hover' => 'header_input_border_hover',
            ],
            'header_dropdown_select' => [
                'background-color' => 'header_dropdown_background',
                'background-color_hover' => 'header_dropdown_background_hover',
                'color' => 'header_dropdown_text',
                'color_hover' => 'header_dropdown_text_hover',
                'border' => 'header_dropdown_border',
                'border_hover' => 'header_dropdown_border_hover',
            ],
            'header_dropdown' => [
                'background' => 'header_dropdown_background',
                'background_hover' => 'header_dropdown_background_hover',
                'color' => 'header_dropdown_text',
                'color_hover' => 'header_dropdown_text_hover',
                'border' => 'header_dropdown_border',
                'border_hover' => 'header_dropdown_border_hover',
            ],
            'header_dropdown_selected_item' => [
                'border' => 'header_dropdown_border',
                'border_hover' => 'header_dropdown_border_hover',
            ],
            'header_dropdown_list' => [
                'background' => 'header_dropdown_background',
            ],
            'header_dropdown_item' => [
                'background_hover' => 'header_dropdown_item_background_hover',
                'color_hover' => 'header_dropdown_item_text_hover',
            ],
            'header_dropdown_button' => [
                'background' => 'header_dropdown_background',
                'background_hover' => 'header_dropdown_background_hover',
                'color' => 'header_dropdown_text',
                'color_hover' => 'header_dropdown_text_hover',
            ],
            'header_filter_container' => [
                'background' => 'header_dropdown_background',
                'color' => 'header_dropdown_text',
            ],
            'header_filter_options' => [
                'background' => 'header_dropdown_background_expanded',
            ],
            'header_filter_item' => [
                'color' => 'header_dropdown_item_text',
                'background_hover' => 'header_dropdown_item_background_hover',
                'color_hover' => 'header_dropdown_item_text_hover',
            ],
            'header_filter_dropdown_item' => [
                'color' => 'header_dropdown_item_text',
                'background_hover' => 'header_dropdown_item_background_hover',
                'color_hover' => 'header_dropdown_item_text_hover',
            ],
            'header_filter_item_selected' => [
                'background' => 'header_dropdown_item_background_selected',
                'color' => 'header_dropdown_item_text_selected',
            ],
            'header_filter_reset_button' => [
                'background' => 'header_dropdown_reset_background',
                'background_hover' => 'header_dropdown_reset_background_hover',
                'border' => 'header_dropdown_reset_border',
                'border_hover' => 'header_dropdown_reset_border_hover',
            ],
            'header_filter_reset_button_icon' => [
                'color' => 'header_dropdown_reset_icon',
                'color_hover' => 'header_dropdown_reset_icon_hover',
            ],
            'header_search_button' => [
                'background' => 'submit_background',
                'background_hover' => 'submit_background_hover',
                'color' => 'submit_text',
                'color_hover' => 'submit_text_hover',
                'border' => 'submit_border',
                'border_hover' => 'submit_border_hover',
            ],
            'header_reset_button' => [
                'background' => 'header_reset_background',
                'background_hover' => 'header_reset_background_hover',
                'border' => 'header_reset_border',
                'border_hover' => 'header_reset_border_hover',
            ],
            'header_reset_button_icon' => [
                'color' => 'header_reset_icon',
                'color_hover' => 'header_reset_icon_hover',  // This will be used for parent hover
            ],
            'general_primary_button' => [
                'background_start' => 'general_primary_background_start',
                'background_end' => 'general_primary_background_end',
                'background_start_hover' => 'general_primary_background_start_hover',
                'background_end_hover' => 'general_primary_background_end_hover',
                'color' => 'general_primary_text_color',
                'color_hover' => 'general_primary_text_color_hover',
                'border' => 'general_primary_border_color',
                'border_hover' => 'general_primary_border_color_hover',
                'angle' => 'general_primary_angle',
            ],
            'general_secondary_button' => [
                'background_start' => 'general_secondary_background_start',
                'background_end' => 'general_secondary_background_end',
                'background_start_hover' => 'general_secondary_background_start_hover',
                'background_end_hover' => 'general_secondary_background_end_hover',
                'color' => 'general_secondary_text_color',
                'color_hover' => 'general_secondary_text_color_hover',
                'border' => 'general_secondary_border_color',
                'border_hover' => 'general_secondary_border_color_hover',
                'angle' => 'general_secondary_angle',
            ],
            'listing' => [
                'background' => 'listing_results_background',
                'color' => 'listing_results_text',
            ],
            'listing_link' => [
                'color' => 'listing_results_link',
                'color_hover' => 'listing_results_link_hover',
            ],
            'listing_icons' => [
                'color' => 'listing_icons_color',
            ],
            'listing_cta_more_details' => [
                'background_start' => 'listing_cta_more_details_background_start',
                'background_end' => 'listing_cta_more_details_background_end',
                'background_start_hover' => 'listing_cta_more_details_background_start_hover',
                'background_end_hover' => 'listing_cta_more_details_background_end_hover',
                'color' => 'listing_cta_more_details_text',
                'color_hover' => 'listing_cta_more_details_text_hover',
                'border' => 'listing_cta_more_details_border',
                'border_hover' => 'listing_cta_more_details_border_hover',
                'angle' => 'listing_cta_more_details_angle',
            ],
            'listing_cta_directions' => [
                'background_start' => 'listing_cta_directions_background_start',
                'background_end' => 'listing_cta_directions_background_end',
                'background_start_hover' => 'listing_cta_directions_background_start_hover',
                'background_end_hover' => 'listing_cta_directions_background_end_hover',
                'color' => 'listing_cta_directions_text',
                'color_hover' => 'listing_cta_directions_text_hover',
                'border' => 'listing_cta_directions_border',
                'border_hover' => 'listing_cta_directions_border_hover',
                'angle' => 'listing_cta_directions_angle',
            ],
            'popup_content' => [
                'background' => 'popup_content_background',
                'color' => 'popup_content_text',
                'link' => 'popup_content_link',
                'link_hover' => 'popup_content_link_hover',
                'close' => 'popup_content_close',
                'close_hover' => 'popup_content_close_hover',
            ],
            'popup_icons' => [
                'color' => 'popup_icons_color',
            ],
            'popup_cta_more_details' => [
                'background_start' => 'popup_cta_more_details_background_start',
                'background_end' => 'popup_cta_more_details_background_end',
                'background_start_hover' => 'popup_cta_more_details_background_start_hover',
                'background_end_hover' => 'popup_cta_more_details_background_end_hover',
                'color' => 'popup_cta_more_details_text',
                'color_hover' => 'popup_cta_more_details_text_hover',
                'border' => 'popup_cta_more_details_border',
                'border_hover' => 'popup_cta_more_details_border_hover',
                'angle' => 'popup_cta_more_details_angle',
            ],
            'popup_cta_directions' => [
                'background_start' => 'popup_cta_directions_background_start',
                'background_end' => 'popup_cta_directions_background_end',
                'background_start_hover' => 'popup_cta_directions_background_start_hover',
                'background_end_hover' => 'popup_cta_directions_background_end_hover',
                'color' => 'popup_cta_directions_text',
                'color_hover' => 'popup_cta_directions_text_hover',
                'border' => 'popup_cta_directions_border',
                'border_hover' => 'popup_cta_directions_border_hover',
                'angle' => 'popup_cta_directions_angle',
            ],
        ];

        $this->defaults = [
            'header-container-background' => '#f4f3f3',
            'header-container-text' => '#000',
            'header-input-background' => '#fff',
            'header-input-background-hover' => '#fff',
            'header-input-text' => '#000',
            'header-input-text-hover' => '#000',
            'header-input-border' => '#d0d0d0',
            'header-input-border-hover' => '#d0d0d0',
            'header-submit-background-start' => '#f0f0f0',
            'header-submit-background-end' => '#e0e0e0',
            'header-submit-background-start-hover' => '#e0e0e0',
            'header-submit-background-end-hover' => '#d0d0d0',
            'header-submit-text' => '#000',
            'header-submit-text-hover' => '#000',
            'header-submit-border' => '#e2e2e2',
            'header-submit-border-hover' => '#e2e2e2',
            'header-submit-angle' => '180',
            'header-reset-background' => '#fff',
            'header-reset-background-hover' => '#f0f0f0',
            'header-reset-icon' => '#6b7280',
            'header-reset-icon-hover' => '#404040',
            'header-reset-border' => '#fff',
            'header-reset-border-hover' => '#fff',
            'header-dropdown-background' => '#fff',
            'header-dropdown-background-hover' => '#fff',
            'header-dropdown-background-expanded' => '#fff',
            'header-dropdown-text' => '#000',
            'header-dropdown-text-hover' => '#000',
            'header-dropdown-border' => '#d0d0d0',
            'header-dropdown-border-hover' => '#d0d0d0',
            'header-dropdown-item-text' => '#000',
            'header-dropdown-item-background-hover' => '#f8f9f8',
            'header-dropdown-item-text-hover' => '#000',
            'header-dropdown-item-background-selected' => '#fff',
            'header-dropdown-item-text-selected' => '#000',
            'header-dropdown-list-item-background' => '#fff',
            'header-dropdown-list-item-background-hover' => '#f8f9f8',
            'header-dropdown-list-item-text' => '#000',
            'header-dropdown-list-item-text-hover' => '#000',
            'header-dropdown-reset-background' => '#fff',
            'header-dropdown-reset-background-hover' => '#f0f0f0',
            'header-dropdown-reset-border' => '#fff',
            'header-dropdown-reset-border-hover' => '#fff',
            'header-dropdown-reset-icon' => '#6b7280',
            'header-dropdown-reset-icon-hover' => '#404040',
            'listing-results-background' => '',
            'listing-results-text' => '#000',
            'listing-results-link' => '#0066cc',
            'listing-results-link-hover' => '#003366',
            'listing-icons-color' => '#000',
            'listing-cta-more-details-background-start' => '#ccc',
            'listing-cta-more-details-background-end' => '#bbb',
            'listing-cta-more-details-background-start-hover' => '#bbb',
            'listing-cta-more-details-background-end-hover' => '#aaa',
            'listing-cta-more-details-text' => '#000',
            'listing-cta-more-details-text-hover' => '#000',
            'listing-cta-more-details-border' => 'transparent',
            'listing-cta-more-details-border-hover' => 'transparent',
            'listing-cta-more-details-angle' => '180',
            'listing-cta-directions-background-start' => '#ccc',
            'listing-cta-directions-background-end' => '#bbb',
            'listing-cta-directions-background-start-hover' => '#bbb',
            'listing-cta-directions-background-end-hover' => '#aaa',
            'listing-cta-directions-text' => '#000',
            'listing-cta-directions-text-hover' => '#000',
            'listing-cta-directions-border' => 'transparent',
            'listing-cta-directions-border-hover' => 'transparent',
            'listing-cta-directions-angle' => '180',
            'popup-content-background' => '#fff',
            'popup-content-text' => '#000',
            'popup-content-link' => '#0066cc',
            'popup-content-link-hover' => '#003366',
            'popup-content-close' => '#666',
            'popup-content-close-hover' => '#000',
            'popup-icons-color' => '#000',
            'popup-cta-more-details-background-start' => '#ccc',
            'popup-cta-more-details-background-end' => '#bbb',
            'popup-cta-more-details-background-start-hover' => '#bbb',
            'popup-cta-more-details-background-end-hover' => '#aaa',
            'popup-cta-more-details-text' => '#000',
            'popup-cta-more-details-text-hover' => '#000',
            'popup-cta-more-details-border' => 'transparent',
            'popup-cta-more-details-border-hover' => 'transparent',
            'popup-cta-more-details-angle' => '180',
            'popup-cta-directions-background-start' => '#ccc',
            'popup-cta-directions-background-end' => '#bbb',
            'popup-cta-directions-background-start-hover' => '#bbb',
            'popup-cta-directions-background-end-hover' => '#aaa',
            'popup-cta-directions-text' => '#000',
            'popup-cta-directions-text-hover' => '#000',
            'popup-cta-directions-border' => 'transparent',
            'popup-cta-directions-border-hover' => 'transparent',
            'popup-cta-directions-angle' => '180',
            'general-primary-background-start' => '#0073aa',
            'general-primary-background-end' => '#00659C',
            'general-primary-background-start-hover' => '#0073aa',
            'general-primary-background-end-hover' => '#00578e',
            'general-primary-border-color' => '#0f4c7a',
            'general-primary-border-color-hover' => '#0f4c7a',
            'general-primary-text-color' => '#fff',
            'general-primary-text-color-hover' => '#fff', 
            'general-primary-angle' => '180',
            'general-secondary-background-start' => '#f4f4f4',
            'general-secondary-background-end' => '#e6e6e6',
            'general-secondary-background-start-hover' => '#f4f4f4',
            'general-secondary-background-end-hover' => '#d8d8d8',
            'general-secondary-border-color' => '#d2d2d2',
            'general-secondary-border-color-hover' => '#d2d2d2',
            'general-secondary-text-color' => '#333',
            'general-secondary-text-color-hover' => '#333',
            'general-secondary-angle' => '180',
            'submit-background-start' => '#f4f4f4',
            'submit-background-end' => '#e6e6e6',
            'submit-border' => '#d2d2d2',
            'submit-text' => '#333',
            'submit-background-start-hover' => '#f4f4f4',
            'submit-background-end-hover' => '#d8d8d8',
            'submit-border-hover' => '#d2d2d2',
            'submit-text-hover' => '#333',
            'submit-angle' => '180'
        ];

        $this->css_styles = '';
    }

    /**
     * Generate only the CSS custom properties (variables) based on user settings.
     *
     * @since  3.0.0
     * @return string CSS custom properties wrapped in :root selector
     */
    public function build_custom_style() {
        // Check if custom theme styles are enabled
        $appearance_settings = $this->settings->get_group( 'appearance' );
        $overwrite_enabled = isset( $appearance_settings['overwrite_theme_styles'] ) ? $appearance_settings['overwrite_theme_styles'] : false;

        // Always generate CSS custom properties, but use defaults if overwrite is disabled
        $this->create_css_custom_properties( $overwrite_enabled );

        return $this->css_styles;
    }

    /**
     * Generate CSS custom properties from the properties map
     *
     * @since 3.0.0
     * @param bool $use_custom_colors Whether to use custom colors from DB or default values
     */
    private function create_css_custom_properties( $use_custom_colors = true ) {
        $css_vars = [];
        $added_vars = []; // Track which variables have been added to prevent duplicates

        // Loop through the properties map
        foreach ( $this->properties_map as $section => $properties ) {
            foreach ( $properties as $css_property => $setting_field ) {
                
                // Convert the field name to match CSS custom property format
                $css_var_name = str_replace( '_', '-', $setting_field );

                // Skip if this CSS variable has already been added
                if ( isset( $added_vars[$css_var_name] ) ) {
                    continue;
                }

                // Determine which color group to use based on the field name
                $color_group = 'theme_colors';
                
                if ( strpos( $setting_field, 'submit_' ) === 0 || 
                     strpos( $setting_field, 'general_primary_' ) === 0 || 
                     strpos( $setting_field, 'general_secondary_' ) === 0 ) {
                    $color_group = 'button_colors';
                }

                $should_use_custom = $use_custom_colors || $color_group === 'button_colors';

                // Get the color code - use custom if enabled, otherwise use default
                if ( $should_use_custom ) {
                    $color_code = $this->get_color_value( $setting_field, $color_group );
                    
                    // Fallback to default if no custom color was selected
                    if ( ! $color_code ) {
                        if ( isset( $this->defaults[$css_var_name] ) ) {
                            $color_code = $this->defaults[$css_var_name];
                        } else {
                            $color_code = '';
                        }
                    }
                } else {
                    // Use default values only
                    $color_code = isset( $this->defaults[$css_var_name] ) ? $this->defaults[$css_var_name] : '';
                }

                // Handle different types of values
                if ( strpos( $css_var_name, '-angle' ) !== false ) {
                    $color_code = absint( $color_code );
                    
                    if ( $color_code !== '' ) {
                        $css_vars[] = '--wpsl-' . $css_var_name . ': ' . $color_code . 'deg;';
                        $added_vars[$css_var_name] = true;
                    }
                } elseif ( $color_code === 'transparent' ) {
                    $css_vars[] = '--wpsl-' . $css_var_name . ': transparent;';
                    $added_vars[$css_var_name] = true;
                } else {
                    $color_code = sanitize_hex_color( $color_code );

                    if ( $color_code ) {
                        $css_vars[] = '--wpsl-' . $css_var_name . ': ' . $color_code . ';';
                        $added_vars[$css_var_name] = true;
                    }
                }
            }
        }

        // Generate gradient CSS variables from individual color components
        $this->generate_gradient_variables( $css_vars, $added_vars, $use_custom_colors );

        /**
         * Add focus_outline css var - only when the user has explicitly enabled a custom outline.
         * 
         * When disabled, the var() fallback values defined in CSS take over 
         * (e.g. `var(--wpsl-focus-outline, auto)`), so the browser's/theme's native
         * focus ring is used without any PHP-generated value.
         */
        $appearance_settings = $this->settings->get_group( 'appearance' );
        $custom_focus_enabled = isset( $appearance_settings['custom_focus_outline'] ) ? (bool) $appearance_settings['custom_focus_outline'] : false;

        if ( $custom_focus_enabled ) {
            $focus_outline = isset( $appearance_settings['focus_outline'] ) ? $appearance_settings['focus_outline'] : '';

            if ( ! empty( $focus_outline ) ) {
                $focus_outline = sanitize_hex_color( $focus_outline );

                if ( $focus_outline ) {
                    /**
                     * Store complete outline declaration with width and style.
                     *
                     * @see https://www.w3.org/WAI/WCAG22/Understanding/focus-appearance "at least as large as the area of a 2 CSS pixel"
                     */
                    $css_vars[] = '--wpsl-focus-outline: 2px solid ' . $focus_outline . ';';
                    $css_vars[] = '--wpsl-focus-outline-color: ' . $focus_outline . ';'; // Color only for box-shadow
                    $css_vars[] = '--wpsl-focus-outline-button: 2px solid ' . $focus_outline . ';'; // Use custom color for buttons too
                }
            }
        }

        if ( ! empty( $css_vars ) ) {
            $this->css_styles .= ':root {' . "\r\n";
            $this->css_styles .= '    ' . implode( "\r\n    ", $css_vars ) . "\r\n";
            $this->css_styles .= '}' . "\r\n\r\n";
        }

        // Font size CSS variables scoped to #wpsl-wrap.
        // Unset (e.g. a fresh install, or a site upgraded from a version that
        // didn't have this setting) defaults to disabled, so theme defaults
        // apply unless an admin explicitly opts in.
        $overwrite_font_sizes = isset( $appearance_settings['font_sizes']['overwrite_defaults'] ) ? $appearance_settings['font_sizes']['overwrite_defaults'] : false;

        if ( $overwrite_font_sizes ) {
            $base_font_size          = isset( $appearance_settings['font_sizes']['base'] ) ? absint( $appearance_settings['font_sizes']['base'] ) : 14;
            $location_name_font_size = isset( $appearance_settings['font_sizes']['location_name'] ) ? absint( $appearance_settings['font_sizes']['location_name'] ) : 14;
            $cta_buttons_font_size   = isset( $appearance_settings['font_sizes']['cta_buttons'] ) ? absint( $appearance_settings['font_sizes']['cta_buttons'] ) : 14;

            $base_font_size          = ( $base_font_size >= 12 && $base_font_size <= 20 ) ? $base_font_size : 14;
            $location_name_font_size = ( $location_name_font_size >= 12 && $location_name_font_size <= 20 ) ? $location_name_font_size : 14;
            $cta_buttons_font_size   = ( $cta_buttons_font_size >= 12 && $cta_buttons_font_size <= 20 ) ? $cta_buttons_font_size : 14;

            // Generate CSS variables
            $this->css_styles .= '#wpsl-wrap {' . "\r\n";
            $this->css_styles .= '    --wpsl-font-size-base: ' . $base_font_size . 'px;' . "\r\n";
            $this->css_styles .= '    --wpsl-font-size-location-name: ' . $location_name_font_size . 'px;' . "\r\n";
            $this->css_styles .= '    --wpsl-font-size-cta-buttons: ' . $cta_buttons_font_size . 'px;' . "\r\n";
            $this->css_styles .= '}' . "\r\n\r\n";

            // Generate CSS rules that apply the variables
            $this->css_styles .= '#wpsl-wrap *:not(.wpsl-map-style-wrap):not(.wpsl-map-style-wrap *):not(#wpsl-map):not(#wpsl-map *) { font-size: var(--wpsl-font-size-base); }' . "\r\n";
            $this->css_styles .= '#wpsl-wrap .wpsl-location-content > strong:first-child a { font-size: var(--wpsl-font-size-location-name) !important; }' . "\r\n";
            $this->css_styles .= '#wpsl-wrap .wpsl-cta-section * { font-size: var(--wpsl-font-size-cta-buttons) !important; }' . "\r\n";
        }
        // When disabled, no CSS variables or rules are generated, allowing theme defaults to apply
    }

    /**
     * Generate gradient CSS variables from individual color components.
     *
     * @since  3.0.0
     * @param  array &$css_vars Array of CSS variables to add to
     * @param  array &$added_vars Array tracking which variables have been added
     * @param  bool  $use_custom_colors Whether to use custom colors
     * @return void
     */
    private function generate_gradient_variables( &$css_vars, &$added_vars, $use_custom_colors ) {
        // Define gradient button types that need gradient variables
        $gradient_buttons = [
            'submit' => [
                'start' => 'submit_background_start',
                'end' => 'submit_background_end',
                'angle' => 'submit_angle',
                'start_hover' => 'submit_background_start_hover',
                'end_hover' => 'submit_background_end_hover',
            ],
            'general-primary' => [
                'start' => 'general_primary_background_start',
                'end' => 'general_primary_background_end',
                'angle' => 'general_primary_angle',
                'start_hover' => 'general_primary_background_start_hover',
                'end_hover' => 'general_primary_background_end_hover',
            ],
            'general-secondary' => [
                'start' => 'general_secondary_background_start',
                'end' => 'general_secondary_background_end',
                'angle' => 'general_secondary_angle',
                'start_hover' => 'general_secondary_background_start_hover',
                'end_hover' => 'general_secondary_background_end_hover',
            ],
        ];

        foreach ( $gradient_buttons as $button_type => $fields ) {
            // Get the color values
            $start = $this->get_color_value( $fields['start'], 'button_colors' );
            $end = $this->get_color_value( $fields['end'], 'button_colors' );
            $angle = $this->get_color_value( $fields['angle'], 'button_colors' );
            $start_hover = $this->get_color_value( $fields['start_hover'], 'button_colors' );
            $end_hover = $this->get_color_value( $fields['end_hover'], 'button_colors' );

            // Generate gradient variable for normal state
            if ( $start && $end && $angle !== '' ) {
                $gradient = "linear-gradient({$angle}deg, {$start} 0%, {$end} 100%)";
                $css_vars[] = "--wpsl-{$button_type}-gradient: {$gradient};";
                $added_vars["{$button_type}-gradient"] = true;
            }

            // Generate gradient variable for hover state
            if ( $start_hover && $end_hover && $angle !== '' ) {
                $gradient_hover = "linear-gradient({$angle}deg, {$start_hover} 0%, {$end_hover} 100%)";
                $css_vars[] = "--wpsl-{$button_type}-gradient-hover: {$gradient_hover};";
                $added_vars["{$button_type}-gradient-hover"] = true;
            }
        }
    }

    /**
     * Get the color value for a specific field.
     *
     * @since  3.0.0
     * @param  string  $color_field  The field name in settings format (with underscores)
     * @param  string  $color_group  The color group to read from (theme_colors or button_colors)
     * @return string  The color value
     */
    public function get_color_value( $color_field, $color_group = 'theme_colors' ) {
        // Use the stored settings instead of making a new call
        $appearance_settings = $this->settings->get_group( 'appearance' );

        // Check if we have a user-defined value in the specified color group
        // For angle fields, we need to check isset only (0 is a valid value)
        if ( isset( $appearance_settings[$color_group][$color_field] ) ) {
            $value = $appearance_settings[$color_group][$color_field];
            
            // For angle fields, 0 is valid, so only check for non-empty strings
            if ( strpos( $color_field, '_angle' ) !== false ) {
                return $value;
            }
            
            // For color fields, check if the value is truthy
            if ( $value ) {
                return $value;
            }
        }

        // Convert the field name to match defaults format
        $default_key = str_replace('_', '-', $color_field);

        // Return the default value if it exists, otherwise empty string
        return isset( $this->defaults[$default_key] ) ? $this->defaults[$default_key] : '';
    }

    /**
     * Get current styles (saved values merged with defaults) for JavaScript.
     * This ensures JavaScript has access to the actual saved values, not just defaults.
     *
     * @since  3.0.0
     * @return array  Array of current style values with defaults as fallback
     */
    public function get_current_styles() {
        $current_styles = $this->defaults;
        $appearance_settings = $this->settings->get_group( 'appearance' );

        // Merge button colors from database
        if ( isset( $appearance_settings['button_colors'] ) && is_array( $appearance_settings['button_colors'] ) ) {
            foreach ( $appearance_settings['button_colors'] as $field => $value ) {
                $css_var_name = str_replace( '_', '-', $field );
                
                // Only update if value is not empty (except for angle fields where 0 is valid)
                if ( strpos( $field, '_angle' ) !== false ) {
                    if ( isset( $value ) && $value !== '' ) {
                        $current_styles[$css_var_name] = $value;
                    }
                } else {
                    if ( ! empty( $value ) ) {
                        $current_styles[$css_var_name] = $value;
                    }
                }
            }
        }

        // Merge theme colors from database
        if ( isset( $appearance_settings['theme_colors'] ) && is_array( $appearance_settings['theme_colors'] ) ) {
            foreach ( $appearance_settings['theme_colors'] as $field => $value ) {
                $css_var_name = str_replace( '_', '-', $field );
                
                if ( ! empty( $value ) ) {
                    $current_styles[$css_var_name] = $value;
                }
            }
        }

        return $current_styles;
    }
}