<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="wpsl-content-wrap">
<form id="wpsl-settings-form" action="" method="post">
    <?php settings_errors(); ?>
    <section class="postbox">
    <table class="form-table wpsl-license-table" role="presentation">
        <tbody>
        <?php
        if ( $this->licenses ) {
            $is_first = true;
            foreach ( $this->licenses as $wpsl_license ) {
                $license_details = get_transient( $wpsl_license['short_name'] . '_license_details' );
                $license_key     = get_option( $wpsl_license['short_name'] . '_license_key' );

                if ( $license_key ) {
                    $license_status = isset( $license_details['status'] ) ? $license_details['status'] : 'inactive';
                } else {
                    $license_status = 'inactive';
                }

                $support_status = isset( $license_details['support'] ) ? $license_details['support'] : 'active';

                /*
                 * Licenses stored before expiry was split off from the license
                 * status still carry status 'expired'. Read them the new way so
                 * they don't look inactive until the transient refreshes.
                 */
                if ( 'expired' === $license_status ) {
                    $license_status = 'valid';
                    $support_status = 'expired';
                }

                $expire_date = '';

                if ( ! empty( $license_details['expiration'] ) ) {
                    $expire_timestamp = strtotime( $license_details['expiration'] );
                    $expire_date      = esc_html( date_i18n( 'M j, Y', $expire_timestamp ) );
                }

                $input_id = 'wpsl_licenses[' . esc_attr( $wpsl_license['short_name'] ) . ']';
                ?>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $wpsl_license['name'] ); ?></label>
                    </th>
                    <td>
                        <div class="wpsl-license_control">
                            <input type="password" autocomplete="off" class="regular-text" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_id ); ?>" value="<?php echo esc_attr( $license_key ); ?>"<?php if ( $is_first ) { echo ' autofocus'; $is_first = false; } ?> />
                            <div class="wpsl-license_actions">
                                <?php if ( $license_status === 'valid' ) : ?>
                                <input type="submit" class="button button-secondary" name="<?php echo esc_attr( $wpsl_license['short_name'] ); ?>_license_key_deactivate" value="<?php esc_attr_e( 'Deactivate', 'wp-store-locator' ); ?>" />
                                <?php else : ?>
                                <input type="submit" class="button button-secondary" name="submit" value="<?php esc_attr_e( 'Activate', 'wp-store-locator' ); ?>" />
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php wp_nonce_field( $wpsl_license['short_name'] . '_license-nonce', $wpsl_license['short_name'] . '_license-nonce' ); ?>
                        <?php
                        switch ( $license_status ) {
                            case 'valid':
                                if ( 'expired' === $support_status ) {
                                    ?>
                                    <div class="wpsl-license-data wpsl-license-expired">
                                        <p>
                                            <a class="button button-primary" target="_blank" href="https://wpstorelocator.co/account/#license-keys"><?php esc_html_e( 'Renew your support', 'wp-store-locator' ); ?></a>
                                            <span class="wpsl-info wpsl-warning" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'wp-store-locator' ); ?>">
                                                <span class="wpsl-info-text wpsl-hide">
                                                    <?php
                                                    if ( $expire_date ) {
                                                        /* translators: %1$s: line break, %2$s: support expiration date */
                                                        printf( wp_kses( __( 'Your support expired%1$son %2$s.', 'wp-store-locator' ), [ 'br' => [] ] ), '<br>', esc_html( $expire_date ) );
                                                    } else {
                                                        esc_html_e( 'Your support has expired.', 'wp-store-locator' );
                                                    }
                                                    ?>
                                                    <br><br>
                                                    <?php /* translators: %1$s: opening link tag, %2$s: closing link tag, %3$s: opening bold tag, %4$s: closing bold tag */ ?>
                                                    <?php printf( wp_kses_post( __( 'Your add-ons keep receiving updates. Renewing through your %1$saccount page%2$s comes with a %3$s30%% discount%4$s.', 'wp-store-locator' ) ), '<a target="_blank" href="https://wpstorelocator.co/account/">', '</a>', '<strong>', '</strong>' ); ?>
                                                </span>
                                            </span>
                                        </p>
                                    </div>
                                    <?php
                                } else {
                                    ?>
                                    <div class="wpsl-license-data wpsl-license-valid">
                                        <?php /* translators: %s: support expiration date */ ?>
                                        <p><?php printf( esc_html__( 'Your support expires on %s.', 'wp-store-locator' ), esc_html( $expire_date ) ); ?></p>
                                    </div>
                                    <?php
                                }
                                break;
                            default:
                                ?>
                                <div class="wpsl-license-data wpsl-license-inactive">
                                    <?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ ?>
                                    <p><?php printf( wp_kses_post( __( 'Please provide a valid %1$slicense key%2$s.', 'wp-store-locator' ) ), '<a target="_blank" href="https://wpstorelocator.co/account/#license-keys">', '</a>' ); ?>
                                        <span class="wpsl-info wpsl-warning" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'wp-store-locator' ); ?>">
                                            <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Required for add-on updates and support. Updates keep working after your support expires.', 'wp-store-locator' ); ?></span>
                                        </span>
                                    </p>
                                </div>
                                <?php
                                break;
                        }
                        ?>
                    </td>
                </tr>
                <?php
            }
        }
        ?>
        </tbody>
    </table>
    <p class="submit">
        <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button button-primary" id="submit" name="submit">
    </p>
    </section>
</form>
</div>