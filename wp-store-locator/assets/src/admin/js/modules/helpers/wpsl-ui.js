/**
 * UI component helpers
 * 
 * @since 3.0.0
 */
export const ui = {
    /**
     * SVG icons and graphic elements used across the application
     * 
     * @since 3.0.0
     */
    icons: {
        /**
         * Return the SVG markup for a cross/close icon.
         *
         * @since  3.0.0
         * @return {string} SVG markup
         */
        cross: function() {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg>';
        },

        /**
         * Return the SVG markup for a copy icon.
         *
         * @since  3.0.0
         * @return {string} SVG markup
         */
        copy: function() {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20"><rect x="7" y="7" width="10" height="10" fill="none" stroke="#999" stroke-width="1.5" rx="1" ry="1"/><rect x="3" y="3" width="10" height="10" fill="white" stroke="#999" stroke-width="1.5" rx="1" ry="1"/></svg>';
        },

        /**
         * Return the SVG markup for a copy success icon.
         *
         * @since  3.0.0
         * @return {string} SVG markup
         */
        copySuccess: function() {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20"><circle cx="10" cy="10" r="8.5" fill="white" stroke="#999" stroke-width="1.5"/><path d="M6 10 L9 13 L14 7" fill="white" stroke="#999" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        },
    },

    /**
     * Dialog related helper functions
     * 
     * @since 3.0.0
     */
    dialog: {
        /**
         * Add a close button to the dialog if it doesn't exist yet
         * 
         * @since   3.0.0
         * @param   {string} dialogClass The class of the dialog container
         * @returns {void}
         */
        addCloseButton: function( dialogClass ) {
            if ( jQuery( '.' + dialogClass + ' .wpsl-close-cross' ).length === 0 ) {
                 jQuery( '.' + dialogClass + ' .ui-dialog-titlebar-close' ).remove();
                 jQuery( '.' + dialogClass + ' .ui-dialog-titlebar' ).append( '<button class="wpsl-close-cross wpsl-dialog-close">' + ui.icons.cross() + '</button>' );
            }
        },

        /**
         * The event namespace one dialog's close handler is bound under.
         *
         * Derived from the selector so every dialog owns its own namespace:
         * these handlers are delegated on document and bound again on each
         * open, so without one a dialog either stacks handlers forever or --
         * worse -- unbinds its neighbours along with itself.
         *
         * @since   3.0.0
         * @param   {string} dialogSelector The dialog selector
         * @returns {string} The namespaced click event
         */
        closeNamespace: function( dialogSelector ) {
            return 'click.wpslDialogClose-' + String( dialogSelector ).replace( /[^a-z0-9]/gi, '' );
        },

        /**
         * Bind click handlers to close a jQuery UI dialog.
         *
         * @since   3.0.0
         * @param   {string}   dialogSelector The dialog selector to bind the close handler to
         * @param   {Function} [callback]     Optional callback to run after closing the dialog
         * @returns {void}
         */
        bindCloseHandler: function( dialogSelector, callback ) {
            const namespace = this.closeNamespace( dialogSelector );

            // Re-bound on every open, so drop this dialog's previous handler
            // first -- and only this dialog's.
            jQuery( document ).off( namespace ).on( namespace, '.ui-widget-overlay, .wpsl-dialog-close', function() {
                jQuery( dialogSelector ).dialog( 'close' );

                if ( typeof callback === 'function' ) {
                    callback();
                }
            });
        },

        /**
         * Remove one dialog's close handler.
         *
         * @since   3.0.0
         * @param   {string} dialogSelector The dialog selector to unbind
         * @returns {void}
         */
        unbindCloseHandler: function( dialogSelector ) {
            jQuery( document ).off( this.closeNamespace( dialogSelector ) );
        },
    },

    /**
     * Show a WordPress-style snackbar notification at the bottom center of the screen.
     *
     * The classic-script screens can't reach this module, so they have the
     * same markup and styling in wpslSharedFuncs.snackbar()
     * ( assets/src/admin/js/wpsl-shared-funcs.js ). Keep the two in step.
     *
     * @since   3.0.0
     * @param   {string} message The message to display, already HTML-escaped
     * @returns {void}
     */
    snackbar: function( message ) {
        jQuery( '.wpsl-snackbar' ).remove();

        const $snackbar = jQuery( '<div class="wpsl-snackbar"><div class="wpsl-snackbar__content">' + message + '</div></div>' );
        jQuery( 'body' ).append( $snackbar );

        setTimeout( function() {
            $snackbar.fadeOut( 400, function() { jQuery( this ).remove(); } );
        }, 3000 );
    },
};