<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use dml_exception;
use Middag\Moodle\Domain\Grade\Grade;
use Middag\Moodle\Domain\Grade\GradeItem;
use Middag\Moodle\Support\GradeSupport;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GradeSupport wraps $DB grade reads. $DB is a mocked moodle_database; the
 * gradable-item probe (record_exists) is a central-stub gap tracked in
 * centralStubNeeds, so that branch auto-activates once the method lands.
 *
 * @internal
 */
#[CoversClass(GradeSupport::class)]
final class GradeSupportCoverageTest extends TestCase
{
    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
    }

    #[Test]
    public function testGetGradeUniqueReturnsAnIntegerForAnIntegralNumericField(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record_sql')->willReturn((object) ['finalgrade' => '90']);
        $GLOBALS['DB'] = $db;

        self::assertSame(90, GradeSupport::getGrade(1, 2, 'finalgrade', null, true));
    }

    #[Test]
    public function testGetGradeUniqueReturnsAFloatForAFractionalNumericField(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record_sql')->willReturn((object) ['finalgrade' => '90.5']);
        $GLOBALS['DB'] = $db;

        self::assertSame(90.5, GradeSupport::getGrade(1, 2, 'finalgrade', 'mod', true));
    }

    #[Test]
    public function testGetGradeUniqueReturnsTheRawValueForANonNumericField(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record_sql')->willReturn((object) ['feedback' => 'Well done']);
        $GLOBALS['DB'] = $db;

        self::assertSame('Well done', GradeSupport::getGrade(1, 2, 'feedback', null, true));
    }

    #[Test]
    public function testGetGradeUniqueRejectsAMultiFieldSelector(): void
    {
        $db = $this->createStub(moodle_database::class);
        $GLOBALS['DB'] = $db;

        self::assertNull(GradeSupport::getGrade(1, 2, 'a, b', null, true));
    }

    #[Test]
    public function testGetGradeUniqueRejectsTheWildcardSelector(): void
    {
        $db = $this->createStub(moodle_database::class);
        $GLOBALS['DB'] = $db;

        self::assertNull(GradeSupport::getGrade(1, 2, '*', null, true));
    }

    #[Test]
    public function testGetGradeUniqueReturnsNullWhenNoRecordMatches(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record_sql')->willReturn(false);
        $GLOBALS['DB'] = $db;

        self::assertNull(GradeSupport::getGrade(1, 2, 'finalgrade', null, true));
    }

    #[Test]
    public function testGetGradeUniqueReturnsNullWhenTheFieldIsAbsentFromTheRecord(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record_sql')->willReturn((object) ['other' => '1']);
        $GLOBALS['DB'] = $db;

        self::assertNull(GradeSupport::getGrade(1, 2, 'finalgrade', null, true));
    }

    #[Test]
    public function testGetGradeReturnsNormalizedRecordsWhenNotUnique(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records_sql')->willReturn([
            (object) ['itemid' => '3', 'userid' => '7', 'finalgrade' => '80'],
            (object) ['finalgrade' => '55'],
        ]);
        $GLOBALS['DB'] = $db;

        $records = GradeSupport::getGrade(10, 7);

        self::assertIsArray($records);
        self::assertSame(3, $records[0]->itemid);
        self::assertSame(7, $records[0]->userid);
        self::assertSame('55', $records[1]->finalgrade);
    }

    #[Test]
    public function testGetGradeReturnsEmptyArrayWhenTheReadThrows(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records_sql')->willThrowException(new dml_exception('readfailed'));
        $GLOBALS['DB'] = $db;

        self::assertSame([], GradeSupport::getGrade(10, 7));
    }

    #[Test]
    public function testGetItemMapsARecordToAGradeItemEntity(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturn((object) ['id' => 4, 'courseid' => 10, 'itemtype' => 'mod']);
        $GLOBALS['DB'] = $db;

        $item = GradeSupport::getItem(4);

        self::assertInstanceOf(GradeItem::class, $item);
        self::assertSame('mod', $item->itemtype);
    }

    #[Test]
    public function testGetItemReturnsNullWhenTheRecordIsMissing(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturn(false);
        $GLOBALS['DB'] = $db;

        self::assertNull(GradeSupport::getItem(404));
    }

    #[Test]
    public function testGetItemReturnsNullWhenTheReadThrows(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willThrowException(new dml_exception('readfailed'));
        $GLOBALS['DB'] = $db;

        self::assertNull(GradeSupport::getItem(4));
    }

    #[Test]
    public function testGetUserGradesForCourseIndexesEntitiesByItemId(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records_sql')->willReturn([
            (object) ['id' => 1, 'itemid' => '31', 'userid' => 7, 'finalgrade' => 80.0],
            (object) ['id' => 2, 'itemid' => '32', 'userid' => 7, 'finalgrade' => 90.0],
        ]);
        $GLOBALS['DB'] = $db;

        $grades = GradeSupport::getUserGradesForCourse(10, 7);

        self::assertArrayHasKey(31, $grades);
        self::assertInstanceOf(Grade::class, $grades[31]);
        self::assertSame(32, $grades[32]->itemid);
    }

    #[Test]
    public function testGetUserGradesForCourseReturnsEmptyArrayWhenTheReadThrows(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records_sql')->willThrowException(new dml_exception('readfailed'));
        $GLOBALS['DB'] = $db;

        self::assertSame([], GradeSupport::getUserGradesForCourse(10, 7));
    }

    #[Test]
    public function testIsCourseGradableReturnsTrueWhenGradableItemsExist(): void
    {
        if (!method_exists(moodle_database::class, 'record_exists')) {
            self::markTestSkipped('central moodle_database stub lacks record_exists() — see centralStubNeeds');
        }

        $db = $this->createMock(moodle_database::class);
        $db->method('record_exists')->willReturn(true);
        $GLOBALS['DB'] = $db;

        self::assertTrue(GradeSupport::isCourseGradable(5));
    }

    #[Test]
    public function testIsCourseGradableReturnsFalseWhenTheReadThrows(): void
    {
        if (!method_exists(moodle_database::class, 'record_exists')) {
            self::markTestSkipped('central moodle_database stub lacks record_exists() — see centralStubNeeds');
        }

        $db = $this->createMock(moodle_database::class);
        $db->method('record_exists')->willThrowException(new dml_exception('readfailed'));
        $GLOBALS['DB'] = $db;

        self::assertFalse(GradeSupport::isCourseGradable(5));
    }
}
