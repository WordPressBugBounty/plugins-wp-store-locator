import { preloader } from './wpsl-preloader.js';
import { notice } from './wpsl-notice.js';

/**
 * Handle retrying a failed 2.x -> 3.0 settings migration through the
 * "Retry migration" button on the WPSL settings page -> Tools section.
 *
 * @since   3.0.0
 * @returns {void}
 */
export const retryMigration = {
    /**
     * Send an AJAX request to retry the migration.
     *
     * @since   3.0.0
     * @param   {jQuery} $btn The button element that triggered the request
     * @returns {void}
     */
    makeRequest: function( $btn ) {
        const ajaxData = {
            action: 'wpsl_retry_migration',
            wpsl_nonce: $btn.data( 'nonce' )
        };

        jQuery.post( wpslSettings.ajaxurl, ajaxData, function( response ) {
            preloader.remove();

            const noticeArgs = {
                details: '',
                msg: ( response.data && response.data.message ) ? response.data.message : wpslL10n.securityFail,
                type: response.success ? 'updated' : 'error'
            };

            if ( response.success ) {
                $btn.closest( 'p.wpsl-has-preloader' ).remove();
            } else {
                console.error( 'WPSL: migration retry failed', response );
            }

            notice.create( noticeArgs );
        } ).fail( function( jqXHR ) {
            preloader.remove();

            console.error( 'WPSL: migration retry request failed', jqXHR );

            notice.create( {
                details: '',
                msg: wpslL10n.securityFail,
                type: 'error'
            } );
        } );
    }
};