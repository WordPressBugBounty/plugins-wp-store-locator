import { slData, config } from './wpsl-shared.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { helpers } from './wpsl-helpers.js';
import { search } from './wpsl-search.js';

/**
 * API response helpers
 * 
 * @since 3.0.0
 */
export const responseHandlers = {
    /**
     * Runs after a reverse geocode request is finished
     *
     * @since  3.0.0
     * @param  {object} args     Marker data and reverse geocode arguments
     * @param  {object} response API response data
     * @return {object} args
     */
    reverseGeocodeFinished: function( args, response ) {
        search.maybeSetSearchInput( args, response );

        // Process geocode response for directions, statistics, and borders
        args = helpers.search.processGeocodeResponse( args, response );

        wp.hooks.doAction( 'wpslReverseGeocodeFinished', args, response );

        return args;
    },

    /**
     * Runs after a reverse geocode request that returned nothing usable.
     *
     * It only supplies the address label for coordinates that are already
     * known, so a failure must not stop the search that was waiting on it.
     * Dropping the start marker on open water, or anywhere else the provider
     * has no address for, used to leave the locator doing nothing at all --
     * no new results, no notice, as if the drag never happened.
     *
     * Nothing is shown to the visitor either: the search runs, and it renders
     * its own results or its own "nothing found here". Whoever can act on a
     * real API problem ( key, quota ) gets it in the console.
     *
     * Stadia already worked this way for its reverse endpoint plan gate.
     *
     * @since  3.0.0
     * @param  {object}   args     Marker data and reverse geocode arguments
     * @param  {*}        response The API response, or the provider's status code
     * @param  {Function} callback The caller's continuation
     * @return {void}
     */
    reverseGeocodeFailed: function( args, response, callback ) {
        if ( Number( config.userLoggedin ) ) {
            this.consoleDebugInfo( response, 'geocode' );
        }

        // There is no address to write into the search field, and the flag
        // would otherwise survive into the next search and overwrite whatever
        // the visitor typed there.
        delete slData.setSearchInput;

        callback( args );
    },

    /**
     * Show the user a notice about the failed request, and log the
     * response text / status to the console for logged in users.
     *
     * @since   3.0.0
     * @param   {object} response The API response
     * @param   {string} type     Was it a request for directions data, or a geocode request?
     * @returns {void}
     */
    maybeShowResponseText: function( response, type = 'geocode' ) {
        let noticeText = wpslLabels.technicalProblem;

        if ( Number( config.userLoggedin ) && typeof response.responseJSON !== 'undefined' ) {
            this.consoleDebugInfo( response, type );
        }

        /*
         * Routes API disabled: admins get the label's %s as a link to the
         * Google Cloud console; everyone else gets plain text.
         */
        if ( response.isRoutesApiDisabled ) {
            const canManage    = Number( config.userCanManage );
            const enabledLabel = sharedHelpers.escapeHtml( response.enabledLinkLabel );
            const enabled      = canManage ? '<a target="_blank" rel="noopener noreferrer" href="' + sharedHelpers.escapeHtml( response.apiUrl ) + '">' + enabledLabel + '</a>' : enabledLabel;
            const notice       = sharedHelpers.escapeHtml( response.userNotice );

            if ( notice.includes( '%s' ) ) {
                noticeText = notice.replace( '%s', enabled );
            } else {
                // A site-owner label without the placeholder: only append the link, never a bare word.
                noticeText = canManage ? notice + ' ' + enabled : notice;
            }
        } else if ( typeof response.userNotice !== 'undefined' ) {

            // See if we need to replace the 'Due to a technical problem....'
            // text with a more detailed error text.
            noticeText = response.userNotice;
        } else {
            const apiError = this.getApiErrorMessage( response );

            // Show the reason returned by the API ( e.g. "Route exceeds maximum
            // distance limitation" ). The generic technical problem text advises
            // trying again later, which makes no sense for errors like these.
            if ( apiError ) {
                noticeText = sharedHelpers.escapeHtml( apiError );
            }
        }

        helpers.createUserNotice( noticeText, type );
    },

    /**
     * Get the error message from the API response.
     *
     * The location differs per provider: Mapbox and OSRM use "message",
     * Stadia / Valhalla use "error", and openrouteservice nests
     * it in "error.message".
     *
     * The Stadia and Openrouteservice directions requests are proxied
     * through admin-ajax, so their errors arrive as the full jqXHR with
     * the wp_send_json_error() body ( "data" holds the message the proxy
     * built from the API error ) one level deeper.
     *
     * @since   3.0.0
     * @param   {object} response The API response
     * @returns {string} The error message, or an empty string
     */
    getApiErrorMessage: function( response ) {
        let json = response.responseJSON;

        if ( json && typeof json === 'object' && json.responseJSON ) {
            json = json.responseJSON;
        }

        if ( ! json || typeof json !== 'object' ) {
            return '';
        }

        if ( json.success === false && typeof json.data === 'string' && json.data ) {
            return json.data;
        }

        if ( typeof json.message === 'string' && json.message ) {
            return json.message;
        }

        if ( typeof json.error === 'string' && json.error ) {
            return json.error;
        }

        if ( json.error && typeof json.error.message === 'string' ) {
            return json.error.message;
        }

        return '';
    },

    /**
     * Output API response data in the 
     * browser console for loggedin users.
     *
     * @since   3.0.0
     * @param   {object} response API response data
     * @param   {string} type     Was it a request for directions data, or a geocode request?
     * @returns {void}
     */
    consoleDebugInfo: function( response, type ) {
        console.log( '-- ' + wpslLabels.apiDebugInfo + ' --')
        console.log( wpslLabels.apiResponse + ': ', typeof response === 'object' ? JSON.stringify( response, null, 2 ) : response );

        if ( type == 'geocode' ) {
            switch ( config.api.provider ) {
                case 'mapbox':
                    console.log( wpslLabels.moreInfoConsoleError + ' https://docs.mapbox.com/api/search/geocoding/#geocoding-api-errors' );
                    break;
                case 'gmaps':
                    console.log( wpslLabels.moreInfoConsoleError + ' https://developers.google.com/maps/documentation/javascript/geocoding#GeocodingStatusCodes' );
                    break;
            }
        }
            
        console.log( '---------------------------------------' );
    },

    geocoding: {
        /**
         * Handle geocoding API errors
         *
         * @since   3.0.0
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
 
            // If the user is logged in, then show the exact status code.
            if ( Number( config.userLoggedin ) ) {
                responseHandlers.consoleDebugInfo( status, 'geocode' );
            }

            responseHandlers.maybeShowResponseText( response );
        }
    },

    directions: {
        /**
         * Handle directions API errors.
         *
         * @since 3.0.0
         * @param {object} response The API response
         * @returns {void}
         */
        errorHandler: function( response ) {
            jQuery( '.wpsl-api-message' ).remove();
    
            if ( typeof response.responseJSON !== 'undefined' ) { // API errors ( key related / connection issues )
                responseHandlers.maybeShowResponseText( response, 'directions' );
            } else {
                helpers.createUserNotice( wpslLabels.noDirectionsFoundMessage, 'directions' );
            }
        },

        /**
         * Make it possible to remove the
         * directions API error notice.
         *
         * @since   3.0.0
         * @returns {void}
         */
        bindApiNotice: function() {
            jQuery( '#wpsl-close-api-notice' ).on( 'click', function() {
                jQuery( '.wpsl-api-message, .wpsl-error' ).remove();
    
                return false;
            });
        }
    }
};