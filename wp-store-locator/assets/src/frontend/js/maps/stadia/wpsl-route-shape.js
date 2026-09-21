/**
 * The number of decimals the Stadia Routing API encodes a route shape with.
 *
 * Valhalla's shape_format option is validated by the API but not honoured: it
 * accepts polyline5, and still answers with polyline6. Leaflet.encoded decodes
 * with 5 decimals unless told otherwise, which multiplies every coordinate by
 * ten ( lat 51.9 becomes 519.2 ), and Leaflet then clamps those to the maximum
 * Mercator latitude -- so the route is drawn off the map while the step list,
 * which reads the maneuvers instead of the shape, still looks correct.
 *
 * @since 3.0.0
 * @type  {number}
 */
export const STADIA_SHAPE_PRECISION = 6;

/**
 * Decode a Stadia ( Valhalla ) route shape into Leaflet latLng pairs.
 *
 * @since  3.0.0
 * @param  {string} shape        The encoded shape from the route leg.
 * @param  {object} polylineUtil L.PolylineUtil, from the Leaflet.encoded script.
 * @return {Array}               The [ lat, lng ] pairs, or an empty array if there is nothing to decode.
 */
export const decodeStadiaShape = ( shape, polylineUtil ) => {
    if ( typeof shape !== 'string' || ! shape || ! polylineUtil || typeof polylineUtil.decode !== 'function' ) {
        return [];
    }

    return polylineUtil.decode( shape, STADIA_SHAPE_PRECISION );
};
