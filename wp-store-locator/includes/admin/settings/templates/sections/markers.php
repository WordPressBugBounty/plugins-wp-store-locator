<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section id="wpsl-markers" class="postbox">
    <h3><span><?php esc_html_e( 'Markers', 'wp-store-locator' ); ?></span></h3>
    <div class="inside">
        <p class="wpsl-markers-intro">
            <?php esc_html_e( 'Pick one of the bundled markers, or create your own in the Marker Studio: design a marker from shapes, colors and icons, use your logo inside a shape, or upload an image to use as the marker.', 'wp-store-locator' ); ?>
        </p>
        <?php echo wpsl_get_service( 'marker_manager' )->show_marker_pickers(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Method already escapes output ?>
        <p>
            <label for="wpsl-marker-labels"><?php esc_html_e( 'Marker labels', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide">
                        <?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: %1$s: line breaks, %2$s: opening strong tag, %3$s: closing strong tag. */
                                __( 'Number or letter each marker to match its position in the search results.%1$sA marker fits three characters, so markers past result 999 stay unlabelled. %2$sNot recommended if a search can return more than 999 results.%3$s%1$sBundled pins can\'t show a label, so a default Studio pin is used instead. Your own marker images are kept, without a label.', 'wp-store-locator' ),
                                '<br><br>',
                                '<strong>',
                                '</strong>'
                            )
                        );
                        ?>
                    </span>
                </span>
            </label>
            <select id="wpsl-marker-labels" name="wpsl_markers[labels]">
                <?php
                $wpsl_label_modes = [
                    'none'    => __( 'Off', 'wp-store-locator' ),
                    'numbers' => __( 'Numbers ( 1, 2, 3 )', 'wp-store-locator' ),
                    'letters' => __( 'Letters ( A, B, C )', 'wp-store-locator' ),
                ];

                $wpsl_label_current = isset( $section_settings['labels'] ) ? $section_settings['labels'] : 'none';

                foreach ( $wpsl_label_modes as $wpsl_label_mode => $wpsl_label_name ) {
                    echo '<option value="' . esc_attr( $wpsl_label_mode ) . '"' . selected( $wpsl_label_current, $wpsl_label_mode, false ) . '>' . esc_html( $wpsl_label_name ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label for="wpsl-start-marker-on-top"><?php esc_html_e( 'Show start marker on top of store markers?', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide">
                        <?php esc_html_e( 'When enabled, the start marker will always appear above store markers on the map, even when they overlap.', 'wp-store-locator' ); ?>
                    </span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $section_settings['start_marker_on_top'], true ); ?> name="wpsl_markers[start_marker_on_top]" id="wpsl-start-marker-on-top">
        </p>
        <p>
            <label for="wpsl-marker-clusters"><?php esc_html_e( 'Enable marker clusters?', 'wp-store-locator' ); ?>
                <span class="wpsl-info">
                    <span class="wpsl-info-text wpsl-hide">
                        <?php esc_html_e( 'Recommended for maps with a large amount of markers.', 'wp-store-locator' ); ?>

                        <?php if ( wpsl_get_service( 'wpsl_settings' )->get( 'api', 'active_map_service' ) === 'mapbox' ) { ?>
                            <br><br>
                            <strong><?php esc_html_e( 'Note:', 'wp-store-locator' ); ?></strong> <?php esc_html_e( 'When this option is enabled, keyboard navigation is not available for the markers on the Mapbox map.', 'wp-store-locator' ); ?>
                        <?php } ?>
                    </span>
                </span>
            </label>
            <input type="checkbox" value="" <?php checked( $section_settings['marker_clusters'], true ); ?> name="wpsl_markers[clusters]" id="wpsl-marker-clusters" class="wpsl-has-conditional-option">
        </p>

        <div class="wpsl-conditional-option" <?php if ( ! $section_settings['marker_clusters'] ) { echo 'style="display:none;"'; } ?>>
            <?php
            $active_map_service = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'active_map_service' );
            ?>
            <div class="wpsl-styled-radio wpsl-api-gmaps"<?php if ( $active_map_service !== 'gmaps' ) { echo ' style="display:none;"'; } ?>>
                <p class="wpsl-advanced">
                    <label for="wpsl-default-marker-cluster"><?php esc_html_e( 'Default style', 'wp-store-locator' ); ?>
                        <span class="wpsl-info">
                            <span class="wpsl-info-text wpsl-hide">
                                <img src="<?php echo esc_url( WPSL_URL . 'assets/img/admin/marker-cluster-default.gif' ); ?>" width="150" height="150" alt="<?php esc_attr_e( 'Default cluster marker', 'wp-store-locator' ); ?>">
                            </span>
                        </span>
                    </label>
                    <input type="radio" value="default" <?php checked( 'default', $section_settings['cluster_style'], true ); ?> id="wpsl-default-marker-cluster" name="wpsl_markers[cluster_style]">
                </p>
                <p class="wpsl-advanced">
                    <label for="wpsl-interpolation-marker-cluster"><?php esc_html_e( 'Interpolation style', 'wp-store-locator' ); ?>
                        <span class="wpsl-info">
                            <span class="wpsl-info-text wpsl-hide">
                                <img src="<?php echo esc_url( WPSL_URL . 'assets/img/admin/marker-cluster-color.gif' ); ?>" width="150" height="150" alt="<?php esc_attr_e( 'Color interpolation cluster marker', 'wp-store-locator' ); ?>">
                            </span>
                        </span>
                    </label>
                    <input type="radio" value="interpolation" <?php checked( 'interpolation', $section_settings['cluster_style'], true ); ?> id="wpsl-interpolation-marker-cluster" name="wpsl_markers[cluster_style]">
                </p>
                <p class="wpsl-advanced">
                    <label for="wpsl-cluster-low-density-color"><?php esc_html_e( 'Low density color', 'wp-store-locator' ); ?></label>
                    <input type="text" class="wpsl-color-field" id="wpsl-cluster-low-density-color" name="wpsl_markers[cluster_low_density_color]" value="<?php echo esc_attr( $section_settings['cluster_low_density_color'] ?? '#0000ff' ); ?>" data-default="#0000ff">
                </p>
                <p class="wpsl-advanced">
                    <label for="wpsl-cluster-high-density-color"><?php esc_html_e( 'High density color', 'wp-store-locator' ); ?></label>
                    <input type="text" class="wpsl-color-field" id="wpsl-cluster-high-density-color" name="wpsl_markers[cluster_high_density_color]" value="<?php echo esc_attr( $section_settings['cluster_high_density_color'] ?? '#ff0000' ); ?>" data-default="#ff0000">
                </p>
                <p class="wpsl-advanced">
                    <label for="wpsl-cluster-label-color"><?php esc_html_e( 'Label color', 'wp-store-locator' ); ?></label>
                    <input type="text" class="wpsl-color-field" id="wpsl-cluster-label-color" name="wpsl_markers[cluster_label_color]" value="<?php echo esc_attr( $section_settings['cluster_label_color'] ?? '#ffffff' ); ?>" data-default="#ffffff">
                </p>
                <p>
                    <label for="wpsl-cluster-marker-shape"><?php esc_html_e( 'Cluster marker shape', 'wp-store-locator' ); ?>
                        <span class="wpsl-info">
                            <span class="wpsl-info-text wpsl-hide"><?php esc_html_e( 'The shape used for the cluster markers on the map. Extra shapes can be added with the wpsl_cluster_marker_shapes filter.', 'wp-store-locator' ); ?></span>
                        </span>
                    </label>
                    <select id="wpsl-cluster-marker-shape" name="wpsl_markers[cluster_marker_shape]" autocomplete="off">
                        <?php $current_shape = $section_settings['cluster_marker_shape'] ?? 'default'; ?>
                        <option value="default" <?php selected( $current_shape, 'default' ); ?>><?php esc_html_e( 'Default (circles)', 'wp-store-locator' ); ?></option>
                        <?php foreach ( wpsl_get_cluster_marker_shapes() as $shape_key => $shape ) { ?>
                        <option value="<?php echo esc_attr( $shape_key ); ?>" <?php selected( $current_shape, $shape_key ); ?>><?php echo esc_html( $shape['label'] ?? ucfirst( $shape_key ) ); ?></option>
                        <?php } ?>
                    </select>
                </p>
            </div>
            <?php do_action( 'wpsl_after_cluster_style' ) ?>
            <p>
                <label for="wpsl-marker-exclude"><?php esc_html_e( 'Exclude the start marker from the marker cluster?', 'wp-store-locator' ); ?></label>
                <input type="checkbox" value="" <?php checked( $section_settings['cluster_exclude_start'], true ); ?> name="wpsl_markers[cluster_exclude_start]" id="wpsl-marker-exclude">
            </p>
            <p>
                <label for="wpsl-marker-zoom"><?php esc_html_e( 'Max zoom level', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: line breaks */ echo wp_kses_post( sprintf( __( 'If this zoom level is reached or exceeded, all markers are moved out of the marker cluster and shown as individual markers. %1$s If the field is left empty, it will default to 16 for Google Maps. %2$sOpenStreetMaps will automatically adjust it based on the number of markers.', 'wp-store-locator' ), '<br><br>', '<br><br>' ) ); ?></span></span></label>
                <input type="text" value="<?php echo esc_attr( $section_settings['cluster_zoom'] ); ?>" name="wpsl_markers[cluster_zoom]" id="wpsl-marker-zoom">
            </p>
            <p>
                <label for="wpsl-marker-cluster-size"><?php esc_html_e( 'Cluster size', 'wp-store-locator' ); ?><span class="wpsl-info"><span class="wpsl-info-text wpsl-hide"><?php /* translators: %1$s: line breaks, %2$s: line breaks */ echo wp_kses_post( sprintf( __( 'This pixel value determines how close markers need to be to each other to be grouped into a cluster. %1$s A larger number will result in a lower amount of clusters and also make the algorithm run faster. %2$s If the field is left empty, it will default to 80 for OpenStreetMaps and to 60 for Google Maps.', 'wp-store-locator' ), '<br><br>', '<br><br>' ) ); ?></span></span></label>
                <input type="text" value="<?php echo esc_attr( $section_settings['cluster_size'] ); ?>" name="wpsl_markers[cluster_size]" id="wpsl-marker-cluster-size">
            </p>
        </div>
        <?php do_action( 'wpsl_markers_settings_section' ); ?>
        <p class="submit wpsl-markers-submit">
            <input type="submit" value="<?php esc_html_e( 'Save Changes', 'wp-store-locator' ); ?>" class="button-primary">
            <?php // Creating one is offered inside every picker; this is the library itself. ?>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpsl_stores&page=wpsl_marker_studio' ) . '#library' ); ?>" class="button"><?php esc_html_e( 'Manage Markers', 'wp-store-locator' ); ?></a>
        </p>
    </div>
</section>