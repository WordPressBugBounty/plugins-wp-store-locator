import { dom } from './helpers/wpsl-dom.js';
import { map, mapbox } from './helpers/wpsl-map.js';
import { coordinates } from './helpers/wpsl-coordinates.js';
import { errors } from './helpers/wpsl-errors.js';
import { formatting } from './helpers/wpsl-formatting.js';
import { ui } from './helpers/wpsl-ui.js';
import { utils, onboarding, settings } from './helpers/wpsl-utils.js';

/**
 * WP Store Locator - Helpers
 * 
 * Re-exports the helper modules as a single helpers object.
 * 
 * @since 3.0.0
 */

export const helpers = {
    dom,
    map,
    mapbox,
    coordinates,
    errors,
    formatting,
    ui,
    utils,
    onboarding,
    settings
};