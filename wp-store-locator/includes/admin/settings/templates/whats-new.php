<?php
/**
 * The "What's New" screen shown to users after updating from 2.x to 3.0.
 *
 * Reachable at edit.php?post_type=wpsl_stores&page=wpsl_whats-new, the page
 * itself is hidden from the Store Locator menu.
 *
 * @package WPSL
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
    return;
}

$blog_post_url = wpsl_release_post_url();

/*
 * Only a site that migrated from 2.x has wpsl_updated_from.
 */
$upgraded_from_v2 = (bool) get_option( 'wpsl_updated_from', '' );

$demo_url = 'https://wpstorelocator.co/demos/vertical-theme/?utm_source=wpsl-whats-new&utm_medium=admin&utm_campaign=v3-release';

$settings_url      = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' );
$appearance_url    = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance' );
$marker_studio_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' );
$map_shapes_url    = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_map_shapes' );
$onboarding_url    = admin_url( 'index.php?page=wpsl-onboarding' );

$highlights = [
    [
        'icon'      => 'location-alt',
        'title'     => esc_html__( 'Choose Your Map Provider', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Run the store locator on Google Maps, Mapbox, Stadia Maps, or OpenStreetMap. Switch providers at any time, your locations stay untouched.', 'wp-store-locator' ),
        'link'      => $settings_url . '#wpsl-api',
        'link_text' => esc_html__( 'Pick a provider', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'admin-appearance',
        'title'     => esc_html__( 'Appearance Editor', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Customize the theme colors, buttons, fonts, dimensions and map styles visually. No CSS required.', 'wp-store-locator' ),
        'link'      => $appearance_url,
        'link_text' => esc_html__( 'Open the Appearance editor', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'columns',
        'title'     => esc_html__( 'New Vertical Theme', 'wp-store-locator' ),
        'desc'      => esc_html__( 'A modern two-column layout with the search form and results on the left, and the map on the right.', 'wp-store-locator' ),
        'link'      => $demo_url,
        'link_text' => esc_html__( 'Browse the themes', 'wp-store-locator' ),
        'external'  => true,
    ],
    [
        'icon'      => 'location',
        'title'     => esc_html__( 'Marker Studio', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Build your own markers from a shape, a color and an icon, or upload your logo. Use one for the whole map, or give every category and every store its own, plus a different one for when a visitor selects it.', 'wp-store-locator' ),
        'link'      => $marker_studio_url,
        'link_text' => esc_html__( 'Open Marker Studio', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'image-filter',
        'title'     => esc_html__( 'Map Shapes', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Draw service areas, delivery zones and routes right on the map with polygons, circles, rectangles and lines, and style each one.', 'wp-store-locator' ),
        'link'      => $map_shapes_url,
        'link_text' => esc_html__( 'Start drawing', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'search',
        'title'     => esc_html__( 'Smarter Searches', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Users can now search by store name, and searching for a state or country returns all matching locations.', 'wp-store-locator' ),
        'link'      => $settings_url . '#wpsl-search',
        'link_text' => esc_html__( 'Review the search options', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'forms',
        'title'     => esc_html__( 'Fields Manager', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Add custom data fields to your locations without writing code, and show them anywhere in the store locator templates.', 'wp-store-locator' ),
        'link'      => $settings_url . '#wpsl-fields-manager',
        'link_text' => esc_html__( 'Manage your fields', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'clock',
        'title'     => esc_html__( 'Open / Closed Status', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Show a live "Open now" or "Closed" status for every location, with support for special hours and temporarily or permanently closed stores.', 'wp-store-locator' ),

        // No link: the hours are set per location in the store editor, so
        // there is no settings section to send anyone to.
    ],
    [
        'icon'      => 'editor-code',
        'title'     => esc_html__( 'Template Section Editor', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Fine-tune the markup of the info window, the search results and other template sections directly from the admin.', 'wp-store-locator' ),
        // The link only for someone the section editor is open to.
        'link'      => \WPSL\Admin\Settings\Section_Editor::current_user_can_edit() ? $settings_url . '#wpsl-section-editor' : '',
        'link_text' => esc_html__( 'Open the Section Editor', 'wp-store-locator' ),
    ],
    [
        'icon'      => 'shield',
        'title'     => esc_html__( 'Built-in GDPR Support', 'wp-store-locator' ),
        'desc'      => esc_html__( 'Only load the map after the user consents to it.', 'wp-store-locator' ),
        'link'      => $settings_url . '#wpsl-gdpr',
        'link_text' => esc_html__( 'Set up GDPR', 'wp-store-locator' ),
    ],
];
?>
<style>
    .wpsl-whats-new-hero {
        background-color: #fff;
        border-radius: var( --wpsl-radius, 3px );
        box-shadow: 0 1px 4px rgb( 18 25 97 / 8% );
        color: #3c434a;
        margin-bottom: 15px;
        padding: 40px 40px 35px 40px;
        text-align: center;
    }

    .wpsl-whats-new-badge {
        background-color: #f0f6fc;
        border-radius: 12px;
        color: #2271b1;
        display: inline-block;
        font-size: 12px;
        font-weight: 600;
        padding: 3px 12px;
        text-transform: uppercase;
    }

    .wpsl-whats-new-hero h1 {
        color: #1d2327;
        font-size: 28px;
    }

    .wpsl-whats-new-hero > p {
        font-size: 15px;
        margin: 0 auto;
        max-width: 640px;
    }

    .wpsl-whats-new-grid {
        display: grid;
        grid-gap: 15px;
        grid-template-columns: 1fr 1fr;
    }

    .wpsl-whats-new-card {
        background-color: #fff;
        border-radius: var( --wpsl-radius, 3px );
        box-shadow: 0 1px 4px rgb( 18 25 97 / 8% );
        color: #3c434a;
        display: flex;
        flex-direction: column;
        padding: 20px;
    }

    .wpsl-whats-new-card-icon {
        align-items: center;
        background-color: #f0f6fc;
        border-radius: 50%;
        color: #2271b1;
        display: flex;
        height: 40px;
        justify-content: center;
        margin-bottom: 12px;
        width: 40px;
    }

    .wpsl-whats-new-card-icon .dashicons {
        font-size: 20px;
        height: 20px;
        width: 20px;
    }

    .wpsl-whats-new-card h3 {
        color: #1d2327;
        font-size: 15px;
        margin: 0 0 6px 0;
    }

    .wpsl-whats-new-card p {
        flex-grow: 1;
        font-size: 13px;
        margin: 0 0 12px 0;
    }

    .wpsl-whats-new-card p:last-child {
        margin-bottom: 0;
    }

    .wpsl-whats-new-card a {
        font-size: 13px;
        text-decoration: none;
    }

    .wpsl-whats-new-footer {
        margin: 20px 0 40px 0;
        text-align: center;
    }

    .wpsl-whats-new-footer .button {
        margin: 0 5px;
    }

    .wpsl-whats-new-onboarding {
        color: #646970;
        font-size: 13px;
        margin: 15px auto 0 auto;
        max-width: 560px;
    }

    @media ( max-width: 782px ) {
        .wpsl-whats-new-grid {
            grid-template-columns: 1fr;
        }

        .wpsl-whats-new-hero {
            padding: 25px 20px;
        }
    }
</style>

<div id="wpsl-content-wrap">
    <div class="wpsl-whats-new-hero">
        <span class="wpsl-whats-new-badge"><?php esc_html_e( 'Version 3', 'wp-store-locator' ); ?></span>
        <?php if ( $upgraded_from_v2 ) { ?>
        <h1><?php esc_html_e( "What's New in WP Store Locator", 'wp-store-locator' ); ?></h1>
        <p><?php esc_html_e( 'The plugin was rebuilt from the ground up: run it on the map provider of your choice, design your own markers in Marker Studio, add custom location data with the Fields Manager, and restyle the whole locator without writing CSS. Here are the highlights.', 'wp-store-locator' ); ?></p>
        <?php } else { ?>
        <h1><?php esc_html_e( 'What WP Store Locator Can Do', 'wp-store-locator' ); ?></h1>
        <p><?php esc_html_e( 'Run it on the map provider of your choice, design your own markers in Marker Studio, add custom location data with the Fields Manager, and restyle the whole locator without writing CSS. Here are the highlights.', 'wp-store-locator' ); ?></p>
        <?php } ?>
    </div>

    <div class="wpsl-whats-new-grid">
        <?php foreach ( $highlights as $highlight ) { ?>
        <div class="wpsl-whats-new-card">
            <div class="wpsl-whats-new-card-icon">
                <span class="dashicons dashicons-<?php echo esc_attr( $highlight['icon'] ); ?>"></span>
            </div>
            <h3><?php echo esc_html( $highlight['title'] ); ?></h3>
            <p><?php echo esc_html( $highlight['desc'] ); ?></p>
            <?php
            /*
             * A card without a link is one whose feature has no single screen
             * to open -- the paragraph is then the last child and drops its
             * bottom margin, so the card does not end in a gap.
             */
            if ( ! empty( $highlight['link'] ) ) {
                ?>
            <a href="<?php echo esc_url( $highlight['link'] ); ?>"<?php echo empty( $highlight['external'] ) ? '' : ' target="_blank" rel="noopener"'; ?>><?php echo esc_html( $highlight['link_text'] ); ?> &rarr;</a>
            <?php } ?>
        </div>
        <?php } ?>
    </div>

    <div class="wpsl-whats-new-footer">
        <a class="button-primary" target="_blank" rel="noopener" href="<?php echo esc_url( $blog_post_url ); ?>"><?php esc_html_e( 'Read the full announcement', 'wp-store-locator' ); ?></a>
        <a class="button" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Go to the settings', 'wp-store-locator' ); ?></a>

        <p class="wpsl-whats-new-onboarding">
            <?php esc_html_e( 'Your existing settings carried over, so there is nothing you have to change.', 'wp-store-locator' ); ?>
            <a href="<?php echo esc_url( $onboarding_url ); ?>"><?php esc_html_e( 'Run the setup wizard', 'wp-store-locator' ); ?></a>
            <?php esc_html_e( 'if you want to move the store locator to one of the new map providers.', 'wp-store-locator' ); ?>
        </p>
    </div>
</div>