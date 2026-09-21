<?php
/**
 * Shared helpers for working with underscore template section code.
 *
 * Used by the section editor validator and the section analyzer so both
 * operate on one strip / normalize implementation.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Template_Code {

    /**
     * Regexes matching the Underscore template delimiters the frontend
     * renders with. Must mirror templateSettings in
     * assets/src/admin/js/modules/settings/wpsl-template-validator.js
     * ( and setUnderscoreSettings() in frontend/js/modules/wpsl-setup.js ).
     *
     * @since 3.0.0
     */
    public static $template_tag_regex = [
        'escape'      => '/<%-(.+?)%>/',
        'interpolate' => '/<%=(.+?)%>/',
        'evaluate'    => '/<%(.+?)%>/',
    ];

    /**
     * HTML elements that never have a closing tag.
     *
     * @since 3.0.0
     */
    public static $void_elements = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ];

    /**
     * HTML elements the "tag missing its <" heuristic checks for.
     *
     * Not an allowlist — it's the validator's vocabulary of what a real HTML
     * tag name looks like, and it grants nothing: wp_kses strips disallowed
     * elements on save, and on* handlers are excluded from the attribute sets
     * for the same reason.
     *
     * @since 3.0.0
     */
    public static $html_elements = [
        'a', 'abbr', 'address', 'area', 'article', 'aside', 'audio', 'b', 'base', 'bdi', 'bdo',
        'blockquote', 'body', 'br', 'button', 'canvas', 'caption', 'cite', 'code', 'col',
        'colgroup', 'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'div', 'dl',
        'dt', 'em', 'embed', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2',
        'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html', 'i', 'iframe', 'img',
        'input', 'ins', 'kbd', 'label', 'legend', 'li', 'link', 'main', 'map', 'mark', 'menu',
        'meta', 'meter', 'nav', 'noscript', 'object', 'ol', 'optgroup', 'option', 'output', 'p',
        'param', 'picture', 'pre', 'progress', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'script',
        'section', 'select', 'slot', 'small', 'source', 'span', 'strong', 'style', 'sub',
        'summary', 'sup', 'svg', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th',
        'thead', 'time', 'title', 'tr', 'track', 'u', 'ul', 'var', 'video', 'wbr',
    ];

    /**
     * The element names as a regex alternation, longest first so 'h1' can't
     * be shadowed by a shorter prefix.
     *
     * @since  3.0.0
     * @return string The alternation, for use inside a case-insensitive pattern.
     */
    public static function element_name_pattern() {
        $elements = self::$html_elements;

        usort( $elements, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        } );

        return implode( '|', $elements );
    }

    /**
     * Strip template tags so the remaining markup parses as HTML.
     * Interpolations become '1x1' to keep attributes non-empty; evaluate
     * blocks are removed entirely. Line numbers are preserved.
     *
     * @since  3.0.0
     * @param  string $code The template code.
     * @return string The code without template tags.
     */
    public static function strip_template_tags( $code ) {
        $code = preg_replace( self::$template_tag_regex['escape'], '1x1', $code );
        $code = preg_replace( self::$template_tag_regex['interpolate'], '1x1', $code );

        return preg_replace( self::$template_tag_regex['evaluate'], '', $code );
    }

    /**
     * Normalize stripped template code so it parses as XML: entities
     * and bare ampersands are neutralized, tag names lowercased, boolean
     * attributes valued, void elements self-closed. Line numbers unchanged.
     *
     * @since  3.0.0
     * @param  string $stripped Template code with the <% %> tags stripped.
     * @return string XML-safe markup with unchanged line numbers.
     */
    public static function normalize_for_xml( $stripped ) {
        $xml = preg_replace( '/&#x?[0-9a-fA-F]+;/', 'e', $stripped );
        $xml = preg_replace( '/&[a-zA-Z][a-zA-Z0-9]*;/', 'e', $xml );
        $xml = str_replace( '&', 'a', $xml );

        /*
         * A < that isn't starting a tag, closing tag or comment is just
         * text ( 'open < 5 km' ). Neutralize it so the parser doesn't
         * reject it as an invalid element name.
         */
        $xml = preg_replace( '/<(?![a-zA-Z\/!?])/', 'l', $xml );

        // XML is case-sensitive, HTML isn't: lowercase all closing tag names.
        $xml = preg_replace_callback( '/<\/([a-zA-Z][a-zA-Z0-9]*)\s*>/', function( $matches ) {
            return '</' . strtolower( $matches[1] ) . '>';
        }, $xml );

        $void_elements = self::$void_elements;

        $xml = preg_replace_callback( '/<([a-zA-Z][a-zA-Z0-9]*)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*?)(\/?)>/', function( $matches ) use ( $void_elements ) {
            $tag = strtolower( $matches[1] );

            $attrs = preg_replace_callback( '/([a-zA-Z_][-a-zA-Z0-9_:.]*)(\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', function( $attr ) {
                return empty( $attr[2] ) ? $attr[1] . '="' . $attr[1] . '"' : $attr[0];
            }, $matches[2] );

            $close = ( '/' === $matches[3] || in_array( $tag, $void_elements, true ) ) ? '/' : '';

            return '<' . $tag . $attrs . $close . '>';
        }, $xml );

        return $xml;
    }

    /**
     * Remove the indentation every line of a block shares.
     *
     * Mirrors formatting.dedent() in
     * assets/src/admin/js/modules/helpers/wpsl-formatting.js.
     *
     * Blank lines are ignored when working out the shared prefix — they have
     * no indentation to contribute, and counting them would flatten the
     * block. The prefix is compared character by character rather than by
     * length so a mix of tabs and spaces can never chop a character that
     * isn't common to every line.
     *
     * @since  3.0.0
     * @param  string $block The text to dedent.
     * @return string The block shifted to the left edge.
     */
    public static function dedent( $block ) {
        $lines  = explode( "\n", $block );
        $shared = null;

        foreach ( $lines as $line ) {
            if ( '' === trim( $line ) ) {
                continue;
            }

            preg_match( '/^[\t ]*/', $line, $matches );
            $indent = $matches[0];

            if ( null === $shared ) {
                $shared = $indent;
                continue;
            }

            $i   = 0;
            $max = min( strlen( $shared ), strlen( $indent ) );

            while ( $i < $max && $shared[ $i ] === $indent[ $i ] ) {
                $i++;
            }

            $shared = substr( $shared, 0, $i );
        }

        if ( empty( $shared ) ) {
            return $block;
        }

        return implode( "\n", array_map( function( $line ) use ( $shared ) {
            return ( 0 === strpos( $line, $shared ) ) ? substr( $line, strlen( $shared ) ) : $line;
        }, $lines ) );
    }

    /**
     * Shift a block to a given indentation, keeping its own nesting.
     *
     * @since  3.0.0
     * @param  string $block  The text to shift.
     * @param  string $indent The leading whitespace to apply.
     * @return string The re-indented block.
     */
    public static function reindent( $block, $indent ) {
        $lines = explode( "\n", self::dedent( $block ) );

        if ( '' === $indent ) {
            return implode( "\n", $lines );
        }

        return implode( "\n", array_map( function( $line ) use ( $indent ) {
            return ( '' === trim( $line ) ) ? $line : $indent . $line;
        }, $lines ) );
    }

    /**
     * The leading whitespace of a line.
     *
     * @since  3.0.0
     * @param  string $line The line to read.
     * @return string The line's leading tabs / spaces.
     */
    public static function line_indent( $line ) {
        preg_match( '/^[\t ]*/', $line, $matches );

        return $matches[0];
    }

    /**
     * Remove string literals and comments from a snippet of template JS.
     *
     * Needed because the bracket balance check counts ( ) { }, and a
     * bracket inside "…" or after // is content, not code. Without this
     * a smiley ( <%= ":)" %> ) or replace( "(", "" ) reads as unbalanced.
     *
     * Strings are matched before comments so a // inside a string isn't
     * mistaken for one. Regex literals are left alone ( distinguishing
     * /x/ from division needs a real parser ).
     *
     * @since  3.0.0
     * @param  string $js The JS between a pair of template delimiters.
     * @return string The JS with literals and comments removed.
     */
    public static function strip_js_literals( $js ) {
        return preg_replace(
            '~"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`|//[^\n]*|/\*.*?\*/~s',
            '',
            $js
        );
    }

    /**
     * Parse template code into a DOMDocument whose getLineNo() values map
     * 1:1 onto the input code's line numbers.
     *
     * The <template> wrapper sits on line 1 without a newline, so the
     * parser's line numbers match the editor's.
     *
     * @since  3.0.0
     * @param  string $code The template code.
     * @return \DOMDocument|null The parsed document, or null when the markup is broken.
     */
    public static function parse_dom( $code ) {
        $xml = self::normalize_for_xml( self::strip_template_tags( $code ) );

        $use_errors = libxml_use_internal_errors( true );
        libxml_clear_errors();

        $dom    = new \DOMDocument();
        $loaded = $dom->loadXML( '<template>' . $xml . '</template>' );

        libxml_clear_errors();
        libxml_use_internal_errors( $use_errors );

        return $loaded ? $dom : null;
    }
}