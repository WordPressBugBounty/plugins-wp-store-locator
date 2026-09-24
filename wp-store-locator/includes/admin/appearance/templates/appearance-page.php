<?php
/**
 * Appearance Editor Page Template
 *
 * @since  3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get required services
$wpsl_settings    = wpsl_get_service( 'wpsl_settings' );
$settings_service = wpsl_get_service( 'settings_service' );
$appearance       = wpsl_get_service( 'appearance' );
$admin_settings   = wpsl_get_service( 'admin_settings' );

// Get appearance settings
$section_settings = $wpsl_settings->get_group( 'appearance' );
$template_id = $wpsl_settings->get( 'appearance', 'template_id' );
?>

<script>(function(){if(sessionStorage.getItem('wpsl_reopen_tab')){document.documentElement.classList.add('wpsl-restoring-tab');}})();</script>
<div id="wpsl-content-wrap" class="wpsl-settings-grid wpsl-appearance-editor">
    <div class="wpsl-appearance-nav" id="wpsl-appearance-nav">
        <div class="wpsl-appearance-view wpsl-hidden" id="wpsl-menu-view">
            <nav>
                <ul id="wpsl-appearance-tabs">
                    <li data-id="templates"><a href="#wpsl-templates-tab"><span class="wpsl-icon-theme"><?php esc_html_e( 'Themes', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="theme-styles"><a href="#wpsl-theme-styles-tab"><span class="wpsl-icon-theme-styles"><?php esc_html_e( 'Theme Style', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="button-styles"><a href="#wpsl-button-styles-tab"><span class="wpsl-icon-button-styles"><?php esc_html_e( 'Button Styles', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="map-styles"><a href="#wpsl-map-styles-tab"><span class="wpsl-icon-map"><?php esc_html_e( 'Map Style', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="font-size"><a href="#wpsl-font-size-tab"><span class="wpsl-icon-font-size"><?php esc_html_e( 'Font Size', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="dimensions"<?php echo $appearance->has_template_preview( $template_id ) ? '' : ' style="display:none;"'; ?>><a href="#wpsl-dimensions-tab"><span class="wpsl-icon-dimensions"><?php esc_html_e( 'Dimensions', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="search-results"><a href="#wpsl-search-results-tab"><span class="wpsl-icon-search"><?php esc_html_e( 'Search Results', 'wp-store-locator' ); ?></span></a></li>
                    <li data-id="accessibility"><a href="#wpsl-accessibility-tab"><span class="wpsl-icon-accessibility"><?php esc_html_e( 'Accessibility', 'wp-store-locator' ); ?></span></a></li>
                </ul>
            </nav>
        </div>
        
        <!-- Tab Content Views -->
        <div id="wpsl-settings-content">
            <form id="wpsl-appearance-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" autocomplete="off" accept-charset="utf-8">
                <input type="hidden" name="action" value="wpsl_save_appearance">
                <?php wp_nonce_field( 'wpsl-save-appearance', 'wpsl_save_appearance_nonce' ); ?>
                <?php echo $appearance->get_tab_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </form>
        </div>
    </div>

    <div id="wpsl-appearance-preview" data-template="<?php echo esc_attr( $template_id ); ?>">
        <div id="wpsl-default-view" class="wpsl-appearance-view wpsl-customize-options" >
            <div class="wpsl-styled-template-preview<?php echo $wpsl_settings->get( 'tools', 'disable_v3_css' ) ? '' : ' wpsl-v3-css'; ?>">
                <?php echo $appearance->get_template( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
        </div>
    </div>
</div>