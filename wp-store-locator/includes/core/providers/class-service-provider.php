<?php
namespace WPSL\Core\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service Provider Interface
 *
 * Interface for all service providers to implement.
 *
 * @since 3.0.0
 */
interface Service_Provider {
    /**
     * Register services with the container
     *
     * @param \WPSL\Core\Container $container The container instance
     * @return void
     */
    public function register( $container );
}