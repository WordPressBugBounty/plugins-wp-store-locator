<?php
/**
 * Template management and customization.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Appearance;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations as I18n;

class Manager {

    /**
     * Settings manager instance
     *
     * @var \WPSL\Core\Settings\Manager
     */
    protected $settings;

    /**
     * Stores all plugin settings.
     *
     * @var array
     */
    protected $wpsl_settings;

    /**
     * I18n translation instance
     *
     * @var \WPSL\Core\I18n\Translations
     */
    protected $i18n;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager  $settings Settings manager instance
     * @param \WPSL\Core\I18n\Translations $i18n     Translations instance
     */
    public function __construct( WpslSettings $settings, I18n $i18n ) {
        $this->settings = $settings;
        $this->wpsl_settings = $settings->get_all();
        $this->i18n = $i18n;
        
        add_action( 'wp_ajax_wpsl_activate_template', [ $this, 'activate_template' ] );
        add_action( 'wp_ajax_wpsl_save_appearance', [ $this, 'save_appearance' ] );
        add_action( 'admin_post_wpsl_save_appearance', [ $this, 'save_appearance' ] );
    }

    /**
     * Activate a template.
     *
     * @since 3.0.0
     */
    public function activate_template() {
        if ( ! isset( $_POST['wpsl_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_template_nonce'] ) ), 'wpsl-activate-template' ) ) {
            return wp_send_json_error( __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return;
        }

        $templates = \wpsl_get_templates();
        $active_template = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';

        /**
         * Make sure the passed value
         * exists as an ID in the existing
         * template list.
         */
        $result = array_filter( $templates, function( $row ) use ( $active_template ) {
            return $row['id'] == $active_template;
        });

        if ( $result ) {
            $this->wpsl_settings['appearance']['template_id'] = $active_template;

            // Update the option in the database
            $this->settings->update( 'appearance', $this->wpsl_settings['appearance'] );

            $template = $this->get_template( $active_template );

            wp_send_json_success( [
                'template'    => $template,
                'has_preview' => $this->has_template_preview( $active_template ),
            ] );
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Save appearance settings — handles both AJAX (save button) and normal form submit (activate button).
     *
     * @since 3.0.0
     */
    public function save_appearance() {
        $is_ajax = wp_doing_ajax();

        if ( ! isset( $_POST['wpsl_save_appearance_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_save_appearance_nonce'] ) ), 'wpsl-save-appearance' ) ) {
            if ( $is_ajax ) {
                return wp_send_json_error( [ 'message' => __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) ] );
            }
            wp_safe_redirect( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance' ) );
            exit;
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            if ( $is_ajax ) {
                return wp_send_json_error( [ 'message' => __( 'You do not have permission to save settings.', 'wp-store-locator' ) ] );
            }
            wp_safe_redirect( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance' ) );
            exit;
        }

        $appearance_data = isset( $_POST['wpsl_appearance'] ) ? wp_unslash( $_POST['wpsl_appearance'] ) : [];

        // For form submits: check if a template should be activated.
        $template_to_activate = '';
        if ( ! $is_ajax ) {
            $raw_id = isset( $_POST['wpsl_activate_template'] ) ? sanitize_text_field( wp_unslash( $_POST['wpsl_activate_template'] ) ) : '';
            if ( $raw_id ) {
                $templates = \wpsl_get_templates();
                $valid     = array_filter( $templates, function( $row ) use ( $raw_id ) {
                    return $row['id'] == $raw_id;
                });
                if ( $valid ) {
                    $template_to_activate = $raw_id;
                }
            }
        }

        if ( ! empty( $appearance_data ) ) {
            $sanitizer = wpsl_get_service( 'sanitizer' );
            if ( ! $sanitizer || ! method_exists( $sanitizer, 'appearance' ) ) {
                if ( $is_ajax ) {
                    return wp_send_json_error( [ 'message' => __( 'Settings sanitizer not available.', 'wp-store-locator' ) ] );
                }
            } else {
                $sanitized_data = $sanitizer->appearance( $appearance_data );
                // Merge with stored settings so fields not in the submitted data (e.g. template_id) are preserved.
                $sanitized_data = array_merge( $this->wpsl_settings['appearance'] ?? [], $sanitized_data );
                if ( $template_to_activate ) {
                    $sanitized_data['template_id'] = $template_to_activate;
                }
                $this->settings->update( 'appearance', $sanitized_data );
            }
        } elseif ( $is_ajax ) {
            return wp_send_json_error( [ 'message' => __( 'No appearance data received.', 'wp-store-locator' ) ] );
        } elseif ( $template_to_activate ) {
            // No appearance fields submitted but a template activation was requested.
            $this->wpsl_settings['appearance']['template_id'] = $template_to_activate;
            $this->settings->update( 'appearance', $this->wpsl_settings['appearance'] );
        }

        if ( $is_ajax ) {
            wp_send_json_success( [ 'message' => __( 'Settings saved.', 'wp-store-locator' ) ] );
            return;
        }

        wp_safe_redirect( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance' ) );
        exit;
    }

    /**
     * Return the HTML for the requested template id.
     *
     * @since  3.0.0
     * @param  string $template_id The name of the template to load
     * @return string $template    The HTML structure of template.
     */
    public function get_template( $template_id ) {
        $template_path = WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/customize/' . $template_id . '.php';

        if ( ! file_exists( $template_path ) ) {
            $template_path = WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/customize/custom.php';
        }

        if ( file_exists( $template_path ) ) {
            $template = str_replace( '{{map_provider}}', $this->wpsl_settings['api']['active_map_service'], include( $template_path ) );
        } else {
            $template = '';
        }

        return $template;
    }

    /**
     * Check whether a template has its own live preview
     * (i.e. a dedicated customize PHP file, not the generic fallback).
     *
     * @since  3.0.0
     * @param  string $template_id
     * @return bool
     */
    public function has_template_preview( $template_id ) {
        $specific_path = WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/customize/' . $template_id . '.php';
        $fallback_path = WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/customize/custom.php';
        return file_exists( $specific_path ) && ( $specific_path !== $fallback_path );
    }

    /**
     * Create a list of the
     * available template styles.
     *
     * @since  3.0.0
     * @param  bool $show_customize Whether to render the customize button for the active template. Default false.
     * @return void
     */
    public function template_styles_list( $show_customize = false ) {
        $template_defaults = [
            'desc'           => esc_html__( 'Custom template', 'wp-store-locator' ),
            'customize_path' => '',
            'placeholder'    => WPSL_URL . 'assets/img/admin/themes/custom.svg',
            'supports'       => ''
        ];

        $templates = \wpsl_get_templates();
        foreach ( $templates as $i => $template ) {
            // Make sure that the required fields contain data.
            $template = wp_parse_args( $template, $template_defaults );

            $checked = checked( $this->wpsl_settings['appearance']['template_id'], $template['id'], false );

            if ( $checked ) {
                $selected_class = 'wpsl-selected-template';
            } else {
                $selected_class = '';
            }

            $custom_class = $this->has_template_preview( $template['id'] ) ? '' : 'wpsl-custom-theme';

            $classes = trim( implode( ' ', array_filter( [ 'wpsl-template-style-option', $selected_class, $custom_class ] ) ) );

            echo '<div class="' . $classes . '" data-id="' . esc_attr( $template['id'] ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
            echo '<div class="wpsl-template-img-wrap">';
            echo '<img src="' . esc_attr( $template['placeholder'] )  . '" alt="' . esc_attr( \wpsl_create_alt_text( $template['name'] ) ) . ' ' . esc_html__( 'template', 'wp-store-locator' ) . '" width="115" height="115">';
            echo '</div>';
            echo '<div class="wpsl-template-details">';
            echo '<div class="wpsl-template-header">';
            echo '<strong>' . esc_html( $template['name'] ) . '</strong>';
            $aria_checked = $checked ? 'true' : 'false';
            $toggle_id    = 'wpsl-template-toggle-' . esc_attr( $template['id'] );

            echo '<div class="wpsl-template-header-actions">';

            // Flag custom templates still written in v2 code: they keep working
            // through the backward compatibility layer, but should be updated to v3.
            if ( \wpsl_is_custom_template( $template ) && isset( $template['path'] ) && \wpsl_template_uses_legacy_markers( $template['path'] ) ) {
                echo $this->get_legacy_template_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output
            }

            if ( $show_customize && $checked ) {
                echo '<button type="button" class="button wpsl-customize-btn" aria-label="' . esc_attr__( 'Customize', 'wp-store-locator' ) . '"><i class="wpsl-icon-editor"></i></button>';
            }

            echo '<label class="wpsl-toggle-wrap">';
            echo '<input type="radio" id="' . $toggle_id . '" name="wpsl_activate_template" value="' . esc_attr( $template['id'] ) . '"' . ( $checked ? ' checked="checked"' : '' ) . ' style="display:none;">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
            echo '<div class="wpsl-toggler-slider" tabindex="0" role="switch" aria-checked="' . $aria_checked . '"><div class="wpsl-toggler-knob"></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
            echo '</label>';

            echo '</div>';
            echo '</div>';
            echo '<p>' . esc_html( $template['desc'] ) . '</p>';

            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Build the notice shown next to a custom template written in v2 code.
     *
     * These templates are detected automatically and kept working through the
     * backward compatibility layer, so the notice is purely informational: it
     * tells the user the template is running in compatibility mode and points
     * them to the docs for updating it to the v3 structure.
     *
     * @since  3.0.0
     * @return string The info icon + tooltip markup.
     */
    private function get_legacy_template_notice() {
        $docs_url = 'https://wpstorelocator.co/document/load-custom-store-locator-template/#legacy-mode';
        $link     = '<a href="' . esc_url( $docs_url ) . '" target="_blank">';

        $message = sprintf(
            /* translators: %1$s: line breaks, %2$s: opening link tag to documentation, %3$s: closing link tag */
            __( 'This custom template uses v2 code and is running through the backward compatibility layer.%1$sPlease update your template to the v3 structure to ensure full compatibility with all current and future features.%1$s%2$sRead more%3$s', 'wp-store-locator' ),
            '<br><br>', $link, '</a>'
        );

        return '<span class="wpsl-info wpsl-warning">'
             . '<span class="wpsl-info-text wpsl-hide">' . wp_kses_post( $message ) . '</span>'
             . '</span>';
    }

    /**
     * Get the customization sections for the theme style editor.
     * 
     * @since  3.0.0
     * @return array The customization sections array
     */
    public function get_customize_sections() {
        $customize_texts = [
            'background' => esc_html__( 'Background', 'wp-store-locator' ),
            'border'     => esc_html__( 'Border', 'wp-store-locator' ),
            'hover'      => esc_html__( 'Hover', 'wp-store-locator' ),
            'text'       => esc_html__( 'Text', 'wp-store-locator' ),
            'icon'       => esc_html__( 'Icon', 'wp-store-locator' ),
            'link'       => esc_html__( 'Link', 'wp-store-locator' )
        ];

        // Common properties
        $common_props = [
            'basic' => [
                'background' => $customize_texts['background'],
                'text' => $customize_texts['text'],
            ],
            'with_hover' => [
                'background' => $customize_texts['background'],
                'background-hover' => $customize_texts['background'] . ' ' . lcfirst( $customize_texts['hover'] ),
                'text' => $customize_texts['text'],
                'text-hover' => $customize_texts['text'] . ' ' . lcfirst( $customize_texts['hover'] ),
            ],
            'with_border' => [
                'border' => $customize_texts['border'],
                'border-hover' => $customize_texts['border'] . ' ' . lcfirst( $customize_texts['hover'] ),
            ],
            'with_icon' => [
                'icon' => $customize_texts['icon'],
                'icon-hover'=> $customize_texts['icon'] . ' ' . lcfirst( $customize_texts['hover'] ),
            ],
            'with_link' => [
                'link' => $customize_texts['link'],
                'link-hover'=> $customize_texts['link'] . ' ' . lcfirst( $customize_texts['hover'] ),
            ]
        ];

        // Merge property sets
        $merge_props = function( $sets ) use ( $common_props ) {
            $result = [];

            foreach ( $sets as $set ) {
                if ( isset( $common_props[$set] ) ) {
                    $result = array_merge( $result, $common_props[$set] );
                }
            }
            
            return $result;
        };

        $customize_sections = [
            'header' => [
                'container' => $common_props['basic'],
                'reset' => array_merge(
                    [
                        'background' => $customize_texts['background'],
                        'background-hover' => $customize_texts['background'] . ' ' . lcfirst( $customize_texts['hover'] ),
                    ],
                    $merge_props( ['with_border'] ),
                    $merge_props( ['with_icon'] )
                ),
                'submit' => [
                    'background-start' => esc_html__( 'Background start', 'wp-store-locator' ),
                    'background-end' => esc_html__( 'Background end', 'wp-store-locator' ),
                    'border' => $customize_texts['border'],
                    'text' => $customize_texts['text'],
                    'background-start-hover' => esc_html__( 'Background start hover', 'wp-store-locator' ),
                    'background-end-hover' => esc_html__( 'Background end hover', 'wp-store-locator' ),
                    'border-hover' => $customize_texts['border'] . ' ' . lcfirst( $customize_texts['hover'] ),
                    'text-hover' => $customize_texts['text'] . ' ' . lcfirst( $customize_texts['hover'] ),
                ],
                'input' => array_merge(
                    $merge_props( ['with_hover'] ),
                    $merge_props( ['with_border'] )
                ),
                'dropdown' => array_merge(
                    $merge_props( ['with_hover'] ),
                    $merge_props( ['with_border'] ),
                    [
                        'background-expanded' => esc_html__( 'Background expanded', 'wp-store-locator' ),
                        'item-text' => esc_html__( 'List item text', 'wp-store-locator' ),
                        'item-background-hover' => esc_html__( 'List item background hover', 'wp-store-locator' ),
                        'item-text-hover' => esc_html__( 'List item text hover', 'wp-store-locator' ),
                        'item-background-selected' => esc_html__( 'Background selected item ', 'wp-store-locator' ),
                        'item-text-selected' => esc_html__( 'Text selected item', 'wp-store-locator' ),
                        'reset-background' => esc_html__( 'Reset background', 'wp-store-locator' ),
                        'reset-background-hover' => esc_html__( 'Reset background hover', 'wp-store-locator' ),
                        'reset-border' => esc_html__( 'Reset border', 'wp-store-locator' ),
                        'reset-border-hover' => esc_html__( 'Reset border hover', 'wp-store-locator' ),
                        'reset-icon' => esc_html__( 'Reset icon', 'wp-store-locator' ),
                        'reset-icon-hover' => esc_html__( 'Reset icon hover', 'wp-store-locator' ),
                    ]
                ),
            ],
            'listing' => [
                'results' => array_merge(
                    $common_props['basic'],
                    $merge_props( ['with_link'] )
                ),
                'icons' => [
                    'color' => $customize_texts['icon'],
                ],
            ],
            'popup' => [
                'content' => array_merge(
                    $common_props['basic'],
                    $merge_props( ['with_link'] ),
                    [
                        'close' => esc_html__( 'Close', 'wp-store-locator' ),
                        'close-hover' => esc_html__( 'Close hover', 'wp-store-locator' ),
                    ]
                ),
                'icons' => [
                    'color' => $customize_texts['icon'],
                ],
            ],
        ];

        return $customize_sections;
    }

    /**
     * Get inline CSS variables for the template preview.
     *
     * @since  3.0.0
     * @return string Inline style attribute with CSS variables
     */
    public function get_template_inline_styles() {
        $inline_vars = [];
        
        // Dimension CSS variables
        $dimensions = isset( $this->wpsl_settings['appearance']['dimensions'] ) ? $this->wpsl_settings['appearance']['dimensions'] : [];
        $template_id = $this->settings->get( 'appearance', 'template_id' );
        $active_template = wpsl_get_active_template();
        $has_panel = isset( $active_template['has_panel'] ) && $active_template['has_panel'];
        $category_filter_enabled = $this->settings->get( 'search', 'category_filter' );
        
        // Non-panel templates: search width and label width
        if ( ! $has_panel ) {
            // Search width
            $search_width_mode = isset( $dimensions['search_width_mode'] ) ? $dimensions['search_width_mode'] : 'custom';
            $search_width = isset( $dimensions['search_width'] ) ? absint( $dimensions['search_width'] ) : 179;
            $search_width_value = ( $search_width_mode === 'default' ) ? 'auto' : $search_width . 'px';
            $inline_vars[] = '--wpsl-search-input-width: ' . $search_width_value;
        }
        
        /*
         * Built through the same helper the frontend CSS uses, so the
         * preview cannot show a height visitors do not get. The preview always
         * shows a number, where the frontend leaves an untouched default
         * template to the stylesheet - hence the 350 fallback here.
         */
        if ( $template_id === 'horizontal' ) {
            $heights = wpsl_dimension_heights( $dimensions, 'horizontal' );

            $inline_vars[] = '--wpsl-map-height: ' . $heights['map_height'] . 'px';
            $inline_vars[] = '--wpsl-results-height: ' . $heights['results_height'] . 'px';
        } elseif ( $template_id === 'vertical' ) {
            $heights = wpsl_dimension_heights( $dimensions, 'vertical' );

            $inline_vars[] = '--wpsl-container-height: ' . $heights['sl_height'] . 'px';
        } else {
            // The default template gives the map and the results one height.
            $heights = wpsl_dimension_heights( $dimensions, 'default' );

            $inline_vars[] = '--wpsl-map-height: ' . ( $heights['combined_height'] ? $heights['combined_height'] : 350 ) . 'px';
        }
        
        if ( ! empty( $inline_vars ) ) {
            return ' style="' . esc_attr( implode( '; ', $inline_vars ) ) . ';"';
        }
        
        return '';
    }

    /**
     * Get CSS classes for the template 
     * previews based on appearance settings.
     *
     * @since  3.0.0
     * @return string CSS classes string
     */
    public function get_template_classes() {
        $classes = [];

        $search_settings = $this->wpsl_settings['search'];
        
        // Add icons class if enabled
        if ( ! empty( $this->wpsl_settings['appearance']['icons']['enabled'] ) ) {
            $classes[] = 'wpsl-has-icons';
        }

        // Add CTA classes if enabled
        if ( ! empty( $this->wpsl_settings['appearance']['cta']['enabled'] ) ) {
            $classes[] = 'wpsl-styled-cta';
        }

        if ( ! empty( $this->wpsl_settings['appearance']['cta']['details'] ) ) {
            $classes[] = 'wpsl-cta-details';
        }

        // Build active filters array
        $filter_map = [
            'radius_dropdown'      => 'radius',
            'results_dropdown'     => 'max_results',
            'category_filter'      => 'category',
            'category_filter_only' => 'category_only_filter',
        ];
    
        $active_filters = [];
    
        foreach ( $filter_map as $setting_key => $filter_name ) {
            if ( ! empty( $search_settings[ $setting_key ] ) ) {
                $active_filters[] = $filter_name;
            }
        }
    
        // Check if all keys in $needles are present in $haystack
        $has_all = function( $needles, $haystack ) {
            return count( array_intersect( $needles, $haystack ) ) === count( $needles );
        };
    
        // Logic for wpsl-no-fixed-label
        $cat_checkboxes = ! empty( $search_settings['category_filter'] ) && ( $search_settings['category_filter_type'] === 'checkboxes' );
        $only_radius_max = $has_all( ['radius', 'max_results'], $active_filters ) && count( $active_filters ) === 2;
        $only_radius_or_max = ( count( $active_filters ) === 1 ) && in_array( $active_filters[0], ['radius', 'max_results'], true );
    
        if ( empty( $active_filters ) || $only_radius_max || $only_radius_or_max || $cat_checkboxes ) {
            $classes[] = 'wpsl-no-fixed-label';
        }
    
        // Logic for wpsl-no-bottom-margin and wpsl-cat-filter
        if ( $cat_checkboxes ) {
            $classes[] = 'wpsl-no-bottom-margin';
            $classes[] = 'wpsl-checkboxes-enabled';
        } elseif ( ! empty( $search_settings['category_filter_only'] ) || ( $has_all( ['category', 'countries'], $active_filters ) && count( $active_filters ) === 2 ) ) {
            $classes[] = 'wpsl-cat-filter';
        } else {
            // Valid combinations for wpsl-no-bottom-margin
            $valid_combinations = [
                ['radius' => false, 'max_results' => true,  'category' => false], // max_results only
                ['radius' => true,  'max_results' => true,  'category' => false], // radius and max_results
                ['radius' => true,  'max_results' => false, 'category' => false], // radius only
                ['radius' => false, 'max_results' => false, 'category' => false], // no filter
            ];
    
            foreach ( $valid_combinations as $combo ) {
                $matches = true;
    
                foreach ( $combo as $filter => $should_be_active ) {
                    if ( ( $should_be_active && ! in_array( $filter, $active_filters ) ) || ( ! $should_be_active && in_array( $filter, $active_filters ) ) ) {
                        $matches = false;
                        break;
                    }
                }
    
                if ( $matches ) {
                    $classes[] = 'wpsl-no-bottom-margin';
                    break;
                }
            }
    
            // Additional combinations for both classes
            if ( ! in_array( 'wpsl-no-bottom-margin', $classes ) ) {
                $category_filter_combinations = [
                    ['radius' => true,  'max_results' => false, 'category' => true],
                    ['radius' => false, 'max_results' => true,  'category' => true],
                ];
    
                foreach ( $category_filter_combinations as $combo ) {
                    $matches = true;
    
                    foreach ( $combo as $filter => $should_be_active ) {
                        if ( ( $should_be_active && ! in_array( $filter, $active_filters ) ) || ( ! $should_be_active && in_array( $filter, $active_filters ) ) ) {
                            $matches = false;
    
                            break;
                        }
                    }
    
                    if ( $matches ) {
                        $classes[] = 'wpsl-no-bottom-margin';
                        $classes[] = 'wpsl-cat-filter';
    
                        break;
                    }
                }
            }
        }
    
        return implode( ' ', $classes );
    }

    /**
     * Get location details for example data.
     *
     * @since  3.0.0
     * @return array Location details for each map service
     */
    private function get_location_details() {
        $details = [
            'osm' => [
                'name' => 'Openstreetmap Foundation',
                'street' => 'Cowley Road',
                'city' => 'Cambridge',
                'country' => 'United Kingdom',
                'phone' => '123456',
                'email' => 'email@domain.com',
                'latlng' => '52.2325929, 0.1521742',
            ],
            'mapbox' => [
                'name' => 'Mapbox',
                'street' => '1133 15th Street, N.W., Suite 825',
                'city' => 'Washington, DC 20005',
                'country' => 'United States',
                'phone' => '123456',
                'email' => 'email@domain.com',
                'latlng' => '38.9048605, -77.036569',
            ],
            'gmaps' => [
                'name' => 'Googleplex',
                'street' => '1600 Amphitheatre Parkway',
                'city' => 'Mountain View',
                'country' => 'United States',
                'phone' => '123456',
                'email' => 'email@domain.com',
                'latlng' => '37.422567, -122.084084',
            ],
            'stadia' => [],
        ];
        
        return $details;
    }

    /**
     * Get location example data for the template editor.
     * 
     * Returns a single location item HTML and latlng coordinates.
     * Use get_column_location_list() to get the complete list with proper repetition.
     *
     * @since  3.0.0
     * @param  string $template_type Template type: 'listing' or 'popup'. Default 'listing'.
     * @return array Array with 'html' (single location item) and 'latlng' keys
     */
    public function location_examples( $template_type = 'listing' ) {
        $map_service = $this->wpsl_settings['api']['active_map_service'];
        $locations_details = $this->get_location_details();

        // Stadia uses the same example data as OSM
        if ( ! isset( $locations_details[ $map_service ] ) || empty( $locations_details[ $map_service ] ) ) {
            $map_service = 'osm';
        }

        $cta_directions_enabled = isset( $this->wpsl_settings['appearance']['cta']['enabled'] ) ? $this->wpsl_settings['appearance']['cta']['enabled'] : false;
        $cta_details_enabled = isset( $this->wpsl_settings['appearance']['cta']['details'] ) ? $this->wpsl_settings['appearance']['cta']['details'] : false;
        $icons_enabled = isset( $this->wpsl_settings['appearance']['icons']['enabled'] ) ? $this->wpsl_settings['appearance']['icons']['enabled'] : false;
        $marker_zoom_to = isset( $this->wpsl_settings['ux']['marker_zoom_to'] ) ? $this->wpsl_settings['ux']['marker_zoom_to'] : false;
        
        // Get button style settings (primary or secondary)
        $details_style = isset( $this->wpsl_settings['appearance']['button_styles']['more_details'] ) ? $this->wpsl_settings['appearance']['button_styles']['more_details'] : 'primary';
        $directions_style = isset( $this->wpsl_settings['appearance']['button_styles']['directions'] ) ? $this->wpsl_settings['appearance']['button_styles']['directions'] : 'secondary';
        $zoom_here_style = isset( $this->wpsl_settings['appearance']['button_styles']['zoom_here'] ) ? $this->wpsl_settings['appearance']['button_styles']['zoom_here'] : 'secondary';
        
        // Build button classes - only apply styling when CTA buttons are enabled
        $cta_directions_class = $cta_directions_enabled ? 'wpsl-styled-btn wpsl-' . $directions_style . '-btn' : '';
        $cta_details_class = $cta_directions_enabled ? 'wpsl-styled-btn wpsl-' . $details_style . '-btn' : '';
        $cta_zoom_here_class = $cta_directions_enabled ? 'wpsl-styled-btn wpsl-' . $zoom_here_style . '-btn' : '';
        
        $directions_url = '<a class="wpsl-directions '. $cta_directions_class . '" href="#">' . esc_html( $this->i18n->get_translation( 'directions_label', esc_html__( 'Directions', 'wp-store-locator' ) ) ) . '</a>';
        $details_url = '<a class="wpsl-details '. $cta_details_class . '" href="#">' . esc_html( $this->i18n->get_translation( 'more_details_label', esc_html__( 'More details', 'wp-store-locator' ) ) ) . '</a>';
        $zoom_here_url = '<a class="wpsl-zoom-here '. $cta_zoom_here_class . '" href="#">' . esc_html( $this->i18n->get_translation( 'zoom_here_label', esc_html__( 'Zoom here', 'wp-store-locator' ) ) ) . '</a>';

        // Generate location item based on template type
        $html = '';
        
        // Wrap in <li> only for listing template
        if ( $template_type === 'listing' ) {
            $html .= '                <li>';
        }
        
        // Wrap in wpsl-info-window for popup template
        if ( $template_type === 'popup' ) {
            $html .= '                    <div class="wpsl-info-window">';
        }
        
        $html .= '                    <div class="wpsl-store-location">';
        
        // Add icon classes to <p> tag if icons are enabled
        if ( $icons_enabled ) {
            $address_icon = isset( $this->wpsl_settings['appearance']['icons']['address'] ) ? $this->wpsl_settings['appearance']['icons']['address'] : 'marker';
            $html .= '                        <p class="wpsl-icon-address wpsl-icon-address-' . esc_attr( $address_icon ) . '">';
        } else {
            $html .= '                        <p>';
        }
        
        $html .= '                            <span class="wpsl-location-content">';
        $html .= '                                <strong><a href="#">' . $locations_details[$map_service]['name'] . '</a></strong>';
        $html .= '                                <span class="wpsl-street">' . $locations_details[$map_service]['street'] . '</span>';
        $html .= '                                <span>' . $locations_details[$map_service]['city'] . '</span>';
        $html .= '                                <span class="wpsl-country">' . $locations_details[$map_service]['country'] . '</span>';
        $html .= '                            </span>';
        $html .= '                        </p>';
        $html .= '                        <p class="wpsl-contact-details">';
        
        // Phone span with icon classes if enabled
        if ( $icons_enabled ) {
            $phone_icon = isset( $this->wpsl_settings['appearance']['icons']['phone'] ) ? $this->wpsl_settings['appearance']['icons']['phone'] : 'phone';
            $html .= '                            <span class="wpsl-icon-' . esc_attr( $phone_icon ) . '">';
        } else {
            $html .= '                            <span>';
            $html .= '<strong>' . esc_html__( 'Phone', 'wp-store-locator' ) . '</strong>: ';
        }
        
        $html .= $locations_details[$map_service]['phone'] . '</span>';
        
        // Email span with icon classes if enabled
        if ( $icons_enabled ) {
            $email_icon = isset( $this->wpsl_settings['appearance']['icons']['email'] ) ? $this->wpsl_settings['appearance']['icons']['email'] : 'email';
            $html .= '                            <span class="wpsl-icon-' . esc_attr( $email_icon ) . '">';
        } else {
            $html .= '                            <span>';
            $html .= '<strong>' . esc_html__( 'Email', 'wp-store-locator' ) . '</strong>: ';
        }
        
        $html .= $locations_details[$map_service]['email'] . '</span>';
        $html .= '                        </p>';
        $html .= '                    </div>';
        
        // Only include direction-wrap for listing template
        if ( $template_type === 'listing' ) {
            $html .= '                    <div class="wpsl-direction-wrap">';
            
            // Add icon class to distance span if icons are enabled
            $distance_class = ( $icons_enabled ) ? 'wpsl-distance wpsl-icon-road' : 'wpsl-distance';
            $html .= '                        <span class="' . esc_attr( $distance_class ) . '">0.5 '. $this->wpsl_settings['search']['distance_unit'] . '</span>';

            // Only add directions to direction-wrap if wpsl-cta-section won't be created
            if ( ! $cta_directions_enabled && ! $cta_details_enabled ) {
                $html .= $directions_url;
            }

            $html .= '                    </div>';
        }

        // Add CTA buttons or info-actions for popup
        if ( $template_type === 'popup' ) {
            // Popup always gets wpsl-info-actions container
            $container_class = ( $cta_directions_enabled || $cta_details_enabled ) ? 'wpsl-info-actions wpsl-cta-section' : 'wpsl-info-actions';
            $html .= '                    <div class="' . $container_class . '">';
            
            if ( $cta_details_enabled ) {
                $html .= $details_url;
            }
            
            // Always add directions to popup
            $html .= $directions_url;
            
            // Add zoom here link if marker_zoom_to is enabled
            if ( $marker_zoom_to ) {
                $html .= $zoom_here_url;
            }
            
            $html .= '                    </div>';
        } elseif ( $cta_directions_enabled || $cta_details_enabled ) {
            // Listing gets wpsl-cta-section when CTA styling is enabled OR details button is shown
            $html .= '                    <div class="wpsl-cta-section">';
            
            if ( $cta_details_enabled ) {
                $html .= $details_url;
            }

            // Always add directions to wpsl-cta-section (matching popup behavior)
            $html .= $directions_url;

            $html .= '                    </div>';
        }
        
        // Close wpsl-info-window for popup template (after CTA buttons)
        if ( $template_type === 'popup' ) {
            $html .= '                    </div>';
        }

        // Close <li> only for listing template
        if ( $template_type === 'listing' ) {
            $html .= '                </li>';
        }

        $output = [
            'html' => $html, 
            'latlng' => $locations_details[$map_service]['latlng'] 
        ];

        return $output;
    }

    /**
     * Generate the location list for HTML templates 
     * that support multiple columns.
     *
     * @since  3.0.0
     * @return string The complete location list HTML
     */
    public function get_column_location_list() {
        $location_example = $this->location_examples();
        $appearance_settings = $this->settings->get_group( 'appearance' );
        
        $result_columns = absint( $appearance_settings['result_columns'] );
        
        $output = '<ul class="wpsl-v3-columns-' . esc_attr( $result_columns ) . '" data-latlng="' . esc_attr( $location_example['latlng'] ) . '">';
        
        // Repeat the location HTML based on result_columns
        for ( $i = 0; $i < $result_columns; $i++ ) {
            $output .= $location_example['html'];
        }
        
        $output .= '</ul>';
        
        return $output;
    }

    /**
     * Set min-height for the vertical template if no filters are active
     *
     * @since  3.0.0
     * @return string The min-height attribute
     */
    public function set_minheight() {
        $min_height = '';
        $search_settings = $this->settings->get_group( 'search' );

        if ( ! $search_settings['category_filter'] && ! $search_settings['radius_dropdown'] ) {
            $min_height = 'style="min-height:0"';
        }

        return $min_height;
    }

    /**
     * Determine if the input field should be hidden on page load
     *
     * @since  3.0.0
     * @return string The style attribute to hide the input field, or empty string
     */
    public function get_input_visibility() {
        $search_settings = $this->settings->get_group( 'search' );
        
        // Hide input field if category_only_filter is active
        if ( ! empty( $search_settings['category_filter_only'] ) ) {
            return 'style="display:none;"';
        }

        return '';
    }

    /**
     * Determine if the results wrapper should be hidden on page load
     *
     * @since  3.0.0
     * @return string The style attribute to hide the results wrapper, or empty string
     */
    public function get_results_visibility() {
        $search_settings = $this->settings->get_group( 'search' );
        
        // Hide results wrapper if both country and category filters are active (and radius/max_results are not)
        if ( ! empty( $search_settings['category_filter'] ) && empty( $search_settings['radius_dropdown'] ) && empty( $search_settings['results_dropdown'] ) ) {
            return 'style="display:none;"';
        }

        return '';
    }

    /**
     * Render gradient button preview with angle control
     * 
     * @since 3.0.0
     * @param array $args {
     *     @type string $button_type      Button type identifier (e.g., 'listing_cta_more_details')
     *     @type string $button_label     Display label for the button
     *     @type string $data_section     Optional. Data section attribute value
     *     @type string $data_button      Optional. Data button attribute value (e.g., 'more-details')
     *     @type int    $angle            Gradient angle in degrees
     *     @type string $visibility       Optional. Inline style for visibility
     *     @type bool   $wrap_in_section  Optional. Whether to wrap in wpsl-cta-button-section div
     * }
     */
    public function render_gradient_button_preview( $args ) {
        $defaults = [
            'button_type'     => '',
            'button_label'    => '',
            'data_section'    => '',
            'data_button'     => '',
            'angle'           => 180,
            'visibility'      => '',
            'wrap_in_section' => false,
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        // Open wrapper if needed
        if ( $args['wrap_in_section'] ) {
            echo '<div class="wpsl-cta-button-section"';
            if ( $args['data_section'] ) {
                echo ' data-section="' . esc_attr( $args['data_section'] ) . '"';
            }
            if ( $args['data_button'] ) {
                echo ' data-button="' . esc_attr( $args['data_button'] ) . '"';
            }
            echo ' ' . $args['visibility'] . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
            echo '<p><strong>' . esc_html( $args['button_label'] ) . '</strong></p>';
        }
        
        // Preview button and angle control
        echo '<div class="wpsl-gradient-preview-row"';
        if ( ! $args['wrap_in_section'] && $args['data_section'] ) {
            echo ' data-section="' . esc_attr( $args['data_section'] ) . '"';
        }
        echo '>';
        echo '<button type="button" class="wpsl-preview-btn wpsl-preview-' . esc_attr( $args['button_type'] ) . '">';
        echo esc_html( $args['button_label'] );
        echo '</button>';
        echo '<div class="wpsl-gradient-angle-control">';
        echo '<div class="wpsl-angle-picker" data-button-type="' . esc_attr( $args['button_type'] ) . '">';
        echo '<div class="wpsl-angle-picker-dot" style="transform: rotate(' . esc_attr( $args['angle'] ) . 'deg);"></div>';
        echo '</div>';
        echo '<input type="number" class="wpsl-gradient-angle-input" name="wpsl_appearance[theme_colors][' . esc_attr( $args['button_type'] ) . '_angle]" data-button-type="' . esc_attr( $args['button_type'] ) . '" value="' . esc_attr( $args['angle'] ) . '" min="0" max="360" step="1">';
        echo '</div>';
        echo '</div>';
        
        // Base colors row
        echo '<div class="wpsl-gradient-controls-row"';
        if ( ! $args['wrap_in_section'] && $args['data_section'] ) {
            echo ' data-section="' . esc_attr( $args['data_section'] ) . '"';
        }
        echo '>';
    }

    /**
     * Render a complete button style section with gradient controls
     * 
     * @since 3.0.0
     * @param array $args {
     *     @type string $button_type       Button type identifier (e.g., 'submit', 'general_primary')
     *     @type string $button_label      Display label for the button
     *     @type int    $angle             Gradient angle in degrees
     *     @type string $angle_field_name  Field name for angle input (e.g., 'submit_angle')
     *     @type array  $base_fields       Array of base color fields ['field_key' => 'Field Label']
     *     @type array  $hover_fields      Array of hover color fields ['field_key' => 'Field Label']
     *     @type string $data_section      Optional. Data section attribute value
     *     @type string $preview_class     Optional. Custom preview button class (defaults to button_type)
     *     @type object $theme_styles      Theme styles object for getting color values
     * }
     */
    public function render_button_style_section( $args ) {
        $defaults = [
            'button_type'      => '',
            'button_label'     => '',
            'angle'            => 180,
            'angle_field_name' => '',
            'base_fields'      => [],
            'hover_fields'     => [],
            'data_section'     => '',
            'preview_class'    => '',
            'theme_styles'     => null,
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        if ( empty( $args['preview_class'] ) ) {
            $args['preview_class'] = $args['button_type'];
        }
        
        echo '<div class="wpsl-style-section">';
        echo '<div class="wpsl-gradient-preview-row"';
        
        if ( $args['data_section'] ) {
            echo ' data-section="' . esc_attr( $args['data_section'] ) . '"';
        }

        echo '>';
        // Determine if vertical template is active
        $is_vertical = false;

        if ( $args['button_type'] === 'submit' ) {
            $active_template = wpsl_get_active_template();
            $is_vertical = isset( $active_template['id'] ) && $active_template['id'] === 'vertical';
        }
        
        // Show text submit button (hidden for vertical template)
        $text_button_style = ( $is_vertical ) ? ' style="display:none;"' : '';

        echo '<button type="button" class="wpsl-preview-btn wpsl-preview-' . esc_attr( $args['preview_class'] ) . '"' . $text_button_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
        echo esc_html( $args['button_label'] );
        echo '</button>';
        
        // Show icon submit button only for vertical template
        if ( $args['button_type'] === 'submit' ) {
            $icon_button_style = ( ! $is_vertical ) ? ' style="display:none;"' : ' style="border: none; border-color: transparent;"';
            
            echo '<button class="wpsl-preview-btn wpsl-preview-vertical-submit" type="button" aria-label="Search"' . $icon_button_style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
            echo '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"></path></svg>';
            echo '</button>';
        }
        
        echo '<div class="wpsl-gradient-angle-control">';
        echo '<div class="wpsl-angle-picker" data-button-type="' . esc_attr( $args['button_type'] ) . '">';
        echo '<div class="wpsl-angle-picker-dot" style="transform: rotate(' . esc_attr( $args['angle'] ) . 'deg);"></div>';
        echo '</div>';
        echo '<input type="number" class="wpsl-gradient-angle-input" name="wpsl_appearance[button_colors][' . esc_attr( $args['angle_field_name'] ) . ']" data-button-type="' . esc_attr( $args['button_type'] ) . '" value="' . esc_attr( $args['angle'] ) . '" min="0" max="360" step="1">';
        echo '</div>';
        echo '</div>';
        
        // Base + hover colors in a single row
        if ( ! empty( $args['base_fields'] ) || ! empty( $args['hover_fields'] ) ) {
            $type_slug = str_replace( '_', '-', $args['button_type'] );

            // Map each foreground data-elem to the two gradient background data-elems
            // it should be checked against. Both backgrounds are tested; the lower
            // (worst) ratio drives the warning and the auto-fix.
            $btn_contrast_pairs = [
                // Submit
                'submit-text'                        => [ 'bg' => 'submit-background-start',                  'bg2' => 'submit-background-end' ],
                'submit-text-hover'                  => [ 'bg' => 'submit-background-start-hover',            'bg2' => 'submit-background-end-hover' ],
                // General Primary
                'general-primary-text-color'         => [ 'bg' => 'general-primary-background-start',         'bg2' => 'general-primary-background-end' ],
                'general-primary-text-color-hover'   => [ 'bg' => 'general-primary-background-start-hover',   'bg2' => 'general-primary-background-end-hover' ],
                // General Secondary
                'general-secondary-text-color'       => [ 'bg' => 'general-secondary-background-start',       'bg2' => 'general-secondary-background-end' ],
                'general-secondary-text-color-hover' => [ 'bg' => 'general-secondary-background-start-hover', 'bg2' => 'general-secondary-background-end-hover' ],
            ];

            $_wpsl_settings      = wpsl_get_service( 'wpsl_settings' );
            $_appearance         = $_wpsl_settings->get_group( 'appearance' );
            $_button_colors      = isset( $_appearance['button_colors'] ) ? $_appearance['button_colors'] : [];
            $_contrast_checker   = new \WPSL\Core\UI\Contrast_Checker();

            /**
             * Build the contrast HTML attributes for a given field.
             * Returns a string of data-* attributes (may be empty).
             *
             * @param string $elem_id       The data-elem value of the foreground field.
             * @param string $setting_value The saved hex color of the foreground field.
             */
            $get_contrast_attrs = function( $elem_id, $setting_value ) use ( $btn_contrast_pairs, $_button_colors, $_contrast_checker ) {
                if ( ! isset( $btn_contrast_pairs[ $elem_id ] ) ) {
                    return '';
                }

                $pair      = $btn_contrast_pairs[ $elem_id ];
                $bg1_key   = str_replace( '-', '_', $pair['bg'] );
                $bg2_key   = str_replace( '-', '_', $pair['bg2'] );
                $bg1_value = isset( $_button_colors[ $bg1_key ] ) ? $_button_colors[ $bg1_key ] : '';
                $bg2_value = isset( $_button_colors[ $bg2_key ] ) ? $_button_colors[ $bg2_key ] : '';

                $attrs = ' data-contrast-bg="'   . esc_attr( $pair['bg'] )  . '"'
                       . ' data-contrast-bg-2="' . esc_attr( $pair['bg2'] ) . '"';

                if ( $setting_value && ( $bg1_value || $bg2_value ) ) {
                    $ratio1  = $bg1_value ? $_contrast_checker->get_contrast_ratio( $setting_value, $bg1_value ) : 21.0;
                    $ratio2  = $bg2_value ? $_contrast_checker->get_contrast_ratio( $setting_value, $bg2_value ) : 21.0;
                    $worst   = min( $ratio1, $ratio2 );

                    $attrs .= ' data-initial-contrast-ratio="'  . esc_attr( number_format( $worst, 2 ) ) . '"'
                            . ' data-initial-contrast-rating="' . esc_attr( $_contrast_checker->get_rating( $worst ) ) . '"';
                }

                return $attrs;
            };

            echo '<div class="wpsl-gradient-controls-row"';

            if ( $args['data_section'] ) {
                echo ' data-section="' . esc_attr( $args['data_section'] ) . '"';
            }

            echo '>';
            echo '<ul class="wpsl-' . esc_attr( $type_slug ) . '-options wpsl-gradient-controls-ul">';

            foreach ( $args['base_fields'] as $field_key => $field_label ) {
                $color_field     = $args['button_type'] . '_' . $field_key;
                $setting_value   = $args['theme_styles']->get_color_value( $color_field, 'button_colors' );
                $default_key     = str_replace( '_', '-', $color_field );
                $default_value   = isset( $args['theme_styles']->defaults[ $default_key ] ) ? $args['theme_styles']->defaults[ $default_key ] : '';
                $field_style     = ( $is_vertical && $field_key === 'border' ) ? ' style="display:none;"' : '';
                $field_slug      = str_replace( '_', '-', $field_key );
                $elem_id         = $type_slug . '-' . $field_slug;
                $contrast_attrs  = $get_contrast_attrs( $elem_id, $setting_value );

                echo '<li data-elem="' . esc_attr( $elem_id ) . '"' . $field_style . $contrast_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
                echo '<p>';
                echo '<label for="wpsl-style-' . esc_attr( $type_slug ) . '-' . esc_attr( $field_slug ) . '">' . esc_html( $field_label ) . '</label>';
                echo '<input id="wpsl-style-' . esc_attr( $type_slug ) . '-' . esc_attr( $field_slug ) . '" class="wpsl-color-field wpsl-gradient-color-field" name="wpsl_appearance[button_colors][' . esc_attr( $color_field ) . ']" type="text" value="' . esc_attr( $setting_value ) . '" data-default="' . esc_attr( $default_value ) . '" />';
                echo '</p>';
                echo '</li>';
            }

            foreach ( $args['hover_fields'] as $field_key => $field_label ) {
                $color_field     = $args['button_type'] . '_' . $field_key;
                $setting_value   = $args['theme_styles']->get_color_value( $color_field, 'button_colors' );
                $default_key     = str_replace( '_', '-', $color_field );
                $default_value   = isset( $args['theme_styles']->defaults[ $default_key ] ) ? $args['theme_styles']->defaults[ $default_key ] : '';
                $field_style     = ( $is_vertical && $field_key === 'border_hover' ) ? ' style="display:none;"' : '';
                $field_slug      = str_replace( '_', '-', $field_key );
                $elem_id         = $type_slug . '-' . $field_slug;
                $contrast_attrs  = $get_contrast_attrs( $elem_id, $setting_value );

                echo '<li data-elem="' . esc_attr( $elem_id ) . '"' . $field_style . $contrast_attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
                echo '<p>';
                echo '<label for="wpsl-style-' . esc_attr( $type_slug ) . '-' . esc_attr( $field_slug ) . '">' . esc_html( $field_label ) . '</label>';
                echo '<input id="wpsl-style-' . esc_attr( $type_slug ) . '-' . esc_attr( $field_slug ) . '" class="wpsl-color-field wpsl-gradient-color-field" name="wpsl_appearance[button_colors][' . esc_attr( $color_field ) . ']" type="text" value="' . esc_attr( $setting_value ) . '" data-default="' . esc_attr( $default_value ) . '" />';
                echo '</p>';
                echo '</li>';
            }

            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Generate all appearance editor tab content HTML
     *
     * @return string
     */
    public function get_tab_content() {
        $tabs = [
            [
                'id'          => 'wpsl-theme-styles-tab',
                'title'       => __( 'Theme Style', 'wp-store-locator' ),
                'template'    => 'theme-styles.php',
                'show_reset'  => true,
                'show_footer' => true,
            ],
            [
                'id'          => 'wpsl-button-styles-tab',
                'title'       => __( 'Button Styles', 'wp-store-locator' ),
                'template'    => 'button-styles.php',
                'show_reset'  => true,
                'show_footer' => true,
            ],
            [
                'id'          => 'wpsl-map-styles-tab',
                'title'       => __( 'Map Style', 'wp-store-locator' ),
                'template'    => 'map-styles.php',
                'show_reset'  => false,
                'show_footer' => true,
            ],
            [
                'id'          => 'wpsl-font-size-tab',
                'title'       => __( 'Font Size', 'wp-store-locator' ),
                'template'    => 'font-size.php',
                'show_reset'  => true,
                'show_footer' => true,
            ],
            [
                'id'          => 'wpsl-dimensions-tab',
                'title'       => __( 'Dimensions', 'wp-store-locator' ),
                'template'    => 'dimensions.php',
                'show_reset'  => true,
                'show_footer' => true,
            ],
            [
                'id'          => 'wpsl-search-results-tab',
                'title'       => __( 'Search Results', 'wp-store-locator' ),
                'template'    => 'search-results.php',
                'show_reset'  => false,
                'show_footer' => true,
            ],
            [
                'id'          => 'wpsl-accessibility-tab',
                'title'       => __( 'Accessibility', 'wp-store-locator' ),
                'template'    => 'accessibility.php',
                'show_reset'  => false,
                'show_footer' => true,
            ],
        ];

        $html = '';

        // Theme home view — shown on page load instead of the menu.
        $html .= '<div class="wpsl-appearance-view" id="wpsl-theme-home-view">';
        $html .= '<div class="wpsl-template-selection">';
        ob_start();
        $this->template_styles_list( true );
        $html .= ob_get_clean();
        $html .= '</div>';
        $html .= '</div>';

        // Shared header
        $html .= '<div class="wpsl-tab-header wpsl-hidden">';
        $html .= '<button type="button" class="wpsl-back-button" data-back-to="menu">';
        $html .= '<span class="dashicons dashicons-arrow-left-alt2"></span>';
        $html .= esc_html__( 'Back', 'wp-store-locator' );
        $html .= '</button>';
        $html .= '<h2 class="wpsl-tab-title"></h2>';
        $html .= '</div>';
        
        // Content container with all tab contents
        $html .= '<div id="wpsl-appearance-content" class="wpsl-hidden">';
        
        foreach ( $tabs as $tab ) {
            $show_footer = isset( $tab['show_footer'] ) ? $tab['show_footer'] : true;
            $html .= '<div class="wpsl-appearance-view wpsl-hidden" id="' . esc_attr( $tab['id'] ) . '" data-tab-title="' . esc_attr( $tab['title'] ) . '" data-show-reset="' . esc_attr( $tab['show_reset'] ? '1' : '0' ) . '" data-show-footer="' . esc_attr( $show_footer ? '1' : '0' ) . '">';
            
            // Include the tab template file
            $template_path = WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/' . $tab['template'];
            
            if ( file_exists( $template_path ) ) {
                ob_start();
                include $template_path;
                $html .= ob_get_clean();
            } else {
                $html .= '<!-- Template file not found: ' . esc_html( $tab['template'] ) . ' -->';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        // Shared footer
        $html .= '<div class="wpsl-tab-footer">';
        $html .= '<p class="submit wpsl-appearance-actions">';
        $html .= '<input type="submit" value="' . esc_attr__( 'Save Customization', 'wp-store-locator' ) . '" class="button-primary wpsl-save-tab">';
        $html .= '<input type="submit" value="' . esc_attr__( 'Restore Default Colors', 'wp-store-locator' ) . '" class="button-secondary wpsl-reset-styles">';
        $html .= '</p>';
        $html .= '</div>';
        
        return $html;
    }
}