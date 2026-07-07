<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Task;

use Middag\Moodle\Domain\Task\TaskSummaryDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * TaskSummaryDto is an immutable summary of adhoc tasks aggregated by
 * component and classname, exposing array/object/JSON projections.
 *
 * @internal
 */
#[CoversClass(TaskSummaryDto::class)]
final class TaskSummaryDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorExposesEveryPromotedProperty(): void
    {
        $dto = new TaskSummaryDto(
            component: 'core_course',
            classname: '\core_course\task\cleanup',
            count: 5,
            running: 2,
            failed: 1,
            due: 3,
            nextruntime: 1_700_000_000,
            stopped: true,
        );

        self::assertSame('core_course', $dto->component);
        self::assertSame('\core_course\task\cleanup', $dto->classname);
        self::assertSame(5, $dto->count);
        self::assertSame(2, $dto->running);
        self::assertSame(1, $dto->failed);
        self::assertSame(3, $dto->due);
        self::assertSame(1_700_000_000, $dto->nextruntime);
        self::assertTrue($dto->stopped);
    }

    #[Test]
    public function testToArrayReturnsEveryFieldKeyedByName(): void
    {
        $dto = new TaskSummaryDto('core', '\c', 5, 2, 1, 3, 42, false);

        self::assertSame([
            'component' => 'core',
            'classname' => '\c',
            'count' => 5,
            'running' => 2,
            'failed' => 1,
            'due' => 3,
            'nextruntime' => 42,
            'stopped' => false,
        ], $dto->toArray());
    }

    #[Test]
    public function testToObjectMirrorsTheArrayProjection(): void
    {
        $dto = new TaskSummaryDto('core', '\c', 5, 2, 1, 3, 42, true);

        $obj = $dto->toObject();

        self::assertSame('core', $obj->component);
        self::assertSame('\c', $obj->classname);
        self::assertSame(5, $obj->count);
        self::assertSame(2, $obj->running);
        self::assertSame(1, $obj->failed);
        self::assertSame(3, $obj->due);
        self::assertSame(42, $obj->nextruntime);
        self::assertTrue($obj->stopped);
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new TaskSummaryDto('core', '\c', 5, 2, 1, 3, 42, false);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
