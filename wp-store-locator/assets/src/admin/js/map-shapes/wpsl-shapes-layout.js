/**
 * Map Shapes editor: layout glue.
 *
 * Sizes the map to the window, fits selected/duplicated shapes on screen,
 * and floats the undo button against its shape.
 *
 * @since 3.0.0
 */
( function() {
    'use strict';

    const wrap = document.querySelector( '.wpsl-map-shapes-editor' );

    if ( ! wrap ) {
        return;
    }

    const panel   = document.getElementById( 'wpsl-shapes-style' );
    const mapEl   = document.getElementById( 'wpsl-shapes-map' );
    const service = mapEl ? mapEl.getAttribute( 'data-map-service' ) : '';
    let lastId    = '';
    let wasShown  = false;

    if ( ! panel || ! mapEl ) {
        return;
    }

    // What sits below the map: the card's bottom padding, the admin footer,
    // and slack to keep the window from scrolling. Subtracted from the room
    // left under the map's top edge.
    const CARD_PADDING  = 24;
    const FOOTER_HEIGHT = 40;
    const BOTTOM_SLACK  = 50;
    const MIN_HEIGHT    = 480;

    let lastHeight = 0;
    let observing  = false;

    /**
     * Give the map every pixel below it. Measured in JS, not CSS: the WP admin
     * UI above it ( menu, notices ) has no fixed height. A minimum, not a
     * height, so the map can stretch with the controls column ( shared grid row ).
     */
    function sizeMap() {
        const top    = mapEl.getBoundingClientRect().top;
        const height = Math.max( MIN_HEIGHT, Math.round( window.innerHeight - top - CARD_PADDING - FOOTER_HEIGHT - BOTTOM_SLACK ) );

        if ( height === lastHeight ) {
            return;
        }

        lastHeight            = height;
        mapEl.style.minHeight = height + 'px';

        // Without an observer to catch the box changing, this is the only
        // moment the provider can be told about it.
        if ( ! observing ) {
            notifyResize();
        }
    }

    /**
     * Tell the provider its canvas changed size -- untold, it keeps rendering
     * into the old box with grey beyond it and click coordinates off.
     *
     * @since  3.0.0
     * @return void
     */
    function notifyResize() {
        const adapter = window.wpslShapesAdapter;
        const map     = adapter && adapter.getMap ? adapter.getMap() : null;

        if ( ! map ) {
            return;
        }

        if ( 'function' === typeof map.invalidateSize ) {
            map.invalidateSize(); // Leaflet ( osm / stadia )
        } else if ( 'function' === typeof map.resize ) {
            map.resize(); // Mapbox GL
        } else if ( window.google && window.google.maps && window.google.maps.event ) {
            window.google.maps.event.trigger( map, 'resize' );
        }
    }

    /*
     * The map's box also changes whenever the controls column does ( panel
     * opening, picker, field wrapping ); watching the element catches them all.
     */
    if ( 'function' === typeof window.ResizeObserver ) {
        observing = true;

        new window.ResizeObserver( function() {
            window.requestAnimationFrame( notifyResize );
        } ).observe( mapEl );
    }

    sizeMap();

    let sized = false;

    /*
     * The map is created after this file runs, so size it once more when it
     * exists. The same poll places the toolbar and only stops once that
     * succeeds; the zoom control it measures appears at the provider's
     * choosing. The timeout below is the backstop.
     */
    const sizeWhenReady = window.setInterval( function() {
        const adapter = window.wpslShapesAdapter;

        if ( ! adapter || ! adapter.getMap || ! adapter.getMap() ) {
            return;
        }

        if ( ! sized ) {
            sized      = true;
            lastHeight = 0;

            sizeMap();
        }

        if ( positionToolbar() && findZoomControl() ) {
            window.clearInterval( sizeWhenReady );
        }
    }, 200 );

    window.setTimeout( function() {
        window.clearInterval( sizeWhenReady );
    }, 10000 );

    // Zoom control selectors per provider.
    const ZOOM_SELECTORS = [
        '.leaflet-control-zoom',
        '.mapboxgl-ctrl-group',
        '.gm-bundled-control'
    ];

    const FALLBACK_ZOOM = 34;
    const MIN_CLEARANCE = 8;

    // Mirrors --wpsl-shapes-controls-inset in map-shapes.css; change both together.
    const INSET = 12;

    // Toggles the toolbar's stacked layout; CSS owns the styling.
    const STACKED_CLASS = 'wpsl-shapes-controls-stacked';

    let lastToolbarLayout = '';

    /**
     * Place the drawing toolbar: centred between search and zoom when it
     * fits, dropped to its own row under the search when it doesn't.
     *
     * @return {boolean} Whether a measurement was taken -- what tells the
     *                   startup poll it has nothing left to wait for.
     */
    function positionToolbar() {
        const toolbar  = document.getElementById( 'wpsl-shapes-toolbar' );
        const searchEl = document.getElementById( 'wpsl-shapes-search' );
        const wrapEl   = document.getElementById( 'wpsl-shapes-map-wrap' );

        if ( ! toolbar || ! searchEl || ! wrapEl ) {
            return false;
        }

        // Both start display:none until the editor reveals them ( null
        // offsetParent ) -- measuring now would read zeroes.
        if ( null === toolbar.offsetParent || null === searchEl.offsetParent ) {
            return false;
        }

        const wrapBox   = wrapEl.getBoundingClientRect();
        const searchBox = searchEl.getBoundingClientRect();

        // Left edge of the zoom control, in the wrapper's coordinates. With
        // no control found, the right inset stands in for it.
        const zoomEl   = findZoomControl();
        const zoomLeft = zoomEl
            ? zoomEl.getBoundingClientRect().left - wrapBox.left
            : wrapBox.width - FALLBACK_ZOOM - MIN_CLEARANCE;

        const searchRight = searchBox.right - wrapBox.left;
        const half        = toolbar.offsetWidth / 2;

        // Does the toolbar fit between the two with clearance on both sides?
        const stacked = ( zoomLeft - searchRight ) < ( toolbar.offsetWidth + MIN_CLEARANCE * 2 );

        let offset = 0;
        let top    = INSET;

        if ( stacked ) {
            top = Math.round( searchBox.bottom - wrapBox.top + MIN_CLEARANCE );
        } else {
            offset = Math.round( ( searchRight + zoomLeft ) / 2 - wrapBox.width / 2 );
        }

        // One string, so a run that changes nothing writes nothing.
        const signature = stacked + '|' + offset + '|' + top;

        if ( signature === lastToolbarLayout ) {
            return true;
        }

        lastToolbarLayout = signature;

        wrapEl.classList.toggle( STACKED_CLASS, stacked );

        wrapEl.style.setProperty( '--wpsl-shapes-toolbar-offset', offset + 'px' );
        wrapEl.style.setProperty( '--wpsl-shapes-toolbar-top', top + 'px' );

        return true;
    }

    /**
     * The provider's zoom control, if it has rendered one yet.
     *
     * @return {Element|null}
     */
    function findZoomControl() {
        for ( let i = 0; i < ZOOM_SELECTORS.length; i++ ) {
            const found = mapEl.querySelector( ZOOM_SELECTORS[ i ] );

            if ( found && found.offsetWidth ) {
                return found;
            }
        }

        return null;
    }

    let resizeTimer = null;

    window.addEventListener( 'resize', function() {
        window.clearTimeout( resizeTimer );

        resizeTimer = window.setTimeout( function() {
            sizeMap();
            positionToolbar();
        }, 150 );
    } );

    /*
     * Two boxes change without a window resize: the map's ( admin menu
     * collapsing ) and the search's ( status message ); the toolbar's
     * placement depends on both.
     */
    if ( 'function' === typeof window.ResizeObserver ) {
        let observerTimer = null;

        const toolbarObserver = new window.ResizeObserver( function() {
            window.clearTimeout( observerTimer );

            observerTimer = window.setTimeout( positionToolbar, 100 );
        } );

        toolbarObserver.observe( mapEl );

        const searchToObserve = document.getElementById( 'wpsl-shapes-search' );
        if ( searchToObserve ) {
            toolbarObserver.observe( searchToObserve );
        }
    }

    // Suppresses selection-refocus while a duplicate fit-both is pending.
    let fitPending = false;

    // Max zoom for a selection fit -- shapes are small, and fitting one to
    // the whole viewport lands on street-level nothing.
    const SELECT_MAX_ZOOM = 16;

    // How much of the viewport edges a shape must clear to count as visible.
    const VISIBLE_MARGIN = 0.04;

    /**
     * Bring the selected shape fully into view. Fits, not pans, so a shape
     * half-off the viewport is fully framed; one already wholly visible is
     * left alone.
     */
    function refocus() {
        const editor  = window.wpslShapesEditor;
        const adapter = window.wpslShapesAdapter;

        if ( fitPending || ! editor || ! editor.selectedFeature || ! adapter || ! adapter.getMap ) {
            return;
        }

        const feature = editor.selectedFeature();

        if ( ! feature ) {
            return;
        }

        const box = editor.placement.boundingBox( feature.geometry, feature.properties );

        // Recorded either way, so the same shape is not decided about twice.
        lastId = ( feature.properties || {} ).id || '';

        if ( isFullyVisible( box ) ) {
            return;
        }

        fitBox( box, SELECT_MAX_ZOOM );
    }

    /**
     * The viewport as one plain { west, south, east, north } object -- each
     * provider names its edges differently.
     *
     * @return {object|null}
     */
    function viewBox() {
        const adapter = window.wpslShapesAdapter;
        const map     = adapter && adapter.getMap ? adapter.getMap() : null;

        if ( ! map || 'function' !== typeof map.getBounds ) {
            return null;
        }

        const bounds = map.getBounds();

        if ( ! bounds ) {
            return null;
        }

        if ( 'gmaps' === service ) {
            if ( 'function' !== typeof bounds.getNorthEast ) {
                return null;
            }

            const ne = bounds.getNorthEast();
            const sw = bounds.getSouthWest();

            return { west: sw.lng(), south: sw.lat(), east: ne.lng(), north: ne.lat() };
        }

        if ( 'function' === typeof bounds.getWest ) {
            // Leaflet and Mapbox GL share these four accessors.
            return { west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth() };
        }

        return null;
    }

    /**
     * Would the box fit in the viewport at the current zoom, padding taken
     * off? Asked so Google can be panned instead of fitted ( see fitBox() ).
     * Approximate in latitude ( Mercator ); wrong the safe way, towards fitBounds.
     *
     * @param {Array}  box     [ west, south, east, north ].
     * @param {object} padding { top, bottom, left, right } in pixels.
     */
    function fitsAtCurrentZoom( box, padding ) {
        const view = viewBox();

        if ( ! view || ! box || view.west > view.east ) {
            return false;
        }

        const width  = mapEl.clientWidth;
        const height = mapEl.clientHeight;

        if ( ! width || ! height ) {
            return false;
        }

        const usableX = ( width - padding.left - padding.right ) / width;
        const usableY = ( height - padding.top - padding.bottom ) / height;

        if ( usableX <= 0 || usableY <= 0 ) {
            return false;
        }

        return ( box[2] - box[0] ) <= ( view.east - view.west ) * usableX &&
               ( box[3] - box[1] ) <= ( view.north - view.south ) * usableY;
    }

    /**
     * Is the box wholly inside the current view? A margin means a shape
     * touching the map's edge doesn't count, since that's when a fit helps.
     */
    function isFullyVisible( box ) {
        const view = viewBox();

        if ( ! view || ! box ) {
            return false;
        }

        // A viewport crossing the antimeridian has west > east; rare enough
        // here to refuse rather than solve.
        if ( view.west > view.east ) {
            return false;
        }

        const marginX = ( view.east - view.west ) * VISIBLE_MARGIN;
        const marginY = ( view.north - view.south ) * VISIBLE_MARGIN;

        return box[0] >= ( view.west + marginX ) &&
               box[1] >= ( view.south + marginY ) &&
               box[2] <= ( view.east - marginX ) &&
               box[3] <= ( view.north - marginY );
    }

    /**
     * Refocus when the panel just opened, or when it is open and the
     * selection moved to a different shape.
     */
    function maybeRefocus() {
        const shown = 'none' !== panel.style.display;

        if ( ! shown ) {
            wasShown = false;
            lastId   = '';

            return;
        }

        const feature = window.wpslShapesEditor && window.wpslShapesEditor.selectedFeature
            ? window.wpslShapesEditor.selectedFeature()
            : null;

        const id = feature && feature.properties ? feature.properties.id : '';

        if ( ! wasShown || ( id && id !== lastId ) ) {
            // Next frame, so the editor has finished populating the panel and
            // its width is the rendered one.
            window.requestAnimationFrame( refocus );
        }

        wasShown = true;
    }

    // Panel show()/hide() toggles the style attribute; that's the select/
    // deselect signal.
    new MutationObserver( maybeRefocus ).observe( panel, { attributes: true, attributeFilter: [ 'style' ] } );

    // Shape-to-shape switches don't change panel visibility, so also watch
    // map clicks; the delay lets the editor's click handler settle first.
    mapEl.addEventListener( 'click', function() {
        window.setTimeout( maybeRefocus, 60 );
    }, true );

    // The shape picker is a third entry point neither watcher catches. All
    // clicks go to maybeRefocus unfiltered; it compares ids, so a no-change
    // click costs one comparison.
    const pickerEl = document.querySelector( '.wpsl-shape-picker' );

    if ( pickerEl ) {
        pickerEl.addEventListener( 'click', function() {
            window.setTimeout( maybeRefocus, 60 );
        }, true );

        pickerEl.addEventListener( 'keydown', function() {
            window.setTimeout( maybeRefocus, 60 );
        }, true );
    }

    /**
     * Fit the union of two shapes' bounds into the viewport. Never zooms IN
     * past the current level: when both already fit, the move is a small pan.
     */
    function fitBoth( source, copy ) {
        const editor = window.wpslShapesEditor;

        const a = source && editor ? editor.placement.boundingBox( source.geometry, source.properties ) : null;
        const b = copy && editor ? editor.placement.boundingBox( copy.geometry, copy.properties ) : null;

        if ( ! a || ! b ) {
            return;
        }

        fitBox( [
            Math.min( a[0], b[0] ),
            Math.min( a[1], b[1] ),
            Math.max( a[2], b[2] ),
            Math.max( a[3], b[3] )
        ] );
    }

    /**
     * Fit a [ west, south, east, north ] box into the visible viewport.
     *
     * @param {Array}  box       [ west, south, east, north ].
     * @param {number} [maxZoom] How far in the fit may go. Omitted means not at
     *                           all -- see the note on the cap below.
     */
    function fitBox( box, maxZoom ) {
        const adapter = window.wpslShapesAdapter;
        const map     = adapter && adapter.getMap ? adapter.getMap() : null;

        if ( ! map || ! box ) {
            return;
        }

        const west  = box[0];
        const south = box[1];
        const east  = box[2];
        const north = box[3];

        const padRight = 30;

        /*
         * How far in the fit may go. With a maximum: closer is allowed, but
         * never further out than the map already is ( Math.max ). Without
         * one: no zooming in; the duplicate fit pans, not lurches.
         */
        const cap = maxZoom ? Math.max( maxZoom, map.getZoom() ) : map.getZoom();

        const padding = { top: 60, bottom: 50, left: 30, right: padRight };

        if ( 'gmaps' === service ) {
            const bounds = new google.maps.LatLngBounds( { lat: south, lng: west }, { lat: north, lng: east } );

            if ( ! maxZoom && fitsAtCurrentZoom( box, padding ) ) {
                map.panToBounds( bounds, padding );

                return;
            }

            map.fitBounds( bounds, padding );

            google.maps.event.addListenerOnce( map, 'idle', function() {
                if ( map.getZoom() > cap ) {
                    map.setZoom( cap );
                }
            } );
        } else if ( 'mapbox' === service ) {
            map.fitBounds( [ [ west, south ], [ east, north ] ], {
                padding: padding,
                maxZoom: cap
            } );
        } else {
            map.fitBounds( [ [ south, west ], [ north, east ] ], {
                paddingTopLeft:     [ 30, 60 ],
                paddingBottomRight: [ padRight, 50 ],
                maxZoom: cap
            } );
        }
    }

    /**
     * Put every shape in the viewport at once.
     *
     * The union of all their bounding boxes, capped so a single small shape
     * does not open at street level. Does nothing without shapes, which leaves
     * the map on the configured start location.
     *
     * @since  3.0.0
     * @return void
     */
    function fitAll() {
        const editor   = window.wpslShapesEditor;
        const features = editor && editor.allFeatures ? editor.allFeatures() : [];

        let box = null;

        for ( let i = 0; i < features.length; i++ ) {
            const feature = features[ i ];
            const next    = editor.placement.boundingBox( feature.geometry, feature.properties );

            if ( ! next ) {
                continue;
            }

            box = box ? [
                Math.min( box[0], next[0] ),
                Math.min( box[1], next[1] ),
                Math.max( box[2], next[2] ),
                Math.max( box[3], next[3] )
            ] : next;
        }

        if ( ! box ) {
            return;
        }

        fitBox( box, SELECT_MAX_ZOOM );
    }

    /**
     * After the editor's synchronous duplicate has run: the copy is the new
     * selection; the source is looked up by the id captured at click time.
     */
    function scheduleFit( sourceId ) {
        const editor = window.wpslShapesEditor;
        const prev   = editor && editor.selectedFeature ? editor.selectedFeature() : null;
        const prevId = prev && prev.properties ? prev.properties.id : '';

        fitPending = true;

        window.setTimeout( function() {
            const copy   = editor && editor.selectedFeature ? editor.selectedFeature() : null;
            const copyId = copy && copy.properties ? copy.properties.id : '';
            const source = editor && editor.featureById ? editor.featureById( sourceId ) : null;

            // A refused duplicate changes nothing: the selection is still
            // what it was at click time, and there is nothing to fit.
            if ( copy && source && copyId && copyId !== prevId && copyId !== sourceId ) {
                fitBoth( source, copy );
            }

            fitPending = false;
        }, 80 );
    }

    // The panel's own Duplicate ( the hover toolbar never shows on the selection ).
    const duplicateBtn = document.getElementById( 'wpsl-shape-duplicate' );

    if ( duplicateBtn ) {
        duplicateBtn.addEventListener( 'click', function() {
            const editor = window.wpslShapesEditor;

            if ( ! editor || ! editor.duplicateSelected ) {
                return;
            }

            const selected = editor.selectedFeature ? editor.selectedFeature() : null;

            scheduleFit( selected && selected.properties ? selected.properties.id : '' );
            editor.duplicateSelected();
        } );
    }

    // The hover toolbar's duplicate ( unselected shapes ). Capture phase runs
    // before the editor's own handler, while the toolbar still carries the
    // source shape's id.
    const hoverTools = document.getElementById( 'wpsl-shapes-hover-tools' );

    if ( hoverTools ) {
        hoverTools.addEventListener( 'click', function( e ) {
            const btn = e.target.closest ? e.target.closest( 'button[data-shape-action="duplicate"]' ) : null;

            if ( btn ) {
                scheduleFit( hoverTools.getAttribute( 'data-shape-id' ) || '' );
            }
        }, true );
    }

    const undoBtn = document.getElementById( 'wpsl-shapes-undo' );
    const mapWrap = document.getElementById( 'wpsl-shapes-map-wrap' );

    /**
     * A lat / lng as a pixel in the map container. The adapter's
     * toContainerPoint() wins wherever one exists.
     */
    function containerPoint( map, lat, lng ) {
        const adapter = window.wpslShapesAdapter;
        if ( adapter && 'function' === typeof adapter.toContainerPoint ) {
            return adapter.toContainerPoint( lat, lng );
        }

        return 'mapbox' === service ? map.project( [ lng, lat ] ) : map.latLngToContainerPoint( [ lat, lng ] );
    }

    /**
     * The changed shape's anchor in pixel space, or null if it can't be placed.
     * 
     * @since 3.0.0
     */
    function undoAnchorPoint() {
        const editor  = window.wpslShapesEditor;
        const adapter = window.wpslShapesAdapter;
        if ( ! editor || ! editor.undoFeature || ! adapter || ! adapter.getMap ) {
            return null;
        }

        const feature = editor.undoFeature();
        const map     = adapter.getMap();

        if ( ! feature || ! map ) {
            return null;
        }

        // Above the shape's top edge; centred would compete with the shape
        // for the pointer.
        const box = editor.placement.boundingBox( feature.geometry, feature.properties );

        if ( ! box ) {
            return null;
        }

        const pt = containerPoint( map, box[3], ( box[0] + box[2] ) / 2 );

        if ( ! pt ) {
            return null;
        }

        // Off screen, or under the controls at the edges.
        if ( pt.x < 40 || pt.x > mapEl.offsetWidth - 40 || pt.y < 40 || pt.y > mapEl.offsetHeight - 30 ) {
            return null;
        }

        return pt;
    }

    /**
     * Float Undo against the changed shape, or hide it rather than park it
     * somewhere misleading.
     */
    function placeUndo() {
        if ( ! undoBtn || ! mapWrap ) {
            return;
        }

        const editor  = window.wpslShapesEditor;
        const offered = editor && editor.canUndo ? editor.canUndo() : false;
        const point   = offered ? undoAnchorPoint() : null;

        if ( ! point ) {
            undoBtn.style.display = 'none';

            return;
        }

        undoBtn.style.display = '';
        undoBtn.style.left    = point.x + 'px';
        undoBtn.style.top     = ( point.y - 14 ) + 'px';
    }

    if ( undoBtn ) {
        let lastUndoVisible = 'none' !== undoBtn.style.display;

        new MutationObserver( function() {
            const visible = 'none' !== undoBtn.style.display;

            if ( visible !== lastUndoVisible ) {
                lastUndoVisible = visible;
                placeUndo();
            }
        } ).observe( undoBtn, { attributes: true, attributeFilter: [ 'style' ] } );

        // Re-anchor on every map move, so the undo button stays on its shape
        // and returns when a pan brings it back into view.
        const anchorOnMove = window.setInterval( function() {
            const adapter = window.wpslShapesAdapter;
            const map     = adapter && adapter.getMap ? adapter.getMap() : null;

            if ( ! map ) {
                return;
            }

            window.clearInterval( anchorOnMove );

            if ( 'gmaps' === service ) {
                if ( window.google && window.google.maps && window.google.maps.event ) {
                    window.google.maps.event.addListener( map, 'idle', placeUndo );
                }

                return;
            }

            map.on( 'moveend', placeUndo );
        }, 500 );
    }

    /*
     * The editor calls this once its shapes are on the map. Everything else
     * in this file wires itself up, so this is the only published entry.
     */
    window.wpslShapesLayout = { fitAll: fitAll };
} )();
