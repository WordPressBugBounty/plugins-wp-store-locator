<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );

$active_map_service = $wpsl_settings->get( 'api', 'active_map_service' );

/*
 * Only sites that came from 1.x can hold opening hours as free-form text, and
 * the tool has nothing to offer once every location has been dealt with.
 * Locations it refused still count, so the row stays available for those.
 */
$has_legacy_hours = get_option( 'wpsl_legacy_support' ) && wpsl_get_service( 'hours_converter' )->has_legacy_hours();

// Only true while a 2.x -> 3.0 settings migration attempt has failed and hasn't been retried successfully yet.
$migration_failed = get_option( 'wpsl_v3_migration_complete' ) === 'in_progress';
?>

<section id="wpsl-tools" class="postbox">
    <h3><span><?php esc_html_e( 'Tools', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <?php if ( $migration_failed ) : ?>
        <p class="wpsl-has-preloader">
            <label for="wpsl-retry-migration"><?php esc_html_e( 'Settings migration', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The automatic migration of your v2 settings to the v3 format did not complete successfully. Retrying re-runs the full migration from your original v2 settings, so any changes made here since the failed attempt will be overwritten.', 'wp-store-locator' ); ?></span>
                </span>
            </label>
            <a class="button wpsl-retry-migration" id="wpsl-retry-migration" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpsl-retry-migration' ) ); ?>" href="#"><?php esc_html_e( 'Retry migration', 'wp-store-locator' ); ?></a>
        </p>
        <?php endif; ?>
        <p>
            <label for="wpsl-reset-data"><?php esc_html_e( 'Data management', 'wp-store-locator' ); ?></label>
            <a class="button" id="wpsl-reset-data" href="#"><?php esc_html_e( 'Reset data', 'wp-store-locator' ); ?></a>
        </p>
        <p>
            <label for="wpsl-debug"><?php esc_html_e( 'Enable store locator debug?', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <?php /* translators: %1$s: line breaks, %2$s: opening em tag, %3$s: closing em tag */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'This disables the WPSL transient cache. %1$sThe transient cache is only used if the %2$sLoad locations on page load%3$s option is enabled.', 'wp-store-locator' ), '<br><br>', '<em>', '</em>' ) ); ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $wpsl_settings->get( 'tools', 'debug' ), true ); ?> name="wpsl_tools[debug]" id="wpsl-debug">
        </p>
        <p class="wpsl-advanced">
            <label for="wpsl-disable-v3-css"><?php esc_html_e( 'Disable v3 theme rules?', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <?php /* translators: %s: line breaks */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'WPSL v3 applies additional CSS rules to fix potential styling conflicts between the store locator and WordPress theme styles. %s Disable this if it causes layout issues with your theme.', 'wp-store-locator' ), '<br><br>' ) ); ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $wpsl_settings->get( 'tools', 'disable_v3_css' ), true ); ?> name="wpsl_tools[disable_v3_css]" id="wpsl-disable-v3-css">
        </p>
        <p class="wpsl-api-gmaps wpsl-advanced" <?php if ( $active_map_service !== 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-deregister-gmaps"><?php esc_html_e( 'Enable compatibility mode?', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <?php /* translators: %s: line breaks */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'This option attempts to forcibly unload any other Google Maps scripts that may already be loaded on the page. %s Having multiple versions of Google Maps running simultaneously can lead to conflicts or errors.', 'wp-store-locator' ), '<br><br>' ) ); ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $wpsl_settings->get( 'tools', 'deregister_gmaps' ), true ); ?> name="wpsl_tools[deregister_gmaps]" id="wpsl-deregister-gmaps">
        </p>
        <p class="wpsl-has-preloader wpsl-advanced" <?php if ( $active_map_service !== 'osm' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-flush-nominatim-cache"><?php esc_html_e( 'Nominatim geocode cache', 'wp-store-locator' ); ?></label>
            <a class="button wpsl-flush-cache" id="wpsl-flush-nominatim-cache" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpsl-flush-nominatim-cache' ) ); ?>" href="#"><?php esc_html_e( 'Clear cache', 'wp-store-locator' ); ?></a>
        </p>
        <p class="wpsl-has-preloader wpsl-advanced">
            <label for="wpsl-flush-transient-cache"><?php esc_html_e( 'Autoload cache', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <?php /* translators: %1$s: line breaks, %2$s: opening em tag, %3$s: closing em tag */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'This clears the cached search results used by the %2$sLoad locations on page load%3$s option. %1$sThe stored coordinates for a start location or country restriction are removed as well, these are looked up again the next time the map loads.', 'wp-store-locator' ), '<br><br>', '<em>', '</em>' ) ); ?></span>
                </span>
            </label>
            <a class="button wpsl-flush-cache" id="wpsl-flush-transient-cache" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpsl-flush-transient-cache' ) ); ?>" href="#"><?php esc_html_e( 'Clear cache', 'wp-store-locator' ); ?></a>
        </p>
        <p class="wpsl-flex-center-align wpsl-js-required">
            <label for="wpsl-show-geocode-response"><?php esc_html_e( 'Show the Geocode API response for a location search', 'wp-store-locator' ); ?>
            <?php if ( ! wpsl_has_map_api_key() ): ?>
                <span class="wpsl-info">
                    <?php /* translators: %1$s: opening link tag to API settings, %2$s: closing link tag */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'This option is only available if you have a valid %1$sAPI key%2$s for the active map provider.', 'wp-store-locator' ), '<a class="wpsl-trigger-nav" data-item="api" href="#">', '</a>' ) ); ?></span>
                </span>
            <?php endif; ?>
            </label>
            <a id="wpsl-show-geocode-response" class="button <?php echo ( ! wpsl_has_map_api_key() ) ? ' disabled' : ''; ?>" href="#"><?php esc_html_e( 'Input location details', 'wp-store-locator' ); ?></a>
        </p>
        <?php if ( $has_legacy_hours ) : ?>
        <p class="wpsl-has-preloader wpsl-js-required">
            <label for="wpsl-convert-hours"><?php esc_html_e( 'Convert 1.x opening hours', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide"><?php
                        echo wp_kses_post( sprintf(
                            /* translators: 1: line breaks, 2: an example of hours that convert, 3: an example that is too vague to convert */
                            __( 'Locations created in version 1.x store their opening hours as plain text, which means no open / closed status and no "open now" filter. %1$sIt reads a day name followed by its times, one day per line, in either 12 or 24 hour notation ( %2$s ). Times without AM / PM ( %3$s ) are too vague to place.', 'wp-store-locator' ),
                            '<br><br>',
                            '<code>Mon 9:00 AM - 5:00 PM</code>',
                            '<code>Mon 9 - 5</code>'
                        ) );
                    ?></span>
                </span>
            </label>
            <a class="button" id="wpsl-convert-hours" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpsl-convert-hours' ) ); ?>" href="#"><?php esc_html_e( 'Review locations', 'wp-store-locator' ); ?></a>
        </p>
        <?php endif; ?>
        <?php do_action( 'wpsl_tools_settings_section' ); ?>
        <p>
            <label for="wpsl-run-setup-wizard"><?php esc_html_e( 'Setup wizard', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Steps back through the map provider, API keys and start location. It overwrites those settings as you go, so leave it if you only want to change one of them.', 'wp-store-locator' ); ?></span>
                </span>
            </label>
            <a class="button" id="wpsl-run-setup-wizard" href="<?php echo esc_url( admin_url( 'index.php?page=wpsl-onboarding' ) ); ?>"><?php esc_html_e( 'Run again', 'wp-store-locator' ); ?></a>
        </p>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>