<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * Name search never geocodes, so the geocoding-only region / language options
 * below are irrelevant for it.
 */
$is_name_search = ( $settings_manager->get( 'search', 'search_method' ) === 'name' );

/*
 * The Openrouteservice language only reaches its directions call, and that
 * call is skipped without an API key, or when the route opens on an external
 * site instead of the embedded map. Toggled live in JS
 * ( toggleDirectionsLanguage ), this guard keeps it hidden on page load.
 */
$directions_language_applies = $section_settings['openrouteservice_key'] && ! $settings_manager->get( 'ux', 'direction_redirect' );
?>

<section id="wpsl-api" class="postbox">
    <h3><span><?php esc_html_e( 'API', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p>
            <label for="wpsl-map-service"><?php esc_html_e( 'Map service', 'wp-store-locator' ); ?><span id="wpsl-name-search-notice" class="wpsl-info wpsl-warning" <?php if ( ! $is_name_search ) { echo 'style="display:none;"'; } ?>><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: line breaks */ echo wp_kses_post( sprintf( __( 'Some API options are hidden because the search method is set to %1$sname search%2$s in the Search section. %3$s Name search matches location names directly and never contacts the geocode API, so the API response language and country filter options have no effect. %3$s Set the search method back to %1$sGeocode API%2$s to make these options available again.', 'wp-store-locator' ), '<strong>', '</strong>', '<br><br>' ) ); ?></span></span></label>
            <?php echo $ui->create_dropdown( 'map_services' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <div class="wpsl-api-mapbox" <?php if ( $section_settings['active_map_service'] != 'mapbox' ) { echo 'style="display:none"'; } ?>>
            <p>
                <label for="wpsl-api-mapbox-key"><?php esc_html_e( 'API key', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to Mapbox signup, %2$s: closing link tag, %3$s: line breaks, %4$s: opening link tag to documentation, %5$s: closing link tag */ echo wp_kses_post( sprintf( __( 'An API key is required before you can use %1$sMapbox%2$s. %3$s Read more about how to configure the API key %4$shere%5$s.', 'wp-store-locator' ), '<a target="_blank" href="https://account.mapbox.com/auth/signup/">', '</a>', '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/create-mapbox-api-key/">', '</a>' ) ); ?></span></span></label>
                <span class="wpsl-key-field"><input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $section_settings['mapbox_key'] ); ?>" name="wpsl_api[mapbox_key]" placeholder="<?php esc_attr_e( 'Required', 'wp-store-locator' ); ?>" class="wpsl-key-input wpsl-required-field <?php if ( $section_settings['mapbox_key'] && ! get_option( 'wpsl_valid_mapbox_key' ) ) { echo 'wpsl-error'; } ?>" id="wpsl-api-mapbox-key"><?php echo wpsl_key_visibility_toggle( 'wpsl-api-mapbox-key', $section_settings['mapbox_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?></span>
            </p>
            <p>
                <label for="wpsl-api-mapbox-geocoder"><?php esc_html_e( 'Server geocoder', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening link tag to billing, %3$s: closing link tag, %4$s: opening link tag to documentation, %5$s: closing link tag */ echo wp_kses_post( sprintf( __( 'A request to the selected geocoding service is made when a new location is created and no coordinates are provided. %1$s In order to use Mapbox, provide a %2$svalid credit card%3$s before the results can be %4$spermanently stored%5$s.', 'wp-store-locator' ), '<br><br>', '<a target="_blank" href="https://console.mapbox.com/account/settings/billing/">', '</a>', '<a target="_blank" href="https://docs.mapbox.com/api/search/geocoding/#storing-geocoding-results">', '</a>') ); ?></span></span></label>
                <select id="wpsl-api-mapbox-geocoder" name="wpsl_api[mapbox_geocoder]">
                    <?php echo $api_settings->get_option_list( 'mapbox_geocoder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
            <p class="wpsl-has-preloader">
                <label for="wpsl-verify-mapbox-keys"><?php esc_html_e( 'Validate API key', 'wp-store-locator' ); ?></label>
                <a id="wpsl-verify-mapbox-keys" class="wpsl-verify-keys button <?php if ( ! $section_settings['mapbox_key'] ) { echo 'disabled'; } ?>" href="#"><?php esc_html_e( 'Show response', 'wp-store-locator' ); ?></a>
            </p>
            <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-api-mapbox-language"><?php esc_html_e( 'API response language', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to documentation, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Read more about the different levels of language support %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://docs.mapbox.com/api/search/geocoding/#language-coverage">', '</a>' ) ); ?></span></span></label>
                <select id="wpsl-api-mapbox-language" name="wpsl_api[mapbox_language]">
                    <?php echo $api_settings->get_option_list( 'mapbox_language' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
        </div>
        <div class="wpsl-api-stadia" <?php if ( $section_settings['active_map_service'] != 'stadia' ) { echo 'style="display:none"'; } ?>>
            <p>
                <label for="wpsl-api-stadia-key"><?php esc_html_e( 'API key', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to Stadia Maps signup, %2$s: closing link tag, %3$s: line breaks, %4$s: opening strong tag, %5$s: closing strong tag, %6$s: opening link tag to the Stadia Maps pricing page, %7$s: closing link tag, %8$s: opening link tag to documentation, %9$s: closing link tag */ echo wp_kses_post( sprintf( __( 'An API key is required before you can use %1$sStadia Maps%2$s. %3$s The free plan %4$sdoes not allow commercial use%5$s, see the %6$spricing page%7$s for the paid plans. %3$s Read more about how to create the API key %8$shere%9$s.', 'wp-store-locator' ), '<a target="_blank" href="https://client.stadiamaps.com/signup/">', '</a>', '<br><br>', '<strong>', '</strong>', '<a target="_blank" href="https://stadiamaps.com/pricing/">', '</a>', '<a target="_blank" href="https://wpstorelocator.co/document/create-stadia-maps-api-key/">', '</a>' ) ); ?></span></span></label>
                <span class="wpsl-key-field"><input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $section_settings['stadia_key'] ); ?>" name="wpsl_api[stadia_key]" placeholder="<?php esc_attr_e( 'Required', 'wp-store-locator' ); ?>" class="wpsl-key-input wpsl-required-field <?php if ( $section_settings['stadia_key'] && ! get_option( 'wpsl_valid_stadia_key' ) ) { echo 'wpsl-error'; } ?>" id="wpsl-api-stadia-key"><?php echo wpsl_key_visibility_toggle( 'wpsl-api-stadia-key', $section_settings['stadia_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?></span>
            </p>
            <p class="wpsl-has-preloader">
                <label for="wpsl-verify-stadia-keys"><?php esc_html_e( 'Validate API key', 'wp-store-locator' ); ?></label>
                <a id="wpsl-verify-stadia-keys" class="wpsl-verify-keys button <?php if ( ! $section_settings['stadia_key'] ) { echo 'disabled'; } ?>" href="#"><?php esc_html_e( 'Show response', 'wp-store-locator' ); ?></a>
            </p>
            <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-api-stadia-language"><?php esc_html_e( 'API response language', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to documentation, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Read more about language support %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://docs.stadiamaps.com/geocoding-search-autocomplete/search/#language">', '</a>' ) ); ?></span></span></label>
                <select id="wpsl-api-stadia-language" name="wpsl_api[stadia_language]">
                    <?php echo $api_settings->get_option_list( 'stadia_language' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
            <p>
                <label for="wpsl-api-stadia-eu-endpoints"><?php esc_html_e( 'Use EU endpoints', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening link tag to EU endpoints documentation, %3$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Route all API requests through Stadia Maps EU servers to comply with GDPR data residency requirements. %1$s This ensures that map tiles, geocoding, search, and routing requests are processed within the European Union. %1$s Read more about EU endpoints %2$shere%3$s.', 'wp-store-locator' ), '<br><br>', '<a target="_blank" href="https://docs.stadiamaps.com/eu-gdpr-endpoints/">', '</a>' ) ); ?></span></span></label>
                <input type="checkbox" value="1" id="wpsl-api-stadia-eu-endpoints" name="wpsl_api[stadia_eu_endpoints]" <?php checked( $section_settings['stadia_eu_endpoints'], 1 ); ?>>
            </p>
        </div>
        <div class="wpsl-api-osm" <?php if ( $section_settings['active_map_service'] != 'osm' ) { echo 'style="display:none"'; } ?>>
            <p>
                <label for="wpsl-api-openrouteservice-key"><?php esc_html_e( 'Openrouteservice API key', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening link tag to signup, %3$s: closing link tag */ echo wp_kses_post( sprintf( __( 'This is required if you wish to show the directions on the map itself. %1$s You can create an API key %2$shere%3$s.', 'wp-store-locator' ), '<br><br>', '<a target="_blank" href="https://account.heigit.org/signup">', '</a>' ) ); ?></span></span></label>
                <span class="wpsl-key-field"><input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $section_settings['openrouteservice_key'] ); ?>" name="wpsl_api[openrouteservice_key]" class="wpsl-key-input <?php if ( $section_settings['openrouteservice_key'] && ! get_option( 'wpsl_valid_openrouteservice_key' ) ) { echo ' wpsl-error'; } ?>" id="wpsl-api-openrouteservice-key"><?php echo wpsl_key_visibility_toggle( 'wpsl-api-openrouteservice-key', $section_settings['openrouteservice_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?></span>
            </p>
            <p class="wpsl-has-preloader">
                <label for="wpsl-verify-osm-keys"><?php esc_html_e( 'Validate API key', 'wp-store-locator' ); ?></label>
                <a id="wpsl-verify-osm-keys" class="wpsl-verify-keys button <?php if ( ! $section_settings['openrouteservice_key'] ) { echo 'disabled'; } ?>" href="#"><?php esc_html_e( 'Show response', 'wp-store-locator' ); ?></a>
            </p>
            <p <?php if ( ! $directions_language_applies ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-api-openrouteservice-language"><?php esc_html_e( 'Openrouteservice language', 'wp-store-locator' ); ?></label>
                <select id="wpsl-api-openrouteservice-language" name="wpsl_api[openrouteservice_language]">
                    <?php echo $api_settings->get_option_list( 'openrouteservice_language' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
            <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-api-osm-language"><?php esc_html_e( 'API response language', 'wp-store-locator' ); ?></label>
                <select id="wpsl-api-osm-language" name="wpsl_api[osm_language]">
                    <?php echo $api_settings->get_option_list( 'osm_language' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
        </div>
        <div class="wpsl-api-gmaps" <?php if ( $section_settings['active_map_service'] != 'gmaps' ) { echo 'style="display:none"'; } ?>>
            <?php
            /*
             * The short form of the Routes API alert. The Home page carries the
             * full explanation; this only has to catch someone who came here
             * to check the keys after directions stopped working.
             */
            if ( wpsl_container()->has( 'plugin_alerts' ) && wpsl_get_service( 'plugin_alerts' )->get_routes_api_alert() ) {
                ?>
            <div class="wpsl-warning-callout wpsl-routes-api-notice">
                <p>
                    <?php
                    printf(
                        /* translators: 1: opening link tag to the Routes API page in the Google Cloud console, 2: closing link tag, 3: opening link tag to the alerts on the Home page, 4: closing link tag */
                        esc_html__( 'Since 3.0 directions use the Google Routes API, which has to be %1$senabled in your Google Cloud project%2$s. The %3$salert on the Home page%4$s explains why and what to do.', 'wp-store-locator' ),
                        '<a href="' . esc_url( \WPSL\Admin\Core\Alerts::ROUTES_API_URL ) . '" target="_blank" rel="noopener noreferrer">',
                        '</a>',
                        '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_home#wpsl-home-alerts' ) ) . '">',
                        '</a>'
                    );
                    ?>
                </p>
            </div>
                <?php
            }
            ?>
            <p>
                <label for="wpsl-api-browser-key"><?php esc_html_e( 'Browser key', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to browser key documentation, %2$s: closing link tag, %3$s: opening link tag to JavaScript API, %4$s: closing link tag, %5$s: opening strong tag, %6$s: closing strong tag */ echo wp_kses_post( sprintf( __( 'A %1$sbrowser key%2$s allows you to monitor the usage of the Google Maps %3$sJavaScript API%4$s. %5$sRequired for Google Maps.%6$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key" target="_blank">', '</a>', '<a href="https://developers.google.com/maps/documentation/javascript/">', '</a>', '<br><br><strong>', '</strong>' ) ); ?></span></span></label>
                <span class="wpsl-key-field"><input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $section_settings['gmaps_browser_key'] ); ?>" name="wpsl_api[gmaps_browser_key]" placeholder="<?php esc_attr_e( 'Required', 'wp-store-locator' ); ?>" class="wpsl-key-input wpsl-required-field <?php if ( $section_settings['gmaps_browser_key'] && ! get_option( 'wpsl_valid_gmaps_browser_key' ) ) { echo ' wpsl-error'; } ?>" id="wpsl-api-browser-key"><?php echo wpsl_key_visibility_toggle( 'wpsl-api-browser-key', $section_settings['gmaps_browser_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?></span>            </p>
            <p>
                <label for="wpsl-api-server-key"><?php esc_html_e( 'Server key', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to server key documentation, %2$s: closing link tag, %3$s: opening link tag to Geocoding API, %4$s: closing link tag, %5$s: opening strong tag, %6$s: closing strong tag */ echo wp_kses_post( sprintf( __( 'A %1$sserver key%2$s allows you to monitor the usage of the Google Maps %3$sGeocoding API%4$s. %5$sRequired for Google Maps.%6$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/create-google-api-keys/#server-key" target="_blank">', '</a>', '<a href="https://developers.google.com/maps/documentation/geocoding/intro">', '</a>', '<br><br><strong>', '</strong>' ) ); ?></span></span></label>
                <span class="wpsl-key-field"><input type="password" autocomplete="new-password" spellcheck="false" value="<?php echo esc_attr( $section_settings['gmaps_server_key'] ); ?>" name="wpsl_api[gmaps_server_key]" placeholder="<?php esc_attr_e( 'Required', 'wp-store-locator' ); ?>" class="wpsl-key-input wpsl-required-field <?php if ( $section_settings['gmaps_server_key'] && ! get_option( 'wpsl_valid_gmaps_server_key' ) ) { echo ' wpsl-error'; } ?>" id="wpsl-api-server-key"><?php echo wpsl_key_visibility_toggle( 'wpsl-api-server-key', $section_settings['gmaps_server_key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?></span>
            </p>
            <p class="wpsl-js-required wpsl-has-preloader">
                <label for="wpsl-verify-gmap-keys"><?php esc_html_e( 'Validate API keys', 'wp-store-locator' ); ?></label>
                <a id="wpsl-verify-gmaps-keys" class="wpsl-verify-keys button <?php if ( ! $section_settings['gmaps_browser_key'] && ! $section_settings['gmaps_server_key'] ) { echo 'disabled'; } ?>" href="#"><?php esc_html_e( 'Show response', 'wp-store-locator' ); ?></a>
            </p>
            <p class="wpsl-api-gmaps" <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-api-language"><?php esc_html_e( 'API response language', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'By default the API will attempt to load the most appropriate language based on the users location or browser settings.', 'wp-store-locator' ); ?></span></span></label>
                <select id="wpsl-api-language" name="wpsl_api[gmaps_language]">
                    <?php echo $api_settings->get_option_list( 'gmaps_language' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
            <?php
            $rtype    = isset( $section_settings['region_restriction_type'] ) ? $section_settings['region_restriction_type'] : 'bias';
            $is_gmaps = ( $section_settings['active_map_service'] == 'gmaps' );
            ?>
            <p class="wpsl-api-gmaps" <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-region-restriction-type"><?php esc_html_e( 'Country filtering', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening strong tag, %3$s: closing strong tag */ echo wp_kses_post( sprintf( __( 'Choose how the selected countries affect your search results. %1$s %2$sBias (soft)%3$s favors a single country but still returns matches everywhere. %1$s %2$sRestrict (hard)%3$s limits the results to the selected countries (up to 15).', 'wp-store-locator' ), '<br><br>', '<strong>', '</strong>' ) ); ?></span></span></label>
                <select id="wpsl-region-restriction-type" name="wpsl_api[region_restriction_type]">
                    <option value="bias" <?php selected( $rtype, 'bias' ); ?>><?php esc_html_e( 'Bias results (soft)', 'wp-store-locator' ); ?></option>
                    <option value="restrict" <?php selected( $rtype, 'restrict' ); ?>><?php esc_html_e( 'Restrict results (hard)', 'wp-store-locator' ); ?></option>
                </select>
            </p>
            <p class="wpsl-api-gmaps" id="wpsl-region-bias-field" <?php if ( $is_name_search || $rtype === 'restrict' ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-api-region"><?php esc_html_e( 'Bias country', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks */ echo wp_kses_post( sprintf( __( 'Prefers results from the selected country when a search is ambiguous, but never excludes results elsewhere. %1$s For example, with Australia selected, a search for "Newcastle" favors the Newcastle in Australia over the one in the United Kingdom, but you can still search for other locations in the United Kingdom. %1$s The restricted option, on the other hand, only returns results from the selected countries.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
                <select id="wpsl-api-region" name="wpsl_api[gmaps_region]">
                    <?php echo $api_settings->get_option_list( 'gmaps_region' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </select>
            </p>
        </div>

        <div class="wpsl-multiselect-option wpsl-api-gmaps wpsl-api-osm wpsl-api-stadia wpsl-api-mapbox" id="wpsl-region-restrict-field" <?php if ( $is_name_search || ( $is_gmaps && $rtype !== 'restrict' ) ) { echo 'style="display:none"'; } ?>>
            <?php echo $ui->get_multiselect_list( 'countries' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </div>

        <?php do_action( 'wpsl_api_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
            <input type="hidden" id="wpsl-update-key-validation-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-update-key-validation' ) ); ?>"/>
        </p>
    </div>
</section>