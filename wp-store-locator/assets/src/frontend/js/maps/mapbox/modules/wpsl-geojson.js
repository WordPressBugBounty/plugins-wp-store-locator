import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { layers } from './wpsl-layers.js';
import { infoWindow } from './wpsl-infowindow.js';
import { markers } from './wpsl-markers.js';

/**
 * Mapbox GeoJSON data handling.
 *
 * @since 3.0.0
 */
export const geojson = {
    active: {},
    clickedMarkerId: '',
    originalIcon: undefined,
    _wpslTabNavigating: false,
    _wpslLastExpandedClusterId: null,
    _wpslLastExpandedClusterStoreIds: new Set(),

    /**
     * Add GeoJSON data to the map.
     *
     * With clustering enabled and the start marker excluded, it gets its own
     * 'start-location' source so it never joins a cluster. Everything else
     * uses the 'locations' source.
     *
     * @since  3.0.0
     * @param  {object} data GeoJSON FeatureCollection
     * @param  {object} map  Mapbox map instance
     * @return {void}
     */
    add: function( data, map ) {
        const clusteringEnabled      = helpers.markers.clusteringActive( map );
        const excludeStartFromCluster = clusteringEnabled && config.markers.cluster.excludeStartMarker;

        map._wpslKeyboardOverlaysInitialized = false;

        for ( const feature of data.features ) {
            const markerData = wp.hooks.applyFilters( 'wpslMarkerData', helpers.markers.setProperties( feature.properties ) );

            let icon;

            if ( markerData.id === 0 ) {
                icon = 'start';
            } else if ( markerData.markerUrl && markerData.markerUrl.indexOf( 'store.png' ) === -1 ) {
                icon = helpers.markers.iconToken( markerData.markerUrl ).replace( /\.(png|jpg|jpeg|gif|svg)$/i, '' );
            } else {
                icon = 'store';
            }

            feature.properties.icon      = icon;
            feature.properties.markerUrl = markerData.markerUrl;

            if ( typeof slData.layerDetails[icon] === 'undefined' ) {
                slData.layerDetails[icon] = markerData.markerUrl;
            }
        }

        let startFeature    = null;
        const storeFeatures = [];

        for ( const feature of data.features ) {
            if ( feature.properties && feature.properties.id === 0 ) {
                startFeature = feature;
            } else {
                storeFeatures.push( feature );
            }
        }

        if ( excludeStartFromCluster ) {
            if ( startFeature ) {
                const startData   = { type: 'FeatureCollection', features: [ startFeature ] };
                const startSource = map.getSource( 'start-location' );

                if ( startSource ) {
                    startSource.setData( startData );
                } else {
                    map.addSource( 'start-location', { type: 'geojson', data: startData } );

                    if ( ! slData.activeSources.includes( 'start-location' ) ) {
                        slData.activeSources.push( 'start-location' );
                    }
                }
            } else {
                const startSource = map.getSource( 'start-location' );
                if ( startSource && startSource._data && startSource._data.features && startSource._data.features[0] && ! config.search.skipGeocode ) {
                    startFeature = startSource._data.features[0];
                }
            }

            const storeCollection = { type: 'FeatureCollection', features: storeFeatures };
            const locSource       = map.getSource( 'locations' );

            if ( locSource ) {
                locSource.setData( storeCollection );
            } else {
                const sourceArgs = {
                    type:          'geojson',
                    data:           storeCollection,
                    cluster:        true,
                    clusterMaxZoom: Number( config.markers.cluster.maxZoom ),
                    clusterRadius:  Number( config.markers.cluster.radius )
                };

                map.addSource( 'locations', wp.hooks.applyFilters( 'wpslAddSourceArgs', sourceArgs ) );
                slData.activeSources.push( 'locations' );
            }

        } else {
            const source = map.getSource( 'locations' );
            if ( source && source._data && source._data.features && source._data.features[0] && ! config.search.skipGeocode ) {
                if ( source._data.features[0].properties && source._data.features[0].properties.id === 0 ) {
                    data.features.unshift( source._data.features[0] );
                }
            }

            if ( source ) {
                source.setData( data );
            } else {
                const sourceArgs = {
                    type: 'geojson',
                    data:  data
                };

                if ( clusteringEnabled ) {
                    sourceArgs.cluster        = true;
                    sourceArgs.clusterMaxZoom = Number( config.markers.cluster.maxZoom );
                    sourceArgs.clusterRadius  = Number( config.markers.cluster.radius );
                }

                map.addSource( 'locations', wp.hooks.applyFilters( 'wpslAddSourceArgs', sourceArgs ) );
                slData.activeSources.push( 'locations' );
            }

            storeFeatures.length = 0;
            for ( const f of data.features ) { storeFeatures.push( f ); }
            startFeature = null;
        }

        const allFeatures = [];

        if ( startFeature ) { allFeatures.push( startFeature ); }
        for ( const f of storeFeatures ) { allFeatures.push( f ); }

        this.active = {
            type: 'FeatureCollection',
            features: allFeatures.map( function( feature ) {
                return {
                    type: 'Feature',
                    geometry: {
                        type:        feature.geometry.type,
                        coordinates: feature.geometry.coordinates.slice()
                    },
                    properties: Object.assign( {}, feature.properties )
                };
            })
        };

        this.createMarkerLayers( map );
    },

    /**
     * Create marker layers for each unique icon type.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map instance
     * @return {void}
     */
    createMarkerLayers: function( map ) {
        const iconTypes = new Map();
        const clusteringEnabled = helpers.markers.clusteringActive( map );
        const excludeStartFromCluster = clusteringEnabled && config.markers.cluster.excludeStartMarker;

        if ( this.active.features && this.active.features.length > 0 ) {
            this.active.features.forEach( (feature) => {
                if ( feature.properties && feature.properties.icon ) {
                    const iconType  = feature.properties.icon;
                    const markerUrl = feature.properties.markerUrl;

                    if ( iconType && markerUrl ) {
                        iconTypes.set( iconType, markerUrl );
                        slData.layerDetails[iconType] = markerUrl;
                    }
                }
            });
        }

        let startLayerArgs = null;

        iconTypes.forEach( (markerUrl, iconType) => {
            if ( markerUrl && slData.layerDetails[iconType] ) {
                const layerArgs = {
                    layerId: iconType,
                    map:     map
                };

                if ( iconType === 'start' ) {
                    if ( excludeStartFromCluster && map.getSource( 'start-location' ) ) {
                        layerArgs.dataSource = 'start-location';
                    }

                    layerArgs.onLayerAdded = function() {
                        map.off( 'mousedown', 'start', markers.event.onMouseDown );
                        map.off( 'touchstart', 'start', markers.event.onTouchStart );

                        map.on( 'mousedown', 'start', markers.event.onMouseDown );
                        map.on( 'touchstart', 'start', markers.event.onTouchStart );
                    };

                    startLayerArgs = layerArgs;
                } else {
                    layers.image.load( layerArgs );
                }
            }
        });

        if ( startLayerArgs ) {
            layers.image.load( startLayerArgs );
        }

        markers.loadActiveImage( map );

        if ( clusteringEnabled && ! map.getLayer( 'clusters' ) ) {
            markers.createCluster( map );
        }

        // Cluster layers are added after the start layer, so they render on top of
        // it. Move it back to the top of the stack when startOnTop is enabled.
        if ( config.markers.startOnTop && map.getLayer( 'start' ) ) {
            map.moveLayer( 'start' );
        }

        this.initKeyboardAccessibility( map );
    },

    /**
     * Beyond this many markers only cluster overlays are created.
     * 
     * @since 3.0.0
     */
    MARKER_OVERLAY_THRESHOLD: 100,

    /**
     * Whether the keyboard overlays exist yet. They're created on the
     * first Tab into the map.
     * 
     * @since 3.0.0
     */
    _keyboardOverlaysInitialized: false,

    /**
     * Initialize keyboard accessibility for the map.
     * The overlays are only created once the user tabs into the map.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {void}
     */
    initKeyboardAccessibility: function( map ) {
        const mapContainer = map.getContainer();

        // Per map, the geojson module is shared between all maps on the page.
        map._wpslKeyboardOverlaysInitialized = false;

        if ( ! mapContainer.hasAttribute( 'tabindex' ) ) {
            mapContainer.setAttribute( 'tabindex', '0' );
            mapContainer.setAttribute( 'role', 'region' );
            mapContainer.setAttribute( 'aria-label', wpslLabels.mapLabel || 'Store locator map' );
        }

        const handleMapFocus = () => {
            // A mouse press focuses the map too. The overlays are for
            // keyboard users, so leave them for the first Tab into the map.
            if ( map._wpslRestoringFocus || map._wpslPointerInitiated ) {
                return;
            }

            if ( ! map._wpslKeyboardOverlaysInitialized ) {
                map._wpslKeyboardOverlaysInitialized = true;
                this.createKeyboardOverlays( map );

                const state = map._wpslGeojsonData || geojson;
                const ref = state.active && state.active.features && state.active.features[0];
                if ( ref && ref.geometry && ref.geometry.coordinates ) {
                    const refLng = ref.geometry.coordinates[0];
                    const refLat = ref.geometry.coordinates[1];

                    const allOverlays = Array.from( mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' ) );

                    let closestOverlay = null;
                    let minDistance = Infinity;

                    allOverlays.forEach( overlay => {
                        const storeId = overlay.getAttribute( 'data-store-id' );
                        if ( storeId === '0' ) {
                            return;
                        }

                        const lng = parseFloat( overlay.getAttribute( 'data-lng' ) );
                        const lat = parseFloat( overlay.getAttribute( 'data-lat' ) );

                        if ( ! isNaN( lng ) && ! isNaN( lat ) ) {
                            const distance = ( lng - refLng ) ** 2 + ( lat - refLat ) ** 2;
                            if ( distance < minDistance ) {
                                minDistance = distance;
                                closestOverlay = overlay;
                            }
                        }
                    });

                    if ( closestOverlay ) {
                        closestOverlay.focus();
                    }
                }
            }
        };

        // Swap out the previous search's handler instead of stacking a new one,
        // the same treatment the move/moveend handlers below get with off().
        if ( map._wpslMapFocusHandler ) {
            mapContainer.removeEventListener( 'focus', map._wpslMapFocusHandler );
            mapContainer.removeEventListener( 'focusin', map._wpslMapFocusHandler );
        }

        map._wpslMapFocusHandler = handleMapFocus;

        mapContainer.addEventListener( 'focus', handleMapFocus );
        mapContainer.addEventListener( 'focusin', handleMapFocus );

        if ( ! map._wpslPointerDownBound ) {
            map._wpslPointerDownBound = true;

            mapContainer.addEventListener( 'pointerdown', () => {
                map._wpslPointerInitiated = true;

                setTimeout( () => {
                    map._wpslPointerInitiated = false;
                }, 0 );
            }, true );
        }

        if ( this._moveEndHandler ) {
            map.off( 'moveend', this._moveEndHandler );
        }

        if ( this._moveHandler ) {
            map.off( 'move', this._moveHandler );
        }

        this._moveEndHandler = () => {
            if ( map._wpslKeyboardOverlaysInitialized ) {
                this.updateKeyboardOverlays( map );
                this.updateOverlayTabindex( map );
            }
        };

        this._moveHandler = () => {
            const focused = document.activeElement;
            if ( ! focused ) {
                return;
            }

            if ( focused.classList.contains( 'wpsl-mapbox-cluster-overlay' ) ) {
                const radius = parseInt( focused.getAttribute( 'data-radius' ), 10 ) || 20;

                const lng = parseFloat( focused.getAttribute( 'data-lng' ) );
                const lat = parseFloat( focused.getAttribute( 'data-lat' ) );
                if ( ! isNaN( lng ) && ! isNaN( lat ) ) {
                    const point = map.project( [ lng, lat ] );
                    focused.style.left = `${ point.x - radius }px`;
                    focused.style.top  = `${ point.y - radius }px`;
                }
            } else if ( focused.classList.contains( 'wpsl-mapbox-marker-overlay' ) ) {
                const lng = parseFloat( focused.getAttribute( 'data-lng' ) );
                const lat = parseFloat( focused.getAttribute( 'data-lat' ) );
                if ( ! isNaN( lng ) && ! isNaN( lat ) ) {
                    // The offsets createMarkerOverlay() positioned the box with,
                    // so a custom marker's own size and anchor are kept.
                    const offsetX = parseFloat( focused.getAttribute( 'data-offset-x' ) ) || config.markers.scaledSize[0] / 2;
                    const offsetY = parseFloat( focused.getAttribute( 'data-offset-y' ) ) || config.markers.scaledSize[1];
                    const point   = map.project( [ lng, lat ] );

                    focused.style.left = `${ point.x - offsetX }px`;
                    focused.style.top  = `${ point.y - offsetY }px`;
                }
            }
        };

        map.on( 'moveend', this._moveEndHandler );
        map.on( 'move',    this._moveHandler );

        // Native marker click via the canvas symbol layers. The overlay boxes
        // are pointer-events:none, so mouse clicks reach the canvas and Mapbox
        // reports the marker feature here.
        if ( ! map._wpslNativeClickBound ) {
            map._wpslNativeClickBound = true;

            map.on( 'click', ( e ) => {
                const markerLayerIds = layers.markerLayerIds( map );

                if ( ! markerLayerIds.length ) {
                    return;
                }

                const hits = map.queryRenderedFeatures( e.point, { layers: markerLayerIds } );
                if ( ! hits.length || ! hits[0].properties ) {
                    return;
                }

                let storeId = hits[0].properties.id;
                if ( typeof storeId === 'string' ) {
                    storeId = parseInt( storeId, 10 );
                }

                if ( ! storeId ) {
                    const name = hits[0].properties.store;

                    if ( name ) {
                        if ( map._wpslPopup && map._wpslPopup.isOpen() ) {
                            map._wpslPopup.remove();
                        }

                        // As text: the name can be what the visitor typed as their start location.
                        const content = document.createElement( 'div' );
                        content.className   = 'wpsl-info-window';
                        content.textContent = helpers.decodeHtmlEntity( name );

                        infoWindow.create( content.outerHTML, hits[0].geometry.coordinates, map, hits[0].properties.icon );
                    }

                    return;
                }

                // recenter = false: only pan into view if the popup overflows
                // (Google Maps / Leaflet behavior). The map argument keeps the popup
                // on the clicked map when a page has multiple [wpsl_map] shortcodes.
                markers.triggerClick( storeId, false, map );
            });
        }
    },

    /**
     * Create invisible keyboard-accessible overlays for 
     * markers and clusters currently visible in the viewport.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {void}
     */
    createKeyboardOverlays: function( map ) {
        const mapContainer = map.getContainer();

        if ( ! map.getSource( 'locations' ) ) {
            return;
        }

        const existingOverlays = mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' );
        existingOverlays.forEach( overlay => overlay.remove() );

        const clustersEnabled = typeof config.markers.cluster === 'object' && map.getLayer( 'clusters' );
        const markersToCreate = this.getRenderedMarkerFeatures( map );
        if ( markersToCreate.length > this.MARKER_OVERLAY_THRESHOLD && clustersEnabled ) {
            this.createClusterOverlays( map );
            return;
        }

        const overlayState = map._wpslGeojsonData || geojson;

        const activeOrder = new Map();
        if ( overlayState.active && overlayState.active.features ) {
            overlayState.active.features.forEach( ( f, i ) => {
                if ( f.properties && f.properties.id !== undefined ) {
                    activeOrder.set( f.properties.id, i );
                }
            } );
        }

        markersToCreate.sort( ( a, b ) => {
            const orderA = activeOrder.has( a.properties.id ) ? activeOrder.get( a.properties.id ) : Infinity;
            const orderB = activeOrder.has( b.properties.id ) ? activeOrder.get( b.properties.id ) : Infinity;
            return orderA - orderB;
        } );

        markersToCreate.forEach( feature => {
            this.createMarkerOverlay( map, feature, mapContainer );
        });

        if ( clustersEnabled ) {
            this.createClusterOverlays( map );

            const ref = overlayState.active && overlayState.active.features && overlayState.active.features[0];
            if ( ref && ref.geometry && ref.geometry.coordinates ) {
                const storeRank = {};
                const storeItems = document.querySelectorAll( '#wpsl-stores li[data-store-id]' );
                for ( let i = 0; i < storeItems.length; i++ ) {
                    const storeId = storeItems[i].getAttribute( 'data-store-id' );
                    if ( storeId ) {
                        storeRank[ String( storeId ) ] = i;
                    }
                }

                const allOverlays = Array.from(
                    mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' )
                ).filter( el => el !== ref && el.getAttribute( 'data-store-id' ) !== '0' );

                const getOverlayRank = function( el ) {
                    if ( el.hasAttribute( 'data-store-id' ) ) {
                        const storeId = el.getAttribute( 'data-store-id' );
                        return storeRank[ String( storeId ) ] !== undefined ? storeRank[ String( storeId ) ] : 999999;
                    }
                    
                    if ( el.classList.contains( 'wpsl-mapbox-cluster-overlay' ) ) {
                        return 999999;
                    }
                    return 999999;
                };

                const refLng = ref.geometry.coordinates[0];
                const refLat = ref.geometry.coordinates[1];

                allOverlays.sort( ( a, b ) => {
                    const rankA = getOverlayRank( a );
                    const rankB = getOverlayRank( b );
                    
                    if ( rankA !== 999999 || rankB !== 999999 ) {
                        if ( rankA !== rankB ) {
                            return rankA - rankB;
                        }
                    }

                    const aLng  = parseFloat( a.getAttribute( 'data-lng' ) );
                    const aLat  = parseFloat( a.getAttribute( 'data-lat' ) );
                    const bLng  = parseFloat( b.getAttribute( 'data-lng' ) );
                    const bLat  = parseFloat( b.getAttribute( 'data-lat' ) );
                    const aDist = ( aLng - refLng ) ** 2 + ( aLat - refLat ) ** 2;
                    const bDist = ( bLng - refLng ) ** 2 + ( bLat - refLat ) ** 2;
                    return aDist - bDist;
                } );

                allOverlays.forEach( overlay => mapContainer.appendChild( overlay ) );
            }
        }

        this.updateOverlayTabindex( map );
    },

    /**
     * Get marker features that are currently visible in the viewport.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {array}  Array of visible marker features
     */
    getVisibleMarkerFeatures: function( map ) {
        const source = map.getSource( 'locations' );
        if ( ! source || ! source._data || ! source._data.features ) {
            return [];
        }
        
        const bounds = map.getBounds();
        
        const features = source._data.features;
        return features.filter( feature => {
            if ( ! feature.geometry || ! feature.geometry.coordinates ) {
                return false;
            }
            
            const coords = feature.geometry.coordinates;
            const lng = coords[0];
            const lat = coords[1];
            
            return lng >= bounds.getWest() && 
                   lng <= bounds.getEast() && 
                   lat >= bounds.getSouth() && 
                   lat <= bounds.getNorth();
        });
    },

    /**
     * Get marker features that are actually rendered on the map (not clustered).
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {array}  Array of rendered marker features
     */
    getRenderedMarkerFeatures: function( map ) {
        // Read from this map's own style: layers.active is shared between all
        // maps and reset by every search.
        const markerLayers = layers.markerLayerIds( map );

        if ( ! markerLayers.length ) {
            return [];
        }

        const renderedFeatures = map.queryRenderedFeatures( { layers: markerLayers } );
        const seenIds = new Set();
        const uniqueFeatures = [];

        renderedFeatures.forEach( feature => {
            const storeId = ( feature && feature.properties ) ? feature.properties.id : undefined;

            if ( storeId !== undefined && ! seenIds.has( storeId ) ) {
                seenIds.add( storeId );
                uniqueFeatures.push( feature );
            }
        });

        return uniqueFeatures;
    },

    /**
     * Create a single marker overlay button.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @param  {object} feature GeoJSON feature
     * @param  {HTMLElement} mapContainer Map container element
     * @return {void}
     */
    createMarkerOverlay: function( map, feature, mapContainer ) {
        const coords = feature.geometry.coordinates;
        const point = map.project( coords );
        const storeId = feature.properties.id;

        const button = document.createElement( 'button' );
        button.className = 'wpsl-mapbox-marker-overlay';
        button.setAttribute( 'data-store-id', storeId );
        button.setAttribute( 'data-lng', coords[0] );
        button.setAttribute( 'data-lat', coords[1] );
        button.setAttribute( 'tabindex', '0' );
        button.setAttribute( 'aria-label', helpers.decodeHtmlEntity( feature.properties.store ) );

        /**
         * The box has to cover the symbol on the canvas, so it takes the marker
         * artwork's own size and anchor. Both offsets are stored on the element
         * because the move handler only has the element to work from.
         */
        const custom = helpers.markers.getCustomMarkerGeometry( feature.properties.markerUrl );
        const width  = custom ? custom.width : config.markers.scaledSize[0];
        const height = custom ? custom.height : config.markers.scaledSize[1];

        // The anchor point, not width / 2: the flag anchors at its pole, so
        // its box extends right of the coordinate rather than around it.
        const offsetX = custom ? custom.anchor[0] : config.markers.scaledSize[0] / 2;
        const offsetY = custom ? custom.anchor[1] : config.markers.scaledSize[1];

        button.setAttribute( 'data-offset-x', offsetX );
        button.setAttribute( 'data-offset-y', offsetY );

        button.style.position = 'absolute';
        button.style.left = `${point.x - offsetX}px`;
        button.style.top = `${point.y - offsetY}px`;
        button.style.width = `${width}px`;
        button.style.height = `${height}px`;
        button.style.cursor = 'pointer';
        button.style.zIndex = '1';

        button.style.pointerEvents = 'none';

        if ( storeId === 0 ) {
            button.style.cursor = 'grab';
        }

        button.addEventListener( 'focus', () => {
            // Only recenter for keyboard focus
            if ( map._wpslRestoringFocus || map._wpslPointerInitiated ) {
                return;
            }

            // duration: 0 so the focus outline appears at its final position: an
            // animated panTo makes the box track the map frame-by-frame, see
            // _moveHandler.
            map.panTo( [ coords[0], coords[1] ], { duration: 0 } );
        } );

        button.addEventListener( 'click', () => {
            map._wpslLastFocusedMarkerId = storeId;

            let template;
            const latLng = [coords[0], coords[1]];

            // Basic [wpsl_map] maps have their own geojson state, see
            // triggerClick() in wpsl-markers.js.
            const state = map._wpslGeojsonData || geojson;

            geojson.icon.restore( state );

            // Only close a popup that's open on this map.
            if ( map._wpslPopup && map._wpslPopup.isOpen() ) {
                map._wpslPopup.remove();
            }

            if ( storeId === 0 ) {
                template = wpslLabels.startPoint;

                helpers.map.setCenter( { lng: latLng[0], lat: latLng[1] } );
                infoWindow.create( template, latLng, map, feature.properties.icon );
            } else {
                const activeFeature = state.active.features?.find(
                    f => f.properties?.id === storeId
                );
                const featureProperties = activeFeature?.properties ?? feature.properties;
                template = helpers.template.getInfoWindowTemplate( featureProperties );

                geojson.icon.setActive( storeId, state, map, () => {
                    // No force-centering (Google Maps / Leaflet): infoWindow.create()
                    // pans only if the popup would overflow the map viewport. The
                    // icon is read inside the callback because setActive() has just
                    // swapped it, and the popup clears the marker on screen now.
                    infoWindow.create( template, latLng, map, featureProperties.icon );
                    map.getSource( 'locations' ).setData( state.active );
                });
            }
        });

        button.addEventListener( 'keydown', (e) => {
            if ( e.key === 'Enter' || e.key === ' ' ) {
                e.preventDefault();

                map._wpslShouldReturnFocus = false;

                button.click();
                button.blur();
            } else if ( e.key === 'ArrowUp' || e.key === 'ArrowDown' ) {
                e.preventDefault();

                const allOverlays = Array.from( mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' ) );
                const currentIndex = allOverlays.indexOf( button );

                if ( currentIndex !== -1 ) {
                    let nextIndex;

                    if ( e.key === 'ArrowDown' ) {
                        nextIndex = ( currentIndex + 1 ) % allOverlays.length;
                    } else {
                        nextIndex = ( currentIndex - 1 + allOverlays.length ) % allOverlays.length;
                    }

                    allOverlays[nextIndex].focus();
                }
            } else if ( e.key === 'Tab' ) {
                geojson._wpslTabNavigating = true;
                const allOverlays = Array.from( mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' ) );
                let visibleOverlays = allOverlays.filter( o => o.getAttribute( 'tabindex' ) === '0' );

                if ( geojson._wpslLastExpandedClusterStoreIds && geojson._wpslLastExpandedClusterStoreIds.size > 0 ) {
                    const expandedClusterOverlays = [];
                    const clusterButton = [];
                    const otherOverlays = [];

                    visibleOverlays.forEach( overlay => {
                        const overlayStoreId = overlay.getAttribute( 'data-store-id' );
                        if ( geojson._wpslLastExpandedClusterStoreIds.has( overlayStoreId ) ) {
                            expandedClusterOverlays.push( overlay );
                        } else if ( overlay.classList.contains( 'wpsl-mapbox-cluster-overlay' ) &&
                                   overlay.getAttribute( 'data-cluster-id' ) === String( geojson._wpslLastExpandedClusterId ) ) {
                            clusterButton.push( overlay );
                        } else {
                            otherOverlays.push( overlay );
                        }
                    });

                    if ( expandedClusterOverlays.length > 0 ) {
                        visibleOverlays = [ ...expandedClusterOverlays, ...clusterButton, ...otherOverlays ];
                    }
                }

                setTimeout( () => {
                    geojson._wpslTabNavigating = false;
                    const nextFocused = document.activeElement;
                    if ( nextFocused && nextFocused.classList.contains( 'wpsl-mapbox-cluster-overlay' ) ) {
                        geojson._wpslLastExpandedClusterId = null;
                        geojson._wpslLastExpandedClusterStoreIds = new Set();
                    }
                }, 250 );
            }
        });

        mapContainer.appendChild( button );
    },

    /**
     * Update overlay positions when map moves.
     * Recreates the overlays for the visible markers, and restores focus to the
     * equivalent new button when an overlay had focus before the rebuild.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {void}
     */
    updateKeyboardOverlays: function( map ) {
        if ( this._wpslTabNavigating ) {
            return;
        }

        const focused      = document.activeElement;
        const mapContainer = map.getContainer();
        const isMarker     = focused && focused.classList.contains( 'wpsl-mapbox-marker-overlay' );
        const isCluster    = focused && focused.classList.contains( 'wpsl-mapbox-cluster-overlay' );
        const storeId      = isMarker  ? focused.getAttribute( 'data-store-id' )   : null;
        const clusterId    = isCluster ? focused.getAttribute( 'data-cluster-id' ) : null;

        this.createKeyboardOverlays( map );

        // _wpslRestoringFocus makes the button's own focus handler skip its panTo,
        // which would otherwise rebuild the overlays again in an endless loop.
        if ( storeId !== null || clusterId !== null ) {
            const selector = storeId !== null
                ? `.wpsl-mapbox-marker-overlay[data-store-id="${storeId}"]`
                : `.wpsl-mapbox-cluster-overlay[data-cluster-id="${clusterId}"]`;

            const newButton = mapContainer.querySelector( selector );

            if ( newButton ) {
                map._wpslRestoringFocus = true;
                newButton.focus();
                map._wpslRestoringFocus = false;
            }
        }
    },

    /**
     * Update tabindex for overlays based on viewport visibility, so only the
     * overlays inside the viewport can be reached with Tab.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {void}
     */
    updateOverlayTabindex: function( map ) {
        const mapContainer = map.getContainer();
        const bounds = map.getBounds();
        const allOverlays = Array.from( mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' ) );

        allOverlays.forEach( overlay => {
            const lng = parseFloat( overlay.getAttribute( 'data-lng' ) );
            const lat = parseFloat( overlay.getAttribute( 'data-lat' ) );
            const storeId = overlay.getAttribute( 'data-store-id' );

            // Start marker (store ID "0") should never be focusable via keyboard
            if ( storeId === '0' ) {
                overlay.setAttribute( 'tabindex', '-1' );
                return;
            }

            if ( isNaN( lng ) || isNaN( lat ) ) {
                overlay.setAttribute( 'tabindex', '-1' );
                return;
            }

            const isVisible = bounds.contains( [ lng, lat ] );
            overlay.setAttribute( 'tabindex', isVisible ? '0' : '-1' );
        } );
    },

    /**
     * Create keyboard-accessible overlays for cluster markers.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {void}
     */
    createClusterOverlays: function( map ) {
        const mapContainer = map.getContainer();
        
        const clusterFeatures = map.queryRenderedFeatures( { layers: ['clusters'] } );
        if ( ! clusterFeatures || ! clusterFeatures.length ) {
            return;
        }
        
        const existingClusterOverlays = mapContainer.querySelectorAll( '.wpsl-mapbox-cluster-overlay' );
        existingClusterOverlays.forEach( overlay => overlay.remove() );
        
        // The same cluster can appear in multiple tiles.
        const createdClusterIds = new Set();
        
        const getClusterRadius = ( pointCount ) => {
            const radiusConfig = config.markers.cluster.circle.radius;
            
            if ( pointCount >= radiusConfig[3] ) {
                return radiusConfig[4];
            } else if ( pointCount >= radiusConfig[1] ) {
                return radiusConfig[2];
            }
            
            return radiusConfig[0];
        };
        
        clusterFeatures.forEach( feature => {
            if ( ! feature.geometry || ! feature.geometry.coordinates ) {
                return;
            }
            
            const clusterId = feature.properties.cluster_id;
            
            if ( createdClusterIds.has( clusterId ) ) {
                return;
            }
            
            createdClusterIds.add( clusterId );
            
            const coords = feature.geometry.coordinates;
            const point = map.project( coords );
            const pointCount = feature.properties.point_count;
            const radius = getClusterRadius( pointCount );
            
            const button = document.createElement( 'button' );
            button.className = 'wpsl-mapbox-cluster-overlay';
            button.setAttribute( 'data-cluster-id', clusterId );
            button.setAttribute( 'data-lng', coords[0] );
            button.setAttribute( 'data-lat', coords[1] );
            button.setAttribute( 'data-radius', radius );
            button.setAttribute( 'tabindex', '0' );
            button.setAttribute( 'role', 'button' );
            button.setAttribute( 'aria-label', wpslLabels.clusterTitle.replace( '%d', pointCount ) );
            button.style.position = 'absolute';
            button.style.left = `${point.x - radius}px`;
            button.style.top = `${point.y - radius}px`;
            button.style.width = `${radius * 2}px`;
            button.style.height = `${radius * 2}px`;
            button.style.borderRadius = '50%';
            button.style.cursor = 'pointer';
            button.style.zIndex = '2';
            button.style.background = 'transparent';
            button.style.border = 'none';
            button.style.padding = '0';

            // Keyboard/screen-reader only, like the marker overlays. Mouse clicks
            // fall through to the canvas, where the 'clusters' layer click handler
            // expands the cluster. Catching the mousedown here would focus the
            // button, and the focus pan slides the box out from under the pointer
            // before mouseup, so the click never fires.
            button.style.pointerEvents = 'none';

            const expandCluster = () => {
                // Remove the focus outline before the zoom starts.
                button.blur();
                
                map.getSource( 'locations' ).getClusterExpansionZoom(
                    clusterId,
                    ( err, zoom ) => {
                        if ( err ) return;
                        
                        // Rebuild the overlays once the zoom animation has rendered.
                        const onIdle = async () => {
                            map.off( 'idle', onIdle );
                            geojson.createKeyboardOverlays( map );

                            // Focus the expanded cluster's own member closest to the
                            // search origin, so focus doesn't jump to another visible
                            // marker. _wpslRestoringFocus keeps the focus handler's
                            // panTo from shifting the map away from the cluster.
                            const source = map.getSource( 'locations' );
                            const storeIdsInCluster = new Set();

                            if ( source && source.getClusterChildren ) {
                                try {
                                    const children = await source.getClusterChildren( clusterId );
                                    if ( Array.isArray( children ) ) {
                                        children.forEach( child => {
                                            if ( child.properties && child.properties.id ) {
                                                storeIdsInCluster.add( String( child.properties.id ) );
                                            }
                                        });
                                    }
                                    // Track this expanded cluster for Tab navigation prioritization
                                    geojson._wpslLastExpandedClusterId = clusterId;
                                    geojson._wpslLastExpandedClusterStoreIds = storeIdsInCluster;
                                } catch ( e ) {
                                    // Fallback to empty set if cluster children cannot be fetched
                                }
                            }

                            let target = null;

                            if ( storeIdsInCluster.size > 0 ) {
                                const allOverlays = Array.from(
                                    mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay' )
                                );

                                const clusterOverlays = allOverlays.filter( overlay => {
                                    const storeId = overlay.getAttribute( 'data-store-id' );
                                    return storeId !== '0' && storeIdsInCluster.has( storeId );
                                });

                                // Closest to the search origin first.
                                const ref = geojson.active && geojson.active.features && geojson.active.features[0];
                                if ( ref && ref.geometry && ref.geometry.coordinates ) {
                                    const refLng = ref.geometry.coordinates[0];
                                    const refLat = ref.geometry.coordinates[1];

                                    clusterOverlays.sort( ( a, b ) => {
                                        const aLng = parseFloat( a.getAttribute( 'data-lng' ) );
                                        const aLat = parseFloat( a.getAttribute( 'data-lat' ) );
                                        const bLng = parseFloat( b.getAttribute( 'data-lng' ) );
                                        const bLat = parseFloat( b.getAttribute( 'data-lat' ) );

                                        const aDist = ( aLng - refLng ) ** 2 + ( aLat - refLat ) ** 2;
                                        const bDist = ( bLng - refLng ) ** 2 + ( bLat - refLat ) ** 2;

                                        return aDist - bDist;
                                    });
                                }

                                target = clusterOverlays[0] || null;
                            } else {
                                // Fallback: focus first visible marker overlay (skip start marker)
                                const allOverlays = Array.from(
                                    mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay' )
                                );
                                for ( const overlay of allOverlays ) {
                                    if ( overlay.getAttribute( 'data-store-id' ) !== '0' ) {
                                        target = overlay;
                                        break;
                                    }
                                }
                            }

                            if ( target ) {
                                map._wpslRestoringFocus = true;
                                target.focus();
                                map._wpslRestoringFocus = false;
                            }
                        };
                        
                        map.on( 'idle', onIdle );
                        
                        map.easeTo( {
                            center: coords,
                            zoom: zoom
                        } );
                    }
                );
            };
            
            button.addEventListener( 'click', expandCluster );

            // Pan to this cluster on keyboard focus only. A mouse press focuses the
            // overlay handleMapFocus() picks while the click is still in flight, and
            // panning then makes Mapbox resolve that click against the moved map,
            // opening the popup of whatever marker lands under the cursor.
            button.addEventListener( 'focus', () => {
                if ( ! map._wpslRestoringFocus && ! map._wpslPointerInitiated ) {
                    map.panTo( [ coords[0], coords[1] ] );
                }
            } );

            button.addEventListener( 'keydown', ( e ) => {
                if ( e.key === 'Enter' || e.key === ' ' ) {
                    e.preventDefault();
                    expandCluster();
                } else if ( e.key === 'ArrowUp' || e.key === 'ArrowDown' ) {
                    e.preventDefault();
                    
                    const allOverlays = Array.from( mapContainer.querySelectorAll( '.wpsl-mapbox-marker-overlay, .wpsl-mapbox-cluster-overlay' ) );
                    
                    const currentIndex = allOverlays.indexOf( button );
                    if ( currentIndex !== -1 ) {
                        let nextIndex;
                        
                        if ( e.key === 'ArrowDown' ) {
                            nextIndex = ( currentIndex + 1 ) % allOverlays.length;
                        } else {
                            nextIndex = ( currentIndex - 1 + allOverlays.length ) % allOverlays.length;
                        }
                        
                        allOverlays[nextIndex].focus();
                    }
                }
            } );
            
            mapContainer.appendChild( button );
        } );
    },

    /**
     * Create GeoJSON data for a single marker.
     *
     * @since  3.0.0
     * @param  {object} markerData Marker data
     * @return {object} GeoJSON FeatureCollection
     */
    create: function( markerData ) {
        const featureCollection = {
            'type': 'FeatureCollection',
            'features': [{
                'type': 'Feature',
                'geometry': {
                    'type': 'Point',
                    'coordinates': ''
                },
                'properties': {}
            }]
        };
    
        if ( typeof markerData.latLng === 'object' ) {
            if ( typeof markerData.latLng.lat === 'function' ) {
                featureCollection.features[0].geometry.coordinates = [ Number( markerData.latLng.lng() ), Number( markerData.latLng.lat() ) ];
            } else {
                featureCollection.features[0].geometry.coordinates = [ Number( markerData.latLng.lng ), Number( markerData.latLng.lat ) ];
            }
        } else {
            featureCollection.features[0].geometry.coordinates = [ Number( markerData.lng ), Number( markerData.lat ) ];
        }

        if ( typeof markerData.alternateMarkerUrl === 'string' ) {
            featureCollection.features[0].properties.icon = helpers.markers.iconToken( markerData.alternateMarkerUrl ).replace( /\.(png|jpg|jpeg|gif|svg)$/i, '' );
        } else if ( markerData['id'] === 0 ) {
            featureCollection.features[0].properties.icon = 'start';
        } else if ( markerData.markerUrl && markerData.markerUrl.indexOf( 'store.png' ) === -1 ) {
            featureCollection.features[0].properties.icon = helpers.markers.iconToken( markerData.markerUrl ).replace( /\.(png|jpg|jpeg|gif|svg)$/i, '' );
        } else {
            featureCollection.features[0].properties.icon = 'store';
        }

        if ( typeof slData.layerDetails[ featureCollection.features[0].properties.icon ] === 'undefined' ) {
            slData.layerDetails[ featureCollection.features[0].properties.icon ] = markerData.markerUrl;
        }

        for ( const property in markerData ) {
            if ( property === 'lat' || property === 'lng' ) {
                continue;
            }

            featureCollection.features[0].properties[property] = markerData[property];
        }

        return featureCollection;
    },

    /**
     * Icon manipulation methods.
     * 
     * @since 3.0.0
     */
    icon: {
        /**
         * Set active icon for a marker.
         *
         * @since  3.0.0
         * @param  {number} markerId Marker ID
         * @param  {object} geojsonInstance Reference to the geojson object
         * @param  {object} mapInstance Map instance
         * @param  {Function} callback Callback function
         * @return {void}
         */
        setActive: function( markerId, geojsonInstance = geojson, mapInstance = null, callback ) {
            if ( typeof mapInstance === 'function' ) {
                callback = mapInstance;
                mapInstance = null;
            }
    
            jQuery.each( geojsonInstance.active.features, function( i ) {
                if ( geojsonInstance.active.features[i].properties.id === markerId ) {
                    geojsonInstance.clickedMarkerId = i;

                    return false;
                }
            });
            
            const feature = geojsonInstance.active.features[ geojsonInstance.clickedMarkerId ];
            geojsonInstance.originalIcon = feature.properties.icon;

            let activeIcon;

            // Per-store active first, then per-category — the same order every
            // other provider resolves in.
            const ownActiveUrl = feature.properties.locationMarkerUrlActive || feature.properties.categoryMarkerUrlActive;

            if ( ownActiveUrl ) {
                activeIcon = helpers.markers.iconToken( ownActiveUrl ).replace( /\.(png|jpg|jpeg|gif|svg)$/i, '' );

                const map = mapInstance || slData.maps[0];

                if ( typeof slData.layerDetails[activeIcon] === 'undefined' ) {
                    slData.layerDetails[activeIcon] = ownActiveUrl;
                }

                if ( map && ! map.getLayer( activeIcon ) ) {
                    const layerArgs = {
                        layerId: activeIcon,
                        map: map,
                        onLayerAdded: function() {                                
                            feature.properties.icon = activeIcon;
                            
                            if ( typeof callback === 'function' ) {
                                callback();
                            }
                        }
                    };

                    layers.image.load( layerArgs );

                    return;
                }
            } else {
                activeIcon = 'active';
            }
            
            feature.properties.icon = activeIcon;
            
            if ( typeof callback === 'function' ) {
                callback();
            }
        },

        /**
         * Restore marker to original icon.
         *
         * @since  3.0.0
         * @param  {object} geojsonInstance Reference to the geojson object
         * @return {void}
         */
        restore: function( geojsonInstance = geojson ) {
            if ( geojsonInstance.clickedMarkerId !== '' && typeof geojsonInstance.clickedMarkerId !== 'undefined' ) {
                const feature = geojsonInstance.active.features[ geojsonInstance.clickedMarkerId ];
                                    
                if ( typeof geojsonInstance.originalIcon !== 'undefined' ) {
                    feature.properties.icon = geojsonInstance.originalIcon;
                    geojsonInstance.originalIcon = undefined;
                } else {
                    if ( feature.properties.markerUrl && feature.properties.markerUrl.indexOf( 'store.png' ) === -1 ) {
                        const restoredIcon = helpers.markers.iconToken( feature.properties.markerUrl ).replace( /\.(png|jpg|jpeg|gif|svg)$/i, '' );

                        feature.properties.icon = restoredIcon;
                    } else {
                        feature.properties.icon = 'store';
                    }
                }

                geojsonInstance.clickedMarkerId = '';
            }
        },

        /**
         * Restore all markers to their original icon state.
         *
         * @since  3.0.0
         * @param  {object} geojsonInstance Reference to the geojson object
         * @return {void}
         */
        restoreAll: function( geojsonInstance = geojson ) {
            if ( ! geojsonInstance.active || ! geojsonInstance.active.features ) {
                return;
            }

            geojsonInstance.active.features.forEach( function( feature ) {
                if ( ! feature.properties ) {
                    return;
                }
                
                if ( feature.properties.id === 0 ) {
                    feature.properties.icon = 'start';
                    return;
                }

                if ( feature.properties.markerUrl ) {
                    const markerUrl = feature.properties.markerUrl;
                    
                    if ( markerUrl.indexOf( 'store.png' ) === -1 ) {
                        feature.properties.icon = helpers.markers.iconToken( markerUrl ).replace( /\.(png|jpg|jpeg|gif|svg)$/i, '' );
                    } else {
                        feature.properties.icon = 'store';
                    }
                } else {
                    feature.properties.icon = 'store';
                }
            });

            geojsonInstance.clickedMarkerId = '';
            geojsonInstance.originalIcon = undefined;
        }
    }
};