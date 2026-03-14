<?php
if ( !defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All $wpsl_admin method calls return pre-escaped HTML elements.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variables in template file, not globals.

global $wpdb, $wpsl, $wpsl_admin, $wp_version, $wpsl_settings;

$borlabs_exists = function_exists( 'BorlabsCookieHelper' );
?>
<div id="wpsl-wrap" class="wrap wpsl-settings <?php if ( floatval( $wp_version ) < 3.8 ) { echo 'wpsl-pre-38'; } // Fix CSS issue with < 3.8 versions ?>">
	<h2>WP Store Locator <?php esc_html_e( 'Settings', 'wp-store-locator' ); ?></h2>

    <?php
    settings_errors();

    $tabs          = apply_filters( 'wpsl_settings_tab', array( 'general' => __( 'General', 'wp-store-locator' ) ) );
    $wpsl_licenses = apply_filters( 'wpsl_license_settings', array() );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking which settings tab to display, not processing form data
    $current_tab   = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';

    if ( $wpsl_licenses ) {
        $tabs['licenses'] = __( 'Licenses', 'wp-store-locator' );
    }

    // Default to the general tab if an unknow tab value is set
    if ( !array_key_exists( $current_tab, $tabs ) ) {
        $current_tab = 'general';
    }

    if ( count( $tabs ) > 1 ) {
        echo '<h2 id="wpsl-tabs" class="nav-tab-wrapper">';

        foreach ( $tabs as $tab_key => $tab_name ) {
            if ( !$current_tab && $tab_key == 'general' || $current_tab == $tab_key ) {
                $active_tab = 'nav-tab-active';
            } else {
                $active_tab = '';
            }

            echo '<a class="nav-tab ' . esc_attr( $active_tab ) . '" title="' . esc_attr( $tab_name ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings&tab=' . $tab_key ) ) . '">' . esc_attr( $tab_name ) . '</a>';
        }

        echo '</h2>';
    }
        
    if ( $wpsl_licenses && $current_tab == 'licenses' ) {
        ?>

        <form action="" method="post">
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Add-On', 'wp-store-locator' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'License Key', 'wp-store-locator' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'License Expiry Date', 'wp-store-locator' ); ?></th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php
                    foreach ( $wpsl_licenses as $wpsl_license ) {
                        $key = ( $wpsl_license['status'] == 'valid' ) ? esc_attr( $wpsl_license['key'] ) : '';
                        
                        echo '<tr>';
                        echo '<td>' . esc_html( $wpsl_license['name'] ) . '</td>';
                        echo '<td>';
                        echo '<input type="text" value="' . esc_attr( $key ) . '" name="wpsl_licenses[' . esc_attr( $wpsl_license['short_name'] ) . ']" />';
                        
                        if ( $wpsl_license['status'] == 'valid' ) {
                           echo '<input type="submit" class="button-secondary" name="' . esc_attr( $wpsl_license['short_name'] ) . '_license_key_deactivate" value="' . esc_attr__( 'Deactivate License',  'wp-store-locator' ) . '"/>';
                        }
                        
                        wp_nonce_field( $wpsl_license['short_name'] . '_license-nonce', $wpsl_license['short_name'] . '_license-nonce' );
                        
                        echo '</td>';
                        echo '<td>';
                        
                        if ( $wpsl_license['expiration'] && $wpsl_license['status'] == 'valid' ) {
                            echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $wpsl_license['expiration'] ) ) );
                        }
                        
                        echo '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button button-primary" id="submit" name="submit">
            </p>
        </form>
    <?php
    } else if ( $current_tab == 'general' || !$current_tab ) {
    ?>
    
    <div id="general">
        <form id="wpsl-settings-form" method="post" action="options.php" autocomplete="off" accept-charset="utf-8">
            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-api-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Google Maps API', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                                <label for="wpsl-api-browser-key"><?php esc_html_e( 'Browser key', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening browser key link tag, %2$s: closing link tag, %3$s: opening JavaScript API link tag, %4$s: closing link tag, %5$s: line break, %6$s: opening strong tag, %7$s: closing strong tag, %8$s: opening applications link tag, %9$s: closing link tag */ echo wp_kses_post( sprintf( __( 'A %1$sbrowser key%2$s allows you to monitor the usage of the Google Maps %3$sJavaScript API%4$s. %5$s %6$sRequired%7$s for %8$sapplications%9$s created after June 22, 2016.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/create-google-api-keys/#browser-key" target="_blank">', '</a>', '<a href="https://developers.google.com/maps/documentation/javascript/">', '</a>', '<br><br>', '<strong>', '</strong>', '<a href="https://googlegeodevelopers.blogspot.nl/2016/06/building-for-scale-updates-to-google.html">', '</a>' ) ); ?></span>
                                    </span>
                                </label>
                                <input type="text" value="<?php echo esc_attr( $wpsl_settings['api_browser_key'] ); ?>" name="wpsl_api[browser_key]" class="textinput" id="wpsl-api-browser-key">
                            </p>
                            <p>
                                <label for="wpsl-api-server-key"><?php esc_html_e( 'Server key', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening server key link tag, %2$s: closing link tag, %3$s: opening Geocoding API link tag, %4$s: closing link tag, %5$s: line break, %6$s: opening strong tag, %7$s: closing strong tag, %8$s: opening applications link tag, %9$s: closing link tag */ echo wp_kses_post( sprintf( __( 'A %1$sserver key%2$s allows you to monitor the usage of the Google Maps %3$sGeocoding API%4$s. %5$s %6$sRequired%7$s for %8$sapplications%9$s created after June 22, 2016.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/create-google-api-keys/#server-key" target="_blank">', '</a>', '<a href="https://developers.google.com/maps/documentation/geocoding/intro">', '</a>', '<br><br>', '<strong>', '</strong>', '<a href="https://googlegeodevelopers.blogspot.nl/2016/06/building-for-scale-updates-to-google.html">', '</a>' ) ); ?></span>
                                    </span>
                                </label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl_settings['api_server_key'] ); ?>" name="wpsl_api[server_key]"  class="textinput<?php if ( !get_option( 'wpsl_valid_server_key' ) ) { echo ' wpsl-validate-me wpsl-error'; } ?>" id="wpsl-api-server-key">
                            </p>
                            <p>
                                <label for="wpsl-verify-keys"><?php esc_html_e( 'Validate API keys', 'wp-store-locator' ); ?></label>
                                <a id="wpsl-verify-keys" class="button" href="#"><?php esc_html_e( 'Show response', 'wp-store-locator' ); ?></a>
                            </p>
                            <p>
                                <label for="wpsl-api-language"><?php esc_html_e( 'Map language', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'If no map language is selected the browser\'s prefered language is used.', 'wp-store-locator' ); ?></span>
                                    </span>
                                </label> 
                                <select id="wpsl-api-language" name="wpsl_api[language]">
                                    <?php echo $wpsl_admin->settings_page->get_api_option_list( 'language' ); ?>          	
                                </select>
                            </p>
                            <p>
                                <label for="wpsl-api-region"><?php esc_html_e( 'Map region', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening geocoding link tag, %2$s: closing link tag, %3$s: line break */ echo wp_kses_post( sprintf( __( 'This will bias the %1$sgeocoding%2$s results towards the selected region. %3$s If no region is selected the bias is set to the United States.', 'wp-store-locator' ), '<a href="https://developers.google.com/maps/documentation/javascript/geocoding#Geocoding">', '</a>', '<br><br>' ) ); ?></span>
                                    </span>
                                </label> 
                                <select id="wpsl-api-region" name="wpsl_api[region]">
                                    <?php echo $wpsl_admin->settings_page->get_api_option_list( 'region' ); ?>
                                </select>
                            </p>
                            <p id="wpsl-geocode-component" <?php if ( !$wpsl_settings['api_region'] ) { echo 'style="display:none;"'; } ?>>
                                <label for="wpsl-api-component"><?php esc_html_e( 'Restrict the geocoding results to the selected map region?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening geocoding link tag, %2$s: closing link tag, %3$s: first line break, %4$s: second line break, %5$s: opening filter link tag, %6$s: closing link tag */ echo wp_kses_post( sprintf( __( 'If the %1$sgeocoding%2$s API finds more relevant results outside of the set map region ( some location names exist in multiple regions ), the user will likely see a "No results found" message. %3$s To rule this out you can restrict the results to the set map region. %4$s You can modify the used restrictions with %5$sthis%6$s filter.', 'wp-store-locator' ), '<a href="https://developers.google.com/maps/documentation/javascript/geocoding#Geocoding">', '</a>', '<br><br>', '<br><br>', '<a href="http://wpstorelocator.co/document/wpsl_geocode_components">', '</a>' ) ); ?></span>
                                    </span>
                                </label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['api_geocode_component'], true ); ?> name="wpsl_api[geocode_component]" id="wpsl-api-component">
                            </p>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-search-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Search', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                                <label for="wpsl-search-autocomplete"><?php esc_html_e( 'Enable autocomplete?', 'wp-store-locator' ); ?></label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['autocomplete'], true ); ?> name="wpsl_search[autocomplete]" id="wpsl-search-autocomplete" class="wpsl-has-conditional-option">
                            </p>
                            <?php $autocomplete_warning = false; ?>

                            <div class="wpsl-conditional-option" <?php if ( ! $wpsl_settings['autocomplete'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                    <label for="wpsl-autocomplete-api-versions"><?php esc_html_e( 'Autocomplete source', 'wp-store-locator' ); ?>
                                        <span class="wpsl-info">
                                            <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: first line break, %2$s: second line break, %3$s: opening link tag, %4$s: closing link tag */ echo wp_kses_post( sprintf( __( 'If your API keys were created before March 1, 2025, you can keep using the Places Autocomplete Service. %1$s However, if your API keys were created after this date, you must use the Autocomplete Data API. %2$s %3$sRead more%4$s', 'wp-store-locator' ), '<br><br>', '<br><br>', '<a href="https://wpstorelocator.co/migrate-to-the-new-places-api/" target="_blank">', '</a>' ) ); ?></span>
                                        </span>
                                    </label>
                                    <?php echo $wpsl_admin->settings_page->create_dropdown( 'autocomplete_api_versions' ); ?>
                                </p>
                            </div>
                            <p>
                                <label for="wpsl-force-postalcode"><?php esc_html_e( 'Force zipcode only search', 'wp-store-locator' ); ?>:
                                    <?php
                                    if ( $wpsl_settings['force_postalcode'] && ( !$wpsl_settings['api_geocode_component'] || !$wpsl_settings['api_region'] ) ) {
                                    ?>
                                        <span class="wpsl-info wpsl-required-setting"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line break, %2$s: opening link tag, %3$s: closing link tag */ echo wp_kses_post( sprintf( __( 'For this option to work correctly you need to set a map region and restrict the results to the selected region. %1$s You can do this in the %2$sGoogle Maps API section%3$s.', 'wp-store-locator' ), '<br><br>', '<a href="#wpsl-api-settings">', '</a>' ) ); ?></span></span>
                                    <?php
                                    }

                                    if ( $wpsl_settings['autocomplete'] && $wpsl_settings['force_postalcode'] ) {
                                        $autocomplete_warning = true;
                                    }
                                    ?>
                                    <span class="wpsl-info <?php if ( !$autocomplete_warning ) { echo 'wpsl-hide'; } ?> wpsl-required-setting wpsl-info-zip-only">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line break */ echo wp_kses_post( sprintf( __( "Zipcode only search does unfortunately not work well in combination with the autocomplete option. %s It's recommended to not have both options active at the same time.", "wp-store-locator" ), "<br><br>" ) ); ?></span>
                                    </span>
                                </label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['force_postalcode'], true ); ?> name="wpsl_search[force_postalcode]" id="wpsl-force-postalcode">
                            </p>
                            <p>
                                <label for="wpsl-results-dropdown"><?php esc_html_e( 'Show the max results dropdown?', 'wp-store-locator' ); ?></label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['results_dropdown'], true ); ?> name="wpsl_search[results_dropdown]" id="wpsl-results-dropdown">
                            </p>
                            <p>
                                <label for="wpsl-radius-dropdown"><?php esc_html_e( 'Show the search radius dropdown?', 'wp-store-locator' ); ?></label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['radius_dropdown'], true ); ?> name="wpsl_search[radius_dropdown]" id="wpsl-radius-dropdown">
                            </p>
                            <p>
                                <label for="wpsl-category-filters"><?php esc_html_e( 'Enable category filters?', 'wp-store-locator' ); ?></label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['category_filter'], true ); ?> name="wpsl_search[category_filter]" id="wpsl-category-filters" class="wpsl-has-conditional-option">
                            </p>
                            <div class="wpsl-conditional-option" <?php if ( !$wpsl_settings['category_filter'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                    <label for="wpsl-cat-filter-types"><?php esc_html_e( 'Filter type:', 'wp-store-locator' ); ?></label>
                                    <?php echo $wpsl_admin->settings_page->create_dropdown( 'filter_types' ); ?>           
                                </p>
                            </div>
                            <p>
                                <label for="wpsl-distance-unit"><?php esc_html_e( 'Distance unit', 'wp-store-locator' ); ?>:</label>                          
                                <span class="wpsl-radioboxes">
                                    <input type="radio" autocomplete="off" value="km" <?php checked( 'km', $wpsl_settings['distance_unit'] ); ?> name="wpsl_search[distance_unit]" id="wpsl-distance-km">
                                    <label for="wpsl-distance-km"><?php esc_html_e( 'km', 'wp-store-locator' ); ?></label>
                                    <input type="radio" autocomplete="off" value="mi" <?php checked( 'mi', $wpsl_settings['distance_unit'] ); ?> name="wpsl_search[distance_unit]" id="wpsl-distance-mi">
                                    <label for="wpsl-distance-mi"><?php esc_html_e( 'mi', 'wp-store-locator' ); ?></label>
                                </span>
                            </p>
                            <p>
                                <label for="wpsl-max-results"><?php esc_html_e( 'Max search results', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The default value is set between the [ ].', 'wp-store-locator' ); ?></span>
                                    </span>
                                </label>
                                <input type="text" value="<?php echo esc_attr( $wpsl_settings['max_results'] ); ?>" name="wpsl_search[max_results]" class="textinput" id="wpsl-max-results">
                            </p>
                            <p>
                                <label for="wpsl-search-radius"><?php esc_html_e( 'Search radius options', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The default value is set between the [ ].', 'wp-store-locator' ); ?></span>
                                    </span>
                                </label>
                                <input type="text" value="<?php echo esc_attr( $wpsl_settings['search_radius'] ); ?>" name="wpsl_search[radius]" class="textinput" id="wpsl-search-radius">
                            </p>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>        
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-map-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Map', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                                <label for="wpsl-auto-locate"><?php esc_html_e( 'Attempt to auto-locate the user', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Most modern browsers %1$srequire%2$s a HTTPS connection before the Geolocation feature works.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/html-5-geolocation-not-working/">', '</a>' ) ); ?></span>
                                    </span>
                                </label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['auto_locate'], true ); ?> name="wpsl_map[auto_locate]" id="wpsl-auto-locate">
                            </p>
                            <p>
                                <label for="wpsl-autoload"><?php esc_html_e( 'Load locations on page load', 'wp-store-locator' ); ?>:</label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['autoload'], true ); ?> name="wpsl_map[autoload]" id="wpsl-autoload" class="wpsl-has-conditional-option">
                            </p>
                            <div class="wpsl-conditional-option" <?php if ( !$wpsl_settings['autoload'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                    <label for="wpsl-autoload-limit"><?php esc_html_e( 'Number of locations to show', 'wp-store-locator' ); ?>:
                                        <span class="wpsl-info">
                                            <span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line break */ echo wp_kses_post( sprintf( __( 'Although the location data is cached after the first load, a lower number will result in the map being more responsive. %s If this field is left empty or set to 0, then all locations are loaded.', 'wp-store-locator' ), '<br><br>' ) ); ?></span>
                                        </span>
                                    </label>
                                    <input type="text" value="<?php echo esc_attr( $wpsl_settings['autoload_limit'] ); ?>" name="wpsl_map[autoload_limit]" class="textinput" id="wpsl-autoload-limit">
                                </p>
                            </div>
                            <p>
                                <label for="wpsl-start-name"><?php esc_html_e( 'Start point', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: line break */ echo wp_kses_post( sprintf( __( '%1$sRequired field.%2$s %3$s If auto-locating the user is disabled or fails, the center of the provided city or country will be used as the initial starting point for the user.', 'wp-store-locator' ), '<strong>', '</strong>', '<br><br>' ) ); ?></span>
                                    </span>
                                </label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl_settings['start_name'] ); ?>" name="wpsl_map[start_name]" class="textinput" id="wpsl-start-name">
                                <input type="hidden" value="<?php echo esc_attr( $wpsl_settings['start_latlng'] ); ?>" name="wpsl_map[start_latlng]" id="wpsl-latlng" />
                            </p>
                            <p>
                                <label for="wpsl-run-fitbounds"><?php esc_html_e( 'Auto adjust the zoom level to make sure all markers are visible?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This runs after a search is made, and makes sure all the returned locations are visible in the viewport.', 'wp-store-locator' ); ?></span>
                                    </span>
                                </label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['run_fitbounds'], true ); ?> name="wpsl_map[run_fitbounds]" id="wpsl-run-fitbounds">
                            </p>
                            <p>
                                <label for="wpsl-zoom-level"><?php esc_html_e( 'Initial zoom level', 'wp-store-locator' ); ?>:</label> 
                                <?php echo $wpsl_admin->settings_page->show_zoom_levels(); ?>
                            </p>
                            <p>
                                <label for="wpsl-max-zoom-level"><?php esc_html_e( 'Max auto zoom level', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line break, %2$s: max zoom level value */ echo wp_kses_post( sprintf( __( 'This value sets the zoom level for the "Zoom here" link in the info window. %1$s It is also used to limit the zooming when the viewport of the map is changed to make all the markers fit on the screen. The max auto zoom level is set to %2$s.', 'wp-store-locator' ), '<br><br>', esc_html( $wpsl_settings['auto_zoom_level'] ) ) ); ?></span>
                                    </span>
                                </label> 
                                <?php echo $wpsl_admin->settings_page->create_dropdown( 'max_zoom_level' ); ?>
                            </p> 

                            <p>
                                <label for="wpsl-zoom-controls"><?php esc_html_e( 'Show the zoom controls?', 'wp-store-locator' ); ?></label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['zoom_controls'], true ); ?> name="wpsl_map[zoom_controls]" id="wpsl-zoom-controls" class="wpsl-has-conditional-option">
                            </p>

                            <div class="wpsl-conditional-option" <?php if ( ! $wpsl_settings['zoom_controls'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                    <label><?php esc_html_e( 'Zoom control position', 'wp-store-locator' ); ?>:</label>
                                    <span class="wpsl-radioboxes">
                                        <input type="radio" autocomplete="off" value="left" <?php checked( 'left', $wpsl_settings['control_position'], true ); ?> name="wpsl_map[control_position]" id="wpsl-control-left">
                                        <label for="wpsl-control-left"><?php esc_html_e( 'Left', 'wp-store-locator' ); ?></label>
                                        <input type="radio" autocomplete="off" value="right" <?php checked( 'right', $wpsl_settings['control_position'], true ); ?> name="wpsl_map[control_position]" id="wpsl-control-right">
                                        <label for="wpsl-control-right"><?php esc_html_e( 'Right', 'wp-store-locator' ); ?></label>
                                    </span>
                                </p>
                            </div>

                            <p>
                                <label for="wpsl-fullscreen"><?php esc_html_e( 'Show the fullscreen controls?', 'wp-store-locator' ); ?></label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['fullscreen'], true ); ?> name="wpsl_map[fullscreen]" id="wpsl-fullscreen">
                            </p>

                            <p>
                                <label for="wpsl-streetview"><?php esc_html_e( 'Show the street view controls?', 'wp-store-locator' ); ?></label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['streetview'], true ); ?> name="wpsl_map[streetview]" id="wpsl-streetview">
                            </p>
                            <p>
                                <label for="wpsl-type-control"><?php esc_html_e( 'Show the map type control?', 'wp-store-locator' ); ?></label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['type_control'], true ); ?> name="wpsl_map[type_control]" id="wpsl-type-control">
                            </p>
                            <p>
                                <label for="wpsl-scollwheel-zoom"><?php esc_html_e( 'Enable scroll wheel zooming?', 'wp-store-locator' ); ?></label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['scrollwheel'], true ); ?> name="wpsl_map[scrollwheel]" id="wpsl-scollwheel-zoom">
                            </p>

                            <p>
                                <label for="wpsl-map-type"><?php esc_html_e( 'Map type', 'wp-store-locator' ); ?>:</label> 
                                <?php echo $wpsl_admin->settings_page->create_dropdown( 'map_types' ); ?>
                            </p>
                            <p>
                                <label for="wpsl-map-style"><?php esc_html_e( 'Map style', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Custom map styles only work if the map type is set to "Roadmap" or "Terrain".', 'wp-store-locator' ); ?></span>
                                    </span>
                                </label>
                            </p>
                            <div class="wpsl-style-input">
                                <p><?php /* translators: %1$s: opening Snazzy Maps link tag, %2$s: closing link tag, %3$s: opening Styling Wizard link tag, %4$s: closing link tag */ echo wp_kses_post( sprintf( __( 'You can use existing map styles from %1$sSnazzy Maps%2$s and paste it in the textarea below, or you can generate a custom map style through the %3$sGoogle Maps Platform Styling Wizard%4$s.', 'wp-store-locator' ), '<a target="_blank" href="http://snazzymaps.com">', '</a>', '<a target="_blank" href="https://mapstyle.withgoogle.com/">', '</a>' ) ); ?></p>
                                <textarea id="wpsl-map-style" name="wpsl_map[map_style]"><?php echo esc_textarea( $wpsl_admin->settings_page->get_map_style() ); ?></textarea>
                                <input type="submit" value="<?php esc_html_e( 'Preview Map Style', 'wp-store-locator' ); ?>" class="button-primary" name="wpsl-style-preview" id="wpsl-style-preview">
                            </div>
                            <div id="wpsl-gmap-wrap" class="wpsl-styles-preview"></div>
                            <p>
                               <label for="wpsl-show-credits"><?php esc_html_e( 'Show credits?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'This will place a "Search provided by WP Store Locator" backlink below the map.', 'wp-store-locator' ); ?></span>
                                    </span>
                               </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['show_credits'], true ); ?> name="wpsl_credits" id="wpsl-show-credits">
                            </p>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>        
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-user-experience" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'User Experience', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                                <label for="wpsl-design-height"><?php esc_html_e( 'Store Locator height', 'wp-store-locator' ); ?>:</label> 
                                <input size="3" value="<?php echo esc_attr( $wpsl_settings['height'] ); ?>" id="wpsl-design-height" name="wpsl_ux[height]"> px
                            </p> 
                            <p>
                                <label for="wpsl-infowindow-width"><?php esc_html_e( 'Max width for the info window content', 'wp-store-locator' ); ?>:</label> 
                                <input size="3" value="<?php echo esc_attr( $wpsl_settings['infowindow_width'] ); ?>" id="wpsl-infowindow-width" name="wpsl_ux[infowindow_width]"> px
                            </p>
                            <p>
                                <label for="wpsl-search-width"><?php esc_html_e( 'Search field width', 'wp-store-locator' ); ?>:</label> 
                                <input size="3" value="<?php echo esc_attr( $wpsl_settings['search_width'] ); ?>" id="wpsl-search-width" name="wpsl_ux[search_width]"> px
                            </p>
                            <p>
                                <label for="wpsl-label-width"><?php esc_html_e( 'Search and radius label width', 'wp-store-locator' ); ?>:</label> 
                                <input size="3" value="<?php echo esc_attr( $wpsl_settings['label_width'] ); ?>" id="wpsl-label-width" name="wpsl_ux[label_width]"> px
                            </p> 
                            <p>
                               <label for="wpsl-store-template"><?php esc_html_e( 'Store Locator template', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line break, %2$s: opening link tag, %3$s: closing link tag */ echo wp_kses_post( sprintf( __( 'The selected template is used with the [wpsl] shortcode. %1$s You can add a custom template with the %2$swpsl_templates%3$s filter.', 'wp-store-locator' ), '<br><br>', '<a href="http://wpstorelocator.co/document/wpsl_templates/">', '</a>' ) ); ?></span>
                                    </span>
                                </label> 
                               <?php echo $wpsl_admin->settings_page->show_template_options(); ?>
                            </p>
                            <p id="wpsl-listing-below-no-scroll" <?php if ( $wpsl_settings['template_id'] != 'below_map' ) { echo 'style="display:none;"'; } ?>>
                                <label for="wpsl-more-info-list"><?php esc_html_e( 'Hide the scrollbar?', 'wp-store-locator' ); ?></label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['listing_below_no_scroll'], true ); ?> name="wpsl_ux[listing_below_no_scroll]" id="wpsl-listing-below-no-scroll">
                            </p>
                            <p>
                               <label for="wpsl-new-window"><?php esc_html_e( 'Open links in a new window?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['new_window'], true ); ?> name="wpsl_ux[new_window]" id="wpsl-new-window">
                            </p>
                            <p>
                               <label for="wpsl-reset-map"><?php esc_html_e( 'Show a reset map button?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['reset_map'], true ); ?> name="wpsl_ux[reset_map]" id="wpsl-reset-map">
                            </p> 
                            <p>
                               <label for="wpsl-direction-redirect"><?php esc_html_e( 'When a user clicks on "Directions", open a new window, and show the route on google.com/maps ?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['direction_redirect'], true ); ?> name="wpsl_ux[direction_redirect]" id="wpsl-direction-redirect">
                            </p>

                            <p>
                               <label for="wpsl-more-info"><?php esc_html_e( 'Show a "More info" link in the store listings?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php echo esc_html( __( 'This places a "More Info" link below the address and will show the phone, fax, email, opening hours and description once the link is clicked.', 'wp-store-locator' ) ); ?></span>
                                    </span>
                                </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['more_info'], true ); ?> name="wpsl_ux[more_info]" id="wpsl-more-info" class="wpsl-has-conditional-option">
                            </p>   
                            <div class="wpsl-conditional-option" <?php if ( !$wpsl_settings['more_info'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                    <label for="wpsl-more-info-list"><?php esc_html_e( 'Where do you want to show the "More info" details?', 'wp-store-locator' ); ?></label>
                                    <?php echo $wpsl_admin->settings_page->create_dropdown( 'more_info' ); ?>
                                </p>
                            </div>
                            <p>
                               <label for="wpsl-contact-details"><?php esc_html_e( 'Always show the contact details below the address in the search results?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['show_contact_details'], true ); ?> name="wpsl_ux[show_contact_details]" id="wpsl-contact-details">
                            </p>
                            <p>
                                <label for="wpsl-clickable-contact-details"><?php esc_html_e( 'Make the contact details always clickable?', 'wp-store-locator' ); ?></label>
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['clickable_contact_details'], true ); ?> name="wpsl_ux[clickable_contact_details]" id="wpsl-clickable-contact-details">
                            </p>
                            <p>
                               <label for="wpsl-store-url"><?php esc_html_e( 'Make the store name clickable if a store URL exists?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'If %1$spermalinks%2$s are enabled, the store name will always link to the store page.', 'wp-store-locator' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-permalink-settings' ) ) . '">', '</a>' ) ); ?></span>
                                    </span>
                                </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['store_url'], true ); ?> name="wpsl_ux[store_url]" id="wpsl-store-url">
                            </p>
                            <p>
                               <label for="wpsl-phone-url"><?php esc_html_e( 'Make the phone number clickable on mobile devices?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['phone_url'], true ); ?> name="wpsl_ux[phone_url]" id="wpsl-phone-url">
                            </p>
                            <p>
                               <label for="wpsl-marker-streetview"><?php esc_html_e( 'If street view is available for the current location, then show a "Street view" link in the info window?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line break */ echo wp_kses_post( sprintf( __( 'Enabling this option can sometimes result in a small delay in the opening of the info window. %s This happens because an API request is made to Google Maps to check if street view is available for the current location.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['marker_streetview'], true ); ?> name="wpsl_ux[marker_streetview]" id="wpsl-marker-streetview">
                            </p>
                            <p>
                               <label for="wpsl-marker-zoom-to"><?php esc_html_e( 'Show a "Zoom here" link in the info window?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Clicking this link will make the map zoom in to the %1$s max auto zoom level %2$s.', 'wp-store-locator' ), '<a href="#wpsl-zoom-level">', '</a>' ) ); ?></span>
                                    </span>
                                </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['marker_zoom_to'], true ); ?> name="wpsl_ux[marker_zoom_to]" id="wpsl-marker-zoom-to">
                            </p>
                            <p>
                               <label for="wpsl-mouse-focus"><?php esc_html_e( 'On page load move the mouse cursor to the search field?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line break, %2$s: opening em tag, %3$s: closing em tag */ echo wp_kses_post( sprintf( __( 'If the store locator is not placed at the top of the page, enabling this feature can result in the page scrolling down. %1$s %2$sThis option is disabled on mobile devices.%3$s', 'wp-store-locator' ), '<br><br>', '<em>', '</em>' ) ); ?></span>
                                    </span>
                                </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['mouse_focus'], true ); ?> name="wpsl_ux[mouse_focus]" id="wpsl-mouse-focus">
                            </p>
                            <p>
                               <label for="wpsl-infowindow-style"><?php esc_html_e( 'Use the default style for the info window?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: line break */ echo wp_kses_post( sprintf( __( 'If the default style is disabled the %1$sInfoBox%2$s library will be used instead. %3$s This enables you to easily change the look and feel of the info window through the .wpsl-infobox css class.', 'wp-store-locator' ), '<a href="https://github.com/googlemaps/v3-utility-library/tree/master/archive/infobox" target="_blank">', '</a>', '<br><br>' ) ); ?></span>
                                    </span>
                                </label>
                               <input type="checkbox" value="default" <?php checked( $wpsl_settings['infowindow_style'], 'default' ); ?> name="wpsl_ux[infowindow_style]" id="wpsl-infowindow-style">
                            </p>
                            <p>
                               <label for="wpsl-hide-country"><?php esc_html_e( 'Hide the country in the search results?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['hide_country'], true ); ?> name="wpsl_ux[hide_country]" id="wpsl-hide-country">
                            </p>
                            <p>
                               <label for="wpsl-hide-distance"><?php esc_html_e( 'Hide the distance in the search results?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['hide_distance'], true ); ?> name="wpsl_ux[hide_distance]" id="wpsl-hide-distance">
                            </p>
                            <p>
                               <label for="wpsl-bounce"><?php esc_html_e( 'If a user hovers over the search results the store marker', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: first line break, %2$s: second line break */ echo wp_kses_post( sprintf( __( 'If marker clusters are enabled this option will not work as expected as long as the markers are clustered. %1$s The bouncing of the marker won\'t be visible at all unless a user zooms in far enough for the marker cluster to change back in to individual markers. %2$s The info window will open as expected, but it won\'t be clear to which marker it belongs to. ', 'wp-store-locator' ), '<br><br>' , '<br><br>' ) ); ?></span>
                                    </span>
                                </label> 
                               <?php echo $wpsl_admin->settings_page->create_dropdown( 'marker_effects' ); ?>
                            </p>  
                            <p>
                                <label for="wpsl-address-format"><?php esc_html_e( 'Address format', 'wp-store-locator' ); ?>:
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'You can add custom address formats with the %1$swpsl_address_formats%2$s filter.', 'wp-store-locator' ), '<a href="http://wpstorelocator.co/document/wpsl_address_formats/">', '</a>' ) ); ?></span>
                                    </span>
                                </label> 
                               <?php echo $wpsl_admin->settings_page->create_dropdown( 'address_format' ); ?>
                            </p>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>        
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-marker-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Markers', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <?php echo $wpsl_admin->settings_page->show_marker_options(); ?>
                            <p>
                               <label for="wpsl-marker-clusters"><?php esc_html_e( 'Enable marker clusters?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Recommended for maps with a large amount of markers.', 'wp-store-locator' ); ?></span>
                                    </span>
                                </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['marker_clusters'], true ); ?> name="wpsl_map[marker_clusters]" id="wpsl-marker-clusters" class="wpsl-has-conditional-option">
                            </p>

                            <?php if ( $borlabs_exists && $wpsl_settings['delay_loading'] && ! $wpsl_settings['direction_redirect'] && $wpsl_settings['marker_clusters'] ) { ?>
                                <?php /* translators: %1$s and %2$s: line breaks */ ?>
                                <div class="wpsl-callout"><?php echo wp_kses_post( sprintf( __( 'There is a problem with the marker clusters and Borlabs Cookie plugin in combination with the option to show the directions on the map. %1$s It results in the marker clusters not being removed from the map after the user clicked on "directions". %2$s To prevent this, you can either disable the marker cluster option or enable the option: "When a user clicks on "Directions", open a new window, and show the route on google.com/maps ?".', 'wp-store-locator' ), '<br><br>', '<br><br>' ) ); ?></div>
                            <?php } ?>

                            <div class="wpsl-conditional-option" <?php if ( !$wpsl_settings['marker_clusters'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                   <label for="wpsl-marker-zoom"><?php esc_html_e( 'Max zoom level', 'wp-store-locator' ); ?>:
                                        <span class="wpsl-info">
                                            <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'If this zoom level is reached or exceeded, then all markers are moved out of the marker cluster and shown as individual markers.', 'wp-store-locator' ); ?></span>
                                        </span>
                                    </label> 
                                   <?php echo $wpsl_admin->settings_page->show_cluster_options( 'cluster_zoom' ); ?>
                                </p>
                                <p>
                                   <label for="wpsl-marker-cluster-size"><?php esc_html_e( 'Cluster size', 'wp-store-locator' ); ?>:
                                        <span class="wpsl-info">
                                            <span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line break */ echo wp_kses_post( sprintf( __( 'The grid size of a cluster in pixels. %s A larger number will result in a lower amount of clusters and also make the algorithm run faster.', 'wp-store-locator' ), '<br><br>' ) ); ?></span>
                                        </span>
                                    </label> 
                                   <?php echo $wpsl_admin->settings_page->show_cluster_options( 'cluster_size' ); ?>
                                </p>
                            </div>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-store-editor-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Store Editor', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                                <label for="wpsl-editor-country"><?php esc_html_e( 'Default country', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'editor_country', '' ) ); ?>" name="wpsl_editor[default_country]" class="textinput" id="wpsl-editor-country">
                            </p>
                            <p>
                                <label for="wpsl-editor-map-type"><?php esc_html_e( 'Map type for the location preview', 'wp-store-locator' ); ?>:</label> 
                                <?php echo $wpsl_admin->settings_page->create_dropdown( 'editor_map_types' ); ?>
                            </p>
                            <p>
                                <label for="wpsl-editor-hide-hours"><?php esc_html_e( 'Hide the opening hours?', 'wp-store-locator' ); ?></label> 
                                <input type="checkbox" value="" <?php checked( $wpsl_settings['hide_hours'], true ); ?> name="wpsl_editor[hide_hours]" id="wpsl-editor-hide-hours" class="wpsl-has-conditional-option">
                            </p>
                            <div class="wpsl-conditional-option" <?php if ( $wpsl_settings['hide_hours'] ) { echo 'style="display:none"'; } ?>>
                                <?php if ( get_option( 'wpsl_legacy_support' ) ) { // Is only set for users who upgraded from 1.x ?>
                                <p>
                                    <label for="wpsl-editor-hour-input"><?php esc_html_e( 'Opening hours input type', 'wp-store-locator' ); ?>:</label> 
                                    <?php echo $wpsl_admin->settings_page->create_dropdown( 'hour_input' ); ?>
                                </p>
                                <p class="wpsl-hour-notice <?php if ( $wpsl_settings['editor_hour_input'] !== 'dropdown' ) { echo 'style="display:none"'; } ?>">
                                    <em><?php /* translators: %1$s: opening strong tag, %2$s: closing strong tag */ echo wp_kses_post( sprintf( __( 'Opening hours created in version 1.x %1$sare not%2$s automatically converted to the new dropdown format.', 'wp-store-locator' ), '<strong>', '</strong>' ) ); ?></em>
                                </p>
                                <div class="wpsl-textarea-hours" <?php if ( $wpsl_settings['editor_hour_input'] !== 'textarea' ) { echo 'style="display:none"'; } ?>>
                                    <p class="wpsl-default-hours"><strong><?php esc_html_e( 'The default opening hours', 'wp-store-locator' ); ?></strong></p>
                                    <textarea rows="5" cols="5" name="wpsl_editor[textarea]" id="wpsl-textarea-hours"><?php if ( isset( $wpsl_settings['editor_hours']['textarea'] ) ) { echo esc_textarea( stripslashes( $wpsl_settings['editor_hours']['textarea'] ) ); } ?></textarea>
                                </div>
                                <?php } ?>
                                <div class="wpsl-dropdown-hours" <?php if ( $wpsl_settings['editor_hour_input'] !== 'dropdown' ) { echo 'style="display:none"'; } ?>>
                                    <p>
                                        <label for="wpsl-editor-hour-format"><?php esc_html_e( 'Opening hours format', 'wp-store-locator' ); ?>:</label> 
                                        <?php echo $wpsl_admin->settings_page->show_opening_hours_format(); ?>
                                    </p>
                                    <p class="wpsl-default-hours"><strong><?php esc_html_e( 'The default opening hours', 'wp-store-locator' ); ?></strong></p>
                                    <?php echo $wpsl_admin->metaboxes->opening_hours( 'settings' ); ?>
                                </div>
                            </div>
                            <p><em><?php esc_html_e( 'The default country and opening hours are only used when a new store is created. So changing the default values will have no effect on existing store locations.', 'wp-store-locator' ); ?></em></p>

                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>        
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-permalink-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Permalink', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                               <label for="wpsl-permalinks-active"><?php esc_html_e( 'Enable permalink?', 'wp-store-locator' ); ?></label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['permalinks'], true ); ?> name="wpsl_permalinks[active]" id="wpsl-permalinks-active" class="wpsl-has-conditional-option">
                            </p>
                            <div class="wpsl-conditional-option" <?php if ( !$wpsl_settings['permalinks'] ) { echo 'style="display:none;"'; } ?>>
                                <p>
                                    <label for="wpsl-permalink-remove-front"><?php esc_html_e( 'Remove the front base from the permalink structure?', 'wp-store-locator' ); ?>
                                        <span class="wpsl-info">
                                            <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: line break */ echo wp_kses_post( sprintf( __( 'The front base is set on the %1$spermalink settings%2$s page in the "Custom structure" field. %3$s If a front base is set ( for example /blog/ ), then enabling this option will remove it from the store locator permalinks.', 'wp-store-locator' ), '<a href="https://codex.wordpress.org/Settings_Permalinks_Screen#Customize_Permalink_Structure" target="_blank">', '</a>', '<br><br>' ) ); ?></span>
                                        </span>
                                    </label>
                                    <input type="checkbox" value="" <?php checked( $wpsl_settings['permalink_remove_front'], true ); ?> name="wpsl_permalinks[remove_front]" id="wpsl-permalink-remove-front">
                                </p>
                                <p>
                                    <label for="wpsl-permalinks-slug"><?php esc_html_e( 'Store slug', 'wp-store-locator' ); ?>:</label> 
                                    <input type="text" value="<?php echo esc_attr( $wpsl_settings['permalink_slug'] ); ?>" name="wpsl_permalinks[slug]" class="textinput" id="wpsl-permalinks-slug">
                                </p>
                                <p>
                                    <label for="wpsl-category-slug"><?php esc_html_e( 'Category slug', 'wp-store-locator' ); ?>:</label> 
                                    <input type="text" value="<?php echo esc_attr( $wpsl_settings['category_slug'] ); ?>" name="wpsl_permalinks[category_slug]" class="textinput" id="wpsl-category-slug">
                                </p>
                                <em><?php /* translators: %1$s: opening strong tag, %2$s: closing strong tag */ echo wp_kses_post( sprintf( __( 'The permalink slugs %1$smust be unique%2$s on your site.', 'wp-store-locator' ), '<strong>', '</strong>' ) ); ?></em>
                            </div>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>        
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-label-settings" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Labels', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <?php
                            /*
                             * Show a msg to make sure that when a WPML compatible plugin 
                             * is active users use the 'String Translations' page to change the labels, 
                             * instead of the 'Label' section.
                             */
                            if ( $wpsl->i18n->wpml_exists() ) {
                                /* translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: opening link tag, %4$s: closing link tag */
                                echo '<p>' . wp_kses_post( sprintf( __( '%1$sWarning!%2$s %3$sWPML%4$s, or a plugin using the WPML API is active.', 'wp-store-locator' ), '<strong>', '</strong>', '<a href="https://wpml.org/">', '</a>' ) ) . '</p>';
                                echo '<p>' . esc_html( __( 'Please use the "String Translations" section in the used multilingual plugin to change the labels. Changing them here will have no effect as long as the multilingual plugin remains active.', 'wp-store-locator' ) ) . '</p>';
                            }
                            ?>
                            <p>
                                <label for="wpsl-search"><?php esc_html_e( 'Your location', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'search_label', __( 'Your location', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[search]" class="textinput" id="wpsl-search">
                            </p>
                            <p>
                                <label for="wpsl-search-radius"><?php esc_html_e( 'Search radius', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'radius_label', __( 'Search radius', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[radius]" class="textinput" id="wpsl-search-radius">
                            </p>
                            <p>
                                <label for="wpsl-no-results"><?php esc_html_e( 'No results found', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'no_results_label', __( 'No results found', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[no_results]" class="textinput" id="wpsl-no-results">
                            </p>
                            <p>
                                <label for="wpsl-search-btn"><?php esc_html_e( 'Search', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'search_btn_label', __( 'Search', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[search_btn]" class="textinput" id="wpsl-search-btn">
                            </p>
                            <p>
                                <label for="wpsl-preloader"><?php esc_html_e( 'Searching (preloader text)', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'preloader_label', __( 'Searching...', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[preloader]" class="textinput" id="wpsl-preloader">
                            </p>
                            <p>
                                <label for="wpsl-results"><?php esc_html_e( 'Results', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'results_label', __( 'Results', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[results]" class="textinput" id="wpsl-results">
                            </p>
                            <p>
                                <label for="wpsl-category"><?php esc_html_e( 'Category filter', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'category_label', __( 'Category', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[category]" class="textinput" id="wpsl-category">
                            </p>
                            <p>
                                <label for="wpsl-category-default"><?php esc_html_e( 'Category first item', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'category_default_label', __( 'Any', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[category_default]" class="textinput" id="wpsl-category-default">
                            </p>
                            <p>
                                <label for="wpsl-more-info"><?php esc_html_e( 'More info', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'more_label', __( 'More info', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[more]" class="textinput" id="wpsl-more-info">
                            </p>
                            <p>
                                <label for="wpsl-phone"><?php esc_html_e( 'Phone', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'phone_label', __( 'Phone', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[phone]" class="textinput" id="wpsl-phone">
                            </p>                        
                            <p>
                                <label for="wpsl-fax"><?php esc_html_e( 'Fax', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'fax_label', __( 'Fax', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[fax]" class="textinput" id="wpsl-fax">
                            </p>
                            <p>
                                <label for="wpsl-email"><?php esc_html_e( 'Email', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'email_label', __( 'Email', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[email]" class="textinput" id="wpsl-email">
                            </p>
                            <p>
                                <label for="wpsl-url"><?php esc_html_e( 'Url', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'url_label', __( 'Url', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[url]" class="textinput" id="wpsl-url">
                            </p>
                            <p>
                                <label for="wpsl-hours"><?php esc_html_e( 'Hours', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'hours_label', __( 'Hours', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[hours]" class="textinput" id="wpsl-hours">
                            </p>
                            <p>
                                <label for="wpsl-start"><?php esc_html_e( 'Start location', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'start_label', __( 'Start location', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[start]" class="textinput" id="wpsl-start">
                            </p>
                            <p>
                                <label for="wpsl-directions"><?php esc_html_e( 'Get directions', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'directions_label', __( 'Directions', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[directions]" class="textinput" id="wpsl-directions">
                            </p>
                            <p>
                                <label for="wpsl-no-directions"><?php esc_html_e( 'No directions found', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'no_directions_label', __( 'No route could be found between the origin and destination', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[no_directions]" class="textinput" id="wpsl-no-directions">
                            </p>
                            <p>
                                <label for="wpsl-back"><?php esc_html_e( 'Back', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'back_label', __( 'Back', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[back]" class="textinput" id="wpsl-back">
                            </p>
                            <p>
                                <label for="wpsl-street-view"><?php esc_html_e( 'Street view', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'street_view_label', __( 'Street view', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[street_view]" class="textinput" id="wpsl-street-view">
                            </p> 
                            <p>
                                <label for="wpsl-zoom-here"><?php esc_html_e( 'Zoom here', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'zoom_here_label', __( 'Zoom here', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[zoom_here]" class="textinput" id="wpsl-zoom-here">
                            </p>
                            <p>
                                <label for="wpsl-error"><?php esc_html_e( 'General error', 'wp-store-locator' ); ?>:</label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'error_label', __( 'Something went wrong, please try again!', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[error]" class="textinput" id="wpsl-error">
                            </p>
                            <p>
                                <label for="wpsl-limit"><?php esc_html_e( 'Query limit error', 'wp-store-locator' ); ?>:<span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening usage limit link tag, %2$s: closing link tag, %3$s: opening API key link tag, %4$s: closing link tag */ echo wp_kses_post( sprintf( __( 'You can raise the %1$susage limit%2$s by obtaining an API %3$skey%4$s, and fill in the "API key" field at the top of this page.', 'wp-store-locator' ), '<a href="https://developers.google.com/maps/documentation/javascript/usage#usage_limits" target="_blank">', '</a>' ,'<a href="https://developers.google.com/maps/documentation/javascript/tutorial#api_key" target="_blank">', '</a>' ) ); ?></span></span></label> 
                                <input type="text" value="<?php echo esc_attr( $wpsl->i18n->get_translation( 'limit_label', __( 'API usage limit reached', 'wp-store-locator' ) ) ); ?>" name="wpsl_label[limit]" class="textinput" id="wpsl-limit">
                            </p>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>        
                    </div>   
                </div>  
            </div>

            <div class="postbox-container">
                <div class="metabox-holder">
                    <div id="wpsl-tools" class="postbox">
                        <h3 class="hndle"><span><?php esc_html_e( 'Tools', 'wp-store-locator' ); ?></span></h3>
                        <div class="inside">
                            <p>
                               <label for="wpsl-debug"><?php esc_html_e( 'Enable store locator debug?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line break, %2$s: opening em tag, %3$s: closing em tag */ echo wp_kses_post( sprintf( __( 'This disables the WPSL transient cache. %1$sThe transient cache is only used if the %2$sLoad locations on page load%3$s option is enabled.', 'wp-store-locator' ), '<br><br>', '<em>', '</em>' ) ); ?></span>
                                    </span>
                                </label> 
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['debug'], true ); ?> name="wpsl_tools[debug]" id="wpsl-debug">
                            </p>
                            <p>
                               <label for="wpsl-deregister-gmaps"><?php esc_html_e( 'Enable compatibility mode?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info">
                                        <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: first line break, %4$s: opening em tag, %5$s: closing em tag, %6$s: second line break */ echo wp_kses_post( sprintf( __( 'If the %1$sbrowser console%2$s shows the error below, then enabling this option should fix it. %3$s %4$sYou have included the Google Maps API multiple times on this page. This may cause unexpected errors.%5$s %6$s This error can in some situations break the store locator map.', 'wp-store-locator' ), '<a href="https://codex.wordpress.org/Using_Your_Browser_to_Diagnose_JavaScript_Errors#Step_3:_Diagnosis">', '</a>', '<br><br>', '<em>', '</em>', '<br><br>' ) ); ?></span>
                                    </span>
                                </label>
                               <input type="checkbox" value="" <?php checked( $wpsl_settings['deregister_gmaps'], true ); ?> name="wpsl_tools[deregister_gmaps]" id="wpsl-deregister-gmaps">
                            </p>
                            <p>
                               <label for="wpsl-transient"><?php esc_html_e( 'WPSL transients', 'wp-store-locator' ); ?></label> 
                               <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( "edit.php?post_type=wpsl_stores&page=wpsl_settings&action=clear_wpsl_transients" ), 'clear_transients' ) ); ?>"><?php esc_html_e( 'Clear store locator transient cache', 'wp-store-locator' ); ?></a>
                            </p>
                            <?php
                                /**
                                 * Make sure the blocked content type for the store locator exists
                                 * in the Borlabs Cookie plugins. If not, then it's created.
                                 */
                                if ( $borlabs_exists ) {
                                    $borlabs = New WPSL_Borlabs_Cookie();
                                    $borlabs->maybe_enable_bct();
                                }
                            ?>
                            <p>
                                <label for="wpsl-delay-loading"><?php esc_html_e( 'GDPR - Only load Google Maps after the user agrees to it?', 'wp-store-locator' ); ?>
                                    <span class="wpsl-info <?php if ( !$borlabs_exists ) { echo 'wpsl-warning'; } ?>">
                                        <?php if ( !$borlabs_exists ) { ?>
                                            <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'This option requires the %1$sBorlabs Cookie%2$s plugin.', 'wp-store-locator' ), '<a target="_new" href="https://borlabs.io/borlabs-cookie/">', '</a>' ) ); ?></span>
                                        <?php } else { ?>
                                            <span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Make sure to wrap the Borlabs Cookie %1$sshortcode%2$s around the WPSL shortcode.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/the-general-data-protection-regulation/#borlabs">', '</a>' ) ); ?></span>
                                        <?php }?>
                                    </span>
                                </label>
                                <input <?php if ( !$borlabs_exists ) { echo 'disabled="disabled"'; } ?> type="checkbox" value="" <?php checked( $wpsl_settings['delay_loading'], true ); ?> name="wpsl_tools[delay_loading]" id="wpsl-delay-loading">
                            </p>
                            <p>
                                <label for="wpsl-show-geocode-response"><?php esc_html_e( 'Show the Geocode API response for a location search', 'wp-store-locator' ); ?></label>
                                <a id="wpsl-show-geocode-response" class="button" href="#"><?php esc_html_e( 'Input location details', 'wp-store-locator' ); ?></a>
                            </p>
                            <p class="submit">
                                <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                            </p>
                        </div>
                    </div>
                </div>                    
            </div>

            <?php settings_fields( 'wpsl_settings' ); ?>
        </form>
    </div>
    
    <?php
    } else {
        do_action( 'wpsl_settings_section', $current_tab );
    }
    ?>
</div>
<div id="wpsl-geocode-test" class="wpsl-hide" title="<?php esc_html_e( 'Geocode API Response', 'wp-store-locator' ); ?>">
    <div class="wpsl-geocode-warning" style="display: none;">
        <p><strong><?php esc_html_e( 'Note', 'wp-store-locator' ); ?>: </strong></p>
    </div>

    <input id="wpsl-geocode-input" type="text" placeholder="<?php esc_html_e( 'Location details', 'wp-store-locator' ); ?>" >
    <input id="wpsl-geocode-submit" type="submit" class="button-primary" name="<?php esc_html_e( 'Search', 'wp-store-locator' ); ?>" />
    <p class="wpsl-geocode-api-notice" style="display: none;">
        <strong><?php esc_html_e( 'API Status', 'wp-store-locator' ); ?>: </strong>
        <span></span>
    </p>
    <div id="wpsl-geocode-tabs" style="width: auto;">
        <ul>
            <li><a href="#wpsl-geocode-preview"><?php esc_html_e( 'Map Preview', 'wp-store-locator' ); ?></a></li>
            <li><a href="#wpsl-geocode-response"><?php esc_html_e( 'API Response', 'wp-store-locator' ); ?></a></li>
        </ul>
        <div id="wpsl-geocode-preview" style="width:auto;height:300px;"></div>
        <div id="wpsl-geocode-response">
            <textarea readonly="readonly" cols="50" rows="25"></textarea>
        </div>
    </div>
</div>