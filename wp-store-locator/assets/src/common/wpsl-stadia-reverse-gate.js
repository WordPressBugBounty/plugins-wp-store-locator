/**
 * The Stadia reverse geocode gate.
 *
 * The reverse geocode only supplies the address label for coordinates we
 * already have, so every failure is survivable and silence is the right
 * default, except for one case.
 *
 * Stadia sells the reverse endpoint separately from forward search, and
 * returns 403 on plans that include search but not reverse. The locator then
 * works apart from the search field never filling in, which reads as a plugin
 * bug.
 *
 * Standalone ( no jQuery / shared-state imports, own escaping rather than
 * sharedHelpers.escapeHtml, which needs a DOM ) so the node test suite can
 * exercise it directly.
 *
 * @since 3.0.0
 */

/**
 * Where an admin can change the plan the 403 is complaining about.
 *
 * wpsl_stadia_account_url() owns this and passes it through the labels. Kept
 * here as the fallback for a missing label, and so the node tests can assert.
 *
 * @since 3.0.0
 * @type  {string}
 */
export const stadiaAccountUrl = 'https://client.stadiamaps.com/dashboard/account/subscription-details/';

/**
 * Escape a string for insertion as HTML.
 *
 * @since   3.0.0
 * @param   {string} text The raw text
 * @returns {string} The escaped text
 */
function escapeHtml( text ) {
    return String( text )
        .replace( /&/g, '&amp;' )
        .replace( /</g, '&lt;' )
        .replace( />/g, '&gt;' )
        .replace( /"/g, '&quot;' )
        .replace( /'/g, '&#039;' );
}

/**
 * Replace the first %s without letting the replacement act as a pattern.
 * String.replace() expands $&, $1 and friends in the replacement, which here
 * is an API error string we don't control.
 *
 * @since   3.0.0
 * @param   {string} template    The string holding the %s
 * @param   {string} replacement The value to drop in
 * @returns {string}
 */
function fillPlaceholder( template, replacement ) {
    return template.replace( '%s', function() {
        return replacement;
    } );
}

/**
 * The endpoint that was refused, without its query string.
 *
 * @since   3.0.0
 * @param   {string} url The request URL
 * @returns {string} The URL up to the query string
 */
function endpointOnly( url ) {
    return String( url ).split( '?' )[0];
}

/**
 * Whether the reverse geocode failed because the account can't use the endpoint.
 *
 * Takes the jqXHR from the fail callback, but also sees successful responses
 * with no features, since both reach the same branch. Anything without a 403
 * is treated as transient.
 *
 * @since   3.0.0
 * @param   {object}  response The API response or the failed jqXHR
 * @returns {boolean} True when the endpoint is not available to this account
 */
export function reverseGeocodeDenied( response ) {
    if ( ! response || typeof response !== 'object' ) {
        return false;
    }

    return Number( response.status ) === 403;
}

/**
 * Build the notice body for a refused reverse geocode.
 *
 * Quotes Stadia's own error so the locator matches the console and support
 * forum searches.
 *
 * @since   3.0.0
 * @param   {object} response The failed jqXHR
 * @param   {object} labels   The localized strings ( text, apiError, upgrade,
 *                            request, adminOnly )
 * @returns {string} Escaped HTML, ready to insert
 */
export function reverseDeniedBody( response, labels ) {
    labels = labels || {};

    const error = ( response && response.responseJSON && typeof response.responseJSON.error === 'string' )
        ? response.responseJSON.error.trim()
        : '';

    const paragraphs = [];

    if ( labels.text ) {
        paragraphs.push( '<p>' + escapeHtml( labels.text ) + '</p>' );
    }

    if ( error && labels.apiError ) {
        paragraphs.push( '<p>' + fillPlaceholder( escapeHtml( labels.apiError ), escapeHtml( error ) ) + '</p>' );
    }

    // The action, on its own line: linked from inside the quoted error it put
    // the only way out mid sentence, in what reads as a machine message.
    if ( labels.upgrade ) {
        const upgradeUrl = labels.upgradeUrl || stadiaAccountUrl;

        paragraphs.push(
            '<p class="wpsl-inline-notice-cta">' +
                '<a href="' + escapeHtml( upgradeUrl ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( labels.upgrade ) + '</a>' +
            '</p>'
        );
    }

    // The failing request, so the notice matches the console. No cause is
    // named: border enforcement and the statistics add-on also force a reverse
    // geocode on a plain text search ( see wpsl-buttons.js ).
    const status = ( response && response.status ) ? String( response.status ) : '';
    const url    = ( response && typeof response.wpslRequestUrl === 'string' ) ? endpointOnly( response.wpslRequestUrl ) : '';

    if ( labels.request && ( status || url ) ) {
        paragraphs.push(
            '<p class="wpsl-inline-notice-meta">' +
                fillPlaceholder( escapeHtml( labels.request ), escapeHtml( ( status + ' ' + url ).trim() ) ) +
            '</p>'
        );
    }

    if ( labels.adminOnly ) {
        paragraphs.push( '<p class="wpsl-inline-notice-meta">' + escapeHtml( labels.adminOnly ) + '</p>' );
    }

    return paragraphs.join( '' );
}