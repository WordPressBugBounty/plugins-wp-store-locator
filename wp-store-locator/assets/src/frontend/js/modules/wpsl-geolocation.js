import { config, slData } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';
import { search } from './wpsl-search.js';
import { filters } from './wpsl-filters.js';

/**
 * Geolocation functionality for WPSL frontend
 * 
 * @since 3.0.0
 */

export const geolocation = {
    timeout: '',
    locationTimeout: '',
    /**
     * Initialize geolocation based on trigger setting.
     * Either runs immediately or shows a dialog for user confirmation.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        if ( config.search.autoLocate.trigger === 'pageload' ) {
            this.run();
        } else if ( config.search.autoLocate.trigger === 'user_request' ) {
            this.showDialog();
        }
    },


    /**
     * Fall back to the configured start location when the visitor declines
     * the location prompt ( or has no geolocation ) and no results are showing.
     *
     * The search box and the autocomplete coordinates are cleared with it:
     * both belong to the search being abandoned, and the next search would
     * otherwise reuse them.
     *
     * @since   3.0.0
     * @returns {void}
     */
    searchStartLocation: function() {
        jQuery( '#wpsl-search-input' ).val( '' );

        slData.autoCompleteLatLng = '';

        search.prepare( { latLng: config.map.startLatLng } );
    },

    /**
     * Show a dialog asking the user if they want to share their location.
     *
     * @since   3.0.0
     * @returns {void}
     */
    showDialog: function() {
        let acceptClass = 'wpsl-geolocation-accept wpsl-icon-geolocation',
            declineClass = 'wpsl-geolocation-decline';
        
        if ( config.ux.ctaButtons && config.ux.buttonStyles ) {
            const buttonStyles = config.ux.buttonStyles;
            const acceptStyle = buttonStyles.share_location || 'primary';
            const declineStyle = buttonStyles.no_thanks || 'secondary';
            
            acceptClass += ' wpsl-styled-btn wpsl-' + acceptStyle + '-btn';
            declineClass += ' wpsl-styled-btn wpsl-' + declineStyle + '-btn';
        } else {
            acceptClass += ' wpsl-styled-btn wpsl-primary-btn';
            declineClass += ' wpsl-styled-btn wpsl-secondary-btn';
        }
        
        const $dialog = jQuery( '<div class="wpsl-geolocation-dialog" role="dialog" aria-modal="true" aria-labelledby="wpsl-geolocation-title">' +
            '<div class="wpsl-geolocation-dialog-content">' +
                '<p id="wpsl-geolocation-title">' + wpslLabels.geoLocationDialog + '</p>' +
                '<div class="wpsl-geolocation-dialog-actions">' +
                    '<button class="' + acceptClass + '" type="button">' + wpslLabels.geoLocationAccept + '</button>' +
                    '<button class="' + declineClass + '" type="button">' + wpslLabels.geoLocationDecline + '</button>' +
                '</div>' +
            '</div>' +
        '</div>' );

        jQuery( '#wpsl-map' ).append( $dialog );

        // Focus the first button for keyboard accessibility
        const $acceptBtn = $dialog.find( '.wpsl-geolocation-accept' );
        const $declineBtn = $dialog.find( '.wpsl-geolocation-decline' );
        
        setTimeout( function() {
            $acceptBtn.trigger( 'focus' );
        }, 100 );

        $acceptBtn.on( 'click', () => {
            $dialog.remove();
            this.run();
        });

        $declineBtn.on( 'click', () => {
            $dialog.remove();
            
            // Load default map if no results are shown
            if ( ! jQuery( '#wpsl-stores li' ).length || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-no-results' ) ) {
                geolocation.searchStartLocation();
            }
        });

        // Handle Escape key to close dialog
        jQuery( document ).on( 'keydown.wpsl-geolocation', function( e ) {
            if ( e.key === 'Escape' || e.keyCode === 27 ) {
                $declineBtn.trigger( 'click' );
                jQuery( document ).off( 'keydown.wpsl-geolocation' );
            }
        });
    },

    /**
     * Check if the Geolocation API is supported by the used browser.
     * If so, make the location request. Otherwise show an error message.
     *
     * @since   3.0.0
     * @returns {void}
     */
    run: function() {
        let args = {},
            inProgress = '';

        this.timeout = config.search.geoLocationTimeout;

        if ( navigator.geolocation ) {

            // Show a small overlay so the user gets feedback while we locate them.
            this.showLocatingOverlay();

            // Make the direction icon flash every 600ms to
            // indicate the geolocation attempt is in progress.
            inProgress = setInterval( function() {
                jQuery( '.wpsl-icon-direction' ).toggleClass( 'wpsl-active-icon' );
            }, 600 );

            // Load the default map if the user doesn't approve in time. The
            // wpsl_geolocation_timeout filter changes the timeout value.
            this.locationTimeout = setTimeout( function() {
                // Only run fallback if geolocation hasn't succeeded yet
                if ( ! jQuery( '.wpsl-search' ).hasClass( 'wpsl-geolocation-run' ) ) {

                    // Mark as handled to prevent error callback from also running
                    jQuery( '.wpsl-search' ).addClass( 'wpsl-geolocation-run' );

                    geolocation.attemptFinished( inProgress );

                    // Fall back to the start location only when no results
                    // are showing, like the decline and no-API branches.
                    if ( ! jQuery( '#wpsl-stores li' ).length || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-no-results' ) ) {
                        geolocation.searchStartLocation();
                    }
                }
            }, this.timeout );

            this.locateUser( inProgress );
        } else {
            alert( wpslGeolocationErrors.unavailable );

            // Only run a search for the default start point
            // if no results are shown on the map
            if ( ! jQuery( '#wpsl-stores li' ).length || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-no-results' ) ) {
                geolocation.searchStartLocation();
            }
        }
    },

    /**
     * Try to obtain the users current position
     * by making a call to the Geolocation API.
     *
     * @since   3.0.0
     * @param   {number} inProgress The setInterval timer
     * @returns {void}
     */
    locateUser: function( inProgress ) {
        const args = {};
        const self = this;

        navigator.geolocation.getCurrentPosition( function( position ) {
            self.attemptFinished( inProgress );
            clearTimeout( self.locationTimeout );

            args.position = position;

            if ( jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-search-types-support' ) ) {
                filters.dropdowns.setSelectedOption( 'wpsl-search-type-dropdown', 'location' );

                //@todo check two selection opens with name + location template
                config.search.namesEnabled = 0;
            }

            // Clear the map first: after a timeout, enabling the geolocation
            // detection again would otherwise leave multiple start markers.
            slData.provider.markers.removeAll();
            self.handleQuery( args );

            // Workaround for https://bugzilla.mozilla.org/show_bug.cgi?id=1283563:
            // in Firefox the error callback also fires after a successful lookup,
            // which would run showStores() with the wrong start location.
            jQuery( '.wpsl-search' ).addClass( 'wpsl-geolocation-run' );

            wp.hooks.doAction( 'wpslUserGeolocation', position );
        }, function( error ) {
            self.hideLocatingOverlay();

            // Only show the geocode errors if the user actually clicked on the
            // direction icon. With the "Attempt to auto-locate the user" option
            // enabled a failed attempt ( blocked in the browser, unavailable )
            // would otherwise greet the visitor with an alert box on pageload.
            // Without that click the default map is shown, no alert.
            if ( jQuery( '.wpsl-icon-direction' ).hasClass( 'wpsl-user-activated' ) && ! jQuery( '.wpsl-search' ).hasClass( 'wpsl-geolocation-run' ) ) {
                switch ( error.code ) {
                    case error.PERMISSION_DENIED:
                        alert( wpslGeolocationErrors.denied );
                        break;
                    case error.POSITION_UNAVAILABLE:
                        alert( wpslGeolocationErrors.unavailable );
                        break;
                    case error.TIMEOUT:
                        alert( wpslGeolocationErrors.timeout );
                        break;
                    default:
                        alert( wpslGeolocationErrors.generalError );
                        break;
                }

                jQuery( '.wpsl-icon-direction' ).removeClass( 'wpsl-active-icon' );
            } else if ( ! jQuery( '.wpsl-search' ).hasClass( 'wpsl-geolocation-run' ) ) {
                clearTimeout( geolocation.locationTimeout );

                geolocation.searchStartLocation();
            }
        }, { maximumAge: 60000, timeout: geolocation.timeout, enableHighAccuracy: true });
    },

    /**
     * Clean up after the geolocation attempt finished.
     *
     * @since   2.0.0
     * @param   {number} inProgress The setInterval timer
     * @returns {void}
     */
    attemptFinished: function( inProgress ) {
        clearInterval( inProgress );
        jQuery( '.wpsl-icon-direction' ).removeClass( 'wpsl-active-icon' );
        this.hideLocatingOverlay();
    },

    /**
     * Show a small centered overlay with a preloader on top of the map
     * while the geolocation API is determining the user's position.
     *
     * Works the same for all map providers since it's appended to #wpsl-map.
     *
     * @since   3.0.0
     * @returns {void}
     */
    showLocatingOverlay: function() {
        const $map = jQuery( '#wpsl-map' );
        if ( ! $map.length || $map.find( '.wpsl-geolocation-overlay' ).length ) {
            return;
        }

        const text = wpslLabels.geoLocationLocating;

        $map.append(
            '<div class="wpsl-geolocation-overlay" role="status" aria-live="polite">' +
                '<div class="wpsl-geolocation-overlay-box">' +
                    '<img alt="' + wpslLabels.preloadLabel + '" width="18" height="18" src="' + config.search.geoLocationPreloader + '" />' +
                    '<span>' + text + '</span>' +
                '</div>' +
            '</div>'
        );
    },

    /**
     * Remove the geolocation loading overlay.
     *
     * @since   3.0.0
     * @returns {void}
     */
    hideLocatingOverlay: function() {
        jQuery( '#wpsl-map .wpsl-geolocation-overlay' ).remove();
    },

    /**
     * Handle the data returned from the Geolocation API.
     *
     * On an error / timeout args carries the 'start point' from the settings
     * instead of the user's position.
     *
     * @since	1.0.0
     * @param   {object} args The users coordinates and the start coordinates from the settings page
     * @returns {void}
     */
    handleQuery: function( args ) {
        const position = args.position;

        if ( typeof position !== 'undefined' ) {

            // All map providers expect the coordinates in this latLng structure.
            args = {
                autoLoad: config.search.autoLoad,
                latLng: {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                }
            };

            // For Google Maps, convert to LatLng instance if the helper function exists.
            // Other providers (OSM, Mapbox) work with plain objects.
            if ( typeof helpers.map.checkLatLngInstance === 'function' ) {
                args.latLng = helpers.map.checkLatLngInstance( args.latLng );
            }

            slData.geolocation = {
                active: true,
                position: args,
                newRequest: true
            };
        }

        search.prepare( args );
    }
};