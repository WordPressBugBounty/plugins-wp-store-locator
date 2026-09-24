<?php
/**
 * API key validation and diagnostic hints.
 *
 * Part of the wpsl-functions API ( see wpsl-functions.php ).
 *
 * @package WPSL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Turn a WP_Error from a failed API request into a consistent,
 * human-readable status string.
 *
 * @since 3.0.0
 * @param  \WP_Error $error The error returned by wp_remote_*().
 * @return string           A non-empty status string.
 */
function wpsl_format_wp_error_status( $error ) {
    $message = $error->get_error_message();

    if ( ! $message ) {
        $message = esc_html__( 'No further details are available.', 'wp-store-locator' );
    }

    /* translators: %s: error message */
    return sprintf( esc_html__( 'Request failed. %s', 'wp-store-locator' ), $message );
}

/**
 * Build a hint for a request that failed because it timed out, or
 * otherwise never reached the API ( DNS failure, connection refused ).
 *
 * Shared by the Gmaps / Mapbox / Stadia / Openrouteservice key checks so a
 * slow or unreachable API explains itself the same way everywhere, instead
 * of showing a bare technical cURL string.
 *
 * @since 3.0.0
 * @param  string $status The error status string ( may contain the reason ).
 * @return string         A hint paragraph, or an empty string if this isn't a connection failure.
 */
function wpsl_get_timeout_error_hint( $status ) {
    if ( stripos( $status, 'timed out' ) === false
        && stripos( $status, 'could not resolve' ) === false
        && stripos( $status, 'connection refused' ) === false
    ) {
        return '';
    }

    return '<p>' . esc_html__( 'This usually means the request to the API took too long to complete, or your server could not reach it. This is often a temporary network issue — please try again in a moment.', 'wp-store-locator' ) . '</p>';
}

/**
 * Check the status of the used OpenRouteService key.
 *
 * @since  3.0.0
 * @param  string $key_value The OpenRouteService API key value to test
 * @return string $status    The returned status of the API request
 */
function wpsl_check_openrouteservice_key_status( $key_value ) {
    $args = [
        'api_key' => $key_value,
        'start'   => '8.681495,49.41461',
        'end'     => '8.687872,49.420318'
    ];

    $response = wpsl_call_openrouteservice_api( $args );

    return $response;
}

/**
 * Check the status of the used Google Maps server key.
 *
 * @since  3.0.0
 * @param  string $key_value The Google Maps API key value to test
 * @return string $status    The returned status of the API request
 */
function wpsl_check_gmaps_key_status( $key_value = '' ) {
    if ( ! $key_value ) {
        $key_value = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'gmaps_server_key' );
    }

    $args = [
        'api_key' => $key_value,
        'force_map_service' => 'gmaps'
    ];

    $response = wpsl_call_geocode_api( 'Manhattan, NY 10036, USA', 'gmaps', $args );

    // Network error, DNS failure, etc.
    if ( is_wp_error( $response ) ) {
        $status = wpsl_format_wp_error_status( $response );
    } else {
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code === 200 ) {
            $decoded_response = json_decode( $body, true );

            if ( is_array( $decoded_response ) && isset( $decoded_response['results'] ) ) {
                $status = ! empty( $decoded_response['results'] ) ? 'OK' : 'ZERO_RESULTS';

                // Clear any previously stored console error now the key works.
                delete_transient( 'wpsl_gmaps_key_error_details' );
            } else {
                $status = esc_html__( 'Invalid API response format', 'wp-store-locator' );
            }
        } else {
            $decoded_error = json_decode( $body, true );

            // Use the human-readable message from Google as the reason, e.g.
            // "Requests from referer <empty> are blocked." or an IP restriction message.
            $reason = isset( $decoded_error['error']['message'] ) ? $decoded_error['error']['message'] : wp_remote_retrieve_response_message( $response );

            // Store the full detail for the browser console.
            set_transient( 'wpsl_gmaps_key_error_details', $code . ': ' . $reason, MINUTE_IN_SECONDS );

            // Plain two-line status. The display layer escapes it and turns the
            // newline into a <br> for the notice; the status report shows it as-is.
            /* translators: 1: HTTP status code, 2: error reason from the API */
            $status = sprintf( __( 'Error code: %1$s', 'wp-store-locator' ), $code ) . "\n" . sprintf( __( 'Reason: %1$s', 'wp-store-locator' ), $reason );
        }
    }

    return $status;
}

