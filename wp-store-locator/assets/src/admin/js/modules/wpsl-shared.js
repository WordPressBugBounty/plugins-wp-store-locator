/**
 * Store the current state of the WPSL admin page.
 *
 * @since   3.0.0
 */
export const state = {
    focusRegion: {
        active: false,
        latlng: ''
    },
    currentPage: '',
    mapService: ( jQuery( '#wpsl-map-service' ).length ) ? jQuery( '#wpsl-map-service' ).val() : wpslSettings.api.provider,
    blockEditor: jQuery( 'body' ).hasClass( 'block-editor-page' ),
    /**
     * Whether the force postal code search option is currently ticked.
     *
     * A getter, not a snapshot: the checkbox is toggled while the page is open
     * and the geocode test dialog has to act on what it says now rather than on
     * what it said at load.
     *
     * @since   3.0.0
     * @returns {boolean} Whether zip-only search is enabled
     */
    get forcePostalCode() {
        return jQuery( '#wpsl-force-postalcode' ).is( ':checked' );
    },

    // Map tracking
    activeMapIds: [],
    
    // Marker tracking
    activeMarkers: [],

    /**
     * Update the active map service.
     *
     * @since   3.0.0
     * @param   {string} service The map service identifier (gmaps, mapbox, osm)
     * @returns {void}
     */
    setMapService: function( service ) {
        this.mapService = service;
    },
};

/**
 * Get the map container selector for the current page and map provider.
 * 
 * Settings page: .wpsl-map-style-wrap. Editor page: #wpsl-{provider}-wrap.
 * 
 * @since   3.0.0
 * @returns {string} The selector to use for the map container
 */
export const getMapContainerSelector = function() {
    if ( ! state.blockEditor ) {
        return '.wpsl-map-style-wrap';
    }

    return '#wpsl-' + state.mapService + '-wrap';
};

/**
 * Generate the HTML for a compact loader box with spinner and label.
 *
 * Matches the frontend geolocation overlay style.
 *
 * @since   3.0.0
 * @param   {string} [label]  Optional label text. Defaults to wpslL10n.loadSection.
 * @returns {string}          The loader box HTML string.
 */
export const createLoaderHTML = function( label ) {
    const text = label || wpslL10n.loadSection;

    return '<div class="wpsl-geocode-map-loader-box">' +
        '<img src="' + wpslSettings.url + 'assets/img/ajax-loader.svg" width="18" height="18" alt="" />' +
        '<span>' + text + '</span>' +
    '</div>';
};

/*
 * bindInfoPopup() is re-invoked constantly ( group switches, dialog opens,
 * contrast warnings ), so the tooltip state lives at module scope: the
 * window / document level handlers bind once, and every invocation shares
 * the same close timer and creation flag with them.
 */
let tooltipTimeout = null;
let isCreatingTooltip = false;
let tooltipGlobalsBound = false;

function hideTooltip( $infoIcon, $portalTooltip ) {
    $portalTooltip.remove();

    if ( $infoIcon.length ) {
        $infoIcon.removeData( 'portal-tooltip' );
    }
}

/**
 * Return the next tabbable element in the page after $reference,
 * excluding anything inside #wpsl-tooltip-portal.
 *
 * @param  {object} $reference jQuery element to start from.
 * @returns {object} jQuery element, or $([]) if none found.
 */
function getNextTabbable( $reference ) {
    const sel = [
        'a[href]',
        'button:not([disabled])',
        'input:not([type="hidden"]):not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex="0"]',
    ].join( ', ' );

    const $all = jQuery( sel ).filter( ':visible' ).not( function() {
        return jQuery( this ).closest( '#wpsl-tooltip-portal' ).length;
    } );

    const idx = $all.index( $reference );

    return ( idx >= 0 && idx < $all.length - 1 ) ? $all.eq( idx + 1 ) : jQuery( [] );
}

/**
 * Bind the window / document level tooltip handlers exactly once.
 *
 * Only one tooltip exists at a time, so these can serve every icon set
 * bindInfoPopup() was ever called with by targeting .wpsl-info live.
 *
 * @since   3.0.0
 * @returns {void}
 */
