<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$map_settings = $this->settings->get_group( 'map' );

/*
 * Without a reverse geocode the marker cannot name where it was dropped, so
 * the drag is withdrawn ( see wpsl-marker-drag-gate.js ) and the instructions
 * must not send anyone chasing a marker that will not move.
 */
$can_drag = wpsl_map_service_can_reverse_geocode( $this->get_map_service() );
?>

<div id="wpsl-onboarding">
    <form id="wpsl-onboarding-start-location" method="post" action="<?php echo esc_url( add_query_arg( 'step', $this->nav_step() ) ); ?>">
        <?php $this->hidden_fields(); ?>

        <input type="hidden" name="wpsl_map[start_latlng]" value="<?php echo esc_attr( $map_settings['start_latlng'] ); ?>" id="wpsl-latlng">

        <h1><?php esc_html_e( 'Where should the map start?', 'wp-store-locator' ); ?></h1>
        <p class="wpsl-onboarding-intro"><?php
        if ( $can_drag ) {
            esc_html_e( 'The map is centered here before a visitor searches. Search for a city or address, or drag the marker.' , 'wp-store-locator' );
        } else {
            esc_html_e( 'The map is centered here before a visitor searches. Search for a city or address.' , 'wp-store-locator' );
        }
        ?></p>

        <div class="start-location-search">
            <label for="wpsl-start-location" class="screen-reader-text"><?php esc_html_e( 'Start location', 'wp-store-locator' ); ?></label>
            <span class="wpsl-start-location-field">
                <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="7" cy="7" r="4.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10.6 10.6L14 14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <input type="text" name="wpsl_map[start_name]" placeholder="<?php esc_attr_e( 'Start location', 'wp-store-locator' ); ?>" value="<?php echo esc_attr( stripslashes( $map_settings['start_name'] ) ); ?>" id="wpsl-start-location">
            </span>
            <input type="submit" class="button button-secondary" name="preview" value="<?php esc_attr_e( 'Find on map', 'wp-store-locator' ); ?>" id="wpsl-onboarding-location-preview" />
        </div>

        <div id="wpsl-onboarding-map"></div>

        <?php $this->action_buttons(); ?>
    </form>
</div>
