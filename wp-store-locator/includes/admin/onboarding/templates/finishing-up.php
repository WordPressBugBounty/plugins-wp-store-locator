<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wpsl-onboarding" class="wpsl-onboarding-complete">
    <input type="hidden" name="map-service" value="<?php echo esc_attr( $this->map_service ); ?>">

    <div class="wpsl-onboarding-hero">
        <h1><?php esc_html_e( 'Setup Complete' , 'wp-store-locator' ); ?></h1>
        <p><?php
            /* translators: 1: opening link tag for the shortcode docs, 2: closing link tag */
            echo sprintf( esc_html__( 'Your store locator is ready. Add your first store, then place the [wpsl] %1$sshortcode%2$s on the page where the locator should appear.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/shortcodes/">','</a>' ); ?></p>
        <p class="wpsl-onboarding-cta">
            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpsl_stores' ) ); ?>"><?php esc_html_e( 'Add your first store', 'wp-store-locator' ); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' ) ); ?>"><?php esc_html_e( 'Review settings', 'wp-store-locator' ); ?></a>
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
        $service_labels = [
            'gmaps'  => esc_html__( 'Google Maps', 'wp-store-locator' ),
            'mapbox' => esc_html__( 'Mapbox', 'wp-store-locator' ),
            'stadia' => esc_html__( 'Stadia Maps', 'wp-store-locator' ),
        ];

        $map_service_label = isset( $service_labels[ $fallback_service ] ) ? $service_labels[ $fallback_service ] : $fallback_service;
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
    <div class="wpsl-onboarding-learn-more">
        <h2><?php esc_html_e( 'Learn More', 'wp-store-locator' ); ?></h2>
        <ul>
            <li><a target="_blank" href="https://wpstorelocator.co/documentation/getting-started/"><?php esc_html_e( 'Getting Started', 'wp-store-locator' ); ?></a></li>
            <li><a target="_blank" href="https://wpstorelocator.co/documentation/troubleshooting/"><?php esc_html_e('Troubleshooting', 'wp-store-locator' ); ?></a></li>
            <li><a target="_blank" href="https://wpstorelocator.co/documentation/customisations/"><?php esc_html_e('Customizations', 'wp-store-locator' ); ?></a></li>
        </ul>
        <?php
        // Only promote the CSV Manager add-on if it isn't already active.
        if ( ! defined( 'WPSL_CSV_VERSION_NUM' ) ) { ?>
            <p class="wpsl-onboarding-note"><?php
                /* translators: 1: opening link tag, 2: closing link tag */
                echo sprintf( esc_html__( 'Need to bulk import locations? The %1$sCSV Manager%2$s add-on imports them from a spreadsheet.', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/add-ons/csv-manager/">','</a>' ); ?></p>
        <?php } ?>
    </div>
    <?php $this->action_buttons(); ?>
</div>