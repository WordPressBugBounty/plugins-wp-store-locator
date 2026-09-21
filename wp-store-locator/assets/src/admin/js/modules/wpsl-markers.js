import { state } from './wpsl-shared.js';
import { helpers } from './wpsl-helpers.js';
import { mapObjects } from './wpsl-map-bootstrap.js';
import { importedLibraries } from '../../../common/wpsl-core.js';
import { sharedHelpers } from '../../../common/wpsl-shared-helpers.js';
import { markerIsDraggable } from '../../../common/wpsl-marker-drag-gate.js';

/**
 * Resolve the marker src a Location Marker dropdown currently shows.
 *
 * Both srcs come off the picked item, already resolved by PHP: its <img> for
 * the standard one, its data-retina-src for the sharper file. Never build a
 * retina filename here; marker + "@2x" 404s on folders that spell it
 * marker2x.png ( WPSL 2.3 ) or hold no variant, and means nothing for a data
 * URI or legacy attachment URL. No data-retina-src means PHP found nothing
 * sharper, so the standard artwork stands.
 *
 * @since 3.0.0
 * @param  {jQuery}  $dropdown The .wpsl-lm-dropdown to resolve from.
 * @param  {boolean} retina    Whether to ask for the marker's retina file.
 * @return {string} The marker image src, or '' when nothing resolves.
 */
export function dropdownMarkerSrc( $dropdown, retina ) {
    const $selected = $dropdown.find( '.wpsl-lm-item.selected' );

    if ( retina ) {
        const retinaSrc = $selected.attr( 'data-retina-src' );

        if ( retinaSrc ) {
            return retinaSrc;
        }
    }

    return $selected.find( 'img' ).attr( 'src' ) || '';
}

/**
 * The image src for the store marker shown on admin maps.
 *
 * The retina flag exists because providers disagree: Google and Mapbox want the
 * sharper file, Leaflet does not. PHP localizes the two srcs separately;
 * storeMarkerRetinaUrl holds the retina file on disk, or the standard src when
 * there is none.
 *
 * storeMarkerUrl is the standard src, only set when markerUrl + storeMarker
 * would be wrong: a Marker Manager marker resolves to a data URI, and a bundled
 * color is served from the plugin's folder on a site that filtered the marker
 * directory elsewhere.
 *
 * @since 3.0.0
 * @param  {boolean} retina Whether to ask for the marker's retina file.
 * @return {string} The marker image src
 */
export function storeMarkerSrc( retina ) {
    // On the store editor the Location Marker box is the source of truth;
    // wpsl-lm-preview-source marks the last-picked dropdown. Elsewhere no
    // dropdown exists, so the settings marker below is used.
    let $dropdown = jQuery( '.wpsl-lm-dropdown.wpsl-lm-preview-source' );

    if ( ! $dropdown.length ) {
        $dropdown = jQuery( '.wpsl-lm-dropdown' ).first();
    }

    if ( $dropdown.length ) {
        const src = dropdownMarkerSrc( $dropdown, retina );

        if ( src ) {
            return src;
        }
    }

    if ( retina && wpslSettings.storeMarkerRetinaUrl ) {
        return wpslSettings.storeMarkerRetinaUrl;
    }

    if ( wpslSettings.storeMarkerUrl ) {
        return wpslSettings.storeMarkerUrl;
    }

    return wpslSettings.markerUrl + wpslSettings.storeMarker;
}

/**
 * The box the preview map draws a marker in.
 *
 * Bundled markers are 24x35 pins anchored at bottom centre, hard-coded by every
 * provider. A Marker Studio marker is not necessarily a pin ( a circle forced
 * into 24x35 draws as an oval above its coordinate ), so its artwork carries
 * its own size and anchor, read here as the front end reads it.
 *
 * @since 3.0.0
 * @param  {string} src The marker image src.
 * @return {object} { width, height, anchor, popupAnchor, offset, position }
 */
