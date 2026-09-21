<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get services from the container
$wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'search' );
$appearance_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );
$filters = wpsl_get_service( 'template_filters' );
$panel_filters = wpsl_get_service( 'panel_filters' );
$appearance = wpsl_get_service('appearance');
$i18n = wpsl_get_service( 'i18n' );
$location_example = $appearance->location_examples();

$output = '<div id="wpsl-wrap" class="wpsl-flex wpsl-vertical-template wpsl-filter ' . $appearance->get_template_classes() . '"' . $appearance->get_template_inline_styles() . '>'  . "\r\n";
$output .= "\t\t\t" .'<div id="wpsl-panel">' . "\r\n";
$output .= "\t\t\t\t" .'<div class="wpsl-search">' . "\r\n";
$output .= "\t\t\t\t\t" .'<div class="wpsl-search-wrap">' . "\r\n";
$output .= "\t\t\t\t\t\t" .'<input id="wpsl-search-input" type="text" value="' . esc_html__( 'Your Locations', 'wp-store-locator' ) . '" name="wpsl-search-input" placeholder="" aria-required="true" class="pac-target-input" autocomplete="off">'  . "\r\n";
$output .= "\t\t\t\t\t\t" .'<div class="wpsl-search-action-wrapper" id="wpsl-clear-wrapper">' . "\r\n";
$output .= "\t\t\t\t\t\t\t" .'<button id="wpsl-clear-search-input" type="reset" aria-label="' . esc_attr__( 'Clear search input', 'wp-store-locator' ) . '" class="wpsl-svg-cross">' . "\r\n";
$output .= "\t\t\t\t\t\t\t\t" . wpsl_get_svg_icon( 'reset' );
$output .= "\t\t\t\t\t\t\t" . '</button>' . "\r\n";
$output .= "\t\t\t\t\t\t" .'</div>' . "\r\n";
$output .= "\t\t\t\t\t\t" .'<div class="wpsl-search-action-wrapper" id="wpsl-submit-wrapper">' . "\r\n";
$output .= "\t\t\t\t\t\t\t" .'<button id="wpsl-search-btn" type="button" aria-label="' . esc_attr__( 'Search', 'wp-store-locator' ) . '">' . "\r\n";
$output .= "\t\t\t\t\t\t\t\t" . wpsl_get_svg_icon( 'search' );
$output .= "\t\t\t\t\t\t\t" . '</button>' . "\r\n";
$output .= "\t\t\t\t\t\t" .'</div>' . "\r\n";
$output .= "\t\t\t\t\t" . '</div>' . "\r\n";
$output .= "\t\t\t" . '</div>' . "\r\n";

$filters_label    = $i18n->get_translation( 'filters_label', __( 'Filters', 'wp-store-locator' ) );
$categories_label = $i18n->get_translation( 'categories_label', __( 'Categories', 'wp-store-locator' ) );
$filter_layout    = ! empty( $appearance_settings['filter_layout'] ) ? $appearance_settings['filter_layout'] : 'horizontal';

// Example category + radius option markup, reused by every layout below.
$categories_mock  = '<div class="wpsl-filter" data-type="category">';
$categories_mock .= '    <ul>';
$categories_mock .= '        <li class="wpsl-categories-level-0" aria-selected="false"><label><input class="wpsl-categories-level-0" type="checkbox" value="3">Amsterdam</label></li>';
$categories_mock .= '        <li class="wpsl-categories-level-0" aria-selected="false"><label><input class="wpsl-categories-level-0" type="checkbox" value="6">Category test</label></li>';
$categories_mock .= '        <li class="wpsl-categories-level-0 wpsl-has-child" aria-selected="false"><label><input class="wpsl-categories-level-0 wpsl-has-child" type="checkbox" value="4">Rotterdam</label>';
$categories_mock .= '            <ul>';
$categories_mock .= '                <li class="wpsl-categories-level-1 wpsl-has-child" aria-selected="false"><label><input class="wpsl-categories-level-1 wpsl-has-child" type="checkbox" value="40">Coolsingle</label>';
$categories_mock .= '                    <ul>';
$categories_mock .= '                        <li class="wpsl-categories-level-2" aria-selected="false"><label><input class="wpsl-categories-level-2" type="checkbox" value="108">V&amp;D</label></li>';
$categories_mock .= '                    </ul>';
$categories_mock .= '                </li>';
$categories_mock .= '            </ul>';
$categories_mock .= '        </li>';
$categories_mock .= '        <li class="wpsl-categories-level-0" aria-selected="false"><label><input class="wpsl-categories-level-0" type="checkbox" value="2">Utrecht</label></li>';
$categories_mock .= '    </ul>';
$categories_mock .= '</div>';

$radius_mock  = '<ul role="listbox" data-handler="radius">';
$radius_mock .= '    <li role="option" tabindex="0" aria-selected="false" data-radius="10">10 km</li>';
$radius_mock .= '    <li class="wpsl-selected-filter-option" role="option" tabindex="0" aria-selected="false" data-radius="20">20 km</li>';
$radius_mock .= '    <li role="option" tabindex="0" aria-selected="false" data-radius="30">30 km</li>';
$radius_mock .= '    <li role="option" tabindex="0" aria-selected="false" data-radius="50">50 km</li>';
$radius_mock .= '    <li role="option" tabindex="0" aria-selected="false" data-radius="100">100 km</li>';
$radius_mock .= '</ul>';

