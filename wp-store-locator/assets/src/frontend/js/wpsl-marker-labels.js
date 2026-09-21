/**
 * Runtime marker labels: number ( or letter ) each search result's marker
 * to match its position in the result list, and put the same badge on the
 * list item.
 *
 * @since 3.0.0
 */
( function( $ ) {
    'use strict';

    const settings = window.wpslSettings;
    const label    = window.wpslMarkerLabel;

    if ( ! settings || typeof settings.markers !== 'object' || ! label || typeof wp === 'undefined' || ! wp.hooks ) {
        return;
    }

    const mode = settings.markers.labels;

    if ( 'numbers' !== mode && 'letters' !== mode ) {
        return;
    }

    const NS = 'wpsl/marker-labels';

    let counter   = 0;
    let searching = false;
    let labels    = {};
    let routeUrls = {};

    /**
     * Escape text for HTML.
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {string}
     */
    function escapeHtml( value ) {
        return String( value )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    wp.hooks.addFilter( 'wpslAjaxData', NS, function( ajaxData ) {
        counter   = 0;
        searching = true;
        labels    = {};
        routeUrls = {};

        return ajaxData;
    } );

    /*
     * A route's destination keeps its result label. Leaflet and Mapbox reuse
     * the result's own marker for it; Google Maps draws a new one, which
     * takes its labelled artwork from here.
     */
    wp.hooks.addFilter( 'wpslDirectionsMarkerUrl', NS, function( url, storeId ) {
        return routeUrls[ String( storeId ) ] || url;
    } );

    wp.hooks.addFilter( 'wpslMarkerProperties', NS, function( markerData ) {
        if ( ! searching || ! markerData || typeof markerData.id === 'undefined' || markerData.online ) {
            return markerData;
        }

        // The start marker is the user, not a result.
        if ( 0 === parseInt( markerData.id, 10 ) ) {
            return markerData;
        }

        const text = label.indexLabel( ++counter, mode );

        if ( ! text ) {
            return markerData;
        }

        // The list badge follows the position even when the marker's own
        // artwork cannot take a label, so the list stays 1, 2, 3.
        labels[ String( markerData.id ) ] = text;

        /*
         * Past three characters ( 1000, AAAA ) there is no room on the marker,
         * and cutting the label down would repeat a number another result
         * already has. The marker stays unlabelled; the list keeps the label.
         */
        if ( label.weight( text ) > label.MAX_WEIGHT ) {
            return markerData;
        }

        markerData.marker_label = text;

        const write = ( typeof window.mapboxgl !== 'undefined' )
            ? function( uri ) { return label.strip( uri ); }
            : function( uri ) { return label.apply( uri, text ); };

        const original = markerData.markerUrl;

        markerData.markerUrl = write( original );

        routeUrls[ String( markerData.id ) ] = markerData.markerUrl;

        // Unlabelled artwork ( bundled files, image markers ) keeps its
        // active image as it was.
        if ( markerData.markerUrl === original ) {
            return markerData;
        }

        // Label the active image too, written back as the marker's own so
        // every provider's setActive() picks it up unchanged.
        const active = markerData.locationMarkerUrlActive || markerData.categoryMarkerUrlActive || settings.markers.active;

        if ( typeof active === 'string' && /^data:/i.test( active ) ) {
            markerData.locationMarkerUrlActive = write( active );
        }

        return markerData;
    } );

    /**
     * Put each result's label on its list item.
     *
     * @since  3.0.0
     * @return void
     */
    function addBadges() {
        const $items = $( '#wpsl-stores li[data-store-id]' );

        /*
         * One layout for the whole list, decided by every result of this
         * search: up to 99 ( or ZZ ) the badges fit beside each result, past
         * that every badge goes above its result.
         */
        const stacked = Object.keys( labels ).some( function( id ) {
            return String( labels[ id ] ).length > 2;
        } );

        if ( ! Object.keys( labels ).length ) {
            return;
        }

        $items.each( function() {
            const $item = $( this );
            const text  = labels[ String( $item.attr( 'data-store-id' ) ) ];

            /*
             * Every result takes the badge column, so an unlabelled one
             * ( an online store ) still lines up with the labelled ones.
             */
            $item.addClass( 'wpsl-has-marker-label' );
            $item.toggleClass( 'wpsl-marker-label-stacked', stacked );

            if ( ! text ) {
                return;
            }

            const $badge = $item.find( '.wpsl-marker-label' );

            if ( $badge.length ) {
                $badge.text( text );

                return;
            }

            $item.prepend( '<span class="wpsl-marker-label">' + escapeHtml( text ) + '</span>' );
        } );
    }

    wp.hooks.addAction( 'wpslAjaxResultsFound', NS, function() {
        searching = false;
        addBadges();
    } );

    wp.hooks.addAction( 'wpslAjaxNoResultsFound', NS, function() {
        searching = false;
    } );

} )( jQuery );