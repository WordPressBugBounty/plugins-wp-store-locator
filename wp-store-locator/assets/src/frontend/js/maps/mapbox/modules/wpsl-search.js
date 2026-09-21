import { slData } from '../../../modules/wpsl-shared.js';
import { markers } from './wpsl-markers.js';

/**
 * Mapbox search utilities
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
     * @since 3.0.0
     * @param {object} response The Mapbox Geocoding API response
     * @return {void}
     */
    collectStatsData: function( response ) {
        const locationDetails = ( typeof response.features !== 'undefined' && response.features.length > 0 ) ? response.features[0] : response;
        
        let statsData = {};

        if ( typeof locationDetails === 'object' && typeof locationDetails.context !== 'undefined' ) {
            // Mapbox context array contains place types like: place, region, country, postcode
            jQuery.each( locationDetails.context, function( index, item ) {
                if ( typeof item.id === 'string' ) {
                    const placeType = item.id.split('.')[0];
                    
                    switch( placeType ) {
                        case 'place':
                            statsData.location = item.text;
                            break;
                        case 'region':
                            statsData.region = item.text;
                            break;
                        case 'country':
                            statsData.country = item.text;
                            break;
                    }
                }
            });
            
            // If no place found in context, try to get it from place_name
            if ( ! statsData.location && typeof locationDetails.place_name === 'string' ) {
                statsData.location = locationDetails.place_name.split(',')[0];
            }
        }

        slData.statistics = wp.hooks.applyFilters( 'wpslStatsData', statsData, response );
    }
};
