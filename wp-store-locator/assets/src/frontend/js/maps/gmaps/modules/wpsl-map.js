import { importedLibraries } from '../../../../../common/wpsl-core.js';
import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { mapBootstrap } from '../../../modules/wpsl-map-bootstrap.js';
import { buttons } from '../../../modules/wpsl-buttons.js';
import { eventHandlers } from '../../../modules/wpsl-event-handlers.js';
import { markers } from './wpsl-markers.js';
import { infoWindow } from './wpsl-infowindow.js';
/*
 * Statically imported, never importModule()'d: that helper resolves to
 * assets/dist/frontend/js/modules/, a directory the build never writes, so a
 * runtime import is a guaranteed 404 on any site without SCRIPT_DEBUG. A
 * static import puts the module inside this provider's emitted chunk.
 */
import { shapes } from './wpsl-shapes.js';

/**
 * Google Maps map functionality for WPSL frontend
 * 
 * @since 3.0.0
 */
export const map = {
    /**
     * Create Google Map.
     * 
     * @since  3.0.0
     * @param  {string} mapId The ID of the map element
     * @param  {number} mapIndex The index of the map
     * @return {void}
     */
    create: function( mapId, mapIndex ) {
        const defaultZoomLevel = config.map.zoomLevel;
        const maxZoom = config.map.autoZoomLevel;

        let zoomLevel, markerData = [];

        if ( typeof config.map.startLatLng.lat !== 'function' ) {
            config.map.startLatLng = new google.maps.LatLng( config.map.startLatLng.lat, config.map.startLatLng.lng );
        }

        const settings = helpers.getMapSettings( mapIndex );
        let startLatLng = settings.startLatLng;
        if ( startLatLng && typeof startLatLng.lat === 'number' && typeof startLatLng.lng === 'number' ) {
            startLatLng = new google.maps.LatLng( startLatLng.lat, startLatLng.lng );
        }

        //@todo zoom to generic func, same for osm/gmaps/mapbox

        // Either the settings page value, or the zoom level set through the shortcode.
        zoomLevel = Number( settings.zoomLevel );

        // A value that differs from the default came from the shortcode, and
        // then it doubles as the max zoom level.
        if ( zoomLevel !== defaultZoomLevel ) {
            config.map.autoZoomLevel = zoomLevel;
        }

        infoWindow.current = infoWindow.create();

        // This is only necessary when multiple [wpsl_map]
        // shortcodes are used on the same page.
        if ( jQuery( '.wpsl-canvas-gmaps' ).length > 1 ) {
            infoWindow.active.push( infoWindow.current );
        }

        // Set the map options.
        const mapOptions = wp.hooks.applyFilters( 'wpslMapOptions', {
            zoom: zoomLevel,
            center: startLatLng,
            mapTypeControl: settings.mapTypeControl,
            streetViewControl: settings.streetView,
            gestureHandling: config.map.gestureHandling,
            cameraControlOptions: {
                position: google.maps.ControlPosition[ settings.controlPosition.toUpperCase() + '_BOTTOM' ]
            }
        }, mapId, mapIndex )

        // Only set a Map ID when one is actually configured. A Map ID and a
        // client-side styles array are mutually exclusive (Google ignores the
        // styles when a mapId is present), so JSON-style mode leaves it empty.
        // The [wpsl_map] map_id attribute overrides the global value per map.
        if ( settings.mapId ) {
            mapOptions.mapId = settings.mapId;
        }

        mapOptions.mapTypeId = google.maps.MapTypeId[ settings.mapType.toUpperCase() ];

        // The legacy scrollwheel option has no effect on vector-rendered maps,
        // which is what renders whenever a Map ID is present ( cloud-based
        // styling ). There scroll-zoom is disabled through gestureHandling
        // 'cooperative' ( Ctrl/Cmd + scroll to zoom ) instead. Raster maps ( no
        // Map ID ) still honor scrollwheel, except when gestureHandling is
        // already 'cooperative', where setting it would undo that.
        if ( ! settings.scrollWheel && mapOptions.mapId ) {
            mapOptions.gestureHandling = 'cooperative';
        } else if ( config.map.gestureHandling !== 'cooperative' ) {
            mapOptions.scrollwheel = settings.scrollWheel;
        }

        const mapElement = document.getElementById( mapId );

        slData.maps[mapIndex] = new importedLibraries.maps.Map( mapElement, mapOptions );
        slData.maps[mapIndex]._wpslMapIndex = mapIndex;

        // Per-map cluster flag: the locator follows the global setting /
        // [wpsl] attribute, a [wpsl_map] additionally honors its own
        // marker_clusters attribute ( shortCode.cluster ).
        slData.maps[mapIndex]._wpslClusterEnabled = ( mapId === 'wpsl-map' )
            ? !! config.markers.markerClusters
            : !! ( window[ 'wpslMap_' + mapIndex ]?.shortCode?.cluster || config.markers.markerClusters );

        // Render admin-defined shapes (unless [wpsl_map shapes="false"]). The
        // infowindow callback resolves per click so shapes and markers share one
        // window; `active` is only populated as each map is created.
        shapes.render( slData.maps[mapIndex], settings, function() {
            return infoWindow.active.length ? infoWindow.active[mapIndex] : infoWindow.current;
        } );

        // After the first render (idle), move the skip-to-results link from its
        // PHP position (just before #wpsl-map, correct for initial Tab order)
        // INTO the map container as its firstChild.
        google.maps.event.addListenerOnce( slData.maps[mapIndex], 'idle', () => {
            const mapEl   = slData.maps[mapIndex].getDiv();
            const skipLink = document.querySelector( '.wpsl-skip-to-results' );

            if ( skipLink && mapEl && ! mapEl.contains( skipLink ) ) {
                mapEl.insertBefore( skipLink, mapEl.firstChild );
            }
        });

        // See if we need to apply a map style.
        this.applyMapStyle( mapIndex );

        // Only run this part if the store locator
        // exist, and we don't just have a basic map.
        if ( jQuery( '#wpsl-map.wpsl-canvas-gmaps' ).length ) {
            mapBootstrap.prepareWpsl();
        }

        // A click on the map closes its infowindow and restores its active markers
        // to the default state. Only the clicked map is affected, a click on map B
        // must not close the popup on map A.
        if ( slData.maps[mapIndex] ) {
            google.maps.event.addListener( slData.maps[mapIndex], 'click', function() {
                const ownInfoWindow = infoWindow.active.length ? infoWindow.active[mapIndex] : infoWindow.current;
                if ( ownInfoWindow ) {
                    ownInfoWindow.close();
                }

                markers.restoreActiveMarkers( mapIndex );
            });
        }

        // One or more [wpsl_map] shortcodes are used.
        if ( helpers.map.wpslMapLocationsExist( mapIndex ) ) {
            const bounds = new google.maps.LatLngBounds();
            const wpslMap = window[ 'wpslMap_' + mapIndex ];
            const locationData = wpslMap.locations;

            jQuery.each( locationData, function( index ) {
                locationData[index].latLng = new google.maps.LatLng( locationData[index].lat, locationData[index].lng );

                if ( markers ) {
                    markers.add( locationData[index], slData.maps[mapIndex] );
                }

                markerData.push( locationData[index].id );
                bounds.extend( locationData[index].latLng );
            });

            if ( helpers.markers.clusteringActive( slData.maps[mapIndex] ) && typeof slData.provider.markers.createCluster === 'function' ) {
                markers.createCluster( slData.maps[mapIndex], mapIndex );
            }

            // Lets a click look up the infowindow that belongs to the clicked
            // marker's map, so a click on map A can't close the popup on map B.
            slData.basicMaps.markers[mapIndex] = markerData;

            // With more than one location and "auto adjust the zoom level" enabled,
            // fit all markers. Disabled leaves the map at its configured start
            // location / zoom level.
            if ( config.map.fitBounds && locationData.length > 1 ) {
                markers.fitBounds( bounds, slData.maps[mapIndex] );
            }

            // Collect the active maps for the grey-map-in-a-tab fix.
            // See the fixGreyTabMap function.
            if ( Array.isArray( config.map.tabAnchor ) ) {
                const mapDetails = {
                    map: slData.maps[mapIndex],
                    bounds: bounds,
                    maxZoom: maxZoom
                };

                slData.mapsInTabs.push( mapDetails );

                this.maybeApplyTabFix();
            }

            eventHandlers.bindExpandingHours();
        }

        // The map controls belong to the [wpsl] locator map, which exists once
        // per page, so a [wpsl_map] must not inject a second copy of them.
        if ( 'wpsl-map' === mapId ) {
            this.mapControlIcons( mapIndex );
        }
    },

    /**
     * Apply custom map styling to the map.
     *
     * @since 3.0.0
     * @param {number} mapIndex The index of the map to apply styling to
     * @return {void}
     */
    applyMapStyle: function( mapIndex ) {
        const mapStyle = wp.hooks.applyFilters( 'wpslMapStyle', helpers.formatting.tryParseJSON( config.map.style ), mapIndex );
        if ( mapStyle ) {
            slData.maps[mapIndex].setOptions( { styles: mapStyle } );
        }
    },

    /**
     * Add the 'reload' and 'find location' icon 
     * to the map once it has finished loading.
     *
     * @since  3.0.0
     * @param  {number} mapIndex The index of the map the controls belong to
     * @return {void}
     */
    mapControlIcons: function( mapIndex ) {
        const currentMap = slData.maps[mapIndex];

        google.maps.event.addListenerOnce( currentMap, 'tilesloaded', function() {
            jQuery( currentMap.getDiv() ).find( '.gm-style' ).first().append( config.map.controls );

            if ( jQuery( '#wpsl-wrap').hasClass( 'wpsl-search-types-support' ) ) {
                if ( jQuery( '#wpsl-search-type-dropdown').val() !== 'location' ) {
                    jQuery( '.wpsl-icon-direction' ).hide();
                    jQuery( '#wpsl-map-controls' ).addClass( 'wpsl-location-search-inactive' );
                }
            }

            buttons.bindMapControlIcons();
        });
    },

    /**
     * Check if we need to run the code to prevent Google Maps
     * from showing up grey when placed inside one or more tabs.
     *
     * @since 2.2.10
     * @note I can no longer replicate this issue ( April, 2020 ),
     *       but will the leave code in case some user do still experience this issue.
     * @return {void}
     */
    maybeApplyTabFix: function() {
        const len = slData.mapsInTabs.length;

        if ( Array.isArray( config.map.tabAnchor ) ) {
            for ( let i = 0; i < len; i++ ) {
                this.fixGreyTabMap( slData.mapsInTabs[i], config.map.tabAnchor[i], i );
            }
        } else if ( jQuery( 'a[href="#' + config.map.tabAnchor + '"]' ).length ) {
            this.fixGreyTabMap( slData.maps[0], config.map.tabAnchor, 0 );
        }
    },

    /**
     * This code prevents the map from showing a large grey area if
     * the store locator is placed in a tab, and that tab is actived.
     *
     * The default map anchor is set to 'wpsl-map-tab', but you can
     * change this with the 'wpsl_map_tab_anchor' filter.
     *
     * Note: If the "Attempt to auto-locate the user" option is enabled, and the
     * user switches to the store locator tab before the Geolocation timeout is
     * reached, the map is sometimes centered in the ocean. Cause unknown, the
     * only fix is to disable that option when the locator is used in a tab.
     *
     * @since  3.0.0
     * @param  {object} currentMap The map object from the current map
     * @param  {string} mapTabAnchor The anchor used in the tab that holds the map
     * @param  {number} mapNumber Map number
     * @link   http://stackoverflow.com/questions/9458215/google-maps-not-working-in-jquery-tabs
     * @return {void}
     */
    fixGreyTabMap: function( currentMap, mapTabAnchor, mapNumber ) {
        // The setTimeout callback below runs with `this` as window.
        const self = this;

        const $wpsl_tab = jQuery( 'a[href="#' + mapTabAnchor + '"]' );
        const maxZoom = typeof currentMap.maxZoom !== 'undefined' ? currentMap.maxZoom : config.map.autoZoomLevel;

        let tabMap;

        // We need to do this to prevent the map from flashing if
        // there's only a single marker on the first click on the tab.
        if ( typeof mapNumber !== 'undefined' && mapNumber === 0 ) {
            $wpsl_tab.addClass( 'wpsl-fitbounds' );
        }

        $wpsl_tab.on( 'click', function() {
            setTimeout( function() {
                let bounds;

                if ( typeof currentMap.map !== 'undefined' ) {
                    bounds = currentMap.bounds;

                    tabMap = currentMap.map;
                } else {
                    tabMap = currentMap;
                }

                const mapZoom   = tabMap.getZoom();
                const mapCenter = tabMap.getCenter();

                google.maps.event.trigger( tabMap, 'resize' );

                if ( ! $wpsl_tab.hasClass( 'wpsl-fitbounds' ) ) {
                    // Make sure fitBounds doesn't zoom past the max zoom level
                    self.attachBoundsChangedListener( tabMap, maxZoom );

                    tabMap.setZoom( mapZoom );
                    tabMap.setCenter( mapCenter );

                    if ( typeof bounds !== 'undefined' ) {
                        tabMap.fitBounds( bounds );
                    } else {
                        markers.fitBounds();
                    }

                    $wpsl_tab.addClass( 'wpsl-fitbounds' );
                }
            }, 50 );

            return config.map.tabAnchorReturn;
        });
    },

    /**
     * Add the bounds_changed event listener to the map object
     * to make sure we don't zoom past the max zoom level.
     *
     * @since  3.0.0
     * @param  {object} map The map object to attach the event listener to
     * @param  {number} maxZoom The maximum allowed zoom level
     * @return {void}
     */
    attachBoundsChangedListener: function ( map, maxZoom ) {
        google.maps.event.addListenerOnce( map, 'bounds_changed', function() {
            google.maps.event.addListenerOnce( map, 'idle', function() {
                if ( this.getZoom() > maxZoom ) {
                    this.setZoom( maxZoom );
                }
            });
        });
    },

    /**
     * Invalidate map size to force re-rendering.
     * Call this when a hidden map becomes visible (e.g., tab switching).
     *
     * @since  3.0.0
     * @param  {number} mapIndex The index of the map to invalidate (default: 0)
     * @return {void}
     */
    invalidateSize: function( mapIndex ) {
        mapIndex = mapIndex || 0;
        if ( typeof slData.maps[mapIndex] !== 'undefined' ) {
            google.maps.event.trigger( slData.maps[mapIndex], 'resize' );
        }
    }
};