import { state } from '../wpsl-shared.js';
import { alignSearchColumns } from '../../../../common/wpsl-dropdowns.js';

/**
 * Event handlers for the appearance section.
 *
 * - Template events
 * - Color picker changes
 * - Button style toggles
 * - Icon toggles
 * - Filter interactions
 *
 * @since 3.0.0
 */
export const eventHandlers = {
    /**
     * Reference to parent appearance object.
     */
    parent: null,

    /**
     * Initialize event handlers module.
     *
     * @since 3.0.0
     * @param {object} parentAppearance - Reference to main appearance object
     */
    init( parentAppearance ) {
        this.parent = parentAppearance;
    },

    /**
     * Bind all event handlers.
     *
     * @since 3.0.0
     */
    bind() {
        const self = this;

        this.templateEvents();

        jQuery( '#wpsl-theme-customization-tabs .ui-tabs-nav li' ).on( 'click', function() {
            const tabId = jQuery( this ).data( 'id' );
            const themeStylesActive = ( tabId == 'theme' );
            const buttonStylesActive = ( tabId == 'button' );
            const mapStylesActive = ( tabId == 'map' );
            const showActions = ( mapStylesActive && self.parent.hasMap ) ? true : false;

            // Button Styles tab: always show reset button
            // Theme Style tab: only show if overwrite_theme_styles is enabled
            let shouldShowResetButton = false;

            if ( buttonStylesActive ) {
                shouldShowResetButton = true;
            } else if ( themeStylesActive ) {
                const overwriteEnabled = jQuery( '#wpsl-overwrite-theme-styles' ).is( ':checked' );

                shouldShowResetButton = overwriteEnabled;
            }

            jQuery( '#wpsl-style-reset-colors' ).toggle( shouldShowResetButton );
            jQuery( '.wpsl-style-actions' ).toggle( showActions );

            if ( ! showActions ) {
                jQuery( '#wpsl-search-results-tab p:first-child' ).css( 'margin-bottom', 0 );
            }
        });

        this.bindFilterInteractions();
        this.bindResultColumns();
        this.bindFilterLayout();
        this.bindStyleEditorDropdown();
        this.bindStyleSections();
        this.bindTabJumpLinks();
        this.bindButtonStyleMode();
        this.bindButtonTypeFilter();
        this.bindButtonStyleToggle();
        this.bindOverwriteThemeStyles();
        this.bindCustomFocusOutline();
        this.bindResetColors();
        this.bindCTACheckboxes();
        this.bindCTAButtons();
        this.bindMoreDetailsTarget();
        this.bindIconToggle();
        this.bindGmapsStyleSource();

        this.setInitialResetButtonVisibility();
        this.bindIconRadios();
        this.bindIconDropdowns();
        this.bindGradientControls();
        this.bindAnglePicker();
        this.bindFontSizeSliders();
        this.bindOverwriteFontSizes();
        this.bindDimensionWidths();
    },

    /**
     * Bind template-related events.
     *
     * @since 3.0.0
     */
    templateEvents() {
        jQuery( document ).on( 'click', '.wpsl-template-style-option', function( e ) {
            if ( jQuery( e.target ).closest( '.wpsl-toggle-wrap' ).length ) {
                return;
            }

            const $radio = jQuery( this ).find( 'input[name="wpsl_activate_template"]' );

            if ( $radio.length && ! $radio.is( ':checked' ) ) {
                $radio.prop( 'checked', true ).trigger( 'change' );
            }
        });

        // Submit the form when a template is selected.
        // No sessionStorage key is set — the page should reload to the home view.
        jQuery( document ).on( 'change', 'input[name="wpsl_activate_template"]', function() {
            jQuery( '#wpsl-appearance-form' ).submit();
        });

        // "Customize" button on the active template card opens the menu.
        jQuery( document ).on( 'click', '.wpsl-customize-btn', function() {
            jQuery( '#wpsl-theme-home-view' ).addClass( 'wpsl-hidden' );
            jQuery( '#wpsl-menu-view' ).removeClass( 'wpsl-hidden' );
            jQuery( '#wpsl-appearance-tabs a' ).first().trigger( 'focus' );
        });

        // Keyboard: Enter / Space on a template toggle slider activates that template.
        jQuery( document ).on( 'keydown', '.wpsl-template-style-option .wpsl-toggler-slider', function( e ) {
            if ( e.key !== ' ' && e.key !== 'Enter' ) {
                return;
            }

            e.preventDefault();

            const $radio = jQuery( this ).closest( '.wpsl-template-style-option' ).find( 'input[name="wpsl_activate_template"]' );
            if ( $radio.length && ! $radio.is( ':checked' ) ) {
                $radio.prop( 'checked', true ).trigger( 'change' );
            }
        });

        // Store the open tab so it can be restored after the save redirect.
        jQuery( document ).on( 'click', '.wpsl-save-tab', function() {
            const $activeTab = jQuery( '#wpsl-appearance-content .wpsl-appearance-view:not(.wpsl-hidden)' );

            if ( $activeTab.length ) {
                sessionStorage.setItem( 'wpsl_reopen_tab', '#' + $activeTab.attr( 'id' ) );
            }

            // Save the style editor dropdown selections to localStorage
            const mainDropdown = jQuery( '#wpsl-style-editor-dropdown' );
            if ( mainDropdown.length ) {
                const mainVal = mainDropdown.val();
                localStorage.setItem( 'wpsl_style_editor_dropdown', mainVal );
                
                const subDropdown = jQuery( '#wpsl-' + mainVal + '-style-sections' );
                if ( subDropdown.length ) {
                    localStorage.setItem( 'wpsl_style_filter_' + mainVal, subDropdown.val() );
                }
            }
        });
    },

    /**
     * Bind filter interactions for vertical template.
     *
     * @since 3.0.0
     */
    bindFilterInteractions() {
        if ( ! this.parent.dropdownHandler ) {
            this.parent.initPreviewDropdowns();
        }

        if ( this.parent.dropdownHandler ) {
            this.parent.dropdownHandler.bindFilterInteractions();
        }
    },

    /**
     * Bind result columns change handler.
     *
     * @since 3.0.0
     */
    bindResultColumns() {
        jQuery( '#wpsl-result-columns' ).on( 'change', function() {
            const currentColumns = Number( jQuery( '#wpsl-result-list li' ).length );
            const selectedColumns = Number( jQuery( this ).val() );

            if ( currentColumns !== selectedColumns ) {
                const $list = jQuery( '#wpsl-result-list ul' );
                const $items = $list.find('li');

                $list.removeClass( function( index, className ) {
                    return ( className.match( /wpsl-v3-columns-\d+/g ) || [] ).join(' ');
                }).addClass( 'wpsl-v3-columns-' + selectedColumns );

                if ( currentColumns < selectedColumns ) {
                    for ( let i = currentColumns; i < selectedColumns; i++ ) {
                        const $newItem = $items.first().clone();

                        $list.append( $newItem );
                    }
                } else if ( currentColumns > selectedColumns ) {
                    $items.slice( selectedColumns ).remove();
                }
            }
        });
    },

    /**
     * Bind the "Filter layout" dropdown (vertical template only).
     *
     * The preview markup is always the horizontal baseline, so cache it here;
     * applyFilterLayoutPreview() rebuilds it into stacked / nested.
     *
     * @since 3.0.0
     */
    bindFilterLayout() {
        const self = this;

        const baselineButtons = jQuery( '#wpsl-filter-baseline-buttons' ).html();
        const baselineOptions = jQuery( '#wpsl-filter-baseline-options' ).html();

        this._filterBaseline = {
            buttons: baselineButtons != null ? baselineButtons : jQuery( '#wpsl-result-filters' ).html(),
            options: baselineOptions != null ? baselineOptions : jQuery( '#wpsl-filter-options' ).html()
        };

        // Accordion toggling inside the nested preview (delegated, bound once).
        // Opening one child closes the others.
        jQuery( '#wpsl-filter-options' ).on( 'click', '.wpsl-nested-toggle', function( e ) {
            e.preventDefault();

            const $filter  = jQuery( this ).closest( '.wpsl-nested-filter' );
            const willOpen = ! $filter.hasClass( 'wpsl-nested-open' );

            if ( willOpen ) {
                jQuery( '#wpsl-filter-options .wpsl-nested-filter' ).removeClass( 'wpsl-nested-open' )
                    .find( '.wpsl-nested-toggle' ).attr( 'aria-expanded', 'false' );
            }

            $filter.toggleClass( 'wpsl-nested-open', willOpen );
            jQuery( this ).attr( 'aria-expanded', willOpen ? 'true' : 'false' );
        });

        jQuery( '#wpsl-filter-layout' ).on( 'change', function() {
            self.applyFilterLayoutPreview( jQuery( this ).val() );
        });
    },

    /**
     * Rebuild the preview filters into the given layout.
     *
     * Every layout is built from the cached baseline, not from the current DOM.
     *
     * @since 3.0.0
     * @param {string} layout horizontal | stacked | nested
     */
    applyFilterLayoutPreview( layout ) {
        if ( ! this._filterBaseline ) {
            return;
        }

        const $rf = jQuery( '#wpsl-result-filters' );
        const $fo = jQuery( '#wpsl-filter-options' );

        // Restore the horizontal baseline.
        $rf.html( this._filterBaseline.buttons ).removeClass( 'wpsl-filters-stacked wpsl-filters-nested' );
        $fo.html( this._filterBaseline.options );

        if ( layout === 'stacked' ) {
            $rf.addClass( 'wpsl-filters-stacked' );
        } else if ( layout === 'nested' ) {
            $rf.addClass( 'wpsl-filters-nested' );
            this.buildNestedFilterPreview();
        }

        this.bindFilterInteractions();
    },

    /**
     * Transform the horizontal baseline into the nested ( accordion ) layout.
     *
     * Reuses the existing flat option panels; child labels come from each
     * button's data-nested-label.
     *
     * @since 3.0.0
     */
    buildNestedFilterPreview() {
        const $rf = jQuery( '#wpsl-result-filters' );
        const $fo = jQuery( '#wpsl-filter-options' );

        const filtersLabel = $rf.attr( 'data-filters-label' ) || 'Filters';
        const $buttons     = $rf.children( 'button[data-filter]' );
        const iconHtml     = $buttons.first().find( 'svg' ).length ? $buttons.first().find( 'svg' )[0].outerHTML : '';

        const $nested  = jQuery( '<div data-id="wpsl-show-nested" class="wpsl-nested"></div>' );
        let   $actions = jQuery();

        $buttons.each( function() {
            const $btn = jQuery( this );

            // Skip filters that are disabled ( hidden ) in the preview.
            if ( ( $btn.attr( 'style' ) || '' ).indexOf( 'display: none' ) !== -1 ) {
                return;
            }

            const optionsId = $btn.attr( 'id' );
            const label     = $btn.attr( 'data-nested-label' ) || $btn.find( 'span' ).first().text();
            const isRadius  = ( optionsId === 'wpsl-show-radius' );
            const $panel    = $fo.children( '[data-id="' + optionsId + '"]' );

            // The shared actions move out of the categories panel to the bottom.
            const $panelActions = $panel.find( '.wpsl-filter-actions' );
            if ( $panelActions.length ) {
                $actions = $panelActions.detach();
            }

            const $child  = jQuery( '<div class="wpsl-nested-filter' + ( isRadius ? ' wpsl-nested-filter-radius' : '' ) + '"></div>' );
            const $toggle = jQuery( '<button type="button" class="wpsl-nested-toggle" aria-expanded="false"><div><span></span></div></button>' );

            $toggle.find( 'span' ).text( label );
            $toggle.append( iconHtml );

            const $opts = jQuery( '<div data-id="' + optionsId + '" class="wpsl-nested-options"></div>' );
            $opts.append( $panel.children() );

            $child.append( $toggle ).append( $opts );
            $nested.append( $child );
        });

        if ( $actions.length ) {
            $nested.append( $actions );
        }

        $fo.empty().append( $nested );

        // Collapse the button row into a single "Filters" parent button.
        const $parent = jQuery( '<button type="button" id="wpsl-show-nested"><div><span></span></div></button>' );
        $parent.find( 'span' ).text( filtersLabel );
        $parent.append( iconHtml );

        $rf.empty().append( $parent );
    },

    /**
     * Bind main style editor dropdown changes.
     *
     * @since 3.0.0
     */
    bindStyleEditorDropdown() {
        jQuery( '#wpsl-style-editor-dropdown' ).on( 'change', function() {
            const selectedSection = jQuery( this ).val();

            // Only hide style sections in the main editor, not in button colors section
            jQuery( '#wpsl-theme-styles-tab .wpsl-conditional-option > .wpsl-style-section' ).removeClass( 'wpsl-visible' );
            jQuery( '.wpsl-style-filter' ).hide();
            jQuery( '#wpsl-' + selectedSection + '-section' ).addClass( 'wpsl-visible' );

            // The submit button tooltip only applies to the header group.
            jQuery( '#wpsl-submit-style-info' ).toggle( selectedSection === 'header' );

            // Special handling for listing and popup sections - always show the sub-dropdown
            if ( selectedSection === 'listing' || selectedSection === 'popup' ) {
                jQuery( '#wpsl-' + selectedSection + '-style-sections' ).show();

                const firstSubSection = jQuery( '#wpsl-' + selectedSection + '-style-sections' ).val();
                if ( firstSubSection ) {
                    jQuery( '#wpsl-' + selectedSection + '-section .wpsl-' + firstSubSection + '-options' ).css( 'display', 'block' );
                }
            } else {
                jQuery( '#wpsl-' + selectedSection + '-style-sections' ).show();

                const $sectionUls = jQuery( '#wpsl-' + selectedSection + '-section ul' );
                if ( $sectionUls.length == 1 ) {
                    $sectionUls.css( 'display', 'block' );
                } else if ( $sectionUls.length > 1 ) {
                    const firstSubSection = jQuery( '#wpsl-' + selectedSection + '-style-sections' ).val();

                    if ( firstSubSection ) {
                        jQuery( '#wpsl-' + selectedSection + '-section .wpsl-' + firstSubSection + '-options' ).css( 'display', 'block' );
                    }
                }
            }
        });
    },

    /**
     * Bind style section dropdown changes.
     *
     * @since 3.0.0
     */
    bindStyleSections() {
        jQuery( '.wpsl-style-filter' ).on( 'change', function() {
            const sectionId = jQuery( '#wpsl-style-editor-dropdown' ).val();
            const subSection = jQuery( this ).val();

            jQuery( '#wpsl-' + sectionId + '-section > *' ).hide();

            if ( subSection === 'listing-cta' ) {
                const detailsCheckboxChecked = jQuery( '#wpsl-cta-details-button' ).is( ':checked' );
                if ( detailsCheckboxChecked ) {
                    jQuery( '.wpsl-cta-button-section[data-section="' + subSection + '"][data-button="more-details"]' ).show();
                    jQuery( '.wpsl-cta-button-section[data-section="' + subSection + '"][data-button="more-details"] ul' ).css( 'display', 'block' );
                }

                jQuery( '.wpsl-cta-button-section[data-section="' + subSection + '"][data-button="directions"]' ).show();
                jQuery( '.wpsl-cta-button-section[data-section="' + subSection + '"][data-button="directions"] ul' ).css( 'display', 'block' );
            } else {
                jQuery( '.wpsl-gradient-preview-row[data-section="' + subSection + '"]' ).each(function() {
                    jQuery( this ).show();
                    jQuery( this ).nextAll('.wpsl-gradient-controls-row').first().show();
                });

                jQuery( 'div[data-section="' + subSection + '"]' ).show();
                jQuery( '#wpsl-' + sectionId + '-section .wpsl-' + subSection + '-options' ).css( 'display', 'block' );

                if ( subSection === 'listing-icons' || subSection === 'popup-icons' ) {
                    jQuery( '#wpsl-' + sectionId + '-section .wpsl-' + subSection + '-options li' ).show();
                }
            }
        });
    },

    /**
     * Bind links inside a tab that jump to another appearance tab.
     *
     * The link's own href names the target tab panel; triggering the matching
     * nav item runs the full tab switch ( panel, header, reset button ).
     *
     * @since 3.0.0
     */
    bindTabJumpLinks() {
        jQuery( document ).on( 'click', '.wpsl-jump-to-tab', function( e ) {
            e.preventDefault();

            // A jump link can live inside a tooltip - close it before leaving.
            jQuery( '#wpsl-tooltip-portal' ).empty();
            jQuery( '.wpsl-info' ).removeData( 'portal-tooltip' );

            jQuery( '#wpsl-appearance-tabs a[href="' + jQuery( this ).attr( 'href' ) + '"]' ).trigger( 'click' );
        });
    },

    /**
     * Bind button style mode dropdown.
     *
     * @since 3.0.0
     */
    bindButtonStyleMode() {
        jQuery( '#wpsl-button-style-mode' ).on( 'change', function() {
            const selectedMode = jQuery( this ).val();

            jQuery( '[data-button-mode]' ).hide();
            jQuery( '[data-button-mode="' + selectedMode + '"]' ).show();

            if ( selectedMode === 'manage-styles' ) {
                jQuery( '.wpsl-submit-button-section' ).show();
                jQuery( '#wpsl-button-type-filter' ).trigger( 'change' );
            } else {
                jQuery( '.wpsl-submit-button-section' ).hide();
            }
        });
    },

    /**
     * Bind button type filter dropdown.
     *
     * @since 3.0.0
     */
    bindButtonTypeFilter() {
        jQuery( '#wpsl-button-type-filter' ).on( 'change', function() {
            const selectedType = jQuery( this ).val();

            jQuery( '.wpsl-button-style-section' ).hide();
            jQuery( '.wpsl-button-style-section[data-button-type="' + selectedType + '"]' ).show();

            if ( selectedType === 'primary' || selectedType === 'secondary' ) {
                jQuery( '.wpsl-cta-button-section' ).show();
            }
        });
    },

    /**
     * Bind button style toggle changes.
     *
     * @since 3.0.0
     */
    bindButtonStyleToggle() {
        const self = this;

        jQuery( '.wpsl-button-style-toggle' ).on( 'change', function() {
            const $radio = jQuery( this );
            const buttonTarget = $radio.data( 'button-target' );
            const styleType = $radio.data( 'style-type' );

            if ( ! buttonTarget || ! styleType ) {
                return;
            }

            const $targetButtons = jQuery( '.wpsl-styled-template-preview .' + buttonTarget );
            if ( $targetButtons.length ) {
                $targetButtons.removeClass( 'wpsl-primary-btn wpsl-secondary-btn' );
                $targetButtons.addClass( 'wpsl-' + styleType + '-btn' );
            }

            self.updateCTAPopupContent();
        });
    },

    /**
     * Bind overwrite theme styles checkbox.
     *
     * @since 3.0.0
     */
    bindOverwriteThemeStyles() {
        const self = this;

        jQuery( '#wpsl-overwrite-theme-styles' ).on( 'change', function() {
            const isEnabled = jQuery( this ).is( ':checked' );

            // This checkbox only appears on the theme styles tab, so toggle directly
            jQuery( '.wpsl-reset-styles' ).toggle( isEnabled );

            if ( ! jQuery( '#wpsl-' + state.mapService + '-wrap' ).length ) {
                return;
            }

            if ( isEnabled ) {
                if ( self.parent.themes ) {
                    self.parent.themes.applyInitialColors();
                } else {
                    self.parent.applyInitialColors();
                }

                if ( jQuery( '#wpsl-cta-buttons' ).is( ':checked' ) ) {
                    jQuery( '.wpsl-cta-section a' ).each( function() {
                        const $link = jQuery( this );
                        $link.addClass( 'wpsl-styled-btn' );

                        let buttonTarget = '';
                        if ( $link.hasClass( 'wpsl-details' ) ) {
                            buttonTarget = 'wpsl-details';
                        } else if ( $link.hasClass( 'wpsl-directions' ) ) {
                            buttonTarget = 'wpsl-directions';
                        } else if ( $link.hasClass( 'wpsl-zoom-here' ) ) {
                            buttonTarget = 'wpsl-zoom-here';
                        }

                        if ( buttonTarget ) {
                            const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="' + buttonTarget + '"]:checked' ).data( 'style-type' );
                            if ( selectedStyle ) {
                                $link.addClass( 'wpsl-' + selectedStyle + '-btn' );
                            }
                        }
                    });
                }
            } else {
                if ( self.parent.themes ) {
                    self.parent.themes.removeRootStyles();
                } else {
                    const wpslWrap = document.getElementById( 'wpsl-wrap' );
                    if ( wpslWrap ) {
                        wpslWrap.removeAttribute( 'style' );
                    }
                }

                const $ctaButtonsCheckbox = jQuery( '#wpsl-cta-buttons' );
                if ( $ctaButtonsCheckbox.is( ':checked' ) ) {
                    $ctaButtonsCheckbox.prop( 'checked', false ).trigger( 'change' );
                }

                jQuery( '.wpsl-cta-section a' ).removeClass( function( index, className ) {
                    return ( className.match( /(^|\s)wpsl-\S+-btn/g ) || [] ).join( ' ' );
                });

                if ( self.parent.buttons ) {
                    self.parent.buttons.applyDefaultSubmitStyles();
                }
            }
        });
    },

    /**
     * Bind custom focus outline checkbox.
     *
     * Removes / restores the focus outline CSS variables in the live preview.
     *
     * @since 3.0.0
     */
    bindCustomFocusOutline() {
        jQuery( '#wpsl-custom-focus-outline' ).on( 'change', function() {            
            const styleElement = document.getElementById( 'wpsl-wrap' );
            if ( ! styleElement ) {
                return;
            }

            const isEnabled = jQuery( this ).is( ':checked' );
            if ( isEnabled ) {
                const color = jQuery( '#wpsl-style-focus-outline' ).val();
                if ( color ) {
                    styleElement.style.setProperty( '--wpsl-focus-outline', '2px solid ' + color );
                    styleElement.style.setProperty( '--wpsl-focus-outline-color', color );
                    styleElement.style.setProperty( '--wpsl-focus-outline-button', '2px solid ' + color );
                }
            } else {
                styleElement.style.setProperty( '--wpsl-focus-outline', 'initial' );
                styleElement.style.setProperty( '--wpsl-focus-outline-color', 'initial' );
                styleElement.style.setProperty( '--wpsl-focus-outline-button', 'initial' );
            }
        } );
    },

    /**
     * Bind reset colors button.
     *
     * @since 3.0.0
     */
    bindResetColors() {
        const self = this;

        jQuery( '.wpsl-reset-styles' ).on( 'click', function( e ) {
            e.preventDefault();

            const activeTabId = jQuery( '#wpsl-appearance-content .wpsl-appearance-view:not(.wpsl-hidden)' ).attr( 'id' );
            if ( activeTabId === 'wpsl-button-styles-tab' ) {
                if ( self.parent.buttons ) {
                    self.parent.buttons.resetButtonStyles();
                }
            } else if ( activeTabId === 'wpsl-theme-styles-tab' ) {
                if ( self.parent.themes ) {
                    self.parent.themes.resetThemeStyles();
                }
            } else if ( activeTabId === 'wpsl-font-size-tab' ) {
                self.resetFontSizes();
            } else if ( activeTabId === 'wpsl-dimensions-tab' ) {
                self.resetDimensions();
            }

            return false;
        } );
    },

    /**
     * Bind CTA checkbox changes.
     *
     * @since 3.0.0
     */
    bindCTACheckboxes() {
        const self = this;

        const handleCTAToggle = ( $container, isChecked, ctaButtonsEnabled, insertAfter, $directionsTarget ) => {
            let $ctaButtons = $container.find( '.wpsl-cta-section' );
            let $detailsLink = $container.find( '.wpsl-details' );
            let $directionsLink = $container.find( '.wpsl-directions' );

            if ( isChecked ) {
                if ( ! $ctaButtons.length ) {
                    $ctaButtons = jQuery( '<div class="wpsl-cta-section"></div>' );
                    insertAfter.after( $ctaButtons );
                }

                if ( ! $detailsLink.length ) {
                    const buttonClass = ctaButtonsEnabled ? ' wpsl-styled-btn' : '';
                    $detailsLink = jQuery( '<a class="wpsl-details' + buttonClass + '" href="#">' + wpslL10n.moreDetails + '</a>' );
                } else if ( ctaButtonsEnabled ) {
                    $detailsLink.addClass( 'wpsl-styled-btn' );
                }

                if ( ctaButtonsEnabled ) {
                    const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="wpsl-details"]:checked' ).data( 'style-type' );
                    if ( selectedStyle ) {
                        $detailsLink.addClass( 'wpsl-' + selectedStyle + '-btn' );
                    }
                }

                $detailsLink.prependTo( $ctaButtons );

                // Move directions link into CTA buttons
                if ( $directionsLink.length ) {
                    $directionsLink.appendTo( $ctaButtons );
                }
            } else {
                $detailsLink.remove();

                // Only move directions link back if styled buttons are also disabled
                if ( ! ctaButtonsEnabled && $directionsLink.length && $directionsTarget && $directionsTarget.length ) {
                    $directionsLink.appendTo( $directionsTarget );

                    // Remove empty CTA buttons container
                    if ( $ctaButtons.length && ! $ctaButtons.children().length ) {
                        $ctaButtons.remove();
                    }
                }
            }
        };

        jQuery( '.wpsl-cta-checkbox' ).on( 'change', function() {
            const ctaType = jQuery( this ).data( 'cta-type' );
            const isChecked = jQuery( this ).is( ':checked' );
            const ctaButtonsEnabled = jQuery( '#wpsl-cta-buttons' ).is( ':checked' );

            if ( ctaType !== 'details' ) {
                return;
            }

            // Mirror the server-side class so the preview matches the front-end.
            jQuery( '#wpsl-wrap' ).toggleClass( 'wpsl-cta-details', isChecked );

            jQuery( '#wpsl-stores li' ).each( function() {
                const $storeItem = jQuery( this );
                const $directionWrap = $storeItem.find( '.wpsl-direction-wrap' );

                handleCTAToggle( $storeItem, isChecked, ctaButtonsEnabled, $directionWrap, $directionWrap );
            });

            const popupSelectors = ['.gm-style-iw-d', '.mapboxgl-popup-content', '.leaflet-popup-content-wrapper'];

            for ( let i = 0; i < popupSelectors.length; i++ ) {
                const $popupContent = jQuery( popupSelectors[i] );
                if ( $popupContent.length ) {
                    const $contactDetails = $popupContent.find( '.wpsl-contact-details' );
                    const $infoActions = $popupContent.find( '.wpsl-info-actions' );

                    handleCTAToggle( $popupContent, isChecked, ctaButtonsEnabled, $contactDetails, $infoActions );

                    break;
                }
            }

            self.updateCTAPopupContent();
        });
    },

    /**
     * Bind main CTA buttons toggle.
     *
     * @since 3.0.0
     */
    bindCTAButtons() {
        const self = this;

        jQuery( '#wpsl-cta-buttons' ).on( 'change', function() {
            const isChecked = jQuery( this ).is( ':checked' );

            // Mirror the server-side class so the preview matches the front-end.
            jQuery( '#wpsl-wrap' ).toggleClass( 'wpsl-styled-cta', isChecked );

            jQuery( '.wpsl-cta-font-size-option' ).toggle( isChecked );

            if ( ! isChecked ) {
                jQuery( '.wpsl-submit-button-section, .wpsl-button-style-section[data-button-type="submit"]' ).show();
                jQuery( '.wpsl-button-style-section[data-button-type="primary"], .wpsl-button-style-section[data-button-type="secondary"], .wpsl-cta-button-section' ).hide();

                self.parent.buttons.updateGradientPreview( 'submit' );
            } else {
                jQuery( '#wpsl-button-style-mode' ).val( 'manage-styles' );
                jQuery( '#wpsl-button-type-filter' ).val( 'submit' );
                jQuery( '#wpsl-button-style-mode' ).trigger( 'change' );
            }

            if ( isChecked ) {
                jQuery( '#wpsl-search-btn' ).removeAttr( 'style' );

                self.parent.buttons.updateGradientPreview( 'submit' );
            }

            // Handle listing items
            jQuery( '#wpsl-stores li' ).each( function() {
                const $storeItem = jQuery( this );
                const $directionWrap = $storeItem.find( '.wpsl-direction-wrap' );
                const $directionsLink = $storeItem.find( '.wpsl-directions' );
                const detailsEnabled = jQuery( '#wpsl-cta-details-button' ).is( ':checked' );

                let $ctaButtons = $storeItem.find( '.wpsl-cta-section' );

                if ( isChecked ) {
                    // Create CTA buttons container if it doesn't exist
                    if ( ! $ctaButtons.length && $directionWrap.length ) {
                        $ctaButtons = jQuery( '<div class="wpsl-cta-section"></div>' );
                        $directionWrap.after( $ctaButtons );
                    }

                    // Move directions link into CTA buttons if not already there
                    if ( $directionsLink.length && $ctaButtons.length && ! $ctaButtons.find( '.wpsl-directions' ).length ) {
                        $directionsLink.appendTo( $ctaButtons );
                    }

                    // Style all links in CTA buttons
                    $ctaButtons.find( 'a' ).each( function() {
                        const $link = jQuery( this );

                        $link.addClass( 'wpsl-styled-btn' );

                        let buttonTarget = '';

                        if ( $link.hasClass( 'wpsl-details' ) ) {
                            buttonTarget = 'wpsl-details';
                        } else if ( $link.hasClass( 'wpsl-directions' ) ) {
                            buttonTarget = 'wpsl-directions';
                        } else if ( $link.hasClass( 'wpsl-zoom-here' ) ) {
                            buttonTarget = 'wpsl-zoom-here';
                        }

                        if ( buttonTarget ) {
                            const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="' + buttonTarget + '"]:checked' ).data( 'style-type' );

                            if ( selectedStyle ) {
                                $link.addClass( 'wpsl-' + selectedStyle + '-btn' );
                            }
                        }
                    });
                } else {
                    // Remove styling from all links
                    $storeItem.find( '.wpsl-cta-section a, .wpsl-direction-wrap .wpsl-directions' ).removeClass( function( index, className ) {
                        return ( className.match( /(^|\s)wpsl-\S+-btn/g ) || [] ).join( ' ' );
                    });

                    // If details are disabled, move directions back to direction wrap
                    if ( ! detailsEnabled && $directionsLink.length && $directionWrap.length && $ctaButtons.length ) {
                        $directionsLink.appendTo( $directionWrap );

                        // Remove empty CTA buttons container
                        if ( ! $ctaButtons.children().length ) {
                            $ctaButtons.remove();
                        }
                    }
                }
            });

            // Handle popup
            const popupSelectors = ['.gm-style-iw-d', '.mapboxgl-popup-content', '.leaflet-popup-content-wrapper'];

            for ( let i = 0; i < popupSelectors.length; i++ ) {
                const $popupContent = jQuery( popupSelectors[i] );

                if ( $popupContent.length ) {
                    let $ctaButtons = $popupContent.find( '.wpsl-cta-section' );

                    const $infoActions = $popupContent.find( '.wpsl-info-actions' );
                    const $directionsLink = $popupContent.find( '.wpsl-directions' );
                    const $contactDetails = $popupContent.find( '.wpsl-contact-details' );
                    const detailsEnabled = jQuery( '#wpsl-cta-details-button' ).is( ':checked' );

                    if ( isChecked ) {
                        // Create CTA buttons container if it doesn't exist
                        if ( ! $ctaButtons.length && $contactDetails.length ) {
                            $ctaButtons = jQuery( '<div class="wpsl-cta-section"></div>' );
                            $contactDetails.after( $ctaButtons );
                        }

                        // Move directions link into CTA buttons if not already there
                        if ( $directionsLink.length && $ctaButtons.length && ! $ctaButtons.find( '.wpsl-directions' ).length ) {
                            $directionsLink.appendTo( $ctaButtons );
                        }

                        // Style all links in CTA buttons
                        $ctaButtons.find( 'a' ).each( function() {
                            const $link = jQuery( this );
                            $link.addClass( 'wpsl-styled-btn' );

                            let buttonTarget = '';

                            if ( $link.hasClass( 'wpsl-details' ) ) {
                                buttonTarget = 'wpsl-details';
                            } else if ( $link.hasClass( 'wpsl-directions' ) ) {
                                buttonTarget = 'wpsl-directions';
                            } else if ( $link.hasClass( 'wpsl-zoom-here' ) ) {
                                buttonTarget = 'wpsl-zoom-here';
                            }

                            if ( buttonTarget ) {
                                const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="' + buttonTarget + '"]:checked' ).data( 'style-type' );
                                if ( selectedStyle ) {
                                    $link.addClass( 'wpsl-' + selectedStyle + '-btn' );
                                }
                            }
                        });
                    } else {
                        // Remove styling from all links
                        $popupContent.find( '.wpsl-directions, .wpsl-details, .wpsl-zoom-here' ).removeClass( function( index, className ) {
                            return ( className.match( /(^|\s)wpsl-\S+-btn/g ) || [] ).join( ' ' );
                        });

                        // If details are disabled, move directions back to info-actions
                        if ( ! detailsEnabled && $directionsLink.length && $infoActions.length && $ctaButtons.length ) {
                            $directionsLink.appendTo( $infoActions );

                            // Remove empty CTA buttons container
                            if ( ! $ctaButtons.children().length ) {
                                $ctaButtons.remove();
                            }
                        }
                    }

                    break;
                }
            }

            self.updateCTAPopupContent();
        });
    },

    /**
     * Toggle the permalink warning for the "More details target" dropdown.
     *
     * Linking to a local page only works when landing pages (permalinks) are
     * enabled in the Local Pages settings; PHP passes that state through the
     * dropdown's data-permalinks-enabled attribute.
     *
     * @since 3.0.0
     */
    bindMoreDetailsTarget() {
        const $target = jQuery( '#wpsl-more-details-target' );
        if ( ! $target.length ) {
            return;
        }

        const $warning = jQuery( '.wpsl-more-details-warning' );

        $target.on( 'change', function() {
            const permalinksEnabled = jQuery( this ).data( 'permalinks-enabled' ) == 1;
            const showWarning       = ( ! permalinksEnabled && jQuery( this ).val() === 'landing_page' );

            $warning.toggleClass( 'wpsl-hide', ! showWarning );
        });
    },

    /**
     * Bind icon toggle checkbox.
     *
     * @since 3.0.0
     */
    bindIconToggle() {
        const self = this;

        jQuery( '#wpsl-enable-icons' ).on( 'change', function() {
            const isChecked = jQuery( this ).is( ':checked' );
            const $wpslWrap = jQuery( '#wpsl-wrap' );

            $wpslWrap.toggleClass( 'wpsl-has-icons', isChecked );

            const $iconElements = jQuery( '#wpsl-listing-style-sections option[value="listing-icons"], #wpsl-popup-style-sections option[value="popup-icons"], .wpsl-listing-icons-options, .wpsl-popup-icons-options' );

            if ( isChecked ) {
                if ( self.parent.icons ) {
                    self.parent.icons.add();
                }

                $iconElements.show();
            } else {
                if ( self.parent.icons ) {
                    self.parent.icons.remove();
                }

                $iconElements.hide();

                const $listingDropdown = jQuery( '#wpsl-listing-style-sections' );
                const $popupDropdown = jQuery( '#wpsl-popup-style-sections' );

                if ( $listingDropdown.val() === 'listing-icons' ) {
                    $listingDropdown.val( 'listing-results' ).trigger( 'change' );
                }

                if ( $popupDropdown.val() === 'popup-icons' ) {
                    $popupDropdown.val( 'popup-content' ).trigger( 'change' );
                }
            }

            // Update the stored popup content and refresh any open/bound popups
            if ( self.parent.icons ) {
                self.parent.icons.updatePopupContent( isChecked );
                self.parent.icons.refreshMarkerPopups();
            }
        });
    },

    /**
     * Bind icon dropdown open/close and selection.
     * 
     * On selection the hidden radio input is checked and a change event is
     * fired so bindIconRadios() can update the live preview as usual.
     *
     * @since 3.0.0
     */
    bindIconDropdowns() {

        // Toggle dropdown open/close
        jQuery( document ).on( 'click', '.wpsl-icon-dropdown-button', function( e ) {
            e.preventDefault();
            e.stopPropagation();

            const $menu = jQuery( this ).closest( '.wpsl-icon-dropdown-wrapper' ).find( '.wpsl-icon-dropdown-menu' );

            jQuery( '.wpsl-icon-dropdown-menu' ).not( $menu ).removeClass( 'show' );
            $menu.toggleClass( 'show' );
        });

        // Select an icon
        jQuery( document ).on( 'click', '.wpsl-icon-dropdown-item', function( e ) {
            e.preventDefault();
            e.stopPropagation();

            const $item    = jQuery( this );
            const $wrapper = $item.closest( '.wpsl-icon-dropdown-wrapper' );
            const $menu    = $wrapper.find( '.wpsl-icon-dropdown-menu' );
            const value    = $item.data( 'icon-value' );
            const group    = $wrapper.data( 'icon-group' );

            // Update selected state in menu
            $menu.find( '.wpsl-icon-dropdown-item' ).removeClass( 'selected' );
            $item.addClass( 'selected' );

            // Rebuild the button icon from the group + value
            let iconClass;

            if ( group === 'address' ) {
                iconClass = 'wpsl-icon-address-' + value;
            } else {
                iconClass = 'wpsl-icon-' + value;
            }

            $wrapper.find( '.wpsl-icon-dropdown-selected' ).html( '<span class="' + iconClass + '"></span>' );

            // Check the hidden radio and fire change so bindIconRadios() updates the preview
            $wrapper.find( 'input[type="radio"][value="' + value + '"]' ).prop( 'checked', true ).trigger( 'change' );

            $menu.removeClass( 'show' );
        });

        // Close when clicking outside any icon dropdown
        jQuery( document ).on( 'click', function( e ) {
            if ( ! jQuery( e.target ).closest( '.wpsl-icon-dropdown-wrapper' ).length ) {
                jQuery( '.wpsl-icon-dropdown-menu' ).removeClass( 'show' );
            }
        });

        // Close on Escape
        jQuery( document ).on( 'keydown', function( e ) {
            if ( e.key === 'Escape' ) {
                jQuery( '.wpsl-icon-dropdown-menu' ).removeClass( 'show' );
            }
        });
    },

    /**
     * Bind icon radio button changes.
     *
     * @since 3.0.0
     */
    bindIconRadios() {
        const self = this;

        jQuery( 'input[name="wpsl_appearance[icons][address]"]' ).on( 'change', function() {
            const selectedIcon = jQuery( this ).val();

            jQuery( '#wpsl-appearance-preview .wpsl-icon-address' ).each( function() {
                const $this = jQuery( this );
                const currentClass = $this.attr( 'class' );
                const newClass = currentClass.replace( /wpsl-icon-address-[\w-]+/g, 'wpsl-icon-address-' + selectedIcon );

                $this.attr( 'class', newClass.trim() );
            });

            if ( self.parent.icons ) {
                self.parent.icons.updatePopupContent( true );
                self.parent.icons.refreshMarkerPopups();
            }
        });

        jQuery( 'input[name="wpsl_appearance[icons][phone]"]' ).on( 'change', function() {
            const selectedIcon = jQuery( this ).val();

            jQuery( '#wpsl-appearance-preview .wpsl-contact-details span[class*="wpsl-icon-"]' ).filter( function() {
                return jQuery( this ).attr( 'class' ).match( /wpsl-icon-(phone|mobile-phone)/ );
            }).each( function() {
                const $this = jQuery( this );
                const currentClass = $this.attr( 'class' );
                const newClass = currentClass.replace( /wpsl-icon-[\w-]+/g, 'wpsl-icon-' + selectedIcon );

                $this.attr( 'class', newClass.trim() );
            });

            if ( self.parent.icons ) {
                self.parent.icons.updatePopupContent( true );
                self.parent.icons.refreshMarkerPopups();
            }
        });

        jQuery( 'input[name="wpsl_appearance[icons][email]"]' ).on( 'change', function() {
            const selectedIcon = jQuery( this ).val();

            jQuery( '#wpsl-appearance-preview .wpsl-contact-details span[class*="wpsl-icon-"]' ).filter( function() {
                return jQuery( this ).attr( 'class' ).match( /wpsl-icon-(email|email-outline)/ );
            }).each( function() {
                const $this = jQuery( this );
                const currentClass = $this.attr( 'class' );
                const newClass = currentClass.replace( /wpsl-icon-[\w-]+/g, 'wpsl-icon-' + selectedIcon );

                $this.attr( 'class', newClass.trim() );
            });

            if ( self.parent.icons ) {
                self.parent.icons.updatePopupContent( true );
                self.parent.icons.refreshMarkerPopups();
            }
        });
    },

    /**
     * Bind gradient color field changes.
     *
     * @since 3.0.0
     */
    bindGradientControls() {
        const self = this;

        jQuery( document ).on( 'change keyup', 'input.wpsl-gradient-color-field', function() {
            const $input = jQuery( this );
            const $ul = $input.closest( 'ul' );

            let buttonType = 'primary';

            if ( $ul.hasClass( 'wpsl-submit-options' ) ) {
                buttonType = 'submit';
            } else if ( $ul.hasClass( 'wpsl-general-secondary-options' ) ) {
                buttonType = 'general_secondary';
            } else if ( $ul.hasClass( 'wpsl-general-primary-options' ) ) {
                buttonType = 'general_primary';
            }

            self.parent.buttons.updateGradientPreview( buttonType );
        });
    },

    /**
     * Bind angle picker interactions.
     *
     * @since 3.0.0
     */
    bindAnglePicker() {
        const self = this;

        jQuery( document ).on( 'mousedown', '.wpsl-angle-picker', function( e ) {
            e.preventDefault();
            const $picker = jQuery( this );

            // Measured once: the dial can't move or resize mid-drag.
            const rect = $picker[0].getBoundingClientRect();

            self.parent.buttons.updateAngleFromMouse( e, $picker, rect );

            // rAF-throttled: mousemove fires faster than the repaint.
            let frame = null;
            let latest = null;

            jQuery( document ).on( 'mousemove.anglePicker', function( e ) {
                latest = e;

                if ( frame ) {
                    return;
                }

                frame = requestAnimationFrame( function() {
                    frame = null;

                    self.parent.buttons.updateAngleFromMouse( latest, $picker, rect );
                } );
            });

            jQuery( document ).on( 'mouseup.anglePicker', function() {
                jQuery( document ).off( '.anglePicker' );

                if ( frame ) {
                    cancelAnimationFrame( frame );
                    frame = null;
                }

                // Apply the final angle a cancelled frame would have dropped.
                if ( latest ) {
                    self.parent.buttons.updateAngleFromMouse( latest, $picker, rect );
                }
            });
        });

        jQuery( document ).on( 'input', '.wpsl-gradient-angle-input', function() {
            const $input = jQuery( this );
            const buttonType = $input.data( 'button-type' );
            const angle = $input.val();

            jQuery( '.wpsl-angle-picker[data-button-type="' + buttonType + '"] .wpsl-angle-picker-dot' ).css( 'transform', 'rotate(' + angle + 'deg)' );

            self.parent.buttons.updateGradientPreview( buttonType );
        });
    },

    /**
     * Bind Google Maps style source dropdown toggle.
     *
     * @since 3.0.0
     */
    bindGmapsStyleSource() {
        const $dropdown = jQuery( '#wpsl-gmaps-styles' );

        if ( $dropdown.length === 0 ) {
            return;
        }

        const toggleJsonNotice = function() {
            const style = $dropdown.val();

            jQuery( '[id^="wpsl-gmaps-style-"]' ).hide();
            jQuery( '#wpsl-gmaps-style-' + style ).show();
            jQuery( '.wpsl-map-style-source .wpsl-info' ).toggle( style !== 'cloud_based' );

            // Show deprecation notice only for JSON styling
            jQuery( '#wpsl-json-style-notice' ).toggle( style === 'json' );
        };

        $dropdown.on( 'change', toggleJsonNotice );

        toggleJsonNotice();
    },

    fontSizeVarMap: {
        'wpsl-font-size-base':          '--wpsl-font-size-base',
        'wpsl-font-size-location-name': '--wpsl-font-size-location-name',
        'wpsl-font-size-cta-buttons':   '--wpsl-font-size-cta-buttons',
    },

    /**
     * Bind font size slider changes.
     *
     * @since 3.0.0
     */
    bindFontSizeSliders() {
        const self = this;

        jQuery( '.wpsl-font-size-slider' ).on( 'input', function() {
            const $slider = jQuery( this );
            const value = $slider.val();
            const $valueDisplay = $slider.next( '.wpsl-font-size-value' );

            if ( $valueDisplay.length ) {
                $valueDisplay.text( value + ' px' );
            }

            const cssVar = self.fontSizeVarMap[ $slider.attr( 'id' ) ];
            const wpslWrap = document.getElementById( 'wpsl-wrap' );

            if ( cssVar && wpslWrap ) {
                wpslWrap.style.setProperty( cssVar, value + 'px' );
            }
        });
    },

    /**
     * Bind the "Overwrite theme font size defaults" toggle: unchecked
     * drops the CSS vars so var() falls back to inherit in the preview.
     *
     * @since 3.0.0
     */
    bindOverwriteFontSizes() {
        const self = this;

        jQuery( '#wpsl-overwrite-font-sizes' ).on( 'change', function() {
            const isChecked = jQuery( this ).is( ':checked' );
            const activeTabId = jQuery( '#wpsl-appearance-content .wpsl-appearance-view:not(.wpsl-hidden)' ).attr( 'id' );

            if ( activeTabId === 'wpsl-font-size-tab' ) {
                jQuery( '.wpsl-reset-styles' ).toggle( isChecked );
            }

            self.applyFontSizeVars( isChecked );
        });
    },

    /**
     * Apply or remove font-size CSS vars and rules on #wpsl-wrap.
     *
     * @since 3.0.0
     * @param {boolean} isEnabled
     */
    applyFontSizeVars( isEnabled ) {
        const wpslWrap = document.getElementById( 'wpsl-wrap' );
        if ( ! wpslWrap ) {
            return;
        }

        let styleEl = document.getElementById( 'wpsl-font-size-rules' );
        if ( styleEl ) {
            styleEl.remove();
        }

        if ( isEnabled ) {
            Object.entries( this.fontSizeVarMap ).forEach( ( [ sliderId, cssVar ] ) => {
                const slider = document.getElementById( sliderId );

                if ( slider ) {
                    wpslWrap.style.setProperty( cssVar, slider.value + 'px' );
                }
            });

            // Inject CSS rules that apply the variables
            styleEl = document.createElement( 'style' );
            styleEl.id = 'wpsl-font-size-rules';
            styleEl.textContent = `
                #wpsl-wrap * { font-size: var(--wpsl-font-size-base); }
                #wpsl-wrap .wpsl-location-content > strong:first-child a { font-size: var(--wpsl-font-size-location-name) !important; }
                #wpsl-wrap .wpsl-cta-section * { font-size: var(--wpsl-font-size-base) !important; }
            `;

            document.head.appendChild( styleEl );
        } else {
            Object.values( this.fontSizeVarMap ).forEach( ( cssVar ) => {
                wpslWrap.style.removeProperty( cssVar );
            });
        }
    },

    /**
     * Reset all font size sliders to their default value (14px).
     *
     * @since 3.0.0
     */
    resetFontSizes() {
        const defaultSize = 14;
        const wpslWrap = document.getElementById( 'wpsl-wrap' );

        Object.entries( this.fontSizeVarMap ).forEach( ( [ sliderId, cssVar ] ) => {
            const slider = document.getElementById( sliderId );

            if ( ! slider ) {
                return;
            }

            slider.value = defaultSize;

            const $valueDisplay = jQuery( slider ).next( '.wpsl-font-size-value' );

            if ( $valueDisplay.length ) {
                $valueDisplay.text( defaultSize + ' px' );
            }

            if ( cssVar && wpslWrap ) {
                wpslWrap.style.setProperty( cssVar, defaultSize + 'px' );
            }
        } );
    },

    /**
     * Reset all visible dimension dropdowns to 'default' and trigger live preview update.
     *
     * @since 3.0.0
     */
    resetDimensions() {
        const selectors = [
            '#wpsl-map-height-mode',
            '#wpsl-map-height-mode-horizontal',
            '#wpsl-results-height-mode-horizontal',
            '#wpsl-sl-height-mode',
            '#wpsl-search-width-mode',
        ];

        selectors.forEach( ( selector ) => {
            const $el = jQuery( selector );
            if ( $el.is( ':visible' ) && $el.val() !== 'default' ) {
                $el.val( 'default' ).trigger( 'change' );
            }
        } );
    },

    /**
     * Bind dimension width controls (search field and label widths).
     * Updates CSS variables on #wpsl-wrap when dropdowns or inputs change.
     *
     * @since 3.0.0
     */
    bindDimensionWidths() {
        const wpslWrap = document.getElementById( 'wpsl-wrap' );
        
        if ( ! wpslWrap ) {
            return;
        }

        const updateSearchWidth = () => {
            if ( ! jQuery( '#wpsl-search-width-mode' ).is( ':visible' ) ) return;

            const mode = jQuery( '#wpsl-search-width-mode' ).val();
            const customWidth = jQuery( '#wpsl-search-width' ).val();
            // 'Default' is the v2 width, the same the frontend prints for it.
            const value = mode === 'default' ? '179px' : ( customWidth ? customWidth + 'px' : '179px' );

            wpslWrap.style.setProperty( '--wpsl-search-input-width', value );

            // The category dropdown is sized to the search field, so re-measure
            // it against the new width ( and let it shrink back on 'Default' ).
            alignSearchColumns();
        };

        // Update map height (default template only)
        const updateMapHeight = ( event ) => {
            if ( ! jQuery( '#wpsl-map-height-mode' ).is( ':visible' ) ) return;

            const mode = jQuery( '#wpsl-map-height-mode' ).val();
            if ( mode === 'default' ) {
                jQuery( '.wpsl-map-height-auto-info' ).show();
                wpslWrap.style.setProperty( '--wpsl-map-height', '350px' );
                return;
            } else {
                jQuery( '.wpsl-map-height-auto-info' ).hide();
            }
            
            let height = parseInt( jQuery( '#wpsl-design-height' ).val() );
            
            // Only enforce minimum on blur/change, not during typing
            if ( event && event.type === 'input' ) {
                if ( height && height >= 250 ) {
                    const value = height + 'px';
                    wpslWrap.style.setProperty( '--wpsl-map-height', value );
                }
                return;
            }
            
            if ( height && height < 250 ) {
                height = 250;
                jQuery( '#wpsl-design-height' ).val( 250 );
            }
            
            const value = ( height && height >= 250 ) ? height + 'px' : '350px';
            wpslWrap.style.setProperty( '--wpsl-map-height', value );
        };

        // Bind search width mode dropdown
        jQuery( document ).on( 'change', '#wpsl-search-width-mode', updateSearchWidth );
        
        // Bind search width input
        jQuery( document ).on( 'input change', '#wpsl-search-width', updateSearchWidth );
        
        const updateHorizontalMapHeight = ( event ) => {
            if ( ! jQuery( '#wpsl-map-height-mode-horizontal' ).is( ':visible' ) ) return;

            const mode = jQuery( '#wpsl-map-height-mode-horizontal' ).val();
            if ( mode === 'default' ) {
                jQuery( '.wpsl-map-height-auto-info-horizontal' ).show();
                wpslWrap.style.setProperty( '--wpsl-map-height', '350px' );
                return;
            } else {
                jQuery( '.wpsl-map-height-auto-info-horizontal' ).hide();
            }
            
            let height = parseInt( jQuery( '#wpsl-map-height' ).val() );
            
            // Only enforce minimum on blur/change, not during typing
            if ( event && event.type === 'input' ) {
                if ( height && height >= 250 ) {
                    const value = height + 'px';

                    wpslWrap.style.setProperty( '--wpsl-map-height', value );
                }
                return;
            }
            
            if ( height && height < 250 ) {
                height = 250;
                jQuery( '#wpsl-map-height' ).val( 250 );
            }
            
            const value = ( height && height >= 250 ) ? height + 'px' : '350px';
            wpslWrap.style.setProperty( '--wpsl-map-height', value );
        };

        const updateHorizontalResultsHeight = ( event ) => {
            if ( ! jQuery( '#wpsl-results-height-mode-horizontal' ).is( ':visible' ) ) return;
            
            const mode = jQuery( '#wpsl-results-height-mode-horizontal' ).val();
            if ( mode === 'default' ) {
                jQuery( '.wpsl-results-height-auto-info-horizontal' ).show();

                wpslWrap.style.setProperty( '--wpsl-results-height', '350px' );

                return;
            } else {
                jQuery( '.wpsl-results-height-auto-info-horizontal' ).hide();
            }

            // Show all results: no max height, the list grows with its content.
            if ( mode === 'all' ) {
                wpslWrap.style.setProperty( '--wpsl-results-height', 'none' );

                return;
            }
            
            let height = parseInt( jQuery( '#wpsl-results-height' ).val() );
            
            // Only enforce minimum on blur/change, not during typing
            if ( event && event.type === 'input' ) {
                if ( height && height >= 250 ) {
                    const value = height + 'px';
                    wpslWrap.style.setProperty( '--wpsl-results-height', value );
                }

                return;
            }
            
            if ( height && height < 250 ) {
                height = 250;
                jQuery( '#wpsl-results-height' ).val( 250 );
            }
            
            const value = ( height && height >= 250 ) ? height + 'px' : '350px';
            wpslWrap.style.setProperty( '--wpsl-results-height', value );
        };

        // Update vertical theme container height
        const updateVerticalHeight = ( event ) => {
            if ( ! jQuery( '#wpsl-sl-height-mode' ).is( ':visible' ) ) return;
            
            const mode = jQuery( '#wpsl-sl-height-mode' ).val();
            if ( mode === 'default' ) {
                jQuery( '.wpsl-sl-height-auto-info' ).show();
                wpslWrap.style.setProperty( '--wpsl-container-height', '450px' );

                return;
            } else {
                jQuery( '.wpsl-sl-height-auto-info' ).hide();
            }
            
            let height = parseInt( jQuery( '#wpsl-sl-height' ).val() );
            
            // Only enforce minimum on blur/change, not during typing
            if ( event && event.type === 'input' ) {
                if ( height && height >= 250 ) {
                    const value = height + 'px';
                    wpslWrap.style.setProperty( '--wpsl-container-height', value );
                }
                
                return;
            }
            
            if ( height && height < 250 ) {
                height = 250;
                jQuery( '#wpsl-sl-height' ).val( 250 );
            }
            
            const value = ( height && height >= 250 ) ? height + 'px' : '450px';
            wpslWrap.style.setProperty( '--wpsl-container-height', value );
        };

        // Bind map height mode dropdown
        jQuery( document ).on( 'change', '#wpsl-map-height-mode', updateMapHeight );
        jQuery( document ).on( 'change', '#wpsl-map-height-mode-horizontal', updateHorizontalMapHeight );
        jQuery( document ).on( 'change', '#wpsl-results-height-mode-horizontal', updateHorizontalResultsHeight );
        jQuery( document ).on( 'change', '#wpsl-sl-height-mode', updateVerticalHeight );

        // Bind map height input
        jQuery( document ).on( 'input', '#wpsl-design-height', updateMapHeight );
        jQuery( document ).on( 'blur change', '#wpsl-design-height', updateMapHeight );
        jQuery( document ).on( 'input', '#wpsl-map-height', updateHorizontalMapHeight );
        jQuery( document ).on( 'blur change', '#wpsl-map-height', updateHorizontalMapHeight );
        jQuery( document ).on( 'input', '#wpsl-results-height', updateHorizontalResultsHeight );
        jQuery( document ).on( 'blur change', '#wpsl-results-height', updateHorizontalResultsHeight );
        jQuery( document ).on( 'input', '#wpsl-sl-height', updateVerticalHeight );
        jQuery( document ).on( 'blur change', '#wpsl-sl-height', updateVerticalHeight );

        // Set initial values on page load
        updateSearchWidth();
        updateMapHeight();
        updateHorizontalMapHeight();
        updateHorizontalResultsHeight();
        updateVerticalHeight();
    },

    /**
     * Apply CTA styles to template preview.
     *
     * @since 3.0.0
     * @param {boolean} ctaButtonsEnabled - Whether styled buttons are enabled
     * @param {boolean} detailsEnabled - Whether details button is enabled
     */
    applyCTAStyles( ctaButtonsEnabled, detailsEnabled ) {

        // Handle listing items
        jQuery( '#wpsl-stores li' ).each( function() {
            const $storeItem = jQuery( this );
            const $directionWrap = $storeItem.find( '.wpsl-direction-wrap' );
            const $directionsLink = $storeItem.find( '.wpsl-directions' );
            const $detailsLink = $storeItem.find( '.wpsl-details' );

            let $ctaButtons = $storeItem.find( '.wpsl-cta-section' );

            // If styled buttons or details are enabled, create CTA buttons container
            if ( ( ctaButtonsEnabled || detailsEnabled ) && ! $ctaButtons.length && $directionWrap.length ) {
                $ctaButtons = jQuery( '<div class="wpsl-cta-section"></div>' );
                $directionWrap.after( $ctaButtons );
            }

            // Handle details button
            if ( detailsEnabled && $ctaButtons.length ) {
                if ( ! $detailsLink.length ) {
                    const buttonClass = ctaButtonsEnabled ? ' wpsl-styled-btn' : '';
                    const $newDetailsLink = jQuery( '<a class="wpsl-details' + buttonClass + '" href="#">' + wpslL10n.moreDetails + '</a>' );

                    if ( ctaButtonsEnabled ) {
                        const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="wpsl-details"]:checked' ).data( 'style-type' );

                        if ( selectedStyle ) {
                            $newDetailsLink.addClass( 'wpsl-' + selectedStyle + '-btn' );
                        }
                    }

                    $newDetailsLink.prependTo( $ctaButtons );
                }
            }

            // Move directions link into CTA buttons if needed
            if ( ( ctaButtonsEnabled || detailsEnabled ) && $directionsLink.length && $ctaButtons.length ) {
                if ( ! $ctaButtons.find( '.wpsl-directions' ).length ) {
                    $directionsLink.appendTo( $ctaButtons );
                }
            }

            // Apply styling if enabled
            if ( ctaButtonsEnabled && $ctaButtons.length ) {
                $ctaButtons.find( 'a' ).each( function() {
                    const $link = jQuery( this );
                    let buttonTarget = '';

                    $link.addClass( 'wpsl-styled-btn' );

                    if ( $link.hasClass( 'wpsl-details' ) ) {
                        buttonTarget = 'wpsl-details';
                    } else if ( $link.hasClass( 'wpsl-directions' ) ) {
                        buttonTarget = 'wpsl-directions';
                    }

                    if ( buttonTarget ) {
                        const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="' + buttonTarget + '"]:checked' ).data( 'style-type' );
                        
                        if ( selectedStyle ) {
                            $link.addClass( 'wpsl-' + selectedStyle + '-btn' );
                        }
                    }
                });
            }
        });

        // Handle popup
        const popupSelectors = ['.gm-style-iw-d', '.mapboxgl-popup-content', '.leaflet-popup-content-wrapper'];

        for ( let i = 0; i < popupSelectors.length; i++ ) {
            const $popupContent = jQuery( popupSelectors[i] );

            if ( $popupContent.length ) {
                const $directionsLink = $popupContent.find( '.wpsl-directions' );
                const $contactDetails = $popupContent.find( '.wpsl-contact-details' );
                const $detailsLink = $popupContent.find( '.wpsl-details' );

                let $ctaButtons = $popupContent.find( '.wpsl-cta-section' );

                // If styled buttons or details are enabled, create CTA buttons container
                if ( ( ctaButtonsEnabled || detailsEnabled ) && ! $ctaButtons.length && $contactDetails.length ) {
                    $ctaButtons = jQuery( '<div class="wpsl-cta-section"></div>' );
                    $contactDetails.after( $ctaButtons );
                }

                // Handle details button
                if ( detailsEnabled && $ctaButtons.length ) {
                    if ( ! $detailsLink.length ) {
                        const buttonClass = ctaButtonsEnabled ? ' wpsl-styled-btn' : '';
                        const $newDetailsLink = jQuery( '<a class="wpsl-details' + buttonClass + '" href="#">' + wpslL10n.moreDetails + '</a>' );

                        if ( ctaButtonsEnabled ) {
                            const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="wpsl-details"]:checked' ).data( 'style-type' );
                            if ( selectedStyle ) {
                                $newDetailsLink.addClass( 'wpsl-' + selectedStyle + '-btn' );
                            }
                        }

                        $newDetailsLink.prependTo( $ctaButtons );
                    }
                }

                // Move directions link into CTA buttons if needed
                if ( ( ctaButtonsEnabled || detailsEnabled ) && $directionsLink.length && $ctaButtons.length ) {
                    if ( ! $ctaButtons.find( '.wpsl-directions' ).length ) {
                        $directionsLink.appendTo( $ctaButtons );
                    }
                }

                // Apply styling if enabled
                if ( ctaButtonsEnabled && $ctaButtons.length ) {
                    $ctaButtons.find( 'a' ).each( function() {
                        const $link = jQuery( this );

                        let buttonTarget = '';

                        $link.addClass( 'wpsl-styled-btn' );

                        if ( $link.hasClass( 'wpsl-details' ) ) {
                            buttonTarget = 'wpsl-details';
                        } else if ( $link.hasClass( 'wpsl-directions' ) ) {
                            buttonTarget = 'wpsl-directions';
                        }

                        if ( buttonTarget ) {
                            const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="' + buttonTarget + '"]:checked' ).data( 'style-type' );

                            if ( selectedStyle ) {
                                $link.addClass( 'wpsl-' + selectedStyle + '-btn' );
                            }
                        }
                    });
                }

                break;
            }
        }
    },

    /**
     * Update the stored popup content (wpslL10n.popupContent)
     * to reflect the current CTA button state.
     *
     * @since 3.0.0
     */
    updateCTAPopupContent() {
        if ( typeof wpslL10n === 'undefined' || ! wpslL10n.popupContent ) {
            return;
        }

        const ctaButtonsEnabled = jQuery( '#wpsl-cta-buttons' ).is( ':checked' );
        const detailsEnabled = jQuery( '#wpsl-cta-details-button' ).is( ':checked' );
        const $popup = jQuery( '<div>' ).html( wpslL10n.popupContent );
        const $infoActions = $popup.find( '.wpsl-info-actions' );

        if ( ! $infoActions.length ) {
            return;
        }

        // Toggle wpsl-cta-section class on the container
        $infoActions.toggleClass( 'wpsl-cta-section', ctaButtonsEnabled || detailsEnabled );

        // Handle details link
        let $detailsLink = $infoActions.find( '.wpsl-details' );

        if ( detailsEnabled ) {
            if ( ! $detailsLink.length ) {
                $detailsLink = jQuery( '<a class="wpsl-details" href="#">' + wpslL10n.moreDetails + '</a>' );
                $infoActions.prepend( $detailsLink );
            }
        } else {
            $detailsLink.remove();
        }

        // Update styled classes on all action links
        $infoActions.find( 'a' ).each( function() {
            const $link = jQuery( this );

            // Remove existing button style classes
            $link.removeClass( function( index, className ) {
                return ( className.match( /(^|\s)wpsl-\S+-btn/g ) || [] ).join( ' ' );
            });

            if ( ctaButtonsEnabled ) {
                $link.addClass( 'wpsl-styled-btn' );

                let buttonTarget = '';

                if ( $link.hasClass( 'wpsl-details' ) ) {
                    buttonTarget = 'wpsl-details';
                } else if ( $link.hasClass( 'wpsl-directions' ) ) {
                    buttonTarget = 'wpsl-directions';
                } else if ( $link.hasClass( 'wpsl-zoom-here' ) ) {
                    buttonTarget = 'wpsl-zoom-here';
                }

                if ( buttonTarget ) {
                    const selectedStyle = jQuery( 'input.wpsl-button-style-toggle[data-button-target="' + buttonTarget + '"]:checked' ).data( 'style-type' );

                    if ( selectedStyle ) {
                        $link.addClass( 'wpsl-' + selectedStyle + '-btn' );
                    }
                }
            }
        });

        wpslL10n.popupContent = $popup.html();

        if ( this.parent.icons ) {
            this.parent.icons.refreshMarkerPopups();
        }
    },

    /**
     * Set initial reset button visibility on page load.
     *
     * @since 3.0.0
     */
    setInitialResetButtonVisibility() {
        jQuery( '.wpsl-reset-styles' ).hide();
    },

    /**
     * Toggle dimension fields based on active template.
     *
     * - Horizontal: Shows map_height and results_height separately
     * - Vertical: Shows sl_height (store locator height)
     * - Default: Shows map_and_results_height (combined)
     * - Panel templates (vertical): Hides search_width and label_width
     *
     * @since 3.0.0
     */
    toggleDimensionFields() {
        const templateId = jQuery( '#wpsl-appearance-preview' ).attr( 'data-template' );
        const isHorizontal = ( templateId === 'horizontal' );
        const isVertical = ( templateId === 'vertical' );

        /**
         * Show all row elements in a group and restore each conditional-option
         * div's visibility from its preceding dropdown's current value.
         *
         * @param {string} selector CSS class selector for the group
         */
        const showGroup = ( selector ) => {
            jQuery( selector ).not( '.wpsl-conditional-option' ).show();

            jQuery( selector + '.wpsl-conditional-option' ).each( function() {
                const val = jQuery( this ).prev().find( 'select.wpsl-has-conditional-option' ).val();
                
                jQuery( this ).toggle( val === 'custom' );
            } );
        };

        jQuery( '.wpsl-horizontal-dimensions, .wpsl-vertical-dimensions, .wpsl-default-dimensions, .wpsl-non-panel-dimensions' ).hide();

        if ( isHorizontal ) {
            showGroup( '.wpsl-horizontal-dimensions' );
        } else if ( isVertical ) {
            showGroup( '.wpsl-vertical-dimensions' );
        } else {
            showGroup( '.wpsl-default-dimensions' );
        }

        if ( ! isVertical ) {
            showGroup( '.wpsl-non-panel-dimensions' );
        }
    },

    /**
     * Rebind handlers after template change.
     *
     * @since 3.0.0
     */
    rebind() {
        this.bindFilterInteractions();
    }
};