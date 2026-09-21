/**
 * The store locator block's editor UI.
 *
 * @since 3.0.0
 */

import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextControl,
    CheckboxControl,
    ToggleControl,
    Placeholder,
    Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import CountrySelect from '../shared/country-select';
import MarkerSelect from '../shared/marker-select';
import { useBlockData, useStoreCategories } from '../shared/use-block-data';

export default function Edit( { attributes, setAttributes } ) {
    const {
        template,
        start_location,
        auto_locate,
        country,
        state,
        city,
        distance_unit,
        category,
        category_selection,
        category_filter_type,
        checkbox_columns,
        map_type,
        map_style,
        start_marker,
        store_marker,
        active_marker,
        marker_clusters,
        marker_labels,
        disable_shapes,
    } = attributes;

    const { data: blockData, isLoading } = useBlockData();
    const categoryOptions = useStoreCategories();

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

    const templateOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...blockData.templates.map( ( tpl ) => ( {
            label: tpl.name,
            value: tpl.id,
        } ) ),
    ];

    // Map type is Google-only: the Mapbox and Leaflet frontends never read it.
    const isGoogle = 'gmaps' === blockData.map_service;

    const mapTypeOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...Object.keys( blockData.map_types ).map( ( key ) => ( {
            label: blockData.map_types[ key ],
            value: key,
        } ) ),
    ];

    // wpsl_map_style_options() builds the list for the active map service, so
    // nothing is offered the shortcode can't resolve.
    const mapStyleOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        ...( blockData.map_styles || [] ).map( ( style ) => ( {
            label: style.label,
            value: style.value,
        } ) ),
    ];

    const distanceUnitOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        { label: __( 'km', 'wp-store-locator' ), value: 'km' },
        { label: __( 'mi', 'wp-store-locator' ), value: 'mi' },
    ];

    // "true" / "false", the strings the shortcode's boolean check reads;
    // "" leaves the settings page in charge.
    const clusterOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        { label: __( 'Enabled', 'wp-store-locator' ), value: 'true' },
        { label: __( 'Disabled', 'wp-store-locator' ), value: 'false' },
    ];

    // The values Marker_Label::MODES accepts; "" leaves the settings page in charge.
    const labelOptions = [
        { label: __( 'Default (from settings)', 'wp-store-locator' ), value: '' },
        { label: __( 'Off', 'wp-store-locator' ), value: 'none' },
        { label: __( 'Numbers (1, 2, 3)', 'wp-store-locator' ), value: 'numbers' },
        { label: __( 'Letters (A, B, C)', 'wp-store-locator' ), value: 'letters' },
    ];

    const filterTypeOptions = [
        { label: __( 'None', 'wp-store-locator' ), value: '' },
        { label: __( 'Dropdown', 'wp-store-locator' ), value: 'dropdown' },
        { label: __( 'Checkboxes', 'wp-store-locator' ), value: 'checkboxes' },
    ];

    const categorySelectionOptions = [
        { label: __( 'None', 'wp-store-locator' ), value: '' },
        ...categoryOptions.map( ( cat ) => ( {
            label: cat.name,
            value: cat.slug,
        } ) ),
    ];

    const checkboxColumnOptions = [
        { label: '1', value: '1' },
        { label: '2', value: '2' },
        { label: '3', value: '3' },
        { label: '4', value: '4' },
    ];

    /**
     * Toggle a slug in the category restriction. Restricting clears the filter
     * attributes, because the render callback drops them too.
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
     * Toggle a slug in the checkbox pre-selection ( comma-separated string ).
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

    // The same string the render callback assembles, shown as the preview.
    let shortcodePreview = '[wpsl';
    if ( template ) shortcodePreview += ` template="${ template }"`;
    if ( start_location ) shortcodePreview += ` start_location="${ start_location }"`;
    if ( auto_locate === 'true' ) shortcodePreview += ' auto_locate="true"';
    if ( auto_locate === 'false' ) shortcodePreview += ' auto_locate="false"';
    if ( marker_clusters === 'true' ) shortcodePreview += ' marker_clusters="true"';
    if ( marker_clusters === 'false' ) shortcodePreview += ' marker_clusters="false"';
    if ( country ) shortcodePreview += ` country="${ country }"`;
    if ( state ) shortcodePreview += ` state="${ state }"`;
    if ( city ) shortcodePreview += ` city="${ city }"`;
    if ( distance_unit ) shortcodePreview += ` distance_unit="${ distance_unit }"`;
    if ( category.length ) shortcodePreview += ` category="${ category.join( ',' ) }"`;
    if ( ! category.length && category_filter_type ) shortcodePreview += ` category_filter_type="${ category_filter_type }"`;
    if ( ! category.length && category_selection ) shortcodePreview += ` category_selection="${ category_selection }"`;
    if ( ! category.length && category_filter_type === 'checkboxes' && checkbox_columns !== '3' ) shortcodePreview += ` checkbox_columns="${ checkbox_columns }"`;
    if ( map_type ) shortcodePreview += ` map_type="${ map_type }"`;
    if ( map_style ) shortcodePreview += ` map_style="${ map_style }"`;
    if ( start_marker ) shortcodePreview += ` start_marker="${ start_marker }"`;
    if ( store_marker ) shortcodePreview += ` store_marker="${ store_marker }"`;
    if ( active_marker ) shortcodePreview += ` active_marker="${ active_marker }"`;
    if ( marker_labels ) shortcodePreview += ` marker_labels="${ marker_labels }"`;
    if ( disable_shapes ) shortcodePreview += ' shapes="false"';
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
                    { isGoogle && (
                        <SelectControl
                            label={ __( 'Map type', 'wp-store-locator' ) }
                            value={ map_type }
                            options={ mapTypeOptions }
                            onChange={ ( val ) => setAttributes( { map_type: val } ) }
                        />
                    ) }
                    <SelectControl
                        label={ __( 'Map style', 'wp-store-locator' ) }
                        value={ map_style }
                        options={ mapStyleOptions }
                        onChange={ ( val ) => setAttributes( { map_style: val } ) }
                    />
                    { blockData.has_shapes && (
                        <ToggleControl
                            label={ __( 'Disable shapes', 'wp-store-locator' ) }
                            help={ __( 'Prevent admin-defined map shapes from displaying on this map.', 'wp-store-locator' ) }
                            checked={ !! disable_shapes }
                            onChange={ ( val ) => setAttributes( { disable_shapes: val } ) }
                        />
                    ) }
                </PanelBody>

                <PanelBody title={ __( 'Search Options', 'wp-store-locator' ) } initialOpen={ false }>
                    <CountrySelect
                        label={ __( 'Country', 'wp-store-locator' ) }
                        placeholder={ __( 'Select your country(s)', 'wp-store-locator' ) }
                        help={ __( 'Restricts the search results to stores in these countries. Combine it with the state and city fields for a narrower area.', 'wp-store-locator' ) }
                        value={ country }
                        options={ blockData.countries }
                        onChange={ ( val ) => setAttributes( { country: val } ) }
                    />
                    <TextControl
                        label={ __( 'State', 'wp-store-locator' ) }
                        value={ state }
                        onChange={ ( val ) => setAttributes( { state: val } ) }
                    />
                    <TextControl
                        label={ __( 'City', 'wp-store-locator' ) }
                        value={ city }
                        onChange={ ( val ) => setAttributes( { city: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Distance unit', 'wp-store-locator' ) }
                        value={ distance_unit }
                        options={ distanceUnitOptions }
                        onChange={ ( val ) => setAttributes( { distance_unit: val } ) }
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
                    <MarkerSelect
                        label={ __( 'Start location marker', 'wp-store-locator' ) }
                        value={ start_marker }
                        markers={ blockData.markers }
                        fallback={ blockData.defaults && blockData.defaults.start }
                        onChange={ ( val ) => setAttributes( { start_marker: val } ) }
                    />
                    <MarkerSelect
                        label={ __( 'Store location marker', 'wp-store-locator' ) }
                        value={ store_marker }
                        markers={ blockData.markers }
                        fallback={ blockData.defaults && blockData.defaults.store }
                        onChange={ ( val ) => setAttributes( { store_marker: val } ) }
                    />
                    <MarkerSelect
                        label={ __( 'Active store marker', 'wp-store-locator' ) }
                        value={ active_marker }
                        markers={ blockData.markers }
                        fallback={ blockData.defaults && blockData.defaults.active }
                        onChange={ ( val ) => setAttributes( { active_marker: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Marker clusters', 'wp-store-locator' ) }
                        help={ __( 'Group nearby markers into a cluster. Recommended for maps with a large amount of markers.', 'wp-store-locator' ) }
                        value={ marker_clusters }
                        options={ clusterOptions }
                        onChange={ ( val ) => setAttributes( { marker_clusters: val } ) }
                    />
                    <SelectControl
                        label={ __( 'Marker labels', 'wp-store-locator' ) }
                        help={ __( 'Number or letter each marker to match its position in the search results.', 'wp-store-locator' ) }
                        value={ marker_labels }
                        options={ labelOptions }
                        onChange={ ( val ) => setAttributes( { marker_labels: val } ) }
                    />
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