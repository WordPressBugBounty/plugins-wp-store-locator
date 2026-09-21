<?php
/**
 * Template, weekday and opening-hours helpers.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return a list of the store templates.
 *
 * @since  1.2.20
 * @return array $templates The list of default store templates
 */
function wpsl_get_templates() {
    $templates = [
        [
            'id'             => 'default',
            'name'           => esc_html__( 'Default', 'wp-store-locator' ),
            'desc'           => esc_html__( 'A full-width search form is positioned at the top, with the store listings and map container displayed in two columns below.', 'wp-store-locator' ),
            'path'           => WPSL_PLUGIN_DIR . 'includes/frontend/templates/default.php',
            'customize_path' => WPSL_URL . 'admin/settings/templates/customize/default.html', // Optional for custom templates
            'placeholder'    => WPSL_URL . 'assets/img/admin/themes/default.svg',
        ],
        [
            'id'          => 'horizontal',
            'name'        => esc_html__( 'Horizontal', 'wp-store-locator' ),
            'desc'        => esc_html__( 'The search form, map, and results are displayed in a single column, one below the other.', 'wp-store-locator' ),
            'path'        => WPSL_PLUGIN_DIR . 'includes/frontend/templates/horizontal.php',
            'placeholder' => WPSL_URL . 'assets/img/admin/themes/horizontal.svg',
        ],
        [
            'id'          => 'vertical',
            'name'        => esc_html__( 'Vertical', 'wp-store-locator' ),
            'desc'        => esc_html__( 'A two-column layout where the left column holds the search form and results, while the right column contains the map.', 'wp-store-locator' ),
            'path'        => WPSL_PLUGIN_DIR . 'includes/frontend/templates/vertical.php',
            'placeholder' => WPSL_URL . 'assets/img/admin/themes/vertical.svg',
            'exclude_css_properties' => [ 'submit_border', 'submit_border_hover' ],
            'has_panel' => true,
        ],
    ];

    return apply_filters( 'wpsl_templates', $templates );
}

/**
 * Get the active template details
 *
 * @since  3.0.0
 * @return array The active template data (id, name, desc, path, etc.)
 */
function wpsl_get_active_template() {
    $wpsl_settings = wpsl_container()->get( 'wpsl_settings' )->get_all();
    $template_id   = isset( $wpsl_settings['appearance']['template_id'] ) ? $wpsl_settings['appearance']['template_id'] : 'default';
    
    $template_loader = wpsl_get_service( 'template_loader' );
    $template_details = $template_loader->get_details( $template_id );

    return $template_details;
}

/**
 * Check if a template is loaded from outside the plugin directory ( = custom template ).
 *
 * The template id is intentionally ignored, a custom template can only replace
 * a reserved id ( default, horizontal, vertical ) by overriding the built-in
 * entry through the 'wpsl_templates' filter, and in that case the path will
 * always differ from the plugin directory.
 *
 * @since  3.0.0
 * @param  array $template The template data ( id, name, path, etc. )
 * @return bool  True if the template path is outside the plugin directory
 */
function wpsl_is_custom_template( $template ) {
    if ( empty( $template['path'] ) || ! is_string( $template['path'] ) ) {
        return false;
    }

    $path       = wp_normalize_path( $template['path'] );
    $plugin_dir = wp_normalize_path( WPSL_PLUGIN_DIR );

    return strpos( $path, $plugin_dir ) !== 0;
}

/**
 * Return all registered templates that are loaded from outside the plugin directory.
 *
 * @since  3.0.0
 * @return array $custom_templates The custom template data
 */
function wpsl_get_custom_templates() {
    return array_values( array_filter( wpsl_get_templates(), 'wpsl_is_custom_template' ) );
}

/**
 * Check if the globally active template is a custom one.
 *
 * @since  3.0.0
 * @return bool True if the active template is loaded from outside the plugin directory
 */
function wpsl_custom_template_is_active() {
    return wpsl_is_custom_template( wpsl_get_active_template() );
}

/**
 * Detect whether a custom template was written for WP Store Locator 2.x.
 *
 * We scan the file for markers that only appear in 2.x templates:
 * - `global $wpsl(_settings)`
 * - `$this->` calls
 * - the `$wpsl` object
 * 
 * @since  3.0.0
 * @param  string $path Absolute path to the template file.
 * @return bool   True if the template shows v2 markers.
 */
