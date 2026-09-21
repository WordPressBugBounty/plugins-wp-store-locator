<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are passed in scope, not global
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$output         = $assets_manager->get_custom_css();
$autoload_class = ( ! $wpsl_settings['map']['autoload'] ) ? 'class="wpsl-not-loaded"' : '';

$label_type    = ( isset( $wpsl_settings['search']['search_method'] ) && $wpsl_settings['search']['search_method'] === 'name' ) ? 'search_name_label' : 'search_label';
$label_default = ( $label_type === 'search_name_label' ) ? __( 'Store name', 'wp-store-locator' ) : __( 'Your location', 'wp-store-locator' );

$output .= '<div id="wpsl-wrap" ' . $assets_manager->get_outer_class() . '>' . "\r\n";
$output .= $frontend->maybe_show_gdpr_checkpoint();
$output .= "\t" . '<div class="wpsl-search wpsl-clearfix ' . $assets_manager->get_css_classes() . '">' . "\r\n";
$output .= "\t\t" . '<div id="wpsl-search-wrap">' . "\r\n";
$output .= "\t\t\t" . '<form autocomplete="off">' . "\r\n";
$output .= "\t\t\t" . '<div class="wpsl-input">' . "\r\n";

$search_visibility_key = ( $label_type === 'search_name_label' ) ? 'search_name' : 'search';
if ( $i18n->is_label_visible( $search_visibility_key ) ) {
    $output .= "\t\t\t\t" . '<div><label for="wpsl-search-input">' . esc_html( $i18n->get_translation( $label_type, $label_default ) ) . '</label></div>' . "\r\n";
}

$output .= $assets_manager->maybe_output_mapbox_autocomplete();
$output .= "\t\t\t\t" . '<input id="wpsl-search-input" type="text" value="' . esc_attr( apply_filters( 'wpsl_search_input', '' ) ) . '" name="wpsl-search-input" placeholder="' . esc_attr( $wpsl_settings['search']['input_placeholder'] ) . '" aria-required="true" />' . "\r\n";
$output .= "\t\t\t" . '</div>' . "\r\n";

if ( ! $template_filters->is_category_filter_only() ) {
    if ( $template_filters->is_radius_enabled() || $template_filters->is_results_enabled() ) {
        $output .= "\t\t\t" . '<div class="wpsl-select-wrap">' . "\r\n";

        if ( $template_filters->is_radius_enabled() ) {
            $output .= "\t\t\t\t" . '<div id="wpsl-radius">' . "\r\n";
            if ( $i18n->is_label_visible( 'radius' ) ) {
                $output .= "\t\t\t\t\t" . '<label for="wpsl-radius-dropdown">' . esc_html( $i18n->get_translation( 'radius_label', __( 'Search radius', 'wp-store-locator' ) ) ) . '</label>' . "\r\n";
            }
            $output .= "\t\t\t\t\t" . '<select id="wpsl-radius-dropdown" class="wpsl-dropdown" name="wpsl-radius">' . "\r\n";
            $output .= "\t\t\t\t\t\t" . $search_filters->get_dropdown_list( 'search_radius' ) . "\r\n";
            $output .= "\t\t\t\t\t" . '</select>' . "\r\n";
            $output .= "\t\t\t\t" . '</div>' . "\r\n";
        }

        if ( $template_filters->is_results_enabled() ) {
            $output .= "\t\t\t\t" . '<div id="wpsl-results">' . "\r\n";
            if ( $i18n->is_label_visible( 'results' ) ) {
                $output .= "\t\t\t\t\t" . '<label for="wpsl-results-dropdown">' . esc_html( $i18n->get_translation( 'results_label', __( 'Results', 'wp-store-locator' ) ) ) . '</label>' . "\r\n";
            }
            $output .= "\t\t\t\t\t" . '<select id="wpsl-results-dropdown" class="wpsl-dropdown" name="wpsl-results">' . "\r\n";
            $output .= "\t\t\t\t\t\t" . $search_filters->get_dropdown_list( 'max_results' ) . "\r\n";
            $output .= "\t\t\t\t\t" . '</select>' . "\r\n";
            $output .= "\t\t\t\t" . '</div>' . "\r\n";
        }

        $output .= "\t\t\t" . '</div>' . "\r\n";
    }
}

if ( $template_filters->is_category_enabled() ) {
    $output .= $template_filters->category_list();
}

$output .= "\t\t\t" . '<div class="wpsl-search-btn-wrap">' . "\r\n";
$output .= "\t\t\t" . '<input id="wpsl-search-btn" type="submit" value="' . esc_attr( $i18n->get_translation( 'search_btn_label', esc_html__( 'Search', 'wp-store-locator' ) ) ) . '" tabindex="0">';
$output .= "\t\t\t" . '</div>' . "\r\n";
$output .= "\t\t" . '</form>' . "\r\n";
$output .= "\t\t" . '</div>' . "\r\n";
$output .= "\t" . '</div>' . "\r\n";
    
$output .= '<a href="#wpsl-result-list" class="wpsl-skip-to-results">' . esc_html( $i18n->get_translation( 'skip_to_results_label', __( 'Skip map, jump to search results', 'wp-store-locator' ) ) ) . '</a>';
$output .= "\t" . '<div id="wpsl-map" class="wpsl-canvas-' . esc_attr( $wpsl_settings['api']['active_map_service'] ) . '">';
$output .= "\t" .  '</div>' . "\r\n";

$output .= "\t" . '<div id="wpsl-result-list" tabindex="-1">' . "\r\n";
$output .= "\t\t" . '<div id="wpsl-stores" '. $autoload_class .'>' . "\r\n";
$output .= "\t\t\t" . '<ul></ul>' . "\r\n";
$output .= "\t\t" . '</div>' . "\r\n";
$output .= "\t\t" . '<div id="wpsl-direction-details">' . "\r\n";
$output .= "\t\t\t" . '<ul></ul>' . "\r\n";
$output .= "\t\t" . '</div>' . "\r\n";
$output .= "\t" . '</div>' . "\r\n";

if ( $wpsl_settings['map']['show_credits'] ) {
    /* translators: 1: opening link tag, 2: closing link tag */
    $output .= "\t" . '<div class="wpsl-provided-by">'. sprintf( esc_html__( "Search provided by %1\$sWP Store Locator%2\$s", 'wp-store-locator' ), "<a target='_blank' href='https://wpstorelocator.co'>", "</a>" ) .'</div>' . "\r\n";
}

$output .= '</div>' . "\r\n";

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The dynamic values are escaped while the template output is built above.
echo $output;