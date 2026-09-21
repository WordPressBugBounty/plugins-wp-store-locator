<?php
/**
 * Container class for dependency management
 *
 * @package WPSL\Core
 * @since 3.0.0
 */

namespace WPSL\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Container class for managing services and dependencies
 *
 * @since 3.0.0
 */
class Container {
    /**
     * Registered services
     *
     * @var array
     */
    private $services = [];

    /**
     * Track classes being resolved to prevent circular dependencies
     *
     * @var array
     */
    private $resolving = [];

    /**
     * Track service ids being resolved to prevent circular dependencies
     *
     * Kept apart from $resolving because a service may be registered under
     * its own class name, which would otherwise collide with the class key
     * auto_resolve() sets for it.
     *
     * @var array
     */
    private $resolving_ids = [];

    /**
     * Map class names onto the service ids that provide them
     *
     * @var array
     */
    private $class_map = [];

    /**
     * Register a service in the container
     *
     * @param string $id Service identifier
     * @param mixed  $concrete Service implementation (object or factory function)
     * @param bool   $shared Whether this service should be shared (singleton)
     * @return void
     * @throws \InvalidArgumentException If service ID is invalid
     */
    public function register( $id, $concrete, $shared = false ) {
        // Validate service ID
        if ( ! is_string( $id ) || trim( $id ) === '' ) {
            throw new \InvalidArgumentException( 'Service ID must be a non-empty string.' );
        }
        
        // Validate concrete implementation
        if ( ! is_callable( $concrete ) && ! is_object( $concrete ) ) {
            throw new \InvalidArgumentException( 'Service concrete must be callable or object.' );
        }
        
        $this->services[$id] = [
            'concrete' => $concrete,
            'shared'   => $shared,
            'instance' => null,
        ];
    }

    /**
     * Register a service as shared (singleton)
     *
     * @param string $id Service identifier
     * @param mixed  $concrete Service implementation
     * @return void
     */
    public function register_shared( $id, $concrete ) {
        $this->register( $id, $concrete, true );
    }

    /**
     * Register a service with automatic dependency resolution
     *
     * Also binds $class_name to $id, so another auto-resolved service can take
     * this one as a constructor dependency and receive the registered instance.
     *
     * @param string $id Service identifier
     * @param string $class_name Fully qualified class name
     * @param bool   $shared Whether this service should be shared (singleton)
     * @return void
     */
    public function register_with_auto_resolution( $id, $class_name, $shared = false ) {
        $this->register( $id, function( $container ) use ( $class_name ) {
            return $this->auto_resolve( $class_name );
        }, $shared );

        $this->bind_class( $class_name, $id );
    }

    /**
     * Map a class name onto the service id that provides it
     *
     * auto_resolve() type-hints its way through constructors, but services are
     * registered under short ids ( 'wpsl_settings' ), never their class name.
     * Without this map it rebuilds every dependency from scratch instead of
     * handing back the container's own shared instance.
     *
     * @since 3.0.0
     * @param string $class_name Fully qualified class name, with or without a leading backslash
     * @param string $id Service identifier that provides the class
     * @return void
     * @throws \InvalidArgumentException If the class name or service ID is invalid
     */
    public function bind_class( $class_name, $id ) {
        if ( ! is_string( $class_name ) || trim( $class_name, " \t\n\r\0\x0B\\" ) === '' ) {
            throw new \InvalidArgumentException( 'Class name must be a non-empty string.' );
        }

        if ( ! is_string( $id ) || trim( $id ) === '' ) {
            throw new \InvalidArgumentException( 'Service ID must be a non-empty string.' );
        }

        $this->class_map[ ltrim( $class_name, '\\' ) ] = $id;
    }

