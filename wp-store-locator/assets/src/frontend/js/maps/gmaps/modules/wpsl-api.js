import { createApiRequest, importedLibraries } from '../../../../../common/wpsl-core.js';
import { slData, config } from '../../../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { responseHandlers } from '../../../modules/wpsl-response-handlers.js';
import { markers } from './wpsl-markers.js';
import { buttons } from '../../../modules/wpsl-buttons.js';

/**
 * Google Maps API module (geocoding, directions, streetview, autocomplete).
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
         * Geocode the user input with the Google Geocode API
         *
         * @since   1.0.0
         * @param   {object} args
         * @returns {void}
         */
        getLatLng: function( args ) {
            const requestArgs = {};

            if ( typeof args === 'undefined' ) {
                args = {
                    autoLoad: false
                };
            } else if ( typeof args.fullSearch === 'object' ) {
                delete args.fullSearch;
            }

            // Check if we need to set the geocode component restrictions.
            if ( typeof config.api.geocodeComponents !== 'undefined' && ! jQuery.isEmptyObject( config.api.geocodeComponents ) ) {
                requestArgs.componentRestrictions = config.api.geocodeComponents;

                if ( typeof requestArgs.componentRestrictions.postalCode !== 'undefined' ) {
                    requestArgs.componentRestrictions.postalCode = jQuery( '#wpsl-search-input' ).val();
                } else {
                    requestArgs.address = jQuery( '#wpsl-search-input' ).val();
                }
            } else {
                requestArgs.address = jQuery( '#wpsl-search-input' ).val();
            }

            // Google's componentRestrictions only accepts a single country, so an
            // ambiguous postcode ( "2000" exists in several countries ) can't be
            // resolved in one request. config.api.geocodeCountries is only set by
            // the server for a zip-only search restricted to multiple countries,
            // so its presence alone triggers the fallback - we can't check for a
            // postalCode component because the new Places API ( "latest" ) geocodes
            // that value as a plain address instead.
            const fallbackCountries = (
                Array.isArray( config.api.geocodeCountries ) &&
                config.api.geocodeCountries.length > 1
            ) ? config.api.geocodeCountries.slice() : [];

            if ( fallbackCountries.length ) {
                this.geocodeWithCountryFallback( requestArgs, fallbackCountries, args );
            } else {
                this.sendGeocodeRequest( requestArgs, args );
            }
        },

        /**
         * Send a single geocode request and handle the response.
         *
         * @since 3.0.0
         * @param {object}   requestArgs The Geocoder request arguments
         * @param {object}   args        The search arguments passed along on success
         * @param {Function} [onResult]  Optional interceptor called with ( status,
         *                               response ). Return true to suppress the
         *                               default success / error handling ( used by
         *                               the multi-country fallback loop ).
         * @return {void}
         */
        sendGeocodeRequest: function( requestArgs, args, onResult ) {
            const self = this;

            // A newer search may start before Google answers, see search.current().
            const stillCurrent = search.current( () => true );

            createApiRequest.gmaps.geocode( wp.hooks.applyFilters( 'wpslGeocodeParam', requestArgs ), ( response, status ) => {
                if ( ! stillCurrent() ) {
                    return;
                }

                // See if a country filter is active, and we have the expected results.
                status = self.maybeOverwriteStatusMsg( status, response );

                // Let the caller intercept the result ( multi-country fallback ).
                if ( typeof onResult === 'function' && onResult( status, response ) ) {
                    return;
                }

                if ( status === google.maps.GeocoderStatus.OK ) {
                    self.handleGeocodeSuccess( response, args );
                } else {
                    responseHandlers.geocoding.errors( status );
                }
            });
        },

        /**
         * Handle a successful geocode response.
         *
         * @since 3.0.0
         * @param {object} response The Geocoder response
         * @param {object} args     The search arguments
         * @return {void}
         */
        handleGeocodeSuccess: function( response, args ) {
            helpers.search.maybeIncludeStatistics( response );

            // Check if the provided input is for a country / state / province.
            args = helpers.search.maybeGetFullSearchData( args, response );
            args.latLng = response[0].geometry.location;

            wp.hooks.doAction( 'wpslGeocodeFinished', args, response );

            search.prepare( args );
        },

        /**
         * Retry a zip-only geocode against each restricted country until a match
         * is found.
         *
         * Google often returns a fuzzy result ( status OK with partial_match ) for
         * a postcode that doesn't exist in the restricted country instead of
         * ZERO_RESULTS, so an exact match wins immediately and a partial match is
         * only kept in case no country yields an exact one.
         *
         * @since 3.0.0
         * @param {object} requestArgs The base Geocoder request arguments
         * @param {Array}  countries   The ISO country codes to try, in order
         * @param {object} args        The search arguments passed along on success
         * @return {void}
         */
        geocodeWithCountryFallback: function( requestArgs, countries, args ) {
            const self = this;
            let index = 0;
            let partialResponse = null;

            const tryNext = function() {
                if ( index >= countries.length ) {

                    // No exact match anywhere. Use a partial match if we found one,
                    // otherwise report no results.
                    if ( partialResponse ) {
                        self.handleGeocodeSuccess( partialResponse, args );
                    } else {
                        responseHandlers.geocoding.errors( google.maps.GeocoderStatus.ZERO_RESULTS );
                    }

                    return;
                }

                // Clone so each attempt gets its own country without mutating the base args.
                const countryArgs = jQuery.extend( true, {}, requestArgs );

                // The new Places API geocodes a plain address, so there may not be
                // a componentRestrictions object yet to attach the country to.
                if ( typeof countryArgs.componentRestrictions === 'undefined' ) {
                    countryArgs.componentRestrictions = {};
                }

                countryArgs.componentRestrictions.country = countries[ index ];
                index++;

                self.sendGeocodeRequest( countryArgs, args, function( status, response ) {

                    // Exact match for this country - let the default handler run it.
                    if ( status === google.maps.GeocoderStatus.OK && ! response[0].partial_match ) {
                        return false;
                    }

                    // Remember the first partial match, but keep looking for an exact one.
                    if ( status === google.maps.GeocoderStatus.OK && ! partialResponse ) {
                        partialResponse = response;
                    }

                    // A real error ( quota, denied, ... ) - stop and report it.
                    if ( status !== google.maps.GeocoderStatus.OK && status !== google.maps.GeocoderStatus.ZERO_RESULTS ) {
                        return false;
                    }

                    // No usable result yet - try the next country.
                    tryNext();

                    return true;
                });
            };

            tryNext();
        },

        /**
         * Reverse geocode the passed coordinates and set the
         * returned zipcode / address in the input field.
         *
         * @since   1.0.0
         * @param   {object}   args     The coordinates and optionally whether it's from a draggable marker or not
         * @param   {function} callback
         * @returns {object} response The address components if the stats add-on is active
         */
        reverse: function( args, callback ) {

            // args.latLng only exists for a forward geocode ( a normal search ).
            // The "Search this area" button and other viewport searches pass plain
            // lat / lng numbers, and without falling back to those the Geocoder
            // receives { location: undefined } and returns INVALID_REQUEST.
            const coordinates = helpers.extractCoordinates( args );

            if ( ! coordinates ) {
                console.warn( '[WPSL] Reverse geocode skipped: no valid coordinates in', args );
                responseHandlers.geocoding.errors( google.maps.GeocoderStatus.INVALID_REQUEST );

                return;
            }

            const location = { lat: coordinates.lat, lng: coordinates.lng };
            createApiRequest.gmaps.geocode( wp.hooks.applyFilters( 'wpslReverseGeocodeParam', { 'location': location } ), function( response, status ) {
                if ( status === google.maps.GeocoderStatus.OK ) {
                    args = responseHandlers.reverseGeocodeFinished( args, response );

                    callback( args );
                } else {
                    responseHandlers.reverseGeocodeFailed( args, status, callback );
                }
            });
        },

        /**
         * Filter out different data from the API response.
         *
         * @since 3.0.0
         * @param   {object} response     The complete Google API response
         * @param   {string} format       See which data to grab from the response ( zip, country, full address etc )
         * @returns {object} userLocation The filtered API data
         */
        filterResponse: function( response, format ) {
            const userInput = jQuery( '#wpsl-search-input' ).val();

            let responseType, longName,
                userLocation = {},
                filteredData = {};

            // The format is only empty for a normal user triggered search. The zip /
            // formatted_address format ( set on the settings page ) is used when the
            // users location is automatically determined, and the country code is
            // used internally to keep results from nearby countries out.
            if ( ! format ) {

                // Check if the search was for a country name
                if ( response[0].types.join( ',' ) === 'country,political' ) {
                    userLocation.fullSearch = {
                        type: 'country'
                    };
                }

                // Check if the search was for a state / province name
                if ( response[0].types.join( ',' ) === 'administrative_area_level_1,political' ) {
                    userLocation.fullSearch = {
                        type: 'state'
                    };
                }

                if ( typeof userLocation.fullSearch === 'object' ) {
                    longName = response[0].address_components[0].long_name; // Full country / state name returned by the geocode API.

                    // A state search uses the raw user input instead of the Geocode API
                    // response, because the map language and the wpsl_state meta in the
                    // database can hold different languages. Country searches can fall
                    // back on the stored ISO code, but states have no such field.
                    if ( userLocation.fullSearch.type === 'state' ) {

                        // Keep the API long name when the input already matches the short
                        // name, so the wpsl_state query always holds both a long
                        // ( California ) and a short ( CA ) name.
                        if ( userInput.toUpperCase() !== response[0].address_components[0].short_name.toUpperCase() ) {
                            longName = userInput;
                        }
                    }

                    userLocation.fullSearch.query = longName + ',' + response[0].address_components[0].short_name;
                }
            } else {
                switch ( format ) {
                    case 'country':
                        jQuery.each( response[0].address_components, function( index ) {
                            responseType = response[0].address_components[index].types;

                            if ( responseType.join( ',' ) === 'country,political' ) {
                                userLocation.countryCode = response[0].address_components[index].short_name;
                                userLocation.country     = response[0].address_components[index].long_name;

                                return false;
                            }
                        });
                        break;
                    case 'formatted_address':
                        userLocation.location = response[0].formatted_address;
                        break;
                    case 'city':
                        jQuery.each( response, function( index ) {
                            responseType = response[index].types;
                            if ( responseType.join( ',' ) === 'locality,political' ) {
                                userLocation.locality = response[index].long_name;

                                return false;
                            }
                        });
                        break;
                    case 'zip':
                        jQuery.each( response, function( i ) {
                            jQuery.each( response[i].address_components, function( j ) {
                                responseType = response[i].address_components[j].types;
                                if ( responseType === 'postal_code' || responseType.join( ',' ) === 'postal_code,postal_code_prefix' ) {
                                    filteredData.zip = response[i].address_components[j].long_name;

                                    return false;
                                }

                                if ( responseType.join( ',' ) === 'locality,political' ) {
                                    filteredData.locality = response[i].address_components[j].long_name;
                                }
                            });

                            if ( typeof filteredData.zip !== 'undefined' ) {
                                return false;
                            }
                        });

                        // If no zip code was found ( it's rare, but it happens ),
                        // then we use the city / town name as backup.
                        if ( typeof filteredData.zip === 'undefined' && typeof filteredData.locality !== 'undefined' ) {
                            userLocation.location = filteredData.locality;
                        } else {
                            userLocation.location = filteredData.zip;
                        }

                        break;
                }
            }

            return wp.hooks.applyFilters( 'wpslFilterResponse', userLocation, response );
        },
        
        /**
         * Handle the geocode errors.
         *
         * @since   1.0.0
         * @param   {string} status Contains the error code
         * @returns {void}
         */
        errors: function( status ) {
            let response = {};

            switch ( status ) {
                case 'ZERO_RESULTS':
                    response.userNotice = wpslLabels.noResults;
                    break;
                case 'OVER_QUERY_LIMIT':
                    response.userNotice = wpslLabels.queryLimit;
                    break;
                default:
                    response.userNotice = wpslLabels.generalError;
                    break;
            }

            responseHandlers.maybeShowResponseText( response );
        },
        
        /**
         * See if we need to overwrite the returned status message when
         * the country filter is used in combination with user input.
         *
         * Where the OpenStreetMaps API returns 'no results found' for input it
         * can't match, the Google Geocode API falls back to the center coordinates
         * of the country. In that case we set the status to 'ZERO_RESULTS'.
         *
         * @since  3.0.0
         * @param  {string} status Current API status
         * @param  {object} response The API response
         * @return {string} Adjusted API status
         */
        maybeOverwriteStatusMsg: function( status, response ) {
            // A restriction is active when either the country filter dropdown is
            // present, or the geocode component restriction ( Region + "Restrict
            // the geocoding results" option ) is set.
            const hasCountryDropdown   = jQuery( '#wpsl-country-dropdown' ).length;
            const hasRegionRestriction = typeof config.api.geocodeComponents === 'object' && config.api.geocodeComponents !== null && typeof config.api.geocodeComponents.country !== 'undefined';

            if ( ( hasCountryDropdown || hasRegionRestriction ) && jQuery( '#wpsl-search-input' ).val() && ! jQuery.isEmptyObject( response ) && response.length === 1 && response[0].types.join( ',' ) === 'country,political' ) {
                status = 'ZERO_RESULTS';
            }

            return status;
        }
    },
    
    /**
     * Directions API methods.
     * 
     * @since 3.0.0
     */
    directions: {
        storeId: '',
        mapState: '',
        
        /**
         * Initialize the directions service.
         *
         * @since 3.0.0
         * @param {Function} callback function to execute after initialization
         * @return {void}
         */
        init: function( callback ) {
            createApiRequest.gmaps.importLibrary( 'routes' ).then( () => {
                callback();
            });
        },

        /**
         * Show the driving directions.
         *
         * @since 3.0.0
         * @param {object} e The clicked element
         * @return {void}
         */
        show: function( e ) {
            const activeMarkers = slData.provider.markers.active[0] || [];
            const len = activeMarkers.length;

            let originLatLng, destinationLatLng;

            // Close street view if it's open, so the directions render on the map.
            api.streetView.close();

            this.storeId = helpers.results.getClickedElemID( e );

            // Used to restore the map back to the state it
            // was in before the user clicked on 'directions'.
            this.mapState = {
                centerLatlng: slData.maps[0].getCenter(),
                zoomLevel: slData.maps[0].getZoom()
            };

            // Find the latlng that belongs to the start and end point.
            for ( let i = 0; i < len; i++ ) {
                const marker = activeMarkers[i];
                if ( marker.storeId === 0 ) {
                    originLatLng = marker.position;
                } else if ( marker.storeId === this.storeId ) {
                    destinationLatLng = marker.position;
                }
            
                if ( originLatLng && destinationLatLng ) break;
            }

            // Calculate the route, or show an error if a coordinate is missing.
            helpers.directions.checkRouteCoordinates( originLatLng, destinationLatLng );

            // Bind the back button and the search result hover actions.
            markers.checkMouseOverEvent();

            // Make sure no marker is set to active.
            markers.restoreActiveMarkers();

            wp.hooks.doAction( 'wpslShowDirections', originLatLng, destinationLatLng );
        },

        /**
         * Calculate the route from the start to the end.
         *
         * @since   1.0.0
         * @see     https://developers.google.com/maps/documentation/javascript/directions
         * @param   {object} originLatLng      The latlng from the start point
         * @param   {object} destinationLatLng The latlng from the end point
         * @returns {void}
         */
        calcRoute: function( originLatLng, destinationLatLng ) {
            const self = this;
            const distanceUnit = config.search.distanceUnit === 'km' ? 'METRIC' : 'IMPERIAL';
            const args = wp.hooks.applyFilters( 'wpslDirectionsApiArgs', {
                origin: originLatLng,
                destination: destinationLatLng,
                travelMode: config.search.directionsTravelMode.toUpperCase(),
                unitSystem: google.maps.UnitSystem[ distanceUnit ]
            });

            createApiRequest.gmaps.directions( args, function( routes, error ) {
                if ( error ) {
                    self.errors( error );
                    return;
                }

                slData.directions.active = true;

                // The route is drawn on the map, so the shapes go until it is gone.
                helpers.directions.setShapesVisible( false );

                const polylines = routes[0].createPolylines();
                polylines.forEach( ( polyline ) => polyline.setMap( slData.maps[0] ) );
                slData.directionsPolylines = polylines;

                // Fit map bounds to the polyline paths
                if ( polylines.length > 0 ) {
                    const bounds = new google.maps.LatLngBounds();

                    polylines.forEach( ( polyline ) => {
                        const path = polyline.getPath();
                        path.forEach( ( latLng ) => bounds.extend( latLng ) );
                    });

                    slData.maps[0].fitBounds( bounds );
                }

                jQuery( '#wpsl-map' ).addClass( 'wpsl-directions-active' );

                if ( routes.length > 0 ) {
                    const route = routes[0];
                    const stopDetails = {
                        startAddress: route.legs[0].startAddress,
                        endAddress: route.legs[0].endAddress
                    };

                    let directionStops = '';

                    jQuery.each( route.legs, function( i ) {
                        const leg = route.legs[i];
                        jQuery.each( leg.steps, function( j ) {
                            const step = leg.steps[j];
                            const index = j + 1;
                            const distance = step.localizedValues?.distance ?? '';
                            directionStops += '<li><div class="wpsl-direction-index">' + index + '.</div><div class="wpsl-direction-txt">' + sharedHelpers.escapeHtml( step.instructions ) + '</div><div class="wpsl-direction-distance">' + distance + '</div></li>';
                        });
                    });

                    jQuery( '#wpsl-stores, #wpsl-result-filters' ).hide();
                    const totalDistance = route.legs[0].localizedValues?.distance ?? '';
                    const totalDuration = route.legs[0].localizedValues?.duration ?? '';

                    jQuery( '#wpsl-direction-details ul' )
                        .append( directionStops )
                        .before( helpers.formatDirectionsHeader( totalDistance, totalDuration ) )
                        .after( '<p class="wpsl-direction-after">' + sharedHelpers.escapeHtml( route.copyrights ?? '' ) + '</p>' );
                    jQuery( '#wpsl-direction-details' ).show();

                    const activeMarkers = slData.provider.markers.active[0] || [];
                    for ( let i = 0, len = activeMarkers.length; i < len; i++ ) {
                        activeMarkers[i].setMap( null );
                    }

                    markers.createDirectionStops( originLatLng, destinationLatLng, stopDetails );

                    if ( slData.provider.markers.cluster && slData.provider.markers.cluster[0] ) {
                        slData.provider.markers.cluster[0].clearMarkers();
                    }

                    helpers.directions.maybeAdjustViewport();
                }

                helpers.directions.focusBackButton();
                helpers.directions.styling.init();
            });
        },

        /**
         * Remove anything related to the shown routes
         * and restore the map back to how it was after
         * the search was completed.
         *
         * @since 3.0.0
         * @return {void}
         */
        restoreResults: function() {
            const mapsState = this.mapState;

            let i, len;

            // Remove the directions polylines from the map
            if ( slData.directionsPolylines ) {
                slData.directionsPolylines.forEach( ( polyline ) => polyline.setMap( null ) );
                slData.directionsPolylines = [];
            }

            // Remove the origin / destination markers from map.
            for ( i = 0, len = slData.provider.markers.directionStops.length; i < len; i++ ) {
                slData.provider.markers.directionStops[i].setMap( null );
            }

            slData.provider.markers.directionStops = [];

            // Restore the store markers on the map
            const mapIndex = 0;
            const activeMarkers = slData.provider.markers.active[mapIndex] || [];
            for ( i = 0, len = activeMarkers.length; i < len; i++ ) {
                activeMarkers[i].setMap( slData.maps[mapIndex] );
            }

            // If marker clusters are enabled, restore them
            if ( slData.provider.markers.cluster && Object.keys( slData.provider.markers.cluster ).length > 0 ) {
                markers.createCluster( slData.maps[mapIndex], mapIndex );
            }

            // Restore the center and zoom level
            slData.maps[0].setCenter( mapsState.centerLatlng );
            slData.maps[0].setZoom( mapsState.zoomLevel );

            helpers.results.sharedRestoreSteps();
        },

        /**
         * Handle the errors from the directions API.
         *
         * @since   1.2.20
         * @param   {Error|string} error The error object or message
         * @returns {void}
         */
        errors: function ( error ) {
            const msg = ( error && error.message ) ? error.message : String( error );

            let response = {};

            if ( error && error.isRoutesApiDisabled ) {
                response.isRoutesApiDisabled = true;
                response.apiUrl = error.apiUrl;
                response.userNotice = wpslLabels.routesApiDisabled;
                response.enabledLinkLabel = wpslLabels.routesApiEnabledLink;
            } else if ( msg.includes( 'NOT_FOUND' ) || msg.includes( 'ZERO_RESULTS' ) ) {
                response.userNotice = wpslLabels.noDirectionsFound;
            } else if ( msg.includes( 'OVER_QUERY_LIMIT' ) || msg.includes( 'RESOURCE_EXHAUSTED' ) ) {
                response.userNotice = wpslLabels.queryLimit;
            } else {
                response.userNotice = wpslLabels.generalError + '.';
            }

            responseHandlers.maybeShowResponseText( response, 'directions' );
        }
    },

    /**
     * Street View API methods.
     * 
     * @since 3.0.0
     */
    streetView: {
        /**
         * Activate streetview for the clicked location.
         *
         * @since   3.0.0
         * @param   {object} marker     The current marker
         * @param   {object} currentMap The map object
         * @return {void}
         */
        activate: function( marker, currentMap ) {
            const panorama = currentMap.getStreetView();
            panorama.setPosition( marker.position );
            panorama.setVisible( true );

            slData.streetView.active = true;

            jQuery( '#wpsl-map-controls' ).hide();

            this.addListener( panorama, currentMap );

            wp.hooks.doAction( 'wpslStreetViewActive', currentMap );
        },

        /**
         * Close street view if it's currently open.
         *
         * Setting the panorama to hidden triggers the 'visible_changed'
         * listener, which restores the map controls.
         *
         * @since  3.0.0
         * @return {void}
         */
        close: function() {
            if ( ! slData.streetView.active || ! slData.maps || ! slData.maps[0] ) {
                return;
            }

            const panorama = slData.maps[0].getStreetView();
            if ( panorama && panorama.getVisible() ) {
                panorama.setVisible( false );
            }
        },

        /**
         * Listen for changes in the streetview visibility.
         *
         * Sometimes the infowindow offset is incorrect after switching
         * back from streetview. We fix this by zooming in and out.
         *
         * @since   3.0.0
         * @param   {object} panorama   The streetview object
         * @param   {object} currentMap The map object
         * @return {void}
         */
        addListener: function( panorama, currentMap ) {
            google.maps.event.addListener( panorama, 'visible_changed', function() {
                if ( ! panorama.getVisible() ) {
                    slData.streetView.active = false;

                    const currentZoomLevel = currentMap.getZoom();

                    jQuery( '#wpsl-map-controls' ).show();

                    currentMap.setZoom( currentZoomLevel-1 );
                    currentMap.setZoom( currentZoomLevel );
                }
            });
        },

        /**
         * Check the streetview status.
         *
         * Make sure that a streetview exists for
         * the latlng for the open info window.
         *
         * @since   3.0.0
         * @param   {object}   latLng The latlng coordinates
         * @param   {Function} callback Callback function to execute
         * @return {void}
         */
        checkStatus: function( latLng, callback ) {
            const streetViewService = new importedLibraries.streetView.StreetViewService();
            streetViewService.getPanoramaByLocation( latLng, 50, function( result, status ) {
                config.map.streetViewAvailable = ( status === google.maps.StreetViewStatus.OK ) ? true : false;

                callback();
            });
        }
    },

    /**
     * Handle the autocomplete suggestions.
     * 
     * @since 3.0.0
     */
    autoComplete: {
        importedService: {},
        self: null,

        /**
         * Activate the autocomplete for the store search.
         *
         * @since 2.2.0
         * @return {void}
         */
        init: function() {
            this.self = this;

            if ( config.api.autoComplete.version === 'legacy' ) {
                this.makeLegacyRequest();
            } else {
                
                // Add ESC key handler
                jQuery( document ).on( 'keydown.wpslAutocomplete', function( e ) {
                    if ( e.key === 'Escape' ) {
                        jQuery( '.wpsl-autocomplete-search-results' ).hide();
                    }
                });

                helpers.setupAutocompleteClickOutside( '.wpsl-autocomplete-search-container', '.wpsl-autocomplete-search-results' );

                const autoCompleteOptions = config.api.autoComplete.options;
                for ( const key in autoCompleteOptions ) {
                    if ( autoCompleteOptions.hasOwnProperty( key ) ) {
                        const value = autoCompleteOptions[ key ];

                        // Check if the value is not empty
                        if ( value !== '' && value !== null && value !== undefined ) {
                            this.current.request[ key ] = value;
                        }
                    }
                }

                this.current.request = wp.hooks.applyFilters( 'wpslFilterAutocompleteRequest', this.current.request );
                this.current.refreshToken();

                /*
                 * Debounce the Places request the same way the Stadia module does:
                 * every fetchAutocompleteSuggestions() call is billed, so wait for
                 * a pause in typing instead of firing on each keystroke. An emptied
                 * field still clears the suggestion list right away.
                 */
                jQuery( '#wpsl-search-input' ).on( 'input', function( e ) {
                    const autocomplete = api.autoComplete.current;

                    if ( autocomplete.debounceTimer ) {
                        clearTimeout( autocomplete.debounceTimer );
                    }

                    if ( e.target.value.trim() === '' ) {
                        autocomplete.makeRequest( e );
                        return;
                    }

                    autocomplete.debounceTimer = setTimeout( function() {
                        autocomplete.makeRequest( e );
                    }, 300 );
                });
            }
        },

        /**
         * Use the new places API
         *
         * @since 2.2.250
         * @see   https://developers.google.com/maps/documentation/javascript/places-js
         */
        current: {
            /**
             * Hold the different options for the autocomplete suggestions
             *
             * @see https://developers.google.com/maps/documentation/javascript/reference/autocomplete-data#AutocompleteRequest.input
             */
            request: {
                input: '',
            },

            newestRequestId: 0,
            debounceTimer: null,
            /**
             * Make a request to the new Places API for matching
             * location names based on the provided user input.
             *
             * @param  {object} inputEvent
             * @return {Promise<void>}
             */
            makeRequest: async function( inputEvent ) {
                const query = inputEvent.target.value.trim();
                const autocomplete = api.autoComplete.current;

                try {
                    // Reset elements and exit if an empty string is received.
                    if ( query == '' ) {
                        jQuery( '.wpsl-autocomplete-search-results' ).find( 'ul' ).empty().end().hide();

                        return;
                    }

                    // Add the latest char sequence to the request.
                    autocomplete.request.input = query;

                    // To avoid race conditions, store the request ID and compare after the request.
                    const requestId = ++autocomplete.newestRequestId;
                    
                    let suggestions;
                    
                    try {
                        const result = await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions( autocomplete.request );
                        suggestions = result.suggestions;
                    } catch ( error ) {
                        
                        // Only show error message to logged-in users
                        if ( Number( config.userLoggedin ) ) {
                            if ( ! createApiRequest.gmaps.consoleNoticeVisible ) {
                                createApiRequest.gmaps.consoleNoticeVisible = true;
                                
                                // Extract the actual error message from the exception.
                                let errorText = '';
                                if ( error && error.message ) {
                                    const rpcErrorMatch = error.message.match( /RpcError:\s*(.+)/ );
                                    errorText = rpcErrorMatch ? rpcErrorMatch[1] : error.message;
                                } else {
                                    errorText = wpslLabels.placesApiFallbackError;
                                }

                                if ( jQuery( '#wpsl-wrap' ).length ) {
                                    jQuery( '#wpsl-wrap' ).addClass( 'wpsl-api-message-wrap' );

                                    // Build the paragraph using safe DOM nodes so that URLs
                                    // become clickable links without any risk of HTML injection.
                                    const $paragraph = jQuery( '<p></p>' );
                                    const parts = errorText.split( /(https:\/\/[^\s<>"]+)/g );

                                    parts.forEach( part => {
                                        if ( /^https:\/\//.test( part ) ) {
                                            const link = document.createElement( 'a' );
                                            link.href        = part;
                                            link.target      = '_blank';
                                            link.rel         = 'noopener noreferrer';
                                            link.textContent = part;
                                            $paragraph[0].appendChild( link );
                                        } else if ( part ) {
                                            $paragraph[0].appendChild( document.createTextNode( part ) );
                                        }
                                    } );

                                    jQuery( '.wpsl-search' ).empty().append( $paragraph );
                                }
                            }
                        }
                        
                        return;
                    }

                    // If the request has been superseded by a newer request, do not render the output.
                    if ( requestId !== autocomplete.newestRequestId )
                        return;

                    // Add the HTML required to render the returned autocomplete results.
                    if ( ! jQuery( '.wpsl-autocomplete-search-container' ).length ) {
                        jQuery( '#wpsl-search-input' ).wrap( '<div class="wpsl-autocomplete-search-container"></div>' );

                        const resultsListHtml = '<div class="wpsl-autocomplete-search-results">\n' +
                            '            <ul>\n' +
                            '            </ul>\n' +
                            '        </div>';

                        jQuery( '#wpsl-search-input' ).after( resultsListHtml );
                        jQuery( '#wpsl-search-input' ).trigger( 'focus' );
                    }

                    // Show the results container if we have results
                    if ( suggestions.length > 0 ) {
                        jQuery( '.wpsl-autocomplete-search-results' ).show();
                        
                        const $searchWrap = jQuery( '#wpsl-search-input' ).closest( '.wpsl-search-wrap' );

                        if ( $searchWrap.length ) {
                            const submitButtonWidth = jQuery( '#wpsl-submit-wrapper' ).outerWidth() || 0;
                            const availableWidth = $searchWrap.outerWidth() - submitButtonWidth;

                            jQuery( '.wpsl-autocomplete-search-container' ).outerWidth( availableWidth );
                            jQuery( '.wpsl-autocomplete-search-results' ).outerWidth( availableWidth );
                        }

                        // The input is now wrapped in an autocomplete container,
                        // so re-match the field and category dropdown widths.
                        helpers.template.alignSearchColumns();
                    } else {
                        jQuery( '.wpsl-autocomplete-search-results' ).hide();
                    }

                    // Clear the list first.
                    jQuery( '.wpsl-autocomplete-search-results ul' ).empty();

                    // Loop over the returned results.
                    for ( const suggestion of suggestions ) {
                        const placePrediction = suggestion.placePrediction;
                        const item = placePrediction.text.toString();
                        const lowerCaseQuery = query.toLowerCase().replace(/[^\p{L}0-9\s'-,]/gu, '');

                        const $a = jQuery( '<a>' );
                        const $li = jQuery( '<li>' ).attr( 'role', 'option' ).attr( 'tabindex', '0' );

                        // Add keyboard support for the list item itself
                        $li.on( 'keydown', ( e ) => {
                            if ( e.key === 'Enter' || e.key === ' ' ) {
                                e.preventDefault();
                                autocomplete.onPlaceSelected( placePrediction.toPlace() );
                            }
                        });

                        // Add the click event to the <li> element
                        $li.on( 'click', () => {
                            autocomplete.onPlaceSelected( placePrediction.toPlace() );
                        });

                        const matchIndex = item.toLowerCase().indexOf( lowerCaseQuery );

                        // Highlight the part that matches the user input. Use DOM nodes
                        // rather than .html() so place-name text ( which may contain &,
                        // <, > ) is never reinterpreted as HTML.
                        const beforeMatch = item.slice( 0, matchIndex );
                        const match       = item.slice( matchIndex, matchIndex + lowerCaseQuery.length );
                        const afterMatch  = item.slice( matchIndex + lowerCaseQuery.length );

                        const aEl = $a[0];
                        aEl.appendChild( document.createTextNode( beforeMatch ) );

                        const highlight = document.createElement( 'span' );
                        highlight.className   = 'wpsl-autocomplete-highlight';
                        highlight.textContent = match;
                        aEl.appendChild( highlight );

                        aEl.appendChild( document.createTextNode( afterMatch ) );

                        $li.append( $a );

                        jQuery( '.wpsl-autocomplete-search-results ul' ).append( $li );
                    }
                } catch ( error ) {
                    jQuery( '#wpsl-search-input' ).off( 'input' );
                }
            },

            /**
             * When the user clicks on an autocomplete result,
             * we get the location details ( coordinates )
             * and formatted address which is show in the
             * input field.
             *
             * @param  {object} place
             * @return {Promise<void>}
             */
            onPlaceSelected: async function( place ) {
                const autocomplete = api.autoComplete.current;

                helpers.panel.maybeCloseFilters();

                await place.fetchFields({
                    fields: ['formattedAddress', 'location'],
                });

                slData.autoCompleteLatLng = place.location;

                jQuery( '#wpsl-search-input' ).val( place.formattedAddress );
                jQuery( '.wpsl-autocomplete-search-results' ).hide();

                autocomplete.refreshToken();

                if ( config.search.autoSubmitAutoComplete ) {
                    jQuery( '#wpsl-search-btn' ).trigger( 'click' );
                }
            },

            /**
             * Refresh the token used in the autocomplete request.
             *
             * @since 3.0.0
             * @return {void}
             * @see https://developers.google.com/maps/documentation/javascript/reference/autocomplete-data#AutocompleteSessionToken
             */
            refreshToken: function() {
                this.request.sessionToken = new google.maps.places.AutocompleteSessionToken();
            },
        },

        /**
         * Keep support the legacy places API for
         * API keys created before March 1st, 2025
         *
         * @since 3.0.0
         * @return {void}
         * @see https://developers.google.com/maps/documentation/javascript/places
         */
        makeLegacyRequest: function() { 
            // Make sure the Places library is loaded before initializing autocomplete
            createApiRequest.maybeLoadLibraries( [ 'places' ], () => {
                const input = document.getElementById( 'wpsl-search-input' );
                const customRestrictions = config.api.autoComplete.options;

                let options = {};

                // Handle keyboard submits. Without this the search breaks the second
                // time the user searches, selects the item with the keyboard and
                // submits it with the enter key.
                jQuery( '#wpsl-search-input' ).on( 'keydown', function( e ) {
                    if ( e.which == 13 ) {
                        search.reset();
                        api.geocoding.getLatLng();

                        return false;
                    }
                });

                // The geocode component restrictions are set automatically when a
                // fixed map region is selected on the WPSL settings page.
                if ( typeof config.api.geocodeComponents === 'object' && ! jQuery.isEmptyObject( config.api.geocodeComponents ) ) {
                    options.componentRestrictions = config.api.geocodeComponents;

                    // If the postalCode is included in the autocomplete
                    // together with '(regions)' ( which is included ),
                    // then it will break it. So we have to remove it.
                    const { postalCode, ...componentRestrictions } = options.componentRestrictions;
                    options.componentRestrictions = componentRestrictions;
                }

                // Check if we need to restrict the autocomplete data.
                if ( typeof customRestrictions === 'object' && ! jQuery.isEmptyObject( customRestrictions ) ) {

                    // If we need to restrict the autocomplete
                    // region to bounds, then create them.
                    if ( ! jQuery.isEmptyObject( customRestrictions.bounds ) ) {
                        const swLatLng = customRestrictions.bounds.sw.split( ',' );
                        const neLatLng = customRestrictions.bounds.ne.split( ',' );

                        options.bounds = new google.maps.LatLngBounds(
                            new google.maps.LatLng( swLatLng[0], swLatLng[1] ),
                            new google.maps.LatLng( neLatLng[0], neLatLng[1] )
                        );

                        delete customRestrictions.bounds;
                    }

                    // Add any additional custom restrictions.
                    for ( let key in customRestrictions ) {
                        if ( customRestrictions.hasOwnProperty( key ) ) {
                            options[key] = customRestrictions[key];
                        }
                    }
                }

                this.importedService = new importedLibraries.places.Autocomplete( input, wp.hooks.applyFilters( 'wpslAutoCompleteOptions', options ) );
            });
        },

        /**
         * Set autocomplete component restrictions.
         *
         * @since 3.0.0
         * @param {object} args
         * @return {void}
         */
        setRestrictions: function( args ) {
            this.importedService.setComponentRestrictions( args );
        },

        /**
         * Based on the selected dropdown value either init or
         * remove the autocomplete from the input field.
         *
         * Used when different search types are available
         * through the dropdown ( name / location or so ).
         *
         * @since 3.0.0
         * @param {string} searchType
         * @return {void}
         */
        toggle: function( searchType ) {
            if ( config.api.provider == 'gmaps' && config.search.autoComplete ) {
                if ( searchType == 'location' ) {
                    api.autoComplete.init();
                } else {
                    jQuery( '.pac-container' ).remove();

                    // Removing the .pac-container isn't enough to stop the
                    // autocomplete from firing. Only replacing the input
                    // field itself works, otherwise a name search made after a
                    // location search still shows the autocomplete popup warning
                    // that no results were found for 'random name'.
                    jQuery( '#wpsl-search-input' ).remove();
                    jQuery( '#wpsl-search-type-filter' ).append( '<input id="wpsl-search-input" type="text" value="" name="wpsl-search-input" placeholder="" aria-required="true" class="wpsl-has-focus" autoComplete="off">' );

                    buttons.bindSearch();
                }
            }
        }
    }
};
