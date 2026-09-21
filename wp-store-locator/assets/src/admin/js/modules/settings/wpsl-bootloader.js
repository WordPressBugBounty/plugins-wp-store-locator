import { state } from '../wpsl-shared.js';
import { createApiRequest } from '../../../../common/wpsl-core.js';

/**
 * Manage the dynamic loading of JS libraries from different map providers.
 *
 * @since 3.0.0
 * @see https://developers.google.com/maps/documentation/javascript/load-maps-js-api#dynamic-library-import
 * @returns {void}
 */
export const bootloader = {
    /**
     * Dynamically load the Google Maps JavaScript API.
     *
     * @since   3.0.0
     * @param   {Object}   apiKeys  Object containing API keys for different map services
     * @param   {Function} callback Function to call after the API is loaded
     * @returns {void}
     */
    add: function( apiKeys, callback ) {
        if ( state.mapService === 'gmaps' ) {
            // Drop the existing bootloader scripts, so validating the API
            // keys a second time reloads with the current browser key.
            const existingScripts = document.querySelectorAll( '#wpsl-bootloader, #wpsl-admin-js-after' );
            if ( existingScripts.length ) {
                existingScripts.forEach( script => script.remove() );

                // Also remove the actual Maps API script loaded by the previous bootloader
                document.querySelectorAll( 'script[src*="maps.googleapis.com"]' ).forEach( script => script.remove() );

                // Clean up the entire google object to prevent connectForExplicitThirdPartyLoad errors
                // when the Maps API is reloaded on the same page.
                if ( typeof google !== 'undefined' ) {
                    delete window.google;
                }
            }

            const script = document.createElement( 'script' );
            script.id = 'wpsl-bootloader';

            /**
             * Google's bootstrap loader, with one WPSL change: the "already
             * loaded" branch also sets window.wpslGmapsConflict so the conflict
             * notice can be shown.
             *
             * Keep this in sync with the copies in wpsl_gmaps_bootstrap()
             * ( includes/core/functions/functions-maps.php ) and wpsl-gdpr.js.
             */
            script.text = '(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?(window.wpslGmapsConflict=1,console.warn(p+" only loads once. Ignoring:",g)):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({ key: "' + apiKeys['gmaps_browser_key'] + '", v: "quarterly" });';
            
            document.body.appendChild( script );

            try {
                createApiRequest.gmaps.importRequiredLibraries( '', callback );
            } catch ( error ) {
                console.error( 'Error loading Google Maps libraries:', error );
            }
        } else {
            callback();
        }
    },
};