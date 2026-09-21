<?php
namespace WPSL\Admin\Core;

/**
 * Create WPSL styled pages.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Display_Page {

    /**
     * Current page name.
     *
     * @since 3.0.0
     */
    public $page;

    /**
     * Active tab name.
     *
     * @since 3.0.0
     */
    public $tab;

    /**
     * Add-on licenses.
     *
     * @since 3.0.0
     */
    public $licenses;

    /**
     * Class constructor
     *
     * @since 3.0.0
     */
    public function __construct() {}

    /**
     * Create a new WPSL styled page.
     *
     * @since 3.0.0
     * @param array $args Optional arguments {
     *     @type bool $skip_header Whether to skip rendering the admin header. Default false.
     * }
     * @return void
     */
    public function create( $args = [] ) {
        $defaults = [
            'skip_header' => false
        ];
        
        $args = wp_parse_args( $args, $defaults );

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
        if ( $this->is_wpsl_admin_page() ) {
            $this->tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
            $this->page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            // Skip header if requested
            if ( ! $args['skip_header'] ) {
                $this->admin_header();
            }
            
            $this->content();
        }
    }

    /**
     * Whether this request is for one of our admin screens.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_wpsl_admin_page() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( ! $page || strpos( $page, 'wpsl' ) !== 0 ) {
            return false;
        }

        if ( isset( $_GET['post_type'] ) && 'wpsl_stores' !== sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) ) {
            return false;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return true;
    }

    /**
     * Create the appearance page with header skipped.
     *
     * @since 3.0.0
     * @return void
     */
    public function create_appearance_page() {
        $this->create( [ 'skip_header' => true ] );
    }

    /**
     * Create the Marker Studio page with the standard WPSL admin header.
     *
     * @since 3.0.0
     * @return void
     */
    public function create_marker_studio_page() {
        $this->create();
    }

    /**
     * Create the Map Shapes page with the standard WPSL admin header.
     *
     * @since 3.0.0
     * @return void
     */
    public function create_map_shapes_page() {
        $this->create();
    }

    /**
     * Create the admin header in WPSL style.
     *
     * @since 3.0.0
     * @return void
     */
    public function admin_header() {
        ?>
        <div id="wpsl-header">
            <div class="wpsl-nav-wrap">
                <img src="<?php echo esc_url( plugins_url( 'assets/img/admin/logo.png', WPSL_PLUGIN_FILE ) ) ?>" alt="WP Store Locator">
                <?php
                $this->create_nav();

                if ( 'wpsl_home' === $this->page ) {
                    $mode = wpsl_get_service( 'simple_mode' )->get_mode_control();
                ?>
                <div class="wpsl-mode-switch">
                    <?php
                    // Plain links, not a control: the active mode is
                    // underlined, the other is one click away.
                    foreach ( $mode['options'] as $option ) {

                        if ( $option['active'] ) {
                            echo '<span class="wpsl-mode-option wpsl-mode-active" aria-current="true">' . esc_html( $option['label'] ) . '</span>';
                            continue;
                        }
                        ?>
                        <a class="wpsl-mode-option" href="<?php echo esc_url( $option['url'] ); ?>"><?php echo esc_html( $option['label'] ); ?></a>
                        <?php
                    }
                    ?>
                    <span class="wpsl-info" tabindex="0">
                        <span class="wpsl-info-text wpsl-tooltip-below"><?php echo esc_html( $mode['tooltip'] ); ?></span>
                    </span>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php
        if ( function_exists( 'wpsl_get_service' ) && ! wpsl_admin_page_hides_notices() ) {
            echo '<div id="wpsl-notice-wrap">';

            /**
             * Print notices that would otherwise land above the WPSL header.
             *
             * @since 3.0.0
             */
            do_action( 'wpsl_admin_notices' );

            wpsl_get_service( 'notices' )->show_for_addon_page();
            echo '</div>';
        }
    }

    /**
     * Load the main content based on
     * the active tab / page.
     *
     * @since  3.0.0
     * @return void
     */
    public function content() {
        $default_tab = $this->get_default_tab();
        $allowed = [ 'home', 'settings', 'appearance', 'marker_studio', 'map_shapes', 'import-export-settings', 'licenses', 'system-status', 'addons', 'whats-new' ];

        if ( ! $this->tab ) {
            $template = $this->page;

            if ( strpos( $template, 'wpsl_' ) !== false ) {
                $template = str_replace('wpsl_', '', $template );
            }
        } else {
            $template = $this->tab;
        }

        if ( in_array( $template, $allowed ) ) {
            // Load appearance template from new appearance folder
            if ( $template === 'appearance' ) {
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/appearance-page.php' );
            } elseif ( $template === 'marker_studio' ) {
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/marker-studio/templates/marker-studio-page.php' );
            } elseif ( $template === 'map_shapes' ) {
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/map-shapes/templates/map-shapes-page.php' );
            } else {
                require_once( WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/' . $template . '.php' );
            }
        }
    }

    /**
     * Get the default active tab for each page.
     *
     * @since   3.0.0
     * @return  string $default
     */
    public function get_default_tab() {
        $default = '';
        $tabs = apply_filters( 'wpsl_default_page_tabs', [
            'wpsl_tools'    => 'system-status',
            'wpsl_settings' => 'settings'
        ] );

        if ( isset( $tabs[ $this->page ] ) ) {
            $default = $tabs[ $this->page ];
        }

        return $default;
    }

    /**
     * Create the navigation tabs.
     *
     * @since  3.0.0
     * @return void
     */
    public function create_nav() {
        $tabs = [];
        $default_tab = $this->get_default_tab();

        if ( $this->page ) {
            $license_notice = false;

            switch ( $this->page ) {
                case 'wpsl_settings':
                    $tabs = apply_filters( 'wpsl_settings_tab', [
                        'settings' => esc_html__( 'Settings', 'wp-store-locator' )
                    ] );

                    // Lazy-load licenses to ensure add-ons have registered via wpsl_license_settings filter
                    if ( ! isset( $this->licenses ) ) {
                        $this->licenses = apply_filters( 'wpsl_license_settings', [] );
                    }

                    if ( $this->licenses ) {
                        $tabs['licenses'] = esc_html__( 'Licenses', 'wp-store-locator' );

                        /**
                         * See if there's a license key that's not valid, or one
                         * whose support has run out. If so, we show a notice icon
                         * in the license tab.
                         */
                        foreach ( $this->licenses  as $k => $license ) {
                            if ( ! is_array( $license ) ) {
                                continue;
                            }

                            $support_expired = isset( $license['support'] ) && 'expired' === $license['support'];
                            
                            if ( $license['status'] !== 'valid' || $support_expired ) {
                                $license_notice = true;

                                break;
                            }
                        }
                    }

                    /**
                     * Default to the 'settings' tab
                     * if an unknow tab value is set.
                     */
                    if ( ! array_key_exists( $this->tab, $tabs ) ) {
                        $this->tab = 'settings';
                    }

                    break;
                case 'wpsl_tools':
                    $tabs = apply_filters( 'wpsl_tools_tab', [
                        'system-status'          => esc_html__( 'System Status', 'wp-store-locator' ),
                        'import-export-settings' => esc_html__( 'Import / Export Settings', 'wp-store-locator' )
                    ] );

                    /**
                     * Default to the 'system-status' tab
                     * if an unknow tab value is set.
                     */
                    if ( ! array_key_exists( $this->tab, $tabs ) ) {
                        $this->tab = 'system-status';
                    }

                    break;
            }
        }

        if ( count( $tabs ) > 1 ) {
            foreach ( $tabs as $tab_key => $tab_name ) {
                $tab_warning = '';

                if ( ( ! $this->tab && $tab_key == $default_tab ) || $this->tab == $tab_key ) {
                    $active_tab = 'wpsl-active-tab';
                } else {
                    $active_tab = '';
                }

                if ( $tab_key == 'licenses' && $license_notice ) {
                    $tab_warning = '<span class="wpsl-info"></span>';
                }

                $short_text = $this->get_short_tab_text( $tab_key, $tab_name );
                
                echo '<a class="' . esc_attr( $active_tab ) . '" title="' . esc_attr( $tab_name ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=' . $this->page . '&tab=' . $tab_key ) ) . '">';
                echo '<span class="wpsl-tab-full">' . esc_attr( $tab_name ) . '</span>';
                echo '<span class="wpsl-tab-short">' . esc_attr( $short_text ) . '</span>';
                echo ' ' . wp_kses_post( $tab_warning ) . '</a>';
            }
        }
    }

    /**
     * Get short text version for tab names on small viewports.
     *
     * @since  3.0.0
     * @param  string $tab_key  The tab key identifier
     * @param  string $tab_name The full tab name
     * @return string The shortened tab name
     */
    public function get_short_tab_text( $tab_key, $tab_name ) {
        $short_names = apply_filters( 'wpsl_short_tab_names', [
            'system-status'          => esc_html__( 'System', 'wp-store-locator' ),
            'import-export-settings' => esc_html__( 'Import / Export', 'wp-store-locator' )
        ] );

        return isset( $short_names[ $tab_key ] ) ? $short_names[ $tab_key ] : $tab_name;
    }

    /**
     * Create a WPSL-styled admin page for add-ons.
     * 
     * This method allows add-ons to create admin pages with the same
     * header and navigation styling as the main WPSL plugin.
     *
     * @since 3.0.0
     * @param array $args {
     *     Configuration for the admin page.
     *     
     *     @type string   $page_slug          The page slug (e.g., 'wpsl_statistics')
     *     @type array    $tabs               Array of tabs with key => label pairs
     *     @type callable $content_callback   Callback function to render content for each tab
     *     @type string   $default_tab        Default tab to show (optional)
     *     @type array    $short_tab_names    Short names for responsive display (optional)
     *     @type callable $nav_extras_callback Callback to render extra HTML inside the nav wrap. Receives $current_tab as argument. (optional)
     *     @type string   $layout              Layout mode: 'wide' (full width) or 'default' (narrow, matching main WPSL settings width). Default 'wide'.
     *     @type array    $tab_classes          Optional CSS classes for individual tabs. Array of tab_key => class string pairs (e.g., 'dashboard' => 'icon-dashboard').
     * }
     * @return void
     */
    public function create_addon_page( $args ) {
        $defaults = [
            'page_slug'           => '',
            'tabs'                => [],
            'content_callback'    => null,
            'default_tab'         => '',
            'short_tab_names'     => [],
            'nav_extras_callback' => null,
            'layout'              => 'wide',
            'tab_classes'         => []
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        // Validate required parameters
        if ( empty( $args['page_slug'] ) || empty( $args['tabs'] ) || ! is_callable( $args['content_callback'] ) ) {
            return;
        }
        
        // Get current page and tab
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only page routing, nothing is processed or stored.
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $current_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Only render if we're on the correct page
        if ( $current_page !== $args['page_slug'] ) {
            return;
        }
        
        // Set default tab if none specified
        if ( empty( $current_tab ) && ! empty( $args['default_tab'] ) ) {
            $current_tab = $args['default_tab'];
        } elseif ( empty( $current_tab ) ) {
            $current_tab = key( $args['tabs'] );
        }
        
        // Validate current tab exists
        if ( ! array_key_exists( $current_tab, $args['tabs'] ) ) {
            $current_tab = ! empty( $args['default_tab'] ) ? $args['default_tab'] : key( $args['tabs'] );
        }
        
        // Render WPSL header
        $this->render_addon_header( $args['page_slug'], $current_tab, $args['tabs'], $args['short_tab_names'], $args['nav_extras_callback'], $args['layout'], $args['tab_classes'] );
        
        // Wrap content in #wpsl-content-wrap for 'default' (narrow) layout
        if ( $args['layout'] === 'default' ) {
            echo '<div id="wpsl-content-wrap">';
            // Output deferred notices inside #wpsl-content-wrap so they are not
            // direct children of #wpbody-content (which the WPSL CSS hides on these pages).
            if ( function_exists( 'wpsl_get_service' ) ) {
                wpsl_get_service( 'notices' )->show_for_addon_page();
            }
        }

        // Call the content callback with the current tab
        call_user_func( $args['content_callback'], $current_tab );

        if ( $args['layout'] === 'default' ) {
            echo '</div>';
        }
    }

    /**
     * Render WPSL-styled admin header with navigation tabs for add-ons.
     * 
     * @since 3.0.0
     * @param string   $page_slug           The page slug
     * @param string   $current_tab         The currently active tab
     * @param array    $tabs                Array of tabs with key => label pairs
     * @param array    $short_tab_names     Optional short names for responsive display
     * @param callable $nav_extras_callback Optional callback to render extra content inside the nav wrap
     * @param string   $layout              Layout mode: 'wide' or 'default'. Default 'wide'.
     * @param array    $tab_classes         Optional CSS classes for individual tabs.
     * @return void
     */
    public function render_addon_header( $page_slug, $current_tab, $tabs, $short_tab_names = [], $nav_extras_callback = null, $layout = 'wide', $tab_classes = [] ) {
        $header_class = ( $layout === 'wide' ) ? ' wpsl-wide-layout' : '';
        ?>
        <div id="wpsl-header" class="<?php echo esc_attr( $header_class ); ?>">
            <div class="wpsl-nav-wrap">
                <img src="<?php echo esc_url( plugins_url( 'assets/img/admin/logo.png', WPSL_PLUGIN_FILE ) ); ?>" alt="WP Store Locator">
                <nav class="wpsl-nav-tabs">
                    <?php $this->render_addon_tabs( $page_slug, $current_tab, $tabs, $short_tab_names, $tab_classes ); ?>
                </nav>
                <?php
                if ( is_callable( $nav_extras_callback ) ) {
                    call_user_func( $nav_extras_callback, $current_tab );
                }
                ?>
            </div>
        </div>
        <?php do_action( 'all_admin_notices' ); ?>
        <?php
    }

    /**
     * Render navigation tabs for add-on admin pages.
     * 
     * @since 3.0.0
     * @param string $page_slug       The page slug
     * @param string $current_tab     The currently active tab
     * @param array  $tabs            Array of tabs with key => label pairs
     * @param array  $short_tab_names Optional short names for responsive display
     * @param array  $tab_classes     Optional CSS classes for individual tabs
     * @return void
     */
    public function render_addon_tabs( $page_slug, $current_tab, $tabs, $short_tab_names = [], $tab_classes = [] ) {
        if ( count( $tabs ) > 1 ) {
            foreach ( $tabs as $tab_key => $tab_name ) {
                
                $classes = ( $current_tab === $tab_key ) ? 'wpsl-active-tab' : '';

                if ( isset( $tab_classes[ $tab_key ] ) ) {
                    $classes .= ' ' . $tab_classes[ $tab_key ];
                }

                $classes = trim( $classes );
                $short_text   = isset( $short_tab_names[ $tab_key ] ) ? $short_tab_names[ $tab_key ] : $tab_name;
                
                echo '<a class="' . esc_attr( $classes ) . '" title="' . esc_attr( $tab_name ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=' . $page_slug . '&tab=' . $tab_key ) ) . '">';
                echo '<span class="wpsl-tab-full">' . esc_html( $tab_name ) . '</span>';
                echo '<span class="wpsl-tab-short">' . esc_html( $short_text ) . '</span>';
                echo '</a>';
            }
        }
    }
}