import { importModule } from '../../../../common/wpsl-core.js';
import { helpers } from '../wpsl-helpers.js';
import { state } from '../wpsl-shared.js';
import { mapBootstrap, mapObjects } from '../wpsl-map-bootstrap.js';

/**
 * Handle the map styles.
 *
 * @since 3.0.0
 */
export const mapStyles = {
    /**
     * Whether the map styles have been initialized.
     *
     * @since 3.0.0
     * @type  {boolean}
     */
    initialized: false,

    /**
     * Initialize the map styles.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        if ( this.initialized ) {
            return;
        }

        this.initialized = true;
        this.initializeMapboxAriaState();
        this.bindListener();
    },

    /**
     * Initialize aria-checked state for Mapbox style options on page load.
     *
     * @since   3.0.0
     * @returns {void}
     */
    initializeMapboxAriaState: function() {
        // Initialize Mapbox style options
        const $checkedMapbox = jQuery( '.wpsl-mapbox-style-options input[type="radio"]:checked' );
        if ( $checkedMapbox.length ) {
            jQuery( '.wpsl-mapbox-style-options label[role="radio"]' ).attr( 'aria-checked', 'false' );

            const mapboxId = $checkedMapbox.attr( 'id' );
            jQuery( 'label[for="' + mapboxId + '"][role="radio"]' ).attr( 'aria-checked', 'true' );

            jQuery( '.wpsl-mapbox-style-options img' ).removeClass( 'wpsl-selected-mapbox-style' );
            $checkedMapbox.closest( 'li' ).find( 'img' ).addClass( 'wpsl-selected-mapbox-style' );

            const styleUrl = $checkedMapbox.data( 'url' );
            const service  = mapStyles[ state.mapService ];
            if ( styleUrl && service && typeof service.setStyle === 'function' ) {
                service.setStyle( styleUrl );
            }
        }

        // Initialize Stadia style options
        const $checkedStadia = jQuery( '.wpsl-stadia-style-options input[type="radio"]:checked' );
        if ( $checkedStadia.length ) {
            jQuery( '.wpsl-stadia-style-options label[role="radio"]' ).attr( 'aria-checked', 'false' );

            const stadiaId = $checkedStadia.attr( 'id' );
            jQuery( 'label[for="' + stadiaId + '"]' ).attr( 'aria-checked', 'true' );

            jQuery( '.wpsl-stadia-style-options img' ).removeClass( 'wpsl-selected-stadia-style' );
            $checkedStadia.closest( 'li' ).find( 'img' ).addClass( 'wpsl-selected-stadia-style' );

            const stadiaValue = $checkedStadia.val();
            if ( stadiaValue && typeof mapStyles.stadia.setStyle === 'function' ) {
                mapStyles.stadia.setStyle( stadiaValue );
            }
        }
    },

    /**
     * Attach map style related event handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindListener: function() {
        // Handle clicks on the map style preview button.
        jQuery( '.wpsl-style-action' ).on( 'click', function( e ) {
            const $clickedButton = jQuery( e.currentTarget );
            const action = $clickedButton.data( 'action' );

            jQuery( '.wpsl-error-notice' ).remove();
            jQuery( '#wpsl-map-style-gmaps' ).removeClass( 'wpsl-error' );

            if ( action === 'apply' ) {
                this.apply();
            } else {
                this.remove();
            }

            return false;
        }.bind( this ) );

        // Handle changes to the selected Mapbox styles
        jQuery( '#wpsl-mapbox-styles input[type=radio]' ).on( 'change keyup', function( event ) {
            if ( event.type === 'keyup' && event.key !== 'Enter' ) {
                return;
            }
                        
            const $this = jQuery( event.currentTarget );
            const selectedValue = $this.val();

            // OpenFreeMap radios live inside #wpsl-mapbox-styles too, but the
            // appearance editor's preset preview owns them. Handling them here
            // would overwrite the panel with the mapboxNoKey notice when an
            // OpenFreeMap style is picked without a Mapbox key.
            if ( $this.closest( '#wpsl-osm-tile-source-openfreemap' ).length ) {
                return;
            }

            // Update aria-checked on the image-based style grid labels.
            jQuery( '.wpsl-mapbox-style-options label[role="radio"]' )
                .attr( 'aria-checked', 'false' );

            jQuery( 'label[for="' + $this.attr( 'id' ) + '"][role="radio"]' )
                .attr( 'aria-checked', 'true' );

            // Update aria-checked for the custom-URL toggle slider.
            jQuery( '#wpsl-mapbox-styles .wpsl-toggler-slider' ).attr( 'aria-checked', 'false' );
            $this.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', 'true' );
            
            const isStadiaStyle = $this.closest( '.wpsl-stadia-style-options' ).length > 0;

            if ( isStadiaStyle && ( state.mapService === 'osm' || state.mapService === 'stadia' ) ) {
                jQuery( '.wpsl-stadia-style-options label[role="radio"]' ).attr( 'aria-checked', 'false' );
                jQuery( 'label[for="' + $this.attr( 'id' ) + '"]' ).attr( 'aria-checked', 'true' );

                jQuery( '.wpsl-stadia-style-options img' ).removeClass( 'wpsl-selected-stadia-style' );
                $this.closest( 'li' ).find( 'img' ).addClass( 'wpsl-selected-stadia-style' );

                this.stadia.setStyle( selectedValue );
                return;
            }

            // Only proceed if we're using Mapbox or OSM with Mapbox tiles
            if ( state.mapService === 'mapbox' || state.mapService === 'osm' ) {
                if ( ! helpers.map.mapbox.getAccessToken() ) {
                    jQuery( '#wpsl-mapbox-styles' ).html( '<p>' + wpslL10n.mapboxNoKey + '</p>' );
                    return;
                }

                if ( selectedValue === 'custom' ) {
                    const styleUrl = jQuery( '#wpsl-style-url' ).val();
                    const $span = $this.closest( 'p' ).find( '.wpsl-info' );
                    
                    if ( ! this.mapbox.validStyleUrl( styleUrl ) ) {
                        jQuery( '#wpsl-mapbox-styles input[type=radio]' ).prop( 'checked', false );
                        
                        helpers.errors.applyMapErrorStyle( jQuery( '#wpsl-style-url' ), $span, 'mapboxPrefixError' );
                        
                        jQuery( '.wpsl-mapbox-style-options img' ).removeClass( 'wpsl-selected-mapbox-style' );
                        
                        mapStyles[ state.mapService ].setStyle( 'mapbox://styles/mapbox/standard' );
                        
                        return false;
                    }
                }
                
                // Show a darker border around the selected map style image.
                jQuery( '.wpsl-mapbox-style-options img' ).removeClass( 'wpsl-selected-mapbox-style' );
                $this.parents( 'li' ).find( 'img' ).addClass( 'wpsl-selected-mapbox-style' );

                if ( selectedValue === 'custom' ) {
                    const styleUrl = jQuery( '#wpsl-style-url' ).val();
                    
                    // Clear the error state left by an earlier invalid URL.
                    if ( jQuery( '#wpsl-style-url' ).hasClass( 'wpsl-error' ) ) {
                        const $customMapstyle = jQuery( '#wpsl-mapbox-styles p:first-child' );

                        // Reset the info text to the default help text
                        $customMapstyle.find( '.wpsl-warning .wpsl-info-text, .wpsl-info .wpsl-info-text' ).html( wpslL10n.mapboxCustomStyleHelp );
                        $customMapstyle.find( 'span' ).removeClass( 'wpsl-warning' );
                        $customMapstyle.find( 'input' ).removeClass( 'wpsl-error' );
                        $customMapstyle.find( '.wpsl-hide' ).addClass( 'wpsl-info-text' );
                    }
                    
                    if ( helpers.map.mapbox.getAccessToken() ) {
                        this[ state.mapService ].setStyle( styleUrl );
                    } else {
                        jQuery( '#wpsl-mapbox-styles' ).html( '<p>' + wpslL10n.mapboxNoKey + '</p>' );
                    }
                } else {
                    const styleUrl = this.mapbox.getActiveStyle( $this );
                    if ( styleUrl ) {
                        this[ state.mapService ].setStyle( styleUrl );
                    } else {
                        jQuery( '#wpsl-mapbox-styles' ).html( '<p>' + wpslL10n.mapboxNoKey + '</p>' );
                    }
                    
                    if ( jQuery( '#wpsl-style-url' ).hasClass( 'wpsl-error' ) ) {
                        const $customMapstyle = jQuery( '#wpsl-mapbox-styles p:first-child' );

                        // Reset the info text to the default help text
                        $customMapstyle.find( '.wpsl-warning .wpsl-info-text, .wpsl-info .wpsl-info-text' ).html( wpslL10n.mapboxCustomStyleHelp );
                        $customMapstyle.find( 'span' ).removeClass( 'wpsl-warning' );
                        $customMapstyle.find( 'input' ).removeClass( 'wpsl-error' );
                        $customMapstyle.find( '.wpsl-hide' ).addClass( 'wpsl-info-text' );
                    }
                }
            }
        }.bind( this ) );

        // Toggle visibility based on dropdown selection
        const toggleStyleSections = function() {
            if ( state.mapService !== 'gmaps' ) {
                return;
            }

            const $cloudIdSection = jQuery( '#wpsl-gmaps-style-cloud_based' );
            const $jsonSection = jQuery( '#wpsl-gmaps-style-json-group' );
            const $actionButtonsWrapper = jQuery( '.wpsl-buttons-row' );

            const selectedStyle = jQuery( '#wpsl-gmaps-styles' ).val();
            if ( selectedStyle === 'cloud_based' ) {
                $cloudIdSection.css( 'display', '' );
                $jsonSection.css( 'cssText', 'display: none !important;' );
                $actionButtonsWrapper.css( 'display', '' );
            } else if ( selectedStyle === 'json' ) {
                $cloudIdSection.css( 'cssText', 'display: none !important;' );
                $jsonSection.css( 'display', '' );
                $actionButtonsWrapper.css( 'display', '' );
            }
        };

        jQuery( '#wpsl-gmaps-styles' ).on( 'change', toggleStyleSections );

        toggleStyleSections();

        // Update action buttons when cloud ID input changes
        jQuery( '#wpsl-cloud-based-id' ).on( 'input', function() {
            const selectedStyle = jQuery( '#wpsl-gmaps-styles' ).val();
            if ( selectedStyle === 'cloud_based' ) {
                toggleStyleSections();
            }
        } );

        // Update action buttons when JSON textarea changes
        jQuery( '#wpsl-map-style-gmaps' ).on( 'input', function() {
            const selectedStyle = jQuery( '#wpsl-gmaps-styles' ).val();
            if ( selectedStyle === 'json' ) {
                toggleStyleSections();
            }
        } );

        // Validate Mapbox style URL when the field loses focus
        jQuery( '#wpsl-style-url' ).on( 'blur', function() {
            const styleUrl = jQuery( '#wpsl-style-url' ).val().trim();
            const $infoText = jQuery( 'label[for="wpsl-style-url"] .wpsl-info-text' );
            const $infoSpan = jQuery( 'label[for="wpsl-style-url"] .wpsl-info' );

            // Remove any existing error notices
            jQuery( '.wpsl-style-url-error' ).remove();
            jQuery( '#wpsl-style-url' ).removeClass( 'wpsl-error' );

            $infoSpan.removeClass( 'wpsl-warning' );

            if ( styleUrl && ! this.mapbox.validStyleUrl( styleUrl ) ) {
                jQuery( '#wpsl-style-url' ).addClass( 'wpsl-error' );

                // Show the info text and add error message inside it
                $infoText.removeClass( 'wpsl-hide' );
                $infoSpan.addClass( 'wpsl-warning' );
            }

            if ( ! styleUrl ) {
                jQuery( '.wpsl-mapbox-style-options li:first-child img' ).trigger( 'click' );
            }
        }.bind( this ) );
    },

    /**
     * Remove the applied map style.
     *
     * Only the selected style type's field is cleared, so the other one's
     * data survives.
     *
     * @since   3.0.0
     * @returns {void}
     */
    remove: function() {
        if ( state.mapService === 'gmaps' ) {
            const $dropdown = jQuery( '#wpsl-gmaps-styles' );
            const selectedStyle = $dropdown.length > 0 ? $dropdown.val() : 'cloud_based';

            if ( selectedStyle === 'cloud_based' ) {
                jQuery( '#wpsl-cloud-based-id' ).val( '' );
            } else if ( selectedStyle === 'json' ) {
                jQuery( '#wpsl-map-style-gmaps' ).val( '' );
            }

            mapStyles.gmaps.rebuildMap( {} );
        } else {
            mapStyles[ state.mapService ].updateStyles( null );
        }
    },

    /**
     * Apply the map style code.
     *
     * @since   3.0.0
     * @returns {void}
     */
    apply: function() {
        if ( state.mapService === 'mapbox' ) {
            mapStyles[ state.mapService ].updateStyles();
        } else {
            const $dropdown = jQuery( '#wpsl-' + state.mapService + '-styles' );
            const selectedStyle = $dropdown.length > 0 ? $dropdown.val() : 'cloud_based';
            if ( selectedStyle === 'cloud_based' && state.mapService === 'gmaps' ) {
                const $mapIdInput = jQuery( '#wpsl-cloud-based-id' );
                const mapId = ( $mapIdInput.val() || '' ).trim();

                // Strip the whitespace from the field itself as well, the
                // rebuilt map reads the Map ID straight from the input.
                $mapIdInput.val( mapId );

                if ( mapId ) {
                    mapStyles[ state.mapService ].updateStyles( mapId );
                }

                $mapIdInput.toggleClass( 'wpsl-error', ! mapId );
            } else if ( selectedStyle === 'json' && state.mapService !== 'mapbox' ) {
                const mapStyle = jQuery( '#wpsl-map-style-' + state.mapService ).val();
                if ( mapStyle ) {
                    const validStyle = helpers.formatting.tryParseJSON( mapStyle );
                    if ( ! validStyle ) {
                        jQuery( '#wpsl-map-style-gmaps' ).addClass( 'wpsl-error' ).before( '<p class="wpsl-error-notice">' + wpslL10n.styleError + '</p>' );
                    } else {
                        mapStyles[ state.mapService ].updateStyles( validStyle );
                    }
                } else {
                    mapStyles.remove();
                }

                if ( ! jQuery( '#wpsl-gmaps-style-json .wpsl-error-notice' ).length ) {
                    jQuery( '#wpsl-map-style-gmaps' ).toggleClass( 'wpsl-error', ! mapStyle );
                }
            }
        }
    },

    /**
     * Shared spinner overlay for the preview map, used by every provider.
     *
     * The overlay is only rendered once a style swap has been running for
     * `delay` ms ( default 2000 ), so quick swaps don't flash a spinner. A hard
     * safety timeout guarantees it can never get stuck if a provider's "done"
     * signal never arrives.
     *
     * @since 3.0.0
     */
    mapPreloader: {
        _showTimer: null,
        _safetyTimer: null,

        /**
         * Arm the delayed spinner for the current swap. Resetting the previous
         * swap's timers first means rapid switching only shows a spinner if the
         * latest swap is the one that runs long.
         *
         * @since   3.0.0
         * @param   {number} [delay] ms to wait before showing ( default 2000 ).
         * @returns {void}
         */
        start: function( delay ) {
            const wait = typeof delay === 'number' ? delay : 2000;

            this.stop();

            this._showTimer = setTimeout( function() {
                mapStyles.mapPreloader._render();
            }, wait );

            // Never let the spinner outlive a reasonable swap.
            this._safetyTimer = setTimeout( function() {
                mapStyles.mapPreloader.stop();
            }, 15000 );
        },

        /**
         * Cancel a pending spinner and remove any visible overlay.
         *
         * @since   3.0.0
         * @returns {void}
         */
        stop: function() {
            if ( this._showTimer ) { clearTimeout( this._showTimer ); this._showTimer = null; }
            if ( this._safetyTimer ) { clearTimeout( this._safetyTimer ); this._safetyTimer = null; }

            const container = this._container();
            if ( container ) {
                jQuery( container ).find( '.wpsl-map-preloader' ).remove();
            }
        },

        /**
         * Render the overlay into the current preview map container.
         *
         * @since   3.0.0
         * @returns {void}
         */
        _render: function() {
            const container = this._container();
            if ( ! container ) { return; }

            const $container = jQuery( container );
            if ( ! $container.find( '.wpsl-map-preloader' ).length ) {
                $container.append( '<div class="wpsl-map-preloader"><img src="' + wpslSettings.url + 'assets/img/ajax-loader.svg" width="28" height="28" alt="" /></div>' );
            }
        },

        /**
         * Resolve the DOM container of the active preview map across libraries
         * ( Leaflet / Mapbox GL: getContainer(); Google Maps: getDiv() ). Falls
         * back to the provider's map wrap while a map is being rebuilt and is
         * momentarily absent from mapObjects ( e.g. Google Maps ).
         *
         * @since   3.0.0
         * @returns {HTMLElement|null}
         */
        _container: function() {
            const map = mapObjects.get();
            if ( map ) {
                if ( typeof map.getContainer === 'function' ) { return map.getContainer(); }
                if ( typeof map.getDiv === 'function' ) { return map.getDiv(); }
            }

            return document.getElementById( 'wpsl-' + state.mapService + '-wrap' ) || null;
        }
    },

    mapbox: {
        /**
         * Check if the passed url starts with mapbox://styles/
         *
         * @since  3.0.0
         * @param  {string} url Mapbox style URL ( mapbox://styles/..... )
         * @return {bool}
         */
        validStyleUrl: function( url ) {
            return url.indexOf( 'mapbox://styles/' ) !== -1;
        },

        /**
         * Get the Mapbox style URL from the passed element.
         *
         * Classic styles ( radio boxes ) carry it in a data attribute, a
         * custom https://studio.mapbox.com/ style comes from the input field.
         *
         * @since   3.0.0
         * @param   {string} elem     The selected element
         * @returns {string} styleUrl The URL to the Mapbox style
         */
        getActiveStyle: function( elem ) {
            let styleUrl = '';

            const selectedStyle = elem.val();
            if ( selectedStyle !== 'custom' ) {
                styleUrl = elem.data( 'url' );
            } else {
                styleUrl = jQuery( '#wpsl-style-url' ).val();
            }

            if ( ! this.validStyleUrl( styleUrl ) ) {
                styleUrl = false;
            }

            return styleUrl;
        },

        /**
         * Set a Mapbox style based on the passed style URL.
         *
         * @since   3.0.0
         * @param   {string} styleUrl
         * @returns {void}
         */
        setStyle: function( styleUrl ) {
            const mapObj = mapObjects.get();
            if ( ! mapObj ) {
                return;
            }

            mapStyles.mapPreloader.start();

            // Mapbox GL fires 'idle' once the new style has finished rendering.
            if ( typeof mapObj.once === 'function' ) {
                mapObj.once( 'idle', function() {
                    mapStyles.mapPreloader.stop();
                } );
            }

            mapObj.setStyle( styleUrl );
        },

        /**
         * Apply a new style to the Mapbox map
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateStyles: function() {
            const styleUrl = mapStyles.mapbox.getActiveStyle( jQuery( '#wpsl-mapbox-styles input[type="radio"]:checked' ) );
            if ( styleUrl ) {
                mapStyles.mapbox.setStyle( styleUrl );
            }
        },
    },

    gmaps: {
        /**
         * Destroy the existing map and create a new one.
         *
         * For now required to swap mapID styles until this is fixed
         * https://issuetracker.google.com/issues/161501609?pli=1 
         *
         * @since   3.0.0
         * @param   {object} options The map options used to recreate the map
         * @returns {void}
         */
        rebuildMap: function( options ) {
            const elemId = 'wpsl-' + state.mapService + '-wrap';

            // A full teardown + reload can run long enough to warrant the spinner.
            mapStyles.mapPreloader.start();

            // Remove old map instance
            state.activeMapIds.length = 0;

            const existingMapIndex = mapObjects.active.findIndex( obj => obj.id === elemId );
            if ( existingMapIndex !== -1 ) {
                mapObjects.active.splice( existingMapIndex, 1 );
            }

            // Import and rebuild map
            importModule( 'mapBootstrap', {
                path: `${wpslSettings.url}assets/src/admin/js/modules/wpsl-map-bootstrap.js`
            }).then( ( mapBootstrapModule ) => {
                if ( mapBootstrapModule && mapBootstrapModule.gmaps ) {
                    mapBootstrapModule.gmaps( { elemId: elemId } );
                }

                // Hide the spinner once the freshly built map has drawn its tiles.
                const newMap = mapObjects.get();
                if ( newMap && typeof google !== 'undefined' && google.maps && google.maps.event ) {
                    google.maps.event.addListenerOnce( newMap, 'tilesloaded', function() {
                        mapStyles.mapPreloader.stop();
                    } );
                } else {
                    mapStyles.mapPreloader.stop();
                }
            }).catch( ( error ) => {
                mapStyles.mapPreloader.stop();
                console.error( '[mapStyles.gmaps.rebuildMap] Error importing module:', error );
            });
        },

        /**
         * Apply the available map style to the Google Maps map.
         *
         * @since   3.0.0
         * @param   {mixed} style The map style ( JSON code or the cloud based mapId )
         * @returns {void}
         */
        updateStyles: function( style ) {
            const $dropdown = jQuery( '#wpsl-gmaps-styles' );

            const styleSource = $dropdown.length > 0 ? $dropdown.val() : 'cloud_based';

            let args = { styles: style };

            if ( styleSource === 'cloud_based' ) {
                args = { mapId: style };
            } else if ( styleSource === 'json' ) {
                args = { styles: style };
            }

            // A mapId change, or a switch between JSON ( old ) and cloud based
            // ( new ) styles, only takes effect after a rebuild.
            mapStyles.gmaps.rebuildMap( args );
        },
    },

    stadia: {
        /**
         * Apply a Stadia Maps style to an OSM (Leaflet) map.
         *
         * @since   3.0.0
         * @param   {string} styleKey The Stadia style key (e.g., 'alidade_smooth')
         * @returns {void}
         */
        setStyle: function( styleKey ) {
            const map = mapObjects.get();
            if ( ! map ) {
                return;
            }

            const stadiaKey = typeof wpslSettings !== 'undefined' && wpslSettings.stadiaKey ? wpslSettings.stadiaKey : '';
            if ( ! stadiaKey ) {
                console.warn( '[WPSL] No Stadia API key available for map preview' );
                return;
            }

            if ( mapBootstrap.tileLayer ) {
                map.removeLayer( mapBootstrap.tileLayer );
            }

            const urlTemplate = 'https://tiles.stadiamaps.com/tiles/{style}/{z}/{x}/{y}{r}.png?api_key={api_key}';

            const tileLayerOptions = {
                attribution: '&copy; <a href="https://stadiamaps.com/" target="_blank">Stadia Maps</a> &copy; <a href="https://openmaptiles.org/" target="_blank">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors',
                style: styleKey,
                api_key: stadiaKey,
                maxZoom: styleKey === 'stamen_watercolor' ? 16 : 20
            };

            mapStyles.mapPreloader.start();

            mapBootstrap.tileLayer = L.tileLayer( urlTemplate, tileLayerOptions );
            mapBootstrap.tileLayer.once( 'load', function() {
                mapStyles.mapPreloader.stop();
            } );
            mapBootstrap.tileLayer.addTo( map );
        }
    },

    osm: {
        /**
         * Restore the default OpenStreetMap tile layer.
         *
         * @since   3.0.0
         * @returns {void}
         */
        setDefault: function() {
            const map = mapObjects.get();
            if ( ! map ) {
                return;
            }

            if ( mapBootstrap.tileLayer ) {
                map.removeLayer( mapBootstrap.tileLayer );
            }

            mapStyles.mapPreloader.start();

            mapBootstrap.tileLayer = L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            } );
            mapBootstrap.tileLayer.once( 'load', function() {
                mapStyles.mapPreloader.stop();
            } );
            mapBootstrap.tileLayer.addTo( map );
        },

        /**
         * Apply a Mapbox based style to an OSM map .
         *
         * @since   3.0.0
         * @param   {string} styleUrl
         * @returns {void}
         */
        setStyle: function( styleUrl ) {
            const map = mapObjects.get();
            const accessToken = helpers.map.mapbox.getAccessToken();

            if ( ! accessToken ) {
                return;
            }

            map.removeLayer( mapBootstrap.tileLayer );

            const isStandardStyle = styleUrl === 'mapbox://styles/mapbox/standard';
            let urlTemplate;
            
            if ( isStandardStyle ) {
                urlTemplate = 'https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/{z}/{x}/{y}?access_token={accessToken}';
            } else {
                urlTemplate = 'https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}';
            }

            const tileLayerOptions = {
                attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> <strong><a href="https://www.mapbox.com/map-feedback/" target="_blank">Improve this map</a></strong>',
                tileSize: 512,
                maxZoom: 18,
                zoomOffset: -1,
                accessToken: accessToken
            };

            if ( ! isStandardStyle ) {
                tileLayerOptions.id = mapStyles.osm.getStyleId( styleUrl );
            }

            mapStyles.mapPreloader.start();

            mapBootstrap.tileLayer = L.tileLayer( urlTemplate, tileLayerOptions );
            mapBootstrap.tileLayer.once( 'load', function() {
                mapStyles.mapPreloader.stop();
            } );

            mapBootstrap.tileLayer.addTo( map );
        },
        
        /**
         * Remove the 'mapbox://styles/' part from the Mapbox style.
         *
         * @since   3.0.0
         * @param   {string} styleUrl
         * @returns {string}  The last part of the Mapbox style URL
         */
        getStyleId: function( styleUrl ) {
            return styleUrl.split( 'mapbox://styles/' )[1];
        },

        /**
         * Apply the available map style to the map
         *
         * @param {object} style The map style
         */
        updateStyles: function( style ) {
            L.geoJSON( style, {
                style: style
            }).addTo( mapObjects.get() );
        },
    },

    openfreemap: {
        /**
         * Token bumped by every setStyle() call, so that switching styles faster
         * than the vector tiles load still leaves only the most recent selection
         * as the visible base layer.
         *
         * @since 3.0.0
         * @type  {number}
         */
        _seq: 0,

        /**
         * The MapLibre layer that is currently loading but has not yet been
         * promoted to the base layer. Tracked so a superseding request can
         * remove it instead of stacking orphaned canvases on the map.
         *
         * @since 3.0.0
         * @type  {object|null}
         */
        _pendingLayer: null,

        /**
         * Swap the preview base layer to a MapLibre vector style and report
         * whether it loaded. Reverts to the previous layer on failure so the
         * preview never sits blank.
         *
         * Vector styles load asynchronously, so rapid switches used to race: each
         * call kept its own "previous layer" and whichever finished loading last
         * won, leaving the wrong ( earlier ) selection on screen. With the
         * per-call token a stale load discards its own layer instead.
         *
         * @since   3.0.0
         * @param   {string} styleUrl MapLibre style JSON URL.
         * @param   {Function} [onResult] Optional callback( true|false ).
         * @returns {void}
         */
        setStyle: function( styleUrl, onResult ) {
            const self = mapStyles.openfreemap;
            const map  = mapObjects.get();

            if ( ! map || typeof L.maplibreGL === 'undefined' || ! styleUrl ) {
                if ( typeof onResult === 'function' ) { onResult( false ); }
                return;
            }

            const token = ++self._seq;

            // Drop any earlier in-flight layer that never finished loading so we
            // don't leave orphaned MapLibre canvases stacked on the map.
            if ( self._pendingLayer ) {
                map.removeLayer( self._pendingLayer );
                self._pendingLayer = null;
            }

            mapStyles.mapPreloader.start();

            const previousLayer = mapBootstrap.tileLayer;

            const glLayer = L.maplibreGL( {
                style: styleUrl,
                attribution: '<a href="https://openfreemap.org">OpenFreeMap</a> &copy; <a href="https://www.openmaptiles.org/">OpenMapTiles</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            } );

            self._pendingLayer = glLayer;

            let settled = false;

            // A newer setStyle() call owns the base layer and the preloader now,
            // so drop this layer without touching either.
            const discardStale = function() {
                settled = true;
                map.removeLayer( glLayer );
            };

            const succeed = function() {
                if ( settled ) { 
                    return; 
                }

                if ( token !== self._seq ) { 
                    discardStale(); 
                    return; 
                }

                settled = true;
                self._pendingLayer = null;

                if ( previousLayer ) { 
                    map.removeLayer( previousLayer );
                }
                
                mapBootstrap.tileLayer = glLayer;
                mapStyles.mapPreloader.stop();
                
                if ( typeof onResult === 'function' ) { 
                    onResult( true ); 
                }
            };

            const fail = function() {
                if ( settled ) { 
                    return; 
                }

                if ( token !== self._seq ) { 
                    discardStale(); 
                    return; 
                }

                settled = true;
                self._pendingLayer = null;
                map.removeLayer( glLayer );

                mapStyles.mapPreloader.stop();

                if ( typeof onResult === 'function' ) { 
                    onResult( false ); 
                }
            };

            glLayer.addTo( map );

            const glMap = glLayer.getMaplibreMap ? glLayer.getMaplibreMap() : null;
            if ( glMap ) {
                glMap.once( 'load', succeed );
                glMap.on( 'error', function() { 
                    if ( ! settled ) { 
                        fail();
                    } 
                } );
                
                // Timeout guard for hung/unreachable styles.
                setTimeout( function() { 
                    if ( ! settled ) { 
                        fail(); 
                    } 
                }, 10000 );
            } else {
                succeed();
            }
        }
    }
};