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

use Middag\Moodle\Domain\Completion\CompletionProgressDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(CompletionProgressDto::class)]
final class CompletionProgressDtoCoverageTest extends TestCase
{
    #[Test]
    public function constructorDefaultsAreZeroAndFalse(): void
    {
        $dto = new CompletionProgressDto();

        self::assertSame(0, $dto->courseid);
        self::assertSame(0, $dto->userid);
        self::assertSame(0, $dto->totalActivities);
        self::assertSame(0, $dto->completedActivities);
        self::assertSame(0, $dto->pendingActivities);
        self::assertSame(0.0, $dto->percentage);
        self::assertNull($dto->timecompleted);
        self::assertFalse($dto->enabled);
    }

    #[Test]
    public function constructorAcceptsAllArgs(): void
    {
        $dto = new CompletionProgressDto(
            courseid: 5,
            userid: 9,
            totalActivities: 10,
            completedActivities: 4,
            pendingActivities: 6,
            percentage: 40.0,
            timecompleted: 1700,
            enabled: true,
        );

        self::assertSame(5, $dto->courseid);
        self::assertSame(9, $dto->userid);
        self::assertSame(10, $dto->totalActivities);
        self::assertSame(4, $dto->completedActivities);
        self::assertSame(6, $dto->pendingActivities);
        self::assertSame(40.0, $dto->percentage);
        self::assertSame(1700, $dto->timecompleted);
        self::assertTrue($dto->enabled);
    }

    #[Test]
    public function fromCountsComputesPercentageAndPending(): void
    {
        $dto = CompletionProgressDto::fromCounts(1, 2, 8, 2, 1700, true);

        self::assertSame(1, $dto->courseid);
        self::assertSame(2, $dto->userid);
        self::assertSame(8, $dto->totalActivities);
        self::assertSame(2, $dto->completedActivities);
        self::assertSame(6, $dto->pendingActivities);
        self::assertSame(25.0, $dto->percentage);
        self::assertSame(1700, $dto->timecompleted);
        self::assertTrue($dto->enabled);
    }

    #[Test]
    public function fromCountsRoundsPercentageToTwoDecimals(): void
    {
        $dto = CompletionProgressDto::fromCounts(1, 2, 3, 1);

        self::assertSame(33.33, $dto->percentage);
        self::assertNull($dto->timecompleted);
        self::assertFalse($dto->enabled);
    }

    #[Test]
    public function fromCountsClampsNegativeTotalToZeroAndYieldsZeroPercentage(): void
    {
        $dto = CompletionProgressDto::fromCounts(1, 2, -5, 3);

        self::assertSame(0, $dto->totalActivities);
        self::assertSame(0, $dto->completedActivities);
        self::assertSame(0, $dto->pendingActivities);
        self::assertSame(0.0, $dto->percentage);
    }

    #[Test]
    public function fromCountsClampsCompletedToTotal(): void
    {
        $dto = CompletionProgressDto::fromCounts(1, 2, 4, 10);

        self::assertSame(4, $dto->completedActivities);
        self::assertSame(0, $dto->pendingActivities);
        self::assertSame(100.0, $dto->percentage);
    }

    #[Test]
    public function fromCountsClampsNegativeCompletedToZero(): void
    {
        $dto = CompletionProgressDto::fromCounts(1, 2, 4, -3);

        self::assertSame(0, $dto->completedActivities);
        self::assertSame(4, $dto->pendingActivities);
        self::assertSame(0.0, $dto->percentage);
    }

    #[Test]
    public function isCompleteTrueWhenTimecompletedPositive(): void
    {
        self::assertTrue((new CompletionProgressDto(timecompleted: 100))->isComplete());
    }

    #[Test]
    public function isCompleteFalseWhenTimecompletedNull(): void
    {
        self::assertFalse((new CompletionProgressDto())->isComplete());
    }

    #[Test]
    public function isCompleteFalseWhenTimecompletedZero(): void
    {
        self::assertFalse((new CompletionProgressDto(timecompleted: 0))->isComplete());
    }

    #[Test]
    public function isEmptyTrueWhenNoCompletedActivities(): void
    {
        self::assertTrue((new CompletionProgressDto(completedActivities: 0))->isEmpty());
    }

    #[Test]
    public function isEmptyFalseWhenSomeCompleted(): void
    {
        self::assertFalse((new CompletionProgressDto(completedActivities: 1))->isEmpty());
    }

    #[Test]
    public function toArrayMapsToSnakeCaseKeys(): void
    {
        $dto = new CompletionProgressDto(
            courseid: 5,
            userid: 9,
            totalActivities: 10,
            completedActivities: 4,
            pendingActivities: 6,
            percentage: 40.0,
            timecompleted: 1700,
            enabled: true,
        );

        self::assertSame([
            'courseid' => 5,
            'userid' => 9,
            'total_activities' => 10,
            'completed_activities' => 4,
            'pending_activities' => 6,
            'percentage' => 40.0,
            'timecompleted' => 1700,
            'enabled' => true,
        ], $dto->toArray());
    }

    #[Test]
    public function toObjectMirrorsToArray(): void
    {
        $dto = new CompletionProgressDto(courseid: 3, userid: 7, totalActivities: 2, completedActivities: 1, pendingActivities: 1, percentage: 50.0);

        $obj = $dto->toObject();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertSame(3, $obj->courseid);
        self::assertSame(7, $obj->userid);
        self::assertSame(2, $obj->total_activities);
        self::assertSame(1, $obj->completed_activities);
        self::assertSame(1, $obj->pending_activities);
        self::assertSame(50.0, $obj->percentage);
        self::assertNull($obj->timecompleted);
        self::assertFalse($obj->enabled);
    }
}
