<?php
/**
 * Shortcode Generator class
 *
 * @author Tijmen Smit
 * @since  2.2.10
 */

namespace WPSL\Admin\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Admin\Settings\Marker_Manager;
use WPSL\Admin\Settings\UI;

/**
 * Handle the generation of the WPSL shortcode through the media button
 *
 * @since 2.2.10
 */
class Shortcode_Generator {

    /**
     * Settings object.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    public $settings;

    /**
     * Marker Manager object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\Marker_Manager
     */
    public $marker_manager;

    /**
     * UI object.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Settings\UI
     */
    public $ui;

    /**
     * Constructor
     *
     * @since 2.2.10
     * @param \WPSL\Core\Settings\Manager       $settings        Settings manager instance
     * @param \WPSL\Admin\Settings\Marker_Manager $marker_manager  Marker manager instance
     * @param \WPSL\Admin\Settings\UI           $ui              UI helper instance
     */
    public function __construct( WpslSettings $settings, Marker_Manager $marker_manager, UI $ui ) {
        $this->settings = $settings;
        $this->marker_manager = $marker_manager;
        $this->ui = $ui;

        add_action( 'media_buttons',         [ $this, 'add_wpsl_media_button' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_dialog_assets' ] );
        add_action( 'admin_footer',          [ $this, 'show_dialog_content' ] );
    }

    /**
     * Whether the current screen carries the media button and its dialog.
     *
     * @since 3.0.0
     * @return bool
     */
    private function is_editor_screen() {
        global $pagenow, $typenow;

        /* Make sure we're on a post/page or edit screen in the admin area */
        return in_array( $pagenow, [ 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ] ) && $typenow != 'wpsl_stores';
    }

    /**
     * Add the WPSL media button to the media button row
     *
     * @since 2.2.10
     * @return void
     */
    public function add_wpsl_media_button() {
        if ( $this->is_editor_screen() ) {
            echo '<a href="#" id="wpsl-open-shortcode-dialog" class="button wpsl-media-button">' . esc_html__( 'Insert Store Locator', 'wp-store-locator' ) . '</a>';
        }
    }

    /**
     * Load the dialog assets on the screens that show the media button.
     *
     * @since 3.0.0
     * @return void
     */
    public function enqueue_dialog_assets() {
        if ( ! $this->is_editor_screen() ) {
            return;
        }

        $script_debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );

        // The JS is minified next to its source, the CSS into the dist folder.
        $min      = $script_debug ? '' : '.min';
        $css_base = $script_debug ? 'assets/src/' : 'assets/dist/';
        $css_ext  = $script_debug ? '.css' : '.min.css';

        wp_enqueue_script( 'wpsl-shared-funcs', WPSL_URL . 'assets/src/admin/js/wpsl-shared-funcs' . $min . '.js', [ 'jquery' ], WPSL_VERSION_NUM, true );
        wp_enqueue_script( 'wpsl-shortcode-generator', plugins_url( 'assets/src/admin/js/wpsl-shortcode-generator' . $min . '.js', WPSL_PLUGIN_FILE ), [ 'jquery', 'wpsl-shared-funcs', 'jquery-ui-dialog', 'jquery-ui-tabs' ], WPSL_VERSION_NUM, true );

        // The dialog frame, field rows, toggle slider and marker picker
        // styling, plus the fontello icon on the media button.
        wp_enqueue_style( 'wpsl-fontello', WPSL_URL . $css_base . 'admin/css/fontello' . $css_ext, false, WPSL_VERSION_NUM );
        wp_enqueue_style( 'wpsl-admin', WPSL_URL . $css_base . 'admin/css/style' . $css_ext, false, WPSL_VERSION_NUM );

        wp_enqueue_style( 'wp-jquery-ui-dialog' );

        wpsl_enqueue_library( 'wpsl-jquery-ui-theme', 'jquery_ui_theme_css' );
    }

    /**
     * Print the shortcode dialog markup on the editor screens.
     *
     * @since 2.2.10 As the thickbox iframe endpoint; rebuilt as an in-page dialog in 3.0.0.
     * @return void
     */
    public function show_dialog_content() {
        if ( ! $this->is_editor_screen() || ! current_user_can( 'edit_pages' ) ) {
            return;
        }
        ?>
        <div id="wpsl-shortcode-dialog" class="wpsl-hide" title="<?php esc_attr_e( 'Insert Store Locator', 'wp-store-locator' ); ?>">
            <div id="wpsl-shortcode-tabs">
                <ul>
                    <li><a href="#wpsl-sc-tab-general"><?php esc_html_e( 'General Options', 'wp-store-locator' ); ?></a></li>
                    <li><a href="#wpsl-sc-tab-markers"><?php esc_html_e( 'Markers', 'wp-store-locator' ); ?></a></li>
                </ul>
                <div id="wpsl-sc-tab-general">
                    <p>
                        <label for="wpsl-store-template"><?php esc_html_e( 'Theme', 'wp-store-locator' ); ?></label>
                        <?php echo $this->ui->show_template_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                    </p>
                    <p>
                        <label for="wpsl-start-location"><?php esc_html_e( 'Start point', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to settings page, %2$s: closing link tag */ echo sprintf( esc_html__( 'If nothing it set, then the start point from the %1$ssettings%2$s page is used.', 'wp-store-locator' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-map-settings' ) ) . '" target="_blank">', '</a>' ); ?></span></span></label>
                        <input type="text" placeholder="<?php esc_attr_e( 'Optional', 'wp-store-locator' ); ?>" value="" id="wpsl-start-location">
                    </p>
                    <p>
                        <label for="wpsl-auto-locate"><?php esc_html_e( 'Attempt to auto-locate the user', 'wp-store-locator' ); ?><span class="wpsl-info <?php if ( ! wpsl_get_service( 'system_utils' )->ssl_active() ) { echo 'wpsl-warning'; } ?>"><?php /* translators: %1$s: opening link tag to documentation, %2$s: closing link tag */ ?><span class="wpsl-info-text wpsl-hide"><?php echo wp_kses_post( sprintf( __( 'A HTTPS connection is %1$srequired%2$s before the Geolocation API can access the user\'s location.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/html-5-geolocation-not-working/" target="_blank">', '</a>' ) ); ?></span></span></label>
                        <input type="checkbox" value="" <?php checked( $this->settings->get( 'map', 'auto_locate' ), true ); ?> name="wpsl_map[auto_locate]" id="wpsl-auto-locate">
                    </p>
                    <p>
                        <label for="wpsl-restrict-country"><?php esc_html_e( 'Country', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Restricts the search results to stores in this country. Combine it with the state and city fields for a narrower area.', 'wp-store-locator' ); ?></span></span></label>
                        <input type="text" placeholder="<?php esc_attr_e( 'Optional', 'wp-store-locator' ); ?>" value="" id="wpsl-restrict-country">
                    </p>
                    <p>
                        <label for="wpsl-restrict-state"><?php esc_html_e( 'State', 'wp-store-locator' ); ?></label>
                        <input type="text" placeholder="<?php esc_attr_e( 'Optional', 'wp-store-locator' ); ?>" value="" id="wpsl-restrict-state">
                    </p>
                    <p>
                        <label for="wpsl-restrict-city"><?php esc_html_e( 'City', 'wp-store-locator' ); ?></label>
                        <input type="text" placeholder="<?php esc_attr_e( 'Optional', 'wp-store-locator' ); ?>" value="" id="wpsl-restrict-city">
                    </p>
                    <p>
                        <label for="wpsl-distance-unit"><?php esc_html_e( 'Distance unit', 'wp-store-locator' ); ?></label>
                        <select id="wpsl-distance-unit" autocomplete="off">
                            <option value="" selected="selected"><?php esc_html_e( 'Default (from settings)', 'wp-store-locator' ); ?></option>
                            <option value="km"><?php esc_html_e( 'km', 'wp-store-locator' ); ?></option>
                            <option value="mi"><?php esc_html_e( 'mi', 'wp-store-locator' ); ?></option>
                        </select>
                    </p>
                    <?php
                    $terms = get_terms( [
                        'taxonomy'   => 'wpsl_store_category',
                        'hide_empty' => true,
                    ] );

                    if ( $terms ) {
                        ?>
                        <p>
                            <label for="wpsl-cat-filter-types"><?php esc_html_e( 'Category filter type', 'wp-store-locator' ); ?></label>
                            <select id="wpsl-cat-filter-types" autocomplete="off">
                                <option value="" selected="selected"><?php esc_html_e( 'None', 'wp-store-locator' ); ?></option>
                                <option value="dropdown"><?php esc_html_e( 'Dropdown', 'wp-store-locator' ); ?></option>
                                <option value="checkboxes"><?php esc_html_e( 'Checkboxes', 'wp-store-locator' ); ?></option>
                            </select>
                        </p>
                        <p class="wpsl-cat-restriction">
                            <label for="wpsl-cat-restriction"><?php esc_html_e( 'Restrict to categories', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Automatically restrict the returned results to one or more categories.', 'wp-store-locator' ); ?></span></span></label>
                            <?php
                            $cat_restricton = '<select id="wpsl-cat-restriction" multiple="multiple" autocomplete="off">';

                            foreach ( $terms as $term ) {
                                $cat_restricton .= '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                            }

                            $cat_restricton .= '</select>';

                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The term names and slugs are escaped while the dropdown is built above.
                            echo $cat_restricton;
                            ?>
                        </p>
                        <p class="wpsl-cat-selection wpsl-hide">
                            <label for="wpsl-cat-selection"><?php esc_html_e( 'Set a selected category?', 'wp-store-locator' ); ?></label>
                            <?php
                            $cat_selection = '<select id="wpsl-cat-selection" autocomplete="off">';

                            $cat_selection .= '<option value="" selected="selected">' . esc_html__( 'Select category', 'wp-store-locator' ) . '</option>';

                            foreach ( $terms as $term ) {
                                $cat_selection .= '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                            }

                            $cat_selection .= '</select>';

                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The term names and slugs are escaped while the dropdown is built above.
                            echo $cat_selection;
                            ?>
                        </p>
                        <p class="wpsl-checkbox-options wpsl-hide">
                            <label for="wpsl-checkbox-columns"><?php esc_html_e( 'Checkbox columns', 'wp-store-locator' ); ?></label>
                            <?php
                            echo '<select id="wpsl-checkbox-columns">';

                            $i = 1;

                            while ( $i <= 4 ) {
                                $selected = ( $i == 3 ) ? "selected='selected'" : ''; // 3 is the default

                                echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data
                                $i++;
                            }

                            echo '</select>';
                            ?>
                        </p>
                        <p class="wpsl-checkbox-selection wpsl-hide">
                            <label for="wpsl-checkbox-selection"><?php esc_html_e( 'Set selected checkboxes', 'wp-store-locator' ); ?></label>
                            <?php
                            $checkbox_selection = '<select id="wpsl-checkbox-selection" multiple="multiple" autocomplete="off">';

                            foreach ( $terms as $term ) {
                                $checkbox_selection .= '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                            }

                            $checkbox_selection .= '</select>';

                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The term names and slugs are escaped while the dropdown is built above.
                            echo $checkbox_selection;
                            ?>
                        </p>
                        <?php
                    }
                    ?>
                    <p>
                        <label for="wpsl-map-type"><?php esc_html_e( 'Map type', 'wp-store-locator' ); ?></label>
                        <?php echo $this->ui->create_dropdown( 'map_types' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                    </p>
                </div>
                <div id="wpsl-sc-tab-markers">
                    <?php
                    /**
                     * The store editor's Location Marker dropdowns, one per
                     * shortcode marker type. Each starts on Default (from
                     * settings), whose empty value emits no marker attribute,
                     * so an untouched dropdown keeps following the settings.
                     */
                    $pickable = \wpsl_pickable_markers();

                    $marker_types = [
                        'start'  => __( 'Start marker', 'wp-store-locator' ),
                        'store'  => __( 'Store marker', 'wp-store-locator' ),
                        'active' => __( 'Active store marker', 'wp-store-locator' ),
                    ];

                    foreach ( $marker_types as $marker_type => $marker_label ) {
                        // A v2 upgrade can leave an empty stored value; fall back
                        // to the registered default rather than an empty preview.
                        $default_value = $this->settings->get( 'markers', $marker_type . '_marker' ) ?: $this->settings->get_default( 'markers', $marker_type . '_marker' );
                        ?>
                        <p class="wpsl-lm-type-label" id="wpsl-lm-type-label-<?php echo esc_attr( $marker_type ); ?>"><?php echo esc_html( $marker_label ); ?></p>
                        <?php
                        $this->marker_manager->render_marker_dropdown( $marker_type, [
                            'saved'         => '',
                            'bundled'       => $pickable['bundled'],
                            'custom'        => $pickable['custom'],
                            'default_src'   => \wpsl_marker_src( $default_value ),
                            'default_value' => $default_value,
                            'default_label' => __( 'from settings', 'wp-store-locator' ),
                            'input_name'    => 'wpsl_shortcode_markers[' . $marker_type . ']',
                        ] );
                    }

                    // Once, under all three dropdowns, not inside each of them.
                    $this->marker_manager->render_marker_creation_links();
                    ?>
                    <?php // Three-way like the dropdowns above: an empty value keeps this map on the settings page's choice. ?>
                    <p class="wpsl-sc-clusters">
                        <label for="wpsl-marker-clusters"><?php esc_html_e( 'Marker clusters', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Group nearby markers into a cluster. Recommended for maps with a large amount of markers.', 'wp-store-locator' ); ?></span></span></label>
                        <select id="wpsl-marker-clusters" autocomplete="off">
                            <option value="" selected="selected"><?php esc_html_e( 'Default (from settings)', 'wp-store-locator' ); ?></option>
                            <option value="true"><?php esc_html_e( 'Enabled', 'wp-store-locator' ); ?></option>
                            <option value="false"><?php esc_html_e( 'Disabled', 'wp-store-locator' ); ?></option>
                        </select>
                    </p>
                </div>
            </div>
            <div class="wpsl-sc-actions">
                <input type="button" id="wpsl-insert-shortcode" class="button-primary" value="<?php esc_attr_e( 'Insert Store Locator', 'wp-store-locator' ); ?>" onclick="WPSL_InsertShortcode();" />
            </div>
        </div>
        <?php
    }
}