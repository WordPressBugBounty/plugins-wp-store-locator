<?php
/**
 * Marker management.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Markers\Custom_Markers;
use WPSL\Core\Settings\Manager as WpslSettings;
    
class Marker_Manager {

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
     * Show the options of the start and store markers.
     *
     * @since  1.0.0
     * @return string $marker_list The complete list of available and selected markers
     */
    public function show_options() {
        $marker_list   = '';
        $marker_images = $this->get_available_markers();
        $marker_type   = [
            'start'  => esc_html__( 'Start marker', 'wp-store-locator' ),
            'store'  => esc_html__( 'Store marker', 'wp-store-locator' ),
            'active' => esc_html__( 'Active store marker', 'wp-store-locator' )
        ];

        foreach ( $marker_type as $type => $text ) {
            if ( ! empty( $marker_images ) ) {
                $marker_list .= $text . '<ul class="wpsl-marker-list">';

                foreach ( $marker_images as $marker_img ) {
                    $marker_list .= $this->create_html( $marker_img, $type );
                }

                $marker_list .= $this->create_custom_html( $type );
                $marker_list .= '</ul>';
            }
        }

        return $marker_list;
    }

    /**
     * Render the marker picker HTML for the settings page.
     *
     * Three lists: start, store, and active store marker. The marker matching
     * the stored option value is marked checked.
     *
     * @since  1.0.0
     * @param  string $marker_img  The filename of the marker
     * @param  string $location    "start", "store", or "active"
     * @return string              HTML list of available markers
     */
    public function create_html( $marker_img, $location ) {
        $marker_path = ( defined( 'WPSL_MARKER_URI' ) ) ? WPSL_MARKER_URI : WPSL_URL . 'assets/img/frontend/markers/';
        $marker_list = '';

        $saved_marker = $this->settings->get( 'markers', $location . '_marker' );

        /**
         * A custom marker is saved as "custom:{id}" and has no extension, so it
         * must never be compared to a bundled filename -- pathinfo() would
         * reduce it to the raw value and could check a bundled marker next to
         * the custom one. Custom markers match in create_custom_html() instead.
         */
        if ( ! \wpsl_custom_marker_id( $saved_marker ) && pathinfo( $saved_marker, PATHINFO_FILENAME ) === pathinfo( $marker_img, PATHINFO_FILENAME ) ) {
            $checked   = 'checked="checked"';
            $css_class = 'class="wpsl-active-marker"';
        } else {
            $checked   = '';
            $css_class = '';
        }
        
        $marker_list .= '<li ' . $css_class . '>';
        $marker_list .= '<img src="' . esc_url( $marker_path . $marker_img ) . '" width="24" height="35" alt="' . esc_attr( \wpsl_create_alt_text( $marker_img ) . ' ' . __( 'marker', 'wp-store-locator' ) ) . '" />';
        $marker_list .= '<input ' . $checked . ' type="radio" name="wpsl_markers[' . esc_attr( $location ) . ']"  value="' . esc_attr( $marker_img ) . '" />';
        $marker_list .= '</li>';

        return $marker_list;
    }

    /**
     * Create the html for the custom markers created in the Marker Manager.
     *
     * These are appended to the same <ul class="wpsl-marker-list"> as the
     * bundled markers, so they behave like any other option in the picker. They
     * carry the marker id in a data attribute so the Marker Manager JS can keep
     * the list in sync while markers are created, edited or deleted, and their
     * radio value is "custom:{id}" instead of a filename.
     *
     * @since  3.0.0
     * @param  string $location    Either "start", "store" or "active".
     * @return string $marker_list The list items for the stored custom markers.
     */
    public function create_custom_html( $location ) {
        $custom_markers = \wpsl_get_service( 'custom_markers' );
        $marker_list    = '';

        if ( ! $custom_markers ) {
            return $marker_list;
        }

        $saved_marker = $this->settings->get( 'markers', $location . '_marker' );

        foreach ( $custom_markers->get_markers() as $marker ) {
            if ( empty( $marker['id'] ) ) {
                continue;
            }

            $data_uri = $custom_markers->get_data_uri( $marker['id'] );

            // An unknown marker produces no SVG, so there is nothing to show.
            if ( ! $data_uri ) {
                continue;
            }

            $value = 'custom:' . $marker['id'];
            $name  = isset( $marker['name'] ) ? $marker['name'] : '';

            /**
             * Not the bundled markers' fixed 24x35: a square or circular shape
             * would render as an oval in that box. Every shape's own size comes
             * from Custom_Markers::SHAPES, or -- for an image marker -- from its
             * own uploaded dimensions.
             */
            $size = isset( $marker['image_w'], $marker['image_h'] )
                ? Custom_Markers::get_image_render_size( $marker['image_w'], $marker['image_h'], isset( $marker['size'] ) ? $marker['size'] : Custom_Markers::DEFAULT_SIZE )
                : Custom_Markers::get_render_size( isset( $marker['shape'] ) ? $marker['shape'] : 'classic_pin', isset( $marker['size'] ) ? $marker['size'] : Custom_Markers::DEFAULT_SIZE );

            if ( $saved_marker === $value ) {
                $checked   = 'checked="checked"';
                $css_class = 'class="wpsl-active-marker"';
            } else {
                $checked   = '';
                $css_class = '';
            }

            $marker_list .= '<li ' . $css_class . ' data-custom-marker-id="' . esc_attr( $marker['id'] ) . '">';
            $marker_list .= '<img src="' . esc_attr( $data_uri ) . '" width="' . esc_attr( $size[0] ) . '" height="' . esc_attr( $size[1] ) . '" alt="' . esc_attr( $name ) . '" />';
            $marker_list .= '<input ' . $checked . ' type="radio" name="wpsl_markers[' . $location . ']"  value="' . esc_attr( $value ) . '" />';
            $marker_list .= '</li>';
        }

        return $marker_list;
    }

    /**
     * Create the three-column marker picker shown on the settings page.
     *
     * @since 3.0.0
     * @return string The marker picker HTML.
     */
    public function show_marker_pickers() {
        $types = [
            'start'  => __( 'Start marker', 'wp-store-locator' ),
            'store'  => __( 'Store marker', 'wp-store-locator' ),
            'active' => __( 'Active store marker', 'wp-store-locator' ),
        ];

        $pickable = \wpsl_pickable_markers();
        $bundled  = $pickable['bundled'];
        $custom   = $pickable['custom'];

        $studio_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' );
        $html       = '<div class="wpsl-marker-pickers">';

        foreach ( $types as $type => $label ) {
            $current = $this->settings->get( 'markers', $type . '_marker' );
            $html   .= $this->create_picker_html( $type, $label, $current, $bundled, $custom, $studio_url );
        }

        return $html . '</div>';
    }

    /**
     * Whether a saved marker filename still has a tile in the picker.
     *
     * @since  3.0.0
     * @param  string $value   The saved marker filename.
     * @param  array  $bundled Bundled markers, keyed by filename.
     * @return bool            True when one of the tiles matches the value.
     */
    private function has_bundled_match( $value, $bundled ) {
        if ( ! $value ) {
            return false;
        }

        foreach ( array_keys( $bundled ) as $filename ) {
            if ( pathinfo( $value, PATHINFO_FILENAME ) === pathinfo( $filename, PATHINFO_FILENAME ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create the markup for a single marker picker column.
     *
     * @since 3.0.0
     * @param  string $type       The marker type, "start", "store" or "active".
     * @param  string $label      The unescaped label text for the column.
     * @param  string $current    The currently saved marker value for this type.
     * @param  array  $bundled    Bundled markers, keyed by filename, valued by display name.
     * @param  array  $custom     Custom markers, keyed by "custom:{id}", valued by [ 'name', 'src', 'shape', 'dims' ].
     * @param  string $studio_url The Marker Studio admin URL.
     * @return string             The picker column HTML.
     */
    private function create_picker_html( $type, $label, $current, $bundled, $custom, $studio_url ) {
        $custom_id  = \wpsl_custom_marker_id( $current );
        $is_deleted = $custom_id && ! isset( $custom[ $current ] );
        $is_unknown = ! $custom_id && ! $this->has_bundled_match( $current, $bundled );

        $match_value        = ( $is_deleted || $is_unknown ) ? $this->settings->get_default( 'markers', $type . '_marker' ) : $current;
        $is_custom_selected = $custom_id && ! $is_deleted;

        $selected_style = '';

        if ( $is_custom_selected ) {
            $selected_name  = $custom[ $current ]['name'];
            $selected_src   = $custom[ $current ]['src'];
            $selected_style = ' style="max-height:' . esc_attr( Custom_Markers::get_picker_height( $custom[ $current ]['shape'], 34, $custom[ $current ]['dims'] ) ) . 'px"';
        } else {
            $selected_name = $match_value;
            $selected_src  = \wpsl_marker_src( $match_value );

            foreach ( $bundled as $filename => $name ) {
                if ( pathinfo( $match_value, PATHINFO_FILENAME ) === pathinfo( $filename, PATHINFO_FILENAME ) ) {
                    $selected_name = $name;
                    $selected_src  = \wpsl_marker_src( $filename );
                    break;
                }
            }
        }

        $label_id = 'wpsl-marker-picker-label-' . $type;
        $name_id  = 'wpsl-marker-picker-name-' . $type;

        $html  = '<div class="wpsl-marker-picker" data-marker-type="' . esc_attr( $type ) . '">';
        $html .= '<span class="wpsl-marker-picker-label" id="' . esc_attr( $label_id ) . '">' . esc_html( $label ) . '</span>';
        $html .= '<button type="button" class="wpsl-marker-picker-toggle" aria-expanded="false" aria-labelledby="' . esc_attr( $label_id . ' ' . $name_id ) . '">';

        $html .= '<img src="' . esc_attr( $selected_src ) . '"' . $selected_style . ' alt="" />';
        $html .= '<span class="wpsl-marker-picker-name" id="' . esc_attr( $name_id ) . '">' . esc_html( $selected_name ) . '</span>';
        $html .= '<span class="wpsl-marker-picker-arrow"></span>';
        $html .= '</button>';
        $html .= '<div class="wpsl-marker-picker-popover" hidden>';
        $html .= '<p class="wpsl-marker-picker-group">' . esc_html__( 'Default markers', 'wp-store-locator' ) . '</p>';
        $html .= '<div class="wpsl-marker-picker-grid">';

        foreach ( $bundled as $filename => $name ) {
            $is_selected = ! $is_custom_selected && pathinfo( $match_value, PATHINFO_FILENAME ) === pathinfo( $filename, PATHINFO_FILENAME );
            $selected    = $is_selected ? ' is-selected' : '';
            $html       .= '<button type="button" class="wpsl-marker-picker-tile' . $selected . '" data-marker="' . esc_attr( $filename ) . '" data-name="' . esc_attr( $name ) . '" title="' . esc_attr( $name ) . '">';
            $html       .= '<img src="' . esc_attr( \wpsl_marker_src( $filename ) ) . '" alt="" /></button>';
        }

        $html .= '<button type="button" class="wpsl-marker-picker-add"><span class="wpsl-marker-picker-add-icon" aria-hidden="true">+</span> ' . esc_html__( 'Color', 'wp-store-locator' ) . '</button>';
        $html .= '</div>';

        if ( $custom ) {
            $html .= '<p class="wpsl-marker-picker-group">' . esc_html__( 'Custom markers', 'wp-store-locator' ) . '</p>';
            $html .= '<div class="wpsl-marker-picker-grid">';

            foreach ( $custom as $value => $marker ) {
                $selected     = ( $current === $value ) ? ' is-selected' : '';
                $tile_height  = Custom_Markers::get_picker_height( $marker['shape'], 34, $marker['dims'] );
                $html        .= '<button type="button" class="wpsl-marker-picker-tile' . $selected . '" data-marker="' . esc_attr( $value ) . '" data-name="' . esc_attr( $marker['name'] ) . '" title="' . esc_attr( $marker['name'] ) . '">';
                $html        .= '<img src="' . esc_attr( $marker['src'] ) . '" style="max-height:' . esc_attr( $tile_height ) . 'px" alt="" /></button>';
            }

            $html .= '</div>';
        }

        $html .= '<div class="wpsl-marker-picker-panel" hidden></div>';
        $html .= '<template class="wpsl-marker-picker-tile-template"><button type="button" class="wpsl-marker-picker-tile" data-marker="" data-name="" title=""><img src="" alt="" /></button></template>';

        $html .= '<div class="wpsl-marker-picker-footer">';
        $html .= '<a class="wpsl-marker-picker-new" href="' . esc_url( $studio_url ) . '"><span class="wpsl-marker-picker-add-icon" aria-hidden="true">+</span> ' . esc_html__( 'New Marker', 'wp-store-locator' ) . '</a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<input type="hidden" name="wpsl_markers[' . esc_attr( $type ) . ']" value="' . esc_attr( $current ) . '" />';
        $html .= '</div>';

        return $html;
    }

    /**
     * A picker row's retina src, as a ready-to-print attribute.
     *
     * @since  3.0.0
     * @param  string $value The row's marker value.
     * @param  string $src   The row's standard image src.
     * @return string An escaped attribute, or '' when the standard src stands.
     */
    private function retina_src_attribute( $value, $src ) {
        $retina = \wpsl_marker_retina_src( $value );

        if ( ! $retina || $retina === $src ) {
            return '';
        }

        return ' data-retina-src="' . esc_attr( $retina ) . '"';
    }

    /**
     * Render a single marker dropdown ( the store editor's Location Marker
     * control ).
     *
     * @since 3.0.0
     * @param string $type    The marker type the dropdown selects for.
     * @param array  $options {
     *     The dropdown contents.
     *
     *     @type string $saved         The saved marker value, '' for the default item.
     *     @type array  $bundled       Bundled markers, keyed by filename, valued by display name.
     *     @type array  $custom        Custom markers, keyed by "custom:{id}", valued by [ 'name', 'src' ].
     *     @type string $default_src   The image src of the default item.
     *     @type string $default_value The marker value behind the default item, for its preview cap.
     *     @type string $default_label Where the default comes from, e.g. "from settings".
     *     @type string $input_name    The hidden input's name attribute.
     * }
     * @return void
     */
    public function render_marker_dropdown( $type, $options ) {

        $saved    = $options['saved'];
        $selected = $saved ? \wpsl_marker_src( $saved ) : $options['default_src'];

        $label_id = 'wpsl-lm-type-label-' . $type;
        $name_id  = 'wpsl-lm-name-' . $type;

        if ( $saved && isset( $options['custom'][ $saved ] ) ) {
            $selected_name = $options['custom'][ $saved ]['name'];
        } elseif ( $saved && isset( $options['bundled'][ $saved ] ) ) {
            $selected_name = $options['bundled'][ $saved ];
        } else {
            $saved         = ''; // A stale value that no longer resolves renders as Default.
            $selected      = $options['default_src'];
            $selected_name = __( 'Default', 'wp-store-locator' );
        }

        ?>
        <div class="wpsl-lm-dropdown" data-marker-type="<?php echo esc_attr( $type ); ?>">
            <button type="button" class="wpsl-lm-toggle" aria-expanded="false" aria-haspopup="true" aria-labelledby="<?php echo esc_attr( $label_id . ' ' . $name_id ); ?>">
                <span class="wpsl-lm-art"><img src="<?php echo esc_attr( $selected ); ?>"<?php echo \wpsl_marker_preview_style( $saved ? $saved : $options['default_value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?> alt="" /></span>
                <span class="wpsl-lm-name" id="<?php echo esc_attr( $name_id ); ?>"><?php echo esc_html( $selected_name ); ?></span>
                <span class="wpsl-lm-arrow"></span>
            </button>
            <div class="wpsl-lm-menu" hidden>
                <button type="button" class="wpsl-lm-item wpsl-lm-default<?php echo ! $saved ? ' selected' : ''; ?>" data-marker=""<?php echo $this->retina_src_attribute( $options['default_value'], $options['default_src'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?>>
                    <span class="wpsl-lm-art"><img src="<?php echo esc_attr( $options['default_src'] ); ?>"<?php echo \wpsl_marker_preview_style( $options['default_value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?> alt="" /></span>
                    <span><?php esc_html_e( 'Default', 'wp-store-locator' ); ?><small><?php echo esc_html( $options['default_label'] ); ?></small></span>
                </button>
                <div class="wpsl-lm-group"><?php esc_html_e( 'Default markers', 'wp-store-locator' ); ?></div>
                <?php
                foreach ( $options['bundled'] as $filename => $name ) {
                    $bundled_src = \wpsl_marker_src( $filename );
                    ?>
                    <button type="button" class="wpsl-lm-item<?php echo $saved === $filename ? ' selected' : ''; ?>" data-marker="<?php echo esc_attr( $filename ); ?>"<?php echo $this->retina_src_attribute( $filename, $bundled_src ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?>>
                        <span class="wpsl-lm-art"><img src="<?php echo esc_attr( $bundled_src ); ?>" alt="" /></span>
                        <span><?php echo esc_html( $name ); ?></span>
                    </button>
                <?php } ?>
                <?php if ( $options['custom'] ) { ?>
                    <div class="wpsl-lm-group"><?php esc_html_e( 'Custom markers', 'wp-store-locator' ); ?></div>
                    <?php foreach ( $options['custom'] as $value => $marker ) { ?>
                        <button type="button" class="wpsl-lm-item<?php echo $saved === $value ? ' selected' : ''; ?>" data-marker="<?php echo esc_attr( $value ); ?>">
                            <span class="wpsl-lm-art"><img src="<?php echo esc_attr( $marker['src'] ); ?>"<?php echo \wpsl_marker_preview_style( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?> alt="" /></span>
                            <span><?php echo esc_html( $marker['name'] ); ?></span>
                        </button>
                    <?php } ?>
                <?php } ?>
            </div>
            <input type="hidden" name="<?php echo esc_attr( $options['input_name'] ); ?>" value="<?php echo esc_attr( $saved ); ?>" />
        </div>
        <?php
    }

    /**
     * Render two buttons for creating a new marker (upload an image or open
     * the Marker Studio), once per screen.
     *
     * @since 3.0.0
     * @return void
     */
    public function render_marker_creation_links() {
        $studio_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' );
        ?>
        <div class="wpsl-lm-create">
            <a class="wpsl-lm-create-link" href="<?php echo esc_url( $studio_url ); ?>">
                <span class="wpsl-lm-create-glyph" aria-hidden="true">+</span>
                <span><?php esc_html_e( 'Design a Marker', 'wp-store-locator' ); ?><small><?php esc_html_e( 'Shapes, colors and icons', 'wp-store-locator' ); ?></small></span>
            </a>
            <a class="wpsl-lm-create-link" href="<?php echo esc_url( $studio_url . '#image-marker' ); ?>">
                <span class="wpsl-lm-create-glyph" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" /></svg></span>
                <span><?php esc_html_e( 'Use Your Own Image', 'wp-store-locator' ); ?><small><?php esc_html_e( 'Shown as-is on the map', 'wp-store-locator' ); ?></small></span>
            </a>
        </div>
        <?php
    }

    /**
     * Load the markers that are used on the map.
     *
     * @since  1.0.0
     * @return array $marker_images A list of all the available markers.
     */
    public function get_available_markers() {
        return \wpsl_bundled_markers();
    }
}