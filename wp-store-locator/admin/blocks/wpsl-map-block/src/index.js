import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import metadata from '../block.json';
import Edit from './edit';

import './editor.scss';

const wpslIcon = createElement(
    'svg',
    { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: '24', height: '24' },
    createElement( 'polygon', { points: '3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21', fill: 'none', stroke: 'currentColor', strokeWidth: '1.5', strokeLinejoin: 'round' } ),
    createElement( 'line', { x1: '9', x2: '9', y1: '3', y2: '18', stroke: 'currentColor', strokeWidth: '1.5' } ),
    createElement( 'line', { x1: '15', x2: '15', y1: '6', y2: '21', stroke: 'currentColor', strokeWidth: '1.5' } )
);

registerBlockType( metadata.name, {
    icon: wpslIcon,
    edit: Edit,
    save: () => null,
} );
