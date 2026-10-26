<?php
/**
 * Social media link helpers: the supported platforms and their icons.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The built-in social media platforms a location can link to.
 *
 * The generic "other" platform is not in this list, it has an icon but no
 * brand label or color.
 *
 * @since  3.1.1
 * @return array $platforms The platforms as slug => [ 'label' => string, 'color' => '#RRGGBB' ]
 */
function wpsl_social_platforms() {
    $platforms = [
        'facebook'    => [ 'label' => 'Facebook',    'color' => '#0866FF' ],
        'instagram'   => [ 'label' => 'Instagram',   'color' => '#FF0069' ],
        'x'           => [ 'label' => 'X',           'color' => '#000000' ],
        'linkedin'    => [ 'label' => 'LinkedIn',    'color' => '#0A66C2' ],
        'youtube'     => [ 'label' => 'YouTube',     'color' => '#FF0000' ],
        'tiktok'      => [ 'label' => 'TikTok',      'color' => '#000000' ],
        'pinterest'   => [ 'label' => 'Pinterest',   'color' => '#BD081C' ],
        'whatsapp'    => [ 'label' => 'WhatsApp',    'color' => '#25D366' ],
        'yelp'        => [ 'label' => 'Yelp',        'color' => '#FF1A1A' ],
        'tripadvisor' => [ 'label' => 'Tripadvisor', 'color' => '#34E0A1' ],
    ];

    /**
     * Filter the social media platforms.
     *
     * @since 3.1.1
     * @param array $platforms The platforms as slug => [ 'label' => string, 'color' => '#RRGGBB' ]
     */
    return apply_filters( 'wpsl_social_platforms', $platforms );
}

/**
 * Get the icon of a social media platform as an inline svg.
 *
 * The brand icons are filled with the brand color, the generic "other" icon
 * is an outline that follows the text color. A platform without an icon of
 * its own ( one added with the wpsl_social_platforms filter, or an imported
 * one ) gets the generic icon, so its link is never empty.
 *
 * @since  3.1.1
 * @param  string $platform The platform slug, or 'other'
 * @return string $svg      The svg element
 */
function wpsl_social_icon_svg( $platform ) {
    static $icons = null;

    if ( null === $icons ) {
        $icons = require WPSL_PLUGIN_DIR . 'includes/core/social/social-icons.php';
    }

    if ( ! is_string( $platform ) || ! isset( $icons[ $platform ] ) ) {
        $platform = 'other';
    }

    $icon = $icons[ $platform ];

    if ( 'other' === $platform ) {
        $paint = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    } else {
        $platforms = wpsl_social_platforms();
        $color     = isset( $platforms[ $platform ]['color'] ) ? $platforms[ $platform ]['color'] : 'currentColor';
        $paint     = 'fill="' . esc_attr( $color ) . '"';
    }

    // The CSS sets the size, the width and height keep the icon small when the stylesheet is missing or late.
    return '<svg class="wpsl-social-icon wpsl-social-icon-' . esc_attr( $platform ) . '" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="' . esc_attr( $icon['viewbox'] ) . '" ' . $paint . ' aria-hidden="true" focusable="false">' . $icon['markup'] . '</svg>';
}

/**
 * The social media fields that are set up when the social media group is first enabled.
 *
 * @since  3.1.1
 * @return array $fields The fields as a list of [ 'platform', 'label', 'name', 'css_class' ]
 */
function wpsl_social_default_fields() {
    $platforms = wpsl_social_platforms();
    $fields    = [];

    foreach ( [ 'x', 'youtube', 'instagram', 'facebook' ] as $platform ) {
        $fields[] = [
            'platform'  => $platform,
            'label'     => isset( $platforms[ $platform ]['label'] ) ? $platforms[ $platform ]['label'] : $platform,
            'name'      => wpsl_social_field_name( $platform, '' ),
            'css_class' => '',
        ];
    }

    return $fields;
}

/**
 * The name of the social media group in the store editor.
 *
 * @since  3.1.1
 * @return string The group label
 */
function wpsl_social_group_label() {
    return __( 'Social Media', 'wp-store-locator' );
}

