<?php
/**
 * Handle data management operations.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Markers\Custom_Markers;
use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Shapes\Repository as Shapes_Repository;

class Data_Management {

    /**
     * True while a batch of locations is being deleted.
     *
     * The batch fires delete_post for every location it removes. The
     * plugin's own listener on that hook flushes the whole store cache per
     * post; it checks this and leaves the flush to the end of the run.
     *
     * @since 3.0.0
     * @var   bool
     */
    public static $bulk_deleting = false;

    /**
     * The settings instance.
     *
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Constructor.
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager $settings The settings handler instance.
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;

        add_action( 'wp_ajax_wpsl_data_management', [ $this, 'handle_data_management' ] );
        add_action( 'wp_ajax_wpsl_get_data_counts', [ $this, 'get_data_counts' ] );
    }

    /**
     * Handle the AJAX call from the data management dialog.
     *
     * @since  3.0.0
     * @return void
     */
    public function handle_data_management() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsl-data-management' ) ) {
            return wp_send_json_error( __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return wp_send_json_error( __( 'You do not have permission to perform this action.', 'wp-store-locator' ) );
        }

        $actions = isset( $_POST['reset'] ) ? array_map( 'sanitize_key', (array) $_POST['reset'] ) : [];
        $results = [];

        // Process each selected action
        foreach ( $actions as $action ) {
            $action = sanitize_key( $action );
            
            switch ( $action ) {
                case 'settings':
                    $results['reset_settings'] = $this->reset_settings();
                    break;
                case 'locations':
                    $results['delete_locations'] = $this->delete_locations();
                    break;
                case 'categories':
                    $results['delete_categories'] = $this->delete_categories();
                    break;
                case 'custom_markers':
                    $results['custom_markers'] = $this->delete_custom_markers();
                    break;
                case 'map_shapes':
                    $results['map_shapes'] = $this->delete_map_shapes();
                    break;
                default:
                    $results[$action] = [
                        'success' => false,
                        /* translators: %s: action name */
                        'message' => sprintf( __( 'Unknown action: %s', 'wp-store-locator' ), $action )
                    ];
                    break;
            }
        }

        wp_send_json( $results );
    }

    /**
     * Reset settings to defaults.
     *
     * @since  3.0.0
     * @return array
     */
    private function reset_settings() {
        $option_names = [

            // The settings groups, see WPSL\Core\Settings\Manager::$groups.
            'wpsl_api',
            'wpsl_search',
            'wpsl_map',
            'wpsl_ux',
            'wpsl_markers',
            'wpsl_editor',
            'wpsl_appearance',
            'wpsl_local_pages',
            'wpsl_local_seo', // Pre-3.0-beta name for wpsl_local_pages.
            'wpsl_labels',
            'wpsl_gdpr',
            'wpsl_tools',

            // API key validation verdicts, see WPSL\Admin\Settings\Validate_Keys.
            'wpsl_valid_gmaps_browser_key',
            'wpsl_valid_gmaps_server_key',
            'wpsl_valid_mapbox_key',
            'wpsl_valid_openrouteservice_key',
            'wpsl_valid_stadia_key',
            'wpsl_migrated_server_key_error'
        ];

        foreach ( $option_names as $option_name ) {
            delete_option( $option_name );
        }
        
        // Restore default settings after deletion
        $this->settings->set_defaults();
        
        // Clear the settings cache to ensure fresh data is loaded
        $this->settings->clear_cache();

        /*
         * Cached results were built from the settings that just reset, so
         * serving them would contradict the reset.
         */
        wpsl_flush_store_cache();

        return [
            'success' => true,
            'message' => ''
        ];
    }

    /**
     * The number of locations a post_status => any query finds.
     *
     * The deletion loop pages through get_posts( post_status => any ), which
     * covers every status registered with exclude_from_search false:
     * scheduled ones and custom statuses included. Counting a fixed list of
     * four left scheduled locations out, so a site holding only those was
     * told there was nothing to delete.
     *
     * @since  3.0.0
     * @return int
     */
    private function count_locations() {
        $counts = wp_count_posts( 'wpsl_stores' );
        $total  = 0;

        foreach ( get_post_stati( [ 'exclude_from_search' => false ] ) as $status ) {
            $total += isset( $counts->$status ) ? (int) $counts->$status : 0;
        }

        return $total;
    }

    /**
     * Delete all store locations with progress tracking.
     *
     * @since  3.0.0
     * @return array
     */
    private function delete_locations() {
        $batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : apply_filters( 'wpsl_location_batch_size', 500 );
        // Never allow a zero batch: get_posts( posts_per_page => 0 ) doesn't mean
        // "no rows" and would break the completion tracking below.
        $batch_size = max( 1, $batch_size );
        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        
        $result = [
            'success'  => false,
            'message'  => '',
            'progress' => []
        ];

        /**
         * For progressive deletion, we need to maintain the original total count
         * Store it in a transient on the first request (offset = 0)
         */
        if ( $offset === 0 ) {
            $total_count = $this->count_locations();

            set_transient( 'wpsl_deletion_original_total', $total_count, HOUR_IN_SECONDS );
        } else {
            $total_count = get_transient( 'wpsl_deletion_original_total' );

            // Fallback if transient is lost - get current count
            if ( false === $total_count ) {
                $total_count = $this->count_locations();
            }
        }
        
        if ( $total_count === 0 ) {
            $result['success'] = true;
            $result['message'] = __( 'No stores to delete.', 'wp-store-locator' );
            $result['progress'] = [
                'completed'  => true,
                'total'      => 0,
                'processed'  => 0,
                'percentage' => 100
            ];

            return $result;
        }

        // Always use offset 0 since we're deleting posts from the beginning
        $store_posts = get_posts( [
            'post_type'      => 'wpsl_stores',
            'posts_per_page' => $batch_size,
            'offset'         => 0,
            'post_status'    => 'any',
            'orderby'        => 'ID',
            'order'          => 'ASC'
        ] );

        $deleted_count  = 0;
        $failed_count   = 0;
        $database_error = '';

        // Defer term count updates for better performance
        wp_defer_term_counting( true );

        // Collect post IDs for bulk operations
        $post_ids = wp_list_pluck( $store_posts, 'ID' );

        $term_taxonomy_ids = [];

        if ( ! empty( $post_ids ) ) {
            global $wpdb;

            // Use direct database queries for better performance
            $id_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

            /**
             * Collect term taxonomy ids before the term relationships are
             * deleted. The raw SQL below bypasses the WP API, so term counts
             * aren't updated automatically and must be recounted afterwards.
             */
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders are generated above, SQL is prepared
            $term_taxonomy_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT tt.term_taxonomy_id
                       FROM {$wpdb->term_relationships} AS tr
                 INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                      WHERE tt.taxonomy = 'wpsl_store_category'
                        AND tr.object_id IN ({$id_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder list is built from %d above
                    $post_ids
                )
            );

            /*
             * The raw SQL below skips wp_delete_post(), and with it every hook
             * that function fires. Fire them here, in the same places around
             * the delete, so add-ons and the plugin's own listeners ( e.g. the
             * coordinate problem count ) hear about each deleted location.
             * The per-post store cache flush on delete_post is held back
             * meanwhile: the last batch flushes once ( see below ).
             */
            self::$bulk_deleting = true;

            foreach ( $store_posts as $store_post ) {
                do_action( 'before_delete_post', $store_post->ID, $store_post );
                do_action( 'delete_post', $store_post->ID, $store_post );
            }

            /*
             * One transaction, so a failure part way can't leave posts without
             * their metadata or metadata without its post. Where the tables
             * don't support transactions ( MyISAM ) the statements still stop
             * at the first failure and the failure is still reported.
             */
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control, no core API.
            $wpdb->query( 'START TRANSACTION' );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders are generated above, SQL is prepared
            $deleted = false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$id_placeholders})", $post_ids ) );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders are generated above, SQL is prepared
            $deleted = $deleted && false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$id_placeholders})", $post_ids ) );

            /*
             * Zero rows is a failure too: the same posts would come back on
             * the next batch, and the client would loop on them forever.
             */
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Placeholders are generated above, SQL is prepared
            $deleted = $deleted && $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$id_placeholders})", $post_ids ) );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control, no core API.
            $deleted = $deleted && false !== $wpdb->query( 'COMMIT' );

            if ( $deleted ) {
                $deleted_count = count( $post_ids );

                // Clean post cache for deleted posts
                foreach ( $post_ids as $post_id ) {
                    clean_post_cache( $post_id );
                }

                foreach ( $store_posts as $store_post ) {
                    do_action( 'deleted_post', $store_post->ID, $store_post );
                    do_action( 'after_delete_post', $store_post->ID, $store_post );
                }
            } else {
                $database_error = $wpdb->last_error;

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control, no core API.
                $wpdb->query( 'ROLLBACK' );

                $failed_count = count( $post_ids );
            }

            self::$bulk_deleting = false;
        }

        // Re-enable term counting
        wp_defer_term_counting( false );

        // Update the term counts for the categories the deleted locations were assigned to.
        if ( ! empty( $term_taxonomy_ids ) ) {
            wp_update_term_count_now( array_map( 'absint', $term_taxonomy_ids ), 'wpsl_store_category' );
        }

        // Calculate progress based on original total and current offset
        $processed = $offset + $deleted_count;
        $remaining = max( 0, $total_count - $processed );
        $percentage = $total_count > 0 ? min( 100, round( ( $processed / $total_count ) * 100, 1 ) ) : 100;

        /*
         * Done when the query itself comes back short, not when the count
         * says so: the count is taken once at the start and can be off ( a
         * location added meanwhile ). A failed batch ends the run as well,
         * the same locations would only fail again.
         */
        $completed = count( $store_posts ) < $batch_size || $failed_count > 0;

        // Clean up transient when completed
        if ( $completed ) {
            delete_transient( 'wpsl_deletion_original_total' );

            /*
             * The per-post flush on delete_post is held back while a batch
             * runs, so the store cache is flushed here on the last batch only,
             * after all stores are gone, or a front-end autoload between
             * batches would re-cache stores still left.
             */
            if ( $processed > 0 ) {
                wpsl_flush_store_cache();
            }
        }

        if ( $failed_count ) {
            $result['success'] = false;
            $result['message'] = sprintf(
                /* translators: %1$d: number of stores deleted before the failure, %2$s: the database error, may be empty */
                __( 'The locations could not be deleted, nothing in the last batch was removed. %1$d locations were deleted before that. %2$s', 'wp-store-locator' ),
                $offset,
                $database_error
            );
            $result['progress'] = [
                'completed'        => true,
                'total'            => $total_count,
                'processed'        => $processed,
                'deleted_in_batch' => 0,
                'failed_in_batch'  => $failed_count,
                'remaining'        => $remaining,
                'percentage'       => $percentage,
                'next_offset'      => 0,
            ];

            return $result;
        }

        $result['success'] = true;
        $result['progress'] = [
            'completed'        => $completed,
            'total'            => $total_count,
            'processed'        => $processed,
            'deleted_in_batch' => $deleted_count,
            'failed_in_batch'  => $failed_count,
            'remaining'        => $remaining,
            'percentage'       => $percentage,
            'next_offset'      => $completed ? 0 : $processed
        ];

        if ( $completed ) {
            $result['progress']['percentage'] = 100;
            $result['message'] = '';
        } else {
            /* translators: %1$d: number of processed stores, %2$d: total number of stores, %3$s: percentage */
            $result['message'] = sprintf( __( 'Processed %1$d of %2$d stores (%3$s%%)...', 'wp-store-locator' ), $processed, $total_count, $percentage );
        }

        return $result;
    }

    /**
     * Delete all store categories.
     *
     * @since  3.0.0
     * @return array
     */
    private function delete_categories() {
        $terms = get_terms( [
            'taxonomy'   => 'wpsl_store_category',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $terms ) ) {
            return [
                'success' => false,
                'message' => $terms->get_error_message()
            ];
        }

        foreach ( $terms as $term ) {
            wp_delete_term( $term->term_id, 'wpsl_store_category' );
        }

        return [
            'success' => true,
            'message' => ''
        ];
    }

    /**
     * Delete all custom markers created in the Marker Studio.
     *
     * @since  3.0.0
     * @return array
     */
    private function delete_custom_markers() {
        delete_option( Custom_Markers::OPTION_NAME );
        delete_metadata( 'post', 0, Custom_Markers::LOGO_META_KEY, '', true );

        $this->sweep_custom_marker_references();

        return [
            'success' => true,
            'message' => ''
        ];
    }

    /**
     * Let go of everything pointing at the markers being deleted.
     *
     * @since  3.0.0
     * @return void
     */
    private function sweep_custom_marker_references() {
        $custom_markers = wpsl_get_service( 'custom_markers' );
        $values         = array_keys( $custom_markers->get_reference_counts() );
        $markers        = get_option( 'wpsl_markers' );

        if ( is_array( $markers ) ) {
            foreach ( Custom_Markers::SETTING_SLOTS as $key ) {
                if ( isset( $markers[ $key ] ) && wpsl_custom_marker_id( $markers[ $key ] ) ) {
                    $values[] = $markers[ $key ];
                }
            }
        }

        $custom_markers->release_references( $values );
    }

    /**
     * Delete all map shapes.
     *
     * @since  3.0.0
     * @return array
     */
    private function delete_map_shapes() {
        delete_option( Shapes_Repository::OPTION_NAME );

        return [
            'success' => true,
            'message' => ''
        ];
    }

    /**
     * Get current counts for locations and categories.
     *
     * Used to refresh checkbox labels after deletion operations.
     *
     * @since  3.0.0
     * @return void
     */
    public function get_data_counts() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsl-data-management' ) ) {
            return wp_send_json_error( __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return wp_send_json_error( __( 'You do not have permission to perform this action.', 'wp-store-locator' ) );
        }

        $locations_data = wpsl_locations_exist();
        $categories_data = wpsl_categories_exist();

        wp_send_json_success( [
            'locations' => $locations_data,
            'categories' => $categories_data,
            'custom_markers' => wpsl_custom_markers_exist(),
            'map_shapes' => wpsl_map_shapes_exist()
        ] );
    }
}