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

use Middag\Moodle\Persistence\UpgradeHelper;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test UpgradeHelper.
 *
 * Step markers persist through get_config/set_config (stubbed in
 * tests/bootstrap.php); the component is ComponentContext::name()
 * ('local_example', configured by the bootstrap). Row migration is driven by a
 * mocked moodle_database.
 *
 * @internal
 */
#[CoversClass(UpgradeHelper::class)]
final class UpgradeHelperCoverageTest extends TestCase
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
    public function stepCompletedIsFalseForAnUnrecordedStep(): void
    {
        $this->assertFalse(UpgradeHelper::stepCompleted('add_index'));
    }

    #[Test]
    public function stepCompleteMarksTheStepSoItReportsCompleted(): void
    {
        UpgradeHelper::stepComplete('add_index');

        $this->assertArrayHasKey('upgrade_step_add_index', $GLOBALS['__middag_test_config']);
        $this->assertTrue(UpgradeHelper::stepCompleted('add_index'));
    }

    #[Test]
    public function normalizeExtensionTypesMigratesMatchingRows(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('count_records')->willReturn(3);
        $db->expects($this->once())->method('set_field')
            ->with('mdl_items', 'type', 'new', ['type' => 'old']);

        $result = UpgradeHelper::normalizeExtensionTypes($db, ['old' => 'new'], 'mdl_items');

        $this->assertSame(3, $result['migrated']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function normalizeExtensionTypesSkipsRenameWhenNoRowsMatch(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('count_records')->willReturn(0);
        $db->expects($this->never())->method('set_field');

        $result = UpgradeHelper::normalizeExtensionTypes($db, ['old' => 'new'], 'mdl_items');

        $this->assertSame(0, $result['migrated']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function normalizeExtensionTypesCollectsErrorsFromFailedRenames(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('count_records')->willThrowException(new RuntimeException('db down'));

        $result = UpgradeHelper::normalizeExtensionTypes($db, ['old' => 'new'], 'mdl_items');

        $this->assertSame(0, $result['migrated']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('old→new', $result['errors'][0]);
        $this->assertStringContainsString('db down', $result['errors'][0]);
    }
}
