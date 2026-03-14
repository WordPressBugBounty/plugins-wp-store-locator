<?php
if ( !defined( 'ABSPATH' ) || !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we need to run the uninstall for a single or mu installation.
if ( ! is_multisite() ) {
    wpsl_uninstall();
} else {
    
    $wpsl_blog_ids = get_sites( array(
        'fields'   => 'ids',
        'number'   => 0, // 0 retrieves ALL sites, bypassing the default limit
        'spam'     => 0, // Exclude spam sites
        'deleted'  => 0, // Exclude deleted sites
    ) );

    $wpsl_original_blog_id = get_current_blog_id();
    
    foreach ( $wpsl_blog_ids as $wpsl_blog_id ) {
        switch_to_blog( $wpsl_blog_id );
        wpsl_uninstall();  
    }
    
    switch_to_blog( $wpsl_original_blog_id );
}

// Delete the table ( users who upgraded from 1.x only ), options, store locations and taxonomies from the db.
function wpsl_uninstall() {

    global $wpdb, $current_user;

    // If the 1.x table still exists we remove it.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping table during uninstall
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpsl_stores' );

    // Check if we need to delete the autoload transients.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for wildcard search on options
    $option_names = $wpdb->get_results( "SELECT option_name AS transient_name FROM " . $wpdb->options . " WHERE option_name LIKE ('\_transient\_wpsl\_autoload\_%')" );

    if ( $option_names ) {
        foreach ( $option_names as $option_name ) {
            $transient_name = str_replace( "_transient_", "", $option_name->transient_name );

            delete_transient( $transient_name );
        }
    }

    // Delete the options used by the plugin.
    $options = array( 'wpsl_version', 'wpsl_settings', 'wpsl_notices', 'wpsl_legacy_support', 'wpsl_flush_rewrite', 'wpsl_delete_transient', 'wpsl_convert_cpt', 'wpsl_valid_server_key' );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    delete_user_meta( $current_user->ID, 'wpsl_disable_location_warning' );
    delete_user_meta( $current_user->ID, 'wpsl_disable_v3_beta_warning' );
    delete_user_meta( $current_user->ID, 'wpsl_stores_per_page' ); // Not used in 2.x, but was used in 1.x

    // Disable the time limit before we start removing all the store location posts.
    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Necessary to prevent timeouts when removing large numbers of store posts during uninstall.
    @set_time_limit( 0 );

    // 'any' ignores trashed or auto-draft store location posts, so we make sure they are removed as well.
    $post_statuses = array( 'any', 'trash', 'auto-draft' );

    // Delete the 'wpsl_stores' custom post types.
    foreach ( $post_statuses as $post_status ) {
        $posts = get_posts( array( 'post_type' => 'wpsl_stores', 'post_status' => $post_status, 'posts_per_page' => -1, 'fields' => 'ids' ) );

        if ( $posts ) {
            foreach ( $posts as $post ) {
                wp_delete_post( $post, true );
            }
        }
    }

    // Delete all terms associated with the 'wpsl_store_category' taxonomy.
    $terms = get_terms( array(
        'taxonomy'   => 'wpsl_store_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );

    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $term_id ) {
            wp_delete_term( $term_id, 'wpsl_store_category' );
        }
    }

    // Remove the WPSL caps and roles.
    include_once( 'admin/roles.php' );

    wpsl_remove_caps_and_roles();

    // If the Borlabs Cookie plugin is used, then remove the 'wpstorelocator' content type.
    if ( function_exists( 'BorlabsCookieHelper' ) ) {
        BorlabsCookieHelper()->deleteBlockedContentType( 'wpstorelocator' );
    }
}