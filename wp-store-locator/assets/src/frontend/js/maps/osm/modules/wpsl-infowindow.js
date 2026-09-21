import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { api } from './wpsl-api.js';

/**
 * OpenStreetMap info window handling
 * 
 * @since 3.0.0
 */
export const infoWindow = {
    /**
     * Bind info window actions.
     * 
     * @since 3.0.0
     * @param {object} latLng Coordinates
     * @return {void}
     */
    actions: function( latLng ) {
        this.closeOnEscKey();

        helpers.results.maybeShowZoomOption( slData.maps[0] );

        jQuery( '.wpsl-info-actions a' ).off( 'click' ).on( 'click', function( e ) {
            const maxZoom = config.map.autoZoomLevel;

            if ( jQuery( this ).hasClass( 'wpsl-directions' ) ) {
                if ( config.search.directionRedirect || ( ( config.api.provider === 'osm' || config.api.provider === 'stadia' ) && ! config.api.hasValidRouteKey ) ) {
                    return true;
                } else {
                    e.stopImmediatePropagation();
                    helpers.directions.scrollToTop();

                    api.directions.show( jQuery( this ) );
                    
                    return false;
                }
            } else if ( jQuery( this ).hasClass( 'wpsl-zoom-here' ) ) {
                e.stopImmediatePropagation();
                slData.maps[0].setView( latLng, maxZoom );
                
                return false;
            }
        });
    },

    /**
     * Make sure that the 'esc' key closes the open window.
     *
     * @since   3.0.0
     * @return {void}
     */
    closeOnEscKey: function() {
        const map = slData.maps[0];

        jQuery( document ).off( 'keyup.wpslOsmEsc' ).on( 'keyup.wpslOsmEsc', function( e ) {
            if ( e.keyCode === 27 && map ) {
                map.closePopup();
            }
        });
    }
};
