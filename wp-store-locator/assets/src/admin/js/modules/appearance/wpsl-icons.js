import { state } from '../wpsl-shared.js';

/**
 * Icon management for the appearance section.
 *
 * @since 3.0.0
 */
export const iconManager = {
    parent: null,

    /**
     * Initialize the icon manager.
     * 
     * @since 3.0.0
     * @param {object} parentAppearance - Reference to main appearance object
     */
    init( parentAppearance ) {
        this.parent = parentAppearance;
    },

    /**
     * Add icon classes to location items when icons are enabled.
     * 
     * @since 3.0.0
     */
    add() {
        const $containers = jQuery( '#wpsl-stores li, .wpsl-map-style-wrap .wpsl-info-window' );
        const addressIcon = jQuery( 'input[name="wpsl_appearance[icons][address]"]:checked' ).val() || 'marker';
        const phoneIcon = jQuery( 'input[name="wpsl_appearance[icons][phone]"]:checked' ).val() || 'phone';
        const emailIcon = jQuery( 'input[name="wpsl_appearance[icons][email]"]:checked' ).val() || 'email';

        $containers.each( function() {
            const $storeLocation = jQuery( this ).find( '.wpsl-store-location' );
            const $addressPara = $storeLocation.find( '> p:first-child' );

            if ( $addressPara.length ) {
                $addressPara.addClass( 'wpsl-icon-address wpsl-icon-address-' + addressIcon );
            }
            
            const $contactDetails = $storeLocation.find( '.wpsl-contact-details' );
            if ( $contactDetails.length ) {
                $contactDetails.find( '> span:first-child' ).addClass( 'wpsl-icon-' + phoneIcon );
                $contactDetails.find( '> span:last-child' ).addClass( 'wpsl-icon-' + emailIcon );
            }
            
            const $directionWrap = jQuery( this ).find( '.wpsl-direction-wrap' );
            if ( $directionWrap.length ) {
                $directionWrap.find( '.wpsl-distance' ).addClass( 'wpsl-icon-road' );
            }
        });
    },

    /**
     * Remove icon classes from location items when icons are disabled.
     * 
     * @since 3.0.0
     */
    remove() {
        const $containers = jQuery( '#wpsl-stores li, .wpsl-map-style-wrap .wpsl-info-window' );

        $containers.each( function() {
            const $storeLocation = jQuery( this ).find( '.wpsl-store-location' );
            const $addressPara = $storeLocation.find( '> p:first-child' );

            if ( $addressPara.length ) {
                $addressPara.removeClass( function( index, className ) {
                    return ( className.match( /\bwpsl-icon-address[\w-]*\b/g ) || [] ).join( ' ' );
                });
            }
            
            const $contactDetails = $storeLocation.find( '.wpsl-contact-details' );
            if ( $contactDetails.length ) {
                $contactDetails.find( '> span' ).removeClass( function( index, className ) {
                    return ( className.match( /\bwpsl-icon-[\w-]+\b/g ) || [] ).join( ' ' );
                });
            }
            
            const $directionWrap = jQuery( this ).find( '.wpsl-direction-wrap' );
            if ( $directionWrap.length ) {
                $directionWrap.find( '.wpsl-distance' ).removeClass( 'wpsl-icon-road' );
            }
        });
    },

    /**
     * Update the stored popup content string (wpslL10n.popupContent)
     * to reflect the current icon toggle state.
     * 
     * @since 3.0.0
     * @param {boolean} iconsEnabled - Whether icons are currently enabled
     */
    updatePopupContent( iconsEnabled ) {
        if ( typeof wpslL10n === 'undefined' || ! wpslL10n.popupContent ) {
            return;
        }

        const $popup = jQuery( '<div>' ).html( wpslL10n.popupContent );
        const $storeLocation = $popup.find( '.wpsl-store-location' );
        const $addressPara = $storeLocation.find( '> p:first-child' );
        const $contactDetails = $storeLocation.find( '.wpsl-contact-details' );

        if ( iconsEnabled ) {
            const addressIcon = jQuery( 'input[name="wpsl_appearance[icons][address]"]:checked' ).val() || 'marker';
            const phoneIcon = jQuery( 'input[name="wpsl_appearance[icons][phone]"]:checked' ).val() || 'phone';
            const emailIcon = jQuery( 'input[name="wpsl_appearance[icons][email]"]:checked' ).val() || 'email';

            if ( $addressPara.length ) {
                $addressPara.removeClass( function( index, className ) {
                    return ( className.match( /\bwpsl-icon-address[\w-]*\b/g ) || [] ).join( ' ' );
                });

                $addressPara.addClass( 'wpsl-icon-address wpsl-icon-address-' + addressIcon );
            }

            if ( $contactDetails.length ) {
                $contactDetails.find( '> span' ).removeClass( function( index, className ) {
                    return ( className.match( /\bwpsl-icon-[\w-]+\b/g ) || [] ).join( ' ' );
                });
                
                $contactDetails.find( '> span:first-child' ).addClass( 'wpsl-icon-' + phoneIcon );
                $contactDetails.find( '> span:last-child' ).addClass( 'wpsl-icon-' + emailIcon );
            }
        } else {
            if ( $addressPara.length ) {
                $addressPara.removeClass( function( index, className ) {
                    return ( className.match( /\bwpsl-icon-address[\w-]*\b/g ) || [] ).join( ' ' );
                });
            }

            if ( $contactDetails.length ) {
                $contactDetails.find( '> span' ).removeClass( function( index, className ) {
                    return ( className.match( /\bwpsl-icon-[\w-]+\b/g ) || [] ).join( ' ' );
                });
            }
        }

        wpslL10n.popupContent = $popup.html();
    },

    /**
     * Refresh popup content on existing map markers
     * so that reopening the popup shows the updated icon state.
     * 
     * @since 3.0.0
     */
    refreshMarkerPopups() {
        if ( typeof wpslL10n === 'undefined' || ! wpslL10n.popupContent ) {
            return;
        }

        const popupContent = wpslL10n.popupContent;

        // Google Maps: update any currently-open InfoWindow in the DOM
        jQuery( '.gm-style-iw-d .wpsl-info-window' ).closest( '.gm-style-iw-d' ).html( popupContent );

        // Leaflet/OSM and Mapbox: update bound popup content on existing markers
        if ( state.activeMarkers ) {
            state.activeMarkers.forEach( function( marker ) {
                if ( marker && typeof marker.getPopup === 'function' ) {
                    const popup = marker.getPopup();

                    if ( popup ) {
                        // Mapbox uses setHTML, Leaflet uses setContent
                        if ( typeof popup.setHTML === 'function' ) {
                            popup.setHTML( popupContent );
                        } else if ( typeof popup.setContent === 'function' ) {
                            popup.setContent( popupContent );
                        }
                    }
                }
            });
        }
    }
};