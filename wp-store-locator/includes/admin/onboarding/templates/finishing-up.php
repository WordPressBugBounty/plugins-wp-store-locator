<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$service_names = $this->map_service_names();
$map_settings  = $this->settings->get_group( 'map' );
$start_name    = isset( $map_settings['start_name'] ) ? trim( stripslashes( $map_settings['start_name'] ) ) : '';
$step_url      = admin_url( 'index.php?page=wpsl-onboarding' );
$check_icon    = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M4.8 8.3l2.1 2.1 4.3-4.4" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$warning_icon  = '<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="7" fill="currentColor"/><path d="M8 4.5v4.2" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/><circle cx="8" cy="11.2" r="1" fill="#fff"/></svg>';
?>

<div id="wpsl-onboarding" class="wpsl-onboarding-complete">
    <input type="hidden" name="map-service" value="<?php echo esc_attr( $this->map_service ); ?>">

    <div class="wpsl-onboarding-hero">
        <svg width="56" height="56" viewBox="0 0 56 56" aria-hidden="true" focusable="false"><circle cx="28" cy="28" r="28" fill="#d7f2e3"/><path d="M17 29l7.5 7.5L39.5 21" fill="none" stroke="#1a7f4b" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h1><?php esc_html_e( 'Your store locator is set up' , 'wp-store-locator' ); ?></h1>
        <p>
            <?php esc_html_e( 'Add your first store, then put the locator on a page.', 'wp-store-locator' ); ?><br>
            <?php
            /* translators: 1: opening link to the Settings page, 2: closing link */
            echo sprintf( esc_html__( 'Everything here can be changed later in %1$sSettings%2$s.', 'wp-store-locator' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) ) . '">', '</a>' );
            ?>
        </p>
    </div>

    <?php
    /**
     * Inform the user when we switched them to OpenStreetMap because the
     * Google Maps / Mapbox / Stadia Maps service they picked had no valid API
     * key. Without a valid key that service won't load and can't geocode, so
     * the store locator falls back to OpenStreetMap to keep the map working.
     */
    $fallback_service = $this->get_key_fallback_service();

    if ( $fallback_service ) {
        $map_service_label = isset( $service_names[ $fallback_service ] ) ? $service_names[ $fallback_service ] : $fallback_service;
        ?>
        <div class="wpsl-warning-callout">
            <h2><?php esc_html_e( 'Switched to OpenStreetMap', 'wp-store-locator' ); ?></h2>
            <p><?php
                /* translators: %s: the selected map service name (e.g. Google Maps or Mapbox) */
                echo esc_html( sprintf( __( 'You selected %s, but no valid API key was provided. Without one the map won\'t load and locations that require geocoding can\'t be created, so we\'ve switched the store locator to OpenStreetMap for now.', 'wp-store-locator' ), $map_service_label ) ); ?></p>
            <p><?php
                /* translators: 1: map service name, 2: opening API key doc link, 3: closing link, 4: opening Settings page link, 5: closing link */
                echo sprintf( esc_html__( 'To use %1$s instead, add a %2$svalid API key%3$s on the %4$sSettings page%5$s and set the map service to %1$s.', 'wp-store-locator' ), esc_html( $map_service_label ), '<a target="_blank" href="' . esc_url( $this->api_key_doc_url( $fallback_service ) ) . '">', '</a>', '<a href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) ) . '">', '</a>' ); ?></p>
        </div>
        <?php
    }
    ?>

    <h2 class="wpsl-onboarding-section-title"><?php esc_html_e( 'Your setup', 'wp-store-locator' ); ?></h2>
    <table class="wpsl-onboarding-summary">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Setting', 'wp-store-locator' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Your choice', 'wp-store-locator' ); ?></th>
                <th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?></span></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th scope="row"><?php esc_html_e( 'Map Service', 'wp-store-locator' ); ?></th>
                <td><?php echo esc_html( isset( $service_names[ $this->map_service ] ) ? $service_names[ $this->map_service ] : $this->map_service ); ?></td>
                <td class="wpsl-summary-edit"><a href="<?php echo esc_url( add_query_arg( 'step', 'map-service', $step_url ) ); ?>"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'Map Service', 'wp-store-locator' ); ?></span></a></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'API Keys', 'wp-store-locator' ); ?></th>
                <?php if ( $this->map_service === 'osm' ) { ?>
                    <td><?php esc_html_e( 'Not needed', 'wp-store-locator' ); ?></td>
                <?php } elseif ( $this->map_service_missing_api_key() ) { ?>
                    <td class="wpsl-summary-warning"><?php echo $warning_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup ?><?php esc_html_e( 'Missing or not working', 'wp-store-locator' ); ?></td>
                <?php } else { ?>
                    <td class="wpsl-summary-ok"><?php echo $check_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup ?><?php esc_html_e( 'Working', 'wp-store-locator' ); ?></td>
                <?php } ?>
                <td class="wpsl-summary-edit"><a href="<?php echo esc_url( add_query_arg( 'step', 'api-keys', $step_url ) ); ?>"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'API Keys', 'wp-store-locator' ); ?></span></a></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Start Location', 'wp-store-locator' ); ?></th>
                <td><?php echo $start_name ? esc_html( $start_name ) : esc_html__( 'Not set', 'wp-store-locator' ); ?></td>
                <td class="wpsl-summary-edit"><a href="<?php echo esc_url( add_query_arg( 'step', 'start-location', $step_url ) ); ?>"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'Start Location', 'wp-store-locator' ); ?></span></a></td>
            </tr>
            <?php
            // The six Quick setup choices, as the collapsed card on the Home page words them.
            foreach ( wpsl_get_service( 'quick_setup' )->get_summary() as $summary_row ) { ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $summary_row['label'] ); ?></th>
                    <td><?php echo esc_html( $summary_row['value'] ); ?></td>
                    <td class="wpsl-summary-edit"><a href="<?php echo esc_url( add_query_arg( 'step', 'your-locator', $step_url ) ); ?>"><?php esc_html_e( 'Edit', 'wp-store-locator' ); ?><span class="screen-reader-text"> <?php echo esc_html( $summary_row['label'] ); ?></span></a></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <?php
    /*
     * The add-on warning, printed inside the panel and above the template
     * callout below: both are things to deal with after setup, and the add-on
     * one is the more urgent of the two, since those add-ons are inactive
     * until they are updated.
     *
     * @see \WPSL\Core\Legacy_Addons
     */
    do_action( 'wpsl_admin_notices' );

    /**
     * Check for custom templates created for WP Store Locator 2.
     *
     * The check runs here instead of only at activation time because templates
     * registered through the 'wpsl_templates' filter by themes or companion
     * plugins may not be hooked yet when the activation hook runs.
     */
    $custom_templates = wpsl_get_custom_templates();

    if ( $custom_templates ) {
        ?>
        <div class="wpsl-warning-callout">
            <h2><?php esc_html_e( 'Custom template detected', 'wp-store-locator' ); ?></h2>
            <p><?php esc_html_e( 'We detected one or more custom templates created for WP Store Locator 2:', 'wp-store-locator' ); ?></p>
            <ul>
                <?php foreach ( $custom_templates as $custom_template ) { ?>
                    <li><?php echo esc_html( $custom_template['name'] ); ?></li>
                <?php } ?>
            </ul>
            <p><?php esc_html_e( 'Templates created for v2 keep working automatically — WP Store Locator detects them and runs them through its backward compatibility layer. We still recommend updating them to the v3 structure when you can.', 'wp-store-locator' ); ?></p>
            <p><a target="_blank" href="https://wpstorelocator.co/document/load-custom-store-locator-template/#legacy-mode"><?php esc_html_e( 'Read more', 'wp-store-locator' ); ?></a></p>
        </div>
        <?php
    }
    ?>

    <h2 class="wpsl-onboarding-next-title"><?php esc_html_e( 'Next steps', 'wp-store-locator' ); ?></h2>
    <ol class="wpsl-onboarding-next-steps">
        <li>
            <span class="wpsl-next-step-number" aria-hidden="true">1</span>
            <div class="wpsl-next-step-text">
                <strong><?php esc_html_e( 'Add your first store', 'wp-store-locator' ); ?></strong>
                <span><?php esc_html_e( 'Name, address and opening hours. The address is turned into map coordinates when you save.', 'wp-store-locator' ); ?></span>
            </div>
            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpsl_stores' ) ); ?>"><?php esc_html_e( 'Add store', 'wp-store-locator' ); ?></a>
        </li>
        <li>
            <span class="wpsl-next-step-number" aria-hidden="true">2</span>
            <div class="wpsl-next-step-text">
                <strong><?php esc_html_e( 'Put the locator on a page', 'wp-store-locator' ); ?></strong>
                <span><?php
                    /* translators: 1: opening link tag for the shortcode docs, 2: closing link tag */
                    echo sprintf( esc_html__( 'Paste the %1$sshortcode%2$s into the page where the store locator should appear.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/shortcodes/">', '</a>' ); ?></span>
                <span class="wpsl-shortcode-copy">
                    <code>[wpsl]</code>
                    <button type="button" class="button wpsl-copy-shortcode" data-copy="[wpsl]"><svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="5" y="5" width="8.5" height="8.5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M3 10.5V3.5A1 1 0 0 1 4 2.5h6.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg><span><?php esc_html_e( 'Copy', 'wp-store-locator' ); ?></span></button>
                </span>
            </div>
            <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Add a new page', 'wp-store-locator' ); ?></a>
        </li>
        <li>
            <span class="wpsl-next-step-number" aria-hidden="true">3</span>
            <div class="wpsl-next-step-text">
                <strong><?php esc_html_e( 'Make it match your site', 'wp-store-locator' ); ?></strong>
                <span><?php esc_html_e( 'Pick a layout and colors, and choose what shows in the search results.', 'wp-store-locator' ); ?></span>
            </div>
            <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance' ) ); ?>"><?php esc_html_e( 'Open Appearance', 'wp-store-locator' ); ?></a>
        </li>
    </ol>

    <div class="wpsl-onboarding-learn-more">
        <p class="wpsl-onboarding-links">
            <strong><?php esc_html_e( 'Learn More', 'wp-store-locator' ); ?>:</strong>
            <a target="_blank" href="https://wpstorelocator.co/documentation/getting-started/"><?php esc_html_e( 'Getting Started', 'wp-store-locator' ); ?></a>
            <a target="_blank" href="https://wpstorelocator.co/documentation/troubleshooting/"><?php esc_html_e( 'Troubleshooting', 'wp-store-locator' ); ?></a>
            <a target="_blank" href="https://wpstorelocator.co/documentation/customisations/"><?php esc_html_e( 'Customizations', 'wp-store-locator' ); ?></a>
            <a target="_blank" href="https://wpstorelocator.co/support/"><?php esc_html_e( 'Contact support', 'wp-store-locator' ); ?></a>
        </p>
        <?php
        // Only promote the CSV Manager add-on if it isn't already active.
        if ( ! defined( 'WPSL_CSV_VERSION_NUM' ) ) { ?>
            <p class="wpsl-onboarding-note"><?php
                /* translators: 1: opening link tag, 2: closing link tag */
                echo sprintf( esc_html__( 'Need to bulk import locations? The %1$sCSV Manager%2$s add-on imports them from a spreadsheet.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/add-ons/csv-manager/">','</a>' ); ?></p>
        <?php } ?>
    </div>
</div>
