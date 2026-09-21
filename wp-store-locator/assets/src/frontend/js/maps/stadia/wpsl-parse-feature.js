/**
 * Normalize a Stadia Maps Geocoding v2 GeoJSON feature into a flat object.
 *
 * v2 nests address data under `address_components` and geographic context under
 * `context.whosonfirst`, unlike the flat v1 (Pelias) shape. This helper is the
 * single source of truth for those field paths. It never throws: a missing
 * branch yields an empty string, absent coordinates yield undefined.
 *
 * @since  3.0.0
 * @param  {object} feature A single GeoJSON feature from a v2 response.
 * @return {object} Flat normalized fields.
 */
export const parseStadiaFeature = ( feature ) => {
    const props   = ( feature && feature.properties ) || {};
    const address = props.address_components || {};
    const context = props.context || {};
    const wof     = context.whosonfirst || {};
    const coords  = ( feature && feature.geometry && feature.geometry.coordinates ) || [];

    const wofName = ( key ) => ( wof[ key ] && wof[ key ].name ) || '';

    return {
        label:       props.formatted_address_line || props.name || '',
        name:        props.name || '',
        // Autocomplete features carry no address_components or context, only a
        // pre-formatted "city, region, country" line to disambiguate same-named
        // places. Empty on search / place details responses.
        coarseLocation: props.coarse_location || '',
        street:      address.street || '',
        houseNumber: address.number || '',
        postalCode:  address.postal_code || '',
        city:        wofName( 'locality' ) || wofName( 'localadmin' ) || wofName( 'county' ),
        region:      wofName( 'region' ),
        county:      wofName( 'county' ),
        country:     wofName( 'country' ),
        countryCode: context.iso_3166_a2 || '',
        layer:       props.layer || '',
        // 'match' and 'interpolated' are both real locations; 'fallback' means
        // Stadia returned something loosely similar to the query instead.
        matchType:   props.match_type || '',
        confidence:  typeof props.confidence === 'number' ? props.confidence : null,
        lat:         coords[1],
        lng:         coords[0]
    };
};

/**
 * Join a parsed autocomplete feature into a single unambiguous line.
 *
 * "Rotterdam" alone matches places on three continents, so the coarse location
 * is appended for the search field value. Features whose label already spells
 * out the context ( search / place details responses ) are returned unchanged.
 *
 * @since  3.0.0
 * @param  {object} parsed The result of parseStadiaFeature().
 * @return {string} The label with its coarse location appended, when useful.
 */
export const stadiaFeatureLine = ( parsed ) => {
    if ( ! parsed || ! parsed.coarseLocation ) {
        return ( parsed && parsed.label ) || '';
    }

    if ( ! parsed.label ) {
        return parsed.coarseLocation;
    }

    return parsed.label.indexOf( parsed.coarseLocation ) !== -1 ? parsed.label : parsed.label + ', ' + parsed.coarseLocation;
};
