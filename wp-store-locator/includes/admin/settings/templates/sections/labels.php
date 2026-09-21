<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$i18n             = wpsl_get_service( 'i18n' );
$label_visibility = $wpsl_settings->get( 'labels', 'visibility' );
?>

<section id="wpsl-labels" class="postbox">
    <h3><span><?php esc_html_e( 'Labels', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <?php
        /**
         * When a WPML compatible plugin is active the labels below are the
         * source text it translates, so point users to the 'String Translations'
         * page for the other languages.
         */
        $wpml_compatible = defined( 'WPML_ST_VERSION' ) || ( defined( 'POLYLANG_VERSION' ) && defined( 'POLYLANG_DIR' ) );

        if ( $wpml_compatible ) {
            ?>
            <div class="wpsl-red-callout">
                <?php
                /* translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: opening link tag to WPML, %4$s: closing link tag */
                echo '<p>' . wp_kses_post( sprintf( __( '%1$sNote:%2$s %3$sWPML%4$s, or a plugin using the WPML API is active.', 'wp-store-locator' ), '<strong>', '</strong>', '<a href="https://wpml.org/" target="_blank">', '</a>' ) ) . '</p>';
                echo '<p>' . esc_html__( 'The labels below are the source text. Use the "(String) Translations" section in your multilingual plugin to translate them into your other languages.', 'wp-store-locator' ) . '</p>';
                ?>
            </div>
            <?php
        }
        ?>

        <div class="wpsl-label-panel-switch">
            <label for="wpsl-label-panel-select" class="screen-reader-text"><?php esc_html_e( 'Show labels', 'wp-store-locator' ); ?></label>
            <select id="wpsl-label-panel-select" autocomplete="off">
                <option value="search" selected="selected"><?php esc_html_e( 'Search & filter labels', 'wp-store-locator' ); ?></option>
                <option value="other"><?php esc_html_e( 'Other labels', 'wp-store-locator' ); ?></option>
            </select>
        </div>

        <div class="wpsl-labels-panel wpsl-labels-panel-search wpsl-active">

        <div class="wpsl-label-head">
            <span><?php esc_html_e( 'Description', 'wp-store-locator' ); ?></span>
            <span><?php esc_html_e( 'Visible', 'wp-store-locator' ); ?></span>
            <span><?php esc_html_e( 'Custom text', 'wp-store-locator' ); ?></span>
        </div>

        <p class="wpsl-label-row">
            <label for="wpsl-search-location-label"><?php esc_html_e( 'Your location', 'wp-store-locator' ); ?></label>
            <span class="wpsl-label-visibility">
                <input type="checkbox" value="1" <?php checked( ! empty( $label_visibility['search'] ), true ); ?> name="wpsl_labels[visibility][search]" id="wpsl-visibility-search" aria-label="<?php esc_attr_e( 'Show the "Your location" label', 'wp-store-locator' ); ?>">
            </span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'search_label', __( 'Your location', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[search]" id="wpsl-search-location-label">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-search-name-label"><?php esc_html_e( 'Store name', 'wp-store-locator' ); ?></label>
            <span class="wpsl-label-visibility">
                <input type="checkbox" value="1" <?php checked( ! empty( $label_visibility['search_name'] ), true ); ?> name="wpsl_labels[visibility][search_name]" id="wpsl-visibility-search-name" aria-label="<?php esc_attr_e( 'Show the "Store name" label', 'wp-store-locator' ); ?>">
            </span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'search_name_label', __( 'Store name', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[search_name]" id="wpsl-search-name-label">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-search-radius-label"><?php esc_html_e( 'Search radius', 'wp-store-locator' ); ?></label>
            <span class="wpsl-label-visibility">
                <input type="checkbox" value="1" <?php checked( ! empty( $label_visibility['radius'] ), true ); ?> name="wpsl_labels[visibility][radius]" id="wpsl-visibility-radius" aria-label="<?php esc_attr_e( 'Show the "Search radius" label', 'wp-store-locator' ); ?>">
            </span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'radius_label', __( 'Search radius', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[radius]" id="wpsl-search-radius-label">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-results"><?php esc_html_e( 'Results', 'wp-store-locator' ); ?></label>
            <span class="wpsl-label-visibility">
                <input type="checkbox" value="1" <?php checked( ! empty( $label_visibility['results'] ), true ); ?> name="wpsl_labels[visibility][results]" id="wpsl-visibility-results" aria-label="<?php esc_attr_e( 'Show the "Results" label', 'wp-store-locator' ); ?>">
            </span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'results_label', __( 'Results', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[results]" id="wpsl-results">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-category-label"><?php esc_html_e( 'Category filter', 'wp-store-locator' ); ?></label>
            <span class="wpsl-label-visibility">
                <input type="checkbox" value="1" <?php checked( ! empty( $label_visibility['category'] ), true ); ?> name="wpsl_labels[visibility][category]" id="wpsl-visibility-category" aria-label="<?php esc_attr_e( 'Show the "Category filter" label', 'wp-store-locator' ); ?>">
            </span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'category_label', __( 'Category', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[category]" id="wpsl-category-label">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-category-default-label"><?php esc_html_e( 'Category first item', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The first item in the category dropdown always needs text, so it cannot be hidden.', 'wp-store-locator' ); ?></span></span></label>
            <span class="wpsl-label-visibility wpsl-label-visibility-na" aria-hidden="true">&mdash;</span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'category_default_label', __( 'Any', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[category_default]" id="wpsl-category-default-label">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-search-btn-txt"><?php esc_html_e( 'Search button', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'A button always needs text, so it cannot be hidden. The Vertical template shows a search icon instead and ignores this text.', 'wp-store-locator' ); ?></span></span></label>
            <span class="wpsl-label-visibility wpsl-label-visibility-na" aria-hidden="true">&mdash;</span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'search_btn_label', __( 'Search', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[search_btn]" id="wpsl-search-btn-txt">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-show-filters"><?php esc_html_e( 'Show filters', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Only used by the Vertical template, on the button that opens the filter panel. A button always needs text, so it cannot be hidden.', 'wp-store-locator' ); ?></span></span></label>
            <span class="wpsl-label-visibility wpsl-label-visibility-na" aria-hidden="true">&mdash;</span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'show_filters_label', __( 'Show filters', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[show_filters]" id="wpsl-show-filters">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-filters"><?php esc_html_e( 'Filters', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Only used by the Vertical template, on the filter panel heading and on the button that opens it when the filters are nested.', 'wp-store-locator' ); ?></span></span></label>
            <span class="wpsl-label-visibility wpsl-label-visibility-na" aria-hidden="true">&mdash;</span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'filters_label', __( 'Filters', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[filters]" id="wpsl-filters">
        </p>
        <p class="wpsl-label-row">
            <label for="wpsl-apply-label"><?php esc_html_e( 'Apply button', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Only used by the Vertical template, on the button that applies the selected filters. A button always needs text, so it cannot be hidden.', 'wp-store-locator' ); ?></span></span></label>
            <span class="wpsl-label-visibility wpsl-label-visibility-na" aria-hidden="true">&mdash;</span>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'apply_label', __( 'Apply', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[apply]" id="wpsl-apply-label">
        </p>

        </div><!-- .wpsl-labels-panel-search -->

        <div class="wpsl-labels-panel wpsl-labels-panel-other">

        <p>
            <label for="wpsl-no-results"><?php esc_html_e( 'No results found', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'no_results_label', __( 'No results found.', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[no_results]" id="wpsl-no-results">
        </p>
        <p>
            <label for="wpsl-preloader"><?php esc_html_e( 'Searching (preloader text)', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'preloader_label', __( 'Searching...', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[preloader]" id="wpsl-preloader">
        </p>
        <p>
            <label for="wpsl-more-info-label"><?php esc_html_e( 'More info', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'more_label', __( 'More info', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[more]" id="wpsl-more-info-label">
        </p>
        <p>
            <label for="wpsl-phone"><?php esc_html_e( 'Phone', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'phone_label', __( 'Phone', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[phone]" id="wpsl-phone">
        </p>
        <p>
            <label for="wpsl-fax"><?php esc_html_e( 'Fax', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'fax_label', __( 'Fax', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[fax]" id="wpsl-fax">
        </p>
        <p>
            <label for="wpsl-email"><?php esc_html_e( 'Email', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'email_label', __( 'Email', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[email]" id="wpsl-email">
        </p>
        <p>
            <label for="wpsl-url"><?php esc_html_e( 'URL', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'url_label', __( 'Url', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[url]" id="wpsl-url">
        </p>
        <p>
            <label for="wpsl-more-details"><?php esc_html_e( 'More details', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'more_details_label', __( 'More details', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[more_details]" id="wpsl-more-details">
        </p>
        <p>
            <label for="wpsl-hours"><?php esc_html_e( 'Hours', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'hours_label', __( 'Hours', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[hours]" id="wpsl-hours">
        </p>
        <p>
            <label for="wpsl-start"><?php esc_html_e( 'Start location', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'start_label', __( 'Start location', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[start]" id="wpsl-start">
        </p>
        <p>
            <label for="wpsl-directions"><?php esc_html_e( 'Get directions', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'directions_label', __( 'Directions', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[directions]" id="wpsl-directions">
        </p>
        <p class="wpsl-api-osm" <?php if ( $wpsl_settings->get( 'api', 'active_map_service' ) != 'osm' ) { echo 'style="display:none"'; } ?>>
            <label for="wpsl-loading-directions"><?php esc_html_e( 'Loading directions', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'loading_directions_label', __( 'Loading directions', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[loading_directions]" id="wpsl-loading-directions">
        </p>
        <p>
            <label for="wpsl-no-directions"><?php esc_html_e( 'No directions found', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'no_directions_label', __( 'No route found between the origin and destination.', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[no_directions]" id="wpsl-no-directions">
        </p>
        <p>
            <label for="wpsl-back"><?php esc_html_e( 'Back', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'back_label', __( 'Back', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[back]" id="wpsl-back">
        </p>
        <p>
            <label for="wpsl-street-view"><?php esc_html_e( 'Street view', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'street_view_label', __( 'Street view', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[street_view]" id="wpsl-street-view">
        </p>
        <p>
            <label for="wpsl-zoom-here"><?php esc_html_e( 'Zoom here', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'zoom_here_label', __( 'Zoom here', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[zoom_here]" id="wpsl-zoom-here">
        </p>
        <p>
            <label for="wpsl-error"><?php esc_html_e( 'General error', 'wp-store-locator' ); ?></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'error_label', __( 'Something went wrong, please try again!', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[error]" id="wpsl-error">
        </p>
        <p>
            <label for="wpsl-number-results-label"><?php esc_html_e( 'Number of results', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The {number} will be replaced in the template with the number of returned results, so it has to be included in the text.', 'wp-store-locator' ); ?></span></span></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'number_results_label', __( '{number} stores near you', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[number_results]" id="wpsl-number-results-label">
        </p>
        <p>
            <label for="wpsl-number-results-single-label"><?php esc_html_e( 'Number of results (single)', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Used instead of the text above when exactly one result is found. The {number} will be replaced with the result count.', 'wp-store-locator' ); ?></span></span></label>
            <input type="text" value="<?php echo esc_attr( $i18n->get_translation( 'number_results_single_label', __( '{number} store near you', 'wp-store-locator' ) ) ); ?>" name="wpsl_labels[number_results_single]" id="wpsl-number-results-single-label">
        </p>
        </div><!-- .wpsl-labels-panel-other -->

        <?php do_action( 'wpsl_label_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>