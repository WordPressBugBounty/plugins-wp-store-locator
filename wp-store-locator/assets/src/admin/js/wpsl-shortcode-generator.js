/**
 * Turn the shortcode dialog's values into a [wpsl] shortcode.
 *
 * @since 2.2.10
 */
function WPSL_InsertShortcode() {
    const win = window.dialogArguments || opener || parent || top;
    const startLocation  = jQuery( "#wpsl-start-location" ).val();
    const catFilterType  = jQuery( "#wpsl-cat-filter-types" ).val();
    const catRestriction = jQuery( "#wpsl-cat-restriction" ).val();
    const locateUser     = ( jQuery( "#wpsl-auto-locate" ).is( ":checked" ) ) ? true : false;

    let shortcodeAtts, checkboxColumns, catSelectionID, catSelection;

    shortcodeAtts = 'template="' + jQuery( "#wpsl-store-template" ).val() + '" map_type="' + jQuery( "#wpsl-map-type" ).val() + '" auto_locate="' + locateUser + '"';

    const markers = WPSL_Selected_Markers();

    if ( typeof markers.start !== "undefined" ) {
        shortcodeAtts += ' start_marker="' + markers.start + '"';
    }

    if ( typeof markers.store !== "undefined" ) {
        shortcodeAtts += ' store_marker="' + markers.store + '"';
    }

    if ( typeof markers.active !== "undefined" ) {
        shortcodeAtts += ' active_marker="' + markers.active + '"';
    }

    if ( startLocation ) {
        shortcodeAtts += ' start_location="' + startLocation + '"';
    }

    /*
     * An empty option is left out, so the inserted map keeps following the
     * settings page for anything the user did not deliberately set here.
     */
    jQuery.each( {
        country:         "#wpsl-restrict-country",
        state:           "#wpsl-restrict-state",
        city:            "#wpsl-restrict-city",
        distance_unit:   "#wpsl-distance-unit",
        marker_clusters: "#wpsl-marker-clusters"
    }, function( att, selector ) {
        const value = jQuery( selector ).val();

        if ( value ) {
            shortcodeAtts += ' ' + att + '="' + value + '"';
        }
    });

    if ( typeof catRestriction !== "undefined" && catRestriction !== null && !catFilterType ) {
        shortcodeAtts += ' category="' + catRestriction + '"';
    }

    if ( catFilterType === "dropdown" ) {
        catSelectionID = "wpsl-cat-selection";
    } else {
        catSelectionID = "wpsl-checkbox-selection";
    }

    catSelection = jQuery( '#' + catSelectionID + '' ).val();
    if ( catSelection ) {
        shortcodeAtts += ' category_selection="' + catSelection + '"';
    }

    if ( catFilterType ) {
        shortcodeAtts += ' category_filter_type="' + catFilterType + '"';
    }

    if ( catFilterType === "checkboxes" ) {
        checkboxColumns = parseInt( jQuery( "#wpsl-checkbox-columns" ).val() );

        if ( ! isNaN( checkboxColumns ) ) {
            shortcodeAtts += ' checkbox_columns="' + checkboxColumns + '"';
        }
    }

    win.send_to_editor("[wpsl " + shortcodeAtts + "]");

    jQuery( "#wpsl-shortcode-dialog" ).dialog( "close" );
}

/**
 * Collect the markers picked in the start / store / active dropdowns.
 *
 * Every dropdown starts on the Default ( from settings ) item, whose empty
 * value is skipped: writing out today's setting would pin the map to it.
 * Only an explicit pick becomes an attribute.
 */
function WPSL_Selected_Markers() {
    const selectedMarkers = {};

    jQuery( ".wpsl-lm-dropdown" ).each( function() {
        const type  = jQuery( this ).attr( "data-marker-type" );
        const value = jQuery( this ).find( "input[type=hidden]" ).val();

        if ( ! type || ! value ) {
            return;
        }

        // A bundled marker att is stored without its file extension; a custom
        // marker's "custom:{id}" value has none and passes through untouched.
        selectedMarkers[ type ] = value.replace( /\.(svg|png)$/i, "" );
    });

    return selectedMarkers;
}

jQuery( document ).ready( function( $ ) {
    const $dialog = $( "#wpsl-shortcode-dialog" );

    if ( ! $dialog.length ) {
        return;
    }

    // Targets are passed explicitly: the dialog has no #wpsl-settings-form for
    // createToggleSliders()' default selector to find.
    wpslSharedFuncs.createToggleSliders( $dialog.find( "input[type=checkbox]" ) );
    wpslSharedFuncs.bindInfoPopup( $dialog.find( ".wpsl-info" ) );
    wpslSharedFuncs.initMarkerDropdowns();

    $( "#wpsl-cat-filter-types" ).on( "change", function() {
        const filterType = $( this ).val();

        // wpsl-hide rather than show()/hide(): jQuery's show() restores
        // display:block, undoing the stylesheet's flex layout on these rows.
        if ( filterType === 'dropdown' ) {
            $( ".wpsl-cat-selection" ).removeClass( "wpsl-hide" );
            $( ".wpsl-checkbox-options, .wpsl-cat-restriction, .wpsl-checkbox-selection" ).addClass( "wpsl-hide" );
        } else if ( filterType === 'checkboxes' ) {
            $( ".wpsl-checkbox-options, .wpsl-checkbox-selection" ).removeClass( "wpsl-hide" );
            $( ".wpsl-cat-selection, .wpsl-cat-restriction" ).addClass( "wpsl-hide" );
        } else {
            $( ".wpsl-cat-restriction" ).removeClass( "wpsl-hide" );
            $( ".wpsl-checkbox-options, .wpsl-cat-selection, .wpsl-checkbox-selection" ).addClass( "wpsl-hide" );
        }
    });

    // Open the dialog with the same options and framing as the settings page
    // dialogs ( see createDialog() in wpsl-geocode-test.js ).
    $( "#wpsl-open-shortcode-dialog" ).on( "click", function( e ) {
        e.preventDefault();

        $dialog.dialog({
            resizable: false,
            height: 'auto',
            width: 650,
            modal: true,
            closeOnEscape: true,
            closeText: '',
            dialogClass: 'wpsl-dialog wpsl-flex-dialog wpsl-shortcode-dialog',
            classes: { 'ui-dialog': 'wpsl-dialog wpsl-flex-dialog wpsl-shortcode-dialog' },
            appendTo: '#wpbody-content',
            position: {
                my: "center",
                at: "center",
                of: "#wpbody-content"
            },
            open: function() {
                const $container = $( ".ui-dialog.wpsl-shortcode-dialog" );

                // The same close-button swap helpers.ui.dialog.addCloseButton()
                // makes, repeated because that helper lives in the
                // settings-only bundle.
                if ( ! $container.find( ".wpsl-close-cross" ).length ) {
                    $container.find( ".ui-dialog-titlebar-close" ).remove();
                    $container.find( ".ui-dialog-titlebar" ).append( '<button type="button" class="wpsl-close-cross wpsl-dialog-close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path></svg></button>' );
                }

                if ( ! $( "#wpsl-shortcode-tabs" ).hasClass( "ui-tabs" ) ) {
                    $( "#wpsl-shortcode-tabs" ).tabs();
                }

                $( ".ui-widget-overlay, .wpsl-dialog-close" ).on( "click", function() {
                    $dialog.dialog( "close" );
                });
            },
            close: function() {
                $( ".ui-widget-overlay, .wpsl-dialog-close" ).off( "click" );
            },
        });
    });
});