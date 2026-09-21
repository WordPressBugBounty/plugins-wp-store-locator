<?php
/**
 * AJAX endpoints for the custom marker manager.
 *
 * @since 3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Markers\Custom_Markers;

class Custom_Marker_Ajax {

    /**
     * @var Custom_Markers
     */
    private $markers;

    public function __construct( Custom_Markers $markers ) {
        $this->markers = $markers;

        add_action( 'wp_ajax_wpsl_marker_manager_save',   [ $this, 'save_marker' ] );
        add_action( 'wp_ajax_wpsl_marker_manager_delete', [ $this, 'delete_marker' ] );
        add_action( 'wp_ajax_wpsl_marker_manager_tag_logo',   [ $this, 'tag_logo' ] );
        add_action( 'wp_ajax_wpsl_marker_manager_untag_logo', [ $this, 'untag_logo' ] );
    }

    /**
     * Save ( create or update ) a custom marker.
     *
     * @since  3.0.0
     * @return void
     */
    public function save_marker() {
        check_ajax_referer( 'wpsl_marker_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to manage markers.', 'wp-store-locator' ) ] );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field in Custom_Markers::sanitize_marker().
        $saved = $this->markers->save_marker( $_POST );

        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( [ 'message' => $saved->get_error_message() ] );
        }

        wp_send_json_success( [
            'marker'   => $saved,
            'data_uri' => $this->markers->get_data_uri( $saved['id'] ),
            'size'     => 'image' === $saved['shape']
                ? Custom_Markers::get_image_render_size( $saved['image_w'], $saved['image_h'], $saved['size'] )
                : Custom_Markers::get_render_size( $saved['shape'], $saved['size'] ),
            'picker_height' => Custom_Markers::get_picker_height(
                $saved['shape'],
                34,
                'image' === $saved['shape'] ? [ $saved['image_w'], $saved['image_h'] ] : null
            ),
        ] );
    }

    /**
     * Delete a custom marker.
     *
     * @since  3.0.0
     * @return void
     */
    public function delete_marker() {
        check_ajax_referer( 'wpsl_marker_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to manage markers.', 'wp-store-locator' ) ] );
        }

        $id = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';

        if ( '' === $id || ! $this->markers->delete_marker( $id ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Unknown marker id.', 'wp-store-locator' ) ] );
        }

        wp_send_json_success( [ 'markers' => $this->markers->get_markers() ] );
    }

    /**
     * Mark an attachment as a reusable marker logo.
     *
     * @since  3.0.0
     * @return void
     */
    public function tag_logo() {
        $logo_id = $this->validated_logo_id();

        update_post_meta( $logo_id, Custom_Markers::LOGO_META_KEY, time() );

        wp_send_json_success( [] );
    }

    /**
     * Remove an attachment from the reusable marker logos.
     *
     * @since  3.0.0
     * @return void
     */
    public function untag_logo() {
        $logo_id = $this->validated_logo_id();
        $in_use  = $this->markers->get_markers_using_logo( $logo_id );

        if ( $in_use ) {
            wp_send_json_error(
                [
                    'code'    => 'logo_in_use',
                    'message' => $this->logo_in_use_message( count( $in_use ) ),
                    'markers' => $in_use,
                ]
            );
        }

        delete_post_meta( $logo_id, Custom_Markers::LOGO_META_KEY );

        wp_send_json_success( [] );
    }

    /**
     * Why a logo cannot be untagged, and what to do about it.
     *
     * @since  3.0.0
     * @param  int $count How many markers are using the logo, at least one.
     * @return string
     */
    private function logo_in_use_message( $count ) {
        return _n(
            'This marker is using the logo. Give it a different icon in the Library, or delete it, and then the logo can be removed here.',
            'These markers are using the logo. Give them a different icon in the Library, or delete them, and then the logo can be removed here.',
            $count,
            'wp-store-locator'
        );
    }

    /**
     * Shared nonce, capability, and image check for the tag_logo() and
     * untag_logo() endpoints.
     *
     * Sends the JSON error and ends the request on any failure, so callers
     * only see a valid attachment id.
     *
     * @since  3.0.0
     * @return int The validated attachment id.
     */
    private function validated_logo_id() {
        check_ajax_referer( 'wpsl_marker_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to manage markers.', 'wp-store-locator' ) ] );
        }

        $logo_id = isset( $_POST['logo_id'] ) ? absint( $_POST['logo_id'] ) : 0;

        if ( ! $logo_id || ! wp_attachment_is_image( $logo_id ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Not a usable image attachment.', 'wp-store-locator' ) ] );
        }

        return $logo_id;
    }
}