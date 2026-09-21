/**
 * Marker Studio editor pane: the "Edit marker" column (name, shape toggles,
 * icon picker, Color fields, sliders, drop-shadow, save / delete actions).
 * Persists via the wpsl_marker_manager_save / _delete AJAX actions.
 *
 * @since 3.0.0
 */
( function( $ ) {
    const $editor = $( '#wpsl-ms-editor' );

    if ( ! $editor.length || typeof wpslMarkerStudio === 'undefined' ) {
        return;
    }

    const l10n     = wpslMarkerStudio.l10n || {};
    const defaults = wpslMarkerStudio.defaults || {};

    const $document = $( document );

    const $name             = $( '#wpsl-ms-name' );
    const $shapes           = $( '#wpsl-ms-shapes' );
    const $shapeToggle      = $shapes.find( '.wpsl-ms-shape' );
    const $iconSearch       = $( '#wpsl-ms-icon-search' );
    const $iconClear        = $iconSearch.siblings( '.wpsl-ms-search-clear' );
    const $iconGrid         = $( '#wpsl-ms-icon-grid' );
    let   $iconTiles        = $iconGrid.find( '.wpsl-ms-icon-tile' );
    let   tileIndexCache    = null;
    let   iconSearchTimer   = null;
    const $iconCount        = $( '#wpsl-ms-icon-count' );
    const $iconEmpty        = $( '#wpsl-ms-icon-empty' );
    const $icon             = $( '#wpsl-ms-icon' );
    const $logoId           = $( '#wpsl-ms-logo-id' );
    const $iconToolbar      = $( '#wpsl-ms-icon-toolbar' );
    const $iconCategory     = $( '#wpsl-ms-icon-category' );
    const $uploadLogo       = $( '#wpsl-ms-upload-logo' );
    const $logoChip         = $( '#wpsl-ms-logo-badge' );
    const $logoThumb        = $( '#wpsl-ms-logo-badge-thumb' );
    const $logoName         = $( '#wpsl-ms-logo-badge-name' );
    const $logoMissing      = $( '#wpsl-ms-logo-missing' );
    const $labelRow         = $( '#wpsl-ms-label-row' );
    const $labelText        = $( '#wpsl-ms-label-text' );
    const $iconColorRow     = $( '.wpsl-ms-color[data-color-id="wpsl-ms-icon-color"]' );
    const $outline          = $( '#wpsl-ms-outline' );
    const $outlineOut       = $( '#wpsl-ms-outline-value' );
    const $radius           = $( '#wpsl-ms-radius' );
    const $radiusOut        = $( '#wpsl-ms-radius-value' );
    const $frame            = $( '#wpsl-ms-frame' );
    const $imageFill        = $( '#wpsl-ms-image-fill' );
    const $imageFillRow     = $( '#wpsl-ms-image-fill-row' );
    const $fillColorRow     = $( '.wpsl-ms-color[data-color-id="wpsl-ms-fill"]' );
    const $size             = $( '#wpsl-ms-size' );
    const $sizeOut          = $( '#wpsl-ms-size-value' );
    const $iconSize         = $( '#wpsl-ms-icon-size' );
    const $iconSizeOut      = $( '#wpsl-ms-icon-size-value' );
    const $iconSizeLabel    = $( 'label[for="wpsl-ms-icon-size"]' );
    const $glyphGroup       = $( '#wpsl-ms-glyph-group' );
    const $glyphLabel       = $( '#wpsl-ms-glyph-label' );
    const $shadow           = $( '#wpsl-ms-shadow' );
    const $centerFill       = $( '#wpsl-ms-center-fill' );
    const $centerFillRow    = $( '#wpsl-ms-center-fill-row' );
    const $centerColorRow   = $( '.wpsl-ms-color[data-color-id="wpsl-ms-center-color"]' );
    const $status           = $( '#wpsl-ms-status' );
    const $save             = $( '#wpsl-ms-save' );
    const $savePreloader    = $( '#wpsl-ms-save-preloader' );
    const $duplicate        = $( '#wpsl-ms-duplicate' );
    const $remove           = $( '#wpsl-ms-delete' );
    const $shortcodeOpen    = $( '#wpsl-ms-get-shortcode' );
    const $shortcodeDialog  = $( '#wpsl-ms-shortcode-dialog' );
    const $shortcodeId      = $( '#wpsl-ms-shortcode-id' );
    const $shortcodeExample = $( '#wpsl-ms-shortcode-example' );
    const $shortcodeCopy    = $( '#wpsl-ms-copy-shortcode' );
    const $imageUpload      = $( '#wpsl-ms-upload-image' );
    const $imageThumb       = $( '#wpsl-ms-image-thumb' );
    const $studioWrap       = $( '#wpsl-content-wrap' );

    // Tab bar elements.
    const $tabbar       = $( '.wpsl-ms-tabs' );
    const $tabDesign    = $( '#wpsl-ms-tab-design-btn' );
    const $tabLibrary   = $( '#wpsl-ms-tab-library-btn' );
    const $panelDesign  = $( '#wpsl-ms-tab-design' );
    const $panelLibrary = $( '#wpsl-ms-tab-library' );
    const tabs          = [ $tabDesign, $tabLibrary ];
    const tabIds        = [ 'design', 'library' ];

    // The color fields, keyed by the marker field each one writes.
    const colorFields = {
        fill_color:   $( '#wpsl-ms-fill' ),
        icon_color:   $( '#wpsl-ms-icon-color' ),
        stroke_color: $( '#wpsl-ms-stroke' ),
        center_color: $( '#wpsl-ms-center-color' )
    };

    const ICON_COLUMNS = 7;

    // Every tile but "No icon", "Dot" and "Text", none of which is an icon and
    // all of which are excluded from the "shown / total" readout for that reason.
    const iconTotal = $iconTiles.filter( '[data-icon]' ).not( '.wpsl-ms-icon-tile-dot, .wpsl-ms-icon-tile-text' ).length - 1;

    // Id of the marker currently loaded, empty while creating a new one.
    let currentEditId = '';
    let changeRafId;

    let seededIcon = null;

    // 'text' or 'dot': the tile a shape released. '' when nothing is parked.
    let parkedGlyph = '';

    let snapshot = null;

    /**
     * Wire the pane up and sync every readout with the initial values.
     *
     * @since  3.0.0
     * @return void
     */
    function init() {
        $name.on( 'input', announceChange );

        // Clear the missing-name mark as soon as the user types.
        $name.on( 'input', clearMissingName );

        $shapes.on( 'click', '.wpsl-ms-shape', onShapeClick );

        $iconSearch.on( 'input search', function() {
            clearTimeout( iconSearchTimer );
            iconSearchTimer = setTimeout( filterIcons, 100 );
        } );
        $iconSearch.on( 'input', function() {
            $iconClear.prop( 'hidden', ! this.value );
            $iconSearch.closest( '.wpsl-ms-search' ).toggleClass( 'wpsl-ms-search-filled', !! this.value );
        } );

        $iconClear.on( 'click', function() {
            $iconSearch.val( '' ).trigger( 'input' ).focus();
        } );

        // Category dropdown: filters the grid by group, alongside the search.
        $iconCategory.on( 'change', filterIcons );

        // Delegated: one handler for every tile, no matter how many.
        $iconGrid.on( 'click', '.wpsl-ms-icon-tile', onIconTileClick );
        $iconGrid.on( 'keydown', '.wpsl-ms-icon-tile', onIconGridKeydown );

        // The Custom tile's remove control.
        $iconGrid.on( 'click', '.wpsl-ms-logo-tile-remove', onLogoTileRemove );

        // Logo upload: the "Upload logo" button and the badge's "Replace" both
        // open the same wp.media modal. "Remove" clears the logo, which brings
        // back the glyph the marker had underneath it.
        $uploadLogo.on( 'click', openMediaModal );
        $imageUpload.on( 'click', openMediaModal );

        $( '#wpsl-ms-logo-badge-replace' ).on( 'click', openMediaModal );
        $( '#wpsl-ms-logo-badge-remove' ).on( 'click', function() {
            clearLogo();
            announceChange();
        } );

        // Clamp the Text glyph's field as typed, by the same rule the save
        // applies -- but not mid-IME-composition, where writing this.value
        // would cancel it. compositionend fires an input of its own.
        $labelText.on( 'input compositionend', function( e ) {
            if ( 'input' === e.type && e.originalEvent && e.originalEvent.isComposing ) {
                announceChange();

                return;
            }

            if ( typeof wpslMarkerLabel !== 'undefined' ) {
                const clamped = wpslMarkerLabel.clamp( this.value, wpslMarkerLabel.STUDIO_MAX_WEIGHT );

                if ( clamped !== this.value ) {
                    this.value = clamped;
                }
            }

            announceChange();
        } );

        /*
         * The color fields carry the .wpsl-color-field class, so wpsl-color-picker.js
         * attaches a WPSL_ColorPicker to each of them on document ready.
         */
        $.each( colorFields, function( field, $input ) {
            $input.on( 'change', function() {
                syncColorRow( field );
                announceChange();
            } );
        } );

        $document.on( 'wpsl-ms-editor-change', function( e, fields ) {
            refreshContrast( fields );
        } );

        // Delegated from the document: the shared tooltip code moves the popup
        // into a portal outside the editor, taking this link with it.
        $document.on( 'click keydown', '.wpsl-ms-contrast-autofix', onContrastAutoFix );

        // Update readouts live during a drag.
        $outline.add( $size ).add( $iconSize ).add( $radius ).on( 'input change', function() {
            syncReadouts();
            announceChange();
        } );

        $shadow.on( 'change', announceChange );

        // Filling the hole reveals the color to fill it with, so the row has
        // to follow the switch and not only the shape.
        $centerFill.on( 'change', function() {
            syncCenterControls();
            syncIconSeedToCenter();
            announceChange();
        } );

        $frame.add( $imageFill ).on( 'change', function() {
            syncImageMode();
            announceChange();
        } );

        // Use the shared admin toggle switch for all four checkboxes.
        if ( typeof wpslSharedFuncs !== 'undefined' && wpslSharedFuncs.createToggleSliders ) {
            wpslSharedFuncs.createToggleSliders( $shadow.add( $centerFill ).add( $frame ).add( $imageFill ) );
        }

        /*
         * The logo and image info tooltips.
         */
        if ( typeof wpslSharedFuncs !== 'undefined' && wpslSharedFuncs.bindInfoPopup ) {
            wpslSharedFuncs.bindInfoPopup( $( '.wpsl-ms-upload-info, .wpsl-ms-image-info' ) );
        }

        initTabs();

        // "+ New marker": reset the editor, switch to Design, focus the name.
        // Prompts if there are unsaved changes.
        $( '#wpsl-ms-new-marker' ).on( 'click', function() {
            if ( ! confirmDiscardIfDirty() ) {
                return;
            }

            resetEditor();
            showTab( 'design' );
            $name.trigger( 'focus' );
        } );

        // Trigger the browser's native "leave site?" prompt on unsaved changes.
        $( window ).on( 'beforeunload', onBeforeUnload );

        $save.on( 'click', onSaveClick );
        $duplicate.on( 'click', onDuplicateClick );
        $remove.on( 'click', onDeleteClick );
        $shortcodeOpen.on( 'click', openShortcodeDialog );
        $shortcodeCopy.on( 'click', onCopyShortcodeClick );

        if ( window.wpslSharedFuncs && wpslSharedFuncs.bindInfoPopup ) {
            wpslSharedFuncs.bindInfoPopup();
        }

        // Enforce the default shape's dot/text/center state explicitly.
        refreshDotTile();
        refreshTextTile();
        syncTextRow();
        syncCenterControls();

        syncReadouts();
        syncAllColorRows();

        syncIconColorLabel();
        syncActions();
    }

    /**
     * Read the editor fields into a flat marker object, in the format
     * Custom_Markers::sanitize_marker() expects.
     *
     * @since  3.0.0
     * @return object
     */
    function collectFields() {
        const logoId = parseInt( $logoId.val(), 10 ) || 0;
        const logo   = logoId ? ( wpslMarkerStudio.logos || {} )[ logoId ] : null;

        return {
            id:           currentEditId,
            name:         ( $name.val() || '' ).trim(),
            shape:        currentShape(),
            fill_color:   colorFields.fill_color.val(),
            stroke_color: colorFields.stroke_color.val(),
            stroke_width: parseInt( $outline.val(), 10 ) || 0,
            icon_name:    normalizeIconName( $icon.val() ),
            label_text:   $labelText.val() || '',
            icon_color:   colorFields.icon_color.val(),
            size:         parseInt( $size.val(), 10 ) || 38,
            icon_size:    parseInt( $iconSize.val(), 10 ) || 100,
            logo_id:      logoId,
            logo_src:     logo ? logo.url : '',
            image_w:      logo && logo.width ? logo.width : 0,
            image_h:      logo && logo.height ? logo.height : 0,
            center_color: colorFields.center_color.val(),
            shadow:       $shadow.prop( 'checked' ) ? 1 : 0,
            center_fill:  $centerFill.prop( 'checked' ) ? 1 : 0,
            image_frame:  $frame.prop( 'checked' ) ? 1 : 0,
            image_fill:   $imageFill.prop( 'checked' ) ? 1 : 0,
            image_radius: parseInt( $radius.val(), 10 ) || 0
        };
    }

    /**
     * Load a marker's values into the editor and take its id.
     *
     * @since  3.0.0
     * @param  object marker A saved marker, or a plain object of the same shape.
     * @return void
     */
    function applyFields( marker ) {
        currentEditId = marker.id || '';
        seededIcon    = null;
        parkedGlyph   = '';

        $name.val( marker.name || '' );
        clearMissingName();
        setShape( marker.shape || 'classic_pin' );
        $labelText.val( marker.label_text || '' );
        setIcon( marker.icon_name || '' );
        refreshDotTile();
        refreshTextTile();

        applyLogo( undefinedTo( marker.logo_id, 0 ) );

        setColorField( colorFields.fill_color, marker.fill_color || defaults.fill_color );
        setColorField( colorFields.icon_color, marker.icon_color || defaults.icon_color );
        setColorField( colorFields.stroke_color, marker.stroke_color || defaults.stroke_color );
        setColorField( colorFields.center_color, marker.center_color || defaults.center_color );

        $outline.val( undefinedTo( marker.stroke_width, defaults.stroke_width ) );
        $size.val( undefinedTo( marker.size, defaults.size ) );
        $iconSize.val( undefinedTo( marker.icon_size, defaults.icon_size ) );

        $radius.val( undefinedTo( marker.image_radius, 0 ) );

        const shadowOn = undefinedTo( marker.shadow, false ) ? true : false;
        $shadow.prop( 'checked', shadowOn );

        const centerFillOn = undefinedTo( marker.center_fill, false ) ? true : false;
        $centerFill.prop( 'checked', centerFillOn );

        const frameOn = undefinedTo( marker.image_frame, false ) ? true : false;
        $frame.prop( 'checked', frameOn );

        // image_fill falls back to true: markers saved before the
        // background could be dropped were drawn with one.
        const imageFillOn = undefinedTo( marker.image_fill, true ) ? true : false;
        $imageFill.prop( 'checked', imageFillOn );

        $shadow.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', shadowOn );
        $centerFill.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', centerFillOn );
        $frame.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', frameOn );
        $imageFill.next( '.wpsl-toggler-slider' ).attr( 'aria-checked', imageFillOn );

        syncReadouts();
        syncAllColorRows();
        syncCenterControls();
        syncActions();
        syncImageMode();

        setStatus( '', false );

        announceChange( true );

        takeSnapshot();
    }

    /**
     * A value, or the fallback when it was never provided.
     *
     * @since  3.0.0
     * @param  *      value
     * @param  *      fallback
     * @return *
     */
    function undefinedTo( value, fallback ) {
        return ( typeof value === 'undefined' || null === value ) ? fallback : value;
    }

    /**
     * Return the editor to its blank "create a marker" state.
     *
     * @since  3.0.0
     * @return void
     */
    function resetEditor() {
        clearIconSearch();

        applyFields( {
            id:           '',
            name:         '',
            shape:        defaults.shape,
            fill_color:   defaults.fill_color,
            stroke_color: defaults.stroke_color,
            stroke_width: defaults.stroke_width,
            icon_name:    defaults.icon_name,
            label_text:   '',
            icon_color:   defaults.icon_color,
            size:         defaults.size,
            icon_size:    defaults.icon_size,
            shadow:       defaults.shadow,
            center_fill:  defaults.center_fill,
            center_color: defaults.center_color
        } );
    }

    /**
     * Load a saved marker into the editor by id.
     *
     * @since  3.0.0
     * @param  string id
     * @return boolean Whether the id was known.
     */
    function loadMarker( id ) {
        const marker = ( wpslMarkerStudio.markers || {} )[ id ];

        if ( ! marker ) {
            return false;
        }

        // Prompt before discarding unsaved changes.
        if ( ! confirmDiscardIfDirty() ) {
            return false;
        }

        clearIconSearch();
        applyFields( marker );

        return true;
    }

    /**
     * Drop the icon search so the grid starts from the whole set again.
     *
     * @since  3.0.0
     * @return void
     */
    function clearIconSearch() {
        $iconSearch.val( '' ).trigger( 'input' );
        $iconCategory.val( '' );
        filterIcons();
    }

    /**
     * The shape the toggle group currently has pressed.
     *
     * @since  3.0.0
     * @return string
     */
    function currentShape() {
        const $pressed = $shapeToggle.filter( '[aria-pressed="true"]' ).first();

        return $pressed.length ? ( $pressed.attr( 'data-shape' ) || 'classic_pin' ) : 'classic_pin';
    }

    /**
     * Press one shape toggle and release the rest.
     *
     * @since  3.0.0
     * @param  string shape
     * @return void
     */
    function setShape( shape ) {
        let $target = $shapeToggle.filter( function() {
            return $( this ).attr( 'data-shape' ) === shape;
        } );

        if ( ! $target.length ) {
            $target = $shapeToggle.first();
        }

        $shapeToggle.attr( 'aria-pressed', 'false' );
        $target.attr( 'aria-pressed', 'true' );
    }

    /**
     * Select the shape that was clicked.
     *
     * @since  3.0.0
     * @return void
     */
    function onShapeClick() {
        const previous = currentShape();
        const shape    = $( this ).attr( 'data-shape' ) || 'classic_pin';

        setShape( shape );

        refreshDotTile();
        refreshTextTile();
        restoreParkedGlyph();
        syncCenterControls();

        // Before announceChange() so the preview draws the new color.
        // Restore first: leaving a holed shape hands back the color its
        // seed replaced, then the next holed shape seeds afresh.
        if ( shape !== previous ) {
            restoreIconColorForShape( previous, shape );
            seedIconColorForShape( shape );
        }

        syncImageMode();

        announceChange();
    }

    /**
     * Select the icon tile that was clicked.
     *
     * @since  3.0.0
     * @return void
     */
    function onIconTileClick() {
        const logoId = parseInt( $( this ).attr( 'data-logo-id' ), 10 ) || 0;

        parkedGlyph = '';

        if ( logoId ) {
            setIcon( '' );
            applyLogo( logoId );
            announceChange();

            return;
        }

        setIcon( $( this ).attr( 'data-icon' ) || '' );
        clearLogo();
        announceChange();

        // The Text tile is a prompt for the text, so put the caret there.
        if ( 'text' === $icon.val() ) {
            $labelText.trigger( 'focus' );
        }
    }

    /**
     * Store an icon name and bring the grid in line with it.
     *
     * @since  3.0.0
     * @param  string iconName The icon to select, '' for none.
     * @return void
     */
    function setIcon( iconName ) {
        const name = normalizeIconName( iconName );

        $icon.val( name );
        markSelectedTile( name );
        syncIconColorLabel();
        syncTextRow();
    }

    /**
     * Label the icon color row and size slider after what they act on --
     * the dot centre and the text are not icons, and the size slider is
     * the one control that scales the text.
     *
     * @since  3.0.0
     * @return void
     */
    function syncIconColorLabel() {
        const icon = $icon.val();
        let   label = l10n.iconLabel;

        if ( 'dot' === icon ) {
            label = l10n.dotLabel;
        } else if ( 'text' === icon ) {
            label = l10n.textLabel;
        }

        $iconColorRow.find( '.wpsl-ms-color-label' ).text( label );
        $iconSizeLabel.text( 'text' === icon ? l10n.textSizeLabel : l10n.iconSizeLabel );

        $glyphLabel.text( label );
        $glyphGroup.prop( 'hidden', '' === icon );
    }

    /**
     * Show the Text glyph's field while the Text tile is pressed.
     *
     * @since  3.0.0
     * @return void
     */
    function syncTextRow() {
        $labelRow.prop( 'hidden', 'text' !== $icon.val() );
    }

    /**
     * Whether text is offered on a shape.
     *
     * @since  3.0.0
     * @param  string shape
     * @return boolean
     */
    function textAllowed( shape ) {
        const allowed = wpslMarkerStudio.labelShapes || [];

        return allowed.indexOf( shape ) !== -1;
    }

    /**
     * refreshDotTile() for the Text tile.
     *
     * @since  3.0.0
     * @return void
     */
    function refreshTextTile() {
        const allowed = textAllowed( currentShape() );

        if ( ! allowed && 'text' === $icon.val() ) {
            parkedGlyph = 'text';
            setIcon( '' );
        }

        $iconTiles.filter( '.wpsl-ms-icon-tile-text' ).toggleClass( 'wpsl-ms-icon-tile-hidden', ! allowed );
    }

    /**
     * Whether the dot centre is offered on a shape.
     *
     * @since  3.0.0
     * @param  string shape
     * @return boolean
     */
    function dotAllowed( shape ) {
        const allowed = ( wpslMarkerStudio.centerShapes && wpslMarkerStudio.centerShapes.dot ) ? wpslMarkerStudio.centerShapes.dot : [];

        return allowed.indexOf( shape ) !== -1;
    }

    /**
     * Show the dot tile for shapes that support it, hide it otherwise.
     *
     * @since  3.0.0
     * @return void
     */
    function refreshDotTile() {
        const allowed = dotAllowed( currentShape() );

        if ( ! allowed && 'dot' === $icon.val() ) {
            parkedGlyph = 'dot';
            setIcon( '' );
        }

        $iconTiles.filter( '.wpsl-ms-icon-tile-dot' ).toggleClass( 'wpsl-ms-icon-tile-hidden', ! allowed );
    }

    /**
     * Point the grid's selected state and its single tab stop at one tile.
     *
     * @since  3.0.0
     * @param  string iconName The selected icon, '' for the "No icon" tile.
     * @return void
     */
    function markSelectedTile( iconName ) {
        const $selected = tileFor( iconName );

        $iconTiles.attr( 'aria-pressed', 'false' ).attr( 'tabindex', '-1' );

        // An unknown icon leaves no tile pressed and shows a status notice,
        // rather than silently falling back to "No icon".
        if ( ! $selected.length ) {
            $iconTiles.first().attr( 'tabindex', '0' );
            setIconStatus( iconName ? l10n.unknownIcon : '' );

            return;
        }

        $selected.attr( 'aria-pressed', 'true' ).attr( 'tabindex', '0' );
        setIconStatus( '' );
    }

    /**
     * The tile for an icon name.
     *
     * @since  3.0.0
     * @param  string iconName
     * @return jQuery
     */
    function tileFor( iconName ) {
        const name = normalizeIconName( iconName );

        return $iconTiles.filter( function() {
            return ! $( this ).attr( 'data-logo-id' ) && ( $( this ).attr( 'data-icon' ) || '' ) === name;
        } );
    }

    /**
     * The tile for a reusable-logo attachment id, or an empty set when the
     * logo has no tile ( e.g. its attachment was deleted ).
     *
     * @since  3.0.0
     * @param  {number|string} logoId
     * @return jQuery
     */
    function logoTileFor( logoId ) {
        const id = String( parseInt( logoId, 10 ) || 0 );

        return $iconTiles.filter( function() {
            return ( $( this ).attr( 'data-logo-id' ) || '' ) === id;
        } );
    }

    /**
     * markSelectedTile() for a logo tile.
     *
     * @since  3.0.0
     * @param  {number|string} logoId
     * @return void
     */
    function markSelectedLogoTile( logoId ) {
        const $selected = logoTileFor( logoId );

        $iconTiles.attr( 'aria-pressed', 'false' ).attr( 'tabindex', '-1' );

        if ( ! $selected.length ) {
            $iconTiles.first().attr( 'tabindex', '0' );

            return;
        }

        $selected.attr( 'aria-pressed', 'true' ).attr( 'tabindex', '0' );
        setIconStatus( '' );
    }

    /**
     * Re-collect the tile set after a tile is added or removed at runtime.
     *
     * @since  3.0.0
     * @return void
     */
    function refreshTiles() {
        $iconTiles     = $iconGrid.find( '.wpsl-ms-icon-tile' );
        tileIndexCache = null;
    }

    /**
     * Append a Custom-category tile for a just-picked logo.
     *
     * @since  3.0.0
     * @param  {number} logoId
     * @param  {object} logo { url, thumb, filename }
     * @return void
     */
    function appendLogoTile( logoId, logo ) {
        if ( logoTileFor( logoId ).length ) {
            return;
        }

        const name = escapeAttr( logo.filename || '' );
        const id   = escapeAttr( logoId );

        $iconGrid.append(
            '<span class="wpsl-ms-logo-tile-wrap">'
            + '<button type="button" class="wpsl-ms-icon-tile wpsl-ms-icon-tile-logo" data-logo-id="' + id + '" data-filename="' + name + '" aria-pressed="false" tabindex="-1" title="' + name + '" aria-label="' + name + '">'
            + '<img src="' + escapeAttr( logo.thumb || logo.url ) + '" alt="" loading="lazy" decoding="async">'
            + '</button>'
            + '<button type="button" class="wpsl-ms-logo-tile-remove" data-logo-id="' + id + '" title="' + escapeAttr( l10n.removeLogoTile ) + '" aria-label="' + escapeAttr( l10n.removeLogoTile ) + '">&times;</button>'
            + '</span>'
        );

        refreshTiles();
        filterIcons();
    }

    /**
     * Untag a logo on server confirmation -- no modal, since untag_logo()
     * refuses when a marker still uses it. Undo comes via the snackbar.
     *
     * @since  3.0.0
     * @param  Event e
     * @return void
     */
    function onLogoTileRemove( e ) {
        e.stopPropagation();

        const $btn   = $( this );
        const logoId = parseInt( $btn.attr( 'data-logo-id' ), 10 ) || 0;
        const $wrap  = $btn.closest( '.wpsl-ms-logo-tile-wrap' );

        const $after = $wrap.prev();

        $btn.prop( 'disabled', true );

        $.post( wpslMarkerStudio.ajaxurl, {
            action:  'wpsl_marker_manager_untag_logo',
            nonce:   wpslMarkerStudio.nonce,
            logo_id: logoId
        } ).done( function( response ) {
            if ( ! response || ! response.success ) {
                $btn.prop( 'disabled', false );

                const refusal = getErrorMessage( response, l10n.logoRemoveFailed );

                // "Logo in use" gets a dialog with a link to the Library tab;
                // other errors are dead ends and stay on the status line.
                if ( response && response.data && 'logo_in_use' === response.data.code ) {
                    showLogoInUse( refusal, response.data.markers );
                } else {
                    setStatus( refusal, true );
                }

                return;
            }

            $wrap.detach();
            refreshTiles();
            filterIcons();

            snackbar( l10n.logoTileRemoved, {
                label: l10n.undo,
                onClick: function() {
                    restoreLogoTile( logoId, $wrap, $after );
                }
            } );
        } ).fail( function() {
            $btn.prop( 'disabled', false );
            setStatus( l10n.logoRemoveFailed, true );
        } );
    }

    /**
     * Show which markers use a logo so the user can edit them on the Library tab.
     *
     * @since  3.0.0
     * @param  string   message The server's refusal.
     * @param  object[] markers { id, name } for each marker using the logo.
     * @return void
     */
    function showLogoInUse( message, markers ) {
        const $dialog = $( '#wpsl-ms-logo-in-use' );

        markers = markers || [];

        // Fallback: list the markers on the status line if the dialog can't open.
        if ( ! $dialog.length || ! $.fn.dialog ) {
            setStatus( message + ' ' + markerNames( markers ).join( ', ' ), true );

            return;
        }

        // Remove .wpsl-hide so jQuery UI can size the dialog (see confirmDelete()).
        $dialog.removeClass( 'wpsl-hide' );

        $dialog.find( '.wpsl-ms-logo-in-use-message' ).text( message );

        renderLogoInUseList( markers );

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

        // autoOpen is a create-time option only -- see confirmDelete().
        $dialog.dialog( 'open' );

        $( '#wpsl-ms-logo-in-use-close, .ui-widget-overlay' ).off( 'click.wpslStudioLogo' ).on( 'click.wpslStudioLogo', function() {
            $dialog.dialog( 'close' );

            return false;
        } );

        $( '#wpsl-ms-logo-in-use-library' ).off( 'click.wpslStudioLogo' ).on( 'click.wpslStudioLogo', function() {
            $dialog.dialog( 'close' );

            if ( wpslMarkerStudio.tabs ) {
                wpslMarkerStudio.tabs.show( 'library' );
            }

            return false;
        } );
    }

    /**
     * Draw one tile per marker holding the logo, 
     * using the library pane's artwork.
     *
     * @since  3.0.0
     * @param  object[] markers { id, name } records from the server.
     * @return void
     */
    function renderLogoInUseList( markers ) {
        const $list   = $( '#wpsl-ms-logo-in-use-list' ).empty();
        const library = wpslMarkerStudio.library;

        $.each( markers, function( index, marker ) {
            const name = marker.name || l10n.untitled;
            const art  = ( library && 'function' === typeof library.artFor ) ? library.artFor( marker.id ) : null;

            const $tile = $( '<li>' ).addClass( 'wpsl-ms-logo-in-use-row' ).attr( 'title', name );

            if ( art ) {
                $tile.append(
                    $( '<img>' )
                        .attr( 'src', art.src )
                        .attr( 'width', art.width )
                        .attr( 'height', art.height )
                        .attr( 'alt', '' )
                        .attr( 'decoding', 'async' )
                );
            }

            $tile.append( $( '<span>' ).addClass( 'wpsl-ms-logo-in-use-name screen-reader-text' ).text( name ) );
            $list.append( $tile );
        } );
    }

    /**
     * The names out of the server's marker records, for the no-dialog fallback.
     *
     * @since  3.0.0
     * @param  object[] markers
     * @return string[]
     */
    function markerNames( markers ) {
        return $.map( markers, function( marker ) {
            return marker.name || l10n.untitled;
        } );
    }

    /**
     * Put an untagged logo back among the reusable ones.
     *
     * @since  3.0.0
     * @param  number logoId
     * @param  jQuery $wrap  The detached tile.
     * @param  jQuery $after The element it stood after, empty if it was first.
     * @return void
     */
    function restoreLogoTile( logoId, $wrap, $after ) {
        $.post( wpslMarkerStudio.ajaxurl, {
            action:  'wpsl_marker_manager_tag_logo',
            nonce:   wpslMarkerStudio.nonce,
            logo_id: logoId
        } ).done( function( response ) {
            if ( ! response || ! response.success ) {
                setStatus( getErrorMessage( response, l10n.logoRestoreFailed ), true );

                return;
            }

            if ( $after.length ) {
                $after.after( $wrap );
            } else {
                $iconGrid.append( $wrap );
            }

            $wrap.find( '.wpsl-ms-logo-tile-remove' ).prop( 'disabled', false );

            refreshTiles();
            filterIcons();
        } ).fail( function() {
            setStatus( l10n.logoRestoreFailed, true );
        } );
    }

    /**
     * Match the icon name to what sanitize_key() accepts server-side.
     *
     * @since  3.0.0
     * @param  string value
     * @return string
     */
    function normalizeIconName( value ) {
        return String( value || '' ).trim().toLowerCase().replace( /[^a-z0-9_-]/g, '' );
    }

    /**
     * Show or hide one tile, writing only when the state actually changes.
     *
     * @since  3.0.0
     * @param  {HTMLElement} el     The tile ( or its wrapper ).
     * @param  {boolean}     hidden Whether it should be hidden.
     * @return void
     */
    function setTileHidden( el, hidden ) {
        if ( el.classList.contains( 'wpsl-ms-icon-tile-hidden' ) !== hidden ) {
            el.classList.toggle( 'wpsl-ms-icon-tile-hidden', hidden );
        }
    }

    /**
     * The per-tile data filterIcons() works on, cached.
     *
     * @since  3.0.0
     * @return {Array} One entry per tile, in grid order.
     */
    function tileIndex() {
        if ( tileIndexCache ) {
            return tileIndexCache;
        }

        tileIndexCache = $iconTiles.get().map( function( el ) {
            const logoId = el.getAttribute( 'data-logo-id' );

            if ( logoId ) {
                return {
                    el:          el,
                    isLogo:      true,
                    filenameKey: searchKey( el.getAttribute( 'data-filename' ) ),
                    wrapEl:      ( el.parentElement && el.parentElement.classList.contains( 'wpsl-ms-logo-tile-wrap' ) ) ? el.parentElement : null
                };
            }

            const name   = el.getAttribute( 'data-icon' ) || '';
            const isDot  = el.classList.contains( 'wpsl-ms-icon-tile-dot' );
            const isText = el.classList.contains( 'wpsl-ms-icon-tile-text' );

            return {
                el:      el,
                isLogo:  false,
                name:    name,
                nameKey: searchKey( name ),
                isDot:   isDot,
                exempt:  ( '' === name ) || isDot || isText
            };
        } );

        return tileIndexCache;
    }

    /**
     * Filter the grid down to the icons matching the search box.
     *
     * @since  3.0.0
     * @return void
     */
    function filterIcons() {
        const term      = searchKey( $iconSearch.val() );
        const category  = $iconCategory.val() || '';
        let matches     = 0;
        let logoMatches = 0;

        const groupOf   = iconGroupIndex();
        const tagsOf    = iconTagsIndex();
        const tiles     = tileIndex();
        const dotIsOpen = dotAllowed( currentShape() );

        for ( let i = 0; i < tiles.length; i++ ) {
            const tile = tiles[ i ];

            if ( tile.isLogo ) {
                const logoHit = ( '' === term || tile.filenameKey.indexOf( term ) !== -1 )
                    && ( '' === category || 'custom' === category );

                setTileHidden( tile.el, ! logoHit );

                if ( tile.wrapEl ) {
                    setTileHidden( tile.wrapEl, ! logoHit );
                }

                if ( logoHit ) {
                    logoMatches++;
                }

                continue;
            }

            const name   = tile.name;
            const exempt = tile.exempt;

            // Hit if the term matches the name, tags, or category words.
            const nameHit     = exempt || ( '' === term ) || ( tile.nameKey.indexOf( term ) !== -1 );
            const tagHit      = exempt || ( '' === term ) || tagMatches( tagsOf[ name ], term );
            const groupHit    = exempt || ( '' === term ) || groupMatches( groupWords[ name ], term );
            const categoryHit = exempt || ( '' === category ) || ( groupOf[ name ] === category );

            let hit = ( nameHit || tagHit || groupHit ) && categoryHit;

            // A search must not un-hide the dot on a shape that lacks it.
            if ( tile.isDot ) {
                hit = hit && dotIsOpen;
            }

            setTileHidden( tile.el, ! hit );

            if ( hit && ! exempt ) {
                matches++;
            }
        }

        $iconCount.text( matches + ' / ' + iconTotal );

        const anyMatches = matches || logoMatches;

        $iconEmpty.text( anyMatches ? '' : l10n.noIconsFound ).prop( 'hidden', !! anyMatches );

        // Move the tab stop to a visible tile if the current one was hidden.
        if ( ! visibleTiles().filter( '[tabindex="0"]' ).length ) {
            $iconTiles.attr( 'tabindex', '-1' );
            visibleTiles().first().attr( 'tabindex', '0' );
        }
    }

    /**
     * Build a slug → group-label index from the localized iconGroups map.
     *
     * @since  3.0.0
     * @return object
     */
    let _iconGroupIndex   = null;
    const groupWords      = {};
    
    function iconGroupIndex() {
        if ( null === _iconGroupIndex ) {
            _iconGroupIndex = {};
            const groups  = wpslMarkerStudio.iconGroups || {};
            const aliases = wpslMarkerStudio.iconGroupAliases || {};

            $.each( groups, function( label, icons ) {
                // Each label word is indexed ( "Health & care" -> health, care )
                // so a partial category name still matches.
                const words = $.map(
                    String( label ).split( /[^A-Za-z0-9]+/ ).concat( aliases[ label ] || [] ),
                    function( word ) {
                        const key = searchKey( word );

                        return key ? key : null;
                    }
                );

                $.each( icons, function( i, slug ) {
                    _iconGroupIndex[ slug ] = label;
                    groupWords[ slug ]      = words;
                } );
            } );
        }

        return _iconGroupIndex;
    }

    /**
     * Whether a search term matches any of an icon's category words.
     *
     * @since  3.0.0
     * @param  Array  words The icon's category words, already search-keyed.
     * @param  string term  The search term, already search-keyed.
     * @return bool
     */
    function groupMatches( words, term ) {
        if ( ! words || ! words.length ) {
            return false;
        }

        for ( let i = 0; i < words.length; i++ ) {
            if ( 0 === words[ i ].indexOf( term ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a slug → tags-string index from the localized iconTags map.
     *
     * @since  3.0.0
     * @return object
     */
    let _iconTagsIndex = null;
    function iconTagsIndex() {
        if ( null === _iconTagsIndex ) {
            _iconTagsIndex = {};
            const tags = wpslMarkerStudio.iconTags || {};

            $.each( tags, function( slug, list ) {
                _iconTagsIndex[ slug ] = searchKey( ( list || [] ).join( ' ' ) );
            } );
        }

        return _iconTagsIndex;
    }

    /**
     * Test a tag blob against a search term.
     *
     * @since  3.0.0
     * @param  string tagsBlob
     * @param  string term
     * @return boolean
     */
    function tagMatches( tagsBlob, term ) {
        return !! tagsBlob && tagsBlob.indexOf( term ) !== -1;
    }

    /**
     * Reduce a value to the form the icon search compares on.
     *
     * @since  3.0.0
     * @param  string value
     * @return string
     */
    function searchKey( value ) {
        return String( value || '' ).toLowerCase().replace( /[^a-z0-9]/g, '' );
    }

    /**
     * The tiles the current search leaves on screen.
     *
     * @since  3.0.0
     * @return jQuery
     */
    function visibleTiles() {
        return $iconTiles.not( '.wpsl-ms-icon-tile-hidden' );
    }

    /**
     * Arrow-key navigation across the icon grid.
     *
     * Moves through visible tiles in DOM order; Up/Down step a row,
     * Home/End jump to the ends. Enter/Space activate via <button>.
     *
     * @since  3.0.0
     * @param  Event e
     * @return void
     */
    function onIconGridKeydown( e ) {
        const $tiles = visibleTiles();
        const index  = $tiles.index( this );
        let target;

        if ( index === -1 ) {
            return;
        }

        switch ( e.key ) {
            case 'ArrowRight':
                target = index + 1;
                break;
            case 'ArrowLeft':
                target = index - 1;
                break;
            case 'ArrowDown':
                target = index + ICON_COLUMNS;
                break;
            case 'ArrowUp':
                target = index - ICON_COLUMNS;
                break;
            case 'Home':
                target = 0;
                break;
            case 'End':
                target = $tiles.length - 1;
                break;
            default:
                return;
        }

        if ( target < 0 || target >= $tiles.length ) {
            return;
        }

        e.preventDefault();

        $tiles.attr( 'tabindex', '-1' );
        $tiles.eq( target ).attr( 'tabindex', '0' ).trigger( 'focus' );
    }

    /**
     * Show or clear the message under the icon grid.
     *
     * @since  3.0.0
     * @param  string message
     * @return void
     */
    function setIconStatus( message ) {
        if ( ! message ) {
            if ( $iconEmpty.text() === l10n.unknownIcon ) {
                $iconEmpty.text( '' ).prop( 'hidden', true );
            }

            return;
        }

        $iconEmpty.text( message ).prop( 'hidden', false );
    }

    let mediaFrame = null;

    /**
     * Bring the logo badge, the Upload button and the Icon color row in line
     * with a logo_id.
     *
     * @since  3.0.0
     * @param  number logoId The attachment id, or 0 for no logo.
     * @return void
     */
    function applyLogo( logoId ) {
        logoId = parseInt( logoId, 10 ) || 0;

        $logoId.val( logoId );
        if ( ! logoId ) {
            showIconToolbar();
            return;
        }

        const logo = ( wpslMarkerStudio.logos || {} )[ logoId ];
        if ( ! logo ) {
            showLogoMissing();
            return;
        }

        $logoThumb.attr( 'src', logo.url );
        $logoName.text( logo.filename );

        // The grid stays interactive so an icon can be picked to swap the
        // logo back out. The badge shows which logo is in use.
        $iconToolbar.prop( 'hidden', false );
        $uploadLogo.prop( 'hidden', true );
        $logoChip.prop( 'hidden', false );
        $logoMissing.prop( 'hidden', true );

        /*
         * Hide the Icon color row rather than greying it out: with a logo,
         * get_svg_marker() draws no icon, so the field is dead.
         */
        $iconColorRow.prop( 'hidden', true );

        // Press the matching logo tile so the grid reflects the selection.
        markSelectedLogoTile( logoId );
    }

    /**
     * Clear the logo and restore the icon toolbar and grid to their normal
     * state. Called by the "Remove" button and when an icon tile is selected.
     *
     * @since  3.0.0
     * @return void
     */
    function clearLogo() {
        $logoId.val( 0 );
        showIconToolbar();

        setStatus( '', false );
    }

    /**
     * Show the icon toolbar ( search + category dropdown ) and the Upload logo
     * button, hide the badge and the missing-notice, and re-enable the Icon
     * Color.
     *
     * @since  3.0.0
     * @return void
     */
    function showIconToolbar() {
        $iconToolbar.prop( 'hidden', false );
        $uploadLogo.prop( 'hidden', false );
        $logoChip.prop( 'hidden', true );
        $logoMissing.prop( 'hidden', true );
        $iconColorRow.prop( 'hidden', false );
    }

    /**
     * Show the "logo no longer in your Media Library" notice in place of the
     * badge. The grid stays interactive so the user can pick a replacement.
     *
     * @since  3.0.0
     * @return void
     */
    function showLogoMissing() {
        $iconToolbar.prop( 'hidden', false );
        $uploadLogo.prop( 'hidden', true );
        $logoChip.prop( 'hidden', true );
        $logoMissing.prop( 'hidden', false );
        $iconColorRow.prop( 'hidden', false );
    }

    /**
     * Open the wp.media modal to select a logo image.
     *
     * @since  3.0.0
     * @return void
     */
    function openMediaModal() {
        if ( ! wp.media ) {
            return;
        }

        if ( ! mediaFrame ) {
            mediaFrame = wp.media( {
                title:    l10n.uploadLogo,
                button:   { text: l10n.uploadLogo },
                library:  { type: 'image' },
                multiple: false
            } );

            mediaFrame.on( 'select', function() {
                const attachment = mediaFrame.state().get( 'selection' ).first();

                if ( ! attachment ) {
                    return;
                }

                // Clear any previous refusal before judging this file.
                setStatus( '', false );

                // Refuse now; the save would silently zero logo_id instead.
                const refusal = logoRefusal( attachment );
                if ( refusal ) {
                    setStatus(
                        'svg' === refusal ? l10n.logoNoSvg : format( l10n.logoTooLarge, wpslMarkerStudio.logoMaxHuman || '' ),
                        true
                    );

                    return;
                }

                const id   = attachment.get( 'id' );
                const url   = attachment.get( 'url' );
                const fname = attachment.get( 'filename' );

                const sizes = attachment.get( 'sizes' );

                wpslMarkerStudio.logos = wpslMarkerStudio.logos || {};
                wpslMarkerStudio.logos[ id ] = {
                    url:      url,
                    filename: fname,
                    thumb:    ( sizes && sizes.thumbnail ) ? sizes.thumbnail.url : url,
                    width:    attachment.get( 'width' ) || 0,
                    height:   attachment.get( 'height' ) || 0
                };

                $.post( wpslMarkerStudio.ajaxurl, {
                    action: 'wpsl_marker_manager_tag_logo',
                    nonce: wpslMarkerStudio.nonce,
                    logo_id: id
                } ).done( function( response ) {
                    if ( response && response.success ) {
                        appendLogoTile( id, wpslMarkerStudio.logos[ id ] );

                        if ( ( parseInt( $logoId.val(), 10 ) || 0 ) === id ) {
                            markSelectedLogoTile( id );
                        }
                    }
                } ).fail( function() {
                    console.warn( 'WPSL: failed to tag the logo for reuse.' );
                } );

                setIcon( '' );

                applyLogo( id );
                syncImageMode();
                announceChange();
            } );
        }

        mediaFrame.open();
    }

    /**
     * Write a color into one of the color fields and resync its picker.
     *
     * @since  3.0.0
     * @param  jQuery $field The .wpsl-color-field input.
     * @param  string hex    The color to show.
     * @return void
     */
    function setColorField( $field, hex ) {
        const picker = $field.data( 'wpsl-color-picker' );

        $field.val( hex );

        if ( ! picker ) {
            return;
        }

        const hsl = picker.hexToHsl( hex );

        picker.currentHue   = hsl.h;
        picker.currentSat   = hsl.s;
        picker.currentLight = hsl.l;

        picker.updateUI( true );
    }

    /**
     * Whether a shape cuts a transparent hole through its middle.
     *
     * @since  3.0.0
     * @param  string shape
     * @return boolean
     */
    function holeShape( shape ) {
        const shapes = wpslMarkerStudio.holeShapes || {};

        return Object.prototype.hasOwnProperty.call( shapes, shape );
    }

    /**
     * Show/hide the centre controls based on whether the shape has a hole,
     * and reveal the centre color only when the fill is on.
     *
     * @since  3.0.0
     * @return void
     */
    function syncCenterControls() {
        const hasHole = holeShape( currentShape() );

        $centerFillRow.prop( 'hidden', ! hasHole );
        $centerColorRow.prop( 'hidden', ! hasHole || ! $centerFill.prop( 'checked' ) );
    }

    /**
     * Whether the editor is in image mode.
     *
     * @since  3.0.0
     * @return boolean
     */
    function isImageMode() {
        return 'image' === currentShape();
    }

    /**
     * Keep the wpsl-ms-image-mode and wpsl-ms-frame-on 
     * classes and the image thumb truthful.
     *
     * @since  3.0.0
     * @return void
     */
    function syncImageMode() {
        const imageMode = isImageMode();

        $studioWrap.toggleClass( 'wpsl-ms-image-mode', imageMode );

        const framed = imageMode && $frame.prop( 'checked' );

        $studioWrap.toggleClass( 'wpsl-ms-frame-on', framed );

        // Only a framed image has a background, only a filled one a color.
        // Outside image mode this row is the shape's Fill.
        $imageFillRow.prop( 'hidden', ! framed );
        $fillColorRow.prop( 'hidden', imageMode && ! $imageFill.prop( 'checked' ) );

        syncFillColorLabel();
        syncReadouts();

        const logoId = parseInt( $logoId.val(), 10 ) || 0;
        const logo   = logoId ? ( wpslMarkerStudio.logos || {} )[ logoId ] : null;

        $imageThumb.prop( 'hidden', ! logo );

        if ( logo ) {
            $imageThumb.attr( 'src', logo.thumb || logo.url );
        }
    }

    /**
     * Label the fill color row after what it fills: "Fill" on a shape,
     * "Background" on a framed image. See syncIconColorLabel().
     *
     * @since  3.0.0
     * @return void
     */
    function syncFillColorLabel() {
        $fillColorRow.find( '.wpsl-ms-color-label' ).text( isImageMode() ? l10n.bgLabel : l10n.fillLabel );
    }

    /**
     * Seed the icon color to match the fill when switching to a holed shape.
     *
     * @since  3.0.0
     * @param  string shape The shape just selected.
     * @return void
     */
    function seedIconColorForShape( shape ) {
        // A filled centre is a background of its own, so the icon keeps its color.
        if ( hasLogo() || ! holeShape( shape ) || $centerFill.prop( 'checked' ) || colorFields.icon_color.val() === colorFields.fill_color.val() ) {
            return;
        }

        seededIcon = {
            was:    colorFields.icon_color.val(),
            seeded: colorFields.fill_color.val()
        };

        setColorField( colorFields.icon_color, seededIcon.seeded );
        syncColorRow( 'icon_color' );
    }

    /**
     * Follow the Fill the center switch: the seed only exists because an
     * unfilled centre is light, so filling it hands the icon its color back,
     * and emptying it seeds again. A color picked by hand is left alone.
     *
     * @since  3.0.0
     * @return void
     */
    function syncIconSeedToCenter() {
        const shape = currentShape();

        if ( hasLogo() || ! holeShape( shape ) ) {
            return;
        }

        if ( ! $centerFill.prop( 'checked' ) ) {
            seedIconColorForShape( shape );

            return;
        }

        if ( seededIcon && colorFields.icon_color.val() === seededIcon.seeded ) {
            setColorField( colorFields.icon_color, seededIcon.was );
            syncColorRow( 'icon_color' );
        }

        seededIcon = null;
    }

    /**
     * Hand back the icon colour the open pin's seed replaced.
     *
     * @since  3.0.0
     * @param  string previous The shape being left.
     * @param  string shape    The shape being entered.
     * @return void
     */
    function restoreIconColorForShape( previous, shape ) {
        if ( ! seededIcon || ! holeShape( previous ) || holeShape( shape ) ) {
            return;
        }

        if ( colorFields.icon_color.val() === seededIcon.seeded ) {
            setColorField( colorFields.icon_color, seededIcon.was );
            syncColorRow( 'icon_color' );
        }

        seededIcon = null;
    }

    /**
     * Re-select a parked dot/text tile when this shape can hold it and
     * nothing else was chosen since. The label text was never cleared,
     * so "A" comes back with the tile.
     *
     * @since  3.0.0
     * @return void
     */
    function restoreParkedGlyph() {
        if ( '' === parkedGlyph || '' !== $icon.val() ) {
            return;
        }

        const shape   = currentShape();
        const allowed = 'text' === parkedGlyph ? textAllowed( shape ) : dotAllowed( shape );

        if ( ! allowed ) {
            return;
        }

        setIcon( parkedGlyph );
        parkedGlyph = '';
    }

    /**
     * Why a picked attachment cannot be a logo, or '' if it can.
     *
     * Pre-checks the server's rules ( no SVG, size cap ) so the refusal
     * lands in the picker. Returns a key, not a message, so a missing
     * l10n entry can't read as "this file is fine".
     *
     * @since  3.0.0
     * @param  object attachment A wp.media attachment model.
     * @return string 'svg', 'size', or '' when the file is fine.
     */
    function logoRefusal( attachment ) {
        const mime  = String( attachment.get( 'mime' ) || '' ).toLowerCase();
        const name  = String( attachment.get( 'filename' ) || '' ).toLowerCase();
        const bytes = parseInt( attachment.get( 'filesizeInBytes' ), 10 );
        const max   = parseInt( wpslMarkerStudio.logoMaxBytes, 10 ) || 0;

        if ( mime.indexOf( 'svg' ) > -1 || /\.svgz?$/.test( name ) ) {
            return 'svg';
        }

        if ( max && bytes && bytes > max ) {
            return 'size';
        }

        return '';
    }

    /**
     * Whether a logo is standing in for the icon.
     *
     * @since  3.0.0
     * @return boolean
     */
    function hasLogo() {
        return ( parseInt( $logoId.val(), 10 ) || 0 ) > 0;
    }

    /**
     * Bring one color row's hex readout in line with its field.
     *
     * @since  3.0.0
     * @param  string field The marker field the row writes, e.g. "fill_color".
     * @return void
     */
    function syncColorRow( field ) {
        const $input = colorFields[ field ];
        const id     = $input.attr( 'id' );
        const hex    = String( $input.val() || '' ).toLowerCase();

        $editor.find( '.wpsl-ms-hex[data-hex-for="' + id + '"]' ).text( hex );
    }

    /**
     * Sync all three color rows.
     *
     * @since  3.0.0
     * @return void
     */
    function syncAllColorRows() {
        $.each( colorFields, function( field ) {
            syncColorRow( field );
        } );
    }

    /**
     * Mirror the sliders' current values into their readouts.
     *
     * @since  3.0.0
     * @return void
     */
    function syncReadouts() {
        $outlineOut.text( isImageMode() ? $outline.val() : format( l10n.pixels, $outline.val() ) );
        $sizeOut.text( format( l10n.pixels, $size.val() ) );
        $iconSizeOut.text( format( l10n.percent, $iconSize.val() ) );
        $radiusOut.text( format( l10n.percent, $radius.val() ) );
    }

    /**
     * Show or hide the actions that only make sense for a saved marker.
     *
     * @since  3.0.0
     * @return void
     */
    function syncActions() {
        $remove.prop( 'hidden', '' === currentEditId );

        const shortcodeId = currentEditId ? 'custom:' + currentEditId : '';

        $shortcodeId.val( shortcodeId );
        $shortcodeExample.text( shortcodeId ? '[wpsl start_marker="' + shortcodeId + '"]' : '' );
        $shortcodeOpen.prop( 'hidden', '' === currentEditId );
    }

    /**
     * Open the shortcode-id dialog.
     *
     * @since  3.0.0
     * @return void
     */
    function openShortcodeDialog() {
        if ( ! $shortcodeDialog.length || ! $.fn.dialog ) {
            window.prompt( l10n.shortcodeIdLabel, $shortcodeId.val() );

            return;
        }

        // Remove .wpsl-hide so jQuery UI can size the dialog
        $shortcodeDialog.removeClass( 'wpsl-hide' );

        $shortcodeDialog.dialog( {
            resizable:   false,
            height:      'auto',
            width:       480,
            modal:       true,
            closeOnEscape: true,
            closeText:   '',
            dialogClass: 'wpsl-dialog wpsl-ms-shortcode-dialog-frame',
            classes:     { 'ui-dialog': 'wpsl-dialog wpsl-ms-shortcode-dialog-frame' },
            open:        function() {
                const $shortcodeDialog = $( '.ui-dialog.wpsl-ms-shortcode-dialog-frame' );

                if ( ! $shortcodeDialog.find( '.ui-dialog-titlebar .wpsl-close-cross' ).length ) {
                    $shortcodeDialog.find( '.ui-dialog-titlebar-close' ).remove();
                    $shortcodeDialog.find( '.ui-dialog-titlebar' ).append( $( '#wpsl-ms-shortcode-cross' ) );
                }

                $( '.ui-dialog-buttonpane' ).hide();

                $shortcodeDialog.find( '.wpsl-ms-copy-feedback' ).remove();
            },
            close: function() {
                $( document ).off( 'click.wpslStudioShortcode' );
            }
        } );

        $shortcodeDialog.dialog( 'open' );

        $( document ).off( 'click.wpslStudioShortcode' ).on( 'click.wpslStudioShortcode', '#wpsl-ms-shortcode-close, .wpsl-dialog-close, .ui-widget-overlay', function() {
            $shortcodeDialog.dialog( 'close' );

            return false;
        } );
    }

    /**
     * Put the marker's shortcode value on the clipboard.
     *
     * @since  3.0.0
     * @return void
     */
    function onCopyShortcodeClick() {
        const value = $shortcodeId.val();

        if ( ! value ) {
            return;
        }

        $shortcodeId.trigger( 'select' );

        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( value ).then( function() {
                setCopyFeedback( l10n.copied, false );
            } ).catch( function() {
                copyWithExecCommand();
            } );

            return;
        }

        copyWithExecCommand();
    }

    /**
     * The pre-clipboard-API copy, off the field's own selection.
     *
     * @since  3.0.0
     * @return void
     */
    function copyWithExecCommand() {
        let copied = false;

        try {
            copied = document.execCommand( 'copy' );
        } catch ( e ) {
            copied = false;
        }

        setCopyFeedback( copied ? l10n.copied : l10n.copyFailed, ! copied );
    }

    /**
     * Copy feedback inside the dialog -- the status line sits behind the
     * modal overlay.
     *
     * @since  3.0.0
     * @param  string  message
     * @param  boolean isError
     * @return void
     */
    function setCopyFeedback( message, isError ) {
        let $feedback = $shortcodeDialog.find( '.wpsl-ms-copy-feedback' );
        if ( ! $feedback.length ) {
            $feedback = $( '<p class="wpsl-ms-copy-feedback" role="status"></p>' ).insertAfter( $shortcodeDialog.find( '.wpsl-ms-shortcode-row' ) );
        }

        $feedback.text( message ).toggleClass( 'wpsl-ms-copy-feedback-error', !! isError );
    }

    /**
     * Save ( create or update ) the marker currently in the editor.
     *
     * @since  3.0.0
     * @return void
     */
    function onSaveClick() {
        save( collectFields() );
    }

    /**
     * Save a copy of the current fields as a new marker.
     *
     * @since  3.0.0
     * @return void
     */
    function onDuplicateClick() {
        duplicateMarker( currentEditId );
    }

    /**
     * Duplicate a marker by id.
     *
     * @since  3.0.0
     * @param  string id The marker id to duplicate. If empty, the editor's
     *                   current fields are used instead.
     * @return void
     */
    function duplicateMarker( id ) {
        let fields;

        if ( id && wpslMarkerStudio.markers && wpslMarkerStudio.markers[ id ] ) {
            fields = $.extend( {}, wpslMarkerStudio.markers[ id ] );
        } else {
            fields = collectFields();
        }

        if ( ! fields.name ) {
            flagMissingName();

            return;
        }

        fields.id   = '';
        fields.name = format( l10n.copyOf, fields.name );

        save( fields );
    }

    /**
     * POST a set of marker fields to the save endpoint.
     *
     * @since  3.0.0
     * @param  object fields The marker to save.
     * @return void
     */
    function save( fields ) {
        if ( ! fields.name ) {
            flagMissingName();

            return;
        }

        if ( 'image' === fields.shape && ! fields.logo_id ) {
            setStatus( l10n.pickImageFirst, true );

            return;
        }

        $save.prop( 'disabled', true );
        $duplicate.prop( 'disabled', true );

        $savePreloader.prop( 'hidden', false );
        setStatus( l10n.saving, false, true );

        $.ajax( {
            url: wpslMarkerStudio.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: $.extend( { action: 'wpsl_marker_manager_save', nonce: wpslMarkerStudio.nonce }, fields )
        } ).done( function( response ) {
            if ( response && response.success ) {
                onSaveSuccess( response.data );
            } else {
                setStatus( getErrorMessage( response, l10n.saveFailed ), true );
            }
        } ).fail( function() {
            setStatus( l10n.saveError, true );
        } ).always( function() {
            $save.prop( 'disabled', false );
            $duplicate.prop( 'disabled', false );
            $savePreloader.prop( 'hidden', true );
        } );
    }

    /**
     * Take over the saved marker after a successful save.
     *
     * @since  3.0.0
     * @param  object data { marker, data_uri, size } from the AJAX response.
     * @return void
     */
    function onSaveSuccess( data ) {
        const marker = data.marker;

        wpslMarkerStudio.markers = wpslMarkerStudio.markers || {};
        wpslMarkerStudio.markers[ marker.id ] = marker;

        applyFields( marker );

        setStatus( '', false );
        snackbar( l10n.saved );

        $document.trigger( 'wpsl-ms-marker-saved', [ marker, data.data_uri, data.size, data.picker_height ] );
    }

    /**
     * Delete the marker currently loaded, after confirmation.
     *
     * @since  3.0.0
     * @return void
     */
    function onDeleteClick() {
        deleteMarker( currentEditId );
    }

    /**
     * Delete a marker by id, after confirmation.
     *
     * @since  3.0.0
     * @param  string id The marker id to delete.
     * @return void
     */
    function deleteMarker( id ) {
        if ( ! id ) {
            return;
        }

        confirmDelete( id, function() {
            performDelete( id );
        } );
    }

    /**
     * Ask before deleting, through the admin's shared confirmation dialog.
     *
     * @since  3.0.0
     * @param  string   id        The marker id to delete.
     * @param  function onConfirm Runs once, only if the user confirms.
     * @return void
     */
    function confirmDelete( id, onConfirm ) {
        const $dialog = $( '#wpsl-delete-confirmation' );

        // No dialog or jQuery UI: fall back to the browser prompt.
        if ( ! $dialog.length || ! $.fn.dialog ) {
            if ( window.confirm( buildDeleteConfirm( id ) ) ) {
                onConfirm();
            }

            return;
        }

        // Remove .wpsl-hide so jQuery UI can size the dialog (see showLogoInUse()).
        $dialog.removeClass( 'wpsl-hide' );

        $dialog.find( 'p:first-child span' ).text( deleteSubject( id ) );

        renderDeleteNote( $dialog, id );

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

        $( '#wpsl-cancel-delete, .ui-widget-overlay' ).off( 'click.wpslStudio' ).on( 'click.wpslStudio', function() {
            $dialog.dialog( 'close' );

            return false;
        } );

        $( '#wpsl-confirm-delete' ).off( 'click.wpslStudio' ).on( 'click.wpslStudio', function() {
            $dialog.dialog( 'close' );

            onConfirm();

            return false;
        } );
    }

    /**
     * What the dialog calls the marker it is about to delete.
     *
     * @since  3.0.0
     * @param  string id The marker id.
     * @return string
     */
    function deleteSubject( id ) {
        const marker = ( wpslMarkerStudio.markers || {} )[ id ];

        return ( marker && marker.name ) ? '"' + marker.name + '"' : l10n.untitled;
    }

    /**
     * Put the delete warnings and "This can't be undone." into the dialog.
     *
     * @since  3.0.0
     * @param  object $dialog The dialog element.
     * @param  string id      The marker id.
     * @return void
     */
    function renderDeleteNote( $dialog, id ) {
        const lines = ( ( wpslMarkerStudio.deleteWarnings || {} )[ id ] || [] ).slice();

        $dialog.find( '.wpsl-ms-delete-note' ).remove();

        if ( l10n.cantUndone ) {
            lines.push( l10n.cantUndone );
        }

        if ( ! lines.length ) {
            return;
        }

        let $anchor = $dialog.find( 'p' ).first();

        // Warnings before "can't be undone", in order.
        $.each( lines, function( index, line ) {
            $anchor = $( '<p class="wpsl-ms-delete-note"></p>' ).text( line ).insertAfter( $anchor );
        } );
    }

    /**
     * Delete a marker, the asking already done.
     *
     * @since  3.0.0
     * @param  string id The marker id to delete.
     * @return void
     */
    function performDelete( id ) {
        $remove.prop( 'disabled', true );

        $.ajax( {
            url: wpslMarkerStudio.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wpsl_marker_manager_delete',
                nonce: wpslMarkerStudio.nonce,
                id: id
            }
        } ).done( function( response ) {
            if ( response && response.success ) {
                wpslMarkerStudio.markers = response.data.markers;

                if ( id === currentEditId ) {
                    resetEditor();
                }

                setStatus( '', false );
                snackbar( l10n.deleted );

                $document.trigger( 'wpsl-ms-marker-deleted', [ id ] );
            } else {
                setStatus( getErrorMessage( response, l10n.deleteFailed ), true );
            }
        } ).fail( function() {
            setStatus( l10n.deleteError, true );
        } ).always( syncActions );
    }

    /**
     * Build the delete confirmation message for a marker.
     *
     * @since  3.0.0
     * @param  string id The marker id.
     * @return string The full confirm message.
     */
    function buildDeleteConfirm( id ) {
        const marker = ( wpslMarkerStudio.markers || {} )[ id ];
        const name   = marker && marker.name ? marker.name : '';
        let lines    = [];

        if ( name ) {
            lines.push( '"' + name + '"' );
        } else {
            lines.push( l10n.confirmDelete );
        }

        const warnings = ( wpslMarkerStudio.deleteWarnings || {} )[ id ];
        if ( warnings && warnings.length ) {
            lines = lines.concat( warnings );
        }

        if ( l10n.cantUndone ) {
            lines.push( l10n.cantUndone );
        }

        return lines.join( '\n' );
    }

    /**
     * Refuse a save or duplicate with no name.
     *
     * @since  3.0.0
     * @return void
     */
    function flagMissingName() {
        $name.addClass( 'wpsl-error' ).attr( 'aria-invalid', 'true' ).trigger( 'focus' );
    }

    /**
     * Take the mark off again.
     *
     * @since  3.0.0
     * @return void
     */
    function clearMissingName() {
        $name.removeClass( 'wpsl-error' ).removeAttr( 'aria-invalid' );
    }

    /**
     * Confirm something that worked, in the bar the rest of the admin uses.
     *
     * @since  3.0.0
     * @param  string message
     * @return void
     */
    function snackbar( message, action ) {
        if ( window.wpslSharedFuncs && 'function' === typeof wpslSharedFuncs.snackbar ) {
            wpslSharedFuncs.snackbar( message, action );
        }
    }

    /**
     * Write a message into the status line above the action buttons.
     *
     * @since  3.0.0
     * @param  string  message
     * @param  boolean isError
     * @param  boolean quiet   Announce it without drawing it.
     * @return void
     */
    function setStatus( message, isError, quiet ) {
        $status.text( message )
            .toggleClass( 'wpsl-ms-status-error', !! isError )
            .toggleClass( 'screen-reader-text', !! quiet );
    }

    /**
     * Pull the error message out of a wp_send_json_error() response.
     *
     * @since  3.0.0
     * @param  object response
     * @param  string fallback
     * @return string
     */
    function getErrorMessage( response, fallback ) {
        return ( response && response.data && response.data.message ) || fallback;
    }

    /**
     * Fill the single %s / %d placeholder in a localized string.
     *
     * @since  3.0.0
     * @param  string template
     * @param  *      value
     * @return string
     */
    function format( template, value ) {
        return String( template || '' ).replace( /%[sd]/, function() {
            return value;
        } ).replace( /%%/g, '%' );
    }

    /**
     * Tell the rest of the page the draft changed.
     *
     * @since  3.0.0
     * @param  boolean immediate Skip the rAF coalescing.
     * @return void
     */
    function announceChange( immediate ) {
        if ( changeRafId ) {
            cancelAnimationFrame( changeRafId );
            changeRafId = null;
        }

        if ( true === immediate ) {
            $document.trigger( 'wpsl-ms-editor-change', [ collectFields() ] );

            return;
        }

        changeRafId = requestAnimationFrame( function() {
            changeRafId = null;
            $document.trigger( 'wpsl-ms-editor-change', [ collectFields() ] );
        } );
    }

    /**
     * Take a snapshot of the current fields for dirty comparison.
     *
     * @since  3.0.0
     * @return void
     */
    function takeSnapshot() {
        snapshot = collectFields();
    }

    /**
     * Whether any field differs from the snapshot.
     *
     * Opening a marker and touching nothing must never register as dirty.
     *
     * @since  3.0.0
     * @return boolean
     */
    function isDirty() {
        if ( ! snapshot ) {
            return false;
        }

        const current = collectFields();

        for ( let key in snapshot ) {
            if ( snapshot.hasOwnProperty( key ) && String( snapshot[ key ] ) !== String( current[ key ] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * If the editor is dirty, prompt before discarding changes.
     *
     * @since  3.0.0
     * @return boolean True if the action should proceed ( not dirty, or the
     *                 user confirmed the discard ).
     */
    function confirmDiscardIfDirty() {
        if ( ! isDirty() ) {
            return true;
        }

        const name = ( $name.val() || '' ).trim() || l10n.untitled || '';

        return window.confirm( format( l10n.confirmDiscard, name ) );
    }

    /**
     * The beforeunload guard.
     *
     * @since  3.0.0
     * @param  Event e
     * @return string|undefined
     */
    function onBeforeUnload( e ) {
        if ( isDirty() ) {
            e.preventDefault();

            return '';
        }
    }

    /**
     * Escape a value for safe interpolation inside an HTML/SVG attribute.
     *
     * @since  3.0.0
     * @param  *      value
     * @return string
     */
    function escapeAttr( value ) {
        return String( value )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#39;' );
    }

    /**
     * Tab bar: Design / Library.
     *
     * @since 3.0.0
     */
    function initTabs() {
        // Restore the active tab from the URL hash, defaulting to Design.
        const hash = window.location.hash.replace( '#', '' );
        const initial = ( 'library' === hash ) ? 'library' : 'design';
        showTab( initial, true );

        if ( 'upload-logo' === hash ) {
            showTab( 'design' );
            $uploadLogo.trigger( 'click' );
        }

        if ( 'image-marker' === hash ) {
            showTab( 'design' );
            setShape( 'image' );
            syncImageMode();

            announceChange();

            if ( ! ( parseInt( $logoId.val(), 10 ) || 0 ) ) {
                $imageUpload.trigger( 'click' );
            }
        }

        // Click a tab to switch.
        $tabbar.on( 'click', '[role="tab"]', function() {
            const id = $( this ).attr( 'aria-controls' ).replace( 'wpsl-ms-tab-', '' );
            showTab( id );
        } );

        // Arrow-key navigation between tabs ( WAI-ARIA tab pattern ).
        $tabbar.on( 'keydown', '[role="tab"]', function( e ) {
            const id  = $( this ).attr( 'aria-controls' ).replace( 'wpsl-ms-tab-', '' );
            const idx = tabIds.indexOf( id );
            let next;

            if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) {
                next = ( idx + 1 ) % tabIds.length;
            } else if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
                next = ( idx - 1 + tabIds.length ) % tabIds.length;
            } else if ( e.key === 'Home' ) {
                next = 0;
            } else if ( e.key === 'End' ) {
                next = tabIds.length - 1;
            } else {
                return;
            }

            e.preventDefault();
            showTab( tabIds[ next ] );
            tabs[ next ].trigger( 'focus' );
        } );

        // Keep the hash in step if the user edits it directly or uses history.
        $( window ).on( 'hashchange', function() {
            const h = window.location.hash.replace( '#', '' );
            if ( 'library' === h || 'design' === h ) {
                showTab( h, true );
            }
        } );
    }

    /**
     * Show one tab, hide the other, and update aria, tabindex, and the hash.
     *
     * @since  3.0.0
     * @param  string  id     The tab id: 'design' or 'library'.
     * @param  boolean silent If true, do not update the URL hash ( used when
     *                        restoring from the hash on load / hashchange ).
     * @return void
     */
    function showTab( id, silent ) {
        const target  = ( 'library' === id ) ? 'library' : 'design';
        const panelId = 'wpsl-ms-tab-' + target;

        tabs.forEach( function( $tab, i ) {
            const selected = ( tabIds[ i ] === target );
            $tab.attr( 'aria-selected', selected ? 'true' : 'false' );
            $tab.attr( 'tabindex', selected ? '0' : '-1' );
        } );

        $panelDesign.prop( 'hidden', target !== 'design' );
        $panelLibrary.prop( 'hidden', target !== 'library' );

        if ( ! silent ) {
            history.replaceState( null, '', '#' + target );
        }

        $document.trigger( 'wpsl-ms-tab-change', [ target ] );
    }

    // Built on first use, then reused: the row it lives in never goes away.
    let $contrastWarn = null;

    /**
     * The shape tables the SVG builder needs, in the shape it expects.
     *
     * @since  3.0.0
     * @return object
     */
    function contrastTables() {
        return {
            shapes:          wpslMarkerStudio.shapes || {},
            centerShapes:    wpslMarkerStudio.centerShapes || {},
            centerDotRadius: wpslMarkerStudio.centerDotRadius || {},
            holeShapes:      wpslMarkerStudio.holeShapes || {},
            labelShapes:     wpslMarkerStudio.labelShapes || [],
            icons:           wpslMarkerStudio.icons || {}
        };
    }

    /**
     * The warning icon in the Icon color row, created on first use.
     *
     * @since  3.0.0
     * @return jQuery
     */
    function contrastWarning() {
        if ( $contrastWarn ) {
            return $contrastWarn;
        }

        $contrastWarn = $(
            '<span class="wpsl-info wpsl-warning wpsl-ms-contrast" tabindex="0" role="button">' +
                '<span class="wpsl-info-text wpsl-hide"></span>' +
            '</span>'
        );

        $( '.wpsl-ms-color[data-color-id="wpsl-ms-icon-color"] .wpsl-ms-color-label' ).after( $contrastWarn );

        // The same tooltip behaviour as every other info icon in the admin.
        if ( window.wpslSharedFuncs && wpslSharedFuncs.bindInfoPopup ) {
            wpslSharedFuncs.bindInfoPopup( $contrastWarn );
        }

        return $contrastWarn;
    }

    /**
     * Re-check the glyph against its backdrop and show or hide the warning.
     *
     * @since  3.0.0
     * @param  object fields The editor state, as collectFields() returns it.
     * @return void
     */
    function refreshContrast( fields ) {
        const svgApi = window.wpslMarkerStudioSvg;

        if ( ! svgApi || typeof svgApi.getLabelContrast !== 'function' ) {
            return;
        }

        const result = svgApi.getLabelContrast( fields, contrastTables() );

        // Null is "nothing to check": no glyph, a logo, an image marker, or
        // an open pin whose transparent centre shows the map rather than a
        // colour.
        if ( ! result || result.passes ) {
            if ( $contrastWarn ) {
                $contrastWarn.hide();
            }

            return;
        }

        const $warn    = contrastWarning();
        const rounded  = parseFloat( result.ratio.toFixed( 2 ) );
        const severity = rounded < 1.5 ? l10n.contrastVeryLow : l10n.contrastLow;

        let message = severity + ' ' + l10n.contrastGlyphHard +
            '<br><br>' + format( l10n.contrastGraphicRequirement, rounded.toString() );

        // A fix is offered only when one exists; on a mid-grey backdrop no
        // lightness of the current hue can reach 3:1 in either direction.
        if ( result.fixed ) {
            message += '<br><br><a href="#" class="wpsl-ms-contrast-autofix">' + l10n.contrastAutoFix + '</a>';
        }

        $warn.attr( 'aria-label', severity );
        $warn.find( '.wpsl-info-text' ).html( message );
        $warn.data( 'wpsl-fixed', result.fixed || '' );
        $warn.show();
    }

    /**
     * Apply the offered fix to the Icon color.
     *
     * @since  3.0.0
     * @param  object e
     * @return void
     */
    function onContrastAutoFix( e ) {
        if ( 'keydown' === e.type && 'Enter' !== e.key && ' ' !== e.key ) {
            return;
        }

        e.preventDefault();

        const fixed = $contrastWarn ? $contrastWarn.data( 'wpsl-fixed' ) : '';

        if ( ! fixed ) {
            return;
        }

        setColorField( colorFields.icon_color, fixed );
        syncColorRow( 'icon_color' );

        announceChange( true );
    }

    /**
     * What the library pane may use. getFields also serves the image-mode
     * test harness, which reads the editor's state through it.
     *
     * @since 3.0.0
     */
    wpslMarkerStudio.editor = {
        load:        loadMarker,
        getFields:   collectFields,
        currentId:   function() { return currentEditId; },
        duplicate:   duplicateMarker,
        deleteMarker: deleteMarker
    };

    /**
     * Tab switching API for the library pane.
     * 
     * @since 3.0.0
     */
    wpslMarkerStudio.tabs = {
        show: function( id ) { showTab( id ); }
    };

    init();

} )( jQuery );