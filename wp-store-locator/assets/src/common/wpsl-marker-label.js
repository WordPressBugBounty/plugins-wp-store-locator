/**
 * Marker label text: the client-side mirror of Marker_Label.
 *
 * @since 3.0.0
 */
( function( root ) {
    'use strict';

    const MAX_WEIGHT = 3;

    // The widest label the Studio's Text field accepts. See Marker_Label::STUDIO_MAX_WEIGHT.
    const STUDIO_MAX_WEIGHT = 2;

    const FONT_SIZE = {
        1: '15',
        2: '12',
        3: '9.5'
    };

    // Glyphs that take two columns. The same ranges as Marker_Label::WIDE.
    const WIDE = /[\p{Script=Han}\p{Script=Hiragana}\p{Script=Katakana}\p{Script=Hangul}\u3000-\u303F\uFF01-\uFF60\uFFE0-\uFFE6\u2600-\u27BF\u{1F000}-\u{1FAFF}]/u;

    // Whitespace trimmed off both ends. The same class as Marker_Label::TRIM.
    const TRIM = /^[\s\p{Z}\uFEFF]+|[\s\p{Z}\uFEFF]+$/gu;

    // Controls and the two zero-width blanks, removed from anywhere in a
    // label before it is measured. The same class as Marker_Label::STRIP.
    const STRIP = /[\p{Cc}\u200B\u2060]/gu;

    const FONT_FAMILY = 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif';

    // How far below the box centre the baseline sits, as a percentage of the
    // font size: half a cap height. Integer percent so this twin and
    // Marker_Label round identically -- see Marker_Label::BASELINE_PERCENT.
    const BASELINE_PERCENT = 35;

    // Code points that never start a unit. The same class as Marker_Label::EXTENDER.
    const EXTENDER = /^[\p{M}\u200C\u200D\uFE00-\uFE0F\u{E0100}-\u{E01EF}\u{1F3FB}-\u{1F3FF}\u{E0020}-\u{E007F}]$/u;

    // A regional indicator symbol. The same class as Marker_Label::REGIONAL.
    const REGIONAL = /^[\u{1F1E6}-\u{1F1FF}]$/u;

    const ZWJ = '\u200D';

    /**
     * Escape text for an attribute.
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
            .replace( /'/g, '&#039;' );
    }

    /**
     * Escape a text node, spelled the way PHP's esc_html() spells it.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {string}
     */
    function escapeText( value ) {
        return escapeAttr( value );
    }

    /**
     * Split text into user-perceived characters.
     *
     * @since  3.0.0
     * @param  {*} text
     * @return {string[]}
     */
    function graphemes( text ) {
        if ( typeof text !== 'string' || '' === text ) {
            return [];
        }

        // Code points, not code units: an emoji is one item.
        const points = Array.from( text );

        const units  = [];
        let current  = '';
        let previous = '';
        let regional = 0;

        for ( let i = 0; i < points.length; i++ ) {
            const point = points[ i ];

            const joins = ( '' !== current ) && (
                EXTENDER.test( point )
                || ZWJ === previous
                || ( 1 === regional && REGIONAL.test( point ) )
            );

            if ( ! joins ) {
                if ( '' !== current ) {
                    units.push( current );
                }

                current  = '';
                regional = 0;
            }

            current += point;

            if ( REGIONAL.test( point ) ) {
                regional++;
            }

            previous = point;
        }

        if ( '' !== current ) {
            units.push( current );
        }

        return units;
    }

    /**
     * How many columns one grapheme takes.
     *
     * @since  3.0.0
     * @param  {string} grapheme
     * @return {number} 1 or 2.
     */
    function weightOf( grapheme ) {
        return WIDE.test( grapheme ) ? 2 : 1;
    }

    /**
     * How many columns a label takes.
     *
     * @since  3.0.0
     * @param  {*} text
     * @return {number}
     */
    function weight( text ) {
        let total = 0;

        graphemes( text ).forEach( function( grapheme ) {
            total += weightOf( grapheme );
        } );

        return total;
    }

    /**
     * Trim a label and cut it down to a maximum weight.
     *
     * @since  3.0.0
     * @param  {*}      text
     * @param  {number} [max] The heaviest label to keep. Defaults to MAX_WEIGHT.
     * @return {string}
     */
    function clamp( text, max ) {
        if ( typeof text !== 'string' ) {
            return '';
        }

        text = text.replace( STRIP, '' ).replace( TRIM, '' );
        if ( '' === text ) {
            return '';
        }

        max = ( typeof max === 'undefined' ) ? MAX_WEIGHT : Math.max( 1, Math.min( MAX_WEIGHT, Math.trunc( Number( max ) ) || 1 ) );

        let out   = '';
        let total = 0;

        const parts = graphemes( text );

        for ( let i = 0; i < parts.length; i++ ) {
            const w = weightOf( parts[ i ] );
            if ( total + w > max ) {
                break;
            }

            out   += parts[ i ];
            total += w;
        }

        return out;
    }

    /**
     * The font size a label is drawn at, in icon box units ( the box is 24
     * wide ). Heavier labels draw smaller so three columns still fit. The
     * Mapbox layer reads this too -- it draws labels onto a canvas copy.
     *
     * @since  3.0.0
     * @param  {string} text  A label already clamped.
     * @param  {*}      scale The shape's text scale.
     * @return {number}
     */
    function fontSize( text, scale ) {
        const total = weight( text );
        const base  = Object.prototype.hasOwnProperty.call( FONT_SIZE, total ) ? FONT_SIZE[ total ] : FONT_SIZE[ MAX_WEIGHT ];

        // The shape's text scale. Anything that is not a positive number
        // means 1. Rounded to two decimals, the same as Marker_Label::font_size().
        const factor = ( typeof scale === 'number' || typeof scale === 'string' ) && Number( scale ) > 0 && isFinite( Number( scale ) ) ? Number( scale ) : 1;

        return Math.round( Number( base ) * factor * 100 ) / 100;
    }

    /**
     * The <text> element for a label, centred in the 24x24 icon box.
     *
     * @since  3.0.0
     * @param  {*}      text  Raw label text, clamped here.
     * @param  {string} color The text color.
     * @return {string} SVG markup, or '' for an empty label.
     */
    function markup( text, color, scale ) {
        text = clamp( text );

        if ( '' === text ) {
            return '';
        }

        const size = fontSize( text, scale );

        // Half a cap height below the centre: see BASELINE_PERCENT.
        const dy = Math.round( size * BASELINE_PERCENT ) / 100;

        return '<text x="12" y="12" text-anchor="middle" dy="' + escapeAttr( dy ) + '"'
            + ' font-family="' + escapeAttr( FONT_FAMILY ) + '" font-weight="700" font-size="' + escapeAttr( size ) + '"'
            + ' fill="' + escapeAttr( color ) + '" stroke="none">' + escapeText( text ) + '</text>';
    }

    /**
     * The label for a result's position in the list.
     *
     * @since  3.0.0
     * @param  {number} index 1-based position.
     * @param  {string} mode  'numbers' or 'letters'; anything else labels nothing.
     * @return {string}
     */
    function indexLabel( index, mode ) {
        index = Math.trunc( Number( index ) );

        if ( ! ( index >= 1 ) ) {
            return '';
        }

        if ( 'numbers' === mode ) {
            return String( index );
        }

        if ( 'letters' === mode ) {
            // A..Z, then AA..AZ, BA.. -- the way spreadsheet columns run.
            let out = '';
            let n   = index;

            while ( n > 0 ) {
                const r = ( n - 1 ) % 26;

                out = String.fromCharCode( 65 + r ) + out;
                n   = Math.floor( ( n - 1 ) / 26 );
            }

            return out;
        }

        return '';
    }

    /**
     * Encode SVG markup as a data URI, spelled the way
     * Custom_Markers::get_data_uri() spells it ( rawurlencode ).
     *
     * @since  3.0.0
     * @param  {string} svg
     * @return {string}
     */
    function toDataUri( svg ) {
        const encoded = encodeURIComponent( svg ).replace( /[!'()*]/g, function( char ) {
            return '%' + char.charCodeAt( 0 ).toString( 16 ).toUpperCase();
        } );

        return 'data:image/svg+xml;charset=utf-8,' + encoded;
    }

    const applied = new Map();

    /**
     * Write a label into a marker's artwork.
     *
     * @since  3.0.0
     * @param  {*}      uri  A marker src.
     * @param  {string} text The label.
     * @return {*} The labelled data URI, or the input untouched.
     */
    function apply( uri, text ) {
        text = clamp( text );

        if ( '' === text ) {
            return uri;
        }

        return replaceIcon( uri, text );
    }

    /**
     * Take the glyph out of a marker's artwork,
     * leaving the space a label would fill empty.
     *
     * @since  3.0.0
     * @param  {*} uri A marker src.
     * @return {*} The stripped data URI, or the input untouched.
     */
    function strip( uri ) {
        return replaceIcon( uri, '' );
    }

    /**
     * Swap a labelable artwork's icon group for a label. Shared by
     * apply() and strip().
     *
     * @since  3.0.0
     * @param  {*}      uri  A marker src.
     * @param  {string} text The label, already clamped; '' draws nothing.
     * @return {*} The rewritten data URI, or the input untouched.
     */
    function replaceIcon( uri, text ) {
        if ( typeof uri !== 'string' || ! /^data:image\/svg\+xml;charset=utf-8,/i.test( uri ) ) {
            return uri;
        }

        // A newline cannot occur in a data URI, so it separates the two safely.
        const key = uri + '\n' + text;

        if ( applied.has( key ) ) {
            return applied.get( key );
        }

        let svg;

        try {
            svg = decodeURIComponent( uri.slice( uri.indexOf( ',' ) + 1 ) );
        } catch ( e ) {
            return uri;
        }

        if ( ! /<svg[^>]*\sdata-wpsl-labelable="1"/.test( svg ) ) {
            return uri;
        }

        // The colour and, on markers built since it existed, the text scale
        // the shape wants. An older marker has no scale attribute: draw at 1.
        const open = /<g data-wpsl-icon="[a-z]+" data-wpsl-icon-color="([^"]*)"(?: data-wpsl-text-scale="([^"]*)")?[^>]*>/.exec( svg );

        if ( ! open ) {
            return uri;
        }

        // The group's own closing tag: a Phosphor icon nests a <g> of its
        // own, so count depth rather than stopping at the next </g>.
        const tags = /<g\b|<\/g>/g;
        let depth  = 1;
        let end    = -1;
        let match;

        tags.lastIndex = open.index + open[0].length;

        while ( ( match = tags.exec( svg ) ) ) {
            if ( '<g' === match[0] ) {
                depth++;
            } else if ( 0 === --depth ) {
                end = match.index;
                break;
            }
        }

        if ( end < 0 ) {
            return uri;
        }

        const color = open[1].replace( /&quot;/g, '"' ).replace( /&#0?39;/g, "'" ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&amp;/g, '&' );
        const group = open[0].replace( /data-wpsl-icon="[a-z]+"/, 'data-wpsl-icon="text"' );
        const scale = open[2] ? parseFloat( open[2] ) : 1;
        const inner = '' === text ? '' : markup( text, color, scale );
        const out   = toDataUri( svg.slice( 0, open.index ) + group + inner + svg.slice( end ) );

        applied.set( key, out );

        return out;
    }

    root.wpslMarkerLabel = {
        MAX_WEIGHT:        MAX_WEIGHT,
        BASELINE_PERCENT:  BASELINE_PERCENT,
        FONT_FAMILY:       FONT_FAMILY,
        STUDIO_MAX_WEIGHT: STUDIO_MAX_WEIGHT,
        graphemes:  graphemes,
        weight:     weight,
        clamp:      clamp,
        fontSize:   fontSize,
        markup:     markup,
        indexLabel: indexLabel,
        apply:      apply,
        strip:      strip
    };

} )( typeof window !== 'undefined' ? window : this );