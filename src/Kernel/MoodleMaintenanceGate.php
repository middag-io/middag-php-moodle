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

use Middag\Framework\Kernel\Bootstrap\NullMaintenanceGate;
use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;

use function function_exists;

/**
 * Moodle maintenance gate.
 *
 * Reports whether Moodle is mid-upgrade / mid-install so the kernel stands down
 * instead of booting modules/routes against a half-upgraded core.
 *
 * Probes, in order:
 *   - `$CFG->upgraderunning` (set by Moodle while a core/plugin upgrade is in flight);
 *   - `during_initial_install()` (true while the site has no config table yet —
 *     the very first install run).
 *
 * Replaces the framework's default {@see NullMaintenanceGate}
 * (which always reports "not under maintenance") for the Moodle host.
 */
final class MoodleMaintenanceGate implements MaintenanceGateInterface
{
    public function isUnderMaintenance(): bool
    {
        global $CFG;

        if (isset($CFG->upgraderunning) && $CFG->upgraderunning) {
            return true;
        }

        return function_exists('during_initial_install') && during_initial_install();
    }
}
