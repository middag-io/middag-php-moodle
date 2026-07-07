<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel;

use Middag\Framework\Kernel\Contract\MaintenanceGateInterface;
use Middag\Moodle\Kernel\MoodleMaintenanceGate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * MoodleMaintenanceGate reports mid-upgrade / mid-install through two probes:
 * the global $CFG->upgraderunning flag and Moodle's during_initial_install().
 * Both are driven from globals ($CFG and $GLOBALS['__middag_test_during_initial_install'])
 * so every branch is exercised without a Moodle runtime; setUp/tearDown save
 * and restore the shared globals to keep state clean.
 *
 * @internal
 */
#[CoversClass(MoodleMaintenanceGate::class)]
final class MoodleMaintenanceGateCoverageTest extends TestCase
{
    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        unset($GLOBALS['CFG'], $GLOBALS['__middag_test_during_initial_install']);
    }

    protected function tearDown(): void
    {
        if ($this->prevCfg === null) {
            unset($GLOBALS['CFG']);
        } else {
            $GLOBALS['CFG'] = $this->prevCfg;
        }

        unset($GLOBALS['__middag_test_during_initial_install']);
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(MaintenanceGateInterface::class, new MoodleMaintenanceGate());
    }

    #[Test]
    public function reportsMaintenanceWhenUpgradeIsRunning(): void
    {
        // First probe: $CFG->upgraderunning set and truthy → short-circuit true,
        // never reaching the during_initial_install() probe.
        $GLOBALS['CFG'] = new stdClass();
        $GLOBALS['CFG']->upgraderunning = true;
        $GLOBALS['__middag_test_during_initial_install'] = false;

        self::assertTrue((new MoodleMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function fallsThroughWhenUpgradeFlagIsSetButFalsy(): void
    {
        // isset() is true but the flag is falsy → the && short-circuits false
        // and execution continues to the second (install) probe, which is also
        // false here → not under maintenance.
        $GLOBALS['CFG'] = new stdClass();
        $GLOBALS['CFG']->upgraderunning = false;
        $GLOBALS['__middag_test_during_initial_install'] = false;

        self::assertFalse((new MoodleMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function reportsNoMaintenanceWhenNeitherProbeTrips(): void
    {
        // $CFG->upgraderunning unset → isset() short-circuits the first probe;
        // during_initial_install() returns false → not under maintenance.
        $GLOBALS['CFG'] = new stdClass();
        $GLOBALS['__middag_test_during_initial_install'] = false;

        self::assertFalse((new MoodleMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function reportsMaintenanceDuringTheInitialInstall(): void
    {
        // Upgrade flag absent, so the second probe decides: during_initial_install()
        // true (fresh site with no config table yet) → under maintenance.
        $GLOBALS['CFG'] = new stdClass();
        $GLOBALS['__middag_test_during_initial_install'] = true;

        self::assertTrue((new MoodleMaintenanceGate())->isUnderMaintenance());
    }

    #[Test]
    public function toleratesAnEntirelyAbsentConfigObject(): void
    {
        // No global $CFG at all: isset($CFG->upgraderunning) is false without a
        // warning, and the install probe governs the result.
        unset($GLOBALS['CFG']);
        $GLOBALS['__middag_test_during_initial_install'] = false;

        self::assertFalse((new MoodleMaintenanceGate())->isUnderMaintenance());
    }
}