export function previewMarkerGeometry( src ) {
    const custom = sharedHelpers.getCustomMarkerGeometry( src );

    if ( custom ) {
        return custom;
    }

    return {
        width: 24,
        height: 35,
        anchor: [ 12, 35 ],

        // The rule a custom marker gets from its own artwork: the pin's
        // height, the gap, and the pixel Leaflet's own placement works out to.
        popupAnchor: [ 0, -( 35 + sharedHelpers.markerGap - 1 ) ],
        offset: [ 0, 0 ],
        position: 'bottom'
    };
}

/**
 * Markup for bundled SVG markers already fetched, keyed by URL, and the
 * in-flight requests still arriving; a dropdown clicked through quickly asks
 * for the same file several times.
 */
const svgMarkupCache    = {};
const svgMarkupRequests = {};

/**
 * Fetch an SVG marker's markup, once per URL.
 *
 * @since 3.0.0
 * @param  {string} url The marker URL.
 * @return {Promise<string>} The markup.
 */
function fetchSvgMarkup( url ) {
    if ( svgMarkupCache[ url ] ) {
        return Promise.resolve( svgMarkupCache[ url ] );
    }

    if ( ! svgMarkupRequests[ url ] ) {
        svgMarkupRequests[ url ] = fetch( url )
            .then( ( response ) => {
                if ( ! response.ok ) {
                    throw new Error( 'Failed to load SVG marker: ' + url );
                }

                return response.text();
            } )
            .then( ( markup ) => {
                svgMarkupCache[ url ] = markup;

                return markup;
            } );
    }

    return svgMarkupRequests[ url ];
}

/**
 * An <img> marker element at fixed dimensions.
 *
 * @since   3.0.0
 * @param   {string} markerUrl URL of the marker image
 * @param   {object} geometry  The box previewMarkerGeometry() resolved
 * @returns {HTMLElement} An <img> element
 */
function createImgContent( markerUrl, geometry ) {
    const img = document.createElement( 'img' );

    img.src = markerUrl;
    img.width = geometry.width;
    img.height = geometry.height;
    img.style.cursor = 'pointer';

    return img;
}

/**
 * An inline-SVG marker element.
 *
 * @since   3.0.0
 * @param   {string} markerUrl URL of the SVG marker
 * @param   {object} geometry  The box previewMarkerGeometry() resolved
 * @returns {HTMLElement} A wrapper element containing the marker
 */
function createSvgContent( markerUrl, geometry ) {
    const wrapper = document.createElement( 'span' );

    wrapper.style.setProperty( 'display',     'block',  'important' );
    wrapper.style.setProperty( 'width',       geometry.width + 'px', 'important' );
    wrapper.style.setProperty( 'height',      geometry.height + 'px', 'important' );
    wrapper.style.setProperty( 'line-height', '0',      'important' );
    wrapper.style.cursor = 'pointer';

    const inject = ( markup ) => {
        /*
         * Marker artwork only: a custom marker's SVG is built server-side from
         * the shape table ( every value escaped ); a bundled one is a file in
         * the plugin's marker directory.
         */
        wrapper.innerHTML = markup;

        const svg = wrapper.querySelector( 'svg' );
        if ( svg ) {
            svg.setAttribute( 'width',  geometry.width );
            svg.setAttribute( 'height', geometry.height );
            svg.style.setProperty( 'display', 'block', 'important' );
        }
    };

    const inline = sharedHelpers.svgMarkupFromDataUri( markerUrl ) || svgMarkupCache[ markerUrl ];
    if ( inline ) {
        inject( inline );

        return wrapper;
    }

    wrapper.appendChild( createImgContent( markerUrl, geometry ) );

    fetchSvgMarkup( markerUrl ).then( inject ).catch( () => {} );

    return wrapper;
}

/**
 * The element a preview marker draws itself with, on every provider.
 *
 * @since   3.0.0
 * @param   {string} markerUrl URL of the marker image
 * @param   {object} geometry  The box previewMarkerGeometry() resolved
 * @returns {HTMLElement} An inline <svg> wrapper for vector artwork, an <img> otherwise
 */
