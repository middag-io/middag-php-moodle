<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel;

use Closure;
use Middag\Moodle\Http\Routing\MoodleRouter;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * OSS boot seam for the DI container.
 *
 * The full premium container wiring (synthetic services, persistence-scope
 * stacks, signal/hook registration) lives in a non-OSS MIDDAG product layer,
 * outside this OSS adapter. This adapter ships only the OSS boot entry the
 * {@see Kernel} calls; the product composition registers the real builder via
 * {@see self::setBuilder()} (e.g. the host plugin wires its own factory's
 * getInstance() during its bootstrap). Keeping the seam here lets the Apache
 * adapter remain free of any non-OSS MIDDAG dependency while still exposing a
 * stable boot contract.
 *
 * @internal
 */
final class ContainerFactory
{
    /** @var null|ContainerBuilder Cached built container (singleton per request). */
    private static ?ContainerBuilder $container = null;

    /**
     * @var null|Closure(Kernel, MoodleRouter): ContainerBuilder product-supplied builder
     */
    private static ?Closure $builder = null;

    private function __construct() {}

    /**
     * Register the container builder used at boot.
     *
     * The product layer (which may depend on non-OSS MIDDAG packages) calls this
     * during its own bootstrap, passing a closure that constructs and compiles
     * the fully-wired container. Keeping the closure here means the adapter never
     * names the non-OSS factory directly.
     *
     * @param Closure(Kernel, MoodleRouter): ContainerBuilder $builder
     */
    public static function setBuilder(Closure $builder): void
    {
        self::$builder = $builder;
    }

    /**
     * Build (or return the cached) container for the given kernel + router.
     *
     * @param Kernel       $kernel the kernel instance injected into the container
     * @param MoodleRouter $router the router configured for annotation scanning
     *
     * @throws RuntimeException when no builder has been registered by the product layer
     */
    public static function getInstance(Kernel $kernel, MoodleRouter $router): ContainerBuilder
    {
        if (self::$container instanceof ContainerBuilder) {
            return self::$container;
        }

        if (!self::$builder instanceof Closure) {
            throw new RuntimeException(
                'MIDDAG container builder is not registered. The product layer must call '
                . self::class . '::setBuilder() during bootstrap to supply the compiled container.'
            );
        }

        return self::$container = (self::$builder)($kernel, $router);
    }

    /**
     * Reset the cached container (test isolation / re-boot).
     */
    public static function reset(): void
    {
        self::$container = null;
    }
}
