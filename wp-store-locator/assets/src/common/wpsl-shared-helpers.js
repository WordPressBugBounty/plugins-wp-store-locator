/**
 * Shared helper functions for both frontend and admin.
 * 
 * These utilities are used by common modules like 
 * wpsl-dropdowns.js that need to work in both contexts.
 * 
 * @since 3.0.0
 */

/*
 * escapeHtml()'s five entities. The quote characters are not optional: callers
 * build double-quoted attributes with the output ( href="...", <option
 * value="..." ), and an unescaped quote ends the attribute early.
 */
const htmlEscapes = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    '\'': '&#039;'
};

// Parsed geometry per marker src; never grows past the marker count.
const markerGeometryCache = new Map();

/**
 * Parse the geometry out of a custom marker's inline SVG data URI.
 *
 * @since 3.0.0
 * @param   {string} src The marker image src, already known to be an SVG data URI.
 * @returns {object|null} The geometry, or null when the artwork carries none.
 */
function parseCustomMarkerGeometry( src ) {
    let markup;

    try {
        markup = decodeURIComponent( src );
    } catch ( e ) {
        return null;
    }

    const size = markup.match( /<svg[^>]*\bwidth="(\d+(?:\.\d+)?)"[^>]*\bheight="(\d+(?:\.\d+)?)"/i );

    if ( ! size ) {
        return null;
    }

    const width    = parseFloat( size[1] );
    const height   = parseFloat( size[2] );
    const centered = /data-wpsl-anchor="center"/i.test( markup );

    /*
     * data-wpsl-anchor-x / -y are viewBox units, so converting either
     * to a pixel needs the viewBox's own origin and extent -- all four
     * numbers are already on the root.
     */
    const viewBox = markup.match( /<svg[^>]*\bviewBox="(-?[\d.]+)[ ,]+(-?[\d.]+)[ ,]+(\d[\d.]*)[ ,]+(\d[\d.]*)"/i );

    // Absent ( every shape but the flag ), the anchor stays on the
    // horizontal centre.
    let anchorX = width / 2;

    const anchorUnits = markup.match( /\bdata-wpsl-anchor-x="(-?\d+(?:\.\d+)?)"/i );

    if ( anchorUnits && viewBox && parseFloat( viewBox[3] ) > 0 ) {
        anchorX = ( parseFloat( anchorUnits[1] ) - parseFloat( viewBox[1] ) ) / parseFloat( viewBox[3] ) * width;
    }

    // Custom shapes keep a 1-2px outline margin, so data-wpsl-anchor-y is
    // the silhouette's own bottom; the box edge stays the fallback.
    let anchorY = centered ? height / 2 : height;

    const anchorYUnits = markup.match( /\bdata-wpsl-anchor-y="(-?\d+(?:\.\d+)?)"/i );

    if ( ! centered && anchorYUnits && viewBox && parseFloat( viewBox[4] ) > 0 ) {
        anchorY = ( parseFloat( anchorYUnits[1] ) - parseFloat( viewBox[2] ) ) / parseFloat( viewBox[4] ) * height;
    }

    // Whole pixels: a fractional anchor resamples into a blurry marker.
    anchorX = Math.round( anchorX );
    anchorY = Math.round( anchorY );

    // The artwork's centre, measured from the anchor: 0 when they coincide,
    // -0.5 on an odd width, far out on the flag ( anchored at its pole ).
    const centreX = width / 2 - anchorX;

    return {
        width:  width,
        height: height,
        anchor: [ anchorX, anchorY ],

        /**
         * Leaflet's popup tip, relative to the anchor. x uses the artwork's
         * centre: a fractional offset costs a box nothing, unlike artwork.
         */
        popupAnchor: [ centreX, -( anchorY + sharedHelpers.markerGap - 1 ) ],
        position: centered ? 'center' : 'bottom',

        /*
         * Measured from bottom centre ( translate( -50%, -100% ) ). On an odd
         * width that start is a half pixel, and this offset carries the
         * matching half back; rounding it would restore the fraction the
         * anchor above just removed.
         */
        offset: [ centreX, height - anchorY ]
    };
}

/**
 * Build the geometry for a file-based custom marker from its dimensions.
 *
 * A plain image carries no anchor, so it is anchored at its bottom centre,
 * the rule the bundled pins are hard-coded with, and takes the same popup
 * gap a Studio shape reads off its own artwork.
 *
 * @since  3.0.0
 * @param   {number} width  The artwork's display width in pixels
 * @param   {number} height The artwork's display height in pixels
 * @returns {object} { width, height, anchor, popupAnchor, position, offset }
 */
function fileMarkerGeometry( width, height ) {
    const anchorX = Math.round( width / 2 );
    const centreX = width / 2 - anchorX;

    return {
        width:       width,
        height:      height,
        anchor:      [ anchorX, height ],
        popupAnchor: [ centreX, -( height + sharedHelpers.markerGap - 1 ) ],
        position:    'bottom',
        offset:      [ centreX, 0 ]
    };
}

