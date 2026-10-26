/**
 * The wpslMapReady action.
 *
 * @since 3.1.1
 */

import { slData } from './wpsl-shared.js';

/**
 * Fire wpslMapReady once for a map canvas.
 *
 * Called by each provider when its map has rendered once: Google on the
 * first 'idle', Leaflet on whenReady(), Mapbox on 'load'. [wpsl_map]
 * markers exist by then; store locator results arrive later, see the
 * wpslAjaxResultsFound action. The flag on the canvas keeps it to one call,
 * also when the bootstrap runs twice ( Borlabs ) and creates a new map
 * for the same canvas.
 *
 * On Leaflet the action runs synchronously at the end of map creation, so
 * handlers must be added before the map is created ( for example on
 * wpslFrontendInit ).
 *
 * It doesn't fire when the Mapbox style fails to load, since 'load' never
 * comes then. A handler that throws stops the handlers after it on the same
 * hook ( wp.hooks behaviour ); map creation carries on.
 *
 * @since  3.1.1
 * @param  {object}      map      The provider's map instance.
 * @param  {number}      mapIndex The map index, the DOM order of the map canvases.
 * @param  {HTMLElement} canvas   The map's DOM element.
 * @return {void}
 */
export function fireMapReady( map, mapIndex, canvas ) {
    // Keyed on the canvas, the map instance only when there's no canvas.
    const owner = canvas || map;

    if ( ! map || owner._wpslReadyFired ) {
        return;
    }

    owner._wpslReadyFired = true;

    if ( window.wp && window.wp.hooks ) {
        /**
         * Fires once per map canvas when its map has rendered for the first time.
         *
         * @since 3.1.1
         * @param {HTMLElement} canvas   The map's DOM element.
         * @param {number}      mapIndex The map index.
         * @param {string}      provider gmaps, osm, stadia or mapbox.
         * @param {object}      map      The provider's map instance.
         */
        try {
            window.wp.hooks.doAction( 'wpslMapReady', canvas, mapIndex, slData.providerName, map );
        } catch ( error ) {
            // Rethrow outside this call stack: the error still reaches the
            // console and error trackers, but map creation carries on.
            window.setTimeout( () => {
                throw error;
            } );
        }
    }
}
