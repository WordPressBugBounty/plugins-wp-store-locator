<?php
/**
 * The Social Media block of the Fields Manager.
 *
 * Shows the stored social media fields, also while the "Enable the social
 * media group?" option is off, so turning the option off and on again keeps
 * the list. Its inputs post with the Store Editor settings.
 *
 * Expects $editor_data, the settings of the editor group.
 *
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$social_enabled  = ! empty( $editor_data['social_media'] );
$social_stored   = ( ! empty( $editor_data['social_fields'] ) && is_array( $editor_data['social_fields'] ) ) ? $editor_data['social_fields'] : [];
$social_platform = wpsl_social_platforms();

/**
 * Get the markup of a single social media field row.
 *
 * @since  3.1.1
 * @param  string $index     The row index used in the input names
 * @param  string $platform  The platform slug, or 'other'
 * @param  string $label     The label of an Other field
 * @param  string $css_class The CSS class of the field
 * @param  string $field     The stored field name, empty for a new row
 * @return string $row       The row
 */
$wpsl_social_row = function( $index, $platform, $label, $css_class, $field = '' ) use ( $social_platform ) {
    $name = 'wpsl_editor[social_fields][' . $index . ']';

    $options = '';

    foreach ( $social_platform as $slug => $details ) {
        $options .= '<option value="' . esc_attr( $slug ) . '"' . selected( $platform, $slug, false ) . '>' . esc_html( $details['label'] ) . '</option>';
    }

    $options .= '<option value="other"' . selected( $platform, 'other', false ) . '>' . esc_html__( 'Other', 'wp-store-locator' ) . '</option>';

    $label_hidden = ( 'other' === $platform ) ? '' : ' wpsl-hide';

    $row  = '<div class="wpsl-social-field">';
    $row .= '<span class="wpsl-social-field-icon">' . wpsl_social_icon_svg( $platform ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static svg markup built by wpsl_social_icon_svg()
    $row .= '<select class="wpsl-social-field-platform" name="' . esc_attr( $name ) . '[platform]" aria-label="' . esc_attr__( 'Platform', 'wp-store-locator' ) . '">' . $options . '</select>';
    $row .= '<input type="text" class="wpsl-social-field-label' . $label_hidden . '" name="' . esc_attr( $name ) . '[label]" value="' . esc_attr( $label ) . '" maxlength="50" placeholder="' . esc_attr__( 'Label', 'wp-store-locator' ) . '" aria-label="' . esc_attr__( 'Label', 'wp-store-locator' ) . '" autocomplete="off">';
    $row .= '<input type="text" class="wpsl-social-field-class" name="' . esc_attr( $name ) . '[css_class]" value="' . esc_attr( $css_class ) . '" placeholder="' . esc_attr__( 'Optional CSS class', 'wp-store-locator' ) . '" aria-label="' . esc_attr__( 'CSS class', 'wp-store-locator' ) . '" autocomplete="off">';
    // The stored name, a renamed Other field keeps it so the links on the stores stay.
    $row .= '<input type="hidden" class="wpsl-social-field-name" name="' . esc_attr( $name ) . '[name]" value="' . esc_attr( $field ) . '">';
    $row .= '<a href="#" class="wpsl-social-field-remove">' . esc_html__( 'Delete', 'wp-store-locator' ) . '</a>';
    // Drag the rows to set the order of the links, in the store editor and on the front end.
    $row .= '<span class="wpsl-social-field-handle" aria-hidden="true">' . wpsl_get_svg_icon( 'draggable' ) . '</span>';
    $row .= '</div>';

    return $row;
};
?>
<div id="wpsl-social-fields" class="<?php if ( ! $social_enabled ) { echo 'wpsl-hide'; } ?>">
    <h4><?php echo esc_html( wpsl_social_group_label() ); ?></h4>
    <p class="wpsl-defaults">
        <em><?php esc_html_e( 'The links of these platforms are added to the store editor. For the Other platform enter a label, and optionally a CSS class to show an icon from an icon font that your theme loads, for example fa-brands fa-discord.', 'wp-store-locator' ); ?></em>
    </p>
    <div id="wpsl-social-field-list">
        <?php
        $social_index = 0;

        foreach ( $social_stored as $social_field ) {
            if ( ! is_array( $social_field ) || empty( $social_field['platform'] ) || ! is_string( $social_field['platform'] ) ) {
                continue;
            }

            if ( 'other' !== $social_field['platform'] && ! isset( $social_platform[ $social_field['platform'] ] ) ) {
                continue;
            }

            $social_label = ( 'other' === $social_field['platform'] && isset( $social_field['label'] ) && is_string( $social_field['label'] ) ) ? $social_field['label'] : '';
            $social_class = ( isset( $social_field['css_class'] ) && is_string( $social_field['css_class'] ) ) ? $social_field['css_class'] : '';
            $social_name  = ( isset( $social_field['name'] ) && is_string( $social_field['name'] ) ) ? $social_field['name'] : '';

            echo $wpsl_social_row( (string) $social_index, $social_field['platform'], $social_label, $social_class, $social_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The row escapes its values

            $social_index++;
        }
        ?>
    </div>
    <input type="hidden" name="wpsl_editor[social_fields_submitted]" value="1">
    <?php // The submit class gives it the same spacing as the buttons below the custom fields. ?>
    <p class="submit wpsl-social-field-actions">
        <input id="wpsl-add-social-field" type="button" value="<?php esc_attr_e( '+ Add Social Media Field', 'wp-store-locator' ); ?>" class="button">
        <input type="submit" value="<?php esc_attr_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
    </p>

    <?php // The icons the rows swap between, outside the list so they don't post. ?>
    <div id="wpsl-social-field-icons" class="wpsl-hide" aria-hidden="true">
        <?php
        foreach ( array_merge( array_keys( $social_platform ), [ 'other' ] ) as $social_slug ) {
            echo '<span data-platform="' . esc_attr( $social_slug ) . '">' . wpsl_social_icon_svg( $social_slug ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static svg markup built by wpsl_social_icon_svg()
        }
        ?>
    </div>
</div>
<script type="text/template" id="wpsl-social-field-row">
<?php
echo $wpsl_social_row( '{index}', 'other', '', '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The row escapes its values
?>
</script>
