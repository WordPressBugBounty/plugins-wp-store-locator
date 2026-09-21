/**
 * The tiny BBCode subset a map shape's message may carry.
 *
 * The server stores the admin-authored message through
 * sanitize_textarea_field(), so BBCode is the only formatting that survives
 * to the visitor. This turns it into HTML for a popup.
 *
 * The order below is the whole of its security: every character is escaped
 * first, and only the handful of tags we allow are put back. A message
 * containing "<script>" can therefore only come out as the text
 * "&lt;script&gt;", whatever the server did or did not strip.
 *
 * @since 3.0.0
 */

/**
 * Escape every character HTML gives meaning to.
 *
 * @since  3.0.0
 * @param  {string} value
 * @return {string}
 */
function escapeHtml( value ) {
    return String( value == null ? '' : value )
        .replace( /&/g, '&amp;' )
        .replace( /</g, '&lt;' )
        .replace( />/g, '&gt;' )
        .replace( /"/g, '&quot;' )
        .replace( /'/g, '&#039;' );
}

/**
 * A link target we are willing to emit, or ''.
 *
 * http and https only. The value has already been escaped, which cannot
 * introduce a scheme -- so anything that is not plainly http(s) here
 * ( javascript:, data:, vbscript:, a protocol-relative //host ) is refused
 * rather than sanitized, and its [url] tag is left as literal text.
 *
 * @since  3.0.0
 * @param  {string} escapedUrl
 * @return {string}
 */
function safeUrl( escapedUrl ) {
    const url = String( escapedUrl ).trim();

    return /^https?:\/\/[^\s"'<>]+$/i.test( url ) ? url : '';
}

/**
 * Convert a map shape message to the HTML a popup can show.
 *
 * Supported: [b] [i] [u] [br] and [url], the last as either [url]link[/url]
 * or [url=link]label[/url]. Unclosed or unknown tags stay as the literal
 * text they were typed as.
 *
 * @since  3.0.0
 * @param  {string} message The stored message.
 * @return {string} HTML, safe to inject.
 */
export function bbcodeToHtml( message ) {
    if ( typeof message !== 'string' || '' === message ) {
        return '';
    }

    let html = escapeHtml( message );

    // [url=target]label[/url] first: the bare form's pattern would otherwise
    // match the "=target]label" of this one as its own target.
    html = html.replace( /\[url=([^\]]+)\]([\s\S]*?)\[\/url\]/gi, function( match, target, label ) {
        const href = safeUrl( target );

        return href ? '<a href="' + href + '" rel="nofollow noopener">' + label + '</a>' : match;
    } );

    html = html.replace( /\[url\]([\s\S]*?)\[\/url\]/gi, function( match, target ) {
        const href = safeUrl( target );

        return href ? '<a href="' + href + '" rel="nofollow noopener">' + href + '</a>' : match;
    } );

    html = html
        .replace( /\[b\]([\s\S]*?)\[\/b\]/gi, '<strong>$1</strong>' )
        .replace( /\[i\]([\s\S]*?)\[\/i\]/gi, '<em>$1</em>' )
        .replace( /\[u\]([\s\S]*?)\[\/u\]/gi, '<u>$1</u>' )
        .replace( /\[br\]/gi, '<br>' );

    // The author's own line breaks, which BBCode never asked them to mark up.
    return html.replace( /\r\n|\r|\n/g, '<br>' );
}