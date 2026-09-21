/**
 * Formatting and data transformation helpers
 * 
 * @since 3.0.0
 */
export const formatting = {
    /**
     * Change dash separated values into camelCase.
     *
     * background-color is turned into backgroundColor
     *
     * @since  3.0.0
     * @param  {string} value
     * @return {string}
     */
    toCamelCase: function( value ) {
        if ( value.includes( '-' ) ) {
            return value.split( '-' ).map( ( word, index ) => {
                return index === 0 ? word : word.charAt( 0 ).toUpperCase() + word.slice( 1 );
            }).join( '' );
        }

        return value;
    },

    /**
     * Remove the indentation every line of a block shares.
     *
     * A snippet lifted out of a template carries the indentation it had
     * there, which pushes the preview away from the left edge. Only the
     * shared prefix goes, so the block's own nesting still reads as nesting.
     *
     * @since  3.0.0
     * @param  {string} block The text to dedent
     * @return {string} The block shifted to the left edge
     */
    dedent: function( block ) {
        const lines = block.split( '\n' );
        let shared = null;

        lines.forEach( function( line ) {
            if ( ! line.trim() ) {
                return;
            }

            const indent = line.match( /^[\t ]*/ )[0];

            if ( shared === null ) {
                shared = indent;

                return;
            }

            let i = 0;

            while ( i < shared.length && i < indent.length && shared[i] === indent[i] ) {
                i++;
            }

            shared = shared.slice( 0, i );
        });

        if ( ! shared ) {
            return block;
        }

        return lines.map( function( line ) {
            return line.startsWith( shared ) ? line.slice( shared.length ) : line;
        }).join( '\n' );
    },

    /**
     * Make sure the JSON is valid.
     *
     * @link   http://stackoverflow.com/a/20392392/1065294
     * @since  2.0.0
     * @param  {string} jsonString The JSON data
     * @return {object|boolean} The JSON string or false if it's invalid json.
     */
    tryParseJSON: function( jsonString ) {
        try {
            const o = JSON.parse( jsonString );

            /*
            * Handle non-exception-throwing cases:
            * Neither JSON.parse(false) or JSON.parse(1234) throw errors, hence the type-checking,
            * but... JSON.parse(null) returns 'null', and typeof null === "object",
            * so we must check for that, too.
            */
            if ( o && typeof o === 'object' ) {
                return o;
            }
        }
        catch ( e ) { }

        return false;
    },

    /**
     * Language-sensitive list formatting.
     *
     * @since  3.0.0
     * @see    https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/ListFormat
     * @param  {object} args
     * @return {Intl.ListFormat}
     */
    listFormatter: function( args ) {
        return new Intl.ListFormat( wpslSettings.language, args );
    },
};