<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the search settings
$search_settings = $wpsl_settings->get_group( 'search' );

// Online stores have their own listing section, offered on the same condition
// the front end collects it on.
$editor_settings = $wpsl_settings->get_group( 'editor' );

// Get translations service from container
$i18n->check_multilingual_plugins();

// Load the default section code from container
$template_sections = wpsl_get_service( 'template_sections' );
$section_editor    = wpsl_get_service( 'section_editor' );

$default_section = $template_sections->get( [ 'template' => 'default', 'section' => 'listing' ] );
$default_checked = ( $template_sections->check_custom_status( 'default_listing' ) ) ? true : false;

// The Sync Settings button only makes sense when there's actually
// something for it to find; computed from the same code the editor
// textarea below is initialized with, so there's no flash on load.
// JS keeps this in sync afterwards when Load / Restore Default / Apply
// change what's in the editor.
$section_analyzer  = wpsl_get_service( 'section_analyzer' );
$show_sync_button  = ! empty( $section_analyzer->analyze( $default_section['html'], [ 'section' => 'listing' ] ) );
?>
<section id="wpsl-section-editor" class="postbox wpsl-no-flex">
    <h3>
        <span><?php esc_html_e( 'Section Editor', 'wp-store-locator' ); ?></span>
        <a class="wpsl-show-sections wpsl-close-cross" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-themes' ) ); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        </a>
    </h3>
    <div class="inside">
        <div class="wpsl-section-actions">
            <label for="wpsl-template-list"><?php esc_html_e( 'Template', 'wp-store-locator' ); ?></label>
            <?php 
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template_dropdown() returns pre-escaped HTML with esc_attr() and esc_html()
            echo $section_editor->template_dropdown(); 
            ?>
            <label for="wpsl-section-list" class="wpsl-visually-hidden "><?php esc_html_e( 'Section', 'wp-store-locator' ); ?></label>
            <select id="wpsl-section-list">
                <option value="listing"><?php esc_html_e( 'List', 'wp-store-locator' ); ?></option>
                <option value="info_window"><?php esc_html_e( 'Info window', 'wp-store-locator' ); ?></option>
                <?php if ( $search_settings['number_results'] ) { ?>
                <option value="number_results"><?php esc_html_e( 'Number of results', 'wp-store-locator' ); ?></option>
                <?php } ?>
                <?php if ( $editor_settings['enable_online_only'] ) { ?>
                <option value="online"><?php esc_html_e( 'Online stores', 'wp-store-locator' ); ?></option>
                <?php } ?>
            </select>

            <?php
            /**
             * If a multilingual plugin is active,
             * then we show the language options
             */
            if ( $i18n->active_plugin ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- active_language_dropdown() returns pre-escaped HTML with esc_attr() and esc_html()
                echo $i18n->active_language_dropdown();
            }
            ?>

            <input id="wpsl-load-section" type="submit" data-action="load" value="<?php esc_html_e( 'Load', 'wp-store-locator' ); ?>" class="button-secondary">
            <input type="hidden" id="wpsl-load-section-nonce" name="wpsl_load_section_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-load_section' ) ); ?>"/>
        </div>

        <textarea id="wpsl-fancy-code-textarea"><?php echo esc_textarea( $default_section['html'] ); ?></textarea>

        <?php do_action( 'wpsl_section_editor_section' ); ?>
        <p class="wpsl-overwrite-section">
            <label class="wpsl-no-checkbox-spacing" style="flex: none;" for="wpsl-activate-custom-section"><?php esc_html_e( 'Activate this custom template section', 'wp-store-locator' ); ?></label>
            <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'When this is off, the section uses the default code generated from your settings, which a theme or plugin can still change through its template filter. Your saved code stays stored, but isn\'t used.', 'wp-store-locator' ); ?></span></span>
            <input type="checkbox" value="" <?php checked( $default_checked, true ); ?> name="wpsl_appearance[overwrite_sections]" id="wpsl-activate-custom-section">
        </p>

        <div class="wpsl-section-actions">
            <input id="wpsl-generate-field-code" type="submit" value="<?php esc_html_e( 'Create Field Code', 'wp-store-locator' ); ?>" class="button-secondary">
            <input id="wpsl-restore-section" data-action="restore" type="submit" value="<?php esc_html_e( 'Restore Default', 'wp-store-locator' ); ?>" title="<?php esc_attr_e( 'Regenerates this section using your current settings.', 'wp-store-locator' ); ?>" class="button-secondary">
            <span id="wpsl-compare-section-wrap" class="<?php echo $show_sync_button ? '' : 'wpsl-hide'; ?>">
                <input id="wpsl-compare-section" type="submit" value="<?php esc_attr_e( 'Sync Settings', 'wp-store-locator' ); ?>" class="button-secondary">
                <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Checks if any settings were changed that require the template code of this section to be updated. Found changes can be reviewed and applied to the code in the editor.', 'wp-store-locator' ); ?></span></span>
            </span>

            <input id="wpsl-save-section" type="submit" value="<?php esc_html_e( 'Save Section', 'wp-store-locator' ); ?>" class="button-primary">
            <input type="hidden" id="wpsl-save-section-nonce" name="wpsl_save_section_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-save_section' ) ); ?>"/>
            <input type="hidden" id="wpsl-restore-section-nonce" name="wpsl_restore_section_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-restore_section' ) ); ?>"/>
            <input type="hidden" id="wpsl-compare-section-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-compare_section' ) ); ?>"/>
            <input type="hidden" id="wpsl-merge-section-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-merge_section' ) ); ?>"/>
            <input type="hidden" id="wpsl-merge-languages-section-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-merge_languages_section' ) ); ?>"/>
        </div>
        <div id="wpsl-compare-dialog" style="display:none;"></div>
    </div>
</section>