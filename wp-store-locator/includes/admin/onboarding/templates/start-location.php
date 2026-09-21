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
        <p><?php
        if ( $can_drag ) {
            esc_html_e( 'Drag the marker to the default start location, or provide the details in the field below.' , 'wp-store-locator' );
        } else {
            esc_html_e( 'Search for the default start location in the field below.' , 'wp-store-locator' );
        }
        ?></p>

        <div class="start-location-search">
            <input type="text" name="wpsl_map[start_name]" placeholder="<?php esc_html_e( 'Start location', 'wp-store-locator' ); ?>" value="<?php echo esc_attr( stripslashes( $map_settings['start_name'] ) ); ?>" id="wpsl-start-location">
            <input type="submit" class="button-primary" name="preview" value="<?php esc_html_e( 'Preview', 'wp-store-locator' ); ?>" id="wpsl-onboarding-location-preview" />
        </div>

        <div id="wpsl-onboarding-map"></div>

        <?php $this->action_buttons(); ?>
    </form>
</div>