/**
 * UI management for fields and groups: sorting, the draggable state,
 * expanding and collapsing fields, and toggle sliders.
 *
 * @since 3.0.0
 */
export const fieldsUI = {
    /**
     * Show the default field and required checkbox, and remove any inline styles from the options.
     * 
     * @since 3.0.0
     * @param {jQuery} $fieldEditor The field editor element to update
     */
    showDefaultAndRequired: function( $fieldEditor ) {
        $fieldEditor.find( '.wpsl-field-default, .wpsl-is-field-required' ).show();
        $fieldEditor.find( '.wpsl-field-type-options' ).removeAttr( 'style' );
    },

    /**
     * Make a group's fields sortable only when it holds multiple fields.
     *
     * @since   3.0.0
     * @returns {void}
     */
    updateDraggableState: function() {
        const $fieldsLists = jQuery( '.wpsl-fields-list-wrap' );

        for ( let i = 0; i < $fieldsLists.length; i++ ) {
            const $fieldsList = jQuery( $fieldsLists[i] );
            const fieldCount = $fieldsList.find( '.wpsl-field-item' ).length;
            const isSortable = $fieldsList.hasClass( 'ui-sortable' );
        
            if ( fieldCount > 1 ) {
                $fieldsList.addClass( 'wpsl-has-multiple-fields' );
            
                if ( ! isSortable ) {
                    $fieldsList.sortable({
                        placeholder: 'wpsl-sortable-placeholder',
                        handle: '.wpsl-field-item-actions span',
                        cursor: 'grab',
                        start: function( e, ui ) {
                            jQuery( '.wpsl-field-expanded select' ).off( 'change' );
                            jQuery( '.wpsl-fields-list-wrap .wpsl-field-expanded' ).height( '38.2px' );
                            fieldsUI.closeAll();
                        },
                        stop: function( e, ui ) {
                            ui.item.removeAttr( 'style' );
                        },
                        cancel: '.wpsl-field-editor',
                    });
                }
            } else {
                $fieldsList.removeClass( 'wpsl-has-multiple-fields' );
            
                if ( isSortable ) {
                    $fieldsList.sortable( 'destroy' );
                }
            }
        }
    },

    /**
     * Make the group list sortable only when there are multiple groups.
     *
     * @since   3.0.0
     * @returns {void}
     */
    updateGroupsDraggableState: function() {
        const $groupsList = jQuery( '#wpsl-manage-group-list' );
        const groupCount = $groupsList.find( 'li' ).length;
        const isSortable = $groupsList.hasClass( 'ui-sortable' );
        
        if ( groupCount > 1 ) {
            $groupsList.addClass( 'wpsl-has-multiple-groups' );
            
            if ( ! isSortable ) {
                $groupsList.sortable({
                    placeholder: 'wpsl-sortable-placeholder',
                    handle: '.wpsl-group-item-actions span',
                    cursor: 'grab',
                });
            }
        } else {
            $groupsList.removeClass( 'wpsl-has-multiple-groups' );
            
            if ( isSortable ) {
                $groupsList.sortable( 'destroy' );
            }
        }
    },

    /**
     * Make the field list sortable.
     *
     * @since   3.0.0
     * @see     https://api.jqueryui.com/sortable
     * @returns {void}
     */
    sortableFields: function() {
        jQuery( '.wpsl-fields-list-wrap' ).sortable({
            placeholder: 'wpsl-sortable-placeholder',
            handle: '.wpsl-field-item-actions span',
            cursor: 'grab',
            start: function() {
                jQuery( '.wpsl-field-expanded select' ).off( 'change' );
                jQuery( '.wpsl-fields-list-wrap .wpsl-field-expanded' ).height( '38.2px' );
                
                fieldsUI.closeAll();
            },
            stop: function( event, ui ) {
                ui.item.removeAttr( 'style' );
            },
            cancel: '.wpsl-field-editor',
        });
    },

    /**
     * Close the expanded field item.
     *
     * We set a fixed height to fix an issue when the item is dragged.
     *
     * @since   3.0.0
     * @returns {void}
     */
    closeAll: function() {
        jQuery( '.wpsl-fields-list-wrap .wpsl-field-editor' ).hide();
        jQuery( '.wpsl-fields-list-wrap .wpsl-field-item' ).removeClass( 'wpsl-field-expanded' );
    },

    /**
     * Convert the checkbox in the group's last field item into a slider.
     *
     * @param   {string} groupId
     * @returns {void}
     */
    callToggleSlider: function( groupId ) {
        const $lastFieldItem = jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + groupId + '"] .wpsl-field-item' ).last();

        // Remove the {} placeholder values.
        $lastFieldItem.find( 'input[type="text"]' ).val( '' );

        const $sliderElem = $lastFieldItem.find( 'input[type=checkbox]' );

        wpslSharedFuncs.createToggleSliders( $sliderElem );
    },
};