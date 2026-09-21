/**
 * Small standalone admin behaviour for the Home page: the shortcode copy
 * buttons, the feedback popup, and the "Choose a map service" step of the
 * setup checklist.
 *
 * @since 3.0.0
 */
( function( $ ) {
    'use strict';

    /*
     * Wire each copy button to the field beside it. By class, not id --
     * the shortcode appears more than once on the page.
     */
    $( '.wpsl-shortcode-copy' ).each( function() {
        const $button = $( this );
        const $field  = $button.parent().find( '.wpsl-shortcode-field' ).first();

        if ( ! $field.length ) {
            return;
        }

        const original = $button.text();

        let restore;

        function confirmed() {
            $button.text( $button.attr( 'data-copied' ) || original );

            window.clearTimeout( restore );

            restore = window.setTimeout( function() {
                $button.text( original );
            }, 2000 );
        }

        $button.on( 'click', function() {
            $field.trigger( 'focus' ).trigger( 'select' );

            /*
             * The clipboard API needs a secure context, which plenty of local
             * and intranet installs are not, so the old execCommand path stays
             * as the fallback rather than the button silently doing nothing.
             */
            if ( navigator.clipboard && window.isSecureContext ) {
                navigator.clipboard.writeText( $field.val() ).then( confirmed, function() {
                    document.execCommand( 'copy' );
                    confirmed();
                } );

                return;
            }

            document.execCommand( 'copy' );
            confirmed();
        } );
    } );

    const config = window.wpslHome || null;

    if ( ! config ) {
        return;
    }

    /**
     * Print one notice, in the settings page's format, into a container.
     *
     * @param {jQuery}        $container Where the notice goes
     * @param {string}        type       success, warning, error or info
     * @param {string}        msg        The headline
     * @param {string|jQuery} details    The body, optional
     */
    function addNoticeTo( $container, type, msg, details ) {
        const $notice = $( '<div>' )
            .addClass( 'notice notice-' + type + ' inline' )
            .append( $( '<p>' ).append( $( '<strong>' ).text( msg ) ) );

        if ( details ) {
            $notice.append( 'string' === typeof details ? $.parseHTML( details ) : details );
        }

        $container.append( $notice );
    }

    /**
     * POST to admin-ajax and parse the JSON reply. A refusal comes back as
     * an error status plus a JSON body, so a responseJSON body resolves the
     * promise; only a body-less failure rejects.
     *
     * @param  {string} action The wp_ajax action
     * @param  {Object} fields Extra fields; may override the nonce
     * @return {jQuery.Promise<Object>}
     */
    function post( action, fields ) {
        const data = $.extend( { action: action, nonce: config.nonce }, fields );

        return $.post( config.ajaxUrl, data, null, 'json' ).then( null, function( jqXHR ) {
            return jqXHR.responseJSON ? jqXHR.responseJSON : $.Deferred().reject( jqXHR );
        } );
    }

    /**
     * The settings page's toggle sliders, from wpsl-shared-funcs.
     *
     * @param {jQuery} $checkboxes
     */
    function createToggleSliders( $checkboxes ) {
        if ( window.wpslSharedFuncs && typeof window.wpslSharedFuncs.createToggleSliders === 'function' ) {
            window.wpslSharedFuncs.createToggleSliders( $checkboxes );
        }
    }

    /**
     * The settings page's tooltips, from wpsl-shared-funcs.
     *
     * @param {jQuery} $info
     */
    function bindInfoPopup( $info ) {
        if ( window.wpslSharedFuncs && typeof window.wpslSharedFuncs.bindInfoPopup === 'function' ) {
            window.wpslSharedFuncs.bindInfoPopup( $info );
        }
    }

    /*
     * Dismissing an alert.
     */
    ( function wireAlerts() {
        const $box = $( '#wpsl-home-alerts' );

        if ( ! $box.length || ! config.dismissNonce ) {
            return;
        }

        const $list  = $box.find( '.wpsl-alerts-list' );
        const $empty = $box.find( '.wpsl-no-alerts' );

        $list.on( 'click', '.wpsl-dismiss-alert', function( event ) {
            event.preventDefault();

            const $button = $( this );
            const $item   = $button.closest( 'li' );
            const key     = $button.attr( 'data-plugin' );

            if ( ! $item.length || ! key ) {
                return;
            }

            // Stops a second click while the first is still in flight.
            $button.hide();

            post( 'wpsl_dismiss_alert', { nonce: config.dismissNonce, plugin: key } )
                .then( function( response ) {
                    if ( ! response || ! response.success ) {
                        $button.show();
                        window.alert( ( response && response.data && response.data.message ) || config.i18n.requestFailed );

                        return;
                    }

                    $item.remove();

                    if ( ! $list.find( 'li' ).length ) {
                        $list.hide();
                        $empty.show();
                    }

                    updateAdminBarBadge( response.data && response.data.count );
                } )
                .catch( function() {
                    $button.show();
                    window.alert( config.i18n.requestFailed );
                } );
        } );
    }() );

    /**
     * Keep the admin bar badge in step with the alerts left.
     *
     * The badge is the only other place a count is shown, now that the
     * settings navigation no longer carries one.
     *
     * @param  {number} count Alerts remaining
     * @return {void}
     */
    function updateAdminBarBadge( count ) {
        const $badge = $( '#wp-admin-bar-wpsl-menu .wpsl-menu-notification-counter' );
        const $dot   = $( '#wp-admin-bar-wpsl-notifications .wpsl-menu-notification-indicator' );

        if ( count > 0 ) {
            $badge.text( count );

            return;
        }

        $badge.remove();
        $dot.remove();
    }

    /*
     * The feedback popup.
     *
     * Runs whether or not the checklist is on the page: the Help box that
     * opens it is always there.
     */
    ( function wireFeedback() {
        const $open  = $( '#wpsl-home-feedback-open' );
        const $modal = $( '#wpsl-home-feedback' );
        const $form  = $( '#wpsl-home-feedback-form' );

        if ( ! $open.length || ! $modal.length || ! $form.length ) {
            return;
        }

        const $type        = $( '#wpsl-home-feedback-type' );
        const $message     = $( '#wpsl-home-feedback-message' );
        const $reportRow   = $( '#wpsl-home-feedback-report-row' );
        const $report      = $( '#wpsl-home-feedback-report' );
        const $emailToggle = $( '#wpsl-home-feedback-email-toggle' );
        const $emailRow    = $( '#wpsl-home-feedback-email-row' );
        const $emailInput  = $( '#wpsl-home-feedback-email' );
        const $result      = $form.find( '.wpsl-home-feedback-result' );
        const $submit      = $form.find( 'button[type="submit"]' );
        const $spinner     = $form.find( '.wpsl-preloader' );

        // The kind of feedback decides the prompt, and whether site info is offered.
        function syncType() {
            const chosen = $type.val();

            $reportRow.prop( 'hidden', 'bug' !== chosen );
            $message.attr( 'placeholder', config.i18n.feedbackPrompt[ chosen ] || config.i18n.feedbackPrompt.general );
        }

        /*
         * A hidden field still takes part in validation, and the browser
         * cannot focus it to say what is wrong, so disable it as well.
         */
        function syncEmail() {
            const wanted = $emailToggle.prop( 'checked' );

            $emailRow.prop( 'hidden', ! wanted );
            $emailInput.prop( 'disabled', ! wanted );
        }

        function notice( type, text, details ) {
            $result.empty();
            addNoticeTo( $result, type, text, details );
        }

        function busy( on ) {
            $submit.prop( 'disabled', on );
            $spinner.prop( 'hidden', ! on );
        }

        /*
         * Open and close the popup.
         */
        function openModal() {
            $form.removeClass( 'wpsl-home-feedback-sent' );
            $result.empty();

            if ( window.MicroModal ) {
                window.MicroModal.show( 'wpsl-home-feedback', { disableScroll: true, disableFocus: false } );

                return;
            }

            $modal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
        }

        function closeModal() {
            if ( window.MicroModal ) {
                window.MicroModal.close( 'wpsl-home-feedback' );

                return;
            }

            $modal.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
        }

        $open.on( 'click', openModal );

        // MicroModal binds these itself; without it they would do nothing.
        $modal.on( 'click', '[data-micromodal-close]', function() {
            if ( ! window.MicroModal ) {
                closeModal();
            }
        } );

        $( document ).on( 'keydown', function( event ) {
            if ( ! window.MicroModal && 'Escape' === event.key && $modal.hasClass( 'is-open' ) ) {
                closeModal();
            }
        } );

        $type.on( 'change', syncType );
        $emailToggle.on( 'change', syncEmail );

        createToggleSliders( $modal.find( 'input[type="checkbox"]' ) );
        bindInfoPopup( $modal.find( '.wpsl-info' ) );

        $form.on( 'submit', function( event ) {
            event.preventDefault();

            const chosen = $type.val();
            if ( ! chosen ) {
                notice( 'error', config.i18n.feedbackType );

                return;
            }

            if ( ! $message.val().trim() ) {
                notice( 'error', config.i18n.feedbackEmpty );
                $message.trigger( 'focus' );

                return;
            }

            $result.empty();
            busy( true );

            post( config.actions.feedback, {
                nonce:          config.feedbackNonce,
                type:           chosen,
                message:        $message.val(),
                email:          $emailToggle.prop( 'checked' ) ? $emailInput.val() : '',
                include_report: ( 'bug' === chosen && $report.prop( 'checked' ) ) ? '1' : ''
            } ).then( function( response ) {
                busy( false );

                if ( response && response.success ) {
                    $form[ 0 ].reset();
                    syncType();
                    syncEmail();
                    $form.addClass( 'wpsl-home-feedback-sent' );
                    notice( 'success', response.data.message );

                    return;
                }

                const data = ( response && response.data ) || {};

                notice( 'error', data.message || config.i18n.requestFailed, data.details );
            } ).catch( function() {
                busy( false );
                notice( 'error', config.i18n.requestFailed );
            } );
        } );

        syncType();
        syncEmail();
    }() );

    /*
     * Checklist elements both steps redraw, looked up once here.
     */
    const $firstStep = $( '#wpsl-home-first-location' );
    const $warning   = $( '#wpsl-home-first-location-warning' );
    const $progress  = $( '#wpsl-home-progress' );
    const $setup     = $( '#wpsl-home-setup' );

    /**
     * Tick one step of the checklist, or put its number back.
     *
     * @param {string}  step The step's id
     * @param {boolean} done
     */
    function markStep( step, done ) {
        $( '.wpsl-home-check li' )
            .filter( function() {
                return $( this ).attr( 'data-step' ) === step;
            } )
            .toggleClass( 'wpsl-home-done', !! done )
            .toggleClass( 'wpsl-home-todo', ! done );
    }

    /**
     * Redraw the checklist from the state the server reports.
     *
     * @param {Object} state { items: [ { id, done, url, blocked } ], done, total, show }
     */
    function applyState( state ) {
        if ( ! state ) {
            return;
        }

        $.each( state.items, function( index, item ) {
            markStep( item.id, item.done );

            if ( 'first_location' === item.id ) {
                $firstStep
                    .attr( 'data-blocked', item.blocked || '' )
                    .attr( 'href', item.blocked ? '#wpsl-home-map-service' : item.url );

                $warning.prop( 'hidden', ! item.blocked );
                $warning.find( '.wpsl-info-text' ).text( item.blocked || '' );
            }
        } );

        if ( ! $progress.length ) {
            return;
        }

        const $label  = $progress.find( '.wpsl-progress-label' );
        const percent = state.total ? Math.round( ( state.done / state.total ) * 100 ) : 0;

        $progress.find( '.wpsl-progress-fill' ).css( 'width', percent + '%' );

        // Nothing done yet reads as a telling-off, so the count and bar wait for the first tick.
        $progress.toggleClass( 'wpsl-home-no-progress', ! state.done );

        if ( ! $label.length ) {
            return;
        }

        if ( state.show ) {
            $label.text( config.i18n.tasksDone.replace( '%1$d', state.done ).replace( '%2$d', state.total ) );

            return;
        }

        /*
         * Every box is ticked. The server has already put the checklist
         * away for good; let the page catch up after a beat so the last
         * tick is seen landing.
         */
        $label.text( config.i18n.setupComplete );

        // Nothing left to finish, so the line telling them to finish it goes.
        $progress.find( '.wpsl-home-progress-note' ).prop( 'hidden', true );

        const $checklist = $progress.add( $setup );

        window.setTimeout( function() {
            $checklist.addClass( 'wpsl-home-setup-hide' );

            window.setTimeout( function() {
                $checklist.prop( 'hidden', true );
            }, 450 );
        }, 1800 );
    }

    /*
     * The page step: create the draft page without leaving the screen, 
     * and the checkbox that ticks the step off by hand.
     *
     * Its own block, because the page step is printed whether or not the
     * map service step is.
     */
    ( function wirePageStep() {
        const $pageForm = $( '#wpsl-home-page-form' );
        const $manual   = $( '#wpsl-home-placed-manually' );

        if ( $pageForm.length ) {
            const $titleField  = $( '#wpsl-new-page-title' );
            const $titleRow    = $pageForm.find( '.wpsl-home-page-title-field' );
            const $nonceField  = $pageForm.find( '[name="wpsl_create_page_nonce"]' );
            const $pageResult  = $pageForm.find( '.wpsl-home-page-result' );
            const $pageButton  = $pageForm.find( 'button[type="submit"]' );
            const $pageSpinner = $pageForm.find( '.wpsl-preloader' );

            /*
             * The preloader stands where the button was rather than beside
             * it, so the row does not grow, and there is only ever one thing
             * to look at. A failure puts the button back; success does not,
             * because the page it would make now exists.
             */
            const pageBusy = function( on ) {
                $pageButton.prop( 'hidden', on );
                $pageSpinner.prop( 'hidden', ! on );
            };

            $pageForm.on( 'submit', function( event ) {
                event.preventDefault();

                $pageResult.empty();
                pageBusy( true );

                post( config.actions.createPage, {
                    nonce: $nonceField.val() || '',
                    title: $titleField.val() || ''
                } ).then( function( response ) {
                    if ( ! response || ! response.success ) {
                        pageBusy( false );
                        addNoticeTo( $pageResult, 'error', ( response && response.data && response.data.message ) || config.i18n.requestFailed );

                        return;
                    }

                    /*
                     * Done: the field and its button go, and the notice below
                     * takes their place. Nothing left to type here.
                     */
                    $pageSpinner.prop( 'hidden', true );
                    $titleRow.prop( 'hidden', true );

                    const $link = $( '<a>' )
                        .attr( 'href', response.data.editUrl )
                        .text( config.i18n.editPage + ' “' + response.data.title + '”' );

                    const $line = $( '<p>' ).append(
                        $( '<strong>' ).text( response.data.existing ? config.i18n.pageExists : config.i18n.pageCreated ),
                        ' ',
                        $link
                    );

                    $( '<div>' )
                        .addClass( 'notice notice-' + ( response.data.existing ? 'info' : 'success' ) + ' inline' )
                        .append( $line )
                        .appendTo( $pageResult );

                    applyState( response.data.state );
                } ).catch( function() {
                    pageBusy( false );
                    addNoticeTo( $pageResult, 'error', config.i18n.requestFailed );
                } );
            } );
        }

        if ( $manual.length ) {
            /*
             * Tick the page step off, or back on.
             */
            $manual.on( 'change', function() {
                const placed = $manual.prop( 'checked' );

                markStep( 'placed_on_page', placed );

                post( config.actions.markPlaced, { placed: placed ? '1' : '' } ).then( function( response ) {
                    if ( response && response.success ) {
                        applyState( response.data.state );
                    }
                } );
            } );

            createToggleSliders( $manual );
            bindInfoPopup( $( '.wpsl-home-page-manual .wpsl-info' ) );
        }
    }() );

    /*
     * The map service step.
     */
    const $form = $( '#wpsl-home-service-form' );

    if ( ! $form.length ) {
        return;
    }

    const $select  = $( '#wpsl-home-service-select' );
    const $panels  = $form.find( '.wpsl-home-service-keys' );
    const $button  = $form.find( 'button[type="submit"]' );
    const $spinner = $form.find( '.wpsl-preloader' );
    const $result  = $form.find( '.wpsl-home-service-result' );
    const $details = $( '#wpsl-home-map-service' );

    /**
     * The panel holding the current service's key fields.
     *
     * @return {jQuery} Empty when the service has no panel.
     */
    function activePanel() {
        const service = $select.val();

        return $panels.filter( function() {
            return $( this ).attr( 'data-service' ) === service;
        } ).first();
    }

    /**
     * Show the key fields for the chosen service and word the button for it.
     */
    function showPanel() {
        const $panel = activePanel();
        const keyed  = $panel.find( '.wpsl-key-input' ).length > 0;

        $panels.not( $panel ).prop( 'hidden', true );
        $panel.prop( 'hidden', false );

        $button.text( keyed ? config.i18n.saveVerify : config.i18n.save );

        clearNotices();
    }

    function clearNotices() {
        $result.empty();
    }

    function addNotice( type, msg, details ) {
        addNoticeTo( $result, type, msg, details );
    }

    /**
     * Report one key's verdict: a notice, and a red border on a bad key.
     *
     * @param {string} setting The setting name, e.g. mapbox_key
     * @param {Object} status  { valid, msg, details, warning }
     */
    function reportKey( setting, status ) {
        if ( ! status ) {
            return;
        }

        let type = 'error';

        if ( status.valid ) {
            type = status.warning ? 'warning' : 'success';
        }

        addNotice( type, status.msg, status.details );

        $( '#wpsl-home-' + setting.replace( /_/g, '-' ) ).toggleClass( 'wpsl-error', ! status.valid );
    }

    /**
     * @param {boolean} on
     */
    function busy( on ) {
        $button.prop( 'disabled', on );
        $spinner.prop( 'hidden', ! on );
    }

    /**
     * Check a Google browser key the only way it can be checked: load the
     * Maps JavaScript API with it and geocode an address.
     *
     * A second run drops the previous load first, or Google keeps the old
     * key and warns that the API was included twice.
     *
     * @param {string}   key
     * @param {Function} callback ( valid, status )
     */
    function checkBrowserKey( key, callback ) {
        let settled = false;
        let timer;

        function finish( valid, status ) {
            if ( settled ) {
                return;
            }

            settled = true;
            window.clearTimeout( timer );
            callback( valid, status );
        }

        $( 'script[src*="maps.googleapis.com"]' ).remove();

        if ( window.google ) {
            try {
                delete window.google;
            } catch ( e ) {
                window.google = undefined;
            }
        }

        // Google calls this for a key it rejects outright.
        window.gm_authFailure = function() {
            finish( false, 'REQUEST_DENIED' );
        };

        window.wpslHomeGmapsReady = function() {
            try {
                window.google.maps.importLibrary( 'geocoding' ).then( function( lib ) {
                    new lib.Geocoder().geocode( { address: '1600 Amphitheatre Parkway, Mountain View, CA' }, function( results, status ) {
                        finish( 'OK' === status, status );
                    } );
                } ).catch( function( error ) {
                    finish( false, String( error && error.message ? error.message : error ) );
                } );
            } catch ( error ) {
                finish( false, String( error && error.message ? error.message : error ) );
            }
        };

        const script = document.createElement( 'script' );

        script.async = true;
        script.src   = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent( key ) + '&v=quarterly&loading=async&callback=wpslHomeGmapsReady';

        script.onerror = function() {
            finish( false, config.i18n.browserLoad );
        };

        timer = window.setTimeout( function() {
            finish( false, config.i18n.browserSlow );
        }, 15000 );

        document.head.appendChild( script );
    }

    /**
     * Report the browser key's verdict.
     *
     * @param {boolean} valid
     * @param {string}  status
     */
    function finishBrowserKey( valid, status ) {
        reportKey( 'gmaps_browser_key', {
            valid:   valid ? 1 : 0,
            msg:     valid ? config.i18n.browserOk : config.i18n.browserFail,
            details: valid ? '' : $( '<p>' ).text( status )
        } );

        post( 'wpsl_update_validation_status', {
            nonce:    config.validationStatusNonce,
            key_type: 'gmaps_browser',
            status:   valid ? 1 : 0
        } ).then( function() {
            return post( config.actions.status, {} );
        } ).then( function( response ) {
            if ( response && response.success ) {
                applyState( response.data.state );
            }

            busy( false );
        } ).catch( function() {
            addNotice( 'error', config.i18n.requestFailed );
            busy( false );
        } );
    }

    $form.on( 'submit', function( event ) {
        event.preventDefault();

        const fields = { service: $select.val() };

        activePanel().find( '.wpsl-key-input' ).each( function() {
            fields[ this.name ] = $( this ).val().trim();
        } );

        clearNotices();
        busy( true );

        post( config.actions.save, fields ).then( function( response ) {
            if ( ! response || ! response.success ) {
                addNotice( 'error', ( response && response.data && response.data.message ) || config.i18n.requestFailed );
                busy( false );

                return;
            }

            const data = response.data;

            if ( $.isEmptyObject( data.results ) ) {
                addNotice( 'success', config.i18n.saved );
            }

            $.each( data.results, reportKey );

            if ( data.browser_pending ) {
                addNotice( 'info', config.i18n.browserWait );
                checkBrowserKey( fields[ 'keys[gmaps_browser_key]' ], function( valid, status ) {
                    // Replace the waiting notice with the verdict.
                    $result.children( '.notice-info' ).first().remove();

                    finishBrowserKey( valid, status );
                } );

                return;
            }

            applyState( data.state );
            busy( false );
        } ).catch( function() {
            addNotice( 'error', config.i18n.requestFailed );
            busy( false );
        } );
    } );

    $select.on( 'change', showPanel );
    showPanel();

    if ( $warning.length ) {
        bindInfoPopup( $warning );
    }

    if ( $details.length ) {
        $firstStep.on( 'click', function( event ) {
            const blocked = $firstStep.attr( 'data-blocked' );
            if ( ! blocked ) {
                return;
            }

            event.preventDefault();

            $details.prop( 'open', true );
            clearNotices();
            addNotice( 'warning', blocked );
            $select.trigger( 'focus' );
        } );
    }
} )( jQuery );