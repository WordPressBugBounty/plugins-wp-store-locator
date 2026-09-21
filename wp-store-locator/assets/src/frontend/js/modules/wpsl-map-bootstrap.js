import { slData, config } from './wpsl-shared.js';
import { createApiRequest } from '../../../common/wpsl-core.js';
import { mapKeyMissing } from '../../../common/wpsl-map-key-gate.js';
import { search } from './wpsl-search.js';
import { filters } from './wpsl-filters.js';
import { geolocation } from './wpsl-geolocation.js';
import { helpers } from './wpsl-helpers.js';
import { eventHandlers } from './wpsl-event-handlers.js';

// prepareWpsl() may only run once: with [wpsl] + N [wpsl_map] maps on one
// page every created map calls it, which stacked the delegated bindings
// ( and a possible autoload search ) N + 1 times.
let wpslPrepared = false;

/**
 * Map creation functionality for WPSL frontend
 *
 * @since 3.0.0
 */
export const mapBootstrap = {
    /**
     * Initialize map creation
     * 
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        const $mapCanvas = jQuery( `.wpsl-canvas-${config.api.provider}` );

        let mapId, mapCount = 0;

        // Without a key Mapbox GL throws before painting anything and Stadia's
        // tiles 401, leaving an unexplained blank. So for those providers a
        // missing key stops the whole boot here - no provider init, no
        // geolocation, no autoload - and every canvas ( [wpsl] and each
        // [wpsl_map] ) shows the key-required box instead. Google Maps is
        // excluded: its own error handler already shows the API's message.
        if ( mapKeyMissing( config.api ) ) {
            $mapCanvas.addClass( 'wpsl-key-required' ).html( '<p>' + wpslApiErrors[ config.api.provider ].keyRequiredMap + '</p>' );
            return;
        }

        slData.provider.init();
        
        // Call the provider's init function from createApiRequest to trigger hooks
        const activeProvider = createApiRequest.getActiveProvider();

        if ( typeof createApiRequest[activeProvider]?.init === 'function' ) {
            createApiRequest[activeProvider].init();
        }

        if ( $mapCanvas.length ) {
            jQuery( '<img alt="preloader" />' ).attr( 'src', config.search.preloader );

            // Show possible API errors on top of the map for logged in users
            if ( Number( config.userLoggedin ) ) {
                createApiRequest.monitorConsoleOutput();
            }

            // [wpsl] exists once per page, but [wpsl_map] can exist multiple
            // times, so loop over all of them.
            $mapCanvas.each( function( mapIndex ) {
                mapId = jQuery( this ).attr( 'id' );
                mapCount++;

                slData.provider.map.create( mapId, mapIndex );
            });

            // Auto-initialize jQuery UI tabs if the maps are placed inside them.
            this.maybeInitTabs( mapCount );
        }
    },

    /**
     * Only runs if jQuery UI tabs is loaded and tabs element exists
     * 
     * @since 3.0.0
     * @param {number} mapCount Number of maps on the page
     */
    maybeInitTabs: function( mapCount ) {
        if ( typeof jQuery.fn.tabs === 'undefined' ) {
            return;
        }
        
        const hasMainLocator = jQuery( '#wpsl-map' ).length > 0;
        
        if ( mapCount > 1 || ( mapCount === 1 && ! hasMainLocator ) ) {
            if ( jQuery( '#tabs' ).length ) {
                jQuery( '#tabs' ).tabs({
                    activate: function( event, ui ) {
                        if ( typeof window.wpsl !== 'undefined' && typeof window.wpsl.api.handleTabSwitch === 'function' ) {
                            window.wpsl.api.handleTabSwitch( ui.newPanel );
                        }
                    }
                });
            }
        }
    },

    /**
     * Required code for the [wpsl] shortcode.
     * 
     * Initializes filters, autocomplete, geolocation, and autoload functionality.
     *
     * @since 3.0.0
     * @returns {void}
     */
    prepareWpsl: function() {
        let args = {};

        if ( wpslPrepared ) {
            return;
        }

        wpslPrepared = true;

        // Only show the map after a search is made
        if ( config.search.inputOnly ) {
            search.bindAjaxComplete();
        }

        // See if we need to make the marker respond
        // to the user hovering over the search results.
        if ( typeof slData.provider.markers.checkMouseOverEvent === 'function' ) {
            slData.provider.markers.checkMouseOverEvent();
        }

        // Both markup variants need the dropdown style check; only the v3
        // template also has the advanced filter system to initialize.
        filters.dropdowns.checkStyle();

        if ( jQuery( '#wpsl-result-filters' ).length ) {
            filters.advanced.init(); //v3
        }

        // Delegated, so it also covers .wpsl-directions elements added later.
        eventHandlers.bindDirections();

        // Bind the keyboard skip link that bypasses map markers and jumps
        // focus to the search results (default + horizontal templates).
        eventHandlers.bindSkipToResults();

        // Check if we need to enable autocomplete,
        // and if the used map provider supports this.
        if ( config.search.autoComplete && typeof slData.provider.api.autoComplete === 'object' ) {
            if ( ! config.search.namesEnabled ) {
                slData.provider.api.autoComplete.init();
            }
        }

        // Check if we need to autolocate the user,
        // or autoload the store locations.
        if ( ! config.search.widgetEnabled ) {
            if ( config.search.autoLocate.enabled ) {
                geolocation.init();
            } else if ( config.search.autoLoad ) {
                slData.firstLoadInProgress = true;

                // If the country dropdown is present and a country is selected,
                // then set the search type to country to make sure all results
                // from the selected country are returned.
                //
                // @todo check filter values.
                if ( jQuery( '#wpsl-country .wpsl-selected-dropdown' ).length ) {
                    args = search.getAutoSubmitArgs();

                    search.autoSubmit( args );
                } else {
                    args.latLng = config.map.startLatLng;
                    
                    // Extract coordinates for AJAX request (handles both Google Maps LatLng objects and plain objects)
                    const coordinates = helpers.extractCoordinates( { latLng: config.map.startLatLng } );
                    
                    if ( coordinates ) {
                        args.lat = coordinates.lat;
                        args.lng = coordinates.lng;
                    }
                    
                    args.autoLoad = true;

                    search.prepare( args );
                }
            }
        }

        // Move the mousecursor to the store search
        // field if the focus option is enabled.
        if ( config.ux.mouseFocus && ! helpers.isTouchPrimary() ) {
            jQuery( '#wpsl-search-input' ).trigger( 'focus' );
        }

        helpers.search.checkWidgetSubmit();

        // The map exists now, so the credits may show. Held back by PHP for
        // every consent handler, including the ones that leave the rest of
        // the locator alone ( Borlabs ) and never reach revealSearch().
        jQuery( '#wpsl-wrap' ).removeClass( 'wpsl-credits-gated' );

        wp.hooks.doAction( 'wpslPrepareWpsl' );
    }
};
