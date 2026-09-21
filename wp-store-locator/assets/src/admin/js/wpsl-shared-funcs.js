/**
 * WPSL functions shared between different add-ons.
 *
 * @since 3.0.0
 */

window.wpslSharedFuncs = window.wpslSharedFuncs || {};

/**
 * WCAG contrast maths, shared by the appearance editor and the Marker Studio.
 *
 * @since 3.0.0
 */
( function() {
    'use strict';

    /**
     * The WCAG thresholds. Text is the default because the appearance editor
     * was here first and calls findFixedColor without one.
     *
     * @since 3.0.0
     */
    const TEXT_THRESHOLD    = 4.5;
    const GRAPHIC_THRESHOLD = 3;

    const contrast = {

        TEXT_THRESHOLD:    TEXT_THRESHOLD,
        GRAPHIC_THRESHOLD: GRAPHIC_THRESHOLD,

        /**
         * Convert a hex color string to an {r, g, b} object.
         *
         * @since  3.0.0
         * @param  {string} hex  With or without leading '#', 3 or 6 digits.
         * @return {{r:number, g:number, b:number}|null}  Null when the string isn't a hex color.
         */
        hexToRgb( hex ) {
            let digits = String( hex ).replace( /^#/, '' );

            // Shorthand: every digit stands for itself doubled, so 'f' is 'ff'.
            if ( digits.length === 3 ) {
                digits = digits.replace( /./g, d => d + d );
            }

            if ( ! /^[0-9a-f]{6}$/i.test( digits ) ) {
                return null;
            }

            const n = parseInt( digits, 16 );

            return { r: ( n >> 16 ) & 255, g: ( n >> 8 ) & 255, b: n & 255 };
        },

        /**
         * Calculate relative luminance per WCAG 2.1.
         *
         * @since  3.0.0
         * @param  {{r:number, g:number, b:number}} rgb
         * @return {number} Luminance in [0, 1].
         */
        getLuminance( { r, g, b } ) {
            const weights = [ 0.2126, 0.7152, 0.0722 ];

            return [ r, g, b ].reduce( ( sum, channel, i ) => {
                const v = channel / 255;
                const lin = v <= 0.04045 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
                return sum + lin * weights[ i ];
            }, 0 );
        },

        /**
         * Calculate the WCAG contrast ratio between two hex colors.
         *
         * @since  3.0.0
         * @param  {string} hex1
         * @param  {string} hex2
         * @return {number} Contrast ratio in [1, 21]. Returns 1.0 if either value is invalid.
         */
        getContrastRatio( hex1, hex2 ) {
            try {
                const l1 = this.getLuminance( this.hexToRgb( hex1 ) );
                const l2 = this.getLuminance( this.hexToRgb( hex2 ) );

                return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
            } catch ( e ) {
                return 1.0;
            }
        },

        /**
         * Convert {r, g, b} (0-255) to {h (0-360), s (0-100), l (0-100)}.
         *
         * @since  3.0.0
         * @param  {number} r
         * @param  {number} g
         * @param  {number} b
         * @return {{h:number, s:number, l:number}}
         */
        rgbToHsl( r, g, b ) {
            r /= 255; g /= 255; b /= 255;

            const max = Math.max( r, g, b );
            const min = Math.min( r, g, b );
            const l   = ( max + min ) / 2;
            let h = 0, s = 0;

            if ( max !== min ) {
                const d = max - min;
                s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );

                switch ( max ) {
                    case r:
                        h = ( ( g - b ) / d + ( g < b ? 6 : 0 ) ) / 6;
                        break;
                    case g:
                        h = ( ( b - r ) / d + 2 ) / 6;
                        break;
                    case b:
                        h = ( ( r - g ) / d + 4 ) / 6;
                        break;
                }
            }

            return { h: h * 360, s: s * 100, l: l * 100 };
        },

        /**
         * Convert HSL to {r, g, b} (0-255).
         *
         * @since  3.0.0
         * @param  {number} h  Hue 0-360.
         * @param  {number} s  Saturation 0-100.
         * @param  {number} l  Lightness 0-100.
         * @return {{r:number, g:number, b:number}}
         */
        hslToRgb( h, s, l ) {
            h /= 360; s /= 100; l /= 100;

            if ( s === 0 ) {
                const v = Math.round( l * 255 );
                return { r: v, g: v, b: v };
            }

            const hue2rgb = ( p, q, t ) => {
                if ( t < 0 ) t += 1;
                if ( t > 1 ) t -= 1;
                if ( t < 1 / 6 ) return p + ( q - p ) * 6 * t;
                if ( t < 1 / 2 ) return q;
                if ( t < 2 / 3 ) return p + ( q - p ) * ( 2 / 3 - t ) * 6;
                return p;
            };

            const q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s;
            const p = 2 * l - q;

            return {
                r: Math.round( hue2rgb( p, q, h + 1 / 3 ) * 255 ),
                g: Math.round( hue2rgb( p, q, h         ) * 255 ),
                b: Math.round( hue2rgb( p, q, h - 1 / 3 ) * 255 ),
            };
        },

        /**
         * Convert {r, g, b} (0-255) to a '#rrggbb' hex string.
         *
         * @since  3.0.0
         * @param  {{r:number, g:number, b:number}} rgb
         * @return {string}
         */
        rgbToHex( { r, g, b } ) {
            return '#' + [ r, g, b ]
                .map( v => Math.min( 255, Math.max( 0, v ) ).toString( 16 ).padStart( 2, '0' ) )
                .join( '' );
        },

        /**
         * Binary-search the lightness ( within [lo, hi] ) that hits a target
         * luminance, without changing the color's hue or saturation.
         *
         * @since  3.0.0
         * @param  {number} h          Hue 0-360.
         * @param  {number} s          Saturation 0-100.
         * @param  {number} targetLum  Target relative luminance in [0, 1].
         * @param  {number} lo         Lower bound for L (0-100).
         * @param  {number} hi         Upper bound for L (0-100).
         * @return {number}            Lightness value in [lo, hi].
         */
        findLightness( h, s, targetLum, lo, hi ) {
            for ( let i = 0; i < 30; i++ ) {
                const mid = ( lo + hi ) / 2;
                const lum = this.getLuminance( this.hslToRgb( h, s, mid ) );
                if ( lum < targetLum ) { lo = mid; } else { hi = mid; }
            }

            return ( lo + hi ) / 2;
        },

        /**
         * Find the closest color to `fgHex` that reaches `threshold` against
         * `bgHex`, adjusting only lightness. Both directions ( lighter and
         * darker ) are tried; the smallest lightness change wins.
         *
         * @since  3.0.0
         * @param  {string}      fgHex        Foreground hex color.
         * @param  {string}      bgHex        Background hex color.
         * @param  {number}      [threshold]  Ratio to reach. Defaults to the text threshold.
         * @return {string|null}              Fixed hex color, or null if no fix was found.
         */
        findFixedColor( fgHex, bgHex, threshold ) {
            const fgRgb = this.hexToRgb( fgHex );
            const bgRgb = this.hexToRgb( bgHex );

            if ( ! fgRgb || ! bgRgb ) {
                return null;
            }

            const R = typeof threshold === 'number' ? threshold : TEXT_THRESHOLD;

            const bgLum = this.getLuminance( bgRgb );
            const { h, s, l: originalL } = this.rgbToHsl( fgRgb.r, fgRgb.g, fgRgb.b );

            // Solve for the exact luminance required in each direction.
            const lighterLum = R * ( bgLum + 0.05 ) - 0.05;
            const darkerLum  = ( bgLum + 0.05 ) / R - 0.05;

            /**
             * The binary search lands on the WCAG boundary, but rounding to
             * whole 0-255 channels can shift the result back below it. So walk
             * the lightness 0.5 at a time, re-checking the ratio on the rounded
             * hex until it passes.
             *
             * @param  {number} targetLum  Target luminance at the boundary.
             * @param  {number} direction  +1 to walk lighter, -1 to walk darker.
             * @return {{hex:string, l:number}|null}
             */
            const resolve = ( targetLum, direction ) => {
                let l = this.findLightness( h, s, targetLum, 0, 100 );

                for ( let i = 0; i < 40; i++ ) {
                    const hex = this.rgbToHex( this.hslToRgb( h, s, l ) );

                    if ( this.getContrastRatio( hex, bgHex ) >= R ) {
                        return { hex, l };
                    }

                    l += direction * 0.5;

                    if ( l < 0 || l > 100 ) {
                        break;
                    }
                }

                return null;
            };

            const candidates = [];

            // Candidate 1: fg lighter than bg
            if ( lighterLum >= 0 && lighterLum <= 1 ) {
                const result = resolve( lighterLum, +1 );

                if ( result ) {
                    candidates.push( { hex: result.hex, delta: Math.abs( result.l - originalL ) } );
                }
            }

            // Candidate 2: fg darker than bg
            if ( darkerLum >= 0 && darkerLum <= 1 ) {
                const result = resolve( darkerLum, -1 );

                if ( result ) {
                    candidates.push( { hex: result.hex, delta: Math.abs( result.l - originalL ) } );
                }
            }

            if ( ! candidates.length ) {
                return null;
            }

            return candidates.sort( ( a, b ) => a.delta - b.delta )[ 0 ].hex;
        }
    };

    window.wpslSharedFuncs.contrast = contrast;

} )();