/**
 * The field names a social media field can't use.
 *
 * A social media field name is a variable in the search results and info
 * window templates, where the value is printed as a link. So it can't be the
 * name of a store field or of any other variable the templates already get,
 * and it has to be a name that is safe to use in JavaScript.
 *
 * @since  3.1.1
 * @return array $names The reserved names, in lower case
 */
function wpsl_social_reserved_names() {
    return [
        // The store fields.
        'address', 'address2', 'city', 'state', 'zip', 'country', 'country_iso', 'lat', 'lng', 'hours', 'timezone', 'phone', 'fax', 'email', 'url', 'online',

        // The other variables the templates get.
        'id', 'store', 'permalink', 'thumb', 'distance', 'distance_short', 'distance_unit', 'description', 'categories', 'hours_raw', 'hours_status', 'location_status', 'featured', 'status', 'mapshortcode', 'number_results',

        // Used by the compiled underscore templates, and the helpers the templates call.
        'obj', 'print', '_', '__p', '__t', '__j', '__e', 'formatphonenumber', 'formatemail', 'createdirectionurl', 'createinfowindowactions',

        // The properties every JavaScript object has.
        '__proto__', 'constructor', 'prototype', 'hasownproperty', 'isprototypeof', 'propertyisenumerable', 'tolocalestring', 'tostring', 'valueof', '__definegetter__', '__definesetter__', '__lookupgetter__', '__lookupsetter__',

        // Globals that a template variable can fall back on when the store data has no value for it.
        'window', 'document', 'location', 'name', 'top', 'parent', 'self', 'history', 'screen', 'navigator', 'event', 'frames', 'length', 'origin', 'opener', 'closed', 'console', 'alert', 'confirm', 'prompt', 'open', 'close', 'stop', 'focus', 'blur', 'fetch', 'crypto', 'performance', 'external', 'jquery', 'undefined', 'nan', 'infinity',

        // The JavaScript reserved words.
        'break', 'case', 'catch', 'class', 'const', 'continue', 'debugger', 'default', 'delete', 'do', 'else', 'enum', 'export', 'extends', 'false', 'finally', 'for', 'function', 'if', 'implements', 'import', 'in', 'instanceof', 'interface', 'let', 'new', 'null', 'package', 'private', 'protected', 'public', 'return', 'static', 'super', 'switch', 'this', 'throw', 'true', 'try', 'typeof', 'var', 'void', 'while', 'with', 'yield', 'await', 'async', 'arguments', 'eval',
    ];
}

/**
 * Get the configured social media fields.
 *
 * A field is left out when its name is not safe to use ( a reserved name, a
 * settings import can write those without the sanitizer ), or when a field of
 * the Fields Manager already has that name. So the links, the front end data
 * and the [wpsl_address] shortcode only use names that are registered as social
 * media url fields.
 *
 * @since  3.1.1
 * @return array The fields as a list of [ 'platform', 'label', 'name', 'css_class' ], empty when the social media option is off
 */
function wpsl_get_social_fields() {
    $editor = wpsl_get_service( 'wpsl_settings' )->get_group( 'editor' );

    if ( empty( $editor['social_media'] ) || empty( $editor['social_fields'] ) || ! is_array( $editor['social_fields'] ) ) {
        return [];
    }

    // The names of the fields the user added with the Fields Manager.
    $user_names = [];

    if ( ! empty( $editor['field_manager']['groups'] ) && is_array( $editor['field_manager']['groups'] ) && ! empty( $editor['field_manager']['fields'] ) && is_array( $editor['field_manager']['fields'] ) ) {
        foreach ( array_keys( $editor['field_manager']['groups'] ) as $group_id ) {
            if ( empty( $editor['field_manager']['fields'][ $group_id ] ) || ! is_array( $editor['field_manager']['fields'][ $group_id ] ) ) {
                continue;
            }

            foreach ( $editor['field_manager']['fields'][ $group_id ] as $user_field ) {
                if ( ! empty( $user_field['name'] ) && is_string( $user_field['name'] ) ) {
                    $user_names[] = $user_field['name'];
                }
            }
        }
    }

    $reserved = wpsl_social_reserved_names();
    $fields   = [];

    foreach ( $editor['social_fields'] as $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) || ! is_string( $field['name'] ) ) {
            continue;
        }

        if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $field['name'] ) || in_array( strtolower( $field['name'] ), $reserved, true ) || in_array( $field['name'], $user_names, true ) ) {
            continue;
        }

        $fields[] = $field;
    }

    return $fields;
}

