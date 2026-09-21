import { state } from '../wpsl-shared.js';
import { formatting } from './wpsl-formatting.js';

/**
 * Utility helper functions
 * 
 * @since 3.0.0
 */
let uniqidSeed = '';

export const utils = {
    /**
     * Get the comma separated field names from a data attribute, lowercased.
     *
     * @since  3.0.0
     * @param  {string} dataAttrName The name of the data attribute to get values from
     * @param  {string} selector     jQuery selector
     * @return {array}               Array of processed field names
     */
    getDataValues: function( dataAttrName, selector = '#wpsl-fields-manager' ) {
        const data = jQuery( selector ).data( dataAttrName );
        
        if ( ! data ) {
            return [];
        }
        
        return data.split( ',' )
            .filter( Boolean )
            .map( field => field.toLowerCase() );
    },

    /**
     * Returns a unique ID (PHP version)
     *
     * @since   3.0.0
     * @source  http://locutus.io/php/misc/uniqid/
     * @param   {string} prefix
     * @param   {bool}   moreEntropy
     * @return  {string}
     */
    uniqId: function( prefix, moreEntropy ) {
        if ( typeof prefix === 'undefined' ) {
            prefix = '';
        }

        let retId;

        const _formatSeed = function ( seed, reqWidth ) {
            seed = parseInt( seed, 10 ).toString( 16 ); // to hex str

            if ( reqWidth < seed.length ) {
                // so long we split
                return seed.slice( seed.length - reqWidth );
            }

            if ( reqWidth > seed.length ) {
                // so short we pad
                return Array( 1 + ( reqWidth - seed.length ) ).join( '0' ) + seed;
            }

            return seed;
        };

        if ( ! uniqidSeed ) {
            // init seed with big random int
            uniqidSeed = Math.floor( Math.random() * 0x75bcd15 );
        }

        uniqidSeed++;

        // start with prefix, add current milliseconds hex string
        retId = prefix;
        retId += _formatSeed( parseInt( new Date().getTime() / 1000, 10 ), 8 );
        
        // add seed hex string
        retId += _formatSeed( uniqidSeed, 5 );

        if ( moreEntropy ) {
            retId += ( Math.random() * 10 ).toFixed( 8 ).toString();
        }

        return retId;
    },

    /**
     * Return the selected regions that restrict the API request results.
     *
     * @since   3.0.0
     * @param   {string} type Either text or values
     * @returns {string|array} restrictions Formatted label list for 'text', an
     *                         array of ISO 3166-1 codes for 'values', or '' when
     *                         nothing is selected.
     */
    getRegionRestrictionValues: function( type = 'text' ) {
        const formatter = formatting.listFormatter( { style: 'long', type: 'conjunction' } );
        const $tags = jQuery( '#wpsl-multiselect-countries .wpsl-multiselect-tag' );

        let restrictions = [];

        for ( let i = 0; i < $tags.length; i++ ) {
            const $tag = jQuery( $tags[i] );

            // 'values' returns the ISO code from data-value, not the tag's display
            // label ( e.g. 'The Netherlands' ), which geocoders reject.
            if ( type === 'values' ) {
                const value = $tag.find( '.wpsl-multiselect-tag-remove' ).attr( 'data-value' );

                if ( value ) {
                    restrictions.push( value );
                }
            } else {
                restrictions.push( $tag.find( '.wpsl-multiselect-tag-text' ).text() );
            }
        }

        // Checkboxes live in .wpsl-multiselect-menu, a sibling of the button,
        // so scope to the container rather than the button id.
        if ( type === 'values' && ! restrictions.length ) {
            jQuery( '#wpsl-multiselect-countries' )
                .closest( '.wpsl-multiselect-container' )
                .find( '.wpsl-multiselect-menu input[type="checkbox"]:checked' )
                .each( function() {
                    restrictions.push( jQuery( this ).val() );
                });
        }

        if ( type === 'text' && restrictions.length ) {
            restrictions = formatter.format( restrictions );
        }

        return restrictions.length ? restrictions : '';
    },
};

/**
 * Onboarding helpers
 */
export const onboarding = {
    /**
     * Update the start location field in the onboarding page.
     * 
     * @since   3.0.0
     * @param   {object} latLng The coordinates from the dragged marker
     * @returns {void}
     */
    updateStartLocationField: function( latLng ) {
        if ( state.currentPage === 'onboarding' ) {
            const onboarding = window.wpslOnboarding;
            onboarding.setVars();
            onboarding.updateStartLocationField( latLng );
        }
    }
};

/**
 * Settings page helpers
 */
export const settings = {
    /**
     * List of section IDs that should be displayed in fullscreen mode.
     *
     * Going full page only reclaims the nav column; #wpsl-content-wrap keeps
     * its 52rem cap. A section that needs more than that lifts the cap in its
     * own stylesheet ( appearance-editor.css ).
     *
     * @since 3.0.0
     * @type {Array}
     */
    fullPageSections: wp.hooks.applyFilters( 'wpslFullPageSections', [ 'wpsl-section-editor' ] ),

    /**
     * Check if a section ID should be displayed in fullscreen mode.
     * 
     * @since  3.0.0
     * @param  {string}  id The section ID to check
     * @return {boolean} True if the section should be displayed in fullscreen mode
     */
    isFullPageSection: function( id ) {
        if ( id && id.charAt( 0 ) === '#' ) {
            id = id.substring( 1 );
        }

        return this.fullPageSections.includes( id );
    },

    /**
     * Set the page to fullscreen mode
     * 
     * @since 3.0.0
     * @return {void}
     */
    setFullPage: function() {
        jQuery( '#wpsl-content-wrap' ).removeClass( 'wpsl-settings-grid' );
        jQuery( '#wpsl-content-wrap nav' ).hide();
    }   
};