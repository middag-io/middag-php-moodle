<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Calendar;

use Middag\Framework\Shared\Dto\AbstractDto;
use Middag\Moodle\Domain\Calendar\CalendarEventDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(CalendarEventDto::class)]
final class CalendarEventDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructsWithDefaultValues(): void
    {
        $dto = new CalendarEventDto();

        self::assertNull($dto->id);
        self::assertSame('', $dto->name);
        self::assertSame('', $dto->description);
        self::assertSame(1, $dto->format);
        self::assertSame('user', $dto->eventtype);
        self::assertSame(0, $dto->timestart);
        self::assertSame(0, $dto->timeduration);
        self::assertNull($dto->courseid);
        self::assertNull($dto->groupid);
        self::assertNull($dto->userid);
        self::assertTrue($dto->visible);
        self::assertNull($dto->categoryid);
        self::assertSame(0, $dto->repeats);
    }

    #[Test]
    public function testConstructsWithAllArguments(): void
    {
        $dto = new CalendarEventDto(
            id: 55,
            name: 'Kickoff',
            description: '<p>Intro</p>',
            format: 2,
            eventtype: 'course',
            timestart: 1_700_000_000,
            timeduration: 3600,
            courseid: 7,
            groupid: 3,
            userid: 9,
            visible: false,
            categoryid: 4,
            repeats: 5,
        );

        self::assertSame(55, $dto->id);
        self::assertSame('Kickoff', $dto->name);
        self::assertSame('<p>Intro</p>', $dto->description);
        self::assertSame(2, $dto->format);
        self::assertSame('course', $dto->eventtype);
        self::assertSame(1_700_000_000, $dto->timestart);
        self::assertSame(3600, $dto->timeduration);
        self::assertSame(7, $dto->courseid);
        self::assertSame(3, $dto->groupid);
        self::assertSame(9, $dto->userid);
        self::assertFalse($dto->visible);
        self::assertSame(4, $dto->categoryid);
        self::assertSame(5, $dto->repeats);
    }

    #[Test]
    public function testIsAnAbstractDto(): void
    {
        self::assertInstanceOf(AbstractDto::class, new CalendarEventDto());
    }

    #[Test]
    public function testToArrayExposesTheSelectedFields(): void
    {
        $dto = new CalendarEventDto(
            id: 12,
            name: 'Deadline',
            description: 'ignored by toArray',
            eventtype: 'group',
            timestart: 1000,
            timeduration: 900,
            courseid: 6,
            userid: 8,
            visible: false,
        );

        self::assertSame([
            'id' => 12,
            'name' => 'Deadline',
            'eventtype' => 'group',
            'timestart' => 1000,
            'timeduration' => 900,
            'courseid' => 6,
            'userid' => 8,
            'visible' => false,
        ], $dto->toArray());
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new CalendarEventDto(id: 3, name: 'JSON');

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }

    #[Test]
    public function testToObjectMirrorsToArray(): void
    {
        $dto = new CalendarEventDto(
            id: 21,
            name: 'Sync',
            eventtype: 'site',
            timestart: 2000,
            timeduration: 0,
            visible: true,
        );

        $object = $dto->toObject();

        self::assertInstanceOf(stdClass::class, $object);
        self::assertSame(21, $object->id);
        self::assertSame('Sync', $object->name);
        self::assertSame('site', $object->eventtype);
        self::assertSame(2000, $object->timestart);
        self::assertSame(0, $object->timeduration);
        self::assertNull($object->courseid);
        self::assertNull($object->userid);
        self::assertTrue($object->visible);
        self::assertSame($dto->toArray(), (array) $object);
    }
}
