<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * Name search matches on the post / location name and never uses coordinates,
 * so all the coordinate-dependent options below are hidden for it.
 */
$is_name_search = ( $section_settings['search_method'] === 'name' );
?>

<section id="wpsl-search" class="postbox">
    <h3><span><?php esc_html_e( 'Search', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p>
            <label for="wpsl-search-method"><?php esc_html_e( 'Search method', 'wp-store-locator' ); ?></label>
            <select id="wpsl-search-method" name="wpsl_search[search_method]" autocomplete="off">
                <option value="geocode" <?php selected( $section_settings['search_method'], 'geocode' ); ?>><?php esc_html_e( 'Geocode API (default)', 'wp-store-locator' ); ?></option>
                <option value="name" <?php selected( $section_settings['search_method'], 'name' ); ?>><?php esc_html_e( 'Name search (post / location name)', 'wp-store-locator' ); ?></option>
            </select>
        </p>

        <p>
            <label for="wpsl-input-placeholder"><?php esc_html_e( 'Placeholder for the search field', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $section_settings['input_placeholder'] ); ?>" name="wpsl_search[input_placeholder]" id="wpsl-input-placeholder">
        </p>

        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-auto-locate"><?php esc_html_e( 'Attempt to auto-locate the user', 'wp-store-locator' ); ?>
                <span class="wpsl-info <?php if ( ! wpsl_get_service( 'system_utils' )->ssl_active() ) { echo 'wpsl-warning'; } ?>">
                    <?php /* translators: %1$s: opening link tag to documentation, %2$s: closing link tag */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'A HTTPS connection is %1$srequired%2$s before the Geolocation API can access the user\'s location.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/html-5-geolocation-not-working/">', '</a>' ) ); ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $section_settings['auto_locate'], true ); ?> name="wpsl_search[auto_locate]" id="wpsl-auto-locate" class="wpsl-has-conditional-option">
        </p>
        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['auto_locate'] || $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <p class="wpsl-advanced">
                <label for="wpsl-auto-locate-format"><?php esc_html_e( 'Auto-locate address format', 'wp-store-locator' ); ?></label>
                <?php echo $ui->create_dropdown( 'auto_locate_format' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </p>
            <p class="wpsl-advanced">
                <label for="wpsl-auto-locate-trigger"><?php esc_html_e( 'Auto-locate trigger', 'wp-store-locator' ); ?></label>
                <?php echo $ui->create_dropdown( 'auto_locate_trigger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </p>
        </div>

        <p class="wpsl-api-gmaps wpsl-api-mapbox wpsl-api-stadia" <?php if ( $is_name_search || $settings_manager->get( 'api', 'active_map_service' ) === 'osm' ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-search-autocomplete"><?php esc_html_e( 'Enable autocomplete?', 'wp-store-locator' ); ?>
                <span class="wpsl-info" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) !== 'mapbox' ) { echo 'style="display:none;"'; } ?>>
                    <?php /* translators: %1$s: opening link tag to API settings, %2$s: closing link tag */ ?>
                    <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'The returned results are restricted to the selected %1$smap region(s)%2$s.', 'wp-store-locator' ), '<a href="#" class="wpsl-trigger-nav" data-item="api">', '</a>' ) ); ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $section_settings['autocomplete'], true ); ?> name="wpsl_search[autocomplete]" id="wpsl-search-autocomplete" class="wpsl-has-conditional-option">
        </p>
        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['autocomplete'] || $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <div class="wpsl-api-gmaps" <?php if ( $settings_manager->get( 'api', 'active_map_service' ) !== 'gmaps' ) { echo 'style="display:none;"'; } ?>>
                <p class="wpsl-api-gmaps">
                    <label for="wpsl-gmaps-autocomplete-api-versions"><?php esc_html_e( 'Autocomplete source', 'wp-store-locator' ); ?>
                        <span class="wpsl-info">
                            <?php /* translators: %1$s: line breaks, %2$s: line breaks, %3$s: opening link tag to documentation, %4$s: closing link tag */ ?>
                            <span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'API keys created after March 1, 2025 only work with the Autocomplete Data API. Keys created before that date work with both options. %1$s Not sure how old your keys are? Select the Autocomplete Data API, and switch to the Places Autocomplete Service if no address suggestions show up. %2$s %3$sRead more%4$s', 'wp-store-locator' ), '<br><br>', '<br><br>', '<a href="https://wpstorelocator.co/migrate-to-the-new-places-api/" target="_blank">', '</a>' ) ); ?></span>
                        </span>
                    </label>
                    <?php echo wpsl_get_service( 'admin_ui' )->create_dropdown( 'gmaps_autocomplete_api_versions' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                </p>
            </div>
            <div <?php
                $active_service = $settings_manager->get( 'api', 'active_map_service' );
                $is_gmaps_legacy = ( $active_service === 'gmaps' && isset( $section_settings['api_versions']['gmaps']['autocomplete'] ) && $section_settings['api_versions']['gmaps']['autocomplete'] === 'legacy' );
                $show_autosubmit = ( $active_service === 'gmaps' && ! $is_gmaps_legacy ) || $active_service === 'mapbox' || $active_service === 'stadia';
                if ( ! $show_autosubmit ) { echo 'style="display:none;"'; } 
            ?>>
                <p class="wpsl-autosubmit-autocomplete">
                    <label for="wpsl-search-autocomplete-autosubmit"><?php esc_html_e( 'Automatically start a search when an autocomplete value is selected?', 'wp-store-locator' ); ?></label>
                    <input type="checkbox" value="" <?php checked( $section_settings['autosubmit_autocomplete'], true ); ?> name="wpsl_search[autosubmit_autocomplete]" id="wpsl-search-autocomplete-autosubmit">
                </p>
            </div>
        </div>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-force-postalcode"><?php esc_html_e( 'Force zip code only search', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide"><?php
                        $force_postalcode_info = esc_html__( 'This option works best when the API results are restricted to one or more regions.', 'wp-store-locator' );

                        if ( $wpsl_settings->get( 'api', 'active_map_service' ) === 'gmaps' ) {
                            $force_postalcode_info .= '<br><br>' . esc_html__( 'The Google Geocoding API can only restrict a request to a single country, so when results are restricted to multiple countries the plugin tries the zip code against each country in turn until a match is found. This will lead to higher API usage.', 'wp-store-locator' );
                        }

                        echo wp_kses( $force_postalcode_info, [ 'br' => [] ] );
                    ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $section_settings['force_postalcode'], true ); ?> name="wpsl_search[force_postalcode]" id="wpsl-force-postalcode">
        </p>
        <p class="wpsl-api-gmaps">
            <label for="wpsl-search-input-only"><?php esc_html_e( 'Hide the map until the first search is made?', 'wp-store-locator' ); ?>
                <?php
                    /*
                     * Auto-locate is hidden and ignored under a name search, so
                     * only the autoload option can still reveal the map there --
                     * pointing at a checkbox that isn't on screen only confuses.
                     */
                    $conflicts_with_input_only = $wpsl_settings->get( 'map', 'autoload' ) || ( ! $is_name_search && $wpsl_settings->get( 'search', 'auto_locate' ) );
                ?>
                <span class="wpsl-info <?php if ( ! $conflicts_with_input_only ) { echo 'wpsl-hide'; } ?> wpsl-required-setting wpsl-search-input-only">
                    <span class="wpsl-info-text wpsl-hide"><?php
                        if ( $is_name_search ) {
                            esc_html_e( 'Please make sure to disable the "Load locations on page load" (Map) option, otherwise the map will still show on page load', 'wp-store-locator' );
                        } else {
                            esc_html_e( 'Please make sure to disable the "Load locations on page load" (Map) and the "Attempt to auto-locate the user" (Search) options, otherwise the map will still show on page load', 'wp-store-locator' );
                        }
                    ?></span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $section_settings['input_only'], true ); ?> name="wpsl_search[input_only]" id="wpsl-search-input-only">
        </p>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-distance-unit"><?php esc_html_e( 'Distance unit', 'wp-store-locator' ); ?></label>
            <span class="wpsl-radioboxes">
                <input type="radio" value="km" <?php checked( 'km', $section_settings['distance_unit'] ); ?> name="wpsl_search[distance_unit]" id="wpsl-distance-km">
                <label for="wpsl-distance-km"><?php esc_html_e( 'km', 'wp-store-locator' ); ?></label>
                <input type="radio" value="mi" <?php checked( 'mi', $section_settings['distance_unit'] ); ?> name="wpsl_search[distance_unit]" id="wpsl-distance-mi">
                <label for="wpsl-distance-mi"><?php esc_html_e( 'mi', 'wp-store-locator' ); ?></label>
            </span>
        </p>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-radius_filter"><?php esc_html_e( 'Show search radius dropdown?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['radius_dropdown'], true ); ?> name="wpsl_search[radius_dropdown]" id="wpsl-radius_filter">
        </p>
        <p <?php if ( $settings['appearance']['template_id'] === 'vertical' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-max_results_filter"><?php esc_html_e( 'Show max results dropdown?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['results_dropdown'], true ); ?> name="wpsl_search[results_dropdown]" id="wpsl-max_results_filter">
        </p>
        <p>
            <label for="wpsl-max-results"><?php esc_html_e( 'Max search results', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The default value is set between the [ ].', 'wp-store-locator' ); ?></span></span></label>
            <input type="text" value="<?php echo esc_attr( $section_settings['max_results'] ); ?>" name="wpsl_search[max_results]" id="wpsl-max-results">
        </p>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-search-radius"><?php esc_html_e( 'Search radius options', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The default value is set between the [ ].', 'wp-store-locator' ); ?></span></span></label>
            <input type="text" value="<?php echo esc_attr( $section_settings['search_radius'] ); ?>" name="wpsl_search[search_radius]" id="wpsl-search-radius">
        </p>
        <p>
            <label for="wpsl-sort-by"><?php esc_html_e( 'Sort by', 'wp-store-locator' ); ?></label>
            <?php echo wpsl_get_service( 'admin_ui' )->create_dropdown( 'orderby' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label for="wpsl-sort-order"><?php esc_html_e( 'Sort order', 'wp-store-locator' ); ?></label>
            <?php echo wpsl_get_service( 'admin_ui' )->create_dropdown( 'order' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
        <p>
            <label for="wpsl-category_filter"><?php esc_html_e( 'Enable category filter?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['category_filter'], true ); ?> name="wpsl_search[category_filter]" id="wpsl-category_filter" class="wpsl-has-conditional-option">
        </p>
        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['category_filter'] ) { echo 'style="display:none;"'; } ?>>
            <?php
            $categories = wpsl_get_unique_categories();
            $ui = wpsl_get_service( 'admin_ui' );

            /**
             * Panel templates ( e.g. vertical ) always render the category filter
             * as a hierarchical checkbox list, so the "Filter type" choice doesn't
             * apply and the "all categories" option is always relevant.
             */
            $active_template = wpsl_get_active_template();
            $has_panel       = isset( $active_template['has_panel'] ) && $active_template['has_panel'];
            ?>
            <p <?php if ( ! $section_settings['category_filter'] || count( $categories ) < 2 ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-category-default"><?php esc_html_e( 'Default category filter selection', 'wp-store-locator' ); ?></label>
                <?php echo $ui->create_dropdown( 'category_default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </p>
            <p <?php if ( $has_panel ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-cat-filter-types"><?php esc_html_e( 'Filter type', 'wp-store-locator' ); ?></label>
                <?php echo $ui->create_dropdown( 'filter_types' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </p>
            <p class="wpsl-all-categories" <?php if ( ! $has_panel && $section_settings['category_filter_type'] === 'dropdown' ) { echo 'style="display:none;"'; } ?>>
                <label for="wpsl-all-categories"><?php esc_html_e( 'Only show locations that are in all the selected categories?', 'wp-store-locator' ); ?></label>
                <input type="checkbox" value="" <?php checked( $section_settings['all_categories_required'], true ); ?> name="wpsl_search[all_categories_required]" id="wpsl-all-categories">
            </p>
            <p>
                <label for="wpsl-category_only_filter"><?php esc_html_e( 'Only show the category filter?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Enabling this option will automatically hide the location input field and the max results and radius filters.', 'wp-store-locator' ); ?></span></span></label>
                <input type="checkbox" value="" <?php checked( $section_settings['category_filter_only'], true ); ?> name="wpsl_search[category_filter_only]" id="wpsl-category_only_filter">
            </p>
        </div>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-enforce-borders"><?php esc_html_e( 'Exclude locations from nearby countries?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( esc_html__( 'Returns only locations in the same country as the search. %s E.g. a search for a Canadian border town returns only Canadian locations, not ones a few km across the US border.', 'wp-store-locator' ), '<br><br>' ) ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['enforce_borders'], true ); ?> name="wpsl_search[enforce_borders]" id="wpsl-enforce-borders">
        </p>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-full-search"><?php esc_html_e( 'Return all locations when someone searches for a whole country or state?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %s: line breaks */ echo wp_kses_post( sprintf( __( 'When a visitor searches for an entire country or state, this option returns every location in that area and ignores the max results and search radius limits. %s For example, if you have 200 locations in Germany and someone searches for "Germany", all 200 are returned.', 'wp-store-locator' ), "<br><br>" ) ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['full_search'], true ); ?> name="wpsl_search[full_search]" id="wpsl-full-search">
        </p>
        <p>
            <label for="wpsl-number-results"><?php esc_html_e( 'Show the number of returned results?', 'wp-store-locator' ); ?></label>
            <input type="checkbox" value="" <?php checked( $section_settings['number_results'], true ); ?> name="wpsl_search[number_results]" id="wpsl-number-results">
        </p>
        <p <?php if ( $is_name_search ) { echo 'style="display:none;"'; } ?>>
            <label for="wpsl-find-nearest-location"><?php esc_html_e( 'If no results are found, then show the nearest location ignoring the search radius?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'If a search returns no locations within the chosen radius, the single closest location is shown instead, even if it falls outside that radius.', 'wp-store-locator' ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['find_nearest_location'], true ); ?> name="wpsl_search[find_nearest_location]" id="wpsl-find-nearest-location">
        </p>
        <?php do_action( 'wpsl_search_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>