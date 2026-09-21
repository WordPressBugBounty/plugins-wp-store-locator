import { state } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';
import { mapBootstrap, mapObjects } from './wpsl-map-bootstrap.js';
import { mapStyles } from './settings/wpsl-map-styles.js';
import { createDropdowns, alignSearchColumns } from '../../../common/wpsl-dropdowns.js';

import { buttonStyles } from './appearance/wpsl-buttons.js';
import { eventHandlers } from './appearance/wpsl-handlers.js';
import { iconManager } from './appearance/wpsl-icons.js';
import { themeStyles } from './appearance/wpsl-theme-styles.js';
import { contrastChecker } from './appearance/wpsl-contrast.js';
import { preloaderPreview } from './appearance/wpsl-preloader-preview.js';

/**
 * Appearance Editor Module
 *
 * @since 3.0.0
 */
export const appearanceEditor = {
    $tabs: null,
    $tabContents: null,
    currentTemplate: null,
    hasMap: jQuery( '#wpsl-wrap' ).length,
    activeTemplateId: '',
    previousTemplateId: '',

    // modules
    buttons: buttonStyles,
    handlers: eventHandlers,
    icons: iconManager,
    themes: themeStyles,
    contrast: contrastChecker,
    preloaderPreview: preloaderPreview,

    /**
     * Initialize the appearance editor
     *
     * @since 3.0.0
     * @returns {void}
     */
    init() {
        if ( document.readyState === 'loading' ) {
            document.addEventListener( 'DOMContentLoaded', () => this.doInit() );
        } else {
            this.doInit();
        }
    },

    /**
     * Do the actual initialization after DOM is ready
     *
     * @since 3.0.0
     * @returns {void}
     */
    doInit() {
        if ( ! jQuery( '#wpsl-appearance-tabs' ).length ) {
            return;
        }

        this.$tabs = jQuery( '#wpsl-appearance-tabs li' );
        this.$tabContents = jQuery( '.wpsl-appearance-view' );

        this.buttons.init( this );
        this.handlers.init( this );
        this.handlers.bind();
        this.icons.init( this );

        // Apply initial icon state if icons are already enabled
        if ( jQuery( '#wpsl-enable-icons' ).is( ':checked' ) ) {
            this.icons.add();
        }

        this.themes.init( this );
        this.contrast.init( this );
        this.preloaderPreview.init( this );
        this.contrast.bindEvents();

        this.initTabs();
        this.restoreTabFromSession();
        this.applyInitialColors();
        this.contrast.runInitialChecks();
        this.initStyleEditorDropdowns();
        this.initConditionalOptions();
        this.handlers.toggleDimensionFields();
        this.initMapPreview();
        this.initPreviewDropdowns();
        this.initToggleSliders();
        this.initMapboxStyleLabels();
        this.initTileSourceHandler();
        this.initStadiaStyleSourceHandler();
        this.initMapLibreStylePreview();
        this.initInfoPopups();
    },

    /**
     * Initialize vertical tab navigation
     *
     * @since 3.0.0
     * @returns {void}
     */
    initTabs() {
        // Handle tab clicks - hide menu and show content
        jQuery( document ).on( 'click', '#wpsl-appearance-tabs a', ( e ) => {
            e.preventDefault();

            const $link = jQuery( e.currentTarget );
            const targetId = $link.attr( 'href' );

            jQuery( '#wpsl-menu-view' ).addClass( 'wpsl-hidden' );

            // "Themes" tab navigates back to the home view instead of a tab panel.
            if ( targetId === '#wpsl-templates-tab' ) {
                jQuery( '#wpsl-theme-home-view' ).removeClass( 'wpsl-hidden' );
                jQuery( '#wpsl-appearance-content' ).addClass( 'wpsl-hidden' );
                jQuery( '.wpsl-tab-header' ).addClass( 'wpsl-hidden' );
                jQuery( '.wpsl-tab-footer' ).hide();
                jQuery( '.wpsl-customize-btn' ).trigger( 'focus' );
                return;
            }

            // Hide any previously visible tab panel before showing the new one.
            jQuery( '#wpsl-appearance-content .wpsl-appearance-view' ).addClass( 'wpsl-hidden' );

            jQuery( '#wpsl-theme-home-view' ).addClass( 'wpsl-hidden' );
            jQuery( '#wpsl-appearance-content' ).removeClass( 'wpsl-hidden' );
            jQuery( '.wpsl-tab-header' ).removeClass( 'wpsl-hidden' );
            jQuery( targetId ).removeClass( 'wpsl-hidden' );

            this.updateTabHeader( targetId );

            this.onTabChange( targetId );
        } );

        jQuery( document ).on( 'click', '.wpsl-back-button', ( e ) => {
            e.preventDefault();
            e.stopPropagation();

            // Clearing this stops restoreTabFromSession() reopening the tab.
            sessionStorage.removeItem( 'wpsl_reopen_tab' );

            jQuery( '.wpsl-color-field' ).each( function() {
                const picker = jQuery( this ).data( 'wpsl-color-picker' );
                if ( picker && picker.state.open ) {
                    picker.close();
                }
            } );

            // Hide all content views and the content area, then show the menu.
            jQuery( '#wpsl-appearance-nav .wpsl-appearance-view' ).addClass( 'wpsl-hidden' );
            jQuery( '#wpsl-appearance-content' ).addClass( 'wpsl-hidden' );
            jQuery( '#wpsl-menu-view' ).removeClass( 'wpsl-hidden' );
            jQuery( '.wpsl-tab-header' ).addClass( 'wpsl-hidden' );

            jQuery( '.wpsl-tab-title' ).text( '' );
            jQuery( '.wpsl-tab-footer' ).hide();

            jQuery( '#wpsl-appearance-tabs a' ).first().trigger( 'focus' );
        } );
    },

    /**
     * Reopen a tab after a page reload if one was stored in sessionStorage.
     *
     * @since 3.0.0
     * @returns {void}
     */
    restoreTabFromSession() {
        const tabId = sessionStorage.getItem( 'wpsl_reopen_tab' );
        if ( ! tabId ) {
            return;
        }

        sessionStorage.removeItem( 'wpsl_reopen_tab' );

        if ( tabId !== '#wpsl-templates-tab' ) {
            const $link = jQuery( '#wpsl-appearance-tabs a[href="' + tabId + '"]' );
            if ( $link.length ) {
                $link.trigger( 'click' );
            }
        }

        document.documentElement.classList.remove( 'wpsl-restoring-tab' );
    },

    /**
     * Update tab header title and restore button visibility
     *
     * @since 3.0.0
     * @param {string} targetId - The ID of the target tab content
     * @returns {void}
     */
    updateTabHeader( targetId ) {
        const $targetTab = jQuery( targetId );
        const tabTitle = $targetTab.data( 'tab-title' ) || '';
        let showReset = $targetTab.data( 'show-reset' ) === 1;

        // Theme styles tab gates the reset button on the overwrite checkbox
        if ( targetId === '#wpsl-theme-styles-tab' ) {
            showReset = showReset && jQuery( '#wpsl-overwrite-theme-styles' ).is( ':checked' );
        }

        // Font size tab gates the reset button on the overwrite checkbox
        if ( targetId === '#wpsl-font-size-tab' ) {
            showReset = showReset && jQuery( '#wpsl-overwrite-font-sizes' ).is( ':checked' );
        }

        jQuery( '.wpsl-tab-title' ).text( tabTitle );

        if ( showReset ) {
            let resetLabel;
            
            if ( targetId === '#wpsl-font-size-tab' ) {
                resetLabel = wpslL10n.restoreDefaultFontSizes;
            } else if ( targetId === '#wpsl-dimensions-tab' ) {
                resetLabel = wpslL10n.restoreDefaultDimensions;
            } else {
                resetLabel = wpslL10n.restoreDefaultColors;
            }

            jQuery( '.wpsl-reset-styles' ).val( resetLabel );
            jQuery( '.wpsl-reset-styles' ).show();
        } else {
            jQuery( '.wpsl-reset-styles' ).hide();
        }

        const showFooter = $targetTab.data( 'show-footer' ) !== 0;
        jQuery( '.wpsl-tab-footer' ).toggle( showFooter );
    },

    /**
     * Initialize style editor dropdowns for theme styles tab
     *
     * @since 3.0.0
     * @returns {void}
     */
    initStyleEditorDropdowns() {
        jQuery( '#wpsl-theme-styles-tab .wpsl-style-section ul' ).hide();

        const overwriteEnabled = jQuery( '#wpsl-overwrite-theme-styles' ).is( ':checked' );
        const savedMainVal = overwriteEnabled ? localStorage.getItem( 'wpsl_style_editor_dropdown' ) : null;
        if ( savedMainVal ) {
            const $mainDropdown = jQuery( '#wpsl-style-editor-dropdown' );
            if ( $mainDropdown.length ) {
                $mainDropdown.val( savedMainVal );
                
                const savedSubVal = localStorage.getItem( 'wpsl_style_filter_' + savedMainVal );
                const $subDropdown = jQuery( '#wpsl-' + savedMainVal + '-style-sections' );
                if ( savedSubVal && $subDropdown.length ) {
                    $subDropdown.val( savedSubVal );
                }
            }
        }

        jQuery( '#wpsl-style-editor-dropdown' ).trigger( 'change' );

        const activeSection = jQuery( '#wpsl-style-editor-dropdown' ).val();
        if ( activeSection ) {
            jQuery( '#wpsl-' + activeSection + '-style-sections' ).trigger( 'change' );
        }

        jQuery( '#wpsl-cta-buttons' ).trigger( 'change' );
    },

    /**
     * Initialize conditional options toggle for all checkboxes
     *
     * @since 3.0.0
     * @returns {void}
     */
    initConditionalOptions() {
        wpslSharedFuncs.bindConditionalOptions();

        // Conditional options that toggle multiple elements.
        jQuery( document ).on( 'change', '.wpsl-has-conditional-options', function() {
            const isChecked = jQuery( this ).is( ':checked' );

            if ( helpers.dom.shouldToggle( jQuery( this ) ) ) {
                if ( isChecked ) {
                    jQuery( this ).parents( 'p' ).nextAll( '.wpsl-conditional-option' ).show();

                    jQuery( '#wpsl-button-style-mode' ).val( 'manage-styles' );

                    jQuery( this ).parents( 'p' ).nextAll( '[data-button-mode]' ).hide();
                    jQuery( this ).parents( 'p' ).nextAll( '[data-button-mode="manage-styles"]' ).first().show();
                } else {
                    jQuery( this ).parents( 'p' ).nextAll( '.wpsl-conditional-option' ).hide();
                    jQuery( this ).parents( 'p' ).nextAll( '[data-button-mode]' ).hide();
                }
            }
        });
    },

    /**
     * Initialize custom dropdown widgets for the template preview.
     *
     * The preview template ships native <select class="wpsl-dropdown"> elements.
     * Converting them to the button/listbox widget puts Tab focus on the styled
     * <button class="wpsl-selected-item">, which has a visible focus outline.
     *
     * @since 3.0.0
     * @returns {void}
     */
    initPreviewDropdowns() {
        const hasSelectDropdown = jQuery( '#wpsl-appearance-preview select.wpsl-dropdown' ).length;
        const hasResultFilters = jQuery( '#wpsl-appearance-preview #wpsl-result-filters' ).length;

        if ( ! hasSelectDropdown && ! hasResultFilters ) {
            return;
        }

        const dropdownConfig = {
            ux: {
                maxDropdownHeight: 250
            },
            search: {
                distanceUnit: ( typeof wpslSettings !== 'undefined' && wpslSettings.distanceUnit ) ? wpslSettings.distanceUnit : 'km'
            }
        };

        this.dropdownHandler = createDropdowns( {}, dropdownConfig );

        if ( hasSelectDropdown ) {
            this.dropdownHandler.create();
        }

        // Line up the search bar column ( equal label widths + category dropdown
        // matched to the input ) the same way the frontend does.
        alignSearchColumns();

        // Reveal the search bar now the column is aligned ( it is hidden from
        // first paint by a visibility rule in appearance-editor.css ).
        jQuery( '#wpsl-wrap' ).addClass( 'wpsl-labels-aligned' );

        // The measured width changes once web fonts swap in, so re-run then.
        if ( document.fonts && document.fonts.ready ) {
            document.fonts.ready.then( function () {
                alignSearchColumns();
            } );
        }
    },

    applyInitialColors() { return this.themes.applyInitialColors(); },
    updateStyles( ...args ) { return this.themes.updateStyles( ...args ); },
    updateGradientPreview( ...args ) { return this.buttons.updateGradientPreview( ...args ); },

    /**
     * Handle tab change
     *
     * @since 3.0.0
     * @param {string} tabId - Tab ID
     * @returns {void}
     */
    onTabChange( tabId ) {
        if ( tabId === '#wpsl-dimensions-tab' ) {
            this.handlers.toggleDimensionFields();
        }

        if ( tabId === '#wpsl-map-styles-tab' ) {
            // Ensure map preview is initialized when map styles tab is accessed
            if ( ! this.hasMap ) {
                this.hasMap = jQuery( '#wpsl-wrap' ).length;
            }

            if ( this.hasMap ) {
                this.styleEditor();
            }
        }

        // Catch checkboxes that only exist once the tab's content is shown.
        setTimeout( () => {
            this.initToggleSliders();
        }, 100 );
    },

    /**
     * Initialize map preview
     *
     * @since 3.0.0
     * @returns {void}
     */
    initMapPreview() {
        if ( ! jQuery( '#wpsl-wrap' ).length ) {
            return;
        }

        this.styleEditor();
    },

    /**
     * Initialize info popups
     *
     * @since 3.0.0
     * @returns {void}
     */
    initInfoPopups() {
        wpslSharedFuncs.bindInfoPopup();
    },

    /**
     * Handle the OSM tile source dropdown changes.
     *
     * When the tile source changes:
     * - "default": restore default OSM tiles, hide style sections.
     * - "mapbox": show Mapbox styles, auto-select first if none selected.
     * - "stadia": show Stadia styles, auto-select first if none selected.
     *
     * @since 3.0.0
     * @returns {void}
     */
    initTileSourceHandler() {
        const $tileSource = jQuery( '#wpsl-osm-tile-source' );
        if ( ! $tileSource.length ) {
            return;
        }

        const applyTileSource = function() {
            const value = jQuery( this ).val();

            jQuery( '.wpsl-osm-tile-source-section' ).hide();

            const $selectedSection = jQuery( '#wpsl-osm-tile-source-' + value );
            if ( $selectedSection.length ) {
                $selectedSection.show();
            }

            if ( value === 'default' ) {
                mapStyles.osm.setDefault();
            } else if ( value === 'mapbox' ) {
                const $checked = jQuery( '#wpsl-osm-tile-source-mapbox input[type="radio"]:checked' );
                if ( ! $checked.length ) {
                    const $first = jQuery( '#wpsl-osm-tile-source-mapbox input[type="radio"]:first' );
                    if ( $first.length ) {
                        $first.prop( 'checked', true ).trigger( 'change' );
                    }
                } else {
                    $checked.trigger( 'change' );
                }
            } else if ( value === 'stadia' ) {
                const $checked = jQuery( '#wpsl-osm-tile-source-stadia input[type="radio"]:checked' );
                if ( ! $checked.length ) {
                    const $first = jQuery( '#wpsl-osm-tile-source-stadia input[type="radio"]:first' );
                    if ( $first.length ) {
                        $first.prop( 'checked', true ).trigger( 'change' );
                    }
                } else {
                    $checked.trigger( 'change' );
                }
            } else if ( value === 'openfreemap' ) {
                const $checked = jQuery( '#wpsl-osm-tile-source-openfreemap input[type="radio"]:checked' );
                if ( ! $checked.length ) {
                    const $first = jQuery( '#wpsl-osm-tile-source-openfreemap input[type="radio"]:first' );
                    if ( $first.length ) {
                        $first.prop( 'checked', true ).trigger( 'change' );
                    }
                } else {
                    $checked.trigger( 'change' );
                }
            } else if ( value === 'maplibre' ) {
                appearanceEditor.previewMapLibreStyle();
            }
        };

        $tileSource.on( 'change', applyTileSource );

        // Preview the OpenFreeMap preset choices.
        jQuery( '#wpsl-osm-tile-source-openfreemap input[type="radio"]' ).on( 'change', function() {
            const $checked = jQuery( '#wpsl-osm-tile-source-openfreemap input[type="radio"]:checked' );
            if ( $checked.length ) {
                mapStyles.openfreemap.setStyle( $checked.data( 'url' ) );
            }
        } );

        // Apply initial state on page load
        applyTileSource.call( $tileSource );
    },

    /**
     * Handle the Stadia style source dropdown changes.
     *
     * - "stadia": show the preset styles, preview the selected one.
     * - "maplibre": show the MapLibre style URL section, preview the URL.
     *
     * @since 3.0.0
     * @returns {void}
     */
    initStadiaStyleSourceHandler() {
        const $styleSource = jQuery( '#wpsl-stadia-style-source' );
        if ( ! $styleSource.length ) {
            return;
        }

        const applyStyleSource = function() {
            const value = jQuery( this ).val();

            jQuery( '.wpsl-stadia-style-source-section' ).hide();
            jQuery( '#wpsl-stadia-style-source-' + value ).show();

            if ( value === 'stadia' ) {
                const $checked = jQuery( '#wpsl-stadia-style-source-stadia input[type="radio"]:checked' );
                if ( $checked.length ) {
                    $checked.trigger( 'change' );
                }
            } else if ( value === 'maplibre' ) {
                appearanceEditor.previewMapLibreStyle();
            }
        };

        $styleSource.on( 'change', applyStyleSource );

        // Only preview on load when the MapLibre source is active; the preset
        // preview is already the map's saved state.
        if ( $styleSource.val() === 'maplibre' ) {
            appearanceEditor.previewMapLibreStyle();
        }
    },

    /**
     * Bind the MapLibre style URL field and its apply toggle
     * ( osm / stadia providers ).
     *
     * @since 3.0.0
     * @returns {void}
     */
    initMapLibreStylePreview() {
        // Editing the URL implies wanting it applied: switch the toggle on
        // ( the change trigger keeps the slider's ARIA state in sync ).
        jQuery( '#wpsl-maplibre-style-url' ).on( 'change blur', function() {
            const $toggle = jQuery( '#wpsl-maplibre-enabled' );

            if ( $toggle.length && ! $toggle.prop( 'checked' ) ) {
                $toggle.prop( 'checked', true ).trigger( 'change' );
                return; // The toggle's change handler runs the preview.
            }

            appearanceEditor.previewMapLibreStyle();
        } );

        jQuery( '#wpsl-maplibre-enabled' ).on( 'change', function() {
            appearanceEditor.previewMapLibreStyle();
        } );
    },

    /**
     * Put the preview back on what the provider renders without the custom
     * style: the Stadia preset when its picker is on the page, the default
     * OSM tiles otherwise.
     *
     * @since 3.0.0
     * @returns {void}
     */
    revertMapLibrePreview() {
        const $stadiaPreset = jQuery( '#wpsl-stadia-style-source-stadia input[type="radio"]:checked' );

        if ( $stadiaPreset.length ) {
            $stadiaPreset.trigger( 'change' );
        } else {
            mapStyles.osm.setDefault();
        }
    },

    /**
     * Preview + validate the custom MapLibre style JSON URL.
     *
     * With the apply toggle off the URL is kept but not applied, so the
     * preview reverts to the provider's default tiles instead.
     *
     * @since 3.0.0
     * @returns {void}
     */
    previewMapLibreStyle() {
        const $url      = jQuery( '#wpsl-maplibre-style-url' );
        const $toggle   = jQuery( '#wpsl-maplibre-enabled' );
        const $info     = jQuery( 'label[for="wpsl-maplibre-style-url"] .wpsl-info' );
        const $infoText = $info.find( '.wpsl-info-text' );

        if ( ! $url.length ) {
            return;
        }

        // Remember the original tooltip help so it can be restored after an error.
        if ( ! this._maplibreHelpHtml ) {
            this._maplibreHelpHtml = $infoText.html();
        }

        const helpHtml = this._maplibreHelpHtml;

        const clearError = function() {
            $info.removeClass( 'wpsl-warning' );
            $infoText.html( helpHtml );
            $url.removeClass( 'wpsl-error' );
        };

        const flagError = function() {
            $info.addClass( 'wpsl-warning' );
            $infoText.text( wpslL10n.openfreemapUrlInvalid );
            $url.addClass( 'wpsl-error' );
        };

        if ( $toggle.length && ! $toggle.prop( 'checked' ) ) {
            clearError();
            this.revertMapLibrePreview();
            return;
        }

        const styleUrl = ( $url.val() || '' ).trim();

        if ( styleUrl.indexOf( 'https://' ) !== 0 ) {
            flagError();
            return;
        }

        clearError();

        /*
         * {api_key} is expanded server-side and the key never reaches the
         * page, so loading this would always fail. Leave the saved layer in
         * place and let the front-end resolve it.
         */
        if ( styleUrl.indexOf( '{api_key}' ) !== -1 ) {
            return;
        }

        mapStyles.openfreemap.setStyle( styleUrl, function( ok ) {
            // The source may have moved on while the async load was in flight.
            const sourceActive = jQuery( '#wpsl-osm-tile-source' ).val() === 'maplibre'
                || jQuery( '#wpsl-stadia-style-source' ).val() === 'maplibre';

            if ( ! ok && sourceActive ) {
                flagError();
            }
        } );
    },

    /**
     * Initialize toggle sliders for checkboxes
     *
     * @since 3.0.0
     * @returns {void}
     */
    initToggleSliders() {
        const $appearanceCheckboxes = jQuery( '#wpsl-appearance-form input[type=checkbox], #wpsl-appearance-form input[type=radio]' )
            .not( '.wpsl-styled-template-preview input, .wpsl-multiselect-container input, .wpsl-icon-radio-group input, .wpsl-icon-dropdown-wrapper input, input[name="wpsl_activate_template"], .wpsl-mapbox-style-options input, .wpsl-stadia-style-options input, .wpsl-openfreemap-style-options input' );

        // Only wrap if the first candidate hasn't been wrapped yet
        if ( $appearanceCheckboxes.length && ! $appearanceCheckboxes.first().closest( '.wpsl-toggle-wrap' ).length ) {
            wpslSharedFuncs.createToggleSliders( $appearanceCheckboxes );
        }
    },

    /**
     * Make the Mapbox style image grid keyboard-accessible using the
     * roving-tabindex radiogroup pattern.
     *
     * - Tab enters the group and lands on the currently selected option.
     * - Arrow keys (up/down/left/right) cycle through options.
     * - Enter / Space activates the focused option.
     *
     * @since 3.0.0
     * @returns {void}
     */
    initMapboxStyleLabels() {
        const $lists = jQuery( '.wpsl-mapbox-style-options, .wpsl-stadia-style-options, .wpsl-openfreemap-style-options' );
        if ( ! $lists.length ) {
            return;
        }

        $lists.each( function() {
            const $list = jQuery( this );
            $list.attr( 'role', 'radiogroup' );

            const $labels = $list.find( 'label' );
            $labels.each( function() {
                const $label = jQuery( this );
                const $radio = jQuery( '#' + $label.attr( 'for' ) );
                const isChecked = $radio.is( ':checked' );

                $label.attr( 'role', 'radio' );
                $label.attr( 'aria-checked', isChecked ? 'true' : 'false' );
                $label.attr( 'tabindex', '0' );
            } );
        } );

        jQuery( document ).on( 'keydown', '.wpsl-mapbox-style-options label, .wpsl-stadia-style-options label, .wpsl-openfreemap-style-options label', function( e ) {
            const isArrow  = e.key === 'ArrowDown' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowLeft';
            const isSelect = e.key === ' ' || e.key === 'Enter';
            if ( ! isArrow && ! isSelect ) {
                return;
            }

            e.preventDefault();

            const $list = jQuery( this ).closest( '.wpsl-mapbox-style-options, .wpsl-stadia-style-options, .wpsl-openfreemap-style-options' );

            if ( isSelect ) {
                jQuery( this ).trigger( 'click' );
                return;
            }

            const $labels  = $list.find( 'label' );
            const total    = $labels.length;
            const current  = $labels.index( this );
            const forward  = e.key === 'ArrowDown' || e.key === 'ArrowRight';
            const newIndex = forward ? ( current + 1 ) % total : ( current - 1 + total ) % total;

            $labels.eq( newIndex ).trigger( 'focus' );
        } );

        jQuery( document ).on( 'change', '.wpsl-mapbox-style-options input[type="radio"], .wpsl-stadia-style-options input[type="radio"], .wpsl-openfreemap-style-options input[type="radio"]', function() {
            const $radio = jQuery( this );
            const $list = $radio.closest( '.wpsl-mapbox-style-options, .wpsl-stadia-style-options, .wpsl-openfreemap-style-options' );
            let selectedClass = 'wpsl-selected-mapbox-style';
            if ( $list.hasClass( 'wpsl-stadia-style-options' ) ) {
                selectedClass = 'wpsl-selected-stadia-style';
            } else if ( $list.hasClass( 'wpsl-openfreemap-style-options' ) ) {
                selectedClass = 'wpsl-selected-openfreemap-style';
            }

            $list.find( 'label[role="radio"]' ).attr( 'aria-checked', 'false' );
            jQuery( 'label[for="' + $radio.attr( 'id' ) + '"]' ).attr( 'aria-checked', 'true' );

            $list.find( 'img' ).removeClass( selectedClass );
            $radio.closest( 'li' ).find( 'img' ).addClass( selectedClass );
        } );
    },

    /**
     * Initialize the style editor.
     * 
     * @since 3.0.0
     */
    styleEditor() {
        const mapId = `wpsl-${ state.mapService }-wrap`;
        let mapElem = mapObjects.get( mapId );

        const initCallback = () => {
            // Get the map element again after creation
            mapElem = mapObjects.get( mapId );
            if ( mapElem ) {
                mapStyles.init( mapElem );
            }

            // Add marker at the location-specific coordinates
            const args = {
                'latLng': helpers.coordinates.getLocationCoordinates(),
                'zoom': parseInt( wpslSettings.defaultZoom ),
                'addMarker': true,
                'openInfoWindow': true,
                'elemId': mapId
            };

            helpers.map.setViewport( args );

            // Force map rerender to fix grey blocks and recenter popup
            setTimeout( () => {
                helpers.map.invalidateSize( mapId );
            }, 150 );
        };

        // Create the map if it doesn't exist yet
        if ( ! mapElem && document.getElementById( mapId ) ) {
            if ( state.mapService === 'gmaps' ) {
                // The Google Maps API loads asynchronously, so wait for
                // mapBootstrap.init()'s loader callback to fire wpslAppearanceApiReady.
                if ( ! mapBootstrap.isGmapsReady() ) {

                    /**
                     * With another plugin owning the Maps library our loader is
                     * ignored, so wpslAppearanceApiReady never fires and the wait
                     * below would never end. Explain the blank preview instead.
                     */
                    if ( window.wpslGmapsConflict ) {

                        // Same treatment as the frontend: the message takes over
                        // the search wrap and the map and results are hidden.
                        jQuery( '#wpsl-appearance-preview #wpsl-wrap' )
                            .addClass( 'wpsl-api-message' )
                            .find( '.wpsl-search' )
                            .html( '<p>' + wpslApiErrors.gmaps.conflictDetected + '</p>' );

                        return;
                    }

                    wp.hooks.addAction( 'wpslAppearanceApiReady', 'wpsl-appearance', () => {
                        wp.hooks.removeAction( 'wpslAppearanceApiReady', 'wpsl-appearance' );
                        this.styleEditor();
                    } );
                    return;
                }
                mapBootstrap.gmaps( { elemId: mapId }, initCallback );
            } else if ( state.mapService === 'mapbox' ) {
                mapBootstrap.mapbox( { elemId: mapId }, initCallback );
            } else if ( state.mapService === 'osm' ) {
                mapBootstrap.osm( { elemId: mapId }, initCallback );
            } else if ( state.mapService === 'stadia' ) {
                mapBootstrap.stadia( { elemId: mapId }, initCallback );
            }
        } else if ( mapElem ) {
            initCallback();
        }
    },

    /**
     * Helper functions for color picker operations.
     * 
     * @since 3.0.0
     */
    helpers: {
        /**
         * Get the parent element containing the data-elem attribute.
         * 
         * @since 3.0.0
         * @param {jQuery} $input - The color input field
         * @returns {jQuery} - Parent element with data-elem attribute
         */
        getDataElemParent( $input ) {
            return $input.parents( 'li[data-elem], p[data-elem]' );
        },

        /**
         * Detect button type from input field's parent container.
         * 
         * @since 3.0.0
         * @param {jQuery} $input - The color input field
         * @returns {string|null} - Button type or null if not a button gradient field
         */
        getButtonTypeFromInput( $input ) {
            if ( ! $input.hasClass( 'wpsl-gradient-color-field' ) ) {
                return null;
            }

            const $ul = $input.closest( 'ul' );
            const classMap = {
                'wpsl-submit-options': 'submit',
                'wpsl-general-primary-options': 'general_primary',
                'wpsl-general-secondary-options': 'general_secondary',
                'wpsl-listing-cta-more-details-options': 'listing_cta_more_details',
                'wpsl-listing-cta-directions-options': 'listing_cta_directions',
                'wpsl-popup-cta-more-details-options': 'popup_cta_more_details',
                'wpsl-popup-cta-directions-options': 'popup_cta_directions'
            };

            for ( const [className, buttonType] of Object.entries( classMap ) ) {
                if ( $ul.hasClass( className ) ) {
                    return buttonType;
                }
            }

            return null;
        }
    }
};