/**
 * Marker Studio: the library pane.
 *
 * The library lists saved markers -- searchable, paged, clickable into the
 * editor.
 *
 * @since 3.0.0
 */
( function( $ ) {
    if ( typeof wpslMarkerStudio === 'undefined' || ! wpslMarkerStudio.editor ) {
        return;
    }

    const l10n   = wpslMarkerStudio.l10n || {};
    const editor = wpslMarkerStudio.editor;

    const PER_PAGE = parseInt( wpslMarkerStudio.perPage, 10 ) || 18;
    const CARDS_PER_ROW = 3;

    const $library       = $( '#wpsl-ms-library' );
    const $cards         = $( '#wpsl-ms-cards' );
    const $librarySearch = $( '#wpsl-ms-library-search' );
    const $libraryClear  = $librarySearch.siblings( '.wpsl-ms-search-clear' );
    const $libraryCount  = $( '#wpsl-ms-library-count' );
    const $libraryEmpty  = $( '#wpsl-ms-library-empty' );
    const $pagination    = $( '#wpsl-ms-pagination' );
    const $cardTemplate  = $( '#wpsl-ms-card-template' );

    let currentPage = 1;
    let lastActiveId = null;

    wpslMarkerStudio.library = {
        artFor: artFor
    };

    init();

    /**
     * Init both panes.
     *
     * @since  3.0.0
     * @return void
     */
    function init() {
        if ( $library.length ) {
            initLibrary();
        }

        $( document ).on( 'wpsl-ms-editor-change', onEditorChange );
        $( document ).on( 'wpsl-ms-marker-saved', onMarkerSaved );
        $( document ).on( 'wpsl-ms-marker-deleted', onMarkerDeleted );

        // Nothing has changed yet, so nothing has been announced.
        markActiveCard( editor.currentId() );
    }

    /**
     * The editor's draft changed: follow the selection.
     *
     * @since  3.0.0
     * @param  Event  e
     * @param  object fields The editor's current values.
     * @return void
     */
    function onEditorChange( e, fields ) {
        markActiveCard( editor.currentId() );
    }

    /**
     * A marker was saved: refresh the card list and preview.
     *
     * @since  3.0.0
     * @param  Event    e
     * @param  object   marker  The saved marker.
     * @param  string   dataUri The server's artwork for it.
     * @param  number[] size    [ width, height ] the artwork wants.
     * @param  number   cap     The card's max-height for that artwork.
     * @return void
     */
    function onMarkerSaved( e, marker, dataUri, size, cap ) {
        upsertCard( marker, dataUri, size, cap );
        lastActiveId = null;

        // Show the page the saved marker's card is actually on.
        renderLibrary( marker.id );
        markActiveCard( marker.id );
    }

    /**
     * A marker was deleted: drop its card.
     *
     * @since  3.0.0
     * @param  Event  e
     * @param  string id
     * @return void
     */
    function onMarkerDeleted( e, id ) {
        cardFor( id ).remove();
        renderLibrary();
    }

    /**
     * Wire the library's search, cards and pagination up.
     *
     * @since  3.0.0
     * @return void
     */
    function initLibrary() {
        $librarySearch.on( 'input search', function() {
            currentPage = 1;
            renderLibrary();
        } );

        // Toggle the custom clear button and wire it to reset the term.
        $librarySearch.on( 'input', function() {
            $libraryClear.prop( 'hidden', ! this.value );
            $librarySearch.closest( '.wpsl-ms-search' ).toggleClass( 'wpsl-ms-search-filled', !! this.value );
        } );
        $libraryClear.on( 'click', function() {
            $librarySearch.val( '' ).trigger( 'input' ).focus();
        } );

        // Delegated: the card list is rebuilt around these handlers.
        $cards.on( 'click', '.wpsl-ms-card-open', function() {
            const id = $( this ).closest( '.wpsl-ms-card' ).attr( 'data-marker-id' );
            if ( id && editor.load( id ) ) {
                markActiveCard( id );

                if ( wpslMarkerStudio.tabs ) {
                    wpslMarkerStudio.tabs.show( 'design' );
                }
            }
        } );

        // Per-card edit: load the marker into the editor and switch to Design.
        $cards.on( 'click', '.wpsl-ms-card-edit', function() {
            const id = $( this ).closest( '.wpsl-ms-card' ).attr( 'data-marker-id' );
            if ( id && editor.load( id ) ) {
                markActiveCard( id );

                if ( wpslMarkerStudio.tabs ) {
                    wpslMarkerStudio.tabs.show( 'design' );
                }
            }
        } );

        // Per-card duplicate
        $cards.on( 'click', '.wpsl-ms-card-duplicate', function() {
            const id = $( this ).closest( '.wpsl-ms-card' ).attr( 'data-marker-id' );
            if ( id && editor.duplicate ) {
                editor.duplicate( id );
            }
        } );

        // Per-card delete
        $cards.on( 'click', '.wpsl-ms-card-delete', function() {
            const id = $( this ).closest( '.wpsl-ms-card' ).attr( 'data-marker-id' );
            if ( id && editor.deleteMarker ) {
                editor.deleteMarker( id );
            }
        } );

        // Empty state: "Start designing" switches to the Design tab so the
        // user can create their first marker.
        $( '#wpsl-ms-empty-design' ).on( 'click', function() {
            if ( wpslMarkerStudio.tabs ) {
                wpslMarkerStudio.tabs.show( 'design' );
            }
        } );

        $pagination.on( 'click', '.wpsl-ms-page', function() {
            currentPage = parseInt( $( this ).attr( 'data-page' ), 10 ) || 1;
            renderLibrary();

            $library[ 0 ].scrollIntoView();
        } );

        renderLibrary();
    }

    /**
     * All cards, in DOM order.
     *
     * @since  3.0.0
     * @return jQuery
     */
    function allCards() {
        return $cards.children( '.wpsl-ms-card' );
    }

    /**
     * The card for one marker id.
     *
     * @since  3.0.0
     * @param  string id
     * @return jQuery
     */
    function cardFor( id ) {
        return allCards().filter( function() {
            return $( this ).attr( 'data-marker-id' ) === id;
        } );
    }

    /**
     * The artwork this pane is showing for a marker.
     *
     * @since  3.0.0
     * @param  string id A saved marker id.
     * @return object|null { src, width, height }, or null with no artwork to show.
     */
    function artFor( id ) {
        const $art = cardFor( id ).find( '.wpsl-ms-card-art img' ).first();
        if ( ! $art.length || $art.prop( 'hidden' ) || ! $art.attr( 'src' ) ) {
            return null;
        }

        return {
            src:    $art.attr( 'src' ),
            width:  $art.attr( 'width' ),
            height: $art.attr( 'height' )
        };
    }

    /**
     * Where a marker's card sits in a list of cards.
     *
     * @since  3.0.0
     * @param  Element[] cards
     * @param  string    id
     * @return number The index, or -1.
     */
    function indexOfCard( cards, id ) {
        for ( let index = 0; index < cards.length; index++ ) {
            if ( $( cards[ index ] ).attr( 'data-marker-id' ) === id ) {
                return index;
            }
        }

        return -1;
    }

    /**
     * Reduce a value to the form the marker search compares on.
     *
     * @since  3.0.0
     * @param  string value
     * @return string
     */
    function searchKey( value ) {
        return String( value || '' ).toLowerCase().replace( /\s+/g, ' ' ).trim();
    }

    /**
     * Apply the search and the current page to the cards.
     *
     * Show / hide rather than build / discard.
     *
     * @since  3.0.0
     * @param  string focusId Optional marker id whose card has to end up on the
     *                        page being shown.
     * @return void
     */
    function renderLibrary( focusId ) {
        const term    = searchKey( $librarySearch.val() );
        const $all    = allCards();
        const matches = [];

        $all.each( function() {
            const $card = $( this );
            const name  = searchKey( $card.find( '.wpsl-ms-card-name' ).text() );
            if ( '' === term || name.indexOf( term ) !== -1 ) {
                matches.push( this );
            }
        } );

        // Turn to the page holding the card being pointed at.
        if ( focusId ) {
            const focused = indexOfCard( matches, focusId );
            if ( focused !== -1 ) {
                currentPage = Math.floor( focused / PER_PAGE ) + 1;
            }
        }

        const pages = pageCount( matches.length );
        if ( currentPage > pages ) {
            currentPage = pages;
        }

        const first = ( currentPage - 1 ) * PER_PAGE;
        const page = matches.slice( first, currentPage === pages ? matches.length : first + PER_PAGE );

        $all.addClass( 'wpsl-ms-card-hidden' );
        $( page ).removeClass( 'wpsl-ms-card-hidden' );

        // The site's language, as number_format_i18n() printed it on load, not the browser's.
        $libraryCount.text( $all.length.toLocaleString( document.documentElement.lang || undefined ) );

        const hasMarkers  = $all.length > 0;
        const showEmpty   = matches.length === 0;
        const showContent = showEmpty && ! hasMarkers;
        const showSearch  = showEmpty && hasMarkers;

        $libraryEmpty.prop( 'hidden', ! showEmpty );
        $libraryEmpty.find( '.wpsl-ms-library-empty-content' ).prop( 'hidden', ! showContent );
        $libraryEmpty.find( '.wpsl-ms-library-empty-search' )
            .prop( 'hidden', ! showSearch )
            .text( showSearch ? l10n.noMatches : '' );

        renderPagination( pages );
    }

    /**
     * How many pages a result set of this size is worth.
     *
     * @since  3.0.0
     * @param  number total The number of cards to lay out.
     * @return number
     */
    function pageCount( total ) {
        let pages  = Math.max( 1, Math.ceil( total / PER_PAGE ) );
        const last = total % PER_PAGE;

        if ( pages > 1 && last > 0 && last < CARDS_PER_ROW ) {
            pages--;
        }

        return pages;
    }

    /**
     * Draw the pager under the card list.
     *
     * @since  3.0.0
     * @param  number pages The number of pages the current result set has.
     * @return void
     */
    function renderPagination( pages ) {
        $pagination.empty().prop( 'hidden', pages < 2 );

        if ( pages < 2 ) {
            return;
        }

        $pagination.append( pageButton( currentPage - 1, '', l10n.previousPage, currentPage === 1, false, 'previous' ) );

        for ( let page = 1; page <= pages; page++ ) {
            $pagination.append( pageButton( page, String( page ), format( l10n.goToPage, page ), false, page === currentPage ) );
        }

        $pagination.append( pageButton( currentPage + 1, '', l10n.nextPage, currentPage === pages, false, 'next' ) );
    }

    /**
     * The chevron a step button is drawn with.
     *
     * @since  3.0.0
     * @param  string direction "previous" or "next".
     * @return SVGElement
     */
    function chevron( direction ) {
        const ns   = 'http://www.w3.org/2000/svg';
        const svg  = document.createElementNS( ns, 'svg' );
        const path = document.createElementNS( ns, 'path' );

        svg.setAttribute( 'viewBox', '0 0 24 24' );
        svg.setAttribute( 'aria-hidden', 'true' );
        svg.setAttribute( 'focusable', 'false' );

        path.setAttribute( 'd', 'previous' === direction ? 'm15 18-6-6 6-6' : 'm9 18 6-6-6-6' );
        svg.appendChild( path );

        return svg;
    }

    /**
     * One button in the pager.
     *
     * @since  3.0.0
     * @param  number  page     The page the button goes to.
     * @param  string  label    The visible text.
     * @param  string  title    The accessible name.
     * @param  boolean disabled
     * @param  boolean current  Whether this is the page being shown.
     * @param  string  glyph    "previous" or "next" for a step button, omitted
     *                          for a numbered one.
     * @return jQuery
     */
    function pageButton( page, label, title, disabled, current, glyph ) {
        const $button = $( '<button>' )
            .attr( 'type', 'button' )
            .addClass( 'wpsl-ms-page' )
            .toggleClass( 'is-current', !! current )
            .attr( 'data-page', page )
            .attr( 'aria-label', title || '' )
            .attr( 'aria-current', current ? 'true' : null )
            .prop( 'disabled', !! disabled );

        if ( glyph ) {
            return $button.addClass( 'wpsl-ms-page-step' ).append( chevron( glyph ) );
        }

        return $button.text( label );
    }

    /**
     * Create or refresh the card for a saved marker.
     *
     * @since  3.0.0
     * @param  object   marker
     * @param  string   dataUri
     * @param  number[] size    [ width, height ], as the save endpoint reports it.
     * @param  number   cap     The card's max-height for that artwork, from the same response.
     * @return void
     */
    function upsertCard( marker, dataUri, size, cap ) {
        let $card = cardFor( marker.id );
        if ( ! $card.length ) {
            $card = $cardTemplate.find( '.wpsl-ms-card' ).first().clone();
            if ( ! $card.length ) {
                return;
            }

            $card.appendTo( $cards );
        }

        $card.attr( 'data-marker-id', marker.id );
        $card.find( '.wpsl-ms-card-name' ).text( marker.name || l10n.untitled );

        const $art = $card.find( '.wpsl-ms-card-art img' );

        // Hidden rather than left with an empty src when there is no artwork
        $art.attr( 'src', dataUri || '' ).attr( 'alt', '' ).prop( 'hidden', ! dataUri );

        if ( size && size.length === 2 ) {
            $art.attr( 'width', size[0] ).attr( 'height', size[1] );
        }

        // The cap belongs to the previous shape; a shape change shifts the
        // margin fraction, so a stale cap would size the new shape wrong.
        if ( cap ) {
            $art.css( 'max-height', cap + 'px' );
        }
    }

    /**
     * Point the library's selected state at one marker.
     *
     * @since  3.0.0
     * @param  string id The marker being edited, '' while creating a new one.
     * @return void
     */
    function markActiveCard( id ) {
        if ( id === lastActiveId ) {
            return;
        }

        allCards().each( function() {
            const $card   = $( this );
            const isMatch = !! id && $card.attr( 'data-marker-id' ) === id;

            $card.toggleClass( 'is-active', isMatch );
            $card.find( '.wpsl-ms-card-open' ).attr( 'aria-pressed', isMatch ? 'true' : 'false' );
        } );

        lastActiveId = id;
    }

    /**
     * Fill the single %s / %d placeholder in a localized string.
     *
     * @since  3.0.0
     * @param  string template
     * @param  *      value
     * @return string
     */
    function format( template, value ) {
        return String( template || '' ).replace( /%[sd]/, function() {
            return value;
        } );
    }

} )( jQuery );