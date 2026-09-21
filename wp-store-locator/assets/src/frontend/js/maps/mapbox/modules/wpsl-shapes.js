/**
 * Render the admin-defined map shapes ( the wpsl_map_shapes option ) on a
 * Mapbox GL map.
 *
 * No drawing library here: visitors get one GeoJSON source and two data-driven
 * layers. The style guards below are deliberately the same ones the editor
 * adapter uses ( wpsl-shapes-draw-mapbox.js: safeColor / strokeWidth /
 * clampOpacity ), so a shape looks the same in the editor and on the map.
 *
 * @since 3.0.0
 */

import { bbcodeToHtml } from '../../../modules/wpsl-bbcode.js';

const DEFAULT_COLOR = '#cc3333';

/**
 * What render() set up on each map: the source id its two layers hang off,
 * and whether hide() currently has them hidden. The layers themselves are
 * rebuilt on every style.load, so the flag lives here, not on the map style.
 *
 * @since 3.0.0
 * @type  {WeakMap} map => { sourceId: string, hidden: boolean }
 */
const rendered = new WeakMap();

/**
 * The layer ids a shapes source draws through.
 *
 * @since  3.0.0
 * @param  {string} sourceId
 * @return {Array}
 */
function layerIds( sourceId ) {
    return [ sourceId + '-fill', sourceId + '-line' ];
}

/**
 * Apply the map's hidden flag to the shapes layers that exist right now.
 *
 * @since  3.0.0
 * @param  {object} map The GL JS map.
 * @return {void}
 */
function applyVisibility( map ) {
    const state = rendered.get( map );

    if ( ! state ) {
        return;
    }

    layerIds( state.sourceId ).forEach( function( layerId ) {
        if ( map.getLayer( layerId ) ) {
            map.setLayoutProperty( layerId, 'visibility', state.hidden ? 'none' : 'visible' );
        }
    } );
}

/**
 * A hex color, or the default.
 *
 * @since  3.0.0
 * @param  {*} value
 * @return {string}
 */
function safeColor( value ) {
    return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( String( value ) ) ? String( value ) : DEFAULT_COLOR;
}

/**
 * A non-negative integer stroke width.
 *
 * 0 is a valid width -- it is how a shape renders without a border. Treating it
 * as "empty" turns it back into the default 2px outline.
 *
 * @since  3.0.0
 * @param  {*} value
 * @return {number}
 */
function strokeWidth( value ) {
    const width = parseInt( value, 10 );

    return ( isNaN( width ) || width < 0 ) ? 2 : width;
}

/**
 * A 0-1 opacity.
 *
 * @since  3.0.0
 * @param  {*} value
 * @return {number}
 */
function clampOpacity( value ) {
    const opacity = parseFloat( value );

    return isNaN( opacity ) ? 0.4 : Math.min( 1, Math.max( 0, opacity ) );
}

/**
 * A longitude wrapped into [ -180, 180 ].
 *
 * @since  3.0.0
 * @param  {number} lng
 * @return {number}
 */
function wrapLongitude( lng ) {
    return ( ( lng + 540 ) % 360 ) - 180;
}

/**
 * A circle as the polygon ring GL JS can draw.
 *
 * GL JS has no circle geometry in metres, so a stored centre + radius becomes a
 * polygon. Copy of the editor adapter's circleToPolygon
 * ( wpsl-shapes-draw-mapbox.js ), and it has to stay identical to it, or one
 * saved circle is two different sizes in the editor and on the store locator.
 *
 * Longitudes are wrapped back into [ -180, 180 ] so a circle beside the
 * antimeridian stays inside the range the sanitizer accepts.
 *
 * @since  3.0.0
 * @param  {Array}  center [ lng, lat ].
 * @param  {number} radius Metres.
 * @return {Array}  [ ring ], the ring being 65 positions.
 */
export function circleToPolygon( center, radius ) {
    const STEPS        = 64;
    const EARTH_RADIUS = 6378137;

    const lng     = center[0] * Math.PI / 180;
    const lat     = center[1] * Math.PI / 180;
    const angular = radius / EARTH_RADIUS;
    const ring    = [];

    for ( let i = 0; i < STEPS; i++ ) {
        const bearing = 2 * Math.PI * i / STEPS;

        const pointLat = Math.asin(
            Math.sin( lat ) * Math.cos( angular ) + Math.cos( lat ) * Math.sin( angular ) * Math.cos( bearing )
        );

        const pointLng = lng + Math.atan2(
            Math.sin( bearing ) * Math.sin( angular ) * Math.cos( lat ),
            Math.cos( angular ) - Math.sin( lat ) * Math.sin( pointLat )
        );

        ring.push( [ wrapLongitude( pointLng * 180 / Math.PI ), pointLat * 180 / Math.PI ] );
    }

    ring.push( [ ring[0][0], ring[0][1] ] );

    return [ ring ];
}

