<?php
/**
 * Analyze custom template sections against the current settings.
 *
 * Detects enabled features missing from custom template sections and
 * merges the required markup at anchor points. Detection is additive-only:
 * existing markup for disabled options is reported, never removed.
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

class Section_Analyzer {

    /**
     * Cap on line matches per feature to prevent broadly-matching
     * markers ( e.g. icons ) from flooding the dialog.
     *
     * @since 3.0.0
     * @var   int
     */
    const REVERSE_MATCH_LIMIT = 12;

    /**
     * Plugin settings
     *
     * @since 3.0.0
     * @var array
     */
    private $settings;

    /**
     * Template sections generator
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Sections
     */
    private $sections;

    /**
     * Translations service, used to name the languages of out-of-sync
     * rows. Optional: without a multilingual plugin there is a single
     * language-neutral row and nothing to name.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\I18n\Translations|null
     */
    private $i18n;

    /**
     * Per-request cache of findings for a template's active custom
     * sections, keyed by template id.
     *
     * @since 3.0.0
     * @var array
     */
    private $section_findings = [];

    /**
     * The active languages indexed by slug, built on first use. Null until
     * then: an empty map is valid (no multilingual plugin) and must not
     * trigger a rebuild on every lookup.
     *
     * @since 3.0.0
     * @var   array|null
     */
    private $language_names = null;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager      $settings Settings manager instance
     * @param \WPSL\Core\Templates\Sections    $sections Template sections instance
     * @param \WPSL\Core\I18n\Translations|null $i18n    Translations instance, for naming languages
     */
    public function __construct( WpslSettings $settings, Sections $sections, ?Translations $i18n = null ) {
        $this->settings = $settings->get_all();
        $this->sections = $sections;
        $this->i18n     = $i18n;
    }

    /**
     * Does the template have an active custom listing / info window section?
     *
     * @since  3.0.0
     * @param  string $template_id The template id ( default / vertical / ... ).
     * @return bool
     */
    public function has_active_custom_section( $template_id ) {
        $active = (array) $this->settings['appearance']['active_custom_sections'];

        foreach ( [ 'listing', 'info_window' ] as $section ) {
            if ( in_array( $template_id . '_' . $section, $active, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * All findings across a template's active custom sections ( listing
     * and info window combined ). Recomputed from stored section code on
     * each call so results stay in sync with setting changes; cached per
     * request.
     *
     * @since  3.0.0
     * @param  string $template_id The template id ( default / vertical / ... ).
     * @return array
     */
    private function get_active_section_findings( $template_id ) {
        if ( isset( $this->section_findings[ $template_id ] ) ) {
            return $this->section_findings[ $template_id ];
        }

        $active   = (array) $this->settings['appearance']['active_custom_sections'];
        $findings = [];

        foreach ( [ 'listing', 'info_window' ] as $section ) {
            if ( ! in_array( $template_id . '_' . $section, $active, true ) ) {
                continue;
            }

            $args = [ 'template' => $template_id, 'section' => $section ];
            $rows = $this->sections->get_custom_rows( $args );

            /**
             * No stored rows means the section falls back to generated
             * code, which is in sync by definition.
             */
            foreach ( $rows as $row ) {
                if ( '' === trim( (string) $row['content'] ) ) {
                    continue;
                }

                foreach ( $this->analyze( $row['content'], [ 'section' => $section ] ) as $finding ) {
                    $finding['language'] = (string) $row['language'];
                    $findings[]          = $finding;
                }
            }
        }

        $this->section_findings[ $template_id ] = $findings;

        return $findings;
    }

    /**
     * The languages whose stored rows are out of sync, as the raw codes
     * stored in the wpsl_themes language column.
     *
     * @since 3.0.0
     * @param  string      $template_id The template id ( default / vertical / ... ).
     * @param  string|null $feature_id  Only count this feature, or null for any.
     * @return array The distinct language codes, in row order.
     */
    public function get_out_of_sync_languages( $template_id, $feature_id = null ) {
        $languages = [];

        foreach ( $this->get_active_section_findings( $template_id ) as $finding ) {
            if ( $feature_id !== null && $finding['id'] !== $feature_id ) {
                continue;
            }

            $language = isset( $finding['language'] ) ? $finding['language'] : '';

            if ( ! in_array( $language, $languages, true ) ) {
                $languages[] = $language;
            }
        }

        return $languages;
    }

    /**
     * The out-of-sync languages as display names, ready for a notice.
     *
     * @since 3.0.0
     * @param  string      $template_id The template id ( default / vertical / ... ).
     * @param  string|null $feature_id  Only count this feature, or null for any.
     * @return array The display names, empty when nothing is out of sync or no language is known.
     */
    public function get_out_of_sync_language_names( $template_id, $feature_id = null ) {
        $names = [];

        foreach ( $this->get_out_of_sync_languages( $template_id, $feature_id ) as $code ) {
            if ( '' === $code ) {
                continue;
            }

            $names[] = $this->language_name( $code );
        }

        return $names;
    }

    /**
     * The display name for a stored language code.
     *
     * @since 3.0.0
     * @param  string $code The stored language code.
     * @return string The display name, or the code itself when unknown.
     */
    public function language_name( $code ) {
        $code = (string) $code;

        if ( '' === $code ) {
            return '';
        }

        if ( $this->language_names === null ) {
            $this->language_names = [];

            foreach ( ( $this->i18n ? (array) $this->i18n->get_active_languages() : [] ) as $known => $name ) {
                $this->language_names[ Sections::normalize_lang( $known ) ] = $name;
            }
        }

        $slug = Sections::normalize_lang( $code );

        return isset( $this->language_names[ $slug ] ) ? $this->language_names[ $slug ] : $code;
    }

    /**
     * Does the template have an active custom section that is out of
     * sync with the current settings, in any way?
     *
     * @since  3.0.0
     * @param  string $template_id The template id ( default / vertical / ... ).
     * @return bool
     */
    public function has_out_of_sync_custom_section( $template_id ) {
        return ! empty( $this->get_active_section_findings( $template_id ) );
    }

    /**
     * Is a specific feature the reason a custom section is out of sync?
     * Scopes warnings to the setting that causes them.
     *
     * @since  3.0.0
     * @param  string $template_id The template id ( default / vertical / ... ).
     * @param  string $feature_id  The feature id ( see get_features() ).
     * @return bool
     */
    public function has_out_of_sync_feature( $template_id, $feature_id ) {
        foreach ( $this->get_active_section_findings( $template_id ) as $finding ) {
            if ( $finding['id'] === $feature_id ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze section code for enabled-but-missing features.
     *
     * @since  3.0.0
     * @param  string $code The section code from the editor.
     * @param  array  $args Optional 'section' ( listing / info_window ).
     * @return array  Findings, see the section-sync design doc.
     */
    public function analyze( $code, $args = [] ) {
        $section = isset( $args['section'] ) ? $args['section'] : 'listing';

        if ( ! in_array( $section, [ 'listing', 'info_window' ], true ) ) {
            return [];
        }

        $findings = [];
        $lines    = explode( "\n", $code );
        $scoped   = $this->code_outside_more_info( $lines );

        foreach ( $this->get_features( $section ) as $feature ) {
            $haystack = $feature['scoped'] ? $scoped : $code;
            $present  = (bool) preg_match( $feature['marker'], $haystack );

            /**
             * Reverse-only features skip the enabled side: "enabled but
             * missing" is already covered for them, and reporting twice
             * would duplicate findings on one piece of markup.
             */
            $report_forward = ! isset( $feature['report_forward'] ) || $feature['report_forward'];

            if ( $feature['enabled'] && $report_forward && ! $this->is_feature_present( $feature, $haystack ) ) {
                $finding = $this->build_finding( $feature, $lines, $code );

                if ( $finding ) {
                    $findings[] = $finding;
                }
            } elseif ( ! $feature['enabled'] && $present && ! empty( $feature['report_reverse'] ) ) {
                list( $matched_lines, $matched_snippet ) = $this->find_reverse_matches( $feature['marker'], $lines, $feature['scoped'] );

                $truncated = max( 0, count( $matched_lines ) - self::REVERSE_MATCH_LIMIT );

                $findings[] = $this->with_reverse_block_end( [
                    'id'              => $feature['id'],
                    'label'           => $feature['label'],
                    'status'          => 'info',
                    /* translators: %s: feature name */
                    'message'         => sprintf( __( 'This section still contains %s markup, but the option is disabled in the settings.', 'wp-store-locator' ), $feature['label'] ),
                    'lines'           => array_slice( $matched_lines, 0, self::REVERSE_MATCH_LIMIT ),
                    'lines_truncated' => $truncated,
                    'snippet'         => implode( "\n", array_slice( $matched_snippet, 0, self::REVERSE_MATCH_LIMIT ) ),
                ], $lines, $matched_lines );
            }
        }

        return array_merge( $findings, $this->more_info_field_findings( $section, $lines ) );
    }

    /**
     * Is an enabled feature fully present in the code?
     *
     * A feature's marker matches its container, which isn't always the whole
     * feature: a custom section can have the CTA wrapper while the "More
     * details" link inside it is missing. Optional 'requires' markers cover
     * parts the container marker can't see.
     *
     * @since  3.0.0
     * @param  array  $feature  The feature registry entry.
     * @param  string $haystack The code to search, already scoped.
     * @return bool
     */
    private function is_feature_present( $feature, $haystack ) {
        if ( ! preg_match( $feature['marker'], $haystack ) ) {
            return false;
        }

        $requires = isset( $feature['requires'] ) ? (array) $feature['requires'] : [];

        foreach ( $requires as $marker ) {
            if ( ! preg_match( $marker, $haystack ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detect leftover field markup inside the "More info" block after
     * its more_info placement was turned off. Reverse-only; uses a
     * "<field>_more_info" id to avoid colliding with primary-placement
     * findings.
     *
     * @since  3.0.0
     * @param  string $section The section name.
     * @param  array  $lines   The code split into lines.
     * @return array Findings, same shape as the reverse findings above.
     */
    private function more_info_field_findings( $section, $lines ) {
        if ( $section !== 'listing' ) {
            return [];
        }

        $range = $this->more_info_line_range( $lines );

        if ( $range === null ) {
            return [];
        }

        $ux = $this->settings['ux'];

        $fields = [
            'contact_details' => [ __( 'contact details', 'wp-store-locator' ), '/wpsl-contact-details/', (array) $ux['contact_details'] ],
            'hours'           => [ __( 'opening hours', 'wp-store-locator' ), '/<%=\s*hours(_status)?\s*%>/', (array) $ux['hours'] ],
            'description'     => [ __( 'description', 'wp-store-locator' ), '/<%=\s*description\s*%>/', (array) $ux['description'] ],
        ];

        $findings = [];

        foreach ( $fields as $id => $field ) {
            list( $label, $marker, $placements ) = $field;

            if ( in_array( 'more_info', $placements, true ) ) {
                continue;
            }

            list( $matched_lines, $matched_snippet ) = $this->find_matches_in_range( $marker, $lines, $range );

            if ( empty( $matched_lines ) ) {
                continue;
            }

            $truncated = max( 0, count( $matched_lines ) - self::REVERSE_MATCH_LIMIT );

            $findings[] = $this->with_reverse_block_end( [
                'id'              => $id . '_more_info',
                /* translators: %s: feature name */
                'label'           => sprintf( __( '%s (More info)', 'wp-store-locator' ), $label ),
                'status'          => 'info',
                /* translators: %s: feature name */
                'message'         => sprintf( __( 'The "More info" section still contains %s markup, but that section is no longer selected as a placement for it.', 'wp-store-locator' ), $label ),
                'lines'           => array_slice( $matched_lines, 0, self::REVERSE_MATCH_LIMIT ),
                'lines_truncated' => $truncated,
                'snippet'         => implode( "\n", array_slice( $matched_snippet, 0, self::REVERSE_MATCH_LIMIT ) ),
            ], $lines, $matched_lines );
        }

        return $findings;
    }

    /**
     * The 1-based closing line of the multi-line HTML block a line opens, or
     * null when it doesn't open one. Used to size up reverse findings and to
     * tell an "insert inside this element" anchor from one that lands next to
     * a sibling.
     *
     * @since  3.0.0
     * @param  array $lines     The code split into lines.
     * @param  int   $open_line The 0-based line to read.
     * @return int|null
     */
    private function html_block_end( $lines, $open_line ) {
        $stripped = Template_Code::strip_template_tags( $lines[ $open_line ] );

        if ( ! preg_match( '/<(div|p|ul|ol|li|span|a)(?![a-zA-Z0-9])[^>]*>/i', $stripped, $matches ) ) {
            return null;
        }

        $tag = strtolower( $matches[1] );

        if ( preg_match( '/<\/' . $tag . '\s*>/i', $stripped ) ) {
            return null;
        }

        $end = $this->find_closing_line( $lines, $open_line, $tag );

        return ( $end !== null && $end > $open_line ) ? $end + 1 : null;
    }

    /**
     * Expand a single-line reverse finding to its full block: the multi-line
     * HTML element it opens, or the wrapping <% if %>/<% } %> conditional
     * around a bare template tag. The snippet becomes the whole range so the
     * user sees exactly what to delete.
     *
     * @since  3.0.0
     * @param  array $finding       The finding under construction.
     * @param  array $lines         The code split into lines.
     * @param  array $matched_lines All 1-based matched line numbers.
     * @return array The finding, possibly with adjusted 'lines' / a 'line_end' key and a full-range snippet.
     */
    private function with_reverse_block_end( $finding, $lines, $matched_lines ) {
        if ( count( $matched_lines ) !== 1 ) {
            return $finding;
        }

        $start = $matched_lines[0] - 1;
        $end   = $this->html_block_end( $lines, $start );

        if ( $end === null && $start > 0 && $this->is_bare_if_opener( $lines[ $start - 1 ] ) ) {
            $wrapper_end = $this->reverse_template_block( $lines, $start - 1 );

            if ( $wrapper_end !== null ) {
                $start = $start - 1;
                $end   = $wrapper_end;
            }
        }

        if ( $end !== null ) {
            $finding['lines']    = [ $start + 1 ];
            $finding['line_end'] = $end;
            $finding['snippet']  = implode( "\n", array_slice( $lines, $start, $end - $start ) );
        }

        return $finding;
    }

    /**
     * Is this a line that only contains an <% if (...) { %> conditional?
     *
     * @since  3.0.0
     * @param  string $line The line to check.
     * @return bool
     */
    private function is_bare_if_opener( $line ) {
        return (bool) preg_match( '/^\s*<%\s*if\s*\(.*\)\s*\{\s*%>\s*$/', $line );
    }

    /**
     * The 1-based closing line of an <% if %> block, by counting brace
     * depth per line.
     *
     * @since  3.0.0
     * @param  array $lines The code split into lines.
     * @param  int   $open  The 0-based line the if-opener is on.
     * @return int|null
     */
    private function reverse_template_block( $lines, $open ) {
        $depth = 0;
        $total = count( $lines );

        for ( $i = $open; $i < $total; $i++ ) {
            $depth += substr_count( $lines[ $i ], '{' );
            $depth -= substr_count( $lines[ $i ], '}' );

            if ( $i > $open && $depth === 0 ) {
                return $i + 1;
            }
        }

        return null;
    }

    /**
     * Find every line where a feature's markup still appears so the
     * finding can point the user at what to delete. Scoped features skip
     * the "More info" block since it's a separate placement option.
     *
     * @since  3.0.0
     * @param  string $marker The feature's marker regex.
     * @param  array  $lines  The code split into lines.
     * @param  bool   $scoped Whether to exclude the "More info" block.
     * @return array{0: int[], 1: string[]} 1-based line numbers and their trimmed content, in matching order.
     */
    private function find_reverse_matches( $marker, $lines, $scoped ) {
        $matched_lines   = [];
        $matched_snippet = [];
        $exclude         = $scoped ? $this->more_info_line_range( $lines ) : null;

        foreach ( $lines as $i => $line ) {
            if ( $exclude !== null && $i >= $exclude[0] && $i <= $exclude[1] ) {
                continue;
            }

            if ( preg_match( $marker, $line ) ) {
                $matched_lines[]   = $i + 1;
                $matched_snippet[] = trim( $line );
            }
        }

        return [ $matched_lines, $matched_snippet ];
    }

    /**
     * Find matching lines within a 0-based [start, end] range. The inverse
     * of find_reverse_matches()'s exclusion — used for the "More info" block.
     *
     * @since  3.0.0
     * @param  string $marker The marker regex.
     * @param  array  $lines  The code split into lines.
     * @param  array  $range  0-based [start, end] line range to search within.
     * @return array{0: int[], 1: string[]} 1-based line numbers and their trimmed content, in matching order.
     */
    private function find_matches_in_range( $marker, $lines, $range ) {
        $matched_lines   = [];
        $matched_snippet = [];

        foreach ( $lines as $i => $line ) {
            if ( $i < $range[0] || $i > $range[1] ) {
                continue;
            }

            if ( preg_match( $marker, $line ) ) {
                $matched_lines[]   = $i + 1;
                $matched_snippet[] = trim( $line );
            }
        }

        return [ $matched_lines, $matched_snippet ];
    }

    /**
     * Merge selected missing features into the section code. Nothing is
     * persisted; already-present features are skipped.
     *
     * @since  3.0.0
     * @param  string $code        The section code from the editor.
     * @param  array  $feature_ids The accepted feature ids.
     * @param  array  $args        Optional 'section' ( listing / info_window ).
     * @return array  { code: string, applied: array, skipped: array }
     */
    public function merge( $code, $feature_ids, $args = [] ) {
        $section = isset( $args['section'] ) ? $args['section'] : 'listing';
        $lines   = explode( "\n", $code );
        $applied = [];
        $skipped = [];

        if ( ! in_array( $section, [ 'listing', 'info_window' ], true ) ) {
            return [ 'code' => $code, 'applied' => [], 'skipped' => array_values( (array) $feature_ids ) ];
        }

        $selected = [];

        foreach ( $this->get_features( $section ) as $feature ) {
            if ( in_array( $feature['id'], (array) $feature_ids, true ) && $feature['enabled'] ) {
                $selected[ $feature['id'] ] = $feature;
            }
        }

        /**
         * Icons first: they rewrite existing lines by content, so the
         * block insertions below don't invalidate anything.
         */
        if ( isset( $selected['icons'] ) ) {
            $result = preg_match( $selected['icons']['marker'], implode( "\n", $lines ) )
                ? [ 'applied' => [] ]
                : $this->apply_icon_transforms( $lines );

            if ( ! empty( $result['applied'] ) ) {
                $lines     = $result['lines'];
                $applied[] = 'icons';
            } else {
                $skipped[] = 'icons';
            }

            unset( $selected['icons'] );
        }

        // Button styling is also a content-based rewrite, applied before
        // the block insertions for the same reason as the icons.
        if ( isset( $selected['cta_styling'] ) ) {
            $result = preg_match( $selected['cta_styling']['marker'], implode( "\n", $lines ) )
                ? [ 'applied' => [] ]
                : $this->apply_button_style_transforms( $lines );

            if ( ! empty( $result['applied'] ) ) {
                $lines     = $result['lines'];
                $applied[] = 'cta_styling';
            } else {
                $skipped[] = 'cta_styling';
            }

            unset( $selected['cta_styling'] );
        }

        /**
         * The store id is added by rewriting the listing <li> opening tag, a
         * content transform like the icons / styling ones above rather than a
         * block insertion. The negative lookahead keeps repeated runs from
         * duplicating the attribute.
         */
        if ( isset( $selected['store_id'] ) ) {
            $joined = implode( "\n", $lines );
            $new    = preg_replace( '/<li\b(?![^>]*\bdata-store-id\b)/i', '<li data-store-id="<%= id %>"', $joined, 1 );

            if ( null !== $new && $new !== $joined ) {
                $lines     = explode( "\n", $new );
                $applied[] = 'store_id';
            } else {
                $skipped[] = 'store_id';
            }

            unset( $selected['store_id'] );
        }

        /**
         * Resolve anchors against the current code, then apply insertions
         * bottom-up so earlier splices don't shift later line numbers.
         */
        $order   = [ 'description' => 1, 'hours' => 2, 'contact_details' => 3, 'more_info' => 4, 'cta_section' => 5 ];
        $current = implode( "\n", $lines );
        $scoped  = $this->code_outside_more_info( $lines );
        $inserts = [];

        foreach ( $selected as $id => $feature ) {
            $haystack = $feature['scoped'] ? $scoped : $current;

            if ( $this->is_feature_present( $feature, $haystack ) ) {
                $skipped[] = $id;
                continue;
            }

            $line    = $this->resolve_anchor( $feature['anchor'], $lines, $current );
            $snippet = $this->get_snippet( $id, $current );

            if ( $line === null || $snippet === '' ) {
                $skipped[] = $id;
                continue;
            }

            $inserts[] = [ 'id' => $id, 'line' => $line, 'snippet' => $snippet ];
        }

        usort( $inserts, function( $a, $b ) use ( $order ) {
            if ( $a['line'] !== $b['line'] ) {
                return $b['line'] - $a['line'];
            }

            return $order[ $b['id'] ] - $order[ $a['id'] ];
        } );

        /*
         * Indent each block to match the line it's inserted after. Snippets
         * carry the indentation of wherever they were written (literals at
         * column 0, generator blocks two tabs in), so without this one merge
         * can drop two blocks at different depths into the same template.
         */
        foreach ( $inserts as $insert ) {
            $snippet = Template_Code::reindent( $insert['snippet'], $this->insert_indent( $lines, $insert['line'] ) );

            array_splice( $lines, $insert['line'] + 1, 0, explode( "\n", $snippet ) );
            $applied[] = $insert['id'];
        }

        return [
            'code'    => implode( "\n", $lines ),
            'applied' => $applied,
            'skipped' => $skipped,
        ];
    }

    /**
     * The indentation a merged block gets: the indentation of the line it's
     * inserted after.
     *
     * Except when that line opens an element the block goes *inside* (the
     * CTA section's links do, anchoring to the wrapper's opening tag to keep
     * the details/directions order). There the anchor's indentation is the
     * parent's, so the block follows the sibling below it instead.
     *
     * @since  3.0.0
     * @param  array $lines The code split into lines.
     * @param  int   $line  The 0-based line the block is inserted after.
     * @return string The leading tabs / spaces to indent with.
     */
    private function insert_indent( $lines, $line ) {
        $anchor = isset( $lines[ $line ] ) ? $lines[ $line ] : '';
        $indent = Template_Code::line_indent( $anchor );

        if ( ! isset( $lines[ $line + 1 ] ) || $this->html_block_end( $lines, $line ) === null ) {
            return $indent;
        }

        $inner = Template_Code::line_indent( $lines[ $line + 1 ] );

        return ( strlen( $inner ) > strlen( $indent ) ) ? $inner : $indent;
    }

    /**
     * The feature registry for a section: each feature has an enabled flag,
     * marker regex, scoped flag, and anchor for snippet insertion.
     *
     * @since  3.0.0
     * @param  string $section The section name.
     * @return array
     */
    private function get_features( $section ) {
        $appearance = $this->settings['appearance'];
        $ux         = $this->settings['ux'];

        $cta_enabled = ! empty( $appearance['cta']['enabled'] );
        $cta_details = ! empty( $appearance['cta']['details'] );

        // Name search has no route, so the CTA section holds the details link alone
        // there. Without that link Sections::listing() writes no wrapper at all, and
        // the feature has to agree or every custom section is reported as missing it.
        $is_name_search = $this->sections->is_name_search();

        $placement = ( $section === 'listing' ) ? 'search_results' : 'marker_popup';

        $features = [];

        $features[] = [
            'id'             => 'icons',
            'label'          => __( 'icons', 'wp-store-locator' ),
            'enabled'        => ! empty( $appearance['icons']['enabled'] ),
            'marker'         => '/wpsl-icon-/',
            'scoped'         => false,
            'anchor'         => 'icons',
            'report_reverse' => true,
        ];

        $features[] = [
            'id'             => 'description',
            'label'          => __( 'description', 'wp-store-locator' ),
            'enabled'        => in_array( $placement, (array) $ux['description'], true ),
            'marker'         => '/<%=\s*description\s*%>/',
            'scoped'         => true,
            'anchor'         => 'after_address',
            'report_reverse' => true,
        ];

        $features[] = [
            'id'             => 'hours',
            'label'          => __( 'opening hours', 'wp-store-locator' ),
            'enabled'        => ( $section === 'listing' )
                ? in_array( 'search_results', (array) $ux['hours'], true )
                : ( in_array( 'marker_popup', (array) $ux['hours'], true ) && ! empty( $ux['show_hour_status'] ) ),
            'marker'         => '/<%=\s*hours(_status)?\s*%>/',
            'scoped'         => true,
            'anchor'         => 'after_address',
            'report_reverse' => true,
        ];

        $features[] = [
            'id'             => 'contact_details',
            'label'          => __( 'contact details', 'wp-store-locator' ),
            'enabled'        => in_array( $placement, (array) $ux['contact_details'], true ),
            'marker'         => '/wpsl-contact-details/',
            'scoped'         => true,
            'anchor'         => 'after_address',
            'report_reverse' => true,
        ];

        if ( $section === 'listing' ) {
            $features[] = [
                'id'      => 'store_id',
                'label'   => __( 'store id attribute', 'wp-store-locator' ),
                'enabled' => true,
                'marker'  => '/<li\b[^>]*\bdata-store-id\b/i',
                'scoped'  => false,
                'anchor'  => 'store_id',
            ];

            $features[] = [
                'id'             => 'more_info',
                'label'          => __( 'more info', 'wp-store-locator' ),
                'enabled'        => $this->has_more_info_enabled(),
                'marker'         => '/wpsl-more-info-listings/',
                'scoped'         => false,
                'anchor'         => 'store_location_close',
                'report_reverse' => true,
            ];

            $features[] = [
                'id'             => 'cta_section',
                'label'          => __( 'call to action buttons', 'wp-store-locator' ),
                'enabled'        => $cta_details || ( $cta_enabled && ! $is_name_search ),
                'marker'         => '/wpsl-cta-section/',
                // The wrapper can be there while the details link is not.
                'requires'       => $cta_details ? [ '/wpsl-details/' ] : [],
                'scoped'         => false,
                'anchor'         => 'cta_section',
                'report_reverse' => true,
            ];

            /**
             * The details link on its own, reported only in reverse.
             *
             * cta_section's marker matches the wrapper and stays enabled while
             * the CTA section is wanted for the directions button alone, so
             * turning "More details link" off left it in the template with
             * neither direction of the cta_section check able to see it. The
             * enabled side is already covered by cta_section's 'requires'.
             *
             * Registered only while the CTA section itself is enabled: with
             * the whole section off, cta_section reports the entire block
             * (link included) and this would duplicate it.
             */
            if ( $cta_enabled ) {
                $features[] = [
                    'id'             => 'cta_details',
                    'label'          => __( 'more details link', 'wp-store-locator' ),
                    'enabled'        => $cta_details,
                    'marker'         => '/wpsl-details/',
                    'scoped'         => false,
                    'anchor'         => 'cta_section',
                    'report_forward' => false,
                    'report_reverse' => true,
                ];
            }

            $features[] = [
                'id'             => 'cta_styling',
                'label'          => __( 'call to action button styling', 'wp-store-locator' ),
                'enabled'        => $cta_enabled,
                'marker'         => '/wpsl-styled-btn/',
                'scoped'         => false,
                'anchor'         => 'cta_styling',
                'report_reverse' => true,
            ];
        }

        return $features;
    }

    /**
     * Is the "More info" block enabled? Mirrors Sections::has_more_info_enabled().
     *
     * @since  3.0.0
     * @return bool
     */
    private function has_more_info_enabled() {
        $ux = $this->settings['ux'];

        return in_array( 'more_info', (array) $ux['contact_details'], true )
            || in_array( 'more_info', (array) $ux['hours'], true )
            || in_array( 'more_info', (array) $ux['description'], true );
    }

    /**
     * Build the finding for an enabled-but-missing feature.
     *
     * @since  3.0.0
     * @param  array  $feature The feature registry entry.
     * @param  array  $lines   The code split into lines.
     * @param  string $code    The full code.
     * @return array|null The finding, or null when there is nothing to insert.
     */
    private function build_finding( $feature, $lines, $code ) {
        /**
         * Icons transform existing markup instead of inserting a block:
         * mergeable whenever at least one sub-transform finds its anchor.
         */
        if ( $feature['anchor'] === 'icons' ) {
            $result = $this->apply_icon_transforms( $lines );

            if ( ! empty( $result['applied'] ) ) {
                return [
                    'id'      => $feature['id'],
                    'label'   => $feature['label'],
                    'status'  => 'mergeable',
                    'message' => __( 'Adds the icon classes and wrappers to the existing address, contact details, hours and distance markup.', 'wp-store-locator' ),
                ];
            }

            return [
                'id'      => $feature['id'],
                'label'   => $feature['label'],
                'status'  => 'manual',
                'snippet' => $this->icons_manual_snippet(),
            ];
        }

        /**
         * Button styling transforms existing markup. No manual fallback:
         * when no template-authored action links exist, nothing to style.
         */
        if ( $feature['anchor'] === 'cta_styling' ) {
            $result = $this->apply_button_style_transforms( $lines );

            if ( empty( $result['applied'] ) ) {
                return null;
            }

            return [
                'id'      => $feature['id'],
                'label'   => $feature['label'],
                'status'  => 'mergeable',
                'message' => __( 'Adds the configured button style classes to the existing action links.', 'wp-store-locator' ),
            ];
        }

        /**
         * The store id is an attribute on the listing <li>, not a block. It is
         * merged by rewriting the opening tag ( see merge() ), so the finding
         * carries no snippet or insert line.
         */
        if ( $feature['anchor'] === 'store_id' ) {
            return [
                'id'      => $feature['id'],
                'label'   => $feature['label'],
                'status'  => 'mergeable',
                'message' => __( 'Adds data-store-id="<%= id %>" to the result <li>. The front-end needs it to link a clicked result to its store; without it the Directions button does nothing.', 'wp-store-locator' ),
            ];
        }

        $snippet = $this->get_snippet( $feature['id'], $code );

        if ( $snippet === '' ) {
            return null;
        }

        $finding = [
            'id'      => $feature['id'],
            'label'   => $feature['label'],
            'snippet' => $snippet,
        ];

        // Spell out that this one adds a link to a block that looks complete.
        if ( $feature['id'] === 'cta_section' && $this->cta_wrapper_line( $code ) !== null ) {
            $finding['message'] = __( 'This section already has a call to action block, but not the link for every enabled button. The missing link goes inside the block you have.', 'wp-store-locator' );
        }

        $insert_line = $this->resolve_anchor( $feature['anchor'], $lines, $code );

        if ( $insert_line === null ) {
            $finding['status'] = 'manual';
        } else {
            $finding['status']      = 'mergeable';
            $finding['insert_line'] = $insert_line + 1;
        }

        return $finding;
    }

    /**
     * Resolve an anchor to the 0-based line index the snippet is inserted
     * after, or null when the target structure doesn't exist.
     *
     * @since  3.0.0
     * @param  string $anchor The anchor name from the feature registry.
     * @param  array  $lines  The code split into lines.
     * @param  string $code   The full code.
     * @return int|null
     */
    private function resolve_anchor( $anchor, $lines, $code ) {
        $dom = Template_Code::parse_dom( $code );

        if ( ! $dom ) {
            return null;
        }

        $xpath = new \DOMXPath( $dom );

        switch ( $anchor ) {
            case 'after_address':
                $open = $this->address_open_line( $lines, $xpath );

                return ( $open === null ) ? null : $this->find_closing_line( $lines, $open, 'p' );
            case 'store_location_close':
                $open = $this->element_line( $xpath, "//div[contains(concat(' ', normalize-space(@class), ' '), ' wpsl-store-location ')]" );

                if ( $open === null ) {
                    return null;
                }

                $close = $this->find_closing_line( $lines, $open, 'div' );

                return ( $close === null ) ? null : $close - 1;
            case 'cta_section':
                $open = $this->cta_wrapper_line( $code );

                if ( $open !== null ) {
                    $close = $this->find_closing_line( $lines, $open, 'div' );

                    /**
                     * The wrapper is already there, so only the missing link
                     * goes in — right after the opening tag, which keeps the
                     * details / directions order. A wrapper that opens and
                     * closes on one line has no room for it, so that degrades
                     * to a manual finding.
                     */
                    return ( $close !== null && $close > $open ) ? $open : null;
                }

                // No CTA section yet: add the whole block at the end of the <li>.

            case 'before_li_close':
                $open = $this->element_line( $xpath, '//li' );

                if ( $open === null ) {
                    return null;
                }

                $close = $this->find_closing_line( $lines, $open, 'li' );

                return ( $close === null ) ? null : $close - 1;
        }

        return null;
    }

    /**
     * The 0-based line the section's CTA wrapper opens on, or null when
     * the section doesn't have one.
     *
     * @since  3.0.0
     * @param  string $code The full code.
     * @return int|null
     */
    private function cta_wrapper_line( $code ) {
        $dom = Template_Code::parse_dom( $code );

        if ( ! $dom ) {
            return null;
        }

        return $this->element_line(
            new \DOMXPath( $dom ),
            "//div[contains(concat(' ', normalize-space(@class), ' '), ' wpsl-cta-section ')]"
        );
    }

    /**
     * The 0-based line where the first element matching the XPath query opens.
     *
     * @since  3.0.0
     * @param  \DOMXPath $xpath The document xpath.
     * @param  string    $query The query.
     * @return int|null
     */
    private function element_line( $xpath, $query ) {
        $nodes = $xpath->query( $query );

        if ( ! $nodes || ! $nodes->length ) {
            return null;
        }

        return $nodes->item( 0 )->getLineNo() - 1;
    }

    /**
     * The 0-based line where the address paragraph opens: the last <p>
     * that opens on or before the line containing <%= address %>.
     *
     * @since  3.0.0
     * @param  array     $lines The code split into lines.
     * @param  \DOMXPath $xpath The document xpath.
     * @return int|null
     */
    private function address_open_line( $lines, $xpath ) {
        $addr = null;

        foreach ( $lines as $i => $line ) {
            if ( preg_match( '/<%=\s*address\s*%>/', $line ) ) {
                $addr = $i;
                break;
            }
        }

        if ( $addr === null ) {
            return null;
        }

        $open = null;

        foreach ( $xpath->query( '//p' ) as $node ) {
            $line = $node->getLineNo() - 1;

            if ( $line <= $addr && ( $open === null || $line > $open ) ) {
                $open = $line;
            }
        }

        return $open;
    }

    /**
     * Find the 0-based line where the tag opened at $open_line closes,
     * by counting tag depth per template-stripped line.
     *
     * @since  3.0.0
     * @param  array  $lines     The code split into lines.
     * @param  int    $open_line The 0-based line the opening tag is on.
     * @param  string $tag       The tag name.
     * @return int|null
     */
    private function find_closing_line( $lines, $open_line, $tag ) {
        $depth = 0;
        $total = count( $lines );
        $safe  = preg_quote( $tag, '/' );

        for ( $i = $open_line; $i < $total; $i++ ) {
            $stripped = Template_Code::strip_template_tags( $lines[ $i ] );

            $depth += preg_match_all( '/<' . $safe . '(?![a-zA-Z0-9])[^>]*>/i', $stripped );
            $depth -= preg_match_all( '/<\/' . $safe . '\s*>/i', $stripped );

            if ( $depth === 0 && preg_match( '/<\/?' . $safe . '(?![a-zA-Z0-9])/i', $stripped ) ) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The section code with the more-info block removed, so top-level
     * markers don't match markup inside the collapsible div.
     *
     * @since  3.0.0
     * @param  array $lines The code split into lines.
     * @return string
     */
    private function code_outside_more_info( $lines ) {
        $range = $this->more_info_line_range( $lines );

        if ( $range === null ) {
            return implode( "\n", $lines );
        }

        $outside = [];

        foreach ( $lines as $i => $line ) {
            if ( $i < $range[0] || $i > $range[1] ) {
                $outside[] = $line;
            }
        }

        return implode( "\n", $outside );
    }

    /**
     * The 0-based [start, end] line range of the more-info div, or null.
     *
     * @since  3.0.0
     * @param  array $lines The code split into lines.
     * @return array|null
     */
    private function more_info_line_range( $lines ) {
        $start = null;

        foreach ( $lines as $i => $line ) {
            if ( strpos( $line, 'wpsl-more-info-listings' ) !== false ) {
                $start = $i;
                break;
            }
        }

        if ( $start === null ) {
            return null;
        }

        $end = $this->find_closing_line( $lines, $start, 'div' );

        return [ $start, ( $end !== null ) ? $end : $start ];
    }

    /**
     * The snippet to merge in for a feature. Normalized to \n line
     * endings; tab indentation from the generators is preserved.
     *
     * @since  3.0.0
     * @param  string $feature_id The feature id.
     * @param  string $code       The current section code ( for duplication guards ).
     * @return string
     */
    private function get_snippet( $feature_id, $code ) {
        $icons_enabled = ! empty( $this->settings['appearance']['icons']['enabled'] );

        switch ( $feature_id ) {
            case 'description':
                return "<% if ( description ) { %>\n<p><%= description %></p>\n<% } %>";
            case 'hours':
                $hours = $icons_enabled
                    ? '<div class="wpsl-icon-hours"><%= hours %></div>'
                    : '<%= hours %>';

                return '<% if ( typeof hours !== "undefined" && hours ) { %>' . "\n" . $hours . "\n" . '<% } %>';
            case 'contact_details':
                return $this->normalize_snippet( $this->sections->contact_details() );
            case 'more_info':
                return $this->normalize_snippet( $this->sections->more_info_template() );
            case 'cta_section':
                $include_directions = ( strpos( $code, 'createDirectionUrl()' ) === false ) && ! $this->sections->is_name_search();
                $include_details    = ! empty( $this->settings['appearance']['cta']['details'] ) && strpos( $code, 'wpsl-details' ) === false;

                if ( ! $include_directions && ! $include_details ) {
                    return '';
                }

                /**
                 * A section that already has the wrapper only needs the
                 * link that is missing from it, not a second wrapper.
                 */
                $cta = ( $this->cta_wrapper_line( $code ) !== null )
                    ? $this->sections->cta_links( $include_details, $include_directions )
                    : $this->sections->cta_section( $include_details, $include_directions );

                return $this->normalize_snippet( $cta );
        }

        return '';
    }

    /**
     * Convert a Sections generator block to editor formatting.
     *
     * @since  3.0.0
     * @param  string $snippet The generated block.
     * @return string
     */
    private function normalize_snippet( $snippet ) {
        return trim( str_replace( "\r\n", "\n", $snippet ), "\n" );
    }

    /**
     * Apply icon transforms to the section code: address paragraph
     * classes + wrapper, contact span icon classes, hours wrapper, and
     * distance span icon.
     *
     * @since  3.0.0
     * @param  array $lines The code split into lines.
     * @return array { lines: array, applied: array }
     */
    private function apply_icon_transforms( $lines ) {
        $icons   = isset( $this->settings['appearance']['icons'] ) ? (array) $this->settings['appearance']['icons'] : [];
        $address = $this->sanitize_icon( isset( $icons['address'] ) ? $icons['address'] : '', 'marker' );
        $phone   = $this->sanitize_icon( isset( $icons['phone'] ) ? $icons['phone'] : '', 'phone' );
        $email   = $this->sanitize_icon( isset( $icons['email'] ) ? $icons['email'] : '', 'email' );

        $applied = [];

        // 1. Address paragraph: icon classes + the location-content wrapper.
        $dom = Template_Code::parse_dom( implode( "\n", $lines ) );

        if ( $dom ) {
            $open = $this->address_open_line( $lines, new \DOMXPath( $dom ) );

            if ( $open !== null && strpos( $lines[ $open ], 'wpsl-icon-address' ) === false ) {
                $close = $this->find_closing_line( $lines, $open, 'p' );

                if ( $close !== null && $close > $open ) {
                    $lines[ $open ] = preg_replace_callback( '/<p(\s[^>]*)?>/i', function( $m ) use ( $address ) {
                        $attrs = isset( $m[1] ) ? $m[1] : '';

                        if ( preg_match( '/class=(["\'])(.*?)\1/i', $attrs, $cm ) ) {
                            $attrs = str_replace( $cm[0], 'class=' . $cm[1] . $cm[2] . ' wpsl-icon-address wpsl-icon-address-' . $address . $cm[1], $attrs );
                        } else {
                            $attrs .= ' class="wpsl-icon-address wpsl-icon-address-' . $address . '"';
                        }

                        return '<p' . $attrs . '>';
                    }, $lines[ $open ], 1 );

                    // Splice bottom-up so the first insert doesn't shift the second.
                    array_splice( $lines, $close, 0, [ '</span>' ] );
                    array_splice( $lines, $open + 1, 0, [ '<span class="wpsl-location-content">' ] );

                    $applied[] = 'address';
                }
            }
        }

        // 2. Contact spans: swap the <strong>Label</strong>: prefix for the icon class.
        $contact_rules = [
            'contact_phone' => [ '/<span[^>]*><strong>[^<]*<\/strong>:?\s*(<%=\s*formatPhoneNumber\(\s*phone\s*\)\s*%>)\s*<\/span>/', 'wpsl-icon-' . $phone ],
            'contact_fax'   => [ '/<span[^>]*><strong>[^<]*<\/strong>:?\s*(<%=\s*formatPhoneNumber\(\s*fax\s*\)\s*%>)\s*<\/span>/', 'wpsl-icon-fax' ],
            'contact_email' => [ '/<span[^>]*><strong>[^<]*<\/strong>:?\s*(<%=\s*formatEmail\(\s*email\s*\)\s*%>)\s*<\/span>/', 'wpsl-icon-' . $email ],
        ];

        foreach ( $lines as $i => $line ) {
            foreach ( $contact_rules as $key => $rule ) {
                if ( preg_match( $rule[0], $lines[ $i ] ) ) {
                    $lines[ $i ] = preg_replace( $rule[0], '<span class="' . $rule[1] . '">$1</span>', $lines[ $i ] );
                    $applied[]   = $key;
                }
            }

            // 3. Wrap a bare hours interpolation.
            if ( preg_match( '/^(\s*)<%=\s*hours\s*%>\s*$/', $lines[ $i ], $m ) ) {
                $lines[ $i ] = $m[1] . '<div class="wpsl-icon-hours"><%= hours %></div>';
                $applied[]   = 'hours';
            }

            // 4. Add the road icon to the distance span.
            if ( strpos( $lines[ $i ], 'wpsl-distance' ) !== false && strpos( $lines[ $i ], 'wpsl-icon-road' ) === false ) {
                $updated = preg_replace( '/class=(["\'])([^"\']*wpsl-distance[^"\']*)\1/', 'class=$1$2 wpsl-icon-road$1', $lines[ $i ], 1, $count );

                if ( $count ) {
                    $lines[ $i ] = $updated;
                    $applied[]   = 'distance';
                }
            }
        }

        return [
            'lines'   => $lines,
            'applied' => $applied,
        ];
    }

    /**
     * Add configured button style classes to template-authored action
     * links. Only missing classes are added; runtime-generated links
     * are not touched.
     *
     * @since  3.0.0
     * @param  array $lines The code split into lines.
     * @return array { lines: array, applied: array }
     */
    private function apply_button_style_transforms( $lines ) {
        $styles = isset( $this->settings['appearance']['button_styles'] ) ? (array) $this->settings['appearance']['button_styles'] : [];

        $link_rules = [
            'details'    => [ 'wpsl-details', isset( $styles['more_details'] ) ? $styles['more_details'] : '', 'primary' ],
            'directions' => [ 'wpsl-directions', isset( $styles['directions'] ) ? $styles['directions'] : '', 'secondary' ],
        ];

        $applied = [];

        foreach ( $lines as $i => $line ) {
            foreach ( $link_rules as $key => $rule ) {
                if ( strpos( $lines[ $i ], $rule[0] ) === false ) {
                    continue;
                }

                $pattern = '/class=(["\'])([^"\']*' . $rule[0] . '[^"\']*)\1/';

                if ( ! preg_match( $pattern, $lines[ $i ], $matches ) ) {
                    continue;
                }

                $style = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $rule[1] ) );

                if ( $style === '' ) {
                    $style = $rule[2];
                }

                $color_class = 'wpsl-' . $style . '-btn';

                /**
                 * Scope the check to this link's own class attribute —
                 * another already-styled link on the same line must not
                 * suppress this one.
                 */
                $existing_classes = preg_split( '/\s+/', trim( $matches[2] ) );
                $needed_classes   = [];

                if ( ! in_array( 'wpsl-styled-btn', $existing_classes, true ) ) {
                    $needed_classes[] = 'wpsl-styled-btn';
                }

                if ( ! in_array( $color_class, $existing_classes, true ) ) {
                    $needed_classes[] = $color_class;
                }

                if ( empty( $needed_classes ) ) {
                    continue;
                }

                $updated = preg_replace( $pattern, 'class=$1$2 ' . implode( ' ', $needed_classes ) . '$1', $lines[ $i ], 1, $count );

                if ( $count ) {
                    $lines[ $i ] = $updated;
                    $applied[]   = $key;
                }
            }
        }

        return [
            'lines'   => $lines,
            'applied' => $applied,
        ];
    }

    /**
     * Reduce an icon setting to a css-class-safe value.
     *
     * @since  3.0.0
     * @param  string $value   The stored icon value.
     * @param  string $default The fallback.
     * @return string
     */
    private function sanitize_icon( $value, $default ) {
        $value = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $value ) );

        return ( $value !== '' ) ? $value : $default;
    }

    /**
     * Example markup shown when the icon transforms find no anchors.
     *
     * @since  3.0.0
     * @return string
     */
    private function icons_manual_snippet() {
        $icons   = isset( $this->settings['appearance']['icons'] ) ? (array) $this->settings['appearance']['icons'] : [];
        $address = $this->sanitize_icon( isset( $icons['address'] ) ? $icons['address'] : '', 'marker' );

        return '<p class="wpsl-icon-address wpsl-icon-address-' . $address . '">' . "\n"
            . '<span class="wpsl-location-content">' . "\n"
            . '<!-- address fields -->' . "\n"
            . '</span>' . "\n"
            . '</p>';
    }
}