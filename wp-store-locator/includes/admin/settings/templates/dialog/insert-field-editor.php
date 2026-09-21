<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Admin\Core\Metaboxes;

// Get the metaboxes service from the container
$metaboxes = wpsl_get_service( 'metaboxes' );

$meta_fields = $metaboxes->meta_box_fields();

$options = '';

foreach ( $meta_fields as $group_name => $fields ) {
    $options .= '<optgroup label="' . esc_html( $group_name ) . '">';

    foreach ( $fields as $field => $meta ) {
        $field_type = isset( $meta['type'] ) ? $meta['type'] : 'text';
        $options .= '<option value="' . esc_attr( $field ) . '" data-type="' . esc_attr( $field_type ) . '">' . esc_html( $field ) . '</option>';
    }

    $options .= '</optgroup>';
}
?>

<div id="wpsl-generate-field-options" class="wpsl-hide wpsl-flex-dialog" style="height: auto;" title="Generate Field Code">
    <p>
        <label for="wpsl-generate-field-types"><?php esc_html_e( 'Field type', 'wp-store-locator' ); ?></label>
        <select id="wpsl-generate-field-types">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The group and field names are escaped while the option list is built above.
            echo $options;
            ?>
        </select>
    </p>
    <p>
        <label for="wpsl-generate-field-tags"><?php esc_html_e( 'Wrapper tag', 'wp-store-locator' ); ?></label>
        <select id="wpsl-generate-field-tags">
            <option value="none"><?php esc_html_e( 'none', 'wp-store-locator' ); ?></option>
            <option value="div"><?php esc_html_e( 'div', 'wp-store-locator' ); ?></option>
            <option value="p"><?php esc_html_e( 'p', 'wp-store-locator' ); ?></option>
            <option value="span"><?php esc_html_e( 'span', 'wp-store-locator' ); ?></option>
            <option value="strong"><?php esc_html_e( 'strong', 'wp-store-locator' ); ?></option>
        </select>
    </p>

    <div class="wpsl-generate-field-css" style="display: none;">
        <p>
            <label for="wpsl-generate-field-id"><?php esc_html_e( 'CSS id', 'wp-store-locator' ); ?></label>
            <input type="text" id="wpsl-generate-field-id" value="" placeholder="<?php esc_html_e( 'Optional', 'wp-store-locator' ); ?>">
        </p>
        <p>
            <label for="wpsl-generate-field-class"><?php esc_html_e( 'CSS class', 'wp-store-locator' ); ?></label>
            <input type="text" id="wpsl-generate-field-class" value="" placeholder="<?php esc_html_e( 'Optional', 'wp-store-locator' ); ?>">
        </p>
    </div>
    
    <div class="wpsl-code-container">
        <div id="wpsl-copy-code-wrap">
            <button type="button" aria-label="<?php esc_attr_e( 'Copy code', 'wp-store-locator' ); ?>" id="wpsl-copy-code">
                <div class="wpsl-svg-tooltip"><?php esc_html_e( 'Copy', 'wp-store-locator' ); ?></div>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20">
                    <rect x="7" y="7" width="10" height="10" fill="none" stroke="#999" stroke-width="1.5" rx="1" ry="1"></rect>
                    <rect x="3" y="3" width="10" height="10" fill="white" stroke="#999" stroke-width="1.5" rx="1" ry="1"></rect>
                </svg>
            </button>
        </div>
        <pre><code id="wpsl-generate-code-preview"></code></pre>
    </div>
</div>