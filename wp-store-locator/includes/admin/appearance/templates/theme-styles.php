<?php
/**
 * Theme Styles Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$appearance = wpsl_get_service( 'appearance' );
$theme_styles = wpsl_get_service( 'theme_styles' );
$template_id = $wpsl_settings->get( 'appearance', 'template_id' );
?>

<p class="wpsl-conditional-overwrite">
    <label class="wpsl-tab-label wpsl-no-checkbox-spacing" for="wpsl-overwrite-theme-styles"><?php esc_html_e( 'Overwrite default theme styles', 'wp-store-locator' ); ?></label>
    <input type="checkbox" <?php checked( isset( $section_settings['overwrite_theme_styles'] ) ? $section_settings['overwrite_theme_styles'] : false, true ); ?> name="wpsl_appearance[overwrite_theme_styles]" id="wpsl-overwrite-theme-styles" class="wpsl-has-conditional-option">
</p>
<div class="wpsl-conditional-option" <?php if ( ! isset( $section_settings['overwrite_theme_styles'] ) || ! $section_settings['overwrite_theme_styles'] ) { echo 'style="display:none;"'; } ?>>
<p class="wpsl-no-flex">
    <select id="wpsl-style-editor-dropdown">
        <option value="header"><?php esc_html_e( 'Header', 'wp-store-locator' ); ?></option>
        <option value="listing"><?php esc_html_e( 'Listing', 'wp-store-locator' ); ?></option>
        <option value="popup"><?php esc_html_e( 'Popup', 'wp-store-locator' ); ?></option>
    </select>

    <select id="wpsl-header-style-sections" class="wpsl-style-filter">
        <option value="header-container"><?php esc_html_e( 'Container', 'wp-store-locator' ); ?></option>
        <option value="header-input"><?php esc_html_e( 'Input field', 'wp-store-locator' ); ?></option>
        <option value="header-reset" <?php if ( $template_id !== 'vertical' ) { echo 'style="display: none;"'; } ?>><?php esc_html_e( 'Reset search', 'wp-store-locator' ); ?></option>
        <option value="header-dropdown"><?php esc_html_e( 'Dropdown', 'wp-store-locator' ); ?></option>
    </select>

    <!-- The submit button is styled on the Button Styles tab - point users there. -->
    <span id="wpsl-submit-style-info" class="wpsl-info">
        <span class="wpsl-info-text wpsl-hide">
            <?php
            /* translators: %1$s: opening link tag to the Button Styles tab, %2$s: closing link tag */
            echo sprintf( esc_html__( 'The submit button can be styled in the %1$sButton Styles%2$s section.', 'wp-store-locator' ), '<a href="#wpsl-button-styles-tab" class="wpsl-jump-to-tab">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above, placeholders hold static link tags
            ?>
        </span>
    </span>

    <select id="wpsl-listing-style-sections" class="wpsl-style-filter" style="display: none;">
        <option value="listing-results"><?php esc_html_e( 'Search results', 'wp-store-locator' ); ?></option>
        <option value="listing-icons" <?php if ( ! isset( $section_settings['icons']['enabled'] ) || ! $section_settings['icons']['enabled'] ) { echo 'style="display: none;"'; } ?>><?php esc_html_e( 'Icons', 'wp-store-locator' ); ?></option>
    </select>

    <select id="wpsl-popup-style-sections" class="wpsl-style-filter" style="display: none;">
        <option value="popup-content"><?php esc_html_e( 'Content', 'wp-store-locator' ); ?></option>
        <option value="popup-icons" <?php if ( ! isset( $section_settings['icons']['enabled'] ) || ! $section_settings['icons']['enabled'] ) { echo 'style="display: none;"'; } ?>><?php esc_html_e( 'Icons', 'wp-store-locator' ); ?></option>
    </select>
</p>

<?php
// Get the customization sections from the Appearance class
$customize_sections = $appearance->get_customize_sections();

/**
 * Map foreground data-elem IDs to the background data-elem they 
 * should be checked against for WCAG contrast.
 */
$contrast_pairs = [
    // Header — container
    'header-container-text'                  => 'header-container-background',
    // Header — input field
    'header-input-text'                      => 'header-input-background',
    'header-input-text-hover'                => 'header-input-background-hover',
    // Header — dropdown
    'header-dropdown-text'                   => 'header-dropdown-background',
    'header-dropdown-text-hover'             => 'header-dropdown-background-hover',
    'header-dropdown-item-text'              => 'header-dropdown-background-expanded',
    'header-dropdown-item-text-hover'        => 'header-dropdown-item-background-hover',
    'header-dropdown-item-text-selected'     => 'header-dropdown-item-background-selected',
    // Listing — results
    'listing-results-text'                   => 'listing-results-background',
    'listing-results-link'                   => 'listing-results-background',
    'listing-results-link-hover'             => 'listing-results-background',
    // Popup — content
    'popup-content-text'                     => 'popup-content-background',
    'popup-content-link'                     => 'popup-content-background',
    'popup-content-link-hover'               => 'popup-content-background',
    'popup-content-close'                    => 'popup-content-background',
    'popup-content-close-hover'              => 'popup-content-background',
];

$saved_colors = isset( $section_settings['theme_colors'] ) ? $section_settings['theme_colors'] : [];
$contrast_checker = new \WPSL\Core\UI\Contrast_Checker();

$section_index = 0;
foreach ( $customize_sections as $parent_name => $sections ) {
    $visible_class = ( $section_index === 0 ) ? ' wpsl-visible' : '';

    echo '<div id="wpsl-' . esc_attr( $parent_name ) . '-section" class="wpsl-style-section' . $visible_class . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data

    $section_index++;

        if ( wpsl_is_multidimensional( $customize_sections[$parent_name] ) ) {
            foreach ( $customize_sections[$parent_name] as $section_name => $section_fields ) {

                // Determine if this section should be hidden based on checkbox state
                $section_visibility = '';
                
                // CTA buttons should be hidden by default (JS will show them when needed)
                if ( $section_name === 'cta_more_details' || $section_name === 'cta_directions' ) {
                    $section_visibility = 'style="display: none;"';
                }
                
                if ( $section_name === 'icons' && ( ! isset( $section_settings['icons']['enabled'] ) || ! $section_settings['icons']['enabled'] ) ) {
                    $section_visibility = 'style="display: none;"';
                }

                // Add section header for CTA buttons with data-section attribute
                if ( $section_name === 'cta_more_details' ) {
                    $appearance->render_gradient_button_preview( [
                        'button_type'     => $parent_name . '_cta_more_details',
                        'button_label'    => __( 'More Details', 'wp-store-locator' ),
                        'data_section'    => $parent_name . '-cta',
                        'data_button'     => 'more-details',
                        'angle'           => isset( $section_settings['theme_colors'][$parent_name . '_cta_more_details_angle'] ) ? $section_settings['theme_colors'][$parent_name . '_cta_more_details_angle'] : 180,
                        'visibility'      => $section_visibility,
                        'wrap_in_section' => true,
                    ] );
                } elseif ( $section_name === 'cta_directions' ) {
                    $appearance->render_gradient_button_preview( [
                        'button_type'     => $parent_name . '_cta_directions',
                        'button_label'    => __( 'Directions', 'wp-store-locator' ),
                        'data_section'    => $parent_name . '-cta',
                        'data_button'     => 'directions',
                        'angle'           => isset( $section_settings['theme_colors'][$parent_name . '_cta_directions_angle'] ) ? $section_settings['theme_colors'][$parent_name . '_cta_directions_angle'] : 180,
                        'visibility'      => $section_visibility,
                        'wrap_in_section' => true,
                    ] );
                }

                // Skip submit section rendering here - it's now in the Button Style tab
                if ( $parent_name === 'header' && $section_name === 'submit' ) {
                    continue;
                }

                // Convert underscores to hyphens for CTA sections only
                $ul_class_section_name = ( strpos( $section_name, 'cta_' ) === 0 ) ? str_replace( '_', '-', $section_name ) : $section_name;
                echo '<ul class="wpsl-' . esc_attr( $parent_name ) . '-' . esc_attr( $ul_class_section_name )  . '-options" ' . $section_visibility . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data

                foreach ( $section_fields as $field_name => $value ) {

                    // Determine field visibility based on template and section.
                    $should_hide = false;
                    
                    // Vertical template specific rules
                    if ( $template_id === 'vertical' ) {
                        $vertical_hidden_fields = [
                            'container' => [ 'text' ],
                            'submit'    => [ 'text', 'text-hover', 'border', 'border-hover' ],
                            'dropdown'  => [ 'border', 'border-hover' ]
                        ];
                        
                        // Check if field should be hidden for vertical template
                        if ( isset( $vertical_hidden_fields[ $section_name ] ) && in_array( $field_name, $vertical_hidden_fields[ $section_name ] ) ) {
                            $should_hide = true;
                        }
                    }
                    
                    // Non-vertical template specific rules
                    if ( $template_id !== 'vertical' ) {

                        // Hide icon fields for non-vertical templates (except for the icons section itself)
                        if ( strpos( $field_name, 'icon' ) !== false && $section_name !== 'icons' ) {
                            $should_hide = true;
                        }
                        
                        // Hide vertical-only dropdown fields for non-vertical templates
                        if ( $section_name === 'dropdown' ) {
                            $vertical_only_fields = [ 'reset-background', 'reset-background-hover', 'reset-border', 'reset-border-hover', 'reset-icon', 'reset-icon-hover' ];
                            
                            if ( in_array( $field_name, $vertical_only_fields ) ) {
                                $should_hide = true;
                            }
                        }
                    }
                    
                    $visibility = $should_hide ? 'style="display: none;"' : '';

                    $color_field = $parent_name . '_' . $section_name . '_' . str_replace( '-', '_', $field_name );
                    $setting_value = $theme_styles->get_color_value( $color_field );
                    $default_key = str_replace( '_', '-', $color_field );
                    $default_color = isset( $theme_styles->defaults[$default_key] ) ? $theme_styles->defaults[$default_key] : '';

                    // Build the data-elem ID
                    $elem_id = $parent_name . '-' . str_replace( '_', '-', $section_name ) . '-' . $field_name;

                    // Contrast check attributes
                    $contrast_bg_attr    = '';
                    $contrast_ratio_attr = '';

                    if ( isset( $contrast_pairs[ $elem_id ] ) ) {
                        $bg_elem             = $contrast_pairs[ $elem_id ];
                        $contrast_bg_attr    = ' data-contrast-bg="' . esc_attr( $bg_elem ) . '"';

                        $bg_field_key = str_replace( '-', '_', $bg_elem );
                        $bg_value     = isset( $saved_colors[ $bg_field_key ] ) ? $saved_colors[ $bg_field_key ] : '';

                        if ( $setting_value && $bg_value ) {
                            $ratio               = $contrast_checker->get_contrast_ratio( $setting_value, $bg_value );
                            $rating              = $contrast_checker->get_rating( $ratio );
                            $contrast_ratio_attr = ' data-initial-contrast-ratio="' . esc_attr( number_format( $ratio, 2 ) ) . '"'
                                                 . ' data-initial-contrast-rating="' . esc_attr( $rating ) . '"';
                        }
                    }

                    echo '<li ' . $visibility . ' data-elem="' . esc_attr( $elem_id ) . '"' . $contrast_bg_attr . $contrast_ratio_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
                    echo '<p>';
                    echo '<label class="wpsl-tab-label" for="wpsl-style-' . esc_attr( $parent_name ) . '-' . esc_attr( $section_name ) . '-' . esc_attr( $field_name ) . '">' . esc_html( $value ) . '</label>';
                    echo '<input id="wpsl-style-' . esc_attr( $parent_name ) . '-' . esc_attr( $section_name ) . '-' . esc_attr( $field_name ) . '" class="wpsl-color-field" name="wpsl_appearance[theme_colors][' . esc_attr( $parent_name ) . '_' . esc_attr( $section_name ) . '_' . esc_attr( str_replace( '-', '_', $field_name ) ) . ']" type="text" value="' . esc_attr( $setting_value ) . '" data-default="' . esc_attr( $default_color ) . '" />';
                    echo '</p>';
                    echo '</li>';
                }

                echo '</ul>';
                
                // Close gradient controls row for header-submit and CTA buttons
                if ( $parent_name === 'header' && $section_name === 'submit' ) {
                    echo '</div>'; // Close wpsl-gradient-controls-row
                } elseif ( $section_name === 'cta_more_details' || $section_name === 'cta_directions' ) {
                    echo '</div>'; // Close wpsl-gradient-controls-row
                    echo '</div>'; // Close wpsl-cta-button-section wrapper
                }
            }
        } else {
            // Standard ul for other sections
            echo '<ul>';

            foreach ( $sections as $style_name => $value ) {
                $color_field = $parent_name . '_' . str_replace( '-', '_', $style_name );
                $setting_value = $theme_styles->get_color_value( $color_field );
                $default_key = $parent_name . '-' . $style_name;
                $default_color = isset( $theme_styles->defaults[$default_key] ) ? $theme_styles->defaults[$default_key] : '';

                echo '<li data-elem="' . esc_attr( $parent_name ) . '-' . esc_attr( $style_name ) . '">';
                echo '<p>';
                echo '<label class="wpsl-tab-label" for="wpsl-style-' . esc_attr( $parent_name ) . '-' . esc_attr( $style_name ) . '">' . esc_html( $value ) . '</label>';
                echo '<input id="wpsl-style-' . esc_attr( $parent_name ) . '-' . esc_attr( $style_name ) . '" class="wpsl-color-field" name="wpsl_appearance[theme_colors][' . esc_attr( $parent_name ) . '_' . esc_attr( str_replace( '-', '_', $style_name ) ) . ']" type="text" value="' . esc_attr( $setting_value ) . '" data-default="' . esc_attr( $default_color ) . '" />';
                echo '</p>';
                echo '</li>';
            }

            echo '</ul>';
        }

    echo '</div>';
}
?>
</div>