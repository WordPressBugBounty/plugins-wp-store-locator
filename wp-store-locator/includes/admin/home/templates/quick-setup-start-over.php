<?php
/**
 * The line at the bottom of the Home page.
 *
 * "Starting over? Run the setup wizard again", and when the Quick setup card
 * is hidden "Quick setup · Run the setup wizard again", where Quick setup
 * brings the card back. Both versions are printed, the one that doesn't
 * apply has the hidden attribute, the script swaps them.
 *
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$qs_state = wpsl_get_service( 'quick_setup' )->get_state( get_current_user_id() );
?>
<p class="wpsl-home-start-over" data-state="<?php echo esc_attr( $qs_state ); ?>">
    <span class="wpsl-qs-start-hidden"<?php echo ( 'hidden' === $qs_state ) ? '' : ' hidden'; ?>><button type="button" class="button-link wpsl-qs-show"><?php esc_html_e( 'Quick setup', 'wp-store-locator' ); ?></button> &middot; </span>
    <span class="wpsl-qs-start-default"<?php echo ( 'hidden' === $qs_state ) ? ' hidden' : ''; ?>><?php esc_html_e( 'Starting over?', 'wp-store-locator' ); ?> </span>
    <a href="<?php echo esc_url( admin_url( 'index.php?page=wpsl-onboarding' ) ); ?>"><?php esc_html_e( 'Run the setup wizard again', 'wp-store-locator' ); ?></a>
</p>
