import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { mapBootstrap } from '../../../modules/wpsl-map-bootstrap.js';
import { eventHandlers } from '../../../modules/wpsl-event-handlers.js';
import { buttons } from '../../../modules/wpsl-buttons.js';
import { markers } from './wpsl-markers.js';
import { geojson } from './wpsl-geojson.js';
/*
 * Statically imported, never importModule()'d: that helper resolves to
 * assets/dist/frontend/js/modules/, which the build never writes, so a runtime
 * import is a guaranteed 404 on any site without SCRIPT_DEBUG.
 */
import { shapes } from './wpsl-shapes.js';

/**
 * Mapbox map creation and management.
 * 
 * @since 3.0.0
 */
export const map = {
    /**
     * Create Mapbox Map.
     * 
     * @since  3.0.0
     * @param  {string} mapId The ID of the map element
     * @param  {number} mapIndex The index of the map
     * @return {void}
     */
    create: function( mapId, mapIndex ) {
        const settings = helpers.getMapSettings( mapIndex );

        mapboxgl.accessToken = config.api.key;

        // A [wpsl_map] shortcode can override the style for a single map
        // through the map_style attribute, otherwise the global one is used.
        const perMapData = window[ 'wpslMap_' + mapIndex ];
        const mapStyle = ( perMapData && typeof perMapData.shortCode === 'object' && perMapData.shortCode.mapStyle ) ? perMapData.shortCode.mapStyle : config.map.style;

        const mapOptions = wp.hooks.applyFilters( 'wpslMapOptions', {
            mapId: mapId,
            options: {
                container: mapId,
                style: mapStyle,
                center: [settings.startLatLng.lng, settings.startLatLng.lat],
                zoom: settings.zoomLevel,
                projection: 'mercator', // keep the map flat
                scrollZoom: settings.scrollWheel
            }
        }, mapId, mapIndex );

        slData.maps[mapIndex] = new mapboxgl.Map( mapOptions.options );

        // Per-map cluster flag: the locator follows the global setting, a
        // [wpsl_map] also honors its own marker_clusters attribute.
        slData.maps[mapIndex]._wpslClusterEnabled = ( mapId === 'wpsl-map' )
            ? !! config.markers.markerClusters
            : !! ( ( perMapData && perMapData.shortCode && perMapData.shortCode.cluster ) || config.markers.markerClusters );

        // The admin-defined map shapes. The source / layers are added once the
        // style has loaded, which the module handles itself.
        shapes.render( slData.maps[mapIndex], settings, mapIndex );

        // Move the skip-to-results link from its PHP position (just before
        // #wpsl-map) into the map container so position:absolute can place it.
        slData.maps[mapIndex].once( 'load', () => {
            const mapEl   = slData.maps[mapIndex].getContainer();
            const skipLink = document.querySelector( '.wpsl-skip-to-results' );

            if ( skipLink && mapEl && ! mapEl.contains( skipLink ) ) {
                mapEl.insertBefore( skipLink, mapEl.firstChild );
            }
        });

        // Add the map nav control elements.
        const nav = new mapboxgl.NavigationControl( {showCompass: false} );
        const position = ['left', 'right'].includes( settings.controlPosition ) ? settings.controlPosition : 'right';

        slData.maps[mapIndex].addControl( nav, `top-${position}` );

        slData.canvas = jQuery( '.mapboxgl-canvas' );
        slData.layerDetails = {};
        
        const markerSettings = helpers.markers.getSettings();
        slData.layerDetails.start = helpers.markers.resolveMarkerSrc( config.markers.start, markerSettings.url );

        if ( jQuery( '#wpsl-map.wpsl-canvas-mapbox' ).length ) {
            slData.maps[0].on( 'load', () => {
                mapBootstrap.prepareWpsl();
            });
        }

        if ( helpers.map.wpslMapLocationsExist( mapIndex ) ) {
            const wpslMap = window['wpslMap_' + mapIndex];
            const mapData = wpslMap.locations;

            slData.maps[mapIndex].on( 'style.load', () => {
                if ( mapData && mapData.type === 'FeatureCollection' ) {
                    geojson.add( mapData, slData.maps[mapIndex] );
                } else {
                    jQuery.each( mapData, function( index ) {
                        markers.add( mapData[index], slData.maps[mapIndex] );
                    });
                }

                // The geojson module is shared, so geojson.active only holds the
                // last-created map's data. A [wpsl_map] keeps its own copy, or a
                // marker click opens a popup with another map's data. The locator
                // map can use the shared state, its data is replaced every search.
                if ( mapId !== 'wpsl-map' ) {
                    slData.maps[mapIndex]._wpslGeojsonData = {
                        active: geojson.active,
                        clickedMarkerId: '',
                        originalIcon: undefined
                    };
                }

                if ( config.map.fitBounds ) {
                    markers.fitBounds( '', slData.maps[mapIndex] );
                }

                eventHandlers.bindExpandingHours();
            });
        }

        this.mapControlIcons();
    },

    /**
     * Add the 'reload' and 'find location' icon to the map.
     *
     * @since  3.0.0
     * @return {void}
     */
    mapControlIcons: function() {
        slData.maps[0].on( 'load', function() {
            jQuery( '.mapboxgl-canvas' ).after( config.map.controls );
            
            buttons.bindMapControlIcons();
        });
    },

    /**
     * Invalidate map size to force re-rendering.
     * 
     * Fixes the rendering issues a hidden map has once it becomes visible.
     *
     * @since  3.0.0
     * @param  {number} mapIndex The index of the map to invalidate (default: 0)
     * @return {void}
     */
    invalidateSize: function( mapIndex ) {
        mapIndex = mapIndex || 0;
        
        if ( typeof slData.maps[mapIndex] !== 'undefined' ) {
            slData.maps[mapIndex].resize();
        }
    }
};
