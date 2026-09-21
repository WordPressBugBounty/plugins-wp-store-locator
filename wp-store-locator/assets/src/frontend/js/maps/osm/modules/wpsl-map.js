import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { mapBootstrap } from '../../../modules/wpsl-map-bootstrap.js';
import { eventHandlers } from '../../../modules/wpsl-event-handlers.js';
import { buttons } from '../../../modules/wpsl-buttons.js';
import { markers } from './wpsl-markers.js';
/*
 * Statically imported, never importModule()'d: that helper resolves to
 * assets/dist/frontend/js/modules/, a directory the build never writes, so a
 * runtime import 404s on any site without SCRIPT_DEBUG.
 */
import { shapes } from './wpsl-shapes.js';

/**
 * Detect WebGL support (required by MapLibre GL).
 *
 * @return {boolean}
 */
function wpslWebglSupported() {
    try {
        const canvas = document.createElement( 'canvas' );
        return !! ( window.WebGLRenderingContext && ( canvas.getContext( 'webgl' ) || canvas.getContext( 'experimental-webgl' ) ) );
    } catch ( e ) {
        return false;
    }
}

/**
 * OpenStreetMap map creation and management
 *
 * @since 3.0.0
 */
export const map = {
    /**
     * Create OpenStreetMap Map.
     * 
     * @since 3.0.0
     * @param {string} mapId Map element ID
     * @param {number} mapIndex Map index
     * @return {void}
     */
    create: function( mapId, mapIndex ) {
        const defaultZoomLevel = config.map.zoomLevel;

        let markerSetup,
            mapData,
            zoomLevel;

        markers.currentMapIndex = mapIndex;
        
        // Initialize marker arrays for this map if they don't exist
        if ( typeof markers.active[mapIndex] === 'undefined' ) {
            markers.active[mapIndex] = [];
            markers.latLng[mapIndex] = [];
            markers.current[mapIndex] = '';
        }

        const settings = helpers.getMapSettings( mapIndex );

        // Either the settings page value, or the shortcode's zoom level.
        zoomLevel = Number( settings.zoomLevel );

        // Not equal means it came from the shortcode, so use it as the max zoom.
        if ( zoomLevel !== defaultZoomLevel ) {
            config.map.autoZoomLevel = zoomLevel;
        }

        // A [wpsl_map] shortcode can override the tile layer for a single map
        // through the map_style attribute, otherwise the global one is used.
        const perMapData = window[ 'wpslMap_' + mapIndex ];
        const shortCodeTileLayer = ( perMapData && typeof perMapData.shortCode === 'object' && perMapData.shortCode.tileLayer ) ? perMapData.shortCode.tileLayer : null;
        const wpslTileConfig = shortCodeTileLayer ? shortCodeTileLayer : config.api.tileLayer;

        const tileMaxZoom = ( wpslTileConfig.type === 'vector' ) ?
            wpslTileConfig.options.maxZoom :
            ( wpslTileConfig.options ? wpslTileConfig.options.maxZoom : undefined );

        // Create the map
        const mapOptions = wp.hooks.applyFilters( 'wpslMapOptions', {
            mapId: mapId,
            options: {
                center: [settings.startLatLng.lat, settings.startLatLng.lng],
                zoom: defaultZoomLevel,
                zoomControl: 0,
                scrollWheelZoom: settings.scrollWheel,
                maxZoom: tileMaxZoom
            }
        }, mapId, mapIndex );

        slData.maps[ mapIndex ] = L.map( mapOptions.mapId, mapOptions.options );

        // Per-map cluster flag
        slData.maps[ mapIndex ]._wpslClusterEnabled = ( mapId === 'wpsl-map' )
            ? !! config.markers.markerClusters
            : !! ( window[ 'wpslMap_' + mapIndex ]?.shortCode?.cluster || config.markers.markerClusters );

        // The admin-defined map shapes, if there are any and this map didn't
        // opt out with [wpsl_map shapes="false"].
        shapes.render( slData.maps[ mapIndex ], settings );

        if ( wpslTileConfig.type === 'vector' && typeof L.maplibreGL !== 'undefined' && wpslWebglSupported() ) {
            const glLayer = L.maplibreGL( {
                style: wpslTileConfig.style,
                attribution: wpslTileConfig.options.attribution,
                maxZoom: wpslTileConfig.options.maxZoom
            } );

            // If the style fails to load, fall back to raster so the map is never blank.
            let glLoaded = false;
            let fellBack = false;

            glLayer.addTo( slData.maps[mapIndex] );
            slData.tileLayer = glLayer;

            const glMap = glLayer.getMaplibreMap ? glLayer.getMaplibreMap() : null;

            if ( glMap ) {
                glMap.once( 'load', function() { glLoaded = true; } );
                glMap.on( 'error', function() {
                    if ( glLoaded || fellBack ) {
                        return;
                    }
                    fellBack = true;
                    slData.maps[mapIndex].removeLayer( glLayer );
                    slData.tileLayer = L.tileLayer( wpslTileConfig.fallback.urlTemplate, wpslTileConfig.fallback.options ).addTo( slData.maps[mapIndex] );
                } );
            }
        } else {
            const rasterConfig = ( wpslTileConfig.type === 'vector' ) ? wpslTileConfig.fallback : wpslTileConfig;
            slData.tileLayer = L.tileLayer( rasterConfig.urlTemplate, rasterConfig.options ).addTo( slData.maps[mapIndex] );
        }

        const controlPosition = [ 'left', 'right' ].includes( settings.controlPosition ) ? settings.controlPosition : 'right';

        L.control.zoom( { position: 'top' + controlPosition } ).addTo( slData.maps[mapIndex] );

        // Move the skip-to-results link from its PHP position (just before
        // #wpsl-map) into the map container as firstChild, so position:absolute
        // places it over the map.
        slData.maps[ mapIndex ].whenReady( function() {
            const mapEl   = slData.maps[ mapIndex ].getContainer();
            const skipLink = document.querySelector( '.wpsl-skip-to-results' );

            if ( skipLink && mapEl && ! mapEl.contains( skipLink ) ) {
                mapEl.insertBefore( skipLink, mapEl.firstChild );
            }
        });

        markerSetup = {
            markers: '',
            map: slData.maps[ mapIndex ]
        };

        markers.init( markerSetup );

        // Only when the store locator exists, not for a basic map.
        if ( jQuery( '#wpsl-map.wpsl-canvas-osm, #wpsl-map.wpsl-canvas-stadia' ).length ) {
            mapBootstrap.prepareWpsl();
        }

        // One or more [wpsl_map] shortcodes are used.
        if ( helpers.map.wpslMapLocationsExist( mapIndex ) ) {
            const wpslMap = window[ 'wpslMap_' + mapIndex ];
            
            mapData = wpslMap.locations;

            // Reset existing marker data for this map only
            markers.latLng[mapIndex].length = 0;
            
            // Per-map shortcode attribute, or the global setting.
            const clusterEnabled = helpers.markers.clusteringActive( slData.maps[ mapIndex ] );

            if ( clusterEnabled ) {
                markers.layer = new L.markerClusterGroup( wp.hooks.applyFilters( 'wpslMarkerClusterGroup', config.markers.cluster ) );
            }

            jQuery.each( mapData, function( index ) {
                markers.add( mapData[index], slData.maps[ mapIndex ] );
            });

            if ( clusterEnabled && markers.layer ) {
                slData.maps[ mapIndex ].addLayer( markers.layer );
            }

            // With "auto adjust the zoom level" off we keep the configured viewport.
            if ( config.map.fitBounds ) {
                markers.fitBounds( markers.latLng[mapIndex], slData.maps[ mapIndex ] );
            }

            eventHandlers.bindExpandingHours();
        }

        /*
         * The marker layer and map index are shared, and every map created
         * here claims them. Hand them back to the [wpsl] map afterwards, or
         * its search results land on ( and clear ) a [wpsl_map] below it.
         */
        if ( mapId === 'wpsl-map' ) {
            markers.storeLocator = { layer: markers.layer, mapIndex: mapIndex };
        } else if ( markers.storeLocator ) {
            markers.layer           = markers.storeLocator.layer;
            markers.currentMapIndex = markers.storeLocator.mapIndex;
        }

        this.mapControlIcons();
    },

    /**
     * Add the 'reload' and 'find location' icon to the map.
     *
     * @since   2.0.0
     * @return {void}
     */
    mapControlIcons: function() {
        slData.maps[0].whenReady( function() {
            jQuery( '.leaflet-container' ).append( config.map.controls );

            buttons.bindMapControlIcons();
        });
    },

    /**
     * Make sure we don't zoom too far on the map.
     *
     * @since   3.0.0
     * @param   {object} mapObj The current map
     * @param   {number} maxZoom The max allowed zoom level
     * @return {void}
     */
    attachBoundsChangedListener: function( mapObj, maxZoom ) {
        mapObj.once( 'load moveend', function( e ) {
            if ( mapObj.getZoom() > maxZoom ) {
                mapObj.setZoom( maxZoom );
            }
        });
    },

    /**
     * Invalidate map size to force re-rendering, for when a hidden map becomes
     * visible through e.g. a tab switch.
     *
     * @since   3.0.0
     * @param   {number} mapIndex The index of the map to invalidate (default: 0)
     * @return {void}
     */
    invalidateSize: function( mapIndex ) {
        mapIndex = mapIndex || 0;

        if ( typeof slData.maps[mapIndex] === 'undefined' ) {
            return;
        }

        slData.maps[mapIndex].invalidateSize();

        // Run any fitBounds() that was deferred while the container had no size.
        // The animation is off so the zoom is computed against the settled
        // viewport instead of racing the resize.
        const pending = markers.pendingFit[mapIndex];

        if ( pending && slData.maps[mapIndex].getSize().x && slData.maps[mapIndex].getSize().y ) {
            delete markers.pendingFit[mapIndex];

            markers.fitBounds( pending.bounds, pending.mapObj, jQuery.extend( {}, pending.options || {}, { animate: false } ) );
        }
    }
};