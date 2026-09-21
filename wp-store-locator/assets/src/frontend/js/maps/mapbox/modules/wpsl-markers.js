import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { geojson } from './wpsl-geojson.js';
import { infoWindow } from './wpsl-infowindow.js';
import { api } from './wpsl-api.js';
import { layers } from './wpsl-layers.js';

/**
 * Mapbox markers functionality for WPSL frontend.
 * 
 * @since 3.0.0
 */
export const markers = {
    canvas: '',
    moved: false,

    /**
     * The DOM overlay marker used for the "bounce" hover effect, and the
     * requestAnimationFrame handle driving it.
     *
     * @since 3.0.0
     */
    _bounceMarker: null,
    _bounceFrame: null,

    /**
     * Get active markers.
     *
     * @since  3.0.0
     * @return {array} Array of active marker features
     */
    get active() {
        if ( geojson.active && geojson.active.features ) {
            return geojson.active.features;
        }
        
        return [];
    },

    /**
     * Add a marker to the map.
     *
     * @since  3.0.0
     * @param  {object} markerData Marker data including coordinates and properties
     * @param  {object} map        The map object
     * @return {void}
     */
    add: function( markerData, map ) {
        if ( markerData.latLng ) {
            if ( typeof markerData.latLng.lat === 'function' ) {
                markerData.lat = markerData.latLng.lat();
                markerData.lng = markerData.latLng.lng();
            } else {
                markerData.lat = markerData.latLng.lat;
                markerData.lng = markerData.latLng.lng;
            }
        }

        // setProperties() assigns id = 0 to the start marker, so it must run
        // BEFORE startVisibility(), which keys off id == 0 to detect it.
        markerData = wp.hooks.applyFilters( 'wpslMarkerData', helpers.markers.setProperties( markerData ) );

        const visibility = helpers.markers.startVisibility( markerData );

        if ( ! visibility ) {
            return;
        }

        markerData = geojson.create( markerData );

        geojson.add( markerData, map );
    },

    /**
     * Remove all markers from the map.
     *
     * @since  3.0.0
     * @return {void}
     */
    removeAll: function() {
        const map = slData.maps[0];

        for ( let key in layers.active ) {
            if ( map.getLayer( layers.active[key] ) ) {
                map.removeLayer( layers.active[key] );
            }

            if ( layers.image.exists( layers.active[key] + '-image', map ) ) {
                map.removeImage( layers.active[key] + '-image' );
            }

            layers.unbindHoverCursor( map, layers.active[key] );
        }

        layers.removeLabelImages( map );

        /*
         * Emptied, not removed: geojson.add() refills an existing source with
         * setData(). Removing one makes Mapbox GL update the style's terrain,
         * which throws on a style with terrain ( Mapbox Standard ) and stops
         * the search halfway, leaving the search button disabled.
         */
        for ( const sourceId of slData.activeSources ) {
            const source = map.getSource( sourceId );

            if ( source ) {
                source.setData( { type: 'FeatureCollection', features: [] } );
            }
        }

        layers.active = [];
        geojson.active = {};

        if ( map._wpslHoveredLayers ) {
            map._wpslHoveredLayers.clear();
            map.getCanvas().style.cursor = '';
        }
    },

    /**
     * Make sure all markers fit in the viewport.
     *
     * @since  3.0.0
     * @param  {object} bounds The optional marker bounds
     * @param  {object} map    The optional map object
     * @return {void}
     */
    fitBounds: function( bounds, map ) {
        map = helpers.getMapObj( map );

        let features;
        const source = map.getSource( 'locations' );

        if ( bounds === '' ) {
            bounds = undefined;
        }

        if ( typeof bounds === 'undefined' && source && source._data && source._data.features ) {
            features = source._data.features;
        } else if ( typeof bounds === 'undefined' && geojson.active ) {
            features = geojson.active.features;
        }

        const hasValidFeatures = typeof bounds === 'undefined' &&
            Array.isArray( features ) &&
            features.length > 0 &&
            features.every( f => f.type === 'Feature' && f.geometry );

        if ( hasValidFeatures ) {
            bounds = new mapboxgl.LngLatBounds();

            map.setCenter( [ features[0].geometry.coordinates[0], features[0].geometry.coordinates[1] ], {
                zoom: config.map.autoZoomLevel,
                pitch: 0,
                duration: 0
            });

            features.forEach( function( feature ) {
                if ( feature.geometry && feature.geometry.coordinates ) {
                    bounds.extend( [ feature.geometry.coordinates[0], feature.geometry.coordinates[1] ] );
                }
            });
        } else if ( bounds ) {
            bounds = new mapboxgl.LngLatBounds( bounds );
        } else {
            return;
        }

        const padding = 0.1;

        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();

        const lngDiff = ne.lng - sw.lng;
        const latDiff = ne.lat - sw.lat;

        const paddedSw = new mapboxgl.LngLat(
            Math.max( sw.lng - lngDiff * padding, -180 ),
            Math.max( sw.lat - latDiff * padding, -90 )
        );
        
        const paddedNe = new mapboxgl.LngLat(
            Math.min( ne.lng + lngDiff * padding, 180 ),
            Math.min( ne.lat + latDiff * padding, 90 )
        );

        const paddedBounds = new mapboxgl.LngLatBounds( paddedSw, paddedNe );

        map.fitBounds( paddedBounds, {
            padding: 50,
            maxZoom: config.map.autoZoomLevel
        });
    },

    /**
     * Return the coordinates from the start location marker.
     * 
     * @since  3.0.0
     * @return {object} The coordinates of the start marker
     */
    getStartCoordinates: function() {
        if ( ! geojson.active ) {
            return { lat: config.map.startLatLng.lat, lng: config.map.startLatLng.lng };
        }

        let features = null;
        
        if ( geojson.active.type === 'FeatureCollection' && Array.isArray( geojson.active.features ) ) {
            features = geojson.active.features;
        } else if ( Array.isArray( geojson.active ) ) {
            features = geojson.active;
        }
        
        if ( features ) {
            const startMarker = features.find(
                feature => feature.type === 'Feature' &&
                           feature.properties &&
                           feature.properties.icon === 'start'
            );
            
            if ( startMarker ) {
                if ( startMarker.properties.latLng ) {
                    return {
                        lat: startMarker.properties.latLng.lat,
                        lng: startMarker.properties.latLng.lng
                    };
                } else if ( startMarker.geometry && startMarker.geometry.coordinates ) {
                    return {
                        lng: startMarker.geometry.coordinates[0],
                        lat: startMarker.geometry.coordinates[1]
                    };
                }
            }

            // No explicit start marker ( e.g. a category-only search ). Fall back
            // to the first active marker so the map centers on a result instead of
            // the default start location, matching Google Maps and OSM.
            const firstMarker = features.find(
                feature => feature.type === 'Feature' &&
                           feature.geometry &&
                           Array.isArray( feature.geometry.coordinates )
            );

            if ( firstMarker ) {
                return {
                    lng: firstMarker.geometry.coordinates[0],
                    lat: firstMarker.geometry.coordinates[1]
                };
            }
        }

        return {
            lat: config.map.startLatLng.lat,
            lng: config.map.startLatLng.lng
        };
    },

    /**
     * Marker event handlers for drag interactions.
     * 
     * @since 3.0.0
     */
    event: {
        /**
         * Update the marker coordinates when it's being moved around.
         *
         * @since  3.0.0
         * @param  {object} e Event object
         * @return {void}
         */
        onMove: function( e ) {
            const coords = e.lngLat;

            markers.moved = true;
    
            infoWindow.close();

            slData.canvas[0].style.cursor = 'grabbing';

            geojson.active.features[0].geometry.coordinates = [coords.lng, coords.lat];
            slData.maps[0].getSource( 'locations' ).setData( geojson.active );
        },

        /**
         * Handle mouse down event on marker.
         *
         * @since  3.0.0
         * @param  {object} e Event object
         * @return {void}
         */
        onMouseDown: function( e ) {
            const canvas = slData.canvas[0];

            e.preventDefault();

            if ( slData.directions.active ) {
                return;
            }

            canvas.style.cursor = 'grab';

            slData.maps[0].on( 'mousemove', markers.event.onMove );
            slData.maps[0].once( 'mouseup', markers.event.onUp );
        },

        /**
         * Fires when the marker is released.
         *
         * @since  3.0.0
         * @param  {object} e Event object
         * @return {void}
         */
        onUp: function( e ) {
            const coords = e.lngLat;

            slData.canvas[0].style.cursor = '';

            slData.maps[0].off( 'mousemove', markers.event.onMove );
            slData.maps[0].off( 'touchmove', markers.event.onMove );

            slData.setSearchInput = true;
    
            if ( markers.moved ) {
                const args = {
                    id: 0,
                    lat: coords.lat,
                    lng: coords.lng,
                    markerDragged: true
                };

                markers.moved = false;
                markers.removeAll();
                markers.add( args, slData.maps[0] );

                helpers.map.setCenter( { lng: args.lng, lat: args.lat } );

                // A newer search may start before the address comes back, see search.current().
                api.geocoding.reverse( args, search.current( function() {
                    search.run( args );
                }) );
            }
        },

        /**
         * Handle touch start event on marker.
         *
         * @since  3.0.0
         * @param  {object} e Event object
         * @return {void}
         */
        onTouchStart: function( e ) {
            if ( e.points.length !== 1 ) return;

            e.preventDefault();

            slData.maps[0].on( 'touchmove', markers.event.onMove );
            slData.maps[0].once( 'touchend', markers.event.onUp );
        }
    },

    /**
     * Run a callback for every marker symbol layer (skipping the cluster
     * layers) that's currently present on the map.
     *
     * @since  3.0.0
     * @param  {object}   map      The Mapbox map instance
     * @param  {Function} callback Receives the layer id
     * @return {void}
     */
    eachMarkerLayer: function( map, callback ) {
        layers.markerLayerIds( map ).forEach( callback );
    },

    /**
     * Resolve the active marker image URL for a geojson feature.
     *
     * Store-specific first, then category-specific, then the globally configured
     * active marker. Returns an empty string when none is configured, so callers
     * can fall back to the regular icon.
     *
     * @since  3.0.0
     * @param  {object} feature The geojson feature of the hovered store
     * @return {string} The active marker URL, or an empty string
     */
    getActiveMarkerUrl: function( feature ) {
        if ( feature.properties && feature.properties.locationMarkerUrlActive ) {
            return feature.properties.locationMarkerUrlActive;
        }

        if ( feature.properties && feature.properties.categoryMarkerUrlActive ) {
            return feature.properties.categoryMarkerUrlActive;
        }

        if ( typeof config.markers.active === 'undefined' ) {
            return '';
        }

        const settings = helpers.markers.getSettings();

        return helpers.markers.resolveMarkerSrc( config.markers.active, settings.url );
    },

    /**
     * Bounce the marker that matches the passed store id.
     *
     * @since  3.0.0
     * @param  {number} storeId The store id of the marker that should bounce
     * @param  {string} status  'start' to begin bouncing, anything else to stop
     * @return {void}
     */
    bounce: function( storeId, status ) {
        const map = slData.maps[0];

        // Always stop a previous bounce and reveal every symbol again.
        if ( this._bounceFrame ) {
            cancelAnimationFrame( this._bounceFrame );
            this._bounceFrame = null;
        }

        if ( this._bounceMarker ) {
            this._bounceMarker.remove();
            this._bounceMarker = null;
        }

        this.eachMarkerLayer( map, function( layerId ) {
            map.setPaintProperty( layerId, 'icon-opacity', 1 );
        });

        if ( status !== 'start' ) {
            return;
        }

        let target = null;
        const features = geojson.active.features || [];

        for ( let i = 0, len = features.length; i < len; i++ ) {
            if ( features[i] && features[i].properties && features[i].properties.id === storeId ) {
                target = features[i];
                break;
            }
        }

        if ( ! target ) {
            return;
        }

        const coords = target.geometry.coordinates.slice();

        // Bounce with the active marker image, falling back to the regular one.
        let iconUrl = target.properties.markerUrl;

        if ( helpers.markers.maybeSetActivemarker( storeId, !! ( target.properties.locationMarkerUrlActive || target.properties.categoryMarkerUrlActive ) ) ) {
            iconUrl = this.getActiveMarkerUrl( target ) || iconUrl;
        }

        // The layer's artwork is shared and unlabelled; the symbol gets its label from drawLabelImage(), this copy needs it baked in.
        if ( target.properties.marker_label && layers.labelsOn() ) {
            iconUrl = window.wpslMarkerLabel.apply( iconUrl, String( target.properties.marker_label ) );
        }

        // The bouncing copy has to match the symbol it replaces, so it takes the
        // custom marker's own size and anchor where there is one.
        const custom = helpers.markers.getCustomMarkerGeometry( iconUrl );
        const width  = custom ? custom.width : config.markers.scaledSize[0];
        const height = custom ? custom.height : config.markers.scaledSize[1];

        const image = document.createElement( 'img' );
        image.src                = iconUrl;
        image.alt                = '';
        image.style.width        = width + 'px';
        image.style.height       = height + 'px';
        image.style.display      = 'block';
        image.style.pointerEvents = 'none';

        this._bounceMarker = new mapboxgl.Marker({
            element: image,
            anchor:  'bottom',
            // Half the height for a centre-anchored shape, the pole's nudge
            // for a flag, zero for a pin that already ends at its tip.
            offset:  custom ? custom.offset : [ 0, 0 ]
        }).setLngLat( coords ).addTo( map );

        // Hide the underlying symbol for this store so it doesn't double up.
        this.eachMarkerLayer( map, function( layerId ) {
            map.setPaintProperty( layerId, 'icon-opacity', [
                'case', [ '==', [ 'get', 'id' ], storeId ], 0, 1
            ] );
        });

        const self      = this;
        const height_px = config.markers.bounceHeight || 12;
        const period    = config.markers.bouncePeriod || 500;
        const startTime = ( typeof performance !== 'undefined' ) ? performance.now() : Date.now();

        const step = function( now ) {
            // Rests at offset 0 and only lifts upward; abs(sin) touches down
            // each cycle, matching the Google Maps bounce.
            const offset = -Math.abs( Math.sin( ( ( now - startTime ) / period ) * Math.PI ) ) * height_px;
            const point  = map.project( coords );

            point.y += offset;

            self._bounceMarker.setLngLat( map.unproject( point ) );
            self._bounceFrame = requestAnimationFrame( step );
        };

        self._bounceFrame = requestAnimationFrame( step );
    },

    /**
     * Check what effect we need to trigger once a user hovers over the store
     * list. Either bounce the corresponding marker up and down or ignore it.
     *
     * @since  3.0.0
     * @return {void}
     */
    checkMouseOverEvent: function() {
        if ( config.ux.markerEffect === 'bounce' ) {
            jQuery( '#wpsl-stores' ).on( 'mouseenter', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                this.bounce( storeId, 'start' );
            }.bind( this ) );

            jQuery( '#wpsl-stores' ).on( 'mouseleave', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                this.bounce( storeId, 'stop' );
            }.bind( this ) );
        }
    },

    /**
     * Trigger a click event on marker that matches with the passed ID.
     *
     * @since  3.0.0
     * @param  {number}  storeId  ID of the marker that's we need to trigger the click event for
     * @param  {boolean} recenter Whether to center the map on the marker. The store
     *                            list uses true; a click on the marker itself uses
     *                            false so the map only pans when the popup would
     *                            overflow (Google Maps / Leaflet).
     * @param  {object}  map      The map the marker is on. Defaults to the first map;
     *                            basic [wpsl_map] maps pass their own instance.
     * @return {void}
     */
    triggerClick: function( storeId, recenter = true, map ) {
        map = map || slData.maps[0];

        // Basic [wpsl_map] maps have their own geojson state, the store
        // locator map uses the shared state that's replaced by every search.
        const state = map._wpslGeojsonData || geojson;
        const features = ( state.active && state.active.features ) || [];
        const len = features.length;

        for ( let i = 0; i < len; i++ ) {
            const feature = features[i];

            if ( feature && feature.properties && feature.properties.id === storeId ) {
                const latLng = [ feature.geometry.coordinates[0], feature.geometry.coordinates[1] ];
                const template = helpers.template.getInfoWindowTemplate( feature.properties );

                geojson.icon.restore( state );

                // Only close a popup that's open on this map: a click on map B
                // must not close the popup on map A.
                if ( map._wpslPopup && map._wpslPopup.isOpen() ) {
                    map._wpslPopup.remove();
                }

                geojson.icon.setActive( feature.properties.id, state, map, () => {
                    if ( recenter ) {
                        helpers.map.setCenter( { lng: latLng[0], lat: latLng[1] } );
                    }

                    // Read inside the callback: setActive() has just swapped the
                    // icon, and the popup clears the marker that's on screen now.
                    infoWindow.create( template, latLng, map, feature.properties.icon );

                    map.getSource( 'locations' ).setData( state.active );
                });

                break;
            }
        }
    },

    /**
     * Remove cluster layers from the map.
     *
     * @since  3.0.0
     * @param  {object} map      The Mapbox map instance
     * @param  {array}  layerIds Array of layer IDs to remove
     * @return {void}
     */
    removeClusterLayers: function( map, layerIds ) {
        layerIds.forEach( function( layerId ) {
            if ( map.getLayer( layerId ) ) {
                map.removeLayer( layerId );
            }
        });
    },

    /**
     * Merge markers that are nearby into a cluster.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map instance
     * @return {void}
     */
    createCluster: function( map ) {
        this.removeClusterLayers( map, ['cluster-count', 'clusters', 'unclustered-point'] );
            
        map.addLayer({
            id: 'clusters',
            type: 'circle',
            source: 'locations',
            filter: ['has', 'point_count'],
            paint: {
                'circle-color': [
                    'step',
                    ['get', 'point_count'],
                    config.markers.cluster.circle.color[0],
                    config.markers.cluster.circle.color[1],
                    config.markers.cluster.circle.color[2],
                    config.markers.cluster.circle.color[3],
                    config.markers.cluster.circle.color[4]
                ],
                'circle-radius': [
                    'step',
                    ['get', 'point_count'],
                    config.markers.cluster.circle.radius[0],
                    config.markers.cluster.circle.radius[1],
                    config.markers.cluster.circle.radius[2],
                    config.markers.cluster.circle.radius[3],
                    config.markers.cluster.circle.radius[4]
                ]
            }
        });

        if ( ! layers.active.includes( 'clusters' ) ) {
            layers.active.push( 'clusters' );
        }

        // The cluster overlay boxes are pointer-events:none, so the hover
        // cursor has to come from the canvas layer, like the marker layers.
        layers.bindHoverCursor( map, 'clusters' );

        map.addLayer({
            id: 'cluster-count',
            type: 'symbol',
            source: 'locations',
            filter: ['has', 'point_count'],
            layout: {
                'text-field': ['get', 'point_count_abbreviated'],
                'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
                'text-size': 12,

                // The marker icons take part in symbol collision even with
                // icon-allow-overlap on, so a marker near a cluster ( or two
                // close clusters ) would silently hide the count label.
                'text-allow-overlap': true,
                'text-ignore-placement': true
            }
        });

        if ( ! layers.active.includes( 'cluster-count' ) ) {
            layers.active.push( 'cluster-count' );
        }

        // Bound once per map ( see bindHoverCursor in wpsl-layers.js ): GL JS
        // keeps layer-scoped listeners on the map when the layer is removed, so
        // re-binding on every search would stack a click handler per search.
        if ( ! map._wpslClusterClickBound ) {
            map._wpslClusterClickBound = true;

            map.on( 'click', 'clusters', ( e ) => {
                const features = map.queryRenderedFeatures( e.point, {
                    layers: ['clusters']
                });

                const clusterId = features[0].properties.cluster_id;

                map.getSource( 'locations' ).getClusterExpansionZoom(
                    clusterId,
                    ( err, zoom ) => {
                        if (err) return;

                        map.easeTo({
                            center: features[0].geometry.coordinates,
                            zoom: zoom
                        });
                    }
                );
            });
        }
    },

    /**
     * Load the marker image used with the active ( clicked ) state.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map instance
     * @return {void}
     */
    loadActiveImage: function( map ) {
        if ( typeof config.markers.active === 'undefined' || layers.image.exists( 'active-image', map ) ) {
            return;
        }

        const markerSettings = helpers.markers.getSettings();

        if ( typeof slData.layerDetails.start === 'undefined' || !slData.layerDetails.start ) {
            slData.layerDetails.start = helpers.markers.resolveMarkerSrc( config.markers.start, markerSettings.url );
        }

        /**
         * Resolve from the config instead of swapping the filename in the start
         * marker's URL: both are built from the same marker directory URL, and a
         * custom marker is a complete data URI with no filename to swap out.
         */
        slData.layerDetails['active'] = helpers.markers.resolveMarkerSrc( config.markers.active, markerSettings.url );

        layers.image.load({
            layerId: 'active',
            map: map
        });
    }
};