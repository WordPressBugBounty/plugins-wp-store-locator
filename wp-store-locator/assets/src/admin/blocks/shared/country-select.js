/**
 * The country multiselect.
 *
 * Mirrors the settings page's country restriction control so it inherits the
 * same .wpsl-multiselect-* styles, with the open / check / tag / close
 * behavior of modules/settings/wpsl-multiselect.js as component state.
 *
 * The value is a comma-separated list of ISO codes. A code with no matching
 * option still gets a tag, so it can be seen and removed instead of vanishing.
 *
 * @since 3.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';

/**
 * The settings page's tag cross, markup for markup.
 */
function TagRemoveIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            />
        </svg>
    );
}

export default function CountrySelect( { label, value, options, placeholder, help, onChange } ) {
    const [ isOpen, setIsOpen ] = useState( false );
    const wrapRef = useRef( null );

    // The two dismissals the settings page's handlers give the control there.
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

    const countryList = options || [];
    const selected    = value ? value.split( ',' ).map( ( code ) => code.trim() ).filter( Boolean ) : [];

    const isSelected = ( code ) => selected.some( ( picked ) => picked.toLowerCase() === code.toLowerCase() );

    const labelFor = ( code ) => {
        const match = countryList.find( ( country ) => country.value === code.toLowerCase() );

        return match ? match.label : code;
    };

    const toggle = ( code ) => {
        const next = isSelected( code )
            ? selected.filter( ( picked ) => picked.toLowerCase() !== code.toLowerCase() )
            : [ ...selected, code ];

        onChange( next.join( ',' ) );
    };

    return (
        <div className="wpsl-block-country-select" ref={ wrapRef }>
            <p className="components-base-control__label">{ label }</p>

            <div className="wpsl-multiselect-container wpsl-block-multiselect">
                <button
                    type="button"
                    data-placeholder={ placeholder }
                    aria-expanded={ isOpen }
                    aria-haspopup="true"
                    onClick={ () => setIsOpen( ! isOpen ) }
                >
                    <div className="wpsl-multiselect-content">
                        { 0 === selected.length && (
                            <span className="wpsl-multiselect-placeholder">{ placeholder }</span>
                        ) }

                        { selected.map( ( code ) => (
                            <div className="wpsl-multiselect-tag" key={ code }>
                                <span className="wpsl-multiselect-tag-text">{ labelFor( code ) }</span>
                                <span
                                    className="wpsl-multiselect-tag-remove"
                                    data-value={ code }
                                    role="button"
                                    tabIndex={ 0 }
                                    onClick={ ( event ) => {
                                        // The cross sits inside the button
                                        // that opens the menu.
                                        event.stopPropagation();
                                        toggle( code );
                                    } }
                                    onKeyDown={ ( event ) => {
                                        if ( 'Enter' === event.key || ' ' === event.key ) {
                                            event.preventDefault();
                                            event.stopPropagation();
                                            toggle( code );
                                        }
                                    } }
                                >
                                    <TagRemoveIcon />
                                </span>
                            </div>
                        ) ) }
                    </div>
                </button>

                <ul className={ 'wpsl-multiselect-menu' + ( isOpen ? ' show' : '' ) }>
                    { countryList.map( ( country ) => (
                        <li key={ country.value }>
                            <label>
                                <input
                                    type="checkbox"
                                    value={ country.value }
                                    data-label={ country.label }
                                    checked={ isSelected( country.value ) }
                                    onChange={ () => toggle( country.value ) }
                                />
                                <span>{ country.label }</span>
                            </label>
                        </li>
                    ) ) }
                </ul>
            </div>

            { help && <p className="components-base-control__help">{ help }</p> }
        </div>
    );
}