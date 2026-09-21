<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create the table that holds the theme
 * customizations ( style / template ).
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_create_theme_table() {
    global $wpdb;

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $wpdb->wpsl_themes = $wpdb->prefix . 'wpsl_themes';

    $collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

    $sql = "CREATE TABLE IF NOT EXISTS $wpdb->wpsl_themes (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `template` varchar(50) NOT NULL,
                `content` mediumtext,
                `section` varchar(25) NOT NULL,
                `language` varchar(5) NOT NULL,
                `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
    ) $collate;";

    dbDelta( $sql );
}

/**
 * Format a string that can be used as an alt text.
 *
 * @since  3.0.0
 * @param  string $input The input string.
 * @return string        The formatted string.
 */
function wpsl_create_alt_text( $input ) {
    // Split the input at the first occurrence of a period
    $parts = explode( '.', $input, 2 );
    $input = $parts[0];

    // Replace '-' and '_' with spaces
    $input = str_replace( [ '-', '_' ], ' ', $input );

    // Remove any characters that are not letters or spaces
    $input = preg_replace( '/[^a-zA-Z\s]/', '', $input );

    // Capitalize the first letter of each word
    $input = ucwords( $input );

    return $input;
}

/**
 * Check if the required API keys are present for the selected map provider.
 *
 * @since  3.0.0
 * @param  string $map_service Optional. The map service to check. If not provided, uses the active map service.
 * @return bool   True if API keys are present, false otherwise.
 */
function wpsl_has_map_api_key( $map_service = '' ) {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'api' );

    // If no map service is specified, use the active one
    if ( empty( $map_service ) ) {
        $map_service = $wpsl_settings['active_map_service'];
    }

    // Check if the required API keys are present based on the map provider
    switch ( $map_service ) {
        case 'gmaps':
            return ! empty( $wpsl_settings['gmaps_browser_key'] );
        case 'mapbox':
            return ! empty( $wpsl_settings['mapbox_key'] );
        case 'stadia':
            return ! empty( $wpsl_settings['stadia_key'] );
        case 'osm':
            // OSM doesn't require an API key for basic geocoding
            return true;
        default:
            return false;
    }
}

/**
 * The wpstorelocator.co walkthrough for creating a provider's API key.
 *
 * @since  3.0.0
 * @param  string $map_service The map service ( gmaps, mapbox, stadia ).
 * @return string The documentation URL, or an empty string for providers
 *                without a key.
 */
function wpsl_create_key_docs_url( $map_service ) {
    $urls = [
        'gmaps'  => 'https://wpstorelocator.co/document/create-google-api-keys/',
        'mapbox' => 'https://wpstorelocator.co/document/create-mapbox-api-key/',
        'stadia' => 'https://wpstorelocator.co/document/create-stadia-maps-api-key/',
    ];

    return isset( $urls[ $map_service ] ) ? $urls[ $map_service ] : '';
}

/**
 * The admin "a valid API key is required to load the map" message for a
 * provider that can't render anything without one.
 *
 * Links the API settings section and create-a-key docs. Shared by the admin
 * JS localization and the Map Shapes editor data, so every admin screen shows
 * the same message.
 *
 * @since  3.0.0
 * @param  string $map_service The map service ( mapbox, stadia ).
 * @return string The message HTML, or an empty string for providers
 *                that don't require a key or handle errors themselves.
 */
function wpsl_admin_key_required_message( $map_service ) {
    $names = [
        'mapbox' => 'Mapbox',
        'stadia' => 'Stadia Maps',
    ];

    if ( ! isset( $names[ $map_service ] ) ) {
        return '';
    }

    return sprintf(
        /* translators: 1: map provider name, 2: opening link tag for API section, 3: closing link tag, 4: opening link tag to the create-a-key documentation, 5: closing link tag */
        esc_html__( 'A valid %1$s %2$sAPI key%3$s is required to load the map! %4$sLearn how to create one%5$s.', 'wp-store-locator' ),
        $names[ $map_service ],
        '<a class="wpsl-trigger-nav" data-item="api" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) . '">',
        '</a>',
        '<a target="_blank" href="' . wpsl_create_key_docs_url( $map_service ) . '">',
        '</a>'
    );
}

/**
 * The generic admin "a valid API key is required to load the map" message.
 *
 * @since  3.0.0
 * @return string The message HTML.
 */
function wpsl_admin_api_key_missing_message() {
    return sprintf(
        /* translators: 1: opening link tag for API section, 2: closing link tag */
        esc_html__( 'A valid %1$sAPI key%2$s is required to load the map!', 'wp-store-locator' ),
        '<a class="wpsl-trigger-nav" data-item="api" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) . '">',
        '</a>'
    );
}

/**
 * The show / hide control that sits at the right-hand end 
 * of a masked API key input.
 *
 * The key inputs render as type="password" so a key isn't left on screen
 * during a screen share or a screenshot. This flips one back to plain text.
 *
 * There is nothing to reveal in an empty input, so the control starts
 * hidden until a key exists. wpsl-key-visibility.js shows it again as
 * soon as one is typed or pasted.
 *
 * @since  3.0.0
 * @param  string $input_id The id of the key input this control toggles.
 * @param  string $key      The key currently in the input.
 * @return string The button HTML.
 */
function wpsl_key_visibility_toggle( $input_id, $key = '' ) {
    $show = __( 'Show', 'wp-store-locator' );
    $hide = __( 'Hide', 'wp-store-locator' );

    return sprintf(
        '<button type="button" class="wpsl-toggle-key" aria-controls="%1$s" aria-pressed="false" data-show="%2$s" data-hide="%3$s"%5$s><span class="wpsl-toggle-key-text">%4$s</span></button>',
        esc_attr( $input_id ),
        esc_attr( $show ),
        esc_attr( $hide ),
        esc_html( $show ),
        '' === trim( (string) $key ) ? ' hidden' : ''
    );
}

/**
 * Get a list of locations where different data 
 * ( hours, contact details, etc.) can be displayed.
 *
 * @since  3.0.0
 * @return array
 */
function wpsl_get_multiselect_ux_locations() {
    $wpsl_settings = wpsl_get_service( 'wpsl_settings' )->get_group( 'local_pages' );
    
    // Contact detail location(s)
    $multiselect_options = [
        //'search_results' => __( 'In the search results below the address', 'wp-store-locator' ),
        'search_results' => __( 'Below the address in the search results', 'wp-store-locator' ),
        'more_info'      => __( 'The "more info" section in the search results', 'wp-store-locator' ),
        'marker_popup'   => __( 'The store locator marker pop-up', 'wp-store-locator' )
    ];

    if ( $wpsl_settings['permalinks'] ) {
        $multiselect_options['landing_page'] = __( 'Below the address on the landing page', 'wp-store-locator' );
        $multiselect_options['landing_page_marker_popup'] = __( 'The landing page marker pop-up', 'wp-store-locator' );
    }
    
    return $multiselect_options;
}