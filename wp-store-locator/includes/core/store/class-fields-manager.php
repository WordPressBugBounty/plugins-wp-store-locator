<?php
/**
 * Store Fields Manager
 *
 * Manages the field definitions for store locations.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WPSL\Core\Settings\Manager as Settings;

class Fields_Manager {

    /**
     * Settings manager instance.
     *
     * @since 3.0.0
     * @var \WPSL\Core\Settings\Manager
     */
    private $settings;

    /**
     * Constructor
     *
     * @since 3.0.0
     * @param Settings $settings Settings manager instance
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Get the meta box fields.
     *
     * @since  3.0.0
     * @param  array $args Arguments to filter the fields
     * @return array The meta box fields
     */
    public function get_fields( $args = [ 'only_defaults' => false, 'only_custom' => false ] ) {
        $wpsl_settings = $this->settings->get_group( 'editor' );

        // If only_custom is true, return only custom fields
        if ( isset( $args['only_custom'] ) && $args['only_custom'] ) {
            return $this->get_custom_field_names();
        }

        $meta_fields = [
            esc_html__( 'Location', 'wp-store-locator' ) => [
                'address' => [
                    'label'    => esc_html__( 'Address', 'wp-store-locator' ),
                    'required' => true
                ],
                'address2' => [
                    'label' => esc_html__( 'Address 2', 'wp-store-locator' )
                ],
                'city' => [
                    'label'    => esc_html__( 'City', 'wp-store-locator' ),
                    'required' => true
                ],
                'state' => [
                    'label' => esc_html__( 'State', 'wp-store-locator' )
                ],
                'zip' => [
                    'label' => esc_html__( 'Zip Code', 'wp-store-locator' )
                ],
                'country' => [
                    'label' => esc_html__( 'Country', 'wp-store-locator' )
                ],
                'country_iso' => [
                    'type' => 'hidden'
                ],
                'lat' => [
                    'label' => esc_html__( 'Latitude', 'wp-store-locator' )
                ],
                'lng' => [
                    'label' => esc_html__( 'Longitude', 'wp-store-locator' )
                ]
            ],
            esc_html__( 'Opening Hours', 'wp-store-locator' ) => [
                'hours' => [
                    'label' => esc_html__( 'Hours', 'wp-store-locator' ),
                    'type'  => $wpsl_settings['hour_input'] //Either set to textarea or dropdown. This is defined through the 'Opening hours input format: ' option on the settings page
                ],
                'timezone' => [
                    'label' => esc_html__( 'Timezone', 'wp-store-locator' ),
                    'type'  => 'timezone'
                ]
            ],
            esc_html__( 'Additional Information', 'wp-store-locator' ) => [
                'phone' => [
                    'label' => esc_html__( 'Tel', 'wp-store-locator' ),
                    'type'  => 'tel'
                ],
                'fax' => [
                    'label' => esc_html__( 'Fax', 'wp-store-locator' ),
                    'type'  => 'tel'
                ],
                'email' => [
                    'label' => esc_html__( 'Email', 'wp-store-locator' ),
                    'type'  => 'email'
                ],
                'url' => [
                    'label' => esc_html__( 'Url', 'wp-store-locator' ),
                    'type'  => 'url'
                ]
            ]
        ];

        if ( $wpsl_settings['enable_online_only'] ) {
            $meta_fields[ esc_html__( 'Location', 'wp-store-locator' ) ]['online'] = [
                'label' => esc_html__( 'Online only', 'wp-store-locator' ),
                'type'  => 'checkbox'
            ];
        }

        // Optionally include data from the fields manager.
        if ( isset( $args['only_defaults'] ) && ! $args['only_defaults'] ) {
            $meta_fields = $this->get_custom_field_names( $meta_fields );
        }

        return apply_filters( 'wpsl_meta_box_fields', $meta_fields );
    }

    /**
     * Get the custom field names, and optionally merge them with the default meta fields.
     *
     * @since  3.0.0
     * @param  array $meta_fields Optionally the existing meta fields
     * @param  bool  $only_custom Whether to return only custom fields
     * @return array The meta fields with custom fields added
     */
    public function get_custom_field_names( $meta_fields = [], $only_custom = false ) {
        $wpsl_settings = $this->settings->get_group( 'editor' );
        
        $field_manager = [];

        if ( isset( $wpsl_settings['field_manager']['groups'] ) && is_array( $wpsl_settings['field_manager']['groups'] ) && ! empty( $wpsl_settings['field_manager']['groups'] ) ) {
            foreach ( $wpsl_settings['field_manager']['groups'] as $group_id => $group_name ) {
                if ( isset( $wpsl_settings['field_manager']['fields'][$group_id] ) && ! empty( $wpsl_settings['field_manager']['fields'][$group_id] ) ) {
                    $fields = $wpsl_settings['field_manager']['fields'][$group_id];
                    $group_fields = [];

                    foreach ( $fields as $field_id => $field_names ) {
                        if ( isset( $field_names['name'] ) && $field_names['name'] ) {
                            $group_fields[ $field_names['name'] ] = [
                                'label'   => $field_names['label'],
                                'type'    => $field_names['type'],
                                'default' => $field_names['default']
                            ];

                            if ( isset( $field_names['required'] ) ) {
                                $group_fields[ $field_names['name'] ]['required'] = true;
                            }

                            if ( $field_names['type'] == 'dropdown' ) {
                                $options = explode("\n", $field_names['options'] );
                                $list = [];

                                /**
                                 * Convert each line from the textarea input into
                                 * individual <option> values.
                                 */
                                foreach ( $options as $key => $option ) {
                                    $list[ strtolower( wpsl_alphanum_no_space( $option ) ) ] = $option;
                                }

                                $group_fields[ $field_names['name'] ]['options'] = $list;
                            }
                        }
                    }

                    $field_manager[$group_name] = $group_fields;
                }
            }
        }

        // Merge the default fields with the field manager fields.
        if ( ! empty( $field_manager ) ) {
            if ( $only_custom ) {
                $meta_fields = $field_manager;
            } else {
                $meta_fields = array_merge( $meta_fields, $field_manager );
            }
        }

        return $meta_fields;
    }
}