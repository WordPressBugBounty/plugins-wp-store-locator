/**
 * Re-geocode locations from the store list.
 *
 * @since 3.0.0
 */
( function( $ ) {
    'use strict';

    if ( typeof wpslGeocodeL10n === 'undefined' ) {
        return;
    }

    const geocodeLocations = {
        queue: [],
        total: 0,
        fixed: 0,
        repaired: 0,
        last: null,
        failures: [],
        running: false,
        cancelled: false,
        startTime: 0,
        timer: null,
        request: null,
        run: 0,

        /**
         * Bind the row links and the bulk action.
         *
         * @returns {void}
         */
        init: function() {
            if ( ! $( '#wpsl-geocode-locations' ).length ) {
                return;
            }

            $( document ).on( 'click', '.wpsl-geocode-fix', function() {
                geocodeLocations.open( [ parseInt( $( this ).data( 'id' ), 10 ) ] );

                return false;
            });

            geocodeLocations.bindTooltips();

            $( '#doaction, #doaction2' ).on( 'click', function( event ) {
                const suffix = ( 'doaction2' === this.id ) ? '-bottom' : '-top';

                if ( $( '#bulk-action-selector' + suffix ).val() !== 'wpsl_geocode' ) {
                    return;
                }

                event.preventDefault();

                const ids = $( 'input[name="post[]"]:checked' ).map( function() {
                    return parseInt( this.value, 10 );
                }).get();

                if ( ! ids.length ) {
                    window.alert( wpslGeocodeL10n.noneSelected );

                    return;
                }

                geocodeLocations.open( ids );
            });

            // Unlike the bulk action, this covers locations on every page.
            $( '#wpsl-geocode-all' ).on( 'click', function() {
                const $button = $( this );

                $button.addClass( 'disabled' );

                $.post( wpslGeocodeL10n.ajaxurl, {
                    action: 'wpsl_geocode_locations',
                    nonce: $( '#wpsl-geocode-nonce' ).val(),
                    mode: 'list'
                }).done( function( response ) {
                    $button.removeClass( 'disabled' );

                    if ( ! response.success || ! response.data.ids.length ) {
                        window.alert( wpslGeocodeL10n.noneLeft );

                        return;
                    }

                    geocodeLocations.open( response.data.ids );
                }).fail( function() {
                    $button.removeClass( 'disabled' );
                    window.alert( wpslGeocodeL10n.requestFailed );
                });

                return false;
            });

            $( '#wpsl-geocode-start' ).on( 'click', function() {
                geocodeLocations.start();

                return false;
            });

            // Cancel lets the in-flight request land and stops before the next.
            $( '#wpsl-geocode-locations' ).on( 'click', '.wpsl-geocode-cancel', function() {
                const $button = $( this );

                if ( geocodeLocations.cancelled || ! window.confirm( wpslGeocodeL10n.cancelConfirm ) ) {
                    return false;
                }

                geocodeLocations.cancelled = true;

                $button.prop( 'disabled', true ).text( wpslGeocodeL10n.cancelling );

                return false;
            });

            /*
             * The column and the view count are both rendered server-side, so
             * the page has to come back to show what changed.
             */
            $( '#wpsl-geocode-close' ).on( 'click', function() {
                window.location.reload();

                return false;
            });
        },

        /**
         * Swap jQuery UI's close button for the one the other WPSL dialogs use.
         *
         * @returns {void}
         */
        addCloseButton: function() {
            const $container = $( '.ui-dialog.wpsl-geocode-locations-dialog' );

            if ( ! $container.find( '.wpsl-close-cross' ).length ) {
                $container.find( '.ui-dialog-titlebar-close' ).remove();
                $container.find( '.ui-dialog-titlebar' ).append(
                    '<button type="button" class="wpsl-close-cross wpsl-dialog-close">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">' +
                    '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path>' +
                    '</svg></button>'
                );
            }

            $( document ).off( '.wpslGeocodeClose' ).on(
                'click.wpslGeocodeClose',
                '.ui-widget-overlay, .wpsl-geocode-locations-dialog .wpsl-dialog-close',
                function() {
                    $( '#wpsl-geocode-locations' ).dialog( 'close' );
                }
            );
        },

        /**
         * Hand the column's warning icons to the shared tooltip.
         *
         * @returns {void}
         */
        bindTooltips: function() {
            if ( ! window.wpslSharedFuncs || 'function' !== typeof window.wpslSharedFuncs.bindInfoPopup ) {
                return;
            }

            window.wpslSharedFuncs.bindInfoPopup( $( '#the-list .wpsl-coordinates .wpsl-info' ) );
        },

        /**
         * Open the dialog for a set of locations.
         *
         * @param   {Array} ids The location IDs to geocode
         * @returns {void}
         */
        open: function( ids ) {
            const $dialog = $( '#wpsl-geocode-locations' );

            // Every lookup would fail on the key, so say so instead of starting a run.
            if ( wpslGeocodeL10n.blocked ) {
                window.alert( wpslGeocodeL10n.blocked );

                return;
            }

            this.reset( ids );

            $dialog.dialog({
                resizable: false,
                height: 'auto',
                width: 600,
                maxHeight: $( window ).height() - 80,
                modal: true,
                closeOnEscape: true,
                closeText: '',
                dialogClass: 'wpsl-dialog wpsl-geocode-locations-dialog',
                classes: { 'ui-dialog': 'wpsl-dialog wpsl-geocode-locations-dialog' },
                appendTo: '#wpbody-content',
                position: { my: 'center', at: 'center', of: window },
                open: function() {
                    geocodeLocations.addCloseButton();
                },
                close: function() {
                    $( document ).off( '.wpslGeocodeClose' );

                    geocodeLocations.cancelled = true;
                    geocodeLocations.stopTimer();

                    if ( geocodeLocations.request && 'function' === typeof geocodeLocations.request.abort ) {
                        geocodeLocations.request.abort();
                        geocodeLocations.request = null;
                    }

                    if ( geocodeLocations.fixed || geocodeLocations.running ) {
                        window.location.reload();
                    }
                }
            });
        },

        /**
         * Put the dialog back to its opening state.
         *
         * @param   {Array} ids The location IDs to geocode
         * @returns {void}
         */
        reset: function( ids ) {
            this.queue     = ids.slice();
            this.total     = ids.length;
            this.fixed     = 0;
            this.repaired  = 0;
            this.last      = null;
            this.failures  = [];
            this.running   = false;
            this.cancelled = false;
            this.request   = null;
            this.run++;

            this.stopTimer();

            $( '.wpsl-geocode-status' ).text( '' );
            $( '.wpsl-geocode-timer strong' ).text( '0s' );
            $( '.wpsl-geocode-cancel' ).prop( 'disabled', false ).text( wpslGeocodeL10n.cancel ).removeClass( 'wpsl-hide' );

            $( '.wpsl-geocode-summary' ).text(
                ( 1 === this.total )
                    ? wpslGeocodeL10n.summarySingle
                    : wpslGeocodeL10n.summaryPlural.replace( '%d', this.total )
            );

            $( '.wpsl-geocode-intro' ).removeClass( 'wpsl-hide' );
            $( '.wpsl-geocode-progress, .wpsl-geocode-failures, .wpsl-geocode-error' ).addClass( 'wpsl-hide' );
            $( '.wpsl-geocode-result' ).addClass( 'wpsl-hide' ).empty();
            $( '#wpsl-geocode-failure-log' ).val( '' );
            $( '#wpsl-geocode-start' ).removeClass( 'wpsl-hide' );
            $( '#wpsl-geocode-close' ).addClass( 'wpsl-hide' );

            this.updateProgress( 0 );
        },

        /**
         * Begin the run.
         *
         * @returns {void}
         */
        start: function() {
            if ( this.running || ! this.queue.length ) {
                return;
            }

            this.running = true;

            $( '#wpsl-geocode-start' ).addClass( 'wpsl-hide' );

            // "10 locations will be geocoded" stops being true the moment it starts.
            $( '.wpsl-geocode-intro' ).addClass( 'wpsl-hide' );
            $( '.wpsl-geocode-progress' ).removeClass( 'wpsl-hide' );

            this.startTimer();
            this.next();
        },

        /**
         * Start the elapsed-time ticker.
         *
         * @returns {void}
         */
        startTimer: function() {
            this.startTime = Date.now();

            this.timer = window.setInterval( function() {
                $( '.wpsl-geocode-timer strong' ).text( geocodeLocations.elapsed() );
            }, 1000 );
        },

        /**
         * Stop the ticker.
         *
         * @returns {void}
         */
        stopTimer: function() {
            if ( this.timer ) {
                window.clearInterval( this.timer );
                this.timer = null;
            }
        },

        /**
         * How long the run has been going, as a short string.
         *
         * @returns {string}
         */
        elapsed: function() {
            const seconds = Math.round( ( Date.now() - this.startTime ) / 1000 );
            if ( seconds < 60 ) {
                return seconds + 's';
            }

            return Math.floor( seconds / 60 ) + 'm ' + ( seconds % 60 ) + 's';
        },

        /**
         * Send the next batch, then keep going until the queue is empty.
         *
         * @returns {void}
         */
        next: function() {
            if ( ! this.queue.length || this.cancelled ) {
                this.finish();

                return;
            }

            const batch = this.queue.splice( 0, wpslGeocodeL10n.batchSize );
            const done  = this.total - this.queue.length - batch.length;

            // Say which batch is in flight.
            $( '.wpsl-geocode-status' ).text(
                wpslGeocodeL10n.processing
                    .replace( '%1$s', done + 1 )
                    .replace( '%2$s', done + batch.length )
                    .replace( '%3$s', this.total )
            );

            const run = this.run;

            this.request = $.post( wpslGeocodeL10n.ajaxurl, {
                action: 'wpsl_geocode_locations',
                nonce: $( '#wpsl-geocode-nonce' ).val(),
                mode: 'geocode',
                ids: batch
            }).done( function( response ) {
                if ( run !== geocodeLocations.run ) {
                    return;
                }

                geocodeLocations.request = null;

                if ( ! response.success ) {
                    geocodeLocations.showError( response.data );

                    return;
                }

                geocodeLocations.collect( response.data.results );
                geocodeLocations.updateProgress( geocodeLocations.total - geocodeLocations.queue.length );
                geocodeLocations.next();
            }).fail( function( jqXHR, textStatus ) {
                if ( run !== geocodeLocations.run || 'abort' === textStatus ) {
                    return;
                }

                geocodeLocations.request = null;
                geocodeLocations.showError();
            });
        },

        /**
         * Record what came back for one batch.
         *
         * @param   {Array} results One entry per location
         * @returns {void}
         */
        collect: function( results ) {
            $.each( results || [], function( index, result ) {
                if ( result.success ) {
                    geocodeLocations.fixed++;
                    geocodeLocations.last = result;

                    if ( result.repaired ) {
                        geocodeLocations.repaired++;
                    }

                    if ( result.warning ) {
                        geocodeLocations.failures.push( '#' + result.id + ' ' + result.name + ' — ' + result.warning );
                    }

                    return;
                }

                geocodeLocations.failures.push( '#' + result.id + ' ' + result.name + ' — ' + result.message );
            });
        },

        /**
         * Move the progress bar along.
         *
         * @param   {number} done How many locations have been handled
         * @returns {void}
         */
        updateProgress: function( done ) {
            const total = this.total || 1;
            const percentage = Math.min( Math.round( ( done / total ) * 100 ), 100 );

            $( '.wpsl-geocode-progress .wpsl-progress-fill' ).css( 'width', percentage + '%' );

            // The label sits mid-bar; at 50% the fill reaches it and it
            // flips to white to stay legible.
            $( '.wpsl-geocode-progress .wpsl-progress-label' )
                .toggleClass( 'wpsl-on-fill', percentage >= 50 )
                .text(
                wpslGeocodeL10n.progress
                    .replace( '%1$s', done )
                    .replace( '%2$s', this.total ) + ' (' + percentage + '%)'
            );
        },

        /**
         * Report the outcome.
         *
         * @returns {void}
         */
        finish: function() {
            this.running = false;

            this.stopTimer();

            $( '.wpsl-geocode-timer strong' ).text( this.elapsed() );
            $( '.wpsl-geocode-status' ).text( '' );
            $( '.wpsl-geocode-cancel' ).addClass( 'wpsl-hide' );

            let summary = wpslGeocodeL10n.done
                .replace( '%1$d', this.fixed )
                .replace( '%2$d', this.total );

            // A repaired fix used the stored value, not the API.
            if ( this.repaired ) {
                summary += ' ' + wpslGeocodeL10n.repaired.replace( '%d', this.repaired );
            }

            // A single "Fix" run reports the coordinates, not a tally.
            if ( 1 === this.total && this.last ) {
                summary = wpslGeocodeL10n.single
                    .replace( '%1$s', this.last.lat )
                    .replace( '%2$s', this.last.lng );

                if ( this.last.repaired ) {
                    summary += ' ' + wpslGeocodeL10n.singleRepaired;
                }
            }

            // "Stopped" goes first -- the tally that follows is not the whole
            // job. Built with .text() so a translation can never inject HTML.
            const $result  = $( '.wpsl-geocode-result' ).removeClass( 'wpsl-hide' ).empty();
            const stopped  = this.cancelled && this.queue.length;

            if ( stopped ) {
                $result.append( $( '<p class="wpsl-geocode-stopped"></p>' ).text( wpslGeocodeL10n.stopped ) );
            }

            $result.append( $( '<p></p>' ).text( summary ) );

            // Say how much was left, so a stopped run is not read as a finished one.
            if ( stopped ) {
                $result.append( $( '<p></p>' ).text( wpslGeocodeL10n.notProcessed.replace( '%d', this.queue.length ) ) );
            }

            if ( this.failures.length ) {
                $( '.wpsl-geocode-failures' ).removeClass( 'wpsl-hide' );
                $( '#wpsl-geocode-failure-log' ).val( this.failures.join( '\n' ) );
            }

            $( '#wpsl-geocode-close' ).removeClass( 'wpsl-hide' );
        },

        /**
         * Stop the run and say why.
         *
         * @param   {Object} data The error payload, if there was one
         * @returns {void}
         */
        showError: function( data ) {
            this.running = false;

            this.stopTimer();

            $( '.wpsl-geocode-status' ).text( '' );
            $( '.wpsl-geocode-cancel' ).addClass( 'wpsl-hide' );

            const message = ( data && data.message ) ? data.message : wpslGeocodeL10n.requestFailed;

            $( '.wpsl-geocode-progress' ).addClass( 'wpsl-hide' );
            $( '.wpsl-geocode-error' ).removeClass( 'wpsl-hide' ).text( message );
            $( '#wpsl-geocode-close' ).removeClass( 'wpsl-hide' );
        }
    };

    $( function() {
        geocodeLocations.init();
    });

}( jQuery ) );