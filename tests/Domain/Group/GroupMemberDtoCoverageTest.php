<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Group;

use Middag\Moodle\Domain\Group\GroupMemberDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GroupMemberDto is a pure membership record extending the framework AbstractDto.
 * The constructor promotes five scalar fields and toArray() projects them; both
 * are exercised without any Moodle runtime.
 *
 * @internal
 */
#[CoversClass(GroupMemberDto::class)]
final class GroupMemberDtoCoverageTest extends TestCase
{
    #[Test]
    public function defaultsToZeroesAndEmptyComponent(): void
    {
        $dto = new GroupMemberDto();

        self::assertSame(0, $dto->groupid);
        self::assertSame(0, $dto->userid);
        self::assertSame(0, $dto->timeadded);
        self::assertSame('', $dto->component);
        self::assertSame(0, $dto->itemid);
    }

    #[Test]
    public function constructorAssignsAllPromotedProperties(): void
    {
        $dto = new GroupMemberDto(
            groupid: 12,
            userid: 34,
            timeadded: 1_700_000_000,
            component: 'mod_unidade',
            itemid: 56,
        );

        self::assertSame(12, $dto->groupid);
        self::assertSame(34, $dto->userid);
        self::assertSame(1_700_000_000, $dto->timeadded);
        self::assertSame('mod_unidade', $dto->component);
        self::assertSame(56, $dto->itemid);
    }

    #[Test]
    public function toArrayProjectsEveryFieldWithExpectedKeys(): void
    {
        $dto = new GroupMemberDto(
            groupid: 7,
            userid: 99,
            timeadded: 1_650_000_000,
            component: 'core',
            itemid: 3,
        );

        self::assertSame([
            'groupid' => 7,
            'userid' => 99,
            'timeadded' => 1_650_000_000,
            'component' => 'core',
            'itemid' => 3,
        ], $dto->toArray());
    }

    #[Test]
    public function jsonSerializeDelegatesToToArray(): void
    {
        $dto = new GroupMemberDto(groupid: 1, userid: 2, timeadded: 3, component: 'x', itemid: 4);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
