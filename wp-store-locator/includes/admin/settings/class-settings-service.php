<?php
/**
 * Settings Service
 *
 * Shared functionality between Admin Settings Handler and Sanitizer
 *
 * @since 3.0.0
 */

namespace WPSL\Admin\Settings;

use WPSL\Core\Settings\Manager as WpslSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings Service class
 *
 * @since 3.0.0
 */
class Settings_Service {
    
    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    protected $settings;
    
    /**
     * Constructor
     *
     * @since 3.0.0
     * @param WpslSettings $settings Core settings manager
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;
    }
    
    /**
     * Handle validation errors
     * 
     * @since  3.0.0
     * @param  string $error_type Contains the type of validation error that occurred
     * @return void
     */
    public function validation_error( $error_type ) {
        switch ( $error_type ) {
            case 'max_results':
                $error_msg = esc_html__( 'The max results field needs a default value set between square brackets, for example [25],50,75,100. The default value has been restored.', 'wp-store-locator' );
                break;
            case 'max_results_empty':
                $error_msg = esc_html__( 'The max results field cannot be empty, the default value has been restored.', 'wp-store-locator' );
                break;
            case 'search_radius':
                $error_msg = esc_html__( 'The search radius field needs a default value set between square brackets, for example 10,25,[50],100. The default value has been restored.', 'wp-store-locator' );
                break;
            case 'search_radius_empty':
                $error_msg = esc_html__( 'The search radius field cannot be empty, the default value has been restored.', 'wp-store-locator' );
                break;
            case 'start_point':
                $error_msg = sprintf(
                    /* translators: %s: link to the Map section, with the link text 'Map section' */
                    esc_html__( 'Set a start location in the %s, used when auto-locating is off or fails.', 'wp-store-locator' ),
                    '<a href="#" class="wpsl-trigger-nav" data-item="map" data-focus="wpsl_map[start_name]">' . esc_html__( 'Map section', 'wp-store-locator' ) . '</a>'
                );
                break;
            case 'zoom_level':
                $error_msg = esc_html__( 'The zoom level needs to be a number between 1 and 21.', 'wp-store-locator' );
                break;
            case 'group_name_empty':
                $error_msg = esc_html__( 'A field group needs a name, so the group without one was not saved.', 'wp-store-locator' );
                break;
            case 'group_name_duplicate':
                $error_msg = esc_html__( 'Duplicate group names are not allowed. The previous name has been restored.', 'wp-store-locator' );
                break;
            case 'auto_locate_missing':
                $error_msg = esc_html__( 'If you want to enable auto locating the user, you need to enable the "Use my location" option as well.', 'wp-store-locator' );
                break;
            default:
                $error_msg = esc_html__( 'Something went wrong with validating the settings.', 'wp-store-locator' );
                break;
        }
        
        add_settings_error( 'setting-errors', esc_attr( $error_type ), $error_msg, 'error' );
    }
    
    /**
     * Get a specific setting
     *
     * @since  3.0.0
     * @param  string $section Section name
     * @param  string $key Setting key
     * @param  mixed $default Default value if setting doesn't exist
     * @return mixed
     */
    public function get_setting( $section, $key, $default = '' ) {
        return $this->settings->get( $section, $key, $default );
    }
    
    /**
     * Get all settings for a section
     *
     * @since  3.0.0
     * @param  string $section Section name
     * @return array
     */
    public function get_section_settings( $section ) {
        return $this->settings->get_group( $section );
    }
    
    /**
     * Get all sections used on the WPSL settings page
     *
     * @since  3.0.0
     * @return array Sections used on the WPSL settings page
     */
    public function get_sections() {
        $sections = [
            'api' => [
                'text' => esc_html__( 'API', 'wp-store-locator' ),
            ],
            'search' => [
                'text' => esc_html__( 'Search', 'wp-store-locator' ),
            ],
            'map' => [
                'text' => esc_html__( 'Map', 'wp-store-locator' ),
            ],
            'ux' => [
                'text' => esc_html__( 'User Experience', 'wp-store-locator' ),
            ],
            'markers' => [
                'text' => esc_html__( 'Markers', 'wp-store-locator' ),
            ],
            'editor' => [
                'text' => esc_html__( 'Store Editor', 'wp-store-locator' ),
            ],
            'fields-manager' => [
                'text' => esc_html__( 'Fields Manager', 'wp-store-locator' ),
            ],
            'local_pages' => [
                'text' => esc_html__( 'Local Pages', 'wp-store-locator' ),
            ],
            'labels' => [
                'text' => esc_html__( 'Labels', 'wp-store-locator' ),
            ],
            'gdpr' => [
                'text' => esc_html__( 'GDPR', 'wp-store-locator' ),
            ],
            'tools' => [
                'text' => esc_html__( 'Tools', 'wp-store-locator' ),
            ]
        ];
        
        /*
         * The section editor is offered ( nav entry and form body, both are
         * rendered from this list ) only to a user it is open to, like the
         * theme editor in core. Its parent stays: the Fields Manager form
         * fields must keep posting. See Section_Editor::current_user_can_edit().
         */
        if ( Section_Editor::current_user_can_edit() ) {
            $sections['fields-manager']['sub'] = [
                'section-editor' => [
                    'text' => esc_html__( 'Section Editor', 'wp-store-locator' ),
                ],
            ];
        }

        // Check if any addons want to display settings
        if ( has_action( 'wpsl_addon_settings_output' ) ) {
            $sections['addons'] = [
                'text' => esc_html__( 'Add-Ons', 'wp-store-locator' ),
            ];
        }
        
        return apply_filters( 'wpsl_setting_sections', $sections );
    }

    /**
     * The sections the settings nav should offer.
     *
     * Nav only. get_sections() stays complete on purpose: templates/settings.php
     * renders every section into the form, and Sanitizer::editor() rebuilds
     * wpsl_editor out of what was posted - so a Fields Manager section that is
     * not in the form would wipe every custom field group on the next save. The
     * section itself is hidden with the wpsl-advanced class instead.
     *
     * @since  3.0.0
     * @return array
     */
    public function get_nav_sections() {
        $sections    = $this->get_sections();
        $simple_mode = wpsl_get_service( 'simple_mode' );

        if ( $simple_mode && $simple_mode->is_hidden( 'fields-manager' ) ) {
            unset( $sections['fields-manager'] );
        }

        return $sections;
    }
}
