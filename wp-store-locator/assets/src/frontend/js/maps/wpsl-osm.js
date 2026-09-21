import { slData, config } from '../modules/wpsl-shared.js';
import { helpers } from '../modules/wpsl-helpers.js';
import { buttons } from '../modules/wpsl-buttons.js';

// Import OSM-specific modules
import { map } from './osm/modules/wpsl-map.js';
import { markers } from './osm/modules/wpsl-markers.js';
import { infoWindow } from './osm/modules/wpsl-infowindow.js';
import { api } from './osm/modules/wpsl-api.js';
import { geocodeCache } from './osm/modules/wpsl-geocode-cache.js';
import { search as osmSearch } from './osm/modules/wpsl-search.js';
import { shapes } from './osm/modules/wpsl-shapes.js';

/**
 * OpenStreetMap implementation for WPSL frontend
 *
 * @since 3.0.0
 */
export const osm = {
    self: null,

    // Expose modules
    map,
    markers,
    infoWindow,
    api,
    geocodeCache,
    search: osmSearch,
    shapes,

    /**
     * Initialize OpenStreetMap
     * 
     * @since 3.0.0
     */
    init: function() {
        this.self = this;

        // Initialize osm-specific marker properties
        slData.markers.latLng = {};
        slData.markers.layer = '';
        slData.markers.currentMapIndex = 0;

        // Extend helpers before any maps are created
        this.extendHelpers();

        // If the directionRedirect option is disabled,
        // then bind the remove directions button.
        if ( ! config.search.directionRedirect ) {
            buttons.bindRemoveDirections();
        }
    },

    /**
     * OpenStreetMaps Specific template helpers
     *
     * @since 3.0.0
     */
    templateHelpers: {
        /**
         * Create an OpenStreetMap directions URL.
         *
         * @since 3.0.0
         * @param {object} locationData Store location data
         * @return {string} The directions URL
         */
        createDirectionsUrl: function( locationData ) {
            const destLat = parseFloat( locationData.lat );
            const destLng = parseFloat( locationData.lng );

            if ( isNaN( destLat ) || isNaN( destLng ) ) {
                console.warn( 'createDirectionsUrl: Invalid coordinates detected:', locationData.lat, locationData.lng );
                return '#';
            }

            // openstreetmap.org expects lat,lng, unlike the lng,lat
            // order used by the Mapbox and OpenRouteService URLs.
            const origin = helpers.directions.getOriginCoords( 'latlng' );
            const destinationCoords = destLat + ',' + destLng;

            // Use OpenStreetMap's web directions interface instead of the
            // OpenRouteService one that handles the in-map routing, so both
            // Leaflet providers send visitors to the same place.
            const url = 'https://www.openstreetmap.org/directions?from=' + helpers.template.rfc3986EncodeURIComponent( origin ) + '&to=' + helpers.template.rfc3986EncodeURIComponent( destinationCoords );

            return wp.hooks.applyFilters( 'wpslDirectionsUrl', url );
        }
    },

    /**
     * Extend helpers.map with OpenStreetMaps specific functions.
     *
     * @since 3.0.0
     */
    extendHelpers: function() {
        /**
         * Get the supported [wpsl_map] shortcodes
         * settings for OpenStreetMaps.
         *
         * @since   3.0.0
         * @returns {object} List of supported settings.
         */
        helpers.map.getWpslMapShortcodeSettings = function() {
            return [ 'zoomLevel', 'scrollWheel', 'controlPosition', 'shapes' ];
        };

        /**
         * Set the map to the stored center and zoom level.
         *
         * @since 3.0.0
         * @returns {void}
         */
        helpers.map.restoreMapState = function() {
            slData.maps[0].setView( slData.viewport.center, slData.viewport.zoomLevel );
        };

        /**
         * Center the map based on the passed coordinates.
         *
         * @since 	3.0.0
         * @param   {object} args The coordinates
         * @returns {void}
         */
        helpers.map.setCenter = function( args ) {
            let latlng;

            if ( typeof args === 'undefined' ) {
                args = markers.getStartCoordinates();
            }

            if ( typeof args.getLatLng !== 'undefined' && typeof args.getLatLng() === 'object' ) {
                args = args.getLatLng();
            }

            latlng = L.latLng( args.lat, args.lng );

            // Preserve current zoom level when centering
            const currentZoom = slData.maps[0].getZoom();
            
            // Use animate:false to make setView synchronous and prevent race conditions
            slData.maps[0].setView( latlng, currentZoom, { animate: false } );
        };

        /**
         * Set the zoom level.
         *
         * @since   3.0.0
         * @param   {object} args The map object and the used zoom level
         * @returns {void}
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
         * @since   3.0.0
         * @param   {object} mapData Holds the viewport data and the current map object
         * @returns {bool}   changed Has the viewport changed or not
         */
        helpers.map.viewportChanged = function( mapData ) {
            let changed = false;

            if ( mapData.map.getCenter().lat !== mapData.viewport.center.lat || mapData.map.getCenter().lng !== mapData.viewport.center.lng || mapData.map.getZoom() !== mapData.viewport.zoomLevel ) {
                changed = true;
            }

            return changed;
        };

        /**
         * Store the current center coordinates and zoom
         * for later usage.
         *
         * @since 3.0.0
         * @param {object} map OpenStreetMaps object
         * @param {Function} callback Callback function to execute
         * @return {void}
         */
        helpers.map.setViewport = function( map, callback ) {
            map.once( 'moveend zoomend', function( e ) {
                slData.viewport = {
                    center: map.getCenter(),
                    zoomLevel: map.getZoom()
                };

                callback();
            });
        };
    }
};
