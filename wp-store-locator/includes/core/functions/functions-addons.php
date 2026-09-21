<?php
/**
 * Official add-on compatibility and version checks.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Find active add-ons too old for this version.
 *
 * The 3.x rewrite replaced the 2.x global object graph ($wpsl, $wpsl_admin)
 * with a service container, so add-ons below 2.0 call functions/globals that
 * no longer exist and fatal when their code runs. Activation is refused while
 * any are active. Matched by plugin-header name prefix "WP Store Locator - ",
 * with the version read from the header so the result doesn't depend on the
 * add-on being loaded.
 *
 * @since  3.0.0
 * @param  string $min_version Minimum required add-on version.
 * @return array  List of [ 'name' => string, 'version' => string ] entries.
 */
function wpsl_get_incompatible_addons( $min_version = '2.0' ) {
    
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $incompatible = [];

    foreach ( get_plugins() as $plugin_file => $plugin_data ) {

        // Only official WP Store Locator add-ons, never the core plugin itself.
        if ( strpos( $plugin_data['Name'], 'WP Store Locator - ' ) !== 0 ) {
            continue;
        }

        if ( ! is_plugin_active( $plugin_file ) ) {
            continue;
        }

        // A missing version header counts as incompatible ( unknown = block ).
        if ( version_compare( $plugin_data['Version'], $min_version, '<' ) ) {
            $incompatible[] = [
                'file'    => $plugin_file,
                'name'    => $plugin_data['Name'],
                'version' => '' !== $plugin_data['Version'] ? $plugin_data['Version'] : __( 'unknown', 'wp-store-locator' ),
            ];
        }
    }

    return $incompatible;
}

/**
 * The version of each official add-on with full support for this release.
 *
 * Keyed by plugin folder. An older add-on is not broken and is not held back:
 * it runs and keeps all its own features, it just cannot use the parts of this
 * release it was never built against. That is the whole difference from
 * wpsl_get_incompatible_addons(), whose floor sits far lower and marks where an
 * add-on stops working at all.
 *
 * @since  3.0.0
 * @return array Plugin folder => version with full support.
 */
function wpsl_get_addon_versions() {
    return [
        'wp-store-locator-statistics' => '2.0.1',
        'wp-store-locator-csv'        => '2.1.0',
        'wp-store-locator-widget'     => '2.1.0',
    ];
}

/**
 * Find active add-ons that work but are behind wpsl_get_addon_versions().
 *
 * Informational only, kept apart from wpsl_get_incompatible_addons():
 * nothing here gets neutralized. Add-ons below the compatibility floor
 * are skipped since that function already flags them with a stronger
 * message.
 *
 * Reported through the admin's Alerts, which caches the result - reading the
 * header of every installed plugin is too much work to repeat per page view.
 *
 * @since  3.0.0
 * @param  string $min_version The compatibility floor, below which an add-on
 *                             is Legacy_Addons' problem rather than this one.
 * @return array  List of [ 'file', 'name', 'version', 'latest' ] entries.
 */
function wpsl_get_outdated_addons( $min_version = '2.0' ) {

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $latest   = wpsl_get_addon_versions();
    $outdated = [];

    foreach ( get_plugins() as $plugin_file => $plugin_data ) {

        $folder = dirname( $plugin_file );

        if ( ! isset( $latest[ $folder ] ) ) {
            continue;
        }

        if ( ! is_plugin_active( $plugin_file ) ) {
            continue;
        }

        // Below the floor ( and a missing header reads as below it ) the
        // add-on is neutralized and already carries its own warning row.
        if ( version_compare( $plugin_data['Version'], $min_version, '<' ) ) {
            continue;
        }

        if ( version_compare( $plugin_data['Version'], $latest[ $folder ], '<' ) ) {
            $outdated[] = [
                'file'    => $plugin_file,
                'name'    => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'latest'  => $latest[ $folder ],
            ];
        }
    }

    return $outdated;
}