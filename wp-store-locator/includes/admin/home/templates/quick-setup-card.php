<?php
/**
 * The Quick setup card on the Home page.
 *
 * Always prints the open card ( the six tiles ) and the collapsed one ( the
 * summary rows ), data-state and the stylesheet decide which one shows, so
 * the script can switch between them without a reload. A hidden card is
 * printed too, with the hidden attribute, so Quick setup in the bottom line
 * can bring it back.
 *
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$quick_setup = wpsl_get_service( 'quick_setup' );
$context     = 'home';

$qs_state = $quick_setup->get_state( get_current_user_id() );

// An outline icon per question, on a 24-unit grid, colored by the row's --qs-ink.
$qs_icons = [
    'method'    => '<circle cx="11" cy="11" r="7"/><path d="M20.5 20.5l-4.6-4.6"/>',
    'countries' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
    'locate'    => '<path d="M3 11l18-8-8 18-2-8z"/>',
    'pages'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 17.5s-3-2.6-3-4.8a3 3 0 0 1 6 0c0 2.2-3 4.8-3 4.8z"/>',
    'layout'    => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M3 9h18"/><path d="M10 9v12"/>',
    'filters'   => '<path d="M4 6h10"/><path d="M18 6h2"/><circle cx="16" cy="6" r="2"/><path d="M4 12h3"/><path d="M11 12h9"/><circle cx="9" cy="12" r="2"/><path d="M4 18h12"/><circle cx="18" cy="18" r="2"/>',
];
?>
<section class="postbox wpsl-home-glance wpsl-qs" id="wpsl-quick-setup" data-state="<?php echo esc_attr( $qs_state ); ?>" aria-labelledby="wpsl-qs-title"<?php echo ( 'hidden' === $qs_state ) ? ' hidden' : ''; ?>>
    <h3><span id="wpsl-qs-title"><?php esc_html_e( 'Quick setup', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <div class="wpsl-qs-head">
            <p class="wpsl-qs-intro wpsl-qs-when-open"><?php esc_html_e( 'The six choices that shape your store locator. Each one is saved as soon as you pick it.', 'wp-store-locator' ); ?></p>
            <p class="wpsl-qs-intro wpsl-qs-when-collapsed"><?php esc_html_e( 'How your store locator searches and what it shows. Click a choice to change it.', 'wp-store-locator' ); ?></p>
            <div class="wpsl-qs-actions">
                <p class="wpsl-qs-status wpsl-qs-when-open" role="status"></p>
                <button type="button" class="button-link wpsl-qs-collapse wpsl-qs-when-open"><?php esc_html_e( 'Collapse', 'wp-store-locator' ); ?></button>
                <button type="button" class="button-link wpsl-qs-hide wpsl-qs-when-open"><?php esc_html_e( 'Hide quick setup', 'wp-store-locator' ); ?></button>
                <button type="button" class="button wpsl-qs-edit wpsl-qs-when-collapsed"><?php esc_html_e( 'Edit all', 'wp-store-locator' ); ?></button>
            </div>
        </div>

        <div class="wpsl-qs-body">
            <?php require __DIR__ . '/quick-setup-tiles.php'; ?>
        </div>

        <ul class="wpsl-qs-summary" aria-label="<?php esc_attr_e( 'Current choices', 'wp-store-locator' ); ?>">
            <?php foreach ( $quick_setup->get_summary() as $qs_row ) { ?>
                <li>
                    <button type="button" class="wpsl-qs-row" data-question="<?php echo esc_attr( $qs_row['id'] ); ?>" style="--qs-ink: <?php echo esc_attr( $qs_row['ink'] ); ?>; --qs-tint: <?php echo esc_attr( $qs_row['tint'] ); ?>;">
                        <span class="wpsl-qs-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><?php echo $qs_icons[ $qs_row['id'] ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup defined above, no user input. ?></svg></span>
                        <span class="wpsl-qs-row-text">
                            <span class="wpsl-qs-row-label"><?php echo esc_html( $qs_row['label'] ); ?></span>
                            <span class="wpsl-qs-row-value"><?php echo esc_html( $qs_row['value'] ); ?></span>
                        </span>
                        <svg class="wpsl-qs-row-chevron" aria-hidden="true" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </li>
            <?php } ?>
        </ul>
    </div>
</section>