function bindTooltipGlobals() {
    if ( tooltipGlobalsBound ) {
        return;
    }

    tooltipGlobalsBound = true;

    // Close tooltips on scroll/wheel events
    jQuery( window ).on( 'wheel.wpslTooltip scroll.wpslTooltip', function() {
        if ( tooltipTimeout ) {
            clearTimeout( tooltipTimeout );
            tooltipTimeout = null;
        }

        jQuery( '#wpsl-tooltip-portal' ).empty();
        jQuery( '.wpsl-info' ).removeData( 'portal-tooltip' );

        isCreatingTooltip = false;
    });

    // Cancel the close timer when the mouse enters the portal tooltip,
    // so moving from the icon into the tooltip never closes it mid-transit.
    jQuery( document ).on( 'mouseenter.wpslTooltip', '.wpsl-tooltip-portal', function() {
        if ( tooltipTimeout ) {
            clearTimeout( tooltipTimeout );
            tooltipTimeout = null;
        }
    } );

    jQuery( document ).on( 'mouseleave.wpslTooltip', '.wpsl-tooltip-portal', function( e ) {
        const $portalTooltip = jQuery( this );

        const $associatedIcon = jQuery( '.wpsl-info' ).filter( function() {
            return jQuery( this ).data( 'portal-tooltip' ) && jQuery( this ).data( 'portal-tooltip' ).is( $portalTooltip );
        });

        // Check if we're moving back to the icon
        if ( $associatedIcon.length && jQuery( e.relatedTarget ).closest( $associatedIcon ).length ) {
            return;
        }

        hideTooltip( $associatedIcon, $portalTooltip );
    });

    // Close the portal when focus leaves it entirely (e.g. Tab past last link
    // was not intercepted, or the user clicked elsewhere).
    jQuery( document ).on( 'focusout.wpslTooltip', '#wpsl-tooltip-portal', function() {
        setTimeout( function() {
            const $active = jQuery( document.activeElement );
            if ( $active.closest( '#wpsl-tooltip-portal' ).length || $active.is( '.wpsl-info' ) ) {
                return;
            }

            let $ownerIcon = jQuery( [] );

            jQuery( '.wpsl-info' ).each( function() {
                if ( jQuery( this ).data( 'portal-tooltip' ) ) {
                    $ownerIcon = jQuery( this );
                    return false;
                }
            } );

            if ( $ownerIcon.length ) {
                hideTooltip( $ownerIcon, $ownerIcon.data( 'portal-tooltip' ) );
            } else {
                jQuery( '#wpsl-tooltip-portal' ).empty();
            }
        }, 50 );
    } );
}

/**
 * Handle the tooltips on the exclamation marks on the settings page.
 *
 * @since   3.0.0
 * @param   {string} elem optional jQuery selector
 * @returns {void}
 */
