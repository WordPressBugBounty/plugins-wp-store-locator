<?php
/**
 * Search Results Tab Content
 *
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$wpsl_settings = wpsl_get_service( 'wpsl_settings' );
$section_settings = $wpsl_settings->get_group( 'appearance' );
$ui = wpsl_get_service( 'admin_ui' );
$template_id = $wpsl_settings->get( 'appearance', 'template_id' );
?>

<?php require WPSL_PLUGIN_DIR . 'includes/admin/appearance/templates/partials/custom-section-notice.php'; ?>

<?php $preloader_color = wpsl_get_preloader_color(); ?>
<p>
    <label for="wpsl-preloader-color"><?php esc_html_e( 'Preloader color', 'wp-store-locator' ); ?></label>
    <?php echo $ui->create_dropdown( 'preloader_color' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
</p>
<div class="wpsl-conditional-option"<?php if ( $preloader_color !== 'custom' ) { echo ' style="display:none;"'; } ?>>
    <p>
        <label for="wpsl-preloader-custom-color"><?php esc_html_e( 'Custom color', 'wp-store-locator' ); ?></label>
        <input id="wpsl-preloader-custom-color" class="wpsl-color-field" name="wpsl_appearance[search][preloader_custom_color]" type="text" value="<?php echo esc_attr( isset( $section_settings['preloader_custom_color'] ) ? $section_settings['preloader_custom_color'] : '' ); ?>" data-default="#000000" />
    </p>
</div>
<p class="wpsl-results-columns" <?php if ( $template_id !== 'horizontal' ) { echo 'style="display:none"'; } ?>>
    <label for="wpsl-result-columns"><?php esc_html_e( 'Search results columns', 'wp-store-locator' ); ?></label>
    <?php echo $ui->create_dropdown( 'result_columns' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
</p>
<p class="wpsl-filter-layout-option" <?php if ( $template_id !== 'vertical' ) { echo 'style="display:none"'; } ?>>
    <label for="wpsl-filter-layout"><?php esc_html_e( 'Filter layout', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'How the result filter buttons are shown: in a horizontal row (wraps when there is not enough space), stacked full-width on top of each other, or nested under a single "Filters" button that expands to each filter and its options. Useful when there are several filters or a narrow panel.', 'wp-store-locator' ); ?></span></span></label>
    <?php echo $ui->create_dropdown( 'filter_layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
</p>
<p>
    <label for="wpsl-enable-icons"><?php esc_html_e( 'Use icons?', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'Enabling this will include different icons in the results (for example email, phone, hours and directions).', 'wp-store-locator' ); ?></span></span><?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- section_sync_warning_span() returns pre-escaped HTML
    echo $ui->section_sync_warning_span( 'icons' );
    ?></label>
    <input type="checkbox" <?php checked( isset( $section_settings['icons']['enabled'] ) ? $section_settings['icons']['enabled'] : false, true ); ?> name="wpsl_appearance[icons][enabled]" id="wpsl-enable-icons" class="wpsl-has-conditional-option">
</p>
<div class="wpsl-conditional-option" <?php if ( ! isset( $section_settings['icons']['enabled'] ) || ! $section_settings['icons']['enabled'] ) { echo 'style="display:none;"'; } ?>>
    <?php
    // Address icon dropdown
    $address_icon = isset( $section_settings['icons']['address'] ) ? $section_settings['icons']['address'] : 'marker';
    $address_icons = [
        'marker'           => [ 'label' => __( 'Marker', 'wp-store-locator' ),           'class' => 'wpsl-icon-address-marker' ],
        'house'            => [ 'label' => __( 'House', 'wp-store-locator' ),             'class' => 'wpsl-icon-address-house' ],
        'building'         => [ 'label' => __( 'Building', 'wp-store-locator' ),          'class' => 'wpsl-icon-address-building' ],
        'building-outline' => [ 'label' => __( 'Building outline', 'wp-store-locator' ),  'class' => 'wpsl-icon-address-building-outline' ],
    ];
    if ( ! isset( $address_icons[ $address_icon ] ) ) {
        $address_icon = 'marker';
    }
    ?>
    <div class="wpsl-icon-selector">
        <label class="wpsl-icon-selector-label"><?php esc_html_e( 'Address details icon', 'wp-store-locator' ); ?></label>
        <div class="wpsl-icon-dropdown-wrapper" data-icon-group="address">
            <button type="button" class="wpsl-icon-dropdown-button">
                <span class="wpsl-icon-dropdown-selected">
                    <span class="<?php echo esc_attr( $address_icons[ $address_icon ]['class'] ); ?>"></span>
                </span>
                <div class="wpsl-icon-dropdown-arrow"></div>
            </button>
            <div class="wpsl-icon-dropdown-menu">
                <?php foreach ( $address_icons as $value => $icon_data ) : ?>
                    <div class="wpsl-icon-dropdown-item<?php echo $address_icon === $value ? ' selected' : ''; ?>" data-icon-value="<?php echo esc_attr( $value ); ?>">
                        <span class="<?php echo esc_attr( $icon_data['class'] ); ?>"></span>
                        <span class="wpsl-icon-dropdown-item-label"><?php echo esc_html( $icon_data['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $address_icons as $value => $icon_data ) : ?>
                <input type="radio" name="wpsl_appearance[icons][address]" value="<?php echo esc_attr( $value ); ?>"<?php checked( $address_icon, $value ); ?>>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Phone icon dropdown
    $phone_icon = isset( $section_settings['icons']['phone'] ) ? $section_settings['icons']['phone'] : 'phone';
    $phone_icons = [
        'phone'        => [ 'label' => __( 'Phone', 'wp-store-locator' ),        'class' => 'wpsl-icon-phone' ],
        'mobile-phone' => [ 'label' => __( 'Mobile phone', 'wp-store-locator' ), 'class' => 'wpsl-icon-mobile-phone' ],
    ];

    if ( ! isset( $phone_icons[ $phone_icon ] ) ) {
        $phone_icon = 'phone';
    }
    ?>
    <div class="wpsl-icon-selector">
        <label class="wpsl-icon-selector-label"><?php esc_html_e( 'Phone icon', 'wp-store-locator' ); ?></label>
        <div class="wpsl-icon-dropdown-wrapper" data-icon-group="phone">
            <button type="button" class="wpsl-icon-dropdown-button">
                <span class="wpsl-icon-dropdown-selected">
                    <span class="<?php echo esc_attr( $phone_icons[ $phone_icon ]['class'] ); ?>"></span>
                </span>
                <div class="wpsl-icon-dropdown-arrow"></div>
            </button>
            <div class="wpsl-icon-dropdown-menu">
                <?php foreach ( $phone_icons as $value => $icon_data ) : ?>
                    <div class="wpsl-icon-dropdown-item<?php echo $phone_icon === $value ? ' selected' : ''; ?>" data-icon-value="<?php echo esc_attr( $value ); ?>">
                        <span class="<?php echo esc_attr( $icon_data['class'] ); ?>"></span>
                        <span class="wpsl-icon-dropdown-item-label"><?php echo esc_html( $icon_data['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $phone_icons as $value => $icon_data ) : ?>
                <input type="radio" name="wpsl_appearance[icons][phone]" value="<?php echo esc_attr( $value ); ?>"<?php checked( $phone_icon, $value ); ?>>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    // Email icon dropdown
    $email_icon = isset( $section_settings['icons']['email'] ) ? $section_settings['icons']['email'] : 'email';
    $email_icons = [
        'email'         => [ 'label' => __( 'Email', 'wp-store-locator' ),         'class' => 'wpsl-icon-email' ],
        'email-outline' => [ 'label' => __( 'Email outline', 'wp-store-locator' ), 'class' => 'wpsl-icon-email-outline' ],
    ];

    if ( ! isset( $email_icons[ $email_icon ] ) ) {
        $email_icon = 'email';
    }
    ?>
    <div class="wpsl-icon-selector">
        <label class="wpsl-icon-selector-label"><?php esc_html_e( 'Email icon', 'wp-store-locator' ); ?></label>
        <div class="wpsl-icon-dropdown-wrapper" data-icon-group="email">
            <button type="button" class="wpsl-icon-dropdown-button">
                <span class="wpsl-icon-dropdown-selected">
                    <span class="<?php echo esc_attr( $email_icons[ $email_icon ]['class'] ); ?>"></span>
                </span>
                <div class="wpsl-icon-dropdown-arrow"></div>
            </button>
            <div class="wpsl-icon-dropdown-menu">
                <?php foreach ( $email_icons as $value => $icon_data ) : ?>
                    <div class="wpsl-icon-dropdown-item<?php echo $email_icon === $value ? ' selected' : ''; ?>" data-icon-value="<?php echo esc_attr( $value ); ?>">
                        <span class="<?php echo esc_attr( $icon_data['class'] ); ?>"></span>
                        <span class="wpsl-icon-dropdown-item-label"><?php echo esc_html( $icon_data['label'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php foreach ( $email_icons as $value => $icon_data ) : ?>
                <input type="radio" name="wpsl_appearance[icons][email]" value="<?php echo esc_attr( $value ); ?>"<?php checked( $email_icon, $value ); ?>>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<p>
    <label for="wpsl-cta-details-button"><?php esc_html_e( 'More details link', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'If a URL is provided in the details, the location name will link to that website. However, if permalinks are enabled, it will always link to its local page.', 'wp-store-locator' ); ?></span></span><?php
    /*
     * Both directions of the mismatch belong on this one toggle:
     * cta_section when the link is missing, cta_details when it is still
     * in the template after being switched off.
     */
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- section_sync_warning_span() returns pre-escaped HTML
    echo $ui->section_sync_warning_span( [ 'cta_section', 'cta_details' ] );
    ?></label>
    <input type="checkbox" value="1" <?php checked( isset( $section_settings['cta']['details'] ) ? $section_settings['cta']['details'] : false, true ); ?> name="wpsl_appearance[cta][details]" id="wpsl-cta-details-button" class="wpsl-cta-checkbox wpsl-has-conditional-option" data-cta-type="details">
