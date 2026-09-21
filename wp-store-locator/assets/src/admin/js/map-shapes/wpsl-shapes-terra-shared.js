/**
 * Map Shapes drawing: the engine-independent half of the drawing adapters.
 *
 * @since 3.0.0
 */
( function() {
    'use strict';

    /** Editor shape type => Terra Draw mode name. */
    const DRAW_MODES = {
        polygon:   'polygon',
        polyline:  'linestring',
        rectangle: 'rectangle',
        circle:    'circle'
    };

    /** The gesture that finishes each shape in the drawing library. */
    const INSTRUCTIONS = {
        polygon:   'drawVertices',
        polyline:  'drawVertices',
        circle:    'drawCircleDrag',
        rectangle: 'drawRectangleDrag'
    };

    const DEFAULT_COLOR = '#cc3333';

    /*
     * How far a deactivated shape is faded on the editor map. Visitors never
     * see one at all, so rendering it at full strength would mislead.
     */
    const INACTIVE_DIM = 0.4;

    /*
     * Shape appearance while drawing, before the editor renders it in its own
     * colors. Terra Draw names its style keys per mode, hence two objects.
     */
    const DRAW_PREVIEW_AREA = {
        fillColor:    '#3388ff',
        fillOpacity:  0.2,
        outlineColor: '#3388ff',
        outlineWidth: 2
    };

    const DRAW_PREVIEW_LINE = {
        lineStringColor: '#3388ff',
        lineStringWidth: 2
    };

    /**
     * The drawing engine an adapter runs: Terra Draw, its four modes, and the
     * finished-drawing hand-off to the editor. Made per adapter because it
     * holds state that belongs to one map.
     *
     * @since  3.0.0
     * @param  {object} options { createAdapter, engineLabel }: a factory for
     *                          the Terra Draw adapter wrapping the map, and
     *                          the engine name for the load-failure report.
     * @return {object}
     */
    function createEngine( options ) {
        let draw       = null;
        let completeCb = null;

        /*
         * When the last drawing finished. The click that ends a drawing also
         * reaches the map listener and would deselect the new shape, so the
         * adapter asks consumeFinishClick() first. Time-bounded, not a flag:
         * a drawing can end with Enter, which would leave a flag armed to eat
         * a later real deselect.
         */
        let finishedAt = 0;

        /**
         * Build the drawing engine and hand the editor its ready signal.
         *
         * @since  3.0.0
         * @param  {function} ready
         * @return void
         */
        function start( ready ) {
            try {
                draw = new window.terraDraw.TerraDraw( {
                    adapter: options.createAdapter(),
                    modes: [
                        new window.terraDraw.TerraDrawPolygonMode( { styles: DRAW_PREVIEW_AREA } ),
                        new window.terraDraw.TerraDrawLineStringMode( { styles: DRAW_PREVIEW_LINE } ),

                        /* Both gestures: the instruction line describes a drag, and click-then-move keeps working too. */
                        new window.terraDraw.TerraDrawCircleMode( { drawInteraction: 'click-move-or-drag', styles: DRAW_PREVIEW_AREA } ),
                        new window.terraDraw.TerraDrawRectangleMode( { drawInteraction: 'click-move-or-drag', styles: DRAW_PREVIEW_AREA } )
                    ]
                } );

                draw.on( 'ready', ready );
                draw.on( 'finish', onDrawFinish );

                draw.start();
            } catch ( error ) {
                reportLoadFailure( error );
            }
        }

        /**
         * A finished drawing, handed to the editor as a feature.
         *
         * 'finish' fires for dragged vertices too, so only a completed drawing
         * becomes a shape; it leaves Terra Draw's store, or the editor would
         * draw it twice.
         *
         * @since  3.0.0
         * @param  {string} id      The Terra Draw feature id.
         * @param  {object} context { action, mode }.
         * @return void
         */
        function onDrawFinish( id, context ) {
            if ( ! draw || ! context || 'draw' !== context.action ) {
                return;
            }

            // Stamped first: the finishing press is still on its way to the
            // map listener -- see the note on finishedAt.
            finishedAt = Date.now();

            const drawn = draw.getSnapshot().filter( function( item ) {
                return item.id === id;
            } )[0];

            draw.removeFeatures( [ id ] );

            // One shape per arming: the editor re-arms the tool itself.
            draw.setMode( 'static' );

            const converted = drawn ? terraFeatureToFeature( drawn ) : null;

            if ( converted && completeCb ) {
                completeCb( converted );
            }
        }

        /**
         * Report a drawing engine that never came up.
         *
         * @since  3.0.0
         * @param  {*} error
         * @return void
         */
        function reportLoadFailure( error ) {
            window.console.error( 'WP Store Locator: the ' + options.engineLabel + ' drawing library could not be loaded.', error );
        }

        /**
         * Start drawing a shape.
         *
         * @since  3.0.0
         * @param  {string} shapeType polygon|circle|rectangle|polyline.
         * @return void
         */
        function enableDraw( shapeType ) {
            const mode = DRAW_MODES[ shapeType ];

            if ( ! draw || ! mode ) {
                return;
            }

            draw.setMode( mode );
        }

        /**
         * Abandon the drawing in progress.
         *
         * clear() is safe as a blanket wipe: finished shapes leave the store
         * on conversion, so anything still in there is the abandoned drawing.
         *
         * @since  3.0.0
         * @return void
         */
        function cancelDraw() {
            if ( ! draw ) {
                return;
            }

            draw.setMode( 'static' );
            draw.clear();
        }

        /**
         * The l10n key describing how this engine finishes a shape.
         *
         * @since  3.0.0
         * @param  {string} shapeType
         * @return {string}
         */
        function instructionKey( shapeType ) {
            return INSTRUCTIONS[ shapeType ] || '';
        }

        /**
         * Register the callback for a finished drawing.
         *
         * @since  3.0.0
         * @param  {function} cb
         * @return void
         */
        function onShapeComplete( cb ) {
            completeCb = cb;
        }

        /**
         * Whether the click now reaching the map finished the last drawing.
         * Spent either way: one press, one suppression.
         *
         * @since  3.0.0
         * @return {boolean}
         */
        function consumeFinishClick() {
            if ( ! finishedAt ) {
                return false;
            }

            const wasFinish = Date.now() - finishedAt < 500;

            finishedAt = 0;

            return wasFinish;
        }

        return {
            start:              start,
            enableDraw:         enableDraw,
            cancelDraw:         cancelDraw,
            instructionKey:     instructionKey,
            onShapeComplete:    onShapeComplete,
            consumeFinishClick: consumeFinishClick,
            reportLoadFailure:  reportLoadFailure
        };
    }

    /**
     * Convert a Terra Draw feature to a WPSL GeoJSON feature.
     *
     * Terra Draw keys its output by properties.mode, not geometry.type: a
     * circle arrives as a closed 64-gon Polygon with radiusKilometers, never
     * a Point with a radius in metres.
     *
     * @since  3.0.0
     * @param  {object} tdFeature A Terra Draw feature.
     * @return {object|null}
     */
    function terraFeatureToFeature( tdFeature ) {
        if ( ! tdFeature || 'object' !== typeof tdFeature ) {
            return null;
        }

        const geometry   = tdFeature.geometry;
        const properties = tdFeature.properties || {};

        if ( ! geometry ) {
            return null;
        }

        if ( 'circle' === properties.mode ) {
            const ring   = ( geometry.coordinates || [] )[0] || [];
            const center = ringCentroid( ring );
            const radius = parseFloat( properties.radiusKilometers ) * 1000;

            if ( ! center || ! ( radius > 0 ) ) {
                return null;
            }

            return feature(
                { type: 'Point', coordinates: center },
                { shape_type: 'circle', radius: radius }
            );
        }

        if ( 'rectangle' === properties.mode ) {
            return feature(
                { type: 'Polygon', coordinates: [ closeRing( ( geometry.coordinates || [] )[0] || [] ) ] },
                { shape_type: 'rectangle' }
            );
        }

        if ( 'polygon' === properties.mode ) {
            return feature(
                { type: 'Polygon', coordinates: ( geometry.coordinates || [] ).map( closeRing ) },
                { shape_type: 'polygon' }
            );
        }

        if ( 'linestring' === properties.mode ) {
            return feature(
                { type: 'LineString', coordinates: geometry.coordinates },
                { shape_type: 'polyline' }
            );
        }

        return null;
    }

    /**
     * The mean of a ring's positions, excluding the closing duplicate.
     * A regular polygon's vertex mean is its centre, so a Terra Draw circle's
     * 64-gon ring gives its centre back.
     *
     * @since  3.0.0
     * @param  {Array} ring [ [ lng, lat ], ... ], closed.
     * @return {Array|null} [ lng, lat ], or null for an empty ring.
     */
    function ringCentroid( ring ) {
        if ( ! ring || ! ring.length ) {
            return null;
        }

        const positions = ring.length > 1 ? ring.slice( 0, -1 ) : ring;

        let sumLng = 0;
        let sumLat = 0;

        for ( let i = 0; i < positions.length; i++ ) {
            sumLng += positions[ i ][0];
            sumLat += positions[ i ][1];
        }

        return [ sumLng / positions.length, sumLat / positions.length ];
    }

    /**
     * A GeoJSON Feature wrapper.
     *
     * @since  3.0.0
     * @param  {object} geometry
     * @param  {object} properties
     * @return {object}
     */
    function feature( geometry, properties ) {
        return { type: 'Feature', geometry: geometry, properties: properties };
    }

    /**
     * Repeat the first position at the end, once. Anything too short to be a
     * ring is left alone rather than padded into something nobody drew.
     *
     * @since  3.0.0
     * @param  {Array} ring
     * @return {Array}
     */
    function closeRing( ring ) {
        if ( ring.length < 3 ) {
            return ring;
        }

        const first = ring[0];
        const last  = ring[ ring.length - 1 ];

        if ( first[0] === last[0] && first[1] === last[1] ) {
            return ring;
        }

        return ring.concat( [ [ first[0], first[1] ] ] );
    }

    /**
     * A hex color, or the default. Same guard as the editor's.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {string}
     */
    function safeColor( value ) {
        return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( String( value ) ) ? String( value ) : DEFAULT_COLOR;
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

        return ( isNaN( width ) || width < 0 ) ? 2 : width;
    }

    /**
     * A 0-1 opacity.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {number}
     */
    function clampOpacity( value ) {
        const opacity = parseFloat( value );

        return isNaN( opacity ) ? 0.4 : Math.min( 1, Math.max( 0, opacity ) );
    }

    window.wpslShapesTerraShared = {
        INACTIVE_DIM:          INACTIVE_DIM,
        createEngine:          createEngine,
        terraFeatureToFeature: terraFeatureToFeature,
        ringCentroid:          ringCentroid,
        feature:               feature,
        closeRing:             closeRing,
        safeColor:             safeColor,
        strokeWidth:           strokeWidth,
        clampOpacity:          clampOpacity
    };
} )();