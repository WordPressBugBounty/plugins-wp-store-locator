<?php
/**
 * Find and fix locations without usable coordinates.
 *
 * A store whose coordinates are missing or out of range never shows up in a
 * search result. This shows the state as a column and a view link, and
 * re-geocodes the affected stores in batches from a dialog.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Admin\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Admin\API\Geocode;
use WPSL\Core\Utils\Location_Utils;

class Geocode_Locations {

    /**
     * How many locations are geocoded per AJAX request.
     *
     * Deliberately small: every one of these is an external API call. Nominatim
     * is held to one request per second site-wide, so a larger batch would sit
     * on the request until PHP gives up.
     *
     * @since 3.0.0
     * @var int
     */
    const BATCH_SIZE = 5;

    /**
     * The query arg the "Invalid coordinates" view link sets.
     *
     * @since 3.0.0
     * @var string
     */
    const VIEW_ARG = 'wpsl_coordinates';

    /**
     * Name of the bulk action added to the store list dropdown.
     *
     * @since 3.0.0
     * @var string
     */
    const BULK_ACTION = 'wpsl_geocode';

    /**
     * The orderby value the sortable coordinates column uses.
     *
     * @since 3.0.0
     * @var string
     */
    const ORDER_BY = 'wpsl_coordinate_state';

    /**
     * Transient holding the classified problem ids.
     *
     * The ids rather than a bare count, because keeping the cache current
     * without rescanning means knowing whether the store that was just saved
     * was already being counted. A count cannot answer that; a set can.
     *
     * @since 3.0.0
     * @var string
     */
    const STATES_TRANSIENT = 'wpsl_coordinate_states';

    /**
     * How long the cached split lives.
     *
     * @since 3.0.0
     * @var int
     */
    const CACHE_LIFETIME = 2 * DAY_IN_SECONDS;

    /**
     * How many stores one request keeps the cached split current for, one
     * at a time, before it is cheaper to drop the cache and rescan later.
     *
     * @since 3.0.0
     * @var int
     */
    const SYNC_LIMIT = 25;

    /**
     * The validation option of the key each geocoder needs. OpenStreetMap
     * ( Nominatim ) needs none, so it is not listed.
     *
     * @since 3.0.0
     * @var array<string, string>
     */
    const GEOCODER_KEYS = [
        'gmaps'  => 'wpsl_valid_gmaps_server_key',
        'mapbox' => 'wpsl_valid_mapbox_key',
        'stadia' => 'wpsl_valid_stadia_key',
    ];

    /**
     * The store ids kept current during this request. See SYNC_LIMIT.
     *
     * @since 3.0.0
     * @var array<int, true>
     */
    private static $synced = [];

    /**
     * Whether this request passed SYNC_LIMIT and dropped the cache instead.
     *
     * @since 3.0.0
     * @var bool
     */
    private static $bulk_write = false;

    /**
     * Reset the per-request sync bookkeeping ( for tests, which run many
     * requests' worth of saves in one process ).
     *
     * @since  3.0.0
     * @return void
     */
    public static function reset_sync_state() {
        self::$synced     = [];
        self::$bulk_write = false;
    }

    /**
     * The geocode service.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\API\Geocode
     */
    private $geocode;

    /**
     * The address parts handed to the geocoder, in the order create_address()
     * expects to find them.
     *
     * @since 3.0.0
     * @var string[]
     */
    private $address_fields = [ 'address', 'city', 'state', 'zip', 'country' ];

    /**
     * Constructor.
     *
     * @since 3.0.0
     * @param \WPSL\Admin\API\Geocode $geocode The geocode service instance.
     */
    public function __construct( Geocode $geocode ) {
        $this->geocode = $geocode;

        add_action( 'wp_ajax_wpsl_geocode_locations',           [ $this, 'handle_request' ] );

        add_filter( 'views_edit-wpsl_stores',                   [ $this, 'add_view' ] );
        add_filter( 'bulk_actions-edit-wpsl_stores',            [ $this, 'add_bulk_action' ] );
        add_filter( 'handle_bulk_actions-edit-wpsl_stores',     [ $this, 'handle_bulk_action' ], 10, 3 );
        add_action( 'pre_get_posts',                            [ $this, 'filter_list' ] );
        add_filter( 'manage_edit-wpsl_stores_sortable_columns', [ $this, 'add_sortable_column' ] );
        add_filter( 'posts_clauses',                            [ $this, 'sort_by_state' ], 10, 2 );
        add_action( 'manage_posts_extra_tablenav',              [ $this, 'add_geocode_all' ] );
        add_action( 'admin_footer',                             [ $this, 'render_dialog' ] );
        add_action( 'admin_enqueue_scripts',                    [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices',                            [ $this, 'bulk_action_notice' ] );
    }

    /**
     * Keep the cached split current from wherever coordinates change.
     *
     * @since  3.0.0
     * @return void
     */
    public static function register_sync_hooks() {
        static $registered = false;

        if ( $registered ) {
            return;
        }

        $registered = true;

        add_action( 'added_post_meta',       [ __CLASS__, 'sync_meta' ], 10, 3 );
        add_action( 'updated_post_meta',     [ __CLASS__, 'sync_meta' ], 10, 3 );
        add_action( 'deleted_post_meta',     [ __CLASS__, 'sync_meta' ], 10, 3 );

        add_action( 'save_post_wpsl_stores', [ __CLASS__, 'sync_store' ] );
        add_action( 'trashed_post',          [ __CLASS__, 'drop_store' ] );
        add_action( 'untrashed_post',        [ __CLASS__, 'sync_store' ] );
        add_action( 'deleted_post',          [ __CLASS__, 'flush_deleted' ], 10, 2 );
    }

    /**
     * Why the configured geocoder can't look up addresses right now.
     *
     * @since  3.0.0
     * @return string Plain text reason, empty when geocoding can run.
     */
    public static function geocoder_problem() {
        $service = wpsl_get_geocoding_service();

        if ( ! isset( self::GEOCODER_KEYS[ $service ] ) || get_option( self::GEOCODER_KEYS[ $service ], 0 ) ) {
            return '';
        }

        $names = wpsl_get_map_services();

        return sprintf(
            /* translators: %s: the geocoding service, e.g. Google Maps */
            __( '%s needs a valid API key before these locations can be geocoded. Fix the key in the API settings, then try again.', 'wp-store-locator' ),
            isset( $names[ $service ] ) ? $names[ $service ] : $service
        );
    }

    /**
     * Where the API keys are set.
     *
     * @since  3.0.0
     * @return string
     */
    public static function api_settings_url() {
        return admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_settings#wpsl-api' );
    }

    /**
     * Reclassify a store whose coordinate meta was written or removed.
     *
     * @since  3.0.0
     * @param  int|int[] $meta_id  The meta row id ( ids, for a delete )
     * @param  int       $post_id  The post the meta belongs to
     * @param  string    $meta_key The meta key that changed
     * @return void
     */
    public static function sync_meta( $meta_id, $post_id, $meta_key ) {
        if ( 'wpsl_lat' !== $meta_key && 'wpsl_lng' !== $meta_key ) {
            return;
        }

        self::sync_store( $post_id );
    }

    /**
     * Whether the current request is the store list screen.
     *
     * @since  3.0.0
     * @return bool
     */
    private function is_store_list() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();

        return ( $screen && 'edit-wpsl_stores' === $screen->id );
    }

    /**
     * Classify a pair of stored coordinates.
     *
     * @since  3.0.0
     * @param  mixed $lat The stored latitude
     * @param  mixed $lng The stored longitude
     * @return string 'valid', 'missing' or 'invalid'
     */
    public static function classify( $lat, $lng ) {
        $lat = trim( (string) $lat );
        $lng = trim( (string) $lng );

        if ( '' === $lat || '' === $lng ) {
            return 'missing';
        }

        // validate_latlng() is false when a value isn't numeric or falls
        // outside the -90/90 and -180/180 ranges; an importer writing
        // "N/A" or a swapped lat/lng lands here.
        if ( ! Location_Utils::validate_latlng( $lat, $lng ) ) {
            return 'invalid';
        }

        return 'valid';
    }

    /**
     * The coordinate state of a single store.
     *
     * @since  3.0.0
     * @param  int $post_id The store post ID
     * @return string 'valid', 'missing' or 'invalid'
     */
    public static function state( $post_id ) {
        return self::classify(
            get_post_meta( $post_id, 'wpsl_lat', true ),
            get_post_meta( $post_id, 'wpsl_lng', true )
        );
    }

    /**
     * Every store whose coordinates are missing or unusable.
     *
     * @since  3.0.0
     * @return int[] The post IDs that need geocoding
     */
    public static function get_problem_ids() {
        $states = self::get_classified_ids();

        $ids = array_merge( $states['missing'], $states['invalid'] );

        sort( $ids );

        return $ids;
    }

    /**
     * The problem stores, split by what is wrong with them.
     *
     * The sort and the column both read this, so a row can never be ordered
     * as one state while its icon shows another. Cached, and kept current
     * by sync_store() rather than rebuilt, so the scan runs once rather
     * than after every store save.
     *
     * @since  3.0.0
     * @return array {
     *     @type int[] $missing Stores with no coordinates stored
     *     @type int[] $invalid Stores holding something that is not a point on the map
     * }
     */
    public static function get_classified_ids() {
        $cached = get_transient( self::STATES_TRANSIENT );

        if ( self::is_split( $cached ) ) {
            return $cached;
        }

        $states = self::scan();

        self::store( $states );

        return $states;
    }

    /**
     * Whether a cached value has the expected shape: the 'missing' and
     * 'invalid' buckets, both arrays.
     *
     * Guards against a cold cache and the integer the previous version stored.
     *
     * @since  3.0.0
     * @param  mixed $states The cached value
     * @return bool
     */
    private static function is_split( $states ) {
        return is_array( $states )
            && isset( $states['missing'], $states['invalid'] )
            && is_array( $states['missing'] )
            && is_array( $states['invalid'] );
    }

    /**
     * Write the split back, and put its full lifetime back with it.
     *
     * Every save refreshes the expiry, so an actively edited site never
     * pays for the scan; one left alone long enough rebuilds once, which
     * keeps an outside-WordPress change from being cached forever.
     *
     * @since  3.0.0
     * @param  array $states The split to cache
     * @return void
     */
    private static function store( array $states ) {
        set_transient( self::STATES_TRANSIENT, $states, self::CACHE_LIFETIME );
    }

    /**
     * Keep the cached split current for a single store: a save moves its id
     * between the buckets instead of emptying the cache for a full rescan.
     *
     * @since  3.0.0
     * @param  int $post_id The store post ID
     * @return void
     */
    public static function sync_store( $post_id ) {
        $post_id = (int) $post_id;

        /*
         * trashed_post and untrashed_post fire for every post type, and an id
         * that is not a store would otherwise be classified on meta it never
         * had and counted as missing coordinates.
         */
        if ( 'wpsl_stores' !== get_post_type( $post_id ) ) {
            return;
        }

        // A bulk write already dropped the cache; there is nothing to keep current.
        if ( self::$bulk_write ) {
            return;
        }

        $states = get_transient( self::STATES_TRANSIENT );

        if ( ! self::is_split( $states ) ) {
            return;
        }

        /*
         * An import or WP-CLI loop touches more stores than it is worth
         * reclassifying one at a time; past the limit the cache is dropped
         * once and the next reader rebuilds it in one scan.
         */
        self::$synced[ $post_id ] = true;

        if ( count( self::$synced ) > self::SYNC_LIMIT ) {
            self::$bulk_write = true;

            self::flush_count();

            return;
        }

        $states = self::without( $states, $post_id );
        $state  = self::state( $post_id );

        /*
         * The scan skips these two statuses, so the cache has to skip them as
         * well, or a trashed store keeps being reported as broken.
         */
        $counted = ! in_array( get_post_status( $post_id ), [ 'trash', 'auto-draft' ], true );

        if ( $counted && isset( $states[ $state ] ) ) {
            $states[ $state ][] = $post_id;

            sort( $states[ $state ] );
        }

        self::store( $states );
    }

    /**
     * Take a store out of the cached split.
     *
     * @since  3.0.0
     * @param  int $post_id The post ID
     * @return void
     */
    public static function drop_store( $post_id ) {
        $states = get_transient( self::STATES_TRANSIENT );

        if ( ! self::is_split( $states ) ) {
            return;
        }

        self::store( self::without( $states, (int) $post_id ) );
    }

    /**
     * The split without a given id in either bucket.
     *
     * @since  3.0.0
     * @param  array $states  The split
     * @param  int   $post_id The id to remove
     * @return array $states
     */
    private static function without( array $states, $post_id ) {
        foreach ( [ 'missing', 'invalid' ] as $bucket ) {
            $states[ $bucket ] = array_values( array_diff( $states[ $bucket ], [ $post_id ] ) );
        }

        return $states;
    }

    /**
     * Read every store's coordinates and classify them.
     *
     * The expensive one, and the reason for the cache: it reads the whole
     * post type and both coordinate meta keys. It runs only when a reader
     * finds the cache cold and asks for it ( get_classified_ids() /
     * count_problems( true ) ) -- a save never triggers it, and a front end
     * page view only when the caller opts in.
     *
     * @since  3.0.0
     * @return array {
     *     @type int[] $missing Stores with no coordinates stored
     *     @type int[] $invalid Stores holding something that is not a point on the map
     * }
     */
    private static function scan() {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT posts.ID, lat.meta_value AS lat, lng.meta_value AS lng
               FROM {$wpdb->posts} AS posts
          LEFT JOIN {$wpdb->postmeta} AS lat
                 ON lat.post_id = posts.ID AND lat.meta_key = %s
                AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS pick_lat WHERE pick_lat.post_id = posts.ID AND pick_lat.meta_key = %s AND pick_lat.meta_id < lat.meta_id )
          LEFT JOIN {$wpdb->postmeta} AS lng
                 ON lng.post_id = posts.ID AND lng.meta_key = %s
                AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} AS pick_lng WHERE pick_lng.post_id = posts.ID AND pick_lng.meta_key = %s AND pick_lng.meta_id < lng.meta_id )
              WHERE posts.post_type = %s
                AND posts.post_status NOT IN ( 'trash', 'auto-draft' )
           ORDER BY posts.ID ASC",
            'wpsl_lat',
            'wpsl_lat',
            'wpsl_lng',
            'wpsl_lng',
            'wpsl_stores'
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No meta_query can express an out of range value. Prepared above, cached through the count transient.
        $rows = $wpdb->get_results( $sql );

        $states = [ 'missing' => [], 'invalid' => [] ];

        foreach ( $rows as $row ) {
            $state = self::classify( $row->lat, $row->lng );

            if ( isset( $states[ $state ] ) ) {
                $states[ $state ][] = (int) $row->ID;
            }
        }

        return $states;
    }

    /**
     * How many stores need attention.
     *
     * Read by the store list's view link and the alert, so this counts the
     * cached split rather than asking the database. A cold cache is rebuilt
     * only when the caller says so: the admin bar badge on a front end page
     * view cannot afford the scan, and reports nothing until an admin
     * screen has built the cache.
     *
     * @since  3.0.0
     * @param  bool $build Whether a cold cache may be built by a scan.
     * @return int
     */
    public static function count_problems( $build = true ) {
        if ( ! $build ) {
            $states = get_transient( self::STATES_TRANSIENT );

            if ( ! self::is_split( $states ) ) {
                return 0;
            }
        } else {
            $states = self::get_classified_ids();
        }

        return count( $states['missing'] ) + count( $states['invalid'] );
    }

    /**
     * Drop the cached split so the next read rebuilds it.
     *
     * @since  3.0.0
     * @return void
     */
    public static function flush_count() {
        delete_transient( self::STATES_TRANSIENT );
    }

    /**
     * Take a deleted store out of the cached split.
     *
     * deleted_post fires for every post type, so the cache is only touched
     * when the deletion could actually have changed it.
     *
     * @since  3.0.0
     * @param  int       $post_id The deleted post ID
     * @param  \WP_Post $post    The deleted post
     * @return void
     */
    public static function flush_deleted( $post_id, $post = null ) {
        if ( $post && 'wpsl_stores' !== $post->post_type ) {
            return;
        }

        self::drop_store( $post_id );
    }

    /**
     * Add the "Invalid coordinates" link to the views row above the table.
     *
     * Only shown when there is something to look at. A site with clean data
     * doesn't need a permanent zero..
     *
     * @since  3.0.0
     * @param  array $views The existing view links
     * @return array $views
     */
    public function add_view( $views ) {
        $count = self::count_problems();

        if ( ! $count ) {
            return $views;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter
        $active = isset( $_GET[ self::VIEW_ARG ] ) ? ' class="current" aria-current="page"' : '';

        $url = add_query_arg(
            [
                'post_type'      => 'wpsl_stores',
                self::VIEW_ARG   => 'problem',
            ],
            admin_url( 'edit.php' )
        );

        $views['wpsl_coordinates'] = sprintf(
            '<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
            esc_url( $url ),
            $active, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static string
            esc_html__( 'Invalid coordinates', 'wp-store-locator' ),
            $count
        );

        return $views;
    }

    /**
     * Narrow the store list to the problem rows when the view link is active.
     *
     * @since  3.0.0
     * @param  \WP_Query $query The query about to run
     * @return void
     */
    public function filter_list( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'wpsl_stores' !== $query->get( 'post_type' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter
        if ( ! isset( $_GET[ self::VIEW_ARG ] ) ) {
            return;
        }

        $ids = self::get_problem_ids();

        /*
         * An empty post__in is ignored by WP_Query, which would show every
         * store instead of none. A nonexistent ID gives the empty list the
         * user asked for.
         */
        $query->set( 'post__in', $ids ? $ids : [ 0 ] );

        // Clicking a column header while filtered should still sort by it.
        if ( ! $query->get( 'orderby' ) ) {
            $query->set( 'orderby', 'post__in' );
        }
    }

    /**
     * Make the coordinates column sortable.
     *
     * @since  3.0.0
     * @param  array $columns The sortable columns
     * @return array $columns
     */
    public function add_sortable_column( $columns ) {
        $columns['coordinates'] = [ self::ORDER_BY, true ];

        return $columns;
    }

    /**
     * Order the list by coordinate state.
     *
     * @since  3.0.0
     * @param  array     $clauses The query clauses
     * @param  \WP_Query $query   The query being run
     * @return array     $clauses
     */
    public function sort_by_state( $clauses, $query ) {
        global $wpdb;

        if ( ! is_admin() || ! $query->is_main_query() ) {
            return $clauses;
        }

        if ( 'wpsl_stores' !== $query->get( 'post_type' ) || self::ORDER_BY !== $query->get( 'orderby' ) ) {
            return $clauses;
        }

        $states = self::get_classified_ids();

        if ( ! $states['missing'] && ! $states['invalid'] ) {
            return $clauses;
        }

        $order = ( 'asc' === strtolower( (string) $query->get( 'order' ) ) ) ? 'ASC' : 'DESC';
        $cases = [];

        /*
         * Missing first, then invalid, then everything that is fine. Descending
         * is the default so one click puts the locations needing attention on
         * top, which is the reason to sort by this column at all.
         */
        foreach ( [ 'missing' => 2, 'invalid' => 1 ] as $state => $rank ) {
            if ( $states[ $state ] ) {
                $cases[] = sprintf(
                    'WHEN %s.ID IN ( %s ) THEN %d',
                    $wpdb->posts,
                    implode( ',', array_map( 'absint', $states[ $state ] ) ),
                    $rank
                );
            }
        }

        $rank = 'CASE ' . implode( ' ', $cases ) . ' ELSE 0 END';

        // Same title order within a rank, so the list does not reshuffle on reload.
        $clauses['orderby'] = $rank . ' ' . $order . ', ' . $wpdb->posts . '.post_title ASC';

        return $clauses;
    }

    /**
     * Add "Geocode" to the bulk actions dropdown.
     *
     * @since  3.0.0
     * @param  array $actions The existing bulk actions
     * @return array $actions
     */
    public function add_bulk_action( $actions ) {
        $actions[ self::BULK_ACTION ] = esc_html__( 'Geocode', 'wp-store-locator' );

        return $actions;
    }

    /**
     * Fallback for the bulk action without JavaScript.
     *
     * @since  3.0.0
     * @param  string $redirect The redirect URL
     * @param  string $action   The selected bulk action
     * @param  array  $post_ids The selected post IDs
     * @return string $redirect
     */
    public function handle_bulk_action( $redirect, $action, $post_ids ) {
        if ( self::BULK_ACTION !== $action ) {
            return $redirect;
        }

        return add_query_arg( 'wpsl-status', 'geocode-needs-js', $redirect );
    }

    /**
     * Explain why the bulk action did nothing without JavaScript.
     *
     * @since  3.0.0
     * @return void
     */
    public function bulk_action_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a status flag set by our own redirect; renders a notice, changes nothing.
        if ( ! isset( $_REQUEST['wpsl-status'] ) || 'geocode-needs-js' !== sanitize_key( wp_unslash( $_REQUEST['wpsl-status'] ) ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html__( 'Geocoding runs in a dialog so it can report progress and per-location errors. Enable JavaScript and try again.', 'wp-store-locator' )
        );
    }

    /**
     * Load the store list script.
     *
     * @since  3.0.0
     * @return void
     */
    public function enqueue_assets() {
        if ( ! $this->is_store_list() ) {
            return;
        }

        if ( ! current_user_can( 'edit_stores' ) ) {
            return;
        }

        $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        wp_enqueue_script(
            'wpsl-geocode-locations',
            WPSL_URL . 'assets/src/admin/js/wpsl-geocode-locations' . $min . '.js',
            [ 'jquery', 'jquery-ui-dialog' ],
            WPSL_VERSION_NUM,
            true
        );

        wp_localize_script( 'wpsl-geocode-locations', 'wpslGeocodeL10n', [
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'batchSize'     => self::BATCH_SIZE,
            'summarySingle' => __( '1 location will be geocoded.', 'wp-store-locator' ),
            /* translators: %d: the number of locations */
            'summaryPlural' => __( '%d locations will be geocoded.', 'wp-store-locator' ),
            /* translators: %1$s: locations handled so far, %2$s: the total */
            'progress'      => __( 'Geocoding %1$s / %2$s', 'wp-store-locator' ),
            /* translators: %1$d: locations placed, %2$d: locations attempted */
            'done'          => __( 'Fixed %1$d of %2$d locations.', 'wp-store-locator' ),
            /* translators: %d: the number of locations repaired without an API call */
            'repaired'      => __( '%d of those only needed their coordinates reformatted, not looked up.', 'wp-store-locator' ),
            /* translators: %1$s: latitude, %2$s: longitude */
            'single'        => __( 'Coordinates set to %1$s, %2$s.', 'wp-store-locator' ),
            'singleRepaired' => __( 'These were already stored and only needed reformatting. No lookup was needed.', 'wp-store-locator' ),
            /* translators: %1$s: first location in the batch, %2$s: last, %3$s: the total */
            'processing'    => __( 'Processing %1$s to %2$s of %3$s', 'wp-store-locator' ),
            'cancel'        => __( 'Cancel', 'wp-store-locator' ),
            'cancelling'    => __( 'Cancelling...', 'wp-store-locator' ),
            'cancelConfirm' => __( 'Stop geocoding? Locations that are already fixed are kept.', 'wp-store-locator' ),
            'stopped'       => __( 'Stopped', 'wp-store-locator' ),
            /* translators: %d: the number of locations the run never reached */
            'notProcessed'  => __( '%d locations were not processed.', 'wp-store-locator' ),
            'noneSelected'  => __( 'Select at least one location first.', 'wp-store-locator' ),
            'noneLeft'      => __( 'Every location already has usable coordinates.', 'wp-store-locator' ),
            'requestFailed' => __( 'The request failed. Please reload the page and try again.', 'wp-store-locator' ),
            'blocked'       => self::geocoder_problem(),
        ] );
    }

    /**
     * Offer to geocode every affected location at once.
     *
     * @since  3.0.0
     * @param  string $which Which end of the table this is, 'top' or 'bottom'
     * @return void
     */
    public function add_geocode_all( $which ) {
        if ( 'top' !== $which || ! $this->is_store_list() || ! current_user_can( 'edit_stores' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter
        if ( ! isset( $_GET[ self::VIEW_ARG ] ) ) {
            return;
        }

        $count = self::count_problems();

        if ( ! $count ) {
            return;
        }

        printf(
            '<div class="alignleft actions wpsl-geocode-all-wrap"><a href="#" class="button" id="wpsl-geocode-all">%s</a></div>',
            esc_html(
                sprintf(
                    /* translators: %d: the number of locations without usable coordinates */
                    _n( 'Fix %d location', 'Fix all %d locations', $count, 'wp-store-locator' ),
                    $count
                )
            )
        );
    }

    /**
     * Print the dialog markup in the store list footer.
     *
     * @since  3.0.0
     * @return void
     */
    public function render_dialog() {
        if ( ! $this->is_store_list() ) {
            return;
        }

        if ( ! current_user_can( 'edit_stores' ) ) {
            return;
        }

        require WPSL_PLUGIN_DIR . 'includes/admin/settings/templates/dialog/geocode-locations.php';
    }

    /**
     * Handle the AJAX call from the store list.
     *
     * @since  3.0.0
     * @return void
     */
    public function handle_request() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsl-geocode-locations' ) ) {
            wp_send_json_error( [ 'message' => __( 'The security check failed, please reload the page and try again.', 'wp-store-locator' ) ] );
        }

        if ( ! current_user_can( 'edit_stores' ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'wp-store-locator' ) ] );
        }

        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'list';

        if ( 'geocode' === $mode ) {
            $ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : [];

            wp_send_json_success( $this->geocode_batch( array_filter( $ids ) ) );
        }

        wp_send_json_success( [ 'ids' => self::get_problem_ids() ] );
    }

    /**
     * Geocode one batch of stores.
     *
     * @since  3.0.0
     * @param  int[] $post_ids The stores to geocode
     * @return array One result entry per store
     */
    public function geocode_batch( $post_ids ) {
        $post_ids = array_slice( $post_ids, 0, self::BATCH_SIZE );
        $results  = [];

        foreach ( $post_ids as $post_id ) {
            $results[] = $this->geocode_location( $post_id );

            /*
             * Geocoding writes the coordinates straight to meta, which fires
             * no save_post, so the cache is told here instead. Per store, so
             * a batch that fails halfway still leaves the count honest.
             */
            self::sync_store( $post_id );
        }

        return [ 'results' => $results ];
    }

    /**
     * Re-geocode a single store and store what comes back.
     *
     * @since  3.0.0
     * @param  int $post_id The store post ID
     * @return array The outcome for this store
     */
    private function geocode_location( $post_id ) {
        $result = [
            'id'      => $post_id,
            'name'    => $this->plain_text( get_the_title( $post_id ) ),
            'success' => false,
        ];

        if ( 'wpsl_stores' !== get_post_type( $post_id ) ) {
            $result['message'] = __( 'This is not a store.', 'wp-store-locator' );

            return $result;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            $result['message'] = __( 'You are not allowed to edit this store.', 'wp-store-locator' );

            return $result;
        }

        /*
         * Rescue what is already stored before paying for a lookup: a
         * spreadsheet export writes a negative coordinate as '-31.961407,
         * with a leading apostrophe forcing it to text. The number is
         * right, only the notation is wrong, and re-geocoding it would be
         * a wasted API call.
         */
        $repaired = $this->repair_coordinates( $post_id );

        if ( $repaired ) {
            $this->geocode->set_metadata( $post_id, [ 'latlng' => $repaired ] );

            $result['success']  = true;
            $result['repaired'] = true;
            $result['lat']      = $repaired['lat'];
            $result['lng']      = $repaired['lng'];

            return $result;
        }

        $store_data = $this->store_data( $post_id );

        if ( ! $this->geocode->create_address( $store_data ) ) {
            $result['message'] = __( 'No address to look up.', 'wp-store-locator' );

            return $result;
        }

        $response = $this->geocode->geocode_location( $store_data, '', true );

        if ( empty( $response['latlng'] ) ) {
            $result['message'] = $this->error_message( $response );

            return $result;
        }

        $location_data = [ 'latlng' => $response['latlng'] ];

        if ( ! empty( $response['country_iso'] ) ) {
            $location_data['country_iso'] = $response['country_iso'];
        }

        /*
         * Only fill in a country or postcode the store is missing. What someone
         * typed outranks what the address happens to resolve to.
         */
        foreach ( [ 'country', 'zip' ] as $field ) {
            if ( ! empty( $response[ $field ] ) && empty( $store_data[ $field ] ) ) {
                $location_data[ $field ] = $response[ $field ];
            }
        }

        $this->geocode->set_metadata( $post_id, $location_data );

        $result['success'] = true;
        $result['lat']     = $location_data['latlng']['lat'];
        $result['lng']     = $location_data['latlng']['lng'];

        // A partial or country-level match still counts as a fix, but is worth saying out loud.
        if ( ! empty( $response['warning'] ) ) {
            $result['warning'] = $this->plain_text( $response['warning'] );
        }

        return $result;
    }

    /**
     * Salvage coordinates that are correct but 
     * written in a form nothing can read.
     *
     * Only two rewrites are attempted, both spreadsheet artifacts, and
     * nothing is returned unless the result is a real point on the map.
     * A wrong guess here costs a lookup rather than bad data.
     *
     * @since  3.0.0
     * @param  int $post_id The store post ID
     * @return array|false The repaired pair, or false if it could not be read
     */
    private function repair_coordinates( $post_id ) {
        $lat = Location_Utils::sanitize_coordinate( get_post_meta( $post_id, 'wpsl_lat', true ), 'lat' );
        $lng = Location_Utils::sanitize_coordinate( get_post_meta( $post_id, 'wpsl_lng', true ), 'lng' );

        if ( '' === $lat || '' === $lng ) {
            return false;
        }

        return Location_Utils::validate_latlng( $lat, $lng );
    }

    /**
     * Turn a message meant for HTML output into plain text.
     *
     * Geocoders build their messages with esc_html__(), so entities survive
     * ( type &quot;locality&quot; ) -- and the failure log is a textarea
     * filled with .val(), which would read them literally.
     *
     * @since  3.0.0
     * @param  string $text The message as the geocoder built it
     * @return string Plain text, safe to drop into a textarea
     */
    private function plain_text( $text ) {
        $text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );

        return trim( wp_strip_all_tags( $text ) );
    }

    /**
     * The address fields a store was saved with.
     *
     * @since  3.0.0
     * @param  int $post_id The store post ID
     * @return array The address parts
     */
    private function store_data( $post_id ) {
        $store_data = [];

        foreach ( $this->address_fields as $field ) {
            $store_data[ $field ] = (string) get_post_meta( $post_id, 'wpsl_' . $field, true );
        }

        return $store_data;
    }

    /**
     * Pull a readable reason out of a failed geocode response.
     *
     * @since  3.0.0
     * @param  mixed $response Whatever the geocoder handed back
     * @return string
     */
    private function error_message( $response ) {
        foreach ( [ 'message', 'error', 'status' ] as $key ) {
            if ( ! empty( $response[ $key ] ) && is_string( $response[ $key ] ) ) {
                return $this->plain_text( $response[ $key ] );
            }
        }

        return __( 'The geocoder did not return a location for this address.', 'wp-store-locator' );
    }
}