/**
 * Get the field name of a social media field.
 *
 * A built-in platform uses '{platform}_url'. The generic "other" platform
 * derives the name from its label, using the Fields Manager rules with hyphens turned into underscores.
 * A label without any ASCII letters or digits ( ВКонтакте, 微博 ) gets
 * 'social_' and the first 8 characters of the md5 hash of the label.
 *
 * @since  3.1.1
 * @param  string $platform The platform slug, or 'other'
 * @param  string $label    The label of the field, only used for 'other'
 * @return string $name     The field name, the post meta key is this name with a 'wpsl_' prefix
 */
function wpsl_social_field_name( $platform, $label ) {
    if ( 'other' !== $platform ) {
        return $platform . '_url';
    }

    // Hyphens become underscores, the name is used as a variable name in the templates.
    $name = str_replace( '-', '_', strtolower( wpsl_alphanum_no_space( $label ) ) );

    // Field names are used as JS variable names in templates, so they can't start with a digit.
    if ( preg_match( '/^[0-9]/', $name ) ) {
        $name = 'field_' . $name;
    }

    // Nothing is left of a label in another script, it gets a stable name from its hash.
    if ( '' === $name && is_string( $label ) && '' !== trim( $label ) ) {
        $name = 'social_' . substr( md5( $label ), 0, 8 );
    }

    return $name;
}

/**
 * Get the field names a social media field can't use.
 *
 * These are the names of the core fields, the Fields Manager treats them as
 * protected, and the names of the custom fields in the given Fields Manager
 * settings. Used by the settings form and the settings import.
 *
 * @since  3.1.1
 * @param  array $field_manager The Fields Manager groups and fields
 * @return array $names         The field names
 */
function wpsl_social_taken_names( $field_manager ) {
    $names  = [];
    $groups = wpsl_get_service( 'store_fields' )->get_fields( [ 'only_defaults' => true ] );

    foreach ( $groups as $fields ) {
        if ( is_array( $fields ) ) {
            $names = array_merge( $names, array_keys( $fields ) );
        }
    }

    if ( ! is_array( $field_manager ) || empty( $field_manager['fields'] ) || ! is_array( $field_manager['fields'] ) ) {
        return $names;
    }

    foreach ( $field_manager['fields'] as $group_fields ) {
        if ( ! is_array( $group_fields ) ) {
            continue;
        }

        foreach ( $group_fields as $field ) {
            if ( is_array( $field ) && ! empty( $field['name'] ) && is_string( $field['name'] ) ) {
                $names[] = $field['name'];
            }
        }
    }

    return $names;
}

/**
 * Sanitize the submitted social media fields.
 *
 * Unknown platforms, a platform that is used twice, an "other" field without
 * a label and fields with a name that is already in use are dropped. All but
 * the unknown platforms are reported with an error code.
 *
 * An "other" field gets its name from its label when it is added. After that
 * the name stays, the stored links use it as their meta key: a row that posts
 * the name of a stored "other" field keeps that name whatever its label
 * becomes. A posted name that is not one of the stored "other" names is
 * ignored.
 *
 * @since  3.1.1
 * @param  array $input         The submitted fields
 * @param  array $taken_names   The names that can't be used, the core fields and the custom fields
 * @param  array $stored_fields The stored social media fields, their "other" names can be kept
 * @return array $result        [ 'fields' => the sanitized fields as a list, 'errors' => the validation error codes ]
 */
