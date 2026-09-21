<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
    return;
}

use WPSL\Admin\Tools\Status_Report;

$status = wpsl_get_service( 'status_report' );
?>

<div id="wpsl-content-wrap" class="wpsl-system-status">
    <form action="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_tools&section=status-report' ) ); ?>" method="post" dir="ltr">
        <div class="wpsl-content-item">
            <textarea id="wpsl-report-textarea" readonly="readonly" name="wpsl-status-report"><?php echo esc_textarea( $status->get_report() ); ?></textarea>
            <p class="submit">
                <input type="hidden" name="wpsl-action" value="download_status_report" />
                <?php wp_nonce_field( 'wpsl_status_report', 'wpsl_status_report_nonce' ); ?>
                <?php submit_button( 'Download Report', 'primary', 'wpsl-status-download', false ); ?>
            </p>
        </div>
    </form>
</div>