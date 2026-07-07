<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\User;

use Middag\Moodle\Domain\User\UserProfileFieldDataDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(UserProfileFieldDataDto::class)]
final class UserProfileFieldDataDtoCoverageTest extends TestCase
{
    #[Test]
    public function constructorAppliesDefaults(): void
    {
        $dto = new UserProfileFieldDataDto();

        self::assertNull($dto->id);
        self::assertSame(0, $dto->userid);
        self::assertSame(0, $dto->fieldid);
        self::assertSame('', $dto->shortname);
        self::assertSame('', $dto->data);
        self::assertSame(0, $dto->dataformat);
    }

    #[Test]
    public function constructorAssignsAllValues(): void
    {
        $dto = new UserProfileFieldDataDto(
            id: 12,
            userid: 5,
            fieldid: 3,
            shortname: 'department',
            data: 'Engineering',
            dataformat: 1,
        );

        self::assertSame(12, $dto->id);
        self::assertSame(5, $dto->userid);
        self::assertSame(3, $dto->fieldid);
        self::assertSame('department', $dto->shortname);
        self::assertSame('Engineering', $dto->data);
        self::assertSame(1, $dto->dataformat);
    }

    #[Test]
    public function isEmptyTrueWhenDataBlank(): void
    {
        self::assertTrue((new UserProfileFieldDataDto(data: ''))->isEmpty());
    }

    #[Test]
    public function isEmptyFalseWhenDataPresent(): void
    {
        self::assertFalse((new UserProfileFieldDataDto(data: 'x'))->isEmpty());
    }

    #[Test]
    public function toArrayReturnsAllFields(): void
    {
        $dto = new UserProfileFieldDataDto(
            id: 12,
            userid: 5,
            fieldid: 3,
            shortname: 'department',
            data: 'Engineering',
            dataformat: 1,
        );

        self::assertSame([
            'id' => 12,
            'userid' => 5,
            'fieldid' => 3,
            'shortname' => 'department',
            'data' => 'Engineering',
            'dataformat' => 1,
        ], $dto->toArray());
    }

    #[Test]
    public function toObjectMirrorsArrayAsStdClass(): void
    {
        $dto = new UserProfileFieldDataDto(
            userid: 8,
            fieldid: 2,
            shortname: 'bio',
            data: '',
            dataformat: 0,
        );

        $obj = $dto->toObject();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertNull($obj->id);
        self::assertSame(8, $obj->userid);
        self::assertSame(2, $obj->fieldid);
        self::assertSame('bio', $obj->shortname);
        self::assertSame('', $obj->data);
        self::assertSame(0, $obj->dataformat);
    }
}
