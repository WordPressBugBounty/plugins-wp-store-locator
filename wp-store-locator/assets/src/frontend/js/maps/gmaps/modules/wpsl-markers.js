import { importedLibraries } from '../../../../../common/wpsl-core.js';
import { slData, config } from '../../../modules/wpsl-shared.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { markerGroups } from '../../../modules/wpsl-marker-groups.js';
import { groupWindow } from '../../../modules/wpsl-group-window.js';
import { infoWindow } from './wpsl-infowindow.js';
import { api } from './wpsl-api.js';

/**
 * Max CSS z-index ( 32-bit signed int ) -- above it the browser clamps and
 * markers tie on DOM order. Reserved for the start marker so "start marker
 * on top" always wins; clusters stay below.
 *
 * @since 3.0.0
 */
const WPSL_MAX_ZINDEX          = 2147483647;
const WPSL_CLUSTER_BASE_ZINDEX = 1000000;

/**
 * The z-index the start marker gets with "start marker on top" and no
 * clusters. Mirrors the value set in legacy.create() / advanced.create().
 *
 * @since 3.1.1
 */
const WPSL_START_ZINDEX = 9999;

/**
 * The map the results list belongs to. Later indexes are [wpsl_map]
 * shortcodes with their own markers.
 *
 * @since 3.0.0
 */
const RESULTS_MAP_INDEX = 0;

/**
 * Fallback cluster SVG templates, used when the templates are missing from
 * the cluster config. Mirrors the defaults set in PHP in get_cluster_templates().
 *
 * @since 3.0.0
 */
const WPSL_DEFAULT_CLUSTER_TEMPLATES = {
    default: {
        svg: '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" opacity=".6" r="70" /><circle cx="120" cy="120" opacity=".3" r="90" /><circle cx="120" cy="120" opacity=".2" r="110" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
        size: 50
    },
    interpolation: {
        svg: '<svg fill="${color}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><circle cx="120" cy="120" opacity=".8" r="70" /><text x="50%" y="50%" style="fill:${labelColor}" text-anchor="middle" font-size="${labelSize}" dominant-baseline="middle" font-family="roboto,arial,sans-serif">${count}</text></svg>',
        size: 75
    }
};

/**
 * Replace the ${placeholder} tokens in a cluster SVG template.
 *
 * The templates are set in PHP and can be changed with the
 * wpsl_cluster_marker_templates / wpsl_cluster_marker_shapes filters.
 *
 * Unknown tokens are left untouched so a typo in a custom
 * template stays visible instead of becoming "undefined".
 *
 * @since  3.0.0
 * @param  {string} template The SVG template containing ${name} tokens
 * @param  {object} vars     The replacement values keyed by token name
 * @return {string} The SVG with all known tokens replaced
 */
function wpslRenderClusterTemplate( template, vars ) {
    return String( template ).replace( /\$\{(\w+)\}/g, function( match, key ) {
        return vars.hasOwnProperty( key ) ? vars[key] : match;
    });
}

/**
 * The number of locations in a cluster. A marker shared by the locations
 * at one address counts once for each of them.
 *
 * @since  3.1.1
 * @param  {array}  clusterMarkers The markers in the cluster
 * @param  {number} count          The number of markers, used when the list is missing
 * @return {number}
 */
function wpslLocationCount( clusterMarkers, count ) {
    if ( ! Array.isArray( clusterMarkers ) ) {
        return count;
    }

    return clusterMarkers.reduce( ( total, marker ) => total + ( marker && marker._wpslGroup ? marker._wpslGroup.length : 1 ), 0 );
}

/**
 * Fetched SVG markup and pending fetches, keyed by URL, so each color is
 * requested once even when many markers share it.
 *
 * @since 3.0.0
 */
const wpslSvgMarkupCache   = {};
const wpslSvgFetchPromises = {};

/**
 * Fetch ( and cache ) the raw markup of an SVG marker so it can be inlined.
 *
 * Only same-origin requests resolve; a cross-origin marker without CORS headers
 * rejects and the caller falls back to an <img>.
 *
 * @since  3.0.0
 * @param  {string} url The SVG URL.
 * @return {Promise<string>} Resolves with the SVG markup.
 */
function wpslFetchSvgMarkup( url ) {
    if ( wpslSvgMarkupCache[ url ] ) {
        return Promise.resolve( wpslSvgMarkupCache[ url ] );
    }

    if ( ! wpslSvgFetchPromises[ url ] ) {
        wpslSvgFetchPromises[ url ] = fetch( url )
            .then( ( response ) => {
                if ( ! response.ok ) {
                    throw new Error( 'Failed to load SVG marker: ' + url );
                }

                return response.text();
            } )
            .then( ( markup ) => {
                wpslSvgMarkupCache[ url ] = markup;

                return markup;
            } );
    }

    return wpslSvgFetchPromises[ url ];
}

/**
 * Google Maps markers functionality for WPSL frontend.
 *
 * @since 3.0.0
 */
