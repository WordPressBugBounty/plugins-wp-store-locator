<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="modal micromodal-slide" id="wpsl-exit-survey" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" data-micromodal-close>
        <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="modal-1-title">
            <header class="modal__header">
                <h1 class="modal__title"><?php esc_html_e( 'WP Store Locator Deactivation', 'wp-store-locator' ); ?></h1>
            </header>
            <form>
                <main class="modal__content">
                    <p><?php esc_html_e("If you have a moment, please let us know why you are deactivating WP Store Locator. This helps us improve the plugin.", 'wp-store-locator' ); ?></p>
                    <ul>
                        <li>
                            <input type="radio" id="wpsl-not-working" name="survey-reason" value="not_working">
                            <label for="wpsl-not-working"><?php esc_html_e( 'I couldn\'t get the plugin to work' , 'wp-store-locator' ); ?></label>
                            <div class="wpsl-survey-not-working">
                                <select id="wpsl-survey-not-working-reason" aria-label="<?php esc_attr_e( 'What went wrong?', 'wp-store-locator' ); ?>">
                                    <option value=""><?php esc_html_e( 'What went wrong?', 'wp-store-locator' ); ?></option>
                                    <?php foreach ( \WPSL\Admin\Utils\Exit_Survey::get_not_working_reasons() as $wpsl_reason_slug => $wpsl_reason_label ) : ?>
                                        <option value="<?php echo esc_attr( $wpsl_reason_slug ); ?>"><?php echo esc_html( $wpsl_reason_label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="wpsl-survey-not-working-feedback"><?php esc_html_e( 'What happened?', 'wp-store-locator' ); ?></label>
                                <textarea id="wpsl-survey-not-working-feedback" rows="3" maxlength="1000"></textarea>
                                <label class="wpsl-survey-debug" for="wpsl-survey-debug">
                                    <input type="checkbox" id="wpsl-survey-debug" value="1">
                                    <?php esc_html_e( 'Include technical details to help us debug', 'wp-store-locator' ); ?>
                                </label>
                                <details class="wpsl-survey-debug-details">
                                    <summary><?php esc_html_e( 'What is sent?', 'wp-store-locator' ); ?></summary>
                                    <ul>
                                        <li><?php esc_html_e( 'The WordPress, PHP, MySQL and WP Store Locator versions, and server settings such as the memory limit', 'wp-store-locator' ); ?></li>
                                        <li><?php esc_html_e( 'Your WP Store Locator settings, such as the map provider, search radius and template', 'wp-store-locator' ); ?></li>
                                        <li><?php esc_html_e( 'Whether your API keys are saved and valid. The keys themselves are never sent', 'wp-store-locator' ); ?></li>
                                        <li><?php esc_html_e( 'The active theme and plugins, with versions', 'wp-store-locator' ); ?></li>
                                        <li><?php esc_html_e( 'The WP Store Locator alerts that are showing, such as a conflicting plugin', 'wp-store-locator' ); ?></li>
                                        <li><?php esc_html_e( 'The number of locations and categories', 'wp-store-locator' ); ?></li>
                                    </ul>
                                    <p><strong><?php esc_html_e( 'Your site address, your stores and any personal data are not included.', 'wp-store-locator' ); ?></strong></p>
                                </details>
                            </div>
                            <p class="wpsl-survey-support-links">
                                <a class="button-primary" href="https://wpstorelocator.co/support" target="_blank"><?php esc_html_e( 'Open Support Ticket', 'wp-store-locator' ); ?></a>
                                <a class="button-primary" href="https://wpstorelocator.co/documentation" target="_blank"><?php esc_html_e( 'Documentation', 'wp-store-locator' ); ?></a>
                            </p>
                        </li>
                        <li>
                            <input type="radio" id="wpsl-better-plugin" name="survey-reason" value="better_plugin">
                            <label for="wpsl-better-plugin"><?php esc_html_e( 'I found a better plugin' , 'wp-store-locator' ); ?></label>
                            <div class="wpsl-survey-competitor">
                                <select id="wpsl-survey-competitor" aria-label="<?php esc_attr_e( 'Which plugin are you switching to?', 'wp-store-locator' ); ?>">
                                    <option value=""><?php esc_html_e( 'Which plugin are you switching to?', 'wp-store-locator' ); ?></option>
                                    <?php foreach ( \WPSL\Admin\Utils\Exit_Survey::get_competitors() as $wpsl_competitor_slug => $wpsl_competitor_name ) : ?>
                                        <option value="<?php echo esc_attr( $wpsl_competitor_slug ); ?>"><?php echo esc_html( $wpsl_competitor_name ); ?></option>
                                    <?php endforeach; ?>
                                    <option value="other"><?php esc_html_e( 'Other' , 'wp-store-locator' ); ?></option>
                                </select>
                                <input type="text" id="wpsl-survey-competitor-other" maxlength="100" placeholder="<?php esc_attr_e( 'Please tell us which plugin?', 'wp-store-locator' ); ?>">
                                <label for="wpsl-survey-competitor-feedback"><?php esc_html_e( 'What does it offer that WP Store Locator doesn\'t?', 'wp-store-locator' ); ?></label>
                                <textarea id="wpsl-survey-competitor-feedback" rows="3" maxlength="1000"></textarea>
                            </div>
                        </li>
                        <li>
                            <input type="radio" id="wpsl-missing-feature" name="survey-reason" value="missing_feature">
                            <label for="wpsl-missing-feature"><?php esc_html_e( 'Missing feature' , 'wp-store-locator' ); ?></label>
                            <input type="text" class="wpsl-exit-survey-suggestions" placeholder="<?php esc_attr_e( 'Please tell us which feature you\'re missing?', 'wp-store-locator' ); ?>">
                        </li>
                        <li>
                            <input id="wpsl-temporary-deactivation" type="radio" name="survey-reason" value="temporary_deactivation">
                            <label for="wpsl-temporary-deactivation"><?php esc_html_e( 'It\'s a temporary deactivation' , 'wp-store-locator' ); ?></label>
                        </li>
                        <li>
                            <input type="radio" id="wpsl-other" name="survey-reason" value="other">
                            <label for="wpsl-other"><?php esc_html_e( 'Other' , 'wp-store-locator' ); ?></label>
                            <input type="text" class="wpsl-exit-survey-suggestions" placeholder="<?php esc_attr_e( 'What can we improve?', 'wp-store-locator' ); ?>">
                        </li>
                    </ul>
                </main>
                <footer class="modal__footer">
                    <button class="button-primary"><?php esc_html_e( 'Submit &amp; Deactivate' , 'wp-store-locator' ); ?></button>
                    <a id="wpsl-skip-survey" href=""><?php esc_html_e( 'Skip &amp; Deactivate' , 'wp-store-locator' ); ?></a>
                </footer>
                <input id="wpsl-survey-nonce" name="wpsl-survey-nonce" type="hidden" value="<?php echo esc_attr( wp_create_nonce( 'wpsl_survey_nonce' ) ); ?>" />
            </form>
        </div>
    </div>
</div>