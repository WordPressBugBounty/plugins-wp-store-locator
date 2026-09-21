<?php
/**
 * Neutralizes active pre-2.0 WP Store Locator add-ons on 3.0.
 *
 * The 1.x add-ons pass their own compatibility guards on 3.0, then hit
 * removed 2.x APIs. This class stops each failure point without
 * deactivating the add-on, so licensing/updater keeps working and a
 * one-click update to 2.0 self-heals the site:
 *
 *  - Search Widget : unregister the widget + remove its shortcode ( the only
 *                    visitor-facing fatal ). Detected via the add-on's own
 *                    version constant so front-end requests stay cheap.
 *  - Statistics    : hide its admin menu page ( admin only ).
 *  - CSV Manager   : hide its admin menu page and load the legacy WPSL_Geocode
 *                    stub so the add-on's load-time instantiation can't fatal.
 *  - All           : alias WPSL_License_Manager back into existence so the
 *                    add-on updaters register ( see alias_license_manager ),
 *                    plus a red warning row under each add-on and a notice.
 *
 * @package WP_Store_Locator
 * @since   3.0.0
 */

namespace WPSL\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Legacy_Addons {

    /**
     * Where the 2.x add-on downloads live.
     *
     * The fallback for anyone the one-click update can't reach: updates are
     * license-gated, so an add-on without an active key never gets an update
     * row and has to be replaced with a manual download.
     *
     * @var string
     */
    const ACCOUNT_URL = 'https://wpstorelocator.co/account/';

    /**
     * The menu the add-on settings pages hang off.
     *
     * @var string
     */
    const MENU_PARENT = 'edit.php?post_type=wpsl_stores';

    /**
     * Admin pages registered by the pre-2.0 add-ons.
     *
     * Keyed by the add-on's plugin folder, so an entry only applies when that
     * add-on is actually installed and incompatible.
     *
     * @var array
     */
    const LEGACY_PAGES = [
        'wp-store-locator-statistics' => 'wpsl_statistics',
        'wp-store-locator-csv'        => 'wpsl_csv',
    ];

    /**
     * Incompatible add-ons found in the admin context.
     *
     * @var array List of [ 'file', 'name', 'version' ] entries.
     */
    private $incompatible = [];

    /**
     * Wire up the neutralization.
     *
     * Instantiated on plugins_loaded:6 — after core ( :5 ), before the add-ons
     * ( :10 ) — so the geocode stub is defined before CSV Manager loads.
     *
     * @since 3.0.0
     */
    public function __construct() {
        // Front-end + admin, and cheap: the widget is neutralized from its own
        // version constant, so no plugin scan runs on front-end page views.
        add_action( 'widgets_init', [ $this, 'maybe_disable_widget' ], 99 );
        add_action( 'init',         [ $this, 'maybe_disable_widget_shortcode' ], 99 );

        // Everything else needs the plugin list. The warnings only matter in
        // wp-admin, but the license alias also has to exist on cron and WP-CLI,
        // where the update transient gets rebuilt.
        if ( is_admin() || \wpsl_doing_background_update_check() ) {
            $this->setup_addon_compat();
        }
    }

