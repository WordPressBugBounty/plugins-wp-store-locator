<?php
/**
 * Admin service loader
 *
 * Decides which deferred admin services and procedural includes a request
 * needs. Runs on init, before get_current_screen() exists, so it classifies
 * from $pagenow and the superglobals. 
 * 
 * Service registrations are unconditional; only constructors (hook registrations) 
 * are gated, so any admin service stays resolvable.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Service_Loader {

    /**
     * Map WPSL submenu slugs to a context id.
     *
     * @since 3.0.0
     * @var   array
     */
    private const PAGE_CONTEXTS = [
        'wpsl_home'          => 'home',
        'wpsl_settings'      => 'settings',
        'wpsl_appearance'    => 'appearance',
        'wpsl_map_shapes'    => 'map_shapes',
        'wpsl_marker_studio' => 'marker_studio',
        'wpsl_tools'         => 'tools',
        'wpsl_addons'        => 'addons',
        'wpsl_whats-new'     => 'whats_new',
        'wpsl-onboarding'    => 'onboarding',
    ];

    /**
     * Map an AJAX / admin-post action to the service(s) 
     * whose constructor registers its handler.
     *
     * Actions owned by always-global classes are absent ( those load on
     * every admin request ). wpsl_home_feedback is listed because its
     * handler regenerates the status report server-side.
     *
     * @since 3.0.0
     * @var   array
     */
    public const AJAX_SERVICES = [
        'wpsl_activate_template'        => [ 'appearance' ],
        'wpsl_save_appearance'          => [ 'appearance' ],
        'wpsl_field_manager'            => [ 'field_manager' ],
        'wpsl_section_editor'           => [ 'section_editor' ],
        'wpsl_marker_manager_save'      => [ 'custom_marker_ajax' ],
        'wpsl_marker_manager_delete'    => [ 'custom_marker_ajax' ],
        'wpsl_marker_manager_tag_logo'  => [ 'custom_marker_ajax' ],
        'wpsl_marker_manager_untag_logo' => [ 'custom_marker_ajax' ],
        'wpsl_save_map_shapes'          => [ 'map_shapes_admin' ],
        'wpsl_map_shapes_geocode'       => [ 'map_shapes_admin' ],
        'wpsl_flush_cache'              => [ 'cache_manager' ],
        'wpsl_validate_key'             => [ 'validate_keys' ],
        'wpsl_update_validation_status' => [ 'validate_keys' ],
        'wpsl_data_management'          => [ 'data_management' ],
        'wpsl_get_data_counts'          => [ 'data_management' ],
        'wpsl_convert_hours'            => [ 'hours_converter' ],
        'wpsl_geocode_locations'        => [ 'geocode_locations' ],
        'wpsl_create_locator_page'      => [ 'home' ],
        'wpsl_home_save_map_service'    => [ 'home', 'validate_keys' ],
        'wpsl_home_setup_status'        => [ 'home' ],
        'wpsl_home_feedback'            => [ 'home', 'status_report' ],
        'wpsl_home_create_page'         => [ 'home' ],
        'wpsl_home_mark_placed'         => [ 'home' ],
    ];

    /**
     * Classify the current request.
     *
     * Contexts are cumulative - editing a store matches both 'editor' and
     * 'store_edit'. A ?page= starting with 'wpsl' but not one of ours maps to
     * 'unknown_wpsl', which loads the full deferred set so add-on screens keep
     * the pre-3.0 behavior.
     *
     * @since  3.0.0
     * @return string[] The matched context ids
     */
    public function get_contexts() {
        global $pagenow;

        $contexts = [];

        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- routing only, no state changes
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( $page ) {
            if ( isset( self::PAGE_CONTEXTS[ $page ] ) ) {
                $contexts[] = self::PAGE_CONTEXTS[ $page ];
            } elseif ( strpos( $page, 'wpsl' ) === 0 ) {
                $contexts[] = 'unknown_wpsl';
            }
        }

        if ( in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
            $contexts[] = 'editor';

            if ( $this->current_post_type() === 'wpsl_stores' ) {
                $contexts[] = 'store_edit';
            }
        }

        if ( 'edit.php' === $pagenow && ! $page && $this->current_post_type() === 'wpsl_stores' ) {
            $contexts[] = 'store_list';
        }

        if ( in_array( $pagenow, [ 'edit-tags.php', 'term.php' ], true )
            && isset( $_GET['taxonomy'] )
            && 'wpsl_store_category' === sanitize_key( wp_unslash( $_GET['taxonomy'] ) )
        ) {
            $contexts[] = 'store_taxonomy';
        }

        if ( 'plugins.php' === $pagenow ) {
            $contexts[] = 'plugins';
        }

        if ( 'options.php' === $pagenow
            && isset( $_POST['option_page'] )
            && 'wpsl_settings' === sanitize_key( wp_unslash( $_POST['option_page'] ) )
        ) {
            $contexts[] = 'settings';
        }

        // Import/export settings actions fire through Controller::process_actions().
        if ( isset( $_REQUEST['wpsl-action'] ) ) {
            $contexts[] = 'tools';
        }

        if ( wp_doing_ajax() ) {
            $contexts[] = 'ajax';
        }

        if ( 'admin-post.php' === $pagenow ) {
            $contexts[] = 'admin_post';
        }
        // phpcs:enable

        return array_values( array_unique( $contexts ) );
    }

    /**
     * The deferred services the current ( or given ) contexts need.
     *
     * @since  3.0.0
     * @param  array|null $contexts Context ids, or null to classify the request
     * @return string[]             Container service ids to resolve
     */
    public function get_services( $contexts = null ) {
        if ( null === $contexts ) {
            $contexts = $this->get_contexts();
        }

        $map      = $this->screen_service_map();
        $services = [];

        foreach ( $contexts as $context ) {
            if ( isset( $map[ $context ] ) ) {
                $services = array_merge( $services, $map[ $context ] );
            }
        }

        if ( in_array( 'ajax', $contexts, true ) || in_array( 'admin_post', $contexts, true ) ) {
            /**
             * Filter the AJAX / admin-post action to service map.
             *
             * Lets add-ons pre-hook WPSL admin services for their own actions.
             *
             * @since 3.0.0
             * @param array $map Action name => service id list
             */
            $action_map = apply_filters( 'wpsl_admin_ajax_service_map', self::AJAX_SERVICES );

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- routing only, the handlers verify nonces
            $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

            if ( $action && isset( $action_map[ $action ] ) ) {
                $services = array_merge( $services, $action_map[ $action ] );
            }
        }

        /**
         * The welcome pointer follows the user to any admin page until it is
         * dismissed, and the asset manager owns its script. Once dismissed,
         * non-WPSL pages stop loading WPSL admin assets entirely.
         */
        if ( ! in_array( 'ajax', $contexts, true )
            && ! in_array( 'admin_post', $contexts, true )
            && ! in_array( 'asset_manager', $services, true )
            && $this->pointer_pending()
        ) {
            $services[] = 'asset_manager';
        }

        // Only sites that came from 1.x can have opening hours left to convert.
        if ( ! get_option( 'wpsl_legacy_support' ) ) {
            $services = array_diff( $services, [ 'hours_converter' ] );
        }

        return array_values( array_unique( $services ) );
    }

    /**
     * The context to services map.
     *
     * 'unknown_wpsl' is the full deferred set: the union of the settings,
     * appearance, tools and store_edit rows, so unrecognized wpsl* add-on
     * screens keep the pre-3.0 always-loaded behavior.
     *
     * @since  3.0.0
     * @return array Context id => service id list
     */
    private function screen_service_map() {
        $map = [
            'home'           => [ 'home', 'asset_manager' ],
            'settings'       => [ 'admin_settings', 'field_manager', 'section_editor', 'validate_keys', 'asset_manager' ],
            'appearance'     => [ 'appearance', 'asset_manager' ],
            'map_shapes'     => [ 'map_shapes_admin', 'asset_manager' ],
            'marker_studio'  => [ 'asset_manager' ],
            'tools'          => [ 'data_management', 'cache_manager', 'hours_converter', 'status_report', 'asset_manager' ],
            'store_edit'     => [ 'metaboxes', 'geocode', 'asset_manager' ],
            'store_list'     => [ 'asset_manager', 'geocode_locations' ],
            'store_taxonomy' => [ 'asset_manager' ],
            'addons'         => [ 'asset_manager' ],
            'whats_new'      => [ 'asset_manager' ],
            'onboarding'     => [ 'asset_manager', 'onboarding' ],
            'editor'         => [ 'shortcode_generator' ],
        ];

        $map['unknown_wpsl'] = array_values( array_unique( array_merge(
            $map['settings'],
            $map['appearance'],
            $map['tools'],
            $map['store_edit']
        ) ) );

        /**
         * Filter the context to services map.
         *
         * Lets add-ons register their own admin screens ( in combination with
         * a context they add via request classification, or by extending an
         * existing context's service list ).
         *
         * @since 3.0.0
         * @param array $map Context id => service id list
         */
        return apply_filters( 'wpsl_admin_screen_services', $map );
    }

    /**
     * Resolve the post type this request is about.
     *
     * Store screens identify it three ways: post-new.php and edit.php carry
     * ?post_type=, post.php GETs carry ?post=, and the save POST carries
     * post_ID.
     *
     * @since  3.0.0
     * @return string The post type, or an empty string
     */
    private function current_post_type() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- routing only
        if ( isset( $_GET['post_type'] ) ) {
            return sanitize_key( wp_unslash( $_GET['post_type'] ) );
        }

        $post_id = 0;

        if ( isset( $_GET['post'] ) ) {
            $post_id = (int) $_GET['post'];
        } elseif ( isset( $_POST['post_ID'] ) ) {
            $post_id = (int) $_POST['post_ID'];
        }
        // phpcs:enable

        return $post_id ? (string) get_post_type( $post_id ) : '';
    }

    /**
     * The procedural admin files the current ( or given ) contexts need.
     *
     * These files register hooks at file scope ( or self-instantiate ), so
     * unlike the namespaced services they cannot be autoloaded on demand.
     *
     * @since  3.0.0
     * @param  array|null $contexts Context ids, or null to classify the request
     * @return string[]             Plugin-dir-relative file paths
     */
    public function get_required_files( $contexts = null ) {
        if ( null === $contexts ) {
            $contexts = $this->get_contexts();
        }

        $map = [
            'tools'        => [ 'includes/admin/tools/import-export-settings.php' ],
            'store_edit'   => [ 'includes/admin/tools/data-export.php' ],
            'plugins'      => [ 'includes/admin/utils/class-exit-survey.php' ],
            'unknown_wpsl' => [
                'includes/admin/tools/import-export-settings.php',
                'includes/admin/tools/data-export.php',
            ],
        ];

        $files = [];

        foreach ( $contexts as $context ) {
            if ( isset( $map[ $context ] ) ) {
                $files = array_merge( $files, $map[ $context ] );
            }
        }

        return array_values( array_unique( $files ) );
    }

    /**
     * Whether the welcome pointer is still undismissed for this user.
     *
     * Mirrors the checks Assets\Manager::maybe_show_pointer() runs, so the
     * asset manager keeps loading globally exactly as long as the pointer
     * can still render.
     *
     * @since  3.0.0
     * @return bool
     */
    private function pointer_pending() {
        /** This filter is documented in includes/admin/assets/class-manager.php */
        if ( apply_filters( 'wpsl_disable_welcome_pointer', false ) ) {
            return false;
        }

        $dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

        return ! in_array( 'wpsl_signup_pointer', $dismissed, true );
    }
}