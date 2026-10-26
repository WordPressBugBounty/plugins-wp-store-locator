/**
 * Check whether a CDN or cache serves outdated store locator scripts.
 *
 * Runs in the admin's browser, so the requests take the same route through
 * the CDN as a visitor's ( a request from PHP to the site's own URL can skip
 * it ). See WPSL\Admin\Core\Asset_Cache for what the two requests tell.
 *
 * window.wpslAssetCacheCheck() runs it and resolves with the AJAX response.
 * The Home page calls it when a check is due and shows the alerts; the
 * "Outdated cached files" button in the Tools section is wired here.
 *
 * @since 3.1.1
 */
( function( $ ) {
    'use strict';

    const config = window.wpslAssetCache || null;

    if ( ! config || ! config.check || ! window.fetch ) {
        return;
    }

    /*
     * Headers that say a response came from a cache. Age is the standard one,
     * the others are what CloudFront, Cloudflare, Fastly, Varnish, nginx and
     * hosting caches send instead of or next to it.
     */
    const CACHE_HEADERS = [ 'x-cache', 'cf-cache-status', 'cache-status', 'x-cache-status', 'x-proxy-cache', 'x-litespeed-cache' ];

    /**
     * The header that shows a response was answered from a cache.
     *
     * @param  {Response} response
     * @return {string} The header as "name: value", or an empty string for a fresh response
     */
    function cacheHeader( response ) {
        for ( let i = 0; i < CACHE_HEADERS.length; i++ ) {
            const value = response.headers.get( CACHE_HEADERS[ i ] );

            if ( value && /\bhit\b/i.test( value ) ) {
                return CACHE_HEADERS[ i ] + ': ' + value;
            }
        }

        const age = parseInt( response.headers.get( 'age' ), 10 );

        return age > 0 ? 'age: ' + age : '';
    }

    /**
     * The script URL with a version nobody has requested before.
     *
     * New on every run: a second run with the same URL would find the copy
     * the first one left in the CDN, on a CDN that does respect the version.
     *
     * @return {string}
     */
    function probeUrl() {
        const url = new URL( config.check.url, window.location.href );

        url.searchParams.set( 'ver', 'wpsl-check-' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 8 ) );

        return url.toString();
    }

    /**
     * Fetch the script and see what the CDN answers.
     *
     * cache: 'no-store' keeps the admin's own browser cache out of it. It
     * also sends Cache-Control: no-cache, which CloudFront and Cloudflare
     * ignore by default, so they still answer from their cache.
     *
     * @return {Promise} Resolves with the AJAX response of the stored result
     */
    function run() {
        const options    = { cache: 'no-store', credentials: 'omit' };
        const sameOrigin = new URL( config.check.url, window.location.href ).origin === window.location.origin;

        // The URL visitors load: without the installed build in it, it's outdated.
        const current = window.fetch( config.check.url, options ).then( function( response ) {
            return response.ok ? response.text() : Promise.reject();
        } ).then( function( text ) {
            return text.indexOf( config.check.build ) === -1;
        } );

        /*
         * A version nobody requested: answered from a cache, the CDN ignores
         * the version. Another origin ( a CDN plugin that moves the scripts
         * to their own domain ) hides these headers, so it isn't asked.
         */
        const probe = ! sameOrigin ? Promise.resolve( '' ) : window.fetch( probeUrl(), options ).then( function( response ) {
            if ( ! response.ok ) {
                return Promise.reject();
            }

            const header = cacheHeader( response );

            // Only the headers are needed, not the file.
            if ( response.body && typeof response.body.cancel === 'function' ) {
                response.body.cancel().catch( function() {} );
            }

            return header;
        } );

        return Promise.all( [ current, probe ] ).then( function( results ) {
            return {
                stale:           results[0] ? 1 : 0,
                ignores_version: results[1] ? 1 : 0,
                header:          results[1]
            };
        }, function() {
            // A 404, a redirect to another origin, or no connection at all.
            return { failed: 1 };
        } ).then( function( fields ) {
            return $.post( config.ajaxUrl, $.extend( { action: config.action, nonce: config.nonce }, fields ) );
        } );
    }

    window.wpslAssetCacheCheck = run;

    /*
     * The button in the Tools section: run it now, and say what it found
     * under the button.
     */
    $( document ).on( 'click', '#wpsl-asset-cache-check', function( e ) {
        const $button = $( this );
        const $row    = $button.closest( 'p' );

        e.preventDefault();

        if ( $button.hasClass( 'disabled' ) ) {
            return;
        }

        $button.addClass( 'disabled' );
        $row.next( '.wpsl-asset-cache-result' ).remove();
        $button.after( '<img src="' + config.preloader + '" class="wpsl-preloader" width="16" height="16" alt="" />' );

        function show( type, message ) {
            const $notice = $( '<div class="notice inline wpsl-asset-cache-result"><p></p></div>' );

            $notice.addClass( 'notice-' + type ).find( 'p' ).text( message );
            $row.after( $notice );
        }

        run().then( function( response ) {
            if ( response && response.success ) {
                show( response.data.type, response.data.message );
            } else {
                show( 'error', ( response && response.data && response.data.message ) || config.i18n.requestFailed );
            }
        }, function() {
            show( 'error', config.i18n.requestFailed );
        } ).then( function() {
            $button.removeClass( 'disabled' ).next( '.wpsl-preloader' ).remove();
        } );
    } );
}( jQuery ) );
