<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Course;

use Middag\Moodle\Domain\Course\Course;
use Middag\Moodle\Domain\Course\CourseDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Complements {@see CourseDtoTest} by covering the one branch it omits:
 * the {@see CourseDto::fromEntity()} projection from a full Course entity.
 *
 * @internal
 */
#[CoversClass(CourseDto::class)]
final class CourseDtoCoverageTest extends TestCase
{
    #[Test]
    public function fromEntityProjectsEveryMappedField(): void
    {
        $course = Course::fromRecord([
            'id' => 7,
            'fullname' => 'Biology',
            'shortname' => 'BIO1',
            'category' => 3,
            'idnumber' => 'ID-7',
            'format' => 'weeks',
            'visible' => 0,
            'startdate' => 111,
            'enddate' => 222,
        ]);

        $dto = CourseDto::fromEntity($course);

        $this->assertSame(7, $dto->id);
        $this->assertSame('Biology', $dto->fullname);
        $this->assertSame('BIO1', $dto->shortname);
        $this->assertSame(3, $dto->category);
        $this->assertSame('ID-7', $dto->idnumber);
        $this->assertSame('weeks', $dto->format);
        $this->assertSame(0, $dto->visible);
        $this->assertSame(111, $dto->startdate);
        $this->assertSame(222, $dto->enddate);
    }

    #[Test]
    public function fromEntityUsesEntityDefaultsWhenUnset(): void
    {
        // A minimal course (only id set) exercises fromEntity against the
        // entity's own property defaults: idnumber '' and format 'topics'.
        $course = Course::fromRecord(['id' => 9]);

        $dto = CourseDto::fromEntity($course);

        $this->assertSame(9, $dto->id);
        $this->assertSame('', $dto->fullname);
        $this->assertSame('', $dto->shortname);
        $this->assertSame(0, $dto->category);
        $this->assertSame('', $dto->idnumber);
        $this->assertSame('topics', $dto->format);
        $this->assertSame(1, $dto->visible);
        $this->assertSame(0, $dto->startdate);
        $this->assertSame(0, $dto->enddate);
    }
}
