<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-editor" class="postbox">
    <h3><span><?php esc_html_e( 'Store Editor', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p>
            <label for="wpsl-editor-country"><?php esc_html_e( 'Default country', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $section_settings['country'] ); ?>" name="wpsl_editor[default_country]" id="wpsl-editor-country">
        </p>
        <p>
            <label for="wpsl-editor-online-locations"><?php esc_html_e( 'Include online stores?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Online stores always appear first in the search results, and only need the website field to be filled in.', 'wp-store-locator' ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['enable_online_only'], true ); ?> name="wpsl_editor[enable_online_only]" id="wpsl-editor-online-locations">
        </p>
        <p class="wpsl-api-gmaps" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) != 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-editor-map-type"><?php esc_html_e( 'Map type for the location preview', 'wp-store-locator' ); ?></label>
            <?php echo $ui->create_dropdown( 'editor_map_types' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label for="wpsl-editor-hide-hours"><?php esc_html_e( 'Hide the opening hours?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['hide_hours'], true ); ?> name="wpsl_editor[hide_hours]" id="wpsl-editor-hide-hours" class="wpsl-has-conditional-option">
        </p>
        <div class="wpsl-conditional-option" <?php if ( $section_settings['hide_hours'] ) { echo 'style="display:none"'; } ?>>
            <?php if ( get_option( 'wpsl_legacy_support' ) ) { // Is only set for users who upgraded from 1.x ?>
                <p>
                    <label for="wpsl-editor-hour-input"><?php esc_html_e( 'Opening hours input type', 'wp-store-locator' ); ?></label>
                    <?php echo $ui->create_dropdown( 'hour_input' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </p>
                <?php
                /*
                 * Shown in both modes on purpose. The upgrade puts 1.x sites on
                 * 'textarea', so hiding this unless the dropdowns are selected
                 * kept it away from the only people it is written for.
                 */
                ?>
                <p class="wpsl-hour-notice">
                    <?php /* translators: 1: opening strong tag, 2: closing strong tag, 3: opening link tag to the Tools section, 4: closing link tag */ ?>
                    <em><?php echo wp_kses_post( sprintf( __( 'Opening hours created in version 1.x are %1$snot%2$s converted to the dropdown format automatically. The %3$sTools tab%4$s can convert the ones it can read.', 'wp-store-locator' ), '<strong>', '</strong>', '<a class="wpsl-trigger-nav" data-item="tools" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-tools' ) ) . '">', '</a>' ) ); ?></em>
                </p>
                <div class="wpsl-textarea-hours" <?php if ( $section_settings['hour_input'] !== 'textarea' ) { echo 'style="display:none"'; } ?>>
                    <?php /* Keep the label and textarea in one <p>: that is the flex row the settings page styles size both columns with. */ ?>
                    <p>
                        <label for="wpsl-textarea-hours"><?php esc_html_e( 'The default opening hours', 'wp-store-locator' ); ?></label>
                        <textarea rows="5" name="wpsl_editor[hours][textarea]" id="wpsl-textarea-hours"><?php if ( isset( $section_settings['hours']['textarea'] ) ) { echo esc_textarea( stripslashes( $section_settings['hours']['textarea'] ) ); } ?></textarea>
                    </p>
                </div>
            <?php } ?>
            <div class="wpsl-dropdown-hours" <?php if ( $section_settings['hour_input'] !== 'dropdown' ) { echo 'style="display:none"'; } ?>>
                <p>
                    <label for="wpsl-editor-hour-format"><?php esc_html_e( 'Opening hours format', 'wp-store-locator' ); ?></label>
                    <?php echo $ui->show_opening_hours_format(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </p>
                <p class="wpsl-default-hours wpsl-no-min-height"><strong><?php esc_html_e( 'The default opening hours', 'wp-store-locator' ); ?></strong></p>
                <?php echo wpsl_get_service( 'metaboxes' )->opening_hours( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
        </div>
        <p><em><?php esc_html_e( 'The default country and opening hours are only used when a new store is created. So changing the default values will have no effect on existing store locations.', 'wp-store-locator' ); ?></em></p>
        <?php do_action( 'wpsl_store_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>