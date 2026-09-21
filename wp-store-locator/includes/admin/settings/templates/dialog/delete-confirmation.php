<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wpsl-delete-confirmation" class="wpsl-hide" style="height: auto;">
    <p><?php esc_html_e( 'Are you sure you want to delete', 'wp-store-locator' ); ?> <span></span>?</p>
    <p>
        <input type="submit" id="wpsl-cancel-delete" class="button-secondary" value="<?php esc_html_e( 'Cancel', 'wp-store-locator' ); ?>">
        <input type="submit" id="wpsl-confirm-delete" class="button-primary" value="<?php esc_html_e( 'Delete', 'wp-store-locator' ); ?>">
        <input type="hidden" id="wpsl-delete-field-nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpsl-delete' ) ); ?>"/>
    </p>
</div>