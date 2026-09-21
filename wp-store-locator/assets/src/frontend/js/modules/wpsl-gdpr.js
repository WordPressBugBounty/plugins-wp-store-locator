import { setup } from './wpsl-setup.js';

/**
 * GDPR checkpoint for different map providers.
 *
 * @since 3.0.0
 */
export const gdpr = {
    /**
     * Bind the GDPR checkpoint, unless the user already approved
     * loading the external scripts.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        const $wpslWrap = jQuery( '#wpsl-wrap' );

        // User has previously approved the loading of the map.
        if ( $wpslWrap.hasClass( 'wpsl-gdpr-passed' ) ) {
            return;
        }

        // No permission yet to load the map, so bind the event listener.
        jQuery( '#wpsl-gdpr-approved' ).on( 'click', function() {
            gdpr.approve( jQuery( '#wpsl-gdpr-permanent-approved' ) );
        });

        // The [wpsl_map] overlays are class-based, [wpsl_map] can exist
        // multiple times on one page. Approving any of them approves the
        // whole page - consent is per visitor, not per map.
        jQuery( '.wpsl-gdpr-approve' ).on( 'click', function() {
            gdpr.approve( jQuery( this ).closest( '.wpsl-gdpr-checkpoint-overlay' ).find( '.wpsl-gdpr-permanent' ) );
        });
    },

    /**
     * Handle a click on a checkpoint's approve button.
     *
     * @since   3.0.0
     * @param   {Object} $checkbox The clicked checkpoint's "don't ask again" checkbox
     * @returns {void}
     */
    approve: function( $checkbox ) {
        // Remember the user approving the map load.
        if ( $checkbox.is( ':checked' ) && typeof window.localStorage !== 'undefined' ) {
            localStorage.setItem( 'wpsl-gdpr-' + wpslSettings.api.provider, 1 );
        }

        gdpr.approved();
    },

    /**
     * Clear the checkpoint and run the setup after approval.
     *
     * @since   3.0.0
     * @returns {void}
     */
    approved: function() {
        jQuery( '#wpsl-gdpr-checkpoint' ).remove();

        // Clear the [wpsl_map] overlays and flip their containers to passed -
        // the same state the localStorage fast path applies to returning visitors.
        jQuery( '.wpsl-gdpr-checkpoint-overlay' ).remove();
        jQuery( 'div[id^="wpsl-base-"]' ).removeClass( 'wpsl-gdpr-checkpoint' ).addClass( 'wpsl-gdpr-passed' );

        /**
         * The provider scripts are deliberately not loaded here. setup.init()
         * reaches createApiRequest.maybeLoadLibraries(), which loads them for
         * the wpsl handler. Doing it here as well ran Google's bootstrap
         * twice, and the second run reported the store locator as a plugin
         * conflicting with itself ( window.wpslGmapsConflict ).
         */
        setup.loadProviderModule( function() {
            setup.revealSearch();

            setup.init();
        });
    },

    /**
     * Let Complianz decide when the map may load.
     *
     * @since  3.0.0
     * @return {Promise}
     */
    handleComplianz: async function() {
        const category = wp.hooks.applyFilters( 'wpslComplianzCategory', 'marketing' );

        // The button on Complianz' placeholder consents to the service, not
        // to the category: it reports the service on detail.service and
        // leaves detail.category unmatched. Read the name off the canvas so
        // it always matches whatever the integration registered.
        const canvas  = document.querySelector( '.cmplz-placeholder-element[data-service]' );
        const service = canvas ? canvas.getAttribute( 'data-service' ) : '';

        let loading = false;

        const loadMap = () => {
            if ( loading ) {
                return;
            }

            loading = true;

            setup.loadProviderModule( () => {
                gdpr.scripts.load( setup.getProvider(), () => {

                    // Release the search and the panel, held back while the map
                    // could not load. Mirrors gdpr.approved().
                    setup.revealSearch();

                    setup.init();
                });
            });
        };

        document.addEventListener( 'cmplz_enable_category', event => {
            const detail = event.detail || {};

            if ( detail.category === category || ( service && detail.service === service ) ) {
                loadMap();
            }
        });

        // Falls back to the category consent for a service the visitor never
        // answered for, so this covers both paths.
        if ( service && typeof cmplz_has_service_consent === 'function' ) {
            if ( cmplz_has_service_consent( service, category ) ) {
                loadMap();
            }
        } else if ( typeof cmplz_has_consent === 'function' && cmplz_has_consent( category ) ) {
            loadMap();
        }
    },

    scripts: {
        /**
         * See if the active map provider still needs its scripts loaded.
         * Only used when the GDPR checkpoint is enabled.
         *
         * @since 3.0.0
         * @param  {string } provider Name of the used map provider
         * @return {boolean}
         */
        requireLoad: function( provider ) {
            return ( provider == 'gmaps' && typeof google == 'undefined' ) || 
                   ( provider == 'mapbox' && typeof mapboxgl == 'undefined' );
        },

        /**
         * Load the asset chain PHP published as wpslSettings.api.assets.
         *
         * The scripts are appended one after the other because the list is in
         * dependency order ( e.g. the MapLibre bridge needs both Leaflet and
         * MapLibre to have run first ).
         *
         * @since   3.0.0
         * @param   {Function} callback Function to execute once everything loaded
         * @returns {void}
         */
        loadDeferredAssets: function( callback ) {
            const assets = wpslSettings.api.assets || { css: [], js: [] };

            /**
             * load() runs both from the approval click and again from
             * setup.init(), so skip anything already in the document.
             */
            const inDocument = function( selector, url ) {
                return document.querySelector( selector + '[' + ( selector === 'link' ? 'href' : 'src' ) + '="' + CSS.escape( url ) + '"]' ) !== null;
            };

            ( assets.css || [] ).forEach( function( asset ) {
                if ( inDocument( 'link', asset.url ) ) {
                    return;
                }

                const link = document.createElement( 'link' );

                link.setAttribute( 'rel', 'stylesheet' );
                link.setAttribute( 'href', asset.url );

                if ( asset.integrity ) {
                    link.setAttribute( 'integrity', asset.integrity );
                    link.setAttribute( 'crossorigin', 'anonymous' );
                }

                document.head.appendChild( link );
            } );

            const queue = ( assets.js || [] ).slice();

            ( function next() {
                const asset = queue.shift();

                if ( ! asset ) {
                    if ( callback ) {
                        callback();
                    }

                    return;
                }

                if ( inDocument( 'script', asset.url ) ) {
                    next();
                    return;
                }

                const s = document.createElement( 'script' );

                s.type = 'text/javascript';
                s.setAttribute( 'src', asset.url );

                if ( asset.integrity ) {
                    s.setAttribute( 'integrity', asset.integrity );
                    s.setAttribute( 'crossorigin', 'anonymous' );
                }

                s.onload = next;
                s.onerror = next;

                document.body.appendChild( s );
            } )();
        },

        /**
         * Fire any callbacks registered server-side via the wpsl_gmaps_callbacks
         * PHP filter. PHP publishes them as window.wpslGmapsCallbacks.
         *
         * @since   3.0.0
         * @returns {void}
         */
        fireCallbacks: function() {
            if ( ! window.wpslGmapsCallbacks || ! window.wpslGmapsCallbacks.length ) {
                return;
            }

            google.maps.importLibrary( 'places' ).then( () => {
                window.wpslGmapsCallbacks.forEach( fn => {
                    if ( typeof window[ fn ] === 'function' ) window[ fn ]();
                } );
            } );
        },

        /**
         * Dynamically loads the required external scripts and styles
         * for the specified map provider when GDPR is enabled.
         *
         * @since 3.0.0
         * @param  {string}   mapProvider The map provider ('gmaps', 'mapbox', or 'osm')
         * @param  {Function} callback    Function to execute after scripts are loaded
         * @return {void}
         */
        load: function( mapProvider, callback ) {
            let s = '';
    
            switch ( mapProvider ) {
                case 'mapbox':

                    /**
                     * PHP publishes the whole chain ( GL, plus the geocoder
                     * plugin when autocomplete is on ) as api.assets. Loading
                     * GL on its own leaves MapboxGeocoder undefined, and the
                     * throw that follows takes the rest of the setup with it.
                     * Pages cached before the update only carry api.v, so
                     * they keep the single-script path.
                     */
                    if ( wpslSettings.api.assets ) {
                        gdpr.scripts.loadDeferredAssets( callback );
                        break;
                    }

                    // Append the Mapbox CSS to the head
                    const link = document.createElement( 'link' );
    
                    link.setAttribute( 'rel', 'stylesheet' );
                    link.setAttribute( 'href', 'https://api.mapbox.com/mapbox-gl-js/v' + wpslSettings.api.v + '/mapbox-gl.css' );
    
                    document.head.appendChild( link );
    
                    // Append the Mapbox JS to the body
                    s = document.createElement( 'script' );
        
                    s.type = 'text/javascript';
                    s.setAttribute( 'src', 'https://api.mapbox.com/mapbox-gl-js/v' + wpslSettings.api.v + '/mapbox-gl.js' );
    
                    s.onload = () => {
                        if ( callback ) {
                            callback();
                        }
                    };

                    /**
                     * Without this a failed load ( bad version, CDN blocked )
                     * leaves the consent gate hanging silently: no map and
                     * nothing in the console pointing at the script.
                     */
                    s.onerror = () => {
                        console.error( 'WPSL: failed to load the Mapbox GL script from ' + s.src );

                        if ( callback ) {
                            callback();
                        }
                    };

                    document.body.appendChild( s );
                    break;
                case 'gmaps':

                    /**
                     * Google's bootstrap loader, with one WPSL change: the
                     * "already loaded" branch also sets window.wpslGmapsConflict
                     * so the conflict notice can be shown.
                     *
                     * Keep this in sync with the copy in wpsl_gmaps_bootstrap()
                     * ( includes/core/functions/functions-maps.php ), which is
                     * used when no GDPR handler is active.
                     */
                    (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?(window.wpslGmapsConflict=1,console.warn(p+" only loads once. Ignoring:",g)):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
                        key: wpslSettings.api.key,
                        v: wpslSettings.api.v,
                        language: wpslSettings.api.language
                    });
    
                    callback();
                    gdpr.scripts.fireCallbacks();
                    break;
                case 'osm':
                case 'stadia':
                    gdpr.scripts.loadDeferredAssets( callback );
                    break;
            }
        },
    },
};