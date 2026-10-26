<?php
/**
 * Result Card Tab Content
 *
 * The settings for each location in the search results ( and the info
 * window ): icons, categories and the More details link, taken out of
 * Search Results. Only shown when an add-on turns the item on, see
 * Appearance\Manager::has_result_card_item(); without it they stay under
 * Search Results, as in 3.1.0.
 *
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$ui = wpsl_get_service( 'admin_ui' );
$template_id = $wpsl_settings->get( 'appearance', 'template_id' );
?>

<?php require WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/partials/custom-section-notice.php'; ?>

<?php
/**
 * Fires at the top of the Result Card item, above the card settings.
 *
 * The item sits inside the Appearance form, so inputs printed here are
 * posted on save, see the 'wpsl_appearance_saved' action.
 *
 * @since 3.1.1
 */
do_action( 'wpsl_appearance_result_card' );
?>
<?php require WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/partials/result-card-fields.php'; ?>
