/**
 * Live preview for the search results preloader.
 *
 * While the preloader color controls are in use, the preview shows the
 * preloader where the frontend puts it: the flex ( vertical ) template swaps
 * the clear button inside the search input for it, the other templates
 * replace the result list rows with a single preloader row. The idle preview
 * returns shortly after the last interaction, so the icon and CTA previews
 * stay visible the rest of the time.
 *
 * @since 3.0.0
 */
export const preloaderPreview = {
    parent: null,

    // How long the preloader stays visible after the last interaction.
    restoreDelay: 2500,

    _timer: null,
    _svgText: null,

    /**
     * Initialize the preloader preview module.
     *
     * @since 3.0.0
     * @param {object} parentAppearance - Reference to main appearance object
     * @returns {void}
     */
    init( parentAppearance ) {
        this.parent = parentAppearance;
        this.bind();
    },

    /**
     * Bind the preloader control interactions.
     *
     * @since 3.0.0
     * @returns {void}
     */
    bind() {
        const self = this;

        jQuery( '#wpsl-preloader-color, #wpsl-preloader-custom-color' ).on( 'change', function() {
            self.show();
        } );

        /*
         * Opening the custom color picker previews its color right away. On
         * mousedown because the picker stops the click from bubbling, and the
         * open state is still pre-toggle there, so closed means about to open.
         */
        jQuery( document ).on( 'mousedown', '.wpsl-color-preview-btn', function() {
            const $field = jQuery( this ).closest( 'p' ).find( '#wpsl-preloader-custom-color' );
            if ( ! $field.length ) {
                return;
            }

            const picker = $field.data( 'wpsl-color-picker' );
            if ( picker && ! picker.state.open ) {
                self.show();
            }
        } );

        // Leaving the tab restores the idle preview immediately.
        jQuery( document ).on( 'click', '.wpsl-back-button, #wpsl-appearance-tabs a', function() {
            self.restore();
        } );
    },

    /**
     * Show the preloader in the preview and schedule the restore.
     *
     * @since 3.0.0
     * @returns {void}
     */
    show() {
        const self = this;

        this.getSrc( function( src ) {
            self.render( src );
            self.scheduleRestore();
        } );
    },

    /**
     * Resolve the preloader image src for the currently selected color mode.
     *
     * @since 3.0.0
     * @param {Function} callback - Receives the resolved image src
     * @returns {void}
     */
    getSrc( callback ) {
        const mode = jQuery( '#wpsl-preloader-color' ).val();
        const hex = ( jQuery( '#wpsl-preloader-custom-color' ).val() || '' ).trim();

        if ( mode === 'white' ) {
            callback( wpslSettings.url + 'assets/img/ajax-loader-white.svg' );
            return;
        }

        if ( mode !== 'custom' || ! /^#[0-9a-fA-F]{6}$/.test( hex ) ) {
            callback( wpslSettings.url + 'assets/img/ajax-loader.svg' );
            return;
        }

        this.loadSvg( function( svg ) {
            if ( svg ) {
                callback( 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( svg.replace( /#000000/g, hex ) ) );
            } else {
                callback( wpslSettings.url + 'assets/img/ajax-loader.svg' );
            }
        } );
    },

    /**
     * Fetch and cache the preloader SVG markup used for custom colors.
     *
     * @since 3.0.0
     * @param {Function} callback - Receives the SVG markup, or '' on failure
     * @returns {void}
     */
    loadSvg( callback ) {
        const self = this;

        if ( this._svgText !== null ) {
            callback( this._svgText );
            return;
        }

        jQuery.ajax( {
            url: wpslSettings.url + 'assets/img/ajax-loader.svg',
            dataType: 'text'
        } ).done( function( text ) {
            self._svgText = text;
            callback( text );
        } ).fail( function() {
            callback( '' );
        } );
    },

    /**
     * Render the preloader in the preview, or update its 
     * color if it is already showing.
     *
     * @since 3.0.0
     * @param {string} src - The preloader image src
     * @returns {void}
     */
    render( src ) {
        const $preview = jQuery( '#wpsl-appearance-preview' );
        const label = wpslL10n.searching;

        let $img = $preview.find( 'img.wpsl-preloader, li.wpsl-preloader-row img' );
        if ( ! $img.length ) {
            if ( $preview.find( '#wpsl-wrap' ).hasClass( 'wpsl-flex' ) ) {
                const $clearWrapper = $preview.find( '#wpsl-clear-wrapper' );

                if ( ! $clearWrapper.length ) {
                    return;
                }

                // The preview forces the clear button to display: flex
                // !important, so hiding needs the class, not .hide().
                $clearWrapper.find( '#wpsl-clear-search-input' ).addClass( 'wpsl-preloader-hidden' );
                $img = jQuery( '<img class="wpsl-preloader" />' ).appendTo( $clearWrapper );
            } else {
                const $list = $preview.find( '#wpsl-stores ul' );

                if ( ! $list.length ) {
                    return;
                }

                // Keep the list at its idle height so the layout doesn't
                // collapse to a single row while the preloader shows.
                $list.css( 'min-height', $list.outerHeight() + 'px' );

                $list.children( 'li' ).addClass( 'wpsl-preloader-hidden' );

                /*
                 * Not the frontend's wpsl-preloader class: inside
                 * #wpsl-content-wrap that class is the admin AJAX spinner,
                 * which shared.css clamps to a 16px square.
                 */
                const $row = jQuery( '<li class="wpsl-preloader-row"></li>' ).appendTo( $list );

                $img = jQuery( '<img />' ).appendTo( $row );
                $row.append( document.createTextNode( label ) );
            }

            $img.attr( 'alt', label );
        }

        $img.attr( 'src', src );
    },

    /**
     * Restore the idle preview once the user stops interacting with the
     * preloader controls. Re-arms itself while the color picker is open.
     *
     * @since 3.0.0
     * @returns {void}
     */
    scheduleRestore() {
        const self = this;

        clearTimeout( this._timer );

        this._timer = setTimeout( function() {
            const picker = jQuery( '#wpsl-preloader-custom-color' ).data( 'wpsl-color-picker' );

            if ( picker && picker.state.open ) {
                self.scheduleRestore();
                return;
            }

            self.restore();
        }, this.restoreDelay );
    },

    /**
     * Put the preview back in its idle state.
     *
     * @since 3.0.0
     * @returns {void}
     */
    restore() {
        clearTimeout( this._timer );
        this._timer = null;

        const $preview = jQuery( '#wpsl-appearance-preview' );

        $preview.find( 'img.wpsl-preloader, li.wpsl-preloader-row' ).remove();
        $preview.find( '#wpsl-clear-search-input, #wpsl-stores li' ).removeClass( 'wpsl-preloader-hidden' );
        $preview.find( '#wpsl-stores ul' ).css( 'min-height', '' );
    }
};