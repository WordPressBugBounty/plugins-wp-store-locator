import { markers } from './wpsl-markers.js';

/**
 * WP Store Locator - Location marker.
 *
 * Handles the location marker dropdown on the store editor. Open/select/dismiss
 * handlers live in wpslSharedFuncs.initMarkerDropdowns() (shared with the
 * shortcode generator); this module updates the preview map's marker to match
 * the dropdown selection.
 *
 * @since 3.0.0
 */

export const locationMarker = {

    /**
     * Initialize the location marker dropdown handler.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        if ( ! jQuery( '.wpsl-lm-dropdown' ).length ) {
            return;
        }

        if ( typeof wpslSharedFuncs === 'undefined' || ! wpslSharedFuncs.initMarkerDropdowns ) {
            return;
        }

        wpslSharedFuncs.initMarkerDropdowns( {
            onSelect: function( $item, $dropdown ) {
                /*
                 * The preview map mirrors the last-picked dropdown;
                 * storeMarkerSrc() resolves from the dropdown with this class.
                 */
                jQuery( '.wpsl-lm-dropdown' ).removeClass( 'wpsl-lm-preview-source' );
                $dropdown.addClass( 'wpsl-lm-preview-source' );

                // No coordinates means no marker on the preview map.
                const handler = markers.getActive();

                if ( handler && typeof handler.updateIcon === 'function' ) {
                    handler.updateIcon();
                }
            }
        } );
    }
};