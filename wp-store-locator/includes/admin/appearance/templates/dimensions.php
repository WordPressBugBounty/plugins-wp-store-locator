<?php
/**
 * Dimensions Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$active_template = wpsl_get_active_template();
$template_id = $wpsl_settings->get( 'appearance', 'template_id' );
$dimensions = $wpsl_settings->get( 'appearance', 'dimensions' );
$has_panel = isset( $active_template['has_panel'] ) && $active_template['has_panel'];

// Get template-specific dimensions
$horizontal_dims = isset( $dimensions['horizontal'] ) ? $dimensions['horizontal'] : [];
$vertical_dims = isset( $dimensions['vertical'] ) ? $dimensions['vertical'] : [];
$default_dims = isset( $dimensions['default'] ) ? $dimensions['default'] : [];
?>

<p class="wpsl-horizontal-dimensions" <?php if ( $template_id !== 'horizontal' ) { echo 'style="display:none;"'; } ?>>
    <label for="wpsl-map-height-mode-horizontal"><?php esc_html_e( 'Map height', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-map-height-auto-info-horizontal" <?php 
        $map_height_mode_h = isset( $horizontal_dims['map_height_mode'] ) ? $horizontal_dims['map_height_mode'] : 'custom';
        if ( $map_height_mode_h !== 'default' ) { 
            echo 'style="display:none;"'; 
        } 
    ?>><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Default mode sets the map height to 350px.', 'wp-store-locator' ); ?></span></span></label>
    <select id="wpsl-map-height-mode-horizontal" name="wpsl_appearance[dimensions][horizontal][map_height_mode]" class="wpsl-has-conditional-option">
        <option value="default" <?php selected( isset( $horizontal_dims['map_height_mode'] ) ? $horizontal_dims['map_height_mode'] : 'custom', 'default' ); ?>><?php esc_html_e( 'Default', 'wp-store-locator' ); ?></option>
        <option value="custom" <?php selected( isset( $horizontal_dims['map_height_mode'] ) ? $horizontal_dims['map_height_mode'] : 'custom', 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-store-locator' ); ?></option>
    </select>
</p>
<div class="wpsl-conditional-option wpsl-horizontal-dimensions" <?php 
    $map_height_mode_h = isset( $horizontal_dims['map_height_mode'] ) ? $horizontal_dims['map_height_mode'] : 'custom';
    if ( $template_id !== 'horizontal' || $map_height_mode_h === 'default' ) { 
        echo 'style="display:none;"'; 
    } 
?>>
    <p>
        <label for="wpsl-map-height" class="wpsl-hidden"><?php esc_html_e( 'Map height', 'wp-store-locator' ); ?></label>
        <span class="wpsl-input-with-unit">
            <input type="number" value="<?php echo esc_attr( isset( $horizontal_dims['map_height'] ) ? $horizontal_dims['map_height'] : 350 ); ?>" id="wpsl-map-height" name="wpsl_appearance[dimensions][horizontal][map_height]" min="250" max="800">
            <span class="wpsl-unit-suffix">px</span>
        </span>
    </p>
</div>
<p class="wpsl-horizontal-dimensions" <?php if ( $template_id !== 'horizontal' ) { echo 'style="display:none;"'; } ?>>
    <label for="wpsl-results-height-mode-horizontal"><?php esc_html_e( 'Results height', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-results-height-auto-info-horizontal" <?php 
        $results_height_mode_h = isset( $horizontal_dims['results_height_mode'] ) ? $horizontal_dims['results_height_mode'] : 'custom';
        if ( $results_height_mode_h !== 'default' ) { 
            echo 'style="display:none;"'; 
        } 
    ?>><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Default mode sets the results height to 350px.', 'wp-store-locator' ); ?></span></span></label>
    <select id="wpsl-results-height-mode-horizontal" name="wpsl_appearance[dimensions][horizontal][results_height_mode]" class="wpsl-has-conditional-option">
        <option value="default" <?php selected( isset( $horizontal_dims['results_height_mode'] ) ? $horizontal_dims['results_height_mode'] : 'custom', 'default' ); ?>><?php esc_html_e( 'Default', 'wp-store-locator' ); ?></option>
        <option value="custom" <?php selected( isset( $horizontal_dims['results_height_mode'] ) ? $horizontal_dims['results_height_mode'] : 'custom', 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-store-locator' ); ?></option>
    </select>
</p>
<div class="wpsl-conditional-option wpsl-horizontal-dimensions" <?php 
    $results_height_mode_h = isset( $horizontal_dims['results_height_mode'] ) ? $horizontal_dims['results_height_mode'] : 'custom';
    if ( $template_id !== 'horizontal' || $results_height_mode_h === 'default' ) { 
        echo 'style="display:none;"'; 
    } 
?>>
    <p>
        <label for="wpsl-results-height" class="wpsl-hidden"><?php esc_html_e( 'Results height', 'wp-store-locator' ); ?></label>
        <span class="wpsl-input-with-unit">
            <input type="number" value="<?php echo esc_attr( isset( $horizontal_dims['results_height'] ) ? $horizontal_dims['results_height'] : 350 ); ?>" id="wpsl-results-height" name="wpsl_appearance[dimensions][horizontal][results_height]" min="250" max="800">
            <span class="wpsl-unit-suffix">px</span>
        </span>
    </p>
</div>
<p class="wpsl-vertical-dimensions" <?php if ( $template_id !== 'vertical' ) { echo 'style="display:none;"'; } ?>>
    <label for="wpsl-sl-height-mode"><?php esc_html_e( 'Store locator height', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-sl-height-auto-info" <?php 
        $sl_height_mode = isset( $vertical_dims['sl_height_mode'] ) ? $vertical_dims['sl_height_mode'] : 'custom';
        if ( $sl_height_mode !== 'default' ) { 
            echo 'style="display:none;"'; 
        } 
    ?>><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Default mode sets the container height to 450px.', 'wp-store-locator' ); ?></span></span></label>
    <select id="wpsl-sl-height-mode" name="wpsl_appearance[dimensions][vertical][sl_height_mode]" class="wpsl-has-conditional-option">
        <option value="default" <?php selected( isset( $vertical_dims['sl_height_mode'] ) ? $vertical_dims['sl_height_mode'] : 'custom', 'default' ); ?>><?php esc_html_e( 'Default', 'wp-store-locator' ); ?></option>
        <option value="custom" <?php selected( isset( $vertical_dims['sl_height_mode'] ) ? $vertical_dims['sl_height_mode'] : 'custom', 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-store-locator' ); ?></option>
    </select>
</p>
<div class="wpsl-conditional-option wpsl-vertical-dimensions" <?php 
    $sl_height_mode = isset( $vertical_dims['sl_height_mode'] ) ? $vertical_dims['sl_height_mode'] : 'custom';
    if ( $template_id !== 'vertical' || $sl_height_mode === 'default' ) { 
        echo 'style="display:none;"'; 
    } 
?>>
    <p>
        <label for="wpsl-sl-height" class="wpsl-hidden"><?php esc_html_e( 'Store locator height', 'wp-store-locator' ); ?></label>
        <span class="wpsl-input-with-unit">
            <input type="number" value="<?php echo esc_attr( isset( $vertical_dims['sl_height'] ) ? $vertical_dims['sl_height'] : 450 ); ?>" id="wpsl-sl-height" name="wpsl_appearance[dimensions][vertical][sl_height]" min="250" max="800">
            <span class="wpsl-unit-suffix">px</span>
        </span>
    </p>
</div>
<p class="wpsl-default-dimensions" <?php if ( $template_id !== 'default' ) { echo 'style="display:none;"'; } ?>>
    <label for="wpsl-map-height-mode"><?php esc_html_e( 'Map height', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-map-height-auto-info" <?php 
        $map_height_mode = isset( $default_dims['map_height_mode'] ) ? $default_dims['map_height_mode'] : 'custom';
        if ( $map_height_mode !== 'default' ) { 
            echo 'style="display:none;"'; 
        } 
    ?>><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Default mode sets the map height to 350px.', 'wp-store-locator' ); ?></span></span></label>
    <select id="wpsl-map-height-mode" name="wpsl_appearance[dimensions][default][map_height_mode]" class="wpsl-has-conditional-option">
        <option value="default" <?php selected( isset( $default_dims['map_height_mode'] ) ? $default_dims['map_height_mode'] : 'custom', 'default' ); ?>><?php esc_html_e( 'Default', 'wp-store-locator' ); ?></option>
        <option value="custom" <?php selected( isset( $default_dims['map_height_mode'] ) ? $default_dims['map_height_mode'] : 'custom', 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-store-locator' ); ?></option>
    </select>
</p>
<div class="wpsl-conditional-option wpsl-default-dimensions" <?php 
    $map_height_mode = isset( $default_dims['map_height_mode'] ) ? $default_dims['map_height_mode'] : 'custom';
    if ( $template_id !== 'default' || $map_height_mode === 'default' ) { 
        echo 'style="display:none;"'; 
    } 
?>>
    <p>
        <label for="wpsl-design-height" class="wpsl-hidden"><?php esc_html_e( 'Map height', 'wp-store-locator' ); ?></label>
        <span class="wpsl-input-with-unit">
            <input type="number" value="<?php echo esc_attr( isset( $default_dims['map_height'] ) ? $default_dims['map_height'] : 350 ); ?>" id="wpsl-design-height" name="wpsl_appearance[dimensions][default][map_height]" min="250" max="800">
            <span class="wpsl-unit-suffix">px</span>
        </span>
    </p>
</div>

<p class="wpsl-non-panel-dimensions" <?php if ( $has_panel ) { echo 'style="display:none;"'; } ?>>
    <label for="wpsl-search-width-mode"><?php esc_html_e( 'Search field width', 'wp-store-locator' ); ?></label>
    <select id="wpsl-search-width-mode" name="wpsl_appearance[dimensions][search_width_mode]" class="wpsl-has-conditional-option">
        <option value="default" <?php selected( isset( $dimensions['search_width_mode'] ) ? $dimensions['search_width_mode'] : 'custom', 'default' ); ?>><?php esc_html_e( 'Default', 'wp-store-locator' ); ?></option>
        <option value="custom" <?php selected( isset( $dimensions['search_width_mode'] ) ? $dimensions['search_width_mode'] : 'custom', 'custom' ); ?>><?php esc_html_e( 'Custom', 'wp-store-locator' ); ?></option>
    </select>
</p>
<div class="wpsl-conditional-option wpsl-non-panel-dimensions" <?php 
    $search_mode = isset( $dimensions['search_width_mode'] ) ? $dimensions['search_width_mode'] : 'custom';
    if ( $has_panel || $search_mode === 'default' ) { 
        echo 'style="display:none;"'; 
    } 
?>>
    <p>
        <label for="wpsl-search-width" class="wpsl-hidden"><?php esc_html_e( 'Search field width', 'wp-store-locator' ); ?></label>
        <span class="wpsl-input-with-unit">
            <input type="number" value="<?php echo esc_attr( $dimensions['search_width'] ); ?>" id="wpsl-search-width" name="wpsl_appearance[dimensions][search_width]">
            <span class="wpsl-unit-suffix">px</span>
        </span>
    </p>
</div>