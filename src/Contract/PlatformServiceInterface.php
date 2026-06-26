<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

use Middag\Framework\Exception\MiddagInfrastructureException as middag_infrastructure_exception;
use Middag\Moodle\Dto\PluginDto as plugin_dto;

/**
 * Platform service contract — centralized Moodle version and feature checks.
 *
 * Composes version_support + check_support + plugin_support into a single API
 * for version-gated feature decisions. Core to the framework's promise:
 * "don't worry about Moodle updates" (ADR-103).
 *
 * @api
 */
interface PlatformServiceInterface
{
    /**
     * Moodle version as semver string (e.g. '4.5.1').
     */
    public function version(): string;

    /**
     * Moodle branch as integer (e.g. 405 for 4.5).
     */
    public function branch(): int;

    /**
     * Whether the current Moodle version is at least $min.
     *
     * @param string $min Semver string (e.g. '4.5')
     */
    public function atLeast(string $min): bool;

    /**
     * Whether the current Moodle version is between $min and $max (inclusive).
     */
    public function between(string $min, string $max): bool;

    /**
     * Whether a feature is supported on the current Moodle version.
     *
     * @param string                $feature Feature identifier
     * @param array<string, string> $matrix  Map of feature → minimum Moodle version
     */
    public function supports(string $feature, array $matrix): bool;

    /**
     * Assert that Moodle meets a minimum version, or throw.
     *
     * @throws middag_infrastructure_exception
     */
    public function assertMin(string $min, ?string $message = null): void;

    /**
     * Get information about a specific plugin.
     *
     * @param string $type   Plugin type (e.g. 'local', 'mod')
     * @param string $plugin Plugin name (e.g. 'middag', 'forum')
     */
    public function getPluginInfo(string $type, string $plugin): ?plugin_dto;

    /**
     * Whether a plugin is installed and enabled.
     */
    public function pluginExists(string $type, string $plugin): bool;

    /**
     * Run a health check and return its result.
     *
     * @return null|array{id: string, name: string, status: string, summary: string}
     */
    public function runCheck(string $classname): ?array;
}
