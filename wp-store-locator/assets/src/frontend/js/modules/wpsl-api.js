/**
 * Public API methods for WPSL
 * 
 * @since 3.0.0
 */

import { slData } from './wpsl-shared.js';

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
    }
};
