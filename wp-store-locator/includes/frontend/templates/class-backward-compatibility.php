<?php
/**
 * Backward compatibility wrapper for v2.x templates
 *
 * This class provides the old $this-> interface for v2.x templates
 * while delegating to the new v3.x service objects.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Backward_Compatibility {

    /**
     * Template services
     *
     * @since 3.0.0
     * @var array
     */
    private $services;

    /**
     * Settings array (flattened for v2 compatibility)
     *
     * @since 3.0.0
     * @var array
     */
    public $settings;

    /**
     * I18n service
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    public $i18n;

    /**
     * Frontend controller
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Controller
     */
    public $frontend;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param array $template_services The template services array (keyed by service names)
     */
    public function __construct( $template_services ) {
        $this->services = $template_services;
        $this->settings = $template_services['wpsl_settings'];
        $this->i18n     = $template_services['i18n'];
        $this->frontend = $template_services['frontend'];
    }

    /**
     * Get CSS classes for the search wrapper
     * Delegates to assets_manager
     *
     * @since  2.0.0
     * @return string CSS classes
     */
    public function get_css_classes() {
        return $this->services['assets_manager']->get_css_classes();
    }

    /**
     * Get custom CSS
     * Delegates to assets_manager
     *
     * @since  2.0.0
     * @return string Custom CSS
     */
    public function get_custom_css() {
        return $this->services['assets_manager']->get_custom_css();
    }

    /**
     * Get dropdown list
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @param  string $list_type The type of dropdown list
     * @return string Dropdown HTML
     */
    public function get_dropdown_list( $list_type ) {
        return $this->services['template_filters']->dropdown_list( $list_type );
    }

    /**
     * Check if category filter should be used
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @return bool Whether to use category filter
     */
    public function use_category_filter() {
        return $this->services['template_filters']->is_category_enabled();
    }

    /**
     * Create category filter
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @return string Category filter HTML
     */
    public function create_category_filter() {
        return $this->services['template_filters']->category_list();
    }

    /**
     * Get search button
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @return string Search button HTML
     */
    public function get_search_button() {
        return $this->services['template_filters']->get_search_button();
    }

    /**
     * Get reset button
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @return string Reset button HTML
     */
    public function get_reset_button() {
        return $this->services['template_filters']->get_reset_button();
    }

    /**
     * Get direction button
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @return string Direction button HTML
     */
    public function get_direction_button() {
        return $this->services['template_filters']->get_direction_button();
    }

    /**
     * Get more info button
     * Delegates to template_filters
     *
     * @since  2.0.0
     * @return string More info button HTML
     */
    public function get_more_info_button() {
        return $this->services['template_filters']->get_more_info_button();
    }

    /**
     * Get back button, delegates to template_filters
     *
     * @since 2.0.0
     * @return string Back button HTML
     */
    public function get_back_button() {
        return $this->services['template_filters']->get_back_button();
    }

    /**
     * Get zoom here button, delegates to template_filters
     *
     * @since  2.0.0
     * @return string Zoom here button HTML
     */
    public function get_zoom_here_button() {
        return $this->services['template_filters']->get_zoom_here_button();
    }

    /**
     * Get phone button, delegates to template_filters
     *
     * @since  2.0.0
     * @return string Phone button HTML
     */
    public function get_phone_button() {
        return $this->services['template_filters']->get_phone_button();
    }

    /**
     * Get map controls, delegates to maps_manager
     *
     * @since  2.0.0
     * @return string Map controls HTML
     */
    public function get_map_controls() {
        if ( method_exists( $this->services['maps_manager'], 'get_map_controls' ) ) {
            return $this->services['maps_manager']->get_map_controls();
        }
        
        return '';
    }

    /**
     * Get geolocation button, delegates to template_filters
     *
     * @since  2.0.0
     * @return string Geolocation button HTML
     */
    public function get_geolocation_button() {
        return $this->services['template_filters']->get_geolocation_button();
    }

    /**
     * Magic method to handle any other method calls
     *
     * @param  string $method The method name
     * @param  array $args The method arguments
     * @return mixed
     */
    public function __call( $method, $args ) {
        foreach ( $this->services as $service_name => $service ) {
            if ( is_object( $service ) && method_exists( $service, $method ) ) {
                return call_user_func_array( [ $service, $method ], $args );
            }
        }

        return '';
    }

    /**
     * Magic method to handle property access
     * This provides access to settings and other properties
     *
     * @param  string $property The property name
     * @return mixed
     */
    public function __get( $property ) {
        switch ( $property ) {
            case 'settings':
                return $this->settings;
            case 'i18n':
                return $this->i18n;
            case 'frontend':
                return $this->frontend;
            default:
                if ( isset( $this->settings[$property] ) ) {
                    return $this->settings[$property];
                }
                
                return null;
        }
    }
}