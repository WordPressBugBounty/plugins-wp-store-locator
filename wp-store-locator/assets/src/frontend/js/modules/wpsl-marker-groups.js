import { slData, config } from './wpsl-shared.js';

/**
 * Locations at the same coordinates share one marker.
 *
 * Built once per result set, before the markers are created. The first
 * location of a group in the response order is its lead: its marker stands
 * in for the whole group, and its id is what the marker carries. The other
 * locations get no marker of their own.
 *
 * Every lookup that goes from a store id to a marker runs the id through
 * leadId() first, so a hover or click on any location in the results list
 * finds the shared marker.
 *
 * @since 3.1.1
 */
export const markerGroups = {

    /**
     * Whether locations at the same coordinates share a marker.
     *
     * @since  3.1.1
     * @return {boolean}
     */
    enabled: function() {
        return !! ( config.markers && config.markers.groupColocated );
    },

    /**
     * The number of decimals the coordinates have to share.
     *
     * @since  3.1.1
     * @return {number}
     */
    precision: function() {
        const precision = parseInt( config.markers && config.markers.groupPrecision, 10 );

        return ( precision >= 3 && precision <= 7 ) ? precision : 5;
    },

    /**
     * The key locations are grouped on, or null when the location can't be
     * grouped ( no coordinates ).
     *
     * @since  3.1.1
     * @param  {object} store The location data, with lat and lng
     * @return {string|null}
     */
    key: function( store ) {
        const lat = parseFloat( store.lat );
        const lng = parseFloat( store.lng );

        if ( ! isFinite( lat ) || ! isFinite( lng ) ) {
            return null;
        }

        const precision = this.precision();
        const key       = lat.toFixed( precision ) + ',' + lng.toFixed( precision );

        /**
         * Filter the key a location is grouped on. Locations with the same key
         * share a marker, so a coarser key groups locations that are close
         * together but not at the exact same spot.
         *
         * @since 3.1.1
         * @param {string} key   The rounded "lat,lng"
         * @param {object} store The location data
         */
        return wp.hooks.applyFilters( 'wpslMarkerGroupKey', key, store );
    },

    /**
     * Group the locations of a result set and remember the groups for the map.
     *
     * The start marker ( id 0 ), online-only locations and locations without
     * coordinates are never grouped. A key with one location is not a group.
     *
     * @since  3.1.1
     * @param  {array}  stores   The location data, in the response order
     * @param  {number} mapIndex The map the markers go on
     * @param  {object} map      The map object, passed on to the filter
     * @return {object|null} { groups: { leadId: [ store, … ] }, leadOf: { storeId: leadId } }, null when disabled
     */
    build: function( stores, mapIndex = 0, map = null ) {
        this.clear( mapIndex );

        if ( ! this.enabled() || ! Array.isArray( stores ) ) {
            return null;
        }

        const byKey = {};
        const order = [];

        stores.forEach( ( store ) => {
            if ( ! store || typeof store.id === 'undefined' || Number( store.id ) === 0 || typeof store.online !== 'undefined' ) {
                return;
            }

            const key = this.key( store );

            if ( key === null ) {
                return;
            }

            if ( ! byKey[ key ] ) {
                byKey[ key ] = [];
                order.push( key );
            }

            byKey[ key ].push( store );
        });

        let result = { groups: {}, leadOf: {} };

        order.forEach( ( key ) => {
            const members = byKey[ key ];

            if ( members.length < 2 ) {
                return;
            }

            const leadId = String( members[0].id );

            result.groups[ leadId ] = members;

            members.forEach( ( member ) => {
                result.leadOf[ String( member.id ) ] = leadId;
            });
        });

        /**
         * Filter the groups of a result set.
         *
         * @since 3.1.1
         * @param {object} result { groups: { leadId: [ store, … ] }, leadOf: { storeId: leadId } }
         * @param {array}  stores The location data of the result set
         * @param {object} map    The map object
         */
        result = wp.hooks.applyFilters( 'wpslMarkerGroups', result, stores, map );

        if ( ! result || typeof result.groups !== 'object' || typeof result.leadOf !== 'object' ) {
            return null;
        }

        slData.markerGroups[ mapIndex ] = result;

        return result;
    },

    /**
     * The groups of a map.
     *
     * @since  3.1.1
     * @param  {number} mapIndex The map index
     * @return {object|null} { groups, leadOf }, null without groups
     */
    get: function( mapIndex = 0 ) {
        return slData.markerGroups[ mapIndex ] || null;
    },

    /**
     * Forget the groups of a map.
     *
     * @since  3.1.1
     * @param  {number} mapIndex The map index
     * @return {void}
     */
    clear: function( mapIndex = 0 ) {
        delete slData.markerGroups[ mapIndex ];
    },

    /**
     * The group the location belongs to, as the members in display order.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  Any location in the group
     * @param  {number}        mapIndex The map index
     * @return {array|null} The members, or null when the location isn't grouped
     */
    members: function( storeId, mapIndex = 0 ) {
        const result = slData.markerGroups[ mapIndex ];

        if ( ! result ) {
            return null;
        }

        const leadId = result.leadOf[ String( storeId ) ];

        return ( leadId && result.groups[ leadId ] ) ? result.groups[ leadId ] : null;
    },

    /**
     * The id of the location whose marker shows the passed location.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The location id
     * @param  {number}        mapIndex The map index
     * @return {number|string} The lead's id, or the passed id when it isn't grouped
     */
    leadId: function( storeId, mapIndex = 0 ) {
        const members = this.members( storeId, mapIndex );

        return members ? members[0].id : storeId;
    },

    /**
     * Whether a marker shows any of the passed locations: its own location,
     * or one of the others at its address.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The location of the marker
     * @param  {array}         ids      Location ids
     * @param  {number}        mapIndex The map index
     * @return {boolean}
     */
    showsAny: function( storeId, ids, mapIndex = 0 ) {
        const wanted  = ids.map( String );
        const members = this.members( storeId, mapIndex ) || [ { id: storeId } ];

        return members.some( ( member ) => wanted.indexOf( String( member.id ) ) !== -1 );
    },

    /**
     * Whether the location is grouped but not the lead, so it has no marker.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The location id
     * @param  {number}        mapIndex The map index
     * @return {boolean}
     */
    isFollower: function( storeId, mapIndex = 0 ) {
        const members = this.members( storeId, mapIndex );

        return !! members && String( members[0].id ) !== String( storeId );
    },

    /**
     * The position of the location in its group.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The location id
     * @param  {number}        mapIndex The map index
     * @return {number} The index, 0 when the location isn't grouped
     */
    indexOf: function( storeId, mapIndex = 0 ) {
        const members = this.members( storeId, mapIndex ) || [];
        const index   = members.findIndex( ( member ) => String( member.id ) === String( storeId ) );

        return index > 0 ? index : 0;
    },

    /**
     * The data of one location in a group.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The location id
     * @param  {number}        mapIndex The map index
     * @return {object|null}
     */
    store: function( storeId, mapIndex = 0 ) {
        const members = this.members( storeId, mapIndex ) || [];

        return members.find( ( member ) => String( member.id ) === String( storeId ) ) || null;
    },

    /**
     * The number of locations a marker stands for.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The marker's location id
     * @param  {number}        mapIndex The map index
     * @return {number}
     */
    size: function( storeId, mapIndex = 0 ) {
        const members = this.members( storeId, mapIndex );

        return members ? members.length : 1;
    },

    /**
     * Remember which locations wpsl.api.showLocations() left on a map, so a
     * shared marker counts and lists only those. Kept with the groups, so the
     * next result set ( build() ) shows every location again.
     *
     * @since  3.1.1
     * @param  {array|null} ids      Location ids, null for all
     * @param  {number}     mapIndex The map index
     * @return {void}
     */
    setShown: function( ids, mapIndex = 0 ) {
        const result = slData.markerGroups[ mapIndex ];

        if ( result ) {
            result.shown = Array.isArray( ids ) ? ids.map( String ) : null;
        }
    },

    /**
     * The members of a group that are shown on the map: all of them, or
     * those wpsl.api.showLocations() kept, in the same order.
     *
     * @since  3.1.1
     * @param  {array}  members  The group's locations
     * @param  {number} mapIndex The map index
     * @return {array}
     */
    shownOf: function( members, mapIndex = 0 ) {
        const result = slData.markerGroups[ mapIndex ];
        const shown  = result && Array.isArray( result.shown ) ? result.shown : null;

        if ( ! Array.isArray( members ) || ! shown ) {
            return members;
        }

        return members.filter( ( member ) => shown.indexOf( String( member.id ) ) !== -1 );
    },

    /**
     * The position of the location among the shown members of its group,
     * where the info window opens on it.
     *
     * @since  3.1.1
     * @param  {number|string} storeId  The location id
     * @param  {number}        mapIndex The map index
     * @return {number} The index, 0 when the location isn't grouped or shown
     */
    shownIndexOf: function( storeId, mapIndex = 0 ) {
        const members = this.shownOf( this.members( storeId, mapIndex ) || [], mapIndex );
        const index   = members.findIndex( ( member ) => String( member.id ) === String( storeId ) );

        return index > 0 ? index : 0;
    }
};
