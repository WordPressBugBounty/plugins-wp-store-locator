/**
 * The start marker drag gate.
 *
 * Dragging the marker picks a start location without typing, but the drop only
 * becomes a setting once reverse geocoded into a name: start_name is required,
 * and the sanitizer clears start_latlng on an empty one. A provider that can't
 * reverse geocode ends a drag with an error and nothing saved, so it's better
 * not to offer it.
 *
 * Only Stadia can be in that position, and only on some plans, which is why the
 * answer is localized rather than derived from the provider name. Standalone
 * ( no jQuery / shared-state imports ) so the node test suite can exercise it.
 *
 * @since 3.0.0
 */

/**
 * Whether the marker on this screen may be dragged.
 *
 * The settings page shows a preview, never a draggable marker. The store editor
 * writes coordinates straight into its fields and needs no name, so it drags
 * regardless. Onboarding is the only screen that depends on the reverse geocode;
 * an absent flag means "not localized", which must not read as a refusal.
 *
 * @since   3.0.0
 * @param   {string}  currentPage      The admin screen ( settings, editor, onboarding )
 * @param   {*}       reverseGeocoding The localized flag, "1" / "0" or undefined
 * @returns {boolean} True when the marker may be dragged
 */
export function markerIsDraggable( currentPage, reverseGeocoding ) {
    if ( currentPage === 'settings' ) {
        return false;
    }

    if ( currentPage !== 'onboarding' ) {
        return true;
    }

    // Only an explicit 0 / "0" withdraws the drag.
    return ! ( reverseGeocoding === 0 || reverseGeocoding === '0' );
}