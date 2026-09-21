import { helpers } from '../../wpsl-helpers.js';

/**
 * Utility functions for the fields manager: input cleaning, template
 * building, AJAX requests and data attribute management.
 *
 * @since 3.0.0
 */
export const fieldsUtility = {
    /**
     * Replace spaces with hyphens, and make sure only
     * 'a-zA-Z0-9_-' characters are part of the string.
     *
     * @param  {string} input
     * @return {string} cleanedInput
     */
    cleanInput: function( input ) {
        const nameWithHyphens = input.replace( /\s+/g, '_' );
        const cleanedInput = nameWithHyphens.replace( /[^a-zA-Z0-9_-]/g, '' );

        return cleanedInput;
    },

    /**
     * Replace the {} in the template code.
     *
     * @param {string} str
     * @param {object} replacements
     * @return {string}
     */
    replacePlaceholders: function( str, replacements ) {
        return str.replace( /{(.*?)}/g, function( match, p1 ) {
            if ( replacements.hasOwnProperty( p1 ) ) {
                return replacements[p1];
            } else {
                return match;
            }
        });
    },

    /**
     * Build the HTML template for a new field, with the
     * group and unique ID values filled in.
     *
     * @param  {object} args
     * @return {string} template
     */
    buildTemplate: function( args ) {
        const fieldHeaderTemplate = jQuery( '#wpsl-field-header-template' ).html();
        const uniqId = helpers.utils.uniqId();
        const defaultLabel = 'New Field';
        const defaultName = this.cleanInput( defaultLabel ).toLowerCase();
        const replacements = {
            'id': uniqId,
            'group_id': args.group_id,
            'label': defaultLabel,
            'name': defaultName,
            'type': '',
            'default': '',
            'options': ''
        };

        const fieldInputTemplate = this.replacePlaceholders( jQuery( '#wpsl-field-input-template' ).html(), replacements );

        let visibility = 'style="display:none;"',
            template = '',
            expandedClass = '';

        if ( args.includeWrap ) {
            template += '<div class="wpsl-fields-list-wrap" data-group-id="' + replacements.group_id + '">';
        }

        if ( args.fieldVisible ) {
            visibility = '';
        }

        if ( args.fieldExpanded ) {
            expandedClass = 'wpsl-field-expanded';
        }

        template += '<div class="wpsl-field-item ' + expandedClass +'" data-field="' + replacements.id + '">';
        template += fieldHeaderTemplate;
        template += '<div class="wpsl-field-editor"' + visibility + '>';
        template += fieldInputTemplate;
        template += '</div>';
        template += '</div>';

        if ( args.includeWrap ) {
            template += '</div>';
        }

        return template;
    },

    /**
     * AJAX request to update the field / groups data.
     *
     * @since 3.0.0
     * @param {object}   args
     * @param {function} callback
     * @param {function} errorCallback
     */
    makeRequest: function( args, callback, errorCallback ) {
        const ajaxData = {
            action: 'wpsl_field_manager',
            type: args.type,
            value: args.value,
            wpsl_field_manager_nonce: args.nonce
        };

        jQuery.post( wpslSettings.ajaxurl, ajaxData, function( response ) {
            if ( typeof response.success !== 'undefined' ) {
                if ( ! response.success ) {
                    if ( typeof errorCallback === 'function' ) {
                        errorCallback( response.data );
                    } else {
                        alert( response.data );
                    }
                } else {
                    callback( response.data );
                }
            }
        }).fail( function() {
            if ( typeof errorCallback === 'function' ) {
                errorCallback( wpslL10n.errorOccured );
            } else {
                alert( wpslL10n.errorOccured );
            }
        });
    },
};

/**
 * Manages the data-custom-fields attribute that tracks all custom field names.
 *
 * @since  3.0.0
 */
export const dataAttributeManager = {
    /**
     * Get the existing fields as an array from the data attribute.
     * 
     * @return {array} Array of existing field names
     */
    getFields: function() {
        const existingFieldsStr = jQuery( '#wpsl-fields-manager' ).attr( 'data-custom-fields' );
        return existingFieldsStr ? existingFieldsStr.split( ',' ) : [];
    },

    /**
     * Remove a field from the existing fields list and update the data attribute.
     * 
     * @param {string} fieldToRemove The field name to remove
     * @return {boolean} True if field was found and removed, false otherwise
     */
    removeField: function( fieldToRemove ) {
        const existingFields = this.getFields();

        const fieldIndex = existingFields.indexOf( fieldToRemove );
        if ( fieldIndex > -1 ) {
            existingFields.splice( fieldIndex, 1 );

            jQuery( '#wpsl-fields-manager' ).attr( 'data-custom-fields', existingFields.join( ',' ) );

            return true;
        }
        
        return false;
    },

    /**
     * Update an existing field name in the data-custom-fields attribute.
     * 
     * @param {string} oldFieldName The original field name to replace
     * @param {string} newFieldName The new field name to add
     * @return {boolean} True if the update was successful
     */
    updateField: function( oldFieldName, newFieldName ) {
        if ( ! oldFieldName ) {
            const existingFields = this.getFields();
            if ( newFieldName && ! existingFields.includes( newFieldName ) ) {
                existingFields.push( newFieldName );

                jQuery( '#wpsl-fields-manager' ).attr( 'data-custom-fields', existingFields.join( ',' ) );

                return true;
            }
            return false;
        }

        if ( ! newFieldName ) {
            return this.removeField( oldFieldName );
        }

        // Get all current field names except this one to check if oldFieldName is still in use
        const currentFieldNames = [];
        const $fieldInputs = jQuery( '.wpsl-field-editor .wpsl-field-name' );

        for ( let i = 0; i < $fieldInputs.length; i++ ) {
            const fieldName = jQuery( $fieldInputs[i] ).val().trim().toLowerCase();

            if ( fieldName ) {
                currentFieldNames.push( fieldName );
            }
        }
        
        const oldFieldStillInUse = currentFieldNames.includes( oldFieldName.toLowerCase() );

        const existingFields = this.getFields();
        const oldFieldIndex = existingFields.indexOf( oldFieldName );
        if ( oldFieldIndex > -1 ) {
            if ( ! oldFieldStillInUse ) {
                existingFields.splice( oldFieldIndex, 1 );
            }

            if ( newFieldName && ! existingFields.includes( newFieldName ) ) {
                existingFields.push( newFieldName );
            }

            jQuery( '#wpsl-fields-manager' ).attr( 'data-custom-fields', existingFields.join( ',' ) );

            return true;
        } else if ( newFieldName && ! existingFields.includes( newFieldName ) ) {
            existingFields.push( newFieldName );

            jQuery( '#wpsl-fields-manager' ).attr( 'data-custom-fields', existingFields.join( ',' ) );
            
            return true;
        }
        
        return false;
    },

    /**
     * Collect all field names from a specific group and remove them from existing fields.
     * 
     * @param {string} groupId The ID of the group to collect fields from
     * @return {array} Array of field names that were removed
     */
    removeFieldsFromGroup: function( groupId ) {
        const removedFields = [];

        const $fieldItems = jQuery( '.wpsl-fields-list-wrap[data-group-id="' + groupId + '"] .wpsl-field-item' );
        for ( let i = 0; i < $fieldItems.length; i++ ) {
            const $fieldName = jQuery( $fieldItems[i] ).find( '.wpsl-field-name' );

            if ( $fieldName.length ) {
                const fieldName = $fieldName.text().trim();

                if ( fieldName && this.removeField( fieldName ) ) {
                    removedFields.push( fieldName );
                }
            }
        }
        
        return removedFields;
    },
};