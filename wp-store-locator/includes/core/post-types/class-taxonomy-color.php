<?php
/**
 * Handle the color field for store categories.
 *
 * @author Tijmen Smit
 * @since  3.1.0
 */

namespace WPSL\Core\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomy_Color {

    /**
     * The term meta key the color is stored under.
     *
     * @since 3.1.0
     * @var string
     */
    const META_KEY = 'wpsl_category_color';

    /**
     * The color the picker opens on when a category has none yet.
     *
     * @since 3.1.0
     * @var string
     */
    const DEFAULT_COLOR = '#2271b1';

    /**
     * Constructor
     *
     * The form fields are hooked before the marker fields ( priority 10 ),
     * so the color sits above the "Enable category markers" toggle.
     *
     * @since 3.1.0
     */
    public function __construct() {
        add_action( 'wpsl_store_category_add_form_fields',      [ $this, 'add_category_color_field' ], 9 );
        add_action( 'wpsl_store_category_edit_form_fields',     [ $this, 'edit_category_color_field' ], 9 );
        add_action( 'created_wpsl_store_category',              [ $this, 'save_category_color' ] );
        add_action( 'edited_wpsl_store_category',               [ $this, 'save_category_color' ] );
        add_action( 'admin_enqueue_scripts',                    [ $this, 'enqueue_category_color_styles' ] );
        add_filter( 'manage_edit-wpsl_store_category_columns',  [ $this, 'add_category_color_column' ], 11 );
        add_filter( 'manage_wpsl_store_category_custom_column', [ $this, 'display_category_color_column' ], 10, 3 );
    }

