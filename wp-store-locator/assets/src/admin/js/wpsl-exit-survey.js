/**
 * Handle the exit survey modal and submission.
 *
 * @since 2.2.240
 */
( function( $ ) {
    const surveyOptions = ['not_working', 'better_plugin', 'missing_feature', 'temporary_deactivation', 'other'];
    const $surveySupportLinks = $( '.wpsl-survey-support-links' );
    const $exitSurvey = $( '#wpsl-exit-survey' );
    const $competitor = $( '.wpsl-survey-competitor' );
    const $competitorSelect = $( '#wpsl-survey-competitor' );
    const $competitorOther = $( '#wpsl-survey-competitor-other' );
    const $notWorking = $( '.wpsl-survey-not-working' );

    let params, currentUrl, feedback, selectedReason, competitor, notWorkingReason;

    $( '#deactivate-wp-store-locator' ).on( 'click', function() {
        params = '';
        currentUrl = $( this ).attr( 'href' );

        MicroModal.show( 'wpsl-exit-survey', {
            onClose: modal => wpslUnbindSurveyListeners()
        });

        $exitSurvey.find( 'input[type=radio], input[type=checkbox]' ).prop( 'checked', false );
        $exitSurvey.find( 'input[type=text], select, textarea' ).val( '' );

        $( '.wpsl-survey-support-links, .wpsl-exit-survey-suggestions' ).hide();
        $notWorking.hide();
        $competitor.hide();
        $competitorOther.hide();

        // Set the correct href value for the 'Skip & Deactivate' link.
        $( '#wpsl-skip-survey' ).attr( 'href', currentUrl );

        // The selected reason decides which text input or support link shows.
        $exitSurvey.find( 'input[type=radio]' ).on( 'change', function() {

            $exitSurvey.find( '.wpsl-exit-survey-suggestions' ).hide();
            $surveySupportLinks.hide();
            $notWorking.hide();
            $competitor.hide();

            switch ( $( this ).val() ) {
                case 'not_working':
                    $notWorking.show();
                    $surveySupportLinks.show();
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

        // Only ask for the plugin name when it is not in the list.
        $competitorSelect.on( 'change', function() {
            $competitorOther.toggle( $( this ).val() === 'other' );
        });

        // Deactivate, carrying the reason and feedback in the URL parameters.
        $exitSurvey.find( 'footer .button-primary' ).on( 'click', function() {
            selectedReason = $exitSurvey.find( 'input[type=radio]:checked' );

            if ( $.inArray( selectedReason.val(), surveyOptions ) >= 0 ) {
                params = '&wpsl_deactivation_reason=' + selectedReason.val();

                if ( selectedReason.val() === 'better_plugin' ) {
                    competitor = $competitorSelect.val();
                    feedback   = $( '#wpsl-survey-competitor-feedback' ).val();

                    if ( competitor ) {
                        params = params + '&wpsl_competitor=' + encodeURIComponent( competitor );

                        if ( competitor === 'other' && $competitorOther.val().trim() ) {
                            params = params + '&wpsl_competitor_other=' + encodeURIComponent( $competitorOther.val().trim() );
                        }
                    }
                } else if ( selectedReason.val() === 'not_working' ) {
                    notWorkingReason = $( '#wpsl-survey-not-working-reason' ).val();
                    feedback         = $( '#wpsl-survey-not-working-feedback' ).val();

                    if ( notWorkingReason ) {
                        params = params + '&wpsl_not_working=' + encodeURIComponent( notWorkingReason );
                    }

                    // Only the opt-in is sent from here, the details are collected on the server.
                    if ( $( '#wpsl-survey-debug' ).is( ':checked' ) ) {
                        params = params + '&wpsl_debug=1';
                    }
                } else {
                    feedback = selectedReason.parent( 'li' ).find( 'input[type=text]' ).val();
                }

                if ( feedback ) {
                    params = params + '&wpsl_deactivation_feedback=' + encodeURIComponent( feedback.trim() );
                }
            }

            window.location.href = currentUrl + params + '&wpsl_nonce=' + $( '#wpsl-survey-nonce' ).val();

            return false;
        });

        return false;
    });

    /**
     * Unbind the survey listeners when the modal closes.
     */
    function wpslUnbindSurveyListeners() {
        $exitSurvey.find( 'input[type=radio]' ).off();
        $exitSurvey.find( 'footer .button-primary' ).off();
        $competitorSelect.off();
    }

} )( jQuery );