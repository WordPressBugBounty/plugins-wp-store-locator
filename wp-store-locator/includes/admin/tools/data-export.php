<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_init', 'wpsl_single_location_export' );

/**
 * Handle the export of a single store location.
 *
 * Creates a CSV file holding the location details
 * that can be handed over in case a GDPR related
 * data access request is received.
 *
 * @since  2.2.15
 * @return void
 */
function wpsl_single_location_export() {
    if ( isset( $_GET['wpsl_data_export'] ) && isset( $_GET['wpsl_export_nonce'] ) ) {
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wpsl_export_nonce'] ) ), 'wpsl_export_' . $post_id ) )
            return;

        if ( is_int( wp_is_post_revision( $post_id ) ) )
            return;

        if ( ! current_user_can( 'edit_post', $post_id ) )
            return;

        $meta_fields  = [];
        $wp_field_map = [
            'wpsl_id'     => 'ID',
            'name'        => 'post_title',
            'status'      => 'post_status',
            'permalink'   => 'post_name',
            'description' => 'post_content',
            'excerpt'     => 'post_excerpt',
            'author'      => 'post_author',
            'date'        => 'post_date'
        ];

        $meta_box_fields = wpsl_get_service( 'metaboxes' )->meta_box_fields();
        $fields          = array_keys( $wp_field_map );
        
        array_push( $fields, 'image', 'category', 'tags' );

        foreach ( $meta_box_fields as $k => $field_section ) {
            foreach ( $field_section as $field_name => $field_value ) {
                $meta_fields[] = $field_name;
            }
        }

        $meta_data = get_post_custom( $post_id );
        $post_meta = [];

        // Loop over the wpsl meta fields, and collect the meta data.
        foreach ( $meta_fields as $meta_field ) {
            if ( $meta_field !== 'hours' ) {
                if ( isset( $meta_data['wpsl_' . $meta_field][0] ) ) {
                    $post_meta['data'][$meta_field] = $meta_data['wpsl_' . $meta_field][0];
                } else {
                    $post_meta['data'][$meta_field] = '';
                }

                $post_meta['headers'][] = $meta_field;
            }
        }

        // Make it possible to add additional custom data from for example ACF
        $post_meta = apply_filters( 'wpsl_single_location_export_data', $post_meta, $post_id );

        if ( $post_meta ) {
            $file_name = 'wpsl-export-' . $post_id . '-' . gmdate('Ymd' ) . '.csv';

            // Set the download headers for the CSV file.
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );

            $output = fopen( 'php://output', 'w' );

            // Neutralize spreadsheet formula injection: store meta is editable by
            // the store manager role, so a value like =cmd|'/c calc'!A1 must not be
            // handed to Excel / Sheets as a live formula when the admin opens the file.
            $safe_data = array_map( 'wpsl_escape_csv_field', $post_meta['data'] );

            fputcsv( $output, $post_meta['headers'] );
            fputcsv( $output, $safe_data );
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Using php://output stream for CSV export, WP_Filesystem not applicable
            fclose( $output );
        }

        exit();
    }
}