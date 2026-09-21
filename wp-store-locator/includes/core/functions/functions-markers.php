<?php
/**
 * Marker and preloader image helpers.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The image extensions that are valid for a marker.
 *
 * SVG is listed first so it wins whenever a color is available in both formats.
 *
 * @since  3.0.0
 * @return array The allowed marker extensions, lowercase, without the leading dot.
 */
function wpsl_marker_extensions() {
    return apply_filters( 'wpsl_marker_extensions', [ 'svg', 'png' ] );
}

/**
 * Check whether a marker filename refers to an SVG.
 *
 * @since  3.0.0
 * @param  string $filename The marker filename.
 * @return bool             True when the file is an SVG.
 */
function wpsl_marker_is_svg( $filename ) {
    return 'svg' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
}

/**
 * Check whether a marker filename is a retina ( 2x ) variant.
 *
 * @since  3.0.0
 * @param  string $filename The marker filename.
 * @return bool             True when the file is a retina variant.
 */
function wpsl_marker_is_retina( $filename ) {
    return (bool) preg_match( '/@?2x$/i', pathinfo( $filename, PATHINFO_FILENAME ) );
}

/**
 * The directories marker files load from, in priority order.
 *
 * A site can point wpsl_admin_marker_dir at its own folder (see
 * https://wpstorelocator.co/document/use-custom-markers/). That folder is
 * searched first, so one of its files wins a name clash, but it no longer
 * REPLACES the bundled folder: every marker value that predates the filter
 * (shipped defaults, a category marker, a per-location marker) still has to
 * resolve, and a site must be able to go back to a bundled color without
 * deleting files off the server.
 *
 * @since  3.0.0
 * @return string[] The URL each directory is served from, keyed by its absolute path.
 */
function wpsl_marker_sources() {
    $bundled_dir = trailingslashit( WPSL_PLUGIN_DIR . 'assets/img/frontend/markers/' );
    $bundled_url = trailingslashit( WPSL_URL . 'assets/img/frontend/markers/' );
    $custom_dir  = trailingslashit( apply_filters( 'wpsl_admin_marker_dir', $bundled_dir ) );

    /**
     * Without a directory of its own, WPSL_MARKER_URI describes the bundled
     * markers themselves ( a CDN, or a moved assets folder ).
     */
    if ( $custom_dir === $bundled_dir ) {
        return [ $bundled_dir => defined( 'WPSL_MARKER_URI' ) ? trailingslashit( WPSL_MARKER_URI ) : $bundled_url ];
    }

    return [
        $custom_dir  => defined( 'WPSL_MARKER_URI' ) ? trailingslashit( WPSL_MARKER_URI ) : $bundled_url,
        $bundled_dir => $bundled_url,
    ];
}

/**
 * Every marker file on disk, and which directory each one is in.
 *
 * One readdir() per directory per request, because the front end resolves a
 * marker for every category and every location that carries one.
 *
 * @since  3.0.0
 * @return string[] The absolute directory each marker filename lives in.
 */
function wpsl_marker_files() {
    static $cache = [];

    $sources = wpsl_marker_sources();

    $cache_key = implode( '|', array_keys( $sources ) );

    if ( isset( $cache[ $cache_key ] ) ) {
        return $cache[ $cache_key ];
    }

    $files = [];

    foreach ( array_keys( $sources ) as $dir ) {
        if ( ! is_dir( $dir ) || ! ( $dh = opendir( $dir ) ) ) {
            continue;
        }

        while ( false !== ( $file = readdir( $dh ) ) ) {
            $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

            // An earlier directory holding this filename keeps it.
            if ( isset( $files[ $file ] ) || ! in_array( $ext, wpsl_marker_extensions(), true ) ) {
                continue;
            }

            $files[ $file ] = $dir;
        }

        closedir( $dh );
    }

    $cache[ $cache_key ] = $files;

    return $files;
}

/**
 * The retina filename for a marker, the way this plugin writes it.
 *
 * An SVG is resolution independent and ships no variant, so its name is
 * returned unchanged -- appending @2x would name a file that isn't there.
 *
 * The name is a guess until wpsl_marker_files() has been asked whether it
 * exists, which is why nothing hands this straight to a browser.
 *
 * @since  3.0.0
 * @param  string $filename The standard marker filename.
 * @return string           The @2x filename, or $filename when there is no variant.
 */
function wpsl_marker_retina_filename( $filename ) {
    if ( ! $filename || wpsl_marker_is_svg( $filename ) ) {
        return $filename;
    }

    return pathinfo( $filename, PATHINFO_FILENAME ) . '@2x.' . pathinfo( $filename, PATHINFO_EXTENSION );
}

