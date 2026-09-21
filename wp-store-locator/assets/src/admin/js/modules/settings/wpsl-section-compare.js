import { codeMirror } from './wpsl-codemirror.js';
import { helpers } from '../wpsl-helpers.js';
import { formatting } from '../helpers/wpsl-formatting.js';

/**
 * Compare a custom template section with the markup the current settings
 * generate, and merge the missing features into the editor.
 *
 * The merge result only updates the CodeMirror editor — the user reviews
 * the code and saves it through the normal Save Section flow.
 *
 * @since 3.0.0
 */
export const sectionCompare = {
    findings: {},

    /**
     * Initialize the compare button and the auto-open deep link.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        if ( ! jQuery( '#wpsl-compare-section' ).length ) {
            return;
        }

        this.bindHandlers();
        this.bindVisibilityRefresh();
        this.maybeAutoOpen();
    },

    /**
     * Attach the compare button handler.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers: function() {
        const self = this;

        jQuery( '#wpsl-compare-section' ).on( 'click', function() {
            jQuery( '.wpsl-save-error' ).remove();
            self.compare();

            return false;
        });
    },

    /**
     * Auto-open the dialog when reached via the "Review differences" link
     * ( ?wpsl-compare=1#wpsl-section-editor ), after loading the active
     * template's section so the comparison matches the front-end.
     *
     * The param is also stripped from the hidden _wp_http_referer field,
     * not just the URL — otherwise it keeps resurfacing on saves from any
     * tab, since every tab shares one form and referer redirect.
     *
     * @since   3.0.0
     * @returns {void}
     */
    maybeAutoOpen: function() {
        const url = new URL( window.location.href );
        if ( ! url.searchParams.get( 'wpsl-compare' ) ) {
            return;
        }

        // One-time deep link, not page state — strip it so a reload doesn't
        // re-open the dialog once the section is back in sync.
        url.searchParams.delete( 'wpsl-compare' );
        window.history.replaceState( {}, '', url.toString() );

        // Scrub the hidden referer field too ( see the docblock ).
        jQuery( 'input[name="_wp_http_referer"]' ).each( function() {
            const referer = new URL( jQuery( this ).val(), window.location.origin );

            referer.searchParams.delete( 'wpsl-compare' );
            jQuery( this ).val( referer.pathname + referer.search );
        });

        if ( window.location.hash !== '#wpsl-section-editor' ) {
            return;
        }

        const self = this;

        const onComplete = function( event, xhr, settings ) {
            const request = ( settings ? ( settings.url || '' ) + ' ' + ( settings.data || '' ) : '' );
            if ( request.indexOf( 'wpsl_section_editor' ) === -1 || request.indexOf( 'template_action=load' ) === -1 ) {
                return;
            }

            jQuery( document ).off( 'ajaxComplete', onComplete );
            self.compare();
        };

        jQuery( document ).on( 'ajaxComplete', onComplete );

        jQuery( '#wpsl-load-section' ).trigger( 'click' );
    },

    /**
     * Show the Sync Settings button ( and its tooltip ) only when there's
     * something to sync. The editor's content only changes at a few known
     * points — Load, Restore Default, Save, Apply changes — so re-checking
     * after those beats checking on every keystroke.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindVisibilityRefresh: function() {
        const self = this;
        const refreshActions = [ 'template_action=load', 'template_action=restore', 'template_action=save' ];

        jQuery( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
            const request = ( settings ? ( settings.url || '' ) + ' ' + ( settings.data || '' ) : '' );
            if ( request.indexOf( 'wpsl_section_editor' ) === -1 ) {
                return;
            }

            const isRefreshAction = refreshActions.some( function( action ) {
                return request.indexOf( action ) !== -1;
            });

            if ( ! isRefreshAction ) {
                return;
            }

            self.refreshVisibility();
        });
    },

    /**
     * Silently re-check the editor's current code and update the Sync
     * Settings button's visibility, without opening the dialog.
     *
     * @since   3.0.0
     * @returns {void}
     */
    refreshVisibility: function() {
        this.fetchFindings();
    },

    /**
     * Request the findings for the editor's current code.
     *
     * @since   3.0.0
     * @returns {void}
     */
    compare: function() {
        this.fetchFindings( this.showDialog.bind( this ) );
    },

    /**
     * Shared ajax request for the editor's current code. Always updates
     * the Sync Settings button's visibility; callers can additionally
     * act on the findings ( e.g. open the dialog ) via onSuccess.
     *
     * @since   3.0.0
     * @param   {Function} [onSuccess] Optional callback, called with the response data.
     * @returns {void}
     */
    fetchFindings: function( onSuccess ) {
        const self = this;

        jQuery.ajax({
            type: 'POST',
            url: wpslSettings.ajaxurl,
            data: {
                action: 'wpsl_section_editor',
                wpsl_section_editor_nonce: jQuery( '#wpsl-compare-section-nonce' ).val(),
                template_action: 'compare',
                template: jQuery( '#wpsl-template-list' ).val(),
                section: jQuery( '#wpsl-section-list' ).val(),
                code: codeMirror.editor.getValue(),

                // Which language is loaded, so the response can list the
                // *other* stored languages that are still out of sync.
                lang: self.currentLanguage(),
            },
            success: function( response ) {
                if ( response.success ) {
                    self.updateVisibility( response.data );

                    if ( onSuccess ) {
                        onSuccess( response.data );
                    }
                } else {
                    codeMirror.showSaveError( response.data );
                }
            },
        }).fail( function( response, textStatus, errorThrown ) {
            console.error( 'AJAX request failed:', textStatus, errorThrown );
        });
    },

    /**
     * Show or hide the Sync Settings button + tooltip based on whether
     * the last check found anything to sync.
     *
     * @since   3.0.0
     * @param   {object} data The compare response data
     * @returns {void}
     */
    updateVisibility: function( data ) {
        const hasFindings = ! data.empty && data.findings && data.findings.length > 0;
        jQuery( '#wpsl-compare-section-wrap' ).toggleClass( 'wpsl-hide', ! hasFindings );
    },

    /**
     * Open the findings dialog.
     *
     * @since   3.0.0
     * @param   {object} data The compare response data
     * @returns {void}
     */
    showDialog: function( data ) {
        const self = this;

        jQuery( '#wpsl-compare-dialog' ).html( this.renderFindings( data ) ).dialog({
            resizable: false,
            height: 'auto',
            maxHeight: 550,
            width: 550,
            modal: true,
            closeOnEscape: true,
            closeText: '',
            title: wpslL10n.compareTitle,
            dialogClass: 'wpsl-dialog wpsl-compare-dialog',
            classes: { 'ui-dialog': 'wpsl-dialog wpsl-compare-dialog' },
            open: function() {
                helpers.ui.dialog.addCloseButton( 'wpsl-compare-dialog' );
                helpers.ui.dialog.bindCloseHandler( '#wpsl-compare-dialog', function() {} );

                const $dialog = jQuery( this );

                wpslSharedFuncs.bindInfoPopup( $dialog.find( '.wpsl-info' ) );

                // The auto-open deep link opens this dialog while the page is
                // still settling ( tab switch, CodeMirror refresh ), so jQuery
                // UI's initial center is stale. Recenter on the next tick.
                setTimeout( function() {
                    $dialog.dialog( 'option', 'position', { my: 'center', at: 'center', of: window } );
                }, 0 );
            },
            close: function() {
                // This dialog's handler only -- the shared selector would take
                // every other dialog's close handler down with it.
                helpers.ui.dialog.unbindCloseHandler( '#wpsl-compare-dialog' );
            },
            buttons: self.dialogButtons( data ),
        });
    },

    /**
     * Build the findings list as a collapsed-by-default accordion, one
     * <details> per finding, so a long list stays scannable instead of
     * pushing the dialog to its max height immediately.
     *
     * All user-facing values go in with .text(), never parsed as HTML.
     *
     * @since   3.0.0
     * @param   {object} data The compare response data
     * @returns {object} The jQuery element with the rendered findings
     */
    renderFindings: function( data ) {
        const self = this;
        const $wrap = jQuery( '<div class="wpsl-compare-findings"></div>' );

        this.findings = {};

        if ( data.empty ) {
            return $wrap.append( jQuery( '<p></p>' ).text( wpslL10n.compareEmpty ) );
        }

        if ( ! data.findings.length ) {
            return $wrap.append( jQuery( '<p></p>' ).text( wpslL10n.compareInSync ) );
        }

        data.findings.forEach( function( finding ) {
            self.findings[ finding.id ] = finding;

            const $item    = jQuery( '<details class="wpsl-compare-item"></details>' );
            const $summary = jQuery( '<summary class="wpsl-compare-item-head"></summary>' );
            const $label   = jQuery( '<span class="wpsl-compare-item-label"></span>' );

            $label.append( jQuery( '<strong></strong>' ).text( finding.label ) );

            if ( finding.status === 'mergeable' ) {
                const lineText = finding.insert_line
                    ? wpslL10n.compareInsertAfter.replace( '%s', finding.insert_line )
                    : wpslL10n.compareModifies;

                $label.append( jQuery( '<span class="wpsl-compare-line"></span>' ).text( ' — ' + lineText ) );
            } else if ( finding.status === 'info' ) {
                // Leftover markup for a now-disabled option. Apply changes
                // can't remove it, so flag that up front with a tooltip.
                $label.append( jQuery( '<span class="wpsl-compare-line wpsl-compare-line-manual"></span>' ).text( ' — ' + wpslL10n.compareRemoveManually ) );

                const $info = jQuery( '<span class="wpsl-info"></span>' );
                $info.append( jQuery( '<span class="wpsl-info-text wpsl-hide"></span>' ).text( wpslL10n.compareManualEditInfo ) );
                $label.append( $info );
            }

            $summary.append( $label );
            $item.append( $summary );

            const $body = jQuery( '<div class="wpsl-compare-item-body"></div>' );

            if ( finding.message ) {
                $body.append( jQuery( '<p class="wpsl-compare-message"></p>' ).text( finding.message ) );
            }

            if ( finding.status === 'manual' ) {
                $body.append( jQuery( '<p class="wpsl-compare-message"></p>' ).text( wpslL10n.compareManual ) );
            }

            // Reverse/info findings point at the exact lines to delete, since
            // Apply changes can't remove them. When the matched line opens a
            // multi-line block, line_end marks where it closes.
            if ( finding.lines && finding.lines.length ) {
                let lineText;

                if ( finding.line_end && finding.lines.length === 1 ) {
                    lineText = wpslL10n.compareFoundOnLineRange
                        .replace( '%1$s', finding.lines[ 0 ] )
                        .replace( '%2$s', finding.line_end );
                } else {
                    lineText = wpslL10n.compareFoundOnLines + ' ' + finding.lines.join( ', ' );

                    if ( finding.lines_truncated ) {
                        lineText += ' ' + wpslL10n.compareAndMoreLines.replace( '%s', finding.lines_truncated );
                    }
                }

                $body.append( jQuery( '<p class="wpsl-compare-message wpsl-compare-lines"></p>' ).text( lineText ) );
            }

            // Preview only: the snippet keeps its source template's indentation,
            // which reads as a stray gap here. Apply changes inserts the original,
            // indented to match the editor.
            if ( finding.snippet ) {
                $body.append( jQuery( '<pre class="wpsl-compare-snippet"></pre>' ).text( formatting.dedent( finding.snippet ) ) );
            }

            $item.append( $body );
            $wrap.append( $item );
        });

        /**
         * Everything above describes only the editor's language. Without naming
         * the other stale ones, the dialog reads as though fixing it here fixes
         * the site.
         */
        const staleLanguages = this.staleLanguages( data );
        if ( staleLanguages.length ) {
            const names = staleLanguages.map( function( row ) {
                return row.name || row.language;
            });

            $wrap.append(
                jQuery( '<p class="wpsl-compare-message wpsl-compare-languages"></p>' )
                    .text( wpslL10n.compareOtherLanguages.replace( '%s', names.join( ', ' ) ) )
            );
        }

        return $wrap;
    },

    /**
     * The dialog buttons: Apply changes ( only when something is
     * mergeable ) and Close.
     *
     * @since   3.0.0
     * @param   {object} data The compare response data
     * @returns {array}
     */
    dialogButtons: function( data ) {
        const self = this;
        const buttons = [
            {
                text: wpslL10n.close,
                'class': 'button-secondary',
                click: function() {
                    jQuery( this ).dialog( 'close' );
                }
            }
        ];

        const hasMergeable = ( data.findings || [] ).some( function( finding ) {
            return finding.status === 'mergeable';
        });

        const staleLanguages = this.staleLanguages( data );

        /**
         * Applying to other languages saves them outright, so it only makes
         * sense with a mergeable finding and other stale languages to save.
         */
        if ( hasMergeable && staleLanguages.length ) {
            buttons.unshift({
                text: wpslL10n.compareApplyAllLanguages.replace( '%s', staleLanguages.length ),
                'class': 'button-secondary',
                click: function() {
                    const $dialog = jQuery( this );

                    self.applyToOtherLanguages( $dialog );
                    self.applyFindings( $dialog );
                }
            });
        }

        if ( hasMergeable ) {
            buttons.unshift({
                text: wpslL10n.compareApply,
                'class': 'button-primary',
                click: function() {
                    self.applyFindings( jQuery( this ) );
                }
            });
        }

        return buttons;
    },

    /**
     * The language currently selected in the editor, or '' when no
     * multilingual plugin is active and the dropdown isn't rendered.
     *
     * @since 3.0.0
     * @returns {string}
     */
    currentLanguage: function() {
        const $languages = jQuery( '#wpsl-template-languages' );

        return $languages.length ? $languages.val() : '';
    },

    /**
     * The other stored languages that are also out of sync, as reported
     * by the last compare.
     *
     * @since 3.0.0
     * @param   {object} data The compare response data
     * @returns {array}
     */
    staleLanguages: function( data ) {
        return ( data && data.languages ) ? data.languages : [];
    },

    /**
     * Merge into every other language's stored section and save them.
     * Unlike Apply changes, these go straight to the database. Each is merged
     * independently server-side, so a hand-edited language keeps its structure.
     *
     * @since 3.0.0
     * @param   {object} $dialog The dialog content element
     * @returns {void}
     */
    applyToOtherLanguages: function( $dialog ) {
        const self = this;
        const features = Object.keys( this.findings ).filter( function( id ) {
            return self.findings[ id ].status === 'mergeable';
        });

        if ( ! features.length ) {
            return;
        }

        jQuery.ajax({
            type: 'POST',
            url: wpslSettings.ajaxurl,
            data: {
                action: 'wpsl_section_editor',
                wpsl_section_editor_nonce: jQuery( '#wpsl-merge-languages-section-nonce' ).val(),
                template_action: 'merge_languages',
                template: jQuery( '#wpsl-template-list' ).val(),
                section: jQuery( '#wpsl-section-list' ).val(),
                lang: self.currentLanguage(),
                features: features,
            },
            success: function( response ) {
                if ( ! response.success ) {
                    codeMirror.showSaveError( response.data );
                    return;
                }

                const results = response.data.results || [];
                const updated = results.filter( function( row ) {
                    return row.status === 'updated';
                });

                const failed = results.filter( function( row ) {
                    return row.status === 'error';
                });

                if ( updated.length ) {
                    const names = updated.map( function( row ) {
                        return row.name || row.language;
                    });

                    helpers.ui.snackbar( wpslL10n.compareLanguagesUpdated.replace( '%s', names.join( ', ' ) ) );
                }

                // A merge that failed validation left the language untouched
                // rather than saved broken — say which, they aren't visible here.
                if ( failed.length ) {
                    const names = failed.map( function( row ) {
                        return row.name || row.language;
                    });

                    codeMirror.showSaveError( wpslL10n.compareLanguagesFailed.replace( '%s', names.join( ', ' ) ), false, true );
                }

                if ( ! updated.length && ! failed.length ) {
                    helpers.ui.snackbar( wpslL10n.compareLanguagesUnchanged );
                }
            },
        }).fail( function( response, textStatus, errorThrown ) {
            console.error( 'AJAX request failed:', textStatus, errorThrown );
        });
    },

    /**
     * Merge all mergeable features and put the result in the editor.
     *
     * @since   3.0.0
     * @param   {object} $dialog The dialog content element
     * @returns {void}
     */
    applyFindings: function( $dialog ) {
        const self = this;
        const features = Object.keys( this.findings ).filter( function( id ) {
            return self.findings[ id ].status === 'mergeable';
        });

        if ( ! features.length ) {
            $dialog.dialog( 'close' );

            return;
        }

        jQuery.ajax({
            type: 'POST',
            url: wpslSettings.ajaxurl,
            data: {
                action: 'wpsl_section_editor',
                wpsl_section_editor_nonce: jQuery( '#wpsl-merge-section-nonce' ).val(),
                template_action: 'merge',
                template: jQuery( '#wpsl-template-list' ).val(),
                section: jQuery( '#wpsl-section-list' ).val(),
                code: codeMirror.editor.getValue(),
                features: features,
            },
            success: function( response ) {
                $dialog.dialog( 'close' );

                if ( ! response.success ) {
                    codeMirror.showSaveError( response.data );
                    return;
                }

                codeMirror.editor.setValue( response.data.html );
                codeMirror.editor.refresh();

                helpers.ui.snackbar( wpslL10n.sectionsMerged );

                // Reverse/info findings survive the merge, so re-check rather
                // than assume the button can now hide.
                self.refreshVisibility();

                if ( response.data.skipped && response.data.skipped.length ) {
                    const labels = response.data.skipped.map( function( id ) {
                        return self.findings[ id ] ? self.findings[ id ].label : id;
                    });

                    codeMirror.showSaveError( wpslL10n.compareSkipped.replace( '%s', labels.join( ', ' ) ), false, true );
                }
            },
        }).fail( function( response, textStatus, errorThrown ) {
            console.error( 'AJAX request failed:', textStatus, errorThrown );
        });
    }
};