    /**
     * Add the color field to the add term form.
     *
     * @since  3.1.0
     * @return void
     */
    public function add_category_color_field() {
        echo $this->render_color_toggle( false, 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output
        ?>
        <div class="form-field wpsl-category-color-field wpsl-hidden">
            <label for="wpsl-category-color"><?php esc_html_e( 'Color', 'wp-store-locator' ); ?></label>
            <?php echo $this->render_color_picker( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            <?php echo $this->render_show_categories_hint(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </div>
        <?php
    }

    /**
     * Add the color field to the edit term form.
     *
     * @since  3.1.0
     * @param  object $term Current taxonomy term object
     * @return void
     */
    public function edit_category_color_field( $term ) {
        $color        = wpsl_category_color( $term->term_id );
        $hidden_class = $color ? '' : ' wpsl-hidden';

        echo $this->render_color_toggle( (bool) $color, 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output
        ?>
        <tr class="form-field wpsl-category-color-field<?php echo esc_attr( $hidden_class ); ?>">
            <th scope="row">
                <label for="wpsl-category-color"><?php esc_html_e( 'Color', 'wp-store-locator' ); ?></label>
            </th>
            <td>
                <?php echo $this->render_color_picker( $color ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                <?php echo $this->render_show_categories_hint(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render the "enable category color" toggle field.
     *
     * @since  3.1.0
     * @param  bool   $enabled Whether the toggle should start checked.
     * @param  string $context 'add' for the add-term div layout, 'edit' for the
     *                         edit-term table-row layout.
     * @return string Toggle field HTML.
     */
    private function render_color_toggle( $enabled, $context ) {
        $checked = $enabled ? ' checked' : '';

        $field = '<input type="hidden" name="wpsl_category_color_fields" value="1" />'
               . '<input type="checkbox" class="wpsl-toggle-pending" id="wpsl-category-color-enabled" name="wpsl_category_color_enabled" value="1"' . $checked . ' />';

        $info = '<span class="wpsl-info"><span class="wpsl-info-text wpsl-hide">'
              . esc_html__( 'Shown as a dot in the category filter, and in the search results when "Show categories?" is on.', 'wp-store-locator' )
              . '</span></span>';

        $label = '<label class="wpsl-marker-toggle-text" for="wpsl-category-color-enabled">'
               . esc_html__( 'Enable category color', 'wp-store-locator' ) . $info . '</label> ';

        if ( 'edit' === $context ) {
            return '<tr class="form-field wpsl-category-color-toggle-row">'
                 . '<th scope="row">' . esc_html__( 'Category Color', 'wp-store-locator' ) . '</th>'
                 . '<td>' . $label . $field . '</td>'
                 . '</tr>';
        }

        return '<div class="form-field wpsl-category-color-toggle">' . $label . $field . '</div>';
    }

    /**
     * Render the color picker field.
     *
     * @since  3.1.0
     * @param  string $color The stored color, or '' when the category has none.
     * @return string Field HTML.
     */
    private function render_color_picker( $color ) {
        $presets = array_filter( array_map( 'sanitize_hex_color', wpsl_category_color_presets() ) );

        return '<input type="text" id="wpsl-category-color" class="wpsl-color-field" name="' . esc_attr( self::META_KEY ) . '" value="' . esc_attr( $color ) . '" data-default="' . esc_attr( self::DEFAULT_COLOR ) . '" data-presets="' . esc_attr( implode( ',', $presets ) ) . '" data-picker-position="below" />';
    }

    /**
     * Point to "Show categories?" while it's off.
     *
     * The color always shows in the category filter, but only in the search
     * results with that option on. Without this, a color seems to do nothing
     * there. Sits in the color field, so it shows only while a color is on.
     *
     * @since  3.1.0
     * @return string The hint, or '' when the categories are shown.
     */
    private function render_show_categories_hint() {
        if ( wpsl_get_service( 'wpsl_settings' )->get( 'appearance', 'categories.enabled' ) ) {
            return '';
        }

        // The hash opens the Search Results section, see restoreTabFromSession() in wpsl-appearance-editor.js.
        $link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance#wpsl-search-results-tab' ) ) . '">';

        /* translators: %1$s: opening link tag to the Appearance page, %2$s: closing link tag */
        $text = sprintf( esc_html__( 'The color shows in the category filter. To also show the categories in the search results, turn on "Show categories?" under Search Results on the %1$sAppearance page%2$s.', 'wp-store-locator' ), $link, '</a>' );

        return '<p class="description wpsl-category-color-hint">' . $text . '</p>';
    }

    /**
     * Save the category color.
     *
     * @since  3.1.0
     * @param  int $term_id Term ID
     * @return void
     */
    public function save_category_color( $term_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Term save nonce is handled by WordPress core.
        if ( ! isset( $_POST['wpsl_category_color_fields'] ) ) {
            return;
        }

        $color = '';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Term save nonce is handled by WordPress core.
        if ( isset( $_POST['wpsl_category_color_enabled'], $_POST[ self::META_KEY ] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Term save nonce is handled by WordPress core.
            $color = sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) ) );
        }

        if ( $color ) {
            $changed = (bool) update_term_meta( $term_id, self::META_KEY, $color );
        } else {
            $changed = delete_term_meta( $term_id, self::META_KEY );
        }

        // The cached search results hold the category markup, color included.
        if ( $changed ) {
            wpsl_flush_store_cache();
        }
    }

    /**
     * Enqueue the styles for the color field and the list table column.
     *
     * @since  3.1.0
     * @return void
     */
    public function enqueue_category_color_styles() {
        $screen = get_current_screen();

        if ( ! $screen || ( $screen->id !== 'edit-wpsl_store_category' && $screen->taxonomy !== 'wpsl_store_category' ) ) {
            return;
        }

        wp_add_inline_style( 'wp-admin', $this->get_color_field_styles() );
    }

    /**
     * Add the color column to the category list table, in front of the name.
     *
     * @since  3.1.0
     * @param  array $columns Existing columns
     * @return array Modified columns
     */
    public function add_category_color_column( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $value ) {
            if ( $key === 'name' ) {
                $new_columns['wpsl_color'] = esc_html__( 'Color', 'wp-store-locator' );
            }

            $new_columns[ $key ] = $value;
        }

        return $new_columns;
    }

    /**
     * Display the category color in the list table.
     *
     * @since  3.1.0
     * @param  string $content     Column content
     * @param  string $column_name Column name
     * @param  int    $term_id     Term ID
     * @return string Column content
     */
    public function display_category_color_column( $content, $column_name, $term_id ) {
        if ( $column_name === 'wpsl_color' ) {
            $color = wpsl_category_color( $term_id );

            if ( $color ) {
                $content = '<span class="wpsl-category-color-swatch" style="background-color:' . esc_attr( $color ) . '" aria-hidden="true"></span>'
                         . '<span class="screen-reader-text">' . esc_html( $color ) . '</span>';
            } else {
                $content = '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No color', 'wp-store-locator' ) . '</span>';
            }
        }

        return $content;
    }

    /**
     * Get the inline CSS for the color field and the list table column.
     *
     * @since  3.1.0
     * @return string CSS styles
     */
    private function get_color_field_styles() {
        // The swatch in the list table has the shape the dot has on the front end.
        $swatch_radius = ( wpsl_category_dot_shape() === 'square' ) ? '3px' : '50%';

        return '
            .wpsl-category-color-field.wpsl-hidden {
                display: none;
            }

            .wpsl-category-color-toggle .wpsl-info,
            .wpsl-category-color-toggle-row .wpsl-info {
                position: relative;
                display: inline-block;
                margin-inline-start: 4px;
                vertical-align: middle;
            }

            .wpsl-category-color-hint {
                max-width: 40em;
                margin-top: 8px;
            }

            /* The hex field in the color wheel is not a term form field. */
            .form-field .wpsl-color-picker-popup .wpsl-hex-input {
                width: 100%;
            }

            .column-wpsl_color {
                width: 60px;
            }

            .wpsl-category-color-swatch {
                display: inline-block;
                width: 16px;
                height: 16px;
                border-radius: ' . $swatch_radius . ';
                box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.2);
                vertical-align: middle;
            }
        ';
    }
}