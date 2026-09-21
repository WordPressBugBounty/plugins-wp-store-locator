/**
 * Map Shapes drawing for Google Maps.
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

    /* Centre handle size in pixels (.wpsl-shape-move-handle in map-shapes.css -- keep in step). */
    const MOVE_HANDLE_SIZE   = 16;
    const MOVE_HANDLE_STROKE = 2;

    /* Radius handle size in pixels -- the vertex-dot look (.wpsl-shape-handle). */
    const RADIUS_HANDLE_SIZE   = 10;
    const RADIUS_HANDLE_STROKE = 1;

    let map      = null;
    let selectCb = null;
    let editCb   = null;
    let hoverCb  = null;

    // The shared drawing engine, wrapping this map once init() has built it.
    const engine = shared.createEngine( {
        engineLabel: 'Google Maps',
        createAdapter: function() {
            return new window.terraDrawGoogleMapsAdapter.TerraDrawGoogleMapsAdapter( {
                lib:                 google.maps,
                map:                 map,
                coordinatePrecision: 9
            } );
        }
    } );

    // Empty OverlayView kept for its projection, which turns LatLng into
    // pixels for the hover toolbar. See centerPoint().
    let projector = null;

    let rendered    = {};
    let highlighted = '';
    let editingId   = '';

    // Centre handle that relocates the selected shape, and the Marker class
    // it's built from (imported in init()). See addMoveHandle().
    let moveHandle = null;
    let Marker     = null;

    // Circumference handle (due east of centre): the only way to change a
    // radius held in metres. Google's own circle editing is unused.
    let radiusHandle = null;

    // True during a centre-handle drag: geometry events fire every frame
    // (a new undo step each), so the move reports once, on dragend.
    let moving = false;

    /**
     * Build the map, the drawing engine and the completion listener.
     *
     * @since  3.0.0
     * @param  {Element}  el          The map container.
     * @param  {object}   startLatLng { lat, lng }, guaranteed numeric by PHP.
     * @param  {function} ready
     * @return void
     */
    function init( el, startLatLng, ready ) {
        // Marker lives in its own library, so it is requested by name --
        // without it setEditable() has no move handle to offer.
        Promise.all( [
            google.maps.importLibrary( 'maps' ),
            google.maps.importLibrary( 'marker' )
        ] ).then( function( libraries ) {
            const Map = libraries[0].Map;

            Marker = libraries[1].Marker;

            const mapOptions = {
                center:            startLatLng,
                zoom:              8,
                mapTypeControl:    false,
                streetViewControl: false,
                fullscreenControl: false,
                zoomControl:        true,
                zoomControlOptions: {
                    position: google.maps.ControlPosition.RIGHT_TOP
                }
            };

            // Resolve the configured style as wpsl-map-bootstrap.js does:
            // the selected source wins, an empty Map ID falls back to
            // DEMO_MAP_ID, and an unparseable JSON style does too.
            const mapStyle    = ( window.wpslMapShapes || {} ).mapStyle || {};
            let   parsedStyle = null;

            if ( mapStyle.json ) {
                try {
                    parsedStyle = JSON.parse( mapStyle.json );
                } catch ( e ) {
                    parsedStyle = null;
                }
            }

            if ( mapStyle.selected === 'json' && parsedStyle ) {
                mapOptions.styles = parsedStyle;
            } else if ( mapStyle.cloud_based && mapStyle.cloud_based.trim() ) {
                mapOptions.mapId = mapStyle.cloud_based;
            } else {
                mapOptions.mapId = 'DEMO_MAP_ID';
            }

            map = new Map( el, mapOptions );

            // OverlayView's three abstract methods must be implemented even for
            // a no-draw overlay. setMap() gives it a projection.
            projector = new google.maps.OverlayView();

            projector.onAdd    = function() {};
            projector.draw     = function() {};
            projector.onRemove = function() {};

            projector.setMap( map );

            // A click that reached the map hit no overlay ( Google doesn't
            // pass overlay clicks through ). An empty id drops the selection.
            map.addListener( 'click', function() {
                if ( ! selectCb ) {
                    return;
                }

                // The finishing press's second delivery, not a deselect.
                if ( engine.consumeFinishClick() ) {
                    return;
                }

                selectCb( '' );
            } );

            google.maps.event.addListenerOnce( map, 'projection_changed', function() {
                engine.start( ready );
            } );
        } ).catch( engine.reportLoadFailure );
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
     * A Google mouse event's position inside the map container.
     *
     * @since  3.0.0
     * @param  {object} event
     * @return {object|null} { x, y }.
     */
    function pointerPoint( event ) {
        const dom = event && event.domEvent;

        if ( ! dom || ! map || ! map.getDiv() ) {
            return null;
        }

        const box = map.getDiv().getBoundingClientRect();

        return { x: dom.clientX - box.left, y: dom.clientY - box.top };
    }

    /**
     * The container pixel a rendered overlay's centre falls on.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return {object|null} { x, y }.
     */
    function centerPoint( entry ) {
        const projection = projector ? projector.getProjection() : null;
        return projection ? projection.fromLatLngToContainerPixel( overlayCenter( entry ) ) : null;
    }

    /**
     * Any lat / lng as a container pixel, for controls the layout places over the map.
     *
     * @since  3.0.0
     * @param  {number} lat
     * @param  {number} lng
     * @return {object|null} { x, y }, or null before the projection exists.
     */
    function toContainerPoint( lat, lng ) {
        const projection = projector ? projector.getProjection() : null;
        return projection ? projection.fromLatLngToContainerPixel( new google.maps.LatLng( lat, lng ) ) : null;
    }

    /**
     * A rendered overlay's own centre.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return {object} A google.maps.LatLng.
     */
    function overlayCenter( entry ) {
        const overlay = entry.overlay;

        if ( 'circle' === entry.type ) {
            return overlay.getCenter();
        }

        if ( 'rectangle' === entry.type ) {
            return overlay.getBounds().getCenter();
        }

        const path   = 'polyline' === entry.type ? overlay.getPath() : overlay.getPaths().getAt( 0 );
        const bounds = new google.maps.LatLngBounds();

        path.forEach( function( latLng ) {
            bounds.extend( latLng );
        } );

        return bounds.getCenter();
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

        // clearRendered() drops the editing state; put back on the new overlay below.
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
     * @param  {object} props
     * @return void
     */
    function updateStyle( featureId, props ) {
        const entry = rendered[ featureId ];

        if ( ! entry ) {
            return;
        }

        const options = overlayOptions( props );

        if ( editingId === featureId ) {
            options.editable  = 'circle' !== entry.type;
            options.draggable = true;
        }

        entry.weight = strokeWidth( props.stroke_width );
        entry.overlay.setOptions( options );
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

        entry.overlay.setMap( null );

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
        if ( featureId && editingId === featureId && rendered[ featureId ] ) {
            return;
        }

        if ( editingId && rendered[ editingId ] ) {
            rendered[ editingId ].overlay.setOptions( { editable: false, draggable: false } );
        }

        removeMoveHandle();
        removeRadiusHandle();

        editingId = '';

        const entry = rendered[ featureId ];

        if ( ! entry ) {
            return;
        }

        editingId = featureId;

        entry.overlay.setOptions( {
            editable:  'circle' !== entry.type,
            draggable: true
        } );

        addMoveHandle( featureId, entry );

        if ( 'circle' === entry.type ) {
            addRadiusHandle( featureId, entry );
        }
    }

    /**
     * The circle in the middle of the selected shape that moves the whole of it.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {object} entry     The rendered entry to attach the handle to.
     * @return void
     */
    function addMoveHandle( featureId, entry ) {
        if ( ! Marker ) {
            return;
        }

        removeMoveHandle();

        let origin = null;

        moveHandle = new Marker( {
            position:  overlayCenter( entry ),
            map:       map,
            draggable: true,
            cursor:    'move',
            zIndex: 1000,
            icon: {
                path:         google.maps.SymbolPath.CIRCLE,
                scale:        ( MOVE_HANDLE_SIZE - MOVE_HANDLE_STROKE ) / 2,
                fillColor:    '#2271b1',
                fillOpacity:  1,
                strokeColor:  '#fff',
                strokeWeight: MOVE_HANDLE_STROKE
            }
        } );

        moveHandle.addListener( 'dragstart', function() {
            origin = moveHandle.getPosition();
            moving = true;
        } );

        moveHandle.addListener( 'drag', function() {
            if ( ! origin ) {
                return;
            }

            const to = moveHandle.getPosition();

            shiftOverlay( entry, to.lat() - origin.lat(), to.lng() - origin.lng() );

            origin = to;

            syncRadiusHandle( entry );
        } );

        moveHandle.addListener( 'dragend', function() {
            origin = null;
            moving = false;

            // Once, now that the shape has stopped moving -- see `moving`.
            emitEdit( featureId );
        } );
    }

    /**
     * Put the move handle back in the middle of the shape it belongs to.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return void
     */
    function syncMoveHandle( entry ) {
        if ( moveHandle && entry ) {
            moveHandle.setPosition( overlayCenter( entry ) );
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
            moveHandle.setMap( null );
            moveHandle = null;
        }

        moving = false;
    }

    /**
     * The handle that resizes a selected circle.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {object} entry     The rendered circle to attach the handle to.
     * @return void
     */
    function addRadiusHandle( featureId, entry ) {
        if ( ! Marker ) {
            return;
        }

        removeRadiusHandle();

        radiusHandle = new Marker( {
            position:  radiusHandlePosition( entry ),
            map:       map,
            draggable: true,
            cursor:    'move',
            zIndex:    1000,
            icon: {
                path:         google.maps.SymbolPath.CIRCLE,
                scale:        ( RADIUS_HANDLE_SIZE - RADIUS_HANDLE_STROKE ) / 2,
                fillColor:    '#fff',
                fillOpacity:  1,
                strokeColor:  '#2271b1',
                strokeWeight: RADIUS_HANDLE_STROKE
            }
        } );

        radiusHandle.addListener( 'dragstart', function() {
            moving = true;
        } );

        radiusHandle.addListener( 'drag', function() {
            const center = entry.overlay.getCenter();
            const at     = radiusHandle.getPosition();
            const meters = haversineMeters( center.lat(), center.lng(), at.lat(), at.lng() );

            if ( meters > 0 ) {
                entry.overlay.setRadius( meters );
            }
        } );

        radiusHandle.addListener( 'dragend', function() {
            moving = false;

            syncRadiusHandle( entry );

            emitEdit( featureId );
        } );
    }

    /**
     * Where the radius handle sits: on the circumference, due east.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return {object} A google.maps.LatLng.
     */
    function radiusHandlePosition( entry ) {
        return new google.maps.LatLng(
            entry.overlay.getCenter().lat(),
            entry.overlay.getBounds().getNorthEast().lng()
        );
    }

    /**
     * Put the radius handle back on the circumference it belongs to.
     *
     * @since  3.0.0
     * @param  {object} entry
     * @return void
     */
    function syncRadiusHandle( entry ) {
        if ( radiusHandle && entry && 'circle' === entry.type ) {
            radiusHandle.setPosition( radiusHandlePosition( entry ) );
        }
    }

    /**
     * Take the radius handle off the map.
     *
     * @since  3.0.0
     * @return void
     */
    function removeRadiusHandle() {
        if ( radiusHandle ) {
            radiusHandle.setMap( null );
            radiusHandle = null;
        }
    }

    /**
     * Metres between two points.
     *
     * @since  3.0.0
     * @param  {number} lat1
     * @param  {number} lng1
     * @param  {number} lat2
     * @param  {number} lng2
     * @return {number}
     */
    function haversineMeters( lat1, lng1, lat2, lng2 ) {
        const EARTH_RADIUS = 6378137;
        const toRad        = Math.PI / 180;

        const dLat = ( lat2 - lat1 ) * toRad;
        const dLng = ( lng2 - lng1 ) * toRad;

        const h = Math.sin( dLat / 2 ) * Math.sin( dLat / 2 ) +
            Math.cos( lat1 * toRad ) * Math.cos( lat2 * toRad ) * Math.sin( dLng / 2 ) * Math.sin( dLng / 2 );

        return 2 * EARTH_RADIUS * Math.asin( Math.min( 1, Math.sqrt( h ) ) );
    }

    /**
     * Shift a rendered overlay by a lat / lng delta.
     * 
     * @since  3.0.0
     * @param  {object} entry
     * @param  {number} dLat
     * @param  {number} dLng
     * @return void
     */
    function shiftOverlay( entry, dLat, dLng ) {
        const overlay = entry.overlay;

        if ( 'circle' === entry.type ) {
            overlay.setCenter( shiftLatLng( overlay.getCenter(), dLat, dLng ) );

            return;
        }

        if ( 'rectangle' === entry.type ) {
            const bounds = overlay.getBounds();

            overlay.setBounds( new google.maps.LatLngBounds(
                shiftLatLng( bounds.getSouthWest(), dLat, dLng ),
                shiftLatLng( bounds.getNorthEast(), dLat, dLng )
            ) );

            return;
        }

        if ( 'polyline' === entry.type ) {
            shiftPathInPlace( overlay.getPath(), dLat, dLng );

            return;
        }

        // Every ring, not just the outer one: a polygon's holes travel with it.
        overlay.getPaths().forEach( function( path ) {
            shiftPathInPlace( path, dLat, dLng );
        } );
    }

    /**
     * Move one path's positions, WITHOUT replacing the path.
     *
     * @since  3.0.0
     * @param  {object} path A google.maps.MVCArray of LatLng.
     * @param  {number} dLat
     * @param  {number} dLng
     * @return void
     */
    function shiftPathInPlace( path, dLat, dLng ) {
        const moved = shiftPath( path.getArray(), dLat, dLng );
        for ( let i = 0; i < moved.length; i++ ) {
            path.setAt( i, new google.maps.LatLng( moved[ i ].lat, moved[ i ].lng ) );
        }
    }

    /**
     * One path's positions, all moved by the same offset.
     *
     * @since  3.0.0
     * @param  {Array}  latLngs
     * @param  {number} dLat
     * @param  {number} dLng
     * @return {Array} Plain { lat, lng } literals.
     */
    function shiftPath( latLngs, dLat, dLng ) {
        return latLngs.map( function( latLng ) {
            return shiftLatLng( latLng, dLat, dLng );
        } );
    }

    /**
     * One LatLng moved by an offset, kept inside the coordinate range.
     * 
     * @since  3.0.0
     * @param  {object} latLng A google.maps.LatLng.
     * @param  {number} dLat
     * @param  {number} dLng
     * @return {object} { lat, lng }.
     */
    function shiftLatLng( latLng, dLat, dLng ) {
        const lng = latLng.lng() + dLng;

        return {
            lat: Math.min( 90, Math.max( -90, latLng.lat() + dLat ) ),
            lng: ( ( lng + 540 ) % 360 ) - 180
        };
    }

    /**
     * Listen for the geometry changes an editable overlay reports.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {object} overlay
     * @param  {string} type      The editor's shape type.
     * @return void
     */
    function bindEditListeners( featureId, overlay, type ) {
        const report = function() {
            // Nothing while the centre handle is dragged.
            if ( moving ) {
                return;
            }

            emitEdit( featureId );

            // The shape has a new centre and the handles still stand on the old one.
            if ( editingId === featureId ) {
                syncMoveHandle( rendered[ featureId ] );
                syncRadiusHandle( rendered[ featureId ] );
            }
        };

        overlay.addListener( 'drag', function() {
            if ( editingId === featureId ) {
                syncMoveHandle( rendered[ featureId ] );
                syncRadiusHandle( rendered[ featureId ] );
            }
        } );

        overlay.addListener( 'dragend', report );

        if ( 'circle' === type ) {
            overlay.addListener( 'radius_changed', report );
            overlay.addListener( 'center_changed', report );

            return;
        }

        if ( 'rectangle' === type ) {
            overlay.addListener( 'bounds_changed', report );

            return;
        }

        // Polyline: one path; polygon: one per ring. Geometry events fire on
        // each path's MVCArray, not the overlay, so each is bound separately.
        const paths = 'polyline' === type ? [ overlay.getPath() ] : overlay.getPaths().getArray();

        for ( let i = 0; i < paths.length; i++ ) {
            paths[ i ].addListener( 'insert_at', report );
            paths[ i ].addListener( 'set_at', report );
            paths[ i ].addListener( 'remove_at', report );
        }
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
        if ( ! entry || ! editCb || editingId !== featureId ) {
            return;
        }

        const geometry = overlayGeometry( entry );
        if ( ! geometry ) {
            return;
        }

        // A circle's size is not in its geometry, so the radius travels alongside.
        const properties = 'circle' === entry.type ? { radius: entry.overlay.getRadius() } : null;
        editCb( featureId, geometry, properties );
    }

    /**
     * A rendered overlay read back out as GeoJSON geometry.
     * 
     * @since  3.0.0
     * @param  {object} entry
     * @return {object|null}
     */
    function overlayGeometry( entry ) {
        const overlay = entry.overlay;

        if ( 'circle' === entry.type ) {
            const center = overlay.getCenter();

            return { type: 'Point', coordinates: [ center.lng(), center.lat() ] };
        }

        if ( 'rectangle' === entry.type ) {
            return { type: 'Polygon', coordinates: [ boundsToRing( overlay.getBounds() ) ] };
        }

        if ( 'polyline' === entry.type ) {
            return { type: 'LineString', coordinates: pathToLine( overlay.getPath().getArray() ) };
        }

        const paths = overlay.getPaths().getArray();
        const rings = [];

        for ( let i = 0; i < paths.length; i++ ) {
            rings.push( pathToRing( paths[ i ].getArray() ) );
        }

        return { type: 'Polygon', coordinates: rings };
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

        const options = overlayOptions( props );
        let overlay = null;

        if ( 'Point' === geometry.type ) {
            options.radius = parseFloat( props.radius );

            if ( ! ( options.radius > 0 ) ) {
                return;
            }

            options.center = positionToLatLng( geometry.coordinates );
            overlay = new google.maps.Circle( options );
        } else if ( 'LineString' === geometry.type ) {
            options.path = lineToPath( geometry.coordinates );
            overlay = new google.maps.Polyline( options );
        } else if ( 'rectangle' === props.shape_type ) {
            options.bounds = ringToBounds( ( geometry.coordinates || [] )[0] || [] );
            overlay = new google.maps.Rectangle( options );
        } else if ( 'Polygon' === geometry.type ) {
            options.paths = ( geometry.coordinates || [] ).map( ringToPath );
            overlay = new google.maps.Polygon( options );
        }

        if ( ! overlay ) {
            return;
        }

        const type = shapeKind( props, geometry );

        overlay.setMap( map );
        overlay.addListener( 'click', function() {
            if ( selectCb ) {
                selectCb( id );
            }
        } );

        // mouseover only: the toolbar is placed once and stays put.
        overlay.addListener( 'mouseover', function( event ) {
            if ( ! hoverCb ) {
                return;
            }

            const at = 'polyline' === type ? pointerPoint( event ) : centerPoint( rendered[ id ] );

            hoverCb( id, at );
        } );

        overlay.addListener( 'mouseout', function() {
            if ( hoverCb ) {
                hoverCb( '', null );
            }
        } );

        bindEditListeners( id, overlay, type );

        rendered[ id ] = { overlay: overlay, weight: strokeWidth( props.stroke_width ), type: type };
    }

    /**
     * Which of the four shapes a stored feature is.
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
     * Drop every rendered overlay.
     *
     * @since  3.0.0
     * @return void
     */
    function clearRendered() {
        setEditable( '' );

        for ( let id in rendered ) {
            if ( Object.prototype.hasOwnProperty.call( rendered, id ) ) {
                rendered[ id ].overlay.setMap( null );
            }
        }

        rendered = {};
    }

    /**
     * Feature properties as Google overlay options.
     *
     * @since  3.0.0
     * @param  {object} props
     * @return {object}
     */
    function overlayOptions( props ) {
        const dim = false === props.active ? INACTIVE_DIM : 1;

        return {
            strokeColor:   safeColor( props.stroke_color ),
            strokeWeight:  strokeWidth( props.stroke_width ),
            strokeOpacity: dim,
            fillColor:     safeColor( props.fill_color ),
            fillOpacity:   ( props.fill ? clampOpacity( props.fill_opacity ) : 0 ) * dim,
            clickable:     true,
            editable:      false,
            draggable:     false
        };
    }

    /**
     * A Google path to a closed GeoJSON ring.
     *
     * @since  3.0.0
     * @param  {Array} latLngs Objects with .lat() / .lng().
     * @return {Array}
     */
    function pathToRing( latLngs ) {
        return closeRing( pathToLine( latLngs ) );
    }

    /**
     * A Google path to GeoJSON positions, in order, unclosed.
     *
     * @since  3.0.0
     * @param  {Array} latLngs
     * @return {Array}
     */
    function pathToLine( latLngs ) {
        const positions = [];

        for ( let i = 0; i < ( latLngs || [] ).length; i++ ) {
            positions.push( [ latLngs[ i ].lng(), latLngs[ i ].lat() ] );
        }

        return positions;
    }

    /**
     * Convert a Google bounds to a GeoJSON ring.
     *
     * @since  3.0.0
     * @param  {object} bounds
     * @return {Array} SW, SE, NE, NW, SW.
     */
    function boundsToRing( bounds ) {
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();

        const west  = sw.lng();
        const south = sw.lat();
        const east  = ne.lng();
        const north = ne.lat();

        return [
            [ west, south ],
            [ east, south ],
            [ east, north ],
            [ west, north ],
            [ west, south ]
        ];
    }

    /**
     * The canonical rectangle ring back to a Google bounds.
     * 
     * @since  3.0.0
     * @param  {Array} ring [ [ lng, lat ], ... ].
     * @return {object} google.maps.LatLngBounds.
     */
    function ringToBounds( ring ) {
        const bounds = new google.maps.LatLngBounds();

        for ( let i = 0; i < ( ring || [] ).length; i++ ) {
            bounds.extend( positionToLatLng( ring[ i ] ) );
        }

        return bounds;
    }

    /**
     * A GeoJSON position to a Google LatLng literal.
     *
     * @since  3.0.0
     * @param  {Array} position [ lng, lat ].
     * @return {object} { lat, lng }.
     */
    function positionToLatLng( position ) {
        return { lat: position[1], lng: position[0] };
    }

    /**
     * A GeoJSON ring to a Google path.
     *
     * @since  3.0.0
     * @param  {Array} ring
     * @return {Array}
     */
    function ringToPath( ring ) {
        const open = ( ring || [] ).slice();

        if ( open.length > 3 ) {
            const first = open[0];
            const last  = open[ open.length - 1 ];

            if ( first[0] === last[0] && first[1] === last[1] ) {
                open.pop();
            }
        }

        return lineToPath( open );
    }

    /**
     * GeoJSON positions to a Google path.
     *
     * @since  3.0.0
     * @param  {Array} positions
     * @return {Array}
     */
    function lineToPath( positions ) {
        const path = [];

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            path.push( positionToLatLng( positions[ i ] ) );
        }

        return path;
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
        map.setCenter( { lat: lat, lng: lng } );
        map.setZoom( zoom );
    }

    window.wpslShapesAdapter = {
        init:             init,
        getMap:           function() { return map; },
        panTo:            panTo,
        enableDraw:       engine.enableDraw,
        cancelDraw:       engine.cancelDraw,
        instructionKey:   engine.instructionKey,
        onShapeComplete:  engine.onShapeComplete,
        onShapeSelect:    onShapeSelect,
        onShapeEdit:      onShapeEdit,
        onShapeHover:     onShapeHover,
        toContainerPoint: toContainerPoint,
        renderCollection: renderCollection,
        updateStyle:      updateStyle,
        removeShape:      removeShape,
        highlight:        highlight,
        setEditable:      setEditable,
    };
} )();