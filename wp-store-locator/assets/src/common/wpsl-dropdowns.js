import { accessibility } from '../frontend/js/modules/wpsl-accessibility.js';
import { sharedHelpers } from './wpsl-shared-helpers.js';

/**
 * Shared dropdown functionality for WPSL
 *
 * Turns native <select> elements into accessible, keyboard-navigable custom
 * dropdowns. Used on both the frontend and in the admin.
 *
 * @since 3.0.0
 */

/**
 * Create a dropdown handler with the provided dependencies.
 * 
 * @param   {Object} helpers The helpers object
 * @param   {Object} config  The config object with ux.maxDropdownHeight property
 * @returns {Object} The dropdown handler object
 */
export const createDropdowns = function( helpers, config ) {
    return {
        /**
         * Filter handlers for advanced filter interactions.
         *
         * Only run when the filter markup carries a data-handler attribute
         * (e.g. data-handler="radius").
         *
         * @since 3.0.0
         */
        handlers: {
            /**
             * Make sure we have something available
             * to handle the filter selection.
             *
             * @param   {string} handler The name of the function to call
             * @param   {object} elem    The clicked element
             * @returns {void}
             */
            check: function( handler, elem ) {
                if ( typeof this[ handler ] === 'function' ) {
                    this[ handler ]( elem );
                } else {
                    console.log( 'WPSL Error: Not sure what to do with ' + handler );
                }
            },

            /**
             * Radius filter changes.
             *
             * @param {object} elem The clicked element
             */
            radius: function( elem ) {
                const radius = Number( elem.attr( 'data-radius' ) );
                const $filterLi = jQuery( '#wpsl-filter-options li' );

                // Set the correct value for 'aria-selected'
                accessibility.aria.resetFilterItems();
                accessibility.aria.setSelected( elem, true );

                // Set the 'wpsl-selected-filter-option' class on the correct element
                $filterLi.removeClass( 'wpsl-selected-filter-option' );
                elem.addClass( 'wpsl-selected-filter-option' );

                // Set the new value in the filter list.
                if ( radius > 0 ) {
                    jQuery( '#wpsl-show-radius span' ).html( radius + ' ' + config.search.distanceUnit );
                }

                const $nestedFilter = elem.closest( '.wpsl-nested-filter' );
                if ( $nestedFilter.length ) {
                    $nestedFilter.find( '.wpsl-nested-toggle > div > span' ).first().text( elem.text() );

                    $nestedFilter.removeClass( 'wpsl-nested-open' );
                    accessibility.aria.setExpanded( $nestedFilter.find( '.wpsl-nested-toggle' ), false );

                    return;
                }

                jQuery( '#wpsl-panel' ).removeClass( 'wpsl-filters-open' );
                jQuery( '#wpsl-result-list, #wpsl-result-filters' ).show();
                jQuery( '#wpsl-filter-options, #wpsl-filter-header' ).hide();

                accessibility.aria.resetFilterButtons();
                accessibility.focus.removeFromTabOrder( jQuery( '#wpsl-clear-filter' ) );

                jQuery( '#wpsl-filter-options > div' ).removeClass( 'wpsl-filter-expanded' );
            },
        },

        /**
         * Handle filter button clicks to show filter options.
         *
         * @since   3.0.0
         * @param   {Object} $button The clicked button element
         * @returns {void}
         */
        handleFilterButtonClick: function( $button ) {
            const id = $button.attr( 'id' );

            // Update the filter header title to match the opened filter.
            // Falls back to the default label when the button has no data-filter-label.
            const $filterTitle = jQuery( '#wpsl-filter-header .wpsl-filter-title' );
            const filterLabel = $button.attr( 'data-filter-label' );
            $filterTitle.text( filterLabel || $filterTitle.attr( 'data-default' ) || $filterTitle.text() );

            accessibility.aria.setExpanded( $button, true );
            
            // Show the filter panel
            jQuery( '#wpsl-panel' ).addClass( 'wpsl-filters-open' );
            
            // Hide all filter sections and results, remove expanded class from all filter panels
            jQuery( '[id^="wpsl-result-"], #wpsl-filter-options > div' ).hide().removeClass( 'wpsl-filter-expanded' );
            
            // Show the filter options, the specific filter section, and the header
            jQuery( '#wpsl-filter-options, #wpsl-filter-options [data-id="' + id + '"], #wpsl-filter-header' ).show();
            
            accessibility.focus.makeFocusable( jQuery( '#wpsl-clear-filter' ) );
            
            // Add expanded class to the visible filter panel
            jQuery( '#wpsl-filter-options [data-id="' + id + '"]' ).addClass( 'wpsl-filter-expanded' );

            jQuery( '#wpsl-filter-options' ).scrollTop( 0 );
        },

        /**
         * Handle close filter button clicks: back to the results view, after
         * running any close handler registered for this filter type.
         *
         * @since   3.0.0
         * @param   {Object} closeHandlers Optional object with close handler functions
         * @returns {void}
         */
        handleCloseFilterClick: function( closeHandlers ) {            
            const id = jQuery( '#wpsl-filter-options div:visible[data-id]' ).data( 'id' );        
            
            // Call any registered close handler for this filter type
            if ( closeHandlers && id ) {
                const funcName = sharedHelpers.toCamelCase( id );
                if ( typeof closeHandlers[ funcName ] === 'function' ) {
                    closeHandlers[ funcName ]();
                }
            }
            
            // Reset aria-expanded on the button that opened this panel
            if ( id ) {
                accessibility.aria.setExpanded( jQuery( '#' + id ), false );
            }

            // Remove filter count if no checkboxes are selected
            if ( id ) {
                const checkedCount = jQuery( '[data-id="' + id + '"] input[type="checkbox"]:checked' ).length;
                if ( checkedCount === 0 ) {
                    jQuery( '#' + id ).find( '.wpsl-filter-count' ).remove();
                }
            }
            
            jQuery( '#wpsl-filter-options, #wpsl-filter-header' ).hide();
            jQuery( '#wpsl-result-filters, #wpsl-result-list' ).show();
            jQuery( '#wpsl-panel' ).removeClass( 'wpsl-filters-open' );
            
            accessibility.focus.removeFromTabOrder( jQuery( '#wpsl-clear-filter' ) );
        },

        /**
         * Bind all filter interaction events.
         *
         * @since   3.0.0
         * @param   {Object} options Configuration options
         * @param   {Object} options.prepareHandlers Optional prepare handlers to run before showing filters
         * @param   {Object} options.closeHandlers Optional close handlers to run when closing filters
         * @returns {void}
         */
        bindFilterInteractions: function( options ) {
            const self = this;

            options = options || {};
            
            // Remove any existing handlers first
            jQuery( '#wpsl-result-filters button, #wpsl-filter-options li, .wpsl-close-filter button[type="reset"], #wpsl-remove-filters, .wpsl-apply-filters' ).off( 'click keydown' );
            
            // Handle clicks on the filter buttons (show filters, show radius, etc.)
            jQuery( '#wpsl-result-filters button' ).on( 'click', function( e ) {
                e.preventDefault();
                
                const id = jQuery( this ).attr( 'id' );
                
                // Call any prepare handler for this filter type
                if ( options.prepareHandlers && id ) {
                    const prepareFuncName = sharedHelpers.toCamelCase( id );
                    if ( typeof options.prepareHandlers[ prepareFuncName ] === 'function' ) {
                        options.prepareHandlers[ prepareFuncName ]( id );
                    } else if ( typeof options.prepareHandlers.default === 'function' ) {
                        options.prepareHandlers.default( id );
                    }
                }
                
                // Use the shared handler for consistent behavior
                self.handleFilterButtonClick( jQuery( this ) );
                
                // Update parent active states when opening filters
                self.updateParentActiveState();
                
                return false;
            });
            
            // Handle close filter button clicks
            jQuery( '.wpsl-close-filter button[type="reset"]' ).on( 'click keydown', function( e ) {

                // If it's a keydown event, only proceed for Space or Enter keys
                if ( e.type === 'keydown' && ! accessibility.keyboard.isActivationKey( e ) ) {
                    return;
                }
                
                // Use the shared handler, passing the close handlers object
                self.handleCloseFilterClick( options.closeHandlers );
                
                return false;
            });
            
            // Handle focus on filter option items for aria-selected
            jQuery( '#wpsl-filter-options li' ).on( 'focus', function() {
                accessibility.aria.updateFilterItemSelection( jQuery( this ) );
            });
            
            // Handle clicks on filter option items (li elements)
            jQuery( '#wpsl-filter-options li' ).on( 'click keydown', function( e ) {

                // If it's a keydown event, only proceed for Space or Enter keys
                if ( e.type === 'keydown' && ! accessibility.keyboard.isActivationKey( e ) ) {
                    return;
                }
                
                let checkbox;
                
                // If the target is a checkbox or label, find the checkbox
                if ( jQuery( e.target ).is( ':checkbox' ) ) {
                    checkbox = jQuery( e.target );
                } else if ( jQuery( e.target ).closest( 'label' ).length ) {
                    checkbox = jQuery( e.target ).closest( 'label' ).find( 'input[type="checkbox"]' );
                } else {
                    checkbox = jQuery( this ).find( 'input[type="checkbox"]' );
                }
                
                // If clicking on li or label (not directly on checkbox), toggle the checkbox
                if ( ! jQuery( e.target ).is( ':checkbox' ) ) {
                    checkbox.prop( 'checked', ! checkbox.prop( 'checked' ) ).trigger( 'change' );
                }
                
                // Update aria-selected to match the checkbox state (after any toggle)
                const isChecked = checkbox.prop( 'checked' );
                accessibility.aria.setSelected( jQuery( e.currentTarget ), isChecked );
                
                const handler = jQuery( this ).parent( 'ul' ).attr( 'data-handler' );
                if ( typeof handler === 'string' ) {
                    self.handlers.check( handler, jQuery( this ) );
                }

                // Allow native checkbox toggle behavior for direct clicks/keydown events, but stop propagation.
                if ( jQuery( e.target ).is( ':checkbox' ) ) {
                    if ( e.type === 'click' ) {
                        e.stopPropagation();

                        return;
                    } else if ( e.type === 'keydown' ) {
                        e.stopPropagation();
                        
                        return;
                    }
                }

                return false;
            });
            
            // Handle remove filters button
            jQuery( '#wpsl-remove-filters' ).on( 'click', function( e ) {
                jQuery( '.wpsl-filter input[type="checkbox"]' ).prop( 'checked', false );
                jQuery( '.wpsl-filter-count, .wpsl-total-filter-count' ).remove();
                jQuery( this ).hide();
                
                accessibility.aria.resetFilterItems();
                
                self.updateParentActiveState();
                
                return false;
            });
            
            // Handle apply filters button
            jQuery( document ).on( 'click', '.wpsl-apply-filters', function( e ) {

                // Sync the active filter counts shown on the filter buttons.
                self.updateFilterCounts();

                // Hide the filter and show the results div
                jQuery( '#wpsl-result-filters, #wpsl-filter-options, #wpsl-result-list, #wpsl-filter-header' ).toggle();
                jQuery( '#wpsl-panel' ).removeClass( 'wpsl-filters-open' );
                
                jQuery( '.wpsl-filter-actions' ).hide();
                
                accessibility.aria.resetFilterButtons();
                
                jQuery( '#wpsl-filter-options > div' ).removeClass( 'wpsl-filter-expanded wpsl-has-filter-actions' );

                accessibility.focus.removeFromTabOrder( jQuery( '#wpsl-clear-filter' ) );

                jQuery( '#wpsl-search-btn' ).trigger( 'click' );
                
                return false;
            });
            
            // Track checkbox changes to show/hide remove filters button and update parent classes
            jQuery( document ).on( 'change', '.wpsl-filter input[type="checkbox"]', function() {
                const anyChecked = jQuery( '.wpsl-filter input[type="checkbox"]:checked' ).length > 0;
                jQuery( '#wpsl-remove-filters' ).toggle( anyChecked );
                
                // Update parent categories with wpsl-has-active-child class
                self.updateParentActiveState();
            });

            // On page load, reflect any pre-selected ( default ) category filter
            // in the counts, so "Show filters" already shows e.g. (1).
            self.updateFilterCounts();

            const hasPreselected = jQuery( '.wpsl-filter input[type="checkbox"]:checked' ).length > 0;
            jQuery( '#wpsl-remove-filters' ).toggle( hasPreselected );

            self.updateParentActiveState();
        },

        /**
         * Update the active filter counts shown on the filter buttons.
         *
         * Covers both the category filter ( .wpsl-filter ) and custom checkbox
         * filters ( .wpsl-custom-checkboxes, e.g. country ). Also runs on page
         * load, so a pre-selected default category is counted.
         *
         * @since   3.0.0
         * @returns {number} totalCount The total number of checked filters
         */
        updateFilterCounts: function() {
            let totalCount = 0;

            jQuery( '#wpsl-filter-options > div[data-id]' ).each( function() {
                const $panel    = jQuery( this );
                const $filterId = $panel.data( 'id' );
                if ( typeof $filterId !== 'string' ) {
                    return;
                }

                const checkedCount     = $panel.find( 'input[type="checkbox"]:checked' ).length;
                const $button          = jQuery( '#' + $filterId );
                const $filterCountElem = $button.find( '.wpsl-filter-count' );

                if ( checkedCount ) {

                    // See if we need to update / append the count
                    if ( $filterCountElem.length ) {
                        $filterCountElem.text( '(' + checkedCount + ')' );
                    } else {
                        $button.find( 'span' ).first().append( ' <span class="wpsl-filter-count">(' + checkedCount + ')</span>' );
                    }

                    totalCount = totalCount + checkedCount;
                } else {
                    $filterCountElem.remove();
                }
            });

            return totalCount;
        },

        /**
         * Flag parent categories with a checked child with wpsl-has-active-child.
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateParentActiveState: function() {
            jQuery( '#wpsl-filter-options li.wpsl-has-child' ).removeClass( 'wpsl-has-active-child' );

            jQuery( '#wpsl-filter-options li.wpsl-has-child' ).each( function() {
                const $parent = jQuery( this );
                const hasCheckedChildren = $parent.find( 'ul input[type="checkbox"]:checked' ).length > 0;
                if ( hasCheckedChildren ) {
                    $parent.addClass( 'wpsl-has-active-child' );
                }
            });
        },

        /**
         * Create the styled dropdown filters from select.wpsl-dropdown elements.
         *
         * Inspired by https://github.com/patrickkunka/easydropdown
         *
         * @since   1.2.24
         * @returns {void}
         */
        create: function() {
            const self = this;

            jQuery( 'select.wpsl-dropdown' ).each( function( index ) {
                let	active, maxHeight, $btn, $this = jQuery( this );
                
                $this.$dropdownWrap = $this.wrap( '<div class="wpsl-dropdown' + ' ' + ( $this.attr( 'id' ) || '' ) + '"></div>' ).parent();
                $this.$selectedVal  = $this.val();
                $this.$dropdownElem = jQuery( '<div><ul role="listbox"></ul></div>' ).appendTo( $this.$dropdownWrap );
                $this.$dropdown     = $this.$dropdownElem.find( 'ul' );
                $this.$options 	  	= $this.$dropdownWrap.find( 'option' );

                $this.hide().removeClass( 'wpsl-dropdown' );

                jQuery.each( $this.$options, function() {
                    const optionText = jQuery( this ).text();

                    if ( jQuery( this ).val() == $this.$selectedVal ) {
                        active = 'class="wpsl-selected-dropdown"';
                    } else {
                        active = '';
                    }

                    $this.$dropdown.append( '<li tabindex="0" role="option" data-value="' + sharedHelpers.escapeHtml( jQuery( this ).val() ) + '" ' + active + '>' + sharedHelpers.escapeHtml( optionText ) + '</li>' );
                });

                /*
                 * Built through the attribute API rather than a markup string.
                 * The value used to be concatenated in unquoted, so an empty
                 * one ( a custom dropdown's "Any" option ) left
                 * `data-value= class='wpsl-selected-item'` and the parser took
                 * the next token as the value -- which is what the search then
                 * sent. A value with a space split into two attributes the same
                 * way. Setting it as an attribute also escapes the label, which
                 * was interpolated raw.
                 */
                const $selected = $this.find( ':selected' );

                const $button = jQuery( '<button>' )
                    .attr( {
                        'aria-expanded':     'false',
                        'aria-haspopup':     'listbox',
                        'data-filter-count': index,
                        'data-value':        $selected.val(),
                        'tabindex':          '0'
                    } )
                    .addClass( 'wpsl-selected-item' )
                    .text( $selected.text() );

                $this.$dropdownElem.before( $button );
                $this.$dropdownItem = $this.$dropdownElem.find( 'li' );

                // Batched: one measuring pass instead of a forced layout per item.
                const itemTexts = $this.$dropdownItem.map( function() {
                    return jQuery( this ).text();
                }).get();

                $this.$dropdownWrap.css( 'width', fitDropdownWidth( $this.$dropdownWrap, $button, itemTexts ) + 'px' );

                $this.$dropdownWrap.on( 'click', function( e ) {
                    $btn = jQuery( this ).find( 'button' );
                    accessibility.aria.toggleExpanded( $btn );

                    // Check if we only need to close the current open dropdown.
                    if ( jQuery( this ).hasClass( 'wpsl-active' ) ) {
                        jQuery( this ).removeClass( 'wpsl-active' );
                        self.closeAll();

                        return false;
                    }

                    self.closeAll();

                    jQuery( this ).toggleClass( 'wpsl-active' );
                    maxHeight = 0;

                    // Either calculate the correct height for the <ul>, or set it to 0 to hide it.
                    if ( jQuery( this ).hasClass( 'wpsl-active' ) ) {
                        $this.$dropdownItem.each( function( index ) {
                            maxHeight += jQuery( this ).outerHeight();
                        });

                        $this.$dropdownElem.css( 'height', maxHeight + 2 + 'px' );
                    } else {
                        $this.$dropdownElem.css( 'height', 0 );
                    }

                    // Check if we need to enable the scrollbar in the dropdown filter.
                    if ( maxHeight > config.ux.maxDropdownHeight ) {
                        jQuery( this ).addClass( 'wpsl-scroll-required' );
                        $this.$dropdownElem.css( 'height', ( config.ux.maxDropdownHeight ) + 'px' );
                    }

                    e.stopPropagation();

                    return false;
                });

                $this.$dropdownItem.on( 'click', function( e ) {
                    $btn = jQuery( this ).closest( '.wpsl-dropdown' ).find( 'button' );
                    accessibility.aria.toggleExpanded( $btn );

                    $this.$dropdownWrap.find( jQuery( '.wpsl-selected-item' ) ).text( jQuery( this ).text() ).attr( 'data-value', jQuery( this ).attr( 'data-value' ) );

                    $this.$dropdownItem.removeClass( 'wpsl-selected-dropdown' );
                    jQuery( this ).addClass( 'wpsl-selected-dropdown' );

                    self.closeAll();

                    e.stopPropagation();
                });
            });

            self.keyboardNavigation();

            // A click anywhere outside the dropdowns resets them.
            jQuery( document ).on( 'click', function() {
                accessibility.aria.setExpanded( jQuery( '#wpsl-result-filters button, .wpsl-dropdown button.wpsl-selected-item' ), false );

                self.closeAll();
            });
        },

        /**
         * Bind click and keyboard events on already-transformed dropdowns.
         *
         * @since   3.0.0
         * @returns {void}
         */
        bindExisting: function() {
            const self = this;

            jQuery( 'div.wpsl-dropdown' ).each( function() {
                const $dropdownWrap = jQuery( this );
                const $dropdownElem = $dropdownWrap.find( '> button.wpsl-selected-item' ).next( 'div' );
                const $dropdownItem = $dropdownWrap.find( 'li' );

                // Batched: one measuring pass instead of a forced layout per item.
                const itemTexts = $dropdownItem.map( function() {
                    return jQuery( this ).text();
                }).get();

                const $button = $dropdownWrap.find( '> button.wpsl-selected-item' );

                $dropdownWrap.css( 'width', fitDropdownWidth( $dropdownWrap, $button, itemTexts ) + 'px' );

                $dropdownWrap.off( 'click' ).on( 'click', function( e ) {
                    const $btn = jQuery( this ).find( 'button' );
                    accessibility.aria.toggleExpanded( $btn );

                    if ( jQuery( this ).hasClass( 'wpsl-active' ) ) {
                        jQuery( this ).removeClass( 'wpsl-active' );
                        self.closeAll();

                        return false;
                    }

                    self.closeAll();

                    jQuery( this ).toggleClass( 'wpsl-active' );
                    let maxHeight = 0;

                    if ( jQuery( this ).hasClass( 'wpsl-active' ) ) {
                        $dropdownItem.each( function() {
                            maxHeight += jQuery( this ).outerHeight();
                        });

                        $dropdownElem.css( 'height', maxHeight + 2 + 'px' );
                    } else {
                        $dropdownElem.css( 'height', 0 );
                    }

                    if ( maxHeight > config.ux.maxDropdownHeight ) {
                        jQuery( this ).addClass( 'wpsl-scroll-required' );
                        $dropdownElem.css( 'height', ( config.ux.maxDropdownHeight ) + 'px' );
                    }

                    e.stopPropagation();

                    return false;
                });

                $dropdownItem.off( 'click' ).on( 'click', function( e ) {
                    const $btn = jQuery( this ).closest( '.wpsl-dropdown' ).find( 'button' );
                    accessibility.aria.toggleExpanded( $btn );

                    $dropdownWrap.find( '.wpsl-selected-item' ).text( jQuery( this ).text() ).attr( 'data-value', jQuery( this ).attr( 'data-value' ) );

                    $dropdownItem.removeClass( 'wpsl-selected-dropdown' );
                    jQuery( this ).addClass( 'wpsl-selected-dropdown' );

                    self.closeAll();

                    e.stopPropagation();
                });
            });

            self.keyboardNavigation();

            jQuery( document ).off( 'click.wpslDropdown' ).on( 'click.wpslDropdown', function() {
                accessibility.aria.setExpanded( jQuery( '#wpsl-result-filters button, .wpsl-dropdown button.wpsl-selected-item' ), false );

                self.closeAll();
            });
        },

        /**
         * Handle the keyboard navigation for the
         * different elements in the search bar.
         *
         * @since 3.0.0
         */
        keyboardNavigation: function() {
            accessibility.keyboard.handleDropdownNavigation( {} );
        },

        /**
         * Close all the dropdowns and
         * reset the aria-expanded value.
         *
         * @since   1.2.24
         * @returns {void}
         */
        closeAll: function() {
            jQuery( '.wpsl-dropdown' ).removeClass( 'wpsl-active' );
            jQuery( '.wpsl-dropdown div' ).css( 'height', 0 );
        }
    };
};

