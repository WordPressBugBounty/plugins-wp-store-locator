<?php
/**
 * The six Quick setup tiles.
 *
 * Shared by the Home card and the last step of the setup wizard, so the two
 * cannot drift. Every answer is a button, the script saves it the moment it
 * is picked and updates the buttons and notes in place.
 *
 * Expects:
 *   $quick_setup  \WPSL\Admin\Home\Quick_Setup
 *   $context      'home' or 'wizard'. The home links every tile to its
 *                 settings section, the wizard has no such links and prints
 *                 its own status line ( the card keeps that in its header ).
 *
 * @since 3.1.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$qs_is_home = ( 'wizard' !== $context );
$qs_context = $qs_is_home ? 'home' : 'wizard';
$qs_answers = $quick_setup->get_answers();
$qs_notes   = $quick_setup->get_notes();

$qs_settings_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings' );

// Where the "More ... options" link of every tile goes.
$qs_more = [
    'method'    => [ __( 'More search options', 'wp-store-locator' ), $qs_settings_url . '#wpsl-search' ],
    'countries' => [ __( 'More restriction options', 'wp-store-locator' ), $qs_settings_url . '#wpsl-api' ],
    'locate'    => [ __( 'More location options', 'wp-store-locator' ), $qs_settings_url . '#wpsl-search' ],
    'pages'     => [ __( 'More location page options', 'wp-store-locator' ), $qs_settings_url . '#wpsl-local_pages' ],
    'layout'    => [ __( 'Colors and styles in Appearance', 'wp-store-locator' ), admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_appearance' ) ],
    'filters'   => [ __( 'More filter options', 'wp-store-locator' ), $qs_settings_url . '#wpsl-search' ],
];

$qs_info_icon = '<svg class="wpsl-qs-note-icon" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/></svg>';

/**
 * The one-line note of a tile or a filter. Always printed, hidden while it
 * has no text, so the script can show it without building it.
 *
 * @param string $key  A question id, or filter_categories / filter_radius / filter_results.
 * @param string $id   The element id, for aria-describedby.
 * @param string $link Optional link ( escaped html ) after the text.
 */
$qs_note = function( $key, $id, $link = '' ) use ( $qs_notes, $qs_info_icon ) {
    $text = isset( $qs_notes[ $key ] ) ? $qs_notes[ $key ] : '';

    echo '<p class="wpsl-qs-note" id="' . esc_attr( $id ) . '" data-note-for="' . esc_attr( $key ) . '"' . ( '' === $text ? ' hidden' : '' ) . '>';
    echo $qs_info_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup defined above, no user input.
    echo '<span class="wpsl-qs-note-text">' . esc_html( $text ) . '</span>';
    echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts by the caller.
    echo '</p>';
};

/**
 * One radio answer.
 *
 * @param string $value    The answer value.
 * @param string $label    Plain text.
 * @param string $desc     Plain text.
 * @param bool   $checked  Whether it is the saved answer.
 * @param bool   $disabled Whether the answer doesn't apply right now.
 */
$qs_radio = function( $value, $label, $desc, $checked, $disabled ) {
    ?>
    <button type="button" class="wpsl-qs-answer" role="radio" aria-checked="<?php echo $checked ? 'true' : 'false'; ?>"<?php echo $disabled ? ' aria-disabled="true"' : ''; ?> data-value="<?php echo esc_attr( $value ); ?>">
        <span class="wpsl-qs-dot" aria-hidden="true"></span>
        <span class="wpsl-qs-answer-text">
            <span class="wpsl-qs-answer-label"><?php echo esc_html( $label ); ?></span>
            <span class="wpsl-qs-answer-desc"><?php echo esc_html( $desc ); ?></span>
        </span>
    </button>
    <?php
};

// The link to the settings section of a tile, the wizard has none.
$qs_more_link = function( $question ) use ( $qs_is_home, $qs_more ) {
    if ( $qs_is_home ) {
        echo '<a class="wpsl-qs-more" href="' . esc_url( $qs_more[ $question ][1] ) . '">' . esc_html( $qs_more[ $question ][0] ) . '</a>';
    }
};

$qs_countries_on = ! empty( $qs_answers['countries'] );

// The layouts with a thumbnail, and the active one.
$qs_templates   = $quick_setup->get_layout_choices();
$qs_layout_desc = '';

foreach ( $qs_templates as $qs_template ) {
    if ( $qs_template['id'] === $qs_answers['layout'] ) {
        $qs_layout_desc = html_entity_decode( $qs_template['desc'], ENT_QUOTES, 'UTF-8' );
    }
}

$qs_filters = [
    'categories' => __( 'Categories', 'wp-store-locator' ),
    'radius'     => __( 'Search radius', 'wp-store-locator' ),
    'results'    => __( 'Number of results', 'wp-store-locator' ),
];

// The link only for someone the section editor is open to, as on the Settings page.
$qs_pages_link = '';

