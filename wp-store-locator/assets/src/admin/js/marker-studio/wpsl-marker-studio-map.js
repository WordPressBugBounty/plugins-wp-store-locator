/**
 * Marker Studio: live map preview in the sidebar.
 *
 * @since 3.0.0
 */
( function( $ ) {

    let map       = null;
    let marker    = null;
    let currentIcon = null;
    let provider  = 'osm';
    let lat       = 37.422;
    let lng       = -122.084;
    let zoom      = 6;

    function initMap() {
        const $map = $( '#wpsl-ms-preview-map' );

        if ( ! $map.length ) {
            return;
        }

        if ( typeof wpslSettings === 'undefined' ) {
            return;
        }

        provider = wpslSettings.api ? wpslSettings.api.provider : 'osm';
        const latLng = String( wpslSettings.defaultLatLng || '37.4220, -122.0840' ).split( ',' );
        lat  = parseFloat( latLng[0] ) || 37.422;
        lng  = parseFloat( latLng[1] ) || -122.084;
        zoom = parseInt( wpslSettings.defaultZoom, 10 ) || 6;

        // Without a valid key (Google Maps, Mapbox, Stadia) the preview map
        // cannot load properly. Show the same key-required box the other
        // admin maps do instead. Same missing-or-flagged-invalid rule as
        // hasValidApiKey().
        if ( 'osm' !== provider ) {
            const api = wpslSettings.api || {};

            if ( ! api.key || api.hasValidKey === false ) {
                const msgs = ( window.wpslL10n && wpslL10n.apiKeyMissingProvider ) || {};
                const msg  = msgs[ provider ] || ( window.wpslL10n && wpslL10n.apiKeyMissing ) || '';

                $map.addClass( 'wpsl-key-required' ).html( '<p>' + msg + '</p>' );

                return;
            }
        }

        if ( 'gmaps' === provider ) {
            initGmaps();
        } else if ( 'mapbox' === provider ) {
            initMapbox();
        } else {
            initLeaflet();
        }
    }

    /**
     * Leaflet-based map for OSM, Stadia, and Mapbox raster tiles.
     */
    function initLeaflet() {
        if ( typeof L === 'undefined' ) {
            return;
        }

        const $map = $( '#wpsl-ms-preview-map' );

        if ( ! $map.length ) {
            return;
        }

        const container = $map[0];

        // Clear Leaflet's init stamp so re-initializing the container (e.g.
        // after a DOM swap) doesn't throw "Map container is already initialized".
        if ( container._leaflet_id ) {
            delete container._leaflet_id;
        }

        map = L.map( container, {
            center: [ lat, lng ],
            zoom: zoom,
            zoomControl: true,
            attributionControl: true
        } );

        const tileLayerConfig = ( wpslSettings.api && wpslSettings.api.tileLayer ) || DEFAULT_RASTER;

        // Vector configs (OpenFreeMap) carry a style URL, not a raster
        // template, and need MapLibre GL.
        if ( ! addVectorLayer( tileLayerConfig ) ) {
            addRasterLayer( tileLayerConfig );
        }

        addLeafletMarker();

        // The map is initialised inside a flex container that may not have
        // its final dimensions yet; invalidateSize forces a recalculation.
        setTimeout( function() { map.invalidateSize(); }, 100 );
    }

    /**
     * Fallback raster tiles, and last resort when a vector style won't load.
     *
     * @since 3.0.0
     * @var   object
     */
    const DEFAULT_RASTER = {
        urlTemplate: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        options:     { attribution: '&copy; OpenStreetMap' }
    };

    /**
     * Whether this browser can render a vector style at all.
     *
     * @since  3.0.0
     * @return {boolean}
     */
    function webglSupported() {
        try {
            const canvas = document.createElement( 'canvas' );

            return !! ( window.WebGLRenderingContext && ( canvas.getContext( 'webgl' ) || canvas.getContext( 'experimental-webgl' ) ) );
        } catch ( e ) {
            return false;
        }
    }

    /**
     * Put a raster tile layer on the Leaflet map.
     *
     * @since  3.0.0
     * @param  {object} config A tile layer config, vector or raster.
     * @return void
     */
    function addRasterLayer( config ) {
        const raster = ( config && 'vector' === config.type ) ? ( config.fallback || DEFAULT_RASTER ) : ( config || DEFAULT_RASTER );
        L.tileLayer( raster.urlTemplate, raster.options ).addTo( map );
    }

    /**
     * Put a vector style on the Leaflet map through the MapLibre GL bridge.
     *
     * @since  3.0.0
     * @param  {object} config A tile layer config.
     * @return {boolean} Whether a vector layer was added.
     */
    function addVectorLayer( config ) {
        if ( ! config || 'vector' !== config.type || ! config.style || typeof L.maplibreGL === 'undefined' || ! webglSupported() ) {
            return false;
        }

        const glLayer = L.maplibreGL( {
            style:       config.style,
            attribution: ( config.options || {} ).attribution
        } );
        glLayer.addTo( map );

        const glMap = glLayer.getMaplibreMap ? glLayer.getMaplibreMap() : null;
        if ( glMap ) {
            let fellBack = false;

            glMap.on( 'error', function() {
                if ( fellBack ) {
                    return;
                }

                fellBack = true;

                map.removeLayer( glLayer );
                addRasterLayer( config );
            } );
        }

        return true;
    }

    /**
     * Mapbox GL preview, on the style the site is configured with.
     */
    function initMapbox() {
        const $map = $( '#wpsl-ms-preview-map' );
        if ( ! $map.length ) {
            return;
        }

        const api = wpslSettings.api || {};

        // If Mapbox GL JS is not available, fall back to Leaflet
        if ( typeof mapboxgl === 'undefined' ) {
            provider = 'osm';

            initLeaflet();

            return;
        }

        mapboxgl.accessToken = api.key;

        map = new mapboxgl.Map( {
            container: $map[0],
            style:     api.style || 'mapbox://styles/mapbox/light-v11',
            center:    [ lng, lat ],
            zoom:      zoom
        } );

        map.addControl( new mapboxgl.NavigationControl(), 'top-right' );

        addMapboxMarker();

        setTimeout( function() {
            if ( map && map.resize ) {
                map.resize();
            }
        }, 100 );
    }

    /**
     * Google Maps preview.
     */
    function initGmaps() {
        const $map = $( '#wpsl-ms-preview-map' );
        if ( ! $map.length ) {
            return;
        }

        if ( typeof google === 'undefined' || typeof google.maps === 'undefined' ) {
            setTimeout( initGmaps, 200 );
            return;
        }

        if ( typeof google.maps.Map !== 'function' ) {
            if ( typeof google.maps.importLibrary === 'function' ) {
                google.maps.importLibrary( 'maps' ).then( function() {
                    initGmaps();
                } );
            } else {
                setTimeout( initGmaps, 200 );
            }
            return;
        }

        const mapOptions = {
            center: { lat: lat, lng: lng },
            zoom: zoom,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        };

        const mapStyle    = ( wpslSettings.mapStyle && wpslSettings.mapStyle.gmaps ) || {};
        let   parsedStyle = null;

        if ( mapStyle.json ) {
            try {
                parsedStyle = JSON.parse( mapStyle.json );
            } catch ( e ) {
                parsedStyle = null;
            }
        }

        if ( mapStyle.selected === 'json' && parsedStyle ) {
            mapOptions.styles = parsedStyle;
        } else if ( mapStyle.cloud_based && mapStyle.cloud_based.trim() ) {
            mapOptions.mapId = mapStyle.cloud_based;
        } else {
            mapOptions.mapId = 'DEMO_MAP_ID';
        }

        map = new google.maps.Map( $map[0], mapOptions );

        addGmapsMarker();
    }

    /**
     * The pixel box and anchor point a marker's own artwork asks for.
     *
     * @since 3.0.0
     * @param {string} dataUri
     * @return {object} { width, height, anchorX, anchorY, centered, offsetX, offsetY }
     */
    function getMarkerGeometry( dataUri ) {
        const fallback = { width: 33, height: 41, anchorX: 17, anchorY: 41, centered: false, offsetX: -0.5, offsetY: 0 };

        if ( typeof dataUri !== 'string' || ! dataUri ) {
            return fallback;
        }

        const cache = getMarkerGeometry._cache = getMarkerGeometry._cache || new Map();

        if ( cache.has( dataUri ) ) {
            return Object.assign( {}, cache.get( dataUri ) );
        }

        let markup;

        try {
            markup = decodeURIComponent( dataUri );
        } catch ( e ) {
            return fallback;
        }

        const size = markup.match( /<svg[^>]*\bwidth="(\d+(?:\.\d+)?)"[^>]*\bheight="(\d+(?:\.\d+)?)"/i );

        if ( ! size ) {
            return fallback;
        }

        const width    = parseFloat( size[1] );
        const height   = parseFloat( size[2] );
        const centered = /data-wpsl-anchor="center"/i.test( markup );

        const box = markup.match( /<svg[^>]*\bviewBox="(-?[\d.]+)[ ,]+(-?[\d.]+)[ ,]+(\d[\d.]*)[ ,]+(\d[\d.]*)"/i );

        let anchorX       = width / 2;
        const anchorUnits = markup.match( /\bdata-wpsl-anchor-x="(-?\d+(?:\.\d+)?)"/i );
        if ( anchorUnits && box && parseFloat( box[3] ) > 0 ) {
            anchorX = ( parseFloat( anchorUnits[1] ) - parseFloat( box[1] ) ) / parseFloat( box[3] ) * width;
        }

        let anchorY        = centered ? height / 2 : height;
        const anchorYUnits = markup.match( /\bdata-wpsl-anchor-y="(-?\d+(?:\.\d+)?)"/i );
        if ( ! centered && anchorYUnits && box && parseFloat( box[4] ) > 0 ) {
            anchorY = ( parseFloat( anchorYUnits[1] ) - parseFloat( box[2] ) ) / parseFloat( box[4] ) * height;
        }

        anchorX = Math.round( anchorX );
        anchorY = Math.round( anchorY );

        const geometry = {
            width:   width,
            height:  height,
            anchorX: anchorX,
            anchorY: anchorY,
            centered: centered,
            offsetX: width / 2 - anchorX,
            offsetY: height - anchorY
        };

        if ( cache.size > 50 ) {
            cache.clear();
        }

        cache.set( dataUri, geometry );

        return Object.assign( {}, geometry );
    }

    /**
     * The marker's artwork, inlined as a real <svg> element.
     *
     * @since  3.0.0
     * @param  {string} dataUri  The marker artwork.
     * @param  {object} geometry The box getMarkerGeometry() resolved.
     * @return {HTMLElement} A wrapper around the artwork.
     */
    function markerArtwork( dataUri, geometry ) {
        const wrapper = document.createElement( 'span' );
        wrapper.style.display    = 'block';
        wrapper.style.width      = geometry.width + 'px';
        wrapper.style.height     = geometry.height + 'px';
        wrapper.style.lineHeight = '0';

        let markup = '';

        try {
            markup = decodeURIComponent( dataUri.slice( dataUri.indexOf( ',' ) + 1 ) );
        } catch ( e ) {
            markup = '';
        }

        const parsed = markup
            ? new DOMParser().parseFromString( markup, 'image/svg+xml' ).documentElement
            : null;

        if ( parsed && 'http://www.w3.org/2000/svg' === parsed.namespaceURI ) {
            parsed.setAttribute( 'width', geometry.width );
            parsed.setAttribute( 'height', geometry.height );
            parsed.style.display = 'block';

            wrapper.appendChild( document.importNode( parsed, true ) );

            return wrapper;
        }

        const image = document.createElement( 'img' );

        image.src           = dataUri;
        image.alt           = '';
        image.style.display = 'block';
        image.style.width   = geometry.width + 'px';
        image.style.height  = geometry.height + 'px';

        wrapper.appendChild( image );

        return wrapper;
    }

    function addLeafletMarker() {
        if ( ! map || typeof L === 'undefined' ) {
            return;
        }

        let icon;

        if ( currentIcon ) {
            const geometry = getMarkerGeometry( currentIcon );
            icon = L.divIcon( {
                html: markerArtwork( currentIcon, geometry ),
                className: 'wpsl-ms-map-marker',
                iconSize: [ geometry.width, geometry.height ],
                iconAnchor: [ geometry.anchorX, geometry.anchorY ]
            } );
        } else {
            icon = L.divIcon( {
                html: '',
                className: 'wpsl-ms-map-marker'
            } );
        }

        marker = L.marker( [ lat, lng ], { icon: icon } ).addTo( map );
    }

    /**
     * The marker element a GL JS marker is built around.
     *
     * @since  3.0.0
     * @return {HTMLElement}
     */
    function mapboxMarkerElement() {
        const element = document.createElement( 'div' );
        element.className = 'wpsl-ms-map-marker';

        if ( ! currentIcon ) {
            return element;
        }

        element.appendChild( markerArtwork( currentIcon, getMarkerGeometry( currentIcon ) ) );

        return element;
    }

    function addMapboxMarker() {
        if ( ! map || typeof mapboxgl === 'undefined' ) {
            return;
        }

        const geometry = currentIcon ? getMarkerGeometry( currentIcon ) : null;
        const anchor   = ( geometry && geometry.centered ) ? 'center' : 'bottom';

        marker = new mapboxgl.Marker( {
            element: mapboxMarkerElement(),
            anchor:  anchor,
            offset:  [ geometry ? geometry.offsetX : 0, geometry ? geometry.offsetY : 0 ]
        } )
            .setLngLat( [ lng, lat ] )
            .addTo( map );
    }

    function addGmapsMarker() {
        if ( ! map ) {
            return;
        }

        let icon = null;

        if ( currentIcon ) {
            const geometry = getMarkerGeometry( currentIcon );

            icon = {
                url: currentIcon,
                scaledSize: new google.maps.Size( geometry.width, geometry.height ),
                anchor: new google.maps.Point( geometry.anchorX, geometry.anchorY )
            };
        }

        marker = new google.maps.Marker( {
            position: { lat: lat, lng: lng },
            map: map,
            icon: icon
        } );
    }

    /**
     * Replace the marker icon with a new data URI. Called on every editor
     * change and on save.
     */
    function setMarkerIcon( dataUri ) {
        currentIcon = dataUri;

        if ( ! map || ! marker ) {
            return;
        }

        const geometry = getMarkerGeometry( dataUri );

        if ( 'mapbox' === provider ) {
            if ( marker && marker.remove ) {
                marker.remove();
            }

            addMapboxMarker();

            return;
        }

        if ( 'gmaps' === provider ) {
            marker.setIcon( {
                url: dataUri,
                scaledSize: new google.maps.Size( geometry.width, geometry.height ),
                anchor: new google.maps.Point( geometry.anchorX, geometry.anchorY )
            } );
        } else {
            marker.setIcon( L.divIcon( {
                html: markerArtwork( dataUri, geometry ),
                className: 'wpsl-ms-map-marker',
                iconSize: [ geometry.width, geometry.height ],
                iconAnchor: [ geometry.anchorX, geometry.anchorY ]
            } ) );
        }
    }

    $( document ).on( 'wpsl-ms-editor-change', function( e, fields ) {
        const dataUri = buildDataUri( withInlinedLogo( fields ) );
        if ( dataUri ) {
            setMarkerIcon( dataUri );
        }
    } );

    $( document ).on( 'wpsl-ms-marker-saved', function( e, markerData, dataUri, size ) {
        if ( dataUri ) {
            setMarkerIcon( dataUri );
        }
    } );

    /**
     * Base64 copies of a marker's logo, keyed by its plain URL, so a color
     * drag redrawing every frame only fetches each logo once.
     */
    const logoDataUriCache = {};

    /**
     * Swap a plain logo_src URL for a base64 data URI before building the
     * marker, fetching and caching it on first sight.
     *
     * @since 3.0.0
     * @param {object} fields The editor's collectFields() shape.
     * @return {object} fields, with logo_src swapped to a data URI once the
     *                   fetch resolves (blank meanwhile, so the marker just
     *                   redraws without a logo until then).
     */
    function withInlinedLogo( fields ) {
        const src = fields.logo_src;
        if ( ! src || /^data:/i.test( src ) ) {
            return fields;
        }

        const cached = logoDataUriCache[ src ];
        if ( cached && 'pending' !== cached ) {
            return $.extend( {}, fields, { logo_src: cached } );
        }

        if ( 'pending' !== cached ) {
            logoDataUriCache[ src ] = 'pending';

            fetch( src )
                .then( function( response ) { return response.blob(); } )
                .then( function( blob ) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        logoDataUriCache[ src ] = reader.result;

                        // Redraw now that the logo is available.
                        const dataUri = buildDataUri( withInlinedLogo( fields ) );
                        if ( dataUri ) {
                            setMarkerIcon( dataUri );
                        }
                    };

                    reader.readAsDataURL( blob );
                } )
                .catch( function() {
                    delete logoDataUriCache[ src ];
                } );
        }

        return $.extend( {}, fields, { logo_src: '' } );
    }

    /**
     * Build a data URI from the editor fields using the SVG builder, the same
     * way wpsl-marker-studio-panes.js does for the preview art. The SVG builder
     * is published as wpslMarkerStudioSvg on window by wpsl-marker-studio-svg.js.
     */
    function buildDataUri( fields ) {
        if ( typeof wpslMarkerStudioSvg === 'undefined' || typeof wpslMarkerStudio === 'undefined' ) {
            return '';
        }

        const svgApi = wpslMarkerStudioSvg;
        const tables = {
            shapes:          wpslMarkerStudio.shapes || {},
            shapeBodies:     wpslMarkerStudio.shapeBodies || {},
            shapeBounds:     wpslMarkerStudio.shapeBounds || {},
            iconScale:       wpslMarkerStudio.iconScale || {},
            icons:           wpslMarkerStudio.icons || {},
            centerShapes:    wpslMarkerStudio.centerShapes || {},
            centerDotRadius: wpslMarkerStudio.centerDotRadius || {},
            holeShapes:      wpslMarkerStudio.holeShapes || {},
            labelShapes:     wpslMarkerStudio.labelShapes || [],
            textScale:       wpslMarkerStudio.textScale || {}
        };

        const markup = svgApi.buildMarkerSvg( fields, tables );
        if ( ! markup ) {
            return '';
        }

        return svgApi.toDataUri( markup );
    }

    $( initMap );

} )( jQuery );