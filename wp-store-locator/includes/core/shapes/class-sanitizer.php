<?php
/**
 * Map shapes GeoJSON sanitizer.
 *
 * Validates the FeatureCollection posted by the Map Shapes editor before it
 * is written to the wpsl_map_shapes option. Geometry is validated hard
 * ( WP_Error on anything structurally wrong ), style values are validated
 * soft ( invalid colors / numbers fall back to safe defaults ).
 *
 * @since 3.0.0
 */

namespace WPSL\Core\Shapes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Sanitizer {

    /**
     * Names that arrived in the collection being sanitized, as a set.
     *
     * @since 3.0.0
     * @var   array
     */
    private $supplied_names = [];

    /**
     * The names actually handed out so far this run, as a set.
     *
     * @since 3.0.0
     * @var   array
     */
    private $assigned_names = [];

    /**
     * How far next_name() has counted per shape type, this run.
     *
     * @since 3.0.0
     * @var   array
     */
    private $type_counts = [];

    /**
     * shape_type => required GeoJSON geometry type.
     */
    const GEOMETRY_MAP = [
        'polygon'   => 'Polygon',
        'rectangle' => 'Polygon',
        'circle'    => 'Point',
        'polyline'  => 'LineString',
    ];

    const DEFAULT_COLOR = '#cc3333';

    /**
     * Property keys always refused. The collection is output to the frontend
     * as a JS object literal; a stored __proto__ key would enable prototype
     * pollution if any frontend code merges the properties into another object.
     *
     * @since 3.0.0
     */
    const FORBIDDEN_PROPERTY_KEYS = [ '__proto__', 'prototype', 'constructor' ];

    /**
     * Upper bounds on what one collection may hold.
     *
     * @since 3.0.0
     */
    const MAX_FEATURES       = 200;
    const MAX_RING_POSITIONS = 2000;

