import { slData, config } from '../modules/wpsl-shared.js';
import { helpers } from '../modules/wpsl-helpers.js';
import { buttons } from '../modules/wpsl-buttons.js';

// Import Google Maps-specific modules
import { map } from './gmaps/modules/wpsl-map.js';
import { markers } from './gmaps/modules/wpsl-markers.js';
import { infoWindow } from './gmaps/modules/wpsl-infowindow.js';
import { api } from './gmaps/modules/wpsl-api.js';
import { search } from './gmaps/modules/wpsl-search.js';
import { shapes } from './gmaps/modules/wpsl-shapes.js';

/**
 * Google Maps implementation for WPSL frontend.
 *
 * @since 3.0.0
 */
export const gmaps = {
    self: null,

    // Expose modules
    map,
    markers,
    infoWindow,
    api,
    search,
    shapes,

    /**
     * Initialize Google Maps.
     * 
     * @since  3.0.0
     * @return {void}
     */
    init: function() {
        this.self = this;

        // Initialize marker data properties
        if ( ! this.markers.active ) {
            this.markers.active = {};
        }
        
        if ( ! this.markers.cluster ) {
            this.markers.cluster = {};
        }

        if ( ! this.markers.directionStops ) {
            this.markers.directionStops = [];
        }

        // Extend helpers before any maps are created
        this.extendHelpers();

        // If the directionRedirect option is disabled,
        // then bind the remove directions button.
        if ( ! config.search.directionRedirect ) {
            buttons.bindRemoveDirections();
        }
    },

    /**
     * Extend helpers.map with Google Maps specific functions.
     *
     * @since  3.0.0
     * @return {void}
     */
    extendHelpers: function() {
        /**
         * Get the latlng coordinates that are used to init the map.
         *
         * @since  3.0.0
         * @param  {number} mapIndex Number of the map
         * @return {object} startLatLng The latlng value where the map will initially focus on
         */
        helpers.map.gmapsStartLatLng = function( mapIndex ) {
            const firstLocation = helpers.map.getFirstLocation( mapIndex );

            let startLatLng;

            // Use the coordinates from the first location, or the default start
            // point defined on the settings page, falling back to 0,0.
            if ( ( typeof firstLocation !== 'undefined' && typeof firstLocation.lat !== 'undefined' ) && ( typeof firstLocation.lng !== 'undefined' ) ) {
                startLatLng = new google.maps.LatLng( firstLocation.lat, firstLocation.lng );
            } else if ( typeof config.map.startLatLng !== 'undefined' && config.map.startLatLng === '' ) {
                startLatLng = new google.maps.LatLng( 0,0 );
            } else {
                startLatLng = config.map.startLatLng;
            }

            return startLatLng;
        };

        /**
         * Get the supported [wpsl_map] shortcodes
         * settings for Google Maps.
         *
         * @since  3.0.0
         * @return {array} List of supported settings
         */
        helpers.map.getWpslMapShortcodeSettings = function() {
            return [ 'zoomLevel', 'mapType', 'mapTypeControl', 'mapStyle', 'mapId', 'streetView', 'scrollWheel', 'controlPosition', 'shapes' ];
        };

        /**
         * List of settings used to init a new map.
         *
         * @since  3.0.0
         * @param  {number} mapIndex The map index
         * @return {object} Map settings object
         */
        helpers.map.getSettingFields = function( mapIndex ) {
            let mapSettings = {
                zoomLevel: config.map.zoomLevel,
                mapType: config.map.type,
                mapTypeControl: config.map.typeControl,
                mapStyle: config.map.style,
                mapId: config.map.mapId,
                streetView: config.map.streetView,
                scrollWheel: config.map.scrollWheel,
                controlPosition: config.map.controlPosition,
                gestureHandling: config.map.gestureHandling
            };

            // If there are settings that are set through the shortcode,
            // then we use them instead of the default ones.
            if ( typeof window[ 'wpslMap_' + mapIndex ] === 'object' ) {
                mapSettings = helpers.map.checkShortCodeSettings( mapSettings, mapIndex );
            }

            mapSettings.startLatLng = helpers.map.gmapsStartLatLng( mapIndex );

            return wp.hooks.applyFilters( 'wpslMapSettings', mapSettings );
        };

        /**
         * Check if the provided coordinates are a Google Maps LatLng instance.
         * If not, convert them to one.
         *
         * @since  3.0.0
         * @param  {object} args The coordinates (can be object with lat/lng or LatLng instance)
         * @return {object} Google Maps LatLng instance
         */
        helpers.map.checkLatLngInstance = function( args ) {
            let latLng;

            if ( ! args ) {
                return null;
            }

            // Check if it's already a LatLng instance
            if ( typeof args.lat === 'function' ) {
                latLng = args;
            } 

            // Check if coordinates are nested in a latLng property
            else if ( args.latLng && typeof args.latLng === 'object' ) {
                if ( typeof args.latLng.lat === 'function' ) {
                    latLng = args.latLng;
                } else {
                    latLng = new google.maps.LatLng( args.latLng.lat, args.latLng.lng );
                }
            }
            // Standard case: lat/lng properties directly on args
            else {
                latLng = new google.maps.LatLng( args.lat, args.lng );
            }

            return latLng;
        };

        /**
         * Set the center of the map.
         *
         * @since  3.0.0
         * @param  {object} args The coordinates
         * @return {void}
         */
        helpers.map.setCenter = function( args ) {
            if ( typeof args === 'undefined' ) {
                args = markers.getStartCoordinates();
            }

            const latLng = helpers.map.checkLatLngInstance( args );
            if ( latLng ) {
                slData.maps[0].setCenter( latLng );
            }
        };

        /**
         * Set the zoom level of the map.
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
         * Attach a listener to the map that checks if the
         * zoom level has changed after the bounds change.
         *
         * This is used to prevent the map from zooming in to much
         * when there's only a single marker on the map.
         *
         * @since  3.0.0
         * @param  {object} map The map object
         * @param  {number} maxZoom The max zoom level
         * @return {void}
         */
        helpers.map.attachBoundsChangedListener = function( map, maxZoom ) {
            google.maps.event.addListenerOnce( map, 'bounds_changed', function() {
                const zoomLevel = Number( maxZoom );
                
                if ( this.getZoom() > zoomLevel ) {
                    this.setZoom( zoomLevel );
                }
            });
        };

        /**
         * Check if the viewpoint has changed since pageload.
         *
         * The center comparison goes through lat()/lng() because both sides are
         * google.maps.LatLng instances, unlike the OSM / Mapbox providers.
         *
         * @since  3.0.0
         * @param  {object} mapData Holds the viewport data and the current map object
         * @return {boolean} Has the viewport changed or not
         */
        helpers.map.viewportChanged = function( mapData ) {
            let changed = false;

            if ( mapData.map.getCenter().lat() !== mapData.viewport.center.lat() || mapData.map.getCenter().lng() !== mapData.viewport.center.lng() || mapData.map.getZoom() !== mapData.viewport.zoomLevel ) {
                changed = true;
            }

            return changed;
        };

        /**
         * Capture and store the current map viewport.
         *
         * @since  3.0.0
         * @param  {object} map The map object
         * @param  {Function} callback Callback function to execute
         * @return {void}
         */
        helpers.map.setViewport = function( map, callback ) {
            google.maps.event.addListenerOnce( map, 'bounds_changed', function() {
                slData.viewport = {
                    center: map.getCenter(),
                    zoomLevel: map.getZoom()
                };

                callback();
            });
        };
    },

    /**
     * Google Maps specific template helpers.
     *
     * @since 3.0.0
     */
    templateHelpers: {
        /**
         * Create a Google Maps directions URL.
         *
         * @since  3.0.0
         * @param  {object} data Store location data
         * @return {string} The directions URL
         */
        createDirectionsUrl: function( data ) {
            const destLat = parseFloat( data.lat );
            const destLng = parseFloat( data.lng );

            if ( isNaN( destLat ) || isNaN( destLng ) ) {
                console.warn( 'Google Maps createDirectionsUrl: Invalid coordinates detected:', data.lat, data.lng );
                return '#';
            }

            const origin = helpers.results.maybeUseBasicMode() ? '' : helpers.directions.getOriginCoords( 'latlng' );

            let destination;

            // By default use the address for the destination since Google sometimes
            // reverse geocodes coordinates into the wrong house number. Setting the
            // 'wpsl_force_direction_coordinates' filter to true switches to coordinates.
            //
            // Locations without an address have nothing to build a destination from,
            // so they always use the coordinates. The admin marks the address field as
            // required, but imported locations never pass through that check.
            const hasAddress = typeof data.address === 'string' && data.address.trim() !== '';

            if ( config.search.forceDirectionCoordinates || ! hasAddress ) {
                destination = destLat + ',' + destLng;
            } else {
                const zip = data.zip ? data.zip + ', ' : '';
                destination = helpers.template.rfc3986EncodeURIComponent( data.address + ', ' + data.city + ', ' + zip + data.country );
            }

            const url = 'https://www.google.com/maps/dir/?api=1&origin=' + origin + '&destination=' + destination + '&travelmode=' + config.search.directionsTravelMode;

            return wp.hooks.applyFilters( 'wpslDirectionsUrl', url );
        }
    },
};
