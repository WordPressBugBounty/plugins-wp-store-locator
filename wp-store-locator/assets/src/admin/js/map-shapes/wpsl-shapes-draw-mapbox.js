/**
 * Map Shapes drawing adapter: Mapbox GL JS + Terra Draw.
 *
 * @since 3.0.0
 */
( function() {
    'use strict';

    /*
     * The Terra Draw wiring, conversions and sanitizers live in
     * wpsl-shapes-terra-shared.js, an enqueued dependency of this file.
     */
    const shared = window.wpslShapesTerraShared;

    const INACTIVE_DIM = shared.INACTIVE_DIM;
    const safeColor    = shared.safeColor;
    const strokeWidth  = shared.strokeWidth;
    const clampOpacity = shared.clampOpacity;

    const SOURCE_ID      = 'wpsl-shapes';
    const FILL_LAYER     = 'wpsl-shapes-fill';
    const LINE_LAYER     = 'wpsl-shapes-line';
    const HIGHLIGHT_LAYER = 'wpsl-shapes-highlight';

    // Fallback when no style was ever saved on the appearance page.
    const DEFAULT_STYLE = 'mapbox://styles/mapbox/light-v11';

    // The extra class on circle and rectangle handles.
    const RESIZE_HANDLE_CLASS = 'wpsl-shape-resize-handle';

    // The extra class on the handle half-way along a side.
    const MIDPOINT_HANDLE_CLASS = 'wpsl-shape-midpoint-handle';

    let map      = null;
    let selectCb = null;
    let editCb   = null;
    let hoverCb  = null;

    // The collection currently rendered, and the id currently highlighted.
    let collection  = emptyCollection();
    let highlighted = '';

    // The shape the pointer is over, so a move across it is only reported once.
    let hovered = '';

    /*
     * refreshSource() is called from every drag frame, and setData() makes GL
     * JS re-parse and re-tile the whole collection each time. Coalescing onto
     * an animation frame pushes the source once per painted frame instead of
     * once per mousemove, which a trackpad can fire several times over.
     */
    let refreshPending = false;

    /*
     * circleToPolygon()'s 64-point rings, one entry per feature id holding the
     * centre and radius it was built from. Dragging one circle overwrites its
     * own entry and leaves every other circle's ring alone -- the rest of
     * buildData() is a few string and number ops per shape and is not worth
     * caching. Keyed on the geometry rather than invalidated by hand, so a
     * new edit path cannot forget to clear it.
     */
    let circleRings = {};

    // The shared drawing engine, wrapping this map once init() has built it.
    const engine = shared.createEngine( {
        engineLabel: 'Mapbox',
        createAdapter: function() {
            return new window.terraDrawMapboxGlAdapter.TerraDrawMapboxGLAdapter( {
                map:                 map,
                coordinatePrecision: 9
            } );
        }
    } );

    // The shape whose handles are up, and the markers that are those handles.
    let editingId = '';
    let handles   = [];

    // The centre handle that relocates the selected shape. Kept apart from
    // handles[] because that array is index-aligned with the vertices.
    let moveHandle = null;

    /**
     * Build the map, its shape layers and the drawing engine.
     *
     * @since  3.0.0
     * @param  {Element}  el          The map container.
     * @param  {object}   startLatLng { lat, lng }, guaranteed numeric by PHP.
     * @param  {function} ready
     * @return void
     */
    function init( el, startLatLng, ready ) {
        const data = window.wpslMapShapes || {};

        mapboxgl.accessToken = data.accessToken;

        map = new mapboxgl.Map( {
            container:  el,
            style:      data.mapStyle || DEFAULT_STYLE,
            center:     [ startLatLng.lng, startLatLng.lat ],
            zoom:       8,
            projection: 'mercator'
        } );

        map.addControl( new mapboxgl.NavigationControl( { showCompass: false } ), 'top-right' );

        map.on( 'load', function() {
            addShapeLayers();

            map.on( 'click', FILL_LAYER, onLayerClick );
            map.on( 'click', LINE_LAYER, onLayerClick );

            map.on( 'click', onMapClick );

            /*
             * Both layers watched: a borderless shape is only in the fill
             * layer, a zero-fill one only in the line layer.
             *
             * mousemove rather than mouseenter, because the hover toolbar is a
             * SIBLING of the map container rather than a child of it: moving
             * onto the toolbar takes the pointer off the canvas and GL JS
             * fires mouseleave. Coming back onto the same shape afterwards is
             * not a new entry as far as an edge-triggered mouseenter is
             * concerned, so the toolbar went away mid-hover and never
             * returned. A move handler re-asserts the hover instead of relying
             * on GL's idea of where the pointer has been. The other three
             * providers hover real DOM elements, which is why only Mapbox
             * showed this.
             */
            map.on( 'mousemove', FILL_LAYER, onLayerHover );
            map.on( 'mousemove', LINE_LAYER, onLayerHover );
            map.on( 'mouseleave', FILL_LAYER, onLayerLeave );
            map.on( 'mouseleave', LINE_LAYER, onLayerLeave );

            engine.start( ready );
        } );
    }

    /**
     * Register the callback for a shape clicked on the map.
     *
     * @since  3.0.0
     * @param  {function} cb
     * @return void
     */
    function onShapeSelect( cb ) {
        selectCb = cb;
    }

    /**
     * Register the callback for a shape reshaped by dragging a handle.
     *
     * @since  3.0.0
     * @param  {function} cb
     * @return void
     */
    function onShapeEdit( cb ) {
        editCb = cb;
    }

    /**
     * Register the callback for the shape under the pointer.
     *
     * @since  3.0.0
     * @param  {function} cb
     * @return void
     */
    function onShapeHover( cb ) {
        hoverCb = cb;
    }

    /**
     * The pointer is over a shape.
     *
     * Runs on every move across the shape, so the hover survives a trip onto
     * the toolbar and back.
     *
     * @since  3.0.0
     * @param  {object} event
     * @return void
     */
    function onLayerHover( event ) {
        const hit = event.features && event.features[0];

        if ( ! hit || ! hoverCb ) {
            return;
        }

        const isLine = 'polyline' === hit.properties.shape_type;

        /*
         * A polygon's toolbar sits on its centre and does not move while the
         * pointer wanders inside it, so it is only worth reporting when the
         * shape under the pointer changes. A line's toolbar follows the
         * pointer and is reported every time.
         */
        if ( ! isLine && hit.properties.id === hovered ) {
            return;
        }

        hovered = hit.properties.id;

        // A polyline's bounds centre is usually off the line, so the pointer
        // is used there instead.
        const at = isLine ? event.point : centerPoint( hit.properties.id );

        hoverCb( hit.properties.id, at );
    }

    /**
     * The container pixel a shape's centre falls on -- one toolbar spot per
     * shape, in the same place every time.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return {object|null} { x, y }.
     */
    function centerPoint( featureId ) {
        const feature = findFeature( featureId );

        if ( ! feature ) {
            return null;
        }

        const geometry = feature.geometry || {};

        if ( 'Point' === geometry.type ) {
            return map.project( geometry.coordinates );
        }

        const positions = 'LineString' === geometry.type ? geometry.coordinates : ( geometry.coordinates || [] )[0];
        let west      = null;
        let east      = null;
        let south     = null;
        let north     = null;

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            const lng = positions[ i ][0];
            const lat = positions[ i ][1];

            west  = ( null === west || lng < west ) ? lng : west;
            east  = ( null === east || lng > east ) ? lng : east;
            south = ( null === south || lat < south ) ? lat : south;
            north = ( null === north || lat > north ) ? lat : north;
        }

        if ( null === west ) {
            return null;
        }

        return map.project( [ ( west + east ) / 2, ( south + north ) / 2 ] );
    }

    /**
     * The pointer left a shape.
     *
     * @since  3.0.0
     * @return void
     */
    function onLayerLeave() {
        hovered = '';

        if ( hoverCb ) {
            hoverCb( '', null );
        }
    }

    /**
     * Draw a whole FeatureCollection, replacing whatever is on the map.
     *
     * @since  3.0.0
     * @param  {object} next
     * @return void
     */
    function renderCollection( next ) {
        collection = next && next.features ? next : emptyCollection();

        // Every feature object was just replaced, so ids the new collection
        // does not use would sit in the ring cache forever.
        circleRings = {};

        refreshSource();

        // The handles were built from the replaced features; setEditable()
        // rebuilds them, or drops them if the shape did not survive.
        if ( editingId ) {
            setEditable( editingId );
        }
    }

    /**
     * Re-style one rendered feature. The source is rebuilt rather than
     * patched: a GeoJSON source has no per-feature update.
     *
     * The editor passes the id and properties the other adapters need; this
     * one re-reads both from the shared collection.
     *
     * @since  3.0.0
     * @return void
     */
    function updateStyle() {
        refreshSource();
    }

    /**
     * Remove one rendered feature -- only from the map; the editor splices
     * its own (shared) copy out of the collection right after this.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function removeShape( featureId ) {
        const features = [];

        if ( editingId === featureId ) {
            setEditable( '' );
        }

        for ( let i = 0; i < collection.features.length; i++ ) {
            if ( ( collection.features[ i ].properties || {} ).id !== featureId ) {
                features.push( collection.features[ i ] );
            }
        }

        collection = { type: 'FeatureCollection', features: features };

        if ( highlighted === featureId ) {
            highlighted = '';
        }

        delete circleRings[ featureId ];

        refreshSource();
    }

    /**
     * Highlight one feature and drop the previous highlight.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function highlight( featureId ) {
        highlighted = featureId || '';

        if ( map && map.getLayer( HIGHLIGHT_LAYER ) ) {
            map.setFilter( HIGHLIGHT_LAYER, highlightFilter( highlighted ) );
        }
    }

    /**
     * The highlight layer's filter: the selected shape, if it has a border.
     * The highlight is the border widened, so a 0-width stroke is left out or
     * selecting it would paint a border the width field says isn't there.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return {Array}
     */
    function highlightFilter( featureId ) {
        return [ 'all',
            [ '==', [ 'get', 'id' ], featureId ],
            [ '>', [ 'get', 'stroke_width' ], 0 ]
        ];
    }

    /**
     * Put the drag handles on one shape, or on none. Drag handlers write into
     * the feature's own coordinates; emitEdit() on dragend records the result.
     *
     * @since  3.0.0
     * @param  {string} featureId '' to take the handles off whatever has them.
     * @return void
     */
    function setEditable( featureId ) {
        clearHandles();

        editingId = '';

        if ( ! map || ! featureId ) {
            return;
        }

        const feature = findFeature( featureId );
        if ( ! feature || ! feature.geometry ) {
            return;
        }

        editingId = featureId;

        buildHandles( feature );
        addMoveHandle( feature );
    }

    /**
     * The centre handle that moves the whole shape: its drag is applied to
     * every coordinate as one offset. Tracked apart from handles[], which is
     * index-aligned with the vertices.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function addMoveHandle( feature ) {
        const center = geometryCenter( feature );

        if ( ! center ) {
            return;
        }

        let origin = null;

        const element = window.document.createElement( 'div' );

        element.className = 'wpsl-shape-move-handle';

        moveHandle = new mapboxgl.Marker( { element: element, draggable: true } )
            .setLngLat( center )
            .addTo( map );

        moveHandle.on( 'dragstart', function() {
            origin = markerPosition( moveHandle );

            // Vertex handles are built once and don't follow a moving shape,
            // but they're removed during the move and rebuilt on dragend.
            setVertexHandlesVisible( false );
        } );

        moveHandle.on( 'drag', function() {
            if ( ! origin ) {
                return;
            }

            const to = markerPosition( moveHandle );

            shiftGeometry( feature.geometry, to[0] - origin[0], to[1] - origin[1] );

            origin = to;

            refreshSource();
        } );

        moveHandle.on( 'dragend', function() {
            origin = null;

            emitEdit();

            // Shown here rather than left to the deferred rebuild, or there
            // is a frame with no handles at all.
            setVertexHandlesVisible( true );

            // The vertex handles were built from coordinates that have all moved.
            rebuildHandles();
        } );
    }

    /**
     * Show or hide the vertex handles, leaving the move handle alone.
     *
     * @since  3.0.0
     * @param  {boolean} visible
     * @return void
     */
    function setVertexHandlesVisible( visible ) {
        for ( let i = 0; i < handles.length; i++ ) {
            handles[ i ].getElement().style.display = visible ? '' : 'none';
        }
    }

    /**
     * A feature's centre, as a position.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {Array|null} [ lng, lat ].
     */
    function geometryCenter( feature ) {
        const geometry = feature.geometry || {};

        if ( 'Point' === geometry.type ) {
            return geometry.coordinates;
        }

        const positions = 'LineString' === geometry.type ? geometry.coordinates : ( geometry.coordinates || [] )[0];
        let box       = null;

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            const lng = positions[ i ][0];
            const lat = positions[ i ][1];

            box = box ? [
                Math.min( box[0], lng ),
                Math.min( box[1], lat ),
                Math.max( box[2], lng ),
                Math.max( box[3], lat )
            ] : [ lng, lat, lng, lat ];
        }

        return box ? [ ( box[0] + box[2] ) / 2, ( box[1] + box[3] ) / 2 ] : null;
    }

    /**
     * Shift every position in a geometry, in place.
     *
     * @since  3.0.0
     * @param  {object} geometry
     * @param  {number} dLng
     * @param  {number} dLat
     * @return void
     */
    function shiftGeometry( geometry, dLng, dLat ) {
        if ( 'Point' === geometry.type ) {
            geometry.coordinates = shiftPosition( geometry.coordinates, dLng, dLat );

            return;
        }

        if ( 'LineString' === geometry.type ) {
            geometry.coordinates = shiftPositions( geometry.coordinates, dLng, dLat );

            return;
        }

        const rings = geometry.coordinates || [];

        for ( let i = 0; i < rings.length; i++ ) {
            rings[ i ] = shiftPositions( rings[ i ], dLng, dLat );
        }
    }

    /**
     * A list of positions, shifted.
     *
     * @since  3.0.0
     * @param  {Array}  positions
     * @param  {number} dLng
     * @param  {number} dLat
     * @return {Array}
     */
    function shiftPositions( positions, dLng, dLat ) {
        const shifted = [];

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            shifted.push( shiftPosition( positions[ i ], dLng, dLat ) );
        }

        return shifted;
    }

    /**
     * One position, shifted and kept inside the coordinate range.
     *
     * @since  3.0.0
     * @param  {Array}  position
     * @param  {number} dLng
     * @param  {number} dLat
     * @return {Array}
     */
    function shiftPosition( position, dLng, dLat ) {
        return [
            wrapLongitude( position[0] + dLng ),
            Math.min( 90, Math.max( -90, position[1] + dLat ) )
        ];
    }

    /**
     * The handles a feature gets, by what kind of shape it is.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function buildHandles( feature ) {
        const props    = feature.properties || {};
        const geometry = feature.geometry;

        if ( 'Point' === geometry.type ) {
            buildCircleHandles( feature );

            return;
        }

        if ( 'LineString' === geometry.type ) {
            buildVertexHandles( feature, geometry.coordinates, false );

            return;
        }

        if ( 'Polygon' !== geometry.type ) {
            return;
        }

        if ( 'rectangle' === props.shape_type ) {
            buildRectangleHandles( feature );

            return;
        }

        // The outer ring only: holes aren't drawable here and are left as stored.
        buildVertexHandles( feature, ( geometry.coordinates || [] )[0] || [], true );
    }

    /**
     * One handle per position, for a polygon ring or a line.
     *
     * @since  3.0.0
     * @param  {object}  feature
     * @param  {Array}   positions The live coordinate array -- dragging writes
     *                             straight into it.
     * @param  {boolean} closed    Whether the last position repeats the first.
     * @return void
     */
    function buildVertexHandles( feature, positions, closed ) {
        const count    = vertexCount( positions, closed );
        const segments = segmentCount( positions, closed );

        for ( let i = 0; i < count; i++ ) {
            addHandle( positions[ i ], vertexDragger( positions, i, closed ) );
        }

        // Half-way handles go on after every vertex has one: handles[] is
        // index-aligned with the vertices.
        for ( let i = 0; i < segments; i++ ) {
            const at = midpointOf( positions, i, count );

            addHandle( at, midpointDragger( positions, i, at ), MIDPOINT_HANDLE_CLASS );
        }
    }

    /**
     * How many vertices a set of positions holds. A closed ring's last
     * position is the first again, and would get an undraggable handle.
     *
     * @since  3.0.0
     * @param  {Array}   positions
     * @param  {boolean} closed
     * @return {number}
     */
    function vertexCount( positions, closed ) {
        return closed ? positions.length - 1 : positions.length;
    }

    /**
     * How many sides those vertices make: 
     * a ring has as many sides as corners, a line one fewer.
     *
     * @since  3.0.0
     * @param  {Array}   positions
     * @param  {boolean} closed
     * @return {number}
     */
    function segmentCount( positions, closed ) {
        const count = vertexCount( positions, closed );

        return closed ? count : count - 1;
    }

    /**
     * The point half-way along one side.
     *
     * @since  3.0.0
     * @param  {Array}  positions
     * @param  {number} index The side, named by the vertex it starts at.
     * @param  {number} count The vertex count, which wraps a ring's last side
     *                        back onto its first vertex.
     * @return {Array}  [ lng, lat ].
     */
    function midpointOf( positions, index, count ) {
        const from = positions[ index ];
        const to   = positions[ ( index + 1 ) % count ];

        return [ ( from[0] + to[0] ) / 2, ( from[1] + to[1] ) / 2 ];
    }

    /**
     * The dragger for one vertex.
     *
     * @since  3.0.0
     * @param  {Array}   positions
     * @param  {number}  index
     * @param  {boolean} closed
     * @return {object}  A dragger: { move }.
     */
    function vertexDragger( positions, index, closed ) {
        return {
            move: function( position ) {
                positions[ index ] = position;

                // The closing position is the first one: moved on its own it
                // would tear the ring open.
                if ( closed && 0 === index ) {
                    positions[ positions.length - 1 ] = [ position[0], position[1] ];
                }

                refreshSource();
                syncMidpoints( positions, index, closed );
            }
        };
    }

    /**
     * The dragger for one half-way handle: the one that adds a vertex. The
     * position joins the ring at drag start, so every frame after that moves
     * a vertex the shape really has; slot index + 1 keeps the ring closed.
     *
     * @since  3.0.0
     * @param  {Array}  positions
     * @param  {number} index The side this handle splits.
     * @param  {Array}  at    Where the handle started, which is the position
     *                        the new vertex takes until the drag moves it.
     * @return {object} A dragger: { start, move, end }.
     */
    function midpointDragger( positions, index, at ) {
        const slot = index + 1;

        return {
            start: function() {
                positions.splice( slot, 0, [ at[0], at[1] ] );
            },

            move: function( position ) {
                positions[ slot ] = position;

                refreshSource();
            },

            // The new vertex needs a handle of its own, and the split side
            // needs two half-way handles where there was one.
            end: rebuildHandles
        };
    }

    /**
     * Put the half-way handles either side of a dragged vertex 
     * back in the middle of the sides they split.
     *
     * @since  3.0.0
     * @param  {Array}   positions
     * @param  {number}  index The vertex that moved.
     * @param  {boolean} closed
     * @return void
     */
    function syncMidpoints( positions, index, closed ) {
        const count    = vertexCount( positions, closed );
        const segments = segmentCount( positions, closed );

        // The side ending on this vertex, then the one leaving it. A ring's
        // first vertex also ends the last side.
        const sides = [ closed && 0 === index ? segments - 1 : index - 1, index ];

        for ( let i = 0; i < sides.length; i++ ) {
            const side   = sides[ i ];
            const marker = ( side >= 0 && side < segments ) ? handles[ count + side ] : null;

            if ( marker ) {
                marker.setLngLat( midpointOf( positions, side, count ) );
            }
        }
    }

    /**
     * Four corner handles that keep a rectangle a rectangle: dragging one
     * rebuilds the ring from it and the opposite corner, so the neighbours follow.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function buildRectangleHandles( feature ) {
        const ring = ( feature.geometry.coordinates || [] )[0] || [];

        // The stored ring is SW, SE, NE, NW, SW ( cornersToRing() ), so the
        // corner opposite index i is always two steps around from it.
        for ( let i = 0; i < 4 && i < ring.length; i++ ) {
            addHandle( ring[ i ], rectangleDragger( feature, i ), RESIZE_HANDLE_CLASS );
        }
    }

    /**
     * The dragger for one rectangle corner.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @param  {number} index The corner's position in the stored ring.
     * @return {object} A dragger: { start, move, end }.
     */
    function rectangleDragger( feature, index ) {
        let anchor = null;

        return {
            // Read once at drag start: cornersToRing() re-sorts every time, so
            // reading per move makes the rectangle chase its own tail.
            start: function() {
                const ring = feature.geometry.coordinates[0];
                const away = ring[ ( index + 2 ) % 4 ];

                anchor = [ away[0], away[1] ];
            },

            move: function( position ) {
                if ( ! anchor ) {
                    return;
                }

                feature.geometry.coordinates[0] = cornersToRing( anchor, position );

                refreshSource();
                syncHandles( feature.geometry.coordinates[0], index );
            },

            // The final ring is in canonical corner order again, which the
            // dragged handle's index may no longer match.
            end: rebuildHandles
        };
    }

    /**
     * A circle's centre handle and its radius handle.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function buildCircleHandles( feature ) {
        // Handle 0 moves the circle; handle 1, due east on the circumference,
        // is the only way to change a radius held in metres.
        addHandle( feature.geometry.coordinates, {
            move: function( position ) {
                feature.geometry.coordinates = position;

                refreshSource();
                syncHandles( [ position, edgePosition( feature ) ], 0 );
            }
        }, RESIZE_HANDLE_CLASS );

        addHandle( edgePosition( feature ), {
            move: function( position ) {
                const meters = haversineMeters( feature.geometry.coordinates, position );

                // A radius of 0 is a circle the sanitizer refuses.
                if ( meters > 0 ) {
                    feature.properties.radius = meters;
                }

                refreshSource();
            },

            // Back onto the circumference: the handle followed the pointer.
            end: rebuildHandles
        }, RESIZE_HANDLE_CLASS );
    }

    /**
     * A draggable marker at one position.
     *
     * @since  3.0.0
     * @param  {Array}  position [ lng, lat ].
     * @param  {object} dragger  { move, start?, end? }.
     * @param  {string} modifier Extra class for the handle element, if any.
     * @return void
     */
    function addHandle( position, dragger, modifier ) {
        const element = window.document.createElement( 'div' );

        element.className = modifier ? 'wpsl-shape-handle ' + modifier : 'wpsl-shape-handle';

        const marker = new mapboxgl.Marker( { element: element, draggable: true } )
            .setLngLat( position )
            .addTo( map );

        marker.on( 'dragstart', function() {
            if ( dragger.start ) {
                dragger.start();
            }
        } );

        // The move handle follows every frame ( reshaping moves the centre ).
        // Only here, never in its own drag -- setting a marker's position
        // from inside its own drag fights the pointer.
        marker.on( 'drag', function() {
            dragger.move( markerPosition( marker ) );
            syncMoveHandle();
        } );

        marker.on( 'dragend', function() {
            dragger.move( markerPosition( marker ) );
            syncMoveHandle();

            emitEdit();

            if ( dragger.end ) {
                dragger.end();
            }
        } );

        handles.push( marker );
    }

    /**
     * Put the move handle back in the middle of the shape -- every vertex,
     * corner and radius change moves the centre out from under it.
     *
     * @since  3.0.0
     * @return void
     */
    function syncMoveHandle() {
        const feature = moveHandle ? findFeature( editingId ) : null;

        if ( ! feature ) {
            return;
        }

        const center = geometryCenter( feature );

        if ( center ) {
            moveHandle.setLngLat( center );
        }
    }

    /**
     * Move the handles that are not the one under the cursor -- the dragged
     * marker already follows the pointer.
     *
     * @since  3.0.0
     * @param  {Array}  positions   Index-aligned with the handles.
     * @param  {number} draggedIndex
     * @return void
     */
    function syncHandles( positions, draggedIndex ) {
        for ( let i = 0; i < handles.length && i < positions.length; i++ ) {
            if ( i !== draggedIndex ) {
                handles[ i ].setLngLat( positions[ i ] );
            }
        }
    }

    /**
     * Build the handles again for whatever is being edited. Deferred a tick:
     * the callers sit inside a marker's own dragend, and removing that marker
     * mid-event is not something mapboxgl.Marker promises to survive.
     *
     * @since  3.0.0
     * @return void
     */
    function rebuildHandles() {
        const featureId = editingId;

        window.setTimeout( function() {
            if ( editingId === featureId ) {
                setEditable( featureId );
            }
        }, 0 );
    }

    /**
     * Drop every handle.
     *
     * @since  3.0.0
     * @return void
     */
    function clearHandles() {
        for ( let i = 0; i < handles.length; i++ ) {
            handles[ i ].remove();
        }

        handles = [];

        if ( moveHandle ) {
            moveHandle.remove();

            moveHandle = null;
        }
    }

    /**
     * Tell the editor what the shape being edited now looks like.
     *
     * @since  3.0.0
     * @return void
     */
    function emitEdit() {
        const feature = findFeature( editingId );

        if ( ! feature || ! editCb ) {
            return;
        }

        // A circle's size is not in its geometry, so the radius travels alongside.
        const properties = 'Point' === feature.geometry.type ? { radius: feature.properties.radius } : null;

        editCb( editingId, feature.geometry, properties );
    }

    /**
     * The position due east of a circle's centre, read off circleToPolygon()'s
     * own ring so the handle sits on the outline actually drawn. The ring
     * starts due north and walks clockwise, putting due east at index 16.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {Array} [ lng, lat ].
     */
    function edgePosition( feature ) {
        const radius = parseFloat( ( feature.properties || {} ).radius );

        return circleToPolygon( feature.geometry.coordinates, radius > 0 ? radius : 1 )[0][16];
    }

    /**
     * A marker's position, inside the range the sanitizer accepts: GL JS
     * counts past the antimeridian rather than wrapping, and one out-of-range
     * longitude costs the user the whole save.
     *
     * @since  3.0.0
     * @param  {object} marker
     * @return {Array} [ lng, lat ].
     */
    function markerPosition( marker ) {
        const lngLat = marker.getLngLat();

        return [ wrapLongitude( lngLat.lng ), Math.min( 90, Math.max( -90, lngLat.lat ) ) ];
    }

    /**
     * A feature in the rendered collection, by id.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return {object|null}
     */
    function findFeature( featureId ) {
        if ( ! featureId ) {
            return null;
        }

        for ( let i = 0; i < collection.features.length; i++ ) {
            if ( ( collection.features[ i ].properties || {} ).id === featureId ) {
                return collection.features[ i ];
            }
        }

        return null;
    }

    /**
     * The source and the three layers everything saved is drawn with.
     *
     * @since  3.0.0
     * @return void
     */
    function addShapeLayers() {
        map.addSource( SOURCE_ID, { type: 'geojson', data: emptyCollection() } );

        // Paint values come from feature properties that buildData() has
        // normalized -- a ['get'] landing on undefined or a wrong type is a
        // paint error, not a fallback.
        map.addLayer( {
            id:     FILL_LAYER,
            type:   'fill',
            source: SOURCE_ID,
            filter: [ '==', [ 'geometry-type' ], 'Polygon' ],
            paint:  {
                'fill-color':   [ 'get', 'fill_color' ],
                'fill-opacity': [ 'get', 'fill_opacity' ]
            }
        } );

        // GL JS draws a hairline for 0-width lines, so borderless shapes are
        // filtered out. The frontend filters identically.
        map.addLayer( {
            id:     LINE_LAYER,
            type:   'line',
            source: SOURCE_ID,
            filter: [ '>', [ 'get', 'stroke_width' ], 0 ],
            paint:  {
                'line-color':   [ 'get', 'stroke_color' ],
                'line-width':   [ 'get', 'stroke_width' ],
                'line-opacity': [ 'get', 'stroke_opacity' ]
            }
        } );

        // The highlight is a second line on top of the first, filtered down to
        // the selected id -- nothing about the feature itself changes.
        map.addLayer( {
            id:     HIGHLIGHT_LAYER,
            type:   'line',
            source: SOURCE_ID,
            filter: highlightFilter( '' ),
            paint:  {
                'line-color': [ 'get', 'stroke_color' ],
                'line-width': [ '+', [ 'get', 'stroke_width' ], 2 ],
                'line-opacity': 0.6
            }
        } );
    }

    /**
     * Ask for the collection to be pushed into the source on the next frame.
     *
     * Callers that follow this with syncHandles(), syncMidpoints() or
     * rebuildHandles() stay correct: those read the feature's own coordinates
     * and the handle markers, never the source.
     *
     * @since  3.0.0
     * @return void
     */
    function refreshSource() {
        if ( refreshPending ) {
            return;
        }

        refreshPending = true;

        window.requestAnimationFrame( function() {
            refreshPending = false;
            applySource();
        } );
    }

    /**
     * Push the current collection into the source.
     *
     * @since  3.0.0
     * @return void
     */
    function applySource() {
        if ( ! map || ! map.getSource( SOURCE_ID ) ) {
            return;
        }

        map.getSource( SOURCE_ID ).setData( buildData( collection ) );

        highlight( highlighted );
    }

    /**
     * The collection as the source sees it: circles turned into polygons and
     * every paint property normalized to a value the expressions can read.
     *
     * @since  3.0.0
     * @param  {object} source
     * @return {object}
     */
    function buildData( source ) {
        const features = [];
        const input    = ( source && source.features ) ? source.features : [];

        for ( let i = 0; i < input.length; i++ ) {
            const props    = input[ i ].properties || {};
            let geometry = input[ i ].geometry || {};
            const id       = String( props.id || '' );

            if ( ! id || ! geometry.type ) {
                continue;
            }

            const radius = parseFloat( props.radius );

            if ( 'Point' === geometry.type ) {
                if ( ! ( radius > 0 ) ) {
                    continue;
                }

                geometry = { type: 'Polygon', coordinates: circleRing( id, geometry.coordinates, radius ) };
            }

            const dim = false === props.active ? INACTIVE_DIM : 1;

            features.push( {
                type:     'Feature',
                geometry: geometry,
                properties: {
                    id:           id,
                    shape_type:   String( props.shape_type || '' ),
                    fill_color:   safeColor( props.fill_color ),
                    fill_opacity: ( props.fill ? clampOpacity( props.fill_opacity ) : 0 ) * dim,
                    stroke_color: safeColor( props.stroke_color ),
                    stroke_width: strokeWidth( props.stroke_width ),

                    // Always written: the line layer's ['get'] on a missing
                    // property is a paint error, not a fallback to 1.
                    stroke_opacity: dim
                }
            } );
        }

        return { type: 'FeatureCollection', features: features };
    }

    /**
     * A click on one of the shape layers.
     *
     * @since  3.0.0
     * @param  {object} event
     * @return void
     */
    function onLayerClick( event ) {
        const hit = event.features && event.features[0];

        if ( ! hit || ! selectCb ) {
            return;
        }

        selectCb( hit.properties.id );
    }

    /**
     * A click anywhere on the map. GL JS delivers a layer-scoped click AND
     * this one for the same press, so it has to ask what is under the point.
     * An empty id drops the selection.
     *
     * @since  3.0.0
     * @param  {object} event
     * @return void
     */
    function onMapClick( event ) {
        if ( ! selectCb || ! map.getLayer( FILL_LAYER ) ) {
            return;
        }

        const hits = map.queryRenderedFeatures( event.point, { layers: [ FILL_LAYER, LINE_LAYER ] } );

        if ( hits.length ) {
            return;
        }

        // The finishing press's second delivery, not a deselect.
        if ( engine.consumeFinishClick() ) {
            return;
        }

        selectCb( '' );
    }

    /**
     * Two opposite corners to the canonical rectangle ring.
     *
     * @since  3.0.0
     * @param  {Array} a [ lng, lat ].
     * @param  {Array} b [ lng, lat ].
     * @return {Array} SW, SE, NE, NW, SW.
     */
    function cornersToRing( a, b ) {
        const west  = Math.min( a[0], b[0] );
        const east  = Math.max( a[0], b[0] );
        const south = Math.min( a[1], b[1] );
        const north = Math.max( a[1], b[1] );

        return [
            [ west, south ],
            [ east, south ],
            [ east, north ],
            [ west, north ],
            [ west, south ]
        ];
    }

    /**
     * One circle's ring, reused while its centre and radius hold still.
     *
     * Every buildData() pass would otherwise re-run the 64-segment trig for
     * every circle on the map, including the ones the drag is not touching.
     * Each feature keeps a single entry, so a drag overwrites its own rather
     * than piling up an entry per frame.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {Array}  center    [ lng, lat ].
     * @param  {number} radius    Metres.
     * @return {Array}  [ ring ], the ring being 65 positions.
     */
    function circleRing( featureId, center, radius ) {
        const key    = center[0] + ',' + center[1] + ',' + radius;
        const cached = circleRings[ featureId ];

        if ( cached && cached.key === key ) {
            return cached.ring;
        }

        const ring = circleToPolygon( center, radius );

        circleRings[ featureId ] = { key: key, ring: ring };

        return ring;
    }

    /**
     * A circle as polygon coordinates. Keep identical to the frontend copy,
     * or a saved circle changes size per screen. 64 segments, spherical law
     * of cosines, closed ring, longitudes wrapped to [ -180, 180 ].
     *
     * @since  3.0.0
     * @param  {Array}  center [ lng, lat ].
     * @param  {number} radius Metres.
     * @return {Array}  [ ring ], the ring being 65 positions.
     */
    function circleToPolygon( center, radius ) {
        const STEPS        = 64;
        const EARTH_RADIUS = 6378137;

        const lng      = center[0] * Math.PI / 180;
        const lat      = center[1] * Math.PI / 180;
        const angular  = radius / EARTH_RADIUS;
        const ring     = [];

        for ( let i = 0; i < STEPS; i++ ) {
            const bearing = 2 * Math.PI * i / STEPS;

            const pointLat = Math.asin(
                Math.sin( lat ) * Math.cos( angular ) + Math.cos( lat ) * Math.sin( angular ) * Math.cos( bearing )
            );

            const pointLng = lng + Math.atan2(
                Math.sin( bearing ) * Math.sin( angular ) * Math.cos( lat ),
                Math.cos( angular ) - Math.sin( lat ) * Math.sin( pointLat )
            );

            ring.push( [ wrapLongitude( pointLng * 180 / Math.PI ), pointLat * 180 / Math.PI ] );
        }

        ring.push( [ ring[0][0], ring[0][1] ] );

        return [ ring ];
    }

    /**
     * Metres between two positions.
     *
     * @since  3.0.0
     * @param  {Array} a [ lng, lat ].
     * @param  {Array} b [ lng, lat ].
     * @return {number}
     */
    function haversineMeters( a, b ) {
        const EARTH_RADIUS = 6378137;
        const toRad        = Math.PI / 180;

        const lat1 = a[1] * toRad;
        const lat2 = b[1] * toRad;
        const dLat = ( b[1] - a[1] ) * toRad;
        const dLng = ( b[0] - a[0] ) * toRad;

        const h = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
            Math.cos( lat1 ) * Math.cos( lat2 ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );

        return 2 * EARTH_RADIUS * Math.asin( Math.min( 1, Math.sqrt( h ) ) );
    }

    /**
     * A longitude wrapped into [ -180, 180 ].
     *
     * @since  3.0.0
     * @param  {number} lng
     * @return {number}
     */
    function wrapLongitude( lng ) {
        return ( ( lng + 540 ) % 360 ) - 180;
    }

    /**
     * An empty FeatureCollection.
     *
     * @since  3.0.0
     * @return {object}
     */
    function emptyCollection() {
        return { type: 'FeatureCollection', features: [] };
    }

    /**
     * Move the map to a searched place.
     *
     * @since  3.0.0
     * @param  {number} lat
     * @param  {number} lng
     * @param  {number} zoom The zoom the editor frames a place at.
     * @return void
     */
    function panTo( lat, lng, zoom ) {
        map.flyTo( { center: [ lng, lat ], zoom: zoom } );
    }

    window.wpslShapesAdapter = {
        init:             init,
        getMap:           function() { return map; },

        /**
         * A lat / lng as a pixel in the map container.
         *
         * @since  3.0.0
         * @param  {number} lat
         * @param  {number} lng
         * @return {object|null} { x, y }
         */
        toContainerPoint: function( lat, lng ) {
            return map ? map.project( [ lng, lat ] ) : null;
        },

        panTo:            panTo,
        enableDraw:       engine.enableDraw,
        cancelDraw:       engine.cancelDraw,
        instructionKey:   engine.instructionKey,
        onShapeComplete:  engine.onShapeComplete,
        onShapeSelect:    onShapeSelect,
        onShapeEdit:      onShapeEdit,
        onShapeHover:     onShapeHover,
        renderCollection: renderCollection,
        updateStyle:      updateStyle,
        removeShape:      removeShape,
        highlight:        highlight,
        setEditable:      setEditable,
        conversions:      { circleToPolygon: circleToPolygon }
    };
} )();