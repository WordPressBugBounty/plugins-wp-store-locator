<?php
/**
 * Map Shapes admin: AJAX persistence and editor assets.
 *
 * @since 3.0.0
 */

namespace WPSL\Admin\Map_Shapes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Shapes\Repository;
use WPSL\Core\Shapes\Sanitizer;

class Manager {

    /**
     * The last-resort map centre, in numeric form.
     *
     * Googleplex, the same coordinates
     * Admin\Assets\Resources::get_default_lat_lng() falls back to as the string
     * '37.4220, -122.0840'.
     *
     * @since 3.0.0
     */
    const DEFAULT_LAT = 37.4220;
    const DEFAULT_LNG = -122.0840;

    /**
     * Shapes storage.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Shapes\Repository
     */
    private $repository;

    /**
     * GeoJSON sanitizer.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Shapes\Sanitizer
     */
    private $sanitizer;

    /**
     * Class constructor.
     *
     * @since 3.0.0
     * @param \WPSL\Core\Shapes\Repository $repository Shapes storage.
     * @param \WPSL\Core\Shapes\Sanitizer  $sanitizer  GeoJSON sanitizer.
     */
    public function __construct( Repository $repository, Sanitizer $sanitizer ) {
        $this->repository = $repository;
        $this->sanitizer  = $sanitizer;

        add_action( 'wp_ajax_wpsl_save_map_shapes', [ $this, 'save_shapes' ] );
        add_action( 'wp_ajax_wpsl_map_shapes_geocode', [ $this, 'geocode_place' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'admin_body_class', [ $this, 'body_class' ] );
    }

    /**
     * Mark the full-width layout on the body.
     *
     * @since  3.0.0
     * @param  string $classes Space-separated body classes.
     * @return string
     */
    public function body_class( $classes ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only, no state changes
        if ( isset( $_GET['page'] ) && 'wpsl_map_shapes' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
            $classes .= ' wpsl-full-page';
        }

        return $classes;
    }

    /**
     * Store the editor's FeatureCollection payload.
     *
     * @since  3.0.0
     * @return void
     */
    public function save_shapes() {
        check_ajax_referer( 'wpsl_map_shapes_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to manage map shapes.', 'wp-store-locator' ) ] );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated per field in Sanitizer::sanitize_collection().
        $raw = ( isset( $_POST['collection'] ) && is_string( $_POST['collection'] ) ) ? json_decode( wp_unslash( $_POST['collection'] ), true ) : null;

        $collection = $this->sanitizer->sanitize_collection( $raw );

        if ( is_wp_error( $collection ) ) {
            wp_send_json_error( [ 'message' => $collection->get_error_message() ] );
        }

        $this->repository->save_collection( $collection );

