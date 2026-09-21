import { state } from './wpsl-shared.js';
import { createApiRequest } from '../../../common/wpsl-core.js';
import { mapBootstrap, mapObjects } from './wpsl-map-bootstrap.js';
import { helpers } from './wpsl-helpers.js';
import { markers } from './wpsl-markers.js';
import { geocodeResponseTest } from './settings/wpsl-geocode-test.js';
import { parseStadiaFeature } from '../../../frontend/js/maps/stadia/wpsl-parse-feature.js';

/**
 * All Google / OSM / Mapbox API request related code.
 * 
 * Library loading and the geocoding / directions requests live in
 * /js/wpsl-core.js
 *
 * @since 3.0.0
 */
export const api = {
    mapbox: {
        /**
         * Load Mapbox and initialize the map.
         *
         * @since   3.0.0
         * @returns {void}
         */
        loader: function() {
            if ( [ 'editor', 'appearance' ].includes( state.currentPage ) ) {
                mapBootstrap.init();
            }
        },

        /**
         * Make a request to the Mapbox API.
         *
         * @since   3.0.0
         * @param   {object} requestParams The location details that need to be geocoded
         * @returns {void}
         */
        codeAddress: function( requestParams ) {
            createApiRequest.mapbox.geocode( requestParams, function( response ) {
                // Consume the focus flag here: a focus request that comes back
                // empty must not leave it set for the next search.
                const isFocusRequest = state.focusRegion.active;
                state.focusRegion.active = false;

                if ( state.activeMarkers.length ) {
                    markers.getActive().removeAll();
                }

                if ( ! jQuery.isEmptyObject( response.features ) ) {
                    const lat = response.features[0].geometry.coordinates[1];
                    const lng = response.features[0].geometry.coordinates[0];

                    if ( isFocusRequest ) {
                        const map = mapObjects.get();

                        state.focusRegion.latlng = [lng, lat];

                        map.setCenter( state.focusRegion.latlng );
                        map.setZoom( parseInt( wpslSettings.defaultZoom ) );
                    } else {
                        const args = {
                            lat: lat,
                            lng: lng
                        };

                        switch ( state.currentPage ) {
                            case 'editor':
                                helpers.coordinates.setLatlng( args );
                                helpers.map.setViewport( { 'latLng': { 'lat': args.lat, 'lng': args.lng } } );

                                const country = helpers.map.mapbox.findContextvalue( response, 'country' );
                                if ( typeof country === 'object' ) {
                                    if ( typeof country.text !== 'undefined' ) {
                                        jQuery( '#wpsl-country' ).val( country.text );
                                    }

                                    if ( typeof country.country_code !== 'undefined' ) {
                                        jQuery( '#wpsl-country_iso' ).val( country.country_code );
                                    }
                                }

                                const postcode = helpers.map.mapbox.findContextvalue( response, 'postcode' );
                                if ( typeof postcode === 'object' ) {
                                    helpers.dom.fillEmptyField( '#wpsl-zip', postcode.text );
                                }

                                helpers.map.maybeShowMarkerDragDescription();
                                break;
                            case 'settings':
                                const latLng = api[ state.mapService ].createLatLngObj( lat, lng );

                                geocodeResponseTest.status( 'OK' );
                                geocodeResponseTest.maybeShowImpreciseNotice( response.features[0].properties.feature_type );
                                geocodeResponseTest.maybeShowCountryMismatchNotice( response );

                                helpers.map.setViewport( { 'latLng': latLng, 'zoom': 6 } );

                                jQuery( '#wpsl-geocode-response textarea' ).val( JSON.stringify( response, null, 4 ) );
                                break;
                        }
                    }
                } else {
                    let startLatLng;

                    /**
                     * Return to the country the results were restricted to, so
                     * an empty search does not leave the preview in a country
                     * the results could never have come from.
                     *
                     * Mapbox stores the focus point as [lng, lat], matching
                     * map.setCenter(), while createLatLngObj() takes lat first.
                     */
                    if ( typeof state.focusRegion.latlng === 'object' ) {
                        startLatLng = api[ state.mapService ].createLatLngObj( state.focusRegion.latlng[1], state.focusRegion.latlng[0] );
                    } else {
                        startLatLng = api[ state.mapService ].createLatLngObj( mapBootstrap.defaultLatLng[0], mapBootstrap.defaultLatLng[1] );
                    }

                    api.resetMap( startLatLng, false );

                    if ( jQuery( '#wpsl-geocode-test' ).length ) {
                        geocodeResponseTest.status( wpslL10n.noResults );

                        jQuery( '#wpsl-geocode-response textarea' ).val( '' );
                    } else {
                        alert( wpslL10n.noResults.replace( /<br\s*\/?>/gi, '\n' ) );
                    }
                }
            });
        },

        /**
         * Create a LatLng object from the passed lat lng values.
         *
         * @since   3.0.0
         * @see     https://docs.mapbox.com/mapbox-gl-js/api/geography/#lnglat
         * @param   {string} lat Latitude value
         * @param   {string} lng Longitude value
         * @returns {LatLng}
         */
        createLatLngObj: function( lat, lng ) {
            return new mapboxgl.LngLat( lng, lat );
        },

        /**
         * Create the params used in the request
         * to the Mapbox Geocode API.
         *
         * @since 3.0.0
         * @param {object} args Optional data for the json field.
         */
        createParams: function( args = {} ) {
            let searchQuery;
            let countryRestrictions = '';

            if ( jQuery.isEmptyObject( args ) ) {
                searchQuery = helpers.coordinates.createGeocodeAddressString();
            } else {
                searchQuery = args.json;
            }

            const regions = helpers.utils.getRegionRestrictionValues( 'values' );

            if ( regions.length ) {
                countryRestrictions = regions.join( ',' );
            }

            let optionalParams = '';

            const $mapboxLanguage = jQuery( '#wpsl-api-mapbox-language' );
            const mapboxLanguage = $mapboxLanguage.length ? $mapboxLanguage.val() : '';
            if ( mapboxLanguage ) {
                optionalParams = 'language=' + mapboxLanguage + '&';
            }

            if ( state.currentPage === 'settings' && wpslSettings.zipCode === '1' && typeof args.ignoreZip === 'undefined' ) {
                optionalParams = optionalParams + 'types=postcode&';
            }

            if ( countryRestrictions ) {
                optionalParams = optionalParams + 'country=' + encodeURIComponent( countryRestrictions ) + '&';
            }

            // Build the path parameter that wpsl-core.js expects
            const encodedQuery = encodeURIComponent( searchQuery.replace( ';', ' ' ) );
            const path = 'forward?q=' + encodedQuery + ( optionalParams ? '&' + optionalParams.replace( /&$/, '' ) : '' );

            const $mapboxKeyField = jQuery( '#wpsl-api-mapbox-key' );
            const mapboxApiKey = $mapboxKeyField.length ? $mapboxKeyField.val() : wpslSettings.api.key;

            const param = {
                path: path,
                accessToken: mapboxApiKey
            };

            return param;
        },
    },
    gmaps: {
        /**
         * Load the Google Maps JavaScript API and required libraries.
         *
         * @since   3.0.0
         * @returns {void}
         */
        loader: function() {
            let additionalLibs;

            if ( jQuery( '#wpsl-map-settings' ).length || state.currentPage === 'editor' ) {
                additionalLibs = [ 'places' ];
            }

            createApiRequest.maybeLoadLibraries( additionalLibs, function() {
                if ( [ 'editor', 'appearance' ].includes( state.currentPage ) ) {
                    mapBootstrap.init();
                }
            });
        },

        /**
         * Make a request to the Google Geocode API.
         *
         * @since   3.0.0
         * @param   {object} requestParams The location details that need to be geocoded
         * @returns {void}
         */
        codeAddress: function( requestParams ) {
            const map = mapObjects.get();

            let latlng;

            createApiRequest.gmaps.geocode( requestParams, function( response, status ) {
                // Consume the focus flag here: a focus request that comes back
                // empty must not leave it set for the next search.
                const isFocusRequest = state.focusRegion.active;
                state.focusRegion.active = false;

                if ( status === 'OK' && typeof response[0] !== 'undefined' ) {
                    latlng = response[0].geometry.location;

                    jQuery( '.wpsl-geocode-partial-match' ).remove();

                    if ( typeof response[0].partial_match === 'boolean' ) {
                        jQuery( '.wpsl-geocode-api-notice' ).after( '<p class="wpsl-geocode-partial-match"><span class="wpsl-info wpsl-warning"></span>' + wpslL10n.partialMatch + '</p>' );
                    }

                    markers.getActive().removeAll();
                }

                if ( isFocusRequest && status === 'OK' ) {
                    state.focusRegion.latlng = latlng;

                    map.fitBounds( response[0].geometry.bounds );
                } else {
                    switch ( state.currentPage ) {
                        case 'editor':
                            if ( status === 'OK' ) {
                                helpers.coordinates.setLatlng( latlng );
                                helpers.map.setViewport( { 'latLng': latlng } );

                                const filteredResponse = api[ state.mapService ].filterApiResponse( response );

                                jQuery( '#wpsl-country' ).val( filteredResponse.country.long_name );
                                jQuery( '#wpsl-country_iso' ).val( filteredResponse.country.short_name );

                                helpers.dom.fillEmptyField( '#wpsl-zip', filteredResponse.zip );

                                helpers.map.maybeShowMarkerDragDescription();
                            } else {
                                alert( wpslL10n.geocodeFail + ": " + status );
                            }
                            break;
                        case 'settings':
                            if ( status === 'OK' || status === 'ZERO_RESULTS' ) {
                                // The Google OK path doesn't call status(), so hide
                                // the map loader here as well.
                                geocodeResponseTest.hideMapLoader();

                                if ( status === 'OK' ) {
                                    helpers.map.setViewport( { 'latLng': latlng, 'zoom': 6 } );
                                } else {
                                    let startLatLng;

                                    if ( typeof state.focusRegion.latlng === 'object' ) {
                                        startLatLng = state.focusRegion.latlng;
                                    }

                                    jQuery( '#wpsl-geocode-response textarea' ).val( '' );

                                    api.resetMap( startLatLng, false );
                                }
                            } else {
                                status = wpslL10n.browserKeyError;

                                jQuery( "div[id^='wpsl-'][id$='-geocode-preview'], #wpsl-geocode-response textarea" ).remove();
                            }

                            geocodeResponseTest.status( status );
                            geocodeResponseTest.maybeShowCountryMismatchNotice( response );

                            if ( response !== null && response ) {
                                jQuery( '#wpsl-geocode-response textarea' ).val( JSON.stringify( response, null, 4 ) );
                            }
                            break;

                    }
                }
            });
        },

        /**
         * Filter out the country name and postcode from the API response.
         *
         * @since	1.0.0
         * @param   {object} response	   The response of the geocode API
         * @returns {object} collectedData The country names and the postcode
         */
        filterApiResponse: function( response ) {
            const addressLength = response[0].address_components.length;

            let responseType, country = {}, zip = '';

            for ( let i = 0; i < addressLength; i++ ) {
                responseType = response[0].address_components[i].types;

                if ( /^country,political$/.test( responseType ) ) {
                    country = {
                        long_name: response[0].address_components[i].long_name,
                        short_name: response[0].address_components[i].short_name
                    };
                } else if ( /^postal_code$/.test( responseType ) ) {
                    zip = response[0].address_components[i].long_name;
                }
            }

            const collectedData = {
                country: country,
                zip: zip
            };

            return collectedData;
        },

        /**
         * Create a LatLng object from the passed lat lng values.
         *
         * @since   3.0.0
         * @see     https://developers.google.com/maps/documentation/javascript/reference/coordinates?hl=en
         * @param   {string} lat Latitude value
         * @param   {string} lng Longitude value
         * @returns {LatLng}
         */
        createLatLngObj: function( lat, lng ) {
            return new google.maps.LatLng( lat, lng );
        },

        /**
         * Bind a listener to Google Maps that checks for any
         * errors with the users billing account ( happens regulary ).
         *
         * @since   3.0.0
         * @returns {void}
         */
        errorListener: function() {
            const self = this;
            const map = mapObjects.get();

            google.maps.event.addListenerOnce( map, 'tilesloaded', function() {
                self.checkLoadErrors();
            });
        },

        /**
         * Replace the preview with an error when the map failed to load.
         *
         * A billing account problem leaves a 'dismissButton' class in the map.
         *
         * @since   2.2.22
         * @returns {void}
         */
        checkLoadErrors: function() {
            setTimeout( () => {
                if ( jQuery( '#wpsl-gmaps-geocode-preview .dismissButton' ).length > 0 ) {
                    jQuery( '.wpsl-geocode-warning, #wpsl-geocode-test input' ).remove();

                    geocodeResponseTest.status( wpslL10n.loadingError );
                }
            }, 1000 );
        },

        /**
         * Create the params used in the request
         * to the Google Maps Geocode API.
         *
         * @since   2.2.22
         * @returns {object} request The parameters included in the geocode API request
         */
        createParams: function() {
            const address = helpers.coordinates.createGeocodeAddressString();
            const request = {};

            if ( typeof wpslSettings.geocodeComponents !== 'undefined' && ! jQuery.isEmptyObject( wpslSettings.geocodeComponents ) ) {
                request.componentRestrictions = wpslSettings.geocodeComponents;

                if ( typeof request.componentRestrictions.postalCode !== 'undefined' ) {
                    request.componentRestrictions.postalCode = address;
                } else {
                    request.address = address;
                }
            } else {
                request.address = address;
            }

            const $gmapsLanguage = jQuery( '#wpsl-api-language' );
            const gmapsLanguage = $gmapsLanguage.length ? $gmapsLanguage.val() : '';

            if ( gmapsLanguage ) {
                request.language = gmapsLanguage;
            }

            return request;
        },
    },
    osm: {
        /**
         * Load OSM and initialize the map.
         *
         * @since   3.0.0
         * @returns {void}
         */
        loader: function() {
            if ( [ 'editor', 'appearance' ].includes( state.currentPage ) ) {
                mapBootstrap.init();
            }
        },

        /**
         * Make a request to the OpenStreetMaps API.
         *
         * @since   3.0.0
         * @param   {object} requestParams The location details that need to be geocoded
         * @returns {void}
         */
        codeAddress: function( requestParams ) {
            createApiRequest.osm.geocode( requestParams, function( response ) {
                // Consume the focus flag here: a focus request that comes back
                // empty must not leave it set for the next search.
                const isFocusRequest = state.focusRegion.active;
                state.focusRegion.active = false;

                if ( state.activeMarkers.length ) {
                    markers.getActive().removeAll();
                }

                if ( response.length && typeof response[0].lat === 'string' ) {
                    const map = mapObjects.get();

                    if ( isFocusRequest ) {
                        state.focusRegion.latlng = [response[0].lat, response[0].lon];

                        map.setView( state.focusRegion.latlng, parseInt( wpslSettings.defaultZoom ) );
                    } else {
                        const args = {
                            lat: response[0].lat,
                            lng: response[0].lon,
                            addressDetails: response[0].address
                        };

                        switch ( state.currentPage ) {
                            case 'editor':
                                helpers.coordinates.setLatlng( args );

                                map.setView( [args.lat, args.lng], 16 );

                                markers.getActive().add( { latLng: { lat: args.lat, lng: args.lng } } );

                                if ( typeof args.addressDetails === 'object' ) {
                                    if ( typeof args.addressDetails.country !== 'undefined' ) {
                                        jQuery( '#wpsl-country' ).val( args.addressDetails.country );
                                    }

                                    if ( typeof args.addressDetails.country_code !== 'undefined' ) {
                                        jQuery( '#wpsl-country_iso' ).val( args.addressDetails.country_code );
                                    }

                                    helpers.dom.fillEmptyField( '#wpsl-zip', args.addressDetails.postcode );
                                }

                                helpers.map.maybeShowMarkerDragDescription();

                                break;
                            case 'settings':
                                const latLng = api[ state.mapService ].createLatLngObj( args.lat, args.lng );

                                geocodeResponseTest.status( 'OK' );
                                geocodeResponseTest.maybeShowCountryMismatchNotice( response );

                                helpers.map.setViewport( {
                                    'latLng': {
                                        'lat': latLng.lat,
                                        'lng': latLng.lng
                                    },
                                    'zoom': 6
                                } );

                                jQuery( '#wpsl-geocode-response textarea' ).val( JSON.stringify( response, null, 4 ) );

                                break;
                        }
                    }
                } else {
                    let startLatLng;

                    if ( typeof state.focusRegion.latlng === 'object' ) {
                        startLatLng = api[ state.mapService ].createLatLngObj( state.focusRegion.latlng[0], state.focusRegion.latlng[1] );
                    } else {
                        startLatLng = api[ state.mapService ].createLatLngObj( mapBootstrap.defaultLatLng[0], mapBootstrap.defaultLatLng[1] );
                    }

                    api.resetMap( startLatLng, false );

                    if ( jQuery( '#wpsl-geocode-test' ).length ) {
                        geocodeResponseTest.status( wpslL10n.noResults );

                        jQuery( '#wpsl-geocode-response textarea' ).val( '' );
                    } else {
                        alert( wpslL10n.noResults.replace( /<br\s*\/?>/gi, '\n' ) );
                    }
                }
            });
        },
        /**
         * Create a new latLng object.
         *
         * @since   3.0.0
         * @see     https://leafletjs.com/reference.html#latlng
         * @param   {string} lat Latitude values
         * @param   {string} lng Longitude values
         * @returns {object}
         */
        createLatLngObj: function( lat, lng ) {
            return L.latLng( lat, lng );
        },

        /**
         * Create the params used in the request
         * to the Nominatim Geocode API.
         *
         * @since  2.2.22
         * @param  {object} args
         * @return {object} request The parameters included in the geocode API request
         */
        createParams: function( args ) {
            let location;
            let defaults = '&format=json&addressdetails=1';

            if ( typeof args === 'object' && typeof args.country !== 'undefined' ) {
                location = 'country=' + encodeURIComponent( args.country );
            } else {
                location = this.createGeocodeAddress();
            }

            const regions = helpers.utils.getRegionRestrictionValues( 'values' );

            if ( regions.length ) {
                defaults = defaults + '&countrycodes=' + encodeURIComponent( regions.join( ',' ) );
            }

            const $osmLanguage = jQuery( '#wpsl-api-osm-language' );
            const osmLanguage = $osmLanguage.length ? $osmLanguage.val() : '';

            if ( osmLanguage ) {
                defaults = defaults + '&accept-language=' + osmLanguage;
            }

            return location + defaults;
        },

        /**
         * Create the address string send to the geocode API
         *
         * @since   3.0.0
         * @returns {string}
         */
        createGeocodeAddress: function() {
            const addressParts = {
                'address': 'street',
                'city': 'city',
                'state': 'state',
                'zip': 'postalcode',
                'country': 'country'
            };

            let part, paramSection = [];

            for ( const key in addressParts ) {
                if ( addressParts.hasOwnProperty( key ) ) {
                    part = jQuery( '#wpsl-' + key ).val().trim();
                    if ( part ) {
                        paramSection.push( addressParts[key] + '=' + encodeURIComponent( part ) );
                    }
                }
            }

            return paramSection.join( '&' );
        },
    },
    stadia: {
        /**
         * Load Stadia ( Stamen ) tiles through the OSM/Leaflet loader.
         *
         * @since   3.0.0
         * @returns {void}
         */
        loader: function() {
            api.osm.loader();
        },

        /**
         * Create a LatLng object from the passed lat lng values.
         *
         * @since   3.0.0
         * @see     https://leafletjs.com/reference.html#latlng
         * @param   {string} lat Latitude value
         * @param   {string} lng Longitude value
         * @returns {object}
         */
        createLatLngObj: function( lat, lng ) {
            return api.osm.createLatLngObj( lat, lng );
        },

        /**
         * Make a request to the Stadia geocoding API.
         *
         * @since   3.0.0
         * @param   {object} requestParams The location details that need to be geocoded
         * @returns {void}
         */
        codeAddress: function( requestParams ) {
            createApiRequest.stadia.geocode( requestParams, function( response ) {
                // Consume the focus flag here: a focus request that comes back
                // empty must not leave it set for the next search.
                const isFocusRequest = state.focusRegion.active;
                state.focusRegion.active = false;

                if ( state.activeMarkers.length ) {
                    markers.getActive().removeAll();
                }

                if ( response && response.features && response.features.length ) {
                    const feature = response.features[0];
                    const map = mapObjects.get();
                    const lat = feature.geometry.coordinates[1];
                    const lng = feature.geometry.coordinates[0];

                    if ( isFocusRequest ) {
                        state.focusRegion.latlng = [lat, lng];
                        map.setView( state.focusRegion.latlng, parseInt( wpslSettings.defaultZoom ) );
                    } else {
                        const args = {
                            lat: lat,
                            lng: lng,
                            addressDetails: parseStadiaFeature( feature )
                        };

                        switch ( state.currentPage ) {
                            case 'editor':
                                helpers.coordinates.setLatlng( args );
                                map.setView( [args.lat, args.lng], 16 );
                                markers.getActive().add( { latLng: { lat: args.lat, lng: args.lng } } );

                                if ( typeof args.addressDetails === 'object' ) {
                                    if ( args.addressDetails.country ) {
                                        jQuery( '#wpsl-country' ).val( args.addressDetails.country );
                                    }
                                    if ( args.addressDetails.countryCode ) {
                                        jQuery( '#wpsl-country_iso' ).val( args.addressDetails.countryCode );
                                    }

                                    helpers.dom.fillEmptyField( '#wpsl-zip', args.addressDetails.postalCode );
                                }

                                helpers.map.maybeShowMarkerDragDescription();
                                break;
                            case 'settings':
                                const latLng = api[ state.mapService ].createLatLngObj( args.lat, args.lng );
                                geocodeResponseTest.status( 'OK' );
                                geocodeResponseTest.maybeShowCountryMismatchNotice( response );
                                helpers.map.setViewport( {
                                    'latLng': {
                                        'lat': latLng.lat,
                                        'lng': latLng.lng
                                    },
                                    'zoom': 6
                                } );
                                jQuery( '#wpsl-geocode-response textarea' ).val( JSON.stringify( response, null, 4 ) );
                                break;
                        }
                    }
                } else {
                    let startLatLng;

                    if ( typeof state.focusRegion.latlng === 'object' ) {
                        startLatLng = api[ state.mapService ].createLatLngObj( state.focusRegion.latlng[0], state.focusRegion.latlng[1] );
                    } else {
                        startLatLng = api[ state.mapService ].createLatLngObj( mapBootstrap.defaultLatLng[0], mapBootstrap.defaultLatLng[1] );
                    }

                    api.resetMap( startLatLng, false );

                    if ( jQuery( '#wpsl-geocode-test' ).length ) {
                        geocodeResponseTest.status( wpslL10n.noResults );
                        jQuery( '#wpsl-geocode-response textarea' ).val( '' );
                    } else {
                        alert( wpslL10n.noResults.replace( /<br\s*\/?>/gi, '\n' ) );
                    }
                }
            });
        },

        /**
         * Create the params used in the request
         * to the Stadia Maps Geocoding Search API.
         *
         * @since  3.0.0
         * @param  {object} args
         * @return {object} params The parameters for the geocode API request
         */
        createParams: function( args ) {
            let params = {};

            if ( typeof args === 'object' && typeof args.country !== 'undefined' ) {
                params.text = args.country;
            } else {
                params.text = this.createGeocodeAddress();
            }

            const regions = helpers.utils.getRegionRestrictionValues( 'values' );

            if ( regions.length ) {
                params['boundary.country'] = regions.join( ',' );
            }

            const $stadiaLanguage = jQuery( '#wpsl-api-stadia-language' );
            const stadiaLanguage = $stadiaLanguage.length ? $stadiaLanguage.val() : '';

            if ( stadiaLanguage ) {
                params.lang = stadiaLanguage;
            }

            return params;
        },

        /**
         * Create the address string sent to the geocode API.
         *
         * @since   3.0.0
         * @returns {string}
         */
        createGeocodeAddress: function() {
            const addressParts = {
                'address': 'street',
                'city': 'city',
                'state': 'state',
                'zip': 'postalcode',
                'country': 'country'
            };

            let part, paramSection = [];

            for ( const key in addressParts ) {
                if ( addressParts.hasOwnProperty( key ) ) {
                    part = jQuery( '#wpsl-' + key ).val().trim();

                    if ( part ) {
                        paramSection.push( part );
                    }
                }
            }

            return paramSection.join( ', ' );
        },
    },
    
    /**
     * Reset the map back to the default state.
     *
     * - Remove existing markers
     * - Maybe add marker based on the start location ( based on addMarker value )
     * - Reset the zoom level to default
     * - Focus on start location latlng
     *
     * @since   3.0.0
     * @param   {obj}  startLatLng
     * @param   {bool} addMarker   Optionally add the start marker
     * @returns {void}
     */
    resetMap: function( startLatLng, addMarker = true ) {

        if ( ! startLatLng ) {
            const defaultLatLng = mapBootstrap.getDefaultLatLng();
            startLatLng = api[ state.mapService ].createLatLngObj( defaultLatLng[0], defaultLatLng[1] );
        }

        const args = {
            'latLng': startLatLng,
            'zoom': parseInt( wpslSettings.defaultZoom ),
            'addMarker': addMarker
        };

        markers.getActive().removeAll();

        helpers.map.setViewport( args );
    },
};