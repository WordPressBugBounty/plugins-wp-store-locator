import { slData, config } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';
import { search } from './wpsl-search.js';
import { geolocation } from './wpsl-geolocation.js';
import { accessibility } from './wpsl-accessibility.js';

/**
 * Button handlers for WPSL frontend
 * 
 * @since 3.0.0
 */
export const buttons = {
    args: {
        autoLoad: false,
        wasEmpty: true
    },

    /**
     * Execute search based on current state.
     * 
     * @since 3.0.0
     * @param {object} args Search arguments
     * @param {string} typeField Type field value
     */
    executeSearch: function( args, typeField ) {
        if ( ( typeField.length && typeField != 'location' ) || ( ! typeField && typeof wpslSettings.search !== 'undefined' && wpslSettings.search.namesEnabled ) ) {
            config.search.namesEnabled = 1;
            config.search.skipGeocode = true;

            jQuery( '.wpsl-icon-direction' ).hide();

            search.reset();
            search.run( args );
        } else {
            config.search.namesEnabled = 0;
            delete config.search.skipGeocode;

            jQuery( '.wpsl-icon-direction' ).show();
            search.reset();

            // Coordinates are known when a suggestion was picked from the
            // autocomplete, or when the search widget passed the location
            // of a geolocation request along with the searched text.
            if ( slData.autoCompleteLatLng ) {
                args.latLng = slData.autoCompleteLatLng;

                // Border enforcement needs the country code and the statistics
                // add-on needs the address components, both of which only come
                // from the reverse geocode response. Skipping it when either is
                // active leaves restrictions[borders] as 'undefined,undefined'.
                if ( ! config.search.restrictions.borders && typeof config.collectStatistics === 'undefined' ) {
                    slData.skipReverseGeocode = true;
                } else {
                    slData.skipReverseGeocode = false;
                }

                search.prepare( args );
            } else {
                search.submitActions( buttons.args );
            }
        }
    },

    /**
     * Bind search button and input field events.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindSearch: function() {
        const $input = helpers.input.getSearchField();

        let typeField = '', 
            args = {};

        // Make sure the 'enter' input triggers the
        // search instead of opening the next dropdown.
        $input.on( 'keypress', function( e ) {
            if ( e.which == 13 ) {
                jQuery( '#wpsl-search-btn' ).trigger( 'click' );

                return false;
            }
        });

        // v3 templates: show / hide the 'clear' button and remove the error state.
        if ( helpers.flexboxAvailable() ) {
            $input.on( 'input', function() {
                const isEmpty = jQuery( this ).val().length === 0;

                buttons.updateClearSearchVisibility( isEmpty );
                
                // Remove error state when user starts typing
                if ( ! isEmpty ) {
                    jQuery( '.wpsl-search-wrap' ).removeClass( 'wpsl-error' );
                }

                // Force a fresh geocode instead of reusing stale coordinates.
                slData.autoCompleteLatLng = '';
            });
        } else {
            // For v2 templates, remove error class from input field when user starts typing
            $input.on( 'input', function() {
                if ( jQuery( this ).val().length > 0 ) {
                    jQuery( this ).removeClass( 'wpsl-error' );
                }

                // Force a fresh geocode instead of reusing stale coordinates.
                slData.autoCompleteLatLng = '';
            });
        }

        jQuery( '#wpsl-search-btn' ).off( 'click keydown' ).on( 'click keydown', function( e ) {
            // Allow both click and keyboard activation (Enter or Space)
            if ( e.type === 'keydown' && e.which !== 13 && e.which !== 32 ) {
                return;
            }
            
            if ( e.type === 'keydown' ) {
                e.preventDefault();
            }

            jQuery( '#wpsl-search-btn' ).attr( 'disabled', true );
            $input.removeClass();

            // Make sure the previous results and
            // filter are hidden for v3.x+ templates.
            if ( helpers.flexboxAvailable() ) {
                jQuery( '#wpsl-result-list' ).hide();
                jQuery( '.wpsl-search-wrap' ).removeClass( 'wpsl-error' );

                helpers.panel.maybeCloseFilters();
            }

            slData.useBasicMode            = false;
            slData.firstLoadInProgress     = false;

            if ( jQuery( '#wpsl-search-wrap input[name=types]' ).length ) {
                typeField = jQuery( '#wpsl-search-wrap input[name=types]' ).val().trim();
            }

            if ( $input.val() ) {
                buttons.executeSearch( args, typeField );
            } else if ( helpers.flexboxAvailable() && jQuery( '.wpsl-filter input[type="checkbox"]:checked, .wpsl-custom-checkboxes input[type="checkbox"]:checked' ).length > 0 ) {
                // Filter-only search ( category and / or custom panels like country, no user input required, v3.x+ templates )
                config.search.skipGeocode = true;

                // Clear the existing markers / results before running, like the geocoded search path does.
                search.reset();
                search.run( args );
            } else if ( jQuery( '#wpsl-wrap' ).hasClass( 'wpsl-cat-filter-only' ) ) {
                config.search.skipGeocode = true;

                search.reset();
                search.run( args );
            } else {
                jQuery( '#wpsl-search-btn' ).attr( 'disabled', false );
                $input.removeClass();

                if ( helpers.flexboxAvailable() ) {
                    jQuery( '.wpsl-search-wrap' ).addClass( 'wpsl-error' );
                } else {
                    $input.addClass( 'wpsl-error' );
                }

                $input.trigger( 'focus' );
            }

            return false;
        });
    },

    /**
     * Update the visibility of the clear search input button.
     * 
     * @since   3.0.0
     * @param   {boolean} isEmpty Whether the search input is empty
     * @returns {void}
     */
    updateClearSearchVisibility: function( isEmpty ) {
        const $clearBtn = jQuery( '#wpsl-clear-search-input' );

        if ( ! isEmpty ) {
            $clearBtn.show();
            
            if ( ! $clearBtn.data( 'bound' ) ) {
                this.bindEmptySearchInput();
                $clearBtn.data( 'bound', true );
            }
        } else {
            $clearBtn.hide();
            search.reset();
        }
    },

    /**
     * Bind map control icons (reset and direction buttons).
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindMapControlIcons: function() {
        if ( jQuery( '.wpsl-icon-reset, #wpsl-reset-map' ).length > 0 ) {

            this.bindResetMapBtn();

            // Hide it to prevent users from clicking it
            // before the store location are placed on the map.
            if ( jQuery.isEmptyObject( slData.viewport ) ) {
                jQuery( '.wpsl-icon-reset' ).hide();
            } else {
                // Google Maps only adds the controls once its tiles are in, by
                // which time the autoload search has already stored the
                // viewport and tried to show a button that wasn't there yet.
                helpers.map.revealResetBtn();
            }
        }

        // Bind the direction button to trigger a new geolocation request.
        jQuery( '.wpsl-icon-direction' ).on( 'click keydown', function( e ) {
            // For keyboard events, only respond to Enter or Space
            if ( e.type === 'keydown' && ! accessibility.keyboard.isActivationKey( e ) ) {
                return;
            }

            if ( e.type === 'keydown' ) {
                e.preventDefault();
            }

            jQuery( this ).addClass( 'wpsl-user-activated' );
            jQuery( '#wpsl-search-btn' ).attr( 'disabled', true );

            slData.directions.active = false;

            // If the direction details are showing, then hide them.
            if ( jQuery( '#wpsl-direction-details' ).is( ':visible' ) ) {
                jQuery( '.wpsl-back' ).trigger( 'click' );
            }

            geolocation.run( true );
        });
    },

    /**
     * Bind the clear search input button.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindEmptySearchInput: function() {
        jQuery( '#wpsl-clear-search-input' ).off( 'click keydown' ).on( 'click keydown', function( e ) {

            // Allow both click and keyboard activation (Enter or Space)
            if ( e.type === 'keydown' && e.which !== 13 && e.which !== 32 ) {
                return;
            }
            
            if ( e.type === 'keydown' ) {
                e.preventDefault();
            }

            helpers.input.getSearchField().val( '' );
            jQuery( '#wpsl-clear-search-input' ).hide();

            jQuery( '.wpsl-autocomplete-search-results' ).hide();
            
            // Close filters if they're open (same as search button behavior)
            if ( helpers.flexboxAvailable() ) {
                helpers.panel.maybeCloseFilters();
            }
            
            search.reset();

            return false;
        });
    },

    /**
     * Make sure that when the reset button ( bottom corner of the map ) is
     * clicked the map is restored to how it was on pageload.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindResetMapBtn: function() {
        jQuery( '.wpsl-icon-reset , #wpsl-reset-map' ).on( 'click keydown', function( e ) {
            // For keyboard events, only respond to Enter or Space
            if ( e.type === 'keydown' && ! accessibility.keyboard.isActivationKey( e ) ) {
                return;
            }

            if ( e.type === 'keydown' ) {
                e.preventDefault();
            }

            helpers.results.restoreInitialState();
        });
    },

    /**
     * Bind the back button that returns from the directions to the results.
     *
     * @since  3.0.0
     * @returns {void}
     */
    bindRemoveDirections: function() {
        jQuery( '#wpsl-result-list' ).on( 'click', '.wpsl-back', function() {
            slData.provider.api.directions.restoreResults();

            // Move focus to the first store result so the user can keep cycling
            // the list. setTimeout(0) defers until restoreResults() has finished
            // showing #wpsl-stores.
            setTimeout( function() {
                const firstResult = document.querySelector(
                    '#wpsl-stores ul li .wpsl-location-name a, #wpsl-stores ul li a[href]'
                );

                if ( firstResult ) {
                    firstResult.focus();
                } else {
                    // Fallback: focus the result list container itself
                    const resultList = document.querySelector( '#wpsl-stores' );

                    if ( resultList ) {
                        resultList.setAttribute( 'tabindex', '-1' );
                        resultList.focus();
                    }
                }
            }, 0 );

            return false;
        });
    },
};