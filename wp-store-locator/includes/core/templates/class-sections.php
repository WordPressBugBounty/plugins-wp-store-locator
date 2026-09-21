<?php
/**
 * Template sections.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;

class Sections {

    /**
     * Plugin settings
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Translations service
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * Whether icons are enabled in search results
     *
     * @since 3.0.0
     * @var bool
     */
    private $icons_enabled;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager  $settings Settings manager instance
     * @param \WPSL\Core\I18n\Translations $i18n     Translations service instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n ) {
        $this->settings = $settings->get_all();
        $this->i18n = $i18n;
        $this->icons_enabled = (bool) $this->settings['appearance']['icons']['enabled'];
    }

    /**
     * Get the button class for a specific button type.
     * Returns empty string if CTA buttons are not enabled.
     * Returns the appropriate button style class if CTA buttons are enabled.
     *
     * @since  3.0.0
     * @param  string $button_type The button type (more_details, directions, zoom_here, streetview, share_location, no_thanks)
     * @param  string $base_class The base CSS class for the button (e.g., 'wpsl-details', 'wpsl-directions')
     * @return string The complete class string for the button
     */
    private function get_button_class( $button_type, $base_class ) {
        $cta_enabled = (bool) $this->settings['appearance']['cta']['enabled'];

        if ( ! $cta_enabled ) {
            return $base_class;
        }

        $button_style = $this->settings['appearance']['button_styles'][ $button_type ] ?? 'primary';

        return $base_class . ' wpsl-styled-btn wpsl-' . $button_style . '-btn';
    }

    /**
     * The CTA section container with the details / directions links.
     *
     * The duplication guards exist for the section analyzer: when a custom
     * section already renders one of the links, the merged snippet must
     * not add it a second time.
     *
     * @since  3.0.0
     * @param  bool $include_details    Include the "More details" link.
     * @param  bool $include_directions Include the directions link.
     * @return string
     */
    public function cta_section( $include_details = true, $include_directions = true ) {
        return "\t" . '<div class="wpsl-cta-section">' . "\r\n"
            . $this->cta_links( $include_details, $include_directions )
            . "\t" . '</div>' . "\r\n";
    }

    /**
     * The details / directions links that live inside the CTA section.
     *
     * Split out from cta_section() so the section analyzer can merge a
     * single missing link into a custom section that already has the
     * wrapper, instead of adding a second one around it.
     *
     * @since  3.0.0
     * @param  bool $include_details    Include the "More details" link.
     * @param  bool $include_directions Include the directions link.
     * @return string
     */
    public function cta_links( $include_details = true, $include_directions = true ) {
        $details_class = $this->get_button_class( 'more_details', 'wpsl-details' );

        $links = '';

        if ( $include_details ) {
            // Use external URL if available, otherwise fall back to permalink
            if ( $this->settings['local_pages']['permalinks'] ) {
                $links .= "\t\t" . '<a class="' . esc_attr( $details_class ) . '" target="_blank" rel="noopener" href="<% if ( url ) { %><%= url %><% } else { %><%= permalink %><% } %>">' . "{{wpsl_label( 'more_details_label' )}}" . '</a>' . "\r\n";
            } else {
                $links .= "\t\t" . '<% if ( url ) { %><a class="' . esc_attr( $details_class ) . '" target="_blank" rel="noopener" href="<%= url %>">' . "{{wpsl_label( 'more_details_label' )}}" . '</a><% } %>' . "\r\n";
            }
        }

        if ( $include_directions ) {
            $links .= "\t\t" . '<%= createDirectionUrl() %>' . "\r\n";
        }

        return $links;
    }

    /**
     * Return the code for the requested section.
     *
     * This will be de default code, or the customized
     * section code if a db entry exists.
     *
     * @since  3.0.0
     * @param  array $args
     * @return array $template_section
     */
    public function get( $args = [] ) {
        // v2 filter callbacks may run when the section filters are applied,
        // and those routinely read $wpsl->i18n / $wpsl_settings.
        wpsl_maybe_set_v2_global();

        $template_section = [];

        /**
         * If no template name is provided, then we default to
         * the name of selected template on the WPSL settings page.
         */
        if ( ! isset( $args['template'] ) || $args['template'] === '' ) {
            $args = array_merge( [ 'template' => $this->settings['appearance']['template_id'] ], $args );
        }

        $name = $this->create_name( $args );

        if ( ! isset( $args['default'] ) ) {
            $args['default'] = ( $this->check_custom_status( $name ) ) ? false : true;
        }

        // If no section is provided, then we default to 'listing'.
        if ( ! isset( $args['section'] ) || $args['section'] === '' ) {
            $args = array_merge( $args, [ 'section' => 'listing' ] );
        }

        if ( $args['section'] ) {
            /**
             * If the 'default' key is set, then we
             * will not check the db for any entries,
             * and just return the default section code.
             */
            if ( $args['default'] ) {
                $template_section['html'] = $this->load_default( $args );
            } else {
                $content = $this->get_custom( $args );
                if ( ! $content ) {
                    $template_section['html'] = $this->load_default( $args );
                } else {
                    $template_section['html'] = stripslashes( $content );
                }
            }
        }

        return $template_section;
    }

    /**
     * Return the default section code.
     *
     * @since  3.0.0
     * @param  array        $args           The section arguments
     * @param  array|string $shortcode_atts The shortcode attributes
     * @return string|null  The template code, or null when the section has no default
     */
    public function load_default( $args, $shortcode_atts = '' ) {
        if ( method_exists( $this, $args['section'] ) ) {
            $result = call_user_func( [ $this, $args['section'] ], $args, $shortcode_atts );

            return $result;
        }
    }

    /**
     * See if we have a customized
     * section in the database.
     *
     * @since  3.0.0
     * @param  array  $args
     * @return string|null $content
     */
    public function get_custom( $args ) {
        $row = $this->get_custom_row( $args );

        return $row ? $row['content'] : null;
    }

    /**
     * Return the customized section row that best matches the requested
     * ( or current ) language.
     *
     * The language rows are written by the section editor keyed on the
     * multilingual plugin's locale ( 'de-DE' / 'de_DE' ), while the
     * front-end language detection returns slugs ( 'de' ), so the match
     * happens in PHP through select_custom_row() instead of in SQL.
     *
     * @since  3.0.0
     * @param  array $args           The section args ( template, section, optionally lang ).
     * @param  bool  $exact_language Only accept a row for the requested language ( used by save ).
     * @return array|null The matching row ( id, language, content ), or null.
     */
    public function get_custom_row( $args, $exact_language = false ) {
        global $wpdb;

        if ( isset( $args['lang'] ) ) {
            $lang = $args['lang'];
        } else {
            // The front-end never passes a language, so use the current one.
            $lang = $this->i18n ? (string) $this->i18n->check_multilingual_code() : '';
        }

        $sql = "SELECT id, language, content FROM {$wpdb->prefix}wpsl_themes WHERE template = %s AND section = %s ORDER BY id ASC";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable, SQL is prepared with $wpdb->prepare() with placeholders, table name uses safe $wpdb->prefix
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, [ $args['template'], $args['section'] ] ), ARRAY_A );

        if ( ! $rows ) {
            return null;
        }

        return $this->select_custom_row( $rows, $lang, $exact_language );
    }

    /**
     * Every customized section row for a template + section, one per
     * language, ordered by id.
     *
     * @since 3.0.0
     * @param  array $args The section args ( template, section ).
     * @return array The rows ( id, language, content ), empty when the section isn't customized.
     */
    public function get_custom_rows( $args ) {
        global $wpdb;

        $sql = "SELECT id, language, content FROM {$wpdb->prefix}wpsl_themes WHERE template = %s AND section = %s ORDER BY id ASC";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table query, caching not applicable, SQL is prepared with $wpdb->prepare() with placeholders, table name uses safe $wpdb->prefix
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, [ $args['template'], $args['section'] ] ), ARRAY_A );

        return $rows ? $rows : [];
    }

    /**
     * Pick the row that best matches the requested language.
     *
     * Match order: exact language, then same language slug ( bridges the
     * 'de' / 'de-DE' / 'de_DE' formats ), then — unless an exact match is
     * required — the language-neutral row, and finally the first row, so an
     * activated custom section always renders one of its saved templates.
     *
     * @since  3.0.0
     * @param  array  $rows           The wpsl_themes rows, ordered by id.
     * @param  string $lang           The requested language, or '' when unknown.
     * @param  bool   $exact_language Skip the neutral / first row fallbacks.
     * @return array|null The selected row, or null.
     */
    public function select_custom_row( array $rows, $lang = '', $exact_language = false ) {
        $lang = (string) $lang;
        $slug = self::normalize_lang( $lang );

        foreach ( $rows as $row ) {
            if ( $row['language'] === $lang ) {
                return $row;
            }
        }

        if ( $slug !== '' ) {
            foreach ( $rows as $row ) {
                if ( self::normalize_lang( $row['language'] ) === $slug ) {
                    return $row;
                }
            }
        }

        if ( $exact_language ) {
            return null;
        }

        if ( $lang !== '' ) {
            foreach ( $rows as $row ) {
                if ( $row['language'] === '' ) {
                    return $row;
                }
            }
        }

        return $rows[0];
    }

    /**
     * Reduce a language code to its lowercase slug.
     *
     * 'de-DE', 'de_DE' and 'DE' all become 'de', which is the only part
     * the different multilingual plugins ( and the editor dropdown vs the
     * front-end detection ) agree on.
     *
     * @since  3.0.0
     * @param  string|null $lang The language code.
     * @return string The language slug, or '' if none.
     */
    public static function normalize_lang( $lang ) {
        $lang = strtolower( trim( (string) $lang ) );

        if ( $lang === '' ) {
            return '';
        }

        $parts = preg_split( '/[-_]/', $lang );

        return $parts[0];
    }

    /**
     * Check if we need to replace the values between {{}}
     * with values returned by the function name ( required for the front-end ),
     * or just keep it as it is ( section editor in the admin area ).
     *
     * @since   3.0.0
     * @param   string $template The template code
     * @param   array  $args     The section arguments
     * @return  string $template
     */
    public function maybe_call_func( $template, $args ) {
        // Extract the functions from the template code
        preg_match_all( '/{{(.*?)}}/', $template, $matches );

        $func_placeholders = $matches[0];
        $func_to_call = $matches[1];

        /**
         * The functions the template tags are allowed to call.
         *
         * Template code can be saved by the wpsl_store_locator_manager role,
         * so only allowlisted functions may run. Developers can extend the
         * list through the filter.
         */
        $allowed_functions = apply_filters( 'wpsl_template_functions', [
            'wpsl_get_distance_unit',
            'wpsl_label',
        ] );

        /**
         * Loop over the different functions and replace
         * the {{placeholder}} with the returned values.
         */
        foreach ( $func_to_call as $index => $function ) {
            $template_function = trim( preg_replace( '/\(.*?\)/', '', $function ) );
            $response = '';

            if ( in_array( $template_function, $allowed_functions, true ) && function_exists( $template_function ) ) {
                /**
                 * Only a single quoted string literal may be forwarded as an
                 * argument, e.g. {{wpsl_label( 'phone_label' )}}. Anything
                 * else between the parentheses is discarded and the function
                 * receives just the section args.
                 */
                if ( preg_match( '/\(\s*([\'"])([\w-]+)\1\s*\)/', $function, $literal ) ) {
                    $response = call_user_func( $template_function, $literal[2], $args );
                } else {
                    $response = call_user_func( $template_function, $args );
                }
            }

            $template = str_replace( $func_placeholders[ $index ], $response, $template );
        }

        return $template;
    }

    /**
     * Check the active custom sections.
     *
     * This determines if we need to load the default
     * or customized section from the db.
     *
     * @since  3.0.0
     * @param  string $section_name The section name to check.
     * @return bool
     */
    public function check_custom_status( $section_name ) {
        return in_array( $section_name, $this->settings['appearance']['active_custom_sections'] );
    }

    /**
     * Create the section name.
     *
     * @since  3.0.0
     * @param  array  $args The section args.
     * @return string The section name.
     */
    public function create_name( $args ) {
        $section_name = '';

        if ( isset( $args['template'] ) && isset( $args['section'] ) ) {
            $section_name = $args['template'] . '_' . $args['section'];
        }

        return $section_name;
    }

    /**
     * Get the address details.
     *
     * @since  3.0.0
     * @return string $address
     */
    public function get_address() {
        $address = "\t" . '<div class="wpsl-store-location">' . "\r\n";
        $address .= "\t\t" . '<p>' . "\r\n";
        $address .= "\t\t\t" . $this->store_header( 'listing' ) . "\r\n"; // Check which header format we use
        $address .= "\t\t\t" . '<span class="wpsl-street"><%= address %></span>' . "\r\n";
        $address .= "\t\t\t" . '<% if ( address2 ) { %>' . "\r\n";
        $address .= "\t\t\t" . '<span class="wpsl-street"><%= address2 %></span>' . "\r\n";
        $address .= "\t\t\t" . '<% } %>' . "\r\n";
        $address .= "\t\t\t" . '<span>' . $this->format_address() . '</span>' . "\r\n"; // Use the correct address format

        if ( ! $this->settings['ux']['hide_country'] ) {
            $address .= "\t\t\t" . '<span class="wpsl-country"><%= country %></span>' . "\r\n";
        }

        $address .= "\t\t" . '</p>' . "\r\n";
        $address .= "\t" . '</div>' . "\r\n";

        return $address;
    }

    /**
     * Generate the address content template (store header and address fields).
     *
     * @since  3.0.0
     * @param  string $context Context for the template ('listing' or 'popup')
     * @return string Address content template
     */
    private function get_address_content_template( $context = 'listing' ) {
        $indent_level = ( $this->icons_enabled ) ? 3 : 2;
        $indent = str_repeat( "\t", $indent_level );
        $template = '';

        // Determine which store header to use
        $store_header = ( $context === 'listing' ) ? $this->store_header( 'listing' ) : $this->store_header();

        $template .= $indent . $store_header . "\r\n";
        $template .= $indent . '<span class="wpsl-street"><%= address %></span>' . "\r\n";
        $template .= $indent . '<% if ( address2 ) { %>' . "\r\n";
        $template .= $indent . '<span class="wpsl-street"><%= address2 %></span>' . "\r\n";
        $template .= $indent . '<% } %>' . "\r\n";
        $template .= $indent . '<span>' . $this->format_address() . '</span>' . "\r\n";

        // Only include country in listing context
        if ( $context === 'listing' && ! $this->settings['ux']['hide_country'] ) {
            $template .= $indent . '<span class="wpsl-country"><%= country %></span>' . "\r\n";
        }

        return $template;
    }

    /**
     * The featured image placeholder used by the listing templates.
     *
     * Guarded with a typeof check because the store data only carries a
     * 'thumb' key on the search-result paths.
     *
     * @since  3.0.0
     * @return string The underscore placeholder
     */
    private function thumb_placeholder() {
        return '<%= typeof thumb !== "undefined" ? thumb : "" %>';
    }

    /**
     * Generate the complete address paragraph template.
     * Used by both listing and info_window templates.
     *
     * @since  3.0.0
     * @param  string $context Context for the template ('listing' or 'popup')
     * @return string Complete address paragraph template
     */
    private function get_address_paragraph_template( $context = 'listing' ) {
        $base_indent = ( $context = 'listing' ) ? 2 : 1;

        $indent = str_repeat( "\t", $base_indent );
        $template = '';

        // Thumbnail placeholder only for listing template
        $thumb = ( $context === 'listing' ) ? $this->thumb_placeholder() : '';

        // Add icon classes to address paragraph if icons are enabled
        if ( $this->icons_enabled ) {
            $address_icon = isset( $this->settings['appearance']['icons']['address'] ) ? $this->settings['appearance']['icons']['address'] : 'marker';
            $template .= $indent . '<p class="wpsl-icon-address wpsl-icon-address-' . esc_attr( $address_icon ) . '">' . $thumb . "\r\n";
            $template .= $indent . "\t" . '<span class="wpsl-location-content">' . "\r\n";
            $template .= $this->get_address_content_template( $context );
            $template .= $indent . "\t" . '</span>' . "\r\n";
            $template .= $indent . '</p>' . "\r\n";
        } else {
            $template .= $indent . '<p>' . $thumb . "\r\n";
            $template .= $this->get_address_content_template( $context );
            $template .= $indent . '</p>' . "\r\n";
        }

        return $template;
    }

    /**
     * Whether the active search is an origin-less "name" search.
     *
     * Name search never geocodes, so it has neither 
     * a distance nor a directions route.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_name_search() {
        return ( $this->settings['search']['search_method'] === 'name' || $this->settings['appearance']['template_id'] === 'name_search' );
    }

    /**
     * Code for the li element section
     * used in the search results.
     *
     * @param   array  $args
     * @return  string $template
     */
    public function listing( $args ) {
        $listing_template = '<li data-store-id="<%= id %>">' . "\r\n";
        $listing_template .= "\t" . '<div class="wpsl-store-location">' . "\r\n";
        $listing_template .= $this->get_address_paragraph_template( 'listing' );

        // Maybe include the contact details.
        if ( in_array( 'search_results', $this->settings['ux']['contact_details'] ) ) {
            $listing_template .= $this->contact_details();
        }

        // Maybe include the hours data.
        if ( in_array( 'search_results', $this->settings['ux']['hours'] ) ) {
            $listing_template .= "\t\t" . '<% if ( typeof hours !== "undefined" && hours ) { %>' . "\r\n";

            // Add icon classes to hours if icons are enabled
            if ( $this->icons_enabled ) {
                $listing_template .= "\t\t" . '<div class="wpsl-icon-hours"><%= hours %></div>' . "\r\n";
            } else {
                $listing_template .= "\t\t" . '<%= hours %>' . "\r\n";
            }

            $listing_template .= "\t\t" . '<% } %>' . "\r\n";
        }

        $status_class = ( $this->icons_enabled ) ? 'wpsl-location-status wpsl-icon-attention' : 'wpsl-location-status';

        $listing_template .= "\t\t" . '<% if ( typeof location_status !== "undefined" && location_status ) { %>' . "\r\n";
        $listing_template .= "\t\t" . '<p class="' . esc_attr( $status_class ) . '"><%= location_status %></p>' . "\r\n";
        $listing_template .= "\t\t" . '<% } %>' . "\r\n";

        // Maybe include the description data.
        if ( in_array( 'search_results', $this->settings['ux']['description'] ) ) {
            $listing_template .= "\t\t" . '<% if ( typeof description !== "undefined" && description ) { %>' . "\r\n";
            $listing_template .= "\t\t" . '<p><%= description %></p>' . "\r\n";
            $listing_template .= "\t\t" . '<% } %>' . "\r\n";
        }

        if ( $this->has_more_info_enabled() ) {
            $listing_template .= "\t\t" . $this->more_info_template() . "\r\n"; // Check if we need to show the 'More Info' link and info
        }

        $listing_template .= "\t" . '</div>' . "\r\n";

        $cta_enabled = isset( $this->settings['appearance']['cta']['enabled'] ) ? $this->settings['appearance']['cta']['enabled'] : false;
        $cta_details_enabled = isset( $this->settings['appearance']['cta']['details'] ) ? $this->settings['appearance']['cta']['details'] : false;

        // Render the separate CTA section when the action links are styled as buttons
        // or the "More details" link is enabled. Either one needs the links grouped
        // together in their own container.
        $has_cta_section = $cta_enabled || $cta_details_enabled;

        // A name search has neither a distance nor a directions route, which would
        // leave an empty .wpsl-direction-wrap on every result, so skip the whole
        // wrapper for it.
        $is_name_search = $this->is_name_search();

        if ( ! $is_name_search ) {
            $listing_template .= "\t" . '<div class="wpsl-direction-wrap">' . "\r\n";

            if ( ! $this->settings['ux']['hide_distance'] ) {
                $listing_template .= "\t\t" . '<% if ( typeof distance !== "undefined" ) { %>' . "\r\n";

                // Add icon class to distance span if icons are enabled
                $distance_class = ( $this->icons_enabled ) ? 'wpsl-distance wpsl-icon-road' : 'wpsl-distance';
                $listing_template .= "\t\t" . '<span class="' . esc_attr( $distance_class ) . '"><%= distance %> <% if ( typeof distance_unit !== "undefined" ) { %><%= distance_unit %><% } else { %>{{wpsl_get_distance_unit( $args )}}<% } %></span>' . "\r\n";

                $listing_template .= "\t\t" . '<% } %>' . "\r\n";
            }

            // Keep the directions link inline when there's no separate CTA section.
            if ( ! $has_cta_section ) {
                $listing_template .= "\t\t" . '<%= createDirectionUrl() %>' . "\r\n";
            }

            $listing_template .= "\t" . '</div>' . "\r\n";
        }

        // Add the CTA section container when styling links as buttons or showing the details link.
        // Name search has no route, so the directions link is skipped there ( it would render
        // empty anyway ). Without the details link that leaves nothing to put in the wrapper,
        // so it's skipped too.
        if ( $has_cta_section && ( $cta_details_enabled || ! $is_name_search ) ) {
            $listing_template .= $this->cta_section( $cta_details_enabled, ! $is_name_search );
        }

        $listing_template .= '</li>';

        return apply_filters( 'wpsl_listing_template', $listing_template );
    }

    /**
     * The template for the info window
     * on the store locator page.
     *
     * @since  3.0.0
     * @return string $info_window_template
     */
    public function info_window() {
        $info_window_template = '<div data-store-id="<%= id %>" class="wpsl-info-window">' . "\r\n";
        $info_window_template .= $this->get_address_paragraph_template( 'popup' );

        // Maybe include the contact details.
        if ( in_array( 'marker_popup', $this->settings['ux']['contact_details'] ) ) {
            $info_window_template .= $this->contact_details();
        }

        // Maybe include the hours status in the popup (short version, no expandable hours table).
        // Skip if location_status is set (temporarily/permanently closed), since that takes priority.
        if ( in_array( 'marker_popup', $this->settings['ux']['hours'] ) && $this->settings['ux']['show_hour_status'] ) {
            $info_window_template .= "\t" . '<% if ( typeof hours_status !== "undefined" && hours_status && ( typeof location_status === "undefined" || !location_status ) ) { %>' . "\r\n";

            if ( $this->icons_enabled ) {
                $info_window_template .= "\t" . '<div class="wpsl-icon-hours"><%= hours_status %></div>' . "\r\n";
            } else {
                $info_window_template .= "\t" . '<%= hours_status %>' . "\r\n";
            }

            $info_window_template .= "\t" . '<% } %>' . "\r\n";
        }

        // Maybe include the description data.
        if ( in_array( 'marker_popup', $this->settings['ux']['description'] ) ) {
            $info_window_template .= "\t" . '<% if ( typeof description !== "undefined" && description ) { %>' . "\r\n";
            $info_window_template .= "\t" . '<p><%= description %></p>' . "\r\n";
            $info_window_template .= "\t" . '<% } %>' . "\r\n";
        }

        $status_class = ( $this->icons_enabled ) ? 'wpsl-location-status wpsl-icon-attention' : 'wpsl-location-status';

        $info_window_template .= "\t" . '<% if ( typeof location_status !== "undefined" && location_status ) { %>' . "\r\n";
        $info_window_template .= "\t" . '<p class="' . esc_attr( $status_class ) . '"><%= location_status %></p>' . "\r\n";
        $info_window_template .= "\t" . '<% } %>' . "\r\n";

        $info_window_template .= "\t" . '<%= createInfoWindowActions( id, url, typeof permalink !== "undefined" ? permalink : "" ) %>' . "\r\n";

        $info_window_template .= '</div>';

        return apply_filters( 'wpsl_info_window_template', $info_window_template );
    }

    /**
     * The template for the info window
     * on the single wpsl store pages.
     *
     * @since  3.0.0
     * @return string $cpt_info_window_template
     */
    public function cpt_info_window() {
        $cpt_info_window_template = '<div class="wpsl-info-window">' . "\r\n";
        $cpt_info_window_template .= "\t" . '<p class="wpsl-no-margin">' . "\r\n";
        $cpt_info_window_template .= "\t\t" . $this->store_header( 'wpsl_map' ) . "\r\n";
        $cpt_info_window_template .= "\t\t" . '<span><%= address %></span>' . "\r\n";
        $cpt_info_window_template .= "\t\t" . '<% if ( address2 ) { %>' . "\r\n";
        $cpt_info_window_template .= "\t\t" . '<span><%= address2 %></span>' . "\r\n";
        $cpt_info_window_template .= "\t\t" . '<% } %>' . "\r\n";
        $cpt_info_window_template .= "\t\t" . '<span>' . $this->format_address() . '</span>' . "\r\n"; // Use the correct address format

        if ( ! $this->settings['ux']['hide_country'] ) {
            $cpt_info_window_template .= "\t\t" . '<span class="wpsl-country"><%= country %></span>' . "\r\n";
        }

        $cpt_info_window_template .= "\t" . '</p>' . "\r\n";

        // Maybe include the contact details.
        if ( in_array( 'landing_page_marker_popup', $this->settings['ux']['contact_details'] ) ) {
            $cpt_info_window_template .= $this->contact_details();
        }

        // Maybe include the hours status (short open / closed status).
        if ( in_array( 'landing_page_marker_popup', $this->settings['ux']['hours'] ) && $this->settings['ux']['show_hour_status'] ) {
            $cpt_info_window_template .= "\t" . '<% if ( typeof hours_status !== "undefined" && hours_status ) { %>' . "\r\n";

            if ( $this->icons_enabled ) {
                $cpt_info_window_template .= "\t" . '<div class="wpsl-icon-hours"><%= hours_status %></div>' . "\r\n";
            } else {
                $cpt_info_window_template .= "\t" . '<%= hours_status %>' . "\r\n";
            }

            $cpt_info_window_template .= "\t" . '<% } %>' . "\r\n";
        }

        // Maybe include the description data.
        if ( in_array( 'landing_page_marker_popup', $this->settings['ux']['description'] ) ) {
            $cpt_info_window_template .= "\t" . '<% if ( typeof description !== "undefined" && description ) { %>' . "\r\n";
            $cpt_info_window_template .= "\t" . '<p><%= description %></p>' . "\r\n";
            $cpt_info_window_template .= "\t" . '<% } %>' . "\r\n";
        }

        $cpt_info_window_template .= '</div>';

        return apply_filters( 'wpsl_cpt_info_window_template', $cpt_info_window_template );
    }

    /**
     * Template used to show the number
     * of returned search results.
     *
     * @since  3.0.0
     * @return string $number_results_template
     */
    public function number_results() {
        $number_results_template = '';

        if ( $this->settings['search']['number_results'] ) {
            $number_results_template = '<div class="wpsl-number-results"><strong><%= number_results %></strong></div>';
        }

        return apply_filters( 'wpsl_number_results_template', $number_results_template );
    }

    /**
     * Online only section
     *
     * @since  3.0.0
     * @return string The online-only listing template
     */
    public function online() {
        $online_template = '<li data-store-id="<%= id %>">' . "\r\n";
        $online_template .= "\t\t" . '<div class="wpsl-store-location">' . "\r\n";

        /*
         * The regular listing carries the thumbnail inside the address
         * paragraph, which an online store doesn't have. So it rides with the
         * store name instead, the one thing every online store has.
         */
        $online_template .= "\t\t\t" . '<p>' . $this->thumb_placeholder() . '<strong><%= store %></strong></p>' . "\r\n";
        $online_template .= "\t\t\t" . '<% if ( typeof description !== "undefined" && description ) { %>' . "\r\n";
        $online_template .= "\t\t\t" . '<p><%= description %></p>' . "\r\n";
        $online_template .= "\t\t\t" . '<% } %>' . "\r\n";
        $online_template .= "\t\t\t" . $this->contact_details( true );
        $online_template .= "\t" . '</div>' . "\r\n";
        $online_template .= '</li>' . "\r\n";

        return $online_template;
    }

    /**
     * Contact details template
     *
     * @since  3.0.0
     * @param  bool   $include_url
     * @return string
     */
    public function contact_details( $include_url = false ) {
        $contact_template = "\t\t" . '<p class="wpsl-contact-details">' . "\r\n";

        if ( $include_url ) {
            $contact_template .= "\t\t\t\t" . '<% if ( typeof url !== "undefined" && url ) { %>' . "\r\n";
            $contact_template .= "\t\t\t\t" . '<span><strong>' . "{{wpsl_label( 'url_label' )}}" . '</strong>: <a' . $this->new_window() . ' href="<%= url %>"><%= url %></a></span>' . "\r\n";
            $contact_template .= "\t\t\t\t" . '<% } %>' . "\r\n";
        }

        $contact_template .= "\t\t\t\t" . '<% if ( typeof phone !== "undefined" && phone ) { %>' . "\r\n";

        // Add icon classes to phone span if icons are enabled
        if ( $this->icons_enabled ) {
            $phone_icon = isset( $this->settings['appearance']['icons']['phone'] ) ? $this->settings['appearance']['icons']['phone'] : 'phone';
            $contact_template .= "\t\t\t\t" . '<span class="wpsl-icon-' . esc_attr( $phone_icon ) . '"><%= formatPhoneNumber( phone ) %></span>' . "\r\n";
        } else {
            $contact_template .= "\t\t\t\t" . '<span><strong>' . "{{wpsl_label( 'phone_label' )}}" . '</strong>: <%= formatPhoneNumber( phone ) %></span>' . "\r\n";
        }

        $contact_template .= "\t\t\t\t" . '<% } %>' . "\r\n";
        $contact_template .= "\t\t\t\t" . '<% if ( typeof fax !== "undefined" && fax ) { %>' . "\r\n";

        if ( $this->icons_enabled ) {
            $contact_template .= "\t\t\t\t" . '<span class="wpsl-icon-fax"><%= formatPhoneNumber( fax ) %></span>' . "\r\n";
        } else {
            $contact_template .= "\t\t\t\t" . '<span><strong>' . "{{wpsl_label( 'fax_label' )}}" . '</strong>: <%= formatPhoneNumber( fax ) %></span>' . "\r\n";
        }

        $contact_template .= "\t\t\t\t" . '<% } %>' . "\r\n";
        
        $contact_template .= "\t\t\t\t" . '<% if ( typeof email !== "undefined" && email ) { %>' . "\r\n";

        // Add icon classes to email span if icons are enabled
        if ( $this->icons_enabled ) {
            $email_icon = isset( $this->settings['appearance']['icons']['email'] ) ? $this->settings['appearance']['icons']['email'] : 'email';
            $contact_template .= "\t\t\t\t" . '<span class="wpsl-icon-' . esc_attr( $email_icon ) . '"><%= formatEmail( email ) %></span>' . "\r\n";
        } else {
            $contact_template .= "\t\t\t\t" . '<span><strong>' . "{{wpsl_label( 'email_label' )}}" . '</strong>: <%= formatEmail( email ) %></span>' . "\r\n";
        }

        $contact_template .= "\t\t\t\t" . '<% } %>' . "\r\n";
        $contact_template .= "\t\t\t" . '</p>' . "\r\n";

        return $contact_template;
    }

    /**
     * More info template
     *
     * @since  3.0.0
     * @return string
     */
    public function more_info_template() {
        $more_info_template = '<% if ( hasMoreInfoData ) { %>' . "\r\n";
        $more_info_template .= "<p class='wpsl-more-info'><a class=\"wpsl-store-details wpsl-store-listing\" aria-expanded=\"false\" href=\"#wpsl-id-<%= id %>\">" . "{{wpsl_label( 'more_label' )}}" . '</a></p>' . "\r\n";
        $more_info_template .= "\t\t" . '<div id="wpsl-id-<%= id %>" class="wpsl-more-info-listings">' . "\r\n";

        if ( in_array( 'more_info', $this->settings['ux']['contact_details'] ) ) {
            $more_info_template .= "\t" . $this->contact_details();
        }

        if ( in_array( 'more_info', $this->settings['ux']['hours'] ) ) {
            $more_info_template .= "\t\t\t" . '<% if ( typeof hours !== "undefined" && hours ) { %>' . "\r\n";

            if ( $this->icons_enabled ) {
                $more_info_template .= "\t\t\t" . '<div class="wpsl-store-hours wpsl-icon-hours"><%= hours %></div>' . "\r\n";
            } else {
                $more_info_template .= "\t\t\t" . '<div class="wpsl-store-hours"><strong>' . "{{wpsl_label( 'hours_label' )}}" . '</strong><%= hours %></div>' . "\r\n";
            }

            $more_info_template .= "\t\t\t" . '<% } %>' . "\r\n";
        }

        if ( in_array( 'more_info', $this->settings['ux']['description'] ) ) {
            $more_info_template .= "\t\t\t" . '<% if ( typeof description !== "undefined" && description ) { %>' . "\r\n";
            $more_info_template .= "\t\t\t" . '<%= description %>' . "\r\n";
            $more_info_template .= "\t\t\t" . '<% } %>' . "\r\n";
        }

        $more_info_template .= "\t\t" . '</div>' . "\r\n";
        $more_info_template .= '<% } %>' . "\r\n";

        return apply_filters( 'wpsl_more_info_template', $more_info_template );
    }

    /**
     * Create the address placeholders based on the
     * structure defined on the settings page.
     *
     * @since  2.0.0
     * @return string $address_placeholders A list of address placeholders in the correct order
     */
    public function format_address() {
        $address_format = explode( '_', $this->settings['ux']['address_format'] );
        $placeholders = '';
        $part_count = count( $address_format ) - 1;
        $i = 0;

        foreach ( $address_format as $address_part ) {
            if ( $address_part != 'comma' ) {

                /*
                 * Don't add a space after the placeholder if the next part
                 * is going to be a comma or if it is the last part.
                 */
                if ( $i == $part_count || $address_format[ $i + 1 ] == 'comma' ) {
                    $space = '';
                } else {
                    $space = ' ';
                }

                $placeholders .= '<%= ' . $address_part . ' %>' . $space;
            } else {
                $placeholders .= ', ';
            }

            $i++;
        }

        return $placeholders;
    }

    /**
     * The store header markup: a linked store name when permalinks or
     * an external URL is available, otherwise a plain <strong> name.
     * Indentation is adjusted per location for readable HTML output.
     *
     * @since  3.0.0
     * @param  string $location Where the header is shown ('listing', 'info_window', 'wpsl_map').
     * @return string The header template markup.
     */
    public function store_header( $location = 'info_window' ) {
        $new_window = $this->new_window();

        if ( $location == 'listing' ) {
            $tab = "\t\t\t\t";
        } else {
            $tab = "\t\t\t";
        }

        if ( $this->settings['local_pages']['permalinks'] ) {
            /**
             * It's possible the permalinks are enabled, but not included in the location data on
             * pages where the [wpsl_map] shortcode is used.
             *
             * So we need to check for undefined, which isn't necessary in all other cases.
             */
            if ( $location == 'wpsl_map' ) {
                // The renderer fills an absent permalink in as '', so the
                // test has to be for a value, not only for the key.
                $header_template = '<% if ( typeof permalink !== "undefined" && permalink ) { %>' . "\r\n";
                $header_template .= $tab . '<strong class="wpsl-location-name"><a tabindex="0"' . $new_window . ' href="<%= permalink %>"><%= store %></a></strong>' . "\r\n";
                $header_template .= $tab . '<% } else { %>' . "\r\n";
                $header_template .= $tab . '<strong><%= store %></strong>' . "\r\n";
                $header_template .= $tab . '<% } %>';
            } else {
                $header_template = '<strong class="wpsl-location-name"><a tabindex="0"' . $new_window . ' href="<%= permalink %>"><%= store %></a></strong>';
            }
        } else {
            $header_template = '<% if ( wpslSettings.storeUrl == 1 && url ) { %>' . "\r\n";
            $header_template .= $tab . '<strong class="wpsl-location-name"><a tabindex="0"' . $new_window . ' href="<%= url %>"><%= store %></a></strong>' . "\r\n";
            $header_template .= $tab . '<% } else { %>' . "\r\n";
            $header_template .= $tab . '<strong><%= store %></strong>' . "\r\n";
            $header_template .= $tab . '<% } %>';
        }

        return apply_filters( 'wpsl_store_header_template', $header_template, $location );
    }

    /**
     * Do links need to open in a new window?
     *
     * @since  3.0.0
     * @return string
     */
    public function new_window() {
        return $this->settings['ux']['new_window'] ? ' target="_blank" rel="noopener"' : '';
    }

    /**
     * Check if more info is enabled.
     *
     * @since  3.0.0
     * @return bool
     */
    private function has_more_info_enabled() {
        return in_array( 'more_info', $this->settings['ux']['contact_details'] ) ||
            in_array( 'more_info', $this->settings['ux']['hours'] ) ||
            in_array( 'more_info', $this->settings['ux']['description'] );
    }
}