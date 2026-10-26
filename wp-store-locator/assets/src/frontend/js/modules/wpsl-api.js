/**
 * Public API methods for WPSL
 * 
 * @since 3.0.0
 */

import { slData } from './wpsl-shared.js';

const CANVAS_SELECTOR = '[class*="wpsl-canvas-"]';

/**
 * The index of the map a public API call is about: the map canvas is the
 * element itself, one of its ancestors, or its first descendant canvas.
 *
 * @since  3.1.1
 * @param  {HTMLElement} element The map canvas, an element inside it, or one that contains it.
 * @return {number} The map index, or -1 when there's no canvas.
 */
function mapIndexOf( element ) {
    if ( ! element ) {
        return -1;
    }

    const canvas = ( element.closest && element.closest( CANVAS_SELECTOR ) ) || ( element.querySelector && element.querySelector( CANVAS_SELECTOR ) );

    return canvas ? Array.prototype.indexOf.call( document.querySelectorAll( CANVAS_SELECTOR ), canvas ) : -1;
}

/**
 * The provider's markers module when it has the method and the map exists.
 *
 * @since  3.1.1
 * @param  {number} mapIndex The map index.
 * @param  {string} method   The markers method the call needs.
 * @return {object|null}
 */
function markersFor( mapIndex, method ) {
    const provider = slData.provider;

    if ( mapIndex < 0 || ! slData.maps[ mapIndex ] || ! provider || ! provider.markers || typeof provider.markers[ method ] !== 'function' ) {
        return null;
    }

    return provider.markers;
}

export const api = {
    /**
     * Handle map re-rendering in tabs.
     * 
     * @since 3.0.0
     * @param {HTMLElement} tabPanel The tab panel element containing the map
     *
     * @example
     * // Custom implementation
     * wpsl.api.handleTabSwitch( document.querySelector( '.my-tab-panel' ) );
     */
    handleTabSwitch: function( tabPanel ) {
        const $panel = jQuery( tabPanel );

        /*
         * The maps are indexed by the DOM order of their canvases, which is
         * what Shortcodes::show_store_locator() counts when it publishes
         * wpslMap_{n}. So the panel's canvas is found first, then its position
         * among all of them is the map it belongs to.
         *
         * This used to read `.wpsl-canvas` and a `data-map-index` attribute,
         * neither of which is rendered -- the canvas is `wpsl-canvas-{provider}`
         * and carries no data attribute -- so the lookup always missed and every
         * tab resized map 0.
         */
        const $canvas  = $panel.find( '[class*="wpsl-canvas-"]' ).first();
        const mapIndex = $canvas.length ? jQuery( '[class*="wpsl-canvas-"]' ).index( $canvas ) : 0;
        
        // Small delay to ensure tab is fully visible
        setTimeout( function() {
            if ( typeof slData !== 'undefined' && slData.provider && slData.provider.map ) {
                slData.provider.map.invalidateSize( mapIndex );
            }
        }, 50 );
    },

    /**
     * Pan a map to a location and open its info window, as a click would.
     *
     * Zooms only as far as needed to show a clustered marker; with
     * options.zoom it zooms to that level and still further when the
     * marker is clustered there. Returns false
     * when the map provider hasn't loaded yet ( map consent ), the map
     * doesn't exist, or the location isn't on it; never throws. A hidden
     * map must be shown, and handleTabSwitch() called, first.
     *
     * @since  3.1.1
     * @param  {HTMLElement}   element The map canvas, an element inside it, or one that contains it.
     * @param  {number|string} storeId The location ID.
     * @param  {object}        options options.zoom: zoom to at least this level.
     * @return {boolean} Whether the location was found and the focus started.
     *
     * @example
     * wpsl.api.focusLocation( document.querySelector( '.my-map-panel' ), 42 );
     */
    focusLocation: function( element, storeId, options = {} ) {
        try {
            const id       = Number( storeId );
            const mapIndex = mapIndexOf( element );
            const markers  = id ? markersFor( mapIndex, 'focus' ) : null;

            return markers ? !! markers.focus( id, mapIndex, options || {} ) : false;
        } catch ( error ) {
            return false;
        }
    },

    /**
     * Close the open info window on a map and restore its active marker, as
     * a click on the map would.
     *
     * Only the map that element belongs to is affected. Returns false when
     * the map provider hasn't loaded yet ( map consent ) or the map doesn't
     * exist; never throws. Closing a map without an open info window does
     * nothing and returns true.
     *
     * @since  3.1.1
     * @param  {HTMLElement} element The map canvas, an element inside it, or one that contains it.
     * @return {boolean} Whether the map was found.
     *
     * @example
     * wpsl.api.closePopups( document.querySelector( '.my-map-panel' ) );
     */
    closePopups: function( element ) {
        try {
            const mapIndex = mapIndexOf( element );
            const markers  = markersFor( mapIndex, 'closePopups' );

            return markers ? !! markers.closePopups( mapIndex ) : false;
        } catch ( error ) {
            return false;
        }
    },

    /**
     * Show only the passed locations on a map, or all of them again.
     *
     * Hidden locations also leave the clusters, so a cluster counts only the
     * locations it shows. A marker shared by locations at one address stays
     * while any of them is shown. Closes the open info window. Returns false
     * when the map provider hasn't loaded yet ( map consent ) or the map
     * doesn't exist; never throws. A map that loads new locations ( a search )
     * shows all of those.
     *
     * @since  3.1.1
     * @param  {HTMLElement}  element The map canvas, an element inside it, or one that contains it.
     * @param  {array|null}   ids     Location IDs to show, null to show all again.
     * @param  {object}       options options.fitBounds: fit the map to the shown locations.
     * @return {boolean} Whether the map was found.
     *
     * @example
     * // Only the three nearest stores, zoomed to them.
     * wpsl.api.showLocations( document.querySelector( '.my-map-panel' ), [ 12, 7, 31 ], { fitBounds: true } );
     */
    showLocations: function( element, ids, options = {} ) {
        try {
            const mapIndex = mapIndexOf( element );
            const markers  = markersFor( mapIndex, 'showLocations' );
            const list     = Array.isArray( ids ) ? ids.map( Number ).filter( Boolean ) : null;

            return markers ? !! markers.showLocations( list, mapIndex, options || {} ) : false;
        } catch ( error ) {
            return false;
        }
    }
};
