
// Set webpack publicPath for dynamic chunk loading (only needed in production builds)
if ( typeof wpslSettings !== 'undefined' && ! wpslSettings.scriptDebug ) {
    __webpack_public_path__ = wpslSettings.url + 'assets/dist/';
}

import { createApiRequest } from '../../common/wpsl-core.js';
import { api } from './modules/wpsl-api.js';
import { mapBootstrap } from './modules/wpsl-map-bootstrap.js';
import { verifyKeys } from './modules/settings/wpsl-verify-keys.js';
import { markers } from './modules/wpsl-markers.js';
import { helpers } from './modules/wpsl-helpers.js';
import { state, bindInfoPopup } from './modules/wpsl-shared.js';

const DEFAULT_ZOOM_LEVEL = 10;
const DEFAULT_LATLNG = '41.3873974, 2.168568';

/**
 * Onboarding page specific actions.
 *
 * @since 3.0.0
 */
export const onboarding = {
    selectedService: '',
    isInitialized: false,
    $startLocation: '',
    $latLng: '',
    $errorText: '',
    $startLocationSearch: '',
    init: function() {
        this.setVars();
        this.bindHandlers();

        bindInfoPopup();

        createApiRequest.monitorConsoleOutput();

        if ( jQuery( '#wpsl-onboarding-map' ).length && ! this.isInitialized ) {
            this.isInitialized = true;

            createApiRequest.maybeLoadLibraries( null, () => {
                let defaultLatLng = DEFAULT_LATLNG;

                // Can only exist when the user uses the 'Previous' button
                // to go back to the start location step.
                if ( this.$latLng.val() ) {
                    defaultLatLng = this.$latLng.val();
                }
                
                this.prepareMap();

                const latLng = defaultLatLng.split( ',' );
                const latLngObj = api[ this.selectedService ].createLatLngObj( latLng[0], latLng[1] );

                // Focus on the latlng and add a marker.
                helpers.map.setViewport({
                    elemId: 'wpsl-onboarding-map',
                    latLng: latLngObj,
                    zoom: DEFAULT_ZOOM_LEVEL
                });
            });
        }
    },

    /**
     * Set the variables used in the onboarding process.
     *
     * @since   3.0.0
     * @returns {void}
     */
    setVars: function() {
        // The map service step has radio buttons instead of the hidden field.
        const selectedMapService = jQuery( '#wpsl-selected-map-service' ).val() || jQuery( '#wpsl-onboarding-map-service input[type=radio]:checked' ).val() || wpslSettings.api.provider;

        this.selectedService = selectedMapService;
        this.$startLocation = jQuery( '#wpsl-start-location' );
        this.$latLng = jQuery( '#wpsl-latlng' );
        this.$startLocationSearch = jQuery( '.start-location-search' );

        state.mapService = selectedMapService;
        state.currentPage = helpers.dom.getCurrentPage();
    },

    /**
     * Get the geocoding service to use (fallback to OSM for Mapbox).
     *
     * @since   3.0.0
     * @returns {string}
     */
    getGeocodingService: function() {
        return this.selectedService === 'mapbox' ? 'osm' : this.selectedService;
    },

    /**
     * Check if an error message is currently displayed.
     *
     * @since   3.0.0
     * @returns {boolean}
     */
    hasErrorMessage: function() {
        return jQuery( '.wpsl-error-text' ).length > 0;
    },

    /**
     * Get formatted address from geocoding response.
     *
     * @since   3.0.0
     * @param   {object} response The geocoding response
     * @param   {string} service The geocoding service used
     * @returns {string}
     */
    getFormattedAddressFromResponse: function( response, service ) {
        if ( service === 'osm' || service === 'stadia' ) {
            return createApiRequest[ service ].helpers.getFormattedAddress( response );
        }

        return createApiRequest.helpers.getFormattedAddress( response );
    },

    /**
     * Get latLng from geocoding response.
     *
     * @since   3.0.0
     * @param   {object} response The geocoding response
     * @param   {string} service The geocoding service used
     * @returns {object}
     */
    getLatLngFromResponse: function( response, service ) {
        if ( service === 'osm' || service === 'stadia' ) {
            return createApiRequest[ service ].helpers.getResponseLatLng( response );
        }

        return createApiRequest[ service ].helpers.getResponseLatLng( response );
    },

    /**
     * Initialize the map.
     *
     * @since   3.0.0
     * @returns {void}
     */
    prepareMap: function() {
        mapBootstrap.init( 'wpsl-onboarding-map' );
    },

    /**
     * Bind event handlers for the onboarding process.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers: function() {
        if ( jQuery( '#wpsl-onboarding-api-keys' ).length ) {
            verifyKeys.init();
        }

        jQuery( '#wpsl-onboarding-location-preview' ).on( 'click', ( event ) => {
            const startLocation = this.$startLocation.val();
            const geocodingService = this.getGeocodingService();

            let args;

            if ( startLocation ) {
                switch ( geocodingService ) {
                    case 'osm':
                    case 'stadia':
                    case 'mapbox':
                        args = {
                            q: startLocation,
                            format: 'jsonv2',
                            addressdetails: 1,
                            limit: 1
                        };
                        break;
                    case 'gmaps':
                        args = {
                            address: startLocation
                        };
                        break;
                }

                jQuery( '.wpsl-error-text' ).remove();
                this.$startLocation.removeClass( 'wpsl-error' );

                createApiRequest[ geocodingService ].geocode( args, ( response, status ) => {
                    const responseStatus = createApiRequest.helpers.getResponseStatus( response, status );

                    if ( createApiRequest.helpers.isValidResponse( responseStatus ) ) {
                        const latLng = this.getLatLngFromResponse( response, geocodingService );
                        if ( latLng ) {
                            markers.getActive().removeAll();

                            helpers.map.setViewport({
                                elemId: 'wpsl-onboarding-map',
                                latLng: latLng,
                                zoom: DEFAULT_ZOOM_LEVEL,
                                addMarker: true
                            });

                            this.$latLng.val( latLng.lat + ',' + latLng.lng );
                        } else {
                            this.showNoResultsError();
                        }
                    } else {
                        this.showNoResultsError();
                    }
                });
            } else {
                this.$startLocation.addClass( 'wpsl-error' );

                if ( ! this.hasErrorMessage() ) {
                    this.$startLocationSearch.before( "<p class='wpsl-error-text'>" + wpslOnboardingL10n.noStartLocation + "</p>" );
                }
            }

            return false;
        });
    
        jQuery( '#wpsl-onboarding-map-service' ).on( 'change', 'input[type=radio]', ( event ) => {
            this.selectedService = event.currentTarget.value;
            this.updateContinueLabel();
        });

        jQuery( '.wpsl-copy-shortcode' ).on( 'click', ( event ) => {
            this.copyShortcode( jQuery( event.currentTarget ) );
        });
    },

    /**
     * Name the picked map service on the button that closes the step.
     *
     * @since   3.1.1
     * @returns {void}
     */
    updateContinueLabel: function() {
        const serviceName = wpslOnboardingL10n.serviceNames[ this.selectedService ];

        if ( serviceName ) {
            jQuery( '#wpsl-onboarding-continue' ).val( wpslOnboardingL10n.continueWith.replace( '%s', serviceName ) );
        }
    },

    /**
     * Copy the shortcode on the closing step, and confirm it on the button.
     *
     * @since   3.1.1
     * @param   {object} $button The clicked copy button.
     * @returns {void}
     */
    copyShortcode: function( $button ) {
        const $label = $button.find( 'span' );
        const text   = $button.data( 'copy' );

        const confirmCopy = () => {
            if ( ! $button.data( 'label' ) ) {
                $button.data( 'label', $label.text() );
            }

            $label.text( wpslOnboardingL10n.copied );

            clearTimeout( $button.data( 'timer' ) );
            $button.data( 'timer', setTimeout( () => $label.text( $button.data( 'label' ) ), 2000 ) );
        };

        // The Clipboard API only exists on HTTPS, so an HTTP admin copies the old way.
        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( text ).then( confirmCopy );
        } else {
            const $field = jQuery( '<textarea readonly>' ).val( text ).css( { position: 'fixed', top: 0, opacity: 0 } ).appendTo( 'body' );

            $field.trigger( 'select' );

            if ( document.execCommand( 'copy' ) ) {
                confirmCopy();
            }

            $field.remove();
            $button.trigger( 'focus' );
        }
    },

    /**
     * Reverse geocode a dragged marker into the start location field.
     *
     * @since   3.0.0
     * @param   {object} latLng The coordinates from the dragged marker.
     * @returns {void}
     */
    updateStartLocationField: function( latLng ) {
        const geocodingService = this.getGeocodingService();
        let args;
        
        switch ( geocodingService ) {
            case 'osm':
            case 'stadia':
            case 'mapbox':
                const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
                const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;
                
                args = {
                    format: 'json',
                    lat: lat,
                    lon: lng,
                    addressdetails: '1'
                };
                break;
            case 'gmaps':
                args = {
                    location: latLng
                };
                break;
        }

        jQuery( '.wpsl-error-text' ).remove();
        this.$startLocation.removeClass( 'wpsl-error' );

        createApiRequest[ geocodingService ].geocode( args, ( response, status ) => {
            const responseStatus = createApiRequest.helpers.getResponseStatus( response, status );

            if ( createApiRequest.helpers.isValidResponse( responseStatus ) ) {
                const formattedAddress = this.getFormattedAddressFromResponse( response, geocodingService );

                if ( formattedAddress ) {
                    this.$startLocation.val( formattedAddress );
                    
                    jQuery( '.wpsl-error-text' ).remove();
                } else {
                    this.showNoResultsError();
                }
            } else {
                this.showNoResultsError();
            }
        });
    },

    /**
     * Tell the user the geocode API found nothing for the start location.
     *
     * @since   3.0.0
     * @returns {void}
     */
    showNoResultsError: function() {
        this.resetStartLocationField();

        this.$startLocation.val( '' ).attr( 'placeholder', wpslOnboardingL10n.startLocation );

        if ( ! this.hasErrorMessage() ) {
            this.$startLocationSearch.before( "<p class='wpsl-error-text'>" + wpslOnboardingL10n.noResults + "</p>" );
        }
    },
    
    /**
     * Empty the input field and hidden latlng field.
     * 
     * @since   3.0.0
     * @returns {void}
     */
    resetStartLocationField: function() {
        this.$startLocation.val( '' );
        this.$latLng.val( '' );
    },
};

jQuery( document ).ready( () => onboarding.init() );

window.wpslOnboarding = onboarding;