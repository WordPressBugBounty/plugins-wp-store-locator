<?php
/**
 * Handle JavaScript data for frontend scripts.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Frontend\Assets;

defined( 'ABSPATH' ) || exit;

use WPSL\Core\Settings\Manager as WpslSettings;
use WPSL\Core\I18n\Translations;
use WPSL\Core\Templates\Sections;
use WPSL\Core\Utils\Location_Utils;
use WPSL\Core\Templates\Filters;
use WPSL\Core\Container;

use WPSL\Frontend\Maps\Markers;
use WPSL\Frontend\Maps\Manager as MapsManager;
use WPSL\Frontend\State\Manager as StateManager;
use WPSL\Frontend\Shortcodes\Shortcodes;

class Resources {

    /**
     * The settings data.
     *
     * @since 3.0.0
     * @var   array
     */
    public $settings;

    /**
     * Translations service
     *
     * @since 3.0.0
     * @var \WPSL\Core\I18n\Translations
     */
    private $i18n;

    /**
     * Template sections service
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Sections
     */
    private $template_sections;

    /**
     * Markers service
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Maps\Markers
     */
    private $markers;

    /**
     * Maps manager service
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\Maps\Manager
     */
    private $maps_manager;
    
    /**
     * Template filters service
     *
     * @since 3.0.0
     * @var \WPSL\Core\Templates\Filters
     */
    private $template_filters;
    
    /**
     * State manager service
     *
     * @since 3.0.0
     * @var \WPSL\Frontend\State\Manager
     */
    private $state;

    /**
     * Container instance
     *
     * @since 3.0.0
     * @var \WPSL\Core\Container
     */
    private $container;

    /**
     * Class constructor
     *
     * @since 3.0.0
     * @param \WPSL\Core\Settings\Manager   $settings            Settings manager instance
     * @param \WPSL\Core\I18n\Translations  $i18n                Translations instance
     * @param \WPSL\Core\Templates\Sections $template_sections   Template sections service instance
     * @param \WPSL\Frontend\Maps\Markers   $markers             Markers service instance
     * @param \WPSL\Frontend\Maps\Manager   $maps_manager        Maps manager service instance
     * @param \WPSL\Core\Templates\Filters  $template_filters    Template filters service instance
     * @param \WPSL\Frontend\State\Manager  $state               Frontend state manager service instance
     * @param \WPSL\Core\Container          $container           Container instance
     */
    public function __construct( WpslSettings $settings, Translations $i18n, Sections $template_sections, Markers $markers, MapsManager $maps_manager, Filters $template_filters, StateManager $state, Container $container ) {
        $this->settings          = $settings->get_all();
        $this->i18n              = $i18n;
        $this->template_sections = $template_sections;
        $this->markers           = $markers;
        $this->maps_manager      = $maps_manager;
        $this->template_filters  = $template_filters;
        $this->state             = $state;
        $this->container         = $container;
    }

    /**
     * Safely get a setting value with fallback
     *
     * @since  3.0.0
     * @param  string $section The settings section
     * @param  string $key The setting key
     * @param  mixed  $default The default value if setting doesn't exist
     * @return mixed  The setting value or default
     */
    private function get_setting( $section, $key, $default = '' ) {
        if ( isset( $this->settings[ $section ][ $key ] ) ) {
            return $this->settings[ $section ][ $key ];
        }
        
        return $default;
    }

    /**
     * Get the localized data for frontend scripts
     *
     * @since  3.0.0
     * @param  string $page_type The page type (store_locator, store_page)
     * @return array  The localized data
     */
    public function get_localized_data( $page_type = 'store_locator' ) {
        $sl_settings = [];

        // Base settings structure matching the old format
        $base_settings = [
            'url'         => WPSL_URL,
            'scriptDebug' => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ),
            'api'         => $this->get_api_settings(),
            'map'         => $this->get_map_settings(),
            'search'      => [
                'directionsTravelMode' => wpsl_get_directions_travel_mode(),
            ],
            'markers'     => $this->markers->get_props(),
            'ux'          => [
                'storeUrl' => $this->settings['ux']['store_url'],
            ],
            'storeUrl'    => $this->settings['ux']['store_url'],
            'gdpr'        => wpsl_get_gdpr_handler(),
        ];

        $base_settings = $this->add_map_shapes( $base_settings );

        // For store locator pages, add additional settings
        if ( $page_type == 'store_locator' ) {

            /**
             * A name search matches the input against the location name, so
             * coordinates have nothing to search on. The settings page hides
             * the autocomplete and auto-locate checkboxes once name search is
             * picked, but a hidden checkbox keeps posting its old value, so
             * sites that switched over still carry the enabled flags -- and
             * auto_locate would run a geolocation search on pageload.
             */
            $is_name_search = $this->get_setting( 'search', 'search_method' ) === 'name';

            $sl_settings = [
                'search' => [
                    'ajaxurl'                => wpsl_get_ajax_url(),
                    'preloader'              => wpsl_get_preloader_url(),
                    'geoLocationPreloader'   => wpsl_get_geolocation_preloader_url(),
                    'inputOnly'              => $this->get_setting( 'search', 'input_only' ),
                    'autoComplete'           => $is_name_search ? 0 : $this->get_setting( 'search', 'autocomplete' ),
                    'autoLocate'             => [
                            'enabled' => $is_name_search ? 0 : $this->get_setting( 'search', 'auto_locate' ),
                            'format'  => $this->get_setting( 'search', 'auto_locate_format' ),
                            'trigger' => $this->get_setting( 'search', 'auto_locate_trigger' ),
                        ],
                    'draggableFormat'        => 'formatted_address',
                    'fullSearch'             => $this->maybe_enable_fullsearch( $base_settings['api'] ),
                    'autoLoad'               => $this->get_setting( 'map', 'autoload' ),
                    'autoloadHideDetails'    => $this->get_setting( 'map', 'autoload_start_latlng' ),
                    'categoryFilterOnly'     => $this->get_setting( 'search', 'category_filter_only' ),
                    'distanceUnit'           => wpsl_get_distance_unit(),
                    'directionRedirect'      => $this->get_setting( 'ux', 'direction_redirect' ),
                    /**
                     * Send coordinates instead of the address details in the
                     * Google Maps directions URL. For the rare location whose
                     * address Google does not recognise and so misplaces.
                     *
                     * Check the marker sits in the right place on the admin
                     * editor's preview map first, otherwise this will not fix
                     * it. Google Maps direction links only: the other map
                     * services always send coordinates to openstreetmap.org.
                     *
                     * @see https://developers.google.com/maps/documentation/directions/get-directions#destination
                     */
                    'forceDirectionCoordinates' => apply_filters( 'wpsl_force_direction_coordinates', false ),
                    'geoLocationTimeout'     => apply_filters( 'wpsl_geolocation_timeout', 7500 ),
                    'restrictions'           => $this->get_restriction_settings(),
                    'resultColumns'          => $this->get_setting( 'appearance', 'result_columns' ),
                    'namesEnabled'           => $is_name_search ? 1 : 0,
                    'widgetEnabled'          => 0,
                ],
                'ux' => [
                    // Honors a [wpsl template="..."] override; the custom
                    // section checks below key off this value as well.
                    'templateId'            => $this->get_active_template_id(),
                    'ctaButtons'            => ! empty( $this->settings['appearance']['cta']['enabled'] ),
                    'ctaDetailsButton'      => ! empty( $this->settings['appearance']['cta']['details'] ),
                    'markerEffect'          => $this->get_setting( 'ux', 'marker_effect' ),
                    'markerStreetView'      => $this->get_setting( 'ux', 'marker_streetview' ),
                    'markerZoomTo'          => $this->get_setting( 'ux', 'marker_zoom_to' ),
                    'keyboardFocusMinZoom'  => $this->get_setting( 'ux', 'keyboard_focus_min_zoom' ),
                    'newWindow'             => $this->get_setting( 'ux', 'new_window' ),
                    'resetMap'              => $this->get_setting( 'ux', 'reset_map' ),
                    'phoneUrl'              => $this->get_setting( 'ux', 'phone_url' ),
                    'clickableDetails'      => $this->get_setting( 'ux', 'clickable_contact_details' ),
                    //'moreInfoLocation'      => $this->get_setting( 'ux', 'more_info_location' ),
                    'mouseFocus'            => $this->get_setting( 'ux', 'mouse_focus' ),
                    'maxDropdownHeight'     => apply_filters( 'wpsl_max_dropdown_height', 300 ),
                    'enableStyledDropdowns' => $this->active_template_has_panel() ? true : apply_filters( 'wpsl_enable_styled_dropdowns', true ),
                    'addressEvent'          => $this->get_setting( 'ux', 'address_event' ),
                    'buttonStyles'          => $this->get_setting( 'appearance', 'button_styles' )
                ],
            ];

            // Mapbox & OpenStreetMaps can have restrictions set for multiple regions.
            if ( wpsl_has_multi_region_restrictions() && $this->get_setting( 'api', 'multiple_regions' ) ) {
                $sl_settings['api']['regions'] = implode( ',', $this->get_setting( 'api', 'multiple_regions' ) );
            }

            $custom_sections = $this->get_setting( 'appearance', 'active_custom_sections' );

            // Check we're using a custom template for the number results.
            if ( $custom_sections ) {
                if ( in_array( $sl_settings['ux']['templateId'] . '_number_results', $custom_sections ) ) {
                    $sl_settings['results']['customNumberResultsSection'] = true;
                }

                if ( in_array( $sl_settings['ux']['templateId'] . '_listing', $custom_sections ) ) {
                    $sl_settings['results']['customListingSection'] = true;
                }
            }

            // Always set moreInfoFields so hasMoreInfoData() works,
            // even when custom template sections are enabled.
            $sl_settings['results']['moreInfoFields'] = $this->get_more_info_fields();

            if ( $sl_settings['search']['autoComplete'] && $this->get_setting( 'search', 'autosubmit_autocomplete' ) ) {
                $sl_settings['search']['autoSubmitAutoComplete'] = 1;
            }

            if ( $this->set_skip_geocode_param() ) {
                $sl_settings['search']['skipGeocode'] = true;
            }

            /**
             * If no results are found then by default it will just show the
             * "No results found" text. This filter makes it possible to show
             * a custom HTML block instead of the "No results found" text.
             */
            $no_results_msg = apply_filters( 'wpsl_no_results', '' );

            if ( $no_results_msg ) {
                $sl_settings['search']['noResults'] = $no_results_msg;
            }

            /**
             * Used to determine whether to show possible
             * API error / warning messages on the front-end.
             */
            if ( is_user_logged_in() ) {
                $sl_settings['userLoggedin'] = 1;
            }

            /**
             * Console output is fine for any logged in user, but a notice
             * rendered in the locator itself is only actionable by someone who
             * can reach the settings page. Without this a subscriber on a shop
             * would be told to change an API plan they cannot see.
             */
            if ( current_user_can( 'manage_options' ) ) {
                $sl_settings['userCanManage'] = 1;
            }

            if ( $this->get_setting( 'api', 'active_map_service' ) === 'osm' ) {

                if ( $this->get_setting( 'search', 'force_postalcode' ) ) {
                    $sl_settings['api']['types'] = 'postcode';
                } else {
                    /**
                     * Not set by default, but it's possible to set it to country, state, city, settlement.
                     * 
                     * Unlike Mapbox where you can set multiple restrictions, 
                     * here we can only use a single one.
                     * 
                     * Values provided here are used to created a structured query
                     * https://nominatim.org/release-docs/latest/api/Search/#structured-query 
                     * 
                     * Note: input that matches a postcode seems to often be accepted 
                     * regardless what type you restrict the result to.
                     * 
                     * @see https://nominatim.org/release-docs/latest/api/Search/#result-restriction
                     */
                    $sl_settings['api']['types'] = apply_filters( 'wpsl_osm_featureTypes', 'city' );
                }
            }

            // Both OSM and Stadia use the regions setting for country restrictions.
            if ( $this->get_setting( 'api', 'active_map_service' ) === 'osm' || $this->get_setting( 'api', 'active_map_service' ) === 'stadia' ) {
                if ( $this->get_setting( 'api', 'multiple_regions' ) ) {
                    $sl_settings['api']['regions'] = implode( ',', $this->get_setting( 'api', 'multiple_regions' ) );
                }
            }

            /**
             * 'Force zip code only search' for Stadia.
             *
             * No server-side filter is sent: Stadia's layers=postalcode can
             * return the wrong postcode while claiming an exact match, so the
             * error is invisible. Without the filter, a wrong match comes back
             * as a low-quality 'fallback' the plugin can recognise and reject.
             *
             * The signal is passed through and the restriction is enforced on
             * the response instead ( see geocoding.isUsableGeocodeResult() in
             * wpsl-stadia.js ).
             */
            if ( $this->get_setting( 'api', 'active_map_service' ) === 'stadia' && $this->get_setting( 'search', 'force_postalcode' ) ) {
                $sl_settings['api']['types'] = 'postcode';
            }

            // If autocomplete is enabled include the required data.
            if ( $this->get_setting( 'search', 'autocomplete' ) && in_array( $this->get_setting( 'api', 'active_map_service' ), [ 'gmaps', 'mapbox', 'stadia' ] ) ) {

                if ( $this->get_setting( 'api', 'active_map_service' ) === 'mapbox' ) {
                    $sl_settings['api']['autoComplete'] = true;

                    if ( $this->get_setting( 'search', 'force_postalcode' ) ) {
                        $sl_settings['api']['types'] = 'postcode';
                    } else {
                        //@see https://docs.mapbox.com/api/search/search-box/#administrative-unit-types
                        $sl_settings['api']['types'] = implode( ',', [
                            'country', 
                            'region', 
                            'postcode', 
                            'district', 
                            'place', 
                            'locality', 
                            'neighborhood', 
                            //'street', -> triggers error
                            'address'
                        ] );
                    }
                }

                if ( $this->get_setting( 'api', 'active_map_service' ) === 'gmaps' ) {
                    $version = $this->settings['search']['api_versions']['gmaps']['autocomplete'];

                    /**
                     * New Places API ( for API keys created after March 1, 2025 )
                     *
                     * @see https://developers.google.com/maps/documentation/javascript/reference/autocomplete-data
                     * for a full list of supported fields.
                     */
                    if ( $version === 'latest' ) {
                        // Resolve the country restriction with the shortcode taking
                        // precedence over the single country filter and the
                        // settings-page multi-country restriction.
                        $gmaps_countries = $this->get_gmaps_autocomplete_countries();

                        $autocomplete_options = [];

                        // includedRegionCodes supports up to 15 countries. Only
                        // send it when there's actually a restriction; the API
                        // rejects an empty includedRegionCodes array.
                        if ( ! empty( $gmaps_countries ) ) {
                            $autocomplete_options['includedRegionCodes'] = array_slice( $gmaps_countries, 0, 15 );
                        }

                        if ( $this->get_setting( 'search', 'force_postalcode' ) ) {
                            $autocomplete_options['includedPrimaryTypes'] = [ 'postal_code' ];
                        } else {

                            /**
                             * Match the legacy Places Autocomplete default: restrict
                             * suggestions to the "(regions)" type collection ( cities,
                             * towns, villages, administrative areas ) instead of also
                             * returning businesses, street names and addresses.
                             *
                             * @see https://developers.google.com/maps/documentation/places/web-service/place-types#table-collections
                             */
                            $autocomplete_options['includedPrimaryTypes'] = [ '(regions)' ];
                        }

                        $sl_settings['api']['autoComplete'] = [
                            'options' => $autocomplete_options,
                        ];
                    } else {
                        $geocode_components = [];

                        if ( $this->get_setting( 'search', 'force_postalcode' ) ) {
                            $geocode_components['postalCode'] = true;
                        }

                        $sl_settings['api']['geocodeComponents'] = apply_filters( 'wpsl_geocode_components', $geocode_components );

                        /**
                         * If the 'Force zip code only search' option is enabled,
                         * then make sure to restrict the autocomplete results
                         * to zip codes only.
                         */
                        if ( $this->get_setting( 'search', 'force_postalcode' ) ) {
                            $sl_settings['api']['autoComplete'] = [
                                'options' => [
                                    'fields' => [ 'address_components' ],
                                    'types'  => [ 'postal_code' ]
                                ],
                            ];
                        } else {
                            $sl_settings['api']['autoComplete'] = [
                                'options' => [
                                    'fields' => [ 'geometry.location' ],
                                    'types'  => [ '(regions)' ]
                                ],
                            ];
                        }

                        /**
                         * Restrict the returned suggestions to one or more
                         * countries. Uses the same resolver as the "latest"
                         * Places API branch, so a [wpsl country="..."] shortcode
                         * takes precedence over the below-map single country
                         * filter and the settings-page multi-country restriction.
                         */
                        $gmaps_countries = $this->get_gmaps_autocomplete_countries();

                        if ( ! empty( $gmaps_countries ) ) {
                            // The legacy Places Autocomplete componentRestrictions.country supports up to 5 countries.
                            $sl_settings['api']['autoComplete']['options']['componentRestrictions']['country'] = array_slice( $gmaps_countries, 0, 5 );
                        }
                    }

                    $sl_settings['api']['autoComplete']['version'] = $version;
                }

                if ( $this->get_setting( 'api', 'active_map_service' ) === 'stadia' ) {
                    $sl_settings['api']['autoComplete'] = true;

                    /**
                     * Default the autocomplete to populated places ( towns,
                     * villages and cities ) instead of every layer. 
                     * 
                     * When the zip-code-only is enabled we suggest postal codes instead.
                     *
                     * @see https://docs.stadiamaps.com/geocoding-search-autocomplete/layers/
                     */
                    $default_stadia_layers = $this->get_setting( 'search', 'force_postalcode' )
                        ? [ 'postalcode' ]
                        : [ 'locality', 'localadmin', 'borough' ];

                    /**
                     * Filter the layers used for the Stadia Maps autocomplete request.
                     *
                     * @since 3.0.0
                     * @see   https://docs.stadiamaps.com/geocoding-search-autocomplete/layers/
                     * @param array|string $layers The layers to request. Defaults to populated places.
                     */
                    $stadia_layers = apply_filters( 'wpsl_stadia_autocomplete_layers', $default_stadia_layers );

                    // Accept either a comma-separated string or an array.
                    if ( is_string( $stadia_layers ) ) {
                        $stadia_layers = explode( ',', $stadia_layers );
                    }

                    if ( is_array( $stadia_layers ) && ! empty( $stadia_layers ) ) {
                        $valid_layers = [
                            'venue',
                            'address',
                            'street',
                            'neighbourhood',
                            'borough',
                            'localadmin',
                            'locality',
                            'county',
                            'macrocounty',
                            'region',
                            'macroregion',
                            'country',
                            'postalcode',
                            'coarse',
                        ];

                        // Normalize, validate against the allowed layers and drop duplicates.
                        $stadia_layers = array_map( 'trim', $stadia_layers );
                        $stadia_layers = array_map( 'strtolower', $stadia_layers );
                        $stadia_layers = array_values( array_unique( array_intersect( $stadia_layers, $valid_layers ) ) );

                        if ( ! empty( $stadia_layers ) ) {
                            $sl_settings['api']['layers'] = implode( ',', $stadia_layers );
                        }
                    }
                }
                
                $sl_settings = apply_filters( 'wpsl_autocomplete_options', $sl_settings );
            }

            /**
             * Restricting the geocoding results to a country uses the Geocoding API
             * component filter, which only supports a SINGLE country. So when exactly
             * one country is selected we apply a true geocode restriction here ( this
             * runs regardless of whether autocomplete is enabled or which Places API
             * version is used ). Multiple countries can't be enforced at the geocode
             * level, so those are handled server-side ( store filter ) + autocomplete.
             *
             * @see https://developers.google.com/maps/documentation/javascript/geocoding#ComponentFiltering
             */
            if ( $this->get_setting( 'api', 'active_map_service' ) === 'gmaps' ) {
                $shortcode_country = $this->get_shortcode_restriction_country();

                if ( $shortcode_country ) {

                    /**
                     * A [wpsl country="..."] shortcode restriction overrides the
                     * settings page, matching the autocomplete behavior and the
                     * Mapbox / Stadia geocoders ( which pass the shortcode
                     * country along with every geocode request ). For multiple
                     * countries the per-country retry always applies, since the
                     * shortcode is an explicit restriction for this page.
                     */
                    $gmaps_countries     = $this->normalize_country_codes( $shortcode_country );
                    $use_geocode_retries = true;
                } elseif ( $this->get_setting( 'api', 'region_restriction_type' ) === 'restrict' ) {
                    $gmaps_countries = is_array( $this->get_setting( 'api', 'multiple_regions' ) ) ? array_values( $this->get_setting( 'api', 'multiple_regions' ) ) : [];

                    /**
                     * For the settings-page restriction the multi-country retry
                     * is only needed for zip-only searches ( a bare postcode
                     * like "2000" exists in several countries ); regular multi
                     * country searches are handled server-side + autocomplete.
                     */
                    $use_geocode_retries = (bool) $this->get_setting( 'search', 'force_postalcode' );
                } else {
                    $gmaps_countries     = [];
                    $use_geocode_retries = false;
                }

                if ( count( $gmaps_countries ) === 1 ) {
                    $geocode_components = ( isset( $sl_settings['api']['geocodeComponents'] ) && is_array( $sl_settings['api']['geocodeComponents'] ) )
                        ? $sl_settings['api']['geocodeComponents']
                        : [];

                    $geocode_components['country'] = strtoupper( $gmaps_countries[0] );

                    $sl_settings['api']['geocodeComponents'] = apply_filters( 'wpsl_geocode_components', $geocode_components );
                } else if ( count( $gmaps_countries ) > 1 && $use_geocode_retries ) {

                    /**
                     * Google's Geocoding API componentRestrictions only accepts
                     * a single country, so a search restricted to multiple
                     * countries can't be resolved in one request. Expose the
                     * full list so the frontend retries the geocode against
                     * each country until it finds a match.
                     */
                    $sl_settings['api']['geocodeCountries'] = apply_filters( 'wpsl_geocode_countries', array_values( array_map( 'strtoupper', $gmaps_countries ) ) );
                }
            }
        }

        /**
         * Maybe include data that's required when the map assets are deferred
         * until consent. The enqueue side defers on both page types, so a
         * store page with a [wpsl_map] shortcode needs this data as well.
         */
        if ( $this->gdpr_defers_assets() ) {
            if ( $this->settings['api']['active_map_service'] == 'gmaps' ) {
                $sl_settings['api']['v'] = wpsl_get_script_version( 'gmaps' );
            }

            if ( $this->settings['api']['active_map_service'] == 'mapbox' ) {
                $sl_settings['api']['v']      = wpsl_get_script_version( 'mapbox_gl_js' );
                $sl_settings['api']['assets'] = $this->get_mapbox_assets();
            }

            /**
             * Leaflet has no single bootstrap URL, so the checkpoint gets the
             * whole chain ( tile bridge, clusters, routing ) in load order.
             */
            if ( in_array( $this->settings['api']['active_map_service'], [ 'osm', 'stadia' ], true ) ) {
                $sl_settings['api']['assets'] = $this->get_leaflet_assets();
            }
        }

        // Use array_replace_recursive to avoid duplicate scalar values being converted to arrays
        $settings = array_replace_recursive( $base_settings, $sl_settings );

        $shortcodes = $this->container->get( 'shortcodes' );

        // Check if we need to overwrite JS settings that are set through the [wpsl] shortcode.
        if ( isset( $shortcodes->atts['js'] ) ) {
            // Array settings that replace the base value whole instead of
            // being merged key by key: the tile layer config differs in
            // shape between raster and vector styles, so merging would
            // leave stale keys from the other type behind.
            $replace_settings = [ 'tileLayer' ];

            foreach ( $shortcodes->atts['js'] as $shortcode_key => $shortcode_val ) {
                /**
                 * The marker overrides are already resolved by
                 * Markers::get_props() ( including the retina filename
                 * handling and the conditional active marker ), so merging
                 * the raw filenames in again would undo that ( e.g. turning
                 * red@2x.png back into red.png ).
                 */
                if ( 'markers' === $shortcode_key ) {
                    continue;
                }

                if ( is_array( $shortcodes->atts['js'][$shortcode_key] ) ) {
                    foreach ( $shortcodes->atts['js'][$shortcode_key] as $setting => $value ) {
                        if ( is_array( $value ) && ! in_array( $setting, $replace_settings, true ) ) {
                            foreach ( $value as $child_setting => $child_value ) {
                                $settings[$shortcode_key][$setting][$child_setting] = $child_value;
                            }
                        } else {
                            $settings[$shortcode_key][$setting] = $value;
                        }
                    }
                } else {
                    $settings[$shortcode_key] = $shortcode_val;
                }
            }
        }

        return apply_filters( 'wpsl_js_settings', $settings );
    }

    /**
     * Add the admin-defined map shapes to the localized settings.
     *
     * The key is only written when active shapes actually exist. Sites
     * without them must not pay for the feature at all: no mapShapes key
     * means the frontend shapes module returns on its first line, and the
     * option ( autoload off ) is only read on pages that localize the
     * frontend scripts. A collection holding only deactivated shapes pays
     * nothing either -- the same rule as an empty one.
     *
     * @since  3.0.0
     * @param  array $settings The localized settings so far
     * @return array $settings The settings, with mapShapes when there are shapes
     */
    private function add_map_shapes( $settings ) {
        $collection = $this->container->get( 'shapes_repository' )->get_active_collection();

        if ( ! empty( $collection['features'] ) ) {
            $settings['mapShapes'] = $collection;
        }

        return $settings;
    }

    /**
     * Get the marker cluster title label.
     *
     * Used on its own for the store page localization, and is also included
     * in the full labels() array used on the store locator page.
     *
     * @since  3.0.0
     * @return string The cluster title label ( contains a %d placeholder ).
     */
    public function cluster_label() {
        /* translators: %d: number of markers in the cluster */
        return esc_html__( 'Cluster of %d markers', 'wp-store-locator' );
    }

    /**
     * Get the labels for frontend scripts
     *
     * @since  3.0.0
     * @return array The labels
     */
    public function labels() {
        $labels = [
            'preloader'         => $this->i18n->get_js_translation( 'preloader_label', esc_html__( 'Searching...', 'wp-store-locator' ) ),
            'loadingDirections' => $this->i18n->get_js_translation( 'loading_directions_label', esc_html__( 'Loading directions...', 'wp-store-locator' ) ),
            'noResults'         => $this->i18n->get_no_results_message(),
            'moreInfo'          => $this->i18n->get_js_translation( 'more_label', esc_html__( 'More info', 'wp-store-locator' ) ),
            'generalError'      => $this->i18n->get_js_translation( 'error_label', esc_html__( 'Something went wrong, please try again', 'wp-store-locator' ) ),
            'directions'        => $this->i18n->get_js_translation( 'directions_label', esc_html__( 'Directions', 'wp-store-locator' ) ),
            'noDirectionsFound' => $this->i18n->get_js_translation( 'no_directions_label', esc_html__( 'No route found between the origin and destination.', 'wp-store-locator' ) ),
            'noDirectionsFoundMessage' => $this->i18n->get_no_directions_message(),
            /* translators: %s: the word "enabled", linked to the Google Cloud console page where the Routes API can be enabled */
            'routesApiDisabled' => $this->i18n->get_js_translation( 'routes_api_disabled_label', esc_html__( 'The directions can only be rendered if the Routes API is %s.', 'wp-store-locator' ) ),
            'routesApiEnabledLink' => $this->i18n->get_js_translation( 'routes_api_enabled_link_label', esc_html__( 'enabled', 'wp-store-locator' ) ),
            'startPoint'        => $this->i18n->get_js_translation( 'start_label', esc_html__( 'Start location', 'wp-store-locator' ) ),
            'back'              => $this->i18n->get_js_translation( 'back_label', esc_html__( 'Back', 'wp-store-locator' ) ),
            'zoomHere'          => $this->i18n->get_js_translation( 'zoom_here_label', esc_html__( 'Zoom here', 'wp-store-locator' ) ),
            'newWindow'         => esc_html__( ' ,opens in new window', 'wp-store-locator' ), // For screen readers after the 'directions' link.
            'preloadLabel'      => esc_html__( 'Preloader', 'wp-store-locator' ),
            'adjustSearch'      => $this->i18n->get_adjust_search_text(),
            'close'             => esc_html__( 'Close', 'wp-store-locator' ),
            'apply'             => $this->i18n->get_js_translation( 'apply_label', esc_html__( 'Apply', 'wp-store-locator' ) ),
            /* translators: %d: number of markers in the cluster */
            'clusterTitle'      => esc_html__( 'Cluster of %d markers', 'wp-store-locator' ),
            /* translators: 1: line breaks */
            'technicalProblem'  => sprintf( esc_html__( 'Due to a technical problem this function is currently unavailable.%1$s Please try again later.', 'wp-store-locator' ), '</br></br>' ),
            'moreDetails'       => $this->i18n->get_js_translation( 'more_details_label', esc_html__( 'More details', 'wp-store-locator' ) ),
            'geoLocationDialog' => $this->i18n->get_js_translation( 'geolocation_dialog_label', esc_html__( 'Would you like to share your location to find nearby stores?', 'wp-store-locator' ) ),
            'geoLocationAccept' => $this->i18n->get_js_translation( 'geolocation_accept_label', esc_html__( 'Share Location', 'wp-store-locator' ) ),
            'geoLocationDecline' => $this->i18n->get_js_translation( 'geolocation_decline_label', esc_html__( 'No Thanks', 'wp-store-locator' ) ),
            'geoLocationLocating' => $this->i18n->get_js_translation( 'geolocation_locating_label', esc_html__( 'Determining your location…', 'wp-store-locator' ) ),
            //'retrySearch'       => sprintf( esc_html__( '%sNo results found.%s %sPlease adjust your search and try again.%s', 'wp-store-locator' ), '<p>', '</p>', '<p>', '</p>' )
        ];

        // Some labels are map service specific.
        if ( $this->settings['api']['active_map_service'] == 'osm' || $this->settings['api']['active_map_service'] == 'stadia' ) {
            $map_service_labels = [
                /* translators: 1: line breaks, 2: opening link tag, 3: closing link tag */
                'OpenRouteServiceError' => sprintf( esc_html__( 'The OpenRouteService returned code #. %1$s You can see the uptime and current status of the directions API %2$shere%3$s.', 'wp-store-locator' ), '<br><br>', '<a target="_blank" href="http://status.openrouteservice.org/">', '</a>' )
            ];
        } else {
            $map_service_labels = [
                'queryLimit' => $this->i18n->get_js_translation( 'limit_label', esc_html__( 'API usage limit reached', 'wp-store-locator' ) ),
                'streetView' => $this->i18n->get_js_translation( 'street_view_label', esc_html__( 'Street view', 'wp-store-locator' ) )
            ];
        }

        $labels = array_merge( $labels, $map_service_labels );

        /**
         * These labels are used when the openrouteservice API is active,
         * and the directions are rendered on the map.
         */
        if ( $this->settings['api']['active_map_service'] !== 'gmaps' ) {
            $labels['hour']  = esc_html__( 'hour', 'wp-store-locator' );
            $labels['hours'] = esc_html__( 'hours', 'wp-store-locator' );
            $labels['min']   = esc_html__( 'min', 'wp-store-locator' );
            $labels['mins']  = esc_html__( 'mins', 'wp-store-locator' );
        }

        // Check if we need to include the 'x number of results' label txt.
        if ( $this->settings['search']['number_results'] ) {
            $labels['numberResults']       = $this->i18n->get_js_translation( 'number_results_label', esc_html__( '{number} stores near you', 'wp-store-locator' ) );
            $labels['numberResultsSingle'] = $this->i18n->get_js_translation( 'number_results_single_label', esc_html__( '{number} store near you', 'wp-store-locator' ) );
        }

        /**
         * Do we need to show the user the option to show
         * the nearest locations if no results are found?
         */
        if ( $this->settings['search']['find_nearest_location' ] ) {
            /* translators: 1: line breaks, 2: opening link tag, 3: closing link tag */
            $labels['findNearestLocations'] = $this->i18n->get_js_translation( 'no_results_label', esc_html__( 'No results found.', 'wp-store-locator' ) ) . ' '. sprintf( __( '%1$s Ignore the search radius and %2$sshow the nearest location%3$s?', 'wp-store-locator' ), '<br><br>', '<a id="wpsl-search-nearest-btn" href="#">', '</a>' );
            /* translators: 1: line breaks */
            $labels['noNearbyLocations']    = sprintf( esc_html__( 'Still no results found. %1$s Please change the search input and try again.', 'wp-store-locator' ), '<br><br>' );
        }

        // Used to show more detailed API errors in the browser console
        if ( is_user_logged_in() ) {
            $labels['status']       = esc_html__( 'Status', 'wp-store-locator' );
            $labels['apiDebugInfo'] = esc_html__( 'WP Store Locator - API Debug Info', 'wp-store-locator' );
            $labels['apiResponse']  = esc_html__( 'API Response', 'wp-store-locator' );
            $labels['moreInfoConsoleError'] = esc_html__( 'For more information please see', 'wp-store-locator' );
        }

        /**
         * Shown in the locator when the Stadia reverse geocode is refused.
         *
         * The wording stays on what the key could not do rather than naming a
         * plan, because a referrer-restricted key returns the same 403.
         */
        if ( current_user_can( 'manage_options' ) ) {
            /*
             * Deliberately says nothing about where the coordinates came from.
             * The reverse geocode is not only the auto-locate's: border
             * enforcement and the statistics add-on force it on a plain text
             * search too, where "your current location" was simply wrong.
             */
            $labels['reverseDeniedText'] = esc_html__( 'The Stadia Maps reverse geocoding request failed, so the coordinates could not be converted into an address.', 'wp-store-locator' );

            /* translators: %s: the error message returned by the Stadia Maps API */
            $labels['reverseDeniedApiError'] = esc_html__( 'The Stadia Maps API returned this error: "%s"', 'wp-store-locator' );

            $labels['reverseDeniedUpgrade']    = esc_html__( 'Please upgrade your account', 'wp-store-locator' );
            $labels['reverseDeniedUpgradeUrl'] = wpsl_stadia_account_url();

            /* translators: %s: the HTTP status code followed by the API endpoint that was called */
            $labels['reverseDeniedRequest']   = esc_html__( 'Failed request: %s', 'wp-store-locator' );
            $labels['reverseDeniedAdminOnly'] = esc_html__( 'Only administrators see this notice.', 'wp-store-locator' );
            $labels['dismissNotice']          = esc_html__( 'Dismiss', 'wp-store-locator' );

            // The close button is built in JS, so it needs the same cross the
            // templates render through wpsl_get_svg_icon().
            $labels['closeIcon'] = wpsl_get_svg_icon( 'cross' );
        }

        // Places API error link text
        $labels['enablePlacesApi'] = esc_html__( 'Enable Places API (New)', 'wp-store-locator' );
        $labels['placesApiFallbackError'] = esc_html__( 'The autocomplete feature is not working. Please enable the Places API (New) in your Google Cloud Console.', 'wp-store-locator' );

        return apply_filters( 'wpsl_labels', $labels );
    }

    /**
     * The different geolocation errors.
     *
     * They are shown when the Geolocation API returns an error.
     *
     * @since  2.0.0
     * @return array $geolocation_errors
     */
    public function geolocation_errors() {
        $geolocation_errors = [
            'denied'       => __( 'The application does not have permission to use the Geolocation API.', 'wp-store-locator' ),
            'unavailable'  => __( 'Location information is unavailable.', 'wp-store-locator' ),
            'timeout'      => __( 'The geolocation request has timed out.', 'wp-store-locator' ),
            'generalError' => __( 'An unknown error has occurred.', 'wp-store-locator' )
        ];

        return $geolocation_errors;
    }

    /**
     * Get the API settings based on the selected map provider
     *
     * @since  3.0.0
     * @return array The API settings
     */
    private function get_api_settings() {
        $map_service = $this->get_setting( 'api', 'active_map_service' );
        
        $api_settings = [
            'provider' => $map_service,
            'language' => $this->get_setting( 'api', $map_service . '_' . 'language' ),
            'filters'  => $this->get_setting( 'api', 'filters' ),
        ];
        
        // Include provider specific settings
        switch ( $map_service ) {
            case 'gmaps':
                $api_settings['hasValidKey'] = $this->get_cached_api_validation( 'wpsl_valid_gmaps_browser_key' );
                $api_settings['libraries']   = wpsl_gmaps_libraries( $this->state->get_load_scripts() );
                $api_settings['key']         = $this->get_setting( 'api', 'gmaps_browser_key' );
                break;
            case 'mapbox':
                $api_settings['hasValidKey'] = $this->get_cached_api_validation( 'wpsl_valid_mapbox_key' );
                $api_settings['key']         = $this->get_setting( 'api', 'mapbox_key' );
                break;
            case 'osm':

                // Only include the status of the OpenRouteService API if we're using it.
                if ( ! $this->settings['ux']['direction_redirect'] ) {
                    $api_settings['hasValidRouteKey'] = $this->get_cached_api_validation( 'wpsl_valid_openrouteservice_key' );
                }

                $api_settings['tileLayer'] = wpsl_get_osm_tile_layer();
                break;
            case 'stadia':
                $api_settings['hasValidKey'] = $this->get_cached_api_validation( 'wpsl_valid_stadia_key' );
                $api_settings['key']         = $this->get_setting( 'api', 'stadia_key' );
                $api_settings['euEndpoints'] = (bool) $this->get_setting( 'api', 'stadia_eu_endpoints' );

                // Stadia uses the same key for routing.
                if ( ! $this->settings['ux']['direction_redirect'] ) {
                    $api_settings['hasValidRouteKey'] = $this->get_cached_api_validation( 'wpsl_valid_stadia_key' );
                }

                $api_settings['tileLayer'] = wpsl_get_stadia_tile_layer();
                break;
        }
        
        return apply_filters( 'wpsl_api_settings', $api_settings );
    }
    
    /**
     * Get the map settings
     * 
     * @since  3.0.0
     * @return array The map settings
     */
    private function get_map_settings() {
        $map_service = $this->get_setting( 'api', 'active_map_service' );

        if ( $map_service == 'gmaps' ) {
            $map_settings = [
                'type'                => $this->get_setting( 'map', 'type' ),
                'typeControl'         => $this->get_setting( 'map', 'type_control' ),
                'streetView'          => $this->get_setting( 'map', 'streetview' ),
                'gestureHandling'     => apply_filters( 'wpsl_gesture_handling', 'auto' ),
                'streetViewAvailable' => false,
            ];

            $selected_style = $this->settings['appearance']['map_style']['gmaps']['selected'];

            // Default to 'cloud_based' if no style is explicitly selected
            if ( empty( $selected_style ) ) {
                $selected_style = 'cloud_based';
            }

            $map_settings['styleSelected'] = $selected_style;

            if ( $selected_style == 'json' ) {
                $map_settings['style'] = wp_strip_all_tags( stripslashes( json_decode( $this->settings['appearance']['map_style']['gmaps']['json'] ) ) );
                // Client-side JSON styling requires the legacy marker class and
                // cannot be combined with a Map ID (Google ignores the styles when
                // a mapId is present), so we deliberately leave the mapId empty.
                $map_settings['mapId'] = '';
            } else {
                $cloud_based = $this->settings['appearance']['map_style']['gmaps']['cloud_based'];
                $map_settings['mapId'] = ! empty( $cloud_based ) ? $cloud_based : 'DEMO_MAP_ID';
            }
        }

        $map_settings['scrollWheel']     = (bool) $this->get_setting( 'map', 'scrollwheel' );
        $map_settings['controlPosition'] = $this->get_setting( 'map', 'control_position' );
        $map_settings['controls']        = $this->maps_manager->get_controls();
        // A [wpsl] city / state / country restriction geocodes its own start
        // location in Shortcodes::check_sl_shortcode_atts(), which overrides
        // this value through the atts['js'] merge.
        $map_settings['startLatLng']     = $this->get_setting( 'map', 'start_latlng', '' );
        $map_settings['zoomLevel']       = $this->get_setting( 'map', 'zoom_level', 3 );
        $map_settings['autoZoomLevel']   = $this->get_setting( 'map', 'auto_zoom_level', 15 );
        $map_settings['tabAnchor']       = $this->maps_manager->get_map_tab_anchor();
        $map_settings['tabAnchorReturn'] = apply_filters( 'wpsl_map_tab_anchor_return', false );
        $map_settings['fitBounds']       = $this->get_setting( 'map', 'run_fitbounds', '1' );

        if ( $map_service == 'mapbox' ) {
            $map_settings['style'] = wpsl_active_mapbox_style();
        }

        /**
         * If no start coordinates are provided, the we default the 
         * coordinates to the hq of the selected map provider.
         */
        if ( empty( $map_settings['startLatLng'] ) ) {
            $map_settings['startLatLng'] = wpsl_get_map_hq_coordinates( $map_service );
        }
                
        return apply_filters( 'wpsl_map_settings', $map_settings );
    }
    
    /**
     * Get the map service specific settings
     *
     * @since  3.0.0
     * @return array The map service settings
     */
    private function get_map_service_settings() {
        $map_service = $this->get_setting( 'api', 'active_map_service' );
        $settings    = [];

        switch ( $map_service ) {
            case 'gmaps':
                $settings = [
                    'key'          => $this->get_setting( 'api', 'browser_key' ),
                    'language'     => $this->get_setting( 'api', 'language' ),
                    'region'       => $this->get_setting( 'api', 'gmaps_region' ),
                    'restrict'     => ( $this->get_setting( 'api', 'region_restriction_type' ) === 'restrict' && is_array( $this->get_setting( 'api', 'multiple_regions' ) ) && count( $this->get_setting( 'api', 'multiple_regions' ) ) === 1 ),
                    'autocomplete' => $this->get_setting( 'search', 'autocomplete' )
                ];
                break;
            case 'mapbox':
                $settings = [
                    'key'          => $this->get_setting( 'api', 'mapbox_key' ),
                    'language'     => $this->get_setting( 'api', 'mapbox_language' ),
                    'region'       => $this->get_setting( 'api', 'multiple_regions' ),
                    'style'        => wpsl_active_mapbox_style(),
                    'autocomplete' => $this->get_setting( 'search', 'autocomplete' )
                ];
                break;
            case 'osm':
                $settings = [
                    'language'     => $this->get_setting( 'api', 'osm_language' ),
                    'region'       => $this->get_setting( 'api', 'multiple_regions' ),
                    'tileLayer'    => wpsl_get_osm_tile_layer(),
                    'autocomplete' => $this->get_setting( 'search', 'autocomplete' )
                ];
                break;
        }

        return $settings;
    }
    
    /**
     * Collect different settings used to
     * restrict the returned results.
     *
     * @since  3.0.0
     * @return array $restrictions
     */
    public function get_restriction_settings() {
        $search_restrictions = '';
        $dropdown_defaults   = $this->template_filters->get_default_restrictions();

        if ( $this->state->has_js_shortcode_att( 'js.search.restrictions' ) ) {
            $search_restrictions = $this->state->get_js_shortcode_att( 'js.search.restrictions' );
        }

        $restrictions = [
            'radius' => $dropdown_defaults['search_radius']
        ];

        $restrictions['maxResults'] = $dropdown_defaults['max_results'];

        if ( $search_restrictions ) {
            foreach ( $search_restrictions as $key => $value ) {
                $restrictions[$key] = $search_restrictions[$key];
            }
        }

        /**
         * Enforce borders restricts the results to the country of the searched
         * location. A [wpsl country="..."] shortcode is a deliberate, explicit
         * scope, so when one is set it takes precedence and borders is skipped;
         * otherwise the searched country gets OR'd into the shortcode countries
         * and results from outside them leak in.
         */
        if ( $this->get_setting( 'search', 'enforce_borders' ) && ! $this->get_shortcode_restriction_country() ) {
            $restrictions['borders'] = 1;
        }

        $category_filters = $this->get_setting( 'search', 'category_filter' );

        if ( $category_filters ) {
            $restrictions['categoryDefault'] = $category_filters;
        }

        return apply_filters( 'wpsl_search_restriction_settings', $restrictions );
    }

    /**
     * Check if we need to overwrite the value from the
     * "If the search input is a country or state, then return all results?"
     * option from the WPSL settings page.
     *
     * This happens when it's disabled on the settings page,
     * but the 'wpsl_api_response_restrictions' filter is
     * used to restrict the API results to a state / country.
     *
     * @since  3.0.0
     * @param  array $settings The settings array
     * @return bool $enabled
     */
    public function maybe_enable_fullsearch( $settings ) {
        if ( ! isset( $settings['filters'] ) ) {
            return;
        }

        // Mapbox / Nominatim field names
        $fields = [
            'country',
            'region',
            'state'
        ];

        $enabled = $this->get_setting( 'search', 'full_search', false );

        $filters = explode( ',', $settings['filters'] ) ;

        foreach ( $filters as $k => $filter ) {
            if ( in_array( $filters[$k], $fields ) ) {
                $enabled = true;

                break;
            }
        }

        return $enabled;
    }

    /**
     * Resolve the country restriction ( ISO codes ) applied to the Google Maps
     * autocomplete on the latest Places API, honoring, in order of precedence:
     *
     *   1. A [wpsl country="..."] shortcode restriction.
     *   2. The below-map single country filter ( appearance.country_filter ).
     *   3. The settings-page multi-country restriction ( "restrict" mode ).
     *
     * @since  3.0.0
     * @return array Lowercase ISO 3166-1 alpha-2 country codes ( possibly empty ).
     */
    private function get_gmaps_autocomplete_countries() {
        // 1. A shortcode country restriction overrides the settings page.
        $shortcode_country = $this->get_shortcode_restriction_country();

        if ( $shortcode_country ) {
            return $this->normalize_country_codes( $shortcode_country );
        }

        // 2. The below-map single country filter.
        if ( $this->get_setting( 'appearance', 'country_filter' ) && $this->get_setting( 'appearance', 'country_default' ) ) {
            return $this->normalize_country_codes( $this->get_setting( 'appearance', 'country_default' ) );
        }

        // 3. The settings-page multi-country restriction ( hard "restrict" only;
        // "bias" mode intentionally applies no hard autocomplete restriction ).
        if ( $this->get_setting( 'api', 'region_restriction_type' ) === 'restrict' && is_array( $this->get_setting( 'api', 'multiple_regions' ) ) ) {
            return $this->normalize_country_codes( array_values( $this->get_setting( 'api', 'multiple_regions' ) ) );
        }

        return [];
    }

    /**
     * Get the active template id, honoring a [wpsl template="..."] override.
     * The shortcode value is validated against the registered templates; an
     * unknown id falls back to the settings page template.
     *
     * @since  3.0.0
     * @return string The active template id.
     */
    private function get_active_template_id() {
        $shortcodes = $this->container->get( 'shortcodes' );

        if ( ! empty( $shortcodes->atts['template'] ) ) {
            $ids = array_column( wpsl_get_templates(), 'id' );

            if ( in_array( $shortcodes->atts['template'], $ids, true ) ) {
                return $shortcodes->atts['template'];
            }
        }

        return $this->get_setting( 'appearance', 'template_id' );
    }

    /**
     * Check if the template being rendered is a panel ( flexbox ) template.
     *
     * @since  3.0.0
     * @return bool True if the active template renders a #wpsl-panel.
     */
    private function active_template_has_panel() {
        $active_template = $this->get_active_template_id();

        foreach ( wpsl_get_templates() as $template ) {
            if ( isset( $template['id'] ) && $template['id'] === $active_template ) {
                return ! empty( $template['has_panel'] );
            }
        }

        return false;
    }

    /**
     * Get the country restriction set through the [wpsl country="..."] shortcode.
     *
     * @since  3.0.0
     * @return string Comma-separated country name(s) / code(s), or an empty string.
     */
    private function get_shortcode_restriction_country() {
        $shortcodes = $this->container->get( 'shortcodes' );

        if ( isset( $shortcodes->atts['js']['search']['restrictions']['country'] ) ) {
            return $shortcodes->atts['js']['search']['restrictions']['country'];
        }

        return '';
    }

    /**
     * Normalize a mix of country names and/or ISO codes into a list of unique,
     * lowercase ISO 3166-1 alpha-2 codes.
     *
     * Accepts a comma-separated string ( e.g. "de,be" or "Germany, Belgium" )
     * or an array. Values that can't be matched to a known country are dropped.
     *
     * @since  3.0.0
     * @param  string|array $value The country name(s) / code(s).
     * @return array
     */
    private function normalize_country_codes( $value ) {
        $regions     = wpsl_get_regions(); // Name => code
        $valid_codes = array_filter( array_values( $regions ) );

        // Build a case-insensitive full-name => code lookup.
        $name_to_code = [];

        foreach ( $regions as $name => $code ) {
            if ( $code ) {
                $name_to_code[ strtolower( $name ) ] = $code;
            }
        }

        $tokens = is_array( $value ) ? $value : explode( ',', (string) $value );
        $result = [];

        foreach ( $tokens as $token ) {
            $token = strtolower( trim( $token ) );

            if ( $token === '' ) {
                continue;
            }

            if ( strlen( $token ) === 2 && in_array( $token, $valid_codes, true ) ) {
                $result[] = $token;
            } else if ( isset( $name_to_code[ $token ] ) ) {
                $result[] = $name_to_code[ $token ];
            }
        }

        return array_values( array_unique( $result ) );
    }

    /**
     * Get the fields that we need to show in the more info section.
     *
     * @since   3.0.0
     * @return  array The field names shown in the more info section
     */
    private function get_more_info_fields() {
        $fields = ['hours', 'description', 'contact_details'];
        $ux = $this->settings['ux'];
        $found_fields = [];

        foreach ( $fields as $field ) {
            if ( isset( $ux[$field] ) && in_array( 'more_info', $ux[$field] ) ) {
    
                // If contact_details is found, replace it with its individual components
                if ( $field === 'contact_details' ) {
                    $found_fields = array_merge( $found_fields, ['phone', 'fax', 'email'] );
                } else {
                    $found_fields[] = $field;
                }
            }
        }
        
        return $found_fields;
    }

    /**
     * Check if marker clusters should be enabled.
     *
     * @since  3.0.0
     * @return bool
     */
    public function cluster_markers_active() {
        $shortcodes = $this->container->get( 'shortcodes' );
        
        $atts_to_check = ! empty( $shortcodes->detected_atts ) ? $shortcodes->detected_atts : $shortcodes->atts;
        
        $marker_clusters_val = '';
        if ( is_array( $atts_to_check ) ) {
            if ( array_key_exists( 'marker_clusters', $atts_to_check ) && $atts_to_check['marker_clusters'] !== '' ) {
                $marker_clusters_val = $atts_to_check['marker_clusters'];
            } elseif ( array_key_exists( 'marker_cluster', $atts_to_check ) && $atts_to_check['marker_cluster'] !== '' ) {
                $marker_clusters_val = $atts_to_check['marker_cluster'];
            }
        }
        
        if ( $marker_clusters_val !== '' ) {
            return filter_var( $marker_clusters_val, FILTER_VALIDATE_BOOLEAN );
        }

        /*
         * Early detection misses [wpsl_map] shortcodes rendered outside the post
         * content, so their cluster attribute never reaches the values checked
         * above. The per-map data is complete by wp_footer, so read the flag
         * from there: a clustering basic map loads the assets without clustering
         * the locator map too.
         */
        if ( $this->markers->basic_map_clusters_active() ) {
            return true;
        }

        // Fall back to settings page value
        return (bool) $this->get_setting( 'markers', 'marker_clusters', '0' );
    }

    /**
     * Whether the active GDPR handler keeps the map assets off the page
     * until the visitor consents.
     *
     * @since  3.0.0
     * @return bool
     */
    public function gdpr_defers_assets() {
        return in_array( $this->get_setting( 'gdpr', 'handler' ), [ 'wpsl', 'complianz' ], true );
    }

    /**
     * The Mapbox asset chain, in load order.
     *
     * Both the normal enqueue path and the GDPR checkpoint read this, so the
     * URLs and the conditions that select them only exist once. It has to be
     * a list rather than a single bootstrap URL because of the geocoder
     * plugin: it needs GL to have run first, and when it never arrives the
     * map still boots and then throws on MapboxGeocoder, taking every later
     * setup step down with it.
     *
     * @since  3.0.0
     * @return array{css: array, js: array}
     */
    public function get_mapbox_assets() {
        $gl_ver = wpsl_get_script_version( 'mapbox_gl_js' );

        $css = [
            [
                'handle'    => 'wpsl-mapbox',
                'url'       => apply_filters( 'wpsl_mapbox_gl_css', 'https://api.mapbox.com/mapbox-gl-js/v' . $gl_ver . '/mapbox-gl.css' ),
                'version'   => $gl_ver,
                'integrity' => '',
            ],
        ];

        $js = [
            [
                'handle'    => 'wpsl-mapbox-gl',
                'url'       => apply_filters( 'wpsl_mapbox_gl_js', 'https://api.mapbox.com/mapbox-gl-js/v' . $gl_ver . '/mapbox-gl.js' ),
                'version'   => $gl_ver,
                'deps'      => [],
                'integrity' => '',
            ],
        ];

        if ( $this->get_setting( 'search', 'autocomplete' ) ) {
            $geocoder_ver = wpsl_get_script_version( 'mapbox_gl_geocoder' );
            $geocoder_url = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-geocoder/v' . $geocoder_ver . '/mapbox-gl-geocoder';

            $css[] = [
                'handle'    => 'wpsl-mapbox-autocomplete',
                'url'       => apply_filters( 'wpsl_mapbox_geocoder_css', $geocoder_url . '.css' ),
                'version'   => $geocoder_ver,
                'integrity' => '',
            ];

            $js[] = [
                'handle'    => 'wpsl-mapbox-geocoder',
                'url'       => apply_filters( 'wpsl_mapbox_geocoder_js', $geocoder_url . '.min.js' ),
                'version'   => $geocoder_ver,
                'deps'      => [ 'wpsl-mapbox-gl' ],
                'integrity' => '',
            ];
        }

        return [
            'css' => $css,
            'js'  => $js,
        ];
    }

    /**
     * The Leaflet asset chain for the osm / stadia providers, in load order.
     *
     * Both the normal enqueue path and the GDPR checkpoint read this, so the
     * URLs and the conditions that select them only exist once.
     *
     * @since  3.0.0
     * @return array{css: array, js: array}
     */
    public function get_leaflet_assets() {
        $css = [
            [ 'handle' => 'wpsl-leaflet' ] + wpsl_get_library_asset( 'leaflet_css' ),
        ];

        $js = [
            [ 'handle' => 'wpsl-leaflet', 'deps' => [] ] + wpsl_get_library_asset( 'leaflet_js' ),
        ];

        // A vector tile source needs MapLibre GL plus the Leaflet bridge:
        // either the active service's tile layer is vector, or a
        // [wpsl_map] shortcode requested a vector style ( 'maplibre' ).
        $tile_layer = ( 'stadia' === wpsl_get_active_map_service() ) ? wpsl_get_stadia_tile_layer() : wpsl_get_osm_tile_layer();

        $needs_maplibre = ( isset( $tile_layer['type'] ) && 'vector' === $tile_layer['type'] )
            || in_array( 'maplibre', $this->state->get_load_scripts(), true );

        if ( $needs_maplibre ) {
            $css[] = [ 'handle' => 'wpsl-maplibre-gl' ] + wpsl_get_library_asset( 'maplibre_gl_css' );
            $js[]  = [ 'handle' => 'wpsl-maplibre-gl', 'deps' => [] ] + wpsl_get_library_asset( 'maplibre_gl_js' );
            $js[]  = [ 'handle' => 'wpsl-maplibre-gl-leaflet', 'deps' => [ 'wpsl-leaflet', 'wpsl-maplibre-gl' ] ] + wpsl_get_library_asset( 'maplibre_gl_leaflet_js' );
        }

        if ( $this->cluster_markers_active() ) {
            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

            $js[] = [
                'handle'    => 'wpsl-leaflet-markercluster',
                'url'       => WPSL_URL . 'assets/vendor/markerclusterer/osm/leaflet-markercluster' . $min . '.js',
                'version'   => WPSL_VERSION_NUM,
                'deps'      => [ 'wpsl-leaflet' ],
                'integrity' => '',
            ];
        }

        // Only required when the directions are shown on the map itself.
        $valid_key = ( $this->get_setting( 'api', 'active_map_service' ) === 'stadia' )
            ? get_option( 'wpsl_valid_stadia_key' )
            : get_option( 'wpsl_valid_openrouteservice_key' );

        if ( ! $this->settings['ux']['direction_redirect'] && $valid_key ) {
            $js[] = [
                'handle'    => 'wpsl-leaflet-encoded',
                'url'       => apply_filters( 'wpsl_leaflet_encoded_js', WPSL_URL . 'assets/vendor/leaflet/leaflet-encoded.min.js' ),
                'version'   => WPSL_VERSION_NUM,
                'deps'      => [ 'wpsl-leaflet' ],
                'integrity' => '',
            ];
        }

        return [
            'css' => $css,
            'js'  => $js,
        ];
    }

    /**
     * Make sure the user input is not geocoded
     * which is uncessary when for example a name
     * based search is used or other custom search types
     * that work without coordinates.
     *
     * This will exclude the coordinates, max radius
     * and possible statistics data from the ajax data.
     *
     * @since  3.0.0
     * @return true|void
     */
    public function set_skip_geocode_param() {
        $skip_geocode = apply_filters( 'wpsl_skip_geocode_params', false );

        if ( $this->get_setting( 'search', 'search_method' ) === 'name' || $skip_geocode ) {
            return true;
        }
    }

    /**
     * Get cached API validation status to reduce database queries.
     * Uses wp_cache to store validation status for the current request.
     *
     * @since  3.0.0
     * @param  string $option_name The option name to check
     * @return bool   Whether the API key is valid
     */
    private function get_cached_api_validation( $option_name ) {
        $cache_key = 'wpsl_api_validation_' . $option_name;
        $cached = wp_cache_get( $cache_key, 'wp-store-locator' );
        
        if ( $cached !== false ) {
            return (bool) $cached;
        }
        
        $is_valid = ( get_option( $option_name ) == '1' );
        wp_cache_set( $cache_key, $is_valid ? 1 : 0, 'wp-store-locator' );
        
        return $is_valid;
    }
}