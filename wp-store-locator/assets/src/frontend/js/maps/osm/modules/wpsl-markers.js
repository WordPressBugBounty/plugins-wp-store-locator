import { slData, config } from '../../../modules/wpsl-shared.js';
import { sharedHelpers } from '../../../../../common/wpsl-shared-helpers.js';
import { helpers } from '../../../modules/wpsl-helpers.js';
import { search } from '../../../modules/wpsl-search.js';
import { markerGroups } from '../../../modules/wpsl-marker-groups.js';
import { groupWindow } from '../../../modules/wpsl-group-window.js';
import { infoWindow } from './wpsl-infowindow.js';
import { api } from './wpsl-api.js';
import { map } from './wpsl-map.js';

/**
 * Mouse clicks and programmatic focus restoration also focus a marker, and
 * panning then shifts it out from under the cursor. Track the input modality
 * so focus handlers only pan for keyboard navigation.
 *
 * @since 3.0.0
 */
let wpslFocusViaKeyboard = false;
let wpslSuppressFocusPan = false;

function wpslInitFocusModalityTracking() {
    if ( document.body.hasAttribute( 'data-wpsl-focus-modality-init' ) ) {
        return;
    }

    document.body.setAttribute( 'data-wpsl-focus-modality-init', 'true' );

    document.addEventListener( 'keydown', function( e ) {
        if ( e.key === 'Tab' || ( e.key && e.key.indexOf( 'Arrow' ) === 0 ) ) {
            wpslFocusViaKeyboard = true;
        }
    }, true );

    [ 'mousedown', 'pointerdown', 'touchstart' ].forEach( function( type ) {
        document.addEventListener( type, function() {
            wpslFocusViaKeyboard = false;
        }, true );
    });
}

/**
 * Whether a focus event should pan the map into view.
 *
 * @since  3.0.0
 * @return {boolean}
 */
function wpslShouldPanOnFocus() {
    return wpslFocusViaKeyboard && ! wpslSuppressFocusPan;
}

/**
 * The number of locations in a cluster. A marker shared by the locations
 * at one address counts once for each of them.
 *
 * @since  3.1.1
 * @param  {object} cluster The L.MarkerCluster
 * @return {number}
 */
function wpslLocationCount( cluster ) {
    return cluster.getAllChildMarkers().reduce( ( total, marker ) => total + ( marker._wpslGroup ? marker._wpslGroup.length : 1 ), 0 );
}

/**
 * The markercluster default icon, with the number of locations instead of
 * markers. Only used when locations at one address share a marker.
 *
 * @since  3.1.1
 * @param  {object} cluster The L.MarkerCluster
 * @return {object} The L.DivIcon
 */
function wpslLocationClusterIcon( cluster ) {
    const count = wpslLocationCount( cluster );
    const size  = count < 10 ? 'small' : ( count < 100 ? 'medium' : 'large' );

    return new L.DivIcon( {
        html: '<div><span>' + count + '</span></div>',
        className: 'marker-cluster marker-cluster-' + size,
        iconSize: new L.Point( 40, 40 )
    } );
}

/**
 * OpenStreetMap markers functionality for WPSL frontend.
 *
 * @since 3.0.0
 */
