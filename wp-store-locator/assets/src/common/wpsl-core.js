/**
 * Shared core: GDPR module registration and the API request object used to
 * talk to Google Maps, Nominatim, and Mapbox.
 *
 * @since 3.0.0
 */

/**
 * The GDPR module, handed over by the frontend entry point.
 *
 * This file is shared with the admin bundles, so it cannot import the frontend
 * GDPR module itself: that would pull the whole frontend module graph into the
 * admin build. Registering keeps a single instance too - a second copy would
 * leave setup.init() acting on state ( slData / config ) nothing else can see.
 *
 * @since 3.0.0
 * @type {Object|null}
 */
let gdprModule = null;

/**
 * Register the GDPR module. Called by the frontend entry point.
 *
 * @since   3.0.0
 * @param   {Object} module The gdpr module
 * @returns {void}
 */
export const setGdprModule = function( module ) {
    gdprModule = module;
};

/**
 * Get the registered GDPR module, or null on the admin side.
 *
 * @since   3.0.0
 * @returns {Object|null}
 */
export const getGdprModule = function() {
    return gdprModule;
};

/**
 * The API request object used to talk to Google Maps, Nominatim, and Mapbox.
 *
 * @since   3.0.0
 * @returns {Object} The API request object
 */
export const createApiRequest = {
    library: {}, // holds the loaded libraries

    /**
     * Hold back the Google Maps conflict notice.
     *
     * Set while the settings page verifies API keys: the bootloader wipes
     * window.google and reloads from scratch, so the conflict can't affect the
     * result and the notice would only contradict it. Reported on page load
     * instead, where it does break the preview map and address autocomplete.
     *
     * @since 3.0.0
     * @type {boolean}
     */
    suppressConflictNotice: false,

    /**
     * Get the active map provider.
     *
     * On the settings page the dropdown value wins, since the user can switch
     * providers without saving. Elsewhere it's the value set on page load.
     *
     * @since  3.0.0
     * @return {string} The active map provider
     */
    getActiveProvider: function() {
        let activeProvider = wpslSettings.api.provider;

        const isAdmin = document.body.classList.contains( 'wp-admin' );
        if ( isAdmin ) {
            const $selectedProvider = jQuery( '#wpsl-map-service' );
            if ( $selectedProvider.length > 0 ) {
                activeProvider = $selectedProvider.find( ':selected' ).val();
            }
        }

        return activeProvider;
    },

    /**
     * Check if we need to load API libraries.
     *
     * @since 3.0.0
     * @param {object} additionalLibs Optional additional libraries to load.
     * @param {function} callback
     */
    maybeLoadLibraries: async function( additionalLibs, callback ) {
        const activeProvider = this.getActiveProvider();
        const mapProvider = this[ activeProvider ];

        const gdpr = getGdprModule();
        if ( gdpr && wpslSettings.gdpr == 'wpsl' && ! document.body.classList.contains( 'wp-admin' ) ) {
            await new Promise( resolve => gdpr.scripts.load( activeProvider, resolve ) );
            await this.checkLibraryImport( mapProvider, additionalLibs, () => {
                callback();
            });
        } else {
            await this.checkLibraryImport( mapProvider, additionalLibs, () => {
                callback();
            });
        }
    },

    /**
     * Check if the map provider has a library import method and execute it.
     *
     * @since 3.0.0
     * @param {object} mapProvider The map provider object (gmaps, osm, mapbox)
     * @param {object} additionalLibs Optional additional libraries to load
     * @param {Function} callback Callback function to execute after import
     */
    checkLibraryImport: async function( mapProvider, additionalLibs, callback ) {
        if ( typeof mapProvider.importRequiredLibraries === 'function' ) {
            await mapProvider.importRequiredLibraries( additionalLibs, function() {
                callback();
            });
        } else {
            callback();
        }
    },

    /**
     * Monitor for errors / warnings from the browser console
     * and check if they are related to API request we make.
     *
     * @since 3.0.0
     * @param {boolean} start Whether to start (true) or stop (false) monitoring
     */
    monitorConsoleOutput: function( start = true ) {
        const activeProvider = this.getActiveProvider();
        const mapProvider = this[ activeProvider ];

        // Store original console methods if we haven't already
        if ( ! this._originalConsole ) {
            this._originalConsole = {
                warn: console.warn,
                error: console.error
            };
        }

        //@todo split between admin and front-end.
        if ( start && typeof mapProvider.handleError === 'function' ) {
            console.error = ( message, ...rest ) => {
                mapProvider.handleError( message );
                this._originalConsole.error( message, ...rest );
            };
        } else if ( ! start && this._originalConsole ) {
            console.error = this._originalConsole.error;
        }

        if ( start && typeof mapProvider.handleWarnings === 'function' ) {
            console.warn = ( message, ...rest ) => {
                mapProvider.handleWarnings( message );
                this._originalConsole.warn( message, ...rest );
            };
        } else if ( ! start && this._originalConsole ) {
            console.warn = this._originalConsole.warn;
        }

        /**
         * Failures inside the Maps library import arrive as rejected promises,
         * which the browser reports without going through console.error. Listen
         * for them separately so they reach handleError as well.
         */
        if ( start && typeof mapProvider.handleError === 'function' ) {
            if ( ! this._rejectionHandler ) {
                this._rejectionHandler = ( event ) => {
                    const reason = event.reason;

                    mapProvider.handleError( typeof reason === 'string' ? reason : ( reason && reason.message ) || '' );
                };

                window.addEventListener( 'unhandledrejection', this._rejectionHandler );
            }
        } else if ( ! start && this._rejectionHandler ) {
            window.removeEventListener( 'unhandledrejection', this._rejectionHandler );
            this._rejectionHandler = null;
        }

        /**
         * The bootstrap loaders flag a conflict before these wrappers exist, so
         * the "only loads once" warning is never captured. Report anything
         * already flagged now that monitoring has started.
         */
        if ( start && window.wpslGmapsConflict && typeof mapProvider.showConflictNotice === 'function' ) {
            mapProvider.showConflictNotice();
        }
    },

    /**
     * Stop monitoring console output and restore original console methods.
     *
     * @since 3.0.0
     */
    stopMonitoringConsole: function() {
        this.monitorConsoleOutput( false );
    },

    /**
     * Google Maps API requests.
     *
     * @since 3.0.0
     */
    gmaps: {
        consoleNoticeVisible: false,
        geocoder: '',
        /**
         * Initialize the Google Maps provider.
         *
         * @since 3.0.0
         */
        init: function() {
            wp.hooks.doAction('wpslMapProviderInit', 'gmaps');
        },

        /**
         * Show the notice explaining that another plugin loaded Google Maps
         * before the store locator could.
         *
         * Called from three directions: monitorConsoleOutput() for a conflict
         * the bootstrap loaders flagged on window.wpslGmapsConflict before the
         * console wrappers existed, and handleError() / handleWarnings() when
         * that flag is still set once another API error is reported.
         *
         * @since   3.0.0
         * @returns {boolean} Whether the notice was rendered.
         */
        showConflictNotice: function() {
            if ( this.consoleNoticeVisible || createApiRequest.suppressConflictNotice ) {
                return false;
            }

            const errorMessages = ( typeof wpslApiErrors.gmaps !== 'undefined' ) ? wpslApiErrors.gmaps : wpslApiErrors;
            if ( ! errorMessages.conflictDetected ) {
                return false;
            }

            this.consoleNoticeVisible = true;

            const isAdminOrOnboarding = document.body.classList.contains( 'wp-admin' ) || document.body.classList.contains( 'wpsl-onboarding' );
            if ( isAdminOrOnboarding ) {

                /**
                 * The settings page reports this through the alerts list
                 * instead ( see wpsl-admin.js ), where the copy can point at
                 * the Tools tab.
                 */
                if ( jQuery( '#wpsl-onboarding-start-location' ).length ) {
                    jQuery( '#wpsl-onboarding-map' ).before( '<div class="wpsl-api-message"><p>' + errorMessages.conflictDetected + '</p></div>' );
                }
            } else if ( jQuery( '#wpsl-wrap' ).length ) {
                jQuery( '#wpsl-wrap' ).addClass( 'wpsl-api-message-wrap' );
                jQuery( '.wpsl-search' ).html( '<p>' + errorMessages.conflictDetected + '</p>' );
            }

            return true;
        },

        /**
         * Show the returned error messages
         * with a link to the related documentation.
         *
         * @param {string} message The error message captured from the developers console.
         */
        handleError: function( message ) {
            const urlRegex = /http(s)?:\/\/[^\s]+/gm;

            if ( ! message || typeof message !== 'string' ) {
                return;
            }

            if ( this.consoleNoticeVisible ) {
                return;
            }

            const errorMessages = ( typeof wpslApiErrors.gmaps !== 'undefined' ) ? wpslApiErrors.gmaps : wpslApiErrors;

            // If another plugin loaded Google Maps first, show a
            // conflict notice instead of the standard API error.
            if ( window.wpslGmapsConflict && this.showConflictNotice() ) {
                return;
            }

            let url = '',
                error = '',
                noticeDetails = '';

            // Look for API errors that include error codes,
            // billing related errors or quota related errors.
            if ( message.match( /Google Maps JavaScript API error:/ ) ) {
                message = message.split( 'error:' );
                const matches = message[1] ? message[1].match( /^(.+)\s+(http(s?):\/\/.+)/m ) : null;
                if ( matches ) {
                    url   = matches[2];
                    error = matches[1].trim();
                }
            } else if ( message.match( /enable billing/i ) ) {
                const matches = message.match( urlRegex );
                if ( matches ) {
                    url = matches[0];
                }

                error = errorMessages.enableBilling;

                // Show Google's billing message unmodified ( minus any "Service:" /
                // "error:" prefix ), with its URLs turned into clickable links.
                let billingText = message;
                const colonIndex = billingText.indexOf( ': ' );

                if ( colonIndex !== -1 && /enable billing/i.test( billingText.slice( colonIndex + 2 ) ) ) {
                    billingText = billingText.slice( colonIndex + 2 );
                }

                const billingNode = document.createElement( 'span' );
                billingNode.textContent = billingText;
                let billingHtml = billingNode.innerHTML;

                if ( matches ) {
                    matches.forEach( ( matchUrl ) => {
                        const urlNode = document.createElement( 'span' );
                        urlNode.textContent = matchUrl;
                        const safeUrlText = urlNode.innerHTML;

                        billingHtml = billingHtml.split( safeUrlText ).join(
                            '<a target="_blank" href="' + encodeURI( matchUrl ) + '">' + safeUrlText + '</a>'
                        );
                    });
                }

                noticeDetails = '<p>' + billingHtml + '</p>';
            } else if ( message.match( /^(?=.*exceeded)(?=.*quota).*$/im ) ) {
                message = message.split( '. ' );
                const matches = message[1] ? message[1].match( urlRegex ) : null;
                if ( matches ) {
                    url   = matches[0];
                    error = message[0];
                }
            } else if ( message.match( /Geocoding Service/i ) ) {
                message = message.split( 'Geocoding Service:' );
                const matches = message[1] ? message[1].match( /^(.+)\s+(http(s?):\/\/.+)/m ) : null;
                if ( matches ) {
                    url   = matches[2];
                    error = matches[1].trim();
                }
            }

            if ( url && error && url.indexOf( 'http' ) === 0 && error.length ) {
                const documentationUrl = '<a target="_blank" href=' + url + '>' + error + '</a>';

                // Use the unmodified message ( billing ) when available, otherwise the
                // captured error text linked to the documentation URL.
                const primaryDetails = noticeDetails ? noticeDetails : '<p>' + documentationUrl + '</p>';

                this.consoleNoticeVisible = true;

                /*
                 * The apiDescribed label carries a %s placeholder marking the
                 * word that links to the documentation. A translation without
                 * the placeholder gets the link appended after the sentence.
                 */
                const wpslDocLink = '<a href="https://wpstorelocator.co/document/create-google-api-keys">' + errorMessages.wpslDoc + '</a>';
                const apiDescribed = '<p>' + ( errorMessages.apiDescribed.includes( '%s' )
                    ? errorMessages.apiDescribed.replace( '%s', wpslDocLink )
                    : errorMessages.apiDescribed + ' ' + wpslDocLink + '.' ) + '</p>';

                const isAdminOrOnboarding = document.body.classList.contains( 'wp-admin' ) || document.body.classList.contains( 'wpsl-onboarding' );
                if ( isAdminOrOnboarding ) {
                    import( '../admin/js/modules/wpsl-helpers.js' ).then( ( { helpers } ) => {
                        helpers.errors.console.maybePrepareResponse( error, url, noticeDetails );
                    });

                    if ( jQuery( '#wpsl-onboarding-start-location' ).length ) {
                        jQuery( '#wpsl-onboarding-map' ).before( '<div class="wpsl-api-message">' + primaryDetails + apiDescribed + '</div>' );
                    }
                } else if ( jQuery( '#wpsl-wrap' ).length ) {
                    jQuery( '#wpsl-wrap' ).addClass( 'wpsl-api-message-wrap' );
                    jQuery( '.wpsl-search' ).html( primaryDetails + apiDescribed );
                }
            }
        },

        /**
         * Handle warning messages from the Google Maps API.
         *
         * @since 3.0.0
         * @param {string} message The warning message captured from the console
         */
        handleWarnings: function( message ) {
            if ( ! message ) {
                return;
            }

            // If the bootstrap loader detected another plugin already
            // loaded Google Maps, show the conflict notice immediately.
            if ( window.wpslGmapsConflict ) {
                this.showConflictNotice();

                return;
            }

            if ( ! document.body.classList.contains( 'wp-admin' ) ) {
                return;
            }

            if ( message.match( /Google Maps JavaScript API/ ) && message.match( /warning:/ ) ) {
                if ( this.consoleNoticeVisible ) {    
                    return;
                }
                    
                message = message.split( 'warning:' );
                const matches = message[1].match( /^(.+)\s+(http(s?):\/\/.+)/m ),
                    url = matches[2],
                    error = matches[1].trim();

                import( '../admin/js/modules/wpsl-helpers.js' ).then( ( { helpers } ) => {
                    helpers.errors.console.maybePrepareResponse( error, url );
                });

                this.consoleNoticeVisible = true;
            }
        },

        /**
         * Make sure the required libraries are imported.
         *
         * The 'core', 'maps', 'geocoding' and 'marker' libraries are always
         * imported, additionalLibs adds to them.
         *
         * @since  3.0.0
         * @see    https://developers.google.com/maps/documentation/javascript/libraries
         * @param  {object} additionalLibs
         * @param  {function} callback
         * @return {Promise<void>}
         */
        importRequiredLibraries: async function( additionalLibs, callback ) {
            // Copied: pushing into the localized array itself would grow
            // wpslSettings.api.libraries by the extras on every call.
            let libraries = wpslSettings.api.libraries.slice();

            if ( typeof additionalLibs === 'object' ) {
                jQuery.each( additionalLibs, function( key, value ) {
                    libraries.push( value );
                });
            }

            for ( const key of Object.keys( libraries ) ) {
                importedLibraries[ libraries[key] ] = await google.maps.importLibrary( libraries[key] );
            }

            wp.hooks.doAction( 'wpslLoadRequiredLibraries', libraries );

            callback();
        },

        /**
         * Import a single library when required.
         *
         * @since  3.0.0
         * @param  {string} name Library name we need to load
         * @return {Promise<void>}
         */
        importLibrary: async function( name ) {
            if ( ! importedLibraries[ name ] ) {
                importedLibraries[ name ] = await google.maps.importLibrary( name );
            }

            return importedLibraries[ name ];
        },

        /**
         * Make geocode / reverse geocode requests to the Google Maps API.
         *
         * Geocode requests -> 'address: startLocation' ( as in address / city / state / country )
         * Reverse requests -> 'location: latLng'
         *
         * @since 3.0.0
         * @see   https://developers.google.com/maps/documentation/javascript/geocoding#GeocodingRequests &
         *        https://developers.google.com/maps/documentation/javascript/geocoding#ReverseGeocoding
         * @param {object} args data for the Geocode API.
         * @param {function} callback
         */
        geocode: function( args, callback ) {
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode( wp.hooks.applyFilters( 'wpslMakeGeocodeRequestArgs', args ), function( response, status ) {
                callback( response, status );
            });
        },

        /**
         * Make a request to the Route API.
         *
         * @since 3.0.0
         * @note  requires that the 'routes' library is loaded.
         * @see   https://developers.google.com/maps/documentation/javascript/directions
         * @param {object} args The origin and destination coordinates.
         * @param {function} callback
         */
        directions: async function( args, callback ) {
            const { Route } = importedLibraries.routes;

            const routeArgs = wp.hooks.applyFilters( 'wpslMakeDirectionsRequestArgs', {
                origin:      args.origin,
                destination: args.destination,
                travelMode:  args.travelMode,
                units:       args.unitSystem === google.maps.UnitSystem.METRIC ? google.maps.UnitSystem.METRIC : google.maps.UnitSystem.IMPERIAL,
                fields:      [ 'legs' ],
            });

            try {
                const { routes } = await Route.computeRoutes( routeArgs );
                callback( routes, null );
            } catch ( error ) {
                const message = ( error && error.message ) ? error.message : String( error );

                /*
                 * The Routes API has not been enabled for the project. Google's
                 * error spells this out ("...has not been used in project... or
                 * it is disabled"), so we show a link to enable it.
                 */
                if ( message.includes( 'PERMISSION_DENIED' ) && message.includes( 'Routes API' ) && ( message.includes( 'disabled' ) || message.includes( 'has not been used' ) ) ) {
                    const projectMatch = message.match( /project=(\d+)/ );
                    const projectId = projectMatch ? projectMatch[1] : '';
                    const apiUrl = projectId ? `https://console.developers.google.com/apis/api/routes.googleapis.com/overview?project=${projectId}` : 'https://console.cloud.google.com/apis/library/routes.googleapis.com';

                    error.isRoutesApiDisabled = true;
                    error.apiUrl = apiUrl;
                } else if ( /PERMISSION_DENIED|API_KEY|referer|referrer|\bblocked\b|forbidden|403/i.test( message ) ) {
                    /*
                     * A 403 from routes.googleapis.com that is not an "API disabled"
                     * error is almost always an API key restriction (API or
                     * website/referrer restriction) blocking the Routes API.
                     */
                    error.isRoutesApiRestricted = true;

                    console.error(
                        '[WP Store Locator] The Google Routes API request was rejected (403 Forbidden / PERMISSION_DENIED).\n' +
                        'This usually means the Google Maps API key has API or website (HTTP referrer) restrictions that block the Routes API.\n' +
                        'To fix it: open Google Cloud Console -> APIs & Services -> Credentials -> select your API key, then under ' +
                        '"API restrictions" make sure "Routes API" is allowed (or set the key to "Don\'t restrict key"), and confirm this ' +
                        'site is listed under the "Website restrictions".\n' +
                        'Original error: ' + message
                    );
                }

                callback( null, error );
            }
        },

        helpers: {
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
             * Check if the API response is 'OK'.
             *
             * @since  3.0.0
             * @param  {string} responseStatus
             * @return {boolean}
             */
            isValidResponse: function( responseStatus ) {
                return responseStatus === 'OK';
            },

            /**
             * Filter out the lat / lng coordinates from the
             * Google Geocode API response.
             *
             * @since  3.0.0
             * @param  {object} response API response
             * @return {{lng: *, lat: *}}
             */
            getResponseLatLng: function( response ) {
                if ( response?.[0]?.geometry?.location ) {
                    return {
                        lat: response[0].geometry.location.lat(),
                        lng: response[0].geometry.location.lng()
                    };
                }

                return undefined;
            }
        }
    },
    /**
     * Nominatim / OpenStreetMaps API requests.
     *
     * @since 3.0.0
     */
    osm: {
        /**
         * Initialize the OpenStreetMap provider.
         *
         * @since 3.0.0
         */
        init: function() {
            wp.hooks.doAction('wpslMapProviderInit', 'osm');
        },
        
        /**
         * Make a geocode request the Nominatim Geocode API.
         *
         * @param {object} args
         * @param {function} callback
         */
        geocode: function( args, callback ) {
            let type = 'search';

            if ( typeof args.lon !== 'undefined' ) {
                type = 'reverse';
            }

            jQuery.get( 'https://nominatim.openstreetmap.org/' + type, wp.hooks.applyFilters( 'wpslMakeGeocodeRequestArgs', args ), function( response ) {
                callback( response );
            }).fail( function( data ) {
                callback( data );
            });
        },

        /**
         * Request the directions from the Openrouteservice API.
         *
         * @see   https://openrouteservice.org/
         * @param {object} args
         * @param {function} callback
         */
        directions: function( args, callback ) {
            jQuery.get( wpslSettings.search.ajaxurl, wp.hooks.applyFilters( 'wpslMakeDirectionsRequestArgs', args ), function( response ) {
                callback( response );
            }).fail( function( data ) {
                callback( data );
            });
        },

        helpers: {
            /**
             * Create a new latLng object.
             *
             * @see     https://leafletjs.com/reference.html#latlng
             * @param   {string} lat Latitude values
             * @param   {string} lng Longitude values
             * @returns {object}
             */
            createLatLngObj: function( lat, lng ) {
                return L.latLng( lat, lng );
            },

            /**
             * Filter out the formatted address from the
             * Nominatim API response.
             *
             * @since  3.0.0
             * @param  {object} response
             * @return {string} formatedAddress
             */
            getFormattedAddress: function( response ) {
                const format = [ 'road', 'town', 'village', 'city', 'country' ];

                if ( typeof response.address === 'object' ) {
                    const adressParts = Object.entries( response.address )
                        .filter( ( [ key ] ) => format.includes( key ) )
                        .map( ( [ , value ] ) => value );
                    
                    if ( adressParts.length > 0 ) {
                        return adressParts.join( ', ' );
                    }
                }

                return response.display_name || '';
            },

            /**
             * Filter out the lat / lng coordinates from 
             * the Nominatim Geocode API response.
             *
             * @param  {object} response The API response
             * @return {{lng, lat}}
             */
            getResponseLatLng: function( response ) {
                response = createApiRequest.helpers.checkResponseStructure( response );

                if ( response.lat && response.lon ) {
                    return {
                        lat: Number( response.lat ),
                        lng: Number( response.lon )
                    };
                }

                return undefined;
            },
        }
    },
    stadia: {
        consoleNoticeVisible: false,
        /**
         * Initialize the Stadia Maps provider.
         *
         * Stadia uses Leaflet with Stadia tile layers, its own
         * Geocoding Search API, and Valhalla-based routing API.
         *
         * @since 3.0.0
         */
        init: function() {
            wp.hooks.doAction( 'wpslMapProviderInit', 'stadia' );
        },

        /**
         * Make a geocode / reverse geocode request to the Stadia Maps Geocoding API.
         *
         * Search requests -> 'text: searchQuery' (or 'q' for compatibility)
         * Reverse requests -> 'point.lat' and 'point.lon'
         *
         * @see   https://docs.stadiamaps.com/geocoding-search/search/
         * @param {object} args
         * @param {function} callback
         */
        geocode: function( args, callback ) {
            let url, params = {};

            // Reverse geocode request
            if ( typeof args.lat !== 'undefined' && typeof args.lon !== 'undefined' ) {
                // Use the v2 reverse endpoint (shared v2 GeoJSON response shape parsed by parseStadiaFeature).
                url = ( wpslSettings.api.euEndpoints ? 'https://api-eu.stadiamaps.com' : 'https://api.stadiamaps.com' ) + '/geocoding/v2/reverse';
                params = {
                    'point.lat': args.lat,
                    'point.lon': args.lon,
                    size: 1
                };
            } else {
                // Forward search request
                url = ( wpslSettings.api.euEndpoints ? 'https://api-eu.stadiamaps.com' : 'https://api.stadiamaps.com' ) + '/geocoding/v2/search';

                // Handle both 'q' (Nominatim-style) and 'text' (Pelias-style) params
                if ( typeof args.text !== 'undefined' ) {
                    params.text = args.text;
                } else if ( typeof args.q !== 'undefined' ) {
                    params.text = args.q;
                }

                params.size = 1;

                // params is rebuilt from scratch above, so anything the caller
                // set that isn't copied over here is silently dropped.
                //
                // Stadia expects the dotted 'boundary.country' name with ISO
                // 3166-1 alpha-2 / alpha-3 codes: an unknown param name is
                // ignored without an error, an invalid code returns HTTP 400.
                const boundaryCountry = args['boundary.country'] || args.boundary_country;

                if ( boundaryCountry ) {
                    params['boundary.country'] = boundaryCountry;
                }
            }

            // Add API key if available
            if ( typeof wpslSettings !== 'undefined' && wpslSettings.api && wpslSettings.api.key ) {
                params.api_key = wpslSettings.api.key;
            }

            // Add language if set. An explicit args.lang wins so the settings
            // page can test a language that hasn't been saved yet.
            if ( args.lang ) {
                params.lang = args.lang;
            } else if ( typeof wpslSettings !== 'undefined' && wpslSettings.api && wpslSettings.api.language ) {
                params.lang = wpslSettings.api.language;
            }

            jQuery.get( url, wp.hooks.applyFilters( 'wpslMakeGeocodeRequestArgs', params ), function( response ) {
                callback( response );
            }).fail( function( data ) {
                // Which endpoint was refused, for the admin-only notice. The key
                // rides in params rather than the URL, so this leaks nothing.
                data.wpslRequestUrl = url;

                callback( data );
            });
        },

        /**
         * Request directions from the Stadia Maps Routing API (Valhalla).
         *
         * @see   https://docs.stadiamaps.com/routing/standard-routing/
         * @param {object} args The AJAX data including action, start, end
         * @param {function} callback
         */
        directions: function( args, callback ) {
            jQuery.get( wpslSettings.search.ajaxurl, wp.hooks.applyFilters( 'wpslMakeDirectionsRequestArgs', args ), function( response ) {
                callback( response );
            }).fail( function( data ) {
                callback( data );
            });
        },

        helpers: {
            /**
             * Create a new latLng object using Leaflet.
             *
             * @see     https://leafletjs.com/reference.html#latlng
             * @param   {string} lat Latitude value
             * @param   {string} lng Longitude value
             * @returns {object}
             */
            createLatLngObj: function( lat, lng ) {
                return L.latLng( lat, lng );
            },

            /**
             * Filter out the formatted address from the
             * Stadia Maps Geocoding API (Pelias) response.
             *
             * @since  3.0.0
             * @param  {object} response GeoJSON FeatureCollection
             * @return {string} Formatted address label
             */
            getFormattedAddress: function( response ) {
                if ( response && response.features && response.features[0] ) {
                    const props = response.features[0].properties || {};

                    // v2 exposes the single-line address as formatted_address_line;
                    // this is the one intentional inline v2 field read (wpsl-core.js
                    // does not import the wpsl-stadia.js normalizer).
                    return props.formatted_address_line || props.name || '';
                }

                return '';
            },

            /**
             * Filter out the lat / lng coordinates from
             * the Stadia Maps Geocoding API (Pelias) response.
             *
             * @since  3.0.0
             * @param  {object} response GeoJSON FeatureCollection
             * @return {{lng, lat}|undefined}
             */
            getResponseLatLng: function( response ) {
                if ( response && response.features && response.features[0] ) {
                    const coords = response.features[0].geometry.coordinates;
                    if ( coords && coords.length === 2 ) {
                        return {
                            lat: coords[1],
                            lng: coords[0]
                        };
                    }
                }

                return undefined;
            },
        }
    },
    mapbox: {
        /**
         * Initialize the Mapbox provider.
         *
         * @since 3.0.0
         */
        init: function() {
            wp.hooks.doAction( 'wpslMapProviderInit', 'mapbox' );
        },

        /**
         * Handle geocode requests to the Mapbox API
         *
         * @since 3.0.0
         * @see   https://docs.mapbox.com/api/search/geocoding/#reverse-geocoding
         * @see   https://docs.mapbox.com/api/search/geocoding/#forward-geocoding
         * @param {object} param Geocode request arguments.
         * @param {function} callback
         */
        geocode: function( param, callback ) {
            if ( typeof param.searchText === 'string' ) {
                param.path = 'forward?q={' + encodeURIComponent( param.searchText.replace( ';', ' ' ) ) + '}';
            }

            const url = 'https://api.mapbox.com/search/geocode/v6/' + param.path + '&access_token=' + param.accessToken + '&limit=1';

            jQuery.get( url, function( response ) {
                callback( response );
            }).fail( function( data ) {
                callback( data );
            });
        },

        /**
         * Request the directions from the Mapbox API.
         *
         * @since 3.0.0
         * @see   https://docs.mapbox.com/help/glossary/directions-api/
         * @param {object} args
         * @param {function} callback
         */
        directions: async function( args, callback ) {
            const optionalArgs = 'steps=true&geometries=geojson';
            const query = await fetch(
                'https://api.mapbox.com/directions/v5/mapbox/' + args.profile + '/' + args.coordinates + '?' + wp.hooks.applyFilters( 'wpslMakeDirectionsRequestArgs', optionalArgs ) +'&access_token=' + args.accessToken,
                { method: 'GET' }
            );

            const json = await query.json();
            const response = {
                query: query,
                json: json
            };

            callback( response );
        },

        helpers: {
            /**
             * Filter out the lat / lng coordinates from the
             * Mapbox Geocode API response.
             *
             * @param  {object} response The API response
             * @return {{lng, lat}}
             */
            getResponseLatLng: function( response ) {
                response = createApiRequest.helpers.checkResponseStructure( response );
                if ( response.features?.length > 0 ) {
                    const coords = response.features[0].geometry.coordinates;
                    return {
                        lat: coords[1],
                        lng: coords[0]
                    };
                }

                return undefined;
            }
        }
    },
    helpers: {
        /**
         * Get the response status based on the active provider.
         *
         * Google Maps returns status separately, other providers include it in response.
         *
         * @since 3.0.0
         * @param {object} response The API response
         * @param {string} status The status (used for Google Maps)
         * @return {string|object} The response status
         */
        getResponseStatus: function( response, status ) {
            const activeProvider = createApiRequest.getActiveProvider();
            return activeProvider === 'gmaps' ? status : response;
        },

        /**
         * Check and normalize the response structure.
         *
         * Some APIs return an array, others return an object directly.
         *
         * @since 3.0.0
         * @param {object|array} response The API response
         * @return {object} The normalized response object
         */
        checkResponseStructure: function( response ) {
            return Array.isArray( response ) && response[0] ? response[0] : response;
        },

        /**
         * Check if the response from the API included the expected data.
         *
         * Used by Mapbox / Nominatim, Google Maps has its own check.
         *
         * @since 3.0.0
         * @param {object|array} response The API response
         * @return {boolean}
         */
        isValidResponse: function( response ) {
            const activeProvider = createApiRequest.getActiveProvider();
            const mapProvider = createApiRequest[ activeProvider ];
            if ( typeof mapProvider.helpers.isValidResponse === 'function' ) {
                return mapProvider.helpers.isValidResponse( response );
            }
            
            response = this.checkResponseStructure( response );
            return response && Object.keys( response ).length > 0;
        },

        /**
         * Filter out the formatted address from the API response.
         *
         * @since  3.0.0
         * @param  {object} response
         * @return {string} formatedAddress
         */
        getFormattedAddress: function( response ) {
            const activeProvider = createApiRequest.getActiveProvider();
            const mapProvider = createApiRequest[ activeProvider ];

            if ( typeof mapProvider.helpers.getFormattedAddress === 'function' ) {
                return mapProvider.helpers.getFormattedAddress( response );
            }
            
            // Google Maps
            if ( response[0]?.formatted_address ) {
                return response[0].formatted_address;
            }
            
            // Mapbox
            if ( response.features?.[0]?.place_name ) {
                return response.features[0].place_name;
            }
            
            // Nominatim / OSM
            if ( response.display_name ) {
                return response.display_name;
            }
            
            return '';
        }
    },

    /**
     * Get the active map provider object.
     *
     * @since 3.0.0
     * @return {object} The active provider object (gmaps, osm, or mapbox)
     */
    getActive: function() {
        const activeProvider = this.getActiveProvider();
        return this[ activeProvider ];
    },
};

