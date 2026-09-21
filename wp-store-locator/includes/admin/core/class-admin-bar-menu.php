<?php
namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin Bar Menu
 *
 * Adds WPSL menu items to the WordPress admin bar.
 *
 * @since 3.0.0
 */
class Admin_Bar_Menu {

    /**
     * Class constructor
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action( 'admin_bar_menu',        [ $this, 'add_admin_bar_menu' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_styles' ] );
    }

    /**
     * Get the count of active notifications
     *
     * @since  3.0.0
     * @return int Number of active notifications
     */
    private function get_notification_count() {
        $count = 0;
        
        /*
         * Count alerts ( plugin conflicts, API issues, etc. ).
         */
        if ( function_exists( 'wpsl_container' ) && wpsl_container()->has( 'plugin_alerts' ) ) {
            $count += wpsl_get_service( 'plugin_alerts' )->get_alert_count();
        }
        
        // Count temporary notices (if any exist)
        $notices = get_option( 'wpsl_notices' );
        
        if ( ! empty( $notices ) ) {
            if ( wpsl_is_multi_array( $notices ) ) {
                $count += count( $notices );
            } else {
                $count += 1;
            }
        }
        
        return $count;
    }

    /**
     * Enqueue admin bar menu styles
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_styles() {
        if ( ! is_admin_bar_showing() ) {
            return;
        }

        $mask = 'url( "' . wpsl_get_pin_icon_url() . '" ) no-repeat center / 16px';

        wp_add_inline_style( 'admin-bar', '
            #wpadminbar #wp-admin-bar-wpsl-menu .wpsl-icon-markers {
                display: inline-block;
                vertical-align: top;
                width: 16px;
                height: 100%;
                margin-right: 4px;
                background-color: currentColor;
                -webkit-mask: ' . $mask . ';
                mask: ' . $mask . ';
            }

            #wpadminbar #wp-admin-bar-wpsl-menu .wpsl-icon-markers:before {
                content: none;
            }

            #wpadminbar .wpsl-menu-notification-counter {
                display: inline-block;
                vertical-align: top;
                box-sizing: border-box;
                margin: 7px 0 -1px 5px;
                padding: 0 5px;
                min-width: 18px;
                height: 18px;
                border-radius: 9px;
                background-color: #d63638;
                color: #fff;
                font-size: 11px;
                line-height: 1.6;
                text-align: center;
                z-index: 26;
            }
            
            #wpadminbar .wpsl-menu-notification-indicator {
                display: inline-block;
                width: 8px;
                height: 8px;
                margin: -1px 0 0 5px;
                border-radius: 50%;
                background-color: #d63638;
                vertical-align: middle;
                animation: wpsl-pulse 2s ease-in-out infinite;
            }
            
            @keyframes wpsl-pulse {
                0%, 100% {
                    opacity: 1;
                    transform: scale(1);
                }
                50% {
                    opacity: 0.6;
                    transform: scale(1.1);
                }
            }
        ' );
    }

    /**
     * Add WPSL menu to the admin bar
     *
     * @param \WP_Admin_Bar $wp_admin_bar The WordPress admin bar object
     * @since  3.0.0
     * @return void
     */
    public function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return;
        }

        $show_admin_bar_menu = apply_filters( 'wpsl_admin_bar_menu', true );

        if ( ! $show_admin_bar_menu ) {
            return;
        }

        $notification_count = $this->get_notification_count();
        $notification_badge = '';
        
        if ( $notification_count > 0 ) {
            $notification_badge = ' <div class="wp-core-ui wp-ui-notification wpsl-menu-notification-counter">' . $notification_count . '</div>';
        }

        $wp_admin_bar->add_node( [
            'id'    => 'wpsl-menu',
            'title' => '<span class="wpsl-icon-markers"></span><span class="ab-label">' . __( 'WPSL', 'wp-store-locator' ) . '</span>' . $notification_badge,
            'href'  => admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_home' ),
            'meta'  => [
                'title' => __( 'WP Store Locator', 'wp-store-locator' ),
            ],
        ] );

        $notification_indicator = '';
        
        if ( $notification_count > 0 ) {
            $notification_indicator = ' <div class="wp-core-ui wp-ui-notification wpsl-menu-notification-indicator"></div>';
        }

        $wp_admin_bar->add_node( [
            'parent' => 'wpsl-menu',
            'id'     => 'wpsl-notifications',
            'title'  => __( 'Notifications', 'wp-store-locator' ) . $notification_indicator,
            'href'   => admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_home#wpsl-home-alerts' ),
        ] );

        $wp_admin_bar->add_node( [
            'parent' => 'wpsl-menu',
            'id'     => 'wpsl-settings',
            'title'  => __( 'Settings', 'wp-store-locator' ),
            'href'   => admin_url( 'admin.php?page=wpsl_settings' ),
        ] );

        $wp_admin_bar->add_node( [
            'parent' => 'wpsl-menu',
            'id'     => 'wpsl-all-stores',
            'title'  => __( 'All Stores', 'wp-store-locator' ),
            'href'   => admin_url( 'edit.php?post_type=wpsl_stores' ),
        ] );

        $wp_admin_bar->add_node( [
            'parent' => 'wpsl-menu',
            'id'     => 'wpsl-support',
            'title'  => __( 'Support', 'wp-store-locator' ),
            'href'   => 'https://wpstorelocator.co/support/',
            'meta'   => [
                'target' => '_blank',
            ],
        ] );
    }
}