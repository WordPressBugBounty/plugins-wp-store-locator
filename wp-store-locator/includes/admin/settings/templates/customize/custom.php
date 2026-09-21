<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$output = '<div class="wpsl-no-preview-available">';
$output .= '<p>' . esc_html__( 'No preview available', 'wp-store-locator' ) . '</p>';
$output .= '</div>';

return $output;