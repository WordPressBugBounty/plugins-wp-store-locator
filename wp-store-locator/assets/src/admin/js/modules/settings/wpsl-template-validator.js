/**
 * Validator for the section editor's Underscore/HTML templates.
 *
 * 1. Template/JS: every <% %> pair must open and close on the same line
 *    ( the frontend's delimiters don't match newlines ), and the combined
 *    JS must compile via _.template().
 * 2. HTML: template tags are stripped, the remaining markup is normalized
 *    and parsed as XML. XML is strict, so unmatched tags, bad nesting and
 *    unquoted attributes are reported with a line number, unlike DOMParser's
 *    text/html mode which silently auto-corrects.
 *
 * @since 3.0.0
 */
export const templateValidator = {

    /**
     * Must mirror setUnderscoreSettings() in frontend/js/modules/wpsl-setup.js.
     * The frontend renders with these delimiters, and `.+?` doesn't match
     * newlines, so a multi-line template tag is never rendered and must be
     * treated as invalid.
     */
    templateSettings: {
        evaluate: /\<\%(.+?)\%\>/g,
        interpolate: /\<\%=(.+?)\%\>/g,
        escape: /\<\%-(.+?)\%\>/g
    },

    /**
     * HTML elements that never have a closing tag.
     */
    voidElements: [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ],

    /**
     * HTML elements the "missing <" text check reports on.
     * Must mirror $html_elements in class-template-code.php.
     *
     * The check works on text, so a known-name list stops wording like
     * 'Open now >' from being reported as a broken tag. The XML parse still
     * catches any structural breakage this list lets through.
     */
    htmlElements: [
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
        'thead', 'time', 'title', 'tr', 'track', 'u', 'ul', 'var', 'video', 'wbr'
    ],

    /**
     * The element names as a regex alternation, longest first so 'h1' can't
     * be shadowed by a shorter prefix.
     *
     * @since   3.0.0
     * @returns {string} The alternation, for use inside a case-insensitive pattern
     */
    elementNamePattern: function() {
        return this.htmlElements.slice().sort( function( a, b ) {
            return b.length - a.length;
        } ).join( '|' );
    },

    /**
     * Remove string literals and comments from a snippet of template JS.
     * Must mirror strip_js_literals() in class-template-code.php.
     *
     * The bracket balance check counts ( ) { }; a bracket inside "…" or
     * after // is content, not code. Strings are matched before comments so
     * a // inside a string isn't mistaken for one. Regex literals are left
     * alone ( telling /x/ from division needs a parser ).
     *
     * @since   3.0.0
     * @param   {string} js The JS between a pair of template delimiters
     * @returns {string} The JS with literals and comments removed
     */
    stripJsLiterals: function( js ) {
        return js.replace( /"(?:[^"\\]|\\.)*"|'(?:[^'\\]|\\.)*'|`(?:[^`\\]|\\.)*`|\/\/[^\n]*|\/\*[\s\S]*?\*\//g, '' );
    },

    /**
     * Look up a localized error string.
     *
     * @since   3.0.0
     * @param   {string} key The wpslL10n key
     * @returns {string} The localized string, or the key itself as fallback
     */
    str: function( key ) {
        return ( typeof wpslL10n !== 'undefined' && wpslL10n[ key ] ) ? wpslL10n[ key ] : key;
    },

    /**
     * Run all validators and collect every error, sorted by line number.
     *
     * @since   3.0.0
     * @param   {string} code The template code to validate
     * @returns {array} Array of error message strings ( empty if valid )
     */
    validateAll: function( code ) {
        const tagErrors = this.validateTemplateTags( code );

        // Broken <% %> pairs make both the compiled JS and the stripped
        // HTML unreliable, so stop here to avoid cascading false positives.
        if ( tagErrors.length ) {
            return this.sortByLine( tagErrors );
        }

        const errors = this.validateJs( code ).concat( this.validateHtml( code ) );

        return this.sortByLine( errors );
    },

    /**
     * Validate that every <% opens and closes on the same line.
     *
     * The frontend template delimiters don't match newlines, so a tag that
     * spans lines is just as broken as one that never closes.
     *
     * @since   3.0.0
     * @param   {string} code The template code to validate
     * @returns {array} Array of error message strings
     */
    validateTemplateTags: function( code ) {
        const errors = [];
        const lines = code.split( '\n' );

        for ( let i = 0; i < lines.length; i++ ) {
            const tokens = lines[i].match( /<%|%>/g ) || [];
            let open = false;

            for ( const token of tokens ) {
                if ( token === '<%' ) {
                    if ( open ) {
                        errors.push( this.str( 'errorUnclosedTemplateTag' ).replace( '%s', ( i + 1 ) ) );
                    }
                    open = true;
                } else if ( open ) {
                    open = false;
                } else {
                    errors.push( this.str( 'errorStrayTemplateClose' ).replace( '%s', ( i + 1 ) ) );
                }
            }

            if ( open ) {
                errors.push( this.str( 'errorUnclosedTemplateTag' ).replace( '%s', ( i + 1 ) ) );
            }
        }

        return errors;
    },

    /**
     * Validate the JS inside the template tags by compiling the template
     * with the same settings the frontend renders with.
     *
     * On a compile error the balance checker provides line-number detail;
     * if the pairs are balanced the compiler message is shown instead.
     *
     * @since   3.0.0
     * @param   {string} code The template code to validate
     * @returns {array} Array of error message strings
     */
    validateJs: function( code ) {
        if ( typeof _ === 'undefined' || ! _.template ) {
            return this.balanceHints( code );
        }

        try {
            _.template( code, this.templateSettings );

            return [];
        } catch ( e ) {
            const hints = this.balanceHints( code );

            return hints.length ? hints : [ this.str( 'errorTemplateCompile' ).replace( '%s', e.message ) ];
        }
    },

    /**
     * Check for unbalanced () and {} inside <% %> tags.
     *
     * Only used to add line numbers once _.template() has already failed —
     * it doesn't understand strings or comments, so it can't be the
     * source of truth on its own.
     *
     * @since   3.0.0
     * @param   {string} code The template code to validate
     * @returns {array} Array of error message strings
     */
    balanceHints: function( code ) {
        const parenStack = [];
        const braceStack = [];
        const errors = [];
        const lines = code.split( '\n' );

        for ( let i = 0; i < lines.length; i++ ) {
            const blocks = lines[i].match( /<%[-=]?(.+?)%>/g ) || [];

            for ( const block of blocks ) {
                const jsCode = this.stripJsLiterals( block.replace( /^<%[-=]?/, '' ).replace( /%>$/, '' ) );

                for ( const ch of jsCode ) {
                    if ( ch === '(' ) {
                        parenStack.push( i + 1 );
                    } else if ( ch === ')' ) {
                        if ( parenStack.length === 0 ) {
                            errors.push( this.str( 'errorParenWithoutOpen' ).replace( '%s', ( i + 1 ) ) );
                        } else {
                            parenStack.pop();
                        }
                    } else if ( ch === '{' ) {
                        braceStack.push( i + 1 );
                    } else if ( ch === '}' ) {
                        if ( braceStack.length === 0 ) {
                            errors.push( this.str( 'errorBraceWithoutOpen' ).replace( '%s', ( i + 1 ) ) );
                        } else {
                            braceStack.pop();
                        }
                    }
                }
            }
        }

        if ( parenStack.length > 0 ) {
            errors.push( this.str( 'errorUnclosedParen' ).replace( '%s', parenStack[0] ) );
        }

        if ( braceStack.length > 0 ) {
            errors.push( this.str( 'errorUnclosedBrace' ).replace( '%s', braceStack[0] ) );
        }

        return errors;
    },

    /**
     * Validate the HTML structure of the template.
     *
     * @since   3.0.0
     * @param   {string} code The template code to validate
     * @returns {array} Array of error message strings
     */
    validateHtml: function( code ) {
        const errors = [];
        const stripped = this.stripTemplateTags( code );
        const lines = stripped.split( '\n' );

        // A tag missing its opening < is plain text to any parser, so it
        // needs its own check.
        const elements = this.elementNamePattern();
        const missingClose = new RegExp( '(?:^|>)\\s*\\/(' + elements + ')\\s*>', 'i' );

        // Opening variant. Attribute names must start with a letter so
        // 'Store -> details' isn't read as a tag.
        const missingOpen = new RegExp( '(?:^|>)\\s*(' + elements + ')(?:>|(?:\\s+[a-zA-Z][a-zA-Z0-9-]*(?:=(?:"[^"]*"|\'[^\']*\'|[^\\s>]+))?)+\\s*>)', 'i' );

        for ( let i = 0; i < lines.length; i++ ) {
            const missingCloseBracket = lines[i].match( missingClose );
            if ( missingCloseBracket ) {
                errors.push( this.str( 'errorMissingOpenBracket' ).replace( '%1$s', ( i + 1 ) ).replace( '%2$s', '/' + missingCloseBracket[1] ) );
            }

            const missingOpenBracket = lines[i].match( missingOpen );
            if ( missingOpenBracket ) {
                errors.push( this.str( 'errorMissingOpenBracket' ).replace( '%1$s', ( i + 1 ) ).replace( '%2$s', missingOpenBracket[1] ) );
            }
        }

        const xmlError = this.parseAsXml( stripped );
        if ( xmlError ) {
            errors.push( xmlError );
        }

        return errors;
    },

    /**
     * Strip template tags so the remaining markup can be parsed as HTML.
     * Interpolations become '1x1' so attributes like href="<%= url %>" stay
     * non-empty; the digit prefix avoids the missing-< heuristic. Evaluate
     * blocks are removed entirely. Line numbers are preserved because the
     * delimiters never match newlines.
     *
     * @since   3.0.0
     * @param   {string} code The template code
     * @returns {string} The code without template tags
     */
    stripTemplateTags: function( code ) {
        return code
            .replace( this.templateSettings.escape, '1x1' )
            .replace( this.templateSettings.interpolate, '1x1' )
            .replace( this.templateSettings.evaluate, '' );
    },

    /**
     * Parse the stripped template as XML and translate the first parser
     * error into a readable message with a line number.
     *
     * @since   3.0.0
     * @param   {string} stripped Template code with the <% %> tags stripped
     * @returns {string|false} Error message, or false if the markup is valid
     */
    parseAsXml: function( stripped ) {
        if ( typeof DOMParser === 'undefined' ) {
            return false;
        }

        const self = this;

        // A < that isn't starting a tag, closing tag or comment is just
        // text ( 'open < 5 km' ). Neutralize it so the parser doesn't
        // reject it as an invalid element name.
        let xml = stripped
            .replace( /&#x?[0-9a-fA-F]+;/g, 'e' )
            .replace( /&[a-zA-Z][a-zA-Z0-9]*;/g, 'e' )
            .replace( /&/g, 'a' )
            .replace( /<(?![a-zA-Z\/!?])/g, 'l' );

        // XML is case-sensitive, HTML isn't: lowercase all tag names.
        xml = xml.replace( /<\/([a-zA-Z][a-zA-Z0-9]*)\s*>/g, function( match, name ) {
            return '</' + name.toLowerCase() + '>';
        } );

        // Normalize opening tags for XML: lowercase the name, give bare
        // boolean attributes a value, and self-close void elements.
        xml = xml.replace( /<([a-zA-Z][a-zA-Z0-9]*)((?:"[^"]*"|'[^']*'|[^>"'])*?)(\/?)>/g, function( match, name, attrs, selfClose ) {
            const tag = name.toLowerCase();
            const fixedAttrs = attrs.replace( /([a-zA-Z_][-a-zA-Z0-9_:.]*)(\s*=\s*(?:"[^"]*"|'[^']*'|[^\s"'>]+))?/g, function( attrMatch, attrName, attrValue ) {
                return attrValue ? attrMatch : attrName + '="' + attrName + '"';
            } );
            const close = ( selfClose === '/' || self.voidElements.indexOf( tag ) !== -1 ) ? '/' : '';

            return '<' + tag + fixedAttrs + close + '>';
        } );

        // The wrapper sits on line 1 without a newline, so the parser's
        // line numbers map 1:1 onto the editor's.
        const doc = new DOMParser().parseFromString( '<template>' + xml + '</template>', 'application/xml' );
        const parserError = doc.querySelector( 'parsererror' ) || doc.getElementsByTagName( 'parsererror' )[0];
        if ( ! parserError ) {
            return false;
        }

        const text = parserError.textContent || '';

        // Chromium: 'error on line N at column M: reason'. Firefox: 'XML Parsing Error: reason … Line Number N, Column M'.
        const lineMatch = text.match( /error on line (\d+)/i ) || text.match( /line number (\d+)/i ) || text.match( /line (\d+)/i );
        const reasonMatch = text.match( /column \d+:\s*([^\n]+)/i ) || text.match( /XML Parsing Error:\s*([^\n]+)/i );
        let reason = ( reasonMatch ? reasonMatch[1] : text.split( '\n' )[0] ).trim();

        // Translate the most cryptic parser phrasings into plain language.
        if ( /AttValue/i.test( reason ) ) {
            reason = this.str( 'errorHtmlUnquotedAttr' );
        } else if ( /Unescaped '<' not allowed in attributes values/i.test( reason ) ) {
            reason = this.str( 'errorHtmlUnclosedQuote' );
        }

        if ( lineMatch ) {
            return this.str( 'errorHtmlLine' ).replace( '%1$s', lineMatch[1] ).replace( '%2$s', reason );
        }

        return this.str( 'errorHtml' ).replace( '%s', reason );
    },

    /**
     * Sort error messages by the line number they mention.
     *
     * @since   3.0.0
     * @param   {array} errors Error message strings
     * @returns {array} The sorted errors
     */
    sortByLine: function( errors ) {
        errors.sort( function( a, b ) {
            const aMatch = a.match( /line (\d+)/i );
            const bMatch = b.match( /line (\d+)/i );
            const aLine = aMatch ? parseInt( aMatch[1], 10 ) : 9999;
            const bLine = bMatch ? parseInt( bMatch[1], 10 ) : 9999;

            return aLine - bLine;
        } );

        return errors;
    }
};