/**
 * The stored features as a collection GL JS can take as-is: circles converted
 * to polygons, and every style property normalized so the layer paint
 * expressions can simply read them.
 *
 * @since  3.0.0
 * @param  {Array} features The stored features
 * @return {object} A FeatureCollection, possibly empty
 */
export function buildCollection( features ) {
    const collection = { type: 'FeatureCollection', features: [] };

    features.forEach( function( feature ) {
        try {
            const props    = ( feature && feature.properties ) ? feature.properties : {};
            const geometry = ( feature && feature.geometry ) ? feature.geometry : {};

            let drawn;

            if ( 'circle' === props.shape_type ) {
                if ( ! Array.isArray( geometry.coordinates ) || geometry.coordinates.length < 2 || ! ( props.radius > 0 ) ) {
                    throw new Error( 'a circle needs a centre and a positive radius' );
                }

                drawn = {
                    type:        'Polygon',
                    coordinates: circleToPolygon( geometry.coordinates, props.radius )
                };
            } else {
                if ( ! Array.isArray( geometry.coordinates ) || ! geometry.coordinates.length ) {
                    throw new Error( 'missing coordinates' );
                }

                drawn = {
                    type:        ( 'polyline' === props.shape_type ) ? 'LineString' : 'Polygon',
                    coordinates: geometry.coordinates
                };
            }

            collection.features.push( {
                type:       'Feature',
                geometry:   drawn,
                properties: {
                    fill:         !! props.fill,
                    fill_color:   safeColor( props.fill_color ),
                    fill_opacity: clampOpacity( props.fill_opacity ),
                    stroke_color: safeColor( props.stroke_color ),
                    stroke_width: strokeWidth( props.stroke_width ),

                    /*
                     * Converted here rather than on click: GL JS hands a click
                     * handler its own copy of the properties, and only strings
                     * and numbers survive that round trip.
                     */
                    message: bbcodeToHtml( props.message )
                }
            } );
        } catch ( e ) {
            // One malformed feature must never cost the visitor the map.
            console.warn( 'WPSL: skipped invalid map shape', feature, e );
        }
    } );

    return collection;
}

/**
 * Show a shape's message where the visitor clicked it.
 *
 * Bound to the two layers a shape can be hit on. The layers are recreated on
 * every style.load, but their listeners are NOT: GL JS keeps layer-scoped
 * listeners on the map when the layer goes, so each id is bound once per map
 * ( the bindHoverCursor pattern in wpsl-layers.js ) or a runtime setStyle()
 * would stack another set per style switch.
 *
 * The popup goes through map._wpslPopup, the one-popup-per-map slot the marker
 * side already keeps ( set in wpsl-infowindow.js, closed before a new one opens
 * in wpsl-geojson.js and wpsl-markers.js ), which is what makes a shape's
 * message and a marker's exclusive in both directions.
 *
 * @since  3.0.0
 * @param  {object} map      The GL JS map.
 * @param  {string} sourceId The shapes source id for this map.
 * @return {void}
 */
function bindMessages( map, sourceId ) {
    map._wpslShapeMessagesBound = map._wpslShapeMessagesBound || {};

    [ sourceId + '-fill', sourceId + '-line' ].forEach( function( layerId ) {
        if ( map._wpslShapeMessagesBound[ layerId ] ) {
            return;
        }

        map._wpslShapeMessagesBound[ layerId ] = true;

        map.on( 'click', layerId, function( event ) {
            const hit = ( event.features && event.features.length ) ? event.features[0] : null;
            const html = hit && hit.properties ? hit.properties.message : '';

            if ( ! html ) {
                return;
            }

            // Without this every click left another popup behind: a loose Popup
            // is tracked nowhere, so nothing could close it.
            if ( map._wpslPopup && map._wpslPopup.isOpen() ) {
                map._wpslPopup.remove();
            }

            // Already HTML, and only ever the tags bbcodeToHtml() writes: the
            // stored text was escaped before any of them were put back.
            map._wpslPopup = new mapboxgl.Popup().setLngLat( event.lngLat ).setHTML( html ).addTo( map );
        } );

        map.on( 'mouseenter', layerId, function() {
            map.getCanvas().style.cursor = 'pointer';
        } );

        map.on( 'mouseleave', layerId, function() {
            map.getCanvas().style.cursor = '';
        } );
    } );
}

