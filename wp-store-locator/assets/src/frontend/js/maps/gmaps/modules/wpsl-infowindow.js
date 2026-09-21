import { slData, config } from '../../../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { api } from './wpsl-api.js';
import { markers } from './wpsl-markers.js';

/**
 * Google Maps InfoWindow module.
 * 
 * @since 3.0.0
 */
export const infoWindow = {
    current: '',
    active: [],
    openWindow: [],

    /**
     * How far the tail has to move to clear the marker it is anchored to.
     *
     * Anchored to a marker, Maps measures the artwork itself and lands the tail
     * one pixel INTO its box, at any marker height. So the lift is the gap plus
     * that pixel -- except against an AdvancedMarkerElement, which has no anchor
     * of its own: createMarkerContent() shifts its content DOWN to put the
     * artwork's real anchor on the coordinate, and Maps measures the element
     * where it would have been without that shift. The tail is already carrying
     * the shift as clearance, so it is given back here rather than added twice
     * -- which is 5px on a photo marker, the whole gap over again.
     *
     * Measured against both marker types, a bundled pin and a photo marker.
     *
     * @since  3.0.0
     * @param  {object} marker The marker the window is anchored to
     * @return {object} A google.maps.Size for the InfoWindow's pixelOffset
     */
    pixelOffset: function( marker ) {
        // A legacy marker hands Maps its anchor, so there is nothing to correct.
        const geometry = ( marker && undefined !== marker.content )
            ? helpers.markers.getCustomMarkerGeometry( marker._iconUrl )
            : null;

        const shift = geometry ? geometry.offset[1] : 0;

        /*
         * x is the artwork's centre relative to the anchor, for the same reason
         * Leaflet's popupAnchor carries it: the anchor is snapped to a whole
         * pixel, so on an odd width it sits half a pixel right of the middle of
         * the marker. A legacy marker hands Maps its anchor and Maps rounds it
         * itself, so there is nothing to give back there.
         */
        const centreX = geometry ? geometry.offset[0] : 0;

        return new google.maps.Size( centreX, shift - ( sharedHelpers.markerGap + 1 ) );
    },

    /**
     * Re-apply the pixelOffset for the artwork a marker is wearing now.
     *
     * setContent() works the offset out once, from the image the marker has
     * when its window opens, and nothing revisits it while the window stays
     * open. Swapping the image underneath leaves the window standing off an
     * image it no longer wears: hovering any other search result restores the
     * marker the window is open on back to its store image ( setActiveOnHover()
     * -> setActive() -> restoreActiveMarkers() ).
     *
     * Only Advanced Markers are affected, a legacy marker's offset does not
     * depend on its artwork. Worth a couple of pixels on a pin, but half the
     * artwork on a centred custom marker -- 50px between a 150px store image
     * and a 50px active one.
     *
     * @since  3.0.0
     * @param  {object} marker   The marker whose artwork just changed
     * @param  {number} mapIndex The index of the map the marker belongs to
     * @return {void}
     */
    reanchor: function( marker, mapIndex ) {
        // The same per-map lookup map.getInfoWindow() uses: with several
        // [wpsl_map] shortcodes on a page, this.current is whichever window
        // opened last and can belong to another map entirely.
        const openWindow = this.active.length ? this.active[mapIndex] : this.current;

        if ( ! openWindow || typeof openWindow.getAnchor !== 'function' || openWindow.getAnchor() !== marker ) {
            return;
        }

        openWindow.setOptions( { pixelOffset: this.pixelOffset( marker ) } );
    },

    /**
     * Create a new infoWindow object.
     *
     * @since  3.0.0
     * @return {object} infoWindow The infoWindow object
     */
    create: function() {
        return new google.maps.InfoWindow();
    },

    /**
     * Apply rounded dimensions to the info window buttons: fractional pixel
     * dimensions blur their borders.
     *
     * @since  3.0.0
     * @return {void}
     */
    applyRoundedButtonDimensions: function() {
        const $buttons = jQuery( '.wpsl-info-window .wpsl-styled-btn' );
        if ( ! $buttons.length ) {
            return;
        }

        // Per button, since their text widths differ.
        $buttons.each( function() {
            const $button = jQuery( this ),
                  width = $button.outerWidth(),
                  height = $button.outerHeight();
            
            // Round up to nearest whole pixel and apply inline
            $button.css({
                width: Math.ceil( width ) + 'px',
                height: Math.ceil( height ) + 'px'
            });
        });
    },

    /**
     * Handle clicks for the different info window actions like,
     * direction, streetview and zoom here.
     *
     * @since  3.0.0
     * @param  {object} marker The marker object
     * @param  {object} currentMap The map object
     * @return {void}
     */
    actions: function( marker, currentMap ) {
        jQuery( '.wpsl-info-actions a' ).on( 'click', function( e ) {
            const maxZoom = config.map.autoZoomLevel;
            const clickedElem = jQuery( this );

            e.stopImmediatePropagation();

            if ( jQuery( this ).hasClass( 'wpsl-directions' ) ) {

                // Check if we need to show the direction on the map
                // or send the users to maps.google.com
                if ( config.search.directionRedirect || config.search.namesEnabled ) {
                    return true;
                } else {
                    helpers.directions.scrollToTop();

                    api.directions.init( function() {
                        api.directions.show( clickedElem );
                    });
                }
            } else if ( jQuery( this ).hasClass( 'wpsl-streetview' ) ) {
                api.streetView.activate( marker, currentMap );
            } else if ( jQuery( this ).hasClass( 'wpsl-zoom-here' ) ) {
                currentMap.setCenter( marker.position );
                currentMap.setZoom( maxZoom );

                // Wait for the map to finish zooming, then check if info window is visible
                google.maps.event.addListenerOnce( currentMap, 'idle', function() {
                    const $mapDiv = jQuery( currentMap.getDiv() );
                    const infoWindowNode = $mapDiv.find( '.wpsl-info-window' ).closest( '.gm-style-iw-c' );
                    
                    if ( infoWindowNode.length ) {
                        const infoWindowTop = infoWindowNode.offset().top;
                        const mapTop = $mapDiv.offset().top;

                        // If the info window top is above the map viewport, pan down
                        if ( infoWindowTop < mapTop ) {
                            const panAmount = mapTop - infoWindowTop + 20; // 20px padding
                            
                            currentMap.panBy( 0, -panAmount );
                        }
                    }
                });
            }

            return false;
        });
    },

    /**
     * Set the correct info window content for the marker.
     *
     * @since  3.0.0
     * @param  {object} marker The marker object
     * @param  {object} infoWindowData The data to show in the infowindow
     * @param  {object} currentMap The map object
     * @return {void}
     */
    setContent: function( marker, infoWindowData, currentMap ) {
        let template;

        if ( marker.storeId === 0 && ! slData.directions.active ) {
            template = marker.title;
        } else if ( typeof infoWindowData.store !== 'undefined' ) {
            template = helpers.template.getInfoWindowTemplate( infoWindowData );
        } else {
            template = '<div data-store-id="' + marker.storeId + '" class="wpsl-info-window">' + infoWindowData + '</div>';
        }

        this.openWindow.length = 0;

        // With multiple [wpsl_map] shortcodes on a page, use the infowindow that
        // belongs to the clicked marker's map. Otherwise all maps share the
        // infowindow of the last created map, and a click on map B closes the
        // popup on map A.
        if ( this.active.length && typeof currentMap._wpslMapIndex === 'number' && this.active[currentMap._wpslMapIndex] ) {
            this.current = this.active[currentMap._wpslMapIndex];
        }

        this.current.setContent( template );

        // Per marker, not once: the offset depends on the artwork the marker is
        // wearing right now, which the active marker can have just replaced.
        this.current.setOptions( { pixelOffset: this.pixelOffset( marker ) } );

        this.current.open( currentMap, marker );

        this.openWindow.push( this.current );

        // Add custom class to the outer InfoWindow element for styling consistency
        // Use a small delay to ensure the DOM is fully rendered
        setTimeout( () => {
            const iwContainer = document.querySelector( '.gm-style-iw' );
            if ( iwContainer && ! iwContainer.classList.contains( 'wpsl-infobox' ) ) {
                iwContainer.classList.add( 'wpsl-infobox' );
            }
        }, 0 );

        // Make sure no active marker exists when the pop-up is closed,
        // and return keyboard focus to the marker that triggered it.
        google.maps.event.clearListeners( this.current, 'closeclick' );
        this.current.addListener( 'closeclick', () => {
            markers.restoreActiveMarkers( currentMap._wpslMapIndex );

            // Restore focus to the element that opened the infowindow
            // (either a marker element or a search result element)
            const sourceEl = slData.markers.focusSourceElement;

            if ( sourceEl && sourceEl.nodeType && document.body.contains( sourceEl ) ) {
                setTimeout( () => sourceEl.focus(), 0 );
                slData.markers.focusSourceElement = null;
            } else if ( typeof currentMap !== 'undefined' && typeof currentMap.getDiv === 'function' ) {
                // Fallback: focus the marker if source element is gone
                const mapContainer = currentMap.getDiv();
                const markerTitle  = marker.title;
                const markerEl     = Array.from(
                    mapContainer.querySelectorAll(
                        '[role="button"][aria-label]:not(.wpsl-cluster-marker)[tabindex="0"]'
                    )
                ).find( el => el.getAttribute( 'aria-label' ) === markerTitle );

                if ( markerEl ) {
                    setTimeout( () => markerEl.focus(), 0 );
                }

                slData.markers.focusSourceElement = null;
            }
        });

        wp.hooks.doAction( 'wpslInfoWindowContentFinished', this, infoWindowData, marker, currentMap );
    },
};