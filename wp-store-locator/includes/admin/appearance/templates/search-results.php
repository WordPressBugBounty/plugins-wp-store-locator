<?php
/**
 * Search Results Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$ui = wpsl_get_service( 'admin_ui' );
$template_id = $wpsl_settings->get( 'appearance', 'template_id' );
?>

<?php require WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/partials/custom-section-notice.php'; ?>

<?php $preloader_color = wpsl_get_preloader_color(); ?>
<p>
    <label for="wpsl-preloader-color"><?php esc_html_e( 'Preloader color', 'wp-store-locator' ); ?></label>
    <?php echo $ui->create_dropdown( 'preloader_color' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
</p>
<div class="wpsl-conditional-option"<?php if ( $preloader_color !== 'custom' ) { echo ' style="display:none;"'; } ?>>
    <p>
        <label for="wpsl-preloader-custom-color"><?php esc_html_e( 'Custom color', 'wp-store-locator' ); ?></label>
        <input id="wpsl-preloader-custom-color" class="wpsl-color-field" name="wpsl_appearance[search][preloader_custom_color]" type="text" value="<?php echo esc_attr( isset( $section_settings['preloader_custom_color'] ) ? $section_settings['preloader_custom_color'] : '' ); ?>" data-default="#000000" />
    </p>
</div>
<p class="wpsl-results-columns" <?php if ( $template_id !== 'horizontal' ) { echo 'style="display:none"'; } ?>>
    <label for="wpsl-result-columns"><?php esc_html_e( 'Search results columns', 'wp-store-locator' ); ?></label>
    <?php echo $ui->create_dropdown( 'result_columns' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
</p>
<p class="wpsl-filter-layout-option" <?php if ( $template_id !== 'vertical' ) { echo 'style="display:none"'; } ?>>
    <label for="wpsl-filter-layout"><?php esc_html_e( 'Filter layout', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'How the result filter buttons are shown: in a horizontal row (wraps when there is not enough space), stacked full-width on top of each other, or nested under a single "Filters" button that expands to each filter and its options. Useful when there are several filters or a narrow panel.', 'wp-store-locator' ); ?></span></span></label>
    <?php echo $ui->create_dropdown( 'filter_layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
</p>
<?php if ( wpsl_get_service( 'appearance' )->has_result_card_item() ) : ?>
<p class="wpsl-moved-note"><?php esc_html_e( 'The icons, the categories and the More details link are under Result Card.', 'wp-store-locator' ); ?></p>
<?php else : ?>
<?php require WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/partials/result-card-fields.php'; ?>
<?php endif; ?>