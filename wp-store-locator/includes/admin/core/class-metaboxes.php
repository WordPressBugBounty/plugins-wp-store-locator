<?php
/**
 * Handle the metaboxes
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Utils\Location_Utils;
use WPSL\Core\API\Service as ApiService;

use WPSL\Admin\Controller as Admin;
use WPSL\Admin\API\Geocode;
use WPSL\Admin\Core\Notices;
use WPSL\Admin\Core\Field_Renderer;
use WPSL\Admin\Utils\System;
use WPSL\Admin\Settings\UI;

/**
 * Handle the meta boxes
 *
 * @since 2.0.0
 */
class Metaboxes {

    /**
     * Whether required fields can be left empty when saving a store.
     *
     * @since 3.0.0
     */
    private $skip_required_check;

    /**
     * Admin instance.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Controller
     */
    private $admin;

    /**
     * Geocode instance.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\API\Geocode
     */
    private $geocode;

    /**
     * Notices instance.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Core\Notices
     */
    private $notices;

    /**
     * System instance.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Utils\System
     */
    private $system;

    /**
     * API service instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\API\Service
     */
    private $api;

    /**
     * UI helper instance.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\UI
     */
    private $ui;

    /**
     * Location utils instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Utils\Location_Utils
     */
    private $location_utils;

