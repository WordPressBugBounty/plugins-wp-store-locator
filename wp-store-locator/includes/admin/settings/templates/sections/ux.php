<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-ux" class="postbox">
    <h3><span><?php esc_html_e( 'User Experience', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p id="wpsl-listing-below-option" <?php if ( $wpsl_settings->get( 'ux', 'template_id' ) != 'horizontal' ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-more-info-list"><?php esc_html_e( 'Hide the scrollbar?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['listing_below_no_scroll'], true ); ?> name="wpsl_ux[listing_below_no_scroll]" id="wpsl-listing-below-no-scroll">
        </p>
        <p>
            <label for="wpsl-new-window"><?php esc_html_e( 'Open links in a new window?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['new_window'], true ); ?> name="wpsl_ux[new_window]" id="wpsl-new-window">
        </p>
        <p>
            <label for="wpsl-reset-map"><?php esc_html_e( 'Show a reset map button?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['reset_map'], true ); ?> name="wpsl_ux[reset_map]" id="wpsl-reset-map">
        </p>
        <p class="wpsl-api-gmaps wpsl-api-osm wpsl-api-stadia" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) === 'mapbox' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-direction-redirect"><?php esc_html_e( 'Show the route on an external website?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( esc_html__( 'When enabled, clicking the directions link opens the route on an external website in a new window. %sWhen disabled, the route is drawn directly on the map embedded in your page.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span><?php if ( get_option( 'wpsl_valid_openrouteservice_key' ) !== '1' ) : ?><span class="wpsl-api-osm wpsl-info wpsl-warning"<?php if ( $settings_manager->get( 'api', 'active_map_service' ) !== 'osm' ) { echo ' style="display:none"'; } ?>><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening link tag to the API section, %3$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Rendering directions on the embedded OpenStreetMap requires a valid Openrouteservice API key. %1$s You can add one in the %2$sAPI section%3$s.', 'wp-store-locator' ), '<br><br>', '<a class="wpsl-trigger-nav" data-item="api" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) . '">', '</a>' ) ); ?></span></span><?php endif; ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['direction_redirect'], true ); ?> name="wpsl_ux[direction_redirect]" id="wpsl-direction-redirect">
        </p>

        <div class="wpsl-details-custom-locations">
            <div class="wpsl-multiselect-option">
                <?php echo $ui->get_multiselect_list( 'contact_details' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>

            <?php /* If the opening hours are globally disabled there's nothing to show, so hide the location picker. */ ?>
            <div class="wpsl-multiselect-option"<?php if ( $settings_manager->get( 'editor', 'hide_hours' ) ) { echo ' style="display:none;"'; } ?>>
                <?php echo $ui->get_multiselect_list( 'hours' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>

            <div class="wpsl-multiselect-option">
                <?php echo $ui->get_multiselect_list( 'description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
        </div>

        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['show_contact_details'] ) { echo 'style="display:none;"'; } ?>>
            <p>
                <label for="wpsl-clickable-contact-details"><?php esc_html_e( 'Make the contact details always clickable?', 'wp-store-locator' ); ?></label>
                <input type="checkbox" value="" <?php checked( $section_settings['clickable_contact_details'], true ); ?> name="wpsl_ux[clickable_contact_details]" id="wpsl-clickable-contact-details">
            </p>
        </div>
        <?php /* If the opening hours are globally disabled there's nothing to show, so hide the hour status options. */ ?>
        <div class="wpsl-hour-status-options"<?php if ( $settings_manager->get( 'editor', 'hide_hours' ) ) { echo ' style="display:none;"'; } ?>>
            <p>
                <label for="wpsl-hide-closed-hours"><?php esc_html_e( 'Hide days marked as closed in the opening hours?', 'wp-store-locator' ); ?></label>
                <input type="checkbox" value="" <?php checked( $section_settings['hide_closed_hours'], true ); ?> name="wpsl_ux[hide_closed_hours]" id="wpsl-hide-closed-hours">
            </p>
            <p>
                <label for="wpsl-show-hour-status"><?php esc_html_e( 'Show the current open / closed status?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( __( 'When this option is enabled, the results that are loaded automatically on page load are no longer cached. %s The open / closed status is part of the results, and serving it from a cache could show an outdated status.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
                <input type="checkbox" value="" <?php checked( $section_settings['show_hour_status'], true ); ?> name="wpsl_ux[show_hour_status]" id="wpsl-show-hour-status" class="wpsl-has-conditional-option">
            </p>
            <div class="wpsl-conditional-option" <?php if ( ! $section_settings['show_hour_status'] ) { echo 'style="display:none;"'; } ?>>
                <p class="wpsl-advanced">
                    <label for="wpsl-expand-hours"><?php esc_html_e( 'Make the current opening status expandable in the search results?', 'wp-store-locator' ); ?></label>
                    <input type="checkbox" value="" <?php checked( $section_settings['expand_hours'], true ); ?> name="wpsl_ux[expand_hours]" id="wpsl-expand-hours">
                </p>
            </div>
        </div>
        <p>
            <label for="wpsl-store-url"><?php esc_html_e( 'Make the store name clickable if a store URL exists?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to permalink settings, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'This only applies when %1$spermalinks%2$s are disabled. With permalinks enabled, the store name always links to its store page instead of the store URL.', 'wp-store-locator' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-permalink-settings' ) ) . '">', '</a>' ) ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['store_url'], true ); ?> name="wpsl_ux[store_url]" id="wpsl-store-url">
        </p>
        <p>
            <label for="wpsl-phone-url"><?php esc_html_e( 'Make the phone number clickable on mobile devices?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['phone_url'], true ); ?> name="wpsl_ux[phone_url]" id="wpsl-phone-url">
        </p>
        <p class="wpsl-api-gmaps" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) != 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-marker-streetview"><?php esc_html_e( 'Show a "Street view" link in the info window when available for the location?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( __( 'Enabling this option can sometimes result in a small delay in the opening of the info window. %s This happens because an API request is made to Google Maps to check if street view is available for the current location.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['marker_streetview'], true ); ?> name="wpsl_ux[marker_streetview]" id="wpsl-marker-streetview">
        </p>
        <p>
            <label for="wpsl-marker-zoom-to"><?php esc_html_e( 'Show a "Zoom here" link in the info window?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to zoom level setting, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Clicking this link will make the map zoom in to the %1$s max auto zoom level %2$s.', 'wp-store-locator' ), '<a href="#" class="wpsl-trigger-nav" data-item="map" data-focus="wpsl_map[max_auto_zoom]">', '</a>' ) ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['marker_zoom_to'], true ); ?> name="wpsl_ux[marker_zoom_to]" id="wpsl-marker-zoom-to">
        </p>
        <p>
            <label for="wpsl-mouse-focus"><?php esc_html_e( 'On page load move the mouse cursor to the search field?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening em tag, %3$s: closing em tag */ echo wp_kses_post( sprintf( __( 'If the store locator is not placed at the top of the page, enabling this feature can result in the page scrolling down. %1$s %2$sThis option is disabled on mobile devices.%3$s', 'wp-store-locator' ), '<br><br>', '<em>', '</em>' ) ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['mouse_focus'], true ); ?> name="wpsl_ux[mouse_focus]" id="wpsl-mouse-focus">
        </p>
        <p>
            <label for="wpsl-hide-country"><?php esc_html_e( 'Hide the country in the results?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['hide_country'], true ); ?> name="wpsl_ux[hide_country]" id="wpsl-hide-country">
        </p>
        <p>
            <label for="wpsl-hide-distance"><?php esc_html_e( 'Hide the distance in the results?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['hide_distance'], true ); ?> name="wpsl_ux[hide_distance]" id="wpsl-hide-distance">
        </p>
        <p>
            <label for="wpsl-bounce"><?php esc_html_e( 'When hovering over a search result, the matching marker', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: line breaks */ echo wp_kses_post( sprintf( __( 'If marker clusters are enabled, this option will not work as expected while the markers are clustered. %1$s The bouncing of the marker won\'t be visible at all, unless a user zooms in far enough for the marker cluster to change back to individual markers. %2$s The info window will open as expected, but it won\'t be clear to which marker it belongs to. ', 'wp-store-locator' ), '<br><br>' , '<br><br>' ) ); ?></span></span></label>
            <?php echo $ui->create_dropdown( 'marker_effects' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label for="wpsl-address-event"><?php esc_html_e( 'Clicking on the address details opens the info window on the map?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Currently not supported when marker clusters are used.', 'wp-store-locator' ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['address_event'], true ); ?> name="wpsl_ux[address_event]" id="wpsl-address-event">
        </p>
        <p>
            <label for="wpsl-address-format"><?php esc_html_e( 'Address format', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to documentation, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'You can add custom address formats with the %1$swpsl_address_formats%2$s filter.', 'wp-store-locator' ), '<a href="http://wpstorelocator.co/document/wpsl_address_formats/">', '</a>' ) ); ?></span></span></label>
            <?php echo $ui->create_dropdown( 'address_format' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <?php do_action( 'wpsl_ux_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>