/**
 * Check if the Mapbox API key is valid.
 *
 * @since  3.0.0
 * @param  string $key_value The Mapbox API key value to test
 * @return string $status    The returned status of the API request
 */
function wpsl_check_mapbox_key_status( $key_value = '') {
    if ( ! $key_value ) {
        $key_value = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'mapbox_key' );
    }

    $args = [
        'access_token' => $key_value,
        'force_mapbox_geocoder' => true, // Makes sure we use Mapbox and not the Nomination API in case this is selected on the WPSL settings page.
        'permanent' => false
    ];

    $response = wpsl_call_geocode_api( 'Manhattan, NY 10036, USA', 'mapbox', $args );

    if ( is_wp_error( $response ) ) {
        $status = wpsl_format_wp_error_status( $response );
    } else {
        $code = $response['response']['code'];

        if ( $code !== 200 ) {
            $body           = wp_remote_retrieve_body( $response );
            $http_message   = wp_remote_retrieve_response_message( $response );
            $decoded_error  = json_decode( $body, true );

            if ( isset( $decoded_error['message'] ) ) {
                $reason = $decoded_error['message'];
            } elseif ( isset( $decoded_error['error'] ) && is_string( $decoded_error['error'] ) ) {
                $reason = $decoded_error['error'];
            } else {
                $reason = $http_message ? $http_message : $body;
            }

            // Plain two-line status, matching the Google Maps key error format.
            // The display layer escapes it and turns the newline into a <br>.
            /* translators: 1: HTTP status code, 2: error reason from the API */
            $status = sprintf( __( 'Error code: %1$s', 'wp-store-locator' ), $code ) . "\n" . sprintf( __( 'Reason: %1$s', 'wp-store-locator' ), $reason );
        } else {
            $status = 'OK';
        }
    }

    return $status;
}

/**
 * Check if the Stadia Maps API key is valid.
 *
 * @since  3.0.0
 * @param  string $key_value The Stadia Maps API key value to test
 * @return string $status    The returned status of the API request
 */
function wpsl_check_stadia_key_status( $key_value = '' ) {
    if ( ! $key_value ) {
        $key_value = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'stadia_key' );
    }

    $key_value = trim( (string) $key_value );

    if ( ! $key_value ) {
        return esc_html__( 'No API key provided.', 'wp-store-locator' );
    }

    // Stadia only authenticates the last 36 characters of the api_key parameter,
    // so a key with anything in front of it ( a stray character from a bad paste )
    // still comes back 200 and would be reported as valid. Their keys are UUIDs,
    // so check the format ourselves before making the request.
    if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key_value ) ) {
        return esc_html__( 'The API key is not in the expected format. A Stadia Maps API key looks like 123e4567-e89b-12d3-a456-426614174000.', 'wp-store-locator' );
    }

    $url = wpsl_stadia_api_base_url( 'tiles' ) . '/tiles/alidade_smooth/0/0/0.png?api_key=' . urlencode( $key_value );

    $response = wp_remote_request( $url, [
        'method'  => 'HEAD',
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return wpsl_format_wp_error_status( $response );
    }

    $code = wp_remote_retrieve_response_code( $response );

    if ( $code === 200 ) {
        return 'OK';
    }

    $http_message = wp_remote_retrieve_response_message( $response );
    $reason       = $http_message ? $http_message : $code;

    /* translators: 1: HTTP status code, 2: error reason from the API */
    return sprintf( __( 'Error code: %1$s', 'wp-store-locator' ), $code ) . "\n" . sprintf( __( 'Reason: %1$s', 'wp-store-locator' ), $reason );
}

/**
 * Where a Stadia Maps plan is changed.
 *
 * One home for it, so the settings-page key notice and the frontend notice
 * built in JS cannot end up pointing at different pages. The frontend gets it
 * through the localized labels.
 *
 * @since  3.0.0
 * @return string The Stadia Maps subscription page.
 */
function wpsl_stadia_account_url() {
    return 'https://client.stadiamaps.com/dashboard/account/subscription-details/';
}