    /**
     * Configuration object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $config;

    /**
     * Settings manager instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Field renderer ( the metabox view layer ).
     *
     * @var \WPSL\Admin\Core\Field_Renderer
     */
    private $field_renderer;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Admin\API\Geocode         $geocode        Geocode service instance
     * @param \WPSL\Admin\Controller          $admin          Admin controller instance
     * @param \WPSL\Admin\Core\Notices        $notices        Notices manager instance
     * @param \WPSL\Admin\Utils\System        $system         System info service instance
     * @param \WPSL\Core\Settings\Manager     $settings       Settings manager instance
     * @param \WPSL\Core\API\Service          $api            API service instance
     * @param \WPSL\Admin\Settings\UI         $ui             UI helper instance
     * @param \WPSL\Core\Utils\Location_Utils $location_utils Location utils instance
     */
    public function __construct( Geocode $geocode, Admin $admin, Notices $notices, System $system, WpslSettings $settings, ApiService $api, UI $ui, Location_Utils $location_utils ) {
        $this->geocode  = $geocode;
        $this->admin    = $admin;
        $this->notices  = $notices;
        $this->system   = $system;
        $this->config   = $settings->get_all();
        $this->settings = $settings;
        $this->api      = $api;
        $this->ui       = $ui;
        $this->location_utils = $location_utils;
        
        $this->skip_required_check = apply_filters( 'wpsl_skip_required_check', false );

        $this->field_renderer = new Field_Renderer(
            $this->config,
            $this->skip_required_check,
            $this->ui
        );

        add_action( 'add_meta_boxes',         [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post',              [ $this, 'save_post' ] );
        add_action( 'post_updated_messages',  [ $this, 'store_update_messages' ] );
    }

    /**
     * Add the meta boxes.
     *
     * @since  2.0.0
     * @return void
     */
    public function add_meta_boxes() {
        global $pagenow;

        add_meta_box( 'wpsl-store-details', esc_html__( 'Store Details', 'wp-store-locator' ), [ $this, 'create_meta_fields' ], 'wpsl_stores', 'normal', 'high' );

        add_meta_box( 'wpsl-location-marker', esc_html__( 'Marker', 'wp-store-locator' ), [ $this, 'location_marker' ], 'wpsl_stores', 'side' );
        add_meta_box( 'wpsl-map-preview', esc_html__( 'Store Map', 'wp-store-locator' ), [ $this, 'map_preview' ], 'wpsl_stores', 'side' );

        $enable_option = apply_filters( 'wpsl_enable_export_option', true );

        add_meta_box( 'wpsl-location-status', esc_html__( 'Store Status', 'wp-store-locator' ), [ $this, 'location_status' ], 'wpsl_stores', 'side', 'low' );

        // Store status is irrelevant for online-only stores. Hide the box on
        // load (the JS toggles it when the online checkbox changes).
        add_filter( 'postbox_classes_wpsl_stores_wpsl-location-status', [ $this, 'maybe_hide_online_metabox' ] );

        if ( $enable_option && $pagenow == 'post.php' ) {
            add_meta_box( 'wpsl-data-export', esc_html__( 'Export', 'wp-store-locator' ), [ $this, 'export_data' ], 'wpsl_stores', 'side', 'low' );
        }
    }

    /**
     * Hide a side meta box for online-only stores.
     *
     * Used as a 'postbox_classes_*' filter callback so the box is hidden on
     * page load, avoiding a flash before the JS online-only toggle runs.
     *
     * @since  3.0.0
     * @param  array $classes Existing postbox CSS classes.
     * @return array $classes
     */
    public function maybe_hide_online_metabox( $classes ) {
        if ( $this->field_renderer->get_store_meta( 'online' ) ) {
            $classes[] = 'wpsl-hide';
        }

        return $classes;
    }

    /**
     * Get the meta box fields.
     *
     * @since  3.0.0
     * @param  array $args Arguments to filter the fields
     * @return array The meta box fields
     */
    public function meta_box_fields( $args = [ 'only_defaults' => false, 'only_custom' => false ] ) {
        $fields_manager = wpsl_get_service( 'store_fields' );

        return $fields_manager->get_fields( $args );
    }

    /**
     * Get the custom field names.
     *
     * @since  3.0.0
     * @param  array $meta_fields Optional. The existing meta fields
     * @return array The meta fields with custom fields added
     */
    private function get_custom_field_names( $meta_fields = [] ) {
        $fields_manager = wpsl_get_service( 'store_fields' );

        return $fields_manager->get_custom_field_names( $meta_fields );
    }

    /**
     * Create the store locator metabox input fields.
     *
     * @since  2.0.0
     * @return void
     */
    public function create_meta_fields() {
        global $wp_version;

        $wpsl_settings = $this->config['editor'];

        wp_nonce_field( 'save', 'wpsl_nonce' );

        // The block editor refreshes this nonce from the validate_save_post AJAX
        // response, so long-lived editor sessions don't fail on an expired nonce.
        ?>
        <input type="hidden" id="wpsl_validate_nonce" name="wpsl_validate_nonce" value="<?php echo esc_attr( wp_create_nonce( 'validate' ) ); ?>"/>

        <?php
        // Warn the user up front when the active geocoder has no valid API key,
        // so they know an address-only store will be saved as a draft on publish.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Notice is built from individually escaped parts.
        echo $this->check_geocode_key_notice();

        // Create the tabbed navigation structure. Flag online-only stores up
        // front so the hours tab and required markers are hidden via CSS on load,
        // instead of flashing before the JS toggle runs.
        $nav_class = 'wpsl-styled-inputs';

        if ( $this->field_renderer->get_store_meta( 'online' ) ) {
            $nav_class .= ' wpsl-online-only';
        }

        echo '<div id="wpsl-meta-nav" class="' . esc_attr( $nav_class ) . '">';
        
        // Collect all tab elements in a single loop with string concatenation
        $boxes = [
            'radio_buttons' => '',
            'labels' => '',
            'content' => ''
        ];
        
        $tab_counter = 1;
        
        // Single loop to build all HTML strings
        foreach ( $this->meta_box_fields() as $tab => $meta_fields ) {

            // Skip opening hours tab if hidden in settings
            if ( $wpsl_settings['hide_hours'] && $tab == esc_html__( 'Opening Hours', 'wp-store-locator' ) ) {
                continue;
            }
            
            /*
             * A group name is free-form, so it may hold nothing that can become
             * an id ( '#$%', or a name written entirely in a non-Latin script ).
             * The group's own uniqid is gone by this point - meta_box_fields()
             * keys by name - so the fallback is derived from the name instead,
             * which is stable across renders and distinct per group.
             */
            $tab_id = $this->convert_attribute_value( $tab, 'group-' . substr( md5( $tab ), 0, 12 ) );
            $checked = ( $tab_counter == 1 ) ? ' checked=""' : '';

            // Mark the opening hours tab so it can be hidden for online-only stores.
            $tab_class = ( $tab === esc_html__( 'Opening Hours', 'wp-store-locator' ) ) ? ' class="wpsl-hours-tab"' : '';

            // Collect the meta box tabs content with data attributes for CSS custom properties
            $boxes['radio_buttons'] .= '<input type="radio" name="tabs"' . $tab_class . ' id="wpsl-' . $tab_id . '-tab" data-tab-index="' . $tab_counter . '"' . $checked . '>';
            $boxes['labels'] .= '<label' . $tab_class . ' for="wpsl-' . $tab_id . '-tab" data-tab-index="' . $tab_counter . '" role="tab" aria-controls="wpsl-' . $tab_id . '-content" tabindex="0">' . esc_html( $tab ) . '</label>';
            $boxes['content'] .= '<div id="wpsl-' . $tab_id . '-content" class="wpsl-tab-content" data-tab-index="' . $tab_counter . '" role="tabpanel" aria-labelledby="wpsl-' . $tab_id . '-tab" tabindex="0">';
                        
            foreach ( $meta_fields as $field_key => $field_data ) {
                $args = [
                    'key'  => $field_key,
                    'data' => $field_data
                ];

                if ( ! isset( $field_data['type'] ) || empty( $field_data['type'] ) || in_array( $field_data['type'], [ 'url', 'email', 'tel' ] ) ) {
                    $field_type = 'text';
                } else {
                    $field_type = $field_data['type'];
                }

                if ( 'hours' === $field_key ) {
                    $field_type = $this->hours_input_type( $field_type );
                }

                // Capture field output and add to content
                ob_start();

                if ( method_exists( $this->field_renderer, $field_type . '_input' ) ) {
                    call_user_func( [ $this->field_renderer, $field_type . '_input' ], $args );
                } else {
                    do_action( 'wpsl_metabox_' . $field_type . '_input', $args );
                }

                $boxes['content'] .= ob_get_clean();
            }
            
            // Close content div
            $boxes['content'] .= '</div>';
            
            $tab_counter++;
        }
        
        // Generate dynamic CSS for tab highlighting and content display based on actual tab count
        $total_tabs = $tab_counter - 1; // Subtract 1 since counter was incremented after last tab
        $checked_selectors = [];
        $focus_selectors = [];
        $content_selectors = [];
        
        //@todo try and find a cleaner solution?
        for ( $i = 1; $i <= $total_tabs; $i++ ) {
            $checked_selectors[] = "#wpsl-meta-nav input[type=\"radio\"]:nth-of-type({$i}):checked ~ .wpsl-tabs label:nth-of-type({$i})";
            $focus_selectors[]   = "#wpsl-meta-nav input[type=\"radio\"]:nth-of-type({$i}):focus ~ .wpsl-tabs label:nth-of-type({$i})";
            $content_selectors[] = "#wpsl-meta-nav input[type=\"radio\"]:nth-of-type({$i}):checked ~ .wpsl-tab-content-wrap > .wpsl-tab-content:nth-of-type({$i})";
        }
        
        $dynamic_css = "
        <style>
        " . implode( ",\n        ", $checked_selectors ) . " {
            background-color: #fff;
            border-bottom-color: #fff;
            color: #000;
        }
        
        " . implode( ",\n        ", $focus_selectors ) . " {
            box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, 0.8);
            outline: none;
        }
        
        " . implode( ",\n        ", $content_selectors ) . " {
            display: block;
        }
        </style>" . "\r\n";
        
        // Output all collected HTML in the correct order
        echo $boxes['radio_buttons']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
        
        echo '<div class="wpsl-tabs" role="tablist">';
        echo $boxes['labels']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
        echo '</div>';
        
        // Output the dynamically generated CSS
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline style block generated from internal selectors, escaping would break the CSS syntax.
        echo $dynamic_css;
        
        echo '<div class="wpsl-tab-content-wrap">';
        echo $boxes['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when the value is built above
        
        // Close content container
        echo '</div>';
        ?>
        <?php echo $this->check_restriction_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        <?php
    }

    /**
     * Warn the user when the active geocoder has no valid API key.
     *
     * @since  3.0.0
     * @return string The notice HTML, or an empty string when nothing is wrong.
     */
    private function check_geocode_key_notice() {
        $wpsl_settings = $this->config['api'];

        $map_service  = $wpsl_settings['active_map_service'];
        $service_name = '';

        if ( $map_service == 'gmaps' ) {
            if ( ! get_option( 'wpsl_valid_gmaps_server_key' ) ) {
                $service_name = esc_html__( 'Google Maps', 'wp-store-locator' );
            }
        } elseif ( $map_service == 'mapbox' && isset( $wpsl_settings['mapbox_geocoder'] ) && $wpsl_settings['mapbox_geocoder'] == 'mapbox' ) {
            if ( ! get_option( 'wpsl_valid_mapbox_key' ) ) {
                $service_name = esc_html__( 'Mapbox', 'wp-store-locator' );
            }
        } elseif ( $map_service == 'stadia' ) {
            // Unlike Mapbox there is no geocoder choice: Stadia geocodes
            // through its own API, which always needs the key.
            if ( ! get_option( 'wpsl_valid_stadia_key' ) ) {
                $service_name = esc_html__( 'Stadia Maps', 'wp-store-locator' );
            }
        }

        // The active geocoder either needs no key or already has a valid one.
        if ( ! $service_name ) {
            return '';
        }

        $settings_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' );

        /* translators: %s: the active map service name ( Google Maps or Mapbox ), wrapped in strong tags. */
        $intro = sprintf( esc_html__( 'No valid %s API key was found.', 'wp-store-locator' ), '<strong>' . $service_name . '</strong>' );

        $explanation = esc_html__( 'New stores are geocoded to determine their coordinates. Without a valid key this request fails, so any store you publish without manually entering a latitude and longitude will be saved as a draft.', 'wp-store-locator' );

        /* translators: 1: opening link tag to the API settings page, 2: closing link tag, 3: opening link tag to the create-a-key documentation, 4: closing link tag. */
        $action = sprintf( esc_html__( 'Add a valid key on the %1$ssettings page%2$s ( %3$slearn how to create one%4$s ), or enter the coordinates manually below.', 'wp-store-locator' ), '<a class="wpsl-trigger-nav" data-item="api" target="_blank" href="' . esc_url( $settings_url ) . '">', '</a>', '<a target="_blank" href="' . esc_url( wpsl_create_key_docs_url( $map_service ) ) . '">', '</a>' );

        $notice  = '<div class="wpsl-warning-callout wpsl-force-block">';
        $notice .= '<p>' . $intro . ' ' . $explanation . '</p>';
        $notice .= '<p>' . $action . '</p>';
        $notice .= '</div>';

        return $notice;
    }

    /**
     * Check if we need to notify the user that
     * there are country restrictions active.
     *
     * @since  3.0.0
     * @return string $restrictions
     */
    private function check_restriction_notice() {
        $wpsl_settings = $this->config['api'];

        $map_service = $wpsl_settings['active_map_service'];
        $country_restrictions = '';
        $lang_restriction = '';

        // For Google Maps the restriction only applies in hard-restrict mode.
        $gmaps_bias_only = ( $map_service === 'gmaps' && ( ! isset( $wpsl_settings['region_restriction_type'] ) || $wpsl_settings['region_restriction_type'] !== 'restrict' ) );

        // All map services share the 'multiple_regions' country restriction.
        if ( ! $gmaps_bias_only && ! empty( $wpsl_settings['multiple_regions'] ) ) {
            $country_restrictions = wpsl_map_country_names( $wpsl_settings['multiple_regions'] );

            if ( $country_restrictions ) {
                $lang_key = $map_service . '_language';

                if ( ! empty( $wpsl_settings[ $lang_key ] ) ) {
                    $name = wpsl_map_language_names( $wpsl_settings[ $lang_key ] );
                    /* translators: %s: language name, this is used to inform the user that the geocoding results are restricted to a specific language. */
                    $lang_restriction = sprintf( esc_html__( ' and will be in %s.', 'wp-store-locator' ), $name );
                }
            }
        }

        if ( $country_restrictions ) {
            /* translators: 1: opening paragraph and strong tags, 2: closing strong tag, 3: opening link tag for settings, 4: closing link tag, 5: country restrictions with language, 6: closing paragraph tag */
            $restrictions = sprintf( esc_html__( '%1$sNote:%2$s with the current %3$ssettings%4$s the geocoding results are restricted to %5$s %6$s', 'wp-store-locator' ), '<p class="wpsl-force-block"><strong>', '</strong>', '<a target="_blank" href="' . admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) .'">', '</a>', $country_restrictions . $lang_restriction, '</p>' );

            return $restrictions;
        }
    }

    /**
     * Create the li elements that are used in the tabs above the store meta fields.
     *
     * @since  2.0.0
     * @param  string $tab          The name of the tab
     * @param  string $active_class Either the class name or empty
     * @return string $nav_item     The HTML for the nav list
     */
    private function meta_field_nav( $tab, $active_class ) {
        $tab_lower = strtolower( str_replace( ' ', '-', $tab ) );
        $nav_item  = '<li class="wpsl-' . esc_attr( $tab_lower ) . '-tab ' . $active_class . '"><a href="#wpsl-' . esc_attr( $tab_lower ) . '">' . esc_html( $tab ) . '</a></li>';

        return $nav_item;
    }

    /**
     * Pick the opening hours input for the location being edited.
     *
     * @since  3.0.0
     * @param  string $default The input type from the settings page.
     * @return string          'textarea' or 'dropdown'
     */
    private function hours_input_type( $default ) {
        global $post;

        if ( ! isset( $post->ID ) ) {
            return $default;
        }

        $stored = get_post_meta( $post->ID, 'wpsl_hours', true );

        if ( is_array( $stored ) && $stored ) {
            return 'dropdown';
        }

        if ( is_string( $stored ) && '' !== trim( $stored ) ) {
            return 'textarea';
        }

        return $default;
    }

    /**
     * Render the opening-hours table.
     *
     * @since  2.0.0
     * @param  string $location Where the hours are shown ( store_page or settings ).
     * @return void
     */
    public function opening_hours( $location = 'store_page' ) {
        return $this->field_renderer->opening_hours( $location );
    }

    /**
     * Save the custom post data.
     *
     * @since  2.0.0
     * @param  integer $post_id store post ID
     * @return void
     */
    public function save_post( $post_id ) {
        global $wpsl;

        $trigger_recode = false;

        if ( empty( $_POST['wpsl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_nonce'] ) ), 'save' ) ) {
            return;
        }

