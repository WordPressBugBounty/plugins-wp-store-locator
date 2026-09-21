import { preloader } from '../wpsl-preloader.js';

/**
 * Confirmation dialog.
 *
 * @since 3.0.0
 * @see   https://jqueryui.com/dialog/
 */
export const confirmationDialog = {

    /**
     * Create a new dialog.
     *
     * @since   3.0.0
     * @param   {object} $item         The item to delete
     * @param   {string} label         The label to show in the dialog
     * @param   {object} actionHandler The handler with a delete method
     * @param   {object} options       Additional options for the dialog
     * @returns {void}
     */
    create: function( $item, label, actionHandler, options = {} ) {
        const self = this;

        jQuery( '#wpsl-delete-confirmation' ).dialog({
            resizable: false,
            height: 'auto',
            minHeight: 79,
            minWidth: 200,
            maxWidth: 600,
            width: 400,
            modal: true,
            closeText: '',
            dialogClass: 'wpsl-dialog wpsl-no-titlebar no-close',
            classes: { 'ui-dialog': 'wpsl-dialog wpsl-no-titlebar no-close' },
            open: function() {
                jQuery( '.ui-dialog-buttonpane' ).hide();

                const $dialog = jQuery( this ).closest( '.ui-dialog' );
                $dialog.css({
                    'max-width': '600px',
                    'min-width': '200px',
                    'width': 'auto'
                });

                self.updateContent( label );
            },
            buttons: [
                {
                    text: wpslL10n.close,
                    'class': 'button-secondary',
                    click: function() {
                        jQuery( this ).dialog( 'close' );
                    }
                }
            ],
        });

        self.bindActions( $item, actionHandler, options );
    },

    /**
     * Show the correct field / group name in the dialog.
     *
     * @since   3.0.0
     * @param   {string} label The name to display in the confirmation
     * @returns {void}
     */
    updateContent: function( label ) {
        let labelText = '';

        if ( label ) {
            labelText = '"' + label.trim() + '"';
        } else {
            if ( jQuery( '.wpsl-fields-manager-wrap' ).is( ':visible' ) ) {
                labelText = wpslL10n.thisField;
            } else if ( jQuery( '.wpsl-groups-manager-wrap' ).is( ':visible' ) ) {
                labelText = wpslL10n.thisGroup;
            }
        }

        jQuery( '#wpsl-delete-confirmation p:first-child span' ).html( labelText );
    },
    
    /**
     * Bind the dialog's cancel / confirm buttons.
     *
     * @since   3.0.0
     * @param   {object} $item         The item to delete
     * @param   {object} actionHandler The handler with a delete method
     * @param   {object} options       Additional options for the dialog
     * @returns {void}
     */
    bindActions: function( $item, actionHandler, options = {} ) {
        jQuery( '#wpsl-cancel-delete, .ui-widget-overlay' ).off( 'click.wpslConfirmation' ).on( 'click.wpslConfirmation', function() {
            preloader.remove();

            jQuery( '#wpsl-delete-confirmation' ).dialog( 'close' );

            return false;
        });

        jQuery( '#wpsl-confirm-delete' ).off( 'click.wpslConfirmation' ).on( 'click.wpslConfirmation', function() {
            preloader.add( jQuery( this ) );

            if ( options.beforeDelete && typeof options.beforeDelete === 'function' ) {
                options.beforeDelete();
            }

            if ( typeof $item === 'object' && typeof actionHandler !== 'undefined' && typeof actionHandler.delete === 'function' ) {
                actionHandler.delete( $item );
            }

            return false;
        });
    }
}