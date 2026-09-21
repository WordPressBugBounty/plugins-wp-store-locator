<?php
/**
 * WPSL / Borlabs Cookie class
 *
 * @author Tijmen Smit
 * @todo remove
 * @since  2.2.22
 */

namespace WPSL\Core\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Borlabs_Cookie {

    /**
     * Class constructor
     *
     * @since 2.2.22
     */
    public function __construct() {
        if ( ! is_admin() ) {
            add_filter( 'borlabsCookie/bct/modify_content/wpstorelocator', [ $this, 'update_content_blocker' ], 10, 2 );
        }
    }

    /**
     * Check if the 'wpstorelocator' blocked content type exists.
     * If this is not the case, then we create it.
     *
     * @since  2.2.22
     * @return void
     */
    public function maybe_enable_bct() {
        $wpsl_bct_data = BorlabsCookieHelper()->getBlockedContentTypeDataByTypeId( 'wpstorelocator' );

        if ( ! $wpsl_bct_data ) {
            $this->enable();
        }
    }

    /**
     * Add support for the delayed loading of the
     * Google Maps library in the Borlabs Cookies plugin 
     * by adding a 'wpstorelocator' blocked content type.
     *
     * @since  2.2.22
     * @return void
     */
    public function enable() {
        /**
         * First, we delete old Blocked Content Types with the id wpstorelocator.
         * If the id doesn't exist, nothing happens.
         *
         * Doing so ensures that both plugins work as intended.
         */
        BorlabsCookieHelper()->deleteBlockedContentType( 'wpstorelocator' );

        // Add new Blocked Content Type wpstorelocator - if the BCT exists nothing happens
        BorlabsCookieHelper()->addBlockedContentType(
            'wpstorelocator',
            'WP Store Locator',
            '',
            [],
            '<div class="borlabs-cookie-bct bc-bct-iframe bc-bct-google-maps">
                <p class="bc-thumbnail"><img src="%%thumbnail%%" alt="%%name%%"></p>
                <div class="bc-text">
                    <p>' . _x( 'To protect your personal data, your connection to Google Maps has been blocked.<br>Click on <strong>Load map</strong> to unblock Google Maps.<br>By loading the map you accept the privacy policy of Google.<br>More information about Google\'s privacy policy can be found here <a href="https://policies.google.com/privacy?hl=en&amp;gl=en" target="_blank" rel="nofollow">Google - Privacy &amp; Terms</a> . ', 'Borlabs Cookie', 'wp-store-locator' ) . '</p>
                    <p><label><input type="checkbox" name="unblockAll" value="1" checked> ' . _x( 'Do not block Google Maps.', 'Borlabs Cookie', 'wp-store-locator' ) . '</label>
                    <a role="button" data-borlabs-cookie-unblock>' . _x( 'Load map', 'Borlabs Cookie', 'wp-store-locator' ) . '</a></p>
                </div>
            </div>',
            '',
            '',
            [
                'responsiveIframe' => false,
            ],
            true,
            true
        );
    }

    /**
     * Remove the 'wpstorelocator' blocked content type
     * from the Borlabs Cookie plugin.
     *
     * @since 2.2.22
     */
    public function disable() {
        if ( function_exists('BorlabsCookieHelper' ) ) {
            BorlabsCookieHelper()->deleteBlockedContentType( 'wpstorelocator' );
        }
    }

    /**
     * Return the blocked-content preview HTML for the WPSL content blocker.
     *
     * @since  2.2.22
     * @param  string $id      Unused. Passed by the Borlabs filter.
     * @param  string $content Unused. Passed by the Borlabs filter.
     * @return string The preview HTML shown while the content is blocked
     */
    public function update_content_blocker( $id, $content ) {
        // Get settings of the Blocked Content Type
        $wpsl_data = BorlabsCookieHelper()->getBlockedContentTypeDataByTypeId( 'wpstorelocator' );

        // Workaround, fixed in newer versions of Borlabs Cookie
        if ( ! isset($wpsl_data['settings']['unblockAll'] ) ) {
            $wpsl_data['settings']['unblockAll'] = false;
        }

        BorlabsCookieHelper()->updateBlockedContentTypeJavaScript(
            'wpstorelocator',
            wpsl_gmaps_bootstrap(),
            'wpslBorlabsCallback();',
            $wpsl_data['settings']
        );

        // Default thumbnail
        if ( defined( 'BORLABS_COOKIE_VERSION' ) && version_compare( BORLABS_COOKIE_VERSION, '2.2.36', '>=' )) {
            $thumbnail = BORLABS_COOKIE_PLUGIN_URL . 'assets/images/cb-maps.png';
        } else {
            $thumbnail = BORLABS_COOKIE_PLUGIN_URL . 'images/bct-google-maps.png';
        }

        // Get the title which was maybe set via title-attribute in a shortcode
        $title = BorlabsCookieHelper()->getCurrentTitleOfBlockedContentType();

        // If no title was set use the Blocked Content Type name as title
        if ( empty( $title ) ) {
            $title = $wpsl_data['name'];
        }

        // Replace text variables
        $wpsl_data['previewHTML'] = str_replace(
            [
                '%%name%%',
                '%%thumbnail%%',
            ],
            [
                $title,
                $thumbnail
            ],
            $wpsl_data['previewHTML']
        );

        /* Return the HTML that displays the information, that the original content was blocked */
        return $wpsl_data['previewHTML'];
    }
}