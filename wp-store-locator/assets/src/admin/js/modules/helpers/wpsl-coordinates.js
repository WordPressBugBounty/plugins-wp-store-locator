import { state } from '../wpsl-shared.js';
import { api } from '../wpsl-api.js';
import { mapBootstrap } from '../wpsl-map-bootstrap.js';

/**
 * Coordinate handling and formatting helpers
 * 
 * @since 3.0.0
 */
export const coordinates = {
    /**
     * Set the coordinates in the correct input fields.
     *
     * @since   1.0.0
     * @param   {object} latLng The coordinates
     * @returns {void}
     */
    setLatlng: function( latLng ) {
        latLng = this.formatLatLng( latLng );

        if ( state.currentPage === 'editor' ) {
            jQuery( '#wpsl-lat' ).val( latLng.lat );
            jQuery( '#wpsl-lng' ).val( latLng.lng );
        } else {
            jQuery( '#wpsl-latlng' ).val( latLng.lat + ',' + latLng.lng );
        }
    },

    /**
     * Make sure the latLng object is formatted as expected.
     *
     * @since   1.0.0
     * @param   {object} latLng         The provided coordinates
     * @returns {object} formatedLatLng The latLng object
     */
    formatLatLng: function( latLng ) {
        let formatedLatLng;

        if ( typeof latLng === 'object' && typeof latLng.lat === 'function' ) {
            formatedLatLng = {
                lat: this.round( latLng.lat() ),
                lng: this.round( latLng.lng() )

            };
        } else {
            formatedLatLng = {
                lat: this.round( latLng.lat ),
                lng: this.round( latLng.lng )
            };
        }

        return formatedLatLng;
    },

    /**
     * Round the coordinate to 6 digits after the comma.
     *
     * @since   1.0.0
     * @param   {string} coordinate   The coordinate
     * @returns {number} roundedCoord The rounded coordinate
     */
    round: function( coordinate ) {
        const decimals = 6;

        return Math.round( coordinate * Math.pow( 10, decimals ) ) / Math.pow( 10, decimals );
    },

    /**
     * Create the address string that is send to the Geocode API.
     *
     * @since   3.0.0
     * @returns {string}
     */
    createGeocodeAddressString: function() {
        const addressParts = ['address', 'city', 'state', 'zip', 'country'];
        const address = [];
              
        let part;

        for ( let i = 0; i < addressParts.length; i++ ) {
            part = jQuery( '#wpsl-' + addressParts[i] ).val().trim();

            if ( part ) {
                address.push( part );
            }

            part = '';
        }

        return address.join();
    },

    /**
     * Get the lat/lng coordinates that fit the current context.
     *
     * @since  3.0.0
     * @return {object} The lat/lng object with coordinates
     */
    getLocationCoordinates: function() {
        const isAppearanceActive = jQuery( '#wpsl-nav-appearance' ).hasClass( 'active' );
        const isCustomizeActive = jQuery( '#wpsl-appearance-preview' ).hasClass( 'active' );
        
        // Default coordinates — fall back to wpslSettings if mapBootstrap hasn't been initialized yet
        const defaultLatLng = mapBootstrap.defaultLatLng || wpslSettings.defaultLatLng.split( ',' );
        let lat = defaultLatLng[0];
        let lng = defaultLatLng[1];
        
        // On the appearance tab and customize preview the data attribute
        // holds the hq of the selected map provider.
        if ( state.currentPage === 'appearance' || isAppearanceActive || isCustomizeActive ) {
            const startLatLng = jQuery( '#wpsl-stores ul' ).data( 'latlng' );

            if ( startLatLng ) {
                const latlngParts = startLatLng.split( ',' );
                if ( latlngParts.length === 2 ) {
                    lat = latlngParts[0].trim();
                    lng = latlngParts[1].trim();
                }
            }
        }
        
        return api[ state.mapService ].createLatLngObj( lat, lng );
    },
};