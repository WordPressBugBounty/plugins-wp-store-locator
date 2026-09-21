import { helpers } from '../../wpsl-helpers.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { confirmationDialog } from '../wpsl-confirmation-dialog.js';
import { fieldsUtility, dataAttributeManager } from './wpsl-utility.js';
import { fieldsValidation } from './wpsl-validation.js';
import { fieldsCRUD } from './wpsl-fields-crud.js';

/**
 * Event listeners for field management: expanding and deleting fields,
 * label and name handling, the type dropdown, the required checkbox
 * and field name validation.
 *
 * @since 3.0.0
 */
export const fieldsEvents = {
    // Reference to the main fieldsManager for accessing other modules
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
     * Refresh the event listeners for the field manager.
     *
     * @since   3.0.0
     * @returns {void}
     */
    update: function() {
        this.remove();
        this.add();
        fieldsEvents.fieldsManager.eventListeners.groupDropdown();
        fieldsEvents.fieldsManager.eventListeners.typeDropdown();
    },

    /**
     * Remove the event listeners from the fields.
     *
     * @since   3.0.0
     * @returns {void}
     */
    remove: function() {
        jQuery( '.wpsl-field-item ul, .wpsl-field-item .wpsl-field-edit, .wpsl-field-item .wpsl-field-delete' ).off( 'click' );
        jQuery( '.wpsl-fields-list-wrap input[type=text]' ).off( 'blur' );
        jQuery( '.wpsl-field-editor .wpsl-info' ).off( '.wpslTooltip' );
        jQuery( '.wpsl-field-expanded select' ).off( 'change' );
        jQuery( '.wpsl-is-field-required input[type=checkbox]' ).off( 'change' );
    },

    /**
     * Collection of event listeners that we need to re-initialize 
     * when a new item is added to a field group.
     *
     * @since   3.0.0
     * @returns {void}
     */
    add: function() {

        // Expand the field item when the user clicks on the 'ul' or edit button.
        jQuery( '.wpsl-field-item ul, .wpsl-field-item .wpsl-field-edit' ).off( 'click' ).on( 'click', function() {
            const $parentDiv = jQuery( this ).closest( 'div' );
            const expanded = $parentDiv.hasClass( 'wpsl-field-expanded' );

            jQuery( '.wpsl-fields-list-wrap' ).find( '.wpsl-field-editor' ).hide();
            jQuery( '.wpsl-field-expanded select' ).off( 'change' );
            jQuery( '.wpsl-fields-list-wrap .wpsl-field-item' ).removeClass( 'wpsl-field-expanded' );

            if ( ! expanded ) {
                $parentDiv.addClass( 'wpsl-field-expanded' ).removeAttr( 'style' );
                $parentDiv.find( '.wpsl-field-editor' ).fadeIn( 500 );

                const typeValue = $parentDiv.find( 'select' ).val();
                if ( typeValue === 'dropdown' ) {
                    $parentDiv.find( '.wpsl-field-dropdown-options' ).show();
                    $parentDiv.find( '.wpsl-field-default' ).hide();
                } else if ( typeValue === 'checkbox' ) {
                    $parentDiv.find( '.wpsl-field-default' ).hide();
                } else if ( typeValue === 'textarea' ) {
                    $parentDiv.find( '.wpsl-bbcode-info' ).show();
                }

                fieldsEvents.fieldsManager.eventListeners.typeDropdown();
            }

            return false;
        });

        // Open a dialog for delete confirmation
        jQuery( '.wpsl-field-item .wpsl-field-delete' ).off( 'click' ).on( 'click', function( e ) {
            const $item = jQuery( this ).closest( '.wpsl-field-item' );
            const label = jQuery( this ).closest( 'ul' ).find( '.wpsl-field-label' ).text();
            const actionHandler = fieldsCRUD;

            confirmationDialog.create( $item, label, actionHandler );

            e.stopPropagation();

            return false;
        });

        // Mirror the label into the name field, with spaces replaced by _.
        jQuery( '.wpsl-fields-list-wrap .wpsl-field-label' ).off( 'blur' ).on( 'blur', function() {
            const $expandedLabel = jQuery( '.wpsl-field-expanded .wpsl-field-label' );

            let nameLabel = '';
            let warning = '';
            let labelInput = sharedHelpers.stripTags( jQuery( this ).val().trim() );

            jQuery( this ).val( labelInput );

            if ( labelInput ) {
                jQuery( this ).removeClass( 'wpsl-error' );
            }

            if ( ! labelInput ) {
                labelInput = '( ' + wpslL10n.NoLabel + ' )';
            } else if ( ! jQuery( '.wpsl-field-expanded .wpsl-field-name' ).val() ) {
                nameLabel = fieldsUtility.cleanInput( labelInput ).toLowerCase();
            }

            // A required field needs the warning span that holds the *.
            if ( jQuery( '.wpsl-field-expanded .wpsl-is-field-required input[type=checkbox]:checked' ).length ) {
                warning = fieldsEvents.fieldsManager.warningHtml;
            }

            // Set the plain-text label safely (truncated for display), then append the trusted warning HTML separately.
            $expandedLabel.text( sharedHelpers.truncate( labelInput ) );

            if ( warning ) {
                $expandedLabel.append( ' ' + warning );
            }

            if ( nameLabel && ! jQuery( '.wpsl-field-expanded .wpsl-field-name' ).text() ) {
                jQuery( '.wpsl-field-expanded .wpsl-field-name' ).text( sharedHelpers.truncate( nameLabel ) ).removeClass( 'wpsl-error' );
            }

            const nameInput = jQuery( this ).parent( 'p' ).next( 'p' ).find( 'input[type=text]' );

            // Update the name if it's empty, or if it was previously auto-generated from the label.
            if ( nameLabel && ( ! nameInput.val() || nameInput.data( 'auto-name' ) ) ) {
                const prevName = nameInput.val().trim();
                const $fieldEditor = nameInput.closest( '.wpsl-field-editor' );

                // Reset any previous validation errors before setting the new name.
                fieldsValidation.resetFieldValidation( $fieldEditor );
                nameInput.removeClass( 'wpsl-error' );

                nameInput.data( 'original-value', prevName );
                nameInput.val( nameLabel );
                nameInput.data( 'auto-name', true );
                
                const fieldNameLower = nameLabel.toLowerCase();
                
                // Get protected and existing field names (lowercase for case-insensitive comparison)
                const protectedFields = helpers.utils.getDataValues( 'protected-fields' );
                const existingFields = helpers.utils.getDataValues( 'custom-fields' );
                
                if ( protectedFields.includes( fieldNameLower ) || existingFields.includes( fieldNameLower ) ) {
                    nameInput.val( '' ).addClass( 'wpsl-error' );
                    
                    const warningType = protectedFields.includes( fieldNameLower ) ? 'protected' : '';

                    fieldsValidation.createDuplicateWarning( nameLabel, $fieldEditor, warningType );
                    
                    return;
                }

                // Field names can't start with a digit (used as JS variable names in templates).
                if ( /^[0-9]/.test( nameLabel ) ) {
                    nameInput.addClass( 'wpsl-error' );

                    const $infoSpan = $fieldEditor.find( 'label[for*="-name"] .wpsl-info' );
                    $infoSpan.addClass( 'wpsl-warning' );
                    $infoSpan.find( '.wpsl-info-text' ).removeClass( 'wpsl-hide' ).css( 'display', 'block' );

                    fieldsValidation.createDuplicateWarning( nameLabel, $fieldEditor, 'invalid' );

                    return;
                }
                
                // Valid name: sync the data attribute and the header label.
                if ( prevName ) {
                    dataAttributeManager.updateField( prevName, nameLabel );
                } else {
                    dataAttributeManager.updateField( '', nameLabel );
                }
                
                jQuery( '.wpsl-field-expanded .wpsl-field-name' ).text( sharedHelpers.truncate( nameLabel ) );
            }
        });

        /*
         * Field name focus/blur handling ( storing the original value, marking
         * a manual edit, validating on blur ) lives in the delegated pair in
         * checkFieldNameDuplicates() ( wpsl-fieldsmanager.js ), which is bound
         * once and covers dynamically added fields too.
         */

        // The "required" checkbox
        jQuery( '.wpsl-is-field-required input[type=checkbox]' ).on( 'change', function() {
            if ( jQuery( this ).is( ':checked' ) ) {
                jQuery( '.wpsl-field-expanded .wpsl-field-label' ).append( fieldsEvents.fieldsManager.warningHtml );
            } else {
                jQuery( '.wpsl-field-expanded .wpsl-field-label' ).find( '.wpsl-warning' ).remove();
            }
        });

        wpslSharedFuncs.bindInfoPopup( jQuery( '.wpsl-field-editor .wpsl-info' ) );           
    },
};