import { helpers } from '../wpsl-helpers.js'; 

/**
 * This manages the options in the data management dialog.
 * 
 * - Reset all settings to defaults
 * - Remove all locations / categories
 *
 * @since 3.0.0
 */
export const dataManagement = {
    /**
     * Set to false when the dialog closes, which cancels an ongoing operation.
     *
     * @since 3.0.0
     */
    isProcessing: false,

    /**
     * Initialize the data management module.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        this.bindHandlers();
    },

    /**
     * Bind the required button listeners
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers: function() {
        const self = this;

        jQuery( '#wpsl-reset-data' ).on( 'click', function() {
            self.createDialog();
        });
    },

    /**
     * Create the dialog box
     *
     * @since   2.2.22
     * @returns {void}
     */
    createDialog: function() {
        jQuery( '#wpsl-data-management' ).dialog({
            resizable: false,
            height: 'auto',
            width: 400,
            modal: true,
            closeText: '',
            dialogClass: 'wpsl-dialog wpsl-data-management',
            classes: { 'ui-dialog': 'wpsl-dialog wpsl-data-management' },
            open: function() {
                dataManagement.resetDialogState();
                dataManagement.isProcessing = false;

                helpers.ui.dialog.addCloseButton( 'wpsl-data-management' );
                helpers.ui.dialog.bindCloseHandler( '#wpsl-data-management' );

                if ( ! jQuery( '#wpsl-data-management .wpsl-toggler-slider' ).length ) {
                    wpslSharedFuncs.createToggleSliders( jQuery( '#wpsl-data-management input[type=checkbox]' ) );
                }
                
                dataManagement.setupConditionalVisibility();
                
                const $saveButton = jQuery( '#wpsl-data-changes' );

                if ( $saveButton.length ) {
                    $saveButton.prop( 'disabled', true ).addClass( 'ui-state-disabled' );
                }
            },
            close: function() {
                dataManagement.isProcessing = false;
                dataManagement.updateCheckboxCounts();
                dataManagement.resetDialogState();
            },
            buttons: [
                {
                    text: wpslL10n.applyChanges,
                    'class': 'button-primary wpsl-save-changes',
                    'id': 'wpsl-data-changes',
                    click: function() {
                        if ( ! jQuery( '#wpsl-data-changes' ).hasClass( 'ui-state-disabled' ) ) {
                            jQuery( '#wpsl-confirmation-section' ).addClass( 'wpsl-hide' );

                            dataManagement.processDataManagement();
                        }
                    }
                },
                {
                    text: wpslL10n.close,
                    'class': 'button-secondary',
                    click: function() {
                        jQuery( this ).dialog( 'close' );
                    }
                }
            ],
        });
    },

    /**
     * Progress bar management for location deletion operations
     *
     * @since 3.0.0
     */
    progressBar: {
        /**
         * Create and inject the progress bar UI into the dialog
         *
         * @since   3.0.0
         * @returns {void}
         */
        create: function() {
            const $dialog = jQuery( '#wpsl-data-management' );    
            $dialog.find( '.wpsl-progress-container' ).remove();
            
            const progressHtml = `
                <div class="wpsl-progress-container">
                    <div class="wpsl-progress-label">
                        ${wpslL10n.deletingStores}
                    </div>
                    <div class="wpsl-progress-bar-container">
                        <div class="wpsl-progress-bar">
                            <div class="wpsl-progress-percentage">0%</div>
                        </div>
                    </div>
                    <div class="wpsl-progress-text">
                        ${wpslL10n.preparingDeletion}
                    </div>
                </div>
            `;
            
            $dialog.find( '.ui-dialog-buttonpane' ).before( progressHtml );
            
            const $progressContainer = $dialog.find( '.wpsl-progress-container' );

            if ( $progressContainer.length > 0 ) {
                $progressContainer.hide().fadeIn( 300 );
            }
        },
        
        /**
         * Update the progress bar with current deletion status
         *
         * @since   3.0.0
         * @param   {Object} progress Progress data from server response
         * @returns {void}
         */
        update: function( progress ) {
            const $dialog = jQuery( '#wpsl-data-management' );
            const $progressBar = $dialog.find( '.wpsl-progress-bar' );
            const $progressText = $dialog.find( '.wpsl-progress-text' );
            const $progressLabel = $dialog.find( '.wpsl-progress-label' );
            const $progressPercentage = $dialog.find( '.wpsl-progress-percentage' );
                        
            if ( progress.error ) {
                $progressLabel.text( wpslL10n.errorOccurredDeletion );
                $progressBar.css( 'background', '#dc3232' );
                $progressText.text( wpslL10n.errorOccured );
                $progressPercentage.text( wpslL10n.error );

                return;
            }
            
            $progressBar.css( 'width', progress.percentage + '%' );
            $progressPercentage.text( progress.percentage + '%' );
            
            if ( progress.completed ) {
                $progressLabel.text( wpslL10n.deletingCompleted );
                $progressText.text( wpslL10n.successfullyProcessed.replace( '%s', progress.total ) );
                $progressBar.css( 'background', 'linear-gradient(90deg, #46b450, #2e7d32)' );
                $progressPercentage.text( '100%' );
            } else {
                $progressLabel.text( wpslL10n.deletingStores );
                $progressText.text( 
                    wpslL10n.progressProcessed
                        .replace( '%1$s', progress.processed )
                        .replace( '%2$s', progress.total )
                        .replace( '%3$s', progress.percentage )
                );
            }
        }
    },
    
    /**
     * Task processor for data management operations
     *
     * @since 3.0.0
     */
    processor: {
        /**
         * Process location deletion in batches
         *
         * A callback switches to task mode: errors stay silent and progress
         * goes to the multi-task UI instead of the standalone progress bar.
         *
         * @since   3.0.0
         * @param   {number}   offset    Starting position for the current batch
         * @param   {number}   batchSize Number of locations to delete per batch
         * @param   {Function} callback  Optional callback for task mode (receives success boolean)
         * @returns {void}
         */
        locationsDeletion: function( offset, batchSize, callback = null ) {
            const isTaskMode = callback !== null;
            
            // Check if processing was cancelled, this happens when the
            // user closed the dialog before the process was complete.
            if ( isTaskMode && ! dataManagement.isProcessing ) {
                callback( false );
                return;
            }
            
            const ajaxData = {
                action: 'wpsl_data_management',
                nonce: jQuery( '#wpsl-data-management-nonce' ).val(),
                reset: ['locations'],
                progress_mode: 'true',
                offset: offset,
                batch_size: batchSize
            };
            
            jQuery.ajax({
                url: wpslSettings.ajaxurl,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: function( response ) {
                    if ( response.delete_locations && response.delete_locations.success ) {
                        const result = response.delete_locations;
                        const progress = result.progress;
                        
                        if ( isTaskMode ) {
                            dataManagement.updateLocationTaskProgress( progress );
                        } else {
                            dataManagement.progressBar.update( progress );
                        }
                        
                        if ( progress.completed ) {
                            if ( isTaskMode ) {
                                callback( true );
                            } else {
                                dataManagement.updateCheckboxCounts();
                                
                                setTimeout( function() {
                                    dataManagement.resetSaveButton();
                                }, 2000 );
                            }
                        } else if ( dataManagement.isProcessing ) {
                            // Check again inside setTimeout in
                            // case dialog was closed during the delay.
                            setTimeout( function() {
                                if ( dataManagement.isProcessing ) {
                                    dataManagement.processor.locationsDeletion( progress.next_offset, batchSize, callback );
                                } else if ( isTaskMode ) {
                                    callback( false );
                                }
                            }, 50 );
                        } else if ( isTaskMode ) {
                            callback( false );
                        }
                    } else {
                        if ( isTaskMode ) {
                            callback( false );
                        } else {
                            let errorMessage = wpslL10n.errorDuringDeletion;

                            if ( response.delete_locations && response.delete_locations.message ) {
                                errorMessage = response.delete_locations.message;
                            } else if ( response.data ) {
                                errorMessage = response.data;
                            } else if ( response.message ) {
                                errorMessage = response.message;
                            }
                            
                            dataManagement.showMessage( errorMessage, 'error' );
                            dataManagement.resetSaveButton();
                            dataManagement.progressBar.update({ error: true });
                        }
                    }
                },
                error: function() {
                    if ( isTaskMode ) {
                        callback( false );
                    } else {
                        dataManagement.showMessage( wpslL10n.errorDuringProcessing, 'error' );
                        dataManagement.resetSaveButton();
                        dataManagement.progressBar.update({ error: true });
                    }
                }
            });
        },
        
        /**
         * Execute a single data management task
         *
         * @since   3.0.0
         * @param   {string}   taskName Task identifier ('settings', 'categories', or 'locations')
         * @param   {Function} callback Function to call with success boolean when complete
         * @returns {void}
         */
        singleTask: function( taskName, callback ) {
            if ( taskName === 'locations' ) {
                this.singleLocationTask( callback );

                return;
            }
            
            const ajaxData = {
                action: 'wpsl_data_management',
                nonce: jQuery( '#wpsl-data-management-nonce' ).val(),
                reset: [ taskName ],
                confirmation: true
            };
            
            jQuery.ajax({
                url: wpslSettings.ajaxurl,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: function( response ) {
                    let success = false;
                    let errorMessage = null;
                    
                    if ( response.success === true ) {
                        success = true;
                    } else if ( response[taskName] && response[taskName].success === true ) {
                        success = true;
                    } else if ( response.reset_settings && response.reset_settings.success === true ) {
                        success = true;
                    } else if ( response.delete_categories && response.delete_categories.success === true ) {
                        success = true;
                    } else {
                        if ( response.data && typeof response.data === 'string' ) {
                            errorMessage = response.data;
                        } else if ( response[taskName] && response[taskName].message ) {
                            errorMessage = response[taskName].message;
                        } else if ( response.reset_settings && response.reset_settings.message ) {
                            errorMessage = response.reset_settings.message;
                        } else if ( response.delete_categories && response.delete_categories.message ) {
                            errorMessage = response.delete_categories.message;
                        } else if ( response.message ) {
                            errorMessage = response.message;
                        }
                    }
                    
                    callback( success, errorMessage );
                },
                error: function() {
                    callback( false, wpslL10n.errorDuringProcessing );
                }
            });
        },
        
        /**
         * Initialize location deletion for multi-task workflow
         *
         * @since   3.0.0
         * @param   {Function} callback Function to call with success boolean when complete
         * @returns {void}
         */
        singleLocationTask: function( callback ) {
            dataManagement.createLocationTaskProgress();
            this.locationsDeletion( 0, wpslSettings.locationBatchSize, callback );
        },
    },

    /**
     * Set up conditional visibility for the confirmation section
     *
     * @since   3.0.0
     * @returns {void}
     */
    setupConditionalVisibility: function() {
        const $destructiveActions = jQuery( '.wpsl-data-action' );
        const $confirmationSection = jQuery( '#wpsl-confirmation-section' );
        const $confirmationCheckbox = jQuery( '#wpsl-confirmation' );
        
        const toggleConfirmationSection = function() {
            const anyDestructiveSelected = $destructiveActions.is( ':checked' );
            if ( anyDestructiveSelected ) {
                $confirmationSection.removeClass( 'wpsl-hide' );
            } else {
                $confirmationSection.addClass( 'wpsl-hide' );
                $confirmationCheckbox.prop( 'checked', false );
            }
            
            dataManagement.updateSaveButtonState();
        };
        
        const handleConfirmationChange = function() {
            dataManagement.updateSaveButtonState();
        };
        
        // Namespaced off/on: this runs on every dialog open and the checkboxes
        // outlive the dialog, so a plain .on() would stack a handler per open.
        $destructiveActions.off( 'change.wpslDataManagement' ).on( 'change.wpslDataManagement', toggleConfirmationSection );
        $confirmationCheckbox.off( 'change.wpslDataManagement' ).on( 'change.wpslDataManagement', handleConfirmationChange );

        toggleConfirmationSection();
    },
    
    /**
     * Update the save button state based on current selections
     * Button is only enabled when confirmation checkbox is checked (if visible)
     *
     * @since   3.0.0
     * @returns {void}
     */
    updateSaveButtonState: function() {
        const $saveButton = jQuery( '#wpsl-data-changes' );
        
        // Button should be disabled only when:
        // Destructive actions are selected BUT confirmation is NOT checked
        const shouldDisable = jQuery( '.wpsl-data-action' ).is( ':checked' ) && ! jQuery( '#wpsl-confirmation' ).is( ':checked' );
        
        if ( shouldDisable ) {
            $saveButton.prop( 'disabled', true ).addClass( 'ui-state-disabled' );
        } else {
            $saveButton.prop( 'disabled', false ).removeClass( 'ui-state-disabled' );
        }
    },

    /**
     * Process the data management form submission via AJAX
     *
     * @since   3.0.0
     * @returns {void}
     */
    processDataManagement: function() {
        this.isProcessing = true;
        
        jQuery( '#wpsl-confirmation-section' ).addClass( 'wpsl-hide' );
        jQuery( '.wpsl-save-changes' ).hide();
        
        const selectedActions = [];

        jQuery( '#wpsl-data-management' ).find( 'input[type="checkbox"]:checked' ).each( function() {
            const actionName = jQuery( this ).attr( 'name' );

            if ( actionName && actionName !== 'confirmation' ) {
                selectedActions.push( actionName );
            }
        });
        
        this.hideAllActionParagraphs();
        this.createMultiStepProgress( selectedActions );
        this.processTaskQueue( selectedActions, 0 );
    },

    /**
     * Hide all action paragraphs when processing starts
     *
     * @since   3.0.0
     * @returns {void}
     */
    hideAllActionParagraphs: function() {
        jQuery( '#wpsl-data-management' ).find( 'p' ).addClass( 'wpsl-hide' );
    },

    /**
     * Reset the save button to its original state
     *
     * @since   3.0.0
     * @returns {void}
     */
    resetSaveButton: function() {
        jQuery( '.wpsl-save-changes' ).show().text( wpslSettings.applyChanges );
        this.updateSaveButtonState();
    },

    /**
     * Create multi-step progress display for selected tasks
     *
     * @since   3.0.0
     * @param   {Array} selectedActions Array of selected action names
     * @returns {void}
     */
    createMultiStepProgress: function( selectedActions ) {
        const $dialog = jQuery( '#wpsl-data-management' );

        $dialog.find( '.wpsl-multi-step-progress' ).remove();
        
        // Task definitions with display names
        const taskDefinitions = {
            'settings': {
                name: wpslL10n.resetSettingsToDefaults
            },
            'categories': {
                name: wpslL10n.deleteAllCategories
            },
            'locations': {
                name: wpslL10n.deleteAllLocations
            },
            'custom_markers': {
                name: wpslL10n.deleteAllCustomMarkers
            },
            'map_shapes': {
                name: wpslL10n.deleteAllMapShapes
            }
        };
        
        let progressHtml = `
            <div class="wpsl-multi-step-progress">
                <h4>${wpslL10n.processingTasks}</h4>
                <div class="wpsl-task-list">
        `;
        
        // Add each selected task to the progress display
        selectedActions.forEach( function( actionName, index ) {
            const task = taskDefinitions[ actionName ];
            if ( task ) {
                progressHtml += `
                    <div class="wpsl-task-item" data-task="${actionName}">
                        <div class="wpsl-task-header">
                            <div class="wpsl-task-name">${task.name}</div>
                            <div class="wpsl-task-status">
                                <img class="wpsl-task-spinner" src="${wpslSettings.url}/assets/img/ajax-loader.svg" alt="${wpslL10n.loading}" style="display: none; width: 16px; height: 16px;" />
                                <div class="wpsl-task-complete" style="display: none;">✓</div>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
        
        progressHtml += `
                </div>
            </div>
        `;
        
        // Insert before the button pane
        const $buttonPane = $dialog.find( '.ui-dialog-buttonpane' );
        
        if ( $buttonPane.length ) {
            $buttonPane.before( progressHtml );
        } else {
            $dialog.append( progressHtml );
        }
        
        $dialog.find( '.wpsl-multi-step-progress' ).hide().fadeIn( 300 );
    },

    /**
     * Update task status in the multi-step progress display
     *
     * @since   3.0.0
     * @param   {string} taskName The task name to update
     * @param   {string} status   The status: 'waiting', 'processing', 'complete', 'error'
     * @returns {void}
     */
    updateTaskStatus: function( taskName, status ) {
        const $taskItem = jQuery( '#wpsl-data-management' ).find( `.wpsl-task-item[data-task="${taskName}"]` );
        if ( ! $taskItem.length ) {
            return;
        }
        
        const $spinner = $taskItem.find( '.wpsl-task-spinner' );
        const $complete = $taskItem.find( '.wpsl-task-complete' );
        
        $spinner.hide();
        $complete.hide();
        
        switch ( status ) {
            case 'waiting':
                // Show nothing when waiting
                break;
            case 'processing':
                $spinner.show();
                break;
            case 'complete':
                $complete.show();
                break;
            case 'error':
                $complete.html( '✗' ).css( 'color', '#dc3232' ).show();
                break;
        }
    },

    /**
     * Process tasks sequentially from the task queue
     *
     * @since   3.0.0
     * @param   {Array}  selectedActions Array of selected action names
     * @param   {number} currentIndex    Current task index being processed
     * @returns {void}
     */
    processTaskQueue: function( selectedActions, currentIndex ) {
        const self = this;

        if ( ! this.isProcessing ) {
            return;
        }

        if ( currentIndex >= selectedActions.length ) {
            this.onAllTasksComplete();

            return;
        }
        
        const currentTask = selectedActions[ currentIndex ];
        
        this.updateTaskStatus( currentTask, 'processing' );

        this.processor.singleTask( currentTask, function( success, errorMessage ) {
            if ( success ) {
                self.updateTaskStatus( currentTask, 'complete' );
                
                setTimeout( function() {
                    self.processTaskQueue( selectedActions, currentIndex + 1 );
                }, 800 );
            } else {
                self.updateTaskStatus( currentTask, 'error' );
                
                // Use specific error message if provided, otherwise use generic message
                const message = errorMessage || wpslL10n.failedToProcess.replace( '%s', currentTask );
                self.showMessage( message, 'error' );
                
                // Hide the Apply Changes button and make Close button primary on error
                jQuery( '#wpsl-data-changes' ).hide();
                self.swapButtonClasses();
                
                // Mark processing as complete to prevent further operations
                self.isProcessing = false;
            }
        });
    },

    /**
     * Create progress bar specifically for the locations task
     *
     * @since   3.0.0
     * @returns {void}
     */
    createLocationTaskProgress: function() {
        const $locationTask = jQuery( '#wpsl-data-management' ).find( '.wpsl-task-item[data-task="locations"]' );
        
        if ( ! $locationTask.length ) {
            return;
        }
        
        // Remove any existing progress bar (check both inside and after the task)
        $locationTask.find( '.wpsl-location-progress' ).remove();
        $locationTask.next( '.wpsl-location-progress' ).remove();
        
        // Add mini progress bar inside the task item
        const progressHtml = `
            <div class="wpsl-location-progress">
                <div class="wpsl-location-progress-text">${wpslL10n.preparing}</div>
                <div>
                    <div class="wpsl-location-progress-bar"></div>
                </div>
            </div>
        `;
        
        $locationTask.append( progressHtml );
    },

    /**
     * Update the mini progress bar for locations task
     *
     * @since   3.0.0
     * @param   {Object} progress Progress data from server
     * @returns {void}
     */
    updateLocationTaskProgress: function( progress ) {
        const $dialog = jQuery( '#wpsl-data-management' );
        const $progressBar = $dialog.find( '.wpsl-location-progress-bar' );
        const $progressText = $dialog.find( '.wpsl-location-progress-text' );
        
        if ( ! $progressBar.length ) {
            return;
        }
        
        $progressBar.css( 'width', progress.percentage + '%' );
        
        if ( progress.completed ) {
            $progressBar.css( 'width', '100%' );
            $progressText.text( wpslL10n.completeDeleted.replace( '%s', progress.processed ) );
        } else {
            $progressText.text( 
                wpslL10n.progressProcessed
                    .replace( '%1$s', progress.processed )
                    .replace( '%2$s', progress.total )
                    .replace( '%3$s', progress.percentage )
            );
        }
    },

    /**
     * Called when all tasks are completed
     *
     * @since   3.0.0
     * @returns {void}
     */
    onAllTasksComplete: function() {
        this.isProcessing = false;

        const $saveButton = jQuery( '#wpsl-data-changes' );
        $saveButton.hide();
        
        // Hide the "Processing Tasks..." header
        const $dialog = jQuery( '#wpsl-data-management' );
        $dialog.find( '.wpsl-multi-step-progress h4' ).hide();
        
        this.swapButtonClasses();
        this.updateCheckboxCounts();

        this.showMessage( wpslL10n.processingTasksCompleted, 'success' );
    },

    /**
     * Swap button classes - make Close button primary when processing is complete
     *
     * @since   3.0.0
     * @returns {void}
     */
    swapButtonClasses: function() {
        const $dialog = jQuery( '#wpsl-data-management' );
        const $buttonSet = $dialog.parent().find( '.ui-dialog-buttonset' );
        const $buttons = $buttonSet.find( 'button' );
        
        // Second button (Close) should become primary when processing is complete
        $buttons.each( function( index ) {
            const $button = jQuery( this );
            
            if ( index === 1 ) {
                $button.removeClass( 'button-secondary' ).addClass( 'button-primary' );
            }
        });
    },

    /**
     * Revert button classes to their original state
     *
     * @since   3.0.0
     * @returns {void}
     */
    revertButtonClasses: function() {
        const $dialog = jQuery( '#wpsl-data-management' );
        const $buttonSet = $dialog.parent().find( '.ui-dialog-buttonset' );
        const $buttons = $buttonSet.find( 'button' );
        
        // First button (Save Changes) should be primary, second button (Close) should be secondary
        $buttons.each( function( index ) {
            const $button = jQuery( this );
            
            if ( index === 0 ) {
                $button.removeClass( 'button-secondary' ).addClass( 'button-primary' );
            } else if ( index === 1 ) {
                $button.removeClass( 'button-primary' ).addClass( 'button-secondary' );
            }
        });
    },

    /**
     * Reset the dialog to its initial state
     *
     * @since   3.0.0
     * @returns {void}
     */
    resetDialogState: function() {
        const $dialog = jQuery( '#wpsl-data-management' );
                
        this.isProcessing = false;
        
        $dialog.find( 'input[type="checkbox"]' ).prop( 'checked', false );
        
        $dialog.find( 'p' ).not( '#wpsl-confirmation-section' ).each( function() {
            const $p = jQuery( this );
            const $countSpan = $p.find( '.wpsl-count' );
            
            $p.removeAttr( 'style' );
            
            // Only show paragraphs that don't have a count of (0)
            if ( $countSpan.length === 0 || $countSpan.text() !== '(0)' ) {
                $p.removeClass( 'wpsl-hide' );
            }
        });
        
        jQuery( '#wpsl-confirmation-section' ).addClass( 'wpsl-hide' ).removeAttr( 'style' );
        
        $dialog.find( '.wpsl-multi-step-progress' ).remove();
        $dialog.find( '.wpsl-location-progress' ).remove();
        $dialog.find( '.wpsl-message' ).remove();
        
        jQuery( '#wpsl-data-changes' ).removeClass( 'wpsl-hide' ).text( wpslSettings.applyChanges ).prop( 'disabled', true ).addClass( 'ui-state-disabled' );
        
        this.revertButtonClasses();
        
        $dialog.dialog( 'option', 'title', $dialog.attr( 'title' ) || wpslL10n.dataManagement );
    },

    /**
     * Update checkbox labels with current counts from server
     * 
     * Fetches fresh location and category counts after deletion operations
     * and updates the checkbox labels. Hides checkboxes if counts are zero.
     *
     * @since   3.0.0
     * @returns {void}
     */
    updateCheckboxCounts: function() {
        jQuery.ajax({
            url: wpslSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsl_get_data_counts',
                nonce: jQuery( '#wpsl-data-management-nonce' ).val()
            },
            dataType: 'json',
            success: function( response ) {
                if ( response.success && response.data ) {
                    const data = response.data;
                    
                    // Update locations checkbox
                    if ( data.locations ) {
                        const $locationsParagraph = jQuery( '#wpsl-delete-locations' ).closest( 'p' );
                        const $countSpan = $locationsParagraph.find( '.wpsl-count[data-type="locations"]' );
                        
                        $countSpan.text( '(' + data.locations.count + ')' );
                        
                        if ( ! data.locations.exist ) {
                            $locationsParagraph.addClass( 'wpsl-hide' );
                        }
                    }
                    
                    // Update categories checkbox
                    if ( data.categories ) {
                        const $categoriesParagraph = jQuery( '#wpsl-delete-categories' ).closest( 'p' );
                        const $countSpan = $categoriesParagraph.find( '.wpsl-count[data-type="categories"]' );

                        $countSpan.text( '(' + data.categories.count + ')' );

                        if ( ! data.categories.exist ) {
                            $categoriesParagraph.addClass( 'wpsl-hide' );
                        }
                    }

                    // Update custom markers checkbox
                    if ( data.custom_markers ) {
                        const $markersParagraph = jQuery( '#wpsl-delete-custom-markers' ).closest( 'p' );
                        const $countSpan = $markersParagraph.find( '.wpsl-count[data-type="custom_markers"]' );

                        $countSpan.text( '(' + data.custom_markers.count + ')' );

                        if ( ! data.custom_markers.exist ) {
                            $markersParagraph.addClass( 'wpsl-hide' );
                        }
                    }

                    // Update map shapes checkbox
                    if ( data.map_shapes ) {
                        const $shapesParagraph = jQuery( '#wpsl-delete-map-shapes' ).closest( 'p' );
                        const $countSpan = $shapesParagraph.find( '.wpsl-count[data-type="map_shapes"]' );

                        $countSpan.text( '(' + data.map_shapes.count + ')' );

                        if ( ! data.map_shapes.exist ) {
                            $shapesParagraph.addClass( 'wpsl-hide' );
                        }
                    }
                }
            }
        });
    },

    /**
     * Show a message to the user
     *
     * @since   3.0.0
     * @param   {string} message The message to show
     * @param   {string} type    The message type (success, error, info)
     * @returns {void}
     */
    showMessage: function( message, type ) {
        const $dialog = jQuery( '#wpsl-data-management' );
        
        $dialog.find( '.wpsl-message' ).remove();
        
        const messageClass = 'wpsl-message wpsl-message-' + type;
        const $message = jQuery( '<div class="' + messageClass + '"><p class="wpsl-no-spacing">' + message + '</p></div>' );
        
        $dialog.prepend( $message );
    }
};