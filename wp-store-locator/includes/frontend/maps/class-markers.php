<?php
/**
 * Handle the map markers.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Maps;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as Settings;
use WPSL\Frontend\State\Manager as StateManager;
use WPSL\Frontend\Shortcodes\Shortcodes;
use WPSL\Core\Container;

class Markers {

    /**
     * Holds the marker settings
     *
     * @since 3.0.0
     * @var   array
     */
    private $settings;

    /**
     * Holds the marker settings
     *
     * @since 3.0.0
     * @var   array
     */
    private $marker_settings;

    /**
     * Holds the state manager
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;

    /**
     * Holds the container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager  $settings  Settings manager instance
     * @param \WPSL\Frontend\State\Manager $state     Frontend state manager instance
     * @param \WPSL\Core\Container         $container Container instance
     */
    public function __construct( Settings $settings, StateManager $state, Container $container ) {
        $this->settings = $settings;
        $this->state = $state;
        $this->container = $container;
        $this->marker_settings = $settings->get_group( 'markers' );
    }

    /**
     * Get the used marker properties.
     *
     * @since  2.1.0
     * @link   https://developers.google.com/maps/documentation/javascript/3.exp/reference#Icon
     * @return array $marker_props The marker properties.
     */
    public function get_props() {
        $map_service = $this->settings->get( 'api', 'active_map_service' );
        $shortcodes  = $this->container->get( 'shortcodes' );

        // Check for shortcode marker overrides, otherwise use settings
        $start_marker  = $this->marker_settings['start_marker'];
        $store_marker  = $this->marker_settings['store_marker'];
        $active_marker = $this->marker_settings['active_marker'] ?? '';

        if ( isset( $shortcodes->atts['js']['markers']['start'] ) ) {
            $start_marker = $shortcodes->atts['js']['markers']['start'];
        }
        
        if ( isset( $shortcodes->atts['js']['markers']['store'] ) ) {
            $store_marker = $shortcodes->atts['js']['markers']['store'];
        }
        
        if ( isset( $shortcodes->atts['js']['markers']['active'] ) ) {
            $active_marker = $shortcodes->atts['js']['markers']['active'];
        }

        /**
         * The stored marker can be an empty string ( v2 saved '' when no
         * marker radio was included in the POST data and the v2 -> v3
         * migration copies the value unchanged ). Without a filename the
         * front-end would request a broken "@2x." marker URL, so fall back
         * to the shipped defaults.
         */
        if ( ! $start_marker ) {
            $start_marker = $this->settings->get_default( 'markers', 'start_marker' );
        }

        if ( ! $store_marker ) {
            $store_marker = $this->settings->get_default( 'markers', 'store_marker' );
        }

        // Runtime labels ( none / numbers / letters ): the shortcode attribute
        // outranks the setting, the same way the markers themselves do.
        $labels = \WPSL\Core\Markers\Marker_Label::sanitize_mode( $this->marker_settings['labels'] ?? 'none' );

        if ( isset( $shortcodes->atts['js']['markers']['labels'] ) ) {
            $labels = \WPSL\Core\Markers\Marker_Label::sanitize_mode( $shortcodes->atts['js']['markers']['labels'] );
        }

        $marker = [
            'start'          => $this->resolve_marker( $start_marker, 'start_marker' ),
            'store'          => $this->resolve_marker( $store_marker, 'store_marker' ),
            'skipStart'      => false,
            'scaledSize'     => [ 24, 35 ],
            'origin'         => [ 0, 0 ],
            'anchor'         => [ 12, 35 ],
            'markerClusters' => $this->cluster_active(),
            'startOnTop'     => ! empty( $this->marker_settings['start_marker_on_top'] ),
            'labels'         => $labels,

            /**
             * Bounce hover effect (Mapbox / Leaflet). Vertical travel in pixels
             * and the duration of one full up-and-down bounce in milliseconds.
             * Both can be overwritten through the wpsl_marker_props filter.
             */
            'bounceHeight'   => 12,
            'bouncePeriod'   => 500,
        ];

        /**
         * Only include the active marker if it actually differs from the store
         * marker.
         */
        if ( $active_marker ) {
            $active = $this->resolve_marker( $active_marker, 'active_marker' );

            if ( $active !== $marker['store'] ) {
                $marker['active'] = $active;
            }
        }

        /*
         * A bundled pin image cannot carry a runtime label, so while labels
         * are on the Studio's default pin stands in for it -- and for the
         * active state too, so a labelled marker still changes when picked.
         *
         * A marker image of the site's own ( a wpsl_admin_marker_dir folder )
         * was picked on purpose, so it stays. Its markers go unlabelled, the
         * result list keeps its badges.
         */
        $store_is_own_image = ( 0 !== strpos( (string) $marker['store'], 'data:' ) ) && ! \wpsl_marker_is_bundled( $store_marker );

        if ( 'none' !== $labels && ! $store_is_own_image ) {
            $store_is_custom = ( 0 === strpos( (string) $marker['store'], 'data:' ) );

            /*
             * [wpsl_map] markers never get a label, so they keep the pins
             * set on the settings page. Without an active marker of its own
             * the store pin doubles as it, so picking one changes nothing.
             */
            $unlabelled = [
                'store'  => $marker['store'],
                'active' => $marker['active'] ?? $marker['store'],
            ];

            if ( ! $store_is_custom ) {
                $marker['store'] = \WPSL\Core\Markers\Custom_Markers::get_label_fallback_uri( 'store' );
            }

            if ( isset( $marker['active'] ) ) {
                if ( 0 !== strpos( (string) $marker['active'], 'data:' ) && \wpsl_marker_is_bundled( $active_marker ) ) {
                    $marker['active'] = \WPSL\Core\Markers\Custom_Markers::get_label_fallback_uri( 'active' );
                }
            } elseif ( ! $store_is_custom ) {
                $marker['active'] = \WPSL\Core\Markers\Custom_Markers::get_label_fallback_uri( 'active' );
            }

            if ( $unlabelled['store'] !== $marker['store'] || $unlabelled['active'] !== ( $marker['active'] ?? $marker['store'] ) ) {
                $marker['unlabelled'] = $unlabelled;
            }
        }

        // Add provider-specific marker settings
        if ( $map_service == 'osm' || $map_service == 'stadia' ) {
            $marker['iconAnchor'] = [ 12, 35 ];

            /*
             * Where Leaflet hangs the popup, for the bundled pins. Same rule a
             * custom marker gets from its own artwork in getCustomMarkerGeometry():
             * the pin's 35px height, 4px of gap, less the pixel Leaflet's own
             * placement works out to.
             */
            $marker['popupAnchor'] = [ 0, -38 ];
        }

        /**
         * We are using layers with Geojson to place the markers on the map.
         * 
         * This means we need to add a workaround to add keyboard accessible 
         * support since the markers are not part of the DOM.
         * 
         * - Layers = better performance for large number of markers and supports clustering, but can not be keyboard accessible
         * - HTML markers = better accessibility, but no build in support for clustering and
         * not great for 100+ markers.
         */
        if ( $map_service == 'mapbox' ) {
            $marker['keyboardAccessible'] = apply_filters( 'wpsl_mapbox_keyboard_accessible', true );
        }

        /**
         * If this is not defined, the url path will default to
         * the url path of the WPSL plugin folder + /img/markers/
         * in the wpsl-gmap.js.
         */
        if ( defined( 'WPSL_MARKER_URI' ) ) {
            $marker['url'] = WPSL_MARKER_URI;

            // Custom marker files carry no size of their own, so read each one's dimensions, keyed by src.
            $geometry = $this->file_marker_geometry( $marker );

            if ( $geometry ) {
                $marker['geometry'] = $geometry;
            }
        }

        /**
         * Include the cluster properties when the locator map clusters, or
         * when any [wpsl_map] on the page requested clusters through its
         * shortcode. The properties are style / behavior config only — the
         * per-map enable flags are markers.markerClusters ( locator ) and
         * wpslMap_x.shortCode.cluster ( basic maps ).
         */
        if ( $this->cluster_active() || $this->basic_map_clusters_active() ) {
            $marker['cluster'] = $this->get_cluster_props();
        }

        return apply_filters( 'wpsl_marker_props', $marker );
    }

    /**
     * Check if any [wpsl_map] shortcode on the page enabled marker
     * clusters through its marker_clusters attribute.
     *
     * @since  3.0.0
     * @return bool
     */
    public function basic_map_clusters_active() {
        foreach ( (array) $this->state->get_all_store_map_data() as $map_data ) {
            if ( ! empty( $map_data['shortCode']['cluster'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the cluster marker properties
     *
     * @since  3.0.0
     * @return array $marker The cluster marker properties
     */
    public function get_cluster_props() {
        $cluster_zoom = $this->marker_settings['cluster_zoom'] ?? '';
        $cluster_size = $this->marker_settings['cluster_size'] ?? '';
        $cluster_style = $this->marker_settings['cluster_style'] ?? 'interpolation';
        $cluster_exclude_start = $this->marker_settings['cluster_exclude_start'] ?? 0;
        $map_service  = $this->settings->get( 'api', 'active_map_service' );

        switch ( $map_service ) {
            case 'gmaps':
                $marker['style']              = $cluster_style;
                $marker['excludeStartMarker'] = $cluster_exclude_start;
                $marker['zoom']               = ( ! $cluster_zoom ) ? 16 : $cluster_zoom;
                $marker['size']               = ( ! $cluster_size ) ? 40 : $cluster_size;

                // Get colors from settings, with defaults
                $low_density_color  = $this->marker_settings['cluster_low_density_color'] ?? '#0000ff';
                $high_density_color = $this->marker_settings['cluster_high_density_color'] ?? '#ff0000';
                $label_color        = $this->marker_settings['cluster_label_color'] ?? '#ffffff';

                // Allow filter override for backward compatibility
                $cluster_colors = apply_filters( 'wpsl_cluster_marker_colors', [
                    'low_density_color'  => $low_density_color,
                    'high_density_color' => $high_density_color,
                    'label_color'        => $label_color,
                ] );

                $marker['lowDensityColor']  = $cluster_colors['low_density_color'];
                $marker['highDensityColor'] = $cluster_colors['high_density_color'];
                $marker['labelColor']       = $cluster_colors['label_color'];

                // Cluster SVG templates, the ${} placeholders are replaced in JS at render time.
                $marker['templates'] = $this->get_cluster_templates();

                /*
                 * The label sizes are calibrated against the default circle
                 * templates ( font-size 50 at size 50 for the default style,
                 * 38 at size 75 for interpolation ). A custom shape scales
                 * its 240 unit viewBox to its own size, so the label size is
                 * normalized against that to keep the rendered count the same
                 * as with the default circles.
                 */
                $style_key = ( $cluster_style == 'interpolation' ) ? 'interpolation' : 'default';
                $base      = ( $style_key == 'interpolation' ) ? array( 'label' => 38, 'size' => 75 ) : array( 'label' => 50, 'size' => 50 );

                $template_size = $marker['templates'][ $style_key ]['size'] ?? $base['size'];

                if ( ! is_numeric( $template_size ) || ! $template_size ) {
                    $template_size = $base['size'];
                }

                $marker['labelSize'] = (string) round( $base['label'] * $base['size'] / $template_size );

                break;
            case 'mapbox':
                $marker['excludeStartMarker'] = $cluster_exclude_start;
                $marker['maxZoom'] = ( ! $cluster_zoom ) ? 14 : $cluster_zoom;
                $marker['radius']  = ( ! $cluster_size ) ? 20 : $cluster_size;

                // Use step expressions (https://docs.mapbox.com/style-spec/reference/expressions/#step)
                // with three steps to implement three types of circles:
                // * Blue, 20px circles when point count is less than 100
                // * Yellow, 30px circles when point count is between 100 and 750
                $marker['circle'] = [
                    'color' => [
                        '#51bbd6', // blue
                        100,
                        '#f1f075', // yellow
                        750,
                        '#f28cb1' // pink
                    ],
                    'radius' => [
                        20,
                        100,
                        30,
                        750,
                        40
                    ]
                ];

                break;
            case 'osm':
            case 'stadia':
                //@see https://github.com/Leaflet/Leaflet.markercluster#options
                $marker = [
                    'spiderfyOnMaxZoom'   => true,
                    'showCoverageOnHover' => false,
                    'zoomToBoundsOnClick' => true,
                    'excludeStartMarker'  => $cluster_exclude_start,
                ];

                // If nothing is set, it will default to 80.
                if ( $cluster_size ) {
                    $marker['maxClusterRadius'] = $cluster_size;
                }
                /**
                 * By default, OpenStreetMaps will automatically
                 * adjust the visible markers based on the total number
                 * of markers on the map and the current zoom level.
                 */
                if ( $cluster_zoom ) {
                    $marker['disableClusteringAtZoom'] = $cluster_zoom;
                }

                break;
        }

        return apply_filters( 'wpsl_cluster_marker_props', $marker );
    }

    /**
     * Get the cluster marker SVG templates ( Google Maps only ).
     *
     * The templates support the ${color}, ${labelColor}, ${labelSize} and
     * ${count} placeholders, they are replaced with the actual values in
     * JS when the cluster marker is rendered on the map.
     *
     * The 'size' value is the width/height in pixels the SVG is scaled
     * to on the map.
     *
     * If a custom marker shape is selected on the settings page, then
     * the shape template is used for both cluster styles.
     *
     * @since  3.0.0
     * @return array $templates The cluster SVG templates keyed by the cluster style
     */
    public function get_cluster_templates() {
        $defaults = [
            'default' => [
                'svg'  => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" opacity=".6" r="70" /><circle cx="120" cy="120" opacity=".3" r="90" /><circle cx="120" cy="120" opacity=".2" r="110" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
                'size' => 50,
            ],
            'interpolation' => [
                'svg'  => '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" opacity=".8" r="70" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
                'size' => 75,
            ],
        ];

        $templates = $defaults;

        // Check if a custom marker shape is selected instead of the default circles.
        $selected_shape = $this->marker_settings['cluster_marker_shape'] ?? 'default';

        if ( 'default' !== $selected_shape ) {
            $shapes = wpsl_get_cluster_marker_shapes();

            if ( isset( $shapes[ $selected_shape ]['svg'] ) ) {
                $shape_template = [
                    'svg'  => $shapes[ $selected_shape ]['svg'],
                    'size' => $shapes[ $selected_shape ]['size'] ?? 50,
                ];

                $templates['default']       = $shape_template;
                $templates['interpolation'] = $shape_template;
            }
        }

        $templates = apply_filters( 'wpsl_cluster_marker_templates', $templates );

        /**
         * Fall back to the default circles if a filter or custom shape
         * supplied broken or missing SVG code for one of the cluster styles.
         */
        foreach ( $defaults as $style => $default_template ) {
            if ( ! isset( $templates[ $style ]['svg'] ) || ! wpsl_is_valid_cluster_svg( $templates[ $style ]['svg'] ) ) {
                $templates[ $style ] = $default_template;
            }
        }

        return $templates;
    }

    /**
     * Check if the cluster markers are active.
     *
     * @since  3.0.0
     * @return bool $active Whether or not the cluster markers are active.
     */
    public function cluster_active() {
        $shortcodes = $this->container->get( 'shortcodes' );

        // Check if marker_clusters (or singular marker_cluster) is explicitly set via shortcode (not empty string)
        $marker_clusters_val = '';
        
        if ( array_key_exists( 'marker_clusters', $shortcodes->atts ) && $shortcodes->atts['marker_clusters'] !== '' ) {
            $marker_clusters_val = $shortcodes->atts['marker_clusters'];
        } elseif ( array_key_exists( 'marker_cluster', $shortcodes->atts ) && $shortcodes->atts['marker_cluster'] !== '' ) {
            $marker_clusters_val = $shortcodes->atts['marker_cluster'];
        }

        if ( $marker_clusters_val !== '' ) {
            return filter_var( $marker_clusters_val, FILTER_VALIDATE_BOOLEAN );
        }

        // Fall back to settings page value
        return (bool) $this->marker_settings['marker_clusters'];
    }

    /**
     * Resolve a stored marker setting to the value the front-end should load.
     *
     * A bundled marker is a filename; the JS prefixes it with the marker
     * directory URL, so only the retina variant needs resolving. A Marker
     * Manager marker is "custom:{id}" with no file on disk, so it is replaced
     * by its inline SVG data URI ( skipping create_retina_filename(), which
     * would turn it into "custom:{id}@2x." ).
     *
     * A deleted custom marker falls back to the shipped default for that slot.
     * A bundled filename outside the JS-prefixed directory is turned into a
     * complete URL by wpsl_marker_js_value() ( e.g. when wpsl_admin_marker_dir
     * points at its own folder ).
     *
     * @since 3.0.0
     * @param  string $value       The stored ( or shortcode supplied ) marker value.
     * @param  string $default_key The markers settings key holding this slot's default.
     * @return string              A marker filename, a marker URL, or an SVG data URI.
     */
    private function resolve_marker( $value, $default_key ) {
        $custom_id = \wpsl_custom_marker_id( $value );

        if ( $custom_id ) {
            $data_uri = \wpsl_custom_marker_data_uri( $custom_id );

            if ( $data_uri ) {
                return $data_uri;
            }

            $value = $this->settings->get_default( 'markers', $default_key );
        }

        return \wpsl_marker_js_value( $this->create_retina_filename( $value ), $value );
    }

    /**
     * Create a filename with @2x in it for the selected marker color.
     *
     * E.g. green.png becomes green@2x.png for retina devices. SVG markers are
     * resolution independent, so they have no @2x variant and are returned
     * unchanged.
     *
     * The naming lives in wpsl_marker_retina_filename() now, so the admin side
     * spells a retina file the same way; this stays as the method add-ons call.
     *
     * @since  1.0.0
     * @param  string $filename The name of the seleted marker
     * @return string $filename The filename with @2x added to the end
     */
    public function create_retina_filename( $filename ) {
        return \wpsl_marker_retina_filename( $filename );
    }

    /**
     * The dimensions of every file-based custom marker in use.
     *
     * @since  3.0.0
     * @param  array $marker The marker props built so far.
     * @return array         src => [ width, height ].
     */
    private function file_marker_geometry( $marker ) {
        $files    = \wpsl_marker_files();
        $base_url = trailingslashit( $marker['url'] );
        $geometry = [];

        foreach ( [ $marker['start'], $marker['store'], $marker['active'] ?? '' ] as $filename ) {
            if ( ! is_string( $filename ) || '' === $filename || preg_match( '/^(data:|https?:|\/\/)/i', $filename ) ) {
                continue;
            }

            $size = $this->marker_display_size( $filename, $files );

            if ( ! $size ) {
                continue;
            }

            // The symbol layers request the retina spelling, Leaflet strips
            // @2x first; both names get the same box.
            foreach ( array_unique( [ $filename, $this->marker_base_filename( $filename ) ] ) as $name ) {
                $geometry[ $base_url . $name ] = $size;
            }
        }

        return $geometry;
    }

    /**
     * The size a marker file's artwork displays at.
     *
     * @since  3.0.0
     * @param  string $filename The resolved marker filename.
     * @param  array  $files    Every marker file on disk, keyed by filename.
     * @return array[]|array    [ width, height ], or [] when it can't be read.
     */
    private function marker_display_size( $filename, $files ) {
        $base = $this->marker_base_filename( $filename );

        if ( isset( $files[ $base ] ) && ! \wpsl_marker_is_bundled( $base ) ) {
            $size = $this->marker_file_size( $base, $files );

            if ( $size ) {
                return $size;
            }
        }

        if ( $base === $filename || ! isset( $files[ $filename ] ) || \wpsl_marker_is_bundled( $filename ) ) {
            return [];
        }

        // Variant-only folder: the variant's pixels are the base artwork's at 2x.
        $size = $this->marker_file_size( $filename, $files );

        if ( ! $size ) {
            return [];
        }

        return [
            'width'  => (int) round( $size['width'] / 2 ),
            'height' => (int) round( $size['height'] / 2 ),
        ];
    }

    /**
     * The pixel dimensions of a marker file, raster or SVG.
     *
     * @since  3.0.0
     * @param  string $filename The marker filename.
     * @param  array  $files    Every marker file on disk, keyed by filename.
     * @return array[]|array    [ width, height ], or [] when it can't be read.
     */
    private function marker_file_size( $filename, $files ) {
        $path = $files[ $filename ] . $filename;

        if ( \wpsl_marker_is_svg( $filename ) ) {
            $markup = (string) @file_get_contents( $path, false, null, 0, 4096 );

            if ( preg_match( '/<svg[^>]*\bwidth="(\d+(?:\.\d+)?)"[^>]*\bheight="(\d+(?:\.\d+)?)"/i', $markup, $match ) ) {
                return [
                    'width'  => (float) $match[1],
                    'height' => (float) $match[2],
                ];
            }

            return [];
        }

        $size = wp_getimagesize( $path );

        if ( ! $size ) {
            return [];
        }

        return [
            'width'  => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    /**
     * The base filename of a marker, its retina suffix removed.
     *
     * @since  3.0.0
     * @param  string $filename The marker filename, variant or not.
     * @return string           The filename without a retina suffix.
     */
    private function marker_base_filename( $filename ) {
        if ( \wpsl_marker_is_svg( $filename ) ) {
            return $filename;
        }

        $name = pathinfo( $filename, PATHINFO_FILENAME );

        if ( '@2x' === substr( $name, -3 ) ) {
            $name = substr( $name, 0, -3 );
        } elseif ( '2x' === substr( $name, -2 ) ) {
            $name = substr( $name, 0, -2 );
        }

        return $name . '.' . pathinfo( $filename, PATHINFO_EXTENSION );
    }
}