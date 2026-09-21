/**
 * Button styles management for the appearance section.
 *
 * @since 3.0.0
 */
export const buttonStyles = {
    /**
     * Reference to parent appearance object.
     */
    parent: null,

    /**
     * Initialize button styles module.
     *
     * @since 3.0.0
     * @param {object} parentAppearance - Reference to main appearance object
     */
    init( parentAppearance ) {
        this.parent = parentAppearance;
    },

    /**
     * Shared CTA button properties.
     *
     * @since 3.0.0
     */
    ctaProperties: [
        'background_start',
        'background_end',
        'background_start_hover',
        'background_end_hover',
        'text',
        'text_hover',
        'border',
        'border_hover',
        'angle'
    ],

    /**
     * Helper functions for button operations.
     *
     * @since 3.0.0
     */
    helpers: {
        /**
         * Generate a linear gradient CSS string.
         *
         * @since   3.0.0
         * @param   {number} angle - Gradient angle in degrees
         * @param   {string} startColor - Start color
         * @param   {string} endColor - End color
         * @returns {string} - Linear gradient CSS string
         */
        createGradient( angle, startColor, endColor ) {
            if ( startColor && endColor ) {
                return `linear-gradient(${angle}deg, ${startColor} 0%, ${endColor} 100%)`;
            }

            return startColor || endColor || '';
        },

        /**
         * Set angle for a button type.
         *
         * @since 3.0.0
         * @param {string} buttonType - Button type identifier
         * @param {number} angle - Angle value
         */
        setAngle( buttonType, angle ) {
            jQuery( `.wpsl-gradient-angle-input[data-button-type="${buttonType}"]` ).val( angle );
            jQuery( `.wpsl-angle-picker[data-button-type="${buttonType}"] .wpsl-angle-picker-dot` ).css( 'transform', `rotate(${angle}deg)` );
        },

        /**
         * Apply CSS variables to button elements.
         *
         * @since 3.0.0
         * @param {string} buttonType - Button type identifier
         * @param {object} buttonMap - Map of button types to selectors
         * @param {array}  properties - Array of property names
         */
        applyCSSVars( buttonType, buttonMap, properties ) {
            if ( ! buttonMap[ buttonType ] ) {
                return;
            }

            const $buttons = jQuery( buttonMap[ buttonType ] );

            if ( ! $buttons.length ) {
                return;
            }

            const cssVars = {};

            properties.forEach( function( property ) {
                const inputName = `wpsl_appearance[theme_colors][${buttonType}_${property}]`;
                const $input = jQuery( `[name="${inputName}"]` );

                if ( $input.length ) {
                    const cssVarName = `--${buttonType.replace(/_/g, '-')}-${property.replace(/_/g, '-')}`;
                    let value = $input.val();

                    if ( ! value || value === '' ) {
                        const defaultKey = `${buttonType.replace(/_/g, '-')}-${property.replace(/_/g, '-')}`;
                        value = wpslDefaultStyles[ defaultKey ];
                    }

                    if ( value !== undefined && value !== null && value !== '' ) {
                        if ( property === 'angle' ) {
                            value += 'deg';
                        }

                        cssVars[ cssVarName ] = value;
                    }
                }
            });

            $buttons.css( cssVars );
        },

        /**
         * Get color values and defaults for gradient preview.
         *
         * @since   3.0.0
         * @param   {object} inputs - Object containing jQuery input elements
         * @param   {string} cssVarPrefix - CSS variable prefix
         * @param   {string} buttonType - Button type for suffix determination
         * @param   {function} getColorValue - Function to extract color from input
         * @returns {object} - Object with colors and defaults
         */
        getGradientColors( inputs, cssVarPrefix, buttonType, getColorValue ) {
            const borderSuffix = ( buttonType === 'submit' ) ? '-border' : '-border-color';
            const textSuffix = ( buttonType === 'submit' ) ? '-text' : '-text-color';

            return {
                colors: {
                    start: getColorValue( inputs.colorStart ),
                    end: getColorValue( inputs.colorEnd ),
                    border: getColorValue( inputs.border ),
                    text: getColorValue( inputs.text ),
                    startHover: getColorValue( inputs.colorStartHover ),
                    endHover: getColorValue( inputs.colorEndHover ),
                    borderHover: getColorValue( inputs.borderHover ),
                    textHover: getColorValue( inputs.textHover )
                },
                defaults: {
                    start: wpslDefaultStyles[ cssVarPrefix + '-background-start' ] || '',
                    end: wpslDefaultStyles[ cssVarPrefix + '-background-end' ] || '',
                    startHover: wpslDefaultStyles[ cssVarPrefix + '-background-start-hover' ] || '',
                    endHover: wpslDefaultStyles[ cssVarPrefix + '-background-end-hover' ] || '',
                    border: wpslDefaultStyles[ cssVarPrefix + borderSuffix ] || '',
                    borderHover: wpslDefaultStyles[ cssVarPrefix + borderSuffix + '-hover' ] || '',
                    text: wpslDefaultStyles[ cssVarPrefix + textSuffix ] || '',
                    textHover: wpslDefaultStyles[ cssVarPrefix + textSuffix + '-hover' ] || ''
                },
                suffixes: {
                    border: borderSuffix,
                    text: textSuffix
                }
            };
        }
    },

    /**
     * Apply default submit button styles.
     *
     * @since 3.0.0
     */
    applyDefaultSubmitStyles() {
        jQuery( '#wpsl-search-btn' ).css({
            'background': 'linear-gradient(180deg, #f0f0f0 0%, #e0e0e0 100%)',
            'color': '#000',
            'border': '1px solid ' + '#e2e2e2'
        });
    },

    /**
     * Reset button styles to their default values.
     *
     * @since 3.0.0
     */
    resetButtonStyles() {
        const styleElement = document.getElementById( 'wpsl-admin-inline-css' );

        // Update color picker fields (same pattern as resetThemeStyles)
        jQuery( '#wpsl-button-styles-tab li[data-elem]' ).each( function() {
            const elemId = jQuery( this ).data( 'elem' );
            const defaultColor = wpslDefaultStyles[ elemId ];
            const $colorField = jQuery( this ).find( '.wpsl-color-field' );

            if ( defaultColor !== undefined ) {
                $colorField.val( defaultColor || '' );

                const picker = $colorField.data( 'wpsl-color-picker' );

                if ( picker ) {
                    if ( defaultColor ) {
                        const hsl = picker.hexToHsl( defaultColor );
                        picker.currentHue = hsl.h;
                        picker.currentSat = hsl.s;
                        picker.currentLight = hsl.l;
                        picker.updateUI( true );
                    } else {
                        picker.clearColor();
                    }
                }
            }

        });

        // Update only button-related CSS variables in the :root block
        if ( styleElement ) {
            const buttonPattern = /^(submit-|general-primary-|general-secondary-)|-cta-/;
            let cssContent = styleElement.textContent;

            // Extract current :root content
            const rootMatch = cssContent.match( /:root\s*{([^}]*)}/ );

            if ( rootMatch ) {
                let rootContent = rootMatch[1];

                // Replace each button CSS variable with its default value
                Object.entries( wpslDefaultStyles ).forEach( ( [key, value] ) => {
                    if ( buttonPattern.test( key ) ) {
                        const regex = new RegExp( `--${key}:\\s*[^;]+;`, 'g' );

                        if ( rootContent.match( regex ) ) {
                            rootContent = rootContent.replace( regex, `--${key}: ${value};` );
                        } else {
                            rootContent += `\n    --${key}: ${value};`;
                        }
                    }
                });

                cssContent = cssContent.replace( /:root\s*{[^}]*}/, `:root {${rootContent}}` );
                styleElement.textContent = cssContent.trim();
            }
        }

        // Remove button-related inline styles from both wrappers
        const buttonCssVarPattern = /^--(submit-|general-primary-|general-secondary-)|^--.*-cta-/;

        [ 'wpsl-content-wrap', 'wpsl-wrap' ].forEach( id => {
            const el = document.getElementById( id );

            if ( ! el ) return;

            const propsToRemove = [];

            for ( let i = 0; i < el.style.length; i++ ) {
                if ( buttonCssVarPattern.test( el.style[i] ) ) {
                    propsToRemove.push( el.style[i] );
                }
            }

            propsToRemove.forEach( prop => el.style.removeProperty( prop ) );
        } );

        jQuery( '#wpsl-wrap .wpsl-details, #wpsl-wrap .wpsl-directions' ).removeAttr( 'style' );
        jQuery( '.wpsl-info-window .wpsl-details, .wpsl-info-window .wpsl-directions' ).removeAttr( 'style' );

        // Remove inline styles from preview buttons (except submit buttons — handled below)
        jQuery( '.wpsl-preview-btn:not(.wpsl-preview-submit, .wpsl-preview-vertical-submit)' ).removeAttr( 'style' );
        jQuery( '.wpsl-preview-primary, .wpsl-preview-secondary' ).removeAttr( 'style' );

        const ctaEnabled = jQuery( '#wpsl-cta-buttons' ).is( ':checked' );

        if ( ctaEnabled ) {
            const defaultButtonStyles = {
                'more_details': 'primary',
                'directions': 'secondary',
                'zoom_here': 'secondary',
                'share_location': 'primary',
                'no_thanks': 'secondary',
                'streetview': 'secondary'
            };

            jQuery.each( defaultButtonStyles, function( buttonName, defaultStyle ) {
                jQuery( 'input[name="wpsl_appearance[button_styles][' + buttonName + ']"][value="' + defaultStyle + '"]' ).prop( 'checked', true ).trigger( 'change' );
            });
        }

        // Reset angle pickers
        const angles = {
            'general_primary': wpslDefaultStyles['general-primary-angle'] || 180,
            'general_secondary': wpslDefaultStyles['general-secondary-angle'] || 180,
            'submit': wpslDefaultStyles['submit-angle'] || 180
        };

        Object.entries( angles ).forEach( ( [buttonType, angle] ) => {
            this.helpers.setAngle( buttonType, angle );
        });

        this.updateGradientPreview( 'submit' );

        if ( ctaEnabled ) {
            this.updateGradientPreview( 'general_primary' );
            this.updateGradientPreview( 'general_secondary' );
        }

        // Restore correct submit button visibility based on active template
        const isVerticalTemplate = jQuery( '#wpsl-appearance-preview #wpsl-wrap' ).hasClass( 'wpsl-vertical-template' );

        if ( isVerticalTemplate ) {
            jQuery( '.wpsl-preview-vertical-submit' ).show().css( { 'border': 'none', 'border-color': 'transparent' } );
            jQuery( '.wpsl-preview-submit' ).hide();
        } else {
            jQuery( '.wpsl-preview-submit' ).show();
            jQuery( '.wpsl-preview-vertical-submit' ).hide().css( { 'border': '', 'border-color': '' } );
        }

        // updateUI ran with skipInput, so the fields above never fired 'change'
        // and the contrast warnings still describe the replaced colors.
        this.parent.contrast.checkAll();
    },

    /**
     * Update angle from mouse position on circular picker.
     *
     * @since 3.0.0
     * @param {Event} e - Mouse event
     * @param {jQuery} $picker - The angle picker element
     * @param {DOMRect} [pickerRect] - The picker's box, measured once by a drag
     *                                 that is about to call this per frame.
     */
    updateAngleFromMouse( e, $picker, pickerRect ) {
        const rect = pickerRect || $picker[0].getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const deltaX = e.clientX - centerX;
        const deltaY = e.clientY - centerY;

        let angle = Math.atan2( deltaX, -deltaY ) * (180 / Math.PI);

        if ( angle < 0 ) {
            angle += 360;
        }

        angle = Math.round( angle );

        const buttonType = $picker.data( 'button-type' );

        $picker.find( '.wpsl-angle-picker-dot' ).css( 'transform', 'rotate(' + angle + 'deg)' );
        jQuery( '.wpsl-gradient-angle-input[data-button-type="' + buttonType + '"]' ).val( angle );

        this.updateGradientPreview( buttonType );
    },

    /**
     * The inputs one button type's gradient preview reads.
     *
     * Resolved once per button type and kept: the eight substring-matched
     * lookups run on every frame of a colour or angle drag, and the inputs
     * they find never change after the page has loaded.
     *
     * @since 3.0.0
     * @param {string} buttonType - Either 'primary', 'secondary', or 'header-submit'
     * @return {object} The selector prefixes and the resolved inputs.
     */
    gradientInputs( buttonType ) {
        this._gradientInputs = this._gradientInputs || {};

        if ( this._gradientInputs[ buttonType ] ) {
            return this._gradientInputs[ buttonType ];
        }

        let selectorPrefix, cssVarPrefix;

        if ( buttonType === 'submit' ) {
            selectorPrefix = '.wpsl-submit-options';
            cssVarPrefix = 'submit';
        } else if ( buttonType.indexOf( '_cta_' ) !== -1 ) {
            const parts = buttonType.split( '_' );
            const parent = parts[0];
            const ctaType = parts.slice(2).join( '_' );

            selectorPrefix = '.wpsl-' + parent + '-cta-' + ctaType.replace(/_/g, '-') + '-options';
            cssVarPrefix = buttonType.replace(/_/g, '-');
        } else {
            const selectorName = buttonType.replace(/_/g, '-');
            selectorPrefix = '.wpsl-' + selectorName + '-options';
            cssVarPrefix = selectorName;
        }

        this._gradientInputs[ buttonType ] = {
            selectorPrefix: selectorPrefix,
            cssVarPrefix:   cssVarPrefix,
            $angle:                jQuery( '.wpsl-gradient-angle-input[data-button-type="' + buttonType + '"]' ),
            $colorStartInput:      jQuery( selectorPrefix + ' input[name*="background_start"]' ).not( '[name*="hover"]' ),
            $colorEndInput:        jQuery( selectorPrefix + ' input[name*="background_end"]' ).not( '[name*="hover"]' ),
            $borderColorInput:     jQuery( selectorPrefix + ' input[name*="border"]' ).not( '[name*="hover"]' ),
            $textColorInput:       jQuery( selectorPrefix + ' input[name*="text"]' ).not( '[name*="hover"]' ),
            $colorStartHoverInput: jQuery( selectorPrefix + ' input[name*="background_start_hover"]' ),
            $colorEndHoverInput:   jQuery( selectorPrefix + ' input[name*="background_end_hover"]' ),
            $borderColorHoverInput: jQuery( selectorPrefix + ' input[name*="border"][name*="hover"]' ),
            $textColorHoverInput:  jQuery( selectorPrefix + ' input[name*="text"][name*="hover"]' )
        };

        return this._gradientInputs[ buttonType ];
    },

    /**
     * Update gradient preview for primary, secondary, or header-submit button.
     *
     * @since 3.0.0
     * @param {string} buttonType - Either 'primary', 'secondary', or 'header-submit'
     */
    updateGradientPreview( buttonType ) {
        const $contentWrap = jQuery( '#wpsl-content-wrap' );

        const {
            selectorPrefix,
            cssVarPrefix,
            $angle,
            $colorStartInput,
            $colorEndInput,
            $borderColorInput,
            $textColorInput,
            $colorStartHoverInput,
            $colorEndHoverInput,
            $borderColorHoverInput,
            $textColorHoverInput
        } = this.gradientInputs( buttonType );

        const angle = $angle.val() || 180;

        const getColorValue = ( $input ) => {
            if ( ! $input.length ) return '';
            return $input.val() || '';
        };

        const { colors, defaults, suffixes } = this.helpers.getGradientColors({
            colorStart: $colorStartInput,
            colorEnd: $colorEndInput,
            border: $borderColorInput,
            text: $textColorInput,
            colorStartHover: $colorStartHoverInput,
            colorEndHover: $colorEndHoverInput,
            borderHover: $borderColorHoverInput,
            textHover: $textColorHoverInput
        }, cssVarPrefix, buttonType, getColorValue );

        const isCTAButton = buttonType.indexOf( '_cta_' ) !== -1;

        const gradient = this.helpers.createGradient( angle, colors.start, colors.end )
            || this.helpers.createGradient( angle, defaults.start, defaults.end );
        const gradientHover = this.helpers.createGradient( angle, colors.startHover, colors.endHover )
            || this.helpers.createGradient( angle, defaults.startHover, defaults.endHover );

        // Set vars on #wpsl-content-wrap — the common parent of both the sidebar
        // (#wpsl-appearance-nav) and the preview panel (#wpsl-appearance-preview).
        $contentWrap.css( '--wpsl-' + cssVarPrefix + '-gradient', gradient );
        $contentWrap.css( '--wpsl-' + cssVarPrefix + '-gradient-hover', gradientHover );
        $contentWrap.css( '--wpsl-' + cssVarPrefix + suffixes.border, colors.border || defaults.border );
        $contentWrap.css( '--wpsl-' + cssVarPrefix + suffixes.border + '-hover', colors.borderHover || defaults.borderHover );
        $contentWrap.css( '--wpsl-' + cssVarPrefix + suffixes.text, colors.text || defaults.text );
        $contentWrap.css( '--wpsl-' + cssVarPrefix + suffixes.text + '-hover', colors.textHover || defaults.textHover );

        if ( ! isCTAButton ) {
            $contentWrap.css( '--wpsl-' + cssVarPrefix + '-background', gradient );
            $contentWrap.css( '--wpsl-' + cssVarPrefix + '-background-hover', gradientHover );
        }

        if ( isCTAButton ) {
            this.applyCTAButtonVars( buttonType );
            this.applyCTAPreviewButtonVars( buttonType );
        }
    },

    /**
     * Apply CTA button CSS variables directly to button elements.
     *
     * @since 3.0.0
     * @param {string} specificButtonType - Optional. If provided, only update this button type
     */
    applyCTAButtonVars( specificButtonType ) {
        const buttonMap = {
            'listing_cta_more_details': '#wpsl-stores .wpsl-details',
            'listing_cta_directions': '#wpsl-stores .wpsl-directions',
            'popup_cta_more_details': '.wpsl-info-window .wpsl-details',
            'popup_cta_directions': '.wpsl-info-window .wpsl-directions'
        };

        const buttonTypesToProcess = specificButtonType ? [specificButtonType] : Object.keys( buttonMap );
        const self = this;

        buttonTypesToProcess.forEach( function( buttonType ) {
            self.helpers.applyCSSVars( buttonType, buttonMap, self.ctaProperties );
        });
    },

    /**
     * Apply CTA button CSS variables directly to preview button elements.
     *
     * @since 3.0.0
     * @param {string} specificButtonType - Optional. If provided, only update this button type
     */
    applyCTAPreviewButtonVars( specificButtonType ) {
        const previewButtonMap = {
            'listing_cta_more_details': '.wpsl-preview-listing_cta_more_details',
            'listing_cta_directions': '.wpsl-preview-listing_cta_directions',
            'popup_cta_more_details': '.wpsl-preview-popup_cta_more_details',
            'popup_cta_directions': '.wpsl-preview-popup_cta_directions'
        };

        const buttonTypesToProcess = specificButtonType ? [specificButtonType] : Object.keys( previewButtonMap );
        const self = this;

        buttonTypesToProcess.forEach( function( buttonType ) {
            self.helpers.applyCSSVars( buttonType, previewButtonMap, self.ctaProperties );
        });
    }
};