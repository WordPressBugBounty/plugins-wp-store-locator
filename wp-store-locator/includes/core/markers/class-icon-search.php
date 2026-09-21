<?php
/**
 * Search vocabulary shared by both icon libraries.
 *
 * @since 3.0.0
 */

namespace WPSL\Core\Markers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Icon_Search {

    /**
     * The loaded alias map, or null while it hasn't been read yet.
     *
     * @since 3.0.0
     * @var   array|null
     */
    private static $group_aliases = null;

    /**
     * Extra search words per category label.
     *
     * Loaded lazily and cached, the same way the icon geometry is: a page
     * that never opens the picker never reads the file.
     *
     * @since  3.0.0
     * @return array Group label => array of extra search words.
     */
    public static function get_group_aliases() {
        if ( null === self::$group_aliases ) {
            $file = __DIR__ . '/icon-group-aliases.php';

            $aliases = file_exists( $file ) ? require $file : [];

            self::$group_aliases = is_array( $aliases ) ? $aliases : [];
        }

        return self::$group_aliases;
    }
}