    /**
     * Compatibility shims for incompatible add-ons, plus the admin warnings.
     *
     * @since  3.0.0
     * @return void
     */
    private function setup_addon_compat() {
        $this->incompatible = \wpsl_get_incompatible_addons();

        if ( empty( $this->incompatible ) ) {
            return;
        }

        // Has to happen before the add-ons initialize at :10, in every context.
        // A 1.x add-on registers its updater only if WPSL_License_Manager exists
        // by then, so without the alias it drops out of cron update checks.
        $this->alias_license_manager();

        // The rest is admin-only. The add-on gates its own admin includes on
        // is_admin(), so nothing outside wp-admin can reach WPSL_Geocode.
        if ( ! is_admin() ) {
            return;
        }

        // Define WPSL_Geocode now ( before CSV Manager loads at :10 ) so its
        // Import constructor can't fatal on `new WPSL_Geocode()` / require_once.
        // The stub is the last file left in the pre-3.0 admin/ directory and has
        // to stay at that exact path — CSV Manager 1.2.12 requires it by hand.
        if ( $this->addon_active( 'wp-store-locator-csv' ) ) {
            require_once WPSL_PLUGIN_DIR . 'admin/class-geocode.php';
        }

        add_action( 'admin_menu',    [ $this, 'hide_menus' ], 99 );
        add_action( 'admin_head',    [ $this, 'plugin_row_style' ] );

        // Two hooks, one notice: WordPress' own for every other screen, and
        // WPSL's for its settings-style pages, where admin_notice() holds back
        // so Display_Page::admin_header() can print it inside the notice wrap
        // instead of above the logo bar.
        add_action( 'admin_notices',      [ $this, 'admin_notice' ] );
        add_action( 'wpsl_admin_notices', [ $this, 'admin_notice' ] );

        // Only the dashboard copy can be dismissed, see admin_notice().
        add_action( 'wp_ajax_wpsl_dismiss_addon_notice', [ $this, 'dismiss_notice' ] );

        // Only the Plugins screen updates add-ons in place, so that is the only
        // screen where this markup can go stale without a reload.
        add_action( 'admin_footer-plugins.php', [ $this, 'plugin_row_script' ] );

        foreach ( $this->incompatible as $addon ) {
            add_action( "after_plugin_row_{$addon['file']}", [ $this, 'plugin_row_notice' ], 10, 2 );
        }
    }

    /**
     * Expose the license manager under its pre-3.0 global class name.
     *
     * Every 1.x add-on gates its updater on class_exists( 'WPSL_License_Manager' ),
     * a class 3.0 moved into the WPSL\Admin\Tools namespace. Without the alias that
     * guard fails silently: no EDD updater registers, the Plugins screen never
     * offers the 2.0 release, and the add-on can only be replaced by hand.
     *
     * Aliased only when a legacy add-on is actually present, so the v2 global
     * doesn't leak back into a clean 3.0 install.
     *
     * @since  3.0.0
     * @return void
     */
    private function alias_license_manager() {
        // Autoload off: nothing can autoload the old global name, so a hit here
        // means something else already declared it and the alias would warn.
        if ( class_exists( 'WPSL_License_Manager', false ) ) {
            return;
        }

        if ( ! class_exists( '\WPSL\Admin\Tools\License_Manager' ) ) {
            return;
        }

        class_alias( '\WPSL\Admin\Tools\License_Manager', 'WPSL_License_Manager' );
    }

    /**
     * Is a pre-2.0 add-on from the given plugin folder active?
     *
     * @since  3.0.0
     * @param  string $slug_dir The add-on's plugin folder ( e.g. wp-store-locator-csv ).
     * @return bool
     */
    private function addon_active( $slug_dir ) {
        return null !== $this->find_addon( $slug_dir );
    }

    /**
     * Return the incompatible add-on installed in the given plugin folder.
     *
     * @since 3.0.0
     * @param  string     $slug_dir The add-on's plugin folder ( e.g. wp-store-locator-csv ).
     * @return array|null           The [ 'file', 'name', 'version' ] entry, or null.
     */
    private function find_addon( $slug_dir ) {
        foreach ( $this->incompatible as $addon ) {
            if ( strpos( $addon['file'], $slug_dir . '/' ) === 0 ) {
                return $addon;
            }
        }

        return null;
    }

    /**
     * Strip the shared "WP Store Locator - " prefix off an add-on name.
     *
     * Every message that names an add-on already establishes the plugin it
     * belongs to, so the prefix is noise.
     *
     * @since 3.0.0
     * @param  string $name The add-on's full plugin name.
     * @return string
     */
    private function short_name( $name ) {
        $prefix = 'WP Store Locator - ';

        return strpos( $name, $prefix ) === 0 ? substr( $name, strlen( $prefix ) ) : $name;
    }

    /**
     * Whether an active Search Widget add-on is below 2.0.
     *
     * Uses the add-on's own constant ( defined when it loads at :10 ), so this
     * works on the front-end without scanning installed plugins.
     *
     * @since  3.0.0
     * @return bool
     */
    private function widget_is_legacy() {
        return defined( 'WPSL_WIDGET_VERSION_NUM' ) && version_compare( WPSL_WIDGET_VERSION_NUM, '2.0', '<' );
    }

