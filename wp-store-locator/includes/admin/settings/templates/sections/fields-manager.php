<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

ob_start();
require_once( WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/fields-manager/field-input.php' );

$field_input_template = ob_get_clean();

$fields = [
    'defaults' => $metaboxes->get_field_names( [ 'only_defaults' => true ] ),
    'custom'   => $metaboxes->get_field_names( [ 'only_custom' => true ] ),
];

$editor_data = $wpsl_settings->get_group( 'editor' );
$section_settings = $editor_data['field_manager'];
?>
<section id="wpsl-fields-manager" data-protected-fields="<?php echo esc_attr( implode( ',', $fields[ 'defaults' ] ) ); ?>"  data-custom-fields="<?php echo esc_attr( implode( ',', $fields[ 'custom' ] ) ); ?>" class="postbox">
    <h3><span><?php esc_html_e( 'Fields Manager', 'wp-store-locator' ); ?></span> <span class="wpsl-groups-header" style="display:none;">/ <?php esc_html_e( 'Groups Manager', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <div class="wpsl-fields-manager-wrap">
            <?php            
            if ( is_array( $section_settings['groups'] ) && ! empty( $section_settings['groups'] ) ) {
            ?>
            <div class="wpsl-fields-manager-options">
                <?php echo $field_manager->get_groups_dropdown(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                <input id="wpsl-manage-groups" type="submit" value="<?php esc_html_e( 'Manage Groups', 'wp-store-locator' ); ?>" class="button">
            </div>

            <ul id="wpsl-fields-header">
                <li><?php esc_html_e( 'Label', 'wp-store-locator' ); ?></li>
                <li><?php esc_html_e( 'Name', 'wp-store-locator' ); ?></li>
                <li><?php esc_html_e( 'Type', 'wp-store-locator' ); ?></li>
                <li><?php esc_html_e( 'Actions', 'wp-store-locator' ); ?></li>
            </ul>

                <?php
                $placeholders = [ '{group_id}', '{id}', '{label}', '{name}', '{type}', '{default}', '{options}', '{required}' ];
                
                if ( is_array( $section_settings['groups'] ) ) {
                    $i = 0;

                    foreach ( $section_settings['groups'] as $group_id => $group_name ) {
                        if ( isset( $section_settings['fields'][$group_id] ) ) {
                            $fields = $section_settings['fields'][$group_id];

                            $maybe_hide = ( $i > 0 ) ? 'style="display:none"' : '';

                            echo '<div class="wpsl-fields-list-wrap" data-group-id="' . esc_attr( $group_id ) . '" ' . $maybe_hide . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data

                            foreach ( $fields as $field_id => $field_names ) {

                                // Extract and sanitize the fields using list()
                                $sanitized_fields = array_map( 'sanitize_text_field', [
                                    $field_names['label'] ?? '',
                                    $field_names['name'] ?? '',
                                    $field_names['type'] ?? '',
                                ] );

                                // Use list() to assign to individual variables
                                list( $label, $field_name, $field_type ) = $sanitized_fields;

                                $required_field = ( isset( $fields[ $field_id ]['required'] ) ) ? true : false;
                                $required_star = $required_field ? ' <span class="wpsl-warning">*</span>' : '';

                                echo '<div class="wpsl-field-item" data-field="' . esc_attr( $field_id ) . '">';
                                echo '<ul>';
                                $label_display = mb_strlen( $label ) > 45 ? mb_substr( $label, 0, 45 ) . '...' : $label;

                                echo '<li class="wpsl-field-label">' . esc_html( $label_display ) . $required_star . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
                                echo '<li class="wpsl-field-name">' . esc_html( $field_name ) . '</li>';
                                echo '<li class="wpsl-field-type">' . esc_html( $field_type ) . '</li>';
                                echo '<li class="wpsl-field-item-actions"><a class="wpsl-field-edit" href="#">' . esc_html__( 'Edit', 'wp-store-locator' ) . '</a> <a class="wpsl-field-delete" href="#">' . esc_html__( 'Delete', 'wp-store-locator' ) . '</a><span>' . wpsl_get_svg_icon( 'draggable' ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns static SVG markup
                                echo '</ul>';

                                if ( in_array( $field_type, [ 'dropdown', 'textarea' ] ) ) {
                                    $default = sanitize_textarea_field( $field_names['default'] );
                                } else {
                                    $default = sanitize_text_field( $field_names['default'] );
                                }

                                /**
                                 * The placeholders are replaced inside value="" attributes
                                 * in the field input template, so the values have to be
                                 * attribute escaped to prevent breaking out of them.
                                 */
                                $replacements = [
                                    '{group_id}' => esc_attr( $group_id ),
                                    '{id}' => esc_attr( $field_id ),
                                    '{label}' => esc_attr( $label ),
                                    '{name}' => esc_attr( $field_name ),
                                    '{type}' => esc_attr( $field_type ),
                                    '{default}' => esc_attr( $default ),
                                ];

                                // Start with the template
                                $template = $field_input_template;

                                // Apply the basic replacements first
                                $template = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

                                // Handle options separately
                                if ( $field_type === 'dropdown' ) {
                                    $dropdown_options = isset( $field_names['options'] ) ? sanitize_textarea_field( $field_names['options'] ) : '';

                                    $options_html = '<p class="wpsl-field-dropdown-options">';
                                    $options_html .= '<label for="wpsl-fields-' . esc_attr( $field_id ) . '-options">' . esc_html__( 'Dropdown options', 'wp-store-locator' ) . '</label>';
                                    $options_html .= '<textarea id="wpsl-fields-' . esc_attr( $field_id ) . '-options" class="wpsl-required-field" name="wpsl_editor[field_manager][fields][' . esc_attr( $group_id ) . '][' . esc_attr( $field_id ) . '][options]" placeholder="' . esc_html__( 'Required, enter each option on a new line', 'wp-store-locator' ) . '">' . esc_textarea( $dropdown_options ) . '</textarea>';
                                    $options_html .= '</p>';
                                } else {
                                    $options_html = '';
                                }

                                $template = str_replace( '{options}', $options_html, $template );

                                // Handle required attribute last
                                $required_value = $required_field ? 'checked="checked"' : '';
                                $template = str_replace( '{required}', $required_value, $template );

                                echo '<div class="wpsl-field-editor" style="display:none;">';
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The placeholder values are escaped when the replacements array is built above.
                                echo $template;
                                echo '</div>';
                                echo '</div>';
                            }

                            echo '</div>';
                        } else {
                            echo '<div class="wpsl-fields-list-wrap" data-group-id="' . esc_attr( $group_id ) . '"></div>';
                        }

                        $i++;
                    }
                } else {
                    ?>
                    <div class="wpsl-fields-list-wrap wpsl-first-field">
                        <div class="wpsl-field-item wpsl-field-expanded">
                            <ul>
                                <li class="wpsl-field-label">( <?php esc_html_e( 'No label', 'wp-store-locator' ); ?> )</li>
                                <li class="wpsl-field-name"></li>
                                <li class="wpsl-field-type"><?php esc_html_e( 'Text', 'wp-store-locator' ); ?></li>
                                <li class="wpsl-field-item-actions">
                                    <a class="wpsl-field-edit" href="#"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?></a> 
                                    <a class="wpsl-field-delete" href="#"><?php esc_html_e( 'Delete', 'wp-store-locator' ); ?></a>
                                </li>
                            </ul>
                            <div class="wpsl-field-editor">
                                <?php
                                $template = str_replace( $placeholders, '', $field_input_template );

                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static template HTML, all placeholders are replaced with empty strings above.
                                echo $template;
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
            <div id="wpsl-fields-first-group" <?php if ( ! empty( $section_settings['groups'] ) ) { echo 'class="wpsl-hide"'; } ?>>
                <p><?php esc_html_e( 'Before you can create custom fields, you first have to create a new group.', 'wp-store-locator' ); ?></p>
                <p class="wpsl-has-preloader">
                    <input type="text" value="" placeholder="Group name" name="wpsl_editor[field_manager][fields][label]" autocomplete="off" id="wpsl-group-name" maxlength="50">
                    <input type="submit" value="<?php esc_html_e( 'Create Group', 'wp-store-locator' ); ?>" class="button-primary" id="wpsl-create-first-group">
                </p>
                <input type="hidden" id="wpsl-create-group-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-create_group' ) ); ?>"/>
            </div>
            <div id="wpsl-field-actions" <?php if ( empty( $section_settings['groups'] ) ) { echo 'style="display:none;"'; } ?>>
                <p class="submit" style="justify-content: flex-end;">
                    <input id="wpsl-add-field" type="submit" value="<?php esc_html_e( '+ Add Field', 'wp-store-locator' ); ?>" class="button">
                    <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
                    <input type="hidden" id="wpsl-delete-field-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-delete_field' ) ); ?>"/>
                </p>
            </div>
        </div>
        <div class="wpsl-groups-manager-wrap" style="display:none;">
            <ul id="wpsl-groups-header">
                <li><?php esc_html_e( 'Name', 'wp-store-locator' ); ?></li>
                <li><?php esc_html_e( 'Actions', 'wp-store-locator' ); ?></li>
            </ul>
            <ul id="wpsl-manage-group-list">
                <?php
                if ( is_array( $section_settings['groups'] ) ) {
                    foreach ( $section_settings['groups'] as $group_id => $group_name ) {
                        echo '<li class="wpsl-group-item" data-group-id="' . esc_attr( $group_id ) . '"><input type="text" value="' . esc_attr( $group_name ) . '" name="wpsl_editor[field_manager][groups][' . esc_attr( $group_id ) . ']"><a class="wpsl-group-delete" href="#">' . esc_html__( 'Delete', 'wp-store-locator' ) . '</a></li>';
                    }
                }
                ?>
            </ul>
            <p class="wpsl-defaults">
                <em><?php esc_html_e( 'Removing a group will automatically delete all related fields!', 'wp-store-locator' ) ?></em>
            </p>
            <input class="button-secondary" id="wpsl-add-group" type="submit" value="<?php esc_html_e( '+ Add Group', 'wp-store-locator' ); ?>" />
            <input class="button-primary" id="wpsl-show-fields-manager" type="submit" value="<?php esc_html_e( 'Return to Fields Manager', 'wp-store-locator' ); ?>" />
            <input type="hidden" id="wpsl-delete-group-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-delete_group' ) ); ?>"/>
            <input type="hidden" id="wpsl-update-groups-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-update_group' ) ); ?>"/>
        </div>
    </div>
</section>

<script type="text/html" id="wpsl-fields-manager-options">
    <div class="wpsl-fields-manager-options">
        <select class="wpsl-field-group-list" name="wpsl_editor[fields][selected_group]">
        </select>
        <input id="wpsl-manage-groups" type="submit" value="<?php esc_html_e( 'Manage Groups', 'wp-store-locator' ); ?>" class="button">
    </div>
</script>
<script type="text/html" id="wpsl-fields-header-template">
    <ul id="wpsl-fields-header">
        <li><?php esc_html_e( 'Label', 'wp-store-locator' ); ?></li>
        <li><?php esc_html_e( 'Name', 'wp-store-locator' ); ?></li>
        <li><?php esc_html_e( 'Type', 'wp-store-locator' ); ?></li>
        <li><?php esc_html_e( 'Actions', 'wp-store-locator' ); ?></li>
    </ul>
</script>
<script type="text/html" id="wpsl-field-header-template">
    <ul>
        <li class="wpsl-field-label">( <?php esc_html_e( 'No label', 'wp-store-locator' ); ?> )</li>
        <li class="wpsl-field-name"></li>
        <li class="wpsl-field-type"><?php esc_html_e( 'Text', 'wp-store-locator' ); ?></li>
        <li class="wpsl-field-item-actions">
            <a class="wpsl-field-edit" href="#"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?></a> 
            <a class="wpsl-field-delete" href="#"><?php esc_html_e( 'Delete', 'wp-store-locator' ); ?></a>
            <span><?php echo wpsl_get_svg_icon( 'draggable' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns static SVG markup ?></span>
        </li>
    </ul>
</script>
<script type="text/html" id="wpsl-field-input-template">
<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static template HTML with literal {placeholder} tags, contains no dynamic data.
echo $field_input_template;
?>
</script>
<script type="text/html" id="wpsl-group-input-template">
    <li class="wpsl-group-item" data-group-id="{group_id}">
        <input type="text" value="" name="wpsl_editor[field_manager][groups][{group_id}]" maxlength="50">
        <div class="wpsl-group-item-actions">
            <a class="wpsl-group-delete" href="#"><?php esc_html_e( 'Delete', 'wp-store-locator' ); ?></a>
            <span><?php echo wpsl_get_svg_icon( 'draggable' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns static SVG markup ?></span>
        </div>
    </li>
</script>