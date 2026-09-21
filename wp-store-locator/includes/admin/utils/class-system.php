<?php
/**
 * Handle system related tasks.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class System {

    /**
     * Constructor
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action( 'delete_post',      [ $this, 'maybe_delete_autoload_transient' ] );
        add_action( 'wp_trash_post',    [ $this, 'maybe_delete_autoload_transient' ] );
        add_action( 'untrash_post',     [ $this, 'maybe_delete_autoload_transient' ] );
        add_action( 'save_post',        [ $this, 'maybe_delete_autoload_transient' ], 20 );
        add_action( 'updated_post_meta', [ $this, 'maybe_flush_on_meta_change' ], 10, 4 );
        add_action( 'added_post_meta',   [ $this, 'maybe_flush_on_meta_change' ], 10, 4 );

        add_filter( 'posts_search',     [ $this, 'extend_admin_post_search' ] );
        add_filter( 'admin_body_class', [ $this, 'modify_admin_body_classes' ] );
    }

    /**
     * Extend the admin search with support
     * for different WPSL meta fields.
     *
     * @since  3.0.0
     * @param  string $search The SQL that is used in the WHERE clause of WP_Query
     * @return string $search The adjusted WHERE clause include wpsl_ meta fields
     */
    public function extend_admin_post_search( $search ) {
        global $pagenow, $wpdb, $wp;

        $post_ids = [];

        // Only continue if we are on the correct page
        if ( 'edit.php' != $pagenow || ! is_search() || ! isset( $wp->query_vars['s'] ) || 'wpsl_stores' != $wp->query_vars['post_type'] ) {
            return $search ;
        }

        // The meta key of the custom field we are looking for
        $meta_keys  = apply_filters( 'wpsl_post_search_fields', [ 'wpsl_address', 'wpsl_city', 'wpsl_state', 'wpsl_zip', 'wpsl_country' ] );
        $search_ids = [];

        // Look for matches in the wpsl meta fields
        foreach ( $meta_keys as $meta_key ) {
            if ( ! empty( $post_ids ) ) {
                break;
            }

            $like     = '%' . $wpdb->esc_like( $wp->query_vars['s'] ) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom meta search query, caching not applicable for dynamic search results
            $post_ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id WHERE meta_key=%s AND meta_value LIKE %s", $meta_key, $like ) );
        }

        /**
         * If searching the meta fields returned no results,
         * then see if the input matches with an author name.
         */
        if ( empty( $post_ids ) ) {
            $user = get_user_by( 'login', sanitize_text_field( $wp->query_vars['s'] ) );

            if ( $user ) {
                $user_id  = $user->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Author search query, caching not applicable for dynamic search results
                $post_ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_author = %s AND post_status = 'publish' AND post_type = 'wpsl_stores'", $user_id ) );
            }
        }

        if ( $post_ids ) {
            foreach ( $post_ids as $ids ) {
                $search_ids[] = $ids->ID;
            }

            $search = str_replace( 'AND (((', "AND ( ({$wpdb->posts}.ID IN (" . implode( ',', array_map( 'absint', $search_ids ) ) . ")) OR ((", $search );
        }

        return $search;
    }

    /**
     * Check if we need to delete the autoload transient.
     *
     * This is called when a post is saved, deleted, trashed or untrashed.
     *
     * @since 2.0.0
     * @param int $post_id The ID of the post being saved / deleted
     * @return void
     */
    public function maybe_delete_autoload_transient( $post_id ) {
        // Deleting all locations flushes once after its last batch instead.
        if ( \WPSL\Admin\Tools\Data_Management::$bulk_deleting ) {
            return;
        }

        if ( wpsl_get_service( 'wpsl_settings' )->get( 'map', 'autoload' ) && get_post_type( $post_id ) == 'wpsl_stores' ) {
            $this->delete_autoload_transient();
        }
    }

    /**
     * Flush the autoload transient when post meta that affects store
     * data is changed outside of the normal save_post flow.
     *
     * The featured image ( _thumbnail_id ) is set via a separate AJAX
     * call in the block editor, which does not fire save_post. Without
     * this hook the transient cache would serve stale data missing the
     * thumb field, causing a "thumb is not defined" template error.
     *
     * @since 3.0.0
     * @param int    $meta_id   ID of the metadata entry.
     * @param int    $post_id   Post ID.
     * @param string $meta_key  Meta key.
     * @param mixed  $meta_value Meta value.
     * @return void
     */
    public function maybe_flush_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( '_thumbnail_id' !== $meta_key ) {
            return;
        }

        if ( get_post_type( $post_id ) !== 'wpsl_stores' ) {
            return;
        }

        if ( ! wpsl_get_service( 'wpsl_settings' )->get( 'map', 'autoload' ) ) {
            return;
        }

        $this->delete_autoload_transient();
    }

    /**
     * Flush the autoload transient cache.
     *
     * Runs from post lifecycle hooks (save/delete/trash/untrash), so it calls
     * flush_autoload_transients() directly rather than api->flush_transient_cache(),
     * which guards on a nonce and manage_wpsl_settings capability that aren't
     * present during native trash/delete or a save by the Store Locator Manager
     * role. Access is already gated by maybe_delete_autoload_transient() on post
     * type and autoload setting.
     *
     * @since 2.0.0
     * @return void
     */
    private function delete_autoload_transient() {
        wpsl_get_service( 'system_utils' )->flush_autoload_transients();
    }

    /**
     * Add additional CSS classes based on the active tab section.
     *
     * @since  3.0.0
     * @param  string $classes current body classes for settings page
     * @return string $classes updated body classes
     */
    public function modify_admin_body_classes( $classes ) {
        global $wp_version;

        // Add wpsl-v3 class to all WPSL admin pages (including add-ons)
        if ( isset( $_GET['post_type'] ) && $_GET['post_type'] === 'wpsl_stores' ) {
            $classes .= ' wpsl-v3';
        } else if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wpsl_' ) === 0 ) {
            $classes .= ' wpsl-v3';
        }

        if ( isset( $_GET['page'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['page'] ) ), [ 'wpsl_settings', 'wpsl_tools' ], true ) ) {
            if ( isset( $_GET['tab'] ) ) {
                $tab = sanitize_key( $_GET['tab'] );
                
                switch ( $tab ) {
                    case 'settings':
                        $classes .= ' wpsl-settings';
                        break;
                    case 'licenses':
                        $classes .= ' wpsl-licenses';
                        break;
                    case 'system-status':
                        $classes .= ' wpsl-system-status';
                        break;
                    case 'import-export-settings':
                        $classes .= ' wpsl-import-export';
                }
            } else if ( sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl_settings' ) {
                $classes .= ' wpsl-settings';
            }
        }

        // Add wpsl-appearance class when on the appearance page
        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wpsl_appearance' ) {
            $classes .= ' wpsl-appearance';
        }

        // The Home page's boxes are the Settings page's boxes.
        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wpsl_home' ) {
            $classes .= ' wpsl-home';
        }

        /*
         * The Marker Studio fills the window like the Map Shapes editor, so it
         * needs the same wpsl-full-page class (Map_Shapes\Manager::body_class()
         * adds it for itself): the header's .wpsl-nav-wrap is capped at 52rem,
         * and only a body class can lift that cap outside the Studio's container.
         */
        if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'wpsl_marker_studio' ) {
            $classes .= ' wpsl-full-page';
        }

        // Add wpsl-editor class when editing a store location
        global $pagenow;
        if ( in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
            $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';

            if ( ! $post_type && isset( $_GET['post'] ) ) {
                $post_type = get_post_type( absint( $_GET['post'] ) );
            }

            if ( $post_type === 'wpsl_stores' ) {
                $classes .= ' wpsl-editor';
            }
        }

        // Add wpsl-wp-7-plus class for WP 7.0+
        if ( version_compare( $wp_version, '7.0', '>=' ) ) {
            $classes .= ' wpsl-wp-7-plus';
        }

        return $classes;
    }
}