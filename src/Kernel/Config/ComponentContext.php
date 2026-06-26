<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel\Config;

use InvalidArgumentException;
use LogicException;
use Middag\Moodle\Kernel\ContainerFactory;

/**
 * Composition-root seam for the running plugin's Moodle frankenstyle component.
 *
 * The adapter is a generic, reusable Moodle package: it must not hard-code the
 * consumer plugin's component (e.g. {@code local_example}). Instead the product
 * composition root configures this context once at boot — mirroring the
 * {@see ContainerFactory::setBuilder()} seam — and every
 * adapter helper resolves the component through {@see self::name()}.
 *
 * Required-config by design: {@see self::name()} throws when the product forgot
 * to wire it, so a misconfiguration fails loud instead of silently scoping
 * config/cache/lang/file access to a wrong (or non-existent) component.
 *
 * @internal
 */
final class ComponentContext
{
    /** @var null|string The configured frankenstyle component (e.g. {@code local_example}). */
    private static ?string $componentName = null;

    /** @var null|string The plugin autoload function name (e.g. {@code local_example_autoload}). */
    private static ?string $autoloadFunction = null;

    private function __construct() {}

    /**
     * Configure the adapter for the running plugin. Called once by the product
     * composition root during bootstrap.
     *
     * @param string      $componentName    Moodle frankenstyle component identifier
     * @param null|string $autoloadFunction Plugin autoload function; defaults to
     *                                      {@code {componentName}_autoload} when null
     *
     * @throws InvalidArgumentException when $componentName is empty
     */
    public static function configure(string $componentName, ?string $autoloadFunction = null): void
    {
        if ($componentName === '') {
            throw new InvalidArgumentException('Moodle adapter component name must not be empty.');
        }

        self::$componentName = $componentName;
        self::$autoloadFunction = ($autoloadFunction === null || $autoloadFunction === '')
            ? $componentName . '_autoload'
            : $autoloadFunction;
    }

    /**
     * Resolve the configured component name.
     *
     * @throws LogicException when the product composition root has not configured the adapter
     */
    public static function name(): string
    {
        return self::$componentName ?? throw new LogicException(
            'Moodle adapter component is not configured. The product composition root must call '
            . self::class . '::configure() during bootstrap (e.g. alongside ContainerFactory::setBuilder()).'
        );
    }

    /**
     * Resolve the plugin autoload function name.
     *
     * @throws LogicException when the product composition root has not configured the adapter
     */
    public static function autoloadFunction(): string
    {
        self::name();

        return self::$autoloadFunction ?? throw new LogicException(
            'Moodle adapter autoload function is not configured.'
        );
    }

    /**
     * Whether the adapter has been configured. Lets optional code paths degrade
     * gracefully instead of throwing.
     */
    public static function isConfigured(): bool
    {
        return self::$componentName !== null;
    }

    /**
     * Reset the configured state (test isolation / re-boot).
     */
    public static function reset(): void
    {
        self::$componentName = null;
        self::$autoloadFunction = null;
    }
}
