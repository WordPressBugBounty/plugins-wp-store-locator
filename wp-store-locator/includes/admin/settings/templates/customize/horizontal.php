<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from the container
$wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'search' );
$filters = wpsl_get_service( 'template_filters' );
$appearance = wpsl_get_service( 'appearance' );
$i18n = wpsl_get_service( 'i18n' );

$label_type    = ( isset( $wpsl_settings['search_method'] ) && $wpsl_settings['search_method'] === 'name' ) ? 'search_name_label' : 'search_label';
$label_default = ( $label_type === 'search_name_label' ) ? __( 'Store name', 'wp-store-locator' ) : __( 'Your location', 'wp-store-locator' );

$output = '<div id="wpsl-wrap" class="wpsl-horizontal-template wpsl-filter ' . $appearance->get_template_classes() . '"' . $appearance->get_template_inline_styles() . '>'  . "\r\n";
$output .= '    <div class="wpsl-search wpsl-clearfix">';
$output .= '        <div id="wpsl-search-wrap">';
$output .= '            <div data-filter="input" class="wpsl-input" ' . $appearance->get_input_visibility() . '>';
$output .= '                <div>';
$search_visibility_key = ( $label_type === 'search_name_label' ) ? 'search_name' : 'search';
if ( $i18n->is_label_visible( $search_visibility_key ) ) {
    $output .= '                    <label for="wpsl-search-input">' . esc_html( $i18n->get_translation( $label_type, $label_default ) ) . '</label>';
}
$output .= '                </div>';
$output .= '                <input id="wpsl-search-input" type="text" value="' . esc_html__( 'Your Locations', 'wp-store-locator' ) . '" name="wpsl-search-input" placeholder="" aria-required="true" class="pac-target-input" autocomplete="off">';
$output .= '            </div>';

$output .= '            <div data-filter="results" class="wpsl-select-wrap" ' . $appearance->get_results_visibility() . '>';
$output .= '                <div id="wpsl-radius" ' . ( $wpsl_settings['radius_dropdown'] ? '' : ' style="display: none;"' ) . '  data-filter="radius">';
if ( $i18n->is_label_visible( 'radius' ) ) {
    $output .= '                    <label for="wpsl-radius-dropdown">' . esc_html( $i18n->get_translation( 'radius_label', __( 'Search radius', 'wp-store-locator' ) ) ) .'</label>';
}
$output .= '                    <select id="wpsl-radius-dropdown" class="wpsl-dropdown" name="wpsl-radius">';
$output .= '                        <option value="10">10 km</option>';
$output .= '                        <option value="25">25 km</option>';
$output .= '                        <option selected="selected" value="50">50 km</option>';
$output .= '                        <option value="100">100 km</option>';
$output .= '                        <option value="200">200 km</option>';
$output .= '                        <option value="500">500 km</option>';
$output .= '                    </select>';
$output .= '                </div>';

$output .= '                <div id="wpsl-results"' . ( $wpsl_settings['results_dropdown'] ? '' : ' style="display: none;"' ) . ' data-filter="max_results">';
if ( $i18n->is_label_visible( 'results' ) ) {
    $output .= '                    <label for="wpsl-results-dropdown">' . esc_html( $i18n->get_translation( 'results_label', __( 'Results', 'wp-store-locator' ) ) ) . '</label>';
}
$output .= '                    <select id="wpsl-results-dropdown" class="wpsl-dropdown" name="wpsl-results" >';
$output .= '                        <option value="5">5</option>';
$output .= '                        <option selected="selected" value="10">10</option>';
$output .= '                        <option value="50">50</option>';
$output .= '                        <option value="75">75</option>';
$output .= '                        <option value="100">100</option>';
$output .= '                    </select>';
$output .= '                </div>';
$output .= '            </div>';

if ( $wpsl_settings['category_filter'] ) {
    $output .= '            <div class="wpsl-flex-linebreak" aria-hidden="true"></div>';
}
$output .= '            <div data-filter="category" ' . ( $wpsl_settings['category_filter'] ? '' : ' style="display: none;"' ) . '>';
$output .= '                <div data-filter="category_dropdown" ' . ( $wpsl_settings['category_filter_type'] == 'dropdown' ? '' : ' style="display: none;"' ) . '>';
$output .=                      $filters->category_list( [ 'style' => 'dropdown', 'defaults' => true ] );
$output .= '                </div>';

$output .= '                <div data-filter="category_checkboxes" ' . ( $wpsl_settings['category_filter_type'] == 'checkboxes' ? '' : ' style="display: none;"' ) . '>';
$output .=                      $filters->category_list( [ 'style' => 'checkboxes', 'defaults' => true ] );
$output .= '                </div>';
$output .= '            </div>';

$output .= '            <div data-filter="search" class="wpsl-search-btn-wrap">';
$output .= '                <input id="wpsl-search-btn" type="submit" value="' . esc_attr( $i18n->get_translation( 'search_btn_label', __( 'Search', 'wp-store-locator' ) ) ) . '" tabindex="0">';
$output .= '            </div>';
$output .= '        </div>';
$output .= '    </div>';
$output .= '    <div id="wpsl-{{map_provider}}-wrap" class="wpsl-map-style-wrap"></div>';

$output .= '    <div id="wpsl-result-list" class="wpsl-no-flex">';
$output .= '        <div id="wpsl-stores">';
$output .= '            ' . $appearance->get_column_location_list();
$output .= '        </div>';
$output .= '    </div>';
$output .= '</div>';

return $output;
?>