/**
 * The other way a marker's retina file can be spelled.
 *
 * Retina markers are named blue@2x.png here, but WP Store Locator 2.3 renamed
 * the ones it shipped to blue2x.png, so folders built against either release
 * are still out there. Both are read; only "@2x" is ever written.
 *
 * @since  3.0.0
 * @param  string $variant The retina filename to find the counterpart of.
 * @return string          The other spelling, or '' when there isn't one.
 */
function wpsl_marker_retina_alternate( $variant ) {
    $name = pathinfo( $variant, PATHINFO_FILENAME );

    if ( '@2x' !== substr( $name, -3 ) ) {
        return '';
    }

    return substr( $name, 0, -3 ) . '2x.' . pathinfo( $variant, PATHINFO_EXTENSION );
}

/**
 * Choose between a marker's retina variant and its base file.
 *
 * The directory that wins the base name also decides the variant: on a site
 * whose wpsl_admin_marker_dir folder overrides blue.png without a blue@2x.png
 * beside it, the bundled blue@2x.png is different artwork - not a sharper copy
 * of the override - so a variant from a later directory than its base file
 * loses to the base file. Loading the standard file at twice the size beats
 * loading nothing, and beats loading somebody else's marker.
 *
 * @since  3.0.0
 * @param  string $variant The retina filename.
 * @param  string $base    The standard filename.
 * @return string          The filename the front end should use.
 */
function wpsl_marker_retina_choice( $variant, $base ) {
    $files = wpsl_marker_files();

    // A folder written to the 2.3 convention spells its retina file blue2x.png.
    if ( $variant !== $base && ! isset( $files[ $variant ] ) ) {
        $alternate = wpsl_marker_retina_alternate( $variant );

        if ( $alternate && isset( $files[ $alternate ] ) ) {
            $variant = $alternate;
        }
    }

    if ( $variant === $base || ! isset( $files[ $variant ] ) ) {
        return $base;
    }

    if ( ! isset( $files[ $base ] ) ) {
        return $variant;
    }

    $rank = array_flip( array_keys( wpsl_marker_sources() ) );

    return ( $rank[ $files[ $variant ] ] > $rank[ $files[ $base ] ] ) ? $base : $variant;
}

/**
 * The value the map JS should load for a marker filename.
 *
 * The JS prefixes a bare filename with the marker directory URL - the first
 * source only. A file living anywhere else (a bundled color on a site whose
 * wpsl_admin_marker_dir filter points elsewhere) must be handed over as a
 * complete URL, which resolveMarkerSrc() passes through untouched, the same
 * as it does an inline data URI.
 *
 * @since  3.0.0
 * @param  string $filename The marker filename, retina variant included.
 * @param  string $fallback Optional. The standard filename $filename is the retina variant of.
 * @return string           A bare filename, or a complete URL.
 */
function wpsl_marker_js_value( $filename, $fallback = '' ) {
    if ( $fallback ) {
        $filename = wpsl_marker_retina_choice( $filename, $fallback );
    }

    $files   = wpsl_marker_files();
    $sources = wpsl_marker_sources();

    // Nothing to rewrite when the file is in the directory the JS already
    // prefixes with, which is the first source and only that one.
    if ( ! isset( $files[ $filename ] ) || $files[ $filename ] === array_key_first( $sources ) ) {
        return $filename;
    }

    return $sources[ $files[ $filename ] ] . $filename;
}

/**
 * Whether a marker filename loads from the plugin's own marker folder.
 *
 * False for a file in a wpsl_admin_marker_dir folder, including one that
 * overrides a bundled name. A value that isn't a file on disk ( a missing
 * file, a Studio "custom:" value ) counts as bundled: it falls back to a
 * shipped default.
 *
 * @since  3.0.0
 * @param  string $filename The saved marker filename.
 * @return bool
 */
function wpsl_marker_is_bundled( $filename ) {
    $files = wpsl_marker_files();

    if ( ! is_string( $filename ) || ! isset( $files[ $filename ] ) ) {
        return true;
    }

    return $files[ $filename ] === trailingslashit( WPSL_PLUGIN_DIR . 'assets/img/frontend/markers/' );
}

/**
 * Extract the custom marker id from a saved marker value.
 *
 * Markers created in the Marker Manager are not files on disk, so they are
 * stored as "custom:{id}" instead of a filename. Everything that turns a marker
 * setting into an image (the admin picker, the settings sanitizer and the
 * front-end marker props) uses this to tell the two formats apart, so the
 * prefix is only spelled out in one place.
 *
 * @since  3.0.0
 * @param  mixed $value The saved or submitted marker value.
 * @return string       The marker id, or '' when this isn't a custom marker value.
 */
