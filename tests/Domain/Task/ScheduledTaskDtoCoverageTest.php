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

use Middag\Moodle\Domain\Task\ScheduledTaskDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ScheduledTaskDto is an immutable snapshot of a configured scheduled task. Its
 * array/object projection intentionally exposes only a summary subset.
 *
 * @internal
 */
#[CoversClass(ScheduledTaskDto::class)]
final class ScheduledTaskDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorExposesEveryPromotedProperty(): void
    {
        $dto = new ScheduledTaskDto(
            classname: '\core\task\cron',
            component: 'core',
            lastruntime: 1_699_000_000,
            nextruntime: 1_700_000_000,
            disabled: false,
            minute: '*/5',
            hour: '*',
            day: '1',
            month: '*',
            dayofweek: '0',
            customised: true,
            faildelay: 30,
        );

        self::assertSame('\core\task\cron', $dto->classname);
        self::assertSame('core', $dto->component);
        self::assertSame(1_699_000_000, $dto->lastruntime);
        self::assertSame(1_700_000_000, $dto->nextruntime);
        self::assertFalse($dto->disabled);
        self::assertSame('*/5', $dto->minute);
        self::assertSame('*', $dto->hour);
        self::assertSame('1', $dto->day);
        self::assertSame('*', $dto->month);
        self::assertSame('0', $dto->dayofweek);
        self::assertTrue($dto->customised);
        self::assertSame(30, $dto->faildelay);
    }

    #[Test]
    public function testConstructorAcceptsNullLastruntime(): void
    {
        $dto = new ScheduledTaskDto('\c', 'core', null, 0, false, '*', '*', '*', '*', '*', false, 0);

        self::assertNull($dto->lastruntime);
    }

    #[Test]
    public function testToArrayReturnsTheProjectedSubsetOnly(): void
    {
        $dto = new ScheduledTaskDto('\c', 'core', 111, 222, true, '*', '*', '*', '*', '*', false, 0);

        self::assertSame([
            'classname' => '\c',
            'component' => 'core',
            'lastruntime' => 111,
            'nextruntime' => 222,
            'disabled' => true,
        ], $dto->toArray());
    }

    #[Test]
    public function testToObjectMirrorsTheArrayProjection(): void
    {
        $dto = new ScheduledTaskDto('\c', 'core', null, 222, false, '*', '*', '*', '*', '*', false, 0);

        $obj = $dto->toObject();

        self::assertSame('\c', $obj->classname);
        self::assertSame('core', $obj->component);
        self::assertNull($obj->lastruntime);
        self::assertSame(222, $obj->nextruntime);
        self::assertFalse($obj->disabled);
        self::assertFalse(property_exists($obj, 'minute'));
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new ScheduledTaskDto('\c', 'core', 1, 2, false, '*', '*', '*', '*', '*', false, 0);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
