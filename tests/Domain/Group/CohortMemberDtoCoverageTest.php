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

use Middag\Moodle\Domain\Group\CohortMemberDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CohortMemberDto is a pure cohort-membership record extending the framework
 * AbstractDto. The constructor promotes three scalar fields and toArray()
 * projects them; both are exercised without any Moodle runtime.
 *
 * @internal
 */
#[CoversClass(CohortMemberDto::class)]
final class CohortMemberDtoCoverageTest extends TestCase
{
    #[Test]
    public function defaultsToZeroes(): void
    {
        $dto = new CohortMemberDto();

        self::assertSame(0, $dto->cohortid);
        self::assertSame(0, $dto->userid);
        self::assertSame(0, $dto->timeadded);
    }

    #[Test]
    public function constructorAssignsAllPromotedProperties(): void
    {
        $dto = new CohortMemberDto(
            cohortid: 8,
            userid: 21,
            timeadded: 1_699_999_999,
        );

        self::assertSame(8, $dto->cohortid);
        self::assertSame(21, $dto->userid);
        self::assertSame(1_699_999_999, $dto->timeadded);
    }

    #[Test]
    public function toArrayProjectsEveryFieldWithExpectedKeys(): void
    {
        $dto = new CohortMemberDto(
            cohortid: 5,
            userid: 6,
            timeadded: 1_640_000_000,
        );

        self::assertSame([
            'cohortid' => 5,
            'userid' => 6,
            'timeadded' => 1_640_000_000,
        ], $dto->toArray());
    }

    #[Test]
    public function jsonSerializeDelegatesToToArray(): void
    {
        $dto = new CohortMemberDto(cohortid: 1, userid: 2, timeadded: 3);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
