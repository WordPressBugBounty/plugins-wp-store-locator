import { preloader } from '../wpsl-preloader.js';

/**
 * Convert the 1.x opening hours of existing locations to the dropdown format.
 *
 * Opens a dialog listing every location that still holds free-form text, so the
 * user can review them before anything is rewritten. Schedules are only fetched
 * when a row is opened - a locator can hold thousands of locations, so loading
 * every before / after up front would be a payload nobody reads.
 *
 * @since 3.0.0
 */
export const hoursConverter = {

    /**
     * Locations reported as unreadable, passed back on every batch so the
     * server steps over them instead of handing them out again.
     *
     * @since 3.0.0
     */
    skipped: 0,

    /**
     * How many locations the conversion started with, for the progress bar.
     *
     * @since 3.0.0
     */
    total: 0,

    /**
     * Running count of locations actually rewritten, summed over the batches.
     *
     * @since 3.0.0
     */
    converted: 0,

    /**
     * The nonce, read off the Tools tab button.
     *
     * @since 3.0.0
     */
    nonce: '',

    /**
     * Bind the button on the Tools tab.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        const $button = jQuery( '#wpsl-convert-hours' );
        if ( ! $button.length ) {
            return;
        }

        this.nonce = $button.data( 'nonce' );

        $button.on( 'click', function() {
            hoursConverter.openDialog();

            return false;
        });

        this.bindList();
    },

    /**
     * Post to the converter endpoint.
     *
     * @since   3.0.0
     * @param   {Object} data Extra POST fields
     * @returns {jQuery.jqXHR}
     */
    request: function( data ) {
        return jQuery.post( wpslSettings.ajaxurl, jQuery.extend( {
            action: 'wpsl_convert_hours',
            nonce: hoursConverter.nonce
        }, data ) );
    },

    /**
     * Open the dialog and load the list of locations.
     *
     * @since   3.0.0
     * @returns {void}
     */
    openDialog: function() {
        const $dialog = jQuery( '#wpsl-hours-converter' );
        $dialog.dialog({
            resizable: false,
            height: 'auto',
            width: 800,
            maxHeight: jQuery( window ).height() - 80,
            modal: true,
            closeOnEscape: true,
            closeText: '',
            dialogClass: 'wpsl-dialog wpsl-hours-converter-dialog',
            classes: { 'ui-dialog': 'wpsl-dialog wpsl-hours-converter-dialog' },
            appendTo: '#wpbody-content',
            position: { my: 'center', at: 'center', of: '#wpbody-content' },
            open: function() {
                jQuery( '.ui-widget-overlay' ).off( 'click.wpslHours' ).on( 'click.wpslHours', function() {
                    $dialog.dialog( 'close' );
                });

                hoursConverter.reset();
                hoursConverter.loadList();
            },
            close: function() {
                jQuery( '.ui-widget-overlay' ).off( 'click.wpslHours' );
            }
        });
    },

    /**
     * Put the dialog back to its opening state.
     *
     * @since   3.0.0
     * @returns {void}
     */
    reset: function() {
        this.skipped = 0;
        this.total = 0;

        jQuery( '.wpsl-hours-converter-list' ).empty();
        jQuery( '.wpsl-hours-converter-summary' ).empty();
        jQuery( '.wpsl-hours-converter-error, .wpsl-hours-converter-empty, .wpsl-hours-converter-progress, .wpsl-hours-converter-done' ).addClass( 'wpsl-hide' );
        jQuery( '.wpsl-hours-converter-bulk' ).removeClass( 'wpsl-hide' );
        jQuery( '#wpsl-convert-all' ).removeClass( 'disabled' );
    },

    /**
     * Fetch and render the list of locations.
     *
     * @since   3.0.0
     * @returns {void}
     */
    loadList: function() {
        const $list = jQuery( '.wpsl-hours-converter-list' );
        $list.html( '<p class="wpsl-hours-converter-loading">' + wpslL10n.hoursConvertLoading + '</p>' );

        this.request( { mode: 'list' } ).done( function( response ) {
            if ( ! response.success ) {
                hoursConverter.showError( response.data );
                return;
            }

            hoursConverter.renderList( response.data );
        }).fail( function() {
            hoursConverter.showError();
        });
    },

    /**
     * Render the summary line and one row per location.
     *
     * @since   3.0.0
     * @param   {Object} data The list response
     * @returns {void}
     */
    renderList: function( data ) {
        const $list = jQuery( '.wpsl-hours-converter-list' ).empty();

        hoursConverter.total = data.convertible;

        if ( ! data.total ) {
            jQuery( '.wpsl-hours-converter-empty' ).removeClass( 'wpsl-hide' );
            jQuery( '.wpsl-hours-converter-bulk' ).addClass( 'wpsl-hide' );

            return;
        }

        jQuery( '.wpsl-hours-converter-summary' ).text(
            wpslL10n.hoursConvertSummary
                .replace( '%1$s', data.convertible )
                .replace( '%2$s', data.total )
        );

        if ( ! data.convertible ) {
            jQuery( '#wpsl-convert-all' ).addClass( 'disabled' );
        }

        data.locations.forEach( function( location ) {
            $list.append( hoursConverter.row( location ) );
        });
    },

    /**
     * Build one collapsed location row.
     *
     * @since   3.0.0
     * @param   {Object} location The location summary
     * @returns {jQuery}          The row markup
     */
    row: function( location ) {
        const state = location.convertible ? 'convertible' : 'skipped';

        const $row = jQuery( '<div class="wpsl-hours-converter-row" role="listitem" />' )
            .addClass( 'wpsl-hours-' + state )
            .attr( 'data-id', location.id );

        const $header = jQuery( '<button type="button" class="wpsl-hours-converter-toggle" aria-expanded="false" />' );
        $header.append( jQuery( '<span class="wpsl-hours-converter-number" />' ).text( location.number ) );

        const $title = jQuery( '<span class="wpsl-hours-converter-title" />' );
        $title.append( jQuery( '<strong />' ).text( location.store || wpslL10n.hoursConvertUntitled ) );

        if ( location.address ) {
            $title.append( jQuery( '<span class="wpsl-hours-converter-address" />' ).text( location.address ) );
        }

        $header.append( $title );
        $header.append(
            jQuery( '<span class="wpsl-hours-converter-state" />' ).text(
                location.convertible ? wpslL10n.hoursConvertStateReady : wpslL10n.hoursConvertStateKeep
            )
        );

        $row.append( $header );
        $row.append( '<div class="wpsl-hours-converter-detail wpsl-hide"></div>' );

        return $row;
    },

    /**
     * Expand / collapse a row, loading its schedule the first time.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindList: function() {
        jQuery( document ).on( 'click', '.wpsl-hours-converter-toggle', function() {
            const $header = jQuery( this );
            const $row = $header.closest( '.wpsl-hours-converter-row' );
            const $detail = $row.find( '.wpsl-hours-converter-detail' );
            const expanded = $header.attr( 'aria-expanded' ) === 'true';

            $header.attr( 'aria-expanded', expanded ? 'false' : 'true' );
            $detail.toggleClass( 'wpsl-hide', expanded );

            if ( expanded || $row.data( 'loaded' ) ) {
                return false;
            }

            $row.data( 'loaded', true );
            $detail.html( '<p class="wpsl-hours-converter-loading">' + wpslL10n.hoursConvertLoading + '</p>' );

            hoursConverter.request( { mode: 'detail', id: $row.data( 'id' ) } ).done( function( response ) {
                if ( ! response.success ) {
                    $detail.html( jQuery( '<p class="wpsl-error-text" />' ).text( wpslL10n.hoursConvertFailed ) );
                    return;
                }

                $detail.empty().append( hoursConverter.detail( response.data ) );
            }).fail( function() {
                $row.data( 'loaded', false );
                $detail.html( jQuery( '<p class="wpsl-error-text" />' ).text( wpslL10n.hoursConvertFailed ) );
            });

            return false;
        });

        jQuery( document ).on( 'click', '#wpsl-convert-all', function() {
            if ( jQuery( this ).hasClass( 'disabled' ) ) {
                return false;
            }

            hoursConverter.startConversion();

            return false;
        });

        jQuery( document ).on( 'click', '#wpsl-hours-converter-close', function() {
            jQuery( '#wpsl-hours-converter' ).dialog( 'close' );

            return false;
        });
    },

    /**
     * Build the expanded before / after ( or the reason it is left alone ).
     *
     * @since   3.0.0
     * @param   {Object} data The detail response
     * @returns {jQuery}      The detail markup
     */
    detail: function( data ) {
        const $detail = jQuery( '<div />' );
        $detail.append( jQuery( '<span class="wpsl-hours-converter-label" />' ).text( wpslL10n.hoursConvertBefore ) );
        $detail.append( jQuery( '<pre />' ).text( data.before ) );

        if ( data.after ) {
            $detail.append( jQuery( '<span class="wpsl-hours-converter-label" />' ).text( wpslL10n.hoursConvertAfter ) );
            $detail.append( jQuery( '<pre />' ).text( data.after ) );
        } else {
            $detail.append( jQuery( '<em />' ).text( hoursConverter.reasonText( data.reason ) ) );
        }

        return $detail;
    },

    /**
     * Explain why one location is left alone.
     *
     * @since   3.0.0
     * @param   {string} reason The reason code from the parser
     * @returns {string}        The message to show
     */
    reasonText: function( reason ) {
        const messages = {
            prose: wpslL10n.hoursConvertReasonProse,
            ambiguous: wpslL10n.hoursConvertReasonAmbiguous,
            extra: wpslL10n.hoursConvertReasonExtra,
            time: wpslL10n.hoursConvertReasonTime,
            missing: wpslL10n.hoursConvertReasonMissing
        };

        return messages[ reason ] || wpslL10n.hoursConvertReasonProse;
    },

    /**
     * Start converting, showing the progress bar.
     *
     * @since   3.0.0
     * @returns {void}
     */
    startConversion: function() {
        this.skipped = 0;
        this.converted = 0;

        jQuery( '#wpsl-convert-all' ).addClass( 'disabled' );
        jQuery( '.wpsl-hours-converter-progress' ).removeClass( 'wpsl-hide' );

        this.updateProgress( 0 );
        this.convert();
    },

    /**
     * Convert one batch, then keep going until the server says it is done.
     *
     * @since   3.0.0
     * @returns {void}
     */
    convert: function() {
        this.request( { mode: 'convert', skipped: this.skipped } ).done( function( response ) {
            if ( ! response.success ) {
                hoursConverter.showError( response.data );
                return;
            }

            hoursConverter.skipped = response.data.skipped;
            hoursConverter.converted += response.data.converted;

            const done = hoursConverter.total - response.data.remaining;

            hoursConverter.updateProgress( done );

            if ( ! response.data.done ) {
                hoursConverter.convert();

                return;
            }

            hoursConverter.showResult( response.data );
        }).fail( function() {
            hoursConverter.showError();
        });
    },

    /**
     * Move the progress bar along.
     *
     * @since   3.0.0
     * @param   {number} done How many locations have been handled
     * @returns {void}
     */
    updateProgress: function( done ) {
        const total = this.total || 1;
        const percentage = Math.min( Math.round( ( done / total ) * 100 ), 100 );

        jQuery( '.wpsl-hours-converter-progress .wpsl-progress-fill' ).css( 'width', percentage + '%' );
        jQuery( '.wpsl-hours-converter-progress .wpsl-progress-label' ).text(
            wpslL10n.hoursConvertProgress
                .replace( '%1$s', done )
                .replace( '%2$s', this.total ) + ' (' + percentage + '%)'
        );
    },

    /**
     * Report the outcome and mark the rows that were converted.
     *
     * @since   3.0.0
     * @param   {Object} data The final batch response
     * @returns {void}
     */
    showResult: function( data ) {
        jQuery( '.wpsl-hours-converter-summary' ).text(
            wpslL10n.hoursConvertDone.replace( '%s', this.converted )
        );

        if ( data.skipped ) {
            jQuery( '.wpsl-hours-converter-summary' ).append(
                jQuery( '<span class="wpsl-hours-converter-skipped" />' ).text(
                    ' ' + wpslL10n.hoursConvertLeft.replace( '%s', data.skipped )
                )
            );
        }

        if ( data.switched ) {
            jQuery( '.wpsl-hours-converter-summary' ).append(
                jQuery( '<span />' ).text( ' ' + wpslL10n.hoursConvertSwitched )
            );
        }

        // The converted rows no longer hold text, so drop them from the list.
        jQuery( '.wpsl-hours-converter-row.wpsl-hours-convertible' ).remove();

        // Nothing left to convert.
        jQuery( '.wpsl-hours-converter-progress, .wpsl-hours-converter-bulk' ).addClass( 'wpsl-hide' );
        jQuery( '.wpsl-hours-converter-done' ).removeClass( 'wpsl-hide' );

        // No skipped locations means no 1.x text remains, so drop the Tools
        // row now rather than on the next page load.
        if ( ! data.skipped ) {
            jQuery( '#wpsl-convert-hours' ).closest( 'p' ).remove();
        }
    },

    /**
     * Show an error inside the dialog.
     *
     * @since   3.0.0
     * @param   {Object} [data] The error payload returned by the server
     * @returns {void}
     */
    showError: function( data ) {
        preloader.remove();

        jQuery( '.wpsl-hours-converter-progress' ).addClass( 'wpsl-hide' );
        jQuery( '.wpsl-hours-converter-loading' ).remove();

        jQuery( '.wpsl-hours-converter-error' )
            .removeClass( 'wpsl-hide' )
            .text( ( data && data.message ) ? data.message : wpslL10n.hoursConvertFailed );
    }
};