if ( \WPSL\Admin\Settings\Section_Editor::current_user_can_edit() ) {
    $qs_pages_link = ' <a class="wpsl-qs-note-link" href="' . esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings&wpsl-compare=1#wpsl-section-editor' ) ) . '">' . esc_html__( 'Review differences', 'wp-store-locator' ) . '</a>';
}
?>
<div class="wpsl-qs-tiles wpsl-qs-context-<?php echo esc_attr( $qs_context ); ?>">
    <?php if ( ! $qs_is_home ) { ?>
        <p class="wpsl-qs-status" role="status"></p>
    <?php } ?>

    <div class="wpsl-qs-tile" id="wpsl-qs-method" data-question="method">
        <h4 class="wpsl-qs-question" id="wpsl-qs-method-title"><?php esc_html_e( 'How do visitors search?', 'wp-store-locator' ); ?></h4>
        <div class="wpsl-qs-answers" role="radiogroup" aria-labelledby="wpsl-qs-method-title" aria-describedby="wpsl-qs-note-method">
            <?php
            $qs_radio( 'address', __( 'Address or city', 'wp-store-locator' ), __( 'Stores near the place they type.', 'wp-store-locator' ), 'address' === $qs_answers['method'], false );
            $qs_radio( 'name', __( 'Store name', 'wp-store-locator' ), __( 'Stores whose name matches.', 'wp-store-locator' ), 'name' === $qs_answers['method'], false );
            ?>
        </div>
        <?php $qs_note( 'method', 'wpsl-qs-note-method' ); ?>
        <?php $qs_more_link( 'method' ); ?>
    </div>

    <?php $qs_disabled = $quick_setup->is_disabled( 'countries' ); ?>
    <div class="wpsl-qs-tile<?php echo $qs_disabled ? ' wpsl-qs-disabled' : ''; ?>" id="wpsl-qs-countries" data-question="countries">
        <h4 class="wpsl-qs-question" id="wpsl-qs-countries-title"><?php esc_html_e( 'Limit the search to countries?', 'wp-store-locator' ); ?></h4>
        <div class="wpsl-qs-answers" role="radiogroup" aria-labelledby="wpsl-qs-countries-title" aria-describedby="wpsl-qs-note-countries">
            <?php
            $qs_radio( 'anywhere', __( 'Anywhere', 'wp-store-locator' ), __( 'Visitors can search any address.', 'wp-store-locator' ), ! $qs_countries_on, $qs_disabled );
            $qs_radio( 'countries', __( 'Only in these countries', 'wp-store-locator' ), __( 'Searches outside them find nothing.', 'wp-store-locator' ), $qs_countries_on, $qs_disabled );
            ?>
        </div>
        <div class="wpsl-qs-country-box"<?php echo $qs_countries_on ? '' : ' hidden'; ?>>
            <?php
            /*
             * The multiselect of the Settings page, built by the same method.
             * The checkboxes have no name, the script saves the checked ones.
             */
            echo wpsl_get_service( 'admin_ui' )->get_multiselect_control( 'countries', [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The method escapes its output.
                'selected_values' => $qs_answers['countries'],
                'field_name'      => '',
                'button_id'       => 'wpsl-qs-countries-select-' . $qs_context,
                'disabled'        => $qs_disabled,
                'max_selected'    => \WPSL\Admin\Home\Quick_Setup::MAX_COUNTRIES,
            ] );
            ?>
            <p class="wpsl-qs-country-hint" hidden><?php esc_html_e( 'Pick at least one country.', 'wp-store-locator' ); ?></p>
        </div>
        <?php $qs_note( 'countries', 'wpsl-qs-note-countries' ); ?>
        <?php $qs_more_link( 'countries' ); ?>
    </div>

    <?php $qs_disabled = $quick_setup->is_disabled( 'locate' ); ?>
    <div class="wpsl-qs-tile<?php echo $qs_disabled ? ' wpsl-qs-disabled' : ''; ?>" id="wpsl-qs-locate" data-question="locate">
        <h4 class="wpsl-qs-question" id="wpsl-qs-locate-title"><?php esc_html_e( 'Find visitors automatically?', 'wp-store-locator' ); ?></h4>
        <div class="wpsl-qs-answers" role="radiogroup" aria-labelledby="wpsl-qs-locate-title" aria-describedby="wpsl-qs-note-locate">
            <?php
            $qs_radio( 'off', __( 'No', 'wp-store-locator' ), __( 'Visitors type where they are.', 'wp-store-locator' ), 'off' === $qs_answers['locate'], $qs_disabled );
            $qs_radio( 'pageload', __( 'As the page opens', 'wp-store-locator' ), __( 'Shows the stores near them right away.', 'wp-store-locator' ), 'pageload' === $qs_answers['locate'], $qs_disabled );
            $qs_radio( 'button', __( 'With a locate button', 'wp-store-locator' ), __( 'Only when they click it.', 'wp-store-locator' ), 'button' === $qs_answers['locate'], $qs_disabled );
            ?>
        </div>
        <?php $qs_note( 'locate', 'wpsl-qs-note-locate' ); ?>
        <?php $qs_more_link( 'locate' ); ?>
    </div>

    <div class="wpsl-qs-tile" id="wpsl-qs-pages" data-question="pages">
        <h4 class="wpsl-qs-question" id="wpsl-qs-pages-title"><?php esc_html_e( 'A page for every location?', 'wp-store-locator' ); ?></h4>
        <div class="wpsl-qs-answers" role="radiogroup" aria-labelledby="wpsl-qs-pages-title" aria-describedby="wpsl-qs-note-pages">
            <?php
            $qs_radio( '0', __( 'No', 'wp-store-locator' ), __( 'Locations only show in the store locator.', 'wp-store-locator' ), ! $qs_answers['pages'], false );
            $qs_radio( '1', __( 'Yes, with clean URLs', 'wp-store-locator' ), __( 'Each location gets its own page to share.', 'wp-store-locator' ), $qs_answers['pages'], false );
            ?>
        </div>
        <?php $qs_note( 'pages', 'wpsl-qs-note-pages', $qs_pages_link ); ?>
        <?php $qs_more_link( 'pages' ); ?>
    </div>

    <div class="wpsl-qs-tile" id="wpsl-qs-layout" data-question="layout">
        <h4 class="wpsl-qs-question" id="wpsl-qs-layout-title"><?php esc_html_e( 'Layout', 'wp-store-locator' ); ?></h4>
        <div class="wpsl-qs-answers wpsl-qs-layouts" role="radiogroup" aria-labelledby="wpsl-qs-layout-title" aria-describedby="wpsl-qs-layout-desc">
            <?php foreach ( $qs_templates as $qs_template ) { ?>
                <button type="button" class="wpsl-qs-answer wpsl-qs-layout" role="radio" aria-checked="<?php echo ( $qs_template['id'] === $qs_answers['layout'] ) ? 'true' : 'false'; ?>" data-value="<?php echo esc_attr( $qs_template['id'] ); ?>" data-desc="<?php echo esc_attr( html_entity_decode( $qs_template['desc'], ENT_QUOTES, 'UTF-8' ) ); ?>">
                    <span class="wpsl-qs-dot" aria-hidden="true"></span>
                    <span class="wpsl-qs-answer-text">
                        <span class="wpsl-qs-answer-label"><?php echo esc_html( html_entity_decode( $qs_template['name'], ENT_QUOTES, 'UTF-8' ) ); ?></span>
                    </span>
                    <?php if ( ! empty( $qs_template['placeholder'] ) ) { ?>
                        <img class="wpsl-qs-layout-thumb" src="<?php echo esc_url( $qs_template['placeholder'] ); ?>" alt="" width="64" loading="lazy">
                    <?php } ?>
                </button>
            <?php } ?>
        </div>
        <p class="wpsl-qs-layout-desc" id="wpsl-qs-layout-desc"><?php echo esc_html( $qs_layout_desc ); ?></p>
        <?php $qs_more_link( 'layout' ); ?>
    </div>

    <div class="wpsl-qs-tile" id="wpsl-qs-filters" data-question="filters">
        <h4 class="wpsl-qs-question" id="wpsl-qs-filters-title"><?php esc_html_e( 'Search filters', 'wp-store-locator' ); ?></h4>
        <div class="wpsl-qs-answers" role="group" aria-labelledby="wpsl-qs-filters-title">
            <?php foreach ( $qs_filters as $qs_filter => $qs_label ) {
                $qs_disabled = $quick_setup->is_disabled( 'filter_' . $qs_filter );
                $qs_on       = ! $qs_disabled && ! empty( $qs_answers['filters'][ $qs_filter ] );
                ?>
                <div class="wpsl-qs-filter">
                    <button type="button" class="wpsl-qs-answer" role="checkbox" aria-checked="<?php echo $qs_on ? 'true' : 'false'; ?>"<?php echo $qs_disabled ? ' aria-disabled="true"' : ''; ?> data-filter="<?php echo esc_attr( $qs_filter ); ?>" aria-describedby="wpsl-qs-note-filter-<?php echo esc_attr( $qs_filter ); ?>">
                        <span class="wpsl-qs-box" aria-hidden="true"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="M4 12.5l5 5L20 6.5"/></svg></span>
                        <span class="wpsl-qs-answer-text">
                            <span class="wpsl-qs-answer-label"><?php echo esc_html( $qs_label ); ?></span>
                        </span>
                    </button>
                    <?php $qs_note( 'filter_' . $qs_filter, 'wpsl-qs-note-filter-' . $qs_filter ); ?>
                </div>
            <?php } ?>
        </div>
        <?php $qs_more_link( 'filters' ); ?>
    </div>
</div>
