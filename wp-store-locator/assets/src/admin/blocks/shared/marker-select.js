/**
 * The marker dropdown both blocks share.
 *
 * Mirrors the PHP Marker_Manager::render_marker_dropdown() so it inherits the
 * same .wpsl-lm-* styles, with the open / select / close behavior of
 * wpslSharedFuncs.initMarkerDropdowns() as component state. The "create
 * marker" links are omitted on purpose: that's Marker Studio's job.
 *
 * @since 3.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * A marker image, height-capped per shape like the PHP control's rows --
 * cap 0 means the stylesheet's own cap already fits.
 */
function MarkerArt( { src, cap } ) {
    return (
        <span className="wpsl-lm-art">
            <img src={ src } alt="" style={ cap ? { maxHeight: cap + 'px' } : undefined } />
        </span>
    );
}

export default function MarkerSelect( { label, value, markers, fallback, onChange } ) {
    const [ isOpen, setIsOpen ] = useState( false );
    const wrapRef = useRef( null );

    // The two dismissals the shared jQuery handlers give the PHP control.
    useEffect( () => {
        if ( ! isOpen ) {
            return undefined;
        }

        const onDocumentClick = ( event ) => {
            if ( wrapRef.current && ! wrapRef.current.contains( event.target ) ) {
                setIsOpen( false );
            }
        };

        const onKeyDown = ( event ) => {
            if ( 'Escape' === event.key ) {
                setIsOpen( false );
            }
        };

        document.addEventListener( 'mousedown', onDocumentClick );
        document.addEventListener( 'keydown', onKeyDown );

        return () => {
            document.removeEventListener( 'mousedown', onDocumentClick );
            document.removeEventListener( 'keydown', onKeyDown );
        };
    }, [ isOpen ] );

    const markerList = markers || [];
    const bundled    = markerList.filter( ( marker ) => 'bundled' === marker.group );
    const custom     = markerList.filter( ( marker ) => 'custom' === marker.group );

    // A stale value that no longer resolves renders as Default, like in PHP.
    const selected     = markerList.find( ( marker ) => marker.value === value );
    const selectedName = selected ? selected.label : __( 'Default', 'wp-store-locator' );
    const selectedSrc  = selected ? selected.src : ( fallback ? fallback.src : '' );
    const selectedCap  = selected ? selected.cap : ( fallback ? fallback.cap : 0 );

    const pick = ( markerValue ) => {
        onChange( markerValue );
        setIsOpen( false );
    };

    const item = ( marker ) => (
        <button
            type="button"
            key={ marker.value }
            className={ 'wpsl-lm-item' + ( marker.value === value ? ' selected' : '' ) }
            onClick={ () => pick( marker.value ) }
        >
            <MarkerArt src={ marker.src } cap={ marker.cap } />
            <span>{ marker.label }</span>
        </button>
    );

    return (
        <div className="wpsl-block-marker-select" ref={ wrapRef }>
            <p className="wpsl-lm-type-label">{ label }</p>
            <div className="wpsl-lm-dropdown">
                <button
                    type="button"
                    className="wpsl-lm-toggle"
                    aria-expanded={ isOpen }
                    aria-haspopup="true"
                    onClick={ () => setIsOpen( ! isOpen ) }
                >
                    <MarkerArt src={ selectedSrc } cap={ selectedCap } />
                    <span className="wpsl-lm-name">{ selectedName }</span>
                    <span className="wpsl-lm-arrow"></span>
                </button>
                <div className="wpsl-lm-menu" hidden={ ! isOpen }>
                    <button
                        type="button"
                        className={ 'wpsl-lm-item wpsl-lm-default' + ( ! selected ? ' selected' : '' ) }
                        onClick={ () => pick( '' ) }
                    >
                        <MarkerArt src={ fallback ? fallback.src : '' } cap={ fallback ? fallback.cap : 0 } />
                        <span>
                            { __( 'Default', 'wp-store-locator' ) }
                            <small>{ __( 'from settings', 'wp-store-locator' ) }</small>
                        </span>
                    </button>

                    { bundled.length > 0 && (
                        <>
                            <div className="wpsl-lm-group">{ __( 'Default markers', 'wp-store-locator' ) }</div>
                            { bundled.map( item ) }
                        </>
                    ) }

                    { custom.length > 0 && (
                        <>
                            <div className="wpsl-lm-group">{ __( 'Custom markers', 'wp-store-locator' ) }</div>
                            { custom.map( item ) }
                        </>
                    ) }
                </div>
            </div>
        </div>
    );
}