<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform\Enum;

use Middag\Moodle\Domain\Platform\Enum\CheckResultStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CheckResultStatus wraps Moodle's `\core\check\result` status constants. Every
 * case is exercised across isHealthy(), isCriticalOrWorse(), severity() and
 * label(), plus resolve()'s tryFrom hit and its UNKNOWN fallback.
 *
 * @internal
 */
#[CoversClass(CheckResultStatus::class)]
final class CheckResultStatusCoverageTest extends TestCase
{
    #[Test]
    public function testBackingValues(): void
    {
        self::assertSame('na', CheckResultStatus::Na->value);
        self::assertSame('ok', CheckResultStatus::Ok->value);
        self::assertSame('info', CheckResultStatus::Info->value);
        self::assertSame('unknown', CheckResultStatus::Unknown->value);
        self::assertSame('warning', CheckResultStatus::Warning->value);
        self::assertSame('error', CheckResultStatus::Error->value);
        self::assertSame('critical', CheckResultStatus::Critical->value);
    }

    #[Test]
    public function testIsHealthyIsTrueOnlyForNaOkAndInfo(): void
    {
        self::assertTrue(CheckResultStatus::Na->isHealthy());
        self::assertTrue(CheckResultStatus::Ok->isHealthy());
        self::assertTrue(CheckResultStatus::Info->isHealthy());

        self::assertFalse(CheckResultStatus::Unknown->isHealthy());
        self::assertFalse(CheckResultStatus::Warning->isHealthy());
        self::assertFalse(CheckResultStatus::Error->isHealthy());
        self::assertFalse(CheckResultStatus::Critical->isHealthy());
    }

    #[Test]
    public function testIsCriticalOrWorseIsTrueOnlyForErrorAndCritical(): void
    {
        self::assertTrue(CheckResultStatus::Error->isCriticalOrWorse());
        self::assertTrue(CheckResultStatus::Critical->isCriticalOrWorse());

        self::assertFalse(CheckResultStatus::Na->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::Ok->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::Info->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::Unknown->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::Warning->isCriticalOrWorse());
    }

    #[Test]
    public function testSeverityRanksEveryCaseFromLeastToMostSevere(): void
    {
        self::assertSame(0, CheckResultStatus::Na->severity());
        self::assertSame(1, CheckResultStatus::Ok->severity());
        self::assertSame(2, CheckResultStatus::Info->severity());
        self::assertSame(3, CheckResultStatus::Unknown->severity());
        self::assertSame(4, CheckResultStatus::Warning->severity());
        self::assertSame(5, CheckResultStatus::Error->severity());
        self::assertSame(6, CheckResultStatus::Critical->severity());
    }

    #[Test]
    public function testLabelReturnsAHumanReadableStringForEveryCase(): void
    {
        self::assertSame('N/A', CheckResultStatus::Na->label());
        self::assertSame('OK', CheckResultStatus::Ok->label());
        self::assertSame('Info', CheckResultStatus::Info->label());
        self::assertSame('Unknown', CheckResultStatus::Unknown->label());
        self::assertSame('Warning', CheckResultStatus::Warning->label());
        self::assertSame('Error', CheckResultStatus::Error->label());
        self::assertSame('Critical', CheckResultStatus::Critical->label());
    }

    #[Test]
    public function testResolveReturnsTheMatchingCaseForAKnownValue(): void
    {
        self::assertSame(CheckResultStatus::Warning, CheckResultStatus::resolve('warning'));
        self::assertSame(CheckResultStatus::Critical, CheckResultStatus::resolve('critical'));
    }

    #[Test]
    public function testResolveFallsBackToUnknownForAnUnrecognizedValue(): void
    {
        self::assertSame(CheckResultStatus::Unknown, CheckResultStatus::resolve('not-a-status'));
        self::assertSame(CheckResultStatus::Unknown, CheckResultStatus::resolve(''));
    }
}
