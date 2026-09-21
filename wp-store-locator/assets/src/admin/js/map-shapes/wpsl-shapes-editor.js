/**
 * Map Shapes editor: the provider-independent half.
 *
 * Manages the toolbar, instructions line, style panel, save request and
 * unsaved-changes guard. All map access goes through window.wpslShapesAdapter
 * -- exactly one adapter file is enqueued server side, chosen by
 * wpslMapShapes.drawAdapter. No sidebar list: the map is the list.
 *
 * @since 3.0.0
 */
( function( $ ) {
    'use strict';

    if ( typeof wpslMapShapes === 'undefined' ) {
        return;
    }

    const DEFAULT_COLOR = '#cc3333';
    const DEFAULT_STYLE = {
        fill:         true,
        fill_color:   DEFAULT_COLOR,
        fill_opacity: 0.4,
        stroke_color: DEFAULT_COLOR,
        stroke_width: 2
    };

    /*
     * Default instruction key per tool when the adapter names none -- the
     * gestures the plugin's own engines draw with.
     */
    const DEFAULT_INSTRUCTIONS = {
        polygon:   'drawVertices',
        polyline:  'drawVertices',
        circle:    'drawCircleDrag',
        rectangle: 'drawRectangleDrag'
    };

    const l10n = wpslMapShapes.l10n || {};

    let adapter    = null;
    let collection = normalizeCollection( wpslMapShapes.collection );
    let selectedId = '';
    let activeTool = '';
    let dirty      = false;

    /*
     * Set while the style panel is filled from a feature: WPSL_ColorPicker
     * fires change on write, and selecting a shape would otherwise write its
     * own colors back and mark the page dirty.
     */
    let populating = false;

    // Whether the name field is showing the duplicate-name complaint, so the
    // outline is only written -- and announced -- when the answer changes.
    let nameInvalid = false;

    /*
     * The shape being drawn, before it exists: the name and message the
     * prospective panel collects, and the generated name the field was seeded
     * with -- so a tool switch can tell a typed name from an untouched one.
     */
    let pendingName      = '';
    let pendingGenerated = '';
    let pendingMessage   = '';

    // Monotonic tail for generated ids, so two shapes finished in the same
    // millisecond cannot share one.
    let idSequence = 0;

    // One step of undo: a deep copy of the features before the action, and
    // which shape that undo is about.
    let undoState     = null;
    let undoFeatureId = '';

    /*
     * The collection before the current change. Held from earlier because an
     * adapter may reshape a feature in place; by the time a drag is reported
     * there is nothing left to copy. Refreshed on selection and after each commit.
     */
    let undoBaseline = snapshot();

    // The shape the pointer is over, and the one the hover tools act on.
    let hoveredId = '';

    /*
     * The pending hide of the hover toolbar. Leaving a shape doesn't hide it
     * at once: moving onto the toolbar fires the shape's mouseout first, so a
     * short grace ( cancelled by the toolbar's mouseenter ) keeps the buttons
     * reachable.
     */
    let hideTimer = null;

    const HIDE_DELAY = 160;

    /*
     * The grace a line's toolbar gets instead: it hangs above the line, so
     * reaching it always crosses open map -- which fires the hide at once.
     */
    const LINE_HIDE_DELAY = 2000;

    // How far inside the map's edges the toolbar's anchor must fall for it to
    // be drawn -- half the widest the button pair gets. See isOverMap().
    const TOOLS_MARGIN = 32;

    /*
     * How far a line's toolbar sits above its anchor: half its height plus the
     * 10px CSS gap. Keep in step with .wpsl-shape-tools-above in map-shapes.css.
     */
    const TOOLS_LIFT = 25;
    
    const PLACE_ZOOM = 12;

    // Every provider builds its map asynchronously; the search field is
    // disabled until onMapReady() flips this, or panTo() would have no map.
    let mapReady = false;

    // Whether a geocode request is in flight, so a keystroke mid-request
    // cannot clobber the spinner runPlaceSearch() put up.
    let searchInFlight = false;

    /*
     * Whether the last action was a place search: the map is somewhere new to
     * draw on, so "select a drawing tool" is worth saying even with shapes.
     * Spent on the next arm or selection.
     */
    let searchHint = false;

    let $mapWrap, $hoverTools;
    let $search, $searchWrap, $searchInput, $searchBtn, $searchClearWrap, $searchClear, $searchPreloader, $searchMessage;

    let $map, $toolbar, $instructions, $deselect;
    let $style, $activeToggle, $activeRow, $fillToggle, $fillColor, $fillOpacity, $fillOpacityValue, $strokeColor, $strokeWidth, $strokeWidthValue, $delete;
    let $picker, $shapeSelect, $shapeSelectLabel, $shapeList, $shapeSwatch, $shapeName, $shapeCoords, $shapeMessage;
    let $shapeCenter, $shapeRadius, $shapeNe, $shapeSw, $geoCircle, $geoRectangle, $geoPath, $geoInputs;
    let $save, $savePreloader, $status, $undo, $actions, $shapeActions;

    $( init );

    /**
     * Cache the DOM contract, wire the controls, hand the map to the adapter.
     *
     * @since  3.0.0
     * @return void
     */
    function init() {
        $map          = $( '#wpsl-shapes-map' );
        $toolbar      = $( '#wpsl-shapes-toolbar' );
        $instructions = $( '#wpsl-shapes-instructions' );
        $deselect     = $( '#wpsl-shapes-deselect' );

        $style            = $( '#wpsl-shapes-style' );
        $activeToggle     = $( '#wpsl-shape-active-toggle' );
        $activeRow        = $( '#wpsl-shape-active-row' );
        $fillToggle       = $( '#wpsl-shape-fill-toggle' );
        $fillColor        = $( '#wpsl-shape-fill-color' );
        $fillOpacity      = $( '#wpsl-shape-fill-opacity' );
        $fillOpacityValue = $( '#wpsl-shape-fill-opacity-value' );
        $strokeColor      = $( '#wpsl-shape-stroke-color' );
        $strokeWidth      = $( '#wpsl-shape-stroke-width' );
        $strokeWidthValue = $( '#wpsl-shape-stroke-width-value' );
        $delete           = $( '#wpsl-shape-delete' );

        $picker           = $( '.wpsl-shape-picker' );
        $shapeSelect      = $( '#wpsl-shape-select' );
        $shapeSelectLabel = $( '#wpsl-shape-select-label' );
        $shapeList        = $( '#wpsl-shape-list' );
        $shapeSwatch      = $( '#wpsl-shape-swatch' );
        $shapeName        = $( '#wpsl-shape-name' );
        $shapeCoords      = $( '#wpsl-shape-coords' );
        $shapeMessage     = $( '#wpsl-shape-message' );

        $shapeCenter  = $( '#wpsl-shape-center' );
        $shapeRadius  = $( '#wpsl-shape-radius' );
        $shapeNe      = $( '#wpsl-shape-ne' );
        $shapeSw      = $( '#wpsl-shape-sw' );
        $geoCircle    = $( '.wpsl-shape-geo-circle' );
        $geoRectangle = $( '.wpsl-shape-geo-rectangle' );
        $geoPath      = $( '.wpsl-shape-geo-path' );

        // Geometry fields for the selected shape. Wired as one set because
        // they are read as one set -- see applyGeometryEdit().
        $geoInputs = $shapeCenter.add( $shapeRadius ).add( $shapeNe ).add( $shapeSw ).add( $shapeCoords );

        $save          = $( '#wpsl-shapes-save' );
        $savePreloader = $( '#wpsl-shapes-save-preloader' );
        $status        = $( '#wpsl-shapes-status' );
        $undo          = $( '#wpsl-shapes-undo' );
        $actions       = $( '#wpsl-shapes-actions' );
        $shapeActions  = $( '#wpsl-shapes-shape-actions' );

        $mapWrap    = $( '#wpsl-shapes-map-wrap' );
        $hoverTools = $( '#wpsl-shapes-hover-tools' );

        $search          = $( '#wpsl-shapes-search' );
        $searchWrap      = $search.find( '.wpsl-search-wrap' );
        $searchInput     = $( '#wpsl-shapes-search-input' );
        $searchBtn       = $( '#wpsl-shapes-search-btn' );
        $searchClearWrap = $( '#wpsl-shapes-clear-wrapper' );
        $searchClear     = $( '#wpsl-shapes-clear-search' );
        $searchPreloader = $( '#wpsl-shapes-search-preloader' );
        $searchMessage   = $( '#wpsl-shapes-search-message' );

        if ( ! $map.length ) {
            return;
        }

        // See the note on mapReady.
        setSearchEnabled( false );

        if ( window.wpslSharedFuncs && $fillToggle.length ) {
            wpslSharedFuncs.createToggleSliders( $fillToggle.add( $activeToggle ) );
        }

        bindControls();
        updateActions();

        // A missing or invalid Mapbox / Stadia key would leave the adapter's
        // map blank, so show the key-required box in the map's spot and keep
        // the drawing tools disabled - same rule as the other admin maps.
        if ( wpslMapShapes.keyGate && wpslMapShapes.keyGate.blocked ) {
            $map.addClass( 'wpsl-key-required' ).html( '<p>' + wpslMapShapes.keyGate.message + '</p>' );
            $toolbar.find( 'button[data-shape-type]' ).prop( 'disabled', true );
            $save.prop( 'disabled', true );

            return;
        }

        adapter = window.wpslShapesAdapter;

        if ( ! adapter || 'function' !== typeof adapter.init ) {
            $toolbar.find( 'button[data-shape-type]' ).prop( 'disabled', true );
            $save.prop( 'disabled', true );

            setStatus( text( 'adapterMissing' ), true );

            return;
        }

        adapter.init( $map.get( 0 ), wpslMapShapes.startLatLng, onMapReady );
    }

    /**
     * The adapter has a usable map.
     *
     * @since  3.0.0
     * @return void
     */
    function onMapReady() {
        mapReady = true;

        $toolbar.show();
        $search.show();

        updatePanel();

        if ( 'function' === typeof adapter.panTo ) {
            setSearchEnabled( true );
        }

        adapter.onShapeComplete( onShapeComplete );
        adapter.onShapeSelect( onShapeSelect );

        if ( 'function' === typeof adapter.onShapeEdit ) {
            adapter.onShapeEdit( onShapeEdit );
        }

        if ( 'function' === typeof adapter.onShapeHover ) {
            adapter.onShapeHover( onShapeHover );
        }

        adapter.renderCollection( collection );

        /*
         * Open on the shapes rather than on the configured start location: a
         * locator whose shapes are nowhere near its default centre opened on
         * empty map. Falls back to the start location when there is nothing to
         * fit, and only runs here -- refitting after every edit would yank the
         * map around while someone is drawing.
         */
        if ( window.wpslShapesLayout && 'function' === typeof window.wpslShapesLayout.fitAll ) {
            window.wpslShapesLayout.fitAll();
        }
    }

    /**
     * Wire every control on the page.
     *
     * @since  3.0.0
     * @return void
     */
    function bindControls() {
        $toolbar.on( 'click', 'button[data-shape-type]', onToolClick );

        $searchBtn.on( 'click', runPlaceSearch );

        $searchInput.on( 'keydown', function( event ) {
            if ( 13 !== event.which ) {
                return;
            }

            event.preventDefault();
            runPlaceSearch();
        } );

        $searchInput.on( 'input', function() {
            updateClearVisibility();
            setSearchMessage( '', false );
        } );

        $searchClear.on( 'click', function() {
            $searchInput.val( '' ).trigger( 'focus' );
            updateClearVisibility();
            setSearchMessage( '', false );
        } );

        $deselect.on( 'click', clearEverything );

        $activeToggle.on( 'change', function() {
            writeProperty( 'active', $activeToggle.prop( 'checked' ) );

            refreshShapeList();
        } );

        $fillToggle.on( 'change', function() {
            writeProperty( 'fill', $fillToggle.prop( 'checked' ) );
        } );

        $fillOpacity.on( 'input change', function() {
            const opacity = clampOpacity( $fillOpacity.val() );
            $fillOpacityValue.text( String( opacity ) );

            writeProperty( 'fill_opacity', opacity );
        } );

        $strokeWidth.on( 'input change', function() {
            const width = strokeWidth( $strokeWidth.val() );
            $strokeWidthValue.text( width + 'px' );

            writeProperty( 'stroke_width', width );
        } );

        $delete.on( 'click', function() {
            confirmDelete( selectedId );
        } );

        $undo.on( 'click', undo );

        $hoverTools.on( 'click', 'button[data-shape-action]', function() {
            const featureId = $hoverTools.attr( 'data-shape-id' );

            hideHoverTools();

            if ( 'duplicate' === $( this ).attr( 'data-shape-action' ) ) {
                duplicateShape( featureId );

                return;
            }

            confirmDelete( featureId );
        } );

        // Arriving cancels the mouseout hide; leaving schedules a fresh one.
        $hoverTools.on( 'mouseenter', cancelHide );

        $hoverTools.on( 'mouseleave', scheduleHide );

        // Picking a shape by name selects it
        $shapeSelect.on( 'click', function() {
            toggleShapeList( $shapeList.prop( 'hidden' ) );
        } );

        $shapeList.on( 'click', 'li', function() {
            const id = $( this ).attr( 'data-shape-id' );

            toggleShapeList( false );
            $shapeSelect.focus();

            // No id means deselect.
            if ( ! id ) {
                if ( selectedId ) {
                    clearSelection();
                }

                return;
            }

            if ( id !== selectedId ) {
                selectShape( id );
            }
        } );

        // Escape or a click outside closes the list.
        $picker.on( 'keydown', function( event ) {
            if ( 27 === event.which ) {
                toggleShapeList( false );
                $shapeSelect.focus();
            }
        } );

        $( document ).on( 'click.wpslShapePicker', function( event ) {
            if ( ! $( event.target ).closest( '.wpsl-shape-picker' ).length ) {
                toggleShapeList( false );
            }
        } );

        /*
         * The name is a stored property like any other, but also what the
         * picker lists -- so the list is rebuilt as it is typed.
         */
        $shapeName.on( 'input', function() {
            const typed   = $( this ).val();
            const cleaned = cleanShapeName( typed );

            // Rewritten with the caret restored: val() alone sends it to the end.
            if ( cleaned !== typed ) {
                const caret = Math.max( 0, ( this.selectionStart || 0 ) - ( typed.length - cleaned.length ) );

                $( this ).val( cleaned );

                if ( 'function' === typeof this.setSelectionRange ) {
                    this.setSelectionRange( caret, caret );
                }
            }

            if ( nameTaken( cleaned ) ) {
                markNameInvalid( true );

                return;
            }

            markNameInvalid( false );

            if ( ! selectedId && activeTool ) {
                pendingName = cleaned;
                refreshShapeList();

                return;
            }

            writeProperty( 'name', cleaned );
            refreshShapeList();
        } );

        // Blur abandons a refused rename: the stored name stands, the field springs back.
        $shapeName.on( 'blur', restoreRefusedName );

        // Escape backs out of a refused rename; stopped so the same press
        // doesn't also drop the selection.
        $shapeName.on( 'keydown', function( event ) {
            if ( 27 !== event.which || ! nameInvalid ) {
                return;
            }

            event.stopPropagation();
            restoreRefusedName();
        } );

        // The shape's click message; nothing on the map changes as it is typed.
        $shapeMessage.on( 'input', function() {
            if ( ! selectedId && activeTool ) {
                pendingMessage = $( this ).val();

                return;
            }

            writeProperty( 'message', $( this ).val() );
        } );

        // On 'change', not 'input': a half-typed latitude would fly the map
        // to the other side of the world.
        $geoInputs.on( 'change', applyGeometryEdit );

        $geoInputs.on( 'input', function() {
            const $field = $( this );

            if ( ! $field.hasClass( 'wpsl-shape-invalid' ) ) {
                return;
            }

            $field.removeClass( 'wpsl-shape-invalid' ).removeAttr( 'aria-invalid' ).removeAttr( 'aria-describedby' );
            errorLine( this ).text( '' );

            if ( ! $geoInputs.filter( '.wpsl-shape-invalid' ).length ) {
                setStatus( '', false );
            }
        } );

        $geoInputs.on( 'keydown', function( event ) {
            if ( 13 !== event.which || 'textarea' === this.tagName.toLowerCase() ) {
                return;
            }

            event.preventDefault();
            this.blur();
        } );

        // WPSL_ColorPicker auto-initialises on .wpsl-color-field; change fires
        // on the hidden input.
        $fillColor.on( 'change', onColorChange );
        $strokeColor.on( 'change', onColorChange );

        $save.on( 'click', saveShapes );

        // Escape exits innermost first ( drawing before selection ). On
        // document so it works while focus is in the style panel.
        $( document ).on( 'keydown.wpslShapes', function( event ) {
            if ( 27 !== event.which ) {
                return;
            }

            if ( activeTool ) {
                adapter.cancelDraw();
                clearTool();

                return;
            }

            if ( selectedId ) {
                clearSelection();
            }
        } );

        $( window ).on( 'beforeunload', function() {
            if ( dirty ) {
                return text( 'unsavedChanges' );
            }
        } );
    }

    /**
     * Click a toolbar tool: start drawing that shape, or stop if it's already active.
     *
     * @since  3.0.0
     * @return void
     */
    function onToolClick() {
        const type = $( this ).attr( 'data-shape-type' );

        if ( type === activeTool ) {
            adapter.cancelDraw();
            clearTool();

            return;
        }

        if ( activeTool ) {
            adapter.cancelDraw();
        } else if ( selectedId ) {
            clearSelection();
        }

        activeTool = type;
        searchHint = false;

        updateUndo();
        updateDeselect();
        setEditable( '' );

        $toolbar.find( 'button[data-shape-type]' ).each( function() {
            $( this ).attr( 'aria-pressed', this.getAttribute( 'data-shape-type' ) === type ? 'true' : 'false' );
        } );

        setInstructions( instructionFor( type ) );

        openProspectivePanel( type );

        adapter.enableDraw( type );
    }

    /**
     * Hand the controls column to the shape being drawn, before it exists.
     * A name or message typed here is carried onto the finished shape by
     * onShapeComplete(); cancelling discards both.
     *
     * @since  3.0.0
     * @param  {string} type polygon|circle|rectangle|polyline.
     * @return void
     */
    function openProspectivePanel( type ) {
        const generated = uniqueName( type );

        // A typed name survives a tool switch; an untouched generated one follows the type.
        if ( '' === pendingName || pendingName === pendingGenerated ) {
            pendingName = generated;
        }

        pendingGenerated = generated;

        markNameInvalid( false );

        $shapeName.val( pendingName );
        $shapeMessage.val( pendingMessage );

        // The shared field plumbing with a geometry-less shape: its kind's
        // fields come up empty, and any standing complaint goes.
        populateGeometry( { properties: { shape_type: type }, geometry: null } );

        // Exact placement needs something placed; populateStyle() lifts this
        // once the shape exists.
        $geoInputs.prop( 'disabled', true );

        // Nothing exists to delete or hide; a new shape is born active.
        $delete.hide();
        $activeRow.addClass( 'wpsl-hide' );

        $style.find( '.wpsl-shape-fill-control' ).toggleClass( 'wpsl-hide', 'polyline' === type );

        refreshShapeList();

        $style.show();
    }

    /**
     * The way out of every held state at once: a drawing in progress, 
     * the tool that started it, and the selection.
     *
     * @since  3.0.0
     * @return void
     */
    function clearEverything() {
        if ( activeTool ) {
            adapter.cancelDraw();
            clearTool();
        }

        if ( selectedId ) {
            clearSelection();
        }

        updateDeselect();
    }

    /**
     * Put the toolbar's X up only when it has something to clear.
     *
     * @since  3.0.0
     * @return void
     */
    function updateDeselect() {
        $deselect.toggle( !! activeTool || '' !== selectedId );
    }

    /**
     * Back to the "no tool" state.
     *
     * @since  3.0.0
     * @return void
     */
    function clearTool() {
        activeTool = '';

        pendingName      = '';
        pendingGenerated = '';
        pendingMessage   = '';

        $toolbar.find( 'button[data-shape-type]' ).attr( 'aria-pressed', 'false' );

        if ( selectedId ) {
            setEditable( selectedId );
        } else {
            markNameInvalid( false );
            $style.hide();
        }

        updatePanel();
    }

    /**
     * Put a line at the head of the controls column, or take it away.
     *
     * @since  3.0.0
     * @param  {string} message '' to take the line away.
     * @return void
     */
    function setInstructions( message ) {
        $instructions.text( message || '' ).toggle( !! message );
    }

    /**
     * Put the controls column into the state the editor is actually in.
     *
     * @since  3.0.0
     * @return void
     */
    function updatePanel( refreshList ) {
        if ( ! mapReady ) {
            return;
        }

        const hasShapes = collection.features.length > 0;

        // The list is rebuilt by writing its markup out in full, which is far
        // too much for a slider that changes nothing the list shows.
        if ( false !== refreshList ) {
            refreshShapeList();
        }
        $picker.toggle( hasShapes );

        $shapeActions.toggle( !! selectedId );

        updateUndo();
        updateDeselect();

        if ( activeTool ) {
            return;
        }

        setInstructions( ( selectedId || ( hasShapes && ! searchHint ) ) ? '' : text( 'selectTool' ) );
    }

    /**
     * The instruction line for a tool, in the gesture the adapter's engine uses.
     *
     * @since  3.0.0
     * @param  {string} type polygon|circle|rectangle|polyline.
     * @return {string}
     */
    function instructionFor( type ) {
        if ( adapter && 'function' === typeof adapter.instructionKey ) {
            const key = adapter.instructionKey( type );

            if ( key ) {
                return text( key );
            }
        }

        return text( DEFAULT_INSTRUCTIONS[ type ] || 'selectTool' );
    }

    /**
     * A drawing finished, or an existing shape was edited on the map.
     *
     * @since  3.0.0
     * @param  {object} feature A spec-shaped GeoJSON feature from the adapter.
     * @return void
     */
    function onShapeComplete( feature ) {
        // Reject invalid shapes before they enter the collection: the server's
        // sanitizer rejects the whole payload on the first bad feature.
        if ( ! isDrawableFeature( feature ) ) {
            if ( adapter ) {
                adapter.cancelDraw();
            }

            clearTool();
            setStatus( text( 'incompleteShape' ), true );

            return;
        }

        feature.type = 'Feature';

        // fill: true pinned back on after the panel's values, or one unfilled
        // shape becomes the default for every shape after it.
        feature.properties = applyShapeTypeRules( $.extend( {}, DEFAULT_STYLE, panelStyle(), { fill: true }, feature.properties || {} ) );

        if ( ! feature.properties.id ) {
            feature.properties.id = uniqueId();
        }

        if ( ! feature.properties.name ) {
            feature.properties.name = prospectiveName( feature.properties.shape_type );
        }

        // The message the prospective panel collected while this was drawn.
        if ( '' !== pendingMessage && ! feature.properties.message ) {
            feature.properties.message = pendingMessage;
        }

        // A finished drawing is undoable too; the previous undo predates this shape.
        const previous = snapshot();
        const index    = indexOfFeature( feature.properties.id );

        if ( index > -1 ) {
            collection.features[ index ] = feature;
        } else {
            collection.features.push( feature );
        }

        setUndo( previous, feature.properties.id );

        adapter.renderCollection( collection );

        selectShape( feature.properties.id );
        setDirty( true );
        clearTool();
    }

    /**
     * Style rules that follow from shape type, not the panel: a polyline's
     * fill is forced off, or renderers disagree ( Leaflet fills open paths;
     * Google and Mapbox can't ).
     *
     * @since  3.0.0
     * @param  {object} props The feature properties.
     * @return {object} The same properties, with the shape type's rules applied.
     */
    function applyShapeTypeRules( props ) {
        if ( 'polyline' === props.shape_type ) {
            props.fill = false;
        }

        return props;
    }

    /**
     * The map's selection changed. An empty id means deselect. Ignored while a
     * tool is armed: a map click places a vertex, and losing the selection
     * under each would close the panel mid-drawing.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function onShapeSelect( featureId ) {
        if ( featureId ) {
            selectShape( featureId );

            return;
        }

        if ( ! activeTool ) {
            clearSelection();
        }
    }

    /**
     * A selected shape was reshaped on the map. The adapter already moved what
     * is drawn, so this only records the result; re-rendering mid-edit would
     * take the handles out from under the cursor.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {object} geometry   The reshaped GeoJSON geometry.
     * @param  {object} properties Geometry-carrying properties ( a circle's
     *                             radius ), if any.
     * @return void
     */
    function onShapeEdit( featureId, geometry, properties ) {
        const feature = findFeature( featureId );

        if ( ! feature || ! geometry ) {
            return;
        }

        const edited = $.extend( {}, feature.properties, properties || {} );

        /*
         * Measured against the last settled state, not the feature ( which an
         * adapter may have reshaped in place ). Drops a drag that ended where
         * it began AND the second telling of one gesture: the Leaflet adapter
         * reports pm:edit and pm:markerdragend for one vertex drag, and the
         * second would spend the undo the first had just left.
         */
        const settled = findBaseline( featureId );

        if ( settled && isSameShape( settled, geometry, edited ) ) {
            return;
        }

        // Taken when the shape was selected, not here -- see undoBaseline.
        const previous = undoBaseline;

        // The same gate a new drawing goes through: the sanitizer refuses the
        // WHOLE collection over one bad feature, so a bad drag is put back.
        if ( ! isDrawableFeature( { type: 'Feature', geometry: geometry, properties: edited } ) ) {
            if ( adapter ) {
                adapter.renderCollection( collection );
                setEditable( featureId );
            }

            setStatus( text( 'incompleteShape' ), true );

            return;
        }

        feature.geometry   = geometry;
        feature.properties = edited;

        // The fields describe a shape that just moved under them, so they are
        // rewritten from where it landed; a standing complaint goes with the
        // values it was about.
        if ( featureId === selectedId ) {
            populateGeometry( feature );
        }

        setUndo( previous, feature.properties.id );
        setDirty( true );
    }

    /**
     * The pointer moved onto, across, or off a shape.
     *
     * @since  3.0.0
     * @param  {string} featureId '' when the pointer left the last shape.
     * @param  {object} point     { x, y } in the map container's pixel space.
     * @return void
     */
    function onShapeHover( featureId, point ) {
        hoveredId = featureId || '';

        // No tools while drawing ( they'd eat the click that places a vertex )
        // nor on the selected shape ( they'd land on its handles ).
        if ( ! hoveredId || activeTool || hoveredId === selectedId ) {
            scheduleHide();

            return;
        }

        cancelHide();
        showHoverTools( hoveredId, point );
    }

    /**
     * Put the hover toolbar on a shape: ON it for anything with an interior,
     * lifted just above the stroke for a line; a centred toolbar would sit on
     * the only pixels that select a line.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @param  {object} point     { x, y } in the map wrapper's pixel space.
     * @return void
     */
    function showHoverTools( featureId, point ) {
        const feature = findFeature( featureId );

        // The point is used as the adapter reported it: a shape's centre, or
        // for a line the pointer's own position.
        const isLine = !! feature && 'polyline' === ( feature.properties || {} ).shape_type;

        if ( ! point || ! isOverMap( point, isLine ) ) {
            hideHoverTools();

            return;
        }

        $hoverTools
            .attr( 'data-shape-id', featureId )
            .toggleClass( 'wpsl-shape-tools-above', isLine )
            .css( { left: point.x + 'px', top: point.y + 'px' } )
            .prop( 'hidden', false );
    }

    /**
     * Whether a shape's anchor point is somewhere the toolbar can be drawn.
     * A shape half off the map takes its centre off with it, and the toolbar
     * would follow it out over the controls column.
     *
     * @since  3.0.0
     * @param  {object}  point { x, y } in the map wrapper's pixel space.
     * @param  {boolean} above Whether the toolbar sits above the point ( a
     *                         line ), needing its whole height in hand at the
     *                         top edge.
     * @return {boolean}
     */
    function isOverMap( point, above ) {
        const map = $map.get( 0 );

        if ( ! map ) {
            return false;
        }

        const top = above ? TOOLS_MARGIN + TOOLS_LIFT : TOOLS_MARGIN;

        return point.x >= TOOLS_MARGIN &&
            point.y >= top &&
            point.x <= map.offsetWidth - TOOLS_MARGIN &&
            point.y <= map.offsetHeight - TOOLS_MARGIN;
    }

    /**
     * Take the hover toolbar away after a grace period.
     *
     * @since  3.0.0
     * @return void
     */
    function scheduleHide() {
        cancelHide();

        // Read off the toolbar itself: by the time a hide is scheduled the
        // pointer has left the shape, and hoveredId with it.
        const feature = findFeature( $hoverTools.attr( 'data-shape-id' ) || '' );
        const isLine  = !! feature && 'polyline' === ( feature.properties || {} ).shape_type;

        hideTimer = window.setTimeout( hideHoverTools, isLine ? LINE_HIDE_DELAY : HIDE_DELAY );
    }

    /**
     * Call off a pending hide.
     *
     * @since  3.0.0
     * @return void
     */
    function cancelHide() {
        if ( hideTimer ) {
            window.clearTimeout( hideTimer );

            hideTimer = null;
        }
    }

    /**
     * Take the hover toolbar away now.
     *
     * @since  3.0.0
     * @return void
     */
    function hideHoverTools() {
        cancelHide();

        $hoverTools.prop( 'hidden', true ).removeAttr( 'data-shape-id' );
    }

    /**
     * Copy a shape, offset enough to be visibly separate from the original.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function duplicateShape( featureId ) {
        const feature = findFeature( featureId );

        if ( ! feature ) {
            return;
        }

        const previous = snapshot();
        const copy     = clone( feature );

        copy.properties.id = uniqueId();

        // A copy is a new shape, not the same one twice: it gets its own name.
        copy.properties.name = uniqueName( copy.properties.shape_type );

        offsetGeometry( copy.geometry, freeOffset( feature ) );

        // Validate: an edge-of-world shape could nudge out of range, and one
        // bad feature blocks all later saves.
        if ( ! isDrawableFeature( copy ) ) {
            setStatus( text( 'incompleteShape' ), true );

            return;
        }

        collection.features.push( copy );

        if ( adapter ) {
            adapter.renderCollection( collection );
        }

        setUndo( previous, copy.properties.id );
        selectShape( copy.properties.id );
        setDirty( true );
    }

    /**
     * Where to place a duplicate so it doesn't overlap an existing shape:
     * walked outwards in eight directions, first free spot wins. All
     * occupied, the last is used -- a copy somewhere beats no copy.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {Array} [ lng, lat ].
     */
    function freeOffset( feature ) {
        const step     = shapeStep( feature );
        const box      = boundingBox( feature.geometry, feature.properties );
        const occupied = otherBoxes( feature.properties.id );

        // Eight compass points, then the same ring further out.
        const headings = [
            [ 1, 0 ], [ 0, -1 ], [ -1, 0 ], [ 0, 1 ],
            [ 1, -1 ], [ -1, -1 ], [ -1, 1 ], [ 1, 1 ]
        ];

        let offset = [ step[0], 0 ];

        for ( let ring = 1; ring <= 3; ring++ ) {
            for ( let i = 0; i < headings.length; i++ ) {
                offset = [ headings[ i ][0] * step[0] * ring, headings[ i ][1] * step[1] * ring ];

                if ( ! box || ! overlapsAny( shiftBox( box, offset ), occupied ) ) {
                    return offset;
                }
            }
        }

        return offset;
    }

    /**
     * How far one step is, in degrees, for a shape: its own size plus a
     * margin, floored so a copy of something tiny still lands clear.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {Array} [ lng, lat ].
     */
    function shapeStep( feature ) {
        const geometry = feature.geometry || {};

        if ( 'Point' === geometry.type ) {
            // A circle's extent is its radius, in metres not degrees: a
            // degree of latitude is ~111.32 km everywhere.
            const degrees = ( parseFloat( ( feature.properties || {} ).radius ) || 0 ) / 111320;

            return [ Math.max( 0.01, degrees * 2.5 ), Math.max( 0.01, degrees * 2.5 ) ];
        }

        const box = boundingBox( geometry );
        if ( ! box ) {
            return [ 0.01, 0.01 ];
        }

        return [
            Math.max( 0.01, ( box[2] - box[0] ) * 1.25 ),
            Math.max( 0.01, ( box[3] - box[1] ) * 1.25 )
        ];
    }

    /**
     * A geometry's bounding box.
     *
     * A circle's is squared off its radius: the sanitizer stores it as a
     * centre and metres, so there are no positions to walk.
     *
     * @since  3.0.0
     * @param  {object} geometry
     * @param  {object} properties Needed for a circle's radius.
     * @return {Array|null} [ west, south, east, north ].
     */
    function boundingBox( geometry, properties ) {
        if ( ! geometry ) {
            return null;
        }

        if ( 'Point' === geometry.type ) {
            const degrees = ( parseFloat( ( properties || {} ).radius ) || 0 ) / 111320;
            const center  = geometry.coordinates;
            return [ center[0] - degrees, center[1] - degrees, center[0] + degrees, center[1] + degrees ];
        }

        const positions = 'LineString' === geometry.type ? geometry.coordinates : ( geometry.coordinates || [] )[0];
        let box = null;

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            const lng = positions[ i ][0];
            const lat = positions[ i ][1];

            if ( ! box ) {
                box = [ lng, lat, lng, lat ];
                continue;
            }

            box[0] = Math.min( box[0], lng );
            box[1] = Math.min( box[1], lat );
            box[2] = Math.max( box[2], lng );
            box[3] = Math.max( box[3], lat );
        }

        return box;
    }

    /**
     * Every other shape's bounding box.
     *
     * @since  3.0.0
     * @param  {string} exceptId
     * @return {Array}
     */
    function otherBoxes( exceptId ) {
        const boxes = [];

        for ( let i = 0; i < collection.features.length; i++ ) {
            const other = collection.features[ i ];
            if ( ( other.properties || {} ).id === exceptId ) {
                continue;
            }

            const box = boundingBox( other.geometry, other.properties );
            if ( box ) {
                boxes.push( box );
            }
        }

        return boxes;
    }

    /**
     * A box moved by an offset.
     *
     * @since  3.0.0
     * @param  {Array} box
     * @param  {Array} offset
     * @return {Array}
     */
    function shiftBox( box, offset ) {
        return [ box[0] + offset[0], box[1] + offset[1], box[2] + offset[0], box[3] + offset[1] ];
    }

    /**
     * Whether a box touches any of a list of boxes.
     *
     * @since  3.0.0
     * @param  {Array} box
     * @param  {Array} boxes
     * @return {boolean}
     */
    function overlapsAny( box, boxes ) {
        for ( let i = 0; i < boxes.length; i++ ) {
            const other = boxes[ i ];

            if ( box[0] < other[2] && box[2] > other[0] && box[1] < other[3] && box[3] > other[1] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shift every position in a geometry.
     *
     * @since  3.0.0
     * @param  {object} geometry Mutated in place.
     * @param  {Array}  offset   [ lng, lat ].
     * @return void
     */
    function offsetGeometry( geometry, offset ) {
        if ( 'Point' === geometry.type ) {
            geometry.coordinates = shiftPosition( geometry.coordinates, offset );
            return;
        }

        if ( 'LineString' === geometry.type ) {
            geometry.coordinates = shiftPositions( geometry.coordinates, offset );
            return;
        }

        const rings = geometry.coordinates || [];

        for ( let i = 0; i < rings.length; i++ ) {
            rings[ i ] = shiftPositions( rings[ i ], offset );
        }
    }

    /**
     * A list of positions, shifted.
     *
     * @since  3.0.0
     * @param  {Array} positions
     * @param  {Array} offset
     * @return {Array}
     */
    function shiftPositions( positions, offset ) {
        const shifted = [];

        for ( let i = 0; i < ( positions || [] ).length; i++ ) {
            shifted.push( shiftPosition( positions[ i ], offset ) );
        }

        return shifted;
    }

    /**
     * One position, shifted and wrapped back into range.
     *
     * @since  3.0.0
     * @param  {Array} position
     * @param  {Array} offset
     * @return {Array}
     */
    function shiftPosition( position, offset ) {
        const lng = position[0] + offset[0];
        const lat = position[1] + offset[1];

        return [ ( ( lng + 540 ) % 360 ) - 180, Math.min( 90, Math.max( -90, lat ) ) ];
    }

    /**
     * Select a shape: highlight it, make it draggable, fill in the style panel.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function selectShape( featureId ) {
        const feature = findFeature( featureId );

        if ( ! feature ) {
            return;
        }

        /*
         * A pending undo dies when the selection moves to another shape.
         * Without this, reselecting a shape later would revive a button whose
         * context is gone -- reading as undo but acting as delete.
         */
        if ( featureId !== undoFeatureId ) {
            clearUndo();
        }

        selectedId = featureId;

        // The handles are about to go on, and a drag reports itself too late
        // to copy anything -- see undoBaseline.
        undoBaseline = snapshot();

        if ( adapter ) {
            adapter.highlight( featureId );
        }

        setEditable( featureId );
        populateStyle( feature );

        // The handles are now where the toolbar was sitting.
        hideHoverTools();

        // A selection spends the hint a search left behind: the user has
        // found what to do next.
        searchHint = false;

        updatePanel();
    }

    /**
     * Drop the selection: no handles, no style panel.
     *
     * Deselecting also commits the last change: the undo offer only lives
     * while its shape stays continuously selected -- see selectShape().
     *
     * @since  3.0.0
     * @return void
     */
    function clearSelection() {
        setEditable( '' );

        clearUndo();

        selectedId = '';

        if ( adapter ) {
            adapter.highlight( '' );
        }

        $style.hide();

        updatePanel();
    }

    /**
     * Put the drag handles on one shape, or on none.
     *
     * Optional on the adapter -- see onMapReady() -- so this is the one place
     * that knows the method might not be there.
     *
     * @since  3.0.0
     * @param  {string} featureId '' to take the handles off whatever has them.
     * @return void
     */
    function setEditable( featureId ) {
        if ( adapter && 'function' === typeof adapter.setEditable ) {
            adapter.setEditable( featureId );
        }
    }

    /**
     * Delete a shape from the map and the collection.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function removeShape( featureId ) {
        const index = indexOfFeature( featureId );

        if ( index < 0 ) {
            return;
        }

        const previous = snapshot();

        // Before removal, so an adapter with handles on this shape can drop them.
        if ( selectedId === featureId ) {
            clearSelection();
        }

        if ( adapter ) {
            adapter.removeShape( featureId );
        }

        collection.features.splice( index, 1 );

        // Snapshot the delete; the undo button is tied to the selection, and a
        // deleted shape can't be selected, so it won't show -- but the snapshot
        // still belongs on the stack.
        snapshot();
        setUndo( previous, featureId );
        setDirty( true );
    }

    /**
     * Ask before deleting, through the admin's shared confirmation dialog.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return void
     */
    function confirmDelete( featureId ) {
        const feature = findFeature( featureId );

        if ( ! feature ) {
            return;
        }

        selectShape( featureId );

        const $dialog = $( '#wpsl-delete-confirmation' );

        if ( ! $dialog.length || ! $.fn.dialog ) {
            removeShape( featureId );

            return;
        }

        $dialog.removeClass( 'wpsl-hide' );
        $dialog.find( 'p:first-child span' ).text( subjectFor( feature ) );

        $dialog.dialog( {
            resizable:   false,
            height:      'auto',
            minHeight:   79,
            width:       400,
            modal:       true,
            closeText:   '',
            dialogClass: 'wpsl-dialog wpsl-no-titlebar no-close',
            classes:     { 'ui-dialog': 'wpsl-dialog wpsl-no-titlebar no-close' },
            open:        function() {
                $( '.ui-dialog-buttonpane' ).hide();
            }
        } );

        $dialog.dialog( 'open' );

        $( '#wpsl-cancel-delete, .ui-widget-overlay' ).off( 'click.wpslShapes' ).on( 'click.wpslShapes', function() {
            $dialog.dialog( 'close' );

            return false;
        } );

        $( '#wpsl-confirm-delete' ).off( 'click.wpslShapes' ).on( 'click.wpslShapes', function() {
            $dialog.dialog( 'close' );

            removeShape( featureId );

            return false;
        } );
    }

    /**
     * What the confirmation calls the shape it is about to delete.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {string}
     */
    function subjectFor( feature ) {
        // Use the shape's TYPE, not its stored name: "polygon_2" is an
        // internal handle, not a label. The shape is selected before the
        // dialog opens, so its type is what identifies it on screen.
        const types = {
            polygon:   'selectedPolygon',
            circle:    'selectedCircle',
            rectangle: 'selectedRectangle',
            polyline:  'selectedPolyline'
        };

        return text( types[ ( feature.properties || {} ).shape_type ] || 'selectedPolygon' );
    }

    /**
     * The collection's features as they are right now.
     *
     * @since  3.0.0
     * @return {Array}
     */
    function snapshot() {
        const features = [];

        for ( let i = 0; i < collection.features.length; i++ ) {
            features.push( clone( collection.features[ i ] ) );
        }

        return features;
    }

    /**
     * A deep copy of a plain feature.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {object}
     */
    function clone( feature ) {
        return JSON.parse( JSON.stringify( feature ) );
    }

    /**
     * One feature as the baseline holds it, or null when it holds none.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return {object|null}
     */
    function findBaseline( featureId ) {
        for ( let i = 0; i < undoBaseline.length; i++ ) {
            const properties = undoBaseline[ i ].properties || {};

            if ( properties.id === featureId ) {
                return undoBaseline[ i ];
            }
        }

        return null;
    }

    /**
     * Whether a reported edit says anything this feature does not already
     * say. Compared as JSON -- reading a difference where there is none is
     * the safe way to be wrong.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @param  {object} geometry
     * @param  {object} properties
     * @return {boolean}
     */
    function isSameShape( feature, geometry, properties ) {
        return JSON.stringify( feature.geometry ) === JSON.stringify( geometry ) &&
            JSON.stringify( feature.properties ) === JSON.stringify( properties );
    }

    /**
     * Offer one step back.
     *
     * @since  3.0.0
     * @param  {Array}  features  The snapshot taken before the change.
     * @param  {string} featureId The shape the change happened to, which is
     *                            where the button will be shown.
     * @return void
     */
    function setUndo( features, featureId ) {
        undoState     = features;
        undoFeatureId = featureId || '';

        // What was just committed is where the next change starts from.
        undoBaseline = snapshot();

        updateUndo();
    }

    /**
     * Whether the undo button has anywhere to be.
     *
     * @since  3.0.0
     * @return {boolean}
     */
    function undoOffered() {
        return !! undoState && ! activeTool && '' !== undoFeatureId && selectedId === undoFeatureId;
    }

    /**
     * Put the undo button's visibility in step with that rule.
     *
     * Only the visibility: wpsl-shapes-layout.js watches this element and
     * places it -- or hides it when the shape it belongs to is off screen.
     *
     * @since  3.0.0
     * @return void
     */
    function updateUndo() {
        $undo.toggle( undoOffered() );
    }

    /**
     * Take the offer away: no snapshot, no button.
     *
     * @since  3.0.0
     * @return void
     */
    function clearUndo() {
        undoState     = null;
        undoFeatureId = '';

        updateUndo();
    }

    /**
     * Put the collection back to the last snapshot.
     *
     * @since  3.0.0
     * @return void
     */
    function undo() {
        if ( ! undoState ) {
            return;
        }

        collection.features = undoState;

        clearUndo();
        clearSelection();
        hideHoverTools();

        if ( adapter ) {
            adapter.renderCollection( collection );
        }

        // Still dirty: undo reverts one change, not all since the last save.
        setDirty( true );

        // Same channel as the save confirmation.
        snackbar( text( 'undone' ) );
    }

    /**
     * Fill the style panel in from a feature and show it.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function populateStyle( feature ) {
        const props = $.extend( {}, DEFAULT_STYLE, feature.properties || {} );

        populating = true;

        // Absent means active: shapes stored before the property existed.
        const active = false !== props.active;

        $activeToggle.prop( 'checked', active );
        $activeToggle.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', active ? 'true' : 'false' );
        $fillToggle.prop( 'checked', !! props.fill );
        $fillToggle.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', props.fill ? 'true' : 'false' );
        setColor( $fillColor, safeColor( props.fill_color ) );
        setColor( $strokeColor, safeColor( props.stroke_color ) );
        $fillOpacity.val( clampOpacity( props.fill_opacity ) );
        $fillOpacityValue.text( String( clampOpacity( props.fill_opacity ) ) );
        $strokeWidth.val( strokeWidth( props.stroke_width ) );

        $strokeWidthValue.text( strokeWidth( props.stroke_width ) + 'px' );

        // The field now holds a stored name again; a standing duplicate
        // complaint was about what it no longer shows.
        markNameInvalid( false );

        $shapeName.val( props.name || '' );
        $shapeMessage.val( props.message || '' );
        $shapeSwatch.css( 'background-color', swatchColor( props ) );

        populateGeometry( feature );
        refreshShapeList();

        // The shape exists, so exact placement, deletion and the Active
        // toggle apply again -- the prospective panel takes all three away
        $geoInputs.prop( 'disabled', false );
        $activeRow.removeClass( 'wpsl-hide' );
        $delete.show();

        populating = false;

        // A polyline can't be filled, so the fill controls go.
        $style.find( '.wpsl-shape-fill-control' ).toggleClass( 'wpsl-hide', 'polyline' === props.shape_type );

        $style.show();
    }

    /**
     * Rebuild the shape picker from the collection, keeping the selection.
     *
     * @since  3.0.0
     * @return void
     */
    function refreshShapeList() {
        // The head of the list is the way out of a selection.
        let items = '<li role="option" tabindex="-1" data-shape-id=""' +
            ' aria-selected="' + ( selectedId ? 'false' : 'true' ) + '"' +
            ( selectedId ? '' : ' class="is-selected"' ) + '>' +
            '<span class="wpsl-shape-list-swatch wpsl-shape-list-none" aria-hidden="true"></span>' +
            '<span>' + escapeHtml( text( 'selectShape' ) ) + '</span>' +
            '</li>';

        for ( let i = 0; i < collection.features.length; i++ ) {
            const props    = collection.features[ i ].properties || {};
            const name     = props.name || props.shape_type || '';
            const selected = props.id === selectedId;

            // Tagged rather than only dimmed: a word survives where a shade
            // of grey does not.
            const inactive = false === props.active;
            const classes  = ( selected ? 'is-selected ' : '' ) + ( inactive ? 'wpsl-shape-list-inactive' : '' );

            items += '<li role="option" tabindex="-1"' +
                ' data-shape-id="' + escapeAttr( props.id ) + '"' +
                ' aria-selected="' + ( selected ? 'true' : 'false' ) + '"' +
                ( classes.trim() ? ' class="' + classes.trim() + '"' : '' ) + '>' +
                '<span class="wpsl-shape-list-swatch" aria-hidden="true" style="background-color:' + escapeAttr( swatchColor( props ) ) + '"></span>' +
                '<span>' + escapeHtml( name ) + '</span>' +
                ( inactive ? '<span class="wpsl-shape-list-hidden-tag">' + escapeHtml( text( 'hidden' ) ) + '</span>' : '' ) +
                '</li>';
        }

        $shapeList.html( items );

        const current = findFeature( selectedId );

        // With a tool armed the button names the shape being drawn -- the
        // prospective panel's name -- though the list holds no entry for it.
        $shapeSelectLabel.text( current ? ( current.properties.name || current.properties.shape_type || '' ) : ( activeTool ? pendingName : text( 'selectShape' ) ) );

        // The empty string clears the inline color a deselect would otherwise
        // keep; a prospective shape's color is what the style controls hold.
        $shapeSwatch
            .css( 'background-color', current ? swatchColor( current.properties ) : ( activeTool ? swatchColor( applyShapeTypeRules( $.extend( { shape_type: activeTool }, panelStyle() ) ) ) : '' ) )
            .toggleClass( 'wpsl-shape-list-none', ! current && ! activeTool );
    }

    /**
     * Open or close the shape list.
     *
     * @since  3.0.0
     * @param  {boolean} open
     * @return void
     */
    function toggleShapeList( open ) {
        $shapeList.prop( 'hidden', ! open );
        $shapeSelect.attr( 'aria-expanded', open ? 'true' : 'false' );
    }

    /**
     * The color that stands for a shape: its fill, 
     * or its stroke when it has no fill to show.
     *
     * @since  3.0.0
     * @param  {object} props
     * @return {string}
     */
    function swatchColor( props ) {
        return safeColor( props.fill ? props.fill_color : props.stroke_color );
    }

    /**
     * Show the selected shape's geometry in its own terms: centre + radius
     * for a circle, two corners for a rectangle, a point list for the rest.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return void
     */
    function populateGeometry( feature ) {
        const props    = feature.properties || {};
        const geometry = feature.geometry || {};
        const type     = props.shape_type;

        // Stored values are about to overwrite the fields, which ends any
        // standing complaint about them.
        if ( $geoInputs.filter( '.wpsl-shape-invalid' ).length ) {
            setStatus( '', false );
        }

        markInvalidFields();

        $geoCircle.toggleClass( 'wpsl-hide', 'circle' !== type );
        $geoRectangle.toggleClass( 'wpsl-hide', 'rectangle' !== type );
        $geoPath.toggleClass( 'wpsl-hide', 'circle' === type || 'rectangle' === type );

        if ( 'circle' === type ) {
            $shapeCenter.val( latLngText( geometry.coordinates ) );
            $shapeRadius.val( radiusText( props.radius ) );

            return;
        }

        if ( 'rectangle' === type ) {
            const box = boundingBox( geometry, props );

            // [ west, south, east, north ] -- the corners the rectangle was
            // drawn between, whichever way round it was drawn.
            $shapeNe.val( box ? latLngText( [ box[2], box[3] ] ) : '' );
            $shapeSw.val( box ? latLngText( [ box[0], box[1] ] ) : '' );

            return;
        }

        $shapeCoords.val( coordinateLines( feature ) );
    }

    /**
     * Apply the geometry fields to the selected shape -- for exact placement,
     * not shaping (handles do that).
     *
     * @since  3.0.0
     * @return void
     */
    function applyGeometryEdit() {
        const feature = findFeature( selectedId );

        if ( ! feature ) {
            return;
        }

        const edited    = readGeometryFields( feature );
        const candidate = edited ? {
            type:       'Feature',
            geometry:   edited.geometry,
            properties: $.extend( {}, feature.properties, edited.properties )
        } : null;

        if ( ! candidate || ! isDrawableFeature( candidate ) ) {
            const $invalid = invalidGeometryFields( feature );

            markInvalidFields( $invalid, feature );

            setStatus( $invalid[0] ? fieldErrorText( $invalid[0], feature ) : text( 'invalidCoordinates' ), false, true );

            return;
        }

        // Skip unchanged values: fields round to 6 decimals / whole units, so retyping
        // a read-back value would otherwise trigger Undo and dirty the page.
        if ( sameGeometry( feature, candidate ) ) {
            populateGeometry( feature );

            return;
        }

        const previous = snapshot();

        feature.geometry   = candidate.geometry;
        feature.properties = candidate.properties;

        setUndo( previous, feature.properties.id );
        setDirty( true );

        // Redraw and reattach handles to the replaced outline.
        if ( adapter ) {
            adapter.renderCollection( collection );
        }

        setEditable( selectedId );

        // And back out, so the fields show the stored geometry in its own
        // formatting rather than whatever was typed to reach it.
        populateGeometry( feature );
    }

    /**
     * The geometry the fields currently describe, in the terms the selected
     * shape's own kind is stored in.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {object|null} { geometry, properties }, or null when a field
     *                       holds something that is not a coordinate.
     */
    function readGeometryFields( feature ) {
        const type = ( feature.properties || {} ).shape_type;

        if ( 'circle' === type ) {
            const center = parseLatLng( $shapeCenter.val() );
            const radius = parseRadius( $shapeRadius.val() );

            if ( ! center || ! ( radius > 0 ) ) {
                return null;
            }

            return {
                geometry:   { type: 'Point', coordinates: center },
                properties: { radius: radius }
            };
        }

        if ( 'rectangle' === type ) {
            const ne = parseLatLng( $shapeNe.val() );
            const sw = parseLatLng( $shapeSw.val() );

            if ( ! ne || ! sw ) {
                return null;
            }

            // Corner-ordered, not taken as typed: the two fields are named
            // for corners, so a north-east below its south-west is a rectangle
            // described backwards, not an empty one.
            return {
                geometry:   { type: 'Polygon', coordinates: [ cornerRing( ne, sw ) ] },
                properties: {}
            };
        }

        const positions = parsePositions( $shapeCoords.val() );

        if ( ! positions.length ) {
            return null;
        }

        if ( 'polyline' === type ) {
            return {
                geometry:   { type: 'LineString', coordinates: positions },
                properties: {}
            };
        }

        // The list is written without its closing position ( see
        // coordinateLines() ), so it is put back here.
        return {
            geometry:   { type: 'Polygon', coordinates: [ closeRing( positions ) ] },
            properties: {}
        };
    }

    /**
     * Which of the fields on show is the reason the edit was refused --
     * narrowed to the single field wherever it can be: one bad corner of a
     * rectangle is the corner to point at, not both.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {jQuery}
     */
    function invalidGeometryFields( feature ) {
        const type = ( feature.properties || {} ).shape_type;
        let $invalid = $();

        if ( 'circle' === type ) {
            if ( ! parseLatLng( $shapeCenter.val() ) ) {
                $invalid = $invalid.add( $shapeCenter );
            }

            if ( ! ( parseRadius( $shapeRadius.val() ) > 0 ) ) {
                $invalid = $invalid.add( $shapeRadius );
            }

            return $invalid;
        }

        if ( 'rectangle' === type ) {
            if ( ! parseLatLng( $shapeNe.val() ) ) {
                $invalid = $invalid.add( $shapeNe );
            }

            if ( ! parseLatLng( $shapeSw.val() ) ) {
                $invalid = $invalid.add( $shapeSw );
            }

            return $invalid;
        }

        // A polygon or polyline is one field, so there is nothing to narrow to.
        return $shapeCoords;
    }

    /**
     * Outline the geometry fields that were refused, and only those, each
     * with the reason written on the line below it.
     *
     * @since  3.0.0
     * @param  {jQuery} [$fields] Nothing clears every outline.
     * @param  {object} [feature] The shape the fields describe. Required
     *                            alongside $fields: what a list is short of
     *                            depends on which kind is being typed into.
     * @return void
     */
    function markInvalidFields( $fields, feature ) {
        $geoInputs.each( function() {
            $( this ).removeClass( 'wpsl-shape-invalid' ).removeAttr( 'aria-invalid' ).removeAttr( 'aria-describedby' );
            errorLine( this ).text( '' );
        } );

        if ( ! $fields || ! $fields.length ) {
            return;
        }

        $fields.each( function() {
            const $error = errorLine( this );

            // Described by the line as well as outlined: the outline is the
            // only part of this a screen reader would otherwise reach.
            $( this )
                .addClass( 'wpsl-shape-invalid' )
                .attr( 'aria-invalid', 'true' )
                .attr( 'aria-describedby', $error.attr( 'id' ) );

            $error.text( fieldErrorText( this, feature ) );
        } );
    }

    /**
     * The error line belonging to a geometry field.
     *
     * @since  3.0.0
     * @param  {Element} field
     * @return {jQuery}
     */
    function errorLine( field ) {
        return $( '#' + field.id + '-error' );
    }

    /**
     * Why a geometry field was refused, in the terms that field is typed in.
     *
     * @since  3.0.0
     * @param  {Element} field
     * @param  {object}  feature
     * @return {string}
     */
    function fieldErrorText( field, feature ) {
        // A radius is a distance, and "not a valid coordinate" under a field
        // labelled Radius describes nothing the user did.
        if ( field === $shapeRadius[0] ) {
            return text( 'invalidRadius' );
        }

        // parsePositions() empties the whole list on the first unreadable
        // line, so a list that survives it was refused on its length.
        if ( field === $shapeCoords[0] && parsePositions( $shapeCoords.val() ).length ) {
            return text( 'polyline' === ( feature.properties || {} ).shape_type ? 'tooFewLinePoints' : 'tooFewPolygonPoints' );
        }

        return text( 'invalidCoordinates' );
    }

    /**
     * Whether a candidate says the same thing the stored feature already does.
     *
     * The radius is compared beside the geometry because a circle keeps half
     * of where it is in its properties.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @param  {object} candidate
     * @return {boolean}
     */
    function sameGeometry( feature, candidate ) {
        if ( JSON.stringify( feature.geometry ) !== JSON.stringify( candidate.geometry ) ) {
            return false;
        }

        return ( feature.properties || {} ).radius === candidate.properties.radius;
    }

    /**
     * One typed "lat,lng" as a GeoJSON [ lng, lat ] position.
     *
     * Lenient about separator formatting (space, semicolon, etc.) since this
     * field is for pasting; strict about the numbers -- out-of-range or
     * non-numeric values are refused.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {Array|null} [ lng, lat ].
     */
    function parseLatLng( value ) {
        const parts = String( null == value ? '' : value ).trim().split( /[\s,;]+/ );

        if ( 2 !== parts.length || ! parts[0] || ! parts[1] ) {
            return null;
        }

        // Number(), not parseFloat(): parseFloat reads "51.5x" as 51.5, a
        // typo accepted as a coordinate.
        const position = [ Number( parts[1] ), Number( parts[0] ) ];

        return isPosition( position ) ? position : null;
    }

    /**
     * A typed radius back into the metres a circle is stored in.
     *
     * The readout carries the unit the locator is set to ( "1200 m",
     * "3900 ft" ) and is left in the field to be typed over, so the unit is
     * stripped: whatever is typed is in the unit the field is showing.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {number} Metres, or 0 for anything that is not a distance.
     */
    function parseRadius( value ) {
        // The unit comes off the END and nothing else does: stripping every
        // non-digit would turn a typed "-50" into a 50 metre radius.
        const match  = /^([0-9]+(?:\.[0-9]+)?)\s*[a-z]*$/i.exec( String( null == value ? '' : value ).trim() );
        const amount = match ? Number( match[1] ) : 0;

        if ( ! ( amount > 0 ) ) {
            return 0;
        }

        return 'mi' === wpslMapShapes.distanceUnit ? amount / 3.28084 : amount;
    }

    /**
     * The coordinate list as GeoJSON positions, one line at a time.
     *
     * One line that will not parse fails the whole list rather than being
     * skipped: quietly dropping a corner from a pasted polygon gives a subtly
     * wrong shape, worse than one that comes back refused.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {Array} Empty when any line is not a coordinate.
     */
    function parsePositions( value ) {
        const lines     = String( null == value ? '' : value ).split( /[\r\n]+/ );
        const positions = [];

        for ( let i = 0; i < lines.length; i++ ) {
            if ( ! lines[ i ].trim() ) {
                continue;
            }

            const position = parseLatLng( lines[ i ] );

            if ( ! position ) {
                return [];
            }

            positions.push( position );
        }

        return positions;
    }

    /**
     * Two opposite corners as the canonical rectangle ring.
     *
     * The same SW, SE, NE, NW, SW order every adapter writes ( cornersToRing()
     * in each of the three ), so a rectangle typed here is stored exactly like
     * one that was drawn.
     *
     * @since  3.0.0
     * @param  {Array} a [ lng, lat ].
     * @param  {Array} b [ lng, lat ].
     * @return {Array}
     */
    function cornerRing( a, b ) {
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
     * Repeat the first position at the end, once.
     *
     * @since  3.0.0
     * @param  {Array} positions
     * @return {Array}
     */
    function closeRing( positions ) {
        const first = positions[0];
        const last  = positions[ positions.length - 1 ];

        if ( first[0] === last[0] && first[1] === last[1] ) {
            return positions;
        }

        return positions.concat( [ [ first[0], first[1] ] ] );
    }

    /**
     * One GeoJSON [ lng, lat ] position as displayed "lat,lng".
     *
     * @since  3.0.0
     * @param  {Array} position
     * @return {string}
     */
    function latLngText( position ) {
        if ( ! position || position.length < 2 ) {
            return '';
        }

        return Number( position[1] ).toFixed( 6 ) + ',' + Number( position[0] ).toFixed( 6 );
    }

    /**
     * A radius in the units the locator is set to.
     *
     * Stored in metres whatever the setting -- this is display only, so the
     * number matches the distances the rest of the locator quotes.
     *
     * @since  3.0.0
     * @param  {number} metres
     * @return {string}
     */
    function radiusText( metres ) {
        const value = parseFloat( metres );

        if ( ! isFinite( value ) || value <= 0 ) {
            return '';
        }

        if ( 'mi' === wpslMapShapes.distanceUnit ) {
            return Math.round( value * 3.28084 ) + ' ' + text( 'feet' );
        }

        return Math.round( value ) + ' ' + text( 'metres' );
    }

    /**
     * The selected shape's geometry as one "lat,lng" per line.
     *
     * Latitude first, the order every mapping UI quotes a place in, and the
     * reverse of GeoJSON's [ lng, lat ] storage. Six decimals is about 10cm --
     * past the point the map can be clicked to.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {string}
     */
    function coordinateLines( feature ) {
        const geometry = ( feature || {} ).geometry;

        if ( ! geometry ) {
            return '';
        }

        let positions;

        if ( 'Point' === geometry.type ) {
            positions = [ geometry.coordinates ];
        } else if ( 'LineString' === geometry.type ) {
            positions = geometry.coordinates;
        } else {
            // A polygon's outer ring.
            positions = ( geometry.coordinates || [] )[0] || [];
            positions = positions.slice( 0, Math.max( 0, positions.length - 1 ) );
        }

        const lines = [];

        for ( let i = 0; i < positions.length; i++ ) {
            const position = positions[ i ];
            if ( position && position.length >= 2 ) {
                lines.push( Number( position[1] ).toFixed( 6 ) + ',' + Number( position[0] ).toFixed( 6 ) );
            }
        }

        return lines.join( '\n' );
    }

    /**
     * Escape a string for an HTML attribute value.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {string}
     */
    function escapeAttr( value ) {
        return String( value == null ? '' : value ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
    }

    /**
     * Escape a string for HTML text content.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {string}
     */
    function escapeHtml( value ) {
        return String( value == null ? '' : value ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
    }

    /**
     * Sanitize a shape name to letters, digits, hyphen and underscore.
     *
     * Keeps names safe for rules, shortcode attributes and URLs. Letters
     * includes any script ( \p{L} ), not just A-Z. Applied on input only;
     * stored names from older versions are left as-is until edited.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {string}
     */
    function cleanShapeName( value ) {
        return String( value == null ? '' : value ).replace( /[^\p{L}\p{N}_-]/gu, '' );
    }

    /**
     * The name a finished drawing gets: what the prospective panel collected,
     * if it is still free, else the next generated one.
     *
     * @since  3.0.0
     * @param  {string} shapeType
     * @return {string}
     */
    function prospectiveName( shapeType ) {
        if ( '' !== pendingName && ! nameTaken( pendingName ) ) {
            return pendingName;
        }

        return uniqueName( shapeType );
    }

    /**
     * Whether a shape other than the selected one already carries this name.
     *
     * @since  3.0.0
     * @param  {string} name
     * @return {boolean}
     */
    function nameTaken( name ) {
        if ( '' === name ) {
            return false;
        }

        for ( let i = 0; i < collection.features.length; i++ ) {
            const props = collection.features[ i ].properties || {};

            if ( props.id !== selectedId && props.name === name ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Put the duplicate-name complaint on the name field, or take it off.
     *
     * @since  3.0.0
     * @param  {boolean} invalid
     * @return void
     */
    function markNameInvalid( invalid ) {
        if ( invalid === nameInvalid ) {
            return;
        }

        nameInvalid = invalid;

        const $error = errorLine( $shapeName[0] );

        if ( invalid ) {
            $shapeName
                .addClass( 'wpsl-shape-invalid' )
                .attr( 'aria-invalid', 'true' )
                .attr( 'aria-describedby', $error.attr( 'id' ) );

            $error.text( text( 'duplicateName' ) );
            setStatus( text( 'duplicateName' ), false, true );

            return;
        }

        $shapeName.removeClass( 'wpsl-shape-invalid' ).removeAttr( 'aria-invalid' ).removeAttr( 'aria-describedby' );
        $error.text( '' );

        if ( ! $geoInputs.filter( '.wpsl-shape-invalid' ).length ) {
            setStatus( '', false );
        }
    }

    /**
     * Give up on a refused rename
     *
     * @since  3.0.0
     * @return void
     */
    function restoreRefusedName() {
        if ( ! nameInvalid ) {
            return;
        }

        const feature = findFeature( selectedId );
        $shapeName.val( feature ? ( feature.properties.name || '' ) : ( activeTool ? pendingName : '' ) );

        markNameInvalid( false );
    }

    /**
     * The style panel's current values, as feature properties.
     *
     * @since  3.0.0
     * @return {object}
     */
    function panelStyle() {
        return {
            fill:         $fillToggle.prop( 'checked' ),
            fill_color:   safeColor( $fillColor.val() ),
            fill_opacity: clampOpacity( $fillOpacity.val() ),
            stroke_color: safeColor( $strokeColor.val() ),
            stroke_width: strokeWidth( $strokeWidth.val() )
        };
    }

    /**
     * A Color field changed, from WPSL_ColorPicker or the plain input.
     *
     * @since  3.0.0
     * @param  {object} event
     * @return void
     */
    function onColorChange( event ) {
        const $input = $( event.target );
        const value  = $input.val();
        const key    = 'wpsl-shape-fill-color' === $input.attr( 'id' ) ? 'fill_color' : 'stroke_color';

        writeProperty( key, safeColor( value ) );
    }

    /**
     * Write a style value into the selected feature and onto the map.
     *
     * @since  3.0.0
     * @param  {string} key
     * @param  {*}      value
     * @return void
     */
    /*
     * The properties the shape picker actually renders: its rows carry a name
     * and a swatch and mark the hidden ones, and the header repeats the
     * selected shape's. Everything else -- fill opacity, stroke width -- can
     * change without the list changing, and those are exactly the properties
     * a slider fires on every pixel of a drag.
     */
    const LIST_PROPERTIES = [ 'name', 'shape_type', 'active', 'fill', 'fill_color', 'stroke_color' ];

    function writeProperty( key, value ) {
        if ( populating ) {
            return;
        }

        const feature = findFeature( selectedId );
        if ( ! feature || feature.properties[ key ] === value ) {
            return;
        }

        feature.properties[ key ] = value;

        if ( adapter ) {
            adapter.updateStyle( selectedId, feature.properties );
        }

        setDirty( true, LIST_PROPERTIES.indexOf( key ) !== -1 );
    }

    /**
     * Put a color into a field, whichever kind of field it turned out to be.
     *
     * @since  3.0.0
     * @param  {object} $input
     * @param  {string} value
     * @return void
     */
    function setColor( $input, value ) {
        const picker = $input.data( 'wpsl-color-picker' );
        if ( picker ) {
            $input.val( value );
            picker.parseInitialColor();
            picker.updateUI( true );

            return;
        }

        $input.val( value );
    }

    /**
     * POST the collection to Manager::save_shapes().
     *
     * @since  3.0.0
     * @return void
     */
    function saveShapes() {
        $save.prop( 'disabled', true );

        $savePreloader.prop( 'hidden', false );
        setStatus( text( 'saving' ), false, true );

        $.post( wpslMapShapes.ajaxurl, {
            action:     'wpsl_save_map_shapes',
            nonce:      wpslMapShapes.nonce,
            collection: JSON.stringify( collection )
        } ).done( function( response ) {
            if ( ! response || ! response.success || ! response.data || ! response.data.collection ) {
                setStatus( errorMessage( response ), true );

                return;
            }

            // Server copy replaces local: fills defaults, clamps opacities,
            // generates missing ids, drops unstorable values.
            collection = normalizeCollection( response.data.collection );

            // Snapshot is stale -- server replaced every feature.
            clearUndo();
            hideHoverTools();

            if ( adapter ) {
                adapter.renderCollection( collection );
            }

            const stillThere = findFeature( selectedId );
            if ( stillThere ) {
                populateStyle( stillThere );

                if ( adapter ) {
                    adapter.highlight( selectedId );
                }

                setEditable( selectedId );
            } else {
                clearSelection();
            }

            setDirty( false );

            setStatus( '', false );
            snackbar( text( 'saved' ) );
        } ).fail( function() {
            setStatus( text( 'requestFailed' ), true );
        } ).always( function() {
            $save.prop( 'disabled', false );
            $savePreloader.prop( 'hidden', true );
        } );
    }

    /**
     * Geocode whatever is in the search field and move the map there. The
     * geocoding is the server's, through the configured provider; the adapter
     * is only asked to move.
     *
     * @since  3.0.0
     * @return void
     */
    function runPlaceSearch() {
        const address = ( $searchInput.val() || '' ).trim();

        if ( ! mapReady || ! adapter || 'function' !== typeof adapter.panTo ) {
            return;
        }

        if ( ! address ) {
            setSearchMessage( text( 'searchEmpty' ), true );
            $searchInput.trigger( 'focus' );

            return;
        }

        $searchBtn.prop( 'disabled', true );
        setSearchMessage( text( 'searching' ), false );

        // The clear button's column swaps its cross for a spinner
        searchInFlight = true;
        $searchClearWrap.show();
        $searchClear.hide();
        $searchPreloader.prop( 'hidden', false );

        $.post( wpslMapShapes.ajaxurl, {
            action:  'wpsl_map_shapes_geocode',
            nonce:   wpslMapShapes.nonce,
            address: address
        } ).done( function( response ) {
            if ( ! response || ! response.success || ! response.data || 'number' !== typeof response.data.lat ) {
                setSearchMessage( errorMessage( response, 'searchFailed' ), true );

                return;
            }

            adapter.panTo( response.data.lat, response.data.lng, PLACE_ZOOM );

            clearUndo();

            // The map moving is the answer; a message on top of it is noise.
            setSearchMessage( '', false );

            // A place was searched for in order to draw on it.
            searchHint = true;

            clearSelection();
        } ).fail( function() {
            setSearchMessage( text( 'searchFailed' ), true );
        } ).always( function() {
            $searchBtn.prop( 'disabled', false );

            searchInFlight = false;
            $searchPreloader.prop( 'hidden', true );
            
            updateClearVisibility();
        } );
    }

    /**
     * Show or hide the clear button to match whether the field has text.
     * Skipped mid-search, when runPlaceSearch() owns the spinner in that column.
     *
     * @since  3.0.0
     * @return void
     */
    function updateClearVisibility() {
        if ( searchInFlight ) {
            return;
        }

        const hasValue = '' !== $searchInput.val();

        $searchClear.toggle( hasValue );
        $searchClearWrap.toggle( hasValue );
    }

    /**
     * Announce the search status and mark failures on the field itself.
     *
     * @since  3.0.0
     * @param  {string}  message An empty string clears it.
     * @param  {boolean} isError
     * @return void
     */
    function setSearchMessage( message, isError ) {
        $searchWrap.toggleClass( 'wpsl-error', !! isError );
        $searchMessage.text( message ).prop( 'hidden', ! message );
    }

    /**
     * Switch the search field on or off.
     *
     * @since  3.0.0
     * @param  {boolean} enabled
     * @return void
     */
    function setSearchEnabled( enabled ) {
        $searchInput.prop( 'disabled', ! enabled );
        $searchBtn.prop( 'disabled', ! enabled );
    }

    /**
     * The message to show for a request that answered but did not succeed.
     *
     * @since  3.0.0
     * @param  {object} response
     * @param  {string} [fallback] The l10n key to fall back to. Defaults to the
     *                             save failure, the older caller.
     * @return {string}
     */
    function errorMessage( response, fallback ) {
        if ( response && response.data && response.data.message ) {
            return String( response.data.message );
        }

        return text( fallback || 'saveFailed' );
    }

    /**
     * Confirm something that worked, in the settings screens' snackbar.
     * Successes here, failures to the status line: a failure must stay until dealt with.
     *
     * @since  3.0.0
     * @param  {string} message
     * @return void
     */
    function snackbar( message ) {
        if ( window.wpslSharedFuncs && 'function' === typeof wpslSharedFuncs.snackbar ) {
            wpslSharedFuncs.snackbar( message );
        }
    }

    /**
     * Write the status line.
     *
     * @since  3.0.0
     * @param  {string}  message
     * @param  {boolean} isError
     * @param  {boolean} [quiet]  Announce it without showing it.
     * @return void
     */
    function setStatus( message, isError, quiet ) {
        if ( quiet && message ) {
            $status.empty().append( $( '<span class="screen-reader-text"></span>' ).text( message ) );
        } else {
            $status.text( message );
        }

        $status.toggleClass( 'wpsl-shapes-status-error', !! isError );

        updateActions();
    }

    /**
     * Flip the unsaved-changes flag.
     *
     * @since  3.0.0
     * @param  {boolean} value
     * @return void
     */
    function setDirty( value, refreshList ) {
        dirty = !! value;

        if ( dirty ) {
            setStatus( '', false );
        }

        updateActions();

        // Every shape-changing action ends here -- the one place the picker
        // and column are brought back into step with the collection.
        updatePanel( refreshList );
    }

    /**
     * Show the action row only when it has something to act on: a shape, an
     * unsaved change, or a status message (the only error channel -- hidden
     * errors are unread). Undo lives on the map, not here.
     *
     * @since  3.0.0
     * @return void
     */
    function updateActions() {
        $actions.toggle( dirty || collection.features.length > 0 || '' !== $status.text() );
    }

    /**
     * A localized string.
     *
     * @since  3.0.0
     * @param  {string} key
     * @return {string}
     */
    function text( key ) {
        return ( key && l10n[ key ] ) ? l10n[ key ] : String( key || '' );
    }

    /**
     * Coerce whatever is stored into a usable FeatureCollection.
     *
     * @since  3.0.0
     * @param  {*} raw
     * @return {object}
     */
    function normalizeCollection( raw ) {
        if ( ! raw || 'FeatureCollection' !== raw.type || ! Array.isArray( raw.features ) ) {
            return { type: 'FeatureCollection', features: [] };
        }

        for ( let i = 0; i < raw.features.length; i++ ) {
            if ( ! raw.features[ i ].properties ) {
                raw.features[ i ].properties = {};
            }
        }

        return raw;
    }

    /**
     * A feature by id.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return {object|null}
     */
    function findFeature( featureId ) {
        const index = indexOfFeature( featureId );

        return index > -1 ? collection.features[ index ] : null;
    }

    /**
     * A feature's index by id.
     *
     * @since  3.0.0
     * @param  {string} featureId
     * @return {number} -1 when there is no such feature.
     */
    function indexOfFeature( featureId ) {
        if ( ! featureId ) {
            return -1;
        }

        for ( let i = 0; i < collection.features.length; i++ ) {
            if ( ( collection.features[ i ].properties || {} ).id === featureId ) {
                return i;
            }
        }

        return -1;
    }

    /**
     * An id no feature in the collection already has. Base 36, lower case --
     * the server runs it through sanitize_key(), which rewrites anything else.
     *
     * @since  3.0.0
     * @return {string}
     */
    function uniqueId() {
        let id;

        do {
            id = 'shape_' + Date.now().toString( 36 ) + '_' + ( idSequence++ );
        } while ( indexOfFeature( id ) > -1 );

        return id;
    }

    /**
     * A "<type>_<n>" name no shape already uses -- matches
     * Core\Shapes\Sanitizer::next_name(). Counting up survives deletions and renames.
     *
     * @since  3.0.0
     * @param  {string} shapeType
     * @return {string}
     */
    function uniqueName( shapeType ) {
        const taken = {};

        for ( let i = 0; i < collection.features.length; i++ ) {
            const name = ( collection.features[ i ].properties || {} ).name;

            if ( name ) {
                taken[ name ] = true;
            }
        }

        const type   = shapeType || 'polygon';
        let number = 1;

        while ( taken[ type + '_' + number ] ) {
            number++;
        }

        return type + '_' + number;
    }

    /**
     * Whether a feature is one the server would store -- the geometry rules
     * from Core\Shapes\Sanitizer. Anything else is an unfinished drawing.
     *
     * @since  3.0.0
     * @param  {object} feature
     * @return {boolean}
     */
    function isDrawableFeature( feature ) {
        if ( ! feature || ! feature.geometry ) {
            return false;
        }

        const geometry    = feature.geometry;
        const coordinates = geometry.coordinates;

        if ( 'Point' === geometry.type ) {
            return isPosition( coordinates ) && parseFloat( ( feature.properties || {} ).radius ) > 0;
        }

        if ( 'LineString' === geometry.type ) {
            return isPositionList( coordinates, 2 );
        }

        if ( 'Polygon' === geometry.type ) {
            const ring = Array.isArray( coordinates ) ? coordinates[0] : null;

            return isPositionList( ring, 4 ) && isClosedRing( ring );
        }

        return false;
    }

    /**
     * A numeric [ lng, lat ] pair inside the coordinate range.
     *
     * @since  3.0.0
     * @param  {*} position
     * @return {boolean}
     */
    function isPosition( position ) {
        if ( ! Array.isArray( position ) || position.length < 2 ) {
            return false;
        }

        const lng = position[0];
        const lat = position[1];

        if ( 'number' !== typeof lng || 'number' !== typeof lat || ! isFinite( lng ) || ! isFinite( lat ) ) {
            return false;
        }

        return lng >= -180 && lng <= 180 && lat >= -90 && lat <= 90;
    }

    /**
     * A list of at least `minimum` valid positions.
     *
     * @since  3.0.0
     * @param  {*}      positions
     * @param  {number} minimum
     * @return {boolean}
     */
    function isPositionList( positions, minimum ) {
        if ( ! Array.isArray( positions ) || positions.length < minimum ) {
            return false;
        }

        for ( let i = 0; i < positions.length; i++ ) {
            if ( ! isPosition( positions[ i ] ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a ring's first position is repeated at its end.
     *
     * Every adapter closes its rings ( closeRing() ), so an open one here
     * means a conversion went wrong, not that the user drew something unusual.
     *
     * @since  3.0.0
     * @param  {Array} ring
     * @return {boolean}
     */
    function isClosedRing( ring ) {
        const first = ring[0];
        const last  = ring[ ring.length - 1 ];

        return first[0] === last[0] && first[1] === last[1];
    }

    /**
     * A hex color, or the default. The panel is free text and these values go
     * to map libraries and .css(); nothing else leaves this function.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {string}
     */
    function safeColor( value ) {
        return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( String( value ) ) ? String( value ) : DEFAULT_COLOR;
    }

    /**
     * A 0-1 opacity, rounded to the slider's step.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {number}
     */
    function clampOpacity( value ) {
        const opacity = parseFloat( value );

        if ( isNaN( opacity ) ) {
            return DEFAULT_STYLE.fill_opacity;
        }

        return Math.round( Math.min( 1, Math.max( 0, opacity ) ) * 100 ) / 100;
    }

    /**
     * A non-negative integer stroke width.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {number}
     */
    function strokeWidth( value ) {
        const width = parseInt( value, 10 );

        if ( isNaN( width ) || width < 0 ) {
            return DEFAULT_STYLE.stroke_width;
        }

        return width;
    }

    window.wpslShapesEditor = {
        /**
         * The currently selected feature, or null. Published for the layout
         * glue ( wpsl-shapes-layout.js ), which pans the selected shape clear
         * of its style panel overlay.
         */
        selectedFeature: function() {
            const index = selectedId ? indexOfFeature( selectedId ) : -1;

            return index === -1 ? null : collection.features[ index ];
        },

        /**
         * A feature by id, or null. The layout's duplicate handling reads the
         * SOURCE shape this way after the duplicate moves the selection to the
         * copy, to fit both on screen together.
         */
        featureById: function( id ) {
            const index = id ? indexOfFeature( id ) : -1;

            return index === -1 ? null : collection.features[ index ];
        },

        /**
         * Whether the undo button should be on screen -- see undoOffered().
         * The layout reads this rather than the button's own display, because
         * it writes that display: the offer and the placement must come from
         * different places or they answer each other.
         */
        canUndo: undoOffered,

        /**
         * The shape the pending undo is about, for the undo button to sit
         * against. Always one still on the map: undoOffered() requires the
         * selected shape, and a deleted shape cannot be selected.
         */
        undoFeature: function() {
            const index = undoOffered() ? indexOfFeature( undoFeatureId ) : -1;

            return index === -1 ? null : collection.features[ index ];
        },

        /**
         * Duplicate the selected shape, exactly as the hover toolbar's
         * duplicate does.
         */
        duplicateSelected: function() {
            if ( selectedId ) {
                duplicateShape( selectedId );
            }
        },

        /**
         * Every shape currently on the map. Published for the layout glue,
         * which fits them all into the viewport when the page opens.
         */
        allFeatures: function() {
            return collection.features.slice();
        },

        placement: {
            boundingBox: boundingBox
        }
    };
} )( jQuery );