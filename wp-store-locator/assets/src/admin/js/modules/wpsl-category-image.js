/**
 * Marker dropdown functionality for category markers.
 *
 * @since  3.0.0
 */
export const categoryMarkers = {
    /**
     * Copy a dropdown item's artwork into the button that opens it.
     *
     * Uses .attr() rather than markup: src is a data URI for Marker Studio
     * markers and alt is the marker's name (free text). The style carries a
     * custom marker's height cap (see wpsl_marker_preview_cap()), so its
     * silhouette matches the bundled markers.
     *
     * @since   3.0.0
     * @param   {jQuery} $wrapper The dropdown wrapper.
     * @param   {jQuery} $item    The item whose artwork to show.
     * @returns {void}
     */
    showSelected: function( $wrapper, $item ) {
        const $art = $item.find( 'img' );
        const cap  = $art.attr( 'style' );
        const $copy = jQuery( '<img>' ).attr( 'src', $art.attr( 'src' ) ).attr( 'alt', $art.attr( 'alt' ) || '' );

        if ( cap ) {
            $copy.attr( 'style', cap );
        }

        $wrapper.find( '.wpsl-marker-dropdown-selected' ).empty().append( $copy );
    },

    /**
     * Initialize the marker dropdown handler.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        this.bindEvents();
        this.initializeSelectedMarkers();
        this.setupEnableToggle();
    },

    /**
     * Bind event handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bindEvents: function() {
        const self = this;

        jQuery( document ).on( 'click', '.wpsl-marker-dropdown-button', function( e ) {
            e.preventDefault();
            e.stopPropagation();
            self.toggleMarkerDropdown( jQuery( this ) );
        });

        jQuery( document ).on( 'click', '.wpsl-marker-dropdown-item', function( e ) {
            e.preventDefault();
            e.stopPropagation();
            self.selectMarker( jQuery( this ) );
        });

        jQuery( document ).on( 'click', function( e ) {
            if ( ! jQuery( e.target ).closest( '.wpsl-marker-dropdown-wrapper' ).length ) {
                jQuery( '.wpsl-marker-dropdown-menu' ).removeClass( 'show' );
            }
        });
        
        // Close when clicking inside the wrapper but outside button/menu.
        jQuery( document ).on( 'click', '.wpsl-marker-dropdown-wrapper', function( e ) {
            if ( jQuery( e.target ).hasClass( 'wpsl-marker-dropdown-wrapper' ) ) {
                jQuery( '.wpsl-marker-dropdown-menu' ).removeClass( 'show' );
            }
        });

        jQuery( document ).on( 'keydown', function( e ) {
            if ( e.key === 'Escape' ) {
                jQuery( '.wpsl-marker-dropdown-menu' ).removeClass( 'show' );
            }
        });

        // Reset marker dropdowns after form submission.
        jQuery( '#submit' ).on( 'click', function() {
            setTimeout( function() {
                self.resetForm();
            }, 1000 );
        });
    },

    /**
     * Initialize marker dropdowns to show selected markers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    initializeSelectedMarkers: function() {
        const $wrappers = jQuery( '.wpsl-marker-dropdown-wrapper' );
        
        for ( let i = 0; i < $wrappers.length; i++ ) {
            const $wrapper = jQuery( $wrappers[i] );
            const selectedMarker = $wrapper.find( 'input[type="hidden"]' ).val();
            
            if ( selectedMarker ) {
                const $selectedItem = $wrapper.find( '.wpsl-marker-dropdown-item[data-marker="' + selectedMarker + '"]' );
                
                if ( $selectedItem.length ) {
                    this.showSelected( $wrapper, $selectedItem.addClass( 'selected' ) );
                }
            }
        }
    },

    /**
     * Convert the enable checkbox to a slider and toggle marker dropdown fields.
     *
     * @since   3.0.0
     * @returns {void}
     */
    setupEnableToggle: function() {
        const $checkbox = jQuery( '#wpsl-category-markers-enabled' );
        if ( ! $checkbox.length ) {
            return;
        }

        if ( window.wpslSharedFuncs && typeof window.wpslSharedFuncs.createToggleSliders === 'function' ) {
            window.wpslSharedFuncs.createToggleSliders( $checkbox );
        } else {
            // No slider is coming; restore the checkbox since an invisible one
            // is worse than a plain one.
            $checkbox.removeClass( 'wpsl-toggle-pending' );
        }

        const toggleFields = function() {
            jQuery( '.wpsl-category-marker-field' ).toggleClass( 'wpsl-hidden', ! $checkbox.prop( 'checked' ) );
        };

        $checkbox.on( 'change', toggleFields );
        toggleFields();
    },

    /**
     * Reset the form after successful submission.
     *
     * @since   3.0.0
     * @returns {void}
     */
    resetForm: function() {
        
        // Only reset on the add new term form (not edit).
        if ( jQuery( '#tag-name' ).length ) {
            const $wrappers = jQuery( '.wpsl-marker-dropdown-wrapper' );
            
            for ( let i = 0; i < $wrappers.length; i++ ) {
                const $wrapper = jQuery( $wrappers[i] );
                const $input = $wrapper.find( 'input[type="hidden"]' );
                const $selected = $wrapper.find( '.wpsl-marker-dropdown-selected' );
                
                // Store the default marker on first load.
                if ( ! $wrapper.data( 'default-marker' ) ) {
                    $wrapper.data( 'default-marker', $input.val() );
                }
                
                // Restore the default marker from settings.
                const defaultMarker = $wrapper.data( 'default-marker' );
                if ( defaultMarker ) {
                    const $defaultMarkerItem = $wrapper.find( '.wpsl-marker-dropdown-item[data-marker="' + defaultMarker + '"]' );
                    if ( $defaultMarkerItem.length ) {
                        $input.val( defaultMarker );
                        this.showSelected( $wrapper, $defaultMarkerItem );

                        $wrapper.find( '.wpsl-marker-dropdown-item' ).removeClass( 'selected' );
                        $defaultMarkerItem.addClass( 'selected' );
                    }
                } else {
                    $input.val( '' );
                    $selected.html( '<span class="wpsl-marker-dropdown-placeholder">Select a marker...</span>' );
                    $wrapper.find( '.wpsl-marker-dropdown-item' ).removeClass( 'selected' );
                }
            }

            // Reset the enable toggle and hide marker fields.
            const $enable = jQuery( '#wpsl-category-markers-enabled' );

            if ( $enable.length ) {
                $enable.prop( 'checked', false ).trigger( 'change' );
            }
        }
    },

    /**
     * Toggle the marker dropdown menu.
     *
     * @param {jQuery} $button The dropdown button element
     */
    toggleMarkerDropdown: function( $button ) {
        const $wrapper = $button.closest( '.wpsl-marker-dropdown-wrapper' );
        const $menu = $wrapper.find( '.wpsl-marker-dropdown-menu' );
        
        jQuery( '.wpsl-marker-dropdown-menu' ).not( $menu ).removeClass( 'show' );
        
        $menu.toggleClass( 'show' );
    },

    /**
     * Select a marker from the dropdown.
     *
     * @param {jQuery} $item The clicked dropdown item
     */
    selectMarker: function( $item ) {
        const $wrapper = $item.closest( '.wpsl-marker-dropdown-wrapper' );

        // Stored value: a filename, or "custom:{id}" for a Studio marker.
        const markerValue = $item.attr( 'data-marker' );

        this.showSelected( $wrapper, $item );

        $wrapper.find( 'input[type="hidden"]' ).val( markerValue );

        $wrapper.find( '.wpsl-marker-dropdown-item' ).removeClass( 'selected' );
        $item.addClass( 'selected' );
        
        $wrapper.find( '.wpsl-marker-dropdown-menu' ).removeClass( 'show' );
    }
};