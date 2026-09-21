<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wpsl_import_settings', 'wpsl_import_settings' );
add_action( 'wpsl_export_settings', 'wpsl_export_settings' );

/**
 * Import the WPSL settings from a .json file
 *
 * @since 3.0.0
 */
function wpsl_import_settings() {
    if ( empty( $_POST['wpsl_import_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_import_settings_nonce'] ) ), 'wpsl_import_settings' ) )
        return;

    if ( ! current_user_can( 'manage_wpsl_settings' ) )
        return;

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload array, validated below
    $import = $_FILES['import-wpsl-settings'];

    // Check if file was uploaded
    if ( empty( $import['name'] ) || empty( $import['size'] ) ) {
        add_settings_error( 'import', 'settings-import', esc_html__( 'Please select a file to upload', 'wp-store-locator' ), 'error' );

        return;
    }

    // Check for upload errors
    if ( $import['error'] !== UPLOAD_ERR_OK ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging for import failures, appropriate for production
        error_log( 'WPSL Import Error: ' . $import['error'] );

        add_settings_error( 'import', 'settings-import', esc_html__( 'There was a problem processing the uploaded file. Please try again', 'wp-store-locator' ), 'error' );

        return;
    }

    // Verify it's a legitimate upload
    if ( ! isset( $import['tmp_name'] ) || ! is_uploaded_file( $import['tmp_name'] ) ) {
        add_settings_error( 'import', 'settings-import', esc_html__( 'Security check failed. Invalid file upload.', 'wp-store-locator' ), 'error' );

        return;
    }

    /**
     * Filter the maximum size of an uploaded settings export.
     *
     * An export carries the artwork of every image marker, base64'd, so the
     * file is no longer bounded by how many settings a site has - it is
     * bounded by the Marker Studio library. Custom_Markers::LOGO_MAX_BYTES
     * caps a single logo at 256 KB; 16 MB leaves room for a large library on
     * top of the settings.
     *
     * @since 3.0.0
     * @param int $max_size The cap, in bytes.
     */
    $max_size = (int) apply_filters( 'wpsl_settings_import_max_bytes', 16 * 1024 * 1024 );

    if ( $import['size'] > $max_size ) {
        add_settings_error(
            'import',
            'settings-import',
            sprintf(
                /* translators: %s: the maximum file size, e.g. "16 MB". */
                esc_html__( 'File is too large. Maximum size is %s.', 'wp-store-locator' ),
                esc_html( size_format( $max_size ) )
            ),
            'error'
        );

        return;
    }

    // Validate file extension
    $filename = sanitize_file_name( $import['name'] );
    $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

    if ( $extension !== 'json' ) {
        add_settings_error( 'import', 'settings-import', esc_html__( 'Incorrect file type, please select a valid .json file', 'wp-store-locator' ), 'error' );

        return;
    }

    // Read the imported file using WP_Filesystem
    global $wp_filesystem;

    if ( empty( $wp_filesystem ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    $file_content = $wp_filesystem->get_contents( $import['tmp_name'] );

    if ( $file_content === false ) {
        add_settings_error( 'import', 'settings-import', esc_html__( 'Failed to read the uploaded file.', 'wp-store-locator' ), 'error' );

        return;
    }

    $result = wpsl_get_service( 'settings_transfer' )->import( json_decode( $file_content, true ) );

    if ( is_wp_error( $result ) ) {
        add_settings_error( 'import', 'settings-import', esc_html( $result->get_error_message() ), 'error' );

        return;
    }

    // Redirect so we can show a custom notice
    wp_safe_redirect( esc_url_raw( add_query_arg( [ 'wpsl-status' => 'settings-import-complete' ], admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_tools&tab=import-export-settings' ) ) ) );

    exit();
}

/**
 * Export the WPSL settings to a .json file
 *
 * @since 3.0.0
 */
function wpsl_export_settings() {
    if ( empty( $_POST['wpsl_export_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_export_settings_nonce'] ) ), 'wpsl_export_settings' ) )
        return;

    if ( ! current_user_can( 'manage_wpsl_settings' ) )
        return;

    wpsl_get_service( 'settings_transfer' )->export();

    exit();
}