import { state } from '../wpsl-shared.js';

/**
 * DOM manipulation and validation helpers
 * 
 * @since 3.0.0
 */
export const dom = {
    /**
     * Get the current admin page context
     * 
     * @since  3.0.0
     * @return {string} The current page identifier
     */
    getCurrentPage: function() {
        const queryString = window.location.search.substring( 1 );
        const params = {};

        let page;
        
        if ( queryString ) {
            const pairs = queryString.split( '&' );
            for ( let i = 0; i < pairs.length; i++ ) {
                const pair = pairs[ i ].split( '=' );

                params[ decodeURIComponent( pair[ 0 ] ) ] = decodeURIComponent( pair[ 1 ] || '' );
            }
        }
                
        if ( params.tab ) {
            page = params.tab;
        }

        // Fall back to DOM-based detection if no tab param was found.
        if ( ! page ) {
            if ( jQuery( '#wpsl-store-details' ).length ) {
                page = 'editor';
            } else if ( jQuery( '#wpsl-settings-form' ).length ) {
                page = 'settings';
            } else if ( jQuery( '#wpsl-appearance-form' ).length ) {
                page = 'appearance';
            } else if ( jQuery( '#wpsl-appearance-tabs' ).length ) {
                page = 'appearance';
            } else if ( jQuery( '#wpsl-onboarding' ).length ) {
                page = 'onboarding';
            } else if ( jQuery( 'body' ).hasClass( 'taxonomy-wpsl_store_category' ) ) {
                page = 'category';
            } else if ( params.page ) {
                page = params.page;
            }
        }
        
        return page;
    },

    /**
     * Toggle the enabled/disabled state of a button or input element.
     * 
     * @since  3.0.0
     * @param  {string}  elementId   The ID of the element to toggle (without the # prefix)
     * @param  {boolean} enableState True to enable the element, false to disable it
     * @return {jQuery}  The jQuery element that was modified
     */
    toggleButtonState: function( elementId, enableState ) {
        const $element = jQuery( '#' + elementId );
        if ( $element.length ) {
            if ( enableState ) {
                $element.removeAttr( 'disabled' );
            } else {
                $element.attr( 'disabled', 'disabled' );
            }
        }
        
        return $element;
    },

    /**
     * Remove invalid characters and replace spaces with underscores
     * in the css selector ( class / id name ).
     *
     * @since  3.0.0
     * @param  {string} selector
     * @return {string}
     */
    sanitizeCssSelector: function( selector ) {
        if ( selector ) {
            selector = selector.replace( /\s+/g, '_' ).replace( /[^a-zA-Z0-9_-]/g, '' );

            // Limit to 50 characters to prevent excessively long IDs/classes.
            if ( selector.length > 50 ) {
                selector = selector.substring( 0, 50 );
            }
        }

        return selector;
    },

    /**
     * Create toggle sliders for the checkboxes, and the Mapbox style options.
     *
     * @since   3.0.0
     * @param   {object} [targetElem] Optional target element
     * @returns {void}
     */
    createToggleSliders: function( targetElem ) {
        if ( typeof wpslSharedFuncs !== 'undefined' && typeof wpslSharedFuncs.createToggleSliders === 'function' ) {
            wpslSharedFuncs.createToggleSliders( targetElem );
        }
    },

    /**
     * Validate required fields and apply error classes when empty.
     * 
     * @since  3.0.0
     * @return {void}
     */
    validateRequiredFields: function() {

        function validateField( $field ) {
            const isDropdownOptions = $field.closest( '.wpsl-field-dropdown-options' ).length > 0;
            const value = $field.val().trim();

            // A filled field that already carries wpsl-error was flagged
            // server-side, so leave that class alone.
            const hasServerSideError = value && $field.hasClass( 'wpsl-error' );
            if ( ! hasServerSideError ) {
                $field.removeClass( 'wpsl-error' );
            }
            
            let hasError = false;
            
            if ( isDropdownOptions ) {
                const lines = value.split( '\n' ).filter( line => line.trim().length > 0 );
                if ( lines.length < 2 ) {
                    hasError = true;
                }
            } else if ( ! value ) {
                hasError = true;
            }

            if ( hasError ) {
                $field.addClass( 'wpsl-error' );
            }
        }

        // Use event delegation to handle dynamically added fields
        jQuery( '#wpsl-settings-form' ).on( 'blur', '.wpsl-required-field', function() {
            validateField( jQuery( this ) );
        });

        // Initial validation on page load for existing fields
        const $requiredFields = jQuery( '.wpsl-required-field' );

        for ( let i = 0; i < $requiredFields.length; i++ ) {
            validateField( jQuery( $requiredFields[i] ) );
        }
    },

    /**
     * Check if a conditional option should be toggled
     * based on the element ID and other conditions.
     * 
     * @since  3.0.0
     * @param  {object}  $element The jQuery element that triggered the change
     * @return {boolean} Whether the conditional option should be toggled
     */
    shouldToggle: function( $element ) {
        const elementId = $element.attr( 'id' );
        
        let shouldToggle = true;

        if ( elementId === 'wpsl-search-autocomplete' ) {
            shouldToggle = ( state.mapService === 'gmaps' );
        }

        return shouldToggle;
    },

    /**
     * Write a geocoded value into a store field, but only if it's still empty.
     *
     * Used for address details the geocoder can supply itself ( the postcode ),
     * where whatever the user typed has to win.
     *
     * @since  3.0.0
     * @param  {string} selector The field to fill
     * @param  {string} value    The value returned by the geocoder
     * @return {void}
     */
    fillEmptyField: function( selector, value ) {
        const $field = jQuery( selector );

        if ( ! $field.length || ! value ) {
            return;
        }

        if ( ! $field.val().trim() ) {
            $field.val( value );
        }
    },
};