export const sharedHelpers = {

    /**
     * The room left between a marker's artwork and the popup opened on it.
     *
     * @since 3.0.0
     */
    markerGap: 4,

    /**
     * Measure the width of text with specific styling
     *
     * @since   3.0.0
     * @param   {string} text The text to measure
     * @param   {string} className Optional CSS class to apply for styling
     * @param   {jQuery|Element|string} [context] Element the text renders in
     * @returns {number} The width of the text in pixels
     */
    measureTextWidth: function( text, className, context ) {
        return this.measureTextWidths( [ text ], className, context )[0];
    },

    /**
     * Resolve the element a measuring probe should be attached to.
     *
     * @since   3.0.0
     * @param   {jQuery|Element|string} [context] Element the text renders in
     * @returns {jQuery} The element to attach the probe to
     */
    measureContext: function( context ) {
        const roots = '#wpsl-wrap, .wpsl-styled-template-preview';
        const $near = context ? jQuery( context ).closest( roots ) : jQuery();

        if ( $near.length ) {
            return $near;
        }

        const $any = jQuery( roots ).first();

        return $any.length ? $any : jQuery( 'body' );
    },

    /**
     * Measure many texts in one pass.
     *
     * @since   3.0.0
     * @param   {string[]} texts The texts to measure
     * @param   {string} className Optional CSS class to apply for styling
     * @param   {jQuery|Element|string} [context] Element the texts render in
     * @returns {number[]} The width of each text in pixels, in input order
     */
    measureTextWidths: function( texts, className, context ) {
        const $container = jQuery( '<div>' ).css({
            'position': 'absolute',
            'visibility': 'hidden',
            'white-space': 'nowrap',
            'left': '-9999px'
        });

        const samples = texts.map( function( text ) {
            const $span = jQuery( '<span>' ).text( text );

            if ( className ) {
                $span.addClass( className );
            }

            $span.css({
                'width': 'auto',
                'max-width': 'none',
                'min-width': '0',
                'display': 'inline-block'
            });

            return $span.appendTo( $container );
        });

        $container.appendTo( this.measureContext( context ) );

        const widths = samples.map( function( $span ) {
            return Math.ceil( $span.outerWidth() );
        });

        $container.remove();

        return widths;
    },

    /**
     * Convert kebab-case to camelCase
     * 
     * @since   3.0.0
     * @param   {string} str The string to convert
     * @returns {string} The camelCase string
     */
    toCamelCase: function( str ) {
        return str.replace( /-([a-z])/g, function( match, letter ) {
            return letter.toUpperCase();
        });
    },

    /**
     * Escape HTML special characters to prevent XSS when inserting untrusted
     * text into HTML strings. Quotes are escaped too, since callers use the
     * output inside double-quoted attributes.
     *
     * @since   3.0.0
     * @param   {string} text  The text to escape.
     * @returns {string}       The escaped text, safe for HTML element and
     *                         quoted-attribute insertion.
     */
    escapeHtml: function( text ) {
        return String( text ?? '' ).replace( /[&<>"']/g, function( char ) {
            return htmlEscapes[ char ];
        });
    },

    /**
     * Render one store through a compiled section template.
     *
     * Underscore compiles a template into a function whose body runs inside
     * `with ( data )`, so a template that names a field the store data doesn't
     * carry throws a ReferenceError instead of reading as undefined. The
     * results are rendered in a loop with nothing catching it, and online
     * stores go first, so a single bad reference in a customized section
     * leaves the visitor with an empty result list rather than one empty row.
     *
     * @since   3.0.0
     * @param   {function} template The compiled underscore template.
     * @param   {object}   data     The store data to render.
     * @returns {string}            The markup, or '' when the template threw.
     */
    renderTemplate: function( template, data ) {
        try {
            return template( data );
        } catch ( error ) {
            if ( typeof console !== 'undefined' && console.error ) {
                console.error( 'WP Store Locator: a template section failed to render and was skipped.', error );
            }

            return '';
        }
    },

    /**
     * Strip all HTML tags from a string, leaving only the text content.
     *
     * @since   3.0.0
     * @param   {string} text  The text to strip tags from.
     * @returns {string}       The text with all HTML tags removed.
     */
    stripTags: function( text ) {
        const doc = new DOMParser().parseFromString( text, 'text/html' );
        return doc.body.textContent || '';
    },

    /**
     * Truncate text to a max length and append an ellipsis if truncated.
     *
     * @since   3.0.0
     * @param   {string} text     The text to truncate.
     * @param   {number} [max=30] Maximum number of characters.
     * @returns {string}          The truncated text with "..." if it exceeded max.
     */
    truncate: function( text, max = 30 ) {
        if ( text.length > max ) {
            return text.slice( 0, max ) + '...';
        }

        return text;
    },

    /**
     * Sanitize untrusted text for safe display as HTML: strips tags,
     * truncates to a max length, then escapes remaining special characters.
     *
     * @since   3.0.0
     * @param   {string} text     The text to sanitize.
     * @param   {number} [max=45] Maximum number of characters before truncation.
     * @returns {string}          The sanitized text, safe for HTML insertion.
     */
    sanitizeForDisplay: function( text, max = 45 ) {
        return this.escapeHtml( this.truncate( this.stripTags( text ), max ) );
    },

    /**
     * Check whether a marker src points at SVG artwork.
     *
     * Matches both an .svg URL and an inline "data:image/svg+xml" URI, so a
     * custom marker takes the same vector rendering path as a bundled one.
     *
     * @since 3.0.0
     * @param   {string} src The marker image src
     * @returns {boolean} Whether the src is an SVG
     */
    isSvgSrc: function( src ) {
        if ( typeof src !== 'string' || ! src ) {
            return false;
        }

        return /\.svg(\?|#|$)/i.test( src ) || /^data:image\/svg\+xml/i.test( src );
    },

    /**
     * The SVG markup carried by a data URI, when it carries any.
     *
     * @since 3.0.0
     * @param   {string} src The marker image src
     * @returns {string} The SVG markup, or '' when the src does not carry any
     */
    svgMarkupFromDataUri: function( src ) {
        if ( ! this.isSvgSrc( src ) || 0 !== src.indexOf( 'data:' ) || /;base64,/i.test( src ) ) {
            return '';
        }

        const payload = src.slice( src.indexOf( ',' ) + 1 );

        let markup;

        try {
            markup = decodeURIComponent( payload );
        } catch ( e ) {
            return '';
        }

        return /<svg[\s>]/i.test( markup ) ? markup : '';
    },

    /**
     * The box a custom marker's artwork is drawn in, and where it anchors.
     *
     * @since 3.0.0
     * @param   {string} src The marker image src
     * @param   {object} [fileGeometry] src => { width, height }, from the markers props
     * @returns {object|null} { width, height, anchor, popupAnchor, position, offset } or null
     */
    getCustomMarkerGeometry: function( src, fileGeometry ) {
        if ( typeof src !== 'string' || ! src ) {
            return null;
        }

        if ( /^data:image\/svg\+xml/i.test( src ) ) {
            if ( ! markerGeometryCache.has( src ) ) {
                markerGeometryCache.set( src, parseCustomMarkerGeometry( src ) );
            }

            const geometry = markerGeometryCache.get( src );

            if ( ! geometry ) {
                return null;
            }

            return {
                width:       geometry.width,
                height:      geometry.height,
                anchor:      geometry.anchor.slice(),
                popupAnchor: geometry.popupAnchor.slice(),
                position:    geometry.position,
                offset:      geometry.offset.slice()
            };
        }

        const box = fileGeometry ? fileGeometry[ src ] : null;

        if ( box && box.width > 0 && box.height > 0 ) {
            return fileMarkerGeometry( box.width, box.height );
        }

        return null;
    },

    /**
     * Where a Mapbox popup's tip goes, for every anchor GL JS can pick.
     *
     * @since 3.0.0
     * @param   {object} geometry The marker's box: { width, height, anchor },
     *                            from getCustomMarkerGeometry() or the bundled
     *                            equivalent. anchor[] is the distance from the
     *                            box's top-left corner to the point standing on
     *                            the coordinate.
     * @param   {number} gap      Pixels to leave between artwork and popup
     * @returns {object} An offset per anchor, for mapboxgl.Popup
     */
    mapboxPopupOffset: function( geometry, gap = sharedHelpers.markerGap ) {
        const above = geometry.anchor[1] + gap;
        const below = geometry.height - geometry.anchor[1] + gap;
        const left  = geometry.anchor[0] + gap;
        const right = geometry.width - geometry.anchor[0] + gap;

        /*
         * The artwork's centre, measured from the anchor. The three anchors
         * that hang the popup straight above or below the marker have to be
         * centred on the artwork rather than on the coordinate: a snapped
         * anchor sits half a pixel right of centre on an odd width. The corner
         * anchors already extend away horizontally, and the side anchors' x is
         * clearance rather than centring, so neither takes it.
         */
        const centreX = geometry.width / 2 - geometry.anchor[0];

        return {
            'center':       [ centreX, 0 ],
            'top':          [ centreX, below ],
            'top-left':     [ 0, below ],
            'top-right':    [ 0, below ],
            'bottom':       [ centreX, -above ],
            'bottom-left':  [ 0, -above ],
            'bottom-right': [ 0, -above ],
            'left':         [ right, 0 ],
            'right':        [ -left, 0 ]
        };
    },

    /**
     * Push an open Leaflet popup back onto the marker's current artwork.
     *
     * @since   3.0.0
     * @param   {object} marker The Leaflet marker whose icon just changed
     * @returns {void}
     */
    reanchorLeafletPopup: function( marker ) {
        const popup = ( marker && typeof marker.getPopup === 'function' ) ? marker.getPopup() : null;

        if ( popup && popup.isOpen() ) {
            popup.setLatLng( marker.getLatLng() );
        }
    }
};