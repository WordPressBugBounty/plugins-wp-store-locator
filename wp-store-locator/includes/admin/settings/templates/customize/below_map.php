<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from the container
$wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );
$filters = wpsl_get_service( 'template_filters' );
$appearance = wpsl_get_service( 'appearance' );
$location_example = $appearance->location_examples();
$countries = wpsl_get_unique_countries();

$output = '<div id="wpsl-wrap" class="wpsl-store-below ' . $appearance->get_template_classes() . '"' . $appearance->get_template_inline_styles() . '>';
$output .= '    <div class="wpsl-search wpsl-clearfix">';
$output .= '        <div id="wpsl-search-wrap">';
$output .= '            <div data-filter="input" class="wpsl-input" ' . $appearance->get_input_visibility() . '>';
$output .= '                <div>';
$output .= '                    <label for="wpsl-search-input">' . esc_html__( 'Your location', 'wp-store-locator' ) . '</label>';
$output .= '                </div>';
$output .= '                <input id="wpsl-search-input" type="text" value="' . esc_html__( 'Your Locations', 'wp-store-locator' ) . '" name="wpsl-search-input" placeholder="" aria-required="true" class="pac-target-input" autocomplete="off">';
$output .= '            </div>';

$output .= '            <div data-filter="countries" ' . ( $wpsl_settings['country_filter'] && count ( $countries ) > 1 ? '' : ' style="display: none;"' ) . '>';
$output .=                  $filters->country_list();
$output .= '            </div>';

$output .= '            <div data-filter="results" class="wpsl-select-wrap" ' . $appearance->get_results_visibility() . '>';
$output .= '                <div id="wpsl-radius" ' . ( $wpsl_settings['radius_dropdown'] ? '' : ' style="display: none;"' ) . '  data-filter="radius">';
$output .= '                    <label for="wpsl-radius-dropdown">' . esc_html__( 'Search radius', 'wp-store-locator' ) .'</label>';
$output .= '                    <select id="wpsl-radius-dropdown" name="wpsl-radius">';
$output .= '                        <option value="10">10 km</option>';
$output .= '                        <option value="25">25 km</option>';
$output .= '                        <option selected="selected" value="50">50 km</option>';
$output .= '                        <option value="100">100 km</option>';
$output .= '                        <option value="200">200 km</option>';
$output .= '                        <option value="500">500 km</option>';
$output .= '                    </select>';
$output .= '                </div>';

$output .= '                <div id="wpsl-results"' . ( $wpsl_settings['results_dropdown'] ? '' : ' style="display: none;"' ) . ' data-filter="max_results">';
$output .= '                    <label for="wpsl-results-dropdown"' . ( $wpsl_settings['results_dropdown'] && ! $wpsl_settings['radius_dropdown'] ? ' style="width:95px;"' : '' ) . '>' . esc_html__( 'Results', 'wp-store-locator' ) . '</label>';
$output .= '                    <select id="wpsl-results-dropdown" class="" name="wpsl-results" >';
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
$output .= '                <input id="wpsl-search-btn" type="submit" value="Search" tabindex="0">';
$output .= '            </div>';
$output .= '        </div>';
$output .= '    </div>';
$output .= '    <div id="wpsl-{{map_provider}}-wrap" class="wpsl-map-style-wrap"></div>';

$output .= '    <div id="wpsl-result-list" class="wpsl-no-flex">';
$output .= '        <div id="wpsl-stores">';
$output .= '            <ul class="wpsl-columns-' . esc_attr( $wpsl_settings['result_columns'] ) . '" data-latlng="' . $location_example['latlng'] . '">';
$output .= '                ' . $location_example['html'];
$output .= '            </ul>';
$output .= '        </div>';
$output .= '    </div>';
$output .= '</div>';

return $output;
?>