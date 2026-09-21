import { slData, config } from './wpsl-shared.js';
import { search } from './wpsl-search.js';
import { helpers } from './wpsl-helpers.js';

/**
 * Event handlers for WPSL frontend interactions
 * 
 * Binds everything that happens after the locations are loaded.
 * 
 * @since 3.0.0
 */
export const eventHandlers = {
    /**
     * Handle the search for the nearest location.
     *
     * Only rendered when the option is enabled on the settings page.
     *
     * @since 	3.0.0
     * @param   {object} ajaxData The AJAX data for the search
     * @returns {void}
     */
    bindNearestBtn: function( ajaxData ) {
        const args = {type: 'nearest'};
        const fields = ['action', 'lat', 'lng', 'filter', 'restrictions'];

        jQuery( '#wpsl-search-nearest-btn' ).on( 'click', function( e ) {
            // Only copy the required fields from ajaxData
            jQuery.each( fields, function( index, value ) {
                if ( ajaxData.hasOwnProperty( value) ) {
                    args[value] = ajaxData[value];
                }
            });

            // Try to get the nearest location ignoring the search radius.
            search.makeAjaxRequest( wp.hooks.applyFilters( 'wpslNearbyAjaxArgs', args ) );

            return false;
        });
    },

    /**
     * Bind the directions click event in the search results.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindDirections: function() {
        jQuery( '#wpsl-result-list' ).on( 'click', '.wpsl-directions', function() {
            // Mapbox always renders directions locally on the map (no external link option)
            const isMapbox = config.api.provider === 'mapbox';
            const shouldRenderLocally = isMapbox || ( ! config.search.directionRedirect && ! config.search.skipGeocode 
                && ! helpers.results.maybeUseBasicMode() && ( ( config.api.provider !== 'osm' && config.api.provider !== 'stadia' ) || config.api.hasValidRouteKey ) );

            if ( shouldRenderLocally ) {
                const clickedElem = jQuery( this );

                helpers.directions.scrollToTop();

                slData.provider.api.directions.init( function() {
                    slData.provider.api.directions.show( clickedElem );
                });

                return false;
            }
        });
    },

    /**
     * Handle a click on the "currently open" text and show the whole week.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindExpandingHours: function() {
        // Unbind previous handler to prevent duplicate handlers accumulating
        jQuery( document ).off( 'click', '.wpsl-opening-hours-status a' );
        
        jQuery( document ).on( 'click', '.wpsl-opening-hours-status a', function() {
            jQuery( this ).parents( 'p' ).next( 'table, dl.wpsl-flex-opening-hours' ).toggle();
            jQuery( this ).attr( 'aria-expanded', jQuery( this ).attr( 'aria-expanded' ) === 'false' ? 'true' : 'false' );
            jQuery( this ).toggleClass( 'wpsl-active-details' );

            return false;
        });
    },

    /**
     * Check if flex items have wrapped to multiple lines
     * and add wpsl-wrapped class if they have.
     *
     * @since   3.0.0
     * @returns {void}
     */
    checkFlexWrapping: function() {
        // Debounced: this also runs as the window resize handler, and the
        // offset() reads below force a layout for every event in a resize
        // drag. Only the last call in a burst measures. The delay doubles
        // as the old render-settling timeout, so a single call still runs
        // shortly after the DOM update it was scheduled for. Stored on
        // eventHandlers because `this` is window on the resize path.
        clearTimeout( eventHandlers.flexWrapTimer );

        eventHandlers.flexWrapTimer = setTimeout( function() {
            const $items = jQuery( '.wpsl-flex-hours' );

            if ( ! $items.length ) {
                return;
            }

            // Phase 1 (write): reset to unwrapped state for consistent measurement.
            $items.removeClass( 'wpsl-wrapped' );

            // Phase 2 (read): measure every item. No writes here, so the layout
            // computed for the first read is reused for all subsequent reads.
            const wrapped = [];

            $items.each( function() {
                const item   = this;
                const $spans = jQuery( item ).find( 'span' );

                if ( $spans.length <= 1 ) {
                    return;
                }

                const firstTop = $spans.first().offset().top;

                $spans.each( function() {
                    if ( jQuery( this ).offset().top > firstTop + 2 ) {
                        wrapped.push( item );

                        return false;
                    }
                });
            });

            // Phase 3 (write): apply the class to all wrapped items in one batch.
            if ( wrapped.length ) {
                jQuery( wrapped ).addClass( 'wpsl-wrapped' );
            }
        }, 150 );
    },

    /**
     * Bind the keyboard skip link that lets users bypass map markers
     * and jump directly to the search results.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindSkipToResults: function() {
        jQuery( document ).on( 'click', '.wpsl-skip-to-results', function( e ) {
            e.preventDefault();

            const $resultList    = jQuery( '#wpsl-result-list' );
            const $firstFocusable = $resultList
                .find( 'a[href], button:not([disabled]), [tabindex="0"]' )
                .first();

            if ( $firstFocusable.length ) {
                $firstFocusable.trigger( 'focus' );
            } else {
                // Fallback when no results are loaded yet; the container has
                // tabindex="-1" added in the PHP template so it is focusable.
                $resultList.trigger( 'focus' );
            }
        } );
    },

    /**
     * Bind different handlers to the
     * search results and dropdowns.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers: function() {
        let storeId, $parentLi;

        // Handle clicks on the address details, but not when marker clusters
        // are enabled. The cluster properties can also come from a [wpsl_map]
        // elsewhere on the page, so check the locator's own enable flag.
        if ( config.ux.addressEvent && ! config.markers.markerClusters ) {
            const $addressParagraphs = jQuery( '#wpsl-stores li p:first-child' ).not( '.wpsl-opening-hours-status' );

            // Make address paragraphs keyboard-accessible
            $addressParagraphs.attr( {
                'tabindex': '0',
                'role': 'button'
            } );

            $addressParagraphs.on( 'click keydown mouseover', function( e ) {
                const addressEl = jQuery( this );
                storeId = addressEl.parents( 'li' ).data( 'store-id' );

                if ( e.type === 'click' ) {
                    slData.markers.focusSourceElement = addressEl.get( 0 );
                    slData.provider.markers.triggerClick( storeId );
                } else if ( e.type === 'keydown' ) {
                    if ( e.key === 'Enter' || e.key === ' ' ) {
                        e.preventDefault();

                        slData.markers.focusSourceElement = addressEl.get( 0 );
                        slData.markers.keyboardTriggered = true;
                        slData.provider.markers.triggerClick( storeId );
                    }
                } else if ( e.type === 'mouseover' ) {
                    jQuery( this ).css( 'cursor', 'pointer' );
                }
            });
        }

        // Handle click on the 'more info' link
        jQuery( '#wpsl-stores' ).off( 'click', '.wpsl-store-details' );

        this.bindExpandingHours();
        
        // Check for flex wrapping on load and resize
        this.checkFlexWrapping();
        jQuery( window ).off( 'resize', this.checkFlexWrapping ).on( 'resize', this.checkFlexWrapping );

        jQuery( '#wpsl-stores' ).on( 'click', '.wpsl-store-details', function() {
            $parentLi = jQuery( this ).parents( 'li' );

            storeId = $parentLi.data( 'store-id' );

            if ( config.ux.moreInfoLocation == 'info window' ) {
                // Store the source element so focus can be restored when infowindow closes
                slData.markers.focusSourceElement = jQuery( this ).get( 0 );
                slData.provider.markers.triggerClick( storeId );
            } else {

                if ( $parentLi.find( '.wpsl-more-info-listings' ).is( ':visible' ) ) {
                    jQuery( this ).removeClass( 'wpsl-active-details' ).attr( 'aria-expanded', 'false' );
                } else {
                    jQuery( this ).addClass( 'wpsl-active-details' ).attr( 'aria-expanded', 'true' );
                }

                $parentLi.siblings().find( '.wpsl-store-details' ).removeClass( 'wpsl-active-details' );
                $parentLi.siblings().find( '.wpsl-more-info-listings' ).hide();
                $parentLi.find( '.wpsl-more-info-listings' ).toggle();
            }

            return false;
        });
    }
};
