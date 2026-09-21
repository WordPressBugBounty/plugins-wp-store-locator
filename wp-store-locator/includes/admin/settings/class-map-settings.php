<?php
/**
 * Handle the map settings.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Map_Settings {

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Constructor.
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;
    }
    
    /**
     * Get the map style code
     *
     * @since  3.0.0
     * @param  string $map_service The name of the map service
     * @return string $map_style   The code to style the map (JSON string)
     */
    public function get_map_style( $map_service ) {
        $map_style = '';
        
        // Get the map style settings from the appearance group
        $map_styles = $this->settings->get( 'appearance', 'map_style' );
        
        if ( isset( $map_styles[$map_service] ) && isset( $map_styles[$map_service]['json'] ) ) {
            $stored_value = $map_styles[$map_service]['json'];
            
            // Check if the stored value is already an array or object
            if ( is_array( $stored_value ) || is_object( $stored_value ) ) {
                $map_style = wp_json_encode( $stored_value );
            } else {
                $decoded = json_decode( $stored_value );
                
                if ( $decoded !== null ) {
                    if ( is_array( $decoded ) || is_object( $decoded ) ) {
                        $map_style = wp_json_encode( $decoded );
                    } else {
                        $map_style = wp_strip_all_tags( stripslashes( $decoded ) );
                    }
                }
            }
        }

        return $map_style;
    }

    /**
     * Get the available Mapbox styles.
     *
     * @since  3.0.0
     * @see    https://docs.mapbox.com/api/maps/styles/#mapbox-styles
     * @return string $mapbox_styles The HTMl to render the different Mapbox style options
     */
    public function mapbox_styles() {
        if ( ! $this->settings->get( 'api', 'mapbox_key' ) ) {
            /* translators: %1$s: opening link tag to API settings, %2$s: closing link tag */
            $mapbox_styles = sprintf( esc_html__( 'To use the Map styles, you need to enter a Mapbox %1$sAPI key%2$s', 'wp-store-locator' ), '<a class="wpsl-trigger-nav" data-item="api" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) . '"> ', '</a>' );
        } else {
            $styles = wpsl_mapbox_classic_styles();
            $active_map_provider = $this->settings->get( 'api', 'active_map_service' );
            
            /**
             * Remove both standard Mapbox styles if the active map provider is OSM.
             * 
             * We do this because they are not supported by the OSM map.
             */
            if ( $active_map_provider === 'osm' || $active_map_provider === 'stadia' ) {
                unset( $styles['standard'] );
                unset( $styles['standard-satellite'] );
            }
            
            $selected_status = $this->is_mapbox_style_active( 'custom' );

            $mapbox_styles = '<p>';
            /* translators: %1$s: opening link tag to Mapbox Studio, %2$s: closing link tag */
            $mapbox_styles .= '<label for="wpsl-style-url">' . esc_html__( 'Mapbox Studio Style URL', 'wp-store-locator' ) . '<span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">' . sprintf( __( 'You can create your custom Mapbox style %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://studio.mapbox.com/">', '</a>' ) .'</span></span></label>';
           
            $map_style = $this->settings->get( 'appearance', 'map_style' );
            $custom_url = '';

            if ( isset( $map_style['mapbox'] ) && isset( $map_style['mapbox']['custom_url'] ) ) {
                $custom_url = $map_style['mapbox']['custom_url'];
            }

            $mapbox_styles .= '<input type="text" name="wpsl_appearance[map][style][mapbox][custom_url]" value="' . esc_attr( $custom_url ) . '" id="wpsl-style-url">';
            $mapbox_styles .= '<input type="radio" ' . $selected_status['radio'] . ' id="wpsl-mapbox-custom" value="custom" name="wpsl_appearance[map][style][mapbox][selected]">';
            $mapbox_styles .= '<label for="wpsl-style-url" role="radio" ' . $selected_status['aria'] . '></label>';
            $mapbox_styles .= '</p>';

            $mapbox_styles .= '<p><strong>' . esc_html__( 'Mapbox Styles', 'wp-store-locator' ) . '</strong></p>';

            $mapbox_styles .= '<ul class="wpsl-mapbox-style-options">';

            foreach ( $styles as $style => $url ) {
                $selected_status = $this->is_mapbox_style_active( $style );

                $mapbox_styles .= '<li>';
                $mapbox_styles .= '<label for="wpsl-mapbox-' . esc_attr( $style ) . '" role="radio" ' . $selected_status['aria'] . ' tabindex="0">' . esc_html( str_replace( '-', ' ', ucwords( $style ) ) ) . '<img ' . $selected_status['css'] . ' alt="' . esc_attr( ucfirst( str_replace( '-', ' ', $style ) ) ) . '" src="' . WPSL_URL . 'assets/img/admin/mapbox-styles/' . esc_attr( $style ) . '.png" /></label>';
                $mapbox_styles .= '<input type="radio" ' . $selected_status['radio'] . ' id="wpsl-mapbox-' . esc_attr( $style ) . '" name="wpsl_appearance[map][style][mapbox][selected]" value="' . esc_attr( $style ) . '" data-url="' . esc_attr( $url ).'">';
                $mapbox_styles .= '</li>';
            }

            $mapbox_styles .= '</ul>';
        }

        return $mapbox_styles;
    }

    /**
     * Render the custom MapLibre style JSON URL section.
     *
     * Shown when the 'maplibre' tile / style source is selected for the
     * Leaflet based providers ( osm / stadia ). The URL is shared between
     * them and used to live under openfreemap.custom_url, so the field
     * prefills from there for a site that saved it before the move.
     *
     * @since  3.0.0
     * @return string
     */
    public function maplibre_styles() {
        $map_style  = $this->settings->get( 'appearance', 'map_style' );
        $custom_url = isset( $map_style['maplibre']['custom_url'] ) ? $map_style['maplibre']['custom_url'] : '';

        if ( ! $custom_url && ! empty( $map_style['openfreemap']['custom_url'] ) ) {
            $custom_url = $map_style['openfreemap']['custom_url'];
        }

        $html  = '<p>';
        /* translators: %1$s: opening link tag to Maputnik, %2$s: closing link tag */
        $html .= '<label for="wpsl-maplibre-style-url">' . esc_html__( 'Style URL', 'wp-store-locator' ) . '<span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">' . sprintf( __( 'Paste a MapLibre style JSON URL. Design your own with %1$sMaputnik%2$s and link to the exported style, not the editor page. An {api_key} placeholder in the URL is filled with your Stadia Maps key.', 'wp-store-locator' ), '<a target="_blank" href="https://maputnik.github.io">', '</a>' ) . '</span></span></label>';
        $html .= '<input type="text" name="wpsl_appearance[map][style][maplibre][custom_url]" value="' . esc_attr( $custom_url ) . '" id="wpsl-maplibre-style-url" placeholder="https://example.com/style.json">';

        /**
         * The apply-style toggle.
         */
        $enabled = ! isset( $map_style['maplibre']['enabled'] ) || $map_style['maplibre']['enabled'];

        $html .= '<input type="checkbox" value="1" ' . ( $enabled ? 'checked="checked" ' : '' ) . 'id="wpsl-maplibre-enabled" name="wpsl_appearance[map][style][maplibre][enabled]" aria-label="' . esc_attr__( 'Apply style', 'wp-store-locator' ) . '" title="' . esc_attr__( 'Apply style', 'wp-store-locator' ) . '">';
        $html .= '</p>';

        return $html;
    }

    /**
     * Render the OpenFreeMap style picker (3 presets).
     *
     * No API key is required, so this is always available when the OSM
     * "OpenFreeMap styles" tile source is selected. The custom style URL
     * moved to its own 'MapLibre style URL' tile source ( maplibre_styles() ).
     *
     * @since  3.0.0
     * @return string
     */
    public function openfreemap_styles() {
        $styles    = wpsl_openfreemap_styles();
        $map_style = $this->settings->get( 'appearance', 'map_style' );

        $selected = isset( $map_style['openfreemap']['selected'] ) ? $map_style['openfreemap']['selected'] : 'liberty';

        // The legacy 'custom' selection now lives under the maplibre tile source.
        if ( 'custom' === $selected ) {
            $selected = 'liberty';
        }

        $html  = '<p><strong>' . esc_html__( 'OpenFreeMap Styles', 'wp-store-locator' ) . '</strong></p>';
        $html .= '<ul class="wpsl-openfreemap-style-options">';

        foreach ( $styles as $style => $url ) {
            $is_selected = ( $style === $selected );
            $img_css     = $is_selected ? 'class="wpsl-selected-openfreemap-style"' : '';
            $aria        = $is_selected ? 'aria-checked="true"' : 'aria-checked="false"';

            $html .= '<li>';
            $html .= '<label for="wpsl-openfreemap-' . esc_attr( $style ) . '" role="radio" ' . $aria . ' tabindex="0">' . esc_html( ucfirst( $style ) ) . '<img ' . $img_css . ' data-no-retina alt="' . esc_attr( ucfirst( $style ) ) . '" src="' . WPSL_URL . 'assets/img/admin/openfreemap-styles/' . esc_attr( $style ) . '.png" /></label>';
            $html .= '<input type="radio" ' . checked( $selected, $style, false ) . ' id="wpsl-openfreemap-' . esc_attr( $style ) . '" name="wpsl_appearance[map][style][openfreemap][selected]" value="' . esc_attr( $style ) . '" data-url="' . esc_attr( $url ) . '">';
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Get the available Stadia Maps styles.
     *
     * @since  3.0.0
     * @see    https://docs.stadiamaps.com/themes/
     * @param  string $context       The settings group the radio field names are stored under ( 'osm' or 'stadia' ). Default 'osm'.
     * @return string $stadia_styles The HTML to render the different Stadia Maps style options
     */
    public function stadia_styles( $context = 'osm' ) {
        if ( ! $this->settings->get( 'api', 'stadia_key' ) ) {
            /* translators: %1$s: opening link tag to API settings, %2$s: closing link tag */
            $stadia_styles = sprintf( esc_html__( 'A Stadia Maps %1$sAPI key%2$s is required', 'wp-store-locator' ), '<a class="wpsl-trigger-nav" data-item="api" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) . '"> ', '</a>' );
        } else {
            $styles = wpsl_stadia_styles();
            $map_styles = $this->settings->get( 'appearance', 'map_style' );
            $saved_style = isset( $map_styles['stadia']['selected_style'] ) ? $map_styles['stadia']['selected_style'] : '';
           
            if ( ! $saved_style && isset( $map_styles['osm']['selected_style'] ) ) {
                $saved_style = $map_styles['osm']['selected_style'];
            }

            if ( ! $saved_style ) {
                $saved_style = 'alidade_smooth';
            }

            $stadia_styles = '<ul class="wpsl-stadia-style-options" role="radiogroup">';

            foreach ( $styles as $style => $label ) {
                $is_active  = ( $saved_style === $style );
                $checked    = $is_active ? 'checked="checked"' : '';
                $aria       = $is_active ? 'aria-checked="true"' : 'aria-checked="false"';
                $img_class  = $is_active ? 'class="wpsl-selected-stadia-style"' : '';
                $img_url    = WPSL_URL . 'assets/img/admin/stadia-styles/' . esc_attr( $style ) . '.png';

                $stadia_styles .= '<li>';
                $stadia_styles .= '<label for="wpsl-stadia-' . esc_attr( $style ) . '" role="radio" ' . $aria . ' tabindex="0">';
                $stadia_styles .= esc_html( $label );
                $stadia_styles .= '<img alt="' . esc_attr( $label ) . '" src="' . esc_url( $img_url ) . '" ' . $img_class . '>';
                $stadia_styles .= '</label>';
                $stadia_styles .= '<input type="radio" ' . $checked . ' id="wpsl-stadia-' . esc_attr( $style ) . '" name="wpsl_appearance[map][style][' . esc_attr( $context ) . '][selected_style]" value="' . esc_attr( $style ) . '">';
                $stadia_styles .= '</li>';
            }

            $stadia_styles .= '</ul>';
        }

        return $stadia_styles;
    }

    /**
     * Check if we need to set the
     * passed Mapbox style to active.
     *
     * @since  3.0.0
     * @param  string $style    The name of the style
     * @return array  $selected The checked state of the radio button and the css class based on the style being active or not.
     */
    public function is_mapbox_style_active( $style ) {
        $selected = [
            'radio' => '',
            'css'   => '',
            'aria'  => 'aria-checked="false"',
        ];

        $map_styles = $this->settings->get( 'appearance', 'map_style' );

        // Ensure we have the mapbox data
        if ( ! isset( $map_styles['mapbox'] ) ) {
            return $selected;
        }

        $saved_selected = isset( $map_styles['mapbox']['selected'] ) ? trim( (string) $map_styles['mapbox']['selected'] ) : '';
        $current_style = trim( (string) $style );

        // Compare the saved selected style with the current style
        if ( ! empty( $saved_selected ) && $saved_selected === $current_style ) {
            $selected['radio'] = 'checked="checked"';
            $selected['css'] = 'class="wpsl-selected-mapbox-style"';
            $selected['aria'] = 'aria-checked="true"';
        }

        return $selected;
    }
}