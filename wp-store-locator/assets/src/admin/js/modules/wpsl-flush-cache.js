import { preloader } from './wpsl-preloader.js';
import { notice } from './wpsl-notice.js';

/**
 * Handle the flushing of the transient cache through a
 * button on the WPSL settings page -> Tools section.
 *
 * @since   3.0.0
 * @returns {void}
 */
export const flushCache = {
    /**
     * Send an AJAX request to flush the selected cache.
     *
     * @since   3.0.0
     * @param   {jQuery} $btn The button element that triggered the request
     * @returns {void}
     */
    makeRequest: function( $btn ) {
        let noticeMsg;

        if ( $btn.attr( 'id' ) === 'wpsl-flush-nominatim-cache' ) {
            noticeMsg = wpslL10n.nominatimCacheCleared;
        } else {
            noticeMsg = wpslL10n.autoLoadCacheCleared;
        }

        // Set defaults assuming it won't fail.
        const noticeArgs = {
            details: '',
            msg: noticeMsg,
            type: 'updated'
        };

        const ajaxData = {
            action: 'wpsl_flush_cache',
            id: $btn.attr( 'id' ),
            wpsl_nonce: $btn.data( 'nonce' )
        };

        jQuery.post( wpslSettings.ajaxurl, ajaxData, function( response ) {
            $btn.addClass( 'disabled' );

            preloader.remove();

            if ( ! response.success ) {
                noticeArgs.msg = wpslL10n.securityFail;
                noticeArgs.type = 'error';
            }

            notice.create( noticeArgs );
        });
    }
};