function wpsl_sanitize_social_fields( $input, $taken_names, $stored_fields = [] ) {
    $platforms = wpsl_social_platforms();
    $fields    = [];
    $errors    = [];
    $names     = [];

    // Every built-in platform name is reserved for that platform.
    $reserved = [];

    foreach ( array_keys( $platforms ) as $slug ) {
        $reserved[] = wpsl_social_field_name( $slug, '' );
    }

    $taken_names = is_array( $taken_names ) ? array_map( 'strval', $taken_names ) : [];
    $taken_names = array_merge( $taken_names, wpsl_social_reserved_names() );

    if ( ! is_array( $input ) ) {
        $input = [];
    }

    // The names of the stored "other" fields.
    $stored_names = [];

    if ( is_array( $stored_fields ) ) {
        foreach ( $stored_fields as $stored_field ) {
            if ( is_array( $stored_field ) && isset( $stored_field['platform'], $stored_field['name'] ) && 'other' === $stored_field['platform'] && is_string( $stored_field['name'] ) && preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $stored_field['name'] ) ) {
                $stored_names[] = $stored_field['name'];
            }
        }
    }

    // The stored names the submitted rows keep, a new field can't take one of them.
    $kept_names = [];

    foreach ( $input as $row ) {
        $posted_name = wpsl_social_posted_name( $row, $stored_names );

        if ( '' !== $posted_name ) {
            $kept_names[] = $posted_name;
        }
    }

    foreach ( $input as $row ) {
        if ( ! is_array( $row ) || ! isset( $row['platform'] ) || ! is_string( $row['platform'] ) ) {
            continue;
        }

        $platform = sanitize_text_field( $row['platform'] );

        if ( 'other' !== $platform && ! isset( $platforms[ $platform ] ) ) {
            continue;
        }

        if ( 'other' === $platform ) {
            $label = '';

            if ( isset( $row['label'] ) && is_scalar( $row['label'] ) ) {
                $label = mb_substr( sanitize_text_field( wp_specialchars_decode( (string) $row['label'], ENT_QUOTES ) ), 0, 50 );
            }

            if ( '' === trim( $label ) ) {
                $errors[] = 'social_field_label_empty';
                continue;
            }

            $name = wpsl_social_posted_name( $row, $stored_names );

            // A new field, its name comes from the label.
            if ( '' === $name ) {
                $name = wpsl_social_field_name( 'other', $label );

                if ( '' === $name ) {
                    continue;
                }

                if ( in_array( $name, $kept_names, true ) ) {
                    $errors[] = 'social_platform_duplicate';
                    continue;
                }
            }

            if ( in_array( $name, $reserved, true ) || in_array( $name, $names, true ) ) {
                $errors[] = 'social_platform_duplicate';
                continue;
            }
        } else {
            $label = isset( $platforms[ $platform ]['label'] ) ? $platforms[ $platform ]['label'] : $platform;
            $name  = wpsl_social_field_name( $platform, $label );

            if ( in_array( $name, $names, true ) ) {
                $errors[] = 'social_platform_duplicate';
                continue;
            }
        }

        if ( in_array( $name, $taken_names, true ) ) {
            $errors[] = 'social_field_name_taken';
            continue;
        }

        $css_classes = [];

        if ( isset( $row['css_class'] ) && is_string( $row['css_class'] ) ) {
            foreach ( preg_split( '/\s+/', trim( $row['css_class'] ) ) as $class ) {
                $class = sanitize_html_class( $class );

                if ( '' !== $class ) {
                    $css_classes[] = $class;
                }
            }
        }

        $names[]  = $name;
        $fields[] = [
            'platform'  => $platform,
            'label'     => $label,
            'name'      => $name,
            'css_class' => implode( ' ', $css_classes ),
        ];
    }

    return [
        'fields' => $fields,
        'errors' => array_values( array_unique( $errors ) ),
    ];
}

/**
 * Get the stored name a submitted "other" row keeps.
 *
 * @since  3.1.1
 * @param  mixed  $row          The submitted row
 * @param  array  $stored_names The names of the stored "other" fields
 * @return string $name         The posted name when it is one of the stored names, otherwise an empty string
 */
function wpsl_social_posted_name( $row, $stored_names ) {
    if ( ! is_array( $row ) || ! isset( $row['platform'], $row['name'] ) || 'other' !== $row['platform'] || ! is_string( $row['name'] ) ) {
        return '';
    }

    return in_array( $row['name'], $stored_names, true ) ? $row['name'] : '';
}

/**
 * Get the markup of a single social media link.
 *
 * The href is printed exactly as given, so the caller has to escape it. This
 * lets the same markup serve a PHP value ( esc_url() ) and an underscore
 * template expression ( '<%= facebook_url %>' ).
 *
 * @since  3.1.1
 * @param  array  $field     The field as [ 'platform', 'label', 'name', 'css_class' ]
 * @param  string $href_html The escaped href, or a template expression that outputs one
 * @return string $link      The anchor element
 */
