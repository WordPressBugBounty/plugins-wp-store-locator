import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { helpers } from './wpsl-helpers.js';

/**
 * The info window of a marker that stands for several locations at the
 * same address: one slide per location, each the normal info window
 * template, with buttons to move between them. Past a few locations it
 * opens on a list of their names instead.
 *
 * Shared by every map provider. The provider renders template() as the
 * content of its info window / popup and calls bind() once the content
 * is in the page.
 *
 * Each slide keeps its own .wpsl-info-window[data-store-id], so the info
 * window actions ( directions, more details ) act on that slide's location.
 *
 * @since 3.1.1
 */

const SWIPE_DISTANCE = 40;

export const groupWindow = {

    /**
     * Above this many locations the window opens on a list of them.
     *
     * @since  3.1.1
     * @return {number}
     */
    listThreshold: function() {

        /**
         * Filter the number of locations at one address above which the info
         * window opens on a list of them, instead of on the first location.
         *
         * @since 3.1.1
         * @param {number} threshold Defaults to 5
         */
        return parseInt( wp.hooks.applyFilters( 'wpslMarkerGroupListThreshold', 5 ), 10 ) || 5;
    },

    /**
     * The accessible name of a marker that stands for several locations.
     *
     * @since  3.1.1
     * @param  {array} stores The locations, the lead first
     * @return {string} Plain text
     */
    markerTitle: function( stores ) {
        const name  = helpers.decodeHtmlEntity( String( stores[0].store || '' ) ) || '';
        const label = ( typeof wpslLabels !== 'undefined' && wpslLabels.groupMarkerTitle ) ? wpslLabels.groupMarkerTitle : '%1$s and %2$d more at this address';

        // Function replacements, so a $ in a store name isn't read as a replacement pattern.
        return label.replace( '%1$s', () => name ).replace( '%2$d', () => String( stores.length - 1 ) );
    },

    /**
     * The HTML of the info window.
     *
     * @since  3.1.1
     * @param  {array}       stores      The locations, the lead first
     * @param  {number|null} startIndex  The location to open on, null for a click on the marker
     * @param  {function}    renderSlide Optional. Returns the HTML of one location, defaults to the info window template
     * @return {string}
     */
    template: function( stores, startIndex = null, renderSlide = null ) {
        const render   = typeof renderSlide === 'function' ? renderSlide : ( store ) => helpers.template.getInfoWindowTemplate( store );
        const count    = stores.length;
        const listMode = count > this.listThreshold();
        const active   = Math.min( Math.max( 0, startIndex | 0 ), count - 1 );

        // A marker click on many locations opens on the list, a result in the list on its own location.
        const view = ( listMode && startIndex === null ) ? 'list' : 'slide';

        const header = label( 'groupHeader', '{number} locations at this address' ).replace( '{number}', count );

        /*
         * The HTML opens in its final state, the same render() gives it once bound:
         * a popup can paint before the provider calls bind(), and its slides
         * would briefly show on top of each other.
         */
        const listView = view === 'list';

        let list = '';

        if ( listMode ) {
            list += `<ul class="wpsl-group-list${listView ? ' wpsl-active' : ''}"${hiddenAttributes( ! listView )}>`;

            stores.forEach( ( store, index ) => {

                // Escaped for HTML here: the name arrives escaped, the address may hold a raw & or quote.
                const address = store.address ? `<span class="wpsl-group-list-address">${htmlText( store.address )}</span>` : '';

                list += `<li><button type="button" class="wpsl-group-pick" data-index="${index}"><span class="wpsl-group-list-name">${htmlText( store.store )}</span>${address}</button></li>`;
            });

            list += '</ul>';
        }

        let slides = '';

        stores.forEach( ( store, index ) => {
            const current = ! listView && index === active;

            slides += `<div class="wpsl-group-slide${current ? ' wpsl-active' : ''}" data-index="${index}" role="group" aria-roledescription="slide" aria-label="${sharedHelpers.escapeHtml( slideLabel( index, count ) )}"${hiddenAttributes( ! current )}>${render( store )}</div>`;
        });

        const back    = listMode ? `<button type="button" class="wpsl-group-all"${listView ? ' hidden' : ''}>${sharedHelpers.escapeHtml( label( 'groupAll', 'All locations' ) )}</button>` : '';
        const prevOff = listView || active === 0;
        const nextOff = listView || active >= count - 1;

        /*
         * The buttons come after the locations in the HTML and are shown at the top
         * with CSS. The popups' keyboard handling moves from their close button to
         * the first link in the content and back, so buttons placed before that
         * link could never be reached with Tab.
         */
        let html = `<div class="wpsl-group-window" data-group-size="${count}" data-active-index="${active}" data-view="${view}">
            <div class="wpsl-group-header">
                <span class="wpsl-group-title">${sharedHelpers.escapeHtml( header )}</span>
            </div>
            <div class="wpsl-group-body">${list}${slides}</div>
            <div class="wpsl-group-nav">
                ${back}
                <button type="button" class="wpsl-group-prev" aria-label="${sharedHelpers.escapeHtml( label( 'groupPrev', 'Previous location' ) )}"${prevOff ? ' disabled' : ''}${listView ? ' hidden' : ''}><span aria-hidden="true">&lsaquo;</span></button>
                <span class="wpsl-group-counter" aria-live="polite">${listView ? '' : sharedHelpers.escapeHtml( slideLabel( active, count ) )}</span>
                <button type="button" class="wpsl-group-next" aria-label="${sharedHelpers.escapeHtml( label( 'groupNext', 'Next location' ) )}"${nextOff ? ' disabled' : ''}${listView ? ' hidden' : ''}><span aria-hidden="true">&rsaquo;</span></button>
            </div>
        </div>`;

        /**
         * Filter the HTML of the info window shared by locations at one address.
         *
         * @since 3.1.1
         * @param {string} html       The HTML
         * @param {array}  stores     The locations, the lead first
         * @param {number} startIndex The location the window opens on
         */
        html = wp.hooks.applyFilters( 'wpslGroupInfoWindowTemplate', html, stores, startIndex );

        return html;
    },

    /**
     * Make the info window work once its HTML is in the page.
     *
     * Safe to call more than once for the same window: providers that
     * fire their "content ready" event on every open won't stack handlers.
     *
     * @since  3.1.1
     * @param  {Element|jQuery} container The info window / popup element, or the group window itself
     * @param  {object}         context   { stores, marker, map } passed to the wpslMarkerGroupSlideShown action
     * @return {void}
     */
    bind: function( container, context ) {
        const el   = container && container.jquery ? container[0] : container;
        const root = el && ( el.matches && el.matches( '.wpsl-group-window' ) ? el : el.querySelector && el.querySelector( '.wpsl-group-window' ) );

        if ( ! root ) {
            return;
        }

        root._wpslGroupContext = context || {};

        // Also paints the state the HTML opened with ( counter, hidden slides ).
        this.render( root );

        // Google Maps sizes its window after the content is in the page.
        window.requestAnimationFrame( () => fitList( root ) );

        if ( root._wpslGroupBound ) {
            return;
        }

        root._wpslGroupBound = true;

        root.addEventListener( 'click', ( e ) => {
            const target = e.target.closest( 'button' );

            if ( ! target || ! root.contains( target ) ) {
                return;
            }

            if ( target.classList.contains( 'wpsl-group-prev' ) ) {
                e.preventDefault();
                this.go( root, this.activeIndex( root ) - 1 );
            } else if ( target.classList.contains( 'wpsl-group-next' ) ) {
                e.preventDefault();
                this.go( root, this.activeIndex( root ) + 1 );
            } else if ( target.classList.contains( 'wpsl-group-pick' ) ) {
                e.preventDefault();
                this.go( root, parseInt( target.getAttribute( 'data-index' ), 10 ) || 0, { focus: true } );
            } else if ( target.classList.contains( 'wpsl-group-all' ) ) {
                e.preventDefault();
                root.setAttribute( 'data-view', 'list' );
                this.render( root );

                const first = root.querySelector( '.wpsl-group-pick' );

                if ( first ) {
                    first.focus( { preventScroll: true } );
                    scrollToTop( root );
                }
            }
        });

        // Arrow keys move between the locations while the focus is on the buttons.
        root.addEventListener( 'keydown', ( e ) => {
            if ( ( e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' ) || ! e.target.closest( '.wpsl-group-nav' ) ) {
                return;
            }

            e.preventDefault();
            this.go( root, this.activeIndex( root ) + ( e.key === 'ArrowLeft' ? -1 : 1 ), { keepFocus: true } );
        });

        // The list scrolls on its own. Keep the wheel and a touch drag in it
        // from also zooming or panning the map ( Mapbox listens on the map container ).
        const listEl = root.querySelector( '.wpsl-group-list' );

        if ( listEl ) {
            [ 'wheel', 'touchmove' ].forEach( ( type ) => {
                listEl.addEventListener( type, ( e ) => e.stopPropagation(), { passive: true } );
            });
        }

        // Swipe between the locations on a touch screen.
        const body = root.querySelector( '.wpsl-group-body' );
        let start  = null;

        if ( body ) {
            body.addEventListener( 'touchstart', ( e ) => {
                start = e.touches.length === 1 ? { x: e.touches[0].clientX, y: e.touches[0].clientY } : null;
            }, { passive: true } );

            body.addEventListener( 'touchend', ( e ) => {
                if ( ! start || root.getAttribute( 'data-view' ) !== 'slide' || ! e.changedTouches.length ) {
                    return;
                }

                const dx = e.changedTouches[0].clientX - start.x;
                const dy = e.changedTouches[0].clientY - start.y;

                start = null;

                if ( Math.abs( dx ) >= SWIPE_DISTANCE && Math.abs( dx ) > Math.abs( dy ) ) {
                    this.go( root, this.activeIndex( root ) + ( dx < 0 ? 1 : -1 ) );
                }
            }, { passive: true } );
        }
    },

    /**
     * The index of the location the window shows.
     *
     * @since  3.1.1
     * @param  {Element} root The .wpsl-group-window element
     * @return {number}
     */
    activeIndex: function( root ) {
        return parseInt( root.getAttribute( 'data-active-index' ), 10 ) || 0;
    },

    /**
     * Show one location.
     *
     * @since  3.1.1
     * @param  {Element} root    The .wpsl-group-window element
     * @param  {number}  index   The location to show
     * @param  {object}  options focus: move the focus to the location, keepFocus: keep it on the pressed button
     * @return {void}
     */
    go: function( root, index, options = {} ) {
        const count    = parseInt( root.getAttribute( 'data-group-size' ), 10 ) || 1;
        const next     = Math.min( Math.max( 0, index ), count - 1 );
        const changed  = next !== this.activeIndex( root ) || root.getAttribute( 'data-view' ) !== 'slide';
        const focused  = root.ownerDocument.activeElement;

        root.setAttribute( 'data-active-index', next );
        root.setAttribute( 'data-view', 'slide' );

        this.render( root );

        if ( options.focus ) {
            const slide = root.querySelector( '.wpsl-group-slide.wpsl-active' );

            if ( slide ) {
                slide.setAttribute( 'tabindex', '-1' );
                slide.focus( { preventScroll: true } );
                scrollToTop( root );
            }
        } else if ( focused && root.contains( focused ) && focused.disabled ) {

            // The pressed button is disabled at the first / last location, move the focus to the other one.
            const other = root.querySelector( focused.classList.contains( 'wpsl-group-prev' ) ? '.wpsl-group-next' : '.wpsl-group-prev' );

            if ( other && ! other.disabled ) {
                other.focus();
            }
        }

        if ( ! changed ) {
            return;
        }

        const context = root._wpslGroupContext || {};
        const store   = context.stores ? context.stores[ next ] : null;

        /**
         * Fires when the info window of locations at one address moves to
         * another location. The location it opens on is announced by
         * wpslMarkerClicked instead.
         *
         * @since 3.1.1
         * @param {object} marker The marker
         * @param {object} store  The location data
         * @param {object} map    The map object
         * @param {number} index  The position of the location in the window
         */
        wp.hooks.doAction( 'wpslMarkerGroupSlideShown', context.marker, store, context.map, next );
    },

    /**
     * Bring the window in line with its data-active-index and data-view.
     *
     * @since  3.1.1
     * @param  {Element} root The .wpsl-group-window element
     * @return {void}
     */
    render: function( root ) {
        const count  = parseInt( root.getAttribute( 'data-group-size' ), 10 ) || 1;
        const active = this.activeIndex( root );
        const list   = root.getAttribute( 'data-view' ) === 'list';

        root.querySelectorAll( '.wpsl-group-slide' ).forEach( ( slide ) => {
            const current = ! list && parseInt( slide.getAttribute( 'data-index' ), 10 ) === active;

            slide.classList.toggle( 'wpsl-active', current );
            setHidden( slide, ! current );
        });

        const listEl = root.querySelector( '.wpsl-group-list' );

        if ( listEl ) {
            listEl.classList.toggle( 'wpsl-active', list );
            setHidden( listEl, ! list );
        }

        const prev    = root.querySelector( '.wpsl-group-prev' );
        const next    = root.querySelector( '.wpsl-group-next' );
        const all     = root.querySelector( '.wpsl-group-all' );
        const counter = root.querySelector( '.wpsl-group-counter' );

        if ( prev ) {
            prev.disabled = list || active === 0;
            prev.hidden   = list;
        }

        if ( next ) {
            next.disabled = list || active >= count - 1;
            next.hidden   = list;
        }

        if ( all ) {
            all.hidden = list;
        }

        if ( counter ) {
            counter.textContent = list ? '' : slideLabel( active, count );
        }

        fitList( root );
    }
};

/**
 * Shrink the list when the window has less room than three locations need.
 *
 * Google Maps caps the height of its info window to the map and then
 * scrolls the whole window, a second scrollbar next to the list's own. The
 * list gives up the overflow instead, down to one and a half locations.
 * Leaflet and Mapbox don't cap the height, so nothing changes there.
 *
 * @since  3.1.1
 * @param  {Element} root The .wpsl-group-window element
 * @return {void}
 */
function fitList( root ) {
    const list = root.querySelector( '.wpsl-group-list' );

    if ( ! list ) {
        return;
    }

    list.style.maxHeight = '';

    if ( root.getAttribute( 'data-view' ) !== 'list' ) {
        return;
    }

    let box = root.parentElement;

    // The provider's scroll box sits a few levels up, the page is never touched.
    for ( let i = 0; box && i < 5 && box !== root.ownerDocument.body; i++ ) {
        const overflowY = window.getComputedStyle( box ).overflowY;

        if ( ( overflowY === 'auto' || overflowY === 'scroll' ) && box.scrollHeight > box.clientHeight ) {
            const item     = list.querySelector( 'li' );
            const minimum  = item ? Math.round( item.offsetHeight * 1.5 ) : 60;
            const overflow = box.scrollHeight - box.clientHeight;

            list.style.maxHeight = Math.max( list.clientHeight - overflow, minimum ) + 'px';

            return;
        }

        box = box.parentElement;
    }
}

/**
 * A store value as HTML, escaped once. Decoded first: the name arrives
 * escaped already ( esc_html ), the address as plain text, and both must
 * read the same as in the info window.
 *
 * @since  3.1.1
 * @param  {string} value The store name or address
 * @return {string} HTML
 */
function htmlText( value ) {
    return sharedHelpers.escapeHtml( helpers.decodeHtmlEntity( String( value ?? '' ) ) );
}

/**
 * A frontend label, with a fallback for pages that don't print it.
 *
 * @since  3.1.1
 * @param  {string} key      The wpslLabels key
 * @param  {string} fallback The text without the label
 * @return {string} Plain text
 */
function label( key, fallback ) {
    return ( typeof wpslLabels !== 'undefined' && typeof wpslLabels[ key ] === 'string' && wpslLabels[ key ] ) ? wpslLabels[ key ] : fallback;
}

/**
 * "2 of 3" for a location in the window.
 *
 * @since  3.1.1
 * @param  {number} index The zero-based position
 * @param  {number} count The number of locations
 * @return {string} Plain text
 */
function slideLabel( index, count ) {
    return label( 'groupSlide', '%1$d of %2$d' ).replace( '%1$d', index + 1 ).replace( '%2$d', count );
}

/**
 * Scroll the info window back to its header. A long list can make the
 * provider's popup scroll, and the header holds the way back.
 *
 * @since  3.1.1
 * @param  {Element} root The .wpsl-group-window element
 * @return {void}
 */
function scrollToTop( root ) {
    let el = root.parentElement;

    // The popup's own scroll box sits a few levels up, the page is never touched.
    for ( let i = 0; el && i < 5 && el !== root.ownerDocument.body; i++ ) {
        if ( el.scrollTop > 0 ) {
            el.scrollTop = 0;
        }

        el = el.parentElement;
    }
}

/**
 * The attributes setHidden() gives a hidden part, for the HTML.
 *
 * @since  3.1.1
 * @param  {boolean} hidden Whether the part is hidden
 * @return {string}
 */
function hiddenAttributes( hidden ) {
    return hidden ? ' aria-hidden="true" inert' : '';
}

/**
 * Hide a part of the window from view, the keyboard and screen readers,
 * while it keeps its place in the layout so the window keeps its size.
 *
 * @since  3.1.1
 * @param  {Element} el     The element
 * @param  {boolean} hidden Whether to hide it
 * @return {void}
 */
function setHidden( el, hidden ) {
    if ( hidden ) {
        el.setAttribute( 'aria-hidden', 'true' );
        el.setAttribute( 'inert', '' );
    } else {
        el.removeAttribute( 'aria-hidden' );
        el.removeAttribute( 'inert' );
    }
}
