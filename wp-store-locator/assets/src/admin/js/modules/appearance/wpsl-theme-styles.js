import { state } from '../wpsl-shared.js';

/**
 * Theme styles management for the appearance section.
 *
 * @since 3.0.0
 */
export const themeStyles = {
    parent: null,

    popupElements: [
        'popup-content-background',
        'popup-content-text',
        'popup-content-link',
        'popup-content-link-hover',
        'popup-content-close',
        'popup-content-close-hover',
        'popup-icons-color'
    ],

    elementConfig: {
        'header-container-background': {
            selector: '.wpsl-search'
        },
        'header-container-text': {
            selector: '.wpsl-search'
        },
        'header-input-background': {
            getConfig: ( parent ) => ({
                selector: parent.activeTemplateId === 'vertical'
                    ? '.wpsl-search-wrap'
                    : '#wpsl-search-input',
                hasHover: true
            })
        },
        'header-input-text': {
            selector: '#wpsl-search-input',
            hasHover: true
        },
        'header-input-border': {
            getConfig: ( parent ) => ({
                selector: parent.activeTemplateId === 'vertical'
                    ? '.wpsl-search-wrap'
                    : '#wpsl-search-input',
                hasHover: true
            })
        },
        'header-dropdown-background': {
            getConfig: ( parent ) => ({
                selector: parent.activeTemplateId === 'vertical'
                    ? '#wpsl-result-filters button'
                    : '#wpsl-search-wrap select',
                hasHover: true
            })
        }
    },

    /**
     * Initialize theme styles module.
     *
     * @since 3.0.0
     * @param {object} parentAppearance - Reference to main appearance object
     */
    init( parentAppearance ) {
        this.parent = parentAppearance;
    },

    /**
     * Make sure the first tab, and corresponding content container is visible.
     *
     * @since 3.0.0
     */
    selectThemeStyleTab() {
        jQuery( '.ui-tabs-panel' ).not( ':first' ).hide();
        jQuery( '#wpsl-theme-customization-tabs li.ui-tabs-tab:first-child a' ).trigger( 'click' );
    },

    /**
     * Remove root styles and set default values.
     *
     * @since 3.0.0
     */
    removeRootStyles() {
        const styleElement = document.getElementById( 'wpsl-admin-inline-css' );

        if ( styleElement ) {
            let cssContent = styleElement.textContent;

            // Only reset theme colors, not button colors
            const buttonPattern = /^(submit-|general-primary-|general-secondary-)|-cta-/;
            const themeOnlyStyles = Object.entries( wpslDefaultStyles ).filter( ( [key] ) => ! buttonPattern.test( key ) );

            const newRootContent = `:root {
                ${ themeOnlyStyles.map( ( [key, value] ) => `--wpsl-${ key }: ${ value };` ).join( '\n' ) }
            }`;

            cssContent = cssContent.replace( /:root\s*{[^}]*}/, newRootContent );
            styleElement.textContent = cssContent.trim();

            jQuery( '#wpsl-wrap' ).removeAttr( 'style' );
            jQuery( '#wpsl-wrap .wpsl-details, #wpsl-wrap .wpsl-directions' ).removeAttr( 'style' );
            jQuery( '.wpsl-info-window .wpsl-details, .wpsl-info-window .wpsl-directions' ).removeAttr( 'style' );
        }
    },

    /**
     * Reset theme styles to their default values.
     *
     * @since 3.0.0
     */
    resetThemeStyles() {
        const self = this;

        jQuery( '#wpsl-theme-styles-tab li' ).each( function() {
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

            if ( self.parent.hasMap ) {
                self.updateStyles( defaultColor, elemId, 'remove' );
            }
        } );

        this.removeRootStyles();
        this.parent.contrast.checkAll();
    },

    /**
     * Apply initial colors from saved settings.
     *
     * @since 3.0.0
     */
    applyInitialColors() {
        const self = this;

        if ( ! jQuery( '#wpsl-wrap' ).length ) {
            return;
        }

        // When the overwrite toggle is off, Theme_Styles falls back to defaults
        // and the colour fields keep their saved values for restore. The preview
        // skips them too, or it repaints colours the frontend just dropped.
        const overwriteThemeStyles = jQuery( '#wpsl-overwrite-theme-styles' ).is( ':checked' );

        jQuery( '.wpsl-color-field' ).each( function() {
            const $input = jQuery( this );

            if ( $input.hasClass( 'wpsl-gradient-color-field' ) ) {
                return;
            }

            if ( ! overwriteThemeStyles && $input.closest( '#wpsl-theme-styles-tab' ).length ) {
                return;
            }

            const color = $input.val();
            const $parent = $input.parents( 'li[data-elem], p[data-elem]' );
            const elem = $parent.data( 'elem' );

            // Skip focus-outline when the custom toggle is off, preserving the
            // browser/theme default ring in the preview.
            if ( elem === 'focus-outline' && ! jQuery( '#wpsl-custom-focus-outline' ).is( ':checked' ) ) {
                return;
            }

            if ( color && elem ) {
                self.updateStyles( color, elem, 'update' );
            }
        } );

        const { buttons } = self.parent;

        buttons.updateGradientPreview( 'submit' );
        buttons.updateGradientPreview( 'general_primary' );
        buttons.updateGradientPreview( 'general_secondary' );
        buttons.updateGradientPreview( 'listing_cta_more_details' );
        buttons.updateGradientPreview( 'listing_cta_directions' );
        buttons.updateGradientPreview( 'popup_cta_more_details' );
        buttons.updateGradientPreview( 'popup_cta_directions' );
    },

    /**
     * Update styles for a specific element via CSS variables.
     *
     * @since 3.0.0
     * @param {string} color  - Hex color value
     * @param {string} elem   - Element identifier matching a data-elem attribute
     * @param {string} action - 'update' or 'remove'
     */
    updateStyles( color, elem, action ) {
        if ( ! jQuery( '.wpsl-styled-template-preview' ).length ) {
            return;
        }

        if ( ! elem ) {
            return;
        }

        const styleElement = document.getElementById( 'wpsl-wrap' );

        if ( ! styleElement ) {
            return;
        }

        if ( this.popupElements.includes( elem ) ) {
            this.updateInfoWindowColor( color, elem, action );
            return;
        }

        const isHover = elem.endsWith( '-hover' );
        let configId = isHover ? elem.slice( 0, -6 ) : elem;
        let config = this.elementConfig[ configId ];

        if ( config && typeof config.getConfig === 'function' ) {
            config = config.getConfig( this.parent );
        }

        const cssVarName = '--wpsl-' + elem;

        if ( elem === 'focus-outline' ) {
            if ( color ) {
                styleElement.style.setProperty( '--wpsl-focus-outline', '2px solid ' + color );
                styleElement.style.setProperty( '--wpsl-focus-outline-color', color );
                styleElement.style.setProperty( '--wpsl-focus-outline-button', '2px solid ' + color );
            } else {
                styleElement.style.setProperty( '--wpsl-focus-outline', 'initial' );
                styleElement.style.setProperty( '--wpsl-focus-outline-color', 'initial' );
                styleElement.style.setProperty( '--wpsl-focus-outline-button', 'initial' );
            }
            return;
        }

        if ( color ) {
            styleElement.style.setProperty( cssVarName, color );
        } else {
            const styleName = isHover ? configId + '-hover' : elem;
            const defaultValue = wpslDefaultStyles[ styleName ];

            styleElement.style.setProperty( cssVarName, defaultValue || 'transparent' );
        }
    },

    /**
     * Update info window / popup colors via injected style tags.
     *
     * @since 3.0.0
     * @param {string} color  - Hex color value
     * @param {string} elem   - Element identifier
     * @param {string} action - 'update' or 'remove'
     */
    updateInfoWindowColor( color, elem, action ) {
        if ( action === 'update' ) {
            let styleElement = document.getElementById( `wpsl-dynamic-${ elem }` );

            if ( ! styleElement ) {
                styleElement = document.createElement( 'style' );
                styleElement.id = `wpsl-dynamic-${ elem }`;
                document.head.appendChild( styleElement );
            }

            if ( state.mapService === 'gmaps' ) {
                const gmapsStyles = {
                    'popup-content-background': `
                        #wpsl-wrap .gm-style-iw-d,
                        #wpsl-wrap .gm-style .gm-style-iw-c,
                        #wpsl-wrap .gm-style .gm-style-iw-t::after,
                        #wpsl-wrap .gm-style .gm-style-iw-tc::after,
                        #wpsl-wrap .gm-style .gm-style-iw-d::-webkit-scrollbar-track,
                        #wpsl-wrap .gm-style .gm-style-iw-d::-webkit-scrollbar-track-piece {
                            background: ${ color } !important;
                        }
                    `,
                    'popup-content-text': `#wpsl-wrap .gm-style-iw-d { color: ${ color } !important; }`,
                    'popup-content-link': `#wpsl-wrap .gm-style-iw-d a:not(.wpsl-styled-btn) { color: ${ color } !important; }`,
                    'popup-content-link-hover': `#wpsl-wrap .gm-style-iw-d a:not(.wpsl-styled-btn):hover { color: ${ color } !important; }`,
                    'popup-content-close': `#wpsl-wrap .gm-style .gm-ui-hover-effect > span { background-color: ${ color } !important; }`,
                    'popup-content-close-hover': `#wpsl-wrap .gm-style .gm-ui-hover-effect:hover > span { background-color: ${ color } !important; }`,
                    'popup-icons-color': `
                        #wpsl-wrap .gm-style-iw-d [class*="wpsl-icon-"]::before,
                        #wpsl-wrap .wpsl-info-window [class*="wpsl-icon-"]::before {
                            color: ${ color } !important;
                        }
                    `
                };

                styleElement.innerHTML = gmapsStyles[ elem ] || '';
            } else {
                const isMapbox = state.mapService === 'mapbox';
                const popupClass = isMapbox ? '.mapboxgl-popup-content' : '.leaflet-popup-content-wrapper';
                const textClass = isMapbox ? '.mapboxgl-popup-content' : '.leaflet-popup-content';
                const closeClass = isMapbox ? '.mapboxgl-popup-close-button' : '.leaflet-popup-close-button';

                let popupBackgroundStyle;

                if ( isMapbox ) {
                    popupBackgroundStyle = `#wpsl-wrap ${ popupClass } { background: ${ color } !important; } #wpsl-wrap .mapboxgl-popup-anchor-top .mapboxgl-popup-tip { border-bottom-color: ${ color } !important; } #wpsl-wrap .mapboxgl-popup-anchor-bottom .mapboxgl-popup-tip { border-top-color: ${ color } !important; } #wpsl-wrap .mapboxgl-popup-anchor-left .mapboxgl-popup-tip { border-right-color: ${ color } !important; } #wpsl-wrap .mapboxgl-popup-anchor-right .mapboxgl-popup-tip { border-left-color: ${ color } !important; }`;
                } else {
                    popupBackgroundStyle = `#wpsl-wrap ${ popupClass }, #wpsl-wrap .leaflet-popup-tip { background: ${ color } !important; }`;
                }

                const leafletMapboxStyles = {
                    'popup-content-background': popupBackgroundStyle,
                    'popup-content-text': `#wpsl-wrap ${ textClass } { color: ${ color } !important; }`,
                    'popup-content-link': `#wpsl-wrap ${ textClass } a:not(.wpsl-styled-btn) { color: ${ color } !important; }`,
                    'popup-content-link-hover': `#wpsl-wrap ${ textClass } a:not(.wpsl-styled-btn):hover { color: ${ color } !important; }`,
                    'popup-content-close': `#wpsl-wrap ${ closeClass } { color: ${ color } !important; }`,
                    'popup-content-close-hover': `#wpsl-wrap ${ closeClass }:hover { color: ${ color } !important; }`,
                    'popup-icons-color': `
                        #wpsl-wrap ${ textClass } [class*="wpsl-icon-"]::before,
                        #wpsl-wrap .wpsl-info-window [class*="wpsl-icon-"]::before {
                            color: ${ color } !important;
                        }
                    `
                };

                styleElement.innerHTML = leafletMapboxStyles[ elem ] || '';
            }
        } else {
            document.getElementById( `wpsl-dynamic-${ elem }` )?.remove();
        }
    }
};