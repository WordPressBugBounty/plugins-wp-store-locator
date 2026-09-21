<?php
/**
 * Handle the search functionality.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as Settings;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Utils\Location_Utils;

use WPSL\Frontend\State\Manager as StateManager;

class Shortcodes {

    /**
     * Holds the settings
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Holds the translations
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * Holds the template loader
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Loader
     */
    private $template_loader;

    /**
     * Holds the assets manager
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Assets\Manager
     */
    private $assets_manager;

    /**
     * Holds the service container
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Container
     */
    private $container;

    /**
     * Holds the frontend state manager
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;

    /**
     * Holds the current shortcode attributes
     *
     * @since 3.0.0
     * @var   array
     */
    public $atts = [];

    /**
     * Holds early-detected shortcode attributes (parsed before wp_enqueue_scripts)
     *
     * @since 3.0.0
     * @var   array
     */
    public $detected_atts = [];

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager  $settings  Settings manager instance
     * @param \WPSL\Core\I18n\Translations $i18n      Translations instance
     * @param \WPSL\Frontend\State\Manager $state     Frontend state manager instance
     * @param \WPSL\Core\Container         $container Service container instance
     */
    public function __construct( Settings $settings, Translations $i18n, StateManager $state, \WPSL\Core\Container $container ) {
        $this->settings = $settings;
        $this->i18n = $i18n;
        $this->state = $state;
        $this->container = $container;

        add_shortcode( 'wpsl',         [ $this, 'show_store_locator' ] );
        add_shortcode( 'wpsl_map',     [ $this, 'show_store_map' ] );
        add_shortcode( 'wpsl_address', [ $this, 'show_store_address' ] );
    }

    /**
     * Resolve the template loader on first use.
     *
     * @since  3.0.0
     * @return \WPSL\Core\Templates\Loader
     */
    private function template_loader() {
        if ( ! $this->template_loader ) {
            $this->template_loader = $this->container->get( 'template_loader' );
        }

        return $this->template_loader;
    }

    /**
     * Resolve the assets manager on first use.
     *
     * @since  3.0.0
     * @return \WPSL\Frontend\Assets\Manager
     */
    private function assets_manager() {
        if ( ! $this->assets_manager ) {
            $this->assets_manager = $this->container->get( 'assets_manager' );
        }

        return $this->assets_manager;
    }

    /**
     * Set shortcode attributes.
     *
     * @since  3.0.0
     * @param  array $atts The shortcode attributes
     * @return void
     */
    public function set_atts( $atts ) {
        $this->atts = array_merge( $this->atts, $atts );
    }

    /**
     * Get all shortcode attributes.
     *
     * @since  3.0.0
     * @return array The shortcode attributes
     */
    public function get_atts() {
        return $this->atts;
    }

    /**
     * Get a specific shortcode attribute.
     *
     * @since  3.0.0
     * @param  string $key     The attribute key
     * @param  mixed  $default Default value if key doesn't exist
     * @return mixed  The attribute value or default
     */
    public function get_att( $key, $default = null ) {
        return $this->atts[$key] ?? $default;
    }

    /**
     * Handle the [wpsl] shortcode.
     *
     * @since  1.0.0
     * @param  array  $atts   Shortcode attributes
     * @return string $output The wpsl template
     */
    public function show_store_locator( $atts ) {
        // Covers pages the asset detector cannot see ( theme templates, page
        // builders, widgets ); prints in the footer when it lands this late.
        $this->assets_manager()->ensure_styles();

        $upgraded_from = wpsl_upgraded_from();
        $wpsl_settings = $this->settings->get_group( 'appearance' );

        $atts = shortcode_atts( [
            'template'             => $wpsl_settings['template_id'],
            'start_location'       => '',
            'auto_locate'          => '',
            'category'             => '',
            'exclude_category'     => '',
            'category_selection'   => '',
            'category_filter_type' => '', // dropdown / checkbox
            'category_parent_id'   => '',
            'checkbox_columns'     => '3',
            'category_filter'      => '', // true / false, see Filters::is_category_enabled()
            'radius_filter'        => '', // true / false, see Filters::is_radius_enabled()
            'results_filter'       => '', // true / false, see Filters::is_results_enabled()
            'map_type'             => '',
            'map_style'            => '',
            'map_id'               => '',
            'start_marker'         => '',
            'store_marker'         => '',
            'active_marker'        => '',
            'marker_labels'        => '', // none / numbers / letters, see Marker_Label::MODES
            'marker_clusters'      => '',
            'marker_cluster'       => '',
            'shapes'               => 1,
            'city'                 => '',
            'state'                => '',
            'country'              => '',
            'distance_unit'        => '',
        ], $atts );

        $this->check_sl_shortcode_atts( $atts );

        // Make sure the required scripts are included for the wpsl shortcode.
        $this->state->add_script( 'store_locator' );

        /*
         * The frontend JS indexes every .wpsl-canvas-{provider} by DOM order,
         * and the locator template renders one too, so it has to take a slot in
         * the map count. Otherwise a [wpsl_map] after [wpsl] reads wpslMap_1
         * while its data was published as wpslMap_0 - the locator would render
         * the [wpsl_map] locations and the [wpsl_map] would stay empty.
         */
        $this->state->increment_map_count();

        $template_details = $this->template_loader()->get_details( $atts['template'] );

        // Create an array of commonly used services for templates
        $template_services = [
            'i18n'             => wpsl_get_service( 'i18n' ),
            'assets_manager'   => wpsl_get_service( 'assets_manager' ),
            'maps_manager'     => wpsl_get_service( 'maps_manager' ),
            'search_filters'   => wpsl_get_service( 'search_filters' ),
            'frontend'         => wpsl_get_service( 'frontend' ),
            'wpsl_settings'    => wpsl_get_service( 'wpsl_settings' )->get_all(),
            'template_filters' => wpsl_get_service( 'template_filters' ),
        ];

        // Only include panel_filters for templates that use panel-style filters (v3 templates with #wpsl-panel)
        if ( ! in_array( $atts['template'], [ 'default', 'horizontal' ] ) ) {
            $template_services['panel_filters'] = wpsl_get_service( 'panel_filters' );
        }

        $template_services = apply_filters( 'wpsl_template_services', $template_services );
    
        // Extract the services array to make variables available in the template scope
        extract( $template_services );

        /**
         * Detect 2.x custom templates by their legacy globals / `$this->` /
         * `$wpsl->` markers and run them through the compatibility layer.
         * No toggle exists: a 2.x template can never load without compatibility,
         * and 3.0 templates keep their nested settings untouched.
         */
        $is_custom_template  = wpsl_is_custom_template( $template_details );
        $needs_compatibility = $is_custom_template && wpsl_template_uses_legacy_markers( $template_details['path'] );

        if ( $needs_compatibility ) {
            ob_start();

            $this->load_template_with_compatibility( $template_details['path'], $template_services );
        } else {
            // For new templates, ensure $wpsl_settings is the nested array from template_services
            $wpsl_settings = $template_services['wpsl_settings'];

            ob_start();
            include( $template_details['path'] );
        }

        $output = ob_get_clean();

        /**
         * Built-in templates place the computed outer classes on #wpsl-wrap via
         * get_outer_class(); custom templates (including all 2.x ones) hardcode
         * the wrap. Merge the missing classes in afterwards so scoped styles
         * like .wpsl-has-icons still apply.
         */
        if ( $is_custom_template ) {
            $output = $this->add_outer_classes( $output );
        }

        return $output;
    }

    /**
     * Merge the computed outer classes into a rendered #wpsl-wrap tag.
     *
     * Existing classes are kept, missing ones appended. Output without a
     * #wpsl-wrap element is returned untouched.
     *
     * @since  3.0.0
     * @param  string $output The rendered template output
     * @return string The output with the outer classes merged in
     */
    private function add_outer_classes( $output ) {
        $classes = $this->assets_manager()->get_outer_classes();

        if ( empty( $classes ) || ! is_array( $classes ) ) {
            return $output;
        }

        return preg_replace_callback(
            '/<div\b[^>]*\bid\s*=\s*(["\'])wpsl-wrap\1[^>]*>/i',
            function ( $match ) use ( $classes ) {
                $tag = $match[0];

                if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $tag, $class_attr ) ) {
                    $existing = preg_split( '/\s+/', trim( $class_attr[2] ), -1, PREG_SPLIT_NO_EMPTY );
                    $merged   = array_unique( array_merge( $existing, $classes ) );

                    return str_replace(
                        $class_attr[0],
                        'class=' . $class_attr[1] . esc_attr( join( ' ', $merged ) ) . $class_attr[1],
                        $tag
                    );
                }

                return substr_replace( $tag, ' class="' . esc_attr( join( ' ', $classes ) ) . '">', -1 );
            },
            $output,
            1
        );
    }

    /**
     * Load a v2 custom template through the backward compatibility layer.
     *
     * Writes into the output buffer the caller opened rather than returning
     * anything, so both v2 template styles end up in the same place.
     *
     * @since 3.0.0
     * @param string $template_path The template file path
     * @param array  $template_services The template services array
     * @return void
     */
    private function load_template_with_compatibility( $template_path, $template_services ) {
        $compatibility_wrapper = $this->create_backward_compatibility_object( $template_services );

        // Use a closure to provide $this context for v2 templates
        $template_loader = function( $template_path ) use ( $template_services, $compatibility_wrapper ) {
            global $wpsl, $wpsl_settings;

            // Extract services to make them available as variables
            extract( $template_services );

            // Set global variables for v2 templates that use 'global $wpsl, $wpsl_settings'
            $wpsl          = $compatibility_wrapper;
            $wpsl_settings = $compatibility_wrapper->settings;

            /**
             * A 2.x template ends with `return $output;` (v2 used
             * `$output = include( $path )`), while a 3.0-style template echoes
             * and include() returns 1. Echo only a returned string so the
             * stray 1 never reaches the buffer.
             */
            $returned = include( $template_path );

            if ( is_string( $returned ) ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The dynamic values are escaped while the template output is built.
                echo $returned;
            }
        };

        $bound_loader = $template_loader->bindTo( $compatibility_wrapper, $compatibility_wrapper );

        $bound_loader( $template_path );
    }

    /**
     * Get the template data for the store locator shortcode
     *
     * @since  2.0.0
     * @param  array $atts The shortcode attributes
     * @return array $template_data The template data
     */
    public function show_store_map( $atts = [] ) {
        // Covers pages the asset detector cannot see ( theme templates, page
        // builders, widgets ); prints in the footer when it lands this late.
        $this->assets_manager()->ensure_styles();

        global $post;

        $wpsl_settings = $this->settings->get_all();

        $output  = '';
        $classes = [ 'wpsl-canvas-' . esc_attr( $wpsl_settings['api']['active_map_service'] ) ];

        $atts = shortcode_atts( apply_filters( 'wpsl_map_shortcode_defaults', [
            'id'               => '',
            'category'         => '',
            'width'            => '',
            'height'           => ! empty( $wpsl_settings['appearance']['dimensions']['map_height'] ) ? $wpsl_settings['appearance']['dimensions']['map_height'] : 350,
            'zoom'             => $wpsl_settings['map']['zoom_level'],
            'map_type'         => $wpsl_settings['map']['type'],
            'map_type_control' => $wpsl_settings['map']['type_control'],
            'map_style'        => '',
            'map_id'           => '',
            'street_view'      => $wpsl_settings['map']['streetview'],
            'scrollwheel'      => $wpsl_settings['map']['scrollwheel'],
            'control_position' => $wpsl_settings['map']['control_position'],
            'marker_clusters'  => '',
            'marker_cluster'   => '',
            'shapes'           => 1,
            'store_marker'     => '',
            'active_marker'    => '',
            'city'             => '',
            'state'            => '',
            'country'          => '',
        ] ), $atts );

        /*
         * Clustering and shapes are per-map: they live on wpslMap_{n} data, but
         * the shared atts also feed the global markers.markerClusters flag via
         * Markers::cluster_active(). Drop them so a [wpsl_map] setting doesn't
         * leak into the [wpsl] locator or vice versa.
         */
        $shared_atts = $atts;
        unset( $shared_atts['marker_clusters'], $shared_atts['marker_cluster'], $shared_atts['shapes'] );

        $this->set_atts( $shared_atts );

        $this->state->add_script( 'store_page' );

        if ( get_post_type() == 'wpsl_stores' ) {
            if ( empty( $atts['id'] ) ) {
                if ( isset( $post->ID ) ) {
                    $atts['id'] = $post->ID;
                } else {
                    return;
                }
            }
        } else if ( empty( $atts['id'] ) && empty( $atts['category'] ) && empty( $atts['city'] ) && empty( $atts['state'] ) && empty( $atts['country'] ) ) {
            if ( is_user_logged_in() ) {
                /* translators: 1: opening link tag to documentation, 2: closing link tag */
                $output .= '<p>' . sprintf( esc_html__( 'If you use the [wpsl_map] shortcode outside a store page, then you need to set the %1$sID, category, city, state or country attribute%2$s.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/shortcodes/#store-map">', '</a>' ) . '</p>';
            }

            // Nothing to render without a selector, so bail early instead
            // of building an empty, broken map container ( store id 0 ).
            return $output;
        }

        if ( $atts['category'] ) {
            $store_ids = get_posts( [
                'numberposts' => -1,
                'post_type'   => 'wpsl_stores',
                'post_status' => 'publish',
                'tax_query'   => [
                    [
                        'taxonomy' => 'wpsl_store_category',
                        'field'    => 'slug',
                        'terms'    => array_map( 'trim', explode( ',', sanitize_text_field( $atts['category'] ) ) )
                    ],
                ],
                'fields'      => 'ids'
            ] );

            // The query above already limits us to published stores.
            $validate_store_ids = false;
        } else if ( $atts['city'] || $atts['state'] || $atts['country'] ) {
            // Select the stores to plot by their city / state / country address meta.
            $meta_query = [ 'relation' => 'AND' ];
            $meta_map   = [
                'city'    => 'wpsl_city',
                'state'   => 'wpsl_state',
                'country' => 'wpsl_country',
            ];

            foreach ( $meta_map as $att_key => $meta_key ) {
                if ( $atts[ $att_key ] ) {
                    $meta_query[] = [
                        'key'     => $meta_key,
                        'value'   => sanitize_text_field( $atts[ $att_key ] ),
                        'compare' => '=',
                    ];
                }
            }

            $store_ids = get_posts( [
                'numberposts' => -1,
                'post_type'   => 'wpsl_stores',
                'post_status' => 'publish',
                'meta_query'  => $meta_query,
                'fields'      => 'ids'
            ] );

            // The query above already limits us to published stores.
            $validate_store_ids = false;
        } else {
            $store_ids = array_map( 'absint', explode( ',', $atts['id'] ) );
            $id_count  = count( $store_ids );

            // Ids came from the attribute, so validate them before use.
            $validate_store_ids = true;
        }

        /*
         * Prime the post, term and meta caches for every plotted store, so the
         * title / permalink / meta reads in the loop below don't each hit the
         * DB ( N+1 ). A single store has nothing to batch.
         */
        if ( count( $store_ids ) > 1 ) {
            _prime_post_caches( $store_ids, true, true );

            /*
             * Featured images are separate attachment posts the prime above
             * misses. The loop doesn't read them, but the
             * wpsl_cpt_info_window_meta_fields / wpsl_marker_popup_data filters
             * commonly add thumbnails - keep those query free.
             */
            $thumb_ids = [];

            foreach ( $store_ids as $prime_store_id ) {
                $thumb_id = get_post_thumbnail_id( $prime_store_id );

                if ( $thumb_id ) {
                    $thumb_ids[] = $thumb_id;
                }
            }

            if ( $thumb_ids ) {
                _prime_post_caches( $thumb_ids, false, true );
            }
        }

        /*
        * The location url is included if:
        *
        * - Multiple ids are set.
        * - The category attr is set.
        * - A city / state / country filter is set.
        * - The shortcode is used on a post type other then 'wpsl_stores'. No point in showing a location
        * url to the user that links back to the page they are already on.
        */
        if ( $atts['category'] || $atts['city'] || $atts['state'] || $atts['country'] || isset( $id_count ) && $id_count > 1 || get_post_type() != 'wpsl_stores' && ! empty( $atts['id'] ) ) {
            $incl_url = true;
        } else {
            $incl_url = false;
        }

        $store_meta = [];
        $i          = 0;

        // Check if any category has an image set (for category markers)
        $store_data_service = wpsl_get_service( 'store_data' );
        $has_category_images = $store_data_service->has_any_category_images();

        /**
         * Resolve the store_marker / active_marker attributes to full marker
         * URLs, so they can be baked into every location of this map.
         */
        $shortcode_markers = $this->get_map_marker_urls( $atts );

        foreach ( $store_ids as $store_id ) {

            /**
             * When the ids came from the shortcode attribute, make sure they point
             * to a published store before rendering, so we don't leak meta of drafts,
             * private posts, or non-store post types.
             */
            if ( $validate_store_ids && $store_id != get_the_ID() ) {
                if ( get_post_type( $store_id ) !== 'wpsl_stores' || get_post_status( $store_id ) !== 'publish' ) {
                    continue;
                }
            }

            $lat = get_post_meta( $store_id, 'wpsl_lat', true );
            $lng = get_post_meta( $store_id, 'wpsl_lng', true );

            // Make sure the latlng is numeric before collecting the other meta data.
            if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
                $store_meta[$i] = apply_filters( 'wpsl_cpt_info_window_meta_fields', [
                    'store'    => esc_html( get_the_title( $store_id ) ),
                    'address'  => sanitize_text_field( get_post_meta( $store_id, 'wpsl_address',  true ) ),
                    'address2' => sanitize_text_field( get_post_meta( $store_id, 'wpsl_address2', true ) ),
                    'city'     => sanitize_text_field( get_post_meta( $store_id, 'wpsl_city',     true ) ),
                    'state'    => sanitize_text_field( get_post_meta( $store_id, 'wpsl_state',    true ) ),
                    'zip'      => sanitize_text_field( get_post_meta( $store_id, 'wpsl_zip',      true ) ),
                    'country'  => sanitize_text_field( get_post_meta( $store_id, 'wpsl_country',  true ) )
                ], $store_id );

                // Grab the permalink / url if necessary.
                if ( $incl_url ) {
                    if ( $wpsl_settings['local_pages']['permalinks'] ) {
                        $store_meta[$i]['permalink'] = get_permalink( $store_id );
                    } else {
                        $store_meta[$i]['url'] = get_post_meta( $store_id, 'wpsl_url', true );
                    }
                }

                $store_meta[$i]['lat'] = $lat;
                $store_meta[$i]['lng'] = $lng;
                $store_meta[$i]['id']  = $store_id;

                // Never labelled, so the JS keeps the settings page pins for it.
                $store_meta[$i]['mapShortcode'] = true;

                // Add contact details / hours / description when the landing page
                // marker popup is enabled for them on the settings page.
                $store_meta[$i] = array_merge( $store_meta[$i], $store_data_service->get_marker_popup_data( $store_id ) );

                // Add category marker URLs if category images are enabled
                if ( $has_category_images ) {
                    $category_markers = $store_data_service->get_category_image( $store_id );

                    $store_meta[$i]['categoryMarkerUrl'] = ! empty( $category_markers['normal'] ) ? $category_markers['normal'] : '';
                    $store_meta[$i]['categoryMarkerUrlActive'] = ! empty( $category_markers['active'] ) ? $category_markers['active'] : '';
                }

                $location_marker = get_post_meta( $store_id, 'wpsl_location_marker', true );

                if ( $location_marker ) {
                    $location_marker_src = wpsl_marker_src( $location_marker );
                    if ( $location_marker_src ) {
                        $store_meta[$i]['locationMarkerUrl'] = $location_marker_src;
                    }
                }

                $location_marker_active = get_post_meta( $store_id, 'wpsl_location_marker_active', true );

                if ( $location_marker_active ) {
                    $location_marker_active_src = wpsl_marker_src( $location_marker_active );
                    if ( $location_marker_active_src ) {
                        $store_meta[$i]['locationMarkerUrlActive'] = $location_marker_active_src;
                    }
                }

                /**
                 * Per-map marker colors ride the per-marker override properties
                 * (alternateMarkerUrl beats categoryMarkerUrl in the JS), so an
                 * explicit shortcode attribute wins over a category marker image.
                 */
                if ( $shortcode_markers['store'] ) {
                    $store_meta[$i]['alternateMarkerUrl'] = $shortcode_markers['store'];
                }

                if ( $shortcode_markers['active'] ) {
                    $store_meta[$i]['categoryMarkerUrlActive'] = $shortcode_markers['active'];

                    /**
                     * locationMarkerUrlActive outranks categoryMarkerUrlActive
                     * in the JS, so drop the per-store active for the shortcode
                     * attribute to keep winning - same rule that lets
                     * alternateMarkerUrl beat locationMarkerUrl for the normal state.
                     */
                    unset( $store_meta[$i]['locationMarkerUrlActive'] );
                }

                $i++;
            }
        }

        $map_id = 'wpsl-base-' . $wpsl_settings['api']['active_map_service'] . '_' . $this->state->get_map_count();

        /**
         * With the plugin's own GDPR handler active, each basic map carries
         * its own consent overlay. The id-based locator checkpoint only
         * exists in the [wpsl] templates, so without this a [wpsl_map]-only
         * page would show an empty container with no way to consent.
         */
        $gdpr_checkpoint = wpsl_get_service( 'frontend' )->maybe_show_map_gdpr_checkpoint( $map_id );

        if ( $gdpr_checkpoint ) {
            $classes[] = 'wpsl-gdpr-checkpoint';
        }

        $output = '<div id="' . $map_id . '" class="' . join( ' ', $classes ) . '">' . $gdpr_checkpoint . '</div>' . "\r\n";

        // Make sure the shortcode attributes are valid.
        $map_styles = $this->check_map_atts( $atts );

        if ( $map_styles ) {
            if ( isset( $map_styles['css'] ) && ! empty( $map_styles['css'] ) ) {
                $output .= '<style>' . $map_styles['css'] . '</style>' . "\r\n";
                unset( $map_styles['css'] );
            }

            if ( $map_styles ) {
                $store_data['shortCode'] = $map_styles;
            }
        }

        if ( $this->settings->get( 'api', 'active_map_service') == 'mapbox') {
            require_once WPSL_PLUGIN_DIR . 'includes/core/map/class-geojson.php';

            $geojson = new \WPSL\Core\Map\GeoJSON();

            // Return the results in GeoJSON format?
            if ( $geojson->is_supported() ) {
                $store_meta = $geojson->create( $store_meta );
            }
        }

        $store_data['locations'] = $store_meta;

        $this->state->set_store_map_data( $this->state->get_map_count(), $store_data );
        $this->state->increment_map_count();

        return $output;
    }

    /**
     * Handle the [wpsl_address] shortcode.
     *
     * @since  2.0.0
     * @param  array       $atts   Shortcode attributes
     * @return void|string $output The store address
     */
    public function show_store_address( $atts ) {
        // Covers pages the asset detector cannot see ( theme templates, page
        // builders, widgets ); prints in the footer when it lands this late.
        $this->assets_manager()->ensure_styles();

        global $post;

        $wpsl_settings = $this->settings->get_all();

        $output = '';

        /**
         * The contact-detail location setting acts as the default for the
         * phone / fax / email / url attributes. Any attribute that's explicitly
         * passed to the shortcode always overrides this default.
         */
        $show_contact_details = in_array( 'landing_page', $wpsl_settings['ux']['contact_details'], true );

        /*
         * Which details the author named, before shortcode_atts() merges the
         * defaults over them and the two become indistinguishable. The
         * contact_details group below only fills in the ones left out.
         */
        $named_atts = is_array( $atts ) ? $atts : [];

        $atts = wpsl_bool_check( shortcode_atts( apply_filters( 'wpsl_address_shortcode_defaults', [
            'id'                        => '',
            'name'                      => true,
            'address'                   => true,
            'address2'                  => true,
            'city'                      => true,
            'state'                     => true,
            'zip'                       => true,
            'country'                   => true,
            'contact_details'           => null,
            'phone'                     => $show_contact_details,
            'fax'                       => $show_contact_details,
            'email'                     => $show_contact_details,
            'url'                       => $show_contact_details,
            'directions'                => false,
            'clickable_contact_details' => (bool) $wpsl_settings['ux']['clickable_contact_details'],
            'bold_contact_details'      => true
        ] ), $atts ) );

        /**
         * Switch the four contact details at once, the way the "below the
         * address on the landing page" setting does, so a single instance
         * doesn't need four attributes to disagree with that setting.
         *
         * Individual attributes still win: contact_details supplies a default
         * for the details the author didn't name, not an override. It stays
         * null while unset, which is what separates "no opinion" from a
         * deliberate contact_details="false".
         */
        if ( null !== $atts['contact_details'] ) {
            foreach ( [ 'phone', 'fax', 'email', 'url' ] as $contact_detail ) {
                if ( ! isset( $named_atts[ $contact_detail ] ) ) {
                    $atts[ $contact_detail ] = $atts['contact_details'];
                }
            }
        }

        // Remember if an id was explicitly passed, before we fall back to the current post.
        $explicit_id = ! empty( $atts['id'] );

        if ( get_post_type() == 'wpsl_stores' ) {
            if ( empty( $atts['id'] ) ) {
                if ( isset( $post->ID ) ) {
                    $atts['id'] = $post->ID;
                } else {
                    return;
                }
            }
        } else if ( empty( $atts['id'] ) ) {
            if ( is_user_logged_in() ) {
                /* translators: 1: opening link tag to documentation, 2: closing link tag */
                $output .= '<p>' . sprintf( esc_html__( 'If you use the [wpsl_address] shortcode outside a store page, then you need to set the %1$sID attribute%2$s.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/shortcodes/#store-address">', '</a>' ) . '</p>';
            }

            return $output;
        }

        /**
         * When an id is explicitly passed to the shortcode, make sure it points
         * to a published store before rendering, so we don't leak titles / meta
         * of drafts, private posts, or non-store post types. The current-post
         * fallback on a store page is trusted, so previews keep working.
         */
        if ( $explicit_id ) {
            $atts['id'] = absint( $atts['id'] );

            if ( get_post_type( $atts['id'] ) !== 'wpsl_stores' || get_post_status( $atts['id'] ) !== 'publish' ) {
                return;
            }
        }

        $output .= '<div class="wpsl-locations-details">';

        if ( $atts['name'] && $name = get_the_title( $atts['id'] ) ) {
            $output .= '<span><strong>' . esc_html( $name ) . '</strong></span>';
        }

        $output .= '<div class="wpsl-location-address">';

        if ( $atts['address'] && $address = get_post_meta( $atts['id'], 'wpsl_address', true ) ) {
            $output .= '<span>' . esc_html( $address ) . '</span><br/>';
        }

        if ( $atts['address2'] && $address2 = get_post_meta( $atts['id'], 'wpsl_address2', true ) ) {
            $output .= '<span>' . esc_html( $address2 ) . '</span><br/>';
        }

        $address_format = explode( '_', $wpsl_settings['ux']['address_format'] );
        $count = count( $address_format );
        $i = 1;

        // Loop over the address parts to make sure they are shown in the right order.
        foreach ( $address_format as $address_part ) {

            // Make sure the shortcode attribute is set to true for the $address_part, and it's not the 'comma' part.
            if ( $address_part != 'comma' && $atts[$address_part] ) {
                $post_meta = get_post_meta( $atts['id'], 'wpsl_' . $address_part, true );

                if ( $post_meta ) {

                   /**
                    * Check if the next part of the address is set to 'comma'.
                    * If so add the, after the current address part, otherwise just show a space
                    */
                    if ( isset( $address_format[$i] ) && ( $address_format[$i] == 'comma' ) ) {
                        $punctuation = ', ';
                    } else {
                        $punctuation = ' ';
                    }

                    // If we have reached the last item add a <br /> behind it.
                    $br = ( $count == $i ) ? '<br />' : '';

                    $output .= '<span>' . esc_html( $post_meta ) . $punctuation . '</span>' . $br;
                }
            }

            $i++;
        }

        if ( $atts['country'] && $country = get_post_meta( $atts['id'], 'wpsl_country', true ) ) {
            $output .= '<span>' . esc_html( $country ) . '</span>';
        }

        $output .= '</div>';

        // If either the phone, fax, email or url is set to true, then add the wrap div for the contact details.
        if ( $atts['phone'] || $atts['fax'] || $atts['email'] || $atts['url'] ) {
            $phone = get_post_meta( $atts['id'], 'wpsl_phone', true );
            $fax   = get_post_meta( $atts['id'], 'wpsl_fax', true );
            $email = get_post_meta( $atts['id'], 'wpsl_email', true );

            if ( $atts['clickable_contact_details'] ) {
                $contact_details = [
                    'phone' => '<a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>',
                    'fax'   => '<a href="tel:' . esc_attr( $fax ) . '">' . esc_html( $fax ) . '</a>',
                    'email' => '<a href="mailto:' . esc_attr( $email ) . '">' . esc_attr( $email ) . '</a>'
                ];
            } else {
                $contact_details = [
                    'phone' => esc_html( $phone ),
                    'fax'   => esc_html( $fax ),
                    'email' => esc_attr( $email )
                ];
            }

            // Wrap the contact labels in <strong> to match the search results list, unless disabled.
            $label_open  = $atts['bold_contact_details'] ? '<strong>' : '';
            $label_close = $atts['bold_contact_details'] ? '</strong>' : '';

            $output .= '<div class="wpsl-contact-details">';

            if ( $atts['phone'] && $phone ) {
                $output .= $label_open . esc_html( $this->i18n->get_translation( 'phone_label', esc_html__( 'Phone', 'wp-store-locator' ) ) ) . $label_close . ': <span>' . $contact_details['phone'] . '</span><br/>';
            }

            if ( $atts['fax'] && $fax ) {
                $output .= $label_open . esc_html( $this->i18n->get_translation( 'fax_label', esc_html__( 'Fax', 'wp-store-locator' ) ) ) . $label_close . ': <span>' . $contact_details['fax'] . '</span><br/>';
            }

            if ( $atts['email'] && $email ) {
                $output .= $label_open . esc_html( $this->i18n->get_translation( 'email_label', esc_html__( 'Email', 'wp-store-locator' ) ) ) . $label_close . ': <span>' . $contact_details['email'] . '</span><br/>';
            }

            if ( $atts['url'] && $store_url = get_post_meta( $atts['id'], 'wpsl_url', true ) ) {
                $new_window = ( $wpsl_settings['ux']['new_window'] ) ? 'target="_blank"' : '' ;
                $output .= $label_open . esc_html( $this->i18n->get_translation( 'url_label', esc_html__( 'Url', 'wp-store-locator' ) ) ) . $label_close . ': <a ' . $new_window . ' href="' . esc_url( $store_url ) . '">' . esc_url( $store_url ) . '</a><br/>';
            }

            $output .= '</div>';
        }

        /**
         * Fetch the address here instead of reusing $address, which is only set
         * when the 'address' attribute is enabled. This way the directions link
         * still works with address="false".
         */
        $directions_address = get_post_meta( $atts['id'], 'wpsl_address', true );

        if ( $atts['directions'] && $directions_address ) {
            $new_window = ( $wpsl_settings['ux']['new_window'] ) ? 'target="_blank"' : '' ;

            $output .= '<div class="wpsl-location-directions">';

            $city        = get_post_meta( $atts['id'], 'wpsl_city', true );
            $country     = get_post_meta( $atts['id'], 'wpsl_country', true );
            $destination = $directions_address . ',' . $city . ',' . $country;

            $map_service = wpsl_get_active_map_service();

            if ( $map_service == 'gmaps' ) {
                $direction_url = "https://maps.google.com/maps?saddr=&daddr=" . urlencode( $destination ) . "&travelmode=" . wpsl_get_directions_travel_mode();
            } else if ( in_array( $map_service, [ 'osm', 'stadia', 'mapbox' ] ) ) {
                $lng = get_post_meta( $atts['id'], 'wpsl_lng', true );
                $lat = get_post_meta( $atts['id'], 'wpsl_lat', true );

                // Matches the external directions link these providers use in the store locator.
                $direction_url = "https://www.openstreetmap.org/directions?from=&to=" . urlencode( $lat . ',' . $lng );
            }

            if ( isset( $direction_url ) ) {
                $output .= '<p><a ' . $new_window . ' href="' . esc_url( $direction_url ) . '">' . esc_html__( 'Directions', 'wp-store-locator' ) . '</a></p>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Create backward compatibility object for v2 templates
     *
     * @since  3.0.0
     * @param  array $template_services The template services array
     * @return \WPSL\Frontend\Templates\Backward_Compatibility The backward compatibility object
     */
    private function create_backward_compatibility_object( $template_services ) {
        $legacy_services = $template_services;

        /**
         * v2 templates read flat keys ( $wpsl_settings['height'] ), not the v3
         * groups. Share the same builder wpsl_maybe_set_v2_global() uses, so a
         * template and a filter callback never see a different $wpsl_settings.
         */
        $legacy_services['wpsl_settings'] = wpsl_build_v2_settings();

        return new \WPSL\Frontend\Templates\Backward_Compatibility( $legacy_services );
    }

    /**
     * Handle the [wpsl] shortcode attributes.
     *
     * @since 2.1.1
     * @param array $atts Shortcode attributes
     */
    public function check_sl_shortcode_atts( $atts ) {

        $shared_atts = $atts;
        unset( $shared_atts['shapes'] );

        $this->set_atts( $shared_atts );

        $restrictions = [ 'city', 'state', 'country' ];

        /**
         * The [wpsl] locator uses the same shortCode.shapes flag as [wpsl_map].
         * It publishes no wpslMap_{n} data, so the entry is written only when
         * shapes are off - absent means "draw". Runs before
         * show_store_locator()'s increment_map_count(), so get_map_count()
         * is still this locator's own slot.
         */
        if ( isset( $atts['shapes'] ) && ! $this->shapes_enabled( $atts['shapes'] ) ) {
            $map_index = $this->state->get_map_count();
            $map_data  = $this->state->get_store_map_data( $map_index );
            $map_data  = is_array( $map_data ) ? $map_data : [];

            if ( ! isset( $map_data['shortCode'] ) || ! is_array( $map_data['shortCode'] ) ) {
                $map_data['shortCode'] = [];
            }

            $map_data['shortCode']['shapes'] = false;

            $this->state->set_store_map_data( $map_index, $map_data );
        }

        /**
         * Use a custom start location?
         *
         * If the provided location fails to geocode,
         * then the start location from the settings page is used.
         */
        if ( isset( $atts['start_location'] ) && $atts['start_location'] ) {
            $start_latlng = false;

            /**
             * If there's a comma in the start location shortcode
             * check if we are dealing with coordinates.
             */
            if ( strpos( $atts['start_location'], ',' ) !== false ) {
                $latlng = array_map('trim', explode( ',', $atts['start_location'] ) );

                $start_latlng = Location_Utils::validate_latlng( $latlng[0], $latlng[1] );
            }

            /**
             * No coordinates yet, so check if there's a transient that holds
             * latlng for the passed location. If not, we make a geocode request
             * to get them and store them in a new transient.
             */
            if ( ! $start_latlng ) {
                $start_latlng = wpsl_check_latlng_transient( $atts['start_location'] );
            } else {
                $start_latlng = $start_latlng['lat'] . ',' . $start_latlng['lng'];
            }

            if ( isset( $start_latlng ) && $start_latlng ) {
                $this->atts['js']['map']['startLatLng'] = $start_latlng;
            }
        }

        /**
         * The shortcode atts are merged over the localized settings, so this
         * would put back the auto-locate that a name search deliberately
         * leaves off ( there are no coordinates to search on, and a pageload
         * geolocation search defeats the input_only option ).
         */
        if ( isset( $atts['auto_locate'] ) && $atts['auto_locate'] && $this->settings->get( 'search', 'search_method' ) !== 'name' ) {
            $this->atts['js']['search']['autoLocate']['enabled'] = $this->atts_boolean( $atts['auto_locate'] );
        }

        // Change the category slugs into category ids.
        if ( isset( $atts['category'] ) && $atts['category'] ) {
            $term_ids = wpsl_get_term_ids( $atts['category'] );

            if ( $term_ids ) {
                $this->atts['js']['search']['categoryIds'] = implode( ',', $term_ids );
            }
        }

        if ( isset( $atts['exclude_category'] ) && $atts['exclude_category'] ) {
            $this->atts['exclude_category'] = $atts['exclude_category'];
        }

        if ( isset( $atts['category_selection'] ) && $atts['category_selection'] ) {
            $this->atts['category_selection'] = wpsl_get_term_ids( $atts['category_selection'] );
        }

        if ( isset( $atts['category_filter_type'] ) && ! in_array( $atts['category_filter_type'], [ 'dropdown', 'checkboxes' ] ) ) {
            $this->atts['category_filter_type'] = '';
        }

        /**
         * CSS ships for 1-4 columns (all the generator and block offer), but a
         * higher count still renders wpsl-checkbox-N-columns for a theme to
         * style. Only a non-numeric value is dropped, falling back to
         * category_list()'s default of 3.
         */
        if ( isset( $atts['checkbox_columns'] ) ) {
            $checkbox_columns = is_numeric( $atts['checkbox_columns'] ) ? (int) $atts['checkbox_columns'] : 0;

            $this->atts['checkbox_columns'] = $checkbox_columns >= 1 ? $checkbox_columns : '';
        }

        // An unknown map type keeps the settings value, so only the override is set.
        if ( isset( $atts['map_type'] ) ) {
            if ( array_key_exists( $atts['map_type'], wpsl_get_map_types() ) ) {
                $this->atts['js']['map']['type'] = $atts['map_type'];
            } else {
                $this->atts['map_type'] = '';
            }
        }

        /**
         * Overwrite the map style for the store locator. The value is resolved
         * against the styles of the active map service, the same way as the
         * [wpsl_map] map_style attribute.
         */
        if ( isset( $atts['map_style'] ) && ! empty( $atts['map_style'] ) ) {
            $map_service = $this->settings->get( 'api', 'active_map_service' );

            if ( 'gmaps' === $map_service ) {
                // Google Maps has no named styles, 'default' clears the global JSON style.
                if ( 'default' === $atts['map_style'] ) {
                    $this->atts['js']['map']['style'] = '';
                }
            } elseif ( 'mapbox' === $map_service ) {
                $style_url = wpsl_get_shortcode_mapbox_style( $atts['map_style'] );

                if ( $style_url ) {
                    $this->atts['js']['map']['style'] = $style_url;
                }
            } else {
                // osm / stadia, both render through Leaflet.
                $tile_layer = wpsl_get_shortcode_tile_layer( $atts['map_style'] );

                if ( $tile_layer ) {
                    $this->atts['js']['api']['tileLayer'] = $tile_layer;

                    /**
                     * Vector styles need the MapLibre GL scripts, which are only
                     * enqueued by default when the global style is a vector one.
                     * Flag it so the assets manager loads them for this page too.
                     */
                    if ( isset( $tile_layer['type'] ) && 'vector' === $tile_layer['type'] ) {
                        $this->state->add_script( 'maplibre' );
                    }
                }
            }
        }

        /**
         * Overwrite the Google Maps cloud-based Map ID for the store locator.
         * Only applies when Google Maps is the active map service.
         */
        if ( isset( $atts['map_id'] ) && ! empty( $atts['map_id'] ) && 'gmaps' === $this->settings->get( 'api', 'active_map_service' ) ) {
            $this->atts['js']['map']['mapId'] = sanitize_text_field( $atts['map_id'] );
        }

        if ( isset( $atts['start_marker'] ) && $atts['start_marker'] ) {
            $this->atts['js']['markers']['start'] = $this->validate_marker( $atts['start_marker'], 'red' );
        }

        if ( isset( $atts['store_marker'] ) && $atts['store_marker'] ) {
            $this->atts['js']['markers']['store'] = $this->validate_marker( $atts['store_marker'], 'blue' );
        }

        if ( isset( $atts['active_marker'] ) && $atts['active_marker'] ) {
            $this->atts['js']['markers']['active'] = $this->validate_marker( $atts['active_marker'], 'dark-blue' );
        }

        // Runtime marker labels. An empty or unknown value leaves the setting
        // in charge; off / false / 0 / no turn them off.
        if ( isset( $atts['marker_labels'] ) && '' !== $atts['marker_labels'] ) {
            $label_mode = \WPSL\Core\Markers\Marker_Label::sanitize_mode( $atts['marker_labels'], null );

            if ( null !== $label_mode ) {
                $this->atts['js']['markers']['labels'] = $label_mode;
            }
        }

        if ( isset( $atts['marker_cluster'] ) && $atts['marker_cluster'] !== '' ) {
            $atts['marker_clusters'] = $atts['marker_cluster'];
        }

        if ( isset( $atts['marker_clusters'] ) && $atts['marker_clusters'] !== '' ) {
            $this->atts['marker_clusters'] = $this->atts_boolean( $atts['marker_clusters'] );

            // Safety net for shortcodes the early detection cannot see.
            if ( $this->atts['marker_clusters'] ) {
                $this->assets_manager()->enqueue_cluster_styles();
            }
        }

        if ( isset( $atts['distance_unit'] ) && in_array( $atts['distance_unit'], [ 'km', 'mi' ] ) ) {
            $this->atts['distance_unit'] = $atts['distance_unit'];
        }

        if ( isset( $atts['category_parent_id'] ) && is_numeric( $atts['category_parent_id'] ) ) {
            $this->atts['category_parent_id'] = $atts['category_parent_id'];
        }

        if ( isset( $atts['template'] ) && $atts['template'] ) {
            $this->atts['template'] = $atts['template'];
        }

        /**
         * Check if any city / state / country
         * restrictions are set through the shortcode.
         */
        foreach ( $restrictions as $restriction ) {
            if ( isset( $atts[$restriction] ) && $atts[$restriction] ) {
                $this->atts['js']['search']['restrictions'][$restriction] = $atts[$restriction];
            }
        }

        /**
         * If a city / state / country restriction is set through the shortcode,
         * but no new start location is provided, then we try to create new
         * start location coordinates based on the provided city / state / country values.
         *
         * This prevents situations where a user has for example set London as the
         * start point on the settings page, but the map locations are restricted to Canada.
         */
        if ( isset( $this->atts['js']['search']['restrictions'] ) && ! isset( $this->atts['js']['map']['startLatLng'] ) ) {
            $shortcode_restrictions = wpsl_check_country_restriction( $this->atts['js']['search']['restrictions'] );

            if ( ! isset( $shortcode_restrictions['skip'] ) ) {
                $search_restrictions = [];

                foreach ( $restrictions as $restriction ) {
                    if ( isset( $shortcode_restrictions[$restriction] ) && $shortcode_restrictions[$restriction] ) {
                        $search_restrictions[] = $shortcode_restrictions[$restriction];
                    }
                }

                $restrictions = implode( ',', $search_restrictions );
                $start_latlng = wpsl_check_latlng_transient( $restrictions );

                if ( isset( $start_latlng ) && $start_latlng ) {
                    $this->atts['js']['map']['startLatLng'] = $start_latlng;
                }
            }
        }
    }

    /**
     * Validate marker name against available marker files.
     *
     * @since  3.0.0
     * @param  string $marker_name The marker name ( color, with or without extension ), or "custom:{id}"
     * @param  string $default     The default marker color to use if validation fails
     * @return string              The resolved marker filename, or a "custom:{id}" value
     */
    private function validate_marker( $marker_name, $default = 'red' ) {

        /**
         * The colors the pickers offer, which is the bundled folder plus
         * whatever wpsl_admin_marker_dir added.
         */
        $available_markers = array_keys( \wpsl_bundled_markers() );

        $custom_id = \wpsl_custom_marker_id( $marker_name );

        if ( $custom_id && \wpsl_custom_marker_data_uri( $custom_id ) ) {
            return 'custom:' . $custom_id;
        }

        $base_name = pathinfo( $marker_name, PATHINFO_FILENAME );

        if ( ! in_array( $marker_name, $available_markers, true ) && ! in_array( $base_name, $available_markers, true ) ) {
            $marker_name = $default;
        }

        return \wpsl_resolve_marker_filename( $marker_name );
    }

    /**
     * Make sure the map style shortcode attributes are valid.
     *
     * The values are send to wp_localize_script in add_frontend_scripts.
     *
     * @since  3.0.0
     * @param  array $atts     The map style shortcode attributes
     * @return array $map_atts Validated map style shortcode attributes
     */
    public function check_map_atts( $atts ) {
        $map_atts = [];

        if ( isset( $atts['width'] ) && is_numeric( $atts['width'] ) ) {
            $width = 'width:' . $atts['width'] . 'px;';
        } else {
            $width = '';
        }

        if ( isset( $atts['height'] ) && is_numeric( $atts['height'] ) ) {
            $height = 'height:' . $atts['height'] . 'px;';
        } else {
            $height = '';
        }

        if ( $width || $height ) {
            $map_atts['css'] = '#wpsl-base-' . $this->settings->get( 'api', 'active_map_service' ) . '_' . $this->state->get_map_count() . ' {' . $width . $height . '}';
        }

        if ( isset( $atts['zoom'] ) && ! empty( $atts['zoom'] ) ) {
            $map_atts['zoomLevel'] = wpsl_valid_zoom_level( $atts['zoom'] );
        }

        if ( isset( $atts['map_type'] ) && ! empty( $atts['map_type'] ) ) {
            $map_atts['mapType'] = wpsl_valid_map_type( $atts['map_type'] );
        }

        if ( isset( $atts['map_type_control'] ) ) {
            $map_atts['mapTypeControl'] = $this->atts_boolean( $atts['map_type_control'] );
        }

        if ( isset( $atts['map_style'] ) && ! empty( $atts['map_style'] ) ) {
            $map_service = $this->settings->get( 'api', 'active_map_service' );

            if ( 'gmaps' === $map_service ) {
                // Google Maps has no named styles, 'default' clears the global JSON style for this map.
                if ( $atts['map_style'] == 'default' ) {
                    $map_atts['mapStyle'] = '';
                }
            } elseif ( 'mapbox' === $map_service ) {
                $style_url = wpsl_get_shortcode_mapbox_style( $atts['map_style'] );

                if ( $style_url ) {
                    $map_atts['mapStyle'] = $style_url;
                }
            } else {
                // osm / stadia, both render through Leaflet.
                $tile_layer = wpsl_get_shortcode_tile_layer( $atts['map_style'] );

                if ( $tile_layer ) {
                    $map_atts['tileLayer'] = $tile_layer;

                    /**
                     * Vector styles need the MapLibre GL scripts, which are only
                     * enqueued by default when the global style is a vector one.
                     * Flag it so the assets manager loads them for this page too.
                     */
                    if ( isset( $tile_layer['type'] ) && 'vector' === $tile_layer['type'] ) {
                        $this->state->add_script( 'maplibre' );
                    }
                }
            }
        }

        if ( isset( $atts['map_id'] ) && ! empty( $atts['map_id'] ) && 'gmaps' === $this->settings->get( 'api', 'active_map_service' ) ) {
            $map_atts['mapId'] = sanitize_text_field( $atts['map_id'] );
        }

        if ( isset( $atts['street_view'] ) ) {
            $map_atts['streetView'] = $this->atts_boolean( $atts['street_view'] );
        }

        if ( isset( $atts['scrollwheel'] ) ) {
            $map_atts['scrollWheel'] = $this->atts_boolean( $atts['scrollwheel'] );
        }

        if ( isset( $atts['control_position'] ) && ! empty( $atts['control_position'] ) && ( $atts['control_position'] == 'left' || $atts['control_position'] == 'right' ) ) {
            $map_atts['controlPosition'] = $atts['control_position'];
        }

        if ( isset( $atts['marker_cluster'] ) && $atts['marker_cluster'] !== '' ) {
            $atts['marker_clusters'] = $atts['marker_cluster'];
        }

        if ( isset( $atts['marker_clusters'] ) && $this->atts_boolean( $atts['marker_clusters'] ) ) {
            $map_atts['cluster'] = true;

            $this->assets_manager()->enqueue_cluster_styles();
        }

        /**
         * Whether the admin-defined map shapes are drawn on this map. Defaults
         * to true, [wpsl_map shapes="false"] turns them off for this map only.
         */
        if ( isset( $atts['shapes'] ) ) {
            $map_atts['shapes'] = $this->shapes_enabled( $atts['shapes'] );
        }

        return $map_atts;
    }

    /**
     * Read the shapes attribute of either shortcode.
     *
     * @since  3.0.0
     * @param  mixed $att  The attribute value
     * @return bool  $bool Whether the shapes are drawn on this map
     */
    private function shapes_enabled( $att ) {
        return filter_var( $att, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Build the marker image URLs for the [wpsl_map] store_marker /
     * active_marker attributes.
     *
     * @since  3.0.0
     * @param  array $atts        The shortcode attributes
     * @return array $marker_urls The store / active marker URLs, empty strings when the attribute isn't set
     */
    private function get_map_marker_urls( $atts ) {
        $marker_urls = [
            'store'  => '',
            'active' => '',
        ];

        $marker_atts = [
            'store'  => [ 'att' => 'store_marker',  'default' => 'blue' ],
            'active' => [ 'att' => 'active_marker', 'default' => 'dark-blue' ],
        ];

        foreach ( $marker_atts as $type => $details ) {
            if ( ! empty( $atts[ $details['att'] ] ) ) {
                $filename = $this->validate_marker( $atts[ $details['att'] ], $details['default'] );

                /**
                 * A custom marker is already a complete image source, used as-is
                 * by the JS (alternateMarkerUrl / categoryMarkerUrlActive): no
                 * directory prefix, no retina "@2x" rewrite.
                 */
                $data_uri = \wpsl_custom_marker_data_uri( \wpsl_custom_marker_id( $filename ) );

                if ( $data_uri ) {
                    $marker_urls[ $type ] = $data_uri;

                    continue;
                }

                /**
                 * Same retina handling as the global marker config and admin
                 * previews: variant and base file share a directory, either
                 * "@2x" spelling counts, standard file stands in when no
                 * variant exists on disk.
                 */
                $marker_urls[ $type ] = \wpsl_marker_retina_src( $filename );
            }
        }

        return $marker_urls;
    }

    /**
     * Set the attribute to either 1 or 0.
     *
     * @since  3.0.0
     * @param  string $att     The attribute val
     * @return int    $att_val Either 1 or 0
     */
    public function atts_boolean( $att ) {
        if ( $att === 'true' || absint( $att ) ) {
            $att_val = 1;
        } else {
            $att_val = 0;
        }

        return $att_val;
    }

    /**
     * Set the shortcode attribute to either 1 or 0.
     *
     * @since         2.0.0
     * @deprecated    3.0.0    Use WPSL\Frontend\Shortcodes\Controller::atts_boolean() instead.
     * @param  string $att The shortcode attribute val
     * @return void
     */
    public function shortcode_atts_boolean( $att ) {
        _deprecated_function( __FUNCTION__, '3.0.0', 'WPSL\\Frontend\\Shortcodes\\Controller::atts_boolean()' );
    }

    /**
     * Make sure the map style shortcode attributes are valid.
     *
     * The values are send to wp_localize_script in add_frontend_scripts.
     *
     * @since        2.0.0
     * @deprecated   3.0.0     Use WPSL\Frontend\Shortcodes\Controller::check_map_atts() instead.
     * @param  array $atts The map style shortcode attributes
     * @return void
     */
    public function check_map_shortcode_atts( $atts ) {
        _deprecated_function( __FUNCTION__, '3.0.0', 'WPSL\\Frontend\\Shortcodes\\Controller::check_map_atts()' );
    }
}