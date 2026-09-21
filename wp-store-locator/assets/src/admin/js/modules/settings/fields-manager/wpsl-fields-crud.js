import { preloader } from '../../wpsl-preloader.js';
import { fieldsUtility, dataAttributeManager } from './wpsl-utility.js';
import { fieldsValidation } from './wpsl-validation.js';
import { fieldsUI } from './wpsl-ui.js';

/**
 * CRUD operations for fields: adding, deleting and their state management.
 *
 * @since 3.0.0
 */
export const fieldsCRUD = {
    /**
     * Add a new field.
     *
     * @param {object} eventListeners Reference to event listeners for updates
     * @returns {void}
     */
    add: function( eventListeners ) {
        const groupId = jQuery( '.wpsl-field-group-list option:selected' ).val();
        const args = {
            'includeWrap': false,
            'fieldVisible': true,
            'fieldExpanded': false,
            'group_id': groupId,
        };

        const fieldTemplate = fieldsUtility.buildTemplate( args );
        const $groupContainer = jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + groupId + '"]' );

        fieldsValidation.removeRequiredWarning();

        if ( $groupContainer.find( '.wpsl-field-item' ).length ) {
            fieldsUI.closeAll();
            $groupContainer.find( '.wpsl-field-item:last' ).after( fieldTemplate );
        } else {
            $groupContainer.html( fieldTemplate );
        }

        fieldsUI.callToggleSlider( groupId );

        eventListeners.fields.update();
        fieldsUI.updateDraggableState();

        // Show the new item expanded
        $groupContainer.find( '.wpsl-field-item:last ul' ).trigger( 'click' );

        const $newFieldItem = $groupContainer.find( '.wpsl-field-item:last' );

        // After the expand animation (fadeIn 500ms), focus the first input.
        setTimeout( function() {
            $newFieldItem.find( '.wpsl-field-editor input' ).first().focus();
        }, 510 );

        const $fieldLabel = $newFieldItem.find( '.wpsl-field-label' );
        if ( $fieldLabel.length && $fieldLabel.text() === '( ' + wpslL10n.NoLabel + ' )' ) {
            $fieldLabel.text( 'New Field' );
        }
        
        const $fieldName = $newFieldItem.find( '.wpsl-field-name' );
        if ( $fieldName.length && $fieldName.text() === '' ) {
            $fieldName.text( 'new_field' );
        }
        
        /*
         * The new field's name input needs no handlers of its own: the
         * delegated focus/blur pair in checkFieldNameDuplicates()
         * ( wpsl-fieldsmanager.js ) already covers dynamically added fields.
         */
    },

    /**
     * Delete a single field item
     * based on the passed element.
     *
     * @param   {object} $item
     * @returns {void}
     */
    delete: function( $item ) {
        const fieldId = $item.data( 'field' );
        const groupId = $item.closest( '.wpsl-fields-list-wrap' ).data( 'group-id' );
        
        // Get the field name before removing the item
        const fieldName = $item.find( '.wpsl-field-editor .wpsl-field-name' ).val();
        if ( fieldName ) {
            dataAttributeManager.removeField( fieldName );
        }
        
        const args = {
            type: 'delete_field',
            value: { group_id: groupId, field_id: fieldId },
            nonce: jQuery( '#wpsl-delete-field-nonce' ).val()
        };

        fieldsUtility.makeRequest( args, function() {
            $item.remove();

            preloader.remove();
            jQuery( '#wpsl-delete-confirmation' ).dialog( 'close' );
        }, function() {
            // If the field isn't found on the server (e.g. a newly added, unsaved field),
            // just remove it from the DOM.
            $item.remove();

            preloader.remove();
            jQuery( '#wpsl-delete-confirmation' ).dialog( 'close' );
        });
    },
};