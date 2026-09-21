<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-appearance" class="postbox wpsl-styled-radio">
    <h3><span><?php esc_html_e( 'Appearance', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <div class="wpsl-appearance-template-styles">
            <?php wpsl_get_service( 'appearance' )->template_styles_list(); ?>
        </div>
        <input type="hidden" id="wpsl-template-id" name="wpsl_appearance[template_id]" value="<?php echo esc_attr( $section_settings['template_id'] ); ?>" />
        <input type="hidden" id="wpsl-activate-template-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-activate-template' ) ); ?>"/>
    </div>
</section>