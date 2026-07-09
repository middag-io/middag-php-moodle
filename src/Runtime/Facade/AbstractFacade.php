<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Runtime\Facade;

use BadMethodCallException;
use Exception;
use Middag\Framework\Kernel\Contract\FacadeInterface;
use Middag\Framework\Kernel\Contract\KernelInterface;
use Middag\Moodle\Runtime\Kernel;
use RuntimeException;

/**
 * Base Facade class (Laravel-like, adapted for Moodle + Kernel container).
 *
 * Acts as the internal proxy layer that resolves instances from the Kernel container.
 * External extensions must rely on the public SDK facades instead of this base.
 *
 * @internal
 *
 * @see FacadeInterface
 */
abstract class AbstractFacade implements FacadeInterface
{
    /**
     * The resolved object instances (cached roots).
     *
     * @var array<string, object>
     */
    protected static array $resolvedInstances = [];

    /**
     * Indicates if resolved instances should be cached.
     */
    protected static bool $cached = true;

    /**
     * Dynamically handle static method calls on the facade proxy.
     *
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     *
     * @throws BadMethodCallException when the method does not exist on the root instance
     * @throws RuntimeException       when the facade root is not available
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        if (!method_exists($instance, $method)) {
            // Support for macroable or magic call on the underlying instance if needed
            if (method_exists($instance, '__call')) {
                return $instance->__call($method, $args);
            }

            throw new BadMethodCallException(sprintf('Method %s does not exist in %s.', $method, $instance::class));
        }

        return $instance->{$method}(...$args);
    }

    /**
     * Get the registered service ID or class name that the facade resolves.
     *
     * @return string
     */
    abstract public static function getFacadeAccessor(): string;

    /**
     * Get the root object behind the facade.
     *
     * @return object
     */
    public static function getFacadeRoot(): object
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * Swap a given instance for testing or runtime overriding.
     *
     * @param object $instance
     */
    public static function swap(object $instance): void
    {
        $name = static::getFacadeAccessor();
        static::$resolvedInstances[$name] = $instance;

        // Override in Kernel as well
        Kernel::get(KernelInterface::class)->instance($name, $instance);
    }

    /**
     * Clear one cached facade instance.
     *
     * @param string $name
     */
    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstances[$name]);
    }

    /**
     * Clear all cached facade instances.
     */
    public static function clearresolvedInstances(): void
    {
        static::$resolvedInstances = [];
    }

    /**
     * Disable instance caching (useful for testing).
     */
    public static function disableCache(): void
    {
        static::$cached = false;
        static::$resolvedInstances = [];
    }

    /**
     * Enable instance caching.
     */
    public static function enableCache(): void
    {
        static::$cached = true;
    }

    /**
     * Reset the facade: clear cache and re-enable caching.
     */
    public static function reset(): void
    {
        static::$cached = true;
        static::$resolvedInstances = [];
    }

    /**
     * Resolve the instance from the Kernel container.
     *
     * @param string $name service identifier or class name registered in the container
     *
     * @return object
     *
     * @psalm-return object
     *
     * @throws RuntimeException when the facade root cannot be resolved
     */
    protected static function resolveFacadeInstance(string $name): object
    {
        // return cached instance
        if (static::$cached && isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }

        try {
            $instance = Kernel::get($name);

            if (static::$cached) {
                static::$resolvedInstances[$name] = $instance;
            }

            return $instance;
        } catch (Exception $exception) {
            throw new RuntimeException(
                sprintf('Facade root [%s] not found in container (%s).', $name, static::class),
                0,
                $exception
            );
        }
    }
}
