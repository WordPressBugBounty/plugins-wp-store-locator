<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
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

    foreach ( $wpsl_blog_ids as $wpsl_blog_id ) {
        switch_to_blog( $wpsl_blog_id );
        wpsl_uninstall();
        restore_current_blog();
    }
}

// Delete the table ( users who upgraded from 1.x only ), options, store locations and taxonomies from the db.
function wpsl_uninstall() {

    global $wpdb;

    // If the 1.x table still exists we remove it.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping table during uninstall
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpsl_stores' );

    // Remove the 3.x Nominatim geocode cache table.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping table during uninstall
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpsl_nominatim_cache' );

    // Remove the 3.x table that holds the template section customizations, see wpsl_create_theme_table().
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping table during uninstall
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpsl_themes' );

    // Delete the options used by the plugin.
    $options = array(
        'wpsl_search',
        'wpsl_map',
        'wpsl_ux',
        'wpsl_markers',
        'wpsl_editor',
        'wpsl_local_pages',
        'wpsl_local_seo', // Pre-3.0-beta name for wpsl_local_pages.
        'wpsl_gdpr',
        'wpsl_labels',
        'wpsl_api',
        'wpsl_appearance',
        'wpsl_tools',
        'wpsl_version',
        'wpsl_valid_gmaps_browser_key',
        'wpsl_valid_gmaps_server_key',
        'wpsl_migrated_server_key_error',
        'wpsl_valid_mapbox_key',
        'wpsl_valid_openrouteservice_key',
        'wpsl_valid_stadia_key',
        'wpsl_key_validation_in_progess',
        'wpsl_settings',
        'wpsl_notices',
        'wpsl_alerts',
        'wpsl_addon_notice_dismissed',
        'wpsl_plugins_checked',
        'wpsl_legacy_support',
        'wpsl_flush_rewrite',
        'wpsl_delete_transient',
        'wpsl_cache_version',
        'wpsl_convert_cpt',
        'wpsl_onboarding_finished',
        'wpsl_onboarding_key_fallback',
        'wpsl_updated_from',
        'wpsl_v3_migration_complete',
        'wpsl_license_data_migrated',
        'wpsl_whats_new_redirect',
        'wpsl_valid_server_key',
        'wpsl_store_category_children',
        'wpsl_option_autoload_migrated',
        'wpsl_settings_autoload_fixed',
        'wpsl_gmaps_country_restrictions_migrated',
        'wpsl_region_restriction_type_migrated',
        'wpsl_map_shapes',
        'wpsl_custom_markers',
        'wpsl_throttle_nominatim',
        'wpsl_home_map_service_chosen',
        'wpsl_home_setup_dismissed',
        'wpsl_home_placed_manually',
        'wpsl_simple_mode',
        'wpsl_fields-manager',
        'wpsl_stadia_reverse_access'
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    /**
     * Remove all wpsl transients ( autoload cache, autocomplete data,
     * rate limit counters, etc ). Some are created without an expiration
     * date and would otherwise remain in the options table forever.
     */
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No core function exists to bulk delete transients by prefix during uninstall.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_wpsl_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_wpsl_' ) . '%'
        )
    );

    // Delete the user meta for all users, not just the one running the uninstall.
    delete_metadata( 'user', 0, 'wpsl_disable_location_warning', '', true );
    delete_metadata( 'user', 0, 'wpsl_stores_per_page', '', true ); // Not used in 2.x, but was used in 1.x

    /**
     * Remove wpsl_signup_pointer from dismissed_wp_pointers user meta.
     * Core stores dismissed pointers in a comma-separated list.
     */
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Searching user meta for dismissed pointer during uninstall.
    $users_with_pointer = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dismissed_wp_pointers' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like( 'wpsl_signup_pointer' ) . '%'
        )
    );

    if ( ! empty( $users_with_pointer ) ) {
        foreach ( $users_with_pointer as $user ) {
            $pointers = explode( ',', (string) $user->meta_value );
            $pointers = array_diff( $pointers, array( 'wpsl_signup_pointer' ) );

            if ( empty( $pointers ) ) {
                delete_user_meta( $user->user_id, 'dismissed_wp_pointers' );
            } else {
                update_user_meta( $user->user_id, 'dismissed_wp_pointers', implode( ',', $pointers ) );
            }
        }
    }

    /**
     * Remove the meta tag that marks Media Library attachments as reusable
     * marker logos ( see Custom_Markers::LOGO_META_KEY ). The attachments
     * themselves stay, they belong to the user's media library.
     */
    delete_metadata( 'post', 0, '_wpsl_marker_logo', '', true );

    // Disable the time limit before we start removing all the store location posts.
    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Necessary during uninstall to prevent timeout with large datasets
    @set_time_limit( 0 );

    // 'any' ignores trashed or auto-draft store location posts, so we make sure they are removed as well.
    $post_statuses = array( 'any', 'trash', 'auto-draft' );

    // Delete the 'wpsl_stores' custom post types in batches to prevent memory issues.
    foreach ( $post_statuses as $post_status ) {
        $batch_size = 1000; // Process 1000 posts at a time

        do {
            /**
             * Always query with offset 0. The posts are permanently deleted
             * below, so the next query starts from the beginning of the
             * remaining set. Incrementing the offset would skip a batch of
             * posts on every iteration and leave them behind.
             */
            $posts = get_posts( array(
                'post_type' => 'wpsl_stores',
                'post_status' => $post_status,
                'posts_per_page' => $batch_size,
                'fields' => 'ids'
            ) );

            if ( $posts ) {
                foreach ( $posts as $post ) {
                    wp_delete_post( $post, true );
                }

                // Clear any object caches to free up memory
                wp_cache_flush();
            }

        } while ( count( $posts ) === $batch_size ); // Continue while we're getting full batches
    }

    // Register the taxonomy so get_terms() and wp_delete_term() work during uninstall.
    register_taxonomy( 'wpsl_store_category', 'wpsl_stores' );

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

    // In case any untracked taxonomy rows remain, remove them from term_taxonomy.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct cleanup of custom taxonomy during uninstall.
    $wpdb->delete( $wpdb->term_taxonomy, array( 'taxonomy' => 'wpsl_store_category' ) );

    // Remove the WPSL caps and roles.
    $roles_file = plugin_dir_path( __FILE__ ) . 'includes/admin/utils/roles.php';
    if ( file_exists( $roles_file ) ) {
        include_once( $roles_file );
        
        if ( function_exists( 'wpsl_remove_caps_and_roles' ) ) {
            wpsl_remove_caps_and_roles();
        }
    }

    // If the Borlabs Cookie plugin is used, then remove the 'wpstorelocator' content type.
    if ( function_exists( 'BorlabsCookieHelper' ) ) {
        BorlabsCookieHelper()->deleteBlockedContentType( 'wpstorelocator' );
    }
}