<?php
/**
 * Shortcode Generator class
 *
 * @author Tijmen Smit
 * @since  2.2.10
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WPSL_Shortcode_Generator' ) ) {

    /**
     * Handle the generation of the WPSL shortcode through the media button
     *
     * @since 2.2.10
     */
    class WPSL_Shortcode_Generator {

        /**
         * Constructor
         */
        public function __construct() {
            add_action( 'media_buttons', array( $this, 'add_wpsl_media_button' ) );
            add_action( 'admin_init',    array( $this, 'show_thickbox_iframe_content' ) );
        }

        /**
         * Add the WPSL media button to the media button row
         *
         * @since 2.2.10
         * @return void
         */
        public function add_wpsl_media_button() {

            global $pagenow, $typenow;

            /* Make sure we're on a post/page or edit screen in the admin area */
            if ( in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) && $typenow != 'wpsl_stores' ) {
                $changelog_link = self_admin_url( '?wpsl_media_action=store_locator&KeepThis=true&TB_iframe=true&width=783&height=800' );

                echo '<a href="' . esc_url( $changelog_link ) . '" class="thickbox button wpsl-thickbox" name="' . esc_attr__( 'WP Store Locator' ,'wp-store-locator' ) . '">' .  esc_html__( 'Insert Store Locator', 'wp-store-locator' ) . '</a>';
            }
        }

        /**
         * Show the shortcode thickbox content
         *
         * @since 2.2.10
         * @return void
         */
        function show_thickbox_iframe_content() {

            global $wpsl_settings, $wpsl_admin;

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking URL parameter to determine which thickbox content to display, not processing form data.
            if ( empty( $_REQUEST['wpsl_media_action'] ) ) {
                return;
            }

            if ( !current_user_can( 'edit_pages' ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action', 'wp-store-locator' ), esc_html__( 'Error', 'wp-store-locator' ), array( 'response' => 403 ) );
            }

            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

            // Make sure the required JS / CSS files are loaded in the Thickbox iframe
            wp_enqueue_script( 'jquery-ui-core' );
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_script( 'media-upload' );
            wp_enqueue_script( 'wpsl-shortcode-generator', plugins_url( '/js/wpsl-shortcode-generator' . $min . '.js', __FILE__ ), array( 'jquery-ui-tabs' ), WPSL_VERSION_NUM, true );
            
            wp_enqueue_style( 'buttons' );
            wp_enqueue_style( 'forms' );
            wp_enqueue_style( 'wpsl-shortcode-style', plugins_url( '/css/style' . $min . '.css', __FILE__ ), array(), WPSL_VERSION_NUM, 'all' );
            
            wp_print_scripts();
            wp_print_styles();
            ?>
            <style>
                body {
                    color: #444;
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    font-size: 13px;
                    margin: 0;
                }

                #wpsl-media-tabs .ui-tabs-nav {
                     padding-left: 15px;
                     background: #fff !important;
                     border-bottom: 1px solid #dfdfdf;
                     border-collapse: collapse;
                     padding-top: .2em;
                 }

                #wpsl-media-tabs .ui-tabs-nav::after {
                    clear: both;
                    content: "";
                    display: table;
                    border-collapse: collapse;
                }

                #wpsl-media-tabs .ui-tabs-nav li {
                    list-style: none;
                    float: left;
                    position: relative;
                    top: 0;
                    margin: 1px .2em 0 0;
                    padding: 0;
                    white-space: nowrap;
                    border-bottom-width: 0;
                }

                #wpsl-media-tabs .ui-tabs-anchor {
                    float: left;
                    padding: .5em 1em;
                    text-decoration: none;
                    font-size: 14.3px;
                }

                #wpsl-media-tabs .ui-tabs-active a {
                    color: #212121;
                    cursor: text;
                }

                #wpsl-media-tabs .ui-tabs .ui-tabs-anchor {
                    float: left;
                    padding: .5em 1em;
                    text-decoration: none;
                }

                #wpsl-media-tabs.ui-widget-content {
                    border: none;
                    padding: 10px 0 0 0;
                }

                #wpsl-media-tabs .ui-tabs-anchor {
                    outline: none;
                }

                #wpsl-shortcode-config tr > td {
                    width: 25%;
                }

                #wpsl-markers-tab .wpsl-marker-list {
                    display: block;
                    overflow: hidden;
                    padding: 0;
                    list-style-type: none;
                }

                #wpsl-markers-tab .wpsl-marker-list li input {
                    padding: 0;
                    margin: 0;
                }

                #wpsl-shortcode-config .form-table,
                #wpsl-shortcode-config .form-table td,
                #wpsl-shortcode-config .form-table th,
                #wpsl-shortcode-config .form-table td p {
                    font-size: 13px;
                }

                #wpsl-shortcode-config .ui-tabs .ui-tabs-nav {
                    padding-left: 15px;
                    border-radius: 0;
                    margin: 0;
                }

                .wpsl-shortcode-markers {
                    padding: 0 10px;
                    margin-top: 27px;
                    font-size: 13px;
                }

                #wpsl-insert-shortcode {
                    margin-left: 19px;
                }

                #wpsl-shortcode-config .ui-state-default {
                    border: 1px solid #d3d3d3;
                    border-top-left-radius: 4px;
                    border-top-right-radius: 4px;
                    background: none;
                }

                #wpsl-shortcode-config .ui-state-default a {
                    color: #909090;
                }

                #wpsl-shortcode-config .ui-state-default.ui-tabs-active a {
                    color: #212121;
                }

                #wpsl-shortcode-config .ui-state-hover {
                    border-bottom: none;
                }

                #wpsl-shortcode-config .ui-state-hover a {
                    color: #72777c;
                }

                #wpsl-media-tabs .ui-state-active {
                    border: 1px solid #aaa;
                    border-bottom: 1px solid #fff;
                    padding-bottom: 0;
                }

                #wpsl-shortcode-config li.ui-tabs-active.ui-state-hover,
                #wpsl-shortcode-config li.ui-tabs-active {
                    border-bottom: 1px solid #fff;
                    padding-bottom: 0;
                }

                #wpsl-media-tabs li.ui-tabs-active {
                    margin-bottom: -1px;
                }

                #wpsl-general-tab,
                #wpsl-markers-tab {
                    border: 0;
                    padding: 1em 1.4em;
                    background: none;
                }

                @media ( max-width: 782px ) {
                    #wpsl-shortcode-config tr > td {
                        width: 100%;
                    }
                }
            </style>
            <div id="wpsl-shortcode-config" class="wp-core-ui">
                <div id="wpsl-media-tabs">
                    <ul>
                        <li><a href="#wpsl-general-tab"><?php esc_html_e( 'General Options', 'wp-store-locator' ); ?></a></li>
                        <li><a href="#wpsl-markers-tab"><?php esc_html_e('Markers', 'wp-store-locator' ); ?></a></li>
                    </ul>
                    <div id="wpsl-general-tab">
                        <table class="form-table wpsl-shortcode-config">
                            <tbody>
                            <tr>
                                <td><label for="wpsl-store-template"><?php esc_html_e('Select the used template', 'wp-store-locator' ); ?></label></td>
                                <td>
                                <?php 
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in show_template_options method.
                                    echo $wpsl_admin->settings_page->show_template_options(); 
                                ?>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="wpsl-start-location"><?php esc_html_e( 'Start point', 'wp-store-locator' ); ?></label><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'If nothing it set, then the start point from the %1$ssettings%2$s page is used.', 'wp-store-locator' ), '<a href=' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-map-settings' ) ) . '>', '</a>'  ) ); ?></span></span></p></td>
                                <td><input type="text" placeholder="Optional" value="" id="wpsl-start-location"></td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="wpsl-auto-locate"><?php esc_html_e( 'Attempt to auto-locate the user', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'Most modern browsers %1$srequire%2$s a HTTPS connection before the Geolocation feature works.', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/html-5-geolocation-not-working/">', '</a>' ) ); ?></span></span></label>
                                </td>
                                <td><input type="checkbox" value="" <?php checked( $wpsl_settings['auto_locate'], true ); ?> name="wpsl_map[auto_locate]" id="wpsl-auto-locate"></td>
                            </tr>
                            <?php
                            $terms = get_terms( array(
                                'taxonomy'   => 'wpsl_store_category',
                                'hide_empty' => true,
                            ) );

                            if ( $terms ) {
                                ?>
                                <tr class="wpsl-cat-restriction">
                                    <td style="vertical-align:top;"><label for="wpsl-cat-restriction"><?php esc_html_e('Restrict to categories', 'wp-store-locator' ); ?></label></td>
                                    <td>
                                        <?php
                                        $cat_restricton = '<select id="wpsl-cat-restriction" multiple="multiple" autocomplete="off">';

                                        foreach ( $terms as $term ) {
                                            $cat_restricton .= '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                                        }

                                        $cat_restricton .= '</select>';

                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in variable construction.
                                        echo $cat_restricton;
                                        ?>
                                    </td>
                                </tr>
                                <tr class="wpsl-cat-filter-type-row">
                                    <td><label for="wpsl-cat-filter-types"><?php esc_html_e( 'Category filter type', 'wp-store-locator' ); ?></label></td>
                                    <td>
                                        <select id="wpsl-cat-filter-types" autocomplete="off">
                                            <option value="" selected="selected"><?php esc_html_e( 'None', 'wp-store-locator' ); ?></option>
                                            <option value="dropdown"><?php esc_html_e( 'Dropdown', 'wp-store-locator' ); ?></option>
                                            <option value="checkboxes"><?php esc_html_e( 'Checkboxes', 'wp-store-locator' ); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr class="wpsl-cat-selection wpsl-hide">
                                    <td style="vertical-align:top;"><label for="wpsl-cat-selection"><?php esc_html_e('Set a selected category?', 'wp-store-locator' ); ?></label></td>
                                    <td>
                                        <?php
                                        $cat_selection = '<select id="wpsl-cat-selection" autocomplete="off">';
                                        $cat_selection .= '<option value="" selected="selected">' . esc_html__( 'Select category', 'wp-store-locator' ) . '</option>';

                                        foreach ( $terms as $term ) {
                                            $cat_selection .= '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                                        }

                                        $cat_selection .= '</select>';

                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in variable construction.
                                        echo $cat_selection;
                                        ?>
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                            <tr class="wpsl-checkbox-options wpsl-hide">
                                <td><label for="wpsl-checkbox-columns"><?php esc_html_e('Checkbox columns', 'wp-store-locator' ); ?></label></td>
                                <td>
                                    <?php
                                    echo '<select id="wpsl-checkbox-columns">';

                                    $i = 1;

                                    while ( $i <= 4 ) {
                                        $selected = ( $i == 3 ) ? "selected='selected'" : ''; // 3 is the default

                                        echo '<option value="' . esc_attr( $i ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $i ) . '</option>';
                                        $i++;
                                    }

                                    echo '</select>';
                                    ?>
                                </td>
                            </tr>
                            <tr class="wpsl-checkbox-selection wpsl-hide">
                                <td><label for="wpsl-checkbox-columns"><?php esc_html_e('Set selected checkboxes', 'wp-store-locator' ); ?></label></td>
                                <td>
                                    <?php
                                    $checkbox_selection = '<select id="wpsl-checkbox-selection" multiple="multiple" autocomplete="off">';

                                    foreach ( $terms as $term ) {
                                        $checkbox_selection .= '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
                                    }

                                    $checkbox_selection .= '</select>';

                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in variable construction.
                                    echo $checkbox_selection;
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td><label for="wpsl-map-type"><?php esc_html_e( 'Map type', 'wp-store-locator' ); ?>:</label></td>
                                <td>
                                    <?php 
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in create_dropdown method.
                                    echo $wpsl_admin->settings_page->create_dropdown( 'map_types' ); 
                                    ?>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="wpsl-markers-tab">
                        <div class="wpsl-shortcode-markers">
                            <?php 
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in create_marker_html method.
                            echo $wpsl_admin->settings_page->show_marker_options(); 
                            ?>
                        </div>
                    </div>
                </div>

                <p class="submit">
                    <input type="button" id="wpsl-insert-shortcode" class="button-primary" value="<?php esc_attr_e( 'Insert Store Locator', 'wp-store-locator' ); ?>" onclick="WPSL_InsertShortcode();" />
                </p>
            </div>

            <?php

            exit();
        }
    }

    new WPSL_Shortcode_Generator();
}