/**
 * The circle around the searched location.
 *
 * @since 3.1.1
 */

import { slData, config } from './wpsl-shared.js';

/**
 * Show the search radius on the map, enabled with the "Draw the search
 * radius circle?" option ( config.map.radiusCircle ).
 *
 * This file decides when the circle is drawn and how big it is. Each map
 * service draws it with its own radiusCircle module, exposed on the provider
 * object: draw( map, center, meters, style ), getBounds( map ), remove( map ),
 * hide( map ) and show( map ).
 *
 * The circle only goes on the store locator map ( maps[0] ), and only after a
 * search that used a location and a radius. With "Auto adjust the zoom level"
 * on, the map then fits the circle instead of the markers, see fit().
 *
 * @since 3.1.1
 */
export const radiusCircle = {

    /**
     * Whether the map was fitted to the circle of the current search.
     * makeAjaxRequest() then leaves the view alone when the results arrive.
     *
     * @since 3.1.1
     * @type  {boolean}
     */
    fitted: false,

    /**
     * Replace the circle for a new search.
     *
     * Runs when the search request is sent: the circle of the previous search
     * goes, and the new one is drawn right away, also when nothing is found,
     * so the visitor sees the area that was searched.
     *
     * Unlike the markers, the circle's bounds are known before the results
     * arrive, so with "Auto adjust the zoom level" on the map moves to it
     * here, and the markers and start marker then appear in the final view.
     * A map reset ( restoreViewport ) goes back to the page load view instead.
     *
     * @since  3.1.1
     * @param  {object} ajaxData   The search request params.
     * @param  {object} searchArgs The arguments the request was built from.
     * @return {void}
     */
    update: function( ajaxData, searchArgs = {} ) {
        this.remove();
        this.fitted = false;

        const area = this.getArea( ajaxData, searchArgs );

        if ( ! area ) {
            return;
        }

        const provider = slData.provider;
        const map      = slData.maps[0];

        /**
         * Filter the look of the search radius circle.
         *
         * @since 3.1.1
         * @param {object} style      color, strokeWeight ( px ), strokeOpacity and fillOpacity ( 0 - 1 ).
         * @param {object} area       center ( lat / lng ) and radius ( meters ).
         * @param {object} ajaxData   The search request params.
         */
        const style = wp.hooks.applyFilters( 'wpslRadiusCircleStyle', {
            color:         config.map.radiusCircle.color,
            strokeWeight:  2,
            strokeOpacity: 0.8,
            fillOpacity:   0.15
        }, area, ajaxData );

        try {
            provider.radiusCircle.draw( map, area.center, area.radius, style );

            // A search started while the directions are shown keeps the circle hidden with the other shapes.
            if ( slData.directions.active ) {
                provider.radiusCircle.hide( map );
            }

            if ( config.map.fitBounds && ! searchArgs.restoreViewport ) {
                this.fitted = this.fit();
            }
        } catch ( e ) {
            // Never let the circle take the search down with it.
            console.warn( 'WPSL: could not draw the search radius', e );
        }
    },

    /**
     * The center and size of the searched area, or null when no circle is drawn.
     *
     * Not drawn without a location or a radius ( name and category searches ),
     * for a country / state search, for the nearest location button ( it
     * ignores the radius ), for the page load without a geolocated visitor
     * ( it loads a number of locations, not a radius ), for a map reset,
     * and for an approximate visitor location, which the server searches
     * with a bigger radius than the one sent.
     *
     * @since  3.1.1
     * @param  {object} ajaxData   The search request params.
     * @param  {object} searchArgs The arguments the request was built from.
     * @return {object|null} center ( lat / lng ) and radius ( meters ).
     */
    getArea: function( ajaxData, searchArgs ) {
        if ( ! this.enabled() || ! ajaxData ) {
            return null;
        }

        if ( ajaxData.autoload || ajaxData.approximate || searchArgs.reset ) {
            return null;
        }

        // A custom search type ( name, category, country, state, nearest ) isn't a radius search.
        if ( typeof ajaxData.types !== 'undefined' && ajaxData.types !== 'location' ) {
            return null;
        }

        if ( typeof ajaxData.type !== 'undefined' ) {
            return null;
        }

        const lat    = parseFloat( ajaxData.lat );
        const lng    = parseFloat( ajaxData.lng );
        const radius = parseFloat( ajaxData.search_radius );

        if ( isNaN( lat ) || isNaN( lng ) || isNaN( radius ) || radius <= 0 ) {
            return null;
        }

        const unit = ( ajaxData.distance_unit || config.search.distanceUnit ) === 'mi' ? 1609.344 : 1000;

        return {
            center: { lat: lat, lng: lng },
            radius: radius * unit
        };
    },

    /**
     * Whether the option is on and the map service can draw the circle.
     *
     * @since  3.1.1
     * @return {boolean}
     */
    enabled: function() {
        const settings = config.map ? config.map.radiusCircle : null;

        return !! ( settings && settings.enabled && slData.maps[0] && slData.provider && slData.provider.radiusCircle );
    },

    /**
     * Fit the map to the circle of the current search.
     *
     * A radius search only returns locations inside the circle, so it holds
     * every result, and also shows the searched area when nothing was found.
     * Goes through the provider's markers.fitBounds(), which waits for a
     * hidden map and keeps the "Max auto zoom level".
     *
     * @since  3.1.1
     * @return {boolean} Whether the map was fitted to a circle.
     */
    fit: function() {
        const provider = slData.provider;
        const map      = slData.maps[0];

        if ( ! this.enabled() || typeof provider.radiusCircle.getBounds !== 'function' ) {
            return false;
        }

        const bounds = provider.radiusCircle.getBounds( map );

        if ( ! bounds ) {
            return false;
        }

        // A provider can fit the circle with other options than its markers ( Leaflet: less padding ).
        provider.markers.fitBounds( bounds, map, provider.radiusCircle.fitOptions );

        return true;
    },

    /**
     * Take the circle off the map.
     *
     * @since  3.1.1
     * @return {void}
     */
    remove: function() {
        const provider = slData.provider;

        if ( ! slData.maps[0] || ! provider || ! provider.radiusCircle ) {
            return;
        }

        provider.radiusCircle.remove( slData.maps[0] );
    },

    /**
     * Hide the circle while the directions are shown, or show it again.
     *
     * @since  3.1.1
     * @param  {boolean} visible
     * @return {void}
     */
    setVisible: function( visible ) {
        const provider = slData.provider;

        if ( ! slData.maps[0] || ! provider || ! provider.radiusCircle ) {
            return;
        }

        if ( visible ) {
            provider.radiusCircle.show( slData.maps[0] );
        } else {
            provider.radiusCircle.hide( slData.maps[0] );
        }
    }
};
