import { api } from './wpsl-api.js';
import { state } from './wpsl-shared.js';
import { hours } from './wpsl-hours.js';
import { markers } from './wpsl-markers.js';
import { helpers } from './wpsl-helpers.js';
import { locationMarker } from './wpsl-location-marker.js';

/**
 * Editor page specific actions.
 *
 * @since 3.0.0
 */
const FOCUSABLE_SELECTOR = 'button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';

export const editor = {

    /**
     * Initialize the editor page modules and event handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        this.bindLookUpLocation();
        this.bindPublish();
        this.bindHandlers();

        hours.init();
        locationMarker.init();

        wpslSharedFuncs.bindInfoPopup();
    },

    /**
     * Return the online only location status
     *
     * @since   3.0.0
     * @returns {boolean}
     */
    getOnlineOnlyStatus: function() {
        return jQuery( '#wpsl-online' ).is( ':checked' );
    },

    /**
     * Reflect the online-only state in the editor UI.
     *
     * Online-only locations need no address. The class on the meta container
     * is what hides the required-field markers and the opening hours tab,
     * through CSS.
     *
     * @since   3.0.0
     * @param   {boolean} isOnline Whether the online-only checkbox is checked.
     * @returns {void}
     */
    toggleOnlineOnly: function( isOnline ) {
        const $metaNav = jQuery( '#wpsl-meta-nav' );

        jQuery( '#wpsl-map-preview' ).toggle( ! isOnline );
        jQuery( '#wpsl-location-status' ).toggleClass( 'wpsl-hide', isOnline );
        $metaNav.toggleClass( 'wpsl-online-only', isOnline );

        if ( isOnline ) {
            $metaNav.find( '.wpsl-error' ).removeClass( 'wpsl-error' );

            // The hours tab is hidden for online stores, so fall back to the
            // first tab when it was the active one, to avoid an orphaned panel.
            if ( $metaNav.find( 'input[name="tabs"].wpsl-hours-tab' ).is( ':checked' ) ) {
                $metaNav.find( 'input[name="tabs"]' ).first().prop( 'checked', true );
            }
        }
    },

    /**
     * Style the WPSL meta blocks as tabs.
     *
     * Only runs when no other script throws first. Without it the blocks
     * stack underneath each other, styled by CSS alone.
     *
     * @since   3.0.0
     * @returns {void}
     */
    showMetaNav: function() {
        jQuery( '#wpsl-meta-nav' ).show();

        // Hide all tabs and fallback titles.
        jQuery( '.wpsl-js-fallback, .wpsl-js-fallback-error, .wpsl-tab' ).not( '.wpsl-active' ).hide();
        jQuery( '.wpsl-tab' ).css( 'margin', 0 );
        
        // Make sure the first tab is selected by default for the JS-based tab system
        this.activateStoreTab( 'first' );
        
        // If there's a CSS-based tab system (radio buttons), select the first one
        if ( jQuery( '#wpsl-meta-nav input[type="radio"]' ).length ) {
            jQuery( '#wpsl-meta-nav input[type="radio"]:first' ).prop( 'checked', true );
        }
    },

    /**
     * Check the required fields and geocode the address on publish.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindPublish: function() {
        // Create tabs for the meta blocks.
        this.showMetaNav();

        if ( state.blockEditor ) {
            const wpEditor = wp.data.dispatch( 'core/editor' );
            const editorSelect = wp.data.select( 'core/editor' );
            const locationFields = [ 'address', 'city', 'state', 'zip', 'country', 'lat', 'lng' ];
            const originalSavePost = wpEditor.savePost;
                
            // Override the savePost method to intercept save actions
            wpEditor.savePost = function() {

                const saveContext = this;
                const saveArgs = arguments;

                // For a brand-new (auto-draft) location, WordPress promotes the
                // autosave into a regular draft save, so isAutosavingPost() alone
                // misses it — check the passed options too, or validation flashes
                // a required-fields error on that silent background save.
                const saveOptions = ( saveArgs && saveArgs[0] ) || {};
                const isAutosaving = saveOptions.isAutosave === true || editorSelect.isAutosavingPost();

                if ( isAutosaving ) {
                    return originalSavePost.apply( saveContext, saveArgs );
                }

                // trashPost() calls savePost() again just to reset the editor's
                // dirty state. Letting validation run there shows a stray error
                // notice, and letting the save proceed re-submits the meta box
                // form on an already-trashed post. Bail out instead.
                const isTrashing = editorSelect.isDeletingPost() ||
                    editorSelect.getEditedPostAttribute( 'status' ) === 'trash';

                if ( isTrashing ) {
                    wp.data.dispatch( 'core/notices' ).removeNotice( 'wpslupdate' );
                    return false;
                }
                
                const ajaxData = {
                    action: 'wpsl_validate_save_post',
                    wpsl_validate_nonce: jQuery( '#wpsl_validate_nonce' ).val(),
                    location: {
                        id: wp.data.select( 'core/editor' ).getCurrentPostId()
                    }
                };

                for ( let i = 0; i < locationFields.length; i++ ) {
                    const fieldVal = jQuery( '#wpsl-' + locationFields[i] ).val();
                    ajaxData.location[ locationFields[i] ] = fieldVal ? fieldVal.trim() : '';
                }

                if ( ! editor.checkRequiredFields() ) {
                    return false;
                }

                wp.data.dispatch( 'core/notices' ).removeNotice( 'wpslupdate' );

                // If the location isn't a physical store, then don't continue
                if ( editor.getOnlineOnlyStatus() ) {
                    return originalSavePost.apply( saveContext, saveArgs );
                }

                jQuery.get( wpslSettings.ajaxurl, ajaxData, function( response ) {
                    const noticeOptions = {
                        id: 'wpslupdate', // prevent duplicates
                        isDismissible: true
                    };

                    // Refresh the validation nonce so subsequent saves keep working
                    // on long-lived sessions where the original nonce may have expired.
                    if ( response && typeof response.validate_nonce === 'string' ) {
                        jQuery( '#wpsl_validate_nonce' ).val( response.validate_nonce );
                    }

                    if ( response.success ) {
                        // Writing the geocoded coordinates into these hidden fields
                        // is also what prevents a second geocode request: the
                        // following save_post sees valid lat/lng and skips it.
                        if ( typeof response.latlng === 'object' ) {
                            jQuery( '#wpsl-lat' ).val( response.latlng.lat );
                            jQuery( '#wpsl-lng' ).val( response.latlng.lng );

                            // Make sure the marker is placed in the correct location
                            markers.getActive().removeAll();
                            
                            helpers.map.setEditorViewport();
                            helpers.map.maybeShowMarkerDragDescription();
                        }

                        // Set the returned country name and two letter iso code.
                        if ( typeof response.country_iso === 'string' ) {
                            jQuery( '#wpsl-country_iso' ).val( response.country_iso );
                        }

                        if ( typeof response.country === 'string' ) {
                            jQuery( '#wpsl-country' ).val( response.country );
                        }

                        // The postcode is only filled in when the user left the
                        // field empty, since it's part of the address they typed.
                        if ( typeof response.zip === 'string' ) {
                            helpers.dom.fillEmptyField( '#wpsl-zip', response.zip );
                        }

                        // Usable coordinates, but not for an exact address ( e.g. a
                        // country or place ). Save anyway, but warn the user.
                        if ( typeof response.warning === 'string' && response.warning.length ) {
                            wp.data.dispatch( 'core/notices' ).createNotice(
                                'warning',
                                editor.convert( response.warning ),
                                noticeOptions
                            );
                        }

                        return originalSavePost.apply( saveContext, saveArgs );
                    } else if ( ! response.success && typeof response.message === 'undefined' ) {
                        // wp_send_json_error() nests the message under response.data.
                        // A failed nonce check returns none, hence the fallback to
                        // the generic security message.
                        if ( response.data && typeof response.data.message === 'string' ) {
                            response.message = response.data.message;
                        } else {
                            response.message = wpslL10n.securityFail;
                        }
                    }

                    // A ready-made HTML notice (Google, Mapbox or OSM geocode errors)
                    // already matches the settings page structure, so render it as-is.
                    if ( typeof response.notice_html === 'string' && response.notice_html.length ) {
                        noticeOptions.__unstableHTML = true;

                        wp.data.dispatch( 'core/notices' ).createNotice(
                            'error',
                            editor.convert( response.notice_html ),
                            noticeOptions
                        );

                        jQuery( '.interface-navigable-region.interface-interface-skeleton__content' ).scrollTop( 0 );

                        return;
                    }

                    if ( typeof response.message !== 'undefined' ) {
                        // Check if there's an url to include in the notice.
                        if ( typeof response.url !== 'undefined' && typeof response.label !== 'undefined' ) {
                            noticeOptions.actions = [{
                                url: response.url,
                                label: response.label + '.',
                            }];
                        }

                        // Google error messages can contain a plain text url.
                        // Move it into a clickable notice action.
                        if ( response.message.indexOf( 'http' ) !== -1 ) {
                            const match = response.message.match( /(https?:\/\/[^ ]*)/ );
                            if ( match ) {
                                response.message = response.message.replace( match[1], '' ).trim();

                                // The label is a raw URL, so no trailing period —
                                // it would look like part of the address.
                                noticeOptions.actions = [{
                                    url: match[1],
                                    label: match[1],
                                }];
                            }
                        }

                        let noticeContent = editor.convert( response.message );

                        // A rejected Google Maps or Mapbox geocode request also
                        // returns the raw API response and a documentation link.
                        if ( typeof response.details === 'string' && response.details.length ) {
                            // Show the documentation link inline instead of as a
                            // notice action button at the bottom.
                            if ( typeof response.url !== 'undefined' && typeof response.label !== 'undefined' ) {
                                // Use the already HTML-escaped values as-is ( no entity
                                // decoding ) so the raw HTML output can't be injected into.
                                noticeContent = '<p>' + response.message + '</p>'
                                    + '<p><a class="wpsl-notice-link" href="' + encodeURI( response.url ) + '" target="_blank" rel="noopener">' + response.label + '.</a></p>';

                                noticeOptions.__unstableHTML = true;
                                delete noticeOptions.actions;
                            }
                        }

                        wp.data.dispatch( 'core/notices' ).createNotice(
                            'error',
                            noticeContent,
                            noticeOptions
                        );

                        // Scroll to top to make sure the error notice is visible to the user.
                        jQuery( '.interface-navigable-region.interface-interface-skeleton__content' ).scrollTop( 0 );
                    }
                });
            };
        } else {
            jQuery( '#publish' ).on( 'click', function() {
                return editor.checkRequiredFields();
            });
        }
    },

    /**
     * Decode HTML entities in a string.
     *
     * @since   3.0.0
     * @see     https://stackoverflow.com/a/43011296/1065294
     * @param   {string} string The string containing HTML entities
     * @returns {string} The decoded string
     */
    convert: function( string ) {
        const named = { quot: '"', amp: '&', lt: '<', gt: '>', apos: "'", nbsp: ' ' };

        return string
            // Numeric entities, e.g. &#039; or &#x2F;
            .replace( /&#(?:x([\da-f]+)|(\d+));/ig, function ( _, hex, dec ) {
                return String.fromCharCode( dec || +( '0x' + hex ) );
            })
            // Common named entities, e.g. &quot; that esc_html() produces.
            .replace( /&(quot|amp|lt|gt|apos|nbsp);/g, function ( _, name ) {
                return named[ name ];
            });
    },

    /**
     * Bind handlers used on the WPSL location editor page.
     * 
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers: function() {
        const $closedOptions = jQuery( '.wpsl-temporarily-closed-options' );
        const $permanentlyClosedOptions = jQuery( '.wpsl-permanently-closed-options' );
        const startDate = new Date();

        // The shared converter only runs on the settings form, so the
        // exclude-from-results checkbox needs its toggle slider created here.
        wpslSharedFuncs.createToggleSliders( jQuery( '#wpsl-exclude-closed' ) );

        // Make the first available date tomorrow.
        startDate.setDate( startDate.getDate() + 1 );

        jQuery( '#wpsl-online' ).on( 'change', function() {
            editor.toggleOnlineOnly( jQuery( this ).is( ':checked' ) );
        });

        editor.toggleOnlineOnly( jQuery( '#wpsl-online' ).is( ':checked' ) );

        jQuery( '#wpsl-current-location-status' ).on( 'change', function() {
            if ( jQuery( this ).val() === 'temporarily_closed' ) {
                $closedOptions.removeClass( 'wpsl-hide' );
            } else {
                $closedOptions.addClass( 'wpsl-hide' );
                jQuery( '#wpsl-reopen-datepicker' ).val( '' );
            }

            if ( jQuery( this ).val() === 'permanently_closed' ) {
                $permanentlyClosedOptions.removeClass( 'wpsl-hide' );
            } else {
                $permanentlyClosedOptions.addClass( 'wpsl-hide' );
                // trigger change so the toggle slider syncs its aria-checked state.
                jQuery( '#wpsl-exclude-closed' ).prop( 'checked', false ).trigger( 'change' );
            }
        });

        jQuery( '#wpsl-reopen-datepicker' ).datepicker({
            dateFormat: 'yy-mm-dd',
            nextText: '',
            prevText: '',
            minDate: startDate,
            beforeShow: function( input, inst ) {
                jQuery( '#ui-datepicker-div' ).removeClass( 'ui-datepicker' ).addClass( 'wpsl-datepicker-styling' );
            },
            onChangeMonthYear: function() {
                // Reposition the datepicker above the input after the month/year changes,
                // since months with more/fewer week rows change the datepicker height.
                setTimeout( function() {
                    const $dp = jQuery( '#ui-datepicker-div' ),
                        $input = jQuery( '#wpsl-reopen-datepicker' ),
                        inputOffset = $input[0].getBoundingClientRect(),
                        dpHeight = $dp.outerHeight();

                    $dp.css( 'top', ( inputOffset.top - dpHeight - 4 ) + 'px' );
                }, 0 );
            }
        });

        const $tabs = jQuery( '.wpsl-tabs label' );

        // Arrow key navigation
        $tabs.on( 'keydown', function ( e ) {
            const key = e.which;
            const $current = jQuery( this );

            let index = $tabs.index( $current );

            if ( key === 37 || key === 38 ) { // Left or Up
                index = ( index - 1 + $tabs.length ) % $tabs.length;
                e.preventDefault();
            } else if ( key === 39 || key === 40 ) { // Right or Down
                index = ( index + 1 ) % $tabs.length;
                e.preventDefault();
                
            } else if ( key === 9 && ! e.shiftKey ) { // Tab key (forward)
                // Get the current tab's content panel
                const forAttr = $current.attr( 'for' );
                const tabContentId = 'wpsl-' + forAttr.replace( 'wpsl-', '' ).replace( '-tab', '' ) + '-content';
                const $tabContent = jQuery( '#' + tabContentId );

                const $firstFocusable = $tabContent.find( FOCUSABLE_SELECTOR ).first();

                if ( $firstFocusable.length ) {
                    e.preventDefault();

                    $firstFocusable.trigger( 'focus' );
                }

                return;
            } else {
                return;
            }

            const $next = $tabs.eq( index );
            const forAttr = $next.attr( 'for' );

            jQuery( '#' + forAttr ).prop( 'checked', true );

            $next.trigger( 'focus' );
        });

        // Auto-check radio when focused via Tab
        $tabs.on( 'focus', function () {
            const forAttr = jQuery( this ).attr( 'for' );
            jQuery( '#' + forAttr ).prop( 'checked', true );
        });
        
        // Make tab panels focusable and handle their keyboard events
        jQuery( '.wpsl-tab-content' ).each( function() {
            jQuery( this ).attr( 'tabindex', '0' );
            
            // When the panel receives focus via Tab, find and focus its first focusable element
            jQuery( this ).on( 'focus', function( e ) {
                const $firstFocusable = jQuery( this ).find( FOCUSABLE_SELECTOR ).first();
                
                if ( $firstFocusable.length ) {
                    e.preventDefault();

                    $firstFocusable.trigger( 'focus' );
                }
            });
        });
        
        // Shift+Tab on a panel's first element returns to the tab header.
        jQuery( '.wpsl-tab-content' ).each( function() {
            const $tabContent = jQuery( this );
            const tabId = $tabContent.attr( 'id' );
            const tabName = tabId.replace( 'wpsl-', '' ).replace( '-content', '' );
            const $tabLabel = jQuery( 'label[for="wpsl-' + tabName + '-tab"]' );

            const $firstFocusable = $tabContent.find( FOCUSABLE_SELECTOR ).first();

            if ( $firstFocusable.length ) {
                $firstFocusable.on( 'keydown', function( e ) {

                    if ( e.which === 9 && e.shiftKey ) {
                        e.preventDefault();
                        $tabLabel.trigger( 'focus' );
                    }
                });
            }
        });
        
        // Handle Tab key on document level to ensure proper focus to active tab content
        jQuery( document ).on( 'keydown', function( e ) {
            if ( e.which === 9 && ! e.shiftKey ) {
                const $activeTabInput = jQuery( '#wpsl-meta-nav input[type="radio"]:checked' );

                if ( $activeTabInput.length && jQuery( document.activeElement ).closest( '.wpsl-tabs' ).length ) {
                    const activeTabId = $activeTabInput.attr( 'id' );
                    const activeTabContentId = 'wpsl-' + activeTabId.replace( 'wpsl-', '' ).replace( '-tab', '' ) + '-content';
                    const $activeTabContent = jQuery( '#' + activeTabContentId );

                    const $firstFocusable = $activeTabContent.find( FOCUSABLE_SELECTOR ).first();

                    if ( $firstFocusable.length ) {
                        e.preventDefault();
                        $firstFocusable.trigger( 'focus' );
                    }
                }
            }
        });

        jQuery( '.wpsl-required' ).on( 'blur', function() {
            const $field = jQuery( this );
            const value = $field.val().trim();
            
            if ( ! value ) {
                $field.addClass( 'wpsl-error' );
            } else {
                $field.removeClass('wpsl-error');
            }
        });
    },

    /**
     * Check if all the required fields contain any data
     *
     * @version 3.0.0
     * @returns {boolean} false if one of the required fields are empty.
     */
    checkRequiredFields: function() {
        let firstErrorElem, currentTabClass, errorMsg, missingData = false;
        
        if ( state.blockEditor ) {
            errorMsg = wpslL10n.requiredFields;
        }

        // If the location isn't a physical store, then skip the required field check
        if ( editor.getOnlineOnlyStatus() ) {
            return true;
        }

        const $requiredFields = jQuery( '.wpsl-required' );
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        for ( let i = 0; i < $requiredFields.length; i++ ) {
            const $this = jQuery( $requiredFields[i] );
            const value = $this.val() ? $this.val().trim() : '';
            const fieldType = $this.attr( 'type' );
            let fieldError = false;

            if ( value === '' ) {
                fieldError = true;
            } else if ( fieldType === 'email' && ! emailRegex.test( value ) ) {
                fieldError = true;
                if ( state.blockEditor ) {
                    errorMsg = wpslL10n.invalidEmail || wpslL10n.requiredFields;
                }
            }

            if ( fieldError ) {
                $this.addClass( 'wpsl-error' );

                if ( ! firstErrorElem ) {
                    const $thisTabContent = $this.closest( '.wpsl-tab-content' );
                    const thisTabId = $thisTabContent.attr( 'id' );

                    if ( thisTabId ) {
                        firstErrorElem = editor.getFirstErrorElemAttr( $this );
                    }
                }

                missingData = true;
            }
        }

        // If one of the required fields is empty, then show the
        // error msg and make sure the correct tab is visible.
        if ( missingData ) {
            if ( state.blockEditor ) {
                wp.data.dispatch( 'core/notices' ).createNotice(
                    'error',
                    errorMsg, {
                        id: 'wpslupdate',
                        isDismissible: true
                    });
            }
            
            if ( typeof firstErrorElem !== 'undefined' && firstErrorElem ) {
                let selector;
                if ( firstErrorElem.type === 'id' ) {
                    selector = '#' + firstErrorElem.val + '.wpsl-error';
                } else {
                    selector = '.' + firstErrorElem.val.replace( /wpsl-required|wpsl-error/g, '' ).trim() + '.wpsl-error';
                }
                
                const $element = jQuery( selector );
                if ( $element.length ) {
                    const $tabContent = $element.closest( '.wpsl-tab-content' );
                    if ( $tabContent.length ) {
                        const tabId = $tabContent.attr( 'id' );
                        if ( tabId ) {
                            // Convert "wpsl-additional-information-content" to "additional-information"
                            currentTabClass = tabId.replace( 'wpsl-', '' ).replace( '-content', '' );
                            jQuery( 'html, body' ).scrollTop( Math.round( $element.offset().top - 100 ) );
                            
                            jQuery( '#wpsl-' + currentTabClass + '-tab' ).prop( 'checked', true );
                        }
                    }
                }
            }

            if ( currentTabClass ) {
                editor.activateStoreTab( currentTabClass );
            } else {
                editor.activateStoreTab( 'first' );
            }

            return false;
        } else {
            return true;
        }
    },

    /**
     * Get the id or class of the first required field that's empty.
     *
     * Used to activate the tab the first error occured on.
     *
     * @param   {object} elem			The element the error occured on
     * @returns {object} firstErrorElem The id/class set on the first elem that an error occured on and the attr value
     */
    getFirstErrorElemAttr: function( elem ) {
        let firstErrorElem = {'type': 'id', 'val': elem.attr( 'id' )};
        if ( typeof firstErrorElem.val === 'undefined' ) {
            firstErrorElem = { 'type': 'class', 'val' : elem.attr( 'class' ) };
        }

        return firstErrorElem;
    },

    /**
     * Grab the coordinates for the provided address
     * by making a request to the Geocode API.
     *
     * @returns {void}
     */
    bindLookUpLocation: function() {
        jQuery( '#wpsl-lookup-location' ).on( 'click', function( e ) {
            if ( editor.validatePreviewFields() ) {
                const requestParams = api[ state.mapService ].createParams();
                api[ state.mapService ].codeAddress( requestParams );
            } else {
                editor.activateStoreTab( 'first' );
                alert( wpslL10n.missingGeoData );

                return true;
            }

            e.preventDefault();
        });
    },

    /**
     * Set the correct tab to visible, and hide all other metaboxes
     *
     * @param   {string} $target The name of the tab to show
     * @returns {void}
     */
    activateStoreTab: function( $target ) {
        $target = ( $target === 'first' ) ? ':first-child' : '.' + $target;
            
        const $tabElement = jQuery( '#wpsl-meta-nav li' + $target + '-tab' );
        const $contentElement = jQuery( '.wpsl-store-meta > div' + $target );
        
        if ( ! $tabElement.hasClass( 'wpsl-active' ) ) {
            $tabElement.addClass( 'wpsl-active' ).siblings().removeClass( 'wpsl-active' );

            $contentElement.show().addClass( 'wpsl-active' ).siblings( 'div' ).hide().removeClass( 'wpsl-active' );
        }
    },

    /**
     * Make sure the locationfields that are required
     * for the map preview to work contain data.
     *
     * @since   3.0.0
     * @returns {boolean} error
     */
    validatePreviewFields: function() {
        let fieldData, validated = true;
        
        jQuery( '.wpsl-store-meta input' ).removeClass( 'wpsl-error' );
        
        if ( typeof wpslSettings.requiredFields !== 'undefined' && Array.isArray( wpslSettings.requiredFields ) ) {
            const requiredFields = wpslSettings.requiredFields;

            for ( let i = 0; i < requiredFields.length; i++ ) {
                const $requiredField = jQuery( '#wpsl-' + requiredFields[i] );

                // Skip fields that aren't present on the page.
                if ( ! $requiredField.length ) {
                    continue;
                }

                fieldData = $requiredField.val() ? $requiredField.val().trim() : '';
                
                if ( ! fieldData ) {
                    jQuery( '#wpsl-' + requiredFields[i] ).addClass( 'wpsl-error' );

                    validated = false;
                }

                fieldData = '';
            }
        }
        
        return validated;
    },
    
    /**
     * Populate the different location fields with the
     * passed ( autocomplete ) data.
     *
     * @since   3.0.0
     * @param   {object} locationDetails The address details
     * @returns {void}
     */
    setLocationFields: function( locationDetails ) {
        jQuery( '#wpsl-store-details .wpsl-location input' ).val( '' );

        for ( const index in locationDetails ) {
            if ( locationDetails.hasOwnProperty( index ) && typeof index === 'string' ) {
                const $field = jQuery( '#wpsl-store-details #wpsl-' + index );

                if ( $field.length ) {
                    $field.val( locationDetails[index] );
                }
            }
        }
    }
};