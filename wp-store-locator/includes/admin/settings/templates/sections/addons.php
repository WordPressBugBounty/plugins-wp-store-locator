<?php
/**
 * Add-Ons Settings Section
 * 
 * Creates the main section wrapper for addon settings.
 * Addons can output their content inside this section via the wpsl_addon_settings_output action.
 * 
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-addons" class="postbox">
    <h3><span><?php esc_html_e( 'Add-Ons', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <?php
        /**
         * Allow addons to output their settings content.
         * 
         * Addons should output their content directly without wrapping it in a section element.
         * The section wrapper is provided by the core plugin.
         * 
         * Example addon output:
         * <h4>Addon Name</h4>
         * <form>
         *     <!-- Settings fields here -->
         * </form>
         */
        do_action( 'wpsl_addon_settings_output' );
        ?>
    </div>
</section>