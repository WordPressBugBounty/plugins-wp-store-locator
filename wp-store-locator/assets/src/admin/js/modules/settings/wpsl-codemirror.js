import { feedbackOverlay } from './wpsl-feedback-overlay.js';
import { templateValidator } from './wpsl-template-validator.js';
import { helpers } from '../wpsl-helpers.js';

/**
 * The section editor textarea 
 * code field with codeMirror.
 *
 * @since 3.0.0
 * @see   https://codemirror.net/
 */
export const codeMirror = {
    editor: null,
    /*
     * The typeof half is not optional: a section only receives the fields its
     * own data carries -- the online listing has no address, for one -- and
     * underscore throws a ReferenceError on a name that isn't there, which
     * takes the whole result list down with it.
     */
    defaultCode: '<% if ( typeof field_type !== "undefined" && field_type ) { %>\n' + '<%= field_type %>\n' + '<% } %>',
    
    /**
     * Initialize the codeMirror editor.
     *
     * @since   3.0.0
     * @param   {string} elem The element to initialize the editor on
     * @returns {void}
     */
    init: function( elem ) {
        const selector = elem || '#wpsl-fancy-code-textarea';
        
        const $elem = jQuery( selector );
        if ( ! $elem.length || ! $elem[0] ) {
            return;
        }

        this.editor = wp.codeEditor.initialize( $elem, 'cm_settings' ).codemirror;
        this.bindListener();
    },

    /**
     * Set the height of the code view
     * to minimum of 5 lines.
     *
     * @since   3.0.0
     * @returns {void}
     */
    setMinLines: function() {
        const lineCount = this.editor.lineCount();
        const n = 5 - lineCount;

        let line = this.editor.getCursor().line;

        for ( let i = 0; i < n; i++ ) {
            this.editor.replaceRange( "\n" + ' ', { line } );
            line++;
        }
    },

    /**
     * Attempt to automatically fix broken template syntax.
     *
     * Fixes unclosed <% tags by appending %> at the end of the line,
     * and removes stray %> closing tags without a matching <%.
     *
     * @since   3.0.0
     * @param   {string} code The template code to fix
     * @returns {string|false} Fixed code if successful, false if unfixable
     */
    autofixTemplateSyntax: function( code ) {
        const lines = code.split( '\n' );
        let openCount = 0;
        let fixed = false;

        const statementKeywords = /^(if|else|for|while|switch|case|break|continue|return|var|let|const|function|do|try|catch|finally|throw)\b/;
        const jsPattern = /(?:!==|===|==|!=|&&|\|\||\?|typeof|return|var|let|const|if|for|while)[\s\S]*%>/;

        for ( let i = 0; i < lines.length; i++ ) {
            const opens = ( lines[i].match( /<%(?!>)/g ) || [] ).length;
            const closes = ( lines[i].match( /%>/g ) || [] ).length;

            openCount += opens - closes;

            // If this line has an unclosed <% (more opens than closes), close it.
            if ( openCount > 0 && closes < opens ) {
                // Check if the line ends with a lone % (missing >) rather than
                // a completely missing %> closer.
                if ( /%\s*$/.test( lines[i] ) && !/%>\s*$/.test( lines[i] ) ) {
                    lines[i] = lines[i].replace( /\s*$/, '>' );
                } else {
                    lines[i] = lines[i].replace( /\s*$/, ' %>' );
                }
                openCount -= 1;
                fixed = true;
            }

            // If we have a stray %> (negative count), the opening <% is
            // probably a broken < that should be <% or <%=.
            if ( openCount < 0 ) {
                // Case 1: < followed by = or whitespace (not a letter) — clearly broken template tag.
                // e.g. '< typeof...' or '<= typeof...'
                const brokenNonLetter = lines[i].match( /<(?!%|\/|[a-zA-Z])(=?\s*)(.*)/ );
                // Case 2: < followed by a letter, but the content up to %> contains
                // JS-like patterns — likely a broken template tag, not an HTML tag.
                // e.g. '<typeof thumb !== "undefined" ? thumb : "" %>'
                const brokenLetter = lines[i].match( /<([a-zA-Z][a-zA-Z0-9]*)([\s\S]*?)%>/ );

                if ( brokenNonLetter ) {
                    const replacement = statementKeywords.test( brokenNonLetter[2].trim() ) ? '<%' : '<%=';

                    if ( brokenNonLetter[1] === '=' ) {
                        lines[i] = lines[i].replace( /<(?!%|\/|[a-zA-Z])=/, '<%=' );
                    } else {
                        lines[i] = lines[i].replace( /<(?!%|\/|[a-zA-Z])/, replacement );
                    }
                } else if ( brokenLetter && jsPattern.test( lines[i] ) ) {
                    const replacement = statementKeywords.test( ( brokenLetter[1] + brokenLetter[2] ).trim() ) ? '<%' : '<%=';
                    lines[i] = lines[i].replace( /<([a-zA-Z][a-zA-Z0-9]*)([\s\S]*?)%>/, replacement + '$1$2%>' );
                } else {
                    // No fixable < found, remove the stray %>.
                    lines[i] = lines[i].replace( /%>/, '' );
                }
                openCount += 1;
                fixed = true;
            }

            // Detect HTML issues on the template-stripped line so <% %> tags
            // (which contain < and >) don't interfere with the detection.
            const lineWithoutTemplates = lines[i].replace( /<%[\s\S]*?%>/g, '' );

            // Fix a closing tag missing its opening < (e.g. '/div>').
            // Check this before the generic missing-< case below.
            if ( /^(\s*)\/([a-zA-Z][a-zA-Z0-9]*[^>]*)>$/.test( lineWithoutTemplates ) ) {
                lines[i] = lines[i].replace( /^(\s*)\//, '$1</' );
                fixed = true;
            } else if ( /^(\s*)([a-zA-Z][a-zA-Z0-9]*)(?:\s[^>]*|)>$/.test( lineWithoutTemplates ) ) {
                // An opening tag missing its opening < (e.g. 'div class="x">').
                lines[i] = lines[i].replace( /^(\s*)([a-zA-Z])/, '$1<$2' );
                fixed = true;
            }

            // Fix unclosed opening tags (e.g. '<div class="x"' at end of line).
            if ( /^(\s*)<(?!\/)([a-zA-Z][a-zA-Z0-9]*[^>]*)$/.test( lineWithoutTemplates ) ) {
                lines[i] = lines[i].replace( /\s*$/, '>' );
                fixed = true;
            }

            // Fix unclosed closing tags (e.g. '</div' at end of line).
            if ( /<\/([a-zA-Z][a-zA-Z0-9]*[^>]*)$/.test( lineWithoutTemplates ) ) {
                lines[i] = lines[i].replace( /\s*$/, '>' );
                fixed = true;
            }

            // Fix missing opening quote in HTML attributes.
            // e.g. class=wpsl-cta-section" → class="wpsl-cta-section"
            if ( /=(?!["'\s])([^\s"'=<>]+)"/.test( lineWithoutTemplates ) ) {
                lines[i] = lines[i].replace( /=(?!["'\s])([^\s"'=<>]+)"/g, '="$1"' );
                fixed = true;
            }

            // Fix completely unquoted attribute values.
            // e.g. class=wpsl-cta-section → class="wpsl-cta-section"
            if ( /=(?!["'\s])([^\s"'=<>]+)(?=\s|>)/.test( lineWithoutTemplates ) ) {
                lines[i] = lines[i].replace( /=(?!["'\s])([^\s"'=<>]+)(?=\s|>)/g, '="$1"' );
                fixed = true;
            }
        }

        // If there's still an unclosed tag at the end, append %> to the last line
        if ( openCount > 0 ) {
            lines[lines.length - 1] = lines[lines.length - 1].replace( /\s*$/, ' %>' );
            openCount -= 1;
            fixed = true;
        }

        // Fix unbalanced parentheses and braces inside <% %> template tags by
        // walking the JS across all tags with a running parenthesis depth: a {
        // reached while parentheses are open (e.g. 'if ( cond {') closes them.
        let parenDepth = 0;
        let braceDepth = 0;
        let lastTemplateLine = -1;

        for ( let i = 0; i < lines.length; i++ ) {
            if ( ! /<%[\s\S]*?%>/.test( lines[i] ) ) {
                continue;
            }

            lastTemplateLine = i;

            lines[i] = lines[i].replace( /<%([\s\S]*?)%>/g, function( match, inner ) {
                let out = '';

                for ( const ch of inner ) {
                    if ( ch === '{' && parenDepth > 0 ) {
                        // Missing ) before this { — insert the closers first.
                        out += ')'.repeat( parenDepth );
                        parenDepth = 0;
                        braceDepth++;
                        out += ch;
                        fixed = true;
                    } else if ( ch === '(' ) {
                        parenDepth++;
                        out += ch;
                    } else if ( ch === ')' ) {
                        parenDepth--;
                        out += ch;
                    } else if ( ch === '{' ) {
                        braceDepth++;
                        out += ch;
                    } else if ( ch === '}' ) {
                        braceDepth--;
                        out += ch;
                    } else {
                        out += ch;
                    }
                }

                return '<%' + out + '%>';
            } );
        }

        // Append any remaining unclosed braces/parentheses inside the last
        // template tag so we never corrupt trailing HTML.
        if ( ( braceDepth > 0 || parenDepth > 0 ) && lastTemplateLine >= 0 ) {
            const suffix = '}'.repeat( braceDepth ) + ')'.repeat( parenDepth );
            // Insert before the last %> on the last line that has a template tag.
            lines[lastTemplateLine] = lines[lastTemplateLine].replace( /%>(?![\s\S]*%>)/, suffix + ' %>' );
            fixed = true;
        }

        if ( ! fixed ) {
            return false;
        }

        const fixedCode = lines.join( '\n' );

        // Verify the fix worked — collect all remaining errors.
        const remainingErrors = this.validateAll( fixedCode );
        if ( remainingErrors.length ) {
            return { fixed: fixedCode, error: remainingErrors };
        }

        return fixedCode;
    },

    /**
     * Highlight one or more lines in the CodeMirror editor with an error style.
     *
     * @since   3.0.0
     * @param   {number|array} lineNums The 1-based line number(s) to highlight
     * @returns {void}
     */
    highlightErrorLine: function( lineNums ) {
        this.clearErrorLines();
        const nums = Array.isArray( lineNums ) ? lineNums : [ lineNums ];
        if ( this.editor ) {
            nums.forEach( function( n ) {
                if ( n > 0 ) {
                    this.editor.addLineClass( n - 1, 'gutter', 'wpsl-error-gutter' );
                }
            }.bind( this ) );
            if ( nums.length > 0 && nums[0] > 0 ) {
                this.editor.setCursor( nums[0] - 1 );
            }
        }
    },

    /**
     * Remove all error line highlights from the CodeMirror editor.
     *
     * @since   3.0.0
     * @returns {void}
     */
    clearErrorLines: function() {
        if ( this.editor ) {
            const lineCount = this.editor.lineCount();
            for ( let i = 0; i < lineCount; i++ ) {
                this.editor.removeLineClass( i, 'gutter', 'wpsl-error-gutter' );
            }
        }
    },

    /**
     * Show an error message above the save button row.
     *
     * @since   3.0.0
     * @param   {string}  message     The error message to display
     * @param   {boolean} showAutofix Whether to show the "Try to autofix" button
     * @param   {boolean} fadeAfter   Whether to fade the message out after 3 seconds
     * @returns {void}
     */
    showSaveError: function( message, showAutofix, fadeAfter ) {
        jQuery( '.wpsl-save-error' ).remove();

        // Escape HTML in the message so tag names like <div> in error
        // text don't break the DOM structure.
        const escaped = message.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
        const $error = jQuery( '<div class="wpsl-save-error wpsl-red-callout"><p>' + escaped + '</p></div>' );

        // Parse all line numbers from the message and highlight them.
        const lineMatches = message.match( /line (\d+)/gi ) || [];
        const lineNums = lineMatches.map( function( m ) {
            return parseInt( m.match( /\d+/ )[0], 10 );
        } );
        if ( lineNums.length ) {
            this.highlightErrorLine( lineNums );
        }

        if ( showAutofix ) {
            const $link = jQuery( '<p class="wpsl-autofix-link"><a href="#">' + wpslL10n.tryAutofix + '</a></p>' );
            $link.find( 'a' ).on( 'click', function( e ) {
                e.preventDefault();
                const fixed = codeMirror.autofixTemplateSyntax( codeMirror.editor.getValue() );
                jQuery( '.wpsl-save-error' ).remove();

                if ( fixed && typeof fixed === 'string' ) {
                    codeMirror.editor.setValue( fixed );
                    codeMirror.clearErrorLines();
                    codeMirror.showSaveError( wpslL10n.autofixApplied, false, true );
                } else if ( fixed && fixed.fixed && fixed.error ) {
                    // Partial fix — apply what was fixed and show remaining errors.
                    codeMirror.editor.setValue( fixed.fixed );
                    codeMirror.clearErrorLines();
                    codeMirror.showSaveError( wpslL10n.autofixPartial + ' ' + codeMirror.formatErrorList( fixed.error ), true );
                } else if ( fixed && fixed.error ) {
                    codeMirror.showSaveError( wpslL10n.autofixFailed + ' ' + codeMirror.formatErrorList( fixed.error ), false, true );
                } else {
                    // No fixable issue detected — run validation to get the current error.
                    const code = codeMirror.editor.getValue();
                    const allErrors = codeMirror.validateAll( code );
                    if ( allErrors.length ) {
                        codeMirror.showSaveError( wpslL10n.autofixFailed + ' ' + codeMirror.formatErrorList( allErrors ), false, true );
                    } else {
                        codeMirror.showSaveError( wpslL10n.autofixFailed, false, true );
                    }
                }
            });
            $error.append( $link );
        }

        jQuery( '.wpsl-section-actions' ).last().before( $error );

        if ( fadeAfter ) {
            setTimeout( function() {
                $error.fadeOut( 400, function() {
                    jQuery( this ).remove();
                });
            }, 3000 );
        }
    },

    /**
     * Run all validators and collect every error.
     *
     * @since   3.0.0
     * @param   {string} code The template code to validate
     * @returns {array} Array of error message strings (empty if valid)
     * @see     wpsl-template-validator.js
     */
    validateAll: function( code ) {
        return templateValidator.validateAll( code );
    },

    /**
     * Format an array of error messages as a numbered list.
     * If a single string is passed, return it as-is.
     *
     * @since   3.0.0
     * @param   {string|array} errors Error message(s)
     * @returns {string} Formatted error string
     */
    formatErrorList: function( errors ) {
        if ( typeof errors === 'string' ) {
            return errors;
        }

        return errors.map( function( err, idx ) {
            return '#' + ( idx + 1 ) + ') ' + err;
        } ).join( '\n' );
    },

    /**
     * Attach codeMirror related event handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindListener: function() {
        const self = this;

        // Load the custom section code, or restore the default code if no
        // entries exist in the database.
        jQuery( '#wpsl-load-section, #wpsl-restore-section' ).on( 'click', function() {
            jQuery( '.wpsl-save-error' ).remove();
            self.clearErrorLines();

            const $btn = jQuery( this );
            const requestAction = $btn.data( 'action' );
            const isRestore = ( requestAction === 'restore' );
            const args = {
                method: 'GET',
                type: requestAction,
                nonce: jQuery( '#wpsl-' + requestAction + '-section-nonce' ).val(),
                buttonFeedback: isRestore,
                skipFeedback: !isRestore,
                snackbarText: wpslL10n.sectionRestored,
            };

            // Loading a template includes the language when a multilingual
            // plugin is active.
            if ( $btn.attr( 'id' ) === 'wpsl-load-section' ) {
                args.lang = jQuery( '#wpsl-template-languages' ).val();
            }

            codeMirror.makeRequest( args );

            return false;
        });

        // Save the section code
        jQuery( '#wpsl-save-section' ).on( 'click', function() {
            const editor = codeMirror.editor;
            const code = editor.getValue();

            // An empty code field gets an overlay saying there's nothing to save.
            if ( ! code.length ) {
                codeMirror.showSaveError( wpslL10n.nothingToSave );

                return false;
            }

            // Run all validators and collect every error.
            const allErrors = codeMirror.validateAll( code );
            if ( allErrors.length ) {
                codeMirror.showSaveError( wpslL10n.brokenSyntax + '\n' + codeMirror.formatErrorList( allErrors ), true );

                return false;
            }

            const activate = jQuery( '#wpsl-activate-custom-section' ).is( ':checked' );
            const args = {
                method: 'POST',
                type: 'save',
                nonce: jQuery( '#wpsl-save-section-nonce' ).val(),
                code: code,
                activate: activate,
                buttonFeedback: true,
                snackbarText: activate ? wpslL10n.sectionUpdated : wpslL10n.sectionUpdatedInactive
            };

            if ( jQuery( '#wpsl-template-languages' ).length ) {
                args.lang = jQuery( '#wpsl-template-languages' ).val();
            }

            codeMirror.makeRequest( args );

            return false;
        });

        jQuery( '#wpsl-generate-field-code' ).on( 'click', function() {
            jQuery( '.wpsl-save-error' ).remove();

            jQuery( '#wpsl-generate-field-options' ).dialog({
                resizable: false,
                height: 'auto',
                width: 400,
                modal: true,
                closeOnEscape: true,
                closeText: '',
                dialogClass: 'wpsl-dialog wpsl-generate-field-dialog',
                classes: { 'ui-dialog': 'wpsl-dialog wpsl-generate-field-dialog' },
                open: function() {
                    // Replace the existing close button with our own one.
                    helpers.ui.dialog.addCloseButton( 'wpsl-generate-field-dialog' );

                    // Always bind the overlay click handler when dialog opens.
                    helpers.ui.dialog.bindCloseHandler( '#wpsl-generate-field-options', function() {
                        self.fieldCode.reset();
                    });

                    self.fieldCode.init();
                },
                close: function() {
                    // This dialog's handler only -- the shared selector would take
                    // every other dialog's close handler down with it.
                    helpers.ui.dialog.unbindCloseHandler( '#wpsl-generate-field-options' );
                },
                buttons: [
                    {
                        text: wpslL10n.showCode,
                        'id': 'wpsl-generate-code',
                        'class': 'button-primary',
                        click: function() {
                            self.fieldCode.generate();
                        }
                    },
                ],
            });

            return false;
        });

        jQuery( '#wpsl-generate-field-tags' ).on( 'change', function() {
            if ( jQuery( this ).val() === 'none' ) {
                jQuery( '.wpsl-generate-field-css' ).hide();
            } else {
                jQuery( '.wpsl-generate-field-css' ).show();
            }
        });
    },

    /**
     * AJAX request to load / update the template data.
     *
     * @since   3.0.0
     * @param   {object} args Request arguments
     * @returns {void}
     */
    makeRequest: function( args ) {
        const ajaxData = {
            action: 'wpsl_section_editor',
            wpsl_section_editor_nonce: args.nonce,
            template_action: args.type,
            template: jQuery( '#wpsl-template-list' ).val(),
            section: jQuery( '#wpsl-section-list' ).val(),
        };

        // Include the language when a multilingual plugin is active.
        if ( typeof args.lang === 'string' ) {
            ajaxData.lang = args.lang;
        }

        if ( typeof args.code !== 'undefined' ) {
            ajaxData.code = args.code;
        }

        if ( typeof args.activate !== 'undefined' ) {
            ajaxData.activate = args.activate;
        }

        // Make sure we target the correct text string.
        const loadText = ( ajaxData.template_action !== 'save' ) ? 'load' : 'save';

        // Button-level feedback for save/restore: change button text + wave animation.
        const $btn = args.type === 'save' ? jQuery( '#wpsl-save-section' ) : jQuery( '#wpsl-restore-section' );
        const originalVal = $btn.val();
        const savingText = args.type === 'save' ? wpslL10n.saveSection : wpslL10n.loadSection;

        const resetButton = function() {
            $btn.val( originalVal ).prop( 'disabled', false ).removeClass( 'wpsl-btn-saving' ).css( 'width', '' );
        };

        if ( args.buttonFeedback ) {
            $btn.css( 'width', $btn.outerWidth() + 'px' );
            $btn.val( savingText ).prop( 'disabled', true ).addClass( 'wpsl-btn-saving' );
        } else if ( ! args.skipFeedback ) {
            let feedbackArgs = {
                elem: jQuery( '.CodeMirror' ),
                text: wpslL10n[loadText + 'Section'] + '<span class="wpsl-feedback-dots"></span>'
            };

            feedbackOverlay.create( feedbackArgs );
        }

        jQuery.ajax({
            type: args.method,
            data: ajaxData,
            url: wpslSettings.ajaxurl,
            success: function( response ) {
                const editor = codeMirror.editor;

                if ( response.success ) {
                    jQuery( '.wpsl-save-error' ).remove();
                    codeMirror.clearErrorLines();
                    
                    if ( response.data && typeof response.data === 'object' ) {
                        let content = '';

                        if ( typeof response.data.html === 'string' ) {
                            content = response.data.html;
                        }

                        if ( typeof response.data.status === 'boolean' ) {
                            jQuery( '#wpsl-activate-custom-section' ).prop( 'checked', response.data.status );
                        }

                        // Set the returned code and adjust the height to fit.
                        editor.setValue( content );
                        editor.refresh();

                        // Make sure we have at least 5 lines.
                        codeMirror.setMinLines();
                    }

                    if ( args.buttonFeedback ) {
                        resetButton();

                        helpers.ui.snackbar( args.snackbarText );
                    } else if ( response.data && typeof response.data.missing_translation === 'boolean' ) {
                        let feedbackArgs = {
                            elem: jQuery( '.CodeMirror' ),
                            fadeOut: true,
                            text: wpslL10n.noLanguageDefaultTemplate,
                        };

                        feedbackOverlay.update( feedbackArgs );
                    } else {
                        feedbackOverlay.remove();
                    }
                } else {
                    // Don't clear the editor on save errors.
                    if ( args.type !== 'save' ) {
                        editor.setValue( '' );
                        codeMirror.setMinLines();
                    }

                    if ( args.buttonFeedback ) {
                        resetButton();
                    }

                    // Uncheck the activate checkbox if the server
                    // deactivated the section due to validation errors.
                    if ( args.type === 'save' ) {
                        jQuery( '#wpsl-activate-custom-section' ).prop( 'checked', false );
                    }

                    codeMirror.showSaveError( response.data );
                }
            },
        }).fail( function( response, textStatus, errorThrown ) {
            console.error( 'AJAX request failed:', textStatus, errorThrown );

            if ( args.buttonFeedback ) {
                resetButton();
            }

            feedbackOverlay.remove();
        });
    },

    /**
     * Handle the generation of code for custom fields.
     *
     * @since 3.0.0
     */
    fieldCode: {
        // Wrapper tags the generator may emit; any other value ( including
        // 'none' ) leaves the field code unwrapped.
        allowedTags: [ 'div', 'span', 'p', 'strong' ],

        /**
         * Initialize the copy button handler.
         *
         * @since   3.0.0
         * @returns {void}
         */
        init: function() {
            jQuery( '#wpsl-copy-code' ).off( 'click' ).on( 'click', ( e ) => {
                e.stopPropagation();
                this.copy();
            });
        },

        /**
         * Generate a template code snippet for a custom field, honouring the
         * selected field type, an optional wrapper element and any CSS ID or
         * class attributes.
         *
         * @since 3.0.0
         */
        generate: function() {
            const $cssIDField = jQuery( '#wpsl-generate-field-id' );
            const $cssClassField = jQuery( '#wpsl-generate-field-class' );
            const cssID = helpers.dom.sanitizeCssSelector( $cssIDField.val().trim() );
            const cssClass = helpers.dom.sanitizeCssSelector( $cssClassField.val().trim() );
            const $fieldTypeSelect = jQuery( '#wpsl-generate-field-types' );
            const fieldType = $fieldTypeSelect.find( 'option:selected' ).attr( 'data-type' );
            const fieldName = $fieldTypeSelect.val();
            const selectedTag = jQuery( '#wpsl-generate-field-tags' ).val();
            const code = codeMirror.defaultCode.replaceAll( 'field_type', fieldName );

            // Write back the id / class values with the spaces stripped out.
            $cssIDField.val( cssID );
            $cssClassField.val( cssClass );

            const newData = code.replace( /<%=([^%]+)%>/g, ( match, groupName ) => {
                let output = match;

                // Wrap email, tel and url fields in their respective formatter functions.
                if ( fieldType === 'email' ) {
                    output = '<%= formatEmail( ' + fieldName + ' ) %>';
                } else if ( fieldType === 'tel' ) {
                    output = '<%= formatPhoneNumber( ' + fieldName + ' ) %>';
                } else if ( fieldType === 'url' ) {
                    output = '<a href="<%= ' + fieldName + ' %>"><%= ' + fieldName + ' %></a>';
                }

                if ( this.allowedTags.includes( selectedTag ) ) {
                    const idAttr = ( cssID ) ? ' id="' + cssID + '"' : '';
                    const classAttr = ( cssClass ) ? ' class="' + cssClass + '"' : '';

                    return '<' + selectedTag + idAttr + classAttr + '>' + output + '</' + selectedTag + '>';
                }

                return output;
            });

            jQuery( '#wpsl-generate-code-preview' ).text( newData );
            jQuery( '.wpsl-code-container' ).show();

            // Only show the copy button if the Clipboard API is available
            if ( navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function' ) {
                jQuery( '#wpsl-copy-code' ).css( 'display', 'flex' );
            } else {
                jQuery( '#wpsl-copy-code' ).hide();
            }
        },
        /**
         * Copy the content of the code 
         * preview to the clipboard.
         * 
         * @since 3.0.0
         */
        copy: async function() {
            const copyButton = jQuery( '#wpsl-copy-code' );
            let originalSvg = copyButton.find( 'svg' ).first();

            // Swap in the helper's SVG, then re-fetch it after the replacement.
            if ( originalSvg.length ) {
                originalSvg.replaceWith( jQuery( helpers.ui.icons.copy() ) );

                originalSvg = copyButton.find( 'svg' ).first();
            }

            try {
                const code = jQuery( '#wpsl-generate-code-preview' ).text().trim();
                await navigator.clipboard.writeText( code );
                
                const svgContainer = originalSvg.parent();

                const successIcon = jQuery( helpers.ui.icons.copySuccess() );
                successIcon.css( 'opacity', 0 ); // Start hidden

                originalSvg.css( 'opacity', 1 ).animate( { opacity: 0 }, 200, function() {
                    originalSvg.replaceWith( successIcon );
                    successIcon.animate( { opacity: 1 }, 200 );

                    jQuery( '#wpsl-copy-code-wrap .wpsl-svg-tooltip' ).text( wpslL10n.copyCodeSuccess ).show();
                });
                
                // Restore original after 3 seconds with animation
                setTimeout( () => {
                    const currentIcon = svgContainer.find( 'svg' ).first();
                    originalSvg.css( 'opacity', 0 ); // Reset opacity for original
                    
                    currentIcon.animate( { opacity: 0 }, 200, function() {
                        currentIcon.replaceWith( originalSvg );
                        originalSvg.animate( { opacity: 1 }, 200 );

                        jQuery( '#wpsl-copy-code-wrap .wpsl-svg-tooltip' ).text( wpslL10n.copyCode );
                    });
                }, 3000 );

            } catch( err ) {
                console.error( 'Failed to copy:', err );
            }
        },
        /**
         * Reset the generate field options.
         * 
         * @since 3.0.0
         */
        reset: function() {
            jQuery( '#wpsl-generate-code-preview' ).text( '' );
            jQuery( '#wpsl-generate-field-options input[type=text]' ).val( '' );
    
            jQuery( '.wpsl-code-container' ).hide();

            jQuery( '#wpsl-generate-field-types' ).val( 'address' );
            jQuery( '#wpsl-generate-field-tags' ).val( 'none' );
        }
    }
};