function wpsl_social_link_markup( $field, $href_html ) {
    $platform = isset( $field['platform'] ) && is_string( $field['platform'] ) ? $field['platform'] : '';
    $label    = isset( $field['label'] ) && is_scalar( $field['label'] ) ? (string) $field['label'] : '';

    // The link needs an accessible name, an imported field can be without a label.
    if ( '' === trim( $label ) ) {
        $platforms = wpsl_social_platforms();

        if ( isset( $platforms[ $platform ]['label'] ) && is_scalar( $platforms[ $platform ]['label'] ) && '' !== trim( (string) $platforms[ $platform ]['label'] ) ) {
            $label = (string) $platforms[ $platform ]['label'];
        } elseif ( isset( $field['name'] ) && is_string( $field['name'] ) ) {
            $label = $field['name'];
        }
    }

    // The class is cleaned again here, a settings import can write the fields without running the sanitizer.
    $css_classes = [];

    if ( isset( $field['css_class'] ) && is_string( $field['css_class'] ) ) {
        foreach ( preg_split( '/\s+/', trim( $field['css_class'] ) ) as $class ) {
            $class = sanitize_html_class( $class );

            if ( '' !== $class ) {
                $css_classes[] = $class;
            }
        }
    }

    $css_class = implode( ' ', $css_classes );
    $classes   = 'wpsl-social-link wpsl-social-' . sanitize_html_class( $platform );

    if ( 'other' === $platform && '' !== $css_class ) {
        $icon = '<span class="' . esc_attr( $css_class ) . '" aria-hidden="true"></span>';
    } else {
        $icon = wpsl_social_icon_svg( $platform );

        if ( '' !== $css_class ) {
            $classes .= ' ' . $css_class;
        }
    }

    return '<a class="' . esc_attr( $classes ) . '" href="' . $href_html . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '">' . $icon . '</a>';
}

/**
 * Get the row of social media links of a location.
 *
 * Only the links with a value that survives esc_url() are shown, a location
 * without any gets no row.
 *
 * @since  3.1.1
 * @param  int    $store_id The location ID
 * @return string $html     The row, or an empty string when there is nothing to show
 */
function wpsl_social_links_html( $store_id ) {
    $links = '';

    foreach ( wpsl_get_social_fields() as $field ) {
        if ( empty( $field['name'] ) || ! is_string( $field['name'] ) ) {
            continue;
        }

        $value = get_post_meta( $store_id, 'wpsl_' . $field['name'], true );
        $href  = is_string( $value ) ? esc_url( $value ) : '';

        if ( '' !== $href ) {
            $links .= wpsl_social_link_markup( $field, $href );
        }
    }

    if ( '' === $links ) {
        return '';
    }

    return '<div class="wpsl-social-links">' . $links . '</div>';
}

/**
 * Get the row of social media links as an underscore template fragment.
 *
 * Used in the search results, the info windows and by the {{wpsl_social_links}}
 * template tag. The values are escaped with esc_url() on the server, like the
 * url field is, so they are printed with <%= %>.
 *
 * The values are read as obj.{name}, and only when the store data has the key
 * itself. A bare name would fall back to a global of the page when the data
 * has no such key, for example an element with that id, which a post author
 * can add to the content.
 *
 * @since  3.1.1
 * @param  array  $args     The section arguments, not used
 * @return string $template The fragment, or an empty string when there are no social media fields
 */
function wpsl_social_links( $args = [] ) {
    $conditions = [];
    $links      = '';

    foreach ( wpsl_get_social_fields() as $field ) {
        // The name is used as a variable name in the template.
        if ( empty( $field['name'] ) || ! is_string( $field['name'] ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $field['name'] ) ) {
            continue;
        }

        $value        = 'obj.' . $field['name'];
        $defined      = 'Object.prototype.hasOwnProperty.call( obj, "' . $field['name'] . '" ) && ' . $value;
        $conditions[] = '( ' . $defined . ' )';
        $links       .= '<% if ( ' . $defined . ' ) { %>' . wpsl_social_link_markup( $field, '<%= ' . $value . ' %>' ) . '<% } %>';
    }

    if ( ! $conditions ) {
        return '';
    }

    return '<% if ( ' . implode( ' || ', $conditions ) . ' ) { %><div class="wpsl-social-links">' . $links . '</div><% } %>';
}