export const markers = {
    active: {},
    latLng: {},
    current: {},
    cluster: '',
    startLocation: '',
    layer: '',
    currentMapIndex: 0,

    /**
     * Overlay marker for the "bounce" hover effect, and the real marker
     * hidden underneath it. See bounce() for the rationale.
     *
     * @since 3.0.0
     */
    _bounceMarker: null,
    _hiddenMarker: null,
    _bounceFrame: null,

    /**
     * fitBounds() calls that were deferred because the map container had no size
     * yet (created while display:none for the "hide map until first search"
     * option, or inside a hidden tab). Keyed by map index. Flushed by
     * map.invalidateSize() once the container becomes visible and sized.
     */
    pendingFit: {},

    /**
     * Direct reference to the start marker when it has been added straight
     * to the map (bypassing the cluster layer). Null when not active.
     *
     * @since 3.0.0
     * @type {L.Marker|null}
     */
    _startMarker: null,

    /**
     * See if we need to create a default object that holds the current map
     * and markers. It won't exist when e.g. directions are rendered on the map.
     *
     * @since  3.0.0
     * @param  {void|object} markerSetup Marker setup object
     * @return {object} markerSetup
     */
    checkSetup: function( markerSetup ) {
        if ( typeof markerSetup === 'object' ) {
            for ( const key in markerSetup ) {
                if ( markerSetup.hasOwnProperty( key ) ) {
                    if ( jQuery.isEmptyObject( markerSetup[key] ) && key === 'map' ) {
                        markerSetup[key] = slData.maps[0];
                    }
                }
            }
        } else {
            markerSetup = {
                markers: '',
                map: slData.maps[0]
            };
        }

        return markerSetup;
    },

    /**
     * Create the marker layer group
     * and add it to the map.
     *
     * @since  3.0.0
     * @param  {object} markerSetup Optional holds markers and map objects
     * @return {void}
     */
    init: function( markerSetup ) {
        markerSetup = this.checkSetup( markerSetup );

        // Don't use clusters if the directions are active on the map
        if ( helpers.markers.clusteringActive( markerSetup.map ) && ( typeof slData.directions === 'object' && ! slData.directions.active ) ) {
            let clusterOptions = wp.hooks.applyFilters( 'wpslMarkerClusterGroup', config.markers.cluster );

            // Count locations, not markers, unless the site brought its own cluster icon.
            if ( markerGroups.enabled() && clusterOptions && typeof clusterOptions.iconCreateFunction !== 'function' ) {
                clusterOptions = Object.assign( {}, clusterOptions, { iconCreateFunction: wpslLocationClusterIcon } );
            }

            this.layer = new L.markerClusterGroup( clusterOptions );

            const setupClusterKeyboard = function( clusterElement, childCount, clusterLatLng ) {
                if ( ! clusterElement ) {
                    return;
                }

                clusterElement.setAttribute( 'tabindex', '0' );
                clusterElement.setAttribute( 'role', 'button' );
                clusterElement.setAttribute( 'aria-label', wpslLabels.clusterTitle.replace( '%d', childCount ) );
                
                // Store coordinates for focus centering
                if ( clusterLatLng ) {
                    clusterElement.setAttribute( 'data-lat', clusterLatLng.lat );
                    clusterElement.setAttribute( 'data-lng', clusterLatLng.lng );
                }
                
                // Prevent duplicate event listeners
                if ( clusterElement._wpslKeydownAttached ) {
                    return;
                }

                clusterElement._wpslKeydownAttached = true;
                
                // Handle Enter/Space to expand cluster
                clusterElement.addEventListener( 'keydown', function( event ) {
                    if ( event.key === 'Enter' || event.key === ' ' || event.code === 'Space' ) {
                        event.preventDefault();
                        event.stopPropagation();
                        
                        // Get cluster position by looking up the Leaflet layer object
                        let clusterLat = NaN;
                        let clusterLng = NaN;
                        
                        if ( markers.layer && markers.layer._featureGroup && markers.layer._featureGroup._layers ) {
                            for ( const id in markers.layer._featureGroup._layers ) {
                                const layer = markers.layer._featureGroup._layers[ id ];

                                if ( layer && typeof layer.getElement === 'function' && layer.getElement() === clusterElement ) {
                                    const latLng = layer.getLatLng();

                                    clusterLat = latLng.lat;
                                    clusterLng = latLng.lng;

                                    break;
                                }
                            }
                        }
                        
                        clusterElement.click();
                        
                        // Restore focus after cluster expansion animation
                        if ( markers.layer && typeof markers.layer.once === 'function' ) {
                            markers.layer.once( 'animationend', function() {
                                setTimeout( function() {
                                    // Every marker and cluster, regardless of tabindex.
                                    const allFocusable = document.querySelectorAll( '.leaflet-marker-icon' );
                                    
                                    if ( allFocusable.length === 0 ) {
                                        return;
                                    }
                                    
                                    if ( isNaN( clusterLat ) || isNaN( clusterLng ) || ! markerSetup.map ) {
                                        allFocusable[0].focus();

                                        return;
                                    }
                                    
                                    const clusterPoint = markerSetup.map.latLngToContainerPoint( [ clusterLat, clusterLng ] );
                                    const mapRect = markerSetup.map.getContainer().getBoundingClientRect();
                                    
                                    let closestEl = null;
                                    let minDist = Infinity;
                                    
                                    allFocusable.forEach( function( el ) {
                                        const rect = el.getBoundingClientRect();
                                        const elScreenX = rect.left - mapRect.left + rect.width / 2;
                                        const elScreenY = rect.top - mapRect.top + rect.height / 2;
                                        
                                        const screenDist = Math.sqrt( ( elScreenX - clusterPoint.x ) ** 2 + ( elScreenY - clusterPoint.y ) ** 2 );
                                        
                                        if ( screenDist < minDist ) {
                                            minDist = screenDist;
                                            closestEl = el;
                                        }
                                    });
                                    
                                    if ( closestEl ) {
                                        closestEl.focus();
                                    }
                                }, 50 );
                            });
                        }
                    }
                });
            };
            
            // Add keyboard support when clusters are added to the map
            this.layer.on( 'clusteradd', function( e ) {
                const clusterElement = e.layer.getElement();
                const clusterLatLng = e.layer.getLatLng();
                const childCount = wpslLocationCount( e.layer );
                
                setupClusterKeyboard( clusterElement, childCount, clusterLatLng );
            });

            this.layer.addTo( markerSetup.map );
            
            if ( markerSetup.markers ) {
                this.layer.addLayers( markerSetup.markers );
            }

            const mapContainer = markerSetup.map.getContainer();

            if ( mapContainer && typeof MutationObserver !== 'undefined' ) {
                // init() re-runs when the directions are shown / restored, so
                // disconnect the previous observer instead of accumulating one
                // per run ( see _wpslFocusCenteringInitialized below ).
                if ( mapContainer._wpslClusterObserver ) {
                    mapContainer._wpslClusterObserver.disconnect();
                }

                const observer = new MutationObserver( function( mutations ) {
                    mutations.forEach( function( mutation ) {
                        mutation.addedNodes.forEach( function( node ) {
                            // Check if the added node is a cluster
                            if ( node.nodeType === 1 && node.classList && node.classList.contains( 'marker-cluster' ) ) {
                                const childCount = node.textContent || '?';
                                setupClusterKeyboard( node, childCount, null );
                            }

                            // Also check children of added nodes
                            if ( node.querySelectorAll ) {
                                const clusters = node.querySelectorAll( '.marker-cluster' );

                                if ( clusters.length > 0 ) {
                                    clusters.forEach( function( clusterEl ) {
                                        const childCount = clusterEl.textContent || '?';
                                        setupClusterKeyboard( clusterEl, childCount, null );
                                    });
                                }
                            }
                        });
                    });
                });
                
                observer.observe( mapContainer, {
                    childList: true,
                    subtree: true
                });

                mapContainer._wpslClusterObserver = observer;
            }
        } else {
            this.layer = new L.LayerGroup( markerSetup.markers );
            this.layer.addTo( markerSetup.map );
        }
        
        // Center the map on focused markers and clusters, so a keyboard-focused element stays visible.
        const mapForFocus = markerSetup.map;
        const mapContainer = mapForFocus ? mapForFocus.getContainer() : null;
        
        if ( mapContainer && ! mapContainer._wpslFocusCenteringInitialized ) {
            mapContainer._wpslFocusCenteringInitialized = true;
            wpslInitFocusModalityTracking();

            mapContainer.addEventListener( 'focus', function( e ) {
                const target = e.target;
                
                // Only handle markers and clusters
                const isMarker = target && target.classList.contains( 'leaflet-marker-icon' );
                const isCluster = target && target.classList.contains( 'marker-cluster' );
                
                if ( ! isMarker && ! isCluster ) {
                    return;
                }
                
                // Get the coordinates to center on
                let lat, lng;
                
                // Try data attributes first (set on individual markers)
                if ( target.hasAttribute( 'data-lat' ) && target.hasAttribute( 'data-lng' ) ) {
                    lat = parseFloat( target.getAttribute( 'data-lat' ) );
                    lng = parseFloat( target.getAttribute( 'data-lng' ) );
                }
                
                // For clusters, look up the cluster object to get accurate center
                if ( isCluster && markers.layer && markers.layer._featureGroup ) {
                    const featureGroup = markers.layer._featureGroup;

                    for ( const id in featureGroup._layers ) {
                        const layer = featureGroup._layers[ id ];
                        if ( layer && typeof layer.getElement === 'function' && layer.getElement() === target ) {
                            const clusterLatLng = layer.getLatLng();
                            lat = clusterLatLng.lat;
                            lng = clusterLatLng.lng;
                            break;
                        }
                    }
                }
                
                // Fallback: use the element's screen position
                if ( ( isNaN( lat ) || isNaN( lng ) ) && mapForFocus ) {
                    const rect = target.getBoundingClientRect();
                    const containerRect = mapContainer.getBoundingClientRect();
                    const centerX = rect.left - containerRect.left + rect.width / 2;
                    const centerY = rect.top - containerRect.top + rect.height / 2;
                    const latLng = mapForFocus.containerPointToLatLng( L.point( centerX, centerY ) );

                    lat = latLng.lat;
                    lng = latLng.lng;
                }
                
                // Never on mouse clicks or programmatic focus restoration
                // (see wpslShouldPanOnFocus).
                if ( ! isNaN( lat ) && ! isNaN( lng ) && mapForFocus && wpslShouldPanOnFocus() ) {
                    mapForFocus.panTo( [ lat, lng ] );
                }

            }, true ); // Use capture phase to run before Leaflet's handlers
        }

        // Arrow keys navigate between individual markers, never clusters.
        if ( ! document.body.hasAttribute( 'data-wpsl-arrow-nav-initialized' ) ) {
            document.body.setAttribute( 'data-wpsl-arrow-nav-initialized', 'true' );
            
            document.addEventListener( 'keydown', function( event ) {
                const focusedElement = document.activeElement;
                const isMarker = focusedElement && focusedElement.classList.contains( 'leaflet-marker-icon' ) && ! focusedElement.classList.contains( 'marker-cluster' );
                
                if ( ! isMarker ) {
                    return;
                }
                
                if ( event.key === 'ArrowUp' || event.key === 'ArrowDown' ) {
                    event.preventDefault();
                    
                    // Only navigate between individual markers, not clusters
                    const allMarkers = document.querySelectorAll( '.leaflet-marker-icon[tabindex="0"]:not(.marker-cluster)' );
                    const markerArray = Array.from( allMarkers );
                    const currentIndex = markerArray.indexOf( focusedElement );
                    
                    if ( currentIndex !== -1 ) {
                        let nextIndex;
                        
                        if ( event.key === 'ArrowDown' ) {
                            nextIndex = ( currentIndex + 1 ) % markerArray.length;
                        } else {
                            nextIndex = ( currentIndex - 1 + markerArray.length ) % markerArray.length;
                        }
                        
                        markerArray[nextIndex].focus();
                    }
                }
            });
        }
    },

    /**
     * Add a marker to the map.
     *
     * @since  3.0.0
     * @param  {object} markerData Marker data
     * @param  {object} map Map instance
     * @return {void}
     */
    add: function( markerData, map ) {
        // Extract coordinates from latLng before setProperties is called,
        // since the start marker coordinates are in latLng format.
        if ( markerData.latLng ) {
            if ( typeof markerData.latLng.lat === 'function' ) {
                markerData.lat = markerData.latLng.lat();
                markerData.lng = markerData.latLng.lng();
            } else {
                markerData.lat = markerData.latLng.lat;
                markerData.lng = markerData.latLng.lng;
            }
        }

        markerData = wp.hooks.applyFilters( 'wpslMarkerData', helpers.markers.setProperties( markerData ) );

        const visibility = helpers.markers.startVisibility( markerData );

        if ( isNaN( parseFloat( markerData.lat ) ) || isNaN( parseFloat( markerData.lng ) ) ) {
            return;
        }

        const iconProps = this.getIconProps( markerData.markerUrl );
        const mapIcon = L.icon( wp.hooks.applyFilters( 'wpslMapIcon', iconProps ) );

        // A marker that stands for every location at its address. The lead's
        // own data goes first, as filtered by wpslMarkerData.
        const groupMembers = markerGroups.members( markerData.id, this.currentMapIndex );
        const group        = groupMembers ? [ markerData ].concat( groupMembers.slice( 1 ) ) : null;
        const markerTitle  = group ? groupWindow.markerTitle( group ) : helpers.decodeHtmlEntity( markerData.store );

        const markerOptions = {
            clickable: ( visibility ) ? true : false,
            icon: mapIcon,
            title: markerTitle,
            alt: markerTitle,
            draggable: markerData.draggable,
            storeId: markerData.id
        };

        if ( config.markers.startOnTop ) {
            markerOptions.zIndexOffset = ( markerData.id === 0 ) ? 1000 : 0;
        }

        const marker = L.marker( [ Number( markerData.lat ), Number( markerData.lng ) ], wp.hooks.applyFilters( 'wpslMarker', markerOptions ) );

        if ( markerData.categoryMarkerUrlActive ) {
            marker.options.categoryMarkerUrlActive = markerData.categoryMarkerUrlActive;
        }

        if ( markerData.locationMarkerUrlActive ) {
            marker.options.locationMarkerUrlActive = markerData.locationMarkerUrlActive;
        }

        if ( group ) {

            // _wpslGroup holds the locations shown now, see applyShownGroup().
            marker._wpslGroupAll = group;
            marker._wpslGroup    = group;
        }

        // Prevent start marker from receiving keyboard focus
        if ( markerData.id === 0 ) {
            marker.once( 'add', function() {
                const el = this.getElement();

                if ( el ) {
                    el.setAttribute( 'tabindex', '-1' );
                }
            });
        }

        if ( markerData.id !== 0 ) {
            // A function, not the rendered string: Leaflet evaluates it when the
            // popup opens, so the underscore template doesn't run for every
            // store at add time.
            marker.bindPopup( function() {
                const shownGroup = marker._wpslGroup || null;

                if ( shownGroup && shownGroup.length > 1 ) {

                    // Leaflet opens the popup before the marker's own click handler runs,
                    // so a search result sets the location to open on in advance, see openOn().
                    if ( typeof marker._wpslGroupStart === 'number' ) {
                        marker._wpslGroupOpen  = marker._wpslGroupStart;
                        marker._wpslGroupStart = null;
                    }

                    return groupWindow.template( shownGroup, typeof marker._wpslGroupOpen === 'number' ? marker._wpslGroupOpen : null );
                }

                // One location at the address left by wpsl.api.showLocations() opens on its own.
                return helpers.template.getInfoWindowTemplate( shownGroup ? shownGroup[0] : markerData );
            }, wp.hooks.applyFilters( 'wpslPopupOptions', { minWidth: 150, maxWidth: 360 } ) );
        } else if ( visibility ) {
            marker.bindPopup( markerData.store );
        }

        const currentMapIndex = this.currentMapIndex;
        let currentMarkerElement = null;
        let popupRepaintTeardown = null;

        // The hidden start marker (id=0 with "ignore the default start location
        // on page load" enabled) never gets a popup bound, so getPopup() is
        // undefined: "Cannot read properties of undefined (reading 'on')".
        const markerPopup = marker.getPopup();

        if ( markerPopup ) {
            markerPopup.on( 'remove', function() {
                markers.restoreActiveMarkers( currentMapIndex );

                const allMarkers = document.querySelectorAll( '.leaflet-marker-icon' );
                allMarkers.forEach( function( markerEl ) {
                    // Exclude start marker (id=0) from being focusable
                    const storeId = markerEl.getAttribute( 'data-store-id' );

                    if ( storeId !== '0' ) {
                        markerEl.setAttribute( 'tabindex', '0' );
                    } else {
                        markerEl.setAttribute( 'tabindex', '-1' );
                    }
                });

                const popup = document.querySelector( '.leaflet-popup' );
                if ( popup ) {
                    const focusableElements = popup.querySelectorAll( 'a[href], button' );
                    focusableElements.forEach( function( element ) {
                        if ( element._wpslKeydownHandlers ) {
                            element._wpslKeydownHandlers.forEach( function( handler ) {
                                element.removeEventListener( 'keydown', handler );
                            });

                            delete element._wpslKeydownHandlers;
                        }
                    });
                }

                if ( currentMarkerElement ) {
                    // Restoring focus must not pan the map (keyboard a11y only).
                    wpslSuppressFocusPan = true;
                    currentMarkerElement.focus();
                    wpslSuppressFocusPan = false;
                    currentMarkerElement = null;
                }
            });
        }

        marker.on( 'popupopen', function() {
            markers.restoreActiveMarkers( currentMapIndex );

            if ( helpers.markers.maybeSetActivemarker( markerData.id, !! ( markerData.locationMarkerUrlActive || markerData.categoryMarkerUrlActive ) ) ) {
                markers.setActive( marker, markerData, currentMapIndex );
            }

            currentMarkerElement = marker.getElement();

            // Without this, panning or zooming with the popup open leaves its
            // text/buttons permanently blurry - a stale low quality raster left
            // behind by the browser's compositor. Re-snapped on every
            // 'moveend'/'zoomend' for as long as the popup stays open.
            popupRepaintTeardown = helpers.popup.hardRepaintOnSettle( map, function() {
                return document.querySelector( '.leaflet-popup' );
            });

            const allMarkers = document.querySelectorAll( '.leaflet-marker-icon' );
            
            allMarkers.forEach( function( markerEl ) {
                markerEl.setAttribute( 'tabindex', '-1' );
            });

            setTimeout( function() {
                const popup = document.querySelector( '.leaflet-popup' );
                const closeButton = document.querySelector( '.leaflet-popup-close-button' );

                if ( ! popup || ! closeButton ) {
                    return;
                }

                if ( ! closeButton.hasAttribute( 'tabindex' ) ) {
                    closeButton.setAttribute( 'tabindex', '0' );
                }
                
                const popupLinks = popup.querySelectorAll( '.leaflet-popup-content a' );

                popupLinks.forEach( function( link ) {
                    if ( ! link.hasAttribute( 'tabindex' ) ) {
                        link.setAttribute( 'tabindex', '0' );
                    }
                });

                // The popup's links and buttons, such as a shared popup's list of locations or an add-on's button.
                const contentLinks = helpers.infoWindowControls( popup.querySelector( '.leaflet-popup-content' ) );
                const firstContentLink = contentLinks[0];

                if ( firstContentLink ) {
                    firstContentLink.focus();
                } else {
                    closeButton.focus();
                }
                
                const handleFirstLinkKeydown = function( e ) {
                    if ( e.key === 'Escape' ) {
                        marker.closePopup();
                        return;
                    }
                    
                    if ( e.key === 'Tab' && e.shiftKey ) {
                        e.preventDefault();
                        closeButton.focus();
                    }
                };
                
                const handleEscapeKey = function( e ) {
                    if ( e.key === 'Escape' ) {
                        marker.closePopup();
                    }
                };
                
                if ( firstContentLink ) {
                    firstContentLink.addEventListener( 'keydown', handleFirstLinkKeydown );
                    if ( ! firstContentLink._wpslKeydownHandlers ) {
                        firstContentLink._wpslKeydownHandlers = [];
                    }

                    firstContentLink._wpslKeydownHandlers.push( handleFirstLinkKeydown );
                }
                
                closeButton.addEventListener( 'keydown', handleEscapeKey );
                if ( ! closeButton._wpslKeydownHandlers ) {
                    closeButton._wpslKeydownHandlers = [];
                }

                closeButton._wpslKeydownHandlers.push( handleEscapeKey );
                
                contentLinks.forEach( function( link, index ) {
                    if ( index > 0 ) {
                        link.addEventListener( 'keydown', handleEscapeKey );
                        if ( ! link._wpslKeydownHandlers ) {
                            link._wpslKeydownHandlers = [];
                        }

                        link._wpslKeydownHandlers.push( handleEscapeKey );
                    }
                });
            }, 100 );

            infoWindow.actions( marker.getLatLng() );

            if ( marker._wpslGroup && marker._wpslGroup.length > 1 ) {
                groupWindow.bind( marker.getPopup().getElement(), {
                    stores: marker._wpslGroup,
                    marker: marker,
                    map: slData.maps[ currentMapIndex ]
                });
            }
        });

        marker.on( 'popupclose', function() {
            if ( popupRepaintTeardown ) {
                popupRepaintTeardown();
                popupRepaintTeardown = null;
            }

            // The next click on the marker opens on the first location again.
            marker._wpslGroupOpen = null;

            // The element that opened the popup, either a marker or a search result.
            const sourceEl = slData.markers.focusSourceElement;

            if ( sourceEl && document.body.contains( sourceEl ) ) {
                // Wait for the popup to leave the DOM before restoring focus.
                setTimeout( () => {
                    if ( document.body.contains( sourceEl ) ) {
                        if ( ! sourceEl.hasAttribute( 'tabindex' ) ) {
                            sourceEl.setAttribute( 'tabindex', '0' );
                        }

                        sourceEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
                        sourceEl.focus();
                    }

                    slData.markers.focusSourceElement = null;
                }, 50 );
            } else {
                // Fallback: focus the marker if source element is gone
                setTimeout( () => {
                    const markerElement = marker.getElement();
                    if ( markerElement && document.body.contains( markerElement ) ) {
                        markerElement.focus();
                    }

                    slData.markers.focusSourceElement = null;
                }, 50 );
            }
        });

        this.active[currentMapIndex].push( marker );

        // Only feed the coordinates into fitBounds when the marker is shown.
        // Including the hidden start marker zooms the map out to an invisible
        // start point. Matches Google Maps.
        if ( visibility ) {
            this.latLng[currentMapIndex].push( [ markerData.lat, markerData.lng ] );
        }

        // Track start marker location for keyboard navigation sorting
        if ( markerData.id === 0 ) {
            this.startLocation = {
                lat: Number( markerData.lat ),
                lng: Number( markerData.lng )
            };
        }

        if ( visibility ) {
            // With "exclude start marker" the start marker goes straight onto
            // the Leaflet map instead of the cluster layer, so it is never
            // absorbed into a cluster icon and its zIndexOffset keeps applying.
            const isClusterGroup     = this.layer && typeof this.layer.getVisibleParent === 'function';
            const excludeStartMarker = isClusterGroup &&
                                       typeof config.markers.cluster === 'object' &&
                                       config.markers.cluster.excludeStartMarker;

            if ( markerData.id === 0 && excludeStartMarker ) {
                const mapObj = slData.maps[ this.currentMapIndex ];

                marker.addTo( mapObj );
                this._startMarker = marker;
            } else {
                this.layer.addLayer( marker );
            }

            const setupKeyboardNav = function() {
                setTimeout( function() {
                    const markerElement = marker.getElement();

                    if ( markerElement ) {
                        // Exclude start marker (id=0) from being focusable
                        if ( markerData.id !== 0 ) {
                            markerElement.setAttribute( 'tabindex', '0' );
                        } else {
                            markerElement.setAttribute( 'tabindex', '-1' );
                        }

                        const markerLatLng = marker.getLatLng();

                        markerElement.setAttribute( 'data-lat', markerLatLng.lat );
                        markerElement.setAttribute( 'data-lng', markerLatLng.lng );
                        markerElement.setAttribute( 'data-store-id', marker.options.storeId );

                        if ( marker._panOnFocus ) {
                            marker._panOnFocus = function() {};
                        }
                        
                        if ( markerElement._wpslKeydownAttached ) {
                            return;
                        }

                        markerElement._wpslKeydownAttached = true;

                        // Pan map to this marker when it receives keyboard focus
                        // and make sure we check the min zoom level.
                        markerElement.addEventListener( 'focus', function() {
                            const mapObj = slData.maps[ currentMapIndex ];

                            if ( mapObj && wpslShouldPanOnFocus() ) {
                                const minZoom = config.ux.keyboardFocusMinZoom || 6;
                                const currentZoom = mapObj.getZoom();

                                if ( currentZoom < minZoom ) {
                                    mapObj.setView( marker.getLatLng(), minZoom );
                                } else {
                                    mapObj.panTo( marker.getLatLng() );
                                }
                            }
                        });

                        markerElement.addEventListener( 'keydown', function( e ) {
                            if ( e.key === 'Enter' || e.key === ' ' || e.code === 'Space' ) {
                                e.preventDefault();
                                e.stopPropagation();
                                
                                if ( marker.isPopupOpen() ) {
                                    marker.closePopup();
                                } else {
                                    markerElement.click();
                                }
                            }
                        });
                    }
                }, 100 );
            };
            
            setupKeyboardNav();
            marker.on( 'add', setupKeyboardNav );

            marker.on( 'click', function() {
                // Track the source element for focus restoration on popup close.
                // Already set means the click came from a search result, so keep it.
                if ( slData.markers.focusSourceElement === null || slData.markers.focusSourceElement === undefined ) {
                    const markerEl = marker.getElement();

                    if ( markerEl ) {
                        slData.markers.focusSourceElement = markerEl;
                    }
                }

                infoWindow.actions( this.getLatLng() );

                markers.restoreActiveMarkers( currentMapIndex );

                if ( helpers.markers.maybeSetActivemarker( markerData.id, !! ( markerData.locationMarkerUrlActive || markerData.categoryMarkerUrlActive ) ) ) {
                    markers.setActive( marker, markerData, currentMapIndex );
                }

                // A shared marker announces the location its popup opened on. _wpslGroupOpen
                // is an index in the locations shown now ( wpsl.api.showLocations() ), like the popup.
                const shownGroup = marker._wpslGroup || null;
                const shownData  = shownGroup && shownGroup.length > 1 ? shownGroup[ marker._wpslGroupOpen || 0 ] : ( shownGroup && shownGroup.length === 1 ? shownGroup[0] : markerData );

                wp.hooks.doAction( 'wpslMarkerClicked', marker, shownData, slData.maps[currentMapIndex] );
            });
        }

        if ( markerData.draggable ) {
            let draggableArgs;

            marker.on( 'dragend', function( e ) {
                draggableArgs = this.getLatLng();
                slData.setSearchInput = true;

                markers.removeAll();
                markers.add( this.getLatLng(), slData.maps[0] );

                helpers.map.setCenter( this.getLatLng() );

                markerData.lat = this.getLatLng().lat;
                markerData.lng = this.getLatLng().lng;

                draggableArgs.markerDragged = true;

                // Go through the active provider's API, not the statically
                // imported OSM one: Stadia reuses this module and needs the
                // Pelias reverse instead of Nominatim's.
                slData.provider.api.geocoding.reverse( draggableArgs, search.current( function() {
                    search.run( markerData );
                }) );
            });
        }
    },

    /**
     * Get the icon properties.
     *
     * @since  3.0.0
     * @param  {string} iconUrl path to the marker image
     * @return {object} the properties used to create a new icon
     */
    getIconProps: function( iconUrl ) {
        // Remove the @2x suffix to prevent blurry display on non-retina screens.
        // Safe for a custom marker's inline SVG only because
        // Custom_Markers::get_data_uri() rawurlencode()s the payload, turning any
        // literal "@" into "%40". Switching that encoding ( e.g. to base64 ) would
        // silently corrupt an SVG containing "@2x", so this replace would then
        // have to become URL-only.
        const cleanIconUrl = iconUrl.replace( '@2x', '' );

        // A custom marker's artwork carries its own size and anchor, so a
        // circular or square shape isn't stretched into the bundled pins' box.
        const custom = helpers.markers.getCustomMarkerGeometry( cleanIconUrl );

        const props = wp.hooks.applyFilters( 'wpslMapIcon', {
            iconUrl: cleanIconUrl,
            iconSize: custom ? [ custom.width, custom.height ] : Object.values( config.markers.scaledSize ),
            iconAnchor: custom ? custom.anchor : Object.values( config.markers.iconAnchor ),
            popupAnchor: custom ? custom.popupAnchor : Object.values( config.markers.popupAnchor )
        });

        return props;
    },

    /**
     * Set the correct active marker image
     *
     * @since   3.0.0
     * @param   {object} marker
     * @param   {object} markerData Optional marker data holding the per-store and per-category active marker URLs
     * @param   {number} mapIndex   The map index this marker belongs to
     * @returns {void}
     */
    setActive: function( marker, markerData, mapIndex ) {
        const settings = helpers.markers.getSettings();

        this.restoreActiveMarkers( mapIndex );

        if ( typeof slData.restoreActiveMarker === 'undefined' ) {
            slData.restoreActiveMarker = {};
        }

        slData.restoreActiveMarker[mapIndex] = {
            id: marker.options.storeId,
            iconUrl: marker.options.icon.options.iconUrl
        };

        let activeMarkerUrl;

        if ( markerData && markerData.locationMarkerUrlActive ) {
            activeMarkerUrl = markerData.locationMarkerUrlActive;
        } else if ( markerData && markerData.categoryMarkerUrlActive ) {
            activeMarkerUrl = markerData.categoryMarkerUrlActive;
        } else {
            activeMarkerUrl = helpers.markers.resolveMarkerSrc( config.markers.active, settings.url );
        }

        const activeIconProps = this.getIconProps( activeMarkerUrl );
        const activeIcon = L.Icon.extend({
            options: activeIconProps
        });

        marker.setIcon( new activeIcon );

        // The active image is rarely the same height as the one it replaces,
        // and the popup is already open by the time we get here.
        sharedHelpers.reanchorLeafletPopup( marker );

        this.current[mapIndex] = marker;
    },

    /**
     * Reset any marker that was set active back to its default icon.
     *
     * @since   3.0.0
     * @param   {number} mapIndex The map index to restore markers for
     * @returns {void}
     */
    restoreActiveMarkers: function( mapIndex ) {
        if ( typeof mapIndex === 'undefined' ) {
            mapIndex = this.currentMapIndex;
        }
        
        if ( typeof slData.restoreActiveMarker === 'object' && typeof slData.restoreActiveMarker[mapIndex] === 'object' ) {
            if ( typeof this.active[mapIndex] !== 'undefined' ) {
                const self = this;

                jQuery.each( this.active[mapIndex], function( i ) {
                    if ( slData.restoreActiveMarker[mapIndex].id === self.active[mapIndex][i].options.storeId ) {
                        const originalIconProps = self.getIconProps( slData.restoreActiveMarker[mapIndex].iconUrl );
                        const originalIcon = L.Icon.extend({
                            options: originalIconProps
                        });

                        self.active[mapIndex][i].setIcon( new originalIcon );

                        // A hover restore can land while this marker's popup
                        // is still open, so the same re-anchor applies.
                        sharedHelpers.reanchorLeafletPopup( self.active[mapIndex][i] );
                    }
                });
            }
        }
    },

    /**
     * Remove all markers from the map.
     *
     * @since   3.0.0
     * @return {void}
     */
    removeAll: function() {
        const mapIndex = this.currentMapIndex;

        markerGroups.clear( mapIndex );

        api.directions.removePolyline();

        // Close open popups first, or their DOM elements outlive the markers.
        if ( this.layer && typeof this.layer.eachLayer === 'function' ) {
            this.layer.eachLayer( function( layer ) {
                if ( layer && typeof layer.closePopup === 'function' ) {
                    layer.closePopup();
                }
            });
        }

        // Also close popup on the map level
        const mapObj = slData.maps[mapIndex];

        if ( mapObj && typeof mapObj.closePopup === 'function' ) {
            mapObj.closePopup();
        }

        // When the start marker was added directly to the map (cluster bypass), remove it now.
        if ( this._startMarker ) {
            this._startMarker.remove();
            this._startMarker = null;
        }

        this.layer.clearLayers();

        if ( typeof this.latLng[mapIndex] !== 'undefined' ) {
            this.latLng[mapIndex].length = 0;
        }

        if ( typeof this.active[mapIndex] !== 'undefined' ) {
            this.active[mapIndex].length = 0;
        }

        this.startLocation = null;

        // Prevent a stale reference.
        slData.markers.focusSourceElement = null;

        // Reset the focus state so the next search handles focus correctly.
        const mapContainer = document.querySelector( '.wpsl-canvas-' + ( config && config.map && config.map.provider ? config.map.provider : 'osm' ) );
        
        if ( mapContainer && typeof mapContainer._wpslResetFocusState === 'function' ) {
            mapContainer._wpslResetFocusState();
        }

        // Remove focus from the map container
        if ( document.activeElement && mapContainer && mapContainer.contains( document.activeElement ) ) {
            document.activeElement.blur();
        }
    },

    /**
     * Make sure all markers fit on the viewport.
     *
     * @since   3.0.0
     * @param   {object} bounds  The optional marker bounds
     * @param   {object} mapObj  The optional map object
     * @param   {object} options The optional fitBounds options
     * @return {void}
     */
    fitBounds: function( bounds, mapObj, options ) {
        mapObj = helpers.getMapObj( mapObj );

        let mapIndex = this.currentMapIndex;

        if ( typeof bounds === 'undefined' ) {
            bounds = this.latLng[mapIndex] || [];
        } else if ( typeof slData.maps !== 'undefined' ) {
            // An explicit map was passed (e.g. a [wpsl_map] shortcode); resolve its index.
            const idx = slData.maps.indexOf( mapObj );

            if ( idx !== -1 ) {
                mapIndex = idx;
            }
        }

        // Nothing to fit, e.g. a [wpsl_map] whose locations are all unpublished.
        // Leaflet throws on empty bounds, so keep the configured viewport.
        if ( ! L.latLngBounds( bounds ).isValid() ) {
            return;
        }

        // Don't fit while the container is 0x0 - the "Hide the map until the
        // first search" option creates the map under display:none, as does a
        // [wpsl_map] inside a hidden tab. Fitting then computes a bogus (max)
        // zoom AND starts a zoom animation, and setView() bails out while
        // _animatingZoom is true, so the correct fit is later ignored. Remember
        // it instead and let map.invalidateSize() flush it once sized.
        const size = mapObj.getSize();

        if ( ! size.x || ! size.y ) {
            this.pendingFit[mapIndex] = { bounds: bounds, mapObj: mapObj, options: options };

            return;
        }

        delete this.pendingFit[mapIndex];

        map.attachBoundsChangedListener( mapObj, config.map.autoZoomLevel );

        const fitOptions = jQuery.extend( { padding: [ 50, 50 ] }, options || {} );
        mapObj.fitBounds( bounds, fitOptions );
    },

    /**
     * Return the coordinates from the start location marker.
     *
     * @since   3.0.0
     * @returns {object} The coordinates of the start marker
     */
    getStartCoordinates: function() {
        const mapIndex = this.currentMapIndex;

        let startCoordinates;

        if ( typeof this.active[mapIndex] !== 'undefined' && typeof this.active[mapIndex][0] !== 'undefined' ) {
            startCoordinates = this.active[mapIndex][0].getLatLng();
        } else {
            startCoordinates = {
                lat: config.map.startLatLng.lat,
                lng: config.map.startLatLng.lng
            };
        }

        return startCoordinates;
    },

    /**
     * Only show the markers that belong to the passed id's.
     *
     * @since   3.0.0
     * @param   {array}    ids      Marker id's that should be visible
     * @param	{callback} callback
     * @returns {void}
     */
    showSelection: function( ids, callback ) {
        const mapIndex = this.currentMapIndex;

        let bounds = [];

        if ( typeof this.active[mapIndex] !== 'undefined' ) {
            const self = this;
            
            jQuery.each( this.active[mapIndex], function( i ) {
                if ( jQuery.inArray( self.active[mapIndex][i].options.storeId, ids ) !== -1 ) {
                    self.active[mapIndex][i].addTo( slData.maps[mapIndex] );
                    bounds.push( self.active[mapIndex][i].getLatLng() );
                } else {
                    self.active[mapIndex][i].remove();
                }
            });
        }

        this.fitBounds( bounds );

        callback();
    },

    /**
     * Bounce the marker that matches the passed store id.
     *
     * @since  3.0.0
     * @param  {number} storeId The store id of the marker that should bounce
     * @param  {string} status  'start' to begin bouncing, anything else to stop
     * @return {void}
     */
    bounce: function( storeId, status ) {
        const mapIndex = this.currentMapIndex;
        const mapObj   = slData.maps[mapIndex];

        // Always stop a previous bounce before doing anything else.
        if ( this._bounceFrame ) {
            cancelAnimationFrame( this._bounceFrame );
            this._bounceFrame = null;
        }

        if ( this._bounceMarker ) {
            mapObj.removeLayer( this._bounceMarker );
            this._bounceMarker = null;
        }

        if ( this._hiddenMarker ) {
            this._hiddenMarker.setOpacity( 1 );
            this._hiddenMarker = null;
        }

        if ( status !== 'start' || typeof this.active[mapIndex] === 'undefined' ) {
            return;
        }

        // A location at a shared address bounces the marker it shares.
        storeId = markerGroups.leadId( storeId, mapIndex );

        let target = null;

        for ( let i = 0, len = this.active[mapIndex].length; i < len; i++ ) {
            if ( this.active[mapIndex][i].options.storeId === storeId ) {
                target = this.active[mapIndex][i];
                break;
            }
        }

        // A clustered marker has no element to overlay, so skip it.
        if ( ! target || ! target.getElement() ) {
            return;
        }

        const iconOptions = target.options.icon.options;
        const iconSize    = iconOptions.iconSize;
        const baseLatLng  = target.getLatLng();

        // Build the bounce image as a DOM node and let the browser serialize it,
        // so an attacker-controlled marker URL can never break out of the src
        // attribute ( defense in depth alongside the wpsl_marker_src() allowlist ).
        const bounceImg = document.createElement( 'img' );

        bounceImg.src          = iconOptions.iconUrl;
        bounceImg.alt          = '';
        bounceImg.style.width  = iconSize[0] + 'px';
        bounceImg.style.height = iconSize[1] + 'px';

        const bounceIcon = L.divIcon({
            className:  'wpsl-marker-bounce',
            iconSize:   iconSize,
            iconAnchor: iconOptions.iconAnchor,
            html:       bounceImg.outerHTML
        });

        this._bounceMarker = L.marker( baseLatLng, {
            icon:         bounceIcon,
            interactive:  false,
            keyboard:     false,
            zIndexOffset: 2000
        }).addTo( mapObj );

        target.setOpacity( 0 );
        this._hiddenMarker = target;

        const self      = this;
        const height_px = config.markers.bounceHeight || 12;
        const period    = config.markers.bouncePeriod || 500;
        const startTime = ( typeof performance !== 'undefined' ) ? performance.now() : Date.now();

        const step = function( now ) {
            // Rests at offset 0 and only lifts upward; abs(sin) touches down
            // each cycle, matching the Google Maps bounce.
            const offset = -Math.abs( Math.sin( ( ( now - startTime ) / period ) * Math.PI ) ) * height_px;
            const point  = mapObj.latLngToContainerPoint( baseLatLng );

            point.y += offset;

            self._bounceMarker.setLatLng( mapObj.containerPointToLatLng( point ) );
            self._bounceFrame = requestAnimationFrame( step );
        };

        self._bounceFrame = requestAnimationFrame( step );
    },

    /**
     * Check what effect we need to trigger once a user hovers over the store
     * list. Either bounce the corresponding marker up and down or ignore it.
     *
     * @since  3.0.0
     * @return {void}
     */
    checkMouseOverEvent: function() {
        if ( config.ux.markerEffect === 'bounce' ) {
            jQuery( '#wpsl-stores' ).on( 'mouseenter', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                this.setActiveOnHover( storeId );
                this.bounce( storeId, 'start' );
            }.bind( this ) );

            jQuery( '#wpsl-stores' ).on( 'mouseleave', 'li', function( e ) {
                const storeId = jQuery( e.currentTarget ).data( 'store-id' );
                this.bounce( storeId, 'stop' );

                // Unconditional: a gated restore would strand a per-store active
                // icon on mouseleave, and this is a no-op when nothing was set active.
                this.restoreActiveMarkers( this.currentMapIndex );
            }.bind( this ) );
        }
    },

    /**
     * Swap the hovered marker to its active icon. The bounce overlay reads the
     * marker's current icon, so the bouncing copy picks up the active image too.
     * Runs for a marker with its own active image ( per-store or per-category ),
     * or when a global active marker is configured.
     *
     * @since  3.0.0
     * @param  {number} storeId The store id of the hovered search result
     * @return {void}
     */
    setActiveOnHover: function( storeId ) {
        const mapIndex = this.currentMapIndex;

        if ( typeof this.active[mapIndex] === 'undefined' ) {
            return;
        }

        storeId = markerGroups.leadId( storeId, mapIndex );

        for ( let i = 0, len = this.active[mapIndex].length; i < len; i++ ) {
            if ( this.active[mapIndex][i].options.storeId === storeId ) {
                const marker       = this.active[mapIndex][i];
                const hasOwnActive = !! ( marker.options.locationMarkerUrlActive || marker.options.categoryMarkerUrlActive );

                if ( ! helpers.markers.maybeSetActivemarker( storeId, hasOwnActive ) ) {
                    return;
                }

                const markerData = hasOwnActive
                    ? {
                        locationMarkerUrlActive: marker.options.locationMarkerUrlActive,
                        categoryMarkerUrlActive: marker.options.categoryMarkerUrlActive,
                    }
                    : null;

                this.setActive( marker, markerData, mapIndex );
                break;
            }
        }
    },

    /**
     * Pan to a store's marker on one map, reveal it from a cluster and open
     * it like a click. Used by wpsl.api.focusLocation().
     *
     * A store whose popup is already open keeps it open, so wpslMarkerClicked
     * doesn't fire again for it.
     *
     * @since  3.1.1
     * @param  {number} storeId  The store ID.
     * @param  {number} mapIndex The map index.
     * @param  {object} options  options.zoom: zoom to at least this level.
     * @return {boolean} Whether the marker was found.
     */
    focus: function( storeId, mapIndex, options = {} ) {
        const leafletMap = slData.maps[ mapIndex ];
        const leadId     = markerGroups.leadId( storeId, mapIndex );
        const marker     = ( this.active[ mapIndex ] || [] ).find( ( item ) => item && item.options && item.options.storeId === leadId );

        if ( ! leafletMap || ! marker ) {
            return false;
        }

        let group = null;

        // The shared markers.layer belongs to the locator map, so look the group up on this map.
        leafletMap.eachLayer( ( layer ) => {
            if ( ! group && typeof L.MarkerClusterGroup !== 'undefined' && layer instanceof L.MarkerClusterGroup && layer.hasLayer( marker ) ) {
                group = layer;
            }
        } );

        // A newer focus on this map makes the callback of this one stale.
        const token = {};

        leafletMap._wpslFocusToken = token;

        const open = () => {
            if ( leafletMap._wpslFocusToken !== token ) {
                return;
            }

            // A shared popup that is already open moves to the location instead.
            // It stays where it is: centering its marker can push the popup out of view.
            if ( this.openOn( marker, storeId, mapIndex ) ) {
                return;
            }

            leafletMap.panTo( marker.getLatLng() );

            // bindPopup() toggles on click, so a click would close an open popup.
            if ( ! marker.isPopupOpen() ) {
                const container = leafletMap.getContainer();
                const active    = document.activeElement;

                // Closing the popup returns keyboard focus to the caller's element,
                // as it does for the store locator's result list.
                if ( active && active !== document.body && ! container.contains( active ) ) {
                    slData.markers.focusSourceElement = active;
                }

                marker.fire( 'click' );
            }
        };

        if ( typeof options.zoom === 'number' ) {
            // No animation, so the cluster reveal below starts from the new view.
            leafletMap.setView( marker.getLatLng(), options.zoom, { animate: false } );
        }

        if ( group ) {
            group.zoomToShowLayer( marker, open );
        } else {
            open();
        }

        return true;
    },

    /**
     * Close the open popup on one map and restore its active marker, as a
     * click on the map would. Used by wpsl.api.closePopups().
     *
     * @since  3.1.1
     * @param  {number} mapIndex The map index.
     * @return {boolean} Whether the map exists.
     */
    closePopups: function( mapIndex ) {
        const leafletMap = slData.maps[ mapIndex ];

        if ( ! leafletMap || typeof leafletMap.closePopup !== 'function' ) {
            return false;
        }

        leafletMap.closePopup();
        this.restoreActiveMarkers( mapIndex );

        return true;
    },

    /**
     * Show only the passed locations on a map, or all of them again, see
     * wpsl.api.showLocations(). Hidden markers leave the cluster layer too,
     * so the cluster counts match what's shown. The start marker stays.
     *
     * @since  3.1.1
     * @param  {array|null} ids      Location ids, null for all.
     * @param  {number}     mapIndex The map index.
     * @param  {object}     options  options.fitBounds: fit the map to the shown markers.
     * @return {boolean} Whether the map exists.
     */
    showLocations: function( ids, mapIndex, options = {} ) {
        const leafletMap = slData.maps[ mapIndex ];
        const list       = this.active[ mapIndex ];

        if ( ! leafletMap || typeof leafletMap.eachLayer !== 'function' || ! Array.isArray( list ) ) {
            return false;
        }

        // This map's cluster layer, if it clusters; this.layer is the last map's.
        let clusters = null;

        leafletMap.eachLayer( function( layer ) {
            if ( ! clusters && layer && typeof layer.getVisibleParent === 'function' ) {
                clusters = layer;
            }
        });

        const shown  = [];
        const hidden = [];

        list.forEach( function( marker ) {
            if ( ! marker || ! marker.options || ! marker.options.storeId ) {
                return;
            }

            ( ! ids || markerGroups.showsAny( marker.options.storeId, ids, mapIndex ) ? shown : hidden ).push( marker );
        });

        leafletMap.closePopup();

        markerGroups.setShown( ids, mapIndex );
        shown.forEach( ( marker ) => this.applyShownGroup( marker, mapIndex ) );

        if ( clusters ) {
            clusters.removeLayers( hidden.filter( ( marker ) => clusters.hasLayer( marker ) ) );
            clusters.addLayers( shown.filter( ( marker ) => ! clusters.hasLayer( marker ) ) );
        } else {
            hidden.forEach( ( marker ) => marker.remove() );
            shown.forEach( ( marker ) => {
                if ( ! leafletMap.hasLayer( marker ) ) {
                    marker.addTo( leafletMap );
                }
            });
        }

        if ( options.fitBounds && shown.length ) {
            this.fitBounds( shown.map( ( marker ) => marker.getLatLng() ), leafletMap );
        }

        return true;
    },

    /**
     * Point a shared marker at the locations shown now, see
     * wpsl.api.showLocations(): its title, popup and cluster count leave the
     * hidden ones out.
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

        // Leaflet reads the title when it draws the marker, an element on the map gets it now.
        const title = group.length > 1 ? groupWindow.markerTitle( group ) : helpers.decodeHtmlEntity( group[0].store );
        const el    = marker.getElement ? marker.getElement() : null;

        marker.options.title = title;
        marker.options.alt   = title;

        if ( el ) {
            el.setAttribute( 'title', title );
            el.setAttribute( 'alt', title );
        }
    },

    /**
     * Trigger a click event on marker that matches with the passed ID.
     *
     * @since 3.0.0
     * @param {number} storeId ID of the marker that's we need to trigger the click event for
     */
    triggerClick: function( storeId ) {
        const mapIndex = this.currentMapIndex;

        if ( typeof this.active[mapIndex] === 'undefined' ) {
            return;
        }

        const leadId = markerGroups.leadId( storeId, mapIndex );
        const len    = this.active[mapIndex].length;

        for ( let i = 0; i < len; i++ ) {
            const marker = this.active[mapIndex][i];
            if ( marker.options.storeId === leadId ) {
                helpers.map.setCenter( marker.getLatLng() );

                // A shared popup that is already open moves to the location instead.
                if ( ! this.openOn( marker, storeId, mapIndex ) ) {
                    marker.fire( 'click' );

                    // Inside a cluster the marker isn't on the map and no popup opens,
                    // so drop the location openOn() set for it, or a later click on
                    // the marker itself would open on that location.
                    if ( ! marker.isPopupOpen() ) {
                        marker._wpslGroupStart = null;
                    }
                }

                break;
            }
        }
    },

    /**
     * Make a shared marker's popup open on this location: right away when it
     * is already open, otherwise on the next click.
     *
     * @since  3.1.1
     * @param  {object} marker   The marker
     * @param  {number} storeId  The location id
     * @param  {number} mapIndex The map index
     * @return {boolean} Whether the open popup moved to the location, so no click is needed
     */
    openOn: function( marker, storeId, mapIndex ) {
        if ( ! marker || ! marker._wpslGroup || marker._wpslGroup.length < 2 ) {
            return false;
        }

        const index = markerGroups.shownIndexOf( storeId, mapIndex );
        const popup = marker.isPopupOpen() ? marker.getPopup().getElement() : null;
        const root  = popup ? popup.querySelector( '.wpsl-group-window' ) : null;

        if ( root ) {
            groupWindow.go( root, index );

            return true;
        }

        marker._wpslGroupStart = index;

        return false;
    }
};