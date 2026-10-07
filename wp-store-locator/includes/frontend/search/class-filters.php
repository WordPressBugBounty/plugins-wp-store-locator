<?php
/**
 * Handle the different dropdown / checkbox filters
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Search;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
use WPSL\Frontend\State\Manager as StateManager;
use WPSL\Frontend\Shortcodes\Shortcodes;
use WPSL\Core\Container;
use WPSL\Core\Utils\Location_Utils;

class Filters {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;
    
    /**
     * Translations instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;
    
    /**
     * State manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;
    
    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;
    
    /**
     * Location utils instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Utils\Location_Utils
     */
    private $location_utils;
    
    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager     $settings       Settings manager instance
     * @param \WPSL\Core\I18n\Translations    $i18n           Translations instance
     * @param \WPSL\Frontend\State\Manager    $state          State manager instance
     * @param \WPSL\Core\Container            $container      Container instance
     * @param \WPSL\Core\Utils\Location_Utils $location_utils Location utils instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n, StateManager $state, Container $container, Location_Utils $location_utils ) {
        $this->settings = $settings;
        $this->i18n = $i18n;
        $this->state = $state;
        $this->container = $container;
        $this->location_utils = $location_utils;
    }

    /**
     * Create the category filter.
     * 
     * @since      2.0.0
     * @deprecated 3.0.0 Use category_list() from Core\Templates\Filters instead
     * @return     string|void $category The HTML for the category dropdown, or nothing if no terms exist.
     */
    public function create_category_filter() {
        return wpsl_get_service( 'template_filters' )->category_list();
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
        $shortcodes = $this->container->get( 'shortcodes' );

        if ( isset( $shortcodes->atts['category_parent_id'] ) ) {
            $term = get_term_by( 'id', $shortcodes->atts['category_parent_id'], 'wpsl_store_category' );

            if ( $term && ! $term->parent ) {
                $parent_id = $shortcodes->atts['category_parent_id'];
            }
        }

        return $parent_id;
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
        $shortcodes = $this->container->get( 'shortcodes' );

        if ( ! empty( $shortcodes->atts['exclude_category'] ) ) {
            $exclude_ids = wpsl_get_term_ids( $shortcodes->atts['exclude_category'] );
        }

        return $exclude_ids;
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
        $shortcodes = $this->container->get( 'shortcodes' );

        if ( isset( $_REQUEST['wpsl-widget-categories'] ) ) {
            $selection = absint( $_REQUEST['wpsl-widget-categories'] );
        } else if ( isset( $shortcodes->atts['category_selection'] ) ) {
            $selection = $shortcodes->atts['category_selection'];
        } else if ( isset( $_REQUEST['wpsl_cat'] ) || isset( $_REQUEST['wpsl_category'] ) ) {
            // wpsl_category is the same parameter under the name the other URL parameters follow ( wpsl_address, wpsl_name ).
            $category_param = isset( $_REQUEST['wpsl_cat'] ) ? $_REQUEST['wpsl_cat'] : $_REQUEST['wpsl_category'];
            $selection      = wpsl_get_term_ids( sanitize_text_field( wp_unslash( $category_param ) ) );
        }

        return $selection;
    }

    /**
     * Set the selected category item.
     *
     * @since  2.1.2
     * @param  string           $filter_type The type of filter being used ( dropdown or checkbox )
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
                // The list is empty when the requested category doesn't exist.
                $selected_id = isset( $selection[0] ) ? $selection[0] : '';
            }
        } else {
            $selected_id = $selection;
        }

        if ( $selected_id ) {

            /**
             * Based on the filter type, either return the ID of the selected category,
             * or check if the checkbox needs to be set to checked="checked".
             */
            if ( $filter_type == 'dropdown' ) {
                return $selected_id;
            } else {
                return checked( $selected_id, $term->term_id, false );
            }
        }
    }

    /**
     * Create a dropdown or checkbox list that lists all unique values that
     * belong to the passed meta key.
     *
     * The field is named restrictions[<field>] by default, where <field> is the
     * meta key without the wpsl_ prefix. The search reads that name, and the
     * <field> => meta key pair has to be registered through the
     * wpsl_restriction_meta_fields filter ( city, state, country and iso are built in ).
     *
     * @since  3.0.0
     * @param  array  $args {
     *     Array of arguments for creating the meta filter.
     *
     *     @type string       $meta_key    Required. The custom field meta key to filter by.
     *     @type string       $type        Optional. Filter type: 'dropdown' or 'checkbox'. Default 'dropdown'.
     *     @type string       $label       Optional. Label text (only for dropdown type).
     *     @type string|array $selected    Optional. Pre-selected value, or values for the checkbox type.
     *     @type int          $columns     Optional. Number of columns (only for checkbox type, default: 3).
     *     @type string       $name        Optional. The name the value is sent under. Default restrictions[<field>].
     *     @type string|false $empty_label Optional. Text of the empty first option that clears the filter
     *                                     (only for dropdown type). Default 'Any', pass '' or false to leave it out.
     *     @type array        $labels      Optional. Option labels keyed by the stored value. Defaults to the options
     *                                     of the matching Fields Manager dropdown field, else the stored value.
     *     @type string       $search_type Optional. Route the checkbox values through a built-in search type ( country or state ).
     * }
     * @return string $filter The HTML markup for the filter.
     */
    public function create_meta_filter( $args ) {
        $args = wp_parse_args( $args, [
            'meta_key'    => '',
            'type'        => 'dropdown',
            'label'       => '',
            'selected'    => '',
            'columns'     => 3,
            'name'        => '',
            'empty_label' => null,
            'labels'      => [],
            'search_type' => '',
        ] );

        $meta_key = sanitize_key( $args['meta_key'] );

        if ( '' === $meta_key ) {
            return '';
        }

        $meta_values = $this->location_utils->get_unique_meta_values( $meta_key );

        if ( ! $meta_values ) {
            return '';
        }

        $name     = ( '' !== $args['name'] ) ? $args['name'] : 'restrictions[' . preg_replace( '/^wpsl_/', '', $meta_key ) . ']';
        $labels   = ( is_array( $args['labels'] ) && $args['labels'] ) ? $args['labels'] : $this->get_meta_value_labels( $meta_key );
        $selected = array_map( 'strval', (array) $args['selected'] );
        $filter   = '';

        if ( 'dropdown' === $args['type'] ) {
            $filter = "\t\t\t\t" . '<div class="wpsl-custom-filter">' . "\r\n";

            if ( $args['label'] ) {
                $filter .= "\t\t\t\t\t" . '<label for="' . esc_attr( $meta_key ) . '">' . esc_html( $args['label'] ) . '</label>' . "\r\n";
            }

            $filter .= "\t\t\t\t\t" . '<select id="' . esc_attr( $meta_key ) . '" class="wpsl-dropdown wpsl-custom-dropdown" name="' . esc_attr( $name ) . '">';

            $empty_label = ( null === $args['empty_label'] ) ? __( 'Any', 'wp-store-locator' ) : $args['empty_label'];

            if ( $empty_label ) {
                $filter .= "\t\t\t\t\t\t" . '<option value="">' . esc_html( $empty_label ) . '</option>';
            }

            foreach ( $meta_values as $meta_value ) {
                $is_selected = in_array( (string) $meta_value, $selected, true ) ? ' selected="selected"' : '';
                $option_text = isset( $labels[ $meta_value ] ) ? $labels[ $meta_value ] : $meta_value;

                $filter .= "\t\t\t\t\t\t" . '<option value="' . esc_attr( $meta_value ) . '"' . $is_selected . '>' . esc_html( $option_text ) . '</option>';
            }

            $filter .= '</select>';
            $filter .= '</div>';
        } else if ( 'checkbox' === $args['type'] ) {
            $columns = absint( $args['columns'] ) ? absint( $args['columns'] ) : 3;

            /*
             * Not the wpsl-checkbox-filter id: the JS reads the checked boxes in
             * that list as category ids, and the category filter already uses it.
             * The data-name is where the checked values are sent to.
             */
            $filter  = '<ul id="wpsl-checkbox-' . esc_attr( $meta_key ) . '" class="wpsl-custom-checkboxes wpsl-checkbox-' . $columns . '-columns" data-name="' . esc_attr( $name ) . '"';

            if ( $args['search_type'] ) {
                $filter .= ' data-search-type="' . esc_attr( $args['search_type'] ) . '"';
            }

            $filter .= '>';

            foreach ( $meta_values as $meta_value ) {
                $is_checked  = in_array( (string) $meta_value, $selected, true ) ? 'checked="checked"' : '';
                $option_text = isset( $labels[ $meta_value ] ) ? $labels[ $meta_value ] : $meta_value;

                $filter .= '<li>';
                $filter .= '<label>';
                $filter .= '<input type="checkbox" value="' . esc_attr( $meta_value ) . '" ' . $is_checked . ' />';
                $filter .= esc_html( $option_text );
                $filter .= '</label>';
                $filter .= '</li>';
            }

            $filter .= '</ul>';
        }

        return $filter;
    }

    /**
     * The option labels of a Fields Manager dropdown field.
     *
     * A dropdown field saves the option as a lowercase slug ( finedining ), so
     * the stored value alone would print as "finedining" instead of "Fine dining".
     *
     * @since  3.1.0
     * @param  string $meta_key The custom field meta key, for example wpsl_cuisine.
     * @return array  The option labels keyed by the stored value, empty when the key isn't a dropdown field.
     */
    private function get_meta_value_labels( $meta_key ) {
        if ( ! $this->container->has( 'store_fields' ) ) {
            return [];
        }

        $field_name = preg_replace( '/^wpsl_/', '', $meta_key );

        foreach ( $this->container->get( 'store_fields' )->get_custom_field_names( [], true ) as $group_fields ) {
            if ( isset( $group_fields[ $field_name ]['options'] ) && is_array( $group_fields[ $field_name ]['options'] ) ) {
                return array_map( 'trim', $group_fields[ $field_name ]['options'] );
            }
        }

        return [];
    }

    /**
     * Create a dropdown list holding the search radius or
     * max search results options.
     *
     * @since  1.0.0
     * @param  string $list_type     The name of the list we need to load data for
     * @return string $dropdown_list A list with the available options for the dropdown list
     */
    public function get_dropdown_list( $list_type ) {
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
}