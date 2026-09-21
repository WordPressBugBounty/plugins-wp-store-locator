<?php
/**
 * Block editor integration.
 *
 * Registers the two blocks ( wpsl/store-locator, wpsl/store-map ), the
 * "WP Store Locator" block category, and the REST route the editor reads
 * its data from. Both blocks are dynamic and render server-side through
 * the shortcodes, so there is exactly one rendering path.
 *
 * @package WPSL\Admin\Blocks
 * @since   3.0.0
 */

namespace WPSL\Admin\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle the block editor integration.
 *
 * @since 3.0.0
 */
class Manager {

    /**
     * The REST namespace the editor fetches from.
     *
     * @since 3.0.0
     */
    const REST_NAMESPACE = 'wpsl/v1';

    /**
     * Register the blocks, the block category, and the REST route.
     *
     * @since  3.0.0
     * @return void
     */
    public function register() {
        register_block_type( WPSL_PLUGIN_DIR . 'assets/dist/admin/blocks/store-locator', [
            'render_callback' => [ $this, 'render_locator_block' ],
        ] );

        register_block_type( WPSL_PLUGIN_DIR . 'assets/dist/admin/blocks/store-map', [
            'render_callback' => [ $this, 'render_map_block' ],
        ] );

        add_filter( 'block_categories_all', [ $this, 'register_block_category' ] );
        add_action( 'rest_api_init',        [ $this, 'register_rest_routes' ] );
    }

    /**
     * Add the "WP Store Locator" category the two blocks file under.
     *
     * @since  3.0.0
     * @param  array $categories Existing block categories.
     * @return array
     */
    public function register_block_category( $categories ) {
        $categories[] = [
            'slug'  => 'wpsl',
            'title' => __( 'WP Store Locator', 'wp-store-locator' ),
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 572 1000" width="24" height="24"><path d="M286 0C128 0 0 128 0 286c0 41 12 81 18 100l204 432c9 18 26 29 26 29s11 11 38 11 37-11 37-11 18-11 27-29l203-432c6-19 18-59 18-100C571 128 443 0 286 0zm0 429c-79 0-143-64-143-143s64-143 143-143 143 64 143 143-64 143-143 143z"/></svg>',
        ];

        return $categories;
    }

    /**
     * Render the store locator block: assemble the [wpsl] shortcode from
     * the block attributes and run it.
     *
     * @since  3.0.0
     * @param  array $attributes Block attributes.
     * @return string The rendered store locator.
     */
    public function render_locator_block( $attributes ) {
        $shortcode_atts = '';

        foreach ( [ 'template', 'start_location' ] as $att ) {
            if ( ! empty( $attributes[ $att ] ) ) {
                $shortcode_atts .= ' ' . $att . '="' . esc_attr( $attributes[ $att ] ) . '"';
            }
        }

        foreach ( [ 'auto_locate', 'marker_clusters' ] as $att ) {
            $value = isset( $attributes[ $att ] ) ? $attributes[ $att ] : '';

            if ( 'true' === $value || 'false' === $value ) {
                $shortcode_atts .= ' ' . $att . '="' . esc_attr( $value ) . '"';
            }
        }

        foreach ( [ 'country', 'state', 'city', 'distance_unit' ] as $att ) {
            if ( ! empty( $attributes[ $att ] ) ) {
                $shortcode_atts .= ' ' . $att . '="' . esc_attr( $attributes[ $att ] ) . '"';
            }
        }

        $has_category_restriction = ! empty( $attributes['category'] ) && is_array( $attributes['category'] );

        if ( $has_category_restriction ) {
            $shortcode_atts .= ' category="' . esc_attr( implode( ',', $attributes['category'] ) ) . '"';
        } else {
            foreach ( [ 'category_filter_type', 'category_selection' ] as $att ) {
                if ( ! empty( $attributes[ $att ] ) ) {
                    $shortcode_atts .= ' ' . $att . '="' . esc_attr( $attributes[ $att ] ) . '"';
                }
            }

            // Columns only mean something under the checkbox filter.
            $filter_type = isset( $attributes['category_filter_type'] ) ? $attributes['category_filter_type'] : '';

            if ( 'checkboxes' === $filter_type && ! empty( $attributes['checkbox_columns'] ) ) {
                $shortcode_atts .= ' checkbox_columns="' . esc_attr( $attributes['checkbox_columns'] ) . '"';
            }
        }

        foreach ( [ 'map_type', 'map_style', 'start_marker', 'store_marker', 'active_marker', 'marker_labels' ] as $att ) {
            if ( ! empty( $attributes[ $att ] ) ) {
                $shortcode_atts .= ' ' . $att . '="' . esc_attr( $attributes[ $att ] ) . '"';
            }
        }

        if ( ! empty( $attributes['disable_shapes'] ) ) {
            $shortcode_atts .= ' shapes="false"';
        }

        return do_shortcode( '[wpsl' . $shortcode_atts . ']' );
    }

