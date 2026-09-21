import { helpers } from '../../wpsl-helpers.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { fieldsUtility, dataAttributeManager } from './wpsl-utility.js';

/**
 * Validation for field names (duplicates, protected names), group names,
 * required fields and the validation state.
 *
 * @since 3.0.0
 */
export const fieldsValidation = {
    /**
     * Check for duplicate group names and update UI accordingly
     * 
     * @returns {boolean} Whether duplicates were found
     */
    groupNames: function() {
        const groupNames = [];
        const $groupsHeader = jQuery( '#wpsl-groups-header' ); 

        let duplicateFound = false;

        $groupsHeader.siblings( '.wpsl-warning.wpsl-defaults' ).remove();

        jQuery( '#wpsl-manage-group-list li input' ).removeClass( 'wpsl-error' );

        jQuery( '#wpsl-manage-group-list li' ).each( function() {
            const groupName = jQuery( this ).find( 'input' ).val();
            if ( groupName ) {
                if ( groupNames.includes( groupName ) ) {
                    duplicateFound = true;

                    jQuery( this ).find( 'input' ).addClass( 'wpsl-error' );
                } else {
                    groupNames.push( groupName );
                }
            }
        });
        
        if ( duplicateFound ) {
            $groupsHeader.before( '<p class="wpsl-warning wpsl-defaults">' + wpslL10n.duplicateGroupNames + '</p>' );
            jQuery( '.wpsl-error' ).first().focus();

            return false;
        }

        helpers.dom.toggleButtonState( 'wpsl-show-fields-manager', ! duplicateFound );

        return duplicateFound;
    },

    /**
     * Validate a field name for duplicates and protected names
     * 
     * @since   3.0.0
     * @param   {jQuery} $input The input field element
     * @returns {boolean} True if the field name is valid, false otherwise
     */
    validateFieldName: function( $input ) {
        const fieldName = $input.val().trim();
        const originalValue = $input.data( 'original-value' ) || '';
        const fieldNameLower = fieldName.toLowerCase();
        const originalValueLower = originalValue.toLowerCase();
        const $fieldEditor = $input.closest( '.wpsl-field-editor' );
        const $fieldItem = $input.closest( '.wpsl-field-item' );
        
        this.resetFieldValidation( $fieldEditor );
        
        $input.removeClass( 'wpsl-error' );
        
        // An empty required name is an error even when it hasn't changed.
        if ( ! fieldName && $input.hasClass( 'wpsl-required-field' ) ) {
            $input.addClass( 'wpsl-error' );
            
            if ( originalValue ) {
                dataAttributeManager.removeField( originalValue );
            }
            
            $fieldItem.find( '.wpsl-field-name' ).text( '' );
            
            return false;
        }
        
        if ( fieldNameLower === originalValueLower ) {
            return true;
        }
        
        // If field name is empty (but not required, since we checked that above)
        if ( ! fieldName ) {
            if ( originalValue ) {
                dataAttributeManager.removeField( originalValue );
            }
            
            $fieldItem.find( '.wpsl-field-name' ).text( '' );
            
            return true;
        }

        // Field names are used as JS variable names in Underscore.js templates,
        // so they can't start with a digit.
        if ( /^[0-9]/.test( fieldName ) ) {
            $input.addClass( 'wpsl-error' );

            // Make the tooltip icon red and show the tooltip text.
            const $infoSpan = $fieldEditor.find( 'label[for*="-name"] .wpsl-info' );
            $infoSpan.addClass( 'wpsl-warning' );
            $infoSpan.find( '.wpsl-info-text' ).removeClass( 'wpsl-hide' ).css( 'display', 'block' );

            this.createDuplicateWarning( fieldName, $fieldEditor, 'invalid' );
            return false;
        }
        
        // Get protected field names (lowercase for case-insensitive comparison)
        const protectedFields = helpers.utils.getDataValues( 'protected-fields' );
        if ( protectedFields.includes( fieldNameLower ) ) {
            $input.addClass( 'wpsl-error' );
            
            this.createDuplicateWarning( fieldName, $fieldEditor, 'protected' );
            
            return false;
        }
        
        let isDuplicate = false;
        
        // Get all current field names from inputs (excluding this one)
        const $otherInputs = jQuery( '.wpsl-field-editor .wpsl-field-name' ).not( $input );
        
        for ( let i = 0; i < $otherInputs.length; i++ ) {
            const otherFieldName = jQuery( $otherInputs[i] ).val().trim().toLowerCase();
            if ( otherFieldName === fieldNameLower ) {
                isDuplicate = true;
                break;
            }
        }
        
        if ( isDuplicate ) {
            $input.addClass( 'wpsl-error' );
            this.createDuplicateWarning( fieldName, $fieldEditor );
            
            return false;
        }
        
        // The name is valid, so clean it and update the field.
        const fieldNameText = fieldsUtility.cleanInput( fieldName ).toLowerCase();
        
        $input.closest( '.wpsl-field-item' ).find( '.wpsl-field-name' ).text( sharedHelpers.truncate( fieldNameText ) );
        $input.val( fieldNameText );

        if ( originalValue ) {
            dataAttributeManager.updateField( originalValue, fieldNameText );
        } else if ( fieldNameText ) {
            dataAttributeManager.updateField('', fieldNameText);
        }
        
        // Store the new value as the original value for future comparisons
        $input.data( 'original-value', fieldNameText );
                    
        return true;
    },

    /**
     * Create a duplicate warning message.
     * 
     * @since 3.0.0
     * @param {string} fieldName The name of the field.
     * @param {jQuery} $fieldEditor The field editor element.
     * @param {string} type The type of error we need to show ( duplicate or protected ( email, address, etc. ) ).
     */
    createDuplicateWarning: function( fieldName, $fieldEditor, type = 'duplicate' ) {
        let errorMessage;
        
        if ( type === 'protected' ) {
            errorMessage = wpslL10n.protectedFieldName.replace( '%', fieldName );
        } else if ( type === 'invalid' ) {
            errorMessage = wpslL10n.fieldNameStartsWithDigit.replace( '%', fieldName );
        } else {
            errorMessage = wpslL10n.duplicateFieldName.replace( '%', fieldName );
        }

        // Remove any warning that is already showing first.
        $fieldEditor.find( '.wpsl-field-name-duplicate-warning, #wpsl-field-actions.wpsl-warning' ).remove();
        $fieldEditor.prepend( '<p class="wpsl-field-name-duplicate-warning wpsl-warning wpsl-defaults">' + sharedHelpers.escapeHtml( errorMessage ) + '</p>' );
        
        jQuery( '#wpsl-field-actions .button-primary' ).prop( 'disabled', true );
    },

    /**
     * Remove all wpsl-warning classes from the input fields.
     */
    removeRequiredWarning: function() {
        jQuery( '#wpsl-field-actions .wpsl-warning' ).remove();
    },

    /**
     * Remove a duplicate warning message.
     * 
     * @since 3.0.0
     * @param {jQuery} $fieldEditor The field editor element.
     */
    removeFieldNameDuplicateWarning: function( $fieldEditor ) {
        $fieldEditor.find( '.wpsl-field-name-duplicate-warning' ).remove();
    },

    /**
     * Reset field validation state by removing warnings and enabling the save button
     * 
     * @since 3.0.0
     * @param {jQuery} $fieldEditor The field editor element to reset warnings for
     */
    resetFieldValidation: function( $fieldEditor ) {
        $fieldEditor.find( '.wpsl-field-name-duplicate-warning' ).remove();

        // Reset the tooltip icon and text to their default state.
        const $infoSpan = $fieldEditor.find( 'label[for*="-name"] .wpsl-info' );
        $infoSpan.removeClass( 'wpsl-warning' );
        $infoSpan.find( '.wpsl-info-text' ).addClass( 'wpsl-hide' ).css( 'display', '' );

        jQuery( '#wpsl-field-actions .button-primary' ).prop( 'disabled', false );
    },
};