function wpsl_template_uses_legacy_markers( $path ) {
    static $cache = [];

    if ( empty( $path ) || ! is_string( $path ) ) {
        return false;
    }

    if ( isset( $cache[ $path ] ) ) {
        return $cache[ $path ];
    }

    if ( ! is_readable( $path ) ) {
        return $cache[ $path ] = false;
    }

    $code = file_get_contents( $path );

    if ( false === $code ) {
        return $cache[ $path ] = false;
    }

    // None of these occur in a 3.0 template.
    $markers = [
        '/\bglobal\b[^;]*\$wpsl(_settings)?\b/', // global $wpsl, $wpsl_settings;
        '/\$this\s*->/',                         // v2 templates run bound to a class
        '/\$wpsl\s*->/',                         // legacy $wpsl object
    ];

    $is_legacy = false;

    foreach ( $markers as $pattern ) {
        if ( preg_match( $pattern, $code ) ) {
            $is_legacy = true;
            break;
        }
    }

    return $cache[ $path ] = $is_legacy;
}

/**
 * Return the days of the week.
 *
 * @since  2.0.0
 * @return array $weekdays The days of the week
 */
function wpsl_get_weekdays() {
    /**
     * If we don't do this here, we get
     * 'Uncaught Error: Call to a member function get_weekday() on null' error.
     */
    require_once ABSPATH . WPINC . '/class-wp-locale.php';

    $wp_locale = new WP_Locale();

    $weekdays = [
        'monday'    => __( 'Monday', 'wp-store-locator' ),
        'tuesday'   => __( 'Tuesday', 'wp-store-locator' ),
        'wednesday' => __( 'Wednesday', 'wp-store-locator' ),
        'thursday'  => __( 'Thursday', 'wp-store-locator' ),
        'friday'    => __( 'Friday', 'wp-store-locator' ),
        'saturday'  => __( 'Saturday', 'wp-store-locator' ),
        'sunday'    => __( 'Sunday' , 'wp-store-locator' )
    ];

    // Check if we need to reorder the days based on the set start of the week on the WP Settings.
    $first_day = strtolower( $wp_locale->get_weekday( get_option( 'start_of_week' ) ) );

    if ( $first_day !== 'monday' ) {
        $split      = array_search( $first_day, array_keys( $weekdays ) ) ;
        $end_days   = array_slice( $weekdays, 0, $split );
        $start_days = array_slice( $weekdays, $split );
        $weekdays   = array_merge( $start_days, $end_days );
    }

    return $weekdays;
}

/**
 * Get the default opening hours.
 *
 * @since  2.0.0
 * @return array $opening_hours The default opening hours
 */
function wpsl_default_opening_hours() {
   $current_version = get_option( 'wpsl_version' );

   $opening_hours = [
       'dropdown' => [
           'monday'    => [ '9:00 AM,5:00 PM' ],
           'tuesday'   => [ '9:00 AM,5:00 PM' ],
           'wednesday' => [ '9:00 AM,5:00 PM' ],
           'thursday'  => [ '9:00 AM,5:00 PM' ],
           'friday'    => [ '9:00 AM,5:00 PM' ],
           'saturday'  => '',
           'sunday'    => ''
        ]
    ];

   /* Only add the textarea defaults for users that upgraded from 1.x */
   if ( version_compare( $current_version, '2.0', '<' ) ) {
       /* translators: 1: Monday hours, 2: Tuesday hours, 3: Wednesday hours, 4: Thursday hours, 5: Friday hours, 6: Saturday and Sunday hours */
       $opening_hours['textarea'] = sprintf( __( 'Mon %1$sTue %2$sWed %3$sThu %4$sFri %5$sSat Closed %6$sSun Closed', 'wp-store-locator' ), '9:00 AM - 5:00 PM' . "\n", '9:00 AM - 5:00 PM' . "\n", '9:00 AM - 5:00 PM' . "\n", '9:00 AM - 5:00 PM' . "\n", '9:00 AM - 5:00 PM' . "\n", "\n" ); 
   }

   return $opening_hours;
}

/**
 * Get the address formats.
 *
 * @since  2.0.0
 * @return array $address_formats The address formats
 */
function wpsl_get_address_formats() {
    $address_formats = [
        'city_state_zip'       => __( '(city) (state) (zip code)', 'wp-store-locator' ),
        'city_comma_state_zip' => __( '(city), (state) (zip code)', 'wp-store-locator' ),
        'city_zip'             => __( '(city) (zip code)', 'wp-store-locator' ),
        'city_comma_zip'       => __( '(city), (zip code)', 'wp-store-locator' ),
        'zip_city_state'       => __( '(zip code) (city) (state)', 'wp-store-locator' ),
        'zip_city'             => __( '(zip code) (city)', 'wp-store-locator' )
    ];

    return apply_filters( 'wpsl_address_formats', $address_formats );
}

/**
 * Enqueue the WP Store Locator front-end stylesheets.
 *
 * Since 3.0 the styles only load on requests that render locator markup,
 * detected from the queried post's content. A theme, page builder or widget
 * that renders [wpsl] from somewhere else should call this on
 * 'wp_enqueue_scripts' so the styles print in the head instead of the footer.
 *
 * @since  3.0.0
 * @return void
 */
function wpsl_enqueue_frontend_assets() {
    wpsl_get_service( 'assets_manager' )->ensure_styles();
}