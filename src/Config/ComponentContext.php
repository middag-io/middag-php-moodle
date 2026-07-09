<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Config;

use Middag\Moodle\Exception\MoodleConfigurationException;
use Middag\Moodle\Runtime\ContainerFactory;

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

    /** @codeCoverageIgnore Static-only class; the private constructor exists solely to bar instantiation. */
    private function __construct() {}

    /**
     * Configure the adapter for the running plugin. Called once by the product
     * composition root during bootstrap.
     *
     * @param string      $componentName    Moodle frankenstyle component identifier
     * @param null|string $autoloadFunction Plugin autoload function; defaults to
     *                                      {@code {componentName}_autoload} when null
     *
     * @throws MoodleConfigurationException when $componentName is empty
     */
    public static function configure(string $componentName, ?string $autoloadFunction = null): void
    {
        if ($componentName === '') {
            throw new MoodleConfigurationException('Moodle adapter component name must not be empty.');
        }

        self::$componentName = $componentName;
        self::$autoloadFunction = ($autoloadFunction === null || $autoloadFunction === '')
            ? $componentName . '_autoload'
            : $autoloadFunction;
    }

    /**
     * Resolve the configured component name.
     *
     * @throws MoodleConfigurationException when the product composition root has not configured the adapter
     */
    public static function name(): string
    {
        return self::$componentName ?? throw new MoodleConfigurationException(
            'Moodle adapter component is not configured. The product composition root must call '
            . self::class . '::configure() during bootstrap (e.g. alongside ContainerFactory::setBuilder()).'
        );
    }

    /**
     * Derive the capability component from the frankenstyle name.
     *
     * Moodle capability strings are {@code {type}/{plugin}:{name}} while the
     * component is {@code {type}_{plugin}}; the two differ only in the type
     * separator. For {@code local_middag} this yields {@code local/middag},
     * for {@code mod_unidade} it yields {@code mod/unidade}. Only the first
     * underscore (the plugin-type separator) is rewritten, matching the
     * {@code local_*}/{@code mod_*} plugin convention this adapter targets.
     *
     * @throws MoodleConfigurationException when the product composition root has not configured the adapter
     */
    public static function capabilityComponent(): string
    {
        return preg_replace('/_/', '/', self::name(), 1) ?? self::name();
    }

    /**
     * Derive the plugin's web entry-point base path from the frankenstyle name,
     * e.g. {@code local_middag} → {@code /local/middag}. Used to build router
     * base URLs and redirect targets without hard-coding a product component.
     *
     * @throws MoodleConfigurationException when the product composition root has not configured the adapter
     */
    public static function baseUrlPath(): string
    {
        return '/' . self::capabilityComponent();
    }

    /**
     * Resolve the plugin autoload function name.
     *
     * @throws MoodleConfigurationException when the product composition root has not configured the adapter
     */
    public static function autoloadFunction(): string
    {
        self::name();

        return self::$autoloadFunction ?? throw new MoodleConfigurationException(
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
