<?php
/**
 * Template filters.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Utils\Location_Utils;
use WPSL\Core\Container;

use WPSL\Frontend\State\Manager as StateManager;
use WPSL\Frontend\Shortcodes\Shortcodes;

class Filters {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    protected $settings;

    /**
     * Translations service
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    protected $i18n;

    /**
     * State manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    protected $state;

    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    protected $container;

    /**
     * Location utils instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Utils\Location_Utils
     */
    protected $location_utils;

    /**
     * Shortcodes instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Shortcodes\Shortcodes
     */
    protected $shortcodes;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager        $settings       Settings manager instance
     * @param \WPSL\Core\I18n\Translations       $i18n           Translations instance
     * @param \WPSL\Frontend\State\Manager       $state          Frontend state manager instance
     * @param \WPSL\Core\Container               $container      Container instance
     * @param \WPSL\Core\Utils\Location_Utils    $location_utils Location utils instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n, StateManager $state, Container $container, Location_Utils $location_utils ) {
        $this->settings = $settings;
        $this->i18n = $i18n;
        $this->state = $state;
        $this->container = $container;
        $this->location_utils = $location_utils;
    }

    /**
     * Get shortcodes instance.
     *
     * @since  3.0.0
     * @return Shortcodes
     */
    private function get_shortcodes() {
        if ( ! $this->shortcodes ) {
            $this->shortcodes = $this->container->get( 'shortcodes' );
        }

        return $this->shortcodes;
    }

    /**
     * Create a dropdown list holding the
     * search radius or max search results options.
     *
     * @since  1.0.0
     * @param  string $list_type     The name of the list we need to load data for
     * @return string $dropdown_list A list with the available options for the dropdown list
     */
    public function dropdown_list( $list_type ) {
        $wpsl_settings = $this->settings->get_group( 'search' );

        $dropdown_list = '';
        $settings      = explode( ',', $wpsl_settings[$list_type] );

        // Only show the distance unit if we are dealing with the search radius.
        if ( $list_type == 'search_radius' ) {
            $distance_unit = ' '. esc_attr( wpsl_get_distance_unit() );
        } else {
            $distance_unit = '';
        }

        foreach ( $settings as $index => $setting_value ) {
            // The default radius has a [] wrapped around it, so we check for that and filter out the [].
            if ( strpos( $setting_value, '[' ) !== false ) {
                $setting_value = filter_var( $setting_value, FILTER_SANITIZE_NUMBER_INT );
                $selected = 'selected="selected" ';
            } else {
                $selected = '';
            }

            $dropdown_list .= '<option ' . $selected . 'value="'. absint( $setting_value ) .'">'. absint( $setting_value ) . $distance_unit .'</option>';
        }

        return $dropdown_list;
    }

    /**
     * Create the category filter.
     *
     * @since  2.0.0
     * @param  array       $args     Optional args to force a style ( dropdown / checkbox ) and if we need to use default values
     * @return string|void $category The HTML for the category dropdown, or nothing if no terms exist.
     */
    public function category_list( $args = [] ) {
        $wpsl_settings = $this->settings->get_group( 'search' );

        $terms = $this->get_category_terms();
        
        /**
         * Only used on the theme customization 
         * page if no other category data exists.
         */
        if ( ! count( $terms ) && isset( $args['defaults'] ) && $args['defaults'] ) {
            $terms = [
                (object) ['term_id' => 1, 'name' => 'Restaurant'],
                (object) ['term_id' => 2, 'name' => 'Museum'],
                (object) ['term_id' => 3, 'name' => 'Cafe']
            ];
        }

        if ( count( $terms ) > 0 ) {
            // Either use the shortcode atts filter type or the one from the settings page.
            if ( ! isset( $args['style'] ) ) {
                if ( isset( $this->get_shortcodes()->atts['category_filter_type'] ) && ! empty( $this->get_shortcodes()->atts['category_filter_type'] ) ) {
                    $args['style'] = $this->get_shortcodes()->atts['category_filter_type'];
                } else {
                    $args['style'] = isset( $wpsl_settings['category_filter_type'] ) ? $wpsl_settings['category_filter_type'] : 'dropdown';
                }
            }

            $selected = $this->find_selected_category();

            // Check if we need to show the filter as checkboxes or a dropdown list
            if ( $args['style'] == 'checkboxes' ) {
                if ( isset( $this->get_shortcodes()->atts['checkbox_columns'] ) ) {
                    $checkbox_columns = absint( $this->get_shortcodes()->atts['checkbox_columns'] );
                }

                if ( isset( $checkbox_columns ) && $checkbox_columns ) {
                    $column_count = $checkbox_columns;
                } else {
                    $column_count = 3;
                }

                $category = '<ul id="wpsl-checkbox-filter" class="wpsl-checkbox-' . $column_count . '-columns">';

                foreach ( $terms as $term ) {
                    $category .= '<li>';
                    $category .= '<label>';
                    $category .= '<input type="checkbox" value="' . esc_attr( $term->term_id ) . '" ' . $this->set_selected_category( $args['style'], $selected, $term ) . ' />';
                    $category .= esc_html( $term->name );
                    $category .= '</label>';
                    $category .= '</li>';
                }

                $category .= '</ul>';
            } else {
                $label = $this->i18n->get_translation( 'category_label', esc_html__( 'Category', 'wp-store-locator' ) );

                $category = '<div id="wpsl-category">' . "\r\n";

                if ( $label && $this->i18n->is_label_visible( 'category' ) ) {
                    $category .= '<label for="wpsl-category-list">' . esc_html( $label ) . '</label>' . "\r\n";
                }

                if ( ! empty( $terms ) && reset( $terms ) instanceof \WP_Term ) {
                    $dropdown_args = apply_filters( 'wpsl_dropdown_category_args', [
                            'show_option_none'  => $this->i18n->get_translation( 'category_default_label', esc_html__( 'Any', 'wp-store-locator' ) ),
                            'option_none_value' => '0',
                            'orderby'           => 'NAME',
                            'order'             => 'ASC',
                            'echo'              => 0,
                            'selected'          => $this->set_selected_category( $args['style'], $selected ),
                            'hierarchical'      => 1,
                            'name'              => 'wpsl-category',
                            'id'                => 'wpsl-category-list',
                            'class'             => 'wpsl-dropdown',
                            'taxonomy'          => 'wpsl_store_category',
                            'hide_if_empty'     => true,
                            'exclude'           => $this->maybe_exclude_catogries(),
                            'parent'            => $this->maybe_set_parent_id()
                        ]
                    );

                    $category .= wp_dropdown_categories( $dropdown_args );
                } else { // Only used on the theme customization page if no other category data exists.
                    $category .= '<select id="wpsl-category-list" class="wpsl-dropdown" name="wpsl-category">';

                    foreach ( $terms as $term ) {
                        $category .= '<option value="' . esc_attr( $term->term_id ) . '">' . esc_html( $term->name ) . '</option>';
                    }

                    $category .= '</select>';
                }

                $category .= '</div>' . "\r\n";
            }

            return $category;
        }
    }

    /**
     * See if we need to exclude some categories
     * from the dropdown / checkbox filter.
     *
     * @since   3.0.0
     * @return  void|array $exclude_ids possible list of categories to exclude
     */
    public function maybe_exclude_catogries() {
        $exclude_ids = '';

        if ( ! empty( $this->get_shortcodes()->atts['exclude_category'] ) ) {
            $exclude_ids = wpsl_get_term_ids( $this->get_shortcodes()->atts['exclude_category'] );
        }

        return $exclude_ids;
    }

    /**
     * Get category terms.
     *
     * @since  3.0.0
     * @return array|\WP_Error Array of term objects or WP_Error on failure
     */
    public function get_category_terms() {
        $term_args = [
            'taxonomy'      => 'wpsl_store_category',
            'exclude'       => $this->maybe_exclude_catogries(),
            'parent'        => $this->maybe_set_parent_id(),
            'hide_if_empty' => true
        ];

        return get_terms( apply_filters( 'wpsl_get_terms_args', $term_args ) );
    }

    /**
     * Check if the ID for the selected category
     * is either passed through the widget,
     * shortcode or as a URL parameter.
     *
     * @since   3.0.0
     * @return  string|int|array $selection The ID of the category to set selected
     */
    public function find_selected_category() {
        $selection = '';

        if ( isset( $_REQUEST['wpsl-widget'] ) && isset( $_REQUEST['wpsl-widget']['categories'] ) ) {
            $widget_data = wp_unslash( $_REQUEST['wpsl-widget'] );
            $selection = isset( $widget_data['categories'] ) ? absint( $widget_data['categories'] ) : '';
        } else if ( isset( $_REQUEST['wpsl-widget-categories'] ) ) {
            $selection = absint( $_REQUEST['wpsl-widget-categories'] );
        } else if ( ! empty( $this->get_shortcodes()->atts['category_selection'] ) ) {
            $selection = $this->get_shortcodes()->atts['category_selection'];
        } else if ( isset( $_REQUEST['wpsl_cat'] ) ) {
            $selection = wpsl_get_term_ids( sanitize_text_field( wp_unslash( $_REQUEST['wpsl_cat'] ) ) );
        } else {
            $selection = $this->settings->get( 'search', 'category_default' );
        }

        return $selection;
    }

    /**
     * Set the selected category item.
     *
     * @since  2.1.2
     * @param  string           $filter_type The type of filter being used ( dropdown or checkboxes )
     * @param  int|array        $selection   The category ID we need to set selected
     * @param  int|array|string $term        The term data ( checkbox only )
     * @return string|void      $category    The ID of the selected option, or checked='checked' if it's for a checkbox
     */
    public function set_selected_category( $filter_type, $selection, $term = '' ) {
        $selected_id = '';

        // If the widget is used, then it's not an array.
        if ( is_array( $selection ) ) {
            /**
             * When the term_id is set, then it's a checkbox.
             *
             * Otherwise select the first value from the provided list since
             * multiple selections are not supported in dropdowns.
             */
            if ( $term ) {
                // Check if the passed term id exists in the set shortcode value.
                $key = array_search( $term->term_id, $selection );

                if ( $key !== false ) {
                    $selected_id = $selection[$key];
                }
            } else {
                $selected_id = $selection[0];
            }
        } else {
            $selected_id = $selection;
        }

        if ( $selected_id ) {
            /**
             * Based on the filter type, either return the ID of the selected category,
             * or check if the checkbox needs to be set to checked="checked".
             *
             * Only the checkbox filter has a term to compare against, and
             * category_list() renders everything that isn't 'checkboxes' as a
             * dropdown, so an unrecognised type follows it there instead of
             * reading ->term_id off a term a dropdown never passes.
             */
            if ( $filter_type != 'checkboxes' ) {
                return $selected_id;
            }

            return checked( $selected_id, $term->term_id, false );
        }
    }

    /**
     * Create a dropdown that lists all unique values that
     * below to the passed meta key.
     *
     * @since  3.0.0
     * @param  array $args The meta key we use to collect the data for
     * @return string $filter
     */
    public function custom_meta_filter( $args ) {
        $filter      = '';
        $columns     = 3;
        $meta_values = $this->location_utils->get_unique_meta_values( $args['meta_key'] );

        if ( $meta_values ) {
            if ( $args['type'] == 'dropdown' ) {
                $filter = "\t\t\t\t" . '<div id="' . esc_attr( $args['meta_key'] ) . '">' . "\r\n";

                if ( $args['label'] ) {
                    $filter .= "\t\t\t\t\t" . '<label for="' . esc_attr( $args['meta_key'] ) . '">' . esc_html( $args['label'] ) . '</label>' . "\r\n";
                }

                $filter .= "\t\t\t\t\t" . '<select id="' . esc_attr( $args['meta_key'] ) . '" class="wpsl-dropdown wpsl-custom-dropdown" name="' . esc_attr( $args['meta_key'] ) . '">';

                foreach ( $meta_values as $k => $meta_value ) {
                    $selected = ( $meta_value == $args['selected'] ) ? 'selected="selected"' : '';
                    $filter .= "\t\t\t\t\t\t" . '<option value="' .  esc_attr( $meta_value ) . '"' . $selected . '>' . esc_html( $meta_value ) . '</option>';
                }

                $filter .= '</select>';
                $filter .= '</div>';
            } else if ( $args['type'] == 'checkbox' ) {

                if ( isset( $args['columns'] ) && absint( $args['columns'] ) ) {
                    $columns = $args['columns'];
                }

                // The AJAX param key the checked values are assigned to. Defaults to the meta key.
                $data_name = isset( $args['name'] ) && $args['name'] !== '' ? $args['name'] : $args['meta_key'];

                $filter  = '<ul id="wpsl-checkbox-filter" class="wpsl-custom-checkboxes wpsl-checkbox-' . $columns . '-columns"';
                $filter .= ' data-name="' . esc_attr( $data_name ) . '"';

                /**
                 * When a search type is set, the JS routes the selected values
                 * through location[<type>] + types=<type> so a built-in
                 * Types::<type>_search_args handler ( e.g. country / state )
                 * picks them up. Without it the values are sent as a flat
                 * data-name param instead.
                 */
                if ( ! empty( $args['search_type'] ) ) {
                    $filter .= ' data-search-type="' . esc_attr( $args['search_type'] ) . '"';
                }

                $filter .= '>';

                foreach ( $meta_values as $k => $meta_value ) {
                    $selected = ( $meta_value == $args['selected'] ) ? 'checked="checked"' : '';

                    $filter .= '<li>';
                    $filter .= '<label>';
                    $filter .= '<input type="checkbox" value="' . esc_attr( $meta_value ) . '" ' . $selected . ' />';
                    $filter .= esc_html( $meta_value);
                    $filter .= '</label>';
                    $filter .= '</li>';
                }

                $filter .= '</ul>';
            }
        }

        return $filter;
    }

    /**
     * Check if we need to use a dropdown or checkboxes
     * to filter the search results by categories.
     *
     * @since  2.2.10
     * @return bool $use_filter
     */
    public function is_category_enabled() {
        // Check if category_filter is explicitly set via shortcode (not empty string)
        if ( array_key_exists( 'category_filter', $this->get_shortcodes()->atts ) && $this->get_shortcodes()->atts['category_filter'] !== '' ) {
            return filter_var( $this->get_shortcodes()->atts['category_filter'], FILTER_VALIDATE_BOOLEAN );
        }

        /*
         * The search is already scoped to specific categories via the shortcode's
         * category attribute, so showing a filter on top of that is redundant.
         */
        if ( isset( $this->get_shortcodes()->atts['js']['search']['categoryIds'] ) ) {
            return false;
        }

        // If category_filter_type is set, that means the user wants the category filter
        if ( isset( $this->get_shortcodes()->atts['category_filter_type'] ) && ! empty( $this->get_shortcodes()->atts['category_filter_type'] ) ) {
            return true;
        }

        // Fall back to settings page value
        return (bool) $this->settings->get( 'search', 'category_filter' );
    }

    /**
     * Check if the radius filter should be displayed.
     *
     * @since  3.0.0
     * @return bool $use_filter
     */
    public function is_radius_enabled() {
        if ( $this->settings->get( 'search', 'search_method' ) === 'name' ) {
            return false;
        }

        // Shortcode attribute takes priority over settings page value (not empty string)
        if ( array_key_exists( 'radius_filter', $this->get_shortcodes()->atts ) && $this->get_shortcodes()->atts['radius_filter'] !== '' ) {
            return filter_var( $this->get_shortcodes()->atts['radius_filter'], FILTER_VALIDATE_BOOLEAN );
        }

        // Fall back to settings page value
        return (bool) $this->settings->get( 'search', 'radius_dropdown' );
    }

    /**
     * Check if the results filter should be displayed.
     *
     * @since  3.0.0
     * @return bool $use_filter
     */
    public function is_results_enabled() {
        // Shortcode attribute takes priority over settings page value (not empty string)
        if ( array_key_exists( 'results_filter', $this->get_shortcodes()->atts ) && $this->get_shortcodes()->atts['results_filter'] !== '' ) {
            $result = filter_var( $this->get_shortcodes()->atts['results_filter'], FILTER_VALIDATE_BOOLEAN );
            
            return $result;
        }

        // Fall back to settings page value
        $result = (bool) $this->settings->get( 'search', 'results_dropdown' );
        
        return $result;
    }

    /**
     * Check if category filter only mode is enabled.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_category_filter_only() {
        $category_only = false;

        if ( $this->settings->get( 'search', 'category_filter_only' ) ) {
            $category_only = true;
        }

        return $category_only;
    }

    /**
     * Check if any categories exist.
     *
     * @since  3.0.0
     * @return bool
     */
    public function has_categories() {
        $terms = $this->get_category_terms();
        
        return ( count( $terms ) > 0 );
    }

    /**
     * Create lists of checkboxes based on the category data.
     *
     * @since  3.0.0
     * @param  int      $parent_id
     * @param  int      $depth
     * @param  int|null $selected The pre-selected category ID, resolved on the top-level call
     * @return string   The nested checkbox list HTML
     */
    public function get_categories_recursive( $parent_id = 0, $depth = 0, $selected = null ) {
        /**
         * Resolve the selected/default category once on the top-level call and
         * pass it down the recursion so each checkbox can be pre-selected when
         * it matches. Without this the default category filter selection set on
         * the settings page is never applied to the panel checkbox list.
         */
        if ( $selected === null ) {
            $selected = $this->find_selected_category();
        }

        $args = [
            'taxonomy'      => 'wpsl_store_category',
            'parent'        => $parent_id,
            'hide_empty'    => false,
            'hide_if_empty' => true,
            'exclude'       => $this->maybe_exclude_catogries(),
        ];

        $categories = get_terms( $args );
        $output = '';

        if ( $categories ) {
            $output .= '<ul>';

            foreach ( $categories as $category ) {
                $subcategories = $this->get_categories_recursive( $category->term_id, $depth + 1, $selected );

                $css_class = 'wpsl-categories-level-' . $depth;

                if ( $subcategories ) {
                    $css_class .= ' wpsl-has-child';
                }

                $checked = $this->set_selected_category( 'checkboxes', $selected, $category );

                // Reflect the pre-selected default in the markup so it shows as
                // selected on page load without waiting for a JS click event.
                $aria_selected = $checked ? 'true' : 'false';

                $output .= '<li class="' . esc_attr( $css_class ) . '" aria-selected="' . $aria_selected . '">';
                $output .= '<label><input class="' . esc_attr( $css_class ) . '" type="checkbox" value="' . esc_attr( $category->term_id ) . '" ' . $checked . '>' . esc_html( $category->name ) . '</label>';

                if ( $subcategories ) {
                    $output .= $subcategories;
                }

                $output .= '</li>';
            }

            $output .= '</ul>';
        }

        return $output;
    }

    /**
     * Check if a parent id was set in the shortcode.
     * If so, then only the child categories will be shown.
     *
     * @since  3.0.0
     * @return string $parent_id
     */
    public function maybe_set_parent_id() {
        $parent_id = '';

        if ( isset( $this->get_shortcodes()->atts['category_parent_id'] ) ) {
            $term = get_term_by( 'id', $this->get_shortcodes()->atts['category_parent_id'], 'wpsl_store_category' );

            if ( $term && ! $term->parent ) {
                $parent_id = $this->get_shortcodes()->atts['category_parent_id'];
            }
        }

        return $parent_id;
    }

    /**
     * Get the default values for the
     * max_results and the search_radius dropdown.
     *
     * @since  1.0.2
     * @return array $output The default dropdown values
     */
    public function get_default_restrictions() {
        $wpsl_settings = $this->settings->get_group( 'search' );

        $required_defaults = [
            'max_results',
            'search_radius'
        ];

        // Strip out the default values that are wrapped in [].
        foreach ( $required_defaults as $required_default ) {
            preg_match_all( '/\[([0-9]+?)\]/', $wpsl_settings[ $required_default ], $match, PREG_PATTERN_ORDER );
            $output[ $required_default ] = ( isset( $match[1][0] ) ) ? $match[1][0] : '25';
        }

        return $output;
    }

    /**
     * Make sure the filter contains a valid value, otherwise use the default value.
     *
     * @since  2.0.0
     * @param  array  $args         The values used in the SQL query to find nearby locations
     * @param  string $filter       The name of the filter
     * @return string $filter_value The filter value
     */
    public function check_store_filter( $args, $filter ) {
        if ( isset( $args[$filter] ) && absint( $args[$filter] ) && $this->check_allowed_filter_value( $args, $filter ) ) {
            $filter_value = $args[$filter];
        } else {
            $filter_value = $this->get_default_filter_value( $filter );
        }

        return $filter_value;
    }

    /**
     * Make sure the used filter value isn't bigger
     * then the value that's set on the settings page.
     *
     * @since  2.2.9
     * @param  array       $args      The values used in the SQL query to find nearby locations
     * @param  string      $filter    The name of the filter (e.g., 'radius', 'max_results')
     * @param  string|null $param_key Optional. The actual key in $args array if different from $filter (e.g., 'search_radius' when $filter is 'radius')
     * @return bool        $allowed   True if the value is equal or smaller than the value from the settings page
     */
    public function check_allowed_filter_value( $args, $filter, $param_key = null ) {
        $wpsl_settings = $this->settings->get_group( 'search' );
        $allowed = false;

        // Use param_key if provided, otherwise use filter
        $args_key = $param_key ? $param_key : $filter;

        $max_filter_val = max( explode(',', str_replace( [ '[',']' ], '', $wpsl_settings[ $filter ] ) ) );

        if ( ( int ) $args[$args_key] <= ( int ) $max_filter_val ) {
            $allowed = true;
        }

        return $allowed;
    }

    /**
     * Get the default selected value for a dropdown.
     *
     * @since  1.0.0
     * @param  string $type     The request list type
     * @return string $response The default list value
     */
    public function get_default_filter_value( $type ) {
        $range = wpsl_get_filter_range( $type );

        return $range['default'];
    }
}