/**
 * Check if the API key for the specified map provider exists and is valid.
 * If not, display a warning message in the specified element.
 *
 * @since   3.0.0
 * @param   {string} provider The map provider to validate (gmaps, mapbox, stadia).
 *                            Required - there is no page-level fallback, and an
 *                            empty or unknown provider fails closed.
 * @param   {string} elementSelector jQuery selector for the element to show the warning in
 * @returns {boolean} True if the API key is valid, false otherwise
 */
export const hasValidApiKey = function( provider, elementSelector ) {
    const $element = jQuery( elementSelector );

    // Render the key-required box in the element where the map would have
    // gone. Mapbox / Stadia have provider-specific strings that also link
    // the create-a-key documentation; gmaps keeps the generic message.
    const showKeyMissingMsg = function() {
        if ( ! $element.length ) {
            return;
        }

        const msg = wpslL10n.apiKeyMissingProvider?.[ provider ] ?? wpslL10n.apiKeyMissing;

        $element.addClass( 'wpsl-map-style-wrap wpsl-key-required' ).html( '<p>' + msg + '</p>' );
        $element.closest( '.postbox' ).addClass( 'wpsl-missing-key' );
    };

    // OSM doesn't require an API key
    if ( provider === 'osm' ) {
        return true;
    }

    // Map providers to their input field IDs (for settings page forms)
    const providerFieldMap = {
        'gmaps': '#wpsl-api-browser-key',
        'mapbox': '#wpsl-api-mapbox-key',
        'stadia': '#wpsl-api-stadia-key'
    };

    const apiKeyField = providerFieldMap[ provider ];
    if ( ! apiKeyField ) {
        return false;
    }

    // First, try to get the key from the form input (on settings page)
    const $keyField = jQuery( apiKeyField );
    if ( $keyField.length ) {
        const hasKey = $keyField.val().trim().length > 0;
        if ( ! hasKey ) {
            showKeyMissingMsg();
            return false;
        }

        return true;
    }

    // If no form input exists (e.g., on appearance page), check global settings
    if ( ! wpslSettings.api || wpslSettings.api.provider !== provider ) {
        return false;
    }

    const hasKey = !! wpslSettings.api.key;
    const isValid = wpslSettings.api.hasValidKey !== false;
    if ( ! hasKey || ! isValid ) {
        showKeyMissingMsg();
        return false;
    }

    return true;
};

