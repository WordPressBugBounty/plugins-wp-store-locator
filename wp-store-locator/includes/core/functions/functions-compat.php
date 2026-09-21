<?php
/**
 * Deprecated v2.x template helper wrappers.
 *
 * Logic moved to WPSL\Core\Templates\Sections.
 *
 * @package WPSL
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'wpsl_store_header_template' ) ) {
    /**
     * Create the store header template.
     *
     * @since      2.0.0
     * @deprecated 3.0.0 Use wpsl_get_service( 'template_sections' )->store_header().
     * @param      string $location The location where the header is shown ( info_window / listing / wpsl_map ).
     * @return     string The template for the store header.
     */
    function wpsl_store_header_template( $location = 'info_window' ) {
        _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'template_sections' )->store_header()" );

        return wpsl_get_service( 'template_sections' )->store_header( $location );
    }
}

if ( ! function_exists( 'wpsl_address_format_placeholders' ) ) {
    /**
     * Create the address placeholders based on the structure defined on the settings page.
     *
     * @since      2.0.0
     * @deprecated 3.0.0 Use wpsl_get_service( 'template_sections' )->format_address().
     * @return     string A list of address placeholders in the correct order.
     */
    function wpsl_address_format_placeholders() {
        _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'template_sections' )->format_address()" );

        return wpsl_get_service( 'template_sections' )->format_address();
    }
}

if ( ! function_exists( 'wpsl_contact_details_template' ) ) {
    /**
     * Create the contact details template.
     *
     * @since      3.0.0
     * @deprecated 3.0.0 Use wpsl_get_service( 'template_sections' )->contact_details().
     * @param      bool $include_url Whether to include the store url row.
     * @return     string The contact details template.
     */
    function wpsl_contact_details_template( $include_url = false ) {
        _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'template_sections' )->contact_details()" );

        $sections = wpsl_get_service( 'template_sections' );

        // v2-style callers receive the HTML directly, so resolve the {{...}} tags here.
        return $sections->maybe_call_func( $sections->contact_details( $include_url ), [] );
    }
}

if ( ! function_exists( 'wpsl_more_info_template' ) ) {
    /**
     * Create the more info template.
     *
     * @since      2.0.0
     * @deprecated 3.0.0 Use wpsl_get_service( 'template_sections' )->more_info_template().
     * @return     string The template that is used to show the "More info" content.
     */
    function wpsl_more_info_template() {
        _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'template_sections' )->more_info_template()" );

        $sections = wpsl_get_service( 'template_sections' );

        // v2-style callers receive the HTML directly, so resolve the {{...}} tags here.
        return $sections->maybe_call_func( $sections->more_info_template(), [] );
    }
}

if ( ! function_exists( 'wpsl_maybe_new_window' ) ) {
    /**
     * Return the target attribute used to open links in a new window.
     *
     * @since      3.0.0
     * @deprecated 3.0.0 Use wpsl_get_service( 'template_sections' )->new_window().
     * @return     string The ' target="_blank"' attribute, or an empty string.
     */
    function wpsl_maybe_new_window() {
        _deprecated_function( __FUNCTION__, '3.0.0', "wpsl_get_service( 'template_sections' )->new_window()" );

        return wpsl_get_service( 'template_sections' )->new_window();
    }
}

if ( ! function_exists( 'wpsl_create_underscore_templates' ) ) {
    /**
     * Create the store data templates.
     *
     * The v2 template-generation mechanism ( echoing <script> template blocks )
     * was replaced in v3 by the WPSL\Frontend\Templates\Manager rendering
     * pipeline, which delivers the templates to JS as JSON. There is no direct
     * replacement to delegate to, so this shim only emits the deprecation notice.
     *
     * @since      2.0.0
     * @deprecated 3.0.0 No replacement; templates are rendered by WPSL\Frontend\Templates\Manager.
     * @param      string $template       The type of template ( unused ).
     * @param      array  $shortcode_atts The shortcode attributes ( unused ).
     * @return     void
     */
    function wpsl_create_underscore_templates( $template, $shortcode_atts = '' ) {
        _deprecated_function( __FUNCTION__, '3.0.0' );
    }
}

