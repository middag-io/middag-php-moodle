<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Service;

use Middag\Moodle\Contract\PlatformServiceInterface as platform_service_interface;
use Middag\Moodle\Dto\PluginDto as plugin_dto;
use Middag\Moodle\Support\CheckSupport as check_support;
use Middag\Moodle\Support\PluginSupport as plugin_support;
use Middag\Moodle\Support\VersionSupport as version_support;

/**
 * Platform service — centralized Moodle version and feature checks.
 *
 * Moodle-specific service: delegates to version_support + check_support + plugin_support.
 * Centralizes version-gated feature decisions that are currently scattered across extensions.
 *
 * @internal
 *
 * @see platform_service_interface
 */
class PlatformService implements platform_service_interface
{
    public function __construct(
        private readonly plugin_support $pluginSupport,
    ) {}

    public function version(): string
    {
        return version_support::versionSemver();
    }

    public function branch(): int
    {
        return version_support::branch();
    }

    public function atLeast(string $min): bool
    {
        return version_support::atLeast($min);
    }

    public function between(string $min, string $max): bool
    {
        return version_support::between($min, $max);
    }

    public function supports(string $feature, array $matrix): bool
    {
        return version_support::supports($feature, $matrix);
    }

    public function assertMin(string $min, ?string $message = null): void
    {
        version_support::assertMin($min, $message);
    }

    public function getPluginInfo(string $type, string $plugin): ?plugin_dto
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
        return check_support::runCheck($classname);
    }
}
