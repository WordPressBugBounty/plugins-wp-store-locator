<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<p>
    <label for="wpsl-fields-{id}-label"><?php esc_html_e( 'Label', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The name of the field on the edit page.', 'wp-store-locator' ); ?></span></span></label>
    <input type="text" value="{label}" name="wpsl_editor[field_manager][fields][{group_id}][{id}][label]" placeholder="<?php esc_html_e( 'Required', 'wp-store-locator' ); ?>" class="wpsl-field-label wpsl-required-field" id="wpsl-fields-{id}-label">
</p>
<p>
    <label for="wpsl-fields-{id}-name"><?php esc_html_e( 'Name', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The name value is used in the template code and has to be unique. Only single words ( dashes and underscores allowed ). Must start with a letter or underscore.', 'wp-store-locator' ); ?></span></span></label>
    <input type="text" value="{name}" name="wpsl_editor[field_manager][fields][{group_id}][{id}][name]" placeholder="<?php esc_html_e( 'Required', 'wp-store-locator' ); ?>" class="wpsl-field-name wpsl-required-field" id="wpsl-fields-{id}-name">
</p>
<p class="wpsl-field-type-options">
    <label for="wpsl-fields-{id}-type"><?php esc_html_e( 'Type', 'wp-store-locator' ); ?></label>
    <select id="wpsl-fields-{id}-type" name="wpsl_editor[field_manager][fields][{group_id}][{id}][type]" data-selected="{type}">
        <option value="text"><?php esc_html_e( 'Text', 'wp-store-locator' ); ?></option>
        <option value="textarea"><?php esc_html_e( 'Text area', 'wp-store-locator' ); ?></option>
        <option value="email"><?php esc_html_e( 'Email', 'wp-store-locator' ); ?></option>
        <option value="tel"><?php esc_html_e( 'Telephone', 'wp-store-locator' ); ?></option>
        <option value="url"><?php esc_html_e( 'Url', 'wp-store-locator' ); ?></option>
        <option value="checkbox"><?php esc_html_e( 'Checkbox', 'wp-store-locator' ); ?></option>
        <option value="dropdown"><?php esc_html_e( 'Dropdown', 'wp-store-locator' ); ?></option>
    </select>
</p>
{options}
<p class="wpsl-field-default">
    <label for="wpsl-fields-{id}-default"><?php esc_html_e( 'Default value', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-bbcode-info" style="display: none;"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to the BB code documentation, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'BB code is supported in text area fields: [strong]bold[/strong], [em]italic[/em], [br] line break, [url=https://example.com]link text[/url]. %1$sRead more%2$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/bb-code" target="_blank">', '</a>' ) ); ?></span></span></label>
    <input type="text" value="{default}" name="wpsl_editor[field_manager][fields][{group_id}][{id}][default]" id="wpsl-fields-{id}-default">
</p>
<p class="wpsl-is-field-required">
    <label for="wpsl-fields-{id}-required"><?php esc_html_e( 'Required', 'wp-store-locator' ); ?></label>
    <input type="checkbox" value="1" name="wpsl_editor[field_manager][fields][{group_id}][{id}][required]" id="wpsl-fields-{id}-required" {required}>
</p>