<?php
/**
 * Map Shapes editor page template.
 *
 * Two standard white postbox cards side by side: a controls column, and the map
 * with the rest of the UI floating over it.
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$active_map_service = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'active_map_service' );

/*
 * Whether anything is stored yet, which is what decides the first paint of the
 * Save / Undo row below.
 */
$has_shapes = wpsl_get_service( 'shapes_repository' )->has_shapes();
?>

<div id="wpsl-content-wrap" class="wpsl-map-shapes-editor wpsl-settings-grid">
    <section id="wpsl-shapes-controls" class="postbox">
        <h3><span><?php esc_html_e( 'Map Shapes', 'wp-store-locator' ); ?></span></h3>
        <div class="inside">
                <p id="wpsl-shapes-instructions" class="wpsl-callout" aria-live="polite" style="display:none;"><?php esc_html_e( 'Select a drawing tool to begin.', 'wp-store-locator' ); ?></p>
                <div class="wpsl-shape-picker" style="display:none;">
                    <button type="button" id="wpsl-shape-select" class="wpsl-shape-select" aria-haspopup="listbox" aria-expanded="false" aria-label="<?php esc_attr_e( 'Shape', 'wp-store-locator' ); ?>">
                        <span id="wpsl-shape-swatch" aria-hidden="true"></span>
                        <span id="wpsl-shape-select-label"></span>
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" /></svg>
                    </button>
                    <ul id="wpsl-shape-list" role="listbox" hidden></ul>
                </div>

                <div id="wpsl-shapes-style" style="display:none;">
                    <div class="wpsl-shape-field">
                        <label for="wpsl-shape-name"><?php esc_html_e( 'Name', 'wp-store-locator' ); ?></label>
                        <input type="text" id="wpsl-shape-name" class="wpsl-shape-name">
                        <span class="wpsl-shape-error" id="wpsl-shape-name-error"></span>
                    </div>
                    <p id="wpsl-shape-active-row">
                        <label for="wpsl-shape-active-toggle"><?php esc_html_e( 'Active', 'wp-store-locator' ); ?></label>
                        <input type="checkbox" id="wpsl-shape-active-toggle" checked>
                    </p>
                    <p class="wpsl-shape-fill-control">
                        <label for="wpsl-shape-fill-toggle"><?php esc_html_e( 'Fill', 'wp-store-locator' ); ?></label>
                        <input type="checkbox" id="wpsl-shape-fill-toggle" checked>
                    </p>
                    <p class="wpsl-shape-fill-control">
                        <label for="wpsl-shape-fill-color"><?php esc_html_e( 'Fill color', 'wp-store-locator' ); ?></label>
                        <input type="text" id="wpsl-shape-fill-color" class="wpsl-color-field" value="#cc3333" data-picker-position="beside">
                    </p>
                    <p class="wpsl-shape-fill-control">
                        <label for="wpsl-shape-fill-opacity"><?php esc_html_e( 'Fill opacity', 'wp-store-locator' ); ?></label>
                        <input type="range" id="wpsl-shape-fill-opacity" min="0" max="1" step="0.05" value="0.4">
                        <output id="wpsl-shape-fill-opacity-value" for="wpsl-shape-fill-opacity" aria-hidden="true">0.4</output>
                    </p>
                    <p>
                        <label for="wpsl-shape-stroke-color"><?php esc_html_e( 'Stroke color', 'wp-store-locator' ); ?></label>
                        <input type="text" id="wpsl-shape-stroke-color" class="wpsl-color-field" value="#cc3333" data-picker-position="beside">
                    </p>
                    <p>
                        <label for="wpsl-shape-stroke-width"><?php esc_html_e( 'Stroke width', 'wp-store-locator' ); ?></label>
                        <input type="range" id="wpsl-shape-stroke-width" min="0" max="6" step="1" value="2">
                        <output id="wpsl-shape-stroke-width-value" for="wpsl-shape-stroke-width" aria-hidden="true">2px</output>
                    </p>
                    <div class="wpsl-shape-geo wpsl-shape-geo-circle wpsl-hide">
                        <span class="wpsl-shape-field">
                            <label for="wpsl-shape-center"><?php esc_html_e( 'Center', 'wp-store-locator' ); ?></label>
                            <input type="text" id="wpsl-shape-center" spellcheck="false">
                            <span class="wpsl-shape-error" id="wpsl-shape-center-error"></span>
                        </span>
                        <span class="wpsl-shape-field">
                            <label for="wpsl-shape-radius"><?php esc_html_e( 'Radius', 'wp-store-locator' ); ?></label>
                            <input type="text" id="wpsl-shape-radius" spellcheck="false">
                            <span class="wpsl-shape-error" id="wpsl-shape-radius-error"></span>
                        </span>
                    </div>

                    <div class="wpsl-shape-geo wpsl-shape-geo-rectangle wpsl-hide">
                        <span class="wpsl-shape-field">
                            <label for="wpsl-shape-ne"><?php esc_html_e( 'North-east', 'wp-store-locator' ); ?></label>
                            <input type="text" id="wpsl-shape-ne" spellcheck="false">
                            <span class="wpsl-shape-error" id="wpsl-shape-ne-error"></span>
                        </span>
                        <span class="wpsl-shape-field">
                            <label for="wpsl-shape-sw"><?php esc_html_e( 'South-west', 'wp-store-locator' ); ?></label>
                            <input type="text" id="wpsl-shape-sw" spellcheck="false">
                            <span class="wpsl-shape-error" id="wpsl-shape-sw-error"></span>
                        </span>
                    </div>

                    <div class="wpsl-shape-field wpsl-shape-geo-path wpsl-hide">
                        <label for="wpsl-shape-coords"><?php esc_html_e( 'Coordinates', 'wp-store-locator' ); ?></label>
                        <textarea id="wpsl-shape-coords" rows="5" spellcheck="false"></textarea>
                        <span class="wpsl-shape-error" id="wpsl-shape-coords-error"></span>
                    </div>

                    <div class="wpsl-shape-field">
                        <label for="wpsl-shape-message"><?php esc_html_e( 'Message', 'wp-store-locator' ); ?></label>
                        <textarea id="wpsl-shape-message" rows="4"></textarea>
                        <span class="wpsl-shape-hint">
                            <?php esc_html_e( 'Shown when a visitor clicks the shape. [b] [i] [u] [br] and [url] are supported.', 'wp-store-locator' ); ?>
                        </span>
                    </div>
                    <?php
                    do_action( 'wpsl_map_shapes_style_controls' );
                    ?>
                </div>

                <p id="wpsl-shapes-actions"<?php echo $has_shapes ? '' : ' style="display:none;"'; ?>>
                    <span id="wpsl-shapes-status" role="status"></span>
                    <button type="button" class="button button-primary" id="wpsl-shapes-save"><?php esc_html_e( 'Save Shapes', 'wp-store-locator' ); ?></button>
                    <img class="wpsl-preloader" id="wpsl-shapes-save-preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" alt="" aria-hidden="true" hidden>
                    <span id="wpsl-shapes-shape-actions" style="display:none;">
                        <button type="button" class="button" id="wpsl-shape-duplicate"><?php esc_html_e( 'Duplicate', 'wp-store-locator' ); ?></button>
                        <button type="button" class="button wpsl-shape-delete" id="wpsl-shape-delete"><?php esc_html_e( 'Delete Shape', 'wp-store-locator' ); ?></button>
                    </span>
                </p>
        </div>
    </section>

    <section id="wpsl-shapes-preview" class="postbox">
        <h3><span><?php esc_html_e( 'Preview', 'wp-store-locator' ); ?></span></h3>
        <div class="inside">
            <div id="wpsl-shapes-map-wrap" data-map-service="<?php echo esc_attr( $active_map_service ); ?>">
                <div id="wpsl-shapes-map" data-map-service="<?php echo esc_attr( $active_map_service ); ?>"></div>
                <div id="wpsl-shapes-search" style="display:none;">
                    <div class="wpsl-search-wrap">
                        <input type="text" id="wpsl-shapes-search-input" placeholder="<?php esc_attr_e( 'Search for a place', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Search for a place to move the map to', 'wp-store-locator' ); ?>" autocomplete="off" spellcheck="false">

                        <div class="wpsl-search-action-wrapper" id="wpsl-shapes-clear-wrapper" style="display:none;">
                            <button type="button" id="wpsl-shapes-clear-search" aria-label="<?php esc_attr_e( 'Clear search input', 'wp-store-locator' ); ?>">
                                <?php echo wpsl_get_svg_icon( 'reset' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG from a fixed set. ?>
                            </button>
                            <img class="wpsl-preloader" id="wpsl-shapes-search-preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" alt="" aria-hidden="true" hidden>
                        </div>
                        <div class="wpsl-search-action-wrapper" id="wpsl-shapes-submit-wrapper">
                            <button type="button" id="wpsl-shapes-search-btn" aria-label="<?php esc_attr_e( 'Search', 'wp-store-locator' ); ?>">
                                <?php echo wpsl_get_svg_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static inline SVG from a fixed set. ?>
                            </button>
                        </div>
                    </div>
                    <span id="wpsl-shapes-search-message" class="screen-reader-text" role="status" hidden></span>
                </div>

                <div id="wpsl-shapes-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Drawing tools', 'wp-store-locator' ); ?>" style="display:none;">
                    <button type="button" class="button" data-shape-type="polygon" aria-pressed="false" title="<?php esc_attr_e( 'Polygon', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 21 9.6 17.6 20.2 6.4 20.2 3 9.6Z" /></svg>
                        <span class="screen-reader-text"><?php esc_html_e( 'Polygon', 'wp-store-locator' ); ?></span>
                    </button>
                    <button type="button" class="button" data-shape-type="circle" aria-pressed="false" title="<?php esc_attr_e( 'Circle', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" /></svg>
                        <span class="screen-reader-text"><?php esc_html_e( 'Circle', 'wp-store-locator' ); ?></span>
                    </button>
                    <button type="button" class="button" data-shape-type="rectangle" aria-pressed="false" title="<?php esc_attr_e( 'Rectangle', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="6" width="18" height="12" rx="1" /></svg>
                        <span class="screen-reader-text"><?php esc_html_e( 'Rectangle', 'wp-store-locator' ); ?></span>
                    </button>
                    <button type="button" class="button" data-shape-type="polyline" aria-pressed="false" title="<?php esc_attr_e( 'Polyline', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17 9 9 14 14 21 5" /></svg>
                        <span class="screen-reader-text"><?php esc_html_e( 'Polyline', 'wp-store-locator' ); ?></span>
                    </button>
   
                    <button type="button" class="button" id="wpsl-shapes-deselect" style="display:none;" title="<?php esc_attr_e( 'Clear selection', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 6 6 18" /><path d="m6 6 12 12" /></svg>
                        <span class="screen-reader-text"><?php esc_html_e( 'Clear selection', 'wp-store-locator' ); ?></span>
                    </button>
                </div>
                <button type="button" id="wpsl-shapes-undo" style="display:none;" title="<?php esc_attr_e( 'Undo', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Undo the last change', 'wp-store-locator' ); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 14 4 9l5-5" /><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11" /></svg>
                </button>

                <div id="wpsl-shapes-hover-tools" role="toolbar" aria-label="<?php esc_attr_e( 'Shape tools', 'wp-store-locator' ); ?>" hidden>
                    <button type="button" class="wpsl-shape-tool" data-shape-action="duplicate" title="<?php esc_attr_e( 'Duplicate', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Duplicate this shape', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
                    </button>
                    <button type="button" class="wpsl-shape-tool wpsl-shape-tool-danger" data-shape-action="delete" title="<?php esc_attr_e( 'Delete', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Delete this shape', 'wp-store-locator' ); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></svg>
                    </button>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// The same confirmation the Fields Manager and the Tools screens use
require WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/dialog/delete-confirmation.php';
