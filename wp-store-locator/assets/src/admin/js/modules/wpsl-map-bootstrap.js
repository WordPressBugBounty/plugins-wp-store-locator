import { state, getMapContainerSelector } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';
import { mapStyles } from './settings/wpsl-map-styles.js';
import { hasValidApiKey, importedLibraries } from '../../../common/wpsl-core.js';

/**
 * Handle the creation of a new map.
 *
 * @since 3.0.0
 */
export const mapBootstrap = {
    defaultLatLng: '',
    tileLayer: '',

    /**
     * Initialize the map bootstrap and create the map.
     *
     * @since   3.0.0
     * @param   {string} elemId The ID of the element where the map should be rendered
     * @returns {void}
     */
    init: function( elemId ) {
        if ( state.currentPage === 'editor' ) {
            elemId = `wpsl-${state.mapService}-wrap`;
        }

        this.defaultLatLng = wpslSettings.defaultLatLng.split( ',' );

        // styleEditor() owns map creation on the appearance page. If the Maps API
        // only finished loading after it bailed on an undefined google, let it retry.
        if ( state.currentPage === 'appearance' ) {
            const mapId = `wpsl-${state.mapService}-wrap`;
            if ( ! mapObjects.exists( mapId ) ) {
                wp.hooks.doAction( 'wpslAppearanceApiReady' );
            }

            return;
        }

        // Check if map already exists to prevent duplicate initialization
        if ( elemId && mapObjects.exists( elemId ) ) {
            return;
        }

        if ( typeof elemId === 'string' && elemId.trim() !== '' ) {
            this[ state.mapService ]( { elemId: elemId } );
        }
    },

    /**
     * Return the default lat/lng array, falling back to wpslSettings if
     * mapBootstrap.init() has not yet been called.
     *
     * @since  3.0.0
     * @return {string[]} Two-element array [ lat, lng ]
     */
    getDefaultLatLng: function() {
        return this.defaultLatLng.length ? this.defaultLatLng : wpslSettings.defaultLatLng.split( ',' );
    },

    /**
     * Return true only when the Google Maps API is fully ready for use.
     *
     * The bootstrap script makes `google.maps` an object immediately, but library
     * members like `ControlPosition` only exist after `importLibrary()` resolves,
     * so checking `google.maps` alone gives a false positive.
     *
     * @since  3.0.0
     * @return {boolean}
     */
    isGmapsReady: function() {
        return typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof google.maps.ControlPosition !== 'undefined' && ( typeof google.maps.marker !== 'undefined' || typeof importedLibraries.marker !== 'undefined' );
    },

    mapId: {
        /**
         * Check and normalize the map element ID from the provided args.
         *
         * @since  3.0.0
         * @param  {object|string} args The arguments object or element ID string
         * @return {string} The normalized element ID
         */
        checkValue: function( args ) {
            if ( ! args || typeof args === 'string' ) {
                const elemId = args || '';
                args = { elemId };
            }

            if ( ! args.elemId ) {
                args.elemId = `wpsl-${state.mapService}-wrap`;
            }

            return args.elemId;
        },
    },

    /**
     * Create a new Mapbox map.
     *
     * @since   3.0.0
     * @param   {object}   args     Map arguments; args.elemId is the ID of the element where the map should be rendered
     * @param   {function} callback
     * @returns {void}
     */
    mapbox: function( args = {}, callback ) {
        if ( ! hasValidApiKey( 'mapbox', getMapContainerSelector() ) ) {
            return;
        }

        const elemId = this.mapId.checkValue( args );
        const locationCoordinates = helpers.coordinates.getLocationCoordinates();

        const $mapboxKeyField = jQuery( '#wpsl-api-mapbox-key' );
        const mapboxApiKey = $mapboxKeyField.length ? $mapboxKeyField.val() : wpslSettings.api.key;

        let mapboxStyle = 'mapbox://styles/mapbox/light-v11';
        if ( wpslSettings.api.style ) {
            mapboxStyle = wpslSettings.api.style;
        }

        mapboxgl.accessToken = mapboxApiKey;

        const mapOptions = wp.hooks.applyFilters( 'wpslMapOptions', {
            container: elemId,
            style: mapboxStyle,
            center: [ Number( locationCoordinates.lng ), Number( locationCoordinates.lat ) ],
            flyTo: { duration: 0 },
            zoom: parseInt( wpslSettings.defaultZoom ),
            projection: 'mercator'
        }, elemId );

        try {
            const mapbox = new mapboxgl.Map( mapOptions );
            mapbox.addControl(new mapboxgl.NavigationControl( {showCompass: false} ));
            mapObjects.set( { id: elemId, map: mapbox } );

            mapbox.setProjection( 'mercator' );

            if ( state.currentPage === 'editor' ) {
                jQuery( '#wpsl-mapbox-wrap' ).css( 'opacity', 0 );

                mapbox.on( 'idle', function() {
                    mapbox.resize();
                    jQuery( '#wpsl-mapbox-wrap' ).css( 'opacity', 1 );
                });

                const startLatLng = { lat: Number( this.getDefaultLatLng()[0] ), lng: Number( this.getDefaultLatLng()[1] ) };
                helpers.map.setEditorViewport( startLatLng );
            } else if ( state.currentPage === 'settings' ) {
                mapStyles.init();

                if ( helpers.map.mapbox && typeof helpers.map.mapbox.bindMapListeners === 'function' ) {
                    helpers.map.mapbox.bindMapListeners( mapbox );
                }
            }

            if ( typeof callback === 'function' ) {
                callback();
            }
        } catch ( error ) {
            // Silently handle map creation errors
        }
    },

    /**
     * Create a new Google Maps map.
     *
     * @since   3.0.0
     * @param   {object}   args     Map arguments; args.elemId is the ID of the element where the map should be rendered
     * @param   {function} callback
     * @returns {void}
     */
    gmaps: function( args = {}, callback ) {
        if ( ! hasValidApiKey( 'gmaps', getMapContainerSelector() ) ) {
            return;
        }

        if ( ! this.isGmapsReady() ) {

            /**
             * Another plugin owning the Maps library leaves google.maps without
             * the pieces the admin map needs. Say why instead of an empty box.
             */
            if ( window.wpslGmapsConflict ) {
                jQuery( getMapContainerSelector() )
                    .addClass( 'wpsl-map-style-wrap wpsl-api-message' )
                    .html( '<p>' + wpslApiErrors.gmaps.conflictDetected + '</p>' );
            }

            return;
        }

        if ( state.currentPage === 'editor' && jQuery( '#wpsl-online' ).is( ':checked' ) ) {
            return;
        }

        const elemId = this.mapId.checkValue( args );
        const startLatLng = { lat: Number( this.getDefaultLatLng()[0] ), lng: Number( this.getDefaultLatLng()[1] ) };
        const mapOptions = wp.hooks.applyFilters( 'wpslAdminMapOptions', {
            zoom: parseInt( wpslSettings.defaultZoom ),
            center: startLatLng,
            fullscreenControl: false,
            mapTypeControl: false,
            streetViewControl: false,
            zoomControlOptions: {
                position: google.maps.ControlPosition.RIGHT_TOP
            }
        });

        const $dropdown = jQuery( '#wpsl-gmaps-styles' );
        const $cloudBasedInput = jQuery( '#wpsl-cloud-based-id' );
        const $jsonInput = jQuery( '#wpsl-map-style-gmaps' );

        // Style values come from DOM inputs on settings/appearance. The editor page
        // has no such inputs, so it falls back to wpslSettings.mapStyle.
        const appearanceStyle = ( wpslSettings.mapStyle && wpslSettings.mapStyle.gmaps ) ? wpslSettings.mapStyle.gmaps : null;
        const selectedStyle = $dropdown.length > 0 ? $dropdown.val() : ( appearanceStyle ? appearanceStyle.selected : 'cloud_based' );
        const cloudBasedId  = $cloudBasedInput.length > 0 ? $cloudBasedInput.val() : ( appearanceStyle ? appearanceStyle.cloud_based : '' );
        const jsonStyle     = $jsonInput.length > 0 ? $jsonInput.val() : ( appearanceStyle ? appearanceStyle.json : '' );
        const parsedStyle   = jsonStyle ? helpers.formatting.tryParseJSON( jsonStyle ) : false;

        // The dropdown is authoritative. Falling back to JSON under 'cloud_based'
        // would build the map with no Map ID and silently break Advanced Markers.
        if ( selectedStyle === 'cloud_based' ) {
            mapOptions.mapId = ( cloudBasedId && cloudBasedId.trim() ) ? cloudBasedId.trim() : 'DEMO_MAP_ID';
        } else if ( selectedStyle === 'json' ) {
            if ( parsedStyle ) {
                mapOptions.styles = parsedStyle;
            } else {
                mapOptions.mapId = 'DEMO_MAP_ID';
            }
        } else if ( cloudBasedId && cloudBasedId.trim() ) {
            // Fallback: if dropdown doesn't exist but cloudBasedId has value
            mapOptions.mapId = cloudBasedId;
        } else if ( parsedStyle ) {
            // Fallback: if dropdown doesn't exist but JSON has value
            mapOptions.styles = parsedStyle;
        } else {
            mapOptions.mapId = 'DEMO_MAP_ID';
        }

        const googleMaps = new google.maps.Map( document.getElementById( elemId ), mapOptions );

        mapObjects.set( { id: elemId, map: googleMaps } );

        if ( state.currentPage === 'editor' ) {
            helpers.map.setEditorViewport( startLatLng );
        } else if ( state.currentPage === 'settings' ) {
            mapStyles.init();
        }

        if ( typeof callback === 'function' ) {
            callback();
        }
    },

    /**
     * Create a new Stadia Maps map.
     *
     * Stadia uses Leaflet like OSM, but requires a valid Stadia API key.
     *
     * @since   3.0.0
     * @param   {object}   args     Map arguments; args.elemId is the ID of the element where the map should be rendered
     * @param   {function} callback
     * @returns {void}
     */
    stadia: function( args = {}, callback ) {
        if ( ! hasValidApiKey( 'stadia', getMapContainerSelector() ) ) {
            return;
        }

        this.osm( args, callback );
    },

    /**
     * Create a new OpenStreetMaps map.
     *
     * @since   3.0.0
     * @param   {object}   args     Map arguments; args.elemId is the ID of the element where the map should be rendered
     * @param   {function} callback
     * @returns {void}
     */
    osm: function( args = {}, callback ) {
        const elemId = this.mapId.checkValue( args );
        const locationCoordinates = helpers.coordinates.getLocationCoordinates();
        const container = L.DomUtil.get( elemId );
        
        // Leaving Leaflet's own container reference in place throws
        // "Map container is already initialized" when switching templates.
        if ( container && container._leaflet_id ) {
            delete container._leaflet_id;
        }
        
        const mapOptions = {
            mapId: elemId,
            options: {
                center: [ locationCoordinates.lat, locationCoordinates.lng ],
                zoom: parseInt( wpslSettings.defaultZoom ),
                zoomControl: 0
            }
        };

        const startLatLng = { 
            lat: Number( this.getDefaultLatLng()[0] ), 
            lng: Number( this.getDefaultLatLng()[1] ) 
        };

        const osmMap = L.map( mapOptions.mapId, mapOptions.options );

        const tileLayerConfig = wpslSettings.api.tileLayer || {
            urlTemplate: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            options: { attribution: '&copy; OpenStreetMap' }
        };

        // Vector tile configs ( OpenFreeMap ) go through MapLibre GL, like the
        // frontend wpsl-map.js. They have no urlTemplate, so passing them to
        // L.tileLayer() crashes Leaflet.
        let webglSupported = false;
        try {
            const canvas = document.createElement( 'canvas' );
            webglSupported = !! ( window.WebGLRenderingContext && ( canvas.getContext( 'webgl' ) || canvas.getContext( 'experimental-webgl' ) ) );
        } catch ( e ) {}

        if ( tileLayerConfig.type === 'vector' && typeof L.maplibreGL !== 'undefined' && webglSupported ) {
            const glLayer = L.maplibreGL( {
                style: tileLayerConfig.style,
                attribution: tileLayerConfig.options.attribution
            } );

            let fellBack = false;

            glLayer.addTo( osmMap );
            mapBootstrap.tileLayer = glLayer;

            const glMap = glLayer.getMaplibreMap ? glLayer.getMaplibreMap() : null;

            if ( glMap ) {
                glMap.on( 'error', function() {
                    if ( fellBack ) { 
                        return; 
                    }
                    
                    fellBack = true;
                    osmMap.removeLayer( glLayer );
                    const fallback = tileLayerConfig.fallback || {
                        urlTemplate: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                        options: { attribution: '&copy; OpenStreetMap' }
                    };
                    mapBootstrap.tileLayer = L.tileLayer( fallback.urlTemplate, fallback.options );
                    mapBootstrap.tileLayer.addTo( osmMap );
                } );
            }
        } else {
            const rasterConfig = ( tileLayerConfig.type === 'vector' )
                ? ( tileLayerConfig.fallback || { urlTemplate: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', options: { attribution: '&copy; OpenStreetMap' } } )
                : tileLayerConfig;
            mapBootstrap.tileLayer = L.tileLayer( rasterConfig.urlTemplate, rasterConfig.options );
            mapBootstrap.tileLayer.addTo( osmMap );
        }

        L.control.zoom( { position: 'topright' } ).addTo( osmMap );

        mapObjects.set( { id: elemId, map: osmMap } );

        // Always invalidate size after a delay to fix grey area issues
        // Container dimensions may not be final when map is created
        setTimeout( () => {
            osmMap.invalidateSize( true );
        }, 300 );

        if ( state.currentPage === 'editor' ) {
            helpers.map.setEditorViewport( startLatLng );
        } else if ( state.currentPage === 'settings' ) {
            mapStyles.init();
        }

        if ( typeof callback === 'function' ) {
            callback();
        }
    }
};

/**
 * Manage active map instances.
 *
 * @since 3.0.0
 */
export const mapObjects = {
    active: [],

    /**
     * Store a map instance by its element ID.
     *
     * @since   3.0.0
     * @param   {object} args Object containing id and map properties
     * @returns {void}
     */
    set: function( args ) {
        const existing = this.active.find( function( obj ) {
            return obj.id === args.id;
        });

        // A container only ever holds one map, so a second call for the same
        // id means the previous instance was torn down and rebuilt. Keeping
        // the first one hands every later get() a destroyed map.
        if ( existing ) {
            existing.map = args.map;
        } else {
            this.active.push( args );
        }
    },

    /**
     * Retrieve a map instance by its exact element ID.
     *
     * get() rewrites the ID to match the current screen, which most callers
     * want. Callers that target a specific container ( e.g. cleaning up another
     * service's map ) must look it up literally, or get() returns the current
     * service's map and they act on the wrong one.
     *
     * @since  3.0.0
     * @param  {string} elemId The element ID of the map to retrieve
     * @return {object|null} The map instance or null if not found
     */
    getById: function( elemId ) {
        const foundMap = this.active.find( function( obj ) {
            return obj.id === elemId;
        });

        return foundMap ? foundMap.map : null;
    },

    /**
     * Retrieve a map instance by its element ID.
     *
     * @since  3.0.0
     * @param  {string} elemId The element ID of the map to retrieve
     * @return {object|null} The map instance or null if not found
     */
    get: function( elemId ) {
        const pageTypes = [ 'editor', 'settings', 'appearance' ];

        if ( typeof elemId === 'undefined' || ! elemId ) {
            if ( state.currentPage === 'onboarding' ) {
                elemId = 'wpsl-onboarding-map';
            } else if ( pageTypes.includes( state.currentPage ) ) {
                elemId = 'wpsl-' + state.mapService + '-wrap';
            }
        }

        if ( jQuery( '#wpsl-geocode-test' ).length && 
            typeof jQuery.fn.dialog !== 'undefined' && 
            typeof jQuery( '#wpsl-geocode-test').dialog === 'function' && 
            typeof jQuery( '#wpsl-geocode-test').dialog( 'instance' ) !== 'undefined' && 
            jQuery( '#wpsl-geocode-test' ).dialog( 'isOpen' ) ) {
            elemId = 'wpsl-' + state.mapService + '-geocode-preview';
        }

        return this.getById( elemId );
    },

    /**
     * Check if a map with the passed ID is already active.
     *
     * @since   3.0.0
     * @param   {string} id ID of the current map
     * @returns {bool}   active
     */
    exists: function( id ) {
        return this.active.some( function( obj ) {
            return obj.id === id;
        });
    }
};