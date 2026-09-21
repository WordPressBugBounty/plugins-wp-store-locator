/**
 * Overlay showing feedback while an AJAX request is running: either
 * 'Loading...', or an error if the request fails.
 *
 * @since 3.0.0
 */
export const feedbackOverlay = {
    loader: {
        dots: 0,
        interval: ''

    },

    /**
     * Create a new overlay showing feedback.
     *
     * @since   3.0.0
     * @param   {object} args Object with elem, text, and optional class properties
     * @returns {void}
     */
    create: function( args ) {
        let cssClass = '';

        feedbackOverlay.loader.dots = 0;

        if ( ! args.elem.find( '.wpsl-feedback-overlay' ).length ) {
            if ( typeof args.class === 'string' ) {
                cssClass = args.class;
            }

            args.elem.append( '<div class="wpsl-feedback-overlay ' + cssClass + '"><div class="wpsl-feedback-text">' + args.text + '</div></div>' );

            if ( cssClass !== 'wpsl-red' ) {
                feedbackOverlay.animateDots();
            }
        } else {
            feedbackOverlay.update( args );
        }
    },

    /**
     * Update the text / used class of the feedback overlay.
     *
     * @param {object} args
     */
    update: function( args ) {
        const $feedbackOverlay = args.elem.find( '.wpsl-feedback-overlay' );
        const $feedbackText = $feedbackOverlay.find( '.wpsl-feedback-text' );

        let animateDots = true;

        $feedbackOverlay.removeClass( 'wpsl-red' ).removeAttr( 'style' );
        $feedbackText.html( args.text );

        feedbackOverlay.resetLoader();

        if ( typeof args.class === 'string' ) {
            $feedbackOverlay.addClass( args.class );

            if ( args.class === 'wpsl-red' ) {
                animateDots = false;
            }
        }

        if ( typeof args.fadeOut === 'boolean' ) {
            animateDots = false;

            feedbackOverlay.fadeOut();
        }

        if ( animateDots ) {
            feedbackOverlay.animateDots();
        }
    },

    /**
     * Remove the feedback div.
     *
     * @returns {void}
     */
    remove: function() {
        jQuery( '.wpsl-feedback-overlay' ).remove();
        feedbackOverlay.resetLoader();
    },

    /**
     * Fade out the feedback overlay after a delay.
     *
     * @returns {void}
     */
    fadeOut: function() {
        setTimeout( () => {
            jQuery( '.wpsl-feedback-overlay' ).fadeOut();
        }, 3000 );
    },

    /**
     * Animate the ... after the 'Loading' text.
     *
     * @returns {void}
     */
    animateDots: function() {
        const width = jQuery( '.wpsl-feedback-overlay' ).width();
        jQuery( '.wpsl-feedback-overlay' ).css( 'width', width + 10 ); // make space for the ...

        feedbackOverlay.loader.interval = setInterval( function() {
            if ( feedbackOverlay.loader.dots < 3 ) {
                jQuery( '.wpsl-feedback-dots' ).append( '.' );
                feedbackOverlay.loader.dots++;
            } else {
                jQuery( '.wpsl-feedback-dots' ).html( '' );
                feedbackOverlay.loader.dots = 0;
            }

        }, 250 );
    },

    /**
     * Reset the overlay loader vars.
     *
     * @returns {void}
     */
    resetLoader: function () {
        clearInterval( feedbackOverlay.loader.interval );
        feedbackOverlay.loader.dots = 0;
    }
};