<?php
/**
 * Quick setup
 *
 * The six choices that decide what kind of store locator a site has: how
 * visitors search, which countries, whether to locate them, a page for every
 * location, the layout and the search filters. Reads them from the settings,
 * saves one choice at a time ( the Home card and the setup wizard call this
 * on every pick ), and turns them into the summary rows of the collapsed card.
 *
 * A save merges the single value into the stored settings group through
 * Core\Settings\Manager::update(). It never runs the full settings form
 * sanitizer, which would reset every checkbox that isn't in the request.
 *
 * @author Tijmen Smit
 * @since  3.1.1
 */

namespace WPSL\Admin\Home;

use WPSL\Core\Settings\Manager as WpslSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Quick_Setup {

    /**
     * The user option that holds the state of the Home card. It is stored per
     * site, so on a multisite network every site keeps its own card state.
     *
     * @since 3.1.1
     */
    const USER_META = 'wpsl_quick_setup';

    /**
     * Nonce action behind the AJAX calls.
     *
     * @since 3.1.1
     */
    const NONCE = 'wpsl-quick-setup';

    /**
     * AJAX action that saves one answer.
     *
     * @since 3.1.1
     */
    const SAVE_ACTION = 'wpsl_quick_setup_save';

    /**
     * AJAX action that stores the state of the Home card.
     *
     * @since 3.1.1
     */
    const STATE_ACTION = 'wpsl_quick_setup_state';

    /**
     * AJAX action that flushes the rewrite rules, called after a save
     * that asked for it so the flush runs on a fresh request.
     *
     * @since 3.1.1
     */
    const FLUSH_ACTION = 'wpsl_quick_setup_flush';

    /**
     * The questions, in the order they are shown.
     *
     * @since 3.1.1
     */
    const QUESTIONS = [ 'method', 'countries', 'locate', 'pages', 'layout', 'filters' ];

    /**
     * The most countries the search can be limited to.
     *
     * @since 3.1.1
     */
    const MAX_COUNTRIES = 15;

    /**
     * The states of the Home card.
     *
     * @since 3.1.1
     */
    const STATES = [ 'open', 'collapsed', 'hidden' ];

    /**
     * The site option with the state of a user who never changed it. Set to
     * hidden for a site updated from before 3.1.1, missing on a new install
     * ( open, and the setup wizard collapses it ).
     *
     * @since 3.1.1
     */
    const DEFAULT_STATE_OPTION = 'wpsl_quick_setup_default';

    /**
     * The settings that change which locations the page load returns, or in
     * what order. The cached page load results aren't keyed on them, so a
     * change clears that cache.
     *
     * @since 3.1.1
     */
    const CACHE_FIELDS = [
        'api'    => [ 'multiple_regions', 'region_restriction_type' ],
        'search' => [ 'search_method', 'orderby' ],
    ];

    /**
     * The icon and hover colors of every question, the same pair the Home
     * stat tiles use.
     *
     * @since 3.1.1
     */
    const COLORS = [
        'method'    => [ '#2563eb', '#eff6ff' ],
        'countries' => [ '#0d9488', '#f0fdfa' ],
        'locate'    => [ '#9333ea', '#faf5ff' ],
        'pages'     => [ '#d97706', '#fffbeb' ],
        'layout'    => [ '#16a34a', '#f0fdf4' ],
        'filters'   => [ '#db2777', '#fdf2f8' ],
    ];

    /**
     * @since 3.1.1
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Class constructor
     *
     * @since 3.1.1
     * @param \WPSL\Core\Settings\Manager $settings Settings manager instance
     */
    public function __construct( WpslSettings $settings ) {
        $this->settings = $settings;

        add_action( 'wp_ajax_' . self::SAVE_ACTION, [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_' . self::STATE_ACTION, [ $this, 'ajax_state' ] );
        add_action( 'wp_ajax_' . self::FLUSH_ACTION, [ $this, 'ajax_flush' ] );
    }

    /**
     * The current answers.
     *
     * @since  3.1.1
     * @return array The answers, see the keys below.
     */
    public function get_answers() {
        $search  = $this->settings->get_group( 'search' );
        $locate  = 'off';

        if ( ! empty( $search['auto_locate'] ) ) {
            $locate = ( 'user_request' === $search['auto_locate_trigger'] ) ? 'button' : 'pageload';
        }

        $template = (string) $this->settings->get( 'appearance', 'template_id', 'default' );

        return [
            'method'    => ( 'name' === $search['search_method'] ) ? 'name' : 'address',
            'countries' => $this->get_countries(),
            'locate'    => $locate,
            'pages'     => (bool) $this->settings->get( 'local_pages', 'permalinks' ),
            'layout'    => ( '' !== $template ) ? $template : 'default',
            'filters'   => [
                'categories' => ! empty( $search['category_filter'] ),
                'radius'     => ! empty( $search['radius_dropdown'] ),
                'results'    => ! empty( $search['results_dropdown'] ),
            ],
        ];
    }

    /**
     * The layouts the Layout question offers: the templates that have a
     * thumbnail, and the active one even when it has none, so the current
     * answer is always on the list. Templates that other code adds through
     * the wpsl_templates filter without a placeholder image are left out;
     * saving still accepts any registered template id.
     *
     * @since  3.1.1
     * @return array Template id => template, in the order of wpsl_get_templates().
     */
    public function get_layout_choices() {
        $active  = $this->get_answers()['layout'];
        $choices = [];

        foreach ( wpsl_get_templates() as $template ) {
            if ( ! empty( $template['placeholder'] ) || $template['id'] === $active ) {
                $choices[ $template['id'] ] = $template;
            }
        }

        return $choices;
    }

    /**
     * The one-line notes shown under a question, an answer or a filter.
     *
     * @since  3.1.1
     * @return array Question id ( and filter_categories, filter_radius,
     *               filter_results ) => note, an empty string when there is
     *               nothing to say.
     */
    public function get_notes() {
        $answers = $this->get_answers();
        $notes   = array_fill_keys( [ 'method', 'countries', 'locate', 'pages', 'layout', 'filters', 'filter_categories', 'filter_radius', 'filter_results' ], '' );

        $name_note = __( 'Not used with a store name search.', 'wp-store-locator' );

        if ( $this->is_name_search() ) {
            $notes['countries']     = $name_note;
            $notes['locate']        = $name_note;
            $notes['filter_radius'] = $name_note;
        } elseif ( 'off' !== $answers['locate'] ) {
            $notes['locate'] = __( 'The browser asks the visitor first, and only on a site with HTTPS.', 'wp-store-locator' );
        }

        if ( $this->has_out_of_sync_link( $answers['layout'] ) ) {
            $notes['pages'] = __( 'Your customized result section doesn\'t match this yet.', 'wp-store-locator' );
        }

        if ( ! $this->has_categories() ) {
            $notes['filter_categories'] = __( 'You have no categories yet.', 'wp-store-locator' );
        }

        if ( $this->is_disabled( 'filter_results' ) ) {
            $notes['filter_results'] = __( 'The Vertical layout has no results dropdown.', 'wp-store-locator' );
        }

        return $notes;
    }

    /**
     * Whether an answer, or a filter, doesn't apply to the current setup.
     *
     * @since  3.1.1
     * @param  string $question A question id, or filter_radius / filter_results.
     * @return bool
     */
    public function is_disabled( $question ) {
        if ( in_array( $question, [ 'countries', 'locate', 'filter_radius' ], true ) ) {
            return $this->is_name_search();
        }

        if ( 'filter_results' === $question ) {
            return 'vertical' === $this->settings->get( 'appearance', 'template_id', 'default' );
        }

        return false;
    }

    /**
     * The six rows of the collapsed card.
     *
     * @since  3.1.1
     * @return array Rows in the order of the questions, each with the keys
     *               id, label, value ( plain text, escape it on output ),
     *               ink and tint.
     */
    public function get_summary() {
        $answers = $this->get_answers();
        $not_used = __( 'Not used with a store name search', 'wp-store-locator' );
        $name_search = $this->is_name_search();

        $values = [
            'method'    => ( 'name' === $answers['method'] ) ? __( 'By store name', 'wp-store-locator' ) : __( 'By address or city', 'wp-store-locator' ),
            'countries' => $name_search ? $not_used : $this->summarize_countries( $answers['countries'] ),
            'locate'    => $name_search ? $not_used : $this->summarize_locate( $answers['locate'] ),
            'pages'     => $this->summarize_pages( $answers['pages'] ),
            'layout'    => $this->get_template_name( $answers['layout'] ),
            'filters'   => $this->summarize_filters( $answers['filters'] ),
        ];

        $labels = [
            'method'    => __( 'Search', 'wp-store-locator' ),
            'countries' => __( 'Countries', 'wp-store-locator' ),
            'locate'    => __( 'Find visitors', 'wp-store-locator' ),
            'pages'     => __( 'Location pages', 'wp-store-locator' ),
            'layout'    => __( 'Layout', 'wp-store-locator' ),
            'filters'   => __( 'Filters', 'wp-store-locator' ),
        ];

        $rows = [];

        foreach ( self::QUESTIONS as $id ) {
            $rows[] = [
                'id'    => $id,
                'label' => $labels[ $id ],
                'value' => $values[ $id ],
                'ink'   => self::COLORS[ $id ][0],
                'tint'  => self::COLORS[ $id ][1],
            ];
        }

        return $rows;
    }

    /**
     * Save the answer to one question.
     *
     * @since  3.1.1
     * @param  string $question One of the QUESTIONS.
     * @param  mixed  $value    The answer, see the save_* methods.
     * @return array|\WP_Error  The new answers, summary and notes, and whether
     *                          the rewrite rules need a flush on a fresh request.
     */
    public function save( $question, $value ) {
        if ( ! in_array( $question, self::QUESTIONS, true ) ) {
            return $this->invalid();
        }

        if ( $this->is_disabled( $question ) ) {
            $notes = $this->get_notes();

            return new \WP_Error( 'wpsl_quick_setup_disabled', $notes[ $question ] );
        }

        switch ( $question ) {
            case 'method':
                $result = $this->save_method( $value );
                break;
            case 'countries':
                $result = $this->save_countries( $value );
                break;
            case 'locate':
                $result = $this->save_locate( $value );
                break;
            case 'pages':
                $result = $this->save_pages( $value );
                break;
            case 'layout':
                $result = $this->save_layout( $value );
                break;
            default:
                $result = $this->save_filters( $value );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'answers' => $this->get_answers(),
            'summary' => $this->get_summary(),
            'notes'   => $this->get_notes(),
            'flush'   => ( 'pages' === $question ),
        ];
    }

    /**
     * The state of the Home card for a user.
     *
     * @since  3.1.1
     * @param  int    $user_id The user ID.
     * @return string open, collapsed or hidden. Without a saved state the site's default, open unless it was updated from before 3.1.1.
     */
    public function get_state( $user_id ) {
        $state = get_user_option( self::USER_META, (int) $user_id );

        if ( in_array( $state, self::STATES, true ) ) {
            return $state;
        }

        $default = get_option( self::DEFAULT_STATE_OPTION );

        return in_array( $default, self::STATES, true ) ? $default : 'open';
    }

    /**
     * Store the state of the Home card for a user.
     *
     * @since  3.1.1
     * @param  int    $user_id The user ID.
     * @param  string $state   open, collapsed or hidden.
     * @return bool   False for an unknown state or user, or a failed write.
     */
    public function set_state( $user_id, $state ) {
        $user_id = (int) $user_id;

        if ( $user_id < 1 || ! in_array( $state, self::STATES, true ) ) {
            return false;
        }

        $result = update_user_option( $user_id, self::USER_META, $state );

        // update_user_option() returns false when the stored value is already the same.
        return false !== $result || get_user_option( self::USER_META, $user_id ) === $state;
    }

    /**
     * What the browser script needs: the AJAX address, the nonce, the three
     * actions and the strings. The Home page and the last step of the setup
     * wizard both localize this as wpslQuickSetup. The strings are plain
     * text, the script puts them on the page with .text(), so they are not
     * escaped here.
     *
     * @since  3.1.1
     * @return array
     */
    public function get_script_data() {
        return [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( self::NONCE ),
            'maxCountries' => self::MAX_COUNTRIES,
            'actions'      => [
                'save'  => self::SAVE_ACTION,
                'state' => self::STATE_ACTION,
                'flush' => self::FLUSH_ACTION,
            ],
            'i18n'         => [
                /* translators: %s: the short name of the question the card shows, for example "Search" or "Location pages" */
                'saved'        => __( '%s saved', 'wp-store-locator' ),
                'error'        => __( 'The choice could not be saved. Please reload the page and try again.', 'wp-store-locator' ),
                'flushError'   => __( 'Saved, but the page addresses could not be refreshed yet. Open the Settings page once to finish.', 'wp-store-locator' ),
                /* translators: %d: the number of countries */
                'countriesMax' => sprintf( _n( 'You can choose up to %d country.', 'You can choose up to %d countries.', self::MAX_COUNTRIES, 'wp-store-locator' ), self::MAX_COUNTRIES ),
            ],
        ];
    }

    /**
     * AJAX: save one answer.
     *
     * POST: nonce, question, value ( a string; pages is '0' or '1', countries
     * an array of codes that may be absent, filters an array of '0' / '1' ).
     *
     * @since  3.1.1
     * @return void
     */
    public function ajax_save() {
        $this->verify_ajax_request();

        $question = isset( $_POST['question'] ) ? sanitize_key( wp_unslash( $_POST['question'] ) ) : '';
        $value    = isset( $_POST['value'] ) ? map_deep( wp_unslash( $_POST['value'] ), 'sanitize_text_field' ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- map_deep() sanitizes it, the nonce is checked in verify_ajax_request().

        if ( 'countries' === $question && null === $value ) {
            $value = [];
        }

        $result = $this->save( $question, $value );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: store the state of the Home card for the current user.
     *
     * POST: nonce, state.
     *
     * @since  3.1.1
     * @return void
     */
    public function ajax_state() {
        $this->verify_ajax_request();

        $state = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';

        if ( ! $this->set_state( get_current_user_id(), $state ) ) {
            wp_send_json_error( [ 'message' => __( 'That state is not available.', 'wp-store-locator' ) ], 400 );
        }

        wp_send_json_success( [ 'state' => $state ] );
    }

    /**
     * AJAX: flush the rewrite rules after the landing pages were switched.
     *
     * If the person left before this request went out, the next load of the
     * Home page or the setup wizard does the same ( System_Utils::flush_pending() ),
     * and this one finds nothing left to do.
     *
     * @since  3.1.1
     * @return void
     */
    public function ajax_flush() {
        $this->verify_ajax_request();

        wpsl_get_service( 'system_utils' )->flush_pending();

        wp_send_json_success();
    }

    /**
     * The nonce and capability checks every AJAX handler starts with.
     *
     * @since  3.1.1
     * @return void
     */
    private function verify_ajax_request() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) ], 403 );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'wp-store-locator' ) ], 403 );
        }
    }

    /**
     * @since  3.1.1
     * @return \WP_Error
     */
    private function invalid() {
        return new \WP_Error( 'wpsl_quick_setup_invalid', __( 'That answer is not available.', 'wp-store-locator' ) );
    }

    /**
     * @since  3.1.1
     * @param  mixed $value 'address' or 'name'.
     * @return true|\WP_Error
     */
    private function save_method( $value ) {
        if ( ! in_array( $value, [ 'address', 'name' ], true ) ) {
            return $this->invalid();
        }

        $changes = [ 'search_method' => ( 'name' === $value ) ? 'name' : 'geocode' ];

        /*
         * A store name search has no distance to sort by. The Settings page
         * shows the first other option instead ( Admin\Settings\UI ), so does
         * this. Switching back to an address search leaves the sort as it is,
         * like the Settings page.
         */
        if ( 'name' === $value && 'distance' === $this->settings->get( 'search', 'orderby' ) ) {
            $other = key( array_diff_key( wpsl_get_search_order_options(), [ 'distance' => true ] ) );

            if ( null !== $other ) {
                $changes['orderby'] = $other;
            }
        }

        $this->write( 'search', $changes );

        return true;
    }

    /**
     * Choosing countries also makes the list a hard restriction, a Google
     * Maps "bias" is not offered here.
     *
     * @since  3.1.1
     * @param  mixed $value An array of country codes, [] for anywhere.
     * @return true|\WP_Error
     */
    private function save_countries( $value ) {
        if ( '' === $value ) {
            $value = [];
        }

        if ( ! is_array( $value ) ) {
            return $this->invalid();
        }

        $valid = array_filter( array_values( wpsl_get_regions() ) );
        $codes = [];

        foreach ( $value as $code ) {
            if ( ! is_string( $code ) ) {
                continue;
            }

            $code = strtolower( trim( $code ) );

            if ( in_array( $code, $valid, true ) && ! in_array( $code, $codes, true ) ) {
                $codes[] = $code;
            }
        }

        if ( $value && ! $codes ) {
            return $this->invalid();
        }

        $codes = array_slice( $codes, 0, self::MAX_COUNTRIES );

        /*
         * Anywhere also puts the type back to the default. Choosing countries
         * made it 'restrict', and Google Maps would go on limiting the search
         * to the country it still has for the bias.
         */
        if ( ! $codes ) {
            $this->write( 'api', [
                'multiple_regions'        => '',
                'region_restriction_type' => 'bias',
            ] );

            return true;
        }

        $this->write( 'api', [
            'multiple_regions'        => $codes,
            'region_restriction_type' => 'restrict',
        ] );

        return true;
    }

    /**
     * Turning it off keeps the trigger, so the next time it is switched on
     * the visitor-facing behavior is the one chosen before.
     *
     * @since  3.1.1
     * @param  mixed $value off, pageload or button.
     * @return true|\WP_Error
     */
    private function save_locate( $value ) {
        $changes = [];

        if ( 'off' === $value ) {
            $changes['auto_locate'] = 0;
        } elseif ( 'pageload' === $value || 'button' === $value ) {
            $changes['auto_locate']         = 1;
            $changes['auto_locate_trigger'] = ( 'button' === $value ) ? 'user_request' : 'pageload';
        } else {
            return $this->invalid();
        }

        $this->write( 'search', $changes );

        return true;
    }

    /**
     * The rewrite rules are flushed by the next request ( ajax_flush(), or
     * the settings page ), the cached search results are cleared right away,
     * both like the settings page does after a change.
     *
     * @since  3.1.1
     * @param  mixed $value A boolean, or '0' / '1'.
     * @return true|\WP_Error
     */
    private function save_pages( $value ) {
        $on = $this->to_bool( $value );

        if ( null === $on ) {
            return $this->invalid();
        }

        // Nothing changes, so no rewrite flush and no cache clearing either.
        if ( $on === (bool) $this->settings->get( 'local_pages', 'permalinks' ) ) {
            return true;
        }

        $this->write( 'local_pages', [ 'permalinks' => $on ? 1 : 0 ] );

        update_option( 'wpsl_flush_rewrite', 1, 'no' );
        update_option( 'wpsl_delete_transient', 1, 'no' );
        wpsl_get_service( 'system_utils' )->flush_autoload_transients();

        return true;
    }

    /**
     * Stores what Appearance\Manager::activate_template() stores.
     *
     * @since  3.1.1
     * @param  mixed $value A template id.
     * @return true|\WP_Error
     */
    private function save_layout( $value ) {
        if ( ! is_string( $value ) || ! in_array( $value, wp_list_pluck( wpsl_get_templates(), 'id' ), true ) ) {
            return $this->invalid();
        }

        $this->write( 'appearance', [ 'template_id' => $value ] );

        return true;
    }

    /**
     * A filter that doesn't apply ( the radius under a name search, the
     * results under the vertical layout ) or that isn't in the value keeps
     * what is stored.
     *
     * @since  3.1.1
     * @param  mixed $value [ 'categories' => bool, 'radius' => bool, 'results' => bool ].
     * @return true|\WP_Error
     */
    private function save_filters( $value ) {
        if ( ! is_array( $value ) ) {
            return $this->invalid();
        }

        $keys = [
            'categories' => 'category_filter',
            'radius'     => 'radius_dropdown',
            'results'    => 'results_dropdown',
        ];

        $changes = [];

        foreach ( $keys as $filter => $setting ) {
            if ( ! array_key_exists( $filter, $value ) || $this->is_disabled( 'filter_' . $filter ) ) {
                continue;
            }

            $on = $this->to_bool( $value[ $filter ] );

            if ( null === $on ) {
                return $this->invalid();
            }

            $changes[ $setting ] = $on ? 1 : 0;
        }

        if ( $changes ) {
            $this->write( 'search', $changes );
        }

        return true;
    }

    /**
     * Merge values into a settings group and store it.
     *
     * The search group is merged into the raw option. Reading it through the
     * manager forces the radius dropdown and the nearest location fallback to
     * 0 under a name search, writing that back would lose the visitor's choice
     * for when they switch back to an address search.
     *
     * @since  3.1.1
     * @param  string $group   The settings group.
     * @param  array  $changes Setting => value.
     * @return void
     */
    private function write( $group, $changes ) {
        if ( 'search' === $group ) {
            $stored = get_option( 'wpsl_search', [] );
            $stored = is_array( $stored ) ? $stored : [];
        } else {
            $stored = $this->settings->get_group( $group );
        }

        $clears_cache = false;

        foreach ( isset( self::CACHE_FIELDS[ $group ] ) ? self::CACHE_FIELDS[ $group ] : [] as $field ) {
            if ( array_key_exists( $field, $changes ) && ( ! isset( $stored[ $field ] ) || $stored[ $field ] != $changes[ $field ] ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Stored values can be strings or ints.
                $clears_cache = true;
            }
        }

        $this->settings->update( $group, array_merge( $stored, $changes ) );

        if ( $clears_cache ) {
            wpsl_get_service( 'system_utils' )->flush_autoload_transients();
        }
    }

    /**
     * @since  3.1.1
     * @param  mixed $value A boolean, 0 / 1 or '0' / '1'.
     * @return bool|null    Null when it isn't one.
     */
    private function to_bool( $value ) {
        if ( ! is_scalar( $value ) ) {
            return null;
        }

        return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
    }

    /**
     * @since  3.1.1
     * @return bool
     */
    private function is_name_search() {
        return 'name' === $this->settings->get( 'search', 'search_method' );
    }

    /**
     * The countries the search is limited to.
     *
     * Google Maps only limits the search in the 'restrict' mode, in the 'bias'
     * mode the list is a soft preference, which counts as anywhere here.
     *
     * @since  3.1.1
     * @return string[] Lowercase country codes, [] for anywhere.
     */
    private function get_countries() {
        $api = $this->settings->get_group( 'api' );

        if ( 'gmaps' === $api['active_map_service'] && 'restrict' !== $api['region_restriction_type'] ) {
            return [];
        }

        if ( empty( $api['multiple_regions'] ) || ! is_array( $api['multiple_regions'] ) ) {
            return [];
        }

        return array_values( array_filter( array_map( 'strtolower', array_map( 'sanitize_text_field', $api['multiple_regions'] ) ) ) );
    }

    /**
     * @since  3.1.1
     * @param  string[] $codes Country codes.
     * @return string
     */
    private function summarize_countries( $codes ) {
        if ( ! $codes ) {
            return __( 'Anywhere', 'wp-store-locator' );
        }

        $names = array_flip( wpsl_get_regions() );
        $shown = [];

        foreach ( array_slice( $codes, 0, 3 ) as $code ) {
            $shown[] = isset( $names[ $code ] ) ? html_entity_decode( $names[ $code ], ENT_QUOTES, 'UTF-8' ) : strtoupper( $code );
        }

        $list = implode( ', ', $shown );

        if ( count( $codes ) > 3 ) {
            $more = count( $codes ) - 3;

            /* translators: 1: the first three country names, 2: the number of other countries */
            return sprintf( _n( '%1$s and %2$d more', '%1$s and %2$d more', $more, 'wp-store-locator' ), $list, $more );
        }

        return $list;
    }

    /**
     * @since  3.1.1
     * @param  string $locate off, pageload or button.
     * @return string
     */
    private function summarize_locate( $locate ) {
        if ( 'pageload' === $locate ) {
            return __( 'As the page opens', 'wp-store-locator' );
        }

        if ( 'button' === $locate ) {
            return __( 'With a locate button', 'wp-store-locator' );
        }

        return __( 'Off', 'wp-store-locator' );
    }

    /**
     * @since  3.1.1
     * @param  bool $on Whether the landing pages are on.
     * @return string
     */
    private function summarize_pages( $on ) {
        if ( ! $on ) {
            return __( 'Off', 'wp-store-locator' );
        }

        /* translators: %s: the landing page url slug */
        return sprintf( __( 'On, at /%s/', 'wp-store-locator' ), $this->settings->get( 'local_pages', 'permalink_slug' ) );
    }

    /**
     * Only the filters that are on, and that apply, are listed.
     *
     * @since  3.1.1
     * @param  array $filters The filters answer.
     * @return string
     */
    private function summarize_filters( $filters ) {
        $labels = [
            'categories' => __( 'Categories', 'wp-store-locator' ),
            'radius'     => __( 'search radius', 'wp-store-locator' ),
            'results'    => __( 'number of results', 'wp-store-locator' ),
        ];

        $on = [];

        foreach ( $labels as $filter => $label ) {
            if ( $filters[ $filter ] && ! $this->is_disabled( 'filter_' . $filter ) ) {
                $on[] = $label;
            }
        }

        return $on ? implode( ', ', $on ) : __( 'None', 'wp-store-locator' );
    }

    /**
     * @since  3.1.1
     * @param  string $template_id A template id.
     * @return string The template name as plain text, the id for an unknown template.
     */
    private function get_template_name( $template_id ) {
        foreach ( wpsl_get_templates() as $template ) {
            if ( $template['id'] === $template_id ) {
                return html_entity_decode( $template['name'], ENT_QUOTES, 'UTF-8' );
            }
        }

        return $template_id;
    }

    /**
     * Whether the customized result section of the active template still
     * links the store name the old way after the landing pages were switched.
     *
     * @since  3.1.1
     * @param  string $template_id The active template.
     * @return bool   False as well when the section analyzer isn't available.
     */
    private function has_out_of_sync_link( $template_id ) {
        if ( ! wpsl_container()->has( 'section_analyzer' ) ) {
            return false;
        }

        return wpsl_get_service( 'section_analyzer' )->has_out_of_sync_feature( $template_id, 'landing_page_link' );
    }

    /**
     * @since  3.1.1
     * @return bool Whether the site has a store category.
     */
    private function has_categories() {
        $count = wp_count_terms( [ 'taxonomy' => 'wpsl_store_category', 'hide_empty' => false ] );

        return ! is_wp_error( $count ) && (int) $count > 0;
    }
}
