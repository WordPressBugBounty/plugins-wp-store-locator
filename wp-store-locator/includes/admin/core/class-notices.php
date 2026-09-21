<?php
/**
 * Admin Notices
 *
 * @author Tijmen Smit
 * @since  2.0.0
*/

namespace WPSL\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle the meta boxes.
 *
 * @since 2.0.0
 */
class Notices {

    /**
     * Holds the notices.
     *
     * @since 2.0.0
     * @var array
     */
    private $notices = [];

    /**
     * Whether to defer notice display to the addon page header position.
     *
     * When true, show() skips the standard WordPress all_admin_notices call
     * so the notice is preserved for show_for_addon_page(), which is called
     * from render_addon_header() after the WPSL header is output.
     *
     * @since 2.0.0
     * @var bool
     */
    private $deferred = false;

    /**
     * Holds the singleton instance.
     *
     * @since 3.0.0
     * @var \WPSL\Admin\Core\Notices
     */
    private static $instance = null;

    public function __construct() {
        $this->notices = get_option( 'wpsl_notices' );

        add_action( 'all_admin_notices', [ $this, 'show' ] );
    }

    /**
     * Get the singleton instance
     *
     * @since  3.0.0
     * @return \WPSL\Admin\Core\Notices The singleton instance
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Defer notice display so they appear after the WPSL addon page header.
     *
     * @since  2.0.0
     * @return void
     */
    public function defer() {
        $this->deferred = true;
    }

    /**
     * Output notices in the addon page header position.
     *
     * Called by render_addon_header() after the WPSL header has been output.
     * Resets the deferred flag and calls show() so notices appear between
     * the WPSL header and the page content.
     *
     * @since  2.0.0
     * @return void
     */
    public function show_for_addon_page() {
        $this->deferred = false;
        $this->show();
    }

    /**
     * Show one or more notices.
     *
     * @since  2.0.0
     * @return void
     */
    public function show() {
        if ( $this->deferred ) {
            return;
        }

        /*
         * Re-read instead of trusting the copy loaded in the constructor: save()
         * writes straight to the option, so anything stored later in this request
         * ( e.g. the CPT conversion notice added on admin_init ) would otherwise be
         * skipped here and then wiped by the update_option() below.
         */
        $this->notices = get_option( 'wpsl_notices' );

        if ( ! empty( $this->notices ) ) {
            $allowed_html = [
                'a' => [
                    'href'       => [],
                    'id'         => [],
                    'class'      => [],
                    'data-nonce' => [],
                    // Used by wpsl-navigation.js to jump to a settings section / field.
                    'data-item'  => [],
                    'data-focus' => [],
                    'title'      => [],
                    'target'     => []
                ],
                'p'  => [],
                'br' => [],
                'em' => [],
                'strong' => [
                    'class' => []
                ],
                'span' => [
                    'class' => []
                ],
                'ul' => [
                    'class' => []
                ],
                'li' => [
                    'class' => []
                ],
                'details' => [
                    'class' => []
                ],
                'summary' => [
                    'class' => []
                ]
            ];

            if ( wpsl_is_multi_array( $this->notices ) ) {
                foreach ( $this->notices as $k => $notice ) {
                    $this->create_notice_content( $notice, $allowed_html );
                }
            } else {
                $this->create_notice_content( $this->notices, $allowed_html );
            }

            // Empty the notices.
            $this->notices = [];
            update_option( 'wpsl_notices', $this->notices ,'no' );
        }
    }

    /**
     * Create the content shown in the notice.
     *
     * @since 2.1.0
     * @param array $notice
     * @param array $allowed_html
     */
    public function create_notice_content( $notice, $allowed_html ) {
        if ( ! is_array( $notice ) || ! isset( $notice['message'] ) ) {
            return;
        }

        switch ( $notice['type'] ) {
            case 'update':
            case 'updated':
                $class = 'updated';
                break;
            case 'warning':
                $class = 'notice-warning';
                break;
            case 'info':
                $class = 'notice-info';
                break;
            default:
                $class = 'error';
        }

        $class .= ' notice is-dismissible wpsl-notice-content';

        if ( isset( $notice['multiline'] ) && $notice['multiline'] ) {
            $notice_msg = wp_kses( $notice['message'], $allowed_html );
        } else {
            $message = is_array( $notice['message'] ) ? '' : $notice['message'];

            /*
             * Match WordPress' own single-line admin notices ( e.g. the
             * "Settings saved." notice from settings_errors() ), which bold
             * the whole message.
             */
            $message = '<strong>' . $message . '</strong>';

            if ( isset( $notice['url'] ) && isset( $notice['label'] ) ) {
                $url = '<a href="' . esc_url( $notice['url'] ) . '">' . esc_attr( $notice['label'] ) . '</a>.';

                $message = $message . ' ' . $url;
            }

            $notice_msg = '<p>' . wp_kses( $message, $allowed_html ) . '</p>';
        }

        $html = '<div class="' . esc_attr( $class ) . '">' . wp_kses_post( $notice_msg ) . '</div>';
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above
    }

    /**
     * Clear all stored notices.
     *
     * In the block editor, save_post fires via AJAX without a full page load,
     * so all_admin_notices never runs between saves. An error notice from a
     * failed save can therefore persist in the option and show up on the next
     * full page load (e.g. the stores list) even after the issue is fixed.
     * Calling clear() on a successful save prevents this.
     *
     * @since  3.0.0
     * @return void
     */
    public function clear() {
        $this->notices = [];
        update_option( 'wpsl_notices', $this->notices, 'no' );
    }

    /**
     * Save the notice.
     *
     * @since  2.0.0
     * @param  string $type      The type of notice, either 'update' or 'error'
     * @param  string $message   The user message
     * @param  bool   $multiline True if the message contains multiple lines ( used with notices created in add-ons ).
     * @return void
     */
    public function save( $type, $message, $multiline = false ) {
        $current_notices = get_option( 'wpsl_notices' );

        $new_notice = [
            'type' => $type
        ];

        if ( is_array( $message ) && isset( $message['message'] ) ) {
            $new_notice = array_merge( $message, $new_notice );
        } else {
            $new_notice['message'] = $message;
        }

        if ( $multiline ) {
            $new_notice['multiline'] = true;
        }

        // Check if this notice already exists to prevent duplicates
        if ( $current_notices ) {

            // Convert to multi-array if it's not already
            if ( ! wpsl_is_multi_array( $current_notices ) ) {
                $current_notices = [ $current_notices ];
            }

            // Check for duplicates
            $duplicate_found = false;

            foreach ( $current_notices as $notice ) {
                if ( isset( $notice['message'] ) && isset( $new_notice['message'] ) &&
                     $notice['message'] === $new_notice['message'] &&
                     $notice['type'] === $new_notice['type'] ) {
                    $duplicate_found = true;
                    break;
                }
            }

            if ( ! $duplicate_found ) {
                array_push( $current_notices, $new_notice );
                update_option( 'wpsl_notices', $current_notices, 'no' );
            }
        } else {
            update_option( 'wpsl_notices', [ $new_notice ], 'no' );
        }
    }
}