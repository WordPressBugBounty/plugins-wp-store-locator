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
        id,
        category,
        width,
        height,
        zoom,
        map_type,
        map_type_control,
        map_style,
        street_view,
        scrollwheel,
        control_position,
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
                <Placeholder icon="location-alt" label={ __( 'WP Store Locator Map', 'wp-store-locator' ) }>
                    <Spinner />
                </Placeholder>
            </div>
        );
    }

    if ( ! blockData ) {
        return (
            <div { ...blockProps }>
                <Placeholder icon="location-alt" label={ __( 'WP Store Locator Map', 'wp-store-locator' ) }>
                    { __( 'Failed to load block data.', 'wp-store-locator' ) }
                </Placeholder>
            </div>
        );
    }

    // Map type options
    const mapTypeOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...Object.keys( blockData.map_types ).map( ( key ) => ( {
            label: blockData.map_types[ key ],
            value: key,
        } ) ),
    ];

    // Category options from REST data
    const categoryOptions = blockData.categories || [];

    // Toggle category selection
    const toggleCategory = ( slug ) => {
        const newCats = category.includes( slug )
            ? category.filter( ( c ) => c !== slug )
            : [ ...category, slug ];
        setAttributes( { category: newCats } );
    };

    // Zoom level options (1–21)
    const zoomOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
    ];
    for ( let i = 1; i <= 21; i++ ) {
        zoomOptions.push( { label: String( i ), value: String( i ) } );
    }

    // Boolean/tristate options
    const tristateOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        { label: __( 'Enabled', 'wp-store-locator' ), value: '1' },
        { label: __( 'Disabled', 'wp-store-locator' ), value: '0' },
    ];

    // Map style options
    const mapStyleOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        { label: __( 'Default Google style', 'wp-store-locator' ), value: 'default' },
    ];

    // Control position options
    const controlPositionOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        { label: __( 'Left', 'wp-store-locator' ), value: 'left' },
        { label: __( 'Right', 'wp-store-locator' ), value: 'right' },
    ];

    // Build shortcode preview string
    let shortcodePreview = '[wpsl_map';
    if ( id ) shortcodePreview += ` id="${ id }"`;
    if ( category.length ) shortcodePreview += ` category="${ category.join( ',' ) }"`;
    if ( width ) shortcodePreview += ` width="${ width }"`;
    if ( height ) shortcodePreview += ` height="${ height }"`;
    if ( zoom ) shortcodePreview += ` zoom="${ zoom }"`;
    if ( map_type ) shortcodePreview += ` map_type="${ map_type }"`;
    if ( map_type_control ) shortcodePreview += ` map_type_control="${ map_type_control }"`;
    if ( map_style ) shortcodePreview += ` map_style="${ map_style }"`;
    if ( street_view ) shortcodePreview += ` street_view="${ street_view }"`;
    if ( scrollwheel ) shortcodePreview += ` scrollwheel="${ scrollwheel }"`;
    if ( control_position ) shortcodePreview += ` control_position="${ control_position }"`;
    shortcodePreview += ']';

    return (
        <>
            <InspectorControls>
                <PanelBody title={ __( 'Store Selection', 'wp-store-locator' ) } initialOpen={ true }>
                    <TextControl
                        label={ __( 'Store ID(s)', 'wp-store-locator' ) }
                        help={ __( 'Comma-separated store IDs. Leave empty when used on a store page.', 'wp-store-locator' ) }
                        value={ id }
                        onChange={ ( val ) => setAttributes( { id: val } ) }
                    />
                    { categoryOptions.length > 0 && (
                        <div className="wpsl-block-category-restriction">
                            <p className="components-base-control__label">
                                { __( 'Restrict to categories', 'wp-store-locator' ) }
                            </p>
                            <p className="components-base-control__help">
                                { __( 'Select one or more categories. Overrides store IDs.', 'wp-store-locator' ) }
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
                    ) }
                </PanelBody>

                <PanelBody title={ __( 'Map Dimensions', 'wp-store-locator' ) } initialOpen={ false }>
                    <TextControl
                        label={ __( 'Width (px)', 'wp-store-locator' ) }
                        help={ __( 'Leave empty for 100% width.', 'wp-store-locator' ) }
                        value={ width }
                        type="number"
                        onChange={ ( val ) => setAttributes( { width: val } ) }
                    />
                    <TextControl
                        label={ __( 'Height (px)', 'wp-store-locator' ) }
                        help={ __( 'Leave empty to use the height from the settings page.', 'wp-store-locator' ) }
                        value={ height }
                        type="number"
                        onChange={ ( val ) => setAttributes( { height: val } ) }
                    />
                </PanelBody>

                <PanelBody title={ __( 'Map Options', 'wp-store-locator' ) } initialOpen={ false }>
                    <SelectControl
                        label={ __( 'Zoom level', 'wp-store-locator' ) }
                        help={ __( 'Ignored when category is set (auto-fit is used).', 'wp-store-locator' ) }
                        value={ zoom }
                        options={ zoomOptions }
                        onChange={ ( val ) => setAttributes( { zoom: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Map type', 'wp-store-locator' ) }
                        value={ map_type }
                        options={ mapTypeOptions }
                        onChange={ ( val ) => setAttributes( { map_type: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Map type control', 'wp-store-locator' ) }
                        value={ map_type_control }
                        options={ tristateOptions }
                        onChange={ ( val ) => setAttributes( { map_type_control: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Map style', 'wp-store-locator' ) }
                        help={ __( 'Set to "Default Google style" to ignore the custom map style from settings.', 'wp-store-locator' ) }
                        value={ map_style }
                        options={ mapStyleOptions }
                        onChange={ ( val ) => setAttributes( { map_style: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Street view', 'wp-store-locator' ) }
                        value={ street_view }
                        options={ tristateOptions }
                        onChange={ ( val ) => setAttributes( { street_view: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Scrollwheel zoom', 'wp-store-locator' ) }
                        value={ scrollwheel }
                        options={ tristateOptions }
                        onChange={ ( val ) => setAttributes( { scrollwheel: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Control position', 'wp-store-locator' ) }
                        value={ control_position }
                        options={ controlPositionOptions }
                        onChange={ ( val ) => setAttributes( { control_position: val } ) }
                    />
                </PanelBody>
            </InspectorControls>

            <div { ...blockProps }>
                <div className="wpsl-block-preview">
                    <div className="wpsl-block-preview-header">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 572 1000" width="20" height="20" className="wpsl-block-icon"><path d="M286 0C128 0 0 128 0 286c0 41 12 81 18 100l204 432c9 18 26 29 26 29s11 11 38 11 37-11 37-11 18-11 27-29l203-432c6-19 18-59 18-100C571 128 443 0 286 0zm0 429c-79 0-143-64-143-143s64-143 143-143 143 64 143 143-64 143-143 143z" fill="currentColor" /></svg>
                        <span>{ __( 'WP Store Locator Map', 'wp-store-locator' ) }</span>
                    </div>
                    <code className="wpsl-block-preview-shortcode">{ shortcodePreview }</code>
                    <p className="wpsl-block-preview-hint">
                        { __( 'Use the block settings panel on the right to configure the map options.', 'wp-store-locator' ) }
                    </p>
                </div>
            </div>
        </>
    );
}
