/**
 * The editor's data sources, shared by both blocks.
 *
 * useBlockData() fetches wpsl/v1/block-data through a module-scoped cached
 * promise. The payload carries every marker's inline SVG data URI, so it is
 * fetched at most once per editor session, and only when a WPSL block is on
 * the canvas. A failed fetch clears the cache so the next mount can retry.
 *
 * useStoreCategories() reads core's terms endpoint instead: wpsl_store_category
 * is show_in_rest, so a second copy in our payload would only go stale.
 *
 * @since 3.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import apiFetch from '@wordpress/api-fetch';

let cachedPromise = null;

/**
 * The block-data payload, fetched once per editor session.
 *
 * @since  3.0.0
 * @return {Promise} Resolves to { templates, map_types, markers }.
 */
export function fetchBlockData() {
    if ( ! cachedPromise ) {
        cachedPromise = apiFetch( { path: '/wpsl/v1/block-data' } ).catch( ( error ) => {
            cachedPromise = null;

            throw error;
        } );
    }

    return cachedPromise;
}

/**
 * The payload as component state.
 *
 * @since  3.0.0
 * @return {Object} { data, isLoading } -- data stays null on failure.
 */
export function useBlockData() {
    const [ data, setData ] = useState( null );
    const [ isLoading, setIsLoading ] = useState( true );

    useEffect( () => {
        let isMounted = true;

        fetchBlockData()
            .then( ( payload ) => {
                if ( isMounted ) {
                    setData( payload );
                    setIsLoading( false );
                }
            } )
            .catch( () => {
                if ( isMounted ) {
                    setIsLoading( false );
                }
            } );

        return () => {
            isMounted = false;
        };
    }, [] );

    return { data, isLoading };
}

/**
 * The store categories as { slug, name } pairs, from core's terms endpoint.
 *
 * @since  3.0.0
 * @return {Array} Empty while loading -- the panels render without it.
 */
export function useStoreCategories() {
    return useSelect( ( select ) => {
        const terms = select( coreStore ).getEntityRecords( 'taxonomy', 'wpsl_store_category', { per_page: -1 } );

        return ( terms || [] ).map( ( term ) => ( {
            slug: term.slug,
            name: term.name,
        } ) );
    }, [] );
}