export const shapes = {
    /**
     * Draw every stored shape on the map. Does nothing when the site has no shapes
     * or when this specific map opted out with [wpsl_map shapes="false"].
     *
     * @since  3.0.0
     * @param  {object} map      mapboxgl.Map instance
     * @param  {object} settings The per-map settings ( helpers.getMapSettings result )
     * @param  {number} mapIndex The map index, used to keep the source id unique
     * @return {void}
     */
    render: function( map, settings, mapIndex ) {
        const stored = ( typeof wpslSettings !== 'undefined' ) ? wpslSettings.mapShapes : null;

        if ( ! map || ! stored || ! stored.features || ! stored.features.length ) {
            return;
        }

        if ( settings && settings.shapes === false ) {
            return;
        }

        const collection = buildCollection( stored.features );

        if ( ! collection.features.length ) {
            return;
        }

        const sourceId = 'wpsl-shapes-' + ( ( typeof mapIndex === 'undefined' ) ? 0 : mapIndex );

        rendered.set( map, { sourceId: sourceId, hidden: false } );

        const addLayers = function() {
            try {
                // Runs again on every style.load. The source still being there
                // means setStyle() didn't destroy it, so the layers are intact.
                if ( map.getSource( sourceId ) ) {
                    return;
                }

                map.addSource( sourceId, { type: 'geojson', data: collection } );

                map.addLayer( {
                    id:     sourceId + '-fill',
                    type:   'fill',
                    source: sourceId,
                    filter: [ 'all', [ '==', [ 'geometry-type' ], 'Polygon' ], [ '==', [ 'get', 'fill' ], true ] ],
                    paint:  {
                        'fill-color':   [ 'get', 'fill_color' ],
                        'fill-opacity': [ 'get', 'fill_opacity' ]
                    }
                } );

                // GL JS still draws a hairline for a 0-width line, so borderless
                // shapes are filtered out of the line layer instead.
                map.addLayer( {
                    id:     sourceId + '-line',
                    type:   'line',
                    source: sourceId,
                    filter: [ '>', [ 'get', 'stroke_width' ], 0 ],
                    paint:  {
                        'line-color': [ 'get', 'stroke_color' ],
                        'line-width': [ 'get', 'stroke_width' ]
                    }
                } );

                bindMessages( map, sourceId );

                // A style switch while the directions are up rebuilds the
                // layers visible; put them back the way hide() left them.
                applyVisibility( map );
            } catch ( e ) {
                // Never let the shapes take the map down with them.
                console.warn( 'WPSL: could not add the map shapes layers', e );
            }
        };

        /*
         * on(), not once(): a runtime setStyle() ( add-ons and themes switch
         * styles ) throws away every source and layer and fires style.load
         * again, and a consumed one-shot listener never puts the shapes back.
         */
        map.on( 'style.load', addLayers );

        // The first style.load may already be behind us.
        if ( map.isStyleLoaded && map.isStyleLoaded() ) {
            addLayers();
        }
    },

    /**
     * Hide the shapes while the directions are shown, so the route is not
     * drawn underneath a zone. The source and layers stay; only their
     * visibility changes, and show() turns it back on.
     *
     * A hidden GL JS layer is also skipped by queryRenderedFeatures, so the
     * click handlers in bindMessages() stop firing for it as well.
     *
     * @since  3.0.0
     * @param  {object} map mapboxgl.Map instance
     * @return {void}
     */
    hide: function( map ) {
        const state = rendered.get( map );

        if ( ! state ) {
            return;
        }

        state.hidden = true;

        applyVisibility( map );
    },

    /**
     * Show the shapes hidden by hide() again.
     *
     * @since  3.0.0
     * @param  {object} map mapboxgl.Map instance
     * @return {void}
     */
    show: function( map ) {
        const state = rendered.get( map );

        if ( ! state ) {
            return;
        }

        state.hidden = false;

        applyVisibility( map );
    }
};