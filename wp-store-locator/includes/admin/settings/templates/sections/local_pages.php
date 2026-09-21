<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-local_pages" class="postbox">
    <h3><span><?php esc_html_e( 'Local Pages', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p>
            <label for="wpsl-permalinks-active"><?php esc_html_e( 'Create landing pages?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: opening link tag to documentation, %3$s: closing link tag */ echo sprintf( esc_html__( 'Enabling this option will create single pages for all store locations. %1$s How to create a custom store page template is explained %2$shere%3$s.', 'wp-store-locator' ), '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/create-custom-store-page-template/">', '</a>' ); ?></span></span></label>
            <input type="checkbox" value="" <?php checked( $section_settings['permalinks'], true ); ?> name="wpsl_local_pages[active]" id="wpsl-permalinks-active" class="wpsl-has-conditional-option">
        </p>
        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['permalinks'] ) { echo 'style="display:none;"'; } ?>>
            <p>
                <label for="wpsl-permalink-remove-front"><?php esc_html_e( 'Remove the front base from the permalink structure?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: opening link tag to permalink settings, %2$s: closing link tag, %3$s: line breaks */ echo sprintf( esc_html__( 'The front base is set on the %1$spermalink settings%2$s page in the "Custom structure" field. %3$s If a front base is set (for example /blog/), enabling this option will remove it from the store locator permalinks.', 'wp-store-locator' ), '<a href="https://wordpress.org/support/article/settings-permalinks-screen/" target="_blank">', '</a>', '<br><br>' ); ?></span></span></label>
                <input type="checkbox" value="" <?php checked( $section_settings['permalink_remove_front'], true ); ?> name="wpsl_local_pages[remove_front]" id="wpsl-permalink-remove-front">
            </p>
            <p>
                <label for="wpsl-permalinks-slug"><?php esc_html_e( 'Store slug', 'wp-store-locator' ); ?></label>
                <input type="text" value="<?php echo esc_attr( $section_settings['permalink_slug'] ); ?>" name="wpsl_local_pages[slug]" id="wpsl-permalinks-slug">
            </p>
            <p>
                <label for="wpsl-category-slug"><?php esc_html_e( 'Category slug', 'wp-store-locator' ); ?></label>
                <input type="text" value="<?php echo esc_attr( $section_settings['category_slug'] ); ?>" name="wpsl_local_pages[category_slug]" id="wpsl-category-slug">
            </p>
            <?php /* translators: %1$s: opening strong tag, %2$s: closing strong tag */ ?>
            <em><?php echo sprintf( esc_html__( 'The permalink slugs %1$smust be unique%2$s on your site.', 'wp-store-locator' ), '<strong>', '</strong>' ); ?></em>
        </div>
        <?php do_action( 'wpsl_local_pages_settings_section' ); ?>
        <p class="submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
        </p>
    </div>
</section>