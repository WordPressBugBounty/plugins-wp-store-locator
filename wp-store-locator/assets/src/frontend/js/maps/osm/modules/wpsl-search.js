import { slData } from '../../../modules/wpsl-shared.js';
import { markers } from './wpsl-markers.js';

/**
 * OpenStreetMap search utilities
 * 
 * @since 3.0.0
 */
export const search = {
    /**
     * Used to check if there are locations visible on the map.
     *
     * @since 3.0.0
     * @return {number} The number of active markers on the map
     */
    activeMarkers: function() {
        const mapIndex = markers.currentMapIndex;
        return markers.active[mapIndex] ? markers.active[mapIndex].length : 0;
    },

    /**
     * Collect the data for the statistics.
     *
     * @since 	3.0.0
     * @param 	{object} response The Nominatim API response
     * @return {void}
     */
    collectStatsData: function( response ) {
        const locationDetails = ( typeof response[0] === 'object' ) ? response[0] : response;
        const fields = ['city', 'town', 'village', 'state', 'country'];

        let statsData = {};

        if ( typeof locationDetails === 'object' ) {
            jQuery.each( fields, function( index ) {
                if ( typeof locationDetails.address[ fields[index] ] === 'string' ) {
                    if ( fields[index] === 'state' ) {
                        statsData.region = locationDetails.address[ fields[index] ];
                    } else if ( fields[index] === 'city' || fields[index] === 'village' || fields[index] === 'town' ) {
                        statsData.location = locationDetails.address[ fields[index] ];
                    } else {
                        statsData[ fields[index] ] = locationDetails.address[ fields[index] ];
                    }
                }
            });
        }

        slData.statistics = wp.hooks.applyFilters( 'wpslStatsData', statsData, response );
    }
};
