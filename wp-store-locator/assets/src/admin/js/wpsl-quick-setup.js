/**
 * Quick setup: saves each of the six choices the moment it is picked, and
 * switches the Home card between open, collapsed and hidden.
 *
 * The markup comes from includes/admin/home/templates/quick-setup-tiles.php
 * and quick-setup-card.php. The script only changes attributes and text of
 * what is already on the page, text from the server goes in with .text(),
 * never into html.
 *
 * Saves go out one at a time, in the order they were picked. A choice made
 * while another one is saving waits its turn, a second click on the same
 * question replaces the one that is still waiting. The buttons change at
 * once, and go back to the last saved answers if the server refuses.
 *
 * @since 3.1.1
 */
( function( $ ) {
    'use strict';

    const config = window.wpslQuickSetup || null;

    if ( ! config ) {
        return;
    }

    const QUESTIONS = [ 'method', 'countries', 'locate', 'pages', 'layout', 'filters' ];
    const FILTERS   = [ 'categories', 'radius', 'results' ];

    // A small check mark in front of a "saved" message, static markup.
    const CHECK_ICON = '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 12.5l5 5L20 6.5"/></svg>';

    const maxCountries = parseInt( config.maxCountries, 10 ) || 15;

    // A request that hasn't answered after this long failed, in milliseconds.
    const TIMEOUT = 30000;

    // How long a "… saved" message stays before it fades out, and the fade, in milliseconds.
    const SAVED_VISIBLE = 5000;
    const SAVED_FADE    = 400;

    // What inFlight holds while the rewrite rules are being flushed.
    const FLUSH = 'flush';

    // The Home card, absent on the wizard step.
    const $card = $( '#wpsl-quick-setup' );

    // The answers as the server last confirmed them, what a refused save goes back to.
    let known = null;

    // The saves that wait for their turn, and the question that is on its way.
    const waiting = [];
    let inFlight  = null;

    // A pages save asked for a flush, sent once nothing else waits, with the tile whose status line shows a failure.
    let flushNeeded = false;
    let flushTile   = null;

    /*
     * "Only in these countries" is picked but no country is checked: either
     * the radio was just clicked, or the last country was unchecked ( that
     * saves Anywhere, the page shows Anywhere after a reload ). Until a
     * country is checked the dropdown stays visible, whatever else is rendered.
     */
    let countriesPending = false;

    // The 15th country was just checked, the next "saved" message says why no more can be.
    let capNotice = false;

    // The close icon of a tag, the same static svg the Settings page prints.
    const TAG_REMOVE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>';

    /**
     * Replace %s in a string from the server.
     *
     * @param {string} template A string with one %s
     * @param {string} value    The text for it
     * @return {string}
     */
    function format( template, value ) {
        return String( template ).replace( '%s', function() {
            return value;
        } );
    }

    /**
     * @param {jQuery} $tile A tile
     * @return {string} The question of the tile
     */
    function questionOf( $tile ) {
        return String( $tile.attr( 'data-question' ) || '' );
    }

    /**
     * @param {string} question A question id
     * @return {jQuery} The tile of the question
     */
    function tileOf( question ) {
        return $( '.wpsl-qs-tile[data-question="' + question + '"]' ).first();
    }

    /**
     * The status line of the tile's card, or of the wizard step.
     *
     * @param {jQuery} $from Any element inside the tiles
     * @return {jQuery}
     */
    function statusOf( $from ) {
        const $own = $from.closest( '.wpsl-qs-tiles' ).children( '.wpsl-qs-status' );

        return $own.length ? $own.first() : $from.closest( '.wpsl-qs' ).find( '.wpsl-qs-status' ).first();
    }

    /**
     * Show a message in the status line. A "… saved" message fades out after
     * a few seconds, an error stays until the next message.
     *
     * @param {jQuery}  $from   Any element inside the tiles
     * @param {string}  message Plain text
     * @param {boolean} isError Whether it is an error
     */
    function say( $from, message, isError ) {
        const $status = statusOf( $from );

        clearTimeout( $status.data( 'wpslQsFade' ) );

        $status.empty().removeClass( 'wpsl-qs-status-fading' ).toggleClass( 'wpsl-qs-status-error', !! isError );

        if ( ! message ) {
            return;
        }

        if ( ! isError ) {
            $status.append( $( CHECK_ICON ) );
        }

        $status.append( document.createTextNode( message ) );

        if ( ! isError ) {
            $status.data( 'wpslQsFade', setTimeout( function() {
                $status.addClass( 'wpsl-qs-status-fading' );

                $status.data( 'wpslQsFade', setTimeout( function() {
                    $status.empty().removeClass( 'wpsl-qs-status-fading' );
                }, SAVED_FADE ) );
            }, SAVED_VISIBLE ) );
        }
    }

    /**
     * Mark one answer of a radiogroup as the checked one.
     *
     * @param {jQuery} $tile The tile
     * @param {string} value The data-value of the answer
     */
    function checkRadio( $tile, value ) {
        $tile.find( '.wpsl-qs-answer[data-value]' ).each( function() {
            $( this ).attr( 'aria-checked', $( this ).attr( 'data-value' ) === String( value ) ? 'true' : 'false' );
        } );
    }

    /**
     * @param {jQuery} $tile The tile
     * @return {string} The data-value of the checked answer, '' when none
     */
    function checkedValue( $tile ) {
        return String( $tile.find( '.wpsl-qs-answer[data-value][aria-checked="true"]' ).first().attr( 'data-value' ) || '' );
    }

    /**
     * @param {jQuery} $tile The countries tile
     * @return {jQuery} The checkboxes of the countries dropdown
     */
    function countryBoxes( $tile ) {
        return $tile.find( '.wpsl-multiselect-menu input[type="checkbox"]' );
    }

    /**
     * @param {jQuery} $tile The countries tile
     * @return {string[]} The codes of the checked countries
     */
    function getCodes( $tile ) {
        return countryBoxes( $tile ).filter( ':checked' ).map( function() {
            return this.value;
        } ).get();
    }

    /**
     * Close the open dropdown menus of the tiles.
     *
     * @param {jQuery} $except A menu to leave open
     */
    function closeMenus( $except ) {
        const $open = $( '.wpsl-qs-tiles .wpsl-multiselect-menu.show' );

        ( $except ? $open.not( $except ) : $open ).removeClass( 'show' );
    }

    /**
     * Rebuild the tags in the dropdown button from the checked boxes, or the
     * placeholder when none is checked. Labels and the placeholder go in as
     * text, only the static close icon is html.
     *
     * @param {jQuery} $tile The countries tile
     */
    function renderTags( $tile ) {
        const $button  = $tile.find( '.wpsl-multiselect-container > button' );
        const content  = $tile.find( '.wpsl-multiselect-content' )[ 0 ];
        const $checked = countryBoxes( $tile ).filter( ':checked' );

        if ( ! content ) {
            return;
        }

        while ( content.firstChild ) {
            content.removeChild( content.firstChild );
        }

        if ( ! $checked.length ) {
            const placeholder = document.createElement( 'span' );

            placeholder.className   = 'wpsl-multiselect-placeholder';
            placeholder.textContent = String( $button.attr( 'data-placeholder' ) || '' );
            content.appendChild( placeholder );

            return;
        }

        $checked.each( function() {
            const tag    = document.createElement( 'div' );
            const text   = document.createElement( 'span' );
            const remove = document.createElement( 'span' );

            tag.className = 'wpsl-multiselect-tag';

            text.className   = 'wpsl-multiselect-tag-text';
            text.textContent = this.getAttribute( 'data-label' ) || this.value.toUpperCase();

            remove.className     = 'wpsl-multiselect-tag-remove';
            remove.dataset.value = this.value;
            remove.innerHTML     = TAG_REMOVE_ICON; // Static markup, no variables.

            tag.appendChild( text );
            tag.appendChild( remove );
            content.appendChild( tag );
        } );
    }

    /**
     * Bring the countries tile in line with a list of codes: the checked
     * boxes, the tags, the 15 country limit, which answer is checked, and
     * whether the dropdown can be opened.
     *
     * @param {jQuery}   $tile   The countries tile
     * @param {string[]} codes   The chosen countries
     * @param {boolean}  pending Whether "Only in these countries" is picked without a country
     */
    function showCountries( $tile, codes, pending ) {
        const isDisabled = $tile.hasClass( 'wpsl-qs-disabled' );
        const isFull     = codes.length >= maxCountries;
        const isOn       = codes.length > 0 || !! pending;
        const $button    = $tile.find( '.wpsl-multiselect-container > button' );

        countryBoxes( $tile ).each( function() {
            const want = codes.indexOf( this.value ) !== -1;

            if ( this.checked !== want ) {
                this.checked = want;
            }

            this.disabled = isFull && ! want;
        } );

        renderTags( $tile );

        $button.prop( 'disabled', isDisabled ).attr( 'aria-disabled', isDisabled ? 'true' : null );

        if ( isDisabled || ! isOn ) {
            closeMenus();
        }

        $tile.find( '.wpsl-qs-country-box' ).prop( 'hidden', ! isOn );

        // "Only in these countries" without a country saves nothing yet, say what it waits for.
        $tile.find( '.wpsl-qs-country-hint' ).prop( 'hidden', ! isOn || codes.length > 0 || isDisabled );

        checkRadio( $tile, isOn ? 'countries' : 'anywhere' );
    }

    /**
     * @param {jQuery} $filter A filter button
     * @return {boolean} Whether it is switched on
     */
    function isOn( $filter ) {
        return 'true' === $filter.attr( 'aria-checked' );
    }

    /**
     * The answers as they are on the page right now.
     *
     * @return {Object}
     */
    function readAnswers() {
        const $countries = tileOf( 'countries' );
        const filters    = {};

        FILTERS.forEach( function( filter ) {
            filters[ filter ] = isOn( $( '.wpsl-qs-answer[data-filter="' + filter + '"]' ).first() );
        } );

        return {
            method: checkedValue( tileOf( 'method' ) ),
            countries: getCodes( $countries ),
            locate: checkedValue( tileOf( 'locate' ) ),
            pages: '1' === checkedValue( tileOf( 'pages' ) ),
            layout: checkedValue( tileOf( 'layout' ) ),
            filters: filters
        };
    }

    /**
     * Show the description of the checked layout under the rows.
     *
     * @param {jQuery} $tile The layout tile
     */
    function showLayoutDescription( $tile ) {
        $tile.find( '.wpsl-qs-layout-desc' ).text( $tile.find( '.wpsl-qs-answer[aria-checked="true"]' ).first().attr( 'data-desc' ) || '' );
    }

    /**
     * Show the answer of one question. The disabled states follow the same
     * rules as Quick_Setup::is_disabled().
     *
     * @param {string} question The question id
     * @param {Object} answers  The answers, see readAnswers()
     */
    function showQuestion( question, answers ) {
        const $tile  = tileOf( question );
        const isName = ( 'name' === answers.method );

        if ( ! $tile.length ) {
            return;
        }

        switch ( question ) {
            case 'method':
                checkRadio( $tile, answers.method );
                break;

            case 'countries':
                $tile.toggleClass( 'wpsl-qs-disabled', isName );
                $tile.find( '.wpsl-qs-answer' ).attr( 'aria-disabled', isName ? 'true' : null );

                if ( answers.countries.length ) {
                    countriesPending = false;
                }

                showCountries( $tile, answers.countries, countriesPending && ! isName );
                break;

            case 'locate':
                $tile.toggleClass( 'wpsl-qs-disabled', isName );
                $tile.find( '.wpsl-qs-answer' ).attr( 'aria-disabled', isName ? 'true' : null );
                checkRadio( $tile, answers.locate );
                break;

            case 'pages':
                checkRadio( $tile, answers.pages ? '1' : '0' );
                break;

            case 'layout':
                checkRadio( $tile, answers.layout );
                showLayoutDescription( $tile );
                break;

            default:
                FILTERS.forEach( function( filter ) {
                    const disabled = ( 'radius' === filter && isName ) || ( 'results' === filter && 'vertical' === answers.layout );
                    const $filter  = $tile.find( '.wpsl-qs-answer[data-filter="' + filter + '"]' );

                    $filter.attr( 'aria-disabled', disabled ? 'true' : null );
                    $filter.attr( 'aria-checked', ( ! disabled && answers.filters[ filter ] ) ? 'true' : 'false' );
                } );
        }
    }

    /**
     * Whether a question has a save on its way or waiting.
     *
     * @param {string} question The question id
     * @return {boolean}
     */
    function isBusy( question ) {
        return question === inFlight || waiting.some( function( job ) {
            return job.question === question;
        } );
    }

    /**
     * Show everything a successful save returns: the answers, the notes, the
     * disabled states and the rows of the collapsed card. A question that
     * has another save pending keeps what the visitor just picked.
     *
     * @param {Object} data The data of the response
     */
    function render( data ) {
        QUESTIONS.forEach( function( question ) {
            if ( ! isBusy( question ) ) {
                showQuestion( question, data.answers );
            }
        } );

        $.each( data.notes || {}, function( key, text ) {
            const $note = $( '.wpsl-qs-note[data-note-for="' + key + '"]' );

            $note.find( '.wpsl-qs-note-text' ).text( text );
            $note.prop( 'hidden', '' === text );
        } );

        $.each( data.summary || [], function( index, row ) {
            $( '.wpsl-qs-row[data-question="' + row.id + '"] .wpsl-qs-row-value' ).text( row.value );
        } );
    }

    /**
     * The value to post for a question, from the buttons as they are now.
     *
     * @param {string} question The question id
     * @return {string|string[]|Object}
     */
    function valueOf( question ) {
        const $tile = tileOf( question );

        switch ( question ) {
            case 'countries':
                // jQuery drops an empty array, '' is Anywhere.
                return getCodes( $tile ).length ? getCodes( $tile ) : '';

            case 'filters': {
                const filters = {};

                /*
                 * The ones that don't apply right now are left out, the server keeps
                 * their setting. So are the ones that are as the server last said:
                 * a second tab may have changed those since, and this save is not
                 * about them.
                 */
                $tile.find( '.wpsl-qs-answer[data-filter]' ).each( function() {
                    const filter = $( this ).attr( 'data-filter' );

                    if ( 'true' === $( this ).attr( 'aria-disabled' ) ) {
                        return;
                    }

                    if ( known && known.filters && known.filters[ filter ] === isOn( $( this ) ) ) {
                        return;
                    }

                    filters[ filter ] = isOn( $( this ) ) ? '1' : '0';
                } );

                return filters;
            }

            default:
                return checkedValue( $tile );
        }
    }

    /**
     * @param {Object} filters What valueOf() returns for the filters
     * @return {boolean} Whether any filter differs from the last known state
     */
    function hasChanges( filters ) {
        return Object.keys( filters ).length > 0;
    }

    /**
     * Whether a save or the flush is queued or on its way.
     *
     * @return {boolean}
     */
    function hasUnsaved() {
        return null !== inFlight || waiting.length > 0 || flushNeeded;
    }

    // Leaving now would lose the choice, or the rewrite flush that makes the landing page addresses work.
    window.addEventListener( 'beforeunload', function( event ) {
        if ( ! hasUnsaved() ) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    } );

    /**
     * The short name of a question, as the collapsed card calls it: Search,
     * Countries, Find visitors, Location pages, Layout, Filters.
     *
     * @param {string} question The question id
     * @param {Array}  summary  The summary rows of a response, if there is one
     * @param {jQuery} $tile    The tile, its heading is the last resort
     * @return {string}
     */
    function labelOf( question, summary, $tile ) {
        let label = '';

        $.each( summary || [], function( index, row ) {
            if ( row.id === question ) {
                label = row.label;
            }
        } );

        if ( ! label ) {
            label = $( '.wpsl-qs-row[data-question="' + question + '"] .wpsl-qs-row-label' ).first().text();
        }

        return label || $tile.find( '.wpsl-qs-question' ).first().text();
    }

    /**
     * The message of a failed request, if the server sent one.
     *
     * @param {Object} xhr The request
     * @return {string}
     */
    function messageOf( xhr ) {
        const body = ( xhr && xhr.responseJSON ) || null;

        return ( body && body.data && body.data.message ) || '';
    }

    /**
     * Send one request from the queue. Whatever happens in the handlers, the
     * queue goes on with the next one afterwards, and a request that doesn't
     * answer in time counts as failed.
     *
     * @param {Object}   data   What to post
     * @param {Function} onDone Called with the data of a response that succeeded
     * @param {Function} onFail Called with the server's message, '' when there is none
     */
    function send( data, onDone, onFail ) {
        let finished = false;

        function finish() {
            if ( finished ) {
                return;
            }

            finished = true;
            inFlight = null;
            next();
        }

        $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            data: data,
            dataType: 'json',
            timeout: TIMEOUT
        } ).done( function( response ) {
            try {
                if ( ! response || ! response.success ) {
                    onFail( ( response && response.data && response.data.message ) || '' );
                } else {
                    onDone( response.data );
                }
            } finally {
                finish();
            }
        } ).fail( function( xhr ) {
            try {
                onFail( messageOf( xhr ) );
            } finally {
                finish();
            }
        } );
    }

    /**
     * Send the next save, or the flush that follows the last of them, if
     * nothing is on its way.
     */
    function next() {
        if ( inFlight ) {
            return;
        }

        if ( ! waiting.length ) {
            if ( flushNeeded ) {
                flush();
            }

            return;
        }

        const job   = waiting.shift();
        const value = valueOf( job.question );

        // Switched on and off again before it went out: nothing differs from the server.
        if ( 'filters' === job.question && ! hasChanges( value ) ) {
            next();
            return;
        }

        inFlight = job.question;

        send( {
            action: config.actions.save,
            nonce: config.nonce,
            question: job.question,
            value: value
        }, function( data ) {
            known = data.answers;
            inFlight = null;

            render( data );

            let message = format( config.i18n.saved, labelOf( job.question, data.summary, job.$tile ) );

            if ( 'countries' === job.question && capNotice ) {
                capNotice = false;
                message  += '. ' + config.i18n.countriesMax;
            }

            say( job.$tile, message, false );

            // The landing pages were switched, the rules are rebuilt on a fresh request.
            if ( data.flush ) {
                flushNeeded = true;
                flushTile   = job.$tile;
            }
        }, function( message ) {
            failed( job, message );
        } );
    }

    /**
     * Rebuild the rewrite rules, once, after the last save of the landing
     * pages. It goes through the queue, so two quick switches don't flush at
     * the same time.
     */
    function flush() {
        const $tile = flushTile;

        flushNeeded = false;
        inFlight    = FLUSH;

        send( { action: config.actions.flush, nonce: config.nonce }, function() {}, function( message ) {
            showError( $tile, message || config.i18n.flushError );
        } );
    }

    /**
     * Show an error in the status line. A card that was collapsed or hidden
     * while the request was on its way opens first, at the tile, so the
     * message can be seen.
     *
     * @param {jQuery} $tile   The tile the error belongs to
     * @param {string} message Plain text
     */
    function showError( $tile, message ) {
        if ( $card.length && 'open' !== $card.attr( 'data-state' ) ) {
            setState( 'open' );

            if ( $tile.length ) {
                $tile[ 0 ].scrollIntoView( { block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' } );
            }
        }

        say( $tile, message, true );
    }

    /**
     * A save was refused or never arrived: show why, and put the question
     * back to the last confirmed answer.
     *
     * @param {Object} job     The save
     * @param {string} message The server's message, if there is one
     */
    function failed( job, message ) {
        inFlight = null;

        // Whatever waits for the same question was built on the refused answer.
        for ( let index = waiting.length - 1; index >= 0; index-- ) {
            if ( waiting[ index ].question === job.question ) {
                waiting.splice( index, 1 );
            }
        }

        if ( known ) {
            showQuestion( job.question, known );
        }

        showError( job.$tile, message || config.i18n.error );
    }

    /**
     * Save the answer of a question. The buttons have already changed.
     *
     * @param {jQuery} $tile The tile
     */
    function save( $tile ) {
        const question = questionOf( $tile );

        // The same question is still waiting, it posts the buttons as they are now.
        const isWaiting = waiting.some( function( job ) {
            return job.question === question;
        } );

        if ( isWaiting ) {
            return;
        }

        waiting.push( {
            question: question,
            $tile: $tile
        } );

        say( $tile, '', false );
        next();
    }

    /**
     * Pick a radio answer.
     *
     * @param {jQuery}  $answer  The answer button
     * @param {boolean} fromKeys Whether an arrow key picked it, the focus stays on the answer
     */
    function choose( $answer, fromKeys ) {
        const $tile    = $answer.closest( '.wpsl-qs-tile' );
        const question = questionOf( $tile );
        const value    = String( $answer.attr( 'data-value' ) );

        if ( 'true' === $answer.attr( 'aria-disabled' ) || 'true' === $answer.attr( 'aria-checked' ) ) {
            return;
        }

        if ( 'countries' === question ) {
            if ( 'countries' === value ) {
                // Nothing to save until the first country is checked.
                countriesPending = true;
                showCountries( $tile, getCodes( $tile ), true );

                if ( ! fromKeys ) {
                    $tile.find( '.wpsl-multiselect-container > button' ).trigger( 'focus' );
                }

                return;
            }

            const hadCountries = getCodes( $tile ).length > 0;

            countriesPending = false;
            showCountries( $tile, [], false );

            if ( hadCountries ) {
                save( $tile );
            } else {
                // Anywhere is what the server holds already, confirm it like a save.
                say( $tile, format( config.i18n.saved, labelOf( 'countries', null, $tile ) ), false );
            }

            return;
        }

        checkRadio( $tile, value );

        if ( 'layout' === question ) {
            showLayoutDescription( $tile );
        }

        save( $tile );
    }

    /**
     * A country was checked or unchecked: show it, and save the checked ones.
     * Unchecking the last one saves Anywhere, the tile stays on "Only in
     * these countries" with its dropdown until the page is loaded again.
     *
     * @param {jQuery} $tile The countries tile
     */
    function countriesChanged( $tile ) {
        const codes   = getCodes( $tile );
        const wasFull = countryBoxes( $tile ).filter( ':disabled' ).length > 0;

        if ( $tile.hasClass( 'wpsl-qs-disabled' ) ) {
            showCountries( $tile, known ? known.countries : [], false );
            return;
        }

        countriesPending = 0 === codes.length;

        // Reaching the limit is said once, in the message that follows the save.
        capNotice = codes.length >= maxCountries && ! wasFull;

        showCountries( $tile, codes, countriesPending );
        save( $tile );
    }

    // The answers: radios choose, checkboxes toggle, disabled ones do nothing.
    $( document ).on( 'click', '.wpsl-qs-tiles .wpsl-qs-answer', function() {
        const $answer = $( this );

        if ( 'true' === $answer.attr( 'aria-disabled' ) ) {
            return;
        }

        if ( 'checkbox' === $answer.attr( 'role' ) ) {
            $answer.attr( 'aria-checked', isOn( $answer ) ? 'false' : 'true' );
            save( $answer.closest( '.wpsl-qs-tile' ) );

            return;
        }

        choose( $answer );
    } );

    /*
     * Arrow keys move through a radiogroup and pick what they land on. Space
     * and Enter on a button already send a click, so they are left alone.
     */
    $( document ).on( 'keydown', '.wpsl-qs-tiles [role="radiogroup"] .wpsl-qs-answer', function( event ) {
        const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };

        if ( ! keys[ event.key ] || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
            return;
        }

        const $answers = $( this ).closest( '[role="radiogroup"]' ).find( '.wpsl-qs-answer' ).filter( function() {
            return 'true' !== $( this ).attr( 'aria-disabled' );
        } );

        const at = $answers.index( this );

        if ( at < 0 ) {
            return;
        }

        event.preventDefault();

        const $target = $answers.eq( ( at + keys[ event.key ] + $answers.length ) % $answers.length );

        $target.trigger( 'focus' );
        choose( $target, true );
    } );

    /*
     * The countries dropdown: the Settings page's multiselect, which that
     * page's script drives. Here the script is scoped to the tiles, a menu
     * opens on the button and closes on a click outside, on Escape, or when
     * another one opens.
     */
    $( document ).on( 'click', '.wpsl-qs-tiles .wpsl-multiselect-container > button', function( event ) {
        const $menu = $( this ).siblings( '.wpsl-multiselect-menu' );

        event.preventDefault();

        if ( this.disabled || $( this ).closest( '.wpsl-qs-tile' ).hasClass( 'wpsl-qs-disabled' ) ) {
            return;
        }

        closeMenus( $menu );
        $menu.toggleClass( 'show' );
    } );

    $( document ).on( 'click', function( event ) {
        if ( ! $( event.target ).closest( '.wpsl-qs-tiles .wpsl-multiselect-container' ).length ) {
            closeMenus();
        }
    } );

    $( document ).on( 'keydown', function( event ) {
        const $open = $( '.wpsl-qs-tiles .wpsl-multiselect-menu.show' );

        if ( 'Escape' !== event.key || ! $open.length ) {
            return;
        }

        $open.first().siblings( 'button' ).trigger( 'focus' );
        closeMenus();
    } );

    $( document ).on( 'change', '.wpsl-qs-tiles .wpsl-multiselect-menu input[type="checkbox"]', function() {
        countriesChanged( $( this ).closest( '.wpsl-qs-tile' ) );
    } );

    $( document ).on( 'click', '.wpsl-qs-tiles .wpsl-multiselect-tag-remove', function( event ) {
        const $tile = $( this ).closest( '.wpsl-qs-tile' );
        const value = String( this.dataset.value || '' );

        // The tag sits inside the button, a click on it must not open the menu.
        event.stopPropagation();
        event.preventDefault();

        if ( $tile.hasClass( 'wpsl-qs-disabled' ) ) {
            return;
        }

        countryBoxes( $tile ).filter( function() {
            return this.value === value;
        } ).prop( 'checked', false );

        countriesChanged( $tile );
    } );

    /*
     * The Home card: open, collapsed or hidden. The state is shown at once and
     * stored for the user, a failure to store it changes nothing on the page.
     */
    known = readAnswers();

    if ( ! $card.length ) {
        return;
    }

    /**
     * @param {string} state open, collapsed or hidden
     */
    function setState( state ) {
        const $line = $( '.wpsl-home-start-over' );

        // A "saved" from before doesn't belong to the card that opens again.
        say( $card.find( '.wpsl-qs-tiles' ), '', false );

        $card.attr( 'data-state', state ).prop( 'hidden', 'hidden' === state );

        $line.attr( 'data-state', state );
        $line.find( '.wpsl-qs-start-hidden' ).prop( 'hidden', 'hidden' !== state );
        $line.find( '.wpsl-qs-start-default' ).prop( 'hidden', 'hidden' === state );

        storeState( state );
    }

    /*
     * One state request at a time, so two quick clicks cannot arrive out of
     * order. While one is on its way only the latest state waits.
     */
    let stateInFlight = false;
    let stateWaiting  = null;

    /**
     * @param {string} state open, collapsed or hidden
     */
    function storeState( state ) {
        if ( stateInFlight ) {
            stateWaiting = state;
            return;
        }

        stateInFlight = true;

        $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            data: { action: config.actions.state, nonce: config.nonce, state: state },
            timeout: TIMEOUT
        } ).always( function() {
            stateInFlight = false;

            if ( null !== stateWaiting ) {
                const latest = stateWaiting;

                stateWaiting = null;
                storeState( latest );
            }
        } );
    }

    /**
     * @return {boolean} Whether the visitor asked for less motion
     */
    function reducedMotion() {
        return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
    }

    $card.on( 'click', '.wpsl-qs-collapse', function() {
        setState( 'collapsed' );
        $card.find( '.wpsl-qs-edit' ).trigger( 'focus' );
    } );

    $card.on( 'click', '.wpsl-qs-hide', function() {
        setState( 'hidden' );
        $( '.wpsl-qs-show' ).first().trigger( 'focus' );
    } );

    $card.on( 'click', '.wpsl-qs-edit', function() {
        setState( 'open' );
        $card.find( '.wpsl-qs-collapse' ).trigger( 'focus' );
    } );

    // A row of the summary opens the card at its question.
    $card.on( 'click', '.wpsl-qs-row', function() {
        const $tile = tileOf( String( $( this ).attr( 'data-question' ) ) );

        setState( 'open' );

        if ( ! $tile.length ) {
            return;
        }

        const $checked = $tile.find( '.wpsl-qs-answer[aria-checked="true"]' ).first();
        const $focus   = $checked.length ? $checked : $tile.find( '.wpsl-qs-answer' ).first();

        $tile[ 0 ].scrollIntoView( { block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' } );
        $focus[ 0 ].focus( { preventScroll: true } );
    } );

    // "Quick setup" in the line at the bottom of Home brings a hidden card back.
    $( document ).on( 'click', '.wpsl-qs-show', function() {
        setState( 'open' );
        $card.find( '.wpsl-qs-collapse' ).trigger( 'focus' );
    } );
}( jQuery ) );
