import { config } from './wpsl-shared.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { helpers } from './wpsl-helpers.js';
import { search } from './wpsl-search.js';
import { createDropdowns } from '../../../common/wpsl-dropdowns.js';
import { accessibility } from './wpsl-accessibility.js';

/**
 * Filter functionality for WPSL frontend
 * 
 * @since 3.0.0
 */
export const filters = {
    /**
     * Used in the 3.x templates.
     *
     * @since 3.0.0
     */
    advanced: {
        /**
         * Initialize advanced filter interactions for v3 templates.
         *
         * @since   3.0.0
         * @returns {void}
         */
        init: function () {
            const $filters = jQuery( '.wpsl-filter' );
            const dropdownHandler = filters.dropdowns.getHandler();

            // Bind all filter interactions using the shared common module
            dropdownHandler.bindFilterInteractions({
                prepareHandlers: filters.advanced.prepare,
                closeHandlers: filters.advanced.close
            });

            // Nested ( accordion ) layout: child filter toggles.
            filters.advanced.initNested();

            jQuery( '#wpsl-filter-options' ).on( 'keydown', '.wpsl-filter input[type="checkbox"]', function ( e ) {
                const $currentCheckbox = jQuery( this );
                const $dropdown = $currentCheckbox.closest( '.wpsl-filter' );
                                
                accessibility.keyboard.handleFilterCheckboxNavigation( e, $currentCheckbox, $dropdown, $filters, filters.advanced.closeAll );
            });
            
            // Listen for checkbox changes to show/hide filter actions
            jQuery( '#wpsl-filter-options' ).on( 'change', '.wpsl-filter input[type="checkbox"]', function() {
                filters.advanced.toggleFilterActions();
            });

            // Listen for checkbox changes in custom checkbox panels (e.g. country)
            jQuery( '#wpsl-filter-options' ).on( 'change', '.wpsl-custom-checkboxes input[type="checkbox"]', function() {
                const $panelDiv = jQuery( this ).closest( '[data-id]' );
                const $actions  = $panelDiv.find( '.wpsl-filter-actions' );
                const hasChecked = $panelDiv.find( 'input[type="checkbox"]:checked' ).length > 0;

                filters.advanced.setActionsVisible( $actions, hasChecked );
            });
        },

        /**
         * Filter specific actions to run before
         * the correct filter content is shown.
         */
        prepare: {
            /**
             * Prepare the category filter panel before showing.
             *
             * @since   3.0.0
             * @returns {void}
             */
            wpslShowFilters: function() {
                const $filterOptions = jQuery( '#wpsl-filter-options' );
                const $filterDiv = $filterOptions.find( '[data-id="wpsl-show-filters"]' );
                
                // Insert filter actions if they don't exist (append to filter div after the ul)
                if ( ! $filterDiv.find( '.wpsl-filter-actions' ).length ) {
                    const applyLabel = wpslLabels.apply;
                    
                    $filterDiv.append(
                        '<div class="wpsl-filter-actions">' +
                            '<button type="submit" class="wpsl-apply-filters wpsl-styled-btn wpsl-primary-btn">' + applyLabel + '</button>' +
                        '</div>'
                    );
                }
                
                // Show/hide filter actions based on checkbox state
                filters.advanced.toggleFilterActions();
            },
            
            /**
             * Prepare the radius filter panel before showing.
             *
             * Radius has no category checkboxes, so the actions stay hidden.
             *
             * @since   3.0.0
             * @returns {void}
             */
            wpslShowRadius: function() {
                filters.advanced.setActionsVisible( jQuery( '.wpsl-filter-actions' ), false );
            },

            /**
             * Prepare the nested ( accordion ) parent panel before showing.
             *
             * @since   3.0.0
             * @returns {void}
             */
            wpslShowNested: function() {
                filters.advanced.toggleFilterActions();

                // Measure once the panel is actually visible.
                setTimeout( filters.advanced.updateNestedScroll, 0 );
            },

            /**
             * Fallback prepare handler for any panel that contains .wpsl-custom-checkboxes.
             *
             * @since   3.0.0
             * @param   {string} id The button id that triggered the panel open
             * @returns {void}
             */
            default: function( id ) {
                const $panelDiv = jQuery( '#wpsl-filter-options [data-id="' + id + '"]' );
                if ( ! $panelDiv.find( '.wpsl-custom-checkboxes' ).length ) { return; }

                if ( ! $panelDiv.find( '.wpsl-filter-actions' ).length ) {
                    const applyLabel = wpslLabels.apply;

                    $panelDiv.append(
                        '<div class="wpsl-filter-actions">' +
                            '<button type="submit" class="wpsl-apply-filters wpsl-styled-btn wpsl-primary-btn">' + applyLabel + '</button>' +
                        '</div>'
                    );
                }

                const $actions = $panelDiv.find( '.wpsl-filter-actions' );
                const hasChecked = $panelDiv.find( 'input[type="checkbox"]:checked' ).length > 0;

                filters.advanced.setActionsVisible( $actions, hasChecked );
            },
        },

        /**
         * Show the filter actions only when a checkbox is checked.
         *
         * @since 3.0.0
         * @returns {void}
         */
        toggleFilterActions: function() {
            const hasChecked = jQuery( '#wpsl-filter-options .wpsl-filter input[type="checkbox"]:checked' ).length > 0;

            filters.advanced.setActionsVisible( jQuery( '.wpsl-filter-actions' ), hasChecked );
        },

        /**
         * Show or hide the Apply bar, and mark its panel so the panel only
         * reserves room for the bar while it is there ( see filters.css ).
         *
         * @since   3.0.0
         * @param   {jQuery}  $actions The .wpsl-filter-actions element(s)
         * @param   {boolean} visible  Whether to show them
         * @returns {void}
         */
        setActionsVisible: function( $actions, visible ) {
            $actions.toggle( visible );
            $actions.closest( '#wpsl-filter-options > div' ).toggleClass( 'wpsl-has-filter-actions', visible );
        },

        /**
         * Filter specific actions to run when
         * the close filter button is clicked.
         */
        close: {
            /**
             * Default close handler for filter panels.
             *
             * @since   3.0.0
             * @returns {void}
             */
            default: function () {
                jQuery( '#wpsl-result-filters, #wpsl-filter-options, #wpsl-result-list, #wpsl-filter-header' ).toggle();
                jQuery( '#wpsl-panel' ).removeClass( 'wpsl-filters-open' );

                // Remove expanded class from all filter panels
                jQuery( '#wpsl-filter-options > div' ).removeClass( 'wpsl-filter-expanded' );
            },

            /**
             * Close handler for category filters.
             *
             * @since   3.0.0
             * @returns {void}
             */
            wpslShowFilters: function () {
                this.default();

                accessibility.aria.setExpanded( jQuery( '#wpsl-show-filters' ), false );
                
                // Hide filter actions when closing category filters
                jQuery( '.wpsl-filter-actions' ).hide();
            },

            /**
             * Close handler for radius filter.
             *
             * @since   3.0.0
             * @returns {void}
             */
            wpslShowRadius: function () {
                this.default();

                accessibility.aria.setExpanded( jQuery( '#wpsl-show-radius' ), false );
                jQuery( '#wpsl-filter-options li' ).removeClass( 'wpsl-selected-filter-option' );

                accessibility.aria.resetFilterItems();
            },

            /**
             * Close handler for the nested ( accordion ) parent panel.
             *
             * Also collapses every child accordion so the panel reopens
             * in a clean state.
             *
             * @since   3.0.0
             * @returns {void}
             */
            wpslShowNested: function () {
                this.default();

                accessibility.aria.setExpanded( jQuery( '#wpsl-show-nested' ), false );
                jQuery( '.wpsl-filter-actions' ).hide();

                filters.advanced.collapseNested();
            }
        },

        /**
         * Test if there's an active checkbox within the filter group
         *
         * @since   3.0.0
         * @param   {string}  dataId data id value of the parent div
         * @returns {boolean}
         */
        isAnyCheckboxChecked: function ( dataId ) {
            return jQuery( '[data-id="' + dataId + '"] input[type="checkbox"]:checked' ).length > 0;
        },

        /**
         * Close all filters.
         * Note: This is for closing the main filter panel, not individual filter items.
         *
         * @since   3.0.0
         * @returns {void}
         */
        closeAll: function () {
            accessibility.aria.resetFilterButtons();
        },

        /**
         * Initialise the nested (accordion) filter layout.
         *
         * In nested mode the result filters collapse under a single "Filters"
         * button that reuses the regular filter panel, with each filter inside
         * it becoming a collapsible child ( .wpsl-nested-toggle ).
         *
         * @since   3.0.0
         * @returns {void}
         */
        initNested: function () {
            const $options = jQuery( '#wpsl-filter-options' );
            $options.off( 'click.wpslNested keydown.wpslNested' );

            // Opening a child closes the rest, so only one is ever open.
            $options.on( 'click.wpslNested', '.wpsl-nested-toggle', function ( e ) {
                e.preventDefault();

                const $filter = jQuery( this ).closest( '.wpsl-nested-filter' );
                const willOpen = ! $filter.hasClass( 'wpsl-nested-open' );

                if ( willOpen ) {
                    filters.advanced.collapseNested();
                }

                $filter.toggleClass( 'wpsl-nested-open', willOpen );
                accessibility.aria.setExpanded( jQuery( this ), willOpen );

                // The changed panel height can add or remove the scrollbar.
                filters.advanced.updateNestedScroll();

                return false;
            });

            jQuery( document ).off( 'click.wpslNestedApply' ).on( 'click.wpslNestedApply', '.wpsl-apply-filters', function () {
                filters.advanced.updateNestedCounts();
            });

            filters.advanced.updateNestedCounts();
        },

        /**
         * Collapse every nested child accordion back to its closed state.
         *
         * @since   3.0.0
         * @returns {void}
         */
        collapseNested: function () {
            jQuery( '.wpsl-nested-filter' ).removeClass( 'wpsl-nested-open' );
            accessibility.aria.setExpanded( jQuery( '.wpsl-nested-toggle' ), false );
        },

        /**
         * Update the selected-filter count shown on each nested child toggle.
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateNestedCounts: function () {
            jQuery( '#wpsl-filter-options .wpsl-nested-filter' ).each( function () {
                const $filter = jQuery( this );

                // The radius child reflects its value, not a count.
                if ( $filter.hasClass( 'wpsl-nested-filter-radius' ) ) {
                    return;
                }

                const $label = $filter.find( '.wpsl-nested-toggle > div > span' ).first();
                const count  = $filter.find( '.wpsl-nested-options input[type="checkbox"]:checked' ).length;
                const $count = $label.find( '.wpsl-filter-count' );

                if ( count ) {
                    if ( $count.length ) {
                        $count.text( '(' + count + ')' );
                    } else {
                        $label.append( ' <span class="wpsl-filter-count">(' + count + ')</span>' );
                    }
                } else {
                    $count.remove();
                }
            });

            // The shared handler may have counted onto the parent button.
            jQuery( '#wpsl-show-nested .wpsl-filter-count' ).remove();
        },

        /**
         * Toggle the wpsl-has-scroll class on the nested filter panel.
         *
         * The class lets the CSS keep the apply actions clear of the
         * scrollbar an overflowing panel gets.
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateNestedScroll: function () {
            const el = jQuery( '#wpsl-filter-options' )[0];

            if ( ! el ) {
                return;
            }

            jQuery( el ).toggleClass( 'wpsl-has-scroll', el.scrollHeight > el.clientHeight );
        },

        /**
         * Restore the panel filters back to their page-load state.
         *
         * filters.dropdowns.reset() cannot see these: the panel templates
         * render the radius as a listbox keyed on data-radius and the
         * categories as checkboxes inside #wpsl-filter-options, not as the
         * #wpsl-radius / #wpsl-category dropdowns the other templates use.
         *
         * @since   3.0.0
         * @returns {void}
         */
        reset: function () {
            const $filterOptions = jQuery( '#wpsl-filter-options' );

            if ( ! $filterOptions.length ) {
                return;
            }

            const dropdownHandler = filters.dropdowns.getHandler();
            const defaultRadius   = parseInt( config.search.restrictions.radius );
            const $defaultRadius  = $filterOptions.find( '[data-handler="radius"] li[data-radius="' + defaultRadius + '"]' ).first();

            // Reuse the click handler so the radius ends up in the same state a
            // user selection leaves behind, button labels included. It clears
            // aria-selected on every filter item, so it has to run before the
            // checkboxes below set theirs.
            if ( $defaultRadius.length ) {
                dropdownHandler.handlers.radius( $defaultRadius );
            }

            // Back to the state the markup was rendered in, so a default
            // category set on the settings page stays selected.
            $filterOptions.find( 'input[type="checkbox"]' ).each( function () {
                const $checkbox = jQuery( this );
                const isDefault = !! $checkbox.prop( 'defaultChecked' );

                $checkbox.prop( 'checked', isDefault );

                accessibility.aria.setSelected( $checkbox.closest( 'li' ), isDefault );
            } );

            dropdownHandler.updateFilterCounts();
            dropdownHandler.updateParentActiveState();

            filters.advanced.updateNestedCounts();
            filters.advanced.toggleFilterActions();

            jQuery( '#wpsl-remove-filters' ).toggle( $filterOptions.find( 'input[type="checkbox"]:checked' ).length > 0 );
        },
    },

    // Initialize the shared dropdown handler with dependencies
    _sharedDropdowns: null,

    dropdowns: {
        /**
         * Get or create the shared dropdown handler instance.
         * 
         * @since   3.0.0
         * @returns {Object} The shared dropdown handler
         */
        getHandler: function () {
            if ( ! filters._sharedDropdowns ) {
                filters._sharedDropdowns = createDropdowns( helpers, config );
            }

            return filters._sharedDropdowns;
        },

        /**
         * Check what kind of dropdowns / filters we are using.
         *
         * @since   3.0.0
         * @returns {void}
         */
        checkStyle: function () {
            // A panel ( flexbox ) template has no native-select path: the
            // fallback below is skipped for it, so every value collector reads
            // the styled markup. Neither the filter nor a coarse pointer may
            // leave it unbuilt, or the values are silently dropped.
            const hasPanel = jQuery( '#wpsl-panel' ).length > 0;

            if ( jQuery( '.wpsl-dropdown' ).length && ( hasPanel || ( ! helpers.isTouchPrimary() && config.ux.enableStyledDropdowns ) ) ) {
                filters.dropdowns.create();
            } else if ( ! hasPanel ) {
                jQuery( '#wpsl-search-wrap select' ).show();

                if ( helpers.isTouchPrimary() ) {
                    jQuery( '#wpsl-wrap' ).addClass( 'wpsl-mobile' );

                    config.isMobile = 1;
                } else {
                    config.defaultFilters = 1;
                }
            }

            // Line the search bar column up ( default/horizontal, v3 )
            helpers.template.alignSearchColumns();

            // Reveal the search bar now the column is aligned ( it is hidden
            // from first paint by a visibility rule in styles.css ).
            jQuery( '#wpsl-wrap' ).addClass( 'wpsl-labels-aligned' );

            if ( document.fonts && document.fonts.ready ) {
                document.fonts.ready.then( function () {
                    helpers.template.alignSearchColumns();
                } );
            }

            jQuery( window )
                .off( 'resize.wpslLabels', helpers.template.alignSearchColumns )
                .on( 'resize.wpslLabels', helpers.template.alignSearchColumns );
        },

        /**
         * Create the styled dropdown filters.
         *
         * @since   1.2.24
         * @returns {void}
         */
        create: function () {
            const handler = this.getHandler();
            handler.create();
        },

        /**
         * Reset radius, max results, category, country and any custom
         * dropdowns back to their default values.
         *
         * @since   3.0.0
         * @returns {void}
         */
        reset: function () {
            const defaults = {
                'wpsl-radius': {
                    value: config.search.restrictions.radius,
                    text: config.search.restrictions.radius + ' ' + config.search.distanceUnit
                },
                'wpsl-results': {
                    value: config.search.restrictions.maxResults,
                    text: config.search.restrictions.maxResults
                }
            };

            // Reset radius and max results
            Object.keys( defaults ).forEach( id => {
                const data = defaults[id];
                const $dropdown = jQuery( '#' + id );

                if ( ! $dropdown.length ) return;

                // Reset native select
                $dropdown.find( 'select' ).val( parseInt( data.text ) );

                // Reset styled dropdowns
                $dropdown.find( 'li' ).removeClass( 'wpsl-selected-dropdown' );

                // Find and select the default option
                $dropdown.find( 'li' ).each(function () {
                    if ( jQuery( this ).text() === String( data.text ) ) {
                        jQuery( this ).addClass( 'wpsl-selected-dropdown' );

                        $dropdown.find( '.wpsl-selected-item' ).html( sharedHelpers.escapeHtml( data.text ) ).attr( 'data-value', data.value );
                    }
                });
            });

            // Reset the category and country filters
            jQuery.each( ['wpsl-category', 'wpsl-country'], function ( index, value ) {
                if ( jQuery( '#' + value + '' ).length ) {
                    jQuery( '#' + value + ' select' ).val( 0 );
                    jQuery( '#' + value + ' li' ).removeClass();
                    jQuery( '#' + value + ' li:first-child' ).addClass( 'wpsl-selected-dropdown' );

                    const catText = jQuery( '#' + value + ' li:first-child' ).text();

                    jQuery( '#' + value + ' .wpsl-selected-item' ).html( sharedHelpers.escapeHtml( catText ) ).attr( 'data-value', 0 );
                }
            });

            // If any custom dropdowns exist, then we reset them as well.
            if ( jQuery( '.wpsl-custom-dropdown' ).length > 0 ) {
                jQuery( '.wpsl-custom-dropdown' ).each( function() {
                    // Check if we are dealing with the styled dropdowns, or the default select dropdowns.
                    if ( ! config.defaultFilters ) {
                        const $customDiv = jQuery( this ).siblings( 'div' );
                        const $customFirstLi = $customDiv.find( 'li:first-child' );
                        const customSelectedText = $customFirstLi.text();
                        const customSelectedData = $customFirstLi.attr( 'data-value' );

                        $customDiv.find( 'li' ).removeClass();
                        $customDiv.prev().html( sharedHelpers.escapeHtml( customSelectedText ) ).attr( 'data-value', customSelectedData );
                    } else {
                        jQuery( this ).find( 'option' ).removeAttr( 'selected' );
                    }
                });
            }
        },

        /**
         * Get the values from custom dropdowns.
         *
         * @since   3.0.0
         * @param   {object} ajaxData Data used in the AJAX request
         * @returns {object} ajaxData with the custom dropdown values added
         */
        getCustomValues: function( ajaxData ) {
            jQuery( '.wpsl-custom-dropdown' ).each( function() {
                const name = jQuery( this ).attr( 'name' );
                let value;

                // Check if we are dealing with the styled dropdowns, or the
                // default select dropdowns. Styled dropdowns keep the selected
                // value on the button in front of the sibling listbox -- the
                // same element the reset branch writes it back to.
                if ( ! config.isMobile && ! config.defaultFilters ) {
                    value = jQuery( this ).siblings( 'div' ).prev().attr( 'data-value' );
                } else {
                    value = jQuery( this ).val();
                }

                if ( name && value ) {
                    ajaxData[name] = value;
                }
            });

            return ajaxData;
        },

        /**
         * Get the max results and radius restrictions.
         *
         * @since  3.0.0
         * @param  {boolean} useDefault Whether or not to use the user selected values
         *                              or the values from the settings page.
         * @return {object}  params     The values from the max results and search radius dropdowns
         */
        getRestrictions: function( useDefault = false ) {
            const params = {};

            if ( useDefault ) {
                if ( typeof config.search.restrictions.maxResults !== 'undefined' ) {
                    params.max_results = config.search.restrictions.maxResults;
                }

                params.search_radius = config.search.restrictions.radius;
            } else {
                if ( config.isMobile || config.defaultFilters ) {
                    params.max_results = parseInt( jQuery( '#wpsl-results .wpsl-dropdown' ).val() );
                    params.search_radius = parseInt( jQuery( '#wpsl-radius .wpsl-dropdown' ).val() );
                } else if ( jQuery( '[data-id="wpsl-show-radius"]' ).length ) {
                    params.search_radius = jQuery( '[data-id="wpsl-show-radius"]' ).find( 'li[aria-selected="true"]' ).data( 'radius' );
                } else {
                    params.max_results = parseInt( jQuery( '#wpsl-results .wpsl-selected-item' ).attr( 'data-value' ) );
                    params.search_radius = parseInt( jQuery( '#wpsl-radius .wpsl-selected-item' ).attr( 'data-value' ) );
                }

                // No need to pass the search radius if it's not
                // a location search and the value is NaN anyway.
                if ( ! helpers.search.locationSearchActive() && isNaN( parseInt( params.search_radius ) ) ) {
                    delete params.search_radius;
                }

                if ( isNaN( parseInt( params.max_results ) ) ) {
                    params.max_results = config.search.restrictions.maxResults;
                }

                if ( ! config.search.skipGeocode ) {
                    if ( isNaN( parseInt( params.search_radius ) ) ) {
                        params.search_radius = config.search.restrictions.radius;
                    }
                } else {
                    // Remove search_radius for category-only searches (no location = no distance calculation)
                    delete params.search_radius;
                }
            }

            return params;
        },

        /**
         * Get the ID of the active category.
         *
         * @since  3.0.0
         * @return {object} The search params holding the active category ID
         */
        getSelectedId: function () {
            let categoryId,
                params = {};

            if ( config.isMobile || config.defaultFilters ) {
                categoryId = parseInt( jQuery( '#wpsl-category .wpsl-dropdown' ).val() );
            } else if ( jQuery( '#wpsl-panel' ).length ) {
                const checkedValues = [];

                jQuery( '[data-id="wpsl-show-filters"] [data-type="category"] input[type="checkbox"]:checked' ).each( function() {
                    const val = parseInt( jQuery( this ).val() );

                    if ( ! isNaN( val ) && val !== 0 ) {
                        checkedValues.push( val );
                    }
                } );

                if ( checkedValues.length > 0 ) {
                    params.filter = checkedValues.join( ',' );
                }

                return params;
            } else {
                categoryId = parseInt( jQuery( '#wpsl-category .wpsl-selected-item' ).attr( 'data-value' ) );
            }

            if ( ( ! isNaN( categoryId ) && ( categoryId !== 0 ) ) ) {
                params.filter = categoryId;
            }

            return params;
        },

        /**
         * Get the selected option data from a dropdown.
         *
         * @since   3.0.0
         * @param   {string} id ID of the target select element.
         * @returns {object} Object containing value and text of the selected option.
         */
        getSelectedOption: function( id ) {
            const selectedData = {
                value: '',
                text: ''
            };

            if ( config.ux.enableStyledDropdowns ) {
                // For styled dropdowns, get data from the button element
                const $button = jQuery( '#' + id ).next( 'button' );

                if ( $button.length ) {
                    selectedData.value = $button.attr( 'data-value' ) || '';
                    selectedData.text = $button.text() || '';
                }
            } else {
                // For regular select elements, get the selected option
                const $selected = jQuery( '#' + id + ' option:selected' );

                if ( $selected.length ) {
                    selectedData.value = $selected.val() || '';
                    selectedData.text = $selected.text() || '';
                }
            }

            return selectedData;
        },

        /**
         * Change the default selected dropdown option.
         *
         * @since   3.0.0
         * @param   {string} id    ID of the target element.
         * @param   {string} value The value of the selected option.
         * @returns {void}
         */
        setSelectedOption: function( id, value ) {
            let $listbox,
                $selected = jQuery( '#' + id + ' option[value=' + value + ']' );

            if ( config.ux.enableStyledDropdowns ) {
                jQuery( '#' + id + '' ).next( 'button' ).text( $selected.text() ).val( $selected.val() ).attr( 'data-value', $selected.val() );

                $listbox = jQuery( '#' + id + '' ).next( 'div' ).find( 'ul' );
                $listbox.find( 'li' ).removeClass( 'wpsl-selected-dropdown' );
                $listbox.find( 'li[data-value=' + value + ']' ).addClass( 'wpsl-selected-dropdown' );
            }

            // Remove other possible selected options
            jQuery( '#' + id + ' option' ).removeAttr( 'selected' );
            $selected.attr( 'selected', 'selected' );

            jQuery( '#wpsl-search-wrap input[type=hidden]' ).val( '' );
        }
    },
    checkboxes: {
        /**
         * Collect the ids of the checked checkboxes.
         *
         * @since  2.2.0
         * @return {string} catIds The cat ids from the checkboxes.
         */
        getSelectedIds: function() {
            let catIds = jQuery( '#wpsl-checkbox-filter input:checked' ).map( function() {
                return jQuery( this ).val();
            } );

            catIds = catIds.get();
            catIds = catIds.join( ',' );

            return catIds;
        },

        /**
         * Get custom checkbox values by data-name group.
         *
         * If multiple selection are made, then the returned
         * values are comma separated.
         *
         * @since  2.2.8
         * @param  {object} ajaxData Data used in the AJAX request
         * @return {object} ajaxData Data used in the AJAX request with the custom checkbox values
         */
        getCustomValues: function( ajaxData ) {
            jQuery( '.wpsl-custom-checkboxes' ).each( function() {
                const $list      = jQuery( this );
                const searchType = $list.attr( 'data-search-type' );
                const dataName   = $list.attr( 'data-name' );

                // Without one of these we don't know where to map the values to.
                if ( ! searchType && ! dataName ) {
                    return;
                }

                const checkBoxValues = [];

                $list.find( 'input:checked' ).each( function() {
                    const currentValue = jQuery( this ).val();
                    if ( currentValue ) {
                        checkBoxValues.push( currentValue );
                    }
                } );

                if ( ! checkBoxValues.length ) {
                    return;
                }

                if ( searchType ) {

                    // Reuse the built-in country / state search handlers by
                    // nesting the values under location[<type>] and adding the
                    // type to the types= list.
                    ajaxData = helpers.search.setSearchTypeArgs( ajaxData, searchType, checkBoxValues.join( ',' ) );
                } else {
                    ajaxData[dataName] = checkBoxValues.join( ',' );
                }
            } );

            return ajaxData;
        }
    },
    hidden: {
        /**
         * Get values from hidden input fields.
         *
         * @since   3.0.0
         * @param   {object} ajaxData Data used in the AJAX request
         * @returns {object} ajaxData with hidden input values added
         */
        getCustomValues: function( ajaxData ) {
            jQuery( '#wpsl-search-wrap input[type="hidden"]' ).each( function() {
                ajaxData = filters.setCustomInputData( this, ajaxData );
            } );

            return ajaxData;
        }
    },
    radio: {
        /**
         * Get values from custom radio button groups.
         *
         * @since   3.0.0
         * @param   {object} ajaxData Data used in the AJAX request
         * @returns {object} ajaxData with radio button values added
         */
        getCustomValues: function( ajaxData ) {
            jQuery( '.wpsl-custom-radiobuttons' ).each( function() {
                jQuery.each( jQuery( this ).find( 'input[type="radio"]' ).filter( ':checked' ), function() {
                    ajaxData = filters.setCustomInputData( this, ajaxData );
                } );
            } );

            return ajaxData;
        }
    },
    
    input: {
        /**
         * Get values from custom input fields.
         *
         * @since   3.0.0
         * @param   {object} ajaxData Data used in the AJAX request
         * @returns {object} ajaxData with custom input values added
         */
        getCustomValues: function( ajaxData ) {
            jQuery( '.wpsl-custom-input' ).each( function() {
                jQuery.each( jQuery( this ), function() {
                    ajaxData = filters.setCustomInputData( this, ajaxData );
                } );
            } );

            return ajaxData;
        }
    },

    /**
     * Assign the name / value for custom radio buttons
     * or hidden input fields to the ajaxData object.
     *
     * @since   3.0.0
     * @param   {HTMLElement} elem     The input element
     * @param   {object}      ajaxData Data used in the AJAX request
     * @returns {object}      ajaxData with the element's name/value added
     */
    setCustomInputData: function( elem, ajaxData ) {
        const name = elem.name;
        const value = elem.value;

        if ( name && value ) {
            ajaxData[name] = value;
        }

        return ajaxData;
    },

    /**
     * Collect data from all possible custom elements.
     * So dropdowns, checkboxes, radio buttons and
     * (hidden) input fields.
     *
     * @since   3.0.0
     * @returns {object} customValues All values from custom elements.
     */
    getAllCustomValues: function() {
        const customValues = {};

        if ( jQuery( '.wpsl-custom-dropdown' ).length > 0 ) {
            jQuery.extend( customValues, filters.dropdowns.getCustomValues( customValues ) );
        }

        if ( jQuery( '.wpsl-custom-checkboxes' ).length > 0 ) {
            jQuery.extend( customValues, filters.checkboxes.getCustomValues( customValues ) );
        }

        // If the name value is set to 'type', then it can be used to run
        // custom code that only returns data from specific meta fields.
        //
        // /@todo add link to docu
        if ( jQuery( '#wpsl-search-wrap input[type="hidden"]' ).length > 0 ) {
            jQuery.extend( customValues, filters.hidden.getCustomValues( customValues ) );
        }

        if ( jQuery( '.wpsl-custom-radiobuttons' ).length > 0 ) {
            jQuery.extend( customValues, filters.radio.getCustomValues( customValues ) );
        }

        if ( jQuery( '.wpsl-custom-input' ).length > 0 ) {
            jQuery.extend( customValues, filters.input.getCustomValues( customValues ) );
        }

        return customValues;
    },
    /**
     * Return all possible input values.
     *
     * @since   3.0.0
     * @returns {object} args All input values, so default + custom ones and search args for filters.
     */
    grabAllValues: function() {
        const args = filters.getAllCustomValues();

        if ( jQuery( '#wpsl-results' ).length || jQuery( '#wpsl-radius' ).length || jQuery( '[data-id="wpsl-show-radius"]' ).length ) {
            jQuery.extend( args, filters.dropdowns.getRestrictions() );
        }

        if ( helpers.hasCategoryFilter() ) {
            jQuery.extend( args, filters.dropdowns.getSelectedId() );
        }

        if ( jQuery( '#wpsl-checkbox-filter' ).length ) {
            args.filter = filters.checkboxes.getSelectedIds();

            // If no user input exist, or the input field isn't present, then
            // make sure to return all location from the selected category.
            if ( args.filter.length > 0 ) {
                if ( ! jQuery( '#wpsl-search-input' ).val() || ! jQuery( '#wpsl-search-input' ).length ) {
                    args.types = 'category';
                }
            } else {
                delete args.filter;
            }
        }

        return args;
    },
    /**
     * Listen for changes to custom
     * input fields like radio and checkboxes.
     *
     * @since 3.0.0
     */
    customInputFieldListeners: function() {
        const fields = wp.hooks.applyFilters( 'wpslCustomAutoSubmitInputFields', [
            { '$elem': jQuery( '.wpsl-custom-radiobuttons' ), 'inputType': 'radio' },
            { '$elem': jQuery( '.wpsl-custom-checkboxes' ), 'inputType': 'checkbox' }
        ] );

        let args = {};

        jQuery.each( fields, function( index ) {
            if ( typeof fields[index].$elem !== 'undefined' && typeof fields[index].inputType !== 'undefined' ) {
                jQuery.each( fields[index].$elem, function() {
                    jQuery( this ).find( 'input[type="' + fields[index].inputType + '"]' ).on( 'change', function() {
                        args = filters.grabAllValues();
                        search.autoSubmit( args );
                    } );
                } );
            }
        } );
    },

    /**
     * Reset all possible input / filter fields
     * back to the default state.
     *
     * @since   3.0.0
     * @returns {void}
     */
    resetAll: function() {
        filters.dropdowns.reset();
        filters.advanced.reset();

        jQuery( '#wpsl-search-input' ).val( '' );
        jQuery( '.wpsl-search-wrap' ).removeClass( 'wpsl-error' );
        jQuery( '#wpsl-search-wrap input[type=text]' ).val( '' );

        jQuery.each( jQuery( '#wpsl-search-wrap input[type=checkbox], #wpsl-search-wrap input[type=radio]' ), function() {
            if ( ! jQuery( this ).attr( 'checked' ) ) {
                jQuery( this ).prop( 'checked', false );
            } else {
                jQuery( this ).prop( 'checked', true );
            }
        } );
    },

    /**
     * Set specific selections for dropdowns and checkboxes
     *
     * @since 3.0.0
     * @param {Object} selections Object containing the selections to set
     *                           Format: { dropdowns: { 'dropdown-id': 'value' }, checkboxes: { 'checkbox-id': true/false } }
     */
    setSelection: function( selections ) {
        let catText, $customDiv, $customLi, customSelectedText, customSelectedData;

        // Set dropdown selections
        if ( selections.dropdowns ) {
            jQuery.each( selections.dropdowns, function( dropdownId, selectedValue ) {
                if ( jQuery( '#' + dropdownId ).length ) {
                    const $targetLi = jQuery( '#' + dropdownId + ' li[data-value="' + selectedValue + '"]' );

                    if ( $targetLi.length ) {
                        jQuery( '#' + dropdownId + ' select' ).val( selectedValue );

                        // Update the visual dropdown
                        jQuery( '#' + dropdownId + ' li' ).removeClass( 'wpsl-selected-dropdown' );
                        $targetLi.addClass( 'wpsl-selected-dropdown' );

                        catText = $targetLi.text();
                        jQuery( '#' + dropdownId + ' .wpsl-selected-item' ).html( sharedHelpers.escapeHtml( catText ) ).attr( 'data-value', selectedValue );
                    }
                }
            } );
        }

        // Set custom dropdown selections
        if ( selections.customDropdowns ) {
            jQuery.each( selections.customDropdowns, function( dropdownClass, selectedValue ) {
                const $customDropdown = jQuery( '.' + dropdownClass );

                if ( $customDropdown.length ) {
                    // Check if we are dealing with the styled dropdowns, or the default select dropdowns
                    if ( ! config.defaultFilters ) {
                        $customDiv = $customDropdown.siblings( 'div' );
                        $customLi = $customDiv.find( 'li[data-value="' + selectedValue + '"]' );

                        if ( $customLi.length ) {
                            customSelectedText = $customLi.text();
                            customSelectedData = $customLi.attr( 'data-value' );

                            $customDiv.find( 'li' ).removeClass( 'wpsl-selected-dropdown' );
                            $customLi.addClass( 'wpsl-selected-dropdown' );
                            $customDiv.prev().html( sharedHelpers.escapeHtml( customSelectedText ) ).attr( 'data-value', customSelectedData );
                        }
                    } else {
                        $customDropdown.find( 'option' ).removeAttr( 'selected' );
                        $customDropdown.find( 'option[value="' + selectedValue + '"]' ).attr( 'selected', 'selected' );
                    }
                }
            } );
        }

        // Set checkbox/radio selections
        if ( selections.checkboxes ) {
            jQuery.each( selections.checkboxes, function( checkboxId, isChecked ) {
                const $checkbox = jQuery( '#' + checkboxId );

                if ( $checkbox.length ) {
                    $checkbox.prop( 'checked', isChecked );
                }
            } );
        }
    },

    /**
     * Map focus handling - delegated to accessibility module.
     * 
     * @since 3.0.0
     * @deprecated Use accessibility.map instead
     */
    mapFocus: accessibility.map
};