/**
 * Build a flat settings array compatible with the v2.x $wpsl_settings global.
 *
 * Populates $GLOBALS['wpsl_settings'] so that filter callbacks written for
 * v2.x - the ones opening with `global $wpsl_settings` - continue to work
 * without any code changes.
 *
 * New code should use the v3 helpers instead:
 *   wpsl_settings( 'search', 'distance_unit' )
 *   wpsl_get_service( 'wpsl_settings' )->get_group( 'search' )
 *
 * @since  3.0.0
 * @return array Flat settings array matching the v2.x $wpsl_settings format
 */
function wpsl_build_v2_settings() {
    $handler     = wpsl_get_service( 'wpsl_settings' );
    $api         = $handler->get_group( 'api' );
    $search      = $handler->get_group( 'search' );
    $map         = $handler->get_group( 'map' );
    $ux          = $handler->get_group( 'ux' );
    $markers     = $handler->get_group( 'markers' );
    $editor      = $handler->get_group( 'editor' );
    $appearance  = $handler->get_group( 'appearance' );
    $local_pages = $handler->get_group( 'local_pages' );
    $tools       = $handler->get_group( 'tools' );
    $labels      = $handler->get_group( 'labels' );

    return [
        // API
        'api_browser_key'           => $api['gmaps_browser_key'],
        'api_server_key'            => $api['gmaps_server_key'],
        'api_language'              => $api['gmaps_language'],
        'api_region'                => $api['gmaps_region'],
        // v2 stored as array('autocomplete'=>'latest'); v3 wraps it under 'gmaps'
        'api_versions'              => $api['versions']['gmaps'],

        // Search
        'auto_locate'               => $search['auto_locate'],
        'autocomplete'              => $search['autocomplete'],
        'distance_unit'             => $search['distance_unit'],
        'max_results'               => $search['max_results'],
        'search_radius'             => $search['search_radius'],
        'force_postalcode'          => $search['force_postalcode'],
        'radius_dropdown'           => $search['radius_dropdown'],
        'results_dropdown'          => $search['results_dropdown'],
        'category_filter'           => $search['category_filter'],
        'category_filter_type'      => $search['category_filter_type'],

        // Map
        'start_name'                => $map['start_name'],
        'start_latlng'              => $map['start_latlng'],
        'autoload'                  => $map['autoload'],
        'autoload_limit'            => $map['autoload_limit'],
        'run_fitbounds'             => $map['run_fitbounds'],
        'zoom_level'                => $map['zoom_level'],
        'auto_zoom_level'           => $map['auto_zoom_level'],
        'map_type'                  => $map['type'],    // v2 key 'map_type', v3 key 'type'
        'streetview'                => $map['streetview'],
        'type_control'              => $map['type_control'],
        'scrollwheel'               => $map['scrollwheel'],
        'control_position'          => $map['control_position'],
        'show_credits'              => $map['show_credits'],

        // UX
        'marker_effect'             => $ux['marker_effect'],
        'address_format'            => $ux['address_format'],
        'hide_distance'             => $ux['hide_distance'],
        'hide_country'              => $ux['hide_country'],
        'show_contact_details'      => $ux['show_contact_details'],
        'clickable_contact_details' => $ux['clickable_contact_details'],
        'new_window'                => $ux['new_window'],
        'reset_map'                 => $ux['reset_map'],
        'listing_below_no_scroll'   => $ux['listing_below_no_scroll'],
        'direction_redirect'        => $ux['direction_redirect'],
        // commented out in v3 defaults — read from DB if migrated, fall back to v2 default
        'more_info'                 => isset( $ux['more_info'] ) ? $ux['more_info'] : 0,
        'store_url'                 => $ux['store_url'],
        'phone_url'                 => $ux['phone_url'],
        'marker_streetview'         => $ux['marker_streetview'],
        'marker_zoom_to'            => $ux['marker_zoom_to'],
        'more_info_location'        => isset( $ux['more_info_location'] ) ? $ux['more_info_location'] : 'info window',
        'mouse_focus'               => $ux['mouse_focus'],

        // Markers
        'start_marker'              => $markers['start_marker'],
        'store_marker'              => $markers['store_marker'],
        'marker_clusters'           => $markers['marker_clusters'],
        'cluster_zoom'              => $markers['cluster_zoom'],
        'cluster_size'              => $markers['cluster_size'],

        // Editor
        'editor_country'            => $editor['country'],
        'editor_map_type'           => $editor['map_type'],
        'editor_hours'              => $editor['hours'],
        'editor_hour_input'         => $editor['hour_input'],
        'editor_hour_format'        => $editor['hour_format'],
        'hide_hours'                => $editor['hide_hours'],

        // Appearance
        'template_id'               => $appearance['template_id'],
        'height'                    => $appearance['dimensions']['sl_height'],
        'search_width'              => $appearance['dimensions']['search_width'],
        'label_width'               => $appearance['dimensions']['label_width'],
        'map_style'                 => $appearance['map_style']['gmaps']['json'],
        // v2-only settings with no v3 equivalent — return legacy defaults
        'infowindow_width'          => 225,
        'infowindow_style'          => 'default',
        'zoom_controls'             => 0,
        'fullscreen'                => 0,

        // Local Pages
        'permalinks'                => $local_pages['permalinks'],
        'permalink_remove_front'    => $local_pages['permalink_remove_front'],
        'permalink_slug'            => $local_pages['permalink_slug'],
        'category_slug'             => $local_pages['category_slug'],

        // Tools
        'debug'                     => $tools['debug'],
        'deregister_gmaps'          => $tools['deregister_gmaps'],

        // Labels
        'start_label'               => $labels['start_label'],
        'search_label'              => $labels['search_label'],
        'search_name_label'         => $labels['search_name_label'],
        'search_btn_label'          => $labels['search_btn_label'],
        'preloader_label'           => $labels['preloader_label'],
        'radius_label'              => $labels['radius_label'],
        'no_results_label'          => $labels['no_results_label'],
        'results_label'             => $labels['results_label'],
        'more_label'                => $labels['more_label'],
        'directions_label'          => $labels['directions_label'],
        'no_directions_label'       => $labels['no_directions_label'],
        'back_label'                => $labels['back_label'],
        'street_view_label'         => $labels['street_view_label'],
        'zoom_here_label'           => $labels['zoom_here_label'],
        'error_label'               => $labels['error_label'],
        'limit_label'               => $labels['limit_label'],
        'phone_label'               => $labels['phone_label'],
        'fax_label'                 => $labels['fax_label'],
        'email_label'               => $labels['email_label'],
        'url_label'                 => $labels['url_label'],
        'hours_label'               => $labels['hours_label'],
        'category_label'            => $labels['category_label'],
        'category_default_label'    => $labels['category_default_label'],
    ];
}

