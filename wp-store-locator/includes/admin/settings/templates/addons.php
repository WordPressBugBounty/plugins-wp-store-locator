<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$campaign_params = '?utm_source=wpsl-add-ons&utm_medium=banner&utm_campaign=add-ons';

// Without this the failure branch below reads an undefined variable.
$add_ons = null;

// Load the add-on data from an existing transient, or grab new data from the remote URL.
if ( false === ( $add_ons = get_transient( 'wpsl_addons' ) ) ) {
    $response = wp_remote_get( 'https://s3.amazonaws.com/wpsl/add-ons.json' );

    if ( ! is_wp_error( $response ) ) {
        $add_ons = json_decode( wp_remote_retrieve_body( $response ) );

        if ( $add_ons ) {
            set_transient( 'wpsl_addons', $add_ons, WEEK_IN_SECONDS );
        }
    }
}

/*
 * Whether an add-on from the remote list is installed.
 *
 * The list names each add-on's global class ( WPSL_CSV ), but the current
 * add-on releases declare it inside a namespace, so the namespaced class
 * is checked as well. The keyword in the listed name picks which one.
 */
$addon_installed = function( $class ) {
    if ( ! is_string( $class ) || '' === $class ) {
        return false;
    }

    if ( class_exists( $class ) ) {
        return true;
    }

    $namespaced = [
        'csv'        => 'WPSL_CSV\WPSL_CSV',
        'statistics' => 'WPSL_Statistics\WPSL_Statistics',
        'widget'     => 'WPSL_Widget\WPSL_Widgets',
    ];

    foreach ( $namespaced as $keyword => $namespaced_class ) {
        if ( false !== stripos( $class, $keyword ) ) {
            return class_exists( $namespaced_class );
        }
    }

    return false;
};

/*
 * Which add-ons the bundle covers, and whether
 * this install already has all of them.
 */
$bundle_classes = [];
$bundle_owned   = false;

foreach ( (array) $add_ons as $add_on ) {
    if ( empty( $add_on->bundle ) || empty( $add_on->classes ) ) {
        continue;
    }

    $bundle_classes = array_filter( (array) $add_on->classes, 'is_string' );
    $bundle_owned   = ! empty( $bundle_classes );

    foreach ( $bundle_classes as $bundle_class ) {
        if ( ! $addon_installed( $bundle_class ) ) {
            $bundle_owned = false;
            break;
        }
    }

    break;
}
?>
<div id="wpsl-content-wrap" class="wpsl-grid-wrap wpsl-addon-list">
    <?php
    if ( $add_ons ) {
        foreach ( $add_ons as $add_on ) {
            if ( $bundle_owned && ! empty( $add_on->class ) && in_array( $add_on->class, $bundle_classes, true ) ) {
                continue;
            }

            $is_bundle = ! empty( $add_on->bundle );
            $installed = $is_bundle
                ? $bundle_owned
                : ( ! empty( $add_on->class ) && $addon_installed( $add_on->class ) );
        ?>
        <div class="wpsl-grid-item">
            <?php if ( ! empty( $add_on->url ) ) { ?>
                <a title="<?php echo esc_attr( $add_on->name ); ?>" href="<?php echo esc_url( $add_on->url ) . $campaign_params; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data ?>">
                    <img src="<?php echo esc_url( $add_on->img ); ?>"/>
                </a>
            <?php } else { ?>
                <img src="<?php echo esc_url( $add_on->img ); ?>"/>
            <?php } ?>

            <p class="wpsl-addon-desc"><?php
                echo esc_html( $add_on->desc );

                /*
                 * Optional footnote, used by the bundle to say what the Pro
                 * upgrade is expected to bring. Plain text, same as the
                 * description - no markup crosses the wire, so an older release
                 * that does not know this key simply ignores it.
                 */
                if ( ! empty( $add_on->tooltip ) ) {
                ?> <span class="wpsl-info" tabindex="0"><span class="wpsl-info-text"><?php echo esc_html( $add_on->tooltip ); ?></span></span><?php
                }
            ?></p>

            <div class="wpsl-addon-status">
                <?php if ( $installed ) { ?>
                    <p><strong><?php
                    /*
                     * The bundle is not a plugin, so "Already Installed." does
                     * not describe it. What is true, and all this can actually
                     * detect, is that every add-on is present.
                     */
                    if ( $is_bundle ) {
                        esc_html_e( 'You have every add-on.', 'wp-store-locator' );
                    } else {
                        esc_html_e( 'Already Installed.', 'wp-store-locator' );
                    }
                    ?></strong></p>
                <?php } else if ( isset( $add_on->soon ) && $add_on->soon ) { ?>
                    <p><strong><?php esc_html_e( 'Coming soon!', 'wp-store-locator' ); ?></strong></p>
                <?php } else { ?>
                    <a class="button-primary" href="<?php echo esc_url( $add_on->url ) . $campaign_params; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data ?>">
                        <?php
                        /*
                         * The bundle is every add-on at once, so the singular
                         * "this add-on" misdescribes it. Both labels stay as
                         * literals here rather than coming from the remote JSON,
                         * so that both remain translatable.
                         */
                        if ( $is_bundle ) {
                            esc_html_e( 'Get the Bundle', 'wp-store-locator' );
                        } else {
                            esc_html_e( 'Get This Add-On', 'wp-store-locator' );
                        }
                        ?>
                    </a>
                <?php } ?>
            </div>
        </div>
        <?php
        }
    } else {
        echo '<p>'. esc_html__( 'Loading of the add-on list from the server has failed.', 'wp-store-locator' ) . '</p>';
        echo '<p>'. esc_html__( 'Please try again later.', 'wp-store-locator' ) . '</p>';
    }
    ?>
</div>