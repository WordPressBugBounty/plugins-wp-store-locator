<?php
/**
 * Category dropdown walker.
 *
 * @author Tijmen Smit
 * @since  3.1.0
 */

namespace WPSL\Core\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds the category color to the options of the category dropdown.
 *
 * An <option> can't hold the dot itself, so the color travels as a
 * data-color attribute. wpsl-dropdowns.js reads it when it builds the styled
 * dropdown from the select.
 *
 * @since 3.1.0
 */
class Category_Dropdown_Walker extends \Walker_CategoryDropdown {

    /**
     * Start the element output.
     *
     * @since  3.1.0
     * @param  string   $output            Used to append additional content (passed by reference).
     * @param  \WP_Term $data_object       Category data object.
     * @param  int      $depth             Depth of category. Used for padding.
     * @param  array    $args              Uses 'selected', 'show_count', and 'value_field' keys, if they exist.
     * @param  int      $current_object_id Optional. ID of the current category. Default 0.
     * @return void
     */
    public function start_el( &$output, $data_object, $depth = 0, $args = [], $current_object_id = 0 ) {
        $start = strlen( $output );

        parent::start_el( $output, $data_object, $depth, $args, $current_object_id );

        $color = wpsl_category_color( $data_object->term_id );

        if ( ! $color ) {
            return;
        }

        $option = preg_replace( '/<option /', '<option data-color="' . esc_attr( $color ) . '" ', substr( $output, $start ), 1 );

        $output = substr( $output, 0, $start ) . $option;
    }
}