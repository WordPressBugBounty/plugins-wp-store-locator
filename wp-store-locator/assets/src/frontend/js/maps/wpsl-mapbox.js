import { slData, config } from '../modules/wpsl-shared.js';
import { helpers } from '../modules/wpsl-helpers.js';
import { buttons } from '../modules/wpsl-buttons.js';

// Import Mapbox-specific modules
import { map } from './mapbox/modules/wpsl-map.js';
import { markers } from './mapbox/modules/wpsl-markers.js';
import { infoWindow } from './mapbox/modules/wpsl-infowindow.js';
import { api } from './mapbox/modules/wpsl-api.js';
import { geojson } from './mapbox/modules/wpsl-geojson.js';
import { layers } from './mapbox/modules/wpsl-layers.js';
import { search } from './mapbox/modules/wpsl-search.js';
import { shapes } from './mapbox/modules/wpsl-shapes.js';

/**
 * Mapbox implementation for WPSL frontend.
 *
 * @since 3.0.0
 */
export const mapbox = {
    self: null,

    // Expose modules
    map,
    markers,
    infoWindow,
    api,
    geojson,
    layers,
    search,
    shapes,
    
    /**
     * Initialize Mapbox.
     * 
     * @since  3.0.0
     * @return {void}
     */
    init: function() {
        this.self = this;

        // Initialize mapbox-specific marker properties
        slData.markers.canvas = '';
        slData.markers.moved = false;

        buttons.bindRemoveDirections();
        
        slData.activeSources = [];
        slData.canvas = [];

        // Extend helpers before any maps are created
        this.extendHelpers();
    },

    /**
     * Add the 'reload' and 'find location' icon to the map.
     *
     * @since  3.0.0
     * @return {void}
     */
    mapControlIcons: function() {
        jQuery( '.mapboxgl-canvas' ).after( config.map.controls );
        
        buttons.bindMapControlIcons();
    },

    /**
     * Mapbox specific template helpers.
     *
     * @since 3.0.0
     */
    templateHelpers: {
        /**
         * Create an OpenStreetMap directions URL.
         *
         * @since  3.0.0
         * @param  {object} locationData Store location data
         * @return {string} The directions URL
         */
        createDirectionsUrl: function( locationData ) {
            const destLat = parseFloat( locationData.lat );
            const destLng = parseFloat( locationData.lng );

            if ( isNaN( destLat ) || isNaN( destLng ) ) {
                console.warn( 'Mapbox createDirectionsUrl: Invalid coordinates detected:', locationData.lat, locationData.lng );
                return '#';
            }

            // openstreetmap.org expects lat,lng, unlike the lng,lat order Mapbox uses.
            const origin = helpers.directions.getOriginCoords( 'latlng' );
            const destination = destLat + ',' + destLng;

            // Mapbox only offers the Directions API, not a consumer-facing directions
            // site, so send visitors to the same OpenStreetMap page the other providers
            // use rather than to a raw API endpoint carrying the access token.
            const url = 'https://www.openstreetmap.org/directions?from=' + helpers.template.rfc3986EncodeURIComponent( origin ) + '&to=' + helpers.template.rfc3986EncodeURIComponent( destination );

            return wp.hooks.applyFilters( 'wpslDirectionsUrl', url );
        }
    },

    /**
     * Extend helpers.map with Mapbox specific functions.
     *
     * @since  3.0.0
     * @return {void}
     */
    extendHelpers: function() {
        /**
         * Get the supported [wpsl_map] shortcodes settings for Mapbox.
         *
         * @since  3.0.0
         * @return {array} List of supported settings
         */
        helpers.map.getWpslMapShortcodeSettings = function() {
            return [ 'zoomLevel', 'scrollWheel', 'controlPosition', 'shapes' ];
        };

        /**
         * Center the map based on the passed coordinates.
         *
         * @since  3.0.0
         * @param  {object} args The coordinates
         * @return {void}
         */
        helpers.map.setCenter = function( args ) {
            if ( typeof args === 'undefined' ) {
                args = markers.getStartCoordinates();
            }

            if ( typeof args.getLatLng !== 'undefined' && typeof args.getLatLng() === 'object' ) {
                args = args.getLatLng();
            }

            const latLng = new mapboxgl.LngLat( args.lng, args.lat );
            slData.maps[0].setCenter( latLng );
        };

        /**
         * Set the zoom level.
         *
         * @since  3.0.0
         * @param  {object} args The map object and the used zoom level
         * @return {void}
         */
        helpers.map.setZoom = function( args ) {
            if ( typeof args.map === 'undefined' ) {
                args.map = slData.maps[0];
            }

            args.map.setZoom( args.zoom );
        };

        /**
         * Check if the viewpoint has changed since pageload.
         *
         * @since  3.0.0
         * @param  {object} mapData Holds the viewport data and the current map object
         * @return {boolean} Has the viewport changed or not
         */
        helpers.map.viewportChanged = function( mapData ) {
            let changed = false;

            if ( mapData.map.getCenter().lat !== mapData.viewport.center.lat || mapData.map.getCenter().lng !== mapData.viewport.center.lng || mapData.map.getZoom() !== mapData.viewport.zoomLevel ) {
                changed = true;
            }

            return changed;
        };

        /**
         * Store the current center coordinates and zoom for later usage.
         *
         * @since  3.0.0
         * @param  {object} map Mapbox map object
         * @param  {Function} callback Callback function to execute
         * @return {void}
         */
        helpers.map.setViewport = function( map, callback ) {
            map.once( 'moveend', () => {
                slData.viewport = {
                    center: map.getCenter(),
                    zoomLevel: map.getZoom()
                };

                callback();
            });
        };
    }
};
