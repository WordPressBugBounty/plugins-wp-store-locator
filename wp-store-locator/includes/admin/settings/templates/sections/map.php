<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-map" class="postbox">
    <h3><span><?php esc_html_e( 'Map', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p>
            <label for="wpsl-start-name"><?php esc_html_e( 'Start location', 'wp-store-locator' ); ?><span class="wpsl-info <?php if ( ! $section_settings['start_latlng'] ) { echo 'wpsl-warning'; } ?>"><span class="wpsl-info-text <?php if ( ! $section_settings['start_latlng'] && $section_settings['start_name'] ) { echo 'wpsl-missing-data'; } ?> wpsl-hide"><?php /* translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: line breaks, %4$s: opening span tag, %5$s: opening strong tag, %6$s: closing strong tag, %7$s: opening link tag, %8$s: closing link tag, %9$s: closing span tag */ echo wp_kses_post( sprintf( __( '%1$sRequired field.%2$s %3$s If auto-locating a user is disabled or fails, the center of the provided city or country will be used as the initial starting location for the user. %4$s %5$sWarning:%6$s obtaining the coordinates for the provided start location has failed. %7$sRead more%8$s %9$s', 'wp-store-locator' ), '<strong>', '</strong>', '<br><br>', '<span class="wpsl-conditional-info">', '<strong>', '</strong>', '<a href="#">', '</a>', '</span>' ) ); ?></span></span></label>
            <input type="text" autocomplete="off" value="<?php echo esc_attr( stripslashes( $section_settings['start_name'] ) ); ?>" name="wpsl_map[start_name]" id="wpsl-start-name">
            <input type="hidden" value="<?php echo esc_attr( $section_settings['start_latlng'] ); ?>" name="wpsl_map[start_latlng]" id="wpsl-latlng" />
        </p>
        <p class="wpsl-api-gmaps" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) != 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-map-type"><?php esc_html_e( 'Map type', 'wp-store-locator' ); ?></label>
            <?php echo $ui->create_dropdown( 'map_types' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label for="wpsl-zoom-level"><?php esc_html_e( 'Initial zoom level', 'wp-store-locator' ); ?></label>
            <?php echo $ui->show_zoom_levels(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label for="wpsl-max-zoom-level"><?php esc_html_e( 'Max auto zoom level', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( __( 'This value sets the zoom level for the "Zoom here" link in the info window. %s It is also used to limit the zooming when the viewport of the map is changed to make all the markers fit on the screen.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
            <?php echo $ui->create_dropdown( 'max_zoom_level' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label><?php esc_html_e( 'Zoom control position', 'wp-store-locator' ); ?></label>
            <span class="wpsl-radioboxes">
                <input type="radio" value="left" <?php checked( 'left', $section_settings['control_position'], true ); ?> name="wpsl_map[control_position]" id="wpsl-control-left">
                <label for="wpsl-control-left"><?php esc_html_e( 'Left', 'wp-store-locator' ); ?></label>
                <input type="radio" value="right" <?php checked( 'right', $section_settings['control_position'], true ); ?> name="wpsl_map[control_position]" id="wpsl-control-right">
                <label for="wpsl-control-right"><?php esc_html_e( 'Right', 'wp-store-locator' ); ?></label>
            </span>
        </p>
        <p>
            <label for="wpsl-autoload"><?php esc_html_e( 'Load locations on page load', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['autoload'], true ); ?> name="wpsl_map[autoload]" id="wpsl-autoload" class="wpsl-has-conditional-option">
        </p>
        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['autoload'] ) { echo 'style="display:none;"'; } ?>>
            <p>
                <label for="wpsl-autoload-start-latlng"><?php esc_html_e( 'Ignore the default start location on page load', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening strong tag, %3$s: closing strong tag */ echo wp_kses_post( sprintf( __( 'The locations shown on page load then have no start marker, distance, or directions link. All three return after a search. %1$s%2$sThe "Attempt to auto-locate the user" option overrules this option.%3$s', 'wp-store-locator' ), '<br><br>', '<strong>', '</strong>' ) ); ?></span></span></label>
                <input type="checkbox" value="" <?php checked( $section_settings['autoload_start_latlng'], true ); ?> name="wpsl_map[autoload_start_latlng]" id="wpsl-autoload-start-latlng">
            </p>
            <p>
                <label for="wpsl-autoload-limit"><?php esc_html_e( 'Number of locations to show', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( __( 'Although the location data is cached after the first load, a lower number will result in the map being more responsive. %s If this field is left empty or set to 0, all locations are loaded.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
                <input type="text" value="<?php echo esc_attr( $section_settings['autoload_limit'] ); ?>" name="wpsl_map[autoload_limit]" id="wpsl-autoload-limit">
            </p>
        </div>
        <p>
            <label for="wpsl-run-fitbounds"><?php esc_html_e( 'Auto adjust the zoom level to make sure all markers are visible?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This runs after a search is made, and makes sure all the returned locations are visible in the viewport.', 'wp-store-locator' ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['run_fitbounds'], true ); ?> name="wpsl_map[run_fitbounds]" id="wpsl-run-fitbounds">
        </p>
        <p class="wpsl-api-gmaps" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) != 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-streetview"><?php esc_html_e( 'Show the street view controls?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['streetview'], true ); ?> name="wpsl_map[streetview]" id="wpsl-streetview">
        </p>
        <p class="wpsl-api-gmaps" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) != 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-type-control"><?php esc_html_e( 'Show the map type control?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['type_control'], true ); ?> name="wpsl_map[type_control]" id="wpsl-type-control">
        </p>
        <p>
            <label for="wpsl-scollwheel-zoom"><?php esc_html_e( 'Enable scroll wheel zooming?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['scrollwheel'], true ); ?> name="wpsl_map[scrollwheel]" id="wpsl-scollwheel-zoom">
        </p>
        <p class="wpsl-credits">
            <label for="wpsl-show-credits"><?php esc_html_e( 'Show credits?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This will place a "Search provided by WP Store Locator" backlink below the map.', 'wp-store-locator' ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['show_credits'], true ); ?> name="wpsl_map[show_credits]" id="wpsl-show-credits">
        </p>
        <?php do_action( 'wpsl_map_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>