    /**
     * Unregister the legacy search widget so it can never render ( front-end fatal ).
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_disable_widget() {
        if ( $this->widget_is_legacy() ) {
            unregister_widget( 'WPSL_Search_Widget' );
        }
    }

    /**
     * Remove the legacy [wpsl_widget] shortcode so it can't render the widget.
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_disable_widget_shortcode() {
        if ( $this->widget_is_legacy() ) {
            remove_shortcode( 'wpsl_widget' );
        }
    }

    /**
     * Hide the Statistics / CSV Manager admin menu pages, and block them.
     *
     * remove_submenu_page() only unsets the entry from the $submenu global —
     * it never touches $_registered_pages, which is what
     * user_can_access_admin_page() gates on. So hiding the link leaves the page
     * reachable by direct URL ( a bookmark, browser history, a link in the
     * docs ), where the 1.x admin code would run against the removed 2.x APIs.
     * Intercepting 'load-{$hookname}' stops the render before that happens.
     *
     * @since  3.0.0
     * @return void
     */
    public function hide_menus() {
        foreach ( self::LEGACY_PAGES as $slug_dir => $page_slug ) {
            $addon = $this->find_addon( $slug_dir );

            if ( null === $addon ) {
                continue;
            }

            remove_submenu_page( self::MENU_PARENT, $page_slug );

            /*
             * Passing the parent explicitly keeps get_admin_page_parent() on
             * its early return, so this resolves to the same hookname the
             * request routing computes even though $submenu no longer holds
             * the entry.
             */
            $hookname = get_plugin_page_hookname( $page_slug, self::MENU_PARENT );

            add_action( 'load-' . $hookname, function () use ( $addon ) {
                $this->block_legacy_page( $addon );
            } );
        }
    }

