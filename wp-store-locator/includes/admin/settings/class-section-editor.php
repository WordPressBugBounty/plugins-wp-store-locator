<?php
/**
 * Template section actions.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Templates\Sections;
use WPSL\Core\Templates\Template_Code;
use WPSL\Core\Settings\Manager as WpslSettings;

class Section_Editor {

    /**
     * The name of the sql table that
     * holds the template sections code.
     *
     * @since 3.0.0
     */
    private $template_table;

    /**
     * The template sections instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Sections
     */
    private $template_sections;

    /**
     * The settings instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * List of tags that are allowed in the section editor.
     *
     * Leave out the ones here https://validator.w3.org/feed/docs/warning/SecurityRisk.html
     * and include most tags from this list https://www.w3schools.com/TAGs/
     *
     * @since 3.0.0
     */
    private $allowed_tags = [
        'a',
        'abbr',
        'address',
        'b',
        'bdi',
        'bdo',
        'blockquote',
        'button',
        'caption',
        'cite',
        'col',
        'colgroup',
        'data',
        'datalist',
        'dd',
        'del',
        'details',
        'dfn',
        'div',
        'dl',
        'dt',
        'em',
        'fieldset',
        'figcaption',
        'figure',
        'form',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hgroup',
        'hr',
        'i',
        'img',
        'input',
        'ins',
        'kbd',
        'label',
        'legend',
        'li',
        'ol',
        'optgroup',
        'option',
        'p',
        'picture',
        'pre',
        'q',
        'select',
        'small',
        'span',
        'strong',
        'sub',
        'summary',
        'sup',
        'table',
        'tbody',
        'td',
        'textarea',
        'tfoot',
        'th',
        'thead',
        'time',
        'tr',
        'u',
        'ul',
        'video',
        'wbr'
    ];

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Templates\Sections $sections The template sections instance
     * @param \WPSL\Core\Settings\Manager   $settings The settings manager instance
     */
    public function __construct( Sections $sections, WpslSettings $settings ) {
        global $wpdb;

        $this->template_table    = $wpdb->prefix . 'wpsl_themes';
        $this->template_sections = $sections;
        $this->settings          = $settings;

        add_action( 'wp_ajax_wpsl_section_editor', [ $this, 'actions' ] );
    }

    /**
     * Handle the different section editor actions
     *
     * Load / save / restore the section code.
     *
     * @return void|null
     */
    public function actions() {
        if ( ! isset( $_REQUEST['template_action'] ) ) {
            return;
        }

        $data = stripslashes_deep( $_REQUEST );
        $action = isset( $data['template_action'] ) ? sanitize_key( $data['template_action'] ) : '';

        if ( ! isset( $data['wpsl_section_editor_nonce'] ) || ! wp_verify_nonce( $data['wpsl_section_editor_nonce'], 'wpsl-' . $action . '_section' ) ) {
            return wp_send_json_error( __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! self::current_user_can_edit() ) {
            return wp_send_json_error( __( 'You do not have permission to perform this action.', 'wp-store-locator' ) );
        }

        $args = [
            'template' => sanitize_text_field( $data['template'] ),
            'section'  => sanitize_text_field( $data['section'] ),
        ];

        if ( isset( $data['activate'] ) ) {
            $args['activate'] = filter_var( $data['activate'], FILTER_VALIDATE_BOOLEAN );
        }

        if ( isset( $data['lang'] ) && $data['lang'] ) {
            $args['lang'] = sanitize_text_field( $data['lang'] );
        }

        if ( isset( $data['code'] ) && $data['code'] ) {
            $args['code'] = trim( $data['code'] );
        }

        if ( isset( $data['features'] ) && is_array( $data['features'] ) ) {
            $args['features'] = array_map( 'sanitize_key', $data['features'] );
        }

        $allowed_actions = [ 'load', 'save', 'restore', 'compare', 'merge', 'merge_languages' ];

        if ( in_array( $action, $allowed_actions, true ) && method_exists( $this, $action ) ) {
            call_user_func( [ $this, $action], $args );
        } else {
            return wp_send_json_error( __( 'Invalid action, please reload the page and try again.', 'wp-store-locator' ) );
        }
    }

    /**
     * Whether the current user may use the section editor.
     *
     * A section is an Underscore template, and every real one carries
     * <% if %> blocks that run as JavaScript in each visitor's browser.
     * clean_section_template() keeps those on purpose, so the editor is
     * gated the way core gates the theme editor and raw HTML in posts:
     * on unfiltered_html, which core itself withdraws from multisite site
     * admins and behind DISALLOW_UNFILTERED_HTML. One check for the request
     * handler, the settings nav, the settings import and every link into
     * the editor, so they cannot drift apart.
     *
     * @since  3.0.0
     * @return bool
     */
    public static function current_user_can_edit() {
        return current_user_can( 'manage_wpsl_settings' ) && current_user_can( 'unfiltered_html' );
    }

    /**
     * Create a dropdown listing the templates.
     *
     * @since  3.0.0
     * @return string $options The template dropdown
     */
    public function template_dropdown() {
        $appearance_settings = $this->settings->get_group( 'appearance' );

        $template = wpsl_get_templates();

        $options = '<select id="wpsl-template-list">';

        foreach ( $template as $key => $value ) {
            $options .= '<option value="' . esc_attr( $value['id'] ) . '" ' . selected( $appearance_settings['template_id'], $value['id'], false ) . '>' . esc_html( $value['name'] ) . '</option>';
        }

        $options .= '</select>';

        return $options;
    }

    /**
     * Return the requested section code.
     *
     * @since  3.0.0
     * @param  $args
     * @return void
     */
    private function load( $args ) {
        $section = $this->template_sections->get( $args );
        $name = $this->template_sections->create_name( $args );
        
        $response = [
            'html'   => $section['html'],
            'status' => $this->template_sections->check_custom_status( $name ) 
        ];
        
        if ( isset( $section['missing_translation'] ) ) {
            $response['missing_translation'] = true;
        }
        
        wp_send_json_success( $response );
    }

    /**
     * Set the status of a custom section.
     * 
     * @since 3.0.0
     * @param array $args
     */
    public function set_custom_section_status( $args ) {
        $appearance_settings = $this->settings->get_group( 'appearance' );

        $section_name = $this->template_sections->create_name( $args );

        if ( ! $section_name ) {
            return;
        }

        $section_exists = in_array( $section_name, $appearance_settings['active_custom_sections'] );
        $settings_changed = false;
        
        if ( $args['activate'] && ! $section_exists ) {
            $appearance_settings['active_custom_sections'][] = $section_name;
            $settings_changed = true;
        } else if ( ! $args['activate'] && $section_exists ) {
            $appearance_settings['active_custom_sections'] = array_values( array_diff( $appearance_settings['active_custom_sections'], [ $section_name ] ) );
            $settings_changed = true;
        }
        
        if ( $settings_changed ) {
            $this->settings->set( 'appearance', 'active_custom_sections', $appearance_settings['active_custom_sections'] );
        }
    }

    /**
     * Validate template code the same way the client-side validator does
     * (assets/src/admin/js/modules/settings/wpsl-template-validator.js), so
     * the server never rejects code the editor accepted or vice versa.
     *
     * Two phases:
     * 1. Template/JS: every <% %> pair must open and close on the same line,
     *    and ()/{} inside the tags must balance.
     * 2. HTML: template tags stripped, markup normalized and parsed as XML,
     *    so unmatched tags, bad nesting, and unquoted attributes are reported with
     *    a line number.
     *
     * Known limitation: HTML opened in one <% if %> branch and closed in
     * another looks unbalanced once logic is stripped. Keep tag pairs within
     * a single branch.
     *
     * @since  3.0.0
     * @param  string $code The template code to validate.
     * @return string|false Error message if invalid, false if valid.
     */
    private function validate_template_syntax( $code ) {
        $errors = $this->validate_template_tags( $code );

        /**
         * Broken <% %> pairs make both the JS balance check and the stripped
         * HTML unreliable, so stop here to avoid cascading false positives.
         */
        if ( empty( $errors ) ) {
            $errors = array_merge( $this->validate_template_js( $code ), $this->validate_template_html( $code ) );
        }

        if ( empty( $errors ) ) {
            return false;
        }

        // Report the error closest to the top of the template.
        $first = null;

        foreach ( $errors as $error ) {
            if ( null === $first || $error['line'] < $first['line'] ) {
                $first = $error;
            }
        }

        return $first['message'];
    }

    /**
     * Validate that every <% opens and closes on the same line.
     *
     * The frontend template delimiters don't match newlines, so a tag that
     * spans lines is just as broken as one that never closes.
     *
     * @since  3.0.0
     * @param  string $code The template code to validate.
     * @return array Errors as line / message pairs.
     */
    private function validate_template_tags( $code ) {
        $errors = [];
        $lines  = explode( "\n", $code );

        foreach ( $lines as $line_num => $line ) {
            preg_match_all( '/<%|%>/', $line, $tokens );

            $open = false;

            foreach ( $tokens[0] as $token ) {
                if ( '<%' === $token ) {
                    if ( $open ) {
                        $errors[] = $this->unclosed_template_tag_error( $line_num + 1 );
                    }
                    $open = true;
                } elseif ( $open ) {
                    $open = false;
                } else {
                    $errors[] = [
                        'line'    => $line_num + 1,
                        /* translators: 1: line number */
                        'message' => sprintf( __( 'Line %1$d: %%> without matching <%%.', 'wp-store-locator' ), $line_num + 1 ),
                    ];
                }
            }

            if ( $open ) {
                $errors[] = $this->unclosed_template_tag_error( $line_num + 1 );
            }
        }

        return $errors;
    }

    /**
     * Build the error for a <% tag that doesn't close on its own line.
     *
     * @since  3.0.0
     * @param  int $line_num The 1-indexed line number.
     * @return array The error as a line / message pair.
     */
    private function unclosed_template_tag_error( $line_num ) {
        return [
            'line'    => $line_num,
            /* translators: 1: line number */
            'message' => sprintf( __( 'Line %1$d: unclosed <%% tag.', 'wp-store-locator' ), $line_num ),
        ];
    }

    /**
     * Check for unbalanced () and {} inside <% %> tags.
     *
     * A basic check only — string literals and comments are removed first so
     * their brackets don't count, but regex literals still do, unlike the
     * client side which also compiles the template.
     *
     * @since  3.0.0
     * @param  string $code The template code to validate.
     * @return array Errors as line / message pairs.
     */
    private function validate_template_js( $code ) {
        $paren_stack = [];
        $brace_stack = [];
        $errors      = [];
        $lines       = explode( "\n", $code );

        foreach ( $lines as $line_num => $line ) {
            if ( ! preg_match_all( '/<%[-=]?(.+?)%>/', $line, $blocks ) ) {
                continue;
            }

            foreach ( $blocks[1] as $js_code ) {
                $js_code = Template_Code::strip_js_literals( $js_code );

                if ( '' === $js_code ) {
                    continue;
                }

                foreach ( str_split( $js_code ) as $char ) {
                    if ( '(' === $char ) {
                        $paren_stack[] = $line_num + 1;
                    } elseif ( ')' === $char ) {
                        if ( empty( $paren_stack ) ) {
                            $errors[] = [
                                'line'    => $line_num + 1,
                                /* translators: 1: line number */
                                'message' => sprintf( __( 'Line %1$d: ) without matching (.', 'wp-store-locator' ), $line_num + 1 ),
                            ];
                        } else {
                            array_pop( $paren_stack );
                        }
                    } elseif ( '{' === $char ) {
                        $brace_stack[] = $line_num + 1;
                    } elseif ( '}' === $char ) {
                        if ( empty( $brace_stack ) ) {
                            $errors[] = [
                                'line'    => $line_num + 1,
                                /* translators: 1: line number */
                                'message' => sprintf( __( 'Line %1$d: } without matching {.', 'wp-store-locator' ), $line_num + 1 ),
                            ];
                        } else {
                            array_pop( $brace_stack );
                        }
                    }
                }
            }
        }

        if ( ! empty( $paren_stack ) ) {
            $errors[] = [
                'line'    => $paren_stack[0],
                /* translators: 1: line number */
                'message' => sprintf( __( 'Line %1$d: unclosed (.', 'wp-store-locator' ), $paren_stack[0] ),
            ];
        }

        if ( ! empty( $brace_stack ) ) {
            $errors[] = [
                'line'    => $brace_stack[0],
                /* translators: 1: line number */
                'message' => sprintf( __( 'Line %1$d: unclosed {.', 'wp-store-locator' ), $brace_stack[0] ),
            ];
        }

        return $errors;
    }

    /**
     * Validate the HTML structure of the template.
     *
     * @since  3.0.0
     * @param  string $code The template code to validate.
     * @return array Errors as line / message pairs.
     */
    private function validate_template_html( $code ) {
        $errors   = [];
        $stripped = $this->strip_template_tags( $code );
        $lines    = explode( "\n", $stripped );

        /*
         * A tag missing its opening < (e.g. 'div class="x">') is plain text to
         * any parser, so it needs its own check. Matches are restricted to real
         * element names, or ordinary text like 'Open now >' would be misread as
         * tag 'Open' attribute 'now'.
         */
        $elements = Template_Code::element_name_pattern();

        foreach ( $lines as $line_num => $line ) {
            if ( preg_match( '/(?:^|>)\s*\/(' . $elements . ')\s*>/i', $line, $matches ) ) {
                $errors[] = $this->missing_open_bracket_error( $line_num + 1, '/' . $matches[1] );
            }

            /*
             * Opening variant. Attribute names must start with a letter so
             * 'Store -> details' isn't read as tag 'Store' attribute '-'.
             */
            if ( preg_match( '/(?:^|>)\s*(' . $elements . ')(?:>|(?:\s+[a-zA-Z][a-zA-Z0-9-]*(?:=(?:"[^"]*"|\'[^\']*\'|[^\s>]+))?)+\s*>)/i', $line, $matches ) ) {
                $errors[] = $this->missing_open_bracket_error( $line_num + 1, $matches[1] );
            }
        }

        $xml_error = $this->parse_template_as_xml( $stripped );

        if ( $xml_error ) {
            $errors[] = $xml_error;
        }

        return $errors;
    }

    /**
     * Build the error for a tag that is missing its opening <.
     *
     * @since  3.0.0
     * @param  int    $line_num The 1-indexed line number.
     * @param  string $tag_name The tag name, prefixed with / for closing tags.
     * @return array The error as a line / message pair.
     */
    private function missing_open_bracket_error( $line_num, $tag_name ) {
        return [
            'line'    => $line_num,
            /* translators: 1: line number, 2: tag name */
            'message' => sprintf( __( 'Line %1$d: missing < for %2$s tag.', 'wp-store-locator' ), $line_num, $tag_name ),
        ];
    }

    /**
     * Strip template tags so the remaining markup can be parsed as HTML.
     *
     * @since  3.0.0
     * @param  string $code The template code.
     * @return string The code without template tags.
     */
    private function strip_template_tags( $code ) {
        return Template_Code::strip_template_tags( $code );
    }

    /**
     * Parse the stripped template as XML and translate the first parser
     * error into a readable message with a line number.
     *
     * @since  3.0.0
     * @param  string $stripped Template code with the <% %> tags stripped.
     * @return array|null The error as a line / message pair, or null if the markup is valid.
     */
    private function parse_template_as_xml( $stripped ) {
        $xml = Template_Code::normalize_for_xml( $stripped );

        $use_errors = libxml_use_internal_errors( true );
        libxml_clear_errors();

        /*
         * The wrapper sits on line 1 without a newline, so the parser's
         * line numbers map 1:1 onto the editor's.
         */
        simplexml_load_string( '<template>' . $xml . '</template>' );

        $xml_errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors( $use_errors );

        if ( empty( $xml_errors ) ) {
            return null;
        }

        $reason = trim( $xml_errors[0]->message );
        $line   = (int) $xml_errors[0]->line;

        // Translate the most cryptic parser phrasings into plain language.
        if ( false !== stripos( $reason, 'AttValue' ) ) {
            $reason = __( 'attribute value must be wrapped in quotes', 'wp-store-locator' );
        } elseif ( false !== stripos( $reason, "Unescaped '<' not allowed in attributes values" ) ) {
            $reason = __( 'unclosed quote in attribute value', 'wp-store-locator' );
        }

        if ( $line > 0 ) {
            return [
                'line'    => $line,
                /* translators: 1: line number, 2: parser error */
                'message' => sprintf( __( 'Line %1$d: HTML error: %2$s', 'wp-store-locator' ), $line, $reason ),
            ];
        }

        return [
            'line'    => PHP_INT_MAX,
            /* translators: 1: parser error */
            'message' => sprintf( __( 'HTML error: %s', 'wp-store-locator' ), $reason ),
        ];
    }

    /**
     * Save the updated section code.
     *
     * Security ( nonce + capability ) is handled centrally in actions(); this
     * private method is only reachable through that gated dispatch.
     *
     * @since  3.0.0
     * @param  array $args
     * @return void
     */
    private function save( $args ) {
        global $wpdb;

        $response = [];

        if ( isset( $args['code'] ) ) {
            // Validate the template syntax before saving.
            $validation_error = $this->validate_template_syntax( $args['code'] );
            if ( $validation_error ) {
                // Deactivate the custom section if validation fails.
                $deactivate_args = $args;
                $deactivate_args['activate'] = false;
                $this->set_custom_section_status( $deactivate_args );

                // translators: 1: validation error message
                $message = sprintf( __( '%1$s The custom template section has been deactivated.', 'wp-store-locator' ), $validation_error );
                wp_send_json_error( $message );
            }

            // Only activate/deactivate after validation passes.
            $this->set_custom_section_status( $args );

            /**
             * Look up the row for this exact language ( format-tolerant, but
             * without the neutral / first-row fallbacks the front-end uses ) —
             * falling back here would overwrite another language's template.
             */
            $section = $this->template_sections->get_custom_row( $args, true );

            // Remove tags that don't belong in the template.
            $code = $this->clean_section_template( $args['code'] );

            // The listing <li> must keep its data-store-id hook or directions
            // and marker focus break for every provider on the front-end.
            if ( 'listing' === $args['section'] ) {
                $code = $this->ensure_listing_store_id( $code );
            }

            $lang = isset( $args['lang'] ) ? $args['lang'] : '';

            if ( $section ) {
                /**
                 * Update by row id: the stored language key may be in a
                 * different format than the requested one ( 'de' vs 'de-DE' ),
                 * so matching the language column again could miss the row
                 * that get_custom_row() just found.
                 */
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query on custom table, caching not applicable for custom table operations
                $wpdb->update(
                    $this->template_table,
                    [
                        'content'  => $code,
                    ],
                    [
                        'id' => $section['id'],
                    ],
                    [
                        '%s'
                    ],
                    [
                        '%d'
                    ]
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query on custom table, caching not applicable for custom table operations
                $sql = "INSERT INTO {$this->template_table} ( template, content, section, language ) VALUES ( %s, %s, %s, %s )";

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is prepared with $wpdb->prepare() with placeholders, table name is safe class property, caching not applicable for custom table operations
                $wpdb->query( $wpdb->prepare( $sql, [ $args['template'], $code, $args['section'], $lang ] ) );
            }

            $response['html'] = $code;
        }

        wp_send_json_success( $response );
    }

    /**
     * Merge accepted features into every other language's stored section.
     * The editor only shows one language, so without this the same fix must
     * be repeated by hand per language.
     *
     * @since 3.0.0
     * @param  array $args
     * @return void
     */
    private function merge_languages( $args ) {
        global $wpdb;

        $features = isset( $args['features'] ) ? $args['features'] : [];

        if ( empty( $features ) ) {
            wp_send_json_error( __( 'Nothing to merge.', 'wp-store-locator' ) );
        }

        $analyzer = wpsl_get_service( 'section_analyzer' );
        $current  = Sections::normalize_lang( isset( $args['lang'] ) ? $args['lang'] : '' );
        $rows     = $this->template_sections->get_custom_rows( $args );
        $results  = [];

        foreach ( $rows as $row ) {
            // The editor's own language is handled by merge().
            if ( Sections::normalize_lang( $row['language'] ) === $current ) {
                continue;
            }

            $result = $analyzer->merge( $row['content'], $features, $args );
            $entry  = [
                'language' => $row['language'],
                'name'     => $analyzer->language_name( $row['language'] ),
                'applied'  => $result['applied'],
            ];

            if ( empty( $result['applied'] ) ) {
                $entry['status'] = 'unchanged';
                $results[]       = $entry;
                continue;
            }

            $validation_error = $this->validate_template_syntax( $result['code'] );

            if ( $validation_error ) {
                $entry['status']  = 'error';
                $entry['message'] = $validation_error;
                $results[]        = $entry;
                continue;
            }

            // Same cleanup a manual save applies
            $code = $this->clean_section_template( $result['code'] );

            if ( 'listing' === $args['section'] ) {
                $code = $this->ensure_listing_store_id( $code );
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query on custom table, caching not applicable for custom table operations
            $wpdb->update(
                $this->template_table,
                [ 'content' => $code ],
                [ 'id' => $row['id'] ],
                [ '%s' ],
                [ '%d' ]
            );

            $entry['status'] = 'updated';
            $results[]       = $entry;
        }

        wp_send_json_success( [ 'results' => $results ] );
    }

    /**
     * Return the default section code.
     *
     * @since  3.0.0
     * @param  array $args
     * @return void
     */
    public function restore( $args ) {
        $args['default'] = true;

        $section = $this->template_sections->get( $args );

        wp_send_json_success( [ 'html' => $section['html'] ] );
    }

    /**
     * Compare the editor's code with what the current 
     * settings generate and report enabled-but-missing features.
     *
     * @since  3.0.0
     * @param  array $args
     * @return void
     */
    private function compare( $args ) {
        $code = isset( $args['code'] ) ? $args['code'] : '';

        if ( trim( $code ) === '' ) {
            wp_send_json_success( [
                'findings' => [],
                'empty'    => true,
            ] );
        }

        $analyzer = wpsl_get_service( 'section_analyzer' );

        wp_send_json_success( [
            'findings'  => $analyzer->analyze( $code, $args ),
            'languages' => $this->other_language_rows( $args ),
        ] );
    }

    /**
     * The stored section's other languages, so the dialog can offer to apply
     * the same fix to them. Only out-of-sync rows are listed; the count is
     * what the dialog shows the user.
     *
     * @since  3.0.0
     * @param  array $args The request args ( template, section, optionally lang ).
     * @return array Entries of ( language, name ), empty when this is the only language.
     */
    private function other_language_rows( $args ) {
        $analyzer = wpsl_get_service( 'section_analyzer' );
        $current  = Sections::normalize_lang( isset( $args['lang'] ) ? $args['lang'] : '' );
        $others   = [];

        foreach ( $this->template_sections->get_custom_rows( $args ) as $row ) {
            if ( Sections::normalize_lang( $row['language'] ) === $current || '' === trim( (string) $row['content'] ) ) {
                continue;
            }

            if ( empty( $analyzer->analyze( $row['content'], $args ) ) ) {
                continue;
            }

            $others[] = [
                'language' => $row['language'],
                'name'     => $analyzer->language_name( $row['language'] ),
            ];
        }

        return $others;
    }

    /**
     * Merge the accepted missing features into the editor's code.
     *
     * @since  3.0.0
     * @param  array $args
     * @return void
     */
    private function merge( $args ) {
        $code     = isset( $args['code'] ) ? $args['code'] : '';
        $features = isset( $args['features'] ) ? $args['features'] : [];

        if ( trim( $code ) === '' || empty( $features ) ) {
            wp_send_json_error( __( 'Nothing to merge.', 'wp-store-locator' ) );
        }

        $analyzer = wpsl_get_service( 'section_analyzer' );
        $result   = $analyzer->merge( $code, $features, $args );
        $skipped  = $result['skipped'];

        if ( $result['applied'] && $this->validate_template_syntax( $result['code'] ) ) {
            $working = [];

            foreach ( $result['applied'] as $feature_id ) {
                $candidate = $analyzer->merge( $code, array_merge( $working, [ $feature_id ] ), $args );

                if ( $this->validate_template_syntax( $candidate['code'] ) ) {
                    $skipped[] = $feature_id;
                } else {
                    $working[] = $feature_id;
                }
            }

            $result            = $analyzer->merge( $code, $working, $args );
            $result['skipped'] = array_unique( array_merge( $result['skipped'], $skipped ) );
        }

        wp_send_json_success( [
            'html'    => $result['code'],
            'applied' => $result['applied'],
            'skipped' => array_values( $result['skipped'] ),
        ] );
    }

    /**
     * Guarantee the listing <li> keeps its data-store-id attribute.
     *
     * The front-end maps a clicked search result to its store through
     * <li data-store-id="<%= id %>"> ( directions rendering, marker focus ).
     *
     * @since  3.0.0
     * @param  string $code The cleaned template code.
     * @return string       The template code with data-store-id guaranteed.
     */
    public function ensure_listing_store_id( $code ) {

        // Already present on an <li> — leave the author's markup untouched.
        if ( preg_match( '/<li\b[^>]*\bdata-store-id\s*=/i', $code ) ) {
            return $code;
        }

        // Add the attribute to the first ( and only ) listing <li> opening tag.
        return preg_replace( '/<li\b/i', '<li data-store-id="<%= id %>"', $code, 1 );
    }

    /**
     * Make sure only whitelisted tags are used
     * in the template code.
     *
     * @since  3.0.0
     * @param  string $code
     * @return string The section template code
     */
    public function clean_section_template( $code ) {
        $code = wp_kses( $this->replace_template_syntax( $code ), $this->get_allowed_html() );

        $code = $this->remove_unsafe_styles( $code );
        $code = $this->force_link_relations( $code );
        $code = $this->escape_attribute_interpolations( $code );

        return trim( $this->restore_template_syntax( $code ) );
    }

    /**
     * Template variables that must keep raw <%= %> interpolation inside an
     * attribute. Either already safe there (cast to int, or escaped in the
     * data layer), or they hold markup or a URL that escaping would break.
     *
     * @since 3.0.0
     * @var   array
     */
    private $attribute_safe_vars = [
        'id',              // (int) cast in Store\Data::get_meta_data().
        'store',           // esc_html'd there, so already attribute safe.
        'email',           // esc_attr'd there.
        'url',             // esc_url'd there.
        'permalink',       // WordPress URL - escaping mangles & in query strings.
        'lat',
        'lng',
        'distance',
        'distance_unit',
        'thumb',           // <img> markup.
        'hours',           // markup.
        'description',     // wp_kses_post markup.
        'location_status', // markup ( carries a <br> ).
    ];

    /**
     * Switch <%= %> to the escaping <%- %> for text fields 
     * interpolated into an attribute value.
     *
     * @since  3.0.0
     * @param  string $code The template code, template syntax still replaced.
     * @return string The code with attribute interpolations escaped.
     */
    private function escape_attribute_interpolations( $code ) {
        /**
         * Filters the template variables left on raw interpolation inside an
         * attribute. Add a custom field here when it holds markup or a URL.
         */
        $safe = apply_filters( 'wpsl_attribute_safe_template_vars', $this->attribute_safe_vars );

        return preg_replace_callback( '/<[a-zA-Z][^>]*>/', function( $tag ) use ( $safe ) {
            return preg_replace_callback( '/([\w:.-]+)\s*=\s*("|\')(.*?)\2/s', function( $attribute ) use ( $safe ) {

                $value = preg_replace_callback( '/WPSL_TEMPLATE_OPEN=(\s*)([a-zA-Z_][a-zA-Z0-9_]*)(\s*)WPSL_TEMPLATE_CLOSE/', function( $interpolation ) use ( $safe ) {
                    if ( in_array( $interpolation[2], $safe, true ) ) {
                        return $interpolation[0];
                    }

                    // Keep the author's spacing, swap only the delimiter.
                    return 'WPSL_TEMPLATE_OPEN-' . $interpolation[1] . $interpolation[2] . $interpolation[3] . 'WPSL_TEMPLATE_CLOSE';
                }, $attribute[3] );

                return $attribute[1] . '=' . $attribute[2] . $value . $attribute[2];
            }, $tag[0] );
        }, $code );
    }

    /**
     * Drop CSS declarations that let a section escape its container.
     * 
     * position:fixed/sticky pin an element to the viewport, turning a store
     * listing into a full-page overlay. wp_kses keeps them because
     * safecss_filter_attr() allows the position property without inspecting
     * the value. Other declarations are left untouched, so saving a section
     * with nothing to strip doesn't churn the author's code.
     *
     * @since  3.0.0
     * @param  string $code The template code, template syntax still replaced.
     * @return string The code without viewport-pinning declarations.
     */
    private function remove_unsafe_styles( $code ) {
        return preg_replace_callback( '/\sstyle\s*=\s*("|\')(.*?)\1/is', function( $matches ) {
            $kept = [];
            $dropped = false;

            foreach ( explode( ';', $matches[2] ) as $declaration ) {
                if ( trim( $declaration ) === '' ) {
                    continue;
                }

                $parts = explode( ':', $declaration, 2 );

                if ( count( $parts ) === 2
                    && strtolower( trim( $parts[0] ) ) === 'position'
                    && preg_match( '/^\s*(fixed|sticky)\b/i', $parts[1] )
                ) {
                    $dropped = true;
                    continue;
                }

                $kept[] = $declaration;
            }

            if ( ! $dropped ) {
                return $matches[0];
            }

            if ( ! $kept ) {
                return '';
            }

            return ' style=' . $matches[1] . ltrim( implode( ';', $kept ) ) . $matches[1];
        }, $code );
    }

    /**
     * Make links that open a new tab carry rel="noopener".
     *
     * @since  3.0.0
     * @param  string $code The template code, template syntax still replaced.
     * @return string The code with rel="noopener" on every _blank link.
     */
    private function force_link_relations( $code ) {
        return preg_replace_callback( '/<a\s[^>]*>/i', function( $matches ) {
            $tag = $matches[0];

            if ( ! preg_match( '/\btarget\s*=\s*("|\')?_blank\b/i', $tag ) ) {
                return $tag;
            }

            if ( preg_match( '/\brel\s*=\s*("|\')(.*?)\1/is', $tag, $rel ) ) {
                $values = preg_split( '/\s+/', trim( $rel[2] ), -1, PREG_SPLIT_NO_EMPTY );

                if ( in_array( 'noopener', array_map( 'strtolower', $values ), true ) ) {
                    return $tag;
                }

                $values[] = 'noopener';

                return str_replace( $rel[0], 'rel=' . $rel[1] . implode( ' ', $values ) . $rel[1], $tag );
            }

            return preg_replace( '/\s*(\/?)>$/', ' rel="noopener"$1>', $tag );
        }, $code );
    }

    /**
     * Build the wp_kses allowlist from the allowed section tags.
     *
     * @since  3.0.0
     * @return array The tag => allowed attributes map for wp_kses().
     */
    private function get_allowed_html() {
        $common = [
            'class'    => true,
            'id'       => true,
            'style'    => true,
            'title'    => true,
            'role'     => true,
            'tabindex' => true,
            'aria-*'   => true,
        ];

        $tag_specific = [
            'a'        => [ 'href' => true, 'target' => true, 'rel' => true ],
            'img'      => [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'srcset' => true, 'sizes' => true, 'loading' => true ],
            'input'    => [ 'type' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'checked' => true, 'disabled' => true ],
            'button'   => [ 'type' => true, 'name' => true, 'value' => true ],
            'label'    => [ 'for' => true ],
            'li'       => [ 'data-store-id' => true ],
            'select'   => [ 'name' => true ],
            'option'   => [ 'value' => true, 'selected' => true ],
            'optgroup' => [ 'label' => true ],
            'data'     => [ 'value' => true ],
            'time'     => [ 'datetime' => true ],
            'th'       => [ 'colspan' => true, 'rowspan' => true, 'scope' => true ],
            'td'       => [ 'colspan' => true, 'rowspan' => true ],
            'col'      => [ 'span' => true ],
            'colgroup' => [ 'span' => true ],
            'video'    => [ 'src' => true, 'poster' => true, 'controls' => true, 'width' => true, 'height' => true ],
        ];

        $allowed = [];

        foreach ( $this->allowed_tags as $tag ) {
            $allowed[ $tag ] = isset( $tag_specific[ $tag ] )
                ? array_merge( $common, $tag_specific[ $tag ] )
                : $common;
        }

        return $allowed;
    }

    /**
     * Escape the templating syntax by replacing <% %>
     * with unique placeholders to prevent them from
     * being replaced with strip_tags().
     *
     * @since  3.0.0
     * @param  string $input Raw template code
     * @return string $input Template code with the syntax replaced
     */
    private function replace_template_syntax( $input ) {
        $input = str_replace( [ '<%', '%>' ], [ 'WPSL_TEMPLATE_OPEN', 'WPSL_TEMPLATE_CLOSE' ], $input );

        // Protect & characters inside template syntax from wp_kses encoding them to &amp;.
        $input = preg_replace_callback( '/(WPSL_TEMPLATE_OPEN.*?WPSL_TEMPLATE_CLOSE)/s', function( $matches ) {
            return str_replace( '&', 'WPSL_TEMPLATE_AMP', $matches[0] );
        }, $input );
        
        return $input;
    }

    /**
     * Restore the 'WPSL_TEMPLATE_OPEN' and 'WPSL_TEMPLATE_CLOSE'
     * placeholders with <% and %>.
     *
     * @since  3.0.0
     * @param  string $input Template code with the placeholders
     * @return string $input Template code with the original ( <% and %>  ) syntax
     */
    private function restore_template_syntax( $input ) {
        $input = str_replace( [ 'WPSL_TEMPLATE_OPEN', 'WPSL_TEMPLATE_CLOSE', 'WPSL_TEMPLATE_AMP' ], [ '<%', '%>', '&' ], $input );
        
        return $input;
    }
}