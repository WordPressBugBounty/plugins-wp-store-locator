import { state } from '../wpsl-shared.js';
import { sharedHelpers } from '../../../../common/wpsl-shared-helpers.js';
import { bootloader } from './wpsl-bootloader.js';
import { notice } from '../wpsl-notice.js';
import { preloader } from '../wpsl-preloader.js';
import { createApiRequest } from '../../../../common/wpsl-core.js';
import { helpers } from '../wpsl-helpers.js';

/**
 * Verify the provided API keys.
 *
 * @since 2.2.22
 */
export const verifyKeys = {

    /**
     * Initialize API key verification handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        const isOnboarding = jQuery( 'body' ).hasClass( 'wpsl-onboarding' );
        const $apiContainer = isOnboarding ? jQuery( '#wpsl-onboarding-api-keys' ) : jQuery( '#wpsl-api' );

        // Verify a changed, unverified Google Maps browser key before the
        // settings are saved.
        jQuery( '#wpsl-settings-form' ).on( 'submit', function( e ) {
            if ( state.mapService !== 'gmaps' ) {
                return;
            }
            
            const $form = jQuery( this );

            const $browserKey = jQuery( '#wpsl-api-browser-key' ).val().trim();
            if ( $browserKey !== wpslSettings.api.key ) {
                e.preventDefault();

                if ( $browserKey ) {
                    jQuery.get( "https://maps.googleapis.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&key=" + $browserKey, function( data ) {
                        // Wait for the status to be persisted before submitting, or the
                        // page navigation can abort the AJAX call before it lands and
                        // the key wrongly shows as invalid again after the reload.
                        verifyKeys.updateValidationStatus( 'gmaps_browser', data.status === 'OK' ? 1 : 0 ).always( function() {
                            $form.off( 'submit' ).submit();
                        } );
                    });
                } else {
                    verifyKeys.updateValidationStatus( 'gmaps_browser', 0 ).always( function() {
                        $form.off( 'submit' ).submit();
                    } );
                }
            }
        });

        // Verify the API keys when the verify button is clicked.
        //
        // @since 3.0.0
        $apiContainer.on( 'click', '.wpsl-verify-keys', function() {
            const $self = jQuery( this );
            if ( $self.hasClass( 'disabled' ) ) {
                return;
            }

            $self.attr( 'disabled', 'disabled' );
            
            const $container = jQuery( '#wpsl-onboarding-api-keys' ).length ? '#wpsl-onboarding-api-keys' : '#wpsl-settings-form';
            const $keyInputs = jQuery( $container + ' .wpsl-key-input' );
            let keys = {};

            if ( typeof createApiRequest[ state.mapService ].consoleNoticeVisible === 'boolean' ) {
                createApiRequest[ state.mapService ].consoleNoticeVisible = false;
            }

            /**
             * bootloader.add() rebuilds the Maps environment, so an earlier
             * conflict no longer applies. Hold the notice back until the run
             * finishes, or it renders only to be swept away by preloader.add().
             */
            createApiRequest.suppressConflictNotice = true;

            createApiRequest.monitorConsoleOutput( true );

            // Collect the keys
            for ( let i = 0; i < $keyInputs.length; i++ ) {
                const $input = jQuery( $keyInputs[i] );
                const fullName = $input.attr( 'name' );
                
                // Extract the last part of the name (after the last bracket)
                const keyMatch = fullName.match(/\[([^\]]+)\]$/);
                const keyName = keyMatch ? keyMatch[1] : fullName;
                
                keys[ keyName ] = $input.val();
            }

            bootloader.add( keys, function() {
                preloader.add( $self, 'validateKeys' );

                // Stop monitoring once the verifications are likely done, most
                // complete within 3 seconds.
                setTimeout( function() {
                    createApiRequest.stopMonitoringConsole();
                    createApiRequest.suppressConflictNotice = false;

                    // Re-enable the button after validation is complete
                    $self.removeAttr( 'disabled' );
                }, 3000 );
            });

            return false;
        });

        // Handle input changes for API key fields - delegate to the API container
        $apiContainer.on( 'input', '.wpsl-key-input', function() {
            verifyKeys.maybeDisableOption();
        });
    },

    /**
     * Make the AJAX request to check the status of the used API keys.
     *
     * @since 3.0.0
     * @param {object}   ajaxData The AJAX request data holding the API key
     * @param {string}   keyType  The type of key ( server / browser / directions )
     * @param {function} callback
     */
    requestStatus: function( ajaxData, keyType, callback ) {
        const keyName = Object.keys( ajaxData.wpsl_api )[0];
        let status;

        if ( typeof ajaxData.wpsl_api[ keyName ] !== 'undefined' && ajaxData.wpsl_api[ keyName ].length ) {
            jQuery.post( wpslSettings.ajaxurl, ajaxData, function( response ) {
                // A valid key can still come back with a caveat ( Stadia keys
                // without reverse geocoding access ), which needs the yellow
                // notice rather than the green one.
                if ( response.valid ) {
                    status = response.warning ? 'notice-warning' : 'updated';
                } else {
                    status = 'error';
                }

                verifyKeys.createResponseMsg( response, keyName, status );

                if ( keyType !== 'openrouteservice' && typeof callback === 'function' ) {
                    callback();
                }
            });
        } else {
            verifyKeys.createResponseMsg( wpslL10n[ keyName + 'KeyMissing' ], keyName );

            if ( keyType !== 'openrouteservice' && typeof callback === 'function' ) {
                callback();
            }
        }
    },

    /**
     * Prepare data for the notice showing the API response.
     *
     * @since   2.2.22
     * @param   {mixed}  response   The API response
     * @param   {string} keyType 	The type of API key we need to show the notice for
     * @param   {string} noticeType Show either an error or success notice.
     * @returns {void}
     */
    createResponseMsg: function( response, keyType, noticeType ) {
        let msg, details;

        if ( typeof noticeType === 'undefined' ) {
            noticeType = 'error';
        }

        // Prevent duplicate notices for the same API key ( browser / server ).
        if ( jQuery( '.notice' ).hasClass( 'wpsl-' + keyType + '-key' ) ) {
            // Still clear the preloader; this is a completed verification step.
            preloader.remove();
            return;
        }

        if ( typeof response === 'string' ) {
            msg = response;
            details = '';
        } else if ( typeof response === 'object' && response !== null ) {
            // Normal response shape: { valid, msg, details }
            // wp_send_json_error() shape: { success: false, data: { message } }
            msg     = response.msg ?? response.data?.message ?? wpslL10n.securityFail;
            details = response.details ?? '';
        }

        if ( ! msg ) {
            msg = wpslL10n.securityFail;
        }

        const args = {
            field: 'apiKeys',
            msg: msg,
            details: details,
            keyType: keyType,
            type: noticeType
        };

        notice.create( args );
        preloader.remove();
    },

    /**
     * Only enable the option to verify the API keys once all keys are provided.
     *
     * @since   3.0.0
     * @returns {void}  
     */
    maybeDisableOption: function() {
        const $inputs = jQuery( '.wpsl-api-' + state.mapService + ' .wpsl-key-input' );
        const $verifyButton = jQuery( '#wpsl-verify-' + state.mapService + '-keys' );
        const allHaveValues = Array.from( $inputs ).every( input => input.value.trim().length > 0 );
        
        $verifyButton.toggleClass( 'disabled', ! allHaveValues );
        
        if ( ! allHaveValues ) {
            notice.remove();
        }
    },
    /**
     * Update the validation status for an API key.
     * 
     * @since   3.0.0
     * @param   {string} keyType The type of key (mapbox, gmaps_browser, gmaps_server, openrouteservice)
     * @param   {number} status  The validation status (1 for valid, 0 for invalid)
     * @returns {jqXHR} The AJAX request, so callers can wait for it to finish
     *                  before doing something that unloads the page (e.g. a
     *                  form submit), otherwise the browser can abort the
     *                  request before the status is persisted.
     */
    updateValidationStatus: function( keyType, status ) {
        const ajaxData = {
            action: 'wpsl_update_validation_status',
            nonce: wpslSettings.updateValidationStatusNonce,
            key_type: keyType,
            status: status
        };

        return jQuery.post( wpslSettings.ajaxurl, ajaxData );
    },

    gmaps: {
        /**
         * Make a request to the Google Geocode API to
         * make sure the used API keys are valid.
         *
         * @since   2.2.22
         * @returns {void}
         */
        check: function() {
            this.server( function() {
                verifyKeys.gmaps.browser();
           });
        },

        /**
         * Make a request to the Google Geocode API to
         * check if the server key is valid or not.
         *
         * @since   2.2.22
         * @param   {function} callback
         * @returns {void}
         */
        server: function( callback ) {
            const ajaxData = {
                    action: 'wpsl_validate_key',
                    nonce: wpslSecurity.validateKeyNonce,
                    wpsl_map_service: 'gmaps',
                    wpsl_api: {
                        server: jQuery( '#wpsl-api-server-key' ).val().trim()
                    }
                };

            return verifyKeys.requestStatus( ajaxData, 'server', callback );
        },

        /**
         * Make a request to the Google JavaScript API to
         * check if the browser key is valid or not.
         *
         * @since   2.2.22
         * @returns {void}
         */
        browser: function() {
            preloader.add( jQuery( '#wpsl-verify-gmaps-keys' ) );

            const browserKey = jQuery( '#wpsl-api-browser-key' ).val().trim();
            if ( browserKey ) {
                createApiRequest.gmaps.geocode( { 'address': '1600 Amphitheatre Parkway, Mountain View, CA' }, function( response, status ) {

                    if ( status === google.maps.GeocoderStatus.OK ) {
                        // The onboarding reads this hidden input, and falls back
                        // to OpenStreetMaps when the browser key didn't validate.
                        if ( jQuery( '#wpsl-onboarding' ).length ) {
                            jQuery( '#wpsl-onboarding-api-keys input[type=hidden]:last' ).after( '<input type="hidden" name="browser_key_validated" value="true">' );
                        }

                        verifyKeys.createResponseMsg( wpslL10n.browserKeySuccess, 'browser', 'updated' );
                        verifyKeys.updateValidationStatus( 'gmaps_browser', 1 );
                    } else {
                        if ( ! createApiRequest.gmaps.consoleNoticeVisible ) {
                            const noticeArgs = {
                                msg: wpslApiErrors.gmaps.errorReturned,
                                details: '<p>' + sharedHelpers.escapeHtml( status ) + '</p>' + wpslApiErrors.gmaps.errorNoticeFooter
                            };

                            verifyKeys.createResponseMsg( noticeArgs, 'browser', 'error' );
                        } else {
                            preloader.remove();
                        }

                        verifyKeys.updateValidationStatus( 'gmaps_browser', 0 );
                    }
                });
            } else {
                verifyKeys.createResponseMsg( wpslL10n.browserKeyMissing, 'browser' );
                verifyKeys.updateValidationStatus( 'gmaps_browser', 0 );
            }
        },
    },

    osm: {
        /**
         * Make a request to the Nominatim API to make sure we get a valid response,
         * and optionally check if the provided openrouteservice API key is valid.
         *
         * @since   3.0.0
         * @returns {void}
         */
        check: function() {
            this.nominatim( function() {
                verifyKeys.osm.openRouteService();
           });
        },

        /**
         * Make a request to the Nominatim API to check if it returns valid data.
         *
         * @since   3.0.0
         * @param   {Function} callback Function to call after the check completes
         * @returns {void}
         */
        nominatim: function( callback ) {
            const args = {
                q: 'New York',
                format: 'jsonv2'
            };

            createApiRequest.osm.geocode( args, function( response, status ) {
                if ( response.length ) {
                    verifyKeys.createResponseMsg( wpslApiErrors.nominatim.noErrors, 'nominatim', 'updated' );
                } else {
                    const errorMessage = helpers.errors.getAjaxErrors( response );
                    const noticeArgs = {
                        msg: wpslApiErrors.nominatim.errorReturned,
                    };

                    if ( errorMessage.status > 0 && typeof errorMessage.message !== 'undefined' ) {
                        noticeArgs.details = '<p>' + sharedHelpers.escapeHtml( errorMessage.status ) + ': ' + sharedHelpers.escapeHtml( errorMessage.message ) + '</p>';
                    }

                    verifyKeys.createResponseMsg( noticeArgs, 'nominatim' );
                }

                callback();
            });
        },
        
        /**
         * Check the openrouteservice key when the directions are shown on the
         * map itself, and show an error when no key is provided.
         *
         * @since 3.0.0
         */
        openRouteService: function() {
            const openRouteKey = jQuery( '#wpsl-api-openrouteservice-key' ).val().trim();

            if ( ! jQuery( '#wpsl-direction-redirect' ).is( ':checked' ) ) {
                if ( openRouteKey ) {
                    const ajaxData = {
                        action: 'wpsl_validate_key',
                        nonce: wpslSecurity.validateKeyNonce,
                        wpsl_map_service: 'osm',
                        wpsl_api: {
                            openrouteservice: openRouteKey
                        }
                    };

                    preloader.add( jQuery( '#wpsl-verify-osm-keys' ) );

                    verifyKeys.requestStatus( ajaxData, 'openrouteservice' );
                } else {
                    verifyKeys.createResponseMsg( wpslApiErrors.openrouteservice.keyRequired, 'openrouteservice' );
                    verifyKeys.updateValidationStatus( 'openrouteservice', 0 );
                }
            }
        },

        /**
         * If a Stadia Maps API key is provided,
         * then we check if the API key is valid.
         *
         * @since 3.0.0
         */
        stadia: function() {
            const stadiaKey = jQuery( '#wpsl-api-stadia-key' ).val().trim();
            if ( stadiaKey ) {
                const ajaxData = {
                    action: 'wpsl_validate_key',
                    nonce: wpslSecurity.validateKeyNonce,
                    wpsl_map_service: 'osm',
                    wpsl_api: {
                        stadia: stadiaKey
                    }
                };

                preloader.add( jQuery( '#wpsl-verify-stadia-keys' ) );

                verifyKeys.requestStatus( ajaxData, 'stadia' );
            }
        }
    },

    stadia: {
        /**
         * Stadia uses its own geocoding and routing APIs,
         * so we only need to validate the Stadia API key.
         *
         * @since   3.0.0
         * @returns {void}
         */
        check: function() {
            this.stadiaKey();
        },

        /**
         * Validate the Stadia Maps API key.
         *
         * @since 3.0.0
         */
        stadiaKey: function() {
            const stadiaKey = jQuery( '#wpsl-api-stadia-key' ).val().trim();
            if ( stadiaKey ) {
                const ajaxData = {
                    action: 'wpsl_validate_key',
                    nonce: wpslSecurity.validateKeyNonce,
                    wpsl_map_service: 'stadia',
                    wpsl_api: {
                        stadia: stadiaKey
                    }
                };

                preloader.add( jQuery( '#wpsl-verify-stadia-keys' ) );

                verifyKeys.requestStatus( ajaxData, 'stadia' );
            } else {
                verifyKeys.createResponseMsg( wpslApiErrors.stadia.keyRequired, 'stadia' );
                verifyKeys.updateValidationStatus( 'stadia', 0 );
            }
        }
    },

    mapbox: {
        /**
         * Make a request to the Mapbox API to check for any issues.
         *
         * @since   3.0.0
         * @returns {void}
         */
        check: function() {
            const requestArgs = {
                searchText: 'New York',
                accessToken: helpers.map.mapbox.getAccessToken()
            };

            const selectedGeocoder = jQuery( '#wpsl-api-mapbox-geocoder' ).val();

            createApiRequest.mapbox.geocode( wp.hooks.applyFilters( 'wpslGeocodeParam', requestArgs ), function( response, status ) {
                if ( ! jQuery.isEmptyObject( response.features ) ) {
                    verifyKeys.createResponseMsg( wpslL10n.mapboxKeySuccess, 'mapbox', 'updated' );
                } else {
                    const errorMessage = helpers.errors.getAjaxErrors( response );

                    let noticeFooter;

                    if ( errorMessage.message.match( /Permanent geocodes/i ) ) {
                        noticeFooter = wpslApiErrors.mapbox.errorGeocodesNoticeFooter;
                    } else {
                        noticeFooter = wpslApiErrors.mapbox.errorNoticeFooter;
                    }

                    // A 403 / "Forbidden" usually means the token is URL-restricted
                    // to a different domain, so add an actionable hint for it.
                    const isForbidden = errorMessage.status === 403 || /forbidden/i.test( errorMessage.message );
                    const hint = isForbidden ? wpslApiErrors.mapbox.forbiddenHint : '';

                    // Code/reason in their own (non-bold) paragraph, matching the
                    // Google Maps server key notice and the server-rendered notice.
                    const details = '<p><strong>' + wpslApiErrors.mapbox.errorCodeLabel + '</strong> ' + sharedHelpers.escapeHtml( errorMessage.status ) +
                        '<br><strong>' + wpslApiErrors.mapbox.reasonLabel + '</strong> ' + sharedHelpers.escapeHtml( errorMessage.message ) + '</p>';

                    const noticeArgs = {
                        msg: wpslApiErrors.mapbox.errorReturned,
                        details: details + hint + noticeFooter
                    };

                    verifyKeys.createResponseMsg( noticeArgs, 'mapbox', 'error' );
                }

                verifyKeys.updateValidationStatus( 'mapbox', ! jQuery.isEmptyObject( response.features ) ? 1 : 0 );

                // Nominatim as the server geocoder needs its own check.
                if ( selectedGeocoder === 'nominatim' ) {
                    preloader.add( jQuery( '#wpsl-verify-mapbox-keys' ) );

                    verifyKeys.osm.nominatim( function() {
                        preloader.remove();
                    });
                }
            });
        }
    }
};