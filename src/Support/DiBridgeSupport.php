<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * DI bridge for exposing framework services to Moodle's PSR-11 container.
 *
 * Listens to \core\hook\di_configuration and registers selected @api services
 * from the framework container into Moodle's DI. This enables:
 * - Moodle-native route controllers (ADR-206) to inject MIDDAG services
 * - External plugins to consume MIDDAG services via \core\di::get()
 *
 * Only services classified as @api (ADR-901 Group A) are exposed.
 * The framework container (ADR-601) is not altered.
 *
 * References Moodle DI classes (\core\di, \core\hook\di_configuration) via
 * strings only, never via use statements or docblock @see tags (php-cs-fixer
 * would import those), so the class stays loadable on hosts where the hook
 * does not exist (Moodle < 4.4).
 *
 * @internal
 */
class DiBridgeSupport
{
    /** @var array<string, callable> Product-supplied service ID => factory map to expose in Moodle's DI container. */
    private static array $exports = [];

    /** @var string[] Product export ids individually confirmed in Moodle's DI builder by configure(). */
    private static array $exportedIds = [];

    /** @var bool Whether configure() has actually pushed the exports into Moodle's DI builder. */
    private static bool $configured = false;

    /**
     * Register a service export supplied by the product composition root.
     *
     * The adapter is product-agnostic and ships no exports by default; the
     * product (e.g. its plugin bootstrap) registers the @api services — including
     * its own facade — it wants exposed via Moodle's \core\di container.
     *
     * @param string   $id      fully qualified service/class id
     * @param callable $factory factory producing the service instance
     */
    public static function registerExport(string $id, callable $factory): void
    {
        self::$exports[$id] = $factory;
    }

    /**
     * Check if the Moodle DI configuration hook is available.
     */
    public static function isAvailable(): bool
    {
        return VersionSupport::supports('moodle_di_hook', [
            'moodle_di_hook' => ['since' => '4.4'],
        ]) && VersionSupport::symbolExists('core\hook\di_configuration');
    }

    /**
     * Configure Moodle's DI container with framework services.
     *
     * Called via \core\hook\di_configuration hook (registered in db/hooks.php
     * via build_statics with min_moodle: '4.4').
     *
     * @param object $hook the di_configuration hook instance
     */
    public static function configure(object $hook): void
    {
        // Per-id isolation: one export the DI builder rejects must not abort
        // the registration of every export after it in iteration order.

        // Expose product-registered @api services (including the product facade).
        foreach (self::$exports as $id => $factory) {
            try {
                $hook->add_definition($id, $factory);
                self::$exportedIds[] = $id;
            } catch (Throwable $throwable) {
                Debug::traceException($throwable);
            }
        }

        // Expose additional curated services.
        foreach (self::getExtensionExports() as $id => $factory) {
            try {
                $hook->add_definition($id, $factory);
            } catch (Throwable $throwable) {
                Debug::traceException($throwable);
            }
        }

        // Only now are the (surviving) exports genuinely present in Moodle's DI builder.
        self::$configured = true;
    }

    /**
     * Get the list of service IDs actually exposed to Moodle's DI.
     *
     * Returns the ids only after configure() has pushed them into the DI
     * builder; an id merely registered via registerExport() is NOT reported,
     * because core\di::get() cannot resolve it until configure() runs — and an
     * id the DI builder rejected during configure() is not reported either.
     * Empty on hosts where the DI hook is unavailable (configure() never
     * fires there).
     *
     * @return string[] list of fully qualified class/interface names confirmed in Moodle's DI
     */
    public static function getExportedServiceIds(): array
    {
        if (!self::$configured) {
            return [];
        }

        return self::$exportedIds;
    }

    /**
     * Collect additional service exports from extensions.
     *
     * Reserved for future use. When @api services demonstrate external
     * consumption need, this method will aggregate extension declarations
     * and expose them via Moodle's DI.
     *
     * @return array<string, callable> map of service ID => factory callable
     */
    private static function getExtensionExports(): array
    {
        return [];
    }
}