$filter_actions_mock  = '<div class="wpsl-filter-actions">';
$filter_actions_mock .= '    <button type="submit" id="wpsl-apply-filters" aria-expanded="false">' . esc_html( $i18n->get_translation( 'apply_label', __( 'Apply', 'wp-store-locator' ) ) ) . '</button>';
$filter_actions_mock .= '</div>';

// data-nested-label / data-filters-label expose the ( translated ) labels the
// JS needs when it rebuilds the baseline into another layout.
$dropdown_icon  = wpsl_get_svg_icon( 'dropdown' );
$radius_text    = $panel_filters->get_default_radius_text();
$category_shown = ! empty( $wpsl_settings['category_filter'] );
$radius_shown   = ! empty( $wpsl_settings['radius_dropdown'] );

$baseline_buttons  = '<button type="button" id="wpsl-show-filters" data-filter="category" data-nested-label="' . esc_attr( $categories_label ) . '"' . ( $category_shown ? '' : ' style="display: none;"' ) . '><div><span>' . esc_html( $i18n->get_translation( 'show_filters_label', __( 'Show filters', 'wp-store-locator' ) ) ) . '</span></div>' . $dropdown_icon . '</button>';
$baseline_buttons .= '<button type="button" id="wpsl-show-radius" data-filter="radius"' . ( $radius_shown ? '' : ' style="display: none;"' ) . '><div><span>' . esc_html( $radius_text ) . '</span></div>' . $dropdown_icon . '</button>';

$baseline_options  = '<div data-id="wpsl-show-filters" style="">' . $categories_mock . $filter_actions_mock . '</div>';
$baseline_options .= '<div data-id="wpsl-show-radius" style="display: none;">' . $radius_mock . '</div>';

$result_filters_class = 'wpsl-has-radius-option';

if ( 'nested' === $filter_layout ) {
    $result_filters_class .= ' wpsl-filters-nested';

    $rendered_buttons = '<button type="button" id="wpsl-show-nested"><div><span>' . esc_html( $filters_label ) . '</span></div>' . $dropdown_icon . '</button>';

    $nested_children = '';
    $nested_actions  = '';

    if ( $category_shown ) {
        $nested_children .= '<div class="wpsl-nested-filter">';
        $nested_children .= '<button type="button" class="wpsl-nested-toggle" aria-expanded="false"><div><span>' . esc_html( $categories_label ) . '</span></div>' . $dropdown_icon . '</button>';
        $nested_children .= '<div data-id="wpsl-show-filters" class="wpsl-nested-options">' . $categories_mock . '</div>';
        $nested_children .= '</div>';

        // The shared apply actions live in the category panel; the nested layout
        // moves them to the bottom of the panel.
        $nested_actions = $filter_actions_mock;
    }

    if ( $radius_shown ) {
        $nested_children .= '<div class="wpsl-nested-filter wpsl-nested-filter-radius">';
        $nested_children .= '<button type="button" class="wpsl-nested-toggle" aria-expanded="false"><div><span>' . esc_html( $radius_text ) . '</span></div>' . $dropdown_icon . '</button>';
        $nested_children .= '<div data-id="wpsl-show-radius" class="wpsl-nested-options">' . $radius_mock . '</div>';
        $nested_children .= '</div>';
    }

    $rendered_options = '<div data-id="wpsl-show-nested" class="wpsl-nested">' . $nested_children . $nested_actions . '</div>';
} else {
    if ( 'stacked' === $filter_layout ) {
        $result_filters_class .= ' wpsl-filters-stacked';
    }

    $rendered_buttons = $baseline_buttons;
    $rendered_options = $baseline_options;
}

$output .= '        <div id="wpsl-result-filters" class="' . esc_attr( $result_filters_class ) . '" data-filters-label="' . esc_attr( $filters_label ) . '" ' . $appearance->set_minheight() . '>';
$output .=                  $rendered_buttons;
$output .= '        </div>';
$output .= '        <div id="wpsl-filter-header"><p class="wpsl-close-filter">' . esc_html( $filters_label ) . '<button type="reset" id="wpsl-clear-filter">' . "\r\n";
$output .= "\t\t\t\t\t\t\t\t" . wpsl_get_svg_icon( 'reset' );
$output .= '        </button></p></div>';
$output .= '        <div id="wpsl-filter-options" style="display: none;">';
$output .=                  $rendered_options;
$output .= '        </div>';

// Horizontal baseline for the editor JS to rebuild other layouts on demand
// ( <template> content isn't rendered, so it can't flash ).
$output .= '        <template id="wpsl-filter-baseline-buttons">' . $baseline_buttons . '</template>';
$output .= '        <template id="wpsl-filter-baseline-options">' . $baseline_options . '</template>';
$output .= '        <div id="wpsl-result-list" style="display: block;">';
$output .= '            <div id="wpsl-stores" class="wpsl-no-flex">';
$output .= '                <ul data-latlng="' . $location_example['latlng'] . '">';
$output .= '                    ' . $location_example['html'];
$output .= '                </ul>';
$output .= '            </div>';
$output .= '        </div>';
$output .= '    </div><div id="wpsl-{{map_provider}}-wrap" class="wpsl-map-style-wrap"></div>';
$output .= '</div>';

return $output;
?>