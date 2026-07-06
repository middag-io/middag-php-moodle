<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Persistence;

use Middag\Framework\Database\Contract\VersionTrackerInterface;
use Middag\Moodle\Persistence\VersionTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test VersionTracker.
 *
 * Backed by Moodle's get_config/set_config (stubbed in tests/bootstrap.php,
 * reading/writing $GLOBALS['__middag_test_config']).
 *
 * @internal
 */
#[CoversClass(VersionTracker::class)]
final class VersionTrackerCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_config'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_config']);
    }

    #[Test]
    public function getVersionReturnsStoredValueAsInt(): void
    {
        $GLOBALS['__middag_test_config']['schema_core_version'] = '2026010100';

        $tracker = new VersionTracker('local_core', 'schema_core_version');

        $this->assertSame(2026010100, $tracker->getVersion());
    }

    #[Test]
    public function getVersionDefaultsToZeroWhenUnset(): void
    {
        $tracker = new VersionTracker('local_core', 'schema_core_version');

        $this->assertSame(0, $tracker->getVersion());
    }

    #[Test]
    public function setVersionPersistsUnderTheConfiguredName(): void
    {
        $tracker = new VersionTracker('local_core', 'schema_core_version');

        $tracker->setVersion(2026070600);

        $this->assertSame(2026070600, $GLOBALS['__middag_test_config']['schema_core_version']);
        $this->assertSame(2026070600, $tracker->getVersion());
    }

    #[Test]
    public function implementsVersionTrackerInterface(): void
    {
        $this->assertInstanceOf(
            VersionTrackerInterface::class,
            new VersionTracker('local_core', 'schema_core_version')
        );
    }
}
