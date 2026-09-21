/**
 * Convert a Nominatim reverse geocode response into the Stadia Maps
 * Geocoding v2 response shape.
 *
 * Stadia sells reverse geocoding separately from forward search, so on
 * plans without it the reverse endpoint 403s and the free Nominatim
 * service answers instead. The conversion keeps every Stadia response
 * consumer ( parseStadiaFeature, filterResponse, collectStatsData )
 * working unchanged.
 *
 * Standalone ( no jQuery / shared-state imports ) so the node test suite
 * can exercise it directly.
 *
 * @since  3.0.0
 * @param  {object} response The Nominatim reverse geocode response.
 * @return {object|null} A Stadia v2 shaped FeatureCollection, or null when
 *                       the response holds no usable address.
 */
export const nominatimToStadiaResponse = ( response ) => {
    if ( ! response || typeof response.address !== 'object' || ! response.lat || ! response.lon ) {
        return null;
    }

    const address  = response.address;
    const locality = address.city || address.town || address.village || '';

    // Only the entries Nominatim answered with. parseStadiaFeature treats a
    // missing entry and an empty name the same, but real Stadia responses omit
    // unknown entries.
    const whosonfirst = {};

    if ( locality ) {
        whosonfirst.locality = { name: locality };
    }

    if ( address.county ) {
        whosonfirst.county = { name: address.county };
    }

    if ( address.state ) {
        whosonfirst.region = { name: address.state };
    }

    if ( address.country ) {
        whosonfirst.country = { name: address.country };
    }

    return {
        features: [ {
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [ parseFloat( response.lon ), parseFloat( response.lat ) ]
            },
            properties: {
                name: response.display_name || '',
                formatted_address_line: response.display_name || '',
                layer: 'address',
                match_type: 'exact',
                address_components: {
                    number: address.house_number || '',
                    street: address.road || '',
                    postal_code: address.postcode || ''
                },
                context: {
                    iso_3166_a2: ( address.country_code || '' ).toUpperCase(),
                    whosonfirst: whosonfirst
                }
            }
        } ]
    };
};