/**
 * Populate the v2.x $wpsl global for template-filter callbacks.
 *
 * v2 callbacks overriding an underscore template section routinely open with
 * `global $wpsl` and read $wpsl->i18n->get_translation() for labels. v3 only
 * defines that global for v2 custom template files, so such a callback would
 * fatal on ->i18n. This opts it in only when an underscore-section filter has
 * a callback attached, instead of always defining it.
 *
 * Called from both collect_sections() ( shortcode rendering ) and
 * Sections::get() ( admin section editor ), not just on plugins_loaded, so 1.x
 * add-ons probing for the global still find nothing there.
 *
 * @since  3.0.0
 * @return \WPSL\Frontend\Templates\Backward_Compatibility|object|null The v2 $wpsl
 *         stand-in, or null when no template section is being overridden.
 */
function wpsl_maybe_set_v2_global() {

    // A v2 custom template may already have populated it during the_content.
    if ( isset( $GLOBALS['wpsl'] ) ) {
        return $GLOBALS['wpsl'];
    }

    $template_filters = [
        'wpsl_listing_template',
        'wpsl_info_window_template',
        'wpsl_cpt_info_window_template',
        'wpsl_number_results_template',
        'wpsl_more_info_template',
        'wpsl_store_header_template',
    ];

    $has_override = false;

    foreach ( $template_filters as $template_filter ) {
        if ( has_filter( $template_filter ) ) {
            $has_override = true;
            break;
        }
    }

    if ( ! $has_override ) {
        return null;
    }

    $GLOBALS['wpsl'] = new \WPSL\Frontend\Templates\Backward_Compatibility( [
        'i18n'             => wpsl_get_service( 'i18n' ),
        'assets_manager'   => wpsl_get_service( 'assets_manager' ),
        'maps_manager'     => wpsl_get_service( 'maps_manager' ),
        'search_filters'   => wpsl_get_service( 'search_filters' ),
        'frontend'         => wpsl_get_service( 'frontend' ),
        'template_filters' => wpsl_get_service( 'template_filters' ),
        'wpsl_settings'    => wpsl_build_v2_settings(),
    ] );

    return $GLOBALS['wpsl'];
}