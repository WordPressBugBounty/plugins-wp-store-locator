/**
 * Render the admin-defined map shapes ( the wpsl_map_shapes option ) on a
 * Leaflet map, for both the OpenStreetMap and the Stadia Maps provider.
 *
 * No drawing library is involved: the editor's Geoman layers only exist in
 * wp-admin, visitors get plain Leaflet paths built from the stored GeoJSON.
 * The style guards below are deliberately the editor adapter's own
 * ( wpsl-shapes-draw-osm.js: safeColor / strokeWidth / clampOpacity ), so a
 * shape looks the same in the editor and on the map.
 *
 * @since 3.0.0
 */

import { bbcodeToHtml } from '../../../modules/wpsl-bbcode.js';

const DEFAULT_COLOR = '#cc3333';

/**
 * The layers render() put on each map, so hide() / show() can take them off
 * and put them back while the directions are on the map.
 *
 * @since 3.0.0
 * @type  {WeakMap} map => Array of Leaflet layers
 */
const rendered = new WeakMap();

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
 * 0 has to survive: it is how a shape asks for no border. Treating it as
 * "empty" turns it back into the default 2px outline.
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
 * Whether a feature is a line rather than a region.
 *
 * @since  3.0.0
 * @param  {object} props    The feature properties
 * @param  {object} geometry The feature geometry
 * @return {boolean}
 */
function isLine( props, geometry ) {
    return 'polyline' === ( props && props.shape_type ) || 'LineString' === ( geometry && geometry.type );
}

/**
 * Feature properties as Leaflet path options.
 *
 * A line is never filled, whatever the stored fill says. Leaflet does fill an
 * L.Polyline when asked: its SVG renderer sets the fill attribute on any L.Path
 * with options.fill, and an open subpath is closed implicitly for filling, so a
 * fill: true line renders as a translucent blob closing the route back on
 * itself. Google and Mapbox cannot fill a LineString at all, so honouring the
 * flag would also make the same saved shape look different per provider.
 *
 * @since  3.0.0
 * @param  {object} props    The feature properties
 * @param  {object} geometry The feature geometry
 * @return {object} The Leaflet path options
 */
function layerStyle( props, geometry ) {
    const filled = !! props.fill && ! isLine( props, geometry );

    return {
        color:       safeColor( props.stroke_color ),
        weight:      strokeWidth( props.stroke_width ),
        fillColor:   safeColor( props.fill_color ),
        fill:        filled,
        fillOpacity: filled ? clampOpacity( props.fill_opacity ) : 0
    };
}

/**
 * A GeoJSON [ lng, lat ] position as a Leaflet [ lat, lng ] one.
 *
 * @since  3.0.0
 * @param  {Array} position [ lng, lat ]
 * @return {Array} [ lat, lng ]
 */
function toLatLng( position ) {
    return [ position[1], position[0] ];
}

export const shapes = {
    /**
     * Draw every stored shape on the map. Does nothing when the site has no shapes
     * or when this specific map opted out with [wpsl_map shapes="false"].
     *
     * @since  3.0.0
     * @param  {object} map      Leaflet map instance
     * @param  {object} settings The per-map settings ( helpers.getMapSettings result )
     * @return {void}
     */
    render: function( map, settings ) {
        const collection = ( typeof wpslSettings !== 'undefined' ) ? wpslSettings.mapShapes : null;

        if ( ! map || ! collection || ! collection.features || ! collection.features.length ) {
            return;
        }

        if ( settings && settings.shapes === false ) {
            return;
        }

        const layers = [];

        rendered.set( map, layers );

        collection.features.forEach( function( feature ) {
            try {
                const props    = ( feature && feature.properties ) ? feature.properties : {};
                const geometry = ( feature && feature.geometry ) ? feature.geometry : {};
                const style    = layerStyle( props, geometry );

                let layer;

                if ( 'circle' === props.shape_type ) {
                    layer = L.circle( toLatLng( geometry.coordinates ), Object.assign( { radius: props.radius }, style ) );
                } else if ( 'polyline' === props.shape_type ) {
                    layer = L.polyline( geometry.coordinates.map( toLatLng ), style );
                } else {
                    // polygon + rectangle, both stored as a GeoJSON Polygon.
                    layer = L.polygon( geometry.coordinates.map( function( ring ) {
                        return ring.map( toLatLng );
                    } ), style );
                }

                /*
                 * Marks the layer as ours so the directions cleanup leaves it
                 * alone. api.removePolyline() runs before every search and
                 * sweeps every SVG-rendered vector off map 0 ( anything with a
                 * _path ) -- these shapes included, since they are L.Path
                 * subclasses like the directions polyline it means to remove.
                 */
                layer._wpslShape = true;

                /*
                 * Leaflet renders popup content as HTML, which is all
                 * bbcodeToHtml() ever returns: it escapes the stored text
                 * before putting back the handful of tags it supports.
                 */
                const message = bbcodeToHtml( props.message );

                if ( message ) {
                    layer.bindPopup( message );
                }

                layer.addTo( map );

                layers.push( layer );
            } catch ( e ) {
                // One malformed feature must never cost the visitor the map.
                console.warn( 'WPSL: skipped invalid map shape', feature, e );
            }
        } );
    },

    /**
     * Take the shapes off the map while the directions are shown, so the
     * route is not drawn underneath a zone. The layers are kept, not
     * destroyed: show() adds the same ones back.
     *
     * Removed rather than styled transparent, because a transparent L.Path
     * still catches clicks and would still open its popup over the route.
     *
     * @since  3.0.0
     * @param  {object} map Leaflet map instance
     * @return {void}
     */
    hide: function( map ) {
        const layers = rendered.get( map );

        if ( ! layers ) {
            return;
        }

        layers.forEach( function( layer ) {
            map.removeLayer( layer );
        } );
    },

    /**
     * Put the shapes hidden by hide() back on the map.
     *
     * addTo() is idempotent in Leaflet ( a layer already on the map is left
     * alone ), so calling this on visible shapes is harmless.
     *
     * @since  3.0.0
     * @param  {object} map Leaflet map instance
     * @return {void}
     */
    show: function( map ) {
        const layers = rendered.get( map );

        if ( ! layers ) {
            return;
        }

        layers.forEach( function( layer ) {
            layer.addTo( map );
        } );
    }
};