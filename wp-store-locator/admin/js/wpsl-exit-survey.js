/**
 * Handle the exit survey modal and submission.
 *
 * @since 2.2.240
 */
( function( $ ) {
    var params, currentUrl, feedback, selectedReason,
        surveyOptions= [ 'not_working', 'better_plugin', 'missing_feature', 'temporary_deactivation', 'other' ],
        $surveySupportLinks  = $( '.wpsl-survey-support-links' ),
        $exitSurvey = $( '#wpsl-exit-survey' );

    // Listen for clicks on the WPSL 'Deactivate' link
    $( '#deactivate-wp-store-locator' ).on( 'click', function() {
        params = '';
        currentUrl = $( this ).attr( 'href' );

        // Create the modal
        MicroModal.show( 'wpsl-exit-survey', {
            onClose: modal => wpslUnbindSurveyListeners()
        });

        // Make sure all fields are reset.
        $exitSurvey.find( 'input[type=radio]' ).prop( 'checked', false );
        $exitSurvey.find( 'input[type=text]' ).val( '' );

        // Make sure all text input fields / links are hidden
        $( '.wpsl-survey-support-links, #wpsl-exit-survey input[type=text]' ).hide();

        // Set the correct href value for the 'Skip & Deactivate' link.
        $( '#wpsl-skip-survey' ).attr( 'href', currentUrl );

        /**
         * Listen for radio button changes so that we
         * know which element ( text input fields / links )
         * should be made visible based on the selected value.
         */
        $exitSurvey.find( 'input[type=radio]' ).on( 'change', function() {

            // Hide all additional fields
            $exitSurvey.find( 'input[type=text]' ).hide();
            $surveySupportLinks.hide();

            switch ( $( this ).val() ) {
                case 'not_working':
                    $surveySupportLinks.show();

                    break;
                case 'better_plugin':
                case 'missing_feature':
                case 'other':
                    $( this ).siblings( 'input[type=text]' ).show().css( 'display' ,'block' );

                    break;
            }
        });

        /**
         * Handle the submit & deactivation action
         * and make sure the selected reason / feedback
         * is included in the URL parameters.
         */
        $exitSurvey.find( 'footer .button-primary' ).on( 'click', function() {
            selectedReason = $exitSurvey.find( 'input[type=radio]:checked' );

            if ( $.inArray( selectedReason.val(), surveyOptions ) >= 0 ) {
                params   = '&wpsl_deactivation_reason=' + selectedReason.val();
                feedback = selectedReason.parent( 'li' ).find( 'input[type=text]' ).val();

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
     * Make sure to unbind the 'on'
     * listeners when the survey is closed.
     */
    function wpslUnbindSurveyListeners() {
        $exitSurvey.find( 'input[type=radio]' ).off();
        $exitSurvey.find( 'footer .button-primary' ).off();
    }

} )( jQuery );