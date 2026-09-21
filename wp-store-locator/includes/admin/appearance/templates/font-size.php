<?php
/**
 * Font Size Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$ui = wpsl_get_service( 'admin_ui' );
?>

<p class="wpsl-conditional-overwrite">
    <label class="wpsl-tab-label wpsl-no-checkbox-spacing" for="wpsl-overwrite-font-sizes"><?php esc_html_e( 'Overwrite theme font size defaults', 'wp-store-locator' ); ?></label>
    <input type="checkbox" <?php checked( isset( $section_settings['font_sizes']['overwrite_defaults'] ) ? $section_settings['font_sizes']['overwrite_defaults'] : false, true ); ?> name="wpsl_appearance[font_sizes][overwrite_defaults]" id="wpsl-overwrite-font-sizes" class="wpsl-has-conditional-option">
</p>
<div class="wpsl-conditional-option" <?php if ( isset( $section_settings['font_sizes']['overwrite_defaults'] ) && $section_settings['font_sizes']['overwrite_defaults'] ) { echo ''; } else { echo 'style="display:none;"'; } ?>>
    <p>
        <label for="wpsl-font-size-base"><?php esc_html_e( 'Base', 'wp-store-locator' ); ?></label>
        <?php echo $ui->create_slider( 'font_size_base' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
    </p>
    <p>
        <label for="wpsl-font-size-location-name"><?php esc_html_e( 'Location name', 'wp-store-locator' ); ?></label>
        <?php echo $ui->create_slider( 'font_size_location_name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
    </p>
    <p class="wpsl-cta-font-size-option" <?php if ( ! isset( $section_settings['cta']['enabled'] ) || ! $section_settings['cta']['enabled'] ) { echo 'style="display:none;"'; } ?>>
        <label for="wpsl-font-size-cta-buttons"><?php esc_html_e( 'CTA Buttons', 'wp-store-locator' ); ?></label>
        <?php echo $ui->create_slider( 'font_size_cta_buttons' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
    </p>
</div>