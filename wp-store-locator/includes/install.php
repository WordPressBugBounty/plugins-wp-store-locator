<?php
/**
 * WPSL Install
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wpsl_install( $network_wide ) {
    if ( is_multisite() && $network_wide ) {
        $blog_ids = get_sites( [
            'fields'  => 'ids',
            'number'  => 0, // 0 retrieves ALL sites, bypassing the default limit
            'spam'    => 0,
            'deleted' => 0,
        ] );

        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            wpsl_install_data( true );

            // restore_current_blog() only pops one level off the switch stack, so it has to run once per switch_to_blog().
            restore_current_blog();
        }
    } else {
        wpsl_install_data( false );
    }

    if ( function_exists( 'BorlabsCookieHelper' ) ) {
        require_once( WPSL_PLUGIN_DIR . 'includes/core/integrations/class-borlabs-cookie.php' );

        $borlabs = new \WPSL\Core\Integrations\Borlabs_Cookie();
        $borlabs->enable();
    }
}

/**
 * Install the required data.
 *
 * @since 1.2.20
 * @param bool $network_wide Whether this runs for every site in a network ( activation loop or new subsite ).
 * @return void
 */
function wpsl_install_data( $network_wide = false ) {
    require_once( WPSL_PLUGIN_DIR . 'includes/core/map/class-nominatim-geocode-cache.php' );
    require_once( WPSL_PLUGIN_DIR . 'includes/admin/utils/helpers.php' );

    // Not loaded by the main plugin file outside wp-admin / WP-CLI, but needed when a subsite is created through e.g. the REST API.
    require_once( WPSL_PLUGIN_DIR . 'includes/admin/utils/roles.php' );

    $previous_version = get_option( 'wpsl_version' );

    $settings_handler = new \WPSL\Core\Settings\Manager();

    $post_types = new \WPSL\Core\Post_Types\Register( $settings_handler );
    $post_types->register_post_types();
    $post_types->register_taxonomies();

    wpsl_install_flush_rewrite( $network_wide );

    // Create the default settings using the service container
    $settings = wpsl_get_service( 'wpsl_settings' );
    $settings->set_defaults();

    // Set the correct version, but only for a genuinely new install ( see $previous_version ).
    if ( false === $previous_version ) {
        update_option( 'wpsl_version', WPSL_VERSION_NUM, true );
    }

    // Add user roles.
    wpsl_add_roles();

    // Add user capabilities.
    wpsl_add_caps();

    // Create the Nominatim ( OpenStreetMaps ) Geocode cache table.
    $nominatim_cache = new \WPSL\Core\Map\Nominatim_Geocode_Cache();
    $nominatim_cache->create_table();

    // Create the theme style table.
    wpsl_create_theme_table();

    wpsl_maybe_set_onboarding_redirect( $network_wide, $previous_version );
}

/**
 * Make sure the rewrite rules include the store post type and taxonomy.
 *
 * Regenerating the rules with flush_rewrite_rules() while switched to another
 * site is unreliable and slow on large networks. Deleting the option instead
 * makes every site rebuild its own rules on its next request.
 *
 * @since  3.0.0
 * @param  bool $network_wide Whether this runs for every site in a network.
 * @return void
 */
function wpsl_install_flush_rewrite( $network_wide ) {
    if ( $network_wide ) {
        delete_option( 'rewrite_rules' );
    } else {
        flush_rewrite_rules();
    }
}

/**
 * Show the onboarding page after activating the plugin for the first time.
 *
 * Skipped for network wide installs, otherwise the admin of every subsite
 * would be redirected to the onboarding page on their next dashboard visit.
 *
 * @since  3.0.0
 * @param  bool        $network_wide     Whether this runs for every site in a network.
 * @param  string|bool $previous_version The wpsl_version found before this activation, false when new.
 * @return void
 */
function wpsl_maybe_set_onboarding_redirect( $network_wide, $previous_version = false ) {
    if ( $network_wide || false !== $previous_version ) {
        return;
    }

    if ( ! get_option( 'wpsl_onboarding_finished', false ) ) {
        set_transient( 'wpsl_onboarding_redirect', true, 5 * MINUTE_IN_SECONDS );
    }
}

/**
 * Install the plugin data on a newly created subsite.
 *
 * @since  3.0.0
 * @param  WP_Site $new_site The new site object.
 * @return void
 */
function wpsl_install_new_site( $new_site ) {

    if ( ! wpsl_is_network_activated() ) {
        return;
    }

    switch_to_blog( (int) $new_site->blog_id );
    wpsl_install_data( true );
    restore_current_blog();
}

/**
 * Whether the plugin is activated network wide.
 *
 * @since  3.0.0
 * @return bool
 */
function wpsl_is_network_activated() {
    if ( ! is_multisite() ) {
        return false;
    }

    $plugins = get_site_option( 'active_sitewide_plugins', [] );

    return isset( $plugins[ WPSL_BASENAME ] );
}

/**
 * Include the plugin tables when a subsite is deleted.
 *
 * Core only drops its own tables, custom tables have to be added through
 * the 'wpmu_drop_tables' filter or they are orphaned after site deletion.
 *
 * @since  3.0.0
 * @param  string[] $tables The table names to drop.
 * @param  WP_Site  $site   The site being deleted.
 * @return string[]
 */
function wpsl_drop_site_tables( $tables, $site ) {
    global $wpdb;

    $prefix = $wpdb->get_blog_prefix( $site->blog_id );

    $tables[] = $prefix . 'wpsl_stores'; // 1.x table, may still exist on upgraded sites.
    $tables[] = $prefix . 'wpsl_nominatim_cache';
    $tables[] = $prefix . 'wpsl_themes';

    return $tables;
}