export const markers = {
    /**
     * Determine which marker type to use based on map styling configuration.
     * Legacy markers for JSON styling, Advanced markers for cloud-based or no styling.
     *
     * @since  3.0.0
     * @return {string} 'legacy' or 'advanced'
     */
    getMarkerType: function() {
        if ( slData.markers.markerType !== null ) {
            return slData.markers.markerType;
        }

        const styleSelected = config.map?.styleSelected || 'cloud_based';
        const markerType = styleSelected === 'json' ? 'legacy' : 'advanced';
        slData.markers.markerType = markerType;

        return markerType;
    },

    /**
     * Add a new marker to the map based on the provided location coordinates.
     *
     * @since  3.0.0
     * @param  {object} markerData Data used to place the marker on the map ( coordinates, title )
     * @param  {object} map        The map object
     * @return {object} marker     The created marker instance
     */
    add: function( markerData, map ) {
        const latLng = helpers.map.checkLatLngInstance( markerData );

        // The geolocation call in wpsl.js passes no map object, and that can only
        // happen on the store locator page itself, so map[0] always holds the map.
        map = helpers.getMapObj( map );

        markerData = wp.hooks.applyFilters( 'wpslMarkerData', helpers.markers.setProperties( markerData ) );

        // Check if we need to hide the start marker on the map
        const visibility = helpers.markers.startVisibility( markerData );

        // If we are dealing with a name only search,
        // and it's the start marker, then don't continue.
        if ( config.search.namesEnabled && ! visibility ) {
            return;
        }

        let createdMarker;

        const markerType = this.getMarkerType();
        if ( markerType === 'legacy' ) {
            createdMarker = this.legacy.create( markerData, map, latLng );
        } else {
            createdMarker = this.advanced.create( markerData, map, latLng );
        }

        // Store the marker for later use, organized by map index
        const mapIndex = map._wpslMapIndex || 0;

        if ( ! slData.provider.markers.active ) {
            slData.provider.markers.active = {};
        }

        if ( ! slData.provider.markers.active[mapIndex] ) {
            slData.provider.markers.active[mapIndex] = [];
        }

        // Remember which map the marker is on, so the active marker
        // state can be restored per map when multiple maps exist.
        createdMarker._wpslMapIndex = mapIndex;

        // The route's stop markers open the same info window as the marker they stand in for.
        createdMarker._wpslMarkerData = markerData;

        // A marker that stands for every location at its address. The lead's
        // own data goes first, as filtered by wpslMarkerData.
        const group = markerGroups.members( markerData.id, mapIndex );

        if ( group ) {

            // _wpslGroup holds the locations shown now, see applyShownGroup().
            createdMarker._wpslGroupAll = [ markerData ].concat( group.slice( 1 ) );
            createdMarker._wpslGroup    = createdMarker._wpslGroupAll;

            const title = groupWindow.markerTitle( createdMarker._wpslGroup );

            if ( typeof createdMarker.setTitle === 'function' ) {
                createdMarker.setTitle( title );
            } else {
                createdMarker.title = title;
            }
        }

        slData.provider.markers.active[mapIndex].push( createdMarker );

        if ( markerData.draggable ) {
            slData.provider.markers.startLocation = createdMarker;
        }

        if ( ! map._wpslIdleListenerPending ) {
            map._wpslIdleListenerPending = true;

            google.maps.event.addListenerOnce( map, 'idle', () => {
                this.setupMarkerAccessibility( map );
            });
        }

        // A hidden start marker gets no click listener, otherwise clicking the spot
        // where it's supposed to be brings it back. It's still created, so its
        // location can render the driving directions on the map.
        if ( visibility ) {
            const self = this;

            google.maps.event.addListener( createdMarker, 'click',( function( currentMap, infoWindow, api ) {
                return function() {
                    // Track the source element for focus restoration when the infowindow
                    // closes. An already-set value came from a search result, keep it.
                    if ( ! slData.markers.focusSourceElement ) {
                        const markerEl = createdMarker.element || null;
                        if ( markerEl && markerEl.nodeType ) {
                            slData.markers.focusSourceElement = markerEl;
                        }
                    }

                    // Make sure no marker is set to active on the clicked map.
                    self.restoreActiveMarkers( currentMap._wpslMapIndex );

                    // A shared marker opens on the location a search result asked for, see openOn().
                    // With one of its locations left by wpsl.api.showLocations(), it opens on that one alone.
                    const shownGroup = createdMarker._wpslGroup || null;
                    const group      = shownGroup && shownGroup.length > 1 ? shownGroup : null;
                    const single     = shownGroup && shownGroup.length === 1 ? shownGroup[0] : markerData;

                    createdMarker._wpslGroupOpen  = group && typeof createdMarker._wpslGroupStart === 'number' ? createdMarker._wpslGroupStart : null;
                    createdMarker._wpslGroupStart = null;

                    const shownData = group ? group[ createdMarker._wpslGroupOpen || 0 ] : single;

                    if ( helpers.markers.maybeSetActivemarker( markerData.id, !! ( markerData.locationMarkerUrlActive || markerData.categoryMarkerUrlActive ) ) ) {
                        self.setActive( createdMarker, markerData );
                    }

                    // The start marker will have a store id of 0, all others won't.
                    if ( markerData.id !== 0 ) {
                        // Make sure streetview is available before showing the option to use it.
                        if ( config.ux.markerStreetView ) {
                            api.streetView.checkStatus( latLng, function() {
                                infoWindow.setContent( createdMarker, single, map );
                            });
                        } else {
                            infoWindow.setContent( createdMarker, single, map );
                        }
                    } else {
                        infoWindow.setContent( createdMarker, single, map );
                    }

                    google.maps.event.clearListeners( infoWindow.current, 'domready' );

                    google.maps.event.addListener( infoWindow.current, 'domready', function() {
                        helpers.results.maybeShowZoomOption( currentMap );

                        infoWindow.applyRoundedButtonDimensions();

                        infoWindow.actions( createdMarker, currentMap );

                        if ( group ) {
                            groupWindow.bind( jQuery( currentMap.getDiv() ).find( '.wpsl-group-window' ), {
                                stores: group,
                                marker: createdMarker,
                                map: currentMap
                            });
                        }

                        // Pan the info window back inside the map if it ends up
                        // above the container. 'idle' waits out pan/zoom
                        // animations so the offsets measure the final state.
                        const $mapDiv = jQuery( currentMap.getDiv() );

                        google.maps.event.addListenerOnce( currentMap, 'idle', function() {
                            const infoWindowNode = $mapDiv.find( '.wpsl-info-window' ).closest( '.gm-style-iw-c' );
                            if ( infoWindowNode.length ) {
                                const infoWindowTop = infoWindowNode.offset().top;
                                const mapTop        = $mapDiv.offset().top;
                                if ( infoWindowTop < mapTop ) {
                                    currentMap.panBy( 0, -( mapTop - infoWindowTop + 20 ) );
                                }
                            }
                        });

                        // Move focus into the info window only for keyboard-initiated interactions:
                        // its first link or button, such as an add-on's, else the close button.
                        // Mouse clicks and hovers should not shift focus to the info window.
                        if ( slData.markers.keyboardTriggered ) {
                            const controls = helpers.infoWindowControls( $mapDiv.find( '.wpsl-info-window' ).closest( '.gm-style-iw-d' ).get( 0 ) );
                            const closeButton = jQuery( '.gm-ui-hover-effect[aria-label="Close"]' );

                            if ( controls.length ) {
                                controls[0].focus();
                            } else if ( closeButton.length ) {
                                closeButton.focus();
                            }

                            slData.markers.keyboardTriggered = false;
                        }
                    });

                    markers.current = createdMarker;

                    wp.hooks.doAction( 'wpslMarkerClicked', createdMarker, shownData, map );
                };
            }( map, infoWindow, api ) ) );
        } else {
            // Legacy google.maps.Marker only hides via setMap( null ); see removeAll().
            if ( typeof createdMarker.setMap === 'function' ) {
                createdMarker.setMap( null );
            } else {
                createdMarker.map = null;
            }
        }

        if ( markerData.draggable ) {
            createdMarker.addListener( 'dragend', function() {
                slData.setSearchInput = true;

                // The advanced marker exposes .position, the legacy
                // google.maps.Marker only getPosition().
                const pos = typeof createdMarker.getPosition === 'function' ? createdMarker.getPosition() : createdMarker.position;
                const args = { markerDragged: true, latLng: pos };

                this.removeAll();
                this.add( args, map );

                map.setCenter( pos );

                // A newer search may start before the address comes back, see search.current().
                api.geocoding.reverse( args, search.current( function() {
                    search.run( args );
                }) );
            }.bind(this));
        }

        return createdMarker;
    },

    /**
     * Legacy marker implementation for JSON client-side styling
     *
     * @since 3.0.0
     */
    legacy: {
        /**
         * Create a legacy google.maps.Marker instance
         *
         * @since  3.0.0
         * @param  {object} markerData The marker data
         * @param  {object} map        The map object
         * @param  {object} latLng     The lat/lng coordinate
         * @return {object} marker     The created marker instance
         */
        create: function( markerData, map, latLng ) {
            let markerOptions = {
                position: latLng,
                map: map,
                title: helpers.decodeHtmlEntity( markerData.store ),
                draggable: markerData.draggable,
                optimized: false,
                icon: markers.shared.getIconProps( markerData.markerUrl )
            };

            if ( config.markers.startOnTop ) {
                let startZIndex = WPSL_START_ZINDEX;

                if ( config.markers.markerClusters ) {
                    // Use the CSS z-index ceiling exactly; clusters stay below it so the
                    // start marker wins. Going above the ceiling gets clamped and ties.
                    startZIndex = WPSL_MAX_ZINDEX;
                }

                markerOptions.zIndex = ( markerData.id === 0 ) ? startZIndex : 1;
            }

            markerOptions = wp.hooks.applyFilters( 'wpslMarker', markerOptions );
            const marker = new google.maps.Marker( markerOptions );
            marker.storeId = markerData.id;
            marker._iconUrl = markerData.markerUrl;

            if ( markerData.categoryMarkerUrlActive ) {
                marker.categoryMarkerUrlActive = markerData.categoryMarkerUrlActive;
            }

            if ( markerData.locationMarkerUrlActive ) {
                marker.locationMarkerUrlActive = markerData.locationMarkerUrlActive;
            }

            return marker;
        }
    },

    /**
     * Advanced marker implementation for cloud-based Map ID styling
     *
     * @since 3.0.0
     */
    advanced: {
        /**
         * Create an AdvancedMarkerElement instance
         *
         * @since  3.0.0
         * @param  {object} markerData The marker data
         * @param  {object} map        The map object
         * @param  {object} latLng     The lat/lng coordinate
         * @return {object} marker     The created marker instance
         */
        create: function( markerData, map, latLng ) {
            let markerOptions = {
                position: latLng,
                map: map,
                title: helpers.decodeHtmlEntity( markerData.store ),
                gmpDraggable: markerData.draggable,
                content: markers.shared.createMarkerContent( markerData.markerUrl )
            };

            if ( config.markers.startOnTop ) {
                let startZIndex = WPSL_START_ZINDEX;

                if ( config.markers.markerClusters ) {
                    // Use the CSS z-index ceiling exactly. The cluster renderer keeps
                    // clusters below it ( WPSL_CLUSTER_BASE_ZINDEX + count ), so the
                    // start marker always stacks above them.
                    startZIndex = WPSL_MAX_ZINDEX;
                }

                markerOptions.zIndex = ( markerData.id === 0 ) ? startZIndex : 1;
            }

            markerOptions = wp.hooks.applyFilters( 'wpslMarker', markerOptions );

            const marker = new importedLibraries.marker.AdvancedMarkerElement( markerOptions );
            marker.storeId = markerData.id;
            marker._iconUrl = markerData.markerUrl;

            if ( markerData.categoryMarkerUrlActive ) {
                marker.categoryMarkerUrlActive = markerData.categoryMarkerUrlActive;
            }

            if ( markerData.locationMarkerUrlActive ) {
                marker.locationMarkerUrlActive = markerData.locationMarkerUrlActive;
            }

            return marker;
        }
    },

    /**
     * Shared utilities for both marker types
     *
     * @since 3.0.0
     */
    shared: {
        /**
         * Create marker content as an HTMLElement for AdvancedMarkerElement.
         *
         * SVG markers are inlined as a real <svg> element because an <img> SVG is
         * rasterized at the compositing layer's CSS-pixel size and looks blurry
         * on hi-dpi screens. Raster markers ( PNG ) keep using <img>.
         *
         * @since   3.0.0
         * @param   {string} markerUrl URL where we can find the marker image
         * @returns {HTMLElement} The element to use as marker content
         */
        createMarkerContent: function( markerUrl ) {
            const custom = helpers.markers.getCustomMarkerGeometry( markerUrl );
            const w = custom ? custom.width : config.markers.scaledSize[0];
            const h = custom ? custom.height : config.markers.scaledSize[1];

            const element = helpers.markers.isSvgSrc( markerUrl )
                ? markers.shared.createSvgContent( markerUrl, w, h )
                : markers.shared.createImgContent( markerUrl, w, h );

            // AdvancedMarkerElement anchors at the bottom centre and takes no
            // anchor point, so a custom shape shifts itself by the same offset
            // Mapbox takes as icon-offset.
            if ( custom && ( custom.offset[0] || custom.offset[1] ) ) {
                element.style.setProperty(
                    'transform',
                    'translate(' + custom.offset[0] + 'px, ' + custom.offset[1] + 'px)',
                    'important'
                );
            }

            return element;
        },

        /**
         * Build an <img> marker element pinned to fixed dimensions.
         *
         * The !important px dimensions stop a theme's `img { max-width:100%;
         * height:auto }` rule forcing Google to measure each image's intrinsic
         * size -- a synchronous layout per marker that freezes the page while
         * hundreds load.
         *
         * @since   3.0.0
         * @param   {string} markerUrl URL of the marker image
         * @param   {number} w         Display width in pixels
         * @param   {number} h         Display height in pixels
         * @returns {HTMLElement} An <img> element for use as marker content
         */
        createImgContent: function( markerUrl, w, h ) {
            const img = document.createElement( 'img' );
            img.src    = markerUrl;
            img.width  = w;
            img.height = h;

            img.style.setProperty( 'width',     w + 'px', 'important' );
            img.style.setProperty( 'height',    h + 'px', 'important' );
            img.style.setProperty( 'max-width', 'none',   'important' );
            img.style.setProperty( 'display',   'block',  'important' );

            return wp.hooks.applyFilters( 'wpslMapIcon', img );
        },

        /**
         * Build an inline-SVG marker element so it stays crisp on the Advanced
         * Marker's compositing layer.
         *
         * The markup is fetched once and cached. While it loads ( or if the fetch
         * fails, e.g. a cross-origin marker ) an <img> of the same SVG stands in,
         * so the marker is never empty.
         *
         * @since   3.0.0
         * @param   {string} markerUrl URL of the SVG marker
         * @param   {number} w         Display width in pixels
         * @param   {number} h         Display height in pixels
         * @returns {HTMLElement} A wrapper element containing the marker
         */
        createSvgContent: function( markerUrl, w, h ) {
            const wrapper = document.createElement( 'span' );
            wrapper.style.setProperty( 'display',     'block',  'important' );
            wrapper.style.setProperty( 'width',       w + 'px', 'important' );
            wrapper.style.setProperty( 'height',      h + 'px', 'important' );
            wrapper.style.setProperty( 'line-height', '0',      'important' );

            const inject = ( markup ) => {
                // Not innerHTML: parsed as SVG with anything that can run code removed.
                const svg = sharedHelpers.parseSvgMarkup( markup );

                // Not SVG after all: the <img> stand-in stays.
                if ( ! svg ) {
                    return;
                }

                svg.setAttribute( 'width',  w );
                svg.setAttribute( 'height', h );
                svg.style.setProperty( 'display', 'block', 'important' );

                wrapper.replaceChildren( svg );
            };

            if ( wpslSvgMarkupCache[ markerUrl ] ) {
                // Already fetched: inline synchronously with no placeholder flash.
                inject( wpslSvgMarkupCache[ markerUrl ] );
            } else {
                wrapper.appendChild( markers.shared.createImgContent( markerUrl, w, h ) );

                wpslFetchSvgMarkup( markerUrl ).then( inject ).catch( () => {} );
            }

            return wp.hooks.applyFilters( 'wpslMapIcon', wrapper );
        },

        /**
         * Get the required map icon properties for legacy google.maps.Marker.
         *
         * @since   3.0.0
         * @param   {string} markerUrl URL where we can find the marker image
         * @returns {object} Legacy icon object for google.maps.Marker
         */
        getIconProps: function( markerUrl ) {
            // See createMarkerContent(): a custom marker brings its own size and its
            // own anchor -- a shape with no tip sits centred on its coordinate.
            const custom = helpers.markers.getCustomMarkerGeometry( markerUrl );
            const size   = custom ? [ custom.width, custom.height ] : config.markers.scaledSize;
            const anchor = custom ? custom.anchor : config.markers.anchor;

            const mapIcon = {
                url: markerUrl,
                scaledSize: new google.maps.Size( size[0], size[1] ),
                origin: new google.maps.Point( config.markers.origin[0], config.markers.origin[1] ),
                anchor: new google.maps.Point( anchor[0], anchor[1] )
            };

            return wp.hooks.applyFilters( 'wpslMapIcon', mapIcon );
        }
    },

    /**
     * Create marker content as HTMLElement for AdvancedMarkerElement.
     * Backward compatibility wrapper that delegates to shared.createMarkerContent().
     *
     * @since   3.0.0
     * @param   {string} markerUrl URL where we can find the marker image
     * @returns {HTMLElement} An <img> element for use as marker content
     */
    createMarkerContent: function( markerUrl ) {
        return this.shared.createMarkerContent( markerUrl );
    },

    /**
     * Get the required map icon properties (legacy wrapper for backward compatibility).
     *
     * @since   3.0.0
     * @deprecated Use shared.getIconProps() instead
     * @param   {string} markerUrl URL where we can find the marker image
     * @returns {object} Legacy icon object for google.maps.Marker
     */
    getIconProps: function( markerUrl ) {
        return this.shared.getIconProps( markerUrl );
    },

    /**
     * Set the correct active marker image.
     *
     * @since  3.0.0
     * @param  {object} marker The marker to set as active
     * @param  {object} markerData Optional marker data holding the per-store and per-category active marker URLs
     * @return {void}
     */
    setActive: function( marker, markerData ) {
        const settings = helpers.markers.getSettings();
        const mapIndex = ( typeof marker._wpslMapIndex === 'number' ) ? marker._wpslMapIndex : 0;

        // Make sure no other marker is set to active on this map. Markers on
        // other maps keep their state, every map has its own active marker.
        this.restoreActiveMarkers( mapIndex );

        if ( typeof slData.restoreActiveMarker !== 'object' || slData.restoreActiveMarker === null ) {
            slData.restoreActiveMarker = {};
        }

        // Save the existing marker data per map, same as the OSM provider.
        slData.restoreActiveMarker[mapIndex] = {
            id: marker.storeId,
            iconUrl: marker._iconUrl,
            marker: marker
        };

        let activeMarkerUrl;

        if ( markerData && markerData.locationMarkerUrlActive ) {
            activeMarkerUrl = markerData.locationMarkerUrlActive;
        } else if ( markerData && markerData.categoryMarkerUrlActive ) {
            activeMarkerUrl = markerData.categoryMarkerUrlActive;
        } else {
            activeMarkerUrl = helpers.markers.resolveMarkerSrc( config.markers.active, settings.url );
        }

        marker._iconUrl = activeMarkerUrl;

        // Update marker content/icon based on marker type
        if ( marker.content !== undefined ) {
            // Advanced Marker
            marker.content = this.shared.createMarkerContent( activeMarkerUrl );
        } else if ( marker.setIcon ) {
            // Legacy Marker
            marker.setIcon( this.shared.getIconProps( activeMarkerUrl ) );
        }

        // A hover on this marker's own search result sets it active while its
        // window is already open, so the offset has to follow the new artwork.
        infoWindow.reanchor( marker, mapIndex );

        // Locations at the same address stack their markers. Without this the
        // active one can sit underneath another marker.
        this.lift( marker, 'active' );

        slData.provider.markers.current = marker;
    },

    /**
     * The z-index for a lifted marker: above every other location marker, but
     * below the start marker when "start marker on top" is enabled.
     *
     * @since  3.1.1
     * @return {number} The z-index
     */
    getLiftedZIndex: function() {
        if ( config.markers.startOnTop ) {
            return ( config.markers.markerClusters ? WPSL_MAX_ZINDEX : WPSL_START_ZINDEX ) - 1;
        }

        return WPSL_MAX_ZINDEX;
    },

    /**
     * Raise a marker above the others, for example while it is active or bounces.
     *
     * Each reason is tracked on its own, so a hover that ends ( bounce ) doesn't
     * lower a marker that is still active from a click, and the other way round.
     * The original z-index is kept and put back once no reason is left.
     *
     * @since  3.1.1
     * @param  {object} marker The marker to raise
     * @param  {string} reason Why the marker is raised, 'active' or 'bounce'
     * @return {void}
     */
    lift: function( marker, reason ) {
        if ( ! marker ) {
            return;
        }

        if ( ! marker._wpslLifts ) {
            marker._wpslLifts = new Set();
        }

        if ( marker._wpslLifts.size === 0 ) {
            marker._wpslBaseZIndex = this.getZIndex( marker );
            this.setZIndex( marker, this.getLiftedZIndex() );
        }

        marker._wpslLifts.add( reason );
    },

    /**
     * Drop one reason for raising a marker, see lift(). The marker gets its
     * original z-index back when it was the last reason.
     *
     * @since  3.1.1
     * @param  {object} marker The marker to lower
     * @param  {string} reason The reason passed to lift()
     * @return {void}
     */
    lower: function( marker, reason ) {
        if ( ! marker || ! marker._wpslLifts || ! marker._wpslLifts.delete( reason ) ) {
            return;
        }

        if ( marker._wpslLifts.size === 0 ) {
            this.setZIndex( marker, marker._wpslBaseZIndex );
            delete marker._wpslBaseZIndex;
        }
    },

    /**
     * Read the z-index of a legacy or Advanced marker.
     *
     * @since  3.1.1
     * @param  {object} marker The marker
     * @return {number|null|undefined} The z-index, empty when Google decides the order
     */
    getZIndex: function( marker ) {
        return ( typeof marker.getZIndex === 'function' ) ? marker.getZIndex() : marker.zIndex;
    },

    /**
     * Set the z-index of a legacy or Advanced marker. An empty value hands the
     * order back to Google Maps, which stacks markers by latitude.
     *
     * @since  3.1.1
     * @param  {object}                marker The marker
     * @param  {number|null|undefined} zIndex The z-index
     * @return {void}
     */
    setZIndex: function( marker, zIndex ) {
        if ( typeof marker.setZIndex === 'function' ) {
            marker.setZIndex( zIndex );
        } else {
            marker.zIndex = ( typeof zIndex === 'number' ) ? zIndex : null;
        }
    },

    /**
     * Restore the marker icon from the
     * active state back to the original state.
     *
     * When a map index is passed, only the active marker on that map is
     * restored. Without one all maps are restored ( used by flows that
     * reset the whole page, like a new search on the store locator ).
     *
     * @since  3.0.0
     * @param  {number} mapIndex Optional index of the map to restore the active marker on
     * @return {void}
     */
    restoreActiveMarkers: function( mapIndex ) {
        const records = slData.restoreActiveMarker;
        if ( typeof records !== 'object' || records === null ) {
            return;
        }

        const restoreRecord = ( record, recordMapIndex ) => {
            const marker = record.marker;
            if ( ! marker ) {
                return;
            }

            marker._iconUrl = record.iconUrl;

            // Update marker icon based on marker type
            if ( marker.content !== undefined ) {
                // Advanced Marker
                marker.content = this.shared.createMarkerContent( record.iconUrl );
            } else if ( marker.setIcon ) {
                // Legacy Marker
                marker.setIcon( this.shared.getIconProps( record.iconUrl ) );
            }

            // Hovering another search result restores this marker while its
            // own window can still be open, so the offset follows it back.
            infoWindow.reanchor( marker, recordMapIndex );

            this.lower( marker, 'active' );
        };

        if ( typeof mapIndex === 'number' ) {
            if ( typeof records[mapIndex] === 'object' ) {
                restoreRecord( records[mapIndex], mapIndex );

                delete records[mapIndex];
            }

            return;
        }

        Object.keys( records ).forEach( ( key ) => {
            if ( typeof records[key] === 'object' ) {
                restoreRecord( records[key], Number( key ) );
            }

            delete records[key];
        });
    },

    /**
     * Remove all markers from the map.
     *
     * @since  3.0.0
     * @return {void}
     */
    removeAll: function() {
        const activeMarkers = slData.provider.markers.active[ RESULTS_MAP_INDEX ] || [];

        markerGroups.clear( RESULTS_MAP_INDEX );

        if ( activeMarkers.length > 0 ) {
            jQuery.each( activeMarkers, function( i ) {
                const marker = activeMarkers[i];

                // Legacy google.maps.Marker is only detached via setMap( null );
                // assigning marker.map = null leaves it on the map, which stacks
                // duplicate markers on every search in JSON-style mode.
                // AdvancedMarkerElement has no setMap() and uses the map property.
                if ( typeof marker.setMap === 'function' ) {
                    marker.setMap( null );
                } else {
                    marker.map = null;
                }
            });

            activeMarkers.length = 0;
        }

        // If marker clusters exist, remove them from the map.
        if ( slData.provider.markers.cluster && slData.provider.markers.cluster[0] ) {
            slData.provider.markers.cluster[0].clearMarkers();
        }
    },

    /**
     * Merge nearby markers into a cluster.
     *
     * @since  3.0.0
     * @see    https://googlemaps.github.io/js-markerclusterer/
     * @param  {object} map The Google Maps object
     * @param  {number} mapIndex The map index (for tracking multiple maps)
     * @return {void}
     */
    createCluster: function( map, mapIndex = 0 ) {
        const clusterConfig = config.markers.cluster;
        const mapMarkers = slData.provider.markers.active[mapIndex] || [];

        let markersNoStartMarker;

        // Keep the start location marker out of the cluster so the count
        // represents the returned locations, and not +1 for the start location.
        if ( clusterConfig.excludeStartMarker ) {
            markersNoStartMarker = mapMarkers.slice( 0 );
            markersNoStartMarker.splice( 0, 1 );
        }

        const markersForCluster = ( typeof markersNoStartMarker === 'undefined' ) ? mapMarkers : markersNoStartMarker;

        if ( typeof config.markers.cluster !== 'undefined' ) {
            const algorithm = new markerClusterer.SuperClusterAlgorithm( { radius: Number( clusterConfig.size ), maxZoom: Number( clusterConfig.zoom ) } );
            const renderer  = this.getRenderer();

            if ( ! slData.provider.markers.cluster ) {
                slData.provider.markers.cluster = {};
            }
            
            slData.provider.markers.cluster[mapIndex] = new markerClusterer.MarkerClusterer({
                map,
                markers: markersForCluster,
                algorithm,
                renderer
            });
        }
    },

    /**
     * Ensure the skip-to-results link is positioned correctly in the map container.
     *
     * @since  3.0.0
     * @param  {object} map The Google Maps object
     * @return {void}
     */
    setupMarkerAccessibility: function( map ) {
        const mapContainer = map.getDiv();
        if ( ! mapContainer ) {
            return;
        }

        // Keep the skip-to-results link first in the map container; a search
        // rebuilds the container DOM and can displace it.
        const skipLink = mapContainer.querySelector( '.wpsl-skip-to-results' )
            || document.querySelector( '.wpsl-skip-to-results' );

        if ( skipLink && mapContainer.firstChild !== skipLink ) {
            mapContainer.insertBefore( skipLink, mapContainer.firstChild );
        }
    },

    /**
     * Return the renderer to use to style / cluster the markers.
     *
     * @since 3.0.0
     * @see   https://googlemaps.github.io/js-markerclusterer/public/renderers/
     * @return {*}
     */
    getRenderer: function() {
        const clusterConfig = config.markers.cluster;

        let renderer = '';

        if ( config.markers.cluster.style === 'interpolation' ) {
            renderer = {
                palette: d3.interpolateRgb( clusterConfig.lowDensityColor, clusterConfig.highDensityColor ),
                render( { count, markers: clusterMarkers, position }, stats ) {
                    count = wpslLocationCount( clusterMarkers, count );

                    const color    = this.palette( Math.min( 1, count / stats.clusters.markers.max ) );
                    const template = ( clusterConfig.templates && clusterConfig.templates.interpolation ) || WPSL_DEFAULT_CLUSTER_TEMPLATES.interpolation;
                    const svg      = wpslRenderClusterTemplate( template.svg, {
                        color,
                        count,
                        labelColor: clusterConfig.labelColor,
                        labelSize: clusterConfig.labelSize
                    } );

                    const title = wpslLabels.clusterTitle.replace( '%d', count );

                    return markers.buildClusterMarker( {
                        position,
                        zIndex: WPSL_CLUSTER_BASE_ZINDEX + count,
                        title,
                        svg,
                        size: Number( template.size ) || 75
                    } );
                }
            }
        } else {
            renderer = {
                render( { count, markers: clusterMarkers, position }, stats, map ) {
                    count = wpslLocationCount( clusterMarkers, count );

                    const color    = count > Math.max( 10, stats.clusters.markers.mean ) ? clusterConfig.highDensityColor : clusterConfig.lowDensityColor;
                    const template = ( clusterConfig.templates && clusterConfig.templates.default ) || WPSL_DEFAULT_CLUSTER_TEMPLATES.default;
                    const svg      = wpslRenderClusterTemplate( template.svg, {
                        color,
                        count,
                        labelColor: clusterConfig.labelColor,
                        labelSize: clusterConfig.labelSize
                    } );

                    const title = wpslLabels.clusterTitle.replace( '%d', count );

                    // Cluster z-index stays below the CSS ceiling; see WPSL_CLUSTER_BASE_ZINDEX.
                    return markers.buildClusterMarker( {
                        position,
                        zIndex: WPSL_CLUSTER_BASE_ZINDEX + count,
                        title,
                        svg,
                        size: Number( template.size ) || 50
                    } );
                }
            }
        }

        return wp.hooks.applyFilters( 'wpslClusterMarkerRenderer', renderer );
    },

    /**
     * Build a cluster marker that matches the active marker type.
     *
     * AdvancedMarkerElement needs a Map ID, which JSON styling lacks -- so
     * that mode builds a legacy google.maps.Marker cluster instead.
     *
     * @since  3.0.0
     * @param  {object} args           Cluster marker properties
     * @param  {object} args.position  The cluster position ( LatLng )
     * @param  {number} args.zIndex    The stacking order
     * @param  {string} args.title     The accessible title
     * @param  {string} args.svg       The raw SVG icon markup
     * @param  {number} args.size      The icon width/height in pixels
     * @return {object} The created cluster marker instance
     */
    buildClusterMarker: function( { position, zIndex, title, svg, size } ) {
        const src = `data:image/svg+xml;charset=UTF-8,${encodeURIComponent( svg )}`;

        if ( this.getMarkerType() === 'legacy' ) {
            const clusterOptions = wp.hooks.applyFilters( 'wpslClusterMarkerOptions', {
                position,
                zIndex,
                title,
                // Forces Google Maps to respect the true SVG size. Without this the
                // optimized ( shared canvas ) render path miscalculates the bounding
                // box of data-URI SVGs during rapid redraws, and Chrome paints the
                // cluster icon as a partial segment instead of a full shape.
                optimized: false,
                icon: {
                    url: src,
                    scaledSize: new google.maps.Size( size, size )
                }
            } );

            return new google.maps.Marker( clusterOptions );
        }

        const svgImg = document.createElement( 'img' );
        svgImg.src    = src;
        svgImg.width  = size;
        svgImg.height = size;

        const clusterOptions = wp.hooks.applyFilters( 'wpslClusterMarkerOptions', {
            position,
            zIndex,
            title,
            content: svgImg
        } );

        return new importedLibraries.marker.AdvancedMarkerElement( clusterOptions );
    },

    /**
     * Programmatically trigger a click event on a marker, legacy or Advanced.
     *
     * @since  3.0.0
     * @param  {object} marker The marker to trigger click on
     * @return {void}
     */
    fireClick: function( marker ) {
        if ( marker.element ) {
            marker.element.click();
        } else {
            google.maps.event.trigger( marker, 'click' );
        }
    },

    /**
     * Whether a marker is on the map, not hidden in a cluster.
     *
     * @since  3.1.1
     * @param  {object} marker A legacy or Advanced marker.
     * @return {boolean}
     */
    isOnMap: function( marker ) {
        return marker.element ? !! marker.map : !! ( marker.getMap && marker.getMap() );
    },

    /**
     * The marker that shows a location: its own, or the one it shares with
     * the other locations at its address.
     *
     * @since  3.1.1
     * @param  {number} storeId  The location id
     * @param  {number} mapIndex The map index
     * @return {object|undefined} The marker
     */
    findMarker: function( storeId, mapIndex = RESULTS_MAP_INDEX ) {
        const list   = ( slData.provider.markers.active && slData.provider.markers.active[ mapIndex ] ) || [];
        const leadId = markerGroups.leadId( storeId, mapIndex );

        return list.find( ( item ) => item && item.storeId === leadId );
    },

    /**
     * Make the next click on a shared marker open on this location.
     *
     * @since  3.1.1
     * @param  {object} marker   The marker
     * @param  {number} storeId  The location id
     * @param  {number} mapIndex The map index
     * @return {void}
     */
    openOn: function( marker, storeId, mapIndex = RESULTS_MAP_INDEX ) {
        if ( marker && marker._wpslGroup && marker._wpslGroup.length > 1 ) {
            marker._wpslGroupStart = markerGroups.shownIndexOf( storeId, mapIndex );
        }
    },

    /**
     * Point a shared marker at the locations shown now, see
     * wpsl.api.showLocations(): its title, info window and cluster count
     * leave the hidden ones out.
     *
     * @since  3.1.1
     * @param  {object} marker   The marker
     * @param  {number} mapIndex The map index
     * @return {void}
     */
    applyShownGroup: function( marker, mapIndex ) {
        if ( ! marker || ! marker._wpslGroupAll ) {
            return;
        }

        const group = markerGroups.shownOf( marker._wpslGroupAll, mapIndex );

        if ( ! group.length ) {
            return;
        }

        marker._wpslGroup = group;

        const title = group.length > 1 ? groupWindow.markerTitle( group ) : helpers.decodeHtmlEntity( group[0].store );

        if ( typeof marker.setTitle === 'function' ) {
            marker.setTitle( title );
        } else {
            marker.title = title;
        }
    },

    /**
     * Pan to a store's marker on one map, reveal it from a cluster and open
     * it like a click. Used by wpsl.api.focusLocation().
     *
     * @since  3.1.1
     * @param  {number} storeId  The store ID.
     * @param  {number} mapIndex The map index.
     * @param  {object} options  options.zoom: zoom to at least this level.
     * @return {boolean} Whether the marker was found.
     */
    focus: function( storeId, mapIndex, options = {} ) {
        const map    = slData.maps[ mapIndex ];
        const marker = this.findMarker( storeId, mapIndex );

        if ( ! map || ! marker ) {
            return false;
        }

        // A newer focus on this map makes the callbacks of this one stale.
        const token = {};

        map._wpslFocusToken = token;

        const clusters   = !! ( map._wpslClusterEnabled && config.markers.cluster );
        const revealZoom = clusters ? Number( config.markers.cluster.zoom ) + 1 : 0;
        const clustered  = clusters && ! this.isOnMap( marker );
        const zoom       = map.getZoom();
        let target       = zoom;

        // options.zoom is a minimum: a clustered marker still gets revealed.
        if ( typeof options.zoom === 'number' ) {
            target = Math.max( options.zoom, clustered ? revealZoom : 0 );
        } else if ( clustered ) {
            target = Math.max( zoom, revealZoom );
        }

        map.panTo( marker.position || marker.getPosition() );

        if ( target !== zoom ) {
            map.setZoom( target );
        }

        const open = () => {
            if ( map._wpslFocusToken !== token ) {
                return;
            }

            const active = document.activeElement;

            // Closing the info window returns keyboard focus to the caller's
            // element, as it does for the store locator's result list.
            if ( active && active !== document.body && ! map.getDiv().contains( active ) ) {
                slData.markers.focusSourceElement = active;
            }

            this.openOn( marker, storeId, mapIndex );
            this.fireClick( marker );
        };

        // A marker on the map stays there, unless zooming out pulls it into a cluster.
        if ( this.isOnMap( marker ) && ! ( clusters && target < zoom ) ) {
            open();

            return true;
        }

        /*
         * The clusterer redraws on 'idle'. That never fires when nothing moved,
         * so a timer backs it up; whichever comes first goes on, the other
         * does nothing.
         */
        let settled  = false;
        let listener = null;
        let fallback = 0;

        const settle = () => {
            if ( settled ) {
                return;
            }

            settled = true;
            window.clearTimeout( fallback );
            google.maps.event.removeListener( listener );

            if ( map._wpslFocusToken !== token ) {
                return;
            }

            // A zoom out left the marker in a cluster, so zoom in until it shows.
            if ( clusters && ! this.isOnMap( marker ) && map.getZoom() < revealZoom ) {
                map.setZoom( revealZoom );
                google.maps.event.addListenerOnce( map, 'idle', open );
            } else {
                open();
            }
        };

        listener = google.maps.event.addListenerOnce( map, 'idle', settle );
        fallback = window.setTimeout( settle, 1000 );

        return true;
    },

    /**
     * Close the open info window on one map and restore its active markers,
     * as a click on the map does. Used by wpsl.api.closePopups().
     *
     * @since  3.1.1
     * @param  {number} mapIndex The map index.
     * @return {boolean} Whether the map exists.
     */
    closePopups: function( mapIndex ) {
        if ( ! slData.maps[ mapIndex ] ) {
            return false;
        }

        // The same per-map lookup as the map's click handler in wpsl-map.js.
        const ownInfoWindow = infoWindow.active.length ? infoWindow.active[ mapIndex ] : infoWindow.current;

        if ( ownInfoWindow ) {
            ownInfoWindow.close();
        }

        this.restoreActiveMarkers( mapIndex );

        return true;
    },

    /**
     * Show only the passed locations on a map, or all of them again, see
     * wpsl.api.showLocations(). Hidden markers leave the clusterer too, so
     * the cluster counts match what's shown. The start marker stays.
     *
     * @since  3.1.1
     * @param  {array|null} ids      Location ids, null for all.
     * @param  {number}     mapIndex The map index.
     * @param  {object}     options  options.fitBounds: fit the map to the shown markers.
     * @return {boolean} Whether the map exists.
     */
    showLocations: function( ids, mapIndex, options = {} ) {
        const map  = slData.maps[ mapIndex ];
        const list = ( this.active && this.active[ mapIndex ] ) || null;

        if ( ! map || ! Array.isArray( list ) ) {
            return false;
        }

        const clusterer = this.cluster && this.cluster[ mapIndex ];
        const shown     = [];
        const hidden    = [];

        list.forEach( ( marker ) => {
            if ( ! marker || ! marker.storeId ) {
                return;
            }

            ( ! ids || markerGroups.showsAny( marker.storeId, ids, mapIndex ) ? shown : hidden ).push( marker );
        });

        this.closePopups( mapIndex );

        markerGroups.setShown( ids, mapIndex );
        shown.forEach( ( marker ) => this.applyShownGroup( marker, mapIndex ) );

        if ( clusterer ) {
            const inClusterer = clusterer.markers || [];

            clusterer.removeMarkers( hidden.filter( ( marker ) => inClusterer.indexOf( marker ) !== -1 ), true );
            clusterer.addMarkers( shown.filter( ( marker ) => inClusterer.indexOf( marker ) === -1 ), true );
            clusterer.render();
        } else {
            // Legacy google.maps.Marker only hides via setMap(); AdvancedMarkerElement uses the map property.
            const place = ( marker, target ) => ( typeof marker.setMap === 'function' ? marker.setMap( target ) : ( marker.map = target ) );

            hidden.forEach( ( marker ) => place( marker, null ) );
            shown.forEach( ( marker ) => place( marker, map ) );
        }

        if ( options.fitBounds && shown.length ) {
            this.fitBounds( this.createLatLngBounds( shown ), map );
        }

        return true;
    },

    /**
     * Swap the hovered marker to its active icon so the bounce highlights it.
     *
     * @since  3.0.0
     * @param  {number} storeId The storeId of the hovered search result
     * @return {void}
     */
    setActiveOnHover: function( storeId ) {
        const activeMarkers = slData.provider.markers.active[ RESULTS_MAP_INDEX ] || [];

        // A location at a shared address highlights the marker it shares.
        storeId = markerGroups.leadId( storeId, RESULTS_MAP_INDEX );

        for ( let i = 0, len = activeMarkers.length; i < len; i++ ) {
            if ( activeMarkers[i].storeId === storeId ) {
                const marker        = activeMarkers[i];
                const hasOwnActive  = !! ( marker.locationMarkerUrlActive || marker.categoryMarkerUrlActive );

                if ( ! helpers.markers.maybeSetActivemarker( storeId, hasOwnActive ) ) {
                    return;
                }

                const markerData = hasOwnActive
                    ? {
                        locationMarkerUrlActive: marker.locationMarkerUrlActive,
                        categoryMarkerUrlActive: marker.categoryMarkerUrlActive,
                    }
                    : null;

                this.setActive( marker, markerData );
                break;
            }
        }
    },

    /**
     * Let a single marker bounce.
     *
     * @since  1.0.0
     * @param  {number} storeId The storeId of the marker that we need to bounce on the map
     * @param  {string} status  Indicates whether we should stop or start the bouncing
     * @return {void}
     */
    bounce: function( storeId, status ) {
        const activeMarkers = slData.provider.markers.active[ RESULTS_MAP_INDEX ] || [];

        storeId = markerGroups.leadId( storeId, RESULTS_MAP_INDEX );

        for ( let i = 0, len = activeMarkers.length; i < len; i++ ) {
            if ( activeMarkers[i].storeId === storeId ) {
                const marker = activeMarkers[i];

                // Keep the bouncing marker visible when another one covers it.
                if ( status === 'start' ) {
                    this.lift( marker, 'bounce' );
                } else {
                    this.lower( marker, 'bounce' );
                }

                // Advanced Marker - use CSS class on element
                if ( marker.element ) {
                    marker.element.classList.toggle( 'wpsl-bounce', status === 'start' );
                } else if ( marker.setAnimation ) { // Legacy Marker - use Google Maps Animation API
                    const animation = status === 'start' ? google.maps.Animation.BOUNCE : null;
                    marker.setAnimation( animation );
                }

                break;
            }
        }
    },

    /**
     * Zoom the map so that all markers fit in the window.
     *
     * @since  3.0.0
     * @param  {object} bounds Optional pass the marker bounds.
     * @param  {object} mapElem Optional the map object
     * @return {void}
     */
    fitBounds: function( bounds, mapElem ) {
        const mapObj = helpers.getMapObj( mapElem );

        if ( typeof bounds === 'undefined' ) {
            const mapIndex = mapObj._wpslMapIndex || 0;
            const markersArray = slData.provider.markers.active[mapIndex] || [];

            bounds = this.createLatLngBounds( markersArray );
        }

        helpers.map.attachBoundsChangedListener( mapObj, config.map.autoZoomLevel );

        this.setupMarkerAccessibility( mapObj );

        mapObj.fitBounds( bounds );
    },

    /**
     * Create latLng boundaries based on the markers
     *
     * @since  3.0.0
     * @param  {array}  markers
     * @return {object} bounds  The latLng boundaries based on the visible markers
     */
    createLatLngBounds: function( markers ) {
        const bounds = new google.maps.LatLngBounds();

        // A skipped start marker (the "ignore the default start location on page
        // load" option) is still created for the driving directions and still sits
        // in the active markers array, only hidden. Including its position would
        // make fitBounds zoom out to an invisible start point.
        const skipStart   = helpers.markers.maybeSkipStartMarker();
        const startMarker = slData.provider.markers.startLocation;

        // Add all store markers ( excluding the hidden start marker ).
        jQuery.each( markers, function( i ) {
            const marker = markers[i];
            if ( ! marker || ! marker.position ) {
                return;
            }

            if ( skipStart && startMarker && marker === startMarker ) {
                return;
            }

            bounds.extend( marker.position );
        });

        return bounds;
    },

    /**
     * Check what effect we need to trigger once a user hovers
     * over the store list. Either bounce the corresponding
     * marker up and down, open the info window or ignore it.
     *
     * @since  3.0.0
     * @return {void}
     */
    checkMouseOverEvent: function() {
        // Runs at bootstrap AND every time the directions are shown, so drop
        // the previous pair first or each directions view stacks another one.
        jQuery( '#wpsl-stores' ).off( '.wpslHover' );

        if ( config.ux.markerEffect === 'bounce' ) {
            jQuery( '#wpsl-stores' ).on( 'mouseenter.wpslHover', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                this.setActiveOnHover( storeId );
                this.bounce( storeId, 'start' );
            }.bind( this ) );

            jQuery( '#wpsl-stores' ).on( 'mouseleave.wpslHover', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                this.bounce( storeId, 'stop' );
                this.restoreActiveMarkers( RESULTS_MAP_INDEX );
            }.bind( this ) );
        } else if ( config.ux.markerEffect === 'info_window' ) {
            jQuery( '#wpsl-stores' ).on( 'mouseenter.wpslHover', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                const marker  = this.findMarker( storeId );

                if ( marker ) {
                    slData.maps[0].setCenter( marker.position );
                    this.openOn( marker, storeId );
                    this.fireClick( marker );
                }
            }.bind( this ) );
        }
    },

    /**
     * Trigger a click event on marker
     * that matches with the passed ID.
     *
     * @since  3.0.0
     * @param  {number} storeId ID of the marker that's we need to trigger the click event for
     * @return {void}
     */
    triggerClick: function( storeId ) {
        const mapIndex = 0;
        const activeMarkers = slData.provider.markers.active[mapIndex] || [];

        let markerClusterLen = '';

        // Check if clustering is active
        if ( slData.provider.markers.cluster && Object.keys( slData.provider.markers.cluster ).length > 0 ) {
            markerClusterLen = slData.provider.markers.cluster[mapIndex].clusters.length;
        }

        const leadId = markerGroups.leadId( storeId, mapIndex );

        for ( let i = 0; i < activeMarkers.length; i++ ) {
            const marker = activeMarkers[i];
            if ( marker.storeId === leadId ) {
                helpers.map.setCenter( marker.position );
                this.openOn( marker, storeId, mapIndex );
                this.fireClick( marker );

                // Check if marker clusters are active.
                if ( typeof markerClusterLen === 'number' && markerClusterLen !== activeMarkers.length ) {
                    slData.maps[0].setZoom( 14 );
                }

                break;
            }
        }
    },

    /**
     * Return the coordinates for the start location.
     *
     * @since  3.0.0
     * @return {object} The coordinates of the start location
     */
    getStartCoordinates: function() {
        let startCoordinates;

        // Without a start marker we fall back to the start point from the WPSL
        // settings page. That only happens when the category filter triggers an
        // automatic search and no results are found.
        const activeMarkers = slData.provider.markers.active[ RESULTS_MAP_INDEX ] || [];

        if ( typeof activeMarkers[0] !== 'undefined' ) {
            startCoordinates = activeMarkers[0].position;
        } else {
            startCoordinates = config.map.startLatLng;
        }

        return startCoordinates;
    },

    /**
     * Set the origin and destination markers
     * based on the passed coordinates.
     *
     * @since  3.0.0
     * @param  {object} originLatLng Origin coordinates
     * @param  {object} destinationLatLng Destination coordinates
     * @param  {object} stopDetails Object containing startAddress and endAddress
     * @return {void}
     */
    createDirectionStops: function( originLatLng, destinationLatLng, stopDetails ) {
        const directionStops = {
            'start': {
                'latLng': originLatLng,
                'title': 'Origin',
                'id': 0
            },
            'end': {
                'latLng': destinationLatLng,
                'title': 'Destination',
                'id': api.directions.storeId
            }
        };

        const markerType = this.getMarkerType();
        const resultMarkers = slData.provider.markers.active[ RESULTS_MAP_INDEX ] || [];

        jQuery.each( directionStops, ( index ) => {
            const markerData = helpers.markers.setMarkerUrl( directionStops[index] );
            let marker;

            // With the result's label, as the other providers show it; the marker labels script has the artwork.
            if ( index === 'end' ) {
                markerData.markerUrl = wp.hooks.applyFilters( 'wpslDirectionsMarkerUrl', markerData.markerUrl, directionStops[index].id );
            }

            if ( markerType === 'legacy' ) {
                marker = new google.maps.Marker({
                    position: directionStops[index].latLng,
                    map: slData.maps[0],
                    title: helpers.decodeHtmlEntity( directionStops[index].title ),
                    // See markers.legacy.create() — keeps SVG direction markers off
                    // the shared optimized canvas so they render at their true size.
                    optimized: false,
                    icon: this.shared.getIconProps( markerData.markerUrl )
                });
            } else {
                marker = new importedLibraries.marker.AdvancedMarkerElement({
                    position: directionStops[index].latLng,
                    map: slData.maps[0],
                    title: helpers.decodeHtmlEntity( directionStops[index].title ),
                    content: this.shared.createMarkerContent( markerData.markerUrl )
                });
            }

            marker.storeId = directionStops[index].id;
            marker.type = directionStops[index];

            // What setActive() and restoreActiveMarkers() read from a result marker.
            marker._iconUrl = markerData.markerUrl;
            marker._wpslMapIndex = RESULTS_MAP_INDEX;

            /*
             * AdvancedMarkerElement fires 'gmp-click' instead of 'click'; using
             * 'click' on it logs a deprecation warning. Legacy markers still use 'click'.
             */
            const clickEvent = markerType === 'legacy' ? 'click' : 'gmp-click';

            /*
             * The Routes API legs carry no start / end address, so each stop
             * opens what the result marker it replaces would: the start
             * location's title, or the store's own info window.
             */
            marker.addListener( clickEvent, ()=> {

                // A location at a shared address has no result marker of its own, the shared one stands in.
                const leadId = markerGroups.leadId( marker.storeId, RESULTS_MAP_INDEX );
                const source = resultMarkers.find( ( resultMarker ) => resultMarker.storeId === leadId );
                let infoWindowData;

                if ( marker.storeId === 0 ) {

                    // A marker title is plain text ( the decoded start label ), the window takes HTML.
                    infoWindowData = ( stopDetails && stopDetails.startAddress && sharedHelpers.escapeHtml( stopDetails.startAddress ) ) || sharedHelpers.escapeHtml( ( source && source.title ) || marker.title );
                } else if ( markerGroups.isFollower( marker.storeId, RESULTS_MAP_INDEX ) ) {

                    // The route goes to this location, so its window shows only this location.
                    infoWindowData = markerGroups.store( marker.storeId, RESULTS_MAP_INDEX );
                } else {
                    infoWindowData = ( source && source._wpslMarkerData ) || ( stopDetails && stopDetails.endAddress && sharedHelpers.escapeHtml( stopDetails.endAddress ) );
                }

                if ( ! infoWindowData ) {
                    return;
                }

                // The destination wears the active marker while its window is
                // open, the same as the result marker it stands in for.
                const activeData = source ? source._wpslMarkerData : null;

                this.restoreActiveMarkers( RESULTS_MAP_INDEX );

                if ( helpers.markers.maybeSetActivemarker( marker.storeId, !! ( activeData && ( activeData.locationMarkerUrlActive || activeData.categoryMarkerUrlActive ) ) ) ) {
                    this.setActive( marker, activeData );
                }

                infoWindow.setContent( marker, infoWindowData, slData.maps[0] );
            });

            slData.provider.markers.directionStops.push( marker );
        });
    }
};