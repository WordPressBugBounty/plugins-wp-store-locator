import { state } from '../wpsl-shared.js';
import { preloader } from '../wpsl-preloader.js';

/**
 * Error handling helpers
 * 
 * @since 3.0.0
 */
export const errors = {
    /**
     * Extract error information from AJAX response
     * 
     * @since  3.0.0
     * @param  {object} response The AJAX response object
     * @return {object} error    The extracted error information
     */
    getAjaxErrors: function( response ) {
        const error = {
            status: response.status
        };

        if ( typeof response.responseJSON === 'object' ) {
            if ( response.responseJSON.message ) {
                error.message = response.responseJSON.message;
            } else if ( typeof response.responseJSON.error === 'object' ) {
                error.message = response.responseJSON.error.message;
            }
        } else if ( typeof response.statusText !== 'undefined' ) {
            error.message = response.statusText;
        }

        return error;
    },
    
    /**
     * Apply an error style to the input field and exclamation 
     * mark for the custom Mapbox style URL.
     *
     * @since   3.0.0
     * @param   {string} $elem The clicked radio button
     * @param   {string} $span The exclamation mark span
     * @param   {string} type  The message type to show
     * @returns {void}
     */
    applyMapErrorStyle: function( $elem, $span, type ) {
        if ( ! $elem.parents( 'p' ).find( '.wpsl-warning .wpsl-info-text' ).length ) {
            $span.addClass( 'wpsl-warning' );
            $span.find( '.wpsl-info-text' ).text( wpslL10n[type] );

            $elem.parents( 'p' ).find( 'input[type=text]' ).addClass( 'wpsl-error' ).end().find( '.wpsl-info' ).trigger( 'mouseout' );
        }
    },

    /**
     * Console error handling.
     *
     * @since 3.0.0
     */
    console: {
        /**
         * Modify the captured Google JavaScript API error message so it links
         * to the WPSL docs on configuring the required API keys.
         *
         * @since   3.0.0
         * @param   {string} error         The error message
         * @param   {string} url           URL that was included in the error message
         * @param   {string} customDetails Optional replacement body for the error details
         * @returns {void}
         */
        maybePrepareResponse: function( error, url, customDetails ) {
            preloader.remove();

            if ( error.length && url.indexOf( 'http' ) === 0 ) {
                let detailsBody;

                if ( customDetails && customDetails.length ) {
                    detailsBody = customDetails;
                } else {
                    const safeUrl = encodeURI( url );
                    const tempNode = document.createElement( 'span' );
                    tempNode.textContent = error;
                    const safeError = tempNode.innerHTML;

                    detailsBody = '<p><a href="' + safeUrl + '">' + safeError + '</a></p>' + wpslApiErrors.gmaps.errorNoticeFooter;
                }

                const noticeArgs = {
                    msg: wpslApiErrors.gmaps.errorReturned,
                    details: detailsBody
                };

                // Make sure to show the message on the map that's used to preview the map styles.
                jQuery( '#wpsl-gmaps-wrap' ).addClass( 'wpsl-api-message' ).html( '<p>' + wpslL10n.mapLoadFailed + '</p><p>' + noticeArgs.msg + '</p>' + noticeArgs.details );

                if ( ! this.ignoreNotice( error ) ) {
                    import( /* webpackChunkName: "verify-keys" */ '../settings/wpsl-verify-keys.js' ).then( ( { verifyKeys } ) => {
                        verifyKeys.createResponseMsg( noticeArgs, 'browser', 'error');
                        verifyKeys.updateValidationStatus( 'gmaps_browser', 0 );
                    });
                }
            }
        },

        /**
         * Ignore the NoApiKeys warning while both API key fields are empty,
         * so the user gets a chance to provide a key before being warned.
         *
         * @param  {string} message The captured message from the console.
         * @return {boolean}
         */
        ignoreNotice: function( message ) {
            let ignore = false;

            if ( state.mapService !== 'gmaps' || ( message.search( 'NoApiKeys' ) !== -1 && ! jQuery( '#wpsl-api-server-key' ).val().length ) ) {
                ignore = true;
            }

            return ignore;
        }
    },
};