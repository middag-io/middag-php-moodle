<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Persistence;

use Middag\Framework\Database\Contract\VersionTrackerInterface;

/**
 * Moodle get_config/set_config backed version tracker.
 *
 * Each lib registers its own tracker with a distinct config key so versions
 * are stored independently (e.g. 'schema_core_version', 'schema_framework_version').
 *
 * @internal
 */
class VersionTracker implements VersionTrackerInterface
{
    public function __construct(
        private readonly string $component,
        private readonly string $name,
    ) {}

    public function getVersion(): int
    {
        return (int) (get_config($this->component, $this->name) ?: 0);
    }

    public function setVersion(int $version): void
    {
        set_config($this->name, $version, $this->component);
    }
}
