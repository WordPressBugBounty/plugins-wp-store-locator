<?php
/**
 * The alerts list.
 *
 * Shared by the Alerts section on the Settings page and the Alerts box on
 * the Home page, so the two cannot drift. Both screens can dismiss, so
 * both need the same control.
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_alert_items = ( isset( $wpsl_alert_items ) && is_array( $wpsl_alert_items ) ) ? $wpsl_alert_items : [];
$wpsl_has_alerts  = ! empty( $wpsl_alert_items );
?>
<div class="wpsl-no-alerts" <?php echo $wpsl_has_alerts ? 'style="display: none;"' : ''; ?>>
    <p><?php esc_html_e( 'No active alerts, everything is running smoothly!', 'wp-store-locator' ); ?></p>
</div>

<ul class="wpsl-alerts-list" <?php echo ! $wpsl_has_alerts ? 'style="display: none;"' : ''; ?>>
    <?php foreach ( $wpsl_alert_items as $wpsl_alert_key => $wpsl_alert ) : ?>
        <li data-plugin="<?php echo esc_attr( $wpsl_alert_key ); ?>">
            <span class="wpsl-alert-badge" aria-hidden="true"><span class="wpsl-icon-alerts"></span></span>
            <div class="wpsl-alert-description">
                <?php echo wp_kses( \WPSL\Admin\Core\Alerts::link_settings_sections( $wpsl_alert['description'] ), [ 'a' => [ 'href' => [], 'class' => [], 'data-item' => [], 'target' => [], 'rel' => [] ] ] ); ?>
                <?php if ( ! empty( $wpsl_alert['details'] ) ) : ?>
                <div class="wpsl-alert-details">
                    <?php
                    echo wp_kses(
                        \WPSL\Admin\Core\Alerts::link_settings_sections( $wpsl_alert['details'] ),
                        [
                            'a'      => [ 'href' => [], 'class' => [], 'data-item' => [], 'target' => [], 'rel' => [] ],
                            'p'      => [],
                            'strong' => [],
                            'br'     => [],
                            'ol'     => [],
                            'ul'     => [],
                            'li'     => [],
                        ]
                    );
                    ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
            if ( ! isset( $wpsl_alert['dismissible'] ) || $wpsl_alert['dismissible'] ) :
                ?>
            <span class="wpsl-dismiss-alert" data-plugin="<?php echo esc_attr( $wpsl_alert_key ); ?>" title="<?php esc_attr_e( 'Dismiss', 'wp-store-locator' ); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                    <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path>
                </svg>
            </span>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>