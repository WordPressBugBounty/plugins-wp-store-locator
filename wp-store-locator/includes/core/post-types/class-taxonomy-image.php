<?php
/**
 * Handle taxonomy image fields for store categories.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomy_Image {

    /**
     * Constructor
     * 
     * @since 3.0.0
     */
    public function __construct() {
        add_action( 'wpsl_store_category_add_form_fields',      [ $this, 'add_category_image_field' ], 10, 2 );
        add_action( 'wpsl_store_category_edit_form_fields',     [ $this, 'edit_category_image_field' ], 10, 2 );
        add_action( 'created_wpsl_store_category',              [ $this, 'save_category_image' ], 10, 2 );
        add_action( 'edited_wpsl_store_category',               [ $this, 'save_category_image' ], 10, 2 );
        add_action( 'delete_wpsl_store_category',               [ $this, 'delete_category_image' ], 10, 4 );
        add_action( 'admin_enqueue_scripts',                    [ $this, 'enqueue_category_image_scripts' ] );
        add_filter( 'manage_edit-wpsl_store_category_columns',  [ $this, 'add_category_image_column' ] );
        add_filter( 'manage_wpsl_store_category_custom_column', [ $this, 'display_category_image_column' ], 10, 3 );
    }

    /**
     * Add category image field to the add term form.
     *
     * @since  3.0.0
     * @param  string $taxonomy The taxonomy slug
     * @return void
     */
    public function add_category_image_field( $taxonomy ) {
        $default_marker = $this->get_default_marker( 'store' );
        $default_active_marker = $this->get_default_marker( 'active' );
        ?>

        <?php echo $this->render_marker_toggle( false, 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>

        <div class="form-field term-image-wrap wpsl-category-marker-field wpsl-hidden">
            <label for="wpsl-category-marker"><?php esc_html_e( 'Category Marker', 'wp-store-locator' ); ?></label>
            <?php echo $this->render_marker_dropdown( 'normal', $default_marker, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </div>

        <div class="form-field term-image-wrap wpsl-category-marker-field wpsl-hidden">
            <label for="wpsl-category-marker-active"><?php esc_html_e( 'Active Category Marker', 'wp-store-locator' ); ?></label>
            <?php echo $this->render_marker_dropdown( 'active', $default_active_marker, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        </div>
        <?php
    }

    /**
     * Add category image field to the edit term form.
     *
     * @since  3.0.0
     * @param  object $term     Current taxonomy term object
     * @param  string $taxonomy Current taxonomy slug
     * @return void
     */
    public function edit_category_image_field( $term, $taxonomy ) {
        $selected_marker = $this->get_stored_marker( $term->term_id, 'store' );
        if ( ! $selected_marker ) {
            $selected_marker = $this->get_default_marker( 'store' );
        }

        $selected_active_marker = $this->get_stored_marker( $term->term_id, 'active' );
        if ( ! $selected_active_marker ) {
            $selected_active_marker = $this->get_default_marker( 'active' );
        }

        $enabled      = $this->is_marker_enabled( $term->term_id );
        $hidden_class = $enabled ? '' : ' wpsl-hidden';
        ?>

        <?php echo $this->render_marker_toggle( $enabled, 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>

        <tr class="form-field term-image-wrap wpsl-category-marker-field<?php echo esc_attr( $hidden_class ); ?>">
            <th scope="row">
                <label for="wpsl-category-marker-normal"><?php esc_html_e( 'Category Marker', 'wp-store-locator' ); ?></label>
            </th>
            <td>
                <?php echo $this->render_marker_dropdown( 'normal', $selected_marker, $term->term_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </td>
        </tr>

        <tr class="form-field term-image-wrap wpsl-category-marker-field<?php echo esc_attr( $hidden_class ); ?>">
            <th scope="row">
                <label for="wpsl-category-marker-active"><?php esc_html_e( 'Active Category Marker', 'wp-store-locator' ); ?></label>
            </th>
            <td>
                <?php echo $this->render_marker_dropdown( 'active', $selected_active_marker, $term->term_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Determine whether a category has marker(s) enabled.
     *
     * A stored value that no longer resolves (deleted Studio marker, dropped
     * color) doesn't count - the front end already falls back to the settings
     * marker, so reading bare meta as "enabled" would claim a marker the map
     * isn't using and the toggle can't show.
     *
     * @since  3.0.0
     * @param  int $term_id Term ID.
     * @return bool True when the term has a normal or active marker that resolves.
     */
    private function is_marker_enabled( $term_id ) {
        foreach ( [ 'store', 'active' ] as $type ) {
            $value = get_term_meta( $term_id, wpsl_category_marker_key( $type ), true );

            if ( $value && wpsl_marker_src( $value ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The marker a category has been given, in the form the settings store one.
     *
     * @since  3.0.0
     * @param  int    $term_id Term id.
     * @param  string $type    "store" or "active".
     * @return string The marker value, or '' when the category has none.
     */
    private function get_stored_marker( $term_id, $type ) {
        return wpsl_category_marker( $term_id, $type );
    }

    /**
     * Where a category's marker value is stored.
     *
     * @since  3.0.0
     * @param  string $type "store" or "active".
     * @return string
     */
    private function marker_meta_key( $type ) {
        return wpsl_category_marker_key( $type );
    }

    /**
     * Save category image field data.
     *
     * @since  3.0.0
     * @param  int $term_id Term ID
     * @param  int $tt_id   Term taxonomy ID
     * @return void
     */
    public function save_category_image( $term_id, $tt_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Term save nonce is handled by WordPress core.
        $enabled = isset( $_POST['wpsl_category_markers_enabled'] );
        $changed = false;

        foreach ( [ 'store', 'active' ] as $type ) {
            if ( $enabled ) {
                $moved = $this->save_marker_value( $term_id, $type );
            } else {
                // Markers switched off: whatever was stored goes.
                $moved = delete_term_meta( $term_id, $this->marker_meta_key( $type ) );
            }

            $changed = $changed || $moved;
        }

        // Clear the category images cache
        delete_transient( 'wpsl_has_category_images' );

        /*
         * Only a real change gets here. The term screen resubmits the stored
         * marker through a hidden input, so most saves move nothing, and a day
         * of cached results is too much to spend on those.
         */
        if ( $changed ) {
            wpsl_flush_store_cache();
        }
    }

    /**
     * Store one of a category's two markers from the submitted form.
     *
     * The value is stored as chosen (filename or "custom:{id}").
     * wpsl_sanitize_marker_value() (the same gate the settings use) returns
     * empty for a filename not on disk or a custom id that no longer resolves,
     * and the meta is removed rather than pointed at something that won't draw.
     *
     * @since  3.0.0
     * @param  int    $term_id Term id.
     * @param  string $type    "store" or "active".
     * @return bool            True when the stored marker changed.
     */
    private function save_marker_value( $term_id, $type ) {
        $field = $this->marker_meta_key( $type );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Term save nonce is handled by WordPress core.
        if ( ! isset( $_POST[ $field ] ) ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by wpsl_sanitize_marker_value().
        $value = wpsl_sanitize_marker_value( wp_unslash( $_POST[ $field ] ) );

        if ( $value ) {
            return (bool) update_term_meta( $term_id, $field, $value );
        }

        return (bool) delete_term_meta( $term_id, $field );
    }

    /**
     * Delete category image data when term is deleted.
     *
     * @since  3.0.0
     * @param  int    $term_id      Term ID
     * @param  int    $tt_id        Term taxonomy ID
     * @param  string $taxonomy     Taxonomy slug
     * @param  object $deleted_term Copy of the already-deleted term
     * @return void
     */
    public function delete_category_image( $term_id, $tt_id, $taxonomy, $deleted_term ) {
        delete_transient( 'wpsl_has_category_images' );
        wpsl_flush_store_cache();
    }

    /**
     * Enqueue styles for category marker dropdowns.
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_category_image_scripts() {
        $screen = get_current_screen();
        
        if ( ! $screen || ( $screen->id !== 'edit-wpsl_store_category' && $screen->taxonomy !== 'wpsl_store_category' ) ) {
            return;
        }

        wp_add_inline_style( 'wp-admin', $this->get_marker_dropdown_styles() );
    }

    /**
     * Add image column to category list table.
     *
     * @since  3.0.0
     * @param  array $columns Existing columns
     * @return array Modified columns
     */
    public function add_category_image_column( $columns ) {
        $new_columns = [];
        
        // Add image column after checkbox
        foreach ( $columns as $key => $value ) {
            $new_columns[$key] = $value;
            
            if ( $key === 'cb' ) {
                $new_columns['image'] = esc_html__( 'Image', 'wp-store-locator' );
            }
        }
        
        return $new_columns;
    }

    /**
     * Display category image in the list table.
     *
     * @since  3.0.0
     * @param  string $content     Column content
     * @param  string $column_name Column name
     * @param  int    $term_id     Term ID
     * @return string Column content
     */
    public function display_category_image_column( $content, $column_name, $term_id ) {
        if ( $column_name === 'image' ) {
            $src = wpsl_category_marker_src( $term_id, 'store' );

            if ( $src ) {
                /*
                 * esc_attr, not esc_url: a Marker Studio marker resolves to an
                 * inline SVG data URI, and data: is not in wp_allowed_protocols(),
                 * so esc_url() empties it. Same escaping as the settings marker
                 * picker, which draws the same values.
                 */
                $content = '<img class="wpsl-category-marker-thumb" src="' . esc_attr( $src ) . '" alt="" />';
            }
        }

        return $content;
    }

    /**
     * Render marker dropdown HTML.
     *
     * @since  3.0.0
     * @param  string $type          Type of marker (normal or active)
     * @param  string $selected      Currently selected marker filename
     * @param  int    $term_id       Term ID (0 for new terms)
     * @return string Dropdown HTML
     */
    private function render_marker_dropdown( $type, $selected, $term_id = 0 ) {
        $options    = $this->get_marker_options();
        $field_name = $this->marker_meta_key( $type );
        $field_id   = 'wpsl-marker-dropdown-' . $type;

        if ( empty( $options ) ) {
            return '';
        }

        if ( ! $selected || ! isset( $options[ $selected ] ) ) {
            $selected = $this->get_default_marker( 'active' === $type ? 'active' : 'store' );
        }

        if ( ! $selected || ! isset( $options[ $selected ] ) ) {
            $selected = key( $options );
        }

        ob_start();
        ?>
        <div class="wpsl-marker-dropdown-wrapper" data-type="<?php echo esc_attr( $type ); ?>">
            <button type="button" class="wpsl-marker-dropdown-button" id="<?php echo esc_attr( $field_id ); ?>-button">
                <div class="wpsl-marker-dropdown-selected">
                    <?php
                    /*
                     * esc_attr on every src below, not esc_url: a Marker Studio
                     * marker resolves to an inline SVG data URI, and data: is
                     * not in wp_allowed_protocols(), so esc_url() returns an
                     * empty string and every custom marker draws as nothing.
                     * The settings picker escapes the same values the same way.
                     */
                    ?>
                    <img src="<?php echo esc_attr( $options[ $selected ]['src'] ); ?>"<?php echo wpsl_marker_preview_style( $selected ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?> alt="<?php echo esc_attr( $options[ $selected ]['name'] ); ?>" />
                </div>
                <div class="wpsl-marker-dropdown-arrow"></div>
            </button>

            <div class="wpsl-marker-dropdown-menu" id="<?php echo esc_attr( $field_id ); ?>-menu">
                <?php foreach ( $options as $value => $option ) : ?>
                    <div class="wpsl-marker-dropdown-item<?php echo $selected === $value ? ' selected' : ''; ?>" data-marker="<?php echo esc_attr( $value ); ?>" title="<?php echo esc_attr( $option['name'] ); ?>">
                        <?php
                        /*
                         * Capped by height, raised per shape for a custom
                         * marker so its silhouette matches the bundled ones --
                         * see wpsl_marker_preview_cap(). A fixed box would
                         * scale the artwork box instead, transparent margin and
                         * all, which is what drew these visibly smaller.
                         */
                        ?>
                        <img src="<?php echo esc_attr( $option['src'] ); ?>"<?php echo wpsl_marker_preview_style( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?> alt="<?php echo esc_attr( $option['name'] ); ?>" />
                    </div>
                <?php endforeach; ?>
                <a class="wpsl-marker-dropdown-add" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' ) ); ?>" title="<?php esc_attr_e( 'Create Custom Marker', 'wp-store-locator' ); ?>">
                    <span aria-hidden="true">+</span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Create Custom Marker', 'wp-store-locator' ); ?></span>
                </a>
            </div>

            <input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $selected ); ?>" />
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * The markers a category can be given, keyed by the value that is stored.
     *
     * The same set the settings picker and the store metabox offer, from the
     * same place, so a marker that can be the site default can also be a
     * category's. This screen used to list the bundled markers only, from a
     * copy of the directory scan of its own.
     *
     * @since  3.0.0
     * @return array [ 'name', 'src' ] keyed by marker value.
     */
    private function get_marker_options() {
        $pickable = wpsl_pickable_markers();
        $options  = [];

        foreach ( $pickable['bundled'] as $filename => $name ) {
            $options[ $filename ] = [
                'name' => $name,
                'src'  => wpsl_marker_src( $filename ),
            ];
        }

        foreach ( $pickable['custom'] as $value => $marker ) {
            $options[ $value ] = [
                'name' => $marker['name'],
                'src'  => $marker['src'],
            ];
        }

        return $options;
    }

    /**
     * Render the "enable category markers" toggle field.
     *
     * The bare checkbox is converted to the shared slider UI in JS via
     * wpslSharedFuncs.createToggleSliders(), matching every other WPSL toggle.
     *
     * @since  3.0.0
     * @param  bool   $enabled Whether the toggle should start checked.
     * @param  string $context 'add' for the add-term div layout, 'edit' for the
     *                         edit-term table-row layout.
     * @return string Toggle field HTML.
     */
    private function render_marker_toggle( $enabled, $context ) {
        $checked = $enabled ? ' checked' : '';

        /*
         * wpsl-toggle-pending hides the bare checkbox until
         * setupEnableToggle() builds the slider around it (see style.css).
         */
        $field = '<input type="checkbox" class="wpsl-toggle-pending" id="wpsl-category-markers-enabled" name="wpsl_category_markers_enabled" value="1"' . $checked . ' />';

        if ( 'edit' === $context ) {
            return '<tr class="form-field wpsl-category-markers-toggle-row">'
                 . '<th scope="row">' . esc_html__( 'Category Markers', 'wp-store-locator' ) . '</th>'
                 . '<td><label class="wpsl-marker-toggle-text" for="wpsl-category-markers-enabled">'
                 . esc_html__( 'Enable category markers', 'wp-store-locator' ) . '</label> ' . $field . '</td>'
                 . '</tr>';
        }

        return '<div class="form-field wpsl-category-markers-toggle">'
             . '<label class="wpsl-marker-toggle-text" for="wpsl-category-markers-enabled">'
             . esc_html__( 'Enable category markers', 'wp-store-locator' ) . '</label> ' . $field
             . '</div>';
    }

    /**
     * Get inline CSS for marker dropdown.
     *
     * @since  3.0.0
     * @return string CSS styles
     */
    private function get_marker_dropdown_styles() {
        return '
            .wpsl-marker-dropdown-wrapper {
                position: relative;
                display: inline-block;
            }

            .wpsl-category-marker-field.wpsl-hidden {
                display: none;
            }

            .wpsl-marker-dropdown-button {
                min-width: 68px;
                background: #fff;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                padding: 8px 12px;
                display: inline-flex;
                align-items: center;
                cursor: pointer;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                transition: border-color 0.2s;
            }
            
            .wpsl-marker-dropdown-button:hover {
                border-color: #2271b1;
            }
            
            .wpsl-marker-dropdown-selected {
                display: flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 52px;
            }
            
            .wpsl-marker-dropdown-selected img {
                width: auto;
                height: auto;
                max-width: 52px;
                max-height: 34px;
            }
            
            .wpsl-marker-dropdown-selected span {
                color: #1d2327;
                font-size: 13px;
            }
            
            .wpsl-marker-dropdown-placeholder {
                color: #646970;
            }
            
            .wpsl-marker-dropdown-arrow {
                width: 6px;
                height: 6px;
                border-left: 2px solid #646970;
                border-bottom: 2px solid #646970;
                transform: rotate(-45deg);
                margin-left: 8px;
            }
            
            .wpsl-marker-dropdown-menu {
                position: absolute;
                bottom: calc(100% + 4px);
                top: auto;
                left: 0;
                min-width: 100%;
                background: #fff;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.15);
                max-height: 300px;
                overflow-y: auto;
                scrollbar-gutter: stable;
                display: none;
                z-index: 1000;
            }
            
            .wpsl-marker-dropdown-menu.show {
                display: block;
            }

            .wpsl-marker-dropdown-item {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 8px 12px;
                border-bottom: 1px solid #f0f0f1;
                cursor: pointer;
                transition: background 0.2s;
            }
            
            .wpsl-marker-dropdown-item:last-child {
                border-bottom: none;
            }
            
            .wpsl-marker-dropdown-item:hover,
            .wpsl-marker-dropdown-item.selected {
                background: #f6f7f7;
            }
            
            .wpsl-marker-dropdown-item img {
                width: auto;
                height: auto;
                max-width: 52px;
                max-height: 34px;
            }

            .wpsl-marker-dropdown-add {
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 8px 12px;
                min-height: 44px;
                border: 1px dashed #c3c4c7;
                border-radius: 5px;
                color: #646970;
                font-size: 18px;
                line-height: 1;
                text-decoration: none;
            }

            .wpsl-marker-dropdown-add:hover,
            .wpsl-marker-dropdown-add:focus {
                border-color: #2271b1;
                color: #2271b1;
            }

            .wpsl-category-marker-thumb {
                width: 24px;
                height: 32px;
                object-fit: contain;
            }
        ';
    }

    /**
     * Get default marker from WPSL settings.
     *
     * @since  3.0.0
     * @param  string $type Marker type ('store' or 'active')
     * @return string Marker filename
     */
    private function get_default_marker( $type ) {
        $settings = wpsl_get_service( 'wpsl_settings' )->get_all();
        
        if ( ! $settings || ! isset( $settings['markers'] ) ) {
            return '';
        }
        
        $marker_key = $type . '_marker';
        
        return isset( $settings['markers'][$marker_key] ) ? $settings['markers'][$marker_key] : '';
    }
}