/**
 * Whether the active map service can turn coordinates back into an address.
 *
 * Only Stadia can answer no: it sells the reverse endpoint separately, and a
 * key that passes validation may still be refused there. The answer is recorded
 * when the key is validated, so nothing here makes a request.
 *
 * Callers use this to decide whether a feature that depends on reverse
 * geocoding can be offered at all - dragging the onboarding start marker only
 * makes sense if the drop can be named.
 *
 * @since  3.0.0
 * @param  string $map_service The map service to ask about.
 * @return bool   False only when the service is known to refuse it.
 */
function wpsl_map_service_can_reverse_geocode( $map_service ) {
    if ( $map_service !== 'stadia' ) {
        return true;
    }

    // Unknown counts as available: the notice on the first failure explains it,
    // which is better than removing the marker drag on a guess.
    return (bool) get_option( 'wpsl_stadia_reverse_access', 1 );
}

/**
 * Check whether the API key may use the Stadia Maps reverse geocoding endpoint.
 *
 * wpsl_check_stadia_key_status() validates the key against a tile, which says
 * nothing about geocoding entitlements. Stadia sells the reverse endpoint
 * separately from forward search, so a key can pass that check, run searches,
 * and still 403 on every reverse request - leaving the search field silently
 * empty when auto-locate is on.
 *
 * Only a 403 counts as denied: a timeout or 500 says nothing about the
 * account, and reporting those as a plan problem sends people fixing the
 * wrong thing.
 *
 * @since  3.0.0
 * @param  string $key_value The API key to test. Falls back to the stored key.
 * @return bool   False only when the endpoint is refused for this key.
 */
function wpsl_check_stadia_reverse_access( $key_value = '' ) {
    if ( ! $key_value ) {
        $key_value = wpsl_get_service( 'wpsl_settings' )->get( 'api', 'stadia_key' );
    }

    $key_value = trim( (string) $key_value );

    if ( ! $key_value ) {
        return true;
    }

    $url = wpsl_stadia_api_base_url() . '/geocoding/v2/reverse?' . http_build_query( [
        'point.lat' => 52.3702,
        'point.lon' => 4.8952,
        'size'      => 1,
        'api_key'   => $key_value,
    ] );

    $response = wp_remote_get( $url, [
        'timeout'    => 15,
        'user-agent' => 'WP Store Locator/' . WPSL_VERSION_NUM,
    ] );

    if ( is_wp_error( $response ) ) {
        return true;
    }

    return wp_remote_retrieve_response_code( $response ) !== 403;
}

/**
 * Determine whether the site is running in a local development environment.
 *
 * Used to explain that URL-restricted Mapbox tokens can't be validated on
 * localhost (Mapbox blocks localhost unless it's in the token's allowed list).
 *
 * @since  3.0.0
 * @return bool True when the site appears to be local.
 */
function wpsl_is_local_environment() {
    // WP's own environment flag is the most reliable signal when set.
    if ( wp_get_environment_type() === 'local' ) {
        return true;
    }

    $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

    if ( $host === '' ) {
        return false;
    }

    if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
        return true;
    }

    // Common local TLDs used by Local
    foreach ( [ '.local', '.test', '.localhost', '.ddev.site' ] as $suffix ) {
        if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
            return true;
        }
    }

    // Loopback / private IP ranges.
    if ( filter_var( $host, FILTER_VALIDATE_IP ) && ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
        return true;
    }

    return false;
}

/**
 * Build an extra, actionable hint for known Mapbox key errors.
 *
 * A 403 "Forbidden" means the token is URL-restricted to a domain that doesn't
 * match where it's being used (or, on a local site, that localhost isn't in the
 * token's allowed list). A 401 "Invalid Token" means the token itself is wrong.
 *
 * Shared by the settings page key validation and the editor geocode notice so
 * both show identical guidance.
 *
 * @since  3.0.0
 * @param  string $status A status string that may contain the HTTP code/reason
 *                        (e.g. "Error code: 401\nReason: Not Authorized").
 * @return string         A formatted hint paragraph, or an empty string.
 */
