/**
 * The search radius circle on a Leaflet map ( OpenStreetMap, Stadia ).
 *
 * When and how big is decided in modules/wpsl-radius-circle.js.
 *
 * @since 3.1.1
 */

// The circle drawn on each map.
const circles = new WeakMap();

export const radiusCircle = {
    /**
     * The options fit() passes to markers.fitBounds().
     *
     * Leaflet zooms in whole levels, and with the 50px padding of the marker
     * fit a circle that just misses the next level shows at half the map's
     * height. 20px leaves room for the outline and the markers inside it.
     *
     * @since 3.1.1
     * @type  {object}
     */
    fitOptions: { padding: [ 20, 20 ] },

    /**
     * Draw the circle.
     *
     * @since  3.1.1
     * @param  {object} map    Leaflet map instance
     * @param  {object} center lat / lng
     * @param  {number} meters The radius
     * @param  {object} style  color, strokeWeight, strokeOpacity, fillOpacity
     * @return {void}
     */
    draw: function( map, center, meters, style ) {
        this.remove( map );

        const circle = L.circle( [ center.lat, center.lng ], {
            radius:      meters,
            color:       style.color,
            weight:      style.strokeWeight,
            opacity:     style.strokeOpacity,
            fillColor:   style.color,
            fillOpacity: style.fillOpacity,

            // Clicks go to the map, it never covers a marker or the map itself.
            interactive: false
        } );

        /*
         * api.removePolyline() runs before every search and removes every
         * vector on the map that isn't marked as ours. This one is removed
         * by remove() instead, when the next search starts.
         */
        circle._wpslShape = true;

        circle.addTo( map );

        circles.set( map, circle );
    },

    /**
     * The bounds of the circle, to fit the map to.
     *
     * Worked out from its center and radius, so it also works while the
     * circle is hidden for the directions.
     *
     * @since  3.1.1
     * @param  {object} map Leaflet map instance
     * @return {object|null} L.LatLngBounds, or null without a circle.
     */
    getBounds: function( map ) {
        const circle = circles.get( map );

        return circle ? circle.getLatLng().toBounds( circle.getRadius() * 2 ) : null;
    },

    /**
     * Remove the circle.
     *
     * @since  3.1.1
     * @param  {object} map Leaflet map instance
     * @return {void}
     */
    remove: function( map ) {
        const circle = circles.get( map );

        if ( circle ) {
            map.removeLayer( circle );
            circles.delete( map );
        }
    },

    /**
     * Take the circle off the map while the directions are shown.
     *
     * @since  3.1.1
     * @param  {object} map Leaflet map instance
     * @return {void}
     */
    hide: function( map ) {
        const circle = circles.get( map );

        if ( circle ) {
            map.removeLayer( circle );
        }
    },

    /**
     * Put the circle hidden by hide() back on the map.
     *
     * @since  3.1.1
     * @param  {object} map Leaflet map instance
     * @return {void}
     */
    show: function( map ) {
        const circle = circles.get( map );

        if ( circle ) {
            circle.addTo( map );
        }
    }
};
