<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$api_settings = $this->settings->get_group( 'api' );
?>

<div id="wpsl-onboarding" class="wpsl-<?php echo esc_attr( $this->get_map_service() ); ?>">
    <form id="wpsl-onboarding-api-keys" method="post" autocomplete="off" action="<?php echo esc_url( add_query_arg( 'step', $this->nav_step() ) ); ?>">
        <?php $this->hidden_fields(); ?>
        <h3><?php esc_html_e( 'API Keys' , 'wp-store-locator' ); ?></h3>
        <?php $this->render_api_key_fields(); ?>
        <?php $this->action_buttons(); ?>
    </form>
</div>