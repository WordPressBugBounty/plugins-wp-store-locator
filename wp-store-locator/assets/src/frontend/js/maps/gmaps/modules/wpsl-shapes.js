/**
 * Render the admin-defined map shapes ( the wpsl_map_shapes option ) on a
 * Google map.
 *
 * No drawing library is involved here: Terra Draw only exists in wp-admin,
 * visitors get plain google.maps overlays built straight from the stored
 * GeoJSON. The style guards below are deliberately the same ones the
 * editor adapter uses ( wpsl-shapes-draw-gmaps.js: safeColor / strokeWidth /
 * clampOpacity ), so a shape looks the same in the editor and on the map.
 *
 * @since 3.0.0
 */

import { bbcodeToHtml } from '../../../modules/wpsl-bbcode.js';

const DEFAULT_COLOR = '#cc3333';

/**
 * The fallback: one InfoWindow per map, reused by every shape on it.
 *
 * Only reached when render() is called without the locator's own window -- a
 * standalone caller, or a test. The normal path shares the window the markers
 * use; see messageWindow().
 *
 * @since 3.0.0
 * @type  {WeakMap}
 */
const infoWindows = new WeakMap();

/**
 * The overlays render() put on each map, so hide() / show() can take them off
 * and put them back while the directions are on the map.
 *
 * @since 3.0.0
 * @type  {WeakMap} map => Array of google.maps overlays
 */
const rendered = new WeakMap();

/**
 * The InfoWindow a shape's message should open in.
 *
 * Google closes an InfoWindow per instance, so "only one message at a time"
 * follows from everything on the map sharing one window: a shape's message
 * replaces a marker's, a marker's replaces a shape's, and the map-click handler
 * in wpsl-map.js that closes the marker window closes a shape's too.
 *
 * Handed in rather than imported, because importing wpsl-infowindow.js here
 * would pull the marker, api and helper modules into this chunk for the sake of
 * one object reference. Looked up per click, not per render, because which
 * window belongs to this map changes once a second [wpsl_map] is on the page.
 *
 * @since  3.0.0
 * @param  {object}   map      The Google map.
 * @param  {Function} [shared] Returns the locator's own window for this map.
 * @return {object} A google.maps.InfoWindow.
 */
function messageWindow( map, shared ) {
    const locator = ( 'function' === typeof shared ) ? shared() : null;

    if ( locator ) {
        return locator;
    }

    if ( ! infoWindows.has( map ) ) {
        infoWindows.set( map, new google.maps.InfoWindow() );
    }

    return infoWindows.get( map );
}

/**
 * Open a shape's message where the visitor clicked it.
 *
 * @since  3.0.0
 * @param  {object}   map      The Google map.
 * @param  {object}   overlay  The shape overlay.
 * @param  {string}   message  The message HTML.
 * @param  {Function} [shared] Returns the locator's own window for this map.
 * @return {void}
 */
