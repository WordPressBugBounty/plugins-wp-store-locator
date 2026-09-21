<?php
/**
 * Map shapes storage.
 *
 * @since 3.0.0
 */

namespace WPSL\Core\Shapes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Repository {

    const OPTION_NAME = 'wpsl_map_shapes';

    /**
     * Get the stored FeatureCollection. Always returns a valid, possibly
     * empty, collection — a corrupt option degrades to empty instead of
     * breaking the frontend.
     *
     * @since  3.0.0
     * @return array
     */
    public function get_collection() {
        $collection = get_option( self::OPTION_NAME, null );

        if ( ! is_array( $collection ) || ( $collection['type'] ?? '' ) !== 'FeatureCollection' || ! is_array( $collection['features'] ?? null ) ) {
            return [ 'type' => 'FeatureCollection', 'features' => [] ];
        }

        return $collection;
    }

    /**
     * Save a pre-sanitized FeatureCollection.
     *
     * @since  3.0.0
     * @param  array $collection
     * @return void
     */
    public function save_collection( $collection ) {
        update_option( self::OPTION_NAME, $collection, false );
    }

    /**
     * The stored FeatureCollection with the inactive shapes filtered out.
     *
     * @since  3.0.0
     * @return array
     */
    public function get_active_collection() {
        $collection = $this->get_collection();
        $active     = [];

        foreach ( $collection['features'] as $feature ) {
            $props = is_array( $feature['properties'] ?? null ) ? $feature['properties'] : [];

            if ( ! isset( $props['active'] ) || ! empty( $props['active'] ) ) {
                $active[] = $feature;
            }
        }

        // Rebuilt rather than unset in place, so a filtered set still
        // JSON-encodes as an array.
        $collection['features'] = $active;

        return $collection;
    }

    /**
     * Whether any shapes are stored.
     *
     * @since  3.0.0
     * @return bool
     */
    public function has_shapes() {
        $collection = $this->get_collection();

        return ! empty( $collection['features'] );
    }
}