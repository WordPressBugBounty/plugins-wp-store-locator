/**
 * The search radius circle on a Mapbox map.
 *
 * When and how big is decided in modules/wpsl-radius-circle.js.
 *
 * @since 3.1.1
 */

import { circleToPolygon } from './wpsl-shapes.js';
import { layers } from './wpsl-layers.js';

const SOURCE_ID = 'wpsl-radius-circle';
const FILL_ID   = SOURCE_ID + '-fill';
const LINE_ID   = SOURCE_ID + '-line';

// Per map: the circle feature ( null once removed ), its style, whether it's hidden.
const state = new WeakMap();

/**
 * Add the source and layers, or update them, to match the state of the map.
 *
 * Runs again on every style.load: a runtime setStyle() throws away every
 * source and layer, as for the map shapes.
 *
 * @since  3.1.1
 * @param  {object} map mapboxgl.Map instance
 * @return {void}
 */
function apply( map ) {
    const current = state.get( map );

    if ( ! current ) {
        return;
    }

    const data = {
        type: 'FeatureCollection',
        features: current.feature ? [ current.feature ] : []
    };

    try {
        const source = map.getSource( SOURCE_ID );

        if ( source ) {
            source.setData( data );
        } else {
            if ( ! current.feature ) {
                return;
            }

            map.addSource( SOURCE_ID, { type: 'geojson', data: data } );

            // Below the markers, also when they were added first.
            const beforeId = firstMarkerLayer( map );

            map.addLayer( {
                id:     FILL_ID,
                type:   'fill',
                source: SOURCE_ID,
                paint:  {}
            }, beforeId );

            map.addLayer( {
                id:     LINE_ID,
                type:   'line',
                source: SOURCE_ID,
                paint:  {}
            }, beforeId );
        }

        if ( current.style ) {
            map.setPaintProperty( FILL_ID, 'fill-color', current.style.color );
            map.setPaintProperty( FILL_ID, 'fill-opacity', current.style.fillOpacity );
            map.setPaintProperty( LINE_ID, 'line-color', current.style.color );
            map.setPaintProperty( LINE_ID, 'line-width', current.style.strokeWeight );
            map.setPaintProperty( LINE_ID, 'line-opacity', current.style.strokeOpacity );
        }

        [ FILL_ID, LINE_ID ].forEach( function( layerId ) {
            map.setLayoutProperty( layerId, 'visibility', current.hidden ? 'none' : 'visible' );
        } );
    } catch ( e ) {

        // The style is still loading, the next 'idle' tries again.
        if ( ! current.retry ) {
            current.retry = true;

            map.once( 'idle', function() {
                current.retry = false;
                apply( map );
            } );
        }
    }
}

/**
 * The first marker layer in the style, to add the circle layers below it.
 *
 * @since  3.1.1
 * @param  {object} map mapboxgl.Map instance
 * @return {string|undefined} The layer id, or undefined to add on top.
 */
function firstMarkerLayer( map ) {
    const style     = map.getStyle();
    const markerIds = layers.active.concat( [ 'start', 'clusters', 'cluster-count' ] );

    if ( ! style || ! Array.isArray( style.layers ) ) {
        return undefined;
    }

    const first = style.layers.find( function( layer ) {
        return markerIds.indexOf( layer.id ) !== -1;
    } );

    return first ? first.id : undefined;
}

/**
 * Set the state for a map, and listen for style changes once.
 *
 * @since  3.1.1
 * @param  {object} map     mapboxgl.Map instance
 * @param  {object} changes The state values to change.
 * @return {void}
 */
function update( map, changes ) {
    let current = state.get( map );

    if ( ! current ) {
        current = { feature: null, style: null, hidden: false, retry: false };

        state.set( map, current );

        map.on( 'style.load', function() {
            apply( map );
        } );
    }

    Object.assign( current, changes );

    apply( map );
}

export const radiusCircle = {
    /**
     * Draw the circle.
     *
     * @since  3.1.1
     * @param  {object} map    mapboxgl.Map instance
     * @param  {object} center lat / lng
     * @param  {number} meters The radius
     * @param  {object} style  color, strokeWeight, strokeOpacity, fillOpacity
     * @return {void}
     */
    draw: function( map, center, meters, style ) {
        update( map, {
            feature: {
                type: 'Feature',
                properties: {},
                geometry: {
                    type: 'Polygon',
                    coordinates: circleToPolygon( [ center.lng, center.lat ], meters )
                }
            },
            style:  style,
            hidden: false
        } );
    },

    /**
     * The bounds of the circle, to fit the map to.
     *
     * Taken from the polygon that draws it, so it also works before the
     * style has loaded and while the circle is hidden for the directions.
     *
     * @since  3.1.1
     * @param  {object} map mapboxgl.Map instance
     * @return {Array|null} [ [ west, south ], [ east, north ] ], or null without a circle.
     */
    getBounds: function( map ) {
        const current = state.get( map );

        if ( ! current || ! current.feature ) {
            return null;
        }

        const ring = current.feature.geometry.coordinates[0];
        const lngs = ring.map( ( position ) => position[0] );
        const lats = ring.map( ( position ) => position[1] );

        return [ [ Math.min( ...lngs ), Math.min( ...lats ) ], [ Math.max( ...lngs ), Math.max( ...lats ) ] ];
    },

    /**
     * Remove the circle. The source and layers stay, empty.
     *
     * @since  3.1.1
     * @param  {object} map mapboxgl.Map instance
     * @return {void}
     */
    remove: function( map ) {
        if ( state.has( map ) ) {
            update( map, { feature: null } );
        }
    },

    /**
     * Hide the circle while the directions are shown.
     *
     * @since  3.1.1
     * @param  {object} map mapboxgl.Map instance
     * @return {void}
     */
    hide: function( map ) {
        if ( state.has( map ) ) {
            update( map, { hidden: true } );
        }
    },

    /**
     * Show the circle hidden by hide() again.
     *
     * @since  3.1.1
     * @param  {object} map mapboxgl.Map instance
     * @return {void}
     */
    show: function( map ) {
        if ( state.has( map ) ) {
            update( map, { hidden: false } );
        }
    }
};
