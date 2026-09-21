/**
 * The map key gate.
 *
 * Mapbox GL throws without an access token and Stadia's tiles 401 outside
 * localhost, so a missing or invalid key leaves the map spot blank. This
 * decides when to render the "a valid API key is required" box instead.
 *
 * Google Maps is absent: its error handler shows the API's own message, and
 * OSM needs no key. Standalone ( no jQuery / shared-state imports ) so the
 * node test suite can exercise it directly.
 *
 * @since 3.0.0
 */

const providersRequiringKey = [ 'mapbox', 'stadia' ];

/**
 * Whether the map boot should be replaced by the key-required box.
 *
 * The hasValidKey flag may arrive as a real boolean or as the "1" / ""
 * strings wp_localize_script produces, so it's only trusted for truthiness.
 *
 * @since   3.0.0
 * @param   {object}  api The localized API settings ( provider, key, hasValidKey )
 * @returns {boolean} True when the key is missing or flagged invalid
 */
export function mapKeyMissing( api ) {
    if ( ! api || ! providersRequiringKey.includes( api.provider ) ) {
        return false;
    }

    return ! api.key || ! api.hasValidKey;
}