/**
 * Get the width a dropdown needs to show its widest option in full.
 *
 * The texts are measured with the `wpsl-selected-item` class so they get the
 * toggle's font, but that also gives them the class's padding, which isn't
 * the toggle's own ( and already includes the 35px arrow space ). Adding a
 * fixed arrow width on top of that counted the arrow twice. An empty sample
 * measures the probe's padding by itself, so it can be swapped for the padding
 * and border the toggle really has. The toggle's padding-right leaves room
 * for the arrow.
 *
 * @since   3.0.1
 * @param   {jQuery}   $dropdownWrap The div.wpsl-dropdown wrapper
 * @param   {jQuery}   $button       The toggle button inside it
 * @param   {string[]} texts         The option labels
 * @returns {number} The width in pixels
 */
const fitDropdownWidth = function( $dropdownWrap, $button, texts ) {
    const widths   = sharedHelpers.measureTextWidths( [ '' ].concat( texts ), 'wpsl-selected-item', $dropdownWrap );
    const probeBox = widths.shift();
    const px       = function( style, props ) {
        return props.reduce( function( sum, prop ) {
            return sum + ( parseFloat( style[ prop ] ) || 0 );
        }, 0 );
    };

    // Without a toggle to read, keep the probe's padding as the box.
    let box = probeBox;

    if ( $button.length ) {
        box = px( window.getComputedStyle( $button[0] ), [ 'paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth' ] );
    }

    const wrapStyle = window.getComputedStyle( $dropdownWrap[0] );

    if ( wrapStyle.boxSizing === 'border-box' ) {
        box += px( wrapStyle, [ 'paddingLeft', 'paddingRight', 'borderLeftWidth', 'borderRightWidth' ] );
    }

    return Math.ceil( Math.max( 0, ...widths ) - probeBox + box );
};

