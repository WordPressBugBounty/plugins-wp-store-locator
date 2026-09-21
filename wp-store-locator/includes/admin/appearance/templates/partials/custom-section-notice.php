<?php
/**
 * Warning shown on appearance tabs that hold template-structure options.
 *
 * When the active theme has a customized template section that is out
 * of sync with the current settings, changes to those options don't
 * reach the search results until the custom section is updated; this
 * callout links straight to the section editor's sync flow. Once the
 * section is synced (or fixed manually) the callout disappears.
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_notice_settings = wpsl_get_service( 'wpsl_settings' );
$wpsl_notice_template = $wpsl_notice_settings->get( 'appearance', 'template_id' );
$wpsl_notice_analyzer = wpsl_get_service( 'section_analyzer' );

if ( ! $wpsl_notice_analyzer->has_out_of_sync_custom_section( $wpsl_notice_template ) ) {
    return;
}

/**
 * On a multilingual site each language has its own stored section and the
 * editor only fixes the loaded one, so naming the stale languages is the
 * difference between a fixable warning and a confusing one: the language
 * the user is looking at may already be fine.
 */
$wpsl_notice_languages = $wpsl_notice_analyzer->get_out_of_sync_language_names( $wpsl_notice_template );
?>
<div class="wpsl-warning-callout">
    <p>
        <?php esc_html_e( 'A customized template section is active for this theme. Changes to the options below won\'t appear in the search results until you update the custom section.', 'wp-store-locator' ); ?>
        <?php
        if ( ! empty( $wpsl_notice_languages ) ) {
            printf(
                /* translators: %s: comma separated list of language names */
                esc_html( _n(
                    'Out of sync in %s.',
                    'Out of sync in these languages: %s.',
                    count( $wpsl_notice_languages ),
                    'wp-store-locator'
                ) ),
                esc_html( implode( ', ', $wpsl_notice_languages ) )
            );
        }

        // The link only for someone the section editor is open to.
        if ( \WPSL\Admin\Settings\Section_Editor::current_user_can_edit() ) {
        ?>
        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings&wpsl-compare=1#wpsl-section-editor' ) ); ?>"><?php esc_html_e( 'Review differences', 'wp-store-locator' ); ?></a>
        <?php } ?>
    </p>
</div>