function wpsl_custom_marker_id( $value ) {
    if ( ! is_string( $value ) || ! preg_match( '/^custom:([a-z0-9_\-]+)$/', $value, $match ) ) {
        return '';
    }

    return $match[1];
}

/**
 * Resolve a custom marker id to the inline SVG data URI that renders it.
 *
 * @since  3.0.0
 * @param  string $id The custom marker id.
 * @return string     The data URI, or '' when the id doesn't resolve.
 */
function wpsl_custom_marker_data_uri( $id ) {
    if ( '' === $id ) {
        return '';
    }

    $custom_markers = wpsl_get_service( 'custom_markers' );

    return $custom_markers ? $custom_markers->get_data_uri( $id ) : '';
}

/**
 * Resolve a marker name to an existing file, preferring SVG over PNG.
 *
 * Accepts a bare color name ("red") or a filename with extension ("red.png").
 * Returns the filename of the first extension in wpsl_marker_extensions() that
 * exists on disk, so SVG is used when present and PNG remains a fallback. When
 * no matching file is found, the name is returned with the requested (or
 * default) extension so existing behaviour is preserved.
 *
 * Directory order outranks extension order, so a theme's own red.png wins over
 * the bundled red.svg -- the same file the pickers offer for that color.
 *
 * @since  3.0.0
 * @param  string $marker_name The marker name, with or without an extension.
 * @param  string $dir         Optional. A single directory to look in. Defaults to every marker source.
 * @return string              The resolved marker filename.
 */
function wpsl_resolve_marker_filename( $marker_name, $dir = '' ) {
    $requested_ext = pathinfo( $marker_name, PATHINFO_EXTENSION );
    $base          = pathinfo( $marker_name, PATHINFO_FILENAME );
    $dirs          = ( '' === $dir ) ? array_keys( wpsl_marker_sources() ) : [ trailingslashit( $dir ) ];

    foreach ( $dirs as $marker_dir ) {
        foreach ( wpsl_marker_extensions() as $ext ) {
            if ( file_exists( $marker_dir . $base . '.' . $ext ) ) {
                return $base . '.' . $ext;
            }
        }
    }

    // Nothing on disk; keep the requested extension, or fall back to png.
    $fallback_ext = $requested_ext ? $requested_ext : 'png';

    return $base . '.' . $fallback_ext;
}

/**
 * Sanitize a stored marker value without applying a default.
 *
 * @since 3.0.0
 * @param string $value The raw marker value ( filename or custom:{id} ).
 * @return string The sanitized value, or an empty string.
 */
function wpsl_sanitize_marker_value( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';

    if ( ! $value ) {
        return '';
    }

    $custom_id = wpsl_custom_marker_id( $value );

    if ( $custom_id ) {
        return wpsl_custom_marker_data_uri( $custom_id ) ? 'custom:' . $custom_id : '';
    }

    $filename  = sanitize_file_name( wp_filter_nohtml_kses( $value ) );
    $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

    if ( ! in_array( $extension, wpsl_marker_extensions(), true ) ) {
        return '';
    }

    $resolved = wpsl_resolve_marker_filename( $filename );
    $files    = wpsl_marker_files();

    return isset( $files[ $resolved ] ) ? $resolved : '';
}

/**
 * The marker files a picker can offer, one per color.
 *
 * A color can ship in more than one format; the first extension in
 * wpsl_marker_extensions() wins, so SVG is used where it exists and PNG stays a
 * working fallback. Retina variants are not offered: they are the same marker.
 *
 * Every directory in wpsl_marker_sources() is listed, the first one first. A
 * color present in more than one is taken from the earliest, so a site that
 * filtered in its own red.png gets that file rather than the bundled red.svg.
 *
 * @since  3.0.0
 * @return string[] Filenames, keyed by color name.
 */
