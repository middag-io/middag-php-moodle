<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\NoHost;

use Middag\Moodle\Runtime\MoodleMaintenanceGate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoodleMaintenanceGate::class)]
final class MoodleMaintenanceGateNoHostTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['CFG']);
    }

    public function testNotUnderMaintenanceWhenDuringInitialInstallIsAbsent(): void
    {
        unset($GLOBALS['CFG']);

        // Without a Moodle runtime during_initial_install() does not exist, so
        // the function_exists() guard short-circuits to "not under maintenance".
        self::assertFalse((new MoodleMaintenanceGate())->isUnderMaintenance());
    }

    public function testUpgradeRunningFlagStillWinsWithoutMoodleRuntime(): void
    {
        $GLOBALS['CFG'] = (object) ['upgraderunning' => 2026071400];

        self::assertTrue((new MoodleMaintenanceGate())->isUnderMaintenance());
    }
}
