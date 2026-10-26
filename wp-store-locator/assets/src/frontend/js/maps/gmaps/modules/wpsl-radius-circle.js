/**
 * The search radius circle on a Google map.
 *
 * When and how big is decided in modules/wpsl-radius-circle.js.
 *
 * @since 3.1.1
 */

// The circle drawn on each map.
const circles = new WeakMap();

export const radiusCircle = {
    /**
     * Draw the circle.
     *
     * @since  3.1.1
     * @param  {object} map    google.maps.Map instance
     * @param  {object} center lat / lng
     * @param  {number} meters The radius
     * @param  {object} style  color, strokeWeight, strokeOpacity, fillOpacity
     * @return {void}
     */
    draw: function( map, center, meters, style ) {
        this.remove( map );

        const circle = new google.maps.Circle( {
            center:        center,
            radius:        meters,
            strokeColor:   style.color,
            strokeWeight:  style.strokeWeight,
            strokeOpacity: style.strokeOpacity,
            fillColor:     style.color,
            fillOpacity:   style.fillOpacity,

            // Clicks go to the map, and the circle stays below the map shapes.
            clickable:     false,
            zIndex:        0,
            map:           map
        } );

        circles.set( map, circle );
    },

    /**
     * The bounds of the circle, to fit the map to.
     *
     * @since  3.1.1
     * @param  {object} map google.maps.Map instance
     * @return {object|null} google.maps.LatLngBounds, or null without a circle.
     */
    getBounds: function( map ) {
        const circle = circles.get( map );

        return circle ? circle.getBounds() : null;
    },

    /**
     * Remove the circle.
     *
     * @since  3.1.1
     * @param  {object} map google.maps.Map instance
     * @return {void}
     */
    remove: function( map ) {
        const circle = circles.get( map );

        if ( circle ) {
            circle.setMap( null );
            circles.delete( map );
        }
    },

    /**
     * Take the circle off the map while the directions are shown.
     *
     * @since  3.1.1
     * @param  {object} map google.maps.Map instance
     * @return {void}
     */
    hide: function( map ) {
        const circle = circles.get( map );

        if ( circle ) {
            circle.setMap( null );
        }
    },

    /**
     * Put the circle hidden by hide() back on the map.
     *
     * @since  3.1.1
     * @param  {object} map google.maps.Map instance
     * @return {void}
     */
    show: function( map ) {
        const circle = circles.get( map );

        if ( circle && circle.getMap() !== map ) {
            circle.setMap( map );
        }
    }
};