( function( $ ) {
    'use strict';

    let usingMouse = false;

    // Track mouse vs keyboard input
    $( document ).on( 'mousedown', function() {
        usingMouse = true;
        $( 'body' ).removeClass( 'wpsl-keyboard-nav' );
    });

    $( document ).on( 'keydown', function( e ) {
        usingMouse = false;

        // Only declare keyboard-navigation mode on Tab — typing in a field
        // should not activate keyboard-specific styling.
        if ( e.key === 'Tab' ) {
            $( 'body' ).addClass( 'wpsl-keyboard-nav' );
        }
    });

    /**
     * Create toggle sliders for the given checkbox/radio elements.
     *
     * @param {object} [targetElem] jQuery object of input elements to convert.
     *                              If omitted, targets settings form checkboxes/radios.
     */
    wpslSharedFuncs.createToggleSliders = function( targetElem ) {
        const $slide = '<div class="wpsl-toggler-slider" tabindex="0" role="switch"><div class="wpsl-toggler-knob"></div></div>';

        if ( typeof targetElem === 'undefined' ) {
            targetElem = $( '#wpsl-settings-form section input[type=checkbox], #wpsl-settings-form .wpsl-styled-radio input[type=radio]' ).not( '.wpsl-styled-template-preview input, .wpsl-multiselect-container input, .wpsl-icon-radio-group input, .wpsl-icon-dropdown-wrapper input' );
        }

        const $targets = targetElem;
        for ( let i = 0; i < $targets.length; i++ ) {
            const $input = $( $targets[i] );

            // Skip if already converted
            if ( $input.parent().hasClass( 'wpsl-toggle-wrap' ) ) {
                continue;
            }

            $input.wrap( '<label class="wpsl-toggle-wrap"></label>' )
                 .after( $slide )
                 .hide()
                 .removeClass( 'wpsl-toggle-pending' );

            const $slider = $input.next( '.wpsl-toggler-slider' );
            $slider.attr( 'aria-checked', $input.prop( 'checked' ) );

            $input.on( 'change', function() {
                $slider.attr( 'aria-checked', $( this ).prop( 'checked' ) );
            });

            if ( $input.is( ':disabled' ) ) {
                $slider.addClass( 'wpsl-disabled-slider' ).attr( 'aria-disabled', 'true' );
            }

            $slider.on( 'focus', function() {
                if ( usingMouse ) {
                    $( this ).addClass( 'using-mouse' );
                } else {
                    $( this ).removeClass( 'using-mouse' );
                }
            });

            ( function( $inp ) {
                $inp.next( '.wpsl-toggler-slider' ).on( 'keydown', function( e ) {
                    if ( e.key === ' ' || e.key === 'Enter' ) {
                        e.preventDefault();

                        if ( ! $inp.is( ':disabled' ) ) {
                            $inp.prop( 'checked', ! $inp.prop( 'checked' ) ).trigger( 'change' );
                            $( this ).attr( 'aria-checked', $inp.prop( 'checked' ) );
                        }
                    }
                });
            })( $input );
        }
    };

    /**
     * Bind the marker picker ( start / store / active ) open, select and
     * dismiss handlers.
     *
     * Shared between the settings page and the shortcode generator thickbox.
     *
     * @since   3.0.0
     * @returns {object} The close helpers: close() and leavePanelMode( $popovers ).
     */
    wpslSharedFuncs.initMarkerPickers = function() {
        const leavePanelMode = function( $popovers ) {
            $popovers.removeClass( 'is-panel' )
                .find( '.wpsl-marker-picker-panel' ).prop( 'hidden', true );
        };

        const closeMarkerPickers = function() {
            const $popovers = $( '.wpsl-marker-picker-popover' );
            $popovers.prop( 'hidden', true );
            leavePanelMode( $popovers );

            $( '.wpsl-marker-picker-toggle' ).attr( 'aria-expanded', 'false' );
        };

        $( document ).on( 'click', '.wpsl-marker-picker-toggle', function( e ) {
            e.preventDefault();

            const $toggle  = $( this );
            const $popover = $toggle.siblings( '.wpsl-marker-picker-popover' );
            const isOpen   = ! $popover.prop( 'hidden' );

            closeMarkerPickers();
            if ( ! isOpen ) {
                $popover.prop( 'hidden', false );

                // Reserve the scrollbar gutter only when the list actually
                // scrolls; always reserving it left ~15px of blank right edge.
                const popover = $popover.get( 0 );
                $popover.toggleClass( 'wpsl-is-scrollable', popover.scrollHeight > popover.clientHeight );

                $toggle.attr( 'aria-expanded', 'true' );
            }
        });

        $( document ).on( 'click', '.wpsl-marker-picker-tile', function( e ) {
            e.preventDefault();

            const $tile   = $( this );
            const $picker = $tile.closest( '.wpsl-marker-picker' );

            $picker.find( '.wpsl-marker-picker-tile' ).removeClass( 'is-selected' );
            $tile.addClass( 'is-selected' );
            $picker.find( 'input[type=hidden]' ).val( $tile.attr( 'data-marker' ) );
            // A custom marker's tile carries an inline max-height
            // ( Custom_Markers::get_picker_height() ); copy style alongside src
            // or switching leaves a stale correction behind.
            $picker.find( '.wpsl-marker-picker-toggle img' )
                .attr( 'src', $tile.find( 'img' ).attr( 'src' ) )
                .attr( 'style', $tile.find( 'img' ).attr( 'style' ) || '' );
            $picker.find( '.wpsl-marker-picker-name' ).text( $tile.attr( 'data-name' ) );
            closeMarkerPickers();
        });

        $( document ).on( 'click', function( e ) {
            if ( ! $( e.target ).closest( '.wpsl-marker-picker' ).length ) {
                closeMarkerPickers();
            }
        });

        // Escape steps back one level rather than dismissing everything at once.
        $( document ).on( 'keydown', function( e ) {
            if ( e.key !== 'Escape' ) {
                return;
            }
            if ( $( '.wpsl-color-picker-popup.wpsl-active' ).length ) {
                return;
            }

            const $inPanel = $( '.wpsl-marker-picker-popover.is-panel' ).not( '[hidden]' );
            if ( $inPanel.length ) {
                leavePanelMode( $inPanel );
                $inPanel.siblings( '.wpsl-marker-picker-toggle' ).trigger( 'focus' );
                return;
            }

            closeMarkerPickers();
        });

        return {
            close: closeMarkerPickers,
            leavePanelMode: leavePanelMode
        };
    };

    /**
     * Bind the marker dropdown ( .wpsl-lm-dropdown ) open, select and dismiss
     * handlers. Shared between the store editor's Location Marker metabox and
     * the shortcode generator, which render the same markup through
     * Marker_Manager::render_marker_dropdown(). 
     * 
     * Picking the default item writes
     * an empty string to the hidden input.
     *
     * @since   3.0.0
     * @param   {object}   [opts]          Optional settings.
     * @param   {Function} [opts.onSelect] Called after a marker is selected,
     *                                     with ( $item, $dropdown ) -- the
     *                                     store editor mirrors the pick onto
     *                                     its preview map through this.
     * @returns {void}
     */
    wpslSharedFuncs.initMarkerDropdowns = function( opts ) {
        opts = opts || {};

        const closeMenu = function() {
            $( '.wpsl-lm-menu' ).attr( 'hidden', true );
            $( '.wpsl-lm-toggle' ).attr( 'aria-expanded', 'false' );
        };

        $( document ).on( 'click', '.wpsl-lm-toggle', function( e ) {
            e.preventDefault();

            const $toggle  = $( this );
            const $menu    = $toggle.siblings( '.wpsl-lm-menu' );
            const isHidden = $menu.attr( 'hidden' );

            // Close any other open dropdown before opening this one.
            closeMenu();

            if ( isHidden ) {
                $menu.removeAttr( 'hidden' );
                $toggle.attr( 'aria-expanded', 'true' );
            }
        });

        /*
         * Every .wpsl-lm-item is a marker. The "create a new one" links are
         * .wpsl-lm-create, outside this handler's reach and needing no JS.
         */
        $( document ).on( 'click', '.wpsl-lm-item', function( e ) {
            e.preventDefault();

            const $item     = $( this );
            const $dropdown = $item.closest( '.wpsl-lm-dropdown' );
            const marker    = $item.data( 'marker' );

            const name = $item.find( 'span:not(.wpsl-lm-art)' ).contents().first().text();
            const src  = $item.find( 'img' ).attr( 'src' );

            $dropdown.find( '.wpsl-lm-item' ).removeClass( 'selected' );
            $item.addClass( 'selected' );

            $dropdown.find( 'input[type="hidden"]' ).val( marker );
            $dropdown.find( '.wpsl-lm-toggle img' ).attr( 'src', src );
            $dropdown.find( '.wpsl-lm-name' ).text( name );

            if ( typeof opts.onSelect === 'function' ) {
                opts.onSelect( $item, $dropdown );
            }

            closeMenu();
        });

        $( document ).on( 'click', function( e ) {
            if ( ! $( e.target ).closest( '.wpsl-lm-dropdown' ).length ) {
                closeMenu();
            }
        });

        $( document ).on( 'keydown', function( e ) {
            if ( e.key === 'Escape' ) {
                closeMenu();
            }
        });
    };

    /*
     * bindInfoPopup() is re-invoked constantly ( group switches, dialog opens,
     * contrast warnings ), so the tooltip state lives at script scope: the
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

        const $all = $( sel ).filter( ':visible' ).not( function() {
            return $( this ).closest( '#wpsl-tooltip-portal' ).length;
        } );

        const idx = $all.index( $reference );

        return ( idx >= 0 && idx < $all.length - 1 ) ? $all.eq( idx + 1 ) : $( [] );
    }

    /**
     * Bind the window / document level tooltip handlers exactly once.
     *
     * Only one tooltip exists at a time, so these can serve every icon set
     * bindInfoPopup() was ever called with by targeting .wpsl-info live.
     *
     * @returns {void}
     */
    function bindTooltipGlobals() {
        if ( tooltipGlobalsBound ) {
            return;
        }

        tooltipGlobalsBound = true;

        // Close tooltips on scroll/wheel events
        $( window ).on( 'wheel.wpslTooltip scroll.wpslTooltip', function() {
            if ( tooltipTimeout ) {
                clearTimeout( tooltipTimeout );
                tooltipTimeout = null;
            }

            $( '#wpsl-tooltip-portal' ).empty();
            $( '.wpsl-info' ).removeData( 'portal-tooltip' );

            isCreatingTooltip = false;
        });

        // Cancel the close timer when the mouse enters the portal tooltip,
        // so moving from the icon into the tooltip never closes it mid-transit.
        $( document ).on( 'mouseenter.wpslTooltip', '.wpsl-tooltip-portal', function() {
            if ( tooltipTimeout ) {
                clearTimeout( tooltipTimeout );
                tooltipTimeout = null;
            }
        } );

        $( document ).on( 'mouseleave.wpslTooltip', '.wpsl-tooltip-portal', function( e ) {
            const $portalTooltip = $( this );

            const $associatedIcon = $( '.wpsl-info' ).filter( function() {
                return $( this ).data( 'portal-tooltip' ) && $( this ).data( 'portal-tooltip' ).is( $portalTooltip );
            });

            // Moving back onto the icon is not a dismissal.
            if ( $associatedIcon.length && $( e.relatedTarget ).closest( $associatedIcon ).length ) {
                return;
            }

            hideTooltip( $associatedIcon, $portalTooltip );
        });

        // When focus leaves the portal tooltip entirely, close it.
        $( document ).on( 'focusout.wpslTooltip', '#wpsl-tooltip-portal', function() {
            setTimeout( function() {
                const $active = $( document.activeElement );

                // Still inside the portal, or returning to an info icon — keep open.
                if ( $active.closest( '#wpsl-tooltip-portal' ).length || $active.is( '.wpsl-info' ) ) {
                    return;
                }

                let $ownerIcon = $( [] );

                $( '.wpsl-info' ).each( function() {
                    if ( $( this ).data( 'portal-tooltip' ) ) {
                        $ownerIcon = $( this );

                        return false; // break each
                    }
                } );

                if ( $ownerIcon.length ) {
                    hideTooltip( $ownerIcon, $ownerIcon.data( 'portal-tooltip' ) );
                } else {
                    $( '#wpsl-tooltip-portal' ).empty();
                }
            }, 50 );
        } );
    }

    /**
     * Bind info popup/tooltip functionality to .wpsl-info elements.
     *
     * @param {object} [elem] jQuery object of .wpsl-info elements to bind.
     *                        If omitted, targets all .wpsl-info elements.
     */
    wpslSharedFuncs.bindInfoPopup = function( elem ) {
        const $wpslInfo = ( typeof elem !== 'undefined' ) ? elem : $( '.wpsl-info' );

        // Make info icons keyboard-accessible
        $wpslInfo.each( function() {
            const $icon = $( this );
            if ( ! $icon.attr( 'tabindex' ) ) {
                $icon.attr( 'tabindex', '0' );
            }

            $icon.attr( 'role', 'button' );
            if ( ! $icon.attr( 'aria-label' ) ) {
                $icon.attr( 'aria-label', 'More information' );
            }
        } );

        if ( ! $( '#wpsl-tooltip-portal' ).length ) {
            $( 'body' ).append( '<div id="wpsl-tooltip-portal"></div>' );
        }

        bindTooltipGlobals();

        // Rebinding the same set must replace its handlers, not stack them.
        $wpslInfo.off( '.wpslTooltip' );

        $wpslInfo.on( 'mouseenter.wpslTooltip', function() {
            const $infoIcon = $( this );
            const $infoText = $infoIcon.find( '.wpsl-info-text' );

            if ( tooltipTimeout ) {
                clearTimeout( tooltipTimeout );
                tooltipTimeout = null;
            }

            // Prevent multiple tooltips from being created simultaneously
            if ( isCreatingTooltip ) {
                return;
            }

            isCreatingTooltip = true;
            
            if ( ! $infoText.length ) {
                isCreatingTooltip = false;
                return;
            }

            // Only ever one tooltip on screen.
            $( '#wpsl-tooltip-portal' ).empty();
            $( '.wpsl-info' ).removeData( 'portal-tooltip' );

            const $portalTooltip = $infoText.clone()
                .addClass( 'wpsl-tooltip-portal' )
                .appendTo( '#wpsl-tooltip-portal' );

            // Laid out off-screen so it can be measured before it is placed.
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

            // Viewport coordinates throughout, to match the fixed positioning below.
            left = iconRect.left + ( iconRect.width / 2 ) - ( tooltipRect.width / 2 );

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

            // The arrow points at the icon's centre, clamped inside the tooltip.
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

            // Trigger animation
            setTimeout( function() {
                $portalTooltip.addClass( 'wpsl-show' );
            }, 10 );

            // Store reference for cleanup
            $infoIcon.data( 'portal-tooltip', $portalTooltip );

            // --- Portal keyboard trap ---
            const $portalLinks = $portalTooltip.find( 'a[href]' ).filter( ':visible' );

            if ( $portalLinks.length ) {
                // Shift+Tab on first link → return focus to the info icon.
                $portalLinks.first().on( 'keydown.wpsl-portal', function( e ) {
                    if ( e.key === 'Tab' && e.shiftKey ) {
                        e.preventDefault();
                        hideTooltip( $infoIcon, $portalTooltip );
                        $infoIcon[0].focus();
                    }
                } );

                // Tab on last link → go to the next tabbable element after the icon.
                $portalLinks.last().on( 'keydown.wpsl-portal', function( e ) {
                    if ( e.key === 'Tab' && ! e.shiftKey ) {
                        e.preventDefault();

                        // Resolve the target before removing the tooltip from the DOM.
                        const $next = getNextTabbable( $infoIcon );

                        hideTooltip( $infoIcon, $portalTooltip );

                        if ( $next.length ) {
                            $next[0].focus();
                        }
                    }
                } );

                // Escape on any portal link → close and return focus to icon.
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
            const $infoIcon = $( this );
            const $portalTooltip = $infoIcon.data( 'portal-tooltip' );

            if ( ! $portalTooltip ) {
                return;
            }

            if ( $( e.relatedTarget ).closest( '.wpsl-tooltip-portal' ).length ) {
                return;
            }

            // Debounced, so the gap between icon and tooltip survives the crossing.
            tooltipTimeout = setTimeout( function() {
                hideTooltip( $infoIcon, $portalTooltip );
            }, 100 );
        });

        // Focus: show tooltip the same way mouseenter does.
        $wpslInfo.on( 'focus.wpslTooltip', function() {
            $( this ).trigger( 'mouseenter' );
        } );

        // Blur: hide tooltip unless focus moved into the portal.
        $wpslInfo.on( 'blur.wpslTooltip', function() {
            const $icon = $( this );

            if ( tooltipTimeout ) {
                clearTimeout( tooltipTimeout );
            }

            tooltipTimeout = setTimeout( function() {
                if ( $( document.activeElement ).closest( '#wpsl-tooltip-portal' ).length ) {
                    return; // Focus went into the tooltip — keep it open.
                }

                const $portal = $icon.data( 'portal-tooltip' );

                if ( $portal ) {
                    hideTooltip( $icon, $portal );
                }
            }, 50 );
        } );

        // Keydown: Escape / Enter / Space / Tab
        $wpslInfo.on( 'keydown.wpslTooltip', function( e ) {
            const $icon = $( this );

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

            // Tab: move focus into first VISIBLE link in the tooltip, if any.
            if ( e.key === 'Tab' && ! e.shiftKey ) {
                const $openTip = $icon.data( 'portal-tooltip' );

                if ( $openTip ) {
                    const $firstLink = $openTip.find( 'a[href]' ).filter( ':visible' ).first();

                    if ( $firstLink.length ) {
                        e.preventDefault();
                        $firstLink[0].focus();
                    }
                }
            }
        } );

    };

    /**
     * Show or hide a .wpsl-conditional-option row when the
     * .wpsl-has-conditional-option field above it changes.
     *
     * @since 3.0.0
     */
    wpslSharedFuncs.bindConditionalOptions = function() {
        $( document ).on( 'change', '.wpsl-has-conditional-option', function() {
            const $el = $( this );
            const $conditionalOption = $el.parents( 'p' ).next( '.wpsl-conditional-option' );

            // Don't show conditional options that require permalinks when permalinks are disabled.
            const $permSelect = $conditionalOption.find( 'select[data-permalinks-enabled]' );
            if ( $permSelect.length && $permSelect.data( 'permalinks-enabled' ) == 0 ) {
                $conditionalOption.hide();
                return;
            }

            // A select drives the row from its value, so a multi-option dropdown
            // doesn't flip the row on every change like a blind toggle would.
            if ( $el.is( 'select' ) ) {
                $conditionalOption.toggle( $el.val() === 'custom' );
            } else {
                $conditionalOption.toggle();
            }
        });
    };

    /**
     * Show a WordPress-style snackbar: a self-dismissing bar at the bottom
     * centre of the screen.
     *
     * For confirming an action whose result is off screen -- a saved template,
     * a saved collection of shapes.
     *
     * @since   3.0.0
     * @param   {string} message Plain text; inserted as text, not markup.
     * @param   {object} [action] { label: string, onClick: function }.
     * @returns {void}
     */
    wpslSharedFuncs.snackbar = function( message, action ) {
        $( '.wpsl-snackbar' ).remove();

        const $snackbar = $( '<div class="wpsl-snackbar"><div class="wpsl-snackbar__content"></div></div>' );
        const hasAction = !! ( action && action.label && 'function' === typeof action.onClick );

        $snackbar.find( '.wpsl-snackbar__content' ).text( message );

        if ( hasAction ) {
            const $action = $( '<button type="button" class="wpsl-snackbar__action"></button>' ).text( action.label );

            $action.on( 'click', function() {
                $snackbar.remove();
                action.onClick();
            } );

            $snackbar.append( $action );
        }

        $( 'body' ).append( $snackbar );

        setTimeout( function() {
            $snackbar.fadeOut( 400, function() {
                $( this ).remove();
            } );
        }, hasAction ? 8000 : 3000 );
    };

} )( jQuery );