        if ( ! isset( $_POST['post_type'] ) || 'wpsl_stores' !== $_POST['post_type'] ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( is_int( wp_is_post_revision( $post_id ) ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        foreach ( [ 'wpsl_location_marker', 'wpsl_location_marker_active' ] as $marker_field ) {
            if ( isset( $_POST[ $marker_field ] ) ) {
                $location_marker = wpsl_sanitize_marker_value( sanitize_text_field( wp_unslash( $_POST[ $marker_field ] ) ) );

                if ( $location_marker ) {
                    update_post_meta( $post_id, $marker_field, $location_marker );
                } else {
                    delete_post_meta( $post_id, $marker_field );
                }
            }
        }

        if ( ! isset( $_POST['wpsl'] ) || ! is_array( $_POST['wpsl'] ) ) {
            return;
        }

        $store_data = wp_unslash( $_POST['wpsl'] );

        /**
         * If we have an existing address, then check if any of
         * the address details changed. If so, then make sure the
         * address is geocoded again.
         */
        if ( get_post_meta( $post_id, 'wpsl_address', true ) ) {
            $trigger_recode = $this->geocode->address_changed( $post_id, $store_data );
        }

        $this->api->set_metadata( $post_id, $store_data );

        do_action( 'wpsl_save_post', $store_data );

        /**
         * If it's an online store, then no need
         * to check for missing address details
         * and attempt to geocode it. We do still need to flush
         * the autoload transient, since online stores are part
         * of the cached search results.
         */
        if ( isset( $store_data['online'] ) ) {
            $this->system->maybe_delete_autoload_transient( $post_id );
            return;
        }

        /**
         * If all the required fields contain data, then check if we need to
         * geocode the address and if we should delete the autoload transient.
         *
         * Otherwise show a notice for 'missing data' and set the post status to pending.
         */
        if ( ! $this->check_missing_meta_data( $post_id ) ) {
            /**
             * Include the recode param to check if we need to
             * make a new request to the Google Geocode API.
             */
            $store_data['recode'] = $trigger_recode;

            $this->geocode->check_data( $post_id, $store_data );

            /**
             * Runs after the submitted values are stored, so it only fills the
             * country / zip fields this save left empty.
             */
            $this->geocode->apply_stashed_location_fields( $post_id );

            $this->system->maybe_delete_autoload_transient( $post_id );

            $this->notices->clear();
        } else {
            $this->notices->save( 'error', esc_html__( 'Failed to publish the store. Please fill in the required store details.', 'wp-store-locator' ) );
            $this->set_post_pending( $post_id );
        }
    }

    /**
     * Set the post status to pending if the latlng values are empty.
     *
     * @since  2.0.0
     * @param  integer $post_id store post ID
     * @return void
     */
    public function set_post_pending( $post_id ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update avoids re-triggering save_post, the post cache is cleared below
        $wpdb->update( $wpdb->posts, [ 'post_status' => 'pending' ], [ 'ID' => $post_id ] );

        // The direct DB write bypasses the post cache, so clear it to avoid a stale status.
        clean_post_cache( $post_id );

        add_filter( 'redirect_post_location', [ $this, 'remove_message_arg' ] );
    }

    /**
     * Remove the message query arg.
     *
     * If one or more of the required fields are empty, we show a custom msg.
     * So no need for the normal post update messages arg.
     *
     * @since  2.0.0
     * @param  string $location The destination url
     * @return string The destination url with the message arg removed
     */
    public function remove_message_arg( $location ) {
        return remove_query_arg( 'message', $location );
    }

    /**
     * Make sure all the required post meta fields contain data.
     *
     * @since  2.0.0
     * @param  integer $post_id store post ID
     * @return boolean
     */
    private function check_missing_meta_data( $post_id ) {
        if ( $this->skip_required_check ) {
            return false;
        }

        foreach ( $this->meta_box_fields() as $tab => $meta_fields ) {
            foreach ( $meta_fields as $field_key => $field_data ) {
                if ( isset( $field_data['required'] ) && $field_data['required'] ) {
                    $post_meta = get_post_meta( $post_id, 'wpsl_' . $field_key, true );

                    if ( empty( $post_meta ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * The html for the map preview in the sidebar.
     *
     * @since  2.0.0
     * @return void
     */
    public function map_preview() {
        global $post;

        $wpsl_settings = $this->config['api'];

        $css = '';

        if ( ! $this->location_utils->coordinates_set( $post->ID ) ) {
            $css = 'wpsl-hide';
        }

        ?>
        <div id="wpsl-<?php echo esc_attr( $wpsl_settings['active_map_service'] ); ?>-wrap"></div>
        <p class="wpsl-submit-wrap">
            <a id="wpsl-lookup-location" class="button-primary" href="#wpsl-meta-nav"><?php esc_html_e( 'Preview Location', 'wp-store-locator' ); ?></a>

            <?php /* translators: %s: line break */ ?>
            <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php echo sprintf( esc_html__( 'The map preview is based on the provided address, city and country details. %s It will ignore any custom latitude or longitude values.', 'wp-store-locator' ), '<br><br>' ); ?></span></span>
            <em class="wpsl-marker-drag-desc <?php echo esc_attr( $css ); ?>"><?php esc_html_e( 'If the location is shown incorrectly, drag the marker to adjust.', 'wp-store-locator' ); ?></em>
        </p>
        <?php
    }

    /**
     * Collect the options shown in one location marker dropdown.
     *
     * @since 3.0.0
     * @param int    $post_id The store post ID.
     * @param string $type    The marker type, "store" or "active".
     * @return array The bundled and custom marker options, the saved value and the resolved default.
     */
    private function get_location_marker_options( $post_id, $type = 'store' ) {
        $meta_key = 'store' === $type ? 'wpsl_location_marker' : 'wpsl_location_marker_active';
        $saved    = get_post_meta( $post_id, $meta_key, true );
        $pickable = wpsl_pickable_markers();
        $bundled  = $pickable['bundled'];
        $custom   = $pickable['custom'];

        // The marker that applies when nothing is set here: category first, then the settings default.
        $default_label = '';
        $default_src   = '';
        $default_value = '';
        $terms         = get_the_terms( $post_id, 'wpsl_store_category' );

        if ( $terms && ! is_wp_error( $terms ) ) {
            if ( 'store' === $type ) {
                // Mirrors Data::get_term_markers(): the active marker stands in for
                // the normal one when only it is set.
                $default_value = wpsl_category_marker( $terms[0]->term_id, 'store' );

                if ( ! $default_value ) {
                    $default_value = wpsl_category_marker( $terms[0]->term_id, 'active' );
                }

                $default_src = wpsl_category_marker_src( $terms[0]->term_id, 'store' );

                if ( ! $default_src ) {
                    $default_src = wpsl_category_marker_src( $terms[0]->term_id, 'active' );
                }
            } else {
                $default_value = wpsl_category_marker( $terms[0]->term_id, 'active' );
                $default_src   = wpsl_category_marker_src( $terms[0]->term_id, 'active' );
            }

            if ( $default_src ) {
                /* translators: %s: the store category name. */
                $default_label = sprintf( __( 'from category: %s', 'wp-store-locator' ), $terms[0]->name );
            }
        }

        if ( ! $default_src ) {
            $setting_key = 'store' === $type ? 'store_marker' : 'active_marker';

            // A site upgraded from v2 can have a stored empty string here --
            // unlike store_marker, active_marker has no shipped-default
            // fallback baked into Markers::get_props(), so fall back to the
            // registered default ourselves instead of rendering an empty src.
            $default_value = $this->config['markers'][ $setting_key ] ?: $this->settings->get_default( 'markers', $setting_key );
            $default_src   = wpsl_marker_src( $default_value );
            $default_label = __( 'from settings', 'wp-store-locator' );
        }

        return [
            'saved'         => $saved,
            'bundled'       => $bundled,
            'custom'        => $custom,
            'default_src'   => $default_src,
            'default_value' => $default_value,
            'default_label' => $default_label,
        ];
    }

    /**
     * Render the location marker metabox.
     *
     * @since 3.0.0
     * @return void
     */
    public function location_marker() {
        global $post;

        ?>
        <p class="wpsl-lm-type-label" id="wpsl-lm-type-label-store"><?php esc_html_e( 'Normal', 'wp-store-locator' ); ?></p>
        <?php $this->render_marker_dropdown( 'store', $this->get_location_marker_options( $post->ID, 'store' ) ); ?>
        <p class="wpsl-lm-type-label" id="wpsl-lm-type-label-active"><?php esc_html_e( 'Active', 'wp-store-locator' ); ?></p>
        <?php
        $this->render_marker_dropdown( 'active', $this->get_location_marker_options( $post->ID, 'active' ) );

        // Once, under both dropdowns: the ways to a marker neither list holds.
        wpsl_get_service( 'marker_manager' )->render_marker_creation_links();
    }

    /**
     * Render a single location marker dropdown.
     *
     * The markup lives in Marker_Manager::render_marker_dropdown(), shared
     * with the shortcode generator dialog; this maps the metabox specifics
     * ( the meta key the save handler reads ) onto it.
     *
     * @since 3.0.0
     * @param string $type    The marker type, "store" or "active".
     * @param array  $options The options built by get_location_marker_options().
     * @return void
     */
    private function render_marker_dropdown( $type, $options ) {
        $options['input_name'] = 'store' === $type ? 'wpsl_location_marker' : 'wpsl_location_marker_active';

        wpsl_get_service( 'marker_manager' )->render_marker_dropdown( $type, $options );
    }

    /**
     * The html for the export details section in the sidebar.
     *
     * @since  2.2.15
     * @return void
     */
    public function export_data() {
        global $post;

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Server variable, sanitized with sanitize_text_field
        $link_url = wp_nonce_url( admin_url( 'post.php?' . sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) . '&wpsl_data_export=1' ), 'wpsl_export_' . $post->ID, 'wpsl_export_nonce' );

        ?>
        <p class="wpsl-submit-wrap">
            <a id="wpsl-export-data" class="button-primary" href="<?php echo esc_url( $link_url ); ?>"><?php esc_html_e( 'Export Location Data', 'wp-store-locator' ); ?></a>
        </p>
        <?php
    }

    /**
     * Enable setting the location status
     * to open / temporary closed / permanently closed
     *
     * @since 3.0.0
     */
    public function location_status() {
        global $post;

        $status         = get_post_meta( $post->ID, 'wpsl_location_status', true );
        $reopens        = get_post_meta( $post->ID, 'wpsl_reopens', true );
        $exclude_closed = get_post_meta( $post->ID, 'wpsl_exclude_closed', true );

        $status_options = wpsl_get_location_status_options();

        // Check if the store should be automatically reopened
        if ( $status == 'temporarily_closed' && is_numeric( $reopens ) && $reopens <= time() ) {
            $location_status_handler = wpsl_get_service( 'location_status' );
            $location_status_handler->reopen( $post->ID );

            $status = 'open';
            $reopens = '';
            $exclude_closed = 0;
        }

        if ( $reopens ) {
            $reopens = gmdate( 'Y-m-d', $reopens );
        }
        ?>
        <p>
            <select id="wpsl-current-location-status" name="wpsl[location_status]" autocomplete="off">
                <?php
                foreach ( $status_options as $key => $option ) {
                    $selected = ( $status == $key ) ? 'selected="selected"' : '';

                    echo '<option value="'. esc_attr( $key ) .'" ' . $selected .'>' . esc_html( $option) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
                }
                ?>
            </select>
        </p>
        <div class="wpsl-temporarily-closed-options <?php if ( $status !== 'temporarily_closed' ) { echo 'wpsl-hide'; } ?>">
            <p><?php esc_html_e( 'You can optionally provide a date when the store reopens again.' ,'wp-store-locator' ); ?></p>
            <p><input type="text" placeholder="<?php esc_html_e( 'Reopening date', 'wp-store-locator' ); ?>" value="<?php echo esc_attr( $reopens ); ?>" name="wpsl[reopens]" id="wpsl-reopen-datepicker"></p>
        </div>
        <div class="wpsl-permanently-closed-options <?php if ( $status !== 'permanently_closed' ) { echo 'wpsl-hide'; } ?>">
            <p>
                <label for="wpsl-exclude-closed"><?php esc_html_e( 'Exclude from search results', 'wp-store-locator' ); ?></label>
                <input type="checkbox" id="wpsl-exclude-closed" name="wpsl[exclude_closed]" value="1" <?php checked( $exclude_closed, 1 ); ?>>
            </p>
        </div>
        <?php
    }

    /**
     * Store update messages.
     *
     * @since  2.0.0
     * @param  array $messages Existing post update messages.
     * @return array $messages Amended post update messages with new CPT update messages.
     */
    function store_update_messages( $messages ) {
        $post             = get_post();
        $post_type        = get_post_type( $post );
        $post_type_object = get_post_type_object( $post_type );

        $messages['wpsl_stores'] = [
            0  => '', // Unused. Messages start at index 1.
            1  => esc_html__( 'Store updated.', 'wp-store-locator' ),
            2  => esc_html__( 'Custom field updated.', 'wp-store-locator' ),
            3  => esc_html__( 'Custom field deleted.', 'wp-store-locator' ),
            4  => esc_html__( 'Store updated.', 'wp-store-locator' ),
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress core revision parameter
            /* translators: %s: revision date */
            5  => isset( $_GET['revision'] ) ? sprintf( esc_html__( 'Store restored to revision from %s', 'wp-store-locator' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6  => esc_html__( 'Store published.', 'wp-store-locator' ),
            7  => esc_html__( 'Store saved.', 'wp-store-locator' ),
            8  => esc_html__( 'Store submitted.', 'wp-store-locator' ),
            9  => sprintf(
                /* translators: %1$s: scheduled date and time */
                esc_html__( 'Store scheduled for: <strong>%1$s</strong>.', 'wp-store-locator' ),
                date_i18n( esc_html__( 'M j, Y @ G:i', 'wp-store-locator' ), strtotime( $post->post_date ) )
            ),
            10 => esc_html__( 'Store draft updated.', 'wp-store-locator' )
        ];

        if ( ( 'wpsl_stores' == $post_type ) && ( $post_type_object->publicly_queryable ) ) {
            $permalink = get_permalink( $post->ID );

            $view_link = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), esc_html__( 'View store', 'wp-store-locator' ) );
            $messages[ $post_type ][1] .= $view_link;
            $messages[ $post_type ][6] .= $view_link;
            $messages[ $post_type ][9] .= $view_link;

            $preview_permalink = add_query_arg( 'preview', 'true', $permalink );
            $preview_link = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), esc_html__( 'Preview store', 'wp-store-locator' ) );
            $messages[ $post_type ][8]  .= $preview_link;
            $messages[ $post_type ][10] .= $preview_link;
        }

        return $messages;
    }

    /**
     * Converts a string into a valid attribute value.
     *
     * @since  3.0.0
     * @param  string $string   The original string to be converted.
     * @param  string $fallback Id to use when $string holds nothing that survives
     *                          the strip below, e.g. a name written entirely in a
     *                          non-Latin script. Same role as sanitize_title()'s
     *                          $fallback_title, which wp_insert_post() fills with
     *                          the post ID rather than refusing the title.
     * @return string The converted string, suitable for use as an attribute value.
     */
    public function convert_attribute_value( $string, $fallback = '' ) {
        // Transliterate accented characters to non-accented equivalents.
        if ( class_exists( 'Normalizer' ) ) {
            $normalized = \Normalizer::normalize( $string, \Normalizer::FORM_D );

            if ( false !== $normalized && '' !== $normalized ) {
                $string = $normalized;
            }

            $transliterated = preg_replace( '/\p{M}/u', '', $string );
        } else {
            // iconv can return false, emit a notice, or truncate the string on some
            // server configs, so fall back to the original string if it fails or returns empty.
            $transliterated = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $string );
        }

        if ( false === $transliterated || '' === $transliterated ) {
            $transliterated = $string;
        }

        // Replace spaces with hyphens and force lowercase
        $transliterated = strtolower( str_replace( ' ', '-', $transliterated ) );

        // Remove any remaining non-alphanumeric characters except hyphens and underscores
        $valid_attribute = preg_replace( '/[^a-zA-Z0-9-_]/', '', $transliterated );

        /*
         * Nothing survived. The name is still valid - WordPress puts no
         * character allowlist on a user-facing label - but two such groups
         * would share an empty id, and the label/panel pairing that the tabs
         * are built from breaks. Fall back to the caller's id, stripped on the
         * same terms so it can't smuggle anything into the attribute.
         */
        if ( '' === $valid_attribute ) {
            $valid_attribute = preg_replace( '/[^a-zA-Z0-9-_]/', '', (string) $fallback );
        }

        // Ensure the attribute doesn't start with a number (invalid for CSS selectors)
        if ( preg_match( '/^[0-9]/', $valid_attribute ) ) {
            $valid_attribute = 'field-' . $valid_attribute;
        }

        return $valid_attribute;
    }

    /**
     * Extract field names from a fields array.
     * 
     * @since  3.0.0
     * @param  array $fields_array The array containing fields grouped by tabs
     * @return array An array of field names
     */
    private function extract_field_names( $fields_array ) {
        $field_names = [];
        
        foreach ( $fields_array as $tab => $fields ) {
            foreach ( $fields as $field_name => $field_data ) {
                if ( $field_name !== 'hours' ) { // Skip special cases
                    $field_names[] = $field_name;
                }
            }
        }
        
        return $field_names;
    }

    /**
     * Return the names from the metabox fields.
     * 
     * @since  3.0.0
     * @param  array $args Optional args to include / exclude the fields the user created in the field manager
     * @return array
     */
    public function get_field_names( $args = [] ) {
        // If neither only_defaults nor only_custom is specified, get both
        if ( ! isset( $args['only_defaults'] ) && ! isset( $args['only_custom'] ) ) {

            // Get default fields
            $default_fields = $this->meta_box_fields( [ 'only_defaults' => true ] );
            $default_field_names = $this->extract_field_names( $default_fields );
            
            // Get custom fields
            $custom_fields = $this->get_custom_field_names( [], true );
            $custom_field_names = $this->extract_field_names( $custom_fields );
            
            // Combine default and custom field names
            return array_merge( $default_field_names, $custom_field_names );
        }
        
        // Otherwise, use meta_box_fields with the provided args
        $meta_fields = $this->meta_box_fields( $args );

        return $this->extract_field_names( $meta_fields );
    }
}