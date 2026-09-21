import { helpers } from '../../wpsl-helpers.js';
import { preloader } from '../../wpsl-preloader.js';
import { fieldsUtility, dataAttributeManager } from './wpsl-utility.js';
import { fieldsValidation } from './wpsl-validation.js';
import { fieldsUI } from './wpsl-ui.js';

/**
 * CRUD operations for groups: showing the manager, building the list,
 * adding and deleting groups.
 *
 * @since 3.0.0
 */
export const groupsCRUD = {
    /**
     * Runs after the user clicked on the 'Manage Groups' button.
     * 
     * @param {object} eventListeners Reference to event listeners for updates
     */
    show: function( eventListeners ) {
        fieldsValidation.removeRequiredWarning();

        jQuery( '.wpsl-fields-manager-wrap' ).hide();
        jQuery( '.wpsl-groups-manager-wrap, .wpsl-groups-header' ).show();

        this.buildList();
        fieldsUI.updateGroupsDraggableState();
        eventListeners.groups.update();
    },

    /**
     * Build the list of groups.
     *
     * @since 3.0.0
     */
    buildList: function() {
        const $groupList = jQuery( '#wpsl-manage-group-list' ).html( '' );
        const groupTemplate = jQuery( '#wpsl-group-input-template' ).html();
        const $options = jQuery( '.wpsl-fields-manager-options option' );
    
        // One li per group, holding its name in an editable input.
        for ( let i = 0; i < $options.length; i++ ) {
            const $option = jQuery( $options[i] );
            const groupId = $option.val();
            const groupName = $option.text();
            const itemTemplate = groupTemplate.replace( /{group_id}/g, groupId );

            const $groupItem = jQuery( itemTemplate );

            $groupItem.find( 'input[type="text"]' ).val( groupName );

            $groupList.append( $groupItem );
        }

        fieldsUI.updateGroupsDraggableState();
    },

    /**
     * Add the name of a new group to the list.
     *
     * @param {object} eventListeners Reference to event listeners for updates
     * @since 3.0.0
     */
    add: function( eventListeners ) {
        const groupId = helpers.utils.uniqId();
        const groupTemplate = jQuery( '#wpsl-group-input-template' ).html().replace( /{group_id}/g, groupId );
        const $groupList = jQuery( '#wpsl-manage-group-list' );

        if ( ! $groupList.find( 'li' ).length ) {
            $groupList.append( groupTemplate );
        } else {
            $groupList.find( 'li:last-child' ).after( groupTemplate );
        }

        jQuery( '#wpsl-add-group' ).prop( 'disabled', true );

        eventListeners.groups.update();

        fieldsUI.updateGroupsDraggableState();

        // Move focus to the new group's name input so the user can type immediately.
        $groupList.find( 'li:last-child input[type="text"]' ).focus();
    },

    /**
     * Delete a single group item
     * based on the passed element.
     *
     * @param   {object} $item
     * @returns {void}
     */
    delete: function( $item ) {
        const groupId = $item.data( 'group-id' );
        
        // First remove all field names from this group from the existing fields list
        dataAttributeManager.removeFieldsFromGroup( groupId );
        
        const args = {
            type: 'delete_group',
            value: groupId,
            nonce: jQuery('#wpsl-delete-group-nonce').val()
        };

        fieldsUtility.makeRequest( args, function() {
            $item.remove();

            preloader.remove();
            jQuery( '#wpsl-delete-confirmation' ).dialog( 'close' );

            jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + groupId + '"]' ).remove();
            jQuery( '#wpsl-add-group' ).prop( 'disabled', false );

            fieldsUI.updateGroupsDraggableState();
        });

        // An empty group name field blocks adding another one.
        jQuery( '#wpsl-manage-group-list li input[type=text]' ).each( function() {
            if ( ! jQuery( this ).val() ) {
                jQuery( '#wpsl-add-group' ).prop( 'disabled', true );
            }
        });
    },
};