<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$active_map_service = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'active_map_service' );
?>

<section id="wpsl-gdpr" class="postbox">
    <h3><span><?php esc_html_e( 'GDPR', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p>
            <label for="wpsl-gdpr"><?php esc_html_e( 'GDPR consent handled by', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-gdpr-info-borlabs <?php if ( $section_settings['handler'] !== 'borlabs' ) { echo 'wpsl-hide'; } ?>"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to Borlabs Cookie, %2$s: closing link tag, %3$s: opening link tag to documentation, %4$s: closing link tag */ echo wp_kses_post( sprintf( __( 'How to configure %1$sBorlabs Cookie%2$s is described %3$shere%4$s.', 'wp-store-locator' ), '<a target="_blank" href="https://borlabs.io/borlabs-cookie/">', '</a>', '<a target="_blank" href="https://wpstorelocator.co/document/the-general-data-protection-regulation/">', '</a>' ) ); ?></span></span><span class="wpsl-info wpsl-gdpr-info-complianz <?php if ( $section_settings['handler'] !== 'complianz' ) { echo 'wpsl-hide'; } ?>"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to Complianz, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'The map is loaded once the visitor accepts the marketing category in %1$sComplianz%2$s. Complianz only blocks anything after its own setup wizard has been completed.', 'wp-store-locator' ), '<a target="_blank" href="https://complianz.io/">', '</a>' ) ); ?></span></span></label>
            <?php echo wpsl_get_service( 'admin_ui' )->create_dropdown( 'gdpr' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>

        <div <?php if ( $section_settings['handler'] !== 'wpsl' ) { echo 'style="display:none"'; } ?>>
            <p>
                <label for="wpsl-gdpr-description"><?php esc_html_e( 'Description', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text <?php if ( $section_settings['handler'] !== 'wpsl' ) { echo 'wpsl-hide'; } ?>"><?php esc_html_e( 'The placeholder [map_provider] will be replaced with the name of the active map provider. Any text placed between [policy_url] and [/policy_url] will automatically turn into a clickable link to that provider’s privacy policy.', 'wp-store-locator' ); ?></span></span></label>
                <textarea name="wpsl_gdpr[description]" id="wpsl-gdpr-description"><?php echo esc_textarea( $section_settings['description'] ); ?></textarea>
            </p>
        </div>

        <?php do_action( 'wpsl_gdpr_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>