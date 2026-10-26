import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { markerGroups } from '../../../modules/wpsl-marker-groups.js';
import { groupWindow } from '../../../modules/wpsl-group-window.js';
import { geojson } from './wpsl-geojson.js';
import { infoWindow } from './wpsl-infowindow.js';
import { api } from './wpsl-api.js';
import { layers } from './wpsl-layers.js';

/**
 * The number of locations in a cluster. A point shared by the locations at
 * one address counts once for each, see geojson.withLocationCount().
 *
 * @since 3.1.1
 */
const LOCATION_COUNT = [ 'coalesce', [ 'get', 'storeCount' ], [ 'get', 'point_count' ] ];

/**
 * The cluster label: the location count, abbreviated from 1000 on ( 1.2k ).
 *
 * @since 3.1.1
 */
const LOCATION_COUNT_TEXT = [
    'case',
    [ 'has', 'storeCount' ],
    [
        'case',
        [ '>=', [ 'get', 'storeCount' ], 1000 ],
        [ 'concat', [ 'to-string', [ '/', [ 'round', [ '/', [ 'get', 'storeCount' ], 100 ] ], 10 ] ], 'k' ],
        [ 'to-string', [ 'get', 'storeCount' ] ]
    ],
    [ 'get', 'point_count_abbreviated' ]
];

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

        markerGroups.clear( 0 );

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
     * @since  3.1.1 The options parameter.
     * @param  {object} bounds  The optional marker bounds
     * @param  {object} map     The optional map object
     * @param  {object} options Optional Mapbox fitBounds options, such as duration: 0
     * @return {void}
     */
    fitBounds: function( bounds, map, options = {} ) {
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

        map.fitBounds( paddedBounds, Object.assign( {
            padding: 50,
            maxZoom: config.map.autoZoomLevel
        }, options || {} ) );
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
     * Whether a location's marker is drawn on the map now, and not hidden in
     * a cluster ( or outside the view ).
     *
     * @since  3.1.1
     * @param  {object} map     Mapbox map object
     * @param  {number} storeId The location ID
     * @return {boolean}
     */
    isDrawn: function( map, storeId ) {
        const layerIds = layers.markerLayerIds( map );

        if ( ! layerIds.length ) {
            return false;
        }

        return map.queryRenderedFeatures( { layers: layerIds } ).some( ( feature ) => {
            return feature.properties && String( feature.properties.id ) === String( storeId );
        });
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

        // A location at a shared address bounces the point it shares.
        storeId = markerGroups.leadId( storeId, 0 );

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

        // A marker inside a cluster isn't drawn, so like on the other map services
        // it doesn't bounce: the copy would float on its own above the cluster.
        if ( map._wpslClusterEnabled && ! this.isDrawn( map, storeId ) ) {
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
     * Whether a store is drawn as its own point on a map, not inside a cluster.
     *
     * Clusters carry no 'id' property, so only an unclustered point matches.
     *
     * @since  3.1.1
     * @param  {number} storeId The store ID.
     * @param  {object} map     The Mapbox map instance.
     * @return {boolean} False as well when the source can't be queried.
     */
    isUnclustered: function( storeId, map ) {
        try {
            if ( ! map.getSource( 'locations' ) || typeof map.querySourceFeatures !== 'function' ) {
                return false;
            }

            return map.querySourceFeatures( 'locations', { filter: [ '==', [ 'get', 'id' ], storeId ] } ).length > 0;
        } catch ( error ) {
            return false;
        }
    },

    /**
     * Ease to a store on one map ( to options.zoom when given ), zoom in just
     * past the cluster limit when it's inside a cluster, then open it like a
     * click. Used by wpsl.api.focusLocation().
     *
     * @since  3.1.1
     * @param  {number} storeId  The store ID.
     * @param  {number} mapIndex The map index.
     * @param  {object} options  options.zoom: zoom to at least this level.
     * @return {boolean} Whether the store was found.
     */
    focus: function( storeId, mapIndex, options = {} ) {
        const map = slData.maps[ mapIndex ];

        if ( ! map ) {
            return false;
        }

        const state    = map._wpslGeojsonData || geojson;
        const features = ( state.active && state.active.features ) || [];
        const pointId  = map === slData.maps[0] ? markerGroups.leadId( storeId, 0 ) : storeId;
        const feature  = features.find( ( item ) => item && item.properties && item.properties.id === pointId );

        if ( ! feature ) {
            return false;
        }

        const [ lng, lat ] = feature.geometry.coordinates;

        // A newer focus on this map makes the callbacks of this one stale.
        const token = {};

        map._wpslFocusToken = token;

        // Run a step unless a newer focus took over; never throw from a map event.
        const step = ( callback ) => () => {
            if ( map._wpslFocusToken !== token ) {
                return;
            }

            try {
                callback();
            } catch ( error ) {
                // Swallowed: focusLocation() must never throw, not even
                // later from a map event, and a failed focus just stays put.
            }
        };

        const open = step( () => this.triggerClick( storeId, false, map ) );

        const moveTo = ( zoom, done ) => {
            // easeTo() stops a running ease ( inertia, fitBounds, an earlier
            // focus ) and that fires 'moveend' at once, so stop it first.
            map.stop();

            const center = map.getCenter();

            // easeTo() to where the map already is may not fire 'moveend'.
            if ( Math.abs( center.lng - lng ) < 1e-7 && Math.abs( center.lat - lat ) < 1e-7 && map.getZoom() === zoom ) {
                done();
                return;
            }

            map.once( 'moveend', done );
            map.easeTo( { center: [ lng, lat ], zoom } );
        };

        const reveal = step( () => {
            if ( ! map._wpslClusterEnabled || ! config.markers.cluster || this.isUnclustered( pointId, map ) ) {
                open();
            } else {
                moveTo( Math.max( map.getZoom(), Number( config.markers.cluster.maxZoom ) + 1 ), open );
            }
        } );

        /*
         * Ease at the current zoom, or to options.zoom ( a minimum: a point
         * that's still clustered there gets revealed too ). Then check the
         * point; a clustering map waits for the source to have its tiles.
         */
        const zoom = typeof options.zoom === 'number' ? options.zoom : map.getZoom();

        moveTo( zoom, step( () => {
            if ( map._wpslClusterEnabled && map.getSource( 'locations' ) && ! map.isSourceLoaded( 'locations' ) ) {
                map.once( 'idle', reveal );
            } else {
                reveal();
            }
        } ) );

        return true;
    },

    /**
     * Close the open popup on one map, as a click on the map would. Its
     * 'close' handler restores the marker icon. Used by wpsl.api.closePopups().
     *
     * @since  3.1.1
     * @param  {number} mapIndex The map index.
     * @return {boolean} Whether the map exists.
     */
    closePopups: function( mapIndex ) {
        const map = slData.maps[ mapIndex ];

        if ( ! map ) {
            return false;
        }

        // Only this map's popup: closing map B must not close the popup on map A.
        if ( map._wpslPopup && map._wpslPopup.isOpen() ) {
            map._wpslPopup.remove();
        }

        return true;
    },

    /**
     * Show only the passed locations on a map, or all of them again, see
     * wpsl.api.showLocations(). The 'locations' source gets the matching
     * features, so its clusters count only those.
     *
     * A marker's active icon is swapped in the map's own copy of its
     * locations, which is then put back with setData() ( a popup opening or
     * closing ). So the source keeps the filter: the same locations again are
     * filtered again, other locations ( a search ) clear the filter and show
     * all of them.
     *
     * @since  3.1.1
     * @param  {array|null} ids      Location ids, null for all.
     * @param  {number}     mapIndex The map index.
     * @param  {object}     options  options.fitBounds: fit the map to the shown markers.
     * @return {boolean} Whether the map exists.
     */
    showLocations: function( ids, mapIndex, options = {} ) {
        const map    = slData.maps[ mapIndex ];
        const source = map && typeof map.getSource === 'function' ? map.getSource( 'locations' ) : null;

        if ( ! source || ! source._data || ! Array.isArray( source._data.features ) ) {
            return false;
        }

        if ( ! source._wpslSetData ) {
            const setData = source.setData.bind( source );

            source._wpslSetData = setData;
            source._wpslAll     = source._data;
            source._wpslAllKey  = markers.locationsKey( source._data );
            source._wpslIds     = null;

            source.setData = function( data ) {
                const key = markers.locationsKey( data );

                if ( key !== source._wpslAllKey ) {
                    source._wpslIds = null;
                }

                source._wpslAll    = data;
                source._wpslAllKey = key;

                return setData( source._wpslIds ? markers.filterLocations( data, source._wpslIds, mapIndex ) : data );
            };
        }

        source._wpslIds = ids;

        markerGroups.setShown( ids, mapIndex );
        this.closePopups( mapIndex );
        source.setData( source._wpslAll );

        // Without animation: a focusLocation() call right after it would stop the animation halfway.
        if ( options.fitBounds ) {
            this.fitBounds( '', map, { duration: 0 } );
        }

        return true;
    },

    /**
     * Which locations a collection holds, regardless of their icons or
     * order: their sorted ids. The start location ( id 0 ) doesn't count.
     *
     * @since  3.1.1
     * @param  {object} collection The GeoJSON FeatureCollection.
     * @return {string}
     */
    locationsKey: function( collection ) {
        if ( ! collection || ! Array.isArray( collection.features ) ) {
            return '';
        }

        return collection.features
            .map( ( feature ) => ( feature.properties ? Number( feature.properties.id ) : 0 ) )
            .filter( Boolean )
            .sort( ( a, b ) => a - b )
            .join( ',' );
    },

    /**
     * A location collection with only the features of the passed locations,
     * and those without a location id ( the start location ).
     *
     * @since  3.1.1
     * @param  {object} collection The GeoJSON FeatureCollection.
     * @param  {array}  ids        Location ids.
     * @param  {number} mapIndex   The map index.
     * @return {object}
     */
    filterLocations: function( collection, ids, mapIndex ) {
        if ( ! collection || ! Array.isArray( collection.features ) ) {
            return collection;
        }

        return Object.assign( {}, collection, {
            features: collection.features.filter( ( feature ) => {
                const id = feature.properties ? feature.properties.id : 0;

                return ! id || markerGroups.showsAny( id, ids, mapIndex );
            }).map( ( feature ) => {

                // A shared point counts only its shown locations in a cluster. A copy,
                // the full collection keeps its size for when every location shows again.
                if ( ! feature.properties || ! feature.properties.groupSize ) {
                    return feature;
                }

                const size = markerGroups.shownOf( markerGroups.members( feature.properties.id, mapIndex ) || [], mapIndex ).length;

                return Object.assign( {}, feature, { properties: Object.assign( {}, feature.properties, { groupSize: size } ) } );
            })
        });
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
     * @param  {boolean} onMarker Whether the click was on the marker itself. A shared
     *                            point then opens on its first location ( or the list ),
     *                            otherwise on the passed location.
     * @return {void}
     */
    triggerClick: function( storeId, recenter = true, map, onMarker = false ) {
        map = map || slData.maps[0];

        // Basic [wpsl_map] maps have their own geojson state, the store
        // locator map uses the shared state that's replaced by every search.
        const state = map._wpslGeojsonData || geojson;
        const features = ( state.active && state.active.features ) || [];
        const len = features.length;

        // A location at a shared address is on the point it shares.
        const isLocator = map === slData.maps[0];
        const pointId   = isLocator ? markerGroups.leadId( storeId, 0 ) : storeId;

        for ( let i = 0; i < len; i++ ) {
            const feature = features[i];

            if ( feature && feature.properties && feature.properties.id === pointId ) {
                const latLng = [ feature.geometry.coordinates[0], feature.geometry.coordinates[1] ];
                const content = this.popupContent( feature, latLng, map, ( onMarker || ! isLocator ) ? null : markerGroups.shownIndexOf( storeId, 0 ) );

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
                    infoWindow.create( content.template, latLng, map, feature.properties.icon );
                    this.bindGroupWindow( content, feature, map );

                    /**
                     * The same action the other providers fire on a marker click.
                     * Mapbox has no marker object, so the GeoJSON feature takes
                     * its place and its properties the marker data.
                     *
                     * @since 3.1.1
                     * @param {object} feature    The store's GeoJSON point feature.
                     * @param {object} properties feature.properties, the store data ( id, store, ... ).
                     * @param {object} map        The Mapbox map instance the store was opened on.
                     */
                    if ( window.wp && window.wp.hooks ) {
                        window.wp.hooks.doAction( 'wpslMarkerClicked', feature, content.shown, map );
                    }

                    map.getSource( 'locations' ).setData( state.active );
                    geojson.syncRouteSource( map, state );
                });

                break;
            }
        }
    },

    /**
     * The popup of a point: one location, or every location at the address
     * when the point is shared.
     *
     * @since  3.1.1
     * @param  {object}      feature    The point's GeoJSON feature
     * @param  {array}       latLng     The point's [ lng, lat ]
     * @param  {object}      map        The Mapbox map instance
     * @param  {number|null} startIndex The location to open on, null for a click on the point
     * @return {object} { template, stores, shown }: stores is null for a single location, shown is the data of the location on view
     */
    popupContent: function( feature, latLng, map, startIndex = null ) {

        // The feature's properties have no coordinates, but the directions link needs them.
        const own     = Object.assign( { lat: latLng[1], lng: latLng[0] }, feature.properties );
        const members = map === slData.maps[0] ? markerGroups.members( feature.properties.id, 0 ) : null;

        if ( ! members || String( members[0].id ) !== String( feature.properties.id ) ) {
            return { template: helpers.template.getInfoWindowTemplate( own ), stores: null, shown: feature.properties };
        }

        // Only the locations wpsl.api.showLocations() left on the map.
        const stores = markerGroups.shownOf( [ own ].concat( members.slice( 1 ) ), 0 );

        if ( stores.length < 2 ) {
            const store = stores.length ? stores[0] : own;

            return { template: helpers.template.getInfoWindowTemplate( store ), stores: null, shown: store };
        }

        return {
            template: groupWindow.template( stores, startIndex ),
            stores: stores,
            shown: stores[ startIndex || 0 ]
        };
    },

    /**
     * Make a shared point's popup work once it is on the map.
     *
     * @since  3.1.1
     * @param  {object} content What popupContent() returned
     * @param  {object} feature The point's GeoJSON feature
     * @param  {object} map     The Mapbox map instance
     * @return {void}
     */
    bindGroupWindow: function( content, feature, map ) {
        if ( content.stores && map._wpslPopup ) {
            groupWindow.bind( map._wpslPopup.getElement(), {
                stores: content.stores,
                marker: feature,
                map: map
            });
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
                    LOCATION_COUNT,
                    config.markers.cluster.circle.color[0],
                    config.markers.cluster.circle.color[1],
                    config.markers.cluster.circle.color[2],
                    config.markers.cluster.circle.color[3],
                    config.markers.cluster.circle.color[4]
                ],
                'circle-radius': [
                    'step',
                    LOCATION_COUNT,
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
                'text-field': LOCATION_COUNT_TEXT,
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