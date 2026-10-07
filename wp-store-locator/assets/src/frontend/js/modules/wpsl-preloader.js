import { config } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';

/**
 * Preloader functionality for WPSL frontend
 * 
 * @since 3.0.0
 */
export const preloader = {
    /**
     * Add preloader
     *
     * @since   3.0.0
     * @param   {string} preloadLabel   Optional label key from wpslLabels
     * @param   {string} targetSelector Optional list container to append the
     *                                  preloader to without clearing it.
     * @returns {void}
     */
    add: function( preloadLabel = '', targetSelector = '' ) {
        const preloader = config.search.preloader;

        let text;

        if ( preloadLabel && wpslLabels[ preloadLabel ] ) {
            text = wpslLabels[ preloadLabel ];
        } else {
            text = wpslLabels.preloader;
        }

        const $image = jQuery( '<img>' ).attr({ alt: text, src: preloader });

        if ( helpers.flexboxAvailable() ) {
            jQuery( '#wpsl-clear-search-input' ).hide();

            const $clearWrapper = jQuery( '#wpsl-clear-wrapper' );
            if ( ! $clearWrapper.find( '.wpsl-preloader' ).length ) {
                $clearWrapper.append( $image.addClass( 'wpsl-preloader' ) );
            }
        } else if ( targetSelector ) {
            const $targetElem = jQuery( targetSelector );

            if ( ! jQuery( '.wpsl-preloader' ).length ) {
                $targetElem.append( jQuery( '<li class="wpsl-preloader">' ).text( text ).prepend( $image ) );
            }
        } else {
            const $targetElem = jQuery( '#wpsl-stores ul' );

            if ( ! jQuery( '.wpsl-preloader' ).length ) {
                $targetElem.empty().append( jQuery( '<li class="wpsl-preloader">' ).text( text ).prepend( $image ) );
            }
        }
    },
    
    /**
     * Remove preloader
     * 
     * @since   3.0.0
     * @returns {void}
     */
    remove: function() {
        jQuery( '.wpsl-preloader' ).remove();
        
        if ( helpers.flexboxAvailable() ) {
            const $searchInput = helpers.input.getSearchField();
            
            if ( $searchInput.val().trim() !== '' ) {
                jQuery( '#wpsl-clear-search-input' ).show();
            }
        }
    }
};
