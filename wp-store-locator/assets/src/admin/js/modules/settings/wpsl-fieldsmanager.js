import { helpers } from '../wpsl-helpers.js';
import { preloader } from '../wpsl-preloader.js';
import { sharedHelpers } from '../../../../common/wpsl-shared-helpers.js';
import { fieldsUtility } from './fields-manager/wpsl-utility.js';
import { fieldsValidation } from './fields-manager/wpsl-validation.js';
import { fieldsUI } from './fields-manager/wpsl-ui.js';
import { fieldsCRUD } from './fields-manager/wpsl-fields-crud.js';
import { groupsCRUD } from './fields-manager/wpsl-groups-crud.js';
import { fieldsEvents } from './fields-manager/wpsl-fields-events.js';
import { groupsEvents } from './fields-manager/wpsl-groups-events.js';

/**
 * Manage custom fields / groups. Orchestrates the field manager modules.
 *
 * @since 3.0.0
 */
export const fieldsManager = {
    /**
     * Warning HTML for required fields
     */
    warningHtml: '<span class="wpsl-warning"> *</span>',

    /**
     * Initialize the fields manager.
     *
     * @since 3.0.0
     */
    init: function() {
        // Initialize event modules with reference to this manager
        fieldsEvents.init( this );
        groupsEvents.init( this );

        // Expand the field when the user just created the first group, so the
        // field editor options are visible.
        if ( jQuery( '.wpsl-fields-list-wrap' ).hasClass( 'wpsl-first-field' ) ) {
            jQuery( '.wpsl-fields-list-wrap ul' ).trigger( 'click' );
        }

        this.validateMainFormSubmit();

        // Make sure the correct field type is set to selected in the dropdown
        jQuery( '.wpsl-field-item select' ).each( function() {
            const selectedOption = jQuery( this ).data( 'selected' );
            const $fieldEditor = jQuery( this ).parents( '.wpsl-field-editor' );

            let noMarginElem = '';

            jQuery( this ).val( selectedOption );

            switch ( selectedOption ) {
                case 'checkbox':
                    noMarginElem = '.wpsl-field-type-options';
                    $fieldEditor.find( '.wpsl-field-default, .wpsl-is-field-required' ).hide();
                    break;
                case 'dropdown':
                    noMarginElem = '.wpsl-field-dropdown-options';
                    $fieldEditor.find( '.wpsl-field-default, .wpsl-is-field-required' ).hide();
                    
                    if ( ! $fieldEditor.find( '.wpsl-field-dropdown-options' ).length ) {
                        const uniqId = $fieldEditor.closest( '.wpsl-field-item' ).data( 'field' );
                        const nameAttr = $fieldEditor.find( 'select' ).attr( 'name' ).replace('[type]', '[options]' );
                        
                        let dropdownHtml = '<p class="wpsl-field-dropdown-options">';
                            dropdownHtml += '<label for="wpsl-fields-' + uniqId + '-options">' + wpslL10n.fieldsDropdownOptions + '</label>';
                            dropdownHtml += '<textarea id="wpsl-fields-' + uniqId + '-options" name="' + nameAttr + '" placeholder="' + wpslL10n.fieldsDropdownPlaceholder + '"></textarea>';
                            dropdownHtml += '</p>';
                        
                        $fieldEditor.find( '.wpsl-field-type-options' ).after( dropdownHtml );
                        $fieldEditor.find( '.wpsl-field-dropdown-options' ).show().css( 'margin-bottom', 3 );
                    }
                    break;
                case 'textarea':
                    const $defaultField = $fieldEditor.find( '.wpsl-field-default' );
                    const $defaultInput = $defaultField.find( 'input[type="text"]' );

                    if ( $defaultInput.length ) {
                        const textareaHtml = '<textarea name="' + $defaultInput.attr( 'name' ) + '" placeholder="' + $defaultInput.attr( 'placeholder' ) + '" id="' + $defaultInput.attr( 'id' ) + '">' + $defaultInput.val() + '</textarea>';
                        $defaultInput.replaceWith( textareaHtml );
                    }
                    
                    $defaultField.show();
                    $fieldEditor.find( '.wpsl-bbcode-info' ).show();
                    break;
                default:
                    $fieldEditor.find( '.wpsl-field-default, .wpsl-is-field-required' ).show();
                    $fieldEditor.find( '.wpsl-bbcode-info' ).hide();
                    break;
            }

            if ( noMarginElem ) {
                $fieldEditor.find( noMarginElem ).css( 'margin-bottom', 3 );
            }
        });

        this.eventListeners.bind();
        fieldsUI.sortableFields();
        fieldsUI.updateDraggableState();

        // Restore the previously selected field group from localStorage.
        // This must happen after eventListeners.bind() so the change handler is active.
        try {
            const savedGroupId = localStorage.getItem( 'wpsl_selected_field_group' );
            if ( savedGroupId && jQuery( '.wpsl-field-group-list option[value="' + savedGroupId + '"]' ).length ) {
                jQuery( '.wpsl-field-group-list' ).val( savedGroupId ).trigger( 'change' );
            }
        } catch( e ) {}
    },

    /**
     * Event listeners orchestration.
     *
     * @since 3.0.0
     */
    eventListeners: {
        /**
         * Bind all event listeners.
         *
         * @since   3.0.0
         * @returns {void}
         */
        bind: function() {
            this.checkRequiredFields();
            this.groupDropdown();
            this.manager();
            fieldsEvents.add();
            groupsEvents.add();
            this.checkFieldNameDuplicates();
        },

        /**
         * Make sure the required fields are set before saving the changes.
         *
         * @since   3.0.0
         * @returns {void}
         */
        checkRequiredFields: function() {
            let inputMissing = false;
            let expandedFieldItems = new Set();

            jQuery( '#wpsl-field-actions .button-primary' ).on( 'click', function() {
                let groupNames = [];
                let emptyRequiredFields = [];

                fieldsValidation.removeRequiredWarning();

                jQuery( '.wpsl-field-editor .wpsl-required-field' ).each( function() {
                    if ( ! jQuery( this ).val() ) {
                        const groupId = jQuery( this ).parents( '.wpsl-fields-list-wrap' ).data( 'group-id' );
                        const groupName = "'" + jQuery( '.wpsl-field-group-list option[value="' + groupId + '"]' ).text() + "'"

                        if ( jQuery.inArray( groupName, groupNames ) === -1 ) {
                            groupNames.push( groupName );
                        }

                        inputMissing = true;
                        jQuery( this ).addClass( 'wpsl-error' );
                        
                        const $fieldItem = jQuery( this ).closest( '.wpsl-field-item' );
                        if ( $fieldItem.length ) {
                            emptyRequiredFields.push( $fieldItem );
                        }
                    }
                });

                const requiredFieldsMissingText = wpslL10n.requiredFieldsMissing.replace( /{groups}/, groupNames.join( ', ' ) );

                if ( inputMissing ) {
                    jQuery( '#wpsl-field-actions' ).siblings( '.wpsl-warning.wpsl-defaults' ).remove();
                    jQuery( '#wpsl-field-actions .submit' ).before( '<p class="wpsl-warning wpsl-defaults">' + requiredFieldsMissingText + '</p>' );
                    
                    jQuery( '.wpsl-fields-list-wrap' ).find( '.wpsl-field-editor' ).hide();
                    jQuery( '.wpsl-fields-list-wrap .wpsl-field-item' ).removeClass( 'wpsl-field-expanded' );

                    let fieldItemToExpand = null;
                    
                    for ( let i = 0; i < emptyRequiredFields.length; i++ ) {
                        const $fieldItem = emptyRequiredFields[i];
                        const fieldItemId = $fieldItem.attr( 'id' );
                            
                        if ( ! expandedFieldItems.has( fieldItemId ) ) {
                            fieldItemToExpand = $fieldItem;
                            expandedFieldItems.add( fieldItemId );
                            break;
                        }
                    }
                        
                    if ( ! fieldItemToExpand && emptyRequiredFields.length > 0 ) {
                        fieldItemToExpand = emptyRequiredFields[0];
                        
                        if ( expandedFieldItems.size >= emptyRequiredFields.length ) {
                            expandedFieldItems.clear();
                        }

                        expandedFieldItems.add( fieldItemToExpand.attr( 'id' ) );
                    }
                    
                    if ( fieldItemToExpand ) {
                        const groupId = fieldItemToExpand.closest( '.wpsl-fields-list-wrap' ).data( 'group-id' );
                        if ( groupId ) {
                            jQuery( '.wpsl-field-group-list' ).val( groupId ).trigger( 'change' );
                        }
                        
                        fieldItemToExpand.addClass( 'wpsl-field-expanded' ).removeAttr( 'style' );
                        fieldItemToExpand.find( '.wpsl-field-editor' ).fadeIn( 500 );
                            
                        const typeValue = fieldItemToExpand.find( 'select' ).val();
                        if ( typeValue === 'dropdown' ) {
                            fieldItemToExpand.find( '.wpsl-field-dropdown-options' ).show();
                        }

                        if ( typeValue === 'dropdown' || typeValue === 'checkbox' ) {
                            fieldItemToExpand.find( '.wpsl-field-default' ).hide();
                        }
                            
                        fieldsManager.eventListeners.typeDropdown();
                            
                        jQuery( 'html, body' ).animate({
                            scrollTop: fieldItemToExpand.offset().top - 50
                        }, 500 );
                    }

                    inputMissing = false;

                    return false;
                } else {
                    jQuery( '#wpsl-field-actions .wpsl-warning' ).remove();
                    expandedFieldItems.clear();
                }
            });
        },

        /**
         * Make sure the correct fields group is shown when the group selection changes.
         *
         * @since   3.0.0
         * @returns {void}
         */
        groupDropdown: function() {
            jQuery( '.wpsl-field-group-list' ).off( 'change' ).on( 'change', function() {
                const selectedGroupId = jQuery( this ).val();
                const $fieldManagerWrap = jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + selectedGroupId + '"]' );

                fieldsValidation.removeRequiredWarning();

                jQuery( '.wpsl-fields-list-wrap' ).hide();
                jQuery( '.wpsl-fields-manager-wrap .wpsl-field-item' ).removeClass( 'wpsl-field-expanded' );

                $fieldManagerWrap.show();

                // Persist the selected group so it can be restored on page reload.
                try {
                    localStorage.setItem( 'wpsl_selected_field_group', selectedGroupId );
                } catch( e ) {}

                if ( ! $fieldManagerWrap.find( '.wpsl-field-item' ).length ) {
                    const args = {
                        'includeWrap': false,
                        'fieldVisible': true,
                        'fieldExpanded': true,
                        'group_id': jQuery( this ).val()
                    };

                    const fieldTemplate = fieldsUtility.buildTemplate( args );

                    $fieldManagerWrap.html( fieldTemplate );
                    fieldsUI.callToggleSlider( jQuery( this ).val() );
                } else if ( $fieldManagerWrap.find( '.wpsl-field-item' ).length === 1 ) {
                    $fieldManagerWrap.find( '.wpsl-field-item ul' ).trigger( 'click' );
                } else {
                    $fieldManagerWrap.find( '.wpsl-field-editor' ).hide()
                }

                fieldsEvents.update();
                fieldsUI.updateDraggableState();
            });
        },

        /**
         * Based on the selected field type either show / hide / insert
         * the textarea that holds the dropdown values.
         *
         * @since   3.0.0
         * @returns {void}
         */
        typeDropdown: function() {
            jQuery( '.wpsl-field-expanded select' ).off( 'change' ).on( 'change', function() {
                const selectedVal = jQuery( this ).val();
                const $fieldItem = jQuery( this ).closest( '.wpsl-field-item' );
                const $fieldEditor = $fieldItem.find( '.wpsl-field-editor' );
                const $defaultField = $fieldEditor.find( '.wpsl-field-default' );

                jQuery( '.wpsl-field-expanded .wpsl-field-type' ).text( selectedVal );
                $fieldEditor.find( '.wpsl-field-default, .wpsl-is-field-required, .wpsl-field-dropdown-options' ).hide();

                switch( selectedVal ) {
                    case 'checkbox':
                        $fieldEditor.find( '.wpsl-field-default, .wpsl-is-field-required' ).hide();
                        $fieldEditor.find( '.wpsl-field-type-options' ).css( 'margin-bottom', '3px' );

                        break;
                    case 'dropdown':
                        if ( ! $fieldEditor.find( '.wpsl-field-dropdown-options' ).length ) {
                            const uniqId = $fieldItem.data( 'field' );
                            const nameAttr = jQuery( this ).attr( 'name' ).replace('[type]', '[options]' );
                            
                            let dropdownHtml = '<p class="wpsl-field-dropdown-options">';
                                dropdownHtml += '<label for="wpsl-fields-' + uniqId + '-options">' + wpslL10n.fieldsDropdownOptions + '</label>';
                                dropdownHtml += '<textarea id="wpsl-fields-' + uniqId + '-options" name="' + nameAttr + '" class="wpsl-required-field" placeholder="' + wpslL10n.fieldsDropdownPlaceholder + '"></textarea>';
                                dropdownHtml += '</p>';
                            
                            $fieldEditor.find( '.wpsl-field-type-options' ).after( dropdownHtml );
                            wpslSharedFuncs.bindInfoPopup( $fieldEditor.find( '.wpsl-field-dropdown-options .wpsl-info' ) );
                        }
                        
                        const $dropdownOptions = $fieldEditor.find( '.wpsl-field-dropdown-options' );
                        $dropdownOptions.show().css( 'margin-bottom', 3 );
                        
                        const $textarea = $dropdownOptions.find( 'textarea' );

                        if ( $textarea.length ) {
                            $textarea.trigger( 'blur' );
                        }

                        break;
                    case 'textarea':
                        const $defaultTextarea = $defaultField.find( 'textarea' );
                        
                        if ( ! $defaultTextarea.length ) {
                            const $defaultInput = $defaultField.find( 'input[type="text"]' );
                            
                            if ( $defaultInput.length ) {                                
                                const textareaHtml = '<textarea name="' + $defaultInput.attr( 'name' ) + '" id="' + $defaultInput.attr( 'id' ) + '">' + $defaultInput.val() + '</textarea>';
                                $defaultInput.replaceWith( textareaHtml );
                            }
                        }
                        
                        fieldsUI.showDefaultAndRequired( $fieldEditor );
                        $fieldEditor.find( '.wpsl-bbcode-info' ).show();

                        break;
                    default:
                        const $existingTextarea = $defaultField.find( 'textarea' );
                        
                        if ( $existingTextarea.length ) {
                            const inputHtml = '<input type="text" value="' + $existingTextarea.val() + '" name="' + $existingTextarea.attr( 'name' ) + '" id="' + $existingTextarea.attr( 'id' ) + '">';
                            $existingTextarea.replaceWith( inputHtml );
                        }
                        
                        fieldsUI.showDefaultAndRequired( $fieldEditor );
                        $fieldEditor.find( '.wpsl-bbcode-info' ).hide();

                        break;
                }
            });
        },

        /**
         * Field / group managing listeners.
         *
         * @since   3.0.0
         * @returns {void}
         */
        manager: function() {
            const validateGroupName = function() {
                try {
                    const $groupNameField = jQuery( '#wpsl-group-name' );

                    if ( ! $groupNameField.length ) return false;

                    const groupNameValue = ( $groupNameField.val() || '' ).trim();
                    const $createGroup = jQuery( '#wpsl-create-first-group' );

                    $createGroup.prop( 'disabled', ! groupNameValue );
                    $groupNameField.toggleClass( 'wpsl-error', ! groupNameValue );

                    // Clears the mark a server-side rejection left behind too.
                    if ( groupNameValue ) {
                        $groupNameField.removeAttr( 'aria-invalid' );
                    }
                    
                    return !! groupNameValue;
                } catch ( error ) {
                    return false;
                }
            };

            jQuery( '#wpsl-create-first-group' ).on( 'click', function() {
                if ( ! validateGroupName() ) return false;
                
                const $groupNameField = jQuery( '#wpsl-group-name' );
                const groupNameValue = $groupNameField.val().trim();
                
                const args = {
                    type: 'create_group',
                    value: groupNameValue,
                    nonce: jQuery( '#wpsl-create-group-nonce' ).val()
                };

                const $button = jQuery( this );

                $button.hide();
                preloader.add( $button );

                fieldsUtility.makeRequest( args, function( data ) {
                    const uniquId = helpers.utils.uniqId();

                    jQuery( '#wpsl-field-actions' ).show();

                    if ( ! jQuery( '.wpsl-fields-manager-wrap .wpsl-fields-list-wrap' ).length ) {
                        const args = {
                            'includeWrap': true,
                            'fieldVisible': true,
                            'fieldExpanded': true,
                            'group_id': data.id,
                        };

                        const fieldsHeaderTemplate = jQuery( '#wpsl-fields-header-template' ).html();
                        const fieldTemplate = fieldsUtility.buildTemplate( args );

                        jQuery( '#wpsl-field-actions' ).before( fieldsHeaderTemplate + fieldTemplate );
                    } else {
                        jQuery( '.wpsl-fields-manager-wrap' ).children().show();
                        jQuery( '.wpsl-fields-list-wrap' ).attr( 'data-group-id', data.id );
                        jQuery( '.wpsl-field-item' ).attr( 'data-field', uniquId );
                    }

                    jQuery( '.wpsl-fields-manager-options' ).show();
                    jQuery( '#wpsl-fields-first-group' ).hide();
                    jQuery( '.wpsl-fields-list-wrap .wpsl-field-item' ).last().find( 'input[type="text"]' ).val( '' );

                    fieldsUI.sortableFields();
                    wpslSharedFuncs.bindInfoPopup( jQuery( '.wpsl-field-editor .wpsl-info' ) );

                    if ( ! jQuery( '.wpsl-field-group-list option' ).length ) {
                        const $fieldManagerOptions = jQuery( '#wpsl-fields-manager-options' ).html();

                        jQuery( '#wpsl-fields-header' ).before( $fieldManagerOptions );
                        jQuery( '.wpsl-field-group-list' ).append( '<option value="' + sharedHelpers.escapeHtml( data.id ) + '">' + sharedHelpers.sanitizeForDisplay( data.name ) + '</option>' );

                        jQuery( '#wpsl-manage-groups' ).on( 'click', function() {
                            groupsCRUD.show( fieldsManager.eventListeners );
                            return false;
                        });

                        fieldsManager.eventListeners.groupDropdown();
                    } else {
                        jQuery( '.wpsl-field-group-list' ).html( '<option value="' + sharedHelpers.escapeHtml( data.id ) + '">' + sharedHelpers.sanitizeForDisplay( data.name ) + '</option>' );
                    }

                    groupsCRUD.buildList();
                    fieldsEvents.update();

                    const $sliderElem = jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + data.id + '"]' ).find( 'input[type=checkbox]' );
                    wpslSharedFuncs.createToggleSliders( $sliderElem );

                    preloader.remove();
                }, function( message ) {

                    // The button was hidden before the request went out, so a
                    // rejected name has to put it back to allow a retry.
                    preloader.remove();
                    $button.show();

                    $groupNameField.addClass( 'wpsl-error' ).attr( 'aria-invalid', 'true' ).focus();

                    alert( message );
                });

                return false;
            });

            jQuery( '#wpsl-group-name' ).on( 'input', function() {
                validateGroupName();
            });

            jQuery( '#wpsl-manage-groups' ).on( 'click', function() {
                groupsCRUD.show( fieldsManager.eventListeners );
                return false;
            });

            jQuery( '#wpsl-show-fields-manager' ).on( 'click', function() {
                const groupData = {};
                let optionList = '';
                let duplicateFound = false;
                let groupNames = [];

                jQuery( '#wpsl-manage-group-list li' ).each( function() {
                    const groupId = jQuery( this ).data( 'group-id' );
                    const groupName = jQuery( this ).find( 'input' ).val();

                    if ( groupId && groupName ) {
                        if ( groupNames.includes( groupName ) ) {
                            duplicateFound = true;
                            jQuery( this ).find( 'input' ).addClass( 'wpsl-error' );
                            
                            return false;
                        }
                        
                        groupNames.push( groupName );
                        optionList += '<option value="' + sharedHelpers.escapeHtml( groupId ) + '">' + sharedHelpers.sanitizeForDisplay( groupName ) + '</option>';

                        if ( ! jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + groupId + '"]' ).length ) {
                            const args = {
                                'includeWrap': true,
                                'fieldVisible': true,
                                'fieldExpanded': true,
                                'group_id': groupId,
                            };

                            const fieldTemplate = fieldsUtility.buildTemplate( args );
                            jQuery( '.wpsl-fields-list-wrap' ).last().after( fieldTemplate );

                            fieldsUI.callToggleSlider( groupId );
                        }

                        jQuery.extend( groupData, { [ groupId ]: groupName } );
                    }
                });

                // Unfilled new groups persist in the DOM and trip the save-form validation — remove them.
                jQuery( '#wpsl-manage-group-list li' ).each( function() {
                    if ( ! jQuery( this ).find( 'input' ).val().trim() ) {
                        jQuery( this ).remove();
                    }
                });

                if ( duplicateFound ) {
                    jQuery( '#wpsl-groups-header' ).siblings( '.wpsl-warning.wpsl-defaults' ).remove();
                    jQuery( '#wpsl-groups-header' ).before( '<p class="wpsl-warning wpsl-defaults">' + wpslL10n.duplicateGroupNames + '</p>' );
                    jQuery( '.wpsl-error' ).first().focus();

                    return false;
                }

                if ( jQuery.isEmptyObject( groupData ) ) {
                    jQuery( '.wpsl-groups-manager-wrap, .wpsl-groups-header' ).hide();
                    jQuery( '.wpsl-fields-manager-wrap' ).children().hide();
                    jQuery( '.wpsl-fields-manager-wrap, #wpsl-fields-first-group, #wpsl-create-first-group' ).show();
                    jQuery( '.wpsl-field-group-list' ).focus();
                } else {
                    const args = {
                        type: 'update_group',
                        value: groupData,
                        nonce: jQuery( '#wpsl-update-groups-nonce' ).val()
                    };

                    preloader.add( jQuery( this ) );

                    fieldsUtility.makeRequest( args, function( data ) {
                        jQuery.each( groupData, function( groupId, value ) {
                            if ( ! jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + groupId + '"]' ).length ) {
                                jQuery( '#wpsl-fields-header' ).after( '<div class="wpsl-fields-list-wrap" data-group-id="' + groupId + '"></div>' );
                            }
                        });

                        jQuery( '.wpsl-field-group-list' ).html( optionList );
                        jQuery( '.wpsl-fields-manager-wrap' ).show();
                        jQuery( '.wpsl-groups-manager-wrap, .wpsl-groups-header, .wpsl-fields-list-wrap' ).hide();

                        // Restore the previously selected group, or fall back to the first.
                        let activeGroupId = jQuery( '.wpsl-field-group-list' ).val();
                        try {
                            const savedGroupId = localStorage.getItem( 'wpsl_selected_field_group' );
                            if ( savedGroupId && jQuery( '.wpsl-field-group-list option[value="' + savedGroupId + '"]' ).length ) {
                                activeGroupId = savedGroupId;
                                jQuery( '.wpsl-field-group-list' ).val( savedGroupId );
                            }
                        } catch( e ) {}
                        jQuery( '.wpsl-fields-manager-wrap [data-group-id="' + activeGroupId + '"]' ).show();
                        jQuery( '#wpsl-add-group' ).prop( 'disabled', false );

                        fieldsEvents.update();
                        fieldsUI.sortableFields();
                        fieldsUI.updateDraggableState();

                        preloader.remove();
                        jQuery( '.wpsl-field-group-list' ).focus();
                    });
                }

                return false;
            });

            jQuery( '#wpsl-add-field' ).on( 'click', function() {
                fieldsCRUD.add( fieldsManager.eventListeners );

                return false;
            });
        },

        /**
         * Check for duplicate field names when a field loses focus.
         *
         * @since   3.0.0
         * @returns {void}
         */
        checkFieldNameDuplicates: function() {
            jQuery( '#wpsl-fields-manager' ).on( 'focus', '.wpsl-field-editor .wpsl-field-name', function() {
                const originalValue = jQuery( this ).val().trim();
                jQuery( this ).data( 'original-value', originalValue );

                // Focusing the name field marks it as manually edited, so a
                // later label blur no longer overwrites the auto-filled name.
                jQuery( this ).removeData( 'auto-name' );
            });
                
            jQuery( '#wpsl-fields-manager' ).on( 'blur', '.wpsl-field-editor .wpsl-field-name', function() {
                fieldsValidation.validateFieldName( jQuery( this ) );
            });
        },

        fields: fieldsEvents,
        groups: groupsEvents,
    },

    /**
     * Validate main form submission to prevent empty group names from being saved.
     * 
     * @since 3.0.0
     */
    validateMainFormSubmit: function() {
        const $form = jQuery( '#wpsl-settings-form' );

        if ( ! $form.length ) return;
        
        $form.on( 'submit', function( e ) {
            let hasEmptyGroupName = false;
            
            jQuery( '#wpsl-manage-group-list input[type="text"]' ).each( function() {
                const groupName = jQuery( this ).val().trim();
                
                if ( ! groupName ) {
                    hasEmptyGroupName = true;
                    
                    return false;
                }
            });
            
            if ( hasEmptyGroupName ) {
                e.preventDefault();
                
                groupsCRUD.show( fieldsManager.eventListeners );
                
                jQuery( '#wpsl-groups-header' ).siblings( '.wpsl-warning.wpsl-defaults' ).remove();
                jQuery( '#wpsl-groups-header' ).before( '<p class="wpsl-warning wpsl-defaults">' + wpslL10n.emptyGroupName + '</p>' );
                
                jQuery( 'html, body' ).animate({
                    scrollTop: jQuery( '#wpsl-groups-header' ).offset().top - 50
                }, 500 );
                
                return false;
            }
        });
    }
};