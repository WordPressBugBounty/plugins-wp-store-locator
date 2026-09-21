<?php
/**
 * Translation class
 *
 * @author Tijmen Smit
 * @since  2.0.0
 */

namespace WPSL\Core\I18n;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;

class Translations {

    /**
     * Currently active multilingual plugin
     *
     * @since 2.0.0
     * @var string|null
     */
    public $active_plugin = null;
            
    /**
     * Plugin settings
     *
     * @since 3.0.0
     * @var   array
     */
    private $settings;

    /**
     * Translations returned by the TranslatePress API, keyed by language and string.
     *
     * @since 3.0.0
     * @var   array
     */
    private $trp_translations = [];

    /**
     * The label defaults in the source language, or null when not read yet.
     *
     * @since 3.0.0
     * @var   array|null
     */
    private $source_labels = null;

    /**
     * Constructor.
     *
     * @since 2.0.0
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;

        add_action( 'init', [ $this, 'check_multilingual_plugins' ], 5 );
    }

    /**
     * See if we have support for the WPML API.
     *
     * Polylang for example also supports it
     *
     * @see  https://polylang.pro/doc/wpml-api/
     * @since 3.0.0
     */
    public function is_wpml_compatible() {
        return $this->active_plugin == 'wpml' || $this->active_plugin == 'polylang';
    }

    /**
     * The number of active languages.
     *
     * Used by the store search to decide how many extra rows to fetch: with a
     * multilingual plugin every translation of a store is its own row inside
     * the search radius, and the duplicates are removed after the query. Each
     * store occupies at most one row per language, so the language count is a
     * safe multiplication factor for the SQL limit.
     *
     * @since  3.0.0
     * @return int The language count, at least 1.
     */
    public function get_language_count() {
        $count = 0;

        switch ( $this->active_plugin ) {
            case 'wpml':
                $languages = apply_filters( 'wpml_active_languages', null );

                if ( is_array( $languages ) ) {
                    $count = count( $languages );
                }

                break;
            case 'polylang':
                if ( function_exists( 'pll_languages_list' ) ) {
                    $languages = pll_languages_list();

                    if ( is_array( $languages ) ) {
                        $count = count( $languages );
                    }
                }

                break;
        }

        return max( 1, (int) $count );
    }