    /**
     * Render the map block: assemble the [wpsl_map] shortcode from the
     * block attributes and run it.
     *
     * @since  3.0.0
     * @param  array $attributes Block attributes.
     * @return string The rendered map.
     */
    public function render_map_block( $attributes ) {
        $shortcode_atts = '';

        if ( ! empty( $attributes['id'] ) ) {
            $shortcode_atts .= ' id="' . esc_attr( $attributes['id'] ) . '"';
        }

        if ( ! empty( $attributes['category'] ) && is_array( $attributes['category'] ) ) {
            $shortcode_atts .= ' category="' . esc_attr( implode( ',', $attributes['category'] ) ) . '"';
        }

        foreach ( [ 'width', 'height', 'zoom', 'map_type', 'map_style', 'control_position', 'store_marker', 'active_marker' ] as $att ) {
            if ( ! empty( $attributes[ $att ] ) ) {
                $shortcode_atts .= ' ' . $att . '="' . esc_attr( $attributes[ $att ] ) . '"';
            }
        }

        foreach ( [ 'map_type_control', 'street_view', 'scrollwheel' ] as $att ) {
            if ( isset( $attributes[ $att ] ) && '' !== $attributes[ $att ] ) {
                $shortcode_atts .= ' ' . $att . '="' . esc_attr( $attributes[ $att ] ) . '"';
            }
        }

        if ( ! empty( $attributes['disable_shapes'] ) ) {
            $shortcode_atts .= ' shapes="false"';
        }

        return do_shortcode( '[wpsl_map' . $shortcode_atts . ']' );
    }