function wpsl_get_mapbox_error_hint( $status ) {
    $timeout_hint = wpsl_get_timeout_error_hint( $status );

    if ( $timeout_hint ) {
        return $timeout_hint;
    }

    if ( stripos( $status, '403' ) !== false || stripos( $status, 'Forbidden' ) !== false ) {

        // On a local site a URL-restricted token can't validate: Mapbox blocks
        // localhost unless it's explicitly added to the token's allowed URLs.
        // Show only the localhost-specific note in that case.
        if ( wpsl_is_local_environment() ) {
            $docs_link = '<a target="_blank" href="https://docs.mapbox.com/accounts/guides/tokens/#requirements-and-limitations">';

            $local_note = sprintf(
                /* translators: 1: the local host name, 2: opening Mapbox docs link tag, 3: closing link tag */
                esc_html__( 'You appear to be developing locally (%1$s). Mapbox blocks restricted tokens on localhost unless localhost is added to the token\'s allowed URLs, so a domain-restricted token will always fail to validate here. For local development, create a separate token with more permissive URL restrictions. %2$sLearn more%3$s.', 'wp-store-locator' ),
                esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
                $docs_link,
                '</a>'
            );

            return '<p>' . $local_note . '</p>';
        }

        $console_link = '<a target="_blank" href="https://console.mapbox.com/account/access-tokens/">';
        $support_link = '<a target="_blank" href="https://www.mapbox.com/support">';

        $hint = sprintf(
            /* translators: 1: opening Mapbox console link tag, 2: closing link tag, 3: opening Mapbox support link tag, 4: closing link tag */
            esc_html__( 'This usually means the access token is restricted to a URL that differs from the one it\'s being used on. Double-check the token\'s URL restrictions in the %1$sMapbox console%2$s. If that doesn\'t resolve it, contact %3$sMapbox Support%4$s.', 'wp-store-locator' ),
            $console_link,
            '</a>',
            $support_link,
            '</a>'
        );

        return '<p>' . $hint . '</p>';
    }

    // 401 Not Authorized / Invalid Token: the token itself is wrong (e.g. a
    // copy/paste error), so point the user at copying it correctly or making
    // a new one.
    if ( stripos( $status, '401' ) !== false || stripos( $status, 'Invalid Token' ) !== false ) {
        $tokens_link = '<a target="_blank" href="https://console.mapbox.com/account/access-tokens">';
        $create_link = '<a target="_blank" href="https://wpstorelocator.co/document/create-mapbox-api-key/">';

        $hint = sprintf(
            /* translators: 1: opening Mapbox tokens link tag, 2: closing link tag, 3: opening Mapbox create-token link tag, 4: closing link tag */
            esc_html__( 'Copy your token from the %1$sMapbox console%2$s, or %3$screate a new one%4$s.', 'wp-store-locator' ),
            $tokens_link,
            '</a>',
            $create_link,
            '</a>'
        );

        return '<p>' . $hint . '</p>';
    }

    return '';
}

/**
 * Detect the public IP address of this server.
 *
 * Used to suggest the correct IP restriction for a Google Maps server key.
 * The result is cached for a day to avoid repeated external requests.
 *
 * @since  3.0.0
 * @return string The server's public IP address, or an empty string on failure.
 */
function wpsl_get_server_public_ip() {
    /**
     * Allow the external public IP lookup to be disabled.
     *
     * Return false to skip the request to the external service (e.g. for
     * privacy reasons or on servers without outbound access). The IP
     * suggestion is then omitted from the error hint.
     *
     * @since 3.0.0
     * @param bool $enabled Whether the public IP lookup is allowed. Default true.
     */
    if ( ! apply_filters( 'wpsl_detect_server_ip', true ) ) {
        return '';
    }

    $cached = get_transient( 'wpsl_server_public_ip' );

    if ( $cached ) {
        return $cached;
    }

    $ip       = '';
    $response = wp_remote_get( 'https://api.ipify.org', [ 'timeout' => 5 ] );

    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $body = trim( wp_remote_retrieve_body( $response ) );

        if ( filter_var( $body, FILTER_VALIDATE_IP ) ) {
            $ip = $body;
            set_transient( 'wpsl_server_public_ip', $ip, DAY_IN_SECONDS );
        }
    }

    return $ip;
}

/**
 * Format a plain "Error code: x\nReason: y" status string into HTML,
 * bolding the labels and joining the lines with a single line break.
 *
 * @since  3.0.1
 * @param  string $response The status string.
 * @return string           Escaped HTML.
 */
