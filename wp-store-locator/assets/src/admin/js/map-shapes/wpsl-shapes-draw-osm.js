/**
 * Map Shapes drawing adapter: Leaflet + Terra Draw, edited with Leaflet-Geoman.
 *
 * Used for every Leaflet provider -- Manager::drawing_adapter() resolves them
 * all to 'osm'. Leaflet counts [ lat, lng ], GeoJSON [ lng, lat ]; every
 * crossing goes through the conversions at the bottom of this file.
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
    const closeRing    = shared.closeRing;
    const safeColor    = shared.safeColor;
    const strokeWidth  = shared.strokeWidth;
    const clampOpacity = shared.clampOpacity;

    // Geoman's radius guide line while a stored circle is resized. Weight 1,
    // not Leaflet's default 3, which read as the outline instead of a guide.
    const HINT_LINE_STYLE = {
        color:     '#3388ff',
        dashArray: '5,5',
        weight:    1
    };

    let map      = null;
    let selectCb = null;
    let editCb   = null;
    let hoverCb  = null;

    // The shared drawing engine, wrapping this map once init() has built it.
    const engine = shared.createEngine( {
        engineLabel: 'Leaflet',
        createAdapter: function() {
            return new window.terraDrawLeafletAdapter.TerraDrawLeafletAdapter( {
                lib:                 L,
                map:                 map,
                coordinatePrecision: 9
            } );
        }
    } );

    // featureId => { layer, weight, geometry, type }.
    let rendered = {};

    let highlighted = '';
    let editingId   = '';

    // The centre handle that relocates the selected shape. See addMoveHandle().
    let moveHandle = null;

    /*
     * Centre handle size in px -- keep in step with .wpsl-shape-move-handle.
     * Passed in because L.DivIcon writes its default [ 12, 12 ] iconSize as
     * inline width/height, overriding CSS.
     */
    const MOVE_HANDLE_SIZE = 16;

    // Where PHP's tile config ends up when it names no usable raster source.
    const DEFAULT_TILES = {
        urlTemplate: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        options:     { attribution: '&copy; OpenStreetMap' }
    };

    /**
     * Does this browser have the WebGL that MapLibre needs? Same check as the
     * front end: a vector style without WebGL is a blank map.
     *
     * @since  3.0.0
     * @return {boolean}
     */
    function webglSupported() {
        try {
            const canvas = document.createElement( 'canvas' );

            return !! ( window.WebGLRenderingContext && ( canvas.getContext( 'webgl' ) || canvas.getContext( 'experimental-webgl' ) ) );
        } catch ( e ) {
            return false;
        }
    }

    /**
     * Draw the raster tiles from a config, and hand the layer back. Leaflet
     * interpolates {style} and {api_key} out of the options object itself,
     * which is how the Stadia template resolves.
     *
     * @since  3.0.0
     * @param  {object} config A raster tile config.
     * @return {object} The Leaflet layer.
     */
    function addRasterLayer( config ) {
        if ( ! config || ! config.urlTemplate ) {
            config = DEFAULT_TILES;
        }

        return L.tileLayer( config.urlTemplate, config.options || {} ).addTo( map );
    }

    /**
     * Draw the configured tiles.
     *
     * A vector style (OpenFreeMap) uses the MapLibre bridge; any failure ( no
     * bridge, no WebGL, style won't load ) falls back to the raster source PHP
     * pairs with every vector config. The style request is async, so the load
     * listeners attached after addTo() still catch a failure.
     *
     * @since  3.0.0
     * @return void
     */
    function addTileLayer() {
        const config = ( window.wpslMapShapes || {} ).tileLayer || DEFAULT_TILES;

        if ( 'vector' !== config.type ) {
            addRasterLayer( config );

            return;
        }

        if ( 'undefined' === typeof L.maplibreGL || ! webglSupported() ) {
            addRasterLayer( config.fallback );

            return;
        }

        const glLayer = L.maplibreGL( {
            style:       config.style,
            attribution: ( config.options || {} ).attribution,
            maxZoom:     ( config.options || {} ).maxZoom
        } );

        glLayer.addTo( map );

        /*
         * A vector layer never sets the map's zoom limits, and Geoman reads
         * them when placing vertex markers.
         */
        if ( ( config.options || {} ).maxZoom ) {
            map.setMaxZoom( config.options.maxZoom );
        }

        const glMap    = glLayer.getMaplibreMap ? glLayer.getMaplibreMap() : null;
        let loaded   = false;
        let fellBack = false;

        if ( ! glMap ) {
            return;
        }

        glMap.once( 'load', function() {
            loaded = true;
        } );

        glMap.on( 'error', function() {
            if ( loaded || fellBack ) {
                return;
            }

            fellBack = true;

            map.removeLayer( glLayer );
            addRasterLayer( config.fallback );
        } );
    }

    /**
     * Build the map and start listening for finished drawings.
     *
     * @since  3.0.0
     * @param  {Element}  el          The map container.
     * @param  {object}   startLatLng { lat, lng }, guaranteed numeric by PHP.
     * @param  {function} ready
     * @return void
     */
    function init( el, startLatLng, ready ) {
        map = L.map( el, { zoomControl: false } ).setView( [ startLatLng.lat, startLatLng.lng ], 8 );

        L.control.zoom( { position: 'topright' } ).addTo( map );

        addTileLayer();

        /*
         * Snapping off: shapes sit over stores and other shapes, and Geoman
         * would pull a dropped handle onto whichever is nearest.
         */
        map.pm.setGlobalOptions( { snappable: false, hintlineStyle: HINT_LINE_STYLE } );

        map.on( 'click', onMapClick );

        engine.start( ready );
    }

    /**
     * A click that reached the map hit no shape ( layers stop their own
     * clicks ). An empty id tells the editor to drop the selection.
     *
     * @since  3.0.0
     * @return void
     */
    function onMapClick() {
        if ( ! selectCb ) {
            return;
        }

        // The finishing press's second delivery, not a deselect.
        if ( engine.consumeFinishClick() ) {
            return;
        }

        selectCb( '' );
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
     * Draw a whole FeatureCollection, replacing whatever is on the map.
     *
     * @since  3.0.0
     * @param  {object} collection
     * @return void
     */
    function renderCollection( collection ) {
        const features = ( collection && collection.features ) ? collection.features : [];

        // clearRendered() drops the editing state; put back on the new layer below.
        const editing = editingId;

        clearRendered();

        for ( let i = 0; i < features.length; i++ ) {
            addFeature( features[ i ] );
        }

        if ( highlighted ) {
            highlight( highlighted );
        }

        if ( editing ) {
            setEditable( editing );
        }
    }

    /**
     * Re-style one rendered feature in place.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {object} props     The feature's properties.
     * @return void
     */
    function updateStyle( featureId, props ) {
        const entry = rendered[ featureId ];

        if ( ! entry ) {
            return;
        }

        entry.weight = strokeWidth( props.stroke_width );
        entry.layer.setStyle( layerStyle( props, entry.geometry ) );
    }

    /**
     * Remove one rendered feature.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function removeShape( featureId ) {
        const entry = rendered[ featureId ];

        if ( ! entry ) {
            return;
        }

        if ( editingId === featureId ) {
            setEditable( '' );
        }

        map.removeLayer( entry.layer );

        delete rendered[ featureId ];

        if ( highlighted === featureId ) {
            highlighted = '';
        }
    }

    /**
     * Highlight one feature and drop the previous highlight.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function highlight( featureId ) {
        highlighted = rendered[ featureId ] ? featureId : '';
    }

    /**
     * Put the drag handles on one shape, or on none.
     *
     * @since  3.0.0
     * @param  {string} featureId '' to take the handles off whatever has them.
     * @return void
     */
    function setEditable( featureId ) {
        if ( editingId && rendered[ editingId ] && rendered[ editingId ].layer.pm ) {
            rendered[ editingId ].layer.pm.disable();
        }

        removeMoveHandle();

        editingId = '';

        const entry = rendered[ featureId ];
        if ( ! entry || ! entry.layer.pm ) {
            return;
        }

        editingId = featureId;

        addMoveHandle( entry );

        entry.layer.pm.enable( {
            allowSelfIntersection: true,
            snappable:             false,
            hintlineStyle:         HINT_LINE_STYLE
        } );
    }

    /**
     * Centre handle that moves the whole shape: Geoman's markers reshape but
     * don't relocate. Editing is off during the drag; its markers don't follow
     * a moving geometry.
     *
     * @since  3.0.0
     * @param  {object} entry The rendered entry to attach the handle to.
     * @return void
     */
    function addMoveHandle( entry ) {
        moveHandle = L.marker( layerCenter( entry ), {
            draggable: true,
            icon:      L.divIcon( {
                className: 'wpsl-shape-move-handle',
                iconSize:  [ MOVE_HANDLE_SIZE, MOVE_HANDLE_SIZE ]
            } ),
            zIndexOffset: 1000
        } );

        moveHandle._pmTempLayer = true;

        let origin = null;

        moveHandle.on( 'dragstart', function() {
            origin = moveHandle.getLatLng();

            if ( entry.layer.pm ) {
                entry.layer.pm.disable();
            }
        } );

        moveHandle.on( 'drag', function() {
            if ( ! origin ) {
                return;
            }

            const to = moveHandle.getLatLng();
            moveLayer( entry, to.lat - origin.lat, to.lng - origin.lng );

            origin = to;
        } );

        moveHandle.on( 'dragend', function() {
            origin = null;

            emitEdit( editingId );

            // Re-enabling rebuilds Geoman's markers on the moved shape.
            setEditable( editingId );
        } );

        moveHandle.addTo( map );
    }

    /**
     * A rendered layer's own centre: a circle knows it, everything else is
     * the middle of its bounds.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return {object} A Leaflet LatLng.
     */
    function layerCenter( entry ) {
        return 'circle' === entry.type ? entry.layer.getLatLng() : entry.layer.getBounds().getCenter();
    }

    /**
     * Reposition the move handle at the shape's centre -- vertex drags and
     * resizes move the centre out from under it.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return void
     */
    function syncMoveHandle( entry ) {
        if ( moveHandle && entry ) {
            moveHandle.setLatLng( layerCenter( entry ) );
        }
    }

    /**
     * Take the move handle off the map.
     *
     * @since  3.0.0
     * @return void
     */
    function removeMoveHandle() {
        if ( moveHandle ) {
            map.removeLayer( moveHandle );

            moveHandle = null;
        }
    }

    /**
     * Shift a rendered layer by a lat / lng delta.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @param  {number} dLat
     * @param  {number} dLng
     * @return void
     */
    function moveLayer( entry, dLat, dLng ) {
        const layer = entry.layer;

        if ( 'circle' === entry.type ) {
            const center = layer.getLatLng();
            layer.setLatLng( [ center.lat + dLat, center.lng + dLng ] );

            return;
        }

        if ( 'rectangle' === entry.type ) {
            const bounds = layer.getBounds();
            const sw     = bounds.getSouthWest();
            const ne     = bounds.getNorthEast();

            layer.setBounds( [
                [ sw.lat + dLat, sw.lng + dLng ],
                [ ne.lat + dLat, ne.lng + dLng ]
            ] );

            return;
        }

        layer.setLatLngs( shiftLatLngs( layer.getLatLngs(), dLat, dLng ) );
    }

    /**
     * Every LatLng in a nested Leaflet structure, shifted. getLatLngs() is
     * flat for a line and nested for a polygon, so any depth is walked.
     *
     * @since  3.0.0
     * @param  {Array}  latLngs
     * @param  {number} dLat
     * @param  {number} dLng
     * @return {Array}
     */
    function shiftLatLngs( latLngs, dLat, dLng ) {
        const shifted = [];

        for ( let i = 0; i < ( latLngs || [] ).length; i++ ) {
            if ( Array.isArray( latLngs[ i ] ) ) {
                shifted.push( shiftLatLngs( latLngs[ i ], dLat, dLng ) );

                continue;
            }

            shifted.push( [ latLngs[ i ].lat + dLat, latLngs[ i ].lng + dLng ] );
        }

        return shifted;
    }

    /**
     * Tell the editor what a shape now looks like.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function emitEdit( featureId ) {
        const entry = rendered[ featureId ];

        if ( ! entry || ! editCb ) {
            return;
        }

        const geometry = layerGeometry( entry );
        if ( ! geometry ) {
            return;
        }

        // A circle's size is not in its geometry, so the radius travels alongside.
        const properties = 'circle' === entry.type ? { radius: entry.layer.getRadius() } : null;

        editCb( featureId, geometry, properties );
    }

    /**
     * A rendered layer read back as GeoJSON. Keyed on shape type, not layer
     * class: an L.Rectangle is an L.Polygon, and its vertices come back in
     * drag order where its bounds don't.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return {object|null}
     */
    function layerGeometry( entry ) {
        const layer = entry.layer;

        if ( 'circle' === entry.type ) {
            const center = layer.getLatLng();
            return { type: 'Point', coordinates: [ center.lng, center.lat ] };
        }

        if ( 'rectangle' === entry.type ) {
            return { type: 'Polygon', coordinates: [ boundsToRing( layer.getBounds() ) ] };
        }

        if ( 'polyline' === entry.type ) {
            return { type: 'LineString', coordinates: latLngsToLine( firstRing( layer.getLatLngs() ) ) };
        }

        return { type: 'Polygon', coordinates: ringsToGeoJson( layer.getLatLngs() ) };
    }

    /**
     * Every ring of an edited polygon, closed. A single-ring polygon's
     * getLatLngs() can be a bare point list; both come out as rings.
     *
     * @since  3.0.0
     * @param  {Array} latLngs
     * @return {Array}
     */
    function ringsToGeoJson( latLngs ) {
        let rings = latLngs || [];

        if ( rings.length && ! Array.isArray( rings[0] ) ) {
            rings = [ rings ];
        }

        const geoJson = [];

        for ( let i = 0; i < rings.length; i++ ) {
            geoJson.push( latLngsToRing( rings[ i ] ) );
        }

        return geoJson;
    }

    /**
     * Put one feature on the map.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function addFeature( feature ) {
        const props    = ( feature && feature.properties ) || {};
        const geometry = ( feature && feature.geometry ) || {};
        const id       = String( props.id || '' );

        if ( ! id || ! geometry.type ) {
            return;
        }

        const style = layerStyle( props, geometry );
        let layer = null;

        if ( 'Point' === geometry.type ) {
            style.radius = parseFloat( props.radius );

            if ( ! ( style.radius > 0 ) ) {
                return;
            }

            layer = L.circle( positionToLatLng( geometry.coordinates ), style );
        } else if ( 'LineString' === geometry.type ) {
            layer = L.polyline( lineToLatLngs( geometry.coordinates ), style );
        } else if ( 'rectangle' === props.shape_type ) {
            layer = L.rectangle( ringToBounds( ( geometry.coordinates || [] )[0] || [] ), style );
        } else if ( 'Polygon' === geometry.type ) {
            layer = L.polygon( ( geometry.coordinates || [] ).map( ringToLatLngs ), style );
        }

        if ( ! layer ) {
            return;
        }

        layer.on( 'click', function( event ) {
            // Or the click reaches the map and drops the selection.
            L.DomEvent.stopPropagation( event );

            if ( selectCb ) {
                selectCb( id );
            }
        } );

        // pm:edit covers vertex edits; pm:markerdragend is a circle's radius
        // handle. Both bound so a resize isn't silently lost.
        layer.on( 'pm:edit pm:markerdragend', function() {
            emitEdit( id );

            // The shape has a new centre, and the handle is still on the old one.
            if ( editingId === id ) {
                syncMoveHandle( rendered[ id ] );
            }
        } );

        const kind = shapeKind( props, geometry );

        // The shape's centre, not the pointer's: one toolbar spot per shape.
        // A polyline's bounds centre is usually off the line, so the pointer
        // is used there instead.
        layer.on( 'mouseover', function( event ) {
            if ( ! hoverCb ) {
                return;
            }

            const at = 'polyline' === kind ? event.latlng : layer.getBounds().getCenter();

            hoverCb( id, map.latLngToContainerPoint( at ) );
        } );

        layer.on( 'mouseout', function() {
            if ( hoverCb ) {
                hoverCb( '', null );
            }
        } );

        layer.addTo( map );

        rendered[ id ] = {
            layer:    layer,
            weight:   strokeWidth( props.stroke_width ),
            geometry: geometry,
            type:     kind
        };
    }

    /**
     * Which of the four shapes a stored feature is. shape_type is trusted only
     * where the geometry cannot tell: rectangle vs polygon.
     *
     * @since  3.0.0
     * @param  {object} props
     * @param  {object} geometry
     * @return {string}
     */
    function shapeKind( props, geometry ) {
        if ( 'Point' === geometry.type ) {
            return 'circle';
        }

        if ( 'LineString' === geometry.type ) {
            return 'polyline';
        }

        return 'rectangle' === props.shape_type ? 'rectangle' : 'polygon';
    }

    /**
     * Drop every rendered layer.
     *
     * @since  3.0.0
     * @return void
     */
    function clearRendered() {
        // Dropping a layer alone can leave Geoman's vertex markers behind.
        setEditable( '' );

        for ( let id in rendered ) {
            if ( Object.prototype.hasOwnProperty.call( rendered, id ) ) {
                map.removeLayer( rendered[ id ].layer );
            }
        }

        rendered = {};
    }

    /**
     * Whether a feature is a line rather than a region.
     *
     * @since  3.0.0
     * @param  {object} props
     * @param  {object} geometry
     * @return {boolean}
     */
    function isLine( props, geometry ) {
        return 'polyline' === ( props && props.shape_type ) || 'LineString' === ( geometry && geometry.type );
    }

    /**
     * Feature properties as Leaflet path options. A line is never filled:
     * Leaflet fills an L.Polyline when asked ( closing the route back on
     * itself ), which the other renderers can't do. The frontend carries the
     * same guard.
     *
     * @since  3.0.0
     * @param  {object} props
     * @param  {object} geometry
     * @return {object}
     */
    function layerStyle( props, geometry ) {
        const filled = !! props.fill && ! isLine( props, geometry );
        const dim    = false === props.active ? INACTIVE_DIM : 1;

        return {
            color:       safeColor( props.stroke_color ),
            weight:      strokeWidth( props.stroke_width ),
            opacity:     dim,
            fillColor:   safeColor( props.fill_color ),
            fill:        filled,
            fillOpacity: ( filled ? clampOpacity( props.fill_opacity ) : 0 ) * dim
        };
    }

    /**
     * The outer ring of a Leaflet getLatLngs() result. Holes are not drawn by
     * this editor and are dropped rather than flattened into the ring.
     *
     * @since  3.0.0
     * @param  {Array} latLngs
     * @return {Array}
     */
    function firstRing( latLngs ) {
        if ( latLngs && latLngs.length && Array.isArray( latLngs[0] ) ) {
            return latLngs[0];
        }

        return latLngs || [];
    }

    /**
     * Leaflet LatLngs to a closed GeoJSON ring.
     *
     * @since  3.0.0
     * @param  {Array} latLngs Objects with .lat / .lng.
     * @return {Array} [ [ lng, lat ], ... ] with the first position repeated last.
     */
    function latLngsToRing( latLngs ) {
        return closeRing( latLngsToLine( latLngs ) );
    }

    /**
     * Leaflet LatLngs to GeoJSON positions, in order, unclosed.
     *
     * @since  3.0.0
     * @param  {Array} latLngs
     * @return {Array}
     */
    function latLngsToLine( latLngs ) {
        const positions = [];

        for ( let i = 0; i < ( latLngs || [] ).length; i++ ) {
            positions.push( [ latLngs[ i ].lng, latLngs[ i ].lat ] );
        }

        return positions;
    }

    /**
     * A Leaflet bounds to the canonical rectangle ring.
     *
     * @since  3.0.0
     * @param  {object} bounds L.LatLngBounds.
     * @return {Array} SW, SE, NE, NW, SW.
     */
    function boundsToRing( bounds ) {
        const west  = bounds.getWest();
        const south = bounds.getSouth();
        const east  = bounds.getEast();
        const north = bounds.getNorth();

        return [
            [ west, south ],
            [ east, south ],
            [ east, north ],
            [ west, north ],
            [ west, south ]
        ];
    }

    /**
     * The canonical rectangle ring back to a Leaflet bounds -- L.rectangle()
     * takes two opposite corners, not a ring.
     *
     * @since  3.0.0
     * @param  {Array} ring [ [ lng, lat ], ... ].
     * @return {object} L.LatLngBounds.
     */
    function ringToBounds( ring ) {
        return L.latLngBounds( lineToLatLngs( ring || [] ) );
    }

    /**
     * A GeoJSON position to a Leaflet LatLng pair.
     *
     * @since  3.0.0
     * @param  {Array} position [ lng, lat ].
     * @return {Array} [ lat, lng ].
     */
    function positionToLatLng( position ) {
        return [ position[1], position[0] ];
    }

    /**
     * A GeoJSON ring to Leaflet LatLngs. The closing position is dropped:
     * Leaflet closes a polygon itself, and the duplicate puts two vertices on
     * top of each other once the shape is editable.
     *
     * @since  3.0.0
     * @param  {Array} ring
     * @return {Array}
     */
    function ringToLatLngs( ring ) {
        const open = ( ring || [] ).slice();

        if ( open.length > 3 ) {
            const first = open[0];
            const last  = open[ open.length - 1 ];

            if ( first[0] === last[0] && first[1] === last[1] ) {
                open.pop();
            }
        }

        return lineToLatLngs( open );
    }

    /**
     * GeoJSON positions to Leaflet LatLngs.
     *
     * @since  3.0.0
     * @param  {Array} positions
     * @return {Array}
     */
    function lineToLatLngs( positions ) {
        const latLngs = [];

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            latLngs.push( positionToLatLng( positions[ i ] ) );
        }

        return latLngs;
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
        map.setView( [ lat, lng ], zoom );
    }

    window.wpslShapesAdapter = {
        init:            init,
        getMap:          function() { return map; },

        /**
         * A lat / lng as a pixel in the map container, for controls placed over
         * the map. Every adapter answers this; only it knows how its engine
         * does it.
         *
         * @since  3.0.0
         * @param  {number} lat
         * @param  {number} lng
         * @return {object|null} { x, y }
         */
        toContainerPoint: function( lat, lng ) {
            return map ? map.latLngToContainerPoint( [ lat, lng ] ) : null;
        },

        panTo:           panTo,
        enableDraw:      engine.enableDraw,
        cancelDraw:      engine.cancelDraw,
        instructionKey:  engine.instructionKey,
        onShapeComplete: engine.onShapeComplete,
        onShapeSelect:   onShapeSelect,
        onShapeEdit:     onShapeEdit,
        onShapeHover:    onShapeHover,
        renderCollection: renderCollection,
        updateStyle:     updateStyle,
        removeShape:     removeShape,
        highlight:       highlight,
        setEditable:     setEditable
    };
} )();