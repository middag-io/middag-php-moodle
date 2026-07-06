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

use Middag\Moodle\Domain\Task\AdhocTaskDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AdhocTaskDto is an immutable snapshot of an adhoc task. Its array/object
 * projection intentionally omits customdata and faildelay.
 *
 * @internal
 */
#[CoversClass(AdhocTaskDto::class)]
final class AdhocTaskDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorExposesEveryPromotedProperty(): void
    {
        $dto = new AdhocTaskDto(
            classname: '\core\task\adhoc',
            component: 'core',
            userid: 123,
            nextruntime: 1_700_000_000,
            customdata: '{"a":1}',
            faildelay: 60,
            running: true,
            id: 9,
        );

        self::assertSame('\core\task\adhoc', $dto->classname);
        self::assertSame('core', $dto->component);
        self::assertSame(123, $dto->userid);
        self::assertSame(1_700_000_000, $dto->nextruntime);
        self::assertSame('{"a":1}', $dto->customdata);
        self::assertSame(60, $dto->faildelay);
        self::assertTrue($dto->running);
        self::assertSame(9, $dto->id);
    }

    #[Test]
    public function testConstructorAcceptsNullUserid(): void
    {
        $dto = new AdhocTaskDto('\c', 'core', null, 0, '{}', 0, false, 0);

        self::assertNull($dto->userid);
    }

    #[Test]
    public function testToArrayReturnsTheProjectedSubsetOnly(): void
    {
        $dto = new AdhocTaskDto('\c', 'core', 123, 42, '{"a":1}', 60, true, 9);

        self::assertSame([
            'id' => 9,
            'classname' => '\c',
            'component' => 'core',
            'userid' => 123,
            'nextruntime' => 42,
            'running' => true,
        ], $dto->toArray());
    }

    #[Test]
    public function testToObjectMirrorsTheArrayProjection(): void
    {
        $dto = new AdhocTaskDto('\c', 'core', null, 42, '{}', 0, false, 9);

        $obj = $dto->toObject();

        self::assertSame(9, $obj->id);
        self::assertSame('\c', $obj->classname);
        self::assertSame('core', $obj->component);
        self::assertNull($obj->userid);
        self::assertSame(42, $obj->nextruntime);
        self::assertFalse($obj->running);
        self::assertFalse(property_exists($obj, 'customdata'));
        self::assertFalse(property_exists($obj, 'faildelay'));
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new AdhocTaskDto('\c', 'core', 1, 42, '{}', 0, true, 9);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