function bindMessage( map, overlay, message, shared ) {
    if ( ! message ) {
        return;
    }

    overlay.addListener( 'click', function( event ) {
        const infoWindow = messageWindow( map, shared );

        if ( ! infoWindow ) {
            return;
        }

        /*
         * The shared window may still carry the closeclick handler
         * wpsl-infowindow.js attached for the marker that opened it last, which
         * restores that marker and returns focus to it. A shape has no marker to
         * restore, so the stale handler is dropped -- setContent() there clears
         * it the same way for the same reason.
         */
        if ( 'undefined' !== typeof google && google.maps && google.maps.event ) {
            google.maps.event.clearListeners( infoWindow, 'closeclick' );
        }

        infoWindow.setContent( message );

        // A shape has no one position to speak of, so the click is the
        // position -- the visitor asked about that part of it.
        infoWindow.setPosition( event.latLng );
        infoWindow.open( map );
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
 * 0 is a valid width and has to survive: it is how a shape is asked to render
 * without a border. Anything that treats it as "empty" turns it back into the
 * default 2px outline.
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
 * Feature properties as Google Maps overlay options.
 *
 * strokeOpacity follows the width, because Google draws a 0-weight stroke as
 * a hairline on some overlay types.
 *
 * clickable is the shape's message and nothing else. A Google overlay fires no
 * click event at all while it is false -- which is why a message set in the
 * editor did nothing here, on every shape type, while Leaflet and GL JS showed
 * it -- and a shape WITHOUT a message has to stay transparent to the pointer,
 * or a zone drawn over a town swallows the clicks meant for the markers inside
 * it.
 *
 * @since  3.0.0
 * @param  {object}  props     The feature properties
 * @param  {boolean} clickable Whether this shape has a message to open
 * @return {object} The shared overlay options
 */
function overlayStyle( props, clickable ) {
    const weight = strokeWidth( props.stroke_width );

    return {
        strokeColor:   safeColor( props.stroke_color ),
        strokeWeight:  weight,
        strokeOpacity: weight > 0 ? 1 : 0,
        fillColor:     safeColor( props.fill_color ),
        fillOpacity:   props.fill ? clampOpacity( props.fill_opacity ) : 0,
        clickable:     !! clickable
    };
}

/**
 * A GeoJSON [ lng, lat ] position as a Google Maps LatLngLiteral.
 *
 * @since  3.0.0
 * @param  {Array} position [ lng, lat ]
 * @return {object} { lat, lng }
 */
function toLatLng( position ) {
    return { lat: position[1], lng: position[0] };
}

export const shapes = {
    /**
     * Draw every stored shape on the map. Does nothing when the site has no shapes
     * or when this specific map opted out with [wpsl_map shapes="false"].
     *
     * @since  3.0.0
     * @param  {object}   map      google.maps.Map instance
     * @param  {object}   settings The per-map settings ( helpers.getMapSettings result )
     * @param  {Function} [shared] Returns the locator's own InfoWindow for this
     *                             map, so a shape's message and a marker's share
     *                             one window -- see messageWindow().
     * @return {void}
     */
    render: function( map, settings, shared ) {
        const collection = ( typeof wpslSettings !== 'undefined' ) ? wpslSettings.mapShapes : null;

        if ( ! map || ! collection || ! collection.features || ! collection.features.length ) {
            return;
        }

        if ( settings && settings.shapes === false ) {
            return;
        }

        const overlays = [];

        rendered.set( map, overlays );

        collection.features.forEach( function( feature ) {
            try {
                const props    = ( feature && feature.properties ) ? feature.properties : {};
                const geometry = ( feature && feature.geometry ) ? feature.geometry : {};

                // Built before the style, which needs to know whether there is
                // anything for a click to open.
                const message = bbcodeToHtml( props.message );
                const style   = overlayStyle( props, message );

                let overlay;

                if ( 'circle' === props.shape_type ) {
                    overlay = new google.maps.Circle( Object.assign( {
                        center: toLatLng( geometry.coordinates ),
                        radius: props.radius,
                        map:    map
                    }, style ) );
                } else if ( 'polyline' === props.shape_type ) {
                    overlay = new google.maps.Polyline( Object.assign( {
                        path: geometry.coordinates.map( toLatLng ),
                        map:  map
                    }, style ) );
                } else {
                    // polygon + rectangle, both stored as a GeoJSON Polygon.
                    // Google reads an array of paths as the outer ring plus holes.
                    overlay = new google.maps.Polygon( Object.assign( {
                        paths: geometry.coordinates.map( function( ring ) {
                            return ring.map( toLatLng );
                        } ),
                        map: map
                    }, style ) );
                }

                bindMessage( map, overlay, message, shared );

                overlays.push( overlay );
            } catch ( e ) {
                // One malformed feature must never cost the visitor the map.
                console.warn( 'WPSL: skipped invalid map shape', feature, e );
            }
        } );
    },

    /**
     * Take the shapes off the map while the directions are shown, so the
     * route is not drawn underneath a zone. Nothing is destroyed: show()
     * puts the same overlays back.
     *
     * @since  3.0.0
     * @param  {object} map google.maps.Map instance
     * @return {void}
     */
    hide: function( map ) {
        const overlays = rendered.get( map );

        if ( ! overlays ) {
            return;
        }

        overlays.forEach( function( overlay ) {
            overlay.setMap( null );
        } );
    },

    /**
     * Put the shapes hidden by hide() back on the map.
     *
     * @since  3.0.0
     * @param  {object} map google.maps.Map instance
     * @return {void}
     */
    show: function( map ) {
        const overlays = rendered.get( map );

        if ( ! overlays ) {
            return;
        }

        overlays.forEach( function( overlay ) {
            if ( overlay.getMap() !== map ) {
                overlay.setMap( map );
            }
        } );
    }
};