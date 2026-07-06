<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform;

use Middag\Moodle\Domain\Platform\CheckResultDto;
use Middag\Moodle\Domain\Platform\CheckResultStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CheckResultDto is a typed projection of a Moodle system-check result. Covered
 * through its defaults, isHealthy() delegating to the status enum (both true and
 * false), and the toArray() projection with and without a details string.
 *
 * @internal
 */
#[CoversClass(CheckResultDto::class)]
final class CheckResultDtoCoverageTest extends TestCase
{
    #[Test]
    public function testDefaultsAreAnEmptyUnknownResult(): void
    {
        $dto = new CheckResultDto();

        self::assertSame('', $dto->checkId);
        self::assertSame(CheckResultStatus::UNKNOWN, $dto->status);
        self::assertSame('', $dto->summary);
        self::assertNull($dto->details);
        self::assertNull($dto->timecreated);
    }

    #[Test]
    public function testConstructorAssignsEveryProvidedValue(): void
    {
        $dto = new CheckResultDto(
            checkId: 'core_check_dbschema',
            status: CheckResultStatus::OK,
            summary: 'Schema is up to date',
            details: 'All tables match the install schema.',
            timecreated: 1_700_000_000,
        );

        self::assertSame('core_check_dbschema', $dto->checkId);
        self::assertSame(CheckResultStatus::OK, $dto->status);
        self::assertSame('Schema is up to date', $dto->summary);
        self::assertSame('All tables match the install schema.', $dto->details);
        self::assertSame(1_700_000_000, $dto->timecreated);
    }

    #[Test]
    public function testIsHealthyIsTrueForAHealthyStatus(): void
    {
        $dto = new CheckResultDto(checkId: 'ok', status: CheckResultStatus::OK);

        self::assertTrue($dto->isHealthy());
    }

    #[Test]
    public function testIsHealthyIsFalseForAnUnhealthyStatus(): void
    {
        $dto = new CheckResultDto(checkId: 'boom', status: CheckResultStatus::ERROR);

        self::assertFalse($dto->isHealthy());
    }

    #[Test]
    public function testToArrayProjectsEveryFieldWithTheEnumValue(): void
    {
        $dto = new CheckResultDto(
            checkId: 'core_check_dbschema',
            status: CheckResultStatus::WARNING,
            summary: 'Schema drift detected',
            details: 'Column X missing.',
            timecreated: 1_700_000_000,
        );

        self::assertSame([
            'check_id' => 'core_check_dbschema',
            'status' => CheckResultStatus::WARNING->value,
            'summary' => 'Schema drift detected',
            'details' => 'Column X missing.',
            'timecreated' => 1_700_000_000,
        ], $dto->toArray());
    }

    #[Test]
    public function testToArrayKeepsNullDetailsAndTimecreated(): void
    {
        $dto = new CheckResultDto(
            checkId: 'core_check_dbschema',
            status: CheckResultStatus::NA,
            summary: 'Not applicable',
        );

        $array = $dto->toArray();

        self::assertNull($array['details']);
        self::assertNull($array['timecreated']);
        self::assertSame('na', $array['status']);
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new CheckResultDto(checkId: 'ok', status: CheckResultStatus::INFO);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }
}
