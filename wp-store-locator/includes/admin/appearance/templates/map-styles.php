<?php
/**
 * Map Styles Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$ui = wpsl_get_service( 'admin_ui' );
$active_map_service = $wpsl_settings->get( 'api', 'active_map_service' );
$has_stadia_key = $wpsl_settings->get( 'api', 'stadia_key' ) && get_option( 'wpsl_valid_stadia_key' ) == '1';
$has_mapbox_key = $wpsl_settings->get( 'api', 'mapbox_key' ) && get_option( 'wpsl_valid_mapbox_key' ) == '1';

// Check if user upgraded from v1/v2
$is_upgraded = get_option( 'wpsl_v3_migration_complete' ) !== false;

$is_upgraded = true;

// Check if user has custom JSON code
$json_style = wpsl_get_service( 'map_settings' )->get_map_style( 'gmaps' );
$has_custom_json = ! empty( $json_style ) && $json_style !== '[]';

$has_custom_json = true;

// Determine which fields to show
$show_dropdown = $is_upgraded && $has_custom_json;
$show_json_field = $is_upgraded && $has_custom_json;
$show_cloud_id_field = true;

// The selected style source ( cloud_based / json ). Used to render the correct
// section visibility server-side so the inactive section ( e.g. the JSON
// textarea ) isn't briefly visible before toggleStyleSections() runs in JS.
$selected_style = isset( $section_settings['map_style']['gmaps']['selected'] ) ? $section_settings['map_style']['gmaps']['selected'] : 'cloud_based';
?>

<?php if ( $active_map_service === 'gmaps' ) { ?>
    <?php if ( $show_dropdown ) { ?>
    <div id="wpsl-json-style-notice" class="wpsl-red-callout">
        <span style="font-size: 13px; line-height: 1.6;">
            <?php esc_html_e( 'Client-side JSON styles only work with the deprecated marker class. This class has been replaced by the Advanced marker class that unfortunately no longer supports client side styles.', 'wp-store-locator' ); ?><br><br>
            <?php esc_html_e( 'If a client-side JSON style is provided the deprecated marker class will be used, but this may break in the future. Because of this it\'s better to migrate to the Cloud-based maps styles which automatically uses the new Advanced marker class.', 'wp-store-locator' ); ?><br><br>
            <a href="https://developers.google.com/maps/documentation/javascript/advanced-markers/migration" target="_blank"><?php esc_html_e( 'Read more', 'wp-store-locator' ); ?></a>
        </span>
    </div>

    <div class="wpsl-style-input-group">
        <p class="wpsl-style-input wpsl-map-style-source">
            <label for="wpsl-gmaps-styles"><?php esc_html_e( 'Style source', 'wp-store-locator' ); ?></label>
            <?php echo $ui->create_dropdown( 'gmap_styles' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </p>
    </div>
    <?php } ?>

    <div id="wpsl-gmaps-style-cloud_based" class=" wpsl-style-input-group wpsl-map-style-section"<?php if ( $selected_style !== 'cloud_based' ) { echo ' style="display: none !important;"'; } ?>>
        <p class="wpsl-style-input">
            <label for="wpsl-cloud-based-id"><?php esc_html_e( 'Map ID', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to Google documentation, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'How to create a Map ID and use map styles is explained %1$shere%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://developers.google.com/maps/documentation/javascript/cloud-customization/map-styles">', '</a>' ) ); ?></span></span></label>
            <input type="text" value="<?php echo esc_attr( $section_settings['map_style']['gmaps']['cloud_based'] ); ?>" name="wpsl_appearance[map][style][gmaps][cloud_based]" id="wpsl-cloud-based-id">
        </p>
    </div>

    <?php if ( $show_json_field ) { ?>
    <div id="wpsl-gmaps-style-json-group" class="wpsl-style-input-group wpsl-map-style-section"<?php if ( $selected_style !== 'json' ) { echo ' style="display: none !important;"'; } ?>>
        <div id="wpsl-gmaps-style-json" class="wpsl-style-input">
            <label for="wpsl-map-style-gmaps"><?php esc_html_e( 'JSON Style', 'wp-store-locator' ); ?></label>
            <textarea id="wpsl-map-style-gmaps" name="wpsl_appearance[map][style][gmaps][json]"><?php echo esc_textarea( wpsl_get_service( 'map_settings' )->get_map_style( 'gmaps' ) ); ?></textarea>
        </div>
    </div>
    <?php } ?>

    <div class="wpsl-style-input-group wpsl-buttons-row">
        <label class="wpsl-button-spacer"></label>
        <span class="wpsl-style-actions">
            <input type="submit" value="<?php esc_attr_e( 'Preview Style', 'wp-store-locator' ); ?>" class="button-secondary wpsl-style-action" data-action="apply">
            <input type="submit" value="<?php esc_attr_e( 'Remove Style', 'wp-store-locator' ); ?>" class="button-secondary wpsl-style-action" data-action="remove">
        </span>
    </div>
<?php } ?>

<div id="wpsl-mapbox-styles">
    <?php
    if ( $active_map_service === 'osm' ) { ?>
        <div class="wpsl-api-osm">
            <?php
            $map_style = $wpsl_settings->get( 'appearance', 'map_style' );
            $tile_source = isset( $map_style['osm']['tile_source'] ) ? $map_style['osm']['tile_source'] : 'default';

            // The custom style URL moved from the OpenFreeMap section to its
            // own tile source; a legacy save still selecting it lands there.
            if ( 'openfreemap' === $tile_source && isset( $map_style['openfreemap']['selected'] ) && 'custom' === $map_style['openfreemap']['selected'] ) {
                $tile_source = 'maplibre';
            }
            ?>
            <p>
                <label class="wpsl-tab-label" for="wpsl-osm-tile-source">
                    <?php esc_html_e( 'Tile source', 'wp-store-locator' ); ?>
                </label>
                <select id="wpsl-osm-tile-source" name="wpsl_appearance[map][style][osm][tile_source]">
                    <option value="default" <?php selected( $tile_source, 'default' ); ?>><?php esc_html_e( 'Default OpenStreetMap', 'wp-store-locator' ); ?></option>
                    <option value="openfreemap" <?php selected( $tile_source, 'openfreemap' ); ?>><?php esc_html_e( 'OpenFreeMap styles', 'wp-store-locator' ); ?></option>
                    <option value="maplibre" <?php selected( $tile_source, 'maplibre' ); ?>><?php esc_html_e( 'MapLibre style URL', 'wp-store-locator' ); ?></option>
                    <?php if ( $has_mapbox_key ) { ?>
                    <option value="mapbox" <?php selected( $tile_source, 'mapbox' ); ?>><?php esc_html_e( 'Mapbox styles', 'wp-store-locator' ); ?></option>
                    <?php } ?>
                    <?php if ( $has_stadia_key ) { ?>
                    <option value="stadia" <?php selected( $tile_source, 'stadia' ); ?>><?php esc_html_e( 'Stadia Maps styles', 'wp-store-locator' ); ?></option>
                    <?php } ?>
                </select>
            </p>
            <?php if ( $has_mapbox_key ) { ?>
            <div class="wpsl-osm-tile-source-section" id="wpsl-osm-tile-source-mapbox" <?php if ( $tile_source !== 'mapbox' ) { echo 'style="display:none;"'; } ?>>
                <?php echo wpsl_get_service( 'map_settings' )->mapbox_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
            <?php } ?>
            <?php if ( $has_stadia_key ) { ?>
            <div class="wpsl-osm-tile-source-section" id="wpsl-osm-tile-source-stadia" <?php if ( $tile_source !== 'stadia' ) { echo 'style="display:none;"'; } ?>>
                <?php echo wpsl_get_service( 'map_settings' )->stadia_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
            <?php } ?>
            <div class="wpsl-osm-tile-source-section" id="wpsl-osm-tile-source-openfreemap" <?php if ( $tile_source !== 'openfreemap' ) { echo 'style="display:none;"'; } ?>>
                <?php echo wpsl_get_service( 'map_settings' )->openfreemap_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
            <div class="wpsl-osm-tile-source-section" id="wpsl-osm-tile-source-maplibre" <?php if ( $tile_source !== 'maplibre' ) { echo 'style="display:none;"'; } ?>>
                <?php echo wpsl_get_service( 'map_settings' )->maplibre_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
        </div>
    <?php } elseif ( $active_map_service === 'mapbox' ) { ?>
        <div class="wpsl-api-mapbox">
            <?php echo wpsl_get_service( 'map_settings' )->mapbox_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </div>
    <?php } elseif ( $active_map_service === 'stadia' ) { ?>
        <div class="wpsl-api-stadia">
            <?php
            $map_style    = $wpsl_settings->get( 'appearance', 'map_style' );
            $style_source = isset( $map_style['stadia']['style_source'] ) ? $map_style['stadia']['style_source'] : 'stadia';
            ?>
            <p>
                <label class="wpsl-tab-label" for="wpsl-stadia-style-source">
                    <?php esc_html_e( 'Style source', 'wp-store-locator' ); ?>
                </label>
                <select id="wpsl-stadia-style-source" name="wpsl_appearance[map][style][stadia][style_source]">
                    <option value="stadia" <?php selected( $style_source, 'stadia' ); ?>><?php esc_html_e( 'Stadia styles', 'wp-store-locator' ); ?></option>
                    <option value="maplibre" <?php selected( $style_source, 'maplibre' ); ?>><?php esc_html_e( 'MapLibre style URL', 'wp-store-locator' ); ?></option>
                </select>
            </p>
            <div class="wpsl-stadia-style-source-section" id="wpsl-stadia-style-source-stadia" <?php if ( $style_source !== 'stadia' ) { echo 'style="display:none;"'; } ?>>
                <?php echo wpsl_get_service( 'map_settings' )->stadia_styles( 'stadia' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
            <div class="wpsl-stadia-style-source-section" id="wpsl-stadia-style-source-maplibre" <?php if ( $style_source !== 'maplibre' ) { echo 'style="display:none;"'; } ?>>
                <?php echo wpsl_get_service( 'map_settings' )->maplibre_styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </div>
        </div>
    <?php } ?>
</div>