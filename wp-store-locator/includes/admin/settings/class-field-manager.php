<?php
/**
 * Field Manager.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Field_Manager {

    /**
     * Settings manager instance
     *
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Holds the editor settings
     *
     * @var array
     */
    private $editor;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {

        $this->settings = $settings;
        $this->editor = $this->settings->get_group( 'editor' );

        add_action( 'wp_ajax_wpsl_field_manager', [ $this, 'actions' ] );
    }

    /**
     * Handle the different actions.
     *
     * @since  3.0.0
     * @return void
     */
    public function actions() {
        $type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
        
        if ( ! isset( $_POST['wpsl_field_manager_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_field_manager_nonce'] ) ), 'wpsl-' . $type ) ) {
            return wp_send_json_error( __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            return wp_send_json_error( __( 'You do not have permission to perform this action.', 'wp-store-locator' ) );
        }

        $action = $type;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above, data sanitized in individual methods
        $args = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : '';

        // Restrict dispatch to an explicit allowlist so arbitrary class methods
        // can never be invoked through the user-controlled 'type' parameter.
        $allowed_actions = [ 'create_group', 'update_group', 'delete_group', 'delete_field' ];

        if ( in_array( $action, $allowed_actions, true ) && method_exists( $this, $action ) ) {
            call_user_func( [ $this, $action ], $args );
        } else {
            return wp_send_json_error( __( 'Invalid action, please reload the page and try again.', 'wp-store-locator' ) );
        }
    }

    /**
     * Create a new group.
     *
     * @since  3.0.0
     * @param  string $group_name
     * @return json
     */
    private function create_group( $group_name ) {
        if ( ! isset( $this->editor['field_manager']['groups'] ) || ! is_array( $this->editor['field_manager']['groups'] ) ) {
            $this->editor['field_manager']['groups'] = [];
        }
        
        if ( ! isset( $this->editor['field_manager']['fields'] ) || ! is_array( $this->editor['field_manager']['fields'] ) ) {
            $this->editor['field_manager']['fields'] = [];
        }

        $uniqid = uniqid();
        $group_name = mb_substr( sanitize_text_field( $group_name ), 0, 50 );

        $error = $this->invalid_group_name( $group_name, $this->editor['field_manager']['groups'] );

        if ( $error ) {
            return wp_send_json_error( $error );
        }

        $this->editor['field_manager']['groups'][ $uniqid ] = $group_name;
        $this->editor['field_manager']['fields'][ $uniqid ] = [];

        // Update the field_manager group
        $this->settings->update( 'editor', $this->editor );

        wp_send_json_success( [ 'id' => $uniqid, 'name' => $group_name ] );
    }

    /**
     * Check a group name against the two rules WordPress applies to its own
     * user-facing labels, and only those two.
     *
     * @since  3.0.0
     * @param  string $group_name The sanitized name.
     * @param  array  $taken      Names already in use, keyed by group id.
     * @return string An error message, or '' when the name is fine.
     */
    private function invalid_group_name( $group_name, $taken ) {
        if ( '' === trim( $group_name ) ) {
            return __( 'Please enter a group name.', 'wp-store-locator' );
        }

        if ( in_array( $group_name, $taken, true ) ) {
            return __( 'Duplicate group names are not allowed. Please use unique names for each group.', 'wp-store-locator' );
        }

        return '';
    }

    /**
     * Update the existing groups.
     *
     * @since  3.0.0
     * @return json
     */
    private function update_group() {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in actions() method
        if ( isset( $_POST['value'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in actions(), data sanitized below
            $values = wp_unslash( $_POST['value'] );

            if ( ! is_array( $values ) ) {
                return wp_send_json_error( __( 'Invalid group data, please reload the page and try again.', 'wp-store-locator' ) );
            }

            $sanitized = [];

            foreach ( $values as $group_id => $group_name ) {
                $sanitized[ sanitize_key( $group_id ) ] = mb_substr( sanitize_text_field( $group_name ), 0, 50 );
            }

            $stored = isset( $this->editor['field_manager']['groups'] ) && is_array( $this->editor['field_manager']['groups'] ) ? $this->editor['field_manager']['groups'] : [];

            $taken = array_diff_key( $stored, $sanitized );

            /*
             * Validate the whole batch before writing any of it. Renaming as we
             * go would leave the groups ahead of a rejected one already stored,
             * so the screen the user is still looking at no longer matches the
             * database.
             */
            foreach ( $sanitized as $group_id => $group_name ) {
                $error = $this->invalid_group_name( $group_name, $taken );

                if ( $error ) {
                    return wp_send_json_error( $error );
                }

                $taken[ $group_id ] = $group_name;
            }

            foreach ( $sanitized as $group_id => $group_name ) {
                $this->editor['field_manager']['groups'][ $group_id ] = $group_name;

                if ( ! isset( $this->editor['field_manager']['fields'][ $group_id ] ) ) {
                    $this->editor['field_manager']['fields'][ $group_id ] = [];
                }
            }

            // Update the field_manager group
            $this->settings->update( 'editor', $this->editor );
        }

        wp_send_json_success();
    }

    /**
     * Remove a single group and all related
     * fields based on the provided group id.
     *
     * @since  3.0.0
     * @param  string $args
     * @return json
     */
    private function delete_group( $args ) {
        $group_id = sanitize_key( $args );

        if ( isset( $this->editor['field_manager']['groups'][ $group_id ] ) ) {
            unset( $this->editor['field_manager']['groups'][ $group_id ] );
            unset( $this->editor['field_manager']['fields'][ $group_id ] );

            // Update the field_manager group
            $this->settings->update( 'editor', $this->editor );
        }

        wp_send_json_success();
    }

    /**
     * Delete a single field
     *
     * @since  3.0.0
     * @param  string $args
     * @return json
     */
    private function delete_field( $args ) {
        if ( ! is_array( $args ) || ! isset( $args['field_id'], $args['group_id'] ) ) {
            return wp_send_json_error( __( 'Invalid field data, please reload the page and try again.', 'wp-store-locator' ) );
        }

        $field_id = sanitize_key( $args['field_id'] );
        $group_id = sanitize_key( $args['group_id'] );

        if ( ! isset( $this->editor['field_manager']['fields'][ $group_id ][ $field_id ] ) ) {
            return wp_send_json_error( __( 'The field could not be found.', 'wp-store-locator' ) );
        }

        unset( $this->editor['field_manager']['fields'][ $group_id ][ $field_id ] );

        // Update the field_manager group
        $this->settings->update( 'editor', $this->editor );

        wp_send_json_success();
    }

    /**
     * Build the group dropdown.
     *
     * @since 3.0.0
     */
    public function get_groups_dropdown() {
        $selected_group_id = '';
        $dropdowns = '';

        if ( isset( $this->editor['field_manager']['groups'] ) && is_array( $this->editor['field_manager']['groups'] ) ) {
            $dropdowns = '<select class="wpsl-field-group-list" name="wpsl_editor[fields][selected_group]">';

            foreach ( $this->editor['field_manager']['groups'] as $group_id => $group_name ) {
                if ( ! $selected_group_id ) {
                    $selected_group_id = $group_id;
                }

                $group_name_display = mb_strlen( $group_name ) > 45 ? mb_substr( $group_name, 0, 45 ) . '...' : $group_name;

                $dropdowns .= '<option value="' . esc_attr( $group_id ) . '">' . esc_html( $group_name_display ) . '</option>';
            }

            $dropdowns .= '</select>';
        }

        return $dropdowns;
    }
}