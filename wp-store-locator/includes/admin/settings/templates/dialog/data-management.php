<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Make sure the location / categories / custom marker / map shape data exists
 * before providing the user the option to delete it
 */
$locations_data = wpsl_locations_exist();
$categories_data = wpsl_categories_exist();
$custom_markers_data = wpsl_custom_markers_exist();
$map_shapes_data = wpsl_map_shapes_exist();
?>

<div id="wpsl-data-management" class="wpsl-hide" style="height: auto;" title="<?php echo esc_attr__( 'Data Management', 'wp-store-locator' ); ?>">
    <p>
        <label for="wpsl-reset-defaults"><?php esc_html_e( 'Reset settings back to defaults', 'wp-store-locator' ); ?></label>
        <input type="checkbox" name="settings" id="wpsl-reset-defaults" class="wpsl-data-action" value="" />
    </p>
    <?php if ( $locations_data['exist'] ) { ?>
    <p>
        <label for="wpsl-delete-locations"><?php esc_html_e( 'Delete all locations', 'wp-store-locator' ); ?><span class="wpsl-count" data-type="locations">(<?php echo esc_html( $locations_data['count'] ); ?>)</span></label>
        <input type="checkbox" name="locations" id="wpsl-delete-locations" class="wpsl-data-action" value="" />
    </p>
    <?php } ?>
    <?php if ( $categories_data['exist'] ) { ?>
    <p>
        <label for="wpsl-delete-categories"><?php esc_html_e( 'Delete all categories', 'wp-store-locator' ); ?><span class="wpsl-count" data-type="categories">(<?php echo esc_html( $categories_data['count'] ); ?>)</span></label>
        <input type="checkbox" name="categories" id="wpsl-delete-categories" class="wpsl-data-action" value="" />
    </p>
    <?php } ?>
    <?php if ( $custom_markers_data['exist'] ) { ?>
    <p>
        <label for="wpsl-delete-custom-markers"><?php esc_html_e( 'Delete all custom markers', 'wp-store-locator' ); ?><span class="wpsl-count" data-type="custom_markers">(<?php echo esc_html( $custom_markers_data['count'] ); ?>)</span></label>
        <input type="checkbox" name="custom_markers" id="wpsl-delete-custom-markers" class="wpsl-data-action" value="" />
    </p>
    <?php } ?>
    <?php if ( $map_shapes_data['exist'] ) { ?>
    <p>
        <label for="wpsl-delete-map-shapes"><?php esc_html_e( 'Delete all map shapes', 'wp-store-locator' ); ?><span class="wpsl-count" data-type="map_shapes">(<?php echo esc_html( $map_shapes_data['count'] ); ?>)</span></label>
        <input type="checkbox" name="map_shapes" id="wpsl-delete-map-shapes" class="wpsl-data-action" value="" />
    </p>
    <?php } ?>
    <p id="wpsl-confirmation-section" class="wpsl-hide">
        <label for="wpsl-confirmation"><?php esc_html_e( 'This is permanent and cannot be undone!', 'wp-store-locator' ); ?></label>
        <input type="checkbox" name="confirmation" id="wpsl-confirmation" value="" />
    </p>
    <input type="hidden" id="wpsl-data-management-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-data-management' ) ); ?>"/>
</div>