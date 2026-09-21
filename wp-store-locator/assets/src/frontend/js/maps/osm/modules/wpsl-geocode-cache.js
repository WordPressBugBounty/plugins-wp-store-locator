import { config } from '../../../modules/wpsl-shared.js';

/**
 * OpenStreetMap geocode cache handling
 * 
 * @since 3.0.0
 */
export const geocodeCache = {
    /**
     * See if there's a cached response from a previous
     * request to the Nominatim Geocode API.
     *
     * @since 3.0.0
     * @param {string} query The searched location
     * @param {string} type Optional structured query type ( e.g. 'postcode' )
     * @param {Function} callback Callback function
     * @return {void}
     */
    getResponse: function( query, type, callback ) {
        const response = {};

        if ( config.skipNominatimCache ) {
            callback( response );
            return;
        }

        const ajaxData = {
            action: 'nominatim_search',
            q: query
        };

        // Makes the server query Nominatim's structured postalcode= field
        // instead of q=, which resolves bare postcodes far more reliably.
        if ( type ) {
            ajaxData.type = type;
        }

        if ( typeof config.api.regions === 'string' ) {
            ajaxData.country_codes = config.api.regions;
        }

        jQuery.ajax({
            type: 'GET',
            data: ajaxData,
            dataType: 'json',
            url: config.search.ajaxurl,
            success: function( response ) {
                callback( response );
            }
        });
    }
};
