/**
 * The Social Media block of the Fields Manager: the checkbox that shows it,
 * adding / removing rows and keeping the platform choices unique.
 *
 * The row markup and the icons are rendered by PHP, see
 * includes/admin/settings/templates/fields-manager/social-fields.php.
 *
 * @since 3.1.1
 */
import { preloader } from '../../wpsl-preloader.js';
import { confirmationDialog } from '../wpsl-confirmation-dialog.js';
import { fieldsTabs } from './wpsl-fields-tabs.js';

export const socialFields = {
    /**
     * Initialize the social media block.
     *
     * @since 3.1.1
     */
    init: function() {
        const $block = jQuery( '#wpsl-social-fields' );

        if ( ! $block.length ) {
            return;
        }

        this.$block = $block;
        this.$list  = $block.find( '#wpsl-social-field-list' );

        this.updateOptions();
        this.bind();
        this.updateSortable();
    },

    /**
     * Bind the event listeners.
     *
     * @since 3.1.1
     */
    bind: function() {
        const self = this;

        // Show the block together with the checkbox, without a reload.
        jQuery( '#wpsl-editor-social-media' ).on( 'change', function() {
            self.$block.toggleClass( 'wpsl-hide', ! jQuery( this ).is( ':checked' ) );

            // And the "Location(s) of the social links" setting.
            jQuery( '.wpsl-social-links-option' ).toggle( jQuery( this ).is( ':checked' ) );
        });

        jQuery( '#wpsl-add-social-field' ).on( 'click', function() {
            self.addRow();
        });

        // Delete asks first, like the custom fields. The links saved on the stores stay.
        this.$list.on( 'click', '.wpsl-social-field-remove', function( e ) {
            e.preventDefault();

            const $row = jQuery( this ).closest( '.wpsl-social-field' );

            confirmationDialog.create( $row, self.rowLabel( $row ), {
                delete: function( $item ) {
                    $item.remove();
                    self.renumber();
                    self.updateOptions();
                    self.updateSortable();

                    preloader.remove();
                    jQuery( '#wpsl-delete-confirmation' ).dialog( 'close' );
                }
            });
        });

        // An Other field needs a label, the server would not save it without one.
        this.$list.on( 'input', '.wpsl-social-field-label', function() {
            if ( jQuery( this ).val().trim() ) {
                jQuery( this ).removeClass( 'wpsl-error' ).removeAttr( 'aria-invalid' );
            }
        });

        jQuery( '#wpsl-settings-form' ).on( 'submit', function( e ) {
            if ( ! self.checkLabels() ) {
                e.preventDefault();
            }
        });

        this.$list.on( 'change', '.wpsl-social-field-platform', function() {
            self.updateRow( jQuery( this ).closest( '.wpsl-social-field' ) );
            self.updateOptions();
        });
    },

    /**
     * Add a row for the first built-in platform that is not used yet,
     * or an Other row when all of them are.
     *
     * @since 3.1.1
     */
    addRow: function() {
        const template = jQuery( '#wpsl-social-field-row' ).html();

        if ( ! template ) {
            return;
        }

        const $row = jQuery( template.replace( /{index}/g, this.$list.children().length ) );

        this.$list.append( $row );

        // Pick the first platform the other rows don't use yet.
        const $select = $row.find( '.wpsl-social-field-platform' );

        this.updateOptions();

        const $free = $select.find( 'option:not(:disabled)' ).not( '[value="other"]' ).first();

        $select.val( $free.length ? $free.val() : 'other' );

        this.updateRow( $row );
        this.updateOptions();
        this.updateSortable();

        if ( 'other' === $select.val() ) {
            $row.find( '.wpsl-social-field-label' ).trigger( 'focus' );
        } else {
            $select.trigger( 'focus' );
        }
    },

    /**
     * Update a row after its platform changed: the icon, and the
     * label input that only an Other field uses.
     *
     * @since 3.1.1
     * @param {jQuery} $row The row
     */
    updateRow: function( $row ) {
        const platform = $row.find( '.wpsl-social-field-platform' ).val();
        const $icon    = jQuery( '#wpsl-social-field-icons' ).children( '[data-platform="' + platform + '"]' ).first();

        $row.find( '.wpsl-social-field-icon' ).html( $icon.length ? $icon.html() : '' );
        $row.find( '.wpsl-social-field-label' ).toggleClass( 'wpsl-hide', 'other' !== platform );
    },

    /**
     * The name of a row for the delete dialog: the label of an Other field,
     * or the platform.
     *
     * @since  3.1.1
     * @param  {jQuery} $row The row
     * @return {string} The name
     */
    rowLabel: function( $row ) {
        const $select = $row.find( '.wpsl-social-field-platform' );
        const label   = 'other' === $select.val() ? $row.find( '.wpsl-social-field-label' ).val().trim() : '';

        return label || $select.find( 'option:selected' ).text();
    },

    /**
     * Check that every Other field has a label before the settings are saved,
     * like the required custom fields. Shows the Social Media tab with the
     * empty labels marked when one is missing.
     *
     * @since  3.1.1
     * @return {boolean} True when the labels are fine, or the social media group is off
     */
    checkLabels: function() {
        const $empty = jQuery();

        this.$block.find( '.wpsl-social-warning' ).remove();

        // Off, the fields aren't saved, so they don't need checking.
        if ( ! jQuery( '#wpsl-editor-social-media' ).is( ':checked' ) ) {
            return true;
        }

        this.$list.children( '.wpsl-social-field' ).each( function() {
            const $label = jQuery( this ).find( '.wpsl-social-field-label' );

            if ( 'other' === jQuery( this ).find( '.wpsl-social-field-platform' ).val() && ! $label.val().trim() ) {
                $empty.push( $label[0] );
            }
        });

        if ( ! $empty.length ) {
            return true;
        }

        $empty.addClass( 'wpsl-error' ).attr( 'aria-invalid', 'true' );

        // The text is escaped by esc_html__() on the server.
        this.$block.find( '.wpsl-social-field-actions' ).before( '<p class="wpsl-warning wpsl-defaults wpsl-social-warning" role="alert">' + wpslL10n.socialLabelMissing + '</p>' );

        fieldsTabs.show( 'social', false );
        $empty.first().trigger( 'focus' );

        return false;
    },

    /**
     * Let the rows be dragged into a new order, like the custom fields, once
     * there is more than one. The links follow the saved order everywhere.
     *
     * @since 3.1.1
     */
    updateSortable: function() {
        const self     = this;
        const multiple = this.$list.children( '.wpsl-social-field' ).length > 1;
        const sortable = this.$list.hasClass( 'ui-sortable' );

        this.$list.toggleClass( 'wpsl-has-multiple-fields', multiple );

        if ( 'function' !== typeof this.$list.sortable ) {
            return;
        }

        if ( multiple && ! sortable ) {
            this.$list.sortable({
                items: '> .wpsl-social-field',
                handle: '.wpsl-social-field-handle',
                placeholder: 'wpsl-sortable-placeholder',
                forcePlaceholderSize: true,
                axis: 'y',
                cursor: 'grab',
                stop: function( e, ui ) {
                    ui.item.removeAttr( 'style' );

                    // The posted order is the saved order, keep the names in step with it.
                    self.renumber();
                }
            });
        } else if ( ! multiple && sortable ) {
            this.$list.sortable( 'destroy' );
        }
    },

    /**
     * Number the rows from 0 again, so the posted list has no gaps.
     *
     * @since 3.1.1
     */
    renumber: function() {
        this.$list.children( '.wpsl-social-field' ).each( function( index ) {
            jQuery( this ).find( 'select, input' ).each( function() {
                const name = jQuery( this ).attr( 'name' );

                if ( name ) {
                    jQuery( this ).attr( 'name', name.replace( /\[social_fields\]\[[^\]]*\]/, '[social_fields][' + index + ']' ) );
                }
            });
        });
    },

    /**
     * Disable, in every select, the built-in platforms that another row uses.
     * Other can be used more than once.
     *
     * @since 3.1.1
     */
    updateOptions: function() {
        const $selects = this.$list.find( '.wpsl-social-field-platform' );
        const used     = [];

        $selects.each( function() {
            used.push( jQuery( this ).val() );
        });

        $selects.each( function() {
            const own = jQuery( this ).val();

            jQuery( this ).find( 'option' ).each( function() {
                const value = jQuery( this ).val();

                jQuery( this ).prop( 'disabled', 'other' !== value && value !== own && -1 !== used.indexOf( value ) );
            });
        });
    }
};
