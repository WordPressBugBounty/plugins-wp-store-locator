/**
 * The Social Media and Custom tabs of the Fields Manager.
 *
 * The tabs show while the "Enable the social media group?" option is on, the
 * markup is rendered by PHP, see includes/admin/settings/templates/sections/fields-manager.php.
 * Both panels are in the settings form, so a save on either tab posts the
 * social media fields, and the page reopens on the tab that was open.
 *
 * @since 3.1.1
 */
export const fieldsTabs = {
    /**
     * The sessionStorage key that holds the tab to reopen after a save.
     *
     * @since 3.1.1
     */
    storageKey: 'wpsl_fields_manager_tab',

    /**
     * The tab names, in the order they are shown.
     *
     * @since 3.1.1
     */
    names: [ 'custom', 'social' ],

    /**
     * Initialize the tabs.
     *
     * @since 3.1.1
     */
    init: function() {
        this.$box = jQuery( '#wpsl-fields-manager' );
        this.$bar = jQuery( '#wpsl-fields-tabs' );

        if ( ! this.$box.length || ! this.$bar.length ) {
            return;
        }

        // The tab bar is only there while the saved option is on.
        this.enabled = ! this.$bar.hasClass( 'wpsl-hide' );
        this.active  = 'custom';

        this.restore();
        this.bind();
        this.render( false );
        this.count();
    },

    /**
     * Bind the event listeners.
     *
     * @since 3.1.1
     */
    bind: function() {
        const self = this;

        this.$bar.on( 'click', '[role="tab"]', function() {
            self.show( jQuery( this ).data( 'tab' ), false );
        });

        // Arrow keys move between the tabs, Home and End go to the first and last.
        this.$bar.on( 'keydown', '[role="tab"]', function( e ) {
            const index = self.names.indexOf( self.active );
            let next    = -1;

            // Leave Alt + Left ( back ) and other shortcuts to the browser.
            if ( e.altKey || e.ctrlKey || e.metaKey || e.shiftKey ) {
                return;
            }

            switch ( e.key ) {
                case 'ArrowRight':
                    next = ( index + 1 ) % self.names.length;
                    break;
                case 'ArrowLeft':
                    next = ( index - 1 + self.names.length ) % self.names.length;
                    break;
                case 'Home':
                    next = 0;
                    break;
                case 'End':
                    next = self.names.length - 1;
                    break;
            }

            if ( -1 !== next ) {
                e.preventDefault();
                self.show( self.names[ next ], true );
            }
        });

        // Show the tabs together with the checkbox, without a reload.
        jQuery( '#wpsl-editor-social-media' ).on( 'change', function() {
            self.enabled = jQuery( this ).is( ':checked' );
            self.render( false );
        });

        // Keep the counts up to date while fields are added or removed.
        if ( 'function' === typeof MutationObserver ) {
            const observer = new MutationObserver( function() {
                self.count();
            });

            jQuery( '#wpsl-social-field-list, #wpsl-fields-panel-custom' ).each( function() {
                observer.observe( this, { childList: true, subtree: true } );
            });
        }

        // Remember the open tab, the settings page reloads after a save.
        jQuery( '#wpsl-settings-form' ).on( 'submit', function( e ) {
            if ( e.isDefaultPrevented() ) {
                return;
            }

            self.store();
        });
    },

    /**
     * Open a tab.
     *
     * @since 3.1.1
     * @param {string}  name  The tab name
     * @param {boolean} focus Move the focus to the tab
     */
    show: function( name, focus ) {
        if ( -1 === this.names.indexOf( name ) ) {
            return;
        }

        this.active = name;
        this.render( focus );
    },

    /**
     * Show the tab bar and the open panel, or the Custom panel on its own
     * while the option is off.
     *
     * @since 3.1.1
     * @param {boolean} focus Move the focus to the open tab
     */
    render: function( focus ) {
        const open = this.enabled ? this.active : 'custom';
        const self = this;

        this.$bar.toggleClass( 'wpsl-hide', ! this.enabled );

        // The Groups Manager title belongs to the Custom tab.
        this.$box.toggleClass( 'wpsl-fields-social-open', 'social' === open );

        this.$bar.find( '[role="tab"]' ).each( function() {
            const name     = jQuery( this ).data( 'tab' );
            const selected = name === self.active;

            jQuery( this )
                .toggleClass( 'is-current', selected )
                .attr( 'aria-selected', selected ? 'true' : 'false' )
                .attr( 'tabindex', selected ? null : '-1' );

            if ( selected && focus ) {
                jQuery( this ).trigger( 'focus' );
            }
        });

        jQuery.each( this.names, function( index, name ) {
            jQuery( '#wpsl-fields-panel-' + name ).prop( 'hidden', name !== open );
        });
    },

    /**
     * Show the number of fields on each tab.
     *
     * @since 3.1.1
     */
    count: function() {
        const counts = {
            social: jQuery( '#wpsl-social-field-list .wpsl-social-field' ).length,
            custom: jQuery( '#wpsl-fields-panel-custom .wpsl-fields-list-wrap .wpsl-field-item[data-field]' ).length
        };

        this.$bar.find( '[role="tab"]' ).each( function() {
            const $count = jQuery( this ).find( '.wpsl-fields-tab-count' );
            const value  = String( counts[ jQuery( this ).data( 'tab' ) ] );

            // Only touch the text when it changes, the observer would see it otherwise.
            if ( $count.text() !== value ) {
                $count.text( value );
            }
        });
    },

    /**
     * Store the open tab, so the page can reopen it after a save.
     *
     * @since 3.1.1
     */
    store: function() {
        try {
            if ( this.enabled ) {
                sessionStorage.setItem( this.storageKey, this.active );
            } else {
                sessionStorage.removeItem( this.storageKey );
            }
        } catch ( e ) {}
    },

    /**
     * Reopen the tab that was open when the settings were saved.
     *
     * @since 3.1.1
     */
    restore: function() {
        try {
            const name = sessionStorage.getItem( this.storageKey );

            sessionStorage.removeItem( this.storageKey );

            if ( this.enabled && -1 !== this.names.indexOf( name ) ) {
                this.active = name;
            }
        } catch ( e ) {}
    }
};
