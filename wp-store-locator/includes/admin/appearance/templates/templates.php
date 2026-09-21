<?php
/**
 * Templates Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$appearance = wpsl_get_service( 'appearance' );
?>

<div class="wpsl-template-selection">
    <?php $appearance->template_styles_list(); ?>
</div>