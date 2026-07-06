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

use Middag\Moodle\Domain\Completion\CourseCompletion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CourseCompletion::class)]
final class CourseCompletionCoverageTest extends TestCase
{
    #[Test]
    public function getTableReturnsCourseCompletions(): void
    {
        self::assertSame('course_completions', CourseCompletion::getTable());
    }

    #[Test]
    public function isCompleteTrueWhenTimecompletedPositive(): void
    {
        $entity = CourseCompletion::fromRecord(['timecompleted' => 1700]);

        self::assertTrue($entity->isComplete());
    }

    #[Test]
    public function isCompleteFalseWhenTimecompletedNull(): void
    {
        $entity = CourseCompletion::fromRecord([]);

        self::assertFalse($entity->isComplete());
    }

    #[Test]
    public function isCompleteFalseWhenTimecompletedZero(): void
    {
        // Explicit 0 is non-null, so fromRecord assigns it; isComplete's > 0
        // guard then evaluates false.
        $entity = CourseCompletion::fromRecord(['timecompleted' => 0]);

        self::assertFalse($entity->isComplete());
    }

    #[Test]
    public function hasStartedTrueWhenTimestartedPositive(): void
    {
        $entity = CourseCompletion::fromRecord(['timestarted' => 10]);

        self::assertTrue($entity->hasStarted());
    }

    #[Test]
    public function hasStartedFalseWhenTimestartedZero(): void
    {
        $entity = CourseCompletion::fromRecord(['timestarted' => 0]);

        self::assertFalse($entity->hasStarted());
    }

    #[Test]
    public function isRplTrueWhenRplNonEmpty(): void
    {
        $entity = CourseCompletion::fromRecord(['rpl' => 'transfer credit']);

        self::assertTrue($entity->isRpl());
    }

    #[Test]
    public function isRplFalseWhenRplNull(): void
    {
        $entity = CourseCompletion::fromRecord([]);

        self::assertFalse($entity->isRpl());
    }

    #[Test]
    public function isRplFalseWhenRplEmptyString(): void
    {
        $entity = CourseCompletion::fromRecord(['rpl' => '']);

        self::assertFalse($entity->isRpl());
    }

    #[Test]
    public function durationToCompleteReturnsNullWhenNotComplete(): void
    {
        $entity = CourseCompletion::fromRecord(['timeenrolled' => 100]);

        self::assertNull($entity->durationToComplete());
    }

    #[Test]
    public function durationToCompleteReturnsElapsedSeconds(): void
    {
        $entity = CourseCompletion::fromRecord(['timeenrolled' => 100, 'timecompleted' => 400]);

        self::assertSame(300, $entity->durationToComplete());
    }

    #[Test]
    public function durationToCompleteClampsNegativeToZero(): void
    {
        $entity = CourseCompletion::fromRecord(['timeenrolled' => 500, 'timecompleted' => 200]);

        self::assertSame(0, $entity->durationToComplete());
    }

    #[Test]
    public function fromRecordHydratesAllFieldsWithCoercion(): void
    {
        $entity = CourseCompletion::fromRecord([
            'id' => '7',
            'userid' => '3',
            'course' => '42',
            'timeenrolled' => '100',
            'timestarted' => '150',
            'timecompleted' => '400',
            'reaggregate' => '5',
            'rpl' => 'granted',
            'rplgrade' => '88.5',
        ]);

        self::assertSame(7, $entity->get_id());
        self::assertSame(3, $entity->get_userid());
        self::assertSame(42, $entity->get_course());
        self::assertSame(100, $entity->get_timeenrolled());
        self::assertSame(150, $entity->get_timestarted());
        self::assertSame(400, $entity->get_timecompleted());
        self::assertSame(5, $entity->get_reaggregate());
        self::assertSame('granted', $entity->get_rpl());
        self::assertSame(88.5, $entity->get_rplgrade());
    }

    #[Test]
    public function fromRecordLeavesNullableFieldsNullWhenExplicitlyNull(): void
    {
        $entity = CourseCompletion::fromRecord([
            'timecompleted' => null,
            'reaggregate' => null,
            'rpl' => null,
            'rplgrade' => null,
        ]);

        self::assertNull($entity->get_timecompleted());
        self::assertNull($entity->get_reaggregate());
        self::assertNull($entity->get_rpl());
        self::assertNull($entity->get_rplgrade());
    }

    #[Test]
    public function fromRecordLeavesDefaultsWhenFieldsAbsent(): void
    {
        $entity = CourseCompletion::fromRecord([]);

        self::assertNull($entity->getId());
        self::assertSame(0, $entity->get_userid());
        self::assertSame(0, $entity->get_course());
        self::assertSame(0, $entity->get_timeenrolled());
        self::assertSame(0, $entity->get_timestarted());
        self::assertNull($entity->get_timecompleted());
        self::assertNull($entity->get_reaggregate());
        self::assertNull($entity->get_rpl());
        self::assertNull($entity->get_rplgrade());
    }

    #[Test]
    public function fromRecordAcceptsStdClassObject(): void
    {
        $entity = CourseCompletion::fromRecord((object) ['userid' => 11, 'course' => 22]);

        self::assertSame(11, $entity->get_userid());
        self::assertSame(22, $entity->get_course());
    }
}
