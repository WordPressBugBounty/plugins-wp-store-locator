<?php
/**
 * Complianz integration rules for WP Store Locator.
 *
 * Complianz requires this file in place of its own bundled
 * integrations/plugins/wp-store-locator.php, which still describes the
 * 2.x markup ( wpsl-gmap / wpsl-js-js handles, wpsl-gmap-canvas class )
 * and matches nothing in 3.x.
 *
 * The canvas placeholder and detected service are registered by
 * \WPSL\Core\Integrations\Complianz, loaded early on 'plugins_loaded' to
 * swap this path in.
 *
 * @see \WPSL\Core\Integrations\Complianz::integration_path()
 * @since 3.0.0
 */

defined( 'ABSPATH' ) || exit;