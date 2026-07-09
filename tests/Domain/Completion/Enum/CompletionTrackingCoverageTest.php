<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Completion\Enum;

use Middag\Moodle\Domain\Completion\Enum\CompletionTracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CompletionTracking::class)]
final class CompletionTrackingCoverageTest extends TestCase
{
    #[Test]
    public function allCasesExistWithMoodleValues(): void
    {
        $cases = CompletionTracking::cases();

        self::assertCount(3, $cases);
        self::assertSame(0, CompletionTracking::None->value);
        self::assertSame(1, CompletionTracking::Manual->value);
        self::assertSame(2, CompletionTracking::Automatic->value);
    }

    #[Test]
    public function isTrackedIsFalseOnlyForNone(): void
    {
        self::assertFalse(CompletionTracking::None->isTracked());
        self::assertTrue(CompletionTracking::Manual->isTracked());
        self::assertTrue(CompletionTracking::Automatic->isTracked());
    }

    #[Test]
    public function isManualIsTrueOnlyForManual(): void
    {
        self::assertFalse(CompletionTracking::None->isManual());
        self::assertTrue(CompletionTracking::Manual->isManual());
        self::assertFalse(CompletionTracking::Automatic->isManual());
    }

    #[Test]
    public function isAutomaticIsTrueOnlyForAutomatic(): void
    {
        self::assertFalse(CompletionTracking::None->isAutomatic());
        self::assertFalse(CompletionTracking::Manual->isAutomatic());
        self::assertTrue(CompletionTracking::Automatic->isAutomatic());
    }

    #[Test]
    public function labelReturnsHumanReadableStringForEachCase(): void
    {
        self::assertSame('Not tracked', CompletionTracking::None->label());
        self::assertSame('Manual', CompletionTracking::Manual->label());
        self::assertSame('Automatic', CompletionTracking::Automatic->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        self::assertSame(CompletionTracking::None, CompletionTracking::resolve(0));
        self::assertSame(CompletionTracking::Manual, CompletionTracking::resolve(1));
        self::assertSame(CompletionTracking::Automatic, CompletionTracking::resolve(2));
    }

    #[Test]
    public function resolveDefaultsToNoneForUnknownValue(): void
    {
        self::assertSame(CompletionTracking::None, CompletionTracking::resolve(99));
        self::assertSame(CompletionTracking::None, CompletionTracking::resolve(-1));
    }
}
