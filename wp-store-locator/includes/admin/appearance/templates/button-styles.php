<?php
/**
 * Button Styles Tab Content
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

$submit_angle = isset( $section_settings['button_colors']['submit_angle'] ) ? $section_settings['button_colors']['submit_angle'] : 180;
$primary_angle = isset( $section_settings['button_colors']['general_primary_angle'] ) ? $section_settings['button_colors']['general_primary_angle'] : 180;
$secondary_angle = isset( $section_settings['button_colors']['general_secondary_angle'] ) ? $section_settings['button_colors']['general_secondary_angle'] : 180;

// Check if auto-locate with user_request trigger is enabled for Share location/No thanks buttons
$auto_locate_enabled = $wpsl_settings->get( 'search', 'auto_locate' );
$auto_locate_trigger = $wpsl_settings->get( 'search', 'auto_locate_trigger' );
$show_auto_locate_buttons = ( $auto_locate_enabled && $auto_locate_trigger === 'user_request' ) ? '' : 'wpsl-hide';

// Check if Google Maps is active for Streetview button
$active_map_service = $wpsl_settings->get( 'api', 'active_map_service' );
$show_streetview_button = ( $active_map_service === 'gmaps' ) ? '' : 'wpsl-hide';
?>

<?php require WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/partials/custom-section-notice.php'; ?>

<!-- CTA buttons checkbox - first element -->
<p class="wpsl-conditional-overwrite">
    <label class="wpsl-tab-label wpsl-label-with-info" for="wpsl-cta-buttons"><?php esc_html_e( 'Style action links as buttons?', 'wp-store-locator' ); ?>
        <span class="wpsl-info">
            <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This will turn the "directions", "zoom here" and "street view" links into clickable buttons.', 'wp-store-locator' ); ?></span>
        </span>
    </label>
    <input type="checkbox" value="1" <?php checked( isset( $section_settings['cta']['enabled'] ) ? $section_settings['cta']['enabled'] : false, true ); ?> name="wpsl_appearance[cta][enabled]" id="wpsl-cta-buttons" class="wpsl-has-conditional-options">
</p>

<p class="wpsl-no-flex wpsl-button-styles-options wpsl-conditional-option" <?php if ( ! isset( $section_settings['cta']['enabled'] ) || ! $section_settings['cta']['enabled'] ) { echo 'style="display:none;"'; } ?>>
    <select id="wpsl-button-style-mode">
        <option value="manage-styles"><?php esc_html_e( 'Manage Styles', 'wp-store-locator' ); ?></option>    
        <option value="assign-styles"><?php esc_html_e( 'Assign Styles', 'wp-store-locator' ); ?></option>
    </select>

    <select id="wpsl-button-type-filter" class="wpsl-button-filter" data-button-mode="manage-styles">
        <option value="submit"><?php esc_html_e( 'Submit button', 'wp-store-locator' ); ?></option>
        <option value="primary"><?php esc_html_e( 'Primary button', 'wp-store-locator' ); ?></option>
        <option value="secondary"><?php esc_html_e( 'Secondary button', 'wp-store-locator' ); ?></option>
    </select>
</p>

<!-- Submit button section - always visible -->
<div class="wpsl-button-colors-section wpsl-submit-button-section">
    <div class="wpsl-button-style-section" data-button-type="submit">
    <?php
        $appearance->render_button_style_section( [
            'button_type'      => 'submit',
            'button_label'     => __( 'Submit Button', 'wp-store-locator' ),
            'angle'            => $submit_angle,
            'angle_field_name' => 'submit_angle',
            'data_section'     => 'submit',
            'theme_styles'     => $theme_styles,
            'base_fields'      => [
                'background_start' => __( 'Background start', 'wp-store-locator' ),
                'background_end'   => __( 'Background end', 'wp-store-locator' ),
                'border'           => __( 'Border', 'wp-store-locator' ),
                'text'             => __( 'Text/Icon', 'wp-store-locator' )
            ],
            'hover_fields'     => [
                'background_start_hover' => __( 'Background start hover', 'wp-store-locator' ),
                'background_end_hover'   => __( 'Background end hover', 'wp-store-locator' ),
                'border_hover'           => __( 'Border hover', 'wp-store-locator' ),
                'text_hover'             => __( 'Text/Icon hover', 'wp-store-locator' )
            ]
        ] );
    ?>
    </div>
</div>

<div class="wpsl-button-colors-section wpsl-cta-button-section" data-button-mode="manage-styles" <?php if ( ! isset( $section_settings['cta']['enabled'] ) || ! $section_settings['cta']['enabled'] ) { echo 'style="display:none;"'; } ?>>
    <div class="wpsl-button-style-section" data-button-type="primary" style="display:none;">
    <?php
        $appearance->render_button_style_section( [
            'button_type'      => 'general_primary',
            'button_label'     => __( 'Primary Button', 'wp-store-locator' ),
            'angle'            => $primary_angle,
            'angle_field_name' => 'general_primary_angle',
            'preview_class'    => 'primary',
            'theme_styles'     => $theme_styles,
            'base_fields'      => [
                'background_start' => __( 'Background start', 'wp-store-locator' ),
                'background_end'   => __( 'Background end', 'wp-store-locator' ),
                'border_color'     => __( 'Border color', 'wp-store-locator' ),
                'text_color'       => __( 'Text color', 'wp-store-locator' )
            ],
            'hover_fields'     => [
                'background_start_hover' => __( 'Background start hover', 'wp-store-locator' ),
                'background_end_hover'   => __( 'Background end hover', 'wp-store-locator' ),
                'border_color_hover'     => __( 'Border hover color', 'wp-store-locator' ),
                'text_color_hover'       => __( 'Text hover color', 'wp-store-locator' )
            ]
        ] );
    ?>
    </div>

    <div class="wpsl-button-style-section" data-button-type="secondary" style="display:none;">
    <?php
        $appearance->render_button_style_section( [
            'button_type'      => 'general_secondary',
            'button_label'     => __( 'Secondary Button', 'wp-store-locator' ),
            'angle'            => $secondary_angle,
            'angle_field_name' => 'general_secondary_angle',
            'preview_class'    => 'secondary',
            'theme_styles'     => $theme_styles,
            'base_fields'      => [
                'background_start' => __( 'Background start', 'wp-store-locator' ),
                'background_end'   => __( 'Background end', 'wp-store-locator' ),
                'border_color'     => __( 'Border color', 'wp-store-locator' ),
                'text_color'       => __( 'Text color', 'wp-store-locator' )
            ],
            'hover_fields'     => [
                'background_start_hover' => __( 'Background start hover', 'wp-store-locator' ),
                'background_end_hover'   => __( 'Background end hover', 'wp-store-locator' ),
                'border_color_hover'     => __( 'Border hover color', 'wp-store-locator' ),
                'text_color_hover'       => __( 'Text hover color', 'wp-store-locator' )
            ]
        ] );
    ?>
    </div>
</div>

<div class="wpsl-conditional-option wpsl-button-style-listing" data-button-mode="assign-styles" style="display:none;" <?php if ( ! isset( $section_settings['cta']['enabled'] ) || ! $section_settings['cta']['enabled'] ) { echo 'style="display:none;"'; } ?>>
    <table class="wpsl-button-style-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Button', 'wp-store-locator' ); ?></th>
                <th><?php esc_html_e( 'Primary style', 'wp-store-locator' ); ?></th>
                <th><?php esc_html_e( 'Secondary style', 'wp-store-locator' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e( 'More details', 'wp-store-locator' ); ?></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][more_details]" value="primary" data-button-target="wpsl-details" data-style-type="primary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['more_details'] ) ? $section_settings['button_styles']['more_details'] : '', 'primary' ); ?>></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][more_details]" value="secondary" data-button-target="wpsl-details" data-style-type="secondary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['more_details'] ) ? $section_settings['button_styles']['more_details'] : '', 'secondary' ); ?>></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Directions', 'wp-store-locator' ); ?></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][directions]" value="primary" data-button-target="wpsl-directions" data-style-type="primary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['directions'] ) ? $section_settings['button_styles']['directions'] : '', 'primary' ); ?>></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][directions]" value="secondary" data-button-target="wpsl-directions" data-style-type="secondary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['directions'] ) ? $section_settings['button_styles']['directions'] : '', 'secondary' ); ?>></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Zoom here', 'wp-store-locator' ); ?></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][zoom_here]" value="primary" data-button-target="wpsl-zoom-here" data-style-type="primary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['zoom_here'] ) ? $section_settings['button_styles']['zoom_here'] : '', 'primary' ); ?>></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][zoom_here]" value="secondary" data-button-target="wpsl-zoom-here" data-style-type="secondary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['zoom_here'] ) ? $section_settings['button_styles']['zoom_here'] : '', 'secondary' ); ?>></td>
            </tr>
            <tr class="<?php echo esc_attr( $show_auto_locate_buttons ); ?>">
                <td>
                    <?php esc_html_e( 'Share location', 'wp-store-locator' ); ?>
                    <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This button is only visible if the option to auto-locate the user\'s current location is enabled (search section) and the user needs to approve the geolocation request.', 'wp-store-locator' ); ?></span></span>
                </td>
                <td><input type="radio" name="wpsl_appearance[button_styles][share_location]" value="primary" data-button-target="wpsl-share-location" data-style-type="primary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['share_location'] ) ? $section_settings['button_styles']['share_location'] : '', 'primary' ); ?>></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][share_location]" value="secondary" data-button-target="wpsl-share-location" data-style-type="secondary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['share_location'] ) ? $section_settings['button_styles']['share_location'] : '', 'secondary' ); ?>></td>
            </tr>
            <tr class="<?php echo esc_attr( $show_auto_locate_buttons ); ?>">
                <td>
                    <?php esc_html_e( 'No thanks', 'wp-store-locator' ); ?>
                    <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This button is only visible if the option to auto-locate the user\'s current location is enabled (search section) and the user needs to approve the geolocation request.', 'wp-store-locator' ); ?></span></span>
                </td>
                <td><input type="radio" name="wpsl_appearance[button_styles][no_thanks]" value="primary" data-button-target="wpsl-no-thanks" data-style-type="primary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['no_thanks'] ) ? $section_settings['button_styles']['no_thanks'] : '', 'primary' ); ?>></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][no_thanks]" value="secondary" data-button-target="wpsl-no-thanks" data-style-type="secondary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['no_thanks'] ) ? $section_settings['button_styles']['no_thanks'] : '', 'secondary' ); ?>></td>
            </tr>
            <tr class="<?php echo esc_attr( $show_streetview_button ); ?>">
                <td>
                    <?php esc_html_e( 'Streetview', 'wp-store-locator' ); ?>
                    <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Google Maps only, requires the \'If street view is available for the current location, then show a "Street view" link in the info window?\'', 'wp-store-locator' ); ?></span></span>
                </td>
                <td><input type="radio" name="wpsl_appearance[button_styles][streetview]" value="primary" data-button-target="wpsl-streetview" data-style-type="primary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['streetview'] ) ? $section_settings['button_styles']['streetview'] : '', 'primary' ); ?>></td>
                <td><input type="radio" name="wpsl_appearance[button_styles][streetview]" value="secondary" data-button-target="wpsl-streetview" data-style-type="secondary" class="wpsl-button-style-toggle" <?php checked( isset( $section_settings['button_styles']['streetview'] ) ? $section_settings['button_styles']['streetview'] : '', 'secondary' ); ?>></td>
            </tr>
        </tbody>
    </table>
</div>