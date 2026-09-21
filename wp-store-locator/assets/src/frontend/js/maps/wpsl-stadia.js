import { slData, config } from '../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { helpers } from '../modules/wpsl-helpers.js';
import { buttons } from '../modules/wpsl-buttons.js';
import { createApiRequest } from '../../../common/wpsl-core.js';
import { responseHandlers } from '../modules/wpsl-response-handlers.js';
import { preloader } from '../modules/wpsl-preloader.js';
import { search } from '../modules/wpsl-search.js';
import { parseStadiaFeature, stadiaFeatureLine } from './stadia/wpsl-parse-feature.js';
import { decodeStadiaShape } from './stadia/wpsl-route-shape.js';
import { reverseGeocodeDenied, reverseDeniedBody } from '../../../common/wpsl-stadia-reverse-gate.js';
import { nominatimToStadiaResponse } from './stadia/wpsl-nominatim-feature.js';

// Stadia uses the same Leaflet-based modules as OSM
import { map } from './osm/modules/wpsl-map.js';
import { markers } from './osm/modules/wpsl-markers.js';
import { infoWindow } from './osm/modules/wpsl-infowindow.js';
import { search as osmSearch } from './osm/modules/wpsl-search.js';
import { api as osmApi } from './osm/modules/wpsl-api.js';
import { shapes } from './osm/modules/wpsl-shapes.js';
import { osm } from './wpsl-osm.js';

/**
 * Stadia Maps implementation for WPSL frontend
 *
 * @since 3.0.0
 */

/**
 * Stadia-specific search utilities.
 * Overrides OSM search to parse Pelias GeoJSON properties.
 */
const stadiaSearch = Object.assign( {}, osmSearch, {
    /**
     * Collect statistics data from the Stadia Maps Geocoding API response.
     *
     * @since  3.0.0
     * @param  {object} response GeoJSON FeatureCollection from Pelias
     * @return {void}
     */
    collectStatsData: function( response ) {
        let statsData = {};

        if ( response && response.features && response.features[0] ) {
            const feature = parseStadiaFeature( response.features[0] );

            if ( feature.city ) {
                statsData.location = feature.city;
            } else if ( feature.region ) {
                statsData.location = feature.region;
            }

            if ( feature.region ) {
                statsData.region = feature.region;
            }

            if ( feature.country ) {
                statsData.country = feature.country;
            }
        }

        slData.statistics = wp.hooks.applyFilters( 'wpslStatsData', statsData, response );
    }
});

/**
 * Stadia API module.
 * Overrides OSM geocoding and directions with Stadia's own APIs.
 */
