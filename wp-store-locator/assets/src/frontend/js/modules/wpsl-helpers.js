import { config, slData } from './wpsl-shared.js';
import { alignSearchColumns as alignSearchColumnsShared } from '../../../common/wpsl-dropdowns.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { search } from './wpsl-search.js';
import { filters } from './wpsl-filters.js';
import { responseHandlers } from './wpsl-response-handlers.js';

/*
 * Placeholders a data source may legitimately leave out. Mirrors the list
 * Section_Editor allows, plus hours_status. Empty strings, so a template's
 * `if ( thumb )` still reads as "not present".
 */
const optionalPlaceholders = {
    description:     '',
    location_status: '',
    hours_status:    '',
    hours:           '',
    thumb:           '',
    permalink:       '',
    email:           '',
    url:             '',
    phone:           '',
    fax:             '',
    distance:        '',
    distance_unit:   ''
};

/**
 * Helper functions for the WPSL frontend.
 *
 * Provides utility methods for map operations, directions, templates,
 * search processing, markers, results handling, and formatting.
 *
 * @since 3.0.0
 */
export const helpers = {
    /**
     * Get an item from localStorage if supported.
     * 
     * @since   3.0.0
     * @param   {string} key The localStorage key to retrieve
     * @returns {string|boolean} The stored value or false if not supported/not found
     */
    getLocalStorageItem: function( key ) {
        if ( typeof window.localStorage !== 'undefined' ) {
            return localStorage.getItem( key );
        }
        
        return false;
    },

    /**
     * Return the current map object
     *
     * @since   3.0.0
     * @param   {object} map
     * @returns {object}
     */
    getMapObj: function( map ) {
        if ( typeof map !== 'object' ) {
            map = slData.maps[0];
        }

        return map;
    },

    /**
     * Get the settings that are
     * used to create a new map.
     *
     * @since  3.0.0
     * @param  {number} mapIndex
     * @return {object} mapSettings
     */
    getMapSettings: function( mapIndex ) {
        let mapSettings;

        // No provider specific loader, so fall back to the base settings.
        if ( typeof helpers.map.getSettingFields === 'function' ) {
            mapSettings = helpers.map.getSettingFields( mapIndex );
        } else {
            mapSettings = {
                zoomLevel: config.map.zoomLevel,
                scrollWheel: config.map.scrollWheel,
                controlPosition: config.map.controlPosition,
            };
        }

        // Shortcode settings win over the defaults.
        if ( typeof window[ `wpslMap_${mapIndex}` ] === 'object' ) {
            mapSettings = helpers.map.checkShortCodeSettings( mapSettings, mapIndex );
        }

        // Mapbox / OpenStreetMaps share one implementation, Google Maps needs its own.
        if ( typeof helpers.map[ config.api.provider + 'StartLatLng'] === 'function' ) {
            mapSettings.startLatLng = helpers.map[ config.api.provider + 'StartLatLng']( mapIndex );
        } else {
            mapSettings.startLatLng = helpers.map.getStartLatlng( mapIndex );
        }

        return wp.hooks.applyFilters( 'wpslMapSettings', mapSettings );
    },

    /**
     * Check if we are using a v3 template.
     * 
     * @since    3.0.0
     * @returns {boolean}
     */
    flexboxAvailable: function() {
        return jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-flex' );
    },

    /**
     * Check if the Category filter is available in the current template.
     *
     * @since   3.0.0
     * @returns {boolean}
     */
    hasCategoryFilter: function() {
        if ( jQuery( '#wpsl-category' ).length > 0 ) {
            return true;
        }

        const $options = jQuery( '#wpsl-filter-options' );
        return $options.length > 0;
    },

    /**
     * Get the count of active markers based on the map provider.
     *
     * @since   3.0.0
     * @returns {number} The count of active markers
     */
    getActiveMarkerCount: function() {
        const provider = config.api.provider;
        
        // Mapbox keeps a flat array, which can hold entries that aren't drawable features.
        if ( provider === 'mapbox' ) {
            if ( slData.provider.markers.active && Array.isArray( slData.provider.markers.active ) ) {
                return slData.provider.markers.active.filter( 
                    feature => feature.type === 'Feature' && feature.geometry 
                ).length;
            }

            return 0;
        }
        
        // Google Maps / OSM store per map index: markers.active[mapIndex] = [marker1, ...]
        if ( provider === 'gmaps' || provider === 'osm' || provider === 'stadia' ) {
            const mapIndex = slData.provider.markers.currentMapIndex || 0;
            
            if ( slData.provider.markers.active && typeof slData.provider.markers.active === 'object' && Array.isArray( slData.provider.markers.active[mapIndex] ) ) {
                return slData.provider.markers.active[mapIndex].length;
            }
        }
        
        return 0;
    },

    directions: {
        /**
         * Get validated origin coordinates for directions URL.
         * Returns coordinates in the specified format (lat,lng or lng,lat).
         *
         * @since   3.0.0
         * @param   {string} format 'latlng' for Google Maps (lat,lng) or 'lnglat' for Mapbox/OSM (lng,lat)
         * @returns {string} Validated coordinates string, or empty string if invalid
         */
        getOriginCoords: function( format = 'latlng' ) {
            let lat, lng;

            // directionOrigin stays an empty string until a geocode succeeds,
            // so an empty value falls through to the start location fallback.
            if ( slData.directionOrigin && helpers.search.locationSearchActive() ) {
                const originParts = slData.directionOrigin.split( ',' );
                lat = parseFloat( originParts[0] );
                lng = parseFloat( originParts[1] );
            } else if ( helpers.search.locationSearchActive() && config.map.startLatLng ) {
                lat = parseFloat( config.map.startLatLng.lat );
                lng = parseFloat( config.map.startLatLng.lng );
            }

            if ( ! isNaN( lat ) && ! isNaN( lng ) ) {
                return format === 'lnglat' ? lng + ',' + lat : lat + ',' + lng;
            }

            return '';
        },

        /**
         * Hide the admin-defined map shapes while a route is on the map, or
         * put them back once it is gone.
         *
         * Called wherever slData.directions.active flips: each provider's
         * show() / calcRoute() sets it to true, and sharedRestoreSteps() and
         * the new-search reset set it back to false. The directions only
         * ever draw on the locator map ( maps[0] ), so only that map's
         * shapes are touched; a [wpsl_map] elsewhere on the page keeps its.
         *
         * @since   3.0.0
         * @param   {boolean} visible false while the directions are shown
         * @returns {void}
         */
        setShapesVisible: function( visible ) {
            const provider = slData.provider;
            const map      = slData.maps[0];

            if ( ! map || ! provider || ! provider.shapes ) {
                return;
            }

            if ( visible ) {
                provider.shapes.show( map );
            } else {
                provider.shapes.hide( map );
            }
        },

        /**
         * Calculate the route from the start to the end.
         *
         * @since   3.0.0
         * @param   {object} originLatLng 	   The latlng from the start point
         * @param   {object} destinationLatLng The latlng from the end point
         * @returns {void}
         */
        checkRouteCoordinates: function( originLatLng, destinationLatLng ) {
            if ( originLatLng && destinationLatLng ) {
                jQuery( '#wpsl-direction-details ul' ).empty();
                jQuery( '.wpsl-direction-before, .wpsl-direction-after' ).remove();

                slData.provider.api.directions.calcRoute( originLatLng, destinationLatLng );
            } else {
                // No origin or destination, so no route. Happens when there's no
                // start marker ( default start location skipped ), or the clicked
                // result has no store id ( a custom listing <li> without data-store-id ).
                helpers.createUserNotice( wpslLabels.noDirectionsFoundMessage, 'directions' );
            }
        },

        /**
         * Scroll the result list to the top when directions are clicked, 
         * so the route is visible without manual scrolling.
         *
         * @since   3.0.0
         * @returns {void}
         */
        scrollToTop: function() {
            jQuery( '#wpsl-result-list' ).animate({
                scrollTop: 0
            }, 300);
        },

        /**
         * Adjust the viewport so the route is visible without scrolling up.
         *
         * @since   3.0.0
         * @returns {void}
         */
        maybeAdjustViewport: function() {
            if ( jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-store-below' ) ) {
                const directionOffset = jQuery( '#wpsl-map' ).offset();
                jQuery( window ).scrollTop( directionOffset.top );
            }
        },

        /**
         * Focus the back button after directions are shown, and make each
         * direction step <li> tabbable so keyboard users can step through them.
         *
         * @since   3.0.0
         * @returns {void}
         */
        focusBackButton: function() {
            jQuery( '#wpsl-direction-details ul li' ).attr( 'tabindex', '0' );

            const $backButton = jQuery( '#wpsl-direction-start' );
            if ( $backButton.length ) {
                $backButton.trigger( 'focus' );
            }
        },

        /**
         * Adjust the driving directions styling to the available width.
         *
         * @since 3.0.0
         */
        styling: {
            // Looked up in init(), once the directions list actually exists.
            $elem: null,

            /**
             * Initialize direction styling.
             *
             * @since 3.0.0
             */
            init: function() {
                this.$elem = jQuery( '#wpsl-direction-details ul' );

                this.checkCurrentWidth();
                this.resizeListener();
            },
            
            /**
             * Check and apply styling based on current width.
             *
             * @since 3.0.0
             */
            checkCurrentWidth: function() {
                if ( this.getWidth() < 275 ) {
                    this.$elem.addClass( 'wpsl-direction-sml' );
                } else {
                    this.$elem.removeClass( 'wpsl-direction-sml' );
                }
            },
            
            /**
             * Get the width of the directions element.
             *
             * @since 3.0.0
             * @returns {number} Width in pixels
             */
            getWidth: function() {
                return this.$elem.width();
            },
            
            /**
             * Listen for window resize and adjust styling.
             *
             * @since 3.0.0
             */
            resizeListener: function() {
                const self = this;

                jQuery( window ).off( 'resize.wpslDirections' ).on( 'resize.wpslDirections', function() {
                    self.checkCurrentWidth();
                });
            }
        }
    },
    template: {
        /**
         * Make the phone number clickable when the option is enabled, or when
         * contact details are always clickable. The tel: link works on all
         * devices -- desktop browsers hand it off to a calling app.
         *
         * @since	1.2.20
         * @param	{string} phoneNumber The phone number
         * @returns {string} phoneNumber Either just the plain number, or with a link wrapped around it with tel:
         */
        formatPhoneNumber: function( phoneNumber ) {
            if ( config.ux.phoneUrl || config.ux.clickableDetails ) {
                phoneNumber = `<a href="tel:${this.formatClickablePhoneNumber( phoneNumber )}">${phoneNumber}</a>`;
            }

            return wp.hooks.applyFilters( 'wpslFormattedPhoneNumber', phoneNumber );
        },

        /**
         * Replace spaces - . and () from phone numbers.
         * Also if the number starts with a + we check for a (0) and remove it.
         *
         * @since	1.2.20
         * @param	{string} phoneNumber The phone number
         * @returns {string} phoneNumber The 'cleaned' number
         */
        formatClickablePhoneNumber: function( phoneNumber ) {
            if ( ( phoneNumber.indexOf( '+' ) != -1 ) && ( phoneNumber.indexOf( '(0)' ) != -1 ) ) {
                phoneNumber = phoneNumber.replace( '(0)', '' );
            }

            return phoneNumber.replace( /(-| |\(|\)|\.|)/g, '' );
        },

        /**
         * Check if we need to make the email address clickable.
         *
         * @since   2.2.13
         * @param   {string} email The email address
         * @returns {string} email Either the normal email address, or the clickable version.
         */
        formatEmail: function( email ) {
            if ( config.ux.clickableDetails ) {
                email = `<a href="mailto:${email}">${email}</a>`;
            }

            return wp.hooks.applyFilters( 'wpslFormatEmail', email );
        },

        /**
         * Create the html for the info window actions.
         *
         * @since	2.0.0
         * @param	{string} id        The store id
         * @param	{string} url       The store URL (optional)
         * @param	{string} permalink The store permalink (optional)
         * @returns {string} output    The html for the info window actions
         */
        createInfoWindowActions: function( id, url, permalink ) {
            const $mapElem = jQuery( '#wpsl-map' );

            let output,
                moreDetails = '',
                streetView = '',
                zoomTo = '';

            if ( $mapElem.length ) {
                // Add More Details link if CTA details is enabled
                if ( config.ux.ctaDetailsButton ) {
                    let storeUrl = url || permalink;
                    if ( storeUrl ) {
                        moreDetails = `<a class="wpsl-details${config.ux.ctaDetailsClass}" target="_blank" href="${storeUrl}">${wpslLabels.moreDetails}</a>`;
                    }
                }

                if ( $mapElem.hasClass( 'wpsl-canvas-gmaps' ) ) {
                    if ( config.map.streetViewAvailable ) {
                        streetView = `<a class="wpsl-streetview${config.ux.ctaStreetViewClass}" href="#">${wpslLabels.streetView}</a>`;
                    }
                }

                if ( config.ux.markerZoomTo ) {
                    zoomTo = `<a class="wpsl-zoom-here${config.ux.ctaZoomClass}" href="#">${wpslLabels.zoomHere}</a>`;
                }

                const ctaSectionClass = ( config.ux.ctaButtons || config.ux.ctaDetailsButton ) ? ' wpsl-cta-section' : '';

                output = `<div class="wpsl-info-actions${ctaSectionClass}">${moreDetails}${ this.createDirectionUrl( id ) }${streetView}${zoomTo}</div>`;
            }

            return wp.hooks.applyFilters( 'wpslCreateInfoWindowActions', output );
        },

        /**
         * Check if we need to create an url that takes the user to 
         * an external site to display the route, or if we can display it locally.
         *
         * @since	1.0.0
         * @param	{string} id			  The store id
         * @returns {string} directionUrl The full ( Google Maps, OpenRouteService ) url with the encoded start + end address
         */
        createDirectionUrl: function( id ) {
            let url = {};

            const skipStart = helpers.markers.maybeSkipStartMarker();
            if ( skipStart ) {
                return '';
            }

            // An origin-less search ( name search ) never geocodes a start
            // location, so no route can be calculated on any template.
            if ( config.search.skipGeocode ) {
                return '';
            }

            // On panel templates ( v3 ), filter-only ( category / country ) and
            // autoload searches have no origin, so hide the link until one runs.
            if ( jQuery( '#wpsl-panel' ).length && ! config.search.hasDirectionsOrigin ) {
                return '';
            }

            if ( config.api.provider !== 'mapbox' && ( config.search.directionRedirect || helpers.results.maybeUseBasicMode() || ( ( config.api.provider === 'osm' || config.api.provider === 'stadia' ) && ! config.api.hasValidRouteKey ) ) ) {
                url.target = 'target="_blank"';

                // An id means a marker click, so reuse the url from the search
                // results. Without one we generate it for the map provider.
                if ( typeof id !== 'undefined' ) {
                    url.src = jQuery( '[data-store-id="' + id + '"] .wpsl-directions' ).attr( 'href' );
                }

                if ( typeof url.src === 'undefined' ) {
                    // Validate coordinates before passing to provider
                    if ( this.lat && this.lng && ! isNaN( parseFloat( this.lat ) ) && ! isNaN( parseFloat( this.lng ) ) ) {
                        url.src = slData.provider.templateHelpers.createDirectionsUrl( this );
                    } else {
                        console.warn( 'WPSL Invalid coordinates detected:', this.lat, this.lng );
                        url.src = '#';
                    }
                }
            } else {
                url = {
                    src: '#',
                    target: ''
                };
            }

            const directionUrl = `<a class="wpsl-directions${config.ux.ctaDirectionsClass}" ${url.target} href="${url.src}">${wpslLabels.directions}</a>`;

            return wp.hooks.applyFilters( 'wpslCreateDirectionUrl', directionUrl );
        },

        /**
         * Make the URI encoding compatible with RFC 3986.
         *
         * !, ', (, ), and * will be escaped, otherwise they break the string.
         *
         * @since	1.2.20
         * @param	{string} str The string to encode
         * @returns {string} The encoded string
         */
        rfc3986EncodeURIComponent: function( str ) {
            return encodeURIComponent( str ).replace( /[!'()*]/g, escape );
        },

        /**
         * Check if a label contains a placeholder and if there are results to display.
         *
         * @since   3.0.0
         * @param   {string} label    The label string to check for placeholders
         * @param   {array}  results  The results array to check length
         * @returns {boolean}         True if label has placeholder and results exist
         */
        hasPlaceholderWithResults: function( label, results ) {
            return typeof label === 'string' && label.indexOf( '{number}' ) !== -1 && results.length >= 1;
        },

        /**
         * get the HTML template used in the info windows on the map.
         *
         * @since	1.0.0
         * @param	{object} infoWindowData	The data that is shown in the info window (address, url, phone etc)
         * @returns {string} windowContent	The HTML content that is placed in the info window
         */
        getInfoWindowTemplate: function( infoWindowData ) {
            let template;

            if ( jQuery( '#wpsl-base-' + config.api.provider + '_0' ).length ) {
                template = slData.templates.cptInfoWindow;
            } else {
                template = slData.templates.infoWindow;
            }

            _.extend( infoWindowData, helpers.template, slData.provider.templateHelpers );

            // _.template compiles to `with ( data )`, so a placeholder the
            // data lacks is a ReferenceError that kills the popup.
            const windowContent = _.template( template )( _.defaults( _.extend( {}, infoWindowData ), optionalPlaceholders ) );

            return windowContent;
        },

        /**
         * Line up the search bar column ( equal label widths + category dropdown
         * matched to the search field ). Thin wrapper over the shared
         * implementation in common/wpsl-dropdowns.js.
         *
         * @since   3.0.0
         * @returns {void}
         */
        alignSearchColumns: function() {
            alignSearchColumnsShared();
        },
    },

    autoComplete: {
        /**
         * Check if autocomplete is available.
         *
         * @since 3.0.0
         * @returns {boolean} True if autocomplete is enabled and available
         */
        available: function() {
            return ( config.search.autoComplete == 1 && typeof slData.provider.api.autoComplete === 'object' );
        },
    },

    input: {
        /**
         * Get the correct search input element.
         * 
         * When Mapbox autocomplete is active, it replaces
         * #wpsl-search-input with its own input element.
         *
         * @since   3.0.0
         * @returns {jQuery} jQuery object containing the search input element
         */
        getSearchField: function() {
            const $mapboxInput = jQuery( '#mapbox-autocomplete input[type="text"]' );
            if ( $mapboxInput.length > 0 ) {
                return $mapboxInput;
            }

            return jQuery( '#wpsl-search-input' );
        },
    },
    search: {
        /**
         * Return the country restriction set through the
         * [wpsl country="..."] shortcode, or an empty string when none is set.
         *
         * @since  3.0.0
         * @return {string} Comma-separated ISO country code(s), or an empty string.
         */
        getShortcodeCountry: function() {
            if ( config.search && config.search.restrictions && typeof config.search.restrictions.country === 'string' ) {
                return config.search.restrictions.country;
            }

            return '';
        },

        /**
         * Check if we need to collect the statistics data.
         *
         * @since 3.0.0
         * @param {object} response The API response
         */
        maybeIncludeStatistics: function( response ) {
            if ( typeof config.collectStatistics !== 'undefined' && jQuery( '#wpsl-search-input' ).val() && jQuery.isEmptyObject( slData.statistics ) && ! slData.geolocation.active ) {
                slData.provider.search.collectStatsData( response );
            }
        },

        /**
         * Process geocode response data for directions, statistics, and border enforcement
         *
         * @since  3.0.0
         * @param  {object} args Object containing lat/lng coordinates
         * @param  {object} response The full geocode API response
         * @return {object} Modified args with border enforcement applied
         */
        processGeocodeResponse: function( args, response ) {
            if ( config.search.directionRedirect ) {
                const coordinates = helpers.extractCoordinates( args );
                if ( coordinates ) {
                    slData.directionOrigin = coordinates.lat + ',' + coordinates.lng;
                } else {
                    slData.directionOrigin = '';
                }
            }

            this.maybeIncludeStatistics( response );

            args = this.enforceBorders( args, response );

            return args;
        },

        /**
         * See if we need to restrict the results to a
         * single country and include the country code.
         *
         * @since   3.0.0
         * @param   {object} args     The search arguments
         * @param   {object} response The API response
         * @returns {object} args     The search arguments
         */
        enforceBorders: function( args, response ) {
            if ( config.search.restrictions.borders ) {
                args = this.includeCountryCode( args, response );
            }

            return args;
        },

        /**
         * Take out the two letter country code from the API response,
         * or grab it from the selected dropdown value.
         *
         * @since   3.0.0
         * @param   {object} args
         * @param   {object} response The Geocode API response
         * @returns {object} args
         */
        includeCountryCode: function( args, response  ) {
            let filteredResponse = {};

            if ( typeof config.search.geocodeComponents === 'object' && typeof config.search.geocodeComponents.country !== 'undefined' ) {
                filteredResponse.countryCode = config.search.geocodeComponents.country;
            } else if ( jQuery( '#wpsl-country-dropdown').length )  {
                const selectedOption = filters.dropdowns.getSelectedOption( 'wpsl-country-dropdown' );

                filteredResponse.country = selectedOption.text;
                filteredResponse.countryCode = selectedOption.value;
            } else {
                filteredResponse = slData.provider.api.geocoding.filterResponse( response, 'country' );
            }

            jQuery.extend( args, filteredResponse );

            return args;
        },

        /**
         * Check if the user submitted a search through a search widget.
         *
         * @since	2.1.0
         * @returns {void}
         */
        checkWidgetSubmit: function() {
            if ( config.search.widgetEnabled ) {
                const widgetLatLng = this.getWidgetLatLng();

                // A widget geolocation search carries exact coordinates; use
                // them instead of re-geocoding the address label, which
                // regularly fails across geocoders.
                if ( widgetLatLng ) {
                    slData.autoCompleteLatLng = widgetLatLng;
                }

                jQuery( '#wpsl-search-btn' ).trigger( 'click' );
                jQuery( '.wpsl-search, #wpsl-wrap' ).removeClass( 'wpsl-widget' );
            }
        },

        /**
         * Return the coordinates the search widget passed along with the
         * searched location, or '' when there are none.
         *
         * @since   3.0.0
         * @returns {object|string} The { lat, lng } pair, or '' when absent or unparsable.
         */
        getWidgetLatLng: function() {
            const widgetLatLng = config.search.widgetLatLng;

            if ( typeof widgetLatLng !== 'object' || ! widgetLatLng ) {
                return '';
            }

            // wp_localize_script can deliver the floats as strings.
            const lat = parseFloat( widgetLatLng.lat );
            const lng = parseFloat( widgetLatLng.lng );

            if ( ! isFinite( lat ) || ! isFinite( lng ) ) {
                return '';
            }

            return { lat: lat, lng: lng };
        },

        /**
         * See if we need to check if the
         * search is for a state / country.
         *
         * @since   3.0.0
         * @param   {object} args     Search arguments
         * @param   {object} response API response
         * @returns {object} args     Search arguments
         */
        maybeGetFullSearchData: function( args, response ) {
            if ( config.search.fullSearch && ! jQuery( '#wpsl-country' ).length ) {
                const filteredResponse = this.getFullSearchData( response );

                if ( typeof args.fullSearch === 'object' ) {
                    delete args.fullSearch;
                }

                if ( filteredResponse ) {
                    args.fullSearch = filteredResponse;
                }
            }

            return args;
        },

        /**
         * Check if the provided input is
         * for a country / state / province.
         *
         * @since   3.0.0
         * @param   {object} response
         * @returns {void}
         */
        getFullSearchData: function( response ) {
            const filteredResponse = slData.provider.api.geocoding.filterResponse( response );
            if ( ! jQuery.isEmptyObject( filteredResponse ) && typeof filteredResponse.fullSearch !== 'undefined' ) {
                return filteredResponse.fullSearch;
            }
        },

        /**
         * See if a location search is
         * currently active.
         *
         * @since   3.0.0
         * @returns {boolean} noLocationSearch
         */
        locationSearchActive: function() {
            let locationSearch = true;

            if ( jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-name-support' ) || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-search-types-support' ) && jQuery( '.wpsl-search-type-dropdown button' ).attr( 'data-value' ) !== 'location' ) {
                locationSearch = false;
            }

            return locationSearch;
        },

        /**
         * Check if we need to restrict the geocode request /
         * search results to the selected country.
         *
         * @since   3.0.0
         * @param   {string} countryCode Two letter country code
         * @returns {void}
         */
        maybeSetComponentRestrictions: function( countryCode ) {
            if ( typeof config.search.geocodeComponents === 'undefined' ) {
                config.search.geocodeComponents = {
                    country: countryCode
                };
            } else {
                config.search.geocodeComponents.country = countryCode;
            }

            // Keep autocomplete restricted to the same country.
            if ( helpers.autoComplete.available() ) {
                slData.provider.api.autoComplete.setRestrictions( { country: countryCode } );
            }
        },

        /**
         * Check if we need to reverse geocode the coordinates.
         *
         * @since   3.0.0
         * @returns {bool} status Whether or not reverse geocode the input.
         */
        maybeReverseGeocode: function() {
            let status = false;

            if ( ! slData.skipReverseGeocode ) {
                if ( config.search.directionRedirect || config.search.restrictions.borders || typeof config.collectStatistics !== 'undefined' || slData.geolocation.active ) {
                    status = true;
                }
            }

            return status;
        },

        /**
         * Create or update the search type arguments
         * that are included in the AJAX request.
         *
         * class-search-types.php handles the submitted data for searches that
         * are not coordinate based ( name, category, country, state ).
         *
         * @since   3.0.0
         * @param   {object} args
         * @param   {string} type
         * @param   {string} data
         * @returns {object} args
         */
        setSearchTypeArgs: function( args, type, data ) {
            const locationTypes = ['country', 'state'];

            if ( typeof args.types === 'undefined' ) {
                args.types = {};
            }

            if ( jQuery.isEmptyObject( args.types ) ) {
                args.types = type;
            } else {
                args.types = args.types + ',' + type;
            }

            if ( jQuery.inArray( type, locationTypes ) !== -1 ) {
                args.location = {
                    [type] : data
                };
            } else if ( type == 'category' ) {
                args.filter = data;
            } else {
                args[type] = data;
            }

            return args;
        },

        /**
         * Include the 'category' value in the search types.
         *
         * @since   3.0.0
         * @param   {object} args The existing search argument
         * @returns {object} args Search arguments including the category type.
         */
        includeCategorySearchType: function( args ) {
            const dataValue = parseInt( jQuery( '#wpsl-category .wpsl-selected-item' ).attr( 'data-value' ) );
            if ( dataValue ) {
                args = helpers.search.setSearchTypeArgs( args, 'category', dataValue );
            }

            return args;
        },

        /**
         * Close the filter/radius dropdown when search results are displayed,
         * so both aren't visible at the same time.
         *
         * @since   3.0.0
         * @returns {void}
         */
        closeFiltersOnResults: function() {
            // wpsl-flex marks a v3 template.
            if ( ! jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-flex' ) ) {
                return;
            }
            
            if ( jQuery( '#wpsl-panel' ).hasClass( 'wpsl-filters-open' ) ) {
                jQuery( '#wpsl-filter-options, #wpsl-filter-header' ).hide();
                jQuery( '#wpsl-result-filters, #wpsl-result-list' ).show();
                jQuery( '#wpsl-panel' ).removeClass( 'wpsl-filters-open' );
                jQuery( '#wpsl-filter-options > div' ).removeClass( 'wpsl-filter-expanded' );
                
                // Reset aria-expanded on filter buttons for accessibility
                jQuery( '#wpsl-result-filters button' ).attr( 'aria-expanded', 'false' );
            }
        },

        adjustColumnClass: function( response ) {
            // No need to do this when the template is not using columns
            if ( ! jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-v3-result-columns' ) ) {
                return;
            }

            const $storeList = slData.$storeList;

            // Check if the list has a wpsl-v3-columns- class
            const classList = $storeList.attr( 'class' );
            if ( ! classList || classList.indexOf( 'wpsl-v3-columns-' ) === -1 ) {
                return;
            }

            const configuredColumns = parseInt( config.search.resultColumns );
            if ( ! configuredColumns || isNaN( configuredColumns ) ) {
                return;
            }

            const resultCount = response.length;

            // Only adjust if we have fewer results than configured columns
            const columns = resultCount < configuredColumns ? resultCount : configuredColumns;
            const newClassList = classList.replace( /wpsl-v3-columns-\d+/g, '' ).trim();

            // The separating space is conditional, otherwise an empty class
            // list would put a leading space in the attribute.
            $storeList.attr( 'class', ( newClassList ? newClassList + ' ' : '' ) + 'wpsl-v3-columns-' + columns );
        },

        /**
         * Check if we need to show the number of returned results,
         * and if so, insert the text above the list of results.
         *
         * @since   3.0.0
         * @param   {object} response The returned search results
         * @returns {void}
         */
        numberResults: function( response ) {
            const message = {};

            // numberResults only exists when the option is enabled on the
            // settings page, and only works if the text contains {number}.
            if ( helpers.template.hasPlaceholderWithResults( wpslLabels.numberResults, response ) ) {

                // A custom section renders its own text, so it only needs the count.
                if ( config.search.customNumberResultsSection ) {
                    message.number_results = response.length;
                } else {

                    // Fall back to the plural label when no singular one is configured.
                    const label = ( response.length === 1 && typeof wpslLabels.numberResultsSingle === 'string' && wpslLabels.numberResultsSingle )
                        ? wpslLabels.numberResultsSingle
                        : wpslLabels.numberResults;

                    message.number_results = label.replace( '{number}', response.length );
                }

                jQuery( '.wpsl-number-results' ).remove();
                jQuery( '#wpsl-stores ul' ).before( _.template( slData.templates.numberResults )( message ) );
            }
        },
    },

    /**
     * v3 template specific filter panel functions
     */
    panel: {
        /**
         * Close the filter panel if it's open, so it doesn't obscure the search
         * results after an autocomplete selection.
         *
         * @since   3.0.0
         * @returns {void}
         */
        maybeCloseFilters: function() {
            const $panel = jQuery( '#wpsl-panel' );
            if ( $panel.length && $panel.hasClass( 'wpsl-filters-open' ) ) {
                $panel.removeClass( 'wpsl-filters-open' );

                jQuery( '#wpsl-filter-header, #wpsl-filter-options' ).hide();
                jQuery( '#wpsl-result-filters' ).show();
                
                // Reset aria-expanded on filter toggle buttons
                jQuery( '#wpsl-result-filters button' ).attr( 'aria-expanded', 'false' );
                
                // Remove expanded class from filter panels
                jQuery( '#wpsl-filter-options > div' ).removeClass( 'wpsl-filter-expanded' );
                
                // Remove filter header button from tab order when hidden
                jQuery( '#wpsl-clear-filter' ).attr( 'tabindex', '-1' );
            }
        },
    },

    results: {
        /**
         * The storeId sits on the li in the results list, but on the wrapper
         * div for a marker, so check which one to target.
         *
         * @since   3.0.0
         * @param   {jQuery} e
         * @returns {boolean} storeId The current store ID
         */
        getClickedElemID: function( e ) {
            let storeId;

            if ( e.parents( 'li' ).length > 0 ) {
                storeId = e.parents( 'li' ).data( 'store-id' );
            } else {
                storeId = e.parents( '.wpsl-info-window' ).data( 'store-id' );
            }

            return storeId;
        },

        /**
         * Close any open info windows / popups.
         *
         * @since 3.0.0
         */
        maybeCloseInfoWindow: function() {
            const infoWindow = slData.provider.infoWindow;
            if ( ! jQuery.isEmptyObject( infoWindow ) && ! jQuery.isEmptyObject( infoWindow.openWindow ) && typeof infoWindow.close === 'function' ) {
                infoWindow.close();
            }
        },

        /**
         * Check if we need to show or hide the 'Zoom here' link.
         *
         * The button is hidden if we're already at or above the max zoom level,
         * since clicking it would zoom to the same level.
         *
         * @since   3.0.0
         * @param   {object} map The map object
         * @returns {void}
         */
        maybeShowZoomOption: function( map ) {
            const zoomLevel = map.getZoom();
            const $zoomButton = jQuery( '.wpsl-zoom-here' );
            
            if ( zoomLevel >= config.map.autoZoomLevel ) {
                $zoomButton.hide();
            } else {
                $zoomButton.show();
            }
        },

        /**
         * Switch back from showing the directions
         * details to showing the search results.
         *
         * @since   3.0.0
         * @returns {void}
         */
        hideDirections: function() {
            jQuery( '.wpsl-direction-before, .wpsl-direction-after' ).remove();
            jQuery( '#wpsl-stores, #wpsl-result-filters' ).show();
            jQuery( '#wpsl-direction-details' ).hide();
        },

        /**
         * Hide the distance in the search results.
         *
         * Only used when the start location is skipped on page load, 
         * or when a new search is triggered automatically as the map moves.
         *
         * @since   3.0.0
         * @returns {void}
         */
        hideDistance: function() {
            jQuery( '#wpsl-stores .wpsl-distance' ).hide();
        },

        /**
         * See if we need to hide the distance, force the directions to
         * render on the map providers site and remove the start marker.
         *
         * @since   3.0.0
         * @returns {boolean} active
         */
        maybeUseBasicMode: function() {
            return ( slData.firstLoadInProgress && config.search.autoloadHideDetails ) || config.search.namesEnabled;
        },

        /**
         * Restore the map to it's 
         * initial state on page load.
         *
         * @since   3.0.0
         * @returns {void}
         */
        restoreInitialState: function() {
            const resetIcon = '.wpsl-icon-reset';
            const mapData = {
                viewport: slData.viewport,
                map: slData.maps[0]
            };

            let args = {};

            // Prevent a second reset while one is in progress.
            if ( jQuery( '.wpsl-icon-reset , #wpsl-reset-map' ).hasClass( 'wpsl-in-progress' ) ) {
                return;
            }

            // A name search skips the geocode, but the ajax request needs the
            // start coordinates to restore the locations shown on page load.
            delete config.search.skipGeocode;

            if ( slData.directions.active ) {
                slData.provider.api.directions.restoreResults();
            }

            slData.directions.active = false;

            helpers.results.maybeCloseInfoWindow();

            helpers.results.hideDirections();

            // Dragging the start marker sets autoLoad to false, so re-check it
            // here before the stores are reloaded.
            if ( config.search.autoLoad ) {
                args.autoLoad = 1;
            }

            // Only reset when the latLng or zoom level changed since page load.
            if ( helpers.map.viewportChanged( mapData ) ) {
                slData.provider.markers.removeAll();

                jQuery( '#wpsl-stores ul' ).empty();

                jQuery( resetIcon ).addClass( 'wpsl-in-progress' );

                filters.resetAll();

                jQuery( '#wpsl-pagination' ).remove();

                if ( config.search.autoLocate.enabled && slData.geolocation.active ) {
                    const coordinates = helpers.extractCoordinates( slData.geolocation.position );
                    if ( coordinates ) {
                        args = jQuery.extend( slData.geolocation.position, { resetMap: true } );
                    } else {
                        args.latLng = helpers.extractCoordinates( config.map.startLatLng ) || config.map.startLatLng;
                    }
                } else if ( config.search.autoLoad ) {
                    args.latLng = helpers.extractCoordinates( config.map.startLatLng ) || config.map.startLatLng;
                } else {
                    args = {};
                }

                if ( ! jQuery.isEmptyObject( args ) ) {
                    // Restore the page load zoom level even when the reloaded
                    // search finds nothing, which a normal search no longer does.
                    args.restoreViewport = true;

                    search.prepare( args );
                } else {
                    helpers.map.setDefaultViewport();
                }

                jQuery( resetIcon ).removeClass( 'wpsl-in-progress' );
            }
        },
        /**
         * The last steps to restore the search results
         * after showing the directions on the map.
         *
         * Used by Google Maps / Mapbox / OSM
         *
         * @since 3.0.0
         * @returns {void}
         */
        sharedRestoreSteps: function() {
            helpers.results.hideDirections();

            slData.directions.active = false;

            // The route is gone, so the shapes hidden for it come back.
            helpers.directions.setShapesVisible( true );

            // Used to offset the bottom spacing of the address in the infowindow
            jQuery( '#wpsl-map' ).removeClass( 'wpsl-directions-active' );

            helpers.results.maybeCloseInfoWindow();

            wp.hooks.doAction( 'wpslRestoreResults' );
        },

        /**
         * Check if a location has data for more info fields.
         *
         * @since   3.0.0
         * @param   {object}  locationDetails The location data object
         * @returns {boolean} True if location has more info data
         */
        hasMoreInfoData: function( locationDetails ) {
            const moreInfoFields = config.results.moreInfoFields;
            return moreInfoFields.some( field => {
                const value = locationDetails[field];
                
                return value != null && value.toString().trim() !== '';
            });
        },
    },

    map: {
        /**
         * See if we need to overwrite any of the
         * map settings with a shortcode value.
         *
         * @since   3.0.0
         * @param   {object} mapSettings The map settings
         * @param   {number} mapIndex    The map index
         * @returns {object} mapSettings The map settings including possible shortcode options
         */
        checkShortCodeSettings: function( mapSettings, mapIndex ) {
            const shortCodeSettings = helpers.map.getWpslMapShortcodeSettings();
            const len = shortCodeSettings.length;

            let shortCodeVal;

            if ( typeof window[ 'wpslMap_' + mapIndex ].shortCode !== 'undefined' ) {
                for ( let i = 0; i < len; i++ ) {
                    shortCodeVal = window[ 'wpslMap_' + mapIndex ].shortCode[ shortCodeSettings[i] ];

                    // Overwrite the settings if a shortcode value exists
                    if ( typeof shortCodeVal !== 'undefined' ) {
                        mapSettings[ shortCodeSettings[i] ] = shortCodeVal;
                    }
                }
            }

            return mapSettings;
        },

        /**
         * Return the data for the first wpslMap location.
         *
         * @since   3.0.0
         * @param   {number}      mapIndex      The current map index
         * @returns {object|void} firstLocation The location data
         */
        getFirstLocation: function( mapIndex ) {
            let firstLocation;

            if ( this.wpslMapLocationsExist( mapIndex ) ) {
                firstLocation = window[ 'wpslMap_' + mapIndex ].locations[0];
            }

            return firstLocation;
        },

        /**
         * See if there are locations set for the [wpsl_map] shortcode(s).
         *
         * @since   3.0.0
         * @param   {number} mapIndex The map index
         * @returns {boolean}
         */
        wpslMapLocationsExist: function( mapIndex ) {
            return typeof window[ 'wpslMap_' + mapIndex ]?.locations !== 'undefined';
        },

        /**
         * Refresh the map to fix rendering issues after visibility changes.
         *
         * @since   3.0.0
         * @param   {number} mapIndex The index of the map to refresh (default: 0)
         * @returns {void}
         */
        refreshMap: function( mapIndex ) {
            mapIndex = mapIndex || 0;
            if ( typeof slData !== 'undefined' && slData.provider && slData.provider.map ) {
                slData.provider.map.invalidateSize( mapIndex );
            }
        },

        /**
         * Set the center and default zoom level.
         *
         * @since   3.0.0
         * @returns {void}
         */
        setDefaultViewport: function() {
            helpers.map.setCenter();
            helpers.map.setZoom( { map: slData.maps[0], zoom: config.map.zoomLevel } );
        },

        /**
         * Show the reset button now that the page load viewport is stored.
         *
         * @since   3.0.0
         * @returns {void}
         */
        revealResetBtn: function() {
            jQuery( '#wpsl-map-controls' ).removeClass( 'wpsl-hide-reset' ).addClass( 'wpsl-reset-exists' );
            jQuery( '.wpsl-icon-reset, #wpsl-reset-map' ).show();
        },

        /**
         * Return the start latlng: the first [wpsl_map] location when that
         * shortcode is used, otherwise the configured start location.
         *
         * @since   3.0.0
         * @param   {number} mapIndex    The map index
         * @returns {object} startLatLng The start coordinates
         */
        getStartLatlng: function( mapIndex ) {
            const firstLocation = helpers.map.getFirstLocation( mapIndex );

            let startLatLng;

            if ( typeof firstLocation === 'object' && typeof firstLocation.lat === 'string' && typeof firstLocation.lng === 'string' ) {
                startLatLng = {
                    lat: firstLocation.lat,
                    lng: firstLocation.lng
                };
            } else if ( config.map.startLatLng && typeof config.map.startLatLng.lat === 'function' && typeof config.map.startLatLng.lng === 'function' ) {
                startLatLng = {
                    lat: config.map.startLatLng.lat(),
                    lng: config.map.startLatLng.lng()
                };
            } else if ( config.map.startLatLng && config.map.startLatLng.lat != null && config.map.startLatLng.lng != null ) {
                // Handle plain objects with lat/lng properties (OSM/Mapbox)
                startLatLng = {
                    lat: parseFloat( config.map.startLatLng.lat ),
                    lng: parseFloat( config.map.startLatLng.lng )
                };
            } else {
                // Fallback to default coordinates if startLatLng is not properly initialized
                console.warn( 'WPSL: startLatLng is not properly configured. Using default coordinates (0, 0).' );
                startLatLng = {
                    lat: 0,
                    lng: 0
                };
            }

            return startLatLng;
        },
    },
    markers: {
        /**
         * Check if marker clustering is active for the passed map.
         *
         * @since   3.0.0
         * @param   {object}  map The map instance
         * @returns {boolean} True when this map should cluster its markers
         */
        clusteringActive: function( map ) {
            return typeof config.markers.cluster === 'object' && !! ( map && map._wpslClusterEnabled );
        },

        /**
         * Make sure the correct properties are set
         * before adding the marker to the map.
         *
         * @since   3.0.0
         * @param   {object} markerData
         * @returns {object} markerData
         */
        setProperties: function( markerData ) {
            markerData.draggable = false;
            if ( typeof markerData.id === 'undefined' || markerData.id == 0 ) {
                markerData.draggable = true;
                markerData.id 	     = 0;
                markerData.store     = wpslLabels.startPoint;
            }

            markerData = helpers.markers.setMarkerUrl( markerData );

            return wp.hooks.applyFilters( 'wpslMarkerProperties', markerData );
        },

        /**
         * Set the correct URL to load
         * the marker image from.
         *
         * @since   3.0.0
         * @param   {object} markerData
         * @returns {object} markerData in including the markerUrl
         */
        setMarkerUrl: function( markerData ) {
            const settings = helpers.markers.getSettings();

            /*
             * With labels on, config.markers holds the Studio pins that take
             * a label. [wpsl_map] markers are never labelled, so they keep
             * the pins from the settings page.
             */
            const unlabelled = ( markerData.mapShortcode && config.markers.unlabelled ) ? config.markers.unlabelled : null;
            const storeMarker = unlabelled ? unlabelled.store : config.markers.store;

            if ( unlabelled && ! markerData.locationMarkerUrlActive && ! markerData.categoryMarkerUrlActive ) {
                markerData.categoryMarkerUrlActive = helpers.markers.resolveMarkerSrc( unlabelled.active, settings.url );
            }

            if ( typeof markerData.id === 'undefined' || markerData.id === 0 ) {
                markerData.markerUrl = helpers.markers.resolveMarkerSrc( config.markers.start, settings.url );
            } else if ( typeof markerData.alternateMarkerUrl !== 'undefined' && markerData.alternateMarkerUrl ) {
                markerData.markerUrl = markerData.alternateMarkerUrl;

                delete markerData.alternateMarkerUrl;
            } else if ( typeof markerData.locationMarkerUrl !== 'undefined' && markerData.locationMarkerUrl ) {
                markerData.markerUrl = markerData.locationMarkerUrl;

                delete markerData.locationMarkerUrl;
            } else if ( typeof markerData.categoryMarkerUrl !== 'undefined' && markerData.categoryMarkerUrl ) {
                markerData.markerUrl = markerData.categoryMarkerUrl;

                delete markerData.categoryMarkerUrl;
            } else {
                markerData.markerUrl = helpers.markers.resolveMarkerSrc( storeMarker, settings.url );
            }

            return markerData;
        },

        /**
         * Get the markers settings.
         *
         * @since   3.0.0
         * @returns {object} settings
         */
        getSettings: function() {
            const settings = {};
            const markerProps = config.markers;

            // Use the correct marker path.
            if ( typeof markerProps.url !== 'undefined' ) {
                settings.url = markerProps.url;
            } else {
                settings.url = config.url + 'assets/img/frontend/markers/';
            }

            jQuery.extend( settings, markerProps );

            return settings;
        },

        /**
         * Resolve a configured marker value to the image src the map should load.
         *
         * @since 3.0.0
         * @param   {string} marker  The configured marker value ( filename, URL or data URI )
         * @param   {string} baseUrl The marker directory URL, defaults to the configured one
         * @returns {string} The marker image src
         */
        resolveMarkerSrc: function( marker, baseUrl ) {
            if ( typeof marker === 'string' && /^(data:|https?:|\/\/)/i.test( marker ) ) {
                return marker;
            }

            const base = ( typeof baseUrl === 'string' ) ? baseUrl : helpers.markers.getSettings().url;

            return base + marker;
        },

        /**
         * Check whether a marker src points at SVG artwork.
         *
         * @since 3.0.0
         * @param   {string} src The marker image src
         * @returns {boolean} Whether the src is an SVG
         */
        isSvgSrc: function( src ) {
            return sharedHelpers.isSvgSrc( src );
        },

        /**
         * The size and anchor a custom marker's artwork asks to be drawn at.
         *
         * @since 3.0.0
         * @param   {string} src The marker image src
         * @returns {object|null} { width, height, anchor, popupAnchor, position, offset } or null
         */
        getCustomMarkerGeometry: function( src ) {
            return sharedHelpers.getCustomMarkerGeometry( src, config.markers.geometry || null );
        },

        /**
         * The short, stable name that identifies a marker image on Mapbox.
         *
         * @since 3.0.0
         * @param   {string} src The marker image src
         * @returns {string} The icon name
         */
        iconToken: function( src ) {
            if ( typeof src !== 'string' || ! src ) {
                return '';
            }

            if ( ! /^data:/.test( src ) ) {
                return src.split( '/' ).pop();
            }

            // FNV-1a, so the same URI always yields the same name.
            let hash = 0x811c9dc5;

            for ( let i = 0; i < src.length; i++ ) {
                hash ^= src.charCodeAt( i );
                hash = Math.imul( hash, 0x01000193 );
            }

            return 'custom-' + ( hash >>> 0 ).toString( 36 );
        },

        /**
         * Set the visibility of the start marker.
         *
         * @since   3.0.0
         * @param   {object} markerData
         * @returns {bool}   visibility
         */
        startVisibility: function( markerData ) {
            let visibility = true;

            if ( typeof markerData.id !== 'undefined' && markerData.id == 0 && helpers.markers.maybeSkipStartMarker() ) {
                visibility = false;
            }

            return visibility;
        },

        /**
         * Check if we need to skip the start marker.
         *
         * Skipping also hides the distance and the directions.
         *
         * @since  3.0.0
         * @return {boolean} skipStartMarker Whether or not the show the start marker
         */
        maybeSkipStartMarker: function() {
            let skipStartMarker = false;

            const basicMode = helpers.results.maybeUseBasicMode();

            if ( basicMode || config.markers.skipStart || config.search.namesEnabled ) {
                skipStartMarker = true;
            }

            const result = wp.hooks.applyFilters( 'wpslSkipStartMarker', skipStartMarker );

            return result;
        },

        /**
         * Check if we need to set the active marker.
         *
         * @since  3.0.0
         * @param  {string} markerId      The ID of the marker to activate
         * @param  {boolean} hasOwnActive Optional. Whether the marker carries its own active image
         * @return {boolean} Whether the active marker should be set
         */
        maybeSetActivemarker: function( markerId, hasOwnActive = false ) {
            if ( ! slData || typeof slData.directions !== 'object' || slData.directions === null ) {
                return false;
            }

            // A marker with its own active image passes even without a
            // global one: config.markers.active is only emitted when the
            // settings' active and store markers differ.
            if ( ! hasOwnActive && ( ! config || typeof config.markers !== 'object' || typeof config.markers.active !== 'string' ) ) {
                return false;
            }

            return ! slData.directions.active && markerId !== 0;
        },
    },

    popup: {
        /**
         * Pan/zoom can leave Leaflet/Mapbox popups with stale rasters; cycling
         * the popup's `display` forces a fresh paint.
         *
         * @since  3.0.0
         * @param  {object}   map        The Leaflet or Mapbox GL map instance
         * @param  {Function} getPopupEl Returns the currently open popup element, or a falsy value
         * @return {Function} Teardown function that removes the listeners
         */
        hardRepaintOnSettle: function( map, getPopupEl ) {
            // Without a map there is nothing to subscribe to, but still
            // return a teardown. Cosmetic fix only -- it must never block
            // a popup from opening.
            if ( ! map || 'function' !== typeof map.on ) {
                return function() {};
            }

            const repaint = function() {
                const el = getPopupEl();
                if ( ! el ) {
                    return;
                }

                const originalDisplay = el.style.display;

                el.style.display = 'none';
                void el.offsetHeight; // force the removal to take effect before restoring
                el.style.display = originalDisplay;
            };

            map.on( 'moveend', repaint );
            map.on( 'zoomend', repaint );

            return function() {
                map.off( 'moveend', repaint );
                map.off( 'zoomend', repaint );
            };
        },
    },

    formatting: {
        /**
         * Make sure the JSON is valid.
         *
         * @link   http://stackoverflow.com/a/20392392/1065294
         * @param  {string}         jsonString The JSON data
         * @return {object|boolean}	           The JSON string or false if it's invalid json.
         */
        tryParseJSON: function( jsonString ) {
            try {
                const o = JSON.parse(jsonString);

                // JSON.parse(false) and JSON.parse(1234) don't throw, and
                // typeof null === "object", hence the extra checks.
                if ( o && typeof o === 'object' && o !== null ) {
                    return o;
                }
            } catch ( e ) { }

            return false;
        },
    },

    /**
     * Decode HTML entities.
     *
     * @link	https://gist.github.com/CatTail/4174511
     * @since	2.0.4
     * @param	{string} str The string to decode.
     * @returns {string}     The string with the decoded HTML entities.
     */
    decodeHtmlEntity: function( str ) {
        if ( str ) {
            return str.replace( /&#(\d+);/g, function( match, dec ) {
                return String.fromCharCode( dec );
            });
        }
    },

    /**
     * Detect a touch-primary device ( coarse pointer, no hover ),
     * such as a phone or tablet.
     *
     * Replaces the old user-agent sniffing, which missed touch laptops.
     *
     * @since   3.0.0
     * @returns {boolean} Whether the device is touch-primary.
     */
    isTouchPrimary: function() {
        return window.matchMedia( '(hover: none) and (pointer: coarse)' ).matches;
    },

    /**
     * Extract lat/lng coordinates from different argument formats.
     *
     * - Google Maps: args.latLng with lat() and lng() functions
     * - Direct properties: args.lat and args.lng
     * - String coordinates: args.lat and args.lng as strings
     *
     * @since   3.0.0
     * @param   {object} args The arguments object containing coordinate data
     * @returns {object|null} Object with lat and lng properties, or null if invalid
     */
    extractCoordinates: function( args ) {
        let lat, lng;

        // Check for latLng object first (Leaflet/Mapbox style)
        if ( args.latLng ) {
            if ( typeof args.latLng.lat === 'function' && typeof args.latLng.lng === 'function' ) {
                // Google Maps style: lat() and lng() methods
                lat = args.latLng.lat();
                lng = args.latLng.lng();
            } else if ( args.latLng.lat !== undefined && args.latLng.lng !== undefined ) {
                // Leaflet/Mapbox style: lat and lng properties
                lat = args.latLng.lat;
                lng = args.latLng.lng;
            }
        } else if ( typeof args.lat === 'function' && typeof args.lng === 'function' ) {
            // Direct function methods
            lat = args.lat();
            lng = args.lng();
        } else if ( args.lat !== undefined && args.lng !== undefined ) {
            // Direct properties
            lat = args.lat;
            lng = args.lng;
        } else {
            return null;
        }

        // Validate the extracted coordinates
        const numLat = parseFloat( lat );
        const numLng = parseFloat( lng );

        if ( isNaN( numLat ) || isNaN( numLng ) ) {
            return null;
        }

        return {
            lat: numLat,
            lng: numLng
        };
    },

    /**
     * Make sure the duration from https://openrouteservice.org/ & https://mapbox.com
     * correctly shows in hrs or minutes
     *
     * @since   3.0.0
     * @param   {string} duration The duration returned by the openrouteservice / mapbox API
     * @returns {string} duration The formated duration
     */
    formatDirectionsDuration: function( duration ) {
        let hours, minutes, hourLabel, minuteLabel;

        hours = Math.floor( duration / 3600 );
        if ( hours > 0 ) {
            minutes     = Math.floor( ( duration % 3600 ) / 60 );
            hourLabel   = ( hours > 1 ) ? wpslLabels.hours : wpslLabels.hour;
            minuteLabel = ( minutes > 1 ) ? wpslLabels.mins : wpslLabels.min;

            duration = hours + ' ' + hourLabel + ' ' + minutes + ' ' + minuteLabel;
        } else {
            minutes = ( duration % 3600 ) / 60;

            if ( minutes.toString().charAt( 0 ) > 0 ) {
                duration    = minutes.toFixed( 1 );
                minuteLabel = ( duration > 1 ) ? wpslLabels.mins : wpslLabels.min;
                duration = duration + ' ' + minuteLabel;
            } else {
                duration = 0 + ' ' + wpslLabels.min;
            }
        }

        return duration;
    },

    /**
     * Format an API distance in the configured display unit.
     *
     * @since   3.0.0
     * @param   {string|number} distance The distance returned by the routing API
     * @param   {string}        unit     The unit of the API value ( 'km' or 'm' )
     * @returns {string}        distance The formatted distance, or '' for zero
     */
    formatDirectionsDistance: function( distance, unit ) {
        const distanceUnit = config.search.distanceUnit;

        let meters = parseFloat( distance ) || 0;

        if ( unit == 'km' ) {
            meters = meters * 1000;
        }

        if ( ! meters ) {
            return '';
        }

        if ( meters > 1000 ) {
            // Show in miles or km
            if ( distanceUnit == 'mi' ) {
                distance = ( ( meters / 1000 ) * 0.62137 ).toFixed( 1 ) + ' mi';
            } else {
                distance = ( meters / 1000 ).toFixed( 1 ) + ' km';
            }
        } else {
            // Show in ft or m
            if ( distanceUnit == 'mi' ) {
                distance = Math.round( meters / 0.3048 ) + ' ft';
            } else {
                distance = Math.round( meters ) + ' m';
            }
        }

        return distance;
    },

    /**
     * Generate the directions header HTML with conditional separator.
     *
     * @since   3.0.0
     * @param   {string} totalDistance The formatted total distance
     * @param   {string} totalDuration The formatted total duration
     * @returns {string} The HTML for the directions header
     */
    formatDirectionsHeader: function( totalDistance, totalDuration ) {
        const separatorClass = ( totalDistance && totalDistance !== '0 mi' && totalDistance !== '0 km' && totalDistance !== '0 ft' && totalDistance !== '0 m' ) ? '' : ' wpsl-hidden';
        return '<div class="wpsl-direction-before"><a class="wpsl-back" id="wpsl-direction-start" href="#">' + wpslLabels.back + '</a><div><span class="wpsl-total-distance">' + totalDistance + '</span><span class="wpsl-direction-separator' + separatorClass + '"> - </span><span class="wpsl-total-durations">' + totalDuration + '</span></div></div>';
    },

    /**
     * When no results / directions are found, or when the
     * API request returns an error code we notify the user.
     *
     * @since   3.0.0
     * @param   {string} notice The message to show
     * @param   {string} type   The notice context ( 'geocode' or 'directions' )
     * @returns {void}
     */
    createUserNotice: function( notice, type ) {
        if ( type == 'geocode' ) {
            jQuery( '#wpsl-wrap' ).addClass( 'wpsl-no-results' );
            jQuery( '#wpsl-stores ul' ).html( '<li class="wpsl-no-results-msg">' + notice + '</li>' );

            if ( helpers.flexboxAvailable() ) {
                jQuery( '#wpsl-result-list' ).show();
            }
        } else if ( type == 'directions' ) {
            const message = '<p>' + notice + '</p>';

            // Remove the directions marker and restore
            // the previous results on the map.
            if ( slData.directions.active ) {
                slData.provider.api.directions.restoreResults();
            }

            jQuery( '#wpsl-map' ).append( '<div class="wpsl-api-message">' + message + '<a id="wpsl-close-api-notice" href="#">' + wpslLabels.close + '</a></div>' );

            responseHandlers.directions.bindApiNotice();
        }

        jQuery( '#wpsl-search-btn' ).attr( 'disabled', false );
    },

    /**
     * Show a dismissible notice inside the search block for problems that
     * leave the locator working. It must not take over the result list, and
     * the vertical template's fixed-height panel makes anything added here
     * come straight out of the results -- so it renders once and is
     * dismissible.
     *
     * @since   3.0.0
     * @param   {string} body Escaped HTML for the notice body. The caller owns
     *                        the escaping, since the text can carry a link.
     * @returns {void}
     */
    createInlineNotice: function( body ) {
        const $search = jQuery( '#wpsl-wrap .wpsl-search' ).first();

        // Dragging the start marker or running a second search repeats the
        // same failing request, so this has to stay idempotent.
        if ( ! $search.length || $search.find( '.wpsl-inline-notice' ).length ) {
            return;
        }

        const dismissLabel = wpslLabels.dismissNotice || '';
        const closeIcon    = wpslLabels.closeIcon || '';

        $search.append(
            '<div class="wpsl-inline-notice" role="status">' +
                '<div class="wpsl-inline-notice-body">' + body + '</div>' +
                '<button type="button" class="wpsl-inline-notice-close" aria-label="' + sharedHelpers.escapeHtml( dismissLabel ) + '">' + closeIcon + '</button>' +
            '</div>'
        );

        $search.on( 'click', '.wpsl-inline-notice-close', function() {
            jQuery( this ).closest( '.wpsl-inline-notice' ).remove();
        });
    },

    /**
     * Convert a hyphenated string to camelCase.
     *
     * @since   3.0.0
     * @param   {string} str The hyphenated string to convert
     * @returns {string} The camelCase version of the string
     */
    toCamelCase: function( str ) {
        return str.replace( /-([a-z])/g, function( match, letter ) {
            return letter.toUpperCase();
        });
    },

    /**
     * Validate if a string is a valid HTML color code
     *
     * @since 3.0.0
     * @param {string} color - Color code to validate (hex, rgb, rgba, hsl, hsla, or named colors)
     * @return {boolean}
     */
    isValidColor: function( color ) {
        if ( ! color || typeof color !== 'string' ) {
            return false;
        }

        const tempElem = document.createElement( 'div' );
        tempElem.style.color = color;

        // If the browser accepts it, style.color will be set
        return tempElem.style.color !== '';
    },

    /**
     * Setup click-outside handler to close autocomplete suggestions.
     * 
     * @since 3.0.0
     * @param {string} containerSelector - jQuery selector for the autocomplete container
     * @param {string} resultsSelector - jQuery selector for the results/suggestions element
     * @return {void}
     */
    setupAutocompleteClickOutside: function( containerSelector, resultsSelector ) {
        jQuery( document ).off( 'click.wpslAutocomplete' ).on( 'click.wpslAutocomplete', function( e ) {
            const $target = jQuery( e.target );
            const $autocomplete = jQuery( containerSelector );
            
            if ( ! $autocomplete.length ) {
                return;
            }
            
            if ( ! $target.closest( containerSelector ).length ) {
                const $suggestions = jQuery( resultsSelector );
                
                if ( $suggestions.is( ':visible' ) ) {
                    $suggestions.hide();
                }
            }
        });
    }
};