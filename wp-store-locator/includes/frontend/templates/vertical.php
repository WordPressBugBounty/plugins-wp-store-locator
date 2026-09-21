<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are passed in scope, not global
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$output         = $assets_manager->get_custom_css();
$autoload_class = ( ! $wpsl_settings['map']['autoload'] ) ? 'class="wpsl-not-loaded"' : '';
$radius_enabled    = $panel_filters->is_radius_enabled();
$category_enabled  = $panel_filters->is_category_enabled();
$has_extra_panels  = (bool) apply_filters( 'wpsl_has_panel_filters', false, $panel_filters );

// Resolve how the filter buttons are laid out: horizontal row, stacked
// full-width, or nested under a single "Filters" button.
$filter_layout = ! empty( $wpsl_settings['appearance']['filter_layout'] ) ? $wpsl_settings['appearance']['filter_layout'] : 'horizontal';

// Whether any filter UI is rendered at all.
$has_panel_filters = $radius_enabled || ( $category_enabled && $panel_filters->has_categories() ) || $has_extra_panels;

/**
 * Whether to render the filter header ( title + close button ).
 *
 * The horizontal layout closes a panel automatically when a value is picked
 * ( e.g. selecting a radius ), so it only needs the header when a category /
 * custom panel with checkboxes is present. The nested layout never
 * auto-closes, so it always needs the header's close button to get back to
 * the results - even for a radius-only setup.
 */
$show_filter_header = ( $category_enabled && $panel_filters->has_categories() ) || $has_extra_panels || ( 'nested' === $filter_layout && $has_panel_filters );

$output .= '<div id="wpsl-wrap" ' . $assets_manager->get_outer_class() . '>' . "\r\n";
$output .= $frontend->maybe_show_gdpr_checkpoint();
$output .= "\t" . '<div id="wpsl-panel">' . "\r\n";
$output .= "\t\t" . '<div class="wpsl-search">' . "\r\n";
$output .= "\t\t\t" . '<form autocomplete="off">' . "\r\n";
$output .= "\t\t\t" . '<div class="wpsl-search-wrap">' . "\r\n";
$output .= "\t\t\t\t" . '<input id="wpsl-search-input" type="text" value="' . esc_attr( apply_filters( 'wpsl_search_input', '' ) ) . '" name="wpsl-search-input" placeholder="' . esc_attr( $wpsl_settings['search']['input_placeholder'] ) . '" aria-required="true"/>' . "\r\n";
$output .= $assets_manager->maybe_output_mapbox_autocomplete();
$output .= '<div class="wpsl-search-action-wrapper" id="wpsl-clear-wrapper">';
$output .= '<button id="wpsl-clear-search-input" type="reset" aria-label="' . esc_attr__( 'Clear search input', 'wp-store-locator' ) . '" class="wpsl-svg-cross">' . wpsl_get_svg_icon( 'reset' ) . '</button>';
$output .= '</div>';
$output .= '<div class="wpsl-search-action-wrapper" id="wpsl-submit-wrapper">';
$output .= '<button id="wpsl-search-btn" type="button" aria-label="' . esc_attr__( 'Search', 'wp-store-locator' ) . '">' . wpsl_get_svg_icon( 'search' ) . '</button>';
$output .= '</div>';
$output .= "\t\t\t" . '</div>' . "\r\n";
$output .= "\t\t\t" . '</form>' . "\r\n";
$output .= "\t\t" . '</div>' . "\r\n";

if ( $has_panel_filters ) {
    $filter_classes = [];

    if ( $radius_enabled ) {
        $filter_classes[] = 'wpsl-radius-option';
    }

    if ( 'stacked' === $filter_layout ) {
        $filter_classes[] = 'wpsl-filters-stacked';
    } elseif ( 'nested' === $filter_layout ) {
        $filter_classes[] = 'wpsl-filters-nested';
    }

    $filter_class_attr = $filter_classes ? ' class="' . esc_attr( implode( ' ', $filter_classes ) ) . '"' : '';

    $output .= '<div id="wpsl-result-filters"' . $filter_class_attr . '>';
        $output .= $panel_filters->render_filter_buttons( $filter_layout );
    $output .= '</div>';
}

if ( $show_filter_header ) {
    $filters_label = $i18n->get_translation( 'filters_label', __( 'Filters', 'wp-store-locator' ) );

    $output .= '<div id="wpsl-filter-header">';
        $output .= '<p class="wpsl-close-filter"><span class="wpsl-filter-title" data-default="' . esc_attr( $filters_label ) . '">' . esc_html( $filters_label ) . '</span><button type="reset" id="wpsl-clear-filter" class="wpsl-svg-cross" tabindex="-1">';
        $output .= wpsl_get_svg_icon( 'reset' );
        $output .= '</button>';
        $output .= '</p>';
    $output .= '</div>';
}

if ( $has_panel_filters ) {
    $output .= '<div id="wpsl-filter-options" data-supported-filters="' . $panel_filters->active_filters() . '">';
        $output .= $panel_filters->render_filter_options( $filter_layout );
    $output .= '</div>';
}

$output .= "\t" . '<div id="wpsl-result-list">' . "\r\n";
$output .= "\t\t" . '<div id="wpsl-stores" '. $autoload_class .'>' . "\r\n";
$output .= "\t\t\t" . '<ul></ul>' . "\r\n";
$output .= "\t\t" . '</div>' . "\r\n";
$output .= "\t\t" . '<div id="wpsl-direction-details">' . "\r\n";
$output .= "\t\t\t" . '<ul></ul>' . "\r\n";
$output .= "\t\t" . '</div>' . "\r\n";
$output .= "\t" . '</div>' . "\r\n";


$output .= "\t" . '</div>' . "\r\n";
$output .= "\t" . '<div id="wpsl-map" class="wpsl-canvas-' . esc_attr( $wpsl_settings['api']['active_map_service'] ) . '">';
$output .= "\t" .  '</div>' . "\r\n";
$output .= '</div>' . "\r\n";

if ( $wpsl_settings['map']['show_credits'] ) { 
    /* translators: 1: opening link tag, 2: closing link tag */
    $output .= '<div class="wpsl-provided-by">'. sprintf( esc_html__( "Search provided by %1\$sWP Store Locator%2\$s", 'wp-store-locator' ), "<a target='_blank' href='https://wpstorelocator.co'>", "</a>" ) .'</div>' . "\r\n";
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The dynamic values are escaped while the template output is built above.
echo $output;