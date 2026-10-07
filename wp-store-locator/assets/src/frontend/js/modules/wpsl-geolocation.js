import { config, slData } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';
import { search } from './wpsl-search.js';
import { filters } from './wpsl-filters.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';

/**
 * Geolocation functionality for WPSL frontend
 * 
 * @since 3.0.0
 */

export const geolocation = {
    timeout: '',

    // The running or last location attempt, see run().
    attempt: null,
    /**
     * Initialize geolocation based on trigger setting.
     * Either runs immediately, shows a dialog for user confirmation, or
     * uses the approximate location that needs no permission at all.
     *
     * @since   3.0.0
     * @param   {Function} [showDefault] Shows the locator as it is without auto-locate
     * @returns {void}
     */
    init: function( showDefault ) {
        if ( config.search.autoLocate.trigger === 'pageload' ) {
            this.run();
        } else if ( config.search.autoLocate.trigger === 'user_request' ) {
            this.showDialog();
        } else if ( config.search.autoLocate.trigger === 'approximate' && config.search.autoLocate.approximate ) {
            this.runApproximate( showDefault );
        }
    },

    /**
     * Start the locator near the visitor without the browser asking for
     * permission, with the approximate location the CDN or host of the site
     * reports for the request.
     *
     * @since   3.1.0
     * @param   {Function} [showDefault] Shows the locator as it is without auto-locate
     * @returns {void}
     */
    runApproximate: function( showDefault ) {
        const requestId = search.requestId;

        const useDefault = function() {

            // A visitor who searched in the meantime already has what they asked for.
            if ( requestId === search.requestId && typeof showDefault === 'function' ) {
                showDefault();
            }
        };

        jQuery.ajax({
            type: 'GET',
            data: { action: 'wpsl_visitor_location' },
            dataType: 'json',
            url: config.search.ajaxurl,

            // The map is waiting on this, and most sites don't know the location at all.
            timeout: 2000
        }).done( function( visitorLocation ) {

            // Known more often than the coordinates, and enough to prefer nearby matches when geocoding.
            if ( visitorLocation && visitorLocation.country ) {
                slData.visitorCountry = visitorLocation.country;
            }

            if ( visitorLocation && typeof visitorLocation.lat === 'number' && typeof visitorLocation.lng === 'number' ) {
                if ( requestId === search.requestId ) {
                    geolocation.searchApproximateLocation( visitorLocation );
                }
            } else {
                useDefault();
            }
        }).fail( useDefault );
    },

    /**
     * Search from the approximate location of the visitor.
     *
     * No reverse geocode is made for it. What that would add to the search
     * ( the country for the border restriction, the text for the search
     * field ) the location already comes with.
     *
     * @since   3.1.0
     * @param   {object} visitorLocation The country, region, city, postalCode, lat and lng
     * @returns {void}
     */
    searchApproximateLocation: function( visitorLocation ) {
        const inputValue = ( config.search.autoLocate.format === 'zip' && visitorLocation.postalCode ) || visitorLocation.city;

        const args = {
            autoLoad: config.search.autoLoad,
            approximate: visitorLocation,
            latLng: {
                lat: visitorLocation.lat,
                lng: visitorLocation.lng
            }
        };

        if ( typeof helpers.map.checkLatLngInstance === 'function' ) {
            args.latLng = helpers.map.checkLatLngInstance( args.latLng );
        }

        if ( config.search.restrictions.borders && visitorLocation.country ) {
            args.countryCode = visitorLocation.country;
        }

        if ( config.search.directionRedirect ) {
            slData.directionOrigin = visitorLocation.lat + ',' + visitorLocation.lng;
        }

        // As in searchStartLocation(): what is left of an abandoned search goes.
        helpers.input.getSearchField().val( inputValue || '' );

        slData.autoCompleteLatLng = '';

        // Handled as a geolocation search: no cached results, no search statistics.
        slData.geolocation = {
            active: true,
            position: args,
            newRequest: false
        };

        search.prepare( args );

        wp.hooks.doAction( 'wpslApproximateLocation', visitorLocation );
    },

    /**
     * Where to search from when the exact position of the visitor can't be
     * used ( declined, timed out, no geolocation ): their approximate location
     * if the site knows it, otherwise the configured start location.
     *
     * @since   3.1.0
     * @returns {void}
     */
    searchFallbackLocation: function() {
        // Only while the approximate location is switched on ( Visitor_Location::ENABLED ).
        if ( ! config.search.autoLocate.approximate ) {
            geolocation.searchStartLocation();

            return;
        }

        geolocation.runApproximate( geolocation.searchStartLocation );
    },

    /**
     * Fall back to the configured start location when the visitor declines
     * the location prompt ( or has no geolocation ), their approximate
     * location is unknown as well, and no results are showing.
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
                '<p id="wpsl-geolocation-title">' + sharedHelpers.escapeHtml( wpslLabels.geoLocationDialog ) + '</p>' +
                '<div class="wpsl-geolocation-dialog-actions">' +
                    '<button class="' + acceptClass + '" type="button">' + sharedHelpers.escapeHtml( wpslLabels.geoLocationAccept ) + '</button>' +
                    '<button class="' + declineClass + '" type="button">' + sharedHelpers.escapeHtml( wpslLabels.geoLocationDecline ) + '</button>' +
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
                geolocation.searchFallbackLocation();
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
    run: function( userActivated ) {
        this.timeout = config.search.geoLocationTimeout;

        if ( navigator.geolocation ) {

            /*
             * Every attempt keeps its own state. A click on the direction icon
             * after the pageload attempt timed out used to count as already
             * handled: a declined prompt showed no message, the icon kept
             * flashing and the search button stayed disabled.
             */
            this.endAttempt();

            const attempt = this.attempt = {
                userActivated: !! userActivated,
                finished: false,
                located: false,

                // Make the direction icon flash every 600ms to
                // indicate the geolocation attempt is in progress.
                inProgress: setInterval( function() {
                    jQuery( '.wpsl-icon-direction' ).toggleClass( 'wpsl-active-icon' );
                }, 600 ),
                timer: ''
            };

            // Show a small overlay so the user gets feedback while we locate them.
            this.showLocatingOverlay();

            // Load the default map if the user doesn't approve in time. The
            // wpsl_geolocation_timeout filter changes the timeout value. A
            // location that arrives later is still shown.
            attempt.timer = setTimeout( function() {
                if ( attempt.finished ) {
                    return;
                }

                geolocation.attemptFinished( attempt );

                if ( attempt.userActivated ) {
                    jQuery( '#wpsl-search-btn' ).attr( 'disabled', false );
                }

                // Fall back to the start location only when no results
                // are showing, like the decline and no-API branches.
                if ( ! jQuery( '#wpsl-stores li' ).length || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-no-results' ) ) {
                    geolocation.searchFallbackLocation();
                }
            }, this.timeout );

            this.locateUser( attempt );
        } else {
            alert( wpslGeolocationErrors.unavailable );

            // Only run a search for the default start point
            // if no results are shown on the map
            if ( ! jQuery( '#wpsl-stores li' ).length || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-no-results' ) ) {
                geolocation.searchFallbackLocation();
            }
        }
    },

    /**
     * Try to obtain the users current position
     * by making a call to the Geolocation API.
     *
     * @since   3.0.0
     * @param   {object} attempt The attempt this request belongs to, see run()
     * @returns {void}
     */
    locateUser: function( attempt ) {
        const args = {};
        const self = this;

        navigator.geolocation.getCurrentPosition( function( position ) {

            // A newer attempt took over. The browser answers its request too,
            // so the position is handled there and not searched twice.
            if ( attempt !== self.attempt ) {
                return;
            }

            // Workaround for https://bugzilla.mozilla.org/show_bug.cgi?id=1283563:
            // in Firefox the error callback also fires after a successful lookup,
            // which would run showStores() with the wrong start location.
            attempt.located = true;

            self.attemptFinished( attempt );

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

            wp.hooks.doAction( 'wpslUserGeolocation', position );
        }, function( error ) {
            if ( attempt !== self.attempt || attempt.located ) {
                return;
            }

            const timedOut = attempt.finished;

            self.attemptFinished( attempt );

            // Only show the geocode errors if the user actually clicked on the
            // direction icon. With the "Attempt to auto-locate the user" option
            // enabled a failed attempt ( blocked in the browser, unavailable )
            // would otherwise greet the visitor with an alert box on pageload.
            // Without that click the default map is shown, no alert.
            if ( attempt.userActivated ) {
                jQuery( '#wpsl-search-btn' ).attr( 'disabled', false );

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

                // A click before the pageload attempt finished ended that
                // attempt, and with it the fallback it would have run.
                if ( ! timedOut && ( ! jQuery( '#wpsl-stores li' ).length || jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-no-results' ) ) ) {
                    geolocation.searchFallbackLocation();
                }
            } else if ( ! timedOut ) {

                // After a timeout the fallback already ran.
                geolocation.searchFallbackLocation();
            }
        }, { maximumAge: 60000, timeout: geolocation.timeout, enableHighAccuracy: true });
    },

    /**
     * Clean up after the geolocation attempt finished.
     *
     * @since   2.0.0
     * @param   {object} attempt The attempt that finished, see run()
     * @returns {void}
     */
    attemptFinished: function( attempt ) {
        attempt.finished = true;

        clearInterval( attempt.inProgress );
        clearTimeout( attempt.timer );
        jQuery( '.wpsl-icon-direction' ).removeClass( 'wpsl-active-icon' );
        this.hideLocatingOverlay();
    },

    /**
     * Stop the attempt that is still running, before a new one starts.
     *
     * Its request may still be waiting for an answer, the callbacks then
     * ignore it ( the attempt is no longer the current one ).
     *
     * @since   3.1.0
     * @returns {void}
     */
    endAttempt: function() {
        if ( this.attempt && ! this.attempt.finished ) {
            this.attemptFinished( this.attempt );
        }
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

        const text = sharedHelpers.escapeHtml( wpslLabels.geoLocationLocating );

        $map.append(
            '<div class="wpsl-geolocation-overlay" role="status" aria-live="polite">' +
                '<div class="wpsl-geolocation-overlay-box">' +
                    '<img alt="' + sharedHelpers.escapeHtml( wpslLabels.preloadLabel ) + '" width="18" height="18" src="' + config.search.geoLocationPreloader + '" />' +
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