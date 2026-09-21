<?php
/**
 * Settings export / import.
 *
 * Moves a site's whole locator configuration to another install: every
 * settings group, the map shapes, the Marker Studio library ( artwork
 * included ) and the custom template sections.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Tools;

use WPSL\Admin\Settings\Section_Editor;
use WPSL\Core\Markers\Custom_Markers;
use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Shapes\Repository as Shapes_Repository;
use WPSL\Core\Shapes\Sanitizer as Shapes_Sanitizer;
use WPSL\Core\Templates\Sections;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Transfer {

    /**
     * Payload format version.
     *
     * 1 is the pre-3.0-release export: settings groups only, no wrapper.
     * Those files still import - see import() - so the number is about what
     * a reader may assume is present, not about refusing older files.
     *
     * @since 3.0.0
     * @var   int
     */
    const FORMAT = 2;

    /**
     * Top-level keys that are not settings groups.
     *
     * @since 3.0.0
     */
    const WRAPPER_KEY          = 'wpsl_export';
    const SHAPES_KEY           = 'wpsl_map_shapes';
    const MARKERS_KEY          = 'wpsl_custom_markers';
    const LOGOS_KEY            = 'wpsl_marker_logos';
    const MARKER_FILES_KEY     = 'wpsl_marker_files';
    const TEMPLATE_SECTIONS_KEY = 'wpsl_template_sections';

    /**
     * Meta key holding the checksum of an imported marker logo.
     *
     * Re-importing the same file must not grow the media library a copy at a
     * time, and the attachment id itself cannot be matched on - it is the
     * source site's, and the whole point of the sideload is that it does not
     * exist here.
     *
     * @since 3.0.0
     */
    const LOGO_HASH_META_KEY = '_wpsl_imported_logo_hash';

    /**
     * Transient carrying the import summary across the redirect.
     *
     * @since 3.0.0
     */
    const SUMMARY_TRANSIENT = 'wpsl_import_summary_';

    /**
     * Keys inside a settings group that must never be taken from a file.
     *
     * @since 3.0.0
     * @var   string[]
     */
    const FORBIDDEN_KEYS = [ '__proto__', 'prototype', 'constructor' ];

    /**
     * Core settings manager.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Custom marker repository.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Markers\Custom_Markers
     */
    private $custom_markers;

    /**
     * Map shapes repository.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Shapes\Repository
     */
    private $shapes;

    /**
     * Template sections service.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Templates\Sections
     */
    private $template_sections;

    /**
     * What the last import did, for the notice after the redirect.
     *
     * @since 3.0.0
     * @var   array
     */
    private $summary = [];

    /**
     * The marker image files carried by the file being imported, keyed by filename.
     *
     * @since 3.0.0
     * @var   array
     */
    private $marker_files = [];

    /**
     * Image markers the import created from those files, keyed by filename.
     *
     * @since 3.0.0
     * @var   string[] Filename => custom marker id.
     */
    private $file_marker_ids = [];

    /**
     * Constructor.
     *
     * @since 3.0.0
     * @param WpslSettings      $settings          Core settings manager
     * @param Custom_Markers    $custom_markers    Marker Studio repository
     * @param Shapes_Repository $shapes            Map shapes repository
     * @param Sections          $template_sections Template sections service
     */
    public function __construct( WpslSettings $settings, Custom_Markers $custom_markers, Shapes_Repository $shapes, Sections $template_sections ) {
        $this->settings          = $settings;
        $this->custom_markers    = $custom_markers;
        $this->shapes            = $shapes;
        $this->template_sections = $template_sections;
    }

    /**
     * The name of the themes table.
     *
     * @since  3.0.0
     * @return string
     */
    private function template_table() {
        global $wpdb;

        return $wpdb->prefix . 'wpsl_themes';
    }

    /**
     * Assemble the export payload.
     *
     * @since  3.0.0
     * @return array
     */
    public function build_export() {
        $export = [
            self::WRAPPER_KEY => [
                'format'   => self::FORMAT,
                'version'  => defined( 'WPSL_VERSION_NUM' ) ? WPSL_VERSION_NUM : '',
                'site_url' => home_url(),
                'exported' => gmdate( 'c' ),
            ],
        ];

        foreach ( $this->settings->get_groups() as $group ) {
            $export[ 'wpsl_' . $group ] = $this->settings->get_group( $group );
        }

        $markers = $this->custom_markers->get_markers();

        $export[ self::MARKERS_KEY ]           = $markers;
        $export[ self::LOGOS_KEY ]             = $this->export_marker_logos( $markers );
        $export[ self::MARKER_FILES_KEY ]      = $this->export_marker_files( isset( $export['wpsl_markers'] ) ? $export['wpsl_markers'] : [] );
        $export[ self::SHAPES_KEY ]            = $this->shapes->get_collection();
        $export[ self::TEMPLATE_SECTIONS_KEY ] = $this->export_template_sections();

        /**
         * Filter the settings export payload.
         *
         * Add-ons store their settings in their own options, so this is where
         * they join the file. Use the same key on the import side through
         * 'wpsl_import_settings_payload'.
         *
         * @since 3.0.0
         * @param array $export The payload, keyed by option name.
         */
        return apply_filters( 'wpsl_settings_export_payload', $export );
    }

    /**
     * Send the export as a .json download.
     *
     * @since  3.0.0
     * @return void
     */
    public function export() {
        $file_name = apply_filters( 'wpsl_settings_export_filename', 'wpsl-settings-export-' . strtolower( str_replace( ' ', '-', get_bloginfo() ) ) . '_' . gmdate( 'd-m-Y' ) ) . '.json';

        header( 'Content-Description: File Transfer' );
        header( 'Content-Disposition: attachment; filename="' . $file_name . '"' );
        header( 'Content-Type: application/json; charset=utf-8' );

        wp_send_json( $this->build_export() );
    }

    /**
     * Collect the artwork behind every image marker.
     *
     * @since  3.0.0
     * @param  array $markers The marker library.
     * @return array Keyed by the source site's attachment id.
     */
    private function export_marker_logos( $markers ) {
        $logos = [];

        foreach ( $markers as $marker ) {
            $logo_id = isset( $marker['logo_id'] ) ? absint( $marker['logo_id'] ) : 0;

            if ( ! $logo_id || isset( $logos[ $logo_id ] ) || ! Custom_Markers::is_usable_logo( $logo_id ) ) {
                continue;
            }

            $file = get_attached_file( $logo_id );

            if ( ! $file || ! file_exists( $file ) ) {
                continue;
            }

            // is_usable_logo() already held the file to LOGO_MAX_BYTES, so the
            // read is bounded before it starts.
            $bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment file, read for embedding.

            if ( false === $bytes ) {
                continue;
            }

            $logos[ $logo_id ] = [
                'filename' => wp_basename( $file ),
                'mime'     => (string) get_post_mime_type( $logo_id ),
                'title'    => get_the_title( $logo_id ),
                'hash'     => sha1( $bytes ),
                'data'     => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary transport inside a JSON file.
            ];
        }

        return $logos;
    }

    /**
     * Collect the image files the marker settings name from a folder of the
     * site's own ( wpsl_admin_marker_dir ).
     *
     * The receiving site usually has no such folder, so without the file the
     * setting would point at an image that isn't there. The import turns each
     * one into an image marker instead. An SVG can't be one ( see
     * Custom_Markers::LOGO_BLOCKED ), so it isn't carried.
     *
     * @since  3.0.0
     * @param  array $values The markers settings group.
     * @return array Keyed by filename.
     */
    private function export_marker_files( $values ) {
        $files = [];
        $dirs  = wpsl_marker_files();
        $max   = (int) apply_filters( 'wpsl_marker_logo_max_bytes', Custom_Markers::LOGO_MAX_BYTES, 0 );

        foreach ( Custom_Markers::SETTING_SLOTS as $key ) {
            $filename = isset( $values[ $key ] ) ? $values[ $key ] : '';

            if ( ! is_string( $filename ) || '' === $filename || isset( $files[ $filename ] ) || ! isset( $dirs[ $filename ] ) || wpsl_marker_is_bundled( $filename ) ) {
                continue;
            }

            $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

            if ( in_array( $extension, Custom_Markers::LOGO_BLOCKED['extensions'], true ) ) {
                continue;
            }

            $path = $dirs[ $filename ] . $filename;

            if ( ! is_readable( $path ) || ( $max > 0 && filesize( $path ) > $max ) ) {
                continue;
            }

            $bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local marker file, read for embedding.

            if ( false === $bytes ) {
                continue;
            }

            $filetype = wp_check_filetype( $filename );

            $files[ $filename ] = [
                'filename' => $filename,
                'mime'     => (string) $filetype['type'],
                'title'    => pathinfo( $filename, PATHINFO_FILENAME ),
                'hash'     => sha1( $bytes ),
                'data'     => base64_encode( $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary transport inside a JSON file.
            ];
        }

        return $files;
    }

    /**
     * Read the custom template sections out of the themes table.
     *
     * @since  3.0.0
     * @return array[] One entry per row.
     */
    private function export_template_sections() {
        global $wpdb;

        $table = $this->template_table();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no core API, name from $wpdb->prefix.
        $rows = $wpdb->get_results( "SELECT template, section, language, content FROM {$table} ORDER BY id ASC", ARRAY_A );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * Import a decoded export payload.
     *
     * logos have to exist before the markers that name them can be validated, 
     * the template sections have to exist before appearance's active_custom_sections 
     * can be checked against them.
     *
     * @since  3.0.0
     * @param  array $data The decoded .json contents.
     * @return true|\WP_Error True on success, WP_Error when the file is not an export.
     */
    public function import( $data ) {
        if ( ! is_array( $data ) || ! isset( $data['wpsl_api'] ) || ! is_array( $data['wpsl_api'] ) ) {
            return new \WP_Error( 'wpsl_import_invalid', __( 'The imported file doesn\'t contain valid data', 'wp-store-locator' ) );
        }

        /**
         * Filter the decoded payload before it is imported.
         *
         * The counterpart to 'wpsl_settings_export_payload' - an add-on that
         * added a key there reads it back here.
         *
         * @since 3.0.0
         * @param array $data The decoded payload.
         */
        $data = apply_filters( 'wpsl_import_settings_payload', $data );

        $this->summary         = [];
        $this->file_marker_ids = [];
        $this->marker_files    = ( isset( $data[ self::MARKER_FILES_KEY ] ) && is_array( $data[ self::MARKER_FILES_KEY ] ) ) ? $data[ self::MARKER_FILES_KEY ] : [];

        $logo_map =$this->import_marker_logos( isset( $data[ self::LOGOS_KEY ] ) ? $data[ self::LOGOS_KEY ] : [] );
        $marker_ids = $this->import_custom_markers( isset( $data[ self::MARKERS_KEY ] ) ? $data[ self::MARKERS_KEY ] : null, $logo_map );
        $sections   = $this->import_template_sections( isset( $data[ self::TEMPLATE_SECTIONS_KEY ] ) ? $data[ self::TEMPLATE_SECTIONS_KEY ] : null );

        $this->import_settings_groups( $data, $sections, $marker_ids );

        // The image markers made from marker files are in use, not stale.
        if ( null !== $marker_ids ) {
            $marker_ids = array_merge( $marker_ids, array_values( $this->file_marker_ids ) );
        }

        $this->import_map_shapes( isset( $data[ self::SHAPES_KEY ] ) ? $data[ self::SHAPES_KEY ] : null );
        $this->release_stale_marker_references( $marker_ids );
        $this->revalidate_api_keys();

        $this->settings->clear_cache();

        wpsl_flush_store_cache();
        delete_transient( 'wpsl_has_category_images' );

        $this->store_summary();

        return true;
    }

    /**
     * Re-create the marker artwork in the media library.
     *
     * @since  3.0.0
     * @param  mixed $logos The exported logo map, keyed by source attachment id.
     * @return array Source attachment id => local attachment id.
     */
    private function import_marker_logos( $logos ) {
        if ( ! is_array( $logos ) || ! $logos ) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $map     = [];
        $skipped = 0;

        foreach ( $logos as $source_id => $logo ) {
            $local_id = $this->sideload_logo( $logo );

            if ( $local_id ) {
                $map[ (int) $source_id ] = $local_id;
            } else {
                $skipped++;
            }
        }

        if ( $map ) {
            $this->summary['logos'] = count( $map );
        }

        if ( $skipped ) {
            $this->summary['logos_skipped'] = $skipped;
        }

        return $map;
    }

    /**
     * Write one exported logo to the uploads directory as an attachment.
     *
     * @since  3.0.0
     * @param  mixed $logo The exported entry.
     * @return int The attachment id, or 0 when the entry could not be used.
     */
    private function sideload_logo( $logo ) {
        if ( ! is_array( $logo ) || empty( $logo['data'] ) || ! is_string( $logo['data'] ) ) {
            return 0;
        }

        $bytes = base64_decode( $logo['data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary transport inside a JSON file.

        if ( false === $bytes || '' === $bytes ) {
            return 0;
        }

        $max = (int) apply_filters( 'wpsl_marker_logo_max_bytes', Custom_Markers::LOGO_MAX_BYTES, 0 );

        if ( $max > 0 && strlen( $bytes ) > $max ) {
            return 0;
        }

        $hash = sha1( $bytes );

        $existing = $this->find_imported_logo( $hash );

        if ( $existing ) {
            return $existing;
        }

        $filename = sanitize_file_name( isset( $logo['filename'] ) ? $logo['filename'] : 'wpsl-marker-logo.png' );

        /*
         * An SVG can never be a marker logo (is_usable_logo() refuses one),
         * so reject a mislabelled extension before the write lands on disk.
         */
        $filetype = wp_check_filetype( $filename );

        if ( empty( $filetype['type'] ) || 0 !== strpos( $filetype['type'], 'image/' ) ) {
            return 0;
        }

        if ( in_array( strtolower( (string) $filetype['ext'] ), Custom_Markers::LOGO_BLOCKED['extensions'], true )
            || in_array( strtolower( $filetype['type'] ), Custom_Markers::LOGO_BLOCKED['mimes'], true ) ) {
            return 0;
        }

        $upload = wp_upload_bits( $filename, null, $bytes );

        if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
            return 0;
        }

        if ( ! wp_getimagesize( $upload['file'] ) ) {
            wp_delete_file( $upload['file'] );

            return 0;
        }

        $title = isset( $logo['title'] ) ? sanitize_text_field( $logo['title'] ) : '';

        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => $filetype['type'],
            'post_title'     => $title ? $title : preg_replace( '/\.[^.]+$/', '', wp_basename( $upload['file'] ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $upload['file'] );

        if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
            wp_delete_file( $upload['file'] );

            return 0;
        }

        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

        // Tagged so the Studio lists it; hashed so re-import finds it
        // instead of copying.
        update_post_meta( $attachment_id, Custom_Markers::LOGO_META_KEY, time() );
        update_post_meta( $attachment_id, self::LOGO_HASH_META_KEY, $hash );

        return (int) $attachment_id;
    }

    /**
     * Find an attachment a previous import already created from these bytes.
     *
     * @since  3.0.0
     * @param  string $hash sha1 of the file contents.
     * @return int The attachment id, or 0.
     */
    private function find_imported_logo( $hash ) {
        $ids = get_posts( [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'meta_key'       => self::LOGO_HASH_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One indexed meta lookup per imported logo.
            'meta_value'     => $hash,                    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
        ] );

        if ( ! $ids ) {
            return 0;
        }

        $id = (int) $ids[0];

        return Custom_Markers::is_usable_logo( $id ) ? $id : 0;
    }

    /**
     * Import the Marker Studio library, replacing what is stored.
     *
     * @since  3.0.0
     * @param  mixed $markers  The exported library.
     * @param  array $logo_map Source attachment id => local attachment id.
     * @return array|null The ids that made it in, or null when the payload carried no library.
     */
    private function import_custom_markers( $markers, $logo_map ) {
        if ( ! is_array( $markers ) ) {
            return null;
        }

        $imported = [];
        $skipped  = [];

        foreach ( $markers as $id => $marker ) {
            if ( ! is_array( $marker ) ) {
                continue;
            }

            // The stored key is the authority on the id: a marker settings
            // slot, a category and a store all point at it by "custom:{id}",
            // so it has to survive the trip unchanged.
            $marker['id'] = is_string( $id ) ? $id : ( isset( $marker['id'] ) ? $marker['id'] : '' );

            $source_logo = isset( $marker['logo_id'] ) ? absint( $marker['logo_id'] ) : 0;

            $marker['logo_id'] = ( $source_logo && isset( $logo_map[ $source_logo ] ) ) ? $logo_map[ $source_logo ] : 0;

            $sanitized = $this->custom_markers->sanitize_marker( $marker );

            if ( is_wp_error( $sanitized ) || empty( $sanitized['id'] ) ) {
                $skipped[] = isset( $marker['name'] ) ? sanitize_text_field( $marker['name'] ) : (string) $id;

                continue;
            }

            $imported[ $sanitized['id'] ] = $sanitized;
        }

        update_option( Custom_Markers::OPTION_NAME, $imported, false );

        if ( $imported ) {
            $this->summary['markers'] = count( $imported );
        }

        if ( $skipped ) {
            $this->summary['markers_skipped'] = $skipped;
        }

        return array_keys( $imported );
    }

    /**
     * Import the custom template sections, replacing the rows they collide with.
     *
     * The code goes through the same cleaning the section editor applies on
     * save, so a file cannot introduce markup the editor itself would refuse.
     *
     * @since  3.0.0
     * @param  mixed $rows The exported rows.
     * @return array|null The '{template}_{section}' names that were written, or null when the payload carried none.
     */
    private function import_template_sections( $rows ) {
        global $wpdb;

        if ( ! is_array( $rows ) ) {
            return null;
        }

        /*
         * The cleaner keeps the <% %> template blocks, which run as JavaScript
         * for every visitor, so writing a section takes the same capability
         * the section editor takes. Without it the file is imported as if it
         * carried no sections, and the summary says how many were left out.
         */
        if ( $rows && ! Section_Editor::current_user_can_edit() ) {
            $this->summary['template_sections_skipped'] = count( $rows );

            return null;
        }

        $editor = wpsl_get_service( 'section_editor' );
        $table  = $this->template_table();
        $names  = [];

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || empty( $row['template'] ) || empty( $row['section'] ) ) {
                continue;
            }

            $template = sanitize_text_field( $row['template'] );
            $section  = sanitize_key( $row['section'] );
            $language = Sections::normalize_lang( isset( $row['language'] ) ? $row['language'] : '' );
            $content  = isset( $row['content'] ) ? (string) $row['content'] : '';

            if ( '' === $template || '' === $section || '' === trim( $content ) ) {
                continue;
            }

            $content = $editor->clean_section_template( $content );

            if ( 'listing' === $section ) {
                $content = $editor->ensure_listing_store_id( $content );
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no core API, name from $wpdb->prefix.
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE template = %s AND section = %s AND language = %s", $template, $section, $language ) );

            if ( $existing ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no core API.
                $wpdb->update( $table, [ 'content' => $content ], [ 'id' => (int) $existing ], [ '%s' ], [ '%d' ] );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no core API.
                $wpdb->insert( $table, [
                    'template' => $template,
                    'section'  => $section,
                    'language' => $language,
                    'content'  => $content,
                ], [ '%s', '%s', '%s', '%s' ] );
            }

            $names[] = $template . '_' . $section;
        }

        $names = array_values( array_unique( $names ) );

        if ( $names ) {
            $this->summary['template_sections'] = count( $names );
        }

        return $names;
    }

    /**
     * Import the settings groups.
     *
     * @since  3.0.0
     * @param  array      $data       The decoded payload.
     * @param  array|null $sections   The '{template}_{section}' names that were imported.
     * @param  array|null $marker_ids The custom marker ids that were imported.
     * @return void
     */
    private function import_settings_groups( $data, $sections, $marker_ids ) {
        $imported = 0;

        foreach ( $this->settings->get_groups() as $group ) {
            $values = $this->group_payload( $data, $group );

            if ( null === $values ) {
                continue;
            }

            if ( 'appearance' === $group ) {
                $values = $this->sanitize_appearance( $values, $sections );
            }

            if ( 'markers' === $group ) {
                $values = $this->heal_marker_slots( $values, $marker_ids );
            }

            $this->settings->update( $group, $values );

            $imported++;
        }

        $this->summary['groups'] = $imported;
    }

    /**
     * The values a file carries for one settings group.
     *
     * Beta 1 exported the local pages group under its pre-rename name
     * ( wpsl_local_seo ), so a file from there holds permalinks settings no
     * current group name matches. Adopt that payload for local_pages rather
     * than dropping it.
     *
     * @since  3.0.0
     * @param  array  $data  The decoded payload.
     * @param  string $group The settings group name.
     * @return array|null The sanitized group values, or null when the file carries none.
     */
    private function group_payload( $data, $group ) {
        $option_name = 'wpsl_' . $group;

        if ( isset( $data[ $option_name ] ) && is_array( $data[ $option_name ] ) ) {
            return $this->sanitize_tree( $data[ $option_name ] );
        }

        if ( 'local_pages' === $group && isset( $data['wpsl_local_seo'] ) && is_array( $data['wpsl_local_seo'] ) ) {
            $this->summary['legacy_local_seo'] = true;

            return $this->sanitize_tree( $data['wpsl_local_seo'] );
        }

        return null;
    }

    /**
     * Import the map shapes, replacing the stored collection.
     *
     * @since  3.0.0
     * @param  mixed $collection The exported FeatureCollection.
     * @return void
     */
    private function import_map_shapes( $collection ) {
        if ( ! is_array( $collection ) ) {
            return;
        }

        $sanitizer = new Shapes_Sanitizer();
        $sanitized = $sanitizer->sanitize_collection( $collection );

        if ( is_wp_error( $sanitized ) ) {
            $this->summary['shapes_error'] = $sanitized->get_error_message();

            return;
        }

        $this->shapes->save_collection( $sanitized );

        if ( $sanitized['features'] ) {
            $this->summary['shapes'] = count( $sanitized['features'] );
        }
    }

    /**
     * Reset marker settings slots to defaults when the marker they name
     * didn't survive the import, so a slot doesn't keep a "custom:{id}"
     * that no longer resolves.
     *
     * @since  3.0.0
     * @param  array      $values     The markers group.
     * @param  array|null $marker_ids The custom marker ids that were imported.
     * @return array
     */
    private function heal_marker_slots( $values, $marker_ids ) {
        $files = wpsl_marker_files();

        foreach ( Custom_Markers::SETTING_SLOTS as $key ) {
            if ( ! isset( $values[ $key ] ) || ! is_string( $values[ $key ] ) || '' === $values[ $key ] ) {
                continue;
            }

            $value     = $values[ $key ];
            $custom_id = wpsl_custom_marker_id( $value );

            if ( $custom_id ) {
                if ( null !== $marker_ids && ! in_array( $custom_id, $marker_ids, true ) ) {
                    $values[ $key ] = $this->settings->get_default( 'markers', $key );
                }

                continue;
            }

            // A file this site has, bundled or in its own marker folder.
            if ( isset( $files[ $value ] ) ) {
                continue;
            }

            /*
             * A marker image from the other site's own folder. With the file
             * in the export it becomes an image marker here, otherwise the
             * slot takes the default so it doesn't load a missing image.
             */
            $image_marker_id = $this->image_marker_from_file( $value );

            if ( $image_marker_id ) {
                $values[ $key ] = 'custom:' . $image_marker_id;
            } else {
                $values[ $key ] = $this->settings->get_default( 'markers', $key );

                $this->summary['marker_files_missing'][] = $value;
            }
        }

        if ( ! empty( $this->summary['marker_files_missing'] ) ) {
            $this->summary['marker_files_missing'] = array_values( array_unique( $this->summary['marker_files_missing'] ) );
        }

        return $values;
    }

    /**
     * Turn a marker image carried in the export into an image marker.
     *
     * The same file in more than one slot becomes one marker.
     *
     * @since  3.0.0
     * @param  string $filename The filename the marker setting names.
     * @return string The custom marker id, or '' when the file isn't in the export or can't be used.
     */
    private function image_marker_from_file( $filename ) {
        if ( isset( $this->file_marker_ids[ $filename ] ) ) {
            return $this->file_marker_ids[ $filename ];
        }

        if ( empty( $this->marker_files[ $filename ] ) || ! is_array( $this->marker_files[ $filename ] ) ) {
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $logo_id = $this->sideload_logo( $this->marker_files[ $filename ] );

        if ( ! $logo_id ) {
            return '';
        }

        $marker = $this->custom_markers->save_marker( [
            'name'    => pathinfo( $filename, PATHINFO_FILENAME ),
            'shape'   => 'image',
            'logo_id' => $logo_id,
        ] );

        if ( is_wp_error( $marker ) || empty( $marker['id'] ) ) {
            return '';
        }

        $this->file_marker_ids[ $filename ] = $marker['id'];

        $this->summary['marker_files_converted'][] = $filename;

        return $marker['id'];
    }

    /**
     * Clear categories and stores still pointing at a marker the import
     * replaced.
     *
     * @since  3.0.0
     * @param  array|null $marker_ids The custom marker ids that were imported.
     * @return void
     */
    private function release_stale_marker_references( $marker_ids ) {
        if ( null === $marker_ids ) {
            return;
        }

        $stale = [];

        foreach ( array_keys( $this->custom_markers->get_reference_counts() ) as $value ) {
            $id = wpsl_custom_marker_id( $value );

            if ( $id && ! in_array( $id, $marker_ids, true ) ) {
                $stale[] = $value;
            }
        }

        if ( $stale ) {
            $this->custom_markers->release_references( $stale );

            $this->summary['references_released'] = count( $stale );
        }
    }

    /**
     * Re-test imported API keys against this site.
     *
     * The wpsl_valid_* flags don't travel: Validate_Keys sends this site's
     * URL as the Referer, so a URL-restricted token is evaluated against the
     * domain asking. The same key can be valid on one install and invalid on
     * another, so copying the flag would claim a working map where there is
     * none.
     *
     * @since  3.0.0
     * @return void
     */
    private function revalidate_api_keys() {
        $api = $this->settings->get_group( 'api' );

        // Validate_Keys method name => [ api setting, its flag, display name ].
        $checks = [
            'server'           => [ 'gmaps_server_key', 'wpsl_valid_gmaps_server_key', 'Google Maps' ],
            'mapbox'           => [ 'mapbox_key', 'wpsl_valid_mapbox_key', 'Mapbox' ],
            'stadia'           => [ 'stadia_key', 'wpsl_valid_stadia_key', 'Stadia Maps' ],
            'openrouteservice' => [ 'openrouteservice_key', 'wpsl_valid_openrouteservice_key', 'OpenRouteService' ],
        ];

        $validate = wpsl_get_service( 'validate_keys' );

        if ( ! $validate ) {
            return;
        }

        foreach ( $checks as $type => $check ) {
            list( $setting, $option, $name ) = $check;

            $key = isset( $api[ $setting ] ) ? trim( (string) $api[ $setting ] ) : '';

            if ( '' === $key ) {
                continue;
            }

            $validate->test_response( $type, $key, false );

            /*
             * Report either way: the flag decides whether maps load, and
             * someone who just moved a key between domains needs to know.
             */
            if ( '1' == get_option( $option ) ) {
                $this->summary['valid_keys'][] = $name;
            } else {
                $this->summary['invalid_keys'][] = $name;
            }
        }

        $this->flag_pending_browser_key( $api );
    }

    /**
     * Note the one key this cannot answer for.
     *
     * Only a browser can test the Google browser key. The settings page
     * validates it on "Show response" or on save when the value differs from
     * the stored one, but after an import it doesn't, so the flag stays unset
     * until someone presses the button.
     *
     * @since  3.0.0
     * @param  array $api The imported api group.
     * @return void
     */
    private function flag_pending_browser_key( $api ) {
        if ( ! isset( $api['active_map_service'] ) || 'gmaps' !== $api['active_map_service'] ) {
            return;
        }

        $key = isset( $api['gmaps_browser_key'] ) ? trim( (string) $api['gmaps_browser_key'] ) : '';

        if ( '' === $key || '1' == get_option( 'wpsl_valid_gmaps_browser_key' ) ) {
            return;
        }

        $this->summary['browser_key_pending'] = true;
    }

    /**
     * Sanitize a decoded settings group.
     *
     * @since  3.0.0
     * @param  mixed $value A decoded value.
     * @return mixed
     */
    private function sanitize_tree( $value ) {
        if ( is_array( $value ) ) {
            $out = [];

            foreach ( $value as $key => $item ) {
                if ( is_string( $key ) ) {
                    $key = sanitize_text_field( $key );

                    if ( '' === $key || in_array( $key, self::FORBIDDEN_KEYS, true ) ) {
                        continue;
                    }
                }

                $out[ $key ] = $this->sanitize_tree( $item );
            }

            return $out;
        }

        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
            return $value;
        }

        return $this->sanitize_string( (string) $value );
    }

    /**
     * Sanitize one string out of a settings group.
     *
     * @since  3.0.0
     * @param  string $value The decoded string.
     * @return string
     */
    private function sanitize_string( $value ) {
        return ( false !== strpos( $value, "\n" ) || false !== strpos( $value, "\r" ) )
            ? sanitize_textarea_field( $value )
            : sanitize_text_field( $value );
    }

    /**
     * Validate the appearance group as it is stored.
     *
     * @since  3.0.0
     * @param  array      $input    The decoded appearance group.
     * @param  array|null $sections The '{template}_{section}' names that were imported.
     * @return array
     */
    private function sanitize_appearance( $input, $sections ) {
        $defaults = $this->settings->defaults( 'appearance' );
        $output   = [];

        $output['template_id'] = ! empty( $input['template_id'] ) ? sanitize_text_field( $input['template_id'] ) : $defaults['template_id'];

        $active = [];

        if ( isset( $input['active_custom_sections'] ) && is_array( $input['active_custom_sections'] ) ) {
            foreach ( $input['active_custom_sections'] as $name ) {
                $name = sanitize_text_field( (string) $name );

                if ( '' === $name || ( null !== $sections && ! in_array( $name, $sections, true ) ) ) {
                    continue;
                }

                $active[] = $name;
            }
        }

        $output['active_custom_sections'] = array_values( array_unique( $active ) );

        $output['overwrite_theme_styles'] = $this->flag( $input, 'overwrite_theme_styles' );
        $output['custom_focus_outline']   = $this->flag( $input, 'custom_focus_outline' );
        $output['focus_outline']          = $this->hex( $input, 'focus_outline' );

        $output['theme_colors']  = $this->sanitize_color_set( isset( $input['theme_colors'] ) ? $input['theme_colors'] : [] );
        $output['button_colors'] = $this->sanitize_color_set( isset( $input['button_colors'] ) ? $input['button_colors'] : [] );

        $output['button_styles'] = [];

        if ( isset( $input['button_styles'] ) && is_array( $input['button_styles'] ) ) {
            foreach ( $input['button_styles'] as $action => $style ) {
                if ( isset( $defaults['button_styles'][ $action ] ) && in_array( $style, [ 'primary', 'secondary' ], true ) ) {
                    $output['button_styles'][ $action ] = $style;
                }
            }
        }

        $output['button_styles'] = array_merge( $defaults['button_styles'], $output['button_styles'] );

        $output['map_style'] = $this->sanitize_map_style( isset( $input['map_style'] ) ? $input['map_style'] : [] );

        $preloader = isset( $input['preloader_color'] ) ? sanitize_text_field( $input['preloader_color'] ) : 'black';

        $output['preloader_color']        = in_array( $preloader, [ 'black', 'white', 'custom' ], true ) ? $preloader : 'black';
        $output['preloader_custom_color'] = $this->hex( $input, 'preloader_custom_color' );

        $columns = isset( $input['result_columns'] ) ? absint( $input['result_columns'] ) : 1;

        $output['result_columns'] = in_array( $columns, [ 1, 2, 3 ], true ) ? $columns : 1;

        $layout = isset( $input['filter_layout'] ) ? sanitize_text_field( $input['filter_layout'] ) : 'horizontal';

        $output['filter_layout'] = in_array( $layout, [ 'horizontal', 'stacked', 'nested' ], true ) ? $layout : 'horizontal';

        $icons = isset( $input['icons'] ) && is_array( $input['icons'] ) ? $input['icons'] : [];

        $output['icons'] = [
            'enabled' => $this->flag( $icons, 'enabled' ),
            'address' => $this->choice( $icons, 'address', [ 'marker', 'house', 'building', 'building-outline' ], 'marker' ),
            'phone'   => $this->choice( $icons, 'phone', [ 'phone', 'mobile-phone' ], 'phone' ),
            'email'   => $this->choice( $icons, 'email', [ 'email', 'email-outline' ], 'email' ),
        ];

        $cta = isset( $input['cta'] ) && is_array( $input['cta'] ) ? $input['cta'] : [];

        $output['cta'] = [
            'enabled'        => $this->flag( $cta, 'enabled' ),
            'details'        => $this->flag( $cta, 'details' ),
            'details_target' => $this->choice( $cta, 'details_target', [ 'website', 'landing_page' ], 'website' ),
        ];

        $output['dimensions'] = $this->sanitize_dimensions( isset( $input['dimensions'] ) ? $input['dimensions'] : [], $defaults['dimensions'] );

        $fonts = isset( $input['font_sizes'] ) && is_array( $input['font_sizes'] ) ? $input['font_sizes'] : [];

        $output['font_sizes'] = [ 'overwrite_defaults' => $this->flag( $fonts, 'overwrite_defaults' ) ];

        foreach ( [ 'base', 'location_name', 'cta_buttons' ] as $field ) {
            $size = isset( $fonts[ $field ] ) ? absint( $fonts[ $field ] ) : 0;

            $output['font_sizes'][ $field ] = ( $size >= 12 && $size <= 20 ) ? $size : 14;
        }

        return $output;
    }

    /**
     * Sanitize a theme_colors / button_colors map.
     *
     * @since  3.0.0
     * @param  mixed $colors The decoded map.
     * @return array
     */
    private function sanitize_color_set( $colors ) {
        if ( ! is_array( $colors ) ) {
            return [];
        }

        $output = [];

        foreach ( $colors as $key => $value ) {
            $key = sanitize_key( $key );

            if ( '' === $key ) {
                continue;
            }

            // Gradient angles share the map with the colors, and are degrees.
            if ( false !== strpos( $key, '_angle' ) ) {
                $output[ $key ] = min( 360, absint( $value ) );

                continue;
            }

            $hex = sanitize_hex_color( (string) $value );

            $output[ $key ] = $hex ? $hex : '';
        }

        return $output;
    }

    /**
     * Sanitize the per-provider map style config.
     *
     * Every provider is validated, not just the active one, so switching
     * services later keeps its setup. An import carrying only the active
     * one would silently reset the rest to defaults.
     *
     * @since  3.0.0
     * @param  mixed $input The decoded map_style value.
     * @return array
     */
    private function sanitize_map_style( $input ) {
        $defaults = $this->settings->defaults( 'appearance' );
        $defaults = $defaults['map_style'];

        if ( ! is_array( $input ) ) {
            return $defaults;
        }

        $output = $defaults;

        // The shared MapLibre style JSON URL ( osm / stadia ), https only.
        $maplibre     = isset( $input['maplibre'] ) && is_array( $input['maplibre'] ) ? $input['maplibre'] : [];
        $maplibre_url = isset( $maplibre['custom_url'] ) ? esc_url_raw( trim( (string) $maplibre['custom_url'] ) ) : '';
        $maplibre_url = ( 0 === strpos( $maplibre_url, 'https://' ) ) ? $maplibre_url : '';

        $output['maplibre'] = [
            'custom_url' => $maplibre_url,
            'enabled'    => isset( $maplibre['enabled'] ) ? (int) ! empty( $maplibre['enabled'] ) : 1,
        ];

        $osm         = isset( $input['osm'] ) && is_array( $input['osm'] ) ? $input['osm'] : [];
        $tile_source = $this->choice( $osm, 'tile_source', [ 'default', 'mapbox', 'stadia', 'openfreemap', 'maplibre' ], 'default' );

        // A maplibre tile source with no valid URL cannot resolve; revert.
        if ( 'maplibre' === $tile_source && '' === $maplibre_url ) {
            $tile_source = 'default';
        }

        $output['osm'] = [
            'tile_source' => $tile_source,
            'overwrite_styles' => ( 'mapbox' === $tile_source ) ? 1 : 0,
            'selected'         => ( 'mapbox' === $tile_source ) ? 'mapbox' : 'default',
            'selected_style'   => '',
        ];

        $stadia_styles = wpsl_stadia_styles();
        $stadia        = isset( $input['stadia'] ) && is_array( $input['stadia'] ) ? $input['stadia'] : [];
        $stadia_style  = isset( $stadia['selected_style'] ) ? sanitize_text_field( $stadia['selected_style'] ) : '';

        $stadia_source = $this->choice( $stadia, 'style_source', [ 'stadia', 'maplibre' ], 'stadia' );

        // A maplibre style source with no valid URL cannot resolve; revert.
        if ( 'maplibre' === $stadia_source && '' === $maplibre_url ) {
            $stadia_source = 'stadia';
        }

        $output['stadia'] = [
            'selected_style' => array_key_exists( $stadia_style, $stadia_styles ) ? $stadia_style : 'alidade_smooth',
            'style_source'   => $stadia_source,
        ];

        $gmaps = isset( $input['gmaps'] ) && is_array( $input['gmaps'] ) ? $input['gmaps'] : [];

        $output['gmaps'] = [
            'selected'    => $this->choice( $gmaps, 'selected', [ 'cloud_based', 'json' ], 'cloud_based' ),
            'json'        => isset( $gmaps['json'] ) ? wp_strip_all_tags( (string) $gmaps['json'] ) : '',
            'cloud_based' => isset( $gmaps['cloud_based'] ) ? sanitize_text_field( $gmaps['cloud_based'] ) : '',
        ];

        $mapbox_styles = wpsl_mapbox_classic_styles();
        $mapbox        = isset( $input['mapbox'] ) && is_array( $input['mapbox'] ) ? $input['mapbox'] : [];
        $mapbox_custom = isset( $mapbox['custom_url'] ) ? sanitize_text_field( $mapbox['custom_url'] ) : '';
        $mapbox_custom = ( 0 === strpos( $mapbox_custom, 'mapbox://styles/' ) ) ? $mapbox_custom : '';
        $mapbox_choice = isset( $mapbox['selected'] ) ? sanitize_text_field( $mapbox['selected'] ) : '';

        if ( 'custom' === $mapbox_choice && $mapbox_custom ) {
            $output['mapbox'] = [
                'selected'   => 'custom',
                'url'        => '',
                'custom_url' => $mapbox_custom,
            ];
        } elseif ( array_key_exists( $mapbox_choice, $mapbox_styles ) ) {
            $output['mapbox'] = [
                'selected'   => $mapbox_choice,
                'url'        => $mapbox_styles[ $mapbox_choice ],
                'custom_url' => $mapbox_custom,
            ];
        } else {
            $output['mapbox']['custom_url'] = $mapbox_custom;
        }

        $of_styles = wpsl_openfreemap_styles();
        $of        = isset( $input['openfreemap'] ) && is_array( $input['openfreemap'] ) ? $input['openfreemap'] : [];
        $of_custom = isset( $of['custom_url'] ) ? esc_url_raw( trim( (string) $of['custom_url'] ) ) : '';
        $of_custom = ( 0 === strpos( $of_custom, 'https://' ) ) ? $of_custom : '';
        $of_choice = isset( $of['selected'] ) ? sanitize_text_field( $of['selected'] ) : '';

        if ( 'custom' === $of_choice && '' === $of_custom ) {
            $of_choice = 'liberty';
        } elseif ( 'custom' !== $of_choice && ! array_key_exists( $of_choice, $of_styles ) ) {
            $of_choice = 'liberty';
        }

        $output['openfreemap'] = [
            'selected'   => $of_choice,
            'custom_url' => $of_custom,
        ];

        return $output;
    }

    /**
     * Sanitize the dimensions block.
     *
     * @since  3.0.0
     * @param  mixed $input    The decoded dimensions value.
     * @param  array $defaults The group's dimension defaults.
     * @return array
     */
    private function sanitize_dimensions( $input, $defaults ) {
        if ( ! is_array( $input ) ) {
            return $defaults;
        }

        $output = [];

        foreach ( $input as $key => $value ) {
            $key = sanitize_key( $key );

            if ( '' === $key ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $output[ $key ] = $this->sanitize_dimensions( $value, [] );

                continue;
            }

            if ( false !== strpos( $key, '_mode' ) ) {
                $output[ $key ] = in_array( $value, [ 'default', 'custom' ], true ) ? $value : 'custom';

                continue;
            }

            $output[ $key ] = absint( $value );
        }

        return array_replace_recursive( $defaults, $output );
    }

    /**
     * Read a stored 0/1 flag.
     *
     * @since  3.0.0
     * @param  array  $input The array holding it.
     * @param  string $key   The key.
     * @return int
     */
    private function flag( $input, $key ) {
        return ( isset( $input[ $key ] ) && filter_var( $input[ $key ], FILTER_VALIDATE_BOOLEAN ) ) ? 1 : 0;
    }

    /**
     * Read a stored hex color.
     *
     * @since  3.0.0
     * @param  array  $input The array holding it.
     * @param  string $key   The key.
     * @return string The color, or ''.
     */
    private function hex( $input, $key ) {
        if ( ! isset( $input[ $key ] ) ) {
            return '';
        }

        $hex = sanitize_hex_color( (string) $input[ $key ] );

        return $hex ? $hex : '';
    }

    /**
     * Read a stored value that has to be one of a fixed set.
     *
     * @since  3.0.0
     * @param  array    $input   The array holding it.
     * @param  string   $key     The key.
     * @param  string[] $allowed The accepted values.
     * @param  string   $default The fallback.
     * @return string
     */
    private function choice( $input, $key, $allowed, $default ) {
        $value = isset( $input[ $key ] ) ? sanitize_text_field( (string) $input[ $key ] ) : '';

        return in_array( $value, $allowed, true ) ? $value : $default;
    }

    /**
     * Park the summary for the request the redirect lands on.
     *
     * @since  3.0.0
     * @return void
     */
    private function store_summary() {
        set_transient( self::SUMMARY_TRANSIENT . get_current_user_id(), $this->summary, MINUTE_IN_SECONDS );
    }

    /**
     * Print the notices describing the last import.
     *
     * @since  3.0.0
     * @return void
     */
    public static function render_import_notices() {
        foreach ( self::take_summary_messages() as $notice ) {
            $body = '';

            if ( ! empty( $notice['items'] ) ) {
                $body .= '<ul class="wpsl-import-summary"><li>' . implode( '</li><li>', array_map( 'esc_html', $notice['items'] ) ) . '</li></ul>';
            }

            if ( ! empty( $notice['detail'] ) ) {
                $body .= '<p>' . $notice['detail'] . '</p>';
            }

            printf(
                '<div class="notice notice-%1$s settings-error is-dismissible"><p><strong>%2$s</strong></p>%3$s</div>',
                esc_attr( $notice['type'] ),
                wp_kses_post( $notice['message'] ),
                wp_kses_post( $body )
            );
        }
    }

    /**
     * The messages describing the last import, read once.
     *
     * @since  3.0.0
     * @return array[] Each entry is [ 'message' => string, 'type' => 'success'|'warning' ].
     */
    public static function take_summary_messages() {
        $key     = self::SUMMARY_TRANSIENT . get_current_user_id();
        $summary = get_transient( $key );

        delete_transient( $key );

        $messages = [ [
            'message' => esc_html__( 'Settings imported successfully', 'wp-store-locator' ),
            'items'   => [],
            'detail'  => '',
            'type'    => 'success',
        ] ];

        if ( ! is_array( $summary ) ) {
            return $messages;
        }

        $imported = [];

        if ( ! empty( $summary['groups'] ) ) {
            /* translators: %d: the number of settings sections. */
            $imported[] = sprintf( _n( '%d settings section', '%d settings sections', $summary['groups'], 'wp-store-locator' ), $summary['groups'] );
        }

        if ( ! empty( $summary['markers'] ) ) {
            /* translators: %d: the number of markers. */
            $imported[] = sprintf( _n( '%d custom marker', '%d custom markers', $summary['markers'], 'wp-store-locator' ), $summary['markers'] );
        }

        if ( ! empty( $summary['logos'] ) ) {
            /* translators: %d: the number of images. */
            $imported[] = sprintf( _n( '%d marker image', '%d marker images', $summary['logos'], 'wp-store-locator' ), $summary['logos'] );
        }

        if ( ! empty( $summary['shapes'] ) ) {
            /* translators: %d: the number of map shapes. */
            $imported[] = sprintf( _n( '%d map shape', '%d map shapes', $summary['shapes'], 'wp-store-locator' ), $summary['shapes'] );
        }

        if ( ! empty( $summary['template_sections'] ) ) {
            /* translators: %d: the number of template sections. */
            $imported[] = sprintf( _n( '%d template section', '%d template sections', $summary['template_sections'], 'wp-store-locator' ), $summary['template_sections'] );
        }

        if ( ! empty( $summary['marker_files_converted'] ) ) {
            foreach ( (array) $summary['marker_files_converted'] as $filename ) {
                /* translators: %s: a marker image filename. */
                $imported[] = sprintf( __( '%s, as a custom image marker', 'wp-store-locator' ), $filename );
            }
        }

        if ( $imported ) {
            $messages[0]['message'] = esc_html__( 'Settings imported successfully:', 'wp-store-locator' );
            $messages[0]['items']   = $imported;
        }

        /*
         * An image marker made from a marker file shows at the Studio's
         * default size, not the file's own, so point to where that changes.
         */
        if ( ! empty( $summary['marker_files_converted'] ) ) {
            $messages[0]['detail'] = sprintf(
                /* translators: %1$s: opening link tag to the Marker Studio, %2$s: closing link tag. */
                esc_html__( 'Custom markers are listed in the %1$sMarker Studio%2$s, where you can also adjust the size an image marker is shown at.', 'wp-store-locator' ),
                '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' ) ) . '">',
                '</a>'
            );
        }

        if ( ! empty( $summary['markers_skipped'] ) ) {
            $messages[] = [
                'message' => sprintf(
                    /* translators: %s: a comma separated list of marker names. */
                    esc_html__( 'These custom markers could not be imported and were skipped: %s.', 'wp-store-locator' ),
                    implode( ', ', array_map( 'esc_html', $summary['markers_skipped'] ) )
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['marker_files_missing'] ) ) {
            $messages[] = [
                'message' => sprintf(
                    /* translators: %s: a comma separated list of marker image filenames. */
                    esc_html__( 'These marker images aren\'t on this site, so the default marker is used instead: %s.', 'wp-store-locator' ),
                    implode( ', ', array_map( 'esc_html', (array) $summary['marker_files_missing'] ) )
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['logos_skipped'] ) ) {
            $messages[] = [
                'message' => sprintf(
                    /* translators: %d: the number of images. */
                    esc_html( _n( '%d marker image in the file could not be added to the media library.', '%d marker images in the file could not be added to the media library.', $summary['logos_skipped'], 'wp-store-locator' ) ),
                    $summary['logos_skipped']
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['template_sections_skipped'] ) ) {
            $messages[] = [
                'message' => sprintf(
                    /* translators: %d: the number of template sections. */
                    esc_html( _n( '%d template section in the file was skipped: importing template sections needs the same permission as the section editor, which your account does not have on this site.', '%d template sections in the file were skipped: importing template sections needs the same permission as the section editor, which your account does not have on this site.', $summary['template_sections_skipped'], 'wp-store-locator' ) ),
                    $summary['template_sections_skipped']
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['legacy_local_seo'] ) ) {
            $messages[] = [
                'message' => esc_html__( 'The permalinks settings in this file were exported by an older version under the previous Local SEO name, and were imported as Local Pages settings.', 'wp-store-locator' ),
                'type' => 'info',
            ];
        }

        if ( ! empty( $summary['valid_keys'] ) ) {
            $valid = (array) $summary['valid_keys'];

            $messages[] = [
                'message' => sprintf(
                    /* translators: %s: a comma separated list of provider names, e.g. "Mapbox". */
                    esc_html( _n( 'The imported %s API key was re-tested against this site and works here.', 'The imported %s API keys were re-tested against this site and work here.', count( $valid ), 'wp-store-locator' ) ),
                    esc_html( implode( ', ', $valid ) )
                ),
                'type' => 'info',
            ];
        }

        if ( ! empty( $summary['browser_key_pending'] ) ) {
            $messages[] = [
                'message' => esc_html__( 'The Google Maps browser key still has to be checked on this site.', 'wp-store-locator' ),
                'detail'  => sprintf(
                    /* translators: 1: opening link tag for the API section, 2: closing link tag, 3: the label of the button that runs the check. */
                    esc_html__( 'Only a browser can test it, so the import could not. Open the %1$sAPI settings%2$s and press "%3$s" - until then the maps stay hidden, even though the key itself may be fine.', 'wp-store-locator' ),
                    '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) ) . '">',
                    '</a>',
                    esc_html__( 'Show response', 'wp-store-locator' )
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['invalid_keys'] ) ) {
            $invalid = (array) $summary['invalid_keys'];

            $messages[] = [
                'message' => sprintf(
                    /* translators: %s: a comma separated list of provider names. */
                    esc_html( _n(
                        'The imported %s API key was rejected on this site.',
                        'The imported %s API keys were rejected on this site.',
                        count( $invalid ),
                        'wp-store-locator'
                    ) ),
                    esc_html( implode( ', ', $invalid ) )
                ),
                'detail' => sprintf(
                    /* translators: 1: opening link tag for the API section, 2: closing link tag */
                    esc_html( _n(
                        'A key with a URL restriction only works on the domain it lists, so it likely has to be reissued or widened for this domain. %1$sReview the API settings%2$s.',
                        'A key with a URL restriction only works on the domain it lists, so they likely have to be reissued or widened for this domain. %1$sReview the API settings%2$s.',
                        count( $invalid ),
                        'wp-store-locator'
                    ) ),
                    '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ) ) . '">',
                    '</a>'
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['references_released'] ) ) {
            $messages[] = [
                'message' => sprintf(
                    /* translators: %d: the number of markers. */
                    esc_html( _n( '%d custom marker this site used no longer exists, so the categories and stores set to it fall back to the marker above them.', '%d custom markers this site used no longer exist, so the categories and stores set to them fall back to the marker above them.', $summary['references_released'], 'wp-store-locator' ) ),
                    $summary['references_released']
                ),
                'type' => 'warning',
            ];
        }

        if ( ! empty( $summary['shapes_error'] ) ) {
            $messages[] = [
                'message' => sprintf(
                    /* translators: %s: the reason the shapes were rejected. */
                    esc_html__( 'The map shapes were not imported: %s', 'wp-store-locator' ),
                    esc_html( $summary['shapes_error'] )
                ),
                'type' => 'warning',
            ];
        }

        return $messages;
    }
}