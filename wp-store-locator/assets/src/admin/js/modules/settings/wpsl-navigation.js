import { helpers } from '../wpsl-helpers.js';

/**
 * Navigation tabs on the settings page.
 *
 * @since 3.0.0
 */
export const navigation = {
    fullPageIds: [ '#wpsl-section-editor' ], // the pages that require more space

    /**
     * Initialize navigation tabs and event handlers.
     *
     * @since   3.0.0
     * @returns {void}
     */
    init: function() {
        this.bind();

        // Pass restore()'s resolved hash to toggleFullPageView(). Reading
        // window.location.hash directly here misses the ?section= (form-save
        // redirect) and localStorage cases, leaving the content area blank.
        const activeHash = this.restore();

        this.toggleFullPageView( activeHash );
        this.initKeyboardNav();

        // Initialize validation once for all required fields
        helpers.dom.validateRequiredFields();
        
        // The admin bar WPSL menu items link to a hash like #wpsl-alerts, so
        // activate the matching section instead of only changing the hash.
        jQuery( '#wpadminbar #wp-admin-bar-wpsl-menu a' ).on( 'click', function( e ) {
            const href = jQuery( this ).attr( 'href' );
            
            // Check if this is a hash link to the settings page
            if ( href && href.indexOf( '#wpsl-' ) !== -1 ) {
                const hash = href.split( '#' )[1];
                const currentPage = window.location.href.split( '#' )[0];
                const targetPage = href.split( '#' )[0];
                
                // Only continue we're already on the settings page
                if ( currentPage === targetPage ) {
                    e.preventDefault();
                    
                    const $navLink = jQuery( '#wpsl-content-wrap nav a[href="#' + hash + '"]' );
                    if ( $navLink.length > 0 ) {
                        $navLink.trigger( 'click' );
                    }
                    
                    return false;
                }
            }
        });
    },

    /**
     * Bind the click handler.
     *
     * @since   3.0.0
     * @returns {void}
     */
    bind: function() {
        const $contentWrap = jQuery( '#wpsl-content-wrap' );
        const $settingsForm = jQuery( '#wpsl-settings-form' );
        
        // Restore the nav menu a full screen section hid.
        jQuery( '.wpsl-show-sections' ).on( 'click', function() {
            $contentWrap.find( 'li.wpsl-active-nav-item > a' ).trigger( 'click' );
            $contentWrap.addClass( 'wpsl-settings-grid' );
            $contentWrap.find( 'nav' ).show();

            return false;
        });

        /**
         * Handle clicks on .wpsl-trigger-nav links, which switch sections
         * programmatically. data-item selects the section; data-focus focuses
         * a named input within it.
         *
         * @param {Event} e The click event.
         */
        jQuery( document ).on( 'click', '.wpsl-trigger-nav', function( e ) {
            const targetItem = jQuery( this ).data( 'item' );
            const focusField = jQuery( this ).data( 'focus' );

            e.preventDefault();

            navigation.changeSelection( targetItem );

            if ( focusField ) {
                const $field = jQuery( '[name="' + focusField + '"]' );
                if ( $field.length ) {
                    $field.focus();

                    jQuery( 'html, body' ).animate({
                        scrollTop: $field.offset().top - 100
                    }, 300 );
                }
            }

            return false;
        });

        $contentWrap.find( 'nav a' ).on( 'click', function( e ) {
            const id = jQuery( this ).attr( 'href' );
            const hiddenInputVal = id.split( '#wpsl-' );
            
            $settingsForm.find( '.notice' ).remove();

            // Update hash without triggering scroll
            if ( history.pushState ) {
                history.pushState( null, null, id );
            } else {
                document.location.hash = id;
            }

            localStorage.setItem( 'wpsl-hash', id );

            if ( helpers.settings.isFullPageSection( id ) ) {
                helpers.settings.setFullPage();
            }

            $settingsForm.find( 'section' ).removeClass( 'active' );
            $contentWrap.find( 'li' ).removeClass( 'wpsl-active-nav-item' );
            $settingsForm.find( '.notice.updated' ).remove();
            
            jQuery( '#wpsl-active-nav-section' ).val( hiddenInputVal[1] );

            if ( jQuery( this ).parents().is( 'ul.wpsl-sub-nav' ) ) {
                jQuery( this ).closest( 'ul.wpsl-sub-nav' ).parent( 'li' ).addClass( 'wpsl-active-nav-item' );
                jQuery( id ).addClass( 'active' );

                 // Check if we have subsections.
                 //
                 // If this is the case and the users click
                 // on the nav link, then we make sure only
                 // the first div is visible.
                const $subSections = jQuery( id ).find( '.inside > div' );
                if ( $subSections.length && ! jQuery( id ).hasClass( 'wpsl-no-sections' ) ) {
                    $subSections.removeAttr( 'style' ).last().addClass( 'wpsl-hide' );
                }
            } else {
                jQuery( this ).parent().addClass( 'wpsl-active-nav-item' );
                jQuery( id ).addClass( 'active' );
            }

            // Scroll to keep header visible
            const $header = jQuery( '#wpsl-header' );
            
            if ( $header.length ) {
                const adminBarHeight = jQuery( '#wpadminbar' ).outerHeight() || 0;
                const headerPosition = $header.offset().top - adminBarHeight;

                jQuery( 'html, body' ).animate({
                    scrollTop: headerPosition
                }, 300 );
            }

            navigation.updateNavState();

            return false;
        });
    },

    /**
     * Make sure the correct tab / section is
     * selected when the WPSL settings page loads.
     * 
     * Prioritizes URL hash over localStorage to ensure
     * direct links work correctly.
     *
     * @since   3.0.0
     * @returns {void}
     */
    restore: function() {
        const $contentWrap = jQuery( '#wpsl-content-wrap' );
        const $settingsForm = jQuery( '#wpsl-settings-form' );
        const $adminMenuLinks = jQuery( '#wpadminbar a, #adminmenumain a' );
        const adminMenuLength = $adminMenuLinks.length;

        // Try to get hash from URL first, then check URL parameter, then fall back to localStorage
        let hashId = window.location.hash;
        
        // If URL doesn't have a hash, check if we have a section parameter (from form save redirect)
        if ( ! hashId || hashId.length === 0 ) {
            const urlParams = new URLSearchParams( window.location.search );
            const sectionParam = urlParams.get( 'section' );
            if ( sectionParam ) {
                hashId = '#wpsl-' + sectionParam;
            } else {
                // Otherwise fall back to localStorage
                hashId = localStorage.getItem( 'wpsl-hash' );
            }
        }

        // If the user clicks on a link in the
        // admin menu, then empty the 'wpsl-hash' value.
        //
        // This makes sure that when the user leaves
        // the WPSL settings page and returns, the first
        // item is always selected.
        for ( let i = 0; i < adminMenuLength; i++ ) {
            $adminMenuLinks[i].addEventListener( 'click', function() {
                localStorage.setItem( 'wpsl-hash', '' );
            });
        }

        // Ensure we have a valid hash to work with
        if ( hashId && hashId.length > 0 ) {
            // Ensure the hash always starts with '#'.
            // 
            // This handles edge cases where the hash might be stored
            // or retrieved without the leading '#' character.
            if ( hashId.charAt( 0 ) !== '#' ) {
                hashId = '#' + hashId;
            }

            // Strip '#' for helper checks
            const hashWithoutPrefix = hashId.substring( 1 );

            // Full-page sections are handled entirely by toggleFullPageView(),
            // so skip nav highlight and section activation here.
            if ( helpers.settings.isFullPageSection( hashWithoutPrefix ) ) {
                localStorage.setItem( 'wpsl-hash', hashId );
                navigation.setHiddenInput( hashId );

                return hashId;
            }

            const $navElementHashId = $contentWrap.find( 'nav a[href="' + hashId + '"]' );

            $contentWrap.find( 'nav li' ).removeClass( 'wpsl-active-nav-item' );

            // Apply active class to the nav element
            if ( $navElementHashId.length > 0 ) {
                if ( $navElementHashId.closest( 'ul' ).hasClass( 'wpsl-sub-nav' ) ) {
                    $navElementHashId.closest( 'ul.wpsl-sub-nav' ).parent( 'li' ).addClass( 'wpsl-active-nav-item' );
                } else {
                    $navElementHashId.parent().addClass( 'wpsl-active-nav-item' );
                }
            }

            // Apply active class to the section
            const $section = $settingsForm.find( hashId );

            if ( $section.length > 0 ) {
                $section.addClass( 'active' );
            } else {
                // Hash doesn't match any section — fall back to the default tab
                console.warn( '[WPSL Nav] Hash "' + hashId + '" does not match any section, falling back to #wpsl-api.' );

                hashId = '#wpsl-api';
                $contentWrap.find( '> nav li:first-child' ).addClass( 'wpsl-active-nav-item' );
                $settingsForm.find( '> section:first-child' ).addClass( 'active' );
            }
            
            localStorage.setItem( 'wpsl-hash', hashId );
        } else {
            // Default to the first tab if no hash is found
            hashId = '#wpsl-api';
            window.location.hash = hashId;
            localStorage.setItem( 'wpsl-hash', hashId );

            $contentWrap.find( '> nav li:first-child' ).addClass( 'wpsl-active-nav-item' );
            $settingsForm.find( '> section:first-child' ).addClass( 'active' );
        }

        navigation.updateNavState();
        navigation.setHiddenInput( hashId );

        return hashId;
    },

    /**
     * Force a change in the selected
     * nav item and show the corresponding
     * section.
     *
     * @since   3.0.0
     * @param   {string} target
     * @returns {void}
     */
    changeSelection: function( target ) {
        const $settingsForm = jQuery( '#wpsl-settings-form' );
        $settingsForm.find( 'section' ).removeClass( 'active' );
        $settingsForm.find( '#wpsl-' + target ).addClass( 'active' );

        const $nav = jQuery( '#wpsl-nav' );
        $nav.show();

        const $contentWrap = jQuery( '#wpsl-content-wrap' );
        $contentWrap.addClass( 'wpsl-settings-grid' );

        $nav.find( 'li' ).removeClass( 'wpsl-active-nav-item' ).find( 'a[href="#wpsl-' + target + '"]' ).parent( 'li' ).addClass( 'wpsl-active-nav-item' );

        // Keep the hash, localStorage and the hidden input in sync with the
        // nav click handler, so a save from here redirects back to this
        // section instead of the one the page loaded with.
        const hashId = '#wpsl-' + target;

        if ( history.pushState ) {
            history.pushState( null, null, hashId );
        } else {
            document.location.hash = hashId;
        }

        localStorage.setItem( 'wpsl-hash', hashId );
        navigation.setHiddenInput( hashId );

        navigation.updateNavState();
    },

    /**
     * Toggle the full page view on page load.
     *
     * @since   3.0.0
     * @param   {string} hash
     * @returns {void}
     */
    toggleFullPageView: function( hash ) {
        const $menu = jQuery( '#wpsl-nav' );
        const $content = jQuery( '#wpsl-content-wrap' );
        const $settingsForm = jQuery( '#wpsl-settings-form' );

        // If hash is empty or null, don't do anything special
        if ( ! hash ) {
            $menu.show();
            $content.addClass( 'wpsl-settings-grid' );

            return;
        }

        if ( helpers.settings.isFullPageSection( hash ) ) {
            $settingsForm.find( 'section' ).removeClass( 'active' );

            const sectionId = hash.startsWith( '#' ) ? hash : '#' + hash;
            const $section = jQuery( sectionId );
            if ( $section.length ) {
                $section.addClass( 'active' );
            }

            $menu.hide();
            $content.removeClass( 'wpsl-settings-grid' );
        } else {
            // Make sure the section with this hash ID is visible
            const sectionId = hash.startsWith( '#' ) ? hash : '#' + hash;
            const targetSection = jQuery( sectionId );
            if ( targetSection.length ) {
                $settingsForm.find( 'section' ).removeClass( 'active' );
                targetSection.addClass( 'active' );
            }

            $menu.show();
            $content.addClass( 'wpsl-settings-grid' );
        }
    },

    /**
     * Initialize ARIA Tab Panel keyboard navigation.
     *
     * Applies the tablist / tab / tabpanel roles and their attributes, then
     * binds the arrow keys ( move focus, wrapping ), Enter and Space
     * ( activate ), and Tab ( jump to the first focusable element in the
     * active section ).
     *
     * @since   3.0.0
     * @returns {void}
     */
    initKeyboardNav: function() {
        const $navList = jQuery( '#wpsl-content-wrap nav > ul' );
        $navList.attr( 'role', 'tablist' );

        $navList.find( '> li' ).each( function() {
            const $link = jQuery( this ).find( '> a' );
            const href  = $link.attr( 'href' ) || '';
            const id    = href.replace( '#', '' );
            const tabId = id.replace( 'wpsl-', 'wpsl-tab-' );

            $link.attr({
                id:              tabId,
                role:            'tab',
                'aria-controls': id,
            });

            jQuery( href ).attr({
                role:              'tabpanel',
                'aria-labelledby': tabId,
            });
        });

        navigation.updateNavState();

        $navList.find( '> li > a' ).on( 'keydown.wpsl-nav', function( e ) {
            const $allTabs   = $navList.find( '> li > a' );
            const $current   = jQuery( this );
            const currentIdx = $allTabs.index( $current );
            const total      = $allTabs.length;

            switch ( e.key ) {
                case 'ArrowDown':
                case 'ArrowRight':
                    e.preventDefault();
                    $allTabs.attr( 'tabindex', '-1' );
                    $allTabs.eq( ( currentIdx + 1 ) % total )
                            .attr( 'tabindex', '0' )
                            .focus();
                    break;
                case 'ArrowUp':
                case 'ArrowLeft':
                    e.preventDefault();
                    $allTabs.attr( 'tabindex', '-1' );
                    $allTabs.eq( ( currentIdx - 1 + total ) % total )
                            .attr( 'tabindex', '0' )
                            .focus();
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    $current.trigger( 'click' );
                    break;
                case 'Tab':
                    if ( ! e.shiftKey ) {
                        const $activeSection = jQuery( '#wpsl-settings-form section.active' );

                        if ( $activeSection.length ) {
                            const $first = $activeSection.find(
                                'a[href], button:not([disabled]), ' +
                                'input:not([type="hidden"]):not([disabled]), ' +
                                'select:not([disabled]), textarea:not([disabled]), ' +
                                '[tabindex="0"]'
                            ).filter( ':visible' ).first();

                            if ( $first.length ) {
                                e.preventDefault();
                                // Reset roving tabindex to active tab before leaving the nav.
                                navigation.updateNavState();
                                $first.focus();
                            }
                        }
                    }
                    break;
            }
        });
    },

    /**
     * Sync tabindex and aria-selected on all tab links with
     * the currently active nav item. Call whenever the active tab changes.
     *
     * @since   3.0.0
     * @returns {void}
     */
    updateNavState: function() {
        const $navList = jQuery( '#wpsl-content-wrap nav > ul' );
        const $links   = $navList.find( '> li > a' );
        const $active  = $navList.find( '> li.wpsl-active-nav-item > a' );

        $links.attr({ tabindex: '-1', 'aria-selected': 'false' });

        if ( $active.length ) {
            $active.attr({ tabindex: '0', 'aria-selected': 'true' });
        } else {
            $links.first().attr( 'tabindex', '0' );
        }
    },

    /**
     * Extract the section name from a hash and set it
     * in the hidden input field.
     *
     * @since   3.0.0
     * @param   {string} hashId
     * @returns {void}
     */
    setHiddenInput: function( hashId ) {
        let hiddenInputVal = 'api-settings';

        if ( hashId && hashId.indexOf( '#wpsl-' ) !== -1 ) {
            hiddenInputVal = hashId.split( '#wpsl-' )[1] || 'api-settings';
        }

        jQuery( '#wpsl-active-nav-section' ).val( hiddenInputVal );
    },
};