/**
 * Store imported libraries. For now only used with Google Maps.
 *
 * @since 3.0.0
 */
export const importedLibraries = {};

/**
 * Load and init a module.
 *
 * Always pass args.path. The default resolves to assets/dist/<...>/modules/,
 * which the build never writes ( webpackIgnore keeps these imports out of the
 * bundle ), so it only works under SCRIPT_DEBUG and 404s in production.
 *
 * Modules loaded here get their own instance, so anything reaching shared
 * state ( slData / config ) must be statically imported from the entry point
 * instead, the way the GDPR module is.
 *
 * @since   3.0.0
 * @param   {string} moduleName The name of the module to import
 * @param   {Object} args       Optional arguments
 * @returns {Promise<Object>}   A promise that resolves to the imported module
 */
export const importModule = function( moduleName, args = {} ) {
    const baseUrl = wpslSettings.url;
    const isAdmin = document.body.classList.contains( 'wp-admin' );
    const isDebug = wpslSettings.scriptDebug || false;
    
    // Use src paths in development, dist paths in production
    const basePath = isDebug ? 'assets/src/' : 'assets/dist/';
    
    const defaultPath = isAdmin 
        ? `${baseUrl}${basePath}admin/js/modules/wpsl-${moduleName}.js`
        : `${baseUrl}${basePath}frontend/js/modules/wpsl-${moduleName}.js`;

    let path = args.path || defaultPath;

    // Native imports bypass wp_enqueue_script(), so add the ?ver= it would.
    // Only this file gets it, its own static imports resolve without it.
    if ( wpslSettings.version ) {
        path += ( path.includes( '?' ) ? '&' : '?' ) + 'ver=' + encodeURIComponent( wpslSettings.version );
    }

    return import( /* webpackIgnore: true */ path ).then( module => {
        if ( ! module[ moduleName ] ) {
            throw new Error(`Module ${moduleName} not found in ${path}`);
        }
        
        if ( args.init !== false && typeof module[moduleName].init === 'function' ) {
            module[moduleName].init();
        }

        return module[ moduleName ];
    }).catch( error => {
        console.error(`Failed to load module ${moduleName}:`, error);
        throw error;
    });
};