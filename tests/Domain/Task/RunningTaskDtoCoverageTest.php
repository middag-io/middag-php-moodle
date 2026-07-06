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

use Middag\Moodle\Domain\Task\RunningTaskDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RunningTaskDto is an immutable snapshot of a currently-executing scheduled or
 * adhoc task, exposing array/object/JSON projections.
 *
 * @internal
 */
#[CoversClass(RunningTaskDto::class)]
final class RunningTaskDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorExposesEveryPromotedProperty(): void
    {
        $dto = new RunningTaskDto(
            id: 7,
            classname: '\core\task\cron',
            type: 'scheduled',
            timestarted: 1_700_000_000,
            hostname: 'web-01',
            pid: 4242,
            elapsed: 12,
        );

        self::assertSame(7, $dto->id);
        self::assertSame('\core\task\cron', $dto->classname);
        self::assertSame('scheduled', $dto->type);
        self::assertSame(1_700_000_000, $dto->timestarted);
        self::assertSame('web-01', $dto->hostname);
        self::assertSame(4242, $dto->pid);
        self::assertSame(12, $dto->elapsed);
    }

    #[Test]
    public function testToArrayReturnsEveryFieldKeyedByName(): void
    {
        $dto = new RunningTaskDto(7, '\c', 'adhoc', 100, 'h', 5, 9);

        self::assertSame([
            'id' => 7,
            'classname' => '\c',
            'type' => 'adhoc',
            'timestarted' => 100,
            'hostname' => 'h',
            'pid' => 5,
            'elapsed' => 9,
        ], $dto->toArray());
    }

    #[Test]
    public function testToObjectMirrorsTheArrayProjection(): void
    {
        $dto = new RunningTaskDto(7, '\c', 'adhoc', 100, 'h', 5, 9);

        $obj = $dto->toObject();

        self::assertSame(7, $obj->id);
        self::assertSame('\c', $obj->classname);
        self::assertSame('adhoc', $obj->type);
        self::assertSame(100, $obj->timestarted);
        self::assertSame('h', $obj->hostname);
        self::assertSame(5, $obj->pid);
        self::assertSame(9, $obj->elapsed);
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new RunningTaskDto(7, '\c', 'adhoc', 100, 'h', 5, 9);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
