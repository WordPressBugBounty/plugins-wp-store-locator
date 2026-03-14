import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextControl,
    CheckboxControl,
    Placeholder,
    Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
    const {
        template,
        start_location,
        auto_locate,
        category,
        category_selection,
        category_filter_type,
        checkbox_columns,
        map_type,
        start_marker,
        store_marker,
    } = attributes;

    const [ blockData, setBlockData ] = useState( null );
    const [ isLoading, setIsLoading ] = useState( true );

    useEffect( () => {
        apiFetch( { path: '/wpsl/v1/block-data' } )
            .then( ( data ) => {
                setBlockData( data );
                setIsLoading( false );
            } )
            .catch( () => {
                setIsLoading( false );
            } );
    }, [] );

    const blockProps = useBlockProps();

    if ( isLoading ) {
        return (
            <div { ...blockProps }>
                <Placeholder icon="location-alt" label={ __( 'WP Store Locator', 'wp-store-locator' ) }>
                    <Spinner />
                </Placeholder>
            </div>
        );
    }

    if ( ! blockData ) {
        return (
            <div { ...blockProps }>
                <Placeholder icon="location-alt" label={ __( 'WP Store Locator', 'wp-store-locator' ) }>
                    { __( 'Failed to load block data.', 'wp-store-locator' ) }
                </Placeholder>
            </div>
        );
    }

    // Template options
    const templateOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...blockData.templates.map( ( tpl ) => ( {
            label: tpl.name,
            value: tpl.id,
        } ) ),
    ];

    // Map type options
    const mapTypeOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...Object.keys( blockData.map_types ).map( ( key ) => ( {
            label: blockData.map_types[ key ],
            value: key,
        } ) ),
    ];

    // Category filter type options
    const filterTypeOptions = [
        { label: __( 'None', 'wp-store-locator' ), value: '' },
        { label: __( 'Dropdown', 'wp-store-locator' ), value: 'dropdown' },
        { label: __( 'Checkboxes', 'wp-store-locator' ), value: 'checkboxes' },
    ];

    // Category options from REST data
    const categoryOptions = blockData.categories || [];

    // Single-select category options (for dropdown pre-selection)
    const categorySelectionOptions = [
        { label: __( 'None', 'wp-store-locator' ), value: '' },
        ...categoryOptions.map( ( cat ) => ( {
            label: cat.name,
            value: cat.slug,
        } ) ),
    ];

    // Checkbox column options
    const checkboxColumnOptions = [
        { label: '1', value: '1' },
        { label: '2', value: '2' },
        { label: '3', value: '3' },
        { label: '4', value: '4' },
    ];

    // Marker options
    const markerOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...( blockData.markers || [] ).map( ( marker ) => ( {
            label: marker.replace( '.png', '' ),
            value: marker,
        } ) ),
    ];

    /**
     * Render marker preview image.
     *
     * @param {string} value Marker filename.
     * @return {JSX.Element|null} Image element or null.
     */
    const MarkerPreview = ( { value } ) => {
        if ( ! value || ! blockData.marker_url ) {
            return null;
        }

        return (
            <img
                src={ blockData.marker_url + value }
                alt={ value }
                className="wpsl-block-marker-preview"
            />
        );
    };

    /**
     * Toggle a value in the category restriction array.
     *
     * @param {string} slug Category slug.
     */
    const toggleCategory = ( slug ) => {
        const newCats = category.includes( slug )
            ? category.filter( ( c ) => c !== slug )
            : [ ...category, slug ];

        const newAttrs = { category: newCats };

        if ( newCats.length > 0 ) {
            newAttrs.category_filter_type = '';
            newAttrs.category_selection = '';
            newAttrs.checkbox_columns = '3';
        }

        setAttributes( newAttrs );
    };

    /**
     * Toggle a value in the checkbox pre-selection (comma-separated string).
     *
     * @param {string} slug Category slug.
     */
    const toggleCheckboxSelection = ( slug ) => {
        const selCats = category_selection ? category_selection.split( ',' ) : [];
        const newSel = selCats.includes( slug )
            ? selCats.filter( ( c ) => c !== slug )
            : [ ...selCats, slug ];

        setAttributes( { category_selection: newSel.join( ',' ) } );
    };

    // Build shortcode preview string
    let shortcodePreview = '[wpsl';
    if ( template ) shortcodePreview += ` template="${ template }"`;
    if ( start_location ) shortcodePreview += ` start_location="${ start_location }"`;
    if ( auto_locate === 'true' ) shortcodePreview += ' auto_locate="true"';
    if ( auto_locate === 'false' ) shortcodePreview += ' auto_locate="false"';
    if ( category.length ) shortcodePreview += ` category="${ category.join( ',' ) }"`;
    if ( ! category.length && category_filter_type ) shortcodePreview += ` category_filter_type="${ category_filter_type }"`;
    if ( ! category.length && category_selection ) shortcodePreview += ` category_selection="${ category_selection }"`;
    if ( ! category.length && category_filter_type === 'checkboxes' && checkbox_columns !== '3' ) shortcodePreview += ` checkbox_columns="${ checkbox_columns }"`;
    if ( map_type ) shortcodePreview += ` map_type="${ map_type }"`;
    if ( start_marker ) shortcodePreview += ` start_marker="${ start_marker }"`;
    if ( store_marker ) shortcodePreview += ` store_marker="${ store_marker }"`;
    shortcodePreview += ']';

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'General Options', 'wp-store-locator' ) } initialOpen={ true }>
                    <SelectControl
                        label={ __( 'Template', 'wp-store-locator' ) }
                        value={ template }
                        options={ templateOptions }
                        onChange={ ( val ) => setAttributes( { template: val } ) }
                    />
                    <TextControl
                        label={ __( 'Start point', 'wp-store-locator' ) }
                        help={ __( 'If empty, the start point from the settings page is used.', 'wp-store-locator' ) }
                        value={ start_location }
                        onChange={ ( val ) => setAttributes( { start_location: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Auto-locate the user', 'wp-store-locator' ) }
                        help={ __( 'Requires HTTPS.', 'wp-store-locator' ) }
                        value={ auto_locate }
                        options={ [
                            { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
                            { label: __( 'Yes', 'wp-store-locator' ), value: 'true' },
                            { label: __( 'No', 'wp-store-locator' ), value: 'false' },
                        ] }
                        onChange={ ( val ) => setAttributes( { auto_locate: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Map type', 'wp-store-locator' ) }
                        value={ map_type }
                        options={ mapTypeOptions }
                        onChange={ ( val ) => setAttributes( { map_type: val } ) }
                    />
                </PanelBody>

                { categoryOptions.length > 0 && (
                    <PanelBody title={ __( 'Category Settings', 'wp-store-locator' ) } initialOpen={ false }>
                        <div className="wpsl-block-category-restriction">
                            <p className="components-base-control__label">
                                { __( 'Restrict to categories', 'wp-store-locator' ) }
                            </p>
                            { categoryOptions.map( ( cat ) => (
                                <CheckboxControl
                                    key={ cat.slug }
                                    label={ cat.name }
                                    checked={ category.includes( cat.slug ) }
                                    onChange={ () => toggleCategory( cat.slug ) }
                                />
                            ) ) }
                        </div>

                        { ! category.length && (
                            <>
                                <SelectControl
                                    label={ __( 'Category filter type', 'wp-store-locator' ) }
                                    value={ category_filter_type }
                                    options={ filterTypeOptions }
                                    onChange={ ( val ) => setAttributes( { category_filter_type: val } ) }
                                />

                                { category_filter_type === 'dropdown' && (
                                    <SelectControl
                                        label={ __( 'Pre-selected category', 'wp-store-locator' ) }
                                        value={ category_selection }
                                        options={ categorySelectionOptions }
                                        onChange={ ( val ) => setAttributes( { category_selection: val } ) }
                                    />
                                ) }

                                { category_filter_type === 'checkboxes' && (
                                    <>
                                        <SelectControl
                                            label={ __( 'Checkbox columns', 'wp-store-locator' ) }
                                            value={ checkbox_columns }
                                            options={ checkboxColumnOptions }
                                            onChange={ ( val ) => setAttributes( { checkbox_columns: val } ) }
                                        />
                                        <div className="wpsl-block-category-restriction">
                                            <p className="components-base-control__label">
                                                { __( 'Pre-selected checkboxes', 'wp-store-locator' ) }
                                            </p>
                                            { categoryOptions.map( ( cat ) => {
                                                const selCats = category_selection ? category_selection.split( ',' ) : [];
                                                return (
                                                    <CheckboxControl
                                                        key={ 'sel-' + cat.slug }
                                                        label={ cat.name }
                                                        checked={ selCats.includes( cat.slug ) }
                                                        onChange={ () => toggleCheckboxSelection( cat.slug ) }
                                                    />
                                                );
                                            } ) }
                                        </div>
                                    </>
                                ) }
                            </>
                        ) }
                    </PanelBody>
                ) }

                <PanelBody title={ __( 'Markers', 'wp-store-locator' ) } initialOpen={ false }>
                    <SelectControl
                        label={ __( 'Start location marker', 'wp-store-locator' ) }
                        value={ start_marker }
                        options={ markerOptions }
                        onChange={ ( val ) => setAttributes( { start_marker: val } ) }
                    />
                    <MarkerPreview value={ start_marker } />

                    <SelectControl
                        label={ __( 'Store location marker', 'wp-store-locator' ) }
                        value={ store_marker }
                        options={ markerOptions }
                        onChange={ ( val ) => setAttributes( { store_marker: val } ) }
                    />
                    <MarkerPreview value={ store_marker } />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <div className="wpsl-block-preview">
                    <div className="wpsl-block-preview-header">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 572 1000" width="20" height="20" className="wpsl-block-icon"><path d="M286 0C128 0 0 128 0 286c0 41 12 81 18 100l204 432c9 18 26 29 26 29s11 11 38 11 37-11 37-11 18-11 27-29l203-432c6-19 18-59 18-100C571 128 443 0 286 0zm0 429c-79 0-143-64-143-143s64-143 143-143 143 64 143 143-64 143-143 143z" fill="currentColor" /></svg>
                        <span>{ __( 'WP Store Locator', 'wp-store-locator' ) }</span>
                    </div>
                    <code className="wpsl-block-preview-shortcode">{ shortcodePreview }</code>
                    <p className="wpsl-block-preview-hint">
                        { __( 'Use the block settings panel on the right to configure the store locator options.', 'wp-store-locator' ) }
                    </p>
                </div>
            </div>
        </>
    );
}