    /**
     * Sanitize a decoded FeatureCollection array.
     *
     * @since  3.0.0
     * @param  mixed $raw Decoded JSON payload.
     * @return array|\WP_Error
     */
    public function sanitize_collection( $raw ) {
        if ( ! is_array( $raw ) || ( $raw['type'] ?? '' ) !== 'FeatureCollection' || ! is_array( $raw['features'] ?? null ) ) {
            return new \WP_Error( 'wpsl_shapes_not_collection', __( 'Invalid shape data: expected a FeatureCollection.', 'wp-store-locator' ) );
        }

        if ( count( $raw['features'] ) > self::MAX_FEATURES ) {
            return new \WP_Error(
                'wpsl_shapes_too_many',
                sprintf(
                    /* translators: %d: the maximum number of shapes. */
                    __( 'Too many shapes: a collection can hold at most %d.', 'wp-store-locator' ),
                    self::MAX_FEATURES
                )
            );
        }

        $features = [];

        /*
         * Names must be unique across the entire collection ("polygon_1" is
         * only free if no other feature claims it), so the state is gathered
         * here and reset per run. Seeded with supplied names so a generated
         * name can't collide with one a later feature in the same payload carries.
         */
        $this->supplied_names = [];
        $this->assigned_names = [];
        $this->type_counts    = [];

        foreach ( $raw['features'] as $feature ) {
            $name = is_array( $feature ) ? sanitize_text_field( $feature['properties']['name'] ?? '' ) : '';

            if ( '' !== $name ) {
                $this->supplied_names[ $name ] = true;
            }
        }

        foreach ( $raw['features'] as $feature ) {
            $sanitized = $this->sanitize_feature( $feature );

            if ( is_wp_error( $sanitized ) ) {
                return $sanitized;
            }

            $features[] = $sanitized;
        }

        return [
            'type'     => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Sanitize a single feature.
     *
     * @since  3.0.0
     * @param  mixed $feature
     * @return array|\WP_Error
     */
    private function sanitize_feature( $feature ) {
        if ( ! is_array( $feature ) || ( $feature['type'] ?? '' ) !== 'Feature' || ! is_array( $feature['geometry'] ?? null ) || ! is_array( $feature['properties'] ?? null ) ) {
            return new \WP_Error( 'wpsl_shapes_bad_feature', __( 'Invalid shape data: malformed feature.', 'wp-store-locator' ) );
        }

        $props      = $feature['properties'];
        $shape_type = sanitize_key( $props['shape_type'] ?? '' );

        if ( ! isset( self::GEOMETRY_MAP[ $shape_type ] ) ) {
            return new \WP_Error( 'wpsl_shapes_bad_feature', __( 'Invalid shape data: unknown shape type.', 'wp-store-locator' ) );
        }

        $geometry = $feature['geometry'];

        if ( ( $geometry['type'] ?? '' ) !== self::GEOMETRY_MAP[ $shape_type ] ) {
            return new \WP_Error( 'wpsl_shapes_bad_geometry', __( 'Invalid shape data: geometry does not match the shape type.', 'wp-store-locator' ) );
        }

        $coordinates = $this->sanitize_coordinates( $geometry['coordinates'] ?? null, $geometry['type'] );

        if ( is_wp_error( $coordinates ) ) {
            return $coordinates;
        }

        $sanitized_props = $this->sanitize_properties( $props, $shape_type );

        if ( is_wp_error( $sanitized_props ) ) {
            return $sanitized_props;
        }

        return [
            'type'       => 'Feature',
            'geometry'   => [
                'type'        => $geometry['type'],
                'coordinates' => $coordinates,
            ],
            'properties' => $sanitized_props,
        ];
    }

    /**
     * Validate coordinates for a geometry type.
     *
     * Point => [lng, lat]; LineString => [[lng, lat], ...] ( >= 2 );
     * Polygon => [ ring, ... ] where ring = [[lng, lat], ...] ( >= 4, closed
     * is the drawing library's job — not enforced here ).
     *
     * @since  3.0.0
     * @param  mixed  $coordinates
     * @param  string $geometry_type
     * @return array|\WP_Error
     */
    private function sanitize_coordinates( $coordinates, $geometry_type ) {
        $error = new \WP_Error( 'wpsl_shapes_bad_coordinates', __( 'Invalid shape data: bad coordinates.', 'wp-store-locator' ) );

        if ( 'Point' === $geometry_type ) {
            $position = $this->sanitize_position( $coordinates );

            return ( null === $position ) ? $error : $position;
        }

        if ( 'LineString' === $geometry_type ) {
            if ( ! is_array( $coordinates ) || count( $coordinates ) < 2 || count( $coordinates ) > self::MAX_RING_POSITIONS ) {
                return $error;
            }

            $line = [];

            foreach ( $coordinates as $position ) {
                $position = $this->sanitize_position( $position );

                if ( null === $position ) {
                    return $error;
                }

                $line[] = $position;
            }

            return $line;
        }

        // Polygon: array of linear rings.
        if ( ! is_array( $coordinates ) || empty( $coordinates ) ) {
            return $error;
        }

        $rings = [];

        foreach ( $coordinates as $ring ) {
            if ( ! is_array( $ring ) || count( $ring ) < 4 || count( $ring ) > self::MAX_RING_POSITIONS ) {
                return $error;
            }

            $clean_ring = [];

            foreach ( $ring as $position ) {
                $position = $this->sanitize_position( $position );

                if ( null === $position ) {
                    return $error;
                }

                $clean_ring[] = $position;
            }

            $rings[] = $clean_ring;
        }

        return $rings;
    }

    /**
     * Validate a single [lng, lat] position.
     *
     * @since  3.0.0
     * @param  mixed $position
     * @return array|null [lng, lat] floats, or null when invalid.
     */
    private function sanitize_position( $position ) {
        if ( ! is_array( $position ) || count( $position ) < 2 || ! is_numeric( $position[0] ) || ! is_numeric( $position[1] ) ) {
            return null;
        }

        $lng = (float) $position[0];
        $lat = (float) $position[1];

        if ( $lng < -180 || $lng > 180 || $lat < -90 || $lat > 90 ) {
            return null;
        }

        return [ $lng, $lat ];
    }

    /**
     * The next free "<type>_<n>" name.
     *
     * Counts up rather than counting shapes of that type: deleting polygon_1
     * must not produce a second polygon_2, and a user-renamed "polygon_4"
     * must not be collided with.
     *
     * @since  3.0.0
     * @param  string $shape_type
     * @return string
     */
    private function next_name( $shape_type ) {
        do {
            $this->type_counts[ $shape_type ] = ( $this->type_counts[ $shape_type ] ?? 0 ) + 1;

            $candidate = $shape_type . '_' . $this->type_counts[ $shape_type ];

            // Both sets: one for names a later feature is going to want, one
            // for names already handed out.
        } while ( isset( $this->supplied_names[ $candidate ] ) || isset( $this->assigned_names[ $candidate ] ) );

        return $candidate;
    }

    /**
     * Sanitize feature properties. Style keys fall back to defaults; radius
     * is hard-validated for circles; unknown keys are preserved.
     *
     * @since  3.0.0
     * @param  array  $props
     * @param  string $shape_type
     * @return array|\WP_Error
     */
    private function sanitize_properties( $props, $shape_type ) {
        $known = [
            'id'           => sanitize_key( $props['id'] ?? '' ),
            'name'         => sanitize_text_field( $props['name'] ?? '' ),

            /*
             * What the visitor sees on click. Markup is stripped here and the
             * frontend re-escapes before converting the BBCode subset it
             * supports (wpsl-bbcode.js), so only that module's own formatting
             * can reach a page. sanitize_textarea_field() because a message
             * is allowed to have lines.
             */
            'message'      => sanitize_textarea_field( $props['message'] ?? '' ),
            'shape_type'   => $shape_type,

            /*
             * Whether the shape reaches visitors. Absent means active, so
             * collections stored before the property existed keep rendering.
             */
            'active'       => ! isset( $props['active'] ) || ! empty( $props['active'] ),
            'fill'         => ! empty( $props['fill'] ),
            'fill_color'   => sanitize_hex_color( $props['fill_color'] ?? '' ) ?: self::DEFAULT_COLOR,
            'fill_opacity' => isset( $props['fill_opacity'] ) && is_numeric( $props['fill_opacity'] ) ? max( 0.0, min( 1.0, (float) $props['fill_opacity'] ) ) : 0.4,
            'stroke_color' => sanitize_hex_color( $props['stroke_color'] ?? '' ) ?: self::DEFAULT_COLOR,
            'stroke_width' => isset( $props['stroke_width'] ) && is_numeric( $props['stroke_width'] ) ? max( 0, (int) $props['stroke_width'] ) : 2,
        ];

        if ( '' === $known['id'] ) {
            $known['id'] = 'shape_' . uniqid();
        }

        if ( isset( $this->assigned_names[ $known['name'] ] ) ) {
            $known['name'] = '';
        }

        if ( '' === $known['name'] ) {
            $known['name'] = $this->next_name( $shape_type );
        }

        $this->assigned_names[ $known['name'] ] = true;

        if ( 'circle' === $shape_type ) {
            if ( ! isset( $props['radius'] ) || ! is_numeric( $props['radius'] ) || (float) $props['radius'] <= 0 ) {
                return new \WP_Error( 'wpsl_shapes_bad_radius', __( 'Invalid shape data: a circle needs a positive radius.', 'wp-store-locator' ) );
            }

            $known['radius'] = (float) $props['radius'];
        }

        foreach ( $props as $key => $value ) {
            $key = sanitize_key( $key );

            if ( isset( $known[ $key ] ) || 'radius' === $key || in_array( $key, self::FORBIDDEN_PROPERTY_KEYS, true ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $clean_value = [];

                foreach ( array_values( $value ) as $v ) {
                    if ( ! is_scalar( $v ) ) {
                        return new \WP_Error( 'wpsl_shapes_bad_feature', __( 'Invalid shape data: malformed feature properties.', 'wp-store-locator' ) );
                    }

                    $clean_value[] = is_numeric( $v ) ? $v + 0 : sanitize_text_field( (string) $v );
                }

                $known[ $key ] = $clean_value;
            } elseif ( is_scalar( $value ) ) {
                $known[ $key ] = is_numeric( $value ) ? $value + 0 : sanitize_text_field( (string) $value );
            }
        }

        return $known;
    }
}