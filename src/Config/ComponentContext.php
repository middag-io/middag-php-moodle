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

use Middag\Framework\Kernel\HostContext;
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
 * Public surface: it is a documented composition-root seam, and the host glue
 * layered above this lib resolves the running component through {@see self::name()}.
 *
 * One MIDDAG product per site: this is a single process-wide slot, the same
 * single-host model as {@see HostContext}. Moodle
 * includes the {@code lib.php} of every plugin that declares a callback in use,
 * and cron runs the tasks of every plugin in one process, so two MIDDAG products
 * on one site would share this slot. The first {@see self::configure()} stays:
 * configuring the same component and autoload function again is a no-op, while
 * another component, or the same component with another autoload function,
 * throws instead of silently scoping capability checks, base URLs and
 * config/cache/lang access to the other product. A per-component registry may
 * replace this slot in a later minor without breaking the static API.
 *
 * Call {@see self::configure()} from the plugin's entry points (page, task, CLI
 * script, upgrade step) and let the exception surface there. A plugin's
 * {@code lib.php} is included on every page, so a fatal there breaks the whole
 * site: if {@code lib.php} configures the adapter, catch the exception, log a
 * warning and resolve nothing through the adapter in that request — the slot
 * belongs to the other product.
 *
 * @api
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
     * Configure the adapter for the running plugin. Called by the product
     * composition root during bootstrap; the first configuration of the process
     * stays (one MIDDAG product per site, see the class docblock).
     *
     * @param string      $componentName    Moodle frankenstyle component identifier
     * @param null|string $autoloadFunction Plugin autoload function; defaults to
     *                                      {@code {componentName}_autoload} when null or empty
     *
     * @throws MoodleConfigurationException when $componentName is empty, or when this process
     *                                      already holds another component or another autoload function
     */
    public static function configure(string $componentName, ?string $autoloadFunction = null): void
    {
        if ($componentName === '') {
            throw new MoodleConfigurationException('Moodle adapter component name must not be empty.');
        }

        $resolvedAutoload = ($autoloadFunction === null || $autoloadFunction === '')
            ? $componentName . '_autoload'
            : $autoloadFunction;

        if (self::$componentName !== null) {
            self::assertSameConfiguration(self::$componentName, (string) self::$autoloadFunction, $componentName, $resolvedAutoload);

            return;
        }

        self::$componentName = $componentName;
        self::$autoloadFunction = $resolvedAutoload;
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

    /**
     * Refuse any configuration other than the one this process already holds.
     *
     * @throws MoodleConfigurationException when the component or the autoload function differs
     */
    private static function assertSameConfiguration(
        string $currentComponent,
        string $currentAutoload,
        string $componentName,
        string $autoloadFunction,
    ): void {
        if ($currentComponent !== $componentName) {
            throw new MoodleConfigurationException(sprintf(
                'Cannot configure the Moodle adapter for %s: another MIDDAG product (%s) already configured '
                . 'this process; one MIDDAG product per site is supported.',
                $componentName,
                $currentComponent,
            ));
        }

        if ($currentAutoload !== $autoloadFunction) {
            throw new MoodleConfigurationException(sprintf(
                'The Moodle adapter for %s is already configured in this process with the autoload function %s, '
                . 'so it cannot switch to %s; the first configuration stays.',
                $componentName,
                $currentAutoload,
                $autoloadFunction,
            ));
        }
    }
}
