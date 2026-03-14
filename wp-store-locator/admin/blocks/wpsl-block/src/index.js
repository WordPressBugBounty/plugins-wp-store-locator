import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import metadata from '../block.json';
import Edit from './edit';

import './editor.scss';

const wpslIcon = createElement(
    'svg',
    { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: '24', height: '24' },
    createElement( 'circle', { cx: '10', cy: '10', r: '7', fill: 'none', stroke: 'currentColor', strokeWidth: '2' } ),
    createElement( 'path', { d: 'm15.5 15.5 6 6', fill: 'none', stroke: 'currentColor', strokeWidth: '2', strokeLinecap: 'round' } )
);

registerBlockType( metadata.name, {
    icon: wpslIcon,
    edit: Edit,
    save: () => null,
} );
