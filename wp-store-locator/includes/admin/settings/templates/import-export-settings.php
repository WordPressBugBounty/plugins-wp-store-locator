<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="wpsl-content-wrap" class="wpsl-import-export-settings">
    <?php
    settings_errors();

    // The post-import summary carries a list, which add_settings_error()
    // cannot render - see Settings_Transfer::render_import_notices().
    if ( isset( $_REQUEST['wpsl-status'] ) && 'settings-import-complete' === sanitize_key( wp_unslash( $_REQUEST['wpsl-status'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading a status flag set by our own redirect; renders a notice, changes nothing.
        \WPSL\Admin\Tools\Settings_Transfer::render_import_notices();
    }
    ?>
    <div class="wpsl-grid-wrap">
        <div class="wpsl-grid-item">
            <p><?php esc_html_e( 'Import' , 'wp-store-locator' ); ?></p>
            <form id="wpsl-import-settings" action="" enctype="multipart/form-data" method="post">
                <label for="wpsl-file-upload" id="wpsl-drop-zone" class="wpsl-drop-zone">
                    <span class="wpsl-drop-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </span>
                    <span class="wpsl-drop-title"><?php esc_html_e( 'Drag & Drop your .json file here', 'wp-store-locator' ); ?></span>
                    <span class="wpsl-drop-subtitle"><?php esc_html_e( 'or click to browse from your computer', 'wp-store-locator' ); ?></span>
                    <input type="file" id="wpsl-file-upload" name="import-wpsl-settings" accept=".json" class="wpsl-drop-input">
                </label>

                <div id="wpsl-file-feedback" class="wpsl-message wpsl-file-feedback wpsl-hide" role="status" data-invalid-msg="<?php esc_attr_e( 'Incorrect file type, please select a valid .json file.', 'wp-store-locator' ); ?>">
                    <span id="wpsl-file-name"></span>
                    <button type="button" id="wpsl-remove-file" class="wpsl-file-remove" aria-label="<?php esc_attr_e( 'Remove file', 'wp-store-locator' ); ?>">&#10005;</button>
                </div>

                <p id="wpsl-import-submit" class="wpsl-hide"><?php submit_button( 'Import Settings', 'primary', 'wpsl-settings-file', false ); ?></p>
                <?php wp_nonce_field( 'wpsl_import_settings', 'wpsl_import_settings_nonce' ); ?>
                <input type="hidden" name="wpsl-action" value="import_settings"/>
            </form>
        </div>

        <div class="wpsl-grid-item">
            <p><?php esc_html_e( 'Export' , 'wp-store-locator' ); ?></p>
            <form id="wpsl-export-settings" action="" method="post">
                <p><?php esc_html_e( 'Export the WP Store Locator settings to .json file. This enables you to import the current settings on another domain.', 'wp-store-locator' ); ?></p>
                <p><?php submit_button( 'Export Settings', 'primary', 'wpsl-settings-export', false ); ?></p>
                <?php wp_nonce_field( 'wpsl_export_settings', 'wpsl_export_settings_nonce' ); ?>
                <input type="hidden" name="wpsl-action" value="export_settings"/>
            </form>
        </div>
    </div>
</div>
<script>
    ( function() {
        const dropZone  = document.getElementById( 'wpsl-drop-zone' );
        const fileInput = document.getElementById( 'wpsl-file-upload' );
        const feedback  = document.getElementById( 'wpsl-file-feedback' );
        const fileName  = document.getElementById( 'wpsl-file-name' );
        const removeBtn = document.getElementById( 'wpsl-remove-file' );
        const submit    = document.getElementById( 'wpsl-import-submit' );

        if ( ! dropZone ) {
            return;
        }

        function setFeedback( message, accepted ) {
            fileName.textContent = message;

            feedback.classList.remove( 'wpsl-hide' );
            feedback.classList.toggle( 'wpsl-message-success', accepted );
            feedback.classList.toggle( 'wpsl-message-error', ! accepted );

            // Nothing to take back while the message is a rejection.
            removeBtn.classList.toggle( 'wpsl-hide', ! accepted );
            submit.classList.toggle( 'wpsl-hide', ! accepted );
            dropZone.classList.toggle( 'wpsl-hide', accepted );
        }

        function showFile( name ) {
            if ( name.toLowerCase().endsWith( '.json' ) ) {
                setFeedback( name, true );

                return;
            }

            fileInput.value = '';
            setFeedback( feedback.dataset.invalidMsg, false );
        }

        fileInput.addEventListener( 'change', function() {
            if ( fileInput.files.length ) {
                showFile( fileInput.files[0].name );
            }
        } );

        removeBtn.addEventListener( 'click', function() {
            fileInput.value = '';
            fileName.textContent = '';

            feedback.classList.add( 'wpsl-hide' );
            feedback.classList.remove( 'wpsl-message-success', 'wpsl-message-error' );
            submit.classList.add( 'wpsl-hide' );
            dropZone.classList.remove( 'wpsl-hide' );
        } );

        [ 'dragenter', 'dragover' ].forEach( function( type ) {
            dropZone.addEventListener( type, function( e ) {
                e.preventDefault();
                dropZone.classList.add( 'wpsl-dragover' );
            } );
        } );

        [ 'dragleave', 'drop' ].forEach( function( type ) {
            dropZone.addEventListener( type, function( e ) {
                e.preventDefault();
                dropZone.classList.remove( 'wpsl-dragover' );
            } );
        } );

        dropZone.addEventListener( 'drop', function( e ) {
            const files = e.dataTransfer.files;

            if ( ! files.length ) {
                return;
            }

            // Assigning a FileList is what makes the drop reach the form on submit.
            fileInput.files = files;
            showFile( files[0].name );
        } );
    } )();
</script>