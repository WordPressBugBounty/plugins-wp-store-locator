/**
 * Handle the exit survey modal and submission.
 *
 * @since 2.2.240
 */
( function( $ ) {
    const surveyOptions = ['not_working', 'too_complicated', 'better_plugin', 'missing_feature', 'temporary_deactivation', 'other'];
    const $surveySupportLinks = $( '.wpsl-survey-support-links' );
    const $exitSurvey = $( '#wpsl-exit-survey' );
    const $competitor = $( '.wpsl-survey-competitor' );
    const $competitorSelect = $( '#wpsl-survey-competitor' );
    const $competitorOther = $( '#wpsl-survey-competitor-other' );
    const $notWorking = $( '.wpsl-survey-not-working' );
    const $complicated = $( '.wpsl-survey-complicated' );
    const $submitButton = $exitSurvey.find( 'footer .button-primary' );

    // How long the answer may take to send before the plugin is deactivated anyway.
    const sendTimeout = 6000;

    let currentUrl;

    $( '#deactivate-wp-store-locator' ).on( 'click', function() {
        currentUrl = $( this ).attr( 'href' );

        MicroModal.show( 'wpsl-exit-survey', {
            onClose: modal => wpslUnbindSurveyListeners()
        });

        $exitSurvey.find( 'input[type=radio], input[type=checkbox]' ).prop( 'checked', false );
        $exitSurvey.find( 'input[type=text], select, textarea' ).val( '' );

        // The email fields start with the logged-in user's address again.
        $exitSurvey.find( '.wpsl-survey-email' ).each( function() {
            $( this ).val( $( this ).data( 'default' ) );
        });

        $( '.wpsl-survey-support-links, .wpsl-exit-survey-suggestions, .wpsl-survey-contact-email' ).hide();
        $notWorking.hide();
        $complicated.hide();
        $competitor.hide();
        $competitorOther.hide();
        $submitButton.prop( 'disabled', false );

        // Set the correct href value for the 'Skip & Deactivate' link.
        $( '#wpsl-skip-survey' ).attr( 'href', currentUrl );

        // The selected reason decides which text input or support link shows.
        $exitSurvey.find( 'input[type=radio]' ).on( 'change', function() {

            $exitSurvey.find( '.wpsl-exit-survey-suggestions' ).hide();
            $surveySupportLinks.hide();
            $notWorking.hide();
            $complicated.hide();
            $competitor.hide();

            switch ( $( this ).val() ) {
                case 'not_working':
                    $notWorking.show();
                    $surveySupportLinks.show();
                    break;
                case 'too_complicated':
                    $complicated.show();
                    break;
                case 'better_plugin':
                    $competitor.show();
                    break;
                case 'missing_feature':
                case 'other':
                    $( this ).siblings( 'input[type=text]' ).show();
                    break;
            }
        });

        // The email field only shows, and is only sent, when the user agrees to be contacted.
        $exitSurvey.find( '.wpsl-survey-contact-check' ).on( 'change', function() {
            $( this ).closest( '.wpsl-survey-contact' ).find( '.wpsl-survey-contact-email' ).toggle( $( this ).is( ':checked' ) );
        });

        // Only ask for the plugin name when it is not in the list.
        $competitorSelect.on( 'change', function() {
            $competitorOther.toggle( $( this ).val() === 'other' );
        });

        // Send the answer, then deactivate. A failed or slow request never blocks the deactivation.
        $submitButton.on( 'click', function() {
            const data = wpslCollectAnswer();

            if ( ! data ) {
                window.location.href = currentUrl;

                return false;
            }

            $submitButton.prop( 'disabled', true );

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                timeout: sendTimeout
            }).always( function() {
                window.location.href = currentUrl;
            });

            return false;
        });

        return false;
    });

    /**
     * Collect the answer for the selected reason.
     *
     * @returns {object|null} The request data, or null when no reason is selected.
     */
    function wpslCollectAnswer() {
        const $selected = $exitSurvey.find( 'input[type=radio]:checked' );
        const reason    = $selected.val();

        if ( $.inArray( reason, surveyOptions ) === -1 ) {
            return null;
        }

        const data = {
            action: 'wpsl_exit_survey',
            nonce: $( '#wpsl-survey-nonce' ).val(),
            reason: reason
        };

        let feedback = '',
            $contact = $();

        if ( reason === 'better_plugin' ) {
            const competitor = $competitorSelect.val();

            feedback = $( '#wpsl-survey-competitor-feedback' ).val();

            if ( competitor ) {
                data.competitor = competitor;

                if ( competitor === 'other' && $competitorOther.val().trim() ) {
                    data.competitor_other = $competitorOther.val().trim();
                }
            }
        } else if ( reason === 'not_working' ) {
            const notWorkingReason = $( '#wpsl-survey-not-working-reason' ).val();

            feedback = $( '#wpsl-survey-not-working-feedback' ).val();
            $contact = $notWorking.find( '.wpsl-survey-contact' );

            if ( notWorkingReason ) {
                data.sub_reason = notWorkingReason;
            }

            // Only the opt-in is sent from here, the details are collected on the server.
            if ( $( '#wpsl-survey-debug' ).is( ':checked' ) ) {
                data.debug = 1;
            }
        } else if ( reason === 'too_complicated' ) {
            feedback = $( '#wpsl-survey-complicated-feedback' ).val();
            $contact = $complicated.find( '.wpsl-survey-contact' );
        } else {
            feedback = $selected.parent( 'li' ).find( 'input[type=text]' ).val();
        }

        if ( feedback && feedback.trim() ) {
            data.feedback = feedback.trim();
        }

        if ( $contact.find( '.wpsl-survey-contact-check' ).is( ':checked' ) ) {
            const email = $contact.find( '.wpsl-survey-email' ).val().trim();

            if ( email ) {
                data.email = email;
            }
        }

        return data;
    }

    /**
     * Unbind the survey listeners when the modal closes.
     */
    function wpslUnbindSurveyListeners() {
        $exitSurvey.find( 'input[type=radio]' ).off();
        $exitSurvey.find( '.wpsl-survey-contact-check' ).off();
        $submitButton.off();
        $competitorSelect.off();
    }

} )( jQuery );
