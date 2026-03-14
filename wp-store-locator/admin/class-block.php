<?php
/**
 * Gutenberg Block class
 *
 * @author Tijmen Smit
 * @since  2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WPSL_Block' ) ) {

    /**
     * Handle the WPSL Gutenberg block
     *
     * @since 2.3.0
     */
    class WPSL_Block {

        /**
         * Constructor
         */
        public function __construct() {
            add_action( 'init', array( $this, 'register_block' ) );
            add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
            add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );
        }

        /**
         * Register custom block category for WPSL blocks.
         *
         * @since  2.3.0
         * @param  array  $categories Existing block categories.
         * @param  object $post       Current post object.
         * @return array  Modified block categories.
         */
        public function register_block_category( $categories, $post ) {
            return array_merge(
                $categories,
                array(
                    array(
                        'slug'  => 'wpsl',
                        'title' => __( 'WP Store Locator', 'wp-store-locator' ),
                        'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 572 1000" width="24" height="24"><path d="M286 0C128 0 0 128 0 286c0 41 12 81 18 100l204 432c9 18 26 29 26 29s11 11 38 11 37-11 37-11 18-11 27-29l203-432c6-19 18-59 18-100C571 128 443 0 286 0zm0 429c-79 0-143-64-143-143s64-143 143-143 143 64 143 143-64 143-143 143z"/></svg>',
                    ),
                )
            );
        }

        /**
         * Register the Gutenberg block.
         *
         * @since  2.3.0
         * @return void
         */
        public function register_block() {

            register_block_type( WPSL_PLUGIN_DIR . 'admin/blocks/wpsl-block', array(
                'render_callback' => array( $this, 'render_block' ),
            ) );

            register_block_type( WPSL_PLUGIN_DIR . 'admin/blocks/wpsl-map-block', array(
                'render_callback' => array( $this, 'render_map_block' ),
            ) );
        }

        /**
         * Server-side render callback for the block.
         *
         * Builds the [wpsl] shortcode from block attributes and returns the output.
         *
         * @since  2.3.0
         * @param  array  $attributes Block attributes.
         * @return string The rendered shortcode output.
         */
        public function render_block( $attributes ) {

            $shortcode_atts = '';

            if ( ! empty( $attributes['template'] ) ) {
                $shortcode_atts .= ' template="' . esc_attr( $attributes['template'] ) . '"';
            }

            if ( ! empty( $attributes['start_location'] ) ) {
                $shortcode_atts .= ' start_location="' . esc_attr( $attributes['start_location'] ) . '"';
            }

            if ( $attributes['auto_locate'] === 'true' || $attributes['auto_locate'] === 'false' ) {
                $shortcode_atts .= ' auto_locate="' . esc_attr( $attributes['auto_locate'] ) . '"';
            }

            $has_category_restriction = ! empty( $attributes['category'] ) && is_array( $attributes['category'] );

            if ( $has_category_restriction ) {
                $shortcode_atts .= ' category="' . esc_attr( implode( ',', $attributes['category'] ) ) . '"';
            }

            if ( ! $has_category_restriction && ! empty( $attributes['category_filter_type'] ) ) {
                $shortcode_atts .= ' category_filter_type="' . esc_attr( $attributes['category_filter_type'] ) . '"';
            }

            if ( ! $has_category_restriction && ! empty( $attributes['category_selection'] ) ) {
                $shortcode_atts .= ' category_selection="' . esc_attr( $attributes['category_selection'] ) . '"';
            }

            if ( ! $has_category_restriction && $attributes['category_filter_type'] === 'checkboxes' && ! empty( $attributes['checkbox_columns'] ) ) {
                $shortcode_atts .= ' checkbox_columns="' . esc_attr( $attributes['checkbox_columns'] ) . '"';
            }

            if ( ! empty( $attributes['map_type'] ) ) {
                $shortcode_atts .= ' map_type="' . esc_attr( $attributes['map_type'] ) . '"';
            }

            if ( ! empty( $attributes['start_marker'] ) ) {
                $shortcode_atts .= ' start_marker="' . esc_attr( $attributes['start_marker'] ) . '"';
            }

            if ( ! empty( $attributes['store_marker'] ) ) {
                $shortcode_atts .= ' store_marker="' . esc_attr( $attributes['store_marker'] ) . '"';
            }

            return do_shortcode( '[wpsl' . $shortcode_atts . ']' );
        }

        /**
         * Server-side render callback for the map block.
         *
         * Builds the [wpsl_map] shortcode from block attributes and returns the output.
         *
         * @since  2.3.0
         * @param  array  $attributes Block attributes.
         * @return string The rendered shortcode output.
         */
        public function render_map_block( $attributes ) {

            $shortcode_atts = '';

            if ( ! empty( $attributes['id'] ) ) {
                $shortcode_atts .= ' id="' . esc_attr( $attributes['id'] ) . '"';
            }

            if ( ! empty( $attributes['category'] ) && is_array( $attributes['category'] ) ) {
                $shortcode_atts .= ' category="' . esc_attr( implode( ',', $attributes['category'] ) ) . '"';
            }

            if ( ! empty( $attributes['width'] ) ) {
                $shortcode_atts .= ' width="' . esc_attr( $attributes['width'] ) . '"';
            }

            if ( ! empty( $attributes['height'] ) ) {
                $shortcode_atts .= ' height="' . esc_attr( $attributes['height'] ) . '"';
            }

            if ( ! empty( $attributes['zoom'] ) ) {
                $shortcode_atts .= ' zoom="' . esc_attr( $attributes['zoom'] ) . '"';
            }

            if ( ! empty( $attributes['map_type'] ) ) {
                $shortcode_atts .= ' map_type="' . esc_attr( $attributes['map_type'] ) . '"';
            }

            if ( $attributes['map_type_control'] !== '' ) {
                $shortcode_atts .= ' map_type_control="' . esc_attr( $attributes['map_type_control'] ) . '"';
            }

            if ( ! empty( $attributes['map_style'] ) ) {
                $shortcode_atts .= ' map_style="' . esc_attr( $attributes['map_style'] ) . '"';
            }

            if ( $attributes['street_view'] !== '' ) {
                $shortcode_atts .= ' street_view="' . esc_attr( $attributes['street_view'] ) . '"';
            }

            if ( $attributes['scrollwheel'] !== '' ) {
                $shortcode_atts .= ' scrollwheel="' . esc_attr( $attributes['scrollwheel'] ) . '"';
            }

            if ( ! empty( $attributes['control_position'] ) ) {
                $shortcode_atts .= ' control_position="' . esc_attr( $attributes['control_position'] ) . '"';
            }

            return do_shortcode( '[wpsl_map' . $shortcode_atts . ']' );
        }

        /**
         * Register REST API routes for block data.
         *
         * @since  2.3.0
         * @return void
         */
        public function register_rest_routes() {

            register_rest_route( 'wpsl/v1', '/block-data', array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_block_data' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            ) );
        }

        /**
         * Return the data needed by the block editor.
         *
         * Includes templates, map types, categories, and marker images.
         *
         * @since  2.3.0
         * @return WP_REST_Response
         */
        public function get_block_data() {

            // Templates
            $templates     = wpsl_get_templates();
            $template_data = array();

            foreach ( $templates as $template ) {
                $template_data[] = array(
                    'id'   => isset( $template['id'] ) ? $template['id'] : '',
                    'name' => isset( $template['name'] ) ? $template['name'] : '',
                );
            }

            // Map types
            $map_types = wpsl_get_map_types();

            // Categories
            $terms         = get_terms( array(
                'taxonomy'   => 'wpsl_store_category',
                'hide_empty' => false,
            ) );
            $category_data = array();

            if ( ! is_wp_error( $terms ) && $terms ) {
                foreach ( $terms as $term ) {
                    $category_data[] = array(
                        'slug' => $term->slug,
                        'name' => $term->name,
                    );
                }
            }

            // Markers
            $marker_dir = apply_filters( 'wpsl_admin_marker_dir', WPSL_PLUGIN_DIR . 'img/markers/' );
            $marker_url = ( defined( 'WPSL_MARKER_URI' ) ) ? WPSL_MARKER_URI : WPSL_URL . 'img/markers/';
            $markers    = array();

            if ( is_dir( $marker_dir ) ) {
                if ( $dh = opendir( $marker_dir ) ) {
                    while ( false !== ( $file = readdir( $dh ) ) ) {
                        if ( $file === '.' || $file === '..' || strpos( $file, '2x' ) !== false ) {
                            continue;
                        }

                        $markers[] = $file;
                    }

                    closedir( $dh );
                    sort( $markers );
                }
            }

            return rest_ensure_response( array(
                'templates'  => $template_data,
                'map_types'  => $map_types,
                'categories' => $category_data,
                'markers'    => $markers,
                'marker_url' => $marker_url,
            ) );
        }
    }

    new WPSL_Block();
}
