<?php
/**
 * The Home page.
 *
 * @since 3.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$home        = wpsl_get_service( 'home' );
$simple_mode = wpsl_get_service( 'simple_mode' );

$checklist = $home->get_checklist();
$counts    = $home->get_counts();
$alerts    = $home->get_alerts();
$latest    = $home->get_latest_posts( 2 );

$total = count( $checklist );
$done  = count( array_filter( array_column( $checklist, 'done' ) ) );

$percent = $total ? round( ( $done / $total ) * 100 ) : 0;

// Decided before anything prints: a complete checklist dismisses itself here.
$show_setup = $home->should_show_setup( $checklist );
?>
<div id="wpsl-content-wrap">
    <?php if ( $show_setup ) {
        $form = $home->get_map_service_form();
    ?>
    <?php // The count and bar stay out of sight until the first task is done, see style.css. ?>
    <div class="wpsl-home-progress-bar<?php echo $done ? '' : ' wpsl-home-no-progress'; ?>" id="wpsl-home-progress">
        <div class="wpsl-home-progress-head">
            <span class="wpsl-progress-label"><?php
                /* translators: 1: completed tasks, 2: total tasks */
                echo esc_html( sprintf( __( 'Store Locator setup: %1$d of %2$d tasks done', 'wp-store-locator' ), $done, $total ) ); ?></span>
            <span class="wpsl-home-progress-note"><?php esc_html_e( 'Finish these steps to get your store locator live on the site.', 'wp-store-locator' ); ?></span>
            <?php // Same cross the Tools dialogs close with, see Helpers\UI::icons.cross(). ?>
            <a class="wpsl-home-dismiss wpsl-close-cross" href="<?php echo esc_url( $home->get_dismiss_url() ); ?>" title="<?php esc_attr_e( 'Hide the setup checklist', 'wp-store-locator' ); ?>" aria-label="<?php esc_attr_e( 'Hide the setup checklist', 'wp-store-locator' ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></a>
        </div>
        <div class="wpsl-progress-bar">
            <div class="wpsl-progress-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
        </div>
    </div>

    <section class="postbox wpsl-home-glance wpsl-home-setup" id="wpsl-home-setup">
        <h3><span><?php esc_html_e( 'Finish setup', 'wp-store-locator' ); ?></span></h3>
        <div class="inside">
            <?php if ( $checklist ) { ?>
                <ol class="wpsl-home-check">
                    <?php foreach ( $checklist as $item ) { ?>
                        <li class="<?php echo $item['done'] ? 'wpsl-home-done' : 'wpsl-home-todo'; ?>" data-step="<?php echo esc_attr( $item['id'] ); ?>">
                            <?php
                            if ( 'placed_on_page' === $item['id'] ) { ?>
                                <details class="wpsl-home-make-page wpsl-home-step">
                                    <summary class="wpsl-home-check-label"><?php echo esc_html( $item['label'] ); ?></summary>
                                    <form id="wpsl-home-page-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="wpsl_create_locator_page">
                                        <?php wp_nonce_field( \WPSL\Admin\Home\Home::CREATE_PAGE_ACTION, 'wpsl_create_page_nonce' ); ?>
                                        
                                        <div class="wpsl-home-page-options">
                                            <div class="wpsl-home-page-option">
                                                <h4><?php esc_html_e( 'Use a page you already have', 'wp-store-locator' ); ?></h4>
                                                <p class="wpsl-home-simple-text"><?php esc_html_e( 'Paste this where the locator should appear.', 'wp-store-locator' ); ?></p>
                                                <p class="wpsl-home-shortcode">
                                                    <label class="screen-reader-text" for="wpsl-step-shortcode"><?php esc_html_e( 'Store locator shortcode', 'wp-store-locator' ); ?></label>
                                                    <input type="text" class="wpsl-shortcode-field" id="wpsl-step-shortcode" value="[wpsl]" readonly>
                                                    <button type="button" class="button wpsl-shortcode-copy" data-copied="<?php esc_attr_e( 'Copied', 'wp-store-locator' ); ?>"><?php esc_html_e( 'Copy', 'wp-store-locator' ); ?></button>
                                                </p>
                                                <p class="wpsl-home-page-action"><a href="<?php echo esc_url( $item['url'] ); ?>"><?php esc_html_e( 'Go to Pages', 'wp-store-locator' ); ?></a></p>
                                            </div>
                                            <div class="wpsl-home-page-option">
                                                <h4><?php esc_html_e( 'Or let us make one', 'wp-store-locator' ); ?></h4>
                                                <p class="wpsl-home-simple-text"><?php
                                                    if ( $item['done'] ) {
                                                        esc_html_e( 'Already on a page. A new one is saved as a draft.', 'wp-store-locator' );
                                                    } else {
                                                        esc_html_e( 'Saved as a draft until you publish it.', 'wp-store-locator' );
                                                    }
                                                ?></p>
                                                <p class="wpsl-home-page-title-field">
                                                    <label class="screen-reader-text" for="wpsl-new-page-title"><?php esc_html_e( 'Page title', 'wp-store-locator' ); ?></label>
                                                    <input type="text" id="wpsl-new-page-title" name="wpsl_page_title" value="<?php esc_attr_e( 'Store Locator', 'wp-store-locator' ); ?>">
                                                    <button type="submit" class="button"><?php esc_html_e( 'Create page', 'wp-store-locator' ); ?></button>
                                                    <img class="wpsl-preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" width="16" height="16" alt="" hidden>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="wpsl-home-page-result" aria-live="polite"></div>
                                    </form>
                                    <?php $wpsl_manual = $home->is_placed_manually(); ?>
                                    <div class="wpsl-home-page-manual" <?php if ( $item['done'] && ! $wpsl_manual ) { echo 'hidden'; } ?>>
                                        <input type="checkbox" id="wpsl-home-placed-manually" class="wpsl-toggle-pending" value="1" <?php checked( $wpsl_manual ); ?>>
                                        <label for="wpsl-home-placed-manually"><?php esc_html_e( 'I have added the store locator to a page', 'wp-store-locator' ); ?></label>
                                        <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Tick this when the locator is somewhere we cannot see it: a draft, a template, a widget or a page builder. It only ticks the step off this list.', 'wp-store-locator' ); ?></span></span>
                                    </div>
                                </details>
                            <?php } elseif ( 'map_service' === $item['id'] ) { ?>
                                <details class="wpsl-home-step wpsl-home-map-service" id="wpsl-home-map-service">
                                    <summary class="wpsl-home-check-label"><?php echo esc_html( $item['label'] ); ?></summary>
                                    <form id="wpsl-home-service-form" autocomplete="off" data-current="<?php echo esc_attr( $form['current'] ); ?>">
                                        <?php // Verdicts print above the fields they judge, where the settings page puts its own. ?>
                                        <div class="wpsl-home-service-result" aria-live="polite"></div>
                                        <p class="wpsl-home-service-row">
                                            <label for="wpsl-home-service-select"><?php esc_html_e( 'Map service', 'wp-store-locator' ); ?></label>
                                            <select id="wpsl-home-service-select" name="service">
                                                <?php foreach ( $form['services'] as $service_id => $service_name ) { ?>
                                                    <option value="<?php echo esc_attr( $service_id ); ?>" <?php selected( $form['current'], $service_id ); ?>><?php echo esc_html( $service_name ); ?></option>
                                                <?php } ?>
                                            </select>
                                        </p>
                                        <?php foreach ( $form['keys'] as $service_id => $fields ) { ?>
                                            <?php // A service needing no key prints an empty panel: nothing to say beats saying nothing is needed. ?>
                                            <div class="wpsl-home-service-keys" data-service="<?php echo esc_attr( $service_id ); ?>" <?php if ( $service_id !== $form['current'] ) { echo 'hidden'; } ?>>
                                                <?php if ( $fields ) { ?>
                                                    <?php foreach ( $fields as $setting => $field ) {
                                                        $input_id = 'wpsl-home-' . str_replace( '_', '-', $setting );
                                                    ?>
                                                        <p class="wpsl-home-service-row">
                                                            <label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                                                            <span class="wpsl-key-field"><input type="password" autocomplete="new-password" spellcheck="false" class="wpsl-key-input <?php if ( $field['value'] && ! $field['valid'] ) { echo 'wpsl-error'; } ?>" id="<?php echo esc_attr( $input_id ); ?>" name="keys[<?php echo esc_attr( $setting ); ?>]" value="<?php echo esc_attr( $field['value'] ); ?>" placeholder="<?php esc_attr_e( 'Required', 'wp-store-locator' ); ?>"><?php echo wpsl_key_visibility_toggle( $input_id, $field['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper already escapes output ?></span>
                                                        </p>
                                                    <?php } ?>
                                                    <?php
                                                    if ( ! empty( $form['docs'][ $service_id ]['url'] ) ) { ?>
                                                        <p class="wpsl-home-service-guide"><a href="<?php echo esc_url( $form['docs'][ $service_id ]['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $form['docs'][ $service_id ]['label'] ); ?></a></p>
                                                    <?php } ?>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                        <p class="wpsl-home-service-actions">
                                            <button type="submit" class="button button-primary"><?php
                                                if ( empty( $form['keys'][ $form['current'] ] ) ) {
                                                    esc_html_e( 'Save', 'wp-store-locator' );
                                                } else {
                                                    esc_html_e( 'Save and verify', 'wp-store-locator' );
                                                }
                                            ?></button>
                                            <img class="wpsl-preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" width="16" height="16" alt="" hidden>
                                        </p>
                                    </form>
                                </details>
                            <?php } elseif ( 'first_location' === $item['id'] ) { ?>
                                <?php
                                $blocked = ! empty( $item['blocked'] ) ? $item['blocked'] : '';
                                ?>
                                <a class="wpsl-home-check-label wpsl-home-step-link" id="wpsl-home-first-location" href="<?php echo esc_url( $blocked ? '#wpsl-home-map-service' : $item['url'] ); ?>" data-url="<?php echo esc_url( $item['url'] ); ?>" data-blocked="<?php echo esc_attr( $blocked ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
                                <span class="wpsl-info wpsl-warning wpsl-home-step-warning" id="wpsl-home-first-location-warning" <?php if ( ! $blocked ) { echo 'hidden'; } ?>><span class="wpsl-info-text wpsl-hide"><?php echo esc_html( $blocked ); ?></span></span>
                            <?php } elseif ( ! empty( $item['url'] ) ) { ?>
                                <a class="wpsl-home-check-label wpsl-home-step-link" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
                            <?php } else { ?>
                                <span class="wpsl-home-check-label"><?php echo esc_html( $item['label'] ); ?></span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            <?php } else { ?>
                <p class="wpsl-home-check-label"><?php esc_html_e( 'Nothing to set up yet.', 'wp-store-locator' ); ?></p>
            <?php } ?>
        </div>
    </section>
    <?php } ?>

    <div class="wpsl-home-tiles">
        <?php
        /*
         * One icon per tile, keyed on the tile id so an add-on's tile
         * ( which has none ) still prints. Outline icons on a 24-unit
         * grid, coloured by the tile's CSS custom properties.
         */
        $tile_icons = [
            'locations'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>',
            'categories' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.6 2.6A2 2 0 0 0 11.2 2H4a2 2 0 0 0-2 2v7.2a2 2 0 0 0 .6 1.4l8.7 8.7a2.4 2.4 0 0 0 3.4 0l6.6-6.6a2.4 2.4 0 0 0 0-3.4Z"/><circle cx="7.5" cy="7.5" r="1" fill="currentColor" stroke="none"/></svg>',
            'markers'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.6-.7 1.6-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1a1.6 1.6 0 0 1 1.7-1.7h2c3 0 5.5-2.5 5.5-5.6C22 6 17.5 2 12 2Z"/><circle cx="13.5" cy="6.5" r="1" fill="currentColor" stroke="none"/><circle cx="17.5" cy="10.5" r="1" fill="currentColor" stroke="none"/><circle cx="8.5" cy="7.5" r="1" fill="currentColor" stroke="none"/><circle cx="6.5" cy="12.5" r="1" fill="currentColor" stroke="none"/></svg>',
        ];

        foreach ( $counts as $tile_id => $tile ) { ?>
            <div class="wpsl-home-tile wpsl-home-tile-<?php echo esc_attr( $tile_id ); ?>">
                <a class="wpsl-home-tile-main" href="<?php echo esc_url( $tile['url'] ); ?>">
                    <?php if ( isset( $tile_icons[ $tile_id ] ) ) { ?>
                        <span class="wpsl-home-tile-icon" aria-hidden="true"><?php echo $tile_icons[ $tile_id ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup defined above, no user input. ?></span>
                    <?php } ?>
                    <span class="wpsl-home-tile-text">
                        <strong><?php echo esc_html( $tile['total'] ); ?></strong>
                        <span><?php echo esc_html( $tile['label'] ); ?></span>
                    </span>
                </a>
                <?php
                // Each status links to its own list, so the tile is not
                // one big anchor: a link inside a link is not honoured.
                $meta = array_values( array_filter( $tile['meta'], function( $entry ) {
                    return ! empty( $entry['count'] ) || ! empty( $entry['text'] );
                } ) );

                if ( $meta ) { ?>
                    <em class="wpsl-home-tile-meta">
                        <?php foreach ( $meta as $entry ) { ?>
                            <a class="<?php echo empty( $entry['cta'] ) ? 'wpsl-home-tile-status' : 'wpsl-home-tile-cta'; ?>" href="<?php echo esc_url( $entry['url'] ); ?>"><?php
                                echo esc_html( isset( $entry['text'] ) ? $entry['text'] : $entry['count'] . ' ' . $entry['label'] ); ?></a>
                        <?php } ?>
                    </em>
                <?php } ?>
            </div>
        <?php } ?>
        <?php
        /**
         * Room for an add-on's own tile ( searches, leads ).
         *
         * @since 3.0.0
         */
        do_action( 'wpsl_home_tiles' );
        ?>
    </div>

    <?php
    /**
     * Room for an add-on's own full width block, below the row of counts.
     *
     * @since 3.0.0
     */
    do_action( 'wpsl_home_after_tiles' );
    ?>

        <?php /* The id scopes the dismiss handler: three boxes on this page carry wpsl-home-glance. */ ?>
        <section class="postbox wpsl-home-glance" id="wpsl-home-alerts">
            <h3><span><?php esc_html_e( 'Alerts', 'wp-store-locator' ); ?></span></h3>
            <div class="inside">
                <?php
                $wpsl_alert_items = $alerts['items'];

                require WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/partials/alerts-list.php';
                ?>
            </div>
        </section>

    <div class="wpsl-home-row">
        <section class="postbox">
            <h3><span><?php esc_html_e( 'Shortcode', 'wp-store-locator' ); ?></span></h3>
            <div class="inside">
                <p class="wpsl-home-simple-text"><?php esc_html_e( 'Paste this on the page where the store locator should appear.', 'wp-store-locator' ); ?></p>
                <p class="wpsl-home-shortcode">
                    <label class="screen-reader-text" for="wpsl-home-shortcode"><?php esc_html_e( 'Store locator shortcode', 'wp-store-locator' ); ?></label>
                    <input type="text" class="wpsl-shortcode-field" id="wpsl-home-shortcode" value="[wpsl]" readonly>
                    <button type="button" class="button wpsl-shortcode-copy" data-copied="<?php esc_attr_e( 'Copied', 'wp-store-locator' ); ?>"><?php esc_html_e( 'Copy', 'wp-store-locator' ); ?></button>
                </p>
                <p class="wpsl-home-simple-text"><?php esc_html_e( 'Only the map, without the search form and the results list. Add id="123" to show a single store.', 'wp-store-locator' ); ?></p>
                <p class="wpsl-home-shortcode">
                    <label class="screen-reader-text" for="wpsl-home-shortcode-map"><?php esc_html_e( 'Store map shortcode', 'wp-store-locator' ); ?></label>
                    <input type="text" class="wpsl-shortcode-field" id="wpsl-home-shortcode-map" value="[wpsl_map]" readonly>
                    <button type="button" class="button wpsl-shortcode-copy" data-copied="<?php esc_attr_e( 'Copied', 'wp-store-locator' ); ?>"><?php esc_html_e( 'Copy', 'wp-store-locator' ); ?></button>
                </p>
                <p class="wpsl-home-simple-text"><?php esc_html_e( 'Need category filters or a different map size? The shortcode generator in the post editor builds one with those options.', 'wp-store-locator' ); ?></p>
                <p class="wpsl-home-box-action"><a class="button" href="https://wpstorelocator.co/document/shortcodes/" target="_blank" rel="noopener"><?php esc_html_e( 'All shortcode options', 'wp-store-locator' ); ?></a></p>
            </div>
        </section>
        <section class="postbox wpsl-home-help">
            <h3><span><?php esc_html_e( 'Help & Resources', 'wp-store-locator' ); ?></span></h3>
            <div class="inside">
                <ul class="wpsl-home-resources">
                    <li>
                        <a href="https://wpstorelocator.co/documentation/" target="_blank" rel="noopener">
                            <span class="wpsl-home-resource-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg></span>
                            <span class="wpsl-home-resource-text">
                                <strong><?php esc_html_e( 'Documentation', 'wp-store-locator' ); ?></strong>
                                <span><?php esc_html_e( 'Guides for every screen, setting and shortcode.', 'wp-store-locator' ); ?></span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url( apply_filters( 'wpsl_home_support_url', 'https://wpstorelocator.co/support/' ) ); ?>" target="_blank" rel="noopener">
                            <span class="wpsl-home-resource-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/><circle cx="12" cy="12" r="4"/></svg></span>
                            <span class="wpsl-home-resource-text">
                                <strong><?php esc_html_e( 'Support', 'wp-store-locator' ); ?></strong>
                                <span><?php esc_html_e( 'Stuck on something? Open a support ticket.', 'wp-store-locator' ); ?></span>
                            </span>
                        </a>
                    </li>
                </ul>

                <?php
                if ( $latest ) { ?>
                    <ul class="wpsl-home-resources wpsl-home-resource-posts">
                        <?php foreach ( $latest as $post ) { ?>
                            <li>
                                <a href="<?php echo esc_url( $post['url'] ); ?>" target="_blank" rel="noopener">
                                    <span class="wpsl-home-resource-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1" fill="currentColor"/></svg></span>
                                    <span class="wpsl-home-resource-text">
                                        <strong><?php echo esc_html( $post['title'] ); ?></strong>
                                        <?php if ( $post['date'] ) { ?>
                                            <span><?php echo esc_html( $post['date'] ); ?></span>
                                        <?php } ?>
                                    </span>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
                <p class="wpsl-home-box-action">
                    <button type="button" class="button" id="wpsl-home-feedback-open"><svg class="wpsl-home-button-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><?php esc_html_e( 'Send feedback', 'wp-store-locator' ); ?></button>
                </p>
            </div>
        </section>
    </div>

    <div class="modal micromodal-slide" id="wpsl-home-feedback" aria-hidden="true">
        <div class="modal__overlay" tabindex="-1" data-micromodal-close>
            <div class="modal__container" role="dialog" aria-modal="true" aria-labelledby="wpsl-home-feedback-title">
                <header class="modal__header">
                    <h2 class="modal__title" id="wpsl-home-feedback-title"><?php esc_html_e( 'Send feedback', 'wp-store-locator' ); ?></h2>
                    <button type="button" class="wpsl-close-cross wpsl-dialog-close" aria-label="<?php esc_attr_e( 'Close', 'wp-store-locator' ); ?>" data-micromodal-close><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></button>
                </header>
                <form id="wpsl-home-feedback-form" autocomplete="off">
                    <main class="modal__content">
                        <div class="wpsl-home-feedback-result" aria-live="polite"></div>
                        <div class="wpsl-home-feedback-fields">
                        <p class="wpsl-home-feedback-intro"><?php esc_html_e( 'Tell us what\'s missing, broken, or could be better.', 'wp-store-locator' ); ?></p>
                        <p>
                            <label class="screen-reader-text" for="wpsl-home-feedback-type"><?php esc_html_e( 'What kind of feedback do you have?', 'wp-store-locator' ); ?></label>
                            <select id="wpsl-home-feedback-type" name="wpsl_feedback_type">
                                <option value="feature"><?php esc_html_e( 'Missing feature', 'wp-store-locator' ); ?></option>
                                <option value="general" selected="selected"><?php esc_html_e( 'General feedback', 'wp-store-locator' ); ?></option>
                                <option value="bug"><?php esc_html_e( 'Report an issue', 'wp-store-locator' ); ?></option>
                            </select>
                        </p>
                        <p>
                            <label class="screen-reader-text" for="wpsl-home-feedback-message"><?php esc_html_e( 'Your feedback', 'wp-store-locator' ); ?></label>
                            <textarea id="wpsl-home-feedback-message" rows="6" placeholder="<?php esc_attr_e( 'What would you like to tell us?', 'wp-store-locator' ); ?>"></textarea>
                        </p>
                        <div class="wpsl-home-feedback-option" id="wpsl-home-feedback-report-row" hidden>
                            <input type="checkbox" id="wpsl-home-feedback-report" class="wpsl-toggle-pending" value="1">
                            <label for="wpsl-home-feedback-report"><?php esc_html_e( 'Include site info so we can reproduce it', 'wp-store-locator' ); ?></label>
                            <span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php
                                /* translators: %s: line breaks */
                                echo wp_kses_post( sprintf( __( 'Sent with your message: %1$s WordPress, PHP and server versions %1$s Your store and category counts %1$s This plugin\'s settings %1$s The active theme and the active plugins %1$s No store data, and no personal details beyond the email address you choose to add.', 'wp-store-locator' ), '<br>&bull; ' ) ); ?></span></span>
                        </div>
                        <div class="wpsl-home-feedback-option">
                            <input type="checkbox" id="wpsl-home-feedback-email-toggle" class="wpsl-toggle-pending" value="1">
                            <label for="wpsl-home-feedback-email-toggle"><?php esc_html_e( 'Include my email address so we can respond', 'wp-store-locator' ); ?></label>
                        </div>
                        <p class="wpsl-home-feedback-email" id="wpsl-home-feedback-email-row" hidden>
                            <label class="screen-reader-text" for="wpsl-home-feedback-email"><?php esc_html_e( 'Email address', 'wp-store-locator' ); ?></label>
                            <input type="email" id="wpsl-home-feedback-email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
                        </p>
                        </div>
                    </main>
                    <footer class="modal__footer">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Send', 'wp-store-locator' ); ?></button>
                        <img class="wpsl-preloader" src="<?php echo esc_url( WPSL_URL . 'assets/img/ajax-loader.svg' ); ?>" width="16" height="16" alt="" hidden>
                        <button type="button" class="button" data-micromodal-close><?php esc_html_e( 'Cancel', 'wp-store-locator' ); ?></button>
                    </footer>
                </form>
            </div>
        </div>
    </div>
</div>