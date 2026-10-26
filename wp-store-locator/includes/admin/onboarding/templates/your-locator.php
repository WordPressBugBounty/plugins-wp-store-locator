<?php
/**
 * The last step of the setup wizard: the six Quick setup choices.
 *
 * Every choice is saved the moment it is picked ( see wpsl-quick-setup.js ),
 * the form only moves on to the summary page and folds the Home card up.
 *
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$quick_setup = wpsl_get_service( 'quick_setup' );
$context     = 'wizard';
?>

<div id="wpsl-onboarding">
    <form id="wpsl-onboarding-your-locator" method="post" action="<?php echo esc_url( add_query_arg( 'step', $this->nav_step() ) ); ?>">
        <?php $this->hidden_fields(); ?>

        <h1><?php esc_html_e( 'How should your store locator work?', 'wp-store-locator' ); ?></h1>
        <p class="wpsl-onboarding-intro"><?php esc_html_e( 'Each choice is saved as soon as you pick it. You can change them later on the Home page, or under Settings and Appearance.', 'wp-store-locator' ); ?></p>

        <?php require WPSL_PLUGIN_DIR . 'includes/admin/home/templates/quick-setup-tiles.php'; ?>

        <?php $this->action_buttons(); ?>
    </form>
</div>
