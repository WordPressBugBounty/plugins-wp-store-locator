<?php
/**
 * Handle the front-end templates.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\Container;
use WPSL\Core\Templates\Sections;

use WPSL\Frontend\State\Manager as StateManager;

class Manager {

    /**
     * Settings manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Template sections instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Sections
     */
    private $template_sections;

    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Frontend state manager instance
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Container             $container           Container instance
     * @param \WPSL\Core\Settings\Manager      $settings            Settings manager instance
     * @param \WPSL\Core\Templates\Sections    $template_sections   Template sections instance
     * @param \WPSL\Frontend\State\Manager     $state               Frontend state manager instance
     */
    public function __construct( Container $container, WpslSettings $settings, Sections $template_sections, StateManager $state ) {
        $this->container = $container;
        $this->settings = $settings;
        $this->template_sections = $template_sections;
        $this->state = $state;

        add_filter( 'the_content', [ $this, 'cpt_template' ] );
    }

    /**
     * Create the wpsl post type output.
     *
     * If you want to create a custom template you need to
     * create a single-wpsl_stores.php file in your theme folder.
     *
     * You can see an example here https://wpstorelocator.co/document/create-custom-store-page-template/
     *
     * @since  2.0.0
     * @param  string $content
     * @return string $content
     */
    public function cpt_template( $content ) {
        global $post;
        
        // Bail early if $post is not set (e.g., during backend operations)
        if ( ! $post || ! isset( $post->ID ) ) {
            return $content;
        }
        
        // Prevent duplicate processing
        static $processed_posts = [];
        
        if ( isset( $processed_posts[$post->ID] ) ) {
            return $content;
        }

        $editor = $this->settings->get_group( 'editor' );

        $status = '';
        $skip_cpt_template = apply_filters( 'wpsl_skip_cpt_template', false );

        if ( isset( $post->post_type ) && $post->post_type == 'wpsl_stores' && is_single() && in_the_loop() && ! $skip_cpt_template ) {
            // Mark this post as processed
            $processed_posts[$post->ID] = true;
            
            $this->state->add_script( 'store_page' );

            $store_data = wpsl_get_service( 'store_data' );
            $status = $store_data->get_location_status_msg( $post->ID );

            $shortcodes = '[wpsl_map]';
            $shortcodes .= '[wpsl_address directions="true"]';

            /**
             * The opening hours are added by default, unless they are globally
             * disabled via the editor's "hide_hours" option, a location status
             * is shown, or the "landing page" target isn't selected for the
             * hours location setting. A custom single-wpsl_stores.php template
             * that calls [wpsl_hours] directly always overrides this.
             */
            $hours_locations = (array) $this->settings->get( 'ux', 'hours' );

            if ( ! $editor['hide_hours'] && ! $status && in_array( 'landing_page', $hours_locations, true ) ) {
                $shortcodes .= '[wpsl_hours]';
            }

            if ( $status ) {
                $shortcodes .= '<div class="wpsl-location-status"><p>' . $status . '</p></div>';
            }
            
            // Process shortcodes and add to content
            $content .= do_shortcode( $shortcodes );
        }

        return $content;
    }

    /**
     * Create the Underscore templates
     * used on the front-end to display
     * the location data.
     *
     * @since  3.0.0
     * @param  string $page_type
     * @return array  $templates The different template sections
     */
    public function collect_sections( $page_type ) {
        /*
         * Every wpsl_*_template filter fires from this method, so this is the
         * one place that guarantees the v2 $wpsl global exists before any
         * override callback runs — on both the [wpsl] and [wpsl_map] paths.
         * Does nothing unless a template section is actually being overridden.
         */
        wpsl_maybe_set_v2_global();

        $wpsl_settings = $this->settings->get_group( 'appearance' );

        // Default
        $sections = [
            'store_locator' => [
                'info_window',
            ],
            'store_page' => [
                'cpt_info_window'
            ],
        ];

        // See if we need to overwrite the template_id value
        $selected_template = '';
        $shortcodes = $this->container->get( 'shortcodes' );

        if ( isset( $shortcodes->atts['template'] ) && $shortcodes->atts['template'] ) {
            $templates = wpsl_get_templates();
            $ids       = array_column( $templates, 'id');

            if ( in_array( $shortcodes->atts['template'], $ids ) ) {
                $selected_template = $shortcodes->atts['template'];
            }
        }

        if ( ! $selected_template ) {
            $selected_template = $wpsl_settings['template_id'];
        }

        // Based on the selected template adjust the name of listing template
        $sections['store_locator'] = array_merge( $sections['store_locator'], [ 'listing' ] );

        if ( $this->settings->get( 'search', 'number_results' ) ) {
            $sections['store_locator'] = array_merge( $sections['store_locator'], [ 'number_results' ] );
        }

        // Online-only stores are rendered with their own listing template, so
        // make sure it's available to the frontend when the feature is enabled.
        if ( $this->settings->get( 'editor', 'enable_online_only' ) ) {
            $sections['store_locator'] = array_merge( $sections['store_locator'], [ 'online' ] );
        }

        $templates = [];

        foreach ( $sections[$page_type] as $section ) {
            $template = $this->template_sections->get( [ 'section' => $section ] );

            if ( isset( $template['html'] ) && $template['html'] !== '' ) {

                /**
                 * If '{{' exists in the template code, then we
                 * check if there's a function that we need to run
                 * between the '{{}}' values.
                 */
                if ( strpos( $template['html'], '{{' ) !== false ) {
                    $shortcodes = $this->container->get( 'shortcodes' );
                    $template['html'] = $this->template_sections->maybe_call_func( $template['html'], $shortcodes->atts ) . "\r\n";
                }

                if ( $section === 'basic_listing' ) {
                    $section = 'listing';
                }

                $templates[ wpsl_camel_case( $section ) ] = str_replace( [ "\r\n", "\t" ], '', $template['html'] );
            }
        }

        return $templates;
    }
}