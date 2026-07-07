<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Completion;

use Middag\Moodle\Domain\Completion\CompletionCriteriaDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(CompletionCriteriaDto::class)]
final class CompletionCriteriaDtoCoverageTest extends TestCase
{
    #[Test]
    public function constructorDefaults(): void
    {
        $dto = new CompletionCriteriaDto();

        self::assertNull($dto->id);
        self::assertSame(0, $dto->courseid);
        self::assertSame('', $dto->criteriaType);
        self::assertNull($dto->moduleinstance);
        self::assertNull($dto->courseinstance);
        self::assertNull($dto->enrolperiod);
        self::assertNull($dto->timeend);
        self::assertNull($dto->gradepass);
        self::assertNull($dto->role);
    }

    #[Test]
    public function constructorAcceptsAllArgs(): void
    {
        $dto = new CompletionCriteriaDto(
            id: 3,
            courseid: 10,
            criteriaType: 'activity',
            moduleinstance: 55,
            courseinstance: 66,
            enrolperiod: 86400,
            timeend: 1700,
            gradepass: 60.0,
            role: 5,
        );

        self::assertSame(3, $dto->id);
        self::assertSame(10, $dto->courseid);
        self::assertSame('activity', $dto->criteriaType);
        self::assertSame(55, $dto->moduleinstance);
        self::assertSame(66, $dto->courseinstance);
        self::assertSame(86400, $dto->enrolperiod);
        self::assertSame(1700, $dto->timeend);
        self::assertSame(60.0, $dto->gradepass);
        self::assertSame(5, $dto->role);
    }

    #[Test]
    public function isActivityBasedTrueWhenModuleinstancePositive(): void
    {
        self::assertTrue((new CompletionCriteriaDto(moduleinstance: 4))->isActivityBased());
    }

    #[Test]
    public function isActivityBasedFalseWhenModuleinstanceNull(): void
    {
        self::assertFalse((new CompletionCriteriaDto())->isActivityBased());
    }

    #[Test]
    public function isActivityBasedFalseWhenModuleinstanceZero(): void
    {
        self::assertFalse((new CompletionCriteriaDto(moduleinstance: 0))->isActivityBased());
    }

    #[Test]
    public function isCourseBasedTrueWhenCourseinstancePositive(): void
    {
        self::assertTrue((new CompletionCriteriaDto(courseinstance: 7))->isCourseBased());
    }

    #[Test]
    public function isCourseBasedFalseWhenCourseinstanceNull(): void
    {
        self::assertFalse((new CompletionCriteriaDto())->isCourseBased());
    }

    #[Test]
    public function isCourseBasedFalseWhenCourseinstanceZero(): void
    {
        self::assertFalse((new CompletionCriteriaDto(courseinstance: 0))->isCourseBased());
    }

    #[Test]
    public function toArrayMapsToSnakeCaseKeys(): void
    {
        $dto = new CompletionCriteriaDto(
            id: 3,
            courseid: 10,
            criteriaType: 'grade',
            moduleinstance: 55,
            courseinstance: 66,
            enrolperiod: 86400,
            timeend: 1700,
            gradepass: 60.0,
            role: 5,
        );

        self::assertSame([
            'id' => 3,
            'courseid' => 10,
            'criteria_type' => 'grade',
            'moduleinstance' => 55,
            'courseinstance' => 66,
            'enrolperiod' => 86400,
            'timeend' => 1700,
            'gradepass' => 60.0,
            'role' => 5,
        ], $dto->toArray());
    }

    #[Test]
    public function toObjectMirrorsToArray(): void
    {
        $dto = new CompletionCriteriaDto(courseid: 10, criteriaType: 'self');

        $obj = $dto->toObject();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertNull($obj->id);
        self::assertSame(10, $obj->courseid);
        self::assertSame('self', $obj->criteria_type);
        self::assertNull($obj->moduleinstance);
        self::assertNull($obj->courseinstance);
        self::assertNull($obj->enrolperiod);
        self::assertNull($obj->timeend);
        self::assertNull($obj->gradepass);
        self::assertNull($obj->role);
    }
}
