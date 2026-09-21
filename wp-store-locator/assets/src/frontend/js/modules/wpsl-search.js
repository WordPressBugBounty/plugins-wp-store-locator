import { slData, config } from './wpsl-shared.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { helpers } from './wpsl-helpers.js';
import { responseHandlers } from './wpsl-response-handlers.js';
import { filters } from './wpsl-filters.js';
import { eventHandlers } from './wpsl-event-handlers.js';
import { preloader } from './wpsl-preloader.js';

/**
 * Search functionality for WPSL frontend
 * 
 * @since 3.0.0
 */
export const search = {
    /**
     * Collect the arguments for searches that are automatically submitted,
     * either on page load or when a filter ( dropdown / checkbox ) changes.
     *
     * @since 3.0.0
     * @returns {object} args The search arguments.
     * @todo type=name,category,country,state&search=pizza&filter=1&location=[0]['Canada,CA'][1]['British Columbia']
     */
    getAutoSubmitArgs: function() {
        let args = {
                action: 'store_search'
            };

        if ( slData.firstLoadInProgress ) {
            args.autoload = true;
        }

        // Make sure the geocode request restricts
        // the results to the selected country.
        if ( jQuery( '#wpsl-country' ).length || args.autoload ) {
            const dataValue = jQuery( '#wpsl-country .wpsl-selected-dropdown' ).text() + ',' + jQuery( '#wpsl-country .wpsl-selected-item' ).attr( 'data-value' );
            args = helpers.search.setSearchTypeArgs( args, 'country', dataValue );

            if ( ! jQuery( '#wpsl-search-input' ).val() ) {
                if ( typeof config.collectStatistics !== 'undefined' && ! slData.firstLoadInProgress ) {
                    const search = jQuery( '#wpsl-country .wpsl-selected-dropdown' ).text(),
                          statistics = {
                              country: jQuery( '#wpsl-country .wpsl-selected-dropdown' ).text()
                          };
                          
                    args.search = search;
                    args.statistics = statistics;
                }
            }
        }

        // Include the category data
        if ( jQuery( '#wpsl-category' ).length ) {
            args = helpers.search.includeCategorySearchType( args );
        }

        // Include data for all possible custom fields
        if ( jQuery( '[class*="wpsl-custom-"]' ).length ) {
            jQuery.extend( args, filters.getAllCustomValues() );
        }

        return args;
    },
    
    /**
     * Search on user input, or show all locations from the selected
     * category / checkbox(es) when there is no input.
     *
     * @param {object} args The filter ids and search type ( default category ).
     */
    autoSubmit: function( args ) {
        if ( jQuery( '#wpsl-search-input' ).val() ) {
            jQuery( '#wpsl-search-btn') .trigger( 'click' );
        } else {
            this.reset();
            this.makeAjaxRequest( wp.hooks.applyFilters( 'wpslAutoSubmitArgs', args )  );
        }
    },

    /**
     * Execute actions when a search is submitted.
     *
     * @since 3.0.0
     * @param {object} args Search arguments
     */
    submitActions: function( args ) {
        this.reset();

        slData.provider.api.geocoding.getLatLng( args );
    },

    /**
     * Add the start markers and check if we need to reverse
     * geocode the coordinates for the statistics or driving
     * directions before starting the search.
     *
     * @since 	3.0.0
     * @param	{object} args Search arguments
     * @returns {void}
     */
    prepare: function( args ) {
        search.reset();

        slData.provider.markers.add( args, slData.maps[0] );

        wp.hooks.doAction( 'wpslPrepareSearch', args );

        if ( helpers.search.maybeReverseGeocode() ) {
            slData.provider.api.geocoding.reverse( args, search.current( function() {
                search.run( args );
            }) );
        } else {
            search.run( args );
        }
    },

    /**
     * Make a new search.
     *
     * @since   3.0.0
     * @param   {object} args
     * @returns {void}
     */
    run: function( args) {
        const ajaxParams = this.createParams( args );

        this.makeAjaxRequest( ajaxParams, args );

        // The reset button restores the map to its page load state.
        if ( config.ux.resetMap ) {
            if ( jQuery.isEmptyObject( slData.viewport ) ) {
                helpers.map.setViewport( slData.maps[0], function() {
                    helpers.map.revealResetBtn();
                });
            }
        }

        // Move the cursor to the search field if the focus option is enabled.
        if ( config.ux.mouseFocus && ! helpers.isTouchPrimary() ) {
            jQuery( '#wpsl-search-input' ).trigger( 'focus' );
        }
    },

    /**
     * Create the parameters included in the AJAX request.
     *
     * @since	2.2.0
     * @param	{object} args 	  Data to include in the ajax request
     * @returns {object} ajaxData The collected data
     */
    createParams: function( args ) {
        let ajaxData = {
            action: 'store_search'
        };

        // args.fullSearch is set for a country / state search, which returns
        // every result for that country / state and so needs no coordinates.
        if ( typeof config.search.skipGeocode === 'undefined' ) {
            if ( typeof args.fullSearch === 'undefined' || typeof config.collectStatistics === 'string' ) {

                // Extract coordinates from various formats (Google Maps, Mapbox, direct properties).
                // No coordinates will exist if category only search is used.
                const coords = helpers.extractCoordinates( args );
                
                if ( coords ) {
                    ajaxData.lat = coords.lat;
                    ajaxData.lng = coords.lng;
                }
            }
        }

        // A map reset uses the default dropdown values instead of the selected ones.
        if ( typeof args.reset !== 'undefined' && args.reset ) {
            jQuery.extend( ajaxData, filters.dropdowns.getRestrictions( true ) );
        } else {
            if ( typeof args.fullSearch === 'undefined' ) {
                jQuery.extend( ajaxData, filters.dropdowns.getRestrictions() );
            }

            // Category ids set through the wpsl shortcode 
            // always win over the category dropdown or the checkboxes.
            if ( typeof config.search.categoryIds !== 'undefined' && config.search.categoryIds ) {
                ajaxData.filter = config.search.categoryIds;
            } else if ( helpers.hasCategoryFilter() ) {
                jQuery.extend( ajaxData, filters.dropdowns.getSelectedId() );
            } else if ( jQuery( '#wpsl-checkbox-filter' ).length > 0 ) {
                if ( jQuery( '#wpsl-checkbox-filter input:checked' ).length > 0 ) {
                    ajaxData.filter = filters.checkboxes.getSelectedIds();
                }
            }

            // Include values from custom dropdowns
            if ( jQuery( '.wpsl-custom-dropdown' ).length > 0 ) {
                ajaxData = filters.dropdowns.getCustomValues( ajaxData );
            }

            // Include values from custom checkboxes
            if ( jQuery( '.wpsl-custom-checkboxes' ).length > 0 ) {
                ajaxData = filters.checkboxes.getCustomValues( ajaxData );
            }
        }

        // If the name value is set to 'type', then it can be used to run
        // custom code to only return data from specific meta fields.
        if ( jQuery( '#wpsl-search-wrap input[type="hidden"]' ).length > 0 ) {
            ajaxData = filters.hidden.getCustomValues( ajaxData );
        }

        if ( jQuery( '.wpsl-custom-radiobuttons' ).length > 0 ) {
            ajaxData = filters.radio.getCustomValues( ajaxData );
        }

        if ( jQuery( '.wpsl-custom-input' ).length > 0 ) {
            ajaxData = filters.input.getCustomValues( ajaxData );
        }

        // A geolocated page load isn't cached: hardly two visitors share a
        // start point, so each would only add a transient nobody reads again.
        if ( args.autoLoad ) {
            if ( slData.geolocation.active ) {
                ajaxData.skip_cache = 1;
            } else {
                ajaxData.autoload = 1;

                // With the 'category' attr set on the wpsl shortcode, autoload
                // may only load locations from those categories.
                if ( typeof wpslSettings.categoryIds !== 'undefined' && wpslSettings.categoryIds ) {
                    ajaxData.filter = wpslSettings.categoryIds;
                }
            }
        }

        // Include the searched value when statistics
        // are collected, or when the input isn't geocoded.
        if ( ( typeof config.collectStatistics === 'string' && ! args.autoLoad && ! slData.geolocation.active && typeof args.reset === 'undefined' ) || config.search.skipGeocode ) {
            const searchValue = jQuery( '#wpsl-search-input' ).val();
            
            // For category-only searches (skipGeocode without input), set types=taxonomy and skip search param
            if ( config.search.skipGeocode && ! searchValue && ajaxData.filter ) {

                // Keep any type a custom filter ( e.g. country ) already added to types=.
                ajaxData.types = 'taxonomy' + ( ajaxData.types ? ',' + ajaxData.types : '' );
            } else if ( searchValue ) {
                ajaxData.search = searchValue;
                if ( config.search.namesEnabled ) {
                    ajaxData.types = 'name';
                }
            }

            // No address components will exist without geocoding
            if ( ! config.search.skipGeocode && typeof slData.statistics === 'object' ) {
                ajaxData.statistics = slData.statistics;

                slData.statistics = {};
            }
        }

        // Check if the user searched for a country / state.
        if ( typeof args.fullSearch !== 'undefined' ) {
            ajaxData = helpers.search.setSearchTypeArgs( ajaxData, args.fullSearch.type, args.fullSearch.query );
        }

        // Restrict the search to a country border ( enforce borders ) or to a
        // specific city / state / country set through the shortcode.
        ajaxData = this.checkRestrictionsParams( ajaxData, args );

        // Allow for custom params to the ajaxData using WordPress hooks
        ajaxData = wp.hooks.applyFilters( 'wpslCustomSearchParams', ajaxData, args );

        // Only return locations that are currently open
        if ( jQuery( '#wpsl-open-now' ).length > 0 && jQuery( '#wpsl-open-now' ).is( ':checked' ) ) {
            ajaxData.open_only = true;
        }

        // We don't always need to include the km / miles unit.
        if ( typeof ajaxData.types === 'undefined' || typeof ajaxData.types === 'string' && ajaxData.types == 'location' ) {
            ajaxData.distance_unit = config.search.distanceUnit;
        }

        return wp.hooks.applyFilters( 'wpslAjaxData', ajaxData );
    },

    /**
     * Check if we need to restrict the search
     * to a country border or a specific city
     * / state / country ( set through the shortcode ).
     *
     * @since  3.0.0
     * @param  {object} ajaxData The search params
     * @param  {object} args     Settings
     * @return {object} ajaxData The search params including possible restrictions
     */
    checkRestrictionsParams: function( ajaxData, args ) {
        const availableRestrictions = ['borders', 'city', 'state', 'country'];
        const restrictions = {};

        for ( const restrictionType of availableRestrictions ) {
            const configValue = config.search.restrictions[ restrictionType ];
            if ( typeof configValue !== 'undefined' ) {
                if ( restrictionType === 'borders' ) {

                    // Only include the values we actually found. Without
                    // this an autocomplete search ( which can lack the country
                    // code ) would send 'undefined,undefined' and the search
                    // would be filtered to a non-existent country.
                    const borderValues = [ args.countryCode, args.country ].filter( ( value ) => typeof value !== 'undefined' && value !== '' );

                    if ( borderValues.length ) {
                        restrictions[ restrictionType ] = borderValues.join( ',' );
                    }
                } else {
                    restrictions[ restrictionType ] = configValue;
                }
            }
        }

        if ( ! jQuery.isEmptyObject( restrictions ) ) {
            ajaxData.restrictions = restrictions;
        }

        return ajaxData;
    },

    /**
     * Make the AJAX request for a new location search.
     *
     * @since   3.0.0
     * @param   {object} ajaxData Coordinates and other relevant arguments ( active category / search radius / max results etc ).
     * @param   {object} searchArgs The unmodified search arguments the request was built from.
     * @returns {void}
     */
    makeAjaxRequest: function( ajaxData, searchArgs = {} ) {
        const map = slData.maps[0];

        let geoJSON = '',
            noResultsMsg,
            storeData = '';

        // A directions origin only exists for an actual location search
        // ( user input / geolocation ), which always carries coordinates.
        // Panel templates use this to hide the Directions link until such a
        // search has run.
        config.search.hasDirectionsOrigin = !! ( ajaxData.lat && ajaxData.lng ) && ! ajaxData.autoload;

        config.markers.draggable = false;

        preloader.add();

        jQuery( '.wpsl-api-message' ).remove();
        jQuery( '#wpsl-wrap' ).removeClass( 'wpsl-no-results' );

        /*
         * Only the newest search may touch the page: a slower older request
         * coming back last would replace the newer results. The earlier
         * request is aborted, and every callback still checks isCurrent(),
         * since an abort's own fail/always handlers run too.
         */
        const requestId = ++search.requestId;

        if ( search.activeRequest && typeof search.activeRequest.abort === 'function' ) {
            search.activeRequest.abort();
        }

        const isCurrent = function() {
            return requestId === search.requestId;
        };

        search.activeRequest = jQuery.ajax({
            type: 'GET',
            data: ajaxData,
            dataType: 'json',
            url: config.search.ajaxurl,
            success: function( response ) {
                if ( ! isCurrent() ) {
                    return;
                }

                if ( typeof response === 'object' && ! jQuery.isEmptyObject( response ) && typeof response.addon === 'undefined' ) {
                    if ( typeof response.success !== 'undefined' && ! response.success ) {
                        slData.$storeList.append( '<li>' + sharedHelpers.escapeHtml( response.data.error ) + '</li>' );

                        return false;
                    }
                    
                    // With listDetails on 'basic' the full response is saved,
                    // so extended store details can be shown when a user
                    // clicks a result.
                    if ( typeof config.search.listDetails === 'string' && config.search.listDetails == 'basic' ) {
                        slData.response = response;
                    }

                    // Mapbox will have a GeoJSON response
                    if ( typeof response.features === 'object' ) {
                        geoJSON  = response; // for the markers

                        // Online-only stores have no coordinates, so they arrive
                        // separately rather than as map features. Wrapping them
                        // like a feature and prepending them to the listing only
                        // keeps them out of the marker layer.
                        const onlineFeatures = Array.isArray( response.online )
                            ? response.online.map( function( store ) { return { properties: store }; } )
                            : [];

                        response = onlineFeatures.concat( response.features ); // for the template
                    }

                    // Compile the underscore templates once per response
                    // instead of once per returned store.
                    const listingTemplate = typeof slData.templates.listing !== 'undefined' ? _.template( slData.templates.listing ) : undefined;
                    const onlineTemplate  = typeof slData.templates.online !== 'undefined' ? _.template( slData.templates.online ) : undefined;

                    jQuery.each( response, function( index ) {
                        const locationDetails = typeof geoJSON === 'object' ? response[index].properties : response[index];

                        _.extend( locationDetails, helpers.template );

                        // Fall back to the configured distance unit.
                        if ( typeof locationDetails.distance !== 'undefined' && typeof locationDetails.distance_unit === 'undefined' ) {
                            locationDetails.distance_unit = wpslSettings.distanceUnit;
                        }

                        if ( typeof locationDetails.online !== 'undefined' && typeof onlineTemplate !== 'undefined' ) {
                            storeData = storeData + sharedHelpers.renderTemplate( onlineTemplate, locationDetails );
                        } else if ( typeof locationDetails.online === 'undefined' ) {

                            // Google Maps / Leaflet markers are added one by one.
                            if ( typeof geoJSON !== 'object' ) {
                                slData.provider.markers.add( response[index], map );
                            }

                            // Always set this, even for custom listing sections,
                            // because the custom template may still reference it.
                            locationDetails.hasMoreInfoData = helpers.results.hasMoreInfoData( locationDetails );

                            // Create the HTML output with help from underscore js.
                            storeData += sharedHelpers.renderTemplate( listingTemplate, locationDetails );
                        }
                    });

                    // For Mapbox we add all the marker data at once.
                    if ( typeof geoJSON === 'object' && typeof slData.provider.geojson !== 'undefined' ) {
                        slData.provider.geojson.add( geoJSON, map );
                    }

                    slData.$storeList.empty();
                    helpers.search.closeFiltersOnResults();
                    slData.$storeList.append( storeData );

                    // Adjust column class if result count is less than configured columns
                    helpers.search.adjustColumnClass( response );
                    helpers.search.numberResults( response );

                    if ( helpers.results.maybeUseBasicMode() ) {
                        helpers.results.hideDistance();
                    }

                    if ( config.markers.markerClusters && typeof slData.provider.markers.createCluster === 'function' ) {
                        slData.provider.markers.createCluster( map );
                    }

                    jQuery( '#wpsl-result-list p:empty' ).remove();

                    eventHandlers.bindHandlers();

                    // Reset skipGeocode if it was temporarily set for category-only search
                    if ( config.search.skipGeocode === true && ( typeof wpslSettings.search === 'undefined' || ! wpslSettings.search.skipGeocode ) ) {
                        delete config.search.skipGeocode;
                    }

                    wp.hooks.doAction( 'wpslAjaxResultsFound', response );
                } else {
                    noResultsMsg = search.getNoResultsMsg( ajaxData );

                    helpers.createUserNotice( noResultsMsg, 'geocode' );

                    if ( typeof wpslLabels.numberResults === 'string' ) {
                        jQuery( '.wpsl-number-results' ).remove();
                    }

                    // Bind the button that searches for the nearest location.
                    if ( noResultsMsg.indexOf( 'wpsl-search-nearest-btn' ) !== -1 ) {
                        eventHandlers.bindNearestBtn( ajaxData );
                    }

                    wp.hooks.doAction( 'wpslAjaxNoResultsFound', response );
                }

                // Geolocation can be inaccurate, so no
                // location statistics are collected for it.
                if ( slData.geolocation.active ) {
                    slData.geolocation.active = false;
                }

                // If basicMode is enabled ( so the user dragged the map
                // around to trigger a new search ), and there are no
                // results, then don't refocus the map.
                if ( slData.useBasicMode && typeof response === 'number' ) {
                    return;
                }

                // Either fit all markers in the viewport, or center the map
                // on the start marker / start location.
                const activeMarkerCount = helpers.getActiveMarkerCount();

                if ( config.map.fitBounds && activeMarkerCount > 1 ) {
                    slData.provider.markers.fitBounds();
                } else if ( config.map.fitBounds && ! searchArgs.restoreViewport ) {
                    helpers.map.setCenter();
                } else {
                    helpers.map.setDefaultViewport();
                }
            }
        }).fail( function( jqXHR, textStatus ) {
            // An aborted request was replaced by a newer one; that one reports.
            if ( ! isCurrent() || textStatus === 'abort' ) {
                return;
            }

            let message = wpslLabels.technicalProblem;

            /**
             * A 429 is the search rate limit, not an outage. Show the
             * server's message so the visitor knows to wait instead of
             * reporting the locator as broken. The body is the
             * wp_send_json_error() shape, the message sits in "data".
             */
            if ( jqXHR.status === 429 && jqXHR.responseJSON && typeof jqXHR.responseJSON.data === 'string' && jqXHR.responseJSON.data ) {
                message = sharedHelpers.escapeHtml( jqXHR.responseJSON.data ).replace( /\n/g, '<br><br>' );
            }

            jQuery( '#wpsl-stores' ).html( '<ul><li class="wpsl-no-results-msg">' + message + '</li></ul>' );
        }).always( function() {
            // The newer request owns the preloader now.
            if ( ! isCurrent() ) {
                return;
            }

            search.activeRequest = null;

            preloader.remove();

            jQuery( '#wpsl-search-btn' ).attr( 'disabled', false );

            if ( helpers.flexboxAvailable() ) {
                jQuery( '#wpsl-result-list' ).show();
            }
        });
    },

    /**
     * The number of the search request most recently sent. 
     * Callbacks of an older request compare against it and stand down.
     *
     * @since 3.0.0
     * @type {number}
     */
    requestId: 0,

    /**
     * Wrap a callback so it only runs while its search is still the newest.
     *
     * The store request already stands down when a newer one starts ( see
     * makeAjaxRequest() ), but the geocoding in front of it did not: type
     * one place, then another, and when the first geocode answered last it
     * started its own store request and replaced the newer results. So every
     * step that waits on a geocoder takes a number here when it starts,
     * and its callback does nothing once a newer search took one after it.
     * Anything that starts a store request counts as newer too, it bumps
     * the same counter.
     *
     * @since  3.0.0
     * @param  {Function} callback The work to do when the answer arrives.
     * @return {Function} The callback, guarded.
     */
    current: function( callback ) {
        const operation = ++search.requestId;

        return function() {
            if ( operation === search.requestId ) {
                return callback.apply( this, arguments );
            }
        };
    },

    /**
     * The jqXHR of the search in flight, or null.
     * Aborted when a newer search starts.
     *
     * @since 3.0.0
     * @type {object|null}
     */
    activeRequest: null,

    /**
     * Get the no results message based on the search type.
     *
     * @since  2.2.0
     * @param  {object} args      Search arguments
     * @return {string} noResults The no results found msg to show
     */
    getNoResultsMsg: function( args ) {
        let noResults;

        if ( typeof config.search.noResults !== 'undefined' && config.search.noResults ) {
            noResults = config.search.noResults;
        } else if ( typeof slData.nearestCoords !== 'undefined' && args.lat + ',' + args.lng == slData.nearestCoords ) {
            noResults = wpslLabels.noNearbyLocations;

            delete slData.nearestCoords;
        } else if ( typeof wpslLabels.findNearestLocations === 'string' && wpslLabels.findNearestLocations ) {
            noResults = wpslLabels.findNearestLocations;

            // Track the coordinates, so a second 'nearest search' that also
            // fails gets a different message without the retry option.
            slData.nearestCoords = args.lat + ',' + args.lng;

            slData.statistics = {};
        } else {
            noResults = wpslLabels.noResults;
        }

        return wp.hooks.applyFilters( 'wpslNoResultsMsg', noResults );
    },

    /**
     * Reset all elements before a search is made.
     *
     * @since   3.0.0
     * @returns {void}
     */
    reset: function() {
        jQuery( '#wpsl-result-list ul' ).empty();
        jQuery( '#wpsl-stores' ).show();
        jQuery( '.wpsl-direction-before, .wpsl-direction-after, .wpsl-number-results, #wpsl-pagination' ).remove();
        jQuery( '#wpsl-direction-details, #wpsl-pagination' ).hide();

        if ( helpers.flexboxAvailable() ) {
            jQuery( '#wpsl-panel .wpsl-search-wrap' ).removeClass( 'wpsl-error' );
            jQuery( '#wpsl-result-list, #wpsl-extended-list-details' ).hide();
        }

        // Force the open info window to close.
        helpers.results.maybeCloseInfoWindow();

        // Force street view to close ( gmaps only ) before a new search.
        if ( slData.provider.api.streetView && typeof slData.provider.api.streetView.close === 'function' ) {
            slData.provider.api.streetView.close();
        }

        // Without this the route polylines stay drawn on the map while the
        // new results are shown.
        if ( slData.directions.active ) {
            if ( slData.directionsPolylines && slData.directionsPolylines.length ) {
                slData.directionsPolylines.forEach( ( polyline ) => polyline.setMap( null ) );
                slData.directionsPolylines = [];
            }

            // Remove the origin / destination pin markers.
            if ( slData.provider.markers.directionStops && slData.provider.markers.directionStops.length ) {
                for ( let i = 0, len = slData.provider.markers.directionStops.length; i < len; i++ ) {
                    if ( typeof slData.provider.markers.directionStops[i].setMap === 'function' ) {
                        slData.provider.markers.directionStops[i].setMap( null );
                    }
                }

                slData.provider.markers.directionStops = [];
            }

            slData.directions.active = false;
            jQuery( '#wpsl-map' ).removeClass( 'wpsl-directions-active' );

            // The route is gone, so the shapes hidden for it come back.
            helpers.directions.setShapesVisible( true );
        }

        slData.provider.markers.removeAll();
    },

    /**
     * Show the map and results after the first search completes by removing
     * the 'wpsl-search-input-only' class.
     *
     * The hook unbinds itself on that first ajaxComplete, so it only affects
     * the initial search in input_only mode.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindAjaxComplete: function() {
        const hook = function() {
            jQuery( '#wpsl-wrap, .wpsl-provided-by' ).removeClass( 'wpsl-search-input-only' );

            // The map was created while its container was hidden, so Leaflet
            // cached a 0x0 size and deferred the first fitBounds().
            // invalidateSize() flushes both, and the grey tiles with it.
            setTimeout( function() {
                helpers.map.refreshMap( 0 );
            }, 100 );

            jQuery( document).off( 'ajaxComplete', hook );
        };

        jQuery( document ).on( 'ajaxComplete', hook );
    },

    /**
     * Set the search input value based on geocoding response.
     *
     * @since   3.0.0
     * @param   {object} args     The arguments object
     * @param   {object} response The response object
     * @returns {void}
     */
    maybeSetSearchInput: function( args, response ) {
        let responseFormat;

        if ( slData.useBasicMode || typeof slData.geolocation !== 'undefined' && slData.geolocation.newRequest || typeof args.markerDragged === 'boolean' || typeof slData.setSearchInput !== 'undefined' ) {
            if ( typeof args.markerDragged === 'boolean' ) {
                responseFormat = config.search.draggableFormat;
            } else if ( slData.useBasicMode ) {
                responseFormat = 'city';
            } else {
                responseFormat = config.search.autoLocate.format;
            }

            const filteredResponse = slData.provider.api.geocoding.filterResponse( response, wp.hooks.applyFilters( 'wpslResponseFormat', responseFormat ) );

            if ( ! jQuery.isEmptyObject( filteredResponse ) ) {
                const addressValue = filteredResponse[ Object.keys( filteredResponse )[0] ];
                const mapboxAutocompleteInput = jQuery( '#mapbox-autocomplete input' );
                
                if ( mapboxAutocompleteInput.length ) {
                    mapboxAutocompleteInput.val( addressValue );
                } else {
                    jQuery( '#wpsl-search-input' ).val( addressValue );
                }
            }

            if ( typeof slData.geolocation !== 'undefined' ) {
                slData.geolocation.newRequest = false;
            }

            delete slData.setSearchInput;
        }
    },

    /**
     * Check which error message to show for a failed search.
     *
     * There are two reasons why the AJAX request can fail.
     * - connection issues
     * - there is a problem with the API key ( too many request, expired, blocked etc ).
     *
     * A successful request that returned nothing shows the 'no results found' text.
     *
     * @since   3.0.0
     * @param   {*}    response
     * @returns {void}
     */
    errorHandler: function( response ) {
        if ( typeof response.responseJSON === 'object' ) {
            responseHandlers.maybeShowResponseText( response );
        } else {
            helpers.createUserNotice( wpslLabels.retrySearch, 'geocode' );
        }
    },
};