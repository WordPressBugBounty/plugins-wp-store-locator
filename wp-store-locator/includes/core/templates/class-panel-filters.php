<?php
/**
 * Panel Filters for v3 Templates
 *
 * Handles filter generation for panel-style templates (vertical template).
 * Uses a registry pattern to allow external plugins to add custom filter methods.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations as I18n;
use WPSL\Frontend\State\Manager as StateManager;
use WPSL\Core\Container;
use WPSL\Core\Utils\Location_Utils;

class Panel_Filters extends Filters {

    /**
     * Registry of custom filter methods
     *
     * @var array
     */
    protected static $registered_methods = [];

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager      $settings       Settings handler
     * @param \WPSL\Core\I18n\Translations     $i18n           Translations handler
     * @param \WPSL\Frontend\State\Manager     $state          State manager instance
     * @param \WPSL\Core\Container             $container      Container instance
     * @param \WPSL\Core\Utils\Location_Utils  $location_utils Location utils instance
     */
    public function __construct( WpslSettings $settings, I18n $i18n, StateManager $state, Container $container, Location_Utils $location_utils ) {
        parent::__construct( $settings, $i18n, $state, $container, $location_utils );
        
        $this->register_core_methods();
    }

    /**
     * Register a custom panel filter method
     * 
     * @since 3.0.0
     * @param string   $name     Method name (e.g., 'get_country_listbox')
     * @param callable $callback Callback function that receives $this as first parameter
     */
    public static function register_method( $name, $callback ) {
        if ( is_callable( $callback ) ) {
            self::$registered_methods[$name] = $callback;
        }
    }

    /**
     * Magic method to call registered methods
     *
     * @since 3.0.0
     * @param string $method Method name
     * @param array  $args   Method arguments
     * @return mixed
     * @throws \BadMethodCallException If method doesn't exist
     */
    public function __call( $method, $args ) {
        if ( isset( self::$registered_methods[$method] ) ) {
            return call_user_func_array( self::$registered_methods[$method], array_merge( [ $this ], $args ) );
        }
        
        throw new \BadMethodCallException( sprintf( 'Method %s does not exist in Panel_Filters', esc_html( $method ) ) );
    }

    /**
     * Check if a method is registered
     *
     * @since  3.0.0
     * @param  string $method Method name
     * @return bool
     */
    public function has_method( $method ) {
        return isset( self::$registered_methods[$method] ) || method_exists( $this, $method );
    }

    /**
     * Register core panel filter methods
     *
     * @since 3.0.0
     */
    private function register_core_methods() {
        self::register_method( 'get_radius_listbox', function( $panel_filters ) {
            return $panel_filters->generate_radius_listbox();
        });

        self::register_method( 'get_categories_listbox', function( $panel_filters ) {
            return $panel_filters->generate_categories_listbox();
        });
    }

    /**
     * Generate radius listbox for panel templates
     *
     * @since  3.0.0
     * @return string HTML markup for radius listbox
     */
    private function generate_radius_listbox() {
        $radius_options = $this->get_radius_options();
        $default_restrictions = $this->get_default_restrictions();
        $default_radius = $default_restrictions['search_radius'];
        
        $output = '<ul role="listbox" data-handler="radius">';
        
        foreach ( $radius_options as $radius ) {
            $is_selected = ( $radius == $default_radius ) ? ' wpsl-selected-filter-option' : '';
            $aria_selected = ( $radius == $default_radius ) ? 'true' : 'false';
            
            $output .= '<li class="' . esc_attr( $is_selected ) . '" role="option" tabindex="0" aria-selected="' . esc_attr( $aria_selected ) . '" data-radius="' . esc_attr( $radius ) . '">';
            $output .= esc_html( $radius ) . ' ' . esc_html( wpsl_get_distance_unit() );
            $output .= '</li>';
        }
        
        $output .= '</ul>';
        
        return apply_filters( 'wpsl_panel_radius_listbox', $output );
    }

    /**
     * Get radius options from settings
     *
     * @since  3.0.0
     * @return array Array of radius values
     */
    private function get_radius_options() {
        $radius_values = explode( ',', $this->settings->get( 'search', 'search_radius' ) );
        $radius_options = [];
        
        foreach ( $radius_values as $radius ) {
            $radius = trim( $radius );
            
            // Remove brackets that indicate the default value (e.g., [50])
            if ( strpos( $radius, '[' ) !== false ) {
                $radius = filter_var( $radius, FILTER_SANITIZE_NUMBER_INT );
            }
            
            if ( ! empty( $radius ) && is_numeric( $radius ) ) {
                $radius_options[] = absint( $radius );
            }
        }
        
        // Fallback to defaults if no valid options
        if ( empty( $radius_options ) ) {
            $radius_options = [ 10, 20, 30, 50, 100 ];
        }
        
        return apply_filters( 'wpsl_panel_radius_options', $radius_options );
    }

    /**
     * Get the default radius button text
     *
     * @since  3.0.0
     * @return string Default radius with distance unit (e.g., "50 km")
     */
    public function get_default_radius_text() {
        $default_restrictions = $this->get_default_restrictions();
        $default_radius = $default_restrictions['search_radius'];
        $distance_unit = wpsl_get_distance_unit();
        
        return $default_radius . ' ' . $distance_unit;
    }

    /**
     * Generate categories listbox for panel templates
     *
     * @since  3.0.0
     * @return string HTML markup for categories
     */
    private function generate_categories_listbox() {
        $output = '<div class="wpsl-filter" data-type="category">';
        $output .= $this->get_categories_recursive();
        $output .= '</div>';
        
        return apply_filters( 'wpsl_panel_categories_listbox', $output );
    }

    /**
     * Return the names of the active panel filter(s)
     *
     * @since  3.0.0
     * @return array Array of active filter names
     */
    public function active_filters() {
        $filters = [];

        if ( $this->settings->get( 'search', 'category_filter' ) ) {
            $filters[] = 'category';
        }

        return apply_filters( 'wpsl_filter_names', implode( ',', $filters ) );
    }

    /**
     * Render the buttons inside #wpsl-result-filters.
     *
     * For the horizontal / stacked layouts this is one button per filter.
     * For the nested layout it's a single parent "Filters" button that opens
     * the nested accordion ( see render_filter_options() ).
     *
     * @since  3.0.0
     * @param  string $layout The filter layout ( horizontal | stacked | nested ).
     * @return string HTML for the filter buttons.
     */
    public function render_filter_buttons( $layout = 'horizontal' ) {
        $category_active = $this->is_category_enabled() && $this->has_categories();
        $radius_active   = $this->is_radius_enabled();

        if ( 'nested' === $layout ) {
            $filters_label = $this->i18n->get_translation( 'filters_label', __( 'Filters', 'wp-store-locator' ) );

            return '<button type="button" id="wpsl-show-nested" aria-expanded="false" data-filter-label="' . esc_attr( $filters_label ) . '"><div><span>' . esc_html( $filters_label ) . '</span></div>' . wpsl_get_svg_icon( 'dropdown' ) . '</button>';
        }

        $output = '';

        if ( $category_active ) {
            $output .= '<button type="button" id="wpsl-show-filters" aria-expanded="false" data-filter-label="' . esc_attr( $this->i18n->get_translation( 'filters_label', __( 'Filters', 'wp-store-locator' ) ) ) . '"><div><span>' . esc_html( $this->i18n->get_translation( 'show_filters_label', __( 'Show filters', 'wp-store-locator' ) ) ) . '</span></div>' . wpsl_get_svg_icon( 'dropdown' ) . '</button>';
        }

        if ( $radius_active ) {
            $output .= '<button type="button" id="wpsl-show-radius" aria-expanded="false" data-filter-label="' . esc_attr( $this->i18n->get_translation( 'radius_label', __( 'Search radius', 'wp-store-locator' ) ) ) . '"><div><span>' . esc_html( $this->get_default_radius_text() ) . '</span></div>' . wpsl_get_svg_icon( 'dropdown' ) . '</button>';
        }

        $output .= apply_filters( 'wpsl_panel_filter_buttons', '', $this );

        return $output;
    }

    /**
     * Render the panels inside #wpsl-filter-options.
     *
     * @since  3.0.0
     * @param  string $layout The filter layout ( horizontal | stacked | nested ).
     * @return string HTML for the filter option panels.
     */
    public function render_filter_options( $layout = 'horizontal' ) {
        $category_active = $this->is_category_enabled() && $this->has_categories();
        $radius_active   = $this->is_radius_enabled();

        if ( 'nested' === $layout ) {
            return $this->render_nested_filter_options( $category_active, $radius_active );
        }

        $output = '';

        if ( $category_active ) {
            $output .= '<div data-id="wpsl-show-filters">' . $this->get_categories_listbox() . '</div>';
        }

        if ( $radius_active ) {
            $output .= '<div data-id="wpsl-show-radius">' . $this->get_radius_listbox() . '</div>';
        }

        $output .= apply_filters( 'wpsl_panel_filter_options', '', $this );

        return $output;
    }

    /**
     * Render the nested ( accordion ) variant of the filter options.
     *
     * Each filter becomes a collapsible child: a toggle button followed by
     * its options. The inner option wrappers keep their original data-id
     * values so the existing value collection keeps working unchanged.
     *
     * @since  3.0.0
     * @param  bool $category_active Whether the category filter is active.
     * @param  bool $radius_active   Whether the radius filter is active.
     * @return string HTML for the nested filter options.
     */
    private function render_nested_filter_options( $category_active, $radius_active ) {
        $output = '<div data-id="wpsl-show-nested" class="wpsl-nested">';

        if ( $category_active ) {
            $output .= $this->nested_filter_child(
                $this->i18n->get_translation( 'categories_label', __( 'Categories', 'wp-store-locator' ) ),
                'wpsl-show-filters',
                $this->get_categories_listbox()
            );
        }

        if ( $radius_active ) {
            // Match the horizontal layout: the radius toggle shows the current
            // radius value ( e.g. "100 km" ), not a static "Search radius".
            $output .= $this->nested_filter_child(
                $this->get_default_radius_text(),
                'wpsl-show-radius',
                $this->get_radius_listbox(),
                'wpsl-nested-filter-radius'
            );
        }

        // Let add-ons inject their own nested children ( e.g. countries ).
        $output .= apply_filters( 'wpsl_nested_filter_children', '', $this );

        // Shared apply action for the whole nested panel ( the horizontal /
        // stacked layouts only ever show Apply, so nested matches that ).
        $output .= '<div class="wpsl-filter-actions">';
        $output .= '<button type="submit" class="wpsl-apply-filters wpsl-styled-btn wpsl-primary-btn">' . esc_html( $this->i18n->get_translation( 'apply_label', __( 'Apply', 'wp-store-locator' ) ) ) . '</button>';
        $output .= '</div>';

        $output .= '</div>'; // .wpsl-nested

        return $output;
    }

    /**
     * Build a single nested filter child ( toggle button + its options ).
     *
     * Public so add-ons hooking 'wpsl_nested_filter_children' can render
     * children that match the core markup.
     *
     * @since  3.0.0
     * @param  string $label        The child button label.
     * @param  string $options_id   The data-id for the options wrapper.
     * @param  string $options_html The filter options markup.
     * @param  string $extra_class  Optional extra class for the wrapper.
     * @return string HTML for the nested child.
     */
    public function nested_filter_child( $label, $options_id, $options_html, $extra_class = '' ) {
        $wrapper_class = 'wpsl-nested-filter' . ( $extra_class ? ' ' . $extra_class : '' );

        $output  = '<div class="' . esc_attr( $wrapper_class ) . '">';
        $output .= '<button type="button" class="wpsl-nested-toggle" aria-expanded="false"><div><span>' . esc_html( $label ) . '</span></div>' . wpsl_get_svg_icon( 'dropdown' ) . '</button>';
        $output .= '<div data-id="' . esc_attr( $options_id ) . '" class="wpsl-nested-options">' . $options_html . '</div>';
        $output .= '</div>';

        return $output;
    }
}