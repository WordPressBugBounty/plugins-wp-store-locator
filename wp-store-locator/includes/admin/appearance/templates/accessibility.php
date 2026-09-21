<?php
/**
 * Accessibility Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings    = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$custom_focus_enabled = isset( $section_settings['custom_focus_outline'] ) ? (bool) $section_settings['custom_focus_outline'] : false;
?>

<p class="wpsl-conditional-overwrite">
    <label class="wpsl-tab-label" for="wpsl-custom-focus-outline"><?php esc_html_e( 'Set custom focus outline color', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( __( 'A custom focus outline can ensure better contrast between the focus element (input fields, dropdowns, buttons, or links) and the background. For example, a black outline on a black header, or a blue outline on a blue background.%s The browser or theme defaults are used when disabled.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
    <input type="checkbox" <?php checked( $custom_focus_enabled, true ); ?> name="wpsl_appearance[accessibility][custom_focus_outline]" id="wpsl-custom-focus-outline" class="wpsl-has-conditional-option">
</p>
<div class="wpsl-conditional-option"<?php if ( ! $custom_focus_enabled ) { echo ' style="display:none;"'; } ?>>
    <p class="wpsl-color-picker-field" data-elem="focus-outline">
        <?php /* translators: %s: line breaks */ ?>
        <label class="wpsl-tab-label wpsl-label-with-info" for="wpsl-style-focus-outline"><?php esc_html_e( 'Focus outline color', 'wp-store-locator' ); ?></label>
        <input id="wpsl-style-focus-outline" class="wpsl-color-field" name="wpsl_appearance[accessibility][focus_outline]" type="text" value="<?php echo esc_attr( isset( $section_settings['focus_outline'] ) ? $section_settings['focus_outline'] : '' ); ?>" />
    </p>
</div>