import { createApiRequest, getGdprModule } from '../../../common/wpsl-core.js';
import { slData, config } from './wpsl-shared.js';
import { mapBootstrap } from './wpsl-map-bootstrap.js';
import { buttons } from './wpsl-buttons.js';
import { helpers } from './wpsl-helpers.js';
import { accessibility } from './wpsl-accessibility.js';

/**
 * Whether the page level setup ( state, templates, search bindings ) already
 * ran. Only Borlabs reaches init() twice, see the note there.
 *
 * @since 3.0.0
 */
let pageInitialised = false;

/**
 * Setup and initialization for WPSL frontend
 * 
 * @since 3.0.0
 */
export const setup = {
    /**
     * Initialize the setup
     * 
     * @since 3.0.0
     */
    init: function() {
        if ( ! pageInitialised ) {
            pageInitialised = true;

            this.setState();
            this.setUnderscoreSettings();
            buttons.bindSearch();
        }

        createApiRequest.maybeLoadLibraries( null, function() {
            mapBootstrap.init();
            
            // Initialize map accessibility features after map is loaded
            setTimeout( function() {
                accessibility.map.init();
            }, 500 );
        });
    },

    /**
     * Create config object based on the wpslSettings groups
     * 
     * @since   3.0.0
     * @returns {void}
     */
    createConfig: function() {
        jQuery.each( wpslSettings, function( key, value ) {
            if ( typeof value === 'object' && value !== null ) {
                config[key] = {};

                jQuery.each( value, function( subKey, subValue ) {
                    config[key][subKey] = setup.formatSettings( subValue, subKey );
                });
            } else {
                config[key] = setup.formatSettings( value, key );
            }
        });

        if ( typeof config.map !== 'undefined' ) {
            config.map.fitBounds = Number( config.map.fitBounds ) === 1;
        }

        // Set the CTA button classes based on the ctaButtons setting and button_styles
        if ( config.ux.ctaButtons && config.ux.buttonStyles ) {
            const styles = config.ux.buttonStyles;
            
            config.ux.ctaDetailsClass = ' wpsl-styled-btn wpsl-' + ( styles.more_details || 'primary' ) + '-btn';
            config.ux.ctaDirectionsClass = ' wpsl-styled-btn wpsl-' + ( styles.directions || 'secondary' ) + '-btn';
            config.ux.ctaZoomClass = ' wpsl-styled-btn wpsl-' + ( styles.zoom_here || 'secondary' ) + '-btn';
            config.ux.ctaStreetViewClass = ' wpsl-styled-btn wpsl-' + ( styles.streetview || 'secondary' ) + '-btn';
        } else {
            config.ux.ctaDetailsClass = '';
            config.ux.ctaDirectionsClass = '';
            config.ux.ctaZoomClass = '';
            config.ux.ctaStreetViewClass = '';
        }
        
        // Legacy ctaClass for backwards compatibility
        config.ux.ctaClass = config.ux.ctaButtons ? ' wpsl-styled-btn' : '';
    },

    /**
     * Set state object
     * 
     * @since   3.0.0
     * @returns {void}
     */
    setState: function() {
        // [wpsl] shortcode usage
        if ( jQuery( '#wpsl-map' ).length ) {
            if ( config.search.directionRedirect ) {
                slData.directionOrigin = '';
            }

            slData.$storeList = jQuery( '#wpsl-stores ul' );
        }

        // [wpsl_map] shortcode usage
        if ( jQuery( 'div[id^="wpsl-base-"]' ).length ) {
            slData.basicMaps = {
                markers: [],
                fitBoundsMapIds: []
            };
        }

        if ( typeof wpslTemplateSections !== 'undefined' ) {
            slData.templates = wpslTemplateSections;
        }

        if ( typeof config.collectStatistics === 'string' ) {
            slData.statistics = {};  
        }

        wp.hooks.applyFilters( 'wpslStateData', slData);
    },

    /**
     * Check if we need to split the passed value and turn this into a number or just return it
     * 
     * @since   3.0.0
     * @param   {string|number} value  The original settings value
     * @param   {string}        type   The type of setting we are formatting
     * @returns {string|object|number} The formatted settings value
     */
    formatSettings: function( value, type ) {
        /*
         * categoryIds holds a list of ids ( '30,25' ), which the regex below
         * can't tell apart from a latlng pair. Splitting it makes jQuery send
         * filter[0]=30&filter[1]=25 instead of filter=30,25, and the store
         * search stops seeing a category filter.
         */
        const skipSplit = [ 'categoryIds' ];
        const regeExp = /^-?[0-9.?]*\,-?[0-9.?]+$/;
        
        let setting, splitSetting, splitLatLng, lat, lng;

        if ( type == 'startLatLng' ) {
            if ( this.getProvider() === 'gmaps' ) {
                if ( value && regeExp.test( value ) ) {
                    splitLatLng = this.splitToNumber( value );

                    lat = splitLatLng[0];
                    lng = splitLatLng[1];
                } else {
                    lat = 0;
                    lng = 0;
                }

                // With delayed loading of the dynamic map google.maps.LatLng
                // isn't available yet, so keep a plain object and build the
                // real instance once the user loads the map. Also guards
                // against a stray window.google set by an unrelated script
                // (e.g. GTM, a browser extension) without the Maps API.
                if ( typeof google === 'object' && typeof google.maps === 'object' && typeof google.maps.LatLng === 'function' ) {
                    setting = new google.maps.LatLng( lat, lng );
                } else {
                    setting = {
                        lat: lat,
                        lng: lng
                    };
                }
            } else {
                // For OSM/Mapbox, check if Leaflet is available. No start
                // point ends at 0,0 below, the same as the Google branch.
                splitSetting = value ? this.splitToNumber( value ) : {};
                
                lat = parseFloat( splitSetting[0] );
                lng = parseFloat( splitSetting[1] );
                
                lat = ( isNaN( lat ) ) ? 0 : lat;
                lng = ( isNaN( lng ) ) ? 0 : lng;

                if ( typeof L !== 'undefined' && typeof L.latLng === 'function' ) {
                    // Use Leaflet LatLng object if available
                    setting = L.latLng( lat, lng );
                } else {
                    // Converted to a Leaflet LatLng once Leaflet loads.
                    setting = {
                        lat: lat,
                        lng: lng
                    };
                }

            }
        } else if ( regeExp.test( value ) && ! skipSplit.includes( type ) ) {
            setting = this.splitToNumber( value );
        } else {
            setting = value;
        }

        return setting;
    },

    /**
     * Split the setting on a comma and make sure it's a number and not a string
     * 
     * @since 3.0.0
     * @param {string} value
     * @returns {object} settings The split setting values
     */
    splitToNumber: function( value ) {
        const splitValues = Array.isArray( value ) ? value : value.split( ',' );
        const result = {};

        jQuery.each( splitValues, function( index, val ) {
            const trimmedVal = typeof val === 'string' ? val.trim() : val;
            const numericVal = parseFloat( trimmedVal );
            
            if ( ! isNaN( numericVal ) ) {
                result[index] = numericVal;
            } else {
                result[index] = trimmedVal;
            }
        });

        return result;
    },

    /**
     * Set the underscore template settings
     * 
     * Defining them here stops other underscore/backbone plugins that set a
     * different _.templateSettings from breaking the template rendering.
     * 
     * @since 2.0.0
     * @link  http://underscorejs.org/#template
     * @requires underscore.js
     */
    setUnderscoreSettings: function() {
        if ( typeof _ !== 'undefined' && _.templateSettings ) {
            _.templateSettings = {
                evaluate: /\<\%(.+?)\%\>/g,
                interpolate: /\<\%=(.+?)\%\>/g,
                escape: /\<\%-(.+?)\%\>/g
            };
        }
    },

    /**
     * Get the validated map provider name.
     * 
     * @since  3.0.0
     * @return {string} The validated provider name (gmaps, mapbox, or osm)
     */
    getProvider: function() {
        let provider = wpslSettings.api.provider;
        
        // If no valid provider exists, then we default to OSM
        if ( ! provider || ! ['gmaps', 'mapbox', 'osm', 'stadia'].includes( provider ) ) {
            provider = 'osm';
        }

        return provider;
    },

    /**
     * Load the map provider module and store it in slData.
     * 
     * @since  3.0.0
     * @param  {Function} callback Function to call after provider is loaded
     * @return {Promise}
     */
    loadProviderModule: function( callback ) {
        const provider = this.getProvider();
        
        return import( 
            /* webpackChunkName: "frontend/js/maps/[request]" */
            `../maps/wpsl-${provider}.js` 
        ).then( providerModule => {
            slData.provider = providerModule[provider];
            
            // Make provider globally accessible for cross-module references
            window[provider] = providerModule[provider];
            
            if ( typeof callback === 'function' ) {
                callback();
            }
            
            return providerModule[provider];
        }).catch( error => {
            console.error( 'Failed to load provider module:', provider, error );
            throw error;
        });
    },

    /**
     * Load the provider module and initialize the map.
     *
     * @since  3.0.0
     * @return {Promise}
     */
    loadAndInit: async function() {
        try {
            await this.loadProviderModule( () => {
                this.init();
            });
        } catch ( error ) {
            console.error( 'Error in loadAndInit:', error );
        }
    },

    /**
     * Handle GDPR checkpoint logic.
     *
     * @since  3.0.0
     * @return {Promise}
     */
    handleGdpr: async function() {
        const gdprKey = 'wpsl-gdpr-' + wpslSettings.api.provider;
        const gdprApproved = helpers.getLocalStorageItem( gdprKey );

        if ( ! gdprApproved ) {
            getGdprModule().init();
        } else {
            this.revealSearch();

            await this.loadAndInit();
        }
    },

    /**
     * Reveal the search bar once a consent gate has been cleared.
     *
     * A consent gate holds the search bar at display:none, so the label widths
     * alignSearchColumns() measured at first paint were all zero and never
     * equalised.
     *
     * @since   3.0.0
     * @returns {void}
     */
    revealSearch: function() {
        const $wrap = jQuery( '#wpsl-wrap' );

        $wrap.removeClass( 'wpsl-labels-aligned' );
        $wrap.removeClass( 'wpsl-gdpr-checkpoint' );

        helpers.template.alignSearchColumns();

        $wrap.addClass( 'wpsl-labels-aligned' );
    }
};

/**
 * Borlabs Cookie's unblock callback.
 *
 * Borlabs blocks the Google Maps bootstrap itself and, once the visitor
 * consents, injects it and calls this by name from its own script - so the
 * name has to exist on window, and the bundle being ES module scoped is why
 * it is assigned rather than declared.
 *
 * @since   3.0.0
 * @returns {void}
 */
window.wpslBorlabsCallback = function() {
    setup.loadProviderModule( function() {
        setup.init();
    } );
};