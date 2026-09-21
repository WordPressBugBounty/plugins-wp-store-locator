<?php
/**
 * Home page data
 *
 * Assembles the checklist, the counters and the quick links. Returns arrays
 * and nothing else - templates/home.php does the printing, and every number
 * here comes from a helper that already exists somewhere in the plugin.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Home;

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Markers\Custom_Markers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Home {

    /**
     * Caches only the "yes, the locator is on a page" answer.
     *
     * @since 3.0.0
     */
    const PLACED_TRANSIENT = 'wpsl_home_placed';

    /**
     * Set once the setup checklist has been dismissed.
     *
     * @since 3.0.0
     */
    const DISMISSED_OPTION = 'wpsl_home_setup_dismissed';

    /**
     * Nonce actions behind the checklist's two controls.
     *
     * @since 3.0.0
     */
    const DISMISS_ACTION     = 'wpsl-hide-setup';
    const CREATE_PAGE_ACTION = 'wpsl-create-locator-page';

    /**
     * Creating the page over AJAX, and ticking the page step by hand.
     *
     * The shortcode search only sees a published page or post. A locator put
     * on a draft, in a template, a widget or a builder's own storage is
     * invisible to it, so the step can also be ticked manually.
     *
     * @since 3.0.0
     */
    const CREATE_PAGE_AJAX = 'wpsl_home_create_page';
    const MARK_PLACED_AJAX = 'wpsl_home_mark_placed';
    const PLACED_OPTION    = 'wpsl_home_placed_manually';

    /**
     * The AJAX actions behind the map service step, and the nonce they share.
     *
     * @since 3.0.0
     */
    const SAVE_SERVICE_ACTION = 'wpsl_home_save_map_service';
    const STATUS_ACTION       = 'wpsl_home_setup_status';
    const SERVICE_NONCE       = 'wpsl-home-map-service';

    /**
     * The feedback popup: its AJAX action, nonce, the API it relays to and
     * the kinds of feedback it takes.
     *
     * Same API the exit survey posts to, but with its own action, nonce
     * and handler rather than borrowing that one.
     *
     * @since 3.0.0
     */
    const FEEDBACK_ACTION = 'wpsl_home_feedback';
    const FEEDBACK_NONCE  = 'wpsl-home-feedback';
    const FEEDBACK_SERVER = 'https://feedback.wpstorelocator.co/api';
    const FEEDBACK_TYPES  = [ 'bug', 'feature', 'general' ];
    const FEEDBACK_MAX    = 15000;

    /**
     * The blog feed the "latest post" line reads, and how long one answer
     * is kept - a miss as well, so a site that cannot reach the feed does
     * not try again on every load.
     *
     * @since 3.0.0
     */
    const FEED_URL         = 'https://wpstorelocator.co/feed/';
    const FEED_TRANSIENT   = 'wpsl_home_latest_posts';
    const FEED_TTL         = 12 * HOUR_IN_SECONDS;
    const FEED_MISS_TTL    = HOUR_IN_SECONDS;
    const FEED_CACHE_ITEMS = 5;

    /**
     * Set once a map service has been chosen on purpose.
     *
     * @since 3.0.0
     */
    const CHOSEN_OPTION = 'wpsl_home_map_service_chosen';

    /**
     * The keys each map service cannot work without, as the setting that
     * holds the key and the option that records whether it validated.
     *
     * The Google browser key is only ever checked in a browser, so its
     * option is written by the screen that checked it, not by this class.
     *
     * @since 3.0.0
     */
    const SERVICE_KEYS = [
        'gmaps'  => [
            'gmaps_browser_key' => 'wpsl_valid_gmaps_browser_key',
            'gmaps_server_key'  => 'wpsl_valid_gmaps_server_key',
        ],
        'mapbox' => [
            'mapbox_key' => 'wpsl_valid_mapbox_key',
        ],
        'stadia' => [
            'stadia_key' => 'wpsl_valid_stadia_key',
        ],
        'osm'    => [],
    ];

    /**
     * Which key_status() type tests each key. The browser key has none.
     *
     * @since 3.0.0
     */
    const SERVER_CHECKS = [
        'gmaps_server_key' => 'server',
        'mapbox_key'       => 'mapbox',
        'stadia_key'       => 'stadia',
    ];

    /**
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * @since 3.0.0
     * @var \WPSL\Admin\Home\Simple_Mode
     */
    private $simple_mode;

    /**
     * @since 3.0.0
     * @var \WPSL\Core\Markers\Custom_Markers
     */
    private $markers;

    /**
     * @since 3.0.0
     */
    public function __construct( WpslSettings $settings, Simple_Mode $simple_mode, Custom_Markers $markers ) {
        $this->settings    = $settings;
        $this->simple_mode = $simple_mode;
        $this->markers     = $markers;

        add_action( 'admin_init', [ $this, 'maybe_dismiss_setup' ] );
        add_action( 'admin_post_wpsl_create_locator_page', [ $this, 'create_locator_page' ] );
        add_action( 'wp_ajax_' . self::SAVE_SERVICE_ACTION, [ $this, 'save_map_service' ] );
        add_action( 'wp_ajax_' . self::STATUS_ACTION, [ $this, 'setup_status' ] );
        add_action( 'wp_ajax_' . self::FEEDBACK_ACTION, [ $this, 'send_feedback' ] );
        add_action( 'wp_ajax_' . self::CREATE_PAGE_AJAX, [ $this, 'create_page_ajax' ] );
        add_action( 'wp_ajax_' . self::MARK_PLACED_AJAX, [ $this, 'mark_placed' ] );
    }

    /**
     * The newest posts on wpstorelocator.co, for the Help box.
     *
     * Read through WordPress's own feed fetcher, then kept in a transient of
     * this class's own: the fetcher caches too, but not a failure, and a
     * remote request on every Home load is not a price these lines are worth.
     *
     * @since  3.0.0
     * @param  int $limit How many to return
     * @return array[] [ [ 'title', 'url', 'date' ] ], empty when nothing could be read
     */
    public function get_latest_posts( $limit = 2 ) {
        $cached = get_transient( self::FEED_TRANSIENT );

        if ( is_array( $cached ) ) {
            return array_slice( $cached, 0, $limit );
        }

        include_once ABSPATH . WPINC . '/feed.php';

        // Five seconds, not the fetcher's ten: this is a footnote, not the page.
        $shorten = function( $feed ) {
            $feed->set_timeout( 5 );
        };

        add_action( 'wp_feed_options', $shorten );
        $feed = fetch_feed( self::FEED_URL );
        remove_action( 'wp_feed_options', $shorten );

        $posts = [];

        if ( ! is_wp_error( $feed ) ) {
            // Always cache a few, so raising the limit needs no new request.
            foreach ( (array) $feed->get_items( 0, self::FEED_CACHE_ITEMS ) as $item ) {
                $url = $item->get_permalink();

                if ( ! $url ) {
                    continue;
                }

                $posts[] = [
                    'title' => wp_strip_all_tags( $item->get_title() ),
                    'url'   => $url,
                    'date'  => $item->get_date( 'U' ) ? date_i18n( get_option( 'date_format' ), (int) $item->get_date( 'U' ) ) : '',
                ];
            }
        }

        set_transient( self::FEED_TRANSIENT, $posts, $posts ? self::FEED_TTL : self::FEED_MISS_TTL );

        return array_slice( $posts, 0, $limit );
    }

    /**
     * Relay the feedback popup's message to the feedback API.
     *
     * @since  3.0.0
     * @return void
     */
    public function send_feedback() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::FEEDBACK_NONCE ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) ], 403 );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) ], 403 );
        }

        $type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
        $message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) ) : '';
        $email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( ! in_array( $type, self::FEEDBACK_TYPES, true ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Please choose what kind of feedback you have.', 'wp-store-locator' ) ], 400 );
        }

        if ( '' === $message ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Please write your feedback before sending.', 'wp-store-locator' ) ], 400 );
        }

        // The relay writes to a shared database; a stuck retry must not flood it.
        if ( \WPSL\Core\Utils\Rate_Limiter::is_limited( 'home_feedback', apply_filters( 'wpsl_home_feedback_rate_limit', 3 ) ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You are sending feedback too quickly. Please wait a minute and try again.', 'wp-store-locator' ) ], 429 );
        }

        $lines = [
            'Type: ' . $type,
            'Plugin version: ' . WPSL_VERSION_NUM,
        ];

        if ( $email ) {
            $lines[] = 'Reply-to: ' . $email;
        }

        $lines[] = '';
        $lines[] = '-- Message';
        $lines[] = $message;

        // Site info only travels with a bug report, and only when asked for.
        if ( 'bug' === $type && ! empty( $_POST['include_report'] ) && wpsl_container()->has( 'status_report' ) ) {
            $lines[] = '';
            $lines[] = '-- Site info';
            $lines[] = wpsl_get_service( 'status_report' )->get_report();
        }

        $body = implode( "\n", $lines );

        if ( strlen( $body ) > self::FEEDBACK_MAX ) {
            $body = substr( $body, 0, self::FEEDBACK_MAX ) . "\n\n[ truncated ]";
        }

        $response = wp_remote_post( self::FEEDBACK_SERVER, [
            'timeout'    => 30,
            'headers'    => [ 'Accept' => 'application/json' ],
            'user-agent' => 'WPSL/' . WPSL_VERSION_NUM,
            'body'       => [
                'reason'   => 'home_' . $type,
                'feedback' => $body,
            ],
        ] );

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
            error_log( 'WPSL feedback relay failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : $code . ' ' . substr( wp_remote_retrieve_body( $response ), 0, 500 ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the popup only shows a generic message

            wp_send_json_error( [
                'message' => esc_html__( 'Sending failed.', 'wp-store-locator' ),
                'details' => '<p>' . sprintf(
                    /* translators: %s: link to the support page */
                    esc_html__( 'Please try again, or open a ticket at %s.', 'wp-store-locator' ),
                    '<a href="https://wpstorelocator.co/support/" target="_blank" rel="noopener noreferrer">wpstorelocator.co/support</a>'
                ) . '</p>',
            ], 500 );
        }

        wp_send_json_success( [ 'message' => esc_html__( 'Thanks, your feedback has been sent.', 'wp-store-locator' ) ] );
    }

    /**
     * Record that a map service was picked, see CHOSEN_OPTION.
     *
     * Static: this class only loads on its own screen, while the settings
     * page and the wizard record the choice from theirs.
     *
     * @since  3.0.0
     * @return void
     */
    public static function mark_map_service_chosen() {
        if ( ! get_option( self::CHOSEN_OPTION ) ) {
            update_option( self::CHOSEN_OPTION, 1, 'no' );
        }
    }

    /**
     * Whether anyone has picked a map service yet.
     *
     * @since  3.0.0
     * @return bool
     */
    private function map_service_chosen() {
        return (bool) get_option( self::CHOSEN_OPTION ) || (bool) get_option( 'wpsl_onboarding_finished' );
    }

    /**
     * Whether the checklist belongs on the page.
     *
     * @since  3.0.0
     * @param  array $checklist The get_checklist() items
     * @return bool
     */
    public function should_show_setup( array $checklist ) {
        if ( $this->is_setup_dismissed() ) {
            return false;
        }

        if ( $checklist && count( $checklist ) === count( array_filter( array_column( $checklist, 'done' ) ) ) ) {
            update_option( self::DISMISSED_OPTION, true, true );

            return false;
        }

        return true;
    }

    /**
     * Whether the setup checklist has been dismissed.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_setup_dismissed() {
        return (bool) get_option( self::DISMISSED_OPTION, false );
    }

    /**
     * The link that hides the checklist.
     *
     * @since  3.0.0
     * @return string
     */
    public function get_dismiss_url() {
        return wp_nonce_url( add_query_arg( [ 'wpsl-hide-setup' => 1 ] ), self::DISMISS_ACTION );
    }

    /**
     * Act on that link.
     *
     * @since  3.0.0
     * @return void
     */
    public function maybe_dismiss_setup() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is verified immediately below; this only decides whether to look.
        if ( ! isset( $_GET['wpsl-hide-setup'] ) ) {
            return;
        }

        if ( ! isset( $_GET['_wpnonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::DISMISS_ACTION )
        ) {
            wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) );
        }

        update_option( self::DISMISSED_OPTION, true, true );

        wp_safe_redirect( remove_query_arg( [ 'wpsl-hide-setup', '_wpnonce', '_wp_http_referer' ] ) );
        exit;
    }

    /**
     * Create a draft page carrying the shortcode.
     *
     * @since  3.0.0
     * @return void
     */
    public function create_locator_page() {
        if ( ! isset( $_POST['wpsl_create_page_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpsl_create_page_nonce'] ) ), self::CREATE_PAGE_ACTION )
        ) {
            wp_die( esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) || ! current_user_can( 'publish_pages' ) ) {
            wp_die( esc_html__( 'You do not have permission to create pages.', 'wp-store-locator' ) );
        }

        $title = $this->clean_page_title( isset( $_POST['wpsl_page_title'] ) ? wp_unslash( $_POST['wpsl_page_title'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside.

        // Already made one under this title: open that rather than a twin.
        $page_id = $this->find_locator_page( $title );

        if ( ! $page_id ) {
            $page_id = $this->insert_locator_page( $title );
        }

        if ( is_wp_error( $page_id ) ) {
            wp_die( esc_html( $page_id->get_error_message() ) );
        }

        wp_safe_redirect( get_edit_post_link( $page_id, 'raw' ) );
        exit;
    }

    /**
     * Create the draft page carrying the shortcode.
     *
     * Shared by the form post and the AJAX call, so the two 
     * cannot drift on what a created page looks like.
     *
     * @since  3.0.0
     * @param  string $title The requested title, unsanitized
     * @return int|\WP_Error The new page's id
     */
    private function insert_locator_page( $title ) {
        $title = $this->clean_page_title( $title );

        $page_id = wp_insert_post( [
            'post_title'   => $title,
            'post_content' => '[wpsl]',
            'post_status'  => 'draft',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id(),
        ], true );

        if ( ! is_wp_error( $page_id ) ) {
            // The checklist asks whether the shortcode is on a page; it now is.
            delete_transient( self::PLACED_TRANSIENT );
        }

        return $page_id;
    }

    /**
     * The title a new page will actually carry.
     *
     * @since  3.0.0
     * @param  string $title The requested title, unsanitized
     * @return string
     */
    private function clean_page_title( $title ) {
        $title = sanitize_text_field( (string) $title );

        // A title of spaces, or of nothing but markup, ends up empty here.
        if ( '' === $title ) {
            $title = esc_html__( 'Store Locator', 'wp-store-locator' );
        }

        return $title;
    }

    /**
     * An existing locator page with this exact title, if there is one.
     *
     * @since  3.0.0
     * @param  string $title The cleaned title
     * @return int           The page id, or 0
     */
    private function find_locator_page( $title ) {
        $pages = get_posts( [
            'post_type'              => 'page',
            'title'                  => $title,
            'post_status'            => [ 'publish', 'draft', 'pending', 'private', 'future' ],
            'numberposts'            => 5,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ] );

        foreach ( $pages as $page ) {
            if ( $this->content_has_locator( $page->post_content ) ) {
                return (int) $page->ID;
            }
        }

        return 0;
    }

    /**
     * Whether a post's content carries the locator.
     *
     * @since  3.0.0
     * @param  string $content The post content
     * @return bool
     */
    private function content_has_locator( $content ) {
        return false !== strpos( (string) $content, '[wpsl' )
            || false !== strpos( (string) $content, 'wp:wpsl/' );
    }

    /**
     * Create the page without leaving the Home screen.
     *
     * @since  3.0.0
     * @return void
     */
    public function create_page_ajax() {
        if ( ! isset( $_POST['nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::CREATE_PAGE_ACTION )
        ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) ], 403 );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) || ! current_user_can( 'publish_pages' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to create pages.', 'wp-store-locator' ) ], 403 );
        }

        $title = $this->clean_page_title( isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized inside.

        // A second click, or a revisit, should not build a pile of drafts.
        $existing = $this->find_locator_page( $title );

        if ( $existing ) {
            wp_send_json_success( [
                'title'    => get_the_title( $existing ),
                'editUrl'  => get_edit_post_link( $existing, 'raw' ),
                'existing' => true,
                'state'    => $this->get_setup_state(),
            ] );
        }

        $page_id = $this->insert_locator_page( $title );

        if ( is_wp_error( $page_id ) ) {
            wp_send_json_error( [ 'message' => esc_html( $page_id->get_error_message() ) ], 500 );
        }

        wp_send_json_success( [
            'title'    => get_the_title( $page_id ),
            'editUrl'  => get_edit_post_link( $page_id, 'raw' ),
            'existing' => false,
            'state'    => $this->get_setup_state(),
        ] );
    }

    /**
     * Tick or untick the page step by hand.
     *
     * @since  3.0.0
     * @return void
     */
    public function mark_placed() {
        $this->verify_ajax_request();

        if ( empty( $_POST['placed'] ) ) {
            delete_option( self::PLACED_OPTION );

            // Unticking takes the dismissal back too, or the checklist
            // would be gone with a step still open.
            delete_option( self::DISMISSED_OPTION );
        } else {
            update_option( self::PLACED_OPTION, 1, 'no' );
        }

        // The query's own answer is cached; this choice overrides it either way.
        delete_transient( self::PLACED_TRANSIENT );

        wp_send_json_success( [ 'state' => $this->get_setup_state() ] );
    }

    /**
     * Whether the page step was ticked by hand.
     *
     * @since  3.0.0
     * @return bool
     */
    public function is_placed_manually() {
        return (bool) get_option( self::PLACED_OPTION );
    }

    /**
     * The setup checklist.
     *
     * @since  3.0.0
     * @return array[] [ 'id', 'label', 'done', 'count', 'url' ]
     */
    public function get_checklist() {
        $stores = wp_count_posts( 'wpsl_stores' );

        // Only the three steps between an empty install and a working
        // locator: pick a provider, add a store, put it on a page.
        $service = $this->get_map_service_status();

        $items = [
            [
                'id'    => 'map_service',
                'label' => esc_html__( 'Choose a map service', 'wp-store-locator' ),
                'done'  => $service['ready'],
                'count' => null,
                'url'   => admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' ),
            ],
            [
                'id'      => 'first_location',
                'label'   => esc_html__( 'Add your first location', 'wp-store-locator' ),
                'done'    => (int) $stores->publish > 0,
                'count'   => null,
                'url'     => admin_url( 'post-new.php?post_type=wpsl_stores' ),
                /*
                 * A store is geocoded when it is saved, and the map on its
                 * edit screen needs the service too, so there is no point
                 * sending anyone there before the service works. The message
                 * says why, and the template points the step at step one.
                 */
                'blocked' => $service['ready'] ? '' : $service['reason'],
            ],
            [
                'id'    => 'placed_on_page',
                'label' => esc_html__( 'Add the store locator to a page', 'wp-store-locator' ),
                'done'  => $this->has_locator_on_page(),
                'count' => null,
                'url'   => admin_url( 'edit.php?post_type=page' ),
            ],
        ];

        /**
         * Filter the Home checklist.
         *
         * Add-ons add their own setup steps here - the CSV Manager's import,
         * the Pro add-on's analytics connection. Keep the array shape.
         *
         * @since 3.0.0
         * @param array $items [ 'id', 'label', 'done', 'count', 'url' ]
         */
        return apply_filters( 'wpsl_home_checklist', $items );
    }

    /**
     * The counter tiles: the three things a locator is made of.
     *
     * A tile's meta is the pill in its corner: either a count that needs
     * attention ( drafts, pending ) or, on a tile with nothing in it yet, a
     * 'text' entry marked 'cta' offering the way to start.
     *
     * @since  3.0.0
     * @return array[] Keyed locations, categories, markers
     */
    public function get_counts() {
        $stores     = wp_count_posts( 'wpsl_stores' );
        $categories = wp_count_terms( [ 'taxonomy' => 'wpsl_store_category', 'hide_empty' => false ] );
        $categories = is_wp_error( $categories ) ? 0 : (int) $categories;
        $markers    = count( $this->markers->get_markers() );
        $studio_url = admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' );

        $counts = [
            'locations' => [
                'label' => esc_html( _n( 'Location', 'Locations', (int) $stores->publish, 'wp-store-locator' ) ),
                'total' => (int) $stores->publish,
                'meta'  => [
                    [
                        'label' => esc_html__( 'draft', 'wp-store-locator' ),
                        'count' => (int) $stores->draft,
                        'url'   => admin_url( 'edit.php?post_status=draft&post_type=wpsl_stores' ),
                    ],
                    [
                        'label' => esc_html__( 'pending', 'wp-store-locator' ),
                        'count' => (int) $stores->pending,
                        'url'   => admin_url( 'edit.php?post_status=pending&post_type=wpsl_stores' ),
                    ],
                ],
                'url'   => admin_url( 'edit.php?post_type=wpsl_stores' ),
            ],
            'categories' => [
                'label' => esc_html( _n( 'Category', 'Categories', $categories, 'wp-store-locator' ) ),
                'total' => $categories,
                'meta'  => [],
                'url'   => admin_url( 'edit-tags.php?taxonomy=wpsl_store_category&post_type=wpsl_stores' ),
            ],
            'markers' => [
                'label' => esc_html( _n( 'Custom marker', 'Custom markers', $markers, 'wp-store-locator' ) ),
                'total' => $markers,
                /*
                 * Nothing drawn yet, so the tile says what to do about it
                 * rather than sitting there reporting a nought.
                 */
                'meta'  => $markers ? [] : [
                    [
                        'text' => esc_html__( 'Add your first', 'wp-store-locator' ),
                        'cta'  => true,
                        'url'  => $studio_url,
                    ],
                ],
                'url'   => $studio_url,
            ],
        ];

        /*
         * Simple mode takes the Marker Studio menu away on a site with no
         * markers drawn, so its tile goes too.
         */
        if ( $this->simple_mode->is_hidden( 'marker_studio' ) ) {
            unset( $counts['markers'] );
        }

        return $counts;
    }

    /**
     * The active alerts, for the Home page's own box.
     *
     * Keyed by the alert's own key, because that is what the dismiss control
     * posts back. Dismissing works here as well as on the Settings page: this
     * screen does not load the settings bundle, so the handler for it lives in
     * the Home page's own script instead.
     *
     * @since  3.0.0
     * @return array [ 'count' => int, 'items' => [ key => [ 'description', 'dismissible', 'details' ] ] ]
     */
    public function get_alerts() {
        $alerts = wpsl_get_service( 'plugin_alerts' );
        $active = $alerts ? $alerts->get_active_alerts() : [];
        $items  = [];

        foreach ( (array) $active as $key => $alert ) {
            if ( ! isset( $alert['description'] ) ) {
                continue;
            }

            $items[ $key ] = [
                'description' => $alert['description'],
                /* Dismissible unless it says otherwise, as the template assumes. */
                'dismissible' => ! isset( $alert['dismissible'] ) || $alert['dismissible'],
                /* Optional longer explanation, shown under the description. */
                'details'     => isset( $alert['details'] ) ? $alert['details'] : '',
            ];
        }

        return [
            'count' => count( $items ),
            'items' => $items,
        ];
    }

    /**
     * Whether the shortcode or the block appears on a page or post.
     *
     * @since  3.0.0
     * @return bool
     */
    public function has_locator_on_page() {
        // Said so by hand, which the query below cannot know.
        if ( $this->is_placed_manually() ) {
            return true;
        }

        if ( get_transient( self::PLACED_TRANSIENT ) ) {
            return true;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- No core API answers "is this shortcode used anywhere"; the positive answer is cached below.
        $found = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ( 'publish', 'draft' ) AND post_type IN ( 'page', 'post' ) AND ( post_content LIKE %s OR post_content LIKE %s ) LIMIT 1",
            '%' . $wpdb->esc_like( '[wpsl' ) . '%',
            '%' . $wpdb->esc_like( 'wp:wpsl/' ) . '%'
        ) );

        if ( $found ) {
            set_transient( self::PLACED_TRANSIENT, 1, DAY_IN_SECONDS );

            return true;
        }

        return false;
    }

    /**
     * Where the map service stands: which one, and whether it can be used.
     *
     * @since  3.0.0
     * @return array [ 'service', 'name', 'ready', 'reason' ] - reason is the
     *               empty string when ready, otherwise says what is missing.
     */
    public function get_map_service_status() {
        $service  = $this->settings->get( 'api', 'active_map_service' );
        $services = wpsl_get_map_services();
        $name     = isset( $services[ $service ] ) ? $services[ $service ] : '';

        if ( ! isset( self::SERVICE_KEYS[ $service ] ) ) {
            // An add-on's service is not ours to nag about.
            $ready  = ( '' !== $name );
            $reason = $ready ? '' : esc_html__( 'Choose a map service before you add locations.', 'wp-store-locator' );
        } elseif ( ! self::SERVICE_KEYS[ $service ] ) {
            // OpenStreetMap needs no key, only a deliberate choice.
            $ready  = $this->map_service_chosen();
            $reason = $ready ? '' : esc_html__( 'Choose a map service before you add locations.', 'wp-store-locator' );
        } else {
            $ready = true;

            foreach ( self::SERVICE_KEYS[ $service ] as $option ) {
                if ( ! get_option( $option, 0 ) ) {
                    $ready = false;
                    break;
                }
            }

            /* translators: %s: the map service name, e.g. Mapbox */
            $reason = $ready ? '' : sprintf( esc_html__( '%s needs a valid API key before you can add locations.', 'wp-store-locator' ), $name );
        }

        return [
            'service' => $service,
            'name'    => $name,
            'ready'   => $ready,
            'reason'  => $reason,
        ];
    }

    /**
     * What the map service step's form prints.
     *
     * @since  3.0.0
     * @return array [ 'services' => [ id => name ], 'current' => id,
     *                 'keys' => [ id => [ setting => [ 'label', 'value', 'valid' ] ] ],
     *                 'docs' => [ id => [ 'url', 'label' ] ] ]
     */
    public function get_map_service_form() {
        $api    = $this->settings->get_group( 'api' );
        $labels = [
            'gmaps_browser_key' => esc_html__( 'Browser key', 'wp-store-locator' ),
            'gmaps_server_key'  => esc_html__( 'Server key', 'wp-store-locator' ),
            'mapbox_key'        => esc_html__( 'API key', 'wp-store-locator' ),
            'stadia_key'        => esc_html__( 'API key', 'wp-store-locator' ),
        ];

        // The guides on wpstorelocator.co, one per service that needs a key.
        $guides = [
            'gmaps'  => esc_html__( 'How to create the Google Maps API keys', 'wp-store-locator' ),
            'mapbox' => esc_html__( 'How to create a Mapbox API key', 'wp-store-locator' ),
            'stadia' => esc_html__( 'How to create a Stadia Maps API key', 'wp-store-locator' ),
        ];

        $keys = [];
        $docs = [];

        foreach ( self::SERVICE_KEYS as $service => $fields ) {
            $keys[ $service ] = [];
            $docs[ $service ] = [
                'url'   => wpsl_create_key_docs_url( $service ),
                'label' => isset( $guides[ $service ] ) ? $guides[ $service ] : '',
            ];

            foreach ( $fields as $setting => $option ) {
                $keys[ $service ][ $setting ] = [
                    'label' => $labels[ $setting ],
                    'value' => isset( $api[ $setting ] ) ? (string) $api[ $setting ] : '',
                    'valid' => (bool) get_option( $option, 0 ),
                ];
            }
        }

        return [
            'services' => wpsl_get_map_services(),
            'current'  => $this->settings->get( 'api', 'active_map_service' ),
            'keys'     => $keys,
            'docs'     => $docs,
        ];
    }

    /**
     * The checklist as the page's script needs it after a step changes.
     *
     * @since  3.0.0
     * @return array [ 'items' => [ [ 'id', 'done', 'url', 'blocked' ] ],
     *                 'done' => int, 'total' => int, 'show' => bool ]
     */
    public function get_setup_state() {
        $checklist = $this->get_checklist();
        $items     = [];

        // The list is filterable and the template tolerates an item without a
        // url; a notice raised here would land inside the JSON reply.
        foreach ( $checklist as $item ) {
            $items[] = [
                'id'      => $item['id'],
                'done'    => (bool) $item['done'],
                'url'     => isset( $item['url'] ) ? $item['url'] : '',
                'blocked' => isset( $item['blocked'] ) ? $item['blocked'] : '',
            ];
        }

        return [
            'items' => $items,
            'done'  => count( array_filter( array_column( $checklist, 'done' ) ) ),
            'total' => count( $checklist ),
            'show'  => $this->should_show_setup( $checklist ),
        ];
    }

    /**
     * Save the map service step and test its keys.
     *
     * @since  3.0.0
     * @return void
     */
    public function save_map_service() {
        $this->verify_ajax_request();

        $services = wpsl_get_map_services();
        $service  = isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '';

        if ( ! isset( $services[ $service ] ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Choose a map service first.', 'wp-store-locator' ) ] );
        }

        $previous = $this->settings->get( 'api', 'active_map_service' );

        $this->settings->set( 'api', 'active_map_service', $service );

        if ( $previous !== $service ) {
            // Cached search results carry the old service's geocodes.
            update_option( 'wpsl_delete_transient', 1, 'no' );
        }

        // Fires on update_option too, but not when nothing changed.
        self::mark_map_service_chosen();

        $fields  = isset( self::SERVICE_KEYS[ $service ] ) ? self::SERVICE_KEYS[ $service ] : [];
        $posted  = ( isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ) ? map_deep( wp_unslash( $_POST['keys'] ), 'sanitize_text_field' ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- map_deep sanitizes every value.
        $results = [];
        $pending = false;

        $validate = wpsl_get_service( 'validate_keys' );

        foreach ( $fields as $setting => $option ) {
            $value = isset( $posted[ $setting ] ) ? trim( $posted[ $setting ] ) : '';

            $this->settings->set( 'api', $setting, $value );

            if ( isset( self::SERVER_CHECKS[ $setting ] ) && $validate ) {
                $results[ $setting ] = $validate->key_status( self::SERVER_CHECKS[ $setting ], $value );

                continue;
            }

            // The browser key: empty is decided here, anything else in the browser.
            if ( '' === $value ) {
                update_option( $option, 0, 'no' );

                $results[ $setting ] = [
                    'valid'   => 0,
                    'msg'     => esc_html__( 'The browser key is missing.', 'wp-store-locator' ),
                    'details' => '',
                ];
            } else {
                $pending = true;
            }
        }

        wp_send_json_success( [
            'service'         => $service,
            'results'         => $results,
            'browser_pending' => $pending,
            'state'           => $this->get_setup_state(),
        ] );
    }

    /**
     * The checklist state, for after a check that finished in the browser.
     *
     * @since  3.0.0
     * @return void
     */
    public function setup_status() {
        $this->verify_ajax_request();

        wp_send_json_success( [ 'state' => $this->get_setup_state() ] );
    }

    /**
     * The nonce and capability checks both AJAX handlers start with.
     *
     * @since  3.0.0
     * @return void
     */
    private function verify_ajax_request() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::SERVICE_NONCE ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'Security check failed. Please reload the page and try again.', 'wp-store-locator' ) ] );
        }

        if ( ! current_user_can( 'manage_wpsl_settings' ) ) {
            wp_send_json_error( [ 'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-store-locator' ) ] );
        }
    }
}