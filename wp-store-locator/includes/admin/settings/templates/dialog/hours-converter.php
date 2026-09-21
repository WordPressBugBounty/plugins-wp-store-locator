<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wpsl-hours-converter" class="wpsl-hide" title="<?php esc_attr_e( 'Convert 1.x opening hours', 'wp-store-locator' ); ?>">
    <div class="wpsl-hours-converter-bar">
        <p class="wpsl-hours-converter-summary"></p>
    </div>

    <div class="wpsl-hours-converter-progress wpsl-hide">
        <?php
        $wpsl_progress_bar = new WPSL_Progress_Bar( [
            'current'     => 0,
            'total'       => 0,
            'size'        => 'small',
            'show_counts' => true,
            'label'       => esc_html__( 'Converting', 'wp-store-locator' )
        ] );

        echo $wpsl_progress_bar->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in WPSL_Progress_Bar.
        ?>
    </div>

    <div class="wpsl-hours-converter-list" role="list"></div>

    <p class="wpsl-hours-converter-empty wpsl-hide"><?php esc_html_e( 'No locations with version 1.x opening hours were found.', 'wp-store-locator' ); ?></p>
    <p class="wpsl-hours-converter-error wpsl-error-text wpsl-hide"></p>

    <?php /* Sits at the foot of the dialog, where the geocode test keeps its actions. */ ?>
    <div class="wpsl-hours-converter-bulk">
        <span class="wpsl-info" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'wp-store-locator' ); ?>">
            <span class="wpsl-info-text wpsl-hide"><?php
                /* translators: %1$s: opening strong tag, %2$s: closing strong tag */
                echo wp_kses_post( sprintf( __( 'This rewrites the opening hours of every location it can read, and %1$scannot be undone%2$s. Make a backup of your database first. Locations it cannot read keep their current text.', 'wp-store-locator' ), '<strong>', '</strong>' ) );
            ?></span>
        </span>
        <a class="button button-primary" id="wpsl-convert-all" href="#"><?php esc_html_e( 'Auto convert all', 'wp-store-locator' ); ?></a>
    </div>

    <?php /* Replaces the convert action once there is nothing left to do. */ ?>
    <div class="wpsl-hours-converter-done wpsl-hide">
        <a class="button button-primary" id="wpsl-hours-converter-close" href="#"><?php esc_html_e( 'Close', 'wp-store-locator' ); ?></a>
    </div>
</div>