function wpsl_format_key_error( $response ) {
    $html = [];

    foreach ( explode( "\n", (string) $response ) as $line ) {
        $pos = strpos( $line, ': ' );

        if ( $pos !== false ) {
            $label  = substr( $line, 0, $pos + 1 );
            $value  = substr( $line, $pos + 2 );
            $html[] = '<strong>' . esc_html( $label ) . '</strong> ' . esc_html( $value );
        } else {
            $html[] = esc_html( $line );
        }
    }

    return implode( '<br>', $html );
}

/**
 * The full notice body for a failed Google Maps server key: the error code and
 * reason, followed by the fix-it hint when there is one.
 *
 * Built when it is shown rather than stored, so it follows the current
 * wording and the site language.
 *
 * @since  3.0.1
 * @param  string $status The status string returned by the key check.
 * @return string         Escaped HTML.
 */
function wpsl_get_gmaps_error_details( $status ) {
    return '<p>' . wpsl_format_key_error( $status ) . '</p>' . wpsl_get_gmaps_error_hint( $status );
}

/**
 * Find the first IPv4 or IPv6 address in a piece of text, such as the
 * originating IP Google puts in its IP restriction error.
 *
 * @since  3.0.1
 * @param  string $text
 * @return string The address, or an empty string when there is none.
 */
