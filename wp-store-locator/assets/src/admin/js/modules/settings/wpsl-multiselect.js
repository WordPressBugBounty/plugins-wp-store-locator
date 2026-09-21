/**
 * Multiselect dropdown menus with checkboxes and tags.
 *
 * @since 3.0.0
 */
export const multiselect = {
    containerSelector: '.wpsl-multiselect-container',
    
    /**
     * Initialize the multiselect dropdown functionality.
     * 
     * @since   3.0.0
     * @returns {void}
     */
    init() {
        this.bindHandlers();
        this.initializeExistingTags();
    },
    
    /**
     * Bind all required event handlers for the multiselect dropdowns.
     * 
     * @since   3.0.0
     * @returns {void}
     */
    bindHandlers() {
        const { containerSelector } = this;
        
        jQuery( document )
            .on( 'click', `${containerSelector} > button`, this.toggleDropdown )
            .on( 'click', this.handleOutsideClick )
            .on( 'change', `${containerSelector} .wpsl-multiselect-menu input[type=checkbox]`, this.handleCheckboxChange )
            .on( 'click', '.wpsl-multiselect-tag-remove', this.handleTagRemove );
    },
    
    /**
     * Initialize tags for containers that already have selected checkboxes.
     * 
     * @since   3.0.0
     * @returns {void}
     */
    initializeExistingTags() {
        const $containers = jQuery( this.containerSelector );
        
        for ( let i = 0; i < $containers.length; i++ ) {
            const $container = jQuery( $containers[i] );
            if ( $container.find( '.wpsl-multiselect-menu li input[type=checkbox]:checked' ).length > 0 ) {
                multiselect.updateDropdownTags( $container );
            }
        }
    },
    
    /**
     * Toggle the dropdown menu when clicking the button.
     * 
     * @since   3.0.0
     * @param   {Event} e - The click event
     * @returns {boolean} - Always returns false to prevent default behavior
     */
    toggleDropdown( e ) {        
        const $button = jQuery( this );
        const $currentMenu = $button.siblings( '.wpsl-multiselect-menu' );

        e.preventDefault();

        jQuery( '.wpsl-multiselect-menu' ).not( $currentMenu ).removeClass( 'show' );

        $currentMenu.toggleClass( 'show' );

        return false;
    },
    
    /**
     * Close dropdowns when clicking outside the container.
     * 
     * @since   3.0.0
     * @param   {Event} event - The click event
     * @returns {void}
     */
    handleOutsideClick( event ) {
        if ( ! jQuery( event.target ).closest( '.wpsl-multiselect-container' ).length ) {
            jQuery( '.wpsl-multiselect-menu.show' ).removeClass( 'show' );
        }
    },
    
    /**
     * Update tags when a checkbox is changed.
     * 
     * @since   3.0.0
     * @param   {Event} event - The change event
     * @returns {void}
     */
    handleCheckboxChange( event ) {
        const $container = jQuery( this ).closest( '.wpsl-multiselect-container' );
        multiselect.updateDropdownTags( $container );
    },
    
    /**
     * Remove a tag and uncheck the corresponding checkbox.
     * 
     * @since   3.0.0
     * @param   {Event} e - The click event
     * @returns {boolean} - Always returns false to prevent default behavior
     */
    handleTagRemove( e ) {
        const $container = jQuery( this ).closest( '.wpsl-multiselect-container' );
        const valueToUncheck = jQuery( this ).data( 'value' );

        e.stopPropagation();

        $container.find( `.wpsl-multiselect-menu li input[type=checkbox][value="${valueToUncheck}"]` ).prop( 'checked', false );
        multiselect.updateDropdownTags( $container );

        return false;
    },
    
    /**
     * Update the tags inside dropdown button based on selected checkboxes.
     * 
     * @since   3.0.0
     * @param   {jQuery} $container - The multiselect container
     * @returns {void}
     */
    updateDropdownTags( $container ) {
        const $selectedCheckboxes = $container.find( '.wpsl-multiselect-menu li input[type=checkbox]:checked' );
        const $contentElement = $container.find( '.wpsl-multiselect-content' );
    
        $contentElement.empty();
        
        if ( $selectedCheckboxes.length === 0 ) {
            this.renderPlaceholder( $container, $contentElement );
        } else {
            this.renderTags( $selectedCheckboxes, $contentElement );
        }
    },
    
    /**
     * Render placeholder text when no options are selected.
     * 
     * @since   3.0.0
     * @param   {jQuery} $container - The multiselect container
     * @param   {jQuery} $contentElement - The content element to update
     * @returns {void}
     */
    renderPlaceholder( $container, $contentElement ) {
        const placeholder = $container.find( 'button' ).data( 'placeholder' );
        $contentElement.append( `<span class="wpsl-multiselect-placeholder">${placeholder}</span>` );
    },
    
    /**
     * Render tags for selected options.
     * 
     * @since   3.0.0
     * @param   {jQuery} $selectedCheckboxes - Selected checkbox elements
     * @param   {jQuery} $contentElement - The content element to update
     * @returns {void}
     */
    renderTags( $selectedCheckboxes, $contentElement ) {
        const fragment = document.createDocumentFragment();
        
        for ( let i = 0; i < $selectedCheckboxes.length; i++ ) {
            const $checkbox = jQuery( $selectedCheckboxes[i] );
            const tagLabel = $checkbox.data( 'label' );
            const tagValue = $checkbox.val();
            
            const tagElement = multiselect.createTagElement( tagLabel, tagValue );

            fragment.appendChild( tagElement );
        }
        
        $contentElement.append( fragment );
    },
    
    /**
     * Create a single tag element.
     * 
     * @since   3.0.0
     * @param   {string} label - The tag label
     * @param   {string} value - The tag value
     * @returns {HTMLElement} - The created tag element
     */
    createTagElement( label, value ) {
        const tagDiv = document.createElement( 'div' );
        tagDiv.className = 'wpsl-multiselect-tag';

        // Use textContent / dataset so label and value are never parsed as HTML.
        const tagText = document.createElement( 'span' );
        tagText.className   = 'wpsl-multiselect-tag-text';
        tagText.textContent = label;

        const tagRemove = document.createElement( 'span' );
        tagRemove.className      = 'wpsl-multiselect-tag-remove';
        tagRemove.dataset.value  = value;
        // SVG is static markup with no variables, so innerHTML is safe here.
        tagRemove.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>';

        tagDiv.appendChild( tagText );
        tagDiv.appendChild( tagRemove );

        return tagDiv;
    }
};