function wpsl_bundled_markers() {
    $ext_priority = array_flip( wpsl_marker_extensions() ); // Lower index = higher priority.
    $by_color     = [];

    foreach ( array_keys( wpsl_marker_sources() ) as $dir ) {
        if ( ! is_dir( $dir ) || ! ( $dh = opendir( $dir ) ) ) {
            continue;
        }

        $dir_colors = [];

        while ( false !== ( $file = readdir( $dh ) ) ) {
            if ( '.' === $file || '..' === $file || wpsl_marker_is_retina( $file ) ) {
                continue;
            }

            $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

            if ( ! isset( $ext_priority[ $ext ] ) ) {
                continue;
            }

            $color = pathinfo( $file, PATHINFO_FILENAME );

            // An earlier directory supplied this color, so its file stands.
            if ( isset( $by_color[ $color ] ) ) {
                continue;
            }

            // Keep the highest-priority format when a color exists in several.
            if ( ! isset( $dir_colors[ $color ] ) || $ext_priority[ $ext ] < $dir_colors[ $color ]['priority'] ) {
                $dir_colors[ $color ] = [
                    'file'     => $file,
                    'priority' => $ext_priority[ $ext ],
                ];
            }
        }

        closedir( $dh );

        $by_color += $dir_colors;
    }

    return wp_list_pluck( $by_color, 'file' );
}

/**
 * Every marker a user can be offered, in the two groups the pickers show them in.
 *
 * @since  3.0.0
 * @return array {
 *     @type array $bundled Display names, keyed by filename.
 *     @type array $custom  [ 'name', 'src', 'shape', 'dims' ], keyed by "custom:{id}".
 * }
 */
function wpsl_pickable_markers() {
    $bundled = [];

    // Underscores read as a word break too: the bundled colors use dashes, but
    // a filtered-in file is named however its author named it.
    foreach ( wpsl_bundled_markers() as $color => $filename ) {
        $bundled[ $filename ] = ucwords( str_replace( [ '-', '_' ], ' ', $color ) );
    }

    $custom         = [];
    $custom_markers = wpsl_get_service( 'custom_markers' );

    if ( $custom_markers ) {
        foreach ( $custom_markers->get_markers() as $marker ) {
            if ( empty( $marker['id'] ) ) {
                continue;
            }

            $data_uri = $custom_markers->get_data_uri( $marker['id'] );

            if ( ! $data_uri ) {
                continue;
            }

            $custom[ 'custom:' . $marker['id'] ] = [
                'name'  => isset( $marker['name'] ) ? $marker['name'] : '',
                'src'   => $data_uri,
                'shape' => isset( $marker['shape'] ) ? $marker['shape'] : 'classic_pin',
                'dims'  => isset( $marker['image_w'], $marker['image_h'] )
                    ? [ (int) $marker['image_w'], (int) $marker['image_h'] ]
                    : null,
            ];
        }
    }

    return [
        'bundled' => $bundled,
        'custom'  => $custom,
    ];
}

/**
 * The max-height a marker's preview image needs to read as the target size.
 *
 * @since  3.0.0
 * @param  string $value  A marker value ( filename or custom:{id} ).
 * @param  int    $target The silhouette height every shape should match, in px.
 * @return int    The max-height in px, or 0 when the CSS cap already fits.
 */
function wpsl_marker_preview_cap( $value, $target = 34 ) {
    $id = wpsl_custom_marker_id( $value );

    if ( ! $id ) {
        return 0;
    }

    $custom_markers = wpsl_get_service( 'custom_markers' );
    $markers        = $custom_markers ? $custom_markers->get_markers() : [];
    $shape          = isset( $markers[ $id ]['shape'] ) ? $markers[ $id ]['shape'] : 'classic_pin';

    $dims = isset( $markers[ $id ]['image_w'], $markers[ $id ]['image_h'] )
        ? [ (int) $markers[ $id ]['image_w'], (int) $markers[ $id ]['image_h'] ]
        : null;

    return \WPSL\Core\Markers\Custom_Markers::get_picker_height( $shape, $target, $dims );
}

/**
 * That cap as a style attribute, or '' when none is needed.
 *
 * @since  3.0.0
 * @param  string $value  A marker value.
 * @param  int    $target The silhouette height to match, in px.
 * @return string A ready-to-print style attribute, already escaped.
 */
function wpsl_marker_preview_style( $value, $target = 34 ) {
    $cap = wpsl_marker_preview_cap( $value, $target );

    return $cap ? ' style="max-height:' . esc_attr( $cap ) . 'px"' : '';
}

/**
 * The term meta key a category's marker lives in.
 *
 * @since  3.0.0
 * @param  string $type "store" or "active".
 * @return string The term meta key.
 */
function wpsl_category_marker_key( $type ) {
    return 'active' === $type
        ? 'wpsl_category_marker_active'
        : 'wpsl_category_marker';
}

/**
 * The marker value a category has been given.
 *
 * A filename or "custom:{id}", the same vocabulary as the store_marker setting
 * and the per-location marker.
 *
 * @since  3.0.0
 * @param  int    $term_id Term id.
 * @param  string $type    "store" or "active".
 * @return string The marker value, or '' when the category has no marker.
 */