const api = {
    /**
     * Stadia Maps Autocomplete API methods.
     *
     * @since 3.0.0
     */
    autoComplete: {
        debounceTimer: null,
        currentResults: [],
        restrictionCountry: '',
        requestSeq: 0,

        /**
         * Activate the autocomplete for the location search.
         *
         * @since  3.0.0
         * @return {void}
         */
        init: function() {
            const self = this;
            const $input = jQuery( '#wpsl-search-input' );

            if ( ! $input.length ) {
                return;
            }

            // Wrap the input in a positioned container so the absolutely-positioned
            // results are constrained to the input width (matching Gmaps approach).
            let $container = jQuery( '.wpsl-autocomplete-search-container' );

            if ( ! $container.length ) {
                $input.wrap( '<div class="wpsl-autocomplete-search-container"></div>' );
                $container = jQuery( '.wpsl-autocomplete-search-container' );
            }

            // Create a results container if it doesn't exist
            let $results = jQuery( '#wpsl-stadia-autocomplete-results' );
            if ( ! $results.length ) {
                $results = jQuery( '<div id="wpsl-stadia-autocomplete-results" class="wpsl-autocomplete-search-results" style="display:none;"></div>' );
                $input.after( $results );
            }

            // Handle input events with debounce
            $input.on( 'input.wpslStadiaAutocomplete', function() {
                const query = jQuery( this ).val().trim();

                // Clear autocomplete coordinates when user modifies input manually
                slData.autoCompleteLatLng = '';

                if ( self.debounceTimer ) {
                    clearTimeout( self.debounceTimer );
                }

                if ( query.length < 3 ) {
                    self.hideResults();
                    return;
                }

                self.debounceTimer = setTimeout( function() {
                    self.fetchSuggestions( query );
                }, 300 );
            });

            // Handle keyboard navigation
            $input.on( 'keydown.wpslStadiaAutocomplete', function( e ) {
                const $items = $results.find( '.wpsl-autocomplete-suggestion' );
                const $current = $items.filter( '.wpsl-selected' );
                let $next;

                switch ( e.key ) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if ( $current.length === 0 ) {
                            $items.first().addClass( 'wpsl-selected' );
                        } else {
                            $next = $current.next();
                            if ( $next.length ) {
                                $current.removeClass( 'wpsl-selected' );
                                $next.addClass( 'wpsl-selected' );
                            }
                        }
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        if ( $current.length > 0 ) {
                            $next = $current.prev();
                            if ( $next.length ) {
                                $current.removeClass( 'wpsl-selected' );
                                $next.addClass( 'wpsl-selected' );
                            }
                        }
                        break;
                    case 'Enter':
                        if ( $current.length > 0 ) {
                            e.preventDefault();
                            $current.trigger( 'click' );
                        }
                        break;
                    case 'Escape':
                        self.hideResults();
                        break;
                }
            });

            // Handle suggestion clicks
            $results.on( 'click', '.wpsl-autocomplete-suggestion', function() {
                const index = jQuery( this ).data( 'index' );
                self.selectSuggestion( index );
            });

            // Handle keyboard events on suggestion items (for Tab navigation)
            $results.on( 'keydown', '.wpsl-autocomplete-suggestion', function( e ) {
                if ( e.key === 'Enter' || e.key === ' ' ) {
                    e.preventDefault();
                    const index = jQuery( this ).data( 'index' );
                    self.selectSuggestion( index );
                }
            });

            helpers.setupAutocompleteClickOutside( '.wpsl-autocomplete-search-container', '#wpsl-stadia-autocomplete-results' );

            buttons.bindSearch();
        },

        /**
         * Fetch autocomplete suggestions from the Stadia Maps API.
         *
         * @since  3.0.0
         * @param  {string} query The search text
         * @return {void}
         */
        fetchSuggestions: function( query ) {
            const self = this;
            const params = {
                text: query,
                size: 5
            };

            // Add API key
            if ( config.api.key ) {
                params.api_key = config.api.key;
            }

            // Add language if set
            if ( typeof config.api.language === 'string' && config.api.language ) {
                params.lang = config.api.language;
            }

            // Restrict the searched layers if defined via the
            // wpsl_stadia_autocomplete_layers PHP filter.
            if ( typeof config.api.layers === 'string' && config.api.layers ) {
                params.layers = config.api.layers;
            }

            // Country restriction precedence: a live selection in the country
            // dropdown, then a [wpsl country="..."] shortcode, then the settings page.
            const restrictionCountry = this.restrictionCountry || helpers.search.getShortcodeCountry() || config.api.regions;

            if ( typeof restrictionCountry === 'string' && restrictionCountry ) {
                params['boundary.country'] = restrictionCountry;
            }

            const ajaxParams = jQuery.param( params );

            const apiBase = config.api.euEndpoints ? 'https://api-eu.stadiamaps.com' : 'https://api.stadiamaps.com';

            // Track the request order. Network responses can arrive out of order,
            // so we ignore any response that has been superseded by a newer one.
            const requestId = ++this.requestSeq;

            jQuery.getJSON( apiBase + '/geocoding/v2/autocomplete?' + ajaxParams, function( response ) {
                if ( requestId !== self.requestSeq ) {
                    return;
                }

                if ( response && response.features && response.features.length > 0 ) {
                    self.currentResults = response.features;
                    self.showResults( response.features );
                } else {
                    self.hideResults();
                }
            }).fail( function() {
                if ( requestId !== self.requestSeq ) {
                    return;
                }

                self.hideResults();
            });
        },

        /**
         * Build the legally required Stadia Maps attribution 
         * markup shown below the autocomplete suggestions.
         *
         * @since  3.0.0
         * @see    https://docs.stadiamaps.com/attribution/
         * @return {string} The attribution HTML.
         */
        getAttributionHtml: function() {
            return '<div class="wpsl-search-attribution">' +
                'Powered by <a href="https://stadiamaps.com/" target="_blank" rel="noopener noreferrer">Stadia Maps</a>' +
                ' &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer nofollow">OpenStreetMap</a> contributors' +
                ' &amp; <a href="https://stadiamaps.com/attribution/" target="_blank" rel="noopener noreferrer">others</a>' +
                '</div>';
        },

        /**
         * Display autocomplete suggestions.
         *
         * @since  3.0.0
         * @param  {array} features The GeoJSON features from the API response
         * @return {void}
         */
        showResults: function( features ) {
            const $results = jQuery( '#wpsl-stadia-autocomplete-results' );
            let html = '<ul>';

            const escape = function( text ) {
                return jQuery( '<div>' ).text( text ).html();
            };

            features.forEach( function( feature, index ) {
                const parsed  = parseStadiaFeature( feature );
                const context = parsed.label.indexOf( parsed.coarseLocation ) !== -1 ? '' : parsed.coarseLocation;

                html += '<li class="wpsl-autocomplete-suggestion" data-index="' + index + '" role="option" tabindex="0">';
                html += '<span class="wpsl-suggestion-name">' + escape( parsed.label ) + '</span>';

                // Same-named places on different continents are only told apart
                // by their coarse location, so it gets its own line.
                if ( context ) {
                    html += '<span class="wpsl-suggestion-context">' + escape( context ) + '</span>';
                }

                html += '</li>';
            });

            html += '</ul>';
            html += this.getAttributionHtml();
            $results.html( html ).show();

            // Match the autocomplete to the row, minus the search button.
            // Only the vertical template has .wpsl-search-wrap; elsewhere
            // alignSearchColumns() owns the width.
            const $searchWrap = jQuery( '#wpsl-search-input' ).closest( '.wpsl-search-wrap' );

            if ( $searchWrap.length ) {
                const submitButtonWidth = jQuery( '#wpsl-submit-wrapper' ).outerWidth() || 0;
                const availableWidth = $searchWrap.outerWidth() - submitButtonWidth;

                jQuery( '.wpsl-autocomplete-search-container' ).outerWidth( availableWidth );
                $results.outerWidth( availableWidth );
            }

            // The input is now wrapped in an autocomplete container, so
            // re-match the field and category dropdown widths.
            helpers.template.alignSearchColumns();
        },

        /**
         * Hide the autocomplete results.
         *
         * @since  3.0.0
         * @return {void}
         */
        hideResults: function() {
            jQuery( '#wpsl-stadia-autocomplete-results' ).hide().empty();
            this.currentResults = [];
        },

        /**
         * Handle selection of a suggestion.
         *
         * @since  3.0.0
         * @param  {number} index The index of the selected suggestion
         * @return {void}
         */
        selectSuggestion: function( index ) {
            const feature = this.currentResults[ index ];
            if ( ! feature ) {
                return;
            }

            const self   = this;
            const parsed = parseStadiaFeature( feature );
            const gid    = ( feature.properties && feature.properties.gid ) || '';

            // The coarse location rides along so the field, and the geocode
            // fallback below, keep the country the user actually picked.
            jQuery( '#wpsl-search-input' ).val( stadiaFeatureLine( parsed ) );

            this.hideResults();

            // When the suggestion already carries coordinates, use them directly.
            if ( typeof parsed.lat === 'number' && typeof parsed.lng === 'number' ) {
                slData.autoCompleteLatLng = { lng: parsed.lng, lat: parsed.lat };
                this.submitSelection();

                return;
            }

            // Suggestions come back with null geometry, so the coordinates have
            // to be looked up via the Place Details endpoint.
            if ( gid ) {
                this.fetchPlaceDetails( gid, function( coords ) {
                    slData.autoCompleteLatLng = coords || '';
                    self.submitSelection();
                });

                return;
            }

            // No coordinates and no gid to look up: fall back to geocoding the
            // label text ( still country-restricted via boundary.country ).
            slData.autoCompleteLatLng = '';
            this.submitSelection();
        },

        /**
         * Trigger the search for the selected suggestion when auto-submit is
         * enabled. Otherwise the cached coordinates wait for a manual submit.
         *
         * @since  3.0.0
         * @return {void}
         */
        submitSelection: function() {
            if ( config.search.autoSubmitAutoComplete ) {
                jQuery( '#wpsl-search-btn' ).trigger( 'click' );
            }
        },

        /**
         * Resolve the exact coordinates for a selected autocomplete suggestion
         * via the Stadia Maps Place Details endpoint.
         *
         * Autocomplete answers with a feature id ( gid ) but null geometry.
         *
         * @since  3.0.0
         * @see    https://docs.stadiamaps.com/geocoding-search-autocomplete/place-details/
         * @param  {string}   gid      The feature id, e.g. "geonames:locality:2872113".
         * @param  {Function} callback Receives { lng, lat } on success, or null on failure.
         * @return {void}
         */
        fetchPlaceDetails: function( gid, callback ) {
            const params = { ids: gid };

            if ( config.api.key ) {
                params.api_key = config.api.key;
            }

            const apiBase = config.api.euEndpoints ? 'https://api-eu.stadiamaps.com' : 'https://api.stadiamaps.com';
            jQuery.getJSON( apiBase + '/geocoding/v2/place_details?' + jQuery.param( params ), function( response ) {
                const feature = response && response.features && response.features[0];
                const coords  = feature && feature.geometry && feature.geometry.coordinates; // [lng, lat]
                if ( Array.isArray( coords ) && coords.length >= 2 ) {
                    callback( { lng: coords[0], lat: coords[1] } );
                } else {
                    callback( null );
                }
            }).fail( function() {
                callback( null );
            });
        },

        /**
         * Set country restrictions for autocomplete results.
         *
         * @since  3.0.0
         * @param  {object} restrictions Object with country property
         * @return {void}
         */
        setRestrictions: function( restrictions ) {
            if ( restrictions && restrictions.country ) {
                this.restrictionCountry = restrictions.country;
            }
        }
    },

    /**
     * Geocoding API methods for Stadia Maps.
     *
     * Uses the Stadia Maps Geocoding Search API (Pelias)
     * instead of Nominatim.
     *
     * @since 3.0.0
     */
    geocoding: {
        allowedQueryTypes: ['street', 'city', 'county', 'state', 'country', 'postcode'],

        /**
         * Get the latlng from the Stadia Maps Geocoding API.
         *
         * @since  3.0.0
         * @return {void}
         */
        getLatLng: function() {
            const self = this;

            const query = jQuery( '#wpsl-search-input' ).val().trim();
            if ( ! query ) {
                return;
            }

            preloader.add();

            self.requestGeocode( query, function( response ) {
                const feature = response && response.features && response.features[0];

                if ( feature && self.isUsableGeocodeResult( feature ) ) {
                    self.processResponse( response );
                    return;
                }

                // Stadia indexes some postcodes without their space, so
                // '2152 KC' falls back while '2152KC' matches exactly. Retry
                // once without the space before reporting no results.
                const retryQuery = self.getPostcodeRetryQuery( query );

                if ( ! retryQuery ) {
                    self.showNoResults();
                    return;
                }

                self.requestGeocode( retryQuery, function( retryResponse ) {
                    const retryFeature = retryResponse && retryResponse.features && retryResponse.features[0];

                    if ( retryFeature && self.isUsableGeocodeResult( retryFeature ) ) {
                        self.processResponse( retryResponse );
                    } else {
                        self.showNoResults();
                    }
                });
            });
        },

        /**
         * Run a geocode request for the passed search text.
         *
         * @since  3.0.0
         * @param  {string}   text     The text to geocode.
         * @param  {function} callback Receives the API response.
         * @return {void}
         */
        requestGeocode: function( text, callback ) {
            const params = {
                text: text,
                size: 1
            };

            if ( config.api.key ) {
                params.api_key = config.api.key;
            }

            if ( typeof config.api.language === 'string' && config.api.language ) {
                params.lang = config.api.language;
            }

            // A [wpsl country="..."] shortcode restriction overrides the
            // settings-page country restriction for this map instance.
            const geocodeCountry = helpers.search.getShortcodeCountry() || config.api.regions;

            if ( typeof geocodeCountry === 'string' && geocodeCountry ) {
                params['boundary.country'] = geocodeCountry;
            }

            // A newer search may start before Stadia answers, see search.current().
            createApiRequest.stadia.geocode( wp.hooks.applyFilters( 'wpslMakeGeocodeRequestArgs', params ), search.current( callback ) );
        },

        /**
         * Return the whitespace stripped version of a postcode shaped query,
         * or an empty string when the query should not be retried.
         *
         * Postcodes like '3319 RK' ( NL ), 'SW1A 1AA' ( UK ) and 'K1A 0B1'
         * ( CA ) are two short alphanumeric groups holding both a digit and a
         * letter. Anything else is a street or place name, where removing the
         * space would only corrupt the query.
         *
         * @since  3.0.0
         * @param  {string} query The original search query.
         * @return {string} The retry query, or '' to skip the retry.
         */
        getPostcodeRetryQuery: function( query ) {
            const trimmed = ( query || '' ).trim();

            let retry = '';

            if ( /^[a-z0-9]{2,4}\s+[a-z0-9]{2,4}$/i.test( trimmed )
                && /[0-9]/.test( trimmed )
                && /[a-z]/i.test( trimmed )
            ) {
                retry = trimmed.replace( /\s+/g, '' );
            } else {

                /**
                 * A postcode inside a longer address, '1100 AB Amsterdam',
                 * where the rest of the query has to survive untouched.
                 *
                 * Four digits and two letters is the Dutch format, the one
                 * Stadia indexes without its space. Deliberately narrow:
                 * collapsing any two short groups would rewrite a US address
                 * like '1234 NW 5th Ave' into '1234NW 5th Ave'.
                 */
                retry = trimmed.replace( /\b(\d{4})\s+([a-z]{2})\b/gi, '$1$2' );
            }

            // Nothing gained when stripping the whitespace changes nothing.
            if ( retry === trimmed ) {
                retry = '';
            }

            return wp.hooks.applyFilters( 'wpslStadiaPostcodeRetryQuery', retry, trimmed );
        },

        /**
         * Remove the preloader and show the no results notice.
         *
         * @since  3.0.0
         * @return {void}
         */
        showNoResults: function() {
            preloader.remove();

            responseHandlers.maybeShowResponseText( {
                userNotice: wpslLabels.noResults
            } );
        },

        /**
         * Check whether a geocode result actually matches what was searched for.
         *
         * Stadia returns something for nearly every query. When it can't match
         * the input it sets match_type to 'fallback' and returns a loosely
         * similar record instead - the postcode '2152 KC' returns the street
         * 'K.C. van der Wolfpark', about 100km away - so 'fallback' counts as
         * no result.
         *
         * Only 'fallback' is rejected. 'match' and 'interpolated' are both real
         * locations ( interpolated estimates a house number along a known
         * street ), and rejecting by name keeps any match_type Stadia adds
         * later usable by default.
         *
         * @since  3.0.0
         * @param  {object} feature A single GeoJSON feature from the response.
         * @return {boolean} Whether the result should be used.
         */
        isUsableGeocodeResult: function( feature ) {
            const parsed = parseStadiaFeature( feature );

            let usable = parsed.matchType !== 'fallback';

            /**
             * 'Force zip code only search'. Stadia has no server-side postcode
             * filter worth sending - layers=postalcode resolves '2152 KC' to
             * the neighbouring postcode 2152ND and calls it an exact match -
             * so the restriction is enforced here on the layer that came back.
             */
            if ( usable && config.api.types === 'postcode' ) {
                usable = parsed.layer === 'postalcode';
            }

            return wp.hooks.applyFilters( 'wpslStadiaUsableGeocodeResult', usable, feature );
        },

        /**
         * Process the returned geocode data from the Stadia Maps Geocoding API.
         *
         * @since  3.0.0
         * @param  {object} response GeoJSON FeatureCollection from Pelias
         */
        processResponse: function( response ) {
            const feature = response.features[0];
            const coords = feature.geometry.coordinates; // [lng, lat]

            let args = {
                lat: coords[1],
                lng: coords[0]
            };

            slData.skipReverseGeocode = true;

            args = helpers.search.maybeGetFullSearchData( args, response );
            args = helpers.search.processGeocodeResponse( args, response );

            search.prepare( args );
        },

        /**
         * Reverse geocode the passed coordinates using the Stadia Maps
         * Geocoding Reverse API.
         *
         * @since  3.0.0
         * @param  {object} args The coordinates and optionally whether it's from a draggable marker or not
         * @param  {Function} callback Callback function
         * @return {void}
         */
        reverse: function( args, callback ) {
            const self = this;

            let lat, lng;

            if ( typeof args.latLng === 'object' && typeof args.latLng.lat === 'function' ) {
                lat = args.latLng.lat();
                lng = args.latLng.lng();
            } else {
                lat = args.lat;
                lng = args.lng;
            }

            createApiRequest.stadia.geocode( wp.hooks.applyFilters( 'wpslGeocodeParam', { lat: lat, lon: lng } ), function( response ) {
                if ( response && response.features && response.features[0] ) {
                    args = responseHandlers.reverseGeocodeFinished( args, response );

                    callback( args );

                    return;
                }

                // Not fatal: the reverse geocode only supplies the address
                // label for coordinates we already have. It also fails with a
                // valid key, since Stadia's reverse endpoint 403s ( "Please
                // upgrade your account" ) on plans that do include forward
                // search, which used to kill the whole search with a
                // "technical problem" notice.
                if ( reverseGeocodeDenied( response ) ) {
                    self.nominatimReverse( lat, lng, function( converted ) {
                        if ( converted ) {
                            args = responseHandlers.reverseGeocodeFinished( args, converted );

                            callback( args );

                            return;
                        }

                        if ( Number( config.userCanManage ) ) {
                            // The fallback failed too, so the search field stays
                            // empty on every visit, which reads as a plugin bug.
                            // Tell whoever can act on it, and nobody else.
                            helpers.createInlineNotice( reverseDeniedBody( response, {
                                text:       wpslLabels.reverseDeniedText,
                                apiError:   wpslLabels.reverseDeniedApiError,
                                upgrade:    wpslLabels.reverseDeniedUpgrade,
                                upgradeUrl: wpslLabels.reverseDeniedUpgradeUrl,
                                request:    wpslLabels.reverseDeniedRequest,
                                adminOnly:  wpslLabels.reverseDeniedAdminOnly
                            } ) );
                        }

                        responseHandlers.reverseGeocodeFailed( args, response, callback );
                    });

                    return;
                }

                responseHandlers.reverseGeocodeFailed( args, response, callback );
            });
        },

        /**
         * Reverse geocode via Nominatim and answer in the Stadia shape.
         *
         * Used when the plan does not include Stadia's own reverse endpoint.
         *
         * @since  3.0.0
         * @param  {number}   lat      Latitude
         * @param  {number}   lng      Longitude
         * @param  {Function} callback Receives a Stadia v2 shaped response, or null.
         * @return {void}
         */
        nominatimReverse: function( lat, lng, callback ) {
            createApiRequest.osm.geocode( {
                format: 'json',
                lat: lat,
                lon: lng,
                addressdetails: '1'
            }, function( response ) {
                callback( nominatimToStadiaResponse( response ) );
            });
        },

        /**
         * Filter out different data from the Stadia Maps Geocoding API response.
         *
         * @since  3.0.0
         * @param  {object} response GeoJSON FeatureCollection from Pelias
         * @param  {string} format   Which data to grab from the response (zip, country, full address etc)
         * @return {object} userLocation The filtered API data
         */
        filterResponse: function( response, format ) {
            let userLocation = {};

            if ( response && response.features && response.features[0] ) {
                const feature = parseStadiaFeature( response.features[0] );

                if ( ! format ) {
                    // Administrative / boundary results drive the state / country
                    // "full search" behaviour. v2 keeps the layer on properties.layer.
                    if ( feature.layer === 'region' && feature.region ) {
                        userLocation.fullSearch = {
                            type: 'state',
                            query: jQuery( '#wpsl-search-input' ).val()
                        };
                    } else if ( feature.layer === 'country' && feature.country ) {
                        userLocation.fullSearch = {
                            type: 'country',
                            query: jQuery( '#wpsl-search-input' ).val() + ',' + feature.countryCode
                        };
                    }
                } else {
                    switch ( format ) {
                        case 'country':
                            userLocation.countryCode = feature.countryCode;
                            userLocation.country     = feature.country;
                            break;
                        case 'formatted_address':
                            userLocation.location = feature.label;
                            break;
                        case 'city':
                            userLocation.location = feature.city;
                            break;
                        case 'zip':
                            userLocation.zip = feature.postalCode;
                            break;
                    }
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
        }
    },

    /**
     * Directions API methods for Stadia Maps.
     *
     * Uses the Stadia Maps Routing API (Valhalla)
     * instead of OpenRouteService. The show, restoreResults,
     * removePolyline, and init methods are reused from the OSM
     * module since both use Leaflet with the same marker logic.
     *
     * @since 3.0.0
     */
    directions: Object.assign( {}, osmApi.directions, {
        /**
         * Show the directions on the map.
         *
         * Overrides the OSM version to reference the Stadia api
         * object instead of the OSM one for storeId / focusedStoreId.
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

            const self = this;

            jQuery.each( markerObjects, function( i ) {
                if ( markerObjects[i].options.storeId === 0 && ( typeof originLatLng === 'undefined' || originLatLng === '' ) ) {
                    originLatLng = markers.latLng[mapIndex][i];
                    directionMarkers.push( originLatLng );
                } else if ( markerObjects[i].options.storeId === self.storeId ) {
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
         * Handle clicks on the back button when the route directions are displayed.
         *
         * Overrides the OSM version to reference the Stadia api object.
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
                const focusedId = this.focusedStoreId;

                jQuery.each( activeMarkers, function( i, marker ) {
                    if ( marker.options.storeId === focusedId ) {
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
         * Calculate the route from the start to the end using the Stadia Maps Routing API (Valhalla).
         *
         * @since   3.0.0
         * @param   {object} originLatLng      The start coordinates [lat, lng]
         * @param   {object} destinationLatLng The end coordinates [lat, lng]
         * @return  {void}
         */
        calcRoute: function( originLatLng, destinationLatLng ) {
            const pathOptions = wp.hooks.applyFilters( 'wpslStadiaPathOptions', '' );
            const ajaxData = wp.hooks.applyFilters( 'wpslStadiaDirectionsApiParams', {
                action: 'stadia_directions',
                start: originLatLng[0] + ',' + originLatLng[1],
                end: destinationLatLng[0] + ',' + destinationLatLng[1]
            });

            let index, directionStops = '';

            jQuery( '#wpsl-stores li .wpsl-error' ).remove();

            this.bounds = [];

            jQuery( '#wpsl-direction-details' ).show();
            jQuery( '#wpsl-stores, #wpsl-result-filters' ).hide();

            preloader.add( 'loadingDirections', '#wpsl-direction-details ul' );

            createApiRequest.stadia.directions( ajaxData, function( response ) {
                preloader.remove();

                if ( response && response.trip && response.trip.legs && response.trip.legs[0] ) {
                    const leg = response.trip.legs[0];
                    const summary = response.trip.summary;

                    // Build bounds from the trip summary bounding box
                    if ( summary ) {
                        api.directions.bounds.push( [ summary.min_lat, summary.min_lon ] );
                        api.directions.bounds.push( [ summary.max_lat, summary.max_lon ] );

                        slData.maps[0].fitBounds( api.directions.bounds, { padding:[35, 35] } );
                    }

                    /**
                     * Draw the route polyline.
                     *
                     * L.Polyline.fromEncoded() can't be used here: it decodes
                     * with 5 decimals while the API always answers with 6, and
                     * its second argument sets the path style, not the
                     * precision.
                     */
                    const latLngs = decodeStadiaShape( leg.shape, L.PolylineUtil );

                    if ( latLngs.length ) {
                        const polyline = L.polyline( latLngs );

                        if ( ! jQuery.isEmptyObject( pathOptions ) ) {
                            polyline.setStyle( pathOptions );
                        }

                        polyline.addTo( slData.maps[0] );
                    }

                    // Render maneuver instructions
                    if ( leg.maneuvers && leg.maneuvers.length > 0 ) {
                        jQuery.each( leg.maneuvers, function( i ) {
                            index = i + 1;
                            const maneuver = leg.maneuvers[i];
                            const distance = maneuver.length ? maneuver.length.toFixed( 2 ) : '0.00';

                            directionStops = directionStops + "<li><div class='wpsl-direction-index'>" + index + ".</div><div class='wpsl-direction-txt'>" + sharedHelpers.escapeHtml( maneuver.instruction ) + "</div><div class='wpsl-direction-distance'>" + helpers.formatDirectionsDistance( distance, 'km' ) + "</div></li>";
                        });

                        const totalDistance = helpers.formatDirectionsDistance( summary.length.toFixed( 2 ), 'km' );
                        const totalDuration = helpers.formatDirectionsDuration( summary.time );

                        const attribution = 'Powered by Stadia Maps';

                        jQuery( '#wpsl-direction-details ul' ).empty().append( directionStops ).before( helpers.formatDirectionsHeader( totalDistance, totalDuration ) ).after( "<p class='wpsl-direction-after'>" + attribution + "</p>" );

                        helpers.directions.maybeAdjustViewport();
                    }

                    helpers.directions.focusBackButton();
                    helpers.directions.styling.init();
                } else {
                    if ( response && response.status === 404 ) {
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
            });
        }
    })
};

export const stadia = {
    self: null,

    // Expose modules
    map,
    markers,
    infoWindow,
    api,
    search: stadiaSearch,
    shapes,

    /**
     * Initialize Stadia Maps
     *
     * @since 3.0.0
     */
    init: function() {
        this.self = this;

        // A missing or invalid key is handled by the map-bootstrap key gate
        // before this init runs, so no key check is needed here.

        // Initialize marker properties (same as OSM)
        slData.markers.latLng = {};
        slData.markers.layer = '';
        slData.markers.currentMapIndex = 0;

        // Extend helpers before any maps are created
        this.extendHelpers();

        if ( ! config.search.directionRedirect ) {
            buttons.bindRemoveDirections();
        }
    },

    /**
     * Stadia Maps specific template helpers.
     * Reuses the OSM helpers, since both send the
     * external directions to openstreetmap.org.
     *
     * @since 3.0.0
     */
    templateHelpers: osm.templateHelpers,

    /**
     * Extend helpers.map with Stadia Maps specific functions.
     * Reuses the OSM extendHelpers since both use Leaflet.
     *
     * @since 3.0.0
     */
    extendHelpers: osm.extendHelpers
};