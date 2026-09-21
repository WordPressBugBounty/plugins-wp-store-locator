import { state } from './wpsl-shared.js';
import { flushCache } from './wpsl-flush-cache.js';
import { retryMigration } from './wpsl-retry-migration.js';
import { verifyKeys } from './settings/wpsl-verify-keys.js';

/**
 * Handle the preloader actions.
 *
 * @since 3.0.0
 */
export const preloader = {
    img: `<img src="${wpslSettings.url}assets/img/ajax-loader.svg" class="wpsl-preloader" width="16" height="16" />`,
    
    /**
     * Add a preloader after the specified element.
     *
     * @since   3.0.0
     * @param   {jQuery} $element The jQuery element to add the preloader after
     * @param   {string} [type]   Optional action type to perform after adding preloader
     * @returns {void}
     */
    add: function( $element, type ) {
        if ( type ) {
            jQuery( 'form[id*="wpsl-"] .notice' ).remove();
        }

        const $parent = $element.parent();
        if ( ! $parent.find( '.wpsl-preloader' ).length ) {
            $element.after( this.img );

            if ( type ) {
                this.actions( $element, type );
            }
        }
    },
    
    /**
     * Remove all preloaders and re-enable their associated elements.
     *
     * @since   3.0.0
     * @returns {void}
     */
    remove: function() {
        jQuery( '.wpsl-preloader' ).prev().prop( 'disabled', false ).end().remove();
    },

    /**
     * Execute actions after the preloader is added.
     *
     * @since   3.0.0
     * @param   {jQuery} $element The jQuery element that triggered the action
     * @param   {string} type     The type of action to perform
     * @returns {void}
     */
    actions: function( $element, type ) {
        switch( type ) {
            case 'flushCache':
                flushCache.makeRequest( $element );
                break;
            case 'retryMigration':
                retryMigration.makeRequest( $element );
                break;
            case 'validateKeys':
                jQuery( '#wpsl-api input[type=text]' ).removeClass( 'wpsl-error' );
                verifyKeys[ state.mapService ].check();
                break;
            default:
                wp.hooks.doAction( 'wpslPreloaderActions', $element, type );
        }
    }
};