import { createApiRequest } from '../../../../../common/wpsl-core.js';
import { slData, config } from '../../../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { responseHandlers } from '../../../modules/wpsl-response-handlers.js';
import { buttons } from '../../../modules/wpsl-buttons.js';
import { geojson } from './wpsl-geojson.js';
import { infoWindow } from './wpsl-infowindow.js';
import { layers } from './wpsl-layers.js';
import { markers } from './wpsl-markers.js';

// The unclustered copy of the locations a route draws from while clustering is on.
const ROUTE_SOURCE = 'wpsl-route-locations';

/**
 * Mapbox API handling (geocoding, directions, autocomplete).
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
         * Geocode the user input with the Mapbox Geocode API.
         *
         * @since  3.0.0
         * @param  {object} args Geocoding arguments
         * @return {void}
         */
        getLatLng: function( args) {
            const self = this;
            const requestArgs = this.createRequestArgs( args, 'geocode' );

            // A newer search may start before Mapbox answers, see search.current().
            const stillCurrent = search.current( () => true );

            createApiRequest.mapbox.geocode( wp.hooks.applyFilters( 'wpslGeocodeParam', requestArgs ), function( response ) {
                if ( ! stillCurrent() ) {
                    return;
                }

                if ( ! jQuery.isEmptyObject( response.features ) && self.isUsableGeocodeResult( response.features[0] ) ) {
                    helpers.search.maybeIncludeStatistics( response );

                    args = helpers.search.maybeGetFullSearchData( args, response );
                    args.lat = response.features[0].geometry.coordinates[1];
                    args.lng = response.features[0].geometry.coordinates[0];

                    wp.hooks.doAction( 'wpslGeocodeFinished', args, response );

                    search.prepare( args );
                } else {
                    // Mapbox returns an empty features array for no results. A
                    // non-object response is an actual error ( network, bad key ).
                    if ( response && typeof response === 'object' ) {
                        response.userNotice = wpslLabels.noResults;
                    } else {
                        response = { userNotice: wpslLabels.technicalProblem };
                    }

                    responseHandlers.maybeShowResponseText( response );
                }
            });
        },

        /**
         * Check whether a geocode result actually matches what was searched for.
         *
         * Mapbox grades address matches through properties.match_code, where
         * confidence 'low' means the house number, the region, or more than two
         * other components had to be corrected -- effectively a different
         * address, so it counts as no result.
         *
         * match_code is only present on address-type features. A town or
         * postcode search returns a 'place' / 'postcode' feature without one,
         * which is a perfectly good store locator result, so an absent
         * match_code is accepted rather than rejected.
         *
         * @see    https://docs.mapbox.com/api/search/geocoding/
         * @since  3.0.0
         * @param  {object} feature A single GeoJSON feature from the response.
         * @return {boolean} Whether the result should be used.
         */
        isUsableGeocodeResult: function( feature ) {
            const properties = ( feature && feature.properties ) || {};
            const matchCode  = properties.match_code;

            // No grading available ( place, postcode, region, ... ) - accept.
            const usable = ! matchCode || matchCode.confidence !== 'low';

            return wp.hooks.applyFilters( 'wpslMapboxUsableGeocodeResult', usable, feature );
        },

        /**
         * Reverse geocode the passed coordinates and set the
         * returned zipcode / address in the input field.
         *
         * @since  3.0.0
         * @param  {object} args The coordinates and optionally whether it's from a draggable marker or not
         * @param  {Function} callback Callback function to execute
         * @return {void}
         */
        reverse: function( args, callback ) {
            const requestArgs = this.createRequestArgs( args, 'reverse' );
            createApiRequest.mapbox.geocode( wp.hooks.applyFilters( 'wpslGeocodeParam', requestArgs ), function( response ) {
                if ( typeof response.features === 'object' && response.features.length ) {
                    args = responseHandlers.reverseGeocodeFinished( args, response );

                    callback( args );
                } else {
                    responseHandlers.reverseGeocodeFailed( args, response, callback );
                }
            });
        },

        /**
         * Filter out different data from the API response.
         *
         * @since  3.0.0
         * @param  {object} response The response Mapbox API
         * @param  {string} format See which data to grab from the response ( zip, country, full address etc. )
         * @return {object} userLocation The filtered API data
         */
        filterResponse: function( response, format ) {
            let contextValue,
                userLocation = {};

            if ( ! format ) {
                if ( response.features[0].id.indexOf( 'country' ) !== -1 ) {
                    userLocation.fullSearch = {
                        type: 'country'
                    };
                }

                if ( response.features[0].id.indexOf( 'region' ) !== -1 ) {
                    userLocation.fullSearch = {
                        type: 'state'
                    };
                }

                if ( typeof userLocation.fullSearch === 'object' ) {
                    const longName = response.features[0].text;
                    let shortName = response.features[0].properties.short_code.toUpperCase();

                    if ( userLocation.fullSearch.type === 'state' ) {
                        const stateSplit = shortName.split( '-' );

                        shortName = stateSplit[1];
                    }

                    userLocation.fullSearch.query = longName + ',' + shortName;
                }
            } else {
                switch ( format ) {
                    case 'country':
                        contextValue = this.findContextvalue( response, 'country' );
                        userLocation.country = contextValue.text;
                        userLocation.countryCode = contextValue.short_code;
                        break;
                    case 'formatted_address':
                        // Mapbox uses full_address for the complete formatted address
                        userLocation.location = response.features[0].properties.full_address || response.features[0].place_name || '';
                        break;
                    case 'city':
                        contextValue = this.findContextvalue( response, 'place' );
                        userLocation.location = contextValue.text;
                        break;
                    case 'zip':
                        contextValue = this.findContextvalue( response, 'postcode' );
                        userLocation.location = contextValue.text;
                        break;
                }
            }

            return wp.hooks.applyFilters( 'wpslFilterResponse', userLocation );
        },

        /**
         * Return the text value from the context data included in the API response.
         *
         * @since  3.0.0
         * @param  {object} response API response data
         * @param  {string} targetId The field to get the value from
         * @return {object} value The text / short_code value from the context field
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

        /**
         * Create the params for a Geocode API request.
         *
         * @since  3.0.0
         * @param  {object} args Geocoding arguments
         * @param  {string} type Forward / reverse geocoding
         * @return {object} params Request parameters
         */
        createRequestArgs: function( args, type ) {
            const requestArgs = {
                accessToken: config.api.key,
                optional: ''
            };

            if ( type === 'reverse' ) {
                const coordinates = helpers.extractCoordinates( args );
                if ( coordinates && typeof coordinates.lng === 'number' && typeof coordinates.lat === 'number' ) {
                    requestArgs.path = 'reverse?longitude=' + encodeURIComponent( coordinates.lng ) + '&latitude=' + encodeURIComponent( coordinates.lat );
                } else {
                    const searchValue = jQuery( '#wpsl-search-input' ).val() || '';
                    requestArgs.path = 'forward?q=' + encodeURIComponent( searchValue.replace( ';', ' ' ) );
                }
            } else {
                const searchValue = jQuery( '#wpsl-search-input' ).val() || '';
                requestArgs.path = 'forward?q=' + encodeURIComponent( searchValue.replace( ';', ' ' ) );
            }

            if ( config.api.language ) {
                requestArgs.optional = 'language=' + encodeURIComponent( config.api.language ) + '&';
            }

            // A [wpsl country="..."] shortcode restriction overrides the
            // settings-page country restriction for this map instance.
            const geocodeCountry = helpers.search.getShortcodeCountry() || config.api.regions;
            if ( geocodeCountry ) {
                requestArgs.optional = requestArgs.optional + 'country=' + encodeURIComponent( geocodeCountry ) + '&';
            }

            if ( config.api.filters ) {
                requestArgs.optional = requestArgs.optional + 'types=' + encodeURIComponent( config.api.filters ) + '&';
            }

            return requestArgs;
        },
    },

    /**
     * Directions API methods.
     * 
     * @since 3.0.0
     */
    directions: {
        storeId: '',

        /**
         * Initialize directions module.
         *
         * @since  3.0.0
         * @param  {Function} callback Callback function to execute
         * @return {void}
         */
        init: function( callback ) {
            callback();
        },

        /**
         * Show the directions on the map.
         *
         * @since  3.0.0
         * @param  {object} e The clicked element
         * @return {void}
         */
        show: function( e ) {
            const map = slData.maps[0];
            const storeId = helpers.results.getClickedElemID( e );

            const originLatLng = geojson.active.features[0].geometry.coordinates.join( ',' );

            let destinationLatLng;

            infoWindow.close();

            geojson.icon.restoreAll();
            
            map.getSource( 'locations' ).setData( geojson.active );

            slData.directions.destinationId = storeId;

            slData.viewport = {
                center: map.getCenter(),
                zoomLevel: map.getZoom()
            };

            map.getSource( 'locations' )._data.features.forEach( function( feature ) {
                if ( ! feature.properties ) {
                    return;
                }
                
                if ( feature.properties.id === storeId ) {
                    slData.directions.destinationLayer = feature.properties.icon;

                    destinationLatLng = feature.geometry.coordinates.join( ',' );
                }
            });

            helpers.directions.checkRouteCoordinates( originLatLng, destinationLatLng );

            wp.hooks.doAction( 'wpslShowDirections', originLatLng, destinationLatLng );
        },

        /**
         * Calculate the route from the start to the end.
         *
         * @since  3.0.0
         * @param  {string} originLatLng The start coordinates
         * @param  {string} destinationLatLng The end coordinates
         * @return {void}
         */
        calcRoute: function( originLatLng, destinationLatLng ) {
            const args = {
                accessToken: config.api.key,
                coordinates: encodeURIComponent( originLatLng + ';' + destinationLatLng ),
                profile: config.search.directionsTravelMode
            };

            let index, directionStops = '';

            createApiRequest.mapbox.directions( wp.hooks.applyFilters( 'wpslMapboxDirectionsApiParams', args ), function( response ) {
                if ( response.query.ok && response.query.status === 200 ) {
                    slData.directions.active = true;

                    // The route is drawn on the map, so the shapes go until it is gone.
                    helpers.directions.setShapesVisible( false );

                    switch ( response.json.code ) {
                        case 'Ok':
                            const bounds = new mapboxgl.LngLatBounds();
                            const directions = response.json.routes[0];
                            const route = directions.geometry.coordinates;
                            const routeGeojson = {
                                type: 'Feature',
                                properties: {},
                                geometry: {
                                    type: 'LineString',
                                    coordinates: route
                                }
                            };

                            jQuery( '#wpsl-map' ).addClass( 'wpsl-directions-active' );

                            bounds.extend( originLatLng.split( ',' ).map( Number ) );
                            bounds.extend( destinationLatLng.split( ',' ).map( Number ) );

                            // The source outlives a route ( see restoreResults() ), the line layer does not.
                            if ( slData.maps[0].getSource( 'route' ) ) {
                                slData.maps[0].getSource( 'route' ).setData( routeGeojson );
                            } else {
                                slData.maps[0].addSource( 'route', {
                                    type: 'geojson',
                                    data: routeGeojson
                                });
                            }

                            if ( ! slData.maps[0].getLayer( 'route' ) ) {
                                slData.maps[0].addLayer({
                                    id: 'route',
                                    type: 'line',
                                    source: 'route',
                                    layout: {
                                        'line-join': 'round',
                                        'line-cap': 'round'
                                    },
                                    paint: {
                                        'line-color': '#3887be',
                                        'line-width': 5,
                                        'line-opacity': 0.75
                                    }
                                }, 'start' );
                            }

                            directions.geometry.coordinates.forEach( coord => {
                                bounds.extend( coord );
                            });

                            slData.maps[0].fitBounds( bounds, { padding: 35 } );

                            if ( directions.legs[0].steps.length > 0 ) {
                                const steps = directions.legs[0].steps;

                                jQuery.each( steps, function( i ) {
                                    index = i + 1;
                                    directionStops = directionStops + '<li><div class="wpsl-direction-index">' + index + '.</div><div class="wpsl-direction-txt">' + sharedHelpers.escapeHtml( steps[i].maneuver.instruction ) + '</div><div class="wpsl-direction-distance">' + helpers.formatDirectionsDistance( steps[i].distance.toFixed( 2 ), 'm' ) + '</div></li>';
                                });

                                const totalDistance = helpers.formatDirectionsDistance( directions.distance.toFixed( 2 ), 'm' );
                                const totalDuration = helpers.formatDirectionsDuration( directions.duration );
                                
                                jQuery( '#wpsl-direction-details ul' ).append( directionStops ).before( helpers.formatDirectionsHeader( totalDistance, totalDuration ) );
                                jQuery( '#wpsl-direction-details' ).show();

                                jQuery( '#wpsl-stores, #wpsl-result-filters' ).hide();

                                helpers.directions.focusBackButton();
                                helpers.directions.styling.init();

                                if ( helpers.markers.clusteringActive( slData.maps[0] ) ) {
                                    const currentSource = slData.maps[0].getSource( 'locations' );
                                    if ( currentSource && currentSource._data ) {
                                        const sourceData = currentSource._data;
                                        
                                        for ( const layerId in slData.layerDetails ) {
                                            if ( slData.maps[0].getLayer( layerId ) ) {
                                                slData.maps[0].removeLayer( layerId );
                                            }
                                        }
                                        
                                        if ( slData.maps[0].getLayer( 'clusters' ) ) {
                                            slData.maps[0].removeLayer( 'clusters' );
                                        }
                                        if ( slData.maps[0].getLayer( 'cluster-count' ) ) {
                                            slData.maps[0].removeLayer( 'cluster-count' );
                                        }
                                        
                                        /*
                                         * The route draws from an unclustered copy, so the two stops
                                         * never merge into a cluster. A source of its own rather than
                                         * swapping 'locations' out: removing a source makes Mapbox GL
                                         * update the terrain, which throws on Mapbox Standard.
                                         */
                                        const routeSource = slData.maps[0].getSource( ROUTE_SOURCE );

                                        if ( routeSource ) {
                                            routeSource.setData( sourceData );
                                        } else {
                                            slData.maps[0].addSource( ROUTE_SOURCE, {
                                                'type': 'geojson',
                                                'data': sourceData
                                            });

                                            // So a new search empties it along with the others.
                                            slData.activeSources.push( ROUTE_SOURCE );
                                        }

                                        // A start marker kept out of the clusters has a source of its own.
                                        const startApart = config.markers.cluster.excludeStartMarker && slData.maps[0].getSource( 'start-location' );

                                        for ( const layerId in slData.layerDetails ) {
                                            const args = {
                                                'layerId':    layerId,
                                                'map':        slData.maps[0],
                                                'dataSource': ( 'start' === layerId && startApart ) ? 'start-location' : ROUTE_SOURCE
                                            };

                                            layers.add( args );
                                        }
                                    }
                                }

                                for ( const layer of layers.active ) {
                                    if ( ! slData.maps[0].getLayer( layer ) ) {
                                        continue;
                                    }
                                    
                                    if ( layer === 'start' ) {
                                        slData.maps[0].setFilter( layer, ['==', ['get', 'id'], 0] );
                                    } else if ( layer === slData.directions.destinationLayer ) {
                                        slData.maps[0].setFilter( layer, ['==', ['get', 'id'], slData.directions.destinationId] );
                                    } else {
                                        slData.maps[0].setFilter( layer, ['==', ['get', 'id'], -999] );
                                    }
                                }
                            }

                            break;
                        case 'NoRoute':
                            response.userNotice = wpslLabels.noDirectionsFoundMessage;
                            responseHandlers.maybeShowResponseText( response, 'directions' );
                            break;
                        default:
                            const json = {
                                status: response.query.status,
                                responseJSON: response.json
                            };

                            responseHandlers.maybeShowResponseText( json, 'directions' );
                            break;
                    }
                } else {
                    let json = {
                        status: response.query.status,
                        responseJSON: response.json
                    };

                    responseHandlers.maybeShowResponseText( json, 'directions' );
                }
            });
        },

        /**
         * Handle clicks on the back button when the route directions are displayed.
         *
         * @since  3.0.0
         * @return {void}
         */
        restoreResults: function() {
            const map = slData.maps[0];
            if ( map.getLayer( 'route' ) ) {
                map.removeLayer( 'route' );
            }

            /*
             * Emptied, not removed: removing a source makes Mapbox GL update
             * the terrain, which throws on a style with terrain ( Mapbox
             * Standard ). The next route refills it.
             */
            if ( map.getSource( 'route' ) ) {
                map.getSource( 'route' ).setData( { type: 'FeatureCollection', features: [] } );
            }

            geojson.icon.restoreAll();

            if ( helpers.markers.clusteringActive( map ) ) {
                const currentSource = map.getSource( 'locations' );
                if ( currentSource ) {
                    const activeFeatures = geojson.active.features || [];
                    let storeCollection;

                    const excludeStart   = config.markers.cluster.excludeStartMarker;
                    if ( excludeStart ) {
                        // With "exclude start marker" on the start marker stays out of
                        // 'locations', so keep the 'start-location' source in sync.
                        const startFeature  = activeFeatures.find( f => f.properties && f.properties.id === 0 );
                        const storeFeatures = activeFeatures.filter( f => ! ( f.properties && f.properties.id === 0 ) );

                        storeCollection = { type: 'FeatureCollection', features: storeFeatures };

                        if ( startFeature ) {
                            const startSource = map.getSource( 'start-location' );
                            if ( startSource ) {
                                startSource.setData( {
                                    type:     'FeatureCollection',
                                    features: [ startFeature ]
                                });
                            }
                        }
                    } else {
                        // Otherwise the start marker stays in with the rest.
                        storeCollection = geojson.active;
                    }

                    for ( const layerId in slData.layerDetails ) {
                        if ( map.getLayer( layerId ) ) {
                            map.removeLayer( layerId );
                        }
                    }

                    // Still the clustered source: the route drew from ROUTE_SOURCE instead.
                    currentSource.setData( storeCollection );

                    if ( map.getSource( ROUTE_SOURCE ) ) {
                        map.getSource( ROUTE_SOURCE ).setData( { type: 'FeatureCollection', features: [] } );
                    }

                    for ( const layerId in slData.layerDetails ) {
                        const args = {
                            'layerId': layerId,
                            'map':     map
                        };
                        layers.add( args );
                    }

                    markers.createCluster( map );

                    // Re-move start layer to top after cluster layers are re-added.
                    if ( config.markers.startOnTop && map.getLayer( 'start' ) ) {
                        map.moveLayer( 'start' );
                    }

                    for ( const layer of layers.active ) {
                        if ( layer === 'clusters' || layer === 'cluster-count' ) {
                            continue;
                        }

                        if ( map.getLayer( layer ) ) {
                            map.setFilter( layer, ['==', 'icon', layer] );
                        }
                    }
                } else {
                    if ( map.getLayer( 'clusters' ) ) {
                        map.setLayoutProperty( 'clusters', 'visibility', 'visible' );
                    }
                    
                    if ( map.getLayer( 'cluster-count' ) ) {
                        map.setLayoutProperty( 'cluster-count', 'visibility', 'visible' );
                    }
                }
            } else {
                map.getSource( 'locations' ).setData( geojson.active );

                for ( const layer of layers.active ) {
                    if ( map.getLayer( layer ) ) {
                        map.setFilter( layer, ['==', 'icon', layer] );
                    }
                }
            }

            map.setCenter( slData.viewport.center );
            map.setZoom( slData.viewport.zoomLevel );

            helpers.results.sharedRestoreSteps();
            
            if ( map._wpslLastFocusedMarkerId ) {
                setTimeout( () => {
                    const button = document.querySelector( `.wpsl-mapbox-marker-overlay[data-store-id="${map._wpslLastFocusedMarkerId}"]` );
                    if ( button ) {
                        button.focus();
                    }
                }, 100 );
            }
        }
    },

    /**
     * Autocomplete API methods.
     * 
     * @since 3.0.0
     */
    autoComplete: {
        listener: '',

        /**
         * Activate the autocomplete for the location search.
         *
         * @since  3.0.0
         * @return {void}
         */
        init: function() {

            if ( ! jQuery( '#mapbox-autocomplete' ).length ) {
                return;
            }

            const placeholder = jQuery( '#wpsl-search-input' ).attr( 'placeholder' );
            const params = {
                accessToken: config.api.key,
                useBrowserFocus: true, // copied from https://docs.mapbox.com/mapbox-gl-js/example/mapbox-gl-geocoder-limit-region/, its effect is undocumented
            };

            if ( typeof config.api.language === 'string' ) {
                params.language = config.api.language;
            }

            if ( placeholder ) {
                params.placeholder = placeholder;
            }

            // A [wpsl country="..."] shortcode restriction overrides the
            // settings-page country restriction for this map instance.
            const restrictionCountry = helpers.search.getShortcodeCountry() || config.api.regions;
            if ( typeof restrictionCountry === 'string' && restrictionCountry ) {
                params.countries = restrictionCountry;
            }

            if ( typeof config.api.types !== 'undefined' ) {
                params.types = config.api.types;
            }

            const geocoder = new MapboxGeocoder( wp.hooks.applyFilters( 'wpslMapboxAutocompleteGeocoder', params ) );
            geocoder.addTo( '#mapbox-autocomplete' );

            jQuery( '#mapbox-autocomplete' ).addClass( 'wpsl-geocoder-active' ).show();

            geocoder.on( 'result', ( e ) => {
                if ( e.result?.geometry?.coordinates ) {
                    const coords = e.result.geometry.coordinates;
                    
                    slData.autoCompleteLatLng = {
                        lng: coords[0],
                        lat: coords[1]
                    };
                }

                if ( config.search.autoSubmitAutoComplete ) {
                    jQuery( '#wpsl-search-btn' ) .trigger( 'click' );
                }
            });

            this.setupSearchField();

            helpers.setupAutocompleteClickOutside( '#mapbox-autocomplete', '#mapbox-autocomplete .suggestions' );
        },

        /**
         * Setup the search field for autocomplete.
         *
         * @since  3.0.0
         * @return {void}
         */
        setupSearchField: function() {

            // A search widget submits the location as the value of
            // #wpsl-search-input. Removing that input below drops the value, so
            // the search button's non-empty-input guard bails and the widget
            // search never runs. Carry it over to the geocoder input.
            const submitted = jQuery( '#wpsl-search-input' ).val();

            jQuery( '#wpsl-search-input, .mapboxgl-ctrl-geocoder--pin-right, .mapboxgl-ctrl-geocoder--icon-search' ).remove();

            const $field = jQuery( '#mapbox-autocomplete .mapboxgl-ctrl-geocoder--input' ).attr( 'id', 'wpsl-search-input' );

            if ( submitted ) {
                $field.val( submitted );
            }

            buttons.bindSearch();

            // The geocoder replaced the plain input, so re-match the category
            // dropdown width to the ( now wider ) Mapbox field.
            helpers.template.alignSearchColumns();
        },
    }
};