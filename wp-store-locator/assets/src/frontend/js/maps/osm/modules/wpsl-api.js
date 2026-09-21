import { createApiRequest } from '../../../../../common/wpsl-core.js';
import { slData, config } from '../../../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { responseHandlers } from '../../../modules/wpsl-response-handlers.js';
import { preloader } from '../../../modules/wpsl-preloader.js';
import { geocodeCache } from './wpsl-geocode-cache.js';
import { markers } from './wpsl-markers.js';

/**
 * OpenStreetMap API handling (geocoding and directions)
 * 
 * @since 3.0.0
 */
export const api = {
    /**
     * Geocoding API methods.
     * 
     * @since 3.0.0
     */
    geocoding: {
        /**
         * Allowed query types for geocoding.
         * 
         * @since 3.0.0
         * @type {Array<string>}
         */
        allowedQueryTypes: ['street', 'city', 'county', 'state', 'country', 'postcode'],

        /**
         * Try to get the latlng from the Nominatim geocode cache
         * before making a new geocode request.
         * 
         * @since 3.0.0
         * @return {void}
         */
        getLatLng: function() {
            const self = this;
            const query = jQuery( '#wpsl-search-input' ).val().trim();

            // 'postcode' tells the server to use Nominatim's structured
            // postalcode= query instead of the free-form q= one.
            const type = ( config.api.types === 'postcode' ) ? 'postcode' : '';

            preloader.add();

            // A newer search may start before Nominatim answers, see search.current().
            const stillCurrent = search.current( () => true );

            geocodeCache.getResponse( query, type, function( response ) {
                if ( ! stillCurrent() ) {
                    return;
                }

                if ( response !== null && ! jQuery.isEmptyObject( response ) ) {
                    if ( response.success === false && response.userNotice ) {
                        preloader.remove();

                        const errorResponse = {
                            userNotice: wpslLabels[response.userNotice] || response.userNotice
                        };

                        responseHandlers.maybeShowResponseText( errorResponse );
                    } else if ( response[0] ) {
                        self.processResponse( response );
                    } else {
                        preloader.remove();

                        const noResultsResponse = {
                            userNotice: wpslLabels.noResults
                        };

                        responseHandlers.maybeShowResponseText( noResultsResponse );
                    }
                } else {
                    preloader.remove();

                    const noResultsResponse = {
                        userNotice: wpslLabels.noResults
                    };

                    responseHandlers.maybeShowResponseText( noResultsResponse );
                }
            });
        },

        /**
         * Process the returned geocode data from the Nominatim Geocode API.
         *
         * @since 3.0.0
         * @param {object} response Geocode data from Nominatim
         */
        processResponse: function( response ) {
            const data = response[0];

            let args = {
                lat: data.lat,
                lng: data.lon
            };

            slData.skipReverseGeocode = true;

            args = helpers.search.maybeGetFullSearchData( args, data );
            args = helpers.search.processGeocodeResponse( args, response );

            search.prepare( args );
        },

        /**
         * Reverse geocode the passed coordinates and set the
         * returned zipcode / address in the input field.
         *
         * @since	3.0.0
         * @param	{object} args The coordinates and optionally whether it's from a draggable marker or not
         * @param	{Function} callback Callback function
         * @return {void}
         */
        reverse: function( args, callback ) {
            let lat, lng;

            if ( typeof args.latLng === 'object' && typeof args.latLng.lat === 'function' ) {
                lat = args.latLng.lat();
                lng = args.latLng.lng();
            } else {
                lat = args.lat;
                lng = args.lng;
            }

            const ajaxData = {
                format: 'json',
                lat: lat,
                lon: lng,
                addressdetails: '1'
            };

            createApiRequest.osm.geocode( wp.hooks.applyFilters( 'wpslGeocodeParam', ajaxData ), function( response ) {
                if ( typeof response.lat !== 'undefined' && response.lat && typeof response.lon !== 'undefined' && response.lon ) {
                    args = responseHandlers.reverseGeocodeFinished( args, response );

                    callback( args );
                } else {
                    // Nominatim answers coordinates it has no address for with
                    // { error: "Unable to geocode" }, which is not a failure the
                    // search needs to care about.
                    responseHandlers.reverseGeocodeFailed( args, response, callback );
                }
            });
        },

        /**
         * Filter out different data from the API response.
         *
         * @since	3.0.0
         * @param	{object} response The Nominatim API response
         * @param	{string} format See which data to grab from the response ( zip, country, full address etc )
         * @return {object} userLocation The filtered API data
         */
        filterResponse: function( response, format ) {
            const fieldList = ['suburb', 'town', 'city', 'village'];

            let skipCountryStateSearch,
                userLocation = {};

            if ( typeof response[0] === 'object' ) {
                response = response[0];
            }

            if ( ! format ) {
                if ( response.type == 'administrative' && response.category == 'boundary' ) {
                    jQuery.each( fieldList, function( index ) {
                        if ( typeof response.address[fieldList[index]] !== 'undefined' ) {
                            skipCountryStateSearch = true;

                            return false;
                        }
                    });

                    if ( typeof skipCountryStateSearch === 'undefined' ) {
                        if ( typeof response.address.state !== 'undefined' ) {
                            userLocation.fullSearch = {
                                type: 'state'
                            };

                            userLocation.fullSearch.query = jQuery( '#wpsl-search-input' ).val();
                        }

                        if ( typeof response.address.state === 'undefined' ) {
                            userLocation.fullSearch = {
                                type: 'country'
                            };

                            userLocation.fullSearch.query = jQuery( '#wpsl-search-input' ).val() + ',' + response.address.country_code;
                        }
                    }
                }
            } else {
                switch ( format ) {
                    case 'country':
                        userLocation.countryCode = response.address.country_code;
                        userLocation.country     = response.address.country;
                        break;
                    case 'formatted_address':
                        const locationNameFields = [ 'city', 'village', 'town' ];

                        const fieldsToFilter = {
                            'road': '',
                            'house_number': '',
                            'postcode': '',
                            'location': '',
                            'country': ''
                        };

                        const keys = Object.keys( response.address );

                        for ( const key of keys ) {
                            if ( ! fieldsToFilter.location && locationNameFields.includes( key ) ) {
                                fieldsToFilter.location = response.address[key];
                            }

                            if ( fieldsToFilter.hasOwnProperty( key ) ) {
                                fieldsToFilter[key] = response.address[key];
                            }
                        }

                        if ( fieldsToFilter['house_number'] ) {
                            fieldsToFilter['road'] = fieldsToFilter['road'] + ' ' + fieldsToFilter['house_number'];

                            fieldsToFilter['house_number'] = '';
                        }

                        const outputOrder = ['road', 'house_number', 'postcode', 'location', 'country'];

                        let formattedAddress = [];

                        for ( const key of outputOrder ) {
                            if ( fieldsToFilter[key] !== '' ) {
                                formattedAddress.push( fieldsToFilter[ key ] );
                            }
                        }

                        if ( formattedAddress ) {
                            formattedAddress = formattedAddress.join( ', ' );
                        }

                        userLocation.location = formattedAddress;
                        break;
                    case 'city':
                        const reversedList = fieldList.reverse();

                        jQuery.each( reversedList, function( index ) {
                            if ( typeof response.address[reversedList[index]] !== 'undefined' ) {
                                userLocation.location = response.address[reversedList[index]];

                                return false;
                            }
                        });

                        break;
                    case 'zip':
                        if ( typeof response.address.postcode !== 'undefined' ) {
                            userLocation.zip = response.address.postcode;
                        }

                        break;
                }
            }

            return wp.hooks.applyFilters( 'wpslFilterResponse', userLocation );
        },

        /**
         * Build an address string from the provided fields.
         *
         * @since   3.0.0
         * @param   {object} address Address components
         * @param   {array} targetFields Fields to include in the address string
         * @return {string} Formatted address string
         */
        buildAddressString: function( address, targetFields ) {
            const statsData = [];

            jQuery.each( targetFields, function( i ) {
                if ( typeof address[targetFields[ i ]] === 'string' ) {
                    statsData.push( address[targetFields[ i ]] );
                }
            });

            return statsData.join( ', ' );
        },

    },

    /**
     * Directions API methods.
     * 
     * @since 3.0.0
     */
    directions: {
        /**
         * Current store ID for directions.
         * 
         * @since 3.0.0
         * @type {string}
         */
        storeId: '',

        /**
         * Bounds for the directions route.
         * 
         * @since 3.0.0
         * @type {Array}
         */
        bounds: [],

        /**
         * Store ID of the focused marker before showing directions.
         * 
         * @since 3.0.0
         * @type {number|null}
         */
        focusedStoreId: null,

        /**
         * Initialize directions.
         * 
         * @since 3.0.0
         * @param {Function} callback Callback function
         * @return {void}
         */
        init: function( callback ) {
            callback();
        },

        /**
         * Show the directions on the map.
         *
         * @since   3.0.0
         * @param   {object} e The clicked element
         * @return {void}
         */
        show: function( e ) {
            const mapIndex = 0;
            const map = slData.maps[0];
            const markerLayers = markers.layer;
            const markerObjects = markers.active[mapIndex] || [];

            let originLatLng, destinationLatLng,
                directionMarkers = [];

            this.storeId = helpers.results.getClickedElemID( e );
            this.focusedStoreId = this.storeId;

            jQuery( '.wpsl-api-message' ).remove();

            map.removeLayer( markerLayers );

            slData.directions.active = true;

            // The route takes the map, so the shapes go until it is gone.
            helpers.directions.setShapesVisible( false );

            jQuery( '#wpsl-map' ).addClass( 'wpsl-directions-active' );

            markers.init();

            slData.viewport = {
                center: map.getCenter(),
                zoomLevel: map.getZoom()
            };

            jQuery.each( markerObjects, function( i ) {
                if ( markerObjects[i].options.storeId === 0 && ( typeof originLatLng === 'undefined' || originLatLng === '' ) ) {
                    originLatLng = markers.latLng[mapIndex][i];

                    directionMarkers.push( originLatLng );
                } else if ( markerObjects[i].options.storeId === api.directions.storeId ) {
                    destinationLatLng = markers.latLng[mapIndex][i];

                    directionMarkers.push( destinationLatLng );
                    markers.layer.addLayer( markerObjects[i] );
                }

                if ( originLatLng && destinationLatLng ) {
                    return false;
                }
            });

            if ( markers.active[mapIndex] && markers.active[mapIndex][0] ) {
                markers.layer.addLayer( markers.active[mapIndex][0] );
                markers.active[mapIndex][0].dragging.disable();
            }

            markers.restoreActiveMarkers();

            helpers.directions.checkRouteCoordinates( originLatLng, destinationLatLng );

            wp.hooks.doAction( 'wpslShowDirections', originLatLng, destinationLatLng );
        },

        /**
         * Calculate the route from the start to the end.
         *
         * @since   3.0.0
         * @param   {object} originLatLng      The start coordinates
         * @param   {object} destinationLatLng The end coordinates
         * @return {void}
         */
        calcRoute: function( originLatLng, destinationLatLng ) {
            const pathOptions = wp.hooks.applyFilters( 'wpslOsmPathOptions', '' );
            const ajaxData = wp.hooks.applyFilters( 'wpslOsmDirectionsApiParams', {
                action: 'osm_directions',
                start: originLatLng[1] + ',' + originLatLng[0],
                end: destinationLatLng[1] + ',' + destinationLatLng[0]
            });

            let index, directionStops = '';

            jQuery( '#wpsl-stores li .wpsl-error' ).remove();

            this.bounds = [];

            jQuery( '#wpsl-direction-details' ).show();
            jQuery( '#wpsl-stores, #wpsl-result-filters' ).hide();

            preloader.add( 'loadingDirections', '#wpsl-direction-details ul' );

            createApiRequest.osm.directions( ajaxData, function( response ) {
                preloader.remove();

                if ( typeof response.routes !== 'undefined' && response.routes ) {
                    const route = response.routes[0];

                    api.directions.bounds.push( [ response.bbox[1], response.bbox[0] ] );
                    api.directions.bounds.push( [ response.bbox[3], response.bbox[2] ] );

                    slData.maps[0].fitBounds( api.directions.bounds, { padding:[35, 35] } );

                    if ( route.geometry ) {
                        const polyline = L.Polyline.fromEncoded( route.geometry );

                        if ( ! jQuery.isEmptyObject( pathOptions ) ) {
                            polyline.setStyle( pathOptions );
                        }

                        polyline.addTo( slData.maps[0] );
                    }

                    if ( route.segments && route.segments[0].steps.length > 0 ) {
                        jQuery.each( route.segments[0].steps, function( i ) {
                            index = i+1;
                            directionStops = directionStops + "<li><div class='wpsl-direction-index'>" + index + ".</div><div class='wpsl-direction-txt'>" + sharedHelpers.escapeHtml( route.segments[0].steps[i].instruction ) + "</div><div class='wpsl-direction-distance'>" + helpers.formatDirectionsDistance( route.segments[0].steps[i].distance.toFixed( 2 ), 'km' ) + "</div></li>";
                        });

                        const totalDistance = helpers.formatDirectionsDistance( route.segments[0].distance.toFixed( 2 ), 'km' );
                        const totalDuration = helpers.formatDirectionsDuration( route.segments[0].duration );
                        
                        jQuery( '#wpsl-direction-details ul' ).empty().append( directionStops ).before( helpers.formatDirectionsHeader( totalDistance, totalDuration ) ).after( "<p class='wpsl-direction-after'>" + sharedHelpers.escapeHtml( response.metadata.attribution ) + "</p>" );
                        
                        helpers.directions.maybeAdjustViewport();
                    }

                    helpers.directions.focusBackButton();

                    helpers.directions.styling.init();
                } else {
                    if ( response.status == 404 ) {
                        response.userNotice = wpslLabels.noDirectionsFoundMessage;

                        responseHandlers.maybeShowResponseText( response, 'directions' );
                    } else {
                        const json = {
                            status: response.status,
                            responseJSON: response
                        };

                        responseHandlers.maybeShowResponseText( json, 'directions' );
                    }
                }
            })
        },

        /**
         * Handle clicks on the back button
         * when the route directions are displayed.
         *
         * @since   3.0.0
         * @return {void}
         */
        restoreResults: function() {
            const mapIndex = 0;
            const map = slData.maps[0];
            const activeMarkers = markers.active[mapIndex] || [];
            const markerSetup = {
                markers: activeMarkers,
                map: map
            };

            this.removePolyline();
            map.removeLayer( markers.layer );

            if ( config.markers.skipStart && activeMarkers[0] && activeMarkers[0].options.storeId === 0 ) {
                activeMarkers[0].remove();
            }

            helpers.map.restoreMapState();
            helpers.results.sharedRestoreSteps();

            markers.init( markerSetup );
            
            if ( this.focusedStoreId !== null ) {
                jQuery.each( activeMarkers, function( i, marker ) {
                    if ( marker.options.storeId === api.directions.focusedStoreId ) {
                        const markerElement = marker.getElement();

                        if ( markerElement ) {
                            markerElement.focus();
                        }

                        return false;
                    }
                });
                
                this.focusedStoreId = null;
            }
        },

        /**
         * Remove the directions from map.
         *
         * @since   3.0.0
         * @return {void}
         */
        removePolyline: function() {
            const map = slData.maps[0];
            jQuery.each( map._layers, function( i ) {

                /**
                 * This sweep removes every SVG-rendered vector on the map, not
                 * just the polyline it created, and markers.removeAll() calls
                 * it before every search.
                 */
                if ( map._layers[i]._wpslShape ) {
                    return;
                }

                if ( typeof map._layers[i]._path === 'object' ) {
                    map.removeLayer( map._layers[i] );
                }
            });
        }
    }
};
