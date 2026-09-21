<?php
/**
 * Render the store metabox input fields ( the view layer of the editor ).
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Field_Renderer {

    /**
     * Plugin settings ( the full get_all() array ).
     *
     * @var array
     */
    private $config;

    /**
     * Whether the required-field markers are suppressed.
     *
     * @var bool
     */
    private $skip_required_check;

    /**
     * UI helper ( provides the opening-hours format dropdown ).
     *
     * @var \WPSL\Admin\Settings\UI
     */
    private $ui;

    /**
     * @param array                    $config              Plugin settings.
     * @param bool                     $skip_required_check Suppress required markers.
     * @param \WPSL\Admin\Settings\UI  $ui                  UI helper.
     */
    public function __construct( $config, $skip_required_check, $ui = null ) {
        $this->config              = $config;
        $this->skip_required_check = $skip_required_check;
        $this->ui                  = $ui;
    }

    /**
     * Set the CSS class that tells JS it's an required input field.
     *
     * @since  2.0.0
     * @param  array       $args     The css classes
     * @param  bool        $single   Whether to return just the class name, or also include the class=""
     * @return string|void $response The required CSS class or nothing
     */
    private function set_required_class( $args, $single = false ) {
        if ( ! $this->skip_required_check && isset( $args['required'] ) && ( $args['required'] ) ) {
            if ( ! $single ) {
                $response = 'class="wpsl-required"';
            } else {
                $response = 'wpsl-required';
            }

            return $response;
        }

        return '';
    }

    /**
     * Check if the current field is required.
     *
     * @since  2.0.0
     * @param  array $args The CSS classes
     * @return string|void The HTML for the required element or nothing
     */
    private function is_required_field( $args ) {
        if ( ! $this->skip_required_check && isset( $args['required'] ) && ( $args['required'] ) ) {
            $response = '<span class="wpsl-star"> *</span>';

            return $response;
        }

        return '';
    }

    /**
     * Get the prefilled field data.
     *
     * @since  2.0.0
     * @param  string $field_name The name of the field to get the data for
     * @return string $field_data The field data
     */
    private function get_prefilled_field_data( $field_name ) {
        global $pagenow;

        $wpsl_settings = $this->config['editor'];

        $field_data = '';

        // Prefilled values are only used for new pages, not when a user edits an existing page.
        if ( $pagenow == 'post.php' && isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) == 'edit' ) {
            return;
        }

        $prefilled_fields = [
            'country',
            'hours'
        ];

        if ( in_array( $field_name, $prefilled_fields ) ) {
            $field_data = $wpsl_settings[$field_name];
        }

        return $field_data;
    }

    /**
     * Create a text input field.
     *
     * @since  2.0.0
     * @param  array $args The input name and label
     * @return void
     */
    public function text_input( $args ) {
        $saved_value = $this->get_store_meta( $args['key'] );

        // If there is no existing meta value, check if a prefilled value exists for the input field.
        if ( ! $saved_value ) {
            $saved_value = $this->get_prefilled_field_data( $args['key'] );
        }

        // If there's still no value, fall back to the custom field's configured default.
        if ( ! $saved_value && isset( $args['data']['default'] ) ) {
            $saved_value = $args['data']['default'];
        }

        // If not type is specified, then default to text
        if ( ! isset( $args['data']['type'] ) || empty( $args['data']['type'] ) ) {
            $args['data']['type'] = 'text';
        }

        ?>
        <p>
            <label for="wpsl-<?php echo esc_attr( $args['key'] ); ?>"><?php echo esc_html( $args['data']['label'] ) . ' ' . $this->is_required_field( $args['data'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></label>
            <input id="wpsl-<?php echo esc_attr( $args['key'] ); ?>" <?php echo $this->set_required_class( $args['data'] ); ?> type="<?php echo esc_attr( $args['data']['type'] ); ?>" name="wpsl[<?php echo esc_attr( $args['key'] ); ?>]" value="<?php echo esc_attr( $saved_value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>" />
        </p>
        <?php
    }

    /**
     * Create a hidden input field.
     *
     * @since 2.0.0
     * @param array $args The name of the meta value
     * @return void
     */
    public function hidden_input( $args ) {
        $saved_value = $this->get_store_meta( $args['key'] );
        ?>

        <input id="wpsl-<?php echo esc_attr( $args['key'] ); ?>" type="hidden" name="wpsl[<?php echo esc_attr( $args['key'] ); ?>]" value="<?php echo esc_attr( $saved_value ); ?>" />

        <?php
    }

    /**
     * Create a textarea field.
     *
     * @since  2.0.0
     * @param  array $args The textarea name and label
     * @return void
     */
    public function textarea_input( $args ) {
        $saved_value = $this->get_store_meta( $args['key'] );

        if ( $args['key'] == 'hours' && ! is_string( $saved_value ) ) {
            $saved_value = '';
        }

        // If there is no existing meta value, check if a prefilled value exists for the textarea.
        if ( ! $saved_value ) {
            $prefilled_value = $this->get_prefilled_field_data( $args['key'] );

            if ( isset( $prefilled_value['textarea'] ) ) {
                $saved_value = $prefilled_value['textarea'];
            }
        }

        // If there's still no value, fall back to the custom field's configured default.
        if ( ! $saved_value && isset( $args['data']['default'] ) ) {
            $saved_value = $args['data']['default'];
        }
        ?>

        <?php
        /*
         * The BB code hint belongs on the description-style textareas, not on the
         * opening hours. That one only reaches this renderer on a site upgraded
         * from 1.x, where it holds a plain schedule - offering bold and links for
         * a list of opening times just reads as noise.
         */
        $show_bb_code_hint = ( 'hours' !== $args['key'] );
        ?>

        <p class="wpsl-flex-textarea">
            <label for="wpsl-<?php echo esc_attr( $args['key'] ); ?>"><?php echo esc_html( $args['data']['label'] ) . ' ' . $this->is_required_field( $args['data'] ); ?><?php if ( $show_bb_code_hint ) : ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to the BB code documentation, %2$s: closing link tag */ echo wp_kses_post( sprintf( __( 'BB code is supported: [strong]bold[/strong], [em]italic[/em], [br] line break, [url=https://example.com]link text[/url]. %1$sRead more%2$s', 'wp-store-locator' ), '<a href="https://wpstorelocator.co/document/bb-code" target="_blank">', '</a>' ) ); ?></span></span><?php endif; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></label>
            <textarea id="wpsl-<?php echo esc_attr( $args['key'] ); ?>" <?php echo $this->set_required_class( $args['data'] ); ?> name="wpsl[<?php echo esc_attr( $args['key'] ); ?>]" cols="5" rows="5"><?php echo esc_html( $saved_value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></textarea>
        </p>

        <?php
    }

    /**
     * Create a wp editor field.
     *
     * @since  2.1.1
     * @param  array $args The wp editor name and label
     * @return void
     */
    public function wp_editor_input( $args ) {
        $saved_value = $this->get_store_meta( $args['key'] );
        ?>

        <p>
            <label for="wpsl-<?php echo esc_attr( $args['key'] ); ?>"><?php echo esc_html( $args['data']['label'] ) . ' ' . $this->is_required_field( $args['data'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></label>
            <?php wp_editor( $saved_value, 'wpsleditor_' . wpsl_random_chars(), $settings = ['textarea_name' => 'wpsl['. esc_attr( $args['key'] ).']'] ); ?>
        </p>

        <?php
    }

    /**
     * Create a checkbox field.
     *
     * @since  2.0.0
     * @param  array $args The checkbox name and label
     * @return void
     */
    public function checkbox_input( $args ) {
        $saved_value = $this->get_store_meta( $args['key'] );
        ?>

        <p class="wpsl-flex-checkbox">
            <label for="wpsl-<?php echo esc_attr( $args['key'] ); ?>"><?php echo esc_html( $args['data']['label'] ) . ' ' . $this->is_required_field( $args['data'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></label>
            <input id="wpsl-<?php echo esc_attr( $args['key'] ); ?>" <?php echo $this->set_required_class( $args['data'] ); ?> type="checkbox" name="wpsl[<?php echo esc_attr( $args['key'] ); ?>]" <?php checked( $saved_value, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?> value="1" />
        </p>

        <?php
    }

    /**
     * Create a dropdown field.
     *
     * @since  2.0.0
     * @param  array $args The dropdown name and label
     * @return void
     */
    public function dropdown_input( $args ) {
        // The hour dropdown requires a different structure with multiple dropdowns.
        if ( $args['key'] == 'hours' ) {
            $this->opening_hours();
        } else {
            $option_list = $args['data']['options'];
            $saved_value = $this->get_store_meta( $args['key'] );
            ?>

            <p>
                <label for="wpsl-<?php echo esc_attr( $args['key'] ); ?>"><?php echo esc_html( $args['data']['label'] ) . ' ' . $this->is_required_field( $args['data'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?></label>
                <select id="wpsl-<?php echo esc_attr( $args['key'] ); ?>" <?php echo $this->set_required_class( $args['data'] ); ?>  name="wpsl[<?php echo esc_attr( $args['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>]" autocomplete="off" />
                <?php foreach ( $option_list as $key => $option ) { ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php if ( isset( $saved_value ) ) { selected( $saved_value, $key ); } ?>><?php echo esc_html( $option ); ?></option>
                <?php } ?>
                </select>
            </p>

            <?php
        }
    }

    /**
     * Create the timezone dropdown field.
     *
     * Renders the same city-grouped timezone picker as
     * Settings -> General, prepended with a "Site default" option.
     * The value only influences the open / closed status calculation.
     *
     * @since  3.0.0
     * @param  array $args The field key and label
     * @return void
     */
    public function timezone_input( $args ) {
        $saved_value = $this->get_store_meta( $args['key'] );
        ?>

        <p>
            <label for="wpsl-<?php echo esc_attr( $args['key'] ); ?>"><?php echo esc_html( $args['data']['label'] ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php echo sprintf(
                /* translators: %1$s: opening link tag to the settings page, %2$s: closing link tag, %3$s: opening link tag to the WordPress general settings, %4$s: closing link tag */
                esc_html__( 'Only used when the "Show the current open / closed status?" option is enabled on the %1$ssettings page%2$s. When empty, the timezone configured in %3$sWordPress%4$s is used.', 'wp-store-locator' ),
                '<a target="_blank" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-ux' ) ) . '">',
                '</a>',
                '<a target="_blank" href="' . esc_url( admin_url( 'options-general.php' ) ) . '">',
                '</a>'
            ); ?></span></span></label>
            <select id="wpsl-<?php echo esc_attr( $args['key'] ); ?>" name="wpsl[<?php echo esc_attr( $args['key'] ); ?>]" autocomplete="off">
                <option value=""><?php esc_html_e( 'Site default', 'wp-store-locator' ); ?></option>
                <?php echo wp_timezone_choice( $saved_value, get_user_locale() ); ?>
            </select>
        </p>

        <?php
    }

    /**
     * Get the store post meta.
     *
     * @since  2.0.0
     * @param  string     $key        The name of the meta value
     * @return mixed|void $store_meta Meta value for the store field
     */
    public function get_store_meta( $key ) {
        global $post;

        $store_meta = get_post_meta( $post->ID, 'wpsl_' . $key, true );

        if ( $store_meta ) {
            return $store_meta;
        }
    }

    /**
     * Create the openings hours table with the hours as dropdowns.
     *
     * @since  2.0.0
     * @param  string $location The location were the opening hours are shown.
     * @return void
     */
    public function opening_hours( $location = 'store_page' ) {
        global $post;

        $wpsl_settings = $this->config['editor'];

        $name          = ( $location == 'settings' ) ? 'wpsl_editor[hours]' : 'wpsl[hours]'; // the name of the input or select field
        $opening_days  = array_merge( wpsl_get_weekdays(), [ "special" => "Special" ] );
        $opening_hours = '';
        $hours         = '';

        if ( $location == 'store_page' ) {
            $opening_hours = get_post_meta( $post->ID, 'wpsl_hours' );
        }

        /*
         * If we don't have any opening hours, we use the defaults.
         */
        if ( ! isset( $opening_hours[0] ) || ! is_array( $opening_hours[0] ) || ! $opening_hours[0] ) {
            $opening_hours = $wpsl_settings['hours']['dropdown'];
        } else {
            $opening_hours = $opening_hours[0];
        }

        // Find out whether we have a 12 or 24hr format.
        $hour_format = $this->find_hour_format( $opening_hours );

        if ( $hour_format == 24 ) {
            $hour_class = 'wpsl-twentyfour-format';
        } else {
            $hour_class = 'wpsl-twelve-format';
        }

        /*
         * Only include the 12 / 24hr dropdown switch if we are on store page,
         * otherwise just show the table with the opening hour dropdowns.
         */
        if ( $location == 'store_page' ) {
            ?>
            <p class="wpsl-hours-dropdown">
                <label for="wpsl-editor-hour-input"><?php esc_html_e( 'Hour format', 'wp-store-locator' ); ?></label>
                <?php echo $this->ui->show_opening_hours_format( $hour_format ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
            </p>
        <?php } ?>
        <table id="wpsl-store-hours" class="<?php echo esc_attr( $hour_class ); ?>">
            <tr>
                <th><?php esc_html_e( 'Days', 'wp-store-locator' ); ?></th>
                <th><?php esc_html_e( 'Opening Periods', 'wp-store-locator' ); ?></th>
                <th></th>
            </tr>
            <?php
            $current_day = 0;

            foreach ( $opening_days as $index => $day ) {
                $i = 0;

                if ( isset( $opening_hours[$index] ) && is_array( $opening_hours[$index] ) ) {
                    $hour_count = count( $opening_hours[$index] );
                } else {
                    $hour_count = 0;
                }
                ?>
                <tr>
                    <td class="wpsl-opening-day"><?php echo esc_html( $day ); ?></td>
                    <td id="wpsl-hours-<?php echo esc_attr( $index ); ?>" class="wpsl-opening-hours" data-day="<?php echo esc_attr( $index ); ?>">
                        <?php
                        if ( $current_day !== 7 ) {
                            if ( $hour_count > 0 ) {
                                // Loop over the opening periods.
                                while ( $i < $hour_count ) {
                                    if ( isset( $opening_hours[$index][$i] ) ) {
                                        $hours = explode( ',', $opening_hours[$index][$i] );
                                    } else {
                                        $hours = '';
                                    }

                                    // If we don't have two parts or one of them is empty, then we set the store to closed.
                                    if ( ( count( $hours ) == 2 ) && ( ! empty( $hours[0] ) ) && ( ! empty( $hours[1] ) ) ) {
                                        $args = [
                                            'day'         => $index,
                                            'name'        => $name,
                                            'hour_format' => $hour_format,
                                            'hours'       => $hours
                                        ];
                                        ?>
                                        <div class="wpsl-current-period <?php if ( $i > 0 ) { echo 'wpsl-multiple-periods'; } ?>">
                                            <?php echo $this->opening_hours_dropdown( $args, 'open' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                                            <span> - </span>
                                            <?php echo $this->opening_hours_dropdown( $args, 'closed' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
                                            <div class="wpsl-manage-period">
                                                <button type="button" class="wpsl-icon-cancel-circled"></button>
                                            </div>
                                        </div>
                                        <?php
                                    } else {
                                        $this->show_hours_msg( $name, $index, $opening_hours[$index] );
                                    }

                                    $i++;
                                }
                            } else {

                                /**
                                 * If no hours where selected before the 2.3 update,
                                 * then an empty array was saved. To make sure it
                                 * still shows 'closed' as it did prior to 2.3, we check for
                                 * an empty array and show 'Closed' when necessary.
                                 */
                                if ( isset( $opening_hours[$index] ) ) {
                                    if ( is_array( $opening_hours[$index] ) && ! $opening_hours[$index] ) {
                                        $msg = esc_html__( 'Closed', 'wp-store-locator' );
                                    } else {
                                        $msg = $opening_hours[$index] ;
                                    }
                                } else {
                                    $msg = '';
                                }

                                $this->show_hours_msg( $name, $index, $msg );
                            }
                        } else {
                            ?>
                            <p class="wpsl-custom-period-msg">
                                <textarea id="wpsl-special-hours" name="<?php echo esc_attr( $name ); ?>[special]" rows="5" placeholder="<?php esc_attr_e( 'For example open every first Sunday of the month', 'wp-store-locator' ); ?>"><?php if ( isset( $opening_hours['special'] ) ) { echo esc_textarea( $opening_hours['special'] ); } ?></textarea>
                            </p>
                            <?php
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ( $current_day !== 7 ) { ?>
                            <div class="wpsl-manage-period">
                                <button type="button" class="wpsl-icon-plus-circled"></button>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
                <?php

                $current_day++;
            }
            ?>
        </table>
        <?php
    }

    /**
     * Show a custom store hours msg
     *
     * @since  2.0.0
     * @param  string $name The name for the input field
     * @param  string $day  The day the store is closed
     * @param  string $msg  Custom store hours msg
     * @return void
     */
    private function show_hours_msg( $name, $day, $msg ) {
        if ( empty( $msg ) ) {
            $msg = esc_html__( 'Closed', 'wp-store-locator' );
        }

        echo '<p class="wpsl-custom-period-msg"><input type="text" name="' . esc_attr( $name ) . '[' . esc_attr( $day ) . ']" value="' . esc_attr( $msg ) . '"></p>';
    }

    /**
     * Find out whether the opening hours are set in the 12 or 24hr format.
     *
     * We use this to determine the selected value for the dropdown in the store editor.
     * So a user can decide to change the opening hour format.
     *
     * @since  2.0.0
     * @param  array  $opening_hours The opening hours for the whole week
     * @return string The hour format used in the opening hours
     */
    private function find_hour_format( $opening_hours ) {
        $week_days = wpsl_get_weekdays();

        foreach ( $week_days as $day_key => $day_name ) {
            if ( isset( $opening_hours[$day_key] ) && !empty( $opening_hours[$day_key] ) ) {
                
                // Handle both old format (string) and new format (array)
                if ( is_array( $opening_hours[$day_key] ) ) {
                    $hours_string = isset( $opening_hours[$day_key][0] ) ? $opening_hours[$day_key][0] : '';
                } else {
                    $hours_string = $opening_hours[$day_key];
                }
                
                if ( !empty( $hours_string ) && $hours_string !== 'Closed' ) {
                    if ( strpos( $hours_string, 'AM' ) !== false || strpos( $hours_string, 'PM' ) !== false ) {
                        return 12;
                    } else {
                        return 24;
                    }
                }
            }
        }

        return $this->config['editor']['hour_format'];
    }

    /**
     * Create the opening hours dropdown.
     *
     * @since  2.0.0
     * @param  array  $args   The data to create the opening hours dropdown
     * @param  string $period Either set to open or close
     * @return string $select The html for the dropdown
     */
    private function opening_hours_dropdown( $args, $period ) {
        $select_index  = ( $period == 'open' ) ? 0 : 1;
        $selected_time = $args['hours'][$select_index];
        $select_name   = $args['name'] . '[' . strtolower( $args['day'] ) . '_' . $period . ']';
        $open          = strtotime( '12:00am' );
        $close         = strtotime( '11:59pm' );
        $hour_interval = 900;

        if ( $args['hour_format'] == 12 ) {
            $format = 'g:i A';
        } else {
            $format = 'H:i';
        }

        $select = '<select class="wpsl-' . esc_attr( $period ) . '-hour" name="' . esc_attr( $select_name ) . '[]" autocomplete="off">';

        $normalized_selected = $this->normalize_time_format( $selected_time );

        for ( $i = $open; $i <= $close; $i += $hour_interval ) {
            $date = gmdate( $format, $i );

            if ( $period == "open" && ( $date == "00:00" || $date == "12:00 AM" ) ) {
                $select .= "<option value='24hrs'>" . esc_html__( '24hrs', 'wp-store-locator' ) . "</option>";
            }

            // If the selected time matches the current time then we set it to active.
            // Normalize both times for comparison to handle format differences
            $normalized_date = $this->normalize_time_format( $date );
            
            if ( $normalized_selected == $normalized_date ) {
                $selected = 'selected="selected"';
            } else {
                $selected = '';
            }

            $select .= "<option value='" . $date . "' $selected>" . $date . "</option>";
        }

        $select .= '</select>';

        return $select;
    }

    /**
     * Normalize time format for comparison.
     * 
     * Converts different time formats to a standard format for comparison.
     * Handles cases like "9:00 AM" vs "09:00" or "5:00 PM" vs "17:00".
     *
     * @since  3.0.0
     * @param  string $time The time string to normalize
     * @return string The normalized time string
     */
    private function normalize_time_format( $time ) {
        // Handle special cases
        if ( $time === '24hrs' || $time === 'Closed' || empty( $time ) ) {
            return $time;
        }

        // Try to parse the time and convert to a standard format
        $timestamp = strtotime( $time );
        
        if ( $timestamp === false ) {
            return $time; // Return original if can't parse
        }
        
        // Convert to 24-hour format for comparison
        return gmdate( 'H:i', $timestamp );
    }
}