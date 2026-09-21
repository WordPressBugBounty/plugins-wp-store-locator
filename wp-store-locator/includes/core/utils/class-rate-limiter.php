<?php
/**
 * Per-IP request rate limiting for public AJAX endpoints.
 *
 * @author Tijmen Smit
 * @since  3.0.0
 */

namespace WPSL\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rate_Limiter {

    /**
     * Whether the current visitor has exceeded $max_per_minute requests for $bucket.
     *
     * Public AJAX endpoints (Nominatim geocoding, directions proxies) sit on
     * full-page-cached pages, so a nonce can't be used — a cached page would
     * serve a stale one. Per-IP counting is the fallback to cap third-party
     * API usage.
     *
     * @since  3.0.0
     * @param  string   $bucket         Counter namespace (e.g. 'nominatim', 'directions').
     * @param  int      $max_per_minute Requests allowed per minute; <= 0 disables the limit.
     * @param  int|null $now            Unix time; defaults to time(), overridable for tests.
     * @return bool True if the visitor is over the limit.
     */
    public static function is_limited( $bucket, $max_per_minute, $now = null ) {
        $max_per_minute = (int) $max_per_minute;

        /**
         * Filter whether rate limiting is switched off for every bucket.
         *
         * A single override for sites that want one switch instead of
         * zeroing out each endpoint's own filter ( wpsl_search_rate_limit,
         * wpsl_directions_rate_limit, wpsl_nominatim_rate_limit, ... ).
         *
         * @since 3.0.0
         * @param bool   $disabled True to skip rate limiting entirely.
         * @param string $bucket   The bucket being checked ( e.g. 'nominatim', 'directions' ).
         */
        if ( apply_filters( 'wpsl_rate_limit_disabled', false, $bucket ) ) {
            return false;
        }

        if ( $max_per_minute <= 0 ) {
            return false;
        }

        $ip = self::get_client_ip();

        // Without a client IP we can't attribute requests to a visitor, so we
        // don't limit rather than lump every visitor into one shared counter.
        if ( ! $ip ) {
            return false;
        }

        $now    = ( null === $now ) ? time() : (int) $now;
        $bucket = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $bucket ) );
        $minute = intdiv( $now, MINUTE_IN_SECONDS );

        // wp_hash() is salted. A plain md5() of an IPv4 address is reversed
        // in seconds, which would make the stored name the address itself.
        $key = 'wpsl_rl_' . $bucket . '_' . wp_hash( self::counter_address( $ip ) ) . '_' . $minute;

        $count = wp_using_ext_object_cache()
            ? self::count_in_cache( $key )
            : self::count_in_database( $key, ( $minute + 1 ) * MINUTE_IN_SECONDS );

        return $count > $max_per_minute;
    }

    /**
     * The address a counter is kept for.
     *
     * An IPv4 address is one visitor. An IPv6 visitor is handed a whole /64,
     * so counting per address would give them a fresh budget ( and a fresh
     * row in the options table ) for every one of its 2^64 addresses.
     *
     * @since  3.0.0
     * @param  string $ip The resolved visitor address.
     * @return string     The address itself, or the /64 network of an IPv6 address.
     */
    private static function counter_address( $ip ) {
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return $ip;
        }

        return inet_ntop( substr( inet_pton( $ip ), 0, 8 ) . str_repeat( "\0", 8 ) );
    }

    /**
     * Count the request in the persistent object cache.
     *
     * wp_cache_add() only writes when the key is new, and the increment is
     * a single command in Redis and Memcached.
     *
     * @since  3.0.0
     * @param  string $key The counter name.
     * @return int         The requests counted in this window, this one included. 0 when the cache failed.
     */
    private static function count_in_cache( $key ) {
        wp_cache_add( $key, 0, 'wpsl_rate_limit', MINUTE_IN_SECONDS );

        return (int) wp_cache_incr( $key, 1, 'wpsl_rate_limit' );
    }

    /**
     * Count the request in the options table.
     *
     * The rows are named and paired like a transient, so core's daily
     * delete_expired_transients() and the uninstall routine clean them up,
     * but they are never read through get_transient(): the options API can
     * only read, add one and write back, the UPDATE adds one in place.
     *
     * An INSERT ... ON DUPLICATE KEY UPDATE would do it in one statement,
     * but InnoDB spends an option_id on every one of those, hit or miss.
     *
     * @since  3.0.0
     * @param  string $key     The counter name.
     * @param  int    $expires Unix time the window closes.
     * @return int             The requests counted in this window, this one included. 0 when the query failed.
     */
    private static function count_in_database( $key, $expires ) {
        global $wpdb;

        $name = '_transient_' . $key;
        $bump = $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $name );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- An atomic counter, prepared above
        if ( ! $wpdb->query( $bump ) ) {

            // No row yet, so this request opens the window, unless a parallel one just did.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- See above
            $opened = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", $name, '1' ) );

            if ( 1 === $opened ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- See above
                $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", '_transient_timeout_' . $key, (string) $expires ) );

                return 1;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- See above
            if ( ! $wpdb->query( $bump ) ) {
                return 0;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- See above
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
    }

    /**
     * Resolve the address of the visitor making the current request.
     *
     * @since  3.0.0
     * @return string The IP address, or an empty string when none could be determined.
     */
    public static function get_client_ip() {
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? self::validate_ip( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $ip     = $remote;

        if ( $remote ) {
            if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && self::ip_in_ranges( $remote, self::cloudflare_ranges() ) ) {
                $forwarded = self::validate_ip( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );

                if ( $forwarded ) {
                    $ip = $forwarded;
                }
            } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && self::trust_forwarded_for( $remote ) ) {
                // Walk back from the proxy closest to us, past every further
                // proxy, to the first visitor address. A CDN in front of the
                // operator's own load balancer shows up as a public hop:
                // stopping at it would put everyone behind that CDN edge in
                // one counter.
                $hops    = array_reverse( explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
                $proxies = array_merge( self::private_ranges(), self::trusted_proxy_ranges() );

                foreach ( $hops as $hop ) {
                    $hop = self::validate_ip( $hop );

                    if ( $hop && ! self::ip_in_ranges( $hop, $proxies ) ) {
                        $ip = $hop;
                        break;
                    }
                }
            }
        }

        /**
         * Filter the address the rate limiter attributes the request to.
         *
         * Lets sites behind a proxy that is neither Cloudflare nor on a
         * private address ( e.g. Sucuri, a custom nginx in front ) supply the
         * visitor address from the header that proxy sets.
         *
         * @since 3.0.0
         * @param string $ip     The resolved visitor address.
         * @param string $remote The raw, validated REMOTE_ADDR.
         */
        return (string) apply_filters( 'wpsl_rate_limit_client_ip', $ip, $remote );
    }

    /**
     * Whether X-Forwarded-For may name the visitor for this request:
     * by default only when the direct peer is loopback ( a proxy on the
     * same host ). A load balancer on a private address has to be named
     * with WPSL_TRUST_FORWARDED_FOR or the filter, else every visitor
     * behind it shares one counter. The constant overrides either way;
     * the filter has the last word.
     *
     * @since  3.0.0
     * @param  string $remote The validated REMOTE_ADDR.
     * @return bool
     */
    public static function trust_forwarded_for( $remote ) {
        /*
         * Only a loopback peer is trusted without being told: a proxy on the
         * same host is the only thing that makes the peer loopback, and it
         * appends the address it received the request from. Any other private
         * peer may be a container bridge or a NAT gateway that passes the
         * visitor's own header through, so trusting it takes the constant or
         * the filter below.
         */
        $trust = self::ip_in_ranges( $remote, self::loopback_ranges() );

        if ( defined( 'WPSL_TRUST_FORWARDED_FOR' ) ) {
            $trust = (bool) WPSL_TRUST_FORWARDED_FOR;
        }

        /**
         * Filter whether the X-Forwarded-For header is trusted for this request.
         *
         * @since 3.0.0
         * @param bool   $trust  True to read the visitor address from the header.
         * @param string $remote The raw, validated REMOTE_ADDR.
         */
        return (bool) apply_filters( 'wpsl_rate_limit_trust_forwarded_for', $trust, $remote );
    }

    /**
     * Return the address when it is a well-formed IPv4 or IPv6 address, an empty string otherwise.
     *
     * @since  3.0.0
     * @param  string $ip
     * @return string
     */
    private static function validate_ip( $ip ) {
        $ip = trim( (string) $ip );

        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return '';
        }

        // A dual-stack server reports an IPv4 peer as ::ffff:203.0.113.7, which
        // matches none of the IPv4 ranges below. Bring it back to plain IPv4.
        $packed = inet_pton( $ip );

        if ( 16 === strlen( $packed ) && 0 === strpos( $packed, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
            $ip = inet_ntop( substr( $packed, 12 ) );
        }

        return $ip;
    }

    /**
     * Whether an address falls inside any of the given CIDR ranges.
     *
     * Works on the packed binary form so IPv4 and IPv6 share one code path.
     * An address is only compared against ranges of its own family.
     *
     * @since  3.0.0
     * @param  string $ip     A validated IPv4 or IPv6 address.
     * @param  array  $ranges CIDR notations, e.g. '10.0.0.0/8' or '2400:cb00::/32'.
     * @return bool
     */
    public static function ip_in_ranges( $ip, $ranges ) {
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        $packed = inet_pton( $ip );

        foreach ( (array) $ranges as $range ) {
            if ( strpos( $range, '/' ) === false ) {
                continue;
            }

            list( $subnet, $bits ) = explode( '/', $range, 2 );

            if ( ! filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
                continue;
            }

            $subnet_packed = inet_pton( $subnet );

            // Different address family than the visitor address.
            if ( strlen( $subnet_packed ) !== strlen( $packed ) ) {
                continue;
            }

            $bits  = max( 0, min( (int) $bits, strlen( $packed ) * 8 ) );
            $bytes = intdiv( $bits, 8 );
            $rest  = $bits % 8;

            if ( $bytes && substr( $packed, 0, $bytes ) !== substr( $subnet_packed, 0, $bytes ) ) {
                continue;
            }

            if ( $rest ) {
                $mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;

                if ( ( ord( $packed[ $bytes ] ) & $mask ) !== ( ord( $subnet_packed[ $bytes ] ) & $mask ) ) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Cloudflare's published edge ranges, see https://www.cloudflare.com/ips/
     *
     * Only requests arriving from one of these may carry a trustworthy
     * CF-Connecting-IP header. The list changes rarely, the filter exists
     * for the day it does.
     *
     * @since  3.0.0
     * @return array
     */
    public static function cloudflare_ranges() {
        $ranges = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];

        /**
         * Filter the Cloudflare edge ranges that may carry a trusted CF-Connecting-IP header.
         *
         * @since 3.0.0
         * @param array $ranges CIDR notations.
         */
        return (array) apply_filters( 'wpsl_cloudflare_ip_ranges', $ranges );
    }

    /**
     * Public proxies the X-Forwarded-For walk steps over, like it does the
     * private ranges.
     *
     * The walk only runs when our own proxy handed us the request, so a hop
     * inside one of these ranges was recorded by that proxy and is genuine.
     * Cloudflare is covered by default; a site behind another CDN in front
     * of its load balancer ( CloudFront, Fastly ) adds that CDN's ranges.
     *
     * @since  3.0.0
     * @return array
     */
    public static function trusted_proxy_ranges() {

        /**
         * Filter the public proxy ranges skipped when reading X-Forwarded-For.
         *
         * @since 3.0.0
         * @param array $ranges CIDR notations. Defaults to Cloudflare's edge ranges.
         */
        return (array) apply_filters( 'wpsl_rate_limit_trusted_proxies', self::cloudflare_ranges() );
    }

    /**
     * Loopback ranges: a peer inside one of these is on the same host.
     *
     * @since  3.0.0
     * @return array
     */
    public static function loopback_ranges() {
        return [
            '127.0.0.0/8',
            '::1/128',
        ];
    }

    /**
     * Private, loopback and link-local ranges.
     *
     * A hop inside one of these is never the visitor, so the walk back
     * through X-Forwarded-For steps over it. Whether a peer in one of them
     * is trusted to have written the header is trust_forwarded_for()'s call.
     *
     * @since  3.0.0
     * @return array
     */
    public static function private_ranges() {
        return [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10',
        ];
    }
}