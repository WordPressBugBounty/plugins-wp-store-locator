import { bindInfoPopup } from '../wpsl-shared.js';

/**
 * Accessibility contrast checker for the appearance editor.
 *
 * Each foreground color field carries a `data-contrast-bg` attribute holding
 * the `data-elem` value of its paired background field. Ratios below the
 * WCAG AA threshold (4.5:1) show the existing `.wpsl-info.wpsl-warning`
 * tooltip.
 *
 * PHP pre-computes the initial ratio into `data-initial-contrast-ratio` /
 * `data-initial-contrast-rating` on the `<li>`, so warnings appear before
 * JS runs.
 *
 * @since 3.0.0
 */
export const contrastChecker = {

    /**
     * Reference to the parent appearance editor object.
     */
    parent: null,

    /**
     * WCAG AA threshold for normal-sized text.
     */
    WARN_THRESHOLD: 4.5,

    /**
     * Initialize the module.
     *
     * @since 3.0.0
     * @param {object} parentAppearance
     */
    init( parentAppearance ) {
        this.parent = parentAppearance;
    },

    /**
     * Convert a hex color string to an {r, g, b} object.
     *
     * Shorthand is accepted because the theme style defaults are written as
     * '#fff' / '#000' and reach this method straight from the color fields.
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
     * Find the closest color to `fgHex` that achieves >= 4.5:1 contrast
     * against `bgHex`, adjusting only lightness. Both directions (lighter
     * and darker) are tried; the smallest lightness change wins.
     *
     * @since  3.0.0
     * @param  {string}      fgHex  Foreground hex color.
     * @param  {string}      bgHex  Background hex color.
     * @return {string|null}        Fixed hex color, or null if no fix was found.
     */
    findFixedColor( fgHex, bgHex ) {
        const fgRgb = this.hexToRgb( fgHex );
        const bgRgb = this.hexToRgb( bgHex );

        if ( ! fgRgb || ! bgRgb ) {
            return null;
        }

        const bgLum = this.getLuminance( bgRgb );
        const { h, s, l: originalL } = this.rgbToHsl( fgRgb.r, fgRgb.g, fgRgb.b );
        const R = this.WARN_THRESHOLD;

        // Solve for the exact luminance required in each direction.
        const lighterLum = R * ( bgLum + 0.05 ) - 0.05;
        const darkerLum  = ( bgLum + 0.05 ) / R - 0.05;

        /**
         * The binary search lands on the WCAG boundary, but rounding to whole
         * 0-255 channels for the hex string can shift the result back below
         * the threshold. So nudge the lightness by 0.5 at a time and re-check
         * the ratio on the actual rounded hex until it passes.
         *
         * @param  {number} targetLum  Target luminance at the 4.5:1 boundary.
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
    },

    /**
     * Find the nearest color to `fgHex` that achieves >= 4.5:1 contrast
     * against BOTH `bgHex1` and `bgHex2` (gradient stops), preserving hue
     * and saturation and only adjusting lightness.
     *
     * @since  3.0.0
     * @param  {string}      fgHex
     * @param  {string}      bgHex1
     * @param  {string}      bgHex2
     * @return {string|null}
     */
    findFixedColorForBoth( fgHex, bgHex1, bgHex2 ) {
        const R      = this.WARN_THRESHOLD;
        const passes = ( hex, bg ) => !! hex && !! bg && this.getContrastRatio( hex, bg ) >= R;

        // ── Pass 1 & 2: try fixing against each background individually ──────
        const fix1 = this.findFixedColor( fgHex, bgHex1 );
        if ( passes( fix1, bgHex1 ) && passes( fix1, bgHex2 ) ) {
            return fix1;
        }

        const fix2 = this.findFixedColor( fgHex, bgHex2 );
        if ( passes( fix2, bgHex1 ) && passes( fix2, bgHex2 ) ) {
            return fix2;
        }

        // ── Pass 3: derive the joint luminance constraint ────────────────────
        const bgRgb1 = this.hexToRgb( bgHex1 );
        const bgRgb2 = this.hexToRgb( bgHex2 );
        const fgRgb  = this.hexToRgb( fgHex );

        if ( ! bgRgb1 || ! bgRgb2 || ! fgRgb ) {
            return null;
        }

        const L1 = this.getLuminance( bgRgb1 );
        const L2 = this.getLuminance( bgRgb2 );
        const { h, s } = this.rgbToHsl( fgRgb.r, fgRgb.g, fgRgb.b );

        const lighterMin = R * ( Math.max( L1, L2 ) + 0.05 ) - 0.05;
        const darkerMax  = ( Math.min( L1, L2 ) + 0.05 ) / R - 0.05;

        /**
         * Starting from the boundary luminance, nudge in `direction` (±0.5 L)
         * until the candidate hex passes contrast against both backgrounds.
         *
         * @param  {number}      targetLum  Boundary luminance in [0, 1].
         * @param  {number}      direction  +1 for lighter, -1 for darker.
         * @return {string|null}
         */
        const resolveJoint = ( targetLum, direction ) => {
            if ( targetLum < 0 || targetLum > 1 ) {
                return null;
            }

            let l = this.findLightness( h, s, targetLum, 0, 100 );

            for ( let i = 0; i < 40; i++ ) {
                const hex = this.rgbToHex( this.hslToRgb( h, s, l ) );

                if ( passes( hex, bgHex1 ) && passes( hex, bgHex2 ) ) {
                    return hex;
                }

                l += direction * 0.5;

                if ( l < 0 || l > 100 ) {
                    break;
                }
            }

            return null;
        };

        const jointFix = resolveJoint( lighterMin, +1 ) || resolveJoint( darkerMax, -1 );

        if ( jointFix ) {
            return jointFix;
        }

        // ── Pass 4: last resort — gradient stops too far apart ────────────────
        //
        // lighterMin > 1 or darkerMax < 0: the required ranges don't overlap, so
        // no single text color reaches 4.5:1 against both stops. Best available
        // is the equal-contrast optimum — where the ratio against both stops is
        // identical and maximal — the geometric mean of the adjusted luminances:
        const optimalLum = Math.sqrt( ( L1 + 0.05 ) * ( L2 + 0.05 ) ) - 0.05;

        if ( optimalLum >= 0 && optimalLum <= 1 ) {
            const optimalL = this.findLightness( h, s, optimalLum, 0, 100 );
            return this.rgbToHex( this.hslToRgb( h, s, optimalL ) );
        }

        return fix1 || fix2 || null;
    },

    /**
     * Apply the auto-fix for the foreground field identified by `elemId`:
     * find the nearest passing color, then update the color picker so the
     * standard change flow runs.
     *
     * A secondary background (`data-contrast-bg-2`, on gradient buttons)
     * routes the fix through `findFixedColorForBoth`.
     *
     * @since 3.0.0
     * @param {string} elemId  The `data-elem` value of the foreground `<li>`.
     */
    applyAutoFix( elemId ) {
        if ( ! elemId ) {
            console.warn( 'WPSL contrast auto-fix: missing elemId.' );
            return;
        }

        const $li     = jQuery( `[data-elem="${ elemId }"]` );
        const bgElem  = $li.data( 'contrast-bg' );
        const bgElem2 = $li.data( 'contrast-bg-2' );
        const fgHex   = $li.find( '.wpsl-color-field' ).val();
        const bgHex1  = this.getColorByElem( bgElem );
        const bgHex2  = bgElem2 ? this.getColorByElem( bgElem2 ) : null;

        if ( ! fgHex || ! bgHex1 ) {
            console.warn( 'WPSL contrast auto-fix: could not read fg/bg colors.', { elemId, fgHex, bgHex1 } );
            return;
        }

        // For gradient buttons, find a color that passes BOTH stops at once.
        let fixedHex;

        if ( bgHex2 ) {
            fixedHex = this.findFixedColorForBoth( fgHex, bgHex1, bgHex2 );
        } else {
            fixedHex = this.findFixedColor( fgHex, bgHex1 );
        }

        if ( ! fixedHex ) {
            console.warn( 'WPSL contrast auto-fix: no passing color found for', fgHex, 'on', bgHex1, bgHex2 );
            return;
        }

        const picker = $li.find( '.wpsl-color-field' ).data( 'wpsl-color-picker' );

        if ( ! picker ) {
            console.warn( 'WPSL contrast auto-fix: color picker instance not found on', elemId );
            return;
        }

        const hsl = picker.hexToHsl( fixedHex );
        picker.currentHue   = hsl.h;
        picker.currentSat   = hsl.s;
        picker.currentLight = hsl.l;

        picker.updateUI();
    },

    /**
     * Determine whether no single text color can achieve the WCAG AA ratio
     * (4.5:1) against both gradient stops simultaneously.
     *
     * @since  3.0.0
     * @param  {string}  bgHex1
     * @param  {string}  bgHex2
     * @return {boolean}
     */
    isGradientImpossible( bgHex1, bgHex2 ) {
        const bgRgb1 = this.hexToRgb( bgHex1 );
        const bgRgb2 = this.hexToRgb( bgHex2 );

        if ( ! bgRgb1 || ! bgRgb2 ) {
            return false;
        }

        const R          = this.WARN_THRESHOLD;
        const L1         = this.getLuminance( bgRgb1 );
        const L2         = this.getLuminance( bgRgb2 );
        const lighterMin = R * ( Math.max( L1, L2 ) + 0.05 ) - 0.05;
        const darkerMax  = ( Math.min( L1, L2 ) + 0.05 ) / R - 0.05;

        return lighterMin > 1 && darkerMax < 0;
    },

    /**
     * Return the current hex value for a field identified by its data-elem.
     *
     * @since  3.0.0
     * @param  {string}      elemId  e.g. 'header-container-background'
     * @return {string|null}         Hex string (with '#') or null if not found / empty.
     */
    getColorByElem( elemId ) {
        const val = jQuery( `[data-elem="${ elemId }"]` ).find( '.wpsl-color-field' ).val();
        return val && val.startsWith( '#' ) ? val : null;
    },

    /**
     * Show or hide the contrast warning indicator for a `<li>` element.
     *
     * Reuses the `.wpsl-info.wpsl-warning` markup and `bindInfoPopup` so the
     * tooltip matches the rest of the admin UI.
     *
     * @since 3.0.0
     * @param {jQuery} $li    The <li> containing the color field.
     * @param {number} ratio  WCAG contrast ratio.
     */
    updateWarning( $li, ratio ) {
        const elemId = $li.data( 'elem' );
        let $warn    = $li.find( '.wpsl-info.wpsl-contrast-check' );

        // Compare at display precision, or an integer-rounded hex producing
        // 4.499… warns while formatting as "4.5".
        const roundedRatio = parseFloat( ratio.toFixed( 2 ) );

        if ( roundedRatio < this.WARN_THRESHOLD ) {
            const severity       = roundedRatio < 3.0 ? wpslL10n.contrastVeryLow : wpslL10n.contrastLow;
            const ratioFormatted = roundedRatio.toString();
            const wcagNote       = wpslL10n.contrastWcagRequirement.replace( '{ratio}', ratioFormatted );

            // When no text color can pass 4.5:1 on both gradient stops, drop the
            // WCAG ratio line and the auto-fix link for a note pointing the user
            // at the background colors instead.
            const bgElem2         = $li.data( 'contrast-bg-2' );
            let gradientImpossible = false;
            let bgHex1Cached       = null;
            let bgHex2Cached       = null;

            if ( bgElem2 ) {
                bgHex1Cached = this.getColorByElem( $li.data( 'contrast-bg' ) );
                bgHex2Cached = this.getColorByElem( bgElem2 );

                if ( bgHex1Cached && bgHex2Cached && this.isGradientImpossible( bgHex1Cached, bgHex2Cached ) ) {
                    gradientImpossible = true;
                }
            }

            let message;

            if ( gradientImpossible ) {
                // The best achievable contrast — pure black text vs the darker
                // stop — shows how far off the backgrounds are: "4.2:1" is just
                // under the threshold, "2.8:1" means the dark stop is way off.
                const L1          = this.getLuminance( this.hexToRgb( bgHex1Cached ) );
                const L2          = this.getLuminance( this.hexToRgb( bgHex2Cached ) );
                const bestRatio   = parseFloat( ( ( Math.min( L1, L2 ) + 0.05 ) / 0.05 ).toFixed( 2 ) );
                const impossibleMsg = wpslL10n.contrastGradientImpossible.replace( '{bestRatio}', bestRatio );

                message = severity + ' ' + wpslL10n.contrastHardToRead +
                          '<br><br>' +
                          impossibleMsg;
            } else {
                message = severity + ' ' + wpslL10n.contrastHardToRead +
                          '<br><br>' + wcagNote +
                          '<br><br>' + `<a href="#" class="wpsl-contrast-autofix" data-elem="${ elemId }">${ wpslL10n.contrastAutoFix }</a>`;
            }

            if ( ! $warn.length ) {
                // First time: create the element and bind the tooltip.
                $warn = jQuery(
                    '<span class="wpsl-info wpsl-warning wpsl-contrast-check" tabindex="0" role="button">' +
                        '<span class="wpsl-info-text wpsl-hide"></span>' +
                    '</span>'
                );
                $li.find( '.wpsl-color-picker-wrapper' ).first().append( $warn );

                // Shared tooltip hover + keyboard logic.
                bindInfoPopup( $warn );
            }

            // Keep the accessible label in sync with the current severity level.
            $warn.attr( 'aria-label', severity );

            $warn.find( '.wpsl-info-text' ).html( message );
            $warn.show();
        } else {
            $warn.hide();
        }
    },

    /**
     * Check contrast for a single `<li>` and update its warning indicator.
     *
     * With `data-contrast-bg-2` (gradient backgrounds) the worst of the two
     * ratios drives the warning.
     *
     * @since 3.0.0
     * @param {jQuery} $li
     */
    checkField( $li ) {
        const bgElem  = $li.data( 'contrast-bg' );
        const bgElem2 = $li.data( 'contrast-bg-2' );

        if ( ! bgElem ) {
            return;
        }

        const fgVal   = $li.find( '.wpsl-color-field' ).val();
        const fgColor = fgVal && fgVal.startsWith( '#' ) ? fgVal : null;
        const bgColor = this.getColorByElem( bgElem );

        if ( ! fgColor || ! bgColor ) {
            return;
        }

        let ratio = this.getContrastRatio( fgColor, bgColor );

        // The lower of the two ratios, so either gradient stop can warn.
        if ( bgElem2 ) {
            const bgColor2 = this.getColorByElem( bgElem2 );

            if ( bgColor2 ) {
                ratio = Math.min( ratio, this.getContrastRatio( fgColor, bgColor2 ) );
            }
        }

        this.updateWarning( $li, ratio );
    },

    /**
     * Check every contrast-annotated field on the page.
     *
     * @since 3.0.0
     */
    checkAll() {
        jQuery( '[data-contrast-bg]' ).each( ( _, el ) => {
            this.checkField( jQuery( el ) );
        } );
    },

    /**
     * Bind to color picker change events and the auto-fix link.
     *
     * A changed field is re-checked as a foreground, and every foreground
     * referencing it as a background is re-checked too.
     *
     * The auto-fix handler is delegated so it also works on links cloned into
     * the tooltip portal.
     *
     * @since 3.0.0
     */
    bindEvents() {
        /*
         * Debounced here rather than at the picker, because the picker's change
         * event is what every other consumer repaints from -- a map shape's
         * colour among them -- and delaying it there made all of them lag.
         *
         * This is the pass that actually costs: it walks the document for every
         * field the changed one is a background of, on every frame of a drag. A
         * contrast warning that settles just after the drag reads as instant.
         */
        let contrastTimer = null;
        const pending = new Set();

        jQuery( document ).on( 'change', '.wpsl-color-field', ( e ) => {
            const $li = jQuery( e.target ).closest( '[data-elem]' );

            if ( ! $li.length ) {
                return;
            }

            // Collected rather than overwritten: a drag settles on one field,
            // but a paste or a reset can touch several inside one window.
            pending.add( $li[0] );

            clearTimeout( contrastTimer );

            contrastTimer = setTimeout( () => {
                const fields = [ ...pending ];

                pending.clear();

                fields.forEach( ( field ) => {
                    const $field = jQuery( field );
                    const elemId = $field.data( 'elem' );

                    // This field might itself be a foreground field.
                    this.checkField( $field );

                    // It might also be a background for other fields, primary or gradient.
                    jQuery( `[data-contrast-bg="${ elemId }"], [data-contrast-bg-2="${ elemId }"]` ).each( ( _, el ) => {
                        this.checkField( jQuery( el ) );
                    } );
                } );
            }, 100 );
        } );

        // mousedown, because a mouseleave during the click sequence can remove
        // the portal before a click would land. stopPropagation keeps the
        // mousedown off the picker.
        jQuery( document ).on( 'mousedown', '.wpsl-contrast-autofix', ( e ) => {
            e.preventDefault();
            e.stopPropagation();

            const elemId = jQuery( e.target ).closest( '.wpsl-contrast-autofix' ).data( 'elem' );

            jQuery( '#wpsl-tooltip-portal' ).empty();
            this.applyAutoFix( elemId );
        } );

        // Keyboard: Enter on the auto-fix link — mousedown never fires for
        // keyboard activation, so we need a dedicated keydown handler.
        jQuery( document ).on( 'keydown', '.wpsl-contrast-autofix', ( e ) => {
            if ( e.key === 'Enter' ) {
                e.preventDefault();
                e.stopPropagation();

                const elemId = jQuery( e.target ).closest( '.wpsl-contrast-autofix' ).data( 'elem' );

                jQuery( '#wpsl-tooltip-portal' ).empty();
                this.applyAutoFix( elemId );
            }
        } );
    },

    /**
     * Run checks on page load.
     *
     * Reuses the ratios PHP stored in `data-initial-contrast-ratio`, falling
     * back to a full JS calculation when there is none (e.g. after a reset).
     *
     * @since 3.0.0
     */
    runInitialChecks() {
        jQuery( '[data-contrast-bg]' ).each( ( _, el ) => {
            const $li          = jQuery( el );
            const initialRatio = parseFloat( $li.data( 'initial-contrast-ratio' ) );

            if ( ! isNaN( initialRatio ) ) {
                this.updateWarning( $li, initialRatio );
            } else {
                this.checkField( $li );
            }
        } );
    },
};