import { slData, config } from '../../../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { api } from './wpsl-api.js';
import { geojson } from './wpsl-geojson.js';

/**
 * Mapbox info window handling.
 *
 * @since 3.0.0
 */
export const infoWindow = {
    openWindow: '',

    /**
     * Where the popup's tip goes, for the marker on the given layer.
     *
     * Only a custom marker's own SVG can say where its edges are; the bundled
     * markers are uniform pins hanging from their tip, at the size written down
     * in the settings. Both reach sharedHelpers.mapboxPopupOffset() as the same
     * kind of box, and so does the admin preview map with its own.
     *
     * @since  3.0.0
     * @param  {string} markerIcon The marker's layer id, which is also its
     *                             slData.layerDetails key
     * @return {object} An offset per anchor, for mapboxgl.Popup
     */
    offset: function( markerIcon ) {
        const custom = helpers.markers.getCustomMarkerGeometry( slData.layerDetails?.[ markerIcon ] );

        const geometry = custom || {
            width:  config.markers.scaledSize[0],
            height: config.markers.scaledSize[1],
            anchor: [ config.markers.scaledSize[0] / 2, config.markers.scaledSize[1] ]
        };

        return sharedHelpers.mapboxPopupOffset( geometry );
    },

    /**
     * Create a new popup with the correct template.
     *
     * @since  3.0.0
     * @param  {string} template The content of the popup
     * @param  {object} latLng Marker coordinates where the popup will appear
     * @param  {object} map The map object
     * @param  {string} markerIcon The layer id of the marker the popup belongs
     *                             to, which is also its slData.layerDetails key
     * @return {void}
     */
    create: function( template, latLng, map, markerIcon ) {
        const popupArgs = wp.hooks.applyFilters( 'wpslMapboxPopupArgs', {
            closeButton: true,
            closeOnClick: true,
            closeOnMove: false,
            maxWidth: "none",
            offset: this.offset( markerIcon )
        });

        this.openWindow = new mapboxgl.Popup( popupArgs )
            .setLngLat( latLng )
            .setHTML( template )
            .addTo( map );

        // Track the popup on the map itself, so with multiple [wpsl_map]
        // shortcodes a marker click only closes the clicked map's popup.
        map._wpslPopup = this.openWindow;

        // The keyboard handlers below need this popup: this.openWindow points
        // at whichever was opened last, possibly on another map by then.
        const openWindow = this.openWindow;

        setTimeout( () => {
            // Scope the lookup to this map, another map can have its own popup open.
            const popup = map.getContainer().querySelector( '.mapboxgl-popup' ),
                  closeButton = map.getContainer().querySelector( '.mapboxgl-popup-close-button' );

            if ( ! popup || ! closeButton ) {
                return;
            }

            // Re-snap the popup on every moveend/zoomend so panning or zooming
            // while it's open doesn't leave a blurry compositor raster behind.
            const popupRepaintTeardown = helpers.popup.hardRepaintOnSettle( map, function() {
                return map.getContainer().querySelector( '.mapboxgl-popup' );
            });

            // Mapbox GL has no built-in popup auto-pan. Like Google Maps and
            // Leaflet, pan by the minimum needed to bring an overflowing popup
            // edge into view; a popup that fits leaves the map put.
            const popupRect = popup.getBoundingClientRect();
            const mapRect   = map.getContainer().getBoundingClientRect();
            const pad       = 20;

            if ( popupRect.height > 0 && popupRect.width > 0 ) {
                let offsetX = 0;
                let offsetY = 0;

                const overTop    = ( mapRect.top + pad ) - popupRect.top;
                const overBottom = popupRect.bottom - ( mapRect.bottom - pad );
                const overLeft   = ( mapRect.left + pad ) - popupRect.left;
                const overRight  = popupRect.right - ( mapRect.right - pad );

                if ( overTop > 0 ) {
                    offsetY = -overTop;
                } else if ( overBottom > 0 ) {
                    offsetY = overBottom;
                }

                if ( overLeft > 0 ) {
                    offsetX = -overLeft;
                } else if ( overRight > 0 ) {
                    offsetX = overRight;
                }

                if ( offsetX !== 0 || offsetY !== 0 ) {
                    map.panBy( [ offsetX, offsetY ] );
                }
            }

            if ( ! closeButton.hasAttribute( 'tabindex' ) ) {
                closeButton.setAttribute( 'tabindex', '0' );
            }
            
            const popupLinks = popup.querySelectorAll( '.mapboxgl-popup-content a' );
            popupLinks.forEach( function( link ) {
                if ( ! link.hasAttribute( 'tabindex' ) ) {
                    link.setAttribute( 'tabindex', '0' );
                }
            });
            
            const contentLinks = Array.from( popup.querySelectorAll( '.mapboxgl-popup-content a[href]' ) );
            const firstContentLink = contentLinks[0];
            if ( firstContentLink ) {
                firstContentLink.focus();
            } else {
                closeButton.focus();
            }

            map._wpslShouldReturnFocus = true;

            const handleFirstLinkKeydown = ( e ) => {
                if ( e.key === 'Escape' ) {
                    map._wpslShouldReturnFocus = true;
                    openWindow.remove();
                    return;
                }
                
                if ( e.key === 'Tab' && e.shiftKey ) {
                    e.preventDefault();
                    closeButton.focus();
                }
            };
            
            const handleEscapeKey = ( e ) => {
                if ( e.key === 'Escape' ) {
                    map._wpslShouldReturnFocus = true;
                    openWindow.remove();
                }
            };

            const handleCloseButtonKeydown = ( e ) => {
                if ( e.key === 'Escape' ) {
                    map._wpslShouldReturnFocus = true;
                    openWindow.remove();
                    return;
                }

                if ( e.key === 'Tab' && ! e.shiftKey ) {
                    if ( firstContentLink ) {
                        e.preventDefault();
                        firstContentLink.focus();
                    } else {
                        e.preventDefault();
                        map._wpslShouldReturnFocus = true;
                        openWindow.remove();
                    }
                }

                if ( e.key === 'Tab' && e.shiftKey && firstContentLink ) {
                    e.preventDefault();
                    contentLinks[ contentLinks.length - 1 ].focus();
                }
            };
            
            if ( firstContentLink ) {
                firstContentLink.addEventListener( 'keydown', handleFirstLinkKeydown );
            
                if ( ! firstContentLink._wpslKeydownHandlers ) {
                    firstContentLink._wpslKeydownHandlers = [];
                }

                firstContentLink._wpslKeydownHandlers.push( handleFirstLinkKeydown );
            }
            
            closeButton.addEventListener( 'keydown', handleCloseButtonKeydown );
            if ( ! closeButton._wpslKeydownHandlers ) {
                closeButton._wpslKeydownHandlers = [];
            }

            closeButton._wpslKeydownHandlers.push( handleCloseButtonKeydown );
            
            contentLinks.forEach( function( link, index ) {
                if ( index > 0 ) {
                    link.addEventListener( 'keydown', handleEscapeKey );
                    
                    if ( ! link._wpslKeydownHandlers ) {
                        link._wpslKeydownHandlers = [];
                    }
                    
                    link._wpslKeydownHandlers.push( handleEscapeKey );
                }
            });
            
            closeButton.addEventListener( 'click', () => {
                map._wpslShouldReturnFocus = true;
            });
            
            openWindow.on( 'close', () => {
                popupRepaintTeardown();

                const popupElements = popup.querySelectorAll( 'a[href], button' );
                popupElements.forEach( function( element ) {
                    if ( element._wpslKeydownHandlers ) {
                        element._wpslKeydownHandlers.forEach( function( handler ) {
                            element.removeEventListener( 'keydown', handler );
                        });

                        delete element._wpslKeydownHandlers;
                    }
                });
                
                if ( map._wpslShouldReturnFocus ) {
                    setTimeout( () => {
                        // If closing the start marker, focus the first store marker instead
                        // so Tab navigation stays within the map
                        if ( map._wpslLastFocusedMarkerId === 0 ) {
                            const mapContainer = map.getContainer();
                            const allOverlays = Array.from( mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay' ) );
                            const firstStoreMarker = allOverlays.find( overlay => overlay.getAttribute( 'data-store-id' ) !== '0' );

                            if ( firstStoreMarker ) {
                                firstStoreMarker.focus();
                            }
                            slData.markers.focusSourceElement = null;
                        } else {
                            // Restore focus to the element that opened the infowindow
                            // (either a marker overlay or a search result element)
                            const sourceEl = slData.markers.focusSourceElement;

                            if ( sourceEl && sourceEl.nodeType && document.body.contains( sourceEl ) ) {
                                sourceEl.focus();
                                slData.markers.focusSourceElement = null;
                            } else if ( map._wpslLastFocusedMarkerId ) {
                                // Fallback: focus the marker if source element is gone
                                const button = document.querySelector( `.wpsl-mapbox-marker-overlay[data-store-id="${map._wpslLastFocusedMarkerId}"]` );

                                if ( button ) {
                                    button.focus();
                                }
                                slData.markers.focusSourceElement = null;
                            }
                        }
                    }, 50 );
                }

                map._wpslShouldReturnFocus = true;
            });
        }, 0 );

        this.actions( latLng, map );
    },

    /**
     * Event handlers.
     *
     * @since  3.0.0
     * @param  {object} latLng Marker coordinates where the popup will appear
     * @param  {object} map The map object
     * @return {void}
     */
    actions: function( latLng, map ) {
        helpers.results.maybeShowZoomOption( map );

        jQuery( '.wpsl-info-actions a' ).on( 'click', function( e ) {
            const maxZoom = config.map.autoZoomLevel;

            e.stopImmediatePropagation();

            if ( jQuery( this ).hasClass( 'wpsl-directions' ) ) {
                map._wpslShouldReturnFocus = false;
                
                helpers.directions.scrollToTop();

                api.directions.show( jQuery( this ) );
            } else if ( jQuery( this ).hasClass( 'wpsl-zoom-here' ) ) {
                map.setCenter( latLng ).setZoom( maxZoom );
            }

            return false;
        });

        this.openWindow.on( 'close', function() {
            const mapGeojson = map._wpslGeojsonData || geojson;

            // Only restore icon and update data if it's not the start marker
            if ( map._wpslLastFocusedMarkerId !== 0 ) {
                geojson.icon.restore( mapGeojson );
                map.getSource( 'locations' ).setData( mapGeojson.active );
            }
        });
    },

    /**
     * Make sure all pop-ups are closed.
     *
     * @since  3.0.0
     * @return {void}
     */
    close: function() {
        if ( this.openWindow && this.openWindow.isOpen() ) {
            this.openWindow.remove();
            this.openWindow = '';
        }
    }
};
