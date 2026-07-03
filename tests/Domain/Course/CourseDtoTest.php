<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace MiddagMoodleTestsDomainCourse;

use Middag\Moodle\Domain\Course\CourseDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class CourseDtoTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithRequiredArgs(): void
    {
        $dto = new CourseDto(
            id: 1,
            fullname: 'Introduction to PHP',
            shortname: 'PHP101',
            category: 5,
        );

        $this->assertSame(1, $dto->id);
        $this->assertSame('Introduction to PHP', $dto->fullname);
        $this->assertSame('PHP101', $dto->shortname);
        $this->assertSame(5, $dto->category);
    }

    #[Test]
    public function optionalFieldsHaveCorrectDefaults(): void
    {
        $dto = new CourseDto(
            id: 1,
            fullname: 'Introduction to PHP',
            shortname: 'PHP101',
            category: 5,
        );

        $this->assertNull($dto->idnumber);
        $this->assertNull($dto->format);
        $this->assertSame(1, $dto->visible);
        $this->assertSame(0, $dto->startdate);
        $this->assertSame(0, $dto->enddate);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $dto = new CourseDto(
            id: 42,
            fullname: 'Advanced Laravel',
            shortname: 'LARAVEL-ADV',
            category: 10,
            idnumber: 'COURSE-042',
            format: 'topics',
            visible: 0,
            startdate: 1700000000,
            enddate: 1703000000,
        );

        $this->assertSame(42, $dto->id);
        $this->assertSame('Advanced Laravel', $dto->fullname);
        $this->assertSame('LARAVEL-ADV', $dto->shortname);
        $this->assertSame(10, $dto->category);
        $this->assertSame('COURSE-042', $dto->idnumber);
        $this->assertSame('topics', $dto->format);
        $this->assertSame(0, $dto->visible);
        $this->assertSame(1700000000, $dto->startdate);
        $this->assertSame(1703000000, $dto->enddate);
    }

    #[Test]
    public function isVisibleReturnsTrueWhenVisibleIs1(): void
    {
        $dto = new CourseDto(id: 1, fullname: 'Test', shortname: 'T', category: 1, visible: 1);
        $this->assertTrue($dto->is_visible());
    }

    #[Test]
    public function isVisibleReturnsFalseWhenVisibleIs0(): void
    {
        $dto = new CourseDto(id: 1, fullname: 'Test', shortname: 'T', category: 1, visible: 0);
        $this->assertFalse($dto->is_visible());
    }

    #[Test]
    public function hasEndDateReturnsTrueWhenEnddateGreaterThanZero(): void
    {
        $dto = new CourseDto(id: 1, fullname: 'Test', shortname: 'T', category: 1, enddate: 1700000000);
        $this->assertTrue($dto->has_end_date());
    }

    #[Test]
    public function hasEndDateReturnsFalseWhenEnddateIsZero(): void
    {
        $dto = new CourseDto(id: 1, fullname: 'Test', shortname: 'T', category: 1, enddate: 0);
        $this->assertFalse($dto->has_end_date());
    }

    #[Test]
    public function hasEndDateReturnsFalseByDefault(): void
    {
        $dto = new CourseDto(id: 1, fullname: 'Test', shortname: 'T', category: 1);
        $this->assertFalse($dto->has_end_date());
    }

    #[Test]
    public function toArrayReturnsCompleteRepresentation(): void
    {
        $dto = new CourseDto(
            id: 42,
            fullname: 'Advanced Laravel',
            shortname: 'LARAVEL-ADV',
            category: 10,
            idnumber: 'COURSE-042',
            format: 'topics',
            visible: 0,
            startdate: 1700000000,
            enddate: 1703000000,
        );

        $expected = [
            'id' => 42,
            'fullname' => 'Advanced Laravel',
            'shortname' => 'LARAVEL-ADV',
            'category' => 10,
            'idnumber' => 'COURSE-042',
            'format' => 'topics',
            'visible' => 0,
            'startdate' => 1700000000,
            'enddate' => 1703000000,
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(CourseDto::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(CourseDto::class);
        $this->assertTrue($reflection->isFinal());
    }
}