        wp_send_json_success( [ 'collection' => $collection ] );
    }

    /**
     * Geocode the editor's place search.
     *
     * @since  3.0.0
     * @return void
     */
    public function geocode_place() {
        check_ajax_referer( 'wpsl_map_shapes_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to manage map shapes.', 'wp-store-locator' ) ] );
        }

        $address = isset( $_POST['address'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['address'] ) ) ) : '';

        if ( '' === $address ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Enter a place to search for.', 'wp-store-locator' ) ] );
        }

        $latlng = $this->geocode_address( $address );

        if ( ! $latlng ) {
            wp_send_json_error( [ 'message' => esc_html__( 'No place was found for that search.', 'wp-store-locator' ) ] );
        }

        wp_send_json_success( $latlng );
    }

    /**
     * Turn a free-form place into coordinates with the configured geocoder.
     *
     * @since  3.0.0
     * @param  string     $address The place to geocode.
     * @return array|null [ 'lat' => float, 'lng' => float ], or null if nothing matched.
     */
    private function geocode_address( $address ) {
        $active   = wpsl_get_active_map_service();
        $response = wpsl_call_geocode_api( $address, $active, [ 'force_map_service' => $active ] );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            return null;
        }

        switch ( wpsl_get_geocoding_service() ) {
            case 'gmaps':
                // Geocoding API v4, which answers with a flat location object.
                if ( isset( $body['results'][0]['location']['latitude'], $body['results'][0]['location']['longitude'] ) ) {
                    return [
                        'lat' => (float) $body['results'][0]['location']['latitude'],
                        'lng' => (float) $body['results'][0]['location']['longitude'],
                    ];
                }

                break;
            case 'osm':
                // Nominatim: a bare list, and "lon" rather than "lng".
                if ( isset( $body[0]['lat'], $body[0]['lon'] ) ) {
                    return [
                        'lat' => (float) $body[0]['lat'],
                        'lng' => (float) $body[0]['lon'],
                    ];
                }

                break;
            case 'mapbox':
            case 'stadia':
                // Both answer GeoJSON, so both are [ lng, lat ].
                if ( isset( $body['features'][0]['geometry']['coordinates'][1] ) ) {
                    $coordinates = $body['features'][0]['geometry']['coordinates'];

                    return [
                        'lat' => (float) $coordinates[1],
                        'lng' => (float) $coordinates[0],
                    ];
                }

                break;
        }

        return null;
    }

    /**
     * Enqueue the editor assets on the Map Shapes page only.
     *
     * @since  3.0.0
     * @param  string $hook_suffix The current admin page's hook suffix.
     * @return void
     */
    public function enqueue_assets( $hook_suffix ) {
        if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'wpsl_map_shapes' ) {
            return;
        }

        $settings    = wpsl_get_service( 'wpsl_settings' );
        $map_service = $settings->get( 'api', 'active_map_service' );
        $adapter     = $this->drawing_adapter( $map_service );
        $editor_deps = [ 'jquery', 'wpsl-color-picker' ];

        // Load from dist folder for production, source folder for development
        $css_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $css_ext  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.css' : '.min.css';
        $js_base  = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $js_ext   = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.js' : '.min.js';

        $adapter_deps = [ 'wpsl-shapes-editor', 'wpsl-shapes-terra-shared' ];

        switch ( $adapter ) {
            case 'gmaps':
                $this->enqueue_terra_draw( 'gmaps' );
                $editor_deps[]  = 'wpsl-admin';
                $adapter_deps[] = 'wpsl-terra-draw-gmaps';
                break;
            case 'mapbox':
                $this->enqueue_mapbox();
                $this->enqueue_terra_draw( 'mapbox' );
                $editor_deps[]  = 'wpsl-mapbox';
                $adapter_deps[] = 'wpsl-terra-draw-mapbox';
                break;
            default:
                $tile_layer = $this->tile_layer_config( $map_service );

                $this->enqueue_leaflet( $tile_layer );
                $this->enqueue_terra_draw( 'osm' );

                $editor_deps[]  = 'wpsl-leaflet';
                $adapter_deps[] = 'wpsl-geoman';
                $adapter_deps[] = 'wpsl-terra-draw-leaflet';

                /*
                 * A vector style is drawn through the MapLibre bridge, so the
                 * adapter must not run before it exists.
                 */
                if ( $this->is_vector( $tile_layer ) ) {
                    $adapter_deps[] = 'wpsl-maplibre-gl-leaflet';
                }

                break;
        }

        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_style( 'wp-jquery-ui-dialog' );

        wp_enqueue_style( 'wpsl-colorpicker', WPSL_URL . $css_base . 'admin/css/colorpicker' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_script( 'wpsl-color-picker', WPSL_URL . $js_base . 'admin/js/modules/wpsl-color-picker' . $js_ext, [ 'jquery' ], WPSL_VERSION_NUM, true );

        wp_enqueue_style( 'wpsl-map-shapes', WPSL_URL . $css_base . 'admin/css/map-shapes' . $css_ext, [], WPSL_VERSION_NUM );

        $shapes_base = $js_base . 'admin/js/map-shapes/';

        wp_enqueue_script( 'wpsl-shapes-editor', WPSL_URL . $shapes_base . 'wpsl-shapes-editor' . $js_ext, $editor_deps, WPSL_VERSION_NUM, true );

        wp_enqueue_script( 'wpsl-shapes-terra-shared', WPSL_URL . $shapes_base . 'wpsl-shapes-terra-shared' . $js_ext, [], WPSL_VERSION_NUM, true );
        wp_enqueue_script( 'wpsl-shapes-draw-' . $adapter, WPSL_URL . $shapes_base . 'wpsl-shapes-draw-' . $adapter . $js_ext, $adapter_deps, WPSL_VERSION_NUM, true );
        wp_localize_script( 'wpsl-shapes-editor', 'wpslMapShapes', $this->get_editor_data( $settings, $map_service, $adapter, isset( $tile_layer ) ? $tile_layer : null ) );

        wp_enqueue_script( 'wpsl-shapes-layout', WPSL_URL . $shapes_base . 'wpsl-shapes-layout' . $js_ext, [ 'wpsl-shapes-editor', 'wpsl-shapes-draw-' . $adapter ], WPSL_VERSION_NUM, true );
    }

    /**
     * The data the editor's JS runs on.
     *
     * @since  3.0.0
     * @param  \WPSL\Core\Settings\Manager $settings    The settings service.
     * @param  string                     $map_service The configured provider.
     * @param  string                     $adapter     The drawing adapter in use.
     * @param  array|null                 $tile_layer  The Leaflet tile config, already
     *                                                 built by the caller; null for the
     *                                                 adapters that draw their own tiles.
     * @return array
     */
    private function get_editor_data( $settings, $map_service, $adapter, $tile_layer = null ) {
        $data = [
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'wpsl_map_shapes_nonce' ),
            'collection'   => $this->repository->get_collection(),
            'mapService'   => $map_service,
            'drawAdapter'  => $adapter,
            'startLatLng'  => $this->get_start_lat_lng(),
            'distanceUnit' => $settings->get( 'search', 'distance_unit' ),
            'l10n'         => $this->get_l10n(),
        ];

        /*
         * Google Maps / Mapbox / Stadia can't render anything without a valid key, so the
         * editor shows the same key-required box the other admin maps do
         * instead of handing the adapter a map that stays blank. Same
         * missing-or-flagged-invalid rule as hasValidApiKey() in the JS.
         */
        if ( in_array( $map_service, [ 'gmaps', 'mapbox', 'stadia' ], true ) ) {
            if ( 'gmaps' === $map_service ) {
                $key     = trim( (string) $settings->get( 'api', 'gmaps_browser_key' ) );
                $blocked = ( ! $key || get_option( 'wpsl_valid_gmaps_browser_key' ) != '1' );
                $message = wpsl_admin_api_key_missing_message();
            } else {
                $key     = trim( (string) $settings->get( 'api', $map_service . '_key' ) );
                $blocked = ( ! $key || get_option( 'wpsl_valid_' . $map_service . '_key' ) != '1' );
                $message = wpsl_admin_key_required_message( $map_service );
            }

            $data['keyGate'] = [
                'blocked' => $blocked,
                'message' => $message,
            ];
        }

        if ( 'mapbox' === $adapter ) {
            // Same key the admin's own map config reads ( Assets\Resources::get_settings() ).
            $data['accessToken'] = $settings->get( 'api', 'mapbox_key' );

            // The style the appearance page configured; without it the adapter
            // falls back to its own default and the editor shows a different
            // map than the frontend.
            $data['mapStyle'] = wpsl_active_mapbox_style();
        }

        if ( 'gmaps' === $adapter ) {
            $map_style = $settings->get( 'appearance', 'map_style' );
            $gmaps     = ( is_array( $map_style ) && isset( $map_style['gmaps'] ) ) ? $map_style['gmaps'] : [];

            $data['mapStyle'] = [
                'selected'    => isset( $gmaps['selected'] ) ? $gmaps['selected'] : 'cloud_based',
                'cloud_based' => isset( $gmaps['cloud_based'] ) ? $gmaps['cloud_based'] : '',
                'json'        => wpsl_get_service( 'map_settings' )->get_map_style( 'gmaps' ),
            ];
        }

        if ( 'osm' === $adapter && $tile_layer ) {
            $data['tileLayer'] = $tile_layer;
        }

        return $data;
    }

    /**
     * The editor's runtime strings.
     *
     * @since  3.0.0
     * @return array
     */
    private function get_l10n() {
        return [
            // Instructions, keyed by gesture. See the note above.
            'selectTool'          => __( 'Select a drawing tool to begin.', 'wp-store-locator' ),
            'drawVertices'        => __( 'Click the map to add points. Click the last point again to complete the shape.', 'wp-store-locator' ),
            'drawCircleDrag'      => __( 'Click the center of the circle and drag outwards to set the radius.', 'wp-store-locator' ),
            'drawRectangleDrag'   => __( 'Click and drag to draw the rectangle.', 'wp-store-locator' ),
            'selectShape'         => __( 'Select shape', 'wp-store-locator' ),
            // The picker's tag on a shape whose Active toggle is off.
            'hidden'              => __( 'Hidden', 'wp-store-locator' ),
            // The unit a circle's radius is shown in, per the search settings.
            'metres'              => __( 'm', 'wp-store-locator' ),
            'feet'                => __( 'ft', 'wp-store-locator' ),
            'invalidCoordinates'  => __( 'That is not a valid coordinate, so the shape was left where it was.', 'wp-store-locator' ),
            'invalidRadius'       => __( 'That is not a valid radius, so the shape was left where it was.', 'wp-store-locator' ),
            'duplicateName'       => __( 'Another shape already has this name, so the previous name was kept.', 'wp-store-locator' ),
            'tooFewPolygonPoints' => __( 'A polygon needs at least three points, so the shape was left as it was.', 'wp-store-locator' ),
            'tooFewLinePoints'    => __( 'A line needs at least two points, so the shape was left as it was.', 'wp-store-locator' ),
            'selectedPolygon'     => __( 'the selected polygon', 'wp-store-locator' ),
            'selectedCircle'      => __( 'the selected circle', 'wp-store-locator' ),
            'selectedRectangle'   => __( 'the selected rectangle', 'wp-store-locator' ),
            'selectedPolyline'    => __( 'the selected polyline', 'wp-store-locator' ),

            // Shown next to Undo once there is a change to take back.
            'undone'              => __( 'The last change was undone.', 'wp-store-locator' ),
            'incompleteShape'     => __( 'That shape was not finished, so it was not added. Please draw it again.', 'wp-store-locator' ),
            'searching'           => __( 'Searching…', 'wp-store-locator' ),
            'searchEmpty'         => __( 'Enter a place to search for.', 'wp-store-locator' ),
            'searchFailed'        => __( 'The search request failed. Check your connection and try again.', 'wp-store-locator' ),

            // Saving.
            'saving'              => __( 'Saving…', 'wp-store-locator' ),
            'saved'               => __( 'Shapes saved.', 'wp-store-locator' ),
            'saveFailed'          => __( 'The shapes could not be saved.', 'wp-store-locator' ),
            'requestFailed'       => __( 'The save request failed. Check your connection and try again.', 'wp-store-locator' ),
            'unsavedChanges'      => __( 'You have unsaved shape changes.', 'wp-store-locator' ),
            'adapterMissing'      => __( 'The drawing tools failed to load. Reload the page to try again.', 'wp-store-locator' ),
        ];
    }

    /**
     * The map centre the editor opens on, as a guaranteed numeric pair.
     *
     * @since  3.0.0
     * @return array [ 'lat' => float, 'lng' => float ]
     */
    private function get_start_lat_lng() {
        $raw = wpsl_get_service( 'asset_resources' )->get_default_lat_lng();

        $parts = array_map( 'trim', explode( ',', (string) $raw ) );

        if ( count( $parts ) !== 2 || ! is_numeric( $parts[0] ) || ! is_numeric( $parts[1] ) ) {
            return [ 'lat' => self::DEFAULT_LAT, 'lng' => self::DEFAULT_LNG ];
        }

        return [ 'lat' => (float) $parts[0], 'lng' => (float) $parts[1] ];
    }

    /**
     * Which drawing adapter a configured provider is drawn with.
     *
     * @since  3.0.0
     * @param  string $map_service The configured active_map_service.
     * @return string gmaps, mapbox or osm.
     */
    private function drawing_adapter( $map_service ) {
        if ( 'gmaps' === $map_service || 'mapbox' === $map_service ) {
            return $map_service;
        }

        return 'osm';
    }

    /**
     * Enqueue Mapbox GL JS.
     *
     * The drawing library itself is the vendored Terra Draw pair enqueued by
     * enqueue_terra_draw(), not a Mapbox-published plugin -- see the case
     * 'mapbox' branch in enqueue_assets().
     *
     * @since  3.0.0
     * @return void
     */
    private function enqueue_mapbox() {
        $gl_version = wpsl_get_script_version( 'mapbox_gl_js' );

        wp_enqueue_script( 'wpsl-mapbox', 'https://api.mapbox.com/mapbox-gl-js/v' . $gl_version . '/mapbox-gl.js', [], $gl_version, true );
        wp_enqueue_style( 'wpsl-mapbox', 'https://api.mapbox.com/mapbox-gl-js/v' . $gl_version . '/mapbox-gl.css', [], $gl_version );
    }

    /**
     * Enqueue the Terra Draw core plus one provider's adapter.
     *
     * @since  3.0.0
     * @see    https://github.com/JamesLMilner/terra-draw
     * @param  string $adapter 'gmaps', 'mapbox' or 'osm'.
     * @return void
     */
    private function enqueue_terra_draw( $adapter ) {
        $adapters = [
            'gmaps'  => [
                'file'    => 'terra-draw-google-maps-adapter.umd.js',
                'version' => 'terra_draw_gmaps_adapter',
                'handle'  => 'wpsl-terra-draw-gmaps',
                'deps'    => [ 'wpsl-terra-draw' ],
            ],
            'mapbox' => [
                'file'    => 'terra-draw-mapbox-gl-adapter.umd.js',
                'version' => 'terra_draw_mapbox_adapter',
                'handle'  => 'wpsl-terra-draw-mapbox',
                'deps'    => [ 'wpsl-terra-draw' ],
            ],
            'osm'    => [
                'file'    => 'terra-draw-leaflet-adapter.umd.js',
                'version' => 'terra_draw_leaflet_adapter',
                'handle'  => 'wpsl-terra-draw-leaflet',
                'deps'    => [ 'wpsl-terra-draw', 'wpsl-leaflet' ],
            ],
        ];

        if ( ! isset( $adapters[ $adapter ] ) ) {
            return;
        }

        $config = $adapters[ $adapter ];

        wp_enqueue_script( 'wpsl-terra-draw', WPSL_URL . 'assets/vendor/terra-draw/terra-draw.umd.js', [], wpsl_get_script_version( 'terra_draw' ), true );
        wp_enqueue_script( $config['handle'], WPSL_URL . 'assets/vendor/terra-draw/' . $config['file'], $config['deps'], wpsl_get_script_version( $config['version'] ), true );
    }

    /**
     * Enqueue Leaflet plus Leaflet-Geoman.
     *
     * @since  3.0.0
     * @see    https://leafletjs.com/examples/quick-start/
     * @see    https://github.com/geoman-io/leaflet-geoman
     * @param  array|null $tile_layer The tile config the map will draw. A vector one
     *                                brings MapLibre GL and its Leaflet bridge with it.
     * @return void
     */
    private function enqueue_leaflet( $tile_layer = null ) {
        $geoman_version = wpsl_get_script_version( 'geoman' );

        wpsl_enqueue_library( 'wpsl-leaflet', 'leaflet_js' );
        wpsl_enqueue_library( 'wpsl-leaflet', 'leaflet_css' );

        wp_enqueue_script( 'wpsl-geoman', WPSL_URL . 'assets/vendor/geoman/leaflet-geoman.min.js', [ 'wpsl-leaflet' ], $geoman_version, true );
        wp_enqueue_style( 'wpsl-geoman', WPSL_URL . 'assets/vendor/geoman/leaflet-geoman.min.css', [ 'wpsl-leaflet' ], $geoman_version );

        if ( $this->is_vector( $tile_layer ) ) {
            $this->enqueue_maplibre();
        }
    }

    /**
     * Enqueue MapLibre GL and the Leaflet bridge that draws a vector style.
     *
     * @since  3.0.0
     * @see    https://github.com/maplibre/maplibre-gl-leaflet
     * @return void
     */
    private function enqueue_maplibre() {
        wpsl_enqueue_library( 'wpsl-maplibre-gl', 'maplibre_gl_css' );
        wpsl_enqueue_library( 'wpsl-maplibre-gl', 'maplibre_gl_js' );
        wpsl_enqueue_library( 'wpsl-maplibre-gl-leaflet', 'maplibre_gl_leaflet_js', [ 'wpsl-leaflet', 'wpsl-maplibre-gl' ] );
    }

    /**
     * The tile config the Leaflet adapter should draw.
     *
     * @since  3.0.0
     * @param  string $map_service The configured provider.
     * @return array
     */
    private function tile_layer_config( $map_service ) {
        return ( 'stadia' === $map_service )
            ? wpsl_get_stadia_tile_layer()
            : wpsl_get_osm_tile_layer();
    }

    /**
     * Is this tile config a vector style, i.e. one only MapLibre can draw?
     *
     * @since  3.0.0
     * @param  array|null $tile_layer
     * @return bool
     */
    private function is_vector( $tile_layer ) {
        return is_array( $tile_layer ) && isset( $tile_layer['type'] ) && 'vector' === $tile_layer['type'];
    }
}