export function createMarkerElement( markerUrl, geometry ) {
    return sharedHelpers.isSvgSrc( markerUrl )
        ? createSvgContent( markerUrl, geometry )
        : createImgContent( markerUrl, geometry );
}

export const markers = {
    /**
     * Google Maps marker actions.
     */
    gmaps: {
        /**
         * Create marker content as HTMLElement for AdvancedMarkerElement.
         *
         * @param   {string} markerUrl URL of the marker image
         * @returns {HTMLElement} The element to use as marker content
         */
        createMarkerContent: function( markerUrl ) {
            const geometry = previewMarkerGeometry( markerUrl );
            const element  = createMarkerElement( markerUrl, geometry );

            // AdvancedMarkerElement always places the content's bottom centre
            // on the coordinate, so artwork anchored elsewhere offsets itself
            // by the same amount the front end applies.
            if ( geometry.offset[0] || geometry.offset[1] ) {
                element.style.setProperty(
                    'transform',
                    'translate(' + geometry.offset[0] + 'px, ' + geometry.offset[1] + 'px)',
                    'important'
                );
            }

            return element;
        },

        /**
         * The icon properties for a legacy google.maps.Marker.
         *
         * @since   3.0.0
         * @param   {string} markerUrl URL of the marker image
         * @returns {object} An icon object for google.maps.Marker
         */
        getIconProps: function( markerUrl ) {
            const geometry = previewMarkerGeometry( markerUrl );

            return {
                url: markerUrl,
                scaledSize: new google.maps.Size( geometry.width, geometry.height ),
                anchor: new google.maps.Point( geometry.anchor[0], geometry.anchor[1] )
            };
        },

        /**
         * Whether to use the legacy marker class instead of Advanced Markers.
         *
         * Advanced Markers need a Map ID, but client-side JSON styles need a map
         * without one ( mutually exclusive ). We fall back to the legacy class,
         * or Google throws a "This page can't load Google Maps correctly" error.
         * Mirrors the condition in mapBootstrap.gmaps().
         *
         * @since   3.0.0
         * @returns {boolean}
         */
        usesLegacyMarkers: function() {
            const $dropdown        = jQuery( '#wpsl-gmaps-styles' );
            const $cloudBasedInput = jQuery( '#wpsl-cloud-based-id' );
            const $jsonInput       = jQuery( '#wpsl-map-style-gmaps' );

            // Style values come from DOM inputs on the settings page; the
            // editor and geocode tool have none, so fall back to mapStyle.
            const appearanceStyle = ( wpslSettings.mapStyle && wpslSettings.mapStyle.gmaps ) ? wpslSettings.mapStyle.gmaps : null;
            const selectedStyle = $dropdown.length > 0 ? $dropdown.val() : ( appearanceStyle ? appearanceStyle.selected : '' );

            if ( selectedStyle === 'json' ) {
                const jsonStyle = $jsonInput.length > 0 ? $jsonInput.val() : ( appearanceStyle ? appearanceStyle.json : '' );
                return !! ( jsonStyle && helpers.formatting.tryParseJSON( jsonStyle ) );
            }

            if ( selectedStyle === 'cloud_based' ) {
                return false;
            }

            // Fallback when no explicit style source is set.
            const cloudBasedId = $cloudBasedInput.length > 0 ? $cloudBasedInput.val() : ( appearanceStyle ? appearanceStyle.cloud_based : '' );
            if ( cloudBasedId && cloudBasedId.trim() ) {
                return false;
            }

            const jsonStyle = $jsonInput.length > 0 ? $jsonInput.val() : ( appearanceStyle ? appearanceStyle.json : '' );

            return !! ( jsonStyle && helpers.formatting.tryParseJSON( jsonStyle ) );
        },

        /**
         * Add a new marker to the map based on the provided coordinates.
         *
         * @param   {object} args Holds the latlng value and possible bool to open an infoWindow
         * @returns {void}
         */
        add: function( args ) {
            const map = mapObjects.get();

            if ( ! map ) {
                return;
            }

            const markerUrl = storeMarkerSrc( true );
            const isDraggable = markerIsDraggable( state.currentPage, ( wpslSettings.api || {} ).reverseGeocoding );
            const title = wp.hooks.applyFilters( 'wpslMarkerTitle', wpslL10n.storeLocator );

            let marker;

            if ( this.usesLegacyMarkers() ) {
                marker = new google.maps.Marker( {
                    position: args.latLng,
                    map: map,
                    draggable: isDraggable,
                    icon: this.getIconProps( markerUrl ),
                    title: title
                } );
            } else {
                marker = new importedLibraries.marker.AdvancedMarkerElement( {
                    position: args.latLng,
                    map: map,
                    gmpDraggable: isDraggable,
                    content: this.createMarkerContent( markerUrl ),
                    title: title
                } );
            }

            marker._markerUrl = markerUrl;

            state.activeMarkers.push( marker );

            if ( typeof args.openInfoWindow === 'boolean' && args.openInfoWindow ) {
                const shift = this.usesLegacyMarkers() ? 0 : previewMarkerGeometry( markerUrl ).offset[1];

                const infoWindow = new google.maps.InfoWindow({
                    className: 'custom-info-window',
                    pixelOffset: new google.maps.Size( 0, shift - ( sharedHelpers.markerGap + 1 ) )
                });

                marker.addListener( 'click', function() {
                    infoWindow.setContent( wpslL10n.popupContent );
                    infoWindow.open({
                        anchor: marker,
                        map: map,
                    });
                });
            }

            if ( isDraggable ) {
                marker.addListener( 'dragend', function() {
                    const draggedPosition = ( typeof marker.getPosition === 'function' ) ? marker.getPosition() : marker.position;
                    helpers.coordinates.setLatlng( draggedPosition );

                    if ( state.currentPage === 'onboarding' ) {
                        jQuery( '#wpsl-start-location' ).val( '' ).attr( 'placeholder', wpslOnboardingL10n.searching );

                        helpers.onboarding.updateStartLocationField( draggedPosition );
                    }
                });
            }
        },

        /**
         * Remove all the markers from the map and empty the array.
         *
         * @since   3.0.0
         * @returns {void}
         */
        removeAll: function() {
            if ( state.activeMarkers ) {
                for ( let i = 0; i < state.activeMarkers.length; i++ ) {
                    try {
                        // Legacy markers detach via setMap( null );
                        // Advanced Markers by clearing the map property.
                        if ( typeof state.activeMarkers[i].setMap === 'function' ) {
                            state.activeMarkers[i].setMap( null );
                        } else {
                            state.activeMarkers[i].map = null;
                        }
                    } catch ( error ) {
                        // Silently handle removal errors
                    }
                }

                state.activeMarkers.length = 0;
            }
        },

        /**
         * Repaint the existing preview markers with the currently
         * selected marker image.
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateIcon: function() {
            const markerUrl = storeMarkerSrc( true );

            for ( const marker of state.activeMarkers ) {
                if ( typeof marker.setIcon === 'function' ) {
                    // Legacy marker.
                    marker.setIcon( this.getIconProps( markerUrl ) );
                } else {
                    // Advanced marker.
                    marker.content = this.createMarkerContent( markerUrl );
                }

                marker._markerUrl = markerUrl;
            }
        },
    },

    /**
     * OpenStreetMaps marker actions.
     */
    osm: {
        layerGroup: null,

        /**
         * Build the Leaflet icon used for the admin preview marker.
         *
         * @param   {string} src The marker image src.
         * @returns {object} The L.icon() or L.divIcon() instance.
         */
        mapIcon: function( src ) {
            const geometry = previewMarkerGeometry( src );

            if ( ! sharedHelpers.isSvgSrc( src ) ) {
                return L.icon({
                    iconUrl: src,
                    iconSize: [ geometry.width, geometry.height ],
                    iconAnchor: geometry.anchor,
                    popupAnchor: geometry.popupAnchor
                });
            }

            return L.divIcon({
                html: createSvgContent( src, geometry ),
                className: 'wpsl-preview-marker',
                iconSize: [ geometry.width, geometry.height ],
                iconAnchor: geometry.anchor,
                popupAnchor: geometry.popupAnchor
            });
        },

        /**
         * Add a new marker to the map based on the provided coordinates.
         *
         * @param   {object} args Holds the latlng value and possible bool to open an infoWindow
         * @returns {void}
         */
        add: function( args ) {
            const map = mapObjects.get();

            if ( ! map ) {
                return;
            }

            // Clear marker and reset layerGroup (required when swapping themes).
            this.removeAll();
            this.layerGroup = L.layerGroup().addTo( map );

            const mapIcon = markers.osm.mapIcon( storeMarkerSrc( false ) );
            const marker = L.marker( [args.latLng.lat, args.latLng.lng], {
                icon: mapIcon,
                draggable: markerIsDraggable( state.currentPage, ( wpslSettings.api || {} ).reverseGeocoding )
            });

            marker.on( 'dragend', function() {
                const latLng = this.getLatLng();
                helpers.coordinates.setLatlng( latLng );

                if ( state.currentPage === 'onboarding' ) {
                    jQuery( '#wpsl-start-location' ).val( '' ).attr( 'placeholder', wpslOnboardingL10n.searching );

                    helpers.map.setViewport( {
                        elemId: 'wpsl-onboarding-map',
                        latLng: latLng,
                        zoom: 10
                   });

                    // Need the full address, so call updateStartLocationField.
                    helpers.onboarding.updateStartLocationField( latLng );
                }
            });

            state.activeMarkers = [marker];

            this.layerGroup.addLayer( marker );

            if ( typeof args.openInfoWindow === 'boolean' && args.openInfoWindow ) {
                marker.bindPopup( wpslL10n.popupContent, wp.hooks.applyFilters( 'wpslPopupOptions', { minWidth: 150, maxWidth: 360 } ) );

                marker.on('click', function() {
                    map.openPopup(this.getPopup(), this.getLatLng(), {
                        autoPan: true,
                    });
                });
            }
        },

        /**
         * Remove all the markers from the map and empty the array.
         *
         * @since   3.0.0
         * @returns {void}
         */
        removeAll: function() {
            if ( this.layerGroup ) {
                try {
                    this.layerGroup.clearLayers();
                    this.layerGroup = null;
                    state.activeMarkers = [];
                } catch ( error ) {
                    // Silently handle removal errors
                }
            }
        },

        /**
         * Repaint the existing preview markers with the currently
         * selected marker image.
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateIcon: function() {
            const mapIcon = markers.osm.mapIcon( storeMarkerSrc( false ) );

            ( state.activeMarkers || [] ).forEach( function( marker ) {
                marker.setIcon( mapIcon );
                sharedHelpers.reanchorLeafletPopup( marker );
            });
        },
    },

    /**
     * Mapbox marker actions.
     */
    mapbox: {
        /**
         * Add a new marker to the map based on the provided coordinates.
         *
         * @param   {object} args Holds the latlng value and possible bool to open an infoWindow
         * @returns {void}
         */
        add: function( args ) {
            const map = mapObjects.get( args.elemId );
            if ( ! map ) {
                return;
            }

            const markerUrl = storeMarkerSrc( true );
            const geometry  = previewMarkerGeometry( markerUrl );

            const el = document.createElement( 'div' );
            el.className = 'marker';
            el.style.width = geometry.width + 'px';
            el.style.height = geometry.height + 'px';
            el.style.zIndex = '1000';
            el.appendChild( createMarkerElement( markerUrl, geometry ) );

            try {
                const marker = new mapboxgl.Marker({
                    element: el,
                    anchor: 'bottom',
                    offset: geometry.offset,
                    draggable: markerIsDraggable( state.currentPage, ( wpslSettings.api || {} ).reverseGeocoding ),
                }).setLngLat( args.latLng )
                  .addTo( map );

                if ( typeof args.openInfoWindow === 'boolean' && args.openInfoWindow ) {
                    const popup = new mapboxgl.Popup({
                        anchor: 'bottom',
                        offset: sharedHelpers.mapboxPopupOffset( geometry ),
                        closeOnClick: true,
                        maxWidth: '300px'
                    }).setHTML( wpslL10n.popupContent );

                    marker.setPopup( popup );

                    // Pan map when popup opens to keep it visible.
                    popup.on( 'open', function() {
                        setTimeout( function() {
                            const popupElement = popup.getElement();

                            if ( ! popupElement ) {
                                return;
                            }

                            const popupRect = popupElement.getBoundingClientRect();
                            const mapContainer = map.getContainer();
                            const mapRect = mapContainer.getBoundingClientRect();

                            const padding = 20; // Edge padding.
                            let panX = 0;
                            let panY = 0;

                            if ( popupRect.top < mapRect.top ) {
                                const overflow = mapRect.top - popupRect.top;

                                panY = -(overflow + padding);
                            }

                            if ( popupRect.bottom > mapRect.bottom ) {
                                const overflow = popupRect.bottom - mapRect.bottom;

                                panY = overflow + padding;
                            }

                            if ( popupRect.left < mapRect.left ) {
                                const overflow = mapRect.left - popupRect.left;

                                panX = -(overflow + padding);
                            }

                            if ( popupRect.right > mapRect.right ) {
                                const overflow = popupRect.right - mapRect.right;

                                panX = overflow + padding;
                            }

                            if ( panX !== 0 || panY !== 0 ) {
                                map.panBy( [panX, panY], { duration: 300 } );
                            }
                        }, 50 );
                    });
                }

                state.activeMarkers.push( marker );

                marker.on( 'dragend', this.onDragEnd );
            } catch ( error ) {
                // Silently handle addition errors
            }
        },

        /**
         * Remove all the markers from the map and empty the array.
         *
         * @since   3.0.0
         * @returns {void}
         */
        removeAll: function() {
            if ( state.activeMarkers.length ) {
                for ( let i = 0; i < state.activeMarkers.length; i++ ) {
                    try {
                        if ( state.activeMarkers[i] && typeof state.activeMarkers[i].remove === 'function' ) {
                            state.activeMarkers[i].remove();
                        }
                    } catch ( error ) {
                        // Silently handle removal errors
                    }
                }

                state.activeMarkers = [];
            }
        },

        /**
         * Repaint the existing preview markers with the currently
         * selected marker image.
         *
         * @since   3.0.0
         * @returns {void}
         */
        updateIcon: function() {
            const markerUrl = storeMarkerSrc( true );
            const geometry  = previewMarkerGeometry( markerUrl );

            ( state.activeMarkers || [] ).forEach( function( marker ) {
                const el = marker.getElement();

                el.innerHTML = '';
                el.appendChild( createMarkerElement( markerUrl, geometry ) );
                el.style.width = geometry.width + 'px';
                el.style.height = geometry.height + 'px';

                if ( typeof marker.setOffset === 'function' ) {
                    marker.setOffset( geometry.offset );
                }
            });
        },

        /**
         * Handle the marker dragend event and update coordinates.
         *
         * @since   3.0.0
         * @returns {void}
         */
        onDragEnd: function() {
            const lngLat = state.activeMarkers[0].getLngLat();
            helpers.coordinates.setLatlng( lngLat );
            
            if ( state.currentPage === 'onboarding' ) {
                jQuery( '#wpsl-start-location' ).val( '' ).attr( 'placeholder', wpslOnboardingL10n.searching );

                helpers.onboarding.updateStartLocationField( lngLat );
            }
        },
    },

    /**
     * Stadia Maps marker actions. Uses the same Leaflet-based markers as OSM.
     */
    stadia: {
        layerGroup: null,
        add: function( args ) {
            markers.osm.add.call( this, args );
        },
        removeAll: function() {
            markers.osm.removeAll.call( this );
        },
        updateIcon: function() {
            markers.osm.updateIcon.call( this );
        },
    },
    
    /**
     * Return the marker handler for the currently active map service.
     *
     * @since  3.0.0
     * @return {object} The active map service's marker handler
     */
    getActive: function() {
        return this[ state.mapService ];
    },
};