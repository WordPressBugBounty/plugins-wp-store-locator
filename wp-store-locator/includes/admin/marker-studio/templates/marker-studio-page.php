<?php
/**
 * Marker Studio Page Template
 *
 * The page header and two panes ( library / editor ), with a static SVG
 * preview card at the top of the editor pane and a link out to the online
 * marker builder at markerstudio.app.
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Markers\Custom_Markers;
use WPSL\Core\Markers\Lucide_Icons;
use WPSL\Core\Markers\Phosphor_Icons;

/*
 * The shape toggles, in the order the design shows them. The labels are the
 * only thing hardcoded here: each glyph is drawn by the SVG builder itself, so
 * a shape whose path changes in Custom_Markers cannot end up with a toggle that
 * still shows the old silhouette.
 */
$wpsl_ms_shapes = [
    'classic_pin'   => __( 'Classic pin', 'wp-store-locator' ),
    'rounded_pin'   => __( 'Rounded pin', 'wp-store-locator' ),
    'open_pin'      => __( 'Open pin', 'wp-store-locator' ),
    'circle'        => __( 'Circle', 'wp-store-locator' ),
    'square'        => __( 'Square', 'wp-store-locator' ),
    'shield'        => __( 'Shield', 'wp-store-locator' ),
    'diamond'       => __( 'Diamond', 'wp-store-locator' ),
    'hexagon'       => __( 'Hexagon', 'wp-store-locator' ),
    'speech_bubble' => __( 'Speech bubble', 'wp-store-locator' ),
    'flag'          => __( 'Flag', 'wp-store-locator' ),
];

/*
 * The three color fields. Each is a WPSL_ColorPicker ( class="wpsl-color-field" +
 * data-default, which wpsl-color-picker.js picks up on document ready ).
 */
$wpsl_ms_colors = [
    [
        'id'      => 'wpsl-ms-fill',
        'label'   => __( 'Fill', 'wp-store-locator' ),
        'default' => '#1e5b83',
    ],
    [
        'id'      => 'wpsl-ms-icon-color',
        'label'   => __( 'Icon', 'wp-store-locator' ),
        'default' => '#ffffff',
    ],
    [
        'id'      => 'wpsl-ms-stroke',
        'label'   => __( 'Outline', 'wp-store-locator' ),
        'default' => '#093857',
    ],
    /*
     * Only reachable on a shape that cuts a hole, and only once its "Fill the
     * center" switch is on -- the JS shows and hides the row. It is rendered
     * unconditionally so the control exists to be toggled: the shape can
     * change without the page reloading.
     */
    [
        'id'      => 'wpsl-ms-center-color',
        'label'   => __( 'Center', 'wp-store-locator' ),
        'default' => '#1e5b83',
    ],
];

/*
 * Both icon libraries in one map: Lucide ( stroke glyphs ) first, Phosphor's
 * Fill weight ( solid glyphs, "ph-" prefixed keys ) after it.
 */
$wpsl_ms_icons = array_merge( Lucide_Icons::get_all(), Phosphor_Icons::get_all() );

/*
 * The saved markers the library lists.
 */
$wpsl_ms_repository = wpsl_get_service( 'custom_markers' );
$wpsl_ms_markers    = $wpsl_ms_repository->get_markers();

/**
 * One library card.
 *
 * @param  array $marker   The saved marker, or an empty array for the template.
 * @param  string $data_uri The marker's artwork.
 * @return void
 */
