<?php
/**
 * Simple mode
 *
 * One site option that shortens the WPSL menu and the Settings page for
 * sites that use a small part of the plugin. Nothing is deleted or becomes
 * unreachable, and a feature the site is actually using is never hidden
 * ( see usage() ).
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Home;

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Markers\Custom_Markers;
use WPSL\Core\Shapes\Repository as Shapes_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Mode {

    /**
     * The site option.
     *
     * @since 3.0.0
     */
    const OPTION = 'wpsl_simple_mode';

    /**
     * Nonce action behind the header's mode link.
     *
     * @since 3.0.0
     */
    const ACTION = 'wpsl-set-simple-mode';

    /**
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * @since 3.0.0
     * @var \WPSL\Core\Markers\Custom_Markers
     */
    private $markers;

    /**
     * @since 3.0.0
     * @var \WPSL\Core\Shapes\Repository
     */
    private $shapes;

    /**
     * @since 3.0.0
     */
    public function __construct( WpslSettings $settings, Custom_Markers $markers, Shapes_Repository $shapes ) {
        $this->settings = $settings;
        $this->markers  = $markers;
        $this->shapes   = $shapes;

        add_filter( 'admin_body_class', [ $this, 'body_class' ] );
        add_action( 'admin_init', [ $this, 'maybe_toggle' ] );
    }

    /**
     * @since  3.0.0
     * @return bool
     */
    public function is_enabled() {
        return (bool) get_option( self::OPTION, false );
    }

    /**
     * The features simple mode is allowed to hide, and their labels.
     *
     * @since  3.0.0
     * @return array Feature id => label
     */
    public function features() {
        return [
            'marker_studio'  => esc_html__( 'Marker Studio', 'wp-store-locator' ),
            'map_shapes'     => esc_html__( 'Map Shapes', 'wp-store-locator' ),
            'fields-manager' => esc_html__( 'Fields Manager', 'wp-store-locator' ),
        ];
    }

    /**
     * Whether the site is using a feature, and the sentence that says so.
     *
     * @since  3.0.0
     * @param  string $feature One of the features() keys.
     * @return array  [ 'in_use' => bool, 'reason' => string ]
     */
    public function usage( $feature ) {
        switch ( $feature ) {
            case 'marker_studio':
                $count = count( $this->markers->get_markers() );

                return [
                    'in_use' => $count > 0,
                    /* translators: %d: number of custom markers */
                    'reason' => sprintf( _n( '%d custom marker in use', '%d custom markers in use', $count, 'wp-store-locator' ), $count ),
                ];
            case 'map_shapes':
                $collection = $this->shapes->get_collection();
                $count      = isset( $collection['features'] ) ? count( $collection['features'] ) : 0;

                return [
                    'in_use' => $count > 0,
                    /* translators: %d: number of map shapes */
                    'reason' => sprintf( _n( '%d shape in use', '%d shapes in use', $count, 'wp-store-locator' ), $count ),
                ];
            case 'fields-manager':
                $editor = $this->settings->get_group( 'editor' );

                return [
                    'in_use' => ! empty( $editor['field_manager']['fields'] ),
                    'reason' => esc_html__( 'custom fields configured', 'wp-store-locator' ),
                ];
        }

        return [ 'in_use' => false, 'reason' => '' ];
    }

    /**
     * @since  3.0.0
     * @param  string $feature One of the features() keys.
     * @return bool
     */
    public function is_hidden( $feature ) {
        if ( ! $this->is_enabled() || ! array_key_exists( $feature, $this->features() ) ) {
            return false;
        }

        $usage = $this->usage( $feature );

        return ! $usage['in_use'];
    }

    /**
     * What simple mode is doing right now, for the Home card to print.
     *
     * @since  3.0.0
     * @return array [ 'enabled' => bool, 'hidden' => array, 'kept' => array ]
     */
    public function get_status() {
        $status = [
            'enabled' => $this->is_enabled(),
            'hidden'  => [],
            'kept'    => [],
        ];

        if ( ! $status['enabled'] ) {
            return $status;
        }

        foreach ( $this->features() as $feature => $label ) {
            $usage = $this->usage( $feature );

            if ( $usage['in_use'] ) {
                $status['kept'][] = [
                    'feature' => $feature,
                    'label'   => $label,
                    'reason'  => $usage['reason'],
                ];
            } else {
                $status['hidden'][] = [
                    'feature' => $feature,
                    'label'   => $label,
                ];
            }
        }

        return $status;
    }

    /**
     * @since  3.0.0
     * @param  string $classes Space-separated body classes.
     * @return string
     */
    public function body_class( $classes ) {
        if ( $this->is_enabled() ) {
            $classes .= ' wpsl-simple-mode';
        }

        return $classes;
    }

    /**
     * What the header's easy mode checkbox needs to render itself.
     *
     * @since  3.0.0
     * @return array [ 'action' => string, 'checked' => bool, 'label' => string,
     *               'tooltip' => string ]
     */
    public function get_mode_control() {
        $enabled = $this->is_enabled();

        return [
            'options' => [
                [
                    'mode'   => 'easy',
                    'label'  => esc_html__( 'Easy mode', 'wp-store-locator' ),
                    'active' => $enabled,
                    'url'    => wp_nonce_url( add_query_arg( [ 'wpsl-mode' => 'easy' ] ), self::ACTION ),
                ],
                [
                    'mode'   => 'advanced',
                    'label'  => esc_html__( 'Advanced mode', 'wp-store-locator' ),
                    'active' => ! $enabled,
                    'url'    => wp_nonce_url( add_query_arg( [ 'wpsl-mode' => 'advanced' ] ), self::ACTION ),
                ],
            ],
            'tooltip' => $this->tooltip(),
        ];
    }

    /**
     * What easy mode would do to THIS site, not what it does in general.
     *
     * A site using custom markers or shapes keeps those pages in either mode,
     * which without saying so reads as the switch not working. So the sentence
     * names what stays and why.
     *
     * @since  3.0.0
     * @return string
     */
    private function tooltip() {
        $text = esc_html__( 'Easy mode hides the parts of the plugin this site does not use, and a few advanced options inside the settings. Nothing is deleted, and anything already in use stays visible. Advanced mode shows everything.', 'wp-store-locator' );

        $kept = [];

        foreach ( $this->features() as $feature => $label ) {
            $usage = $this->usage( $feature );

            if ( $usage['in_use'] ) {
                $kept[] = $label . ' ( ' . $usage['reason'] . ' )';
            }
        }

        if ( $kept ) {
            /* translators: %s: comma separated feature names with the reason each stays */
            $text .= ' ' . sprintf( esc_html__( 'On this site that means %s stay visible either way.', 'wp-store-locator' ), implode( ', ', $kept ) );
        }

        return $text;
    }

    /**
     * Act on the header link.
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_toggle() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is verified immediately below; this only decides whether to look.
        if ( ! isset( $_GET['wpsl-mode'] ) ) {
            return;
        }

        if ( ! isset( $_GET['_wpnonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ACTION )
        ) {
            wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
        update_option( self::OPTION, 'easy' === sanitize_key( wp_unslash( $_GET['wpsl-mode'] ) ), true );

        wp_safe_redirect( remove_query_arg( [ 'wpsl-mode', '_wpnonce', '_wp_http_referer' ] ) );
        exit;
    }
}