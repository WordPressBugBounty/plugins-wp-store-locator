/**
 * WPSL Custom color Picker
 *
 * @since 3.0.0
 */
( function( $ ) {
    'use strict';

    /**
     * Creates a new WPSL_ColorPicker instance and attaches it to the given input element.
     *
     * @constructor
     * @param {HTMLInputElement} element - The hidden input that stores the selected hex value.
     * @param {Object}           options - Configuration options.
     * @param {number}           [options.size=180]          - Canvas diameter in pixels.
     * @param {number}           [options.ringThickness=8]   - Outer lightness ring thickness in pixels.
     * @param {number}           [options.gap=8]             - Gap between the inner wheel and the outer ring in pixels.
     * @param {string}           [options.position='right']  - Popup alignment: 'left' or 'right'.
     */
    window.WPSL_ColorPicker = function( element, options ) {
        if ( ! ( element instanceof HTMLInputElement ) ) {
            throw new Error( 'Element must be an input field' );
        }

        this.element = $( element );
        this.options = $.extend( {
            size: 180,
            ringThickness: 8,
            gap: 8,
            position: 'right'
        }, options );

        this.defaultColor = this.element.data( 'default' ) || '';

        this.center = this.options.size / 2;
        this.innerRadius = ( this.options.size / 2 ) - this.options.ringThickness - this.options.gap;
        this.outerRadius = this.options.size / 2;

        this.state = {
            open: false,
            dragging: false
        };

        this.currentHue = 0;
        this.currentSat = 100;
        this.currentLight = 50;
        this.lastSide = 'right';

        this.init();
    };

    WPSL_ColorPicker.prototype = {

        /**
         * Bootstraps the color picker: state, DOM, wheel cache, events, first UI sync.
         */
        init: function() {
            this._rafId = null;
            this._slowTimer = null;
            this._innerCache = null;
            this._startEmpty = false;
            this._initFromDefault = false;
            this.parseInitialColor();
            this.createUI();

            // The wheel itself is painted on first draw instead of here:
            // an appearance page carries dozens of colour fields and all
            // but the one being opened would never use the canvas.
            this.bindEvents();

            if ( this._startEmpty ) {
                this.clearColor();
            } else {
                this.updateUI( this._initFromDefault );
            }
        },

        /**
         * Reads the current input value and converts it to HSL state.
         * Empty falls back to the default color, or sets _startEmpty so init()
         * applies a transparent no-color state.
         */
        parseInitialColor: function() {
            const value = this.element.val().trim();

            if ( value && value.indexOf( '#' ) === 0 ) {
                const hsl = this.hexToHsl( value );
                this.currentHue = hsl.h;
                this.currentSat = hsl.s;
                this.currentLight = hsl.l;
                return;
            }

            if ( this.defaultColor ) {
                const hsl = this.hexToHsl( this.defaultColor );
                this.currentHue = hsl.h;
                this.currentSat = hsl.s;
                this.currentLight = hsl.l;
                this._initFromDefault = true;
                return;
            }

            this._startEmpty = true;
            this.currentHue = 0;
            this.currentSat = 0;
            this.currentLight = 50;
        },

        /**
         * Builds and injects the picker DOM: preview button, popup, canvas, hex input,
         * lightness swatches and the inner/outer drag markers. The swatches are created
         * once here and recolored each frame rather than recreated.
         */
        createUI: function() {
            const self = this;

            this.element.hide();

            this.wrapper = $( '<div class="wpsl-color-picker-wrapper"></div>' );
            this.element.after( this.wrapper );

            this.preview = $( '<button type="button" class="wpsl-color-preview-btn"></button>' );
            this.previewSwatch = $( '<span class="wpsl-color-swatch"></span>' );
            this.preview.append( this.previewSwatch );
            this.wrapper.append( this.preview );

            this.picker = $( '<div class="wpsl-color-picker-popup"></div>' );
            this.wrapper.append( this.picker );

            const closeSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>';
            this.closeBtn = $( '<button type="button" class="wpsl-close-cross" aria-label="' + wpslL10n.close + '">' + closeSvg + '</button>' );
            this.picker.append( this.closeBtn );

            this.canvasContainer = $( '<div class="wpsl-canvas-container"></div>' );
            this.canvasContainer.css( {
                position: 'relative',
                display: 'inline-block',
                userSelect: 'none'
            } );

            this.canvas = $( '<canvas class="wpsl-color-wheel-canvas"></canvas>' );
            this.canvas.attr( {
                width: this.options.size,
                height: this.options.size
            } );

            this.canvas.css( 'cursor', 'default' );

            this.ctx = this.canvas[0].getContext( '2d' );
            this.canvasContainer.append( this.canvas );
            this.picker.append( this.canvasContainer );

            this.controls = $( '<div class="wpsl-color-controls"></div>' );

            this.hexInput = $( '<input type="text" class="wpsl-hex-input" spellcheck="false" maxlength="7" />' );
            this.hexInputWrap = $( '<div class="wpsl-hex-input-wrap"></div>' );
            this.hexInputWrap.append( this.hexInput );

            const restoreLabel = wpslL10n.restoreDefault;

            this.restoreBtn = $( '<button type="button" class="wpsl-restore-default" aria-label="' + restoreLabel + '"><div class="wpsl-svg-tooltip">' + restoreLabel + '</div><i class="wpsl-icon-reload"></i></button>' );
            this.hexInputWrap.append( this.restoreBtn );

            this.controls.append( this.hexInputWrap );

            this.swatchBox = $( '<div class="wpsl-swatches"></div>' );

            for ( let s = 0; s < 10; s++ ) {
                const $swatch = $( '<div class="swatch"></div>' );

                ( function( idx ) {
                    $swatch.attr( {
                        tabindex:    '0',
                        role:        'button',
                        'aria-label': 'Lightness preset ' + ( idx + 1 ),
                    } );

                    $swatch.on( 'click', function() {
                        self.currentLight = ( idx + 1 ) * 9;
                        self.updateUI();
                    } );

                    $swatch.on( 'keydown', function( e ) {
                        if ( e.key === 'Enter' || e.key === ' ' ) {
                            e.preventDefault();
                            self.currentLight = ( idx + 1 ) * 9;
                            self.updateUI();
                        }
                    } );
                } )( s );

                this.swatchBox.append( $swatch );
            }

            this.controls.append( this.swatchBox );
            this.picker.append( this.controls );

            this.markerInner = $( '<div class="marker-inner"></div>' );
            this.markerOuter = $( '<div class="marker-outer"></div>' );
            this.canvasContainer.append( this.markerInner );
            this.canvasContainer.append( this.markerOuter );
        },

        /**
         * Attaches all event handlers:
         * - Preview button click to toggle the popup.
         * - Mousedown + mousemove drag on the canvas for color selection.
         * - Document click to close when clicking outside the wrapper.
         * - Picker click stop-propagation to prevent the above from closing it.
         * - Enter key on the hex input to apply a manually typed color.
         */
        bindEvents: function() {
            const self = this;

            this.preview.on( 'click', function( e ) {
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            } );

            this.canvas.on( 'mousedown', function( e ) {
                self.handleInput( e );
                const move = function( ev ) { self.handleInput( ev ); };

                $( window ).on( 'mousemove', move );

                $( window ).one( 'mouseup', function() {
                    $( window ).off( 'mousemove', move );
                } );
            } );

            this.picker.on( 'click', function( e ) {
                e.stopPropagation();
            } );

            this.hexInput.on( 'keydown', function( e ) {
                if ( e.key === 'Enter' ) {
                    e.preventDefault();
                    e.stopPropagation();

                    let val = $( this ).val().trim();
                    if ( ! val.startsWith( '#' ) ) val = '#' + val;

                    if ( /^#[0-9A-F]{3}$/i.test( val ) || /^#[0-9A-F]{6}$/i.test( val ) ) {
                        const hsl = self.hexToHsl( val );
                        self.currentHue = hsl.h;
                        self.currentSat = hsl.s;
                        self.currentLight = hsl.l;
                        self.updateUI();
                    }
                }
            } );

            this.hexInput.on( 'input', function() {
                let val = $( this ).val().trim();

                if ( ! val.startsWith( '#' ) ) val = '#' + val;

                const is3Char = /^#[0-9A-F]{3}$/i.test( val );
                const is6Char = /^#[0-9A-F]{6}$/i.test( val );

                if ( is3Char || is6Char ) {
                    const hsl = self.hexToHsl( val );
                    self.currentHue = hsl.h;
                    self.currentSat = hsl.s;
                    self.currentLight = hsl.l;
                    self.draw();
                    self._updateMarkers();
                    self._updateSwatchColors();
                    self.previewSwatch.css( 'background-color', val );

                    // Only commit to the hidden input (and fire the change event that
                    // triggers the contrast check and live preview update) once a full
                    // 6-character hex is typed. The 3-char shorthand only updates the
                    // visual picker state so mid-typing doesn't show premature warnings.
                    if ( is6Char ) {
                        self.element.val( val ).trigger( 'change' );
                        self._notifyAppearanceEditor( self.element, val );
                        self._updateRestoreBtn();
                    }
                }
            } );

            if ( this.restoreBtn ) {
                this.restoreBtn.on( 'click', function( e ) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.restoreDefault();
                } );
            }

            this.closeBtn.on( 'click', function( e ) {
                e.preventDefault();
                e.stopPropagation();
                self.close();
                self.preview.focus();
            } );

            /*
             * Bound once for the page rather than once per picker: every
             * instance used to add its own document handler, so a page of
             * colour fields ran dozens of them on every keystroke and none
             * of them was ever removed.
             */
            if ( ! WPSL_ColorPicker._escBound ) {
                WPSL_ColorPicker._escBound = true;

                $( document ).on( 'keydown.wpsl-color-picker-esc', function( e ) {
                    if ( 'Escape' !== e.key ) {
                        return;
                    }

                    $( '.wpsl-color-field' ).each( function() {
                        const picker = $( this ).data( 'wpsl-color-picker' );

                        if ( picker && picker.state.open ) {
                            picker.close();
                            picker.preview.focus();
                        }
                    } );
                } );
            }
        },

        /**
         * Toggles the picker popup between its open and closed states.
         */
        toggle: function() {
            if ( this.state.open ) {
                this.close();
            } else {
                this.open();
            }
        },

        /**
         * Opens the picker popup, closing any other open pickers first.
         * Calculates available viewport space and positions the popup above or below
         * the preview button accordingly, aligned to the configured side.
         */
        open: function() {
            const self = this;

            $( '.wpsl-color-field' ).each( function() {
                const $field = $( this );
                const picker = $field.data( 'wpsl-color-picker' );

                if ( picker && picker !== self ) {
                    picker.close();
                }
            } );

            this.picker.show();

            const wrapperRect  = this.wrapper[ 0 ].getBoundingClientRect();
            const pickerHeight = this.picker.outerHeight();
            const isRtl        = ( 'rtl' === this.picker.css( 'direction' ) );

            // Below 1024 px the layout stacks and there is no vertical room,
            // so open beside the button instead of above/below it.
            let positionCss = { position: 'absolute' };

            if ( this.options.position === 'beside' ) {
                positionCss.right  = isRtl ? 'auto' : '100%';
                positionCss.left   = isRtl ? '100%' : 'auto';
                positionCss.top    = '0';
                positionCss.bottom = 'auto';
            } else if ( window.innerWidth <= 1024 ) {
                // Left of the button, bottom edges aligned.
                positionCss.right  = isRtl ? 'auto' : '100%';
                positionCss.left   = isRtl ? '100%' : 'auto';
                positionCss.bottom = '0';
                positionCss.top    = 'auto';
            } else {
                const viewportHeight = window.innerHeight;
                const spaceBelow     = viewportHeight - wrapperRect.bottom;
                const spaceAbove     = wrapperRect.top;
                const showAbove      = spaceBelow < pickerHeight && spaceAbove > pickerHeight;

                if ( this.options.position === 'left' ) {
                    positionCss.left  = isRtl ? 'auto' : '0';
                    positionCss.right = isRtl ? '0' : 'auto';
                } else {
                    positionCss.left  = isRtl ? '0' : 'auto';
                    positionCss.right = isRtl ? 'auto' : '0';
                }

                if ( showAbove ) {
                    positionCss.bottom = '100%';
                    positionCss.top    = 'auto';
                } else {
                    positionCss.top    = '100%';
                    positionCss.bottom = 'auto';
                }
            }

            this.picker.css( positionCss );
            this.wrapper.addClass( 'wpsl-active' );
            this.picker.addClass( 'wpsl-active' );
            this.state.open = true;

            this.$outsideHandler = function( e ) {
                // Don't interfere with back button clicks - let them propagate to their handler
                if ( $( e.target ).closest( '.wpsl-back-button' ).length ) {
                    return;
                }
                
                if ( ! $( e.target ).closest( self.wrapper ).length ) {
                    self.close();
                    $( document ).off( 'click.wpsl-color-picker', self.$outsideHandler );
                }
            };

            setTimeout( function() {
                $( document ).on( 'click.wpsl-color-picker', self.$outsideHandler );
            }, 100 );
        },

        /**
         * Hides the picker popup and cleans up the outside-click handler.
         */
        close: function() {
            this.state.open = false;
            this.picker.removeClass( 'wpsl-active' );
            this.wrapper.removeClass( 'wpsl-active' );
            this.picker.hide();

            if ( this.$outsideHandler ) {
                $( document ).off( 'click.wpsl-color-picker', this.$outsideHandler );
                this.$outsideHandler = null;
            }
        },

        /**
         * Handles mouse and touch input on the canvas.
         * 
         * Determines whether the cursor is over the inner hue/saturation wheel or the
         * outer lightness ring, then updates the corresponding state values and schedules
         * a canvas repaint via requestAnimationFrame.
         *
         * @param {MouseEvent|TouchEvent} e - The interaction event.
         */
        handleInput: function( e ) {
            const rect = this.canvas[0].getBoundingClientRect();
            const clientX = e.clientX || ( e.touches && e.touches[0].clientX );
            const clientY = e.clientY || ( e.touches && e.touches[0].clientY );
            const x = clientX - rect.left - this.center;
            const y = clientY - rect.top - this.center;
            const dist = Math.sqrt( x * x + y * y );
            let angle = ( Math.atan2( y, x ) * 180 / Math.PI ) + 90;

            if ( angle < 0 ) angle += 360;

            if ( dist <= this.innerRadius + 4 ) {
                this.currentHue = angle % 360;
                this.currentSat = Math.min( ( dist / this.innerRadius ) * 100, 100 );

                if ( this.currentLight === 100 || this.currentLight === 0 ) {
                    this.currentLight = 50;
                }
            } else if ( dist >= this.innerRadius + 10 ) {
                this.lastSide = ( angle > 180 ) ? 'left' : 'right';

                const diff = Math.abs( 180 - angle );

                this.currentLight = 100 - ( diff / 180 ) * 100;
            } else {
                return;
            }

            this._scheduleFrame();
        },

        /**
         * Main rendering method. Copies the pre-rendered inner wheel from the
         * offscreen cache ( which never changes ), then draws the outer
         * lightness ring as a single conic gradient for the current hue
         * and saturation.
         */
        draw: function() {
            this.ctx.clearRect( 0, 0, this.options.size, this.options.size );

            if ( ! this._innerCache ) {
                this._buildInnerCache();
            }

            this.ctx.drawImage( this._innerCache, 0, 0 );

            const ringGrad = this.ctx.createConicGradient( -Math.PI / 2, this.center, this.center );

            for ( let i = 0; i <= 360; i += 5 ) {
                const diff = Math.abs( 180 - i );
                const l = 100 - ( diff / 180 ) * 100;

                ringGrad.addColorStop( i / 360, `hsl(${this.currentHue},${this.currentSat}%,${l}%)` );
            }

            this.ctx.beginPath();
            this.ctx.arc( this.center, this.center, this.outerRadius, 0, Math.PI * 2 );
            this.ctx.arc( this.center, this.center, this.outerRadius - this.options.ringThickness, 0, Math.PI * 2, true );
            this.ctx.fillStyle = ringGrad;
            this.ctx.fill( 'evenodd' );
        },

        /**
         * Pre-renders the static hue/saturation wheel to an offscreen canvas once
         * on init. Cached in `_innerCache` and copied each draw, avoiding 360
         * gradient stops per frame. A conic hue gradient overlaid with a white
         * radial gradient fading to transparent at the edge.
         */
        _buildInnerCache: function() {
            const offscreen = document.createElement( 'canvas' );
            offscreen.width = this.options.size;
            offscreen.height = this.options.size;

            const ctx = offscreen.getContext( '2d' );
            const conicGrad = ctx.createConicGradient( -Math.PI / 2, this.center, this.center );

            for ( let i = 0; i <= 360; i += 5 ) {
                conicGrad.addColorStop( i / 360, `hsl(${i}, 100%, 50%)` );
            }

            ctx.beginPath();
            ctx.arc( this.center, this.center, this.innerRadius, 0, Math.PI * 2 );
            ctx.fillStyle = conicGrad;
            ctx.fill();

            const satGrad = ctx.createRadialGradient( this.center, this.center, 0, this.center, this.center, this.innerRadius );
            
            satGrad.addColorStop( 0, 'white' );
            satGrad.addColorStop( 1, 'rgba(255,255,255,0)' );

            ctx.fillStyle = satGrad;
            ctx.fill();

            this._innerCache = offscreen;
        },

        /**
         * Coalesces rapid state changes into a single rAF repaint. Calls before
         * the frame fires are ignored, so the canvas paints at most once per frame.
         */
        _scheduleFrame: function() {
            if ( this._rafId ) {
                return;
            }

            const self = this;

            this._rafId = requestAnimationFrame( function() {
                self._rafId = null;
                self._fastUpdate();
            } );
        },

        /**
         * Full UI sync from the rAF callback: redraws canvas, repositions markers,
         * updates swatch, hex, hidden input, and swatch strip with no delay.
         */
        _fastUpdate: function() {
            this.draw();
            this._updateMarkers();

            const hex = this.hslToHex( this.currentHue, this.currentSat, this.currentLight );

            this.previewSwatch.css( 'background-color', hex );
            this.hexInput.val( hex.toUpperCase() );

            const $currentInput = $( this.element );

            $currentInput.val( hex );

            // The preview repaints on the frame: this only writes a few CSS
            // variables ( or the gradient preview, whose inputs are resolved
            // once ), and delaying it is the difference between a picker that
            // tracks the pointer and one that lags behind it.
            this._notifyAppearanceEditor( $currentInput, hex );

            /*
             * Announced on the frame, not after the drag: this event is what
             * the map shapes editor repaints a shape from, and anything else
             * that wants a live colour. The one listener this is expensive for
             * -- the contrast pass -- debounces itself.
             */
            $currentInput.trigger( 'change' );

            this._updateSwatchColors();
            this._updateRestoreBtn();
        },


        /**
         * Notifies appearanceEditor of a color change when the picker is embedded
         * in an element with a `data-elem` attribute. Handles gradient and plain inputs.
         *
         * @param {jQuery} $input - The hidden color input element.
         * @param {string} hex    - The new hex color value (e.g. '#ff0000').
         */
        _notifyAppearanceEditor: function( $input, hex ) {
            if ( ! window.appearanceEditor || ! window.appearanceEditor.updateStyles ) {
                return;
            }

            let $parent = $input.closest( 'li[data-elem]' );
            if ( ! $parent.length ) $parent = $input.closest( 'p[data-elem]' );
            if ( ! $parent.length ) $parent = $input.closest( '[data-elem]' );

            const elem = $parent.data( 'elem' );
            if ( ! elem ) {
                return;
            }

            const buttonType = window.appearanceEditor.helpers.getButtonTypeFromInput( this.element );
            if ( buttonType ) {
                window.appearanceEditor.updateGradientPreview( buttonType );
            } else {
                window.appearanceEditor.updateStyles( hex, elem, 'update' );
            }
        },

        /**
         * Repositions the inner hue/saturation marker and the outer lightness marker
         * based on the current HSL state. Uses polar-to-Cartesian conversion so the
         * markers always track the correct point on each wheel/ring.
         */
        _updateMarkers: function() {
            const wheelRads = ( this.currentHue - 90 ) * ( Math.PI / 180 );
            const dist = ( this.currentSat / 100 ) * this.innerRadius;

            this.markerInner.css( {
                left: `${this.center + dist * Math.cos( wheelRads )}px`,
                top: `${this.center + dist * Math.sin( wheelRads )}px`
            } );

            let lightAngle = ( this.currentLight / 100 ) * 180;

            if ( this.lastSide === 'left' ) lightAngle = 360 - lightAngle;

            const ringRads = ( lightAngle - 90 ) * ( Math.PI / 180 );
            const rd = this.outerRadius - ( this.options.ringThickness / 2 );

            this.markerOuter.css( {
                left: `${this.center + rd * Math.cos( ringRads )}px`,
                top: `${this.center + rd * Math.sin( ringRads )}px`
            } );
        },

        /**
         * Recolors the 10 lightness swatch elements to reflect the current hue and
         * saturation at evenly distributed lightness steps (9% through 90%).
         */
        _updateSwatchColors: function() {
            const swatches = this.swatchBox.children();

            for ( let i = 0; i < 10; i++ ) {
                const hex = this.hslToHex( this.currentHue, this.currentSat, ( i + 1 ) * 9 );

                swatches.eq( i )
                    .css( 'background-color', hex )
                    .attr( 'aria-label', hex.toUpperCase() );
            }
        },

        _updateRestoreBtn: function() {
            const currentHex = this.hslToHex( this.currentHue, this.currentSat, this.currentLight ).toUpperCase();

            if ( this.defaultColor ) {
                const defaultHex = this._expandHex( this.defaultColor ).toUpperCase();
                this.restoreBtn.toggle( currentHex !== defaultHex );
            } else {
                this.restoreBtn.toggle( !! this.element.val() );
            }
        },

        _expandHex: function( hex ) {
            if ( hex && hex.length === 4 ) {
                return '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
            }
            return hex;
        },

        /**
         * Syncs the picker's full visual state to the current HSL values: canvas,
         * swatch, hex, hidden input, markers, and swatch strip. Notifies the
         * appearance editor unless `skipInput` is true ( mid-typing in the hex field ).
         *
         * @param {boolean} [skipInput=false] - When true, skips writing back to the hidden input and appearance editor.
         */
        updateUI: function( skipInput ) {
            this.draw();

            const hex = this.hslToHex( this.currentHue, this.currentSat, this.currentLight );

            if ( ! skipInput ) {
                const $currentInput = $( this.element );

                $currentInput.val( hex ).trigger( 'change' );
                this._notifyAppearanceEditor( $currentInput, hex );
            }

            this.previewSwatch.css( 'background-color', hex );
            this.hexInput.val( hex.toUpperCase() );
            this._updateMarkers();
            this._updateSwatchColors();
            this._updateRestoreBtn();
        },

        /**
         * Restores the picker to the default color defined in the data-default attribute.
         */
        restoreDefault: function() {
            if ( this.defaultColor ) {
                const hsl = this.hexToHsl( this.defaultColor );
                this.currentHue = hsl.h;
                this.currentSat = hsl.s;
                this.currentLight = hsl.l;
                this.updateUI();
            } else {
                this.clearColor();
                this.element.trigger( 'change' );
                this._notifyAppearanceEditor( this.element, '' );
            }
        },

        /**
         * Resets the picker to an empty, no-color state without triggering change events.
         * Clears the hidden input value and shows a transparent preview swatch.
         */
        clearColor: function() {
            this.currentHue = 0;
            this.currentSat = 0;
            this.currentLight = 50;
            this.element.val( '' );
            this.draw();
            this._updateMarkers();
            this._updateSwatchColors();
            this.previewSwatch.css( 'background-color', 'transparent' );
            this.hexInput.val( '' );
            this._updateRestoreBtn();
        },

        /**
         * Converts HSL values to a hex color string.
         *
         * @param {number} h - Hue (0–360).
         * @param {number} s - Saturation (0–100).
         * @param {number} l - Lightness (0–100).
         * @returns {string} The hex color string (e.g. '#ff0000').
         */
        hslToHex: function( h, s, l ) {
            l /= 100;

            const a = s * Math.min( l, 1 - l ) / 100;

            const f = function( n ) {
                const k = ( n + h / 30 ) % 12;
                const color = l - a * Math.max( Math.min( k - 3, 9 - k, 1 ), -1 );

                return Math.round( 255 * color ).toString( 16 ).padStart( 2, '0' );
            };

            return `#${f( 0 )}${f( 8 )}${f( 4 )}`;
        },

        /**
         * Converts a hex color string to HSL values.
         * Supports both 3-character shorthand (e.g. '#fff') and full 6-character hex.
         *
         * @param {string} hex - The hex color string (e.g. '#00ff00').
         * @returns {{ h: number, s: number, l: number }} Object with hue (0–360), saturation (0–100), and lightness (0–100).
         */
        hexToHsl: function( hex ) {
            let r = 0, g = 0, b = 0;

            if ( hex.length === 4 ) {
                r = parseInt( hex[1] + hex[1], 16 );
                g = parseInt( hex[2] + hex[2], 16 );
                b = parseInt( hex[3] + hex[3], 16 );
            } else {
                r = parseInt( hex.substring( 1, 3 ), 16 );
                g = parseInt( hex.substring( 3, 5 ), 16 );
                b = parseInt( hex.substring( 5, 7 ), 16 );
            }

            r /= 255; g /= 255; b /= 255;

            const max = Math.max( r, g, b );
            const min = Math.min( r, g, b );
            const l = ( max + min ) / 2;
            let h, s;

            if ( max === min ) {
                h = s = 0;
            } else {
                const d = max - min;
                s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );

                switch ( max ) {
                    case r: h = ( g - b ) / d + ( g < b ? 6 : 0 ); break;
                    case g: h = ( b - r ) / d + 2; break;
                    case b: h = ( r - g ) / d + 4; break;
                }

                h /= 6;
            }

            return { h: h * 360, s: s * 100, l: l * 100 };
        }
    };

    /**
     * jQuery plugin wrapper. Initializes a WPSL_ColorPicker on each matched element,
     * skipping any that have already been initialized.
     *
     * @param {Object} options - Options passed through to WPSL_ColorPicker.
     * @returns {jQuery}
     */
    $.fn.wpslColorPicker = function( options ) {
        return this.each( function() {
            if ( ! $( this ).data( 'wpsl-color-picker' ) ) {
                const picker = new WPSL_ColorPicker( this, options );
                
                $( this ).data( 'wpsl-color-picker', picker );
            }
        } );
    };

    $( document ).ready( function() {
        $( '.wpsl-color-field' ).each( function() {
            const $input = $( this );

            if ( $input.data( 'wpsl-color-picker' ) ) return;
            if ( $input.data( 'skip-picker' ) ) return;

            var position = $input.data( 'picker-position' ) || 'right';
            $input.wpslColorPicker( { position: position } );
        } );
    } );

} )( jQuery );