<?php
/**
 * The dialog that re-geocodes locations from the store list.
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="wpsl-geocode-locations" class="wpsl-hide" title="<?php esc_attr_e( 'Geocode locations', 'wp-store-locator' ); ?>">
    <?php /* Shown before anything is sent, so the size of the job is known up front. */ ?>
    <div class="wpsl-geocode-intro">
        <p class="wpsl-geocode-summary"></p>
    </div>

    <div class="wpsl-geocode-progress wpsl-hide">
        <?php
        $wpsl_progress_bar = new WPSL_Progress_Bar( [
            'current'     => 0,
            'total'       => 0,
            'size'        => 'small',
            'show_counts' => true,
            'label'       => esc_html__( 'Geocoding', 'wp-store-locator' )
        ] );
        echo $wpsl_progress_bar->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in WPSL_Progress_Bar.
        ?>

        <div class="wpsl-geocode-status-row">
            <span class="wpsl-geocode-status"></span>
            <span class="wpsl-geocode-timer">
                <?php esc_html_e( 'Elapsed', 'wp-store-locator' ); ?>: <strong>0s</strong>
            </span>
            <button type="button" class="button-link button-link-delete wpsl-geocode-cancel"><?php esc_html_e( 'Cancel', 'wp-store-locator' ); ?></button>
        </div>
    </div>

    <div class="wpsl-geocode-result wpsl-hide"></div>

    <div class="wpsl-geocode-failures wpsl-hide">
        <label for="wpsl-geocode-failure-log"><?php esc_html_e( 'Locations that could not be geocoded', 'wp-store-locator' ); ?></label>
        <textarea id="wpsl-geocode-failure-log" rows="7" readonly="readonly" spellcheck="false"></textarea>
    </div>

    <p class="wpsl-geocode-error wpsl-error-text wpsl-hide"></p>

    <div class="wpsl-geocode-actions">
        <a class="button button-primary" id="wpsl-geocode-start" href="#"><?php esc_html_e( 'Start geocoding', 'wp-store-locator' ); ?></a>
        <a class="button wpsl-hide" id="wpsl-geocode-close" href="#"><?php esc_html_e( 'Close', 'wp-store-locator' ); ?></a>
    </div>

    <input type="hidden" id="wpsl-geocode-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-geocode-locations' ) ); ?>" />
</div>