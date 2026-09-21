<?php
/**
 * Store Locator custom post type.
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

namespace WPSL\Core\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Register {

    /**
     * Local Pages settings
     *
     * @var array
     */
    private $local_pages_settings;

    /**
     * Class constructor
     *
     * @since 2.0.0
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->local_pages_settings = $settings->get_group( 'local_pages' );

        add_action( 'init',                                     [ $this, 'register_post_types' ], 10, 1 );
        add_action( 'init',                                     [ $this, 'register_taxonomies' ], 10, 1 );
        add_action( 'manage_wpsl_stores_posts_custom_column',   [ $this, 'custom_columns' ], 10, 2 );

        // Clear categories cache when taxonomy terms are modified
        add_action( 'created_wpsl_store_category',              [ $this, 'clear_categories_cache' ] );
        add_action( 'edited_wpsl_store_category',               [ $this, 'clear_categories_cache' ] );
        add_action( 'delete_wpsl_store_category',               [ $this, 'clear_categories_cache' ] );
        add_action( 'set_object_terms',                         [ $this, 'clear_categories_cache_on_term_change' ], 10, 6 );

        add_filter( 'enter_title_here',                         [ $this, 'change_default_title' ] );
        add_filter( 'manage_edit-wpsl_stores_columns',          [ $this, 'edit_columns' ] );
        add_filter( 'manage_edit-wpsl_stores_sortable_columns', [ $this, 'sortable_columns' ] );
        add_filter( 'request',                                  [ $this, 'sort_columns' ] );
    }

    /**
     * Register the WPSL post type.
     * 
     * @since  2.0.0
     * @return void
     */
    public function register_post_types() {
        // Enable permalinks for the post type?
        if ( isset( $this->local_pages_settings['permalinks'] ) && $this->local_pages_settings['permalinks'] ) {
            $public              = true;
            $exclude_from_search = false;
            $rewrite             = [ 'slug' => $this->local_pages_settings['permalink_slug'] ];

            if ( $this->local_pages_settings['permalink_remove_front'] ) {
                $rewrite['with_front'] = false;
            }
        } else {
            $public              = false;
            $exclude_from_search = true;
            $rewrite             = false;
        }

        // The labels for the wpsl_stores post type.
        $labels = apply_filters( 'wpsl_post_type_labels', [
                'name'               => esc_html__( 'Store Locator', 'wp-store-locator' ),
                'all_items'          => esc_html__( 'All Stores', 'wp-store-locator' ),
                'singular_name'      => esc_html__( 'Store', 'wp-store-locator' ),
                'add_new'            => esc_html__( 'New Store', 'wp-store-locator' ),
                'add_new_item'       => esc_html__( 'Add New Store', 'wp-store-locator' ),
                'edit_item'          => esc_html__( 'Edit Store', 'wp-store-locator' ),
                'new_item'           => esc_html__( 'New Store', 'wp-store-locator' ),
                'view_item'          => esc_html__( 'View Stores', 'wp-store-locator' ),
                'search_items'       => esc_html__( 'Search Stores', 'wp-store-locator' ),
                'not_found'          => esc_html__( 'No Stores found', 'wp-store-locator' ),
                'not_found_in_trash' => esc_html__( 'No Stores found in trash', 'wp-store-locator' ),
            ] 
        );
        
        // The arguments for the wpsl_stores post type.
        $args = apply_filters( 'wpsl_post_type_args', [
                'labels'              => $labels, 
                'public'              => $public,
                'exclude_from_search' => $exclude_from_search,
                'show_ui'             => true,
                'menu_position'       => apply_filters( 'wpsl_post_type_menu_position', null ),
                'capability_type'     => 'store',
                'map_meta_cap'        => true,
                'rewrite'             => $rewrite,
                'query_var'           => 'wpsl_stores',
                'supports'            => [ 'title', 'editor', 'author', 'excerpt', 'revisions', 'thumbnail' ],
                'show_in_rest'        => true
            ]
        );

        register_post_type( 'wpsl_stores', $args );
    }

    /**
     * Register the WPSL custom taxonomy.
     * 
     * @since  2.0.0
     * @return void
     */
    public function register_taxonomies() {
        $wpsl_settings = $this->local_pages_settings;
                    
        // Enable permalinks for the taxonomy?
        if ( isset( $wpsl_settings['permalinks'] ) && $wpsl_settings['permalinks'] ) {
            $public  = true;
            $rewrite = [ 'slug' => $wpsl_settings['category_slug'] ];

            if ( $wpsl_settings['permalink_remove_front'] ) {
                $rewrite['with_front'] = false;
            }
        } else {
            $public  = false;
            $rewrite = false;
        }

        $labels = apply_filters( 'wpsl_store_category_labels', [
                'name'              => esc_html__( 'Store Categories', 'wp-store-locator' ),
                'singular_name'     => esc_html__( 'Store Category', 'wp-store-locator' ),
                'search_items'      => esc_html__( 'Search Store Categories', 'wp-store-locator' ),
                'all_items'         => esc_html__( 'All Store Categories', 'wp-store-locator' ),
                'parent_item'       => esc_html__( 'Parent Store Category', 'wp-store-locator' ),
                'parent_item_colon' => esc_html__( 'Parent Store Category:', 'wp-store-locator' ),
                'edit_item'         => esc_html__( 'Edit Store Category', 'wp-store-locator' ),
                'update_item'       => esc_html__( 'Update Store Category', 'wp-store-locator' ),
                'add_new_item'      => esc_html__( 'Add New Store Category', 'wp-store-locator' ),
                'new_item_name'     => esc_html__( 'New Store Category Name', 'wp-store-locator' ),
                'menu_name'         => esc_html__( 'Store Categories', 'wp-store-locator' ),
            ]
        );
                    
        $args = apply_filters( 'wpsl_store_category_args', [
                'labels'                => $labels,
                'public'                => $public,
                'hierarchical'          => true,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'update_count_callback' => '_update_post_term_count',
                'query_var'             => true,
                'rewrite'               => $rewrite,
                'show_in_rest'          => true
            ]
        );

        register_taxonomy( 'wpsl_store_category', 'wpsl_stores', $args );    
    }

    /**
     * Change the default "Enter title here" placeholder.
     *
     * @since  2.0.0
     * @param  string $title The default title placeholder
     * @return string $title The new title placeholder
     */
    public function change_default_title( $title ) {
        $screen = get_current_screen();

        if ( $screen->post_type == 'wpsl_stores' ) {
            $title = esc_html__( 'Enter store title here', 'wp-store-locator' );
        }

        return $title;
    }  

    /**
     * Returns the used columns
     * on the 'All Stores' pages.
     *
     * @since  2.3.0
     * @return array $columns The used columns
     */
    private function used_columns() {
        $columns = [
            'address' => esc_html__( 'Address', 'wp-store-locator' ),
            'city'    => esc_html__( 'City', 'wp-store-locator' ),
            'state'   => esc_html__( 'State', 'wp-store-locator' ),
            'zip'     => esc_html__( 'Zip', 'wp-store-locator' ),
        ];

        /* Raw values only help when filtering for problem rows. */
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter
        if ( isset( $_REQUEST['wpsl_geocode_status'] ) || isset( $_REQUEST['wpsl_coordinates'] ) ) {
            $columns['lat'] = esc_html__( 'Latitude', 'wp-store-locator' );
            $columns['lng'] = esc_html__( 'Longitude', 'wp-store-locator' );
        }

        return apply_filters( 'wpsl_sortable_columns', $columns );
    }

    /**
     * Add new columns to the store list table.
     *
     * @since  2.0.0
     * @param  array $columns The default columns
     * @return array $columns Updated column list
     */
    public function edit_columns( $columns ) {
        $used_columns = $this->used_columns();

        foreach ( $used_columns as $key => $column ) {
            $columns[$key] = $column;
        }

        /* Derived from two meta fields, so not sortable like the used_columns() keys. */
        $columns['coordinates'] = esc_html__( 'Coordinates', 'wp-store-locator' );

        return $columns;
    }
    
    /**
     * Show the correct store content in the correct custom column.
     *
     * @since  2.0.0
     * @param  string $column  The column name
     * @param  int    $post_id The post id
     * @return void
     */
    public function custom_columns( $column, $post_id ) {
        if ( 'coordinates' === $column ) {
            $this->coordinates_column( $post_id );

            return;
        }

        $used_columns = $this->used_columns();

        if ( isset( $used_columns[$column] ) ) {
            echo esc_html( get_post_meta( $post_id, 'wpsl_' . $column, true ) );
        }
    }

    /**
     * Show whether this location can be found on the map.
     *
     * Unusable coordinates are skipped by every search; missing and invalid
     * point at different causes (never geocoded vs. unplotable).
     *
     * @since  3.0.0
     * @param  int $post_id The store post ID
     * @return void
     */
    private function coordinates_column( $post_id ) {
        $state = \WPSL\Admin\Tools\Geocode_Locations::state( $post_id );

        if ( 'valid' === $state ) {
            /**
             * Whether valid coordinates show a confirmation mark.
             *
             * @since 3.0.0
             * @param bool $show_valid
             */
            if ( ! apply_filters( 'wpsl_coordinates_column_show_valid', true ) ) {
                return;
            }

            printf(
                '<span class="wpsl-coordinates wpsl-coordinates-valid" title="%1$s">%2$s<span class="screen-reader-text">%1$s</span></span>',
                esc_attr__( 'Coordinates set', 'wp-store-locator' ),
                wp_kses( $this->coordinates_icon( 'valid' ), $this->icon_tags() )
            );

            return;
        }

        /* No visible label: the icon carries the meaning, the screen-reader text names the state. */
        $announce = ( 'invalid' === $state )
            ? esc_attr__( 'Invalid coordinates', 'wp-store-locator' )
            : esc_attr__( 'Missing coordinates', 'wp-store-locator' );

        $explanation = ( 'invalid' === $state )
            ? esc_html__( 'The stored coordinates are not a point on the map, so this location is never returned by a search.', 'wp-store-locator' )
            : esc_html__( 'This location has no coordinates, so it is never returned by a search.', 'wp-store-locator' );

        // Offering a lookup the geocoder will refuse only produces an error, so point at the key instead.
        $blocked = \WPSL\Admin\Tools\Geocode_Locations::geocoder_problem();

        if ( $blocked ) {
            $action = sprintf(
                '<span class="wpsl-geocode-blocked">%1$s</span><a href="%2$s" class="wpsl-geocode-fix-key">%3$s</a>',
                esc_html( $blocked ),
                esc_url( \WPSL\Admin\Tools\Geocode_Locations::api_settings_url() ),
                esc_html__( 'Go to the API settings', 'wp-store-locator' )
            );
        } else {
            $action = sprintf(
                '<a href="#" class="wpsl-geocode-fix" data-id="%1$d">%2$s</a>',
                absint( $post_id ),
                esc_html__( 'Fix this location', 'wp-store-locator' )
            );
        }

        /*
         * Tooltip so it can carry the fix link. The portal keeps it open
         * for pointer and keyboard, so the link is reachable both ways.
         */
        printf(
            '<span class="wpsl-coordinates wpsl-coordinates-%1$s">' .
                '<span class="wpsl-info wpsl-warning" tabindex="0" role="button" aria-label="%2$s">' .
                    '<span class="wpsl-info-text wpsl-hide">%3$s%4$s</span>' .
                '</span>' .
            '</span>',
            esc_attr( $state ),
            $announce, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
            $explanation, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above
            $action // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above
        );
    }

    /**
     * The icon shown in the coordinates column.
     *
     * @since  3.0.0
     * @param  string $state 'valid', 'missing' or 'invalid'
     * @return string The inline SVG
     */
    private function coordinates_icon( $state ) {
        if ( 'valid' === $state ) {
            return '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M6.2 11.4 3 8.2l1.1-1.1 2.1 2.1 5.7-5.7L13 4.6z"/></svg>';
        }

        return '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M8 1.5 15 14H1zm0 3.6L3.6 12.7h8.8zM7.2 7h1.6v3H7.2zm0 3.9h1.6v1.3H7.2z"/></svg>';
    }

    /**
     * The tags wp_kses() has to keep for the column icons.
     *
     * @since  3.0.0
     * @return array
     */
    private function icon_tags() {
        return [
            'svg'  => [ 'viewbox' => true, 'width' => true, 'height' => true, 'aria-hidden' => true, 'focusable' => true ],
            'path' => [ 'd' => true ],
        ];
    }
    
    /**
     * Define the columns that are sortable.
     *
     * @since  2.0.0
     * @param  array $columns List of sortable columns
     * @return array
     */
    public function sortable_columns( $columns ) {
        $custom       = [];
        $used_columns = $this->used_columns();
        
        foreach ( $used_columns as $key => $column ) {
            $custom[$key] = 'wpsl_' . $key;
        }
        
        return wp_parse_args( $custom, $columns );
    }
    
    /**
     * Set the correct column sort parameters.
     *
     * @since  2.0.0
     * @param  array $vars Column sorting parameters
     * @return array $vars The column sorting parameters inc the correct orderby and wpsl meta_key
     */
    public function sort_columns( $vars ) {
        if ( isset( $vars['post_type'] ) && $vars['post_type'] == 'wpsl_stores' ) {
            if ( isset( $vars['orderby'] ) ) {
                
                $used_columns = $this->used_columns();
                
                /**
                 * Remove 'wpsl_' from the $vars['orderby'] so we can
                 * compare it with the allowed values from the $used_columns.
                 */
                $orderby = substr( $vars['orderby'], 5 );

                if ( isset( $used_columns[$orderby] ) ) {
                    $vars = array_merge( $vars, [
                        'meta_key' => sanitize_text_field( $vars['orderby'] ),
                        'orderby'  => 'meta_value'
                    ] );
                }
            }
        }
        
        return $vars;
    }

    /**
     * Clear the categories cache when taxonomy terms are modified.
     *
     * @since 3.0.0
     */
    public function clear_categories_cache() {
        wpsl_clear_unique_categories_cache();
    }

    /**
     * Clear categories cache when term relationships change for stores.
     *
     * @since 3.0.0
     * @param int    $object_id  Object ID
     * @param array  $terms      An array of object terms
     * @param array  $tt_ids     An array of term taxonomy IDs
     * @param string $taxonomy   Taxonomy slug
     * @param bool   $append     Whether to append new terms to the old terms
     * @param array  $old_tt_ids Old array of term taxonomy IDs
     */
    public function clear_categories_cache_on_term_change( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
        if ( $taxonomy === 'wpsl_store_category' && get_post_type( $object_id ) === 'wpsl_stores' ) {
            wpsl_clear_unique_categories_cache();
        }
    }
}