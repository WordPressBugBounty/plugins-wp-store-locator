import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { confirmationDialog } from '../wpsl-confirmation-dialog.js';
import { fieldsValidation } from './wpsl-validation.js';
import { groupsCRUD } from './wpsl-groups-crud.js';

/**
 * Event listeners for group management: deleting, adding and name validation.
 *
 * @since 3.0.0
 */
export const groupsEvents = {
    fieldsManager: null,

    /**
     * Initialize with reference to main manager.
     *
     * @since   3.0.0
     * @param   {object} manager Reference to the main fieldsManager
     * @returns {void}
     */
    init: function( manager ) {
        this.fieldsManager = manager;
    },

    /**
     * Refresh the event listeners for the group manager.
     *
     * @since   3.0.0
     * @returns {void}
     */
    update: function() {
        this.remove();
        this.add();
    },

    /**
     * Remove event listeners from groups.
     *
     * @since   3.0.0
     * @returns {void}
     */
    remove: function() {
        jQuery( '.wpsl-group-item .wpsl-group-delete, #wpsl-add-group' ).off( 'click' );
    },

    /**
     * Group event listeners, re-initialized whenever the user switches
     * between the field and group manager page.
     *
     * @since   3.0.0
     * @returns {void}
     */
    add: function() {
        // Deleting a group goes through a confirmation dialog.
        jQuery( '.wpsl-group-item .wpsl-group-delete' ).on( 'click', function( e ) {
            const $item = jQuery( this ).closest( 'li' );
            const label = jQuery( this ).prev().val();
            const actionHandler = groupsCRUD;

            // Removing a group clears the "group in use" warning that
            // disabled the fields manager button.
            const beforeDeleteCallback = function() {
                jQuery( 'p.wpsl-warning.wpsl-defaults' ).remove();
                jQuery( '#wpsl-show-fields-manager' ).prop( 'disabled', false );
            };

            confirmationDialog.create( $item, label, actionHandler, {
                beforeDelete: beforeDeleteCallback
            });

            e.stopPropagation();
            $item.addClass( 'clicked-delete' );

            return false;
        });

        // Add a new group field
        jQuery( '#wpsl-add-group' ).on( 'click', function() {
            groupsCRUD.add( groupsEvents.fieldsManager.eventListeners );

            return false;
        });

        // Strip tags on blur, then flag any empty name with 'wpsl-error'.
        jQuery( '.wpsl-group-item input[type=text]' ).on( 'blur', function() {
            const stripped = sharedHelpers.stripTags( jQuery( this ).val().trim() );
            jQuery( this ).val( stripped );
            fieldsValidation.groupNames();
        });

        // The "Add Group" button stays disabled while any group name is empty.
        jQuery( '.wpsl-group-item input[type=text]' ).on( 'input', function() {
            let hasEmptyField = false;
            
            jQuery( '#wpsl-manage-group-list li input[type=text]' ).each( function() {
                if ( ! jQuery( this ).val().trim() ) {
                    hasEmptyField = true;
                    return false; // break the loop
                }
            });
            
            jQuery( '#wpsl-add-group' ).prop( 'disabled', hasEmptyField );
        });
    },
};