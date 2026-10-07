/**
 * Color field for store categories.
 *
 * @since  3.1.0
 */
export const categoryColor = {
    /**
     * Initialize the color field.
     *
     * @since   3.1.0
     * @returns {void}
     */
    init: function() {
        this.$checkbox = jQuery( '#wpsl-category-color-enabled' );
        this.$field    = jQuery( '#wpsl-category-color' );

        if ( ! this.$checkbox.length || ! this.$field.length ) {
            return;
        }

        this.setupEnableToggle();
        this.bindInfoPopup();
        this.bindReset();
    },

    /**
     * The color picker attached to the field by wpsl-color-picker.js.
     *
     * @since   3.1.0
     * @returns {Object|undefined}
     */
    getPicker: function() {
        return this.$field.data( 'wpsl-color-picker' );
    },

    /**
     * Convert the enable checkbox to a slider and toggle the color field.
     *
     * @since   3.1.0
     * @returns {void}
     */
    setupEnableToggle: function() {
        const self      = this;
        const $checkbox = this.$checkbox;

        if ( window.wpslSharedFuncs && typeof window.wpslSharedFuncs.createToggleSliders === 'function' ) {
            window.wpslSharedFuncs.createToggleSliders( $checkbox );
        } else {
            $checkbox.removeClass( 'wpsl-toggle-pending' );
        }

        const toggleField = function() {
            const enabled = $checkbox.prop( 'checked' );
            const picker  = self.getPicker();

            jQuery( '.wpsl-category-color-field' ).toggleClass( 'wpsl-hidden', ! enabled );

            if ( enabled && ! self.$field.val() ) {
                if ( picker ) {
                    picker.setColor( self.$field.data( 'default' ) );
                } else {
                    self.$field.val( self.$field.data( 'default' ) );
                }
            }
        };

        $checkbox.on( 'change', toggleField );
        toggleField();
    },

    /**
     * Bind the tooltip next to the toggle.
     *
     * @since   3.1.0
     * @returns {void}
     */
    bindInfoPopup: function() {
        if ( window.wpslSharedFuncs && typeof window.wpslSharedFuncs.bindInfoPopup === 'function' ) {
            window.wpslSharedFuncs.bindInfoPopup( jQuery( '.wpsl-category-color-toggle .wpsl-info, .wpsl-category-color-toggle-row .wpsl-info' ) );
        }
    },

    /**
     * Reset the field after a category is added.
     *
     * @since   3.1.0
     * @returns {void}
     */
    bindReset: function() {
        const self = this;

        // Only the add form has this field, the edit screen reloads on save.
        if ( ! jQuery( '#tag-name' ).length ) {
            return;
        }

        jQuery( document ).ajaxSuccess( function( event, xhr, settings ) {
            const data = typeof settings.data === 'string' ? settings.data : '';

            if ( data.indexOf( 'action=add-tag' ) === -1 || String( xhr.responseText ).indexOf( '<wp_error' ) !== -1 ) {
                return;
            }

            const picker = self.getPicker();

            if ( picker ) {
                picker.clearColor();
            } else {
                self.$field.val( '' );
            }

            self.$checkbox.prop( 'checked', false ).trigger( 'change' );
        });
    }
};