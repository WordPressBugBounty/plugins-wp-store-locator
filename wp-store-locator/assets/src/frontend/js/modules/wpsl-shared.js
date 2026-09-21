/**
 * Shared state and configuration for WPSL frontend
 * 
 * @since 3.0.0
 */
export const slData = {
    mapsInTabs: [],
    maps: [],
    useBasicMode: false,
    skipReverseGeocode: false,
    delayedSearchInProgress: false,
    autoCompleteLatLng: '',
    viewport: {},
    geolocation: {
        active: false,
        position: {},
        newRequest: false
    },
    directions: {
        active: false,
    },
    directionsPolylines: [],

    // Track street view visibility (gmaps only)
    streetView: {
        active: false,
    },

    // Track dynamically loaded Google Maps libraries
    libraries: {},

    // Shared marker state (provider-specific properties added at runtime)
    markers: {
        active: {},        // Active markers per map (all providers)
        cluster: {},       // Cluster instances (all providers)
        current: null,     // Currently active marker (gmaps, osm)
        startLocation: '',  // Start location marker (gmaps, osm)
        focusSourceElement: null,  // Element that opened the infowindow (marker or search result)
        markerType: null,  // Cached marker type: 'legacy' for JSON, 'advanced' for cloud-based/default
        keyboardTriggered: false  // Whether the info window was opened via keyboard
    }
};

/**
 * Static configuration settings from wpslSettings (set once during initialization)
 */
export const config = {};