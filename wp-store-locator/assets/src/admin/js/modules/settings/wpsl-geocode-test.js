import { state, createLoaderHTML } from '../wpsl-shared.js';
import { helpers } from '../wpsl-helpers.js'; 
import { api } from '../wpsl-api.js';
import { mapBootstrap, mapObjects } from '../wpsl-map-bootstrap.js';
import { markers } from '../wpsl-markers.js';

/**
 * Settings page -> Tools: show the raw API response together with a preview of
 * the marker location for the entered address, with any country / zip code only
 * restrictions applied.
 *
 * @since 3.0.0
 */
export const geocodeResponseTest = {
    initialViewport: null,

    // Handle for the safety timeout that force-hides the map loader.
    mapLoaderTimer: null,

    /**
     * Initialize the geocode response test module.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        this.updateButtonState();
        this.bindHandlers();
    },

    /**
     * Bind the required button listeners
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers: function() {
        const self = this;

        const $showGeocodeResponse = jQuery( '#wpsl-show-geocode-response' );
        const $geocodeTest = jQuery( '#wpsl-geocode-test' );
        const $geocodeSubmit = jQuery( '#wpsl-geocode-submit' );
        const $geocodeReset = jQuery( '#wpsl-geocode-reset' );

        $showGeocodeResponse.on( 'click', function( e ) {
            // Don't open the dialog if the button is disabled
            if ( jQuery( this ).hasClass( 'disabled' ) ) {
                return false;
            }

            const $mapContainer = 'wpsl-' + state.mapService + '-geocode-preview';
            const apiKey = wpslSettings.api.key;

            self.createDialog();
            self.cleanupOldMaps();

            // Make sure the target div exist before creating the new map.
            if ( ! jQuery( '#' + $mapContainer ).length ) {
                jQuery( '#wpsl-geocode-map-wrap' ).append( '<div id="' + $mapContainer + '"></div>' );
            }

            const mapElem = mapObjects.get( $mapContainer );
            const $mapContainerElem = jQuery( '#' + $mapContainer );

            // The dialog can be opened multiple times, so only build the map once.
            if ( $mapContainerElem.html() === '' || ! mapElem ) {
                mapBootstrap[ state.mapService ]( { elemId: $mapContainer }, function() {
                    // Remove any markers from a previous test
                    if ( state.activeMarkers.length ) {
                        markers[ state.mapService ].removeAll();
                    }

                    self.restrictionsMsg.create();

                    // Save the initial viewport state for later restoration
                    self.saveInitialViewport();

                    const mapObj = mapObjects.get( $mapContainer );
                    if ( mapObj && typeof api[ state.mapService ].errorListener === 'function' && typeof apiKey === 'string' && apiKey.trim() !== '' ) {
                        api[ state.mapService ].errorListener();
                    }
                });
            } else {
                self.restrictionsMsg.create();

                // Save the initial viewport state for later restoration
                self.saveInitialViewport();

                const mapObj = mapObjects.get( $mapContainer );
                if ( mapObj && typeof api[ state.mapService ].errorListener === 'function' && typeof apiKey === 'string' && apiKey.trim() !== '' ) {
                    api[ state.mapService ].errorListener();
                }
            }

            return false;
        });

        // Submit the geocode request.
        $geocodeSubmit.on( 'click', function( e ) {
            // Make sure to remove notices / error classes from previous test.
            geocodeResponseTest.resetNotices();

            if ( self.testGeocodeDataExists() ) {
                const requestParams = api[ state.mapService ].createParams();

                markers[ state.mapService ].removeAll();

                self.showMapLoader();

                api[ state.mapService ].codeAddress( requestParams );
            } else {
                if ( state.forcePostalCode ) {
                    jQuery( '#wpsl-zip' ).addClass( 'wpsl-error' );
                } else {
                    jQuery( '.wpsl-geocode-input-required' ).show();
                }

                jQuery( '#wpsl-geocode-response textarea' ).val( '' );

                markers[ state.mapService ].removeAll();
            }
        });

        // Handle users using the enter key in the dialog box.
        $geocodeTest.on( 'keydown', function( event ) {
            const keyCode = event.keyCode || event.which;
            if ( keyCode === 13 ) {
                $geocodeSubmit.trigger( 'click' );
            }
        });

        $geocodeReset.on( 'click', function() {
            self.reset( true );
        });
    },

    /**
     * Center the dialog vertically within the container.
     *
     * @since   3.0.0
     * @param   {jQuery} $dialog The dialog element to position
     * @returns {void}
     */
    centerDialogVertically: function( $dialog ) {
        const $container = jQuery( "#wpbody-content" );
        const containerOffsetTop = $container.offset().top;
        const containerHeight = $container.outerHeight();
        const dialogHeight = jQuery( '.ui-dialog.wpsl-geocode-dialog' ).outerHeight() + 25;
    
        const topOffset = Math.max( containerOffsetTop + ( containerHeight - dialogHeight ) / 2, 0 );
        
        $dialog.css({
            top: topOffset + "px"
        });
    },

    /**
     * Create the dialog box
     *
     * @since   2.2.22
     * @returns {void}
     */
    createDialog: function() {
        const $geocodeTest = jQuery( '#wpsl-geocode-test' );
        const $dialogContainer = jQuery( '.ui-dialog.wpsl-geocode-dialog' );

        $geocodeTest.dialog({
            resizable: false,
            height: 'auto',
            width: 750,
            modal: true,
            closeOnEscape: true,
            closeText: '',
            dialogClass: 'wpsl-dialog wpsl-geocode-dialog',
            classes: { 'ui-dialog': 'wpsl-dialog wpsl-geocode-dialog' },
            appendTo: '#wpbody-content',
            position: {
                my: "center",
                at: "center",
                of: "#wpbody-content"
            },
            open: function() {
                const $dialog = jQuery( this );

                // Replace the existing close button with our own
                helpers.ui.dialog.addCloseButton( 'wpsl-geocode-dialog' );

                // Always bind the overlay click handler when dialog opens
                helpers.ui.dialog.bindCloseHandler( '#wpsl-geocode-test' );

                // With zip only searches enabled, hide every other input field.
                if ( state.forcePostalCode ) {
                    const $geocodeInputs = jQuery( '.wpsl-geocode-input p' );
                    
                    for ( let i = 0; i < $geocodeInputs.length; i++ ) {
                        const $input = jQuery( $geocodeInputs[i] );
                        if ( $input.find( 'input[type=text]' ).attr( 'id' ) !== 'wpsl-zip' ) {
                            $input.hide();
                        } else {
                            $input.css( 'margin-top', '1px' );
                        }
                    }
                }

                jQuery( '#wpsl-geocode-tabs' ).tabs({
                    activate: function( event, ui ) {
                         // Sometimes after closing / opening the dialog the map 
                         // would remain hidden, so we force it to be visible.
                        if ( ui.newPanel.attr( 'id' ) === 'wpsl-geocode-map-wrap' ) {
                            jQuery( '#wpsl-geocode-map-wrap' ).show();
                        }
                    }
                });

                // Ensure map container is visible if it's the active tab
                if ( jQuery( '#wpsl-geocode-tabs' ).tabs( 'option', 'active' ) === 0 ) {
                    jQuery( '#wpsl-geocode-map-wrap' ).show();
                   
                    // Make sure the map container for the active map service is visible
                    jQuery( '#wpsl-' + state.mapService + '-geocode-preview' ).show();
                }

                // Reset the fields; false marks this as not a manual reset.
                geocodeResponseTest.reset( false );

                jQuery( '.ui-widget-overlay' ).on( 'click', function() {
                    $geocodeTest.dialog( 'close' );
                });

                geocodeResponseTest.handleResponsiveDialog( $dialog, $dialogContainer );
                geocodeResponseTest.centerDialogVertically( $dialogContainer );
            },
            close: function() {
                jQuery( '.ui-widget-overlay, .wpsl-dialog-close' ).off( 'click' );
                jQuery( window ).off( 'resize.geocodeDialog' );
            },
        });
    },

    /**
     * Check if at least one input field holds data for the geocode request.
     *
     * @since   3.0.0
     * @returns {bool} dataExists
     */
    testGeocodeDataExists: function() {
        const $inputs = jQuery( '.wpsl-geocode-input input[type=text]' );
        for ( let i = 0; i < $inputs.length; i++ ) {
            if ( jQuery( $inputs[i] ).val() ) {
                return true;
            }
        }

        return false;
    },

    /**
     * Handle the messages explaining the restrictions currently applied to
     * the API response.
     *
     * @since 3.0.0
     */
    restrictionsMsg: {
        /**
         * Tell the user the results are restricted to the selected map region,
         * and possibly to zip codes only.
         *
         * @since   2.2.22
         * @returns {void}
         */
        create: function () {
            const $warningElem = jQuery( '.wpsl-geocode-warning' ).show();
            const $strongElem = $warningElem.find( 'strong' );
            const additionalRestrictions = state.forcePostalCode ? wpslL10n.restrictedZipCode : '';

            // Clear any previous warning message
            $warningElem.find( 'span' ).remove();

            let requestParams;
            let regionRestrictions = '';
            let restrictionMessage = '';
            let args = {};

            /**
             * Whether the selection limits results or only favours them.
             * Google is the only provider that can do either; the others
             * always restrict server-side, however many countries are selected.
             */
            let isHardRestriction = false;

            // Check for restrictions based on the currently selected map service
            if ( state.mapService === 'gmaps' ) {

                /**
                 * Google's two modes read from different settings. 'bias' uses
                 * the 'Bias country' dropdown. 'restrict' ignores that dropdown
                 * ( the region parameter is dropped server-side ) and uses the
                 * country multiselect. Only one country becomes a real
                 * restriction ( componentRestrictions takes one ), so none or
                 * several means nothing is claimed here.
                 */
                if ( jQuery( '#wpsl-region-restriction-type' ).val() === 'restrict' ) {
                    const selectedCountries = geocodeResponseTest.getSelectedCountries();

                    if ( selectedCountries.length === 1 ) {
                        regionRestrictions = selectedCountries[0].label;
                        restrictionMessage = regionRestrictions;
                        isHardRestriction  = true;

                        requestParams = {
                            address: regionRestrictions
                        };
                    }
                } else if ( jQuery( '#wpsl-api-region' ).val() ) {
                    regionRestrictions = jQuery( '#wpsl-api-region option:selected' ).text();
                    restrictionMessage = regionRestrictions;

                    requestParams = {
                        address: regionRestrictions
                    };
                }
            } else if ( state.mapService === 'mapbox' || state.mapService === 'osm' || state.mapService === 'stadia' ) {
                const selectedCountries = geocodeResponseTest.getSelectedCountries();

                if ( selectedCountries.length ) {
                    regionRestrictions = selectedCountries.map( function( country ) {
                        return country.label;
                    }).join( ', ' );

                    restrictionMessage = regionRestrictions;

                    // Stadia, Nominatim and Mapbox all filter server-side, so
                    // every selected country is a hard restriction here.
                    isHardRestriction = true;

                    // Get the first region for the initial geocode request
                    const firstLabel = selectedCountries[0].label.toLowerCase();
                    if ( firstLabel ) {
                        if ( state.mapService === 'mapbox' ) {
                            args = {
                                json: firstLabel,
                                ignoreZip: true
                            };
                        } else if ( state.mapService === 'osm' || state.mapService === 'stadia' ) {
                            args = {
                                country: firstLabel
                            };
                        }
                    }

                    if ( ! jQuery.isEmptyObject( args ) ) {
                        requestParams = api[ state.mapService ].createParams(args);

                        state.countryRestriction = {
                            requestParams: requestParams,
                            regionName: regionRestrictions
                        };
                    }
                }
            }

            const resultsWarning = isHardRestriction ? wpslL10n.resultsRestricted : wpslL10n.resultsBiased;

            if ( regionRestrictions ) {
                if ( additionalRestrictions ) {
                    restrictionMessage = restrictionMessage + ' ' + additionalRestrictions;
                }

                $strongElem.after( '<span class="wpsl-restriction-warning-' + state.mapService + '">' + resultsWarning + ' ' + restrictionMessage + '.</span>' );

                state.countryRestriction = {
                    requestParams: requestParams,
                    regionName: regionRestrictions
                };

                /**
                 * Geocode the restriction itself to centre the preview map, and
                 * remember where it landed. A later empty search returns here
                 * instead of the default start, which may sit in a country the
                 * results would never come from. With several countries, the
                 * first one is used.
                 */
                state.focusRegion.active = true;

                api[ state.mapService ].codeAddress( requestParams );
            } else if ( state.forcePostalCode ) {
                // Zip code only search is a hard restriction on its own.
                $strongElem.after( '<span>' + wpslL10n.resultsRestricted + ' ' + wpslL10n.zipCodes + '.</span>' );
            } else {
                $strongElem.after( '<span>' + wpslL10n.noRestriction + '</span>' );

                jQuery( '.wpsl-region-href' ).on( 'click', function() {
                    jQuery( '.ui-widget-overlay' ).trigger( 'click' );
                });
            }
        },
    },
    /**
     * Show the status of the geocode request to the user.
     *
     * OSM / Google Maps have different responses, but it will
     * be something like 'OK', 'ZERO_RESULTS'.
     *
     * @since   3.0.0
     * @param   {string} msg The returned status message
     * @returns {void}
     */
    status: function( msg ) {
        this.hideMapLoader();

        jQuery( '.wpsl-geocode-api-notice' ).show();
        jQuery( '.wpsl-geocode-api-notice span' ).html( msg );
    },

    /**
     * Warn when Mapbox answered with something less precise than asked.
     *
     * Mapbox only grades address results ( via match_code ), so a full address
     * that comes back as a postcode or region carries no quality signal and
     * reads like an ordinary hit. This happens when a country restriction
     * excludes the real address: searching a Dutch postcode while restricted
     * to Australia returns an Australian postcode, because its digits were the
     * only part that matched inside Australia.
     *
     * The returned type is compared against the most precise field filled in,
     * so a zip-only or city-only search is not warned when it returns exactly that.
     *
     * @since   3.0.0
     * @see     https://docs.mapbox.com/api/search/geocoding/
     * @param   {string} featureType The feature_type of the returned result.
     * @returns {void}
     */
    maybeShowImpreciseNotice: function( featureType ) {
        jQuery( '.wpsl-geocode-partial-match' ).remove();

        if ( typeof wpslL10n.impreciseMatch !== 'string' ) {
            return;
        }

        // Roughly how precise each Mapbox feature type is. An unrecognised type
        // scores highest so a value added later never triggers a false warning.
        const precision = {
            address: 5, street: 4, postcode: 3, neighborhood: 3,
            locality: 2, place: 2, district: 1, region: 1, country: 0
        };

        // The most precise filled field decides what was actually asked for.
        const fields = [
            { selector: '#wpsl-address', rank: 5 },
            { selector: '#wpsl-zip',     rank: 3 },
            { selector: '#wpsl-city',    rank: 2 },
            { selector: '#wpsl-state',   rank: 1 },
            { selector: '#wpsl-country', rank: 0 }
        ];

        let expected = null;

        for ( let i = 0; i < fields.length; i++ ) {
            if ( ( jQuery( fields[i].selector ).val() || '' ).trim() ) {
                expected = fields[i].rank;
                break;
            }
        }

        const returned = ( typeof precision[ featureType ] === 'number' ) ? precision[ featureType ] : 99;

        if ( expected === null || returned >= expected ) {
            return;
        }

        jQuery( '.wpsl-geocode-api-notice' ).after(
            '<p class="wpsl-geocode-partial-match"><span class="wpsl-info wpsl-warning"></span>' +
            wpslL10n.impreciseMatch.replace( '%s', featureType ) +
            '</p>'
        );
    },

    /**
     * Look up the ISO code for a typed country name.
     *
     * The country multiselect carries every name / code pair as data-label and
     * value on its checkboxes, so the typed name is matched against that list
     * rather than a second table. An unknown spelling ( 'Holland' ) returns '',
     * keeping the caller silent rather than guessing.
     *
     * @since   3.0.0
     * @param   {string} name The country name typed into the dialog.
     * @returns {string} The lowercase ISO 3166-1 alpha-2 code, or ''.
     */
    getCountryCodeForName: function( name ) {
        const wanted = ( name || '' ).trim().toLowerCase();

        if ( ! wanted ) {
            return '';
        }

        let code = '';

        jQuery( '#wpsl-multiselect-countries' )
            .closest( '.wpsl-multiselect-container' )
            .find( '.wpsl-multiselect-menu input[type="checkbox"]' )
            .each( function() {
                if ( ( jQuery( this ).attr( 'data-label' ) || '' ).trim().toLowerCase() === wanted ) {
                    code = ( jQuery( this ).val() || '' ).toLowerCase();

                    return false;
                }
            });

        return code;
    },

    /**
     * Pull the ISO country code out of a geocode response.
     *
     * Every provider reports one, each in its own place and its own casing.
     *
     * @since   3.0.0
     * @param   {object|array} response The raw API response.
     * @returns {string} The lowercase ISO 3166-1 alpha-2 code, or ''.
     */
    getResponseCountryCode: function( response ) {
        let code = '';

        if ( state.mapService === 'osm' ) {
            if ( response && response[0] && response[0].address ) {
                code = response[0].address.country_code;
            }
        } else if ( state.mapService === 'gmaps' ) {
            const components = ( response && response[0] && response[0].address_components ) || [];

            for ( let i = 0; i < components.length; i++ ) {
                if ( components[i].types && components[i].types.indexOf( 'country' ) !== -1 ) {
                    code = components[i].short_name;
                    break;
                }
            }
        } else {
            const properties = ( response && response.features && response.features[0] && response.features[0].properties ) || {};
            const context    = properties.context || {};

            if ( state.mapService === 'stadia' ) {
                code = context.iso_3166_a2;
            } else if ( state.mapService === 'mapbox' ) {
                code = ( context.country || {} ).country_code;
            }
        }

        return ( code || '' ).toLowerCase();
    },

    /**
     * Collect the countries currently selected in the multiselect.
     *
     * Reads the rendered tags, not the checkboxes: the id belongs to the
     * <button> holding the tags, while the checkboxes live in a sibling
     * .wpsl-multiselect-menu, so a descendant :checked selector matches
     * nothing. Both halves are needed: the label for the warning, the
     * data-value for the ISO code the APIs expect.
     *
     * @since   3.0.0
     * @returns {array} Objects holding a label and a value key.
     */
    getSelectedCountries: function() {
        const selected = [];

        jQuery( '#wpsl-multiselect-countries .wpsl-multiselect-tag' ).each( function() {
            const $tag  = jQuery( this );
            const label = $tag.find( '.wpsl-multiselect-tag-text' ).text();
            const value = $tag.find( '.wpsl-multiselect-tag-remove' ).attr( 'data-value' );

            if ( label && value ) {
                selected.push( { label: label, value: value } );
            }
        });

        return selected;
    },

    /**
     * Warn when the result sits in a different country than entered.
     *
     * Stadia and Mapbox answer a restricted search by dropping the parts of
     * the query that can't match and keeping the parts that can, producing a
     * confident result in the wrong country: a Dutch address restricted to
     * Australia returns '7 Amicitia Circuit, Northmead NSW 2152' with
     * match_type 'match' and confidence 1. Nothing in the response admits the
     * error, so the only reliable signal is the country the visitor typed.
     *
     * @since   3.0.0
     * @param   {object|array} response The raw API response.
     * @returns {void}
     */
    maybeShowCountryMismatchNotice: function( response ) {
        const $restrictionNote = jQuery( '.wpsl-geocode-warning' );

        jQuery( '.wpsl-geocode-country-mismatch' ).remove();

        /**
         * The restriction note is folded into the mismatch text below, so it
         * only stands alone when there's no mismatch. Restored on every run,
         * since it's written once at dialog open and would otherwise stay
         * hidden after a single mismatch.
         */
        $restrictionNote.show();

        const typed = ( jQuery( '#wpsl-country' ).val() || '' ).trim();

        if ( ! typed ) {
            return;
        }

        const expected = this.getCountryCodeForName( typed );
        const returned = this.getResponseCountryCode( response );

        // Both codes have to be known before a disagreement means anything: an
        // unrecognised spelling is not a mismatch, it is a lookup miss.
        if ( ! expected || ! returned || expected === returned ) {
            return;
        }

        /**
         * Explain the cause that applies. A hard restriction makes the API drop
         * what it can't match inside the allowed countries; without one it
         * searched the whole world and something abroad scored higher.
         */
        const isRestricted = geocodeResponseTest.hasHardCountryRestriction();
        const message      = isRestricted ? wpslL10n.countryMismatchRestricted : wpslL10n.countryMismatchGlobal;

        if ( typeof message !== 'string' ) {
            return;
        }

        let text;

        if ( isRestricted ) {
            const restrictedTo = geocodeResponseTest.getSelectedCountries().map( function( country ) {
                return country.label;
            }).join( ', ' );

            text = message.replace( '%1$s', typed ).replace( '%2$s', restrictedTo );
        } else {
            text = message.replace( '%s', typed );
        }

        /**
         * A spaced postcode is a common cause: Stadia indexes '2152 KC' as
         * '2152KC' and matches only the digits, enough to pull the answer into
         * another country. Suggested only when there is a space to remove.
         */
        if ( /\s/.test( ( jQuery( '#wpsl-zip' ).val() || '' ).trim() ) && typeof wpslL10n.postcodeSpaceHint === 'string' ) {
            text = text + ' ' + wpslL10n.postcodeSpaceHint;
        }

        /**
         * Replace the generic notice rather than stacking. Google's partial-match
         * and Mapbox precision warnings both say the answer isn't quite right;
         * this one names the country, the cause, and what to try, so the most
         * specific diagnosis wins.
         */
        jQuery( '.wpsl-geocode-partial-match' ).remove();
        $restrictionNote.hide();

        jQuery( '.wpsl-geocode-api-notice' ).after(
            '<p class="wpsl-geocode-country-mismatch"><span class="wpsl-info wpsl-warning"></span>' + text + '</p>'
        );
    },

    /**
     * Whether results are actually limited to a set of countries.
     *
     * Only Google can bias instead of restrict, and only a single country in
     * hard-restrict mode reaches componentRestrictions; anything else on Google
     * leaves the search unrestricted. Other providers filter server-side on
     * every selected country.
     *
     * @since   3.0.0
     * @returns {boolean}
     */
    hasHardCountryRestriction: function() {
        if ( state.mapService === 'gmaps' ) {
            return jQuery( '#wpsl-region-restriction-type' ).val() === 'restrict'
                && geocodeResponseTest.getSelectedCountries().length === 1;
        }

        return geocodeResponseTest.getSelectedCountries().length > 0;
    },

    /**
     * Hide feedback texts and remove error classes from the input fields.
     *
     * @since   3.0.0
     * @returns {void}
     */
    resetNotices: function() {
        jQuery( '.wpsl-geocode-api-notice, .wpsl-geocode-partial-match, .wpsl-geocode-country-mismatch, .wpsl-geocode-input-required' ).hide();
        jQuery( '#wpsl-geocode-test input' ).removeClass( 'wpsl-error' );
    },

    /**
     * Show a preloader overlay on the geocode preview map.
     *
     * OSM / Nominatim can take a noticeable moment to respond, which looks like
     * nothing is happening after "Get Response". A safety timer force-hides the
     * loader so a dropped or failed request can't leave it spinning forever.
     *
     * @since   3.0.0
     * @returns {void}
     */
    showMapLoader: function() {
        // Overlay the currently visible tab panel ( Map Preview or API Response )
        // so the feedback is seen whichever tab the user is on. Fall back to the
        // map container when the tabs aren't initialised.
        let $target = jQuery( '#wpsl-geocode-tabs .ui-tabs-panel:visible' ).first();

        if ( ! $target.length ) {
            $target = jQuery( "div[id^='wpsl-'][id$='-geocode-preview']" ).first();
        }

        if ( ! $target.length || $target.find( '.wpsl-geocode-map-loader' ).length ) {
            return;
        }

        $target.append(
            '<div class="wpsl-geocode-map-loader">' +
                createLoaderHTML() +
            '</div>'
        );

        clearTimeout( this.mapLoaderTimer );
        this.mapLoaderTimer = setTimeout( this.hideMapLoader, 15000 );
    },

    /**
     * Remove the geocode preview map preloader overlay.
     *
     * @since   3.0.0
     * @returns {void}
     */
    hideMapLoader: function() {
        clearTimeout( geocodeResponseTest.mapLoaderTimer );

        jQuery( '.wpsl-geocode-map-loader' ).remove();
    },

    /**
     * Handle responsive dialog width and layout adjustments.
     *
     * @since   3.0.0
     * @param   {jQuery} $dialog          The dialog content element
     * @param   {jQuery} $dialogContainer The dialog container element
     * @returns {void}
     */
    handleResponsiveDialog: function( $dialog, $dialogContainer ) {
        const self = this;
        
        const adjustDialogLayout = function() {
            const viewportWidth = jQuery( window ).width();
            const $container = jQuery( '.wpsl-geocode-test-container' );
            
            // Adjust dialog width for smaller viewports
            if ( viewportWidth < 800 ) {
                const dialogWidth = Math.min( viewportWidth - 40, 650 );
                
                $dialogContainer.css( 'width', dialogWidth + 'px' );
                
                // Remove flex layout on smaller screens
                if ( viewportWidth < 650 ) {
                    $container.css( 'display', 'block' );
                } else {
                    $container.css( 'display', 'flex' );
                }
            } else {
                $dialogContainer.css( 'width', '750px' );
                $container.css( 'display', 'flex' );
            }
            
            // Recenter the dialog after width adjustment
            $dialog.dialog( 'option', 'position', {
                my: "center",
                at: "center",
                of: "#wpbody-content"
            });

            self.centerDialogVertically( $dialogContainer );
        };

        adjustDialogLayout();

        jQuery( window ).on( 'resize.geocodeDialog', function() {
            adjustDialogLayout();
        });
    },

    /**
     * Clean up old map containers and their associated map objects
     *
     * @since   3.0.0
     * @returns {void}
     */
    cleanupOldMaps: function() {
        const mapServices = ['gmaps', 'mapbox', 'osm', 'stadia'];
        mapServices.forEach( function( service ) {
            const containerId = 'wpsl-' + service + '-geocode-preview';

            if ( service !== state.mapService ) {
                // Remove any markers belonging to the old service before destroying the map
                if ( markers[ service ] && typeof markers[ service ].removeAll === 'function' ) {
                    markers[ service ].removeAll();
                }

                const mapObj = mapObjects.getById( containerId );

                if ( mapObj && typeof mapObj.remove === 'function' ) {
                    mapObj.remove();
                }

                if ( jQuery( '#' + containerId ).length ) {
                    jQuery( '#' + containerId ).remove();
                }

                mapObjects.active = mapObjects.active.filter( function( obj ) {
                    return obj.id !== containerId;
                });
            }
        });

        if ( state.activeMarkers.length > 0 ) {
            state.activeMarkers = [];
        }

        state.countryRestriction = null;
    },

    /**
     * Save the viewport the dialog opened on, for the reset button to restore.
     *
     * @since   3.0.0
     * @returns {void}
     */
    saveInitialViewport: function() {
        const $mapContainer = 'wpsl-' + state.mapService + '-geocode-preview';
        
        const mapObj = mapObjects.get( $mapContainer );
        if ( mapObj ) {
            let center = null;
            let zoom = null;

            if ( typeof mapObj.getCenter === 'function' ) {
                center = mapObj.getCenter();
            }

            if ( typeof mapObj.getZoom === 'function' ) {
                zoom = mapObj.getZoom();
            }

            if ( center && zoom ) {
                this.initialViewport = {
                    center: center,
                    zoom: zoom,
                    mapService: state.mapService
                };
            } else {
                this.initialViewport = null;
            }
        } else {
            this.initialViewport = null;
        }
    },

    /**
     * Update the button state based on whether the active map service requires an API key
     *
     * @since   3.0.0
     * @returns {void}
     */
    updateButtonState: function() {
        const $button = jQuery( '#wpsl-show-geocode-response' );
        let hasApiKey = false;

        // OSM doesn't require an API key, so always enable it
        if ( state.mapService === 'osm' ) {
            hasApiKey = true;
        } else if ( state.mapService === 'stadia' ) {
            const $stadiaKey = jQuery( '#wpsl-api-stadia-key' );
            hasApiKey = $stadiaKey.length && $stadiaKey.val().trim().length > 0;
        } else if ( state.mapService === 'gmaps' ) {
            const $gmapsBrowserKey = jQuery( '#wpsl-api-browser-key' );
            hasApiKey = $gmapsBrowserKey.length && $gmapsBrowserKey.val().trim().length > 0;
        } else if ( state.mapService === 'mapbox' ) {
            const $mapboxKey = jQuery( '#wpsl-api-mapbox-key' );
            hasApiKey = $mapboxKey.length && $mapboxKey.val().trim().length > 0;
        }

        $button.toggleClass( 'disabled', ! hasApiKey );
    },

    /**
     * Restore all fields to how they
     * were before the dialog was opened.
     *
     * @since   3.0.0
     * @param   {boolean} isManual Whether the reset was triggered manually by the user (true) or automatically (false)
     * @returns {void}
     */
    reset: function( isManual = false ) {
        this.resetNotices();

        jQuery( '#wpsl-geocode-address' ).trigger( 'focus' );

        // Make sure to remove notices / error classes from previous test.
        jQuery( '#wpsl-geocode-test .wpsl-error, #wpsl-geocode-test .wpsl-notice' ).remove();
        jQuery( '#wpsl-geocode-tabs' ).tabs( 'option', 'active', jQuery( '#wpsl-geocode-tabs li' ).index( jQuery( '#wpsl-geocode-tabs li:visible:eq(0)' ) ) );

        // Make sure to remove any previous input
        jQuery( '#wpsl-geocode-test input[type=text], #wpsl-geocode-response textarea' ).val( '' );

        // Remove markers from the map, then clear the tracking array.
        if ( markers[ state.mapService ] && typeof markers[ state.mapService ].removeAll === 'function' ) {
            markers[ state.mapService ].removeAll();
        }

        state.activeMarkers = [];

        // Restore the viewport to the initial state when manually resetting
        if ( isManual && this.initialViewport ) {
            const $mapContainer = 'wpsl-' + state.mapService + '-geocode-preview';
            const mapObj = mapObjects.get( $mapContainer );

            if ( mapObj && this.initialViewport.center && typeof this.initialViewport.zoom === 'number' ) {
                // Handle Leaflet maps (OSM/Mapbox)
                if ( typeof mapObj.setView === 'function' ) {
                    /*
                     * Leaflet's setView takes [lat, lng] and zoom. A Google
                     * LatLng exposes lat/lng as methods and Leaflet as plain
                     * numbers, so the coordinate is picked by type: 0 is a
                     * valid coordinate and falsy, and calling it as a method
                     * on the equator threw.
                     */
                    const center = this.initialViewport.center;
                    const lat    = ( 'function' === typeof center.lat ) ? center.lat() : center.lat;
                    const lng    = ( 'function' === typeof center.lng ) ? center.lng() : center.lng;
                    mapObj.setView( [lat, lng], this.initialViewport.zoom );
                }
                // Handle Google Maps
                else if ( typeof mapObj.setCenter === 'function' && typeof mapObj.setZoom === 'function' ) {
                    mapObj.setCenter( this.initialViewport.center );
                    mapObj.setZoom( this.initialViewport.zoom );
                }
                // Fallback
                else {
                    helpers.map.setViewport({
                        'latLng': this.initialViewport.center,
                        'zoom': this.initialViewport.zoom,
                        'addMarker': false
                    });
                }

                // If we had a country restriction, reapply it to restore markers
                if ( state.countryRestriction && state.countryRestriction.requestParams ) {
                    api[ state.mapService ].codeAddress( state.countryRestriction.requestParams );
                }
            }
        }
    },
};