<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Third-party icon geometry redistributed here under its own licence, not ours.
 *
 *   Heroicons -- MIT Licence, Copyright (c) Tailwind Labs, Inc.
 *   The "cross" / "reset" glyph is 20/solid/x-mark, "search" is
 *   24/outline/magnifying-glass, both copied directly from heroicons v2.
 *
 *   Font Awesome Free -- Copyright (c) Fonticons, Inc. The "phone" glyph
 *   carries its notice inline, the way Font Awesome exports it.
 */

/**
 * Get an SVG icon.
 * 
 * @since  3.0.0
 * @param  string $type The type of icon to retrieve.
 * @return string $icon The SVG icon.
 */
function wpsl_get_svg_icon( $type ) {
    switch( $type ) {
        case 'dropdown':
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 10 10" stroke="currentColor">';
            $icon .= '<path d="M1,3 L5,7 L9,3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>';
            $icon .= '</svg>';
            break;
        case 'cross':
        case 'reset':
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">';
            $icon .= '<path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"></path>';
            $icon .= '</svg>';
            break;
        case 'search':
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">';
            $icon .= '<path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"></path>';
            $icon .= '</svg>';
            break;
        case 'phone':
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" height="10" width="10" viewBox="0 0 512 512"><!--!Font Awesome Free v7.1.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M160.2 25C152.3 6.1 131.7-3.9 112.1 1.4l-5.5 1.5c-64.6 17.6-119.8 80.2-103.7 156.4 37.1 175 174.8 312.7 349.8 349.8 76.3 16.2 138.8-39.1 156.4-103.7l1.5-5.5c5.4-19.7-4.7-40.3-23.5-48.1l-97.3-40.5c-16.5-6.9-35.6-2.1-47 11.8l-38.6 47.2C233.9 335.4 177.3 277 144.8 205.3L189 169.3c13.9-11.3 18.6-30.4 11.8-47L160.2 25z"/></svg>';
            break;
        case 'local_pages':
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">';
            $icon .= '<path fill-rule="evenodd" clip-rule="evenodd" fill="currentColor" d="M4,4.5 H20 A2,2 0 0 1 22,6.5 V17.5 A2,2 0 0 1 20,19.5 H4 A2,2 0 0 1 2,17.5 V6.5 A2,2 0 0 1 4,4.5 Z M4.3,9.5 H19.7 A1,1 0 0 1 20.7,10.5 V17.2 A1,1 0 0 1 19.7,18.2 H4.3 A1,1 0 0 1 3.3,17.2 V10.5 A1,1 0 0 1 4.3,9.5 Z M6.3,7 A0.8,0.8 0 1,1 4.7,7 A0.8,0.8 0 1,1 6.3,7 Z M8.5,7 A0.8,0.8 0 1,1 6.9,7 A0.8,0.8 0 1,1 8.5,7 Z M10.7,7 A0.8,0.8 0 1,1 9.1,7 A0.8,0.8 0 1,1 10.7,7 Z"></path>';
            $icon .= '</svg>';
            break;
        case 'draggable':
            $icon = '<svg width="8" height="12" xmlns="http://www.w3.org/2000/svg">';
            $icon .= '<circle cx="2" cy="2" r="1" fill="#999"></circle>';
            $icon .= '<circle cx="6" cy="2" r="1" fill="#999"></circle>';

            $icon .= '<circle cx="2" cy="6" r="1" fill="#999"></circle>';
            $icon .= '<circle cx="6" cy="6" r="1" fill="#999"></circle>';

            $icon .= '<circle cx="2" cy="10" r="1" fill="#999"></circle>';
            $icon .= '<circle cx="6" cy="10" r="1" fill="#999"></circle>';
            $icon .= '</svg>';
            break;
        default:
            $icon = '';
            break;
            
    }

    return $icon;
}

/**
 * The WPSL map pin as a URL-encoded SVG data URI.
 *
 * @since  3.0.0
 * @return string The data URI, ready to drop into a CSS url().
 */
function wpsl_get_pin_icon_url() {
    $path = 'M429 493q0 59-42 101t-101 42-101-42-42-101 42-101 101-42 101 42 42 101z m142 0q0-61-18-100l-203-432q-9-18-27-29t-37-11-38 11-26 29l-204 432q-18 39-18 100 0 118 84 202t202 84 202-84 83-202z';

    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='-214 0 1000 1000'><path transform='translate(0 850) scale(1 -1)' d='" . $path . "'/></svg>";

    // Only the characters that can't travel inside a CSS url() unescaped.
    return 'data:image/svg+xml,' . str_replace(
        [ '<', '>', '#', '"', ' ' ],
        [ '%3C', '%3E', '%23', '%22', '%20' ],
        $svg
    );
}