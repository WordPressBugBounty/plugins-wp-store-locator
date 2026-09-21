/**
 * Marker Studio: the client-side marker SVG builder.
 *
 * @since 3.0.0
 */
( function( root ) {
    'use strict';

    /**
     * Escape a value for safe interpolation inside an HTML/SVG attribute.
     *
     * Colors are free text until save, so an unescaped quote could close the
     * attribute and inject markup ( e.g. <image onerror> ) into the preview.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {string}
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
     * Spell a number the way PHP spells it in a string.
     *
     * @since  3.0.0
     * @param  {number} value
     * @return {string}
     */
    function phpNumber( value ) {
        const number = Number( value );

        if ( ! isFinite( number ) ) {
            return '0';
        }

        return String( Number( number.toPrecision( 14 ) ) );
    }

    /**
     * Mirror PHP's `( isset( $v ) && is_numeric( $v ) ) ? (int) $v : $fallback`.
     * Math.trunc matches a PHP (int) cast ( truncates, never rounds ).
     *
     * @since  3.0.0
     * @param  {*}      value
     * @param  {number} fallback
     * @return {number}
     */
    function phpInt( value, fallback ) {
        if ( typeof value === 'undefined' || null === value || '' === value || typeof value === 'boolean' ) {
            return fallback;
        }

        const number = Number( value );

        if ( isNaN( number ) || ! isFinite( number ) ) {
            return fallback;
        }

        return Math.trunc( number );
    }

    /**
     * Mirror PHP's `isset( $v ) && (bool) $v` for the shadow flag.
     * PHP reads "0" as false; JS reads it as true, so it is checked explicitly.
     *
     * @since  3.0.0
     * @param  {*} value
     * @return {boolean}
     */
    function phpBool( value ) {
        if ( typeof value === 'undefined' || null === value ) {
            return false;
        }

        if ( '0' === value || 0 === value || '' === value ) {
            return false;
        }

        return !! value;
    }

    /**
     * The transparent margin an image marker's box carries, in image pixels.
     *
     * @since  3.0.0
     * @param  {number} h
     * @return {number}
     */
    function getImageMargin( h ) {
        return Math.max( 1, Math.round( h / 8 ) );
    }

    /**
     * The pixel box an image marker's artwork wants.
     *
     * @since  3.0.0
     * @param  {number} w
     * @param  {number} h
     * @param  {number} [size] Defaults to 38, matching Custom_Markers::DEFAULT_SIZE.
     * @return {number[]} [ width, height ] of the artwork box.
     */
    function getImageRenderSize( w, h, size ) {
        if ( typeof size === 'undefined' ) {
            size = 38;
        }

        w = Math.trunc( w );
        h = Math.trunc( h );

        if ( w < 1 || h < 1 ) {
            return [ 0, 0 ];
        }

        const m      = getImageMargin( h );
        const height = Math.round( Math.trunc( size ) * ( h + 2 * m ) / h );

        return [ Math.round( height * ( w + 2 * m ) / ( h + 2 * m ) ), height ];
    }

    /**
     * The corner radius an image marker's frame is drawn with, in image pixels.
     *
     * @since  3.0.0
     * @param  {number} w
     * @param  {number} h
     * @param  {number} radius The stored percentage, 0-50.
     * @return {number}
     */
    function getImageFrameRadius( w, h, radius ) {
        radius = Math.max( 0, Math.min( 50, phpInt( radius, 0 ) ) );

        return Math.round( radius * Math.min( Math.trunc( w ), Math.trunc( h ) ) / 100 );
    }

    /**
     * The outline width an image marker's frame is drawn with, in image pixels.
     *
     * @since  3.0.0
     * @param  {number} h
     * @param  {number} width The stored slider step, 0-8.
     * @return {number}
     */
    function getImageFrameStroke( h, width ) {
        width = Math.max( 0, Math.min( 8, phpInt( width, 0 ) ) );

        return width ? Math.max( 1, Math.round( width * Math.trunc( h ) / 64 ) ) : 0;
    }

    /**
     * Build the SVG for an image-type marker.
     *
     * @since  3.0.0
     * @param  {object} config
     * @return {string} SVG markup, or '' without a usable image.
     */
    function buildImageSvg( config ) {
        const w       = phpInt( config.image_w, 0 );
        const h       = phpInt( config.image_h, 0 );
        const logoSrc = config.logo_src ? String( config.logo_src ) : '';

        if ( w < 1 || h < 1 || ! logoSrc ) {
            return '';
        }

        const size   = phpInt( config.size, 38 );
        const shadow = phpBool( config.shadow );

        const m          = getImageMargin( h );
        const renderSize = getImageRenderSize( w, h, size );

        let defs    = '';
        let content = '<image href="' + escapeAttr( logoSrc ) + '" x="0" y="0" width="' + escapeAttr( w ) + '" height="' + escapeAttr( h ) + '" preserveAspectRatio="xMidYMid meet" />';

        if ( phpBool( config.image_frame ) ) {
            const r  = getImageFrameRadius( w, h, config.image_radius );
            const sw = getImageFrameStroke( h, phpInt( config.stroke_width, 1 ) );

            const fill   = config.fill_color ? config.fill_color : '#ff3b30';
            const stroke = config.stroke_color ? config.stroke_color : '#ffffff';

            const rect = '<rect x="0" y="0" width="' + escapeAttr( w ) + '" height="' + escapeAttr( h ) + '"'
                + ' rx="' + escapeAttr( r ) + '" ry="' + escapeAttr( r ) + '"';

            defs = '<clipPath id="wpsl-marker-frame">' + rect + ' /></clipPath>';

            content = '<g clip-path="url(#wpsl-marker-frame)">' + content + '</g>';

            // Absent means on, unlike image_frame: a framed marker saved
            // before the background could be dropped was drawn with one.
            const fillOn = ( typeof config.image_fill === 'undefined' || null === config.image_fill )
                ? true
                : phpBool( config.image_fill );

            if ( fillOn ) {
                content = rect + ' fill="' + escapeAttr( fill ) + '" stroke="none" />' + content;
            }

            if ( sw ) {
                content += rect + ' fill="none" stroke="' + escapeAttr( stroke ) + '" stroke-width="' + escapeAttr( sw ) + '" />';
            }
        }

        if ( shadow ) {
            const dy           = Math.max( 1, Math.round( h / 32 ) );
            const stdDeviation = Math.max( 1, Math.round( 3 * h / 64 ) );
            defs += '<filter id="wpsl-marker-shadow" x="-50%" y="-50%" width="200%" height="200%">'
                + '<feDropShadow dx="0" dy="' + escapeAttr( dy ) + '" stdDeviation="' + escapeAttr( stdDeviation ) + '" flood-color="#000000" flood-opacity="0.35" />'
                + '</filter>';
            content = '<g filter="url(#wpsl-marker-shadow)">' + content + '</g>';
        }

        if ( '' !== defs ) {
            defs = '<defs>' + defs + '</defs>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg"'
            + ' viewBox="' + escapeAttr( -m ) + ' ' + escapeAttr( -m ) + ' ' + escapeAttr( w + 2 * m ) + ' ' + escapeAttr( h + 2 * m ) + '"'
            + ' width="' + escapeAttr( renderSize[0] ) + '" height="' + escapeAttr( renderSize[1] ) + '"'
            + ' data-wpsl-anchor="bottom" data-wpsl-anchor-y="' + escapeAttr( h ) + '">'
            + defs + content + '</svg>';
    }

    /**
     * Build the SVG markup for a marker config.
     *
     * @since  3.0.0
     * @param  {object} markerConfig The marker fields ( the editor's collectFields() shape ).
     * @param  {object} tables       The lookup tables PHP publishes via
     *                               wp_localize_script() -- shape silhouettes,
     *                               bounds, icon scaling, center/hole shapes,
     *                               and the merged Lucide + Phosphor icon set.
     *                               See Assets\Manager::get_marker_studio_data().
     * @return {string} SVG markup, or '' for an unknown or unpublished shape.
     */
    function buildMarkerSvg( markerConfig, tables ) {
        const config = markerConfig || {};
        const source = tables || {};
        const shapes = source.shapes || {};
        const bodies = source.shapeBodies || {};
        const bounds = source.shapeBounds || {};
        const scales = source.iconScale || {};
        const icons  = source.icons || {};

        const shape = config.shape ? String( config.shape ) : 'classic_pin';

        if ( 'image' === shape ) {
            return buildImageSvg( config );
        }

        if ( ! has( shapes, shape ) || ! has( bodies, shape ) || ! has( scales, shape ) ) {
            return '';
        }

        const fill        = config.fill_color ? config.fill_color : '#ff3b30';
        const stroke      = config.stroke_color ? config.stroke_color : '#ffffff';
        const strokeWidth = phpInt( config.stroke_width, 1 );
        const iconName    = config.icon_name ? String( config.icon_name ) : '';
        const iconColor   = config.icon_color ? config.icon_color : '#ffffff';

        // Text on this shape draws this much larger than an icon would.
        const textScales = source.textScale || {};
        const textScale  = has( textScales, shape ) ? Number( textScales[ shape ] ) : 1;
        const size       = phpInt( config.size, 38 );
        const iconSize   = phpInt( config.icon_size, 100 );
        const shadow     = phpBool( config.shadow );

        const logoSrc = config.logo_src ? String( config.logo_src ) : '';

        let pathContent = '';
        let fillIcon    = false;
        let textIcon    = false;

        if ( ! logoSrc && iconName ) {
            if ( 'dot' === iconName ) {
                const dotShapes = ( source.centerShapes && source.centerShapes.dot ) ? source.centerShapes.dot : [];
                const dotRadius = source.centerDotRadius || {};
                if ( dotShapes.indexOf( shape ) !== -1 && has( dotRadius, shape ) ) {
                    pathContent = '<circle cx="12" cy="12" r="' + escapeAttr( dotRadius[ shape ] ) + '" fill="' + escapeAttr( iconColor ) + '" stroke="none" />';
                }
            } else if ( 'text' === iconName ) {
                const labelShapes = source.labelShapes || [];
                const labelApi    = root.wpslMarkerLabel;

                if ( labelApi && labelShapes.indexOf( shape ) !== -1 && typeof config.label_text !== 'undefined' && null !== config.label_text ) {
                    pathContent = labelApi.markup( config.label_text, iconColor, textScale );
                    textIcon    = '' !== pathContent;
                }
            } else {
                pathContent = has( icons, iconName ) ? icons[ iconName ] : '';

                // "ph-" marks a Phosphor glyph: solid artwork painted fill =
                // icon color with no stroke. Only when the name matched, so
                // an unknown "ph-" name degrades like an unknown Lucide one.
                fillIcon = '' !== pathContent && 0 === iconName.indexOf( 'ph-' );
            }
        }

        const scale  = scales[ shape ];
        const multiplier = iconSize / 100;
        const effectiveScale = phpNumber( scale * multiplier );
        const offset = phpNumber( 16 - ( 12 * scale * multiplier ) );

        const transform = ' transform="translate(' + escapeAttr( offset ) + ', ' + escapeAttr( offset )
            + ') scale(' + escapeAttr( effectiveScale ) + ')"';

        // The icon group names what it holds and the color it was painted
        // with, mirroring get_svg_marker().
        let iconKind;

        if ( logoSrc ) {
            iconKind = 'logo';
        } else if ( '' === pathContent ) {
            iconKind = 'none';
        } else if ( textIcon ) {
            iconKind = 'text';
        } else if ( 'dot' === iconName ) {
            iconKind = 'dot';
        } else {
            iconKind = 'icon';
        }

        const groupOpen = '<g data-wpsl-icon="' + escapeAttr( iconKind ) + '" data-wpsl-icon-color="' + escapeAttr( iconColor ) + '"'
            + ' data-wpsl-text-scale="' + escapeAttr( textScale ) + '"';

        let iconGroup;

        if ( logoSrc ) {
            iconGroup = groupOpen + transform + '><image href="' + escapeAttr( logoSrc ) + '" x="0" y="0" width="24" height="24" preserveAspectRatio="xMidYMid meet" /></g>';
        } else if ( textIcon ) {
            iconGroup = groupOpen + transform + '>' + pathContent + '</g>';
        } else if ( fillIcon ) {
            iconGroup = groupOpen + ' fill="' + escapeAttr( iconColor ) + '" stroke="none"' + transform + '>' + pathContent + '</g>';
        } else {
            iconGroup = groupOpen + ' fill="none" stroke="' + escapeAttr( iconColor ) + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' + transform + '>' + pathContent + '</g>';
        }

        const paint = ' fill="' + escapeAttr( fill ) + '" stroke="' + escapeAttr( stroke ) + '" stroke-width="' + escapeAttr( strokeWidth ) + '" stroke-linejoin="round" />';

        const geometry = shapes[ shape ];
        const body     = bodies[ shape ] + paint;

        let center       = '';
        const holeShapes = source.holeShapes || {};

        if ( phpBool( config.center_fill ) && has( holeShapes, shape ) ) {
            const hole        = holeShapes[ shape ];
            const centerColor = config.center_color ? config.center_color : '#ffffff';

            center = '<circle cx="' + escapeAttr( hole.cx ) + '" cy="' + escapeAttr( hole.cy ) + '" r="' + escapeAttr( hole.r ) + '"'
                + ' fill="' + escapeAttr( centerColor ) + '" stroke="none" />';
        }

        const renderSize = getRenderSize( geometry, size );

        let defs    = '';
        let content = center + body + iconGroup;

        if ( shadow ) {
            defs = '<defs><filter id="wpsl-marker-shadow" x="-50%" y="-50%" width="200%" height="200%">'
                + '<feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-color="#000000" flood-opacity="0.35" />'
                + '</filter></defs>';
            content = '<g filter="url(#wpsl-marker-shadow)">' + content + '</g>';
        }

        const anchorX = has( geometry, 'anchor_x' ) ? ' data-wpsl-anchor-x="' + escapeAttr( geometry.anchor_x ) + '"' : '';
        const anchorY = ( 'bottom' === geometry.anchor && has( bounds, shape ) )
            ? ' data-wpsl-anchor-y="' + escapeAttr( Math.min(
                bounds[ shape ][3] + ( strokeWidth / 2 ),
                geometry.origin[1] + geometry.viewbox[1]
            ) ) + '"'
            : '';

        // Mirrors get_svg_marker(): only a shape with something behind the
        // text may take a runtime label, and a logo is never written over.
        const labelable = ( ! logoSrc && ( source.labelShapes || [] ).indexOf( shape ) !== -1 ) ? ' data-wpsl-labelable="1"' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg"'
            + ' viewBox="' + escapeAttr( geometry.origin[0] ) + ' ' + escapeAttr( geometry.origin[1] ) + ' '
            + escapeAttr( geometry.viewbox[0] ) + ' ' + escapeAttr( geometry.viewbox[1] ) + '"'
            + ' width="' + escapeAttr( renderSize[0] ) + '" height="' + escapeAttr( renderSize[1] ) + '"'
            + ' data-wpsl-anchor="' + escapeAttr( geometry.anchor ) + '"' + anchorX + anchorY + labelable + '>'
            + defs + content + '</svg>';
    }

    /**
     * The pixel box a marker's artwork wants.
     *
     * @since  3.0.0
     * @param  {object} geometry One row of the published shapes table.
     * @param  {number} size     The marker's on-screen height in px.
     * @return {number[]} [ width, height ] of the artwork box.
     */
    function getRenderSize( geometry, size ) {
        const height = Math.round( Math.trunc( size ) * geometry.viewbox[1] / geometry.size_units );

        return [ Math.round( height * geometry.aspect ), height ];
    }

    /**
     * Own-property test, so a shape or icon named "constructor" or "toString"
     * cannot resolve to something off Object.prototype.
     *
     * @since  3.0.0
     * @param  {object} table
     * @param  {string} key
     * @return {boolean}
     */
    function has( table, key ) {
        return Object.prototype.hasOwnProperty.call( table, key );
    }

    /**
     * The data URI form of a marker, encoded the way
     * Custom_Markers::get_data_uri() encodes it.
     *
     * @since  3.0.0
     * @param  {string} svg
     * @return {string} The data URI, or '' when there is no artwork.
     */
    function toDataUri( svg ) {
        if ( ! svg ) {
            return '';
        }

        const encoded = encodeURIComponent( svg ).replace( /[!'()*]/g, function( char ) {
            return '%' + char.charCodeAt( 0 ).toString( 16 ).toUpperCase();
        } );

        return 'data:image/svg+xml;charset=utf-8,' + encoded;
    }

    /**
     * The color a marker's glyph is drawn on, following buildMarkerSvg's
     * render order: the centre disc on a filled hole shape, the silhouette
     * everywhere else.
     *
     * Null means nothing to check: image shapes and logos draw a bitmap,
     * an unfilled hole shows the map through, and a glyph the shape cannot
     * render draws nothing.
     *
     * @since  3.0.0
     * @param  {object}      markerConfig  The same config buildMarkerSvg takes.
     * @param  {object}      tables        The same shape tables buildMarkerSvg takes.
     * @return {string|null}               Hex color behind the glyph, or null.
     */
    function getLabelBackdrop( markerConfig, tables ) {
        const config = markerConfig || {};
        const source = tables || {};
        const shapes = source.shapes || {};

        const shape = config.shape ? String( config.shape ) : 'classic_pin';

        if ( 'image' === shape || ! has( shapes, shape ) ) {
            return null;
        }

        const logoSrc  = config.logo_src ? String( config.logo_src ) : '';
        const iconName = config.icon_name ? String( config.icon_name ) : '';

        if ( logoSrc || ! iconName ) {
            return null;
        }

        if ( 'dot' === iconName ) {
            const dotShapes = ( source.centerShapes && source.centerShapes.dot ) ? source.centerShapes.dot : [];
            const dotRadius = source.centerDotRadius || {};

            if ( dotShapes.indexOf( shape ) === -1 || ! has( dotRadius, shape ) ) {
                return null;
            }
        } else if ( 'text' === iconName ) {
            const labelShapes = source.labelShapes || [];
            const labelApi    = root.wpslMarkerLabel;

            if ( ! labelApi || labelShapes.indexOf( shape ) === -1 ) {
                return null;
            }

            if ( typeof config.label_text === 'undefined' || null === config.label_text ) {
                return null;
            }

            // An empty label renders nothing.
            if ( '' === labelApi.markup( config.label_text, '#000000' ) ) {
                return null;
            }
        } else {
            const icons = source.icons || {};

            if ( ! has( icons, iconName ) || ! icons[ iconName ] ) {
                return null;
            }
        }

        // A hole shape only has a backdrop once its centre is filled in.
        const holeShapes = source.holeShapes || {};

        if ( has( holeShapes, shape ) ) {
            if ( ! phpBool( config.center_fill ) ) {
                return null;
            }

            return config.center_color ? config.center_color : '#ffffff';
        }

        return config.fill_color ? config.fill_color : '#ff3b30';
    }

    /**
     * Check a marker's glyph against its backdrop for WCAG contrast.
     *
     * Returns the backdrop, the ratio between them, pass/fail against the
     * 3:1 non-text threshold ( WCAG 1.4.11 ), and the nearest passing glyph
     * color. The fix only shifts lightness, so a blue glyph stays blue.
     *
     * Null means there is nothing to check -- see getLabelBackdrop.
     *
     * @since  3.0.0
     * @param  {object}      markerConfig  The same config buildMarkerSvg takes.
     * @param  {object}      tables        The same shape tables buildMarkerSvg takes.
     * @return {object|null}               { backdrop, glyph, ratio, threshold, passes, fixed }
     */
    function getLabelContrast( markerConfig, tables ) {
        const config   = markerConfig || {};
        const backdrop = getLabelBackdrop( config, tables );

        const api = root.wpslSharedFuncs && root.wpslSharedFuncs.contrast;

        if ( null === backdrop || ! api ) {
            return null;
        }

        const glyph     = config.icon_color ? config.icon_color : '#ffffff';
        const threshold = api.GRAPHIC_THRESHOLD;
        const ratio     = api.getContrastRatio( glyph, backdrop );

        // Decided on the rounded ratio, the same as the appearance editor,
        // so a value that displays as "3.00" is not reported as failing.
        const passes = Math.round( ratio * 100 ) / 100 >= threshold;

        return {
            backdrop:  backdrop,
            glyph:     glyph,
            ratio:     ratio,
            threshold: threshold,
            passes:    passes,
            fixed:     passes ? null : api.findFixedColor( glyph, backdrop, threshold )
        };
    }

    root.wpslMarkerStudioSvg = {
        buildMarkerSvg:       buildMarkerSvg,
        getLabelBackdrop:     getLabelBackdrop,
        getLabelContrast:     getLabelContrast,
        getImageRenderSize:   getImageRenderSize,
        getImageFrameRadius:  getImageFrameRadius,
        getImageFrameStroke:  getImageFrameStroke,
        toDataUri:            toDataUri,
        escapeAttr:           escapeAttr
    };

} )( typeof window !== 'undefined' ? window : this );