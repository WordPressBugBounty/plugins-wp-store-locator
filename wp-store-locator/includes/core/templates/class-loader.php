<?php
/**
 * Handle the loading of the WPSL and Add-on templates
 *
 * @author Tijmen Smit
 * @since  2.2.11
 */

namespace WPSL\Core\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loader {

    /**
     * Get the list of available templates
     *
     * @since  2.2.11
     * @param  string $type The template type to return
     * @return array|void
     */
    public function get_list( $type = 'store_locator' ) {
        $template_list = [];

        // Add the WPSL templates or the add-on templates.
        if ( $type == 'store_locator' ) {
            $template_list['store_locator'] = wpsl_get_templates();
        } else {
            $template_list = apply_filters( 'wpsl_template_list', $template_list );
        }

        if ( isset( $template_list[$type] ) && ! empty( $template_list[$type] ) ) {
            return $template_list[$type];
        }
    }

    /**
     * Get the template details
     *
     * @since  2.2.11
     * @param  string $used_template The name of the template
     * @param  string $type          The type of template data to load
     * @return array  $template_data The template data ( id, name, path )
     */
    public function get_details( $used_template, $type = 'store_locator' ) {
        $used_template = ( empty( $used_template ) ) ? 'default' : $used_template;
        
        // Backward compatibility: convert old template IDs to new ones
        $legacy_template_map = [
            'below_map' => 'horizontal'
        ];
        
        if ( isset( $legacy_template_map[ $used_template ] ) ) {
            $used_template = $legacy_template_map[ $used_template ];
        }
        
        $templates     = $this->get_list( $type );
        $template_data = '';
        $template_path = '';

        if ( $templates ) {
            // Grab the the correct template data from the available templates.
            foreach ( $templates as $template ) {
                if ( $used_template == $template['id'] ) {
                    $template_data = $template;
                    break;
                }
            }
        }

        // Old structure ( WPSL only ) was only the path, new structure ( add-ons ) expects the file name as well.
        if ( isset( $template_data['path'] ) && isset( $template_data['file_name'] ) ) {
            $template_path = $template_data['path'] . $template_data['file_name'];
        } else if ( isset( $template_data['path'] ) ) {
            $template_path = $template_data['path'];
        }

        // If no match exists, or the template file doesnt exist, then use the default template.
        if ( ! $template_data || ( ! file_exists( $template_path ) ) ) {
            $template_data = $this->get_template( $type );

            // If no template can be loaded, then show a msg to the admin user.
            if ( ! $template_data && current_user_can( 'administrator' ) ) {
                /* translators: %s: template type */
                echo '<p>' . sprintf( esc_html__( 'No template found for %s', 'wp-store-locator' ), esc_html( $type ) ) . '</p>';
                /* translators: 1: opening code tag, 2: closing code tag */
                echo '<p>' . sprintf( esc_html__( 'Make sure you call the %1$sget_template_details%2$s function with the correct parameters.', 'wp-store-locator' ), '<code>', '</code>' ) . '</p>';
            }
        }

        return $template_data;
    }

    /**
     * Locate the default template
     *
     * @since 2.2.11
     * @param string $type    The type of default template to return
     * @return array $default The default template data
     */
    public function get_template( $type = 'store_locator' ) {
        $template_list = $this->get_list( $type );
        $default       = '';

        if ( $template_list ) {
            foreach ( $template_list as $template ) {
                if ( $template['id'] == 'default' ) {
                    $default = $template;
                    break;
                }
            }
        }

        return $default;
    }

    public function get_template_list() {
        _deprecated_function( __FUNCTION__, '3.0.0', '$wpsl_get_service( "template_loader" )->get_list()' );
    }

    public function get_template_details() {
        _deprecated_function( __FUNCTION__, '3.0.0', '$wpsl_get_service( "template_loader" )->get_details()' );
    }

    public function get_default_template() {
        _deprecated_function( __FUNCTION__, '3.0.0', '$wpsl_get_service( "template_loader" )->get_default()' );
    }

    /**
     * Locate the template file in either the
     * theme folder or the plugin folder itself.
     *
     * @since 2.2.11
     * @param  array  $args     The template data
     * @return string $template The path to the template.
     */
    public function find_template_path( $args ) {
        // Look for the template in the theme folder.
        $template = locate_template(
            [ trailingslashit( 'wpsl-templates' ) . $args['file_name'], $args['file_name'] ]
        );

        // If the template doesn't exist in the theme folder load the one from the plugin dir.
        if ( ! $template ) {
            $template = $args['path'] . $args['file_name'];
        }

        return $template;
    }
}