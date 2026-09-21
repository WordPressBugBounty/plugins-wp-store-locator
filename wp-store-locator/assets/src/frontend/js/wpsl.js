import { setup } from './modules/wpsl-setup.js';
import { filters } from './modules/wpsl-filters.js';
import { api } from './modules/wpsl-api.js';
import { gdpr } from './modules/wpsl-gdpr.js';
import { setGdprModule } from '../../common/wpsl-core.js';

/**
 * The GDPR module is imported here rather than fetched at runtime.
 *
 * It used to be pulled in with importModule(), which resolves to
 * assets/dist/frontend/js/modules/ in production - a directory the build
 * never writes, so every consent handler 404'd without SCRIPT_DEBUG. Loading
 * it separately would give it a second copy of the slData / config it reaches
 * through wpsl-setup, leaving the map initialising against state nothing else
 * can see. Registering it from this entry point keeps it out of the admin
 * bundles, which share wpsl-core, and keeps the module graph free of cycles.
 */
setGdprModule( gdpr );

if ( typeof wpslSettings !== 'undefined' && ! wpslSettings.scriptDebug ) {
    __webpack_public_path__ = wpslSettings.url + 'assets/dist/';
}

// Assigned to window explicitly: a `var` in an ES module stays
// module-scoped, so the documented public wpsl.api would never exist.
window.wpsl = window.wpsl || {};

/**
 * Main entry point for WPSL frontend
 *
 * @since 3.0.0
 */

// Expose public API methods under wpsl.api namespace
window.wpsl.api = api;

/**
 * Prepare WPSL Frontend
 * 
 * @since 3.0.0
 */
function prepareWpslFrontend() {
    if ( typeof wpslSettings === 'undefined' ) {
        console.error( 'WPSL Settings not found' );
        return;
    }

    initWpslFrontend();

    wp.hooks.doAction( 'wpslFrontendInit' );
}

/**
 * Initialize WPSL Frontend
 * 
 * @since 3.0.0
 */
export async function initWpslFrontend() {
    setup.createConfig();

    filters.dropdowns.checkStyle();

    // Check if we need to setup the GDPR checkpoint, wait for a consent
    // plugin, or instantly load the appropriate map provider
    if ( wpslSettings.gdpr == 'wpsl' ) {
        await setup.handleGdpr();
    } else if ( wpslSettings.gdpr == 'complianz' ) {
        await gdpr.handleComplianz();
    } else {
        await setup.loadAndInit();
    }
};

// Initialize when DOM is ready
jQuery( document ).ready( function( $ ) {
    'use strict';

    const canvasElements = jQuery( '[class^="wpsl-canvas-"]' );

    // Deal with v2 legacy custom templates that haven't been updated yet.
    if ( ! canvasElements.length ) {
        const provider = wpslSettings.api.provider;
        const canvasClass = 'wpsl-canvas-' + provider;
        const mapElement = jQuery( '#wpsl-gmap' );
        
        if ( mapElement.length ) {
            mapElement.addClass( canvasClass );
            mapElement.attr( 'id', 'wpsl-map' );
        }
    }

    prepareWpslFrontend();
});