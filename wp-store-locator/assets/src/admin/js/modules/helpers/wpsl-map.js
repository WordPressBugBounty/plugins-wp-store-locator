import { state } from '../wpsl-shared.js';
import { mapObjects } from '../wpsl-map-bootstrap.js';
import { markers } from '../wpsl-markers.js';
import { api } from '../wpsl-api.js';

/**
 * Map-related helper functions
 * 
 * @since 3.0.0
 */
export const map = {
    /**
     * Center the map on the passed coordinates, with an optional start marker.
     *
     * @since   3.0.0
     * @param   {object} args Holds the latLng, zoom and addMarker fields
     * @returns {void}
     */
    setViewport: function( args ) {
        const elemId = args.elemId || 'wpsl-' + state.mapService + '-wrap';
        const map = mapObjects.get( elemId );
        const addMarker = typeof args.addMarker !== 'undefined' ? args.addMarker : true;
        const zoom = args.zoom || parseInt( wpslSettings.defaultZoom );

        if ( ! map || ! args.latLng ) {
            return;
        }

        if ( state.mapService === 'gmaps' ) {
            map.setCenter( args.latLng );
            map.setZoom( zoom );
        } else if ( state.mapService === 'mapbox' ) {
            map.setCenter( [ args.latLng.lng, args.latLng.lat ] );
            map.setZoom( zoom );
        } else if ( state.mapService === 'osm' || state.mapService === 'stadia' ) {
            map.setView( [ args.latLng.lat, args.latLng.lng ], zoom );
        }

        if ( addMarker ) {
            markers.getActive().add( { 
                latLng: args.latLng, 
                elemId: elemId,
                openInfoWindow: args.openInfoWindow
            } );
        }
    },

    /**
     * Show the text telling users they can drag the marker when the
     * geocode / autocomplete response isn't entirely correct.
     *
     * It starts hidden because a new location has no marker on the map yet.
     *
     * @since   3.0.0
     * @returns {void}
     */
    maybeShowMarkerDragDescription: function() {
        const $dragDesc = jQuery( '#wpsl-map-preview .wpsl-marker-drag-desc' );

        if ( $dragDesc.hasClass( 'wpsl-hide' ) ) {
            $dragDesc.removeClass( 'wpsl-hide' );
        }
    },

    /**
     * Set the initial viewport from the saved location, or fall back to
     * the default start location without a marker.
     *
     * @since   3.0.0
     * @param   {object} startLatLng The start coordinates
     * @returns {void}
     */
    setEditorViewport: function( startLatLng = '' ) {
        const lat = jQuery( '#wpsl-lat' ).val();
        const lng = jQuery( '#wpsl-lng' ).val();
        const isOnline = jQuery( '#wpsl-online' ).is( ':checked' );

        if ( isOnline ) {
            return;
        }

        if ( lat && lng ) {
            const latLng = api[ state.mapService ].createLatLngObj( lat, lng );

            this.setViewport( { 'latLng': latLng } );
        } else if ( startLatLng ) {
            this.setViewport( { 'latLng': startLatLng, 'addMarker': false } );
        }
    },

    /**
     * Get the selected map provider from settings
     * 
     * @since  3.0.0
     * @return {string} The selected map service
     */
    selectedMapProvider: function() {
        return jQuery( '#wpsl-map-service' ).val();
    },

    /**
     * Force map to recalculate its size.
     * Fixes grey blocks when map container becomes visible.
     * 
     * @since  3.0.0
     * @param  {string} elemId Optional element ID of the map
     * @param  {object} options Optional settings { recenter: boolean }
     * @return {void}
     */
    invalidateSize: function( elemId, options ) {
        options = options || {};
        const recenter = options.recenter !== false; // Default to true
        
        elemId = elemId || 'wpsl-' + state.mapService + '-wrap';
        
        const mapElem = mapObjects.get( elemId );
        if ( ! mapElem ) {
            return;
        }

        let center = null;
        
        if ( recenter ) {
            if ( typeof mapElem.getCenter === 'function' ) {
                center = mapElem.getCenter();
            } else if ( mapElem.getCenter ) {
                center = mapElem.getCenter();
            }
        }

        // Leaflet-based maps (OSM) have invalidateSize method
        if ( typeof mapElem.invalidateSize === 'function' ) {
            mapElem.invalidateSize();
        } else if ( typeof mapElem.resize === 'function' ) {
            mapElem.resize();
        } else if ( typeof google !== 'undefined' && google.maps && google.maps.event ) {
            google.maps.event.trigger( mapElem, 'resize' );
        }

        if ( recenter && center ) {

            // Leaflet/OSM or Mapbox / Google Maps
            if ( typeof mapElem.setView === 'function' ) {
                mapElem.setView( center );
            } else if ( typeof mapElem.setCenter === 'function' ) {
                mapElem.setCenter( center );
            }
        }
    },

    /**
     * Mapbox-specific map helpers
     */
    mapbox: {
        /**
         * Bind map specific listeners.
         *
         * @since   3.0.0
         * @see     https://docs.mapbox.com/mapbox-gl-js/api/events/#evented#on
         * @param   {object} map The Mapbox map instance
         * @returns {void}
         */
        bindMapListeners: function( map ) {
            import( /* webpackChunkName: "errors" */ './wpsl-errors.js' ).then( ( { errors } ) => {

                map.on( 'error', ( message ) => {
                    
                    // Check if the error message is related to a Mapbox style that failed to load.
                    if ( message.error.status === 404 && ( message.error.url.indexOf( '/styles/' ) !== -1 ) ) {
                        const $this = jQuery( '#wpsl-mapbox-styles input[type="radio"]:checked' );
                        const $span = $this.parent( 'p' ).find( '.wpsl-info' );

                        $span.addClass( 'wpsl-warning' );
                        $this.parents( 'p' ).find( 'span' ).addClass( 'wpsl-warning' );
                        $this.parents( 'p' ).find( 'label' ).first().addClass( 'wpsl-warning' );

                        errors.applyMapErrorStyle( $this, $span, 'mapboxStyleLoadError' );
                    }
                });
            });
        },

        /**
         * Return the access token that we need
         * to make a request to the Mapbox API.
         *
         * @since  3.0.0
         * @return {string} accessToken The Mapbox access token.
         */
        getAccessToken: function() {
            let accessToken;

            if ( jQuery( '#wpsl-api-mapbox-key' ).val() ) {
                accessToken = jQuery( '#wpsl-api-mapbox-key' ).val().trim();
            } else {
                accessToken = wpslSettings.api.key;
            }

            return accessToken;
        },

        /**
         * Return the text value from the context
         * data included in the API response.
         *
         * @since  3.0.0
         * @see    https://docs.mapbox.com/api/search/geocoding/#geocoding-response-object
         * @param  {object} response API response data
         * @param  {string} targetId The field to get the value from ( country, region, place, locality, postcode, neighborhood )
         * @return {object} value    The text / short_code value from the context field
         */
        findContextvalue: function( response, targetId ) {
            const context = response.features[0].properties.context;

            let value = {};

            if ( context && context[targetId] ) {
                const contextItem = context[targetId];
                if ( contextItem.name ) {
                    value.text = contextItem.name;
                }
                
                if ( contextItem.country_code ) {
                    value.short_code = contextItem.country_code;
                } else if ( contextItem.region_code ) {
                    value.short_code = contextItem.region_code;
                }
            }

            return value;
        },
    },
};

/**
 * Mapbox utility helpers
 */
export const mapbox = {
    /**
     * Check if a Mapbox style URL is a default style or a custom one
     * 
     * @since  3.0.0
     * @param  {string}  styleUrl The Mapbox style URL to check
     * @return {boolean} True if it's a default style, false if it's custom
     */
    isDefaultStyle: function( styleUrl ) {
        return styleUrl.includes( 'mapbox://styles/mapbox/' );
    },

    /**
     * Check if a Mapbox style URL is a custom style
     * 
     * @since  3.0.0
     * @param  {string}  styleUrl The Mapbox style URL to check
     * @return {boolean} True if it's a custom style, false if it's a default style
     */
    isCustomStyle: function( styleUrl ) {
        return ! this.isDefaultStyle( styleUrl );
    }
};