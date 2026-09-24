<?php
/**
 * Handle the script / style assets for the frontend.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Assets;

defined( 'ABSPATH' ) || exit;

use WPSL\Frontend\Assets\Resources;
use WPSL\Frontend\Templates\Manager as TemplatesManager;
use WPSL\Frontend\Store\Data as StoreData;
use WPSL\Frontend\State\Manager as StateManager;

use WPSL\Core\Container;
use WPSL\Core\UI\Theme_Styles;
use WPSL\Core\Settings\Manager as WpslSettings;

class Manager {

    /**
     * Resources object.
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Assets\Resources
     */
    private $resources;

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Theme styles instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\UI\Theme_Styles
     */
    private $theme_styles;

    /**
     * Templates manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Templates\Manager
     */
    private $templates_manager;

    /**
     * Store data instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Store\Data
     */
    private $store_data;

    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Frontend state manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;

    /**
     * Whether the front-end stylesheets were already enqueued this request.
     *
     * @since 3.0.0
     * @var   bool
     */
    private $styles_enqueued = false;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container             $container         Container instance
     * @param \WPSL\Core\Settings\Manager      $settings          Settings manager instance
     * @param \WPSL\Frontend\Assets\Resources  $resources         Resources instance
     * @param \WPSL\Core\UI\Theme_Styles       $theme_styles      Theme styles instance
     * @param \WPSL\Frontend\Templates\Manager $templates_manager Templates manager instance
     * @param \WPSL\Frontend\Store\Data        $store_data        Store data instance
     * @param \WPSL\Frontend\State\Manager     $state             Frontend state manager instance
     */
    public function __construct( Container $container, WpslSettings $settings, Resources $resources, Theme_Styles $theme_styles, TemplatesManager $templates_manager, StoreData $store_data, StateManager $state ) {
        $this->container = $container;
        $this->settings = $settings;
        $this->resources = $resources;
        $this->theme_styles = $theme_styles;
        $this->templates_manager = $templates_manager;
        $this->store_data = $store_data;
        $this->state = $state;
        
        add_filter( 'body_class', [ $this, 'add_frontend_body_class' ] );
    }

    /**
     * Add wpsl-v3 class to body on frontend pages.
     *
     * @since  3.0.0
     * @param  array $classes Current body classes
     * @return array Modified body classes
     */
    public function add_frontend_body_class( $classes ) {
        $classes[] = 'wpsl-v3';

        // Conditionally add wpsl-v3-css class - only if NOT disabled
        if ( ! $this->settings->get( 'tools', 'disable_v3_css' ) ) {
            $classes[] = 'wpsl-v3-css';
        }

        return $classes;
    }

    /**
     * Enqueue the Leaflet marker cluster styles for the osm / stadia services.
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_cluster_styles() {
        if ( ! in_array( $this->settings->get( 'api', 'active_map_service' ), [ 'osm', 'stadia' ], true ) ) {
            return;
        }

        if ( wp_style_is( 'wpsl-leaflet-markercluster', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style( 'wpsl-leaflet-markercluster', WPSL_URL . 'assets/vendor/leaflet/leaflet-markercluster.min.css', '', WPSL_VERSION_NUM );

        do_action( 'wpsl_inline_cluster_styles' );
    }

    /**
     * Enqueue the front-end stylesheets once per request.
     *
     * @since  3.0.0
     * @return void
     */
    public function ensure_styles() {
        if ( $this->styles_enqueued ) {
            return;
        }

        $this->styles_enqueued = true;

        $this->enqueue_styles();
    }

    /**
     * Enqueue the required frontend styles.
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_styles() {
        $wpsl_settings = $this->settings->get_all();
        
        // Load from dist folder for production, source folder for development
        $css_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $css_ext = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.css' : '.min.css';

        wp_enqueue_style( 'wpsl-fontello', WPSL_URL . $css_base . 'frontend/css/fontello' . $css_ext, [], WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-styles', WPSL_URL . $css_base . 'frontend/css/styles' . $css_ext, '', WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-common', WPSL_URL . $css_base . 'common/css/common' . $css_ext, '', WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-filters', WPSL_URL . $css_base . 'common/css/filters' . $css_ext, '', WPSL_VERSION_NUM );

        /*
         * The theme colors. With "Overwrite theme styles" on they load as a normal
         * stylesheet and win. With it off they load inside a cascade layer, so any
         * color the theme sets wins, whatever its specificity, and these only
         * color what the theme leaves alone. The layered copy only exists in dist.
         */
        if ( ! empty( $wpsl_settings['appearance']['overwrite_theme_styles'] ) ) {
            wp_enqueue_style( 'wpsl-colors', WPSL_URL . $css_base . 'common/css/colors' . $css_ext, [ 'wpsl-common' ], WPSL_VERSION_NUM );
        } else {
            wp_enqueue_style( 'wpsl-colors', WPSL_URL . 'assets/dist/common/css/colors-layered.min.css', [ 'wpsl-common' ], WPSL_VERSION_NUM );
        }
        wp_enqueue_style( 'wpsl-responsive', WPSL_URL . $css_base . 'common/css/responsive' . $css_ext, '', WPSL_VERSION_NUM );

        if ( in_array( $wpsl_settings['api']['active_map_service'], [ 'osm', 'stadia' ], true ) ) {
            if ( $this->resources->cluster_markers_active() ) {
                $this->enqueue_cluster_styles();
            }

            // The GDPR checkpoint loads the CDN stylesheets itself, after consent.
            if ( wpsl_get_gdpr_handler() === 'none' ) {
                $leaflet_assets = $this->resources->get_leaflet_assets();

                foreach ( $leaflet_assets['css'] as $asset ) {
                    wp_enqueue_style( $asset['handle'], $asset['url'], [], $asset['version'] );
                }

                add_filter( 'style_loader_tag', [ $this, 'add_style_integrity' ], 10, 2 );
            }
        } else if ( $wpsl_settings['api']['active_map_service'] == 'mapbox' && wpsl_get_gdpr_handler() === 'none' ) {
            $mapbox_assets = $this->resources->get_mapbox_assets();

            foreach ( $mapbox_assets['css'] as $asset ) {
                wp_enqueue_style( $asset['handle'], $asset['url'], [], $asset['version'] );
            }
        }
            
        $custom_styles = $this->theme_styles->build_custom_style();

        if ( ! empty( $custom_styles ) ) {
            wp_add_inline_style( 'wpsl-styles', $custom_styles );
        }
    }

    /**
     * Enqueue the required frontend scripts.
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_scripts() {
        $load_scripts = $this->state->get_load_scripts();

        // Only load the required js files on the store locator page or individual store pages.
        if ( empty( $load_scripts ) ) {
            return;
        }
                
        $wpsl_settings = $this->settings->get_all();
        
        // Load from dist folder for production, source folder for development
        $js_path = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/frontend/js/wpsl.js' : 'assets/dist/frontend/js/wpsl.min.js';

        // Register the scripts
        wp_enqueue_script( 'wpsl', WPSL_URL . $js_path, [ 'jquery', 'wp-hooks' ], WPSL_VERSION_NUM, true );

        // Add type="module" only for source files in development mode
        if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
            add_filter( 'script_loader_tag', function( $tag, $handle ) {
                if ( 'wpsl' === $handle ) {
                    return str_replace( '<script', '<script type="module"', $tag );
                }

                return $tag;
            }, 10, 2 );
        }

        // required to render the templates
        wp_enqueue_script( 'underscore' );

        /*
         * Runtime marker labels: the label text rules and the 
         * script that numbers the markers to match the result list.
         */
        $label_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $label_ext  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.js' : '.min.js';

        wp_enqueue_script( 'wpsl-marker-label', WPSL_URL . $label_base . 'common/wpsl-marker-label' . $label_ext, [], WPSL_VERSION_NUM, true );
        wp_enqueue_script( 'wpsl-marker-labels', WPSL_URL . $label_base . 'frontend/js/wpsl-marker-labels' . $label_ext, [ 'jquery', 'wp-hooks', 'wpsl', 'wpsl-marker-label' ], WPSL_VERSION_NUM, true );

        // Load the correct map script based on the active map service
        switch ( wpsl_get_active_map_service() ) {
            case 'gmaps':
                /**
                 * Check if we need to deregister other Google Maps scripts loaded
                 * by other plugins, or the current theme?
                 *
                 * This in some cases can break the store locator map.
                 */
                if ( $wpsl_settings['tools']['deregister_gmaps'] ) {
                    wpsl_deregister_other_gmaps();
                }
            
                if ( wpsl_get_gdpr_handler() == 'none' ) {
                    wp_add_inline_script( 'wpsl', wpsl_gmaps_bootstrap(), 'after' );
                    add_action( 'wp_footer', 'wpsl_gmaps_fire_callbacks', 25 );
                } elseif ( $this->resources->gdpr_defers_assets() ) {
                    add_action( 'wp_footer', 'wpsl_gmaps_publish_callbacks', 25 );
                }

                // Add the MarkerClusterer script if enabled
                if ( $this->resources->cluster_markers_active() ) {
                    //See https://github.com/googlemaps/js-markerclusterer 
                    wp_enqueue_script( 'wpsl-cluster', WPSL_URL . 'assets/vendor/markerclusterer/google/index.min.js', [ 'wpsl' ], '2.6.2', true );
                    wp_enqueue_script( 'wpsl-d3-color', WPSL_URL . 'assets/vendor/d3/d3-color.min.js', [ 'wpsl-cluster' ], '3.1.0', true );
                    wp_enqueue_script( 'wpsl-d3-interpolate', WPSL_URL . 'assets/vendor/d3/d3-interpolate.min.js', [ 'wpsl-cluster' ], '3.0.1', true );
                }
                break;
            case 'osm':
            case 'stadia':
                /**
                 * With a GDPR handler active nothing may hit the CDN before the
                 * visitor consents, so the whole Leaflet chain is deferred to
                 * the checkpoint, which loads it from wpslSettings.api.assets.
                 */
                if ( wpsl_get_gdpr_handler() !== 'none' ) {
                    break;
                }

                $assets = $this->resources->get_leaflet_assets();

                foreach ( $assets['js'] as $asset ) {
                    wp_enqueue_script( $asset['handle'], $asset['url'], $asset['deps'], $asset['version'], true );
                }

                add_filter( 'script_loader_tag', [ $this, 'add_script_integrity' ], 10, 2 );
                break;
            case 'mapbox':
                // Only load the Mapbox API if no GDPR consent handling is required
                if ( wpsl_get_gdpr_handler() === 'none' ) {
                    $assets = $this->resources->get_mapbox_assets();

                    foreach ( $assets['js'] as $asset ) {
                        wp_enqueue_script( $asset['handle'], $asset['url'], $asset['deps'], $asset['version'], true );
                    }
                }

                break;
            default:
                break;
        }

        wp_enqueue_script( 'jquery-ui-tabs' );

        // Get data from State Manager
        $store_map_data = $this->state->get_all_store_map_data();
        
        // Determine page type based on loaded scripts
        $page_type = in_array( 'store_locator', $load_scripts ) ? 'store_locator' : 'store_page';

        $localized_data = $this->resources->get_localized_data( $page_type );
    
        // Localize the scripts
        wp_localize_script( 'wpsl', 'wpslSettings', $localized_data );
        wp_localize_script( 'wpsl', 'wpslTemplateSections', $this->templates_manager->collect_sections( $page_type ) );

        // Both page types boot a map, and the key gate reads these messages.
        wp_localize_script( 'wpsl', 'wpslApiErrors', wpsl_api_error_messages() );

        if ( in_array( 'store_locator', $load_scripts ) ) {
            wp_localize_script( 'wpsl', 'wpslLabels', $this->resources->labels() );
            wp_localize_script( 'wpsl', 'wpslGeolocationErrors', $this->resources->geolocation_errors() );
        } elseif ( in_array( 'store_page', $load_scripts ) ) {
            wp_localize_script( 'wpsl', 'wpslLabels', [ 'clusterTitle' => $this->resources->cluster_label() ] );
        }

        //Add store map data if available.
        if ( ! empty( $store_map_data ) ) {
            foreach ( $store_map_data as $map_index => $map ) {
                wp_localize_script( 'wpsl', 'wpslMap_' . $map_index, $map );
            }
        }

        // Enqueue all scripts that were added to the load_scripts array via state manager
        foreach ( $this->state->get_load_scripts() as $script ) {
            wp_enqueue_script( $script );
        }
    }

    /**
     * Create the css rules based on the height / max-width that is set on the settings page.
     *
     * @since  1.0.0
     * @param  array $shortcode_atts Optional shortcode attributes to check for category filter type
     * @return string $css The custom css rules
     */
    public function get_custom_css( $shortcode_atts = [] ) {
        $thumb_size = $this->store_data->get_store_thumb_size();
        $appearance = $this->settings->get_group( 'appearance' );
        $dimensions = $this->settings->get( 'appearance', 'dimensions' );
        $search     = $this->settings->get_group( 'search' );

        // Get shortcode attributes from Shortcodes service if not passed as parameter
        if ( empty( $shortcode_atts ) ) {
            $shortcode_atts = $this->container->get( 'shortcodes' )->atts;
        }

        // Use the actual template being rendered, not just the settings
        $active_template = isset( $shortcode_atts['template'] ) && $shortcode_atts['template'] ? $shortcode_atts['template'] : $appearance['template_id'];

        $template_details = wpsl_get_service( 'template_loader' )->get_details( $active_template );
        $has_panel        = isset( $template_details['has_panel'] ) && $template_details['has_panel'];

        $css = '<style>' . "\r\n";

        if ( isset( $thumb_size[0] ) && is_numeric( $thumb_size[0] ) && isset( $thumb_size[1] ) && is_numeric( $thumb_size[1] ) ) {
            $css .= "\t" . "#wpsl-stores .wpsl-store-thumb { height:" . esc_attr( $thumb_size[0] ) . "px !important; width:" . esc_attr( $thumb_size[1] ) . "px !important; }" . "\r\n";
        }

        /**
         * If the category dropdown is enabled then we
         * make it the same width as the search input field.
         *
         * The enabled state and the filter type both honor the [wpsl]
         * shortcode attributes ( category_filter / category_filter_type ),
         * so this matches what is_category_enabled() actually renders.
         */
        $category_type = ( isset( $shortcode_atts['category_filter_type'] ) && $shortcode_atts['category_filter_type'] )
            ? $shortcode_atts['category_filter_type']
            : $search['category_filter_type'];

        if ( wpsl_get_service( 'template_filters' )->is_category_enabled() && $category_type == 'dropdown' ) {
            $cat_elem = ',#wpsl-category .wpsl-dropdown';
        } else {
            $cat_elem = '';
        }

        switch ( $active_template ) {
            case 'default':
                $css .= $this->get_search_input_css( $dimensions, $cat_elem );
                $css .= $this->get_combined_height_css( $dimensions );
                $css .= $this->get_mapbox_autocomplete_css( $search, $dimensions );
                break;
            case 'horizontal':
                $heights = wpsl_dimension_heights( $dimensions, 'horizontal' );

                $css .= $this->get_search_input_css( $dimensions, $cat_elem );
                $css .= "\t" . "#wpsl-map {height:" . esc_attr( $heights['map_height'] ) . "px !important;}" . "\r\n";

                if ( $heights['show_all_results'] ) {
                    $css .= "\t" . "#wpsl-stores, #wpsl-direction-details { height:auto !important; }" . "\r\n";
                } else {
                    $css .= "\t" . "#wpsl-stores, #wpsl-direction-details { height:" . esc_attr( $heights['results_height'] ) . "px !important; }" . "\r\n";
                }

                $css .= $this->get_mapbox_autocomplete_css( $search, $dimensions );
                break;
            case 'vertical':
                $css .= $this->get_panel_height_css( $dimensions );
                break;
            default:
                if ( $has_panel ) {
                    $css .= $this->get_panel_height_css( $dimensions );
                } else {
                    $css .= $this->get_search_input_css( $dimensions, $cat_elem );
                    $css .= $this->get_combined_height_css( $dimensions );
                }
                break;
        }
        
        // Add the GDPR artwork for the active map service whenever the map is
        // waiting on consent, whoever is asking for it.
        $gdpr = $this->settings->get_group( 'gdpr' );

        if ( in_array( wpsl_get_gdpr_handler(), [ 'wpsl', 'complianz' ], true ) ) {
            $map_service = wpsl_get_active_map_service();
            $gdpr_background = apply_filters( 'wpsl_gdpr_background', WPSL_URL . 'assets/img/frontend/gdpr/' . esc_attr( $map_service ) . '.gif' );

            if ( wpsl_get_gdpr_handler() == 'wpsl' ) {
                $css .= "\t" . ".wpsl-gdpr-content { background: url( '" . esc_url( $gdpr_background ) . "' ) center top no-repeat; background-size: cover; }" . "\r\n";
            } else {
                $canvas = '.wpsl-canvas-' . esc_attr( $map_service );
                $idle   = $canvas . '.cmplz-placeholder-element:not(.cmplz-activated)';

                $placeholder = $this->get_complianz_placeholder( $map_service, $gdpr_background );

                $css .= "\t" . $idle . " { background: url( '" . esc_url( $placeholder['url'] ) . "' ) center center no-repeat; background-size: " . $placeholder['size'] . "; display: flex; align-items: center; justify-content: center; }" . "\r\n";
                $css .= "\t" . $idle . " .cmplz-blocked-content-notice { max-width: 400px; padding: 20px; font-size: 14px; font-weight: normal; background-color: #fff; color: #000; border: none; border-radius: 3px; text-align: center; cursor: pointer; }" . "\r\n";
            }
        }
        
        $css .= '</style>' . "\r\n";

        return $css;
    }
    
    /**
     * Pick the artwork for the Complianz placeholder.
     *
     * @since  3.0.0
     * @param  string      $map_service The active map service.
     * @param  string|null $fallback    The checkpoint artwork URL, resolved here when omitted.
     * @return array{url: string, size: string}
     */
    public function get_complianz_placeholder( $map_service, $fallback = null ) {
        if ( null === $fallback ) {
            $fallback = apply_filters( 'wpsl_gdpr_background', WPSL_URL . 'assets/img/frontend/gdpr/' . esc_attr( $map_service ) . '.gif' );
        }

        if ( function_exists( 'cmplz_default_placeholder' ) ) {
            $placeholder = [
                'url'  => cmplz_default_placeholder( 'google-maps' ),
                'size' => 'cover',
            ];
        } else {
            $placeholder = [
                'url'  => $fallback,
                'size' => 'auto',
            ];
        }

        /**
         * Filter the Complianz placeholder artwork.
         *
         * @since 3.0.0
         * @param array  $placeholder url and background-size for the canvas
         * @param string $map_service The active map service
         */
        return apply_filters( 'wpsl_complianz_placeholder', $placeholder, $map_service );
    }

    /**
     * Get the height rule for templates where the map and the results share
     * one height, as they did in 2.x.
     *
     * @since  3.0.0
     * @param  array $dimensions The dimensions settings
     * @return string
     */
    private function get_combined_height_css( $dimensions ) {
        $heights = wpsl_dimension_heights( $dimensions, 'default' );

        if ( ! $heights['combined_height'] ) {
            return '';
        }

        return "\t" . "#wpsl-stores, #wpsl-direction-details, #wpsl-map { height:" . esc_attr( $heights['combined_height'] ) . "px !important; }" . "\r\n";
    }

    /**
     * Get the height rule for the panel templates.
     *
     * @since  3.0.0
     * @param  array $dimensions The dimensions settings
     * @return string
     */
    private function get_panel_height_css( $dimensions ) {
        $heights = wpsl_dimension_heights( $dimensions, 'vertical' );
        $css     = "\t" . ".wpsl-flex #wpsl-panel, .wpsl-flex #wpsl-stores, .wpsl-flex #wpsl-map { height:" . esc_attr( $heights['sl_height'] ) . "px !important; }" . "\r\n";

        /*
         * Up to 675px the panel sits above the map ( see responsive.css ), so a
         * fixed height leaves a large gap under a short results list. Let it
         * follow its content instead, capped at the saved height so a long list
         * still scrolls inside the panel.
         */
        $css .= "\t" . "@media (max-width: 675px) { #wpsl-wrap.wpsl-flex:not(.wpsl-full-page-template) #wpsl-panel { height:auto !important; max-height:" . esc_attr( $heights['sl_height'] ) . "px; } }" . "\r\n";

        return $css;
    }

    /**
     * Get CSS rules for search input and label widths.
     *
     * @since  3.0.0
     * @param  array  $dimensions          The dimensions settings
     * @param  string $cat_elem            The category element selector
     * @param  bool   $include_label_width Whether to output the label width rule. Default true.
     * @return string The CSS rules for search input and labels
     */
    private function get_search_input_css( $dimensions, $cat_elem, $include_label_width = true ) {
        $css = '';
        $search_width_mode = isset( $dimensions['search_width_mode'] ) ? $dimensions['search_width_mode'] : 'custom';

        if ( $include_label_width && $this->settings->get( 'tools', 'disable_v3_css' ) && isset( $dimensions['label_width'] ) && $dimensions['label_width'] ) {
            $css .= "\t" . ".wpsl-input label, #wpsl-radius label, #wpsl-category label { width:" . esc_attr( $dimensions['label_width'] ) . "px; }" . "\r\n";
        }

        /*
         * 'Default' is the v2 width ( 179px ), the same one a site gets that
         * never saved the mode. It used to print nothing, which left the field
         * on the browser's own input width.
         */
        $search_width = ( $search_width_mode === 'default' ) ? 179 : $dimensions['search_width'];

        $css .= "\t" . "#wpsl-search-input" . $cat_elem . " { width:" . esc_attr( $search_width ) . "px; }" . "\r\n";

        return $css;
    }

    /**
     * Get CSS rules for Mapbox autocomplete width.
     * Only outputs when Mapbox is the active map service AND autocomplete is enabled.
     *
     * @since  3.0.0
     * @param  array $search     The search settings
     * @param  array $dimensions The dimensions settings
     * @return string The CSS rules for Mapbox autocomplete or empty string
     */
    private function get_mapbox_autocomplete_css( $search, $dimensions ) {
        if ( $this->settings->get( 'api', 'active_map_service' ) !== 'mapbox' || ! $search['autocomplete'] ) {
            return '';
        }

        $search_width_mode = isset( $dimensions['search_width_mode'] ) ? $dimensions['search_width_mode'] : 'custom';
        $width             = ( ( $search_width_mode === 'default' ) ? 179 : absint( $dimensions['search_width'] ) ) . 'px';

        // Above the 570px breakpoint only, so it doesn't beat the full-width
        // rule responsive.css gives the geocoder on small screens.
        return "\t" . "@media (min-width: 571px) { #mapbox-autocomplete, #mapbox-autocomplete .mapboxgl-ctrl-geocoder--input, #mapbox-autocomplete .mapboxgl-ctrl-geocoder, #mapbox-autocomplete input[type=text] { width:" . esc_attr( $width ) . " !important; min-width:" . esc_attr( $width ) . " !important; max-width:" . esc_attr( $width ) . " !important; } }" . "\r\n";
    }

    /**
     * Collect the CSS classes that are placed on the outer store locator div.
     *
     * @since  2.0.0
     * @param  array  $shortcode_atts
     * @return string $classes The custom CSS rules
     */
    public function get_css_classes( $shortcode_atts = [] ) {
        $wpsl_settings = $this->settings->get_all();
        $appearance    = $this->settings->get_group( 'appearance' );
        $search        = $this->settings->get_group( 'search' );

        $classes     = [];
        $wp_template = get_option( 'template' );

        // Get shortcode attributes from Shortcodes service if not passed as parameter
        if ( empty( $shortcode_atts ) ) {
            $shortcode_atts = $this->container->get( 'shortcodes' )->atts;
        }

        if ( $appearance['template_id'] === 'horizontal' ) {
            $classes[] = 'wpsl-v3-result-columns';
        }

        /**
         * Resolve the effective filter state, so the layout classes honor the
         * [wpsl] shortcode attributes ( category_filter / radius_filter /
         * results_filter ) and match what the templates actually render,
         * instead of only looking at the settings page values.
         */
        $template_filters = wpsl_get_service( 'template_filters' );
        $category_enabled = $template_filters->is_category_enabled();
        $radius_enabled   = $template_filters->is_radius_enabled();
        $results_enabled  = $template_filters->is_results_enabled();

        // The effective category filter type ( shortcode attribute wins ).
        $category_type = ( isset( $shortcode_atts['category_filter_type'] ) && $shortcode_atts['category_filter_type'] )
            ? $shortcode_atts['category_filter_type']
            : $search['category_filter_type'];

        if ( $category_enabled && $results_enabled && ! $radius_enabled ) {
            $classes[] = 'wpsl-cat-results-filter';
        }

        // checkboxes class toevoegen?
        if ( ! $category_enabled && ! $results_enabled && ! $radius_enabled ) {
            $classes[] = 'wpsl-no-filters';
        }

        if ( $category_enabled && $category_type == 'checkboxes' ) {
            $classes[] = 'wpsl-checkboxes-enabled';
        }

        if ( $results_enabled && ! $category_enabled && ! $radius_enabled ) {
            $classes[] = 'wpsl-results-only';
        }

        // Adjust the styling of the store locator for the default WP 5.x themes.
        if ( $wp_template === 'twentynineteen' ) {
            $classes[] = 'wpsl-twentynineteen';
        }

        if ( $wp_template === 'twentytwenty' ) {
            $classes[] = 'wpsl-twentytwenty';
        }

        // If we only show the category filters, then we use this class to hide the input button.
        if ( $category_enabled && $search['category_filter_only'] ) {
            $classes[] = 'wpsl-cat-autosubmit';
        }

        if ( $category_enabled && $search['category_filter_only'] ) {
            $classes[] = 'wpsl-cat-filter-only';
        }

        $classes = apply_filters( 'wpsl_template_css_classes', $classes );

        if ( ! empty( $classes ) ) {
            return join( ' ', $classes );
        }
    }

    /**
     * Collect potential classes to be placed 
     * on the outer #wpsl-wrap div.
     * 
     * @since  3.0.0
     * @param  array $shortcode_atts The shortcode attributes. Falls back to the Shortcodes service when empty.
     * @return string|null The class attribute for the outer div, or null if no classes.
     */
    public function get_outer_class( $shortcode_atts = [] ) {
        $classes = $this->get_outer_classes( $shortcode_atts );

        if ( ! empty( $classes ) ) {
            return 'class="'. join( ' ', $classes ) .'"';
        }
    }

    /**
     * Collect the classes for the outer #wpsl-wrap div as a list.
     *
     * @since  3.0.0
     * @param  array $shortcode_atts The shortcode attributes. Falls back to the Shortcodes service when empty.
     * @return array The classes for the outer div.
     */
    public function get_outer_classes( $shortcode_atts = [] ) {
        $classes = [];

        // Get shortcode attributes from Shortcodes service if not passed as parameter
        if ( empty( $shortcode_atts ) ) {
            $shortcode_atts = $this->container->get( 'shortcodes' )->atts;
        }

        // Check if we need to use the shortcode template ID, or the one set on the WPSL settings page.
        if ( isset( $shortcode_atts['template'] ) && $shortcode_atts['template'] )  {
            $wpsl_template = $shortcode_atts['template'];
        } else {
            $wpsl_template = $this->settings->get( 'appearance', 'template_id' );
        }

        $classes[] = 'wpsl-' . $wpsl_template .'-template';

        $template_details = wpsl_get_service( 'template_loader' )->get_details( $wpsl_template );
        $has_panel        = isset( $template_details['has_panel'] ) && $template_details['has_panel'];

        if ( $has_panel ) {
            $classes[] = 'wpsl-flex';
            $classes[] = 'wpsl-has-panel';

            if ( $this->settings->get( 'map', 'show_credits' ) ) {
                $classes[] = 'wpsl-has-credits';
            }
        }

        /**
         * If the hours can not be expanded, 
         * we add the static class to change 
         * the icon location.
         */
        if ( ! $this->settings->get( 'ux', 'show_hour_status' ) ) {
            $classes[] = 'wpsl-static-hours';
        }

        /**
         * Check if we need to set the .wpsl-gdpr-checkpoint class
         * on the outer div to make sure the GDPR checkpoint is shown
         * in the correct location.
         */
        if ( in_array( wpsl_get_gdpr_handler(), [ 'wpsl', 'complianz' ], true ) ) {
            $classes[] = 'wpsl-gdpr-checkpoint';
        }

        /**
         * The credits belong to a loaded map, so they wait for one whoever is
         * asking for consent - Borlabs included, which gets no checkpoint class
         * because it blocks the map script itself and leaves the search and the
         * panel usable. Dropped again when the map bootstrap finishes.
         */
        if ( wpsl_get_gdpr_handler() !== 'none' && $this->settings->get( 'map', 'show_credits' ) ) {
            $classes[] = 'wpsl-credits-gated';
        }

        if ( $this->settings->get( 'api', 'active_map_service' ) == 'mapbox' && $this->settings->get( 'search', 'autocomplete' ) ) {
            $classes[] = 'wpsl-mapbox-autocomplete';
        }

        if ( $this->settings->get( 'api', 'active_map_service' ) == 'stadia' && $this->settings->get( 'search', 'autocomplete' ) ) {
            $classes[] = 'wpsl-stadia-autocomplete';
        }

        if ( $this->settings->get( 'search', 'input_only' ) ) {
            $classes[] = 'wpsl-search-input-only';
        }

        // Are we using icons?
        $icons_settings = $this->settings->get( 'appearance', 'icons' );

        if ( ! empty( $icons_settings['enabled'] ) ) {
            $classes[] = 'wpsl-has-icons';
        }

        // Are the action links styled as buttons / is the details link enabled?
        $cta_settings = $this->settings->get( 'appearance', 'cta' );

        if ( ! empty( $cta_settings['enabled'] ) ) {
            $classes[] = 'wpsl-styled-cta';
        }

        if ( ! empty( $cta_settings['details'] ) ) {
            $classes[] = 'wpsl-cta-details';
        }

        if ( $this->settings->get( 'search', 'search_method' ) === 'name' ) {
            $classes[] = 'wpsl-names';
        }

        return apply_filters( 'wpsl_template_outer_classes', $classes );
    }

    /**
     * Output the Mapbox autocomplete container if conditions are met.
     * 
     * Only outputs when Mapbox is the active map service and autocomplete is enabled.
     * 
     * @since  3.0.0
     * @return string The autocomplete div HTML or empty string
     */
    public function maybe_output_mapbox_autocomplete() {
        if ( $this->settings->get( 'api', 'active_map_service' ) === 'mapbox' && $this->settings->get( 'search', 'autocomplete' ) ) {
            return '<div id="mapbox-autocomplete"></div>';
        }

        return '';
    }

    /**
     * Add crossorigin to the Leaflet chain CDN styles so Subresource Integrity can be enforced.
     *
     * @since  3.0.0
     * @param  string $html   The link tag.
     * @param  string $handle The style handle.
     * @return string
     */
    public function add_style_integrity( $html, $handle ) {
        foreach ( $this->resources->get_leaflet_assets()['css'] as $asset ) {
            if ( $asset['handle'] === $handle && $asset['integrity'] ) {
                return str_replace( "rel='stylesheet'", "rel='stylesheet' integrity='" . esc_attr( $asset['integrity'] ) . "' crossorigin='anonymous'", $html );
            }
        }

        return $html;
    }

    /**
     * Add crossorigin to the Leaflet chain CDN scripts so Subresource Integrity can be enforced.
     *
     * @since  3.0.0
     * @param  string $tag    The script tag.
     * @param  string $handle The script handle.
     * @return string
     */
    public function add_script_integrity( $tag, $handle ) {
        foreach ( $this->resources->get_leaflet_assets()['js'] as $asset ) {
            if ( $asset['handle'] === $handle && $asset['integrity'] ) {
                return str_replace( '<script ', '<script integrity="' . esc_attr( $asset['integrity'] ) . '" crossorigin="anonymous" ', $tag );
            }
        }

        return $tag;
    }

    /**
     * Add a script to the load_scripts array via state manager.
     *
     * @since  3.0.0
     * @param  string $script The script handle to add
     * @return void
     */
    public function add_script( $script ) {
        $this->state->add_script( $script );
    }
}