function wpsl_category_marker( $term_id, $type = 'store' ) {
    return (string) get_term_meta( $term_id, wpsl_category_marker_key( $type ), true );
}

/**
 * The image a category's marker draws as.
 *
 * @since  3.0.0
 * @param  int    $term_id Term id.
 * @param  string $type    "store" or "active".
 * @return string A URL or data URI, or '' when the category has no marker.
 */
function wpsl_category_marker_src( $term_id, $type = 'store' ) {
    $value = get_term_meta( $term_id, wpsl_category_marker_key( $type ), true );

    return $value ? wpsl_marker_src( $value ) : '';
}

/**
 * Resolve a stored marker value to a usable image src.
 *
 * @since 3.0.0
 * @param string $value The stored marker value ( filename or custom:{id} ).
 * @return string A marker URL or SVG data URI, or an empty string if it no longer resolves.
 */
function wpsl_marker_src( $value ) {
    if ( ! $value ) {
        return '';
    }

    $custom_id = wpsl_custom_marker_id( $value );

    if ( $custom_id ) {
        return wpsl_custom_marker_data_uri( $custom_id );
    }

    $files   = wpsl_marker_files();
    $sources = wpsl_marker_sources();

    if ( isset( $files[ $value ] ) ) {
        return $sources[ $files[ $value ] ] . $value;
    }

    // On disk nowhere. The bundled directory is the one that always exists, so
    // point there rather than at a folder this file was never in.
    return end( $sources ) . $value;
}

/**
 * The image src that draws a marker on a screen asking for twice the pixels.
 *
 * @since  3.0.0
 * @param  string $value A marker value ( filename or custom:{id} ).
 * @return string        A marker URL or SVG data URI, or '' when the value doesn't resolve.
 */
function wpsl_marker_retina_src( $value ) {
    if ( ! $value || wpsl_custom_marker_id( $value ) ) {
        // A Marker Studio marker is an inline SVG: one src at every density.
        return wpsl_marker_src( $value );
    }

    $variant = wpsl_marker_retina_filename( $value );
    $choice  = wpsl_marker_retina_choice( $variant, $value );

    return wpsl_marker_src( $choice );
}

/**
 * Get the configured preloader color mode.
 *
 * @since  3.0.0
 * @return string black|white|custom
 */
function wpsl_get_preloader_color() {
    $raw = get_option( 'wpsl_appearance', [] );

    if ( is_array( $raw ) && isset( $raw['white_preloader'] ) && ! isset( $raw['preloader_color'] ) ) {
        return ! empty( $raw['white_preloader'] ) ? 'white' : 'black';
    }

    $appearance = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );

    return ! empty( $appearance['preloader_color'] ) ? $appearance['preloader_color'] : 'black';
}

/**
 * Get the URL for the preloader image.
 *
 * @since  3.0.0
 * @return string The url for the preloader image.
 */
function wpsl_get_preloader_url() {
    $color = wpsl_get_preloader_color();

    if ( 'custom' === $color ) {
        $appearance = wpsl_get_service( 'wpsl_settings' )->get_group( 'appearance' );
        $hex        = isset( $appearance['preloader_custom_color'] ) ? sanitize_hex_color( $appearance['preloader_custom_color'] ) : '';

        if ( $hex ) {
            $svg = file_get_contents( WPSL_PLUGIN_DIR . 'assets/img/ajax-loader.svg' );

            if ( false !== $svg ) {
                $svg           = str_replace( '#000000', $hex, $svg );
                $preloader_url = 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( $svg );

                return apply_filters( 'wpsl_preloader_url', $preloader_url );
            }
        }
    }

    $file_name     = ( 'white' === $color ) ? '-white' : '';
    $preloader_url = WPSL_URL . 'assets/img/ajax-loader' . $file_name . '.svg';

    return apply_filters( 'wpsl_preloader_url', $preloader_url ) ;
}

/**
 * Get the URL for the geolocation overlay preloader image.
 *
 * @since  3.0.0
 * @return string The url for the geolocation preloader image.
 */
function wpsl_get_geolocation_preloader_url() {
    $use_white = apply_filters( 'wpsl_geolocation_white_preloader', false );
    $file_name = $use_white ? '-white' : '';

    $preloader_url = WPSL_URL . 'assets/img/ajax-loader' . $file_name . '.svg';

    return apply_filters( 'wpsl_geolocation_preloader_url', $preloader_url );
}