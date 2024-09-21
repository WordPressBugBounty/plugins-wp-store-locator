<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="modal micromodal-slide" id="wpsl-exit-survey" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
            <header class="modal__header">
                <h1 class="modal__title"><?php esc_html_e( 'WP Store Locator Deactivation', 'wpsl' ); ?></h1>
            </header>
            <form>
                <main class="modal__content">
                    <p><?php esc_html_e("If you have a moment, please let us know why you are deactivating WP Store Locator. This helps us improve the plugin.", 'wpsl' ); ?></p>
                    <ul>
                        <li>
                            <input type="radio" id="wpsl-not-working" name="survey-reason" value="not_working">
                            <label for="wpsl-not-working"><?php esc_html_e( 'I couldn\'t get the plugin to work' ,'wpsl' ); ?></label>
                            <p class="wpsl-survey-support-links">
                                <a class="button-primary" href="https://wpstorelocator.co/support" target="_blank"><?php esc_html_e( 'Open Support Ticket', 'wpsl' ); ?></a>
                                <a class="button-primary" href="https://wpstorelocator.co/documentation" target="_blank"><?php esc_html_e( 'Documentation', 'wpsl' ); ?></a>
                            </p>
                        </li>
                        <li>
                            <input type="radio" id="wpsl-better-plugin" name="survey-reason" value="better_plugin">
                            <label for="wpsl-better-plugin"><?php esc_html_e( 'I found a better plugin' ,'wpsl' ); ?></label>
                            <input type="text" class="wpsl-exit-survey-suggestions" placeholder="<?php esc_html_e( 'Please tell us which plugin?', 'wpsl' ) ?>">
                        </li>
                        <li>
                            <input type="radio" id="wpsl-missing-feature" name="survey-reason" value="missing_feature">
                            <label for="wpsl-missing-feature"><?php esc_html_e( 'Missing feature' ,'wpsl' ); ?></label>
                            <input type="text" class="wpsl-exit-survey-suggestions" placeholder="<?php esc_html_e( 'Please tell us which feature you\'re missing?', 'wpsl' ) ?>">
                        </li>
                        <li>
                            <input id="wpsl-temporary-deactivation" type="radio" name="survey-reason" value="temporary_deactivation">
                            <label for="wpsl-temporary-deactivation"><?php esc_html_e( 'It\'s a temporary deactivation' ,'wpsl' ); ?>
                        </li>
                        <li>
                            <input type="radio" id="wpsl-other" name="survey-reason" value="other">
                            <label for="wpsl-other"><?php esc_html_e( 'Other' ,'wpsl' ); ?></label>
                            <input type="text" class="wpsl-exit-survey-suggestions" placeholder="<?php esc_html_e( 'What can we improve?', 'wpsl' ) ?>">
                        </li>
                    </ul>
                </main>
                <footer class="modal__footer">
                    <button class="button-primary"><?php esc_html_e( 'Submit &amp; Deactivate' ,'wpsl' ); ?></button>
                    <a id="wpsl-skip-survey" href=""><?php esc_html_e( 'Skip &amp; Deactivate' ,'wpsl' ); ?></a>
                </footer>
                <input id="wpsl-survey-nonce" name="wpsl-survey-nonce" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpsl_survey_nonce' ) ); ?>" />
            </form>
        </div>
    </div>
</div>