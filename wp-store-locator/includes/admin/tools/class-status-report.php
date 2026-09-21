<?php
/**
 * Create a status report
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Post_Types\Register as PostTypes;

class Status_Report {

    /**
     * Settings manager
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    public $settings;

    /**
     * Post types manager
     *
     * @since 3.0.0
     * @var \WPSL\Core\Post_Types\Register
     */
    public $post_types;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager    $settings   Settings manager instance
     * @param \WPSL\Core\Post_Types\Register $post_types Post types manager instance
     */
    public function __construct( WpslSettings $settings, PostTypes $post_types ) {
        $this->settings  = $settings;
        $this->post_types = $post_types;
        
        add_action( 'wpsl_download_status_report', [ $this, 'generate_report' ], 10, 2 );
    }

    /**
     * Check if a settings field is enabled or disabled.
     *
     * @since  3.0.0
     * @param  string $section The settings section
     * @param  string $key     The settings key
     * @return string          'Enabled' or 'Disabled'
     */
    protected function check_settings_status( $section, $key ) {
        $value = $this->settings->get( $section, $key, false );

        return $value ? 'Enabled' : 'Disabled';
    }

    /**
     * Report blocks a caller can leave out.
     *
     * The environment and location blocks are always included; they hold no
     * detail beyond the site URL that's already in the report header.
     *
     * @since 3.0.0
     * @var   array
     */
    const OPTIONAL_BLOCKS = [ 'wpsl', 'theme', 'plugins' ];

    /**
     * Collect different system details
     * like the WordPress environment used
     * WPSL settings and the active plugins.
     *
     * @since  3.0.0
     * @param  array|null $blocks Optional subset of self::OPTIONAL_BLOCKS, null for the full report
     * @return string             The formatted report
     */
    public function get_report( $blocks = null ) {
        return implode( "\n", $this->get_report_blocks( $blocks ) );
    }

    /**
     * Build the report as separate named blocks.
     *
     * Blocks are assembled separately so a caller can show 
     * or drop them one by one, and only joined afterwards.
     *
     * @since  3.0.0
     * @param  array|null $blocks Optional subset of self::OPTIONAL_BLOCKS, null for all of them
     * @return array              Block name => formatted text
     */
    public function get_report_blocks( $blocks = null ) {
        $optional = ( null === $blocks ) ? self::OPTIONAL_BLOCKS : array_intersect( self::OPTIONAL_BLOCKS, (array) $blocks );
        $report   = [ 'environment' => $this->get_environment_block() ];

        if ( in_array( 'wpsl', $optional, true ) ) {
            $report['wpsl'] = $this->get_wpsl_block();
        }

        if ( in_array( 'theme', $optional, true ) ) {
            $report['theme'] = $this->get_theme_block();
        }

        if ( in_array( 'plugins', $optional, true ) ) {
            $report['plugins'] = $this->get_plugins_block();
        }

        $report['locations'] = $this->get_locations_block();

        return $report;
    }

    /**
     * The WordPress and server environment.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_environment_block() {
        global $wpdb;

        $locale     = get_locale();
        $permalinks = get_option( 'permalink_structure' );

        $return  = '### Generated at ' . gmdate( 'Y-m-d H:i:s' ) . ' on ' . site_url() . ' ###' .  "\n\n";

        $return .= '-- WordPress Environment' . "\n\n";
        $return .= 'WP Store Locator: ' . WPSL_VERSION_NUM . "\n";
        $return .= 'Version: ' . get_bloginfo( 'version' ) . "\n";
        $return .= 'Language: ' . ( ! empty( $locale ) ? $locale : 'en_US' ) . "\n";
        $return .= 'Multilingual: ' . $this->get_multilingual_status() . "\n";
        $return .= 'WP_DEBUG: ' . ( defined( 'WP_DEBUG' ) ? WP_DEBUG ? 'Enabled' : 'Disabled' : 'Not set' ) . "\n";
        $return .= 'Multisite: ' . ( is_multisite() ? 'Enabled' : 'Disabled' ) . "\n";
        $return .= 'Block theme: ' . ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? 'yes' : 'no' ) . "\n";
        $return .= 'Object cache: ' . ( wp_using_ext_object_cache() ? 'Enabled' : 'Disabled' ) . "\n";
        $return .= 'Permalinks: ' . ( $permalinks ? $permalinks : 'plain' ) . "\n\n";

        $return .= '-- Server Environment' . "\n\n";
        $return .= 'PHP version: ' . PHP_VERSION . "\n";
        $return .= 'MySQL version: ' . $wpdb->db_version() . "\n";
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Server variable, sanitized with sanitize_text_field
        $return .= 'Webserver info: ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ) : 'Unknown' ) . "\n";
        $return .= 'Memory limit: ' . ini_get( 'memory_limit' ) . "\n";
        $return .= 'Upload max size: ' . ini_get( 'upload_max_filesize' ) . "\n";
        $return .= 'Time limit: ' . ini_get( 'max_execution_time' ) . "\n";
        $return .= 'Display errors: ' . ( ini_get( 'display_errors' ) ? 'On (' . ini_get( 'display_errors' ) . ')' : 'N/A' ) . "\n";
        $return .= 'cURL: ' . ( function_exists( 'curl_init' ) ? 'Supported' : 'Not Supported' ) . "\n";

        return $return;
    }

    /**
     * The WP Store Locator configuration.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_wpsl_block() {
        $return = '-- WP Store Locator Configuration' . "\n\n";

        $active_map_service = $this->settings->get( 'api', 'active_map_service', 'gmaps' );
        $return .= 'Map provider: ' . $active_map_service . "\n";
        $return .= 'Map style: ' . $this->get_map_style( $active_map_service ) . "\n";

        if ( $active_map_service == 'gmaps' ) {
            // Use a class method instead of global function if possible
            if ( function_exists( 'wpsl_check_gmaps_key_status' ) ) {
                $return .= 'Server API key status: ' . wpsl_check_gmaps_key_status() . "\n";
            }
        }

        $return .= 'Region handling: ' . $this->settings->get( 'api', 'region_restriction_type' ) . "\n";
        $return .= 'Map region (bias): ' . $this->settings->get( 'api', 'gmaps_region' ) . "\n";
        $country_restrictions = $this->settings->get( 'api', 'multiple_regions' );
        $return .= 'Country restrictions: ' . ( ! empty( $country_restrictions ) && is_array( $country_restrictions ) ? implode( ',', $country_restrictions ) : 'none' ) . "\n";
        $return .= 'Zip only search: ' . $this->check_settings_status( 'search', 'force_postalcode' ) . "\n";
        $return .= 'Max results: ' . $this->settings->get( 'search', 'max_results', '25' ) . "\n";
        $return .= 'Search radius: ' . $this->settings->get( 'search', 'radius', '10' ) . "\n";
        $return .= 'Enforce borders: ' . $this->check_settings_status( 'search', 'enforce_borders' ) . "\n";
        $return .= 'Full search: ' . $this->check_settings_status( 'search', 'full_search' ) . "\n";
        $return .= 'Geolocation API: ' . $this->check_settings_status( 'search', 'auto_locate' ) . "\n";
        $return .= 'Autoload locations: ' . $this->check_settings_status( 'map', 'autoload' ) . "\n";

        if ( $this->settings->get( 'map', 'autoload', false ) ) {
            $return .= 'Do not use the start location: '. $this->check_settings_status( 'map', 'autoload_start_latlng' ) . "\n";
            $return .= 'Autoload limit: ' . $this->settings->get( 'map', 'autoload_limit', '50' ) . "\n";
        }

        $return .= 'Start location coordinates: ' . $this->settings->get( 'map', 'start_latlng' ) . "\n";
        $return .= 'Run fitbounds: ' . $this->check_settings_status( 'map', 'run_fitbounds' ) . "\n";
        $return .= 'Template: ' . $this->settings->get( 'appearance', 'template_id' ) . "\n";
        $return .= $this->get_template_details();
        $return .= 'Start marker: ' . $this->settings->get( 'markers', 'start_marker' ) . "\n";
        $return .= 'Store marker: ' . $this->settings->get( 'markers', 'store_marker' ) . "\n";
        $return .= 'Active store marker: ' . $this->settings->get( 'markers', 'active_marker' ) . "\n";
        $return .= 'Marker clusters: ' . $this->check_settings_status( 'markers', 'marker_clusters' ) . "\n";
        $return .= 'Debug: ' . $this->check_settings_status( 'tools', 'debug' ) . "\n";
        $return .= $this->get_upgrade_details();

        return $return;
    }

    /**
     * The active theme.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_theme_block() {
        $themes = $this->get_theme_data();

        $return  = '-- Theme' . "\n\n";
        $return .= 'Name: ' . $themes['name'] . "\n";
        $return .= 'Version: ' . $themes['version'] . "\n";
        $return .= 'Update available: ' . $themes['update_available'] . "\n";
        $return .= 'Child theme: ' . $themes['child_theme'] . "\n";

        return $return;
    }

    /**
     * The active plugins and their update status.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_plugins_block() {
        $updates        = get_plugin_updates();
        $plugins        = get_plugins();
        $active_plugins = get_option( 'active_plugins', [] );

        $return = '-- Active Plugins' . "\n\n";

        foreach ( $plugins as $path => $plugin ) {
            if ( ! in_array( $path, $active_plugins ) ) {
                continue;
            }

            $update = '';

            if ( isset( $updates[ $path ] ) ) {
                $update = ' (needs update - ' . $updates[ $path ]->update->new_version . ')';
            }

            $return .= $plugin['Name'] . ': ' . $plugin['Version'] . ' ' . $update . "\n";
        }

        return $return;
    }

    /**
     * The store counts per post status, plus the category count.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_locations_block() {
        $post_counts = wp_count_posts( 'wpsl_stores' );
        $categories  = wp_count_terms( [ 'taxonomy' => 'wpsl_store_category', 'hide_empty' => false ] );

        $return  = '-- Locations' . "\n\n";
        $return .= 'Published: ' . ( isset( $post_counts->publish ) ? $post_counts->publish : '0' ) . "\n";
        $return .= 'Draft: ' . ( isset( $post_counts->draft ) ? $post_counts->draft : '0' ) . "\n";
        $return .= 'Pending: ' . ( isset( $post_counts->pending ) ? $post_counts->pending : '0' ) . "\n";
        $return .= 'Private: ' . ( isset( $post_counts->private ) ? $post_counts->private : '0' ) . "\n";
        $return .= 'Trash: ' . ( isset( $post_counts->trash ) ? $post_counts->trash : '0' ) . "\n";
        $return .= 'Categories: ' . ( is_wp_error( $categories ) ? 'unknown' : $categories ) . "\n";

        return $return;
    }

    /**
     * Whether the active template comes from outside the plugin, and whether
     * it still carries v2 markers.
     *
     * A v2 template on a v3 install is the most likely cause of a broken
     * map, and nothing else in the report would reveal it.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_template_details() {
        if ( ! function_exists( 'wpsl_custom_template_is_active' ) ) {
            return '';
        }

        $is_custom = wpsl_custom_template_is_active();
        $return    = 'Custom template: ' . ( $is_custom ? 'yes' : 'no' ) . "\n";

        if ( ! $is_custom ) {
            return $return;
        }

        $template = wpsl_get_active_template();

        if ( ! empty( $template['path'] ) ) {
            // Relative to the WP root, an absolute server path helps nobody debug.
            $return .= 'Template path: ' . str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $template['path'] ) ) . "\n";
            $return .= 'Template uses 2.x markers: ' . ( wpsl_template_uses_legacy_markers( $template['path'] ) ? 'yes' : 'no' ) . "\n";
        }

        return $return;
    }

    /**
     * Flags that reveal whether the site was upgraded or installed fresh.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_upgrade_details() {
        $migrated = [];

        foreach ( [ 'license_data', 'option_autoload', 'region_restriction_type', 'gmaps_country_restrictions' ] as $flag ) {
            if ( get_option( 'wpsl_' . $flag . '_migrated' ) ) {
                $migrated[] = $flag;
            }
        }

        $return  = 'Legacy support ( upgraded from 1.x ): ' . ( get_option( 'wpsl_legacy_support' ) ? 'yes' : 'no' ) . "\n";
        $return .= 'Completed migrations: ' . ( $migrated ? implode( ', ', $migrated ) : 'none' ) . "\n";

        return $return;
    }

    /**
     * The selected map style for the active provider.
     *
     * @since  3.0.0
     * @param  string $provider The active map service
     * @return string
     */
    protected function get_map_style( $provider ) {
        $map_style = $this->settings->get( 'appearance', 'map_style', [] );

        if ( empty( $map_style[ $provider ]['selected'] ) ) {
            return 'unknown';
        }

        return $map_style[ $provider ]['selected'];
    }

    /**
     * The active multilingual plugin and its languages.
     *
     * Named separately because the plugin list is optional, and template
     * sections are stored per language.
     *
     * @since  3.0.0
     * @return string
     */
    protected function get_multilingual_status() {
        $languages = [];

        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $plugin = 'WPML ' . ICL_SITEPRESS_VERSION;

            if ( function_exists( 'icl_get_languages' ) ) {
                $languages = array_keys( (array) icl_get_languages( 'skip_missing=0' ) );
            }
        } elseif ( defined( 'POLYLANG_VERSION' ) ) {
            $plugin = 'Polylang ' . POLYLANG_VERSION;

            if ( function_exists( 'pll_languages_list' ) ) {
                $languages = (array) pll_languages_list();
            }
        } elseif ( defined( 'WEGLOT_VERSION' ) ) {
            $plugin = 'Weglot ' . WEGLOT_VERSION;
        } else {
            return 'none';
        }

        return $plugin . ( $languages ? ' ( ' . implode( ', ', $languages ) . ' )' : '' );
    }

    /**
     * Collect data from the active theme.
     *
     * @since  3.0.0
     * @return array $response
     */
    protected function get_theme_data() {
        $theme_data = wp_get_theme();

        $response = [
            'name'             => $theme_data->Name,
            'version'          => $theme_data->Version,
            'update_available' => ( get_theme_update_available( $theme_data ) ) ? 'yes' : 'no',
            'child_theme'      => ( is_child_theme() ) ? 'yes' : 'no'
        ];

        return $response ;
    }

    /**
     * Send the collected status report
     * as a downloadable text file to the user.
     *
     * @since 3.0.0
     */
    public function generate_report() {
        ob_end_clean();

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return;
        }
        
        // Verify nonce for security
        if ( ! isset( $_POST['wpsl_status_report_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_status_report_nonce'] ) ), 'wpsl_status_report' ) ) {
            wp_die( esc_html__( 'Security check failed', 'wp-store-locator' ) );
        }

        nocache_headers();

        header( 'Content-Type: text/plain' );
        header( 'Content-Disposition: attachment; filename="wpsl-status-report-' . gmdate( 'Y-m-d' ) . '.txt"' );
        header( 'Pragma: no-cache' );

        // Sanitize and validate the POST data
        $report_content = '';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Sanitized with wp_strip_all_tags which handles unslashing
        if ( isset( $_POST['wpsl-status-report'] ) && is_string( $_POST['wpsl-status-report'] ) ) {
            $report_content = wp_strip_all_tags( $_POST['wpsl-status-report'] );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text file download ( Content-Type: text/plain ), HTML escaping would corrupt the report and XSS is not possible in this context.
        echo $report_content;

        exit();
    }
}