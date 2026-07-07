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

use Middag\Moodle\Domain\Platform\CheckResultStatus;
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
        self::assertSame('na', CheckResultStatus::NA->value);
        self::assertSame('ok', CheckResultStatus::OK->value);
        self::assertSame('info', CheckResultStatus::INFO->value);
        self::assertSame('unknown', CheckResultStatus::UNKNOWN->value);
        self::assertSame('warning', CheckResultStatus::WARNING->value);
        self::assertSame('error', CheckResultStatus::ERROR->value);
        self::assertSame('critical', CheckResultStatus::CRITICAL->value);
    }

    #[Test]
    public function testIsHealthyIsTrueOnlyForNaOkAndInfo(): void
    {
        self::assertTrue(CheckResultStatus::NA->isHealthy());
        self::assertTrue(CheckResultStatus::OK->isHealthy());
        self::assertTrue(CheckResultStatus::INFO->isHealthy());

        self::assertFalse(CheckResultStatus::UNKNOWN->isHealthy());
        self::assertFalse(CheckResultStatus::WARNING->isHealthy());
        self::assertFalse(CheckResultStatus::ERROR->isHealthy());
        self::assertFalse(CheckResultStatus::CRITICAL->isHealthy());
    }

    #[Test]
    public function testIsCriticalOrWorseIsTrueOnlyForErrorAndCritical(): void
    {
        self::assertTrue(CheckResultStatus::ERROR->isCriticalOrWorse());
        self::assertTrue(CheckResultStatus::CRITICAL->isCriticalOrWorse());

        self::assertFalse(CheckResultStatus::NA->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::OK->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::INFO->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::UNKNOWN->isCriticalOrWorse());
        self::assertFalse(CheckResultStatus::WARNING->isCriticalOrWorse());
    }

    #[Test]
    public function testSeverityRanksEveryCaseFromLeastToMostSevere(): void
    {
        self::assertSame(0, CheckResultStatus::NA->severity());
        self::assertSame(1, CheckResultStatus::OK->severity());
        self::assertSame(2, CheckResultStatus::INFO->severity());
        self::assertSame(3, CheckResultStatus::UNKNOWN->severity());
        self::assertSame(4, CheckResultStatus::WARNING->severity());
        self::assertSame(5, CheckResultStatus::ERROR->severity());
        self::assertSame(6, CheckResultStatus::CRITICAL->severity());
    }

    #[Test]
    public function testLabelReturnsAHumanReadableStringForEveryCase(): void
    {
        self::assertSame('N/A', CheckResultStatus::NA->label());
        self::assertSame('OK', CheckResultStatus::OK->label());
        self::assertSame('Info', CheckResultStatus::INFO->label());
        self::assertSame('Unknown', CheckResultStatus::UNKNOWN->label());
        self::assertSame('Warning', CheckResultStatus::WARNING->label());
        self::assertSame('Error', CheckResultStatus::ERROR->label());
        self::assertSame('Critical', CheckResultStatus::CRITICAL->label());
    }

    #[Test]
    public function testResolveReturnsTheMatchingCaseForAKnownValue(): void
    {
        self::assertSame(CheckResultStatus::WARNING, CheckResultStatus::resolve('warning'));
        self::assertSame(CheckResultStatus::CRITICAL, CheckResultStatus::resolve('critical'));
    }

    #[Test]
    public function testResolveFallsBackToUnknownForAnUnrecognizedValue(): void
    {
        self::assertSame(CheckResultStatus::UNKNOWN, CheckResultStatus::resolve('not-a-status'));
        self::assertSame(CheckResultStatus::UNKNOWN, CheckResultStatus::resolve(''));
    }
}
