<?php
/**
 * Handle the script / style assets.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Assets;

defined( 'ABSPATH' ) || exit;

use WPSL\Admin\Assets\Resources;
use WPSL\Core\UI\Theme_Styles;
use WPSL\Core\Settings\Manager as WpslSettings;
    
class Manager {

    /**
     * Resources object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Assets\Resources
     */
    public $resources;

    /**
     * Theme styles object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\UI\Theme_Styles
     */
    public $theme_styles;

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Admin\Assets\Resources  $resources    Resources instance
     * @param \WPSL\Core\UI\Theme_Styles    $theme_styles Theme styles instance
     * @param \WPSL\Core\Settings\Manager   $settings     Settings manager instance
     */
    public function __construct( Resources $resources, Theme_Styles $theme_styles, WpslSettings $settings ) {
        $this->resources    = $resources;
        $this->theme_styles = $theme_styles;
        $this->settings     = $settings;
    
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'codemirror' ] );
        add_filter( 'script_loader_tag',     [ $this, 'update_tag' ], 10, 3 );
    }

    /**
     * Add the required admin assets.
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_assets() {
        global $pagenow, $wp_scripts;

        // Load from dist folder for production, source folder for development
        $css_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $css_ext = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.css' : '.min.css';

        $this->maybe_show_pointer();

        wp_enqueue_style( 'wpsl-fontello', WPSL_URL . $css_base . 'admin/css/fontello' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-admin', WPSL_URL . $css_base . 'admin/css/style' . $css_ext, false, WPSL_VERSION_NUM );

        $toggle_min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
        wp_enqueue_script( 'wpsl-shared-funcs', WPSL_URL . 'assets/src/admin/js/wpsl-shared-funcs' . $toggle_min . '.js', [ 'jquery' ], WPSL_VERSION_NUM, true );

        /*
         * The Home page's shortcode copy button and its map service step. A
         * standalone file rather than part of an admin bundle: two controls,
         * on one screen. The key inputs reuse the settings page's show / hide
         * toggle, which is standalone for the same reason.
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check, nothing is processed.
        if ( isset( $_GET['page'] ) && 'wpsl_home' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
            wp_enqueue_script( 'mircomodal', WPSL_URL . 'assets/src/admin/js/micromodal.min.js', [], '0.4.10', true );
            wp_enqueue_style( 'wpsl-micromodal', WPSL_URL . $css_base . 'admin/css/micromodal' . $css_ext, [], WPSL_VERSION_NUM );

            wp_enqueue_script( 'wpsl-admin-ui', WPSL_URL . 'assets/src/admin/js/wpsl-admin-ui' . $toggle_min . '.js', [ 'jquery', 'wpsl-shared-funcs', 'mircomodal' ], WPSL_VERSION_NUM, true );
            wp_enqueue_script( 'wpsl-key-visibility', WPSL_URL . 'assets/src/admin/js/wpsl-key-visibility' . $toggle_min . '.js', [], WPSL_VERSION_NUM, true );

            wp_localize_script( 'wpsl-admin-ui', 'wpslHome', [
                'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
                'nonce'                 => wp_create_nonce( \WPSL\Admin\Home\Home::SERVICE_NONCE ),
                'feedbackNonce'         => wp_create_nonce( \WPSL\Admin\Home\Home::FEEDBACK_NONCE ),
                'validationStatusNonce' => wp_create_nonce( 'wpsl_update_validation_status' ),
                'dismissNonce'          => wp_create_nonce( 'wpsl_dismiss_alert' ),
                'actions'               => [
                    'save'       => \WPSL\Admin\Home\Home::SAVE_SERVICE_ACTION,
                    'status'     => \WPSL\Admin\Home\Home::STATUS_ACTION,
                    'feedback'   => \WPSL\Admin\Home\Home::FEEDBACK_ACTION,
                    'createPage' => \WPSL\Admin\Home\Home::CREATE_PAGE_AJAX,
                    'markPlaced' => \WPSL\Admin\Home\Home::MARK_PLACED_AJAX,
                ],
                'i18n'                  => [
                    'save'          => esc_html__( 'Save', 'wp-store-locator' ),
                    'saveVerify'    => esc_html__( 'Save and verify', 'wp-store-locator' ),
                    'saving'        => esc_html__( 'Saving...', 'wp-store-locator' ),
                    'saved'         => esc_html__( 'Map service saved.', 'wp-store-locator' ),
                    'browserWait'   => esc_html__( 'Checking the browser key with Google Maps...', 'wp-store-locator' ),
                    'browserOk'     => esc_html__( 'No problems found with the browser key.', 'wp-store-locator' ),
                    'browserFail'   => esc_html__( 'Google Maps returned the following error for the browser key.', 'wp-store-locator' ),
                    'browserLoad'   => esc_html__( 'The Google Maps JavaScript API could not be loaded.', 'wp-store-locator' ),
                    'browserSlow'   => esc_html__( 'Google Maps did not answer in time. Please try again.', 'wp-store-locator' ),
                    'requestFailed' => esc_html__( 'The request failed. Please reload the page and try again.', 'wp-store-locator' ),
                    /* translators: 1: completed tasks, 2: total tasks */
                    'tasksDone'     => esc_html__( 'Store Locator setup: %1$d of %2$d tasks done', 'wp-store-locator' ),
                    'setupComplete' => esc_html__( 'Store Locator setup complete!', 'wp-store-locator' ),
                    'feedbackType'  => esc_html__( 'Please choose what kind of feedback you have.', 'wp-store-locator' ),
                    'feedbackEmpty' => esc_html__( 'Please write your feedback before sending.', 'wp-store-locator' ),
                    'pageCreated'   => esc_html__( 'Page created as a draft.', 'wp-store-locator' ),
                    'pageExists'    => esc_html__( 'A page with that title already has the locator on it.', 'wp-store-locator' ),
                    'editPage'      => esc_html__( 'Edit page', 'wp-store-locator' ),
                    'feedbackPrompt' => [
                        'feature' => esc_html__( 'Which feature are you missing, and what would it let you do?', 'wp-store-locator' ),
                        'general' => esc_html__( 'What do you think of the plugin? What works well, and what does not?', 'wp-store-locator' ),
                        'bug'     => esc_html__( 'What were you trying to do, and what happened instead?', 'wp-store-locator' ),
                    ],
                ],
            ] );
        }

        /*
         * The show / hide buttons on the masked API key inputs.
         */
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check, nothing is processed.
        if ( isset( $_GET['page'] ) && 'wpsl_settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
            wp_enqueue_script( 'wpsl-key-visibility', WPSL_URL . 'assets/src/admin/js/wpsl-key-visibility' . $toggle_min . '.js', [], WPSL_VERSION_NUM, true );
        }

        wp_enqueue_style( 'wpsl-shared', WPSL_URL . $css_base . 'admin/css/shared' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-admin-responsive', WPSL_URL . $css_base . 'admin/css/responsive' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-common', WPSL_URL . $css_base . 'common/css/common' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-filters', WPSL_URL . $css_base . 'common/css/filters' . $css_ext, false, WPSL_VERSION_NUM );

        // The theme colors, never layered here: the Appearance preview shows the chosen colors.
        wp_enqueue_style( 'wpsl-colors', WPSL_URL . $css_base . 'common/css/colors' . $css_ext, [ 'wpsl-common' ], WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-responsive', WPSL_URL . $css_base . 'common/css/responsive' . $css_ext, false, WPSL_VERSION_NUM );

        $screen    = get_current_screen();
        $screen_id = $screen ? $screen->id : '';

        $js_base = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/' : 'assets/dist/';
        $js_ext = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.js' : '.min.js';

        // Only enqueue the rest of the css/js files if we are on a page that belongs to the store locator.
        if ( in_array( $screen_id, \wpsl_get_screen_ids() ) ) {
            if ( $screen_id == 'wpsl_stores_page_wpsl_settings' ) {
                wp_enqueue_script( 'wpsl-purify', WPSL_URL . 'assets/src/admin/js/purify.min.js', [ 'jquery' ], '3.1.7', true );
                wp_enqueue_script( 'underscore' );

                // Enqueue custom color picker (CSS and JS)
                $this->enqueue_color_picker( $css_base, $css_ext, $js_base, $js_ext );
            }

            /**
             * Marker Studio ( edit.php?post_type=wpsl_stores&page=wpsl_marker_studio ).
             *
             * Its own page, not a settings sub-section, so it is enqueued on
             * its own screen id rather than alongside wpsl_settings.
             */
            if ( $screen_id == 'wpsl_stores_page_wpsl_marker_studio' ) {
                wp_enqueue_style( 'wpsl-marker-studio', WPSL_URL . $css_base . 'admin/css/marker-studio' . $css_ext, [], WPSL_VERSION_NUM );
                wp_enqueue_media();

                // Enqueue custom color picker (CSS and JS)
                $this->enqueue_color_picker( $css_base, $css_ext, $js_base, $js_ext );

                $studio_deps = [ 'jquery', 'wpsl-color-picker', 'wpsl-shared-funcs' ];
                $studio_base = $js_base . 'admin/js/marker-studio/';

                // The label text rules ( the twin of Marker_Label ), 
                // which the SVG builder consults for the Text glyph.
                wp_enqueue_script( 'wpsl-marker-label', WPSL_URL . $js_base . 'common/wpsl-marker-label' . $js_ext, [], WPSL_VERSION_NUM, true );
                wp_enqueue_script( 'wpsl-marker-studio-svg', WPSL_URL . $studio_base . 'wpsl-marker-studio-svg' . $js_ext, [ 'wpsl-marker-label' ], WPSL_VERSION_NUM, true );
                wp_enqueue_script( 'wpsl-marker-studio', WPSL_URL . $studio_base . 'wpsl-marker-studio' . $js_ext, $studio_deps, WPSL_VERSION_NUM, true );
                wp_enqueue_script( 'wpsl-marker-studio-panes', WPSL_URL . $studio_base . 'wpsl-marker-studio-panes' . $js_ext, array_merge( $studio_deps, [ 'wpsl-marker-studio-svg', 'wpsl-marker-studio' ] ), WPSL_VERSION_NUM, true );
                wp_enqueue_script( 'wpsl-marker-studio-map', WPSL_URL . $studio_base . 'wpsl-marker-studio-map' . $js_ext, [ 'jquery', 'wpsl-marker-studio-svg' ], WPSL_VERSION_NUM, true );

                wp_localize_script( 'wpsl-marker-studio', 'wpslMarkerStudio', $this->get_marker_studio_data() );
            }

            if ( $screen_id == 'wpsl_stores_page_wpsl_appearance' ) {
                // Enqueue appearance editor CSS only (JS is loaded dynamically from admin bundle)
                wp_enqueue_style( 'wpsl-appearance-editor', WPSL_URL . $css_base . 'admin/css/appearance-editor' . $css_ext, false, WPSL_VERSION_NUM );
                
                // Enqueue custom color picker (CSS and JS)
                $this->enqueue_color_picker( $css_base, $css_ext, $js_base, $js_ext );

                // Localize script for appearance editor (passed through main admin bundle)
                wp_localize_script( 'wpsl-admin', 'wpslAppearanceL10n', [
                    'activate'           => esc_html__( 'Activate', 'wp-store-locator' ),
                    'customize'          => esc_html__( 'Customize', 'wp-store-locator' ),
                    'template_activated' => esc_html__( 'Template activated successfully', 'wp-store-locator' ),
                    'saving'             => esc_html__( 'Saving settings...', 'wp-store-locator' ),
                    'saved'              => esc_html__( 'Settings saved successfully!', 'wp-store-locator' ),
                    'save_error'         => esc_html__( 'Error saving settings', 'wp-store-locator' ),
                ] );
                
                // Pass current styles (saved values merged with defaults) for initial page load and fallbacks
                wp_localize_script( 'wpsl-admin', 'wpslCurrentStyles', $this->theme_styles->get_current_styles() );
                
                // Pass pure defaults for reset functionality
                wp_localize_script( 'wpsl-admin', 'wpslDefaultStyles', $this->theme_styles->defaults );
            }
            
            // Load from dist folder for production, source folder for development
            $core_path = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/common/wpsl-core.js' : 'assets/dist/common/wpsl-core.min.js';
            $admin_path = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'assets/src/admin/js/wpsl-admin.js' : 'assets/dist/admin/js/wpsl-admin.min.js';
            
            wp_enqueue_script( 'wpsl-core', WPSL_URL . $core_path, [ 'jquery' ], WPSL_VERSION_NUM, true );
            wp_enqueue_script( 'wpsl-admin', WPSL_URL . $admin_path, [ 'jquery' ], WPSL_VERSION_NUM, true );

            // Add type="module" only for source files in development mode
            if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
                add_filter( 'script_loader_tag', function( $tag, $handle ) {
                    if ( 'wpsl-admin' === $handle || 'wpsl-core' === $handle ) {
                        /*
                         * Bounded pattern, not str_replace(): $tag also holds the
                         * handle's translations and "before"/"after" inline scripts.
                         * Stamping type="module" on those would defer them, and the
                         * "after" Google Maps bootstrap has to run before the footer's
                         * classic scripts. Requiring src= in the lookahead matches
                         * only the file's own tag.
                         */
                        return preg_replace( '#<script(?=[^>]*\ssrc=)#', '<script type="module"', $tag );
                    }

                    return $tag;
                }, 10, 2 );
            }

            wp_enqueue_style( 'wp-jquery-ui-dialog' );
            
            // Enqueue jQuery UI theme CSS for tabs and other components
            wpsl_enqueue_library( 'wpsl-jquery-ui-theme', 'jquery_ui_theme_css' );
            
            wp_enqueue_media();

            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-widget' );
            wp_enqueue_script( 'jquery-ui-dialog' );
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_script( 'jquery-ui-datepicker' );

            if ( $pagenow == 'edit.php' && isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) == 'wpsl_settings' ) {
                wp_enqueue_script( 'jquery-ui-core' );
                wp_enqueue_script( 'jquery-ui-sortable' );
            }

            /*
             * Compatibility mode applies to the admin too: the settings,
             * appearance and editor pages all render a map, and another plugin
             * loading Google Maps first breaks them the same way it breaks the
             * frontend.
             */
            if ( $this->settings->get( 'tools', 'deregister_gmaps' ) ) {
                wpsl_deregister_other_gmaps();
            }

            wp_add_inline_script( 'wpsl-admin', wpsl_gmaps_bootstrap(), 'after' );

            /*
             * The plugin's own pages ( edit.php ) load every provider, because
             * the settings dropdown can switch between them. The single store
             * screens only need the active one.
             */
            $load_all_providers = ( $pagenow == 'edit.php' );
            $active_map_service = $this->settings->get( 'api', 'active_map_service' );

            if ( $active_map_service == 'osm' || $active_map_service == 'stadia' || $load_all_providers ) {
                $this->enqueue_leaflet();

                // Vector tile source (OpenFreeMap) needs MapLibre GL + the Leaflet bridge, so the appearance-page preview can render vector styles.
                wpsl_enqueue_library( 'wpsl-maplibre-gl', 'maplibre_gl_css' );
                wpsl_enqueue_library( 'wpsl-maplibre-gl', 'maplibre_gl_js' );
                wpsl_enqueue_library( 'wpsl-maplibre-gl-leaflet', 'maplibre_gl_leaflet_js', [ 'wpsl-leaflet', 'wpsl-maplibre-gl' ] );
            }

            if ( $active_map_service == 'mapbox' || $load_all_providers ) {
                $this->enqueue_mapbox();
            }

            wp_enqueue_script( 'wpsl-queue', WPSL_URL . 'assets/src/admin/js/ajax-queue.min.js', [ 'jquery' ], WPSL_VERSION_NUM, true );
            wp_enqueue_script( 'wpsl-retina', WPSL_URL . 'assets/src/admin/js/retina.min.js', [ 'jquery' ], WPSL_VERSION_NUM, true );           
            
            wp_localize_script( 'wpsl-admin', 'wpslL10n', $this->resources->get_l10n() );
            wp_localize_script( 'wpsl-admin', 'wpslApiErrors', wpsl_api_error_messages() );
            wp_localize_script( 'wpsl-admin', 'wpslSettings', $this->resources->get_settings() );
            wp_localize_script( 'wpsl-admin', 'wpslSvgIcons', $this->resources->get_svg_icons() );
            wp_localize_script( 'wpsl-admin', 'wpslSecurity', [
                'validateKeyNonce' => wp_create_nonce( 'wpsl_validate_key' ),
            ] );
            
            if ( $screen_id == 'wpsl_stores_page_wpsl_settings' ) {
                wp_localize_script( 'wpsl-admin', 'wpslMarkerCreate', [
                    'ajaxurl'      => wpsl_get_ajax_url(),
                    'nonce'        => wp_create_nonce( 'wpsl_marker_manager_nonce' ),
                    'defaultShape' => 'classic_pin',
                    'previewHeight' => \WPSL\Core\Markers\Custom_Markers::get_picker_height( 'classic_pin' ),
                    'previewSvg'   => \WPSL\Core\Markers\Custom_Markers::get_svg_marker( [
                        'shape'        => 'classic_pin',
                        'fill_color'   => '%%FILL%%',
                        'stroke_color' => '%%OUTLINE%%',
                        'icon_name'    => 'dot',
                        'icon_color'   => '%%ICON%%',
                    ] ),
                    'strings'      => [
                        'customMarkers' => __( 'Custom markers', 'wp-store-locator' ),
                        'namePrefix'    => __( 'Custom', 'wp-store-locator' ),
                        'addColor'      => __( 'Color', 'wp-store-locator' ),
                        'newColor'      => __( 'New marker color', 'wp-store-locator' ),
                        'fill'          => __( 'Fill', 'wp-store-locator' ),
                        'dot'           => __( 'Dot', 'wp-store-locator' ),
                        'outline'       => __( 'Outline', 'wp-store-locator' ),
                        'addAction'     => __( 'Add color', 'wp-store-locator' ),
                        'cancel'        => __( 'Cancel', 'wp-store-locator' ),
                        'saveFailed'    => __( 'The marker could not be saved.', 'wp-store-locator' ),
                    ],
                ] );
            }

            if ( $pagenow == 'edit.php' && isset( $_GET['page'] ) ) {
                $current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
                
                // Check if the current page is either 'wpsl_settings' or 'wpsl_appearance'
                if ( in_array( $current_page, [ 'wpsl_settings', 'wpsl_appearance' ], true ) ) {
                    $theme_styles = $this->theme_styles->build_custom_style();

                    if ( $theme_styles ) {
                        wp_add_inline_style( 'wpsl-admin', $theme_styles );
                    }

                    // Pass current styles for initial page load and fallbacks
                    wp_localize_script( 'wpsl-admin', 'wpslCurrentStyles', $this->theme_styles->get_current_styles() );
                    
                    // Pass pure defaults for reset functionality
                    wp_localize_script( 'wpsl-admin', 'wpslDefaultStyles', $this->theme_styles->defaults );
                }

                // After a failed Google Maps key validation, output the full
                // error message to the browser console for easier debugging.
                if ( 'wpsl_settings' === $current_page ) {
                    $gmaps_key_error = get_transient( 'wpsl_gmaps_key_error_details' );

                    if ( $gmaps_key_error ) {
                        delete_transient( 'wpsl_gmaps_key_error_details' );

                        wp_add_inline_script(
                            'wpsl-admin',
                            'console.error( ' . wp_json_encode( '[WP Store Locator] Google Geocode API error: ' . $gmaps_key_error ) . ' );',
                            'after'
                        );
                    }
                }
            }
        }
    }

    /**
     * Enqueue Leaflet.
     *
     * @since 3.0.0
     * @see    https://leafletjs.com/examples/quick-start/
     * @return void
     */
    private function enqueue_leaflet() {
        wpsl_enqueue_library( 'wpsl-leaflet', 'leaflet_js' );
        wpsl_enqueue_library( 'wpsl-leaflet', 'leaflet_css' );
    }

    /**
     * Enqueue the Mapbox GL JS stack.
     *
     * @since 3.0.0
     * @return void
     */
    private function enqueue_mapbox() {
        wp_enqueue_script( 'wpsl-mapbox', 'https://api.mapbox.com/mapbox-gl-js/v' . wpsl_get_script_version( 'mapbox_gl_js' ) . '/mapbox-gl.js', '', wpsl_get_script_version( 'mapbox_gl_js' ), true );
        wp_enqueue_style( 'wpsl-mapbox', 'https://api.mapbox.com/mapbox-gl-js/v' . wpsl_get_script_version( 'mapbox_gl_js' ) . '/mapbox-gl.css', [], wpsl_get_script_version( 'mapbox_gl_js' ) );
    }

    /**
     * The data the Marker Studio page's JS runs on.
     *
     * Separate from enqueue_assets() because this is a full page of data,
     * split into named blocks: saved markers, shape geometry, field defaults,
     * and runtime strings.
     *
     * @since  3.0.0
     * @return array
     */
    private function get_marker_studio_data() {
        return [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpsl_marker_manager_nonce' ),
            'markers' => wpsl_get_service( 'custom_markers' )->get_markers(),

            /*
             * Reusable logos keyed by attachment id: the same union the
             * template renders Custom tiles from, and how the JS restores the
             * logo badge when a marker with a logo_id is loaded.
             */
            'logos' => wpsl_get_service( 'custom_markers' )->get_reusable_logos(),

            /*
             * Logo size cap, so the picker can refuse an oversized file before
             * save drops it silently.
             */
            'logoMaxBytes' => (int) apply_filters( 'wpsl_marker_logo_max_bytes', \WPSL\Core\Markers\Custom_Markers::LOGO_MAX_BYTES, 0 ),
            'logoMaxHuman' => size_format( (int) apply_filters( 'wpsl_marker_logo_max_bytes', \WPSL\Core\Markers\Custom_Markers::LOGO_MAX_BYTES, 0 ) ),
            'deleteWarnings' => $this->get_marker_studio_delete_warnings( wpsl_get_service( 'custom_markers' )->get_markers() ),

            'shapes' => \WPSL\Core\Markers\Custom_Markers::SHAPES,
            'shapeBodies' => \WPSL\Core\Markers\Custom_Markers::SHAPE_BODIES,

            /*
             * Measured extent of each silhouette, from which the root element's
             * data-wpsl-anchor-y is derived.
             */
            'shapeBounds'     => \WPSL\Core\Markers\Custom_Markers::SHAPE_BOUNDS,
            'iconScale'       => \WPSL\Core\Markers\Custom_Markers::ICON_SCALE,
            'centerShapes'    => \WPSL\Core\Markers\Custom_Markers::CENTER_SHAPES,
            'centerDotRadius' => \WPSL\Core\Markers\Custom_Markers::CENTER_DOT_RADIUS,
            'holeShapes'      => \WPSL\Core\Markers\Custom_Markers::HOLE_SHAPES,

            /*
             * Shapes the Text glyph is offered on, so the tile's shape gate and
             * the preview agree with sanitize_marker().
             */
            'labelShapes'     => \WPSL\Core\Markers\Custom_Markers::LABEL_SHAPES,

            /*
             * How much larger text draws than an icon, per shape, so the
             * preview grows a glyph exactly as the saved marker will.
             */
            'textScale'       => \WPSL\Core\Markers\Custom_Markers::TEXT_SCALE,

            /*
             * Both icon libraries in one map: Lucide stroke glyphs and Phosphor
             * solid (Fill) glyphs. The "ph-" prefix prevents collision and
             * carries the paint mode.
             */
            'icons'           => array_merge(
                \WPSL\Core\Markers\Lucide_Icons::get_all(),
                \WPSL\Core\Markers\Phosphor_Icons::get_all()
            ),

            /*
             * Group to icon-names mapping for the icon 
             * picker's category dropdown.
             */
            'iconGroups'  => array_merge_recursive(
                \WPSL\Core\Markers\Lucide_Icons::get_groups(),
                \WPSL\Core\Markers\Phosphor_Icons::get_groups()
            ),

            /*
             * Extra search words per category, so the picker answers the
             * concept a user has in mind ("travel") not just the label we chose
             * ("Transport & fuel").
             */
            'iconGroupAliases' => \WPSL\Core\Markers\Icon_Search::get_group_aliases(),
            'iconTags'    => array_merge(
                \WPSL\Core\Markers\Lucide_Icons::get_tags(),
                \WPSL\Core\Markers\Phosphor_Icons::get_tags()
            ),
            'perPage'     => 18,

            /*
             * What a new marker starts as, mirroring the fallbacks in
             * Custom_Markers::sanitize_marker().
             */
            'defaults' => [
                'shape'        => 'classic_pin',
                'fill_color'   => '#1e5b83',
                'stroke_color' => '#093857',
                'stroke_width' => 1,
                'icon_name'    => 'dot',
                'label_text'   => '',
                'icon_color'   => '#ffffff',
                'size'         => \WPSL\Core\Markers\Custom_Markers::DEFAULT_SIZE,
                'icon_size'    => 100,
                'shadow'       => false,
                'center_fill'  => false,
                'center_color' => '#1e5b83',
            ],

            'l10n' => [
                'createTitle'   => __( 'New marker', 'wp-store-locator' ),
                'editTitle'     => __( 'Edit marker', 'wp-store-locator' ),
                /* translators: %d: a number of pixels. */
                'pixels'        => __( '%dpx', 'wp-store-locator' ),
                /* translators: %d: a percentage of the default icon size. */
                'percent'       => __( '%d%%', 'wp-store-locator' ),
                'noIconsFound'  => __( 'No icons match that search.', 'wp-store-locator' ),
                // The icon color row is relabeled after what it colors: the
                // dot centre is not an icon.
                'iconLabel'     => __( 'Icon', 'wp-store-locator' ),
                'dotLabel'      => __( 'Dot', 'wp-store-locator' ),
                'textLabel'     => __( 'Text', 'wp-store-locator' ),
                // ...and the icon size slider, which scales the text too.
                'iconSizeLabel' => __( 'Icon size', 'wp-store-locator' ),
                'textSizeLabel' => __( 'Text size', 'wp-store-locator' ),

                /*
                 * The glyph contrast warning. The threshold is 3:1 rather
                 * than the appearance editor's 4.5:1 because a marker icon
                 * is a graphic ( WCAG 1.4.11 ), not body text.
                 */
                'contrastVeryLow'   => __( 'Very low contrast', 'wp-store-locator' ),
                'contrastLow'       => __( 'Low contrast', 'wp-store-locator' ),
                'contrastGlyphHard' => __( '— the icon will be hard to see on this background.', 'wp-store-locator' ),
                'contrastGraphicRequirement' => sprintf(
                    /* translators: 1: opening link tag to the WebAIM contrast article, 2: closing link tag. %s is replaced by the current ratio, e.g. 1.4 */
                    __( 'Icons need a %1$scontrast ratio%2$s of at least 3:1 to stay visible ( you have %%s:1 ).', 'wp-store-locator' ),
                    '<a href="https://webaim.org/articles/contrast/#sc143" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                ),
                'contrastAutoFix'   => __( 'Auto fix', 'wp-store-locator' ),
                // ...and so is the fill row: on a shape the fill IS the
                // marker, on a framed image it sits behind the bitmap.
                'fillLabel'     => __( 'Fill', 'wp-store-locator' ),
                'bgLabel'       => __( 'Background', 'wp-store-locator' ),
                'allCategories' => __( 'All categories', 'wp-store-locator' ),
                'unknownIcon'   => __( 'This marker uses an icon that is no longer available. Pick another one.', 'wp-store-locator' ),
                'saving'        => __( 'Saving…', 'wp-store-locator' ),
                'saved'         => __( 'Marker saved.', 'wp-store-locator' ),
                'saveFailed'    => __( 'The marker could not be saved.', 'wp-store-locator' ),
                'saveError'     => __( 'Something went wrong while saving the marker.', 'wp-store-locator' ),
                'confirmDelete' => __( 'Delete this marker permanently?', 'wp-store-locator' ),
                'cantUndone'    => __( "This can't be undone.", 'wp-store-locator' ),
                'deleted'       => __( 'Marker deleted.', 'wp-store-locator' ),
                'deleteFailed'  => __( 'The marker could not be deleted.', 'wp-store-locator' ),
                'deleteError'   => __( 'Something went wrong while deleting the marker.', 'wp-store-locator' ),
                /* translators: %s: the name of the marker being duplicated. */
                'copyOf'        => __( 'Copy of %s', 'wp-store-locator' ),
                'copied'        => __( 'Shortcode ID copied.', 'wp-store-locator' ),
                'copyFailed'    => __( 'Could not copy. The ID is selected — press Ctrl+C ( ⌘C on a Mac ).', 'wp-store-locator' ),
                /* The window.prompt() fallback when jQuery UI is absent. */
                'shortcodeIdLabel' => __( 'Shortcode ID', 'wp-store-locator' ),
                'uploadLogo'    => __( 'Upload Logo', 'wp-store-locator' ),
                'logoNoSvg'     => __( 'SVG images are not accepted. Use a PNG or a WebP.', 'wp-store-locator' ),
                /* translators: %s: the largest allowed file size, e.g. "256 KB". */
                'logoTooLarge'  => __( 'That image is larger than the %s limit. Pick a smaller one.', 'wp-store-locator' ),
                'replaceLogo'   => __( 'Replace', 'wp-store-locator' ),
                'removeLogo'    => __( 'Remove', 'wp-store-locator' ),
                'logoMissing'   => __( 'This logo is no longer in your Media Library.', 'wp-store-locator' ),
                'removeLogoTile'  => __( 'Remove from marker logos', 'wp-store-locator' ),
                'logoRemoveFailed' => __( 'The logo could not be removed. Please try again.', 'wp-store-locator' ),
                'logoTileRemoved'  => __( 'Removed from your marker logos. The image is still in your Media Library.', 'wp-store-locator' ),
                'undo'             => __( 'Undo', 'wp-store-locator' ),
                'logoRestoreFailed' => __( 'That logo could not be put back. Pick it from your Media Library again.', 'wp-store-locator' ),
                'pickImageFirst'   => __( 'Pick an image for the marker first.', 'wp-store-locator' ),

                /* Dirty tracking. */
                /* translators: %s: the name of the marker with unsaved changes. */
                'confirmDiscard' => __( "You have unsaved changes to '%s'. Discard them?", 'wp-store-locator' ),

                /* Library pane. */
                'untitled'      => __( 'Untitled marker', 'wp-store-locator' ),
                'noMarkers'     => __( 'No markers yet. Design one on the right and save it.', 'wp-store-locator' ),
                'noMatches'     => __( 'No markers match that search.', 'wp-store-locator' ),
                'previousPage'  => __( 'Previous page', 'wp-store-locator' ),
                'nextPage'      => __( 'Next page', 'wp-store-locator' ),
                /* translators: %d: a page number. */
                'goToPage'      => __( 'Go to page %d', 'wp-store-locator' ),
            ],
        ];
    }

    /**
     * Build the per-marker delete warnings map for the Marker Studio localize.
     *
     * @since  3.0.0
     * @param  array $markers The saved markers.
     * @return array Map of markerId => [ warning, ... ].
     */
    private function get_marker_studio_delete_warnings( $markers ) {
        $warnings = [];

        $slots = [
            'store_marker'  => __( 'This is your default store marker. Stores using it will fall back to the standard pin.', 'wp-store-locator' ),
            'start_marker'  => __( 'This is your start point marker. The search start point will fall back to the standard pin.', 'wp-store-locator' ),
            'active_marker' => __( 'This is your active store marker. Highlighted stores will fall back to the standard pin.', 'wp-store-locator' ),
        ];

        $settings   = [];
        $references = wpsl_get_service( 'custom_markers' )->get_reference_counts();

        foreach ( array_keys( $slots ) as $key ) {
            $settings[ $key ] = $this->settings->get( 'markers', $key, '' );
        }

        foreach ( $markers as $marker ) {
            $id = isset( $marker['id'] ) ? $marker['id'] : '';

            if ( ! $id ) {
                continue;
            }

            $value = 'custom:' . $id;
            $lines = [];

            foreach ( $slots as $key => $line ) {
                if ( $settings[ $key ] === $value ) {
                    $lines[] = $line;
                }
            }

            $used = isset( $references[ $value ] ) ? $references[ $value ] : [];

            if ( ! empty( $used['categories'] ) ) {
                $lines[] = sprintf(
                    /* translators: %d: the number of store categories using this marker. */
                    _n(
                        '%d store category uses it and will fall back to the default marker.',
                        '%d store categories use it and will fall back to the default marker.',
                        $used['categories'],
                        'wp-store-locator'
                    ),
                    $used['categories']
                );
            }

            if ( ! empty( $used['locations'] ) ) {
                $lines[] = sprintf(
                    /* translators: %d: the number of locations using this marker. */
                    _n(
                        '%d location uses it and will fall back to the default marker.',
                        '%d locations use it and will fall back to the default marker.',
                        $used['locations'],
                        'wp-store-locator'
                    ),
                    $used['locations']
                );
            }

            /**
             * Append per-marker delete warnings.
             *
             * @since 3.0.0
             * @param array $lines   The warning lines so far.
             * @param array $marker  The saved marker.
             */
            $lines = apply_filters( 'wpsl_marker_delete_warnings', $lines, $marker );

            if ( $lines ) {
                $warnings[ $id ] = array_values( $lines );
            }
        }

        return $warnings;
    }

    /**
     * Enqueue the custom color picker style and script.
     *
     * Used on both the settings and appearance screens.
     *
     * @since  3.0.0
     * @param  string $css_base The base path for styles ( src or dist )
     * @param  string $css_ext  The style file extension ( .css or .min.css )
     * @param  string $js_base  The base path for scripts ( src or dist )
     * @param  string $js_ext   The script file extension ( .js or .min.js )
     * @return void
     */
    private function enqueue_color_picker( $css_base, $css_ext, $js_base, $js_ext ) {
        wp_enqueue_style( 'wpsl-colorpicker', WPSL_URL . $css_base . 'admin/css/colorpicker' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_script( 'wpsl-color-picker', WPSL_URL . $js_base . 'admin/js/modules/wpsl-color-picker' . $js_ext, [ 'jquery' ], WPSL_VERSION_NUM, true );
    }

    /**
     * Filters the HTML script tag of an enqueued script
     * and update the ID attribute
     *
     * @since  3.0.0
     * @param  string $tag    The HTML script tag
     * @param  string $handle The registered script handle
     * @param  string $src    The script source URL
     * @return string $tag
     */
    public function update_tag( $tag, $handle, $src ) {
        if ( $handle === 'wpsl-admin' && strpos( $tag, 'id="wpsl-admin-js-after"' ) !== false ) {
            $tag = str_replace( 'id="wpsl-admin-js-after"', 'id="wpsl-bootloader"', $tag );
        }

        return $tag;
    }

    /**
     * Enqueue the required scripts for the code editor.
     *
     * @since  3.0.0
     * @see    https://codemirror.net/5/doc/manual.html
     * @param  string $hook The current admin page hook
     * @return void
     */
    public function codemirror( $hook ) {
        $screen    = get_current_screen();
        $screen_id = $screen ? $screen->id : '';

        if ( ! in_array( $screen_id, \wpsl_get_screen_ids() ) ) {
            return;
        }

        $cm_settings['codeEditor'] = wp_enqueue_code_editor(
            [
                'type' => 'text/html',
                'codemirror' => [
                    'autofocus' => true,
                    'autoRefresh' => true, // Required to make sure the code is styled correctly on page load
                    'indentWithTabs' => true,
                    'lint' => false
                ]
            ]
        );

        wp_localize_script( 'jquery', 'cm_settings', $cm_settings );

        wp_enqueue_script( 'wp-theme-plugin-editor' );
        wp_enqueue_style( 'wp-codemirror' );
    }

    /**
     * Check if we need to show the wpsl pointer.
     *
     * @since  2.0.0
     * @return void
     */
    private function maybe_show_pointer() {
        $disable_pointer = apply_filters( 'wpsl_disable_welcome_pointer', false );

        if ( $disable_pointer ) {
            return;
        }

        $dismissed_pointers = explode( ',', ( string ) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

        // If the user hasn't dismissed the wpsl pointer, enqueue the script and style, and call the action hook.
        if ( ! in_array( 'wpsl_signup_pointer', $dismissed_pointers ) ) {
            wp_enqueue_style( 'wp-pointer' );
            wp_enqueue_script( 'wp-pointer' );
            
            if ( method_exists( $this, 'welcome_pointer_script' ) ) {
                add_action( 'admin_print_footer_scripts', [ $this, 'welcome_pointer_script' ] );
            }
        }
    }

    /**
     * Add the script for the welcome pointer.
     *
     * @since  2.0.0
     * @return void
     */
    public function welcome_pointer_script() {
        $pointer_content = '<h3>' . esc_html__( 'Welcome to WP Store Locator', 'wp-store-locator' ) . '</h3>';
        $pointer_content .= '<p>' . esc_html__( 'Sign up for the latest plugin updates and announcements.', 'wp-store-locator' ) . '</p>';
        $pointer_content .= '<div id="mc_embed_signup" class="wpsl-mc-wrap" style="padding:0 15px; margin-bottom:13px;"><form action="//wpstorelocator.us10.list-manage.com/subscribe/post?u=34e4c75c3dc990d14002e19f6&amp;id=4be03427d7" method="post" id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank" novalidate><div id="mc_embed_signup_scroll" style="white-space:nowrap;"><input type="email" value="" name="EMAIL" class="email" id="mce-EMAIL" placeholder="email address" required style="margin-right:5px;width:160px;box-sizing:border-box;"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button"><div style="position: absolute; left: -5000px;"><input type="text" name="b_34e4c75c3dc990d14002e19f6_4be03427d7" tabindex="-1" value=""></div></div></form></div>';

        $allowed_pointer_html = wp_kses_allowed_html( 'post' );

        $allowed_pointer_html['form'] = [
            'action'   => true,
            'method'   => true,
            'id'       => true,
            'name'     => true,
            'class'    => true,
            'target'   => true,
            'novalidate' => true,
        ];

        $allowed_pointer_html['input'] = [
            'type'        => true,
            'value'       => true,
            'name'        => true,
            'class'       => true,
            'id'          => true,
            'placeholder' => true,
            'required'    => true,
            'style'       => true,
            'tabindex'    => true,
        ];
        ?>

        <script type="text/javascript">
        //<![CDATA[
        jQuery( document ).ready( function( $ ) {
            $( '#menu-posts-wpsl_stores' ).pointer({
                content: '<?php echo wp_kses( $pointer_content, $allowed_pointer_html ); ?>',
                position: {
                    edge: 'left',
                    align: 'center'
                },
                pointerWidth: 350,
                close: function () {
                    $.post( ajaxurl, {
                        pointer: 'wpsl_signup_pointer',
                        action: 'dismiss-wp-pointer'
                    });
                }
            }).pointer( 'open' );

            // If a user clicked the "subscribe" button trigger the close button for the pointer.
            $( '.wpsl-mc-wrap #mc-embedded-subscribe' ).on( 'click', function() {
                $( '.wp-pointer .close' ).trigger( 'click' );
            });
        });
        //]]>
        </script>

        <?php
    }

    /**
     * Enqueue the required scripts for the admin pages.
     *
     * @since      1.0.0
     * @deprecated 3.0.0
     * @return     void
     */
    public function admin_scripts() {
        _deprecated_function( __FUNCTION__, '3.0.0', '\WPSL\Admin\Assets\Script_Data\get_settings()' );
    }
}