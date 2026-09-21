import { state } from './wpsl-shared.js';
import { preloader } from './wpsl-preloader.js';
import { api } from './wpsl-api.js';
import { helpers } from './wpsl-helpers.js';
import { hours } from './wpsl-hours.js';
import { mapObjects, mapBootstrap } from './wpsl-map-bootstrap.js';
import { notice } from './wpsl-notice.js';

import { multiselect } from './settings/wpsl-multiselect.js';
import { codeMirror } from './settings/wpsl-codemirror.js';
import { navigation } from './settings/wpsl-navigation.js';
import { fieldsManager } from './settings/wpsl-fieldsmanager.js';
import { geocodeResponseTest } from './settings/wpsl-geocode-test.js';
import { dataManagement } from './settings/wpsl-data-management.js';
import { verifyKeys } from './settings/wpsl-verify-keys.js';
import { sectionCompare } from './settings/wpsl-section-compare.js';
import { hoursConverter } from './settings/wpsl-hours-converter.js';

/**
 * Settings page actions.
 *
 * @since 3.0.0
 */
export const settings = {
    addStartMarker: true,

    /**
     * Initialize the settings page modules and event handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        // Checkboxes stay hidden until this runs; even on failure they
        // must be unhidden.
        try {
            wpslSharedFuncs.createToggleSliders();
        } finally {
            jQuery( '#wpsl-settings-form' ).removeClass( 'wpsl-toggles-pending' );
        }

        this.checkStartPoint();
        this.bindHandlers();

        multiselect.init();
        hours.init();
        verifyKeys.init();

        fieldsManager.init();
        navigation.init();
        geocodeResponseTest.init();
        dataManagement.init();
        codeMirror.init();
        sectionCompare.init();
        hoursConverter.init();

        wpslSharedFuncs.bindInfoPopup();

        this.toggleRegionFields();
        this.toggleSearchMethodFields();
        this.toggleDirectionsLanguage();

        wp.hooks.doAction( 'wpslAdminSettingsInit' );
    },

    /**
     * Toggle Google Maps region fields based on the selected "Region filtering"
     * type. Soft bias shows the Map region select; hard restrict shows the
     * countries multiselect. Only applies to Google Maps -- OSM/Mapbox always
     * restrict, so their multiselect is left as-is.
     *
     * @returns {void}
     */
    toggleRegionFields: function() {
        if ( jQuery( '#wpsl-map-service' ).val() !== 'gmaps' ) {
            return;
        }

        const $bias     = jQuery( '#wpsl-region-bias-field' );
        const $restrict = jQuery( '#wpsl-region-restrict-field' );

        if ( jQuery( '#wpsl-region-restriction-type' ).val() === 'restrict' ) {
            $bias.hide();
            $restrict.css( 'display', 'flex' );
        } else {
            $bias.css( 'display', 'flex' );
            $restrict.hide();
        }
    },

    /**
     * Toggle settings fields based on the selected search method. Hides
     * autocomplete and language/country restriction options for every provider
     * when Name Search is selected, and shows a warning tooltip on the API
     * section explaining why.
     *
     * @returns {void}
     */
    toggleSearchMethodFields: function() {
        const method = jQuery( '#wpsl-search-method' ).val();
        const $autoLocate = jQuery( '#wpsl-auto-locate' ).closest( 'p' );
        const $autoLocateConditional = $autoLocate.next( '.wpsl-conditional-option' );
        const $autocomplete = jQuery( '#wpsl-search-autocomplete' ).closest( 'p' );
        const $autocompleteConditional = $autocomplete.next( '.wpsl-conditional-option' );
        const $enforceBorders = jQuery( '#wpsl-enforce-borders' ).closest( 'p' );
        const $fullSearch = jQuery( '#wpsl-full-search' ).closest( 'p' );

        const $apiLanguage = jQuery( '#wpsl-api-language' ).closest( 'p' );
        const $regionRestrictionType = jQuery( '#wpsl-region-restriction-type' ).closest( 'p' );
        const $regionBiasField = jQuery( '#wpsl-region-bias-field' );
        const $regionRestrictField = jQuery( '#wpsl-region-restrict-field' );

        // Name search never geocodes, so geocode language selects are hidden
        // for every provider, not just Google Maps.
        const $providerLanguages = jQuery( '#wpsl-api-mapbox-language, #wpsl-api-osm-language, #wpsl-api-stadia-language' ).closest( 'p' );
        const $nameSearchNotice = jQuery( '#wpsl-name-search-notice' );

        // Additional Search settings fields.
        const $forcePostalcode = jQuery( '#wpsl-force-postalcode' ).closest( 'p' );
        const $radiusFilter = jQuery( '#wpsl-radius_filter' ).closest( 'p' );
        const $findNearest = jQuery( '#wpsl-find-nearest-location' ).closest( 'p' );
        const $searchRadiusOptions = jQuery( '#wpsl-search-radius' ).closest( 'p' );
        const $distanceUnit = jQuery( '#wpsl-distance-km' ).closest( 'p' );

        // "Sort by" stays visible, but distance sorting doesn't exist for
        // name search, so only its distance option is toggled.
        const $sortBy = jQuery( '#wpsl-sort-by' );
        const $sortByDistance = $sortBy.find( 'option[value="distance"]' );

        if ( method === 'name' ) {
            $autoLocate
                .add( $autoLocateConditional )
                .add( $autocomplete )
                .add( $autocompleteConditional )
                .add( $enforceBorders )
                .add( $fullSearch )
                .add( $apiLanguage )
                .add( $providerLanguages )
                .add( $regionRestrictionType )
                .add( $regionBiasField )
                .add( $regionRestrictField )
                .add( $forcePostalcode )
                .add( $radiusFilter )
                .add( $findNearest )
                .add( $searchRadiusOptions )
                .add( $distanceUnit )
                .hide();

            // Move off distance before disabling it, otherwise the invalid
            // value stays selected and gets saved.
            if ( $sortBy.val() === 'distance' ) {
                $sortBy.val( $sortBy.find( 'option' ).not( '[value="distance"]' ).first().val() );
            }
            $sortByDistance.prop( 'disabled', true ).hide();

            $nameSearchNotice.show();
        } else {
            const mapService = jQuery( '#wpsl-map-service' ).val();

            $nameSearchNotice.hide();

            // Restore the distance sort option when leaving name search.
            $sortByDistance.prop( 'disabled', false ).show();

            $autoLocate.css( 'display', 'flex' );
            if ( jQuery( '#wpsl-auto-locate' ).is( ':checked' ) ) {
                $autoLocateConditional.show();
            }

            if ( mapService !== 'osm' ) {
                $autocomplete.css( 'display', 'flex' );
                if ( jQuery( '#wpsl-search-autocomplete' ).is( ':checked' ) ) {
                    $autocompleteConditional.show();
                }
            }

            $enforceBorders.css( 'display', 'flex' );
            $fullSearch.css( 'display', 'flex' );
            
            $forcePostalcode.css( 'display', 'flex' );
            $radiusFilter.css( 'display', 'flex' );
            $findNearest.css( 'display', 'flex' );
            $searchRadiusOptions.css( 'display', 'flex' );
            $distanceUnit.css( 'display', 'flex' );

            if ( mapService === 'gmaps' ) {
                $apiLanguage.css( 'display', 'flex' );
                $regionRestrictionType.css( 'display', 'flex' );
                this.toggleRegionFields();
            } else if ( mapService === 'mapbox' || mapService === 'osm' || mapService === 'stadia' ) {
                jQuery( '#wpsl-api-' + mapService + '-language' ).closest( 'p' ).css( 'display', 'flex' );
                $regionRestrictField.css( 'display', 'flex' );
            }
        }
    },

    /**
     * The Openrouteservice language only affects directions drawn through its
     * API. That call is skipped without an API key, and again when the route
     * opens on an external site instead of the embedded map, so the option is
     * hidden unless it has something to apply to.
     *
     * @returns {void}
     */
    toggleDirectionsLanguage: function() {
        const $key = jQuery( '#wpsl-api-openrouteservice-key' );
        if ( ! $key.length ) {
            return;
        }

        const applies = $key.val().trim() && ! jQuery( '#wpsl-direction-redirect' ).is( ':checked' );

        jQuery( '#wpsl-api-openrouteservice-language' ).closest( 'p' ).css( 'display', applies ? 'flex' : 'none' );
    },

    /**
     * If no start location is set, make the exclamation mark red to flag it.
     *
     * @returns {void}
     */
    checkStartPoint: function() {
        if ( jQuery( '#wpsl-latlng' ).length && ! jQuery( '#wpsl-latlng' ).val() ) {
            jQuery( '#wpsl-latlng' ).siblings( 'label' ).find( '.wpsl-info' ).addClass( 'wpsl-required-setting' );
        }
    },

    /**
     * Bind event handlers for the settings page.
     *
     * @returns {void}
     */
    bindHandlers: function() {
        jQuery( '#wpsl-search-method' ).on( 'change', function() {
            settings.toggleSearchMethodFields();
        });

        jQuery( '#wpsl-label-panel-select' ).on( 'change', function() {
            const panel = jQuery( this ).val();
            const $labels = jQuery( '#wpsl-labels' );

            $labels.find( '.wpsl-labels-panel' ).removeClass( 'wpsl-active' );
            $labels.find( '.wpsl-labels-panel-' + panel ).addClass( 'wpsl-active' );
        });

        jQuery( '#wpsl-template-list' ).on( 'change', function() {
            const isFullPage = jQuery( this).val() === 'full_page';
            const $sectionList = jQuery( '#wpsl-section-list' );
            const $allOptions = $sectionList.find( 'option' );
            const $fullPageOptions = $allOptions.filter( '.wpsl-fullpage-template-option' );
            const $nonFullPageOptions = $allOptions.not( '.wpsl-fullpage-template-option' );

            $nonFullPageOptions.toggle( ! isFullPage );
            $fullPageOptions.toggle( isFullPage );

            const currentSelection = $sectionList.val();

            if ( isFullPage ) {
                if ( ! $fullPageOptions.filter( ':selected' ).length ) {
                    $sectionList.val( $fullPageOptions.first().val() );
                }
            } else {
                if ( $fullPageOptions.filter( ':selected' ).length ) {
                    $sectionList.val( $nonFullPageOptions.first().val() );
                }
            }

            if ( $sectionList.val() !== currentSelection ) {
                $sectionList.trigger( 'change' );
            }
        });

        // Flush the transient / Nominatim geocode cache.
        jQuery( '.wpsl-flush-cache' ).on( 'click', function() {
            if ( jQuery( this ).hasClass( 'disabled' ) ) {
                return;
            }

            preloader.add( jQuery( this ), 'flushCache' );
        });

        // Retry a failed 2.x -> 3.0 settings migration.
        jQuery( '#wpsl-retry-migration' ).on( 'click', function() {
            if ( jQuery( this ).hasClass( 'disabled' ) ) {
                return;
            }

            preloader.add( jQuery( this ), 'retryMigration' );
        });

        jQuery( '#wpsl-osm-styles' ).on( 'change', function() {
            if ( jQuery( this ).val() === 'default' ) {
                jQuery( '#wpsl-mapbox-styles, .wpsl-style-action' ).hide();
            } else {
                jQuery( '#wpsl-mapbox-styles, .wpsl-style-action' ).show();
                const map = mapObjects.get();

                map.invalidateSize(); // Fix grey areas on the map.
            }
        });

        // Delegated marker selection: the Marker Manager appends custom
        // markers to the picker lists after page load.
        jQuery( document ).on( 'click', '.wpsl-marker-list input[type=radio]', function() {
            jQuery( this ).parents( '.wpsl-marker-list' ).find( 'li' ).removeClass();
            jQuery( this ).parent( 'li' ).addClass( 'wpsl-active-marker' );
        });

        jQuery( document ).on( 'click', '.wpsl-marker-list li', function() {
            jQuery( this ).parents( '.wpsl-marker-list' ).find( 'input' ).prop( 'checked', false );
            jQuery( this ).find( 'input' ).prop( 'checked', true );
            jQuery( this ).siblings().removeClass();
            jQuery( this ).addClass( 'wpsl-active-marker' );
        });

        // Picker handlers live in wpsl-shared-funcs.js; the "+ Color" panel
        // below is settings-only but closes through the returned helpers.
        const leavePanelMode = wpslSharedFuncs.initMarkerPickers().leavePanelMode;

        /**
         * Derives a marker's outline color from its fill: lightness halved,
         * saturation nudged up, like the shipped pairs. Greys skip the
         * saturation push -- it would invent a hue.
         *
         * @since   3.0.0
         * @param   {Object} picker - A WPSL_ColorPicker, reused for its color maths.
         * @param   {string} hex    - The chosen fill color.
         * @returns {string} The derived outline color.
         */
        const derivedOutline = function( picker, hex ) {
            const hsl = picker.hexToHsl( hex );

            return picker.hslToHex( hsl.h, hsl.s > 0 ? Math.min( 100, hsl.s + 15 ) : 0, hsl.l * 0.55 );
        };

        /**
         * A popover's "Custom markers" grid, or an empty set if none.
         * Matched by position -- a second grid only exists when custom
         * markers do -- not by the translated heading text.
         *
         * @since   3.0.0
         * @param   {Object} $popover - The popover to look in.
         * @returns {Object} The custom grid, or an empty jQuery set.
         */
        const customMarkerGrid = function( $popover ) {
            const $grids = $popover.find( '.wpsl-marker-picker-grid' );

            return $grids.length > 1 ? $grids.last() : jQuery();
        };

        /**
         * Builds a picker column's create panel on first open, returns cached
         * parts on later opens.
         *
         * @since   3.0.0
         * @param   {Object} $panel - The panel container to fill.
         * @returns {Object} The panel's parts, keyed by role.
         */
        const buildPanel = function( $panel ) {
            const cached = $panel.data( 'wpsl-panel-parts' );

            if ( cached ) {
                return cached;
            }

            const strings = wpslMarkerCreate.strings;

            const type = $panel.closest( '.wpsl-marker-picker' ).attr( 'data-marker-type' ) || '';

            /**
             * One color row in Marker Studio markup (.wpsl-ms-color), minus
             * the hex readout. Label set as text, never HTML -- it is
             * translated, and markup would let a translation inject HTML.
             */
            const buildColorRow = function( id, labelText, value ) {
                const $rowEl = jQuery( '<div class="wpsl-ms-color"></div>' );
                const $head  = jQuery( '<div class="wpsl-ms-color-head"></div>' );
                const $label = jQuery( '<label class="wpsl-ms-color-label"></label>' ).attr( 'for', id ).text( labelText );

                const $rowField = jQuery( '<input type="text" class="wpsl-marker-picker-color" />' )
                    .attr( 'id', id )
                    .attr( 'data-default', value )
                    .val( value );

                $head.append( $label, $rowField );
                $rowEl.append( $head );

                return { row: $rowEl, field: $rowField };
            };

            const $heading = jQuery( '<p class="wpsl-marker-picker-group"></p>' ).text( strings.newColor );
            const $actions = jQuery( '<div class="wpsl-marker-picker-panel-actions"></div>' );

            const $error = jQuery( '<p class="wpsl-marker-picker-panel-error"></p>' );

            // The marker the save would create, redrawn live as colors change.
            const $preview = jQuery( '<div class="wpsl-marker-picker-panel-preview"></div>' );

            const $previewImg = jQuery( '<img alt="" />' ).css( 'max-height', parseInt( wpslMarkerCreate.previewHeight, 10 ) + 'px' );

            $preview.append( $previewImg );

            /*
             * Fill, Dot, Outline -- the Studio's row order.
             */
            const fillRow    = buildColorRow( 'wpsl-marker-picker-fill-' + type, strings.fill, '#3680b1' );
            const iconRow    = buildColorRow( 'wpsl-marker-picker-icon-' + type, strings.dot, '#ffffff' );
            const outlineRow = buildColorRow( 'wpsl-marker-picker-outline-' + type, strings.outline, '' );

            const $save   = jQuery( '<button type="button" class="button button-primary"></button>' ).text( strings.addAction );
            const $cancel = jQuery( '<button type="button" class="button"></button>' ).text( strings.cancel );

            $actions.append( $save, $cancel );
            $panel.append( $heading, $preview, fillRow.row, iconRow.row, outlineRow.row, $actions );

            fillRow.field.wpslColorPicker( { position: 'left' } );
            iconRow.field.wpslColorPicker( { position: 'left' } );

            const parts = {
                field:        fillRow.field,
                picker:       fillRow.field.data( 'wpsl-color-picker' ),
                iconField:    iconRow.field,
                outlineField: outlineRow.field,
                save:         $save,
                cancel:       $cancel,

                showError: function( message ) {
                    $error.text( message ).insertBefore( $actions );
                },

                clearError: function() {
                    $error.detach();
                }
            };

            // Outline starts on the fill's derived pairing; data-default is
            // set before init so "restore default" returns to it.
            const initialOutline = derivedOutline( parts.picker, fillRow.field.val() );

            outlineRow.field.val( initialOutline ).attr( 'data-default', initialOutline );
            outlineRow.field.wpslColorPicker( { position: 'left' } );
            parts.outlinePicker = outlineRow.field.data( 'wpsl-color-picker' );

            // Tells a user-chosen outline from an auto-derived one.
            let lastDerived = initialOutline;

            /**
             * Sets a picker without firing change: updateUI( true ) repaints
             * but skips the input write, so the field is set by hand after.
             */
            const setPickerColor = function( picker, hex ) {
                const hsl = picker.hexToHsl( hex );

                picker.currentHue   = hsl.h;
                picker.currentSat   = hsl.s;
                picker.currentLight = hsl.l;
                picker.updateUI( true );
                picker.element.val( hex );
            };

            // Redraws the preview from the localized PHP template. The hex
            // guards reject a half-typed value, keeping the last preview.
            const isHex = function( value ) {
                return /^#[0-9a-fA-F]{6}$/.test( value );
            };

            const refreshPreview = function() {
                const fill    = fillRow.field.val();
                const icon    = iconRow.field.val();
                const outline = outlineRow.field.val();

                if ( wpslMarkerCreate.previewSvg && isHex( fill ) && isHex( icon ) && isHex( outline ) ) {
                    const svg = wpslMarkerCreate.previewSvg
                        .split( '%%FILL%%' ).join( fill )
                        .split( '%%ICON%%' ).join( icon )
                        .split( '%%OUTLINE%%' ).join( outline );

                    $previewImg.attr( 'src', 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent( svg ) );
                }
            };

            fillRow.field.on( 'change', function() {
                const fill    = jQuery( this ).val();
                const derived = derivedOutline( parts.picker, fill );

                if ( outlineRow.field.val() === lastDerived ) {
                    setPickerColor( parts.outlinePicker, derived );
                }

                lastDerived = derived;

                // Restore-default on the outline now returns to the pairing of
                // the current fill, also putting it back on auto-follow.
                parts.outlinePicker.defaultColor = derived;
                outlineRow.field.attr( 'data-default', derived );

                refreshPreview();
            } );

            iconRow.field.on( 'change', refreshPreview );
            outlineRow.field.on( 'change', refreshPreview );

            refreshPreview();
            $panel.data( 'wpsl-panel-parts', parts );

            return parts;
        };

        jQuery( document ).on( 'click', '.wpsl-marker-picker-add', function( e ) {
            e.preventDefault();

            if ( typeof wpslMarkerCreate === 'undefined' || ! jQuery.fn.wpslColorPicker ) {
                return;
            }

            const $popover = jQuery( this ).closest( '.wpsl-marker-picker-popover' );
            const $panel   = $popover.find( '.wpsl-marker-picker-panel' );
            const parts    = buildPanel( $panel );

            parts.clearError();
            $popover.addClass( 'is-panel' );
            $panel.prop( 'hidden', false );
        } );

        jQuery( document ).on( 'click', '.wpsl-marker-picker-panel-actions .button:not(.button-primary)', function( e ) {
            e.preventDefault();

            const $popover = jQuery( this ).closest( '.wpsl-marker-picker-popover' );
            leavePanelMode( $popover );
            $popover.siblings( '.wpsl-marker-picker-toggle' ).trigger( 'focus' );
        } );

        jQuery( document ).on( 'click', '.wpsl-marker-picker-panel-actions .button-primary', function( e ) {
            e.preventDefault();

            const $popover      = jQuery( this ).closest( '.wpsl-marker-picker-popover' );
            const $originPicker = $popover.closest( '.wpsl-marker-picker' );
            const parts         = $popover.find( '.wpsl-marker-picker-panel' ).data( 'wpsl-panel-parts' );

            if ( ! parts ) {
                return;
            }

            // The field's own value -- the user may have overridden the
            // derived pairing.
            const fill    = parts.field.val();
            const icon    = parts.iconField.val();
            const outline = parts.outlineField.val();

            parts.clearError();
            parts.save.prop( 'disabled', true );

            jQuery.post( wpslMarkerCreate.ajaxurl, {
                action:       'wpsl_marker_manager_save',
                nonce:        wpslMarkerCreate.nonce,
                name:         wpslMarkerCreate.strings.namePrefix + ' ' + fill,
                shape:        wpslMarkerCreate.defaultShape,
                icon_name:    'dot',
                icon_color:   icon,
                fill_color:   fill,
                stroke_color: outline
            } ).done( function( response ) {
                if ( ! response || ! response.success || ! response.data ) {
                    parts.showError( wpslMarkerCreate.strings.saveFailed );
                    return;
                }

                addCustomMarkerTile( response.data, $originPicker );
            } ).fail( function( xhr ) {
                const message = ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message )
                    ? xhr.responseJSON.data.message
                    : wpslMarkerCreate.strings.saveFailed;

                parts.showError( message );
            } ).always( function() {
                parts.save.prop( 'disabled', false );
            } );
        } );

        /**
         * Adds a freshly saved marker to every picker column and selects it in
         * the one it was created from. All three share the same store, so a
         * marker made here is immediately usable as start, store, or active
         * marker without a reload.
         *
         * @since   3.0.0
         * @param   {Object} data          - The save response: marker and data_uri.
         * @param   {Object} $originPicker - The column the panel was opened from.
         * @returns {void}
         */
        const addCustomMarkerTile = function( data, $originPicker ) {
            const value = 'custom:' + data.marker.id;
            let $chosen = jQuery();

            jQuery( '.wpsl-marker-picker' ).each( function() {
                const $picker   = jQuery( this );
                const $popover  = $picker.find( '.wpsl-marker-picker-popover' );
                const template  = $popover.find( '.wpsl-marker-picker-tile-template' ).get( 0 );

                if ( ! template ) {
                    return;
                }

                let $grid = customMarkerGrid( $popover );

                if ( ! $grid.length ) {
                    $grid = jQuery( '<div class="wpsl-marker-picker-grid"></div>' );

                    $popover.find( '.wpsl-marker-picker-panel' )
                        .before( jQuery( '<p class="wpsl-marker-picker-group"></p>' ).text( wpslMarkerCreate.strings.customMarkers ) )
                        .before( $grid );
                }

                // Cloned from the server template, so injected tiles are
                // only ever markup PHP emitted.
                const $tile = jQuery( template.content.firstElementChild.cloneNode( true ) );

                $tile.attr( 'data-marker', value ).attr( 'data-name', data.marker.name ).attr( 'title', data.marker.name );

                // The max-height fix create_picker_html() bakes into a
                // server tile; without it the flat CSS cap shrinks it.
                $tile.find( 'img' ).attr( 'src', data.data_uri ).css( 'max-height', data.picker_height + 'px' );
                $grid.append( $tile );

                if ( $picker.is( $originPicker ) ) {
                    $chosen = $tile;
                }
            } );

            $chosen.trigger( 'click' );
        };

        // Set the active Google Maps cluster style and show/hide marker
        // Color pickers based on the selected value.
        jQuery( '.wpsl-marker-cluster-style input[type=radio]' ).on( 'click', function() {
            jQuery( this ).closest( '.wpsl-marker-cluster-style' ).find( 'img' ).removeClass( 'wpsl-active-cluster-style' );
            jQuery( this ).prev( 'label' ).find( 'img' ).addClass( 'wpsl-active-cluster-style' );

            if ( jQuery( this ).val() !== 'legacy' ) {
                jQuery( '.wpsl-api-gmaps .wpsl-cluster-colors' ).show();
            } else {
                jQuery( '.wpsl-api-gmaps .wpsl-cluster-colors' ).hide();
            }
        });

        // Store template dropdown: if the list is shown under the map, show
        // the option to hide the scrollbar.
        jQuery( '#wpsl-store-template' ).on( 'change', function() {
            const $scrollOption = jQuery( '#wpsl-listing-below-option' );

            if ( jQuery( this ).val() === 'horizontal' ) {
                $scrollOption.show();
            } else {
                $scrollOption.hide();
            }
        });

        // Conditional options -- delegated for dynamically added elements.
        jQuery( document ).on( 'change', '.wpsl-has-conditional-option', function() {
            if ( helpers.dom.shouldToggle( jQuery( this ) ) ) {
                const $conditionalOption = jQuery( this ).parents( 'p' ).next( '.wpsl-conditional-option' );
                
                $conditionalOption.toggle();

                if ( jQuery( this ).attr( 'id' ) === 'wpsl-osm-overwrite-styles' ) {
                    if ( jQuery( this ).is( ':checked' ) ) {
                        const $checkedMapboxRadio = $conditionalOption.find( '.wpsl-mapbox-style-options input[type="radio"]:checked' );
                        if ( $checkedMapboxRadio.length ) {
                            $checkedMapboxRadio.trigger( 'change' );
                        } else {
                            const $firstMapboxRadio = $conditionalOption.find( '.wpsl-mapbox-style-options input[type="radio"]:first' );
                            if ( $firstMapboxRadio.length ) {
                                $firstMapboxRadio.prop( 'checked', true ).trigger( 'change' );
                            }
                        }
                    } else if ( state.mapService === 'osm' ) {
                        const map = mapObjects.get();
                        if ( map && mapBootstrap.tileLayer ) {
                            map.removeLayer( mapBootstrap.tileLayer );

                            mapBootstrap.tileLayer = L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                            });
                            
                            mapBootstrap.tileLayer.addTo( map );
                        }
                    }
                }
            }
        });

        // Conditional options that toggle multiple elements ( .wpsl-has-conditional-options )
        jQuery( '#wpsl-gmaps-styles' ).on( 'change', function() {
            const style = jQuery( this ).val();

            // Disable submit buttons if required fields are empty for the selected style.
            switch ( style ) {
                case 'cloud_based':
                    const cloudId = jQuery( '#wpsl-cloud-based-id' ).val();
                    jQuery( '.wpsl-style-actions input[type=submit]' ).prop( 'disabled', ! cloudId.trim() );
                    break;
                case 'json':
                    const jsonStyle = jQuery( '#wpsl-map-style-gmaps' ).val();
                    jQuery( '.wpsl-style-actions input[type=submit]' ).prop( 'disabled', ! jsonStyle.trim() );
                    break;
            }

            jQuery( '[id^="wpsl-gmaps-style-"]' ).hide();
            jQuery( '#wpsl-gmaps-style-' + style ).show();

            jQuery( '.wpsl-map-style-source .wpsl-info' ).toggle( style !== 'cloud_based' );

            jQuery( '#wpsl-cloud-based-id, #wpsl-map-style-gmaps' ).removeClass( 'wpsl-error' );
        });

        jQuery( '#wpsl-api-openrouteservice-key' ).on( 'input', function() {
            settings.toggleDirectionsLanguage();
        });

        jQuery( '#wpsl-direction-redirect' ).on( 'change', function() {
            settings.toggleDirectionsLanguage();
        });

        // Handle changing the map service.
        jQuery( '#wpsl-map-service' ).on( 'change', function() {
            state.mapService = jQuery( this ).val();

            const oldMapWrapId = jQuery( '#wpsl-appearance-preview .wpsl-map-style-wrap' ).attr( 'id' );
            if ( typeof oldMapWrapId === 'string' ) {
                jQuery( '#wpsl-appearance-preview .wpsl-map-style-wrap' ).attr( 'id', 'wpsl-' + state.mapService + '-wrap' );
            }

            geocodeResponseTest.updateButtonState();

            jQuery( '#wpsl-settings-form [class*=wpsl-api-]:not(.wpsl-api-' + state.mapService + ')' ).hide();

            // Set the correct element to display:flex or block.
            jQuery.each( jQuery( '#wpsl-settings-form [class~=wpsl-api-' + state.mapService + ']' ), function() {
                if ( jQuery( this ).is( 'div' ) ) {
                    if ( jQuery( this ).hasClass( 'wpsl-multiselect-option' ) ) {
                        jQuery( this ).css( 'display', 'flex' );
                    } else {
                        jQuery( this ).css( 'display', 'block' );

                        // Only the label + field rows are flex; a callout's paragraph is plain text.
                        jQuery( this ).find( 'p' ).not( '.wpsl-warning-callout p' ).css( 'display', 'flex' );
                    }
                } else if ( jQuery( this ).is( 'p' ) ) {
                    jQuery( this ).css( 'display', 'flex' );
                } else {
                    jQuery( this ).css( 'display', 'inline-block' );
                }
            });

            const mapWrapId = 'wpsl-' + state.mapService + '-wrap';
            if ( ! jQuery( '#' + mapWrapId ).length ) {
                jQuery( '.wpsl-credits' ).before( '<div id="' + mapWrapId + '" class="wpsl-styles-preview"></div>' );
            }

            if ( state.mapService === 'gmaps' && ( typeof google === 'undefined' || typeof google.maps === 'undefined' || typeof google.maps.LatLng !== 'function' ) ) {
                api.gmaps.loader();
            }

            jQuery( '#wpsl-settings-form .wpsl-styles-preview:not( #' + mapWrapId + ')' ).hide();
            jQuery( '#' + mapWrapId ).show();

            if ( state.mapService === 'gmaps' ) {
                const styleSource = jQuery( '#wpsl-gmaps-styles' ).val();

                jQuery( '[id*="wpsl-gmaps-styles-"]' ).hide();
                jQuery( '#wpsl-gmaps-styles-' + styleSource + '' ).show();

                settings.toggleRegionFields();
            }

            notice.remove();

            if ( jQuery( '#wpsl-search-autocomplete' ).is( ':checked' ) ) {
                const isLegacy = state.mapService === 'gmaps' && jQuery( '#wpsl-gmaps-autocomplete-api-versions' ).val() === 'legacy';
                jQuery( '.wpsl-autosubmit-autocomplete' ).parent().toggle( ! isLegacy );
            }

            settings.toggleSearchMethodFields();
            settings.toggleDirectionsLanguage();
        });

        jQuery( '[name="wpsl_gdpr[handler]"]' ).on( 'change', function() {
            const gdprHandler = jQuery( this ).val();

            jQuery( '.wpsl-gdpr-info-borlabs' ).toggleClass( 'wpsl-hide', ( gdprHandler !== 'borlabs' ) );
            jQuery( '.wpsl-gdpr-info-complianz' ).toggleClass( 'wpsl-hide', ( gdprHandler !== 'complianz' ) );
            jQuery( '#wpsl-gdpr-description' ).siblings( 'label' ).find( '.wpsl-info' ).toggleClass( 'wpsl-hide', ( gdprHandler !== 'wpsl' ) );
            jQuery( '#wpsl-gdpr-description' ).closest( 'div' ).toggle( gdprHandler === 'wpsl' );
        });

        // Create the map only when the user clicks the 'Map Options' tab, to
        // avoid grey areas when switching providers.
        jQuery( '#wpsl-content-wrap nav a[href="#wpsl-map-settings"]' ).on( 'click', function() {
            state.mapService = jQuery( '#wpsl-map-service' ).val();

            const mapId = 'wpsl-' + state.mapService + '-wrap';
            const map = mapObjects.get( mapId );

            // Fix grey areas / incorrect map rendering.
            if ( map ) {
                setTimeout( () => {
                    switch ( state.mapService ) {
                        case 'osm':
                        case 'stadia':
                            if ( typeof map.invalidateSize === 'function' ) {
                                map.invalidateSize();
                            }
                            break;
                        case 'mapbox':
                            if ( typeof map.resize === 'function' ) {
                                map.resize();
                            }
                            break;
                    }
                }, 300 );
            }
        });

        jQuery( '#wpsl-content-wrap' ).on( 'click', 'button.notice-dismiss', function() {
            jQuery( this ).closest( 'div.notice' ).remove();
        });

        // Hide info texts when clicking next to them.
        jQuery( '#wpsl-content-wrap' ).on( 'click', function() {
            jQuery( '.wpsl-info-text' ).hide();
        });

        jQuery( '#wpsl-cat-filter-types' ).on( 'change', function() {
            if ( jQuery( this ).val() === 'checkboxes' ) {
                jQuery( '.wpsl-all-categories' ).show();
            } else {
                jQuery( '.wpsl-all-categories' ).hide();
            }
        });

        // Swap between soft-bias Map region select and hard-restrict countries multiselect.
        jQuery( '#wpsl-region-restriction-type' ).on( 'change', function() {
            settings.toggleRegionFields();
        });

        // Hide the "auto-submit on autocomplete" option if the legacy API is selected.
        jQuery( '#wpsl-gmaps-autocomplete-api-versions' ).on( 'change', function() {
            const isLatest = jQuery( this ).val() !== 'legacy';

            jQuery( '.wpsl-autosubmit-autocomplete' ).toggle( isLatest );
        });

        // Toggle auto-submit visibility when autocomplete is toggled.
        // Show for Mapbox or Google Maps with non-legacy API.
        jQuery( '#wpsl-search-autocomplete' ).on( 'change', function() {
            const $autoSubmitDiv = jQuery( '.wpsl-autosubmit-autocomplete' ).parent();
            
            if ( jQuery( this ).is( ':checked' ) ) {
                const mapService = jQuery( '#wpsl-map-service' ).val();
                const isLegacy = mapService === 'gmaps' && jQuery( '#wpsl-gmaps-autocomplete-api-versions' ).val() === 'legacy';

                $autoSubmitDiv.toggle( ! isLegacy );
            } else {
                $autoSubmitDiv.hide();
            }
        });

    },
};