    /**
     * Register the REST route the editor reads templates, map types and
     * markers from.
     *
     * @since  3.0.0
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route( self::REST_NAMESPACE, '/block-data', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_block_data' ],
            'permission_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ] );
    }

    /**
     * The data the block editor needs, in the shape the editor consumes.
     *
     * @since  3.0.0
     * @return \WP_REST_Response|array
     */
    public function get_block_data() {
        $templates = [];

        foreach ( wpsl_get_templates() as $template ) {
            $templates[] = [
                'id'   => isset( $template['id'] ) ? $template['id'] : '',
                'name' => isset( $template['name'] ) ? $template['name'] : '',
            ];
        }

        /*
         * One flat option list for the marker dropdowns: the bundled colours
         * ( valued by filename, the form validate_marker() resolves ) and the
         * Marker Studio markers ( valued custom:{id}, with their inline SVG
         * as the preview src ).
         */
        $pickable = wpsl_pickable_markers();
        $markers  = [];

        foreach ( $pickable['bundled'] as $filename => $label ) {
            $markers[] = [
                'value' => $filename,
                'label' => $label,
                'src'   => wpsl_marker_src( $filename ),
                'group' => 'bundled',
                'cap'   => wpsl_marker_preview_cap( $filename ),
            ];
        }

        foreach ( $pickable['custom'] as $value => $marker ) {
            $markers[] = [
                'value' => $value,
                'label' => isset( $marker['name'] ) ? $marker['name'] : $value,
                'src'   => isset( $marker['src'] ) ? $marker['src'] : '',
                'group' => 'custom',
                'cap'   => wpsl_marker_preview_cap( $value ),
            ];
        }

        $settings = wpsl_get_service( 'wpsl_settings' );
        $defaults = [];

        foreach ( [ 'start', 'store', 'active' ] as $type ) {
            $default_value = $settings->get( 'markers', $type . '_marker' ) ?: $settings->get_default( 'markers', $type . '_marker' );

            $defaults[ $type ] = [
                'value' => $default_value,
                'src'   => wpsl_marker_src( $default_value ),
                'cap'   => wpsl_marker_preview_cap( $default_value ),
            ];
        }

        /*
         * Style dropdown options built for the active provider, so the editor
         * holds no provider knowledge. Every value offered is one the shortcode
         * resolvers accept, gated by wpsl_map_style_options().
         */
        $map_styles = [];

        foreach ( wpsl_map_style_options( $settings->get( 'api', 'active_map_service' ) ) as $value => $label ) {
            $map_styles[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        /*
         * The provider is passed along so the editor can hide Google-only
         * controls (map type, map type control, street view) on providers
         * whose frontends never read those attributes.
         */
        $data = [
            'templates'   => $templates,
            'map_types'   => wpsl_get_map_types(),
            'markers'     => $markers,
            'defaults'    => $defaults,
            'map_styles'  => $map_styles,
            'map_service' => $settings->get( 'api', 'active_map_service' ),
            'countries'   => $this->get_country_options(),
            'has_shapes'  => $this->has_shapes(),
        ];

        return function_exists( 'rest_ensure_response' ) ? rest_ensure_response( $data ) : $data;
    }

    /**
     * The country restriction's options, valued by ISO code.
     *
     * A two-letter restriction value is matched against the store's
     * wpsl_country_iso meta instead of its country name ( see
     * Search::prepare_sql_restrictions() ), which is the only form immune to
     * the language a store's country was saved in -- "nl" finds Nederland,
     * Netherlands and Holland alike, where the name never could.
     *
     * @since  3.0.0
     * @return array List of value ( ISO code ) / label ( country name ) pairs.
     */
    private function get_country_options() {
        $names = [];

        foreach ( wpsl_get_regions() as $name => $iso ) {
            if ( $iso ) {
                $names[ $iso ] = $name;
            }
        }

        $stored = wpsl_get_service( 'location_utils' )->get_unique_meta_values( 'wpsl_country_iso' );
        $codes  = [];

        foreach ( (array) $stored as $iso ) {
            $iso = strtolower( trim( $iso ) );

            // A code that matches no region has no label to show, and no
            // store the dropdown could honestly promise.
            if ( isset( $names[ $iso ] ) ) {
                $codes[ $iso ] = true;
            }
        }

        $codes     = $codes ? array_keys( $codes ) : array_keys( $names );
        $countries = [];

        foreach ( $codes as $iso ) {
            $countries[] = [
                'value' => $iso,
                'label' => $names[ $iso ],
            ];
        }

        // By label: the site's own codes arrive in meta_value order, which
        // reads as random once the labels are shown.
        usort( $countries, function( $a, $b ) {
            return strcmp( $a['label'], $b['label'] );
        } );

        return $countries;
    }

    /**
     * Check if any map shapes exist.
     *
     * @since  3.0.0
     * @return bool
     */
    private function has_shapes() {
        if ( function_exists( 'wpsl_map_shapes_exist' ) ) {
            $data = wpsl_map_shapes_exist();
            return ! empty( $data['exist'] );
        }

        $shapes = new \WPSL\Core\Shapes\Repository();
        return ! empty( $shapes->get_collection()['features'] );
    }
}