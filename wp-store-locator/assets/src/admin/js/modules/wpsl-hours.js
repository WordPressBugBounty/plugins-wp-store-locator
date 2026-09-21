/**
 * Handles everything openings hours related.
 *
 * @since 3.0.0
 */

export const hours = {

    /**
     * Initialize the opening hours module.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        this.changeFormat();
        this.intervalChanges();
        this.hourChanges();
        this.addPeriod();
    },

    /**
     * Show the hour inputs matching the selected input format.
     *
     * @since   3.0.0
     * @returns {void}
     */
    changeFormat: function() {
        jQuery( '#wpsl-editor-hour-input' ).on( 'change', function() {
            // The 1.x notice applies to both input types, so it is no longer toggled here.
            jQuery( '.wpsl-' + jQuery( this ).val() + '-hours' ).show().siblings( 'div' ).hide();
        });
    },

    /**
     * Update the existing hours when the hour format changes.
     *
     * @since   3.0.0
     * @returns {void}
     */
    intervalChanges: function() {
        jQuery( '#wpsl-editor-hour-format, #wpsl-editor-hour-interval' ).on( 'change', function() {
            const status = [ 'open', 'closed' ];
            for ( let i = 0; i < status.length; i++ ) {
                hours.createHourOptionList( false, status[i] );
            }
        });
    },

    /**
     * Handle removing an opening hour period.
     *
     * @since   2.0.0
     * @returns {void}
     */
    hourChanges: function() {
        jQuery( document ).off( 'click.wpslRemovePeriod' ).on( 'click.wpslRemovePeriod', '#wpsl-store-hours .wpsl-icon-cancel-circled', function() {
            hours.removePeriod( jQuery( this ), 'closed' );
        });

        jQuery( document ).off( 'change.wpslHourChange' ).on( 'change.wpslHourChange', '#wpsl-store-hours select', function() {
            // Only collapse to "always open" on an explicit 24hrs pick. Treating
            // open === closed as 24hrs would also match the transient state while
            // editing one of the two selects, wiping the period mid-edit.
            if ( jQuery( this ).val() === '24hrs' ) {
                hours.removePeriod( jQuery( this ), '24hrs' );
            } else {
                jQuery( this ).parent( '.wpsl-current-period' ).find( '.wpsl-closed-hour' ).removeAttr( 'disabled' );
                jQuery( this ).parents( 'tr' ).find( '.wpsl-manage-period' ).removeClass( 'wpsl-disabled-period' );
            }
        });
    },

    /**
     * Add new openings period to the openings hours table.
     *
     * @since   2.0.0
     * @returns {void}
     */
    addPeriod: function() {
        jQuery( document ).off( 'click.wpslAddPeriod' ).on( 'click.wpslAddPeriod', '#wpsl-store-hours .wpsl-icon-plus-circled', function( e ) {
            e.preventDefault();
            e.stopPropagation();
            
            const $clickedButton = jQuery( this );
            
            const $tr = $clickedButton.closest( 'tr' );
            const $openingHoursCell = $tr.find( '.wpsl-opening-hours' );
            const periodCount = hours.currentPeriodCount( $clickedButton );
            const periodCss = ( periodCount >= 1 ) ? 'wpsl-current-period wpsl-multiple-periods' : 'wpsl-current-period';
            const day = $openingHoursCell.attr( 'data-day' );
            const selectName = ( jQuery( '#wpsl-editor' ).length ) ? 'wpsl_editor[hours]' : 'wpsl[hours]';
            
            if ( ! day ) {
                return;
            }

            let newPeriod, periodHours,
                returnList = true;

            newPeriod = '<div class="' + periodCss +'">';
            newPeriod += '<select autocomplete="off" name="' + selectName + '[' + day + '_open][]" class="wpsl-open-hour">' + hours.createHourOptionList( returnList, 'open' ) + '</select>';
            newPeriod += '<span> - </span>';
            newPeriod += '<select autocomplete="off" name="' + selectName + '[' + day + '_closed][]" class="wpsl-closed-hour">' + hours.createHourOptionList( returnList, 'closed' ) + '</select>';
            newPeriod += '<div class="wpsl-manage-period"><button type="button" class="wpsl-icon-cancel-circled"></button></div>';
            newPeriod += '</div>';

            $tr.find( '.wpsl-custom-period-msg' ).remove();
            jQuery( '#wpsl-hours-' + day ).append( newPeriod );

            if ( jQuery( '#wpsl-editor-hour-format' ).val() === '24' ) {
                periodHours = {
                    'open': '09:00',
                    'close': '17:00'
                };
            } else {
                periodHours = {
                    'open': '9:00 AM',
                    'close': '5:00 PM'
                };
            }

            $tr.find( '.wpsl-open-hour:last option[value="' + periodHours.open + '"]' ).attr( 'selected', 'selected' );
            $tr.find( '.wpsl-closed-hour:last option[value="' + periodHours.close + '"]' ).attr( 'selected', 'selected' );
        });
    },

    /**
     * Remove an openings period
     *
     * @since  2.0.0
     * @param  {object} elem The clicked element
     * @param  {string} type Either set to 'closed' or '24hrs' based on how the users action
     * @return {void}
     */
    removePeriod: function( elem, type ) {
        const periodsLeft = hours.currentPeriodCount( elem );
        const $tr = elem.parents( 'tr' );
        const day = $tr.find('.wpsl-opening-hours').attr('data-day');

        let periodMsg, inputNamePrefix;

        if ( jQuery( '#wpsl-editor' ).length ) {
            inputNamePrefix = 'wpsl_editor';
        } else {
            inputNamePrefix = 'wpsl';
        }

        // If there was 1 opening hour left then we add the 'Closed' text.
        if ( periodsLeft === 1 ) {
            if ( type === '24hrs' ) {
                periodMsg = wpslL10n.alwaysOpen;
            } else {
                periodMsg = wpslL10n.closedDate;
            }

            $tr.find( '.wpsl-opening-hours' ).html( '<p class="wpsl-custom-period-msg"><input type="text" name="' + inputNamePrefix + '[hours][' + day + ']" value="' + periodMsg + '" /></p>' );
            $tr.find( '.wpsl-manage-period' ).removeClass( 'wpsl-disabled-period' );
        }

        elem.parent().closest( '.wpsl-current-period' ).remove();

        if ( $tr.find( '.wpsl-opening-hours div:first-child' ).hasClass( 'wpsl-multiple-periods' ) ) {
            $tr.find( '.wpsl-opening-hours div:first-child' ).removeClass( 'wpsl-multiple-periods' );
        }
    },
    
    /**
     * Count the current opening periods in a day block
     *
     * @since  2.0.0
     * @param  {object} elem The clicked element
     * @return {string} currentPeriods The ammount of period divs found
     */
    currentPeriodCount: function( elem ) {
        return elem.parents( 'tr' ).find( '.wpsl-current-period' ).length;
    },

    /**
     * Create an option list with the correct opening hour format and interval
     *
     * @since  2.0.0
     * @param  {string} returnList Whether to return the option list or call the setSelectedOpeningHours function
     * @param  {string} period Open or closed period
     * @return {mixed} optionList The option list html, or void
     */
    createHourOptionList: function( returnList, period ) {
        const openingTimes = [];
        const openingHourOptions = {
            'hours': {
                'hr12': [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
                'hr24': [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23]
            },
            'interval': ['00', '15', '30', '45']
        };

        let openingHours, openingHourInterval, hour, hrFormat,
            pm = false,
            twelveHrsAfternoon = false,
            pmOrAm = '',
            optionList = '';

        if ( jQuery( '#wpsl-editor-hour-format' ).length ) {
            hrFormat = parseInt( jQuery( '#wpsl-editor-hour-format' ).val() );
        } else {
            hrFormat = parseInt( wpslSettings.hourFormat );
        }

        jQuery( '#wpsl-store-hours td' ).removeAttr( 'style' );

        if ( hrFormat === 12 ) {
            jQuery( '#wpsl-store-hours' ).removeClass().addClass( 'wpsl-twelve-format' );
            openingHours = openingHourOptions.hours.hr12;
        } else {
            jQuery( '#wpsl-store-hours' ).removeClass().addClass( 'wpsl-twentyfour-format' );
            openingHours = openingHourOptions.hours.hr24;
        }

        openingHourInterval = openingHourOptions.interval;

        for ( let i = 0; i < openingHours.length; i++ ) {
            hour = openingHours[i];

            if ( hrFormat === 12 ) {
                if ( hour >= 12 ) {
                    pm = ( twelveHrsAfternoon ) ? true : false;

                    twelveHrsAfternoon = true;
                }

                pmOrAm = ( pm ) ? ' PM' : ' AM';
            } else if ( hrFormat === 24 && hour.toString().length === 1 ) {
                hour = '0' + hour;
            }

            for ( let j = 0; j < openingHourInterval.length; j++ ) {
                openingTimes.push( hour + ':' + openingHourInterval[j] + pmOrAm );
            }
        }

        for ( let k = 0; k < openingTimes.length; k++ ) {
            if ( period === 'open' && ( openingTimes[k] === '00:00' || openingTimes[k] === '12:00 AM' ) ) {
                optionList = optionList + '<option value="24hrs">' + wpslL10n.alwaysOpen + '</option>';
            }

            optionList = optionList + '<option value="' + openingTimes[k].trim() + '">' + openingTimes[k].trim() + '</option>';
        }

        if ( returnList ) {
            return optionList;
        } else {
            hours.setSelectedOpeningHours( optionList, hrFormat, period );
        }
    },
    
    /**
     * Set the correct selected opening hour in the dropdown
     *
     * @since  2.0.0
     * @param  {string} optionList The html for the option list
     * @param  {string} hrFormat The hour format, 12 or 24
     * @param  {string} period Either open / closed
     * @return {void}
     */
    setSelectedOpeningHours: function( optionList, hrFormat, period ) {
        let splitHour, hourType, periodBlock,
            hoursPeriod = {};

        jQuery( '.wpsl-current-period' ).each( function( i ) {
            periodBlock = jQuery( this );
            hoursPeriod = {
                'open': jQuery( this ).find( ".wpsl-open-hour" ).val(),
                'closed': jQuery( this ).find( ".wpsl-closed-hour" ).val()
            };

            jQuery( this ).find( 'select.wpsl-' + period + '-hour' ).html( optionList ).promise().done( function() {
                for ( const key in hoursPeriod ) {
                    if ( hoursPeriod.hasOwnProperty( key ) ) {
                        if ( hoursPeriod[key] !== '24hrs' ) {
                            splitHour = hoursPeriod[key].split( ':' );

                            if ( hrFormat === 12 ) {
                                hourType = '';

                                if ( hoursPeriod[key].charAt( 0 ) === '0' ) {
                                    hoursPeriod[key] = hoursPeriod[key].substr( 1 );
                                    hourType   = ' AM';
                                } else if ( ( splitHour[0].length === 2 ) && ( splitHour[0] > 12 ) ) {
                                    hoursPeriod[key] = ( splitHour[0] - 12 ) + ':' + splitHour[1];
                                    hourType   = ' PM';
                                } else if ( splitHour[0] < 12 ) {
                                    hoursPeriod[key] = splitHour[0] + ':' + splitHour[1];
                                    hourType   = ' AM';
                                } else if ( splitHour[0] === '12' ) {
                                    hoursPeriod[key] = splitHour[0] + ':' + splitHour[1];
                                    hourType   = ' PM';
                                }

                                if ( ( splitHour[1].indexOf( 'PM' ) === -1 ) && ( splitHour[1].indexOf( 'AM' ) === -1 ) ) {
                                    hoursPeriod[key] = hoursPeriod[key] + hourType;
                                }
                            } else if ( hrFormat === 24 ) {
                                if ( splitHour[1].indexOf( 'PM' ) !== -1 ) {
                                    if ( splitHour[0] === '12' ) {
                                        hoursPeriod[key] = '12:' + splitHour[1].replace( ' PM', '' );
                                    } else {
                                        hoursPeriod[key] = ( + splitHour[0] + 12 ) + ':' + splitHour[1].replace( ' PM', '' );
                                    }
                                } else if ( splitHour[1].indexOf( 'AM' ) !== -1 ) {
                                    if ( splitHour[0].toString().length === 1 ) {
                                        hoursPeriod[key] = '0' + splitHour[0] + ':' + splitHour[1].replace( ' AM', '' );
                                    } else {
                                        hoursPeriod[key] = splitHour[0] + ':' + splitHour[1].replace( ' AM', '' );
                                    }
                                } else {
                                    hoursPeriod[key] = splitHour[0] + ':' + splitHour[1];
                                }
                            }
                        }

                        periodBlock.find( '.wpsl-' + key + '-hour option[value="' + hoursPeriod[key].trim() + '"]' ).attr( 'selected', 'selected' );
                    }
                }
            });
        });
    }
};