    /**
     * Automatically resolve dependencies for a class
     *
     * @param string $class_name Fully qualified class name
     * @return object Instance of the class with dependencies resolved
     * @throws \InvalidArgumentException If class name is invalid or doesn't exist
     * @throws \Exception If circular dependency detected
     */
    private function auto_resolve( $class_name ) {
        // Validate class name
        if ( ! is_string( $class_name ) || trim( $class_name ) === '' ) {
            throw new \InvalidArgumentException( 'Class name must be a non-empty string.' );
        }

        // Nested calls key off ReflectionClass::getName(), which has no leading
        // backslash, so normalize or the resolving guard misses self-references.
        $class_name = ltrim( $class_name, '\\' );

        // Check if class exists
        if ( ! class_exists( $class_name ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers
            throw new \InvalidArgumentException( "Class does not exist: $class_name" );
        }
        
        // Check for circular dependency
        if ( isset( $this->resolving[$class_name] ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers
            throw new \Exception( "Circular dependency detected: $class_name" );
        }
        
        // Mark this class as being resolved
        $this->resolving[$class_name] = true;
        
        try {
            $reflection = new \ReflectionClass( $class_name );
            $constructor = $reflection->getConstructor();
            
            // If no constructor, just return a new instance
            if ( ! $constructor ) {
                return new $class_name();
            }
            
            // Get constructor parameters
            $parameters = $constructor->getParameters();
            $dependencies = [];
            
            foreach ( $parameters as $param ) {
                $dependency = null;
                $param_class = $param->getType() && !$param->getType()->isBuiltin() ? 
                    new \ReflectionClass( $param->getType()->getName() ) : null;
                
                // If parameter is a class, try to resolve from container
                if ( $param_class ) {
                    $param_class_name = $param_class->getName();

                    if ( $this->has( $param_class_name ) ) {
                        // Registered under its own class name
                        $dependency = $this->get( $param_class_name );
                    } elseif ( isset( $this->class_map[$param_class_name] ) ) {
                        // Registered under a short id, e.g. 'wpsl_settings'
                        $dependency = $this->get( $this->class_map[$param_class_name] );
                    } else {
                        $dependency = $this->auto_resolve( $param_class_name );
                    }
                } elseif ( $param->isDefaultValueAvailable() ) {
                    // Use default value if available
                    $dependency = $param->getDefaultValue();
                } else {
                    // Cannot resolve this dependency
                    throw new \Exception( "Cannot resolve dependency: " . $param->getName() );
                }
                
                $dependencies[] = $dependency;
            }
            
            // Create a new instance with the resolved dependencies
            return $reflection->newInstanceArgs( $dependencies );
        } finally {
            // Clean up: remove this class from the resolving list
            unset( $this->resolving[$class_name] );
        }
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed The service instance
     * @throws \Exception If service not found or a circular dependency is detected
     */
    public function get( $id ) {
        if ( ! $this->has( $id ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers
            throw new \Exception( "Service not found: $id" );
        }

        $service = $this->services[$id];

        // If shared and already instantiated, return the instance
        if ( $service['shared'] && $service['instance'] !== null ) {
            return $service['instance'];
        }

        /*
         * A shared service only lands in ['instance'] once its factory has
         * returned, so a factory resolving its way back to its own id would
         * re-enter this method until PHP runs out of memory. Nearly every
         * service is a closure and never reaches auto_resolve(), so the guard
         * has to sit here too.
         */
        if ( isset( $this->resolving_ids[$id] ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message for developers
            throw new \Exception( "Circular dependency detected: $id" );
        }

        $this->resolving_ids[$id] = true;

        try {
            // Resolve the concrete implementation
            $concrete = $service['concrete'];
            $instance = is_callable( $concrete ) ? $concrete( $this ) : $concrete;

            // If shared, store the instance
            if ( $service['shared'] ) {
                $this->services[$id]['instance'] = $instance;
            }
        } finally {
            unset( $this->resolving_ids[$id] );
        }

        return $instance;
    }

    /**
     * Check if a service exists in the container
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has( $id ) {
        return isset( $this->services[$id] );
    }

    /**
     * Remove a service from the container
     *
     * @param string $id Service identifier
     * @return void
     */
    public function remove( $id ) {
        unset( $this->services[$id] );
    }

     /**
     * Register a service provider
     *
     * @param \WPSL\Core\Providers\Service_Provider $provider
     * @return $this
     */
    public function register_provider( $provider ) {
        $provider->register( $this );

        return $this;
    }

    /**
     * Get all registered service IDs (for debugging)
     *
     * @since 3.0.0
     * @return array List of registered service IDs
     */
    public function get_registered_services() {
        return array_keys( $this->services );
    }

    /**
     * Get service information (for debugging)
     *
     * @since 3.0.0
     * @param string $id Service identifier
     * @return array|null Service info or null if not found
     */
    public function get_service_info( $id ) {
        if ( ! $this->has( $id ) ) {
            return null;
        }
        
        return [
            'id' => $id,
            'shared' => $this->services[$id]['shared'],
            'instantiated' => $this->services[$id]['instance'] !== null,
            'type' => is_callable( $this->services[$id]['concrete'] ) ? 'factory' : 'instance'
        ];
    }

    /**
     * Reset the container (primarily for testing)
     * WARNING: This will remove all registered services
     *
     * @since 3.0.0
     * @return void
     */
    public function reset() {
        $this->services = [];
        $this->resolving = [];
        $this->resolving_ids = [];
        $this->class_map = [];
    }
}