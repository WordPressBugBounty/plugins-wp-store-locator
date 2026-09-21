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
        } else if ( isset( $_REQUEST['wpsl_cat'] ) ) {
            $selection = wpsl_get_term_ids( sanitize_text_field( wp_unslash( $_REQUEST['wpsl_cat'] ) ) );
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
                $selected_id = $selection[0];
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
     * Create a dropdown that lists all unique values that
     * belong to the passed meta key.
     *
     * @since  3.0.0
     * @param  array  $args {
     *     Array of arguments for creating the meta filter.
     *
     *     @type string $meta_key  Required. The custom field meta key to filter by.
     *     @type string $type      Required. Filter type: 'dropdown' or 'checkbox'.
     *     @type string $label     Optional. Label text (only for dropdown type).
     *     @type string $selected  Optional. Pre-selected value.
     *     @type int    $columns   Optional. Number of columns (only for checkbox type, default: 3).
     * }
     * @return string $filter The HTML markup for the filter.
     */
    public function create_meta_filter( $args ) {
        $filter      = '';
        $columns     = 3;
        $meta_values = $this->location_utils->get_unique_meta_values( $args['meta_key'] );

        if ( $meta_values ) {
            if ( $args['type'] == 'dropdown' ) {
                $filter = "\t\t\t\t" . '<div id="'. esc_attr( $args['meta_key'] ) . '">' . "\r\n";

                if ( $args['label'] ) {
                    $filter .= "\t\t\t\t\t" . '<label for="'. esc_attr( $args['meta_key'] ) . '">' . esc_html( $args['label'] ) . '</label>' . "\r\n";
                }

                $filter .= "\t\t\t\t\t" . '<select id="'. esc_attr( $args['meta_key'] ) . '" class="wpsl-dropdown wpsl-custom-dropdown" name="'. esc_attr( $args['meta_key'] ) . '">';

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

                $filter = '<ul id="wpsl-checkbox-filter" class="wpsl-custom-checkboxes wpsl-checkbox-' . $columns . '-columns">';

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