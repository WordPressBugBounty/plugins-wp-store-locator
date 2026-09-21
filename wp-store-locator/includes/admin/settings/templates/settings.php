<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load required services
$settings_service = wpsl_get_service( 'settings_service' );
$admin_settings = wpsl_get_service( 'admin_settings' );
$wpsl_settings = wpsl_get_service( 'wpsl_settings' );

$sections = $settings_service->get_sections();

/*
 * Two lists on purpose: the nav shows what simple mode leaves, the form
 * renders everything. See Settings_Service::get_nav_sections().
 */
$nav_sections = $settings_service->get_nav_sections();
$simple_mode  = wpsl_get_service( 'simple_mode' );

// Set CSS classes based on navigation display setting
$css = [
    'content_wrap' => 'wpsl-settings-grid',
    'nav' => ''
];

if ( isset( $_GET['nav'] ) && $_GET['nav'] == 'none' ) {
    $css = [
        'content_wrap' => '',
        'nav' => 'class="wpsl-hide"'
    ];
}

$settings = $wpsl_settings->get_all();
?>
<div id="wpsl-content-wrap" class="<?php echo esc_attr( $css['content_wrap'] ); ?>">
    <nav id="wpsl-nav" <?php echo $css['nav']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string, no dynamic data ?>>
        <ul>
            <?php
            foreach ( $nav_sections as $name => $setting ) {
            ?>
                <li id="wpsl-nav-<?php echo esc_attr( $name ); ?>" <?php if ( $name == 'api' ) { echo 'class="wpsl-active-nav-item"'; } ?>>
                    <a href="#wpsl-<?php echo esc_attr( $name ); ?>">
                        <span class="wpsl-icon-<?php echo esc_attr( $name ); ?><?php echo ( $name === 'local_pages' ) ? ' wpsl-icon-svg' : ''; ?>">
                            <?php if ( $name === 'local_pages' ) { echo wpsl_get_svg_icon( 'local_pages' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns static SVG markup ?><?php } ?><?php echo esc_html( $setting['text'] ); ?>
                        </span>
                    </a>
                    <?php if ( isset( $setting['sub'] ) && is_array( $setting['sub'] ) ) { ?>
                        <ul class="wpsl-sub-nav">
                            <?php foreach ( $setting['sub'] as $sub_name => $sub_settings ) { 
                                // Skip the customize option, shown only when user clicks on 'customize' on the 'appearance' page
                                if ( $sub_name == 'customize' ) continue;
                            ?>
                                <li>
                                    <a href="#wpsl-<?php echo esc_attr( $sub_name ); ?>">
                                        <span><?php echo esc_html( $sub_settings['text'] ); ?></span>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    </nav>
    
    <div id="wpsl-settings-content">
        <form id="wpsl-settings-form" class="wpsl-toggles-pending wpsl-<?php echo esc_attr( $wpsl_settings->get( 'api', 'active_map_service' ) ); ?>-active" method="post" action="options.php" autocomplete="off" accept-charset="utf-8">
            <?php
            settings_errors();

            // Custom API key error notices (non-bold code/reason + hint paragraph).
            wpsl_get_service( 'validate_keys' )->render_key_error_notices();

            // Create a single template_args array with all shared dependencies
            $template_args = [
                'settings_manager' => $wpsl_settings,
                'settings_service' => $settings_service,
                'ui'               => wpsl_get_service( 'admin_ui' ),
                'api_settings'     => wpsl_get_service( 'api_settings' ),
                'map_settings'     => wpsl_get_service( 'map_settings' ),
                'appearance'       => wpsl_get_service( 'appearance' ),
                'theme_styles'     => wpsl_get_service( 'theme_styles' ),
                'field_manager'    => wpsl_get_service( 'field_manager' ),
                'metaboxes'        => wpsl_get_service( 'metaboxes' ),
                'i18n'             => wpsl_get_service( 'i18n' ),
            ];
            
            // Create the setting sections
            foreach ( $sections as $name => $setting ) {
                $file = $admin_settings->get_template_args( $name );
                $template_args['section_settings'] = $wpsl_settings->get_group( $name );
                
                extract( $template_args );

                $advanced_section = $simple_mode && $simple_mode->is_hidden( $name );

                if ( $advanced_section ) {
                    echo '<div class="wpsl-advanced">';
                }

                require_once( $file['path'] . $file['name'] . '.php' );

                // Process submenu sections - look in parent section's subfolder
                if ( isset( $setting['sub'] ) && is_array( $setting['sub'] ) ) {
                    foreach ( $setting['sub'] as $sub_name => $sub_settings ) {
                        // Subsections are in /sections/{parent}/{subsection}.php
                        $sub_file_path = $file['path'] . $name . '/' . $sub_name . '.php';

                        if ( file_exists( $sub_file_path ) ) {
                            require_once( $sub_file_path );
                        }
                    }
                }

                if ( $advanced_section ) {
                    echo '</div>';
                }
            }
            ?>
            <input type="hidden" name="wpsl_active_section" id="wpsl-active-nav-section" value="api-settings">
            <?php settings_fields( 'wpsl_settings' ); ?>
        </form>
    </div>
</div>

<?php
// Include dialog templates
$dialog_templates = [
    'geocode-api-test',
    'delete-confirmation',
    'insert-field-editor',
    'data-management'
];

// Matches the condition the Tools row uses, so the dialog isn't shipped without its trigger.
if ( get_option( 'wpsl_legacy_support' ) && wpsl_get_service( 'hours_converter' )->has_legacy_hours() ) {
    $dialog_templates[] = 'hours-converter';
}

foreach ( $dialog_templates as $template ) {
    require_once( WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/dialog/' . $template . '.php' );
}