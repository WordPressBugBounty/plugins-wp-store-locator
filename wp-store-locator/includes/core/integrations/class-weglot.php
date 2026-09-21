<?php
/**
 * WPSL / Weglot integration.
 *
 * Weglot translates the rendered DOM, so everything WPSL hands to
 * wp_localize_script() and the JSON the store search answers with is invisible
 * to it: its parser skips text inside <script> elements, and its JSON checker
 * only translates values under keys it knows.
 *
 * This class registers that data with Weglot's two extension points. Every key
 * list is derived from the code that produces the data, so a new label or
 * custom field is picked up without touching this file.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Weglot {

    /**
     * The service container, used to resolve the frontend services lazily.
     *
     * The Weglot filters fire while Weglot processes the output buffer, long
     * after init. Resolving 'assets_resources' or 'store_data' at construction
     * would pull in the frontend asset stack on requests that never render a
     * map.
     *
     * @since 3.0.0
     * @var   \WPSL\Core\Container
     */
    private $container;

    /**
     * Placeholder token map for the section-template masking,
     * token => original placeholder run.
     *
     * Accumulates across calls: Weglot runs every checker's callback before
     * any revert_callback, so a reset in between would orphan earlier tokens.
     *
     * @since 3.0.0
     * @var   string[]
     */
    private $placeholders = [];

    /**
     * Class constructor.
     *
     * Only constructed when Weglot is active, see
     * Frontend\Controller::maybe_load_weglot().
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container $container The service container.
     */
    public function __construct( $container ) {
        $this->container = $container;

        add_filter( 'weglot_add_json_keys', [ $this, 'add_store_json_keys' ] );
        add_filter( 'weglot_get_regex_checkers', [ $this, 'add_regex_checkers' ] );
    }

    /**
     * Register the store fields with Weglot's JSON translation.
     *
     * Weglot translates the values under the keys collected through this
     * filter. It fires for every JSON response it buffers, so the keys are
     * only added to WPSL's own search response.
     *
     * @since  3.0.0
     * @param  mixed $keys The keys Weglot already translates.
     * @return array The keys, with the store fields added for WPSL's search action.
     */
    public function add_store_json_keys( $keys ) {
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }

        $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

        if ( 'store_search' !== $action ) {
            return $keys;
        }

        return array_values( array_unique( array_merge( $keys, $this->store_json_keys() ) ) );
    }

    /**
     * The store payload keys whose values are text.
     *
     * Derived from the field map the search response is built from
     * ( Data::frontend_meta_fields() ), so Fields Manager fields are picked
     * up automatically. Fields that hold data rather than text are excluded:
     * machine-translating a phone number, a URL or a postcode corrupts it.
     *
     * @since  3.0.0
     * @return array The key names.
     */
    public function store_json_keys() {
        // Text keys Data::get_meta_data() adds outside frontend_meta_fields():
        // the title, the post content, the "Permanently closed" message and the
        // popup's open / closed status string.
        $keys = [ 'store', 'description', 'location_status', 'hours_status' ];

        $excluded_types = [ 'numeric', 'email', 'url' ];
        $excluded_names = [ 'zip', 'phone', 'fax' ];

        $store_data = null;

        if ( is_object( $this->container ) && method_exists( $this->container, 'has' ) && $this->container->has( 'store_data' ) ) {
            $store_data = $this->container->get( 'store_data' );
        }

        if ( $store_data ) {
            foreach ( $store_data->frontend_meta_fields() as $field ) {
                if ( ! isset( $field['name'] ) ) {
                    continue;
                }

                $type = isset( $field['type'] ) ? $field['type'] : 'text';

                if ( in_array( $type, $excluded_types, true ) || in_array( $field['name'], $excluded_names, true ) ) {
                    continue;
                }

                $keys[] = $field['name'];
            }
        }

        /**
         * Filter the store payload keys Weglot translates.
         *
         * @since 3.0.0
         * @param array $keys The key names.
         */
        return apply_filters( 'wpsl_weglot_json_keys', array_values( array_unique( $keys ) ) );
    }

    /**
     * Hand the localized script objects to Weglot as regex checkers.
     *
     * @since  3.0.0
     * @param  mixed $checkers The checkers Weglot has collected.
     * @return mixed The checkers with WPSL's appended.
     */
    public function add_regex_checkers( $checkers ) {
        // Guarded: if either symbol is renamed or removed in a future Weglot
        // version, degrade to "not translated" rather than fatal below.
        if ( ! is_array( $checkers )
            || ! class_exists( '\Weglot\Parser\Check\Regex\RegexChecker' )
            || ! class_exists( '\Weglot\Util\SourceType' )
        ) {
            return $checkers;
        }

        foreach ( $this->checker_definitions() as $definition ) {
            $mask = ! empty( $definition['mask'] );

            $checkers[] = new \Weglot\Parser\Check\Regex\RegexChecker(
                '#var\s+' . preg_quote( $definition['object_name'], '#' ) . '\s*=\s*(.*);#',
                \Weglot\Util\SourceType::SOURCE_JSON,
                1,
                $definition['keys'],
                $mask ? [ $this, 'mask_placeholders' ] : null,
                $mask ? [ $this, 'unmask_placeholders' ] : [ $this, 'reencode' ]
            );
        }

        return $checkers;
    }

    /**
     * The localized objects Weglot should translate, and how.
     *
     * Key lists are read from the same methods that build the localized data
     * (see Assets\Manager::enqueue_scripts()), so a new label is picked up
     * without touching this class.
     *
     * @since  3.0.0
     * @return array Definitions: object_name, keys, mask.
     */
    public function checker_definitions() {
        $resources = null;

        if ( is_object( $this->container ) && method_exists( $this->container, 'has' ) && $this->container->has( 'assets_resources' ) ) {
            $resources = $this->container->get( 'assets_resources' );
        }

        if ( ! $resources ) {
            return [];
        }

        $definitions = [
            [
                'object_name' => 'wpslLabels',
                'keys'        => array_keys( $resources->labels() ),
                'mask'        => false,
            ],
            [
                'object_name' => 'wpslGeolocationErrors',
                'keys'        => array_keys( $resources->geolocation_errors() ),
                'mask'        => false,
            ],
            [
                'object_name' => 'wpslApiErrors',
                'keys'        => $this->collect_leaf_keys( wpsl_api_error_messages() ),
                'mask'        => false,
            ],
        ];

        /**
         * Filter whether the section templates are handed to Weglot.
         *
         * @since 3.0.0
         * @param bool $translate Default true.
         */
        if ( apply_filters( 'wpsl_weglot_translate_template_sections', true ) ) {
            $definitions[] = [
                'object_name' => 'wpslTemplateSections',
                // No key list needed, Weglot parses any value carrying tags as HTML.
                'keys'        => [],
                'mask'        => true,
            ];
        }

        return $definitions;
    }

    /**
     * Collect the string-valued leaf keys of a nested array.
     *
     * Weglot's JSON checker matches on leaf key names at any depth, so the
     * per-provider nesting of wpsl_api_error_messages() flattens to one
     * de-duplicated list.
     *
     * @since  3.0.0
     * @param  array $data The nested array.
     * @return array The leaf key names.
     */
    public function collect_leaf_keys( array $data ) {
        $keys = [];

        foreach ( $data as $key => $value ) {
            if ( is_array( $value ) ) {
                $keys = array_merge( $keys, $this->collect_leaf_keys( $value ) );
            } elseif ( is_string( $value ) && is_string( $key ) ) {
                $keys[] = $key;
            }
        }

        return array_values( array_unique( $keys ) );
    }

    /**
     * Re-encode translated JSON the way wp_localize_script() would have.
     *
     * @since  3.0.0
     * @param  string $json_string The translated JSON.
     * @return string The re-encoded JSON, or the input when it doesn't decode.
     */
    public function reencode( $json_string ) {
        $decoded = json_decode( $json_string, true );

        if ( null === $decoded ) {
            return $json_string;
        }

        $encoded = wp_json_encode( $decoded, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );

        return is_string( $encoded ) ? $encoded : $json_string;
    }

    /**
     * Replace the underscore-template placeholders with opaque tokens.
     *
     * Runs as the checker's callback, before Weglot parses the JSON: the
     * <%= field %> and <% if (...) { %> runs in the section templates would
     * otherwise be collected as translatable words. The tokens contain no
     * alphabetic characters, so nothing about them looks like language.
     *
     * Works on the decoded values rather than the raw JSON text, because in
     * the page source the angle brackets are JSON_HEX_TAG escapes.
     *
     * @since  3.0.0
     * @param  string $json_string The matched JSON, straight from the page.
     * @return string The JSON with every placeholder tokenized.
     */
    public function mask_placeholders( $json_string ) {
        $decoded = json_decode( $json_string, true );

        if ( ! is_array( $decoded ) ) {
            return $json_string;
        }

        array_walk_recursive( $decoded, function ( &$value ) {
            if ( ! is_string( $value ) ) {
                return;
            }

            $replaced = preg_replace_callback( '#<%.*?%>#s', function ( $match ) {
                $token = '⟦' . count( $this->placeholders ) . '⟧';

                $this->placeholders[ $token ] = $match[0];

                return $token;
            }, $value );

            // preg_replace_callback() returns null on a PCRE error ( e.g. a
            // backtrack limit ), which would leave this string null in the payload.
            if ( is_string( $replaced ) ) {
                $value = $replaced;
            }
        } );

        $encoded = wp_json_encode( $decoded );

        // See reencode(): degrade to the input rather than emit a blank statement.
        return is_string( $encoded ) ? $encoded : $json_string;
    }

    /**
     * Restore the placeholders and the wp_localize_script() encoding.
     *
     * Runs as the checker's revert_callback, after translation and before
     * Weglot splices the result back into the page.
     *
     * @since  3.0.0
     * @param  string $json_string The translated, still-tokenized JSON.
     * @return string The JSON with placeholders and JSON_HEX_TAG restored.
     */
    public function unmask_placeholders( $json_string ) {
        $decoded = json_decode( $json_string, true );

        if ( ! is_array( $decoded ) ) {
            return $json_string;
        }

        array_walk_recursive( $decoded, function ( &$value ) {
            if ( is_string( $value ) ) {
                $value = strtr( $value, $this->placeholders );
            }
        } );

        $encoded = wp_json_encode( $decoded, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );

        // See reencode(): degrade to the input rather than emit a blank statement.
        return is_string( $encoded ) ? $encoded : $json_string;
    }
}