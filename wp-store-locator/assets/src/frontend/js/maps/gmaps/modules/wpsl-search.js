import { slData } from '../../../modules/wpsl-shared.js';
import { api } from './wpsl-api.js';

/**
 * Google Maps search utilities.
 * 
 * @since 3.0.0
 */
export const search = {
    /**
     * Used to check if there are locations visible on the map.
     *
     * @since  3.0.0
     * @return {number} The number of active markers on the map
     */
    activeMarkers: function() {     
        const activeMarkers = slData.provider.markers.active[0] || [];
        return activeMarkers.length;
    },

    /**
     * Collect the data for the statistics
     * add-on from the Google Geocode API.
     *
     * @since  3.0.0
     * @param  {object} response The full Geocode API response
     * @return {void}
     */
    collectStatsData: function( response ) {
        const countryDetails = api.geocoding.filterResponse( response, 'country' );
        const statsData = {};

        let requiredFields,
            addressLength,
            responseType,
            missingFields = {};
        
        // The UK structures the city / town / region / country data
        // differently in the Geocode API response, so it needs its own field
        // map. Which field the city / town landed in is corrected further down.
        if ( countryDetails.countryCode === 'GB' ) {
            requiredFields = {
                'location': 'postal_town',
                'location_locality': 'locality,political',
                'region': 'administrative_area_level_2,political',
                'country': 'administrative_area_level_1,political'
            };
        } else {
            requiredFields = {
                'location': 'locality,political',
                'region': 'administrative_area_level_1,political',
                'country': 'country,political'
            };
        }

        addressLength = response[0].address_components.length;
        for ( let i = 0; i < addressLength; i++ ) {
            responseType = response[0].address_components[i].types;

            for ( let key in requiredFields ) {
                if ( requiredFields[key] === responseType.join( ',' ) ) {

                    // In rare cases the long name is empty.
                    if ( response[0].address_components[i].long_name.length > 0 ) {
                        statsData[key] = response[0].address_components[i].long_name;
                    } else {
                        statsData[key] = response[0].address_components[i].short_name;
                    }
                }
            }
        }

        // The first row usually holds every required field, but not always.
        for ( let key in requiredFields ) {
            if ( typeof statsData[key] === 'undefined' ) {
                missingFields[key] = requiredFields[key];
            }
        }

        // In the UK postal_town ( city ) carries the data, and locality is
        // only a backup for when it is missing from the API response - so a
        // filled postal_town makes a missing locality irrelevant.
        if ( countryDetails.countryCode === 'GB' ) {
            if ( typeof missingFields.location_locality !== 'undefined' && typeof missingFields.location === 'undefined' ) {
                missingFields = {};
            }
        }

        // Loop the remaining API data for whatever is still missing.
        if ( Object.keys( missingFields ).length > 0 ) {
            const responseLength = response.length;

            // Skip the first row, that one is already checked.
            for ( let i = 1; i < responseLength; i++ ) {
                addressLength = response[i].address_components.length;
                for ( let j = 0; j < addressLength; j++ ) {
                    responseType = response[i].address_components[j].types;

                    for ( let key in missingFields ) {
                        if ( requiredFields[key] === responseType.join( ',' ) ) {
                            statsData[key] = response[i].address_components[j].long_name;
                        }
                    }
                }
            }
        }

        // UK only: when 'locality,political' ( location_locality ) is set
        // alongside postal_town it holds the more accurate city name, so it
        // wins.
        if ( typeof statsData.location_locality !== 'undefined' && statsData.location_locality.length > 0 ) {
            statsData.location = statsData.location_locality;

            delete statsData.location_locality;
        }

        slData.statistics = wp.hooks.applyFilters( 'wpslStatsData', statsData, response );
    }
};
