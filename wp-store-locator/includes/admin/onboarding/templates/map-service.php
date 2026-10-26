<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wpsl-onboarding" class="wpsl-onboarding-welcome">
    <h1><?php esc_html_e( 'Welcome to WP Store Locator' , 'wp-store-locator' ); ?></h1>
    <p class="wpsl-onboarding-intro"><?php esc_html_e( 'Pick the map service your store locator uses. You can switch at any time in Settings, so there is no wrong choice.', 'wp-store-locator' ); ?></p>

    <form id="wpsl-onboarding-map-service" method="post" action="<?php echo esc_url( add_query_arg( 'step', $this->nav_step() ) ); ?>">
        <?php $this->hidden_fields(); ?>

        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e( 'Map Service', 'wp-store-locator' ); ?></legend>
            <div class="wpsl-provider-grid">
                <?php $this->render_map_service_boxes(); ?>
            </div>
        </fieldset>

        <p class="wpsl-onboarding-hint">
            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 7v4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="8" cy="4.9" r="0.9" fill="currentColor"/></svg>
            <span><?php
                /* translators: 1: opening link tag, 2: closing link tag */
                echo sprintf( esc_html__( 'Bulk geocoding is needed to import locations from a spreadsheet with the %1$sCSV Manager%2$s add-on.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/add-ons/csv-manager/">', '</a>' ); ?></span>
        </p>

        <?php $this->action_buttons(); ?>
    </form>
</div>
