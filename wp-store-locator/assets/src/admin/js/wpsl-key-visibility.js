/**
 * Show / hide the API key inputs on the settings API tab 
 * and in the  onboarding wizard.
 *
 * @since 3.0.0
 */
( function() {
    'use strict';

    /**
     * Flip one key input between masked and readable.
     *
     * @param {HTMLElement} button The toggle that was clicked.
     * @return {void}
     */
    function toggleKey( button ) {
        const input = document.getElementById( button.getAttribute( 'aria-controls' ) );
        if ( ! input ) {
            return;
        }

        const label  = button.querySelector( '.wpsl-toggle-key-text' );
        const reveal = 'password' === input.type;

        input.type = reveal ? 'text' : 'password';

        button.setAttribute( 'aria-pressed', reveal ? 'true' : 'false' );

        if ( label ) {
            label.textContent = button.getAttribute( reveal ? 'data-hide' : 'data-show' ) || '';
        }
    }

    /**
     * Only offer the toggle while the input holds a key. Once it is
     * emptied, the input goes back to masked so the next key starts hidden.
     *
     * @param {HTMLInputElement} input The key input that changed.
     * @return {void}
     */
    function syncToggle( input ) {
        const button = input.parentNode ? input.parentNode.querySelector( '.wpsl-toggle-key' ) : null;
        if ( ! button ) {
            return;
        }

        const empty = '' === input.value.trim();

        if ( empty && 'text' === input.type ) {
            toggleKey( button );
        }

        button.hidden = empty;
    }

    /*
     * Delegated, because the API tab's sections are built before the map
     * service switcher shows and hides them, and the wizard swaps steps.
     */
    document.addEventListener( 'click', function( event ) {
        const button = event.target.closest ? event.target.closest( '.wpsl-toggle-key' ) : null;

        if ( ! button ) {
            return;
        }

        event.preventDefault();
        toggleKey( button );
    } );

    document.addEventListener( 'input', function( event ) {
        if ( event.target.classList && event.target.classList.contains( 'wpsl-key-input' ) ) {
            syncToggle( event.target );
        }
    } );
}() );