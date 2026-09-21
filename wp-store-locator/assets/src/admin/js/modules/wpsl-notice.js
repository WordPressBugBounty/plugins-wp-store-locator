import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';

/**
 * Handle the creation of custom notices
 * on the WPSL settings page.
 *
 * @since 3.0.0
 */
export const notice = {
    /**
     * Create the notice.
     *
     * @since   3.0.0
     * @param   {obj} args Required notice arguments ( message, location etc ).
     * @returns {void}
     */
    create: function( args ) {
        const onboardingActive = ( jQuery( '#wpsl-onboarding-api-keys' ).length ) ? true : false;

        let noticeHtml, cssClass = '';

        if ( typeof args.type !== 'string' ) {
            args.type = 'updated';
        }

        if ( typeof args.keyType !== 'undefined' ) {
            cssClass = 'wpsl-' + args.keyType + '-key ';
        }

        noticeHtml = '<div class="'+ cssClass + args.type + ' notice is-dismissible">';
        noticeHtml += '<p><strong>' + sharedHelpers.escapeHtml( args.msg ) + '</strong></p>';
        noticeHtml += args.details;

        if ( ! onboardingActive ) {
            noticeHtml += '<button type="button" class="notice-dismiss"><span class="screen-reader-text">' + wpslL10n.dismissNotice + '</span></button>';
        }

        noticeHtml += '</div>';

        if ( onboardingActive ) {
            jQuery( '#wpsl-onboarding-api-keys h3' ).after( noticeHtml );
        } else {
            jQuery( '#wpsl-settings-form' ).prepend( noticeHtml );
        }

        this.actions( args );

        wp.hooks.doAction( 'wpslNotice', args );
    },

    /**
     * Additional action to take after the notice has been created.
     *
     * @since   3.0.0
     * @param   {obj}  args
     * @returns {void}
     */
    actions: function( args ) {
        if ( typeof args.field === 'string' ) {
            if ( args.field === 'apiKeys' ) {
                if ( args.type === 'error' ) {
                    jQuery( '#wpsl-settings-form .error.notice' ).addClass( args.field.toLowerCase() + '-error' );

                    jQuery( '#wpsl-api-' + args.keyType + '-key' ).addClass( 'wpsl-error' );
                } else {
                    jQuery( '#wpsl-api-' + args.keyType + '-key' ).removeClass( 'wpsl-error' );
                }
            } else {
                wp.hooks.doAction( 'wpslNoticeActions', args );
            }
        }
    },
    
    /**
     * Remove all notices from the settings page.
     *
     * @since   3.0.0
     * @returns {void}
     */
    remove: function() {
        jQuery( '#wpsl-settings-content .notice' ).remove();
    }
};