    /**
     * Check which multilingual plugins are active.
     *
     * @since  3.0.0
     * @return void
     */
    public function check_multilingual_plugins() {
        if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
            $this->active_plugin = 'wpml';
        } else if ( defined( 'WEGLOT_VERSION' ) ) {
            $this->active_plugin = 'weglot';
        } else if ( class_exists( 'TRP_Translate_Press' ) ) {
            $this->active_plugin = 'translatepress';
        } else if ( class_exists( 'PLL_Admin' ) ) {
            $this->active_plugin = 'polylang';
        }
    }

    /**
     * Check if the Polylang API exists and can be loaded.
     *
     * @since  3.0.0
     * @return boolean
     */
    public function polylang_api_exists() {
        if ( ! defined( 'POLYLANG_VERSION' ) || ! defined( 'POLYLANG_DIR' ) ) {
            return false;
        }
        
        if ( function_exists( 'pll__' ) ) {
            return true;
        }
        
        $api_file = POLYLANG_DIR . '/src/api.php';
        
        if ( ! file_exists( $api_file ) ) {
            $api_file = POLYLANG_DIR . '/include/api.php';
        }
        
        if ( file_exists( $api_file ) ) {
            require_once $api_file;
            return function_exists( 'pll__' );
        }
        
        return false;
    }

    /**
     * Returns the current language used by
     * the active multilingual plugin.
     *
     * @since   2.0.0
     * @updated 3.0.0
     * @return  string Empty or the current language code
     */
    public function check_multilingual_code() {
        $language = '';

        switch ( $this->active_plugin ) {
            case 'wpml':
                $language = apply_filters( 'wpml_current_language', null );
                break;
            case 'weglot':
                if ( function_exists( 'weglot_get_current_language' ) ) {
                    /*
                     * weglot_get_current_language() is documented @throws, and
                     * dereferences null when Weglot's original language option
                     * is empty. An empty language degrades correctly everywhere
                     * this return value is consumed.
                     */
                    try {
                        $language = weglot_get_current_language();
                    } catch ( \Throwable $e ) {
                        $language = '';
                    }

                    if ( ! is_string( $language ) ) {
                        $language = '';
                    }
                }
                break;
            case 'translatepress':
                global $TRP_LANGUAGE;

                $language = $TRP_LANGUAGE;
                break;
            case 'polylang':
                if ( function_exists( 'pll_current_language' ) ) {
                    $language = pll_current_language();
                }
                break;
        }

        return apply_filters( 'wpsl_i18n_language_code', $language );
    }

    /**
     * The languages available in the active multilingual plugin,
     * as a code => display name map.
     *
     * Codes match what the section editor stores in the wpsl_themes
     * language column. Formats differ per plugin/version ( Polylang
     * reports 'fr_FR', older rows were 'fr-FR' ), so match through
     * Sections::normalize_lang() rather than the raw string.
     *
     * @since 3.0.0
     * @return array Language code => display name, empty when no plugin is active.
     */
    public function get_active_languages() {
        $languages = [];

        if ( $this->active_plugin === null ) {
            return $languages;
        }

        switch ( $this->active_plugin ) {
            case 'wpml':
                foreach ( (array) apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' ) as $language ) {
                    $languages[ $language['default_locale'] ] = $language['translated_name'];
                }

                break;
            case 'weglot':
                if ( function_exists( 'weglot_get_service' ) ) {
                    try {
                        $language_services = weglot_get_service( 'Language_Service_Weglot' );

                        $destination_languages = $language_services->get_destination_languages( true );
                        $original_languages = $language_services->get_languages_available( [ 'sort' => true ] );

                        // Hoisted: this doesn't change per row, and used to run once per available language.
                        $current_language = $this->check_multilingual_code();

                        foreach ( $original_languages as $language ) {
                            if ( $language->getInternalCode() == $current_language ) {
                                $languages[ $language->getInternalCode() ] = $language->getEnglishName();
                            }
                        }

                        foreach ( $destination_languages as $language ) {
                            $languages[ $language->getInternalCode() ] = $language->getEnglishName();
                        }
                    } catch ( \Throwable $e ) {
                        // A misconfigured Weglot install throws from its language
                        // service; no dropdown beats a fatal settings page.
                        $languages = [];
                    }
                }

                break;
            case 'translatepress':
                if ( function_exists( 'trp_custom_language_switcher' ) ) {
                    foreach ( (array) trp_custom_language_switcher() as $language ) {
                        $languages[ $language['language_code'] ] = $language['language_name'];
                    }
                }

                break;
            case 'polylang':
                if ( function_exists( 'pll_the_languages' ) ) {
                    foreach ( (array) pll_the_languages( [ 'raw' => 1 ] ) as $language ) {
                        $languages[ $language['locale'] ] = $language['name'];
                    }
                }
                
                break;
        }

        return $languages;
    }

    /**
     * Return a dropdown listing the available languages
     * created in the active multilingual plugin.
     *
     * @since  3.0.0
     * @return string $active_languages A dropdown with the available language, or an empty string is no plugin is active.
     */
    public function active_language_dropdown() {
        $languages = $this->get_active_languages();

        if ( empty( $languages ) ) {
            return '';
        }

        $option = '';

        foreach ( $languages as $code => $name ) {
            $option .= '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
        }

        return '<select id="wpsl-template-languages" class="wpsl-active-' . $this->active_plugin . '-languages">' . $option . '</select>';
    }
            
    /**
     * See if there is a translated page available for the provided store ID.
     * 
     * @since  2.0.0
     * @see    https://wpml.org/documentation/support/creating-multilingual-wordpress-themes/language-dependent-ids/#2
     * @param  string $store_id
     * @return string empty or the id of the translated store
     */
    public function maybe_get_wpml_id( $store_id ) {
        $return_original_id = apply_filters( 'wpsl_return_original_wpml_id', true );

        // icl_object_id is deprecated as of 3.2
        if ( defined( 'ICL_SITEPRESS_VERSION' ) && version_compare( ICL_SITEPRESS_VERSION, 3.2, '>=' ) ) {
            $translated_id = apply_filters( 'wpml_object_id', $store_id, 'wpsl_stores', $return_original_id, ICL_LANGUAGE_CODE );
        } else {
            $translated_id = icl_object_id( $store_id, 'wpsl_stores', $return_original_id, ICL_LANGUAGE_CODE );
        }

        // If '$return_original_id' is set to false, NULL is returned if no translation exists.
        if ( is_null( $translated_id ) ) {
            $translated_id = '';
        }
                    
        return $translated_id;
    }

    /**
     * Get the correct translation.
     *
     * Returns the label saved on the settings page, after any active
     * multilingual plugin had a chance to translate it: WPML/Polylang via
     * wpml-config.xml filtering the option value, Weglot/TranslatePress via
     * the rendered HTML.
     *
     * $text is only used as a fallback when no label was saved, or when the
     * saved label still matches its seed value -- never fed to a
     * translation lookup, since that translated the fallback instead of
     * the label whenever its default text differed from the stored value.
     *
     * @since  3.0.0
     * @param  string $name The name of value from the WPSL settings page ( label section )
     * @param  string $text The default text, used when no label was saved
     * @return string The translation
     */
    public function get_translation( $name, $text ) {
        $wpsl_settings = $this->settings->get_group( 'labels' );

        $value = isset( $wpsl_settings[$name] ) ? $wpsl_settings[$name] : '';

        if ( $value ) {
            $translation = wp_strip_all_tags(
                html_entity_decode(
                    stripslashes( $value ),
                    ENT_QUOTES
                )
            );
        } else {
            $translation = '';
        }

        /*
         * Activation seeds wpsl_labels with the defaults, so an unedited
         * label still has a value here that used to win over $text -- hiding
         * gettext translations ( language packs, plugins hooking gettext )
         * behind it. A label still matching its seed text is handed back to
         * gettext instead, except on the settings page: it shows the label
         * in an editable field written back on save, and a translation
         * shown there would be stored as the label itself, shadowing
         * gettext for every other language.
         */
        if ( $translation !== '' && ( ! is_admin() || wp_doing_ajax() ) && $translation === $this->get_source_label( $name ) ) {
            $translation = '';
        }

        if ( ! $translation ) {
            $translation = $text;
        }

        return $translation;
    }

    /**
     * The text a label was seeded with, in the source language.
     *
     * Defaults are built with gettext calls, so on a translated site they come
     * back translated. Reading them with translations filtered out gives the
     * text update_option() wrote on activation - which tells an untouched label
     * from one the site owner typed.
     *
     * @since  3.0.0
     * @param  string $name The name of value from the WPSL settings page ( label section )
     * @return string The untranslated default, or an empty string for an unknown label
     */
    private function get_source_label( $name ) {
        if ( $this->source_labels === null ) {
            $skip_translation = function( $translation, $text ) {
                return $text;
            };

            add_filter( 'gettext', $skip_translation, PHP_INT_MAX, 2 );
            $this->source_labels = (array) $this->settings->defaults( 'labels' );
            remove_filter( 'gettext', $skip_translation, PHP_INT_MAX );
        }

        return isset( $this->source_labels[$name] ) && is_string( $this->source_labels[$name] ) ? $this->source_labels[$name] : '';
    }

    /**
     * Get a label for output that never reaches the rendered page.
     *
     * Page-translating plugins ( Weglot, TranslatePress ) only see labels that
     * end up in template HTML. Labels handed to wp_localize_script() and the
     * JS-rendered section templates do not, so they stayed in the default
     * language; this asks the multilingual plugin for the translation directly.
     *
     * Only needed for a label the site owner typed: when $text comes back
     * unchanged it is a gettext string TranslatePress already translates via
     * the gettext filter, and adding it to the string list would file it as a
     * new source string.
     *
     * @since  3.0.0
     * @see    self::get_translation() For the normal, rendered-HTML path.
     * @param  string $name The name of value from the WPSL settings page ( label section )
     * @param  string $text The default text, used when no label was saved
     * @return string The translation
     */
    public function get_js_translation( $name, $text ) {
        $translation = $this->get_translation( $name, $text );

        if ( $translation === $text ) {
            return $translation;
        }

        return $this->translate_dynamic_string( $translation );
    }

    /**
     * Translate a string that isn't part of the rendered page.
     *
     * Runs the string through the TranslatePress API against the same string
     * list the Translation Editor writes to, adding it if not already there.
     * Other multilingual plugins get it back unchanged: WPML/Polylang already
     * translate the label via wpml-config.xml before it arrives here.
     *
     * @since  3.0.0
     * @param  string $text The string in the default language
     * @return string The translation, or $text if it can't be translated
     */
    public function translate_dynamic_string( $text ) {
        if ( ! $text || $this->active_plugin !== 'translatepress' || ! function_exists( 'trp_translate' ) ) {
            return $text;
        }

        /*
         * The settings page shows the saved label in an input field, and that
         * value is written back when the settings are saved. Translating it
         * there would replace the label with one of its translations.
         */
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $text;
        }

        $language = $this->check_multilingual_code();

        if ( ! $language || $language === $this->get_default_language() ) {
            return $text;
        }

        $cache_key = $language . '|' . $text;

        if ( ! isset( $this->trp_translations[$cache_key] ) ) {
            /*
             * The last argument stops trp_translate() from wrapping its return
             * value in a <span data-no-translation> element, which can't be
             * used inside a JavaScript string or an HTML attribute.
             */
            $this->trp_translations[$cache_key] = trp_translate( $text, $language, false );
        }

        return $this->trp_translations[$cache_key];
    }

    /**
     * The default language of the active multilingual plugin.
     *
     * Used to skip the translation calls on the pages that are already in the
     * default language.
     *
     * @since  3.0.0
     * @return string Empty, or the default language code
     */
    private function get_default_language() {
        $language = '';

        if ( $this->active_plugin === 'translatepress' ) {
            $trp_settings = get_option( 'trp_settings', [] );

            if ( isset( $trp_settings['default-language'] ) ) {
                $language = $trp_settings['default-language'];
            }
        }

        return apply_filters( 'wpsl_i18n_default_language_code', $language );
    }

    /**
     * Get a label by its settings name, escaped for template output.
     *
     * Backs the {{wpsl_label( '...' )}} template tag; the value is substituted
     * straight into the section template HTML, so it's escaped here.
     *
     * An emptied label stays empty - clearing it is how its text gets left out
     * of a template, so it must not fall back to the default. A label that was
     * never edited does get the default, as a gettext string, so language packs
     * can translate it.
     *
     * @since  3.0.0
     * @param  string $name The name of value from the WPSL settings page ( label section )
     * @return string The escaped label, or an empty string
     */
    public function get_label( $name ) {
        $name   = sanitize_key( $name );
        $labels = $this->settings->get_group( 'labels' );

        if ( empty( $labels[$name] ) || ! is_string( $labels[$name] ) ) {
            return '';
        }

        $defaults = $this->settings->defaults( 'labels' );
        $default  = isset( $defaults[$name] ) && is_string( $defaults[$name] ) ? $defaults[$name] : '';

        return esc_html( $this->get_js_translation( $name, $default ) );
    }

    /**
     * Get the 'please adjust your search' sentence.
     *
     * Kept in one place so the front-end, the settings page and the
     * onboarding all show the exact same text.
     *
     * @since  3.0.0
     * @return string The translated sentence.
     */
    public function get_adjust_search_text() {
        return esc_html__( 'Please adjust your search and try again.', 'wp-store-locator' );
    }

    /**
     * Get the full 'no results found' message.
     *
     * The first sentence is the configurable (and therefore translatable) no
     * results label, followed by the request to adjust the search.
     *
     * @since  3.0.0
     * @return string The message, containing HTML line breaks.
     */
    public function get_no_results_message() {
        return sprintf(
            '%1$s<br><br>%2$s',
            $this->get_js_translation( 'no_results_label', esc_html__( 'No results found.', 'wp-store-locator' ) ),
            $this->get_adjust_search_text()
        );
    }

    /**
     * Get the full 'no directions found' message.
     *
     * Same idea as get_no_results_message(), but for the directions.
     *
     * @since  3.0.0
     * @return string The message, containing HTML line breaks.
     */
    public function get_no_directions_message() {
        return sprintf(
            '%1$s<br><br>%2$s',
            $this->get_js_translation( 'no_directions_label', esc_html__( 'No route found between the origin and destination.', 'wp-store-locator' ) ),
            $this->get_adjust_search_text()
        );
    }

    /**
     * Whether a search-bar label should be rendered.
     *
     * Reads the per-label show/hide flag from the labels group. A missing flag
     * defaults to true so installs predating this feature keep every label.
     *
     * @since  3.0.0
     * @param  string $key One of: search, search_name, radius, results, category
     * @return bool True when the label should render.
     */
    public function is_label_visible( $key ) {
        $labels = $this->settings->get_group( 'labels' );

        if ( isset( $labels['visibility'][ $key ] ) ) {
            return (bool) $labels['visibility'][ $key ];
        }

        return true;
    }

    /**
     * If no label value is set, then don't include
     * the label HTML output in the template.
     *
     * @since  3.0.0
     * @param  string $type The label type
     * @return string $html The HTML for the label
     */
    public function get_template_label( $type ) {
        $html   = '';
        $labels = [
            'search_label'      => esc_html__( 'Your location', 'wp-store-locator' ),
            'search_name_label' => esc_html__( 'Store name', 'wp-store-locator' ),
            'radius_label'      => esc_html__( 'Search radius', 'wp-store-locator' ),
            'results_label'     => esc_html__( 'Results', 'wp-store-locator' ),
        ];

        $label = $this->get_translation( $type, $labels[$type] );

        // Only return the label HTML if the label value exists.
        if ( $label ) {
            switch( $type ) {
                case 'search_label':
                case 'search_name_label':
                    $html = '<div><label for="wpsl-search-input">' . esc_html( $label ) . '</label></div>';
                    break;
                case 'radius_label':
                    $html = '<label for="wpsl-radius-dropdown">' . esc_html( $label ) . '</label>';
                    break;
                case 'results_label':
                    $html = '<label for="wpsl-results-dropdown">' . esc_html( $label ) . '</label>';
                    break;
            }
        }

        return $html;
    }

    /**
     * Check if WPML is active
     *
     * @since 2.0.0
     * @deprecated 3.0.0
     * @return void
     */
    private function wpml_exists() {
        _deprecated_function( __FUNCTION__, '3.0.0', '$wpsl->i18n->active_plugin == wpml' );
    }

    /**
     * Check if a qTranslate compatible plugin is active.
     *
     * @since 2.0.0
     * @deprecated 3.0.0 / 8 years since last update and removed from wordpress.org for security reasons.
     * @return void
     */
    private function qtrans_exists() {
        _deprecated_function( __FUNCTION__, '3.0.0' );
    }
}