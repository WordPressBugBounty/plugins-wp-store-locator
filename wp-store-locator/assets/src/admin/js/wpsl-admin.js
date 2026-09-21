// Set webpack publicPath for dynamic chunk loading (only needed in production builds)
if ( typeof wpslSettings !== 'undefined' && ! wpslSettings.scriptDebug ) {
    __webpack_public_path__ = wpslSettings.url + 'assets/dist/';
}

import { api } from './modules/wpsl-api.js';
import { helpers } from './modules/wpsl-helpers.js';
import { state } from './modules/wpsl-shared.js';

export async function initWpslAdmin() {
    if ( state.currentPage === 'category' ) {
        const { categoryMarkers } = await import( /* webpackChunkName: "admin/js/category-image" */ './modules/wpsl-category-image.js' );
        categoryMarkers.init();

        return;
    }

    // Bind info popups on the licenses page (settings.init() handles this for the settings tab).
    if ( state.currentPage === 'licenses' ) {
        wpslSharedFuncs.bindInfoPopup();
    }

    if ( ['editor', 'settings', 'onboarding', 'appearance'].includes( state.currentPage ) ) {
        if ( state.currentPage === 'settings' ) {
            const { settings } = await import( /* webpackChunkName: "admin/js/modules/settings" */ './modules/wpsl-settings.js' );
            settings.init();
        } else if ( state.currentPage === 'editor' ) {
            const { editor } = await import( /* webpackChunkName: "admin/js/modules/editor" */ './modules/wpsl-editor.js' );
            editor.init();
        } else if ( state.currentPage === 'appearance' ) {
            const { appearanceEditor } = await import( /* webpackChunkName: "admin/js/modules/appearance-editor" */ './modules/wpsl-appearance-editor.js' );
            appearanceEditor.init();
            
            // Make appearanceEditor globally available for color picker integration
            window.appearanceEditor = appearanceEditor;
        }
    }
}

/**
 * Report the Google Maps conflict on the Settings page.
 *
 * Detected in the browser, so it can't be rendered server-side with the
 * others. Not persisted and carries no dismiss button: enabling compatibility
 * mode resolves the conflict, and the alert goes with it on the next load.
 *
 * @since   3.0.0
 * @returns {void}
 */
function addGmapsConflictAlert() {
    const $list = jQuery( '.wpsl-alerts-list' );
    if ( $list.length ) {
        if ( $list.find( '[data-plugin="wpsl-gmaps-conflict"]' ).length ) {
            return;
        }

        jQuery( '.wpsl-no-alerts' ).hide();

        $list.append(
            '<li data-plugin="wpsl-gmaps-conflict">' +
                '<span class="wpsl-alert-badge" aria-hidden="true"><span class="wpsl-icon-alerts"></span></span>' +
                '<span class="wpsl-alert-description">' + wpslL10n.gmapsConflictAlert + '</span>' +
            '</li>'
        ).show();

        return;
    }

    const $section = jQuery( '#wpsl-api > .inside' ).first();
    if ( ! $section.length || $section.children( '.wpsl-gmaps-conflict' ).length ) {
        return;
    }

    $section.prepend(
        '<div class="wpsl-warning-callout wpsl-gmaps-conflict" role="alert">' +
            '<p>' + wpslL10n.gmapsConflictAlert + '</p>' +
        '</div>'
    );
}

async function prepareWpslAdmin() {
    if ( typeof wpslSettings === 'undefined' ) {
        console.error( 'WPSL Settings not found' );
        return;
    }

    state.currentPage = helpers.dom.getCurrentPage();

    /**
     * The bootstrap loader records a conflict long before this script runs.
     * The settings page is where it hurts: another plugin owning the Maps
     * library leaves the style preview and the autocomplete broken.
     */
    if ( state.currentPage === 'settings' && window.wpslGmapsConflict && wpslSettings.api.provider === 'gmaps' ) {
        addGmapsConflictAlert();
    }

    if ( state.currentPage !== 'category' && typeof api[ wpslSettings.api.provider ].loader === 'function' ) {
        api[ wpslSettings.api.provider ].loader();
    }

    initWpslAdmin();

    wp.hooks.doAction( 'wpslAdminInit' );
}

jQuery( document ).ready( () => prepareWpslAdmin() );