<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wpsl-onboarding" class="wpsl-onboarding-welcome">
    <h1><?php esc_html_e( 'Welcome to WP Store Locator' , 'wp-store-locator' ); ?></h1>
    <?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ ?>
    <p><?php echo sprintf( esc_html__( 'The following steps will help you configure WP Store Locator 3. This is completely optional and will only take a few minutes. %1$sNot now%2$s.', 'wp-store-locator' ), '<a href="'. esc_url( admin_url() ) .'">', '</a>' ); ?></p>

    <form id="wpsl-onboarding-map-service" class="wpsl-styled-checkboxes" method="post" action="<?php echo esc_url( add_query_arg( 'step', $this->nav_step() ) ); ?>">
        <?php $this->hidden_fields(); ?>

        <div class="wpsl-flex-container">
            <?php $this->render_map_service_boxes(); ?>
        </div>

        <?php $this->action_buttons(); ?>
    </form>
</div>