    /**
     * Stop a hidden legacy add-on page from rendering.
     *
     * @since 3.0.0
     * @param  array $addon The incompatible add-on's [ 'file', 'name', 'version' ] entry.
     * @return void
     */
    private function block_legacy_page( $addon ) {
        $name = $this->short_name( $addon['name'] );

        $message  = '<p>' . sprintf(
            /* translators: %s: add-on name. */
            wp_kses_post( __( '%s is not compatible with this version of WP Store Locator, so its features have been turned off.', 'wp-store-locator' ) ),
            '<strong>' . esc_html( $name ) . '</strong>'
        ) . '</p>';

        $message .= '<p>' . wp_kses_post( __( 'The plugin stays active so it can still receive updates. Update it to add-on version 2.0 or later to turn its features back on.', 'wp-store-locator' ) ) . '</p>';
        $message .= '<p>' . $this->manual_download_text() . '</p>';

        wp_die(
            $message, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
            esc_html__( 'Add-on needs an update', 'wp-store-locator' ),
            [
                'response'  => 403,
                'link_url'  => admin_url( 'plugins.php' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_die escapes the link_url itself
                'link_text' => esc_html__( 'Go to the Plugins screen', 'wp-store-locator' ),
            ]
        );
    }

    /**
     * Red warning row rendered directly under an incompatible add-on's plugin row.
     *
     * The data-plugin attribute lets plugin_row_script() drop this row once the
     * add-on has been updated in place, without waiting for a page reload.
     *
     * @since  3.0.0
     * @param  string $plugin_file The add-on's plugin file.
     * @param  array  $plugin_data The add-on's plugin data.
     * @return void
     */
    public function plugin_row_notice( $plugin_file, $plugin_data ) {
        $colspan = wp_is_auto_update_enabled_for_type( 'plugin' ) ? 2 : 1;
        $name    = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : esc_html__( 'This add-on', 'wp-store-locator' );

        printf(
            '<tr class="active wpsl-incompatible-addon-row" data-plugin="%1$s"><th class="check-column"><span class="dashicons dashicons-warning" aria-hidden="true"></span></th><td class="column-primary">%2$s</td><td class="column-description" colspan="%3$s"><p>%4$s</p><p>%5$s</p><p>%6$s</p></td></tr>',
            esc_attr( $plugin_file ),
            esc_html__( 'Needs update', 'wp-store-locator' ),
            esc_attr( $colspan ),
            sprintf(
                /* translators: %s: add-on name. */
                wp_kses_post( __( '%s is not compatible with this version of WP Store Locator, so its features have been turned off.', 'wp-store-locator' ) ),
                '<strong>' . esc_html( $name ) . '</strong>'
            ),
            wp_kses_post( __( 'The plugin stays active so it can still receive updates. Update it to add-on version 2.0 or later to turn its features back on.', 'wp-store-locator' ) ),
            $this->manual_download_text() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output
        );
    }

    /**
     * The "no update showing?" fallback, pointing at the account page.
     *
     * Shared by the plugin row and the blocked-page notice so both stay in
     * step and translators only see the string once.
     *
     * @since 3.0.0
     * @return string
     */
    private function manual_download_text() {
        return sprintf(
            /* translators: 1: opening link tag to the wpstorelocator.co account page, 2: closing link tag. */
            wp_kses_post( __( 'No update showing? Download it from your %1$saccount page%2$s.', 'wp-store-locator' ) ),
            '<a target="_blank" href="' . esc_url( self::ACCOUNT_URL ) . '">',
            '</a>'
        );
    }

    /**
     * Row styling ( red tint + border ), matching the WordPress "not fully active" convention.
     *
     * @since  3.0.0
     * @return void
     */
    public function plugin_row_style() {
        echo '<style id="wpsl-incompatible-addons">
            .plugins .wpsl-incompatible-addon-row th,
            .plugins .wpsl-incompatible-addon-row td { background: #fff5f5; }
            .plugins .wpsl-incompatible-addon-row th.check-column { border-left: 4px solid #d63638 !important; }
            .plugins .wpsl-incompatible-addon-row th.check-column .dashicons-warning { margin: 2px 0 0 8px; color: #d63638; }
            .plugins .wpsl-incompatible-addon-row .column-description p { margin: 0; }
            .plugins .wpsl-incompatible-addon-row .column-description p + p { margin-top: 4px; }
        </style>';
    }

    /**
     * Clear the warning markup for an add-on that was just updated in place.
     *
     * @since  3.0.0
     * @return void
     */
    public function plugin_row_script() {
        ?>
        <script id="wpsl-incompatible-addons-js">
        ( function ( $ ) {
            function wpslClearAddonWarning( event, response ) {

                if ( ! response || ! response.plugin ) {
                    return;
                }

                // Matched in JS rather than through a built selector, so a plugin
                // path can never be interpolated into one.
                $( 'tr.wpsl-incompatible-addon-row' ).filter( function () {
                    return $( this ).attr( 'data-plugin' ) === response.plugin;
                } ).remove();
            }

            $( document ).on( 'wp-plugin-update-success', wpslClearAddonWarning );
            $( document ).on( 'wp-plugin-bulk-update-success', wpslClearAddonWarning );
        } )( jQuery );
        </script>
        <?php
    }

    /**
     * Option holding the dismissed state of the dashboard notice.
     *
     * @var string
     */
    const DISMISSED_OPTION = 'wpsl_addon_notice_dismissed';

    /**
     * Fingerprint of the add-ons the warning currently covers.
     *
     * Dismissal is stored against this rather than as a plain flag, so that
     * installing another pre-2.0 add-on, or updating one of several, brings the
     * notice back instead of staying silenced against a set that has changed.
     *
     * @since  3.0.0
     * @return string
     */
    private function addon_fingerprint() {
        $parts = [];

        foreach ( $this->incompatible as $addon ) {
            $parts[] = $addon['file'] . '@' . $addon['version'];
        }

        sort( $parts );

        return md5( implode( '|', $parts ) );
    }

    /**
     * Has the dashboard notice been dismissed for this set of add-ons?
     *
     * @since  3.0.0
     * @return bool
     */
    private function notice_dismissed() {
        return get_option( self::DISMISSED_OPTION ) === $this->addon_fingerprint();
    }

    /**
     * Is this the closing step of the setup wizard?
     *
     * The one screen the notice can't be closed on: the wizard prints it once,
     * as the hand-over at the end of setup, and there is no next page there to
     * see it again on.
     *
     * @since  3.0.0
     * @return bool
     */
    private function on_onboarding() {
        global $pagenow;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
        return 'index.php' === $pagenow && isset( $_GET['page'] ) && 'wpsl-onboarding' === sanitize_text_field( wp_unslash( $_GET['page'] ) );
    }

    /**
     * Record that the notice was dismissed.
     *
     * @since  3.0.0
     * @return void
     */
    public function dismiss_notice() {
        check_ajax_referer( 'wpsl_dismiss_addon_notice', 'nonce' );

        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-store-locator' ) ] );
        }

        update_option( self::DISMISSED_OPTION, $this->addon_fingerprint(), false );

        wp_send_json_success();
    }

    /**
     * Is this a screen the add-on warning belongs on?
     *
     * @since  3.0.0
     * @return bool
     */
    private function notice_belongs_here() {
        global $pagenow;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
        if ( isset( $_GET['post_type'] ) && 'wpsl_stores' === sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ) {
            if ( function_exists( 'wpsl_admin_page_hides_notices' ) && wpsl_admin_page_hides_notices() ) {
                return false;
            }

            return ! $this->notice_dismissed();
        }

        if ( 'index.php' === $pagenow ) {
            if ( ! isset( $_GET['page'] ) ) {
                return ! $this->notice_dismissed();
            }

            /*
             * The setup wizard also lives under index.php. It decides for
             * itself when to fire 'wpsl_admin_notices' ( last step only ), so
             * allowing it here just lets that through; every other
             * index.php?page= screen belongs to somebody else.
             */
            return 'wpsl-onboarding' === sanitize_text_field( wp_unslash( $_GET['page'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return false;
    }

    /**
     * Persistent notice summarizing the disabled add-ons and the fix.
     *
     * @since  3.0.0
     * @return void
     */
    public function admin_notice() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        /*
         * On WPSL's own pages this same callback runs again on
         * 'wpsl_admin_notices', fired from inside #wpsl-notice-wrap. Bail out of
         * the WordPress hook there, or the warning renders twice.
         */
        if ( 'admin_notices' === current_action()
            && function_exists( 'wpsl_admin_page_renders_own_notices' )
            && wpsl_admin_page_renders_own_notices() ) {
            return;
        }

        if ( ! $this->notice_belongs_here() ) {
            return;
        }

        $dismissible = ! $this->on_onboarding();
        ?>
        <div id="wpsl-addon-notice" class="notice notice-error wpsl-addon-notice<?php echo $dismissible ? ' is-dismissible' : ''; ?>">
            <h3><?php esc_html_e( 'Action required: update your add-ons', 'wp-store-locator' ); ?></h3>
            <p><?php esc_html_e( 'Your current add-on versions are not compatible with WP Store Locator 3 — please update them to the latest add-on release.', 'wp-store-locator' ); ?></p>
            <p><a href="<?php echo esc_url( wpsl_release_post_url( '#add-on' ) ); ?>"><?php esc_html_e( 'Read what this means for your add-ons', 'wp-store-locator' ); ?></a></p>
        </div>
        <?php

        if ( ! $dismissible ) {
            return;
        }

        /*
         * WordPress adds the close button and hides the notice on click, but
         * remembers nothing, so it would be back on the next page load. Record
         * it instead. Delegated, because that button does not exist yet when
         * this runs.
         */
        ?>
        <script id="wpsl-addon-notice-js">
        jQuery( document ).on( 'click', '#wpsl-addon-notice .notice-dismiss', function () {
            jQuery.post( ajaxurl, {
                action: 'wpsl_dismiss_addon_notice',
                nonce: '<?php echo esc_js( wp_create_nonce( 'wpsl_dismiss_addon_notice' ) ); ?>'
            } );
        } );
        </script>
        <?php
    }
}