export const bindInfoPopup = function( elem ) {
    let $wpslInfo = jQuery( '.wpsl-info' );

    if ( typeof elem !== 'undefined' ) {
        $wpslInfo = elem;
    }

    // Ensure every icon is keyboard-reachable and has a semantic role.
    $wpslInfo.each( function() {
        const $icon = jQuery( this );
        if ( ! $icon.attr( 'tabindex' ) ) { $icon.attr( 'tabindex', '0' ); }
        if ( ! $icon.attr( 'role' )     ) { $icon.attr( 'role', 'button' ); }
        if ( ! $icon.attr( 'aria-label' ) ) { $icon.attr( 'aria-label', 'More information' ); }
    } );

    if ( ! jQuery( '#wpsl-tooltip-portal' ).length ) {
        jQuery( 'body' ).append( '<div id="wpsl-tooltip-portal"></div>' );
    }

    bindTooltipGlobals();

    // Rebinding the same set must replace its handlers, not stack them.
    $wpslInfo.off( '.wpslTooltip' );

    $wpslInfo.on( 'mouseenter.wpslTooltip', function() {
        const $infoIcon = jQuery( this ),
              $infoText = $infoIcon.find( '.wpsl-info-text' );

        if ( tooltipTimeout ) {
            clearTimeout( tooltipTimeout );
            tooltipTimeout = null;
        }

        if ( isCreatingTooltip ) {
            return;
        }

        isCreatingTooltip = true;
        
        if ( ! $infoText.length ) {
            isCreatingTooltip = false;

            return;
        }

        jQuery( '#wpsl-tooltip-portal' ).empty();
        jQuery( '.wpsl-info' ).removeData( 'portal-tooltip' );

        const $portalTooltip = $infoText.clone()
            .addClass( 'wpsl-tooltip-portal' )
            .appendTo( '#wpsl-tooltip-portal' );

        // Make tooltip visible but positioned off-screen to measure dimensions
        $portalTooltip.css({
            position: 'absolute',
            visibility: 'hidden',
            display: 'block',
            top: '-9999px',
            left: '0',
            right: 'auto'
        });

        const iconRect = this.getBoundingClientRect();
        const tooltipRect = $portalTooltip[0].getBoundingClientRect();
        
        const spaceAbove = iconRect.top;
        
        let top, left;

        // Use viewport coordinates only (no scroll offset) to avoid transform issues.
        left = iconRect.left + ( iconRect.width / 2 ) - ( tooltipRect.width / 2 );

        // Position vertically based on available space
        if ( spaceAbove >= tooltipRect.height + 45 ) {
            top = iconRect.top - tooltipRect.height - 10;
            $portalTooltip.removeClass( 'wpsl-tooltip-below' );
        } else {
            top = iconRect.bottom + 11;
            $portalTooltip.addClass( 'wpsl-tooltip-below' );
        }

        if ( left < 10 ) {
            left = 10;
        }

        if ( left + tooltipRect.width > window.innerWidth - 10 ) {
            left = window.innerWidth - tooltipRect.width - 10;
        }

        if ( top < 10 ) {
            top = iconRect.bottom + 10;
            $portalTooltip.addClass( 'wpsl-tooltip-below' );
        }

        // The arrow points at the icon center.
        const iconCenterX = iconRect.left + ( iconRect.width / 2 );
        const arrowPosition = iconCenterX - left - 11;

        $portalTooltip.css( '--arrow-left', Math.max( 11, Math.min( arrowPosition, tooltipRect.width - 22 ) ) + 'px' );

        $portalTooltip.css({
            position: 'fixed',
            top: top + 'px',
            left: left + 'px',
            zIndex: 999999,
            visibility: 'visible'
        }).show();

        setTimeout( function() {
            $portalTooltip.addClass( 'wpsl-show' );
        }, 10 );

        $infoIcon.data( 'portal-tooltip', $portalTooltip );

        // Portal keyboard trap — wire up links so Tab/Shift-Tab/Escape
        // stay within the tooltip or return focus sensibly.
        const $portalLinks = $portalTooltip.find( 'a[href]' ).filter( ':visible' );

        if ( $portalLinks.length ) {
            $portalLinks.first().on( 'keydown.wpsl-portal', function( e ) {
                if ( e.key === 'Tab' && e.shiftKey ) {
                    e.preventDefault();
                    hideTooltip( $infoIcon, $portalTooltip );
                    $infoIcon[0].focus();
                }
            } );

            $portalLinks.last().on( 'keydown.wpsl-portal', function( e ) {
                if ( e.key === 'Tab' && ! e.shiftKey ) {
                    e.preventDefault();
                    const $next = getNextTabbable( $infoIcon );
                    hideTooltip( $infoIcon, $portalTooltip );
                    if ( $next.length ) { $next[0].focus(); }
                }
            } );

            $portalLinks.on( 'keydown.wpsl-portal', function( e ) {
                if ( e.key === 'Escape' ) {
                    e.preventDefault();
                    hideTooltip( $infoIcon, $portalTooltip );
                    $infoIcon[0].focus();
                }
            } );
        }

        isCreatingTooltip = false;
    });

    $wpslInfo.on( 'mouseleave.wpslTooltip', function( e ) {
        const $infoIcon = jQuery( this );
        const $portalTooltip = $infoIcon.data( 'portal-tooltip' );

        if ( ! $portalTooltip ) {
            return;
        }

        // Check if we're moving to the tooltip
        if ( jQuery( e.relatedTarget ).closest( '.wpsl-tooltip-portal' ).length ) {
            return;
        }

        tooltipTimeout = setTimeout( function() {
            hideTooltip( $infoIcon, $portalTooltip );
        }, 100 );
    });

    // Keyboard: focus opens the tooltip (same as mouseenter).
    $wpslInfo.on( 'focus.wpslTooltip', function() {
        jQuery( this ).trigger( 'mouseenter' );
    } );

    // Keyboard: blur closes after a short delay (unless focus moved into the portal).
    $wpslInfo.on( 'blur.wpslTooltip', function() {
        const $icon = jQuery( this );

        if ( tooltipTimeout ) { 
            clearTimeout( tooltipTimeout ); 
        }

        tooltipTimeout = setTimeout( function() {
            if ( jQuery( document.activeElement ).closest( '#wpsl-tooltip-portal' ).length ) {
                return;
            }
            
            const $portal = $icon.data( 'portal-tooltip' );
            if ( $portal ) { 
                hideTooltip( $icon, $portal ); 
            }
        }, 50 );
    } );

    // Keyboard: Enter / Space toggles; Escape closes.
    $wpslInfo.on( 'keydown.wpslTooltip', function( e ) {
        const $icon = jQuery( this );

        if ( e.key === 'Escape' ) {
            e.preventDefault();
            const $portal = $icon.data( 'portal-tooltip' );
            if ( $portal ) { 
                hideTooltip( $icon, $portal ); 
            }

            return;
        }

        if ( e.key === 'Enter' || e.key === ' ' ) {
            e.preventDefault();

            if ( $icon.data( 'portal-tooltip' ) ) {
                hideTooltip( $icon, $icon.data( 'portal-tooltip' ) );
            } else {
                $icon.trigger( 'mouseenter' );
            }
            
            return;
        }

        // Tab: move focus into the first visible link inside the portal.
        if ( e.key === 'Tab' && ! e.shiftKey ) {
            const $openTip = $icon.data( 'portal-tooltip' );
            if ( $openTip ) {
                const $firstLink = $openTip.find( 'a[href]' ).filter( ':visible' ).first();
                if ( $firstLink.length ) {
                    e.preventDefault();
                    $firstLink[0].focus();
                }
                // No visible links → let Tab proceed naturally; blur handler closes.
            }
        }
    } );
};