/**
 * Set or clear the width of the Mapbox geocoder box.
 *
 * @since   3.0.0
 * @param   {jQuery} $field The #mapbox-autocomplete container
 * @param   {string} width  A CSS width, or '' to restore the emitted rule
 * @returns {void}
 */
const setMapboxFieldWidth = function( $field, width ) {
    const props    = [ 'width', 'min-width', 'max-width' ];
    const $geocoder = $field.find(
        '.mapboxgl-ctrl-geocoder, .mapboxgl-ctrl-geocoder--input, input[type="text"]'
    );

    $field.add( $geocoder ).each( function() {
        const el = this;

        props.forEach( function( prop ) {
            if ( width ) {
                el.style.setProperty( prop, width, 'important' );
            } else {
                el.style.removeProperty( prop );
            }
        } );
    } );
};

/**
 * Line up the search bar column on templates without a side panel.
 *
 * @since   3.0.0
 * @returns {void}
 */
export const alignSearchColumns = function() {
    const $wrap = jQuery( '#wpsl-wrap' );

    // Nothing to do without a search bar, or on panel templates.
    if ( ! $wrap.length || $wrap.hasClass( 'wpsl-has-panel' ) ) {
        return;
    }

    const v3Active = jQuery( 'body' ).hasClass( 'wpsl-v3-css' ) ||
        jQuery( '.wpsl-styled-template-preview' ).hasClass( 'wpsl-v3-css' );

    if ( ! v3Active ) {
        return;
    }

    const $labels = jQuery(
        '#wpsl-search-wrap .wpsl-input label, ' +
        '#wpsl-search-wrap #wpsl-radius label, ' +
        '#wpsl-search-wrap #wpsl-category label'
    );
    const $catDropdown  = jQuery( '#wpsl-category .wpsl-dropdown' );
    const $searchInput  = jQuery( '#wpsl-search-wrap #wpsl-search-input' );
    const $autoWrap     = jQuery( '#wpsl-search-wrap .wpsl-autocomplete-search-container' );
    const $mapboxField  = jQuery( '#wpsl-search-wrap #mapbox-autocomplete' );

    // Clear what we set previously so the natural sizes are measured, and so
    // mobile resets cleanly to auto.
    $labels.css( 'width', '' );
    $catDropdown.css({ 'min-width': '', 'width': '' });
    $searchInput.css( 'width', '' );
    $autoWrap.css( 'width', '' );
    setMapboxFieldWidth( $mapboxField, '' );

    // Below 570px the label moves above its own field ( responsive.css
    // @media max-width: 570px ), so there is no column left to align.
    if ( window.matchMedia( '(max-width: 570px)' ).matches ) {
        return;
    }

    // Equalise the column labels ( read all, then write ).
    if ( $labels.length >= 2 ) {
        let maxWidth = 0;

        $labels.each( function() {
            const width = jQuery( this ).outerWidth();
            if ( width > maxWidth ) {
                maxWidth = width;
            }
        });

        if ( maxWidth ) {
            $labels.css( 'width', Math.ceil( maxWidth ) + 'px' );
        }
    }

    // Below 825px responsive.css sizes the dropdowns.
    if ( window.matchMedia( '(max-width: 825px)' ).matches ) {
        return;
    }

    // Line the category dropdown up with whichever search field is visible
    // ( it varies by provider ).
    if ( $catDropdown.length ) {
        const fieldSelectors = [
            '#wpsl-search-wrap #mapbox-autocomplete.wpsl-geocoder-active',
            '#wpsl-search-wrap .wpsl-autocomplete-search-container',
            '#wpsl-search-wrap #wpsl-search-input'
        ];
        let $field = null;

        for ( let i = 0; i < fieldSelectors.length; i++ ) {
            const $candidate = jQuery( fieldSelectors[ i ] );
            if ( $candidate.length && $candidate.is( ':visible' ) ) {
                $field = $candidate;
                break;
            }
        }

        const fieldWidth = $field ? $field.outerWidth() : 0;
        const target     = Math.max( fieldWidth, $catDropdown.outerWidth() );

        if ( target ) {
            $catDropdown.css( 'min-width', Math.ceil( target ) + 'px' );

            // Widen the field to match, or it jumps when the dropdown grows.
            if ( $field && target > fieldWidth ) {
                const width = Math.ceil( target );

                if ( $field.is( $mapboxField ) ) {
                    setMapboxFieldWidth( $field, width + 'px' );
                } else {
                    $field.outerWidth( width );

                    // The autocomplete container shrink-wraps the input, so
                    // sizing it alone leaves the input on the width the
                    // settings emit as an inline `#wpsl-search-input` rule.
                    // Size both, and the pair still measures as the input's
                    // own width once cleared above.
                    if ( ! $field.is( $searchInput ) ) {
                        $searchInput.outerWidth( width );
                    }
                }
            }
        }
    }
};