$wpsl_ms_render_card = function( $marker, $data_uri ) {
    $id    = isset( $marker['id'] ) ? $marker['id'] : '';
    $name  = isset( $marker['name'] ) ? $marker['name'] : '';
    $shape = isset( $marker['shape'] ) ? $marker['shape'] : 'classic_pin';
    $size  = isset( $marker['size'] ) ? $marker['size'] : Custom_Markers::DEFAULT_SIZE;

    $dims = isset( $marker['image_w'], $marker['image_h'] )
        ? [ (int) $marker['image_w'], (int) $marker['image_h'] ]
        : null;

    // The box the artwork wants, written onto the <img> so a library of 40
    // markers does not reflow as the data URIs decode.
    $render_size = ( 'image' === $shape && $dims )
        ? Custom_Markers::get_image_render_size( $dims[0], $dims[1], $size )
        : Custom_Markers::get_render_size( $shape, $size );

    $cap = Custom_Markers::get_picker_height( $shape, 34, $dims );
    ?>
    <div class="wpsl-ms-card" data-marker-id="<?php echo esc_attr( $id ); ?>" role="listitem">
        <button type="button" class="wpsl-ms-card-open" aria-pressed="false">
            <span class="wpsl-ms-card-art"><img src="<?php echo esc_attr( $data_uri ); ?>" width="<?php echo esc_attr( $render_size[0] ); ?>" height="<?php echo esc_attr( $render_size[1] ); ?>" style="max-height:<?php echo esc_attr( $cap ); ?>px" alt="" decoding="async"<?php echo $data_uri ? '' : ' hidden'; ?>></span>
            <span class="wpsl-ms-card-name"><?php echo esc_html( $name ); ?></span>
        </button>
        <?php
        /*
         * Per-card actions: edit, duplicate, and delete, stacked vertically in
         * the card's top-right corner and revealed on :hover / :focus-within,
         * always visible on coarse pointers.
         */
        ?>
        <div class="wpsl-ms-card-actions">
            <button type="button" class="wpsl-ms-icon-button wpsl-ms-card-edit" title="<?php esc_attr_e( 'Edit', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Edit this marker', 'wp-store-locator' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /><path d="m15 5 4 4" /></svg>
            </button>
            <button type="button" class="wpsl-ms-icon-button wpsl-ms-card-duplicate" title="<?php esc_attr_e( 'Duplicate', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Duplicate this marker', 'wp-store-locator' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
            </button>
            <button type="button" class="wpsl-ms-icon-button wpsl-ms-danger wpsl-ms-card-delete" title="<?php esc_attr_e( 'Delete', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Delete this marker', 'wp-store-locator' ); ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
            </button>
        </div>
        <?php
        if ( $id ) {
            /**
             * Per-marker controls inside a Marker Studio library card.
             *
             * @since 3.0.0
             * @param array $marker The saved marker.
             */
            echo '<div class="wpsl-ms-card-foot">';
            do_action( 'wpsl_marker_manager_row_controls', $marker );
            echo '</div>';
        }
        ?>
    </div>
    <?php
};
?>
<div id="wpsl-content-wrap" class="wpsl-settings-grid wpsl-marker-studio">
    <div class="wpsl-ms-sidebar" id="wpsl-ms-editor">
        <div class="wpsl-ms-sidebar-body">
            <div class="wpsl-ms-preview" id="wpsl-ms-preview">
                <div class="wpsl-ms-preview-map" id="wpsl-ms-preview-map"></div>
            </div>
            <div class="wpsl-ms-group">
                <span class="wpsl-ms-label" id="wpsl-ms-shapes-label"><?php esc_html_e( 'Shape', 'wp-store-locator' ); ?></span>
                <div class="wpsl-ms-shapes" id="wpsl-ms-shapes" role="group" aria-labelledby="wpsl-ms-shapes-label">
                    <?php foreach ( $wpsl_ms_shapes as $wpsl_ms_shape => $wpsl_ms_shape_label ) : ?>
                        <button type="button" class="wpsl-ms-shape" data-shape="<?php echo esc_attr( $wpsl_ms_shape ); ?>" aria-pressed="<?php echo ( 'classic_pin' === $wpsl_ms_shape ) ? 'true' : 'false'; ?>" title="<?php echo esc_attr( $wpsl_ms_shape_label ); ?>">
                            <?php 
                            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Custom_Markers::get_svg_marker() escapes every dynamic value it builds in, and the shape is one of this page's own keys.
                            echo Custom_Markers::get_svg_marker( [
                                'shape'        => $wpsl_ms_shape,
                                'fill_color'   => 'currentColor',
                                'stroke_color' => 'none',
                                'stroke_width' => 0,
                                'shadow'       => false,
                            ] );
                            // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                            <span class="wpsl-visually-hidden"><?php echo esc_html( $wpsl_ms_shape_label ); ?></span>
                        </button>
                    <?php endforeach; ?>
                    <button type="button" class="wpsl-ms-shape wpsl-ms-shape-image" data-shape="image" aria-pressed="false" title="<?php esc_attr_e( 'My Image', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" /></svg>
                        <span class="wpsl-visually-hidden"><?php esc_html_e( 'My Image', 'wp-store-locator' ); ?></span>
                    </button>
                </div>
            </div>

            <div class="wpsl-ms-group wpsl-ms-group-colors">
                <span class="wpsl-ms-label"><?php esc_html_e( 'Colors', 'wp-store-locator' ); ?></span>
                <div class="wpsl-ms-switch" id="wpsl-ms-center-fill-row" hidden>
                    <label for="wpsl-ms-center-fill"><?php esc_html_e( 'Fill the center', 'wp-store-locator' ); ?></label>
                    <input type="checkbox" id="wpsl-ms-center-fill">
                </div>
                <div class="wpsl-ms-switch" id="wpsl-ms-image-fill-row" hidden>
                    <label for="wpsl-ms-image-fill"><?php esc_html_e( 'Fill the background', 'wp-store-locator' ); ?></label>
                    <input type="checkbox" id="wpsl-ms-image-fill" checked>
                </div>

                <?php foreach ( $wpsl_ms_colors as $wpsl_ms_color ) : ?>
                    <div class="wpsl-ms-color" data-color-id="<?php echo esc_attr( $wpsl_ms_color['id'] ); ?>"<?php echo ( 'wpsl-ms-center-color' === $wpsl_ms_color['id'] ) ? ' hidden' : ''; ?>>
                        <div class="wpsl-ms-color-head">
                            <label class="wpsl-ms-color-label" for="<?php echo esc_attr( $wpsl_ms_color['id'] ); ?>"><?php echo esc_html( $wpsl_ms_color['label'] ); ?></label>
                            <span class="wpsl-ms-hex" data-hex-for="<?php echo esc_attr( $wpsl_ms_color['id'] ); ?>" aria-hidden="true"><?php echo esc_html( $wpsl_ms_color['default'] ); ?></span>
                            <input type="text" id="<?php echo esc_attr( $wpsl_ms_color['id'] ); ?>" class="wpsl-color-field" value="<?php echo esc_attr( $wpsl_ms_color['default'] ); ?>" data-default="<?php echo esc_attr( $wpsl_ms_color['default'] ); ?>" data-picker-position="left">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="wpsl-ms-group wpsl-ms-group-glyph" id="wpsl-ms-glyph-group">
                <span class="wpsl-ms-label" id="wpsl-ms-glyph-label"><?php esc_html_e( 'Icon', 'wp-store-locator' ); ?></span>

                <div class="wpsl-ms-label-row" id="wpsl-ms-label-row" hidden>
                    <label class="wpsl-ms-label-row-label" for="wpsl-ms-label-text"><?php esc_html_e( 'Text', 'wp-store-locator' ); ?></label>
                    <span class="wpsl-info wpsl-ms-label-info" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'What the Text field accepts', 'wp-store-locator' ); ?>">
                        <span class="wpsl-info-text wpsl-hide" id="wpsl-ms-label-hint"><?php esc_html_e( 'Up to 2 characters, or 1 wide character ( Japanese, Chinese, Korean, emoji ).', 'wp-store-locator' ); ?></span>
                    </span>
                    <input type="text" id="wpsl-ms-label-text" class="wpsl-ms-text wpsl-ms-label-input" autocomplete="off" spellcheck="false" maxlength="12" aria-describedby="wpsl-ms-label-hint">
                </div>

                <div class="wpsl-ms-slider wpsl-ms-slider-icon-size">
                    <div class="wpsl-ms-slider-head">
                        <label for="wpsl-ms-icon-size"><?php esc_html_e( 'Icon size', 'wp-store-locator' ); ?></label>
                        <output class="wpsl-ms-readout" id="wpsl-ms-icon-size-value" aria-hidden="true">100%</output>
                    </div>
                    <input type="range" id="wpsl-ms-icon-size" min="50" max="200" step="10" value="100">
                </div>
            </div>

            <div class="wpsl-ms-group wpsl-ms-group-form">
                <span class="wpsl-ms-label"><?php esc_html_e( 'Form', 'wp-store-locator' ); ?></span>

                <div class="wpsl-ms-slider wpsl-ms-slider-radius">
                    <div class="wpsl-ms-slider-head">
                        <label for="wpsl-ms-radius"><?php esc_html_e( 'Corner radius', 'wp-store-locator' ); ?></label>
                        <output class="wpsl-ms-readout" id="wpsl-ms-radius-value" aria-hidden="true">0%</output>
                    </div>
                    <input type="range" id="wpsl-ms-radius" min="0" max="50" step="5" value="0">
                </div>

                <div class="wpsl-ms-slider wpsl-ms-slider-outline">
                    <div class="wpsl-ms-slider-head">
                        <label for="wpsl-ms-outline"><?php esc_html_e( 'Outline thickness', 'wp-store-locator' ); ?></label>
                        <output class="wpsl-ms-readout" id="wpsl-ms-outline-value" aria-hidden="true"><?php echo esc_html( sprintf( /* translators: %d: a number of pixels. */ __( '%dpx', 'wp-store-locator' ), 1 ) ); ?></output>
                    </div>
                    <input type="range" id="wpsl-ms-outline" min="0" max="8" step="1" value="1">
                </div>

                <div class="wpsl-ms-slider wpsl-ms-slider-size">
                    <div class="wpsl-ms-slider-head">
                        <label for="wpsl-ms-size"><?php esc_html_e( 'Size', 'wp-store-locator' ); ?></label>
                        <output class="wpsl-ms-readout" id="wpsl-ms-size-value" aria-hidden="true"><?php echo esc_html( sprintf( /* translators: %d: a number of pixels. */ __( '%dpx', 'wp-store-locator' ), Custom_Markers::DEFAULT_SIZE ) ); ?></output>
                    </div>
                    <input type="range" id="wpsl-ms-size" min="28" max="88" step="2" value="<?php echo esc_attr( Custom_Markers::DEFAULT_SIZE ); ?>">
                </div>

                <div class="wpsl-ms-switch wpsl-ms-switch-frame">
                    <label for="wpsl-ms-frame"><?php esc_html_e( 'Frame the image', 'wp-store-locator' ); ?></label>
                    <input type="checkbox" id="wpsl-ms-frame">
                </div>
                <div class="wpsl-ms-switch wpsl-ms-switch-shadow">
                    <label for="wpsl-ms-shadow"><?php esc_html_e( 'Drop shadow', 'wp-store-locator' ); ?></label>
                    <input type="checkbox" id="wpsl-ms-shadow">
                </div>
            </div>
        </div>
    </div>

    <?php
    /*
     * Main column -- tabbed ( Design / Library ).
     */
    ?>
    <div class="wpsl-ms-main">
        <div class="wpsl-ms-header">
            <h1><?php esc_html_e( 'Marker Studio', 'wp-store-locator' ); ?></h1>
        </div>
        <div class="wpsl-ms-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Marker Studio panels', 'wp-store-locator' ); ?>">
            <div class="wpsl-ms-segments">
                <button type="button" role="tab" id="wpsl-ms-tab-design-btn" aria-selected="true" aria-controls="wpsl-ms-tab-design" tabindex="0">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z" /><circle cx="13.5" cy="6.5" r=".5" fill="currentColor" /><circle cx="17.5" cy="10.5" r=".5" fill="currentColor" /><circle cx="6.5" cy="12.5" r=".5" fill="currentColor" /><circle cx="8.5" cy="7.5" r=".5" fill="currentColor" /></svg>
                    <?php esc_html_e( 'Design', 'wp-store-locator' ); ?>
                </button>
                <button type="button" role="tab" id="wpsl-ms-tab-library-btn" aria-selected="false" aria-controls="wpsl-ms-tab-library" tabindex="-1">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect width="7" height="7" x="3" y="3" rx="1" /><rect width="7" height="7" x="14" y="3" rx="1" /><rect width="7" height="7" x="14" y="14" rx="1" /><rect width="7" height="7" x="3" y="14" rx="1" /></svg>
                    <?php esc_html_e( 'Library', 'wp-store-locator' ); ?>
                    <span class="wpsl-ms-count" id="wpsl-ms-library-count" aria-hidden="true"><?php echo esc_html( number_format_i18n( count( $wpsl_ms_markers ) ) ); ?></span>
                </button>
            </div>
        </div>

        <?php
        $wpsl_ms_logo_max = (int) apply_filters( 'wpsl_marker_logo_max_bytes', Custom_Markers::LOGO_MAX_BYTES, 0 );
        ?>
        <div role="tabpanel" id="wpsl-ms-tab-design" aria-labelledby="wpsl-ms-tab-design-btn">
            <div class="wpsl-ms-design-body">
                <div class="wpsl-ms-group">
                    <label class="wpsl-ms-label" for="wpsl-ms-name"><?php esc_html_e( 'Name', 'wp-store-locator' ); ?></label>
                    <div class="wpsl-ms-name-row">
                        <input type="text" id="wpsl-ms-name" class="wpsl-ms-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Untitled marker', 'wp-store-locator' ); ?>">
                        <button type="button" class="button button-secondary wpsl-ms-get-shortcode" id="wpsl-ms-get-shortcode" hidden><?php esc_html_e( 'Get Shortcode', 'wp-store-locator' ); ?></button>
                        <button type="button" class="button button-secondary wpsl-ms-new-marker" id="wpsl-ms-new-marker"><?php esc_html_e( 'New Marker', 'wp-store-locator' ); ?></button>
                    </div>
                </div>

                <div class="wpsl-ms-group wpsl-ms-group-icons">
                    <div class="wpsl-ms-group-head">
                        <span class="wpsl-ms-label" id="wpsl-ms-icon-label"><?php esc_html_e( 'Icon', 'wp-store-locator' ); ?></span>
                        <span class="wpsl-ms-count" id="wpsl-ms-icon-count" aria-hidden="true"><?php echo esc_html( count( $wpsl_ms_icons ) . ' / ' . count( $wpsl_ms_icons ) ); ?></span>
                    </div>

                    <?php
                    /*
                     * The search box and category dropdown sit above the grid. The search
                     * filters the grid by name; the category dropdown filters it by group
                     * ( "All categories" shows everything ).
                     */
                    ?>
                    <div class="wpsl-ms-icon-toolbar" id="wpsl-ms-icon-toolbar">
                        <span class="wpsl-ms-search">
                            <svg class="wpsl-ms-search-glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
                            <input type="search" id="wpsl-ms-icon-search" autocomplete="off" placeholder="<?php echo esc_attr( sprintf( /* translators: %d: number of available icons. */ __( 'Search %d icons', 'wp-store-locator' ), count( $wpsl_ms_icons ) ) ); ?>" aria-controls="wpsl-ms-icon-grid">
                            <button type="button" class="wpsl-ms-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'wp-store-locator' ); ?>" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" /></svg></button>
                        </span>
                        <select id="wpsl-ms-icon-category" aria-label="<?php esc_attr_e( 'Filter icons by category', 'wp-store-locator' ); ?>">
                            <option value=""><?php esc_html_e( 'All categories', 'wp-store-locator' ); ?></option>
                            <?php foreach ( Lucide_Icons::get_groups() as $wpsl_ms_group_label => $wpsl_ms_group_icons ) : ?>
                                <option value="<?php echo esc_attr( $wpsl_ms_group_label ); ?>"><?php echo esc_html( $wpsl_ms_group_label ); ?></option>
                            <?php endforeach; ?>
                            <option value="custom"><?php esc_html_e( 'Custom', 'wp-store-locator' ); ?></option>
                        </select>
                    </div>

                    <div class="wpsl-ms-logo-badge" id="wpsl-ms-logo-badge" hidden>
                        <img class="wpsl-ms-logo-badge-thumb" id="wpsl-ms-logo-badge-thumb" alt="">
                        <span class="wpsl-ms-logo-badge-name" id="wpsl-ms-logo-badge-name"></span>
                        <button type="button" class="wpsl-ms-icon-button wpsl-ms-logo-badge-replace" id="wpsl-ms-logo-badge-replace" title="<?php esc_attr_e( 'Replace', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Replace this logo', 'wp-store-locator' ); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /><path d="M8 16H3v5" /></svg>
                        </button>
                        <button type="button" class="wpsl-ms-icon-button wpsl-ms-logo-badge-remove" id="wpsl-ms-logo-badge-remove" title="<?php esc_attr_e( 'Remove', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Remove this logo from the marker', 'wp-store-locator' ); ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
                        </button>
                    </div>

                    <p class="wpsl-ms-logo-missing" id="wpsl-ms-logo-missing" role="status" hidden><?php esc_html_e( 'This logo is no longer in your Media Library.', 'wp-store-locator' ); ?></p>


                    <div class="wpsl-ms-icon-grid" id="wpsl-ms-icon-grid" role="group" aria-labelledby="wpsl-ms-icon-label">
                        <button type="button" class="wpsl-ms-icon-tile wpsl-ms-icon-tile-none" data-icon="" aria-pressed="false" tabindex="-1" title="<?php esc_attr_e( 'No icon', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'No icon', 'wp-store-locator' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" /><path d="m4.9 4.9 14.2 14.2" /></svg></button>
                        <button type="button" class="wpsl-ms-icon-tile wpsl-ms-icon-tile-dot" data-icon="dot" aria-pressed="true" tabindex="0" title="<?php esc_attr_e( 'Dot', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Dot', 'wp-store-locator' ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="5.5" /></svg></button>
                        <button type="button" class="wpsl-ms-icon-tile wpsl-ms-icon-tile-text" data-icon="text" aria-pressed="false" tabindex="-1" title="<?php esc_attr_e( 'Text', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Text', 'wp-store-locator' ); ?>"><span aria-hidden="true">Aa</span></button>
                        <?php
                        /*
                         * One tile per Lucide_Icons entry
                         */
                        foreach ( $wpsl_ms_icons as $wpsl_ms_icon_name => $wpsl_ms_icon_svg ) {
                            $wpsl_ms_icon_attr = esc_attr( $wpsl_ms_icon_name );

                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The name is escaped above, and the icon geometry is bundled Lucide / Phosphor data, not user input.
                            echo '<button type="button" class="wpsl-ms-icon-tile" data-icon="' . $wpsl_ms_icon_attr . '" aria-pressed="false" tabindex="-1" title="' . $wpsl_ms_icon_attr . '" aria-label="' . $wpsl_ms_icon_attr . '"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $wpsl_ms_icon_svg . '</svg></button>';
                        }

                        foreach ( wpsl_get_service( 'custom_markers' )->get_reusable_logos() as $wpsl_ms_logo_id => $wpsl_ms_logo ) {
                            $wpsl_ms_logo_name = esc_attr( $wpsl_ms_logo['filename'] );

                            echo '<span class="wpsl-ms-logo-tile-wrap">'
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The file name is escaped above, and every other value is escaped inline.
                                . '<button type="button" class="wpsl-ms-icon-tile wpsl-ms-icon-tile-logo" data-logo-id="' . esc_attr( $wpsl_ms_logo_id ) . '" data-filename="' . $wpsl_ms_logo_name . '" aria-pressed="false" tabindex="-1" title="' . $wpsl_ms_logo_name . '" aria-label="' . $wpsl_ms_logo_name . '">'
                                . '<img src="' . esc_url( $wpsl_ms_logo['thumb'] ) . '" alt="" loading="lazy" decoding="async">'
                                . '</button>'
                                . '<button type="button" class="wpsl-ms-logo-tile-remove" data-logo-id="' . esc_attr( $wpsl_ms_logo_id ) . '" title="' . esc_attr__( 'Remove from marker logos', 'wp-store-locator' ) . '" aria-label="' . esc_attr__( 'Remove from marker logos', 'wp-store-locator' ) . '">&times;</button>'
                                . '</span>';
                        }
                        ?>
                    </div>
                    <div class="wpsl-ms-icon-empty" id="wpsl-ms-icon-empty" role="status" hidden></div>

                    <input type="hidden" id="wpsl-ms-icon" value="dot">
                    <input type="hidden" id="wpsl-ms-logo-id" value="0">
                </div>

                <div class="wpsl-ms-group wpsl-ms-image-group" id="wpsl-ms-image-group">
                    <span class="wpsl-ms-label"><?php esc_html_e( 'Marker Image', 'wp-store-locator' ); ?></span>
                    <span class="wpsl-ms-image-thumb-wrap">
                        <img id="wpsl-ms-image-thumb" src="" alt="" hidden>
                        <span class="wpsl-ms-image-placeholder" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" /></svg>
                        </span>
                    </span>
                    <div class="wpsl-ms-image-controls">
                        <button type="button" class="button" id="wpsl-ms-upload-image"><?php esc_html_e( 'Choose Image', 'wp-store-locator' ); ?></button>
                        <span class="wpsl-info wpsl-ms-image-info" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Which images work as a marker', 'wp-store-locator' ); ?>">
                            <span class="wpsl-info-text wpsl-hide">
                                <?php
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: %1$s: line breaks, %2$s: the largest allowed file size, e.g. "256 KB". */
                                        __( 'Shown on the map exactly as uploaded, up to %2$s. PNG and WebP keep their transparency; a JPEG becomes a solid rectangle. SVG is not accepted.%1$sFor sharp markers on retina screens, upload at about twice the size the marker is shown at.%1$sFraming clips the image into a filled, rounded panel. The radius follows the shorter side, so a square image becomes a circle and a wide one a rounded bar.', 'wp-store-locator' ),
                                        '<br><br>',
                                        size_format( $wpsl_ms_logo_max )
                                    )
                                );
                                ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="wpsl-ms-editor-foot">
                <div class="wpsl-ms-status" id="wpsl-ms-status" role="status"></div>
                <div class="wpsl-ms-actions">
                    <button type="button" class="button button-primary wpsl-ms-save" id="wpsl-ms-save"><?php esc_html_e( 'Save Marker', 'wp-store-locator' ); ?></button>
                    <img class="wpsl-preloader wpsl-ms-save-preloader" id="wpsl-ms-save-preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" alt="" aria-hidden="true" hidden>
                    <button type="button" class="button button-secondary wpsl-ms-delete" id="wpsl-ms-delete" hidden><?php esc_html_e( 'Delete Marker', 'wp-store-locator' ); ?></button>
                    <button type="button" class="button wpsl-ms-upload-logo" id="wpsl-ms-upload-logo">
                        <?php esc_html_e( 'Upload Logo', 'wp-store-locator' ); ?>
                    </button>
                    <span class="wpsl-info wpsl-ms-upload-info" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Which images work as a logo', 'wp-store-locator' ); ?>">
                        <span class="wpsl-info-text wpsl-hide">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: %1$s: line breaks, %2$s: the largest allowed logo file size, e.g. "256 KB". */
                                    __( 'Your logo sits inside the marker shape — it does not replace it. To use an image as the whole marker, pick the My Image shape.%1$sAny Media Library image, up to %2$s. PNG and WebP keep their transparency, so the shape shows through; a JPEG covers it with a solid rectangle. SVG is not accepted.%1$sThe file is embedded in every copy of the marker, so smaller is better.', 'wp-store-locator' ),
                                    '<br><br>',
                                    size_format( $wpsl_ms_logo_max )
                                )
                            );
                            ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <?php
        /*
         * Library tab -- the saved marker collection.
         */
        ?>
        <div role="tabpanel" id="wpsl-ms-tab-library" aria-labelledby="wpsl-ms-tab-library-btn" hidden>
            <div id="wpsl-ms-library">
                <div class="wpsl-ms-library-search">
                    <span class="wpsl-ms-search">
                        <svg class="wpsl-ms-search-glyph" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></svg>
                        <input type="search" id="wpsl-ms-library-search" autocomplete="off" placeholder="<?php esc_attr_e( 'Search markers', 'wp-store-locator' ); ?>" aria-controls="wpsl-ms-cards">
                        <button type="button" class="wpsl-ms-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'wp-store-locator' ); ?>" hidden><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" /></svg></button>
                    </span>
                </div>

                <div class="wpsl-ms-cards" id="wpsl-ms-cards" role="list">
                    <?php
                    foreach ( $wpsl_ms_markers as $wpsl_ms_marker ) {
                        $wpsl_ms_render_card( $wpsl_ms_marker, $wpsl_ms_repository->get_data_uri( $wpsl_ms_marker['id'] ) );
                    }
                    ?>
                </div>

                <div class="wpsl-ms-library-empty" id="wpsl-ms-library-empty" role="status"<?php echo $wpsl_ms_markers ? ' hidden' : ''; ?>>
                    <?php if ( ! $wpsl_ms_markers ) : ?>
                        <div class="wpsl-ms-library-empty-content">
                            <h3 class="wpsl-ms-library-empty-title"><?php esc_html_e( 'No markers yet', 'wp-store-locator' ); ?></h3>
                            <p class="wpsl-ms-library-empty-text"><?php esc_html_e( 'Design your first marker and save it to the library.', 'wp-store-locator' ); ?></p>
                            <button type="button" class="button button-primary" id="wpsl-ms-empty-design"><?php esc_html_e( 'Start Designing', 'wp-store-locator' ); ?></button>
                        </div>
                    <?php endif; ?>
                    <p class="wpsl-ms-library-empty-search" hidden></p>
                </div>

                <?php
                /*
                 * Filled in by the JS, which is the only thing that knows how many
                 * cards the current search left to page through.
                 */
                ?>
                <nav class="wpsl-ms-pagination" id="wpsl-ms-pagination" aria-label="<?php esc_attr_e( 'Library pages', 'wp-store-locator' ); ?>" hidden></nav>
                <div class="wpsl-ms-card-template" id="wpsl-ms-card-template" hidden aria-hidden="true">
                    <?php $wpsl_ms_render_card( [], '' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/dialog/delete-confirmation.php';
?>

<div id="wpsl-ms-logo-in-use" class="wpsl-hide" style="height: auto;">
    <p class="wpsl-ms-logo-in-use-message"></p>
    <ul class="wpsl-ms-logo-in-use-list" id="wpsl-ms-logo-in-use-list"></ul>
    <p>
        <input type="submit" id="wpsl-ms-logo-in-use-close" class="button-secondary" value="<?php esc_attr_e( 'Close', 'wp-store-locator' ); ?>">
        <input type="submit" id="wpsl-ms-logo-in-use-library" class="button-primary" value="<?php esc_attr_e( 'Open Library', 'wp-store-locator' ); ?>">
    </p>
</div>

<?php
/*
 * The shortcode-id dialog, opened from the "Get Shortcode ID" link on the
 * Design tab. A dialog rather than a tooltip so the value can sit in a real
 * field with a Copy button, with the explanation as plain text around it.
 */
?>
<div id="wpsl-ms-shortcode-dialog" class="wpsl-hide" title="<?php esc_attr_e( 'Shortcode ID', 'wp-store-locator' ); ?>">
    <button type="button" class="wpsl-close-cross wpsl-dialog-close" id="wpsl-ms-shortcode-cross" aria-label="<?php esc_attr_e( 'Close', 'wp-store-locator' ); ?>">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>
    </button>
    <p>
        <?php
        echo wp_kses_post(
            /* translators: kept as one sentence so translations can reorder it freely. */
            __( 'Paste this ID into a marker attribute to put this marker on a map. The marker\'s name does not work — only this ID, and a name that is not recognized silently falls back to the default marker.', 'wp-store-locator' )
        );
        ?>
    </p>
    <div class="wpsl-ms-shortcode-row">
        <input type="text" id="wpsl-ms-shortcode-id" class="wpsl-ms-text wpsl-ms-shortcode-value" readonly value="" aria-label="<?php esc_attr_e( 'Shortcode ID', 'wp-store-locator' ); ?>">
        <button type="button" class="button wpsl-ms-copy-shortcode" id="wpsl-ms-copy-shortcode"><?php esc_html_e( 'Copy', 'wp-store-locator' ); ?></button>
    </div>
    <p>
        <?php
        echo wp_kses_post(
            sprintf(
                /* translators: %1$s / %2$s: opening and closing link tag to the shortcode documentation. */
                __( 'The <code>store_marker</code> and <code>active_marker</code> %1$sshortcode attributes%2$s accept it on both <code>[wpsl]</code> and <code>[wpsl_map]</code>; <code>start_marker</code> on <code>[wpsl]</code> only.', 'wp-store-locator' ),
                '<a href="https://wpstorelocator.co/document/shortcodes/" target="_blank" rel="noopener noreferrer">',
                '</a>'
            )
        );
        ?>
    </p>
    <p class="wpsl-ms-shortcode-example">
        <?php esc_html_e( 'For example:', 'wp-store-locator' ); ?>
        <code id="wpsl-ms-shortcode-example"></code>
    </p>
    <p>
        <?php
        echo wp_kses_post(
            sprintf(
                /* translators: %s: the two block names, in bold. */
                __( 'Tip: in the block editor, the %s blocks offer a dropdown to select your custom markers by name — no ID needed.', 'wp-store-locator' ),
                '<strong>' . esc_html__( 'Store Locator', 'wp-store-locator' ) . '</strong> / <strong>' . esc_html__( 'Map', 'wp-store-locator' ) . '</strong>'
            )
        );
        ?>
    </p>
    <p class="wpsl-ms-dialog-actions">
        <input type="submit" id="wpsl-ms-shortcode-close" class="button-secondary" value="<?php esc_attr_e( 'Close', 'wp-store-locator' ); ?>">
    </p>
</div>