<?php
/**
 * Handles UI element generation.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
    
class UI {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;
    
    /**
     * Translations instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;
    
    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager     $settings Settings manager instance
     * @param \WPSL\Core\I18n\Translations    $i18n     Translations service instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n ) {
        $this->settings = $settings;
        $this->i18n = $i18n;
    }

    /**
     * Create the dropdown to select the zoom level.
     *
     * @since  1.0.0
     * @return string $dropdown The html for the zoom level list
     */
    public function show_zoom_levels() {
        $dropdown = '<select id="wpsl-zoom-level" name="wpsl_map[zoom_level]" autocomplete="off">';
        
        for ( $i = 1; $i < 13; $i++ ) {
            $selected = ( $this->settings->get( 'map', 'zoom_level' ) == $i ) ? 'selected="selected"' : '';
            
            switch ( $i ) {
                case 1:
                    $zoom_desc = ' - ' . esc_html__( 'World view', 'wp-store-locator' );
                    break;
                case 3:
                    $zoom_desc = ' - ' . esc_html__( 'Default', 'wp-store-locator' );
                    break;
                case 12:
                    $zoom_desc = ' - ' . esc_html__( 'Roadmap', 'wp-store-locator' );
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
     * Show a list of available templates.
     *
     * @since  1.2.20
     * @return string $dropdown The html for the template option list
     */
    public function show_template_options() {
        $dropdown = '<select id="wpsl-store-template" name="wpsl_ux[template_id]" autocomplete="off">';

        foreach ( wpsl_get_templates() as $template ) {
            $template_id = ( isset( $template['id'] ) ) ? $template['id'] : '';
            
            $selected = ( $this->settings->get( 'appearance', 'template_id' ) == $template_id ) ? ' selected="selected"' : '';
            $dropdown .= "<option value='" . esc_attr( $template_id ) . "' $selected>" . esc_html( $template['name'] ) . "</option>";
        }

        $dropdown .= '</select>';

        return $dropdown;            
    }

    /**
     * Create dropdown lists.
     *
     * @since  2.0.0
     * @param  string $type        The type of dropdown
     * @param  string $extra_attrs Optional extra HTML attributes to add to the <select> element (e.g. 'autofocus').
     * @return string $dropdown    The html output for the dropdown
     */
    public function create_dropdown( $type, $extra_attrs = '' ) {
        /**
         * The "open the info window" marker hover effect is only supported by
         * Google Maps. Mapbox and Leaflet only support bouncing the marker, so
         * drop that option from the dropdown when they're the active service.
         */
        $marker_effect_values = [
            'bounce'      => esc_html__( 'Bounces up and down', 'wp-store-locator' ),
            'info_window' => esc_html__( 'Will open the info window', 'wp-store-locator' ),
            'ignore'      => esc_html__( 'Does not respond', 'wp-store-locator' )
        ];

        if ( $this->settings->get( 'api', 'active_map_service' ) !== 'gmaps' ) {
            unset( $marker_effect_values['info_window'] );
        }

        /**
         * Name search matches on the post / location name and never calculates
         * a distance, so sorting by distance isn't possible. Drop that option
         * from the "Sort by" dropdown and, when it was the saved value, fall
         * back to the first remaining option so a valid choice stays selected.
         */
        $order_values   = wpsl_get_search_order_options();
        $order_selected = $this->settings->get( 'search', 'orderby' );

        if ( $this->settings->get( 'search', 'search_method' ) === 'name' ) {
            unset( $order_values['distance'] );

            if ( $order_selected === 'distance' ) {
                $order_selected = key( $order_values );
            }
        }

        /**
         * Borlabs Cookie and Complianz only work while their plugin is active,
         * so only offer them then. A handler saved before deactivation stays
         * listed, so saving an unrelated setting can't silently drop the
         * consent check.
         */
        $gdpr_selected = $this->settings->get( 'gdpr', 'handler' );
        $gdpr_values   = [
            'none' => esc_html__( 'None', 'wp-store-locator' ),
            'wpsl' => esc_html__( 'WP Store Locator', 'wp-store-locator' )
        ];

        if ( wpsl_is_gdpr_handler_available( 'borlabs' ) || $gdpr_selected === 'borlabs' ) {
            $gdpr_values['borlabs'] = esc_html__( 'Borlabs Cookie', 'wp-store-locator' );
        }

        if ( wpsl_is_gdpr_handler_available( 'complianz' ) || $gdpr_selected === 'complianz' ) {
            $gdpr_values['complianz'] = esc_html__( 'Complianz', 'wp-store-locator' );
        }

        $dropdown_lists = apply_filters( 'wpsl_setting_dropdowns', [
            'hour_input' => [
                'values' => [
                    'textarea' => esc_html__( 'Textarea', 'wp-store-locator' ),
                    'dropdown' => esc_html__( 'Dropdowns (recommended)', 'wp-store-locator' )
                ],
                'id'       => 'wpsl-editor-hour-input',
                'name'     => 'wpsl_editor[hour_input]',
                'selected' => $this->settings->get( 'editor', 'hour_input' )
            ],
            'marker_effects' => [
                'values'   => $marker_effect_values,
                'id'       => 'wpsl-marker-effect',
                'name'     => 'wpsl_ux[marker_effect]',
                'selected' => $this->settings->get( 'ux', 'marker_effect' )
            ],
            'more_info' => [
                'values' => [
                    'store listings' => esc_html__( 'In the store listings', 'wp-store-locator' ),
                    'info window'    => esc_html__( 'In the info window on the map', 'wp-store-locator' )
                ],
                'id'       => 'wpsl-more-info-list',
                'name'     => 'wpsl_ux[more_info_location]',
                'selected' => $this->settings->get( 'ux', 'more_info_location' )
            ],
            'map_types' => [
                'values'   => wpsl_get_map_types(),
                'id'       => 'wpsl-map-type',
                'name'     => 'wpsl_map[type]',
                'selected' => $this->settings->get( 'map', 'type' )
            ],
            'editor_map_types' => [
                'values'   => wpsl_get_map_types(),
                'id'       => 'wpsl-editor-map-type',
                'name'     => 'wpsl_editor[map_type]',
                'selected' => $this->settings->get( 'editor', 'map_type' )
            ],
            'max_zoom_level' => [
                'values'   => wpsl_get_max_zoom_levels(),
                'id'       => 'wpsl-max-auto-zoom',
                'name'     => 'wpsl_map[max_auto_zoom]',
                'selected' => $this->settings->get( 'map', 'auto_zoom_level' )
            ],
            'address_format' => [
                'values'   => wpsl_get_address_formats(),
                'id'       => 'wpsl-address-format',
                'name'     => 'wpsl_ux[address_format]',
                'selected' => $this->settings->get( 'ux', 'address_format' )
            ],
            'filter_types' => [
                'values' => [
                    'dropdown'   => esc_html__( 'Dropdown', 'wp-store-locator' ),
                    'checkboxes' => esc_html__( 'Checkboxes', 'wp-store-locator' )
                ],
                'id'       => 'wpsl-cat-filter-types',
                'name'     => 'wpsl_search[category_filter_type]',
                'selected' => $this->settings->get( 'search', 'category_filter_type' )
            ],
            'preloader_color' => [
                'values' => [
                    'black'  => esc_html__( 'Black', 'wp-store-locator' ),
                    'white'  => esc_html__( 'White', 'wp-store-locator' ),
                    'custom' => esc_html__( 'Custom', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-preloader-color',
                'class'    => 'wpsl-has-conditional-option',
                'name'     => 'wpsl_appearance[search][preloader_color]',
                'selected' => wpsl_get_preloader_color()
            ],
            'result_columns' => [
                'values'   => apply_filters( 'wpsl_result_columns', [ 1 => '1', 2 => '2', 3 => '3' ] ),
                'id'       => 'wpsl-result-columns',
                'name'     => 'wpsl_appearance[search][result_columns]',
                'selected' => $this->settings->get( 'appearance', 'result_columns' )
            ],
            'filter_layout' => [
                'values' => [
                    'horizontal' => esc_html__( 'Horizontal row', 'wp-store-locator' ),
                    'stacked'    => esc_html__( 'Stacked', 'wp-store-locator' ),
                    'nested'     => esc_html__( 'Nested ( single Filters button )', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-filter-layout',
                'name'     => 'wpsl_appearance[search][filter_layout]',
                'selected' => $this->settings->get( 'appearance', 'filter_layout' )
            ],
            'cta_details_target' => [
                'values' => [
                    'website'      => esc_html__( 'Website', 'wp-store-locator' ),
                    'landing_page' => esc_html__( 'Local page', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-more-details-target',
                'name'     => 'wpsl_appearance[cta][details_target]',
                'selected' => $this->settings->get( 'appearance', 'cta.details_target' )
            ],
            'orderby' => [
                'values'   => $order_values,
                'id'       => 'wpsl-sort-by',
                'name'     => 'wpsl_search[orderby]',
                'selected' => $order_selected
            ],
            'order' => [
                'values' => [
                    'asc'  => esc_html__( 'Ascending (default)', 'wp-store-locator' ),
                    'desc' => esc_html__( 'Descending', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-sort-order',
                'name'     => 'wpsl_search[order]',
                'selected' => $this->settings->get( 'search', 'order' )
            ],
            'auto_locate_format' => [
                'values' => [
                    'zip'               => esc_html__( 'Zip code ( most accurate )', 'wp-store-locator' ),
                    'city'              => esc_html__( 'City / Town', 'wp-store-locator' ),
                    'formatted_address' => esc_html__( 'The full formatted address', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-auto-locate-format',
                'name'     => 'wpsl_search[auto_locate_format]',
                'selected' => $this->settings->get( 'search', 'auto_locate_format' )
            ],
            'auto_locate_trigger' => [
                'values' => [
                    'pageload'     => esc_html__( 'Runs automatically on pageload', 'wp-store-locator' ),
                    'user_request' => esc_html__( 'Request permission in a popup', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-auto-locate-trigger',
                'name'     => 'wpsl_search[auto_locate_trigger]',
                'selected' => $this->settings->get( 'search', 'auto_locate_trigger' )
            ],
            'map_services' => [
                'values'   => wpsl_get_map_services(),
                'id'       => 'wpsl-map-service',
                'name'     => 'wpsl_api[active_map_service]',
                'selected' => $this->settings->get( 'api', 'active_map_service' )
            ],
            'category_default' => [
                'values'   => wpsl_get_unique_categories(),
                'id'       => 'wpsl-category-default',
                'name'     => 'wpsl_search[category_default]',
                'default'  => $this->i18n->get_translation( 'category_default_label', esc_html__( 'Any', 'wp-store-locator' ) ),
                'selected' => $this->settings->get( 'search', 'category_default' )
            ],
            'gmap_styles' => [
                'values' => [
                    'cloud_based' => esc_html__( 'Cloud-based Maps Styling', 'wp-store-locator' ),
                    'json'        => esc_html__( 'JSON Styling', 'wp-store-locator' )
                ],
                'id'       => 'wpsl-gmaps-styles',
                'class'    => 'wpsl-no-flex-grow',
                'name'     => 'wpsl_appearance[map][gmaps_selected_style]',
                'selected' => $this->settings->get( 'appearance', 'map_style.gmaps.selected' )
            ],
            'gdpr' => [
                'values'   => $gdpr_values,
                'id'       => 'wpsl-gdpr-options',
                'name'     => 'wpsl_gdpr[handler]',
                'selected' => $gdpr_selected
            ],
            'gmaps_autocomplete_api_versions' => [
                /**
                 * Both options are always offered — which one works depends on
                 * when the site's API keys were created ( pre / post March 1,
                 * 2025 ), which the plugin cannot detect. The tooltip next to
                 * the dropdown explains which applies.
                 */
                'values' => [
                    'legacy' => esc_html__( 'Places Autocomplete Service (legacy)', 'wp-store-locator' ),
                    'latest' => esc_html__( 'Autocomplete Data API (new)', 'wp-store-locator' ),
                ],
                'id'       => 'wpsl-gmaps-autocomplete-api-versions',
                'name'     => 'wpsl_search[gmaps][autocomplete_api_version]',
                'selected' => $this->settings->get( 'search', 'api_versions.gmaps.autocomplete' )
            ],
            'font_size_base' => [
                'values'   => array_combine( range( 12, 20 ), range( 12, 20 ) ),
                'id'       => 'wpsl-font-size-base',
                'name'     => 'wpsl_appearance[font_sizes][base]',
                'selected' => $this->settings->get( 'appearance', 'font_sizes.base', 14 )
            ],
            'font_size_location_name' => [
                'values'   => array_combine( range( 12, 20 ), range( 12, 20 ) ),
                'id'       => 'wpsl-font-size-location-name',
                'name'     => 'wpsl_appearance[font_sizes][location_name]',
                'selected' => $this->settings->get( 'appearance', 'font_sizes.location_name', 14 )
            ],
            'font_size_cta_buttons' => [
                'values'   => array_combine( range( 12, 20 ), range( 12, 20 ) ),
                'id'       => 'wpsl-font-size-cta-buttons',
                'name'     => 'wpsl_appearance[font_sizes][cta_buttons]',
                'selected' => $this->settings->get( 'appearance', 'font_sizes.cta_buttons', 14 )
            ],
        ] );

        $css_class = ( isset( $dropdown_lists[$type]['class'] ) ? 'class="' . esc_attr( $dropdown_lists[$type]['class'] ) . '" ' : '' );
                    
        $extra = $extra_attrs ? ' ' . $extra_attrs : '';
        $dropdown = '<select id="' . esc_attr( $dropdown_lists[$type]['id'] ) . '" ' . $css_class .' name="' . esc_attr( $dropdown_lists[$type]['name'] ) . '" autocomplete="off"' . $extra . '>';
        
        if ( isset( $dropdown_lists[$type]['default'] ) ) {
            $dropdown .= "<option value='0'>" . esc_html( $dropdown_lists[$type]['default'] ) . "</option>";
        }

        foreach ( $dropdown_lists[$type]['values'] as $key => $value ) {
            $selected = ( $key == $dropdown_lists[$type]['selected'] ) ? 'selected="selected"' : '';
            $dropdown .= "<option value='" . esc_attr( $key ) . "' $selected>" . esc_html( $value ) . "</option>";
        }
        
        $dropdown .= '</select>';

        return $dropdown;
    }

    /**
     * Create a range slider for font size settings.
     *
     * @since  3.0.0
     * @param  string $type The slider type (font_size_base, font_size_location_name, font_size_cta_buttons)
     * @return string       The html output for the slider
     */
    public function create_slider( $type ) {
        $configs = [
            'font_size_base' => [
                'id'    => 'wpsl-font-size-base',
                'name'  => 'wpsl_appearance[font_sizes][base]',
                'value' => $this->settings->get( 'appearance', 'font_sizes.base', 14 ),
            ],
            'font_size_location_name' => [
                'id'    => 'wpsl-font-size-location-name',
                'name'  => 'wpsl_appearance[font_sizes][location_name]',
                'value' => $this->settings->get( 'appearance', 'font_sizes.location_name', 14 ),
            ],
            'font_size_cta_buttons' => [
                'id'    => 'wpsl-font-size-cta-buttons',
                'name'  => 'wpsl_appearance[font_sizes][cta_buttons]',
                'value' => $this->settings->get( 'appearance', 'font_sizes.cta_buttons', 14 ),
            ],
        ];

        if ( ! isset( $configs[ $type ] ) ) {
            return '';
        }

        $cfg   = $configs[ $type ];
        $value = intval( $cfg['value'] );
        $id    = esc_attr( $cfg['id'] );
        $name  = esc_attr( $cfg['name'] );

        return sprintf(
            '<input type="range" id="%s" name="%s" min="12" max="20" value="%d" class="wpsl-font-size-slider" oninput="this.nextElementSibling.textContent=this.value+\' px\'"><span class="wpsl-font-size-value">%d px</span>',
            $id, $name, $value, $value
        );
    }

    /**
     * Create a dropdown for the 12/24 opening hours format.
     * 
     * @since  2.0.0
     * @param  string $hour_format The hour format that should be set to selected
     * @return string $dropdown    The html for the dropdown
     */
    public function show_opening_hours_format( $hour_format = '' ) {
        $items = [
            '12' => esc_html__( '12 Hours', 'wp-store-locator' ),
            '24' => esc_html__( '24 Hours', 'wp-store-locator' )
        ];
        
        if ( ! absint( $hour_format ) ) {
            $hour_format = $this->settings->get( 'editor', 'hour_format' );
        } 
        
        $dropdown = '<select id="wpsl-editor-hour-format" name="wpsl_editor[hour_format]" autocomplete="off">';
        
        foreach ( $items as $key => $value ) {
            $selected = ( $hour_format == $key ) ? 'selected="selected"' : '';
            $dropdown .= "<option value='$key' $selected>" . esc_html( $value ) . "</option>";
        }
        
        $dropdown .= '</select>';
        
        return $dropdown;			
    }

    /**
     * Gets the multiselect list.
     *
     * @since  3.0.0
     * @param  string $type
     * @return string $multiselect
     */
    public function get_multiselect_list( $type ) {
        $config = $this->get_multiselect_config( $type );
        
        if ( empty( $config ) ) {
            return '';
        }
        
        $label = $this->build_multiselect_label( $type, $config );
        $button = $this->build_multiselect_button( $type, $config );
        $options = $this->build_multiselect_options( $config );
        
        $multiselect = $label;
        $multiselect .= '<div class="wpsl-multiselect-container">';
        $multiselect .= $button;
        $multiselect .= $options;
        $multiselect .= '</div>';
    
        return $multiselect;
    }

    /**
     * Get configuration for multiselect types.
     *
     * @since  3.0.0
     * @param  string $type
     * @return array
     */
    private function get_multiselect_config( $type ) {
        $configs = [
            'countries' => [
                'label_text' => __( 'Restrict the search results to one or more countries', 'wp-store-locator' ),
                'info_text' => '',
                'placeholder' => __( 'Select your country(s)', 'wp-store-locator' ),
                'data_source' => 'wpsl_get_regions',
                'selected_values' => $this->settings->get( 'api', 'multiple_regions' ),
                'field_name' => 'wpsl_api[multiple_regions][]',
            ],
            'hours' => [
                'label_text' => __( 'Location(s) of opening hours', 'wp-store-locator' ),
                'info_text' => '',
                // 'hours' is also the Section_Analyzer feature id this placement maps to.
                'warning_text' => $this->section_sync_warning( 'hours' ),
                'placeholder' => __( 'Select', 'wp-store-locator' ),
                'data_source' => 'static',
                'options' => wpsl_get_multiselect_ux_locations(),
                'selected_values' => $this->settings->get( 'ux', 'hours' ),
                'field_name' => 'wpsl_ux[hours][]'
            ],
            'description' => [
                'label_text' => __( 'Location(s) of the post content', 'wp-store-locator' ),
                'info_text' => '',
                'warning_text' => $this->section_sync_warning( 'description' ),
                'placeholder' => __( 'Select', 'wp-store-locator' ),
                'data_source' => 'static',
                'options' => wpsl_get_multiselect_ux_locations(),
                'selected_values' => $this->settings->get( 'ux', 'description' ),
                'field_name' => 'wpsl_ux[description][]'
            ],
            'contact_details' => [
                'label_text' => __( 'Location(s) of contact details', 'wp-store-locator' ),
                'info_text' => '',
                'warning_text' => $this->section_sync_warning( 'contact_details' ),
                'placeholder' => __( 'Select', 'wp-store-locator' ),
                'data_source' => 'static',
                'options' => wpsl_get_multiselect_ux_locations(),
                'selected_values' => $this->settings->get( 'ux', 'contact_details' ),
                'field_name' => 'wpsl_ux[contact_details][]'
            ]
        ];

        return isset( $configs[$type] ) ? $configs[$type] : [];
    }

    /**
     * Warning shown on an option that is specifically the reason the active
     * template's custom section is out of sync. Scoped per feature so unrelated
     * options don't light up.
     *
     * @since  3.0.0
     * @param  string|array $feature_ids The Section_Analyzer feature id(s) this option maps to.
     * @return string The warning text, or an empty string when this feature is in sync.
     */
    private function section_sync_warning( $feature_ids ) {
        $template_id = $this->settings->get( 'appearance', 'template_id' );
        $analyzer    = wpsl_get_service( 'section_analyzer' );
        $matched     = [];

        foreach ( (array) $feature_ids as $feature_id ) {
            foreach ( [ $feature_id, $feature_id . '_more_info' ] as $id ) {
                if ( $analyzer->has_out_of_sync_feature( $template_id, $id ) ) {
                    $matched[] = $id;
                }
            }

            if ( in_array( 'more_info', (array) $this->settings->get( 'ux', $feature_id ), true )
                && $analyzer->has_out_of_sync_feature( $template_id, 'more_info' ) ) {
                $matched[] = 'more_info';
            }
        }

        if ( empty( $matched ) ) {
            return '';
        }

        /**
         * Name the languages when there are several stored sections: the
         * editor only syncs the language it has loaded, so "out of sync"
         * without saying where sends people to a section that already
         * looks correct. Scoped to the features this option actually
         * caused, so an unrelated mismatch elsewhere doesn't widen the
         * list.
         */
        $languages = [];

        foreach ( array_unique( $matched ) as $id ) {
            foreach ( $analyzer->get_out_of_sync_language_names( $template_id, $id ) as $name ) {
                if ( ! in_array( $name, $languages, true ) ) {
                    $languages[] = $name;
                }
            }
        }

        $where = '';

        if ( ! empty( $languages ) ) {
            $where = ' ' . sprintf(
                /* translators: %s: comma separated list of language names */
                _n(
                    'Out of sync in %s.',
                    'Out of sync in these languages: %s.',
                    count( $languages ),
                    'wp-store-locator'
                ),
                implode( ', ', $languages )
            );
        }

        $message = sprintf(
            /* translators: %s: sentence naming the out-of-sync languages, or empty */
            __( 'A customized template section is active for this theme. Changes to this option won\'t appear in the search results until you update the custom section.%s', 'wp-store-locator' ),
            $where
        );

        // The link only for someone the section editor is open to.
        if ( Section_Editor::current_user_can_edit() ) {
            $message .= ' <a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings&wpsl-compare=1#wpsl-section-editor' ) ) . '">' . __( 'Review differences', 'wp-store-locator' ) . '</a>';
        }

        return $message;
    }

    /**
     * The out-of-sync warning marker for an option, as the same
     * tooltip span the rest of the settings page uses.
     *
     * @since 3.0.0
     * @param  string|array $feature_ids The Section_Analyzer feature id(s) this option maps to.
     * @return string The warning span, or an empty string when in sync.
     */
    public function section_sync_warning_span( $feature_ids ) {
        $warning = $this->section_sync_warning( $feature_ids );

        if ( '' === $warning ) {
            return '';
        }

        return '<span class="wpsl-info wpsl-warning" tabindex="0" role="button" aria-label="'
            . esc_attr__( 'More information', 'wp-store-locator' ) . '">'
            . '<span class="wpsl-info-text wpsl-hide">' . wp_kses_post( $warning ) . '</span>'
            . '</span>';
    }

    /**
     * Build multiselect label.
     *
     * @since  3.0.0
     * @param  string $type
     * @param  array  $config
     * @return string
     */
    private function build_multiselect_label( $type, $config ) {
        $label = '<label for="wpsl-multiselect-' . esc_attr( $type ) . '">';
        $label .= esc_html( $config['label_text'] );

        if ( $config['info_text'] ) {
            $label .= '<span class="wpsl-info">';
            $label .= '<span class="wpsl-info-text wpsl-hide">';
            $label .= wp_kses_post( $config['info_text'] );
            $label .= '</span>';
            $label .= '</span>';
        }

        if ( ! empty( $config['warning_text'] ) ) {
            $label .= '<span class="wpsl-info wpsl-warning" tabindex="0" role="button" aria-label="'
                . esc_attr__( 'More information', 'wp-store-locator' ) . '">';
            $label .= '<span class="wpsl-info-text wpsl-hide">';
            $label .= wp_kses_post( $config['warning_text'] );
            $label .= '</span>';
            $label .= '</span>';
        }

        $label .= '</label>';
        
        return $label;
    }

    /**
     * Build multiselect button.
     *
     * @since  3.0.0
     * @param  string $type
     * @param  array  $config
     * @return string
     */
    private function build_multiselect_button( $type, $config ) {
        $button = '<button type="button" id="wpsl-multiselect-' . esc_attr( $type ) . '" data-placeholder="' . esc_attr( $config['placeholder'] ) . '">';
        $button .= '<div class="wpsl-multiselect-content">';
        
        // Show tags if we have selected values
        if ( ! empty( $config['selected_values'] ) && is_array( $config['selected_values'] ) ) {
            $button .= $this->build_multiselect_tags( $config );
        } else {
            $button .= '<span class="wpsl-multiselect-placeholder">' . esc_html( $config['placeholder'] ) . '</span>';
        }
        
        $button .= '</div>';
        $button .= '</button>';
        
        return $button;
    }

    /**
     * Build multiselect tags for selected values.
     *
     * @since  3.0.0
     * @param  array $config
     * @return string
     */
    private function build_multiselect_tags( $config ) {
        $tags = '';
        
        // Get data source based on type
        if ( $config['data_source'] === 'static' ) {
            $data_source = $config['options'];
        } else {
            $data_source = call_user_func( $config['data_source'] );
        }
        
        foreach ( $config['selected_values'] as $selected_value ) {
            $selected_label = '';
            
            // For static data sources, the key is the value and value is the label
            if ( $config['data_source'] === 'static' ) {
                $selected_label = isset( $data_source[$selected_value] ) ? $data_source[$selected_value] : $selected_value;
            } else {
                // For dynamic data sources, find the key where value matches
                foreach ( $data_source as $option_key => $option_value ) {
                    if ( $option_value === $selected_value ) {
                        $selected_label = $option_key;
                        break;
                    }
                }
            }
            
            if ( ! empty( $selected_label ) ) {
                $tags .= '<div class="wpsl-multiselect-tag">';
                $tags .= '<span class="wpsl-multiselect-tag-text">' . esc_html( $selected_label ) . '</span>';
                $tags .= '<span class="wpsl-multiselect-tag-remove" data-value="' . esc_attr( $selected_value ) . '">';
                $tags .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
                $tags .= '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>';
                $tags .= '</svg>';
                $tags .= '</span>';
                $tags .= '</div>';
            }
        }
        
        return $tags;
    }

    /**
     * Build multiselect options.
     *
     * @since  3.0.0
     * @param  array  $config
     * @return string
     */
    private function build_multiselect_options( $config ) {
        $options = '<ul class="wpsl-multiselect-menu">';
        
        if ( $config['data_source'] === 'static' ) {
            $options .= $this->build_static_options( $config );
        } else {
            $options .= $this->build_dynamic_options( $config );
        }
        
        $options .= '</ul>';
        
        return $options;
    }

    /**
     * Build static options for multiselect.
     *
     * @since  3.0.0
     * @param  array $config
     * @return string
     */
    private function build_static_options( $config ) {
        $options = '';
        
        foreach ( $config['options'] as $value => $label ) {
            $selected = '';

            if ( is_array( $config['selected_values'] ) && in_array( $value, $config['selected_values'] ) ) {
                $selected = 'checked';
            }
            
            $options .= '<li>';
            $options .= '<label>';
            $options .= '<input type="checkbox" ' . $selected . ' value="' . esc_attr( $value ) . '" name="' . esc_attr( $config['field_name'] ) . '" data-label="' . esc_attr( wpsl_underscore_to_readable( $value ) ) . '">';
            $options .= '<span>' . esc_html( $label ) . '</span>';
            $options .= '</label>';
            $options .= '</li>';
        }
        
        return $options;
    }

    /**
     * Build dynamic options for multiselect.
     *
     * @since  3.0.0
     * @param  array $config
     * @return string
     */
    private function build_dynamic_options( $config ) {
        $options = '';
        $data_source = call_user_func( $config['data_source'] );
        
        if ( empty( $data_source ) || ! is_array( $data_source ) ) {
            return $options;
        }
        
        foreach ( $data_source as $option_key => $option_value ) {

            /**
             * Skip placeholder entries with empty values.
             * 
             * This prevent 'Select your region' 
             * from being a selectable option.
             */
            if ( $option_value === '' ) {
                continue;
            }

            $selected = '';
            
            if ( is_array( $config['selected_values'] ) && in_array( $option_value, $config['selected_values'] ) ) {
                $selected = 'checked';
            }
            
            $options .= '<li>';
            $options .= '<label>';
            $options .= '<input type="checkbox" ' . $selected . ' value="' . esc_attr( $option_value ) . '" name="' . esc_attr( $config['field_name'] ) . '" data-label="' . esc_attr( wpsl_underscore_to_readable( $option_key ) ) . '">';
            $options .= '<span>' . esc_html( wpsl_underscore_to_readable( $option_key ) ) . '</span>';
            $options .= '</label>';
            $options .= '</li>';
        }
        
        return $options;
    }

    /**
     * Get autocomplete info text based on the active map provider.
     *
     * @since  3.0.0
     * @return string The info text HTML
     */
    public function get_autocomplete_info_text() {
        $active_provider = $this->settings->get( 'api', 'active_map_service' );
        
        switch ( $active_provider ) {
            case 'gmaps':
                return sprintf(
                    /* translators: %1$s: line breaks, %2$s: opening link tag to filter documentation, %3$s: closing link tag, %4$s: line breaks, %5$s: opening link tag to read more, %6$s: closing link tag */
                    __( 'You can restrict the returned suggestions to multiple countries (up to 15). %1$s Any other customizations can by done with %2$sthis filter%3$s. %4$s %5$sRead more%6$s', 'wp-store-locator' ), 
                    '<br><br>',
                    '<a target="_blank" href="https://wpstorelocator.co/document/wpsl_autocomplete_options/">',
                    '</a>',
                    '<br><br>',
                    '<a href="#">',
                    '</a>'
                );
            case 'mapbox':
                return sprintf(
                    /* translators: %1$s: opening link tag to API section, %2$s: closing link tag */
                    __( 'The autocomplete results are automatically restricted to the selected countries in the %1$sAPI section%2$s.', 'wp-store-locator' ), 
                    '<a href="#" class="wpsl-trigger-nav" data-item="api">',
                    '</a>',
                );
                
            case 'osm':
            default:
                return '';
        }
    }
}