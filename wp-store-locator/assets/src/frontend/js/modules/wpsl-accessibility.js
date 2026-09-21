/**
 * Accessibility module for WPSL frontend
 * 
 * Centralizes all keyboard navigation, ARIA management, and focus handling.
 * 
 * @since 3.0.0
 */

export const accessibility = {
    /**
     * Keyboard navigation handlers for various UI components.
     * 
     * @since 3.0.0
     */
    keyboard: {
        /**
         * Handle keyboard navigation for filter checkboxes.
         * Supports ArrowUp, ArrowDown, and Escape keys.
         * 
         * @since 3.0.0
         * @param {Event}  e                The keyboard event
         * @param {jQuery} $currentCheckbox The currently focused checkbox
         * @param {jQuery} $dropdown        The dropdown container
         * @param {jQuery} $filters         All filter elements
         * @param {Function} closeAllCallback Callback to close all filters
         */
        handleFilterCheckboxNavigation: function( e, $currentCheckbox, $dropdown, $filters, closeAllCallback ) {
            const $items = $dropdown.find( 'input[type="checkbox"]' );
            const currentIndex = $items.index( $currentCheckbox );

            switch ( e.key ) {
                case 'ArrowDown':
                    e.preventDefault();
                    $items.eq( ( currentIndex + 1 ) % $items.length ).trigger( 'focus' );
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    $items.eq( ( currentIndex - 1 + $items.length ) % $items.length ).trigger( 'focus' );
                    break;
                case 'Escape':
                    if ( typeof closeAllCallback === 'function' ) {
                        closeAllCallback();
                    }

                    $dropdown.find( 'button' ).trigger( 'focus' );
                    break;
            }
        },

        /**
         * Handle keyboard navigation for styled dropdown elements.
         * Supports Tab, Enter, Space, Arrow keys, PageUp/PageDown.
         * 
         * @since 3.0.0
         * @param {Object} config Configuration object with search wrap selectors
         */
        handleDropdownNavigation: function( config ) {
            const $searchWrapInput = jQuery( '#wpsl-search-wrap input' );
            const $searchWrapButton = jQuery( '#wpsl-search-wrap button' );

            let dropdownWrap, id, nextDropdown, prevCount, filterCount;

            // Find the last button in a filter group to manage focus flow.
            jQuery.each( jQuery( '#wpsl-search-wrap form' ).children(), function( i ) {
                if ( jQuery( this ).find( 'button' ).length ) {
                    if ( ! jQuery( this ).next().find( 'button' ).length ) {
                        jQuery( this ).find( 'button' ).last().addClass( 'input-fields-next' );
                    }
                }
            });

            // Track focus state on input elements.
            $searchWrapInput.on( 'focusin', function( e ) {
                $searchWrapInput.removeClass( 'wpsl-has-focus' );
                jQuery( ':focus' ).addClass( 'wpsl-has-focus' );
            });

            // Handle focus on dropdown buttons.
            jQuery( '#wpsl-search-wrap .wpsl-dropdown button' ).on( 'focusin', function( e ) {
                $searchWrapInput.removeClass('wpsl-has-focus');
                $searchWrapButton.removeClass('wpsl-active-button');
                
                jQuery( this ).addClass('wpsl-active-button');

                id = jQuery( '.wpsl-active-button' ).closest('.wpsl-dropdown').parent('div').attr('id');

                if ( ! jQuery( '#' + id + ' .wpsl-dropdown' ).hasClass('wpsl-active') ) {
                    jQuery( '#wpsl-search-wrap .wpsl-dropdown' ).removeClass('wpsl-active');
                }

                // Handle keyboard events on dropdown items.
                jQuery( '#' + id + ' li' ).off( 'keyup' ).on( 'keyup', function ( e ) {
                    dropdownWrap = jQuery( '#' + id + ' .wpsl-dropdown' );

                    // Tab key - manage focus flow
                    if ( e.which === 9 ) {
                        $searchWrapInput.removeClass( 'wpsl-has-focus' );

                        if ( e.shiftKey ) {
                            // Shift+Tab - move backwards
                            if ( jQuery( '#' + id + ' button' ).hasClass( 'wpsl-active-button' ) ) {
                                filterCount = Number( jQuery( '.wpsl-active-button' ).attr( 'data-filter-count' ) );
                                prevCount = Math.abs( filterCount - 1 );

                                jQuery( '#wpsl-search-wrap' ).find( 'button[data-filter-count="' + prevCount + '"]' ).addClass( 'wpsl-prev-focus' ).trigger( 'focus' );
                            } else {
                                jQuery( '#' + id + ' button' ).addClass( 'wpsl-active-button' ).trigger( 'focus' );
                            }
                        } else {
                            // Tab - move forwards
                            filterCount = Number( jQuery( '.wpsl-active-button' ).attr( 'data-filter-count' ) ) + 1;
                            nextDropdown = jQuery( '#wpsl-search-wrap' ).find( 'button[data-filter-count="' + filterCount + '"]' );

                            if ( ! jQuery( '#' + id + ' .wpsl-dropdown' ).hasClass( 'wpsl-active' ) ) {
                                if ( nextDropdown.length || jQuery( '#' + id + ' .wpsl-dropdown button' ).hasClass( 'input-fields-next' ) ) {

                                    if ( jQuery( '#' + id + ' .wpsl-dropdown button' ).hasClass( 'input-fields-next' ) && jQuery( '#' + id + ' .wpsl-dropdown button' ).hasClass( 'wpsl-active-button' ) ) {
                                        $searchWrapButton.removeClass( 'wpsl-active-button' );

                                        if ( jQuery.inArray( id, [ 'wpsl-radius', 'wpsl-results' ] ) > 0 ) {
                                            jQuery( '#' + id + '' ).parent().next().find( 'input' ).first().trigger( 'focus' );
                                        } else {
                                            jQuery( '#' + id + '' ).next().find( 'input' ).first().trigger( 'focus' );
                                        }
                                    } else {
                                        nextDropdown.trigger( 'focus' );
                                    }
                                } else {
                                    jQuery( '#wpsl-search-btn' ).trigger( 'focus' );
                                    $searchWrapButton.removeClass( 'wpsl-active-button' );
                                }
                            }
                        }
                    }

                    // Enter - select item
                    if ( e.which === 13 ) {
                        dropdownWrap.find( 'button' ).text( jQuery( this ).text() ).attr( 'data-value', jQuery( this ).attr( 'data-value' ) );
                        dropdownWrap.find( 'li' ).removeClass( 'wpsl-selected-dropdown' );

                        jQuery( '.wpsl-active' ).find( 'li' ).removeClass( 'wpsl-selected-dropdown' );
                        jQuery( this ).addClass( 'wpsl-selected-dropdown' );

                        dropdownWrap.find( '.wpsl-selected-item' ).next().css( 'height', 0 );
                        dropdownWrap.removeClass( 'wpsl-active' );
                    }

                    // Arrow Up
                    if ( e.which === 38 ) {
                        jQuery( this ).removeClass().prev().trigger( 'focus' ).addClass( 'wpsl-selected-dropdown' );
                    }

                    // Arrow Down
                    if ( e.which === 40 ) {
                        jQuery( this ).removeClass().next().trigger( 'focus' ).addClass( 'wpsl-selected-dropdown' );
                    }

                    // PageUp
                    if ( e.which === 33 ) {
                        jQuery( '#' + id + ' li' ).removeClass().first().trigger( 'focus' ).addClass( 'wpsl-selected-dropdown' );
                    }

                    // PageDown
                    if ( e.which === 34 ) {
                        jQuery( '#' + id + ' li' ).removeClass().last().trigger( 'focus' ).addClass( 'wpsl-selected-dropdown' );
                    }
                });

                // Handle Space and Enter on dropdown buttons.
                jQuery( '#' + id + ' .wpsl-dropdown button' ).off( 'keydown' ).on( 'keydown', function ( e ) {
                    // Space and Enter
                    if ( e.which === 13 || e.which === 32 ) {
                        e.preventDefault();

                        const $dropdownWrap = jQuery( this ).closest( '.wpsl-dropdown' );

                        if ( ! $dropdownWrap.hasClass( 'wpsl-active' ) ) {
                            $dropdownWrap.trigger( 'click' );

                            // Delayed so the dropdown is open before focusing.
                            setTimeout( function () {
                                jQuery( '#' + id + ' .wpsl-dropdown li' ).first().trigger( 'focus' );
                            }, 50 );
                        } else {
                            $dropdownWrap.trigger( 'click' );
                        }
                    }
                });
            });
        },

        /**
         * Check if a keyboard event is Space or Enter key.
         * 
         * @since   3.0.0
         * @param   {Event} e The keyboard event
         * @returns {boolean} True if Space or Enter was pressed
         */
        isActivationKey: function( e ) {
            return e.type === 'keydown' && ( e.keyCode === 32 || e.keyCode === 13 );
        }
    },

    /**
     * ARIA attribute management for accessible UI components.
     * 
     * @since 3.0.0
     */
    aria: {
        /**
         * Set aria-expanded attribute on an element.
         * 
         * @since 3.0.0
         * @param {jQuery} $element The element to update
         * @param {boolean} expanded Whether the element is expanded
         */
        setExpanded: function( $element, expanded ) {
            $element.attr( 'aria-expanded', expanded ? 'true' : 'false' );
        },

        /**
         * Toggle aria-expanded attribute on an element.
         * 
         * @since   3.0.0
         * @param   {jQuery} $element The element to toggle
         * @returns {boolean} The new expanded state
         */
        toggleExpanded: function( $element ) {
            const isExpanded = $element.attr( 'aria-expanded' ) === 'true';
            this.setExpanded( $element, !isExpanded );

            return ! isExpanded;
        },

        /**
         * Set aria-selected attribute on an element.
         * 
         * @since   3.0.0
         * @param   {jQuery} $element The element to update
         * @param   {boolean} selected Whether the element is selected
         */
        setSelected: function( $element, selected ) {
            $element.attr( 'aria-selected', selected ? 'true' : 'false' );
        },

        /**
         * Reset aria-expanded to false for all filter buttons.
         * 
         * @since 3.0.0
         */
        resetFilterButtons: function() {
            jQuery( '#wpsl-result-filters button' ).attr( 'aria-expanded', 'false' );
        },

        /**
         * Reset aria-selected to false for all filter items.
         * 
         * @since 3.0.0
         */
        resetFilterItems: function() {
            jQuery( '#wpsl-filter-options li' ).attr( 'aria-selected', 'false' );
        },

        /**
         * Update aria-selected on filter items based on focus.
         * 
         * @since 3.0.0
         * @param {jQuery} $focusedItem The currently focused item
         */
        updateFilterItemSelection: function( $focusedItem ) {
            jQuery( '#wpsl-filter-options li' ).attr( 'aria-selected', 'false' );

            $focusedItem.attr( 'aria-selected', 'true' );
        }
    },

    /**
     * Focus management for keyboard navigation and accessibility.
     * 
     * @since 3.0.0
     */
    focus: {
        /**
         * Set tabindex on an element to control tab order.
         * 
         * @since 3.0.0
         * @param {jQuery} $element The element to update
         * @param {string|number} index The tabindex value ('0', '-1', etc.)
         */
        setTabIndex: function( $element, index ) {
            $element.attr( 'tabindex', String( index ) );
        },

        /**
         * Make an element focusable (tabindex="0").
         * 
         * @since 3.0.0
         * @param {jQuery} $element The element to make focusable
         */
        makeFocusable: function( $element ) {
            this.setTabIndex( $element, '0' );
        },

        /**
         * Remove an element from tab order (tabindex="-1").
         * 
         * @since 3.0.0
         * @param {jQuery} $element The element to remove from tab order
         */
        removeFromTabOrder: function( $element ) {
            this.setTabIndex( $element, '-1' );
        },

        /**
         * Manage filter header button visibility in tab order.
         * 
         * @since 3.0.0
         * @param {boolean} visible Whether the filter header is visible
         */
        updateFilterHeaderTabIndex: function( visible ) {
            const $clearFilter = jQuery( '#wpsl-clear-filter' );

            if ( visible ) {
                this.makeFocusable( $clearFilter );
            } else {
                this.removeFromTabOrder( $clearFilter );
            }
        }
    },

    /**
     * Map accessibility features for Leaflet/OSM maps.
     * 
     * @since 3.0.0
     */
    map: {
        overlay: null,

        /**
         * Initialize the map focus overlay for Leaflet maps.
         * Creates a visual focus indicator that won't be clipped by overflow:hidden.
         * 
         * @since 3.0.0
         */
        init: function() {
            const $mapContainer = jQuery( '#wpsl-map.leaflet-container' );

            if ( ! $mapContainer.length ) {
                return;
            }

            this.overlay = jQuery( '<div>', {
                class: 'wpsl-leaflet-focus-overlay',
                css: {
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    pointerEvents: 'none',
                    zIndex: 9999,
                    display: 'none'
                }
            } );

            // The overlay is absolutely positioned against the container.
            if ( $mapContainer.css( 'position' ) === 'static' ) {
                $mapContainer.css( 'position', 'relative' );
            }

            $mapContainer.append( this.overlay );

            this.bindEvents( $mapContainer );
        },

        /**
         * Bind focus/blur events to the map container.
         * 
         * @since 3.0.0
         * @param {jQuery} $mapContainer The Leaflet map container element
         */
        bindEvents: function( $mapContainer ) {
            const self = this;

            // Show overlay on keyboard focus
            $mapContainer.on( 'focus', function( e ) {
                
                // Check if this is keyboard focus (not mouse click)
                if ( jQuery( this ).is( ':focus-visible' ) ) {
                    self.overlay.show();
                }
            });

            // Hide overlay on blur
            $mapContainer.on( 'blur', function() {
                self.overlay.hide();
            } );

            // Hide overlay on mouse/touch interaction
            $mapContainer.on( 'mousedown touchstart', function() {
                self.overlay.hide();
            } );
        }
    }
};