</p>
<?php
// Linking to a local page only works when landing pages (permalinks) are enabled in the Local Pages settings.
$permalinks_enabled   = (bool) $wpsl_settings->get( 'local_pages', 'permalinks' );
$details_target       = isset( $section_settings['cta']['details_target'] ) ? $section_settings['cta']['details_target'] : 'website';
$show_details_warning = ( ! $permalinks_enabled && 'landing_page' === $details_target );
$local_pages_url        = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-local_pages' );
?>
<div class="wpsl-conditional-option" <?php if ( ! isset( $section_settings['cta']['details'] ) || ! $section_settings['cta']['details'] || ! $permalinks_enabled ) { echo 'style="display:none;"'; } ?>>
    <p>
        <label for="wpsl-more-details-target"><?php esc_html_e( 'More details target', 'wp-store-locator' ); ?><span class="wpsl-info wpsl-warning wpsl-more-details-warning<?php echo $show_details_warning ? '' : ' wpsl-hide'; ?>" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'More information', 'wp-store-locator' ); ?>"><?php /* translators: %1$s: opening link tag to the Local Pages settings, %2$s: closing link tag */ ?><span class="wpsl-info-text wpsl-hide"><?php echo sprintf( esc_html__( 'Linking to a local page only works when landing pages are enabled in the %1$sLocal Pages settings%2$s.', 'wp-store-locator' ), '<a class="wpsl-trigger-nav" data-item="local_pages" href="' . esc_url( $local_pages_url ) . '">', '</a>' ); ?></span></span></label>
        <?php echo $ui->create_dropdown( 'cta_details_target', 'data-permalinks-enabled="' . ( $permalinks_enabled ? '1' : '0' ) . '"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
    </p>
</div>