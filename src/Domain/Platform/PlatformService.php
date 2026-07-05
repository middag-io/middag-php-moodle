<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Platform;

use Middag\Moodle\Domain\Platform\Contract\PlatformServiceInterface;
use Middag\Moodle\Support\CheckSupport;
use Middag\Moodle\Support\PluginSupport;
use Middag\Moodle\Support\VersionSupport;

/**
 * Platform service — centralized Moodle version and feature checks.
 *
 * Moodle-specific service: delegates to VersionSupport + CheckSupport + PluginSupport.
 * Centralizes version-gated feature decisions that are currently scattered across extensions.
 *
 * @internal
 *
 * @see PlatformServiceInterface
 */
class PlatformService implements PlatformServiceInterface
{
    public function __construct(
        private readonly PluginSupport $pluginSupport,
    ) {}

    public function version(): string
    {
        return VersionSupport::versionSemver();
    }

    public function branch(): int
    {
        return VersionSupport::branch();
    }

    public function atLeast(string $min): bool
    {
        return VersionSupport::atLeast($min);
    }

    public function between(string $min, string $max): bool
    {
        return VersionSupport::between($min, $max);
    }

    public function supports(string $feature, array $matrix): bool
    {
        return VersionSupport::supports($feature, $matrix);
    }

    public function assertMin(string $min, ?string $message = null): void
    {
        VersionSupport::assertMin($min, $message);
    }

    public function getPluginInfo(string $type, string $plugin): ?PluginDto
    {
        if (!$this->pluginSupport->pluginExists($type, $plugin)) {
            return null;
        }

        return $this->pluginSupport->getPluginInfo($type, $plugin);
    }

    public function pluginExists(string $type, string $plugin): bool
    {
        return $this->pluginSupport->pluginExists($type, $plugin);
    }

    public function runCheck(string $classname): ?array
    {
        return CheckSupport::runCheck($classname);
    }
}
