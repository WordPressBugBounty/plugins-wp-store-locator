<?php
/**
 * Handle the cache manager.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\API\Service as ApiService;
use WPSL\Core\Map\Nominatim_Geocode_Cache;

/**
 * Cache manager.
 *
 * @since 3.0.0
 */
class Cache_Manager {
        
    /**
     * API service instance
     *
     * @var \WPSL\Core\API\Service
     */
    protected $api_service;

    /**
     * Nominatim geocode cache instance
     *
     * @var \WPSL\Core\Map\Nominatim_Geocode_Cache|null
     */
    protected $nominatim_cache;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param ApiService              $api_service API service instance
     * @param Nominatim_Geocode_Cache|null $nominatim_cache Nominatim geocode cache instance (optional)
     */
    public function __construct( ApiService $api_service, ?Nominatim_Geocode_Cache $nominatim_cache = null ) {
        $this->api_service = $api_service;
        $this->nominatim_cache = $nominatim_cache;

        add_action( 'wp_ajax_wpsl_flush_cache', [ $this, 'flush_cache' ] );
    }

    /**
     * Flush the transient or Nominatim geocode cache.
     *
     * @since 3.0.0
     * @return void
     */
    public function flush_cache() {
        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error();
        }

        if ( ! isset( $_REQUEST['id'] ) || ! isset( $_REQUEST['wpsl_nonce'] ) ) {
            wp_send_json_error();
        }

        // The nonce action is the cache id, matching what the flush methods expect.
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['wpsl_nonce'] ) ), sanitize_key( $_REQUEST['id'] ) ) ) {
            wp_send_json_error();
        }

        if ( isset( $_REQUEST['id'] ) ) {
            $cache_id = sanitize_key( $_REQUEST['id'] );

            switch ( $cache_id ) {
                case 'wpsl-flush-transient-cache':
                    $this->api_service->flush_transient_cache();
                    break;
                case 'wpsl-flush-nominatim-cache':
                    if ( $this->nominatim_cache ) {
                        $this->nominatim_cache->flush();
                    } else {
                        wp_send_json_error();
                    }
                    break;
                default:
                    wp_send_json_error();
            }
        }
    }
}