function wpsl_find_ip_in_text( $text ) {
    // Anything built from the characters an address can contain, validated below.
    if ( ! preg_match_all( '/[0-9a-f:.]{7,45}/i', (string) $text, $candidates ) ) {
        return '';
    }

    foreach ( $candidates[0] as $candidate ) {
        // As found first, then without the period of a sentence that ends
        // right after the address.
        foreach ( [ $candidate, rtrim( $candidate, '.' ) ] as $ip ) {
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }

    return '';
}

/**
 * Build an extra, actionable hint for known Google Maps key errors.
 *
 * Handles the IP address restriction error (extracts the originating IP) and the
 * HTTP referrer restriction error (a misconfigured server key). Shared by the
 * settings page key validation and the editor geocode notice.
 *
 * @since  3.0.0
 * @param  string $status The error status string ( may contain the reason ).
 * @return string         A formatted hint paragraph, or an empty string.
 */
function wpsl_get_gmaps_error_hint( $status ) {
    $timeout_hint = wpsl_get_timeout_error_hint( $status );

    if ( $timeout_hint ) {
        return $timeout_hint;
    }

    $best_practices = '<a target="_blank" href="https://developers.google.com/maps/api-security-best-practices#restricting-api-keys">';

    // Invalid API key: link to the credentials page and docs.
    if ( stripos( $status, 'API key not valid' ) !== false ) {
        return '<p>' .
            sprintf(
                /* translators: 1: opening link tag to Google Cloud credentials, 2: closing link tag, 3: opening link tag to create-key documentation, 4: closing link tag */
                esc_html__( 'Check your existing keys in %1$sGoogle Cloud Console%2$s or %3$screate a key%4$s.', 'wp-store-locator' ),
                '<a target="_blank" href="https://console.cloud.google.com/apis/credentials">',
                '</a>',
                '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#api-key">',
                '</a>'
            ) .
        '</p>';
    }

    /*
     * IP address restriction: Google includes the originating IP in the message.
     * That IP is this server's, and it is the one missing from the key's allowed
     * list, so say so plainly: repeating it as "the IP to change it to" read as
     * if the key were already restricted to it.
     */
    $blocked_ip = ( stripos( $status, 'IP address' ) !== false ) ? wpsl_find_ip_in_text( $status ) : '';

    if ( $blocked_ip ) {
        $ip = esc_html( $blocked_ip );

        $cause = sprintf(
            /* translators: %s: this server's IP address */
            esc_html__( 'Google blocked this request because it came from %s, the IP address of your server, and that address is not on the list of IP addresses this key allows.', 'wp-store-locator' ),
            '<strong>' . $ip . '</strong>'
        );

        $fix = sprintf(
            /* translators: 1: this server's IP address, 2: opening link tag to Google Cloud credentials, 3: closing link tag */
            esc_html__( '%2$sEdit the key in Google Cloud Console%3$s and add %1$s to its IP address restrictions. It can take up to five minutes before the change applies.', 'wp-store-locator' ),
            '<strong>' . $ip . '</strong>',
            '<a target="_blank" href="https://console.cloud.google.com/apis/credentials">',
            '</a>'
        );

        return '<p>' . $cause . '</p><p><strong>' . esc_html__( 'How to fix this', 'wp-store-locator' ) . '</strong><br>' . $fix . '</p>';
    }

    /*
     * API restriction: the key is limited to a list of APIs that leaves out the
     * Geocoding API, a common mistake when one key was set up for the map only.
     * Google answers "Requests to this API geocoding_backend method ... are blocked."
     */
    if ( stripos( $status, 'Requests to this API' ) !== false && stripos( $status, 'blocked' ) !== false ) {
        $cause = esc_html__( 'The API restrictions of this key do not include the Geocoding API, which the server key needs to look up the coordinates of your locations.', 'wp-store-locator' );

        $fix = sprintf(
            /* translators: 1: opening link tag to Google Cloud credentials, 2: closing link tag */
            esc_html__( '%1$sEdit the key in Google Cloud Console%2$s and add the Geocoding API to its API restrictions. It can take up to five minutes before the change applies.', 'wp-store-locator' ),
            '<a target="_blank" href="https://console.cloud.google.com/apis/credentials">',
            '</a>'
        );

        return '<p>' . $cause . '</p><p><strong>' . esc_html__( 'How to fix this', 'wp-store-locator' ) . '</strong><br>' . $fix . '</p>';
    }

    /*
     * The Geocoding API isn't enabled in the key's project. Google answers "This
     * API project is not authorized to use this API." or, for a disabled API,
     * "... has not been used in project ... before or it is disabled."
     */
    if ( stripos( $status, 'not authorized to use this API' ) !== false || stripos( $status, 'it is disabled' ) !== false ) {
        $cause = esc_html__( 'The Geocoding API is not enabled in the Google Cloud project of this key.', 'wp-store-locator' );

        $fix = sprintf(
            /* translators: 1: opening link tag to the Geocoding API page in Google Cloud, 2: closing link tag */
            esc_html__( '%1$sEnable the Geocoding API%2$s in the same project as the key, then try again. It can take up to five minutes before the change applies.', 'wp-store-locator' ),
            '<a target="_blank" href="https://console.cloud.google.com/apis/library/geocoding-backend.googleapis.com">',
            '</a>'
        );

        return '<p>' . $cause . '</p><p><strong>' . esc_html__( 'How to fix this', 'wp-store-locator' ) . '</strong><br>' . $fix . '</p>';
    }

    // HTTP referrer restriction: a server key should use an IP restriction instead.
    if ( stripos( $status, 'referer' ) !== false || stripos( $status, 'referrer' ) !== false ) {
        $ip = wpsl_get_server_public_ip();

        if ( $ip ) {
            $hint = sprintf(
                /* translators: 1: server IP address, 2: opening link tag, 3: closing link tag */
                esc_html__( 'This usually means the server key is restricted to an HTTP referrer (a domain), but a server key should be %2$srestricted to your server\'s IP address%3$s instead. Try restricting it to %1$s.', 'wp-store-locator' ),
                esc_html( $ip ),
                $best_practices,
                '</a>'
            );
        } else {
            $hint = sprintf(
                /* translators: 1: opening link tag, 2: closing link tag */
                esc_html__( 'This usually means the server key is restricted to an HTTP referrer (a domain), but a server key should be %1$srestricted to your server\'s IP address%2$s instead.', 'wp-store-locator' ),
                $best_practices,
                '</a>'
            );
        }

        return '<p>' . $hint . '</p>';
    }

    return '';
}

/**
 * Build an extra, actionable hint for a failed Stadia Maps key check.
 *
 * Every failure that isn't a network problem or an outage on their end comes
 * down to the key itself ( wrong format, revoked, or from another property ),
 * so point the user straight at the dashboard page it's copied from.
 *
 * @since  3.0.0
 * @param  string $status The error status string ( may contain the reason ).
 * @return string         A formatted hint paragraph, or an empty string.
 */
function wpsl_get_stadia_error_hint( $status ) {
    $timeout_hint = wpsl_get_timeout_error_hint( $status );

    if ( $timeout_hint ) {
        return $timeout_hint;
    }

    // A 5xx is Stadia having a problem, not the key, so stay quiet.
    if ( preg_match( '/\b5\d{2}\b/', $status ) ) {
        return '';
    }

    $dashboard_link = '<a target="_blank" href="https://client.stadiamaps.com/dashboard/overview">';
    $create_link    = '<a target="_blank" href="https://wpstorelocator.co/document/create-stadia-maps-api-key/">';

    $hint = sprintf(
        /* translators: 1: opening Stadia Maps dashboard link tag, 2: closing link tag, 3: opening create-key guide link tag, 4: closing link tag */
        esc_html__( 'Open the %1$sStadia Maps dashboard%2$s, click "Manage Properties", and copy the API key shown under Authentication Configuration, or %3$slearn how to create a new key%4$s.', 'wp-store-locator' ),
        $dashboard_link,
        '</a>',
        $create_link,
        '</a>'
    );

    return '<p>' . $hint . '</p>';
}

/**
 * Build a geocode API error notice in a consistent structure.
 *
 * Produces the markup shared by the settings page key validation and the editor
 * geocode notices: a bold title, the HTTP code and reason on their own line, and
 * an optional actionable hint paragraph below.
 *
 * @since  3.0.0
 * @param  string $title  The notice title (plain text, will be escaped).
 * @param  string $code   The HTTP status code (plain text, will be escaped).
 * @param  string $reason The error reason (plain text, will be escaped).
 * @param  string $hint   Optional pre-built hint HTML to append.
 * @return string         The notice HTML.
 */
function wpsl_build_key_error_notice( $title, $code, $reason, $hint = '' ) {
    $html  = '<p><strong>' . esc_html( $title ) . '</strong></p>';
    $html .= '<p><strong>' . esc_html__( 'Error code:', 'wp-store-locator' ) . '</strong> ' . esc_html( (string) $code );
    $html .= '<br><strong>' . esc_html__( 'Reason:', 'wp-store-locator' ) . '</strong> ' . esc_html( $reason ) . '</p>';
    $html .= $hint;

    return $html;
}

/**
 * Error messages used in both the front and backend when
 * errors occur with either the geolocation or Google Maps API.
 *
 * @since  3.0.0
 * @param  string $type           The type of errors to return
 * @return array  $error_messages Collection of error messages
 */
function wpsl_api_error_messages( $type = '' ) {
    $error_messages = [
        'geolocation' => [
            'denied'       => __( 'The application does not have permission to use the Geolocation API.', 'wp-store-locator' ),
            'unavailable'  => __( 'Location information is unavailable.', 'wp-store-locator' ), //@todo add second one that default search will be loaded, but only is no results are already shown.
            'timeout'      => __( 'The geolocation request has timed out.', 'wp-store-locator' ),
            'generalError' => __( 'An unknown error has occurred.', 'wp-store-locator' )
        ],
        'gmaps' => [
            'viewMapsDocs'      => __( 'view Google Maps documentation', 'wp-store-locator' ),
            'errorReturned'     => __( 'The Google JavaScript API has returned the following error.', 'wp-store-locator' ),
            /* translators: %s: the word "documentation", linked to the WPSL document explaining how to create the API keys */
            'apiDescribed'      => __( 'Make sure the API keys are set up as described in the %s.', 'wp-store-locator' ),
            'wpslDoc'           => __( 'documentation', 'wp-store-locator' ),
            'enableBilling'     => __( 'You must enable billing for your Google Cloud Project', 'wp-store-locator' ),
            /* translators: 1: line breaks, 2: opening link tag, 3: closing link tag */
            'conflictDetected'  => sprintf( __( 'Another plugin has already loaded Google Maps breaking the store locator. Please enable compatibility mode in the WP Store Locator Tools settings.%1$s%2$sRead more.%3$s', 'wp-store-locator' ), '<br><br>', '<a target="_blank" href="https://wpstorelocator.co/document/another-plugin-has-already-loaded-google-maps">', '</a>' ),
            /* translators: 1: opening link tag for API keys, 2: closing link tag, 3: opening link tag for troubleshooting, 4: closing link tag */
            'errorNoticeFooter' => sprintf( __( '%1$sConfigure API keys%2$s | %3$sTroubleshooting%4$s', 'wp-store-locator' ),'<p><strong><a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys">', '</a>', '<a target="_blank" href="https://wpstorelocator.co/document/create-google-api-keys/#troubleshooting">', '</a></strong></p>' ),
        ],
        'osm' => [
            'errorReturned' => __( 'The Openrouteservice API has returned the following error.', 'wp-store-locator' ),
        ],
        'openrouteservice' => [
            /* translators: 1: opening link tag, 2: closing link tag */
            'keyRequired' => sprintf( __( 'An API key is required before you can use %1$sOpenrouteservice%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://openrouteservice.org/dev/#/signup">', '</a>' )
        ],
        'nominatim' => [
            /* translators: 1: line break, 2: opening link tag, 3: closing link tag */
            'errorReturned' => sprintf( __( 'OpenStreetMap has failed to return valid data, please try again later.%1$s If you keep seeing this error, please %2$scontact support%3$s.', 'wp-store-locator' ), '<br>', '<a target="_blank" href="https://wpstorelocator.co/support/">', '</a>' ),
            'noErrors' => __( 'No problems found with OpenStreetMaps.', 'wp-store-locator' )
        ],
        'mapbox' => [
            'errorReturned' => __( 'The Mapbox API has returned the following error.', 'wp-store-locator' ),
            'errorCodeLabel' => __( 'Error code:', 'wp-store-locator' ),
            'reasonLabel'    => __( 'Reason:', 'wp-store-locator' ),
            /* translators: 1: opening Mapbox console link tag, 2: closing link tag, 3: opening Mapbox support link tag, 4: closing link tag */
            'forbiddenHint'  => sprintf( __( '%1$sThis usually means the access token is restricted to a URL that differs from the one it\'s being used on. Double-check the token\'s URL restrictions in the %2$sMapbox console%3$s. If that doesn\'t resolve it, contact %4$sMapbox Support%5$s.%6$s', 'wp-store-locator' ), '<p>', '<a target="_blank" href="https://console.mapbox.com/account/access-tokens/">', '</a>', '<a target="_blank" href="https://www.mapbox.com/support">', '</a>', '</p>' ),
            /* translators: 1: opening link tag for API keys, 2: closing link tag, 3: opening link tag for geocoding errors, 4: closing link tag */
            'errorNoticeFooter' => sprintf( __( '%1$sConfigure API keys%2$s | %3$sGeocoding API errors%4$s', 'wp-store-locator' ),'<p><strong><a target="_blank" href="https://wpstorelocator.co/document/create-mapbox-api-key/">', '</a>', '<a target="_blank" href="https://docs.mapbox.com/api/search/geocoding/#geocoding-api-errors">', '</a></strong></p>' ),
            /* translators: 1: opening link tag, 2: closing link tag */
            'errorGeocodesNoticeFooter' => sprintf( __( '%1$sRead more%2$s', 'wp-store-locator' ),'<p><strong><a target="_blank" href="https://docs.mapbox.com/api/search/geocoding/#mapboxplaces-permanent">', '</a></strong></p>' ),
            /* translators: 1: opening link tag to the create-a-key documentation, 2: closing link tag */
            'keyRequiredMap' => sprintf( __( 'A valid Mapbox %1$sAPI key%2$s is required to load the map!', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-mapbox-api-key/">', '</a>' ),
        ],
        'stadia' => [
            'errorReturned' => __( 'The Stadia Maps API has returned the following error.', 'wp-store-locator' ),
            /* translators: 1: opening link tag, 2: closing link tag */
            'keyRequired' => sprintf( __( 'An API key is required before you can use %1$sStadia Maps%2$s.', 'wp-store-locator' ), '<a target="_blank" href="https://client.stadiamaps.com/signup/">', '</a>' ),
            /* translators: 1: opening link tag to the create-a-key documentation, 2: closing link tag */
            'keyRequiredMap' => sprintf( __( 'A valid Stadia Maps %1$sAPI key%2$s is required to load the map!', 'wp-store-locator' ), '<a target="_blank" href="https://wpstorelocator.co/document/create-stadia-maps-api-key/">', '</a>' ),
            'noErrors' => __( 'No problems found with Stadia Maps.', 'wp-store-locator' )
        ],
    ];

    if ( $type ) {
        $error_messages = $error_messages[ $type ];
    }

    return $error_messages;
}