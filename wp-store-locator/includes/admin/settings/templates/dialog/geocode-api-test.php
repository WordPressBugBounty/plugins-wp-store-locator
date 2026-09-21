<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="wpsl-geocode-test" class="wpsl-hide" title="<?php esc_html_e( 'Geocode API Response', 'wp-store-locator' ); ?>">
    <div class="wpsl-geocode-warning" style="display: none;">
        <p><strong><?php esc_html_e( 'Note', 'wp-store-locator' ); ?>: </strong></p>
    </div>

    <p class="wpsl-geocode-api-notice" style="display: none;">
        <strong><?php esc_html_e( 'API Status', 'wp-store-locator' ); ?>: </strong>
        <span></span>
    </p>

    <div class="wpsl-geocode-input-required" style="display: none;">
        <p class="wpsl-error-text"><?php esc_html_e( 'Please provide the details for at least one of the location fields.', 'wp-store-locator' ); ?></p>
    </div>

    <div class="wpsl-geocode-test-container">
        <div class="wpsl-geocode-input">
            <p><input id="wpsl-address" type="text" placeholder="<?php esc_html_e( 'Address', 'wp-store-locator' ); ?>" ></p>
            <p><input id="wpsl-city" type="text" placeholder="<?php esc_html_e( 'City', 'wp-store-locator' ); ?>" ></p>
            <p><input id="wpsl-state" type="text" placeholder="<?php esc_html_e( 'State', 'wp-store-locator' ); ?>" ></p>
            <p><input id="wpsl-zip" type="text" placeholder="<?php esc_html_e( 'Zip Code', 'wp-store-locator' ); ?>" ></p>
            <p><input id="wpsl-country" type="text" placeholder="<?php esc_html_e( 'Country', 'wp-store-locator' ); ?>" ></p>
        </div>

        <div class="wpsl-geocode-results">
            <div id="wpsl-geocode-tabs" style="width: auto;">
                <ul>
                    <li><a href="#wpsl-geocode-map-wrap"><?php esc_html_e( 'Map Preview', 'wp-store-locator' ); ?></a></li>
                    <li><a href="#wpsl-geocode-response"><?php esc_html_e( 'API Response', 'wp-store-locator' ); ?></a></li>
                </ul>

                <div id="wpsl-geocode-map-wrap">
                    <div id="wpsl-<?php echo esc_attr( wpsl_get_service( 'wpsl_settings' )->get( 'api', 'active_map_service' ) ); ?>-geocode-preview"></div>
                </div>
                <div id="wpsl-geocode-response">
                    <textarea readonly="readonly" cols="50" rows="25"></textarea>
                </div>
            </div>
        </div>
    </div>
    <div>
        <input class="button-primary" id="wpsl-geocode-submit" type="submit" value="<?php esc_html_e( 'Get Response', 'wp-store-locator' ); ?>" name="search" />
        <input class="button-secondary" id="wpsl-geocode-reset" type="submit" value="<?php esc_html_e( 'Reset', 'wp-store-locator' ); ?>" name="reset">
    </div>
</div>