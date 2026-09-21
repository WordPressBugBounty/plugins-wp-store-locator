import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';

/**
 * Mapbox layers management.
 * 
 * @since 3.0.0
 */

export const layers = {
    active: [],

    /**
     * The marker symbol layers on one map, read from its own style.
     *
     * layers.active is shared by every map on the page and every [wpsl]
     * search resets it, so it can't tell which layers a [wpsl_map] has.
     *
     * @since  3.0.0
     * @param  {object} map Mapbox map object
     * @return {array}  The layer ids, clusters excluded
     */
    markerLayerIds: function( map ) {
        const sources = [ 'locations', 'start-location', 'wpsl-route-locations' ];
        const style   = map.getStyle();

        if ( ! style || ! Array.isArray( style.layers ) ) {
            return [];
        }

        return style.layers.filter( function( layer ) {
            return layer.type === 'symbol' && layer.id !== 'cluster-count' && sources.includes( layer.source );
        }).map( function( layer ) {
            return layer.id;
        });
    },

    /**
     * Add a layer to the map.
     *
     * @since  3.0.0
     * @param  {object} args Layer arguments
     * @return {void}
     */
    add: function( args ) {
        if ( args.map.getLayer( args.layerId ) ) {
            return;
        }

        let source;

        if ( typeof args.dataSource !== 'undefined' ) {
            source = args.dataSource;
        } else if (
            args.layerId === 'start' &&
            args.map._wpslClusterEnabled &&
            typeof config.markers.cluster === 'object' &&
            config.markers.cluster.excludeStartMarker &&
            args.map.getSource( 'start-location' )
        ) {
            source = 'start-location';
        } else {
            source = 'locations';
        }

        const icon = this.iconLayout( args.layerId );
        const layerArgs = {
            'id': args.layerId,
            'type': 'symbol',
            'source': source,
            'layout': {
                'icon-image': icon['icon-image'],
                'icon-allow-overlap' : true,
                'icon-anchor': 'bottom',
                'icon-offset': icon['icon-offset']
            },
            'filter': [ '==', 'icon', args.layerId ]
        };

        if ( this.labelsOn() && this.labelLayout( slData.layerDetails[ args.layerId ] ) ) {
            this.bindLabelImages( args.map );
        }

        this.active.push( args.layerId );

        args.map.addLayer( wp.hooks.applyFilters( 'wpslAddLayerArgs', layerArgs ) );
        args.map.setLayoutProperty( args.layerId, 'visibility', 'visible' );

        if ( config.markers.startOnTop && args.map.getLayer( 'start' ) ) {
            args.map.moveLayer( 'start' );
        }

        this.bindHoverCursor( args.map, args.layerId );
    },

    /**
     * A layer's icon-image and icon-offset.
     *
     * Pins anchor at their tip; a custom marker's offset is measured from the
     * bottom centre, so 'bottom' works for every shape and icon-offset carries
     * the difference. Labels are drawn into a copy of the artwork per label,
     * not as layer text: Mapbox draws all of a layer's text after all of its
     * icons, so text would show through every marker stacked on top of it.
     *
     * @since  3.0.0
     * @param  {string} layerId The symbol layer id
     * @return {object} { 'icon-image', 'icon-offset' }
     */
    iconLayout: function( layerId ) {
        const src    = slData.layerDetails[ layerId ];
        const custom = helpers.markers.getCustomMarkerGeometry( src );

        return {
            'icon-image':  ( this.labelsOn() && this.labelLayout( src ) ) ? this.labelImageExpression( layerId ) : layerId + '-image',
            'icon-offset': custom ? custom.offset : [ 0, 0 ]
        };
    },

    /**
     * Show a pointer cursor when the mouse is over a marker.
     *
     * The keyboard overlay boxes are pointer-events:none, so the cursor has
     * to come from the symbol layer. Bound once per layer; the start marker
     * is draggable and keeps its own.
     *
     * @since  3.0.0
     * @param  {object} map     The Mapbox map instance
     * @param  {string} layerId The symbol layer id
     * @return {void}
     */
    bindHoverCursor: function( map, layerId ) {
        if ( layerId === 'start' ) {
            return;
        }

        map._wpslHoverBoundLayers = map._wpslHoverBoundLayers || {};

        if ( map._wpslHoverBoundLayers[ layerId ] ) {
            return;
        }

        // Track each hovered layer: a cluster's mouseenter can fire before
        // the marker's mouseleave, and clearing outright would drop the cursor.
        const hovered = map._wpslHoveredLayers = map._wpslHoveredLayers || new Set();

        const bound = {
            enter: function() {
                hovered.add( layerId );
                map.getCanvas().style.cursor = 'pointer';
            },
            leave: function() {
                hovered.delete( layerId );

                if ( ! hovered.size ) {
                    map.getCanvas().style.cursor = '';
                }
            }
        };

        // Kept so unbindHoverCursor() can take them off again.
        map._wpslHoverBoundLayers[ layerId ] = bound;

        map.on( 'mouseenter', layerId, bound.enter );
        map.on( 'mouseleave', layerId, bound.leave );
    },

    /**
     * Take a layer's hover listeners off the map -- GL JS keeps them after
     * the layer is removed.
     *
     * @since  3.0.0
     * @param  {object} map     The Mapbox map instance
     * @param  {string} layerId The symbol layer id
     * @return {void}
     */
    unbindHoverCursor: function( map, layerId ) {
        const bound = map._wpslHoverBoundLayers ? map._wpslHoverBoundLayers[ layerId ] : null;
        if ( ! bound ) {
            return;
        }

        map.off( 'mouseenter', layerId, bound.enter );
        map.off( 'mouseleave', layerId, bound.leave );

        delete map._wpslHoverBoundLayers[ layerId ];
    },

    /**
     * Whether layers draw a runtime label. Needs wpsl-marker-label.js,
     * which is only enqueued while labels are on.
     *
     * @since  3.0.0
     * @return {boolean}
     */
    labelsOn: function() {
        const mode = ( config.markers && typeof config.markers === 'object' ) ? config.markers.labels : '';

        return ( 'numbers' === mode || 'letters' === mode )
            && typeof window !== 'undefined'
            && !! window.wpslMarkerLabel
            && typeof window.wpslMarkerLabel.fontSize === 'function';
    },

    /**
     * Where a label sits on labelable artwork, and how large. Reads the
     * SVG the way wpslMarkerLabel.apply() does -- the icon group's 24x24
     * box -- converted to pixels so drawLabelImage() lands in the same place.
     *
     * @since  3.0.0
     * @param  {*} src A marker src.
     * @return {object|null} { color, textScale, width, height, centre, unit },
     *                       centre in px from the artwork's top left corner
     *                       and unit the px per icon box unit; null when
     *                       the artwork cannot take a label.
     */
    labelLayout: function( src ) {
        if ( typeof src !== 'string' || ! /^data:image\/svg\+xml/i.test( src ) ) {
            return null;
        }

        let markup;

        try {
            markup = decodeURIComponent( src.slice( src.indexOf( ',' ) + 1 ) );
        } catch ( e ) {
            return null;
        }

        if ( ! /<svg[^>]*\sdata-wpsl-labelable="1"/.test( markup ) ) {
            return null;
        }

        const size    = markup.match( /<svg[^>]*\bwidth="(\d+(?:\.\d+)?)"[^>]*\bheight="(\d+(?:\.\d+)?)"/i );
        const viewBox = markup.match( /<svg[^>]*\bviewBox="(-?[\d.]+)[ ,]+(-?[\d.]+)[ ,]+(\d[\d.]*)[ ,]+(\d[\d.]*)"/i );
        const group   = /<g data-wpsl-icon="[a-z]+" data-wpsl-icon-color="([^"]*)"(?: data-wpsl-text-scale="([^"]*)")?[^>]*\btransform="translate\(\s*(-?[\d.]+)[ ,]+(-?[\d.]+)\s*\)\s*scale\(\s*(-?[\d.]+)\s*\)"/.exec( markup );

        if ( ! size || ! viewBox || ! group ) {
            return null;
        }

        const width  = parseFloat( size[1] );
        const height = parseFloat( size[2] );
        const vx     = parseFloat( viewBox[1] );
        const vy     = parseFloat( viewBox[2] );
        const vw     = parseFloat( viewBox[3] );
        const vh     = parseFloat( viewBox[4] );
        const tx     = parseFloat( group[3] );
        const ty     = parseFloat( group[4] );
        const scale  = parseFloat( group[5] );

        if ( ! ( width > 0 && height > 0 && vw > 0 && vh > 0 && scale > 0 ) ) {
            return null;
        }

        // Pixels per viewBox unit. The builders keep the box uniform.
        const unit = height / vh;

        return {
            color:     group[1].replace( /&quot;/g, '"' ).replace( /&#0?39;/g, "'" ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&amp;/g, '&' ),
            textScale: group[2] ? parseFloat( group[2] ) : 1,
            width:     width,
            height:    height,
            centre:    [ ( tx + 12 * scale - vx ) * unit, ( ty + 12 * scale - vy ) * unit ],
            unit:      scale * unit
        };
    },

    /**
     * The icon-image for a labelled layer: the layer's own image for a
     * feature without a label, otherwise '{layerId}-image::{label}', which
     * drawLabelImage() creates on first use.
     *
     * @since  3.0.0
     * @param  {string} layerId The symbol layer id
     * @return {Array} A Mapbox expression
     */
    labelImageExpression: function( layerId ) {
        const base = layerId + '-image';
        const text = [ 'to-string', [ 'coalesce', [ 'get', 'marker_label' ], '' ] ];

        return [ 'case', [ '==', text, '' ], base, [ 'concat', base + '::', text ] ];
    },

    /**
     * Create label images when the map first asks for them. Bound once per map.
     *
     * @since  3.0.0
     * @param  {object} map The Mapbox map instance
     * @return {void}
     */
    bindLabelImages: function( map ) {
        if ( map._wpslLabelImagesBound ) {
            return;
        }

        map._wpslLabelImagesBound = true;

        map.on( 'styleimagemissing', function( event ) {
            layers.drawLabelImage( map, event.id );
        } );
    },

    /**
     * Draw a label onto a copy of its layer's artwork and register it.
     *
     * Synchronous on purpose: an image added inside the styleimagemissing
     * callback is used straight away, one added later is not. The canvas
     * the artwork was rasterized to is kept for this ( see loadSvg() ).
     *
     * @since  3.0.0
     * @param  {object} map The Mapbox map instance
     * @param  {string} id  The missing image id, '{layerId}-image::{label}'
     * @return {void}
     */
    drawLabelImage: function( map, id ) {
        const split = id.indexOf( '-image::' );

        if ( split < 1 || map.hasImage( id ) || ! this.labelsOn() ) {
            return;
        }

        const layerId = id.slice( 0, split );
        const label   = window.wpslMarkerLabel;
        const text    = label.clamp( id.slice( split + 8 ) );
        const base    = map._wpslLabelBases ? map._wpslLabelBases[ layerId ] : null;
        const shape   = this.labelLayout( slData.layerDetails[ layerId ] );

        if ( ! text || ! base || ! shape ) {
            return;
        }

        const canvas  = document.createElement( 'canvas' );
        canvas.width  = base.canvas.width;
        canvas.height = base.canvas.height;

        const ctx = canvas.getContext( '2d' );
        ctx.drawImage( base.canvas, 0, 0 );

        // Canvas px per artwork px, then the same size and baseline wpslMarkerLabel.markup() uses.
        const scale = canvas.height / shape.height;
        const size  = label.fontSize( text, shape.textScale ) * shape.unit * scale;

        ctx.font         = '700 ' + size + 'px ' + label.FONT_FAMILY;
        ctx.fillStyle    = shape.color;
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'alphabetic';
        ctx.fillText( text, shape.centre[0] * scale, shape.centre[1] * scale + size * label.BASELINE_PERCENT / 100 );

        map.addImage( id, ctx.getImageData( 0, 0, canvas.width, canvas.height ), { pixelRatio: base.ratio } );

        ( map._wpslLabelImages = map._wpslLabelImages || [] ).push( id );
    },

    /**
     * Keep the canvas a layer's artwork was rasterized to, for drawLabelImage().
     *
     * @since  3.0.0
     * @param  {object} map     The Mapbox map instance
     * @param  {string} layerId The symbol layer id
     * @param  {object} canvas  The rasterized artwork
     * @param  {number} ratio   The pixel ratio it was registered at
     * @return {void}
     */
    keepLabelBase: function( map, layerId, canvas, ratio ) {
        if ( ! this.labelsOn() ) {
            return;
        }

        map._wpslLabelBases = map._wpslLabelBases || {};
        map._wpslLabelBases[ layerId ] = { canvas: canvas, ratio: ratio };
    },

    /**
     * Remove every label image and kept artwork, alongside the layers' own images.
     *
     * @since  3.0.0
     * @param  {object} map The Mapbox map instance
     * @return {void}
     */
    removeLabelImages: function( map ) {
        ( map._wpslLabelImages || [] ).forEach( function( id ) {
            if ( map.hasImage( id ) ) {
                map.removeImage( id );
            }
        } );

        map._wpslLabelImages = [];
        map._wpslLabelBases  = {};
    },
    
    /**
     * Image loading and management methods.
     * 
     * @since 3.0.0
     */
    image: {
        /**
         * Load an image used in a marker on the map.
         *
         * @since  3.0.0
         * @param  {object} args The current map object
         * @return {void}
         */
        load: function( args ) {
            if ( this.exists( args.layerId + '-image', args.map ) ) {
                if ( typeof args.onLayerAdded === 'function' ) {
                    args.onLayerAdded();
                }

                return;
            }

            const imageUrl = slData.layerDetails[ args.layerId ];
            if ( helpers.markers.isSvgSrc( imageUrl ) ) {
                this.loadSvg( args, imageUrl );

                return;
            }

            args.map.loadImage(
                imageUrl,
                ( error, image ) => {
                    if ( error ) {
                        console.error( `Error loading image for ${args.layerId}:`, error );
                        throw error;
                    }

                    const custom = helpers.markers.getCustomMarkerGeometry( imageUrl );
                    const height = custom ? custom.height : config.markers.scaledSize[1];
                    const pixelRatio = ( height > 0 && image.height > 0 ) ? ( image.height / height ) : 1;

                    this.register( args, image, pixelRatio );
                }
            );
        },

        /**
         * Rasterize an SVG marker and register it with the map.
         *
         * @since  3.0.0
         * @param  {object} args     The layer arguments ( layerId, map, onLayerAdded )
         * @param  {string} imageUrl The SVG URL to rasterize
         * @return {void}
         */
        loadSvg: function( args, imageUrl ) {
            // Min 2x keeps the WebGL-resampled sprite crisp; cap at 4 for memory.
            const ratio  = Math.min( Math.max( window.devicePixelRatio || 1, 2 ), 4 );

            // A custom marker's artwork carries its own render size, so a
            // circular or square shape isn't stretched into the pins' 24x35 box.
            const custom = helpers.markers.getCustomMarkerGeometry( imageUrl );
            const width  = custom ? custom.width : config.markers.scaledSize[0];
            const height = custom ? custom.height : config.markers.scaledSize[1];
            const pxW    = Math.round( width * ratio );
            const pxH    = Math.round( height * ratio );
            const id     = args.layerId + '-image';

            const finalize = () => {
                layers.add( args );

                if ( typeof args.onLayerAdded === 'function' ) {
                    args.onLayerAdded();
                }
            };

            // Sharp path: source SVG already sized to pxW x pxH, draw 1:1.
            const addSharp = ( img ) => {
                if ( ! args.map.hasImage( id ) ) {
                    const canvas = document.createElement( 'canvas' );
                    canvas.width  = pxW;
                    canvas.height = pxH;

                    const ctx = canvas.getContext( '2d' );
                    ctx.drawImage( img, 0, 0, pxW, pxH );

                    args.map.addImage( id, ctx.getImageData( 0, 0, pxW, pxH ), { pixelRatio: ratio } );
                    layers.keepLabelBase( args.map, args.layerId, canvas, ratio );
                }

                finalize();
            };

            // Fallback: add the raw <img> at 1x ( used when the fetch fails ).
            const addFallback = () => {
                const img = new Image();
                img.onload = () => {
                    if ( ! args.map.hasImage( id ) ) {
                        args.map.addImage( id, img, { pixelRatio: 1 } );

                        const canvas  = document.createElement( 'canvas' );
                        canvas.width  = img.width;
                        canvas.height = img.height;
                        canvas.getContext( '2d' ).drawImage( img, 0, 0 );

                        layers.keepLabelBase( args.map, args.layerId, canvas, 1 );
                    }

                    finalize();
                };

                img.onerror = () => {
                    console.error( `Error loading SVG image for ${args.layerId}:`, imageUrl );
                };

                img.src = imageUrl;
            };

            fetch( imageUrl )
                .then( ( response ) => {
                    if ( ! response.ok ) {
                        throw new Error( 'Failed to load SVG marker: ' + imageUrl );
                    }

                    return response.text();
                } )
                .then( ( markup ) => {
                    const svg = new DOMParser().parseFromString( markup, 'image/svg+xml' ).documentElement;

                    // Pin the intrinsic size so the decode happens at full resolution.
                    svg.setAttribute( 'width',  pxW );
                    svg.setAttribute( 'height', pxH );

                    const blob = new Blob( [ new XMLSerializer().serializeToString( svg ) ], { type: 'image/svg+xml' } );
                    const url  = URL.createObjectURL( blob );
                    const img  = new Image();

                    img.onload = () => {
                        addSharp( img );
                        URL.revokeObjectURL( url );
                    };

                    img.onerror = () => {
                        URL.revokeObjectURL( url );
                        addFallback();
                    };

                    img.src = url;
                } )
                .catch( addFallback );
        },

        /**
         * Register a decoded raster image with the map and finalize the layer.
         *
         * @since  3.0.0
         * @param  {object} args       The layer arguments
         * @param  {object} image      The image returned by map.loadImage()
         * @param  {number} pixelRatio The pixel ratio to register the image at
         * @return {void}
         */
        register: function( args, image, pixelRatio ) {
            if ( ! args.map.hasImage( args.layerId + '-image' ) ) {
                args.map.addImage( args.layerId + '-image', image, {
                    pixelRatio: pixelRatio
                });
            }

            layers.add( args );

            if ( typeof args.onLayerAdded === 'function' ) {
                args.onLayerAdded();
            }
        },

        /**
         * Check if an image has already been added to the map based on the passed id.
         *
         * @since  3.0.0
         * @param  {string} id The ID of the image
         * @param  {object} map Map object
         * @return {boolean} True if image exists
         */
        exists: function( id, map ) {
            return map.hasImage( id );
        }
    }
};