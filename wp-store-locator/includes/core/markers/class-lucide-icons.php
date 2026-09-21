<?php
/**
 * Access to the Lucide icon geometry used by custom markers.
 *
 * @since 3.0.0
 */

namespace WPSL\Core\Markers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lucide_Icons {

    /**
     * The loaded icon map, or null while it hasn't been read yet.
     *
     * @since 3.0.0
     * @var   array|null
     */
    private static $icons = null;

    /**
     * The loaded group map, or null while it hasn't been read yet.
     *
     * @since 3.0.0
     * @var   array|null
     */
    private static $groups = null;

    /**
     * The loaded tag map, or null while it hasn't been read yet.
     *
     * @since 3.0.0
     * @var   array|null
     */
    private static $tags = null;

    /**
     * All icons, keyed by their kebab-case name.
     *
     * @since  3.0.0
     * @return array name => inner SVG markup.
     */
    public static function get_all() {
        if ( null === self::$icons ) {
            $file = __DIR__ . '/lucide-icons.php';

            $icons = file_exists( $file ) ? require $file : [];

            self::$icons = is_array( $icons ) ? $icons : [];
        }

        return self::$icons;
    }

    /**
     * The inner SVG markup for one icon.
     *
     * @since  3.0.0
     * @param  string $name The kebab-case icon name, e.g. "coffee".
     * @return string The icon geometry, or '' when the name is unknown.
     */
    public static function get( $name ) {
        if ( ! is_string( $name ) || '' === $name ) {
            return '';
        }

        $icons = self::get_all();

        return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
    }

    /**
     * Whether an icon name resolves to geometry.
     *
     * @since  3.0.0
     * @param  string $name The kebab-case icon name.
     * @return bool
     */
    public static function has( $name ) {
        return '' !== self::get( $name );
    }

    /**
     * Every available icon name, for the icon field's datalist.
     *
     * @since  3.0.0
     * @return string[] Kebab-case icon names, alphabetically.
     */
    public static function get_names() {
        return array_keys( self::get_all() );
    }

    /**
     * The group → icon-names mapping, for the Marker Studio category dropdown.
     *
     * @since  3.0.0
     * @return array Group label => array of kebab-case icon names.
     */
    public static function get_groups() {
        if ( null === self::$groups ) {
            $file = __DIR__ . '/lucide-icon-groups.php';

            $groups = file_exists( $file ) ? require $file : [];

            self::$groups = is_array( $groups ) ? $groups : [];
        }

        return self::$groups;
    }

    /**
     * The icon → tags mapping, for the Marker Studio icon picker search.
     *
     * @since  3.0.0
     * @return array Icon slug => array of tag strings.
     */
    public static function get_tags() {
        if ( null === self::$tags ) {
            $file = __DIR__ . '/lucide-icon-tags.php';

            $tags = file_exists( $file ) ? require $file : [];

            self::$tags = is_array( $tags ) ? $tags : [];
        }

        return self::$tags;
    }
}